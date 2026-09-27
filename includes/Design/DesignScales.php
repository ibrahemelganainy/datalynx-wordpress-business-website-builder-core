<?php

namespace BusinessBuilderCore\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Design scale library (Phase 21 §27, §28, §29).
 *
 * Turns raw numeric controls into NAMED, VISUAL choices so the Studio can show what a value
 * means instead of printing a pixel count (§27).
 *
 * ARCHITECTURE
 * ------------
 * Nothing here defines storage or tokens. Each entry simply proposes a VALUE for a control the
 * Theme already declares; the value is then written through the existing sanitizer. Every group
 * is discovered from the schema, so a control added by a pack is picked up automatically.
 *
 * Each option carries a `bars` hint (1..5) so the UI can draw a proportional bar rather than
 * describing the result in words.
 */
class DesignScales {

	/**
	 * Named steps for each scale group.
	 *
	 * Keys are schema control keys; values are the named options offered for that control.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function groups(): array {

		$groups = array(

			/* ------------------------------------------------ density */
			'section_padding' => array(
				'label'   => __( 'Section spacing', 'business-builder' ),
				'help'    => __( 'How much vertical room each section has. Compact fits more on screen; spacious feels calmer and more premium.', 'business-builder' ),
				'options' => array(
					array( 'label' => __( 'Compact', 'business-builder' ),  'value' => '56px',  'bars' => 2 ),
					array( 'label' => __( 'Balanced', 'business-builder' ), 'value' => '88px',  'bars' => 3 ),
					array( 'label' => __( 'Spacious', 'business-builder' ), 'value' => '120px', 'bars' => 5 ),
				),
			),
			'grid_gap' => array(
				'label'   => __( 'Grid spacing', 'business-builder' ),
				'help'    => __( 'The gap between cards in a grid.', 'business-builder' ),
				'options' => array(
					array( 'label' => __( 'Tight', 'business-builder' ),    'value' => '16px', 'bars' => 2 ),
					array( 'label' => __( 'Balanced', 'business-builder' ), 'value' => '24px', 'bars' => 3 ),
					array( 'label' => __( 'Airy', 'business-builder' ),     'value' => '36px', 'bars' => 5 ),
				),
			),
			'card_padding' => array(
				'label'   => __( 'Card padding', 'business-builder' ),
				'help'    => __( 'The inner spacing of cards. More padding feels more generous.', 'business-builder' ),
				'options' => array(
					array( 'label' => __( 'Tight', 'business-builder' ),    'value' => '18px', 'bars' => 2 ),
					array( 'label' => __( 'Balanced', 'business-builder' ), 'value' => '26px', 'bars' => 3 ),
					array( 'label' => __( 'Generous', 'business-builder' ), 'value' => '36px', 'bars' => 5 ),
				),
			),
			'heading_scale' => array(
				'label'   => __( 'Heading size', 'business-builder' ),
				'help'    => __( 'Scales every heading up or down, keeping the hierarchy intact.', 'business-builder' ),
				'options' => array(
					array( 'label' => __( 'Subtle', 'business-builder' ), 'value' => '0.94', 'bars' => 2 ),
					array( 'label' => __( 'Default', 'business-builder' ), 'value' => '1',   'bars' => 3 ),
					array( 'label' => __( 'Large', 'business-builder' ),  'value' => '1.12', 'bars' => 5 ),
				),
			),
			'heading_letter_spacing' => array(
				'label'   => __( 'Heading tracking', 'business-builder' ),
				'help'    => __( 'Letter spacing in headings. Tighter feels editorial; looser feels airy.', 'business-builder' ),
				'options' => array(
					array( 'label' => __( 'Tight', 'business-builder' ),   'value' => '-0.03', 'bars' => 2 ),
					array( 'label' => __( 'Default', 'business-builder' ), 'value' => '0',     'bars' => 3 ),
					array( 'label' => __( 'Wide', 'business-builder' ),    'value' => '0.03',  'bars' => 5 ),
				),
			),

