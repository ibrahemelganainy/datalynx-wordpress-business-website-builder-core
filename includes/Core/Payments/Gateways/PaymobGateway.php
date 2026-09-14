<?php

namespace BusinessBuilderCore\Core\Payments\Gateways;

use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\PaymentResult;

if ( ! defined( 'ABSPATH' ))  {
    exit;
}

/**
 * Paymob gateway — Accept (card/wallet) flow in Egypt and the region.
 *
 * Real integration using Paymob's REST API (v1) over the WordPress HTTP API.
 * Flow:
 *
 *   1. Auth:        POST /api/auth/tokens                -> auth token
 *   2. Order:       POST /api/ecommerce/orders           -> order id
 *   3. Payment key: POST /api/acceptance/payment_keys    -> payment token
 *   4. Redirect:    {iframe}/acceptance/iframes/{id}?payment_token={token}
 *   5. Callback:    transaction processed callback + HMAC verification
 *
 * Amounts are sent in the smallest currency unit (cents/piasters).
 * Status: implemented; requires real Paymob credentials for live verification.
 */
class PaymobGateway extends AbstractApiGateway {

    public function get_id(): string {
        return 'paymob';
    }

    public function get_name(): string {
        return 'Paymob';
    }

    public function get_description(): string {
        return __( 'Accept cards and wallets in Egypt and the region.', 'business-builder' );
    }

    public function is_integration_ready(): bool {
        return true;
    }

    public function get_supported_currencies(): array {
        return array( 'EGP', 'USD', 'AED', 'SAR' );
    }

    public function get_settings_schema(): array {

        return array(
            'api_key' => array(
                'label'    => __( 'API Key', 'business-builder' ),
                'type'     => 'password',
                'required' => true,
                'secret'   => true,
            ),
            'integration_id' => array(
                'label'    => __( 'Integration ID', 'business-builder' ),
                'type'     => 'text',
                'required' => true,
                'secret'   => false,
            ),
            'iframe_id' => array(
                'label'    => __( 'Iframe ID', 'business-builder' ),
                'type'     => 'text',
                'required' => true,
                'secret'   => false,
            ),
            'hmac_secret' => array(
                'label'       => __( 'HMAC Secret', 'business-builder' ),
                'type'        => 'password',
                'required'    => false,
                'secret'      => true,
                'description' => __( 'From your Paymob account. Required for callback verification.', 'business-builder' ),
            ),
        );
    }

    /**
     * Create a Paymob order + payment key and return the iframe URL.
     *
     * @param PaymentTransaction $transaction Transaction.
     * @return array<string, mixed>
     */
    public function create_payment( PaymentTransaction $transaction ): array {

        $auth = $this->auth_token();

        if ( '' === $auth ) {
            return $this->unavailable( $this->last_error );
        }

        $integration_id = $this->config( 'integration_id' );
        $iframe_id      = $this->config( 'iframe_id' );

        if ( '' === $integration_id || '' === $iframe_id ) {
            return $this->unavailable( __( 'Paymob is not fully configured (missing Integration or Iframe ID).', 'business-builder' ) );
        }

        $amount_minor = $this->to_minor_units( $transaction->amount );

        if ( $amount_minor <= 0 ) {
            return $this->unavailable( __( 'The payment amount is invalid.', 'business-builder' ) );
        }

        /* 1) Order. */
        $order_res = $this->http_post_json(
            $this->api_base() . '/api/ecommerce/orders',
            array(
                'auth_token'  => $auth,
                'delivery_needed' => false,
                'amount_cents'    => $amount_minor,
                'currency'        => $transaction->currency,
                'merchant_order_id' => $transaction->public_ref,
                'items'       => array(),
            )
        );

        $order = is_array( $order_res['body'] ) ? $order_res['body'] : array();

        if ( 201 !== (int) $order_res['status'] && 200 !== (int) $order_res['status'] ) {
            return $this->unavailable( $this->paymob_error( 'create order', $order_res ) );
        }

        $order_id = isset( $order['id'] ) ? (int) $order['id'] : 0;

        if ( $order_id <= 0 ) {
            return $this->unavailable( __( 'Paymob did not return an order id.', 'business-builder' ) );
        }

        /* 2) Payment key. */
        $key_res = $this->http_post_json(
            $this->api_base() . '/api/acceptance/payment_keys',
            array(
                'auth_token'     => $auth,
                'amount_cents'   => $amount_minor,
                'expiration'     => 3600,
                'order_id'       => $order_id,
                'currency'       => $transaction->currency,
                'integration_id' => (int) $integration_id,
                'billing_data'   => array(
                    'first_name'   => 'NA',
                    'last_name'    => 'NA',
                    'phone_number' => 'NA',
                    'email'        => 'NA',
                    'country'      => 'NA',
                    'city'         => 'NA',
                    'street'       => 'NA',
                    'state'        => 'NA',
                    'building'     => 'NA',
                    'floor'        => 'NA',
                    'apartment'    => 'NA',
                ),
            )
        );

        $key = is_array( $key_res['body'] ) ? $key_res['body'] : array();

        if ( 201 !== (int) $key_res['status'] && 200 !== (int) $key_res['status'] ) {
            return $this->unavailable( $this->paymob_error( 'create payment key', $key_res ) );
        }

        $token = isset( $key['token'] ) ? (string) $key['token'] : '';

        if ( '' === $token ) {
            return $this->unavailable( __( 'Paymob did not return a payment token.', 'business-builder' ) );
        }

        $url = $this->iframe_base() . '/acceptance/iframes/' . rawurlencode( $iframe_id ) . '?payment_token=' . rawurlencode( $token );

        return array(
            'type'      => 'redirect',
            'url'       => $url,
            'reference' => (string) $order_id,
        );
    }

