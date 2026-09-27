<?php

namespace BusinessBuilderCore\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Design typography library (Phase 21 §24, §25).
 *
 * Provides the Studio with curated font choices and the sample text each one is previewed with,
 * so a customer sees the actual typeface before selecting it (§24) rather than a font name.
 *
 * THE CONTRACT THIS CLASS MUST RESPECT (measured, not assumed)
 * -----------------------------------------------------------
 * The Theme's font controls are `type => 'font'`, and `bb_theme_sanitize_design_value()` resolves
 * them through `bb_theme_font_stack_choices()`, which maps a SLUG to a CSS stack:
 *
 *     'serif' => 'Georgia, "Times New Roman", serif'
 *
 * A font control therefore STORES A SLUG, not a stack. The four built-in slugs are
 * `system`, `serif`, `sans`, `mono`.
 *
 * This class extends that same filter (`bb_theme_font_stack_choices`) with the curated families
 * below, so:
 *   - the Studio offers real typefaces,
 *   - the Theme's own sanitizer accepts them unchanged (no new validator, no weakened check),
 *   - `DesignFonts` can still load the remote families, because the stack it reads is the one the
 *     slug resolves to.
 *
 * ARCHITECTURE (§25, §40, §59)
 * ---------------------------
 *   - ONE catalog: the families here are the SAME curated set `DesignFonts` knows how to fetch.
 *   - Every stack ends in a generic family, so a design renders correctly even if a remote font
 *     is unavailable.
 *   - Arabic coverage is explicit, and the specimen shows an Arabic sample when it applies.
 */
class DesignTypography {

	/**
	 * Register the curated fonts with the Theme's own font-choice filter.
	 *
	 * This is what makes the Studio's font choices selectable at all: the Theme only accepts a
	 * slug it knows, and this teaches it the curated set.
	 */
	public function register(): void {

		add_filter( 'bb_theme_font_stack_choices', array( $this, 'extend_font_choices' ), 10 );
	}

	/**
	 * Add the curated families to the Theme's font-stack choices.
	 *
	 * Additive only: a slug the Theme already defines is never overwritten.
	 *
	 * @param array $choices Existing slug => stack map.
	 * @return array
	 */
	public function extend_font_choices( $choices ): array {

		$choices = is_array( $choices ) ? $choices : array();

		foreach ( $this->fonts() as $slug => $font ) {

			if ( isset( $choices[ $slug ] ) ) {
				continue;
			}

			$choices[ $slug ] = (string) $font['stack'];
		}

		return $choices;
	}

	/**
	 * Curated heading and body font choices.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function fonts(): array {

		$fonts = array(

			/* ---------------------------------------------------- modern sans */
			'manrope' => array(
				'label'   => 'Manrope',
				'stack'   => '"Manrope", "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans Arabic", sans-serif',
				'kind'    => 'sans',
				'arabic'  => false,
				'note'    => __( 'Modern geometric sans. Clean and technology-forward.', 'business-builder' ),
			),
			'inter' => array(
				'label'   => 'Inter',
				'stack'   => '"Inter", "Manrope", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans Arabic", sans-serif',
				'kind'    => 'sans',
				'arabic'  => false,
				'note'    => __( 'Neutral interface sans. Extremely legible at small sizes.', 'business-builder' ),
			),
			'plus-jakarta-sans' => array(
				'label'   => 'Plus Jakarta Sans',
				'stack'   => '"Plus Jakarta Sans", "Manrope", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans Arabic", sans-serif',
				'kind'    => 'sans',
				'arabic'  => false,
				'note'    => __( 'Soft geometric sans. Warm and approachable.', 'business-builder' ),
			),
			'dm-sans' => array(
				'label'   => 'DM Sans',
				'stack'   => '"DM Sans", "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans Arabic", sans-serif',
				'kind'    => 'sans',
				'arabic'  => false,
				'note'    => __( 'Low-contrast geometric sans. Compact and modern.', 'business-builder' ),
			),
			'outfit' => array(
				'label'   => 'Outfit',
				'stack'   => '"Outfit", "Manrope", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans Arabic", sans-serif',
				'kind'    => 'sans',
				'arabic'  => false,
				'note'    => __( 'Bold geometric display sans. Strong headlines.', 'business-builder' ),
			),

			/* ---------------------------------------------------- serif */
			'cormorant-garamond' => array(
				'label'   => 'Cormorant Garamond',
				'stack'   => '"Cormorant Garamond", Georgia, "Times New Roman", "Noto Naskh Arabic", serif',
				'kind'    => 'serif',
				'arabic'  => false,
				'note'    => __( 'High-contrast display serif. Luxurious and editorial.', 'business-builder' ),
			),
			'source-serif-4' => array(
				'label'   => 'Source Serif 4',
				'stack'   => '"Source Serif 4", Georgia, "Times New Roman", "Noto Naskh Arabic", serif',
				'kind'    => 'serif',
				'arabic'  => false,
				'note'    => __( 'Sturdy text serif. Authoritative and highly readable.', 'business-builder' ),
			),
			'georgia' => array(
				'label'   => 'Georgia (system)',
				'stack'   => 'Georgia, "Times New Roman", "Noto Serif", serif',
				'kind'    => 'serif',
				'arabic'  => false,
				'note'    => __( 'Classic system serif. No download required.', 'business-builder' ),
			),

