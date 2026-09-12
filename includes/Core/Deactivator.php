<?php

namespace BusinessBuilderCore\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles plugin deactivation.
 */
class Deactivator {

    /**
     * Deactivate the plugin.
     */
    public static function deactivate(): void {

        /*
         * Deactivation logic will be added later.
         *
         * User data must never be deleted simply because
         * the plugin has been deactivated.
         */
    }
}