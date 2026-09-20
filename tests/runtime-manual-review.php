<?php
/**
 * Runtime test: the complete MANUAL payment lifecycle.
 *
 *   select manual method -> submit (reference) -> transaction on_hold +
 *   reference persisted on the TRANSACTION meta -> admin approve -> paid
 *   -> entity paid -> receipt available; and a separate reject -> failed.
 *
 * Run:  php tests/runtime-manual-review.php
 */

define( 'WP_USE_THEMES', false );
define( 'WP_ADMIN', true );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\PaymentResult;
use BusinessBuilderCore\Core\Payments\Checkout\TransactionSynchronizer;
use BusinessBuilderCore\Core\Payments\Receipt\Receipt;
use BusinessBuilderCore\Core\Payments\Receipt\ReceiptRenderer;
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

$pm    = new PaymentManager();
$audit = new \BusinessBuilderCore\Core\Audit\AuditLog();
$sync  = new TransactionSynchronizer( $pm, $audit );

/* Configure the manual gateway with real values. */
$pm->save_settings( 'wallet', array( 'wallet_provider' => 'Vodafone Cash', 'wallet_number' => '01099998888', 'instructions' => 'x' ) );
$pm->set_enabled_gateways( array( 'wallet' ) );

/* A consultation to pay for. */
$cns = wp_insert_post( array( 'post_type' => 'bb_consultation', 'post_status' => 'publish', 'post_title' => 'Manual Review Test' ), true );
update_post_meta( $cns, '_bb_consultation_public_reference', 'CNS-REVIEWYYYY' );
update_post_meta( $cns, '_bb_consultation_payment_status', 'pending' );

echo "== 1. Checkout creates a pending transaction ==\n";

$txn = $pm->persist(
    PaymentTransaction::from_array(
        array(
            'public_ref'  => 'TXN-MANUALYYYY',
            'object_type' => 'consultation',
            'object_id'   => $cns,
            'gateway'     => 'wallet',
            'amount'      => '750.00',
            'currency'    => 'EGP',
            'status'      => 'pending',
        )
    )
);

check( 'transaction persisted', $txn->id > 0 );

echo "== 2. Manual submission stores the reference + proof on the TRANSACTION ==\n";

$ok = ManualPaymentSubmission::submit( 'consultation', $cns, 'wallet', array( 'bb_manual_reference' => 'TRX-9988 / ok!' ), $txn );
check( 'submission accepted', true === $ok );

$stored = $pm->store()->find( $txn->id );
check( 'transaction status = on_hold', $stored instanceof PaymentTransaction && 'on_hold' === $stored->status );
check( 'reference stored on transaction meta', $stored instanceof PaymentTransaction && 'TRX-9988 / ok' === ( $stored->meta['manual_reference'] ?? '' ) );
check( 'gateway stored on transaction meta', $stored instanceof PaymentTransaction && 'wallet' === ( $stored->meta['manual_gateway'] ?? '' ) );
check( 'transaction NOT marked paid', $stored instanceof PaymentTransaction && 'paid' !== $stored->status );

echo "== 3. Receipt shows Pending Manual Verification + the manual reference ==\n";

$r = new ReceiptRenderer();
$h = $r->render( Receipt::from_transaction( $stored, 'Electronic Wallet' ) );
check( 'receipt says Pending Manual Verification', false !== strpos( $h, 'Pending Manual Verification' ) );
check( 'receipt carries the manual transaction reference', false !== strpos( $h, 'TRX-9988 / ok' ) );
check( 'receipt carries the payment reference', false !== strpos( $h, 'TXN-MANUALYYYY' ) );

echo "== 4. Admin APPROVE -> transaction paid + consultation paid ==\n";

$approve = new PaymentResult( true, 'paid', 'TRX-9988 / ok', 'approved' );
$paid    = $sync->apply( $stored, $approve );
check( 'transaction paid after approve', 'paid' === $paid->status );
check( 'consultation payment_status = paid', 'paid' === (string) get_post_meta( $cns, '_bb_consultation_payment_status', true ) );

$h2 = $r->render( Receipt::from_transaction( $paid, 'Electronic Wallet' ) );
check( 'receipt now shows Paid', false !== strpos( $h2, '>Paid<' ) || false !== strpos( $h2, 'Paid' ) );

echo "== 5. REJECT path on a second transaction -> failed ==\n";

$cns2 = wp_insert_post( array( 'post_type' => 'bb_consultation', 'post_status' => 'publish', 'post_title' => 'Reject Test' ), true );
update_post_meta( $cns2, '_bb_consultation_public_reference', 'CNS-REJECTZZ' );

