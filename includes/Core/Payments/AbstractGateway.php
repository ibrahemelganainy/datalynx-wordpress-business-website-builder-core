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
