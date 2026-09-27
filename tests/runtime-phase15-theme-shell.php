<?php
/*
 * Phase 15 verification: Theme Shell Variants (Header / Navigation / Footer).
 *
 * Covers:
 *   - registry + catalog shape (3 parts, >=2 variants each, default mandatory)
 *   - safe resolver: unknown / traversal / empty / null -> default
 *   - template path is always a REGISTERED readable file (never user input)
 *   - rendering every variant (header/nav/footer), landmark + JS hooks preserved
 *   - prepared data contracts (header/footer data, no queries)
 *   - Customizer: section + 3 settings + choices derived from the catalog
 *   - theme-mod persistence + sanitize callback + reset
 *   - design-system token inheritance (variants only add structural classes)
 *   - preset independence (structural selection survives a preset switch)
 *   - JSON-ish safety: option values never reach a path
 *   - multisite isolation (site 2 selection does not leak to site 1)
 *   - existing hooks preserved (bb_theme_header_variant / footer_variant)
 *   - default output compatibility (default templates reuse the same helpers)
 */

define('WP_USE_THEMES', false);
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

function check( string $label, bool $ok ): void {
    echo ( $ok ? 'PASS' : 'FAIL' ) . ' — ' . $label . PHP_EOL;
}

$theme_dir = WP_CONTENT_DIR . '/themes/business-builder';
if ( ! defined( 'BB_THEME_PATH' ) ) { define( 'BB_THEME_PATH', trailingslashit( $theme_dir ) ); }
if ( ! defined( 'BB_THEME_URL' ) ) { define( 'BB_THEME_URL', 'http://lawfirm.builder.test/wp-content/themes/business-builder/' ); }
if ( ! defined( 'BB_THEME_VERSION' ) ) { define( 'BB_THEME_VERSION', '1.0.0' ); }
foreach ( array( 'setup', 'theme-support', 'enqueue', 'preset-resolver', 'shell-variants', 'shell-data', 'template-functions', 'template-hooks', 'navigation', 'design-schema', 'customization', 'components' ) as $f ) {
    $p = $theme_dir . '/inc/' . $f . '.php';
    if ( is_readable( $p ) ) { require_once $p; }
}

switch_to_blog( 2 );

/* ---------- 1. Clean state ---------- */
foreach ( array( 'header', 'navigation', 'footer' ) as $part ) { remove_theme_mod( 'bb_theme_shell_' . $part ); }

/* ---------- 2. Registry / catalog ---------- */
check( 'shell-variants.php loaded (function exists)', function_exists( 'bb_theme_shell_variants' ) );
check( 'parts helper exists', function_exists( 'bb_theme_shell_parts' ) );
check( 'resolver exists', function_exists( 'bb_theme_shell_resolve' ) );
check( 'template locator exists', function_exists( 'bb_theme_shell_template' ) );
check( 'renderer exists', function_exists( 'bb_theme_shell_render' ) );
check( 'options helper exists', function_exists( 'bb_theme_shell_options' ) );
check( 'sanitizer exists', function_exists( 'bb_theme_shell_sanitize' ) );

$parts = bb_theme_shell_parts();
check( 'three shell parts', count( $parts ) === 3 && isset( $parts['header'], $parts['navigation'], $parts['footer'] ) );

$catalog = bb_theme_shell_variants();
check( 'catalog has header part', ! empty( $catalog['header'] ) );
check( 'catalog has navigation part', ! empty( $catalog['navigation'] ) );
check( 'catalog has footer part', ! empty( $catalog['footer'] ) );
check( 'header offers >= 2 variants', count( $catalog['header'] ) >= 2 );
check( 'navigation offers >= 2 variants', count( $catalog['navigation'] ) >= 2 );
check( 'footer offers >= 2 variants', count( $catalog['footer'] ) >= 2 );

$all_have_default = true;
$all_templates_readable = true;
foreach ( $catalog as $part => $variants ) {
    if ( ! isset( $variants['default'] ) ) { $all_have_default = false; }
    foreach ( $variants as $slug => $meta ) {
        if ( empty( $meta['template'] ) || ! is_readable( $meta['template'] ) ) { $all_templates_readable = false; }
        if ( empty( $meta['label'] ) ) { $all_have_default = false; }
    }
}
check( 'every part has a default variant with a label', $all_have_default );
check( 'every variant template is readable', $all_templates_readable );

/* Every template lives inside the theme (no traversal outside). */
$all_inside = true;
foreach ( $catalog as $variants ) {
    foreach ( $variants as $meta ) {
        if ( 0 !== strpos( realpath( $meta['template'] ), realpath( BB_THEME_PATH ) ) ) { $all_inside = false; }
    }
}
check( 'every variant template is inside the theme dir', $all_inside );

