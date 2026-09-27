<?php

namespace BusinessBuilderCore\Packs\LawFirm\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LawFirm Design Catalogue (Phase 21).
 *
 * Three COMPLETE VISUAL IDENTITIES for the LawFirm business type, contributed through the
 * EXISTING theme preset filters (the same mechanism Phase 19 used for `medical-modern`).
 *
 * =====================================================================================
 * WHAT MAKES THESE "DESIGNS" AND NOT "COLOUR PRESETS"
 * =====================================================================================
 * Each design below is authored across EVERY independent axis the Phase 21 token system
 * exposes. Two designs must feel different even in greyscale, because they differ on:
 *
 *   palette            - primary / accent / surfaces / text / border
 *   gradients          - brand, hero, CTA, decorative (a primary differentiator)
 *   typography         - heading family, heading scale, letter-spacing, weight
 *   DENSITY            - section rhythm, grid gap, card padding
 *   shape language     - border thickness, card/input/button radius, shadow depth
 *   component language - button padding/weight/shadow, badge treatment
 *   SHELL              - header/navigation/footer VARIANT (structural, not just colour)
 *   motion             - tempo, easing, reveal distance, hover lift
 *
 * The three directions are deliberately distinct in KIND, not degree:
 *
 *   1. MERIDIAN (authoritative)  - light, structured, editorial serif, sharp corners,
 *                                  hairline borders, split header, multi-column footer,
 *                                  calm motion. It reads like printed letterhead.
 *
 *   2. AURORA (modern)           - glassy and technology-forward, modern sans, generous
 *                                  radius, visible cyan-to-violet gradients, soft deep
 *                                  shadows, centered navigation, lively motion.
 *
 *   3. OBSIDIAN (dark luxury)    - DARK surfaces throughout, gold gradient accents, thin
 *                                  luminous borders, serif display headings, centered
 *                                  editorial header, minimal footer, slow cinematic motion.
 *                                  It is a different lighting condition entirely.
 *
 * Remove the colour from all three and they remain unmistakably different websites.
 *
 * =====================================================================================
 * ARCHITECTURAL RULES OBSERVED (Phase 21 §4, §5, §33, §40, §48, §64)
 * =====================================================================================
 *   - ONE Core Theme. No per-design theme, template, or HTML.
 *   - ONE token namespace. Every value is an EXISTING or Phase-21 `--bb-*` token.
 *     There is no `--lawfirm-*`, no `--design-*`, no second token system.
 *   - Presentation only. No CPT, no pack data, no business logic, no queries.
 *   - Registry-driven. Nothing here is hardcoded into Core or the Theme; the catalogue
 *     reads this contribution through `bb_theme_presets`.
 *   - The `shell` key is a SUGGESTION resolved by the Phase 15 shell resolver, never a
 *     hardcoded template path (see includes/Design/DesignShell.php).
 *   - Five slots are architecturally supported. Three are implemented; slots 4 and 5 can be
 *     added by appending to `designs()` with no change to Core, Theme or this class.
 */
class LawFirmDesigns {

	/**
	 * The business type these designs belong to.
	 *
	 * Used only to DECLARE compatibility in the registry; it is never branched on.
	 */
	public const BUSINESS_TYPE = 'law_firm';

	/**
	 * Register the catalogue with the theme preset registry.
	 */
	public function register(): void {

		add_filter( 'bb_theme_presets', array( $this, 'register_presets' ) );

		/*
		 * Refine only OUR OWN designs. Guarded per slug so a LawFirm design can never alter a
		 * Theme built-in, the Default design, or another pack's design.
		 */
		add_filter( 'bb_theme_preset_config', array( $this, 'refine_config' ), 10, 2 );
	}

