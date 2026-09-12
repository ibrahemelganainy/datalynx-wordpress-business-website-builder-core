<?php
/* Real WP runtime: instantiate + render the new admin pages directly. */
define( 'WP_USE_THEMES', false );
define( 'WP_ADMIN', true );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

$root = ABSPATH . 'wp-content/plugins/business-builder-core/';
require_once $root . 'includes/Core/Notifications/NotificationChannel.php';
require_once $root . 'includes/Core/Notifications/Notification.php';
require_once $root . 'includes/Core/Notifications/EmailNotificationChannel.php';
require_once $root . 'includes/Core/Notifications/NotificationManager.php';
require_once $root . 'includes/Core/Audit/AuditLog.php';
foreach ( glob( $root . 'includes/Core/Payments/*.php' ) as $f ) { require_once $f; }
foreach ( glob( $root . 'includes/Core/Payments/Gateways/*.php' ) as $f ) { require_once $f; }
require_once $root . 'includes/Settings/SiteSettings.php';
require_once $root . 'packs/LawFirm/PostTypes/ConsultationMeta.php';
require_once $root . 'packs/LawFirm/Admin/PaymentSettingsAdmin.php';
require_once $root . 'packs/LawFirm/Admin/DashboardAdmin.php';

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Notifications\NotificationManager;
use BusinessBuilderCore\Core\Audit\AuditLog;
use BusinessBuilderCore\Settings\SiteSettings;
use BusinessBuilderCore\Packs\LawFirm\Admin\PaymentSettingsAdmin;
use BusinessBuilderCore\Packs\LawFirm\Admin\DashboardAdmin;

$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'blog_id' => 2 ) );
switch_to_blog( 2 );
wp_set_current_user( $admins[0]->ID );

$pm = new PaymentManager();
$nm = new NotificationManager();
$au = new AuditLog();
$ss = new SiteSettings();

/* Seed a fee so the form shows a value. */
$ss->update( array( 'require_consultation_payment' => 1, 'consultation_fee' => '1500', 'consultation_currency' => 'EGP' ) );
$pm->set_active_gateway( 'paymob' );
$pm->save_settings( 'paymob', array( 'api_key' => 'SK-LIVE-9876', 'integration_id' => '22', 'iframe_id' => '33', 'hmac_secret' => 'HMAC-777' ) );

$pay = new PaymentSettingsAdmin( $pm, $au, $ss );

ob_start();
$pay->render_page();
$out = ob_get_clean();

echo 'PaymentSettings rendered: ' . ( strlen( $out ) > 200 ? 'yes' : 'NO' ) . PHP_EOL;
echo 'has gateway select: ' . var_export( false !== strpos( $out, 'bb_payment_gateway' ), true ) . PHP_EOL;
echo 'masks secret (no raw SK-LIVE-9876): ' . var_export( false === strpos( $out, 'SK-LIVE-9876' ), true ) . PHP_EOL;
echo 'shows masked (****9876): ' . var_export( false !== strpos( $out, '9876' ), true ) . PHP_EOL;
echo 'gateway_settings field present: ' . var_export( false !== strpos( $out, 'gateway_settings' ), true ) . PHP_EOL;

$dash = new DashboardAdmin( $nm );
ob_start();
$dash->render_page();
$d = ob_get_clean();

echo 'Dashboard rendered: ' . ( strlen( $d ) > 200 ? 'yes' : 'NO' ) . PHP_EOL;
echo 'has Consultation Requests shortcut: ' . var_export( false !== strpos( $d, 'bb_consultation' ), true ) . PHP_EOL;
echo 'has Payment Settings shortcut: ' . var_export( false !== strpos( $d, 'business-builder-payments' ), true ) . PHP_EOL;

/* Restore. */
$ss->update( array( 'require_consultation_payment' => 0, 'consultation_fee' => '', 'consultation_currency' => 'USD' ) );
$pm->set_active_gateway( '' );
delete_option( 'bb_payment_gateways' );

restore_current_blog();
echo 'DONE' . PHP_EOL;
