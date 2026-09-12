<?php
/* Real WP runtime: NotificationManager + AuditLog on blog 2. */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

require_once ABSPATH . 'wp-content/plugins/business-builder-core/includes/Core/Notifications/NotificationChannel.php';
require_once ABSPATH . 'wp-content/plugins/business-builder-core/includes/Core/Notifications/Notification.php';
require_once ABSPATH . 'wp-content/plugins/business-builder-core/includes/Core/Notifications/EmailNotificationChannel.php';
require_once ABSPATH . 'wp-content/plugins/business-builder-core/includes/Core/Notifications/NotificationManager.php';
require_once ABSPATH . 'wp-content/plugins/business-builder-core/includes/Core/Audit/AuditLog.php';

use BusinessBuilderCore\Core\Notifications\Notification;
use BusinessBuilderCore\Core\Notifications\NotificationManager;
use BusinessBuilderCore\Core\Audit\AuditLog;

switch_to_blog( 2 );

// Clean slate for the test.
delete_option( 'bb_notifications_feed' );
delete_option( 'bb_notifications_dedupe' );

$nm = new NotificationManager();

$n = new Notification( 'test.event', 'Test subject', 'Test body', '', 123, 'dedupe-abc' );
$r1 = $nm->dispatch( $n );
$r2 = $nm->dispatch( new Notification( 'test.event', 'Test subject', 'Test body', '', 123, 'dedupe-abc' ) );

echo 'first dispatch: ' . var_export( $r1, true ) . PHP_EOL;
echo 'duplicate dispatch: ' . var_export( $r2, true ) . PHP_EOL;
echo 'feed count: ' . count( $nm->feed() ) . PHP_EOL;
echo 'unread: ' . $nm->unread_count() . PHP_EOL;

$nm->mark_all_read();
echo 'unread after mark_all_read: ' . $nm->unread_count() . PHP_EOL;

$audit = new AuditLog();
$audit->record( 'test.action', 'consultation', 55, array( 'status' => 'contacted', 'api_key' => 'SHOULD_BE_SCRUBBED' ) );
$recent = $audit->recent( 1 );
echo 'audit action: ' . $recent[0]['action'] . PHP_EOL;
echo 'audit scrubbed key: ' . $recent[0]['context']['api_key'] . PHP_EOL;

// Multisite isolation: blog 1 must NOT see blog 2 notifications.
switch_to_blog(1 );
$nm1 = new NotificationManager();
echo 'blog1 feed count (must be 0): ' . count( $nm1->feed() ) . PHP_EOL;

restore_current_blog();

// Cleanup test data on blog 2.
switch_to_blog( 2 );
delete_option( 'bb_notifications_feed' );
delete_option( 'bb_notifications_dedupe' );
delete_option( 'bb_audit_log' );
restore_current_blog();

echo 'DONE' . PHP_EOL;
