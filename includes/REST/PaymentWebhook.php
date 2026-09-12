<?php

namespace BusinessBuilderCore\REST;

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\Checkout\TransactionSynchronizer;
use BusinessBuilderCore\Core\Notifications\NotificationManager;
use BusinessBuilderCore\Core\Notifications\Notification;
use BusinessBuilderCore\Core\Audit\AuditLog;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Payment webhook REST endpoint.
 *
 * Route: /wp-json/business-builder/v1/payment/webhook/{gateway}
 *
 * Security model (spec 13 / 14):
 *   - The route is public (payment providers cannot authenticate as a
 *     WordPress user) but every payload is delegated to the gateway's
 *     verify_payment(), which is responsible for cryptographic checks.
 *   - A payload is NEVER trusted to set a "paid" state on its own.
 *   - Processing is idempotent: repeated webhooks for an already-paid
 *     transaction are acknowledged without further side effects.
 *
 * The gateway does the verification; this class only orchestrates.
 */
class PaymentWebhook {

    /**
     * REST namespace.
     */
    private const REST_NAMESPACE = 'business-builder/v1';

    /**
     * Payment manager.
     */
    protected PaymentManager $payments;

    /**
     * Notification manager.
     */
    protected NotificationManager $notifications;

    /**
     * Audit log.
     */
    protected AuditLog $audit;

    /**
     * Transaction synchronizer (Phase F, optional).
     *
     * When present, verification results are applied through the single
     * synchronizer (which also updates appointment meta). When absent,
     * the legacy consultation-only path is used unchanged.
     */
    protected ?TransactionSynchronizer $synchronizer = null;

    /**
     * Constructor.
     *
     * @param PaymentManager               $payments      Payments.
     * @param NotificationManager          $notifications Notifications.
     * @param AuditLog                     $audit         Audit log.
     * @param TransactionSynchronizer|null $synchronizer  Synchronizer (optional).
     */
    public function __construct(
        PaymentManager $payments,
        NotificationManager $notifications,
        AuditLog $audit,
        ?TransactionSynchronizer $synchronizer = null
    ) {
        $this->payments      = $payments;
        $this->notifications = $notifications;
        $this->audit         = $audit;
        $this->synchronizer  = $synchronizer;
    }

    /**
     * Register the REST route.
     */
    public function register(): void {

        add_action(
            'rest_api_init',
            array( $this, 'register_routes' )
        );
    }

    /**
     * Register the webhook route.
     */
    public function register_routes(): void {

        register_rest_route(
            self::REST_NAMESPACE,
            '/payment/webhook/(?P<gateway>[a-z0-9_\-]+)',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle' ),
                /*
                 * Public: providers cannot pass a WP capability. Real
                 * verification happens inside the gateway.
                 */
                'permission_callback' => '__return_true',
                'args'                => array(
                    'gateway' => array(
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_key',
                    ),
                ),
            )
        );
    }

    /**
     * Handle an incoming webhook.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function handle( \WP_REST_Request $request ): \WP_REST_Response {

        $gateway_id = sanitize_key( (string) $request->get_param( 'gateway' ) );

        $gateway = $this->payments->gateway( $gateway_id );

        if ( null === $gateway ) {
            return new \WP_REST_Response(
                array( 'ok' => false, 'reason' => 'unknown_gateway' ),
                404
            );
        }

        /*
         * Manual gateways have no server callback: reject anything
         * trying to auto-verify them.
         */
        if ( $gateway->is_manual() ) {
            return new \WP_REST_Response(
                array( 'ok' => false, 'reason' => 'manual_gateway' ),
                400
            );
        }

        $payload = $request->get_json_params();

        if ( ! is_array( $payload ) || empty( $payload ) ) {
            $payload = $request->get_params();
        }

        if ( ! is_array( $payload ) || empty( $payload ) ) {
            return new \WP_REST_Response(
                array( 'ok' => false, 'reason' => 'empty_payload' ),
                400
            );
        }

        /* The gateway performs signature/HMAC verification. */
        $result = $gateway->verify_payment( $payload );

        if ( ! $result->success ) {

            /*
             * Verified-as-failed (or unverified). We never advance the
             * transaction to paid. Return 200 so the provider does not
             * retry a rejected payload forever.
             */
            $this->audit->record(
                'payment.webhook_rejected',
                'payment',
                0,
                array(
                    'gateway' => $gateway_id,
                    'reason'  => $result->message,
                )
            );

            return new \WP_REST_Response(
                array( 'ok' => false, 'reason' => 'not_verified' ),
                200
            );
        }

        /*
         * Resolve the transaction by provider reference. Prefer the
         * Phase F CPT store, then fall back to the legacy option store
         * so both eras of transactions are found.
         */
        $transaction = $this->payments->store()->find_by_reference( $result->reference );

        if ( null === $transaction ) {
            $transaction = $this->payments->find_by_reference( $result->reference );
        }

        if ( null === $transaction ) {
            return new \WP_REST_Response(
                array( 'ok' => false, 'reason' => 'unknown_transaction' ),
                200
            );
        }

        /*
         * Idempotency: if already paid, acknowledge without side effects
         * so repeated webhooks are safe.
         */
        if ( 'paid' === $transaction->status ) {
            return new \WP_REST_Response(
                array( 'ok' => true, 'reason' => 'already_paid' ),
                200
            );
        }

        if ( $this->synchronizer instanceof TransactionSynchronizer ) {

            /*
             * Phase F: single synchronisation path that updates the
             * CPT-backed transaction store AND the related object meta
             * (consultation or appointment).
             */
            $this->synchronizer->apply( $transaction, $result );

        } else {

            /* Legacy consultation-only path (unchanged behaviour). */
            $this->payments->update_transaction(
                $transaction->id,
                $result->status,
                $result->reference
            );

            $this->sync_consultation(
                $transaction->consultation_id,
                $result->status
            );
        }

        /* Notify + audit (both idempotent / scoped). */
        $this->notifications->dispatch(
            new Notification(
                'payment.' . $result->status,
                sprintf(
                    /* translators: 1: status, 2: consultation id */
                    __( 'Payment %1$s for consultation #%2$d', 'business-builder' ),
                    $result->status,
                    $transaction->consultation_id
                ),
                '',
                '',
                $transaction->consultation_id,
                'payment:' . $gateway_id . ':' . $result->reference
            )
        );

        $this->audit->record(
            'payment.webhook_processed',
            'payment',
            $transaction->id,
            array(
                'gateway' => $gateway_id,
                'status'  => $result->status,
            )
        );

        return new \WP_REST_Response(
            array( 'ok' => true, 'status' => $result->status ),
            200
        );
    }

    /**
     * Update the consultation payment state to match the transaction.
     *
     * @param int    $consultation_id Consultation id.
     * @param string $status          Verified payment status.
     */
    protected function sync_consultation( int $consultation_id, string $status ): void {

        if ( $consultation_id <= 0 ) {
            return;
        }

        update_post_meta(
            $consultation_id,
            '_bb_consultation_payment_status',
            sanitize_key( $status )
        );
    }
}
