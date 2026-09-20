<?php

namespace BusinessBuilderCore\Packs\LawFirm\Frontend;

use BusinessBuilderCore\Packs\LawFirm\PostTypes\ConsultationMeta;
use BusinessBuilderCore\Packs\LawFirm\Appointments\AppointmentMeta;
use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\Transaction\Reference;

defined( 'ABSPATH' ) || exit;

/**
 * Private consultation / appointment status page.
 *
 * Usage:
 *   [bb_consultation_status]
 *   or visit a page with ?bb_status=TXN-XXXXXXXXXX (or CNS-...)
 *
 * Security model (anti-enumeration):
 *   - accepts ONLY a non-sequential public reference (never a post id),
 *   - validates the reference shape before any lookup,
 *   - rate-limits lookups per visitor,
 *   - returns one uniform message whether the reference is unknown or
 *     simply not yet payable, so nothing can be inferred by probing,
 *   - never exposes private contact data, internal ids or gateway
 *     credentials — only status, amount and public reference.
 *
 * A reference may be a TRANSACTION reference (TXN-) or a consultation
 * reference (CNS-); both resolve to the same safe summary.
 */
class StatusPage {

    /**
     * Shortcode tag.
     */
    public const SHORTCODE = 'bb_consultation_status';

    /**
     * Query arg for the reference.
     */
    public const QUERY_ARG = 'bb_status';

    /**
     * Payment manager.
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
     * Register the shortcode.
     */
    public function register(): void {

        add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
    }

    /**
     * Render the shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_shortcode( $atts = array() ): string {

        $atts = shortcode_atts(
            array( 'reference' => '' ),
            is_array( $atts ) ? $atts : array(),
            self::SHORTCODE
        );

        $reference = (string) $atts['reference'];

        if ( '' === $reference ) {
            $reference = $this->reference_from_query();
        }

        return $this->render( $reference );
    }

    /**
     * Read the reference from a query argument (sanitized).
     *
     * @return string
     */
    protected function reference_from_query(): string {

        $key = self::QUERY_ARG;

        $present = isset( $_GET[ $key ] );

        if ( false === $present ) {
            return '';
        }

        return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
    }

    /**
     * Render the status page body for a reference.
     *
     * @param string $reference Candidate reference.
     * @return string
     */
    public function render( string $reference ): string {

        $reference = Reference::normalize( $reference );

        $form = $this->form( $reference, '' );

        if ( '' === $reference ) {
            return $form;
        }

        $valid_shape = Reference::is_valid( $reference );

        if ( false === $valid_shape ) {
            return $this->form( $reference, __( 'Please enter a valid reference number.', 'business-builder' ) );
        }

        if ( $this->is_rate_limited() ) {
            return $this->form( $reference, __( 'Too many attempts. Please wait a moment and try again.', 'business-builder' ) );
        }

        $this->bump_rate_limit();

        $summary = $this->resolve( $reference );

        if ( null === $summary ) {
            /* Uniform response: unknown or unavailable look identical. */
            return $this->form( $reference, __( 'No record was found for that reference number.', 'business-builder' ) );
        }

        return $summary . $this->form( '', '' );
    }

    /**
     * Resolve a reference to a safe summary block (or null).
     *
     * @param string $reference Public reference.
     * @return string|null
     */
    protected function resolve( string $reference ): ?string {

        /* Transaction reference (TXN-): show the payment + its object. */
        $txn_prefix = Reference::TRANSACTION_PREFIX . '-';
        $is_txn     = 0 === strpos( $reference, $txn_prefix );

        if ( $is_txn ) {
            return $this->summary_from_transaction( $reference );
        }

        /* Consultation reference (CNS-): resolve the consultation. */
        $cns_prefix = Reference::CONSULTATION_PREFIX . '-';
        $is_cns     = 0 === strpos( $reference, $cns_prefix );

        if ( $is_cns ) {
            return $this->summary_from_consultation( $reference );
        }

        /* Appointment reference (APT-): resolve the appointment. */
        $apt_prefix = Reference::APPOINTMENT_PREFIX . '-';
        $is_apt     = 0 === strpos( $reference, $apt_prefix );

        if ( $is_apt ) {
            return $this->summary_from_appointment( $reference );
        }

        return null;
    }

