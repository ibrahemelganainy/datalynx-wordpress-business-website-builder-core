<?php
/**
 * Runtime test: permalink-agnostic return/cancel URLs land on the REST
 * callback (not a bare home URL), and carry the origin page for the return.
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;

$fail = 0;

function chk( string $label, bool $cond ): void {
    global $fail;
    if ( $cond ) { echo "  OK   $label\n"; }
    else { $fail++; echo "  FAIL $label\n"; }
}

$pm = new PaymentManager();

$txn = PaymentTransaction::from_array(
    array(
        'public_ref' => 'TXN-RETURN0001',
        'object_type' => 'consultation',
        'object_id' => 1,
        'gateway'   => 'stripe',
        'amount'    => '100.00',
        'currency'  => 'USD',
        'status'    => 'pending',
        'meta'      => array( 'origin' => '/home/' ),
    )
);

foreach ( array( 'stripe', 'paypal', 'paymob', 'fawry' ) as $id ) {
    $txn->gateway = $id;
    $gw   = $pm->gateway( $id );
    $ret  = $gw->get_return_url( $txn );
    $can  = $gw->get_cancel_url( $txn );

    echo str_pad( $id, 8 ) . 'return: ' . $ret . "\n";

    chk( "$id: return targets the callback route", false !== strpos( $ret, '/payment/callback/' . $id ) );
    chk( "$id: return carries the public ref", false !== strpos( $ret, 'TXN-RETURN0001' ) );
    chk( "$id: cancel targets the callback route", false !== strpos( $can, '/payment/callback/' . $id ) );
    chk( "$id: return carries the origin path", false !== strpos( $ret, 'bb_origin=' ) );
    chk( "$id: no raw bb_checkout on the return url", false === strpos( $ret, 'bb_checkout=' ) );
}

/* The route must work with plain permalinks too (rest_route form fallback). */
$txn->gateway = 'stripe';
$ret = $pm->gateway( 'stripe' )->get_return_url( $txn );
echo "stripe return: $ret\n";
chk( 'return is absolute', 0 === strpos( $ret, 'http' ) );
chk( 'return does not contain a hardcoded wp-json-only path', false === strpos( $ret, 'index.php' ) );

echo "\nFAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );
exit( 0 === $fail ? 0 : 1 );
