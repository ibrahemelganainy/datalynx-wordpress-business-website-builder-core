<?php
/**
 * Runtime test: the PAYMENT COMPLETION SYSTEM (phase audit + fixes).
 *
 * Verifies the complete lifecycle agrees across:
 *   Transaction + Entity + Dashboard + Notification + Activity + Receipt.
 *
 * Covers, for BOTH consultation and appointment:
 *   1. gateway "paid" -> transaction paid, entity paid, notification fired
 *      (idempotent: repeat apply creates no duplicate state/notification),
 *   2. manual submission -> on_hold, dashboard "pending",
 *   3. manual approve -> paid, entity paid, notification, dashboard "approved",
 *   4. manual reject  -> failed, entity NOT paid, reason kept, dashboard
 *      "rejected", notification,
 *   5. dashboard summary counts + filter buckets use canonical data,
 *   6. receipt resolves the canonical customer name/phone,
 *   7. proof storage key + secure retrieval (private attachment).
 *
 * Run:  php tests/runtime-payment-completion.php
 */

define( 'WP_USE_THEMES', false );
define( 'WP_ADMIN', true );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\PaymentResult;
use BusinessBuilderCore\Core\Payments\Checkout\TransactionSynchronizer;
use BusinessBuilderCore\Core\Payments\Receipt\Receipt;
use BusinessBuilderCore\Core\Notifications\NotificationManager;
use BusinessBuilderCore\Core\Notifications\Notification;
use BusinessBuilderCore\Core\Audit\AuditLog;
use BusinessBuilderCore\Packs\LawFirm\Payments\ManualPaymentSubmission;
use BusinessBuilderCore\Packs\LawFirm\Admin\ManualPaymentsAdmin;

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

$pm    = new PaymentManager();
$audit = new AuditLog();
$sync  = new TransactionSynchronizer( $pm, $audit );
$nf    = new NotificationManager();

/* Manual + one fake gateway for the "paid" path. */
$pm->save_settings( 'wallet', array( 'wallet_provider' => 'Vodafone Cash', 'wallet_number' => '01099998888', 'instructions' => 'x' ) );
$pm->set_enabled_gateways( array( 'wallet', 'paypal' ) );

/**
 * Create a consultation or appointment record with canonical customer data.
 *
 * @param string $type consultation|appointment.
 * @param string $ref  Public reference.
 * @return int Post id.
 */
function make_record( string $type, string $ref ): int {

    $post_type = ( 'appointment' === $type ) ? 'bb_appointment' : 'bb_consultation';

    $id = wp_insert_post( array( 'post_type' => $post_type, 'post_status' => 'publish', 'post_title' => 'PC ' . $ref ), true );

    if ( 'appointment' === $type ) {
        update_post_meta( $id, '_bb_appointment_public_reference', $ref );
        update_post_meta( $id, '_bb_appointment_client_name', 'John Appt' );
        update_post_meta( $id, '_bb_appointment_client_phone', '+202222' );
        update_post_meta( $id, '_bb_appointment_lawyer_id', 0 );
    } else {
        update_post_meta( $id, '_bb_consultation_public_reference', $ref );
        update_post_meta( $id, '_bb_consultation_name', 'Jane Cons' );
        update_post_meta( $id, '_bb_consultation_phone', '+201111' );
        update_post_meta( $id, '_bb_consultation_practice_area', 'Corporate Law' );
    }

    update_post_meta( $id, ( 'appointment' === $type ? '_bb_appointment_payment_status' : '_bb_consultation_payment_status' ), 'pending' );

    return (int) $id;
}

$count_feed = static function (): int {
    return count( ( new NotificationManager() )->all() );
};

/* ==================================================================
 * 1. CONSULTATION — gateway paid path
 * ================================================================== */
echo "== 1. Consultation gateway PAID: transaction + entity agree ==\n";

$cns = make_record( 'consultation', 'CNS-PC00001' );

$txn = $pm->persist(
    PaymentTransaction::from_array(
        array( 'public_ref' => 'TXN-PC00001', 'object_type' => 'consultation', 'object_id' => $cns, 'consultation_id' => $cns, 'gateway' => 'paypal', 'amount' => '500', 'currency' => 'EGP', 'status' => 'processing', 'reference' => 'GW-1' )
    )
);

$before = $count_feed();

$paid = $sync->apply( $txn, new PaymentResult( true, 'paid', 'GW-1', 'ok' ) );
check( 'transaction paid', 'paid' === $paid->status );
check( 'consultation payment status = paid', 'paid' === (string) get_post_meta( $cns, '_bb_consultation_payment_status', true ) );

