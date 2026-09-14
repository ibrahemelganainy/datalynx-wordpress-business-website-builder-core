<?php

namespace BusinessBuilderCore\Core\Payments\Gateways;

use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\PaymentResult;

if ( ! defined( 'ABSPATH' ))  {
    exit;
}

/**
 * PayPal gateway — Orders v2 (redirect + capture) flow.
 *
 * Real integration using PayPal's REST API over the WordPress HTTP API
 * (no SDK dependency). Flow:
 *
 *   1. OAuth:  POST /v1/oauth2/token  (Basic client_id:client_secret)
 *   2. Create: POST /v2/checkout/orders  -> id + approve link
 *   3. Return: customer approves, returns with token=<orderId>
 *   4. Capture:POST /v2/checkout/orders/{id}/capture  -> COMPLETED
 *   5. Verify: GET  /v2/checkout/orders/{id}          -> server-side truth
 *
 * The amount/currency ALWAYS come from the stored transaction (built
 * server-side). A browser return alone never marks a payment paid:
 * capture + a server-side GET (or a verified webhook) is required.
 *
 * Status: implemented; requires real PayPal sandbox/live credentials.
 */
class PayPalGateway extends AbstractApiGateway {

    public function get_id(): string {
        return 'paypal';
    }

    public function get_name(): string {
        return 'PayPal';
    }

    public function get_description(): string {
        return __( 'Pay with a PayPal account or card. Secure online payment.', 'business-builder' );
    }

    public function is_integration_ready(): bool {
        return true;
    }

    public function get_supported_currencies(): array {
        return array( 'USD', 'EUR', 'GBP', 'SAR', 'AED', 'CAD', 'AUD', 'EGP' );
    }

    public function get_settings_schema(): array {

        return array(
            'client_id' => array(
                'label'    => __( 'Client ID', 'business-builder' ),
                'type'     => 'text',
                'required' => true,
                'secret'   => false,
            ),
            'client_secret' => array(
                'label'    => __( 'Client Secret', 'business-builder' ),
                'type'     => 'password',
                'required' => true,
                'secret'   => true,
            ),
            'mode' => array(
                'label'    => __( 'Mode', 'business-builder' ),
                'type'     => 'select',
                'required' => true,
                'default'  => 'sandbox',
                'secret'   => false,
                'options'  => array(
                    'sandbox' => __( 'Sandbox', 'business-builder' ),
                    'live'    => __( 'Live', 'business-builder' ),
                ),
            ),
            'webhook_id' => array(
                'label'       => __( 'Webhook ID', 'business-builder' ),
                'type'        => 'text',
                'required'    => false,
                'secret'      => false,
                'description' => __( 'From your PayPal webhook settings. Required for webhook verification.', 'business-builder' ),
            ),
        );
    }

    /**
     * Create a PayPal order and return the approval URL.
     *
     * @param PaymentTransaction $transaction Transaction.
     * @return array<string, mixed>
     */
    public function create_payment( PaymentTransaction $transaction ): array {

        $token = $this->access_token();

        if ( '' === $token ) {
            return $this->unavailable( $this->last_error_message() );
        }

        if ( ! $this->currency_supported( $transaction->currency ))  {
            return $this->unavailable( __( 'PayPal does not support the configured currency.', 'business-builder' ) );
        }

        $body = array(
            'intent'         => 'CAPTURE',
            'purchase_units' => array(
                array(
                    'reference_id' => $transaction->public_ref,
                    'custom_id'    => $transaction->public_ref,
                    'description'  => $this->line_label( $transaction ),
                    'amount'       => array(
                        'currency_code' => $transaction->currency,
                        'value'         => $this->amount_string( $transaction->amount ),
                    ),
                ),
            ),
            'application_context' => array(
                'brand_name'          => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
                'shipping_preference' => 'NO_SHIPPING',
                'user_action'         => 'PAY_NOW',
                'return_url'          => $this->get_return_url( $transaction ),
                'cancel_url'          => $this->get_cancel_url( $transaction ),
            ),
        );

        $result = $this->http_post_json(
            $this->api_base() . '/v2/checkout/orders',
            $body,
            array( 'Authorization' => 'Bearer ' . $token )
        );

        $order = is_array( $result['body'] ) ? $result['body'] : array();

        if ( 201 !== (int) $result['status'] && 200 !== (int) $result['status'] ) {
            return $this->unavailable( $this->paypal_error( 'create order', $result ) );
        }

        $order_id = isset( $order['id'] ) ? (string) $order['id'] : '';
        $approve  = $this->approve_link( $order );

        if ( '' === $order_id || '' === $approve ) {
            return $this->unavailable( __( 'PayPal did not return an approval link.', 'business-builder' ) );
        }

        return array(
            'type'      => 'redirect',
            'url'       => $approve,
            'reference' => $order_id,
        );
    }

