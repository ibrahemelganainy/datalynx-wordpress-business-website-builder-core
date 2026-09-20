<?php
namespace BusinessBuilderCore\Packs\LawFirm\Admin;

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\Currencies;
use BusinessBuilderCore\Packs\LawFirm\PostTypes\ConsultationMeta;
use BusinessBuilderCore\Packs\LawFirm\Appointments\AppointmentMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Payment Logs screen (Law Firm dashboard).
 *
 * A single operational log of EVERY payment transaction on this site,
 * whatever the gateway — API (Paymob/PayPal/Stripe/Fawry/XPay) or manual
 * (wallet/InstaPay/bank transfer). It answers "what happened to each
 * payment?" with: the internal reference, the related consultation /
 * appointment reference, the gateway, the amount, the status, and — for a
 * failure — the structured error code and reason.
 *
 * It EXTENDS the existing payment architecture (it reads the existing
 * bb_payment transactions) and creates no second system. It is per-site
 * (WP_Query on the current blog), so Multisite isolation holds.
 *
 * Filters: by gateway, by status, by free-text (reference / entity / code).
 */
class PaymentLogsAdmin {

    /**
     * Submenu slug (owned by DashboardMenu).
     */
    private const PAGE_SLUG = 'bb-law-firm-payment-logs';

    /**
     * Payments.
     */
    protected PaymentManager $payments;

    /**
     * Constructor.
     *
     * @param PaymentManager $payments Payments.
     */
    public function __construct( PaymentManager $payments ) {
        $this->payments = $payments;
    }

