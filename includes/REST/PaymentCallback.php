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

        $result = $gateway->verify_payment( $payload );

        $reference = $result->reference;

        $transaction = '' !== $reference
            ? $this->payments->find_by_reference( $reference )
            : null;

        if ( null === $transaction ) {
            $transaction = $this->resolve_by_hint( $request );
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

        $transaction = $this->synchronizer->apply( $transaction, $result );

        if ( $result->success && 'paid' === $transaction->status ) {
            $this->notify_paid( $transaction );
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

        foreach ( array( 'bb_ref', 'merchant_reference', 'merchant_ref', 'order' ) as $key ) {

            $value = $request->get_param( $key );

            if ( is_scalar( $value ) && '' !== (string) $value ) {
                $hint = sanitize_text_field( (string) $value );
                break;
            }
        }

        if ( '' === $hint ) {
            return null;
        }

        return $this->payments->find_by_public_ref( $hint );
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
                $transaction->object_id,
                'payment:' . $transaction->gateway . ':' . $transaction->public_ref
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

        $url = home_url( '/' );

        $url = add_query_arg( 'bb_checkout', sanitize_key( $status ), $url );

        if ( '' !== $public_ref ) {
            $url = add_query_arg( 'bb_ref', $public_ref, $url );
        }

        $response = new \WP_REST_Response( array( 'ok' => true, 'redirect' => $url ), 200 );

        $response->header( 'Location', $url );

        return $response;
    }
}
