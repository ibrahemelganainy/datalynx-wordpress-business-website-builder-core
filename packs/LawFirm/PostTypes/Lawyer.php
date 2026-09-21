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

        add_filter(
            'manage_bb_lawyer_posts_columns',
            array( $this, 'add_status_column' )
        );

        add_action(
            'manage_bb_lawyer_posts_custom_column',
            array( $this, 'render_status_column' ),
            10,
            2
        );

        add_action(
            'admin_head',
            array( $this, 'admin_styles' )
        );
    }

    /**
     * Minimal status-badge styling on Lawyer admin screens only.
     *
     * Inline (not a new stylesheet) so Phase 4 stays surgical and never
     * touches the shared admin bundle. Scoped to the Lawyer list screen.
     */
    public function admin_styles(): void {

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        if ( ! $screen || 'bb_lawyer' !== $screen->post_type ) {
            return;
        }

        echo '<style>'
            . '.bb-status{display:inline-block;padding:1px 8px;border-radius:999px;font-size:12px;font-weight:600;}'
            . '.bb-status-active{background:#e5f5ea;color:#166534;border:1px solid #b7e0c2;}'
            . '.bb-status-inactive{background:#f1f5f9;color:#475569;border:1px solid #d5dbe3;}'
            . '</style>';
    }

    /**
     * Add a Status column to the Lawyer list table.
     *
     * The status is a plain, safely-escapable label, so it renders as text
     * (never as a link), which keeps the admin list free of any dead action.
     *
     * @param array<string, string> $columns Existing columns.
     * @return array<string, string>
     */
    public function add_status_column( array $columns ): array {

        $new = array();

        foreach ( $columns as $key => $label ) {

            $new[ $key ] = $label;

            /* Insert Status right after the title. */
            if ( 'title' === $key ) {
                $new['bb_lawyer_status'] = __( 'Status', 'business-builder' );
            }
        }

        return $new;
    }

    /**
     * Render the Status column value.
     *
     * @param string $column  Column key.
     * @param int    $post_id Lawyer post ID.
     */
    public function render_status_column( string $column, int $post_id ): void {

        if ( 'bb_lawyer_status' !== $column ) {
            return;
        }

        $status  = LawyerFields::get_status( $post_id );
        $labels  = LawyerFields::statuses();
        $label   = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
        $visible = '1' === (string) get_post_meta( $post_id, '_bb_lawyer_show_on_website', true );

        $class = 'active' === $status ? 'bb-status-active' : 'bb-status-inactive';

        echo '<span class="bb-status ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';

        /* Make the visibility control explicit without changing its meaning. */
        if ( 'active' === $status && ! $visible ) {
            echo '<br /><span class="description">' . esc_html__( 'Hidden on website', 'business-builder' ) . '</span>';
        }
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

            /*
             * Relocated under the independent Law Firm Dashboard menu
             * so the pack has one top-level entry (spec: Part 1).
             */
            'show_in_menu' => \BusinessBuilderCore\Packs\LawFirm\Admin\DashboardMenu::MENU_SLUG,

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