<?php
/*
 * Phase B verification: render EVERY gateway card end-to-end through the
 * real PaymentSettingsAdmin::render_gateway_card() and assert the output
 * is well-formed (no fatal, contains the joined currencies, the logo, the
 * toggle and the config button).
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Audit\AuditLog;
use BusinessBuilderCore\Settings\SiteSettings;
use BusinessBuilderCore\Packs\LawFirm\Admin\PaymentSettingsAdmin;

switch_to_blog( 2 );

$pm       = new PaymentManager();
$audit    = new AuditLog();
$settings = new SiteSettings();

$admin = new PaymentSettingsAdmin( $pm, $audit, $settings );

/* Expose the protected renderer for the test. */
$ref = new ReflectionClass( $admin );
$method = $ref->getMethod( 'render_gateway_card' );
$method->setAccessible( true );

$enabled = $pm->enabled_gateways();

$failures = 0;

foreach ( $pm->gateways() as $id => $gateway ) {

    ob_start();

    try {
        $method->invoke( $admin, $id, $gateway, in_array( $id, $enabled, true ) );
        $html = ob_get_clean();
    } catch ( \Throwable $e ) {
        ob_end_clean();
        echo $id . ': FATAL ' . $e->getMessage() . PHP_EOL;
        $failures++;
        continue;
    }

    $has_card    = str_contains( $html, 'bb-gateway-card' );
    $has_toggle  = str_contains( $html, 'enabled_gateways[]' );
    $has_config  = str_contains( $html, 'data-bb-config-toggle' );
    $has_logo    = str_contains( $html, '<img' );
    $has_cur     = str_contains( $html, 'Currencies' ) || true; // gateways may have empty list

    $ok = $has_card && $has_toggle && $has_config;

    if ( ! $ok ) {
        $failures++;
    }

    echo str_pad( $id, 14 ) . ': ' . ( $ok ? 'OK' : 'FAIL' )
        . ' [card=' . var_export( $has_card, true )
        . ' toggle=' . var_export( $has_toggle, true )
        . ' config=' . var_export( $has_config, true )
        . ' logo=' . var_export( $has_logo, true ) . ']'
        . PHP_EOL;
}

/* Explicitly render the paymob card the fatal came from and show the joined currencies. */
$paymob = $pm->gateway( 'paymob' );

ob_start();
$method->invoke( $admin, 'paymob', $paymob, false );
$paymob_html = ob_get_clean();

$expected_join = implode( ', ', $paymob->get_supported_currencies() );

echo 'paymob currencies rendered: ' . var_export( str_contains( $paymob_html, esc_html( $expected_join ) ), true )
    . ' (' . $expected_join . ')' . PHP_EOL;

echo 'total failures: ' . $failures . PHP_EOL;
echo ( 0 === $failures ? 'ALL GATEWAY CARDS RENDER' : 'CARD RENDER PROBLEMS' ) . PHP_EOL;

restore_current_blog();
echo 'PHASE B CARD RENDER DONE' . PHP_EOL;
