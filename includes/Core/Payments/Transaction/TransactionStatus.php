<?php

namespace BusinessBuilderCore\Core\Payments\Transaction;

use BusinessBuilderCore\Core\Payments\PaymentTransaction;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical transaction status model and legal transitions.
 *
 * Extends the flat status list owned by PaymentTransaction with the
 * workflow rules Phase F needs (an extensible state machine). The
 * status labels and allowed transitions are filterable so providers
 * and add-ons can introduce their own states without touching core.
 *
 * This class is purely additive: PaymentTransaction::statuses() is
 * still the source of the allowed slugs; this class layers workflow
 * semantics on top.
 */
final class TransactionStatus {

    /**
     * Statuses from which no further automatic transition is expected.
     *
     * @var string[]
     */
    private const FINAL_STATUSES = array( 'paid', 'refunded', 'cancelled', 'expired' );

    /**
     * Allowed transitions: from => [to, to, ...].
     *
     * @var array<string, string[]>
     */
    private const TRANSITIONS = array(
        'pending'    => array( 'processing', 'paid', 'failed', 'cancelled', 'expired' ),
        'processing' => array( 'paid', 'failed', 'cancelled', 'expired' ),
        'failed'     => array( 'pending' ),
        'paid'       => array( 'refunded' ),
        'refunded'   => array(),
        'cancelled'  => array(),
        'expired'    => array(),
    );

    /**
     * Non-instantiable utility.
     */
    private function __construct() {
    }

    /**
     * The status slugs known to the system.
     *
     * @return string[]
     */
    public static function all(): array {

        return PaymentTransaction::statuses();
    }

    /**
     * Allowed transitions map (filterable).
     *
     * @return array<string, string[]>
     */
    public static function transitions(): array {

        /**
         * Filter the transaction status transition map.
         *
         * @param array<string, string[]> $transitions from => to[].
         */
        return apply_filters( 'bb_payment_status_transitions', self::TRANSITIONS );
    }

    /**
     * Whether a slug is a known status.
     *
     * @param string $status Status slug.
     * @return bool
     */
    public static function is_valid( string $status ): bool {

        return in_array( sanitize_key( $status ), self::all(), true );
    }

    /**
     * Whether a status is terminal.
     *
     * @param string $status Status slug.
     * @return bool
     */
    public static function is_final( string $status ): bool {

        $status = sanitize_key( $status );

        /**
         * Filter which statuses are considered final.
         *
         * @param string[] $final Final status slugs.
         */
        $final = apply_filters( 'bb_payment_final_statuses', self::FINAL_STATUSES );

        return in_array( $status, $final, true );
    }

    /**
     * Whether a transition from => to is legal.
     *
     * A no-op (from === to) is always allowed so idempotent webhooks
     * never error.
     *
     * @param string $from Current status.
     * @param string $to   Desired status.
     * @return bool
     */
    public static function can_transition_to( string $from, string $to ): bool {

        $from = sanitize_key( $from );
        $to   = sanitize_key( $to );

        if ( $from === $to ) {
            return true;
        }

        $valid_target = in_array( $to, self::all(), true );

        if ( false === $valid_target ) {
            return false;
        }

        $map = self::transitions();

        $has_origin = array_key_exists( $from, $map );

        if ( false === $has_origin ) {

            /*
             * Unknown origin (e.g. a legacy/unknown stored value):
             * allow moving to a known status so data self-heals.
             */
            return true;
        }

        return in_array( $to, $map[ $from ], true );
    }

    /**
     * Human-readable label for a status.
     *
     * @param string $status Status slug.
     * @return string
     */
    public static function label( string $status ): string {

        $status = sanitize_key( $status );

        $labels = array(
            'pending'    => __( 'Pending', 'business-builder' ),
            'processing' => __( 'Processing', 'business-builder' ),
            'paid'       => __( 'Paid', 'business-builder' ),
            'failed'     => __( 'Failed', 'business-builder' ),
            'cancelled'  => __( 'Cancelled', 'business-builder' ),
            'refunded'   => __( 'Refunded', 'business-builder' ),
            'expired'    => __( 'Expired', 'business-builder' ),
        );

        /**
         * Filter the transaction status labels.
         *
         * @param array<string, string> $labels slug => label.
         */
        $labels = apply_filters( 'bb_payment_status_labels', $labels );

        return $labels[ $status ] ?? $status;
    }
}
