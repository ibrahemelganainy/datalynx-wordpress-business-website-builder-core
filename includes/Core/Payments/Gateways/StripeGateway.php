<?php

namespace BusinessBuilderCore\Core\Payments\Gateways;

use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\PaymentResult;

if ( ! defined( 'ABSPATH' )) {
    exit;
}

/**
 * Stripe gateway — Checkout Session (redirect) flow.
 *
 * Real integration using Stripe's REST API over the WordPress HTTP API
 * (no SDK dependency). Flow:
 *
 *   create_payment()  -> POST /v1/checkout/sessions   -> returns { url }
 *   verify_payment()  -> GET  /v1/checkout/sessions/{id} (server-side truth)
 *   handle_webhook()  -> signature-verified checkout.session.completed
 *
 * The amount/currency ALWAYS come from the stored transaction (built
 * server-side), never from the browser. A browser return alone never marks
 * a payment paid: verification (or a signed webhook) is required.
 *
 * Status: implemented; requires real Stripe keys for live verification.
 */
class StripeGateway extends AbstractApiGateway {

    public function get_id(): string {
        return 'stripe';
    }

    public function get_name(): string {
        return 'Stripe';
    }

    public function get_description(): string {
        return __( 'Card payments via Stripe Checkout (Visa, Mastercard, Amex).', 'business-builder' );
    }

    public function is_integration_ready(): bool {
        return true;
    }

    public function get_supported_currencies(): array {
        return array( 'USD', 'EUR', 'GBP', 'AED', 'SAR', 'CAD', 'AUD', 'EGP' );
    }

    public function get_settings_schema(): array {

        return array(
            'mode' => array(
                'label'   => __( 'Mode', 'business-builder' ),
                'type'    => 'select',
                'default' => 'test',
                'options' => array(
                    'test' => __( 'Test', 'business-builder' ),
                    'live' => __( 'Live', 'business-builder' ),
                ),
            ),
            'publishable_key' => array(
                'label'    => __( 'Publishable Key', 'business-builder' ),
                'type'     => 'text',
                'required' => true,
                'secret'   => false,
            ),
            'secret_key' => array(
                'label'    => __( 'Secret Key', 'business-builder' ),
                'type'     => 'password',
                'required' => true,
                'secret'   => true,
            ),
            'webhook_secret' => array(
                'label'       => __( 'Webhook Signing Secret', 'business-builder' ),
                'type'        => 'password',
                'required'    => false,
                'secret'      => true,
                'description' => __( 'From your Stripe webhook endpoint (whsec_...). Required for webhook verification.', 'business-builder' ),
            ),
        );
    }

    /**
     * Create a Stripe Checkout Session and return its redirect URL.
     *
     * @param PaymentTransaction $transaction Transaction.
     * @return array<string, mixed>
     */
    public function create_payment( PaymentTransaction $transaction ): array {

        $secret = $this->config( 'secret_key' );

        if ( '' === $secret ) {
            return $this->unavailable( __( 'Stripe is not configured (missing secret key).', 'business-builder' ) );
        }

        $amount_minor = $this->to_minor_units( $transaction->amount );

        if ( $amount_minor <= 0 ) {
            return $this->unavailable( __( 'The payment amount is invalid.', 'business-builder' ) );
        }

        $currency = strtolower( $transaction->currency );

        $body = array(
            'mode'                                          => 'payment',
            'success_url'                                   => $this->get_return_url( $transaction ) . '&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'                                    => $this->get_cancel_url( $transaction ),
            'client_reference_id'                           => $transaction->public_ref,
            'line_items[0][quantity]'                       => 1,
            'line_items[0][price_data][currency]'           => $currency,
            'line_items[0][price_data][unit_amount]'        => $amount_minor,
            'line_items[0][price_data][product_data][name]' => $this->line_label( $transaction ),
        );

        $result = $this->http_post_form(
            $this->api_base() . '/v1/checkout/sessions',
            $body,
            array( 'Authorization' => 'Bearer ' . $secret )
        );

        $session = is_array( $result['body'] ) ? $result['body'] : array();

        if ( 200 !== (int) $result['status'] && 201 !== (int) $result['status'] ) {
            return $this->unavailable( $this->stripe_error( $result ) );
        }

        $url = isset( $session['url'] ) ? (string) $session['url'] : '';
        $ref = isset( $session['id'] ) ? (string) $session['id'] : '';

        if ( '' === $url ) {
            return $this->unavailable( __( 'Stripe did not return a checkout URL.', 'business-builder' ) );
        }

        return array(
            'type'      => 'redirect',
            'url'       => $url,
            'reference' => $ref,
        );
    }

