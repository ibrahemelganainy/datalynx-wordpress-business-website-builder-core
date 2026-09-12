<?php

namespace BusinessBuilderCore\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Language {

    /**
     * Supported languages.
     */
    private array $languages = array();

    /**
     * Option name.
     */
    private const OPTION_NAME = 'bb_language_settings';

    /**
     * Constructor.
     */
    public function __construct() {

        $this->register_default_languages();
    }

    /**
     * Register default languages.
     */
    private function register_default_languages(): void {

        $this->register(
            'ar',
            array(
                'name'        => 'Arabic',
                'native_name' => 'العربية',
                'direction'   => 'rtl',
                'enabled'     => true,
            )
        );

        $this->register(
            'en',
            array(
                'name'        => 'English',
                'native_name' => 'English',
                'direction'   => 'ltr',
                'enabled'     => true,
            )
        );
    }

    /**
     * Register language.
     */
    public function register(
        string $slug,
        array $args = array()
    ): bool {

        $slug = sanitize_key( $slug );

        if ( empty( $slug ) ) {
            return false;
        }

        $defaults = array(
            'name'        => $slug,
            'native_name' => $slug,
            'direction'   => 'ltr',
            'enabled'     => true,
        );

        $this->languages[ $slug ] = wp_parse_args(
            $args,
            $defaults
        );

        return true;
    }

    /**
     * Get all languages.
     */
    public function get_all(): array {

        return $this->languages;
    }

    /**
     * Get one language.
     */
    public function get( string $slug ): ?array {

        $slug = sanitize_key( $slug );

        return $this->languages[ $slug ] ?? null;
    }

    /**
     * Check language.
     */
    public function exists( string $slug ): bool {

        $slug = sanitize_key( $slug );

        return isset( $this->languages[ $slug ] );
    }

    /**
     * Get enabled languages.
     */
    public function get_enabled(): array {

        return array_filter(
            $this->languages,
            function ( array $language ): bool {
                return ! empty( $language['enabled'] );
            }
        );
    }

    /**
     * Get default language.
     */
    public function get_default(): string {

        $settings = get_option(
            self::OPTION_NAME,
            array()
        );

        if (
            is_array( $settings )
            && ! empty( $settings['default_language'] )
            && $this->exists( $settings['default_language'] )
        ) {
            return $settings['default_language'];
        }

        return 'ar';
    }

    /**
     * Set default language.
     */
    public function set_default( string $language ): bool {

        $language = sanitize_key( $language );

        if ( ! $this->exists( $language ) ) {
            return false;
        }

        $settings = $this->get_settings();

        $settings['default_language'] = $language;

        return update_option(
            self::OPTION_NAME,
            $settings
        );
    }

    /**
     * Get language settings.
     */
    public function get_settings(): array {

        $saved = get_option(
            self::OPTION_NAME,
            array()
        );

        if ( ! is_array( $saved ) ) {
            $saved = array();
        }

        return wp_parse_args(
            $saved,
            array(
                'default_language' => 'ar',
            )
        );
    }

    /**
     * Get language direction.
     */
    public function get_direction(
        string $language
    ): string {

        $config = $this->get( $language );

        if ( null === $config ) {
            return 'ltr';
        }

        return $config['direction'];
    }

    /**
     * Get native language name.
     */
    public function get_native_name(
        string $language
    ): string {

        $config = $this->get( $language );

        if ( null === $config ) {
            return $language;
        }

        return $config['native_name'];
    }
}