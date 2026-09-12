<?php
namespace BusinessBuilderCore\Packs\LawFirm\Templates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * "Luxury" visual template descriptor.
 *
 * IMPORTANT (spec 21 / 22):
 * A visual template is a PRESENTATION layer only. Luxury, Classic and
 * Modern all consume the SAME section data rendered by SectionRenderer.
 * Switching template NEVER requires recreating Lawyers, Services,
 * Practice Areas, or any other entity.
 *
 * The luxury look is applied on the front end by the wrapper class
 * bb-template-luxury (emitted by SectionRenderer::render_page) plus the
 * stylesheets in assets/css/frontend/.
 *
 * This class holds no content logic. It only describes the template.
 */
class Luxury {

    /**
     * Wrapper class applied to the rendered page.
     */
    public const WRAPPER_CLASS = 'bb-template-luxury';

    /**
     * Template slug stored in page meta (_bb_page_template).
     */
    public const SLUG = 'luxury';

    /**
     * Human-readable label.
     */
    public static function label(): string {

        return __( 'Luxury', 'business-builder' );
    }

    /**
     * The wrapper class for this template.
     */
    public static function wrapper_class(): string {

        return self::WRAPPER_CLASS;
    }
}
