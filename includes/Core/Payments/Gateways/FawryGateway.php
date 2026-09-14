<?php

namespace BusinessBuilderCore\Core\Payments\Gateways;

use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\PaymentResult;

if ( ! defined( 'ABSPATH' ))  {
    exit;
}

/**
 * Fawry gateway — reference-number flow (FawryPay).
 *
 * Fawry is NOT a redirect/card gateway. The backend creates a payment and
 * receives a reference number the customer pays at any Fawry outlet or
 * online; the transaction then sits in "awaiting_payment" until Fawry's
 * notification confirms it.
 *
 * Flow:
 *   1. POST /fawrypay-api/api/payments/init -> referenceNumber (+ expiry)
 *   2. Show the reference + amount to the customer (awaiting_payment)
 *   3. Fawry notification (signed) -> verify -> completed
 *
 * The signature is SHA-256 over a documented field ordering.
 * Status: implemented; requires real Fawry merchant credentials.
 */
class FawryGateway extends AbstractApiGateway {

    public function get_id(): string {
        return 'fawry';
    }

    public function get_name(): string {
        return 'Fawry';
    }

    public function get_description(): string {
        return __( 'Pay at thousands of Fawry outlets or online using a reference number.', 'business-builder' );
    }

    public function is_integration_ready(): bool {
        return true;
    }

    public function get_supported_currencies(): array {
        return array( 'EGP' );
    }

    public function get_settings_schema(): array {

        return array(
            'merchant_code' => array(
                'label'    => __( 'Merchant Code', 'business-builder' ),
                'type'     => 'text',
                'required' => true,
                'secret'   => false,
            ),
            'security_key' => array(
                'label'    => __( 'Security Key', 'business-builder' ),
                'type'     => 'password',
                'required' => true,
                'secret'   => true,
            ),
        );
    }

    /**
     * Create a Fawry payment reference.
     *
     * Returns a 'reference' routing result (not a redirect): the caller
     * shows the reference number to the customer.
     *
     * @param PaymentTransaction $transaction Transaction.
     * @return array<string, mixed>
     */
    public function create_payment( PaymentTransaction $transaction ): array {

        $merchant  = $this->config( 'merchant_code' );
        $security  = $this->config( 'security_key' );

        if ( '' === $merchant || '' === $security ) {
            return $this->unavailable( __( 'Fawry is not fully configured.', 'business-builder' ) );
        }

        if ( 'EGP' !== strtoupper( $transaction->currency ))  {
            return $this->unavailable( __( 'Fawry supports EGP only.', 'business-builder' ) );
        }

        $amount = $this->amount_string( $transaction->amount );

        if ( '' === $amount ) {
            return $this->unavailable( __( 'The payment amount is invalid.', 'business-builder' ) );
        }

        $merchant_ref = $transaction->public_ref;
        $signature    = $this->charge_signature( $merchant, $merchant_ref, $amount, $security );

        $body = array(
            'merchantCode'      => $merchant,
            'merchantRefNum'    => $merchant_ref,
            'customerName'      => 'Customer',
            'customerMobile'    => 'NA',
            'customerEmail'     => 'NA',
            'amount'            => $amount,
            'currencyCode'      => 'EGP',
            'language'          => ( is_rtl() ? 'ar-eg' : 'en-gb' ),
            'chargeItems'       => array(
                array(
                    'itemId'    => 'service',
                    'description' => $this->line_label( $transaction ),
                    'price'     => $amount,
                    'quantity'  => 1,
                ),
            ),
            'signature'         => $signature,
        );

        $res  = $this->http_post_json( $this->api_base() . '/fawrypay-api/api/payments/init', $body );
        $data = is_array( $res['body'] ) ? $res['body'] : array();

        if ( 200 !== (int) $res['status'] ) {
            return $this->unavailable( $this->fawry_error( 'init', $res ) );
        }

        $reference = isset( $data['referenceNumber'] ) ? (string) $data['referenceNumber'] : '';

        if ( '' === $reference ) {
            return $this->unavailable( __( 'Fawry did not return a reference number.', 'business-builder' ) );
        }

        return array(
            'type'      => 'reference',
            'reference' => $reference,
            'message'   => __( 'Pay using the Fawry reference number below.', 'business-builder' ),
        );
    }