    /**
     * Summary built from an appointment reference.
     *
     * Mirrors the consultation summary: only non-sensitive public fields
     * are shown, and the customer can only look up their own reference.
     *
     * @param string $reference Public appointment reference.
     * @return string|null
     */
    protected function summary_from_appointment( string $reference ): ?string {

        $post_id = $this->find_appointment_by_reference( $reference );

        if ( $post_id <= 0 ) {
            return null;
        }

        $status = (string) get_post_meta( $post_id, AppointmentMeta::key( 'status' ), true );

        if ( '' === $status ) {
            $status = AppointmentMeta::default_status();
        }

        $payment_state = (string) get_post_meta( $post_id, AppointmentMeta::key( 'payment_status' ), true );

        if ( '' === $payment_state ) {
            $payment_state = 'not_required';
        }

        $rows = array(
            __( 'Reference', 'business-builder' )    => $reference,
            __( 'Appointment Status', 'business-builder' ) => AppointmentMeta::status_label( $status ),
            __( 'Payment Status', 'business-builder' ) => ConsultationMeta::payment_label( $payment_state ),
        );

        $date  = (string) get_post_meta( $post_id, AppointmentMeta::key( 'date' ), true );
        $start = (string) get_post_meta( $post_id, AppointmentMeta::key( 'start' ), true );

        if ( '' !== $date ) {
            $when = $date . ( '' !== $start ? ' ' . $start : '' );
            $rows[ __( 'Appointment Date', 'business-builder' ) ] = $when;
        }

        /*
         * When paid, surface a receipt link. The receipt endpoint is
         * keyed by the PAYMENT (transaction) reference, so we resolve the
         * settled transaction for this appointment.
         */
        $receipt_ref = ( 'paid' === $payment_state )
            ? $this->paid_transaction_ref( 'appointment', $post_id )
            : '';

        return $this->summary( $rows, $reference, '' !== $receipt_ref, $receipt_ref );
    }

    /**
     * Find an appointment id by its public reference meta.
     *
     * @param string $reference Public reference.
     * @return int
     */
    protected function find_appointment_by_reference( string $reference ): int {

        $query = new \WP_Query(
            array(
                'post_type'              => 'bb_appointment',
                'post_status'            => 'publish',
                'posts_per_page'         => 1,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_term_cache' => false,
                'meta_query'             => array(
                    array(
                        'key'   => '_bb_appointment_public_reference',
                        'value' => $reference,
                    ),
                ),
            )
        );

        return isset( $query->posts[0] ) ? (int) $query->posts[0] : 0;
    }

    /**
     * The public reference of a settled transaction for a related object.
     *
     * @param string $object_type Object type.
     * @param int    $object_id   Object id.
     * @return string
     */
    protected function paid_transaction_ref( string $object_type, int $object_id ): string {

        $transactions = $this->payments->transactions_for_object( $object_type, $object_id );

        foreach ( $transactions as $row ) {

            $status = isset( $row['status'] ) ? (string) $row['status'] : '';

            if ( 'paid' === $status && ! empty( $row['public_ref'] )) {
                return (string) $row['public_ref'];
            }
        }

        return '';
    }

    /**
     * Summary built from a payment transaction reference.
     *
     * @param string $reference Public transaction reference.
     * @return string|null
     */
    protected function summary_from_transaction( string $reference ): ?string {

        $transaction = $this->payments->store()->find_by_public_ref( $reference );

        if ( ! $transaction instanceof PaymentTransaction ) {
            $transaction = $this->payments->find_by_public_ref( $reference );
        }

        if ( ! $transaction instanceof PaymentTransaction ) {
            return null;
        }

        $rows = array(
            __( 'Reference', 'business-builder' )   => $transaction->public_ref,
            __( 'Payment Status', 'business-builder' ) => \BusinessBuilderCore\Core\Payments\Transaction\TransactionStatus::label( $transaction->status ),
            __( 'Amount', 'business-builder' )      => \BusinessBuilderCore\Core\Payments\Currencies::format( (float) $transaction->amount, $transaction->currency ),
            __( 'Date', 'business-builder' )        => $transaction->created_at,
        );

        return $this->summary( $rows, $transaction->public_ref, 'paid' === $transaction->status, $transaction->public_ref );
    }

    /**
     * Summary built from a consultation reference.
     *
     * @param string $reference Public consultation reference.
     * @return string|null
     */
    protected function summary_from_consultation( string $reference ): ?string {

        $post_id = $this->find_consultation_by_reference( $reference );

        if ( $post_id <= 0 ) {
            return null;
        }

        $status        = (string) get_post_meta( $post_id, ConsultationMeta::key( 'status' ), true );
        $payment_state = (string) get_post_meta( $post_id, ConsultationMeta::key( 'payment_status' ), true );

        if ( '' === $status ) {
            $status = ConsultationMeta::default_status();
        }

        if ( '' === $payment_state ) {
            $payment_state = 'not_required';
        }

        $practice_area = ConsultationMeta::practice_area_name( $post_id );

        $rows = array(
            __( 'Reference', 'business-builder' )    => $reference,
            __( 'Request Status', 'business-builder' ) => ConsultationMeta::status_label( $status ),
            __( 'Payment Status', 'business-builder' ) => ConsultationMeta::payment_label( $payment_state ),
        );

        if ( '' !== $practice_area ) {
            $rows[ __( 'Practice Area', 'business-builder' ) ] = $practice_area;
        }

        $receipt_ref = ( 'paid' === $payment_state )
            ? $this->paid_transaction_ref( 'consultation', $post_id )
            : '';

        return $this->summary( $rows, $reference, 'paid' === $payment_state, $receipt_ref );
    }

