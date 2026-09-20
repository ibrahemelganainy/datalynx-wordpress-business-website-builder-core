<?php
/**
 * Runtime test: manual payment submission + notifications + receipt states.
 *
 * Verifies:
 *   1. ManualPaymentSubmission records the reference and sets on_hold
 *      (NEVER paid), and rejects non-manual / invalid input.
 *   2. NotificationManager stores rich data, filters by category, searches,
 *      counts unread, and marks read/all-read.
 *   3. The receipt renders "Pending Manual Verification" for on_hold and
 *      "Paid" for a settled payment.
 *
 * Run:  php tests/runtime-manual-notifications.php
 */

define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\Receipt\Receipt;
use BusinessBuilderCore\Core\Payments\Receipt\ReceiptRenderer;
use BusinessBuilderCore\Core\Notifications\NotificationManager;
use BusinessBuilderCore\Core\Notifications\Notification;
use BusinessBuilderCore\Packs\LawFirm\Payments\ManualPaymentSubmission;

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

echo "== 1. Manual submission: wallet ==========\n";

$pm = new PaymentManager();
$pm->save_settings( 'wallet', array( 'wallet_provider' => 'Vodafone Cash', 'wallet_number' => '01012345678', 'instructions' => 'x' ) );
$pm->set_enabled_gateways( array( 'wallet', 'bank_transfer' ) );

$cns = wp_insert_post( array( 'post_type' => 'bb_consultation', 'post_status' => 'publish', 'post_title' => 'Manual Sub Test' ), true );
update_post_meta( $cns, '_bb_consultation_public_reference', 'CNS-MANUAL0001' );

$ok = ManualPaymentSubmission::submit( 'consultation', $cns, 'wallet', array( 'bb_manual_reference' => 'TRX 998877 / ok!' ) );
check( 'manual submission recorded', true === $ok );
check( 'reference stored (sanitized)', 'TRX 998877 / ok' === (string) get_post_meta( $cns, '_bb_consultation_manual_reference', true ) );
check( 'payment status = on_hold', 'on_hold' === (string) get_post_meta( $cns, '_bb_consultation_payment_status', true ) );
check( 'NOT marked paid', 'paid' !== (string) get_post_meta( $cns, '_bb_consultation_payment_status', true ) );

echo "== 2. Manual submission rejects bad input ==========\n";

check( 'empty reference rejected', false === ManualPaymentSubmission::submit( 'consultation', $cns, 'wallet', array( 'bb_manual_reference' => '   ' ) ));
check( 'unknown gateway rejected', false === ManualPaymentSubmission::submit( 'consultation', $cns, 'nope', array( 'bb_manual_reference' => 'X1' ) ));
check( 'non-manual gateway rejected', false === ManualPaymentSubmission::submit( 'consultation', $cns, 'stripe', array( 'bb_manual_reference' => 'X1' ) ));
check( 'bad object id rejected', false === ManualPaymentSubmission::submit( 'consultation', 0, 'wallet', array( 'bb_manual_reference' => 'X1' ) ));

echo "== 3. Notifications: store + query + read ==========\n";

$nm = new NotificationManager();

$nm->dispatch(
    new Notification(
        'consultation.new',
        'New consultation A',
        'Family',
        '',
        $cns,
        'test:new:a:' . $cns,
        array( 'category' => 'consultation', 'entity_type' => 'consultation', 'reference' => 'CNS-MANUAL0001', 'customer' => 'Amina' )
    )
);
$nm->dispatch(
    new Notification(
        'payment.manual_submitted',
        'Manual payment B',
        'Vodafone',
        '',
        $cns,
        'test:man:b:' . $cns,
        array( 'category' => 'payment', 'entity_type' => 'consultation', 'reference' => 'TXN-ABC123', 'gateway' => 'wallet' )
    )
);
$nm->dispatch(
    new Notification(
        'appointment.created',
        'Appointment C',
        '2026',
        '',
        0,
        'test:apt:c:' . $cns,
        array( 'category' => 'appointment' )
    )
);

$all = $nm->query( 'all', '', 0, 100 );
$payments = $nm->query( 'payment', '', 0, 100 );
$found_ref = $nm->query( 'all', 'TXN-ABC123', 0, 100 );

check( 'notifications stored', count( $all ) >= 3 );
check( 'category filter (payment)', count( $payments ) >= 1 );
check( 'search by reference finds item', count( $found_ref ) >= 1 );
check( 'unread count > 0', $nm->unread_count() > 0 );

/* Mark one read: unread count must DECREASE. */
$before = $nm->unread_count();
$first = $all[0];
$nm->mark_read( (string) $first['id'] );
check( 'unread decreases after mark_read', $nm->unread_count() < $before );

$nm->mark_all_read();
check( 'mark_all_read yields 0 unread', 0 === $nm->unread_count() );

echo "== 4. Receipt status correctness ==========\n";

$r = new ReceiptRenderer();

$txn_hold = PaymentTransaction::from_array(
    array( 'public_ref' => 'TXN-HOLDX', 'object_type' => 'consultation', 'object_id' => $cns, 'gateway' => 'wallet', 'amount' => '500', 'currency' => 'EGP', 'status' => 'on_hold', 'meta' => array( 'name' => 'Amina' ) )
);
$h_hold = $r->render( Receipt::from_transaction( $txn_hold, 'Electronic Wallet' ) );
check( 'manual receipt shows Pending Manual Verification', false !== strpos( $h_hold, 'Pending Manual Verification' ) );
check( 'manual receipt not labelled Paid', false === strpos( $h_hold, '>Paid<' ) );

$txn_paid = PaymentTransaction::from_array(
    array( 'public_ref' => 'TXN-PAIDX', 'object_type' => 'consultation', 'object_id' => $cns, 'gateway' => 'paymob', 'amount' => '500', 'currency' => 'EGP', 'status' => 'paid', 'meta' => array( 'name' => 'Amina' ) )
);
$h_paid = $r->render( Receipt::from_transaction( $txn_paid, 'Paymob' ) );
check( 'paid receipt labelled Paid', false !== strpos( $h_paid, 'Paid' ) );

echo "\n----------------------------------------\n";
echo "PASS: $pass   FAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );

/* Cleanup */
wp_delete_post( $cns, true );
delete_option( 'bb_payment_enabled_gateways' );
delete_option( 'bb_payment_active_gateway' );
delete_option( 'bb_payment_gateways' );
delete_option( 'bb_notifications_feed' );
delete_option( 'bb_notifications_dedupe' );

exit( 0 === $fail ? 0 : 1 );