    /**
     * Verify (and capture) a PayPal order from the return payload.
     *
     * @param array $payload Return query / webhook payload.
     * @return PaymentResult
     */
    public function verify_payment( array $payload ): PaymentResult {

        $order_id = '';

        foreach ( array( 'token', 'order_id', 'orderID', 'id' ) as $key ) {
            if ( isset( $payload[ $key ] ) && is_scalar( $payload[ $key ] ) && '' !== (string) $payload[ $key ] ) {
                $order_id = (string) $payload[ $key ];
                break;
            }
        }

        if ( '' === $order_id ) {
            return new PaymentResult( false, 'pending', '', __( 'No PayPal order reference supplied.', 'business-builder' ) );
        }

        return $this->capture_and_verify( $order_id );
    }

    /**
     * Verify a PayPal webhook (signature verification via PayPal's API).
     *
     * @param array<string,mixed>  $payload  Decoded payload.
     * @param array<string,string> $headers  Request headers (lowercase keys).
     * @param string               $raw_body Raw body.
     * @return PaymentResult
     */
    public function handle_webhook( array $payload, array $headers, string $raw_body ): PaymentResult {

        $webhook_id = $this->config( 'webhook_id' );

        if ( '' === $webhook_id ) {
            return new PaymentResult( false, 'pending', '', __( 'PayPal webhook id is not configured.', 'business-builder' ) );
        }

        if ( ! $this->verify_webhook_signature( $webhook_id, $headers, $raw_body ))  {
            $this->log_debug( 'webhook signature verification failed', array() );
            return new PaymentResult( false, 'pending', '', __( 'PayPal webhook signature verification failed.', 'business-builder' ) );
        }

        $event_type = isset( $payload['event_type'] ) ? (string) $payload['event_type'] : '';

        if ( 'CHECKOUT.ORDER.APPROVED' !== $event_type && 'PAYMENT.CAPTURE.COMPLETED' !== $event_type ) {
            return new PaymentResult( false, 'pending', '', __( 'Ignored non-completion PayPal event.', 'business-builder' ) );
        }

        $resource = isset( $payload['resource'] ) && is_array( $payload['resource'] ) ? $payload['resource'] : array();

        $order_id = '';

        if ( 'CHECKOUT.ORDER.APPROVED' === $event_type ) {
            $order_id = isset( $resource['id'] ) ? (string) $resource['id'] : '';
        } else {
            /* PAYMENT.CAPTURE.COMPLETED: find the order id in supplementary data. */
            $order_id = isset( $resource['supplementary_data']['related_ids']['order_id'] )
                ? (string) $resource['supplementary_data']['related_ids']['order_id']
                : '';
        }

        if ( '' === $order_id ) {
            return new PaymentResult( false, 'pending', '', __( 'PayPal webhook carried no order id.', 'business-builder' ) );
        }

        return $this->capture_and_verify( $order_id );
    }

    /* ------------------------------------------------------------------ */