    /**
     * Find a consultation id by its public reference meta.
     *
     * @param string $reference Public reference.
     * @return int
     */
    protected function find_consultation_by_reference( string $reference ): int {

        $query = new \WP_Query(
            array(
                'post_type'              => 'bb_consultation',
                'post_status'            => 'publish',
                'posts_per_page'         => 1,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_term_cache' => false,
                'meta_query'             => array(
                    array(
                        'key'   => '_bb_consultation_public_reference',
                        'value' => $reference,
                    ),
                ),
            )
        );

        $found = isset( $query->posts[0] ) ? (int) $query->posts[0] : 0;

        return $found;
    }

    /**
     * Build the escaped summary markup.
     *
      * @param array<string, string> $rows        Label => value rows.
      * @param string                $reference   Public reference.
      * @param bool                  $is_paid     Settled.
      * @param string                $receipt_ref Transaction reference for the receipt link.
      * @return string
      */
     protected function summary( array $rows, string $reference, bool $is_paid, string $receipt_ref = '' ): string {

         $rows_html = '';

         foreach ( $rows as $label => $value ) {
             $rows_html .= '<tr><th scope="row">' . esc_html( (string) $label ) . '</th>'
                 . '<td>' . esc_html( (string) $value ) . '</td></tr>';
         }

         $receipt = '';

         if ( $is_paid ) {
             /* The receipt endpoint is keyed by the payment (transaction) ref. */
             $link_ref    = '' !== $receipt_ref ? $receipt_ref : $reference;
             $receipt_url = \BusinessBuilderCore\Packs\LawFirm\Frontend\ReceiptRoute::url( $link_ref );
            $receipt     = '<p class="bb-status-receipt"><a class="bb-primary-button" href="'
                . esc_url( $receipt_url )
                . '">' . esc_html__( 'View Receipt', 'business-builder' ) . '</a></p>';
        }

        return '<div class="bb-status-result" id="bb-status-result">'
            . '<h3 class="bb-status-heading">' . esc_html__( 'Your Request Status', 'business-builder' ) . '</h3>'
            . '<table class="bb-status-table"><tbody>'
            . $rows_html // Escaped per-cell above.
            . '</tbody></table>'
            . $receipt
            . '</div>';
    }

    /**
     * The lookup form.
     *
     * @param string $reference Current reference.
     * @param string $message   Optional message.
     * @return string
     */
    protected function form( string $reference, string $message ): string {

        $notice = '';

        if ( '' !== $message ) {
            $notice = '<div class="bb-status-notice">' . esc_html( $message ) . '</div>';
        }

        ob_start();
        ?>
        <div class="bb-status" id="bb-consultation-status">
            <?php
            echo $notice; // Escaped above.
            ?>
            <form class="bb-status-form" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                <label for="bb_status_ref"><?php esc_html_e( 'Reference Number', 'business-builder' ); ?></label>
                <input
                    type="text"
                    name="<?php echo esc_attr( self::QUERY_ARG ); ?>"
                    id="bb_status_ref"
                    value="<?php echo esc_attr( $reference ); ?>"
                    placeholder="TXN-XXXXXXXXXX"
                    autocomplete="off"
                />
                <button type="submit" class="bb-primary-button">
                    <?php esc_html_e( 'Check Status', 'business-builder' ); ?>
                </button>
            </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Rate-limit key for the current visitor.
     *
     * @return string
     */
    protected function rate_key(): string {

        $ip = isset( $_SERVER['REMOTE_ADDR'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
            : 'unknown';

        return 'bb_status_rl_' . md5( $ip . '|' . get_current_blog_id() );
    }

    /**
     * Whether the visitor exceeded the lookup budget.
     *
     * @return bool
     */
    protected function is_rate_limited(): bool {

        $count = (int) get_transient( $this->rate_key() );

        /** Filter the per-window status lookup budget. */
        $max = (int) apply_filters( 'bb_status_rate_limit', 20 );

        return $count >= max( 1, $max );
    }

    /**
     * Increment the visitor's lookup counter.
     */
    protected function bump_rate_limit(): void {

        $key   = $this->rate_key();
        $count = (int) get_transient( $key );

        set_transient( $key, $count + 1, 5 * MINUTE_IN_SECONDS );
    }
}
