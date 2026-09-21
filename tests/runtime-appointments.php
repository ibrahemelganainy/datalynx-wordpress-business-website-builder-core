<?php
/* Real WP runtime: Appointment availability + double-booking protection. */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

$root = ABSPATH . 'wp-content/plugins/business-builder-core/';
foreach ( glob( $root . 'includes/Core/Notifications/*.php' ) as $f ) { require_once $f; }
require_once $root . 'includes/Core/Audit/AuditLog.php';
require_once $root . 'packs/LawFirm/Appointments/AppointmentMeta.php';
require_once $root . 'packs/LawFirm/Appointments/Appointment.php';
require_once $root . 'packs/LawFirm/Appointments/Availability.php';

use BusinessBuilderCore\Packs\LawFirm\Appointments\Appointment;
use BusinessBuilderCore\Packs\LawFirm\Appointments\Availability;
use BusinessBuilderCore\Packs\LawFirm\Appointments\AppointmentMeta;

switch_to_blog( 2 );

/* Register the CPT the way a real request would. */
( new Appointment() )->register_post_type();

echo 'bb_appointment registered: ' . var_export( post_type_exists( 'bb_appointment' ), true ) . PHP_EOL;

$av = new Availability();

/* Pick the next working day (Mon-Fri). */
$date = gmdate( 'Y-m-d', strtotime( 'next monday' ) );
echo 'test date: ' . $date . PHP_EOL;

$slots = $av->slots_for_date( $date );
echo 'slots generated: ' . count( $slots ) . PHP_EOL;

$first = $slots[0]['start'] ?? '';
$second = $slots[1]['start'] ?? '';
echo 'first slot: ' . $first . ' second slot: ' . $second . PHP_EOL;

/* 1. Book the first slot. */
$r1 = $av->create( array( 'client_name' => 'Test Client A', 'date' => $date, 'start' => $first, 'client_phone' => '0100' ) );
echo 'book #1: ' . ( is_wp_error( $r1 ) ? 'ERROR ' . $r1->get_error_code() : 'created id=' . $r1 ) . PHP_EOL;

/* 2. Attempt to double-book the SAME slot. */
$r2 = $av->create( array( 'client_name' => 'Test Client B', 'date' => $date, 'start' => $first, 'client_phone' => '0111' ) );
echo 'book #2 (same slot): ' . ( is_wp_error( $r2 ) ? 'BLOCKED ' . $r2->get_error_code() : 'created id=' . $r2 ) . PHP_EOL;

/* 3. Book a DIFFERENT slot -> should succeed. */
$r3 = $av->create( array( 'client_name' => 'Test Client C', 'date' => $date, 'start' => $second, 'client_phone' => '0122' ) );
echo 'book #3 (other slot): ' . ( is_wp_error( $r3 ) ? 'ERROR ' . $r3->get_error_code() : 'created id=' . $r3 ) . PHP_EOL;

/* 4. Slot list now marks the first as booked. */
$slots2 = $av->slots_for_date( $date );
$first_state = 'n/a';
foreach ( $slots2 as $s ) { if ( $s['start'] === $first ) { $first_state = $s['available'] ? 'available' : 'booked(' . $s['reason'] . ')'; } }
echo 'first slot now: ' . $first_state . PHP_EOL;

/* 5. is_slot_free directly. */
echo 'is_slot_free(first): ' . var_export( $av->is_slot_free( $date, $first ), true ) . PHP_EOL;
echo 'is_slot_free(second): ' . var_export( $av->is_slot_free( $date, $second ), true ) . PHP_EOL;

/* 6. Cancelling frees the slot. */
if ( ! is_wp_error( $r1 ) ) {
    update_post_meta( $r1, AppointmentMeta::key( 'status' ), 'cancelled' );
    echo 'after cancel is_slot_free(first): ' . var_export( $av->is_slot_free( $date, $first ), true ) . PHP_EOL;
}

/* 7. Invalid inputs rejected. */
$bad = $av->create( array( 'client_name' => 'X', 'date' => '2020-13-45', 'start' => '99:99' ) );
echo 'invalid input: ' . ( is_wp_error( $bad ) ? 'REJECTED ' . $bad->get_error_code() : 'ACCEPTED (BUG)' ) . PHP_EOL;

/* Cleanup test appointments. */
foreach ( array( $r1, $r3 ) as $id ) {
    if ( ! is_wp_error( $id ) && $id ) { wp_delete_post( $id, true ); }
}

restore_current_blog();
echo 'DONE' . PHP_EOL;
