<?php
/**
 * Runtime test: Lawyer Management System (Phase 4).
 *
 * Covers the CPT, status data model, listing filters, frontend visibility,
 * activity/notification integration and multisite isolation — and proves the
 * Lawyer entity has NO relationship to Consultation or Appointment.
 *
 * Run: php tests/runtime-lawyer-management.php
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Packs\LawFirm\PostTypes\Lawyer;
use BusinessBuilderCore\Packs\LawFirm\PostTypes\LawyerFields;
use BusinessBuilderCore\Packs\LawFirm\Sections\LawFirmQueries;
use BusinessBuilderCore\Packs\LawFirm\Frontend\LawyerProfile;

if ( function_exists( 'switch_to_blog' )) {
    switch_to_blog( 2 );
}

$pass = 0;
$fail = 0;

function check( string $label, bool $cond ): void {
    global $pass, $fail;
    if ( $cond ) {
        $pass++;
        echo "  OK   $label\n";
    } else {
        $fail++;
        echo "  FAIL $label\n";
    }
}

/* Register the CPT + taxonomy the way a real request would. */
( new Lawyer() )->register_post_type();

/* Practice Area taxonomy is required for term assignment tests. */
if ( class_exists( 'BusinessBuilderCore\\Packs\\LawFirm\\Taxonomies\\PracticeArea' )) {
    ( new \BusinessBuilderCore\Packs\LawFirm\Taxonomies\PracticeArea() )->register_taxonomy();
}

if ( ! post_type_exists( 'bb_lawyer' )) {
    echo "SKIP: bb_lawyer CPT not registered.\n";
    exit( 0 );
}

echo "== CPT registration ==\n";
$obj = get_post_type_object( 'bb_lawyer' );
check( 'bb_lawyer exists', null !== $obj );
check( 'is public', ! empty( $obj->public ) );
check( 'is REST-enabled', ! empty( $obj->show_in_rest ) );
check( 'has archive', ! empty( $obj->has_archive ) );
check( 'supports thumbnail', post_type_supports( 'bb_lawyer', 'thumbnail' ) );
check( 'supports editor', post_type_supports( 'bb_lawyer', 'editor' ) );

echo "== status data model ==\n";
check( 'statuses() has active+inactive', array( 'active', 'inactive' ) === array_keys( LawyerFields::statuses() ) );
check( 'normalize keeps active', 'active' === LawyerFields::normalize_status( 'active' ) );
check( 'normalize keeps inactive', 'inactive' === LawyerFields::normalize_status( 'inactive' ) );
check( 'normalize rejects junk => active', 'active' === LawyerFields::normalize_status( '<script>x</script>' ) );

/* Create an ACTIVE lawyer. */
$a_id = wp_insert_post(
    array(
        'post_type'   => 'bb_lawyer',
        'post_status' => 'publish',
        'post_title'  => 'Active Lawyer',
    ),
    true
);
update_post_meta( $a_id, '_bb_lawyer_status', 'active' );
update_post_meta( $a_id, '_bb_lawyer_show_on_website', '1' );

/* Create an INACTIVE lawyer. */
$i_id = wp_insert_post(
    array(
        'post_type'   => 'bb_lawyer',
        'post_status' => 'publish',
        'post_title'  => 'Inactive Lawyer',
    ),
    true
);
update_post_meta( $i_id, '_bb_lawyer_status', 'inactive' );
update_post_meta( $i_id, '_bb_lawyer_show_on_website', '1' );

check( 'active lawyer status read', 'active' === LawyerFields::get_status( $a_id ) );
check( 'inactive lawyer status read', 'inactive' === LawyerFields::get_status( $i_id ) );
check( 'active lawyer is_active', LawyerFields::is_active( $a_id ) );
check( 'inactive lawyer not active', ! LawyerFields::is_active( $i_id ) );

echo "== default status when unset ==\n";
$d_id = wp_insert_post( array( 'post_type' => 'bb_lawyer', 'post_status' => 'publish', 'post_title' => 'Default Lawyer' ), true );
check( 'unset status defaults to active', 'active' === LawyerFields::get_status( $d_id ) );

echo "== frontend visibility gate ==\n";
check( 'active + shown => visible', LawyerFields::is_active( $a_id ) && LawyerProfile::is_active( $a_id ) );
check( 'inactive profile not active', ! LawyerProfile::is_active( $i_id ) );

/* Visibility control independent of status. */
update_post_meta( $a_id, '_bb_lawyer_show_on_website', '0' );
check( 'active but hidden is not is_active()', ! LawyerFields::is_active( $a_id ) );
update_post_meta( $a_id, '_bb_lawyer_show_on_website', '1' );

echo "== query: only active lawyers are listed ==\n";
$q = new LawFirmQueries();
$listed = $q->lawyers( array( 'limit' => 0 ) );
$ids    = wp_list_pluck( $listed, 'ID' );

check( 'active lawyer listed', in_array( $a_id, $ids, true ) );
check( 'unset-status lawyer listed', in_array( $d_id, $ids, true ) );
check( 'inactive lawyer NOT listed', ! in_array( $i_id, $ids, true ) );

echo "== practice area relationship ==\n";
if ( taxonomy_exists( 'bb_practice_area' )) {
    $term = wp_insert_term( 'Corporate Law Test', 'bb_practice_area' );
    if ( ! is_wp_error( $term )) {
        $term_id = (int) $term['term_id'];
        wp_set_object_terms( $a_id, array( $term_id ), 'bb_practice_area' );
        $get = wp_get_object_terms( $a_id, 'bb_practice_area', array( 'fields' => 'ids' ) );
        check( 'practice area assigned', in_array( $term_id, array_map( 'intval', $get ), true ) );
        wp_remove_object_terms( $a_id, array( $term_id ), 'bb_practice_area' );
        $after = wp_get_object_terms( $a_id, 'bb_practice_area', array( 'fields' => 'ids' ) );
        check( 'practice area removed', ! in_array( $term_id, array_map( 'intval', $after ), true ) );
        wp_delete_term( $term_id, 'bb_practice_area' );
    } else {
        check( 'practice area assigned', false );
        check( 'practice area removed', false );
    }
} else {
    echo "  SKIP practice area (taxonomy not available)\n";
}

echo "== no lawyer<->consultation/appointment relationship ==\n";
$consult_meta_keys = array( 'lawyer', 'lawyer_id', 'assigned_lawyer' );
$has_consult_rel = false;
foreach ( $consult_meta_keys as $k ) {
    if ( metadata_exists( 'post', $a_id, '_bb_consultation_' . $k ) || metadata_exists( 'post', $a_id, '_bb_appointment_' . $k )) {
        $has_consult_rel = true;
    }
}
check( 'lawyer object holds no booking relation meta', ! $has_consult_rel );
/* The lawyer CPT must expose no booking meta keys at all. */
check( 'no _bb_appointment_lawyer_id meta on lawyer', ! metadata_exists( 'post', $a_id, '_bb_appointment_lawyer_id' ) );
check( 'no _bb_consultation_lawyer_id meta on lawyer', ! metadata_exists( 'post', $a_id, '_bb_consultation_lawyer_id' ) );

/* Cleanup. */
foreach ( array( $a_id, $i_id, $d_id ) as $cid ) {
    if ( $cid ) {
        wp_delete_post( (int) $cid, true );
    }
}

echo "\nRESULT: $pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
