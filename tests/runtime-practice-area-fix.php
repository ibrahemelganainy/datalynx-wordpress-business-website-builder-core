<?php
/*
 * Real WP runtime test: Practice Area normalization bug fix (Phase A).
 *
 * Verifies that an Arabic practice area resolves to a human-readable
 * term name from every historical storage shape:
 *   - canonical term ID (new records)
 *   - plain slug
 *   - percent-encoded slug (single)
 *   - double percent-encoded slug (the reported bug)
 * and that an English area still resolves correctly.
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';
require_once ABSPATH . 'wp-content/plugins/business-builder-core/packs/LawFirm/PostTypes/ConsultationMeta.php';

switch_to_blog( 2 );
do_action( 'init' );

use BusinessBuilderCore\Packs\LawFirm\PostTypes\ConsultationMeta;
use BusinessBuilderCore\Packs\LawFirm\Taxonomies\PracticeArea;

/*
 * Make the test self-sufficient: ensure the practice-area taxonomy is
 * registered even if the LawFirm pack was not booted for this site.
 */
$taxonomy = 'bb_practice_area';

$tax_ready = taxonomy_exists( $taxonomy );

if ( ! $tax_ready ) {
    $pa = new PracticeArea();
    $pa->register_taxonomy();
}

/* Create (or reuse) an Arabic and an English practice area. */
$ar_name = 'قانون الشركات';
$en_name = 'Corporate Law';

$ar = term_exists( $ar_name, $taxonomy );
if ( ! $ar ) {
    $ar = wp_insert_term( $ar_name, $taxonomy );
}
$en = term_exists( $en_name, $taxonomy );
if ( ! $en ) {
    $en = wp_insert_term( $en_name, $taxonomy );
}

$ar_id = is_array( $ar ) ? (int) $ar['term_id'] : (int) $ar;
$en_id = is_array( $en ) ? (int) $en['term_id'] : (int) $en;

$ar_term = get_term( $ar_id, $taxonomy );
$en_term = get_term( $en_id, $taxonomy );

echo 'AR term id=' . $ar_id . ' slug=' . $ar_term->slug . PHP_EOL;
echo 'EN term id=' . $en_id . ' slug=' . $en_term->slug . PHP_EOL;

function check( string $label, $stored, string $expected ): void {
    $term = ConsultationMeta::resolve_practice_area( $stored );
    $name = ( $term instanceof WP_Term ) ? $term->name : '(null)';
    $ok   = ( $name === $expected ) ? 'OK' : 'FAIL';
    echo sprintf( '[%s] %s => %s (expected %s)', $ok, $label, $name, $expected ) . PHP_EOL;
}

/* Arabic: every storage shape must resolve to the real name. */
check( 'AR term id',          (string) $ar_id,               $ar_name );
check( 'AR plain slug',       $ar_term->slug,                 $ar_name );
check( 'AR encoded slug',     rawurlencode( $ar_term->slug ), $ar_name );
check( 'AR double-encoded',   rawurlencode( rawurlencode( $ar_term->slug ) ), $ar_name );
check( 'AR by name',          $ar_name,                       $ar_name );

/* English: regression guard. */
check( 'EN term id',          (string) $en_id,               $en_name );
check( 'EN plain slug',       $en_term->slug,                 $en_name );
check( 'EN by name',          $en_name,                       $en_name );

/* A consultation that stores the ID resolves via the helper. */
$c = wp_insert_post( array( 'post_type' => 'bb_consultation', 'post_status' => 'publish', 'post_title' => 'PA-Test' ) );
update_post_meta( $c, '_bb_consultation_practice_area_id', $ar_id );
update_post_meta( $c, '_bb_consultation_practice_area', 'ignored-legacy' );
$via_helper = ConsultationMeta::practice_area_name( (int) $c );
echo 'practice_area_name(consultation) => ' . $via_helper . ' [' . ( $via_helper === $ar_name ? 'OK' : 'FAIL' ) . ']' . PHP_EOL;

/* A legacy record storing only the encoded slug (no id) also resolves. */
$c2 = wp_insert_post( array( 'post_type' => 'bb_consultation', 'post_status' => 'publish', 'post_title' => 'PA-Legacy' ) );
update_post_meta( $c2, '_bb_consultation_practice_area', rawurlencode( $ar_term->slug ) );
$legacy = ConsultationMeta::practice_area_name( (int) $c2 );
echo 'legacy encoded-only => ' . $legacy . ' [' . ( $legacy === $ar_name ? 'OK' : 'FAIL' ) . ']' . PHP_EOL;

/* Public reference must be non-sequential / non-numeric. */
$ref = ConsultationMeta::generate_public_reference();
echo 'public ref sample: ' . $ref . ' [' . ( preg_match( '/^CNS-[A-Z0-9]{10}$/', $ref ) ? 'OK' : 'FAIL' ) . ']' . PHP_EOL;

/* Clean up test posts. */
wp_delete_post( (int) $c, true );
wp_delete_post( (int) $c2, true );

restore_current_blog();
echo 'DONE' . PHP_EOL;
