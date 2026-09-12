<?php

namespace BusinessBuilderCore\Core\Payments;

if ( ! defined( 'ABSPATH' )) {
    exit;
}

/**
 * Structured currency catalogue.
 *
 * Single source of truth for currency codes, readable names and display
 * symbols used across the payment UI (spec: currency selector must NOT
 * be a plain text field; do not hardcode EGP).
 *
 * The list is intentionally curated (common + regional) but is exposed
 * through a filter so packs/extensions can add or override entries.
 */
class Currencies {

    /**
     * All supported currencies.
     *
     * @return array<string, array{code:string,name:string,symbol:string}>
     */
    public static function all(): array {

        $currencies = array(
            'USD' => array( 'code' => 'USD', 'name' => __( 'US Dollar', 'business-builder' ), 'symbol' => '$' ),
            'EUR' => array( 'code' => 'EUR', 'name' => __( 'Euro', 'business-builder' ), 'symbol' => '€' ),
            'GBP' => array( 'code' => 'GBP', 'name' => __( 'British Pound', 'business-builder' ), 'symbol' => '£' ),
            'EGP' => array( 'code' => 'EGP', 'name' => __( 'Egyptian Pound', 'business-builder' ), 'symbol' => 'E£' ),
            'SAR' => array( 'code' => 'SAR', 'name' => __( 'Saudi Riyal', 'business-builder' ), 'symbol' => 'ر.س' ),
            'AED' => array( 'code' => 'AED', 'name' => __( 'UAE Dirham', 'business-builder' ), 'symbol' => 'د.إ' ),
            'KWD' => array( 'code' => 'KWD', 'name' => __( 'Kuwaiti Dinar', 'business-builder' ), 'symbol' => 'د.ك' ),
            'QAR' => array( 'code' => 'QAR', 'name' => __( 'Qatari Riyal', 'business-builder' ), 'symbol' => 'ر.ق' ),
            'BHD' => array( 'code' => 'BHD', 'name' => __( 'Bahraini Dinar', 'business-builder' ), 'symbol' => '.د.ب' ),
            'OMR' => array( 'code' => 'OMR', 'name' => __( 'Omani Rial', 'business-builder' ), 'symbol' => 'ر.ع.' ),
            'JOD' => array( 'code' => 'JOD', 'name' => __( 'Jordanian Dinar', 'business-builder' ), 'symbol' => 'د.ا' ),
            'MAD' => array( 'code' => 'MAD', 'name' => __( 'Moroccan Dirham', 'business-builder' ), 'symbol' => 'د.م.' ),
            'TND' => array( 'code' => 'TND', 'name' => __( 'Tunisian Dinar', 'business-builder' ), 'symbol' => 'د.ت' ),
            'DZD' => array( 'code' => 'DZD', 'name' => __( 'Algerian Dinar', 'business-builder' ), 'symbol' => 'د.ج' ),
            'IQD' => array( 'code' => 'IQD', 'name' => __( 'Iraqi Dinar', 'business-builder' ), 'symbol' => 'ع.د' ),
            'TRY' => array( 'code' => 'TRY', 'name' => __( 'Turkish Lira', 'business-builder' ), 'symbol' => '₺' ),
            'CAD' => array( 'code' => 'CAD', 'name' => __( 'Canadian Dollar', 'business-builder' ), 'symbol' => 'C$' ),
            'AUD' => array( 'code' => 'AUD', 'name' => __( 'Australian Dollar', 'business-builder' ), 'symbol' => 'A$' ),
            'INR' => array( 'code' => 'INR', 'name' => __( 'Indian Rupee', 'business-builder' ), 'symbol' => '₹' ),
            'JPY' => array( 'code' => 'JPY', 'name' => __( 'Japanese Yen', 'business-builder' ), 'symbol' => '¥' ),
            'CNY' => array( 'code' => 'CNY', 'name' => __( 'Chinese Yuan', 'business-builder' ), 'symbol' => '¥' ),
            'CHF' => array( 'code' => 'CHF', 'name' => __( 'Swiss Franc', 'business-builder' ), 'symbol' => 'CHF' ),
            'SEK' => array( 'code' => 'SEK', 'name' => __( 'Swedish Krona', 'business-builder' ), 'symbol' => 'kr' ),
            'NOK' => array( 'code' => 'NOK', 'name' => __( 'Norwegian Krone', 'business-builder' ), 'symbol' => 'kr' ),
            'DKK' => array( 'code' => 'DKK', 'name' => __( 'Danish Krone', 'business-builder' ), 'symbol' => 'kr' ),
            'PLN' => array( 'code' => 'PLN', 'name' => __( 'Polish Zloty', 'business-builder' ), 'symbol' => 'zł' ),
            'ZAR' => array( 'code' => 'ZAR', 'name' => __( 'South African Rand', 'business-builder' ), 'symbol' => 'R' ),
            'NGN' => array( 'code' => 'NGN', 'name' => __( 'Nigerian Naira', 'business-builder' ), 'symbol' => '₦' ),
            'KES' => array( 'code' => 'KES', 'name' => __( 'Kenyan Shilling', 'business-builder' ), 'symbol' => 'KSh' ),
            'PKR' => array( 'code' => 'PKR', 'name' => __( 'Pakistani Rupee', 'business-builder' ), 'symbol' => '₨' ),
        );

        /**
         * Filter the supported currency catalogue.
         *
         * @param array<string, array{code:string,name:string,symbol:string}> $currencies Currencies.
         */
        return apply_filters( 'bb_payment_currencies', $currencies );
    }

