<?php

namespace BusinessBuilderCore\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Design Schema Extension (Phase 21 §5, §27-§33).
 *
 * The Theme owns the customization schema (`bb_theme_design_schema()`), and Phase 21 must NOT
 * build a second one. This class EXTENDS the existing schema through its own documented filter
 * (`bb_theme_design_schema`), adding the design-identity controls a Design needs to express a
 * complete visual language rather than a colour swap:
 *
 *   - gradients      (brand / hero / cta / decorative)
 *   - density        (section rhythm, grid gap, card padding, heading scale)
 *   - shape          (border thickness, card/input radius, card shadow depth)
 *   - components     (button padding/weight/shadow, badge colours)
 *   - shell BEHAVIOUR (header height, blur, transparency, border, nav gap, footer rhythm)
 *   - motion         (tempo, easing, reveal distance, hover lift)
 *
 * ARCHITECTURAL RULES OBSERVED
 * ---------------------------
 *   - ONE token namespace: every control's `token` starts with `--bb-`. The Theme's own
 *     `bb_theme_design_schema()` filter already REJECTS any entry whose token does not start
 *     with `--bb-`, so this is enforced by the existing architecture, not by this class.
 *   - ONE storage mechanism: the Theme derives the theme-mod name from the control key
 *     (`bb_theme_design_mod_name()`), so these controls persist in exactly the same
 *     `bb_design_<key>` mods and inherit the same reset semantics.
 *   - ONE sanitizer: each control declares an existing `type` (`color` | `length` | `select` |
 *     `number`) and is therefore sanitized by the Theme's own `bb_theme_sanitize_design_value()`.
 *     No new sanitizer, no free-form CSS.
 *   - Additive and optional: an entry is only added if the Theme does not already declare that
 *     key, so a future Theme version that ships one of these wins and nothing is duplicated.
 *
 * Because the Theme's sanitizer whitelists gradient values via `type => 'select'` and lengths
 * via `type => 'length'`, the values a customer can pick are always safe, bounded and
 * non-breakable — the same guarantee the pre-existing 28 controls already have.
 *
 * MEASURED CONSTRAINT (tests/_phase21-sanitizer-probe.php)
 * -------------------------------------------------------
 * The Theme's `bb_theme_sanitize_design_value()` — which Phase 21 must NOT modify — was probed
 * directly and accepts ONLY: `px`, `rem`, `em`, `%` and bare integers for `length`, plus
 * negatives for `number`. It REJECTS `ms`, `s` and negative `length` values.
 *
 * The new controls are therefore expressed INSIDE that existing envelope rather than by
 * loosening the Theme's validator:
 *   - MOTION is stored as a `number` of SECONDS (0.24 = 240ms), which is a valid CSS time
 *     value, so `resolve_units()` only has to append `s`.
 *   - LETTER SPACING is stored as a `number` in `em`, appended by `resolve_units()`.
 * This is the "smallest safe solution" §43 asks for: no Theme change, no weakened validation.
 */
class DesignSchema {

	/**
	 * Register the extension.
	 */
	public function register(): void {

		add_filter( 'bb_theme_design_schema', array( $this, 'extend_schema' ), 10 );

		/*
		 * Unit resolution runs INSIDE the existing preset-config cascade, and at a LATE priority so
		 * a user override (which the Theme merges in at priority 10) has already been applied — the
		 * unit is then appended to whichever value actually won.
		 */
		add_filter( 'bb_theme_preset_config', array( $this, 'resolve_gradients' ), 20, 2 );
		add_filter( 'bb_theme_preset_config', array( $this, 'resolve_units' ), 30, 2 );

		/*
		 * The motion MASTER PRESET runs after the customer's own overrides have been
		 * merged in (the Theme does that at priority 10) and BEFORE `resolve_units()`,
		 * so the durations it publishes still receive their `s` suffix.
		 */
		add_filter( 'bb_theme_preset_config', array( $this, 'resolve_motion_mode' ), 25, 2 );

		/**
		 * Gradient presets offered for the gradient controls.
		 *
		 * A gradient is a `select` control whose OPTION VALUES are safe CSS gradients. Because
		 * the Theme's `select` sanitizer only accepts a value present in the control's own
		 * option list, a gradient can never be an arbitrary string.
		 */
		add_filter( 'bb_theme_gradient_choices', array( $this, 'gradient_choices' ) );

		/*
		 * Phase 22 (§5, §37): declare the Theme's built-in designs as GENERIC.
		 *
		 * `is_compatible()` now treats "declares no business types" as "belongs to no
		 * business type", so an un-annotated design can never cross a business-type
		 * boundary. That is the isolation guarantee the phase requires - but it would
		 * also hide the Theme's own three built-ins on a LawFirm or Medical site,
		 * which is not the intent: they are the neutral, business-agnostic designs
		 * every site is allowed to fall back to.
		 *
		 * Rather than special-casing them inside the catalogue (which would be a
		 * hardcoded list in generic infrastructure), they are annotated HERE, in the
		 * plugin's design layer, through the Theme's own documented `bb_theme_presets`
		 * filter. The Theme stays authoritative over which designs exist; the plugin
		 * only supplies the compatibility metadata the catalogue needs.
		 */
		add_filter( 'bb_theme_presets', array( $this, 'annotate_builtin_designs' ), 5 );
	}

	/**
	 * Mark the Theme's built-in designs as business-agnostic.
	 *
	 * The Theme's own presets (`default`, `modern`, `luxury`) are neutral by design: they are not
	 * authored for any business type, so they must remain available everywhere. This adds an
	 * explicit `business_types => array()` marker plus a `generic` flag, which is what the
	 * catalogue reads - no hardcoded slug list is introduced anywhere in the generic layer.
	 *
	 * Only designs the THEME declares are touched, and only to ADD metadata: no token, label or
	 * value is modified, so every existing site renders exactly as before.
	 *
	 * Priority 5 so a pack's own designs (registered at the default priority) are never affected.
	 *
	 * @param mixed $presets Existing presets.
	 * @return array
	 */
	public function annotate_builtin_designs( $presets ): array {

		$presets = is_array( $presets ) ? $presets : array();

		foreach ( $presets as $slug => $preset ) {

			if ( ! is_array( $preset ) ) {
				continue;
			}

			/* A pack already declared its scope: never override another author's metadata. */
			if ( isset( $preset['business_types'] ) ) {
				continue;
			}

			/*
			 * A design that carries no `business_types` and is not one of the Theme's own built-ins
			 * is left alone, so it stays isolated by the new default rule. Only the three presets the
			 * Theme itself ships are marked generic, and they are recognised by their own slug -
			 * which is legitimate here because this file IS the Theme's design extension, not the
			 * generic catalogue.
			 */
			if ( ! in_array( (string) $slug, array( 'default', 'modern', 'luxury' ), true ) ) {
				continue;
			}

			$presets[ $slug ]['business_types'] = array();
			$presets[ $slug ]['generic']        = true;
		}

		return $presets;
	}

