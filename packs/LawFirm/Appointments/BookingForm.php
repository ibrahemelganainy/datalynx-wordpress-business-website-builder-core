<?php

namespace BusinessBuilderCore\Packs\LawFirm\Appointments;

use BusinessBuilderCore\Core\Notifications\NotificationManager;
use BusinessBuilderCore\Core\Notifications\Notification;
use BusinessBuilderCore\Core\Audit\AuditLog;
use BusinessBuilderCore\Packs\LawFirm\Payments\PaymentFlow;
use BusinessBuilderCore\Packs\LawFirm\Payments\SectionPaymentFactory;
use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\Checkout\PaymentCheckout;
use BusinessBuilderCore\Packs\LawFirm\Frontend\BillingPage;

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

        /*
         * Enforce THIS section's availability schedule (days, hours, slot
         * length, per-day cap), re-read from the section's saved meta so the
         * browser can never widen the window. When the section cannot be
         * resolved the service falls back to the site defaults.
         */
        $availability_svc = $this->availability;
        $section_avail    = AvailabilityFactory::resolve_stored_section(
            $this->posted_page_id(),
            $this->posted_section_id()
        );

        if ( null !== $section_avail ) {
            $availability_svc = $this->availability->with_config( $section_avail );
        }

        /* Server-side slot validation + creation (double-booking safe). */
        $result = $availability_svc->create( $data );

        if ( is_wp_error( $result ) ) {

            /*
             * Map each validation failure to an ACCURATE status flag. The
             * old code collapsed every error into "taken", so a past date,
             * an off-hours time or a weekend wrongly told the customer the
             * slot was taken. Only a genuine clash is reported as "taken".
             */
            $code = $result->get_error_code();

            $map = array(
                'bb_slot_taken'        => 'taken',
                'bb_day_full'          => 'day_full',
                'bb_duplicate_booking' => 'duplicate',
                'bb_invalid_date'      => 'invalid_date',
                'bb_invalid_time'      => 'invalid_time',
                'bb_past_date'         => 'past_date',
                'bb_outside_hours'     => 'outside_hours',
                'bb_non_working_day'   => 'closed',
                'bb_blocked_date'      => 'closed',
            );

            $flag = isset( $map[ $code ] ) ? $map[ $code ] : 'error';

            /*
             * Carry the attempted date so the form can compute and show the
             * NEXT available slot for the customer, instead of leaving them
             * to guess which days/times are free.
             */
            $this->redirect( $redirect, $flag, '', '', '', (string) $data['date'] );
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

        $appt_ref = (string) get_post_meta( $appointment_id, AppointmentMeta::key( 'public_reference' ), true );

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
                'appointment:' . $appointment_id . ':created',
                array(
                    'category'    => 'appointment',
                    'entity_type' => 'appointment',
                    'entity_id'   => $appointment_id,
                    'reference'   => $appt_ref,
                    'customer'    => sanitize_text_field( (string) $data['client_name'] ),
                )
            )
        );

        if ( $payable ) {

            $this->apply_pending_payment_meta( $appointment_id, $config );

            $gateway = isset( $_POST['bb_payment_gateway'] )
                ? sanitize_key( wp_unslash( $_POST['bb_payment_gateway'] ))
                : '';

            if ( '' !== $gateway ) {

                /*
                 * Gateways that require billing details (Paymob) are shown
                 * on a SEPARATE payment step page. On the first submit we
                 * redirect there; only the billing-step submit starts the
                 * gateway. All other gateways keep the direct flow.
                 */
                $billing_step = isset( $_POST['bb_pay_step'] )
                    ? sanitize_key( wp_unslash( $_POST['bb_pay_step'] ))
                    : '';

                if ( in_array( $gateway, self::billing_gateways(), true ) && 'billing' !== $billing_step ) {
                    $this->redirect_to_payment_step( $appointment_id, $gateway, $config );
                }

                $payment = $this->start_payment( $appointment_id, $config, $gateway, $data );

                $type = isset( $payment['type'] ) ? (string) $payment['type'] : '';

                if ( 'redirect' === $type && ! empty( $payment['url'] )) {
                    wp_redirect( (string) $payment['url'] );
                    exit;
                }

                if ( 'manual' === $type || 'reference' === $type ) {

                    /*
                     * Manual gateway: record the transaction reference (and
                     * optional receipt) and hold the payment for admin
                     * verification. Never marked paid here.
                     */
                    if ( 'manual' === $type ) {
                        $manual_submitted = \BusinessBuilderCore\Packs\LawFirm\Payments\ManualPaymentSubmission::submit(
                            'appointment',
                            $appointment_id,
                            $gateway,
                            $_POST,
                            isset( $payment['transaction'] ) && $payment['transaction'] instanceof \BusinessBuilderCore\Core\Payments\PaymentTransaction ? $payment['transaction'] : null
                        );

                        if ( ! $manual_submitted ) {
                            $this->redirect(
                                $redirect,
                                'payment_error',
                                '',
                                __( 'We could not save your payment submission. Please try again.', 'business-builder' ),
                                'manual_submission_failed'
                            );
                        }
                    }

                    $pending_ref = '';

                    if ( isset( $payment['transaction'] ) && $payment['transaction'] instanceof \BusinessBuilderCore\Core\Payments\PaymentTransaction ) {
                        $pending_ref = (string) $payment['transaction']->public_ref;
                    }

                    $this->redirect( $redirect, 'pending', $pending_ref );
                }

                /* Log the structured code; keep the request payable. */
                $this->log_payment_failure( $gateway, $payment );

                $this->redirect(
                    $redirect,
                    'payment_error',
                    '',
                    $this->user_failure_reason( $gateway, $payment ),
                    isset( $payment['code'] ) ? (string) $payment['code'] : ''
                );
            }

            /* Payment required but no gateway chosen yet. */
            $this->redirect( $redirect, 'pending' );
        }

        /*
         * Free service: redirect with the OBJECT reference so the form can
         * show a full invoice (clearly marked "Free") for the customer's
         * records, exactly like a paid request shows its receipt.
         */
        $this->redirect( $redirect, 'success', $appt_ref );
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

        $billing = $this->collect_billing( $gateway, $data );

        /*
         * Always record the originating page path so the gateway callback
         * can return the customer to the SAME page (permalink-agnostic).
         */
        $billing['origin'] = $this->origin_path();

        return $flow->start(
            'appointment',
            $appointment_id,
            $config,
            $gateway,
            $label,
            isset( $billing['email'] ) && '' !== $billing['email']
                ? (string) $billing['email']
                : ( isset( $data['client_email'] ) ? (string) $data['client_email'] : '' ),
            $billing
        );
    }

    /**
     * The local path of the page the form was submitted from.
     *
     * @return string
     */
    protected function origin_path(): string {

        $referer = wp_get_referer();

        if ( ! $referer ) {
            return '/';
        }

        $path = (string) wp_parse_url( $referer, PHP_URL_PATH );

        return '' !== $path ? $path : '/';
    }

    /**
     * Redirect the customer to the separate payment step page.
     *
     * @param int    $appointment_id Appointment id.
     * @param string $gateway        Chosen gateway id.
     */
    protected function redirect_to_payment_step( int $appointment_id, string $gateway, array $config = array() ): void {

        $ref = (string) get_post_meta( $appointment_id, AppointmentMeta::key( 'public_reference' ), true );

        /*
         * Snapshot the resolved payment context on the pending appointment
         * and mint a one-time token for the dedicated billing page.
         */
        $context = array(
            'gateway'  => sanitize_key( $gateway ),
            'fee'      => isset( $config['fee'] ) ? (string) $config['fee'] : '',
            'currency' => isset( $config['currency'] ) ? (string) $config['currency'] : '',
            'gateways' => isset( $config['gateways'] ) && is_array( $config['gateways'] ) ? $config['gateways'] : array(),
        );

        $token = BillingPage::store_pending( 'appointment', $appointment_id, $context );

        $url = BillingPage::url( $ref, $token );

        wp_safe_redirect( $url );
        exit;
    }

    /**
     * The page id posted by the booking form.
     *
     * @return int
     */
    protected function posted_page_id(): int {

        return isset( $_POST['bb_page_id'] ) ? absint( wp_unslash( $_POST['bb_page_id'] )) : 0;
    }

    /**
     * The section id posted by the booking form.
     *
     * @return string
     */
    protected function posted_section_id(): string {

        return isset( $_POST['bb_section_id'] )
            ? sanitize_text_field( wp_unslash( $_POST['bb_section_id'] ))
            : '';
    }

    /**
     * Collect billing details for a gateway that requires them.
     *
     * Returns an empty array for every gateway other than the ones that
     * declare a billing requirement (Paymob), so non-billing gateways are
     * completely unaffected. Values are re-sanitized here.
     *
     * @param string $gateway Chosen gateway id.
     * @param array  $data    Already-collected form data (fallback source).
     * @return array<string, string>
     */
    protected function collect_billing( string $gateway, array $data ): array {

        if ( ! in_array( $gateway, self::billing_gateways(), true )) {
            return array();
        }

        $first = isset( $_POST['bb_billing_first_name'] )
            ? sanitize_text_field( wp_unslash( $_POST['bb_billing_first_name'] ))
            : '';

        $last = isset( $_POST['bb_billing_last_name'] )
            ? sanitize_text_field( wp_unslash( $_POST['bb_billing_last_name'] ))
            : '';

        $email = isset( $_POST['bb_billing_email'] )
            ? sanitize_email( wp_unslash( $_POST['bb_billing_email'] ))
            : '';

        $phone = isset( $_POST['bb_billing_phone'] )
            ? sanitize_text_field( wp_unslash( $_POST['bb_billing_phone'] ))
            : '';

        /* Fall back to the main booking fields when the billing block was blank. */
        if ( '' === $first && '' === $last && ! empty( $data['client_name'] )) {
            $first = (string) $data['client_name'];
        }

        if ( '' === $email && ! empty( $data['client_email'] )) {
            $email = (string) $data['client_email'];
        }

        if ( '' === $phone && ! empty( $data['client_phone'] )) {
            $phone = (string) $data['client_phone'];
        }

        return array(
            'first_name' => $first,
            'last_name'  => $last,
            'name'       => trim( $first . ' ' . $last ),
            'email'      => $email,
            'phone'      => $phone,
            'country'    => 'EG',
        );
    }

    /**
     * Gateway ids that require a billing_data block.
     *
     * @return string[]
     */
    protected static function billing_gateways(): array {

        /*
         * Derive the set from each gateway's own contract instead of
         * hard-coding "paymob", so the billing step stays gateway-
         * independent and any future billing gateway is handled the same
         * way. Every other gateway keeps its direct flow.
         */
        $ids = array();

        $manager = new PaymentManager();

        foreach ( $manager->gateways() as $id => $gateway ) {

            if ( $gateway->needs_billing() ) {
                $ids[] = sanitize_key( (string) $id );
            }
        }

        /**
         * Filter the gateways that require billing details.
         *
         * @param string[] $ids Gateway ids.
         */
        return apply_filters( 'bb_payment_billing_gateways', $ids );
    }

    /**
     * Validate required fields.
     *
     * @param array $data Submitted data.
     * @return bool
     */
    protected function valid( array $data ): bool {

        /*
         * A phone number is REQUIRED: it is the same-day duplicate guard's
         * verification method, so a booking without one cannot be checked
         * against an existing appointment for the same day. An email stays
         * optional.
         */
        return '' !== trim( (string) $data['client_name'] )
            && '' !== trim( (string) $data['date'] )
            && '' !== trim( (string) $data['start'] )
            && '' !== trim( (string) $data['client_phone'] );
    }

    /**
     * Redirect back with a status flag.
     *
     * @param string $url    Redirect base.
     * @param string $status Status.
     */
    protected function redirect( string $url, string $status, string $reference = '', string $reason = '', string $code = '', string $try_date = '' ): void {

        $url = add_query_arg(
            'bb_booking',
            $status,
            remove_query_arg( 'bb_booking', $url )
        );

        if ( '' !== $reference ) {
            $url = add_query_arg( 'bb_ref', sanitize_text_field( $reference ), $url );
        }

        /* Carry the attempted date so the form can suggest the next slot. */
        if ( '' !== $try_date ) {
            $url = add_query_arg( 'bb_try_date', sanitize_text_field( $try_date ), $url );
        }

        /* Carry the real, user-safe failure reason so the form explains it. */
        if ( '' !== $reason ) {
            $url = add_query_arg( 'bb_pay_reason', sanitize_text_field( $reason ), $url );
        }

        if ( '' !== $code ) {
            $url = add_query_arg( 'bb_pay_code', sanitize_key( $code ), $url );
        }

        $url .= '#bb-booking-form';

        wp_safe_redirect( $url );
        exit;
    }

    /**
     * Log a structured payment-start failure for the administrator.
     *
     * Writes the structured code + gateway to the debug log ONLY when
     * WP_DEBUG_LOG is on, and to the audit trail otherwise. No secrets and
     * no customer PII are recorded.
     *
     * @param string $gateway Chosen gateway id.
     * @param array  $result  Checkout result.
     */
    protected function user_failure_reason( string $gateway, array $result ): string {

        $code = isset( $result['code'] ) ? sanitize_key( (string) $result['code'] ) : '';

        $gateway_obj = ( new \BusinessBuilderCore\Core\Payments\PaymentManager() )->gateway( $gateway );
        $name        = $gateway_obj instanceof \BusinessBuilderCore\Core\Payments\PaymentGatewayInterface
            ? $gateway_obj->get_name()
            : $gateway;

        $messages = array(
            'gateway_disabled'        => __( 'This payment method is currently disabled. Please choose another, or contact us.', 'business-builder' ),
            'gateway_not_configured'  => __( 'This payment method is not set up yet. Please choose another, or contact us so we can help.', 'business-builder' ),
            'invalid_amount'          => __( 'The amount is not valid. Please contact us so we can correct it.', 'business-builder' ),
            'invalid_currency'        => __( 'The selected payment method does not support this site currency. Please choose another method, or contact us.', 'business-builder' ),
            'transport_unavailable'   => __( 'Our server could not reach the payment provider. Please try again shortly, or choose another method.', 'business-builder' ),
            'ssl_verification_failed' => __( 'Our server could not securely reach the payment provider. Please try again shortly, or choose another method.', 'business-builder' ),
            'redirect_missing'        => __( 'The payment provider did not return a checkout link. Please try again, or choose another method.', 'business-builder' ),
        );

        if ( isset( $messages[ $code ] )) {
            return $messages[ $code ];
        }

        $provider = isset( $result['message'] ) ? (string) $result['message'] : '';

        if ( '' !== $provider ) {
            /* translators: 1: gateway name, 2: provider reason */
            return sprintf( __( '%1$s could not be started: %2$s', 'business-builder' ), $name, $provider );
        }

        /* translators: %s: gateway name */
        return sprintf( __( '%s could not be started. Please try another payment method or contact us.', 'business-builder' ), $name );
    }

    /**
     * Log a structured payment-start failure for the administrator.
     *
     * @param string $gateway Chosen gateway id.
     * @param array  $result  Checkout result.
     */
    protected function log_payment_failure( string $gateway, array $result ): void {

        $code    = isset( $result['code'] ) ? sanitize_key( (string) $result['code'] ) : 'gateway_error';
        $message = isset( $result['message'] ) ? (string) $result['message'] : '';

        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log(
                '[bb-payment checkout] gateway=' . sanitize_key( $gateway )
                . ' code=' . $code
                . ' reason=' . sanitize_text_field( $message )
            );
        }

        $this->audit->record(
            'payment.checkout_failed',
            'payment',
            0,
            array( 'gateway' => sanitize_key( $gateway ), 'code' => $code )
        );
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
