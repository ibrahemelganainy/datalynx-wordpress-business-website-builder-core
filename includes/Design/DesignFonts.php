<?php

namespace BusinessBuilderCore\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Design font loading (Phase 21 §24, §25, §59).
 *
 * WHY THIS EXISTS
 * ---------------
 * A Design's typography is part of its visual identity: `Meridian` is a serif design, `Aurora` is
 * a Manrope design. Those families live in the design's `--bb-font-heading` / `--bb-font-primary`
 * tokens, but a token can only NAME a family - the browser must still have the font.
 *
 * Before this class the platform declared those families and loaded NOTHING, so every design
 * silently fell back to a system font. The typographic half of each design's identity therefore
 * never reached the customer. This class closes that gap.
 *
 * RULES OBSERVED (§25, §59)
 * -------------------------
 *   - LOAD ONLY WHAT IS USED: the remote families are derived from the ACTIVE design's own font
 *     tokens. A design that uses only system fonts (e.g. Meridian) triggers NO remote request.
 *   - NO BLOCKING: the stylesheet is enqueued normally and the connection is preconnected; no
 *     synchronous, render-blocking fetch is added.
 *   - ONE FILTER, ONE SOURCE: the curated family list is exposed through `bb_design_font_families`
 *     so a pack or child theme can add a family without editing this class.
 *   - SYSTEM FONTS ARE FREE: families such as `Georgia`, `Times New Roman`, `Segoe UI`, `Inter`
 *     fallbacks and `-apple-system` are recognised as local and never requested remotely.
 *   - GRACEFUL: if a family is unknown or the request fails, the design's declared fallback stack
 *     still applies - the token always ends in a generic family.
 *
 * The family names are read from the design's tokens, so this class never hardcodes a design,
 * a business type, or a font choice.
 */
class DesignFonts {

