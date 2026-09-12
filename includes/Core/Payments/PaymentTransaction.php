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
     * Internal transaction id.
     */
    public int $id = 0;

    /**
     * Related consultation id.
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
     * Status: pending|paid|failed|refunded.
     */
    public string $status = 'pending';

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
        $t->consultation_id = isset( $data['consultation_id'] ) ? absint( $data['consultation_id'] ) : 0;
        $t->gateway         = isset( $data['gateway'] ) ? sanitize_key( (string) $data['gateway'] ) : '';
        $t->reference       = isset( $data['reference'] ) ? sanitize_text_field( (string) $data['reference'] ) : '';
        $t->amount          = isset( $data['amount'] ) ? (string) $data['amount'] : '';
        $t->currency        = isset( $data['currency'] ) ? (string) $data['currency'] : '';
        $t->status          = isset( $data['status'] ) ? sanitize_key( (string) $data['status'] ) : 'pending';
        $t->created_at      = isset( $data['created_at'] ) ? (string) $data['created_at'] : '';
        $t->updated_at      = isset( $data['updated_at'] ) ? (string) $data['updated_at'] : '';

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
            'consultation_id' => $this->consultation_id,
            'gateway'         => $this->gateway,
            'reference'       => $this->reference,
            'amount'          => $this->amount,
            'currency'        => $this->currency,
            'status'          => $this->status,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        );
    }
}
