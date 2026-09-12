<?php
/* Real WP runtime: PaymentManager + gateways + secret handling + transactions. */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

$base = ABSPATH . 'wp-content/plugins/business-builder-core/includes/Core/Payments/';
require_once $base . 'PaymentGatewayInterface.php';
require_once $base . 'PaymentResult.php';
require_once $base . 'PaymentTransaction.php';
require_once $base . 'AbstractGateway.php';
require_once $base . 'Gateways/AbstractApiGateway.php';
require_once $base . 'Gateways/StripeGateway.php';
require_once $base . 'Gateways/PaymobGateway.php';
require_once $base . 'Gateways/PayPalGateway.php';
require_once $base . 'Gateways/FawryGateway.php';
require_once $base . 'Gateways/BankTransferGateway.php';
require_once $base . 'Gateways/WalletGateway.php';
require_once $base . 'Gateways/InstaPayGateway.php';
require_once $base . 'PaymentManager.php';

use BusinessBuilderCore\Core\Payments\PaymentManager;

switch_to_blog( 2 );

$sep = chr( 44 ) . chr( 32 );
delete_option( 'bb_payment_gateways' );
delete_option( 'bb_payment_active_gateway' );
delete_option( 'bb_payment_transactions' );

$pm = new PaymentManager();

echo 'gateways: ' . implode( $sep, array_keys( $pm->gateways() ) ) . PHP_EOL;

// Manual vs API honesty
echo 'bank_transfer manual: ' . var_export( $pm->gateway( 'bank_transfer' )->is_manual(), true ) . PHP_EOL;
echo 'stripe manual: ' . var_export( $pm->gateway( 'stripe' )->is_manual(), true ) . PHP_EOL;
echo 'stripe integration_ready: ' . var_export( $pm->gateway( 'stripe' )->is_integration_ready(), true ) . PHP_EOL;

// Secret handling: save then re-save with empty secret => must preserve
$pm->set_active_gateway( 'paymob' );
$pm->save_settings( 'paymob', array( 'api_key' => 'SECRET-ABC-1234', 'integration_id' => '555', 'iframe_id' => '777', 'hmac_secret' => 'HMAC-9999' ) );
$s1 = $pm->gateway( 'paymob' )->get_settings();
echo 'stored api_key: ' . $s1['api_key'] . PHP_EOL;

$pm->save_settings( 'paymob', array( 'api_key' => '', 'integration_id' => '555', 'iframe_id' => '777', 'hmac_secret' => '' ) );
$s2 = $pm->gateway( 'paymob' )->get_settings();
echo 'after empty resubmit api_key (must equal SECRET-ABC-1234): ' . $s2['api_key'] . PHP_EOL;
echo 'masked: ' . PaymentManager::mask_secret( $s2['api_key'] ) . PHP_EOL;

// Transaction
$txn = $pm->create_transaction( array( 'consultation_id' => 63, 'gateway' => 'bank_transfer', 'amount' => '500', 'currency' => 'EGP', 'status' => 'pending' ) );
echo 'txn id: ' . $txn->id . PHP_EOL;
$pm->update_transaction( $txn->id, 'paid', 'REF-001' );
$found = $pm->find_by_reference( 'REF-001' );
echo 'found txn status: ' . ( $found ? $found->status : '(null)' ) . PHP_EOL;

// Multisite isolation
switch_to_blog( 1 );
$pm1 = new PaymentManager();
echo 'blog1 active gateway (must be empty): [' . $pm1->active_gateway_id() . ']' . PHP_EOL;
echo 'blog1 txns (must be 0): ' . count( $pm1->transactions() ) . PHP_EOL;

restore_current_blog();
switch_to_blog( 2 );
delete_option( 'bb_payment_gateways' );
delete_option( 'bb_payment_active_gateway' );
delete_option( 'bb_payment_transactions' );
restore_current_blog();

echo 'DONE' . PHP_EOL;
