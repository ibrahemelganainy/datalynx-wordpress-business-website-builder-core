<?php
/*
 * Real WP runtime test: Phase F (checkout, transactions, callbacks,
 * receipts, status page).
 *
 * Verifies:
 *   - payment CPT registers + transaction store round-trips
 *   - secure non-sequential references
 *   - status machine (legal/illegal transitions)
 *   - checkout creates a persisted pending transaction routed to a gateway
 *   - synchronizer updates transaction + related object payment meta
 *   - receipt excludes secrets and renders only for paid transactions
 *   - status page resists enumeration (uniform not-found)
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\PaymentResult;
use BusinessBuilderCore\Core\Payments\Checkout\CheckoutRequest;
use BusinessBuilderCore\Core\Payments\Checkout\PaymentCheckout;
use BusinessBuilderCore\Core\Payments\Checkout\TransactionSynchronizer;
use BusinessBuilderCore\Core\Payments\Transaction\Reference;
use BusinessBuilderCore\Core\Payments\Transaction\TransactionStatus;
use BusinessBuilderCore\Core\Payments\Transaction\PaymentTransactionPostType;
use BusinessBuilderCore\Core\Payments\Receipt\Receipt;
use BusinessBuilderCore\Core\Payments\Receipt\ReceiptRenderer;
use BusinessBuilderCore\Core\Payments\Receipt\ReceiptPage;
use BusinessBuilderCore\Core\Audit\AuditLog;
use BusinessBuilderCore\Packs\LawFirm\Frontend\StatusPage;

switch_to_blog( 2 );

foreach ( array( 'bb_payment_gateways', 'bb_payment_active_gateway', 'bb_payment_enabled_gateways', 'bb_payment_transactions' ) as $opt ) {
    delete_option( $opt );
}

$audit = new AuditLog();
$pm    = new PaymentManager();

/* --- References ---------------------------------------------------- */
$ref = Reference::transaction();
echo 'txn ref: ' . $ref . ' [' . ( preg_match( '/^TXN-[A-Z0-9]{10}$/', $ref ) ? 'OK' : 'FAIL' ) . ']' . PHP_EOL;
echo 'ref valid: ' . var_export( Reference::is_valid( $ref ), true ) . PHP_EOL;
echo 'ref invalid shape rejected: ' . var_export( Reference::is_valid( '12345' ), true ) . PHP_EOL;

/* --- CPT registered ------------------------------------------------ */
( new PaymentTransactionPostType() )->register_post_type();
echo 'cpt exists: ' . var_export( post_type_exists( 'bb_payment' ), true ) . PHP_EOL;

/* --- Status machine ------------------------------------------------ */
echo 'pending->paid ok: ' . var_export( TransactionStatus::can_transition_to( 'pending', 'paid' ), true ) . PHP_EOL;
echo 'paid->pending blocked: ' . var_export( TransactionStatus::can_transition_to( 'paid', 'pending' ), true ) . PHP_EOL;
echo 'paid is final: ' . var_export( TransactionStatus::is_final( 'paid' ), true ) . PHP_EOL;

/* --- Store round-trip --------------------------------------------- */
$txn = $pm->persist( PaymentTransaction::from_array( array(
    'public_ref'  => Reference::transaction(),
    'object_type' => 'consultation',
    'object_id'   => 4242,
    'gateway'     => 'bank_transfer',
    'amount'      => '250.00',
    'currency'    => 'EGP',
    'status'      => 'pending',
) ) );
echo 'stored id > 0: ' . var_export( $txn->id > 0, true ) . PHP_EOL;

$again = $pm->store()->find_by_public_ref( $txn->public_ref );
echo 'find_by_public_ref round-trip: ' . ( $again ? $again->status : '(null)' ) . PHP_EOL;
$mirrored = $pm->find_by_public_ref( $txn->public_ref );
echo 'option store mirrored: ' . ( $mirrored ? 'yes' : 'no' ) . PHP_EOL;

/* --- Checkout (bank transfer = manual) ----------------------------- */
$pm->set_enabled_gateways( array( 'bank_transfer' ) );
$pm->save_settings( 'bank_transfer', array( 'bank_name' => 'Test Bank', 'account_name' => 'Firm', 'account_number' => '123' ) );

