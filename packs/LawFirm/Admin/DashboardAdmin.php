<?php

namespace BusinessBuilderCore\Packs\LawFirm\Admin;

use BusinessBuilderCore\Core\Notifications\NotificationManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * LawFirm enterprise dashboard.
 *
 * Renders the LawFirm operational control center on the independent
 * top-level "Law Firm" menu (owned by DashboardMenu). It is NOT a
 * submenu of Business Builder (spec: Part 1).
 *
 * Server-rendered from REAL data supplied by DashboardStats and
 * NotificationManager: KPI cards, analytics (CSS bar charts), Action
 * Center, Quick Actions and a recent activity feed.
 *
 * Direction is driven by WordPress: is_rtl() adds a class so the
 * stylesheet flips layout; the markup is direction-neutral, so Arabic
 * and English share one implementation (spec: Part 11).
 */
class DashboardAdmin {

    private const PAGE_SLUG = DashboardMenu::MENU_SLUG;
    private const ACTION_READ_ALL = 'bb_mark_notifications_read';
    private const NONCE_READ_ALL = 'bb_mark_notifications_read';

    protected DashboardStats $stats;
    protected NotificationManager $notifications;
    protected DashboardMenu $menu;

    public function __construct(
        DashboardStats $stats,
        NotificationManager $notifications,
        DashboardMenu $menu
    ) {
        $this->stats         = $stats;
        $this->notifications = $notifications;
        $this->menu          = $menu;
    }

