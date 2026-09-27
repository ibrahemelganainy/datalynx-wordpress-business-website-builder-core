<?php
/*
 * Phase 14 verification: theme design system & global customization.
 *  - design schema loads and is well-formed
 *  - overrides fill from theme mods (only when set)
 *  - cascade: defaults -> preset -> overrides -> :root CSS
 *  - sanitization per type; invalid values rejected
 *  - individual + global reset (delete mod == inherit preset)
 *  - preset switching keeps overrides
 *  - unknown keys / arbitrary CSS rejected
 *  - multisite isolation
 */
define( 'WP_USE_THEMES', false );
define( 'WP_ADMIN', true );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

function check( string $label, bool $ok ): void {
    echo ( $ok ? 'PASS' : 'FAIL' ) . ' — ' . $label . PHP_EOL;
}

$theme_dir = WP_CONTENT_DIR . '/themes/business-builder';
require_once $theme_dir . '/inc/preset-resolver.php';
require_once $theme_dir . '/inc/design-schema.php';
require_once $theme_dir . '/inc/customization.php';

switch_to_blog( 2 );

/* --- Schema --- */
$schema = bb_theme_design_schema();
check( 'design schema is non-empty', count( $schema ) >= 20 );
check( 'schema has color controls', ! empty( bb_theme_design_schema_by_group()['colors'] ) );
check( 'schema has typography controls', ! empty( bb_theme_design_schema_by_group()['typography'] ) );
check( 'schema has layout controls', ! empty( bb_theme_design_schema_by_group()['layout'] ) );
check( 'schema has radius controls', ! empty( bb_theme_design_schema_by_group()['radius'] ) );
check( 'schema has shell controls', ! empty( bb_theme_design_schema_by_group()['shell'] ) );
$all_tokens_bb = true;
foreach ( $schema as $c ) { if ( 0 !== strpos( (string) $c['token'], '--bb-' ) ) { $all_tokens_bb = false; } }
check( 'every control targets a --bb-* token', $all_tokens_bb );

/* --- Default state: no overrides, no customization --- */
foreach ( $schema as $c ) { remove_theme_mod( bb_theme_design_mod_name( (string) $c['key'] ) ); }
set_theme_mod( 'bb_theme_preset', 'default' );
$overrides = bb_theme_customization_overrides();
check( 'no mods -> no overrides', empty( $overrides ) );

$css = bb_theme_preset_css();
check( 'default preset emits :root', 0 === strpos( $css, ':root{' ) );
check( 'default preset primary = #2563eb', false !== strpos( $css, '--bb-color-primary:#2563eb' ) );

/* --- Single override --- */
set_theme_mod( 'bb_design_color_primary', '#ef4444' );
$overrides = bb_theme_customization_overrides();
check( 'single override present', isset( $overrides['--bb-color-primary'] ) && '#ef4444' === $overrides['--bb-color-primary'] );
$css = bb_theme_preset_css();
check( 'override wins over preset in CSS', false !== strpos( $css, '--bb-color-primary:#ef4444' ) );

/* --- Multiple overrides --- */
set_theme_mod( 'bb_design_container_width', '1360px' );
set_theme_mod( 'bb_design_radius_lg', '20px' );
set_theme_mod( 'bb_design_font_body', 'serif' );
$overrides = bb_theme_customization_overrides();
check( 'multiple overrides collected', count( $overrides ) >= 4 );
check( 'font override resolves to a CSS stack', false !== strpos( $overrides['--bb-font-body'] ?? '', 'Georgia' ) );
$css = bb_theme_preset_css();
check( 'container width override in CSS', false !== strpos( $css, '--bb-container-width:1360px' ) );
check( 'radius override in CSS', false !== strpos( $css, '--bb-radius-lg:20px' ) );

/* --- Sanitization --- */
$ctrl_color = array( 'type' => 'color' );
check( 'color: valid hex accepted', '#abcdef' === bb_theme_sanitize_design_value( '#abcdef', $ctrl_color ) );
check( 'color: css injection rejected', '' === bb_theme_sanitize_design_value( 'red; } body{display:none', $ctrl_color ) );
check( 'color: named color rejected', '' === bb_theme_sanitize_design_value( 'red', $ctrl_color ) );
check( 'color: rgba accepted', 'rgba(0,0,0,0.5)' === bb_theme_sanitize_design_value( 'rgba(0,0,0,0.5)', $ctrl_color ) );

