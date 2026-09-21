<?php

namespace BusinessBuilderCore\Core;

use BusinessBuilderCore\Core\Payments\Transaction\PaymentTransactionPostType;
use BusinessBuilderCore\Packs\LawFirm\PostTypes\Lawyer;
use BusinessBuilderCore\Packs\LawFirm\PostTypes\LegalService;
use BusinessBuilderCore\Packs\LawFirm\Taxonomies\PracticeArea;

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

        /*
         * Register the PUBLIC pack post types / taxonomy that own pretty
         * permalinks (e.g. bb_lawyer => /lawyers/{slug}/) BEFORE flushing.
         *
         * The pack normally registers these on 'init', but the activation
         * request flushes rewrite rules in a context where those init
         * hooks have not produced the rules yet. Flushing without them
         * stores a rule set with NO /lawyers/ rule, so lawyer profile
         * pages 404 until permalinks are manually re-saved. Registering
         * them here makes the FIRST stored rule set correct.
         */
        $has_lawyer_class = class_exists( Lawyer::class );
        $has_service_class = class_exists( LegalService::class );
        $has_area_class = class_exists( PracticeArea::class );

        if ( $has_lawyer_class ) {
            ( new Lawyer() )->register_post_type();
        }

        if ( $has_service_class ) {
            ( new LegalService() )->register_post_type();
        }

        if ( $has_area_class ) {
            ( new PracticeArea() )->register_taxonomy();
        }

        $has_flush = function_exists( 'flush_rewrite_rules' );

        if ( $has_flush ) {
            flush_rewrite_rules();
        }
    }
}
