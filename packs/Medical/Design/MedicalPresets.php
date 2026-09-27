<?php

namespace BusinessBuilderCore\Packs\Medical\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Medical design style (proof preset).
 *
 * Phase 19 §14-§16. The Theme owns the design framework; a Pack MAY contribute a
 * preset through the existing Theme filters. That is exactly what this class
 * does — and nothing more:
 *
 *   - it uses the EXISTING `--bb-*` token namespace (no `--medical-*`),
 *   - it uses the EXISTING preset registry (`bb_theme_presets`),
 *   - it uses the EXISTING resolver (`bb_theme_preset_config` / `bb_theme_preset_css`),
 *   - it creates NO second token system, NO second resolver and NO Theme dependency
 *     on Medical (the Theme never references this class — the filter is one-way).
 *
 * Because the Theme merges `bb_theme_customization_overrides()` AFTER the preset,
 * an administrator's customization still overrides every value below. That
 * layering is asserted in tests/runtime-phase19-medical-pack.php.
 *
 * The preset is deliberately a *proof*: it overrides only a small, meaningful set
 * of tokens. It is NOT five complete designs (Phase 19 §15 forbids inventing five
 * designs to satisfy a number).
 */
class MedicalPresets {

	/**
	 * The preset slug contributed by this pack.
	 */
	public const PRESET = 'medical-modern';

	/**
	 * Register hooks.
	 */
	public function register(): void {

		add_filter( 'bb_theme_presets', array( $this, 'add_preset' ) );

		/*
		 * Only refine OUR OWN preset; every other preset is returned untouched so
		 * the pack can never alter the Theme's built-in styles (Phase 19 §26).
		 */
		add_filter( 'bb_theme_preset_config', array( $this, 'refine_config' ), 10, 2 );
	}

	/**
	 * Contribute the Medical preset to the Theme preset registry.
	 *
	 * Only tokens that already exist in the Theme's `--bb-*` namespace are used.
	 * See themes/business-builder/inc/preset-resolver.php and design-schema.php.
	 *
	 * @param array $presets Existing presets.
	 * @return array
	 */
	public function add_preset( $presets ): array {

		$presets = is_array( $presets ) ? $presets : array();

		/*
		 * The preset shape is FLAT — tokens sit at the top level beside `label`,
		 * exactly like the Theme's built-in `default` / `modern` / `luxury` presets
		 * (themes/business-builder/inc/preset-resolver.php). No nested namespace.
		 */
		$presets[ self::PRESET ] = array( 'label' => __( 'Medical Modern', 'business-builder' ) ) + $this->tokens();

		return $presets;
	}

	/**
	 * The Medical preset's token values.
	 *
	 * Every key is an EXISTING Theme token. No new namespace is introduced.
	 *
	 * @return array<string, string>
	 */
	public function tokens(): array {

		return array(
			/* Clinical teal primary, calm cyan accent. */
			'--bb-color-primary'       => '#0f766e',
			'--bb-color-primary-hover' => '#115e59',
			'--bb-color-accent'        => '#0891b2',

			/* Calm surfaces and text. */
			'--bb-color-background'    => '#f8fafc',
			'--bb-color-surface'       => '#ffffff',
			'--bb-color-surface-muted' => '#f1f5f9',
			'--bb-color-text'          => '#0f172a',
			'--bb-color-border'        => '#e2e8f0',

			/* Softer, rounder geometry than the corporate default. */
			'--bb-radius-md'           => '0.75rem',
			'--bb-radius-lg'           => '1rem',
			'--bb-button-radius'       => '999px',

			/* Clinical header/footer shell. */
			'--bb-header-bg'           => '#ffffff',
			'--bb-header-color'        => '#0f172a',
			'--bb-footer-bg'           => '#0f172a',
			'--bb-footer-color'        => '#e2e8f0',
		);
	}

	/**
	 * Refine the resolved preset config for THIS pack's preset only.
	 *
	 * Guarded so it only fires for this pack's own active preset — a Medical pack
	 * must never change the tokens of a Theme built-in or another pack's preset.
	 *
	 * @param array  $config Resolved preset config (tokens => values).
	 * @param string $slug   Active preset slug.
	 * @return array
	 */
	public function refine_config( $config, $slug = '' ): array {

		$config = is_array( $config ) ? $config : array();

		if ( self::PRESET !== (string) $slug ) {
			return $config;
		}

		foreach ( $this->tokens() as $token => $value ) {

			/*
			 * Additive only: never clobber a token the Theme (or a user override)
			 * already resolved. The Theme applies user customization BEFORE this
			 * filter, so a user value always wins.
			 */
			if ( ! isset( $config[ $token ] ) ) {
				$config[ $token ] = $value;
			}
		}

		return $config;
	}
}