$txn2 = $pm->persist(
    PaymentTransaction::from_array(
        array( 'public_ref' => 'TXN-MANUALZZ', 'object_type' => 'consultation', 'object_id' => $cns2, 'gateway' => 'wallet', 'amount' => '100', 'currency' => 'EGP', 'status' => 'pending' )
    )
);

ManualPaymentSubmission::submit( 'consultation', $cns2, 'wallet', array( 'bb_manual_reference' => 'BADREF' ), $txn2 );

$stored2 = $pm->store()->find( $txn2->id );
check( 'second transaction on_hold', 'on_hold' === $stored2->status );

/* Persist rejection reason then fail the transaction (mirrors the admin). */
$meta2 = $stored2->meta;
$meta2['rejection_reason'] = 'Reference not found';
$stored2->meta = $meta2;
$pm->persist( $stored2 );

$rejected = $sync->apply( $stored2, new PaymentResult( false, 'failed', '', 'rejected' ) );
check( 'transaction failed after reject', 'failed' === $rejected->status );
check( 'consultation payment_status not paid', 'paid' !== (string) get_post_meta( $cns2, '_bb_consultation_payment_status', true ) );

$reloaded = $pm->store()->find( $txn2->id );
check( 'rejection reason persisted', 'Reference not found' === ( $reloaded->meta['rejection_reason'] ?? '' ) );

echo "== 6. Inline receipt (JS modal + AJAX endpoint) serves the receipt ==\n";

$cli_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
delete_transient( 'bb_receipt_rl_' . md5( $cli_ip . '|' . get_current_blog_id() ) );

$bb_receipt_ref = 'TXN-MANUALYYYY';

/* 6a. The inline receipt is served by ReceiptPage (any page, secure). */
$receipt_page = new \BusinessBuilderCore\Core\Payments\Receipt\ReceiptPage(
    $pm,
    new \BusinessBuilderCore\Core\Payments\Receipt\ReceiptRenderer(),
    $audit
);

$inline = $receipt_page->render_for_reference( $bb_receipt_ref );

check( 'inline receipt renders on any page', false !== strpos( $inline, 'bb-payment-receipt' ) );
check( 'inline receipt shows the doc title', false !== strpos( $inline, 'PAYMENT RECEIPT' ) );
check( 'inline receipt provides a Print button', false !== strpos( $inline, 'data-bb-receipt-print' ) );
check( 'inline receipt provides a Save button', false !== strpos( $inline, 'data-bb-receipt-download' ) );
check( 'inline receipt carries the transaction reference', false !== strpos( $inline, $bb_receipt_ref ) );

/* 6b. The front-end modal script wires the opener + the AJAX action. */
$modal_js = (string) file_get_contents( BB_CORE_PATH . 'assets/js/frontend/receipt-modal.js' );

check( 'modal JS opens on [data-bb-receipt-open]', false !== strpos( $modal_js, 'data-bb-receipt-open' ) );
check( 'modal JS fetches via bb_receipt_inline', false !== strpos( $modal_js, 'bb_receipt_inline' ) );
check( 'modal JS re-binds Print', false !== strpos( $modal_js, 'data-bb-receipt-print' ) );
check( 'modal JS re-binds Save', false !== strpos( $modal_js, 'data-bb-receipt-download' ) );

/* 6c. The lookup result opens the receipt INLINE (no navigation). */
$lookup_js = (string) file_get_contents( BB_CORE_PATH . 'assets/js/frontend/status-lookup.js' );

check( 'lookup opens the receipt inline', false !== strpos( $lookup_js, 'data-bb-receipt-open' ) && false !== strpos( $lookup_js, 'data-bb-receipt-ref' ) );
check( 'lookup no longer navigates to ?bb_ref=', false === strpos( $lookup_js, '?bb_ref=' ) );

/* 6d. The receipt route URL is same-site and carries the reference. */
$route_url  = \BusinessBuilderCore\Packs\LawFirm\Frontend\ReceiptRoute::url( $bb_receipt_ref );
$route_home = home_url( '/' );
check( 'receipt route URL points at the current site', 0 === strpos( $route_url, $route_home ) );
check( 'receipt route URL carries the reference', false !== strpos( $route_url, $bb_receipt_ref ) );
echo "\n----------------------------------------\n";
echo "PASS: $pass   FAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );

/* Cleanup */
wp_delete_post( $cns, true );
wp_delete_post( $cns2, true );
delete_option( 'bb_payment_enabled_gateways' );
delete_option( 'bb_payment_active_gateway' );
delete_option( 'bb_payment_gateways' );

exit( 0 === $fail ? 0 : 1 );
