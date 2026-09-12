<?php

namespace BusinessBuilderCore\Packs\LawFirm\Admin;

use BusinessBuilderCore\Packs\LawFirm\PostTypes\ConsultationMeta;
use BusinessBuilderCore\Core\Notifications\NotificationManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * LawFirm operational dashboard.
 *
 * Adds a top-level overview page under the Business Builder menu with:
 *   - consultation summary counts (by status + payment)
 *   - helper cards and navigation shortcuts
 *   - the notification center (unread count, mark-all-read)
 *
 * Read-only + lightweight (spec 27): counts use get_posts with
 * no_found_rows and fields=ids. Multisite-safe (all queries are
 * site-scoped).
 */
class DashboardAdmin {

    /**
     * Page slug.
     */
    private const PAGE_SLUG = 'business-builder-dashboard';

    /**
     * Mark-all-read action.
     */
    private const ACTION = 'bb_mark_notifications_read';

    /**
     * Nonce action.
     */
    private const NONCE_ACTION = 'bb_mark_notifications_read';

    /**
     * Notifications.
     */
    protected NotificationManager $notifications;

    /**
     * Constructor.
     *
     * @param NotificationManager $notifications Notifications.
     */
    public function __construct( NotificationManager $notifications ) {

        $this->notifications = $notifications;
    }

    /**
     * Register hooks.
     */
    public function register(): void {

        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_mark_read' ) );
    }

    /**
     * Register the dashboard submenu (placed first).
     */
    public function register_menu(): void {

        add_submenu_page(
            'business-builder',
            __( 'Dashboard', 'business-builder' ),
            __( 'Dashboard', 'business-builder' ),
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render_page' )
        );
    }

    /**
     * Render the dashboard.
     */
    public function render_page(): void {

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'business-builder' ) );
        }

        $consultations = $this->consultation_counts();
        $unread        = $this->notifications->unread_count();
        $feed          = $this->notifications->feed( 8 );

        ?>
        <div class="wrap">

            <h1><?php esc_html_e( 'Law Firm Dashboard', 'business-builder' ); ?></h1>

            <p>
                <?php esc_html_e( 'An overview of consultation requests and recent activity for this site.', 'business-builder' ); ?>
            </p>

            <h2><?php esc_html_e( 'Consultations', 'business-builder' ); ?></h2>

            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px;">

                <?php
                $cards = array(
                    'new'       => $consultations['new'],
                    'contacted' => $consultations['contacted'],
                    'scheduled' => $consultations['scheduled'],
                    'pending'   => $consultations['pending_payment'],
                    'total'     => $consultations['total'],
                );

                $labels = array(
                    'new'       => __( 'New', 'business-builder' ),
                    'contacted' => __( 'Contacted', 'business-builder' ),
                    'scheduled' => __( 'Scheduled', 'business-builder' ),
                    'pending'   => __( 'Pending Payment', 'business-builder' ),
                    'total'     => __( 'Total Requests', 'business-builder' ),
                );
                ?>

                <?php foreach ( $cards as $key => $count ) : ?>
                    <div style="min-width:150px;padding:16px 18px;border:1px solid #dcdcde;border-radius:8px;background:#fff;">
                        <div style="font-size:28px;font-weight:700;line-height:1;"><?php echo esc_html( (string) $count ); ?></div>
                        <div style="margin-top:6px;color:#646970;"><?php echo esc_html( $labels[ $key ] ); ?></div>
                    </div>
                <?php endforeach; ?>

            </div>

            <h2><?php esc_html_e( 'Quick Links', 'business-builder' ); ?></h2>

            <p>
                <a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=bb_consultation' ) ); ?>"><?php esc_html_e( 'Consultation Requests', 'business-builder' ); ?></a>
                <a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=bb_lawyer' ) ); ?>"><?php esc_html_e( 'Lawyers', 'business-builder' ); ?></a>
                <a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=bb_legal_service' ) ); ?>"><?php esc_html_e( 'Legal Services', 'business-builder' ); ?></a>
                <a class="button" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=bb_practice_area' ) ); ?>"><?php esc_html_e( 'Practice Areas', 'business-builder' ); ?></a>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=business-builder-payments' ) ); ?>"><?php esc_html_e( 'Payment Settings', 'business-builder' ); ?></a>
            </p>

            <hr>

            <h2>
                <?php esc_html_e( 'Notifications', 'business-builder' ); ?>
                <?php if ( $unread > 0 ) : ?>
                    <span class="awaiting-mod"><?php echo esc_html( (string) $unread ); ?></span>
                <?php endif; ?>
            </h2>

            <?php if ( ! empty( $feed ) ) : ?>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:12px;">
                    <input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
                    <?php wp_nonce_field( self::NONCE_ACTION ); ?>
                    <button type="submit" class="button"><?php esc_html_e( 'Mark All as Read', 'business-builder' ); ?></button>
                </form>

                <table class="widefat striped">
                    <tbody>
                    <?php foreach ( $feed as $item ) : ?>
                        <tr>
                            <td style="width:180px;">
                                <strong><?php echo esc_html( (string) ( $item['subject'] ?? '' ) ); ?></strong>
                            </td>
                            <td>
                                <?php echo esc_html( (string) ( $item['message'] ?? '' ) ); ?>
                                <?php if ( empty( $item['read'] ) ) : ?>
                                    <span class="awaiting-mod"><?php esc_html_e( 'New', 'business-builder' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="width:180px;">
                                <?php
                                $ts = isset( $item['time'] ) ? (int) $item['time'] : 0;
                                echo esc_html( $ts ? date_i18n( 'Y-m-d H:i', $ts ) : '' );
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

            <?php else : ?>

                <p><?php esc_html_e( 'No notifications yet.', 'business-builder' ); ?></p>

            <?php endif; ?>

        </div>
        <?php
    }

    /**
     * Count consultations by status + payment.
     *
     * @return array<string, int>
     */
    protected function consultation_counts(): array {

        $out = array(
            'total'           => 0,
            'new'             => 0,
            'contacted'       => 0,
            'scheduled'       => 0,
            'pending_payment' => 0,
        );

        if ( ! post_type_exists( 'bb_consultation' ) ) {
            return $out;
        }

        $ids = get_posts(
            array(
                'post_type'      => 'bb_consultation',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'no_found_rows'  => true,
                'fields'         => 'ids',
            )
        );

        $out['total'] = count( $ids );

        foreach ( $ids as $id ) {

            $status = (string) get_post_meta( $id, ConsultationMeta::key( 'status' ), true );

            if ( '' === $status ) {
                $status = ConsultationMeta::default_status();
            }

            if ( 'new' === $status ) {
                $out['new']++;
            } elseif ( 'contacted' === $status ) {
                $out['contacted']++;
            } elseif ( 'scheduled' === $status ) {
                $out['scheduled']++;
            }

            $payment_status = (string) get_post_meta( $id, ConsultationMeta::key( 'payment_status' ), true );

            if ( 'pending' === $payment_status ) {
                $out['pending_payment']++;
            }
        }

        return $out;
    }

    /**
     * Mark all notifications read.
     */
    public function handle_mark_read(): void {

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'business-builder' ) );
        }

        check_admin_referer( self::NONCE_ACTION );

        $this->notifications->mark_all_read();

        wp_safe_redirect(
            add_query_arg(
                array( 'page' => self::PAGE_SLUG ),
                admin_url( 'admin.php' )
            )
        );

        exit;
    }
}
