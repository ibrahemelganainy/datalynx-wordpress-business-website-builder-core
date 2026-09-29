<?php
/**
 * Icon public API (§18).
 *
 * A thin functional wrapper around `Design\IconLibrary`, so a template, a pack
 * section or a component can render an icon without knowing the class, and so
 * there is exactly ONE place that decides what an icon looks like.
 *
 *   echo bb_render_icon( $item['icon'], array( 'label' => $item['title'] ) );
 *
 * @package BusinessBuilderCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'bb_icons' ) ) {

	/**
	 * The shared icon library (lazy-initialised).
	 *
	 * @return \BusinessBuilderCore\Design\IconLibrary
	 */
	function bb_icons(): \BusinessBuilderCore\Design\IconLibrary {

		static $library = null;

		if ( null === $library ) {
			$library = new \BusinessBuilderCore\Design\IconLibrary();
		}

		return $library;
	}
}

if ( ! function_exists( 'bb_render_icon' ) ) {

	/**
	 * Render a stored icon slug as safe markup.
	 *
	 * Returns '' for an unknown value, so nothing can be printed for a forged or
	 * stale slug — the caller can therefore simply echo the result.
	 *
	 * @param mixed                $value Raw stored value.
	 * @param array<string, mixed> $args  Optional `label` and `class`.
	 * @return string
	 */
	function bb_render_icon( $value, array $args = array() ): string {

		return bb_icons()->render( $value, $args );
	}
}

if ( ! function_exists( 'bb_validate_icon' ) ) {

	/**
	 * Validate a stored icon slug.
	 *
	 * @param mixed $value Raw value.
	 * @return string A known slug, or ''.
	 */
	function bb_validate_icon( $value ): string {

		return bb_icons()->validate( $value );
	}
}

if ( ! function_exists( 'bb_icon_options' ) ) {

	/**
	 * The flat icon `select` options (slug => "Category — Label").
	 *
	 * @return array<string, string>
	 */
	function bb_icon_options(): array {

		return bb_icons()->flat_options();
	}
}

if ( ! function_exists( 'bb_icon_field' ) ) {

	/**
	 * A standard icon field definition for a section schema.
	 *
	 * WHY A `select`
	 * --------------
	 * The page builder already renders `select` fields (including inside
	 * repeaters), so an icon control works today with no new field type and no
	 * change to the builder's JavaScript. The visual picker is a progressive
	 * ENHANCEMENT layered on top of the same `<select>` (assets/js/admin/
	 * icon-picker.js), so the editor degrades to a searchable-enough native
	 * control if the script never runs.
	 *
	 * @param string $label Translated label.
	 * @return array<string, mixed>
	 */
	function bb_icon_field( string $label = '' ): array {

		if ( '' === $label ) {
			$label = __( 'Icon', 'business-builder' );
		}

		return array(
			'type'        => 'select',
			'label'       => $label,
			'default'     => '',
			'options'     => array( '' => __( 'No icon', 'business-builder' ) ) + bb_icon_options(),
			'data-bb-icon-picker' => '1',
			'description' => __( 'Choose an icon from the Font Awesome library. Icons are loaded only when they are used.', 'business-builder' ),
		);
	}
}