    /**
     * Server-side verification of a Stripe session (never trust the browser).
     *
     * @param array $payload Return query / webhook payload.
     * @return PaymentResult
     */
    public function verify_payment( array $payload ): PaymentResult {

        $session_id = '';

        foreach ( array( 'session_id', 'checkout_session_id', 'id' ) as $key ) {
            if ( isset( $payload[ $key ] ) && is_scalar( $payload[ $key ] ) && '' !== (string) $payload[ $key ] ) {
                $session_id = (string) $payload[ $key ];
                break;
            }
        }

        if ( '' === $session_id ) {
            return new PaymentResult( false, 'pending', '', __( 'No Stripe session reference supplied.', 'business-builder' ) );
        }

        return $this->verify_session( $session_id );
    }

    /**
     * Verify a webhook by signature, then confirm the session server-side.
     *
     * @param array<string,mixed>  $payload  Decoded payload.
     * @param array<string,string> $headers  Request headers (lowercase keys).
     * @param string               $raw_body Raw body.
     * @return PaymentResult
     */
    public function handle_webhook( array $payload, array $headers, string $raw_body ): PaymentResult {

        $signature = isset( $headers['stripe-signature'] ) ? (string) $headers['stripe-signature'] : '';
        $secret    = $this->config( 'webhook_secret' );

        if ( '' === $secret || '' === $signature ) {
            return new PaymentResult( false, 'pending', '', __( 'Stripe webhook signature is missing or not configured.', 'business-builder' ) );
        }

        if ( ! $this->verify_stripe_signature( $raw_body, $signature, $secret )) {
            $this->log_debug( 'webhook signature mismatch', array() );
            return new PaymentResult( false, 'pending', '', __( 'Stripe webhook signature verification failed.', 'business-builder' ) );
        }

        $type = isset( $payload['type'] ) ? (string) $payload['type'] : '';

        if ( 'checkout.session.completed' !== $type ) {
            return new PaymentResult( false, 'pending', '', __( 'Ignored non-completion Stripe event.', 'business-builder' ) );
        }

        $session    = isset( $payload['data']['object'] ) && is_array( $payload['data']['object'] ) ? $payload['data']['object'] : array();
        $session_id = isset( $session['id'] ) ? (string) $session['id'] : '';

        if ( '' === $session_id ) {
            return new PaymentResult( false, 'pending', '', __( 'Stripe webhook carried no session id.', 'business-builder' ) );
        }

        /* Re-confirm with the provider (defence in depth against forged bodies). */
        return $this->verify_session( $session_id );
    }

    /* ------------------------------------------------------------------ */