    /**
     * Verify a Paymob transaction from the callback/webhook payload.
     *
     * @param array $payload Return query / callback payload.
     * @return PaymentResult
     */
    public function verify_payment( array $payload ): PaymentResult {

        $obj = $this->extract_transaction( $payload );

        if ( empty( $obj ))  {
            return new PaymentResult( false, 'pending', '', __( 'No Paymob transaction in the payload.', 'business-builder' ) );
        }

        $order_id    = isset( $obj['order']['id'] ) ? (string) $obj['order']['id'] : '';
        $success     = isset( $obj['success'] ) ? (bool) $obj['success'] : false;
        $reference   = $order_id;

        if ( ! $success ) {
            return new PaymentResult( false, 'failed', $reference, __( 'Paymob reported the payment was not successful.', 'business-builder' ) );
        }

        return new PaymentResult( true, 'paid', $reference, __( 'Paymob payment verified.', 'business-builder' ) );
    }

    /**
     * Verify a Paymob callback via HMAC, then map it to a PaymentResult.
     *
     * @param array<string,mixed>  $payload  Decoded payload.
     * @param array<string,string> $headers  Request headers (lowercase keys).
     * @param string               $raw_body Raw body.
     * @return PaymentResult
     */
    public function handle_webhook( array $payload, array $headers, string $raw_body ): PaymentResult {

        $hmac = isset( $payload['hmac'] ) ? (string) $payload['hmac'] : '';

        if ( '' === $hmac ) {
            $hmac = isset( $headers['x-paymob-hmac'] ) ? (string) $headers['x-paymob-hmac'] : '';
        }

        $secret = $this->config( 'hmac_secret' );

        if ( '' !== $secret && '' !== $hmac ) {
            if ( ! $this->verify_hmac( $payload, $hmac, $secret ))  {
                $this->log_debug( 'callback hmac mismatch', array() );
                return new PaymentResult( false, 'pending', '', __( 'Paymob callback verification failed.', 'business-builder' ) );
            }
        } else {
            /* Without an HMAC secret a callback cannot be trusted. */
            return new PaymentResult( false, 'pending', '', __( 'Paymob callback is not verifiable (no HMAC secret).', 'business-builder' ) );
        }

        return $this->verify_payment( $payload );
    }

    /* ------------------------------------------------------------------ */

    /**
     * Extract the transaction object from a callback payload.
     *
     * @param array<string,mixed> $payload Payload.
     * @return array<string,mixed>
     */
    protected function extract_transaction( array $payload ): array {

        if ( isset( $payload['obj'] ) && is_array( $payload['obj'] ))  {
            return $payload['obj'];
        }

        if ( isset( $payload['obj'] ))  {
            $decoded = json_decode( (string) $payload['obj'], true );
            if ( is_array( $decoded ))  {
                return $decoded;
            }
        }

        return array();
    }

