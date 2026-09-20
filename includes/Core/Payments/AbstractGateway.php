<?php

namespace BusinessBuilderCore\Core\Payments;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Base class for payment gateways.
 *
 * Provides shared credential storage (per-site options), masking, and
 * schema-driven settings. Concrete gateways declare their identity and,
 * where implemented, their API behaviour (spec 5/8/9).
 *
 * Credentials are stored in a per-site option (never post meta, never
 * exposed to the frontend) so Multisite sites stay isolated (spec 10).
 */
abstract class AbstractGateway implements PaymentGatewayInterface {

    /**
     * Option name holding all gateways' settings for this site.
     */
    public const OPTION_NAME = 'bb_payment_gateways';

    /**
     * Default: automatic gateways are not manual.
     */
    public function is_manual(): bool {
        return false;
    }

    /**
     * Default: no billing block required (overridden by gateways that
     * need billing_data, e.g. Paymob).
     */
    public function needs_billing(): bool {
        return false;
    }

    /**
     * Default: no public instructions (automatic gateways redirect the
     * customer to the provider). Manual gateways override this to expose
     * the administrator's configured payment details as safe, labelled
     * rows (never secrets).
     *
     * @return array<string, mixed>
     */
    public function get_public_instructions(): array {
        return array();
    }

    /**
     * Default description (empty => the card shows only the name).
     */
    public function get_description(): string {
        return '';
    }

    /**
     * Default logo: a local asset at assets/img/gateways/{id}.svg.
     *
     * Using a local asset keeps the critical admin UI independent of
     * fragile external image hosts. Packs/extensions may override this.
     */
    public function get_logo_url(): string {

        $relative = 'assets/img/gateways/' . $this->get_id() . '.svg';

        $path = BB_CORE_PATH . $relative;

        if ( file_exists( $path ) ) {
            return BB_CORE_URL . $relative;
        }

        return '';
    }

    /**
     * Default: a gateway accepts any currency unless it says otherwise.
     *
     * @return string[]
     */
    public function get_supported_currencies(): array {
        return array();
    }

    /**
     * Default: not integration-ready (honest default).
     */
    public function is_integration_ready(): bool {
        return false;
    }

    /**
     * Validate configuration using the declared schema.
     *
     * @return string[]
     */
    public function validate_configuration(): array {

        $problems = array();

        foreach ( $this->get_settings_schema() as $key => $field ) {

            if ( empty( $field['required'] ) ) {
                continue;
            }

            if ( '' === (string) $this->get_setting( $key, '' ) ) {

                $label = isset( $field['label'] ) ? (string) $field['label'] : $key;

                $problems[] = sprintf(
                    /* translators: %s: field label */
                    __( '%s is required.', 'business-builder' ),
                    $label
                );
            }
        }

        return $problems;
    }

    /**
     * Default: no redirect URL (manual / embedded gateways override).
     *
     * @param PaymentTransaction $transaction Transaction.
     */
    public function get_payment_url( PaymentTransaction $transaction ): string {
        return '';
    }

    /**
     * Default browser-return URL: the REST callback that VERIFIES the payment.
     *
     * This points at the plugin's callback route
     * (/business-builder/v1/payment/callback/{gateway}) so the incoming
     * return payload is actually processed server-side before the customer
     * is sent back to the site. The URL is built with rest_url(), which
     * adapts to ANY permalink structure (pretty /wp-json/ OR ?rest_route=),
     * so it never breaks when permalinks change.
     *
     * @param PaymentTransaction $transaction Transaction.
     * @return string
     */
    public function get_return_url( PaymentTransaction $transaction ): string {

        return $this->callback_url( $transaction, 'return' );
    }

    /**
     * Build a browser-return / cancel URL that lands on the REST callback.
     *
     * @param PaymentTransaction $transaction Transaction.
     * @param string             $hint        'return'|'cancel' (informational).
     * @return string
     */
    protected function callback_url( PaymentTransaction $transaction, string $hint ): string {

        $path = '/business-builder/v1/payment/callback/' . sanitize_key( $this->get_id() );

        $args = array(
            'bb_outcome' => sanitize_key( $hint ),
            'bb_ref'     => $transaction->public_ref,
        );

        /*
         * Carry the page the customer started from so the callback can send
         * them back to the SAME page (where the checkout message renders)
         * instead of a generic home URL. Only the path is kept (safe).
         */
        $origin = isset( $transaction->meta['origin'] ) ? (string) $transaction->meta['origin'] : '';

        if ( '' !== $origin ) {
            $args['bb_origin'] = $origin;
        }

        /*
         * Build the absolute callback URL through rest_url(), which adapts
         * to the site's permalink structure (pretty /wp-json/ OR the
         * ?rest_route= fallback) and always resolves on the CURRENT site's
         * host/subdirectory. This is gateway-independent: every gateway
         * uses the same helper, so no gateway's routing can break another's.
         *
         * rest_url() already appends the route with the correct separator,
         * so the extra args are appended with add_query_arg().
         */
        $base = rest_url( $path );

        return add_query_arg( $args, $base );
    }

    /**
     * Default cancel URL (status page, keyed by public reference).
     *
     * @param PaymentTransaction $transaction Transaction.
     * @return string
     */
    public function get_cancel_url( PaymentTransaction $transaction ): string {

        return $this->callback_url( $transaction, 'cancel' );
    }

    /**
     * Default webhook handling: delegate to verify_payment().
     *
     * @param array<string, mixed>  $payload  Raw payload.
     * @param array<string, string> $headers  Headers (lowercase keys).
     * @param string                $raw_body Raw body.
     * @return PaymentResult
     */
    public function handle_webhook( array $payload, array $headers, string $raw_body ): PaymentResult {

        return $this->verify_payment( $payload );
    }

    /**
     * Read this gateway's stored settings for the current site.
     *
     * @return array<string, mixed>
     */
    public function get_settings(): array {

        $all = get_option( self::OPTION_NAME, array() );

        if ( ! is_array( $all ) || ! isset( $all[ $this->get_id() ] ) ) {
            return array();
        }

        $settings = $all[ $this->get_id() ];

        return is_array( $settings ) ? $settings : array();
    }

    /**
     * Read one setting.
     *
     * @param string $key     Field key.
     * @param mixed  $default Default.
     * @return mixed
     */
    public function get_setting( string $key, $default = '' ) {

        $settings = $this->get_settings();

        return array_key_exists( $key, $settings )
            ? $settings[ $key ]
            : $default;
    }

    /**
     * Whether required fields are present.
     *
     * @return bool
     */
    public function is_configured(): bool {

        $schema = $this->get_settings_schema();

        foreach ( $schema as $key => $field ) {

            if ( empty( $field['required'] ) ) {
                continue;
            }

            if ( '' === (string) $this->get_setting( $key, '' ) ) {
                return false;
            }
        }

        return true;
    }
}
