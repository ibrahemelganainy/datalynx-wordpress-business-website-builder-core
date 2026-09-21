<?php
/**
 * Runtime test: activity timeline (AuditLog) + multisite isolation.
 *
 * Run: php tests/runtime-activity-and-isolation.php
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

use BusinessBuilderCore\Core\Audit\AuditLog;
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

/* Start clean on this site. */
delete_option( 'bb_audit_log' );
delete_option( 'bb_notifications_feed' );
delete_option( 'bb_notifications_dedupe' );

$log = new AuditLog();

$log->record( 'payment.manual_approved', 'payment', 5, array( 'gateway' => 'instapay', 'reference' => 'TXN-CC' ) );
$log->record( 'consultation.created', 'consultation', 9, array( 'reference' => 'CNS-DDDD' ) );
$log->record( 'appointment.created', 'appointment', 3, array( 'reference' => 'APT-EE' ) );

echo "== activity query + categories ==\n";
check( 'all entries', 3 === count( $log->query( 'all', '', 0, 50 ) ));
check( 'payment category', 1 === count( $log->query( 'payment', '', 0, 50 ) ));
check( 'consultation category', 1 === count( $log->query( 'consultation', '', 0, 50 ) ));
check( 'appointment category', 1 === count( $log->query( 'appointment', '', 0, 50 ) ));
check( 'system category', 0 === count( $log->query( 'system', '', 0, 50 ) ));

echo "== activity search + reference ==\n";
$rows = $log->query( 'all', 'TXN-CC', 0, 50 );
check( 'search finds the reference', 1 === count( $rows ) );
check( 'reference stored on the entry', isset( $rows[0]['reference'] ) && 'TXN-CC' === $rows[0]['reference'] );

echo "== multisite isolation ==\n";

$mgr = new NotificationManager();
$mgr->dispatch( new Notification( 'consultation.new', 'Site A consultation', '', '', 77, 'iso:a', array( 'category' => 'consultation', 'entity_type' => 'consultation', 'entity_id' => 77, 'reference' => 'CNS-SITEA' ) ));
$site_a_count = ( new NotificationManager() )->unread_count();
$site_a_ids   = wp_list_pluck( ( new NotificationManager() )->all(), 'reference' );

$switched = function_exists( 'switch_to_blog' );

if ( $switched ) {
    switch_to_blog( get_current_blog_id() === 2 ? 1 : 2 );

    $other = new NotificationManager();
    $other_refs = wp_list_pluck( $other->all(), 'reference' );

    check( 'site B does not see site A notification', ! in_array( 'CNS-SITEA', $other_refs, true ) );

    /* Create a notification on site B and confirm it does not leak back. */
$other->dispatch( new Notification( 'appointment.created', 'Site B appointment', '', '', 88, 'iso:b', array( 'category' => 'appointment', 'entity_type' => 'appointment', 'entity_id' => 88, 'reference' => 'APT-SITEB' ) ));

    /* Return to site A. */
    switch_to_blog( 2 );

    $back_refs = wp_list_pluck( ( new NotificationManager() )->all(), 'reference' );

    check( 'site A still has its own notification', in_array( 'CNS-SITEA', $back_refs, true ) );
    check( 'site A does not see site B notification', ! in_array( 'APT-SITEB', $back_refs, true ) );

    /* Clean site B. */
    switch_to_blog( get_current_blog_id() === 2 ? 1 : 2 );
    delete_option( 'bb_notifications_feed' );
    delete_option( 'bb_notifications_dedupe' );
    switch_to_blog( 2 );
} else {
    echo "  SKIP multisite (single-site environment)\n";
}

check( 'site A unread unchanged after site B write', $site_a_count === ( new NotificationManager() )->unread_count() );

/* Cleanup. */
delete_option( 'bb_audit_log' );
delete_option( 'bb_notifications_feed' );
delete_option( 'bb_notifications_dedupe' );

echo "\nRESULT: $pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
