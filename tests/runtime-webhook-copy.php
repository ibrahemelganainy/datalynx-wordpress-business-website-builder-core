<?php
/**
 * Runtime check: verify the DOM contract the copy button relies on.
 *
 * Confirms that, in the rendered Payment Settings markup, every copy
 * button's data-bb-copy-target resolves to a real read-only <input> whose
 * value is the expected webhook URL — which is exactly what the JS copies.
 * Also validates the JS file is brace-balanced (no runtime syntax error).
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Packs\LawFirm\Admin\PaymentSettingsAdmin;
use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Audit\AuditLog;
use BusinessBuilderCore\Settings\SiteSettings;

$fail = 0;

function expect( string $label, bool $cond, int &$fail ): void {
    if ( $cond ) {
        echo "  OK   $label\n";
    } else {
        $fail++;
        echo "  FAIL $label\n";
    }
}

$admin  = new PaymentSettingsAdmin( new PaymentManager(), new AuditLog(), new SiteSettings() );
$method = new ReflectionMethod( $admin, 'render_gateway_fields' );
$method->setAccessible( true );

$pm = new PaymentManager();

ob_start();
$method->invoke( $admin, 'paypal', $pm->gateway( 'paypal' ) );
$html = (string) ob_get_clean();

/* 1) Extract the copy button target + the input id + value. */
preg_match( '/data-bb-copy-target="([^"]+)"/', $html, $btn );
preg_match( '/<input\b[^>]*\bid="([^"]+)"[^>]*\bvalue="([^"]+)"[^>]*\breadonly/', $html, $inp );

$target = $btn[1] ?? '';
$id     = $inp[1] ?? '';
$value  = $inp[2] ?? '';

echo 'button target: ' . $target . "\n";
echo 'input id:      ' . $id . "\n";
echo 'input value:   ' . $value . "\n";

expect( 'button target matches the input id', '' !== $target && $target === $id, $fail );
expect( 'input value is the gateway webhook URL', false !== strpos( $value, '/payment/webhook/paypal' ), $fail );
expect( 'input is read-only (selectable/copyable)', false !== strpos( $html, 'readonly' ), $fail );

/* 2) The tooltip text span exists (JS target for the "Copied" swap). */
expect( 'tooltip text span exists', false !== strpos( $html, 'bb-copy-tip-text' ), $fail );
expect( 'tooltip default attribute present', false !== strpos( $html, 'data-bb-tip-default' ), $fail );
expect( 'tooltip copied attribute present', false !== strpos( $html, 'data-bb-tip-copied' ), $fail );

/* 3) The JS file the browser runs is brace/paren balanced. */
$js = (string) file_get_contents( 'c:/MAMP/htdocs/wordpress/wp-content/plugins/business-builder-core/assets/js/admin/payment-settings.js' );

$bal = function ( string $s, string $open, string $close ): int {
    return substr_count( $s, $open ) - substr_count( $s, $close );
};

expect( 'JS braces balanced', 0 === $bal( $js, '{', '}' ), $fail );
expect( 'JS parens balanced', 0 === $bal( $js, '(', ')' ), $fail );
expect( 'JS brackets balanced', 0 === $bal( $js, '[', ']' ), $fail );
expect( 'JS defines initWebhookCopy', false !== strpos( $js, 'function initWebhookCopy' ), $fail );
expect( 'JS calls initWebhookCopy', false !== strpos( $js, 'initWebhookCopy( root )' ), $fail );

/* 4) The copy path uses the Clipboard API + a fallback. */
expect( 'JS uses navigator.clipboard.writeText', false !== strpos( $js, 'navigator.clipboard.writeText' ), $fail );
expect( 'JS has execCommand fallback', false !== strpos( $js, "execCommand( 'copy' )" ), $fail );
expect( 'JS toggles is-copied class', false !== strpos( $js, "'is-copied'" ), $fail );

/* 5) CSS defines the tooltip + green check + is-copied state. */
$css = (string) file_get_contents( 'c:/MAMP/htdocs/wordpress/wp-content/plugins/business-builder-core/assets/css/admin/payment-center.css' );

expect( 'CSS defines bb-copy-tip bubble', false !== strpos( $css, '.bb-copy-tip' ), $fail );
expect( 'CSS defines green check reveal', false !== strpos( $css, '.is-copied .bb-copy-tip-check' ), $fail );
expect( 'CSS defines copied button state', false !== strpos( $css, '.is-copied' ), $fail );

echo "\nFAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );
exit( 0 === $fail ? 0 : 1 );
