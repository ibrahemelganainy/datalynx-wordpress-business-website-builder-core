<?php

namespace BusinessBuilderCore\Core\Payments\Transaction;

defined( 'ABSPATH' ) || exit;

/**
 * Private payment post type.
 *
 * Each payment transaction is stored as a row of the standard posts
 * table (behind a private CPT), so data is automatically isolated per
 * site on Multisite and no custom tables are introduced (spec 23/30).
 *
 * Like the consultation / appointment CPTs, it is not public, exposes
 * no front-end single or archive, and disallows manual creation from
 * the admin list (transactions are only ever created by the checkout
 * flow). A reviewer can still open a transaction to inspect its meta.
 */
class PaymentTransactionPostType {

    /**
     * Post type slug.
     */
    public const POST_TYPE = 'bb_payment';

    /**
     * Meta key prefix.
     */
    public const META_PREFIX = '_bb_payment_';

    /**
     * Register hooks.
     */
    public function register(): void {

        add_action( 'init', array( $this, 'register_post_type' ) );
    }

    /**
     * Register the private payment post type.
     */
    public function register_post_type(): void {

        $labels = array(
            'name'               => _x( 'Payments', 'post type general name', 'business-builder' ),
            'singular_name'      => _x( 'Payment', 'post type singular name', 'business-builder' ),
            'menu_name'          => __( 'Payments', 'business-builder' ),
            'edit_item'          => __( 'View Payment', 'business-builder' ),
            'view_item'          => __( 'View Payment', 'business-builder' ),
            'all_items'          => __( 'Payments', 'business-builder' ),
            'search_items'       => __( 'Search Payments', 'business-builder' ),
            'not_found'          => __( 'No payments found.', 'business-builder' ),
            'not_found_in_trash' => __( 'No payments found in Trash.', 'business-builder' ),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => false,
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
            'menu_icon'           => 'dashicons-money-alt',
            'rewrite'             => false,
        );

        register_post_type( self::POST_TYPE, $args );
    }

    /**
     * The full meta key for a short name.
     *
     * @param string $name Short name without prefix.
     * @return string
     */
    public static function meta_key( string $name ): string {

        return self::META_PREFIX . sanitize_key( $name );
    }
}
