<?php

namespace BusinessBuilderCore\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SiteSettings {

    /**
     * WordPress option name.
     */
    private const OPTION_NAME = 'bb_site_settings';

    /**
     * Default site settings.
     *
     * @return array<string, mixed>
     */
    private function get_defaults(): array {

        return array(
            'business_name'    => '',
            'tagline'          => '',
            'logo_id'          => 0,
            'favicon_id'       => 0,

            'primary_color'    => '#1F2937',
            'secondary_color'  => '#C9A227',
            'accent_color'     => '#111827',

            'phone'            => '',
            'email'            => '',
            'address'          => '',

            'whatsapp'         => '',

            'facebook'         => '',
            'instagram'        => '',
            'youtube'          => '',
            'linkedin'         => '',
            'twitter'          => '',

            'show_phone'       => true,
            'show_email'       => true,
            'show_address'     => true,
            'show_whatsapp'    => true,

            /*
             * Consultation payment settings.
             *
             * Free by default. When require_consultation_payment is
             * true, consultation requests enter a server-controlled
             * "pending" payment state and the fee is shown on the form.
             * No payment gateway is bundled; these settings define the
             * architecture a provider plugs into later.
             */
            'require_consultation_payment' => false,
            'consultation_fee'             => '',
            'consultation_currency'        => 'USD',

            /*
             * Notification recipient. Empty means "use the site admin
             * email". Set this to route consultation, payment and
             * appointment notifications to a specific inbox.
             */
            'notification_email' => '',
        );
    }

    /**
     * Get all site settings.
     *
     * @return array<string, mixed>
     */
    public function get_all(): array {

        $saved = get_option(
            self::OPTION_NAME,
            array()
        );

        if ( ! is_array( $saved ) ) {
            $saved = array();
        }

        return wp_parse_args(
            $saved,
            $this->get_defaults()
        );
    }

    /**
     * Get one setting.
     *
     * @param string $key Setting key.
     * @param mixed  $default Default value.
     *
     * @return mixed
     */
    public function get(
        string $key,
        mixed $default = null
    ): mixed {

        $settings = $this->get_all();

        return array_key_exists( $key, $settings )
            ? $settings[ $key ]
            : $default;
    }

    /**
     * Update all settings.
     *
     * @param array<string, mixed> $settings Settings.
     */
    public function update( array $settings ): bool {

        $current = $this->get_all();

        $updated = wp_parse_args(
            $settings,
            $current
        );

        $updated = $this->sanitize(
            $updated
        );

        return update_option(
            self::OPTION_NAME,
            $updated
        );
    }

    /**
     * Update one setting.
     *
     * @param string $key Setting key.
     * @param mixed  $value Setting value.
     */
    public function set(
        string $key,
        mixed $value
    ): bool {

        $settings = $this->get_all();

        $settings[ $key ] = $value;

        return $this->update(
            $settings
        );
    }

    /**
     * Sanitize settings.
     *
     * @param array<string, mixed> $settings Settings.
     *
     * @return array<string, mixed>
     */
    private function sanitize( array $settings ): array {

        $defaults = $this->get_defaults();

        foreach ( $defaults as $key => $default ) {

            if ( ! array_key_exists( $key, $settings ) ) {
                $settings[ $key ] = $default;
            }

            switch ( $key ) {

                case 'business_name':
                case 'tagline':
                case 'phone':
                case 'address':
                    $settings[ $key ] = sanitize_text_field(
                        (string) $settings[ $key ]
                    );
                    break;

                case 'email':
                    $settings[ $key ] = sanitize_email(
                        (string) $settings[ $key ]
                    );
                    break;

                case 'whatsapp':
                    $settings[ $key ] = preg_replace(
                        '/[^0-9+]/',
                        '',
                        (string) $settings[ $key ]
                    );
                    break;

                case 'facebook':
                case 'instagram':
                case 'youtube':
                case 'linkedin':
                case 'twitter':
                    $settings[ $key ] = esc_url_raw(
                        (string) $settings[ $key ]
                    );
                    break;

                case 'logo_id':
                case 'favicon_id':
                    $settings[ $key ] = absint(
                        $settings[ $key ]
                    );
                    break;

                case 'primary_color':
                case 'secondary_color':
                case 'accent_color':

                    $color = sanitize_hex_color(
                        (string) $settings[ $key ]
                    );

                    $settings[ $key ] = $color ?: $default;
                    break;

                case 'show_phone':
                case 'show_email':
                case 'show_address':
                case 'show_whatsapp':
                    $settings[ $key ] = ! empty(
                        $settings[ $key ]
                    );
                    break;

                case 'require_consultation_payment':
                    $settings[ $key ] = ! empty(
                        $settings[ $key ]
                    );
                    break;

                case 'consultation_fee':

                    /*
                     * Store the fee as a normalized decimal string
                     * (or empty when not set). Never trust raw input.
                     */
                    $fee = is_scalar( $settings[ $key ] )
                        ? (string) $settings[ $key ]
                        : '';

                    $fee = preg_replace( '/[^0-9.]/', '', $fee );

                    $is_valid_fee = ( '' !== $fee && is_numeric( $fee ) );

                    $settings[ $key ] = $is_valid_fee
                        ? (string) ( 0 + $fee )
                        : '';
                    break;

                case 'notification_email':
                    $settings[ $key ] = sanitize_email(
                        (string) $settings[ $key ]
                    );
                    break;

                case 'consultation_currency':

                    $currency = strtoupper(
                        preg_replace(
                            '/[^A-Za-z]/',
                            '',
                            (string) $settings[ $key ]
                        )
                    );

                    $settings[ $key ] = ( '' === $currency )
                        ? $default
                        : substr( $currency, 0, 3 );
                    break;
            }
        }

        return $settings;
    }

    /**
     * Get option name.
     */
    public function get_option_name(): string {

        return self::OPTION_NAME;
    }

    /**
     * Reset settings to defaults.
     */
    public function reset(): bool {

        return update_option(
            self::OPTION_NAME,
            $this->get_defaults()
        );
    }
}