    public function register(): void {

        add_action( 'bb_law_firm_render_dashboard', array( $this, 'render_page' ) );

        /*
         * Register/enqueue the shared shell stylesheet at an EARLY priority
         * so the handle exists before the sibling LawFirm screens (whose own
         * admin_enqueue_scripts callbacks may be registered earlier) declare
         * it as a dependency of their stylesheet.
         */
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 5 );
        add_action( 'admin_post_' . self::ACTION_READ_ALL, array( $this, 'handle_mark_read' ) );
    }

    /**
     * Enqueue / register the dashboard stylesheet.
     *
     * The same stylesheet owns the SHARED admin shell (.bb-dashboard-header,
     * .bb-btn*) reused by the Notifications, Payment Logs and Manual Payments
     * screens. It is therefore REGISTERED (not just enqueued) on every LawFirm
     * admin screen so those screens can declare it as a dependency of their own
     * stylesheet and render a correctly styled header. On the dashboard page
     * itself it is enqueued directly.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( string $hook ): void {

        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

        /* Which screens belong to the LawFirm console (share the shell). */
        $lawfirm_screens = array(
            self::PAGE_SLUG,
            'bb-law-firm-notifications',
            'bb-law-firm-payment-logs',
            'bb-law-firm-manual-payments',
            'bb-law-firm-payments',
        );

        if ( ! in_array( $page, $lawfirm_screens, true ) ) {
            return;
        }

        /*
         * Register first (idempotent) so any sibling screen can depend on
         * this handle; enqueue only when we are actually on the dashboard.
         */
        wp_register_style(
            'bb-law-firm-dashboard',
            BB_CORE_URL . 'assets/css/admin/dashboard.css',
            array(),
            BB_CORE_VERSION
        );

        if ( self::PAGE_SLUG === $page ) {
            wp_enqueue_style( 'bb-law-firm-dashboard' );
        }
    }

    public function render_page(): void {

        if ( ! current_user_can( DashboardMenu::capability() ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'business-builder' ) );
        }

        $kpis    = $this->stats->get_kpis();
        $actions = $this->stats->action_center( 6 );
        $recent  = $this->stats->recent_consultations( 6 );
        $appts   = $this->stats->recent_appointments( 5 );
        $unread  = $this->notifications->unread_count();

        $charts = array(
            array( __( 'Consultations Over Time', 'business-builder' ), $this->stats->consultations_over_time( 6 ), __( 'No consultation data is available yet.', 'business-builder' ), 'number' ),
            array( __( 'Consultations by Status', 'business-builder' ), $this->stats->consultations_by_status(), __( 'No consultation data is available yet.', 'business-builder' ), 'number' ),
            array( __( 'Consultations by Practice Area', 'business-builder' ), $this->stats->consultations_by_practice_area( 6 ), __( 'No practice area data is available yet.', 'business-builder' ), 'number' ),
            array( __( 'Appointments Over Time', 'business-builder' ), $this->stats->appointments_over_time( 6 ), __( 'No appointment data is available yet.', 'business-builder' ), 'number' ),
            array( __( 'Appointments by Status', 'business-builder' ), $this->stats->appointments_by_status(), __( 'No appointment data is available yet.', 'business-builder' ), 'number' ),
            array( __( 'Revenue Over Time', 'business-builder' ), $this->stats->revenue_over_time( 6 ), __( 'No revenue data is available yet.', 'business-builder' ), 'money' ),
            array( __( 'Revenue by Payment Gateway', 'business-builder' ), $this->stats->revenue_by_gateway(), __( 'No payment data is available yet.', 'business-builder' ), 'money' ),
            array( __( 'Paid vs Unpaid Consultations', 'business-builder' ), $this->stats->paid_vs_unpaid_consultations(), __( 'No consultation data is available yet.', 'business-builder' ), 'number' ),
        );

        $rtl_class = is_rtl() ? ' bb-rtl' : ' bb-ltr';

        ?>
        <div class="wrap bb-dashboard<?php echo esc_attr( $rtl_class ); ?>">

            <header class="bb-dashboard-header">
                <div class="bb-dashboard-title">
                    <h1><?php esc_html_e( 'Law Firm Dashboard', 'business-builder' ); ?></h1>
                    <p><?php esc_html_e( 'Your operational control center: requests, bookings, payments and activity for this site.', 'business-builder' ); ?></p>
                </div>
                <div class="bb-dashboard-header-actions">
                    <a class="bb-btn bb-btn-ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer">
                        <span class="dashicons dashicons-external"></span>
                        <?php esc_html_e( 'View Site', 'business-builder' ); ?>
                    </a>
                    <a class="bb-btn bb-btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ); ?>">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e( 'Refresh', 'business-builder' ); ?>
                    </a>
                </div>
            </header>

            <?php $this->render_kpis( $kpis ); ?>

            <div class="bb-dashboard-columns">

                <div class="bb-dashboard-main">
                    <?php $this->render_charts( $charts ); ?>
                    <?php $this->render_quick_actions(); ?>
                </div>

                <aside class="bb-dashboard-side">
                    <?php $this->render_notifications_widget(); ?>
                    <?php $this->render_action_center( $actions ); ?>
                    <?php $this->render_activity( $recent, $unread ); ?>
                    <?php $this->render_recent_appointments( $appts ); ?>
                </aside>

            </div>

        </div>
        <?php
    }

    /**
     * Notifications widget.
     *
     * Pulls the recent notification feed from the SAME NotificationManager
     * the Notifications & Activity page uses — there is no second store.
     * Shows the unread badge, the latest items with a contextual action and
     * a "View All" link to the notification centre.
     */
    protected function render_notifications_widget(): void {

        $items  = $this->notifications->feed( 5 );
        $unread = $this->notifications->unread_count();
        $center = admin_url( 'admin.php?page=bb-law-firm-notifications' );

        echo '<section class="bb-card bb-notifications-widget">';
        echo '<h2 class="bb-card-title">';
        echo '<span class="dashicons dashicons-bell"></span> ';
        echo esc_html__( 'Notifications', 'business-builder' );

        if ( $unread > 0 ) {
            echo ' <span class="bb-badge bb-badge-unread">' . esc_html( (string) $unread ) . '</span>';
        }

        echo '</h2>';

        if ( empty( $items ) ) {
            echo '<div class="bb-empty">';
            echo '<span class="dashicons dashicons-yes-alt"></span>';
            echo '<p>' . esc_html__( 'You are all caught up.', 'business-builder' ) . '</p>';
            echo '</div>';
            echo '</section>';
            return;
        }

        echo '<ul class="bb-notif-widget-list">';

        foreach ( $items as $item ) {

            $subject = isset( $item['subject'] ) ? (string) $item['subject'] : '';
            $event   = isset( $item['event'] ) ? sanitize_key( (string) $item['event'] ) : '';
            $time    = isset( $item['time'] ) ? (int) $item['time'] : 0;
            $type    = isset( $item['entity_type'] ) ? (string) $item['entity_type'] : '';
            $eid     = isset( $item['entity_id'] ) ? (int) $item['entity_id'] : 0;
            $read    = ! empty( $item['read'] );

            $timeago = $time > 0
                ? sprintf(
                    /* translators: %s: human time diff */
                    __( '%s ago', 'business-builder' ),
                    human_time_diff( $time, time() )
                )
                : '';

            /* A real, resolvable action — never a dead link. */
            $action_url   = '';
            $action_label = __( 'View', 'business-builder' );

            if ( 'payment.manual_submitted' === $event ) {
                $action_url   = admin_url( 'admin.php?page=bb-law-firm-manual-payments' );
                $action_label = __( 'Review', 'business-builder' );
            } elseif ( $eid > 0 && 'appointment' === $type ) {
                $action_url   = NotificationsAdmin::edit_url_for( 'bb_appointment', $eid );
                $action_label = __( 'View', 'business-builder' );
            } elseif ( $eid > 0 && 'consultation' === $type ) {
                $action_url   = NotificationsAdmin::edit_url_for( 'bb_consultation', $eid );
                $action_label = __( 'View', 'business-builder' );
            }

            echo '<li class="bb-notif-widget-item' . ( $read ? ' is-read' : ' is-unread' ) . '">';
            echo '<span class="bb-notif-widget-title">' . esc_html( $subject ) . '</span>';
            echo '<span class="bb-notif-widget-time">' . esc_html( $timeago ) . '</span>';

            if ( '' !== $action_url ) {
                echo '<a class="bb-notif-widget-action" href="' . esc_url( $action_url ) . '">' . esc_html( $action_label ) . '</a>';
            }

            echo '</li>';
        }

        echo '</ul>';

        echo '<div class="bb-activity-footer">';
        echo '<a class="bb-btn bb-btn-ghost" href="' . esc_url( $center ) . '">' . esc_html__( 'View All Notifications', 'business-builder' ) . '</a>';
        echo '</div>';
        echo '</section>';
    }

    protected function render_kpis( array $kpis ): void {

        echo '<section class="bb-kpi-grid">';

        foreach ( $kpis as $kpi ) {

            $accent = isset( $kpi['accent'] ) ? (string) $kpi['accent'] : 'primary';
            $url    = isset( $kpi['url'] ) ? (string) $kpi['url'] : '';
            $icon   = isset( $kpi['icon'] ) ? (string) $kpi['icon'] : 'dashicons-chart-bar';
            $label  = isset( $kpi['label'] ) ? (string) $kpi['label'] : '';
            $hint   = isset( $kpi['hint'] ) ? (string) $kpi['hint'] : '';

            /*
             * A KPI is rendered as an <a> when it has a real destination,
             * and as a plain <div> otherwise — never a dead link.
             */
            $tag = '' !== $url ? 'a' : 'div';

            echo '<' . esc_attr( $tag ) . ' class="bb-kpi-card bb-accent-' . esc_attr( $accent ) . '"';
            if ( '' !== $url ) {
                echo ' href="' . esc_url( $url ) . '"';
            }
            echo '>';

            echo '<span class="bb-kpi-top">';
            echo '<span class="bb-kpi-iconchip"><span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span></span>';
            if ( '' !== $url ) {
                echo '<span class="bb-kpi-go dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>';
            }
            echo '</span>';

            echo '<span class="bb-kpi-value">' . esc_html( (string) $kpi['value'] ) . '</span>';
            echo '<span class="bb-kpi-label">' . esc_html( $label ) . '</span>';

            if ( '' !== $hint ) {
                echo '<span class="bb-kpi-hint">' . esc_html( $hint ) . '</span>';
            }

            echo '</' . esc_attr( $tag ) . '>';
        }

        echo '</section>';
    }

    protected function render_charts( array $charts ): void {

        echo '<section class="bb-charts-grid">';

        foreach ( $charts as $chart ) {
            $this->render_chart( (string) $chart[0], is_array( $chart[1] ) ? $chart[1] : array(), (string) $chart[2], (string) $chart[3] );
        }

        echo '</section>';
    }

    protected function render_chart( string $title, array $data, string $empty, string $mode ): void {

        $labels = isset( $data['labels'] ) && is_array( $data['labels'] ) ? $data['labels'] : array();
        $values = isset( $data['values'] ) && is_array( $data['values'] ) ? $data['values'] : array();

        $total = 0.0;
        foreach ( $values as $value ) {
            $total += (float) $value;
        }

        echo '<article class="bb-card bb-chart-card">';
        echo '<h2 class="bb-card-title">' . esc_html( $title ) . '</h2>';

        if ( empty( $labels ) || $total <= 0 ) {
            echo '<div class="bb-empty">';
            echo '<span class="dashicons dashicons-chart-bar"></span>';
            echo '<p>' . esc_html( $empty ) . '</p>';
            echo '</div>';
            echo '</article>';
            return;
        }

        $max = max( array_map( 'floatval', $values ) );
        $max = $max > 0 ? $max : 1;

        echo '<ul class="bb-bar-chart">';

        foreach ( $labels as $index => $label ) {

            $value   = isset( $values[ $index ] ) ? (float) $values[ $index ] : 0.0;
            $percent = max( 2, (int) round( ( $value / $max ) * 100 ) );
            $display = 'money' === $mode ? $this->format_amount( $value, $data ) : number_format_i18n( $value );

            echo '<li class="bb-bar-row">';
            echo '<span class="bb-bar-label">' . esc_html( (string) $label ) . '</span>';
            echo '<span class="bb-bar-track"><span class="bb-bar-fill" style="width:' . esc_attr( (string) $percent ) . '%"></span></span>';
            echo '<span class="bb-bar-value">' . esc_html( $display ) . '</span>';
            echo '</li>';
        }

        echo '</ul>';
        echo '</article>';
    }

    protected function format_amount( float $value, array $data ): string {

        $currency  = isset( $data['currency'] ) ? (string) $data['currency'] : '';
        $formatted = number_format_i18n( $value, 2 );
        $sp        = chr( 32 );

        if ( '' === $currency ) {
            return $formatted;
        }

        if ( class_exists( '\BusinessBuilderCore\Core\Payments\Currencies' ) ) {
            $symbol = \BusinessBuilderCore\Core\Payments\Currencies::symbol( $currency );

            if ( '' !== $symbol ) {
                return $formatted . $sp . $symbol;
            }
        }

        return $formatted . $sp . strtoupper( $currency );
    }

    protected function render_action_center( array $actions ): void {

        echo '<section class="bb-card bb-action-center">';
        echo '<h2 class="bb-card-title"><span class="dashicons dashicons-bell"></span> ' . esc_html__( 'Action Center', 'business-builder' ) . '</h2>';

        if ( empty( $actions ) ) {
            echo '<div class="bb-empty">';
            echo '<span class="dashicons dashicons-yes"></span>';
            echo '<p>' . esc_html__( 'Nothing needs your attention right now.', 'business-builder' ) . '</p>';
            echo '</div>';
            echo '</section>';
            return;
        }

        echo '<ul class="bb-action-list">';

        foreach ( $actions as $item ) {

            echo '<li class="bb-action-item">';
            echo '<div class="bb-action-info">';
            echo '<strong>' . esc_html( (string) $item['title'] ) . '</strong>';

            if ( ! empty( $item['meta'] ) ) {
                echo '<span class="bb-action-meta">' . esc_html( (string) $item['meta'] ) . '</span>';
            }

            if ( ! empty( $item['badge'] ) ) {
                echo '<span class="bb-badge">' . esc_html( (string) $item['badge'] ) . '</span>';
            }

            echo '</div>';
            echo '<div class="bb-action-buttons">';

            $item_actions = (array) $item['actions'];

            foreach ( $item_actions as $action ) {
                echo $this->action_button_html( $action ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }

            echo '</div>';
            echo '</li>';
        }

        echo '</ul>';
        echo '</section>';
    }

    protected function action_button_html( array $action ): string {

        $label = isset( $action['label'] ) ? (string) $action['label'] : '';
        $style = isset( $action['style'] ) ? (string) $action['style'] : 'secondary';
        $class = 'bb-btn bb-btn-' . ( 'primary' === $style ? 'primary' : 'ghost' );
        $sp    = chr( 32 );

        if ( isset( $action['url'] ) && ! isset( $action['action'] ) ) {
            return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( (string) $action['url'] ) . '">' . esc_html( $label ) . '</a>';
        }

        if ( isset( $action['action'], $action['nonce'], $action['id'] ) ) {

            $html  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' )) . '" class="bb-inline-form">';
            $html .= '<input type="hidden" name="action" value="' . esc_attr( (string) $action['action'] ) . '" />';
            $html .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( (string) $action['nonce'] ) . '" />';

            if ( isset( $action['status'] ) ) {
                $html .= '<input type="hidden" name="appointment_id" value="' . esc_attr( (string) $action['id'] ) . '" />';
                $html .= '<input type="hidden" name="target_status" value="' . esc_attr( (string) $action['status'] ) . '" />';
            } else {
                $html .= '<input type="hidden" name="consultation_id" value="' . esc_attr( (string) $action['id'] ) . '" />';
                $html .= '<input type="hidden" name="bb_op" value="' . esc_attr( (string) ( $action['op'] ?? '' )) . '" />';
            }

            $html .= '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button>';
            $html .= '</form>';

            return $html;
        }

        return '';
    }

    protected function render_quick_actions(): void {

        $actions = array(
            array( 'dashicons-businessperson', __( 'Add Lawyer', 'business-builder' ), admin_url( 'post-new.php?post_type=bb_lawyer' ) ),
            array( 'dashicons-category', __( 'Add Practice Area', 'business-builder' ), admin_url( 'edit-tags.php?taxonomy=bb_practice_area' ) ),
            array( 'dashicons-portfolio', __( 'Add Legal Service', 'business-builder' ), admin_url( 'post-new.php?post_type=bb_legal_service' ) ),
            array( 'dashicons-phone', __( 'Consultation Requests', 'business-builder' ), admin_url( 'edit.php?post_type=bb_consultation' ) ),
            array( 'dashicons-calendar-alt', __( 'Appointments', 'business-builder' ), admin_url( 'edit.php?post_type=bb_appointment' ) ),
            array( 'dashicons-money-alt', __( 'Configure Payment', 'business-builder' ), admin_url( 'admin.php?page=bb-law-firm-payments' ) ),
            array( 'dashicons-admin-customizer', __( 'Manage Website', 'business-builder' ), admin_url( 'admin.php?page=business-builder' ) ),
        );

        echo '<section class="bb-card bb-quick-actions">';
        echo '<h2 class="bb-card-title"><span class="dashicons dashicons-lightbulb"></span> ' . esc_html__( 'Quick Actions', 'business-builder' ) . '</h2>';
        echo '<div class="bb-quick-grid">';

        foreach ( $actions as $action ) {
            echo '<a class="bb-quick-item" href="' . esc_url( $action[2] ) . '">';
            echo '<span class="dashicons ' . esc_attr( $action[0] ) . '"></span>';
            echo '<span>' . esc_html( $action[1] ) . '</span>';
            echo '</a>';
        }

        echo '</div>';
        echo '</section>';
    }

    protected function render_recent_appointments( array $appts ): void {

        $list_url = admin_url( 'edit.php?post_type=bb_appointment' );

        echo '<section class="bb-card bb-activity">';
        echo '<h2 class="bb-card-title"><span class="dashicons dashicons-calendar-alt"></span> ' . esc_html__( 'Recent Appointments', 'business-builder' ) . '</h2>';

        if ( empty( $appts ) ) {
            echo '<div class="bb-empty">';
            echo '<span class="dashicons dashicons-calendar"></span>';
            echo '<p>' . esc_html__( 'No appointment data is available yet.', 'business-builder' ) . '</p>';
            echo '</div>';
            echo '<div class="bb-activity-footer">';
            echo '<a class="bb-btn bb-btn-ghost" href="' . esc_url( $list_url ) . '">' . esc_html__( 'View All Appointments', 'business-builder' ) . '</a>';
            echo '</div>';
            echo '</section>';
            return;
        }

        echo '<ul class="bb-activity-list">';

        foreach ( $appts as $row ) {

            $name = '' !== (string) $row['name'] ? (string) $row['name'] : __( '(no name)', 'business-builder' );

            $when = trim( (string) $row['date'] . ' ' . (string) $row['start'] );

            echo '<li class="bb-activity-item">';

            if ( '' !== (string) $row['url'] ) {
                echo '<a href="' . esc_url( (string) $row['url'] ) . '">';
            }

            echo '<strong>' . esc_html( $name ) . '</strong>';

            if ( '' !== $when ) {
                echo '<span class="bb-activity-meta">' . esc_html( $when ) . '</span>';
            }

            echo '<span class="bb-badge bb-badge-status">' . esc_html( (string) $row['status'] ) . '</span>';

            if ( '' !== (string) $row['reference'] ) {
                echo '<span class="bb-activity-ref"><code>' . esc_html( (string) $row['reference'] ) . '</code></span>';
            }

            if ( '' !== (string) $row['url'] ) {
                echo '</a>';
            }

            echo '</li>';
        }

        echo '</ul>';

        echo '<div class="bb-activity-footer">';
        echo '<a class="bb-btn bb-btn-ghost" href="' . esc_url( $list_url ) . '">' . esc_html__( 'View All Appointments', 'business-builder' ) . '</a>';
        echo '</div>';
        echo '</section>';
    }

    protected function render_activity( array $recent, int $unread ): void {

        echo '<section class="bb-card bb-activity">';
        echo '<h2 class="bb-card-title"><span class="dashicons dashicons-list-view"></span> ' . esc_html__( 'Recent Consultation Requests', 'business-builder' ) . '</h2>';

        if ( empty( $recent ) ) {
            echo '<div class="bb-empty">';
            echo '<span class="dashicons dashicons-phone"></span>';
            echo '<p>' . esc_html__( 'No consultation data is available yet.', 'business-builder' ) . '</p>';
            echo '</div>';
            echo '</section>';
            return;
        }

        echo '<ul class="bb-activity-list">';

        foreach ( $recent as $row ) {

            echo '<li class="bb-activity-item">';

            if ( '' !== (string) $row['url'] ) {
                echo '<a href="' . esc_url( (string) $row['url'] ) . '">';
            }

            $name = '' !== (string) $row['name'] ? (string) $row['name'] : __( '(no name)', 'business-builder' );
            echo '<strong>' . esc_html( $name ) . '</strong>';

            if ( '' !== (string) $row['practice_area'] ) {
                echo '<span class="bb-activity-meta">' . esc_html( (string) $row['practice_area'] ) . '</span>';
            }

            echo '<span class="bb-badge bb-badge-status">' . esc_html( (string) $row['status'] ) . '</span>';
            echo '<span class="bb-badge bb-badge-payment">' . esc_html( (string) $row['payment'] ) . '</span>';

            if ( '' !== (string) $row['reference'] ) {
                echo '<span class="bb-activity-ref"><code>' . esc_html( (string) $row['reference'] ) . '</code></span>';
            }

            echo '<span class="bb-activity-time">' . esc_html( mysql2date( 'Y-m-d H:i', (string) $row['created'] )) . '</span>';

            if ( '' !== (string) $row['url'] ) {
                echo '</a>';
            }

            echo '</li>';
        }

        echo '</ul>';

        echo '<div class="bb-activity-footer">';
        echo '<span class="bb-notif-count"><span class="dashicons dashicons-bell"></span> ';
        echo esc_html( sprintf( _n( '%d unread notification', '%d unread notifications', $unread, 'business-builder' ), $unread ) );
        echo '</span>';

        if ( $unread > 0 ) {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' )) . '" class="bb-inline-form">';
            echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_READ_ALL ) . '" />';
            wp_nonce_field( self::NONCE_READ_ALL );
            echo '<button type="submit" class="bb-btn bb-btn-ghost">' . esc_html__( 'Mark All as Read', 'business-builder' ) . '</button>';
            echo '</form>';
        }

        echo '</div>';
        echo '</section>';
    }

    public function handle_mark_read(): void {

        if ( ! current_user_can( DashboardMenu::capability() ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'business-builder' ) );
        }

        check_admin_referer( self::NONCE_READ_ALL );

        $this->notifications->mark_all_read();

        wp_safe_redirect(
            add_query_arg( array( 'page' => self::PAGE_SLUG ), admin_url( 'admin.php' ) )
        );

        exit;
    }
}

