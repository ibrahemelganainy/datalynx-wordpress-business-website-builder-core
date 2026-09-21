<?php
/**
 * Runtime test: a FREE consultation/appointment renders a full invoice
 * clearly marked as free.
 *
 * Run: php tests/runtime-free-invoice.php
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Packs\LawFirm\Appointments\Appointment;
use BusinessBuilderCore\Packs\LawFirm\Appointments\AppointmentMeta;
use BusinessBuilderCore\Core\Payments\Receipt\Receipt;
use BusinessBuilderCore\Core\Payments\Receipt\ReceiptRenderer;
use BusinessBuilderCore\Core\Payments\Transaction\Reference;

if ( function_exists( 'switch_to_blog' ) ) {
    switch_to_blog( 2 );
}

( new Appointment() )->register_post_type();

$pass = 0;
$fail = 0;

function check( string $label, bool $cond ): void {
    global $pass, $fail;
    if ( $cond ) { $pass++; echo "  OK   $label\n"; }
    else { $fail++; echo "  FAIL $label\n"; }
}

/* Create a free appointment (payment_status = not_required). */
$ref = Reference::appointment();
$id  = wp_insert_post(
    array(
        'post_type'   => 'bb_appointment',
        'post_status' => 'publish',
        'post_title'  => 'FREE TEST',
    ),
    true
);

if ( is_wp_error( $id ) ) {
    echo "SKIP: could not create bb_appointment.\n";
    exit( 0 );
}

update_post_meta( $id, AppointmentMeta::key( 'public_reference' ), $ref );
update_post_meta( $id, AppointmentMeta::key( 'client_name' ), 'Free Client' );
update_post_meta( $id, AppointmentMeta::key( 'client_phone' ), '0100000' );
$free_day = gmdate( 'Y-m-d', strtotime( '+3 days' ) );
update_post_meta( $id, AppointmentMeta::key( 'date' ), $free_day );
update_post_meta( $id, AppointmentMeta::key( 'start' ), '10:00' );
update_post_meta( $id, AppointmentMeta::key( 'end' ), '11:00' );
update_post_meta( $id, AppointmentMeta::key( 'payment_status' ), 'not_required' );

echo "== Receipt::from_object builds a free invoice ==\n";
$receipt = Receipt::from_object( (int) $id, 'appointment' );
check( 'receipt built', $receipt instanceof Receipt );
check( 'marked free', $receipt->is_free );
check( 'not marked paid', ! $receipt->is_paid );
check( 'reference is the object ref', $ref === $receipt->reference );
check( 'customer name carried', 'Free Client' === $receipt->customer_name );
check( 'no gateway reference', '' === $receipt->gateway_reference );

echo "== renderer marks it as free, never as paid ==\n";
$html = ( new ReceiptRenderer() )->render( $receipt );
check( 'has is-free banner class', false !== strpos( $html, 'is-free' ) );
check( 'shows FREE', false !== strpos( $html, 'FREE' ) );
check( 'says free of charge', false !== strpos( $html, 'Free of Charge' ) );
check( 'states no payment required', false !== strpos( $html, 'no payment required' ) );
check( 'titled INVOICE', false !== strpos( $html, 'INVOICE' ) );
check( 'does NOT claim payment received', false === stripos( $html, 'Payment received' ) );
check( 'no PHP error', false === stripos( $html, 'Fatal error' ) );

wp_delete_post( (int) $id, true );

echo "\nRESULT: $pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
