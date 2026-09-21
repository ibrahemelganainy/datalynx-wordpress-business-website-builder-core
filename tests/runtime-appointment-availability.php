<?php
/**
 * Runtime test: appointment availability correctness (the false "slot taken" bug).
 *
 * Run: php tests/runtime-appointment-availability.php
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Packs\LawFirm\Appointments\Availability;
use BusinessBuilderCore\Packs\LawFirm\Appointments\AppointmentMeta;

/*
 * The LawFirm CPTs are registered per site and only where the LawFirm
 * pack is active. Run against the pack-enabled test site so the
 * availability query finds the appointment post type.
 */
if ( function_exists( 'switch_to_blog' )) {
    switch_to_blog( 2 );
}

/* Register the CPT the way a real request would (matches runtime-appointments.php). */
( new \BusinessBuilderCore\Packs\LawFirm\Appointments\Appointment() )->register_post_type();

if ( ! post_type_exists( 'bb_appointment' )) {
    echo "SKIP: bb_appointment CPT not registered on this site.\n";
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

/* Pick the next working day within 2 weeks. */
$date = '';
for ( $i = 1; $i <= 14; $i++ ) {
    $d = gmdate( 'Y-m-d', strtotime( "+$i days" ) );
    if ( $av->is_working_day( $d ) ) { $date = $d; break; }
}

echo "== normalize_time ==\n";
check( 'HH:MM accepted', '09:30' === $av->normalize_time( '09:30' ) );
check( 'HH:MM:SS accepted (browser seconds)', '09:30' === $av->normalize_time( '09:30:00' ) );
check( 'garbage rejected', '' === $av->normalize_time( 'nope' ) );

echo "== zero appointments => all slots free ==\n";
$slots = $av->slots_for_date( $date );
check( 'slots generated for a working day', count( $slots ) > 0 );

$any_taken = false;
foreach ( $slots as $s ) {
    if ( ! $s['available'] ) { $any_taken = true; }
}
check( 'no slot reported taken with zero appointments', ! $any_taken );

$first = $slots[0]['start'];

echo "== create with HH:MM:SS (the reported trigger) ==\n";
$r = $av->create( array( 'client_name' => 'AVAIL Test', 'client_phone' => '0100', 'date' => $date, 'start' => $first . ':00' ) );
check( 'HH:MM:SS create succeeds', ! is_wp_error( $r ) );

$appt_id = is_wp_error( $r ) ? 0 : (int) $r;

echo "== slot now blocks ==\n";
if ( $appt_id ) {
    check( 'occupied slot is now taken', ! $av->is_slot_free( $date, $first ) );
}

echo "== accurate error codes ==\n";
$past = $av->create( array( 'client_name' => 'X', 'client_phone' => '010', 'date' => gmdate( 'Y-m-d', strtotime( '-2 days' ) ), 'start' => $first ) );
check( 'past date => bb_past_date', is_wp_error( $past ) && 'bb_past_date' === $past->get_error_code() );

$outside = $av->create( array( 'client_name' => 'X', 'client_phone' => '010', 'date' => $date, 'start' => '23:30' ) );
check( 'after-hours => bb_outside_hours', is_wp_error( $outside ) && 'bb_outside_hours' === $outside->get_error_code() );

/* A weekend date => non-working day. */
$weekend = '';
for ( $i = 1; $i <= 14; $i++ ) {
    $d = gmdate( 'Y-m-d', strtotime( "+$i days" ) );
    if ( ! $av->is_working_day( $d ) ) { $weekend = $d; break; }
}
if ( '' !== $weekend ) {
    $closed = $av->create( array( 'client_name' => 'X', 'client_phone' => '010', 'date' => $weekend, 'start' => $first ) );
    check( 'non-working day => bb_non_working_day', is_wp_error( $closed ) && 'bb_non_working_day' === $closed->get_error_code() );
}

echo "== cancel frees the slot ==\n";
if ( $appt_id ) {
    update_post_meta( $appt_id, AppointmentMeta::key( 'status' ), 'cancelled' );
    check( 'cancelled slot becomes free again', $av->is_slot_free( $date, $first, 0 ) );
    wp_delete_post( $appt_id, true );
}

echo "\nPASS: $pass   FAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );
exit( 0 === $fail ? 0 : 1 );
