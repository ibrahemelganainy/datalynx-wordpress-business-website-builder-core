<?php

namespace BusinessBuilderCore\Packs\Medical;

use BusinessBuilderCore\Builder\SectionRegistry;
use BusinessBuilderCore\Packs\Medical\PostTypes\Doctor;
use BusinessBuilderCore\Packs\Medical\PostTypes\MedicalService;
use BusinessBuilderCore\Packs\Medical\Taxonomies\Specialty;
use BusinessBuilderCore\Packs\Medical\Sections\MedicalSections;
use BusinessBuilderCore\Packs\Medical\Design\MedicalPresets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Medical Pack — the second business pack (Phase 19 platform proof).
 *
 * This pack exists to prove that a genuinely different business type can be added
 * as an INDEPENDENT pack without modifying the Core Theme and without introducing
 * a second design system. It is a website pack, NOT a medical ERP (Phase 19 §24):
 * there are no patients, records, prescriptions, insurance, billing, scheduling
 * engine or portal.
 *
 * Everything below is done through EXISTING extension points only
 * (docs/phase-19-medical-pack-audit.md §16):
 *
 *   bb_register_packs            → the pack registers itself (never by editing core)
 *   SectionRegistry::register()  → the ONE shared section registry
 *   bb_render_section            → the pack claims its own section types
 *   bb_register_section_variants → the pack ships its own section layouts
 *   bb_component_roots           → the pack ships its own domain cards
 *   bb_theme_presets             → the pack contributes a design style
 *
 * No Core file is aware of Medical, and the Theme never references it.
 */
class MedicalPack {

	/**
	 * The business-type slug this pack serves.
	 *
	 * Must match the slug declared in Core's BusinessType registry — the same
	 * slug the admin selects on the Business Builder settings screen.
	 */
	public const BUSINESS_TYPE = 'medical';

	protected Doctor $doctor;

	protected MedicalService $medical_service;

	protected Specialty $specialty;

	protected MedicalSections $sections;

	protected MedicalPresets $presets;

	/**
	 * Constructor.
	 *
	 * Dependencies are resolved by the Core DI container (PackManager::boot()
	 * → Container::make()), exactly like the LawFirm pack. No manual wiring.
	 *
	 * @param SectionRegistry $section_registry Core section registry.
	 */
	public function __construct( SectionRegistry $section_registry ) {

		$this->doctor = new Doctor();

		$this->medical_service = new MedicalService();

		$this->specialty = new Specialty();

		$this->sections = new MedicalSections( $section_registry );

		$this->presets = new MedicalPresets();
	}

	/**
	 * Register the pack with the Core pack registry.
	 *
	 * Hooked on `bb_register_packs`. Done this way — rather than by editing
	 * ServiceProvider — so the Core never hardcodes Medical
	 * (Phase 19 §5). Core fires the action; this pack self-registers.
	 */
	public static function register_pack( $pack_manager ): void {

		if ( ! is_object( $pack_manager ) || ! method_exists( $pack_manager, 'register' ) ) {
			return;
		}

		$pack_manager->register(
			self::BUSINESS_TYPE,
			self::class
		);
	}

	/**
	 * Register the pack's domain functionality.
	 *
	 * Called by PackManager::boot() only when this site's business type is
	 * `medical`, so nothing here runs on a LawFirm or non-BB site.
	 */
	public function register(): void {

		/* Domain entities + taxonomy (pack-owned). */
		$this->doctor->register();

		$this->medical_service->register();

		$this->specialty->register();

		/* Domain cards (theme component API, pack-owned root). */
		add_filter( 'bb_component_roots', array( $this->sections, 'register_component_root' ) );

		/* Domain section layouts (core SectionVariants registry). */
		add_action( 'bb_register_section_variants', array( $this->sections, 'register_section_variants' ) );

		/* Claim this pack's section types on the generic render hook. */
		add_filter( 'bb_render_section', array( $this->sections, 'render_section' ), 10, 5 );

		/* Domain sections in the ONE shared section registry. */
		$this->sections->register();

		    /* Domain design style (theme preset registry, existing --bb-* tokens). */
		    $this->presets->register();

		    /*
		     * Phase 21: the full Medical design catalogue (five prepared designs). Registered
		     * through the SAME theme preset filter as the Phase 19 proof preset, so the Core and
		     * the Theme remain business-agnostic.
		     */
		    if ( class_exists( \BusinessBuilderCore\Packs\Medical\Design\MedicalDesigns::class ) ) {
		        ( new \BusinessBuilderCore\Packs\Medical\Design\MedicalDesigns() )->register();
		    }
		}

	/**
	 * Boot runtime functionality.
	 */
	public function boot(): void {

		/*
		 * Self-healing rewrite flush, mirroring the LawFirm pack.
		 *
		 * The pack's post types expose pretty permalinks (/doctors/{slug}/). If
		 * the stored rewrite rules were generated before these were registered,
		 * singles fall back to "?p=ID". We inspect the STORED rules (the source of
		 * truth) and flush ONCE when the pack's query vars are missing, tracked by
		 * a site-specific version option so it never runs on every request.
		 */
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 20 );
	}

	/**
	 * Flush rewrite rules once if this pack's permalinks are missing.
	 */
	public function maybe_flush_rewrite_rules(): void {

		$version = '1';

		$rules = get_option( 'rewrite_rules' );

		$bb_query_vars = array( 'bb_doctor', 'bb_medical_service', 'bb_specialty' );

		$bb_has_rules = false;

		if ( is_array( $rules ) ) {
			foreach ( $rules as $rule_target ) {
				foreach ( $bb_query_vars as $var ) {
					if ( false !== strpos( (string) $rule_target, $var ) ) {
						$bb_has_rules = true;
						break 2;
					}
				}
			}
		}

		if ( $bb_has_rules ) {
			if ( get_option( 'bb_medical_rewrite_version' ) !== $version ) {
				update_option( 'bb_medical_rewrite_version', $version );
			}

			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		flush_rewrite_rules( false );

		update_option( 'bb_medical_rewrite_version', $version );
	}
}