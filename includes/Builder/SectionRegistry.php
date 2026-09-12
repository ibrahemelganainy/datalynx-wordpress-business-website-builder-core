<?php

namespace BusinessBuilderCore\Builder;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SectionRegistry {

    /**
     * Registered sections.
     */
    protected array $sections = array();

    /**
     * Register a section.
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
            'category'    => 'general',
            'icon'        => 'dashicons-layout',
            'supports'    => array(),

            /*
             * Settings schema.
             *
             * Example:
             *
             * 'alignment' => array(
             *     'type'    => 'select',
             *     'label'   => 'Alignment',
             *     'default' => 'center',
             *     'options' => array(
             *         'left'   => 'Left',
             *         'center' => 'Center',
             *         'right'  => 'Right',
             *     ),
             * ),
             */
            'settings'    => array(),

            /*
             * Content schema.
             */
            'content'     => array(),

            /*
             * Optional frontend render callback.
             */
            'render'      => null,

            'enabled'     => true,
        );

        $config = wp_parse_args(
            $args,
            $defaults
        );

        if ( ! is_array( $config['supports'] ) ) {
            $config['supports'] = array();
        }

        if ( ! is_array( $config['settings'] ) ) {
            $config['settings'] = array();
        }

        if ( ! is_array( $config['content'] ) ) {
            $config['content'] = array();
        }

        /*
         * Normalize field schemas.
         */
        $config['settings'] = $this->normalize_schema(
            $config['settings']
        );

        $config['content'] = $this->normalize_schema(
            $config['content']
        );

        $this->sections[ $slug ] = $config;

        return true;
    }

    /**
     * Normalize a section field schema.
     */
    private function normalize_schema(
        array $schema
    ): array {

        $normalized = array();

        foreach ( $schema as $key => $field ) {

            /*
             * Backward compatibility:
             *
             * If the section currently contains:
             *
             * 'alignment' => 'center'
             *
             * convert it internally to:
             *
             * 'alignment' => array(
             *     'type'    => 'select',
             *     'default' => 'center',
             * )
             */
            if ( ! is_array( $field ) ) {

                $normalized[ $key ] = array(
                    'type'    => $this->guess_field_type(
                        $field
                    ),
                    'default' => $field,
                    'label'   => ucwords(
                        str_replace(
                            '_',
                            ' ',
                            (string) $key
                        )
                    ),
                );

                continue;
            }

            $defaults = array(
                'type'        => 'text',
                'label'       => ucwords(
                    str_replace(
                        '_',
                        ' ',
                        (string) $key
                    )
                ),
                'description' => '',
                'default'     => '',
                'placeholder' => '',
                'options'     => array(),
                'required'    => false,
                'min'         => null,
                'max'         => null,
                'step'        => null,
                'rows'        => 4,
                'multiple'    => false,
                'fields'      => array(),
            );

            $field = wp_parse_args(
                $field,
                $defaults
            );

            $field['type'] = sanitize_key(
                (string) $field['type']
            );

            if ( ! is_array( $field['options'] ) ) {
                $field['options'] = array();
            }

            if ( ! is_array( $field['fields'] ) ) {
                $field['fields'] = array();
            }

            $field['fields'] = $this->normalize_schema(
                $field['fields']
            );

            $normalized[ $key ] = $field;
        }

        return $normalized;
    }

    /**
     * Guess field type for legacy definitions.
     */
    private function guess_field_type(
        mixed $value
    ): string {

        if ( is_bool( $value ) ) {
            return 'checkbox';
        }

        if ( is_int( $value ) || is_float( $value ) ) {
            return 'number';
        }

        if ( is_array( $value ) ) {
            return 'repeater';
        }

        if ( is_string( $value ) ) {

            if (
                filter_var(
                    $value,
                    FILTER_VALIDATE_URL
                )
            ) {
                return 'url';
            }

            if (
                preg_match(
                    '/^#[0-9a-fA-F]{6}$/',
                    $value
                )
            ) {
                return 'color';
            }
        }

        return 'text';
    }

    /**
     * Unregister a section.
     */
    public function unregister(
        string $slug
    ): bool {

        $slug = sanitize_key( $slug );

        if ( ! isset( $this->sections[ $slug ] ) ) {
            return false;
        }

        unset(
            $this->sections[ $slug ]
        );

        return true;
    }

    /**
     * Check whether a section exists.
     */
    public function exists(
        string $slug
    ): bool {

        $slug = sanitize_key( $slug );

        return isset(
            $this->sections[ $slug ]
        );
    }

    /**
     * Get one section.
     */
    public function get(
        string $slug
    ): ?array {

        $slug = sanitize_key( $slug );

        return $this->sections[ $slug ] ?? null;
    }

    /**
     * Get all registered sections.
     */
    public function get_all(): array {

        return $this->sections;
    }

    /**
     * Get enabled sections.
     */
    public function get_enabled(): array {

        return array_filter(
            $this->sections,
            function ( array $section ): bool {

                return ! empty(
                    $section['enabled']
                );
            }
        );
    }

    /**
     * Get sections by category.
     */
    public function get_by_category(
        string $category
    ): array {

        $category = sanitize_key(
            $category
        );

        return array_filter(
            $this->sections,
            function ( array $section ) use ( $category ): bool {

                return isset(
                    $section['category']
                )
                && $section['category'] === $category;
            }
        );
    }

    /**
     * Get section categories.
     */
    public function get_categories(): array {

        $categories = array();

        foreach ( $this->sections as $section ) {

            if ( empty( $section['category'] ) ) {
                continue;
            }

            $category = sanitize_key(
                $section['category']
            );

            $categories[ $category ] = $category;
        }

        return array_values(
            $categories
        );
    }

    /**
     * Get content schema for a section.
     */
    public function get_content_schema(
        string $slug
    ): array {

        $section = $this->get(
            $slug
        );

        if ( null === $section ) {
            return array();
        }

        return $section['content'] ?? array();
    }

    /**
     * Get settings schema for a section.
     */
    public function get_settings_schema(
        string $slug
    ): array {

        $section = $this->get(
            $slug
        );

        if ( null === $section ) {
            return array();
        }

        return $section['settings'] ?? array();
    }

    /**
     * Get complete editor schema for a section.
     */
    public function get_editor_schema(
        string $slug
    ): array {

        $section = $this->get(
            $slug
        );

        if ( null === $section ) {
            return array();
        }

        return array(
            'content'  => $section['content'] ?? array(),
            'settings' => $section['settings'] ?? array(),
        );
    }
}