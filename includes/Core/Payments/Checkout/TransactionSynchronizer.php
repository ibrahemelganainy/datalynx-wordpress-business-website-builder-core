<?php

namespace BusinessBuilderCore\Core\Payments\Checkout;

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\PaymentResult;
use BusinessBuilderCore\Core\Payments\Transaction\TransactionStatus;
use BusinessBuilderCore\Core\Audit\AuditLog;

defined( 'ABSPATH' ) || exit;

/**
 * Applies a verified payment result to the transaction and its related
 * object (consultation or appointment).
 *
 * This is the single place that turns a gateway's PaymentResult into:
 *   - a transaction status change (idempotent, via the store),
 *   - a matching payment-status meta update on the related object,
 *   - an audit entry.
 *
 * Both the server-to-server webhook (PaymentWebhook) and the browser
 * return callback (PaymentCallback) funnel through here, so there is
 * exactly one synchronisation path and no divergence between them.
 */
class TransactionSynchronizer {

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
     * @param PaymentManager $payments Payments.
     * @param AuditLog       $audit    Audit log.
     */
    public function __construct( PaymentManager $payments, AuditLog $audit ) {
        $this->payments = $payments;
        $this->audit    = $audit;
    }

    /**
     * Synchronise a transaction against a verified payment result.
     *
     * Idempotent: re-applying a terminal status is a no-op that still
     * returns the transaction. Illegal transitions are refused so a
     * late/failed callback can never regress a paid transaction.
     *
     * @param PaymentTransaction $transaction Transaction.
     * @param PaymentResult      $result      Verified result.
     * @return PaymentTransaction The (possibly updated) transaction.
     */
    public function apply( PaymentTransaction $transaction, PaymentResult $result ): PaymentTransaction {

        $target = sanitize_key( $result->status );

        if ( '' === $target ) {
            $target = $result->success ? 'paid' : 'failed';
        }

        $target_valid = TransactionStatus::is_valid( $target );

        if ( false === $target_valid ) {
            $target = $result->success ? 'paid' : 'failed';
        }

        /* Refuse illegal/regressive transitions (e.g. paid -> pending). */
        $allowed = TransactionStatus::can_transition_to( $transaction->status, $target );

        if ( false === $allowed ) {
            return $transaction;
        }

        $reference = '' !== $result->reference ? $result->reference : $transaction->reference;

        $updated = $this->payments->record_status_change( $transaction->id, $target, $reference );

        if ( ! $updated instanceof PaymentTransaction ) {
            $updated = $transaction;
            $updated->status = $target;
        }

        $this->sync_object( $updated );
        $this->audit_sync( $updated, $target );

        return $updated;
    }

    /**
     * Push the transaction's payment status onto its related object.
     *
     * Consultation meta key:  _bb_consultation_payment_status
     * Appointment meta key:   _bb_appointment_payment_status
     *
     * @param PaymentTransaction $transaction Transaction.
     */
    protected function sync_object( PaymentTransaction $transaction ): void {

        $object_id = $transaction->object_id > 0
            ? $transaction->object_id
            : $transaction->consultation_id;

        if ( $object_id <= 0 ) {
            return;
        }

        $type  = '' !== $transaction->object_type ? $transaction->object_type : 'consultation';
        $state = $this->object_payment_state( $transaction->status );

        $meta_key = $this->object_meta_key( $type );

        update_post_meta( $object_id, $meta_key, $state );

        /*
         * Backward compatibility: the webhook historically wrote the
         * consultation key directly. Keep it in sync for consultations.
         */
        if ( 'consultation' === $type ) {
            update_post_meta( $object_id, '_bb_consultation_payment_status', $state );
        }
    }

    /**
     * Map a transaction status to the object's payment-state slug.
     *
     * @param string $status Transaction status.
     * @return string
     */
    protected function object_payment_state( string $status ): string {

        $status = sanitize_key( $status );

        $map = array(
            'paid'             => 'paid',
            'completed'        => 'paid',
            'pending'          => 'pending',
            'processing'       => 'processing',
            'awaiting_payment' => 'awaiting_payment',
            'on_hold'          => 'on_hold',
            'failed'           => 'failed',
            'cancelled'        => 'cancelled',
            'refunded'         => 'refunded',
            'expired'          => 'expired',
        );

        return $map[ $status ] ?? $status;
    }

    /**
     * The payment-status meta key for an object type.
     *
     * @param string $type Object type.
     * @return string
     */
    protected function object_meta_key( string $type ): string {

        if ( 'appointment' === $type ) {
            return '_bb_appointment_payment_status';
        }

        return '_bb_consultation_payment_status';
    }

    /**
     * Record the synchronisation in the audit log.
     *
     * @param PaymentTransaction $transaction Transaction.
     * @param string             $status      Applied status.
     */
    protected function audit_sync( PaymentTransaction $transaction, string $status ): void {

        $this->audit->record(
            'payment.status_synced',
            'payment',
            $transaction->id,
            array(
                'gateway' => $transaction->gateway,
                'status'  => $status,
            )
        );
    }
}
