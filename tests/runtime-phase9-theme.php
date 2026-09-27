<?php
/*
 * Phase 9 verification: activate the Business Builder theme on a test site and
 * prove it loads cleanly and exposes its API.
 */
define( 'WP_USE_THEMES', false );
define( 'WP_ADMIN', true );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

function check( string $label, bool $ok ): void {
    echo ( $ok ? 'PASS' : 'FAIL' ) . ' — ' . $label . PHP_EOL;
}

$blog_id = 2;
switch_to_blog( $blog_id );

$original = get_option( 'stylesheet' );

$theme = wp_get_theme( 'business-builder' );
check( 'theme discovered by WordPress', $theme->exists() );
check( 'theme name is "Business Builder"', 'Business Builder' === $theme->get( 'Name' ) );
check( 'theme text domain is business-builder-theme', 'business-builder-theme' === $theme->get( 'TextDomain' ) );

/* Activate it for this site (restored at the end). */
switch_theme( 'business-builder' );
check( 'theme activates without error', 'business-builder' === get_option( 'stylesheet' ) );

$root = get_template_directory();
check( 'template directory is the BB theme', 'business-builder' === basename( $root ) );

if ( ! function_exists( 'bb_theme_get_preset' ) ) {
    require_once $root . '/functions.php';
}

check( 'bb_theme_get_preset() exists', function_exists( 'bb_theme_get_preset' ) );
check( 'bb_theme_preset_config() exists', function_exists( 'bb_theme_preset_config' ) );
check( 'bb_theme_preset_css() exists', function_exists( 'bb_theme_preset_css' ) );
check( 'bb_theme_navigation() exists', function_exists( 'bb_theme_navigation' ) );
check( 'bb_theme_site_identity() exists', function_exists( 'bb_theme_site_identity' ) );
check( 'bb_theme_body_classes() exists', function_exists( 'bb_theme_body_classes' ) );

$presets = bb_theme_presets();
check( 'default preset exists', isset( $presets['default'] ) );
check( 'modern preset exists', isset( $presets['modern'] ) );
check( 'luxury preset exists', isset( $presets['luxury'] ) );

check( 'resolved preset is valid', in_array( bb_theme_get_preset(), array( 'default', 'modern', 'luxury' ), true ) );

$css = bb_theme_preset_css();
check( 'preset CSS emitted on :root', false !== strpos( $css, ':root{' ) );
check( 'preset CSS carries --bb-color-primary', false !== strpos( $css, '--bb-color-primary:' ) );
check( 'preset CSS carries --bb-color-background', false !== strpos( $css, '--bb-color-background:' ) );

add_filter( 'bb_theme_active_preset', function () { return 'nope'; } );
check( 'invalid preset falls back to default', 'default' === bb_theme_get_preset() );

$classes = bb_theme_body_classes( array() );
check( 'body classes include bb-theme', in_array( 'bb-theme', $classes, true ) );
check( 'body classes include a preset class', (bool) preg_grep( '/^bb-theme-preset-/', $classes ) );
check( 'body classes include a direction class', (bool) preg_grep( '/^bb-(rtl|ltr)$/', $classes ) );

$required = array(
    'style.css', 'functions.php', 'index.php', 'header.php', 'footer.php',
    'page.php', 'single.php', '404.php', 'front-page.php', 'archive.php',
    'inc/setup.php', 'inc/enqueue.php', 'inc/theme-support.php',
    'inc/template-functions.php', 'inc/template-hooks.php', 'inc/navigation.php',
    'inc/customization.php', 'inc/preset-resolver.php',
    'assets/css/tokens.css', 'assets/css/typography.css', 'assets/css/layout.css',
    'assets/css/components.css', 'assets/css/header.css', 'assets/css/footer.css',
    'assets/css/responsive.css', 'assets/css/theme.css', 'assets/js/theme.js',
    'template-parts/header/header-default.php',
    'template-parts/footer/footer-default.php',
    'template-parts/navigation/primary.php',
);
$missing = array();
foreach ( $required as $rel ) {
    if ( ! is_readable( $root . '/' . $rel ) ) { $missing[] = $rel; }
}
check( 'all required theme files exist', empty( $missing ) );
if ( ! empty( $missing ) ) { echo '   missing: ' . implode( ', ', $missing ) . PHP_EOL; }

switch_theme( $original );
check( 'original theme restored', $original === get_option( 'stylesheet' ) );

restore_current_blog();
echo 'DONE' . PHP_EOL;