<?php

namespace BusinessBuilderCore\Packs\LawFirm\Appointments;

use BusinessBuilderCore\Core\Notifications\NotificationManager;
use BusinessBuilderCore\Core\Notifications\Notification;
use BusinessBuilderCore\Core\Audit\AuditLog;
use BusinessBuilderCore\Packs\LawFirm\Payments\PaymentFlow;
use BusinessBuilderCore\Packs\LawFirm\Payments\SectionPaymentFactory;
use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\Checkout\PaymentCheckout;

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

        /*
         * Resolve this section's payment configuration from its SAVED meta
         * (never from the browser). When payable, the appointment is created
         * as pending-payment and the customer is sent to the gateway; the
         * slot is still reserved because "pending" blocks re-booking.
         */
        $config  = $this->section_payment_config();
        $payable = is_array( $config ) && ! empty( $config['payable'] );

        if ( $payable ) {
            $data['payment_status'] = 'pending';
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

        if ( $payable ) {

            $this->apply_pending_payment_meta( $appointment_id, $config );

            $gateway = isset( $_POST['bb_payment_gateway'] )
                ? sanitize_key( wp_unslash( $_POST['bb_payment_gateway'] ))
                : '';

            if ( '' !== $gateway ) {

                $payment = $this->start_payment( $appointment_id, $config, $gateway, $data );

                $type = isset( $payment['type'] ) ? (string) $payment['type'] : '';

                if ( 'redirect' === $type && ! empty( $payment['url'] )) {
                    wp_redirect( (string) $payment['url'] );
                    exit;
                }

                if ( 'manual' === $type ) {
                    $this->redirect( $redirect, 'pending' );
                }

                $this->redirect( $redirect, 'payment_error' );
            }

            /* Payment required but no gateway chosen yet. */
            $this->redirect( $redirect, 'pending' );
        }

        $this->redirect( $redirect, 'success' );
    }

    /**
     * Resolve this submission's section payment configuration.
     *
     * The booking form posts a page id + section id; the fee, currency and
     * allowed gateways are re-read from the section's saved meta so the
     * browser can never influence them.
     *
     * @return array<string, mixed>|null
     */
    protected function section_payment_config(): ?array {

        $page_id = isset( $_POST['bb_page_id'] ) ? absint( wp_unslash( $_POST['bb_page_id'] )) : 0;

        $section_id = isset( $_POST['bb_section_id'] )
            ? sanitize_text_field( wp_unslash( $_POST['bb_section_id'] ))
            : '';

        if ( $page_id <= 0 || '' === $section_id ) {
            return null;
        }

        return SectionPaymentFactory::resolve_stored_section(
            $page_id,
            $section_id,
            'booking',
            'appointment'
        );
    }

    /**
     * Record the pending-payment state on an appointment.
     *
     * @param int                 $appointment_id Appointment id.
     * @param array<string,mixed> $config         Resolved section config.
     */
    protected function apply_pending_payment_meta( int $appointment_id, array $config ): void {

        update_post_meta( $appointment_id, AppointmentMeta::key( 'payment_required' ), '1' );
        update_post_meta( $appointment_id, AppointmentMeta::key( 'payment_amount' ), (string) $config['fee'] );
        update_post_meta( $appointment_id, AppointmentMeta::key( 'payment_currency' ), (string) $config['currency'] );
        update_post_meta( $appointment_id, AppointmentMeta::key( 'payment_status' ), 'pending' );
    }

    /**
     * Start the checkout for an appointment through the existing engine.
     *
     * @param int                 $appointment_id Appointment id.
     * @param array<string,mixed> $config         Resolved section config.
     * @param string              $gateway        Chosen gateway id.
     * @param array               $data           Submitted data.
     * @return array<string, mixed> Checkout result.
     */
    protected function start_payment( int $appointment_id, array $config, string $gateway, array $data ): array {

        $payments = new PaymentManager();

        $flow = new PaymentFlow(
            new PaymentCheckout( $payments, new AuditLog() ),
            $payments,
            new AuditLog()
        );

        $label = __( 'Appointment Booking', 'business-builder' );

        if ( ! empty( $data['type'] )) {
            /* translators: %s: appointment type label */
            $label = sprintf( __( 'Appointment Booking — %s', 'business-builder' ), AppointmentMeta::type_label( (string) $data['type'] ));
        }

        return $flow->start(
            'appointment',
            $appointment_id,
            $config,
            $gateway,
            $label,
            isset( $data['client_email'] ) ? (string) $data['client_email'] : ''
        );
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