    /**
     * All currency codes.
     *
     * @return string[]
     */
    public static function codes(): array {

        return array_keys( self::all() );
    }

    /**
     * Whether a currency code is known.
     *
     * @param string $code Currency code.
     * @return bool
     */
    public static function exists( string $code ): bool {

        return isset( self::all()[ self::normalize( $code ) ] );
    }

    /**
     * Options for a currency selector, preserving an unknown current value.
     *
     * Returns the full catalogue, and — when the currently stored code is
     * not part of it — appends a synthetic entry so a legacy/unknown value
     * is shown and preserved instead of being silently dropped from the
     * UI (it can still be replaced by choosing a catalogue currency).
     *
     * @param string $current Currently stored currency code (may be '').
     * @return array<string, array{code:string,name:string,symbol:string,legacy:bool}>
     */
    public static function select_options( string $current = '' ): array {

        $options = array();

        foreach ( self::all() as $code => $entry ) {
            $options[ $code ] = array(
                'code'   => (string) $entry['code'],
                'name'   => (string) $entry['name'],
                'symbol' => (string) $entry['symbol'],
                'legacy' => false,
            );
        }

        $current = self::normalize( $current );

        $known = ( '' !== $current ) && isset( $options[ $current ] );

        if ( false === $known && '' !== $current ) {
            $options[ $current ] = array(
                'code'   => $current,
                'name'   => __( 'Custom / legacy currency', 'business-builder' ),
                'symbol' => $current,
                'legacy' => true,
            );
        }

        return $options;
    }

    /**
     * Display symbol for a currency ('' when unknown).
     *
     * @param string $code Currency code.
     * @return string
     */
    public static function symbol( string $code ): string {

        $code = self::normalize( $code );

        $all = self::all();

        return isset( $all[ $code ] ) ? (string) $all[ $code ]['symbol'] : '';
    }

    /**
     * Readable name for a currency (falls back to the code).
     *
     * @param string $code Currency code.
     * @return string
     */
    public static function name( string $code ): string {

        $code = self::normalize( $code );

        $all = self::all();

        return isset( $all[ $code ] ) ? (string) $all[ $code ]['name'] : $code;
    }

    /**
     * Format an amount with its symbol/name.
     *
     * Uses number_format_i18n so the site's locale controls grouping and
     * decimal separators (Arabic WP installs show Arabic-Indic digits
     * where the locale specifies them).
     *
     * @param float  $amount   Amount.
     * @param string $code     Currency code.
     * @param bool   $with_code Append the code after the symbol.
     * @return string
     */
    public static function format( float $amount, string $code, bool $with_code = false ): string {

        $code      = self::normalize( $code );
        $formatted = number_format_i18n( $amount, 2 );

        $symbol = self::symbol( $code );

        if ( '' === $symbol ) {
            $symbol = $code;
        }

        $out = $formatted . ' ' . $symbol;

        if ( $with_code && $code !== $symbol ) {
            $out .= ' (' . $code . ')';
        }

        return trim( $out );
    }

    /**
     * Normalize a currency code (uppercase, 3 letters).
     *
     * @param string $code Raw code.
     * @return string
     */
    public static function normalize( string $code ): string {

        $code = strtoupper( preg_replace( '/[^A-Za-z]/', '', $code ) );

        return substr( $code, 0, 3 );
    }
}
