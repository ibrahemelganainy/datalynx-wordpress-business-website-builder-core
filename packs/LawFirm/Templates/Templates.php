<?php
namespace BusinessBuilderCore\Packs\LawFirm\Templates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * LawFirm visual templates registry.
 *
 * Lists the templates the pack supports and maps each to the wrapper
 * class emitted by SectionRenderer. Presentation only: every template
 * consumes the SAME section data (spec 21 / 22).
 *
 * Kept deliberately small: it exists so the template list is defined in
 * one place rather than duplicated across UI and verification code.
 */
class Templates {

    /**
     * Template slug => descriptor class.
     *
     * @return array<string, string>
     */
    public static function map(): array {

        return array(
            Classic::SLUG => Classic::class,
            Modern::SLUG  => Modern::class,
            Luxury::SLUG  => Luxury::class,
        );
    }

    /**
     * All template slugs.
     *
     * @return string[]
     */
    public static function slugs(): array {

        return array_keys( self::map() );
    }

    /**
     * Whether a template slug is supported.
     *
     * @param string $slug Template slug.
     * @return bool
     */
    public static function exists( string $slug ): bool {

        return array_key_exists( $slug, self::map() );
    }

    /**
     * Wrapper class for a template slug (falls back to Classic).
     *
     * @param string $slug Template slug.
     * @return string
     */
    public static function wrapper_class( string $slug ): string {

        $map = self::map();

        if ( ! isset( $map[ $slug ] ) ) {
            return Classic::WRAPPER_CLASS;
        }

        $class = $map[ $slug ];

        return $class::wrapper_class();
    }
}