/* ---------- 3. Resolver safety ---------- */
check( 'resolve unknown -> default', 'default' === bb_theme_shell_resolve( 'header', 'nope' ) );
check( 'resolve empty -> default', 'default' === bb_theme_shell_resolve( 'header', '' ) );
check( 'resolve null -> default', 'default' === bb_theme_shell_resolve( 'header', null ) );
check( 'resolve traversal -> default', 'default' === bb_theme_shell_resolve( 'header', '../../wp-config' ) );
check( 'resolve ../.. -> default', 'default' === bb_theme_shell_resolve( 'header', '../..' ) );
check( 'resolve script tag -> default', 'default' === bb_theme_shell_resolve( 'header', '<script>alert(1)</script>' ) );
check( 'resolve registered value kept', 'centered' === bb_theme_shell_resolve( 'header', 'centered' ) );
check( 'resolve unknown part -> default', 'default' === bb_theme_shell_resolve( 'bogus', 'centered' ) );

/* Template locator never returns a non-registered / non-readable path. */
$tpl = bb_theme_shell_template( 'header', '../../wp-config' );
check( 'traversal template resolves to a real default file', '' !== $tpl && is_readable( $tpl ) && false === strpos( $tpl, '..' ) );
check( 'invalid part yields empty template', '' === bb_theme_shell_template( 'bogus', 'default' ) );

/* Sanitizer wrapper. */
check( 'sanitize unknown -> default', 'default' === bb_theme_shell_sanitize( '../../etc', 'header' ) );
check( 'sanitize valid -> kept', 'split' === bb_theme_shell_sanitize( 'split', 'header' ) );
check( 'sanitize array -> default', 'default' === bb_theme_shell_sanitize( array( 'x' ), 'footer' ) );

/* ---------- 4. Rendering every variant ---------- */
$render_ok = true;
foreach ( $catalog as $part => $variants ) {
    foreach ( $variants as $slug => $meta ) {
        set_theme_mod( 'bb_theme_shell_' . $part, $slug );
        ob_start();
        bb_theme_shell_render( $part );
        $html = ob_get_clean();
        if ( '' === $html ) { $render_ok = false; }
        if ( 'header' === $part && false === strpos( $html, '<header id="bb-site-header"' ) ) { $render_ok = false; }
        if ( 'footer' === $part && false === strpos( $html, '<footer id="bb-site-footer"' ) ) { $render_ok = false; }
        if ( 'navigation' === $part && false === strpos( $html, 'id="bb-primary-nav"' ) ) { $render_ok = false; }
        /* JS hook preservation: the toggle + nav id must be present in a header. */
        if ( 'header' === $part && false === strpos( $html, 'class="bb-menu-toggle"' ) ) { $render_ok = false; }
    }
}
check( 'every variant renders its landmark + JS hooks', $render_ok );

/* Header variants carry a distinct structural class (except default). */
$header_classes = array();
foreach ( array_keys( $catalog['header'] ) as $slug ) {
    set_theme_mod( 'bb_theme_shell_header', $slug );
    ob_start(); bb_theme_shell_render( 'header' ); $html = ob_get_clean();
    if ( preg_match( '/<header[^>]*class="([^"]*)"/', $html, $m ) ) { $header_classes[ $slug ] = $m[1]; }
}
check( 'default header class unchanged (bb-site-header only)', 'bb-site-header' === ( $header_classes['default'] ?? '' ) );
$centered_has_modifier = false !== strpos( $header_classes['centered'] ?? '', 'bb-header--centered' );
check( 'centered header adds a structural modifier class', $centered_has_modifier );

/* Default nav output is byte-identical to the shared helper's default call. */
set_theme_mod( 'bb_theme_shell_navigation', 'default' );
ob_start(); bb_theme_shell_render( 'navigation' ); $nav_default = ob_get_clean();
ob_start(); bb_theme_navigation( 'primary', 'bb-nav' ); $nav_reference = ob_get_clean();
check( 'default nav == bb_theme_navigation( primary, bb-nav )', $nav_default === $nav_reference );

/* ---------- 5. Prepared data contracts ---------- */
$hdata = bb_theme_shell_header_data();
check( 'header data exposes site_name', isset( $hdata['site_name'] ) );
check( 'header data exposes tagline', array_key_exists( 'tagline', $hdata ) );
check( 'header data exposes home_url', isset( $hdata['home_url'] ) );
$fdata = bb_theme_shell_footer_data();
check( 'footer data exposes site_name/year/menu flag', isset( $fdata['site_name'], $fdata['year'], $fdata['has_menu'] ) );
check( 'footer menu helper exists', function_exists( 'bb_theme_shell_footer_menu' ) );
check( 'copyright helper exists', function_exists( 'bb_theme_shell_copyright' ) );
/* Data functions must not run a business query. */
check( 'no WP_Query in shell-data source', false === strpos( file_get_contents( $theme_dir . '/inc/shell-data.php' ), 'new WP_Query' ) );

/* ---------- 6. Customizer integration ---------- */
require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
require_once ABSPATH . WPINC . '/class-wp-customize-setting.php';
require_once ABSPATH . WPINC . '/class-wp-customize-control.php';
require_once ABSPATH . WPINC . '/class-wp-customize-section.php';
$wp_customize = new WP_Customize_Manager();
/*
 * Call the theme's registrar directly (Astra is active on the boot blog, so
 * firing the global customize_register action would run Astra's customizer and
 * fatal). This mirrors the Phase-14 admin test.
 */
