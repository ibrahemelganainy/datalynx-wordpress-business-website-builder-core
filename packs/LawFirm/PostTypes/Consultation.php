<?php

namespace BusinessBuilderCore\Packs\LawFirm\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Consultation Request storage.
 *
 * A private post type used only to store submissions from the
 * LawFirm consultation form so they can be reviewed in the admin.
 *
 * Design notes (spec 23 / 37):
 *   - private: not public, no front-end single or archive.
 *   - show_ui: true so site admins can read submissions.
 *   - No custom DB tables - uses the existing posts table, so data
 *     stays per-site on Multisite automatically.
 *
 * Submissions store their fields as post meta:
 *   _bb_consultation_name, _bb_consultation_phone,
 *   _bb_consultation_email, _bb_consultation_practice_area,
 *   _bb_consultation_message, _bb_consultation_preferred_contact,
 *   _bb_consultation_created
 */
class Consultation {

    /**
     * Post type slug.
     */
    private const POST_TYPE = 'bb_consultation';

    /**
     * Register hooks.
     */
    public function register(): void {

        add_action(
            'init',
            array( $this, 'register_post_type' )
        );

        /*
         * The admin panel for consultation requests is provided by
         * BusinessBuilderCore\Packs\LawFirm\Admin\ConsultationAdmin.
         */
    }

    /**
     * Register the private consultation post type.
     */
    public function register_post_type(): void {

        $labels = array(
            'name'               => _x( 'Consultation Requests', 'post type general name', 'business-builder' ),
            'singular_name'      => _x( 'Consultation Request', 'post type singular name', 'business-builder' ),
            'menu_name'          => __( 'Consultations', 'business-builder' ),
            'edit_item'          => __( 'View Consultation', 'business-builder' ),
            'view_item'          => __( 'View Consultation', 'business-builder' ),
            'all_items'          => __( 'Consultation Requests', 'business-builder' ),
            'search_items'       => __( 'Search Consultations', 'business-builder' ),
            'not_found'          => __( 'No consultation requests found.', 'business-builder' ),
            'not_found_in_trash' => __( 'No consultation requests found in Trash.', 'business-builder' ),
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
            'menu_icon'           => 'dashicons-phone',
            'rewrite'             => false,
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
