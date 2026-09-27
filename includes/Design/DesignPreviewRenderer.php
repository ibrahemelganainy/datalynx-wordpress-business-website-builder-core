<?php

namespace BusinessBuilderCore\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Design Preview renderer (Phase 21 §16-§19, §48-§49, §58).
 *
 * Renders a REAL, visual preview of a Design so the catalogue card shows the design itself
 * rather than a placeholder rectangle, a stock photo or a raw hex list.
 *
 * WHY THIS IS NOT A FAKE PREVIEW (§58)
 * -----------------------------------
 * The requirement is that a preview can never show Design A while the site shows Design B. Two
 * mechanisms guarantee that here:
 *
 *   1. THE TOKENS ARE THE DESIGN'S OWN. Every colour, gradient, radius, font, density and
 *      motion value in the preview comes from the design's registry entry — the exact same map
 *      the Theme will apply to the live site. There is no separate "preview palette" that could
 *      drift.
 *
 *   2. THE PREVIEW IS SCOPED BY A DESIGN CLASS, and the Phase-21 identity layer
 *      (`assets/css/frontend/design-identity.css`) + the shared component classes style the
 *      preview markup through those tokens. So the preview is rendered BY the design system
 *      being previewed, not by a parallel drawing.
 *
 * The preview reuses the REAL component markup conventions (`.bb-card`, `.bb-button`,
 * `.bb-section-heading`) so what the admin sees in the card is the same component language the
 * live site uses.
 *
 * READ-ONLY
 * ---------
 * This class renders markup for an admin screen. It queries nothing, writes nothing, and takes
 * a design array that the caller has already obtained from the catalogue. It never touches
 * theme mods.
 *
 * PERFORMANCE (§59)
 * -----------------
 * The preview is pure CSS + a few DOM nodes: no iframe, no screenshot pipeline, no image
 * assets, no JavaScript. It costs one string concatenation per card.
 */
class DesignPreviewRenderer {

	/**
	 * Which parts of a page the preview depicts, in order (§19).
	 *
	 * These are REPRESENTATIVE of the section system rather than a hardcoded list of every
	 * section: the preview shows the shell, the hero, a heading treatment, a grid of cards, a
	 * CTA and the footer, which is what communicates a design quickly. The live site renders
	 * every registered section through the same tokens.
	 */
	protected function anatomy(): array {

		return array( 'header', 'hero', 'heading', 'cards', 'cta', 'footer' );
	}

