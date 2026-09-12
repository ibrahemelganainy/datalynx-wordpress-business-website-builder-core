<?php

namespace BusinessBuilderCore\Core;

use BusinessBuilderCore\Settings\BusinessType;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PackManager {

    /**
     * Business type manager.
     */
    protected BusinessType $business_type;

    /**
     * Dependency injection container.
     */
    protected Container $container;

    /**
     * Registered business packs.
     *
     * @var array<string, string>
     */
    protected array $packs = array();

    /**
     * Loaded pack instances.
     *
     * @var array<string, object>
     */
    protected array $instances = array();

    /**
     * Constructor.
     *
     * @param BusinessType $business_type
     * @param Container    $container
     */
    public function __construct(
        BusinessType $business_type,
        Container $container
    ) {

        $this->business_type = $business_type;

        $this->container = $container;
    }

    /**
     * Register a business pack.
     *
     * @param string $slug
     * @param string $class
     *
     * @return bool
     */
    public function register(
        string $slug,
        string $class
    ): bool {

        $slug = sanitize_key(
            $slug
        );

        if (
            empty( $slug )
            || empty( $class )
        ) {
            return false;
        }

        /*
         * Make sure the class exists.
         *
         * We do not require it immediately if the
         * autoloader can load it later.
         */
        if ( ! class_exists( $class ) ) {

            return false;
        }

        $this->packs[ $slug ] = $class;

        return true;
    }

    /**
     * Get all registered packs.
     *
     * @return array<string, string>
     */
    public function get_all(): array {

        return $this->packs;
    }

    /**
     * Get a registered pack class.
     *
     * @param string $slug
     *
     * @return string|null
     */
    public function get(
        string $slug
    ): ?string {

        $slug = sanitize_key(
            $slug
        );

        return $this->packs[ $slug ] ?? null;
    }

    /**
     * Check whether a pack is registered.
     *
     * @param string $slug
     */
    public function exists(
        string $slug
    ): bool {

        $slug = sanitize_key(
            $slug
        );

        return isset(
            $this->packs[ $slug ]
        );
    }

    /**
     * Get an already-created pack instance.
     *
     * @param string $slug
     *
     * @return object|null
     */
    public function get_instance(
        string $slug
    ): ?object {

        $slug = sanitize_key(
            $slug
        );

        return $this->instances[ $slug ] ?? null;
    }

    /**
     * Boot the current business pack.
     *
     * The current business type determines which
     * pack should be loaded.
     */
    public function boot_current(): void {

        $current_type =
            $this->business_type->get_current();

        /*
         * No business type has been selected yet.
         */
        if ( empty( $current_type ) ) {
            return;
        }

        $this->boot(
            $current_type
        );
    }

    /**
     * Boot a specific business pack.
     *
     * @param string $slug
     *
     * @return bool
     */
    public function boot(
        string $slug
    ): bool {

        $slug = sanitize_key(
            $slug
        );

        /*
         * The pack must be registered.
         */
        if ( ! $this->exists( $slug ) ) {
            return false;
        }

        /*
         * If the pack has already been instantiated,
         * do not instantiate it again.
         */
        if ( isset( $this->instances[ $slug ] ) ) {

            return true;
        }

        $pack_class = $this->get(
            $slug
        );

        if ( empty( $pack_class ) ) {
            return false;
        }

        /*
         * Create the pack through the Container.
         *
         * Example:
         *
         * LawFirmPack::__construct(
         *     SectionRegistry $section_registry
         * )
         *
         * The Container automatically resolves
         * SectionRegistry.
         */
        try {

            $pack = $this->container->make(
                $pack_class
            );

        } catch ( \Throwable $exception ) {

            /*
             * Log the error when WP_DEBUG is enabled.
             */
            if (
                defined( 'WP_DEBUG' )
                && WP_DEBUG
            ) {

                error_log(
                    sprintf(
                        'Business Builder Pack Error [%s]: %s',
                        $slug,
                        $exception->getMessage()
                    )
                );
            }

            return false;
        }

        /*
         * Store the instance.
         */
        $this->instances[ $slug ] = $pack;

        /*
         * Register the pack.
         */
        if (
            method_exists(
                $pack,
                'register'
            )
        ) {

            $pack->register();
        }

        /*
         * Boot runtime functionality.
         */
        if (
            method_exists(
                $pack,
                'boot'
            )
        ) {

            $pack->boot();
        }

        return true;
    }

    /**
     * Get the container.
     *
     * @return Container
     */
    public function get_container(): Container {

        return $this->container;
    }

    /**
     * Get the business type manager.
     *
     * @return BusinessType
     */
    public function get_business_type(): BusinessType {

        return $this->business_type;
    }
}