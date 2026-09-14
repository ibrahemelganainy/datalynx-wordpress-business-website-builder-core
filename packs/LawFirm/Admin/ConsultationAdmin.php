<?php

namespace BusinessBuilderCore\Packs\LawFirm\Admin;

use BusinessBuilderCore\Packs\LawFirm\PostTypes\ConsultationMeta;
use BusinessBuilderCore\Core\Notifications\NotificationManager;
use BusinessBuilderCore\Core\Notifications\Notification;
use BusinessBuilderCore\Core\Audit\AuditLog;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Consultation request administration.
 *
 * Replaces the raw meta dump with a readable operational panel and adds
 * secure action handlers (status change, manual payment verification).
 *
 * Actions use admin-post.php so they work without JavaScript, and every
 * action is capability-checked, nonce-protected, and validated against
 * the object and the allowed value sets.
 */
class ConsultationAdmin {

    private const POST_TYPE = 'bb_consultation';
    private const ACTION = 'bb_consultation_action';
    private const NONCE_ACTION = 'bb_consultation_action';

    protected NotificationManager $notifications;
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

        add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ));

        /* Readable list-table columns (Practice Area must never be a slug). */
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'list_columns' ));
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_list_column' ), 10, 2 );
        add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_action' ) );

        /* Broaden the Consultation Requests list search across payment/meta. */
        add_action( 'pre_get_posts', array( $this, 'extend_admin_search' ) );
        add_filter( 'get_search_query', array( $this, 'search_placeholder_value' ) );
    }

    /**
     * Define readable consultation list columns.
     *
     * Replaces the title-only list with operational columns. The
     * Practice Area column uses the normalized term name so a raw or
     * encoded slug can never appear in the admin list (bug fix).
     *
     * @param array<string, string> $columns Existing columns.
     * @return array<string, string>
     */
    public function list_columns( array $columns ): array {

        $new = array();

        if ( isset( $columns['cb'] )) {
            $new['cb'] = $columns['cb'];
        }

        $new['title']            = __( 'Consultation', 'business-builder' );
        $new['bb_practice_area'] = __( 'Practice Area', 'business-builder' );
        $new['bb_status']        = __( 'Status', 'business-builder' );
        $new['bb_payment']       = __( 'Payment', 'business-builder' );
        $new['bb_reference']     = __( 'Reference', 'business-builder' );

        if ( isset( $columns['date'] )) {
            $new['date'] = $columns['date'];
        }

        return $new;
    }

    /**
     * Render a value for a custom consultation list column.
     *
     * @param string $column  Column key.
     * @param int    $post_id Consultation id.
     */
    public function render_list_column( string $column, int $post_id ): void {

        switch ( $column ) {

            case 'bb_practice_area':
                $stored = ConsultationMeta::stored_practice_area( $post_id );
                $term = ConsultationMeta::resolve_practice_area( $stored );
                
                if ( $term instanceof \WP_Term ) {
                    echo esc_html( $term->name );
                } else {
                    echo esc_html__( '(not specified)', 'business-builder' );
                }
                break;

            case 'bb_status':
                $status = (string) get_post_meta( $post_id, ConsultationMeta::key( 'status' ), true );
                if ( '' === $status ) {
                    $status = ConsultationMeta::default_status();
                }
                echo esc_html( ConsultationMeta::status_label( $status ));
                break;

            case 'bb_payment':
                $state = (string) get_post_meta( $post_id, ConsultationMeta::key( 'payment_status' ), true );
                if ( '' === $state ) {
                    $state = 'not_required';
                }
                echo esc_html( ConsultationMeta::payment_label( $state ));
                break;

            case 'bb_reference':
                $ref = (string) get_post_meta( $post_id, ConsultationMeta::key( 'public_reference' ), true );
                echo '' !== $ref
                    ? '<code>' . esc_html( $ref ) . '</code>'
                    : '&mdash;';
                break;
        }
    }

    /**
     * Register the operational meta box.
     */
    public function register_meta_box(): void {

        add_meta_box(
            'bb_consultation_details',
            __( 'Consultation Request', 'business-builder' ),
            array( $this, 'render_meta_box' ),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Render the operational panel.
     *
     * @param \WP_Post $post Consultation post.
     */
    public function render_meta_box( $post ): void {

        $id = (int) $post->ID;

        $name    = (string) get_post_meta( $id, ConsultationMeta::key( 'name' ), true );
        $phone   = (string) get_post_meta( $id, ConsultationMeta::key( 'phone' ), true );
        $email   = (string) get_post_meta( $id, ConsultationMeta::key( 'email' ), true );
        $prefer  = (string) get_post_meta( $id, ConsultationMeta::key( 'preferred_contact' ), true );
        $message = (string) get_post_meta( $id, ConsultationMeta::key( 'message' ), true );
        $created = (string) get_post_meta( $id, ConsultationMeta::key( 'created' ), true );

        $status = (string) get_post_meta( $id, ConsultationMeta::key( 'status' ), true );

        if ( '' === $status ) {
            $status = ConsultationMeta::default_status();
        }

        $payment_required = '1' === (string) get_post_meta( $id, ConsultationMeta::key( 'payment_required' ), true );

        $payment_status = (string) get_post_meta( $id, ConsultationMeta::key( 'payment_status' ), true );

        if ( '' === $payment_status ) {
            $payment_status = 'not_required';
        }

        $payment_amount   = (string) get_post_meta( $id, ConsultationMeta::key( 'payment_amount' ), true );
        $payment_currency = (string) get_post_meta( $id, ConsultationMeta::key( 'payment_currency' ), true );

        /* Client. */
        echo '<h4>' . esc_html__( 'Client', 'business-builder' ) . '</h4>';
        echo '<table class="widefat striped">';
        $this->row( __( 'Name', 'business-builder' ), $name );
        $this->row( __( 'Phone', 'business-builder' ), $phone );
        $this->row( __( 'Email', 'business-builder' ), $email );
        $this->row( __( 'Preferred Contact', 'business-builder' ), $prefer );
        echo '</table>';

        /* Legal matter (readable practice area + link). */
        echo '<h4>' . esc_html__( 'Legal Matter', 'business-builder' ) . '</h4>';
        echo '<table class="widefat striped"><tr>';
        echo '<th style="width:180px;">' . esc_html__( 'Practice Area', 'business-builder' ) . '</th>';
        $area_cell = $this->practice_area_html( $id );
        echo '<td>' . wp_kses_post( $area_cell ) . '</td>';
        echo '</tr></table>';

        /* Request. */
        echo '<h4>' . esc_html__( 'Request', 'business-builder' ) . '</h4>';
        echo '<div style="padding:12px;background:#f6f7f7;border:1px solid #ddd;">';
        echo wp_kses_post( wpautop( $message ) );
        echo '</div>';

        /* Status. */
        echo '<h4>' . esc_html__( 'Status', 'business-builder' ) . '</h4>';
        echo '<table class="widefat striped">';
        $this->row( __( 'Consultation Status', 'business-builder' ), ConsultationMeta::status_label( $status ) );
        $this->row( __( 'Payment Status', 'business-builder' ), ConsultationMeta::payment_label( $payment_status ) );
        $fee = trim( $payment_amount . ' ' . $payment_currency );
        $this->row( __( 'Consultation Fee', 'business-builder' ), $fee );
        echo '</table>';

        if ( '' !== $created ) {
            echo '<h4>' . esc_html__( 'Submitted', 'business-builder' ) . '</h4>';
            echo '<p>' . esc_html( $created ) . '</p>';
        }

        $this->render_actions( $id, $status, $payment_status, $payment_required );
    }

    /**
     * Echo a labelled table row.
     *
     * @param string $label Label.
     * @param string $value Value.
     */
    protected function row( string $label, string $value ): void {

        echo '<tr>';
        echo '<th style="width:180px;">' . esc_html( $label ) . '</th>';
        echo '<td>' . esc_html( $value ) . '</td>';
        echo '</tr>';
    }

    /**
     * Build the readable, linked practice-area cell.
     *
     * @param int $id Consultation id.
     * @return string
     */
    protected function practice_area_html( int $id ): string {

        $term = ConsultationMeta::resolve_practice_area(
            ConsultationMeta::stored_practice_area( $id )
        );

        if ( ! $term instanceof \WP_Term ) {
            return esc_html__( '(not specified)', 'business-builder' );
        }

        $link = get_edit_term_link(
            $term->term_id,
            ConsultationMeta::practice_area_taxonomy()
        );

        if ( $link ) {
            return '<a href="' . esc_url( $link ) . '">' . esc_html( $term->name ) . '</a>';
        }

        return esc_html( $term->name );
    }

    /**
     * Render action controls.
     *
     * @param int    $id               Consultation id.
     * @param string $status           Current status.
     * @param string $payment_status   Current payment status.
     * @param bool   $payment_required Whether payment is required.
     */
    protected function render_actions( int $id, string $status, string $payment_status, bool $payment_required ): void {

        echo '<hr>';
        echo '<h4>' . esc_html__( 'Actions', 'business-builder' ) . '</h4>';

        $endpoint = admin_url( 'admin-post.php' );
        echo '<form method="post" action="' . esc_url( $endpoint ) . '" style="display:inline-block;margin-right:12px;">';
        echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
        echo '<input type="hidden" name="consultation_id" value="' . esc_attr( (string) $id ) . '" />';
        echo '<input type="hidden" name="bb_op" value="set_status" />';
        wp_nonce_field( self::NONCE_ACTION );
        echo '<label><strong>' . esc_html__( 'Change Status', 'business-builder' ) . '</strong> ';
        echo '<select name="status">';

        foreach ( ConsultationMeta::statuses() as $slug => $label ) {
            echo '<option value="' . esc_attr( $slug ) . '" ' . selected( $status, $slug, false ) . '>' . esc_html( $label ) . '</option>';
        }

        echo '</select></label> ';
        echo '<button type="submit" class="button button-primary">' . esc_html__( 'Apply', 'business-builder' ) . '</button>';
        echo '</form>';

        if ( $payment_required && in_array( $payment_status, array( 'pending', 'failed' ), true ) ) {

            echo '<form method="post" action="' . esc_url( $endpoint ) . '" style="display:inline-block;">';
            echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
            echo '<input type="hidden" name="consultation_id" value="' . esc_attr( (string) $id ) . '" />';
            echo '<input type="hidden" name="bb_op" value="mark_paid" />';
            wp_nonce_field( self::NONCE_ACTION );
            echo '<button type="submit" class="button">' . esc_html__( 'Mark Payment Verified', 'business-builder' ) . '</button>';
            echo '</form>';
        }
    }

    /**
     * Handle an action.
     */
    public function handle_action(): void {

        if ( ! current_user_can( 'edit_pages' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'business-builder' ) );
        }

        check_admin_referer( self::NONCE_ACTION );

        $id = isset( $_POST['consultation_id'] ) ? absint( $_POST['consultation_id'] ) : 0;

        if ( $id <= 0 || self::POST_TYPE !== get_post_type( $id ) ) {
            wp_die( esc_html__( 'Invalid consultation request.', 'business-builder' ) );
        }

        if ( ! current_user_can( 'edit_post', $id ) ) {
            wp_die( esc_html__( 'You do not have permission to edit this request.', 'business-builder' ) );
        }

        $op = isset( $_POST['bb_op'] ) ? sanitize_key( wp_unslash( $_POST['bb_op'] ) ) : '';

        if ( 'set_status' === $op ) {
            $this->do_set_status( $id );
        } elseif ( 'mark_paid' === $op ) {
            $this->do_mark_paid( $id );
        }

        wp_safe_redirect( get_edit_post_link( $id, 'raw' ) );
        exit;
    }

    /**
     * Change the status.
     *
     * @param int $id Consultation id.
     */
    protected function do_set_status( int $id ): void {
        $requested = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

        if ( ! array_key_exists( $requested, ConsultationMeta::statuses() ) ) {
            return;
        }

        update_post_meta( $id, ConsultationMeta::key( 'status' ), $requested );

        $this->audit->record(
            'consultation.status_changed',
            'consultation',
            $id,
            array( 'status' => $requested )
        );

        $this->notifications->dispatch(
            new Notification(
                'consultation.status',
                sprintf(
                    /* translators: 1: id, 2: status */
                    __( 'Consultation #%1$d status changed to %2$s', 'business-builder' ),
                    $id,
                    ConsultationMeta::status_label( $requested )
                ),
                '',
                '',
                $id,
                'consultation:' . $id . ':status:' . $requested
            )
        );
    }

    /**
     * Manually verify a payment.
     *
     * @param int $id Consultation id.
     */
    protected function do_mark_paid( int $id ): void {

        $required = '1' === (string) get_post_meta( $id, ConsultationMeta::key( 'payment_required' ), true );

        if ( ! $required ) {
            return;
        }

        update_post_meta( $id, ConsultationMeta::key( 'payment_status' ), 'paid' );

        $this->audit->record( 'payment.manually_verified', 'consultation', $id, array() );

        $this->notifications->dispatch(
            new Notification(
                'payment.paid',
                sprintf(
                    /* translators: %d: consultation id */
                    __( 'Payment verified for consultation #%d', 'business-builder' ),
                    $id
                ),
                '',
                '',
                $id,
                'consultation:' . $id . ':payment:paid:manual'
            )
        );
    }

    /**
     * Extend the admin list search for consultation requests.
     *
     * Adds a broad OR match across the public reference, customer name,
     * email, phone, practice area, payment reference, payment status and
     * consultation status. Practice-area searches also resolve the term so
     * a match on the taxonomy name works (Arabic included).
     *
     * @param \WP_Query $query Query.
     */
    public function extend_admin_search( $query ): void {

        $is_admin = is_admin() || (bool) apply_filters( 'bb_consultation_search_force', false );
        $is_query = ( $query instanceof \WP_Query );

        if ( ! $is_admin || ! $is_query ) {
            return;
        }

        $post_type = $query->get( 'post_type' );

        if ( self::POST_TYPE !== $post_type ) {
            return;
        }

        $raw_term = (string) $query->get( 's' );
        $trimmed  = trim( $raw_term );

        if ( '' === $trimmed ) {
            return;
        }

        $term = sanitize_text_field( wp_unslash( $raw_term ) );

        /*
         * Remove WordPress's default content search clause. Otherwise the
         * built-in `s` match runs as an AND with our meta_query and every
         * row that lacks a post_title/post_content match is dropped, even
         * when it matches a reference/email/payment field.
         */
        $query->set( 's', '' );

        $meta_keys = array(
            'public_reference',
            'name',
            'email',
            'phone',
            'practice_area',
            'practice_area_id',
            'payment_reference',
            'payment_status',
            'status',
        );

        $meta_query = array( 'relation' => 'OR' );

        foreach ( $meta_keys as $short ) {
            $meta_query[] = array(
                'key'     => ConsultationMeta::key( $short ),
                'value'   => $term,
                'compare' => 'LIKE',
            );
        }

        $terms = get_terms(
            array(
                'taxonomy'   => ConsultationMeta::practice_area_taxonomy(),
                'hide_empty' => false,
                'search'     => $term,
            )
        );

        $term_ids   = array();
        $terms_ok   = ( ! is_wp_error( $terms ) );

        if ( $terms_ok ) {
            foreach ( $terms as $found ) {
                $term_ids[] = (int) $found->term_id;
            }
        }

        $has_terms = ( count( $term_ids ) > 0 );

        if ( $has_terms ) {

            $id_query = array( 'relation' => 'OR' );

            foreach ( $term_ids as $tid ) {
                $id_query[] = array(
                    'key'   => ConsultationMeta::key( 'practice_area_id' ),
                    'value' => (string) $tid,
                );
            }

            $meta_query = array(
                'relation' => 'OR',
                $meta_query,
                $id_query,
            );
        }

        $existing    = $query->get( 'meta_query' );
        $has_existing = ( is_array( $existing ) && count( $existing ) > 0 );

        if ( $has_existing ) {
            $query->set( 'meta_query', array( 'relation' => 'AND', $existing, $meta_query ) );
        } else {
            $query->set( 'meta_query', $meta_query );
        }
    }

    /**
     * Keep the search box showing the raw term.
     *
     * @param string $term Term.
     * @return string
     */
    public function search_placeholder_value( $term ) {

        return $term;
    }
}
