<?php

namespace BusinessBuilderCore\Core\Payments\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable checkout request.
 *
 * A plain, validated description of "what the customer wants to pay
 * for, with which gateway". Built from untrusted input, it normalizes
 * and sanitizes every field and exposes a small validity surface the
 * checkout orchestrator can trust.
 *
 * It deliberately carries only non-sensitive data: never card numbers,
 * CVV or credentials (spec 12).
 */
final class CheckoutRequest {

    /**
     * Related object type: consultation | appointment.
     */
    public readonly string $object_type;

    /**
     * Related object id.
     */
    public readonly int $object_id;

    /**
     * Chosen gateway id.
     */
    public readonly string $gateway;

    /**
     * Amount as a decimal string (e.g. "250.00").
     */
    public readonly string $amount;

    /**
     * Currency code (e.g. "EGP").
     */
    public readonly string $currency;

    /**
     * Optional non-sensitive label for the transaction.
     */
    public readonly string $label;

    /**
     * Optional customer email for notifications.
     */
    public readonly string $email;

    /**
     * Optional non-sensitive billing details (name/phone) used by gateways
     * that require a full billing block (e.g. Paymob). Never card data.
     *
     * @var array<string, string>
     */
    public readonly array $billing;

    /**
     * Free-form validation errors (empty when valid).
     *
     * @var string[]
     */
    public readonly array $errors;

    /**
     * Constructor (use from_array()).
     *
     * @param string   $object_type Object type.
     * @param int      $object_id   Object id.
     * @param string   $gateway     Gateway id.
     * @param string   $amount      Amount.
     * @param string   $currency    Currency.
     * @param string   $label       Label.
     * @param string   $email       Email.
     * @param array    $billing     Billing details (name/phone).
     * @param string[] $errors      Errors.
     */
    private function __construct(
        string $object_type,
        int $object_id,
        string $gateway,
        string $amount,
        string $currency,
        string $label,
        string $email,
        array $billing,
        array $errors
    ) {
        $this->object_type = $object_type;
        $this->object_id   = $object_id;
        $this->gateway     = $gateway;
        $this->amount      = $amount;
        $this->currency    = $currency;
        $this->label       = $label;
        $this->email       = $email;
        $this->billing     = $billing;
        $this->errors      = $errors;
    }

    /**
     * Build and validate from raw input.
     *
     * @param array $input Raw input (e.g. $_POST).
     * @return self
     */
    public static function from_array( array $input ): self {

        $object_type = isset( $input['object_type'] )
            ? sanitize_key( (string) $input['object_type'] )
            : 'consultation';

        $allowed = in_array( $object_type, self::allowed_object_types(), true );

        if ( false === $allowed ) {
            $object_type = 'consultation';
        }

        $object_id = isset( $input['object_id'] ) ? absint( $input['object_id'] ) : 0;

        $gateway = isset( $input['gateway'] )
            ? sanitize_key( (string) $input['gateway'] )
            : '';

        $amount = isset( $input['amount'] )
            ? (string) $input['amount']
            : '';

        $amount = self::normalize_amount( $amount );

        $currency = isset( $input['currency'] )
            ? strtoupper( sanitize_text_field( (string) $input['currency'] ) )
            : '';

        $label = isset( $input['label'] )
            ? sanitize_text_field( (string) $input['label'] )
            : '';

        $email = isset( $input['email'] )
            ? sanitize_email( (string) $input['email'] )
            : '';

        $billing = isset( $input['billing'] ) && is_array( $input['billing'] )
            ? self::normalize_billing( $input['billing'] )
            : array();

        $errors = self::validate( $object_type, $object_id, $gateway, $amount, $currency );

        return new self( $object_type, $object_id, $gateway, $amount, $currency, $label, $email, $billing, $errors );
    }

    /**
     * Normalize the optional billing block to safe scalar strings.
     *
     * @param array $billing Raw billing input.
     * @return array<string, string>
     */
    private static function normalize_billing( array $billing ): array {

        $clean = array();

        foreach ( array( 'first_name', 'last_name', 'name', 'phone', 'email', 'country', 'city', 'origin' ) as $key ) {

            $value = isset( $billing[ $key ] ) && is_scalar( $billing[ $key ] )
                ? (string) $billing[ $key ]
                : '';

            $clean[ $key ] = sanitize_text_field( $value );
        }

        return $clean;
    }

    /**
     * Whether the request passed validation.
     *
     * @return bool
     */
    public function is_valid(): bool {

        return empty( $this->errors );
    }

    /**
     * Related object types the checkout accepts.
     *
     * @return string[]
     */
    public static function allowed_object_types(): array {

        /**
         * Filter the object types that may be paid for.
         *
         * @param string[] $types Allowed types.
         */
        return apply_filters( 'bb_payment_object_types', array( 'consultation', 'appointment' ) );
    }

    /**
     * Normalize an amount to a 2-decimal string, or '' when invalid.
     *
     * @param string $amount Raw amount.
     * @return string
     */
    public static function normalize_amount( string $amount ): string {

        $amount = trim( $amount );

        if ( '' === $amount ) {
            return '';
        }

        /* Reject anything that is not a plain non-negative number. */
        $matches = preg_match( '/^\d+(\.\d{1,2})?$/', $amount );

        if ( false === $matches ) {
            return '';
        }

        if ( 1 !== $matches ) {
            return '';
        }

        return number_format( (float) $amount, 2, '.', '' );
    }

    /**
     * Validate the normalized request.
     *
     * @param string $object_type Object type.
     * @param int    $object_id   Object id.
     * @param string $gateway     Gateway id.
     * @param string $amount      Amount.
     * @param string $currency    Currency.
     * @return string[]
     */
    private static function validate( string $object_type, int $object_id, string $gateway, string $amount, string $currency ): array {

        $errors = array();

        if ( $object_id <= 0 ) {
            $errors[] = __( 'Missing or invalid related item.', 'business-builder' );
        }

        if ( '' === $gateway ) {
            $errors[] = __( 'Please choose a payment method.', 'business-builder' );
        }

        if ( '' === $amount || (float) $amount <= 0 ) {
            $errors[] = __( 'A valid amount is required.', 'business-builder' );
        }

        if ( '' === $currency ) {
            $errors[] = __( 'A currency is required.', 'business-builder' );
        }

        return $errors;
    }
}
