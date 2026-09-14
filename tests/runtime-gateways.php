<?php
/**
 * Runtime test: payment gateway implementations + status model.
 *
 * Verifies:
 *   - interface/registration integrity
 *   - unconfigured API gateways NEVER fake success
 *   - currency validation
 *   - reference/signature helpers
 *   - transaction status model (awaiting_payment/on_hold/completed)
 *
 * Run: php tests/runtime-gateways.php
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\Transaction\TransactionStatus;

$pass = 0;
$fail = 0;

function check( string $label, bool $cond ): void {
    global $pass, $fail;
    if ( $cond ) { $pass++; echo "  OK   $label\n"; }
    else { $fail++; echo "  FAIL $label\n"; }
}

$pm = new PaymentManager();

echo "== registration ==\n";
foreach ( array( 'stripe', 'paypal', 'paymob', 'fawry', 'bank_transfer', 'wallet', 'instapay' ) as $id ) {
    $g = $pm->gateway( $id );
    check( "gateway $id registered", null !== $g );
}

echo "== honesty: integration-ready flags ==\n";
check( 'stripe integration ready', true === $pm->gateway( 'stripe' )->is_integration_ready() );
check( 'paypal integration ready', true === $pm->gateway( 'paypal' )->is_integration_ready() );
check( 'paymob integration ready', true === $pm->gateway( 'paymob' )->is_integration_ready() );
check( 'fawry integration ready', true === $pm->gateway( 'fawry' )->is_integration_ready() );
check( 'bank_transfer is manual', true === $pm->gateway( 'bank_transfer' )->is_manual() );
check( 'stripe is NOT manual', false === $pm->gateway( 'stripe' )->is_manual() );

echo "== unconfigured gateways must NOT fake success ==\n";

/* Clear any stored config so the gateways are unconfigured. */
delete_option( 'bb_payment_gateways' );

$txn = PaymentTransaction::from_array(
    array(
        'public_ref'  => 'TXN-TESTTEST00',
        'object_type' => 'consultation',
        'object_id'   => 1,
        'gateway'     => 'paypal',
        'amount'      => '500.00',
        'currency'    => 'EGP',
        'status'      => 'pending',
    )
);

foreach ( array( 'stripe', 'paypal', 'paymob', 'fawry' ) as $id ) {
    $g = $pm->gateway( $id );

    check( "$id is_configured() false when empty", false === $g->is_configured() );

    $res  = $g->create_payment( $txn );
    $type = isset( $res['type'] ) ? (string) $res['type'] : '';

    check( "$id returns non-success type when unconfigured", 'unavailable' === $type || 'error' === $type );
    check( "$id never returns redirect without config", 'redirect' !== $type );
}

echo "== payment result NEVER claims paid from a create call ==\n";
$res = $pm->gateway( 'paypal' )->create_payment( $txn );
check( 'paypal create has no success flag', ! isset( $res['success'] ) || false === $res['success'] );

echo "== currency support ==\n";
check( 'fawry supports EGP', in_array( 'EGP', $pm->gateway( 'fawry' )->get_supported_currencies(), true ) );
check( 'fawry does not list USD', ! in_array( 'USD', $pm->gateway( 'fawry' )->get_supported_currencies(), true ) );

echo "== return/cancel URLs are public + reference-based ==\n";
$url = $pm->gateway( 'stripe' )->get_return_url( $txn );
check( 'return url contains public ref', false !== strpos( $url, $txn->public_ref ) );
check( 'return url contains no post id', false === strpos( $url, 'post=' ) );

echo "== status model ==\n";
check( 'awaiting_payment is a valid status', TransactionStatus::is_valid( 'awaiting_payment' ) );
check( 'on_hold is a valid status', TransactionStatus::is_valid( 'on_hold' ) );
check( 'completed is a valid status', TransactionStatus::is_valid( 'completed' ) );
check( 'awaiting_payment -> paid allowed', true === TransactionStatus::can_transition_to( 'awaiting_payment', 'paid' ) );
check( 'paid -> pending refused (no regression)', false === TransactionStatus::can_transition_to( 'paid', 'pending' ) );
check( 'paid is final', true === TransactionStatus::is_final( 'paid' ) );

echo "\nPASS: $pass   FAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );
exit( 0 === $fail ? 0 : 1 );
