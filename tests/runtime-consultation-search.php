<?php
/**
 * Runtime test: consultation admin search across reference/name/email/phone/
 * practice-area/payment fields, incl. Arabic practice-area term resolution.
 *
 * Run: php tests/runtime-consultation-search.php
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Packs\LawFirm\Admin\ConsultationAdmin;
use BusinessBuilderCore\Packs\LawFirm\PostTypes\ConsultationMeta;
use BusinessBuilderCore\Core\Notifications\NotificationManager;
use BusinessBuilderCore\Core\Audit\AuditLog;

$pass = 0;
$fail = 0;

function check( string $label, bool $cond ): void {
    global $pass, $fail;
    if ( $cond ) { $pass++; echo "  OK   $label\n"; }
    else { $fail++; echo "  FAIL $label\n"; }
}

/* Register the CPT so the query runs. */
( new \BusinessBuilderCore\Packs\LawFirm\PostTypes\Consultation() )->register_post_type();

$admin = new ConsultationAdmin( new NotificationManager(), new AuditLog() );

/* Create a practice-area term with an Arabic name. */
$tax = ConsultationMeta::practice_area_taxonomy();

if ( ! taxonomy_exists( $tax ))  {
    register_taxonomy( $tax, array( 'bb_consultation' ), array( 'public' => false ) );
}

$term = wp_insert_term( 'قانون الأسرة', $tax );
$term_id = is_wp_error( $term ) ? 0 : (int) $term['term_id'];

/* Create a consultation fixture. */
$ref = 'CNS-SEARCHTST1';
$cid = wp_insert_post(
    array(
        'post_type'   => 'bb_consultation',
        'post_status' => 'publish',
        'post_title'  => 'Search Fixture',
    ),
    true
);

update_post_meta( $cid, ConsultationMeta::key( 'public_reference' ), $ref );
update_post_meta( $cid, ConsultationMeta::key( 'name' ), 'Amina Searchable' );
update_post_meta( $cid, ConsultationMeta::key( 'email' ), 'amina@example.com' );
update_post_meta( $cid, ConsultationMeta::key( 'phone' ), '01099887766' );
update_post_meta( $cid, ConsultationMeta::key( 'practice_area_id' ), (string) $term_id );
update_post_meta( $cid, ConsultationMeta::key( 'payment_reference' ), 'PAY-SRCH1234' );
update_post_meta( $cid, ConsultationMeta::key( 'payment_status' ), 'paid' );

/**
 * Run an admin-style search through the WP query with the admin hook.
 *
 * @param string $term Search term.
 * @return int[] Matching post ids.
 */
function bb_run_search( string $term ): array {
    $admin = new ConsultationAdmin( new NotificationManager(), new AuditLog() );

    /* The search is admin-only in production; force it on for the test. */
    add_filter( 'bb_consultation_search_force', '__return_true' );

    $query = new WP_Query();
    $query->set( 'post_type', 'bb_consultation' );
    $query->set( 'post_status', 'publish' );
    $query->set( 's', $term );
    $query->set( 'fields', 'ids' );
    $query->set( 'posts_per_page', -1 );

    /* Simulate is_admin() by invoking the hook callback directly. */
    $admin->extend_admin_search( $query );

    $query->get_posts();

    return array_map( 'intval', $query->posts );
}

echo "== search by public reference ==\n";
check( 'reference match', in_array( $cid, bb_run_search( $ref ), true ) );

echo "== search by customer fields ==\n";
check( 'name match', in_array( $cid, bb_run_search( 'Searchable' ), true ) );
check( 'email match', in_array( $cid, bb_run_search( 'amina@example.com' ), true ) );
check( 'phone match', in_array( $cid, bb_run_search( '99887766' ), true ) );

echo "== search by payment fields ==\n";
check( 'payment reference match', in_array( $cid, bb_run_search( 'SRCH1234' ), true ) );
check( 'payment status match', in_array( $cid, bb_run_search( 'paid' ), true ) );

echo "== Arabic practice area ==\n";
check( 'Arabic region compiled to term', $term_id > 0 );
check( 'Arabic practice-area name match', in_array( $cid, bb_run_search( 'قانون الأسرة' ), true ) );

echo "== cleanup ==\n";
wp_delete_post( $cid, true );
if ( $term_id > 0 ) {
    wp_delete_term( $term_id, $tax );
}

echo "\nPASS: $pass   FAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );
exit( 0 === $fail ? 0 : 1 );
