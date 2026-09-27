<?php

namespace BusinessBuilderCore\Packs\Medical\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Medical Design Catalogue (Phase 21).
 *
 * Three COMPLETE VISUAL IDENTITIES for the Medical business type, contributed through the
 * EXISTING theme preset filters - the same mechanism Phase 19 proved with `medical-modern`.
 *
 * Phase 19's `medical-modern` preset is PRESERVED (see MedicalPresets) so existing sites that
 * selected it keep working. It additionally receives business-type compatibility metadata below,
 * WITHOUT its tokens being altered, so a site already using it renders identically.
 *
 * =====================================================================================
 * WHAT MAKES THESE "DESIGNS" AND NOT "COLOUR PRESETS"
 * =====================================================================================
 * Each design is authored across EVERY independent axis the Phase 21 token system exposes:
 * palette, gradients, typography, DENSITY, shape language, component language, SHELL variant
 * and motion. The three directions differ in KIND:
 *
 *   1. CLARITY (Clinical)      - clean white clinical surfaces, cool medical blue, restrained
 *                                teal gradients, airy density, minimal elevation, split
 *                                header, columns footer, gentle motion. Calm and legible.
 *
 *   2. VITALITY (Modern Care)  - glassy and warm, generous rounding, turquoise-to-violet
 *                                gradients, soft deep shadows, centered navigation, lively
 *                                motion. Reassuring and patient-friendly.
 *
 *   3. PRECISION (Specialist)  - DARK navy institutional surfaces, emerald accent, thin
 *                                luminous borders, dense spacing, centered editorial header,
 *                                minimal footer, deliberate motion. Diagnostic-grade gravitas.
 *
 * Remove the colour from all three and they remain unmistakably different websites.
 *
 * =====================================================================================
 * ARCHITECTURAL RULES OBSERVED (Phase 21 §4, §5, §33, §40, §48, §64)
 * =====================================================================================
 *   - ONE Core Theme, no per-design theme/template/HTML.
 *   - ONE token namespace: every value is an EXISTING or Phase-21 `--bb-*` token. No `--medical-*`.
 *   - Presentation only: no CPT, no doctor data, no clinical logic, no queries.
 *   - Registry-driven: nothing here is hardcoded into Core or the Theme.
 *   - `shell` is a SUGGESTION resolved by the Phase 15 resolver, never a template path.
 */
class MedicalDesigns {

	/**
	 * The business type these designs belong to.
	 */
	public const BUSINESS_TYPE = 'medical';

	/**
	 * Register the catalogue.
	 */
	public function register(): void {

		add_filter( 'bb_theme_presets', array( $this, 'register_presets' ) );

		add_filter( 'bb_theme_preset_config', array( $this, 'refine_config' ), 10, 2 );

		/*
		 * Phase 19 contributed `medical-modern` without business-type metadata. Declare its
		 * compatibility so the catalogue filters it correctly, without altering the design
		 * itself (existing sites that selected it must render exactly as before).
		 *
		 * Priority 20: this must run AFTER BOTH the Phase 19 contributor (priority 10) and this
		 * class's own register_presets() (priority 10), otherwise the preset does not exist yet
		 * when the annotation is applied and it would stay uncategorized (i.e. treated as a
		 * global design suitable for every business type).
		 */
		add_filter( 'bb_theme_presets', array( $this, 'annotate_phase19_preset' ), 20 );
	}

