<?php
/**
 * Phase 23 — multisite isolation probe (diagnostic only).
 *
 * Prints, per site, the theme, the theme-mod OPTION the value would be stored in,
 * and the raw stored value. Used to explain a leak the suite reported.
 *
 * @package BusinessBuilderCore
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 'CLI only.' );
}

define( 'WP_USE_THEMES', false );
require dirname( __DIR__, 4 ) . '/wp-load.php';

echo 'sites: ' . count( get_sites( array( 'number' => 0 ) ) ) . PHP_EOL;

foreach ( get_sites( array( 'number' => 0 ) ) as $site ) {

	$blog_id = (int) $site->blog_id;

	switch_to_blog( $blog_id );

	$stylesheet = get_option( 'stylesheet' );
	$option_key = 'theme_mods_' . $stylesheet;
	$option     = get_option( $option_key, array() );
	$via_helper = get_theme_mod( 'bb_design_nav_indicator', '' );

	global $wpdb;

	printf(
		"site %d: current=%d table=%-24s theme=%-18s key_present=%s helper=%s mods=%d\n",
		$blog_id,
		get_current_blog_id(),
		$wpdb->options,
		$stylesheet,
		is_array( $option ) && array_key_exists( 'bb_design_nav_indicator', $option ) ? 'yes' : 'no',
		'' === (string) $via_helper ? '(empty)' : $via_helper,
		is_array( $option ) ? count( $option ) : 0
	);

	printf(
		"          direct SQL stylesheet: %s | siteurl: %s\n",
		(string) $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'stylesheet' LIMIT 1" ),
		(string) get_option( 'siteurl' )
	);

	restore_current_blog();
}
