<?php

namespace BusinessBuilderCore\Packs\LawFirm\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LegalService {

    /**
     * Post type slug.
     */
    private const POST_TYPE = 'bb_legal_service';

    /**
     * Register hooks.
     */
    public function register(): void {

        add_action(
            'init',
            array( $this, 'register_post_type' )
        );
    }

    /**
     * Register Legal Service post type.
     */
    public function register_post_type(): void {

        $labels = array(

            'name' => _x(
                'Legal Services',
                'post type general name',
                'business-builder'
            ),

            'singular_name' => _x(
                'Legal Service',
                'post type singular name',
                'business-builder'
            ),

            'menu_name' => __(
                'Legal Services',
                'business-builder'
            ),

            'name_admin_bar' => __(
                'Legal Service',
                'business-builder'
            ),

            'add_new' => __(
                'Add New',
                'business-builder'
            ),

            'add_new_item' => __(
                'Add New Legal Service',
                'business-builder'
            ),

            'new_item' => __(
                'New Legal Service',
                'business-builder'
            ),

            'edit_item' => __(
                'Edit Legal Service',
                'business-builder'
            ),

            'view_item' => __(
                'View Legal Service',
                'business-builder'
            ),

            'all_items' => __(
                'All Legal Services',
                'business-builder'
            ),

            'search_items' => __(
                'Search Legal Services',
                'business-builder'
            ),

            'not_found' => __(
                'No legal services found.',
                'business-builder'
            ),

            'not_found_in_trash' => __(
                'No legal services found in Trash.',
                'business-builder'
            ),

            'featured_image' => __(
                'Service Image',
                'business-builder'
            ),

            'set_featured_image' => __(
                'Set Service Image',
                'business-builder'
            ),

            'remove_featured_image' => __(
                'Remove Service Image',
                'business-builder'
            ),

            'use_featured_image' => __(
                'Use as Service Image',
                'business-builder'
            ),

            'archives' => __(
                'Legal Service Archives',
                'business-builder'
            ),

            'attributes' => __(
                'Legal Service Attributes',
                'business-builder'
            ),

            'insert_into_item' => __(
                'Insert into legal service',
                'business-builder'
            ),

            'uploaded_to_this_item' => __(
                'Uploaded to this legal service',
                'business-builder'
            ),

            'filter_items_list' => __(
                'Filter legal services list',
                'business-builder'
            ),

            'items_list_navigation' => __(
                'Legal services list navigation',
                'business-builder'
            ),

            'items_list' => __(
                'Legal services list',
                'business-builder'
            ),
        );

        $args = array(

            'labels' => $labels,

            'public' => true,

            'show_ui' => true,

            'show_in_menu' => true,

            'show_in_admin_bar' => true,

            'show_in_rest' => true,

            'has_archive' => true,

            'rewrite' => array(
                'slug' => 'legal-services',
            ),

            'supports' => array(
                'title',
                'editor',
                'thumbnail',
                'excerpt',
                'page-attributes',
            ),

            'menu_icon' => 'dashicons-portfolio',

            'publicly_queryable' => true,

            'query_var' => true,
        );

        register_post_type(
            self::POST_TYPE,
            $args
        );
    }

    /**
     * Get post type slug.
     */
    public function get_post_type(): string {

        return self::POST_TYPE;
    }
}