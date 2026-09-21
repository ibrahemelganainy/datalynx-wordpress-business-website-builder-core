<?php

namespace BusinessBuilderCore\Core\Payments\Transaction;

defined( 'ABSPATH' ) || exit;

/**
 * Secure, non-sequential public reference generator.
 *
 * A single, authoritative source for the reference numbers shown to
 * customers. Two shapes are produced:
 *
 *   - transaction references (prefix "TXN-"), which identify a payment,
 *   - object references (prefix "CNS-" / "APT-"), which identify the
 *     underlying consultation or appointment.
 *
 * Both use a CSPRNG (random_bytes) and an unambiguous alphabet
 * (no 0/O/1/I) so they are easy to read over the phone yet effectively
 * impossible to enumerate (spec: prevent enumeration of private status
 * pages). This centralises the token logic that previously lived in
 * PaymentManager and ConsultationMeta without changing their public
 * behaviour.
 */
final class Reference {

    /**
     * Unambiguous uppercase alphabet (Crockford-ish base32).
     */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Default body length (characters after the prefix).
     */
    public const DEFAULT_LENGTH = 10;

    /**
     * Transaction reference prefix.
     */
    public const TRANSACTION_PREFIX = 'TXN';

    /**
     * Consultation object reference prefix.
     */
    public const CONSULTATION_PREFIX = 'CNS';

    /**
     * Appointment object reference prefix.
     */
    public const APPOINTMENT_PREFIX = 'APT';

    /**
     * Receipt number prefix.
     */
    public const RECEIPT_PREFIX = 'RCP';

    /**
     * Non-instantiable utility.
     */
    private function __construct() {
    }

    /**
     * Build a prefixed reference: "{PREFIX}-{token}".
     *
     * @param string $prefix Prefix without the dash (e.g. "TXN").
     * @param int    $length Token length.
     * @return string
     */
    public static function generate( string $prefix, int $length = self::DEFAULT_LENGTH ): string {

        $prefix = strtoupper( sanitize_key( $prefix ) );

        if ( '' === $prefix ) {
            $prefix = self::TRANSACTION_PREFIX;
        }

        return $prefix . '-' . self::token( $length );
    }

    /**
     * A transaction reference, e.g. TXN-8F3KQ2M7ZP.
     *
     * @param int $length Token length.
     * @return string
     */
    public static function transaction( int $length = self::DEFAULT_LENGTH ): string {

        return self::generate( self::TRANSACTION_PREFIX, $length );
    }

    /**
     * A consultation reference, e.g. CNS-8F3KQ2M7ZP.
     *
     * @param int $length Token length.
     * @return string
     */
    public static function consultation( int $length = self::DEFAULT_LENGTH ): string {

        return self::generate( self::CONSULTATION_PREFIX, $length );
    }

    /**
     * An appointment reference, e.g. APT-8F3KQ2M7ZP.
     *
     * @param int $length Token length.
     * @return string
     */
    public static function appointment( int $length = self::DEFAULT_LENGTH ): string {

        return self::generate( self::APPOINTMENT_PREFIX, $length );
    }

    /**
     * A receipt number, e.g. RCP-8F3KQ2M7ZP.
     *
     * @param int $length Token length.
     * @return string
     */
    public static function receipt( int $length = self::DEFAULT_LENGTH ): string {

        return self::generate( self::RECEIPT_PREFIX, $length );
    }

    /**
     * A stable receipt number derived from a transaction's public reference.
     *
     * The receipt number is a DISTINCT concept from the payment reference
     * (TXN-…). It is derived deterministically from the transaction reference
     * so the same payment always yields the same receipt number, even before
     * the number is persisted, and it never changes or breaks the existing
     * references or URLs.
     *
     * @param string $public_ref Public transaction reference (TXN-…).
     * @return string Receipt number (RCP-…), or '' when no reference is given.
     */
    public static function receipt_from_transaction( string $public_ref ): string {

        $public_ref = strtoupper( trim( $public_ref ) );

        if ( '' === $public_ref ) {
            return '';
        }

        $tail = $public_ref;
        if ( false !== strpos( $public_ref, '-' )) {
            $parts = explode( '-', $public_ref );
            $tail  = (string) end( $parts );
        }
        $tail = is_string( $tail ) ? $tail : '';
        $tail = preg_replace( '/[^A-Z0-9]/', '', strtoupper( $tail ) );

        if ( '' === $tail ) {
            $tail = strtoupper( substr( md5( $public_ref ), 0, 8 ) );
        }

        return self::RECEIPT_PREFIX . '-' . $tail;
    }

    /**
     * Raw CSPRNG token using the unambiguous alphabet.
     *
     * Falls back to a salted digest only when random_bytes() is
     * unavailable; the fallback is still non-sequential and site-salted
     * so it cannot be guessed from previously issued references.
     *
     * @param int $length Number of characters.
     * @return string
     */
    public static function token( int $length = self::DEFAULT_LENGTH ): string {

        $length = max( 4, $length );

        $alphabet_size = strlen( self::ALPHABET );

        $out = '';

        try {

            $bytes = random_bytes( $length );

            for ( $i = 0; $i < $length; $i++ ) {

                $index = ord( $bytes[ $i ] ) % $alphabet_size;

                $out .= self::ALPHABET[ $index ];
            }

            return $out;

        } catch ( \Exception $exception ) {

            $seed = wp_generate_password( 32, false, false )
                . microtime( true )
                . wp_rand();

            $digest = md5( $seed );

            return strtoupper( substr( $digest, 0, $length ) );
        }
    }

    /**
     * Whether a value looks like a well-formed reference.
     *
     * Accepts an optional prefix and a token made only of the
     * unambiguous alphabet. Used to reject obviously invalid input
     * before any lookup (cheap anti-enumeration guard).
     *
     * @param string $reference Candidate reference.
     * @return bool
     */
    public static function is_valid( string $reference ): bool {

        $reference = strtoupper( trim( $reference ) );

        if ( '' === $reference ) {
            return false;
        }

        $prefixes = array(
            self::TRANSACTION_PREFIX,
            self::CONSULTATION_PREFIX,
            self::APPOINTMENT_PREFIX,
        );

        $pattern = '/^(?:' . implode( '|', $prefixes ) . ')-[' . self::ALPHABET . ']{4,32}$/';

        return (bool) preg_match( $pattern, $reference );
    }

    /**
     * Normalize a user-supplied reference (uppercase, trimmed).
     *
     * @param string $reference Raw reference.
     * @return string
     */
    public static function normalize( string $reference ): string {

        return strtoupper( trim( $reference ) );
    }
}