	/**
	 * The design-identity controls.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function controls(): array {

		return array(

			/* ---------------- Gradients ---------------- */
			array(
				'key'     => 'gradient_brand',
				'token'   => '--bb-gradient-brand',
				'type'    => 'select',
				'label'   => __( 'Brand gradient', 'business-builder' ),
				'group'   => 'gradients',
				'default' => 'primary',
				'options' => $this->gradient_options(),
			),
			array(
				'key'     => 'gradient_hero',
				'token'   => '--bb-gradient-hero',
				'type'    => 'select',
				'label'   => __( 'Hero gradient', 'business-builder' ),
				'group'   => 'gradients',
				'default' => 'primary',
				'options' => $this->gradient_options(),
			),
			array(
				'key'     => 'gradient_cta',
				'token'   => '--bb-gradient-cta',
				'type'    => 'select',
				'label'   => __( 'Call-to-action gradient', 'business-builder' ),
				'group'   => 'gradients',
				'default' => 'primary',
				'options' => $this->gradient_options(),
			),
			array(
				'key'     => 'gradient_decorative',
				'token'   => '--bb-gradient-decorative',
				'type'    => 'select',
				'label'   => __( 'Decorative gradient', 'business-builder' ),
				'group'   => 'gradients',
				'default' => 'primary',
				'options' => $this->gradient_options(),
			),

			/* ---------------- Density ---------------- */
			array(
				'key'     => 'section_padding',
				'token'   => '--bb-section-padding-block',
				'type'    => 'length',
				'label'   => __( 'Section rhythm', 'business-builder' ),
				'group'   => 'density',
				'default' => '80px',
				'min'     => 32,
				'max'     => 160,
				'unit'    => 'px',
			),
			array(
				'key'     => 'grid_gap',
				'token'   => '--bb-grid-gap',
				'type'    => 'length',
				'label'   => __( 'Grid gap', 'business-builder' ),
				'group'   => 'density',
				'default' => '24px',
				'min'     => 8,
				'max'     => 64,
				'unit'    => 'px',
			),
			array(
				'key'     => 'card_padding',
				'token'   => '--bb-card-padding',
				'type'    => 'length',
				'label'   => __( 'Card padding', 'business-builder' ),
				'group'   => 'density',
				'default' => '24px',
				'min'     => 12,
				'max'     => 56,
				'unit'    => 'px',
			),
			array(
				'key'     => 'heading_scale',
				'token'   => '--bb-heading-scale',
				'type'    => 'number',
				'label'   => __( 'Heading scale', 'business-builder' ),
				'group'   => 'density',
				'default' => '1',
				'min'     => 0.8,
				'max'     => 1.4,
				'step'    => 0.05,
			),
			array(
					'key'     => 'heading_letter_spacing',
					'token'   => '--bb-heading-letter-spacing',
					'type'    => 'number',
					'label'   => __( 'Heading letter spacing', 'business-builder' ),
					'group'   => 'density',
					'default' => '-0.02',
					'min'     => -0.08,
					'max'     => 0.08,
					'step'    => 0.01,
					'unit'    => 'em',
				),

			/* ---------------- Shape ---------------- */
			array(
				'key'     => 'border_thickness',
				'token'   => '--bb-border-thickness',
				'type'    => 'length',
				'label'   => __( 'Border thickness', 'business-builder' ),
				'group'   => 'shape',
				'default' => '1px',
				'min'     => 0,
				'max'     => 4,
				'unit'    => 'px',
			),
			array(
				'key'     => 'card_radius',
				'token'   => '--bb-radius-card',
				'type'    => 'length',
				'label'   => __( 'Card radius', 'business-builder' ),
				'group'   => 'shape',
				'default' => '12px',
				'min'     => 0,
				'max'     => 40,
				'unit'    => 'px',
			),
			array(
				'key'     => 'input_radius',
				'token'   => '--bb-radius-input',
				'type'    => 'length',
				'label'   => __( 'Input radius', 'business-builder' ),
				'group'   => 'shape',
				'default' => '8px',
				'min'     => 0,
				'max'     => 32,
				'unit'    => 'px',
			),

			/* ---------------- Components ---------------- */
			array(
				'key'     => 'button_padding_block',
				'token'   => '--bb-button-padding-block',
				'type'    => 'length',
				'label'   => __( 'Button vertical padding', 'business-builder' ),
				'group'   => 'components',
				'default' => '12px',
				'min'     => 6,
				'max'     => 28,
				'unit'    => 'px',
			),
			array(
				'key'     => 'button_padding_inline',
				'token'   => '--bb-button-padding-inline',
				'type'    => 'length',
				'label'   => __( 'Button horizontal padding', 'business-builder' ),
				'group'   => 'components',
				'default' => '24px',
				'min'     => 12,
				'max'     => 56,
				'unit'    => 'px',
			),
			array(
				'key'     => 'button_weight',
				'token'   => '--bb-button-weight',
				'type'    => 'select',
				'label'   => __( 'Button weight', 'business-builder' ),
				'group'   => 'components',
				'default' => '500',
				'options' => array(
					'400' => __( 'Regular', 'business-builder' ),
					'500' => __( 'Medium', 'business-builder' ),
					'600' => __( 'Semibold', 'business-builder' ),
					'700' => __( 'Bold', 'business-builder' ),
				),
			),
			array(
				'key'     => 'badge_bg',
				'token'   => '--bb-badge-bg',
				'type'    => 'color',
				'label'   => __( 'Badge background', 'business-builder' ),
				'group'   => 'components',
				'default' => '#dbeafe',
			),
			array(
				'key'     => 'badge_color',
				'token'   => '--bb-badge-color',
				'type'    => 'color',
				'label'   => __( 'Badge text', 'business-builder' ),
				'group'   => 'components',
				'default' => '#1e40af',
			),

			/* ---------------- Shell behaviour ---------------- */
			array(
				'key'     => 'header_height',
				'token'   => '--bb-header-height',
				'type'    => 'length',
				'label'   => __( 'Header height', 'business-builder' ),
				'group'   => 'shell',
				'default' => '76px',
				'min'     => 52,
				'max'     => 140,
				'unit'    => 'px',
			),
			array(
				'key'     => 'header_blur',
				'token'   => '--bb-header-blur',
				'type'    => 'length',
				'label'   => __( 'Header backdrop blur', 'business-builder' ),
				'group'   => 'shell',
				'default' => '0px',
				'min'     => 0,
				'max'     => 30,
				'unit'    => 'px',
			),
			array(
				'key'     => 'header_border_width',
				'token'   => '--bb-header-border-width',
				'type'    => 'length',
				'label'   => __( 'Header border width', 'business-builder' ),
				'group'   => 'shell',
				'default' => '1px',
				'min'     => 0,
				'max'     => 4,
				'unit'    => 'px',
			),
			array(
				'key'     => 'nav_gap',
				'token'   => '--bb-nav-gap',
				'type'    => 'length',
				'label'   => __( 'Navigation spacing', 'business-builder' ),
				'group'   => 'shell',
				'default' => '24px',
				'min'     => 8,
				'max'     => 56,
				'unit'    => 'px',
			),
			array(
				'key'     => 'footer_padding',
				'token'   => '--bb-footer-padding-block',
				'type'    => 'length',
				'label'   => __( 'Footer spacing', 'business-builder' ),
				'group'   => 'shell',
				'default' => '48px',
				'min'     => 20,
				'max'     => 120,
				'unit'    => 'px',
			),