    /**
     * Register hooks.
     */
    public function register(): void {

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
     * Enqueue the shared review styles on this screen only.
     *
     * @param string $hook Current admin hook.
     */
    public function enqueue_assets( string $hook ): void {

        $raw_page = isset( $_GET['page'] ) ? (string) wp_unslash( $_GET['page'] ) : '';
        $page     = sanitize_key( $raw_page );

        if ( self::PAGE_SLUG !== $page ) {
            return;
        }

        wp_enqueue_style(
            'bb-manual-payments',
            BB_CORE_URL . 'assets/css/admin/manual-payments.css',
            array(),
            BB_CORE_VERSION
        );
    }

    /**
     * Rows for the current filters.
     *
     * @param string $gateway Gateway filter ('' = all).
     * @param string $status  Status filter ('' = all).
     * @param string $search  Free-text needle.
     * @return PaymentTransaction[]
     */
    protected function rows( string $gateway, string $status, string $search ): array {

        $out    = array();
        $needle = strtolower( trim( $search ) );

        foreach ( $this->payments->store()->all( 300 ) as $txn ) {

            if ( ! $txn instanceof PaymentTransaction ) {
                continue;
            }

            if ( '' !== $gateway && $txn->gateway !== $gateway ) {
                continue;
            }

            if ( '' !== $status && $txn->status !== $status ) {
                continue;
            }

            if ( '' !== $needle ) {

                $entity_ref = $this->entity_reference( $txn->object_type, $txn->object_id );
                $meta       = is_array( $txn->meta ) ? $txn->meta : array();
                $code       = isset( $meta['failure_code'] ) ? (string) $meta['failure_code'] : '';
                $manual_ref = isset( $meta['manual_reference'] ) ? (string) $meta['manual_reference'] : '';

                $sp = chr( 32 );

                $haystack = strtolower(
                    $txn->public_ref . $sp . $txn->gateway . $sp . $txn->status . $sp . $entity_ref . $sp . $code . $sp . $manual_ref
                );

                if ( false === strpos( $haystack, $needle )) {
                    continue;
                }
            }

            $out[] = $txn;
        }

        return $out;
    }

    /**
     * The public reference of a transaction's related object.
     *
     * @param string $object_type Object type.
     * @param int    $object_id   Object id.
     * @return string
     */
    protected function entity_reference( string $object_type, int $object_id ): string {

        if ( $object_id <= 0 ) {
            return '';
        }

        $key = ( 'appointment' === $object_type )
            ? AppointmentMeta::key( 'public_reference' )
            : ConsultationMeta::key( 'public_reference' );

        return (string) get_post_meta( $object_id, $key, true );
    }

    /**
     * Render the Payment Logs screen.
     */
    public function render_page(): void {

        if ( ! current_user_can( DashboardMenu::capability() )) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'business-builder' ) );
        }

        $raw_gw  = isset( $_GET['bb_pl_gateway'] ) ? (string) wp_unslash( $_GET['bb_pl_gateway'] ) : '';
        $raw_st  = isset( $_GET['bb_pl_status'] ) ? (string) wp_unslash( $_GET['bb_pl_status'] ) : '';
        $raw_q   = isset( $_GET['bb_pl_q'] ) ? (string) wp_unslash( $_GET['bb_pl_q'] ) : '';

        $gateway = sanitize_key( $raw_gw );
        $status  = sanitize_key( $raw_st );
        $search  = sanitize_text_field( $raw_q );

        $rows = $this->rows( $gateway, $status, $search );

        $rtl_class = is_rtl() ? ' bb-rtl' : ' bb-ltr';

        $gateways = $this->payments->gateways();
        $statuses = array( 'pending', 'processing', 'awaiting_payment', 'on_hold', 'paid', 'completed', 'failed', 'cancelled', 'refunded', 'expired' );

        $admin_root = admin_url( 'admin.php' );

        ?>
        <div class="wrap bb-manual-payments<?php echo esc_attr( $rtl_class ); ?>">

            <header class="bb-dashboard-header">
                <div class="bb-dashboard-title">
                    <h1><?php esc_html_e( 'Payment Logs', 'business-builder' ); ?></h1>
                    <p><?php esc_html_e( 'Every payment transaction on this site, across all gateways — with the exact status and, for failures, the reason.', 'business-builder' ); ?></p>
                </div>
                <div class="bb-dashboard-header-actions">
                    <span class="bb-mp-count"><?php
                        echo esc_html(
                            sprintf(
                                /* translators: %d: row count */
                                _n( '%d transaction', '%d transactions', count( $rows ), 'business-builder' ),
                                count( $rows )
                            )
                        );
                    ?></span>
                </div>
            </header>

            <form class="bb-pl-filters" method="get" action="<?php echo esc_url( $admin_root ); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />

                <select name="bb_pl_gateway" aria-label="<?php echo esc_attr__( 'Gateway', 'business-builder' ); ?>">
                    <option value=""><?php esc_html_e( 'All gateways', 'business-builder' ); ?></option>
                    <?php foreach ( $gateways as $id => $gw ) : ?>
                        <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $gateway, $id ); ?>>
                            <?php echo esc_html( $gw->get_name() ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="bb_pl_status" aria-label="<?php echo esc_attr__( 'Status', 'business-builder' ); ?>">
                    <option value=""><?php esc_html_e( 'All statuses', 'business-builder' ); ?></option>
                    <?php foreach ( $statuses as $s ) : ?>
                        <option value="<?php echo esc_attr( $s ); ?>" <?php selected( $status, $s ); ?>>
                            <?php echo esc_html( \BusinessBuilderCore\Core\Payments\Transaction\TransactionStatus::label( $s ) ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input
                    type="search"
                    name="bb_pl_q"
                    value="<?php echo esc_attr( $search ); ?>"
                    placeholder="<?php esc_attr_e( 'Reference, entity, code…', 'business-builder' ); ?>"
                    aria-label="<?php echo esc_attr__( 'Search payments', 'business-builder' ); ?>"
                />

                <button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'business-builder' ); ?></button>
                <a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG ), $admin_root ) ); ?>"><?php esc_html_e( 'Reset', 'business-builder' ); ?></a>
            </form>

            <?php if ( count( $rows ) === 0 ) : ?>
                <div class="bb-mp-empty">
                    <span class="dashicons dashicons-list-view"></span>
                    <p><?php esc_html_e( 'No payments match the current filters.', 'business-builder' ); ?></p>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped bb-pl-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Payment Ref', 'business-builder' ); ?></th>
                            <th><?php esc_html_e( 'Entity', 'business-builder' ); ?></th>
                            <th><?php esc_html_e( 'Gateway', 'business-builder' ); ?></th>
                            <th><?php esc_html_e( 'Amount', 'business-builder' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'business-builder' ); ?></th>
                            <th><?php esc_html_e( 'Code / Reason', 'business-builder' ); ?></th>
                            <th><?php esc_html_e( 'Date', 'business-builder' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'business-builder' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rows as $txn ) : ?>
                            <?php
                            $meta       = is_array( $txn->meta ) ? $txn->meta : array();
                            $code       = isset( $meta['failure_code'] ) ? (string) $meta['failure_code'] : '';
                            $reason     = isset( $meta['failure_reason'] ) ? (string) $meta['failure_reason'] : '';
                            $manual_ref = isset( $meta['manual_reference'] ) ? (string) $meta['manual_reference'] : '';
                            $entity_ref = $this->entity_reference( $txn->object_type, $txn->object_id );

                            $gateway_obj = $this->payments->gateway( $txn->gateway );
                            $gateway_name = null !== $gateway_obj ? $gateway_obj->get_name() : $txn->gateway;

                            $is_appt   = ( 'appointment' === $txn->object_type );
                            $ent_label = $is_appt ? __( 'Appointment', 'business-builder' ) : __( 'Consultation', 'business-builder' );

                            $record_url = $txn->object_id > 0
                                ? admin_url( 'post.php?post=' . $txn->object_id . '&action=edit' )
                                : '';
                            ?>
                            <tr>
                                <td><code><?php echo esc_html( $txn->public_ref ); ?></code></td>
                                <td>
                                    <?php echo esc_html( $ent_label ); ?><br />
                                    <code><?php echo esc_html( '' !== $entity_ref ? $entity_ref : '—' ); ?></code>
                                </td>
                                <td><?php echo esc_html( $gateway_name ); ?></td>
                                <td><?php echo esc_html( Currencies::format( (float) $txn->amount, $txn->currency ) ); ?></td>
                                <td>
                                    <span class="bb-pl-status bb-pl-status-<?php echo esc_attr( sanitize_html_class( $txn->status ) ); ?>">
                                        <?php echo esc_html( \BusinessBuilderCore\Core\Payments\Transaction\TransactionStatus::label( $txn->status ) ); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ( '' !== $code ) : ?>
                                        <code><?php echo esc_html( $code ); ?></code>
                                        <?php if ( '' !== $reason ) : ?>
                                            <br /><span class="bb-pl-reason"><?php echo esc_html( $reason ); ?></span>
                                        <?php endif; ?>
                                    <?php elseif ( '' !== $manual_ref ) : ?>
                                        <?php esc_html_e( 'Manual txn:', 'business-builder' ); ?> <code><?php echo esc_html( $manual_ref ); ?></code>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( $txn->created_at ); ?></td>
                                <td>
                                    <?php if ( '' !== $record_url ) : ?>
                                        <a class="button button-small" href="<?php echo esc_url( $record_url ); ?>">
                                            <?php esc_html_e( 'View Record', 'business-builder' ); ?>
                                        </a>
                                    <?php endif; ?>

                                    <a class="button button-small" href="<?php echo esc_url( \BusinessBuilderCore\Packs\LawFirm\Frontend\ReceiptRoute::url( $txn->public_ref ) ); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php esc_html_e( 'Receipt', 'business-builder' ); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        </div>
        <?php
    }
}
