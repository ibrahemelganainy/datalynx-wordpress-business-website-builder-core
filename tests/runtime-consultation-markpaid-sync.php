<?php
/*
 * Phase 7 regression: "Mark Payment Verified" on a consultation with an
 * existing transaction must keep the TRANSACTION and the CONSULTATION in
 * sync (both paid). Previously the consultation was marked paid while the
 * transaction stayed pending - a state divergence.
 *
 * Run: php tests/runtime-consultation-markpaid-sync.php
 */
define( 'WP_USE_THEMES', false );
define( 'WP_ADMIN', true );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

$root = ABSPATH . 'wp-content/plugins/business-builder-core/';
foreach ( glob( $root . 'includes/Core/Payments/*.php' ) as $f ) { require_once $f; }
foreach ( glob( $root . 'includes/Core/Payments/Gateways/*.php' ) as $f ) { require_once $f; }
foreach ( glob( $root . 'includes/Core/Payments/Checkout/*.php' ) as $f ) { require_once $f; }
foreach ( glob( $root . 'includes/Core/Payments/Transaction/*.php' ) as $f ) { require_once $f; }
require_once $root . 'includes/Core/Notifications/NotificationChannel.php';
require_once $root . 'includes/Core/Notifications/Notification.php';
require_once $root . 'includes/Core/Notifications/EmailNotificationChannel.php';
require_once $root . 'includes/Core/Notifications/NotificationManager.php';
require_once $root . 'includes/Core/Audit/AuditLog.php';
require_once $root . 'packs/LawFirm/PostTypes/ConsultationMeta.php';
require_once $root . 'packs/LawFirm/Admin/DashboardMenu.php';
require_once $root . 'packs/LawFirm/Admin/ConsultationAdmin.php';

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Notifications\NotificationManager;
use BusinessBuilderCore\Core\Audit\AuditLog;
use BusinessBuilderCore\Packs\LawFirm\Admin\ConsultationAdmin;

$pass = 0;
$fail = 0;
function check( string $label, bool $cond ): void {
    global $pass, $fail;
    if ( $cond ) { $pass++; echo "  OK   $label\n"; }
    else { $fail++; echo "  FAIL $label\n"; }
}

switch_to_blog( 2 );

$pm = new PaymentManager();

/* Build an isolated consultation + pending transaction. */
$cns = wp_insert_post( array( 'post_type' => 'bb_consultation', 'post_status' => 'publish', 'post_title' => 'P7 Sync Test' ) );
update_post_meta( $cns, '_bb_consultation_payment_required', '1' );
update_post_meta( $cns, '_bb_consultation_payment_status', 'pending' );

$txn = PaymentTransaction::from_array( array(
    'public_ref'  => 'TXN-P7SYNC-' . wp_rand( 100000, 999999 ),
    'object_type' => 'consultation',
    'object_id'   => $cns,
    'gateway'     => 'paymob',
    'amount'      => '1500',
    'currency'    => 'EGP',
    'status'      => 'pending',
) );
$txn = $pm->persist( $txn );

echo "== setup ==\n";
check( 'transaction persisted', $txn->id > 0 );
check( 'transaction starts pending', 'pending' === $txn->status );

/* Run the admin action (protected method invoked as the handler does). */
$admin = new ConsultationAdmin( new NotificationManager(), new AuditLog() );
$ref = new ReflectionMethod( ConsultationAdmin::class, 'do_mark_paid' );
$ref->setAccessible( true );
$ref->invoke( $admin, (int) $cns );

$cns_state = (string) get_post_meta( $cns, '_bb_consultation_payment_status', true );
$fresh     = $pm->store()->find( $txn->id );
$txn_state = $fresh instanceof PaymentTransaction ? $fresh->status : 'MISSING';

echo "== after mark-paid ==\n";
check( 'consultation marked paid', 'paid' === $cns_state );
check( 'transaction ALSO paid (no divergence)', 'paid' === $txn_state );

/* Idempotency: running it again must not error or change state. */
$ref->invoke( $admin, (int) $cns );
$fresh2     = $pm->store()->find( $txn->id );
$txn_state2 = $fresh2 instanceof PaymentTransaction ? $fresh2->status : 'MISSING';
check( 'repeat mark-paid is idempotent (still paid)', 'paid' === $txn_state2 );

/* No-transaction case: direct meta write still works. */
$cns2 = wp_insert_post( array( 'post_type' => 'bb_consultation', 'post_status' => 'publish', 'post_title' => 'P7 No Txn' ) );
update_post_meta( $cns2, '_bb_consultation_payment_required', '1' );
update_post_meta( $cns2, '_bb_consultation_payment_status', 'pending' );
$ref->invoke( $admin, (int) $cns2 );
check( 'no-transaction consultation still marked paid', 'paid' === (string) get_post_meta( $cns2, '_bb_consultation_payment_status', true ) );

/* Cleanup. */
wp_delete_post( $cns, true );
wp_delete_post( $cns2, true );
if ( $fresh instanceof PaymentTransaction ) { wp_delete_post( $fresh->id, true ); }

restore_current_blog();

echo "\nPASS: $pass   FAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );
exit( 0 === $fail ? 0 : 1 );