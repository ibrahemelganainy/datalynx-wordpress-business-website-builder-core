<?php
/**
 * Phase 11 verification: StarterSite blueprint + build logic.
 * Runs standalone with minimal WordPress shims.
 */

$root = 'c:/MAMP/htdocs/wordpress/wp-content/plugins/business-builder-core';

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', $root );
}

if ( ! function_exists( '__' ) ) {
    function __( $s, $d = null ) { return $s; }
}

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $s, $d = null ) { return $s; }
}

if ( ! function_exists( 'get_page_by_path' ) ) {
    function get_page_by_path( $slug ) { return null; }
}

if ( ! function_exists( 'wp_insert_post' ) ) {
    function wp_insert_post( $args, $wp_error = false ) { return 1001; }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $x ) { return false; }
}

if ( ! function_exists( 'get_option' ) ) {
    function get_option( $k, $d = false ) { return $d; }
}

if ( ! function_exists( 'update_option' ) ) {
    function update_option( $k, $v ) { return true; }
}

/**
 * Minimal PageManager stub mirroring the real public API used by
 * the starter: enable_builder / set_page_template / add_section.
 *
 * NOTE: declared inside the BusinessBuilderCore\Builder namespace
 * so it satisfies StarterSite::__construct( PageManager $m ) type.
 */
class PageManager {
    public $enabled = array();
    public $templates = array();
    public $sections = array();

    public function enable_builder( $page_id ) {
        $this->enabled[] = $page_id;
        return true;
    }

    public function set_page_template( $page_id, $template ) {
        $this->templates[ $page_id ] = $template;
        return true;
    }

    public function add_section( $page_id, $type, $settings = array(), $content = array() ) {
        $this->sections[ $page_id ][] = $type;
        return 'uuid-' . $type;
    }
}

/* Alias the stub under the Core namespace so it satisfies the
 * typed constructor of StarterSite. */
class_alias( 'PageManager', 'BusinessBuilderCore\\Builder\\PageManager' );

require_once $root . '/packs/LawFirm/Starter/StarterSite.php';

use BusinessBuilderCore\Packs\LawFirm\Starter\StarterSite;

$ok = true;

$pm = new \BusinessBuilderCore\Builder\PageManager();
$starter = new StarterSite( $pm );

/* 1. Blueprint sanity. */
$bp = $starter->blueprint();
$expected = array( 'home', 'about', 'practice-areas', 'services', 'lawyers', 'contact' );

foreach ( $expected as $slug ) {
    if ( ! isset( $bp[ $slug ] ) ) {
        echo "MISSING page in blueprint: $slug\n";
        $ok = false;
        continue;
    }
    $page = $bp[ $slug ];
    if ( empty( $page['sections'] ) ) {
        echo "Page has no sections: $slug\n";
        $ok = false;
    }
    $sep = chr( 44 ) . chr( 32 );
    $section_list = implode( $sep, $page['sections'] );
    echo $slug . ' template=' . $page['template'] . ' sections=' . $section_list . "\n";
}

/* 2. Build runs and produces pages + sections. */
$result = $starter->build();

$created_list = implode( $sep, $result['created'] );
$skipped_list = implode( $sep, $result['skipped'] );

echo 'created: ' . $created_list . "\n";
echo 'skipped: ' . $skipped_list . "\n";
echo 'sections_added: ' . $result['sections_added'] . "\n";

if ( count( $result['created'] ) !== 6 ) {
    echo "Expected 6 created pages\n";
    $ok = false;
}

if ( $result['sections_added'] < 20 ) {
    echo "Expected a healthy number of sections added\n";
    $ok = false;
}

if ( count( $pm->enabled ) !== 6 ) {
    echo "Builder not enabled on all pages\n";
    $ok = false;
}

/* 3. Home must include the key pack sections. */
$home = isset( $pm->sections[1001] ) ? $pm->sections[1001] : array();
$needed = array( 'slider', 'practice_areas', 'legal_services', 'lawyers', 'testimonials', 'faq' );

foreach ( $needed as $need ) {
    if ( ! in_array( $need, $home, true ) ) {
        echo "Home missing section: $need\n";
        $ok = false;
    }
}

echo $ok ? "\nRESULT: OK\n" : "\nRESULT: FAIL\n";
