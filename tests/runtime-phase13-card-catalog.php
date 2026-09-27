<?php
/*
 * Phase 13 verification: component variant catalog & selection UX.
 *  - the catalog is exposed (labels + descriptions) and whitelisted
 *  - every declared design resolves to a real template
 *  - every design renders and consumes the SAME prepared args
 *  - default parity preserved (null/''/unknown/'default')
 *  - section x component orthogonality holds
 *  - resolver reused (not rebuilt); traversal safe
 */
define( 'WP_USE_THEMES', false );
define( 'WP_ADMIN', true );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

function check( string $label, bool $ok ): void {
    echo ( $ok ? 'PASS' : 'FAIL' ) . ' — ' . $label . PHP_EOL;
}

switch_to_blog( 2 );

if ( ! defined( 'BB_THEME_PATH' ) ) {
    define( 'BB_THEME_PATH', trailingslashit( WP_CONTENT_DIR . '/themes/business-builder' ) );
}
if ( ! function_exists( 'bb_component' ) ) {
    require_once WP_CONTENT_DIR . '/themes/business-builder/inc/components.php';
}
add_filter( 'bb_component_roots', function ( $roots ) {
    $roots   = is_array( $roots ) ? $roots : array();
    $roots[] = WP_CONTENT_DIR . '/plugins/business-builder-core/packs/LawFirm/Sections/components';
    return $roots;
} );

/* Catalog (from the pack). */
if ( ! class_exists( 'BusinessBuilderCore\\Packs\\LawFirm\\Sections\\LawFirmSections' ) ) {
    echo "SKIP: pack class unavailable\n";
    exit;
}
$pack = new \BusinessBuilderCore\Packs\LawFirm\Sections\LawFirmSections(
    new \BusinessBuilderCore\Builder\SectionRegistry()
);
$catalog = $pack->card_design_catalog();

$expected = array( 'default', 'compact', 'featured', 'minimal', 'horizontal' );

foreach ( array( 'lawyer/card', 'service/card', 'practice-area/card' ) as $component ) {
    check( "$component has a catalog entry", isset( $catalog[ $component ] ) );
    foreach ( $expected as $v ) {
        check( "$component catalog declares '$v'", isset( $catalog[ $component ][ $v ] ) );
        check( "$component '$v' has a label", ! empty( $catalog[ $component ][ $v ]['label'] ) );
        check( "$component '$v' has a description", ! empty( $catalog[ $component ][ $v ]['description'] ) );
        /* Every declared design must resolve to a real template. */
        check( "$component '$v' resolves to a template", 'default' === $v || '' !== bb_component_path( $component, $v ) );
    }
}

/* Testimonial/FAQ intentionally have no variant FILES (resolver falls back to
 * the single base template, which is correct). */
check( 'testimonial/card has no compact file', bb_component_path( 'testimonial/card', 'compact' ) === bb_component_path( 'testimonial/card' ) );
check( 'faq/item has no compact file', bb_component_path( 'faq/item', 'compact' ) === bb_component_path( 'faq/item' ) );
check( 'testimonial resolves only to item base', false !== strpos( (string) bb_component_path( 'testimonial/card' ), 'testimonial' ) );
check( 'faq resolves only to item base', false !== strpos( (string) bb_component_path( 'faq/item' ), 'faq' ) );

/* Resolver unchanged + safe. */
check( 'unknown variant resolves to base file', bb_component_path( 'lawyer/card', 'nope' ) === bb_component_path( 'lawyer/card' ) );
check( 'traversal variant never escapes', false === strpos( (string) bb_component_path( 'lawyer/card', '../../evil' ), '..' ) );

/* Same prepared args across ALL lawyer designs. */
$args = array(
    'name' => 'Jane Doe', 'profile_url' => 'https://example.com/jane', 'is_public' => true,
    'role' => 'Partner', 'experience' => '12', 'phone' => '0100', 'email' => 'j@e.com', 'profile_link' => '',
);

$base = bb_render_component( 'lawyer/card', $args );
check( 'default parity: null == base', $base === bb_render_component( 'lawyer/card', $args, null ) );
check( 'default parity: "" == base', $base === bb_render_component( 'lawyer/card', $args, '' ) );
check( 'default parity: unknown == base', $base === bb_render_component( 'lawyer/card', $args, 'zzz' ) );
check( 'default parity: "default" == base', $base === bb_render_component( 'lawyer/card', $args, 'default' ) );