    /**
     * Verify a Fawry payment from a return/webhook payload.
     *
     * @param array $payload Payload (querystring or notification body).
     * @return PaymentResult
     */
    public function verify_payment( array $payload ): PaymentResult {

        $reference = '';

        foreach ( array( 'referenceNumber', 'reference_number', 'fawryRefNumber', 'merchantRefNumber' ) as $key ) {
            if ( isset( $payload[ $key ] ) && is_scalar( $payload[ $key ] ) && '' !== (string) $payload[ $key ] ) {
                $reference = (string) $payload[ $key ];
                break;
            }
        }

        if ( '' === $reference ) {
            return new PaymentResult( false, 'pending', '', __( 'No Fawry reference supplied.', 'business-builder' ) );
        }

        $status = isset( $payload['orderStatus'] ) ? strtoupper( (string) $payload['orderStatus'] ) : '';

        if ( in_array( $status, array( 'PAID', 'SUCCESS', 'SUCCESSFUL' ), true ))  {
            return new PaymentResult( true, 'paid', $reference, __( 'Fawry payment verified.', 'business-builder' ) );
        }

        if ( in_array( $status, array( 'FAILED', 'EXPIRED', 'CANCELED', 'CANCELLED' ), true ))  {
            return new PaymentResult( false, 'failed', $reference, __( 'Fawry reported the payment was not successful.', 'business-builder' ) );
        }

        return new PaymentResult( false, 'pending', $reference, __( 'Fawry payment is not completed yet.', 'business-builder' ) );
    }

    /**
     * Verify a Fawry notification via its signature, then map it.
     *
     * @param array<string,mixed>  $payload  Decoded payload.
     * @param array<string,string> $headers  Headers.
     * @param string               $raw_body Raw body.
     * @return PaymentResult
     */
    public function handle_webhook( array $payload, array $headers, string $raw_body ): PaymentResult {

        $security = $this->config( 'security_key' );

        if ( '' === $security ) {
            return new PaymentResult( false, 'pending', '', __( 'Fawry security key is not configured.', 'business-builder' ) );
        }

        /*
         * Fawry notifications may arrive as form-encoded POST data. The
         * caller passes the decoded payload; the merchant reference and
         * amount must both be present for a signature check.
         */
        $provided = isset( $payload['signature'] ) ? (string) $payload['signature'] : '';

        /* Fawry's classic notification signature: sha256(merchantRefNum + amount + securityKey). */
        $merchant_ref = isset( $payload['merchantRefNumber'] ) ? (string) $payload['merchantRefNumber'] : '';
        $amount       = isset( $payload['paymentAmount'] ) ? (string) $payload['paymentAmount'] : '';

        if ( '' === $provided || '' === $merchant_ref || '' === $amount ) {
            return new PaymentResult( false, 'pending', '', __( 'Fawry notification is incomplete.', 'business-builder' ) );
        }

        $expected = hash( 'sha256', $merchant_ref . $amount . $security );

        if ( ! hash_equals( $expected, strtolower( $provided ) )) {
            $this->log_debug( 'notification signature mismatch', array() );
            return new PaymentResult( false, 'pending', '', __( 'Fawry notification verification failed.', 'business-builder' ) );
        }

        return $this->verify_payment( $payload );
    }

    /* ------------------------------------------------------------------ */

    /**
     * Build the Fawry charge signature.
     *
     * sha256(merchantCode + merchantRefNum + customerProfileId + paymentMethod
     *        + amount + securityKey) — the documented ordering.
     *
     * @param string $merchant     Merchant code.
     * @param string $merchant_ref Merchant reference.
     * @param string $amount       Amount (2dp).
     * @param string $security     Security key.
     * @return string
     */
    protected function charge_signature( string $merchant, string $merchant_ref, string $amount, string $security ): string {

        $raw = $merchant . $merchant_ref . 'NA' . 'PAYATFAWRY' . $amount . $security;

        return hash( 'sha256', $raw );
    }

    /**
     * API base host.
     *
     * @return string
     */
    protected function api_base(): string {
        return 'https://www.atfawry.com';
    }

    /**
     * Format an amount to 2 decimals.
     *
     * @param string $amount Amount.
     * @return string
     */
    protected function amount_string( string $amount ): string {

        if ( '' === $amount || ! is_numeric( $amount ))  {
            return '';
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
     * Log a Fawry error and return a customer-safe message.
     *
     * @param string              $stage  Stage.
     * @param array<string,mixed> $result Result.
     * @return string
     */
    protected function fawry_error( string $stage, array $result ): string {

        $body = is_array( $result['body'] ) ? $result['body'] : array();

        $this->log_debug(
            'fawry ' . $stage . ' failed',
            array(
                'http'      => (int) $result['status'],
                'status'    => isset( $body['statusCode'] ) ? (string) $body['statusCode'] : '',
                'message'   => isset( $body['statusDescription'] ) ? (string) $body['statusDescription'] : '',
                'transport' => (string) $result['error'],
            )
        );

        return __( 'We could not create the Fawry payment. Please try another payment method.', 'business-builder' );
    }

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