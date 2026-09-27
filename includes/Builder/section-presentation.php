<?php
/**
 * Section presentation state (Phase 22 §16, §19, §23, §25).
 *
 * The bridge between the TOKEN cascade and the rendered section markup.
 *
 * WHY IT IS A TOKEN READ AND NOT A SETTING READ
 * ---------------------------------------------
 * Glass, hover and reveal are configured in two places that must not diverge:
 *   - a DESIGN authors them (its preset declares `--bb-glass-blur` etc.);
 *   - a CUSTOMER overrides them (the Studio writes a section-scoped token).
 *
 * If the markup read a *setting* instead, a design's authored glass level would
 * have no effect, and a customer's override would have to be duplicated into
 * the section settings. Reading the RESOLVED TOKEN means there is exactly one
 * source of truth for each decision, and the cascade (design → global →
 * section) is honoured for free.
 *
 * HOW THE TOKEN IS READ
 * ---------------------
 * The tokens are custom properties, so their computed value is only knowable in
 * the browser. But the DECISION they encode is knowable on the server, because
 * the cascade is computed there too:
 *
 *   section override (theme mod)  →  else the design/global resolved config
 *
 * That is exactly what this file computes, using the SAME functions the Theme
 * uses to build `:root`. No value is guessed and no second cascade is invented.
 *
 * GENERIC BY CONSTRUCTION
 * -----------------------
 * Nothing here knows a section name. It answers the question "for this section
 * type, what is the resolved glass level / hover effect / reveal kind?" by
 * reading tokens, so a pack section this file has never seen is served
 * identically to a core one.
 *
 * @package BusinessBuilderCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'bb_section_presentation_tokens' ) ) {

	/**
	 * The resolved `--bb-*` configuration for the current request.
	 *
	 * Uses the Theme's own resolver so the values match what `:root` receives
	 * exactly. Falls back to an empty array when the canonical Theme is absent
	 * (e.g. on Astra), which makes every consumer below degrade to "no
	 * presentation state" rather than erroring.
	 *
	 * @return array<string, string>
	 */
	function bb_section_presentation_tokens(): array {

		static $cache = null;

		if ( null !== $cache ) {
			return $cache;
		}

		$cache = function_exists( 'bb_theme_preset_config' ) ? bb_theme_preset_config() : array();

		if ( ! is_array( $cache ) ) {
			$cache = array();
		}

		return $cache;
	}
}

if ( ! function_exists( 'bb_section_presentation_state' ) ) {

	/**
	 * The presentation attributes a section element should carry.
	 *
	 * Returns a map of ATTRIBUTE NAME => VALUE, already whitelisted. An empty
	 * value means "emit nothing", so a section that has no glass/hover/reveal
	 * configured stays exactly as it was before Phase 22.
	 *
	 * @param string $type Section type slug.
	 * @return array<string, string>
	 */
	function bb_section_presentation_state( string $type ): array {

		$type = sanitize_key( $type );

		if ( '' === $type ) {
			return array();
		}

		$tokens = bb_section_presentation_tokens();

		/*
		 * Section-scoped overrides win over the design/global value, which is
		 * the §16 cascade expressed as a single lookup.
		 */
		$overrides = array();

		if ( class_exists( '\BusinessBuilderCore\Design\SectionStyleSchema' ) ) {
			$overrides = ( new \BusinessBuilderCore\Design\SectionStyleSchema() )->overrides( $type );
		}

		/**
		 * Resolve one token: the section override first, then the design/global value.
		 *
		 * @param string $token    Token name.
		 * @param string $fallback Value when neither source declares it.
		 * @return string
		 */
		$resolve = static function ( string $token, string $fallback = '' ) use ( $overrides, $tokens ): string {

			if ( isset( $overrides[ $token ] ) && '' !== (string) $overrides[ $token ] ) {
				return (string) $overrides[ $token ];
			}

			if ( isset( $tokens[ $token ] ) && '' !== (string) $tokens[ $token ] ) {
				return (string) $tokens[ $token ];
			}

			return $fallback;
		};

		$state = array();

		/* ---- Glass (§19) ----
		 * The attribute is only emitted when glass is actually ON, so the CSS
		 * rule (which is keyed on `[data-bb-glass]`) cannot accidentally apply a
		 * frosted surface to a section that never asked for one.
		 */
		$glass = $resolve( '--bb-section-glass-blur', $resolve( '--bb-glass-blur', '0px' ) );

		$glass_on = ( '' !== $glass && '0px' !== $glass && '0' !== $glass && 'none' !== $glass );

		$state['data-bb-glass'] = $glass_on ? 'on' : 'off';

		/* ---- Hover (§23) ---- */
		$hover = $resolve( '--bb-section-hover', $resolve( '--bb-hover-effect', 'lift' ) );

		$hover = sanitize_key( $hover );

		$state['data-bb-hover'] = in_array( $hover, array( 'none', 'lift', 'scale', 'glow', 'zoom' ), true )
			? $hover
			: '';

		/* ---- Reveal (§23) ----
		 * The reveal KIND is resolved server-side so the animation is chosen by
		 * the design/customer and not hardcoded in JavaScript. `off` is a real
		 * value (not an empty one), because it must actively SUPPRESS the reveal
		 * the motion script would otherwise apply.
		 */
		$reveal = $resolve( '--bb-section-reveal', $resolve( '--bb-reveal-kind', 'fade-up' ) );

		$reveal = sanitize_key( $reveal );

		$kinds = array(
			'none'       => 'none',
			'off'        => 'none',
			'fade'       => 'fade',
			'fade-up'    => 'fade-up',
			'fade-down'  => 'fade-down',
			'fade-left'  => 'fade-left',
			'fade-right' => 'fade-right',
			'scale'      => 'scale',
			'blur'       => 'blur',
		);

		$state['data-bb-reveal'] = isset( $kinds[ $reveal ] ) ? $kinds[ $reveal ] : '';

		/*
		 * A section whose reveal is `none` must not be animated by the motion
		 * script either, so the opt-out is published as its own attribute.
		 */
		$state['data-bb-reveal-off'] = ( 'none' === $state['data-bb-reveal'] ) ? '1' : '';

		return $state;
	}
}

if ( ! function_exists( 'bb_section_glass_level' ) ) {

	/**
	 * Whether a section currently resolves to a glass surface.
	 *
	 * Exposed for the Studio preview and for tests, so the "glass works in more
	 * than one place" claim can be asserted rather than assumed.
	 *
	 * @param string $type Section type slug.
	 * @return bool
	 */
	function bb_section_glass_level( string $type ): bool {

		$state = bb_section_presentation_state( $type );

		return isset( $state['data-bb-glass'] ) && 'on' === $state['data-bb-glass'];
	}
}