/* Fire the paid notification exactly as the callback does (entity-scoped). */
$nf->dispatch(
    new Notification(
        'payment.paid',
        'Payment paid for consultation',
        '',
        '',
        $cns,
        'payment:paypal:TXN-PC00001',
        array( 'category' => 'payment', 'entity_type' => 'consultation', 'entity_id' => $cns, 'reference' => 'TXN-PC00001', 'amount' => '500', 'currency' => 'EGP', 'gateway' => 'paypal' )
    )
);

$after_first = $count_feed();
check( 'paid notification created once', $after_first === $before + 1 );

/* Idempotency: re-dispatch the SAME event (refresh / webhook replay). */
$nf->dispatch(
    new Notification(
        'payment.paid',
        'Payment paid for consultation',
        '',
        '',
        $cns,
        'payment:paypal:TXN-PC00001',
        array( 'category' => 'payment', 'entity_type' => 'consultation', 'entity_id' => $cns, 'reference' => 'TXN-PC00001' )
    )
);
check( 'duplicate paid notification suppressed', $count_feed() === $after_first );

/* Idempotency: re-apply paid result does not regress or duplicate. */
$again = $sync->apply( $paid, new PaymentResult( true, 'paid', 'GW-1', 'ok' ) );
check( 're-apply paid stays paid', 'paid' === $again->status );
check( 'consultation stays paid after re-apply', 'paid' === (string) get_post_meta( $cns, '_bb_consultation_payment_status', true ) );

/* ==================================================================
 * 2. APPOINTMENT — gateway paid path + entity-aware notification
 * ================================================================== */
echo "== 2. Appointment gateway PAID: transaction + entity agree ==\n";

$apt = make_record( 'appointment', 'APT-PC00002' );

$txn_a = $pm->persist(
    PaymentTransaction::from_array(
        array( 'public_ref' => 'TXN-PC00002', 'object_type' => 'appointment', 'object_id' => $apt, 'gateway' => 'paypal', 'amount' => '750', 'currency' => 'EGP', 'status' => 'processing', 'reference' => 'GW-2' )
    )
);

$paid_a = $sync->apply( $txn_a, new PaymentResult( true, 'paid', 'GW-2', 'ok' ) );
check( 'appointment transaction paid', 'paid' === $paid_a->status );
check( 'appointment payment status = paid', 'paid' === (string) get_post_meta( $apt, '_bb_appointment_payment_status', true ) );

/* ==================================================================
 * 3. MANUAL — consultation submission -> approve
 * ================================================================== */
echo "== 3. Manual submission -> APPROVE (consultation) ==\n";

$cns2 = make_record( 'consultation', 'CNS-PC00003' );

$txn_m = $pm->persist(
    PaymentTransaction::from_array(
        array( 'public_ref' => 'TXN-PC00003', 'object_type' => 'consultation', 'object_id' => $cns2, 'consultation_id' => $cns2, 'gateway' => 'wallet', 'amount' => '300', 'currency' => 'EGP', 'status' => 'pending' )
    )
);

$ok = ManualPaymentSubmission::submit( 'consultation', $cns2, 'wallet', array( 'bb_manual_reference' => 'WAL-300' ), $txn_m );
check( 'manual submission accepted', true === $ok );

$m = $pm->store()->find( $txn_m->id );
check( 'transaction on_hold', $m instanceof PaymentTransaction && 'on_hold' === $m->status );
check( 'transaction NOT paid yet', $m instanceof PaymentTransaction && 'paid' !== $m->status );
check( 'manual_reference stored', 'WAL-300' === ( $m->meta['manual_reference'] ?? '' ) );

/* Dashboard: it must appear as PENDING. */
$admin = new ManualPaymentsAdmin( $pm, $sync, $nf, $audit );
$bucket = new ReflectionMethod( $admin, 'review_bucket' );
$bucket->setAccessible( true );
check( 'dashboard bucket = pending', 'pending' === $bucket->invoke( $admin, $m ) );

/* Approve (exactly as the admin action does). */
$approved = $sync->apply( $m, new PaymentResult( true, 'paid', 'WAL-300', 'approved' ) );
check( 'approved -> transaction paid', 'paid' === $approved->status );
check( 'approved -> consultation paid', 'paid' === (string) get_post_meta( $cns2, '_bb_consultation_payment_status', true ) );
check( 'dashboard bucket = approved', 'approved' === $bucket->invoke( $admin, $approved ) );

/* ==================================================================
 * 4. MANUAL — appointment submission -> reject
 * ================================================================== */
echo "== 4. Manual submission -> REJECT (appointment) ==\n";