	/**
	 * The catalogue: slug => metadata + tokens.
	 *
	 * Each entry carries BOTH the design tokens and its catalogue metadata in one flat array,
	 * matching the Theme's existing preset shape (tokens sit beside `label`).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function designs(): array {

		return array(

			/* =========================================================================
			 * 1 - MERIDIAN
			 * -------------------------------------------------------------------------
			 * DIRECTION: light, authoritative, structured, institutional.
			 * KIND:      editorial serif + architectural precision.
			 *
			 * Distinct because: serif headings with TIGHT tracking, near-zero radius,
			 * hairline borders, almost no elevation, a SPLIT header and a MULTI-COLUMN
			 * footer, calm motion. It reads as printed letterhead.
			 * ======================================================================= */
			'lawfirm-meridian' => array(
				'label'          => __( 'Meridian', 'business-builder' ),
				'description'    => __( 'Authoritative and structured. Serif headings, crisp edges and a formal, institutional tone.', 'business-builder' ),
				'business_types' => array( self::BUSINESS_TYPE ),
				'version'        => '2.0.0',

				/* -- Brand -- */
				'--bb-color-primary'          => '#1b3a5c',
				'--bb-color-primary-hover'    => '#12293f',
				'--bb-color-primary-light'    => '#4a7ba7',
				'--bb-color-primary-pale'     => '#e8eef4',
				'--bb-color-accent'           => '#a9863f',
				'--bb-color-accent-light'     => '#d4b978',
				'--bb-color-secondary'        => '#1b3a5c',
				'--bb-color-background'       => '#fdfdfb',
				'--bb-color-surface'          => '#ffffff',
				'--bb-color-surface-muted'    => '#f5f6f8',
				'--bb-color-text'             => '#2a3542',
				'--bb-color-text-muted'       => '#67737f',
				'--bb-color-heading'          => '#14212e',
				'--bb-color-border'           => '#dfe3e8',
				'--bb-color-border-strong'    => '#c5ccd4',
				'--bb-color-text-inverse'     => '#ffffff',

				/* -- Gradients: restrained, almost flat -- */
				'--bb-gradient-brand'         => 'linear-gradient(135deg, #1b3a5c, #12293f)',
				'--bb-gradient-hero'          => 'linear-gradient(180deg, #14212e, #1b3a5c)',
				'--bb-gradient-cta'           => 'linear-gradient(135deg, #1b3a5c, #2a4a6d)',
				'--bb-gradient-decorative'    => 'linear-gradient(90deg, #a9863f, #d4b978)',

				/* -- Typography: editorial serif, tight -- */
				'--bb-font-heading'           => 'Georgia, "Times New Roman", "Noto Serif", serif',
				'--bb-font-primary'           => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans Arabic", Helvetica, Arial, sans-serif',
				'--bb-heading-scale'          => '1',
				'--bb-heading-letter-spacing' => '-0.01',

				/* -- Density: deliberate, formal, not cramped -- */
				'--bb-section-padding-block'  => '88px',
				'--bb-grid-gap'               => '28px',
				'--bb-card-padding'           => '28px',

				/* -- Shape: architectural. Sharp, hairline, flat -- */
				'--bb-radius-sm'              => '0.0625rem',
				'--bb-radius-md'              => '0.0625rem',
				'--bb-radius-lg'              => '0.125rem',
				'--bb-radius-card'            => '2px',
				'--bb-radius-input'           => '2px',
				'--bb-button-radius'          => '2px',
				'--bb-border-thickness'       => '1px',
				'--bb-shadow-md'              => '0 1px 2px rgba(20, 33, 46, 0.06)',
				'--bb-shadow-card'            => '0 1px 2px rgba(20, 33, 46, 0.06)',
				'--bb-shadow-hover'           => '0 4px 14px rgba(20, 33, 46, 0.10)',

				/* -- Components: formal, wide, quiet -- */
				'--bb-button-padding-block'   => '14px',
				'--bb-button-padding-inline'  => '32px',
				'--bb-button-weight'          => '600',
				'--bb-button-shadow'          => 'none',
				'--bb-button-shadow-hover'    => 'none',
				'--bb-badge-bg'               => '#e8eef4',
				'--bb-badge-color'            => '#1b3a5c',
				'--bb-badge-border'           => '#c9d6e2',

				/* -- Shell: formal split header, structured footer -- */
				'--bb-header-bg'              => '#ffffff',
				'--bb-header-color'           => '#14212e',
				'--bb-header-height'          => '84px',
				'--bb-header-blur'            => '0px',
				'--bb-header-border-width'    => '1px',
				'--bb-nav-gap'                => '30px',
				'--bb-footer-bg'              => '#14212e',
				'--bb-footer-color'           => '#c3cdd8',
				'--bb-footer-padding-block'   => '56px',

				/* -- Motion: calm and minimal. Barely moves. -- */
				'--bb-motion-normal'          => '0.18s',
				'--bb-reveal-duration'        => '0.36s',
				'--bb-reveal-distance'        => '12px',
				'--bb-hover-lift'             => '2px',

				'--bb-container-width'        => '1200px',

				/*
				 * -- PHASE 22 AXES -----------------------------------------------------
				 * Background, grid, glass, the scrolled header state and the motion
				 * personality. These are the SAME `--bb-*` tokens the Studio controls, so
				 * a design authors them and a customer overrides them through one
				 * vocabulary. Every value here reaches the frontend through
				 * `design-sections.css`.
				 */

				/* Background: a barely-there editorial wash, no image, no overlay. */
				'--bb-bg-color'               => '#fdfdfb',
				'--bb-bg-gradient'            => 'none',
				'--bb-bg-overlay'             => 'none',
				'--bb-bg-overlay-opacity'     => '0',

				/* Grid: 3 columns, narrowing predictably on tablet and mobile. */
				'--bb-grid-columns'           => '3',
				'--bb-grid-columns-tablet'    => '2',
				'--bb-grid-columns-mobile'    => '1',
				'--bb-grid-row-gap'           => '28px',
				'--bb-card-min-width'         => '280px',
				'--bb-content-width'          => '66ch',

				/* Glass: OFF. A formal letterhead is not frosted. */
				'--bb-glass-blur'             => '0px',
				'--bb-glass-saturate'         => '100%',
				'--bb-glass-opacity'          => '1',

				/*
				 * Header scroll state: this design is the clearest expression of §21.
				 * The header floats TRANSPARENT over the dark hero, then becomes a solid
				 * white bar with dark navigation once the page moves.
				 */
				'--bb-header-initial-bg'      => '#14212e',
				'--bb-header-initial-nav'     => '#ffffff',
				'--bb-header-initial-transparency' => '0',
				'--bb-header-scrolled-bg'     => '#ffffff',
				'--bb-header-scrolled-nav'    => '#14212e',
				'--bb-header-scrolled-border' => '#dfe3e8',
				'--bb-header-scrolled-shadow' => '0 1px 3px rgba(20, 33, 46, 0.08)',
				'--bb-header-scroll-transition' => '0.22s',

				/* Footer detail. */
				'--bb-footer-heading-color'   => '#ffffff',
				'--bb-footer-link-color'      => '#c3cdd8',
				'--bb-footer-link-hover'      => '#d4b978',
				'--bb-footer-column-gap'      => '40px',

				/* Motion: restrained fade, no stagger drama. */
				'--bb-reveal-kind'            => 'fade-up',
				'--bb-reveal-stagger'         => '40ms',
				'--bb-hover-effect'           => 'lift',

				/* -- Shell variant preference: STRUCTURAL difference -- */
				'shell'                       => array(
					'header'     => 'split',
					'navigation' => 'default',
					'footer'     => 'columns',
				),
			),

