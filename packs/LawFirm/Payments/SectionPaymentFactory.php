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
}