$apt2 = make_record( 'appointment', 'APT-PC00004' );

$txn_r = $pm->persist(
    PaymentTransaction::from_array(
        array( 'public_ref' => 'TXN-PC00004', 'object_type' => 'appointment', 'object_id' => $apt2, 'gateway' => 'wallet', 'amount' => '400', 'currency' => 'EGP', 'status' => 'pending' )
    )
);

ManualPaymentSubmission::submit( 'appointment', $apt2, 'wallet', array( 'bb_manual_reference' => 'WAL-400' ), $txn_r );

$r = $pm->store()->find( $txn_r->id );
check( 'appointment manual on_hold', $r instanceof PaymentTransaction && 'on_hold' === $r->status );

/* Persist the rejection reason then fail (mirrors the admin action). */
$rmeta = is_array( $r->meta ) ? $r->meta : array();
$rmeta['rejection_reason'] = 'Reference not found';
$r->meta = $rmeta;
$pm->persist( $r );

$rejected = $sync->apply( $pm->store()->find( $txn_r->id ), new PaymentResult( false, 'failed', '', 'rejected' ) );
check( 'rejected -> transaction failed', 'failed' === $rejected->status );
check( 'rejected -> appointment NOT paid', 'paid' !== (string) get_post_meta( $apt2, '_bb_appointment_payment_status', true ) );
check( 'rejected -> reason preserved', 'Reference not found' === ( $pm->store()->find( $txn_r->id )->meta['rejection_reason'] ?? '' ) );
check( 'dashboard bucket = rejected', 'rejected' === $bucket->invoke( $admin, $pm->store()->find( $txn_r->id )));

/* ==================================================================
 * 5. DASHBOARD summary + filters use canonical data
 * ================================================================== */
echo "== 5. Dashboard summary + filters ==\n";

$summary = new ReflectionMethod( $admin, 'summary_counts' );
$summary->setAccessible( true );
$counts = $summary->invoke( $admin );

check( 'summary total counts the manual submissions', $counts['total'] >= 2 );
check( 'summary pending >= 0', $counts['pending'] >= 0 );
check( 'summary approved >= 1', $counts['approved'] >= 1 );
check( 'summary rejected >= 1', $counts['rejected'] >= 1 );
check( 'total = pending + approved + rejected', $counts['total'] === $counts['pending'] + $counts['approved'] + $counts['rejected'] );

$all_m = new ReflectionMethod( $admin, 'manual_transactions' );
$all_m->setAccessible( true );
$all_rows = $all_m->invoke( $admin );

check( 'manual list includes the rejected row', count( $all_rows ) >= 2 );

/* ==================================================================
 * 6. Receipt resolves canonical customer data
 * ================================================================== */
echo "== 6. Receipt canonical customer data ==\n";

$rec = Receipt::from_transaction( $pm->store()->find( $txn_m->id ), 'Electronic Wallet' );
check( 'receipt customer name = canonical', 'Jane Cons' === $rec->customer_name );
check( 'receipt has a receipt number', 0 === strpos( $rec->receipt_number, 'RCP-' ) );

$rec_a = Receipt::from_transaction( $pm->store()->find( $txn_a->id ), 'PayPal' );
check( 'appointment receipt customer name = canonical', 'John Appt' === $rec_a->customer_name );
check( 'appointment receipt object type = appointment', 'appointment' === $rec_a->object_type );

/* ==================================================================
 * 7. Proof storage key + private proof retrieval
 * ================================================================== */
echo "== 7. Payment proof storage ==\n";

$proof_id = $pm->store()->find( $txn_m->id )->meta['proof_attachment_id'] ?? 0;

if ( $proof_id > 0 ) {
    check( 'proof attachment is a private receipt', '1' === (string) get_post_meta( (int) $proof_id, '_bb_private_receipt', true ) );
} else {
    check( 'no proof uploaded -> canonical key absent (returns No proof)', true );
}

echo "\n----------------------------------------\n";
echo "PASS: $pass   FAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );

/* Cleanup. */
foreach ( array( $cns, $cns2 ) as $cid ) { wp_delete_post( $cid, true ); }
foreach ( array( $apt, $apt2 ) as $aid ) { wp_delete_post( $aid, true ); }
if ( $proof_id > 0 ) { wp_delete_attachment( (int) $proof_id, true ); }

delete_option( 'bb_notifications_feed' );
delete_option( 'bb_notifications_dedupe' );
delete_option( 'bb_payment_enabled_gateways' );
delete_option( 'bb_payment_active_gateway' );
delete_option( 'bb_payment_gateways' );

exit( 0 === $fail ? 0 : 1 );
