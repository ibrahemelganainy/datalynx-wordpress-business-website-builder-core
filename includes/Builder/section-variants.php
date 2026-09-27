<?php
/**
 * Section Variant public API.
 *
 * Thin, functional wrapper around the SectionVariants infrastructure so a pack
 * (or a core section) can register and render variants without touching the
 * builder internals.
 *
 * Registration (a pack registers its own domain variants):
 *
 *   add_action( 'bb_register_section_variants', function ( $variants ) {
 *       $variants->register_many( 'lawyers', array(
 *           'default' => __DIR__ . '/variants/lawyers/default.php',
 *           'list'    => __DIR__ . '/variants/lawyers/list.php',
 *       ) );
 *   } );
 *
 * Rendering (a section resolves then renders):
 *
 *   bb_render_section_variant( 'lawyers', $settings['variant'] ?? null, array(
 *       'items'    => $items,       // prepared data
 *       'settings' => $settings,
 *       'content'  => $content,
 *       'columns'  => 3,
 *       'type'     => 'lawyers',
 *   ) );
 *
 * @package BusinessBuilderCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'bb_section_variants' ) ) {

    /**
     * The shared SectionVariants registry (lazy-initialised, filterable).
     *
     * @return \BusinessBuilderCore\Builder\SectionVariants
     */
    function bb_section_variants(): \BusinessBuilderCore\Builder\SectionVariants {

        static $registry = null;

        if ( null === $registry ) {

            $registry = new \BusinessBuilderCore\Builder\SectionVariants();

            /**
             * Fires once so packs can register their own section variants.
             *
             * @param \BusinessBuilderCore\Builder\SectionVariants $registry Registry.
             */
            do_action( 'bb_register_section_variants', $registry );
        }

        return $registry;
    }
}

if ( ! function_exists( 'bb_resolve_section_variant' ) ) {

    /**
     * Resolve a requested section variant to a registered slug (safe fallback).
     *
     * @param string      $section_type Section slug.
     * @param string|null $requested    Requested variant.
     * @return string Registered variant slug (never a path).
     */
    function bb_resolve_section_variant( string $section_type, ?string $requested ): string {

        return bb_section_variants()->resolve( $section_type, $requested );
    }
}

if ( ! function_exists( 'bb_section_variant_options' ) ) {

    /**
     * The variant select options for a section type (slug => translated label).
     *
     * Only sections with a non-default variant return more than one option, so
     * the builder never shows a pointless single-choice dropdown.
     *
     * @param string $section_type Section slug.
     * @return array<string, string>
     */
    function bb_section_variant_options( string $section_type ): array {

        $registry = bb_section_variants();
        $slugs    = $registry->available( $section_type );

        if ( count( $slugs ) < 2 ) {
            return array();
        }

        /**
         * Filter the human labels for section variants.
         *
         * @param array<string, string> $labels         variant slug => label.
         * @param string                $section_type   Section slug.
         */
        $labels = (array) apply_filters(
            'bb_section_variant_labels',
            array(
                'default' => __( 'Default layout', 'business-builder' ),
                'list'    => __( 'List layout', 'business-builder' ),
                'featured' => __( 'Featured layout', 'business-builder' ),
            ),
            $section_type
        );

        $options = array();

        foreach ( $slugs as $slug ) {
            $options[ $slug ] = isset( $labels[ $slug ] ) ? $labels[ $slug ] : $slug;
        }

        return $options;
    }
}

if ( ! function_exists( 'bb_render_section_variant' ) ) {

    /**
     * Render a section's resolved variant template.
     *
     * The caller acquires and prepares the data ONCE, then hands it here;
     * variant templates never query the database.
     *
     * Fails safely: an unknown section/variant renders the default, and a
     * section with no registered variants renders nothing here (the caller's
     * default path is expected to handle that).
     *
     * @param string      $section_type Section slug (e.g. 'lawyers').
     * @param string|null $requested    Requested variant.
     * @param array       $args         Prepared presentation data for the variant.
     * @return bool True when a variant template rendered.
     */
    function bb_render_section_variant( string $section_type, ?string $requested, array $args = array() ): bool {

        /*
         * Section variants render through the Phase-10 component API. When it
         * is unavailable (the plugin running without the canonical theme), do
         * NOT render: return false so the caller's own default markup path
         * runs instead of fatally calling an undefined function (§46/§53).
         */
        if ( ! function_exists( 'bb_component' ) && ! function_exists( 'bb_render_component' ) ) {
            return false;
        }

        $template = bb_section_variants()->template( $section_type, $requested );

        if ( '' === $template || ! is_readable( $template ) ) {
            return false;
        }

        $variant = bb_section_variants()->resolve( $section_type, $requested );

        /**
         * Fires immediately before a section variant renders.
         *
         * @param string $section_type Section slug.
         * @param string $variant      Resolved variant slug.
         * @param array  $args         Variant arguments.
         */
        do_action( 'bb_before_section_variant', $section_type, $variant, $args );

        /*
         * The template receives $args in its own scope. It must treat $args as
         * prepared, presentation-ready data: no queries, no business logic.
         */
        include $template;

        /**
         * Fires immediately after a section variant renders.
         *
         * @param string $section_type Section slug.
         * @param string $variant      Resolved variant slug.
         * @param array  $args         Variant arguments.
         */
        do_action( 'bb_after_section_variant', $section_type, $variant, $args );

        return true;
    }
}