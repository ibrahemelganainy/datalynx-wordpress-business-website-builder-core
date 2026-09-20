<?php
/**
 * Runtime check: the webhook URL field renders (read-only + copy button)
 * and, for PayPal, appears ABOVE the Webhook ID field.
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Packs\LawFirm\Admin\PaymentSettingsAdmin;
use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Audit\AuditLog;
use BusinessBuilderCore\Settings\SiteSettings;

$admin = new PaymentSettingsAdmin( new PaymentManager(), new AuditLog(), new SiteSettings() );

$method = new ReflectionMethod( $admin, 'render_gateway_fields' );
$method->setAccessible( true );

$pm  = new PaymentManager();
$out = array();

foreach ( array( 'paypal', 'stripe', 'bank_transfer' ) as $id ) {
    ob_start();
    $method->invoke( $admin, $id, $pm->gateway( $id ) );
    $out[ $id ] = (string) ob_get_clean();
}

$fail = 0;

function expect( string $label, bool $cond, int &$fail ): void {
    if ( $cond ) {
        echo "  OK   $label\n";
    } else {
        $fail++;
        echo "  FAIL $label\n";
    }
}

/* PayPal: webhook URL field + read-only + copy button + above Webhook ID. */
$paypal = $out['paypal'];
expect( 'paypal: webhook URL field present', false !== strpos( $paypal, 'bb-webhook-url' ), $fail );
expect( 'paypal: input is readonly', false !== strpos( $paypal, 'readonly' ), $fail );
expect( 'paypal: copy button present', false !== strpos( $paypal, 'data-bb-copy-target' ), $fail );
expect( 'paypal: instruction present', false !== strpos( $paypal, 'dashboard' ), $fail );

/* New: icon button + tooltip bubble + "Copied" label + green check. */
expect( 'paypal: copy icon present', false !== strpos( $paypal, 'bb-copy-icon' ), $fail );
expect( 'paypal: tooltip bubble present', false !== strpos( $paypal, 'class="bb-copy-tip"' ), $fail );
expect( 'paypal: tooltip default text', false !== strpos( $paypal, 'Copy to clipboard' ), $fail );
expect( 'paypal: copied label wired', false !== strpos( $paypal, 'data-bb-copy-label="Copied"' ), $fail );
expect( 'paypal: green check markup present', false !== strpos( $paypal, 'bb-copy-tip-check' ), $fail );

$pos_webhook = strpos( $paypal, 'bb-webhook-url' );
$pos_id      = strpos( $paypal, 'Webhook ID' );
expect( 'paypal: webhook URL is ABOVE Webhook ID', false !== $pos_webhook && false !== $pos_id && $pos_webhook < $pos_id, $fail );

/* Stripe: webhook URL present, above the Webhook Signing Secret field. */
$stripe = $out['stripe'];
expect( 'stripe: webhook URL field present', false !== strpos( $stripe, 'bb-webhook-url' ), $fail );
$pos_s_webhook = strpos( $stripe, 'bb-webhook-url' );
$pos_s_secret  = strpos( $stripe, 'Webhook Signing Secret' );
expect( 'stripe: webhook URL above signing secret', false !== $pos_s_webhook && false !== $pos_s_secret && $pos_s_webhook < $pos_s_secret, $fail );

/* Manual gateway: NO webhook field (correctly omitted). */
expect( 'bank_transfer: no webhook URL (manual)', false === strpos( $out['bank_transfer'], 'bb-webhook-url' ), $fail );

echo "\nFAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );
exit( 0 === $fail ? 0 : 1 );