bb_theme_customize_register( $wp_customize );
check( 'Customizer shell section registered', null !== $wp_customize->get_section( 'bb_theme_shell_structure' ) );
check( 'Customizer header setting registered', null !== $wp_customize->get_setting( 'bb_theme_shell_header' ) );
check( 'Customizer navigation setting registered', null !== $wp_customize->get_setting( 'bb_theme_shell_navigation' ) );
check( 'Customizer footer setting registered', null !== $wp_customize->get_setting( 'bb_theme_shell_footer' ) );
$header_control = $wp_customize->get_control( 'bb_theme_shell_header' );
check( 'header control is a select', $header_control && 'select' === $header_control->type );
check( 'header control choices == catalog options', $header_control && $header_control->choices === bb_theme_shell_options( 'header' ) );

/* A setting's sanitize callback enforces the whitelist. */
$setting = $wp_customize->get_setting( 'bb_theme_shell_footer' );
$cb = $setting->sanitize_callback;
check( 'footer setting sanitize keeps a valid slug', 'centered' === call_user_func( $cb, 'centered' ) );
check( 'footer setting sanitize rejects an unknown slug', 'default' === call_user_func( $cb, 'evil.php' ) );

/* ---------- 7. Persistence + reset ---------- */
set_theme_mod( 'bb_theme_shell_header', 'split' );
set_theme_mod( 'bb_theme_shell_footer', 'columns' );
check( 'header selection persists', 'split' === bb_theme_shell_part_variant( 'header' ) );
check( 'footer selection persists', 'columns' === bb_theme_shell_part_variant( 'footer' ) );
$removed = bb_theme_reset_shell_variants();
check( 'reset removed every set shell mod', $removed >= 2 );
check( 'after reset header is default', 'default' === bb_theme_shell_part_variant( 'header' ) );
check( 'after reset footer is default', 'default' === bb_theme_shell_part_variant( 'footer' ) );

/* ---------- 8. Legacy hooks preserved ---------- */
add_filter( 'bb_theme_header_variant', static function () { return 'minimal'; } );
check( 'legacy bb_theme_header_variant filter wins', 'minimal' === bb_theme_shell_part_variant( 'header' ) );
remove_all_filters( 'bb_theme_header_variant' );
add_filter( 'bb_theme_footer_variant', static function () { return 'centered'; } );
check( 'legacy bb_theme_footer_variant filter wins', 'centered' === bb_theme_shell_part_variant( 'footer' ) );
remove_all_filters( 'bb_theme_footer_variant' );
/* A legacy filter returning junk still resolves safely. */
add_filter( 'bb_theme_footer_variant', static function () { return '../../evil'; } );
check( 'legacy filter junk resolves to default', 'default' === bb_theme_shell_part_variant( 'footer' ) );
remove_all_filters( 'bb_theme_footer_variant' );

/* Uniform hook. */
add_filter( 'bb_theme_shell_variant', static function ( $v, $part ) { return 'navigation' === $part ? 'centered' : $v; }, 10, 2 );
check( 'uniform bb_theme_shell_variant filter applies per part', 'centered' === bb_theme_shell_part_variant( 'navigation' ) );
remove_all_filters( 'bb_theme_shell_variant' );

/* ---------- 9. Design-system inheritance (structure only) ---------- */
$css = file_get_contents( $theme_dir . '/assets/css/shell-variants.css' );
check( 'shell-variants.css uses only --bb-* tokens', 0 === preg_match( '/#[0-9a-fA-F]{3,6}\b/', preg_replace( '/\/\*.*?\*\//s', '', $css ) ) );
check( 'shell-variants.css has no !important', false === strpos( $css, '!important' ) );

/* ---------- 10. Preset independence ---------- */
set_theme_mod( 'bb_theme_shell_header', 'centered' );
foreach ( array( 'default', 'modern', 'luxury' ) as $preset ) {
    set_theme_mod( 'bb_theme_preset', $preset );
    check( "header stays centered under preset=$preset", 'centered' === bb_theme_shell_part_variant( 'header' ) );
}
set_theme_mod( 'bb_theme_preset', 'default' );
bb_theme_reset_shell_variants();

restore_current_blog();

/* ---------- 11. Multisite isolation ---------- */
switch_to_blog( 2 );
set_theme_mod( 'bb_theme_shell_header', 'split' );
$site2 = bb_theme_shell_part_variant( 'header' );
restore_current_blog();
switch_to_blog( 1 );
$site1_has_fn = function_exists( 'bb_theme_shell_part_variant' );
$site1 = $site1_has_fn ? bb_theme_shell_part_variant( 'header' ) : 'default';
restore_current_blog();
check( 'site 2 selection is split', 'split' === $site2 );
check( 'site 1 does not inherit site 2 selection', 'split' !== $site1 );

/* Clean up. */
switch_to_blog( 2 );
bb_theme_reset_shell_variants();
restore_current_blog();

echo 'DONE' . PHP_EOL;