			/* ------------------------------------------------ shape */
			'radius_md' => array(
				'label'   => __( 'Corner roundness', 'business-builder' ),
				'help'    => __( 'The base rounding applied across the interface.', 'business-builder' ),
				'options' => array(
					array( 'label' => __( 'Square', 'business-builder' ),  'value' => '0.125rem', 'bars' => 1 ),
					array( 'label' => __( 'Soft', 'business-builder' ),    'value' => '0.5rem',   'bars' => 3 ),
					array( 'label' => __( 'Rounded', 'business-builder' ), 'value' => '1rem',     'bars' => 5 ),
				),
			),
			'card_radius' => array(
				'label'   => __( 'Card roundness', 'business-builder' ),
				'help'    => __( 'How rounded cards are. Sharp reads formal; round reads friendly.', 'business-builder' ),
				'options' => array(
					array( 'label' => __( 'Sharp', 'business-builder' ),   'value' => '2px',  'bars' => 1 ),
					array( 'label' => __( 'Soft', 'business-builder' ),    'value' => '12px', 'bars' => 3 ),
					array( 'label' => __( 'Rounded', 'business-builder' ), 'value' => '22px', 'bars' => 5 ),
				),
			),
			'input_radius' => array(
				'label'   => __( 'Input roundness', 'business-builder' ),
				'help'    => __( 'The shape of form fields.', 'business-builder' ),
				'options' => array(
					array( 'label' => __( 'Square', 'business-builder' ), 'value' => '2px',  'bars' => 1 ),
					array( 'label' => __( 'Soft', 'business-builder' ),   'value' => '8px',  'bars' => 3 ),
					array( 'label' => __( 'Rounded', 'business-builder' ), 'value' => '16px', 'bars' => 5 ),
				),
			),
			'button_radius' => array(
				'label'   => __( 'Button roundness', 'business-builder' ),
				'help'    => __( 'The shape of every button.', 'business-builder' ),
				'options' => array(
					array( 'label' => __( 'Square', 'business-builder' ), 'value' => '2px',   'bars' => 1 ),
					array( 'label' => __( 'Soft', 'business-builder' ),   'value' => '8px',   'bars' => 3 ),
					array( 'label' => __( 'Pill', 'business-builder' ),   'value' => '999px', 'bars' => 5 ),
				),
			),
			'border_thickness' => array(
				'label'   => __( 'Border weight', 'business-builder' ),
				'help'    => __( 'How heavy borders and dividers are.', 'business-builder' ),
				'options' => array(
					array( 'label' => __( 'None', 'business-builder' ),   'value' => '0px', 'bars' => 1 ),
					array( 'label' => __( 'Hairline', 'business-builder' ), 'value' => '1px', 'bars' => 3 ),
					array( 'label' => __( 'Bold', 'business-builder' ),   'value' => '2px', 'bars' => 5 ),
				),
			),

			/* ------------------------------------------------ shell */
			'header_height' => array(
				'label'   => __( 'Header height', 'business-builder' ),
				'help'    => __( 'A taller header feels grander; a shorter one feels compact.', 'business-builder' ),
				'options' => array(
					array( 'label' => __( 'Compact', 'business-builder' ), 'value' => '64px', 'bars' => 2 ),
					array( 'label' => __( 'Standard', 'business-builder' ), 'value' => '80px', 'bars' => 3 ),
					array( 'label' => __( 'Grand', 'business-builder' ),   'value' => '96px', 'bars' => 5 ),
				),
			),
			'header_blur' => array(
				'label'   => __( 'Header glass', 'business-builder' ),
				'help'    => __( 'Backdrop blur behind the header. A glass header lets the hero show through.', 'business-builder' ),
				'options' => array(
					array( 'label' => __( 'Solid', 'business-builder' ), 'value' => '0px',  'bars' => 1 ),
					array( 'label' => __( 'Light glass', 'business-builder' ), 'value' => '8px', 'bars' => 3 ),
					array( 'label' => __( 'Heavy glass', 'business-builder' ), 'value' => '18px', 'bars' => 5 ),
				),
			),
			'nav_gap' => array(
				'label'   => __( 'Navigation spacing', 'business-builder' ),
				'help'    => __( 'The gap between navigation links.', 'business-builder' ),
				'options' => array(
					array( 'label' => __( 'Tight', 'business-builder' ),    'value' => '16px', 'bars' => 2 ),
					array( 'label' => __( 'Balanced', 'business-builder' ), 'value' => '26px', 'bars' => 3 ),
					array( 'label' => __( 'Wide', 'business-builder' ),     'value' => '40px', 'bars' => 5 ),
				),
			),
			'footer_padding' => array(
				'label'   => __( 'Footer spacing', 'business-builder' ),
				'help'    => __( 'Vertical room inside the footer.', 'business-builder' ),
				'options' => array(
					array( 'label' => __( 'Compact', 'business-builder' ),  'value' => '40px', 'bars' => 2 ),
					array( 'label' => __( 'Balanced', 'business-builder' ), 'value' => '64px', 'bars' => 3 ),
					array( 'label' => __( 'Spacious', 'business-builder' ), 'value' => '88px', 'bars' => 5 ),
				),
			),