    /**
     * Capture (if needed) then read an order and map it to a PaymentResult.
     *
     * @param string $order_id PayPal order id.
     * @return PaymentResult
     */
    protected function capture_and_verify( string $order_id ): PaymentResult {

        $token = $this->access_token();

        if ( '' === $token ) {
            return new PaymentResult( false, 'pending', $order_id, $this->last_error_message() );
        }

        $auth = array( 'Authorization' => 'Bearer ' . $token );

        /* Read current state first. */
        $get = $this->http_get( $this->api_base() . '/v2/checkout/orders/' . rawurlencode( $order_id ), $auth );
        $order = is_array( $get['body'] ) ? $get['body'] : array();

        $status = isset( $order['status'] ) ? (string) $order['status'] : '';

        if ( 'COMPLETED' === $status ) {
            return new PaymentResult( true, 'paid', $order_id, __( 'PayPal payment verified.', 'business-builder' ) );
        }

        if ( 'APPROVED' === $status ) {
            /* Capture the approved order. */
            $cap = $this->http_post_json(
                $this->api_base() . '/v2/checkout/orders/' . rawurlencode( $order_id ) . '/capture',
                array(),
                $auth
            );

            $cap_body   = is_array( $cap['body'] ) ? $cap['body'] : array();
            $cap_status = isset( $cap_body['status'] ) ? (string) $cap_body['status'] : '';

            if ( 'COMPLETED' === $cap_status ) {
                return new PaymentResult( true, 'paid', $order_id, __( 'PayPal payment captured.', 'business-builder' ) );
            }

            if ( 200 !== (int) $cap['status'] && 201 !== (int) $cap['status'] ) {
                return new PaymentResult( false, 'pending', $order_id, $this->paypal_error( 'capture', $cap ) );
            }

            return new PaymentResult( false, 'pending', $order_id, __( 'PayPal capture did not complete.', 'business-builder' ) );
        }

        if ( 'COMPLETED' === $status ) {
            return new PaymentResult( true, 'paid', $order_id, __( 'PayPal payment verified.', 'business-builder' ) );
        }

        return new PaymentResult( false, 'pending', $order_id, __( 'PayPal order is not approved yet.', 'business-builder' ) );
    }

