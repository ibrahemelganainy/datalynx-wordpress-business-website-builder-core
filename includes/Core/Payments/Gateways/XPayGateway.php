<?php

namespace BusinessBuilderCore\Core\Payments\Gateways;

use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\PaymentResult;

if ( ! defined( 'ABSPATH' )) {
    exit;
}

/**
 * XPay (Egypt) gateway — Checkout Session (hosted redirect) flow.
 *
 * Real integration using XPay's REST API over the WordPress HTTP API (no SDK
 * dependency). Contract per the official docs (https://docs.xpay.app):
 *
 *   1. Create:  POST https://api.xpay.app/checkout/sessions
 *               Header: Authorization: Bearer sk_test_… / sk_live_…
 *               Body:   { afterCompletion:{type:'redirect',redirect:{url:
 *                         '…{CHECKOUT_SESSION_ID}'}}, lineItems:[…] }
 *               Returns { id:'cs_…', url:'https://checkout.xpay.app/cs_…',
 *                         status:'open', paymentStatus:'unpaid', … }
 *   2. Redirect the customer to session.url (XPay Hosted Checkout).
 *   3. Return:  the customer comes back to afterCompletion.redirect.url with
 *               the session id substituted for {CHECKOUT_SESSION_ID}.
 *   4. Verify:  GET https://api.xpay.app/checkout/sessions/{id}
 *               -> status ('open'|'complete'|'expired') and paymentStatus
 *                  ('unpaid'|'paid'). A charge is settled only when
 *                  paymentStatus === 'paid'.
 *   5. Webhook: events 'checkout.session.completed' / 'async_payment_succeeded'
 *               carry the same session object as data.object; the signature
 *               header 'XPay-Signature: t=…,v1=…' is verified with the
 *               whsec_… endpoint secret (HMAC-SHA256 over "{t}.{rawBody}").
 *
 * Amounts are integers in the smallest currency unit (piasters for EGP):
 * 149.00 EGP => 14900. The amount/currency ALWAYS come from the stored
 * transaction (built server-side), never the browser. A browser return alone
 * never marks a payment paid: server-side verification (or a signed webhook)
 * is required.
 */
class XPayGateway extends AbstractApiGateway {

    public function get_id(): string {
        return 'xpay';
    }

    public function get_name(): string {
        return 'XPay';
    }

    public function get_description(): string {
        return __( 'Pay securely with XPay — cards and local methods (Egypt).', 'business-builder' );
    }

    /**
     * The live API is implemented against XPay's documented contract.
     */
    public function is_integration_ready(): bool {
        return true;
    }

    /**
     * XPay settles in EGP (Egyptian Pound).
     *
     * @return string[]
     */
    public function get_supported_currencies(): array {
        return array( 'EGP' );
    }

    public function get_settings_schema(): array {

        return array(
            'mode' => array(
                'label'   => __( 'Mode', 'business-builder' ),
                'type'    => 'select',
                'default' => 'test',
                'options' => array(
                    'test' => __( 'Test / Sandbox', 'business-builder' ),
                    'live' => __( 'Live', 'business-builder' ),
                ),
            ),
            'publishable_key' => array(
                'label'       => __( 'Publishable Key', 'business-builder' ),
                'type'        => 'text',
                'required'    => true,
                'secret'      => false,
                'placeholder' => 'pk_test_…',
                'description' => __( 'From your XPay dashboard (Developers → API keys).', 'business-builder' ),
            ),
            'secret_key' => array(
                'label'       => __( 'Secret Key', 'business-builder' ),
                'type'        => 'password',
                'required'    => true,
                'secret'      => true,
                'placeholder' => 'sk_test_…',
                'description' => __( 'Server-side key. Never exposed to visitors.', 'business-builder' ),
            ),
            'webhook_secret' => array(
                'label'       => __( 'Webhook Signing Secret', 'business-builder' ),
                'type'        => 'password',
                'required'    => false,
                'secret'      => true,
                'placeholder' => 'whsec_…',
                'description' => __( 'From your XPay webhook endpoint. Required to verify webhooks.', 'business-builder' ),
            ),
        );
    }

