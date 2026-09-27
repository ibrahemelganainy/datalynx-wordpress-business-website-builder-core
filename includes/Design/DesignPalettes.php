<?php

namespace BusinessBuilderCore\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Design palette presets (Phase 21 §21, §22, §55).
 *
 * A curated, enterprise-grade library of named colour palettes for the Design Studio.
 *
 * WHY THIS EXISTS
 * ---------------
 * §21 forbids presenting customization as raw hex values, and §22 asks for professionally
 * curated palettes rather than a bare colour picker. A customer should choose "Midnight" or
 * "Ocean" and SEE the colours, not type `#0F172A`.
 *
 * ARCHITECTURE (§40, §55)
 * -----------------------
 *   - This is NOT a second design system. A palette is nothing more than a set of values for
 *     controls the Theme ALREADY declares (`color_primary`, `color_accent`, ...). Applying one
 *     writes the existing `bb_design_<key>` theme mods through the existing sanitizer.
 *   - Every key is verified against `bb_theme_design_schema()` before it is offered, so a palette
 *     can never write a control that does not exist.
 *   - Every palette carries a precomputed contrast report so the UI can state, truthfully,
 *     whether body text and buttons remain readable.
 *
 * CONTRAST (§55)
 * --------------
 * `contrast_report()` implements the WCAG 2.1 relative-luminance formula. It is the smallest
 * validation that answers the question §55 asks ("is body text readable? are buttons
 * accessible?"), and it is used to LABEL palettes rather than to silently alter them.
 */
class DesignPalettes {

	/**
	 * The curated palette library.
	 *
	 * Each palette maps a design control key to a colour. Keys are the Theme's existing control
	 * keys; no new token or storage is introduced.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {

		$palettes = array(

			/* ------------------------------------------------ dark / executive */
			'midnight' => array(
				'label'       => __( 'Midnight', 'business-builder' ),
				'description' => __( 'Deep slate navy with a cyan and violet accent. Technology-forward and premium.', 'business-builder' ),
				'mood'        => 'dark',
				'colors'      => array(
					'color_primary'       => '#0f172a',
					'color_primary_hover' => '#1e293b',
					'color_accent'        => '#06b6d4',
					'color_secondary'     => '#8b5cf6',
					'color_background'    => '#f8fafc',
					'color_surface'       => '#ffffff',
					'color_surface_muted' => '#f1f5f9',
					'color_heading'       => '#0f172a',
					'color_text'          => '#1e293b',
					'color_text_muted'    => '#64748b',
					'color_border'        => '#e2e8f0',
				),
			),
			'obsidian' => array(
				'label'       => __( 'Obsidian', 'business-builder' ),
				'description' => __( 'Near-black surfaces with refined gold. Dark luxury and executive.', 'business-builder' ),
				'mood'        => 'dark',
				'colors'      => array(
					'color_primary'       => '#d4af37',
					'color_primary_hover' => '#b8962c',
					'color_accent'        => '#c084fc',
					'color_secondary'     => '#7c3aed',
					'color_background'    => '#080b12',
					'color_surface'       => '#111827',
					'color_surface_muted' => '#0b0f19',
					'color_heading'       => '#f8fafc',
					'color_text'          => '#cbd5e1',
					'color_text_muted'    => '#94a3b8',
					'color_border'        => '#273244',
					/*
					 * MEASURED (§55): white on this gold is 2.10:1 - unreadable. Dark text on the same gold
					 * is 9.11:1. A gold-primary palette MUST invert its button label, or every button fails
					 * WCAG AA. The report below verifies this rather than assuming it.
					 */
					'color_text_inverse'  => '#0b0f19',
				),
			),
			'graphite' => array(
				'label'       => __( 'Graphite', 'business-builder' ),
				'description' => __( 'Charcoal and steel with a cool blue accent. Understated and precise.', 'business-builder' ),
				'mood'        => 'dark',
				'colors'      => array(
					'color_primary'       => '#334155',
					'color_primary_hover' => '#1e293b',
					'color_accent'        => '#38bdf8',
					'color_secondary'     => '#64748b',
					'color_background'    => '#0f172a',
					'color_surface'       => '#1e293b',
					'color_surface_muted' => '#162033',
					'color_heading'       => '#f1f5f9',
					'color_text'          => '#cbd5e1',
					'color_text_muted'    => '#94a3b8',
					'color_border'        => '#334155',
				),
			),

			/* ------------------------------------------------ cool / clinical */
			'ocean' => array(
				'label'       => __( 'Ocean', 'business-builder' ),
				'description' => __( 'Deep teal water with a bright cyan accent. Calm and trustworthy.', 'business-builder' ),
				'mood'        => 'cool',
				'colors'      => array(
					'color_primary'       => '#0f4c5c',
					'color_primary_hover' => '#155e75',
					'color_accent'        => '#06b6d4',
					'color_secondary'     => '#67e8f9',
					'color_background'    => '#f7fdfe',
					'color_surface'       => '#ffffff',
					'color_surface_muted' => '#ecfeff',
					'color_heading'       => '#083344',
					'color_text'          => '#155e75',
					'color_text_muted'    => '#5b7c86',
					'color_border'        => '#cbe9ee',
				),
			),
			'clinical' => array(
				'label'       => __( 'Clinical', 'business-builder' ),
				'description' => __( 'Clean white with medical blue and a teal accent. Legible and reassuring.', 'business-builder' ),
				'mood'        => 'light',
				'colors'      => array(
					'color_primary'       => '#1d4ed8',
					'color_primary_hover' => '#1e40af',
					'color_accent'        => '#0d9488',
					'color_secondary'     => '#0f172a',
					'color_background'    => '#f8fafc',
					'color_surface'       => '#ffffff',
					'color_surface_muted' => '#f1f5f9',
					'color_heading'       => '#111827',
					'color_text'          => '#1f2937',
					'color_text_muted'    => '#6b7280',
					'color_border'        => '#e5e7eb',
				),
			),
			'azure' => array(
				'label'       => __( 'Azure', 'business-builder' ),
				'description' => __( 'Bright corporate blue with a sky accent. Clear and confident.', 'business-builder' ),
				'mood'        => 'light',
				'colors'      => array(
					'color_primary'       => '#2563eb',
					'color_primary_hover' => '#1e40af',
					'color_accent'        => '#06b6d4',
					'color_secondary'     => '#0f172a',
					'color_background'    => '#f8fafc',
					'color_surface'       => '#ffffff',
					'color_surface_muted' => '#f1f5f9',
					'color_heading'       => '#0f172a',
					'color_text'          => '#1e293b',
					'color_text_muted'    => '#64748b',
					'color_border'        => '#e2e8f0',
				),
			),

			/* ------------------------------------------------ natural / calm */
			'emerald' => array(
				'label'       => __( 'Emerald', 'business-builder' ),
				'description' => __( 'Deep forest green with a fresh mint accent. Natural and restorative.', 'business-builder' ),
				'mood'        => 'light',
				'colors'      => array(
					'color_primary'       => '#064e3b',
					'color_primary_hover' => '#065f46',
					'color_accent'        => '#10b981',
					'color_secondary'     => '#6ee7b7',
					'color_background'    => '#f7fdfa',
					'color_surface'       => '#ffffff',
					'color_surface_muted' => '#ecfdf5',
					'color_heading'       => '#052e23',
					'color_text'          => '#14532d',
					'color_text_muted'    => '#5b7a68',
					'color_border'        => '#d1fae5',
				),
			),
			'sage' => array(
				'label'       => __( 'Sage', 'business-builder' ),
				'description' => __( 'Muted green-grey with a soft accent. Quiet, calm and clinical.', 'business-builder' ),
				'mood'        => 'light',
				'colors'      => array(
					'color_primary'       => '#3f6212',
					'color_primary_hover' => '#365314',
					'color_accent'        => '#84cc16',
					'color_secondary'     => '#0d9488',
					'color_background'    => '#fbfdf8',
					'color_surface'       => '#ffffff',
					'color_surface_muted' => '#f7fee7',
					'color_heading'       => '#1a2e05',
					'color_text'          => '#3f6212',
					'color_text_muted'    => '#6b7f57',
					'color_border'        => '#e3ebd5',
				),
			),

			/* ------------------------------------------------ warm / editorial */
			'sand' => array(
				'label'       => __( 'Sand', 'business-builder' ),
				'description' => __( 'Warm stone and burnt orange. Grounded, editorial and human.', 'business-builder' ),
				'mood'        => 'warm',
				'colors'      => array(
					'color_primary'       => '#292524',
					'color_primary_hover' => '#44403c',
					'color_accent'        => '#c2410c',
					'color_secondary'     => '#fdba74',
					'color_background'    => '#fdfbf7',
					'color_surface'       => '#ffffff',
					'color_surface_muted' => '#f5f1ea',
					'color_heading'       => '#1c1917',
					'color_text'          => '#44403c',
					'color_text_muted'    => '#78716c',
					'color_border'        => '#e7e0d5',
				),
			),
			'copper' => array(
				'label'       => __( 'Copper', 'business-builder' ),
				'description' => __( 'Ivory and charcoal with a burgundy-copper accent. Boutique and refined.', 'business-builder' ),
				'mood'        => 'warm',
				'colors'      => array(
					'color_primary'       => '#7c2d12',
					'color_primary_hover' => '#9a3412',
					'color_accent'        => '#b8843c',
					'color_secondary'     => '#1c1917',
					'color_background'    => '#faf7f2',
					'color_surface'       => '#ffffff',
					'color_surface_muted' => '#f5f0e8',
					'color_heading'       => '#2b2620',
					'color_text'          => '#3f3a34',
					'color_text_muted'    => '#7a726a',
					'color_border'        => '#e7ded0',
				),
			),
			'burgundy' => array(
				'label'       => __( 'Burgundy', 'business-builder' ),
				'description' => __( 'Deep wine with a warm copper accent. Traditional, formal and assured.', 'business-builder' ),
				'mood'        => 'warm',
				'colors'      => array(
					'color_primary'       => '#6b1d2b',
					'color_primary_hover' => '#4c1420',
					'color_accent'        => '#c08457',
					'color_secondary'     => '#2b2620',
					'color_background'    => '#fdfaf8',
					'color_surface'       => '#ffffff',
					'color_surface_muted' => '#f7f1ee',
					'color_heading'       => '#2b1218',
					'color_text'          => '#443037',
					'color_text_muted'    => '#7d6a70',
					'color_border'        => '#ecdcd8',
				),
			),

			/* ------------------------------------------------ vivid */
			'royal' => array(
				'label'       => __( 'Royal', 'business-builder' ),
				'description' => __( 'Deep indigo with an electric violet accent. Bold and modern.', 'business-builder' ),
				'mood'        => 'dark',
				'colors'      => array(
					'color_primary'       => '#1e1b4b',
					'color_primary_hover' => '#312e81',
					'color_accent'        => '#6366f1',
					'color_secondary'     => '#a78bfa',
					'color_background'    => '#f8f8ff',
					'color_surface'       => '#ffffff',
					'color_surface_muted' => '#eef2ff',
					'color_heading'       => '#1e1b4b',
					'color_text'          => '#312e81',
					'color_text_muted'    => '#6b6a99',
					'color_border'        => '#e0e7ff',
				),
			),
			'plum' => array(
				'label'       => __( 'Plum', 'business-builder' ),
				'description' => __( 'Deep plum with a soft lilac accent. Premium private-practice feel.', 'business-builder' ),
				'mood'        => 'light',
				'colors'      => array(
					'color_primary'       => '#6d28d9',
					'color_primary_hover' => '#5b21b6',
					'color_accent'        => '#c9a227',
					'color_secondary'     => '#3b2f52',
					'color_background'    => '#fbf9ff',
					'color_surface'       => '#ffffff',
					'color_surface_muted' => '#f5f1ff',
					'color_heading'       => '#2e1065',
					'color_text'          => '#3b2f52',
					'color_text_muted'    => '#76688f',
					'color_border'        => '#e6defa',
				),
			),
		);

		/**
		 * Filter the palette library.
		 *
		 * @param array $palettes Palette slug => definition.
		 */
		$palettes = apply_filters( 'bb_design_palettes', $palettes );

		return is_array( $palettes ) ? $palettes : array();
	}

	/**
	 * A single palette, with its colours filtered to controls that actually exist.
	 *
	 * @param string $slug Palette slug.
	 * @return array<string, mixed>|null
	 */
	public function get( string $slug ): ?array {

		$slug     = sanitize_key( $slug );
		$palettes = $this->all();

		if ( '' === $slug || ! isset( $palettes[ $slug ] ) ) {
			return null;
		}

		$palette = $palettes[ $slug ];

		$colors = isset( $palette['colors'] ) && is_array( $palette['colors'] ) ? $palette['colors'] : array();

		$palette['colors'] = $this->known_controls_only( $colors );
		$palette['slug']   = $slug;
		$palette['swatches'] = $this->swatches( $palette['colors'] );

		/*
		 * The contrast report is attached HERE, not only in `for_ui()`, so a single palette fetched by
		 * slug is always self-describing. Without this, `get()` returned a palette with no `contrast`
		 * key and the UI had to know which accessor produced a complete object.
		 */
		$palette['contrast'] = $this->contrast_report( $palette['colors'] );

		return $palette;
	}

	/**
	 * Keep only keys the Theme's schema actually declares.
	 *
	 * A palette must never be able to write a control that does not exist, so this is the gate
	 * between the library and the storage layer.
	 *
	 * @param array<string, string> $colors Proposed control => colour.
	 * @return array<string, string>
	 */
	protected function known_controls_only( array $colors ): array {

		if ( ! function_exists( 'bb_theme_design_schema' ) ) {
			return array();
		}

		$known = array();

		foreach ( bb_theme_design_schema() as $control ) {

			if ( ! is_array( $control ) || ! isset( $control['key'] ) ) {
				continue;
			}

			/* Only colour controls may receive a colour. */
			if ( 'color' !== ( $control['type'] ?? '' ) ) {
				continue;
			}

			$known[ (string) $control['key'] ] = true;
		}

		$out = array();

		foreach ( $colors as $key => $value ) {

			$key = sanitize_key( (string) $key );

			if ( ! isset( $known[ $key ] ) ) {
				continue;
			}

			$value = sanitize_hex_color( (string) $value );

			if ( null === $value || '' === $value ) {
				continue;
			}

			$out[ $key ] = $value;
		}

		return $out;
	}

	/**
	 * The swatches the Studio shows for a palette, in a deliberate order.
	 *
	 * @param array<string, string> $colors Control => colour.
	 * @return array<int, array{key:string,label:string,value:string}>
	 */
	public function swatches( array $colors ): array {

		$order = array(
			'color_primary'    => __( 'Primary', 'business-builder' ),
			'color_accent'     => __( 'Accent', 'business-builder' ),
			'color_secondary'  => __( 'Secondary', 'business-builder' ),
			'color_background' => __( 'Background', 'business-builder' ),
			'color_surface'    => __( 'Surface', 'business-builder' ),
			'color_text'       => __( 'Text', 'business-builder' ),
			'color_border'     => __( 'Border', 'business-builder' ),
		);

		$out = array();

		foreach ( $order as $key => $label ) {

			if ( empty( $colors[ $key ] ) ) {
				continue;
			}

			$out[] = array(
				'key'   => $key,
				'label' => $label,
				'value' => (string) $colors[ $key ],
			);
		}

		return $out;
	}

	/**
	 * WCAG 2.1 relative luminance of a hex colour.
	 *
	 * @param string $hex Hex colour.
	 * @return float 0 (black) .. 1 (white).
	 */
	public function luminance( string $hex ): float {

		$hex = ltrim( trim( $hex ), '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return 0.0;
		}

		$channels = array();

		for ( $i = 0; $i < 3; $i++ ) {

			$value = hexdec( substr( $hex, $i * 2, 2 ) ) / 255;

			$channels[] = ( $value <= 0.03928 )
				? $value / 12.92
				: pow( ( $value + 0.055 ) / 1.055, 2.4 );
		}

		return ( 0.2126 * $channels[0] ) + ( 0.7152 * $channels[1] ) + ( 0.0722 * $channels[2] );
	}

	/**
	 * WCAG contrast ratio between two colours.
	 *
	 * @param string $a Hex colour.
	 * @param string $b Hex colour.
	 * @return float 1.0 .. 21.0
	 */
	public function contrast_ratio( string $a, string $b ): float {

		$la = $this->luminance( $a );
		$lb = $this->luminance( $b );

		$lighter = max( $la, $lb );
		$darker  = min( $la, $lb );

		return ( $lighter + 0.05 ) / ( $darker + 0.05 );
	}

	/**
	 * The contrast report for a palette (§55).
	 *
	 * Answers the two questions that matter for readability: can body text be read on the
	 * background, and can button labels be read on the primary colour?
	 *
	 * The button-label colour is not assumed. The platform paints a primary button with
	 * `--bb-color-text-inverse`, and a palette may override it; when it does not, the label
	 * colour that the DESIGN SYSTEM will actually use is whatever contrasts best with the primary
	 * colour. That is measured here rather than assumed, because a gold primary with white text is
	 * only 2.10:1 while the same gold with dark text is 9.11:1 - the difference between an
	 * accessible button and an unreadable one.
	 *
	 * @param array<string, string> $colors Control => colour.
	 * @return array<string, mixed>
	 */
	public function contrast_report( array $colors ): array {

		$background = (string) ( $colors['color_background'] ?? '#ffffff' );
		$surface    = (string) ( $colors['color_surface'] ?? $background );
		$text       = (string) ( $colors['color_text'] ?? '#000000' );
		$heading    = (string) ( $colors['color_heading'] ?? $text );
		$muted      = (string) ( $colors['color_text_muted'] ?? $text );
		$primary    = (string) ( $colors['color_primary'] ?? '#000000' );

		/*
		 * An explicit inverse wins; otherwise use the better of the two conventional label colours.
		 * This is the colour a customer would actually get, so the report reflects reality.
		 */
		$inverse = (string) ( $colors['color_text_inverse'] ?? '' );

		if ( '' === $inverse ) {

			$on_white = $this->contrast_ratio( '#ffffff', $primary );
			$on_dark  = $this->contrast_ratio( '#0b0f19', $primary );

			$inverse = ( $on_dark >= $on_white ) ? '#0b0f19' : '#ffffff';
		}

		$body    = $this->contrast_ratio( $text, $background );
		$head    = $this->contrast_ratio( $heading, $background );
		$muted_r = $this->contrast_ratio( $muted, $background );
		$on_prim = $this->contrast_ratio( $inverse, $primary );

		/* WCAG AA: 4.5:1 for body text, 3:1 for large text. */
		$checks = array(
			'body_text'    => array( 'ratio' => round( $body, 2 ), 'pass' => $body >= 4.5 ),
			'heading_text' => array( 'ratio' => round( $head, 2 ), 'pass' => $head >= 3.0 ),
			'muted_text'   => array( 'ratio' => round( $muted_r, 2 ), 'pass' => $muted_r >= 3.0 ),
			'button_label' => array( 'ratio' => round( $on_prim, 2 ), 'pass' => $on_prim >= 4.5 ),
		);

		$failures = 0;

		foreach ( $checks as $check ) {
			if ( ! $check['pass'] ) { $failures++; }
		}

		return array(
			'checks'   => $checks,
			'failures' => $failures,
			/* 'good' = everything passes, 'fair' = only secondary checks fail, 'poor' = body/button fail. */
			'grade'    => ( 0 === $failures ) ? 'good' : ( ( $checks['body_text']['pass'] && $checks['button_label']['pass'] ) ? 'fair' : 'poor' ),
			/* The label colour the button would actually use, so the UI can show it. */
			'button_label' => $inverse,
			'surface_ratio' => round( $this->contrast_ratio( $surface, $background ), 2 ),
		);
	}

	/**
	 * The palette library with each entry fully resolved for the UI.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function for_ui(): array {

		$out = array();

		foreach ( array_keys( $this->all() ) as $slug ) {

			$palette = $this->get( $slug );

			if ( ! $palette ) {
				continue;
			}

			$out[ $slug ] = $palette;
		}

		return $out;
	}
}