    /**
     * Obtain a PayPal OAuth2 access token (cached per request).
     *
     * @return string Token, or '' on failure.
     */
    protected function access_token(): string {

        static $cache = array();

        $client_id     = $this->config( 'client_id' );
        $client_secret = $this->config( 'client_secret' );

        if ( '' === $client_id || '' === $client_secret ) {
            $this->set_last_error( __( 'PayPal is not fully configured (missing Client ID or Secret).', 'business-builder' ) );
            return '';
        }

        $cache_key = md5( $client_id . '|' . $this->config( 'mode', 'sandbox' ) );

        if ( isset( $cache[ $cache_key ] ))  {
            return $cache[ $cache_key ];
        }

        $result = $this->http_post_form(
            $this->api_base() . '/v1/oauth2/token',
            array( 'grant_type' => 'client_credentials' ),
            array(
                'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            )
        );

        $body = is_array( $result['body'] ) ? $result['body'] : array();

        if ( 200 !== (int) $result['status'] ) {
            $this->paypal_error( 'oauth', $result );
            $this->set_last_error( __( 'PayPal authentication failed. Please check the Client ID and Secret.', 'business-builder' ) );
            return '';
        }

        $token = isset( $body['access_token'] ) ? (string) $body['access_token'] : '';

        if ( '' === $token ) {
            $this->set_last_error( __( 'PayPal did not return an access token.', 'business-builder' ) );
            return '';
        }

        $cache[ $cache_key ] = $token;

        return $token;
    }

    /**
     * Verify a PayPal webhook signature using PayPal's verification API.
     *
     * @param string               $webhook_id Webhook id.
     * @param array<string,string> $headers    Request headers (lowercase keys).
     * @param string               $raw_body   Raw body.
     * @return bool
     */
    protected function verify_webhook_signature( string $webhook_id, array $headers, string $raw_body ): bool {

        $token = $this->access_token();

        if ( '' === $token ) {
            return false;
        }

        $body = array(
            'auth_algo'         => isset( $headers['paypal-auth-algo'] ) ? (string) $headers['paypal-auth-algo'] : '',
            'cert_url'          => isset( $headers['paypal-cert-url'] ) ? (string) $headers['paypal-cert-url'] : '',
            'transmission_id'   => isset( $headers['paypal-transmission-id'] ) ? (string) $headers['paypal-transmission-id'] : '',
            'transmission_sig'  => isset( $headers['paypal-transmission-sig'] ) ? (string) $headers['paypal-transmission-sig'] : '',
            'transmission_time' => isset( $headers['paypal-transmission-time'] ) ? (string) $headers['paypal-transmission-time'] : '',
            'webhook_id'        => $webhook_id,
            'webhook_event'     => json_decode( $raw_body, true ),
        );

        $result = $this->http_post_json(
            $this->api_base() . '/v1/notifications/verify-webhook-signature',
            $body,
            array( 'Authorization' => 'Bearer ' . $token )
        );

        $resp = is_array( $result['body'] ) ? $result['body'] : array();

        return isset( $resp['verification_status'] ) && 'SUCCESS' === (string) $resp['verification_status'];
    }

    /**
     * Extract the buyer approval link from an order payload.
     *
     * @param array<string,mixed> $order Order payload.
     * @return string
     */
    protected function approve_link( array $order ): string {

        if ( empty( $order['links'] ) || ! is_array( $order['links'] ))  {
            return '';
        }

        foreach ( $order['links'] as $link ) {
            if ( ! is_array( $link ))  {
                continue;
            }
            $rel = isset( $link['rel'] ) ? (string) $link['rel'] : '';
            if ( 'approve' === $rel || 'payer-action' === $rel ) {
                return isset( $link['href'] ) ? (string) $link['href'] : '';
            }
        }

        return '';
    }

    /**
     * API base for the configured mode.
     *
     * @return string
     */
    protected function api_base(): string {

        if ( 'live' === $this->config( 'mode', 'sandbox' ))  {
            return 'https://api-m.paypal.com';
        }

        return 'https://api-m.sandbox.paypal.com';
    }

    /**
     * Whether a currency is supported by this gateway.
     *
     * @param string $code Currency code.
     * @return bool
     */
    protected function currency_supported( string $code ): bool {

        $supported = $this->get_supported_currencies();

        return empty( $supported ) || in_array( strtoupper( $code ), $supported, true );
    }

    /**
     * Format an amount as PayPal expects (2-decimal string).
     *
     * @param string $amount Decimal string.
     * @return string
     */
    protected function amount_string( string $amount ): string {

        if ( '' === $amount || ! is_numeric( $amount ))  {
            return '0.00';
        }

        return number_format( (float) $amount, 2, '.', '' );
    }

    /**
     * Human line label.
     *
     * @param PaymentTransaction $transaction Transaction.
     * @return string
     */
    protected function line_label( PaymentTransaction $transaction ): string {

        $label = isset( $transaction->meta['label'] ) ? (string) $transaction->meta['label'] : '';

        return '' !== $label ? $label : __( 'Service payment', 'business-builder' );
    }

    /**
     * Log a PayPal error and return a customer-safe message.
     *
     * @param string              $stage  Stage label.
     * @param array<string,mixed> $result Normalised request result.
     * @return string
     */
    protected function paypal_error( string $stage, array $result ): string {

        $body    = is_array( $result['body'] ) ? $result['body'] : array();
        $name    = isset( $body['name'] ) ? (string) $body['name'] : '';
        $message = isset( $body['message'] ) ? (string) $body['message'] : '';
        $details = isset( $body['details'] ) && is_array( $body['details'] ) ? $body['details'] : array();

        $first_issue = '';

        if ( ! empty( $details[0] ) && is_array( $details[0] ))  {
            $first_issue = isset( $details[0]['issue'] ) ? (string) $details[0]['issue'] : '';
        }

        $this->log_debug(
            'paypal ' . $stage . ' failed',
            array(
                'http'        => (int) $result['status'],
                'name'        => $name,
                'message'     => $message,
                'issue'       => $first_issue,
                'transport'   => (string) $result['error'],
            )
        );

        return __( 'We could not start the payment with PayPal. Please try another payment method.', 'business-builder' );
    }

    /**
     * Store a customer-safe last error message for create_payment to return.
     *
     * @param string $message Message.
     */
    protected function set_last_error( string $message ): void {

        $this->last_error = $message;
    }

    /**
     * The last customer-safe error message.
     *
     * @return string
     */
    protected function last_error_message(): string {

        return '' !== $this->last_error
            ? $this->last_error
            : __( 'We could not start the payment with PayPal. Please try another payment method.', 'business-builder' );
    }

    /**
     * Last error holder.
     */
    protected string $last_error = '';

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