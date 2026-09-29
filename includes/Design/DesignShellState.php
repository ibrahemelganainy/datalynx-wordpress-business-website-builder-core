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

		/*
		 * The scrollbar rules are attached to their own handle rather than printed
		 * directly, so they participate in the normal style dependency graph and can
		 * be removed by another plugin. `wp_enqueue_scripts` (not `wp_head`) is the
		 * hook that can still enqueue: WordPress prints styles at `wp_head` priority
		 * 8, so a later hook would be too late to emit anything.
		 */
		add_action( 'wp_enqueue_scripts', array( $this, 'print_scrollbar_styles' ), 20 );
	}

	/**
	 * Add the state classes the Phase 22/23 stylesheets cannot derive themselves.
	 *
	 * WHY THESE ARE CLASSES AND NOT TOKENS
	 * ------------------------------------
	 * A `select` control stores a KEYWORD (`boxed`, `pill`, `off`). CSS cannot
	 * branch on a custom property's value, so a keyword must become a selector
	 * hook. Publishing it as a body class keeps the decision in the token cascade
	 * (the class is derived from the RESOLVED token, not from a second setting)
	 * and keeps the stylesheet declarative.
	 *
	 * Nothing is added when nothing is set: on a site whose design declares none
	 * of these, `token()` returns the fallback and the class is never emitted, so
	 * the output is byte-identical to the pre-Phase-23 page.
	 *
	 * @param array $classes Existing body classes.
	 * @return array
	 */
	public function body_class( $classes ): array {

		$classes = is_array( $classes ) ? $classes : array();

		if ( $this->has_overlay() ) {
			$classes[] = 'has-bb-overlay';
		}

		/* ---- Global Layout (§9) ---- */
		if ( 'boxed' === $this->token( '--bb-layout-mode', 'full' ) ) {
			$classes[] = 'has-bb-layout-boxed';
		}

		/* ---- Navbar (§12) ---- */
		$nav_position = $this->token( '--bb-nav-position', 'center' );

		if ( in_array( $nav_position, array( 'start', 'end' ), true ) ) {
			$classes[] = 'has-bb-nav-' . $nav_position;
		}

		$nav_indicator = $this->token( '--bb-nav-indicator', 'none' );

		if ( in_array( $nav_indicator, array( 'underline', 'pill', 'dot' ), true ) ) {
			$classes[] = 'has-bb-nav-' . $nav_indicator;
		}

		/* ---- Motion master (§14) ---- */
		if ( 'off' === $this->token( '--bb-motion-mode', '' ) ) {
			$classes[] = 'has-bb-motion-off';
		}

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

	/* ======================================================================
	 * SCROLLBAR (§15)
	 * ====================================================================== */

	/**
	 * The scrollbar controls, keyed by the TOKEN they write.
	 *
	 * Kept in one place so the emitter, the Studio preview and the tests all
	 * agree about which tokens belong to this feature.
	 *
	 * @return array<string, string> token => control key.
	 */
	public function scrollbar_controls(): array {

		return array(
			'--bb-scrollbar-width'       => 'scrollbar_width',
			'--bb-scrollbar-track'       => 'scrollbar_track',
			'--bb-scrollbar-thumb'       => 'scrollbar_thumb',
			'--bb-scrollbar-thumb-hover' => 'scrollbar_thumb_hover',
		);
	}

	/**
	 * Whether THIS SITE has deliberately customized the scrollbar.
	 *
	 * MEASURED SCOPE DECISION
	 * -----------------------
	 * The page scrollbar lives on `html`, which the WordPress ADMIN also has.
	 * Styling `html` unconditionally would restyle every screen of wp-admin and
	 * every other site on this install — precisely the "no global leakage" rule.
	 *
	 * So the rules are emitted ONLY when the customer has stored a scrollbar
	 * value for THIS site. A design preset alone does not trigger it: a preset is
	 * a starting point, not a deliberate branding decision about browser chrome.
	 *
	 * Theme mods are per-site, so this check is multisite-isolated for free.
	 *
	 * @return bool
	 */
	public function has_custom_scrollbar(): bool {

		foreach ( $this->scrollbar_controls() as $key ) {

			$stored = get_theme_mod( 'bb_design_' . $key, '' );

			if ( is_string( $stored ) && '' !== trim( $stored ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Print the scrollbar rules, but only on a site that asked for them.
	 *
	 * Every value is read from the RESOLVED token cascade and then re-validated
	 * with the schema's own rules (a colour must be a colour, a length must be a
	 * length), so even a corrupted theme mod can never inject a declaration.
	 *
	 * @return void
	 */
	public function print_scrollbar_styles(): void {

		if ( ! $this->has_custom_scrollbar() ) {
			return;
		}

		$schema = array();

		foreach ( $this->schema_controls() as $control ) {

			if ( isset( $control['token'] ) ) {
				$schema[ (string) $control['token'] ] = $control;
			}
		}

		$values = array();

		foreach ( array_keys( $this->scrollbar_controls() ) as $token ) {

			if ( ! isset( $schema[ $token ] ) ) {
				continue;
			}

			$raw = $this->token( $token, '' );

			if ( '' === $raw ) {
				continue;
			}

			/*
			 * The Theme's own sanitizer is used when available (it is the single
			 * validator for the whole design system); otherwise the value is
			 * rejected rather than trusted.
			 */
			$clean = function_exists( 'bb_theme_sanitize_design_value' )
				? bb_theme_sanitize_design_value( $raw, $schema[ $token ] )
				: '';

			if ( '' !== $clean ) {
				$values[ $token ] = $clean;
			}
		}

		if ( empty( $values ) ) {
			return;
		}

		$width = $values['--bb-scrollbar-width'] ?? '10px';
		$track = $values['--bb-scrollbar-track'] ?? '#f1f5f9';
		$thumb = $values['--bb-scrollbar-thumb'] ?? '#94a3b8';
		$hover = $values['--bb-scrollbar-thumb-hover'] ?? '#64748b';

		if ( ! function_exists( 'wp_register_style' ) ) {
			return;
		}

		$css = 'html{scrollbar-color:' . $thumb . ' ' . $track . ';}'
			. 'html::-webkit-scrollbar{width:' . $width . ';height:' . $width . ';}'
			. 'html::-webkit-scrollbar-track{background:' . $track . ';}'
			. 'html::-webkit-scrollbar-thumb{background:' . $thumb . ';border-radius:' . $width . ';}'
			. 'html::-webkit-scrollbar-thumb:hover{background:' . $hover . ';}';

		/*
		 * Emitted through the standard WordPress inline-style API so it lands in
		 * <head> with the rest of the design layer and can be dequeued by handle.
		 */
		wp_register_style( 'bb-scrollbar', false, array(), defined( 'BB_CORE_VERSION' ) ? BB_CORE_VERSION : '1.0.0' );
		wp_enqueue_style( 'bb-scrollbar' );
		wp_add_inline_style( 'bb-scrollbar', $css );
	}

	/**
	 * The control schema, when the Theme (or the plugin's extension) provides one.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function schema_controls(): array {

		if ( ! function_exists( 'bb_theme_design_schema' ) ) {
			return array();
		}

		$controls = bb_theme_design_schema();

		return is_array( $controls ) ? $controls : array();
	}
}