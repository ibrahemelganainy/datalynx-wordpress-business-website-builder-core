<?php

namespace BusinessBuilderCore\REST;

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\Checkout\TransactionSynchronizer;
use BusinessBuilderCore\Core\Notifications\NotificationManager;
use BusinessBuilderCore\Core\Notifications\Notification;
use BusinessBuilderCore\Core\Audit\AuditLog;

defined( 'ABSPATH' ) || exit;

/**
 * Payment browser-return callback.
 *
 * Route:
 *   GET/POST /wp-json/business-builder/v1/payment/callback/{gateway}
 *
 * This is the endpoint a redirect-based provider sends the CUSTOMER's
 * browser to after checkout (the server-to-server webhook is handled
 * separately by PaymentWebhook). It is public because the browser
 * cannot authenticate as a WordPress user, and it never trusts a
 * client-supplied "paid" flag: verification is delegated to the
 * gateway's verify_payment(), exactly like the webhook.
 *
 * After verification the customer is redirected to the site home with
 * a status flag and (when known) their public reference, so the status
 * page can show an accurate, server-verified state.
 */
class PaymentCallback {

    /**
     * REST namespace.
     */
    private const REST_NAMESPACE = 'business-builder/v1';

    /**
     * Payment manager.
     */
    protected PaymentManager $payments;

    /**
     * Synchronizer.
     */
    protected TransactionSynchronizer $synchronizer;

    /**
     * Notifications.
     */
    protected NotificationManager $notifications;

    /**
     * Audit log.
     */
    protected AuditLog $audit;

    /**
     * Constructor.
     *
     * @param PaymentManager          $payments      Payments.
     * @param TransactionSynchronizer $synchronizer  Synchronizer.
     * @param NotificationManager     $notifications Notifications.
     * @param AuditLog                $audit         Audit log.
     */
    public function __construct(
        PaymentManager $payments,
        TransactionSynchronizer $synchronizer,
        NotificationManager $notifications,
        AuditLog $audit
    ) {
        $this->payments      = $payments;
        $this->synchronizer  = $synchronizer;
        $this->notifications = $notifications;
        $this->audit         = $audit;
    }

