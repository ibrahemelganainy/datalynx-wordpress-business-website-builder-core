<?php

namespace BusinessBuilderCore\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BusinessType {

    /**
     * WordPress option name.
     */
    private const OPTION_NAME = 'bb_business_type';

    /**
     * Registered business types.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $types = array();

    /**
     * Constructor.
     */
    public function __construct() {
        $this->register_default_types();
    }

    /**
     * Register default business types.
     */
    private function register_default_types(): void {

        $this->register(
            'law_firm',
            array(
                'name'        => 'Law Firm',
                'label'       => 'Law Firm',
                'description' => 'Website for law firms and legal professionals.',
                'pack'        => 'LawFirm',
                'enabled'     => true,
            )
        );

        $this->register(
            'medical',
            array(
                'name'        => 'Medical',
                'label'       => 'Medical',
                'description' => 'Website for doctors, clinics and medical businesses.',
                'pack'        => 'Medical',
                'enabled'     => true,
            )
        );

        $this->register(
            'real_estate',
            array(
                'name'        => 'Real Estate',
                'label'       => 'Real Estate',
                'description' => 'Website for real estate businesses.',
                'pack'        => 'RealEstate',
                'enabled'     => true,
            )
        );

        $this->register(
            'education',
            array(
                'name'        => 'Education',
                'label'       => 'Education',
                'description' => 'Website for schools, teachers and education businesses.',
                'pack'        => 'Education',
                'enabled'     => true,
            )
        );
    }

    /**
     * Register a business type.
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
            'label'       => $slug,
            'description' => '',
            'pack'        => '',
            'enabled'     => true,
        );

        $this->types[ $slug ] = wp_parse_args(
            $args,
            $defaults
        );

        return true;
    }

    /**
     * Get all registered business types.
     *
     * @return array<string, array<string, mixed>>
     */
    public function get_all(): array {
        return $this->types;
    }

    /**
     * Get a single business type.
     */
    public function get( string $slug ): ?array {

        $slug = sanitize_key( $slug );

        if ( ! isset( $this->types[ $slug ] ) ) {
            return null;
        }

        return $this->types[ $slug ];
    }

    /**
     * Check if a business type exists.
     */
    public function exists( string $slug ): bool {

        $slug = sanitize_key( $slug );

        return isset( $this->types[ $slug ] );
    }

    /**
     * Get the current site's business type.
     */
    public function get_current(): ?string {

        $business_type = get_option(
            self::OPTION_NAME,
            ''
        );

        if ( empty( $business_type ) ) {
            return null;
        }

        if ( ! $this->exists( $business_type ) ) {
            return null;
        }

        return $business_type;
    }

    /**
     * Set the current site's business type.
     */
    public function set_current( string $slug ): bool {

        $slug = sanitize_key( $slug );

        if ( ! $this->exists( $slug ) ) {
            return false;
        }

        return update_option(
            self::OPTION_NAME,
            $slug
        );
    }

    /**
     * Get the current business type configuration.
     */
    public function get_current_config(): ?array {

        $current = $this->get_current();

        if ( null === $current ) {
            return null;
        }

        return $this->get( $current );
    }

    /**
     * Get the option name.
     */
    public function get_option_name(): string {
        return self::OPTION_NAME;
    }
}