$ctrl_len = array( 'type' => 'length', 'min' => 900, 'max' => 1600, 'unit' => 'px' );
check( 'length: in-range accepted', '1200px' === bb_theme_sanitize_design_value( '1200px', $ctrl_len ) );
check( 'length: below-min rejected', '' === bb_theme_sanitize_design_value( '100px', $ctrl_len ) );
check( 'length: above-max rejected', '' === bb_theme_sanitize_design_value( '9999px', $ctrl_len ) );
check( 'length: unit-less gets default unit', '1200px' === bb_theme_sanitize_design_value( '1200', $ctrl_len ) );
check( 'length: injection rejected', '' === bb_theme_sanitize_design_value( '1200px;}', $ctrl_len ) );

$ctrl_sel = array( 'type' => 'select', 'options' => array( '600' => '600', '700' => '700' ) );
check( 'select: whitelisted accepted', '700' === bb_theme_sanitize_design_value( '700', $ctrl_sel ) );
check( 'select: non-whitelisted rejected', '' === bb_theme_sanitize_design_value( '999', $ctrl_sel ) );

$ctrl_font = array( 'type' => 'font' );
check( 'font: whitelisted accepted', 'serif' === bb_theme_sanitize_design_value( 'serif', $ctrl_font ) );
check( 'font: unknown rejected', '' === bb_theme_sanitize_design_value( 'Comic Sans', $ctrl_font ) );

/* --- Invalid mod falls back to preset (not stored in output) --- */
set_theme_mod( 'bb_design_color_primary', 'not-a-color' );
$overrides = bb_theme_customization_overrides();
check( 'invalid mod yields no override', ! isset( $overrides['--bb-color-primary'] ) );
$css = bb_theme_preset_css();
check( 'invalid mod -> preset value used', false !== strpos( $css, '--bb-color-primary:#2563eb' ) );

/* --- Individual reset = delete mod = inherit preset --- */
set_theme_mod( 'bb_design_color_primary', '#ef4444' );
remove_theme_mod( 'bb_design_color_primary' );
$overrides = bb_theme_customization_overrides();
check( 'individual reset removes the override', ! isset( $overrides['--bb-color-primary'] ) );

/* --- Preset switching keeps overrides (overrides independent of preset) --- */
set_theme_mod( 'bb_design_color_primary', '#ef4444' );
foreach ( array( 'default', 'modern', 'luxury' ) as $p ) {
    set_theme_mod( 'bb_theme_preset', $p );
    $o = bb_theme_customization_overrides();
    check( "override survives preset=$p", ( $o['--bb-color-primary'] ?? '' ) === '#ef4444' );
}

/* --- Global reset --- */
set_theme_mod( 'bb_design_container_width', '1360px' );
set_theme_mod( 'bb_design_radius_lg', '20px' );
$removed = bb_theme_reset_all_overrides();
check( 'reset all removed >= 3 mods', $removed >= 3 );
check( 'reset all left no overrides', empty( bb_theme_customization_overrides() ) );
check( 'reset all kept the preset mod', in_array( get_theme_mod( 'bb_theme_preset' ), array( 'default', 'modern', 'luxury' ), true ) );

/* --- Arbitrary CSS injection can never reach output --- */
set_theme_mod( 'bb_design_color_primary', 'red; }html{background:black' );
$css = bb_theme_preset_css();
check( 'no injected selector in output', false === strpos( $css, 'html{' ) );
check( 'output is a single :root block', 1 === substr_count( $css, ':root{' ) );

/* --- Unknown customization key is ignored (filter adds a bogus token) --- */
add_filter( 'bb_theme_customization_overrides', function ( $o ) {
    $o['--bb-not-a-real-token'] = 'x; }';
    return $o;
} );
$css = bb_theme_preset_css();
check( 'unknown token value cannot break the block', 1 === substr_count( $css, ':root{' ) && false === strpos( $css, '}' . "\n" . '}' ) );

/* Restore a clean state for this site. */
bb_theme_reset_all_overrides();
set_theme_mod( 'bb_theme_preset', 'default' );

restore_current_blog();

/* --- Multisite isolation --- */
switch_to_blog( 2 );
set_theme_mod( 'bb_design_color_primary', '#111111' );
$css_a = bb_theme_preset_css();
restore_current_blog();

switch_to_blog( 1 );
$css_b = bb_theme_preset_css();
restore_current_blog();

check( 'site 2 override not leaked into site 1', false === strpos( $css_b, '#111111' ) );

/* Clean up site 2. */
switch_to_blog( 2 );
remove_theme_mod( 'bb_design_color_primary' );
restore_current_blog();

echo 'DONE' . PHP_EOL;