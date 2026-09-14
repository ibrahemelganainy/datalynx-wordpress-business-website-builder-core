<?php

namespace BusinessBuilderCore\Core\Payments;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Payment transaction value object.
 *
 * Stores the minimum needed to track a payment. Never holds card data,
 * CVV or credentials (spec 12).
 */
class PaymentTransaction {

    /**
     * Transaction statuses (extensible).
     *
     * Explicit string states rather than an ambiguous boolean, so future
     * workflows (refunds, expiry, retries) are supported (spec: Part 9).
     *
     * @return string[]
     */
    public static function statuses(): array {

        return array(
            'pending',
            'processing',
            'awaiting_payment',
            'on_hold',
            'paid',
            'completed',
            'failed',
            'cancelled',
            'refunded',
            'expired',
        );
    }

    /**
     * Internal transaction id.
     */
    public int $id = 0;

    /**
     * Public, non-sequential reference (safe to show to customers).
     */
    public string $public_ref = '';

    /**
     * Related object type: 'consultation' | 'appointment'.
     */
    public string $object_type = 'consultation';

    /**
     * Related object id (consultation / appointment post id).
     */
    public int $object_id = 0;

    /**
     * Related consultation id (kept for backward compatibility).
     */
    public int $consultation_id = 0;

    /**
     * Gateway id.
     */
    public string $gateway = '';

    /**
     * Provider reference / gateway transaction id.
     */
    public string $reference = '';

    /**
     * Amount (decimal string).
     */
    public string $amount = '';

    /**
     * Currency code.
     */
    public string $currency = '';

    /**
     * Status: pending|processing|paid|failed|cancelled|refunded|expired.
     */
    public string $status = 'pending';

    /**
     * Extra metadata (non-sensitive).
     *
     * @var array<string, mixed>
     */
    public array $meta = array();

    /**
     * Created timestamp (mysql).
     */
    public string $created_at = '';

    /**
     * Updated timestamp (mysql).
     */
    public string $updated_at = '';

    /**
     * Build from an array.
     *
     * @param array $data Field data.
     * @return self
     */
    public static function from_array( array $data ): self {

        $t = new self();

        $t->id              = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
        $t->public_ref      = isset( $data['public_ref'] ) ? sanitize_text_field( (string) $data['public_ref'] ) : '';
        $t->object_type     = isset( $data['object_type'] ) ? sanitize_key( (string) $data['object_type'] ) : 'consultation';
        $t->object_id       = isset( $data['object_id'] ) ? absint( $data['object_id'] ) : 0;
        $t->consultation_id = isset( $data['consultation_id'] ) ? absint( $data['consultation_id'] ) : 0;
        $t->gateway         = isset( $data['gateway'] ) ? sanitize_key( (string) $data['gateway'] ) : '';
        $t->reference       = isset( $data['reference'] ) ? sanitize_text_field( (string) $data['reference'] ) : '';
        $t->amount          = isset( $data['amount'] ) ? (string) $data['amount'] : '';
        $t->currency        = isset( $data['currency'] ) ? (string) $data['currency'] : '';
        $t->status          = isset( $data['status'] ) ? sanitize_key( (string) $data['status'] ) : 'pending';
        $t->meta            = isset( $data['meta'] ) && is_array( $data['meta'] ) ? $data['meta'] : array();
        $t->created_at      = isset( $data['created_at'] ) ? (string) $data['created_at'] : '';
        $t->updated_at      = isset( $data['updated_at'] ) ? (string) $data['updated_at'] : '';

        /*
         * Backward compatibility: legacy rows only had consultation_id.
         * Mirror it into object_type/object_id so new code has one
         * consistent shape.
         */
        if ( 0 === $t->object_id && $t->consultation_id > 0 ) {
            $t->object_type = 'consultation';
            $t->object_id   = $t->consultation_id;
        }

        return $t;
    }

    /**
     * Export as array.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array {

        return array(
            'id'              => $this->id,
            'public_ref'      => $this->public_ref,
            'object_type'     => $this->object_type,
            'object_id'       => $this->object_id,
            'consultation_id' => $this->consultation_id,
            'gateway'         => $this->gateway,
            'reference'       => $this->reference,
            'amount'          => $this->amount,
            'currency'        => $this->currency,
            'status'          => $this->status,
            'meta'            => $this->meta,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        );
    }
}