			/* ---------------- Motion ----------------
				 * Expressed in SECONDS (e.g. 0.24 = 240ms) because the Theme's existing sanitizer
				 * rejects `ms`/`s` units. The design-identity CSS consumes these directly as valid
				 * CSS time values, so no unit translation is needed at render time. */
				array(
					'key'     => 'motion_speed',
					'token'   => '--bb-motion-normal',
					'type'    => 'number',
					'label'   => __( 'Motion tempo', 'business-builder' ),
					'group'   => 'motion',
					'default' => '0.24',
					'min'     => 0.08,
					'max'     => 0.6,
					'step'    => 0.02,
					'unit'    => 's',
				),
				array(
					'key'     => 'reveal_duration',
					'token'   => '--bb-reveal-duration',
					'type'    => 'number',
					'label'   => __( 'Reveal duration', 'business-builder' ),
					'group'   => 'motion',
					'default' => '0.42',
					'min'     => 0.12,
					'max'     => 0.9,
					'step'    => 0.02,
					'unit'    => 's',
				),
				array(
					'key'     => 'reveal_distance',
					'token'   => '--bb-reveal-distance',
					'type'    => 'length',
					'label'   => __( 'Reveal distance', 'business-builder' ),
					'group'   => 'motion',
					'default' => '20px',
					'min'     => 0,
					'max'     => 60,
					'unit'    => 'px',
				),
				array(
					'key'     => 'hover_lift',
					'token'   => '--bb-hover-lift',
					'type'    => 'length',
					'label'   => __( 'Hover lift', 'business-builder' ),
					'group'   => 'motion',
					'default' => '4px',
					'min'     => 0,
					'max'     => 12,
					'unit'    => 'px',
				),
				array(
					'key'     => 'reveal_kind',
					'token'   => '--bb-reveal-kind',
					'type'    => 'select',
					'label'   => __( 'Reveal style', 'business-builder' ),
					'group'   => 'motion',
					'default' => 'fade-up',
					'options' => array(
						'none'      => __( 'Off', 'business-builder' ),
						'fade'      => __( 'Fade', 'business-builder' ),
						'fade-up'   => __( 'Slide up', 'business-builder' ),
						'fade-down' => __( 'Slide down', 'business-builder' ),
						'fade-left' => __( 'Slide from end', 'business-builder' ),
						'fade-right' => __( 'Slide from start', 'business-builder' ),
						'scale'     => __( 'Scale', 'business-builder' ),
						'blur'      => __( 'Blur reveal', 'business-builder' ),
					),
				),
				array(
					'key'     => 'reveal_stagger',
					'token'   => '--bb-reveal-stagger',
					/*
					 * A NUMBER of SECONDS, not a `length`.
					 *
					 * MEASURED: the Theme's `bb_theme_sanitize_design_value()` rejects
					 * `ms`/`s` for a `length` control, exactly as Phase 21 documented for
					 * motion timing. `animation-delay` needs a TIME, so the value is stored
					 * as a plain number and the unit is appended by `resolve_units()` -
					 * the same mechanism the other motion controls already use.
					 */
					'type'    => 'number',
					'label'   => __( 'Reveal stagger', 'business-builder' ),
					'group'   => 'motion',
					'default' => '0.06',
					'min'     => 0,
					'max'     => 0.2,
					'step'    => 0.01,
					'unit'    => 's',
				),
				array(
					'key'     => 'hover_effect',
					'token'   => '--bb-hover-effect',
					'type'    => 'select',
					'label'   => __( 'Hover effect', 'business-builder' ),
					'group'   => 'motion',
					'default' => 'lift',
					'options' => array(
						'none'  => __( 'None', 'business-builder' ),
						'lift'  => __( 'Lift', 'business-builder' ),
						'scale' => __( 'Scale', 'business-builder' ),
						'glow'  => __( 'Glow', 'business-builder' ),
						'zoom'  => __( 'Image zoom', 'business-builder' ),
					),
				),

				/* ---------------- Background (§10) ----------------
				 * The site background is LAYERED: a colour, an optional gradient and an
				 * optional image, then an overlay tint. Each layer is a separate token
				 * so a customer can change only the overlay without destroying the
				 * design's gradient. Every value is an existing sanitizer type, so no
				 * arbitrary CSS can be stored. */
				array(
					'key'     => 'bg_gradient',
					'token'   => '--bb-bg-gradient',
					'type'    => 'select',
					'label'   => __( 'Background style', 'business-builder' ),
					'group'   => 'background',
					'default' => 'none',
					'options' => $this->background_options(),
				),
				array(
					'key'     => 'bg_overlay',
					'token'   => '--bb-bg-overlay',
					'type'    => 'select',
					'label'   => __( 'Background overlay', 'business-builder' ),
					'group'   => 'background',
					'default' => 'none',
					'options' => $this->overlay_options(),
				),
				array(
					'key'     => 'bg_overlay_opacity',
					'token'   => '--bb-bg-overlay-opacity',
					'type'    => 'number',
					'label'   => __( 'Overlay strength', 'business-builder' ),
					'group'   => 'background',
					'default' => '0',
					'min'     => 0,
					'max'     => 1,
					'step'    => 0.05,
				),

				/* ---------------- Layout & grid (§11, §12) ----------------
				 * Container, per-breakpoint column counts and card width bounds. The
				 * Studio renders these as VISUAL column pickers, never as CSS terms. */
				array(
					'key'     => 'container_padding_block',
					'token'   => '--bb-container-padding-block',
					'type'    => 'length',
					'label'   => __( 'Container side gutter', 'business-builder' ),
					'group'   => 'layout',
					'default' => '0px',
					'min'     => 0,
					'max'     => 96,
					'unit'    => 'px',
				),
				array(
					'key'     => 'grid_columns',
					'token'   => '--bb-grid-columns',
					'type'    => 'select',
					'label'   => __( 'Columns (desktop)', 'business-builder' ),
					'group'   => 'layout',
					'default' => '3',
					'options' => $this->column_options(),
				),
				array(
					'key'     => 'grid_columns_tablet',
					'token'   => '--bb-grid-columns-tablet',
					'type'    => 'select',
					'label'   => __( 'Columns (tablet)', 'business-builder' ),
					'group'   => 'layout',
					'default' => '2',
					'options' => $this->column_options(),
				),
				array(
					'key'     => 'grid_columns_mobile',
					'token'   => '--bb-grid-columns-mobile',
					'type'    => 'select',
					'label'   => __( 'Columns (mobile)', 'business-builder' ),
					'group'   => 'layout',
					'default' => '1',
					'options' => $this->column_options(),
				),
				array(
					'key'     => 'grid_row_gap',
					'token'   => '--bb-grid-row-gap',
					'type'    => 'length',
					'label'   => __( 'Row gap', 'business-builder' ),
					'group'   => 'layout',
					'default' => '24px',
					'min'     => 0,
					'max'     => 96,
					'unit'    => 'px',
				),
				array(
					'key'     => 'card_min_width',
					'token'   => '--bb-card-min-width',
					'type'    => 'length',
					'label'   => __( 'Card minimum width', 'business-builder' ),
					'group'   => 'layout',
					'default' => '260px',
					'min'     => 160,
					'max'     => 480,
					'unit'    => 'px',
				),
				array(
					'key'     => 'content_width',
					'token'   => '--bb-content-width',
					'type'    => 'length',
					'label'   => __( 'Text measure', 'business-builder' ),
					'group'   => 'layout',
					/*
					 * Expressed in `rem` rather than `ch`.
					 *
					 * MEASURED: the Theme's `bb_theme_sanitize_design_value()` accepts ONLY
					 * px / rem / em / % for a `length` (and rejects out-of-range values
					 * outright rather than clamping). `68ch` is therefore a value the
					 * Theme's OWN validator refuses, which would have made this control
					 * impossible to save. The range is chosen so the default is unchanged:
					 * 42rem ≈ 68ch at a 16px base.
					 */
					'default' => '42rem',
					'min'     => 25,
					'max'     => 70,
					'unit'    => 'rem',
				),
				array(
					'key'     => 'section_align',
					'token'   => '--bb-section-align',
					'type'    => 'select',
					'label'   => __( 'Section alignment', 'business-builder' ),
					'group'   => 'layout',
					'default' => 'start',
					'options' => array(
						'start'  => __( 'Start', 'business-builder' ),
						'center' => __( 'Center', 'business-builder' ),
						'end'    => __( 'End', 'business-builder' ),
					),
				),