	/**
	 * The catalogue.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function designs(): array {

		return array(

			/* =========================================================================
			 * 1 - CLARITY
			 * -------------------------------------------------------------------------
			 * DIRECTION: clean, editorial, clinical, extremely readable.
			 * KIND:      white clinical surfaces + cool medical blue.
			 * Distinct because: near-white surfaces throughout, cool BLUE primary with a
			 * restrained TEAL gradient, generous whitespace, minimal elevation, a SPLIT
			 * header and COLUMNS footer, and gentle motion. Calm, institutional, legible.
			 * ======================================================================= */
			'medical-clarity' => array(
				'label'          => __( 'Clarity', 'business-builder' ),
				'description'    => __( 'Clean and highly readable. Cool clinical blue, generous whitespace and a calm, trustworthy tone.', 'business-builder' ),
				'business_types' => array( self::BUSINESS_TYPE ),
				'version'        => '2.0.0',

				/* -- Brand -- */
				'--bb-color-primary'          => '#1d4ed8',
				'--bb-color-primary-hover'    => '#1e40af',
				'--bb-color-primary-light'    => '#60a5fa',
				'--bb-color-primary-pale'     => '#dbeafe',
				'--bb-color-accent'           => '#0d9488',
				'--bb-color-accent-light'     => '#5eead4',
				'--bb-color-secondary'        => '#0f172a',
				'--bb-color-background'       => '#f8fafc',
				'--bb-color-surface'          => '#ffffff',
				'--bb-color-surface-muted'    => '#f1f5f9',
				'--bb-color-text'             => '#1f2937',
				'--bb-color-text-muted'       => '#6b7280',
				'--bb-color-heading'          => '#111827',
				'--bb-color-border'           => '#e5e7eb',
				'--bb-color-border-strong'    => '#d1d5db',
				'--bb-color-text-inverse'     => '#ffffff',

				/* -- Gradients: restrained, medical -- */
				'--bb-gradient-brand'         => 'linear-gradient(135deg, #1d4ed8, #06b6d4)',
				'--bb-gradient-hero'          => 'linear-gradient(180deg, #f8fafc, #e0f2fe)',
				'--bb-gradient-cta'           => 'linear-gradient(135deg, #1d4ed8, #0d9488)',
				'--bb-gradient-decorative'    => 'linear-gradient(90deg, #1d4ed8, #5eead4)',

				/* -- Typography: clean modern sans, neutral tracking -- */
				'--bb-font-heading'           => '"Inter", "Manrope", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans Arabic", sans-serif',
				'--bb-font-primary'           => '"Inter", "Manrope", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans Arabic", sans-serif',
				'--bb-heading-scale'          => '1.04',
				'--bb-heading-letter-spacing' => '-0.015',

				/* -- Density: airy and legible, generous whitespace -- */
				'--bb-section-padding-block'  => '96px',
				'--bb-grid-gap'               => '28px',
				'--bb-card-padding'           => '28px',

				/* -- Shape: moderate, clean, low elevation -- */
				'--bb-radius-sm'              => '0.25rem',
				'--bb-radius-md'              => '0.5rem',
				'--bb-radius-lg'              => '0.75rem',
				'--bb-radius-card'            => '12px',
				'--bb-radius-input'           => '8px',
				'--bb-button-radius'          => '8px',
				'--bb-border-thickness'       => '1px',
				'--bb-shadow-md'              => '0 1px 3px rgba(15, 23, 42, 0.07)',
				'--bb-shadow-card'            => '0 2px 8px rgba(15, 23, 42, 0.06)',
				'--bb-shadow-hover'           => '0 10px 26px rgba(29, 78, 216, 0.12)',

				/* -- Components: clear and unfussy -- */
				'--bb-button-padding-block'   => '14px',
				'--bb-button-padding-inline'  => '30px',
				'--bb-button-weight'          => '600',
				'--bb-button-shadow'          => 'none',
				'--bb-button-shadow-hover'    => '0 6px 18px rgba(29, 78, 216, 0.22)',
				'--bb-badge-bg'               => '#dbeafe',
				'--bb-badge-color'            => '#1e40af',
				'--bb-badge-border'           => '#bfdbfe',

				/* -- Shell: clean split header, structured footer -- */
				'--bb-header-bg'              => '#ffffff',
				'--bb-header-color'           => '#111827',
				'--bb-header-height'          => '80px',
				'--bb-header-blur'            => '0px',
				'--bb-header-border-width'    => '1px',
				'--bb-nav-gap'                => '28px',
				'--bb-footer-bg'              => '#111827',
				'--bb-footer-color'           => '#9ca3af',
				'--bb-footer-padding-block'   => '60px',

				/* -- Motion: gentle, reassuring -- */
				'--bb-motion-normal'          => '0.22s',
				'--bb-reveal-duration'        => '0.42s',
				'--bb-reveal-distance'        => '16px',
				'--bb-hover-lift'             => '3px',

				'--bb-container-width'        => '1200px',

				/*
				 * -- PHASE 22 AXES -----------------------------------------------------
				 * Clarity is the EDITORIAL clinical design: a neutral wash background, no
				 * glass, three columns, a quiet fade, and a header that goes from a
				 * transparent tint over the hero to a solid white bar.
				 */
				'--bb-bg-color'               => '#f8fafc',
				'--bb-bg-gradient'            => 'linear-gradient(180deg, #f8fafc, #f1f5f9)',
				'--bb-bg-overlay'             => 'none',
				'--bb-bg-overlay-opacity'     => '0',

				'--bb-grid-columns'           => '3',
				'--bb-grid-columns-tablet'    => '2',
				'--bb-grid-columns-mobile'    => '1',
				'--bb-grid-row-gap'           => '28px',
				'--bb-card-min-width'         => '280px',
				'--bb-content-width'          => '68ch',

				'--bb-glass-blur'             => '0px',
				'--bb-glass-saturate'         => '100%',
				'--bb-glass-opacity'          => '1',

				'--bb-header-initial-bg'      => '#1d4ed8',
				'--bb-header-initial-nav'     => '#ffffff',
				'--bb-header-initial-transparency' => '0',
				'--bb-header-scrolled-bg'     => '#ffffff',
				'--bb-header-scrolled-nav'    => '#1f2937',
				'--bb-header-scrolled-border' => '#e5e7eb',
				'--bb-header-scrolled-shadow' => '0 1px 4px rgba(15, 23, 42, 0.08)',
				'--bb-header-scroll-transition' => '0.24s',

				'--bb-footer-heading-color'   => '#ffffff',
				'--bb-footer-link-color'      => '#cbd5e1',
				'--bb-footer-link-hover'      => '#5eead4',
				'--bb-footer-column-gap'      => '32px',

				'--bb-reveal-kind'            => 'fade-up',
				'--bb-reveal-stagger'         => '50ms',
				'--bb-hover-effect'           => 'lift',

				'shell'                       => array(
					'header'     => 'split',
					'navigation' => 'default',
					'footer'     => 'columns',
				),
			),

