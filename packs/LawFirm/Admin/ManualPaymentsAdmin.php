<?php
namespace BusinessBuilderCore\Packs\LawFirm\Admin;

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\PaymentResult;
use BusinessBuilderCore\Core\Payments\Checkout\TransactionSynchronizer;
use BusinessBuilderCore\Core\Payments\Currencies;
use BusinessBuilderCore\Core\Notifications\NotificationManager;
use BusinessBuilderCore\Core\Notifications\Notification;
use BusinessBuilderCore\Core\Audit\AuditLog;
use BusinessBuilderCore\Packs\LawFirm\PostTypes\ConsultationMeta;
use BusinessBuilderCore\Packs\LawFirm\Appointments\AppointmentMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Manual Payments review screen (Law Firm dashboard).
 *
 * A real operational queue for MANUAL payments (wallet / InstaPay / bank
 * transfer) awaiting verification. It EXTENDS the existing payment
 * architecture — it reads the existing bb_payment transactions and uses
 * the shared TransactionSynchronizer + NotificationManager, so no second
 * payment/notification system is created.
 *
 * The screen is per-site (all queries go through WP_Query on the current
 * blog), so Multisite isolation is preserved.
 *
 * Actions (View / Approve / Reject) all verify the LawFirm capability AND
 * a nonce, and re-validate the transaction + entity + current status
 * server-side — a notification action can never approve an arbitrary id.
 */
class ManualPaymentsAdmin {

    /**
     * Submenu slug (owned by DashboardMenu).
     */
    private const PAGE_SLUG = 'bb-law-firm-manual-payments';

    /**
     * admin-post approve action.
     */
    private const ACTION_APPROVE = 'bb_manual_payment_approve';

    /**
     * admin-post reject action.
     */
    private const ACTION_REJECT = 'bb_manual_payment_reject';

    /**
     * Nonce action shared by both actions (the id is also carried).
     */
    private const NONCE = 'bb_manual_payment_review';

    /**
     * admin-post action serving a PRIVATE payment proof to an authorized user.
     */
    private const ACTION_PROOF = 'bb_manual_payment_proof';

    /**
     * Payments.
     */
    protected PaymentManager $payments;

    /**
     * Synchronizer.
     */
    protected TransactionSynchronizer $synchronizer;

    /**
     * Notifications.
     */
    protected NotificationManager $notifications;

    /**
     * Audit.
     */
    protected AuditLog $audit;

    /**
     * Constructor.
     *
     * @param PaymentManager          $payments      Payments.
     * @param TransactionSynchronizer $synchronizer  Synchronizer.
     * @param NotificationManager     $notifications Notifications.
     * @param AuditLog                $audit         Audit.
     */
    public function __construct(
        PaymentManager $payments,
        TransactionSynchronizer $synchronizer,
        NotificationManager $notifications,
        AuditLog $audit
    ) {
        $this->payments      = $payments;
        $this->synchronizer  = $synchronizer;
        $this->notifications = $notifications;
        $this->audit         = $audit;
    }

