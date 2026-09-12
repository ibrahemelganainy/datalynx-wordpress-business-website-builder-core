<?php
/* Real WP runtime: Payment Settings + Dashboard admin pages register & render. */
define( 'WP_USE_THEMES', false );
define( 'WP_ADMIN', true );

require 'c:/MAMP/htdocs/wordpress/wp-load.php';

/* Authenticate as the network/site administrator for the current blog. */
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'blog_id' => 2 ) );

if ( empty( $admins ) ) {
    echo 'NO ADMIN USER FOUND' . PHP_EOL;
    exit( 1 );
}

switch_to_blog( 2 );
wp_set_current_user( $admins[0]->ID );
do_action( 'init' );

echo 'current user: ' . wp_get_current_user()->user_login . ' (can manage_options: ' . var_export( current_user_can( 'manage_options' ), true ) . ')' . PHP_EOL;

/* Fire admin_menu so the pack's submenus register, then inspect. */
require_once ABSPATH . 'wp-admin/includes/plugin.php';
do_action( 'admin_menu' );

global $submenu;
$parent = 'business-builder';
$slugs = array();

if ( isset( $submenu[ $parent ] ) && is_array( $submenu[ $parent ] ) ) {
    foreach ( $submenu[ $parent ] as $item ) {
        $slugs[] = $item[2];
    }
}

echo 'submenus: ' . implode( ', ', $slugs ) . PHP_EOL;

foreach ( array( 'business-builder-dashboard', 'business-builder-payments' ) as $want ) {
    echo 'has ' . $want . ': ' . var_export( in_array( $want, $slugs, true ), true ) . PHP_EOL;
}

/* PaymentManager integration inside the pack's PaymentSettingsAdmin. */
require_once ABSPATH . 'wp-content/plugins/business-builder-core/includes/Core/Payments/PaymentManager.php';

restore_current_blog();
echo 'DONE' . PHP_EOL;