$checkout = new PaymentCheckout( $pm, $audit );
$request  = CheckoutRequest::from_array( array(
    'object_type' => 'consultation',
    'object_id'   => 777,
    'gateway'     => 'bank_transfer',
    'amount'      => '150',
    'currency'    => 'EGP',
    'label'       => 'Consultation #777',
    'email'       => 'client@example.com',
) );
$result = $checkout->start( $request );
echo 'checkout type (manual): ' . $result['type'] . PHP_EOL;
echo 'checkout transaction status: ' . $result['transaction']->status . PHP_EOL;

/* --- Checkout rejects unavailable gateway -------------------------- */
$bad = $checkout->start( CheckoutRequest::from_array( array(
    'object_type' => 'consultation',
    'object_id'   => 777,
    'gateway'     => 'stripe',
    'amount'      => '150',
    'currency'    => 'EGP',
) ) );
echo 'unavailable gateway => error: ' . var_export( 'error' === $bad['type'], true ) . PHP_EOL;

/* --- Request validation -------------------------------------------- */
$invalid = CheckoutRequest::from_array( array( 'object_id' => 0, 'gateway' => '', 'amount' => 'abc', 'currency' => '' ) );
echo 'invalid request has errors: ' . var_export( ! $invalid->is_valid(), true ) . PHP_EOL;

/* --- Synchronizer updates transaction + object meta ---------------- */
$sync   = new TransactionSynchronizer( $pm, $audit );
$target = $result['transaction'];
$paid   = new PaymentResult( true, 'paid', 'PROVIDER-REF-1', 'ok' );
$synced = $sync->apply( $target, $paid );
echo 'sync status paid: ' . $synced->status . PHP_EOL;
echo 'consultation meta paid: ' . get_post_meta( 777, '_bb_consultation_payment_status', true ) . PHP_EOL;

/* No regression: paid -> pending refused */
$regress = $sync->apply( $synced, new PaymentResult( false, 'pending', 'PROVIDER-REF-1' ) );
echo 'no regression from paid: ' . $regress->status . PHP_EOL;

/* --- Receipt ------------------------------------------------------- */
$receipt = Receipt::from_transaction( $synced, $pm->gateway( 'bank_transfer' )->get_name() );
echo 'receipt is_paid: ' . var_export( $receipt->is_paid, true ) . PHP_EOL;
$receipt_array = $receipt->to_array();
$has_secret    = false;
foreach ( array( 'secret_key', 'webhook_secret', 'account_number', 'password' ) as $forbidden ) {
    $present = array_key_exists( $forbidden, $receipt_array );

    if ( $present ) {
        $has_secret = true;
    }
}
echo 'receipt excludes secrets: ' . var_export( ! $has_secret, true ) . PHP_EOL;

$renderer = new ReceiptRenderer();
$html     = $renderer->render( $receipt );
echo 'receipt html non-empty: ' . var_export( '' !== $html, true ) . PHP_EOL;

$receipt_page = new ReceiptPage( $pm, $renderer, $audit );
$receipt_out  = $receipt_page->render_for_reference( $synced->public_ref );
echo 'receipt page shows ref: ' . var_export( false !== strpos( $receipt_out, $synced->public_ref ), true ) . PHP_EOL;
$receipt_miss = $receipt_page->render_for_reference( Reference::transaction() );
echo 'receipt page uniform not-found: ' . var_export( false !== strpos( $receipt_miss, 'No receipt' ), true ) . PHP_EOL;

/* --- Status page (enumeration-resistant) --------------------------- */
$status_page = new StatusPage( $pm );
$unknown     = $status_page->render( Reference::transaction() );
$unknown2    = $status_page->render( Reference::transaction() );
echo 'status unknown #1 uniform: ' . var_export( false !== strpos( $unknown, 'No record' ), true ) . PHP_EOL;
echo 'status unknown #2 uniform: ' . var_export( false !== strpos( $unknown2, 'No record' ), true ) . PHP_EOL;

$known_status = $status_page->render( $synced->public_ref );
echo 'status known shows ref: ' . var_export( false !== strpos( $known_status, $synced->public_ref ), true ) . PHP_EOL;

/* --- Cleanup ------------------------------------------------------- */
$posts = get_posts( array( 'post_type' => 'bb_payment', 'numberposts' => -1, 'fields' => 'ids' ) );
foreach ( $posts as $pid ) {
    wp_delete_post( $pid, true );
}

foreach ( array( 'bb_payment_gateways', 'bb_payment_active_gateway', 'bb_payment_enabled_gateways', 'bb_payment_transactions' ) as $opt ) {
    delete_option( $opt );
}

delete_post_meta( 777, '_bb_consultation_payment_status' );

restore_current_blog();
echo 'PHASE F DONE' . PHP_EOL;
