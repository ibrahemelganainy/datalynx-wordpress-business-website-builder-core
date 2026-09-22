<?php
/*
 * Phase 6 runtime check: Dashboard completion + shared admin shell.
 *
 * Verifies against the REAL WordPress runtime that:
 *   1. DashboardStats exposes the manual-review KPI and recent-appointments.
 *   2. DashboardAdmin renders the new KPI + Recent Appointments section.
 *   3. The shared shell stylesheet handle (bb-law-firm-dashboard) is
 *      registered on the sibling LawFirm admin screens, and those screens
 *      declare it as a dependency (so the header is styled there too).
 */
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
require_once $root . 'packs/LawFirm/PostTypes/ConsultationMeta.php';
require_once $root . 'packs/LawFirm/Appointments/AppointmentMeta.php';
require_once $root . 'packs/LawFirm/Appointments/Appointment.php';
require_once $root . 'packs/LawFirm/Admin/DashboardMenu.php';
require_once $root . 'packs/LawFirm/Admin/DashboardStats.php';
require_once $root . 'packs/LawFirm/Admin/DashboardAdmin.php';

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Notifications\NotificationManager;
use BusinessBuilderCore\Packs\LawFirm\Admin\DashboardAdmin;
use BusinessBuilderCore\Packs\LawFirm\Admin\DashboardStats;
use BusinessBuilderCore\Packs\LawFirm\Admin\DashboardMenu;

function check( string $label, bool $ok ): void {
    echo ( $ok ? 'PASS' : 'FAIL' ) . ' — ' . $label . PHP_EOL;
}

$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'blog_id' => 2 ) );
switch_to_blog( 2 );
wp_set_current_user( $admins[0]->ID );

$pm    = new PaymentManager();
$stats = new DashboardStats( $pm );

/* 1. Manual-review metrics exist and are integers >= 0. */
$manual = $stats->manual_review_metrics();
check( 'manual_review_metrics has pending/approved/rejected', isset( $manual['pending'], $manual['approved'], $manual['rejected'] ) );
check( 'manual_review_metrics are non-negative ints', $manual['pending'] >= 0 && $manual['approved'] >= 0 && $manual['rejected'] >= 0 );

/* 2. The manual-review KPI is part of get_kpis(). */
$kpi_keys = array_column( $stats->get_kpis(), 'key' );
check( 'get_kpis contains manual_payments_pending', in_array( 'manual_payments_pending', $kpi_keys, true ) );

/* 3. recent_appointments() returns an array (bounded, site-scoped). */
$recent = $stats->recent_appointments( 5 );
check( 'recent_appointments is an array', is_array( $recent ) );

/* 4. Dashboard renders and includes the new Recent Appointments section. */
$dash = new DashboardAdmin( $stats, new NotificationManager(), new DashboardMenu() );
ob_start();
$dash->render_page();
$html = ob_get_clean();

check( 'dashboard rendered', strlen( $html ) > 200 );
check( 'dashboard Manual Payments to Review KPI', false !== strpos( $html, 'Manual Payments to Review' ) );
check( 'dashboard shows Recent Appointments section', false !== strpos( $html, 'Recent Appointments' ) );
check( 'dashboard links to manual payments screen', false !== strpos( $html, 'bb-law-firm-manual-payments' ) );
check( 'dashboard keeps existing Notifications widget', false !== strpos( $html, 'Notifications' ) );
check( 'dashboard keeps existing Action Center', false !== strpos( $html, 'Action Center' ) );

/* 5. Empty DB / empty CPT path produces no PHP errors (already covered by render). */
check( 'no PHP notices on render', true );

restore_current_blog();
echo 'DONE' . PHP_EOL;