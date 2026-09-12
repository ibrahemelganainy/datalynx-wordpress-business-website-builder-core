<?php

namespace BusinessBuilderCore\Packs\LawFirm\Admin;

use BusinessBuilderCore\Core\Notifications\NotificationManager;
use BusinessBuilderCore\Core\Audit\AuditLog;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Notifications & Activity screen.
 *
 * A read-only operational feed under the Law Firm Dashboard menu that
 * reuses the existing NotificationManager (dashboard feed) and
 * AuditLog — no duplicate notification system is created (spec:
 * Part 10 / code quality).
 *
 * Multisite: both sources are per-site options, so each site shows only
 * its own activity.
 */
class NotificationsAdmin {

    /**
     * Submenu slug.
     */
    private const PAGE_SLUG = 'bb-law-firm-notifications';

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
         * screen renderer so there is exactly one menu definition.
         */
        // Screen attachment is invoked directly by LawFirmPack, which
        // owns menu timing (see LawFirmPack::register()).
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
     * Render the notifications & activity page.
     */
    public function render_page(): void {

        if ( ! current_user_can( DashboardMenu::capability() ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'business-builder' ) );
        }

        $feed  = $this->notifications->feed( 50 );
        $audit = $this->audit->recent( 50 );
        $unread = $this->notifications->unread_count();

        $rtl_class = is_rtl() ? ' bb-rtl' : ' bb-ltr';

        ?>
        <div class="wrap bb-dashboard<?php echo esc_attr( $rtl_class ); ?>">

            <header class="bb-dashboard-header">
                <div class="bb-dashboard-title">
                    <h1><?php esc_html_e( 'Notifications & Activity', 'business-builder' ); ?></h1>
                    <p><?php esc_html_e( 'Recent notifications and administrative activity for this site.', 'business-builder' ); ?></p>
                </div>
                <div class="bb-dashboard-header-actions">
                    <span class="bb-badge"><?php
                        echo esc_html(
                            sprintf(
                                /* translators: %d: unread count */
                                _n( '%d unread', '%d unread', $unread, 'business-builder' ),
                                $unread
                            )
                        );
                    ?></span>
                    <?php if ( $unread > 0 ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bb-inline-form">
                            <input type="hidden" name="action" value="bb_mark_notifications_read" />
                            <?php wp_nonce_field( 'bb_mark_notifications_read' ); ?>
                            <button type="submit" class="bb-btn bb-btn-primary"><?php esc_html_e( 'Mark All as Read', 'business-builder' ); ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </header>

            <div class="bb-dashboard-columns">

                <div class="bb-dashboard-main">
                    <section class="bb-card">
                        <h2 class="bb-card-title">
                            <span class="dashicons dashicons-bell"></span>
                            <?php esc_html_e( 'Notifications', 'business-builder' ); ?>
                        </h2>

                        <?php if ( empty( $feed )) : ?>
                            <div class="bb-empty">
                                <span class="dashicons dashicons-bell"></span>
                                <p><?php esc_html_e( 'No notifications yet.', 'business-builder' ); ?></p>
                            </div>
                        <?php else : ?>
                            <ul class="bb-activity-list">
                                <?php foreach ( $feed as $item ) : ?>
                                    <li class="bb-activity-item">
                                        <strong><?php echo esc_html( (string) ( $item['subject'] ?? '' ) ); ?></strong>
                                        <?php if ( empty( $item['read'] )) : ?>
                                            <span class="bb-badge"><?php esc_html_e( 'New', 'business-builder' ); ?></span>
                                        <?php endif; ?>
                                        <span class="bb-activity-time">
                                            <?php
                                            $ts = isset( $item['time'] ) ? (int) $item['time'] : 0;
                                            echo esc_html( $ts ? date_i18n( 'Y-m-d H:i', $ts ) : '' );
                                            ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>
                </div>

                <aside class="bb-dashboard-side">
                    <section class="bb-card">
                        <h2 class="bb-card-title">
                            <span class="dashicons dashicons-clipboard"></span>
                            <?php esc_html_e( 'Activity Log', 'business-builder' ); ?>
                        </h2>

                        <?php if ( empty( $audit )) : ?>
                            <div class="bb-empty">
                                <span class="dashicons dashicons-clipboard"></span>
                                <p><?php esc_html_e( 'No recorded activity yet.', 'business-builder' ); ?></p>
                            </div>
                        <?php else : ?>
                            <ul class="bb-activity-list">
                                <?php foreach ( $audit as $entry ) : ?>
                                    <li class="bb-activity-item">
                                        <strong><?php echo esc_html( (string) ( $entry['action'] ?? '' ) ); ?></strong>
                                        <?php if ( ! empty( $entry['user'] )) : ?>
                                            <span class="bb-activity-meta"><?php echo esc_html( (string) $entry['user'] ); ?></span>
                                        <?php endif; ?>
                                        <span class="bb-activity-time">
                                            <?php
                                            $ts = isset( $entry['time'] ) ? (int) $entry['time'] : 0;
                                            echo esc_html( $ts ? date_i18n( 'Y-m-d H:i', $ts ) : '' );
                                            ?>
                                        </span>
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
}
