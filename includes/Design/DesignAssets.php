<?php

namespace BusinessBuilderCore\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Design front-end assets (Phase 21 §14, §15, §59).
 *
 * The Theme owns its own stylesheet list; this class adds ONLY the Phase 21 design layer that
 * lives in the plugin:
 *
 *   - `design-motion.js`   the single lightweight reveal observer (no animation library).
 *   - `design-identity.css` is NOT enqueued here: it is aggregated into the plugin's existing
 *     `assets/css/frontend.css` (see that file), which `Plugin::enqueue_builder_frontend_assets()`
 *     already loads on builder pages. Adding a second stylesheet handle for it would be a
 *     second delivery path for the same cascade position, so it is deliberately avoided.
 *
 * WHY THE MOTION SCRIPT IS NOT ADDED TO THE AGGREGATE
 * --------------------------------------------------
 * It must load on every front-end request that renders a design — including archive and single
 * pages, not only builder pages — because a design is a site-wide identity, not a homepage skin
 * (§53). It is hooking the Theme's own `bb-theme` script as a dependency so ordering is
 * guaranteed without editing the Theme.
 */
class DesignAssets {

	/**
	 * Register hooks.
	 */
	public function register(): void {

		if ( is_admin() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 30 );
	}

	/**
	 * Enqueue the design motion and shell-state scripts.
	 */
	public function enqueue(): void {

		/*
		 * Only load when the Theme's front-end bundle is present: `data-bb-reveal` targets are
		 * produced by the Theme's shell/section markup, so on a non-builder theme (e.g. Astra on
		 * the non-Business-Builder site 1) there is nothing for the observer to do and the script
		 * must not be shipped.
		 */
		if ( ! wp_style_is( 'bb-theme-tokens', 'enqueued' ) && ! wp_script_is( 'bb-theme', 'enqueued' ) ) {
			return;
		}

		$path = BB_CORE_PATH . 'assets/js/frontend/design-motion.js';

		$version = is_readable( $path ) ? (string) filemtime( $path ) : BB_CORE_VERSION;

		wp_enqueue_script(
			'bb-design-motion',
			BB_CORE_URL . 'assets/js/frontend/design-motion.js',
			array(), /* No dependencies: it must never block on the Theme's script. */
			$version,
			true
		);

		/*
		 * The header's scrolled state (Phase 22 §21). A separate, tiny script rather than an
		 * addition to the motion observer: the two have nothing in common (one is a scroll state,
		 * the other a one-shot reveal observer) and bundling them would force every page to
		 * download the observer's logic to get a header state change.
		 */
		$shell_path = BB_CORE_PATH . 'assets/js/frontend/design-shell.js';

		wp_enqueue_script(
			'bb-design-shell',
			BB_CORE_URL . 'assets/js/frontend/design-shell.js',
			array(),
			is_readable( $shell_path ) ? (string) filemtime( $shell_path ) : BB_CORE_VERSION,
			true
		);
	}
}