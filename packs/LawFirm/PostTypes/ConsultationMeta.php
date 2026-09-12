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
            'processing'   => __( 'Processing', 'business-builder' ),
            'paid'         => __( 'Paid', 'business-builder' ),
            'failed'       => __( 'Failed', 'business-builder' ),
            'cancelled'    => __( 'Cancelled', 'business-builder' ),
            'refunded'     => __( 'Refunded', 'business-builder' ),
            'expired'      => __( 'Expired', 'business-builder' ),
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
     * Generate a secure, non-sequential public reference.
     *
     * The prefix makes references recognizable in the UI while the
     * random suffix (CSPRNG, ~8 bytes base32) makes them effectively
     * non-enumerable. This value is what customers use on the private
     * status page, never the internal post ID (spec: privacy).
     *
     * @return string
     */
    public static function generate_public_reference(): string {

        $token = self::random_token( 10 );

        return 'CNS-' . $token;
    }

    /**
     * Cryptographic random token, uppercase base32-ish alphabet.
     *
     * Uses random_bytes() (CSPRNG) and avoids ambiguous characters so
     * the reference is easy to read and type. Falls back to a weaker
     * but still non-sequential source only if random_bytes() is not
     * available (extremely old PHP); WordPress requires PHP 7+, so this
     * path is effectively unreachable but keeps the code total.
     *
     * @param int $length Number of characters.
     * @return string
     */
    private static function random_token( int $length ): string {

        $length = max( 4, $length );

        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max      = strlen( $alphabet ) - 1;

        $out = '';

        try {

            $bytes = random_bytes( $length );

            for ( $i = 0; $i < $length; $i++ ) {

                $index = ord( $bytes[ $i ] ) % ( $max + 1 );

                $out .= $alphabet[ $index ];
            }

            return $out;

        } catch ( \Exception $exception ) {

            /*
             * Last-resort fallback: still not an incremental id, and
             * mixed with a per-site salt so it cannot be guessed from
             * the sequence of previously issued references.
             */
            $seed = wp_generate_password( 32, false, false )
                . microtime( true )
                . wp_rand();

            $digest = md5( $seed );

            return strtoupper( substr( $digest, 0, $length ) );
        }
    }

    /**
     * Resolve a stored practice-area value to a readable term.
     *
     * Accepts a term ID, a term slug (plain or URL-encoded) or a term
     * name and returns the matching WP_Term, or null.
     *
     * Historical note (bug fix): the consultation form used to store
     * the raw slug of the selected term. For Arabic terms WordPress
     * generates a percent-encoded slug (e.g. %d9%82%d8%a7...), and a
     * second sanitize_title() pass produced a value that never matched
     * the term again, so the admin displayed the encoded slug. We now
     * store the term ID going forward, while every legacy record must
     * still resolve to the correct, human-readable term name.
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
         * Build the candidate list. Decode repeatedly: legacy records
         * may hold a single- or double-encoded slug (%25d9... =>
         * %d9... => Arabic). Candidates are only compared against real
         * terms, so decoding never leaks a raw slug to output.
         */
        $candidates = array( $value );

        $decoded = $value;

        for ( $i = 0; $i < 3; $i++ ) {

            $next = rawurldecode( $decoded );

            if ( $next === $decoded ) {
                break;
            }

            $candidates[] = $next;
            $decoded      = $next;
        }

        /*
         * WordPress sanitizes slugs on save; also try a sanitized-title
         * form of each candidate so encoded legacy values resolve.
         */
        $slug_candidates = $candidates;

        foreach ( $candidates as $candidate ) {
            $slug_candidates[] = sanitize_title( $candidate );
        }

        foreach ( array_unique( $slug_candidates ) as $candidate ) {

            $term = get_term_by( 'slug', $candidate, $taxonomy );

            if ( $term instanceof \WP_Term ) {
                return $term;
            }
        }

        foreach ( array_unique( $candidates ) as $candidate ) {

            $term = get_term_by( 'name', $candidate, $taxonomy );

            if ( $term instanceof \WP_Term ) {
                return $term;
            }
        }

        return null;
    }

    /**
     * The stored raw practice-area meta value for a consultation.
     *
     * Prefers the canonical term ID, falling back to the legacy
     * slug/name value stored in older records.
     *
     * @param int $consultation_id Consultation id.
     * @return string
     */
    public static function stored_practice_area( int $consultation_id ): string {

        $id = (int) get_post_meta(
            $consultation_id,
            self::key( 'practice_area_id' ),
            true
        );

        if ( $id > 0 ) {
            return (string) $id;
        }

        return (string) get_post_meta(
            $consultation_id,
            self::key( 'practice_area' ),
            true
        );
    }

    /**
     * Human-readable practice-area name for a consultation.
     *
     * Single presentation helper for every admin/frontend screen so a
     * raw slug or encoded value can never reach the UI (spec 26).
     *
     * @param int $consultation_id Consultation id.
     * @return string
     */
    public static function practice_area_name( int $consultation_id ): string {

        $term = self::resolve_practice_area(
            self::stored_practice_area( $consultation_id )
        );

        return $term instanceof \WP_Term ? (string) $term->name : '';
    }

    /**
     * Resolve a submitted practice-area value to a term.
     *
     * The consultation form may post a term ID, slug or name. This
     * normalizes any of those to a real term using the same safe
     * resolution chain as resolve_practice_area().
     *
     * @param mixed $value Submitted value.
     * @return \WP_Term|null
     */
    public static function normalize_submitted_practice_area( $value ): ?\WP_Term {

        return self::resolve_practice_area( $value );
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
