<?php

namespace BusinessBuilderCore\Packs\LawFirm\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Consultation request meta: statuses, payment states, and helpers.
 *
 * Single source of truth for the consultation data model so the form,
 * the admin panel and any future payment provider agree on keys and
 * allowed values. Kept separate from the CPT class to keep each class
 * focused (spec 31 / 35).
 *
 * Meta keys (all prefixed _bb_consultation_):
 *   name, phone, email, practice_area (name), practice_area_id (term id),
 *   message, preferred_contact, created,
 *   status, payment_required, payment_status, payment_amount,
 *   payment_currency, payment_reference
 */
class ConsultationMeta {

    /**
     * Request statuses (extensible via the filter below).
     *
     * @return array<string, string>
     */
    public static function statuses(): array {

        $statuses = array(
            'new'       => __( 'New', 'business-builder' ),
            'pending'   => __( 'Pending', 'business-builder' ),
            'contacted' => __( 'Contacted', 'business-builder' ),
            'scheduled' => __( 'Scheduled', 'business-builder' ),
            'completed' => __( 'Completed', 'business-builder' ),
            'cancelled' => __( 'Cancelled', 'business-builder' ),
        );

        /**
         * Allow extensions to register additional consultation statuses.
         *
         * @param array<string, string> $statuses slug => label.
         */
        return apply_filters( 'bb_consultation_statuses', $statuses );
    }

    /**
     * Payment states.
     *
     * @return array<string, string>
     */
    public static function payment_states(): array {

        $states = array(
            'not_required' => __( 'Not Required', 'business-builder' ),
            'pending'      => __( 'Pending', 'business-builder' ),
            'paid'         => __( 'Paid', 'business-builder' ),
            'failed'       => __( 'Failed', 'business-builder' ),
            'refunded'     => __( 'Refunded', 'business-builder' ),
        );

        /**
         * Allow extensions (payment providers) to register states.
         *
         * @param array<string, string> $states slug => label.
         */
        return apply_filters( 'bb_consultation_payment_states', $states );
    }

    /**
     * Default request status.
     */
    public static function default_status(): string {

        return 'new';
    }

    /**
     * Human label for a status slug.
     *
     * @param string $status Status slug.
     * @return string
     */
    public static function status_label( string $status ): string {

        $statuses = self::statuses();

        return isset( $statuses[ $status ] )
            ? $statuses[ $status ]
            : $status;
    }

    /**
     * Human label for a payment state slug.
     *
     * @param string $state Payment state slug.
     * @return string
     */
    public static function payment_label( string $state ): string {

        $states = self::payment_states();

        return isset( $states[ $state ] )
            ? $states[ $state ]
            : $state;
    }

    /**
     * The full meta key for a short name.
     *
     * @param string $name Short name without prefix.
     * @return string
     */
    public static function key( string $name ): string {

        return '_bb_consultation_' . sanitize_key( $name );
    }

    /**
     * Resolve a stored practice-area value to a readable term.
     *
     * Accepts either a term ID or a term slug/name and returns the
     * matching WP_Term, or null. This lets older records (which stored
     * a slug) and newer records (which store an ID) both resolve to a
     * human-readable term for the admin (spec 26).
     *
     * @param mixed $value Raw stored value.
     * @return \WP_Term|null
     */
    public static function resolve_practice_area( $value ): ?\WP_Term {

        $value = is_scalar( $value ) ? trim( (string) $value ) : '';

        if ( '' === $value ) {
            return null;
        }

        $taxonomy = self::practice_area_taxonomy();

        /* Numeric => term ID. */
        if ( ctype_digit( $value ) ) {

            $term = get_term( (int) $value, $taxonomy );

            return ( $term instanceof \WP_Term ) ? $term : null;
        }

        /*
         * Otherwise treat as slug, then name. Decode first: legacy
         * records stored a URL-encoded slug (e.g. %d9%82...).
         */
        $candidates = array(
            $value,
            rawurldecode( $value ),
        );

        foreach ( $candidates as $candidate ) {

            $term = get_term_by( 'slug', $candidate, $taxonomy );

            if ( $term instanceof \WP_Term ) {
                return $term;
            }
        }

        foreach ( $candidates as $candidate ) {

            $term = get_term_by( 'name', $candidate, $taxonomy );

            if ( $term instanceof \WP_Term ) {
                return $term;
            }
        }

        return null;
    }

    /**
     * The practice-area taxonomy slug.
     *
     * @return string
     */
    public static function practice_area_taxonomy(): string {

        return 'bb_practice_area';
    }
}
