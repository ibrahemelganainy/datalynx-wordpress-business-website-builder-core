<?php

namespace BusinessBuilderCore\Packs\LawFirm\Appointments;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Convenience factory for section availability resolution.
 *
 * The section render callbacks run from the frontend (and from the editor
 * preview) where no DI container is reachable, so this factory builds a
 * configured Availability service on demand. Mirrors SectionPaymentFactory
 * so the availability path is equally simple to consume.
 */
final class AvailabilityFactory {

    /**
     * Non-instantiable.
     */
    private function __construct() {
    }

    /**
     * Build the effective availability configuration for a section.
     *
     * @param array<string, mixed> $settings Section settings.
     * @return AvailabilityConfig
     */
    public static function config( array $settings ): AvailabilityConfig {

        return AvailabilityConfig::from_section( $settings, ( new Availability() )->site_defaults() );
    }

    /**
     * Build an Availability service bound to a section's configuration.
     *
     * @param array<string, mixed> $settings Section settings.
     * @return Availability
     */
    public static function service( array $settings ): Availability {

        return ( new Availability() )->with_config( self::config( $settings ) );
    }

    /**
     * Resolve the availability configuration for a stored page section.
     *
     * The frontend form only submits a page id + section id; the schedule is
     * always re-read from the section's SAVED meta here, never taken from the
     * browser. Returns null when the section cannot be found or is not a
     * booking section.
     *
     * @param int    $page_id    Page id that owns the section.
     * @param string $section_id Section id (as stored in _bb_page_sections).
     * @return AvailabilityConfig|null
     */
    public static function resolve_stored_section( int $page_id, string $section_id ): ?AvailabilityConfig {

        $page_id    = absint( $page_id );
        $section_id = sanitize_text_field( $section_id );

        if ( $page_id <= 0 || '' === $section_id ) {
            return null;
        }

        $sections = get_post_meta( $page_id, '_bb_page_sections', true );

        if ( ! is_array( $sections ) ) {
            return null;
        }

        foreach ( $sections as $section ) {

            if ( ! is_array( $section ) ) {
                continue;
            }

            $id = isset( $section['id'] ) ? (string) $section['id'] : '';

            if ( $id !== $section_id ) {
                continue;
            }

            $type = isset( $section['type'] ) ? sanitize_key( (string) $section['type'] ) : '';

            if ( 'booking' !== $type ) {
                return null;
            }

            $settings = isset( $section['settings'] ) && is_array( $section['settings'] )
                ? $section['settings']
                : array();

            return self::config( $settings );
        }

        return null;
    }
}