    /**
     * Create an XPay Checkout Session and return the hosted checkout URL.
     *
     * @param PaymentTransaction $transaction Transaction.
     * @return array<string, mixed>
     */
    public function create_payment( PaymentTransaction $transaction ): array {

        $secret = $this->config( 'secret_key' );

        if ( '' === $secret ) {
            return $this->unavailable( __( 'XPay is not configured (missing Secret Key).', 'business-builder' ) );
        }

        if ( 'EGP' !== strtoupper( (string) $transaction->currency )) {
            return $this->unavailable( __( 'XPay supports EGP only. Please choose another payment method.', 'business-builder' ) );
        }

        $amount_minor = $this->to_minor_units( $transaction->amount );

        if ( $amount_minor <= 0 ) {
            return $this->unavailable( __( 'The payment amount is invalid.', 'business-builder' ) );
        }

        /*
         * afterCompletion.redirect.url carries the literal
         * {CHECKOUT_SESSION_ID} token: XPay substitutes the real session id
         * before it stores the URL, so our return route receives it on the
         * path/query and can verify server-side.
         */
        $return_url = add_query_arg( 'session_id', '{CHECKOUT_SESSION_ID}', $this->get_return_url( $transaction ) );

        $body = array(
            /*
             * Hosted Checkout: XPay renders its own payment page and redirects
             * the customer back to redirect.url. Also required so `cancelUrl`
             * is accepted (see below).
             */
            'uiMode'          => 'hosted',
            'afterCompletion' => array(
                'type'     => 'redirect',
                'redirect' => array( 'url' => $return_url ),
            ),
            'lineItems'       => array(
                array(
                    'priceData' => array(
                        'currency'    => 'EGP',
                        'unitAmount'  => $amount_minor,
                        'productData' => array( 'name' => $this->line_label( $transaction ) ),
                    ),
                    'quantity'  => 1,
                ),
            ),
            /*
             * The transaction reference lives ONLY inside `metadata`:
             * XPay flags `clientReferenceId` as an unknown ROOT parameter
             * (400 invalid_request_error / parameter_unknown), so it is NOT
             * sent at the top level. `metadata.public_ref` is what the
             * callback/webhook map back to the transaction.
             *
             * XPay requires STRING values, so every value is cast.
             */
            'metadata'        => array(
                'public_ref'  => (string) $transaction->public_ref,
                'object_type' => (string) $transaction->object_type,
                'object_id'   => (string) $transaction->object_id,
            ),
        );

        /*
         * `cancelUrl` is a valid ROOT parameter ONLY for uiMode "hosted" —
         * XPay rejects it for the embedded/custom modes. We only ever use
         * hosted Checkout, so we send it when it is non-empty, guarded so it
         * can never be sent for a non-hosted mode.
         */
        $cancel = $this->get_cancel_url( $transaction );

        if ( '' !== $cancel && 'hosted' === $body['uiMode'] ) {
            $body['cancelUrl'] = $cancel;
        }

        $result = $this->http_post_json(
            $this->api_base() . '/checkout/sessions',
            $body,
            array( 'Authorization' => 'Bearer ' . $secret )
        );

        $session = is_array( $result['body'] ) ? $result['body'] : array();

        if ( 200 !== (int) $result['status'] && 201 !== (int) $result['status'] ) {
            return $this->unavailable( $this->xpay_error( 'create session', $result ) );
        }

        $url = isset( $session['url'] ) ? (string) $session['url'] : '';
        $ref = isset( $session['id'] ) ? (string) $session['id'] : '';

        if ( '' === $url ) {
            return $this->unavailable( __( 'XPay did not return a checkout URL.', 'business-builder' ) );
        }

        return array(
            'type'      => 'redirect',
            'url'       => $url,
            'reference' => $ref,
        );
    }

    /**
     * Server-side verification of an XPay Checkout Session.
     *
     * Never trusts the browser: the session is re-read from XPay and only a
     * session whose paymentStatus is 'paid' is treated as settled.
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
            return new PaymentResult( false, 'pending', '', __( 'No XPay session reference supplied.', 'business-builder' ) );
        }

        return $this->verify_session( $session_id );
    }

    /**
     * Verify an XPay webhook: check the signature, then re-read the session.
     *
     * @param array<string,mixed>  $payload  Decoded payload.
     * @param array<string,string> $headers  Request headers (lowercase keys).
     * @param string               $raw_body Raw body.
     * @return PaymentResult
     */
    public function handle_webhook( array $payload, array $headers, string $raw_body ): PaymentResult {

        $signature = isset( $headers['xpay-signature'] )
            ? (string) $headers['xpay-signature']
            : ( isset( $headers['x-pay-signature'] ) ? (string) $headers['x-pay-signature'] : '' );

        if ( '' === $signature ) {
            $signature = isset( $payload['signature'] ) ? (string) $payload['signature'] : '';
        }

        $secret = $this->config( 'webhook_secret' );

        if ( '' === $secret || '' === $signature ) {
            return new PaymentResult( false, 'pending', '', __( 'XPay webhook signature is missing or not configured.', 'business-builder' ) );
        }

        if ( ! $this->verify_signature( $raw_body, $signature, $secret )) {
            $this->log_debug( 'webhook signature mismatch', array() );
            return new PaymentResult( false, 'pending', '', __( 'XPay webhook signature verification failed.', 'business-builder' ) );
        }

        $type = isset( $payload['type'] ) ? (string) $payload['type'] : '';

        if ( ! in_array( $type, array( 'checkout.session.completed', 'checkout.session.async_payment_succeeded' ), true )) {
            return new PaymentResult( false, 'pending', '', __( 'Ignored non-completion XPay event.', 'business-builder' ) );
        }

        $session    = isset( $payload['data']['object'] ) && is_array( $payload['data']['object'] )
            ? $payload['data']['object']
            : array();
        $session_id = isset( $session['id'] ) ? (string) $session['id'] : '';

        if ( '' === $session_id ) {
            return new PaymentResult( false, 'pending', '', __( 'XPay webhook carried no session id.', 'business-builder' ) );
        }

        /* Defence in depth: re-confirm with XPay, do not trust the body. */
        return $this->verify_session( $session_id );
    }