    /**
     * Register hooks.
     */
    public function register(): void {

        add_action( 'admin_post_' . self::ACTION_APPROVE, array( $this, 'handle_approve' ) );
        add_action( 'admin_post_' . self::ACTION_REJECT, array( $this, 'handle_reject' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_post_' . self::ACTION_PROOF, array( $this, 'handle_proof' ) );
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
     * Enqueue the review styles on this screen only.
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
     * Manual payment statuses shown on this screen.
     *
     * Maps the canonical transaction statuses onto the three review buckets
     * an administrator works with. We never invent new statuses:
     *   pending  -> on_hold (awaiting verification)
     *   approved -> paid / completed
     *   rejected -> failed (a manual submission the admin rejected)
     *
     * @return string[]
     */
    protected function review_statuses(): array {

        return array( 'on_hold', 'paid', 'completed', 'failed' );
    }

    /**
     * Classify a transaction into a review bucket.
     *
     * @param PaymentTransaction $txn Transaction.
     * @return string 'pending'|'approved'|'rejected'|''
     */
    protected function review_bucket( PaymentTransaction $txn ): string {

        $status = sanitize_key( $txn->status );

        if ( 'on_hold' === $status ) {
            return 'pending';
        }

        if ( 'paid' === $status || 'completed' === $status ) {
            return 'approved';
        }

        if ( 'failed' === $status ) {
            return 'rejected';
        }

        return '';
    }

    /**
     * All recorded manual submissions, newest first.
     *
     * Includes PENDING, APPROVED and REJECTED rows so an administrator has
     * an auditable dashboard (a rejected submission must not silently
     * vanish). Only MANUAL gateways that carry a real customer submission
     * reference are shown.
     *
     * @return PaymentTransaction[]
     */
    protected function manual_transactions(): array {

        $out = array();

        foreach ( $this->payments->store()->all( 500 ) as $txn ) {

            if ( ! $txn instanceof PaymentTransaction ) {
                continue;
            }

            if ( ! in_array( $txn->status, $this->review_statuses(), true )) {
                continue;
            }

            $gateway = $this->payments->gateway( $txn->gateway );

            /* Only MANUAL gateways are reviewed here. */
            if ( null === $gateway || ! $gateway->is_manual() ) {
                continue;
            }

            $meta = is_array( $txn->meta ) ? $txn->meta : array();

            /* A manual gateway checkout alone is not a customer submission. */
            if ( empty( $meta['manual_reference'] )) {
                continue;
            }

            $out[] = $txn;
        }

        return $out;
    }

    /**
     * Counts per review bucket for the CURRENT site.
     *
     * Computed from the SAME canonical transaction set the list uses, so
     * the summary can never disagree with the rows below it. Multisite
     * isolated (the store is per-site).
     *
     * @return array<string, int> bucket => count
     */
    protected function summary_counts(): array {

        $counts = array(
            'pending'  => 0,
            'approved' => 0,
            'rejected' => 0,
            'total'    => 0,
        );

        foreach ( $this->manual_transactions() as $txn ) {

            $bucket = $this->review_bucket( $txn );

            if ( '' !== $bucket && isset( $counts[ $bucket ] )) {
                $counts[ $bucket ]++;
                $counts['total']++;
            }
        }

        return $counts;
    }

    /**
     * Filter a manual transaction list by a review bucket.
     *
     * @param PaymentTransaction[] $transactions Transactions.
     * @param string               $filter       all|pending|approved|rejected.
     * @return PaymentTransaction[]
     */
    protected function filter_transactions( array $transactions, string $filter ): array {

        $filter = sanitize_key( $filter );

        if ( '' === $filter || 'all' === $filter ) {
            return $transactions;
        }

        if ( ! in_array( $filter, array( 'pending', 'approved', 'rejected' ), true )) {
            return $transactions;
        }

        $out = array();

        foreach ( $transactions as $txn ) {

            if ( $this->review_bucket( $txn ) === $filter ) {
                $out[] = $txn;
            }
        }

        return $out;
    }

    /* ------------------------------------------------------------------
     * Actions
     * ------------------------------------------------------------------ */

    /**
     * Approve a manual payment: transaction -> paid, entity -> paid, notify.
     */
    public function handle_approve(): void {

        $transaction = $this->authorize_action();

        if ( ! $transaction instanceof PaymentTransaction ) {
            wp_die( esc_html__( 'This payment could not be approved (invalid or already reviewed).', 'business-builder' ) );
        }

        $reference = isset( $_POST['manual_reference'] ) ? sanitize_text_field( wp_unslash( $_POST['manual_reference'] )) : '';

        if ( '' === $reference ) {
            $reference = isset( $transaction->meta['manual_reference'] ) ? (string) $transaction->meta['manual_reference'] : '';
        }

        /*
         * Apply the verified result through the SINGLE synchronizer so the
         * transaction AND the related object are updated consistently.
         */
        $result = new PaymentResult( true, 'paid', $reference, __( 'Manual payment approved by administrator.', 'business-builder' ) );

        $updated = $this->synchronizer->apply( $transaction, $result );

        $this->audit->record(
            'payment.manual_approved',
            'payment',
            $updated->id,
            array( 'gateway' => $updated->gateway, 'reference' => $reference )
        );

        $this->notify_decision( $updated, 'approved' );

        $this->redirect_back( 'approved' );
    }

    /**
     * Reject a manual payment: transaction -> failed, entity -> failed, notify.
     */
    public function handle_reject(): void {

        $transaction = $this->authorize_action();

        if ( ! $transaction instanceof PaymentTransaction ) {
            wp_die( esc_html__( 'This payment could not be rejected (invalid or already reviewed).', 'business-builder' ) );
        }

        $reason = isset( $_POST['bb_reject_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bb_reject_reason'] )) : '';

        if ( strlen( $reason ) > 500 ) {
            $reason = substr( $reason, 0, 500 );
        }

        /* Persist the rejection reason on the transaction meta (no new column). */
        $meta = is_array( $transaction->meta ) ? $transaction->meta : array();

        $meta['rejection_reason'] = $reason;
        $meta['rejected_at']      = current_time( 'mysql' );

        $transaction->meta = $meta;

        $this->payments->persist( $transaction );

        $result = new PaymentResult( false, 'failed', $transaction->reference, __( 'Manual payment rejected by administrator.', 'business-builder' ) );

        $updated = $this->synchronizer->apply( $transaction, $result );

        $this->audit->record(
            'payment.manual_rejected',
            'payment',
            $updated->id,
            array( 'gateway' => $updated->gateway, 'reason' => $reason )
        );

        $this->notify_decision( $updated, 'rejected', $reason );

        $this->redirect_back( 'rejected' );
    }
    /**
     * Stream a PRIVATE payment proof to an authorized dashboard user.
     *
     * The uploaded proof is stored as a PRIVATE attachment. Rather than
     * printing its raw (publicly addressable) uploads URL on the review
     * screen, the dashboard links to THIS endpoint, which:
     *
     *   - requires the LawFirm dashboard capability,
     *   - verifies a nonce,
     *   - confirms the attachment is a private proof owned by a transaction
     *     on the CURRENT site (Multisite isolation),
     *   - confirms the attachment belongs to the transaction being viewed,
     *   - streams the file with a safe content type and no store-side
     *     execution, and dies safely on any invalid/deleted reference.
     */
    public function handle_proof(): void {

        if ( ! current_user_can( DashboardMenu::capability() )) {
            wp_die( esc_html__( 'You do not have permission to view this payment proof.', 'business-builder' ) );
        }

        check_admin_referer( self::NONCE );

        $attachment_id    = isset( $_GET['attachment_id'] ) ? absint( wp_unslash( $_GET['attachment_id'] )) : 0;
        $transaction_id   = isset( $_GET['transaction_id'] ) ? absint( wp_unslash( $_GET['transaction_id'] )) : 0;

        if ( $attachment_id <= 0 || $transaction_id <= 0 ) {
            wp_die( esc_html__( 'This payment proof could not be found.', 'business-builder' ) );
        }

        /* The transaction must exist on THIS site (store is site-scoped). */
        $transaction = $this->payments->store()->find( $transaction_id );

        if ( ! $transaction instanceof PaymentTransaction ) {
            wp_die( esc_html__( 'This payment proof could not be found.', 'business-builder' ) );
        }

        /* The proof must be the one actually attached to THIS transaction. */
        $meta        = is_array( $transaction->meta ) ? $transaction->meta : array();
        $linked_id   = isset( $meta['proof_attachment_id'] ) ? (int) $meta['proof_attachment_id'] : 0;

        if ( $linked_id <= 0 || $linked_id !== $attachment_id ) {
            wp_die( esc_html__( 'This payment proof could not be found.', 'business-builder' ) );
        }

        /* The attachment must be a private proof uploaded by this workflow. */
        if ( '1' !== (string) get_post_meta( $attachment_id, '_bb_private_receipt', true )) {
            wp_die( esc_html__( 'This payment proof could not be found.', 'business-builder' ) );
        }

        $post = get_post( $attachment_id );

        if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type ) {
            wp_die( esc_html__( 'This payment proof is no longer available.', 'business-builder' ) );
        }

        $path = get_attached_file( $attachment_id );

        if ( ! is_string( $path ) || '' === $path || ! file_exists( $path )) {
            wp_die( esc_html__( 'This payment proof file is no longer available.', 'business-builder' ) );
        }

        $mime = (string) get_post_mime_type( $attachment_id );

        if ( '' === $mime ) {
            $mime = 'application/octet-stream';
        }

        $allowed_mimes = array( 'image/png', 'image/jpeg', 'image/webp', 'application/pdf' );

        if ( ! in_array( $mime, $allowed_mimes, true )) {
            wp_die( esc_html__( 'This payment proof type is not allowed.', 'business-builder' ) );
        }

        $filename = basename( $path );

        nocache_headers();
        header( 'Content-Type: ' . $mime );
        header( 'Content-Disposition: inline; filename="' . rawurlencode( $filename ) . '"' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Length: ' . (string) filesize( $path ) );

        readfile( $path );
        exit;
    }


    /**
     * Verify capability + nonce and load the target transaction.
     *
     * Returns null (after failing safely) when the capability, nonce,
     * transaction, gateway or current status is not valid — so a crafted
     * POST cannot approve/reject an arbitrary or already-reviewed payment.
     *
     * @return PaymentTransaction|null
     */
    protected function authorize_action(): ?PaymentTransaction {

        if ( ! current_user_can( DashboardMenu::capability() )) {
            wp_die( esc_html__( 'You do not have permission to review payments.', 'business-builder' ) );
        }

        check_admin_referer( self::NONCE );

        $id = isset( $_POST['transaction_id'] ) ? absint( wp_unslash( $_POST['transaction_id'] )) : 0;

        if ( $id <= 0 ) {
            return null;
        }

        $transaction = $this->payments->store()->find( $id );

        if ( ! $transaction instanceof PaymentTransaction ) {
            return null;
        }

        /* Only a manual gateway payment may be reviewed this way. */
        $gateway = $this->payments->gateway( $transaction->gateway );

        if ( null === $gateway || ! $gateway->is_manual() ) {
            return null;
        }

        /*
         * Status validation: only a payment currently awaiting verification
         * can be moved. This blocks double-review and replay.
         */
        if ( 'on_hold' !== $transaction->status ) {
            return null;
        }

        return $transaction;
    }

    /**
     * Dispatch a decision notification with useful context.
     *
     * @param PaymentTransaction $transaction Transaction.
     * @param string             $decision    'approved' | 'rejected'.
     * @param string             $reason      Rejection reason (optional).
     */
    protected function notify_decision( PaymentTransaction $transaction, string $decision, string $reason = '' ): void {

        $entity_label = ( 'appointment' === $transaction->object_type )
            ? __( 'appointment', 'business-builder' )
            : __( 'consultation', 'business-builder' );

        if ( 'approved' === $decision ) {
            $subject = sprintf(
                /* translators: %s: entity type */
                __( 'Manual payment approved for %s', 'business-builder' ),
                $entity_label
            );
        } else {
            $subject = sprintf(
                /* translators: %s: entity type */
                __( 'Manual payment rejected for %s', 'business-builder' ),
                $entity_label
            );
        }

        $message = $reason;

        if ( '' !== $reason ) {
            /* translators: %s: rejection reason */
            $message = sprintf( __( 'Reason: %s', 'business-builder' ), $reason );
        }

        $this->notifications->dispatch(
            new Notification(
                'payment.manual_' . $decision,
                $subject,
                $message,
                '',
                (int) $transaction->object_id,
                'payment:manual:' . $decision . ':' . $transaction->id,
                array(
                    'category'    => 'payment',
                    'entity_type' => $transaction->object_type,
                    'entity_id'   => (int) $transaction->object_id,
                    'reference'   => $transaction->public_ref,
                    'amount'      => $transaction->amount,
                    'currency'    => $transaction->currency,
                    'gateway'     => $transaction->gateway,
                )
            )
        );
    }

    /**
     * Redirect back to the review screen with a status flag.
     *
     * @param string $status Status flag.
     */
    protected function redirect_back( string $status ): void {

        $url = add_query_arg(
            array( 'page' => self::PAGE_SLUG, 'bb_mp_done' => sanitize_key( $status ) ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $url );
        exit;
    }

    /* ------------------------------------------------------------------
     * Rendering
     * ------------------------------------------------------------------ */

    /**
     * Render the review screen.
     */
    public function render_page(): void {

        if ( ! current_user_can( DashboardMenu::capability() )) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'business-builder' ) );
        }

        /* Canonical, site-scoped set of manual submissions. */
        $all = $this->manual_transactions();

        $raw_filter = isset( $_GET['bb_mp_filter'] ) ? (string) wp_unslash( $_GET['bb_mp_filter'] ) : 'all';
        $filter     = sanitize_key( $raw_filter );

        if ( '' === $filter ) {
            $filter = 'all';
        }

        $counts     = $this->summary_counts();
        $transactions = $this->filter_transactions( $all, $filter );

        $raw_done = isset( $_GET['bb_mp_done'] ) ? (string) wp_unslash( $_GET['bb_mp_done'] ) : '';
        $done     = sanitize_key( $raw_done );

        $rtl_class = is_rtl() ? ' bb-rtl' : ' bb-ltr';

        $filters = array(
            'all'      => __( 'All', 'business-builder' ),
            'pending'  => __( 'Pending', 'business-builder' ),
            'approved' => __( 'Approved', 'business-builder' ),
            'rejected' => __( 'Rejected', 'business-builder' ),
        );

        $admin_root = admin_url( 'admin.php' );

        ?>
        <div class="wrap bb-manual-payments<?php echo esc_attr( $rtl_class ); ?>">

            <header class="bb-dashboard-header">
                <div class="bb-dashboard-title">
                    <h1><?php esc_html_e( 'Manual Payments', 'business-builder' ); ?></h1>
                    <p><?php esc_html_e( 'Review submitted wallet, InstaPay and bank-transfer payments for this site.', 'business-builder' ); ?></p>
                </div>
            </header>

            <?php if ( 'approved' === $done ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Payment approved. The consultation/appointment is now paid.', 'business-builder' ); ?></p></div>
            <?php elseif ( 'rejected' === $done ) : ?>
                <div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'Payment rejected. The customer\'s request has been marked unpaid.', 'business-builder' ); ?></p></div>
            <?php endif; ?>

            <div class="bb-mp-summary">
                <div class="bb-mp-stat bb-mp-stat-pending">
                    <span class="bb-mp-stat-label"><?php esc_html_e( 'Pending Review', 'business-builder' ); ?></span>
                    <span class="bb-mp-stat-value"><?php echo esc_html( (string) $counts['pending'] ); ?></span>
                </div>
                <div class="bb-mp-stat bb-mp-stat-approved">
                    <span class="bb-mp-stat-label"><?php esc_html_e( 'Approved', 'business-builder' ); ?></span>
                    <span class="bb-mp-stat-value"><?php echo esc_html( (string) $counts['approved'] ); ?></span>
                </div>
                <div class="bb-mp-stat bb-mp-stat-rejected">
                    <span class="bb-mp-stat-label"><?php esc_html_e( 'Rejected', 'business-builder' ); ?></span>
                    <span class="bb-mp-stat-value"><?php echo esc_html( (string) $counts['rejected'] ); ?></span>
                </div>
                <div class="bb-mp-stat bb-mp-stat-total">
                    <span class="bb-mp-stat-label"><?php esc_html_e( 'Total', 'business-builder' ); ?></span>
                    <span class="bb-mp-stat-value"><?php echo esc_html( (string) $counts['total'] ); ?></span>
                </div>
            </div>

            <nav class="bb-mp-filters" aria-label="<?php echo esc_attr__( 'Filter manual payments', 'business-builder' ); ?>">
                <?php foreach ( $filters as $filter_key => $filter_label ) : ?>
                    <?php
                    $filter_url = add_query_arg(
                        array( 'page' => self::PAGE_SLUG, 'bb_mp_filter' => $filter_key ),
                        $admin_root
                    );
                    $is_active  = ( $filter === $filter_key );
                    $badge      = isset( $counts[ $filter_key ] ) ? (int) $counts[ $filter_key ] : $counts['total'];
                    ?>
                    <a class="bb-mp-filter<?php echo $is_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $filter_url ); ?>">
                        <?php echo esc_html( $filter_label ); ?>
                        <span class="bb-mp-filter-count"><?php echo esc_html( (string) $badge ); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if ( count( $transactions ) === 0 ) : ?>
                <div class="bb-mp-empty">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <p><?php esc_html_e( 'No manual payments match this view.', 'business-builder' ); ?></p>
                </div>
            <?php else : ?>
                <div class="bb-mp-list">
                    <?php foreach ( $transactions as $txn ) : ?>
                        <?php $this->render_row( $txn ); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
        <?php
    }

