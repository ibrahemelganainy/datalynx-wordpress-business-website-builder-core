<?php

namespace BusinessBuilderCore\Packs\LawFirm\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Renders the single Lawyer profile page.
 *
 * The profile is built entirely from the Lawyer entity's own data
 * (post fields + meta + taxonomy terms). Nothing is duplicated into
 * pages - the CPT is the single source of truth (spec 12).
 *
 * Template resolution:
 *   1. If the active theme provides single-bb_lawyer.php, it wins
 *      (themes must always be able to override).
 *   2. Otherwise the pack's bundled profile template is used.
 */
class LawyerProfile {

    /**
     * Post type slug.
     */
    private const POST_TYPE = 'bb_lawyer';

    /**
     * Register frontend hooks.
     */
    public function register(): void {

        add_filter(
            'template_include',
            array( $this, 'template' )
        );

        add_action(
            'wp_enqueue_scripts',
            array( $this, 'enqueue_assets' )
        );
    }

    /**
     * Enqueue the frontend stylesheet on single Lawyer views.
     *
     * The shared frontend.css bundle is normally loaded only on
     * builder pages, so a CPT single (which is not a builder page)
     * needs it explicitly to get the profile styles.
     */
    public function enqueue_assets(): void {

        if ( ! is_singular( self::POST_TYPE ) ) {
            return;
        }

        wp_enqueue_style(
            'bb-frontend',
            BB_CORE_URL . 'assets/css/frontend.css',
            array(),
            BB_CORE_VERSION
        );
    }

    /**
     * Resolve the template used for single Lawyer views.
     *
     * @param string $template Current template path chosen by WP.
     * @return string
     */
    public function template( string $template ): string {

        if ( ! is_singular( self::POST_TYPE ) ) {
            return $template;
        }

        /*
         * Respect a theme-provided template first.
         */
        $theme_template = locate_template(
            array( 'single-' . self::POST_TYPE . '.php' )
        );

        if ( '' !== $theme_template ) {
            return $theme_template;
        }

        $pack_template = BB_CORE_PATH . 'templates/single-bb_lawyer.php';

        if ( file_exists( $pack_template ) ) {
            return $pack_template;
        }

        return $template;
    }

    /**
     * Canonical URL for a lawyer profile (single source of truth).
     *
     * Every place that links to a lawyer profile MUST use this helper
     * (spec 14) so URL generation is never duplicated and always goes
     * through the WordPress permalink API (works on Multisite, subdir
     * installs, HTTPS, custom home/site URLs).
     *
     * @param int $post_id Lawyer post ID.
     * @return string
     */
    public static function url( int $post_id ): string {

        if ( $post_id <= 0 ) {
            return '';
        }

        $permalink = get_permalink( $post_id );

        return is_string( $permalink ) ? $permalink : '';
    }

    /**
     * Whether a lawyer profile exists and is publicly viewable.
     *
     * Only published lawyers have a working single URL; draft/auto-draft
     * records would 404, so callers can guard links with this (spec 10).
     *
     * @param int $post_id Lawyer post ID.
     * @return bool
     */
    public static function is_public( int $post_id ): bool {

        return 'publish' === get_post_status( $post_id );
    }

    /**
     * Get all view-model data for a lawyer.
     *
     * Keeps the template free of meta-key knowledge so the data
     * contract lives in one place.
     *
     * @param int $post_id Lawyer post ID.
     * @return array
     */
    public static function get_data( int $post_id ): array {

        $post = get_post( $post_id );

        if ( ! $post instanceof \WP_Post ) {
            return array();
        }

        $practice_areas = get_the_terms(
            $post_id,
            'bb_practice_area'
        );

        if ( is_wp_error( $practice_areas ) || ! is_array( $practice_areas ) ) {
            $practice_areas = array();
        }

        return array(
            'id'             => $post_id,
            'name'           => $post->post_title,
            'permalink'      => get_permalink( $post_id ),
            'photo_id'       => (int) get_post_thumbnail_id( $post_id ),
            'title'          => (string) get_post_meta( $post_id, '_bb_lawyer_title', true ),
            'experience'     => (string) get_post_meta( $post_id, '_bb_lawyer_experience', true ),
            'license_number' => (string) get_post_meta( $post_id, '_bb_lawyer_license_number', true ),
            'education'      => (string) get_post_meta( $post_id, '_bb_lawyer_education', true ),
            'languages'      => (string) get_post_meta( $post_id, '_bb_lawyer_languages', true ),
            'phone'          => (string) get_post_meta( $post_id, '_bb_lawyer_phone', true ),
            'whatsapp'       => (string) get_post_meta( $post_id, '_bb_lawyer_whatsapp', true ),
            'email'          => (string) get_post_meta( $post_id, '_bb_lawyer_email', true ),
            'linkedin'       => (string) get_post_meta( $post_id, '_bb_lawyer_linkedin', true ),
            'facebook'       => (string) get_post_meta( $post_id, '_bb_lawyer_facebook', true ),
            'x'              => (string) get_post_meta( $post_id, '_bb_lawyer_x', true ),
            'short_bio'      => (string) $post->post_excerpt,
            'full_bio'       => (string) $post->post_content,
            'practice_areas' => $practice_areas,
        );
    }
}
