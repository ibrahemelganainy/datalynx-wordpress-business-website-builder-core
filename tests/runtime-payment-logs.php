<?php
/**
 * Verify the Law Firm dashboard menu registers the expected screens, and
 * that the Payment Logs screen renders with a real (failed) transaction.
 */
define( 'WP_USE_THEMES', false );
define( 'WP_ADMIN', true );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\Transaction\Reference;
use BusinessBuilderCore\Packs\LawFirm\Admin\DashboardMenu;
use BusinessBuilderCore\Packs\LawFirm\Admin\PaymentLogsAdmin;
use BusinessBuilderCore\Packs\LawFirm\Admin\ManualPaymentsAdmin;

switch_to_blog( 2 );

/* Set an administrator so the capability checks pass in this CLI context. */
$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ));

if ( ! empty( $admins )) {
    wp_set_current_user( (int) $admins[0]->ID );
}

$pass = 0;
$fail = 0;

function check( string $label, bool $cond ): void {
    global $pass, $fail;

    if ( $cond ) {
        $pass++;
        echo "  OK   $label\n";
    } else {
        $fail++;
        echo "  FAIL $label\n";
    }
}

$pm = new PaymentManager();

/* Seed a FAILED transaction with a code + reason (Payment Logs must show it). */
$cns = wp_insert_post( array( 'post_type' => 'bb_consultation', 'post_status' => 'publish', 'post_title' => 'Logs Test' ), true );
update_post_meta( $cns, '_bb_consultation_public_reference', 'CNS-LOGSTEST' );

$fail_ref = Reference::transaction();

$pm->persist(
    PaymentTransaction::from_array(
        array(
            'public_ref'  => $fail_ref,
            'object_type' => 'consultation',
            'object_id'   => $cns,
            'gateway'     => 'paypal',
            'amount'      => '10.00',
            'currency'    => 'EGP',
            'status'      => 'failed',
            'meta'        => array( 'failure_code' => 'gateway_request_failed', 'failure_reason' => 'Test reason' ),
        )
    )
);

echo "== Payment Logs screen ==\n";

$logs = new PaymentLogsAdmin( $pm );
$menu = new DashboardMenu();
$logs->attach_screen( $menu );

ob_start();
$logs->render_page();
$out = ob_get_clean();

check( 'Payment Logs renders', false !== strpos( $out, 'Payment Logs' ) );
check( 'shows the failed transaction reference', false !== strpos( $out, $fail_ref ) );
check( 'shows the consultation reference', false !== strpos( $out, 'CNS-LOGSTEST' ) );
check( 'shows the failure code', false !== strpos( $out, 'gateway_request_failed' ) );
check( 'shows the failure reason', false !== strpos( $out, 'Test reason' ) );
check( 'shows a Receipt action', false !== strpos( $out, 'Receipt' ) );
check( 'has a gateway filter', false !== strpos( $out, 'bb_pl_gateway' ) );
check( 'has a status filter', false !== strpos( $out, 'bb_pl_status' ) );

echo "\n== Manual Payments screen (manual only) ==\n";

/* A MANUAL transaction on hold, with a proof + manual reference. */
$wallet_pending = Reference::transaction();

$pm->persist(
    PaymentTransaction::from_array(
        array(
            'public_ref'  => $wallet_pending,
            'object_type' => 'consultation',
            'object_id'   => $cns,
            'gateway'     => 'wallet',
            'amount'      => '250.00',
            'currency'    => 'EGP',
            'status'      => 'on_hold',
            'meta'        => array( 'manual_reference' => 'WALLET-TRX-42' ),
        )
    )
);

$mp = new ManualPaymentsAdmin(
    $pm,
    new \BusinessBuilderCore\Core\Payments\Checkout\TransactionSynchronizer( $pm, new \BusinessBuilderCore\Core\Audit\AuditLog() ),
    new \BusinessBuilderCore\Core\Notifications\NotificationManager(),
    new \BusinessBuilderCore\Core\Audit\AuditLog()
);
$mp->attach_screen( $menu );

ob_start();
$mp->render_page();
$mo = ob_get_clean();

check( 'Manual Payments renders', false !== strpos( $mo, 'Manual Payments' ) );
check( 'shows the manual transaction reference', false !== strpos( $mo, 'WALLET-TRX-42' ) );
check( 'shows the entity type (Consultation)', false !== strpos( $mo, 'Consultation' ) );
check( 'has an Approve action', false !== strpos( $mo, 'bb_manual_payment_approve' ) );
check( 'has a Reject action', false !== strpos( $mo, 'bb_manual_payment_reject' ) );
check( 'does NOT show API failures here', false === strpos( $mo, 'gateway_request_failed' ) );

echo "\nPASS: $pass  FAIL: $fail\n";
echo ( 0 === $fail ? "RESULT: OK\n" : "RESULT: FAIL\n" );

/* Cleanup */
wp_delete_post( $cns, true );
delete_option( 'bb_payment_transactions' );

exit( 0 === $fail ? 0 : 1 );
