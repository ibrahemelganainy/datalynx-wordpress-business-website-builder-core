<?php
/**
 * PHASE 24 — ENVIRONMENT PROBE (§62)
 * ==================================
 * Phases 22 and 23 both recorded "browser verification impossible: MAMP fault
 * (php_mysqli.dll missing)". That diagnosis is re-tested here rather than
 * inherited, because a wrong environment diagnosis silently disables an entire
 * mandatory verification step (§62 states browser verification is mandatory and
 * "automated tests alone are NOT sufficient").
 *
 * This probe prints, from the CLI:
 *   - the php.ini the CLI actually loaded, and whether mysqli/mysqlnd are present;
 *   - every site in the network with its active theme and business type, so the
 *     correct URLs for browser verification are known instead of guessed;
 *   - the front-end URL of each site.
 *
 * Run:  php tests/_phase24-env-probe.php
 *
 * @package BusinessBuilderCore
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 'CLI only.' );
}

define( 'WP_USE_THEMES', false );
require dirname( __DIR__, 4 ) . '/wp-load.php';

echo '== PHP (CLI) ==' . PHP_EOL;
printf( "  version:        %s\n", PHP_VERSION );
printf( "  loaded ini:     %s\n", php_ini_loaded_file() ? php_ini_loaded_file() : '(none)' );
printf( "  extension_dir:  %s\n", (string) ini_get( 'extension_dir' ) );
printf( "  mysqli:         %s\n", extension_loaded( 'mysqli' ) ? 'loaded' : 'MISSING' );
printf( "  mysqlnd:        %s\n", extension_loaded( 'mysqlnd' ) ? 'loaded' : 'MISSING' );
printf( "  pdo_mysql:      %s\n", extension_loaded( 'pdo_mysql' ) ? 'loaded' : 'MISSING' );
printf( "  is_multisite:   %s\n", is_multisite() ? 'yes' : 'no' );

echo PHP_EOL . '== NETWORK SITES ==' . PHP_EOL;

if ( is_multisite() ) {

	foreach ( get_sites( array( 'number' => 0 ) ) as $site ) {

		switch_to_blog( (int) $site->blog_id );

		printf(
			"  blog %-3d theme=%-18s type=%-12s preset=%-18s url=%s\n",
			(int) $site->blog_id,
			get_stylesheet(),
			(string) ( get_option( 'bb_business_type' ) ?: '(none)' ),
			(string) ( get_theme_mod( 'bb_theme_preset' ) ?: '(unset)' ),
			home_url( '/' )
		);

		restore_current_blog();
	}
} else {
	printf( "  single site: %s\n", home_url( '/' ) );
}

echo PHP_EOL . '== LIVE HTTP SELF-TEST ==' . PHP_EOL;

/*
 * The point of the probe: prove from the CLI that an HTTP request to the site
 * returns 200 and contains no PHP fatal, which is the precondition for the
 * browser verification step. Uses the WP HTTP API so no curl extension is
 * required.
 */
if ( is_multisite() ) {

	foreach ( get_sites( array( 'number' => 0 ) ) as $site ) {

		$url      = get_site_url( (int) $site->blog_id, '/' );
		$response = wp_remote_get( $url, array( 'timeout' => 20 ) );

		if ( is_wp_error( $response ) ) {
			printf( "  blog %-3d %s -> ERROR: %s\n", (int) $site->blog_id, $url, $response->get_error_message() );
			continue;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );

		printf(
			"  blog %-3d HTTP %-3d %-8s bytes=%-7d php_error=%s\n",
			(int) $site->blog_id,
			$code,
			200 === $code ? 'OK' : 'FAIL',
			strlen( $body ),
			preg_match( '/Fatal error|Parse error|Error establishing a database/i', $body ) ? 'YES' : 'no'
		);
	}
}
