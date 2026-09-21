<?php

namespace BusinessBuilderCore\Packs\LawFirm\Frontend;

use BusinessBuilderCore\Packs\LawFirm\PostTypes\ConsultationMeta;
use BusinessBuilderCore\Packs\LawFirm\Appointments\AppointmentMeta;
use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;

if ( ! defined( 'ABSPATH' ))  {
    exit;
}

/**
 * Public "Consultation & Appointment Lookup" handler.
 *
 * A customer proves ownership with TWO factors — the public reference AND
 * the phone number on the record — before any data is returned. This
 * prevents enumeration: a correct reference with a wrong phone returns the
 * SAME generic "not verified" message as a non-existent reference.
 *
 * The endpoint is public (admin-ajax nopriv) but:
 *   - nonce-protected,
 *   - rate-limited per visitor,
 *   - returns ONLY the minimal, non-sensitive fields,
 *   - never exposes post ids, internal notes, or gateway credentials.
 */
class LookupHandler {

    /**
     * AJAX action.
     */
    public const ACTION = 'bb_status_lookup';

    /**
     * Nonce action.
     */
    public const NONCE_ACTION = 'bb_status_lookup';

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
     * Register hooks.
     */
    public function register(): void {

        add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'handle' ) );
        add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle' ) );
    }

    /**
     * The nonce string for the frontend.
     *
     * @return string
     */
    public static function nonce(): string {

        return wp_create_nonce( self::NONCE_ACTION );
    }

    /**
     * Handle a lookup request.
     */
    public function handle(): void {

        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( $this->is_rate_limited() ) {
            wp_send_json_error( array(
                'message' => __( 'Too many attempts. Please wait a moment and try again.', 'business-builder' ),
            ), 429 );
        }

        $this->bump_rate_limit();

        $type      = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
        $reference = isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : '';
        $phone     = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

        $reference = strtoupper( trim( $reference ) );

        if ( '' === $reference ) {
            wp_send_json_error( array( 'message' => __( 'Please enter your reference number.', 'business-builder' ) ), 400 );
        }

        if ( '' === $phone ) {
            wp_send_json_error( array( 'message' => __( 'Please enter your phone number.', 'business-builder' ) ), 400 );
        }

        /* Must match the expected reference shape for the chosen type. */
        if ( ! $this->reference_matches_type( $type, $reference ))  {
            wp_send_json_error( array( 'message' => $this->generic_message() ), 404 );
        }

        $data = $this->lookup( $type, $reference, $phone );

        if ( null === $data ) {
            wp_send_json_error( array( 'message' => $this->generic_message() ), 404 );
        }

        wp_send_json_success( $data );
    }

    /**
     * The single, uniform message for any failed verification.
     *
     * @return string
     */
    protected function generic_message(): string {

        return __( 'The provided reference and phone number could not be verified.', 'business-builder' );
    }

    /**
     * Whether a reference has the shape expected for the chosen type.
     *
     * @param string $type      'consultation'|'appointment'.
     * @param string $reference Reference.
     * @return bool
     */
    protected function reference_matches_type( string $type, string $reference ): bool {

        $prefix = ( 'appointment' === $type ) ? 'APT-' : 'CNS-';

        return 0 === strpos( $reference, $prefix );
    }

    /**
     * Verify reference + phone and build the safe summary.
     *
     * @param string $type      Type.
     * @param string $reference Reference.
     * @param string $phone     Phone.
     * @return array<string, mixed>|null
     */
    protected function lookup( string $type, string $reference, string $phone ): ?array {

        if ( 'appointment' === $type ) {
            return $this->lookup_appointment( $reference, $phone );
        }

        return $this->lookup_consultation( $reference, $phone );
    }

    /**
     * Consultation lookup.
     *
     * @param string $reference Reference.
     * @param string $phone     Phone.
     * @return array<string, mixed>|null
     */
    protected function lookup_consultation( string $reference, string $phone ): ?array {

        $post_id = $this->find_post( 'bb_consultation', '_bb_consultation_public_reference', $reference );

        if ( $post_id <= 0 ) {
            return null;
        }

        $stored_phone = (string) get_post_meta( $post_id, ConsultationMeta::key( 'phone' ), true );

        if ( ! self::phone_matches( $stored_phone, $phone ))  {
            return null;
        }

        $status = (string) get_post_meta( $post_id, ConsultationMeta::key( 'status' ), true );
        $status = '' !== $status ? $status : ConsultationMeta::default_status();

        $practice_area = ConsultationMeta::practice_area_name( $post_id );

        return array(
            'type'       => 'consultation',
            'reference'  => $reference,
            'customer'   => (string) get_post_meta( $post_id, ConsultationMeta::key( 'name' ), true ),
            'status'     => ConsultationMeta::status_label( $status ),
            'status_key' => $status,
            'practice_area' => $practice_area,
            'submitted'  => (string) get_post_meta( $post_id, ConsultationMeta::key( 'created' ), true ),
            'payment'    => $this->payment_block( 'consultation', $post_id, (string) get_post_meta( $post_id, ConsultationMeta::key( 'payment_status' ), true ) ),
            'timeline'   => $this->consultation_timeline( $status ),
        );
    }

    /**
     * Appointment lookup.
     *
     * @param string $reference Reference.
     * @param string $phone     Phone.
     * @return array<string, mixed>|null
     */
    protected function lookup_appointment( string $reference, string $phone ): ?array {

        $post_id = $this->find_post( 'bb_appointment', '_bb_appointment_public_reference', $reference );

        if ( $post_id <= 0 ) {
            return null;
        }

        $stored_phone = (string) get_post_meta( $post_id, AppointmentMeta::key( 'client_phone' ), true );

        if ( ! self::phone_matches( $stored_phone, $phone ))  {
            return null;
        }

        $status = (string) get_post_meta( $post_id, AppointmentMeta::key( 'status' ), true );
        $status = '' !== $status ? $status : AppointmentMeta::default_status();

        $date  = (string) get_post_meta( $post_id, AppointmentMeta::key( 'date' ), true );
        $start = (string) get_post_meta( $post_id, AppointmentMeta::key( 'start' ), true );

        return array(
            'type'        => 'appointment',
            'reference'   => $reference,
            'customer'    => (string) get_post_meta( $post_id, AppointmentMeta::key( 'client_name' ), true ),
            'status'      => AppointmentMeta::status_label( $status ),
            'status_key'  => $status,
            'date'        => $date,
            'time'        => trim( $start ),
            'submitted'   => (string) get_post_meta( $post_id, AppointmentMeta::key( 'created' ), true ),
            'payment'     => $this->payment_block( 'appointment', $post_id, (string) get_post_meta( $post_id, AppointmentMeta::key( 'payment_status' ), true ) ),
            'timeline'    => $this->appointment_timeline( $status ),
        );
    }

    /**
     * Build the payment sub-block for a record.
     *
     * Returns only NON-sensitive values. The gateway transaction id is
     * masked to its last 6 characters to avoid leaking a full provider id.
     *
     * @param string $object_type   Object type.
     * @param int    $object_id     Object id.
     * @param string $payment_state Payment-state meta value.
     * @return array<string, mixed>
     */
    protected function payment_block( string $object_type, int $object_id, string $payment_state ): array {

        if ( '' === $payment_state ) {
            $payment_state = 'not_required';
        }

        $txns = $this->payments->transactions_for_object( $object_type, $object_id );
        $txn  = isset( $txns[0] ) && is_array( $txns[0] ) ? $txns[0] : array();

        $gateway_id   = isset( $txn['gateway'] ) ? (string) $txn['gateway'] : '';
        $gateway_name = '';
        $gateway = '' !== $gateway_id ? $this->payments->gateway( $gateway_id ) : null;

        if ( $gateway ) {
            $gateway_name = $gateway->get_name();
        }

        $gateway_ref = isset( $txn['reference'] ) ? (string) $txn['reference'] : '';

        return array(
            'status_label'   => $this->payment_state_label( $payment_state ),
            'status_key'     => $payment_state,
            'gateway'        => $gateway_name,
            'payment_ref'    => isset( $txn['public_ref'] ) ? (string) $txn['public_ref'] : '',
            'gateway_ref'    => self::mask_reference( $gateway_ref ),
            'amount'         => isset( $txn['amount'] ) ? (string) $txn['amount'] : '',
            'currency'       => isset( $txn['currency'] ) ? (string) $txn['currency'] : '',
            'date'           => isset( $txn['created_at'] ) ? (string) $txn['created_at'] : '',
            'is_paid'        => in_array( $payment_state, array( 'paid' ), true ),
            'receipt_ref'    => isset( $txn['public_ref'] ) ? (string) $txn['public_ref'] : '',
        );
    }

    /**
     * Customer-facing payment state label.
     *
     * @param string $state State slug.
     * @return string
     */
    protected function payment_state_label( string $state ): string {

        if ( in_array( $state, array( 'on_hold', 'awaiting_payment', 'processing' ), true )) {
            return __( 'Pending Manual Verification', 'business-builder' );
        }

        return ConsultationMeta::payment_label( $state );
    }

    /**
     * Consultation status timeline (real states, no fakes).
     *
     * @param string $status Current status.
     * @return array<int, array<string, mixed>>
     */
    protected function consultation_timeline( string $status ): array {

        $order = array( 'new' => 0, 'pending' => 1, 'contacted' => 2, 'scheduled' => 3, 'completed' => 4 );

        $current = isset( $order[ $status ] ) ? $order[ $status ] : 0;

        $labels = array(
            __( 'Submitted', 'business-builder' ),
            __( 'Payment', 'business-builder' ),
            __( 'Under Review', 'business-builder' ),
            __( 'Scheduled', 'business-builder' ),
            __( 'Completed', 'business-builder' ),
        );

        $steps = array();

        foreach ( $labels as $index => $label ) {
            $steps[] = array(
                'label' => $label,
                'done'  => ( $index <= $current ),
            );
        }

        return $steps;
    }

    /**
     * Appointment status timeline (real states, no fakes).
     *
     * @param string $status Current status.
     * @return array<int, array<string, mixed>>
     */
    protected function appointment_timeline( string $status ): array {

        $order = array( 'pending' => 0, 'confirmed' => 2, 'completed' => 3 );

        $current = isset( $order[ $status ] ) ? $order[ $status ] : 0;

        $labels = array(
            __( 'Requested', 'business-builder' ),
            __( 'Payment', 'business-builder' ),
            __( 'Confirmed', 'business-builder' ),
            __( 'Completed', 'business-builder' ),
        );

        $steps = array();

        foreach ( $labels as $index => $label ) {
            $steps[] = array(
                'label' => $label,
                'done'  => ( $index <= $current ),
            );
        }

        return $steps;
    }

    /**
     * Find a post id by a public-reference meta key.
     *
     * @param string $post_type Post type.
     * @param string $meta_key  Meta key.
     * @param string $reference Reference.
     * @return int
     */
    protected function find_post( string $post_type, string $meta_key, string $reference ): int {

        $args = array(
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'no_found_rows'  => true,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'   => $meta_key,
                    'value' => $reference,
                ),
            ),
        );

        $ids = get_posts( $args );

        return isset( $ids[0] ) ? (int) $ids[0] : 0;
    }

    /**
     * Normalize a phone number for comparison.
     *
     * Rule: strip everything except digits. Leading international
     * prefixes (00 / +) are removed, and a single leading 0 is treated
     * as equivalent to the country code's national form by also comparing
     * the last N digits. Matching is deliberately strict on length so two
     * unrelated numbers cannot collide.
     *
     * @param string $phone Raw phone.
     * @return string
     */
    public static function normalize_phone( string $phone ): string {

        $digits = preg_replace( '/[^0-9]/', '', $phone );

        if ( ! is_string( $digits ))  {
            return '';
        }

        return ltrim( $digits, '0' );
    }

    /**
     * Whether two phone numbers match under the normalization rule.
     *
     * @param string $stored Stored phone.
     * @param string $input  Submitted phone.
     * @return bool
     */
    public static function phone_matches( string $stored, string $input ): bool {

        $a = self::normalize_phone( $stored );
        $b = self::normalize_phone( $input );

        if ( '' === $a || '' === $b ) {
            return false;
        }

        /* Exact match after normalization. */
        if ( $a === $b ) {
            return true;
        }

        /*
         * Otherwise require a long shared tail (>= 8 digits) so differing
         * country prefixes still match but unrelated short numbers cannot.
         */
        $min = min( strlen( $a ), strlen( $b ) );

        if ( $min < 8 ) {
            return false;
        }

        return substr( $a, -8 ) === substr( $b, -8 );
    }

    /**
     * Mask a provider reference to its last 6 characters.
     *
     * @param string $reference Reference.
     * @return string
     */
    public static function mask_reference( string $reference ): string {

        $reference = trim( $reference );

        if ( strlen( $reference ) <= 6 ) {
            return $reference;
        }

        return '…' . substr( $reference, -6 );
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

        return 'bb_lookup_rl_' . md5( $ip . '|' . get_current_blog_id() );
    }

    /**
     * Whether the visitor exceeded the lookup budget.
     *
     * @return bool
     */
    protected function is_rate_limited(): bool {

        $count = (int) get_transient( $this->rate_key() );

        /** Filter the per-window lookup budget. */
        $max = (int) apply_filters( 'bb_status_lookup_rate_limit', 20 );

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
