<?php
/**
 * Runtime test: same-day duplicate booking guard by phone number.
 *
 * Run: php tests/runtime-duplicate-booking.php
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Packs\LawFirm\Appointments\Availability;
use BusinessBuilderCore\Packs\LawFirm\Appointments\Appointment;
use BusinessBuilderCore\Packs\LawFirm\Appointments\AppointmentMeta;

if ( function_exists( 'switch_to_blog' )) {
    switch_to_blog( 2 );
}

( new Appointment() )->register_post_type();

if ( ! post_type_exists( 'bb_appointment' )) {
    echo "SKIP: bb_appointment CPT not registered.\n";
    exit( 0 );
}

$pass = 0;
$fail = 0;

function check( string $label, bool $cond ): void {
    global $pass, $fail;
    if ( $cond ) { $pass++; echo "  OK   $label\n"; }
    else { $fail++; echo "  FAIL $label\n"; }
}

$av = new Availability();

echo "== normalize_phone ==\n";
check( 'strips spaces/dashes', '201000000' === $av->normalize_phone( '20 100-0000' ) );
check( 'strips leading plus', '201000000' === $av->normalize_phone( '+20 100 0000' ) );
check( 'empty for no digits', '' === $av->normalize_phone( 'abc' ) );

/* Pick a working day within 2 weeks. */
$date = '';
for ( $i = 1; $i <= 14; $i++ ) {
    $d = gmdate( 'Y-m-d', strtotime( "+$i days" ) );
    if ( $av->is_working_day( $d )) { $date = $d; break; }
}

if ( '' === $date ) {
    echo "SKIP: no working day found.\n";
    exit( 0 );
}

/* Well-separated start times so buffer overlap cannot confuse results. */
$slots = $av->slots_for_date( $date );
$t1 = isset( $slots[0]['start'] ) ? $slots[0]['start'] : '09:00';
$t2 = isset( $slots[3]['start'] ) ? $slots[3]['start'] : '13:00';

echo "== first booking for a phone succeeds ==\n";
$r1 = $av->create( array( 'client_name' => 'Dup A', 'client_phone' => '+20 100 0000', 'date' => $date, 'start' => $t1 ) );
check( 'created', ! is_wp_error( $r1 ) );
$id1 = is_wp_error( $r1 ) ? 0 : (int) $r1;

echo "== same phone (loose format), same day is blocked as duplicate ==\n";
$r2 = $av->create( array( 'client_name' => 'Dup A', 'client_phone' => '20-100-0000', 'date' => $date, 'start' => $t2 ) );
check( 'rejected', is_wp_error( $r2 ) );
check( 'code is bb_duplicate_booking', is_wp_error( $r2 ) && 'bb_duplicate_booking' === $r2->get_error_code() );

echo "== existing_booking_for_phone ==\n";
check( 'found by loose format', $av->existing_booking_for_phone( $date, '20 100-0000' ) === $id1 );
check( 'no match for another phone', 0 === $av->existing_booking_for_phone( $date, '5555' ) );

echo "== a DIFFERENT phone can still book the same day ==\n";
$r3 = $av->create( array( 'client_name' => 'Dup B', 'client_phone' => '5555', 'date' => $date, 'start' => $t2 ) );
check( 'different phone allowed', ! is_wp_error( $r3 ) );
$id3 = is_wp_error( $r3 ) ? 0 : (int) $r3;

echo "== cancelling frees the customer to rebook ==\n";
if ( $id1 ) {
    update_post_meta( $id1, AppointmentMeta::key( 'status' ), 'cancelled' );
}
$r4 = $av->create( array( 'client_name' => 'Dup A', 'client_phone' => '201000', 'date' => $date, 'start' => $t1 ) );
check( 'rebook after cancel allowed', ! is_wp_error( $r4 ) );
$id4 = is_wp_error( $r4 ) ? 0 : (int) $r4;

foreach ( array( $id1, $id3, $id4 ) as $cid ) {
    if ( $cid ) { wp_delete_post( $cid, true ); }
}

echo "\nRESULT: $pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