$rendered = array();
foreach ( array( 'compact', 'featured', 'minimal', 'horizontal' ) as $v ) {
    $html = bb_render_component( 'lawyer/card', $args, $v );
    $rendered[ $v ] = $html;
    check( "lawyer '$v' renders", '' !== $html );
    check( "lawyer '$v' keeps base class bb-lawyer-card", false !== strpos( $html, 'bb-lawyer-card' ) );
    check( "lawyer '$v' carries modifier --$v", false !== strpos( $html, 'bb-lawyer-card--' . $v ) );
    check( "lawyer '$v' uses the same name data", false !== strpos( $html, 'Jane Doe' ) );
    check( "lawyer '$v' produces no db/query artifact", false === strpos( $html, 'WP_Query' ) );
    check( "lawyer '$v' differs from default presentation", $html !== $base );
}

/* Distinct designs really differ from each other. */
check( 'featured differs from compact', $rendered['featured'] !== $rendered['compact'] );
check( 'minimal differs from horizontal', $rendered['minimal'] !== $rendered['horizontal'] );

/* All designs keep heading hierarchy (one h3 per card) and links. */
foreach ( $rendered as $v => $html ) {
    check( "lawyer '$v' keeps a single h3", 1 === substr_count( $html, '<h3' ) );
    check( "lawyer '$v' keeps the profile link/link semantics", false !== strpos( $html, '<a ' ) );
}

/* Service + practice-area designs render. */
foreach ( array( 'compact', 'featured', 'minimal', 'horizontal' ) as $v ) {
    $s = bb_render_component( 'service/card', array( 'title' => 'Contracts', 'summary' => 'We draft', 'icon' => 'X' ), $v );
    check( "service '$v' renders + keeps base class", false !== strpos( $s, 'bb-service-card' ) && false !== strpos( $s, 'bb-service-card--' . $v ) );

    $p = bb_render_component( 'practice-area/card', array( 'title' => 'Family', 'summary' => 'Family law' ), $v );
    check( "practice-area '$v' renders + keeps base class", false !== strpos( $p, 'bb-practice-area-card' ) && false !== strpos( $p, 'bb-practice-area-card--' . $v ) );
}

/* Section schema exposes the richer catalog with descriptions. */
$registry = new \BusinessBuilderCore\Builder\SectionRegistry();
if ( ! function_exists( 'bb_section_variants' ) ) {
    require_once WP_CONTENT_DIR . '/plugins/business-builder-core/includes/Builder/SectionVariants.php';
    require_once WP_CONTENT_DIR . '/plugins/business-builder-core/includes/Builder/section-variants.php';
}
$secs = new \BusinessBuilderCore\Packs\LawFirm\Sections\LawFirmSections( $registry );
$secs->register_section_variants( bb_section_variants() );
$secs->register();

$law = $registry->get( 'lawyers' );
check( 'lawyers card_variant has 5 options', 5 === count( $law['settings']['card_variant']['options'] ?? array() ) );
check( 'lawyers card_variant exposes descriptions', ! empty( $law['settings']['card_variant']['option_titles'] ) );
check( 'card_variant label is "Card design"', 'Card design' === ( $law['settings']['card_variant']['label'] ?? '' ) );
check( 'section variant still present (layout)', isset( $law['settings']['variant'] ) );

/* Orthogonality: section x component compose. */
if ( class_exists( 'BusinessBuilderCore\\Builder\\SectionVariants' ) ) {
    $secs->register_section_variants( bb_section_variants() );
}
$items = array( array( 'name' => 'A', 'is_public' => false ), array( 'name' => 'B', 'is_public' => false ) );

function m13( string $sec, string $card, array $items ): string {
    ob_start();
    bb_render_section_variant( 'lawyers', $sec, array( 'items' => $items, 'columns' => 3, 'component_variant' => $card ) );
    return ob_get_clean();
}

$m1 = m13( 'default', 'featured', $items );
check( 'default section + featured card', false !== strpos( $m1, 'bb-lawyers-grid' ) && false !== strpos( $m1, 'bb-lawyer-card--featured' ) );
$m2 = m13( 'list', 'horizontal', $items );
check( 'list section + horizontal card', false !== strpos( $m2, 'bb-lawyers-list' ) && false !== strpos( $m2, 'bb-lawyer-card--horizontal' ) );
$m3 = m13( 'list', 'featured', $items );
check( 'card variant does NOT change the section wrapper', false !== strpos( $m3, 'bb-lawyers-list' ) && false === strpos( $m3, 'bb-lawyers-grid' ) );

/* Empty items safe for every design. */
foreach ( array( 'default', 'compact', 'featured', 'minimal', 'horizontal' ) as $v ) {
    $empty = bb_render_component( 'lawyer/card', array( 'name' => '' ), $v );
    check( "empty args safe ($v)", false === stripos( $empty, 'warning' ) && false === stripos( $empty, 'fatal' ) );
}

restore_current_blog();
echo 'DONE' . PHP_EOL;