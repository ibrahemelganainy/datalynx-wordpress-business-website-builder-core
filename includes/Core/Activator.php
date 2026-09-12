<?php

namespace BusinessBuilderCore\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
        if ( function_exists( 'flush_rewrite_rules' ) ) {

            flush_rewrite_rules();
        }
    }
}