				/* ---------------- Glass (§19) ----------------
				 * One reusable treatment, applied where a design asks for it. Level 0
				 * ("Off") is the default, so glass is never forced. The level sets the
				 * blur; the advanced controls refine it. */
				array(
					'key'     => 'glass_level',
					'token'   => '--bb-glass-blur',
					'type'    => 'length',
					'label'   => __( 'Glass strength', 'business-builder' ),
					'group'   => 'glass',
					'default' => '0px',
					'min'     => 0,
					'max'     => 30,
					'unit'    => 'px',
				),
				array(
					'key'     => 'glass_saturate',
					'token'   => '--bb-glass-saturate',
					'type'    => 'length',
					'label'   => __( 'Glass saturation', 'business-builder' ),
					'group'   => 'glass',
					'default' => '100%',
					'min'     => 100,
					'max'     => 220,
					'unit'    => '%',
				),
				array(
					'key'     => 'glass_opacity',
					'token'   => '--bb-glass-opacity',
					'type'    => 'number',
					'label'   => __( 'Glass transparency', 'business-builder' ),
					'group'   => 'glass',
					'default' => '1',
					'min'     => 0.2,
					'max'     => 1,
					'step'    => 0.05,
				),
				array(
					'key'     => 'glass_border_opacity',
					'token'   => '--bb-glass-border-opacity',
					'type'    => 'number',
					'label'   => __( 'Glass border opacity', 'business-builder' ),
					'group'   => 'glass',
					'default' => '1',
					'min'     => 0,
					'max'     => 1,
					'step'    => 0.05,
				),

				/* ---------------- Header scrolled state (§21) ----------------
				 * The initial and scrolled header are configured SEPARATELY so a
				 * transparent-over-hero header that becomes solid can be expressed.
				 * Defaults equal the header colour tokens, so nothing changes unless
				 * the customer opts in. */
				array(
					'key'     => 'header_initial_bg',
					'token'   => '--bb-header-initial-bg',
					'type'    => 'color',
					'label'   => __( 'Header background (top of page)', 'business-builder' ),
					'group'   => 'header',
					'default' => '#ffffff',
				),
				array(
					'key'     => 'header_initial_nav',
					'token'   => '--bb-header-initial-nav',
					'type'    => 'color',
					'label'   => __( 'Navigation colour (top of page)', 'business-builder' ),
					'group'   => 'header',
					'default' => '#111827',
				),
				array(
					'key'     => 'header_initial_transparency',
					'token'   => '--bb-header-initial-transparency',
					'type'    => 'number',
					'label'   => __( 'Header transparency (top of page)', 'business-builder' ),
					'group'   => 'header',
					'default' => '1',
					'min'     => 0,
					'max'     => 1,
					'step'    => 0.05,
				),
				array(
					'key'     => 'header_scrolled_bg',
					'token'   => '--bb-header-scrolled-bg',
					'type'    => 'color',
					'label'   => __( 'Header background (after scrolling)', 'business-builder' ),
					'group'   => 'header',
					'default' => '#ffffff',
				),
				array(
					'key'     => 'header_scrolled_nav',
					'token'   => '--bb-header-scrolled-nav',
					'type'    => 'color',
					'label'   => __( 'Navigation colour (after scrolling)', 'business-builder' ),
					'group'   => 'header',
					'default' => '#111827',
				),
				array(
					'key'     => 'header_scrolled_border',
					'token'   => '--bb-header-scrolled-border',
					'type'    => 'color',
					'label'   => __( 'Header border (after scrolling)', 'business-builder' ),
					'group'   => 'header',
					'default' => '#e5e7eb',
				),
				array(
					'key'     => 'header_scroll_transition',
					'token'   => '--bb-header-scroll-transition',
					'type'    => 'number',
					'label'   => __( 'Scroll transition speed', 'business-builder' ),
					'group'   => 'header',
					'default' => '0.24',
					'min'     => 0,
					'max'     => 0.8,
					'step'    => 0.02,
					'unit'    => 's',
				),

				/* ---------------- Footer (§22) ---------------- */
				array(
					'key'     => 'footer_heading_color',
					'token'   => '--bb-footer-heading-color',
					'type'    => 'color',
					'label'   => __( 'Footer heading colour', 'business-builder' ),
					'group'   => 'footer',
					'default' => '#e5e7eb',
				),
				array(
					'key'     => 'footer_link_color',
					'token'   => '--bb-footer-link-color',
					'type'    => 'color',
					'label'   => __( 'Footer link colour', 'business-builder' ),
					'group'   => 'footer',
					'default' => '#e5e7eb',
				),
				array(
					'key'     => 'footer_link_hover',
					'token'   => '--bb-footer-link-hover',
					'type'    => 'color',
					'label'   => __( 'Footer link hover colour', 'business-builder' ),
					'group'   => 'footer',
					'default' => '#3b82f6',
				),
				array(
					'key'     => 'footer_column_gap',
					'token'   => '--bb-footer-column-gap',
					'type'    => 'length',
					'label'   => __( 'Footer column spacing', 'business-builder' ),
					'group'   => 'footer',
					'default' => '32px',
					'min'     => 8,
					'max'     => 96,
					'unit'    => 'px',
				),

				/* ================================================================
				 * PHASE 23 — GLOBAL LAYOUT (§9)
				 * ----------------------------------------------------------------
				 * Added ONLY where the audit measured a gap (docs/phase23-audit.md §5.1
				 * G9). Container width, section rhythm, grid columns and text measure
				 * already exist and are NOT re-declared — a second control for an
				 * existing property is exactly what the specification forbids.
				 * ================================================================ */
				array(
					'key'     => 'layout_mode',
					'token'   => '--bb-layout-mode',
					'type'    => 'select',
					'label'   => __( 'Page layout', 'business-builder' ),
					'group'   => 'layout',
					/*
					 * A select whose value is a KEYWORD resolves to a class on the body
					 * (DesignShellState), which is how a keyword can drive layout without
					 * any free-form CSS being stored. Same mechanism Phase 22 already uses
					 * for the background overlay and the glass flag.
					 */
					'default' => 'full',
					'options' => array(
						'full'  => __( 'Full width', 'business-builder' ),
						'boxed' => __( 'Boxed', 'business-builder' ),
					),
				),
				array(
					'key'     => 'site_margin',
					'token'   => '--bb-site-margin',
					'type'    => 'length',
					'label'   => __( 'Space around the content', 'business-builder' ),
					'group'   => 'layout',
					'default' => '0px',
					'min'     => 0,
					'max'     => 80,
					'unit'    => 'px',
				),
				array(
					'key'     => 'section_gap',
					'token'   => '--bb-section-gap',
					'type'    => 'length',
					'label'   => __( 'Space between sections', 'business-builder' ),
					'group'   => 'layout',
					'default' => '0px',
					'min'     => 0,
					'max'     => 80,
					'unit'    => 'px',
				),