    /**
     * Verify a checkout session with Stripe and map it to a PaymentResult.
     *
     * @param string $session_id Stripe session id.
     * @return PaymentResult
     */
    protected function verify_session( string $session_id ): PaymentResult {

        $secret = $this->config( 'secret_key' );

        if ( '' === $secret ) {
            return new PaymentResult( false, 'pending', '', __( 'Stripe is not configured.', 'business-builder' ) );
        }

        $result = $this->http_get(
            $this->api_base() . '/v1/checkout/sessions/' . rawurlencode( $session_id ),
            array( 'Authorization' => 'Bearer ' . $secret )
        );

        $session = is_array( $result['body'] ) ? $result['body'] : array();

        if ( 200 !== (int) $result['status'] ) {
            return new PaymentResult( false, 'pending', $session_id, $this->stripe_error( $result ) );
        }

        $payment_status = isset( $session['payment_status'] ) ? (string) $session['payment_status'] : '';
        $status         = isset( $session['status'] ) ? (string) $session['status'] : '';

        if ( 'paid' === $payment_status ) {
            return new PaymentResult( true, 'paid', $session_id, __( 'Stripe payment verified.', 'business-builder' ) );
        }

        if ( 'expired' === $status ) {
            return new PaymentResult( false, 'expired', $session_id, __( 'Stripe session expired.', 'business-builder' ) );
        }

        return new PaymentResult( false, 'pending', $session_id, __( 'Stripe session is not paid yet.', 'business-builder' ) );
    }

    /**
     * Verify a Stripe webhook signature (t=...,v1=... scheme).
     *
     * @param string $body      Raw body.
     * @param string $signature Header value.
     * @param string $secret    Webhook signing secret.
     * @return bool
     */
    protected function verify_stripe_signature( string $body, string $signature, string $secret ): bool {

        $timestamp  = '';
        $signatures = array();

        foreach ( explode( ',', $signature ) as $part ) {
            $pieces = explode( '=', trim( $part ), 2 );
            if ( 2 !== count( $pieces )) {
                continue;
            }
            if ( 't' === $pieces[0] ) {
                $timestamp = $pieces[1];
            } elseif ( 'v1' === $pieces[0] ) {
                $signatures[] = $pieces[1];
            }
        }

        if ( '' === $timestamp || empty( $signatures )) {
            return false;
        }

        /* Reject stale events (replay protection): 5 minute tolerance. */
        if ( abs( time() - (int) $timestamp ) > 300 ) {
            return false;
        }

        $expected = hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );

        foreach ( $signatures as $candidate ) {
            if ( hash_equals( $expected, $candidate )) {
                return true;
            }
        }

        return false;
    }

    /**
     * API base (Stripe uses the same host for test and live keys).
     *
     * @return string
     */
    protected function api_base(): string {
        return 'https://api.stripe.com';
    }

    /**
     * Convert a decimal amount to Stripe minor units (cents).
     *
     * @param string $amount Decimal string.
     * @return int
     */
    protected function to_minor_units( string $amount ): int {

        if ( '' === $amount || ! is_numeric( $amount )) {
            return 0;
        }

        return (int) round( (float) $amount * 100 );
    }

    /**
     * Human line label for the transaction.
     *
     * @param PaymentTransaction $transaction Transaction.
     * @return string
     */
    protected function line_label( PaymentTransaction $transaction ): string {

        $label = isset( $transaction->meta['label'] ) ? (string) $transaction->meta['label'] : '';

        return '' !== $label ? $label : __( 'Service payment', 'business-builder' );
    }

    /**
     * Safe, human error message from a Stripe response (details go to the log).
     *
     * @param array<string,mixed> $result Normalised request result.
     * @return string
     */
    protected function stripe_error( array $result ): string {

        $body    = is_array( $result['body'] ) ? $result['body'] : array();
        $error   = isset( $body['error'] ) && is_array( $body['error'] ) ? $body['error'] : array();
        $message = isset( $error['message'] ) ? (string) $error['message'] : '';
        $code    = isset( $error['code'] ) ? (string) $error['code'] : '';

        $this->log_debug(
            'request failed',
            array(
                'http'  => (int) $result['status'],
                'code'  => $code,
                'error' => $message,
            )
        );

        return __( 'We could not start the payment with Stripe. Please try another payment method.', 'business-builder' );
    }

    /**
     * Standard "unavailable" result.
     *
     * @param string $message Customer-safe message.
     * @return array<string, mixed>
     */
    protected function unavailable( string $message ): array {

        return array( 'type' => 'unavailable', 'message' => $message );
    }
}