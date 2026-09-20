<?php
namespace BusinessBuilderCore\Core\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * HTTP transport self-healing for payment gateways.
 *
 * Some local/server PHP builds ship WITHOUT a configured CA bundle
 * (curl.cainfo / openssl.cafile unset in php.ini). In that state every
 * HTTPS request fails with "SSL certificate problem: unable to get local
 * issuer certificate" (or a self-signed chain error) — which breaks ALL
 * API payment gateways at once, while manual gateways keep working.
 *
 * This class points cURL at a CA bundle that already exists on the machine
 * (WordPress ships one) so outbound HTTPS works even when php.ini is not
 * configured. It only ever sets a CA bundle when one is NOT already set, so
 * a correctly-configured server is never overridden.
 *
 * Scope: only requests made through the WordPress HTTP API (which is what
 * the gateway adapters use). Nothing else is touched.
 */
final class HttpTransport {

    /**
     * Register the cURL CA-bundle fix.
     */
    public static function register(): void {

        add_action( 'http_api_curl', array( __CLASS__, 'configure_curl' ), 10, 3 );
    }

    /**
     * Point cURL at a usable CA bundle when PHP has none configured.
     *
     * @param \CurlHandle|resource $handle The cURL handle (unused; needed for the hook signature).
     * @param array                $args   Request args.
     * @param string               $url    Request URL.
     */
    public static function configure_curl( $handle, array $args, string $url ): void {

        $ca = self::ca_bundle_path();

        if ( '' === $ca ) {
            return;
        }

        /*
         * Respect an explicitly-provided CA bundle on the request itself.
         */
        if ( isset( $args['sslcertificates'] ) && '' !== (string) $args['sslcertificates'] ) {
            return;
        }

        /*
         * Only act on HTTPS requests (HTTP needs no CA).
         */
        if ( 0 !== strpos( strtolower( $url ), 'https://' )) {
            return;
        }

        /* Only fill in a CA bundle when PHP has none configured. */
        $configured = (string) ini_get( 'curl.cainfo' );

        if ( '' !== $configured && file_exists( $configured )) {
            return;
        }

        if ( function_exists( 'curl_setopt' ) && defined( 'CURLOPT_CAINFO' )) {
            curl_setopt( $handle, CURLOPT_CAINFO, $ca );
        }
    }

    /**
     * The first existing CA bundle on this machine (or '').
     *
     * @return string
     */
    public static function ca_bundle_path(): string {

        $candidates = array();

        if ( defined( 'ABSPATH' ) && defined( 'WPINC' )) {
            $candidates[] = ABSPATH . WPINC . '/certificates/ca-bundle.crt';
        }

        /* MAMP keeps a Mozilla bundle with Apache. */
        $candidates[] = 'C:\\MAMP\\bin\\apache\\bin\\cacert.pem';
        $candidates[] = '/Applications/MAMP/Library/OpenSSL/certs/cacert.pem';

        /* PHP's own ini values, if present. */
        $candidates[] = (string) ini_get( 'openssl.cafile' );
        $candidates[] = (string) ini_get( 'curl.cainfo' );

        foreach ( $candidates as $path ) {

            $path = (string) $path;

            if ( '' !== $path && file_exists( $path )) {
                return $path;
            }
        }

        return '';
    }
}
