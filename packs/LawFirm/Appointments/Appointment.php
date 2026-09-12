<?php

namespace BusinessBuilderCore\Packs\LawFirm\Appointments;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Appointment post type.
 *
 * Private CPT (no front-end single/archive) so bookings are managed from
 * the admin. Stored in the posts table => per-site on Multisite, no
 * custom tables (spec 19/23/30).
 */
class Appointment {

    /**
     * Post type slug.
     */
    public const POST_TYPE = 'bb_appointment';

    /**
     * Register hooks.
     */
    public function register(): void {

        add_action( 'init', array( $this, 'register_post_type' ) );
    }

    /**
     * Register the appointment post type.
     */
    public function register_post_type(): void {

        $labels = array(
            'name'               => _x( 'Appointments', 'post type general name', 'business-builder' ),
            'singular_name'      => _x( 'Appointment', 'post type singular name', 'business-builder' ),
            'menu_name'          => __( 'Appointments', 'business-builder' ),
            'add_new'            => __( 'Add New', 'business-builder' ),
            'add_new_item'       => __( 'Add New Appointment', 'business-builder' ),
            'edit_item'          => __( 'Edit Appointment', 'business-builder' ),
            'new_item'           => __( 'New Appointment', 'business-builder' ),
            'view_item'          => __( 'View Appointment', 'business-builder' ),
            'all_items'          => __( 'Appointments', 'business-builder' ),
            'search_items'       => __( 'Search Appointments', 'business-builder' ),
            'not_found'          => __( 'No appointments found.', 'business-builder' ),
            'not_found_in_trash' => __( 'No appointments found in Trash.', 'business-builder' ),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => \BusinessBuilderCore\Packs\LawFirm\Admin\DashboardMenu::MENU_SLUG,
            'show_in_rest'        => false,
            'has_archive'         => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'capability_type'     => 'post',
            'capabilities'        => array(
                'create_posts' => 'do_not_allow',
            ),
            'map_meta_cap'        => true,
            'supports'            => array( 'title' ),
            'menu_icon'           => 'dashicons-calendar-alt',
            'rewrite'             => false,
        );

        register_post_type( self::POST_TYPE, $args );
    }
}
