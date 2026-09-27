<?php
/*
 * Phase 11 verification: section variant architecture.
 * - infrastructure resolves safely (whitelist, fallback, traversal-proof)
 * - the pack registers its variants
 * - the variant setting appears in the schema
 * - variant templates render and REUSE the Phase-10 components
 */
define( 'WP_USE_THEMES', true );
define( 'WP_ADMIN', true );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

function check( string $label, bool $ok ): void {
    echo ( $ok ? 'PASS' : 'FAIL' ) . ' — ' . $label . PHP_EOL;
}

$blog_id = 2;
switch_to_blog( $blog_id );

/*
 * The variant templates render through the theme's bb_component() API. This
 * harness boots before the theme is switched, so provide the minimal theme
 * constants the component API needs, then load ONLY the component file.
 */
if ( ! defined( 'BB_THEME_PATH' ) ) {
    define( 'BB_THEME_PATH', trailingslashit( WP_CONTENT_DIR . '/themes/business-builder' ) );
}
if ( ! defined( 'BB_THEME_URL' ) ) {
    define( 'BB_THEME_URL', trailingslashit( WP_CONTENT_URL . '/themes/business-builder' ) );
}

if ( ! function_exists( 'bb_component' ) ) {
    require_once WP_CONTENT_DIR . '/themes/business-builder/inc/components.php';
}

/* Register the pack component root the same way the pack does at init. */
add_filter( 'bb_component_roots', function ( $roots ) {
    $roots   = is_array( $roots ) ? $roots : array();
    $roots[] = WP_CONTENT_DIR . '/plugins/business-builder-core/packs/LawFirm/Sections/components';
    return $roots;
} );

/* Core section-variant API. */
if ( ! function_exists( 'bb_section_variants' ) ) {
    require_once WP_CONTENT_DIR . '/plugins/business-builder-core/includes/Builder/SectionVariants.php';
    require_once WP_CONTENT_DIR . '/plugins/business-builder-core/includes/Builder/section-variants.php';
}

check( 'SectionVariants class loads', class_exists( 'BusinessBuilderCore\\Builder\\SectionVariants' ) );
check( 'bb_section_variants() exists', function_exists( 'bb_section_variants' ) );
check( 'bb_resolve_section_variant() exists', function_exists( 'bb_resolve_section_variant' ) );
check( 'bb_render_section_variant() exists', function_exists( 'bb_render_section_variant' ) );
check( 'bb_section_variant_options() exists', function_exists( 'bb_section_variant_options' ) );

/* Pack registers its variants (the same hook the plugin fires at init). */
if ( class_exists( 'BusinessBuilderCore\\Packs\\LawFirm\\Sections\\LawFirmSections' ) ) {
    $pack = new \BusinessBuilderCore\Packs\LawFirm\Sections\LawFirmSections(
        new \BusinessBuilderCore\Builder\SectionRegistry()
    );
    $pack->register_section_variants( bb_section_variants() );
}

$reg = bb_section_variants();

/* Registered variants. */
check( 'lawyers has default', in_array( 'default', $reg->available( 'lawyers' ), true ) );
check( 'lawyers has list', in_array( 'list', $reg->available( 'lawyers' ), true ) );
check( 'lawyers has featured', in_array( 'featured', $reg->available( 'lawyers' ), true ) );
check( 'legal_services has list', in_array( 'list', $reg->available( 'legal_services' ), true ) );
check( 'practice_areas has list', in_array( 'list', $reg->available( 'practice_areas' ), true ) );
check( 'testimonials has NO variants (single layout)', 1 === count( $reg->available( 'testimonials' ) ) );
check( 'faq has NO variants (single layout)', 1 === count( $reg->available( 'faq' ) ) );

/* Safe resolution. */
check( 'null → default', 'default' === bb_resolve_section_variant( 'lawyers', null ) );
check( 'empty → default', 'default' === bb_resolve_section_variant( 'lawyers', '' ) );
check( 'valid list → list', 'list' === bb_resolve_section_variant( 'lawyers', 'list' ) );
check( 'unknown → default', 'default' === bb_resolve_section_variant( 'lawyers', 'nonexistent' ) );
check( 'traversal → default', 'default' === bb_resolve_section_variant( 'lawyers', '../../evil' ) );
check( 'unknown section → default', 'default' === bb_resolve_section_variant( 'no_such', 'list' ) );

