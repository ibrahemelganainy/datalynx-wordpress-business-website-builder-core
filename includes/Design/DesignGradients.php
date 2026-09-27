<?php

namespace BusinessBuilderCore\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Design gradient library (Phase 21 §23).
 *
 * Turns the gradient presets the schema already declares into something the Studio can draw as
 * a visual tile, so a customer selects a gradient by SEEING it rather than by reading CSS.
 *
 * ARCHITECTURE
 * ------------
 * This class owns NO gradient definitions of its own. The authoritative library lives in
 * `DesignSchema::gradient_library()`, which is also what the gradient CONTROLS validate against.
 * Reading it here guarantees the Studio can only ever offer a gradient the schema will accept -
 * there is exactly one source of truth.
 */
class DesignGradients {

	protected DesignSchema $schema;

	public function __construct( ?DesignSchema $schema = null ) {

		$this->schema = $schema ?: new DesignSchema();
	}

	/**
	 * Every gradient, resolved for rendering as a tile.
	 *
	 * @return array<string, array{slug:string,label:string,css:string,is_flat:bool}>
	 */
	public function all(): array {

		$out = array();

		foreach ( $this->schema->gradient_library() as $slug => $gradient ) {

			$slug = sanitize_key( (string) $slug );
			$css  = (string) ( $gradient['css'] ?? '' );

			$out[ $slug ] = array(
				'slug'    => $slug,
				'label'   => (string) ( $gradient['label'] ?? $slug ),
				'css'     => $css,
				/* `none` is a legitimate choice and must render as an explicit "no gradient" tile. */
				'is_flat' => ( 'none' === $css || '' === $css ),
			);
		}

		return $out;
	}

	/**
	 * The gradients a specific control offers, in the order the control declares them.
	 *
	 * Reading the CONTROL's own option list (rather than the whole library) keeps the UI in step
	 * with what the sanitizer will actually accept for that control.
	 *
	 * @param string $control_key Schema control key (e.g. `gradient_hero`).
	 * @return array<string, array{slug:string,label:string,css:string,is_flat:bool}>
	 */
	public function for_control( string $control_key ): array {

		$control_key = sanitize_key( $control_key );
		$library     = $this->all();

		if ( ! function_exists( 'bb_theme_design_schema' ) ) {
			return $library;
		}

		foreach ( bb_theme_design_schema() as $control ) {

			if ( ! is_array( $control ) || $control_key !== (string) ( $control['key'] ?? '' ) ) {
				continue;
			}

			$options = isset( $control['options'] ) && is_array( $control['options'] ) ? $control['options'] : array();

			$out = array();

			foreach ( array_keys( $options ) as $slug ) {

				$slug = sanitize_key( (string) $slug );

				if ( isset( $library[ $slug ] ) ) {
					$out[ $slug ] = $library[ $slug ];
				}
			}

			return $out;
		}

		return $library;
	}

	/**
	 * The gradient controls the Studio should render.
	 *
	 * Discovered from the schema, so a new gradient control added by a pack appears automatically.
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

			/* Gradient controls are exactly those that write a `--bb-gradient-*` token. */
			if ( 0 !== strpos( $token, '--bb-gradient-' ) ) {
				continue;
			}

			$key = (string) ( $control['key'] ?? '' );

			if ( '' === $key ) {
				continue;
			}

			$out[ $key ] = array(
				'key'      => $key,
				'token'    => $token,
				'label'    => (string) ( $control['label'] ?? $key ),
				'options'  => $this->for_control( $key ),
			);
		}

		return $out;
	}

	/**
	 * Resolve a stored gradient VALUE (a slug, or already-CSS) to CSS.
	 *
	 * @param string $value Stored value.
	 * @return string
	 */
	public function resolve( string $value ): string {

		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		if ( 'none' === $value ) {
			return 'none';
		}

		/* Already CSS. */
		if ( false !== strpos( $value, 'gradient(' ) ) {
			return $value;
		}

		$library = $this->all();
		$slug    = sanitize_key( $value );

		return isset( $library[ $slug ] ) ? (string) $library[ $slug ]['css'] : '';
	}
}