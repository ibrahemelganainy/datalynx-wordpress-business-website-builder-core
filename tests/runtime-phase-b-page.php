<?php
/*
 * Phase B acceptance: render the FULL Payment Settings page through
 * PaymentSettingsAdmin::render_page() and assert it completes with the
 * expected structural elements for every gateway.
 */
define( 'WP_USE_THEMES', false );
define( 'WP_ADMIN', true );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Audit\AuditLog;
use BusinessBuilderCore\Settings\SiteSettings;
use BusinessBuilderCore\Packs\LawFirm\Admin\PaymentSettingsAdmin;

switch_to_blog( 2 );

/* Simulate a capable admin without a real login. */
$admin_id = 1;
wp_set_current_user( $admin_id );

$pm       = new PaymentManager();
$audit    = new AuditLog();
$settings = new SiteSettings();

$page = new PaymentSettingsAdmin( $pm, $audit, $settings );

ob_start();
$page->render_page();
$html = ob_get_clean();

echo 'page rendered bytes: ' . strlen( $html ) . PHP_EOL;

$checks = array(
    'wrap'            => str_contains( $html, 'wrap bb-payments' ),
    'currency select' => str_contains( $html, 'name="currency"' ),
    'currency combobox' => str_contains( $html, 'data-bb-combobox' ),
    'gateway grid'    => str_contains( $html, 'bb-gateway-grid' ),
    'save button'     => str_contains( $html, 'Save Payment Settings' ),
    'nonce'           => str_contains( $html, 'bb_save_payment_settings' ),
);

foreach ( $checks as $label => $ok ) {
    echo str_pad( $label, 20 ) . ': ' . ( $ok ? 'OK' : 'FAIL' ) . PHP_EOL;
}

/* Every gateway id must appear as a card in the rendered page. */
$missing = array();

foreach ( array_keys( $pm->gateways() ) as $id ) {
    $needle = 'data-gateway="' . esc_attr( $id ) . '"';

    if ( ! str_contains( $html, $needle )) {
        $missing[] = $id;
    }
}

echo 'gateway cards present: ' . ( empty( $missing ) ? 'ALL' : implode( ',', $missing ) . ' MISSING' ) . PHP_EOL;

/* The specific joined-currency string that used to fatal. */
$paymob = $pm->gateway( 'paymob' );
$join   = implode( ', ', $paymob->get_supported_currencies() );
echo 'paymob currencies line present: ' . var_export( str_contains( $html, esc_html( $join ) ), true ) . PHP_EOL;

$fatal = str_contains( $html, 'Fatal error' );

echo 'fatal in output: ' . var_export( $fatal, true ) . PHP_EOL;
echo ( ! $fatal && empty( $missing ) ? 'PAGE LOADS SUCCESSFULLY' : 'PAGE PROBLEM' ) . PHP_EOL;

restore_current_blog();
echo 'PHASE B PAGE DONE' . PHP_EOL;
