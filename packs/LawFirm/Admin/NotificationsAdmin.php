<?php

namespace BusinessBuilderCore\Packs\LawFirm\Admin;

use BusinessBuilderCore\Core\Notifications\NotificationManager;
use BusinessBuilderCore\Core\Audit\AuditLog;

if ( ! defined( 'ABSPATH' )) {
    exit;
}

/**
 * Notifications & Activity centre.
 *
 * A practical, operational feed under the Law Firm menu. It EXTENDS the
 * existing NotificationManager (dashboard feed) and AuditLog — no
 * duplicate notification system is created.
 *
 * Features:
 *   - a live unread badge, refreshed by lightweight AJAX polling (only the
 *     count + NEW items are returned; the list is never fully reloaded),
 *   - category filters (All / Consultations / Appointments / Payments /
 *     System / Unread) and a time window,
 *   - search across reference / customer / amount,
 *   - an activity timeline grouped by day (Today / Yesterday / date),
 *   - per-item actions valid for its type (View record / View receipt /
 *     Mark read), and Mark All as Read,
 *   - persistent notifications (stored per site, Multisite-isolated).
 *
 * Security: every action verifies the LawFirm capability and a nonce; the
 * AJAX endpoint verifies the capability + a nonce; all output is escaped.
 */
class NotificationsAdmin {

    /**
     * Submenu slug.
     */
    private const PAGE_SLUG = 'bb-law-firm-notifications';

    /**
     * admin-post action: mark one notification read.
     */
    private const ACTION_MARK_READ = 'bb_notification_mark_read';

    /**
     * admin-post action: mark all notifications read.
     */
    private const ACTION_MARK_ALL_READ = 'bb_notification_mark_all_read';

    /**
     * AJAX action: poll for unread count + newly arrived notifications.
     */
    private const ACTION_POLL = 'bb_notifications_poll';

    /**
     * Notifications.
     */
    protected NotificationManager $notifications;

    /**
     * Audit log.
     */
    protected AuditLog $audit;

    /**
     * Constructor.
     *
     * @param NotificationManager $notifications Notifications.
     * @param AuditLog            $audit         Audit log.
     */
    public function __construct(
        NotificationManager $notifications,
        AuditLog $audit
    ) {
        $this->notifications = $notifications;
        $this->audit         = $audit;
    }

    /**
     * Register hooks.
     */
    public function register(): void {

        /*
         * The menu itself is owned by DashboardMenu; we only attach our
         * screen renderer and our own action/AJAX handlers so there is
         * exactly one menu definition and no duplicate notification system.
         */
        add_action( 'admin_post_' . self::ACTION_MARK_READ, array( $this, 'handle_mark_read' ) );
        add_action( 'admin_post_' . self::ACTION_MARK_ALL_READ, array( $this, 'handle_mark_all_read' ) );
        add_action( 'wp_ajax_' . self::ACTION_POLL, array( $this, 'handle_poll' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Attach this screen to the Law Firm menu.
     *
     * @param DashboardMenu $menu Menu owner.
     */
    public function attach_screen( DashboardMenu $menu ): void {

        $menu->add_screen( self::PAGE_SLUG, array( $this, 'render_page' ) );
    }

    /**
     * Enqueue the notification-centre assets on this screen only.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( string $hook ): void {

        $raw_page = isset( $_GET['page'] ) ? (string) wp_unslash( $_GET['page'] ) : '';
        $page     = sanitize_key( $raw_page );

        if ( self::PAGE_SLUG !== $page ) {
            return;
        }

        wp_enqueue_style(
            'bb-notifications',
            BB_CORE_URL . 'assets/css/admin/notifications.css',
            array(),
            BB_CORE_VERSION
        );

        wp_enqueue_script(
            'bb-notifications',
            BB_CORE_URL . 'assets/js/admin/notifications.js',
            array(),
            BB_CORE_VERSION,
            true
        );

        wp_localize_script(
            'bb-notifications',
            'BBNotifications',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'action'  => self::ACTION_POLL,
                'nonce'   => wp_create_nonce( self::ACTION_POLL ),
                'unread'  => $this->notifications->unread_count(),
            )
        );
    }

    /**
     * AJAX: return the current unread count plus any notifications that
     * arrived after the client's last-seen timestamp. Lightweight polling;
     * the dashboard list is never fully reloaded.
     */
    public function handle_poll(): void {

        if ( ! current_user_can( DashboardMenu::capability() )) {
            wp_send_json_error( array( 'reason' => 'forbidden' ), 403 );
        }

        check_ajax_referer( self::ACTION_POLL, 'nonce' );

        $raw_since = isset( $_GET['since'] ) ? wp_unslash( $_GET['since'] ) : 0;
        $since     = absint( $raw_since );

        $manager = new NotificationManager();
        $items   = $manager->query( 'all', '', 0, 50 );

        $new = array();

        foreach ( $items as $item ) {

            $item_time = (int) ( $item['time'] ?? 0 );

            if ( $item_time > $since ) {
                $new[] = $this->serialize_item( $item );
            }
        }

        wp_send_json_success(
            array(
                'unread' => $manager->unread_count(),
                'items'  => $new,
                'server' => time(),
            )
        );
    }

    /**
     * Mark ONE notification read (capability + nonce verified).
     */
    public function handle_mark_read(): void {

        if ( ! current_user_can( DashboardMenu::capability() )) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'business-builder' ) );
        }

        check_admin_referer( self::ACTION_MARK_READ );

        $id = isset( $_POST['notification_id'] )
            ? sanitize_text_field( wp_unslash( $_POST['notification_id'] ) )
            : '';

        if ( '' !== $id ) {
            $this->notifications->mark_read( $id );
        }

        $this->redirect_back();
    }

