<?php

namespace BusinessBuilderCore\Core;

use BusinessBuilderCore\Core\Payments\Transaction\PaymentTransactionPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin activation.
 */
class Activator {

    /**
     * Activate the plugin.
     */
    public static function activate(): void {

        /*
         * Register the pack post types / taxonomies for this request
         * so that flush_rewrite_rules() generates the correct permalink
         * rules for entities such as bb_lawyer (/lawyers/{slug}/).
         *
         * Without this, custom-post-type singles 404 until the
         * permalinks are manually re-saved.
         *
         * This is intentionally generic (Core) behaviour, not
         * pack-specific: any future pack's rewrite slugs benefit. The
         * flush is a one-off activation cost and touches no user data.
         */

        /*
         * Register the private payment post type (Phase F) before the
         * flush so any rewrite contribution it ever makes is captured.
         * It is non-public today, so this is purely future-proofing and
         * costs nothing.
         */
        ( new PaymentTransactionPostType() )->register_post_type();

        $has_flush = function_exists( 'flush_rewrite_rules' );

        if ( $has_flush ) {
            flush_rewrite_rules();
        }
    }
}
