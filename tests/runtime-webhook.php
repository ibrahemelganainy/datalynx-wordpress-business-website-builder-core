<?php
/* Real WP runtime: payment webhook REST route + security behaviour. */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

$root = ABSPATH . 'wp-content/plugins/business-builder-core/';
require_once $root . 'includes/Core/Notifications/NotificationChannel.php';
require_once $root . 'includes/Core/Notifications/Notification.php';
require_once $root . 'includes/Core/Notifications/EmailNotificationChannel.php';
require_once $root . 'includes/Core/Notifications/NotificationManager.php';
require_once $root . 'includes/Core/Audit/AuditLog.php';
foreach ( glob( $root . 'includes/Core/Payments/*.php' ) as $f ) { require_once $f; }
foreach ( glob( $root . 'includes/Core/Payments/Gateways/*.php' ) as $f ) { require_once $f; }
require_once $root . 'includes/REST/PaymentWebhook.php';

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Notifications\NotificationManager;
use BusinessBuilderCore\Core\Audit\AuditLog;
use BusinessBuilderCore\REST\PaymentWebhook;

switch_to_blog( 2 );

$pm  = new PaymentManager();
$nm  = new NotificationManager();
$au  = new AuditLog();
$hook = new PaymentWebhook( $pm, $nm, $au );

/* 1. Route is registered after rest_api_init. */
do_action( 'rest_api_init' );
$server = rest_get_server();
$routes = $server->get_routes();
$found = false;
foreach ( array_keys( $routes ) as $route ) {
    $is_webhook = false !== strpos( $route, 'payment/webhook' );
    if ( $is_webhook ) { $found = true; echo 'ROUTE: ' . $route . PHP_EOL; }
}
echo 'webhook route registered: ' . var_export( $found, true ) . PHP_EOL;

/* 2. Unknown gateway => 404. */
$req = new WP_REST_Request( 'POST', '/business-builder/v1/payment/webhook/nope' );
$req->set_param( 'gateway', 'nope' );
$res = $hook->handle( $req );
echo 'unknown gateway status: ' . $res->get_status() . ' reason=' . $res->get_data()['reason'] . PHP_EOL;

/* 3. Manual gateway => 400 (never auto-verified). */
$req2 = new WP_REST_Request( 'POST', '/business-builder/v1/payment/webhook/bank_transfer' );
$req2->set_param( 'gateway', 'bank_transfer' );
$res2 = $hook->handle( $req2 );
echo 'manual gateway status: ' . $res2->get_status() . ' reason=' . $res2->get_data()['reason'] . PHP_EOL;

/* 4. API gateway (not integration-ready) => payload rejected, NEVER paid. */
$req3 = new WP_REST_Request( 'POST', '/business-builder/v1/payment/webhook/stripe' );
$req3->set_param( 'gateway', 'stripe' );
$fake_payload = array( 'status' => 'success', 'reference' => 'FAKE-1' );
$req3->set_body( wp_json_encode( $fake_payload ) );
$req3->set_header( 'content-type', 'application/json' );
$res3 = $hook->handle( $req3 );
$d3 = $res3->get_data();
echo 'stripe spoofed success status: ' . $res3->get_status() . ' reason=' . $d3['reason'] . PHP_EOL;

restore_current_blog();
echo 'DONE' . PHP_EOL;
