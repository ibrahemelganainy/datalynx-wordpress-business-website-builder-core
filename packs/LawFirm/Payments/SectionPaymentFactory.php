<?php
namespace BusinessBuilderCore\Packs\LawFirm\Payments;

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Settings\SiteSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Convenience factory for section payment resolution.
 *
 * The section render callbacks run from the frontend (and from the
 * editor preview) where no DI container is reachable, so this factory
 * builds a SectionPayment with fresh services on demand. The services it
 * wraps (PaymentManager, SiteSettings) are lightweight and read per-site
 * options, so there is no shared-state concern.
 */
final class SectionPaymentFactory {

    /**
     * Non-instantiable.
     */
    private function __construct() {
    }

    /**
     * Build a SectionPayment resolver.
     *
     * @return SectionPayment
     */
    public static function make(): SectionPayment {

        return new SectionPayment(
            new PaymentManager(),
            new SiteSettings()
        );
    }

    /**
     * Resolve the payment config for a consultation section.
     *
     * @param array $settings Section settings.
     * @return array<string, mixed>
     */
    public static function for_consultation( array $settings ): array {

        return self::make()->resolve( 'consultation', $settings );
    }

    /**
     * Resolve the payment config for an appointment section.
     *
     * @param array $settings Section settings.
     * @return array<string, mixed>
     */
    public static function for_appointment( array $settings ): array {

        return self::make()->resolve( 'appointment', $settings );
    }

    /**
     * Resolve the payment config for a stored page section.
     *
     * The frontend form only submits a page id + section id; the fee,
     * currency and gateway set are always re-read from the section's SAVED
     * meta here, never taken from the browser. Returns the resolved config
     * for a matching section, or null when the section cannot be found or is
     * not of the expected type.
     *
     * @param int    $page_id    Page id that owns the section.
     * @param string $section_id Section id (as stored in _bb_page_sections).
     * @param string $type       Expected section type (consultation|booking).
     * @param string $object_type 'consultation' | 'appointment'.
     * @return array<string, mixed>|null
     */
    public static function resolve_stored_section( int $page_id, string $section_id, string $type, string $object_type ): ?array {

        $page_id    = absint( $page_id );
        $section_id = sanitize_text_field( $section_id );
        $type       = sanitize_key( $type );

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

            $section_type = isset( $section['type'] ) ? sanitize_key( (string) $section['type'] ) : '';

            if ( $type !== $section_type ) {
                return null;
            }

            $settings = isset( $section['settings'] ) && is_array( $section['settings'] )
                ? $section['settings']
                : array();

            return self::make()->resolve( $object_type, $settings );
        }

        return null;
    }
}
