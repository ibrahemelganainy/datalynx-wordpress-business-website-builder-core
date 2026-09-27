<?php
/*
 * Phase 12 verification: component (card) variants.
 *  - the existing Phase-10 resolver already supports variants (reused, not rebuilt)
 *  - compact variants resolve + render for the 3 card components
 *  - the SAME prepared args reach default and compact (argument integrity)
 *  - default parity: no variant == 'default' variant == base output
 *  - orthogonality: section variant x component variant compose
 *  - component hooks fire for variants too
 */
define( 'WP_USE_THEMES', false );
define( 'WP_ADMIN', true );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

function check( string $label, bool $ok ): void {
    echo ( $ok ? 'PASS' : 'FAIL' ) . ' — ' . $label . PHP_EOL;
}

switch_to_blog( 2 );

/* Theme API (components) — provide the minimal constants, then load the file. */
if ( ! defined( 'BB_THEME_PATH' ) ) {
    define( 'BB_THEME_PATH', trailingslashit( WP_CONTENT_DIR . '/themes/business-builder' ) );
}
if ( ! defined( 'BB_THEME_URL' ) ) {
    define( 'BB_THEME_URL', trailingslashit( WP_CONTENT_URL . '/themes/business-builder' ) );
}
if ( ! function_exists( 'bb_component' ) ) {
    require_once WP_CONTENT_DIR . '/themes/business-builder/inc/components.php';
}
/* Pack component root (the pack registers this at init in a real request). */
add_filter( 'bb_component_roots', function ( $roots ) {
    $roots   = is_array( $roots ) ? $roots : array();
    $roots[] = WP_CONTENT_DIR . '/plugins/business-builder-core/packs/LawFirm/Sections/components';
    return $roots;
} );

check( 'bb_component() exists', function_exists( 'bb_component' ) );
check( 'bb_component_path() exists', function_exists( 'bb_component_path' ) );

/* ---- 1. Resolver already supports variants (Phase 10, reused). ---- */
check( 'lawyer/card resolves (base)', '' !== bb_component_path( 'lawyer/card' ) );
check( 'lawyer/card compact resolves', '' !== bb_component_path( 'lawyer/card', 'compact' ) );
check( 'service/card compact resolves', '' !== bb_component_path( 'service/card', 'compact' ) );
check( 'practice-area/card compact resolves', '' !== bb_component_path( 'practice-area/card', 'compact' ) );
check( 'unknown variant falls back to base file', bb_component_path( 'lawyer/card', 'nope' ) === bb_component_path( 'lawyer/card' ) );
check( 'path traversal variant never escapes', false === strpos( (string) bb_component_path( 'lawyer/card', '../../evil' ), '..' ) );

/* ---- 2. Argument integrity: same prepared args for default + compact. ---- */
$lawyer_args = array(
    'name' => 'Jane Doe', 'profile_url' => 'https://example.com/jane', 'is_public' => true,
    'role' => 'Partner', 'experience' => '12', 'phone' => '0100', 'email' => 'j@e.com', 'profile_link' => '',
);

$l_default = bb_render_component( 'lawyer/card', $lawyer_args );
$l_compact = bb_render_component( 'lawyer/card', $lawyer_args, 'compact' );
$l_null    = bb_render_component( 'lawyer/card', $lawyer_args, null );
$l_empty   = bb_render_component( 'lawyer/card', $lawyer_args, '' );
$l_unknown = bb_render_component( 'lawyer/card', $lawyer_args, 'does-not-exist' );

/* ---- 3. Default parity. ---- */
check( 'default (no variant) == base output', $l_default === $l_null );
check( 'empty variant == base output', $l_default === $l_empty );
check( 'unknown variant == base output', $l_default === $l_unknown );
check( 'default card keeps .bb-lawyer-card (no modifier)', false === strpos( $l_default, '--compact' ) );

/* ---- 4. Compact renders and is presentation-only. ---- */
check( 'compact lawyer renders .bb-lawyer-card--compact', false !== strpos( $l_compact, 'bb-lawyer-card--compact' ) );
check( 'compact keeps base class .bb-lawyer-card', false !== strpos( $l_compact, 'class="bb-lawyer-card bb-lawyer-card--compact"' ) );
check( 'compact keeps the same name/data', false !== strpos( $l_compact, 'Jane Doe' ) );
check( 'compact keeps the same profile link', false !== strpos( $l_compact, 'https://example.com/jane' ) );
check( 'compact keeps tel: link', false !== strpos( $l_compact, 'href="tel:0100"' ) );
check( 'compact differs from default (presentation)', $l_compact !== $l_default );
check( 'both contain 1 <h3> (same semantics)', substr_count( $l_default, '<h3' ) === substr_count( $l_compact, '<h3' ) );
check( 'neither contains a query/db call artifact', false === strpos( $l_compact, 'WP_Query' ) );

/* ---- 5. Compact for service + practice area. ---- */
$s_compact = bb_render_component( 'service/card', array( 'title' => 'Contracts', 'summary' => 'We draft', 'icon' => 'X' ), 'compact' );
check( 'service compact renders + keeps base class', false !== strpos( $s_compact, 'bb-service-card bb-service-card--compact' ) );
check( 'service compact keeps the title', false !== strpos( $s_compact, 'Contracts' ) );