			/* =========================================================================
			 * 2 - AURORA
			 * -------------------------------------------------------------------------
			 * DIRECTION: modern premium, technology-forward, sophisticated.
			 * KIND:      glass, gradients, generous geometry, lively depth.
			 *
			 * Distinct because: MODERN SANS headings, GENEROUS rounding, visible
			 * cyan-to-violet GRADIENTS in hero/CTA/decorative, SOFT DEEP shadows, a
			 * glassy translucent header, CENTERED navigation, and a noticeably more
			 * spacious, animated rhythm.
			 * ======================================================================= */
			'lawfirm-aurora' => array(
				'label'          => __( 'Aurora', 'business-builder' ),
				'description'    => __( 'Modern and technology-forward. Gradient accents, soft depth and spacious, glassy surfaces.', 'business-builder' ),
				'business_types' => array( self::BUSINESS_TYPE ),
				'version'        => '2.0.0',

				/* -- Brand -- */
				'--bb-color-primary'          => '#0f172a',
				'--bb-color-primary-hover'    => '#1e293b',
				'--bb-color-primary-light'    => '#64748b',
				'--bb-color-primary-pale'     => '#e0f2fe',
				'--bb-color-accent'           => '#06b6d4',
				'--bb-color-accent-light'     => '#67e8f9',
				'--bb-color-secondary'        => '#8b5cf6',
				'--bb-color-background'       => '#f8fafc',
				'--bb-color-surface'          => '#ffffff',
				'--bb-color-surface-muted'    => '#f1f5f9',
				'--bb-color-text'             => '#1e293b',
				'--bb-color-text-muted'       => '#64748b',
				'--bb-color-heading'          => '#0f172a',
				'--bb-color-border'           => '#e2e8f0',
				'--bb-color-border-strong'    => '#cbd5e1',
				'--bb-color-text-inverse'     => '#ffffff',

				/* -- Gradients: the signature -- */
				'--bb-gradient-brand'         => 'linear-gradient(135deg, #06b6d4, #8b5cf6)',
				'--bb-gradient-hero'          => 'linear-gradient(160deg, #0f172a, #312e81)',
				'--bb-gradient-cta'           => 'linear-gradient(135deg, #06b6d4, #8b5cf6)',
				'--bb-gradient-decorative'    => 'linear-gradient(90deg, #06b6d4, #8b5cf6)',
				'--bb-gradient-surface'       => 'linear-gradient(180deg, #ffffff, #f1f5f9)',

				/* -- Typography: modern geometric sans, airy -- */
				'--bb-font-heading'           => '"Manrope", "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans Arabic", sans-serif',
				'--bb-font-primary'           => '"Manrope", "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans Arabic", sans-serif',
				'--bb-heading-scale'          => '1.12',
				'--bb-heading-letter-spacing' => '0',

				/* -- Density: spacious and airy -- */
				'--bb-section-padding-block'  => '112px',
				'--bb-grid-gap'               => '32px',
				'--bb-card-padding'           => '32px',

				/* -- Shape: rounded, soft, contemporary -- */
				'--bb-radius-sm'              => '0.5rem',
				'--bb-radius-md'              => '0.75rem',
				'--bb-radius-lg'              => '1.25rem',
				'--bb-radius-card'            => '20px',
				'--bb-radius-input'           => '12px',
				'--bb-button-radius'          => '999px',
				'--bb-border-thickness'       => '1px',
				'--bb-shadow-md'              => '0 10px 30px rgba(15, 23, 42, 0.08)',
				'--bb-shadow-card'            => '0 12px 34px rgba(15, 23, 42, 0.09)',
				'--bb-shadow-hover'           => '0 22px 52px rgba(15, 23, 42, 0.16)',

				/* -- Components: pill buttons, confident weight -- */
				'--bb-button-padding-block'   => '15px',
				'--bb-button-padding-inline'  => '34px',
				'--bb-button-weight'          => '600',
				'--bb-button-shadow'          => '0 6px 18px rgba(6, 182, 212, 0.28)',
				'--bb-button-shadow-hover'    => '0 12px 28px rgba(139, 92, 246, 0.38)',
				'--bb-badge-bg'               => '#e0f2fe',
				'--bb-badge-color'            => '#0e7490',
				'--bb-badge-border'           => '#bae6fd',

				/* -- Shell: glass header, centered nav, columns footer -- */
				'--bb-header-bg'              => '#ffffff',
				'--bb-header-color'           => '#0f172a',
				'--bb-header-height'          => '78px',
				'--bb-header-blur'            => '14px',
				'--bb-header-saturate'        => '180%',
				'--bb-header-border-width'    => '1px',
				'--bb-nav-gap'                => '26px',
				'--bb-footer-bg'              => '#0f172a',
				'--bb-footer-color'           => '#94a3b8',
				'--bb-footer-padding-block'   => '64px',

				/* -- Motion: lively, larger travel -- */
				'--bb-motion-normal'          => '0.28s',
				'--bb-reveal-duration'        => '0.52s',
				'--bb-reveal-distance'        => '26px',
				'--bb-hover-lift'             => '6px',

				'--bb-container-width'        => '1240px',

				/*
				 * -- PHASE 22 AXES -----------------------------------------------------
				 * Aurora is the GLASS design: a mesh background, a glass header, four
				 * columns, and a scale-in reveal. It differs from Meridian on every one of
				 * the new axes, not only on colour.
				 */
				'--bb-bg-color'               => '#f8fafc',
				'--bb-bg-gradient'            => 'radial-gradient(at 12% 18%, rgba(99, 102, 241, 0.28) 0px, transparent 55%), radial-gradient(at 88% 12%, rgba(6, 182, 212, 0.24) 0px, transparent 55%), radial-gradient(at 70% 88%, rgba(139, 92, 246, 0.22) 0px, transparent 55%)',
				'--bb-bg-overlay'             => 'none',
				'--bb-bg-overlay-opacity'     => '0',

				'--bb-grid-columns'           => '4',
				'--bb-grid-columns-tablet'    => '2',
				'--bb-grid-columns-mobile'    => '1',
				'--bb-grid-row-gap'           => '24px',
				'--bb-card-min-width'         => '240px',
				'--bb-content-width'          => '72ch',

				/* Glass ON: a medium frosted surface for the header and any glass card. */
				'--bb-glass-blur'             => '16px',
				'--bb-glass-saturate'         => '150%',
				'--bb-glass-opacity'          => '0.82',
				'--bb-glass-border-opacity'   => '0.5',

				/*
				 * Header scroll state: this design OPTS IN to the glass treatment. It is
				 * translucent over the hero and becomes a solid frosted bar once scrolled.
				 */
				'--bb-header-initial-bg'      => '#0f172a',
				'--bb-header-initial-nav'     => '#e2e8f0',
				'--bb-header-initial-transparency' => '0.35',
				'--bb-header-scrolled-bg'     => '#ffffff',
				'--bb-header-scrolled-nav'    => '#0f172a',
				'--bb-header-scrolled-border' => '#e2e8f0',
				'--bb-header-scrolled-shadow' => '0 4px 20px rgba(15, 23, 42, 0.08)',
				'--bb-header-scroll-transition' => '0.3s',
				'--bb-header-blur'            => '16px',
				'--bb-header-saturate'        => '150%',

				'--bb-footer-heading-color'   => '#f8fafc',
				'--bb-footer-link-color'      => '#cbd5e1',
				'--bb-footer-link-hover'      => '#67e8f9',
				'--bb-footer-column-gap'      => '32px',

				/* Motion: the most expressive of the three - scale in, image zoom on hover. */
				'--bb-reveal-kind'            => 'scale',
				'--bb-reveal-stagger'         => '90ms',
				'--bb-hover-effect'           => 'zoom',

				/* -- Shell variant preference -- */
				'shell'                       => array(
					'header'     => 'default',
					'navigation' => 'centered',
					'footer'     => 'columns',
				),
			),