	/**
	 * Render the full preview body for a design.
	 *
	 * @param array<string, mixed> $design Normalized design from the catalogue.
	 * @param string               $device Device frame: 'desktop' | 'tablet' | 'mobile'.
	 * @param string               $surface 'card' (small, in the grid) or 'modal' (large preview).
	 * @param bool                 $is_rtl Render the preview in RTL.
	 * @param string               $sample Optional business-type label for the sample copy.
	 * @return string Escaped HTML.
	 */
	public function render( array $design, string $device = 'desktop', string $surface = 'card', bool $is_rtl = false, string $sample = '' ): string {

		$tokens = isset( $design['tokens'] ) && is_array( $design['tokens'] ) ? $design['tokens'] : array();

		$scope = 'bb-design-preview-' . substr( md5( (string) ( $design['slug'] ?? '' ) . $device . $surface . ( $is_rtl ? 'rtl' : 'ltr' ) ), 0, 10 );

		/*
		 * The design's resolved tokens, scoped to this preview instance. `--bb-*` only: the preview
		 * introduces no token of its own, so anything that renders here also renders live.
		 */
		$style = '';

		foreach ( $tokens as $name => $value ) {

			$name = (string) $name;

			if ( 0 !== strpos( $name, '--bb-' ) ) {
				continue;
			}

			$value = $this->safe_value( (string) $value );

			if ( '' === $value ) {
				continue;
			}

			$style .= $name . ':' . $value . ';';
		}

		$classes = array(
			'bb-design-preview',
			'bb-design-preview--' . sanitize_html_class( $surface ),
			'bb-design-preview--' . sanitize_html_class( $device ),
			/* Both directions are rendered; CSS shows the one matching the modal's state (§35). */
			$is_rtl ? 'bb-design-preview--rtl' : 'bb-design-preview--ltr',
		);

		$label = isset( $design['label'] ) ? (string) $design['label'] : '';

		$html = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-bb-design="' . esc_attr( (string) ( $design['slug'] ?? '' ) ) . '" role="img" aria-label="' . esc_attr( $this->alt_text( $design, $device ) ) . '">';

		$html .= '<div class="' . esc_attr( $scope ) . ' bb-design-preview-scope" style="' . esc_attr( $style ) . '">';

		foreach ( $this->anatomy() as $part ) {

			$method = 'part_' . $part;

			if ( method_exists( $this, $method ) ) {
				$html .= $this->$method( $design, $sample );
			}
		}

		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * A descriptive alternative text for the preview (§60).
	 *
	 * @param array<string, mixed> $design Design.
	 * @param string               $device Device.
	 * @return string
	 */
	protected function alt_text( array $design, string $device ): string {

		$label = isset( $design['label'] ) ? (string) $design['label'] : __( 'Design', 'business-builder' );

		return sprintf(
			/* translators: 1: design name, 2: device. */
			__( '%1$s design preview shown on %2$s.', 'business-builder' ),
			$label,
			$device
		);
	}

	/**
	 * Only allow values that are safe to place inside a style attribute.
	 *
	 * The values come from the registry (trusted code), but a design may be filtered by a
	 * third-party pack, so this is a defence-in-depth whitelist: it rejects the characters that
	 * could break out of the declaration, plus `url(` / `expression(` / `javascript:` which would
	 * let a token load remote content or execute.
	 *
	 * NOTE ON QUOTES: font stacks legitimately contain quotes
	 * (`"Manrope", -apple-system, sans-serif`), so a bare `'` / `"` must NOT be rejected
	 * outright. Instead they are normalized: every quote is converted to the SINGLE quote
	 * character, and the whole value is later passed through `esc_attr()`, which renders it
	 * safely inside the attribute. Backslashes are still rejected because they are the escape
	 * mechanism an attacker would need to abuse a quoted string.
	 *
	 * This mirrors — and does not replace — the Theme's own sanitizer, which already validated the
	 * value on the way in.
	 *
	 * @param string $value Raw token value.
	 * @return string Safe value, or '' to skip.
	 */
	protected function safe_value( string $value ): string {

		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		/* Normalize quotes so the value is predictable, then reject the declaration breakers. */
		$value = str_replace( array( '"', '‘', '’', '“', '”' ), "'", $value );

		foreach ( array( ';', '{', '}', '<', '>', '\\' ) as $bad ) {
			if ( false !== strpos( $value, $bad ) ) {
				return '';
			}
		}

		foreach ( array( 'url(', 'expression(', 'javascript:', '@import' ) as $bad ) {
			if ( false !== stripos( $value, $bad ) ) {
				return '';
			}
		}

		return $value;
	}

	/**
	 * Header band: the design's header colours, height, blur and border.
	 *
	 * @param array<string, mixed> $design Design.
	 * @param string               $sample Sample label.
	 * @return string
	 */
	protected function part_header( array $design, string $sample ): string {

		$nav = array(
			__( 'Home', 'business-builder' ),
			__( 'Services', 'business-builder' ),
			__( 'Team', 'business-builder' ),
			__( 'Contact', 'business-builder' ),
		);

		$html = '<div class="bb-design-preview-header">';
		$html .= '<span class="bb-design-preview-logo">' . esc_html( '' !== $sample ? $sample : __( 'Brand', 'business-builder' ) ) . '</span>';
		$html .= '<span class="bb-design-preview-nav">';

		foreach ( $nav as $item ) {
			$html .= '<i>' . esc_html( $item ) . '</i>';
		}

		$html .= '</span>';
		$html .= '<span class="bb-design-preview-header-cta"></span>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Hero band: the design's hero background, gradient and heading typography.
	 *
	 * @param array<string, mixed> $design Design.
	 * @param string               $sample Sample label.
	 * @return string
	 */
	protected function part_hero( array $design, string $sample ): string {

		$html  = '<div class="bb-design-preview-hero">';
		$html .= '<span class="bb-design-preview-eyebrow"></span>';
		$html .= '<span class="bb-design-preview-h1">' . esc_html__( 'Trusted counsel, delivered clearly', 'business-builder' ) . '</span>';
		$html .= '<span class="bb-design-preview-p"></span>';
		$html .= '<span class="bb-design-preview-p bb-design-preview-p--short"></span>';
		$html .= '<span class="bb-design-preview-actions">';
		$html .= '<span class="bb-design-preview-btn bb-design-preview-btn--primary"></span>';
		$html .= '<span class="bb-design-preview-btn"></span>';
		$html .= '</span>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Section heading band: shows the design's heading scale and decorative gradient rule.
	 *
	 * @param array<string, mixed> $design Design.
	 * @param string               $sample Sample label.
	 * @return string
	 */
	protected function part_heading( array $design, string $sample ): string {

		$html  = '<div class="bb-design-preview-section">';
		$html .= '<span class="bb-design-preview-h2">' . esc_html__( 'Our practice', 'business-builder' ) . '</span>';
		$html .= '<span class="bb-design-preview-rule"></span>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Card grid: shows the design's card radius, border, padding, shadow and grid density.
	 *
	 * @param array<string, mixed> $design Design.
	 * @param string               $sample Sample label.
	 * @return string
	 */
	protected function part_cards( array $design, string $sample ): string {

		$html = '<div class="bb-design-preview-grid">';

		for ( $i = 0; $i < 3; $i++ ) {

			$html .= '<span class="bb-design-preview-card">';
			$html .= '<span class="bb-design-preview-card-media"></span>';
			$html .= '<span class="bb-design-preview-card-line"></span>';
			$html .= '<span class="bb-design-preview-card-line bb-design-preview-card-line--short"></span>';
			$html .= '<span class="bb-design-preview-badge"></span>';
			$html .= '</span>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * CTA band: shows the design's CTA gradient and primary button language.
	 *
	 * @param array<string, mixed> $design Design.
	 * @param string               $sample Sample label.
	 * @return string
	 */
	protected function part_cta( array $design, string $sample ): string {

		$html  = '<div class="bb-design-preview-cta">';
		$html .= '<span class="bb-design-preview-cta-title"></span>';
		$html .= '<span class="bb-design-preview-btn bb-design-preview-btn--primary"></span>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Footer band: shows the design's footer variant colours and rhythm.
	 *
	 * @param array<string, mixed> $design Design.
	 * @param string               $sample Sample label.
	 * @return string
	 */
	protected function part_footer( array $design, string $sample ): string {

		$html  = '<div class="bb-design-preview-footer">';
		$html .= '<span class="bb-design-preview-footer-brand"></span>';
		$html .= '<span class="bb-design-preview-footer-cols">';
		$html .= '<i></i><i></i><i></i>';
		$html .= '</span>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * A compact palette strip built from the design's OWN colours (§49).
	 *
	 * Returns swatch markup with the colour NAME as an accessible label, so the palette is
	 * visible rather than textual (§21).
	 *
	 * @param array<string, mixed> $design Design.
	 * @return string
	 */
	public function palette_strip( array $design ): string {

		$palette = isset( $design['palette'] ) && is_array( $design['palette'] ) ? $design['palette'] : array();

		if ( empty( $palette ) ) {
			return '';
		}

		$html = '<span class="bb-design-palette" role="img" aria-label="'
			. esc_attr( sprintf( /* translators: %s: design name. */ __( '%s colour palette', 'business-builder' ), (string) ( $design['label'] ?? '' ) ) )
			. '">';

		foreach ( $palette as $swatch ) {

			$value = $this->safe_value( (string) ( $swatch['value'] ?? '' ) );

			if ( '' === $value ) {
				continue;
			}

			$html .= '<i class="bb-design-swatch" style="background-color:' . esc_attr( $value ) . ';" title="'
				. esc_attr( (string) ( $swatch['label'] ?? '' ) . ' ' . $value ) . '"></i>';
		}

		$html .= '</span>';

		return $html;
	}

	/**
	 * A typography specimen built from the design's own font stack (§24-§25).
	 *
	 * @param array<string, mixed> $design Design.
	 * @return string
	 */
	public function type_specimen( array $design ): string {

		$tokens = isset( $design['tokens'] ) && is_array( $design['tokens'] ) ? $design['tokens'] : array();

		$heading = $this->safe_value( (string) ( $tokens['--bb-font-heading'] ?? '' ) );

		if ( '' === $heading ) {
			return '';
		}

		return '<span class="bb-design-type-specimen" style="font-family:' . esc_attr( $heading ) . ';">'
			. esc_html__( 'Aa', 'business-builder' )
			. '</span>';
	}

	/**
	 * The design's shell description, for the card metadata (§32, §48).
	 *
	 * @param array<string, mixed> $design Design.
	 * @return string
	 */
	public function shell_summary( array $design ): string {

		$shell = isset( $design['shell'] ) && is_array( $design['shell'] ) ? $design['shell'] : array();

		if ( empty( $shell ) ) {
			return '';
		}

		$parts = array();

		foreach ( $shell as $part => $variant ) {
			$parts[] = ucfirst( sanitize_key( (string) $part ) ) . ': ' . ucfirst( sanitize_key( (string) $variant ) );
		}

		return implode( ' · ', $parts );
	}
}