				/* ================================================================
				 * PHASE 23 — GLOBAL TYPOGRAPHY (§10)
				 * ----------------------------------------------------------------
				 * The audit (G10) found the family, base size, one bold weight and the
				 * body line-height already controllable. Body line-height is NOT
				 * re-declared here: the Theme's `line_height_normal` control IS the body
				 * line-height, and it is consumed by `.bb-template` — a second control
				 * would be a duplicate source of truth for one property.
				 * ================================================================ */
				array(
					'key'     => 'body_weight',
					'token'   => '--bb-font-weight-body',
					'type'    => 'select',
					'label'   => __( 'Body weight', 'business-builder' ),
					'group'   => 'typography',
					'default' => '400',
					'options' => array(
						'300' => __( 'Light', 'business-builder' ),
						'400' => __( 'Regular', 'business-builder' ),
						'500' => __( 'Medium', 'business-builder' ),
						'600' => __( 'Semibold', 'business-builder' ),
					),
				),
				array(
					'key'     => 'heading_weight',
					'token'   => '--bb-font-weight-heading',
					'type'    => 'select',
					'label'   => __( 'Heading weight', 'business-builder' ),
					'group'   => 'typography',
					'default' => '700',
					'options' => array(
						'500' => __( 'Medium', 'business-builder' ),
						'600' => __( 'Semibold', 'business-builder' ),
						'700' => __( 'Bold', 'business-builder' ),
						'800' => __( 'Extra bold', 'business-builder' ),
						'900' => __( 'Black', 'business-builder' ),
					),
				),
				array(
					'key'     => 'body_letter_spacing',
					'token'   => '--bb-body-letter-spacing',
					'type'    => 'number',
					'label'   => __( 'Body letter spacing', 'business-builder' ),
					'group'   => 'typography',
					'default' => '0',
					'min'     => -0.03,
					'max'     => 0.1,
					'step'    => 0.005,
					/*
					 * Stored as a NUMBER in `em` and unit-appended by resolve_units(),
					 * because the Theme's length validator accepts only px/rem/em/%.
					 */
					'unit'    => 'em',
				),
				array(
					'key'     => 'heading_line_height',
					'token'   => '--bb-heading-line-height',
					'type'    => 'number',
					'label'   => __( 'Heading line height', 'business-builder' ),
					'group'   => 'typography',
					'default' => '1.2',
					'min'     => 1,
					'max'     => 1.8,
					'step'    => 0.05,
				),
				array(
					'key'     => 'heading_transform',
					'token'   => '--bb-heading-transform',
					'type'    => 'select',
					'label'   => __( 'Heading case', 'business-builder' ),
					'group'   => 'typography',
					'default' => 'none',
					'options' => array(
						'none'       => __( 'As typed', 'business-builder' ),
						'uppercase'  => __( 'UPPERCASE', 'business-builder' ),
						'capitalize' => __( 'Capitalize Each Word', 'business-builder' ),
						'lowercase'  => __( 'lowercase', 'business-builder' ),
					),
				),

				/* ================================================================
				 * PHASE 23 — NAVBAR (§12)
				 * ----------------------------------------------------------------
				 * The audit (G11) found height, blur, border, gap and the initial /
				 * scrolled colours already controllable, so ONLY the missing decisions
				 * are added here. The token names are new (measured free of collisions)
				 * and every one is consumed by `design-sections.css` on
				 * `.bb-theme .bb-site-header`, which is the markup the Theme already
				 * renders — no Theme file is edited.
				 * ================================================================ */
				array(
					'key'     => 'nav_position',
					'token'   => '--bb-nav-position',
					'type'    => 'select',
					'label'   => __( 'Menu position', 'business-builder' ),
					'group'   => 'navbar',
					'default' => 'center',
					'options' => array(
						'start'  => __( 'Beside the logo', 'business-builder' ),
						'center' => __( 'Centered', 'business-builder' ),
						'end'    => __( 'Beside the buttons', 'business-builder' ),
					),
				),
				array(
					'key'     => 'nav_link_size',
					'token'   => '--bb-nav-link-size',
					'type'    => 'length',
					'label'   => __( 'Menu text size', 'business-builder' ),
					'group'   => 'navbar',
					'default' => '1rem',
					'min'     => 0.75,
					'max'     => 1.5,
					'unit'    => 'rem',
				),
				array(
					'key'     => 'nav_link_weight',
					'token'   => '--bb-nav-link-weight',
					'type'    => 'select',
					'label'   => __( 'Menu text weight', 'business-builder' ),
					'group'   => 'navbar',
					'default' => '500',
					'options' => array(
						'400' => __( 'Regular', 'business-builder' ),
						'500' => __( 'Medium', 'business-builder' ),
						'600' => __( 'Semibold', 'business-builder' ),
						'700' => __( 'Bold', 'business-builder' ),
					),
				),
				array(
					'key'     => 'nav_link_tracking',
					'token'   => '--bb-nav-link-tracking',
					'type'    => 'number',
					'label'   => __( 'Menu letter spacing', 'business-builder' ),
					'group'   => 'navbar',
					'default' => '0',
					'min'     => -0.02,
					'max'     => 0.15,
					'step'    => 0.005,
					'unit'    => 'em',
				),
				array(
					'key'     => 'nav_link_hover',
					'token'   => '--bb-nav-link-hover',
					'type'    => 'color',
					'label'   => __( 'Menu link hover colour', 'business-builder' ),
					'group'   => 'navbar',
					'default' => '#2563eb',
				),
				array(
					'key'     => 'nav_link_active',
					'token'   => '--bb-nav-link-active',
					'type'    => 'color',
					'label'   => __( 'Current page link colour', 'business-builder' ),
					'group'   => 'navbar',
					'default' => '#2563eb',
				),
				array(
					'key'     => 'nav_indicator',
					'token'   => '--bb-nav-indicator',
					'type'    => 'select',
					'label'   => __( 'Active link indicator', 'business-builder' ),
					'group'   => 'navbar',
					'default' => 'none',
					'options' => array(
						'none'      => __( 'None', 'business-builder' ),
						'underline' => __( 'Underline', 'business-builder' ),
						'pill'      => __( 'Pill background', 'business-builder' ),
						'dot'       => __( 'Dot above', 'business-builder' ),
					),
				),
				array(
					'key'     => 'nav_radius',
					'token'   => '--bb-nav-radius',
					'type'    => 'length',
					'label'   => __( 'Menu link roundness', 'business-builder' ),
					'group'   => 'navbar',
					'default' => '8px',
					'min'     => 0,
					'max'     => 32,
					'unit'    => 'px',
				),
				array(
					'key'     => 'logo_height',
					'token'   => '--bb-logo-height',
					'type'    => 'length',
					'label'   => __( 'Logo height', 'business-builder' ),
					'group'   => 'navbar',
					'default' => '48px',
					'min'     => 20,
					'max'     => 120,
					'unit'    => 'px',
				),

