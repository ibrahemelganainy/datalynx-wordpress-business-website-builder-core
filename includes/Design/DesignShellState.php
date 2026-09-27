<?php

namespace BusinessBuilderCore\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Design Shell State (Phase 22 §10, §19, §21).
 *
 * Publishes the small amount of MARKUP STATE the Phase 22 CSS needs but cannot
 * derive on its own:
 *
 *   1. whether the site background has an overlay layer, so the overlay
 *      pseudo-element exists only when it is actually configured;
 *   2. whether the header should carry a glass surface;
 *   3. the scroll offset the header switches state at.
 *
 * WHY THIS IS A SEPARATE CLASS
 * ----------------------------
 * The Theme owns its shell markup and its shell variants. Phase 22 must not
 * edit the Theme to add a class, and it must not duplicate the Theme's markup.
 * The Theme already documents a `body_class`-equivalent extension point through
 * standard WordPress filters, so the state is attached there and the Theme stays
 * untouched.
 *
 * THE OVERLAY CLASS IS EARNED, NOT ASSUMED
 * ----------------------------------------
 * `--bb-bg-overlay` defaults to `none` and `--bb-bg-overlay-opacity` to `0`, so
 * a site that never opens the Background section must not gain an extra
 * pseudo-element (and the stacking context that comes with it). The class is
 * therefore added only when the resolved overlay is genuinely visible.
 *
 * NOTHING HERE WRITES ANYTHING. It is a pure read-time decorator over the
 * existing token cascade, so removing it restores the previous output exactly.
 */
class DesignShellState {

	/**
	 * Register the filters.
	 */
	public function register(): void {

		/*
		 * `body_class` is a core WordPress filter; using it means the Theme does
		 * not have to change, and any other theme that renders a body class gets
		 * the same, harmless, class names.
		 */
		add_filter( 'body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Add the background overlay class when the overlay is actually configured.
	 *
	 * @param array $classes Existing body classes.
	 * @return array
	 */
	public function body_class( $classes ): array {

		$classes = is_array( $classes ) ? $classes : array();

		if ( ! $this->has_overlay() ) {
			return $classes;
		}

		$classes[] = 'has-bb-overlay';

		return $classes;
	}

	/**
	 * Whether the resolved site background has a visible overlay layer.
	 *
	 * @return bool
	 */
	public function has_overlay(): bool {

		$overlay = $this->token( '--bb-bg-overlay', 'none' );

		if ( '' === $overlay || 'none' === $overlay ) {
			return false;
		}

		$opacity = (float) $this->token( '--bb-bg-overlay-opacity', '0' );

		return $opacity > 0;
	}

	/**
	 * Whether the header currently resolves to a glass surface.
	 *
	 * Kept public so the Studio preview and the tests can assert the same fact
	 * the frontend acts on, rather than re-deriving it.
	 *
	 * @return bool
	 */
	public function header_is_glass(): bool {

		$blur = $this->token( '--bb-header-blur', '0px' );

		return '' !== $blur && '0px' !== $blur && '0' !== $blur && 'none' !== $blur;
	}

	/**
	 * Whether the header declares a DIFFERENT scrolled state.
	 *
	 * Exposed so a caller can avoid shipping the scroll script when the initial
	 * and scrolled states are identical — the class toggle would then be a
	 * no-op, and §34 says not to ship work that cannot change anything.
	 *
	 * @return bool
	 */
	public function header_changes_on_scroll(): bool {

		$pairs = array(
			array( '--bb-header-initial-bg', '--bb-header-scrolled-bg' ),
			array( '--bb-header-initial-nav', '--bb-header-scrolled-nav' ),
			array( '--bb-header-initial-border', '--bb-header-scrolled-border' ),
		);

		foreach ( $pairs as $pair ) {

			if ( $this->token( $pair[0], '' ) !== $this->token( $pair[1], '' ) ) {
				return true;
			}
		}

		$shadow = $this->token( '--bb-header-scrolled-shadow', 'none' );

		return '' !== $shadow && 'none' !== $shadow;
	}

	/**
	 * Read a resolved design token.
	 *
	 * @param string $token    Token name.
	 * @param string $fallback Value when the cascade does not declare it.
	 * @return string
	 */
	protected function token( string $token, string $fallback = '' ): string {

		static $config = null;

		if ( null === $config ) {
			$config = function_exists( 'bb_theme_preset_config' ) ? bb_theme_preset_config() : array();
			$config = is_array( $config ) ? $config : array();
		}

		if ( isset( $config[ $token ] ) && '' !== (string) $config[ $token ] ) {
			return (string) $config[ $token ];
		}

		return $fallback;
	}
}