<?php

namespace BusinessBuilderCore\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Design-driven Shell selection (Phase 21 §13, §30, §31, §32).
 *
 * A Design is a complete visual identity, so it must be able to say not only "these are my
 * colours" but also "this is my header, my navigation and my footer". Today the shell is an
 * independent Customizer choice; this class lets the ACTIVE DESIGN express a shell preference
 * while keeping the Phase 15 shell architecture completely authoritative.
 *
 * HOW IT WORKS (no new architecture)
 * ----------------------------------
 *   1. A design MAY declare its shell preference in its own registry entry:
 *
 *          'shell' => array(
 *              'header'     => 'centered',
 *              'navigation' => 'centered',
 *              'footer'     => 'columns',
 *          )
 *
 *      `BusinessBuilderCore\Design\DesignCatalogue::normalize()` already flattens a registry
 *      entry, so the key simply travels with the design — no new storage, no new registry.
 *
 *   2. When the Theme resolves a shell part, it fires its OWN documented filter
 *      (`bb_theme_shell_variant`) with the requested slug. We answer that filter with the
 *      design's preference; the Theme then runs it through `bb_theme_shell_resolve()`, which
 *      whitelists it against `bb_theme_shell_variants()`.
 *
 *   3. Therefore an unknown, removed or malformed variant can NEVER reach a template path: the
 *      Phase 15 resolver still decides, exactly as before. This class only SUGGESTS.
 *
 * PRECEDENCE (deliberate, and documented so it is predictable)
 * ----------------------------------------------------------
 *   explicit per-site shell theme mod  >  active design's shell preference  >  'default'
 *
 * An admin who has deliberately picked a header in the Customizer keeps it. A design only
 * supplies the header when the site has not chosen one. This means switching designs can never
 * silently destroy a manual shell choice, and switching to a design still gives the intended
 * shell out of the box.
 *
 * NOTHING HERE WRITES ANYTHING. The class is a pure read-time filter over the existing
 * resolver, so it cannot break a site, and disabling it restores the previous behaviour exactly.
 */
class DesignShell {

	/**
	 * The shell parts a design may configure.
	 *
	 * Read from the Theme's own registry function when available, so a child theme that adds a
	 * fourth shell part automatically gets design support without editing this class.
	 */
	protected function parts(): array {

		if ( function_exists( 'bb_theme_shell_parts' ) ) {
			$parts = bb_theme_shell_parts();

			if ( is_array( $parts ) && ! empty( $parts ) ) {
				return array_keys( $parts );
			}
		}

		/* Conservative fallback so the class is safe even if the Theme is not loaded yet. */
		return array( 'header', 'navigation', 'footer' );
	}

	protected DesignCatalogue $catalogue;

	public function __construct( ?DesignCatalogue $catalogue = null ) {

		$this->catalogue = $catalogue ?: new DesignCatalogue();
	}

	/**
	 * Register the filter.
	 */
	public function register(): void {

		/*
		 * One filter, three parts. `bb_theme_shell_part_variant()` fires this for every part and
		 * passes the part name, so a single handler covers header, navigation and footer.
		 *
		 * Priority 20: runs AFTER a child theme or another integration has had its say at the
		 * default priority, but the Theme's resolver still validates the final value.
		 */
		add_filter( 'bb_theme_shell_variant', array( $this, 'apply_design_shell' ), 20, 2 );
	}

	/**
	 * The shell a design declares, normalized.
	 *
	 * @param string $slug Design slug.
	 * @return array<string, string> part => variant
	 */
	public function design_shell( string $slug ): array {

		$slug = sanitize_key( $slug );

		if ( '' === $slug ) {
			return array();
		}

		$all = $this->catalogue->all();

		if ( ! isset( $all[ $slug ] ) ) {
			return array();
		}

		$out = array();

		$declared = isset( $all[ $slug ]['shell'] ) && is_array( $all[ $slug ]['shell'] )
			? $all[ $slug ]['shell']
			: array();

		foreach ( $this->parts() as $part ) {

			if ( ! isset( $declared[ $part ] ) ) {
				continue;
			}

			$variant = sanitize_key( (string) $declared[ $part ] );

			if ( '' !== $variant ) {
				$out[ $part ] = $variant;
			}
		}

		return $out;
	}

	/**
	 * Answer the Theme's shell-variant filter with the active design's preference.
	 *
	 * @param string $requested The variant the Theme is about to resolve.
	 * @param string $part      Shell part.
	 * @return string
	 */
	public function apply_design_shell( $requested, $part = '' ) {

		$requested = is_string( $requested ) ? $requested : '';
		$part      = sanitize_key( (string) $part );

		if ( '' === $part ) {
			return $requested;
		}

		/*
		 * PRECEDENCE: an explicit site choice wins. `bb_theme_shell_part_variant()` reads the
		 * theme mod and only falls back to `default` when nothing was chosen — so a non-default
		 * value here means an admin deliberately selected it, and we must not override it.
		 */
		if ( '' !== $requested && 'default' !== $requested ) {
			return $requested;
		}

		/*
		 * Read the EXPLICIT mod directly rather than trusting `$requested`: this distinguishes
		 * "the admin chose default" ('' !== mod) from "nothing was ever chosen" ('' === mod),
		 * which is the difference between honouring a deliberate choice and applying the design.
		 */
		if ( function_exists( 'bb_theme_shell_mod_name' ) ) {
			$explicit = (string) get_theme_mod( bb_theme_shell_mod_name( $part ), '' );

			if ( '' !== $explicit ) {
				return $requested;
			}
		}

		$design_shell = $this->design_shell( $this->catalogue->active() );

		if ( isset( $design_shell[ $part ] ) ) {
			return $design_shell[ $part ];
		}

		return $requested;
	}

	/**
	 * Whether a design declares ANY shell preference.
	 *
	 * Exposed for the Design screen so it can show "this design includes a glass header" without
	 * duplicating the shell knowledge.
	 *
	 * @param string $slug Design slug.
	 * @return bool
	 */
	public function has_shell( string $slug ): bool {

		return array() !== $this->design_shell( $slug );
	}
}