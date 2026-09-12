<?php

namespace BusinessBuilderCore\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Container {

    /**
     * Shared service instances.
     *
     * @var array<string, mixed>
     */
    protected array $services = array();

    /**
     * Registered factories.
     *
     * @var array<string, callable>
     */
    protected array $factories = array();

    /**
     * Store a shared service instance.
     *
     * @param string $id
     * @param mixed  $service
     */
    public function set(
        string $id,
        mixed $service
    ): void {

        $this->services[ $id ] = $service;
    }

    /**
     * Check whether a service is registered.
     *
     * @param string $id
     */
    public function has(
        string $id
    ): bool {

        return isset( $this->services[ $id ] )
            || isset( $this->factories[ $id ] );
    }

    /**
     * Register a factory.
     *
     * The factory will be executed only when the service
     * is requested for the first time.
     *
     * @param string   $id
     * @param callable $factory
     */
    public function factory(
        string $id,
        callable $factory
    ): void {

        $this->factories[ $id ] = $factory;
    }

    /**
     * Resolve a service.
     *
     * @param string $id
     *
     * @return mixed
     */
    public function get(
        string $id
    ): mixed {

        /*
         * Return an already-created shared service.
         */
        if ( isset( $this->services[ $id ] ) ) {
            return $this->services[ $id ];
        }

        /*
         * Resolve a registered factory.
         */
        if ( isset( $this->factories[ $id ] ) ) {

            $service = call_user_func(
                $this->factories[ $id ],
                $this
            );

            /*
             * Factories are treated as shared services
             * after their first resolution.
             */
            $this->services[ $id ] = $service;

            return $service;
        }

        /*
         * If the ID is a class name, attempt automatic
         * dependency resolution.
         */
        if ( class_exists( $id ) ) {
            return $this->make( $id );
        }

        throw new \RuntimeException(
            sprintf(
                'Business Builder Container: Service "%s" could not be resolved.',
                $id
            )
        );
    }

    /**
     * Automatically instantiate a class and resolve
     * its constructor dependencies.
     *
     * @param string $class
     *
     * @return object
     */
    public function make(
        string $class
    ): object {

        if ( ! class_exists( $class ) ) {

            throw new \RuntimeException(
                sprintf(
                    'Business Builder Container: Class "%s" does not exist.',
                    $class
                )
            );
        }

        $reflection = new \ReflectionClass(
            $class
        );

        /*
         * The class cannot be instantiated.
         */
        if ( ! $reflection->isInstantiable() ) {

            throw new \RuntimeException(
                sprintf(
                    'Business Builder Container: Class "%s" is not instantiable.',
                    $class
                )
            );
        }

        $constructor = $reflection->getConstructor();

        /*
         * No constructor means no dependencies.
         */
        if ( null === $constructor ) {

            return $reflection->newInstance();
        }

        $parameters = $constructor->getParameters();

        /*
         * No constructor parameters.
         */
        if ( empty( $parameters ) ) {

            return $reflection->newInstance();
        }

        $dependencies = array();

        foreach ( $parameters as $parameter ) {

            $dependency = $parameter->getType();

            /*
             * We currently support class/interface dependencies.
             */
            if (
                null === $dependency
                || $dependency->isBuiltin()
            ) {

                /*
                 * Use the default value if one exists.
                 */
                if ( $parameter->isDefaultValueAvailable() ) {

                    $dependencies[] =
                        $parameter->getDefaultValue();

                    continue;
                }

                throw new \RuntimeException(
                    sprintf(
                        'Business Builder Container: Cannot resolve parameter "$%s" of class "%s".',
                        $parameter->getName(),
                        $class
                    )
                );
            }

            /*
             * Handle named class dependencies.
             */
            if ( $dependency instanceof \ReflectionNamedType ) {

                $dependency_class =
                    $dependency->getName();

                $dependencies[] = $this->get(
                    $dependency_class
                );

                continue;
            }

            /*
             * Union types are not automatically resolved.
             */
            if ( $parameter->isDefaultValueAvailable() ) {

                $dependencies[] =
                    $parameter->getDefaultValue();

                continue;
            }

            throw new \RuntimeException(
                sprintf(
                    'Business Builder Container: Unsupported dependency type for parameter "$%s" of class "%s".',
                    $parameter->getName(),
                    $class
                )
            );
        }

        return $reflection->newInstanceArgs(
            $dependencies
        );
    }

    /**
     * Resolve a class and keep the resulting instance
     * as a shared service.
     *
     * @param string $class
     *
     * @return object
     */
    public function singleton(
        string $class
    ): object {

        if ( isset( $this->services[ $class ] ) ) {
            return $this->services[ $class ];
        }

        $instance = $this->make(
            $class
        );

        $this->services[ $class ] = $instance;

        return $instance;
    }

    /**
     * Remove a registered service.
     *
     * @param string $id
     */
    public function remove(
        string $id
    ): void {

        unset(
            $this->services[ $id ],
            $this->factories[ $id ]
        );
    }

    /**
     * Get all registered service IDs.
     *
     * @return array<int, string>
     */
    public function get_registered(): array {

        return array_values(
            array_unique(
                array_merge(
                    array_keys( $this->services ),
                    array_keys( $this->factories )
                )
            )
        );
    }
}