<?php
/*
 * Real WP runtime test: payment architecture (Phase C).
 *
 * Verifies:
 *   - Currencies catalogue + symbol/format
 *   - multi-gateway enablement + available_gateways filtering
 *   - gateway interface additions (description/currencies/integration flag)
 *   - transaction model (public_ref, object_type/object_id, statuses)
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Core\Payments\Currencies;
use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;

switch_to_blog( 2 );

foreach ( array( 'bb_payment_gateways', 'bb_payment_active_gateway', 'bb_payment_enabled_gateways', 'bb_payment_transactions' ) as $opt ) {
    delete_option( $opt );
}

$pm = new PaymentManager();

/* Currencies */
echo 'EGP symbol: ' . Currencies::symbol( 'EGP' ) . PHP_EOL;
echo 'SAR name: ' . Currencies::name( 'SAR' ) . PHP_EOL;
echo 'unknown falls back: ' . Currencies::name( 'ZZZ' ) . PHP_EOL;
echo 'egp normalized: ' . Currencies::normalize( 'egp' ) . PHP_EOL;
echo 'format EGP: ' . Currencies::format( 1500, 'EGP' ) . PHP_EOL;
echo 'catalogue size: ' . count( Currencies::all() ) . PHP_EOL;

/* Gateway interface additions */
$stripe = $pm->gateway( 'stripe' );
echo 'stripe description non-empty: ' . var_export( '' !== $stripe->get_description(), true ) . PHP_EOL;
echo 'stripe currencies: ' . implode( ',', $stripe->get_supported_currencies() ) . PHP_EOL;
echo 'stripe integration_ready: ' . var_export( $stripe->is_integration_ready(), true ) . PHP_EOL;

/* Multi-gateway: enable two, exclude a disabled one */
$pm->set_enabled_gateways( array( 'paymob', 'bank_transfer', 'instapay' ) );
echo 'enabled: ' . implode( ',', $pm->enabled_gateways() ) . PHP_EOL;

/* Configure one gateway so it becomes available */
$pm->save_settings( 'bank_transfer', array( 'bank_name' => 'Test Bank', 'account_name' => 'Firm', 'account_number' => '123' ) );

$avail = array_keys( $pm->available_gateways() );
echo 'available (configured+enabled): ' . implode( ',', $avail ) . PHP_EOL;
echo 'instapay disabled-config not available: ' . var_export( in_array( 'instapay', $avail, true ), true ) . PHP_EOL;

/* Section-scoped resolution */
$scoped = array_keys( $pm->resolve_gateways( array( 'bank_transfer', 'stripe' )));
echo 'scoped (stripe not enabled => dropped): ' . implode( ',', $scoped ) . PHP_EOL;

/* Transaction model */
$txn = $pm->create_transaction( array(
    'object_type'  => 'appointment',
    'object_id'    => 999,
    'gateway'      => 'bank_transfer',
    'amount'       => '250.00',
    'currency'     => 'EGP',
    'status'       => 'pending',
    'meta'         => array( 'label' => 'Appointment #999' ),
) );
echo 'txn public_ref: ' . $txn->public_ref . ' [' . ( preg_match( '/^TXN-[A-Z0-9]{10}$/', $txn->public_ref ) ? 'OK' : 'FAIL' ) . ']' . PHP_EOL;
echo 'txn object_type/id: ' . $txn->object_type . '/' . $txn->object_id . PHP_EOL;

$found = $pm->find_by_public_ref( $txn->public_ref );
echo 'find_by_public_ref: ' . ( $found ? $found->status : '(null)' ) . PHP_EOL;

$for_obj = $pm->transactions_for_object( 'appointment', 999 );
echo 'transactions_for_object count: ' . count( $for_obj ) . PHP_EOL;

/* Legacy transaction (consultation only) still resolves object shape */
$legacy = $pm->create_transaction( array( 'consultation_id' => 63, 'gateway' => 'bank_transfer', 'amount' => '100', 'currency' => 'EGP', 'status' => 'paid' ) );
echo 'legacy object_type/id: ' . $legacy->object_type . '/' . $legacy->object_id . PHP_EOL;

echo 'statuses: ' . implode( ',', PaymentTransaction::statuses() ) . PHP_EOL;

/* Cleanup */
delete_option( 'bb_payment_gateways' );
delete_option( 'bb_payment_active_gateway' );
delete_option( 'bb_payment_enabled_gateways' );
delete_option( 'bb_payment_transactions' );

restore_current_blog();
echo 'DONE' . PHP_EOL;
