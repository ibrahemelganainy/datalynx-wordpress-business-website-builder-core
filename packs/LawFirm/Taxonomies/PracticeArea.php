<?php

namespace BusinessBuilderCore\Packs\LawFirm\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PracticeArea {

    /**
     * Taxonomy slug.
     */
    private const TAXONOMY = 'bb_practice_area';

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
     * Register Practice Area taxonomy.
     */
    public function register_taxonomy(): void {

        $labels = array(

            'name' => _x(
                'Practice Areas',
                'taxonomy general name',
                'business-builder'
            ),

            'singular_name' => _x(
                'Practice Area',
                'taxonomy singular name',
                'business-builder'
            ),

            'search_items' => __(
                'Search Practice Areas',
                'business-builder'
            ),

            'all_items' => __(
                'All Practice Areas',
                'business-builder'
            ),

            'parent_item' => __(
                'Parent Practice Area',
                'business-builder'
            ),

            'parent_item_colon' => __(
                'Parent Practice Area:',
                'business-builder'
            ),

            'edit_item' => __(
                'Edit Practice Area',
                'business-builder'
            ),

            'view_item' => __(
                'View Practice Area',
                'business-builder'
            ),

            'update_item' => __(
                'Update Practice Area',
                'business-builder'
            ),

            'add_new_item' => __(
                'Add New Practice Area',
                'business-builder'
            ),

            'new_item_name' => __(
                'New Practice Area Name',
                'business-builder'
            ),

            'menu_name' => __(
                'Practice Areas',
                'business-builder'
            ),

            'not_found' => __(
                'No practice areas found.',
                'business-builder'
            ),
        );

        $args = array(

            'labels' => $labels,

            'public' => true,

            'show_ui' => true,

            /* Relocated under the independent Law Firm Dashboard menu. */
            'show_in_menu' => \BusinessBuilderCore\Packs\LawFirm\Admin\DashboardMenu::MENU_SLUG,

            'show_admin_column' => true,

            'show_in_rest' => true,

            'hierarchical' => true,

            'rewrite' => array(
                'slug' => 'practice-areas',
            ),

            'show_in_nav_menus' => true,

            'show_tagcloud' => false,
        );

        register_taxonomy(
            self::TAXONOMY,
            array(
                'bb_lawyer',
                'bb_legal_service',
            ),
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