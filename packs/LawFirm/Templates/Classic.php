<?php
namespace BusinessBuilderCore\Packs\LawFirm\Templates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * "Classic" visual template descriptor.
 *
 * IMPORTANT (spec 21 / 22):
 * A visual template is a PRESENTATION layer only. All three templates
 * (Classic / Modern / Luxury) consume the SAME section data rendered by
 * SectionRenderer. Switching template NEVER requires recreating
 * Lawyers, Services, Practice Areas, or any entity.
 *
 * The actual look is applied on the front end by the wrapper class
 * emitted by SectionRenderer::render_page()
 * (bb-template-default | bb-template-modern | bb-template-luxury),
 * combined with the stylesheets in assets/css/frontend/.
 *
 * This class therefore holds no content logic. It only describes the
 * template for UI listing and verification purposes.
 */
class Classic {

    /**
     * Wrapper class applied to the rendered page.
     */
    public const WRAPPER_CLASS = 'bb-template-default';

    /**
     * Template slug stored in page meta (_bb_page_template).
     */
    public const SLUG = 'default';

    /**
     * Human-readable label.
     */
    public static function label(): string {

        return __( 'Classic', 'business-builder' );
    }

    /**
     * The wrapper class for this template.
     */
    public static function wrapper_class(): string {

        return self::WRAPPER_CLASS;
    }
}
