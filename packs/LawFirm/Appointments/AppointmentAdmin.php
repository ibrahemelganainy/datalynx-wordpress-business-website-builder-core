<?php

namespace BusinessBuilderCore\Packs\LawFirm\Appointments;

use BusinessBuilderCore\Core\Notifications\NotificationManager;
use BusinessBuilderCore\Core\Notifications\Notification;
use BusinessBuilderCore\Core\Audit\AuditLog;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Appointment administration.
 *
 * Readable panel + secure action handlers (confirm / complete / cancel)
 * exposed on the appointment edit screen. Uses admin-post.php so it works
 * without JavaScript. Every action is capability-checked, nonce-protected
 * and validated against the allowed status set.
 */
class AppointmentAdmin {

    /**
     * Action.
     */
    private const ACTION = 'bb_appointment_action';

    /**
     * Nonce.
     */
    private const NONCE_ACTION = 'bb_appointment_action';

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
     * @param NotificationManager $notifications Notifications.
     * @param AuditLog            $audit         Audit.
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

        add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
        add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_action' ) );

        /* Print the real action forms OUTSIDE the post editor form (footer). */
        add_action( 'admin_footer-post.php', array( $this, 'render_actions_form' ) );

        /* Readable Appointments list columns + broadened search. */
        add_filter( 'manage_' . Appointment::POST_TYPE . '_posts_columns', array( $this, 'list_columns' ) );
        add_action( 'manage_' . Appointment::POST_TYPE . '_posts_custom_column', array( $this, 'render_list_column' ), 10, 2 );
        add_action( 'pre_get_posts', array( $this, 'handle_admin_orderby' ) );
        add_filter( 'manage_edit-' . Appointment::POST_TYPE . '_sortable_columns', array( $this, 'sortable_columns' ) );
        add_action( 'admin_head-edit.php', array( $this, 'print_list_styles' ) );
        add_action( 'pre_get_posts', array( $this, 'extend_admin_search' ) );
    }

    /**
     * Register the meta box.
     */
    public function register_meta_box(): void {

        add_meta_box(
            'bb_appointment_details',
            __( 'Appointment', 'business-builder' ),
            array( $this, 'render_meta_box' ),
            Appointment::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Render the panel.
     *
     * @param \WP_Post $post Appointment post.
     */
    public function render_meta_box( $post ): void {

        $id = (int) $post->ID;

        $date = (string) get_post_meta( $id, AppointmentMeta::key( 'date' ), true );
        $start = (string) get_post_meta( $id, AppointmentMeta::key( 'start' ), true );
        $end = (string) get_post_meta( $id, AppointmentMeta::key( 'end' ), true );
        $status = (string) get_post_meta( $id, AppointmentMeta::key( 'status' ), true );
        $type = (string) get_post_meta( $id, AppointmentMeta::key( 'type' ), true );
        $lawyer_id = (int) get_post_meta( $id, AppointmentMeta::key( 'lawyer_id' ), true );
        $notes = (string) get_post_meta( $id, AppointmentMeta::key( 'notes' ), true );

        if ( '' === $status ) {
            $status = AppointmentMeta::default_status();
        }

        $lawyer_name = $lawyer_id > 0 ? get_the_title( $lawyer_id ) : __( 'No preference', 'business-builder' );

        echo '<table class="widefat striped">';
        $this->row( __( 'Client', 'business-builder' ), (string) get_post_meta( $id, AppointmentMeta::key( 'client_name' ), true ) );
        $this->row( __( 'Phone', 'business-builder' ), (string) get_post_meta( $id, AppointmentMeta::key( 'client_phone' ), true ) );
        $this->row( __( 'Email', 'business-builder' ), (string) get_post_meta( $id, AppointmentMeta::key( 'client_email' ), true ) );
        $this->row( __( 'Lawyer', 'business-builder' ), (string) $lawyer_name );
        $this->row( __( 'Practice Area', 'business-builder' ), (string) get_post_meta( $id, AppointmentMeta::key( 'practice_area' ), true ) );
        $this->row( __( 'Type', 'business-builder' ), AppointmentMeta::type_label( $type ) );
        $this->row( __( 'Date', 'business-builder' ), $date );
        $this->row( __( 'Time', 'business-builder' ), trim( $start . ' - ' . $end, ' -' ) );
        $this->row( __( 'Status', 'business-builder' ), AppointmentMeta::status_label( $status ) );
        echo '</table>';

        if ( '' !== $notes ) {
            echo '<h4>' . esc_html__( 'Notes', 'business-builder' ) . '</h4>';
            echo '<div style="padding:12px;background:#f6f7f7;border:1px solid #ddd;">';
            echo wp_kses_post( wpautop( $notes ) );
            echo '</div>';
        }

        $this->render_actions( $id, $status );
    }

    /**
     * Echo a labelled row.
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
     * Render action buttons.
     *
     * @param int    $id     Appointment id.
     * @param string $status Current status.
     */
    protected function render_actions( int $id, string $status ): void {

        echo '<hr>';
        echo '<h4>' . esc_html__( 'Actions', 'business-builder' ) . '</h4>';

        /*
         * NOTE: a meta box lives INSIDE the post editor's <form id="post">.
         * A nested <form> is invalid HTML and the browser drops it, so a
         * meta-box form would actually submit the POST editor and never run
         * our admin-post action. Each action button is therefore associated
         * (via the HTML5 `form` attribute) with a real <form> printed OUTSIDE
         * the post form in the admin footer (render_actions_form()). No JS.
         */
        $actions = array(
            'confirmed' => __( 'Confirm', 'business-builder' ),
            'completed' => __( 'Mark Completed', 'business-builder' ),
            'cancelled' => __( 'Cancel', 'business-builder' ),
        );

        foreach ( $actions as $slug => $label ) {

            if ( $slug === $status ) {
                continue;
            }

            $form_id = 'bb-appointment-action-form-' . sanitize_key( $slug );

            echo '<button type="submit" form="' . esc_attr( $form_id ) . '" class="button" style="margin-right:8px;">' . esc_html( $label ) . '</button>';
        }
    }

    /**
     * Print the REAL action forms in the admin footer, OUTSIDE the post form.
     *
     * WordPress renders the post editor inside <form id="post">; a meta box
     * cannot contain its own <form>. These standalone forms are associated
     * with the meta-box buttons through the HTML5 `form="<id>"` attribute.
     */
    public function render_actions_form(): void {

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        if ( ! $screen || Appointment::POST_TYPE !== $screen->post_type || 'post' !== $screen->base ) {
            return;
        }

        $id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] )) : (int) get_the_ID();

        if ( $id <= 0 || Appointment::POST_TYPE !== get_post_type( $id )) {
            return;
        }

        $endpoint = admin_url( 'admin-post.php' );

        foreach ( array( 'confirmed', 'completed', 'cancelled' ) as $slug ) {

            $form_id = 'bb-appointment-action-form-' . sanitize_key( $slug );

            echo '<form id="' . esc_attr( $form_id ) . '" method="post" action="' . esc_url( $endpoint ) . '" style="display:none;">';
            echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
            echo '<input type="hidden" name="appointment_id" value="' . esc_attr( (string) $id ) . '" />';
            echo '<input type="hidden" name="target_status" value="' . esc_attr( $slug ) . '" />';
            wp_nonce_field( self::NONCE_ACTION );
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

        $id = isset( $_POST['appointment_id'] ) ? absint( $_POST['appointment_id'] ) : 0;

        if ( $id <= 0 || Appointment::POST_TYPE !== get_post_type( $id ) ) {
            wp_die( esc_html__( 'Invalid appointment.', 'business-builder' ) );
        }

        if ( ! current_user_can( 'edit_post', $id ) ) {
            wp_die( esc_html__( 'You do not have permission to edit this appointment.', 'business-builder' ) );
        }

        $raw_target = isset( $_POST['target_status'] ) ? wp_unslash( $_POST['target_status'] ) : '';
        $target = sanitize_key( $raw_target );

        if ( ! array_key_exists( $target, AppointmentMeta::statuses() ) ) {
            wp_die( esc_html__( 'Invalid status.', 'business-builder' ) );
        }

        update_post_meta( $id, AppointmentMeta::key( 'status' ), $target );
        update_post_meta( $id, AppointmentMeta::key( 'updated' ), current_time( 'mysql' ) );

        $this->audit->record(
            'appointment.status_changed',
            'appointment',
            $id,
            array( 'status' => $target )
        );

        $this->notifications->dispatch(
            new Notification(
                'appointment.status',
                sprintf(
                    /* translators: 1: id, 2: status */
                    __( 'Appointment #%1$d is now %2$s', 'business-builder' ),
                    $id,
                    AppointmentMeta::status_label( $target )
                ),
                '',
                '',
                $id,
                'appointment:' . $id . ':status:' . $target
            )
        );

        /*
         * Redirect back to the SAME edit screen. get_edit_post_link() can
         * be empty for a private, non-standard post type, and an empty
         * wp_safe_redirect() target misbehaves, so we build the edit URL
         * explicitly and fall back to the list screen.
         */
        $redirect = get_edit_post_link( $id, 'raw' );

        if ( ! is_string( $redirect ) || '' === $redirect ) {
            $redirect = admin_url( 'post.php?post=' . $id . '&action=edit' );
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Define readable Appointments list columns.
     *
     * Replaces the title-only list with operational columns so an
     * administrator can triage bookings at a glance: the client, the
     * assigned lawyer, the appointment date/time, the status and the
     * payment state. The public reference is shown (never a raw id).
     *
     * @param array<string, string> $columns Existing columns.
     * @return array<string, string>
     */
    public function list_columns( array $columns ): array {

        $new = array();

        if ( isset( $columns['cb'] )) {
            $new['cb'] = $columns['cb'];
        }

        $new['title']           = __( 'Appointment', 'business-builder' );
        $new['bb_appt_client']  = __( 'Client', 'business-builder' );
        $new['bb_appt_lawyer']  = __( 'Lawyer', 'business-builder' );
        $new['bb_appt_when']    = __( 'Date / Time', 'business-builder' );
        $new['bb_appt_status']  = __( 'Status', 'business-builder' );
        $new['bb_appt_payment'] = __( 'Payment', 'business-builder' );
        $new['bb_appt_ref']     = __( 'Reference', 'business-builder' );

        if ( isset( $columns['date'] )) {
            $new['date'] = $columns['date'];
        }

        return $new;
    }

    /**
     * Columns that can be sorted.
     *
     * Date/Time and Status sort by their stored meta so the most relevant
     * operational ordering is available from the list headers.
     *
     * @param array<string, string> $columns Sortable columns.
     * @return array<string, string>
     */
    public function sortable_columns( array $columns ): array {

        $columns['bb_appt_when']   = 'bb_appt_when';
        $columns['bb_appt_status'] = 'bb_appt_status';

        return $columns;
    }

    /**
     * Render a value for a custom appointment list column.
     *
     * @param string $column  Column key.
     * @param int    $post_id Appointment id.
     */
    public function render_list_column( string $column, int $post_id ): void {

        switch ( $column ) {

            case 'bb_appt_client':
                $name  = (string) get_post_meta( $post_id, AppointmentMeta::key( 'client_name' ), true );
                $phone = (string) get_post_meta( $post_id, AppointmentMeta::key( 'client_phone' ), true );

                if ( '' === $name ) {
                    echo '&mdash;';
                    break;
                }

                echo esc_html( $name );

                if ( '' !== $phone ) {
                    echo '<br /><span class="description">' . esc_html( $phone ) . '</span>';
                }
                break;

            case 'bb_appt_lawyer':
                $lawyer_id = (int) get_post_meta( $post_id, AppointmentMeta::key( 'lawyer_id' ), true );

                if ( $lawyer_id > 0 && get_post( $lawyer_id )) {
                    $link = get_edit_post_link( $lawyer_id );
                    $name = get_the_title( $lawyer_id );

                    echo $link
                        ? '<a href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a>'
                        : esc_html( $name );
                    break;
                }

                echo '<em>' . esc_html__( 'No preference', 'business-builder' ) . '</em>';
                break;

            case 'bb_appt_when':
                $date  = (string) get_post_meta( $post_id, AppointmentMeta::key( 'date' ), true );
                $start = (string) get_post_meta( $post_id, AppointmentMeta::key( 'start' ), true );
                $end   = (string) get_post_meta( $post_id, AppointmentMeta::key( 'end' ), true );

                if ( '' === $date ) {
                    echo '&mdash;';
                    break;
                }

                echo esc_html( $date );

                if ( '' !== $start ) {
                    $time = ( '' !== $end ) ? $start . '–' . $end : $start;
                    echo '<br /><span class="description">' . esc_html( $time ) . '</span>';
                }
                break;

            case 'bb_appt_status':
                $status = (string) get_post_meta( $post_id, AppointmentMeta::key( 'status' ), true );

                if ( '' === $status ) {
                    $status = AppointmentMeta::default_status();
                }


                $cls = 'bb-appt-status bb-appt-status-' . sanitize_html_class( $status );

                echo '<span class="' . esc_attr( $cls ) . '">' . esc_html( AppointmentMeta::status_label( $status )) . '</span>';
                break;
            case 'bb_appt_payment':
                $state = (string) get_post_meta( $post_id, AppointmentMeta::key( 'payment_status' ), true );

                if ( '' === $state ) {
                    $state = 'not_required';
                }

                
                $pcls = 'bb-appt-payment bb-appt-payment-' . sanitize_html_class( $state );

                echo '<span class="' . esc_attr( $pcls ) . '">' . esc_html( \BusinessBuilderCore\Packs\LawFirm\PostTypes\ConsultationMeta::payment_label( $state )) . '</span>';
                break;

            case 'bb_appt_ref':
                $ref = (string) get_post_meta( $post_id, AppointmentMeta::key( 'public_reference' ), true );

                echo '' !== $ref
                    ? '<code>' . esc_html( $ref ) . '</code>'
                    : '&mdash;';
                break;
        }
    }

    /**
     * Extend the admin list search for appointments.
     *
     * Adds a broad OR match across the public reference, client name/phone/
     * email, lawyer, appointment type and payment status so an administrator
     * can find a booking by any operational field (mirrors the consultation
     * list search). Respects existing meta queries.
     *
     * @param \WP_Query $query Query.
     */
    public function extend_admin_search( $query ): void {

        $is_admin = is_admin() || (bool) apply_filters( 'bb_appointment_search_force', false );
        $is_query = ( $query instanceof \WP_Query );

        if ( ! $is_admin || ! $is_query ) {
            return;
        }

        if ( Appointment::POST_TYPE !== $query->get( 'post_type' )) {
            return;
        }

        $raw_term = (string) $query->get( 's' );
        $trimmed  = trim( $raw_term );

        if ( '' === $trimmed ) {
            return;
        }

        $term = sanitize_text_field( wp_unslash( $raw_term ) );

        /* Drop WP's default content search so meta-only matches survive. */
        $query->set( 's', '' );

        $meta_keys = array(
            'public_reference',
            'client_name',
            'client_phone',
            'client_email',
            'practice_area',
            'type',
            'status',
            'payment_status',
            'payment_reference',
        );

        $meta_query = array( 'relation' => 'OR' );

        foreach ( $meta_keys as $short ) {
            $meta_query[] = array(
                'key'     => AppointmentMeta::key( $short ),
                'value'   => $term,
                'compare' => 'LIKE',
            );
        }

        $existing     = $query->get( 'meta_query' );
        $has_existing = ( is_array( $existing ) && count( $existing ) > 0 );

        if ( $has_existing ) {
            $query->set( 'meta_query', array( 'relation' => 'AND', $existing, $meta_query ) );
        } else {
            $query->set( 'meta_query', $meta_query );
        }
    }

    /**
     * Apply the sortable-column ordering to the appointments list.
     *
     * The Date/Time and Status columns are virtual (stored meta), so
     * WordPress needs the explicit meta_key/orderby mapping. Numeric
     * fields (Y-m-d dates) sort correctly; anything else falls back to
     * the default ordering so the list is never left in a broken state.
     *
     * @param \WP_Query $query Query.
     */
    public function handle_admin_orderby( $query ): void {

        $is_admin = is_admin() || (bool) apply_filters( 'bb_appointment_search_force', false );

        if ( ! $is_admin || ! ( $query instanceof \WP_Query )) {
            return;
        }

        if ( Appointment::POST_TYPE !== $query->get( 'post_type' )) {
            return;
        }

        $orderby = (string) $query->get( 'orderby' );

        $map = array(
            'bb_appt_when'   => AppointmentMeta::key( 'date' ),
            'bb_appt_status' => AppointmentMeta::key( 'status' ),
        );

        if ( ! isset( $map[ $orderby ] )) {
            return;
        }

        $query->set( 'meta_key', $map[ $orderby ] );
        $query->set( 'orderby', 'meta_value' );
    }

    /**
     * Print the small badge styles for the Appointments list screen.
     *
     * Kept inline (and gated to the appointment list screen only) so the
     * pack introduces no new admin stylesheet and no global asset overhead.
     * Colour-coded status/payment badges make triage faster.
     */
    public function print_list_styles(): void {

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        if ( ! $screen || Appointment::POST_TYPE !== $screen->post_type || 'edit' !== $screen->base ) {
            return;
        }

        ?>
        <style>
            .bb-appt-status, .bb-appt-payment {
                display: inline-block;
                padding: 2px 9px;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 600;
                line-height: 1.7;
                background: #f0f0f1;
                color: #3c434a;
            }
            .bb-appt-status-confirmed { background: #d7f0e0; color: #14623a; }
            .bb-appt-status-pending { background: #fdf3d3; color: #8a6100; }
            .bb-appt-status-completed { background: #dceefb; color: #0d5a8a; }
            .bb-appt-status-cancelled, .bb-appt-status-no_show { background: #fbe3e3; color: #8a1f1f; }
            .bb-appt-status-rescheduled { background: #ece2fb; color: #4b2a8a; }
            .bb-appt-payment-paid { background: #d7f0e0; color: #14623a; }
            .bb-appt-payment-pending, .bb-appt-payment-processing { background: #fdf3d3; color: #8a6100; }
            .bb-appt-payment-failed, .bb-appt-payment-cancelled { background: #fbe3e3; color: #8a1f1f; }
        </style>
        <?php
    }
}