    /**
     * Render one manual payment row.
     *
     * @param PaymentTransaction $txn Transaction.
     */
    protected function render_row( PaymentTransaction $txn ): void {

        $entity  = $this->entity_context( $txn->object_type, $txn->object_id );
        $meta    = is_array( $txn->meta ) ? $txn->meta : array();

        $manual_ref = isset( $meta['manual_reference'] ) ? (string) $meta['manual_reference'] : '';
        $proof_id   = isset( $meta['proof_attachment_id'] ) ? (int) $meta['proof_attachment_id'] : 0;

        $gateway_name = $txn->gateway;

        $gateway = $this->payments->gateway( $txn->gateway );

        if ( null !== $gateway ) {
            $gateway_name = $gateway->get_name();
        }

        $amount = Currencies::format( (float) $txn->amount, $txn->currency );

        $submitted = isset( $meta['manual_submitted_at'] ) ? (string) $meta['manual_submitted_at'] : $txn->created_at;
        $awaiting  = 'on_hold' === $txn->status;
        $bucket    = $this->review_bucket( $txn );
        $status    = $awaiting ? __( 'Pending Manual Verification', 'business-builder' ) : \BusinessBuilderCore\Core\Payments\Transaction\TransactionStatus::label( $txn->status );

        $rejection_reason = isset( $meta['rejection_reason'] ) ? (string) $meta['rejection_reason'] : '';

        /* A receipt is only meaningful for a pending or approved payment. */
        $receipt_available = in_array( $bucket, array( 'pending', 'approved' ), true );

        ?>
        <article class="bb-mp-card bb-mp-card-<?php echo esc_attr( '' !== $bucket ? $bucket : 'unknown' ); ?>" id="bb-mp-<?php echo esc_attr( (string) $txn->id ); ?>">

            <div class="bb-mp-card-head">
                <div class="bb-mp-head-main">
                    <strong class="bb-mp-ref"><?php echo esc_html( $txn->public_ref ); ?></strong>
                    <span class="bb-mp-badge bb-mp-badge-<?php echo esc_attr( '' !== $bucket ? $bucket : 'unknown' ); ?>"><?php echo esc_html( $status ); ?></span>
                </div>
                <span class="bb-mp-amount"><?php echo esc_html( $amount ); ?></span>
            </div>

            <?php if ( 'rejected' === $bucket && '' !== $rejection_reason ) : ?>
                <p class="bb-mp-reject-reason">
                    <strong><?php esc_html_e( 'Rejection reason:', 'business-builder' ); ?></strong>
                    <?php echo esc_html( $rejection_reason ); ?>
                </p>
            <?php endif; ?>

            <dl class="bb-mp-grid">
                <div class="bb-mp-field">
                    <dt><?php esc_html_e( 'Entity', 'business-builder' ); ?></dt>
                    <dd><?php echo esc_html( $entity['label'] ); ?></dd>
                </div>
                <div class="bb-mp-field">
                    <dt><?php echo esc_html( $entity['ref_label'] ); ?></dt>
                    <dd><code><?php echo esc_html( $entity['reference'] ); ?></code></dd>
                </div>
                <div class="bb-mp-field">
                    <dt><?php esc_html_e( 'Customer', 'business-builder' ); ?></dt>
                    <dd><?php echo esc_html( $entity['customer'] ); ?></dd>
                </div>
                <div class="bb-mp-field">
                    <dt><?php esc_html_e( 'Phone', 'business-builder' ); ?></dt>
                    <dd><?php echo esc_html( $entity['phone'] ); ?></dd>
                </div>
                <div class="bb-mp-field">
                    <dt><?php esc_html_e( 'Payment Method', 'business-builder' ); ?></dt>
                    <dd><?php echo esc_html( $gateway_name ); ?></dd>
                </div>
                <div class="bb-mp-field">
                    <dt><?php esc_html_e( 'Transaction Reference', 'business-builder' ); ?></dt>
                    <dd>
                        <?php if ( '' !== $manual_ref ) : ?>
                            <code class="bb-mp-txref"><?php echo esc_html( $manual_ref ); ?></code>
                        <?php else : ?>
                            <em><?php esc_html_e( '(not provided)', 'business-builder' ); ?></em>
                        <?php endif; ?>
                    </dd>
                </div>
                <div class="bb-mp-field">
                    <dt><?php esc_html_e( 'Submitted', 'business-builder' ); ?></dt>
                    <dd><?php echo esc_html( $submitted ); ?></dd>
                </div>
                <div class="bb-mp-field">
                    <dt><?php esc_html_e( 'Payment Proof', 'business-builder' ); ?></dt>
                    <dd>
                        <?php if ( $proof_id > 0 ) : ?>
                            <?php $this->render_proof( $txn, $proof_id ); ?>
                        <?php else : ?>
                            <em><?php esc_html_e( 'No proof uploaded', 'business-builder' ); ?></em>
                        <?php endif; ?>
                    </dd>
                </div>
                <?php foreach ( $entity['extras'] as $extra_label => $extra_value ) : ?>
                    <div class="bb-mp-field">
                        <dt><?php echo esc_html( (string) $extra_label ); ?></dt>
                        <dd><?php echo esc_html( (string) $extra_value ); ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>

            <div class="bb-mp-actions">

                <?php if ( '' !== $entity['admin_url'] ) : ?>
                    <a class="button" href="<?php echo esc_url( $entity['admin_url'] ); ?>">
                        <?php esc_html_e( 'View Record', 'business-builder' ); ?>
                    </a>
                <?php endif; ?>

                <?php if ( $receipt_available ) : ?>
                    <a class="button" href="<?php echo esc_url( \BusinessBuilderCore\Packs\LawFirm\Frontend\ReceiptRoute::url( $txn->public_ref ) ); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e( 'View Receipt', 'business-builder' ); ?>
                    </a>
                <?php endif; ?>

                <?php if ( $awaiting ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bb-mp-form">
                    <input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_APPROVE ); ?>" />
                    <input type="hidden" name="transaction_id" value="<?php echo esc_attr( (string) $txn->id ); ?>" />
                    <input type="hidden" name="manual_reference" value="<?php echo esc_attr( $manual_ref ); ?>" />
                    <?php wp_nonce_field( self::NONCE ); ?>
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'Approve', 'business-builder' ); ?>
                    </button>
                </form>