/* Template resolution is whitelist-only (never user paths). */
$t = $reg->template( 'lawyers', 'list' );
check( 'list template resolves to a real file', '' !== $t && is_readable( $t ) );
check( 'template path is inside the pack', false !== strpos( $t, 'packs' ) && false !== strpos( $t, 'variants' ) );
check( 'evil variant never becomes a path', false === strpos( (string) $reg->template( 'lawyers', '../../../x' ), '..' ) );

/* The variant setting is offered in the schema (>= 2 options). */
$options = bb_section_variant_options( 'lawyers' );
check( 'lawyers variant options offered', count( $options ) >= 2 );
check( 'testimonials offer no variant field', count( bb_section_variant_options( 'testimonials' ) ) === 0 );

/* Rendering reuses the Phase-10 components (no duplicated card markup). */
$items = array(
    array( 'name' => 'A One', 'is_public' => false, 'role' => 'Partner' ),
    array( 'name' => 'B Two', 'is_public' => false, 'role' => 'Associate' ),
);

ob_start();
$ok_default = bb_render_section_variant( 'lawyers', 'default', array( 'items' => $items, 'columns' => 3 ) );
$html_default = ob_get_clean();

ob_start();
$ok_list = bb_render_section_variant( 'lawyers', 'list', array( 'items' => $items, 'columns' => 3 ) );
$html_list = ob_get_clean();

ob_start();
$ok_featured = bb_render_section_variant( 'lawyers', 'featured', array( 'items' => $items, 'columns' => 3 ) );
$html_featured = ob_get_clean();

check( 'default variant renders', $ok_default && '' !== $html_default );
check( 'list variant renders', $ok_list && '' !== $html_list );
check( 'featured variant renders', $ok_featured && '' !== $html_featured );

check( 'default uses .bb-lawyers-grid', false !== strpos( $html_default, 'bb-lawyers-grid' ) );
check( 'default reuses .bb-lawyer-card component', false !== strpos( $html_default, 'bb-lawyer-card' ) );
check( 'list uses .bb-lawyers-list', false !== strpos( $html_list, 'bb-lawyers-list' ) );
check( 'list reuses .bb-lawyer-card component', false !== strpos( $html_list, 'bb-lawyer-card' ) );
check( 'featured uses .bb-lawyers-featured', false !== strpos( $html_featured, 'bb-lawyers-featured' ) );
check( 'featured reuses .bb-lawyer-card component', false !== strpos( $html_featured, 'bb-lawyer-card' ) );

/* Both items appear in every variant (no data loss).
 *
 * Count the ELEMENT (class="bb-lawyer-card) rather than the bare substring: a
 * legitimate card modifier (e.g. bb-lawyer-card--featured, Phase 13) makes the
 * base token appear twice in one card's class list, which would inflate a plain
 * substr_count(). The element count is the assertion's actual intent.
 */
check( 'default shows both items', 2 === substr_count( $html_default, 'class="bb-lawyer-card' ) );
check( 'list shows both items', 2 === substr_count( $html_list, 'class="bb-lawyer-card' ) );
check( 'featured shows both items', 2 === substr_count( $html_featured, 'class="bb-lawyer-card' ) );

/* Empty data is safe for every variant. */
foreach ( array( 'default', 'list', 'featured' ) as $v ) {
    ob_start();
    bb_render_section_variant( 'lawyers', $v, array( 'items' => array(), 'columns' => 3 ) );
    $empty = ob_get_clean();
    check( "empty items safe ($v)", '' !== $empty && false === stripos( $empty, 'warning' ) );
}

/* Single item safe. */
ob_start();
bb_render_section_variant( 'lawyers', 'featured', array( 'items' => array( $items[0] ), 'columns' => 3 ) );
$single = ob_get_clean();
check( 'featured with a single item is safe', 1 === substr_count( $single, 'class="bb-lawyer-card' ) );

restore_current_blog();
echo 'DONE' . PHP_EOL;