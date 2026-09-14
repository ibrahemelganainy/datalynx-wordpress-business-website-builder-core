<?php

namespace BusinessBuilderCore\Core\Payments\Gateways;

use BusinessBuilderCore\Core\Payments\AbstractGateway;
use BusinessBuilderCore\Core\Payments\PaymentTransaction;
use BusinessBuilderCore\Core\Payments\PaymentResult;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Base for API-based gateways that are CONFIGURATION READY but whose
 * live API integration has NOT been implemented/verified in this codebase
 * (spec 35).
 *
 * These classes:
 *   - declare their real credential schema (so admins can configure them);
 *   - store credentials securely via the shared option layer;
 *   - NEVER pretend a payment succeeded.
 *
 * Until a provider is actually implemented + tested, create_payment()
 * returns an 'unavailable' result and verify_payment() returns a failure.
 * This is the honest, safe behaviour the spec requires.
 */
abstract class AbstractApiGateway extends AbstractGateway {

    public function is_manual(): bool {
        return false;
    }

    /**
     * Whether this provider's live API has been implemented AND tested
     * in this codebase. Always false until a real integration is added.
     *
     * @return bool
     */
    public function is_integration_ready(): bool {
        return false;
    }

    public function create_payment( PaymentTransaction $transaction ): array {

        return array(
            'type'    => 'unavailable',
            'message' => sprintf(
                /* translators: %s: gateway name */
                __( '%s is configuration-ready, but its live payment API has not been enabled on this site yet. Please choose another payment method.', 'business-builder' ),
                $this->get_name()
            ),
        );
    }

    public function verify_payment( array $payload ): PaymentResult {

        return new PaymentResult(
            false,
            'pending',
            '',
            sprintf(
                /* translators: %s: gateway name */
                __( '%s webhook verification is not enabled (no verified API integration).', 'business-builder' ),
                $this->get_name()
            )
        );
    }

    /* ------------------------------------------------------------------
     * Shared HTTP + safe debug logging for live API integrations
     * ------------------------------------------------------------------ */

    /**
     * Read a configuration value for this gateway on the current site.
     *
     * @param string $key     Field key.
     * @param string $default Default value.
     * @return string
     */
    protected function config( string $key, string $default = '' ): string {

        $value = $this->get_setting( $key, $default );

        return is_scalar( $value ) ? (string) $value : (string) $default;
    }

    /**
     * POST a JSON body to an API endpoint.
     *
     * Returns [ 'status' => int, 'body' => array|string, 'error' => string ].
     * Never throws: transport errors are returned in 'error'.
     *
     * @param string               $url     Absolute URL.
     * @param array<string, mixed> $body    JSON body.
     * @param array<string, string> $headers Extra headers.
     * @return array<string, mixed>
     */
    protected function http_post_json( string $url, array $body, array $headers = array() ): array {

        $args = array(
            'timeout' => 30,
            'headers' => array_merge(
                array(
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ),
                $headers
            ),
            'body'    => wp_json_encode( $body ),
        );

        return $this->request( 'post', $url, $args );
    }

    /**
     * POST an x-www-form-urlencoded body (used by OAuth token endpoints).
     *
     * @param string                $url     Absolute URL.
     * @param array<string, mixed>  $body    Form fields.
     * @param array<string, string> $headers Extra headers.
     * @return array<string, mixed>
     */
    protected function http_post_form( string $url, array $body, array $headers = array() ): array {

        $args = array(
            'timeout' => 30,
            'headers' => array_merge(
                array( 'Accept' => 'application/json' ),
                $headers
            ),
            'body'    => $body,
        );

        return $this->request( 'post', $url, $args );
    }

    /**
     * GET a URL with optional headers.
     *
     * @param string                $url     Absolute URL.
     * @param array<string, string> $headers Headers.
     * @return array<string, mixed>
     */
    protected function http_get( string $url, array $headers = array() ): array {

        $args = array(
            'timeout' => 30,
            'headers' => array_merge( array( 'Accept' => 'application/json' ), $headers ),
        );

        return $this->request( 'get', $url, $args );
    }

    /**
     * Perform a WordPress HTTP request and normalise the result.
     *
     * @param string               $method 'post'|'get'.
     * @param string               $url    URL.
     * @param array<string, mixed> $args   Request args.
     * @return array<string, mixed> { status:int, body:array|string, error:string, raw:string }
     */
    protected function request( string $method, string $url, array $args ): array {

        $response = 'post' === $method
            ? wp_remote_post( $url, $args )
            : wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {

            $this->log_debug( 'transport error', array( 'url' => $this->redact_url( $url ), 'error' => $response->get_error_message() ) );

            return array( 'status' => 0, 'body' => array(), 'error' => $response->get_error_message(), 'raw' => '' );
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $raw    = (string) wp_remote_retrieve_body( $response );
        $json   = json_decode( $raw, true );

        $body = is_array( $json ) ? $json : $raw;

        return array( 'status' => $status, 'body' => $body, 'error' => '', 'raw' => $raw );
    }

    /**
     * Log a sanitized technical message to the WP debug log.
     *
     * ONLY called when WP_DEBUG_LOG is enabled. Never logs secrets: keys
     * whose name looks sensitive are masked, and the Authorization header
     * is never passed in.
     *
     * @param string               $message Message.
     * @param array<string, mixed> $context Context (sanitized).
     */
    protected function log_debug( string $message, array $context = array() ): void {

        $enabled = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;

        if ( ! $enabled ) {
            return;
        }

        $blocked = array( 'client_secret', 'secret', 'secret_key', 'api_key', 'webhook_secret', 'password', 'token', 'hmac', 'authorization', 'private_key' );

        foreach ( $context as $key => $value ) {
            if ( in_array( strtolower( (string) $key ), $blocked, true ) ) {
                $context[ $key ] = '***';
            }
        }

        error_log( '[bb-payment ' . $this->get_id() . '] ' . $message . '' . wp_json_encode( $context ) );
    }

    /**
     * Strip query strings (which may carry tokens) from a URL for logging.
     *
     * @param string $url URL.
     * @return string
     */
    protected function redact_url( string $url ): string {

        $parts = wp_parse_url( $url );

        if ( ! is_array( $parts ) ) {
            return '[redacted]';
        }

        $host = isset( $parts['host'] ) ? (string) $parts['host'] : '';
        $path = isset( $parts['path'] ) ? (string) $parts['path'] : '';

        return 'https://' . $host . $path;
    }
}
