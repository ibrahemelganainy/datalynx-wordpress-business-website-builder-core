<?php

namespace BusinessBuilderCore\Core\Payments\Receipt;

use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\Currencies;
use BusinessBuilderCore\Core\Payments\Transaction\TransactionStatus;

defined( 'ABSPATH' ) || exit;

/**
 * A payment receipt value object.
 *
 * Built from a PaymentTransaction plus a small, safe snapshot of the
 * related object. It exposes ONLY public, non-sensitive fields:
 *
 *   - the public reference (never the internal id),
 *   - the gateway's human name (never its credentials/settings),
 *   - amount + currency, status, timestamps,
 *   - an optional human label for what was paid for.
 *
 * There is deliberately no accessor for gateway settings, secret keys,
 * card data or the customer's private contact fields (spec 12/34).
 */
final class Receipt {

    /**
     * Public transaction reference.
     */
    public readonly string $reference;

    /**
     * Human gateway name.
     */
    public readonly string $gateway_name;

    /**
     * Amount as a decimal string.
     */
    public readonly string $amount;

    /**
     * Currency code.
     */
    public readonly string $currency;

    /**
     * Amount, formatted for display.
     */
    public readonly string $amount_display;

    /**
     * Status slug.
     */
    public readonly string $status;

    /**
     * Status, human label.
     */
    public readonly string $status_label;

    /**
     * Paid/created timestamp (mysql).
     */
    public readonly string $created_at;

    /**
     * Last update timestamp (mysql).
     */
    public readonly string $updated_at;

    /**
     * Human description of what was paid for.
     */
    public readonly string $description;

    /**
     * Whether the receipt represents a settled payment.
     */
    public readonly bool $is_paid;

    /**
     * Site name at the time of the receipt.
     */
    public readonly string $site_name;

    /**
     * Constructor (use from_transaction()).
     *
     * @param string $reference      Public reference.
     * @param string $gateway_name   Gateway name.
     * @param string $amount         Amount.
     * @param string $currency       Currency.
     * @param string $amount_display Formatted amount.
     * @param string $status         Status slug.
     * @param string $status_label   Status label.
     * @param string $created_at     Created timestamp.
     * @param string $updated_at     Updated timestamp.
     * @param string $description    Description.
     * @param bool   $is_paid        Settled.
     * @param string $site_name      Site name.
     */
    private function __construct(
        string $reference,
        string $gateway_name,
        string $amount,
        string $currency,
        string $amount_display,
        string $status,
        string $status_label,
        string $created_at,
        string $updated_at,
        string $description,
        bool $is_paid,
        string $site_name
    ) {
        $this->reference      = $reference;
        $this->gateway_name   = $gateway_name;
        $this->amount         = $amount;
        $this->currency       = $currency;
        $this->amount_display = $amount_display;
        $this->status         = $status;
        $this->status_label   = $status_label;
        $this->created_at     = $created_at;
        $this->updated_at     = $updated_at;
        $this->description    = $description;
        $this->is_paid        = $is_paid;
        $this->site_name      = $site_name;
    }

    /**
     * Build a receipt from a transaction.
     *
     * @param PaymentTransaction $transaction Transaction.
     * @param string             $gateway_name Human gateway name.
     * @param string             $description  Optional description.
     * @return self
     */
    public static function from_transaction(
        PaymentTransaction $transaction,
        string $gateway_name,
        string $description = ''
    ): self {

        $status = sanitize_key( $transaction->status );

        $amount_display = '' !== $transaction->amount
            ? Currencies::format( (float) $transaction->amount, $transaction->currency )
            : $transaction->amount;

        if ( '' === $description ) {
            $description = self::default_description( $transaction );
        }

        return new self(
            $transaction->public_ref,
            sanitize_text_field( $gateway_name ),
            $transaction->amount,
            $transaction->currency,
            $amount_display,
            $status,
            TransactionStatus::label( $status ),
            $transaction->created_at,
            $transaction->updated_at,
            $description,
            'paid' === $status,
            wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
        );
    }

    /**
     * A safe default description derived from the transaction.
     *
     * @param PaymentTransaction $transaction Transaction.
     * @return string
     */
    private static function default_description( PaymentTransaction $transaction ): string {

        $label = isset( $transaction->meta['label'] ) ? (string) $transaction->meta['label'] : '';

        if ( '' !== $label ) {
            return sanitize_text_field( $label );
        }

        return __( 'Service payment', 'business-builder' );
    }

    /**
     * Safe, associative export (no secrets).
     *
     * @return array<string, mixed>
     */
    public function to_array(): array {

        return array(
            'reference'      => $this->reference,
            'gateway_name'   => $this->gateway_name,
            'amount'         => $this->amount,
            'currency'       => $this->currency,
            'amount_display' => $this->amount_display,
            'status'         => $this->status,
            'status_label'   => $this->status_label,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
            'description'    => $this->description,
            'is_paid'        => $this->is_paid,
            'site_name'      => $this->site_name,
        );
    }
}
