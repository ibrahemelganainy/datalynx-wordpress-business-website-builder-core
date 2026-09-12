<?php

namespace BusinessBuilderCore\Packs\LawFirm\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Faq {

    /**
     * Post type slug.
     */
    private const POST_TYPE = 'bb_faq';

    /**
     * Register hooks.
     */
    public function register(): void {
        add_action( 'init', array( $this, 'register_post_type' ) );
    }

    /**
     * Register the FAQ post type.
     */
    public function register_post_type(): void {
        $labels = array(
            'name' => _x( 'FAQs', 'post type general name', 'business-builder' ),
            'singular_name' => _x( 'FAQ', 'post type singular name', 'business-builder' ),
            'menu_name' => __( 'FAQs', 'business-builder' ),
            'add_new' => __( 'Add New', 'business-builder' ),
            'add_new_item' => __( 'Add New FAQ', 'business-builder' ),
            'edit_item' => __( 'Edit FAQ', 'business-builder' ),
            'new_item' => __( 'New FAQ', 'business-builder' ),
            'view_item' => __( 'View FAQ', 'business-builder' ),
            'all_items' => __( 'All FAQs', 'business-builder' ),
            'search_items' => __( 'Search FAQs', 'business-builder' ),
            'not_found' => __( 'No FAQs found.', 'business-builder' ),
            'not_found_in_trash' => __( 'No FAQs found in Trash.', 'business-builder' ),
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'has_archive' => false,
            'supports' => array( 'title', 'editor', 'page-attributes' ),
            'menu_icon' => 'dashicons-editor-help',
            'rewrite' => array( 'slug' => 'faqs' ),
            'publicly_queryable' => true,
            'query_var' => true,
        );

        register_post_type( self::POST_TYPE, $args );
    }

    /**
     * Get post type slug.
     */
    public function get_post_type(): string {
        return self::POST_TYPE;
    }
}
