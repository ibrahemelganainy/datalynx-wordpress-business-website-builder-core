<?php
namespace BusinessBuilderCore\Packs\LawFirm\Payments;

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\Checkout\CheckoutRequest;
use BusinessBuilderCore\Core\Payments\Checkout\PaymentCheckout;
use BusinessBuilderCore\Core\Audit\AuditLog;

defined( 'ABSPATH' ) || exit;

/**
 * Frontend payment flow coordinator.
 *
 * Ties a submitted Consultation / Appointment to the existing payment
 * checkout engine. It does NOT reimplement any payment logic: it builds a
 * CheckoutRequest from server-side values and delegates to PaymentCheckout.
 *
 * Responsibilities:
 *   - decide whether the related record must be paid for (from the
 *     section's saved configuration),
 *   - mark the record "pending payment" and its status accordingly,
 *   - start the checkout and return a routing instruction the form handler
 *     uses to redirect the customer to the gateway (or show manual steps),
 *   - never mark anything paid — that only happens server-side after a
 *     verified webhook/callback.
 */
class PaymentFlow {

    /**
     * Payment checkout orchestrator.
     */
    protected PaymentCheckout $checkout;

    /**
     * Payment manager.
     */
    protected PaymentManager $payments;

    /**
     * Audit log.
     */
    protected AuditLog $audit;

    /**
     * Constructor.
     *
     * @param PaymentCheckout $checkout Checkout.
     * @param PaymentManager  $payments Payments.
     * @param AuditLog        $audit    Audit log.
     */
    public function __construct( PaymentCheckout $checkout, PaymentManager $payments, AuditLog $audit ) {
        $this->checkout = $checkout;
        $this->payments = $payments;
        $this->audit    = $audit;
    }

    /**
     * Start a payment for a freshly created consultation / appointment.
     *
     * @param string $object_type 'consultation' | 'appointment'.
     * @param int    $object_id   Related post id.
     * @param array  $config      Resolved section payment config (SectionPayment::resolve()).
     * @param string $gateway_id  Chosen gateway id (from the form).
     * @param string $label       Human label for the transaction.
     * @param string $email       Customer email (optional).
     * @return array<string, mixed> Result: ['type'=>'redirect'|'manual'|'error', ...].
     */
    public function start( string $object_type, int $object_id, array $config, string $gateway_id, string $label = '', string $email = '' ): array {

        $object_type = sanitize_key( $object_type );
        $object_id   = absint( $object_id );

        if ( $object_id <= 0 ) {
            return array( 'type' => 'error', 'message' => __( 'The request could not be prepared for payment.', 'business-builder' ) );
        }

        /* Enforce the gateway is allowed for THIS section. */
        $allowed = isset( $config['gateways'] ) && is_array( $config['gateways'] )
            ? $config['gateways']
            : array();

        $gateway_id = sanitize_key( $gateway_id );

        if ( ! in_array( $gateway_id, $allowed, true )) {
            return array( 'type' => 'error', 'message' => __( 'Please choose a valid payment method.', 'business-builder' ) );
        }

        $amount   = isset( $config['fee'] ) ? (string) $config['fee'] : '';
        $currency = isset( $config['currency'] ) ? (string) $config['currency'] : '';

        $request = CheckoutRequest::from_array(
            array(
                'object_type' => $object_type,
                'object_id'   => $object_id,
                'gateway'     => $gateway_id,
                'amount'      => $amount,
                'currency'    => $currency,
                'label'       => $label,
                'email'       => $email,
            )
        );

        $result = $this->checkout->start( $request );

        $this->stamp_object( $object_type, $object_id, $result, $config, $gateway_id );

        return $result;
    }

    /**
     * Record the pending-payment state on the related object.
     *
     * Status is only advanced to a payment-specific state here; the
     * record is never marked confirmed/paid by this method.
     *
     * @param string             $object_type Object type.
     * @param int                $object_id   Object id.
     * @param array<string,mixed> $result      Checkout result.
     * @param array<string,mixed> $config      Section config.
     * @param string             $gateway_id  Gateway id.
     */
    protected function stamp_object( string $object_type, int $object_id, array $result, array $config, string $gateway_id ): void {

        $type   = isset( $result['type'] ) ? (string) $result['type'] : 'error';
        $txn    = isset( $result['transaction'] ) && $result['transaction'] instanceof PaymentTransaction
            ? $result['transaction']
            : null;

        $meta_key = $this->object_meta_key( $object_type );

        /* Always record the fee/currency the customer was shown. */
        update_post_meta( $object_id, $meta_key . 'payment_required', '1' );
        update_post_meta( $object_id, $meta_key . 'payment_amount', (string) $config['fee'] );
        update_post_meta( $object_id, $meta_key . 'payment_currency', (string) $config['currency'] );
        update_post_meta( $object_id, $meta_key . 'payment_gateway', $gateway_id );

        if ( 'error' === $type ) {
            update_post_meta( $object_id, $meta_key . 'payment_status', 'failed' );
        } else {
            update_post_meta( $object_id, $meta_key . 'payment_status', 'pending' );
        }

        if ( $txn instanceof PaymentTransaction ) {
            update_post_meta( $object_id, $meta_key . 'payment_reference', $txn->public_ref );
            update_post_meta( $object_id, $meta_key . 'transaction_id', $txn->id );
        }
    }

    /**
     * The meta-key prefix for an object type.
     *
     * @param string $object_type Object type.
     * @return string
     */
    protected function object_meta_key( string $object_type ): string {

        return ( 'appointment' === $object_type )
            ? '_bb_appointment_'
            : '_bb_consultation_';
    }
}