	/**
	 * Google Fonts families that may be requested remotely.
	 *
	 * Maps a CSS family name (as it appears in a design's token) to its Google Fonts slug and the
	 * weights the platform uses. Anything not listed here is treated as a LOCAL font and is never
	 * fetched - which is what keeps system-font designs request-free.
	 *
	 * @return array<string, array{slug:string, weights:array<int, int>}>
	 */
	public function catalog(): array {

		$catalog = array(
			'Manrope'            => array( 'slug' => 'Manrope', 'weights' => array( 400, 500, 600, 700, 800 ) ),
			'Inter'              => array( 'slug' => 'Inter', 'weights' => array( 400, 500, 600, 700 ) ),
			'Plus Jakarta Sans'  => array( 'slug' => 'Plus+Jakarta+Sans', 'weights' => array( 400, 500, 600, 700 ) ),
			'DM Sans'            => array( 'slug' => 'DM+Sans', 'weights' => array( 400, 500, 700 ) ),
			'Outfit'             => array( 'slug' => 'Outfit', 'weights' => array( 400, 500, 600, 700 ) ),
			'Cormorant Garamond' => array( 'slug' => 'Cormorant+Garamond', 'weights' => array( 400, 500, 600, 700 ) ),
			'Source Serif 4'     => array( 'slug' => 'Source+Serif+4', 'weights' => array( 400, 600, 700 ) ),
			'Noto Serif'         => array( 'slug' => 'Noto+Serif', 'weights' => array( 400, 600, 700 ) ),
			'IBM Plex Sans Arabic' => array( 'slug' => 'IBM+Plex+Sans+Arabic', 'weights' => array( 400, 500, 600, 700 ) ),
			'Cairo'              => array( 'slug' => 'Cairo', 'weights' => array( 400, 600, 700 ) ),
			'Tajawal'            => array( 'slug' => 'Tajawal', 'weights' => array( 400, 500, 700 ) ),
			'Noto Sans Arabic'   => array( 'slug' => 'Noto+Sans+Arabic', 'weights' => array( 400, 600, 700 ) ),
		);

		/**
		 * Filter the curated remote font catalog.
		 *
		 * @param array $catalog family => { slug, weights }.
		 */
		$catalog = apply_filters( 'bb_design_font_families', $catalog );

		return is_array( $catalog ) ? $catalog : array();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {

		if ( is_admin() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 25 );
		add_filter( 'wp_resource_hints', array( $this, 'resource_hints' ), 10, 2 );
	}

	/**
	 * The remote families the ACTIVE design actually needs.
	 *
	 * @return array<int, string> Google Fonts family slugs, in a stable order.
	 */
	public function needed_slugs(): array {

		if ( ! function_exists( 'bb_theme_preset_config' ) ) {
			return array();
		}

		$config = bb_theme_preset_config();

		if ( ! is_array( $config ) ) {
			return array();
		}

		$catalog = $this->catalog();

		$slugs = array();

		/*
		 * Only the typography tokens are inspected: a design's font identity lives there, and
		 * scanning every token would risk matching an unrelated value.
		 */
		foreach ( array( '--bb-font-heading', '--bb-font-primary' ) as $token ) {

			if ( empty( $config[ $token ] ) ) {
				continue;
			}

			$stack = (string) $config[ $token ];

			foreach ( $catalog as $family => $spec ) {

				/*
				 * Match the family as a whole word inside the stack. A plain substring test would
				 * match `Inter` inside `Interstate`, so the name must be quoted or delimited.
				 */
				$quoted  = '"' . $family . '"';
				$plain   = "'" . $family . "'";
				$pattern = '/(?:^|[\s,])' . preg_quote( $family, '/' ) . '(?:$|[\s,])/';

				if (
					false !== strpos( $stack, $quoted )
					|| false !== strpos( $stack, $plain )
					|| preg_match( $pattern, $stack )
				) {
					if ( ! in_array( $spec['slug'], $slugs, true ) ) {
						$slugs[] = $spec['slug'];
					}
				}
			}
		}

		/**
		 * Filter the family slugs to request for the active design.
		 *
		 * @param array $slugs   Google Fonts slugs.
		 * @param array $config  Resolved design configuration.
		 */
		$slugs = apply_filters( 'bb_design_font_slugs', $slugs, $config );

		return is_array( $slugs ) ? array_values( array_unique( $slugs ) ) : array();
	}

	/**
	 * Build the Google Fonts URL for the active design.
	 *
	 * @return string Empty string when the design needs no remote font.
	 */
	public function stylesheet_url(): string {

		$slugs = $this->needed_slugs();

		if ( empty( $slugs ) ) {
			return '';
		}

		$catalog = $this->catalog();

		/* Collect the weights required by the families actually in use. */
		$families = array();

		foreach ( $catalog as $spec ) {

			if ( ! in_array( $spec['slug'], $slugs, true ) ) {
				continue;
			}

			$weights = array_map( 'intval', (array) $spec['weights'] );
			sort( $weights );

			$families[] = 'family=' . $spec['slug'] . ':wght@' . implode( ';', $weights );
		}

		if ( empty( $families ) ) {
			return '';
		}

		/*
		 * `display=swap` shows the fallback text immediately and swaps when the font arrives, so a
		 * slow font never blocks rendering (§59). Only the weights the design uses are requested.
		 */
		return 'https://fonts.googleapis.com/css2?' . implode( '&', $families ) . '&display=swap';
	}

	/**
	 * Enqueue the design's fonts, if it needs any.
	 */
	public function enqueue(): void {

		$url = $this->stylesheet_url();

		if ( '' === $url ) {
			return;
		}

		wp_enqueue_style( 'bb-design-fonts', $url, array(), null );
	}

	/**
	 * Preconnect to the font hosts so the request starts sooner.
	 *
	 * @param array  $urls          Resource hints.
	 * @param string $relation_type Hint type.
	 * @return array
	 */
	public function resource_hints( $urls, $relation_type ) {

		if ( 'preconnect' !== $relation_type ) {
			return $urls;
		}

		/* Only hint when a font will actually be requested. */
		if ( '' === $this->stylesheet_url() ) {
			return $urls;
		}

		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous' );

		return $urls;
	}
}