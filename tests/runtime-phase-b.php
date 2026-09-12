<?php
/*
 * Real WP runtime test: Phase B payment settings persistence.
 *
 * Proves the multi-gateway model end to end without the admin UI:
 *   - all gateways registered + logos resolve
 *   - enabling MANY gateways persists across a new manager instance
 *   - configuring MANY gateways persists (including a secret)
 *   - re-saving with an empty secret field preserves the stored secret
 *   - disabling a gateway does NOT delete its configuration
 *   - currency persists via SiteSettings
 *   - Multisite isolation (site 2 vs site 3 never share credentials)
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\Currencies;
use BusinessBuilderCore\Settings\SiteSettings;

$options = array(
    'bb_payment_gateways',
    'bb_payment_active_gateway',
    'bb_payment_enabled_gateways',
    'bb_payment_transactions',
    'bb_site_settings',
);

/* ---- Site 2: enable + configure several gateways ------------------ */
switch_to_blog( 2 );

foreach ( $options as $opt ) {
    delete_option( $opt );
}

$pm = new PaymentManager();

/* All gateways registered + logos resolve. */
$all = $pm->gateways();
echo 'gateway count: ' . count( $all ) . PHP_EOL;

foreach ( $all as $id => $gateway ) {
    $logo   = $gateway->get_logo_url();
    $status = ( '' !== $logo ) ? 'logo OK' : 'no logo';
    echo ' - ' . $id . ': ' . $status . PHP_EOL;
}

/* Enable FOUR gateways at once (the core requirement). */
$pm->set_enabled_gateways( array( 'paymob', 'stripe', 'fawry', 'bank_transfer' ) );

/* Configure each independently in one shot (simulates one Save). */
$pm->save_settings( 'paymob', array(
    'mode'           => 'live',
    'api_key'        => 'SECRET-PAYMOB-KEY',
    'integration_id' => '11111',
    'iframe_id'      => '22222',
) );
$pm->save_settings( 'stripe', array(
    'mode'            => 'live',
    'publishable_key' => 'pk_live_x',
    'secret_key'      => 'SECRET-STRIPE-KEY',
) );
$pm->save_settings( 'fawry', array(
    'merchant_code' => 'FWRY1',
    'security_key'  => 'SECRET-FAWRY-KEY',
) );
$pm->save_settings( 'bank_transfer', array(
    'bank_name'      => 'Test Bank',
    'account_name'   => 'Firm',
    'account_number' => '123456',
) );

/* Reload: a fresh manager must read the same multi-gateway state. */
$pm2 = new PaymentManager();

$enabled = $pm2->enabled_gateways();
sort( $enabled );
echo 'enabled after reload: ' . implode( ',', $enabled ) . PHP_EOL;
echo 'enabled count == 4: ' . var_export( 4 === count( $enabled ), true ) . PHP_EOL;

$pm2_gw = $pm2->gateway( 'paymob' );
echo 'paymob api_key preserved: ' . var_export( 'SECRET-PAYMOB-KEY' === (string) $pm2_gw->get_setting( 'api_key', '' ), true ) . PHP_EOL;
echo 'stripe secret preserved: ' . var_export( 'SECRET-STRIPE-KEY' === (string) $pm2->gateway( 'stripe' )->get_setting( 'secret_key', '' ), true ) . PHP_EOL;
echo 'fawry security preserved: ' . var_export( 'SECRET-FAWRY-KEY' === (string) $pm2->gateway( 'fawry' )->get_setting( 'security_key', '' ), true ) . PHP_EOL;

/* Re-save paymob with an EMPTY secret field -> secret must survive. */
$pm2->save_settings( 'paymob', array(
    'mode'           => 'test',
    'api_key'        => '',
    'integration_id' => '11111',
    'iframe_id'      => '22222',
) );

$after = new PaymentManager();
echo 'paymob secret kept after empty submit: ' . var_export( 'SECRET-PAYMOB-KEY' === (string) $after->gateway( 'paymob' )->get_setting( 'api_key', '' ), true ) . PHP_EOL;
echo 'paymob mode changed to test: ' . var_export( 'test' === (string) $after->gateway( 'paymob' )->get_setting( 'mode', '' ), true ) . PHP_EOL;

/* Disable stripe -> its configuration must remain. */
$after->set_enabled_gateways( array( 'paymob', 'fawry', 'bank_transfer' ) );

$after_disable = new PaymentManager();
echo 'stripe now disabled: ' . var_export( ! $after_disable->is_gateway_enabled( 'stripe' ), true ) . PHP_EOL;
echo 'stripe secret kept after disable: ' . var_export( 'SECRET-STRIPE-KEY' === (string) $after_disable->gateway( 'stripe' )->get_setting( 'secret_key', '' ), true ) . PHP_EOL;

/* Available gateways = enabled AND configured. */
$available = array_keys( $after_disable->available_gateways() );
sort( $available );
echo 'available (enabled+configured): ' . implode( ',', $available ) . PHP_EOL;

/* Currency persists via SiteSettings + validated against catalogue. */
$settings = new SiteSettings();
$settings->update( array( 'consultation_currency' => 'egp' ) );
$settings_reload = new SiteSettings();
echo 'currency normalized+persisted: ' . (string) $settings_reload->get( 'consultation_currency' ) . PHP_EOL;

/* ---- Site 3: must NOT see site 2's credentials -------------------- */
switch_to_blog( 3 );

foreach ( $options as $opt ) {
    delete_option( $opt );
}

$pm3 = new PaymentManager();
echo 'site3 enabled count: ' . count( $pm3->enabled_gateways() ) . PHP_EOL;
echo 'site3 paymob secret empty: ' . var_export( '' === (string) $pm3->gateway( 'paymob' )->get_setting( 'api_key', '' ), true ) . PHP_EOL;

foreach ( $options as $opt ) {
    delete_option( $opt );
}

restore_current_blog();

foreach ( $options as $opt ) {
    delete_option( $opt );
}

echo 'PHASE B DONE' . PHP_EOL;