    /* ------------------------------------------------------------------ */

    /**
     * Verify a checkout session with XPay and map it to a PaymentResult.
     *
     * @param string $session_id XPay session id (cs_…).
     * @return PaymentResult
     */
    protected function verify_session( string $session_id ): PaymentResult {

        $secret = $this->config( 'secret_key' );

        if ( '' === $secret ) {
            return new PaymentResult( false, 'pending', '', __( 'XPay is not configured.', 'business-builder' ) );
        }

        $result = $this->http_get(
            $this->api_base() . '/checkout/sessions/' . rawurlencode( $session_id ),
            array( 'Authorization' => 'Bearer ' . $secret )
        );

        $session = is_array( $result['body'] ) ? $result['body'] : array();

        if ( 200 !== (int) $result['status'] ) {
            return new PaymentResult( false, 'pending', $session_id, $this->xpay_error( 'verify session', $result ) );
        }

        $payment_status = isset( $session['paymentStatus'] ) ? (string) $session['paymentStatus'] : '';
        $status         = isset( $session['status'] ) ? (string) $session['status'] : '';

        if ( 'paid' === $payment_status ) {
            return new PaymentResult( true, 'paid', $session_id, __( 'XPay payment verified.', 'business-builder' ) );
        }

        if ( 'expired' === $status ) {
            return new PaymentResult( false, 'expired', $session_id, __( 'XPay session expired.', 'business-builder' ) );
        }

        return new PaymentResult( false, 'pending', $session_id, __( 'XPay session is not paid yet.', 'business-builder' ) );
    }

    /**
     * Verify an XPay webhook signature ('t=…,v1=…' scheme).
     *
     * The signed payload is "{t}.{raw_body}" and the expected signature is an
     * HMAC-SHA256 with the endpoint's whsec_… secret, hex-encoded.
     *
     * @param string $body      Raw body.
     * @param string $signature Header value.
     * @param string $secret    Webhook signing secret.
     * @return bool
     */
    protected function verify_signature( string $body, string $signature, string $secret ): bool {

        $timestamp  = '';
        $candidates = array();

        foreach ( explode( ',', $signature ) as $part ) {

            $pieces = explode( '=', trim( $part ), 2 );

            if ( 2 !== count( $pieces )) {
                continue;
            }

            if ( 't' === $pieces[0] ) {
                $timestamp = $pieces[1];
            } elseif ( 'v1' === $pieces[0] ) {
                $candidates[] = $pieces[1];
            }
        }

        if ( '' === $timestamp || empty( $candidates )) {
            /*
             * Fall back to a bare hex signature when the header is not in the
             * t/v1 form, so a plain HMAC-SHA256 of the body still verifies.
             */
            $bare = preg_replace( '/[^a-f0-9]/i', '', $signature );

            if ( strlen( $bare ) >= 32 ) {
                return hash_equals( hash_hmac( 'sha256', $body, $secret ), strtolower( $bare ) );
            }

            return false;
        }

        /* Replay protection: 5 minute tolerance. */
        if ( abs( time() - (int) $timestamp ) > 300 ) {
            return false;
        }

        $expected = hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );

        foreach ( $candidates as $candidate ) {
            if ( hash_equals( $expected, strtolower( $candidate ))) {
                return true;
            }
        }

        return false;
    }

    /**
     * API base host.
     *
     * @return string
     */
    protected function api_base(): string {
        return 'https://api.xpay.app';
    }

    /**
     * Convert a decimal amount to minor units (piasters).
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
     * Safe, human error message from an XPay response (details go to the log).
     *
     * @param string              $stage  Stage.
     * @param array<string,mixed> $result Normalised request result.
     * @return string
     */
    protected function xpay_error( string $stage, array $result ): string {

        $body    = is_array( $result['body'] ) ? $result['body'] : array();
        $message = '';

        if ( isset( $body['message'] ) && is_scalar( $body['message'] )) {
            $message = (string) $body['message'];
        } elseif ( isset( $body['error']['message'] ) && is_scalar( $body['error']['message'] )) {
            $message = (string) $body['error']['message'];
        } elseif ( isset( $body['detail'] ) && is_scalar( $body['detail'] )) {
            $message = (string) $body['detail'];
        }

        $this->log_debug(
            'xpay ' . $stage . ' failed',
            array(
                'http'      => (int) $result['status'],
                'message'   => $message,
                'transport' => (string) $result['error'],
            )
        );

        if ( current_user_can( 'manage_options' ) && '' !== $message ) {
            return sprintf(
                /* translators: 1: stage, 2: provider reason */
                __( 'XPay %1$s failed: %2$s', 'business-builder' ),
                $stage,
                $message
            );
        }

        return __( 'We could not start the payment with XPay. Please try another payment method.', 'business-builder' );
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
