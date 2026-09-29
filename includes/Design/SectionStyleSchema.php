<?php

namespace BusinessBuilderCore\Design;

use BusinessBuilderCore\Builder\SectionRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Section Style Schema (Phase 22 §14-§19, §25, §29, §31).
 *
 * A SITE-WIDE, PER-SECTION-TYPE visual customization layer. It is the piece that makes the
 * Studio "connected to the real rendering system" for individual sections rather than only for
 * the site as a whole.
 *
 * WHAT IT OWNS (and what it deliberately does not)
 * ------------------------------------------------
 *   - It owns a GENERIC control catalogue: background, colours, typography, spacing, borders,
 *     radius, shadow, glass, grid, motion. Every one of those is a `--bb-*` token.
 *   - It owns NOTHING domain-specific. It never mentions `lawyers`, `doctors`, `law_firm` or
 *     `medical`. It asks `SectionRegistry` which sections exist, and builds a panel for each
 *     one it finds. A new pack's sections appear automatically (§14, §31).
 *   - It does not create a second token namespace. A section override is emitted as a SCOPED
 *     block of existing `--bb-*` tokens:
 *
 *         .bb-section-lawyers { --bb-section-bg: #0f172a; --bb-card-radius: 4px; }
 *
 *     so every token a section does not override is simply INHERITED from the design (§16
 *     cascade). Nothing is copied, nothing is duplicated.
 *
 * STORAGE (§29)
 * -------------
 * One theme mod per section type: `bb_design_section_<type>`, holding an array of
 * `token => value`. Theme mods are per-site by construction, so Multisite isolation is
 * inherited from WordPress rather than re-implemented.
 *
 * WHY A THEME MOD AND NOT POST META
 * ---------------------------------
 * A section's VISUAL treatment is site-wide presentation, not page content. Putting it in
 * `_bb_page_sections` (the per-page section meta) would make the same section look different on
 * every page and would scatter one design decision across N posts — exactly what §29 forbids.
 * Section CONTENT stays in post meta, untouched.
 *
 * THE CASCADE (§16, §30)
 * ----------------------
 *   Core tokens.css defaults
 *     → design preset (pack)
 *     → selected design
 *     → global user customization (`bb_design_<key>`)
 *     → SECTION customization (`bb_design_section_<type>`)   ← this class
 *     → component customization (existing card_variant layer)
 *     → final CSS
 *
 * The section block is emitted AFTER the Theme's `:root`, so it wins by source order and by
 * specificity without any `!important`.
 *
 * SECURITY (§33)
 * --------------
 * Every value is validated by the SAME whitelist the global schema uses:
 *   - `color`   → `sanitize_hex_color()`
 *   - `select`  → must be one of the control's own declared options
 *   - `number`  → clamped to the control's own min/max
 *   - `length`  → numeric + a unit from the control's own declared unit
 * A token that is not in this class's own catalogue is DISCARDED, so a forged field name can
 * never become a CSS declaration, and no free-form CSS can be stored.
 */
class SectionStyleSchema {

	/**
	 * Theme-mod prefix. One mod per section type.
	 */
	public const MOD_PREFIX = 'bb_design_section_';

	/**
	 * The prefix every section-scoped token carries.
	 *
	 * Kept distinct from the global token names on purpose: `--bb-section-bg` is a SECTION
	 * override, while `--bb-bg-color` is the SITE background. Reusing the global name would make
	 * a section override leak back into the site-wide token (custom properties inherit downward,
	 * but a section block must not be readable as a global default).
	 */
	public const TOKEN_PREFIX = '--bb-section-';

	/**
	 * Section types whose panels expose card controls (they render a collection of cards).
	 *
	 * Derived from the registry's own `supports` declaration where present; this is only the
	 * conservative fallback so a section that has not declared anything still gets a usable
	 * panel. It is a GENERIC heuristic (a section that renders repeated items), not a list of
	 * business sections.
	 */
	protected const CARD_CAPABLE_FALLBACK = array( 'grid', 'items', 'cards', 'list' );

	protected SectionRegistry $registry;

	public function __construct( ?SectionRegistry $registry = null ) {
		$this->registry = $registry ?: new SectionRegistry();
	}

	/**
	 * Register the front-end style emission.
	 */
	public function register(): void {

		/*
		 * The section block is printed on `wp_head` AFTER the Theme's token stylesheet, so it
		 * simply appears later in the cascade. `wp_add_inline_style()` is deliberately NOT used:
		 * the Theme's token handle may be printed before this runs, and an inline style attached
		 * to a later handle is a fragile ordering guarantee. A direct `wp_head` print at a late
		 * priority is deterministic and adds no HTTP request (§34).
		 */
		add_action( 'wp_head', array( $this, 'print_styles' ), 99 );
	}

	/* =====================================================================
	 * 1. Discovery — which sections exist (§14, §31)
	 * ================================================================== */

	/**
	 * Every section type the platform currently knows about, grouped for the UI.
	 *
	 * Reads the EXISTING `SectionRegistry`, so:
	 *   - a core section appears because core registered it;
	 *   - a pack section appears because the pack registered it;
	 *   - a new pack's sections appear with no change to this class.
	 *
	 * @return array<string, array<string, mixed>> section type => descriptor
	 */
	public function sections(): array {

		$registered = $this->registry->get_all();

		if ( ! is_array( $registered ) ) {
			return array();
		}

		$out = array();

		foreach ( $registered as $slug => $section ) {

			$slug = sanitize_key( (string) $slug );

			if ( '' === $slug || ! is_array( $section ) ) {
				continue;
			}

			$out[ $slug ] = array(
				'type'        => $slug,
				'label'       => isset( $section['label'] ) ? (string) $section['label'] : $slug,
				'description' => isset( $section['description'] ) ? (string) $section['description'] : '',
				'category'    => isset( $section['category'] ) ? sanitize_key( (string) $section['category'] ) : 'general',
				'icon'        => isset( $section['icon'] ) ? (string) $section['icon'] : 'dashicons-layout',
				'card'        => $this->is_card_capable( $section, $slug ),
				'variants'    => function_exists( 'bb_section_variant_options' )
					? bb_section_variant_options( $slug )
					: array(),
			);
		}

		ksort( $out );

		return $out;
	}

	/**
	 * Whether a section renders a collection of cards (so card controls are meaningful).
	 *
	 * GENERIC rules, in order - never a hardcoded business-section list:
	 *
	 *   1. the section declares a repeatable concept in its own `supports`;
	 *   2. the section declares a `variant` SETTING (which is how every Phase 11
	 *      section advertises that it has layout variants) and the Phase 11
	 *      registry confirms it has more than one;
	 *   3. the Phase 11 registry independently reports a non-default variant.
	 *
	 * MEASURED NOTE: rule 2 exists because the registry entry does NOT carry its
	 * own `type` key - the type is the ARRAY KEY it was registered under. An
	 * earlier version of this method looked for `$section['type']` and therefore
	 * found nothing, which silently disabled the whole Cards group for every pack
	 * section:
	 *
	 *     card-capable = features            <-- wrong: only the core section
	 *
	 * The type is now passed in by the caller, which is where it is actually
	 * known.
	 *
	 * @param array  $section Registry entry.
	 * @param string $type    Section type slug (the registry array key).
	 * @return bool
	 */
	protected function is_card_capable( array $section, string $type = '' ): bool {

		/*
		 * Phase 23 §7: a DECLARED capability is authoritative.
		 *
		 * The audit measured that `supports` was documentation nothing read, so the
		 * Cards group was shown on a heuristic (docs/phase23-audit.md G3). Now a
		 * section that declares `cards` gets the group, and a section that declares
		 * no cards capability cannot be offered card controls even if it happens to
		 * have a `columns` setting.
		 *
		 * When `capabilities` is absent the registry has already defaulted it to
		 * `supports`, so this reads correctly for every pre-existing section too.
		 */
		$declared = isset( $section['capabilities'] ) && is_array( $section['capabilities'] )
			? array_map( 'sanitize_key', $section['capabilities'] )
			: array();

		if ( in_array( 'cards', $declared, true ) ) {
			return true;
		}

		$supports = isset( $section['supports'] ) && is_array( $section['supports'] )
			? array_map( 'strval', $section['supports'] )
			: array();

		foreach ( self::CARD_CAPABLE_FALLBACK as $needle ) {

			if ( in_array( $needle, $supports, true ) ) {
				return true;
			}
		}

		/*
		 * A section that exposes a `variant` setting is advertising that it can be
		 * laid out more than one way - which is exactly the case where card
		 * presentation controls are meaningful. This is how a pack's own variant
		 * registration (Phase 11) turns the Cards group on, with no business
		 * knowledge in this class.
		 */
		$settings = isset( $section['settings'] ) && is_array( $section['settings'] )
			? $section['settings']
			: array();

		if ( isset( $settings['variant'] ) ) {
			return true;
		}

		/*
		 * A `card_variant` setting is a direct statement that the section renders
		 * components that can be styled as cards.
		 */
		if ( isset( $settings['card_variant'] ) || isset( $settings['columns'] ) ) {
			return true;
		}

		if ( '' === $type ) {
			return false;
		}

		/* The Phase 11 registry is the authority on whether a variant exists. */
		if ( function_exists( 'bb_section_variants' ) ) {
			return bb_section_variants()->has_variants( $type );
		}

		return false;
	}

	/* =====================================================================
	 * 2. The control catalogue (generic, §15, §18, §19)
	 * ================================================================== */

	/**
	 * The full generic control catalogue, grouped.
	 *
	 * Each control is:
	 *   key    unique within the group
	 *   token  the `--bb-section-*` custom property it writes
	 *   type   color | select | number | length
	 *   label  human label
	 *   group  UI group
	 *   cards  true when the control only applies to card-based sections
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function controls(): array {

		$controls = array();

		foreach ( $this->groups() as $group_key => $group ) {

			foreach ( $group['controls'] as $control ) {
				$control['group']      = $group_key;
				$controls[ $control['key'] ] = $control;
			}
		}

		return $controls;
	}

	/**
	 * The control groups, in Studio order (§15, §18, §19, §23).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function groups(): array {

		$align_options = array(
			'inherit' => __( 'Inherit', 'business-builder' ),
			'start'   => __( 'Start', 'business-builder' ),
			'center'  => __( 'Center', 'business-builder' ),
			'end'     => __( 'End', 'business-builder' ),
		);

		return array(

			/* ---- Layout & grid ------------------------------------------------ */
			'layout' => array(
				'label'    => __( 'Layout & grid', 'business-builder' ),
				'help'     => __( 'How wide the section is and how its items are arranged.', 'business-builder' ),
				'controls' => array(
					array(
						'key'     => 'columns_desktop',
						'token'   => self::TOKEN_PREFIX . 'grid-columns',
						'type'    => 'select',
						'label'   => __( 'Columns (desktop)', 'business-builder' ),
						'default' => '',
						'options' => array(
							'' => __( 'Inherit', 'business-builder' ),
							'1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6',
						),
						'cards'   => true,
					),
					array(
						'key'     => 'columns_tablet',
						'token'   => self::TOKEN_PREFIX . 'grid-columns-tablet',
						'type'    => 'select',
						'label'   => __( 'Columns (tablet)', 'business-builder' ),
						'default' => '',
						'options' => array(
							'' => __( 'Inherit', 'business-builder' ),
							'1' => '1', '2' => '2', '3' => '3', '4' => '4',
						),
						'cards'   => true,
					),
					array(
						'key'     => 'columns_mobile',
						'token'   => self::TOKEN_PREFIX . 'grid-columns-mobile',
						'type'    => 'select',
						'label'   => __( 'Columns (mobile)', 'business-builder' ),
						'default' => '',
						'options' => array(
							'' => __( 'Inherit', 'business-builder' ),
							'1' => '1', '2' => '2',
						),
						'cards'   => true,
					),
					array(
						'key'     => 'gap',
						'token'   => self::TOKEN_PREFIX . 'grid-gap',
						'type'    => 'length',
						'label'   => __( 'Space between items', 'business-builder' ),
						'default' => '',
						'min'     => 0,
						'max'     => 96,
						'unit'    => 'px',
						'cards'   => true,
					),
					array(
						'key'     => 'content_width',
						'token'   => self::TOKEN_PREFIX . 'content-width',
						'type'    => 'length',
						'label'   => __( 'Text width', 'business-builder' ),
						'default' => '',
						/*
						 * `rem`, not `ch`: only px / rem / em / % are accepted by the Theme's
						 * length validator, and this class deliberately uses the same envelope so
						 * a section control can never hold a value the global schema refuses.
						 */
						'min'     => 25,
						'max'     => 70,
						'unit'    => 'rem',
					),
					array(
						'key'     => 'align',
						'token'   => self::TOKEN_PREFIX . 'align',
						'type'    => 'select',
						'label'   => __( 'Content alignment', 'business-builder' ),
						'default' => '',
						'options' => $align_options,
					),
				),
			),

			/* ---- Background --------------------------------------------------- */
			'background' => array(
				'label'    => __( 'Background', 'business-builder' ),
				'help'     => __( 'Give this section its own surface, so it stands apart from the rest of the page.', 'business-builder' ),
				'controls' => array(
					array(
						'key'     => 'bg_color',
						'token'   => self::TOKEN_PREFIX . 'bg',
						'type'    => 'color',
						'label'   => __( 'Background colour', 'business-builder' ),
						'default' => '',
					),
					array(
						'key'     => 'bg_style',
						'token'   => self::TOKEN_PREFIX . 'bg-image',
						'type'    => 'select',
						'label'   => __( 'Background style', 'business-builder' ),
						'default' => '',
						'options' => array(
							''         => __( 'Inherit', 'business-builder' ),
							'none'     => __( 'Solid colour', 'business-builder' ),
							'soft'     => __( 'Soft gradient', 'business-builder' ),
							'mesh'     => __( 'Mesh gradient', 'business-builder' ),
							'aurora'   => __( 'Aurora', 'business-builder' ),
							'dark'     => __( 'Dark radial', 'business-builder' ),
							'glow'     => __( 'Glow', 'business-builder' ),
							'grid'     => __( 'Subtle grid', 'business-builder' ),
						),
					),
					array(
						'key'     => 'bg_overlay_opacity',
						'token'   => self::TOKEN_PREFIX . 'bg-overlay-opacity',
						'type'    => 'number',
						'label'   => __( 'Overlay strength', 'business-builder' ),
						'default' => '',
						'min'     => 0,
						'max'     => 1,
						'step'    => 0.05,
					),
				),
			),

			/* ---- Colours ------------------------------------------------------ */
			'colors' => array(
				'label'    => __( 'Colours', 'business-builder' ),
				'help'     => __( 'Override the site colours for this section only. Anything you leave alone keeps the design\'s colour.', 'business-builder' ),
				'controls' => array(
					array(
						'key'     => 'text_color',
						'token'   => self::TOKEN_PREFIX . 'text',
						'type'    => 'color',
						'label'   => __( 'Body text', 'business-builder' ),
						'default' => '',
					),
					array(
						'key'     => 'heading_color',
						'token'   => self::TOKEN_PREFIX . 'heading',
						'type'    => 'color',
						'label'   => __( 'Headings', 'business-builder' ),
						'default' => '',
					),
					array(
						'key'     => 'accent_color',
						'token'   => self::TOKEN_PREFIX . 'accent',
						'type'    => 'color',
						'label'   => __( 'Accent', 'business-builder' ),
						'default' => '',
					),
					array(
						'key'     => 'button_bg',
						'token'   => self::TOKEN_PREFIX . 'button-bg',
						'type'    => 'color',
						'label'   => __( 'Button background', 'business-builder' ),
						'default' => '',
					),
					array(
						'key'     => 'button_color',
						'token'   => self::TOKEN_PREFIX . 'button-color',
						'type'    => 'color',
						'label'   => __( 'Button text', 'business-builder' ),
						'default' => '',
					),
				),
			),

			/* ---- Typography --------------------------------------------------- */
			'typography' => array(
				'label'    => __( 'Typography', 'business-builder' ),
				'help'     => __( 'Adjust this section\'s text scale without changing the rest of the site.', 'business-builder' ),
				'controls' => array(
					array(
						'key'     => 'heading_scale',
						'token'   => self::TOKEN_PREFIX . 'heading-scale',
						'type'    => 'number',
						'label'   => __( 'Heading size', 'business-builder' ),
						'default' => '',
						'min'     => 0.7,
						'max'     => 1.6,
						'step'    => 0.05,
					),
					array(
						'key'     => 'heading_weight',
						'token'   => self::TOKEN_PREFIX . 'heading-weight',
						'type'    => 'select',
						'label'   => __( 'Heading weight', 'business-builder' ),
						'default' => '',
						'options' => array(
							''    => __( 'Inherit', 'business-builder' ),
							'400' => __( 'Regular', 'business-builder' ),
							'500' => __( 'Medium', 'business-builder' ),
							'600' => __( 'Semibold', 'business-builder' ),
							'700' => __( 'Bold', 'business-builder' ),
							'800' => __( 'Extra bold', 'business-builder' ),
						),
					),
					array(
						'key'     => 'heading_transform',
						'token'   => self::TOKEN_PREFIX . 'heading-transform',
						'type'    => 'select',
						'label'   => __( 'Heading case', 'business-builder' ),
						'default' => '',
						'options' => array(
							''         => __( 'Inherit', 'business-builder' ),
							'none'     => __( 'As typed', 'business-builder' ),
							'uppercase' => __( 'UPPERCASE', 'business-builder' ),
							'capitalize' => __( 'Capitalize', 'business-builder' ),
						),
					),
					array(
						'key'     => 'letter_spacing',
						'token'   => self::TOKEN_PREFIX . 'letter-spacing',
						'type'    => 'number',
						'label'   => __( 'Letter spacing', 'business-builder' ),
						'default' => '',
						'min'     => -0.08,
						'max'     => 0.16,
						'step'    => 0.005,
						'unit'    => 'em',
					),
				),
			),

			/* ---- Spacing ------------------------------------------------------ */
			'spacing' => array(
				'label'    => __( 'Spacing', 'business-builder' ),
				'help'     => __( 'Breathing room above, below and inside this section.', 'business-builder' ),
				'controls' => array(
					array(
						'key'     => 'padding_block',
						'token'   => self::TOKEN_PREFIX . 'padding-block',
						'type'    => 'length',
						'label'   => __( 'Vertical padding', 'business-builder' ),
						'default' => '',
						'min'     => 0,
						'max'     => 200,
						'unit'    => 'px',
					),
					array(
						'key'     => 'padding_inline',
						'token'   => self::TOKEN_PREFIX . 'padding-inline',
						'type'    => 'length',
						'label'   => __( 'Side padding', 'business-builder' ),
						'default' => '',
						'min'     => 0,
						'max'     => 120,
						'unit'    => 'px',
					),
					array(
						'key'     => 'card_padding',
						'token'   => self::TOKEN_PREFIX . 'card-padding',
						'type'    => 'length',
						'label'   => __( 'Card padding', 'business-builder' ),
						'default' => '',
						'min'     => 0,
						'max'     => 72,
						'unit'    => 'px',
						'cards'   => true,
					),
					array(
						'key'     => 'heading_gap',
						'token'   => self::TOKEN_PREFIX . 'heading-gap',
						'type'    => 'length',
						'label'   => __( 'Space under heading', 'business-builder' ),
						'default' => '',
						'min'     => 0,
						'max'     => 72,
						'unit'    => 'px',
					),
				),
			),

			/* ---- Cards (§18) -------------------------------------------------- */
			'cards' => array(
				'label'    => __( 'Cards', 'business-builder' ),
				'help'     => __( 'The look of the items inside this section.', 'business-builder' ),
				'cards'    => true,
				'controls' => array(
					array(
						'key'     => 'card_bg',
						'token'   => self::TOKEN_PREFIX . 'card-bg',
						'type'    => 'color',
						'label'   => __( 'Card background', 'business-builder' ),
						'default' => '',
						'cards'   => true,
					),
					array(
						'key'     => 'card_border_color',
						'token'   => self::TOKEN_PREFIX . 'card-border-color',
						'type'    => 'color',
						'label'   => __( 'Card border colour', 'business-builder' ),
						'default' => '',
						'cards'   => true,
					),
					array(
						'key'     => 'card_radius',
						'token'   => self::TOKEN_PREFIX . 'card-radius',
						'type'    => 'length',
						'label'   => __( 'Card roundness', 'business-builder' ),
						'default' => '',
						'min'     => 0,
						'max'     => 48,
						'unit'    => 'px',
						'cards'   => true,
					),
					array(
						'key'     => 'card_shadow',
						'token'   => self::TOKEN_PREFIX . 'card-shadow',
						'type'    => 'select',
						'label'   => __( 'Card shadow', 'business-builder' ),
						'default' => '',
						'options' => array(
							''       => __( 'Inherit', 'business-builder' ),
							'none'   => __( 'Flat', 'business-builder' ),
							'soft'   => __( 'Soft', 'business-builder' ),
							'medium' => __( 'Medium', 'business-builder' ),
							'strong' => __( 'Strong', 'business-builder' ),
						),
						'cards'   => true,
					),
					array(
						'key'     => 'card_image_ratio',
						'token'   => self::TOKEN_PREFIX . 'card-image-ratio',
						'type'    => 'select',
						'label'   => __( 'Card image shape', 'business-builder' ),
						'default' => '',
						'options' => array(
							''      => __( 'Inherit', 'business-builder' ),
							'1/1'   => __( 'Square', 'business-builder' ),
							'4/3'   => __( 'Landscape', 'business-builder' ),
							'3/4'   => __( 'Portrait', 'business-builder' ),
							'16/9'  => __( 'Wide', 'business-builder' ),
						),
						'cards'   => true,
					),
				),
			),

			/* ---- Borders & radius -------------------------------------------- */
			'shape' => array(
				'label'    => __( 'Borders & shape', 'business-builder' ),
				'controls' => array(
					array(
						'key'     => 'border_width',
						'token'   => self::TOKEN_PREFIX . 'border-width',
						'type'    => 'length',
						'label'   => __( 'Border thickness', 'business-builder' ),
						'default' => '',
						'min'     => 0,
						'max'     => 8,
						'unit'    => 'px',
					),
					array(
						'key'     => 'border_color',
						'token'   => self::TOKEN_PREFIX . 'border-color',
						'type'    => 'color',
						'label'   => __( 'Border colour', 'business-builder' ),
						'default' => '',
					),
					array(
						'key'     => 'radius',
						'token'   => self::TOKEN_PREFIX . 'radius',
						'type'    => 'length',
						'label'   => __( 'Corner roundness', 'business-builder' ),
						'default' => '',
						'min'     => 0,
						'max'     => 64,
						'unit'    => 'px',
					),
				),
			),

			/* ---- Glass (§19) -------------------------------------------------- */
			'glass' => array(
				'label'    => __( 'Glass effect', 'business-builder' ),
				'help'     => __( 'A frosted, translucent surface. Leave it off for a solid look.', 'business-builder' ),
				'controls' => array(
					array(
						'key'     => 'glass',
						'token'   => self::TOKEN_PREFIX . 'glass-blur',
						'type'    => 'select',
						'label'   => __( 'Glass', 'business-builder' ),
						'default' => '',
						'options' => array(
							''       => __( 'Inherit', 'business-builder' ),
							'off'    => __( 'Off', 'business-builder' ),
							'subtle' => __( 'Subtle', 'business-builder' ),
							'medium' => __( 'Medium', 'business-builder' ),
							'strong' => __( 'Strong', 'business-builder' ),
						),
					),
					array(
						'key'     => 'glass_opacity',
						'token'   => self::TOKEN_PREFIX . 'glass-opacity',
						'type'    => 'number',
						'label'   => __( 'Glass transparency', 'business-builder' ),
						'default' => '',
						'min'     => 0.2,
						'max'     => 1,
						'step'    => 0.05,
					),
					array(
						'key'     => 'glass_border_opacity',
						'token'   => self::TOKEN_PREFIX . 'glass-border-opacity',
						'type'    => 'number',
						'label'   => __( 'Glass border visibility', 'business-builder' ),
						'default' => '',
						'min'     => 0,
						'max'     => 1,
						'step'    => 0.05,
					),
				),
			),

			/* ---- Motion (§23) ------------------------------------------------- */
			'motion' => array(
				'label'    => __( 'Motion', 'business-builder' ),
				'help'     => __( 'How this section appears as the visitor scrolls. Motion is always disabled for visitors who ask for reduced motion.', 'business-builder' ),
				'controls' => array(
					array(
						'key'     => 'reveal',
						'token'   => self::TOKEN_PREFIX . 'reveal',
						'type'    => 'select',
						'label'   => __( 'Entrance', 'business-builder' ),
						'default' => '',
						'options' => array(
							''      => __( 'Inherit', 'business-builder' ),
							'off'   => __( 'Off', 'business-builder' ),
							'fade'  => __( 'Fade', 'business-builder' ),
							'up'    => __( 'Slide up', 'business-builder' ),
							'down'  => __( 'Slide down', 'business-builder' ),
							'left'  => __( 'Slide from end', 'business-builder' ),
							'right' => __( 'Slide from start', 'business-builder' ),
							'scale' => __( 'Scale', 'business-builder' ),
							'blur'  => __( 'Blur reveal', 'business-builder' ),
						),
					),
					array(
						'key'     => 'reveal_duration',
						'token'   => self::TOKEN_PREFIX . 'reveal-duration',
						'type'    => 'number',
						'label'   => __( 'Entrance speed', 'business-builder' ),
						'default' => '',
						'min'     => 0.1,
						'max'     => 1.2,
						'step'    => 0.02,
						'unit'    => 's',
					),
					array(
						'key'     => 'reveal_delay',
						'token'   => self::TOKEN_PREFIX . 'reveal-delay',
						'type'    => 'number',
						'label'   => __( 'Entrance delay', 'business-builder' ),
						'default' => '',
						'min'     => 0,
						'max'     => 0.8,
						'step'    => 0.02,
						'unit'    => 's',
					),
					array(
						'key'     => 'hover',
						'token'   => self::TOKEN_PREFIX . 'hover',
						'type'    => 'select',
						'label'   => __( 'Hover effect', 'business-builder' ),
						'default' => '',
						'options' => array(
							''      => __( 'Inherit', 'business-builder' ),
							'none'  => __( 'None', 'business-builder' ),
							'lift'  => __( 'Lift', 'business-builder' ),
							'scale' => __( 'Scale', 'business-builder' ),
							'glow'  => __( 'Glow', 'business-builder' ),
							'zoom'  => __( 'Image zoom', 'business-builder' ),
						),
						'cards'   => true,
					),
				),
			),
		);
	}

	/* =====================================================================
	 * 3. Persistence (§29, §33)
	 * ================================================================== */

	/**
	 * The theme-mod name for a section type.
	 *
	 * @param string $type Section type slug.
	 * @return string
	 */
	public function mod_name( string $type ): string {

		return self::MOD_PREFIX . sanitize_key( $type );
	}

	/**
	 * Read the stored overrides for a section type.
	 *
	 * @param string $type Section type slug.
	 * @return array<string, string> token => value
	 */
	public function overrides( string $type ): array {

		$type = sanitize_key( $type );

		if ( '' === $type ) {
			return array();
		}

		$stored = get_theme_mod( $this->mod_name( $type ), array() );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$catalogue = $this->controls();
		$tokens    = array();

		foreach ( $catalogue as $control ) {
			$tokens[ (string) $control['token'] ] = $control;
		}

		$out = array();

		foreach ( $stored as $token => $value ) {

			$token = (string) $token;

			/* A token outside this class's own catalogue can never be emitted. */
			if ( ! isset( $tokens[ $token ] ) ) {
				continue;
			}

			$clean = $this->sanitize( $value, $tokens[ $token ] );

			if ( '' === $clean ) {
				continue;
			}

			$out[ $token ] = $clean;
		}

		return $out;
	}

	/**
	 * Persist the overrides for a section type.
	 *
	 * An empty submission REMOVES the mod entirely, so "no overrides" is represented by the
	 * absence of the mod rather than by an empty array - which keeps the reset semantics (§30)
	 * exact: resetting a section returns it to the design/global state.
	 *
	 * @param string $type    Section type slug.
	 * @param array  $values  Raw `token => value` pairs (already unslashed).
	 * @return int Number of overrides stored.
	 */
	public function save( string $type, array $values ): int {

		$type = sanitize_key( $type );

		if ( '' === $type ) {
			return 0;
		}

		/*
		 * The section type must be a REGISTERED section, not merely a well-formed
		 * slug.
		 *
		 * MEASURED GAP (Phase 22 test suite, section-binding group):
		 * `save( 'not_a_real_section_xyz', … )` stored a theme mod. The admin
		 * handler already gates on the registry, so this was not exploitable through
		 * the UI — but it meant the VALIDATOR was weaker than the HANDLER, which is
		 * the wrong way round: the storage API is the last line of defence and must
		 * refuse an unknown section on its own.
		 *
		 * It also matters for reset semantics: an unknown type could accumulate a
		 * theme mod that `reset_all()` (which iterates the registry) would never
		 * clean up, leaving orphaned state behind.
		 */
		if ( ! $this->registry->exists( $type ) ) {
			return 0;
		}

		$catalogue = array();

		foreach ( $this->controls() as $control ) {
			$catalogue[ (string) $control['token'] ] = $control;
		}

		$clean = array();

		foreach ( $values as $token => $value ) {

			$token = (string) $token;

			if ( ! isset( $catalogue[ $token ] ) ) {
				continue;
			}

			$sanitized = $this->sanitize( $value, $catalogue[ $token ] );

			if ( '' === $sanitized ) {
				continue;
			}

			$clean[ $token ] = $sanitized;
		}

		if ( empty( $clean ) ) {
			remove_theme_mod( $this->mod_name( $type ) );

			return 0;
		}

		set_theme_mod( $this->mod_name( $type ), $clean );

		return count( $clean );
	}

	/**
	 * Reset ONE section type to the design/global state (§30).
	 *
	 * @param string $type Section type slug.
	 * @return bool
	 */
	public function reset( string $type ): bool {

		$type = sanitize_key( $type );

		if ( '' === $type || ! $this->registry->exists( $type ) ) {
			return false;
		}

		remove_theme_mod( $this->mod_name( $type ) );

		return true;
	}

	/**
	 * Reset EVERY section override for the current site (§30).
	 *
	 * @return int Number of sections whose overrides were removed.
	 */
	public function reset_all(): int {

		$removed = 0;

		foreach ( $this->sections() as $type => $section ) {

			if ( '' !== (string) get_theme_mod( $this->mod_name( (string) $type ), '' ) ) {
				remove_theme_mod( $this->mod_name( (string) $type ) );
				$removed++;
			}
		}

		return $removed;
	}

	/**
	 * How many section types currently carry overrides.
	 *
	 * @return int
	 */
	public function override_count(): int {

		$count = 0;

		foreach ( $this->sections() as $type => $section ) {

			$stored = get_theme_mod( $this->mod_name( (string) $type ), array() );

			if ( is_array( $stored ) && ! empty( $stored ) ) {
				$count++;
			}
		}

		return $count;
	}

	/* =====================================================================
	 * 4. Validation (§33)
	 * ================================================================== */

	/**
	 * Validate one value against its control.
	 *
	 * Returns '' for anything the control does not accept, so an invalid value is DROPPED rather
	 * than stored - the same contract the Theme's own sanitizer uses.
	 *
	 * @param mixed $value   Raw value.
	 * @param array $control Control definition.
	 * @return string Sanitized value, or '' when rejected.
	 */
	public function sanitize( $value, array $control ): string {

		if ( is_array( $value ) ) {
			return '';
		}

		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		switch ( (string) ( $control['type'] ?? '' ) ) {

			case 'color':
				$hex = sanitize_hex_color( $value );

				return is_string( $hex ) ? $hex : '';

			case 'select':
				$options = isset( $control['options'] ) && is_array( $control['options'] )
					? $control['options']
					: array();

				/* '' is always a legal "inherit" value when the control declares it. */
				if ( '' === $value ) {
					return '';
				}

				return isset( $options[ $value ] ) ? $value : '';

			case 'number':
				if ( ! is_numeric( $value ) ) {
					return '';
				}

				$number = (float) $value;
				$min    = isset( $control['min'] ) ? (float) $control['min'] : null;
				$max    = isset( $control['max'] ) ? (float) $control['max'] : null;

				/*
				 * OUT-OF-RANGE IS REJECTED, NOT CLAMPED.
				 *
				 * MEASURED CONTRACT: the Theme's own `bb_theme_sanitize_design_value()`
				 * returns '' for a value outside the control's declared range rather than
				 * pulling it to the boundary. Clamping here would make the section layer
				 * accept values the global layer refuses, which is exactly the kind of
				 * two-different-rules divergence §33 forbids.
				 */
				if ( null !== $min && $number < $min ) {
					return '';
				}

				if ( null !== $max && $number > $max ) {
					return '';
				}

				/*
				 * Normalise the string form so `1` and `1.0` cannot produce two different stored
				 * values for the same setting (which would defeat the "already customised" test).
				 */
				$formatted = rtrim( rtrim( number_format( $number, 3, '.', '' ), '0' ), '.' );

				return '' === $formatted ? '0' : $formatted;

			case 'length':
				$unit = isset( $control['unit'] ) ? (string) $control['unit'] : 'px';
				$unit = preg_replace( '/[^a-z%]/', '', strtolower( $unit ) );

				/*
				 * Only the units the Theme's own validator accepts are allowed. A section
				 * length control can therefore never introduce a unit the global schema
				 * would refuse (`ch`, `vh`, `ms`, …), keeping ONE length vocabulary.
				 */
				if ( ! in_array( $unit, array( 'px', 'rem', 'em', '%' ), true ) ) {
					return '';
				}

				/*
				 * The value must be a number followed by EXACTLY the declared unit (or by
				 * nothing, which is read as the declared unit). Trailing content - the
				 * shape a CSS-injection attempt takes - is rejected outright rather than
				 * truncated, so nothing can ride along after the number.
				 */
				if ( ! preg_match( '/^(-?\d+(?:\.\d+)?)([a-z%]*)$/', $value, $matches ) ) {
					return '';
				}

				$given = strtolower( $matches[2] );

				if ( '' !== $given && $given !== $unit ) {
					return '';
				}

				$number = (float) $matches[1];
				$min    = isset( $control['min'] ) ? (float) $control['min'] : null;
				$max    = isset( $control['max'] ) ? (float) $control['max'] : null;

				/* Out-of-range is rejected, matching the Theme's contract. */
				if ( null !== $min && $number < $min ) {
					return '';
				}

				if ( null !== $max && $number > $max ) {
					return '';
				}

				$formatted = rtrim( rtrim( number_format( $number, 2, '.', '' ), '0' ), '.' );

				return ( '' === $formatted ? '0' : $formatted ) . $unit;

			default:
				return '';
		}
	}

	/* =====================================================================
	 * 5. CSS emission (§16, §25)
	 * ================================================================== */

	/**
	 * Build the scoped CSS block for one section type.
	 *
	 * The returned string is a COMPLETE rule whose declarations are all `--bb-*` custom
	 * properties validated by `sanitize()`. No user text is interpolated into a property name or
	 * a selector: the selector is built from a `sanitize_key()`'d section slug, and every
	 * declaration value is a sanitized hex colour, a whitelisted keyword, a clamped number or a
	 * bounded length.
	 *
	 * @param string $type Section type slug.
	 * @return string
	 */
	public function css_for( string $type ): string {

		$type = sanitize_key( $type );

		if ( '' === $type ) {
			return '';
		}

		$overrides = $this->overrides( $type );

		if ( empty( $overrides ) ) {
			return '';
		}

		$declarations = array();

		foreach ( $overrides as $token => $value ) {

			$value = $this->resolve_keyword( (string) $token, (string) $value );

			if ( '' === $value ) {
				continue;
			}

			$declarations[] = $token . ':' . $value;
		}

		if ( empty( $declarations ) ) {
			return '';
		}

		/*
		 * Scoped to the section's own class (emitted by SectionRenderer as
		 * `bb-section-<type>`), so the override applies to EVERY instance of that section type on
		 * the site - which is exactly the "site-wide per-section design" contract of §16.
		 *
		 * The `--bb-*` values are re-declared on the section element, so any element inside it
		 * that consumes the token (including a nested component) inherits the override. Because
		 * custom properties inherit, an un-overridden token is simply not re-declared and the
		 * design's value keeps flowing down.
		 */
		return '.bb-section-' . $type . '{' . implode( ';', $declarations ) . '}';
	}

	/**
	 * Translate a keyword-valued override into the CSS value it stands for.
	 *
	 * A `select` control stores a SEMANTIC keyword (`medium`, `zoom`, `aurora`) because a
	 * customer must never type raw CSS. This is the single place a keyword becomes a value, and
	 * every mapping is a fixed table - so no arbitrary CSS can be introduced (§33).
	 *
	 * @param string $token Stored token name.
	 * @param string $value Stored keyword/value.
	 * @return string CSS value, or '' when it should be dropped.
	 */
	protected function resolve_keyword( string $token, string $value ): string {

		/* Card shadow levels. */
		if ( self::TOKEN_PREFIX . 'card-shadow' === $token ) {

			$shadows = array(
				'none'   => 'none',
				'soft'   => '0 1px 3px rgba(15, 23, 42, 0.08)',
				'medium' => '0 6px 18px rgba(15, 23, 42, 0.12)',
				'strong' => '0 18px 40px rgba(15, 23, 42, 0.20)',
			);

			return isset( $shadows[ $value ] ) ? $shadows[ $value ] : '';
		}

		/* Section background styles. */
		if ( self::TOKEN_PREFIX . 'bg-image' === $token ) {

			$styles = array(
				'none'   => 'none',
				'soft'   => 'linear-gradient(180deg, var(--bb-color-background), var(--bb-color-surface-muted))',
				'mesh'   => 'radial-gradient(at 12% 18%, rgba(99, 102, 241, 0.28) 0px, transparent 55%), radial-gradient(at 88% 12%, rgba(6, 182, 212, 0.24) 0px, transparent 55%)',
				'aurora' => 'linear-gradient(160deg, #0f172a 0%, #1e1b4b 45%, #312e81 100%)',
				'dark'   => 'radial-gradient(circle at 50% 0%, #1f2937 0%, #0b0f19 70%)',
				'glow'   => 'radial-gradient(ellipse at 50% -10%, rgba(37, 99, 235, 0.35) 0px, transparent 60%)',
				'grid'   => 'linear-gradient(rgba(148, 163, 184, 0.14) 1px, transparent 1px), linear-gradient(90deg, rgba(148, 163, 184, 0.14) 1px, transparent 1px)',
			);

			return isset( $styles[ $value ] ) ? $styles[ $value ] : '';
		}

		/* Glass levels. */
		if ( self::TOKEN_PREFIX . 'glass-blur' === $token ) {

			$levels = array(
				'off'    => '0px',
				'subtle' => '8px',
				'medium' => '16px',
				'strong' => '28px',
			);

			return isset( $levels[ $value ] ) ? $levels[ $value ] : '';
		}

		/* Entrance animations. */
		if ( self::TOKEN_PREFIX . 'reveal' === $token ) {

			$kinds = array(
				'off'   => 'none',
				'fade'  => 'fade',
				'up'    => 'fade-up',
				'down'  => 'fade-down',
				'left'  => 'fade-left',
				'right' => 'fade-right',
				'scale' => 'scale',
				'blur'  => 'blur',
			);

			return isset( $kinds[ $value ] ) ? $kinds[ $value ] : '';
		}

		/* Hover effects. */
		if ( self::TOKEN_PREFIX . 'hover' === $token ) {

			$effects = array(
				'none'  => 'none',
				'lift'  => 'lift',
				'scale' => 'scale',
				'glow'  => 'glow',
				'zoom'  => 'zoom',
			);

			return isset( $effects[ $value ] ) ? $effects[ $value ] : '';
		}

		/* Card image ratios are stored as a fraction keyword and used verbatim. */
		if ( self::TOKEN_PREFIX . 'card-image-ratio' === $token ) {

			return preg_match( '#^\d+/\d+$#', $value ) ? $value : '';
		}

		/* Alignment keywords are logical values, so they are RTL-safe by construction. */
		if ( self::TOKEN_PREFIX . 'align' === $token ) {

			return in_array( $value, array( 'start', 'center', 'end' ), true ) ? $value : '';
		}

		/* Heading text-transform keywords. */
		if ( self::TOKEN_PREFIX . 'heading-transform' === $token ) {

			return in_array( $value, array( 'none', 'uppercase', 'capitalize' ), true ) ? $value : '';
		}

		/* Heading font weight. */
		if ( self::TOKEN_PREFIX . 'heading-weight' === $token ) {

			return in_array( $value, array( '400', '500', '600', '700', '800' ), true ) ? $value : '';
		}

		/* Everything else is already a validated colour / number / length. */
		return $value;
	}

	/**
	 * Print the section style block on the front end (§25).
	 *
	 * Emitted only when at least one section carries overrides, so a site that never customizes a
	 * section ships zero extra bytes (§34).
	 */
	public function print_styles(): void {

		if ( is_admin() ) {
			return;
		}

		$blocks = array();

		foreach ( $this->sections() as $type => $section ) {

			$css = $this->css_for( (string) $type );

			if ( '' !== $css ) {
				$blocks[] = $css;
			}
		}

		if ( empty( $blocks ) ) {
			return;
		}

		/*
		 * `wp_print_inline_style_tag()` is not used: the content is already fully validated and
		 * contains no user-authored text, and it must be printed verbatim. It is output at
		 * priority 99 on `wp_head`, i.e. after the Theme's token stylesheet.
		 */
		echo "\n<style id=\"bb-section-styles\">\n" . implode( "\n", $blocks ) . "\n</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- validated token/value pairs only.
	}
}