    /**
     * Verify the Paymob HMAC for a callback payload.
     *
     * Concatenates the documented fields in order and compares sha512 HMAC.
     *
     * @param array<string,mixed> $payload Callback payload.
     * @param string              $hmac    Provided HMAC.
     * @param string              $secret  HMAC secret.
     * @return bool
     */
    protected function verify_hmac( array $payload, string $hmac, string $secret ): bool {

        $obj = $this->extract_transaction( $payload );

        if ( empty( $obj ))  {
            return false;
        }

        $order = isset( $obj['order'] ) && is_array( $obj['order'] ) ? $obj['order'] : array();

        $fields = array(
            $obj['amount_cents'] ?? '',
            $obj['created_at'] ?? '',
            $obj['currency'] ?? '',
            $obj['error_occured'] ?? '',
            $obj['has_parent_transaction'] ?? '',
            isset( $obj['id'] ) ? $obj['id'] : '',
            $obj['integration_id'] ?? '',
            $obj['is_3d_secure'] ?? '',
            $obj['is_auth'] ?? '',
            $obj['is_capture'] ?? '',
            $obj['is_refunded'] ?? '',
            $obj['is_standalone_payment'] ?? '',
            $obj['is_voided'] ?? '',
            isset( $order['id'] ) ? $order['id'] : '',
            $obj['owner'] ?? '',
            $obj['pending'] ?? '',
            $obj['source_data']['pan'] ?? '',
            $obj['source_data']['sub_type'] ?? '',
            $obj['source_data']['type'] ?? '',
            $obj['success'] ?? '',
        );

        $concatenated = '';

        foreach ( $fields as $value ) {
            $concatenated .= (string) $value;
        }

        $calculated = hash_hmac( 'sha512', $concatenated, $secret );

        return hash_equals( $calculated, strtolower( $hmac ) );
    }

    /**
     * Obtain a Paymob auth token.
     *
     * @return string Token, or '' on failure.
     */
    protected function auth_token(): string {

        $api_key = $this->config( 'api_key' );

        if ( '' === $api_key ) {
            $this->last_error = __( 'Paymob is not configured (missing API key).', 'business-builder' );
            return '';
        }

        $res  = $this->http_post_json( $this->api_base() . '/api/auth/tokens', array( 'api_key' => $api_key ) );
        $body = is_array( $res['body'] ) ? $res['body'] : array();

        if ( 200 !== (int) $res['status'] && 201 !== (int) $res['status'] ) {
            $this->paymob_error( 'auth', $res );
            $this->last_error = __( 'Paymob authentication failed. Please check the API key.', 'business-builder' );
            return '';
        }

        $token = isset( $body['token'] ) ? (string) $body['token'] : '';

        if ( '' === $token ) {
            $this->last_error = __( 'Paymob did not return an auth token.', 'business-builder' );
        }

        return $token;
    }

    /**
     * API base host.
     *
     * @return string
     */
    protected function api_base(): string {
        return 'https://accept.paymob.com';
    }

    /**
     * Iframe base host.
     *
     * @return string
     */
    protected function iframe_base(): string {
        return 'https://accept.paymob.com';
    }

    /**
     * Convert a decimal amount to minor units.
     *
     * @param string $amount Decimal string.
     * @return int
     */
    protected function to_minor_units( string $amount ): int {

        if ( '' === $amount || ! is_numeric( $amount ))  {
            return 0;
        }

        return (int) round( (float) $amount * 100 );
    }

    /**
     * Log a Paymob error and return a customer-safe message.
     *
     * @param string              $stage  Stage.
     * @param array<string,mixed> $result Result.
     * @return string
     */
    protected function paymob_error( string $stage, array $result ): string {

        $body = is_array( $result['body'] ) ? $result['body'] : array();

        $this->log_debug(
            'paymob ' . $stage . ' failed',
            array(
                'http'      => (int) $result['status'],
                'message'   => isset( $body['message'] ) ? (string) $body['message'] : '',
                'detail'    => isset( $body['detail'] ) ? (string) $body['detail'] : '',
                'transport' => (string) $result['error'],
            )
        );

        return __( 'We could not start the payment with Paymob. Please try another payment method.', 'business-builder' );
    }

    /**
     * Last customer-safe error.
     */
    protected string $last_error = '';

    /**
     * Standard "unavailable" result.
     *
     * @param string $message Message.
     * @return array<string, mixed>
     */
    protected function unavailable( string $message ): array {

        return array( 'type' => 'unavailable', 'message' => $message );
    }
}