			/* ------------------------------------------------ motion */
			'motion_speed' => array(
				'label'   => __( 'Motion tempo', 'business-builder' ),
				'help'    => __( 'How quickly interface transitions feel. Slower reads more cinematic.', 'business-builder' ),
				'options' => array(
					array( 'label' => __( 'Snappy', 'business-builder' ),    'value' => '0.16', 'bars' => 1 ),
					array( 'label' => __( 'Balanced', 'business-builder' ),  'value' => '0.24', 'bars' => 3 ),
					array( 'label' => __( 'Cinematic', 'business-builder' ), 'value' => '0.40', 'bars' => 5 ),
				),
			),
			'reveal_duration' => array(
				'label'   => __( 'Reveal speed', 'business-builder' ),
				'help'    => __( 'How long a section takes to animate in as you scroll.', 'business-builder' ),
				'options' => array(
					array( 'label' => __( 'Quick', 'business-builder' ),    'value' => '0.30', 'bars' => 2 ),
					array( 'label' => __( 'Balanced', 'business-builder' ), 'value' => '0.48', 'bars' => 3 ),
					array( 'label' => __( 'Slow', 'business-builder' ),     'value' => '0.72', 'bars' => 5 ),
				),
			),
			'hover_lift' => array(
				'label'   => __( 'Hover lift', 'business-builder' ),
				'help'    => __( 'How far cards rise when you hover them. Set to none for a completely static feel.', 'business-builder' ),
				'options' => array(
					array( 'label' => __( 'None', 'business-builder' ),     'value' => '0px', 'bars' => 1 ),
					array( 'label' => __( 'Subtle', 'business-builder' ),   'value' => '4px', 'bars' => 3 ),
					array( 'label' => __( 'Pronounced', 'business-builder' ), 'value' => '10px', 'bars' => 5 ),
				),
			),
		);

		/**
		 * Filter the named scale groups.
		 *
		 * @param array $groups Control key => group definition.
		 */
		$groups = apply_filters( 'bb_design_scales', $groups );

		return is_array( $groups ) ? $groups : array();
	}

	/**
	 * Only the groups whose control actually exists in the schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function available(): array {

		if ( ! function_exists( 'bb_theme_design_schema' ) ) {
			return array();
		}

		$known = array();

		foreach ( bb_theme_design_schema() as $control ) {

			if ( is_array( $control ) && isset( $control['key'] ) ) {
				$known[ (string) $control['key'] ] = array(
					'label'   => (string) ( $control['label'] ?? $control['key'] ),
					'default' => (string) ( $control['default'] ?? '' ),
					'type'    => (string) ( $control['type'] ?? '' ),
					'unit'    => (string) ( $control['unit'] ?? '' ),
				);
			}
		}

		$out = array();

		foreach ( $this->groups() as $key => $group ) {

			if ( ! isset( $known[ $key ] ) ) {
				continue;
			}

			$group['key']     = $key;
			$group['default'] = $known[ $key ]['default'];
			$group['type']    = $known[ $key ]['type'];

			$out[ $key ] = $group;
		}

		return $out;
	}
}