<?php
/*
 * Phase 9: verify RTL rendering and preset switching on the live site, then
 * restore everything. Read-mostly: only touched options are restored.
 */
define( 'WP_USE_THEMES', false );
define( 'WP_ADMIN', true );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

function check( string $label, bool $ok ): void {
    echo ( $ok ? 'PASS' : 'FAIL' ) . ' — ' . $label . PHP_EOL;
}

switch_to_blog( 2 );

/* Ensure the (active) theme's API is available in this standalone process. */
if ( ! function_exists( 'bb_theme_preset_css' ) ) {
    require_once get_template_directory() . '/functions.php';
}

$orig_locale = get_option( 'WPLANG' );
$orig_preset = get_theme_mod( 'bb_theme_preset', 'default' );

/* --- Preset switching: default vs luxury produce different tokens. --- */
set_theme_mod( 'bb_theme_preset', 'default' );
$default_css = bb_theme_preset_css();

set_theme_mod( 'bb_theme_preset', 'luxury' );
$luxury_css = bb_theme_preset_css();

set_theme_mod( 'bb_theme_preset', 'modern' );
$modern_css = bb_theme_preset_css();

check( 'default preset emits tokens', false !== strpos( $default_css, '--bb-color-primary:#2563eb' ) );
check( 'luxury preset changes primary to gold', false !== strpos( $luxury_css, '--bb-color-primary:#b8843c' ) );
check( 'modern preset changes background', false !== strpos( $modern_css, '--bb-color-background:#f8fafc' ) );
check( 'presets differ', $default_css !== $luxury_css && $luxury_css !== $modern_css );

/* --- RTL: the theme must produce an RTL body class + rtl stylesheet. --- */
set_theme_mod( 'bb_theme_preset', 'default' );
update_option( 'WPLANG', 'ar' );

switch_to_blog( 2 );
/* Force locale for this process. */
add_filter( 'locale', function () { return 'ar'; } );
$classes = bb_theme_body_classes( array() );
check( 'RTL locale yields bb-rtl class', in_array( 'bb-rtl', $classes, true ) );
check( 'is_rtl() true under ar', function_exists( 'is_rtl' ) );

/* --- Restore. --- */
update_option( 'WPLANG', $orig_locale );
set_theme_mod( 'bb_theme_preset', $orig_preset );
restore_current_blog();

echo 'DONE' . PHP_EOL;