<?php
/*
 * Phase 9: render real frontend requests with the Business Builder theme
 * active, using WordPress' own template loader, and assert the shell appears.
 */
define( 'WP_USE_THEMES', true );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

function check( string $label, bool $ok ): void {
    echo ( $ok ? 'PASS' : 'FAIL' ) . ' — ' . $label . PHP_EOL;
}

/**
 * Render a front-end URL through WordPress' template loader.
 */
function bb_render_path( string $path ): string {
    global $wp, $wp_query, $wp_the_query;

    $wp        = new WP();
    $wp_the_query = new WP_Query();
    $wp_query  = $wp_the_query;

    $wp->init();
    $wp->parse_request( ltrim( $path, '/' ) );
    $wp->query_posts();
    $wp_query = $wp->query_vars ? $wp->query_posts() : $wp_the_query;

    $template = '';
    if ( is_404() ) {
        $template = get_404_template();
    } elseif ( is_page() ) {
        $template = get_page_template();
    } elseif ( is_singular() ) {
        $template = get_single_template();
    }
    if ( '' === $template || ! $template ) {
        $template = get_index_template();
    }

    ob_start();
    if ( $template && is_readable( $template ) ) {
        include $template;
    }
    return ob_get_clean();
}

$blog_id = 2;
switch_to_blog( $blog_id );
$original = get_option( 'stylesheet' );
switch_theme( 'business-builder' );

/* Home. */
$home = bb_render_path( '/' );
check( 'home renders (non-empty)', strlen( $home ) > 0 );
check( 'home has doctype', false !== stripos( $home, '<!DOCTYPE html>' ) );
check( 'home has bb-theme body class', false !== stripos( $home, 'bb-theme' ) );
check( 'home has theme header', false !== strpos( $home, 'bb-site-header' ) );
check( 'home has theme footer', false !== strpos( $home, 'bb-site-footer' ) );
check( 'home has skip link', false !== strpos( $home, 'bb-skip-link' ) );
check( 'home has main landmark', false !== strpos( $home, 'id="bb-main"' ) );
check( 'home enqueues theme tokens', false !== strpos( $home, 'bb-theme-tokens' ) );
check( 'home has NO fatal error', false === stripos( $home, 'Fatal error' ) );

/* A builder page. */
$page = get_page_by_path( 'lawyers' );
if ( $page ) {
    $out = bb_render_path( '/' . $page->post_name . '/' );
    check( 'builder page renders', strlen( $out ) > 0 );
    check( 'builder page has theme shell', false !== strpos( $out, 'bb-site-header' ) );
    check( 'builder page injects plugin sections (.bb-template)', false !== strpos( $out, 'bb-template' ) );
    check( 'builder page has NO fatal error', false === stripos( $out, 'Fatal error' ) );
} else {
    echo "SKIP: no 'lawyers' page\n";
}

/* 404. */
$miss = bb_render_path( '/this-does-not-exist-xyz/' );
check( '404 renders with theme shell', false !== strpos( $miss, 'bb-site-footer' ) && false !== strpos( $miss, 'bb-error-404' ) );

switch_theme( $original );
restore_current_blog();
echo 'DONE' . PHP_EOL;