			/* =========================================================================
			 * 2 - VITALITY
			 * -------------------------------------------------------------------------
			 * DIRECTION: modern, warm, approachable, patient-friendly.
			 * KIND:      glass surfaces, generous rounding, turquoise-to-violet gradients.
			 *
			 * Distinct because: VERY generous rounding (pill buttons, 22px cards), a
			 * turquoise-to-violet GRADIENT signature, soft DEEP shadows, glassy translucent
			 * header, CENTERED navigation, warm-tinted surfaces and lively motion.
			 * ======================================================================= */
			'medical-vitality' => array(
				'label'          => __( 'Vitality', 'business-builder' ),
				'description'    => __( 'Warm and reassuring. Soft rounded surfaces, glassy depth and a friendly, modern gradient palette.', 'business-builder' ),
				'business_types' => array( self::BUSINESS_TYPE ),
				'version'        => '2.0.0',

				/* -- Brand -- */
				'--bb-color-primary'          => '#0d9488',
				'--bb-color-primary-hover'    => '#0f766e',
				'--bb-color-primary-light'    => '#5eead4',
				'--bb-color-primary-pale'     => '#ccfbf1',
				'--bb-color-accent'           => '#8b5cf6',
				'--bb-color-accent-light'     => '#c4b5fd',
				'--bb-color-secondary'        => '#06b6d4',
				'--bb-color-background'       => '#f7fdfc',
				'--bb-color-surface'          => '#ffffff',
				'--bb-color-surface-muted'    => '#f0fdfa',
				'--bb-color-text'             => '#134e4a',
				'--bb-color-text-muted'       => '#5f7d7a',
				'--bb-color-heading'          => '#0f3d38',
				'--bb-color-border'           => '#d5f0ec',
				'--bb-color-border-strong'    => '#a9e2da',
				'--bb-color-text-inverse'     => '#ffffff',

				/* -- Gradients: the friendly signature -- */
				'--bb-gradient-brand'         => 'linear-gradient(135deg, #14b8a6, #8b5cf6)',
				'--bb-gradient-hero'          => 'linear-gradient(160deg, #0f766e, #7c3aed)',
				'--bb-gradient-cta'           => 'linear-gradient(135deg, #14b8a6, #06b6d4)',
				'--bb-gradient-decorative'    => 'linear-gradient(90deg, #2dd4bf, #a78bfa)',
				'--bb-gradient-surface'       => 'linear-gradient(180deg, #ffffff, #f0fdfa)',

				/* -- Typography: rounded modern sans -- */
				'--bb-font-heading'           => '"Plus Jakarta Sans", "Manrope", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans Arabic", sans-serif',
				'--bb-font-primary'           => '"Plus Jakarta Sans", "Manrope", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans Arabic", sans-serif',
				'--bb-heading-scale'          => '1.08',
				'--bb-heading-letter-spacing' => '-0.005',

				/* -- Density: spacious and soft -- */
				'--bb-section-padding-block'  => '104px',
				'--bb-grid-gap'               => '30px',
				'--bb-card-padding'           => '30px',

				/* -- Shape: very rounded, soft, approachable -- */
				'--bb-radius-sm'              => '0.625rem',
				'--bb-radius-md'              => '1rem',
				'--bb-radius-lg'              => '1.5rem',
				'--bb-radius-card'            => '22px',
				'--bb-radius-input'           => '14px',
				'--bb-button-radius'          => '999px',
				'--bb-border-thickness'       => '1px',
				'--bb-shadow-md'              => '0 12px 32px rgba(13, 148, 136, 0.10)',
				'--bb-shadow-card'            => '0 14px 38px rgba(13, 148, 136, 0.11)',
				'--bb-shadow-hover'           => '0 24px 56px rgba(139, 92, 246, 0.18)',

				/* -- Components: soft pill buttons with gentle glow -- */
				'--bb-button-padding-block'   => '16px',
				'--bb-button-padding-inline'  => '36px',
				'--bb-button-weight'          => '600',
				'--bb-button-shadow'          => '0 6px 18px rgba(20, 184, 166, 0.26)',
				'--bb-button-shadow-hover'    => '0 12px 30px rgba(139, 92, 246, 0.34)',
				'--bb-badge-bg'               => '#ccfbf1',
				'--bb-badge-color'            => '#0f766e',
				'--bb-badge-border'           => '#99f6e4',

				/* -- Shell: glass header, centered nav, columns footer -- */
				'--bb-header-bg'              => '#ffffff',
				'--bb-header-color'           => '#0f3d38',
				'--bb-header-height'          => '80px',
				'--bb-header-blur'            => '14px',
				'--bb-header-saturate'        => '180%',
				'--bb-header-border-width'    => '1px',
				'--bb-nav-gap'                => '26px',
				'--bb-footer-bg'              => '#0f3d38',
				'--bb-footer-color'           => '#a9e2da',
				'--bb-footer-padding-block'   => '64px',

				/* -- Motion: lively and warm -- */
				'--bb-motion-normal'          => '0.28s',
				'--bb-reveal-duration'        => '0.54s',
				'--bb-reveal-distance'        => '24px',
				'--bb-hover-lift'             => '6px',

				'--bb-container-width'        => '1240px',

				/*
				 * -- PHASE 22 AXES -----------------------------------------------------
				 * Vitality is the GLASS design: a soft mesh background, a frosted header,
				 * glass cards and a scale-in reveal with an image zoom on hover.
				 */
				'--bb-bg-color'               => '#f0fdfa',
				'--bb-bg-gradient'            => 'radial-gradient(at 15% 20%, rgba(13, 148, 136, 0.25) 0px, transparent 55%), radial-gradient(at 85% 15%, rgba(139, 92, 246, 0.22) 0px, transparent 55%)',
				'--bb-bg-overlay'             => 'none',
				'--bb-bg-overlay-opacity'     => '0',

				'--bb-grid-columns'           => '4',
				'--bb-grid-columns-tablet'    => '2',
				'--bb-grid-columns-mobile'    => '1',
				'--bb-grid-row-gap'           => '24px',
				'--bb-card-min-width'         => '240px',
				'--bb-content-width'          => '72ch',

				/* Glass ON: the strongest treatment in the Medical catalogue. */
				'--bb-glass-blur'             => '20px',
				'--bb-glass-saturate'         => '170%',
				'--bb-glass-opacity'          => '0.75',
				'--bb-glass-border-opacity'   => '0.4',

				'--bb-header-initial-bg'      => '#0d9488',
				'--bb-header-initial-nav'     => '#ffffff',
				'--bb-header-initial-transparency' => '0.45',
				'--bb-header-scrolled-bg'     => '#ffffff',
				'--bb-header-scrolled-nav'    => '#0f766e',
				'--bb-header-scrolled-border' => '#ccfbf1',
				'--bb-header-scrolled-shadow' => '0 6px 24px rgba(13, 148, 136, 0.12)',
				'--bb-header-scroll-transition' => '0.32s',
				'--bb-header-blur'            => '20px',
				'--bb-header-saturate'        => '170%',

				'--bb-footer-heading-color'   => '#ffffff',
				'--bb-footer-link-color'      => '#99f6e4',
				'--bb-footer-link-hover'      => '#ffffff',
				'--bb-footer-column-gap'      => '36px',

				'--bb-reveal-kind'            => 'scale',
				'--bb-reveal-stagger'         => '90ms',
				'--bb-hover-effect'           => 'zoom',

				'shell'                       => array(
					'header'     => 'default',
					'navigation' => 'centered',
					'footer'     => 'columns',
				),
			),

