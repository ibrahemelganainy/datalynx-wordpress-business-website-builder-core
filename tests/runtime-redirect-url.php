<?php
/**
 * Runtime test: gateway settings UI about signature fields + no Redirect URL.
 *
 * Verifies:
 *   - Paymob shows an "HMAC Secret" field; Fawry shows a "Security Key" field;
 *   - the read-only Webhook URL field (+ copy button) is still present;
 *   - NO Redirect URL field is rendered for any gateway;
 *   - the signature values persist through the standard save path.
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Packs\LawFirm\Admin\PaymentSettingsAdmin;
use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Audit\AuditLog;
use BusinessBuilderCore\Settings\SiteSettings;

$fail = 0;

function chk( string $label, bool $cond ): void {
    global $fail;
    if ( $cond ) { echo "  OK   $label\n"; }
    else { $fail++; echo "  FAIL $label\n"; }
}

$admin  = new PaymentSettingsAdmin( new PaymentManager(), new AuditLog(), new SiteSettings() );
$method = new ReflectionMethod( $admin, 'render_gateway_fields' );
$method->setAccessible( true );

$pm  = new PaymentManager();
$out = array();

foreach ( array( 'paymob', 'fawry', 'stripe', 'bank_transfer' ) as $id ) {
    ob_start();
    $method->invoke( $admin, $id, $pm->gateway( $id ) );
    $out[ $id ] = (string) ob_get_clean();
}

/* 1) Signature fields present. */
chk( 'paymob: HMAC Secret field present', false !== strpos( $out['paymob'], 'bb_gw_paymob_hmac_secret' ) );
chk( 'paymob: HMAC Secret label present', false !== strpos( $out['paymob'], 'HMAC Secret' ) );
chk( 'fawry: Security Key field present', false !== strpos( $out['fawry'], 'bb_gw_fawry_security_key' ) );
chk( 'fawry: Security Key label present', false !== strpos( $out['fawry'], 'Security Key' ) );

/* 2) Webhook URL field + copy button retained. */
chk( 'paymob: webhook URL field present', false !== strpos( $out['paymob'], 'bb_gw_paymob_webhook_url' ) );
chk( 'paymob: webhook copy button present', false !== strpos( $out['paymob'], 'data-bb-copy-target="bb_gw_paymob_webhook_url"' ) );
chk( 'fawry: webhook URL field present', false !== strpos( $out['fawry'], 'bb_gw_fawry_webhook_url' ) );

/* 3) NO Redirect URL field anywhere. */
foreach ( array( 'paymob', 'fawry', 'stripe', 'bank_transfer' ) as $id ) {
    chk( "$id: no Redirect URL field", false === strpos( $out[ $id ], 'redirect_url' ) );
}

/* 4) Signature values persist via the standard save path. */
delete_option( 'bb_payment_gateways' );

$pm->save_settings(
    'paymob',
    array(
        'api_key'        => 'SECRET-XYZ-9876',
        'integration_id' => '5141466',
        'iframe_id'      => '931027',
        'hmac_secret'    => 'HMAC-PAYMOB-1234',
    )
);

$pm->save_settings(
    'fawry',
    array(
        'merchant_code' => 'MERCH-1',
        'security_key'  => 'FAWRY-SEC-5678',
    )
);

$paymob_saved = $pm->gateway( 'paymob' )->get_settings();
$fawry_saved  = $pm->gateway( 'fawry' )->get_settings();

chk( 'paymob: hmac_secret persisted', 'HMAC-PAYMOB-1234' === ( $paymob_saved['hmac_secret'] ?? '' ) );
chk( 'paymob: api_key persisted', 'SECRET-XYZ-9876' === ( $paymob_saved['api_key'] ?? '' ) );
chk( 'fawry: security_key persisted', 'FAWRY-SEC-5678' === ( $fawry_saved['security_key'] ?? '' ) );

/* 5) Empty re-save preserves the stored secret (never wiped). */
$pm->save_settings( 'paymob', array( 'api_key' => '', 'integration_id' => '5141466', 'iframe_id' => '931027', 'hmac_secret' => '' ) );
$paymob_saved2 = $pm->gateway( 'paymob' )->get_settings();
chk( 'paymob: empty re-save preserves hmac_secret', 'HMAC-PAYMOB-1234' === ( $paymob_saved2['hmac_secret'] ?? '' ) );

delete_option( 'bb_payment_gateways' );

echo "\nFAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );
exit( 0 === $fail ? 0 : 1 );
