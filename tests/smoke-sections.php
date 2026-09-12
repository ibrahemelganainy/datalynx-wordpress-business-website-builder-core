<?php
/**
 * Smoke test: verify LawFirm section + query classes resolve and
 * that the section schemas register correctly. Runs standalone
 * (no WordPress) using minimal shims.
 */

$root = 'c:/MAMP/htdocs/wordpress/wp-content/plugins/business-builder-core';

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', $root );
}

$bb_shim = function ( $name, $body ) {
    if ( function_exists( $name ) ) {
        return;
    }
    eval( 'function ' . $name . '() { ' . $body . ' }' );
};

if ( ! function_exists( '__' ) ) {
    function __( $s, $d = null ) { return $s; }
}
if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $s, $d = null ) { return $s; }
}
if ( ! function_exists( 'get_terms' ) ) {
    function get_terms( $a = array() ) { return array(); }
}
if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $t ) { return false; }
}
if ( ! function_exists( 'sanitize_title' ) ) {
    function sanitize_title( $s ) { return strtolower( $s ); }
}
if ( ! function_exists( 'absint' ) ) {
    function absint( $n ) { return abs( (int) $n ); }
}
if ( ! function_exists( 'get_posts' ) ) {
    function get_posts( $a = array() ) { return array(); }
}
if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', $s ) ); }
}
if ( ! function_exists( 'add_action' ) ) {
    function add_action( $h, $c, $p = 10, $a = 1 ) { return true; }
}
if ( ! function_exists( 'get_term_meta' ) ) {
    function get_term_meta( $id, $k, $s = false ) { return ''; }
}
if ( ! function_exists( 'wp_parse_args' ) ) {
    function wp_parse_args( $args, $defaults = array() ) {
        if ( ! is_array( $args ) ) {
            $args = array();
        }
        return array_merge( $defaults, $args );
    }
}

require_once $root . '/includes/Builder/SectionRegistry.php';
require_once $root . '/packs/LawFirm/Sections/LawFirmQueries.php';
require_once $root . '/packs/LawFirm/Sections/LawFirmSections.php';

use BusinessBuilderCore\Builder\SectionRegistry;
use BusinessBuilderCore\Packs\LawFirm\Sections\LawFirmQueries;
use BusinessBuilderCore\Packs\LawFirm\Sections\LawFirmSections;

$ok = true;

$registry = new SectionRegistry();
$sections = new LawFirmSections( $registry );

try {
    $sections->register();
} catch ( \Throwable $e ) {
    echo "register() FAILED: " . $e->getMessage() . "\n";
    $ok = false;
}

$all = $registry->get_all();
echo "Registered: " . implode( ',', array_keys( $all ) ) . "\n";

foreach ( array( 'lawyers', 'legal_services', 'practice_areas', 'testimonials', 'faq' ) as $slug ) {
    if ( ! $registry->exists( $slug ) ) {
        echo "MISSING: $slug\n";
        $ok = false;
        continue;
    }
    $schema = $registry->get_editor_schema( $slug );
    $c = implode( ',', array_keys( $schema['content'] ) );
    $s = implode( ',', array_keys( $schema['settings'] ) );
    echo "$slug content=[$c] settings=[$s]\n";
}

$q = new LawFirmQueries();
echo 'lawyers:' . gettype( $q->lawyers( array() ) ) . "\n";
echo 'services:' . gettype( $q->legal_services( array() ) ) . "\n";
echo 'testimonials:' . gettype( $q->testimonials( array() ) ) . "\n";
echo 'faqs:' . gettype( $q->faqs( array() ) ) . "\n";
echo 'areas:' . gettype( $q->practice_areas( array() ) ) . "\n";

echo $ok ? "\nRESULT: OK\n" : "\nRESULT: FAIL\n";
