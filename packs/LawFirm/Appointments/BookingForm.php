<?php

namespace BusinessBuilderCore\Packs\LawFirm\Appointments;

use BusinessBuilderCore\Core\Notifications\NotificationManager;
use BusinessBuilderCore\Core\Notifications\Notification;
use BusinessBuilderCore\Core\Audit\AuditLog;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Frontend appointment booking handler.
 *
 * Mirrors the safe ConsultationForm pattern: posts to admin-post.php
 * (works for logged-out visitors), nonce + validation + sanitization,
 * server-side double-booking protection via Availability::create(), then
 * a notification + audit entry. Never trusts POST data.
 */
class BookingForm {

    /**
     * Form action.
     */
    private const ACTION = 'bb_book_appointment';

    /**
     * Nonce action / field.
     */
    private const NONCE_ACTION = 'bb_book_appointment';
    private const NONCE_FIELD = 'bb_appointment_nonce';

    /**
     * Notifications.
     */
    protected NotificationManager $notifications;

    /**
     * Audit.
     */
    protected AuditLog $audit;

    /**
     * Availability service.
     */
    protected Availability $availability;

    /**
     * Constructor.
     *
     * @param NotificationManager $notifications Notifications.
     * @param AuditLog            $audit         Audit.
     * @param Availability        $availability  Availability.
     */
    public function __construct(
        NotificationManager $notifications,
        AuditLog $audit,
        Availability $availability
    ) {
        $this->notifications = $notifications;
        $this->audit         = $audit;
        $this->availability  = $availability;
    }

    /**
     * Register hooks.
     */
    public function register(): void {

        add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
        add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );
    }

    /**
     * Handle a booking submission.
     */
    public function handle(): void {

        $redirect = wp_get_referer();

        if ( ! $redirect ) {
            $redirect = home_url( '/' );
        }

        if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
            $this->redirect( $redirect, 'error' );
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) );

        if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
            $this->redirect( $redirect, 'error' );
        }

        $data = array(
            'client_name'     => isset( $_POST['bb_client_name'] ) ? wp_unslash( $_POST['bb_client_name'] ) : '',
            'client_phone'    => isset( $_POST['bb_client_phone'] ) ? wp_unslash( $_POST['bb_client_phone'] ) : '',
            'client_email'    => isset( $_POST['bb_client_email'] ) ? wp_unslash( $_POST['bb_client_email'] ) : '',
            'lawyer_id'       => isset( $_POST['bb_lawyer_id'] ) ? absint( $_POST['bb_lawyer_id'] ) : 0,
            'practice_area'   => isset( $_POST['bb_practice_area'] ) ? wp_unslash( $_POST['bb_practice_area'] ) : '',
            'type'            => isset( $_POST['bb_type'] ) ? wp_unslash( $_POST['bb_type'] ) : 'consultation',
            'date'            => isset( $_POST['bb_date'] ) ? wp_unslash( $_POST['bb_date'] ) : '',
            'start'           => isset( $_POST['bb_start'] ) ? wp_unslash( $_POST['bb_start'] ) : '',
            'notes'           => isset( $_POST['bb_notes'] ) ? wp_unslash( $_POST['bb_notes'] ) : '',
            'consultation_id' => isset( $_POST['bb_consultation_id'] ) ? absint( $_POST['bb_consultation_id'] ) : 0,
        );

        if ( ! $this->valid( $data ) ) {
            $this->redirect( $redirect, 'error' );
        }

        /* Server-side slot validation + creation (double-booking safe). */
        $result = $this->availability->create( $data );

        if ( is_wp_error( $result ) ) {
            $this->redirect( $redirect, 'taken' );
        }

        $appointment_id = (int) $result;

        $this->audit->record(
            'appointment.created',
            'appointment',
            $appointment_id,
            array(
                'date'  => $data['date'],
                'start' => $data['start'],
            )
        );

        $this->notifications->dispatch(
            new Notification(
                'appointment.created',
                sprintf(
                    /* translators: 1: name, 2: date, 3: time */
                    __( 'New appointment request from %1$s for %2$s at %3$s', 'business-builder' ),
                    sanitize_text_field( (string) $data['client_name'] ),
                    $data['date'],
                    $data['start']
                ),
                '',
                '',
                $appointment_id,
                'appointment:' . $appointment_id . ':created'
            )
        );

        $this->redirect( $redirect, 'success' );
    }

    /**
     * Validate required fields.
     *
     * @param array $data Submitted data.
     * @return bool
     */
    protected function valid( array $data ): bool {

        return '' !== trim( (string) $data['client_name'] )
            && '' !== trim( (string) $data['date'] )
            && '' !== trim( (string) $data['start'] )
            && ( '' !== trim( (string) $data['client_phone'] ) || '' !== trim( (string) $data['client_email'] ) );
    }

    /**
     * Redirect back with a status flag.
     *
     * @param string $url    Redirect base.
     * @param string $status Status.
     */
    protected function redirect( string $url, string $status ): void {

        $url = add_query_arg(
            'bb_booking',
            $status,
            remove_query_arg( 'bb_booking', $url )
        );

        $url .= '#bb-booking-form';

        wp_safe_redirect( $url );
        exit;
    }

    /* ---- Static accessors for the template ---- */

    /**
     * Form action URL.
     *
     * @return string
     */
    public static function action_url(): string {
        return admin_url( 'admin-post.php' );
    }

    /**
     * Action name.
     *
     * @return string
     */
    public static function action_name(): string {
        return self::ACTION;
    }

    /**
     * Nonce field name.
     *
     * @return string
     */
    public static function nonce_field(): string {
        return self::NONCE_FIELD;
    }

    /**
     * Nonce action.
     *
     * @return string
     */
    public static function nonce_action(): string {
        return self::NONCE_ACTION;
    }
}
