<?php

namespace BusinessBuilderCore\Packs\LawFirm\Frontend;

use BusinessBuilderCore\Packs\LawFirm\Sections\LawFirmQueries;
use BusinessBuilderCore\Packs\LawFirm\PostTypes\ConsultationMeta;
use BusinessBuilderCore\Packs\LawFirm\Payments\PaymentFlow;
use BusinessBuilderCore\Packs\LawFirm\Payments\SectionPaymentFactory;
use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\Checkout\PaymentCheckout;
use BusinessBuilderCore\Core\Audit\AuditLog;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * LawFirm consultation form.
 *
 * Self-contained request handler for the "Legal Consultation
 * Request form (spec 19). There is no Core Forms service in this
 * codebase yet, so the pack owns its form processing here; the
 * class is written so its validate/store/notify steps can later be
 * delegated to a Core Forms service without changing callers.
 *
 * Flow:
 *   1. Form posts to admin-post.php with action=bb_consultation.
 *   2. Handler verifies nonce + validates + sanitizes fields.
 *   3. Valid submissions are stored as a bb_consultation post and
 *      emailed to the site admin.
 *   4. The visitor is redirected back with a status flag
 *      (bb_consult=success|error), never with raw input.
 *
 * Works for logged-out visitors (admin_post_nopriv_*).
 */
class ConsultationForm {

    /**
     * Form action name.
     */
    private const ACTION = 'bb_consultation';

    /**
     * Nonce action.
     */
    private const NONCE_ACTION = 'bb_consultation_submit';

    /**
     * Nonce field name.
     */
    private const NONCE_FIELD = 'bb_consultation_nonce';

    /**
     * Submitted field names (without prefix).
     *
     * @var string[]
     */
    private const FIELDS = array(
        'name',
        'phone',
        'email',
        'practice_area',
        'message',
        'preferred_contact',
    );

    /**
     * Register hooks.
     */
    public function register(): void {

        add_action(
            'admin_post_' . self::ACTION,
            array( $this, 'handle' )
        );

        add_action(
            'admin_post_nopriv_' . self::ACTION,
            array( $this, 'handle' )
        );
    }

    /**
     * Handle a submitted consultation request.
     */
    public function handle(): void {

        $redirect = $this->get_redirect_url();

        if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
            $this->redirect_with( $redirect, 'error' );
        }

        $nonce = sanitize_text_field(
            wp_unslash( $_POST[ self::NONCE_FIELD ] )
        );