                <details class="bb-mp-reject">
                    <summary class="button"><?php esc_html_e( 'Reject', 'business-builder' ); ?></summary>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bb-mp-reject-form">
                        <input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_REJECT ); ?>" />
                        <input type="hidden" name="transaction_id" value="<?php echo esc_attr( (string) $txn->id ); ?>" />
                        <?php wp_nonce_field( self::NONCE ); ?>
                        <label for="bb-mp-reason-<?php echo esc_attr( (string) $txn->id ); ?>">
                            <?php esc_html_e( 'Rejection reason (optional)', 'business-builder' ); ?>
                        </label>
                        <textarea
                            id="bb-mp-reason-<?php echo esc_attr( (string) $txn->id ); ?>"
                            name="bb_reject_reason"
                            rows="2"
                            maxlength="500"
                        ></textarea>
                        <button type="submit" class="button button-secondary">
                            <?php esc_html_e( 'Confirm Rejection', 'business-builder' ); ?>
                        </button>
                    </form>
                </details>
                <?php endif; ?>

            </div>
        </article>
        <?php
    }

    /**
     * Render the payment-proof cell for a transaction.
     *
     * Distinguishes the four REAL states so an administrator is never misled:
     *
     *   - PROOF EXISTS            -> image preview + full-size link, or a
     *                                file row (name/type) for a PDF.
     *   - PROOF MISSING           -> the transaction genuinely has no proof.
     *   - PROOF FILE DELETED      -> a proof id exists but the file/attachment
     *                                is gone; shown as such, never as "missing".
     *   - INVALID PROOF REFERENCE -> the stored id is not a valid proof.
     *
     * Files are PRIVATE. The view/thumbnail links point at the protected
     * admin endpoint (capability + nonce checked), never at a raw public
     * uploads URL, so a proof can never be fetched by guessing an id.
     *
     * @param PaymentTransaction $txn           Transaction.
     * @param int                $attachment_id Stored proof attachment id.
     */
    protected function render_proof( PaymentTransaction $txn, int $attachment_id ): void {

        if ( $attachment_id <= 0 ) {
            echo '<em>' . esc_html__( 'No proof uploaded', 'business-builder' ) . '</em>';
            return;
        }

        $post = get_post( $attachment_id );

        /* The id points at nothing at all -> invalid reference. */
        if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type ) {
            echo '<span class="bb-mp-proof-bad">' . esc_html__( 'Invalid proof reference', 'business-builder' ) . '</span>';
            return;
        }

        /* The attachment record exists but the physical file is gone. */
        $path = get_attached_file( $attachment_id );

        if ( ! is_string( $path ) || '' === $path || ! file_exists( $path )) {
            echo '<span class="bb-mp-proof-bad">' . esc_html__( 'Proof record exists but the file was deleted', 'business-builder' ) . '</span>';
            return;
        }

        $mime     = (string) $post->post_mime_type;
        $is_image = ( 0 === strpos( $mime, 'image/' )) ;

        /* Protected, capability + nonce checked endpoint. */
        $view_url = add_query_arg(
            array(
                'action'         => self::ACTION_PROOF,
                'attachment_id'  => $attachment_id,
                'transaction_id' => (int) $txn->id,
            ),
            admin_url( 'admin-post.php' )
        );

        $view_url = wp_nonce_url( $view_url, self::NONCE );

        if ( $is_image ) {

            /*
             * A generated thumbnail is safe to show inline; the full-size
             * view still goes through the protected endpoint.
             */
            $preview = wp_get_attachment_image(
                $attachment_id,
                'medium',
                false,
                array( 'class' => 'bb-mp-proof-thumb', 'loading' => 'lazy' )
            );

            if ( is_string( $preview ) && '' !== $preview ) {
                printf(
                    '<a class="bb-mp-proof-preview" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                    esc_url( $view_url ),
                    $preview // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                );
            }

            printf(
                '<a class="bb-mp-proof-link" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                esc_url( $view_url ),
                esc_html__( 'View Proof', 'business-builder' )
            );

            return;
        }

        /* Non-image (e.g. PDF): show file name + type with a view link. */
        $file_name = basename( (string) $path );

        printf(
            '<span class="bb-mp-proof-file"><span class="bb-mp-proof-file-name">%s</span><span class="bb-mp-proof-file-type">%s</span></span>',
            esc_html( $file_name ),
            esc_html( $mime )
        );

        printf(
            '<a class="bb-mp-proof-link" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url( $view_url ),
            esc_html__( 'View Proof', 'business-builder' )
        );
    }
    /**
     * Resolve the related entity into a safe, display-ready context.
     *
     * @param string $object_type 'consultation' | 'appointment'.
     * @param int    $object_id   Post id.
     * @return array<string, mixed>
     */
    protected function entity_context( string $object_type, int $object_id ): array {

        $context = array(
            'label'     => __( 'Consultation', 'business-builder' ),
            'ref_label' => __( 'Consultation No', 'business-builder' ),
            'reference' => '',
            'customer'  => '',
            'phone'     => '',
            'admin_url' => '',
            'extras'    => array(),
        );

        if ( $object_id <= 0 ) {
            return $context;
        }

        if ( 'appointment' === $object_type ) {

            $context['label']       = __( 'Appointment', 'business-builder' );
            $context['ref_label']   = __( 'Appointment No', 'business-builder' );
            $context['reference']   = (string) get_post_meta( $object_id, AppointmentMeta::key( 'public_reference' ), true );
            $context['customer']    = (string) get_post_meta( $object_id, AppointmentMeta::key( 'client_name' ), true );
            $context['phone']       = (string) get_post_meta( $object_id, AppointmentMeta::key( 'client_phone' ), true );
            $context['admin_url']   = admin_url( 'post.php?post=' . $object_id . '&action=edit' );

            $date  = (string) get_post_meta( $object_id, AppointmentMeta::key( 'date' ), true );
            $start = (string) get_post_meta( $object_id, AppointmentMeta::key( 'start' ), true );

            if ( '' !== $date ) {
                $when = '' !== $start ? $date . chr( 32 ) . $start : $date;
                $context['extras'][ __( 'Appointment Date', 'business-builder' ) ] = $when;
            }

            $lawyer_id = (int) get_post_meta( $object_id, AppointmentMeta::key( 'lawyer_id' ), true );

            if ( $lawyer_id > 0 ) {
                $context['extras'][ __( 'Lawyer', 'business-builder' ) ] = (string) get_the_title( $lawyer_id );
            }

            return $context;
        }

        $context['reference'] = (string) get_post_meta( $object_id, ConsultationMeta::key( 'public_reference' ), true );
        $context['customer']  = (string) get_post_meta( $object_id, ConsultationMeta::key( 'name' ), true );
        $context['phone']     = (string) get_post_meta( $object_id, ConsultationMeta::key( 'phone' ), true );
        $context['admin_url'] = admin_url( 'post.php?post=' . $object_id . '&action=edit' );

        $area = (string) get_post_meta( $object_id, ConsultationMeta::key( 'practice_area' ), true );

        if ( '' !== $area ) {
            $context['extras'][ __( 'Practice Area', 'business-builder' ) ] = $area;
        }

        return $context;
    }
}
