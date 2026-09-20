<?php
/**
 * Runtime check: the Payment Settings webhook URL is derived from the
 * site's own address (home_url) and points at the correct REST route.
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Packs\LawFirm\Admin\PaymentSettingsAdmin;
use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Audit\AuditLog;
use BusinessBuilderCore\Settings\SiteSettings;

$pass = 0;
$fail = 0;

function bump( string $label, bool $cond ): void {
    global $pass, $fail;
    $ok = $cond;
    if ( $ok ) {
        $pass++;
        echo "  OK   $label\n";
    } else {
        $fail++;
        echo "  FAIL $label\n";
    }
}

$admin = new PaymentSettingsAdmin( new PaymentManager(), new AuditLog(), new SiteSettings() );

$ref = new ReflectionMethod( $admin, 'webhook_url' );
$ref->setAccessible( true );

$home      = home_url( '/' );
$home_host = (string) wp_parse_url( $home, PHP_URL_HOST );

echo 'site home_url: ' . $home . "\n";
echo 'site host: ' . $home_host . "\n";

$gateways = array( 'stripe', 'paypal', 'paymob', 'fawry' );

foreach ( $gateways as $gw ) {

    $url = (string) $ref->invoke( $admin, $gw );

    echo str_pad( $gw, 8 ) . '=> ' . $url . "\n";

    $has_host  = ( false !== strpos( $url, $home_host ) );
    $has_route = ( false !== strpos( $url, 'business-builder/v1/payment/webhook/' . $gw ) );
    $is_http   = ( 0 === strpos( $url, 'http' ) );

    bump( "$gw url uses the site host", $has_host );
    bump( "$gw url targets the webhook route", $has_route );
    bump( "$gw url is absolute", $is_http );
}

echo "\nPASS: $pass   FAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );
exit( 0 === $fail ? 0 : 1 );
