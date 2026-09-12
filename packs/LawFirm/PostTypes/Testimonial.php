<?php

namespace BusinessBuilderCore\Packs\LawFirm\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Testimonial {

    /**
     * Post type slug.
     */
    private const POST_TYPE = 'bb_testimonial';

    /**
     * Register hooks.
     */
    public function register(): void {
        add_action( 'init', array( $this, 'register_post_type' ) );
    }

    /**
     * Register the testimonial post type.
     */
    public function register_post_type(): void {
        $labels = array(
            'name' => _x( 'Testimonials', 'post type general name', 'business-builder' ),
            'singular_name' => _x( 'Testimonial', 'post type singular name', 'business-builder' ),
            'menu_name' => __( 'Testimonials', 'business-builder' ),
            'add_new' => __( 'Add New', 'business-builder' ),
            'add_new_item' => __( 'Add New Testimonial', 'business-builder' ),
            'edit_item' => __( 'Edit Testimonial', 'business-builder' ),
            'new_item' => __( 'New Testimonial', 'business-builder' ),
            'view_item' => __( 'View Testimonial', 'business-builder' ),
            'all_items' => __( 'All Testimonials', 'business-builder' ),
            'search_items' => __( 'Search Testimonials', 'business-builder' ),
            'not_found' => __( 'No testimonials found.', 'business-builder' ),
            'not_found_in_trash' => __( 'No testimonials found in Trash.', 'business-builder' ),
            'featured_image' => __( 'Client Photo', 'business-builder' ),
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'has_archive' => false,
            'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
            'menu_icon' => 'dashicons-format-quote',
            'rewrite' => array( 'slug' => 'testimonials' ),
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
