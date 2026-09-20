<?php
/**
 * Plugin Name: Business Builder Core
 * Description: Core engine for the Business Website Builder.
 * Version: 1.0.0
 * Author: Business Builder
 * Text Domain: business-builder
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Plugin Constants
|--------------------------------------------------------------------------
*/

define( 'BB_CORE_VERSION', '1.0.0' );
define( 'BB_CORE_FILE', __FILE__ );
define( 'BB_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'BB_CORE_URL', plugin_dir_url( __FILE__ ) );

/*
|--------------------------------------------------------------------------
| Core Bootstrap
|--------------------------------------------------------------------------
*/

/*
 * Load the Autoloader first.
 */
require_once BB_CORE_PATH . 'includes/Core/Autoloader.php';

/*
 * Register the Autoloader.
 */
BusinessBuilderCore\Core\Autoloader::register();

/*
|--------------------------------------------------------------------------
| Activation / Deactivation
|--------------------------------------------------------------------------
*/

register_activation_hook(
    __FILE__,
    array(
        'BusinessBuilderCore\Core\Activator',
        'activate',
    )
);

register_deactivation_hook(
    __FILE__,
    array(
        'BusinessBuilderCore\Core\Deactivator',
        'deactivate',
    )
);

/*
|--------------------------------------------------------------------------
| Run Plugin
|--------------------------------------------------------------------------
*/

function bb_core_run(): void {

    /*
     * Self-heal HTTPS transport for payment gateways: point cURL at an
     * existing CA bundle when php.ini has none, so a server without a
     * configured curl.cainfo can still reach PayPal/Stripe/Paymob.
     */
    BusinessBuilderCore\Core\Payments\HttpTransport::register();

    $plugin = new BusinessBuilderCore\Core\Plugin();

    $plugin->run();
}

bb_core_run();