<?php
/*
 * Real WP runtime test: LawFirm enterprise dashboard (Phase B).
 *
 * Boots the plugin on a real site, drives admin_menu, and verifies:
 *   - the Law Firm top-level menu exists (independent of Business Builder)
 *   - the auxiliary screens (payments, notifications) are attached
 *   - the CPT/taxonomy screens are relocated under it
 *   - DashboardStats returns real (not fake) numbers
 *   - DashboardAdmin renders HTML without fatal errors
 */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

// Ensure the LawFirm pack is active on this site.
switch_to_blog( 2 );
update_option( 'bb_business_type', 'law_firm' );

global $menu, $submenu;

$menu    = array();
$submenu = array();

if ( ! function_exists( 'bb_boot_menu' ) ) {
    /**
     * Trigger admin_menu the way wp-admin/menu.php does.
     */
    function bb_boot_menu(): void {
        do_action( 'admin_menu' );
    }
}

// Fake a logged-in admin so capability checks pass.
wp_set_current_user( 1 );

/*
 * The plugin booted during wp-load BEFORE we set the business type, so
 * the pack was skipped. Register it now, exactly as PackManager would
 * after boot_current() sees law_firm.
 */
$pack = new BusinessBuilderCore\Packs\LawFirm\LawFirmPack(
    new BusinessBuilderCore\Builder\SectionRegistry(),
    new BusinessBuilderCore\Builder\PageManager( new BusinessBuilderCore\Builder\SectionManager() ),
    new BusinessBuilderCore\Core\Notifications\NotificationManager(),
    new BusinessBuilderCore\Core\Audit\AuditLog(),
    new BusinessBuilderCore\Core\Payments\PaymentManager(),
    new BusinessBuilderCore\Settings\SiteSettings()
);
$pack->register();

/*
 * init already fired during wp-load, so the pack's CPT/taxonomy
 * registrations (hooked to init) would not run. Invoke them directly
 * so the admin menu build sees the relocated screens.
 */
( new BusinessBuilderCore\Packs\LawFirm\PostTypes\Consultation() )->register_post_type();
( new BusinessBuilderCore\Packs\LawFirm\Appointments\Appointment() )->register_post_type();
( new BusinessBuilderCore\Packs\LawFirm\PostTypes\Lawyer() )->register_post_type();
( new BusinessBuilderCore\Packs\LawFirm\PostTypes\LegalService() )->register_post_type();
( new BusinessBuilderCore\Packs\LawFirm\Taxonomies\PracticeArea() )->register_taxonomy();

bb_boot_menu();

$top_slugs = array();
foreach ((array) $menu as $item ) {
    if ( isset( $item[2] ) ) {
        $top_slugs[] = $item[2];
    }
}

echo 'has bb-law-firm top menu: ' . var_export( in_array( 'bb-law-firm', $top_slugs, true ), true ) . PHP_EOL;
echo 'has business-builder top menu: ' . var_export( in_array( 'business-builder', $top_slugs, true ), true ) . PHP_EOL;

$sub = isset( $submenu['bb-law-firm'] ) ? $submenu['bb-law-firm'] : array();
$sub_slugs = array();
foreach ((array) $sub as $row ) {
    if ( isset( $row[2] ) ) {
        $sub_slugs[] = $row[2];
    }
}
echo 'law-firm submenus: ' . implode( ', ', $sub_slugs ) . PHP_EOL;
echo 'payments attached: ' . var_export( in_array( 'bb-law-firm-payments', $sub_slugs, true ), true ) . PHP_EOL;
echo 'notifications attached: ' . var_export( in_array( 'bb-law-firm-notifications', $sub_slugs, true ), true ) . PHP_EOL;
echo 'consultations under law-firm: ' . var_export( in_array( 'edit.php?post_type=bb_consultation', $sub_slugs, true ), true ) . PHP_EOL;

// DashboardStats on real data.
use BusinessBuilderCore\Packs\LawFirm\Admin\DashboardStats;
use BusinessBuilderCore\Core\Payments\PaymentManager;

$stats = new DashboardStats( new PaymentManager() );

$kpis = $stats->get_kpis();
echo 'kpi cards: ' . count( $kpis ) . PHP_EOL;

$cons = $stats->consultation_metrics();
echo 'consultations total=' . $cons['total'] . ' new=' . $cons['new'] . ' paid=' . $cons['paid'] . PHP_EOL;

$series = $stats->consultations_over_time( 6 );
echo 'over-time labels=' . count( $series['labels'] ) . ' values=' . count( $series['values'] ) . PHP_EOL;

$area = $stats->consultations_by_practice_area( 6 );
echo 'by-area labels: ' . implode( ' | ', $area['labels'] ) . PHP_EOL;

$rev = $stats->revenue_metrics();
echo 'revenue total=' . $rev['total'] . ' currency=' . $rev['currency'] . PHP_EOL;

$actions = $stats->action_center( 6 );
echo 'action items: ' . count( $actions ) . PHP_EOL;

// Render the dashboard (must not fatal).
ob_start();
$menu_obj  = new \BusinessBuilderCore\Packs\LawFirm\Admin\DashboardMenu();
$notif     = new \BusinessBuilderCore\Core\Notifications\NotificationManager();
$dash      = new \BusinessBuilderCore\Packs\LawFirm\Admin\DashboardAdmin( $stats, $notif, $menu_obj );
$dash->render_page();
$html = ob_get_clean();

echo 'dashboard html bytes: ' . strlen( $html ) . PHP_EOL;
echo 'has kpi grid: ' . var_export( false !== strpos( $html, 'bb-kpi-grid' ), true ) . PHP_EOL;
echo 'has action center: ' . var_export( false !== strpos( $html, 'bb-action-center' ), true ) . PHP_EOL;
echo 'has charts grid: ' . var_export( false !== strpos( $html, 'bb-charts-grid' ), true ) . PHP_EOL;
echo 'has empty state: ' . var_export( false !== strpos( $html, 'bb-empty' ) || false !== strpos( $html, 'bb-bar-chart' ), true ) . PHP_EOL;

restore_current_blog();
echo 'DONE' . PHP_EOL;
