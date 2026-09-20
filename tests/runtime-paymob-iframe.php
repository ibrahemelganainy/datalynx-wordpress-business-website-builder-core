<?php
/**
 * Runtime test: Paymob iframe redirect URL includes the required /api/ segment
 * and uses the Iframe ID (from settings), not the Integration ID.
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Core\Payments\PaymentManager;

$fail = 0;

function chk( string $label, bool $cond ): void {
    global $fail;
    if ( $cond ) { echo "  OK   $label\n"; }
    else { $fail++; echo "  FAIL $label\n"; }
}

/* Configure Paymob with distinct Integration ID and Iframe ID. */
$pm = new PaymentManager();
$pm->save_settings(
    'paymob',
    array(
        'api_key'        => 'test-api-key',
        'integration_id' => '5141466',
        'iframe_id'      => '931027',
        'hmac_secret'    => 'test-hmac',
    )
);

$gw = $pm->gateway( 'paymob' );

$txn = \BusinessBuilderCore\Core\Payments\PaymentTransaction::from_array(
    array(
        'public_ref' => 'TXN-IFRAMETEST',
        'gateway'    => 'paymob',
        'amount'     => '500.00',
        'currency'   => 'EGP',
        'status'     => 'pending',
        'meta'       => array(),
    )
);

/* Stub the network so create_payment() gets past auth + order + key. */
$stub = new class extends BusinessBuilderCore\Core\Payments\Gateways\PaymobGateway {
    protected function auth_token(): string {
        return 'AUTH-TOKEN';
    }

    protected function http_post_json( string $url, array $body, array $headers = array() ): array {
        $is_order = ( false !== strpos( $url, '/api/ecommerce/orders' ) );
        if ( $is_order ) {
            return array( 'status' => 201, 'body' => array( 'id' => 555 ), 'error' => '', 'raw' => '' );
        }
        $is_key = ( false !== strpos( $url, '/api/acceptance/payment_keys' ) );
        if ( $is_key ) {
            return array( 'status' => 201, 'body' => array( 'token' => 'PAY-TOKEN-ABC' ), 'error' => '', 'raw' => '' );
        }

        return array( 'status' => 200, 'body' => array(), 'error' => '', 'raw' => '' );
    }
};

$res  = $stub->create_payment( $txn );
$url  = isset( $res['url'] ) ? (string) $res['url'] : '';

echo "redirect url: $url\n";

chk( 'returns a redirect', 'redirect' === ( $res['type'] ?? '' ) );
chk( 'url contains /api/acceptance/iframes/', false !== strpos( $url, '/api/acceptance/iframes/' ) );
chk( 'url uses the IFRAME id (931027)', false !== strpos( $url, '/iframes/931027' ) );
chk( 'url does NOT use the integration id', false === strpos( $url, '/iframes/5141466' ) );
chk( 'url carries the payment_token', false !== strpos( $url, 'payment_token=PAY-TOKEN-ABC' ) );
chk( 'url host is accept.paymob.com', false !== strpos( $url, 'accept.paymob.com' ) );

echo "\nFAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );
exit( 0 === $fail ? 0 : 1 );
