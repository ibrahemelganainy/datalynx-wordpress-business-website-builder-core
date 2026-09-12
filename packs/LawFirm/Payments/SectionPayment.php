<?php
namespace BusinessBuilderCore\Packs\LawFirm\Payments;

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\PaymentGatewayInterface;
use BusinessBuilderCore\Core\Payments\Currencies;
use BusinessBuilderCore\Settings\SiteSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Section-level payment configuration.
 *
 * A Consultation / Appointment section stored in a page's section meta can
 * configure its own payment behaviour in the Page Builder. This class is
 * the single place that reads that configuration and resolves it into an
 * effective, safe decision for the frontend form:
 *
 *   - whether payment is required for this section
 *   - the fee + currency (section value, else the site default)
 *   - the gateways the customer may choose, which MUST satisfy ALL of:
 *       1. globally enabled in Payment Settings,
 *       2. fully configured,
 *       3. selected for this section (or all available when none selected)
 *
 * It never trusts the browser: the fee/currency are re-read from stored
 * section meta at submit time, not from the form.
 */
class SectionPayment {

    /**
     * Payment manager.
     */
    protected PaymentManager $payments;

    /**
     * Site settings (global defaults).
     */
    protected SiteSettings $settings;

    /**
     * Constructor.
     *
     * @param PaymentManager $payments Payments.
     * @param SiteSettings   $settings Site settings.
     */
    public function __construct( PaymentManager $payments, SiteSettings $settings ) {
        $this->payments = $payments;
        $this->settings = $settings;
    }

    /**
     * Resolve the effective payment configuration for a section.
     *
     * @param string $object_type 'consultation' | 'appointment'.
     * @param array  $settings    Section settings (from saved section meta).
     * @return array<string, mixed> {
     *     @type bool     $enabled    Whether payment is required.
     *     @type string   $fee        Normalized decimal string.
     *     @type string   $currency   Currency code.
     *     @type string[] $gateways   Allowed gateway ids.
     *     @type array    $available  id => PaymentGatewayInterface (allowed+ready).
     * }
     */
    public function resolve( string $object_type, array $settings ): array {

        $object_type = sanitize_key( $object_type );

        $enabled  = $this->is_enabled_for_section( $object_type, $settings );
        $fee      = $this->fee_for_section( $settings );
        $currency = $this->currency_for_section( $settings );

        /* Gateways selected for this section (may be empty = inherit all). */
        $selected = isset( $settings['payment_gateways'] ) && is_array( $settings['payment_gateways'] )
            ? array_map( 'sanitize_key', $settings['payment_gateways'] )
            : array();

        $allowed   = $this->payments->resolve_gateways( $selected );
        $available = $this->ready_gateways( $allowed );

        /*
         * Payment cannot actually run without at least one ready gateway;
         * the section is only "payable" when enabled AND a gateway exists.
         */
        $payable = $enabled && ! empty( $available );

        return array(
            'enabled'   => $enabled,
            'payable'   => $payable,
            'fee'       => $fee,
            'currency'  => $currency,
            'gateways'  => array_keys( $available ),
            'available' => $available,
        );
    }

    /**
     * Whether payment is required for this section.
     *
     * Section meta wins when it explicitly sets payment_enabled; otherwise
     * the global "require payment by default" flag applies. This keeps the
     * global setting a DEFAULT that a section can override — never a
     * permanent override of a section's own choice.
     *
     * @param string $object_type Object type.
     * @param array  $settings    Section settings.
     * @return bool
     */
    public function is_enabled_for_section( string $object_type, array $settings ): bool {

        if ( isset( $settings['payment_enabled'] ) && '' !== $settings['payment_enabled'] ) {
            return ! empty( $settings['payment_enabled'] );
        }

        /* Fall back to the global default. */
        if ( 'appointment' === $object_type ) {
            return ! empty( $this->settings->get( 'require_appointment_payment', false ) );
        }

        return ! empty( $this->settings->get( 'require_consultation_payment', false ) );
    }

    /**
     * The section fee, else the global default fee.
     *
     * @param array $settings Section settings.
     * @return string Normalized decimal string ('' when unset).
     */
    public function fee_for_section( array $settings ): string {

        $fee = isset( $settings['payment_fee'] ) ? (string) $settings['payment_fee'] : '';

        $empty = ( '' === trim( $fee ) );

        if ( $empty ) {
            $fee = (string) $this->settings->get( 'consultation_fee', '' );
        }

        return self::normalize_amount( $fee );
    }

    /**
     * The section currency, else the global default currency.
     *
     * @param array $settings Section settings.
     * @return string Currency code.
     */
    public function currency_for_section( array $settings ): string {

        $code = isset( $settings['payment_currency'] ) ? (string) $settings['payment_currency'] : '';

        $code = Currencies::normalize( $code );

        if ( '' === $code ) {
            $code = Currencies::normalize( (string) $this->settings->get( 'consultation_currency', 'USD' ) );
        }

        return '' !== $code ? $code : 'USD';
    }

    /**
     * Filter a gateway set down to the ones that can actually be used.
     *
     * @param array<string, PaymentGatewayInterface> $gateways Candidate gateways.
     * @return array<string, PaymentGatewayInterface>
     */
    protected function ready_gateways( array $gateways ): array {

        $out = array();

        foreach ( $gateways as $id => $gateway ) {

            if ( ! $gateway instanceof PaymentGatewayInterface ) {
                continue;
            }

            $enabled = $this->payments->is_gateway_enabled( $id );

            if ( false === $enabled ) {
                continue;
            }

            if ( ! $gateway->is_configured() ) {
                continue;
            }

            $out[ $id ] = $gateway;
        }

        return $out;
    }

    /**
     * Normalize a fee to a 2-decimal string, or '' when invalid.
     *
     * @param string $amount Raw amount.
     * @return string
     */
    public static function normalize_amount( string $amount ): string {

        $amount = preg_replace( '/[^0-9.]/', '', trim( $amount ) );

        if ( '' === $amount || ! is_numeric( $amount ) || (float) $amount <= 0 ) {
            return '';
        }

        return number_format( (float) $amount, 2, '.', '' );
    }
}