				/* ================================================================
				 * PHASE 23 — GLOBAL MOTION (§14)
				 * ----------------------------------------------------------------
				 * The audit (G8) found tempo / kind / duration / distance / stagger /
				 * lift / hover already controllable. What was missing is a single
				 * "how much motion does this site have" decision and an easing.
				 *
				 * `motion_mode` is a MASTER PRESET, not a competing control: it writes
				 * the SAME tokens the individual controls write, and it yields to any
				 * token the customer has explicitly set (DesignSchema::resolve_motion_mode()).
				 * There is therefore still exactly one value per token at render time.
				 * ================================================================ */
				array(
					'key'     => 'motion_mode',
					'token'   => '--bb-motion-mode',
					'type'    => 'select',
					'label'   => __( 'Motion intensity', 'business-builder' ),
					'group'   => 'motion',
					'default' => 'balanced',
					'options' => array(
						'off'        => __( 'None — fully static', 'business-builder' ),
						'subtle'     => __( 'Subtle', 'business-builder' ),
						'balanced'   => __( 'Balanced', 'business-builder' ),
						'expressive' => __( 'Expressive', 'business-builder' ),
					),
				),
				array(
					'key'     => 'motion_ease',
					'token'   => '--bb-ease-standard',
					'type'    => 'select',
					'label'   => __( 'Motion curve', 'business-builder' ),
					'group'   => 'motion',
					'default' => 'ease',
					'options' => array(
						'ease'        => __( 'Ease', 'business-builder' ),
						'ease-in'     => __( 'Ease in', 'business-builder' ),
						'ease-out'    => __( 'Ease out', 'business-builder' ),
						'ease-in-out' => __( 'Ease in and out', 'business-builder' ),
						'linear'      => __( 'Linear', 'business-builder' ),
					),
				),

