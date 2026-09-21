<?php
/**
 * Runtime test: Notifications & Activity centre.
 *
 * Covers event categories, filters, search, pagination, read state and the
 * manual-payment reference fix.
 *
 * Run: php tests/runtime-notifications-center.php
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Core\Notifications\Notification;
use BusinessBuilderCore\Core\Notifications\NotificationManager;

if ( function_exists( 'switch_to_blog' )) {
    switch_to_blog( 2 );
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

/* Start from a clean feed so counts are deterministic. */
delete_option( 'bb_notifications_feed' );
delete_option( 'bb_notifications_dedupe' );

$mgr = new NotificationManager();

echo "== category_for() ==\n";
check( 'consultation.new => consultation', 'consultation' === NotificationManager::category_for( 'consultation.new' ) );
check( 'appointment.created => appointment', 'appointment' === NotificationManager::category_for( 'appointment.created' ) );
check( 'payment.paid => payment', 'payment' === NotificationManager::category_for( 'payment.paid' ) );
check( 'payment.manual_submitted => manual_payment', 'manual_payment' === NotificationManager::category_for( 'payment.manual_submitted' ) );
check( 'unknown => system', 'system' === NotificationManager::category_for( 'something.else' ) );

echo "== dispatch stores notifications with entity + refs ==\n";

$mgr->dispatch(
    new Notification(
        'consultation.new',
        'New consultation',
        'Corporate',
        '',
        101,
        'c:101:new',
        array(
            'category'    => 'consultation',
            'entity_type' => 'consultation',
            'entity_id'   => 101,
            'reference'   => 'CNS-AAAA',
            'customer'    => 'Ibrahim',
            'actionable'  => true,
        )
    )
);

$mgr->dispatch(
    new Notification(
        'payment.manual_submitted',
        'Manual payment requires verification',
        'Awaiting',
        '',
        101,
        'pm:101:1',
        array(
            'category'    => 'manual_payment',
            'entity_type' => 'consultation',
            'entity_id'   => 101,
            'reference'   => 'CNS-AAAA',
            'payment_ref' => 'TXN-BBBB',
            'gateway'     => 'instapay',
            'amount'      => '250',
            'currency'    => 'EGP',
            'actionable'  => true,
        )
    )
);

$mgr->dispatch(
    new Notification(
        'payment.manual_approved',
        'Manual payment approved',
        '',
        '',
        101,
        'pm:approved:1',
        array(
            'category'    => 'manual_payment',
            'entity_type' => 'consultation',
            'entity_id'   => 101,
            'reference'   => 'CNS-AAAA',
            'payment_ref' => 'TXN-BBBB',
            'amount'      => '250',
            'currency'    => 'EGP',
        )
    )
);

$all = $mgr->all();
check( 'three notifications stored', 3 === count( $all ) );
check( 'unread count is three', 3 === $mgr->unread_count() );

echo "== filters ==\n";
check( 'consultation filter => 1', 1 === count( $mgr->query( 'consultation' ) ));
check( 'manual_payment filter => 2', 2 === count( $mgr->query( 'manual_payment' ) ));
check( 'payment super-set => 2', 2 === count( $mgr->query( 'payment' ) ));
check( 'unread filter => 3', 3 === count( $mgr->query( 'unread' ) ));

echo "== search ==\n";
check( 'search by customer', 1 === count( $mgr->query( 'all', 'Ibrahim' ) ));
check( 'search by payment ref', 2 === count( $mgr->query( 'all', 'TXN-BBBB' ) ));
check( 'search by object ref', 3 === count( $mgr->query( 'all', 'CNS-AAAA' ) ));
check( 'search by gateway', 1 === count( $mgr->query( 'all', 'instapay' )) );
check( 'search miss => 0', 0 === count( $mgr->query( 'all', 'zzz-nope' ) ));

echo "== pagination ==\n";
check( 'limit 1 => 1', 1 === count( $mgr->query( 'all', '', 0, 1 ) ));
check( 'offset 1 => 2', 2 === count( $mgr->query( 'all', '', 0, 50, 1 ) ));
check( 'offset 2 => 1', 1 === count( $mgr->query( 'all', '', 0, 50, 2 ) ));
check( 'count total => 3', 3 === $mgr->count( 'all' ) );
check( 'count manual => 2', 2 === $mgr->count( 'manual_payment' ) );

echo "== read state persists ==\n";
$first_id = (string) $all[0]['id'];
$mgr->mark_read( $first_id );
check( 'unread drops to two', 2 === ( new NotificationManager() )->unread_count() );
$mgr->mark_all_read();
check( 'all read => zero unread', 0 === ( new NotificationManager() )->unread_count() );

echo "== manual_payment rows carry the TXN receipt reference ==\n";
$manual = $mgr->query( 'manual_payment', '', 0, 1 );
check( 'has payment_ref TXN-BBBB', isset( $manual[0]['payment_ref'] ) && 'TXN-BBBB' === $manual[0]['payment_ref'] );
check( 'object reference kept for context', 'CNS-AAAA' === $manual[0]['reference'] );
check( 'amount stored', '250' === $manual[0]['amount'] );

echo "== actionable helper ==\n";
check( 'consultation.new actionable', NotificationManager::is_actionable_event( 'consultation.new' ) );
check( 'payment.manual_submitted actionable', NotificationManager::is_actionable_event( 'payment.manual_submitted' ) );
check( 'payment.paid not actionable', ! NotificationManager::is_actionable_event( 'payment.paid' ) );

/* Cleanup. */
delete_option( 'bb_notifications_feed' );
delete_option( 'bb_notifications_dedupe' );

echo "\nRESULT: $pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
