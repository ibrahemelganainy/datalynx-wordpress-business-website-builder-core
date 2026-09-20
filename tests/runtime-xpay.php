<?php
/**
 * Runtime test: XPay gateway adapter (registration, honesty, credentials).
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
$gw = $pm->gateway( 'xpay' );

echo "== registration ==\n";
chk( 'xpay gateway registered', null !== $gw );
chk( 'xpay id is correct', $gw && 'xpay' === $gw->get_id() );
chk( 'xpay name is XPay', $gw && 'XPay' === $gw->get_name() );
chk( 'xpay is an automatic (non-manual) gateway', $gw && false === $gw->is_manual() );

echo "== schema ==\n";
$schema = $gw->get_settings_schema();
chk( 'has publishable_key field', isset( $schema['publishable_key'] ) );
chk( 'has secret_key field (secret)', isset( $schema['secret_key'] ) && ! empty( $schema['secret_key']['secret'] ) );
chk( 'has webhook_secret field (secret)', isset( $schema['webhook_secret'] ) && ! empty( $schema['webhook_secret']['secret'] ) );
chk( 'has mode selector', isset( $schema['mode'] ) );

echo "== integration contract (implemented against docs.xpay.app) ==\n";
chk( 'xpay reports integration-ready', true === $gw->is_integration_ready() );
chk( 'xpay supports EGP only', array( 'EGP' ) === $gw->get_supported_currencies() );

$xb = new ReflectionMethod( get_class( $gw ), 'api_base' );
$xb->setAccessible( true );
$xbase = (string) $xb->invoke( $gw );
chk( 'xpay API base is api.xpay.app', 'https://api.xpay.app' === $xbase );

$source = (string) file_get_contents( BB_CORE_PATH . 'includes/Core/Payments/Gateways/XPayGateway.php' );
chk( 'creates a checkout session (POST /checkout/sessions)', false !== strpos( $source, '/checkout/sessions' ) && false !== strpos( $source, 'http_post_json' ));
chk( 'verifies by session (GET session)', false !== strpos( $source, 'http_get' ) && false !== strpos( $source, 'paymentStatus' ));
chk( 'uses Bearer secret key auth', false !== strpos( $source, 'Bearer ' ));
chk( 'sends afterCompletion.redirect', false !== strpos( $source, 'afterCompletion' ) && false !== strpos( $source, 'CHECKOUT_SESSION_ID' ));
chk( 'verifies XPay-Signature webhook (HMAC sha256)', false !== strpos( $source, 'xpay-signature' ) && false !== strpos( $source, 'hash_hmac' ) );

/*
 * Payload-shape regression guard (XPay 400 parameter_unknown):
 * the reference must live in metadata ONLY, and cancelUrl must be guarded
 * for uiMode "hosted".
 */
chk( 'root payload has NO clientReferenceId key (400 fix)', false === strpos( $source, "'clientReferenceId'" ) );
chk( 'reference kept in metadata.public_ref', false !== strpos( $source, "'public_ref'  => (string)" ) );
chk( 'sends uiMode hosted', false !== strpos( $source, "'uiMode'          => 'hosted'" ) );
chk( 'cancelUrl guarded for hosted only', false !== strpos( $source, "'hosted' === \$body['uiMode']" ) );
chk( 'metadata values cast to strings', 3 === substr_count( $source, '=> (string) $transaction->' ) );

$txn = PaymentTransaction::from_array(
    array(
        'public_ref' => 'TXN-XPAY0001',
        'gateway'    => 'xpay',
        'amount'     => '500.00',
        'currency'   => 'EGP',
        'status'     => 'pending',
    )
);

delete_option( 'bb_payment_gateways' );

$res  = $gw->create_payment( $txn );
$type = isset( $res['type'] ) ? (string) $res['type'] : '';
chk( 'unconfigured xpay returns unavailable', 'unavailable' === $type );
chk( 'xpay never returns a redirect without config', 'redirect' !== $type );
chk( 'xpay create has no success flag', ! isset( $res['success'] ) || false === $res['success'] );

echo "== credentials persist + mask ==\n";
$pm->save_settings( 'xpay', array( 'mode' => 'test', 'publishable_key' => 'pk_xpay_123', 'secret_key' => 'sk_xpay_SECRET', 'webhook_secret' => 'whsec_xpay' ) );

$saved = $gw->get_settings();
chk( 'publishable_key persisted', 'pk_xpay_123' === ( $saved['publishable_key'] ?? '' ) );
chk( 'secret_key persisted', 'sk_xpay_SECRET' === ( $saved['secret_key'] ?? '' ) );
chk( 'webhook_secret persisted', 'whsec_xpay' === ( $saved['webhook_secret'] ?? '' ) );
chk( 'is_configured true after full config', true === $gw->is_configured() );
chk( 'secret mask hides the value', 'sk_xpay_SECRET' !== PaymentManager::mask_secret( 'sk_xpay_SECRET' ) );
chk( 'secret mask keeps last 4', false !== strpos( PaymentManager::mask_secret( 'sk_xpay_SECRET' ), 'CRET' ) );

echo "== empty re-save preserves secret ==\n";
$pm->save_settings( 'xpay', array( 'mode' => 'test', 'publishable_key' => 'pk_xpay_123', 'secret_key' => '', 'webhook_secret' => '' ) );
$saved2 = $gw->get_settings();
chk( 'secret preserved on empty re-save', 'sk_xpay_SECRET' === ( $saved2['secret_key'] ?? '' ) );

echo "== other gateways unaffected ==\n";
chk( 'stripe still registered', null !== $pm->gateway( 'stripe' ) );
chk( 'paymob still registered', null !== $pm->gateway( 'paymob' ) );
chk( 'paypal still registered', null !== $pm->gateway( 'paypal' ) );

delete_option( 'bb_payment_gateways' );

echo "\nFAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );
exit( 0 === $fail ? 0 : 1 );