    /**
     * Register the route.
     */
    public function register(): void {

        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register the callback route.
     */
    public function register_routes(): void {

        register_rest_route(
            self::REST_NAMESPACE,
            '/payment/callback/(?P<gateway>[a-z0-9_\-]+)',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'handle' ),
                    'permission_callback' => '__return_true',
                    'args'                => array(
                        'gateway' => array(
                            'required'          => true,
                            'sanitize_callback' => 'sanitize_key',
                        ),
                    ),
                ),
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'handle' ),
                    'permission_callback' => '__return_true',
                    'args'                => array(
                        'gateway' => array(
                            'required'          => true,
                            'sanitize_callback' => 'sanitize_key',
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Handle a browser return callback.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response|\WP_HTTP_Response
     */
    public function handle( \WP_REST_Request $request ) {

        $gateway_id = sanitize_key( (string) $request->get_param( 'gateway' ) );

        $gateway = $this->payments->gateway( $gateway_id );

        if ( null === $gateway ) {
            return $this->redirect_home( 'error' );
        }

        /*
         * Manual gateways have no provider return: a browser hitting
         * this endpoint is not a valid verification source.
         */
        if ( $gateway->is_manual() ) {
            return $this->redirect_home( 'error' );
        }

        $payload = $this->collect_payload( $request );

        /*
         * Resolve the transaction for THIS browser return.
         *
         * Order matters. Different gateways identify a return differently
         * (Stripe sends session_id, PayPal sends token, others echo a
         * merchant/order reference). We therefore resolve by the
         * gateway-independent PUBLIC reference we always place on the
         * return URL (bb_ref) FIRST, and only fall back to the provider
         * reference / hint. This keeps the callback gateway-neutral and
         * means a return always lands on the correct transaction even when
         * the provider's verify_payment() cannot (yet) return a reference
         * (e.g. the browser return arrives before the provider's final
         * state, or the payment is still pending).
         */
        $transaction = $this->resolve_by_hint( $request );

        /*
         * Verify with the provider. This is never trusted to resolve the
         * transaction on its own, and a failure NEVER marks paid.
         */
        $result = $gateway->verify_payment( $payload );

        if ( null === $transaction && '' !== $result->reference ) {
            $transaction = $this->payments->find_by_reference( $result->reference );
        }

        if ( null === $transaction ) {

            $this->audit->record(
                'payment.callback_unknown',
                'payment',
                0,
                array( 'gateway' => $gateway_id )
            );

            return $this->redirect_home( 'error' );
        }

        /*
         * Only advance the state when the provider actually verified the
         * payment. A pending/failed verification leaves the transaction
         * untouched and the customer sees the accurate pending state.
         */
        if ( $result->success ) {
            $transaction = $this->synchronizer->apply( $transaction, $result );
        }

        if ( $result->success && 'paid' === $transaction->status ) {
            $this->notify_paid( $transaction );
        } elseif ( ! $result->success && 'failed' === sanitize_key( (string) $result->status )) {
            /*
             * A provider that EXPLICITLY reports a failed payment is
             * surfaced as a notification + activity record. A merely
             * pending/unknown verification is NOT treated as a failure, so
             * this never raises a false alarm. The transaction itself is
             * advanced through the synchronizer so the state stays accurate.
             */
            $failed = $this->synchronizer->apply(
                $transaction,
                new PaymentResult( false, 'failed', (string) $result->reference, (string) $result->message )
            );

            $this->notify_failed( $failed );
        }

        $status = 'paid' === $transaction->status ? 'paid' : 'pending';

        return $this->redirect_home( $status, $transaction->public_ref );
    }

    /**
     * Collect the payload from query + body.
     *
     * @param \WP_REST_Request $request Request.
     * @return array<string, mixed>
     */
    protected function collect_payload( \WP_REST_Request $request ): array {

        $payload = $request->get_params();

        $is_array = is_array( $payload );

        if ( false === $is_array ) {
            $payload = array();
        }

        $json = $request->get_json_params();

        $json_ok = is_array( $json );

        $json_has = ! empty( $json );

        if ( $json_ok && $json_has ) {
            $payload = array_merge( $payload, $json );
        }

        return $payload;
    }

    /**
     * Best-effort resolve a transaction from a public reference hint.
     *
     * Providers commonly echo back a merchant reference (our public
     * ref). This is a convenience lookup only; the verification result
     * still decides the state.
     *
     * @param \WP_REST_Request $request Request.
     * @return PaymentTransaction|null
     */
    protected function resolve_by_hint( \WP_REST_Request $request ): ?PaymentTransaction {

        $hint = '';

        foreach ( array( 'bb_ref', 'merchant_reference', 'merchant_ref', 'merchant_order_id', 'order' ) as $key ) {

            $value = $request->get_param( $key );

            if ( is_scalar( $value ) && '' !== (string) $value ) {
                $hint = sanitize_text_field( (string) $value );
                break;
            }
        }

        if ( '' === $hint ) {
            return null;
        }

        /*
         * Prefer the CPT-backed store (the authoritative source since
         * Phase F), then fall back to the legacy option store so both eras
         * of transactions resolve. This is per-site: an option/CPT lookup
         * only ever sees the current blog's data, so one site's callback
         * cannot resolve another site's transaction on Multisite.
         */
        $transaction = $this->payments->store()->find_by_public_ref( $hint );

        if ( null === $transaction ) {
            $transaction = $this->payments->find_by_public_ref( $hint );
        }

        if ( null === $transaction ) {
            return null;
        }

        /*
         * Guard against a cross-gateway reference being replayed on this
         * route: the transaction must belong to the gateway named in the
         * URL, so a crafted /callback/{other}?...bb_ref=<txn> cannot be
         * used to probe or advance another gateway's payment.
         */
        $route_gateway = (string) $request->get_param( 'gateway' );
        $route_gateway = sanitize_key( $route_gateway );

        if ( '' !== $route_gateway && $transaction->gateway !== $route_gateway ) {
            return null;
        }

        return $transaction;
    }

    /**
     * Dispatch a "payment paid" notification.
     *
     * @param PaymentTransaction $transaction Transaction.
     */
    protected function notify_paid( PaymentTransaction $transaction ): void {

        $this->notifications->dispatch(
            new Notification(
                'payment.paid',
                sprintf(
                    /* translators: 1: gateway, 2: public reference */
                    __( 'Payment %1$s confirmed for %2$s', 'business-builder' ),
                    $transaction->gateway,
                    $transaction->public_ref
                ),
                '',
                '',
                (int) $transaction->object_id,
                'payment:' . $transaction->gateway . ':' . $transaction->public_ref,
                array(
                    'category'     => 'payment',
                    'entity_type'  => $transaction->object_type,
                    'entity_id'    => (int) $transaction->object_id,
                    'entity_label' => (string) $transaction->object_type,
                    'reference'    => (string) $transaction->public_ref,
                    'payment_ref'  => (string) $transaction->public_ref,
                    'amount'       => (string) $transaction->amount,
                    'currency'     => (string) $transaction->currency,
                    'gateway'      => (string) $transaction->gateway,
                    'actionable'   => false,
                )
            )
        );
    }

    /**
     * Dispatch a "payment failed" notification/activity record.
     *
     * @param PaymentTransaction $transaction Transaction.
     */
    protected function notify_failed( PaymentTransaction $transaction ): void {

        $this->notifications->dispatch(
            new Notification(
                'payment.failed',
                __( 'Payment failed', 'business-builder' ),
                sprintf(
                    /* translators: 1: gateway, 2: public reference */
                    __( 'The payment via %1$s for %2$s did not complete.', 'business-builder' ),
                    $transaction->gateway,
                    $transaction->public_ref
                ),
                '',
                (int) $transaction->object_id,
                'payment:failed:' . $transaction->gateway . ':' . $transaction->public_ref,
                array(
                    'category'     => 'payment',
                    'entity_type'  => $transaction->object_type,
                    'entity_id'    => (int) $transaction->object_id,
                    'entity_label' => (string) $transaction->object_type,
                    'reference'    => (string) $transaction->public_ref,
                    'payment_ref'  => (string) $transaction->public_ref,
                    'amount'       => (string) $transaction->amount,
                    'currency'     => (string) $transaction->currency,
                    'gateway'      => (string) $transaction->gateway,
                    'actionable'   => true,
                )
            )
        );
    }

    /**
     * Redirect the browser home with a status flag (and optional ref).
     *
     * @param string $status     Status flag.
     * @param string $public_ref Public reference (optional).
     * @return \WP_REST_Response
     */
    protected function redirect_home( string $status, string $public_ref = '' ): \WP_REST_Response {

        $url = $this->return_base();

        $url = add_query_arg( 'bb_checkout', sanitize_key( $status ), $url );

        if ( '' !== $public_ref ) {
            $url = add_query_arg( 'bb_ref', $public_ref, $url );
        }

        $response = new \WP_REST_Response( array( 'ok' => true, 'redirect' => $url ), 200 );

        $response->header( 'Location', $url );

        return $response;
    }

    /**
     * Where to send the customer after the callback is processed.
     *
     * Prefers the page the customer started on (bb_origin), validating it
     * against the current site host so this cannot become an open redirect.
     * Falls back to the site home.
     *
     * @return string
     */
    protected function return_base(): string {

        $origin = '';

        if ( isset( $_GET['bb_origin'] ) ) {
            $origin = sanitize_text_field( wp_unslash( $_GET['bb_origin'] ) );
        }

        if ( '' === $origin ) {
            return home_url( '/' );
        }

        /* Only accept a local path (no scheme/host) to avoid open redirects. */
        $is_path = ( 0 === strpos( $origin, '/' ) && false === strpos( $origin, '//' ) );

        if ( ! $is_path ) {
            return home_url( '/' );
        }

        return home_url( $origin );
    }
}
