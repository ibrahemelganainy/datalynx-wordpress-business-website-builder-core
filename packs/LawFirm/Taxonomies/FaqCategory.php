<?php

namespace BusinessBuilderCore\Packs\LawFirm\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * FAQ Category taxonomy.
 *
 * A dedicated taxonomy for grouping `bb_faq` entries so a FAQ
 * section can be filtered by category. Uses its own taxonomy
 * rather than the global `category` so the LawFirm pack stays
 * self-contained and does not leak into (or depend on) the
 * site's general blog categories.
 */
class FaqCategory {

    /**
     * Taxonomy slug.
     */
    private const TAXONOMY = 'bb_faq_category';

    /**
     * Register hooks.
     */
    public function register(): void {

        add_action(
            'init',
            array( $this, 'register_taxonomy' )
        );
    }

    /**
     * Register the FAQ category taxonomy.
     */
    public function register_taxonomy(): void {

        $labels = array(
            'name' => _x(
                'FAQ Categories',
                'taxonomy general name',
                'business-builder'
            ),
            'singular_name' => _x(
                'FAQ Category',
                'taxonomy singular name',
                'business-builder'
            ),
            'search_items' => __(
                'Search FAQ Categories',
                'business-builder'
            ),
            'all_items' => __(
                'All FAQ Categories',
                'business-builder'
            ),
            'parent_item' => __(
                'Parent FAQ Category',
                'business-builder'
            ),
            'parent_item_colon' => __(
                'Parent FAQ Category:',
                'business-builder'
            ),
            'edit_item' => __(
                'Edit FAQ Category',
                'business-builder'
            ),
            'update_item' => __(
                'Update FAQ Category',
                'business-builder'
            ),
            'add_new_item' => __(
                'Add New FAQ Category',
                'business-builder'
            ),
            'new_item_name' => __(
                'New FAQ Category Name',
                'business-builder'
            ),
            'menu_name' => __(
                'FAQ Categories',
                'business-builder'
            ),
            'not_found' => __(
                'No FAQ categories found.',
                'business-builder'
            ),
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'hierarchical' => true,
            'rewrite' => array(
                'slug' => 'faq-category',
            ),
            'show_in_nav_menus' => false,
            'show_tagcloud' => false,
        );

        register_taxonomy(
            self::TAXONOMY,
            array( 'bb_faq' ),
            $args
        );
    }

    /**
     * Get taxonomy slug.
     */
    public function get_taxonomy(): string {

        return self::TAXONOMY;
    }
}
