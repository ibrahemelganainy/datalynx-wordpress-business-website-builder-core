<?php

namespace BusinessBuilderCore\Packs\LawFirm\Sections;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Reusable query logic for the LawFirm dynamic sections.
 *
 * Centralizes the WP_Query arguments so section render methods
 * stay focused on presentation (spec Â§28) instead of embedding
 * large query blocks in rendering code.
 *
 * Every method returns an array of WP_Post objects and honours
 * the shared section settings: limit, featured-only, order,
 * and a practice-area filter where the entity supports it.
 */
class LawFirmQueries {

    /**
     * Post types.
     */
    public const LAWYER = 'bb_lawyer';
    public const LEGAL_SERVICE = 'bb_legal_service';
    public const TESTIMONIAL = 'bb_testimonial';
    public const FAQ = 'bb_faq';

    /**
     * Practice Area taxonomy.
     */
    public const PRACTICE_AREA_TAX = 'bb_practice_area';

    /**
     * FAQ Category taxonomy.
     */
    public const FAQ_CATEGORY_TAX = 'bb_faq_category';

    /**
     * Build common query args shared by all entities.
     *
     * @param string $post_type   Post type slug.
     * @param array  $settings    Section settings.
     * @param string $order_meta  Meta key used for display order.
     * @return array
     */
    private function base_args(
        string $post_type,
        array $settings,
        string $order_meta
    ): array {

        $limit = isset( $settings['limit'] )
            ? absint( $settings['limit'] )
            : 0;

        $direction = ( ! empty( $settings['order'] ) && 'desc' === $settings['order'] ) ? 'DESC' : 'ASC';

        $args = array(
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => $limit > 0 ? $limit : -1,
            'no_found_rows'  => true,
            /*
             * Order by the display-order meta via a NAMED clause rather than
             * a bare meta_key. A bare meta_key makes WordPress INNER JOIN
             * the meta table, which silently DROPS any record that has never
             * been given a display order. The named-clause form keeps those
             * records (ordered last) so a freshly created item always shows.
             */
            'orderby'        => array(
                'order_clause' => $direction,
                'date'         => 'DESC',
            ),
        );

        $args['meta_query'] = array(
            'relation'     => 'AND',
            'order_clause' => array(
                'relation' => 'OR',
                array(
                    'key'     => $order_meta,
                    'compare' => 'EXISTS',
                ),
                array(
                    'key'     => $order_meta,
                    'compare' => 'NOT EXISTS',
                ),
            ),
        );

        return $args;
    }

