<?php
/**
 * Runtime test: Consultation & Appointment lookup (ref + phone).
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Packs\LawFirm\Frontend\LookupHandler;
use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Packs\LawFirm\PostTypes\ConsultationMeta;

$fail = 0;

function chk( string $label, bool $cond ): void {
    global $fail;
    if ( $cond ) { echo "  OK   $label\n"; }
    else { $fail++; echo "  FAIL $label\n"; }
}

/* Register the CPT for the query. */
( new \BusinessBuilderCore\Packs\LawFirm\PostTypes\Consultation() )->register_post_type();

$handler = new LookupHandler( new PaymentManager() );

$lookup = new ReflectionMethod( $handler, 'lookup_consultation' );
$lookup->setAccessible( true );

/* Fixture. */
$ref = 'CNS-LOOKUP0001';
$cid = wp_insert_post(
    array( 'post_type' => 'bb_consultation', 'post_status' => 'publish', 'post_title' => 'Lookup Test' ),
    true
);

update_post_meta( $cid, ConsultationMeta::key( 'public_reference' ), $ref );
update_post_meta( $cid, ConsultationMeta::key( 'name' ), 'John Doe' );
update_post_meta( $cid, ConsultationMeta::key( 'phone' ), '01012345678' );
update_post_meta( $cid, ConsultationMeta::key( 'status' ), 'contacted' );
update_post_meta( $cid, ConsultationMeta::key( 'payment_status' ), 'paid' );

echo "== phone normalization ==\n";
chk( 'same digits match', LookupHandler::phone_matches( '01012345678', '01012345678' ) );
chk( '+20 prefix matches', LookupHandler::phone_matches( '01012345678', '+201012345678' ) );
chk( '00 prefix matches', LookupHandler::phone_matches( '01012345678', '00201012345678' ) );
chk( 'spaces/dashes ignored', LookupHandler::phone_matches( '01012345678', '010-1234-5678' ) );
chk( 'different number does NOT match', false === LookupHandler::phone_matches( '01012345678', '01099999' ) );
chk( 'short/empty does NOT match', false === LookupHandler::phone_matches( '01012345678', '123' ) );

echo "== lookup verification ==\n";
$ok = $lookup->invoke( $handler, $ref, '01012345678' );
chk( 'correct ref + phone resolves', is_array( $ok ) );
chk( 'result carries the reference', is_array( $ok ) && $ref === $ok['reference'] );
chk( 'result carries the customer name', is_array( $ok ) && 'John Doe' === $ok['customer'] );
chk( 'result carries the status label', is_array( $ok ) && '' !== $ok['status'] );
chk( 'result carries a timeline', is_array( $ok ) && count( $ok['timeline'] ) > 0 );
chk( 'payment block reports paid', is_array( $ok ) && 'paid' === $ok['payment']['status_key'] );

echo "== enumeration protection ==\n";
chk( 'correct ref + WRONG phone returns null', null === $lookup->invoke( $handler, $ref, '01000000' ) );
chk( 'unknown ref returns null', null === $lookup->invoke( $handler, 'CNS-NOTEXIST1', '01012345678' ) );

echo "== reference shape ==\n";
$matches = new ReflectionMethod( $handler, 'reference_matches_type' );
$matches->setAccessible( true );
chk( 'CNS- prefix is a consultation', $matches->invoke( $handler, 'consultation', 'CNS-ABC123' ) );
chk( 'APT- prefix is an appointment', $matches->invoke( $handler, 'appointment', 'APT-ABC123' ) );
chk( 'wrong prefix rejected', false === $matches->invoke( $handler, 'consultation', 'APT-ABC123' ) );

echo "== reference masking ==\n";
chk( 'long ref masked to last 6', LookupHandler::mask_reference( 'cs_test_1234567890ABCDEF' ) === '…ABCDEF' );
chk( 'short ref left intact', LookupHandler::mask_reference( 'ABC' ) === 'ABC' );

wp_delete_post( $cid, true );

echo "\nFAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );
exit( 0 === $fail ? 0 : 1 );