				/* ================================================================
				 * PHASE 23 — SCROLLBAR (§15)
				 * ----------------------------------------------------------------
				 * The audit (G7) found NO scrollbar token anywhere in the codebase, so
				 * this is a genuinely new capability rather than a duplicate.
				 *
				 * The rules are emitted ONLY when a value is actually set
				 * (SectionStyleSchema::print_scrollbar_styles()), on `html`, because
				 * `html` — not the page wrapper — is the scrolling element. Emitting
				 * them unconditionally would restyle the WordPress admin and every
				 * non-builder site, which is exactly the cross-site leak the
				 * specification forbids.
				 * ================================================================ */
				array(
					'key'     => 'scrollbar_width',
					'token'   => '--bb-scrollbar-width',
					'type'    => 'length',
					'label'   => __( 'Scrollbar thickness', 'business-builder' ),
					'group'   => 'scrollbar',
					'default' => '10px',
					'min'     => 4,
					'max'     => 24,
					'unit'    => 'px',
				),
				array(
					'key'     => 'scrollbar_track',
					'token'   => '--bb-scrollbar-track',
					'type'    => 'color',
					'label'   => __( 'Scrollbar track', 'business-builder' ),
					'group'   => 'scrollbar',
					'default' => '#f1f5f9',
				),
				array(
					'key'     => 'scrollbar_thumb',
					'token'   => '--bb-scrollbar-thumb',
					'type'    => 'color',
					'label'   => __( 'Scrollbar handle', 'business-builder' ),
					'group'   => 'scrollbar',
					'default' => '#94a3b8',
				),
				array(
					'key'     => 'scrollbar_thumb_hover',
					'token'   => '--bb-scrollbar-thumb-hover',
					'type'    => 'color',
					'label'   => __( 'Scrollbar handle (hover)', 'business-builder' ),
					'group'   => 'scrollbar',
					'default' => '#64748b',
				),
			);
	}

	/**
	 * The motion MASTER PRESET library (§14).
	 *
	 * Each intensity level is expressed as a set of values for tokens the schema
	 * ALREADY declares. The mode therefore introduces no token, no storage and no
	 * competing control: it is a named bundle of values for existing controls.
	 *
	 * `off` is not "no value" — it is the deliberate value 0, so a site that asks for
	 * no motion gets no motion rather than inheriting the design's tempo.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function motion_mode_library(): array {

		return array(
			'off'        => array(
				'--bb-motion-normal'   => '0.01',
				'--bb-reveal-duration' => '0.01',
				'--bb-reveal-distance' => '0px',
				'--bb-reveal-stagger'  => '0',
				'--bb-hover-lift'      => '0px',
				'--bb-reveal-kind'     => 'none',
				'--bb-hover-effect'    => 'none',
			),
			'subtle'     => array(
				'--bb-motion-normal'   => '0.16',
				'--bb-reveal-duration' => '0.32',
				'--bb-reveal-distance' => '12px',
				'--bb-reveal-stagger'  => '0.04',
				'--bb-hover-lift'      => '3px',
				'--bb-reveal-kind'     => 'fade',
				'--bb-hover-effect'    => 'lift',
			),
			'balanced'   => array(
				'--bb-motion-normal'   => '0.24',
				'--bb-reveal-duration' => '0.48',
				'--bb-reveal-distance' => '20px',
				'--bb-reveal-stagger'  => '0.06',
				'--bb-hover-lift'      => '6px',
				'--bb-reveal-kind'     => 'fade-up',
				'--bb-hover-effect'    => 'lift',
			),
			'expressive' => array(
				'--bb-motion-normal'   => '0.36',
				'--bb-reveal-duration' => '0.72',
				'--bb-reveal-distance' => '44px',
				'--bb-reveal-stagger'  => '0.10',
				'--bb-hover-lift'      => '10px',
				'--bb-reveal-kind'     => 'fade-up',
				'--bb-hover-effect'    => 'scale',
			),
		);
	}

	/**
	 * Publish the chosen motion intensity as values for the existing motion tokens.
	 *
	 * DETERMINISM (§2, §30)
	 * ---------------------
	 * A master preset must never silently win over a deliberate choice. The rule is
	 * therefore explicit and one-directional:
	 *
	 *   - the customer's own override ALWAYS wins (it is already in `$config` and is
	 *     skipped here);
	 *   - otherwise the mode's value replaces the design's authored value;
	 *   - a token the mode does not mention is left exactly as the design declared it.
	 *
	 * So there is still ONE effective value per token, and the cascade stays
	 * `design → motion mode → explicit override`.
	 *
	 * @param mixed  $config Resolved preset configuration.
	 * @param string $slug   Active design slug.
	 * @return array
	 */
	public function resolve_motion_mode( $config, $slug = '' ): array {

		$config = is_array( $config ) ? $config : array();

		/* The mode is only applied when the customer (or the design) selected one. */
		$mode = isset( $config['--bb-motion-mode'] ) ? sanitize_key( (string) $config['--bb-motion-mode'] ) : '';

		if ( '' === $mode ) {
			return $config;
		}

		$library = $this->motion_mode_library();

		if ( ! isset( $library[ $mode ] ) ) {
			return $config;
		}

		/* Which tokens did the CUSTOMER explicitly set? Those are untouchable. */
		$explicit = array();

		if ( function_exists( 'bb_theme_customization_overrides' ) ) {

			$overrides = bb_theme_customization_overrides();

			if ( is_array( $overrides ) ) {
				$explicit = $overrides;
			}
		}

		foreach ( $library[ $mode ] as $token => $value ) {

			if ( isset( $explicit[ $token ] ) && '' !== (string) $explicit[ $token ] ) {
				continue;
			}

			$config[ $token ] = $value;
		}

		return $config;
	}

	/**
	 * Add the controls the Theme does not already declare.
	 *
	 * @param mixed $schema Existing schema.
	 * @return array
	 */
	public function extend_schema( $schema ): array {

		$schema = is_array( $schema ) ? $schema : array();

		$existing = array();

		foreach ( $schema as $control ) {
			if ( is_array( $control ) && isset( $control['key'] ) ) {
				$existing[ (string) $control['key'] ] = true;
			}
		}

		foreach ( $this->controls() as $control ) {

			/* Never duplicate a control the Theme (or another extension) already provides. */
			if ( isset( $existing[ (string) $control['key'] ] ) ) {
				continue;
			}

			$schema[] = $control;
		}

		return $schema;
	}

	/**
	 * The gradient preset library.
	 *
	 * These are the CURATED, named gradients a customer can select. Each value is a complete,
	 * self-contained CSS gradient built from literal colours (optionally composing the design's
	 * own colour tokens via `var()`), so a gradient never depends on undefined variables.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function gradient_library(): array {

		return array(
			'none'     => array(
				'label' => __( 'None', 'business-builder' ),
				'css'   => 'none',
			),
			'primary'  => array(
				'label' => __( 'Primary', 'business-builder' ),
				'css'   => 'linear-gradient(135deg, var(--bb-color-primary), var(--bb-color-primary-hover))',
			),
			'aurora'   => array(
				'label' => __( 'Aurora', 'business-builder' ),
				'css'   => 'linear-gradient(135deg, #06b6d4, #8b5cf6)',
			),
			'aurora-deep' => array(
				'label' => __( 'Aurora Deep', 'business-builder' ),
				'css'   => 'linear-gradient(160deg, #0f172a, #312e81)',
			),
			'ocean'    => array(
				'label' => __( 'Ocean', 'business-builder' ),
				'css'   => 'linear-gradient(135deg, #0f4c5c, #06b6d4)',
			),
			'emerald'  => array(
				'label' => __( 'Emerald', 'business-builder' ),
				'css'   => 'linear-gradient(135deg, #064e3b, #10b981)',
			),
			'royal'    => array(
				'label' => __( 'Royal', 'business-builder' ),
				'css'   => 'linear-gradient(135deg, #1e1b4b, #6366f1)',
			),
			'gold'     => array(
				'label' => __( 'Luxury Gold', 'business-builder' ),
				'css'   => 'linear-gradient(135deg, #d4af37, #f5e6a8)',
			),
			'gold-deep' => array(
				'label' => __( 'Deep Gold', 'business-builder' ),
				'css'   => 'linear-gradient(160deg, #1a1409, #8a5c18)',
			),
			'violet'   => array(
				'label' => __( 'Violet', 'business-builder' ),
				'css'   => 'linear-gradient(135deg, #7c3aed, #c084fc)',
			),
			'teal'     => array(
				'label' => __( 'Teal', 'business-builder' ),
				'css'   => 'linear-gradient(135deg, #14b8a6, #22c55e)',
			),
			'blue'     => array(
				'label' => __( 'Blue', 'business-builder' ),
				'css'   => 'linear-gradient(135deg, #2563eb, #06b6d4)',
			),
			'clinical' => array(
				'label' => __( 'Clinical', 'business-builder' ),
				'css'   => 'linear-gradient(135deg, #0369a1, #38bdf8)',
			),
			'sand'     => array(
				'label' => __( 'Sand', 'business-builder' ),
				'css'   => 'linear-gradient(135deg, #c2410c, #fdba74)',
			),
			'copper'   => array(
				'label' => __( 'Copper', 'business-builder' ),
				'css'   => 'linear-gradient(135deg, #7c2d12, #b8843c)',
			),
			'charcoal' => array(
				'label' => __( 'Charcoal', 'business-builder' ),
				'css'   => 'linear-gradient(135deg, #0b0f19, #273244)',
			),
		);
	}

	/**
	 * The gradient options for a `select` control (value => translated label).
	 *
	 * @return array<string, string>
	 */
	public function gradient_options(): array {

		$options = array();

		foreach ( $this->gradient_library() as $slug => $gradient ) {
			$options[ $slug ] = (string) $gradient['label'];
		}

		return $options;
	}

	/**
	 * The CURATED background library (§10).
	 *
	 * Each entry is a complete, self-contained CSS `background-image` value built from literal
	 * colours (never from an undefined variable), so a background can never depend on a token the
	 * design did not declare. Modern/trending styles are expressed with plain CSS gradients only -
	 * no external background or animation library is loaded.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function background_library(): array {

		return array(
			'none'      => array(
				'label' => __( 'Solid colour', 'business-builder' ),
				'css'   => 'none',
			),
			'soft'      => array(
				'label' => __( 'Soft gradient', 'business-builder' ),
				'css'   => 'linear-gradient(180deg, var(--bb-color-background), var(--bb-color-surface-muted))',
			),
			'mesh'      => array(
				'label' => __( 'Mesh gradient', 'business-builder' ),
				'css'   => 'radial-gradient(at 12% 18%, rgba(99, 102, 241, 0.28) 0px, transparent 55%), radial-gradient(at 88% 12%, rgba(6, 182, 212, 0.24) 0px, transparent 55%), radial-gradient(at 70% 88%, rgba(139, 92, 246, 0.22) 0px, transparent 55%)',
			),
			'aurora'    => array(
				'label' => __( 'Aurora', 'business-builder' ),
				'css'   => 'linear-gradient(160deg, #0f172a 0%, #1e1b4b 45%, #312e81 100%)',
			),
			'dark-radial' => array(
				'label' => __( 'Dark radial', 'business-builder' ),
				'css'   => 'radial-gradient(circle at 50% 0%, #1f2937 0%, #0b0f19 70%)',
			),
			'grid'      => array(
				'label' => __( 'Subtle grid', 'business-builder' ),
				'css'   => 'linear-gradient(rgba(148, 163, 184, 0.14) 1px, transparent 1px), linear-gradient(90deg, rgba(148, 163, 184, 0.14) 1px, transparent 1px)',
			),
			'glow'      => array(
				'label' => __( 'Glow', 'business-builder' ),
				'css'   => 'radial-gradient(ellipse at 50% -10%, rgba(37, 99, 235, 0.35) 0px, transparent 60%)',
			),
			'layered'   => array(
				'label' => __( 'Layered surface', 'business-builder' ),
				'css'   => 'linear-gradient(180deg, var(--bb-color-surface) 0%, var(--bb-color-background) 100%)',
			),
			'glass'     => array(
				'label' => __( 'Glass background', 'business-builder' ),
				'css'   => 'linear-gradient(135deg, rgba(255, 255, 255, 0.55) 0%, rgba(226, 232, 240, 0.35) 100%)',
			),
			'editorial' => array(
				'label' => __( 'Editorial neutral', 'business-builder' ),
				'css'   => 'linear-gradient(180deg, #faf9f7 0%, #f1f0ec 100%)',
			),
		);
	}

	/**
	 * The background `select` options (slug => label).
	 *
	 * @return array<string, string>
	 */
	public function background_options(): array {

		$options = array();

		foreach ( $this->background_library() as $slug => $entry ) {
			$options[ $slug ] = (string) $entry['label'];
		}

		return $options;
	}

	/**
	 * The CURATED overlay library (§10).
	 *
	 * An overlay is a tint layer drawn over the background so text stays readable on an image or
	 * a busy gradient. Its STRENGTH is a separate control, so a customer can dial the tint without
	 * changing its colour.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function overlay_library(): array {

		return array(
			'none'    => array(
				'label' => __( 'None', 'business-builder' ),
				'css'   => 'none',
			),
			'dark'    => array(
				'label' => __( 'Dark tint', 'business-builder' ),
				'css'   => 'linear-gradient(180deg, rgba(2, 6, 23, 0.75), rgba(2, 6, 23, 0.55))',
			),
			'light'   => array(
				'label' => __( 'Light veil', 'business-builder' ),
				'css'   => 'linear-gradient(180deg, rgba(255, 255, 255, 0.8), rgba(255, 255, 255, 0.6))',
			),
			'brand'   => array(
				'label' => __( 'Brand tint', 'business-builder' ),
				'css'   => 'linear-gradient(160deg, var(--bb-color-primary), var(--bb-color-primary-hover, var(--bb-color-primary)))',
			),
		);
	}

	/**
	 * The overlay `select` options (slug => label).
	 *
	 * @return array<string, string>
	 */
	public function overlay_options(): array {

		$options = array();

		foreach ( $this->overlay_library() as $slug => $entry ) {
			$options[ $slug ] = (string) $entry['label'];
		}

		return $options;
	}

	/**
	 * The column-count options used by the grid controls (§11).
	 *
	 * @return array<string, string>
	 */
	public function column_options(): array {

		return array(
			'1' => '1',
			'2' => '2',
			'3' => '3',
			'4' => '4',
			'5' => '5',
			'6' => '6',
		);
	}

	/**
	 * The token names this class resolves from a SLUG to CSS (§10, §19).
	 *
	 * Both the background library and the overlay library store a slug; the Theme's `:root`
	 * emitter writes the stored value verbatim, so the slug must be translated before it reaches
	 * the front end. Enumerating them here keeps the translation table explicit rather than
	 * inferred.
	 *
	 * @return array<string, string> token => library key
	 */
	protected function slug_libraries(): array {

		return array(
			'--bb-bg-gradient' => 'background',
			'--bb-bg-overlay'  => 'overlay',
		);
	}

	/**
	 * Filter the gradient choice LAYER: resolve each option value to its CSS.
	 *
	 * Exposed so a UI (the Design Studio, the Customizer) can render a real gradient preview
	 * without duplicating the library. A `select` control stores the slug; this filter is the
	 * single place the slug is turned into CSS.
	 *
	 * @param array $choices Existing choices.
	 * @return array
	 */
	public function gradient_choices( $choices ): array {

		$choices = is_array( $choices ) ? $choices : array();

		$out = array();

		foreach ( $this->gradient_library() as $slug => $gradient ) {
			$out[ $slug ] = array(
				'label' => (string) $gradient['label'],
				'css'   => (string) $gradient['css'],
			);
		}

		return $out;
	}

	/**
	 * Resolve the CSS for a stored gradient value.
	 *
	 * A gradient control stores a SLUG. The Theme's `:root` emitter writes the raw stored value,
	 * so the slug must be translated to CSS before it reaches the front end — this filter does
	 * that inside the EXISTING preset-config cascade.
	 *
	 * IMPORTANT: this writes only `--bb-*` token names that the Theme has already declared in
	 * `bb_theme_design_schema()`, so it introduces no new namespace and cannot emit an unlisted
	 * property.
	 *
	 * @param array  $config Resolved preset configuration.
	 * @param string $slug   Active design slug.
	 * @return array
	 */
	public function resolve_gradients( $config, $slug = '' ): array {

		$config = is_array( $config ) ? $config : array();

		/*
		 * ONE resolver for every SLUG-valued control (§10, §19). A `select` control stores a slug,
		 * and the Theme's `:root` emitter writes the stored value verbatim - so the slug must be
		 * translated to CSS here, inside the EXISTING preset-config cascade, before it reaches the
		 * front end.
		 *
		 * The translation table is EXPLICIT (`slug_libraries()`), so a token can only ever be
		 * resolved against a named, curated library - no arbitrary value can be introduced.
		 */
		$libraries = array(
			'--bb-gradient-' => $this->gradient_library(),
			'--bb-bg-gradient' => $this->background_library(),
			'--bb-bg-overlay'  => $this->overlay_library(),
		);

		foreach ( $libraries as $prefix => $library ) {

			foreach ( $config as $token => $value ) {

				$token = (string) $token;

				/* `--bb-gradient-*` is a prefix match; the background/overlay tokens are exact. */
				$matches = ( 0 === strpos( $token, '--bb-gradient-' ) && 0 === strpos( $prefix, '--bb-gradient-' ) )
					|| $token === $prefix;

				if ( ! $matches ) {
					continue;
				}

				$value = trim( (string) $value );

				if ( '' === $value ) {
					continue;
				}

				/* Already resolved CSS (a preset may ship the value directly). */
				if ( false !== strpos( $value, 'gradient(' ) || 'none' === $value ) {
					continue;
				}

				if ( isset( $library[ $value ] ) ) {
					$config[ $token ] = (string) $library[ $value ]['css'];
				}
			}
		}

		return $config;
	}

	/**
	 * Append the missing CSS unit to `number` controls that carry a `unit`.
	 *
	 * MEASURED REASON: the Theme's existing sanitizer rejects `ms` and `s` for `length` controls
	 * (and rejects negative lengths), so motion and letter-spacing are stored as plain `number`s.
	 * A raw number is NOT a valid CSS time (`transition-duration: 0.24` is invalid), so the unit
	 * is appended here, inside the existing cascade, immediately before the Theme emits `:root`.
	 *
	 * Only the tokens this class declares are touched, and only with a unit that the control
	 * itself defines — no arbitrary string can be introduced.
	 *
	 * @param array  $config Resolved preset configuration.
	 * @param string $slug   Active design slug.
	 * @return array
	 */
	public function resolve_units( $config, $slug = '' ): array {

		$config = is_array( $config ) ? $config : array();

		foreach ( $this->controls() as $control ) {

			if ( 'number' !== ( $control['type'] ?? '' ) ) {
				continue;
			}

			$unit = isset( $control['unit'] ) ? (string) $control['unit'] : '';

			if ( '' === $unit ) {
				continue;
			}

			$token = (string) $control['token'];

			if ( ! isset( $config[ $token ] ) ) {
				continue;
			}

			$value = trim( (string) $config[ $token ] );

			if ( '' === $value ) {
				continue;
			}

			/* Already carries a unit (a preset may ship `0.24s` directly) — leave it alone. */
			if ( ! is_numeric( $value ) ) {
				continue;
			}

			$config[ $token ] = $value . $unit;
		}

		return $config;
	}
}