$p_compact = bb_render_component( 'practice-area/card', array( 'title' => 'Family', 'summary' => 'Family law' ), 'compact' );
check( 'practice-area compact renders + keeps base class', false !== strpos( $p_compact, 'bb-practice-area-card bb-practice-area-card--compact' ) );
check( 'practice-area compact keeps the title', false !== strpos( $p_compact, 'Family' ) );

/* ---- 6. Component hooks fire for variants too (resolver-owned). ---- */
$hook_hits = array();
$spy = function () use ( &$hook_hits ) { $hook_hits[] = 1; };
add_action( 'bb_before_component', $spy );
add_action( 'bb_after_component', $spy );
bb_render_component( 'lawyer/card', $lawyer_args, 'compact' );
remove_action( 'bb_before_component', $spy );
remove_action( 'bb_after_component', $spy );
check( 'bb_before/after_component fire for a variant', count( $hook_hits ) >= 2 );

/* ---- 7. Orthogonality: section variant x component variant. ---- */
if ( ! function_exists( 'bb_section_variants' ) ) {
    require_once WP_CONTENT_DIR . '/plugins/business-builder-core/includes/Builder/SectionVariants.php';
    require_once WP_CONTENT_DIR . '/plugins/business-builder-core/includes/Builder/section-variants.php';
}
if ( class_exists( 'BusinessBuilderCore\\Packs\\LawFirm\\Sections\\LawFirmSections' ) ) {
    $pack = new \BusinessBuilderCore\Packs\LawFirm\Sections\LawFirmSections(
        new \BusinessBuilderCore\Builder\SectionRegistry()
    );
    $pack->register_section_variants( bb_section_variants() );
}

$items = array(
    array( 'name' => 'A', 'is_public' => false ),
    array( 'name' => 'B', 'is_public' => false ),
    array( 'name' => 'C', 'is_public' => false ),
);

function render_matrix( string $sec_variant, string $card_variant, array $items ): string {
    ob_start();
    bb_render_section_variant(
        'lawyers',
        $sec_variant,
        array( 'items' => $items, 'columns' => 3, 'component_variant' => $card_variant )
    );
    return ob_get_clean();
}

$m_dd = render_matrix( 'default', 'default', $items );
$m_dc = render_matrix( 'default', 'compact', $items );
$m_ld = render_matrix( 'list', 'default', $items );
$m_lc = render_matrix( 'list', 'compact', $items );
$m_fc = render_matrix( 'featured', 'compact', $items );

check( 'default/default → grid + default card', false !== strpos( $m_dd, 'bb-lawyers-grid' ) && false === strpos( $m_dd, '--compact' ) );
check( 'default/compact → grid + compact card', false !== strpos( $m_dc, 'bb-lawyers-grid' ) && false !== strpos( $m_dc, 'bb-lawyer-card--compact' ) );
check( 'list/default → list + default card', false !== strpos( $m_ld, 'bb-lawyers-list' ) && false === strpos( $m_ld, '--compact' ) );
check( 'list/compact → list + compact card', false !== strpos( $m_lc, 'bb-lawyers-list' ) && false !== strpos( $m_lc, 'bb-lawyer-card--compact' ) );
check( 'featured/compact → featured + compact cards in the rest grid', false !== strpos( $m_fc, 'bb-lawyers-featured' ) && false !== strpos( $m_fc, 'bb-lawyer-card--compact' ) );

/* Section layout and card design are independent: changing card variant does
 * NOT change the section wrapper. */
check( 'card variant does not change list wrapper', strpos( $m_ld, 'bb-lawyers-list' ) !== false && strpos( $m_lc, 'bb-lawyers-list' ) !== false );
check( 'all items preserved with a card variant', 3 === substr_count( $m_lc, 'bb-lawyer-card--compact' ) );

/* ---- 8. Setting is exposed + whitelisted in the section schema. ---- */
$registry = new \BusinessBuilderCore\Builder\SectionRegistry();
$secs = new \BusinessBuilderCore\Packs\LawFirm\Sections\LawFirmSections( $registry );
$secs->register_section_variants( bb_section_variants() );
$secs->register();

$law = $registry->get( 'lawyers' );
check( 'lawyers schema exposes card_variant', isset( $law['settings']['card_variant'] ) );
check( 'card_variant is a select', 'select' === ( $law['settings']['card_variant']['type'] ?? '' ) );
check( 'card_variant options include compact', isset( $law['settings']['card_variant']['options']['compact'] ) );
check( 'card_variant default is default', 'default' === ( $law['settings']['card_variant']['default'] ?? '' ) );
check( 'lawyers still keeps the section variant setting', isset( $law['settings']['variant'] ) );
check( 'testimonials have no card_variant (single unit)', ! isset( $registry->get( 'testimonials' )['settings']['card_variant'] ) );
check( 'faq has no card_variant (single unit)', ! isset( $registry->get( 'faq' )['settings']['card_variant'] ) );

restore_current_blog();
echo 'DONE' . PHP_EOL;