			/* ---------------------------------------------------- Arabic-first */
			'ibm-plex-sans-arabic' => array(
				'label'   => 'IBM Plex Sans Arabic',
				'stack'   => '"IBM Plex Sans Arabic", "Noto Sans Arabic", "Segoe UI", Roboto, sans-serif',
				'kind'    => 'arabic',
				'arabic'  => true,
				'note'    => __( 'Purpose-built Arabic sans with matching Latin. Excellent for RTL.', 'business-builder' ),
			),
			'cairo' => array(
				'label'   => 'Cairo',
				'stack'   => '"Cairo", "IBM Plex Sans Arabic", "Noto Sans Arabic", sans-serif',
				'kind'    => 'arabic',
				'arabic'  => true,
				'note'    => __( 'Contemporary Arabic sans. Clear and neutral.', 'business-builder' ),
			),
			'tajawal' => array(
				'label'   => 'Tajawal',
				'stack'   => '"Tajawal", "IBM Plex Sans Arabic", "Noto Sans Arabic", sans-serif',
				'kind'    => 'arabic',
				'arabic'  => true,
				'note'    => __( 'Geometric Arabic sans. Modern and confident.', 'business-builder' ),
			),
			'noto-sans-arabic' => array(
				'label'   => 'Noto Sans Arabic',
				'stack'   => '"Noto Sans Arabic", "IBM Plex Sans Arabic", sans-serif',
				'kind'    => 'arabic',
				'arabic'  => true,
				'note'    => __( 'Comprehensive Arabic coverage. Safe, reliable fallback.', 'business-builder' ),
			),
		);

		/**
		 * Filter the curated font choices.
		 *
		 * @param array $fonts Font slug => definition.
		 */
		$fonts = apply_filters( 'bb_design_font_choices', $fonts );

		return is_array( $fonts ) ? $fonts : array();
	}

	/**
	 * The typography controls the Studio should render.
	 *
	 * Discovered from the schema so nothing is hardcoded: any control whose token is a font
	 * family is treated as a font control, and any numeric/typographic control is grouped with it.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function controls(): array {

		if ( ! function_exists( 'bb_theme_design_schema' ) ) {
			return array();
		}

		$out = array();

		foreach ( bb_theme_design_schema() as $control ) {

			if ( ! is_array( $control ) ) {
				continue;
			}

			$token = (string) ( $control['token'] ?? '' );
			$key   = (string) ( $control['key'] ?? '' );
			$type  = (string) ( $control['type'] ?? '' );

			if ( '' === $key ) {
				continue;
			}

			/*
			 * Font controls are identified by their TYPE, which is exactly what the sanitizer keys on.
			 * Matching the type (rather than a hardcoded list of tokens) means a font control added by
			 * a pack is picked up automatically.
			 */
			if ( 'font' === $type ) {

				$out[ $key ] = array(
					'key'     => $key,
					'token'   => $token,
					'label'   => (string) ( $control['label'] ?? $key ),
					'kind'    => 'font',
					'fonts'   => $this->fonts(),
					'default' => (string) ( $control['default'] ?? '' ),
				);

				continue;
			}

			/* Typographic scale controls, grouped by token. */
			if ( in_array( $token, array( '--bb-font-weight-bold', '--bb-font-size-md', '--bb-line-height-normal', '--bb-heading-scale', '--bb-heading-letter-spacing' ), true ) ) {

				$out[ $key ] = array(
					'key'     => $key,
					'token'   => $token,
					'label'   => (string) ( $control['label'] ?? $key ),
					'kind'    => 'value',
					'type'    => $type,
					'default' => (string) ( $control['default'] ?? '' ),
					'min'     => $control['min'] ?? null,
					'max'     => $control['max'] ?? null,
					'step'    => $control['step'] ?? null,
					'unit'    => (string) ( $control['unit'] ?? '' ),
					'options' => isset( $control['options'] ) && is_array( $control['options'] ) ? $control['options'] : array(),
				);
			}
		}

		return $out;
	}

	/**
	 * Which font slug a stored stack corresponds to.
	 *
	 * Used to preselect the right font in the Studio when a design already declares one. Matches on
	 * the FIRST family of each curated stack, so a design's own longer stack still resolves.
	 *
	 * @param string $stack A CSS font stack.
	 * @return string Font slug, or '' when unknown.
	 */
	public function slug_for_stack( string $stack ): string {

		if ( '' === trim( $stack ) ) {
			return '';
		}

		/* The Theme's own choices win, so a built-in slug resolves to itself. */
		if ( function_exists( 'bb_theme_font_stack_choices' ) ) {

			foreach ( bb_theme_font_stack_choices() as $slug => $candidate ) {
				if ( (string) $candidate === $stack ) {
					return (string) $slug;
				}
			}
		}

		foreach ( $this->fonts() as $slug => $font ) {

			$first = $this->first_family( (string) $font['stack'] );

			if ( '' !== $first && false !== stripos( $stack, $first ) ) {
				return $slug;
			}
		}

		return '';
	}

	/**
	 * The first family in a stack, unquoted.
	 *
	 * @param string $stack CSS font stack.
	 * @return string
	 */
	protected function first_family( string $stack ): string {

		$first = trim( (string) strtok( $stack, ',' ) );

		return trim( $first, " \t\n\r\0\x0B\"'" );
	}

	/**
	 * The specimens the Studio shows, with real sample text (§24).
	 *
	 * @return array<string, string>
	 */
	public function specimens(): array {

		return array(
			'latin'  => __( 'The quick brown fox jumps over the lazy dog', 'business-builder' ),
			'arabic' => 'نص عربي تجريبي للعرض',
			'numeric' => '0123456789',
		);
	}
}