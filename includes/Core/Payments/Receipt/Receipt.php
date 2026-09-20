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
     * Public reference of the paid-for object (consultation / appointment).
     *
     * Empty when the transaction is not linked to an object. This is a
     * public, non-sequential reference — never an internal id.
     */
    public readonly string $object_reference;

    /**
     * Human label of the object type (e.g. "Consultation").
     */
    public readonly string $object_label;

    /**
     * Provider/gateway transaction id (never a credential).
     */
    public readonly string $gateway_reference;

    /**
     * Whether the receipt represents a settled payment.
     */
    public readonly bool $is_paid;

    /**
     * Site name at the time of the receipt.
     */
    public readonly string $site_name;

    /**
     * Public absolute URL of the business logo (empty when none is set).
     */
    public readonly string $logo_url;

    /**
     * Customer name shown on the receipt (may be empty).
     */
    public readonly string $customer_name;

    /**
     * Object type slug ('consultation' | 'appointment').
     */
    public readonly string $object_type;

    /**
     * Extra labelled rows (e.g. Lawyer, Appointment Date for appointments;
     * Practice Area for consultations). Label => value, already safe.
     *
     * @var array<int, array{label: string, value: string}>
     */
    public readonly array $extra_rows;

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
     * @param string $description        Description.
     * @param string $object_reference   Public object reference (CNS-/APT-).
     * @param string $object_label       Object type label.
     * @param string $gateway_reference  Provider transaction id.
     * @param bool   $is_paid            Settled.
     * @param string $site_name          Site name.
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
        string $object_reference,
        string $object_label,
        string $gateway_reference,
        bool   $is_paid,
        string $site_name,
        string $logo_url,
        string $customer_name,
        string $object_type,
        array  $extra_rows
    ) {
        $this->reference         = $reference;
        $this->gateway_name      = $gateway_name;
        $this->amount            = $amount;
        $this->currency          = $currency;
        $this->amount_display    = $amount_display;
        $this->status            = $status;
        $this->status_label      = $status_label;
        $this->created_at        = $created_at;
        $this->updated_at        = $updated_at;
        $this->description       = $description;
        $this->object_reference  = $object_reference;
        $this->object_label      = $object_label;
        $this->gateway_reference = $gateway_reference;
        $this->is_paid           = $is_paid;
        $this->site_name         = $site_name;
        $this->logo_url          = $logo_url;
        $this->customer_name     = $customer_name;
        $this->object_type       = $object_type;
        $this->extra_rows        = $extra_rows;
    }

    /**
     * Build a receipt from a transaction.
     *
     * @param PaymentTransaction $transaction       Transaction.
     * @param string             $gateway_name      Human gateway name.
     * @param string             $description       Optional description.
     * @param string             $object_reference  Optional object reference.
     * @param string             $object_label      Optional object label.
     * @return self
     */
    public static function from_transaction(
        PaymentTransaction $transaction,
        string $gateway_name,
        string $description = '',
        string $object_reference = '',
        string $object_label = ''
    ): self {

        $status = sanitize_key( $transaction->status );

        $amount_display = '' !== $transaction->amount
            ? Currencies::format( (float) $transaction->amount, $transaction->currency )
            : $transaction->amount;

        if ( '' === $description ) {
            $description = self::default_description( $transaction );
        }

        /*
         * Resolve the paid-for object's public reference when the caller
         * did not pass one. Only the public reference is ever surfaced; the
         * internal post id is not part of the receipt.
         */
        if ( '' === $object_reference ) {
            $object_reference = self::object_reference( $transaction );
        }

        if ( '' === $object_label && '' !== $transaction->object_type ) {
            $object_label = self::object_label( $transaction->object_type );
        }

        $is_paid = in_array( $status, array( 'paid', 'completed' ), true );

        return new self(
            $transaction->public_ref,
            sanitize_text_field( $gateway_name ),
            $transaction->amount,
            $transaction->currency,
            $amount_display,
            $status,
            self::receipt_status_label( $status ),
            $transaction->created_at,
            $transaction->updated_at,
            $description,
            sanitize_text_field( $object_reference ),
            sanitize_text_field( $object_label ),
            sanitize_text_field( $transaction->reference ),
            $is_paid,
            wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
            self::logo_url(),
            self::customer_name( $transaction ),
            sanitize_key( $transaction->object_type ),
            self::extra_rows( $transaction )
        );
    }

    /**
     * The business logo URL (from the site settings), or ''.
     *
     * @return string
     */
    private static function logo_url(): string {

        if ( ! class_exists( '\BusinessBuilderCore\Settings\SiteSettings' )) {
            return '';
        }

        $settings = new \BusinessBuilderCore\Settings\SiteSettings();
        $logo_id  = (int) $settings->get( 'logo_id', 0 );

        if ( $logo_id <= 0 ) {
            return '';
        }

        $url = wp_get_attachment_image_url( $logo_id, 'medium' );

        return is_string( $url ) ? $url : '';
    }

    /**
     * The customer name recorded with the transaction, or ''.
     *
     * @param PaymentTransaction $transaction Transaction.
     * @return string
     */
    private static function customer_name( PaymentTransaction $transaction ): string {

        $meta = is_array( $transaction->meta ) ? $transaction->meta : array();

        $name = isset( $meta['name'] ) ? (string) $meta['name'] : '';
        $name = trim( $name );

        if ( $name === '' ) {
            $first = isset( $meta['first_name'] ) ? (string) $meta['first_name'] : '';
            $last  = isset( $meta['last_name'] ) ? (string) $meta['last_name'] : '';
            $sep   = chr( 32 );
            $name  = trim( $first . $sep . $last );
        }

        return sanitize_text_field( $name );
    }

    /**
     * Extra receipt rows relevant to the object type.
     *
     * Appointments show the lawyer, appointment date and time; consultations
     * show the practice area. Values come from the object's OWN meta and are
     * sanitized here; nothing private (phone/email/message) is ever included.
     *
     * @param PaymentTransaction $transaction Transaction.
     * @return array<int, array{label: string, value: string}>
     */
    private static function extra_rows( PaymentTransaction $transaction ): array {

        $rows = array();

        /*
         * The customer-supplied MANUAL transaction reference (wallet /
         * InstaPay / bank transfer) lives on the transaction meta. Show it on
         * the receipt whenever present, independent of the related object.
         */
        $meta       = is_array( $transaction->meta ) ? $transaction->meta : array();
        $manual_ref = isset( $meta['manual_reference'] ) ? (string) $meta['manual_reference'] : '';

        if ( '' !== $manual_ref ) {
            $rows[] = array(
                'label' => __( 'Transaction Reference', 'business-builder' ),
                'value' => sanitize_text_field( $manual_ref ),
            );
        }

        $object_id = $transaction->object_id > 0
            ? $transaction->object_id
            : $transaction->consultation_id;

        if ( $object_id <= 0 ) {
            return $rows;
        }

        $type = '' !== $transaction->object_type ? sanitize_key( $transaction->object_type ) : 'consultation';

        if ( 'appointment' === $type ) {

            $lawyer_id = (int) get_post_meta( $object_id, '_bb_appointment_lawyer_id', true );

            if ( $lawyer_id > 0 ) {
                $lawyer = get_the_title( $lawyer_id );

                if ( is_string( $lawyer ) && '' !== $lawyer ) {
                    $rows[] = array(
                        'label' => __( 'Lawyer', 'business-builder' ),
                        'value' => sanitize_text_field( $lawyer ),
                    );
                }
            }

            $date  = (string) get_post_meta( $object_id, '_bb_appointment_date', true );
            $start = (string) get_post_meta( $object_id, '_bb_appointment_start', true );

            if ( '' !== $date ) {
                $rows[] = array(
                    'label' => __( 'Appointment Date', 'business-builder' ),
                    'value' => sanitize_text_field( $date ),
                );
            }

            if ( '' !== $start ) {
                $rows[] = array(
                    'label' => __( 'Appointment Time', 'business-builder' ),
                    'value' => sanitize_text_field( $start ),
                );
            }

            return $rows;
        }

        $area = (string) get_post_meta( $object_id, '_bb_consultation_practice_area', true );

        if ( '' !== $area ) {
            $rows[] = array(
                'label' => __( 'Practice Area', 'business-builder' ),
                'value' => sanitize_text_field( $area ),
            );
        }

        return $rows;
    }

    /**
     * Customer-facing status label for the receipt.
     *
     * Manual / offline payments that await an administrator are labelled
     * "Pending Manual Verification" so a receipt NEVER implies a payment is
     * settled when it is not.
     *
     * @param string $status Transaction status slug.
     * @return string
     */
    private static function receipt_status_label( string $status ): string {

        $status = sanitize_key( $status );

        if ( in_array( $status, array( 'on_hold', 'awaiting_payment', 'processing' ), true )) {
            return __( 'Pending Manual Verification', 'business-builder' );
        }

        return TransactionStatus::label( $status );
    }

    /**
     * Public reference of the paid-for object (consultation / appointment).
     *
     * Reads ONLY the public reference meta; never the post id.
     *
     * @param PaymentTransaction $transaction Transaction.
     * @return string
     */
    private static function object_reference( PaymentTransaction $transaction ): string {

        $object_id = $transaction->object_id > 0
            ? $transaction->object_id
            : $transaction->consultation_id;

        if ( $object_id <= 0 ) {
            return '';
        }

        $type = '' !== $transaction->object_type ? $transaction->object_type : 'consultation';

        $meta_key = ( 'appointment' === $type )
            ? '_bb_appointment_public_reference'
            : '_bb_consultation_public_reference';

        return (string) get_post_meta( $object_id, $meta_key, true );
    }

    /**
     * Human label for an object type.
     *
     * @param string $type Object type.
     * @return string
     */
    private static function object_label( string $type ): string {

        if ( 'appointment' === sanitize_key( $type )) {
            return __( 'Appointment', 'business-builder' );
        }

        return __( 'Consultation', 'business-builder' );
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
            'description'       => $this->description,
            'object_reference'  => $this->object_reference,
            'object_label'      => $this->object_label,
            'gateway_reference' => $this->gateway_reference,
            'is_paid'           => $this->is_paid,
            'site_name'         => $this->site_name,
            'logo_url'          => $this->logo_url,
            'customer_name'     => $this->customer_name,
            'object_type'       => $this->object_type,
            'extra_rows'        => $this->extra_rows,
        );
    }
}
