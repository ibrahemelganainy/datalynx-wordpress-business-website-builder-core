<?php

namespace BusinessBuilderCore\Packs\LawFirm\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lawyer {

    /**
     * Post type slug.
     */
    private const POST_TYPE = 'bb_lawyer';

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
     * Register Lawyer post type.
     */
    public function register_post_type(): void {

        $labels = array(

            'name' => _x(
                'Lawyers',
                'post type general name',
                'business-builder'
            ),

            'singular_name' => _x(
                'Lawyer',
                'post type singular name',
                'business-builder'
            ),

            'menu_name' => __(
                'Lawyers',
                'business-builder'
            ),

            'name_admin_bar' => __(
                'Lawyer',
                'business-builder'
            ),

            'add_new' => __(
                'Add New',
                'business-builder'
            ),

            'add_new_item' => __(
                'Add New Lawyer',
                'business-builder'
            ),

            'new_item' => __(
                'New Lawyer',
                'business-builder'
            ),

            'edit_item' => __(
                'Edit Lawyer',
                'business-builder'
            ),

            'view_item' => __(
                'View Lawyer',
                'business-builder'
            ),

            'all_items' => __(
                'All Lawyers',
                'business-builder'
            ),

            'search_items' => __(
                'Search Lawyers',
                'business-builder'
            ),

            'not_found' => __(
                'No lawyers found.',
                'business-builder'
            ),

            'not_found_in_trash' => __(
                'No lawyers found in Trash.',
                'business-builder'
            ),

            'featured_image' => __(
                'Lawyer Photo',
                'business-builder'
            ),

            'set_featured_image' => __(
                'Set Lawyer Photo',
                'business-builder'
            ),

            'remove_featured_image' => __(
                'Remove Lawyer Photo',
                'business-builder'
            ),

            'use_featured_image' => __(
                'Use as Lawyer Photo',
                'business-builder'
            ),

            'archives' => __(
                'Lawyer Archives',
                'business-builder'
            ),

            'attributes' => __(
                'Lawyer Attributes',
                'business-builder'
            ),

            'insert_into_item' => __(
                'Insert into lawyer',
                'business-builder'
            ),

            'uploaded_to_this_item' => __(
                'Uploaded to this lawyer',
                'business-builder'
            ),

            'filter_items_list' => __(
                'Filter lawyers list',
                'business-builder'
            ),

            'items_list_navigation' => __(
                'Lawyers list navigation',
                'business-builder'
            ),

            'items_list' => __(
                'Lawyers list',
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
                'slug' => 'lawyers',
            ),

            'supports' => array(
                'title',
                'editor',
                'thumbnail',
                'excerpt',
                'page-attributes',
            ),

            'menu_icon' => 'dashicons-businessperson',

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