			/* =========================================================================
			 * 3 - PRECISION
			 * -------------------------------------------------------------------------
			 * DIRECTION: dark, institutional, diagnostic-grade, premium specialist.
			 * KIND:      dark navy surfaces + emerald light, dense and exact.
			 *
			 * Distinct because: it is DARK where the others are light. Deep navy
			 * surfaces with EMERALD accents, THIN luminous borders, TIGHTER spacing and
			 * a denser grid, serif headings, centered header and MINIMAL footer, and slow
			 * deliberate motion. It reads as specialised clinical technology.
			 * ======================================================================= */
			'medical-precision' => array(
				'label'          => __( 'Precision', 'business-builder' ),
				'description'    => __( 'Dark, dense and exact. Deep navy surfaces with emerald accents for specialist and diagnostic centres.', 'business-builder' ),
				'business_types' => array( self::BUSINESS_TYPE ),
				'version'        => '2.0.0',

				/* -- Brand: dark navy surfaces, emerald light -- */
				'--bb-color-primary'          => '#10b981',
				'--bb-color-primary-hover'    => '#059669',
				'--bb-color-primary-light'    => '#6ee7b7',
				'--bb-color-primary-pale'     => '#0f2e26',
				'--bb-color-accent'           => '#22d3ee',
				'--bb-color-accent-light'     => '#67e8f9',
				'--bb-color-secondary'        => '#0e7490',
				'--bb-color-background'       => '#070d18',
				'--bb-color-surface'          => '#101a2c',
				'--bb-color-surface-muted'    => '#0a1220',
				'--bb-color-text'             => '#cbd5e1',
				'--bb-color-text-muted'       => '#8ba0b8',
				'--bb-color-heading'          => '#f1f5f9',
				'--bb-color-border'           => '#22314a',
				'--bb-color-border-strong'    => '#33475f',
				'--bb-color-text-inverse'     => '#04121a',

				/* -- Gradients: emerald clinical light -- */
				'--bb-gradient-brand'         => 'linear-gradient(135deg, #10b981, #22d3ee)',
				'--bb-gradient-hero'          => 'linear-gradient(160deg, #070d18, #062b26)',
				'--bb-gradient-cta'           => 'linear-gradient(135deg, #059669, #0891b2)',
				'--bb-gradient-decorative'    => 'linear-gradient(90deg, #10b981, #22d3ee)',
				'--bb-gradient-surface'       => 'linear-gradient(180deg, #101a2c, #0a1220)',

				/* -- Typography: serif headings for authority, tight -- */
				'--bb-font-heading'           => '"Source Serif 4", Georgia, "Times New Roman", "Noto Naskh Arabic", serif',
				'--bb-font-primary'           => '"Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans Arabic", sans-serif',
				'--bb-heading-scale'          => '1',
				'--bb-heading-letter-spacing' => '-0.01',

				/* -- Density: TIGHT and data-first -- */
				'--bb-section-padding-block'  => '72px',
				'--bb-grid-gap'               => '20px',
				'--bb-card-padding'           => '22px',

				/* -- Shape: precise, thin-edged, minimal rounding -- */
				'--bb-radius-sm'              => '0.125rem',
				'--bb-radius-md'              => '0.25rem',
				'--bb-radius-lg'              => '0.375rem',
				'--bb-radius-card'            => '6px',
				'--bb-radius-input'           => '4px',
				'--bb-button-radius'          => '4px',
				'--bb-border-thickness'       => '1px',
				'--bb-shadow-md'              => '0 6px 22px rgba(0, 0, 0, 0.50)',
				'--bb-shadow-card'            => '0 8px 28px rgba(0, 0, 0, 0.55)',
				'--bb-shadow-hover'           => '0 16px 44px rgba(16, 185, 129, 0.20)',

				/* -- Components: solid emerald, quiet secondary -- */
				'--bb-button-padding-block'   => '12px',
				'--bb-button-padding-inline'  => '26px',
				'--bb-button-weight'          => '600',
				'--bb-button-shadow'          => 'none',
				'--bb-button-shadow-hover'    => '0 8px 24px rgba(16, 185, 129, 0.32)',
				'--bb-badge-bg'               => '#0f2e26',
				'--bb-badge-color'            => '#6ee7b7',
				'--bb-badge-border'           => '#1c5545',

				/* -- Shell: dark glass, centered editorial, minimal footer -- */
				'--bb-header-bg'              => '#0a1220',
				'--bb-header-color'           => '#f1f5f9',
				'--bb-header-height'          => '72px',
				'--bb-header-blur'            => '10px',
				'--bb-header-saturate'        => '140%',
				'--bb-header-border-width'    => '1px',
				'--bb-nav-gap'                => '30px',
				'--bb-footer-bg'              => '#070d18',
				'--bb-footer-color'           => '#8ba0b8',
				'--bb-footer-padding-block'   => '56px',

				/* -- Motion: deliberate, technical -- */
				'--bb-motion-normal'          => '0.32s',
				'--bb-reveal-duration'        => '0.6s',
				'--bb-reveal-distance'        => '14px',
				'--bb-hover-lift'             => '2px',

				'--bb-container-width'        => '1280px',

				/*
				 * -- PHASE 22 AXES -----------------------------------------------------
				 * Precision is the DARK diagnostic design: a dark radial background, no
				 * glass, a denser grid with a wider row gap, and a slow blur reveal with
				 * a glow hover.
				 */
				'--bb-bg-color'               => '#060d1a',
				'--bb-bg-gradient'            => 'radial-gradient(circle at 50% 0%, #12294a 0%, #060d1a 70%)',
				'--bb-bg-overlay'             => 'none',
				'--bb-bg-overlay-opacity'     => '0',

				'--bb-grid-columns'           => '3',
				'--bb-grid-columns-tablet'    => '2',
				'--bb-grid-columns-mobile'    => '1',
				'--bb-grid-row-gap'           => '32px',
				'--bb-card-min-width'         => '300px',
				'--bb-content-width'          => '64ch',

				'--bb-glass-blur'             => '0px',
				'--bb-glass-saturate'         => '100%',
				'--bb-glass-opacity'          => '1',

				'--bb-header-initial-bg'      => '#060d1a',
				'--bb-header-initial-nav'     => '#a7f3d0',
				'--bb-header-initial-transparency' => '0',
				'--bb-header-scrolled-bg'     => '#0b1526',
				'--bb-header-scrolled-nav'    => '#a7f3d0',
				'--bb-header-scrolled-border' => '#14324a',
				'--bb-header-scrolled-shadow' => '0 8px 28px rgba(0, 0, 0, 0.55)',
				'--bb-header-scroll-transition' => '0.38s',

				'--bb-footer-heading-color'   => '#a7f3d0',
				'--bb-footer-link-color'      => '#94a3b8',
				'--bb-footer-link-hover'      => '#34d399',
				'--bb-footer-column-gap'      => '44px',

				'--bb-reveal-kind'            => 'blur',
				'--bb-reveal-stagger'         => '110ms',
				'--bb-hover-effect'           => 'glow',

				'shell'                       => array(
					'header'     => 'centered',
					'navigation' => 'centered',
					'footer'     => 'minimal',
				),
			),
		);
	}

	/**
	 * Register the designs in the theme preset registry.
	 *
	 * @param array $presets Existing presets.
	 * @return array
	 */
	public function register_presets( $presets ): array {

		$presets = is_array( $presets ) ? $presets : array();

		foreach ( $this->designs() as $slug => $design ) {
			$presets[ $slug ] = $design;
		}

		return $presets;
	}

	/**
	 * Declare the business type for Phase 19's `medical-modern` preset.
	 *
	 * The tokens are intentionally left EXACTLY as Phase 19 shipped them, so a site that
	 * already selected that design renders identically. Only compatibility metadata is added.
	 *
	 * @param array $presets Existing presets.
	 * @return array
	 */
	public function annotate_phase19_preset( $presets ): array {

		$presets = is_array( $presets ) ? $presets : array();

		if ( isset( $presets['medical-modern'] ) && ! isset( $presets['medical-modern']['business_types'] ) ) {

			$presets['medical-modern']['business_types'] = array( self::BUSINESS_TYPE );

			if ( ! isset( $presets['medical-modern']['description'] ) ) {
				$presets['medical-modern']['description'] = __( 'Clean clinical palette with generous spacing.', 'business-builder' );
			}

			if ( ! isset( $presets['medical-modern']['version'] ) ) {
				$presets['medical-modern']['version'] = '1.0.0';
			}
		}

		return $presets;
	}

	/**
	 * Additive refinement of this pack's own designs only.
	 *
	 * @param array  $config Resolved preset config.
	 * @param string $slug   Active preset slug.
	 * @return array
	 */
	public function refine_config( $config, $slug = '' ): array {

		$config = is_array( $config ) ? $config : array();

		$designs = $this->designs();

		if ( ! isset( $designs[ $slug ] ) ) {
			return $config;
		}

		foreach ( $designs[ $slug ] as $key => $value ) {

			if ( 0 !== strpos( (string) $key, '--bb-' ) ) {
				continue;
			}

			if ( ! isset( $config[ $key ] ) ) {
				$config[ $key ] = $value;
			}
		}

		return $config;
	}
}