        if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
            $this->redirect_with( $redirect, 'error' );
        }

        $data = $this->collect();
        $errors = $this->validate( $data );

        if ( ! empty( $errors ) ) {
            $this->redirect_with( $redirect, 'error' );
        }

        $post_id = $this->store( $data );

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            $this->redirect_with( $redirect, 'error' );
        }

        $post_id = (int) $post_id;

        /*
         * Payment gate: when the owning section requires payment, the
         * request is NOT confirmed yet. We resolve the section config from
         * its SAVED meta (never from the browser), start the checkout and
         * send the customer to the gateway. Only a server-verified
         * callback/webhook later marks it paid.
         */
        $config = $this->section_payment_config();

        if ( is_array( $config ) && ! empty( $config['payable'] )) {

            $this->apply_pending_payment_meta( $post_id, $config );

            $gateway = isset( $_POST['bb_payment_gateway'] )
                ? sanitize_key( wp_unslash( $_POST['bb_payment_gateway'] ))
                : '';

            if ( '' !== $gateway ) {

                $result = $this->start_payment( $post_id, $config, $gateway, $data );

                $type = isset( $result['type'] ) ? (string) $result['type'] : '';

                if ( 'redirect' === $type && ! empty( $result['url'] )) {
                    wp_redirect( (string) $result['url'] );
                    exit;
                }

                if ( 'manual' === $type ) {
                    $this->notify( $data );
                    $this->redirect_with( $redirect, 'pending' );
                }

                /* Gateway unavailable: keep the pending request payable. */
                $this->redirect_with( $redirect, 'payment_error' );
            }

            /* Payment required but no gateway chosen yet: show the pay step. */
            $this->redirect_with( $redirect, 'pending' );
        }

        $this->notify( $data );

        $this->redirect_with( $redirect, 'success' );
    }

    /**
     * Resolve this submission's section payment configuration.
     *
     * The form posts a page id + section id (both opaque to the client);
     * the fee, currency and allowed gateways are re-read from the section's
     * saved meta so the browser can never influence them.
     *
     * @return array<string, mixed>|null
     */
    private function section_payment_config(): ?array {

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
            'consultation',
            'consultation'
        );
    }

    /**
     * Record the pending-payment state on a consultation.
     *
     * @param int                 $post_id Consultation id.
     * @param array<string,mixed> $config  Resolved section config.
     */
    private function apply_pending_payment_meta( int $post_id, array $config ): void {

        update_post_meta( $post_id, ConsultationMeta::key( 'payment_required' ), '1' );
        update_post_meta( $post_id, ConsultationMeta::key( 'payment_amount' ), (string) $config['fee'] );
        update_post_meta( $post_id, ConsultationMeta::key( 'payment_currency' ), (string) $config['currency'] );
        update_post_meta( $post_id, ConsultationMeta::key( 'payment_status' ), 'pending' );

        /*
         * A payment-pending request is not a confirmed consultation yet:
         * keep it in the pending request state until payment is verified.
         */
        update_post_meta( $post_id, ConsultationMeta::key( 'status' ), 'pending' );
    }

    /**
     * Start the checkout for a consultation through the existing engine.
     *
     * @param int                 $post_id Consultation id.
     * @param array<string,mixed> $config  Resolved section config.
     * @param string              $gateway Chosen gateway id.
     * @param array               $data    Submitted data (for label/email).
     * @return array<string, mixed> Checkout result.
     */
    private function start_payment( int $post_id, array $config, string $gateway, array $data ): array {

        $payments = new PaymentManager();

        $flow = new PaymentFlow(
            new PaymentCheckout( $payments, new AuditLog() ),
            $payments,
            new AuditLog()
        );

        $label = __( 'Legal Consultation', 'business-builder' );

        if ( ! empty( $data['practice_area'] )) {
            /* translators: %s: practice area name */
            $label = sprintf( __( 'Legal Consultation — %s', 'business-builder' ), (string) $data['practice_area'] );
        }

        return $flow->start(
            'consultation',
            $post_id,
            $config,
            $gateway,
            $label,
            isset( $data['email'] ) ? (string) $data['email'] : ''
        );
    }

    /**
     * Collect and sanitize submitted fields.
     *
     * @return array
     */
    private function collect(): array {

        $data = array();

        $data['name'] = isset( $_POST['bb_name'] )
            ? sanitize_text_field( wp_unslash( $_POST['bb_name'] ) )
            : '';

        $data['phone'] = isset( $_POST['bb_phone'] )
            ? sanitize_text_field( wp_unslash( $_POST['bb_phone'] ) )
            : '';

        $data['email'] = isset( $_POST['bb_email'] )
            ? sanitize_email( wp_unslash( $_POST['bb_email'] ) )
            : '';

        /*
         * Practice area: the form posts a term ID (preferred) but older
         * markup posted a slug/name. Normalize to the matching term so
         * we store the canonical term ID and never a raw/encoded slug
         * (bug fix + spec 26). A readable name is also stored for
         * backward compatibility, never an encoded slug.
         */
        $posted_area = isset( $_POST['bb_practice_area'] )
            ? wp_unslash( $_POST['bb_practice_area'] )
            : '';

        $raw_area = sanitize_text_field( $posted_area );

        $area_term = '' !== $raw_area
            ? ConsultationMeta::normalize_submitted_practice_area( $raw_area )
            : null;

        $data['practice_area_id'] = $area_term instanceof \WP_Term
            ? (int) $area_term->term_id
            : 0;

        $data['practice_area'] = $area_term instanceof \WP_Term
            ? (string) $area_term->name
            : $raw_area;

        $data['message'] = isset( $_POST['bb_message'] )
            ? sanitize_textarea_field( wp_unslash( $_POST['bb_message'] ) )
            : '';

        $data['preferred_contact'] = isset( $_POST['bb_preferred_contact'] )
            ? sanitize_key( wp_unslash( $_POST['bb_preferred_contact'] ) )
            : '';

        return $data;
    }

    /**
     * Validate collected data.
     *
     * @param array $data Submitted data.
     * @return string[] Validation error messages.
     */
    private function validate( array $data ): array {

        $errors = array();

        if ( '' === $data['name'] ) {
            $errors[] = __( 'Name is required.', 'business-builder' );
        }

        if ( '' === $data['message'] ) {
            $errors[] = __( 'Message is required.', 'business-builder' );
        }

        if ( '' === $data['phone'] && '' === $data['email'] ) {
            $errors[] = __( 'Please provide a phone number or an email address.', 'business-builder' );
        }

        if ( '' !== $data['email'] && ! is_email( $data['email'] ) ) {
            $errors[] = __( 'Please provide a valid email address.', 'business-builder' );
        }

        /*
         * Reject submissions that look like bot spam without adding
         * a visible captcha (spec 24 never trust POST input).
         */
        if ( '' !== $data['message'] && preg_match( '/https?:\/\/|\[url=|<a\s/i', $data['message'] ) ) {
            $errors[] = __( 'Links are not allowed in the message.', 'business-builder' );
        }

        return $errors;
    }

    /**
     * Store the request as a private consultation post.
     *
     * @param array $data Sanitized data.
     * @return int|\WP_Error Post ID or error.
     */
    private function store( array $data ) {

        $title = $data['name'];

        if ( '' !== $data['practice_area'] ) {
            $title .= ' - ' . $data['practice_area'];
        }

        $post_id = wp_insert_post(
            array(
                'post_type'   => 'bb_consultation',
                'post_status' => 'publish',
                'post_title'  => $title,
            ),
            true
        );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        /*
         * Always issue a secure, non-sequential public reference so the
         * customer can look the request up without exposing an
         * enumerable post id (privacy).
         */
        $public_ref = ConsultationMeta::generate_public_reference();

        $map = array(
            '_bb_consultation_name'               => $data['name'],
            '_bb_consultation_phone'              => $data['phone'],
            '_bb_consultation_email'              => $data['email'],
            '_bb_consultation_practice_area'      => $data['practice_area'],
            '_bb_consultation_practice_area_id'   => isset( $data['practice_area_id'] ) ? absint( $data['practice_area_id'] ) : 0,
            '_bb_consultation_message'            => $data['message'],
            '_bb_consultation_preferred_contact'  => $data['preferred_contact'],
            '_bb_consultation_public_reference'   => $public_ref,
            '_bb_consultation_status'             => ConsultationMeta::default_status(),
            '_bb_consultation_payment_status'     => 'not_required',
            '_bb_consultation_created'            => current_time( 'mysql' ),
        );

        foreach ( $map as $meta_key => $value ) {
            update_post_meta( $post_id, $meta_key, $value );
        }

        return $post_id;
    }

    /**
     * Notify the site administrator by email.
     *
     * @param array $data Sanitized data.
     */
    private function notify( array $data ): void {

        $to = get_option( 'admin_email' );

        if ( ! is_email( $to ) ) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: business or site name */
            __( '[%s] New legal consultation request', 'business-builder' ),
            wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
        );

        $lines = array(
            __( 'A new consultation request was submitted.', 'business-builder' ),
            '',
            __( 'Name', 'business-builder' ) . ': ' . $data['name'],
            __( 'Phone', 'business-builder' ) . ': ' . $data['phone'],
            __( 'Email', 'business-builder' ) . ': ' . $data['email'],
            __( 'Practice Area', 'business-builder' ) . ': ' . $data['practice_area'],
            __( 'Preferred Contact', 'business-builder' ) . ': ' . $data['preferred_contact'],
            '',
            __( 'Message', 'business-builder' ) . ':',
            $data['message'],
        );

        $body = implode( "\n", $lines );

        wp_mail( $to, $subject, $body );
    }

    /**
     * Determine a safe redirect target back to the form.
     *
     * Uses the referer host only (never the full attacker-supplied
     * URL), preventing open-redirect abuse.
     *
     * @return string
     */
    private function get_redirect_url(): string {

        $referer = wp_get_referer();

        if ( $referer ) {
            return $referer;
        }

        return home_url( '/' );
    }

    /**
     * Redirect back to the form with a status flag.
     *
     * @param string $url    Redirect base URL.
     * @param string $status 'success' or 'error'.
     */
    private function redirect_with( string $url, string $status ): void {

        $url = add_query_arg(
            'bb_consult',
            $status,
            remove_query_arg( 'bb_consult', $url )
        );

        $url .= '#bb-consultation-form';

        wp_safe_redirect( $url );
        exit;
    }

    /**
     * Form action URL for the template.
     *
     * @return string
     */
    public static function action_url(): string {

        return admin_url( 'admin-post.php' );
    }

    /**
     * The hidden action field value.
     *
     * @return string
     */
    public static function action_name(): string {

        return self::ACTION;
    }

    /**
     * The nonce field name.
     *
     * @return string
     */
    public static function nonce_field(): string {

        return self::NONCE_FIELD;
    }

    /**
     * The nonce action.
     *
     * @return string
     */
    public static function nonce_action(): string {

        return self::NONCE_ACTION;
    }

    /**
     * Get selectable practice areas for the form.
     *
     * @return \WP_Term[]
     */
    public static function practice_areas(): array {

        $terms = get_terms(
            array(
                'taxonomy'   => LawFirmQueries::PRACTICE_AREA_TAX,
                'hide_empty' => false,
            )
        );

        if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
            return array();
        }

        return $terms;
    }
}