    /**
     * Apply a "featured only" meta filter when requested.
     *
     * @param array  $args     Query args (by reference).
     * @param array  $settings Section settings.
     * @param string $meta_key Featured meta key.
     */
    private function apply_featured(
        array &$args,
        array $settings,
        string $meta_key
    ): void {

        if ( empty( $settings['featured'] ) ) {
            return;
        }

        if ( ! isset( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
            $args['meta_query'] = array( 'relation' => 'AND' );
        }

        $args['meta_query']['featured_clause'] = array(
            'key'     => $meta_key,
            'value'   => '1',
            'compare' => '=',
        );
    }

    /**
     * Apply a practice-area tax filter when requested.
     *
     * @param array  $args     Query args (by reference).
     * @param array  $settings Section settings.
     */
    private function apply_practice_area(
        array &$args,
        array $settings
    ): void {

        if ( empty( $settings['practice_area'] ) ) {
            return;
        }

        $term = sanitize_title( (string) $settings['practice_area'] );

        if ( '' === $term ) {
            return;
        }

        if ( ! isset( $args['tax_query'] ) || ! is_array( $args['tax_query'] ) ) {
            $args['tax_query'] = array();
        }

        $args['tax_query'][] = array(
            'taxonomy' => self::PRACTICE_AREA_TAX,
            'field'    => 'slug',
            'terms'    => $term,
        );
    }

    /**
     * Query lawyers.
     *
     * @param array $settings Section settings.
     * @return \WP_Post[]
     */
    public function lawyers( array $settings ): array {

        $args = $this->base_args(
            self::LAWYER,
            $settings,
            '_bb_lawyer_display_order'
        );

        $this->apply_featured(
            $args,
            $settings,
            '_bb_lawyer_featured'
        );

        $this->apply_practice_area( $args, $settings );

        /*
         * Status gate: only ACTIVE lawyers are listed. A lawyer with no
         * status meta yet is treated as active (matches get_status()'s
         * default), so existing records are never hidden unexpectedly.
         */
        $this->apply_active_only( $args );

        return get_posts( $args );
    }

    /**
     * Restrict a lawyer query to active records (status = active or unset).
     *
     * Uses meta_query only (no per-post meta lookups), so listing many
     * lawyers never becomes an N+1 query.
     *
     * @param array $args Query args (by reference).
     */
    private function apply_active_only( array &$args ): void {

        if ( ! isset( $args['meta_query'] ) || ! is_array( $args['meta_query'] )) {
            $args['meta_query'] = array( 'relation' => 'AND' );
        }

        /*
         * A lawyer is "active" when the status meta is exactly 'active' OR
         * absent (the same default get_status() applies), so existing
         * records are never hidden unexpectedly.
         */
        $args['meta_query']['status_clause'] = array(
            'relation' => 'OR',
            array(
                'key'     => '_bb_lawyer_status',
                'value'   => 'active',
                'compare' => '=',
            ),
            array(
                'key'     => '_bb_lawyer_status',
                'compare' => 'NOT EXISTS',
            ),
        );
    }

    /**
     * Query legal services.
     *
     * @param array $settings Section settings.
     * @return \WP_Post[]
     */
    public function legal_services( array $settings ): array {

        $args = $this->base_args(
            self::LEGAL_SERVICE,
            $settings,
            '_bb_legal_service_display_order'
        );

        $this->apply_featured(
            $args,
            $settings,
            '_bb_legal_service_featured'
        );

        $this->apply_practice_area( $args, $settings );

        return get_posts( $args );
    }

    /**
     * Query testimonials.
     *
     * @param array $settings Section settings.
     * @return \WP_Post[]
     */
    public function testimonials( array $settings ): array {

        $args = $this->base_args(
            self::TESTIMONIAL,
            $settings,
            '_bb_testimonial_display_order'
        );

        $this->apply_featured(
            $args,
            $settings,
            '_bb_testimonial_featured'
        );

        return get_posts( $args );
    }

    /**
     * Query FAQs, optionally filtered by FAQ category.
     *
     * @param array $settings Section settings.
     * @return \WP_Post[]
     */
    public function faqs( array $settings ): array {

        $args = $this->base_args(
            self::FAQ,
            $settings,
            '_bb_faq_display_order'
        );

        $this->apply_featured(
            $args,
            $settings,
            '_bb_faq_featured'
        );

        if ( ! empty( $settings['faq_category'] ) ) {

            $term = sanitize_title( (string) $settings['faq_category'] );

            if ( '' !== $term ) {
                $args['tax_query'] = array(
                    array(
                        'taxonomy' => self::FAQ_CATEGORY_TAX,
                        'field'    => 'slug',
                        'terms'    => $term,
                    ),
                );
            }
        }

        return get_posts( $args );
    }

    /**
     * Query practice area terms.
     *
     * @param array $settings Section settings.
     * @return \WP_Term[]
     */
    public function practice_areas( array $settings ): array {

        $limit = isset( $settings['limit'] )
            ? absint( $settings['limit'] )
            : 0;

        $args = array(
            'taxonomy'   => self::PRACTICE_AREA_TAX,
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
        );

        if ( $limit > 0 ) {
            $args['number'] = $limit;
        }

        $terms = get_terms( $args );

        if ( is_wp_error( $terms ) ) {
            return array();
        }

        /*
         * Optional "featured only" filter, applied at the term
         * level since practice areas are a taxonomy.
         */
        if ( ! empty( $settings['featured'] ) ) {

            $terms = array_filter(
                $terms,
                function ( $term ) {

                    return '1' === get_term_meta(
                        $term->term_id,
                        '_bb_practice_area_featured',
                        true
                    );
                }
            );
        }

        return array_values( $terms );
    }

    /**
     * Get a section's content value with a fallback.
     *
     * @param array  $content Section content.
     * @param string $key     Content key.
     * @param mixed  $default Fallback.
     * @return mixed
     */
    public static function content(
        array $content,
        string $key,
        $default = ''
    ) {

        return isset( $content[ $key ] ) && '' !== $content[ $key ]
            ? $content[ $key ]
            : $default;
    }
}
