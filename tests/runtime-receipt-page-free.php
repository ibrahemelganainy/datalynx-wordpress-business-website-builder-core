<?php
/**
 * Runtime test: ReceiptPage resolves a FREE invoice by OBJECT reference and
 * never mislabels a paid booking as free.
 *
 * Run: php tests/runtime-receipt-page-free.php
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Packs\LawFirm\Appointments\Appointment;
use BusinessBuilderCore\Packs\LawFirm\Appointments\AppointmentMeta;
use BusinessBuilderCore\Core\Payments\Receipt\ReceiptPage;
use BusinessBuilderCore\Core\Payments\Receipt\ReceiptRenderer;
use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Audit\AuditLog;
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

function make_appt( string $pay_status ): array {
    $ref = Reference::appointment();
    $id  = wp_insert_post( array( 'post_type' => 'bb_appointment', 'post_status' => 'publish', 'post_title' => 'RP TEST' ), true );
    update_post_meta( $id, AppointmentMeta::key( 'public_reference' ), $ref );
    update_post_meta( $id, AppointmentMeta::key( 'client_name' ), 'RP Client' );
    update_post_meta( $id, AppointmentMeta::key( 'payment_status' ), $pay_status );
    return array( (int) $id, $ref );
}

$page = new ReceiptPage( new PaymentManager(), new ReceiptRenderer(), new AuditLog() );

echo "== free object reference -> free invoice ==\n";
list( $free_id, $free_ref ) = make_appt( 'not_required' );
$free_html = $page->render_for_reference( $free_ref );
check( 'renders a receipt', false !== strpos( $free_html, 'bb-receipt' ) );
check( 'is free', false !== strpos( $free_html, 'is-free' ) );
check( 'prints the object reference', false !== strpos( $free_html, $free_ref ) );

echo "== a payment-required object is NOT shown as free ==\n";
list( $paid_id, $paid_ref ) = make_appt( 'pending' );
$paid_html = $page->render_for_reference( $paid_ref );
check( 'not found (no transaction yet)', false === strpos( $paid_html, 'is-free' ) );
check( 'shows the not-found notice', false !== strpos( $paid_html, 'No receipt was found' ) );

wp_delete_post( $free_id, true );
wp_delete_post( $paid_id, true );

echo "\nRESULT: $pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
