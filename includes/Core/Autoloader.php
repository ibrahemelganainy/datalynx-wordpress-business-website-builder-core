<?php

namespace BusinessBuilderCore\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Autoloader {

    /**
     * Root namespace.
     */
    private const NAMESPACE_PREFIX = 'BusinessBuilderCore\\';

    /**
     * Register the autoloader.
     */
    public static function register(): void {

        spl_autoload_register(
            array( self::class, 'autoload' )
        );
    }

    /**
     * Load a class automatically.
     */
    private static function autoload( string $class ): void {

        // Ignore classes outside our namespace.
        if ( strpos( $class, self::NAMESPACE_PREFIX ) !== 0 ) {
            return;
        }

        // Remove root namespace.
        $relative_class = substr(
            $class,
            strlen( self::NAMESPACE_PREFIX )
        );

        /*
         * -------------------------------------------------------------
         * Core / Includes Classes
         * -------------------------------------------------------------
         *
         * Examples:
         *
         * BusinessBuilderCore\Core\Plugin
         * BusinessBuilderCore\Admin\SiteSettingsPage
         * BusinessBuilderCore\Settings\BusinessType
         *
         * These classes live inside:
         *
         * includes/
         */

        if (
            strpos( $relative_class, 'Core\\' ) === 0 ||
            strpos( $relative_class, 'Admin\\' ) === 0 ||
            strpos( $relative_class, 'Builder\\' ) === 0 ||
            strpos( $relative_class, 'REST\\' ) === 0 ||
            strpos( $relative_class, 'Database\\' ) === 0 ||
            strpos( $relative_class, 'PostTypes\\' ) === 0 ||
            strpos( $relative_class, 'Taxonomies\\' ) === 0 ||
            strpos( $relative_class, 'Settings\\' ) === 0 ||
            strpos( $relative_class, 'Media\\' ) === 0 ||
            strpos( $relative_class, 'Helpers\\' ) === 0
        ) {

            $relative_path = str_replace(
                '\\',
                DIRECTORY_SEPARATOR,
                $relative_class
            );

            $file = dirname( __DIR__ )
                . DIRECTORY_SEPARATOR
                . $relative_path
                . '.php';

            if ( file_exists( $file ) ) {
                require_once $file;
            }

            return;
        }

        /*
         * -------------------------------------------------------------
         * Business Packs
         * -------------------------------------------------------------
         *
         * Examples:
         *
         * BusinessBuilderCore\Packs\LawFirm\LawFirmPack
         * BusinessBuilderCore\Packs\LawFirm\PostTypes\Lawyer
         *
         * These classes live inside:
         *
         * packs/
         */

        if ( strpos( $relative_class, 'Packs\\' ) === 0 ) {

            $relative_class = substr(
                $relative_class,
                strlen( 'Packs\\' )
            );

            $relative_path = str_replace(
                '\\',
                DIRECTORY_SEPARATOR,
                $relative_class
            );

            $file = dirname( dirname( __DIR__ ) )
                . DIRECTORY_SEPARATOR
                . 'packs'
                . DIRECTORY_SEPARATOR
                . $relative_path
                . '.php';

            if ( file_exists( $file ) ) {
                require_once $file;
            }
        }
    }
}