			/* =========================================================================
			 * 3 - OBSIDIAN
			 * -------------------------------------------------------------------------
			 * DIRECTION: dark luxury, cinematic, executive, boutique.
			 * KIND:      dark surfaces, gold light, thin luminous edges.
			 *
			 * Distinct because: it is DARK where the others are light - surfaces,
			 * background and footer are all near-black. GOLD gradient accents, very
			 * THIN borders, serif display headings with WIDE tracking, a CENTERED
			 * editorial header and a MINIMAL footer, and slow deliberate motion.
			 * It is a different lighting condition, not a different palette.
			 * ======================================================================= */
			'lawfirm-obsidian' => array(
				'label'          => __( 'Obsidian', 'business-builder' ),
				'description'    => __( 'Dark luxury with gold accents. Cinematic, executive and unmistakably high-end.', 'business-builder' ),
				'business_types' => array( self::BUSINESS_TYPE ),
				'version'        => '2.0.0',

				/* -- Brand: dark surfaces, gold light -- */
				'--bb-color-primary'          => '#d4af37',
				'--bb-color-primary-hover'    => '#b8962c',
				'--bb-color-primary-light'    => '#f5e6a8',
				'--bb-color-primary-pale'     => '#2a2415',
				'--bb-color-accent'           => '#c084fc',
				'--bb-color-accent-light'     => '#d8b4fe',
				'--bb-color-secondary'        => '#7c3aed',
				'--bb-color-background'       => '#080b12',
				'--bb-color-surface'          => '#111827',
				'--bb-color-surface-muted'    => '#0b0f19',
				'--bb-color-text'             => '#cbd5e1',
				'--bb-color-text-muted'       => '#94a3b8',
				'--bb-color-heading'          => '#f8fafc',
				'--bb-color-border'           => '#273244',
				'--bb-color-border-strong'    => '#3b4a61',
				'--bb-color-text-inverse'     => '#0b0f19',

				/* -- Gradients: gold light + deep violet -- */
				'--bb-gradient-brand'         => 'linear-gradient(135deg, #d4af37, #f5e6a8)',
				'--bb-gradient-hero'          => 'linear-gradient(160deg, #080b12, #1a1409)',
				'--bb-gradient-cta'           => 'linear-gradient(135deg, #7c3aed, #c084fc)',
				'--bb-gradient-decorative'    => 'linear-gradient(90deg, #d4af37, #f5e6a8)',
				'--bb-gradient-surface'       => 'linear-gradient(180deg, #111827, #0b0f19)',

				/* -- Typography: serif display, wide tracking, smaller scale -- */
				'--bb-font-heading'           => '"Cormorant Garamond", Georgia, "Times New Roman", "Noto Naskh Arabic", serif',
				'--bb-font-primary'           => '"Manrope", "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans Arabic", sans-serif',
				'--bb-heading-scale'          => '1.04',
				'--bb-heading-letter-spacing' => '0.01',

				/* -- Density: cinematic, generous negative space -- */
				'--bb-section-padding-block'  => '120px',
				'--bb-grid-gap'               => '30px',
				'--bb-card-padding'           => '34px',

				/* -- Shape: precise, thin-edged, barely rounded -- */
				'--bb-radius-sm'              => '0.125rem',
				'--bb-radius-md'              => '0.25rem',
				'--bb-radius-lg'              => '0.375rem',
				'--bb-radius-card'            => '6px',
				'--bb-radius-input'           => '4px',
				'--bb-button-radius'          => '4px',
				'--bb-border-thickness'       => '1px',
				'--bb-shadow-md'              => '0 8px 30px rgba(0, 0, 0, 0.55)',
				'--bb-shadow-card'            => '0 10px 40px rgba(0, 0, 0, 0.60)',
				'--bb-shadow-hover'           => '0 18px 60px rgba(212, 175, 55, 0.18)',

				/* -- Components: solid gold, ghost secondary, soft glow -- */
				'--bb-button-padding-block'   => '14px',
				'--bb-button-padding-inline'  => '30px',
				'--bb-button-weight'          => '600',
				'--bb-button-shadow'          => 'none',
				'--bb-button-shadow-hover'    => '0 8px 26px rgba(212, 175, 55, 0.35)',
				'--bb-badge-bg'               => '#2a2415',
				'--bb-badge-color'            => '#f5e6a8',
				'--bb-badge-border'           => '#4a3f1e',

				/* -- Shell: dark glass, centered editorial, minimal footer -- */
				'--bb-header-bg'              => '#0b0f19',
				'--bb-header-color'           => '#f8fafc',
				'--bb-header-height'          => '88px',
				'--bb-header-blur'            => '10px',
				'--bb-header-saturate'        => '140%',
				'--bb-header-border-width'    => '1px',
				'--bb-nav-gap'                => '34px',
				'--bb-footer-bg'              => '#080b12',
				'--bb-footer-color'           => '#94a3b8',
				'--bb-footer-padding-block'   => '72px',

				/* -- Motion: slow, cinematic, minimal travel -- */
				'--bb-motion-normal'          => '0.36s',
				'--bb-reveal-duration'        => '0.70s',
				'--bb-reveal-distance'        => '18px',
				'--bb-hover-lift'             => '3px',

				'--bb-container-width'        => '1280px',

				/*
				 * -- PHASE 22 AXES -----------------------------------------------------
				 * Obsidian is the DARK, cinematic design: a dark radial background, no
				 * glass, three wide columns, a strong glow on hover and a slow blur
				 * reveal. It shares no Phase 22 axis value with either sibling.
				 */
				'--bb-bg-color'               => '#080b12',
				'--bb-bg-gradient'            => 'radial-gradient(circle at 50% 0%, #1f2937 0%, #0b0f19 70%)',
				'--bb-bg-overlay'             => 'none',
				'--bb-bg-overlay-opacity'     => '0',

				'--bb-grid-columns'           => '3',
				'--bb-grid-columns-tablet'    => '2',
				'--bb-grid-columns-mobile'    => '1',
				'--bb-grid-row-gap'           => '36px',
				'--bb-card-min-width'         => '320px',
				'--bb-content-width'          => '62ch',

				/* Glass OFF: the surface is already dark and luminous; frosting would muddy it. */
				'--bb-glass-blur'             => '0px',
				'--bb-glass-saturate'         => '100%',
				'--bb-glass-opacity'          => '1',

				/*
				 * Header scroll state: dark on dark, but the initial state is fully
				 * transparent so the hero reads as a single cinematic frame; scrolling
				 * lifts a solid dark bar with a hairline gold edge.
				 */
				'--bb-header-initial-bg'      => '#080b12',
				'--bb-header-initial-nav'     => '#f5e6a8',
				'--bb-header-initial-transparency' => '0',
				'--bb-header-scrolled-bg'     => '#0b0f19',
				'--bb-header-scrolled-nav'    => '#f5e6a8',
				'--bb-header-scrolled-border' => '#2a2415',
				'--bb-header-scrolled-shadow' => '0 8px 30px rgba(0, 0, 0, 0.5)',
				'--bb-header-scroll-transition' => '0.4s',

				'--bb-footer-heading-color'   => '#f5e6a8',
				'--bb-footer-link-color'      => '#94a3b8',
				'--bb-footer-link-hover'      => '#d4af37',
				'--bb-footer-column-gap'      => '48px',

				/* Motion: the slowest and most cinematic - blur reveal, glow hover. */
				'--bb-reveal-kind'            => 'blur',
				'--bb-reveal-stagger'         => '120ms',
				'--bb-hover-effect'           => 'glow',

				/* -- Shell variant preference -- */
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
	 * Additive refinement of this pack's own designs only.
	 *
	 * Mirrors the Theme's contract: never clobber a value the Theme (or a user override) has
	 * already resolved.
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