    /**
     * Mark EVERY notification read (capability + nonce verified).
     */
    public function handle_mark_all_read(): void {

        if ( ! current_user_can( DashboardMenu::capability() )) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'business-builder' ) );
        }

        check_admin_referer( self::ACTION_MARK_ALL_READ );

        $this->notifications->mark_all_read();

        $this->redirect_back();
    }

    /**
     * Redirect back to the notifications screen.
     */
    protected function redirect_back(): void {

        wp_safe_redirect(
            add_query_arg( array( 'page' => self::PAGE_SLUG ), admin_url( 'admin.php' ) )
        );

        exit;
    }

    /**
     * Human, translatable label for a raw audit action slug.
     *
     * Keeps the stored slugs stable (they are the data contract) while the
     * activity timeline shows a readable, localized title.
     *
     * @param string $action Audit action slug.
     * @return string
     */
    public static function activity_label( string $action ): string {

        $action = sanitize_key( $action );

        $labels = array(
            'consultation.created'      => __( 'Consultation created', 'business-builder' ),
            'consultation.status_changed' => __( 'Consultation status changed', 'business-builder' ),
            'appointment.created'       => __( 'Appointment created', 'business-builder' ),
            'payment.status_synced'     => __( 'Payment status synchronized', 'business-builder' ),
            'payment.manual_submitted'  => __( 'Manual payment submitted', 'business-builder' ),
            'payment.manual_approved'   => __( 'Manual payment approved', 'business-builder' ),
            'payment.manual_rejected'   => __( 'Manual payment rejected', 'business-builder' ),
            'payment.manually_verified' => __( 'Payment verified manually', 'business-builder' ),
            'payment.receipt_viewed'    => __( 'Receipt viewed', 'business-builder' ),
            'payment.callback_unknown'  => __( 'Unknown payment callback', 'business-builder' ),
            'payment.checkout_failed'   => __( 'Payment checkout failed', 'business-builder' ),
        );

        if ( isset( $labels[ $action ] )) {
            return $labels[ $action ];
        }

        /* Fallback: humanize "foo.bar_baz" => "Foo bar baz". */
        $human = str_replace( array( '.', '_' ), ' ', $action );
        $human = trim( $human );

        return '' !== $human ? ucfirst( $human ) : __( 'Activity', 'business-builder' );
    }

    /**
     * Validate a Y-m-d date string ('' when invalid).
     *
     * @param string $value Candidate date.
     * @return string
     */
    protected static function parse_ymd( string $value ): string {

        $value = trim( $value );

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
            return '';
        }

        $parts = explode( '-', $value );

        if ( ! checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ) {
            return '';
        }

        return $value;
    }

    /**
     * Unix timestamp for the START of a local Y-m-d day.
     *
     * Uses the site timezone so the custom range matches what an
     * administrator sees, not UTC.
     *
     * @param string $date Y-m-d.
     * @return int
     */
    protected static function day_start( string $date ): int {

        $ts = strtotime( $date . ' 00:00:00' );

        return false !== $ts ? $ts : 0;
    }

    /**
     * Unix timestamp for the END of a local Y-m-d day (inclusive).
     *
     * @param string $date Y-m-d.
     * @return int
     */
    protected static function day_end( string $date ): int {

        $ts = strtotime( $date . ' 23:59:59' );

        return false !== $ts ? $ts : 0;
    }

    /**
     * Direct edit URL for a post of a given type, or '' when it is gone.
     *
     * Points at the REAL edit screen for the exact record (never a bare
     * list), and returns '' when the post no longer exists so callers never
     * render a dead link.
     *
     * Public wrapper used by the dashboard widget.
     *
     * @param string $post_type Post type.
     * @param int    $post_id   Post id.
     * @return string
     */
    public static function edit_url_for( string $post_type, int $post_id ): string {

        return self::entity_edit_url( $post_type, $post_id );
    }

    /**
     * Direct edit URL for a post of a given type, or '' when it is gone.
     *
     * @param string $post_type Post type.
     * @param int    $post_id   Post id.
     * @return string
     */
    protected static function entity_edit_url( string $post_type, int $post_id ): string {

        if ( $post_id <= 0 || ! get_post( $post_id ) instanceof \WP_Post ) {
            return '';
        }

        $url = get_edit_post_link( $post_id, 'raw' );

        return is_string( $url ) ? $url : '';
    }

    /**
     * Safe JSON view of one stored notification row.
     *
     * @param array<string, mixed> $item Row.
     * @return array<string, mixed>
     */
    protected function serialize_item( array $item ): array {

        $time      = (int) ( $item['time'] ?? 0 );
        $type      = isset( $item['entity_type'] ) ? (string) $item['entity_type'] : '';
        $entity_id = isset( $item['entity_id'] ) ? (int) $item['entity_id'] : 0;
        $category  = isset( $item['category'] ) && '' !== (string) $item['category']
            ? (string) $item['category']
            : NotificationManager::category_for( (string) ( $item['event'] ?? '' ) );

        /*
         * The polled action URL points at the REAL record when it still
         * exists; a deleted entity yields no URL so the JS never renders a
         * dead link.
         */
        $url = '';

        if ( $entity_id > 0 ) {
            if ( 'appointment' === $type ) {
                $url = self::entity_edit_url( 'bb_appointment', $entity_id );
            } elseif ( 'consultation' === $type ) {
                $url = self::entity_edit_url( 'bb_consultation', $entity_id );
            }
        }

        $timeago = '';

        if ( $time > 0 ) {
            $timeago = sprintf(
                /* translators: %s: human time diff */
                __( '%s ago', 'business-builder' ),
                human_time_diff( $time, time() )
            );
        }

        return array(
            'id'        => sanitize_text_field( (string) ( $item['id'] ?? '' ) ),
            'event'     => sanitize_key( (string) ( $item['event'] ?? '' ) ),
            'category'  => $category,
            'subject'   => sanitize_text_field( (string) ( $item['subject'] ?? '' ) ),
            'message'   => sanitize_text_field( (string) ( $item['message'] ?? '' ) ),
            'reference' => sanitize_text_field( (string) ( $item['reference'] ?? '' ) ),
            'amount'    => sanitize_text_field( (string) ( $item['amount'] ?? '' ) ),
            'currency'  => sanitize_text_field( (string) ( $item['currency'] ?? '' ) ),
            'gateway'   => sanitize_key( (string) ( $item['gateway'] ?? '' ) ),
            'customer'  => sanitize_text_field( (string) ( $item['customer'] ?? '' ) ),
            'time'      => $time,
            'timeago'   => $timeago,
            'url'       => $url,
        );
    }

    /**
     * Render the notifications & activity page.
     */
    public function render_page(): void {

        if ( ! current_user_can( DashboardMenu::capability() )) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'business-builder' ) );
        }

        /* Filters are read-only view state (sanitized). */
        $raw_cat  = isset( $_GET['bb_nf_cat'] ) ? wp_unslash( $_GET['bb_nf_cat'] ) : 'all';
        $raw_q    = isset( $_GET['bb_nf_q'] ) ? wp_unslash( $_GET['bb_nf_q'] ) : '';
        $raw_days = isset( $_GET['bb_nf_days'] ) ? wp_unslash( $_GET['bb_nf_days'] ) : 0;
        $raw_from = isset( $_GET['bb_nf_from'] ) ? wp_unslash( $_GET['bb_nf_from'] ) : '';
        $raw_to   = isset( $_GET['bb_nf_to'] ) ? wp_unslash( $_GET['bb_nf_to'] ) : '';

        $category = sanitize_key( (string) $raw_cat );
        $search   = sanitize_text_field( (string) $raw_q );
        $days     = absint( $raw_days );
        $raw_page = isset( $_GET['bb_nf_page'] ) ? absint( wp_unslash( $_GET['bb_nf_page'] )) : 1;
        $paged    = max( 1, $raw_page );

        if ( '' === $category ) {
            $category = 'all';
        }

        /*
         * Optional CUSTOM date range (bb_nf_from / bb_nf_to, Y-m-d). Invalid
         * or partial values are ignored so a malformed range never hides the
         * whole feed. The range is expressed as timestamps for the manager.
         */
        $range     = array();
        $from_disp = '';
        $to_disp   = '';

        $from_date = self::parse_ymd( (string) $raw_from );
        $to_date   = self::parse_ymd( (string) $raw_to );

        if ( '' !== $from_date ) {
            $from_disp = $from_date;
            $range['from'] = self::day_start( $from_date );
        }

        if ( '' !== $to_date ) {
            $to_disp = $to_date;
            $range['to'] = self::day_end( $to_date );
        }

        /*
         * Pagination: the page never loads the entire history at once. The
         * per-page size is fixed (light-weight) and the offset is derived
         * from the requested page.
         */
        $per_page = 20;
        $offset   = ( $paged - 1 ) * $per_page;

        $feed   = $this->notifications->query( $category, $search, $days, $per_page, $offset, $range );
        $unread = $this->notifications->unread_count();
        $total  = $this->notifications->count( $category, $search, $days, $range );
        $pages  = (int) ceil( $total / $per_page );

        $rtl_class = is_rtl() ? ' bb-rtl' : ' bb-ltr';

        $categories = array(
            'all'            => __( 'All', 'business-builder' ),
            'unread'         => __( 'Unread', 'business-builder' ),
            'consultation'   => __( 'Consultations', 'business-builder' ),
            'appointment'    => __( 'Appointments', 'business-builder' ),
            'payment'        => __( 'Payments', 'business-builder' ),
            'manual_payment' => __( 'Manual Payments', 'business-builder' ),
            'system'         => __( 'System', 'business-builder' ),
        );

        $windows = array(
            0  => __( 'All time', 'business-builder' ),
            1  => __( 'Today', 'business-builder' ),
            7  => __( 'Last 7 days', 'business-builder' ),
            30 => __( 'Last 30 days', 'business-builder' ),
        );

        $admin_root = admin_url( 'admin.php' );

        ?>
        <div class="wrap bb-dashboard bb-notification-center<?php echo esc_attr( $rtl_class ); ?>">

            <header class="bb-dashboard-header bb-nc-header">
                <div class="bb-dashboard-title">
                    <h1><?php esc_html_e( 'Notifications & Activity', 'business-builder' ); ?></h1>
                    <p><?php esc_html_e( 'Live operational feed: requests, bookings, payments and system activity for this site.', 'business-builder' ); ?></p>
                </div>
                <div class="bb-dashboard-header-actions">
                    <span class="bb-nc-bell" data-bb-nc-bell aria-live="polite">
                        <span class="dashicons dashicons-bell" aria-hidden="true"></span>
                        <span class="bb-nc-bell-count" data-bb-nc-count><?php echo esc_html( (string) $unread ); ?></span>
                    </span>
                    <?php if ( $unread > 0 ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bb-inline-form">
                            <input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_MARK_ALL_READ ); ?>" />
                            <?php wp_nonce_field( self::ACTION_MARK_ALL_READ ); ?>
                            <button type="submit" class="bb-btn bb-btn-primary"><?php esc_html_e( 'Mark All as Read', 'business-builder' ); ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </header>

            <div class="bb-nc-toolbar">
                <nav class="bb-nc-filters" aria-label="<?php echo esc_attr__( 'Filter notifications', 'business-builder' ); ?>">
                    <?php foreach ( $categories as $cat_key => $cat_label ) : ?>
                        <?php
                        $cat_url = remove_query_arg(
                            'bb_nf_page',
                            add_query_arg(
                                array(
                                    'page'       => self::PAGE_SLUG,
                                    'bb_nf_cat'  => $cat_key,
                                    'bb_nf_q'    => $search,
                                    'bb_nf_days' => $days,
                                ),
                                $admin_root
                            )
                        );

                        $cat_is = ( $category === $cat_key );
                        ?>
                        <a class="bb-nc-filter<?php echo $cat_is ? ' is-active' : ''; ?>" href="<?php echo esc_url( $cat_url ); ?>">
                            <?php echo esc_html( $cat_label ); ?>
                            <?php if ( 'unread' === $cat_key && $unread > 0 ) : ?>
                                <span class="bb-nc-filter-count"><?php echo esc_html( (string) $unread ); ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <form class="bb-nc-search" method="get" action="<?php echo esc_url( $admin_root ); ?>">
                    <input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
                    <input type="hidden" name="bb_nf_cat" value="<?php echo esc_attr( $category ); ?>" />
                    <input
                        type="search"
                        name="bb_nf_q"
                        value="<?php echo esc_attr( $search ); ?>"
                        placeholder="<?php esc_attr_e( 'Search reference, customer…', 'business-builder' ); ?>"
                        aria-label="<?php echo esc_attr__( 'Search notifications', 'business-builder' ); ?>"
                    />
                    <select name="bb_nf_days" aria-label="<?php echo esc_attr__( 'Time window', 'business-builder' ); ?>">
                        <?php foreach ( $windows as $day_key => $day_label ) : ?>
                            <option value="<?php echo esc_attr( (string) $day_key ); ?>" <?php selected( $days, $day_key ); ?>>
                                <?php echo esc_html( $day_label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label class="bb-nc-range">
                        <span class="screen-reader-text"><?php esc_html_e( 'From date', 'business-builder' ); ?></span>
                        <input type="date" name="bb_nf_from" value="<?php echo esc_attr( $from_disp ); ?>" aria-label="<?php echo esc_attr__( 'From date', 'business-builder' ); ?>" />
                    </label>
                    <label class="bb-nc-range">
                        <span class="screen-reader-text"><?php esc_html_e( 'To date', 'business-builder' ); ?></span>
                        <input type="date" name="bb_nf_to" value="<?php echo esc_attr( $to_disp ); ?>" aria-label="<?php echo esc_attr__( 'To date', 'business-builder' ); ?>" />
                    </label>
                    <button type="submit" class="bb-btn bb-btn-ghost"><?php esc_html_e( 'Search', 'business-builder' ); ?></button>
                </form>
            </div>

            <div class="bb-nc-body">

                <div class="bb-nc-main" data-bb-nc-timeline data-bb-nc-since="<?php echo esc_attr( (string) time() ); ?>">

                    <?php if ( count( $feed ) === 0 ) : ?>
                        <div class="bb-empty">
                            <span class="dashicons dashicons-bell"></span>
                            <p><?php esc_html_e( 'No notifications match your filters.', 'business-builder' ); ?></p>
                        </div>
                    <?php else : ?>
                        <?php
                        /*
                         * Activity timeline grouped by day (Today / Yesterday /
                         * date), newest first. Each item links to its record and
                         * offers only the actions valid for its type.
                         */
                        $last_day  = '';
                        $today     = date_i18n( 'Y-m-d', current_time( 'timestamp' ) );
                        $yesterday = date_i18n( 'Y-m-d', current_time( 'timestamp' ) - DAY_IN_SECONDS );

                        foreach ( $feed as $item ) :

                            $item_time = (int) ( $item['time'] ?? 0 );
                            $item_day  = $item_time > 0 ? date_i18n( 'Y-m-d', $item_time ) : '';

                            if ( $item_day !== $last_day ) :

                                $last_day = $item_day;

                                if ( $item_day === $today ) {
                                    $day_label = __( 'Today', 'business-builder' );
                                } elseif ( $item_day === $yesterday ) {
                                    $day_label = __( 'Yesterday', 'business-builder' );
                                } else {
                                    $day_label = date_i18n( get_option( 'date_format' ), $item_time );
                                }
                                ?>
                                <h2 class="bb-nc-day"><?php echo esc_html( $day_label ); ?></h2>
                                <?php
                            endif;

                            $this->render_item( $item );

                        endforeach;
                        ?>
                    <?php endif; ?>

                    <?php if ( $pages > 1 ) : ?>
                        <nav class="bb-nc-pagination" aria-label="<?php echo esc_attr__( 'Notifications pagination', 'business-builder' ); ?>">
                            <?php
                            $page_base = add_query_arg(
                                array(
                                    'page'       => self::PAGE_SLUG,
                                    'bb_nf_cat'  => $category,
                                    'bb_nf_q'    => $search,
                                    'bb_nf_days' => $days,
                                    'bb_nf_from' => $from_disp,
                                    'bb_nf_to'   => $to_disp,
                                ),
                                $admin_root
                            );

                            $prev_url = add_query_arg( 'bb_nf_page', max( 1, $paged - 1 ), $page_base );
                            $next_url = add_query_arg( 'bb_nf_page', min( $pages, $paged + 1 ), $page_base );
                            ?>
                            <a class="bb-btn bb-btn-ghost<?php echo $paged <= 1 ? ' is-disabled' : ''; ?>" href="<?php echo esc_url( $prev_url ); ?>"><?php esc_html_e( 'Previous', 'business-builder' ); ?></a>
                            <span class="bb-nc-pageof">
                                <?php
                                printf(
                                    /* translators: 1: current page, 2: total pages */
                                    esc_html__( 'Page %1$d of %2$d', 'business-builder' ),
                                    (int) $paged,
                                    (int) $pages
                                );
                                ?>
                            </span>
                            <a class="bb-btn bb-btn-ghost<?php echo $paged >= $pages ? ' is-disabled' : ''; ?>" href="<?php echo esc_url( $next_url ); ?>"><?php esc_html_e( 'Next', 'business-builder' ); ?></a>
                        </nav>
                    <?php endif; ?>
                </div>

                <aside class="bb-nc-side">
                    <section class="bb-card">
                        <h2 class="bb-card-title">
                            <span class="dashicons dashicons-clipboard"></span>
                            <?php esc_html_e( 'Admin Activity Log', 'business-builder' ); ?>
                        </h2>

                        <?php $audit_recent = $this->audit->recent( 25 ); ?>

                        <?php if ( count( $audit_recent ) === 0 ) : ?>
                            <div class="bb-empty">
                                <span class="dashicons dashicons-clipboard"></span>
                                <p><?php esc_html_e( 'No recorded activity yet.', 'business-builder' ); ?></p>
                            </div>
                        <?php else : ?>
                            <ul class="bb-activity-list">
                                <?php foreach ( $audit_recent as $entry ) : ?>
                                    <?php
                                    $entry_action = isset( $entry['action'] ) ? (string) $entry['action'] : '';
                                    $entry_time   = isset( $entry['time'] ) ? (int) $entry['time'] : 0;
                                    $entry_ref    = isset( $entry['reference'] ) ? (string) $entry['reference'] : '';

                                    /* Prefer the display name; fall back to the login. */
                                    $entry_user = '';

                                    if ( isset( $entry['user_name'] ) && '' !== (string) $entry['user_name'] ) {
                                        $entry_user = (string) $entry['user_name'];
                                    } elseif ( isset( $entry['user'] ) && '' !== (string) $entry['user'] ) {
                                        $entry_user = (string) $entry['user'];
                                    }

                                    $entry_when = $entry_time > 0 ? date_i18n( 'Y-m-d H:i', $entry_time ) : '';
                                    ?>
                                    <li class="bb-activity-item">
                                        <strong><?php echo esc_html( self::activity_label( $entry_action ) ); ?></strong>
                                        <?php if ( '' !== $entry_ref ) : ?>
                                            <span class="bb-activity-ref"><code><?php echo esc_html( $entry_ref ); ?></code></span>
                                        <?php endif; ?>
                                        <?php if ( '' !== $entry_user ) : ?>
                                            <span class="bb-activity-meta"><?php echo esc_html( $entry_user ); ?></span>
                                        <?php endif; ?>
                                        <span class="bb-activity-time"><?php echo esc_html( $entry_when ); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>
                </aside>

            </div>

        </div>
        <?php
    }

    /**
     * Render one notification row.
     *
     * @param array<string, mixed> $item Stored notification row.
     */
    protected function render_item( array $item ): void {

        $event     = isset( $item['event'] ) ? sanitize_key( (string) $item['event'] ) : '';
        $subject   = isset( $item['subject'] ) ? (string) $item['subject'] : '';
        $message   = isset( $item['message'] ) ? (string) $item['message'] : '';
        $reference = isset( $item['reference'] ) ? (string) $item['reference'] : '';
        $payment_ref = isset( $item['payment_ref'] ) ? (string) $item['payment_ref'] : '';
        $amount    = isset( $item['amount'] ) ? (string) $item['amount'] : '';
        $currency  = isset( $item['currency'] ) ? (string) $item['currency'] : '';
        $gateway   = isset( $item['gateway'] ) ? (string) $item['gateway'] : '';
        $customer  = isset( $item['customer'] ) ? (string) $item['customer'] : '';
        $read      = ! empty( $item['read'] );
        $time      = (int) ( $item['time'] ?? 0 );
        $id        = (string) ( $item['id'] ?? '' );

        $category = isset( $item['category'] ) && '' !== (string) $item['category']
            ? (string) $item['category']
            : NotificationManager::category_for( $event );

        $type = isset( $item['entity_type'] ) ? (string) $item['entity_type'] : '';
        $entity_id = isset( $item['entity_id'] ) ? (int) $item['entity_id'] : 0;

        /* Has the related entity been deleted? Then no dead action is shown. */
        $entity_missing = false;

        if ( 'consultation' === $type || 'appointment' === $type ) {
            $post_type = ( 'appointment' === $type ) ? 'bb_appointment' : 'bb_consultation';
            $entity_missing = $entity_id <= 0 || ! get_post( $entity_id ) instanceof \WP_Post;
        }

        $icons = array(
            'payment'        => 'dashicons-money-alt',
            'manual_payment' => 'dashicons-clipboard',
            'appointment'    => 'dashicons-calendar-alt',
            'consultation'   => 'dashicons-phone',
            'system'         => 'dashicons-admin-generic',
        );

        $icon = isset( $icons[ $category ] ) ? $icons[ $category ] : 'dashicons-bell';

        /*
         * Actions must resolve to REAL records. The entity filter links show
         * the exact record when it still exists; when it has been deleted we
         * show an honest message instead of a dead link, and the action is
         * dropped entirely rather than pointing at a non-existent page.
         */
        $record_url   = '';
        $record_label = __( 'View', 'business-builder' );

        if ( ! $entity_missing && $entity_id > 0 ) {

            if ( 'appointment' === $type ) {
                $record_url   = self::entity_edit_url( 'bb_appointment', $entity_id );
                $record_label = __( 'View Appointment', 'business-builder' );
            } elseif ( 'consultation' === $type ) {
                $record_url   = self::entity_edit_url( 'bb_consultation', $entity_id );
                $record_label = __( 'View Consultation', 'business-builder' );
            }
        }

        /*
         * The receipt link uses the PAYMENT reference (TXN-…). For events
         * that carry only an object reference (a free invoice), that
         * reference is used instead — both are resolved by ReceiptPage.
         */
        $receipt_ref = '' !== $payment_ref ? $payment_ref : $reference;
        $receipt_url = '';

        $is_payment_event = ( 'payment' === $category || 'manual_payment' === $category );

        if ( $is_payment_event && '' !== $receipt_ref ) {
            $receipt_url = \BusinessBuilderCore\Packs\LawFirm\Frontend\ReceiptRoute::url( $receipt_ref );
        }

        /*
         * A manual payment awaiting verification links straight to the
         * review queue so an administrator can approve/reject in one step.
         */
        $review_url = '';

        if ( 'payment.manual_submitted' === $event ) {
            $review_url = admin_url( 'admin.php?page=bb-law-firm-manual-payments' );
        }

        /* "View Payment" for a payment event without a resolvable record. */
        if ( '' === $record_url && '' === $review_url && 'payment' === $category && ! $entity_missing && $entity_id > 0 ) {
            $record_url   = self::entity_edit_url( $type, $entity_id );
            $record_label = __( 'View Payment', 'business-builder' );
        }

        $read_class = $read ? 'is-read' : 'is-unread';
        $row_prefix = 'bb-nc-item bb-nc-' . sanitize_html_class( $category );
        $row_class  = $row_prefix . chr( 32 ) . $read_class;

        $timeago = '';

        if ( $time > 0 ) {
            $timeago = sprintf(
                /* translators: %s: human time diff */
                __( '%s ago', 'business-builder' ),
                human_time_diff( $time, time() )
            );
        }

        $amount_display = $amount;

        if ( '' !== $amount && class_exists( '\\BusinessBuilderCore\\Core\\Payments\\Currencies' )) {
            $amount_display = \BusinessBuilderCore\Core\Payments\Currencies::format( (float) $amount, $currency );
        }

        ?>
        <article class="<?php echo esc_attr( $row_class ); ?>" data-bb-nc-item="<?php echo esc_attr( $id ); ?>" data-bb-nc-time="<?php echo esc_attr( (string) $time ); ?>">

            <span class="bb-nc-item-icon dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>

            <div class="bb-nc-item-body">
                <div class="bb-nc-item-head">
                    <strong class="bb-nc-item-title"><?php echo esc_html( $subject ); ?></strong>
                    <?php if ( ! $read ) : ?>
                        <span class="bb-nc-dot" aria-label="<?php echo esc_attr__( 'Unread', 'business-builder' ); ?>"></span>
                    <?php endif; ?>
                    <time class="bb-nc-item-time"><?php echo esc_html( $timeago ); ?></time>
                </div>

                <?php if ( '' !== $message ) : ?>
                    <p class="bb-nc-item-msg"><?php echo esc_html( $message ); ?></p>
                <?php endif; ?>

                <div class="bb-nc-item-meta">
                    <?php if ( '' !== $reference ) : ?>
                        <code class="bb-nc-ref"><?php echo esc_html( $reference ); ?></code>
                    <?php endif; ?>
                    <?php if ( '' !== $amount ) : ?>
                        <span class="bb-nc-amount"><?php echo esc_html( $amount_display ); ?></span>
                    <?php endif; ?>
                    <?php if ( '' !== $gateway ) : ?>
                        <span class="bb-nc-gateway"><?php echo esc_html( ucfirst( $gateway ) ); ?></span>
                    <?php endif; ?>
                    <?php if ( '' !== $customer ) : ?>
                        <span class="bb-nc-customer"><?php echo esc_html( $customer ); ?></span>
                    <?php endif; ?>
                    <?php if ( $entity_missing ) : ?>
                        <span class="bb-nc-missing">
                            <?php
                            /* translators: %s: related entity type (consultation/appointment) */
                            echo esc_html( sprintf( __( 'Related %s is no longer available.', 'business-builder' ), $type ) );
                            ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="bb-nc-item-actions">
                    <?php if ( '' !== $review_url ) : ?>
                        <a class="bb-btn bb-btn-primary" href="<?php echo esc_url( $review_url ); ?>">
                            <?php esc_html_e( 'Review Manual Payment', 'business-builder' ); ?>
                        </a>
                    <?php elseif ( '' !== $record_url ) : ?>
                        <a class="bb-btn bb-btn-primary" href="<?php echo esc_url( $record_url ); ?>">
                            <?php echo esc_html( $record_label ); ?>
                        </a>
                    <?php endif; ?>

                    <?php if ( '' !== $receipt_url ) : ?>
                        <a class="bb-btn bb-btn-ghost" href="<?php echo esc_url( $receipt_url ); ?>">
                            <?php echo esc_html( '' !== $payment_ref ? __( 'View Receipt', 'business-builder' ) : __( 'View Invoice', 'business-builder' ) ); ?>
                        </a>
                    <?php endif; ?>

                    <?php if ( ! $read ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bb-inline-form">
                            <input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_MARK_READ ); ?>" />
                            <input type="hidden" name="notification_id" value="<?php echo esc_attr( $id ); ?>" />
                            <?php wp_nonce_field( self::ACTION_MARK_READ ); ?>
                            <button type="submit" class="bb-btn bb-btn-ghost"><?php esc_html_e( 'Mark Read', 'business-builder' ); ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php
    }
}
