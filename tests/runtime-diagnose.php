<?php
/** Diagnose taxonomy availability in CLI bootstrap. */
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';

switch_to_blog( 2 );

echo 'plugin active (blog2): ' . var_export( is_plugin_active( 'business-builder-core/business-builder-core.php' ), true ) . PHP_EOL;
echo 'network active: ' . var_export( is_plugin_active_for_network( 'business-builder-core/business-builder-core.php' ), true ) . PHP_EOL;
echo 'taxonomy exists: ' . var_export( taxonomy_exists( 'bb_practice_area' ), true ) . PHP_EOL;
echo 'cpt exists: ' . var_export( post_type_exists( 'bb_lawyer' ), true ) . PHP_EOL;

$err = get_terms( array( 'taxonomy' => 'bb_practice_area', 'hide_empty' => false ) );
if ( is_wp_error( $err ) ) {
    echo 'get_terms ERROR: ' . $err->get_error_message() . PHP_EOL;
}
restore_current_blog();
echo 'DONE' . PHP_EOL;
