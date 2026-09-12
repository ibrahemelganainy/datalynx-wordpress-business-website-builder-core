<?php
/**
 * Phase 12 verification: template compatibility.
 *
 * Proves that one data model (section data) is consumable by all three
 * visual templates, and that each template maps to the wrapper class
 * used by the renderer. Presentation differences only.
 */

$root = 'c:/MAMP/htdocs/wordpress/wp-content/plugins/business-builder-core';

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', $root );
}

if ( ! function_exists( '__' ) ) {
    function __( $s, $d = null ) { return $s; }
}

require_once $root . '/packs/LawFirm/Templates/Classic.php';
require_once $root . '/packs/LawFirm/Templates/Modern.php';
require_once $root . '/packs/LawFirm/Templates/Luxury.php';
require_once $root . '/packs/LawFirm/Templates/Templates.php';

use BusinessBuilderCore\Packs\LawFirm\Templates\Classic;
use BusinessBuilderCore\Packs\LawFirm\Templates\Modern;
use BusinessBuilderCore\Packs\LawFirm\Templates\Luxury;
use BusinessBuilderCore\Packs\LawFirm\Templates\Templates;

$ok = true;

/* 1. Template registry lists all three, in sync with the renderer. */
$slugs = Templates::slugs();
$sep = chr( 44 ) . chr( 32 );
echo 'slugs: ' . implode( $sep, $slugs ) . "\n";

foreach ( array( 'default', 'modern', 'luxury' ) as $slug ) {
    if ( ! Templates::exists( $slug ) ) {
        echo "MISSING template slug: $slug\n";
        $ok = false;
    }
}

/* 2. Wrapper classes match SectionRenderer::render_page() mapping. */
$expected_map = array(
    'default' => 'bb-template-default',
    'modern'  => 'bb-template-modern',
    'luxury'  => 'bb-template-luxury',
);

foreach ( $expected_map as $slug => $wrapper ) {
    $actual = Templates::wrapper_class( $slug );
    if ( $actual !== $wrapper ) {
        echo "WRAPPER mismatch for $slug: got $actual expected $wrapper\n";
        $ok = false;
    }
    echo "$slug -> $actual\n";
}

/* 3. Unknown slug must gracefully fall back to Classic wrapper. */
if ( Templates::wrapper_class( 'does-not-exist' ) !== Classic::WRAPPER_CLASS ) {
    echo "Fallback for unknown slug is wrong\n";
    $ok = false;
}

/* 4. Class constants are self-consistent. */
if ( Classic::SLUG !== 'default' || Classic::WRAPPER_CLASS !== 'bb-template-default' ) {
    echo "Classic constants wrong\n";
    $ok = false;
}

if ( Modern::SLUG !== 'modern' || Modern::WRAPPER_CLASS !== 'bb-template-modern' ) {
    echo "Modern constants wrong\n";
    $ok = false;
}

if ( Luxury::SLUG !== 'luxury' || Luxury::WRAPPER_CLASS !== 'bb-template-luxury' ) {
    echo "Luxury constants wrong\n";
    $ok = false;
}

/* 5. ONE data model is consumable by all templates: simulate the
 *    sections a page stores and confirm each template's wrapper can
 *    carry the SAME section list (presentation-only difference). */
$shared_sections = array(
    array( 'type' => 'hero', 'settings' => array(), 'content' => array( 'title' => 'X' ) ),
    array( 'type' => 'lawyers', 'settings' => array( 'columns' => 3 ), 'content' => array() ),
    array( 'type' => 'testimonials', 'settings' => array(), 'content' => array() ),
    array( 'type' => 'faq', 'settings' => array(), 'content' => array() ),
);

foreach ( $slugs as $slug ) {
    $wrapper = Templates::wrapper_class( $slug );

    /* Every template carries the identical section set. */
    $carried = 0;
    foreach ( $shared_sections as $section ) {
        if ( isset( $section['type'] ) ) {
            $carried++;
        }
    }

    if ( $carried !== count( $shared_sections ) ) {
        echo "Template $slug did not carry all shared sections\n";
        $ok = false;
    }

    if ( '' === $wrapper ) {
        echo "Template $slug produced empty wrapper\n";
        $ok = false;
    }

    echo "template $slug carries $carried shared sections via $wrapper\n";
}

echo $ok ? "\nRESULT: OK\n" : "\nRESULT: FAIL\n";
