<?php

namespace BusinessBuilderCore\Packs\LawFirm\Frontend;

use BusinessBuilderCore\Packs\LawFirm\Sections\LawFirmQueries;

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

        $this->notify( $data );

        $this->redirect_with( $redirect, 'success' );
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

        $data['practice_area'] = isset( $_POST['bb_practice_area'] )
            ? sanitize_title( wp_unslash( $_POST['bb_practice_area'] ) )
            : '';

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

        $map = array(
            '_bb_consultation_name'              => $data['name'],
            '_bb_consultation_phone'             => $data['phone'],
            '_bb_consultation_email'             => $data['email'],
            '_bb_consultation_practice_area'     => $data['practice_area'],
            '_bb_consultation_message'           => $data['message'],
            '_bb_consultation_preferred_contact' => $data['preferred_contact'],
            '_bb_consultation_created'           => current_time( 'mysql' ),
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
