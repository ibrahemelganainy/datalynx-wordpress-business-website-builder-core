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

        $endpoint = admin_url( 'admin-post.php' );

        $actions = array(
            'confirmed' => __( 'Confirm', 'business-builder' ),
            'completed' => __( 'Mark Completed', 'business-builder' ),
            'cancelled' => __( 'Cancel', 'business-builder' ),
        );

        foreach ( $actions as $slug => $label ) {

            if ( $slug === $status ) {
                continue;
            }

            echo '<form method="post" action="' . esc_url( $endpoint ) . '" style="display:inline-block;margin-right:8px;">';
            echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
            echo '<input type="hidden" name="appointment_id" value="' . esc_attr( (string) $id ) . '" />';
            echo '<input type="hidden" name="target_status" value="' . esc_attr( $slug ) . '" />';
            wp_nonce_field( self::NONCE_ACTION );
            echo '<button type="submit" class="button">' . esc_html( $label ) . '</button>';
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

        wp_safe_redirect( get_edit_post_link( $id, 'raw' ) );
        exit;
    }
}
