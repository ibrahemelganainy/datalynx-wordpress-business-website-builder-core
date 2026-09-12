<?php
/**
 * Phase 10 verification: Consultation form + storage wiring.
 * Runs standalone with minimal WordPress shims.
 */

$root = 'c:/MAMP/htdocs/wordpress/wp-content/plugins/business-builder-core';

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', $root );
}

if ( ! defined( 'BB_CORE_PATH' ) ) {
    define( 'BB_CORE_PATH', $root . '/' );
}

if ( ! function_exists( '__' ) ) {
    function __( $s, $d = null ) { return $s; }
}

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $s, $d = null ) { return $s; }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
    function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
}

if ( ! function_exists( 'sanitize_email' ) ) {
    function sanitize_email( $s ) { return (string) $s; }
}

if ( ! function_exists( 'sanitize_title' ) ) {
    function sanitize_title( $s ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '-', (string) $s ) ); }
}

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $s ) ); }
}

if ( ! function_exists( 'is_email' ) ) {
    function is_email( $s ) { return (bool) filter_var( $s, FILTER_VALIDATE_EMAIL ); }
}

if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; }
}

if ( ! function_exists( 'get_terms' ) ) {
    function get_terms( $a = array() ) { return array(); }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $x ) { return false; }
}

if ( ! function_exists( 'add_action' ) ) {
    function add_action( $h, $c, $p = 10, $a = 1 ) { return true; }
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $h, $c, $p = 10, $a = 1 ) { return true; }
}

if ( ! function_exists( 'register_post_type' ) ) {
    function register_post_type( $t, $a = array() ) { return true; }
}

if ( ! function_exists( 'add_meta_box' ) ) {
    function add_meta_box( $a, $b, $c, $d, $e, $f ) { return true; }
}

if ( ! function_exists( 'wp_parse_args' ) ) {
    function wp_parse_args( $args, $defaults = array() ) {
        if ( ! is_array( $args ) ) {
            $args = array();
        }
        return array_merge( $defaults, $args );
    }
}
if ( ! function_exists( 'admin_url' ) ) {     function admin_url( $path = '' ) { return 'https://example.com/wp-admin/' . $path; } } if ( ! function_exists( 'home_url' ) ) {     function home_url( $path = '' ) { return 'https://example.com/' . $path; } } if ( ! function_exists( 'wp_get_referer' ) ) {     function wp_get_referer() { return ''; } } if ( ! function_exists( 'add_query_arg' ) ) {     function add_query_arg( $k, $v, $url ) { return $url; } } if ( ! function_exists( 'remove_query_arg' ) ) {     function remove_query_arg( $k, $url ) { return $url; } }

require_once $root . '/includes/Builder/SectionRegistry.php';
require_once $root . '/packs/LawFirm/Sections/LawFirmQueries.php';
require_once $root . '/packs/LawFirm/Frontend/ConsultationForm.php';
require_once $root . '/packs/LawFirm/PostTypes/Consultation.php';
require_once $root . '/packs/LawFirm/Sections/LawFirmSections.php';

use BusinessBuilderCore\Packs\LawFirm\Frontend\ConsultationForm;
use BusinessBuilderCore\Packs\LawFirm\PostTypes\Consultation;
use BusinessBuilderCore\Packs\LawFirm\Sections\LawFirmSections;
use BusinessBuilderCore\Builder\SectionRegistry;

$ok = true;

/* 1. Classes resolve. */
if ( ! class_exists( 'BusinessBuilderCore\\Packs\\LawFirm\\Frontend\\ConsultationForm' ) ) {
    echo "ConsultationForm class MISSING\n";
    $ok = false;
}

if ( ! class_exists( 'BusinessBuilderCore\\Packs\\LawFirm\\PostTypes\\Consultation' ) ) {
    echo "Consultation CPT class MISSING\n";
    $ok = false;
}

/* 2. Static accessors used by the template. */
echo 'action_url: ' . ConsultationForm::action_url() . "\n";
echo 'action_name: ' . ConsultationForm::action_name() . "\n";
echo 'nonce_field: ' . ConsultationForm::nonce_field() . "\n";
echo 'nonce_action: ' . ConsultationForm::nonce_action() . "\n";

if ( 'bb_consultation' !== ConsultationForm::action_name() ) {
    echo "action name WRONG\n";
    $ok = false;
}

/* 3. CPT slug accessor. */
$cpt = new Consultation();
echo 'cpt: ' . $cpt->get_post_type() . "\n";

if ( 'bb_consultation' !== $cpt->get_post_type() ) {
    echo "CPT slug WRONG\n";
    $ok = false;
}

/* 4. Consultation section registers with a render callback. */
$registry = new SectionRegistry();
$sections = new LawFirmSections( $registry );

try {
    $sections->register();
} catch ( \Throwable $e ) {
    echo "register() FAILED: " . $e->getMessage() . "\n";
    $ok = false;
}

if ( ! $registry->exists( 'consultation' ) ) {
    echo "consultation section MISSING\n";
    $ok = false;
} else {
    $config = $registry->get( 'consultation' );
    $has_render = isset( $config['render'] ) && is_callable( $config['render'] );
    echo 'consultation render callable: ' . ( $has_render ? 'yes' : 'NO' ) . "\n";
    if ( ! $has_render ) {
        $ok = false;
    }
}

/* 5. Form template exists and includes required fields. */
$template = $root . '/templates/consultation-form.php';
if ( file_exists( $template ) ) {
    $html = file_get_contents( $template );
    foreach ( array( 'bb_name', 'bb_phone', 'bb_email', 'bb_practice_area', 'bb_message', 'bb_preferred_contact' ) as $field ) {
        if ( false === strpos( $html, $field ) ) {
            echo "template MISSING field: $field\n";
            $ok = false;
        }
    }
    echo "template fields: ok\n";
} else {
    echo "template MISSING\n";
    $ok = false;
}

echo $ok ? "\nRESULT: OK\n" : "\nRESULT: FAIL\n";
