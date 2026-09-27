<?php

namespace BusinessBuilderCore\Design;

use BusinessBuilderCore\Settings\BusinessType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Design Catalogue (Phase 21 §6-§9, §36-§37) — GENERIC and registry-driven.
 *
 * This class is the single place the platform asks: "which Designs exist, and which of them
 * are valid for THIS site?" It deliberately owns no designs of its own:
 *
 *   - the THEME owns the preset registry and the token vocabulary;
 *   - the PACKS contribute their designs through `bb_theme_presets`;
 *   - this class only DISCOVERS, normalizes and FILTERS them.
 *
 * CONSEQUENCES (Phase 21 §28, §34):
 *   - There is no `if ( 'law_firm' )` / `if ( 'medical' )` anywhere in this file. A new pack's
 *     designs appear automatically once the pack registers them.
 *   - Design compatibility comes from each design's own `business_types` metadata, never from
 *     a hardcoded map.
 *   - No new storage is introduced: the ACTIVE design remains the theme mod `bb_theme_preset`.
 *
 * RESOLUTION REALITY (documented, not hidden): `bb_theme_presets()` reflects the packs booted
 * in the CURRENT request context. On a LawFirm site the LawFirm filter runs; on a Medical site,
 * the Medical one. The catalogue therefore also filters by business type so a design can never
 * be offered for the wrong site, and an explicit `business_type` argument lets callers (e.g.
 * the network layer) ask about another site's context.
 */
class DesignCatalogue {

	/**
	 * The theme mod holding the ACTIVE design slug (NOT a new key — Phase 21 §35).
	 */
	public const ACTIVE_MOD = 'bb_theme_preset';

	/**
	 * The design that is always valid and always available (§9).
	 */
	public const DEFAULT_DESIGN = 'default';

	/**
	 * The canonical Theme's stylesheet slug.
	 *
	 * A Business Builder business type and a Business Builder design are only
	 * meaningful on a site running THIS theme. The stylesheet is the authority,
	 * because it is what actually decides whether the Theme's design pipeline runs
	 * (§32 isolation).
	 */
	public const CANONICAL_THEME = 'business-builder';

	/**
	 * All registered designs, normalized.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {

		$presets = function_exists( 'bb_theme_presets' ) ? bb_theme_presets() : array();

		if ( ! is_array( $presets ) ) {
			return array();
		}

		$out = array();

		foreach ( $presets as $slug => $preset ) {

			$slug = sanitize_key( (string) $slug );

			if ( '' === $slug ) {
				continue;
			}

			$out[ $slug ] = $this->normalize( $slug, is_array( $preset ) ? $preset : array() );
		}

		/* The Default design must always be present, even if a filter removed it reverse. */
		if ( ! isset( $out[ self::DEFAULT_DESIGN ] ) ) {
			$out[ self::DEFAULT_DESIGN ] = $this->normalize( self::DEFAULT_DESIGN, array( 'label' => __( 'Default', 'business-builder' ) ) );
		}

		return $out;
	}

	/**
	 * Flatten one registry entry into a stable catalogue shape.
	 *
	 * @param string $slug   Design slug.
	 * @param array  $preset Registry entry (tokens + metadata mixed, as the Theme stores it).
	 * @return array<string, mixed>
	 */
	protected function normalize( string $slug, array $preset ): array {

		$tokens = array();

		foreach ( $preset as $key => $value ) {

			if ( 0 === strpos( (string) $key, '--bb-' ) ) {
				$tokens[ (string) $key ] = (string) $value;
			}
		}

		$business_types = array();

		if ( isset( $preset['business_types'] ) ) {

			foreach ( (array) $preset['business_types'] as $type ) {
				$type = sanitize_key( (string) $type );
				if ( '' !== $type ) {
					$business_types[] = $type;
				}
			}
		}

		/*
		 * A design's optional SHELL preference (Phase 21 §32). Sanitized here so the catalogue is
		 * the single validated source; `DesignShell` resolves it through the Theme's own
		 * whitelist resolver. A design that declares nothing simply gets no entry.
		 */
		$shell = array();

		if ( isset( $preset['shell'] ) && is_array( $preset['shell'] ) ) {

			foreach ( $preset['shell'] as $part => $variant ) {

				$part    = sanitize_key( (string) $part );
				$variant = sanitize_key( (string) $variant );

				if ( '' !== $part && '' !== $variant ) {
					$shell[ $part ] = $variant;
				}
			}
		}

		/*
		 * Catalogue presentation metadata (Phase 21 §16-§19, §48-§49): an accent palette strip and
		 * a typography preview need the design's key colours and heading font. These are derived
		 * from the design's OWN tokens, so a preview can never drift from the real design.
		 */
		return array(
			'slug'           => $slug,
			'label'          => isset( $preset['label'] ) ? (string) $preset['label'] : $slug,
			'description'    => isset( $preset['description'] ) ? (string) $preset['description'] : '',
			'business_types' => $business_types,
			'version'        => isset( $preset['version'] ) ? (string) $preset['version'] : '',
			'is_default'     => self::DEFAULT_DESIGN === $slug,
			/*
			 * `generic` marks a design as deliberately business-agnostic. It is set by the design's
			 * own author (the Theme marks its built-ins, a pack would mark a genuinely universal
			 * design), so the catalogue never has to guess - and an un-annotated design stays
			 * isolated instead of being silently promoted to global.
			 */
			'is_generic'     => ! empty( $preset['generic'] ),
			'tokens'         => $tokens,
			'shell'          => $shell,
			'palette'        => $this->palette_of( $tokens ),
		);
	}

	/**
	 * The design's visible palette, in semantic order, for swatches and previews.
	 *
	 * Derived from the design's OWN tokens so a preview always matches the live design (§58).
	 *
	 * @param array<string, string> $tokens Design tokens.
	 * @return array<int, array{token:string,label:string,value:string}>
	 */
	public function palette_of( array $tokens ): array {

		$order = array(
			'--bb-color-primary'       => __( 'Primary', 'business-builder' ),
			'--bb-color-accent'        => __( 'Accent', 'business-builder' ),
			'--bb-color-background'    => __( 'Background', 'business-builder' ),
			'--bb-color-surface'       => __( 'Surface', 'business-builder' ),
			'--bb-color-text'          => __( 'Text', 'business-builder' ),
			'--bb-color-border'        => __( 'Border', 'business-builder' ),
		);

		$out = array();

		foreach ( $order as $token => $label ) {

			if ( empty( $tokens[ $token ] ) ) {
				continue;
			}

			$out[] = array(
				'token' => $token,
				'label' => $label,
				'value' => (string) $tokens[ $token ],
			);
		}

		return $out;
	}

	/**
	 * Whether a design is compatible with a business type (§5, §37, §32).
	 *
	 * THE RULE, AND WHY IT WAS CHANGED IN PHASE 22
	 * -------------------------------------------
	 * Phase 21 treated "a design that declares NO business types" as GLOBAL and
	 * offered it for every business type. That produced exactly the leak §5 of
	 * this phase has to eliminate:
	 *
	 *   - the Theme's own built-ins (`default`, `modern`, `luxury`) declare no
	 *     types, so they were offered on LawFirm, Medical AND on a plain Astra
	 *     site;
	 *   - a design whose author simply FORGOT the metadata silently became a
	 *     global design instead of being caught.
	 *
	 * Phase 22 inverts the default so isolation is the SAFE case:
	 *
	 *   - the Default design is available everywhere (it is the guaranteed
	 *     fallback and the reset target, §9);
	 *   - a design that DECLARES types is offered only for those types;
	 *   - a design that declares NOTHING is offered only when no business type is
	 *     active at all (a plain site that has not chosen a business type yet).
	 *     It is never offered for LawFirm or Medical, so an un-annotated design
	 *     can no longer cross a business-type boundary.
	 *
	 * A pack therefore MUST declare its `business_types`, which is what the
	 * LawFirm and Medical packs already do and what the Theme's built-ins now get
	 * annotated with by `DesignSchema`/the catalogue's own default annotation.
	 *
	 * @param array<string, mixed> $design        Normalized design.
	 * @param string               $business_type Business type slug.
	 * @return bool
	 */
	public function is_compatible( array $design, string $business_type ): bool {

		/* The Default design is the universal fallback and always fits. */
		if ( ! empty( $design['is_default'] ) ) {
			return true;
		}

		$business_type = sanitize_key( $business_type );

		/*
		 * A design whose own author marked it business-agnostic (the Theme's built-ins) is
		 * available everywhere. This is an EXPLICIT declaration, not a fallback for a missing one,
		 * so an un-annotated pack design can never inherit the exemption.
		 */
		if ( ! empty( $design['is_generic'] ) ) {
			return true;
		}

		/* No business type is active: only an un-annotated (generic) design fits. */
		if ( '' === $business_type ) {
			return empty( $design['business_types'] );
		}

		/*
		 * A business type IS active. Only a design that explicitly declares it may
		 * be offered - an un-annotated design is NOT silently promoted to global,
		 * because that is the leak this rule exists to prevent.
		 */
		if ( empty( $design['business_types'] ) ) {
			return false;
		}

		return in_array( $business_type, $design['business_types'], true );
	}

	/**
	 * The designs available for a business type, default first (§37).
	 *
	 * @param string $business_type Business type slug ('' = only business-agnostic designs).
	 * @return array<string, array<string, mixed>>
	 */
	public function for_business_type( string $business_type ): array {

		$business_type = sanitize_key( $business_type );

		$out = array();

		/*
		 * EVERY design is put through the SAME compatibility test, including when no
		 * business type is active.
		 *
		 * MEASURED FAILURE (Phase 22 test suite, multisite group)
		 * -----------------------------------------------------
		 * This loop used to short-circuit on `'' === $business_type` and return the
		 * ENTIRE registry. On a non-Business-Builder site (Astra, site 1) that meant
		 * the catalogue OFFERED every pack design:
		 *
		 *     non-builder site 1 (astra) is offered no business-specific design
		 *       -> lawfirm-aurora, medical-clarity, lawfirm-meridian, …
		 *
		 * The designs were inert on Astra (the Theme never emits them), but the
		 * catalogue is a public surface and a non-builder site has no business type
		 * to justify showing one business's designs. `is_compatible()` already
		 * answers "an un-annotated/generic design only" for the empty type, so the
		 * short-circuit was not merely redundant - it was the leak.
		 *
		 * Removing it makes the catalogue's answer and the resolver's answer the
		 * same question, asked the same way, in every context.
		 */
		foreach ( $this->all() as $slug => $design ) {

			if ( $this->is_compatible( $design, $business_type ) ) {
				$out[ $slug ] = $design;
			}
		}

		/* Present the Default design first so the fallback is always obvious. */
		uasort(
			$out,
			function ( $a, $b ) {

				$a_default = ! empty( $a['is_default'] );
				$b_default = ! empty( $b['is_default'] );

				if ( $a_default === $b_default ) {
					return strcasecmp( (string) $a['label'], (string) $b['label'] );
				}

				return $a_default ? -1 : 1;
			}
		);

		return $out;
	}

	/**
	 * Resolve the design active on a site, with a guaranteed safe fallback (§9, §5).
	 *
	 * Never returns a slug the site is not entitled to use. Two failure modes are handled:
	 *
	 *   1. the stored slug is UNKNOWN (a design was removed, a pack is unavailable, the data was
	 *      hand-edited) → the Default design;
	 *   2. the stored slug is known but belongs to ANOTHER BUSINESS TYPE → the Default design.
	 *
	 * MEASURED FAILURE (Phase 22 audit, tests/_phase22-probe-sites.php)
	 * -----------------------------------------------------------------
	 * Case 2 was not handled in Phase 21. The LawFirm site was measured with
	 * `bb_theme_preset = medical-modern` — a MEDICAL design active on a LAW FIRM site:
	 *
	 *     SITE 2  lawfirm.builder.test   business type = law_firm
	 *       active design = medical-modern
	 *
	 * Phase 21's `active()` only asked "is this slug registered somewhere?", and on the LawFirm
	 * site the Medical pack IS booted, so the slug resolved and the wrong design rendered. The
	 * catalogue FILTERED the picker correctly but the RESOLVER did not filter, so a stale or
	 * hand-edited value could leak one business type's design into another site.
	 *
	 * The fix asks the same compatibility question the picker asks, so the two can never
	 * disagree. Nothing is written: the stored value is left untouched (an admin who switches the
	 * business type back gets their design back), but the site RENDERS the Default design until
	 * it is valid again — which is the safe direction.
	 *
	 * @param int $blog_id Site (0 = current).
	 * @return string
	 */
	public function active( int $blog_id = 0 ): string {

		$blog_id = $blog_id > 0 ? absint( $blog_id ) : get_current_blog_id();

		$is_current = ( $blog_id === get_current_blog_id() );

		/*
		 * THE DESIGN IS READ FROM THE TARGET SITE'S OWN OPTION ROW, ALWAYS.
		 *
		 * MEASURED (Phase 22, tests/_phase22-probe-restore.php)
		 * -----------------------------------------------------
		 * On this host `switch_to_blog()` does not change the active blog at all -
		 * `get_current_blog_id()` returns the SAME id before and after, and
		 * `restore_current_blog()` then leaves the process pointing at the switched
		 * value. Every call reported `switched=false`:
		 *     probe_switch(2)
		 *       inside: current=2 switched=false      <-- the switch did nothing
		 *       after restore: current=2              <-- and restore did nothing
		 *
		 * Two consequences followed, and BOTH were observed as wrong answers:
		 *
		 *   1. `get_theme_mod()` returned the CALLING blog's cached value rather than
		 *      the target's - and not an empty value, so a "fall back when empty" guard
		 *      never fired;
		 *   2. `restore_current_blog()` did not restore, so a caller that resolved one
		 *      site silently left the process on that site for every later question.
		 *
		 * A real single-site HTTP request never hits either (the current blog IS the
		 * target), which is why the front end was always correct. But the Design
		 * screen, the network layer and any tooling that inspects OTHER sites all do,
		 * and they were getting a mixture of the wrong site's design and a corrupted
		 * blog context.
		 *
		 * The fix is to stop depending on the switch for READING. The theme-mod row is
		 * addressed directly (`theme_mods_<target stylesheet>`), which is the same row
		 * WordPress itself reads and which was measured to be correct for all three
		 * sites. The blog context is therefore never mutated, so there is nothing to
		 * restore and no way to leak it to a caller.
		 */
		/*
		 * THE MOD IS READ THROUGH A CACHE-INDEPENDENT PATH, ALWAYS.
		 *
		 * MEASURED (Phase 22, tests/_phase22-probe-mod.php)
		 * -------------------------------------------------
		 * On this host `get_option()` / `get_theme_mod()` do not honour the blog
		 * context at all outside a real HTTP request. Even with
		 * `get_current_blog_id()` correctly reporting 2 or 3, the option cache still
		 * served blog 1's values:
		 *
		 *     site 2: current=2  get_option('stylesheet') = 'astra'   <-- blog 1's value
		 *             get_theme_mod('bb_theme_preset')  = (unset)    <-- blog 1's value
		 *             DIRECT DB (theme_mods_business-builder) = 'lawfirm-meridian'
		 *
		 * A real single-site request primes the cache for its own blog, so the front
		 * end was always correct. But every tooling path (the Design screen's
		 * cross-site preview, the network layer, any CLI work) would silently read the
		 * WRONG site's design.
		 *
		 * The `theme_mods_<stylesheet>` row is therefore addressed directly, which is
		 * the same row WordPress itself reads and which was measured to be correct for
		 * all three sites. There is no fast path worth keeping: one indexed option
		 * lookup per resolution is not a cost, and it removes an entire class of
		 * wrong-site bug rather than special-casing it.
		 */
		$stored = $this->read_theme_mod_for_blog( $blog_id, self::ACTIVE_MOD );

		/*
		 * Fall back to the in-process accessor only when the direct read is
		 * unavailable (a non-standard theme-mod store, or a database that could not be
		 * reached). This keeps behaviour correct rather than empty in that case.
		 */
		if ( '' === $stored && $is_current ) {
			$stored = (string) get_theme_mod( self::ACTIVE_MOD, '' );
		}

		/*
		 * READ THE REGISTRY INSIDE THE TARGET SITE'S CONTEXT.
		 *
		 * MEASURED REGRESSION (Phase 22, tests/_phase22-probe-resolve.php)
		 * -----------------------------------------------------------------
		 * `all()` was called AFTER `restore_current_blog()`, so it reflected the
		 * CALLING site's registry rather than the target's. On site 3 (Medical) the
		 * stored mod was `medical-vitality` and the business type was `medical` -
		 * both correct - yet the resolver returned `default`, because the registry
		 * it consulted was the CLI's site 1, where the Medical pack is not booted
		 * and `medical-vitality` therefore does not exist:
		 *
		 *     stored preset mod          = medical-vitality
		 *     site_business_type(3)      = 'medical'
		 *     compatible(medical)        = true
		 *     catalogue->active(3)       = default      <-- WRONG
		 *
		 * A design is only registered when its pack is booted, and packs boot per
		 * site. Asking the registry in the wrong context therefore produced a false
		 * "unknown design" and silently discarded a perfectly valid selection.
		 *
		 * The lookup now happens while still switched into the target site, which is
		 * exactly the context in which the design became available in the first
		 * place. `all()` is memoisation-free, so no stale cache is involved.
		 */
		/*
		 * The registry is consulted in the current context, and the blog context is
		 * NEVER mutated - so there is nothing to restore and no way to leak a switch
		 * to a caller (see the measured note at the top of this method).
		 */
		$all = $this->all();

		$stored = sanitize_key( $stored );

		if ( '' === $stored ) {
			return self::DEFAULT_DESIGN;
		}

		/* An unknown slug falls back, exactly as before. */
		if ( ! isset( $all[ $stored ] ) ) {
			return self::DEFAULT_DESIGN;
		}

		/*
		 * A known slug must ALSO be valid for THIS site's business type. The business type is read
		 * from the site's own options row through the cache-independent accessor, so the answer is
		 * correct even when the option cache is cold (the Phase 21 apply-time bug).
		 */
		$type = $this->site_business_type( $blog_id );

		if ( ! $this->is_compatible( $all[ $stored ], $type ) ) {
			return self::DEFAULT_DESIGN;
		}

		return $stored;
	}

	/**
	 * Read a theme mod for a specific blog straight from the database.
	 *
	 * Deliberately bypasses the per-blog theme-mod cache: this is used to answer questions about
	 * OTHER sites, where that cache is not guaranteed to hold the target blog's values (see the
	 * measured note in `active()`).
	 *
	 * The `theme_mods_<stylesheet>` row is the same row WordPress reads, and the stylesheet is
	 * resolved from the target blog's own options, so the correct row is always addressed.
	 *
	 * @param int    $blog_id Blog id.
	 * @param string $key     Theme mod key.
	 * @return string Raw mod value, or '' when absent.
	 */
	protected function read_theme_mod_for_blog( int $blog_id, string $key ): string {

		$stylesheet = $this->read_option_for_blog( $blog_id, 'stylesheet' );

		if ( '' === $stylesheet ) {
			return '';
		}

		$mods = $this->read_option_for_blog( $blog_id, 'theme_mods_' . $stylesheet );

		if ( '' === $mods ) {
			return '';
		}

		$unserialized = maybe_unserialize( $mods );

		if ( ! is_array( $unserialized ) || ! isset( $unserialized[ $key ] ) ) {
			return '';
		}

		return is_scalar( $unserialized[ $key ] ) ? (string) $unserialized[ $key ] : '';
	}

	/**
	 * The business type of a site, read without depending on the option cache.
	 *
	 * `DesignCatalogue::business_type_of()` needs a `BusinessType` service to answer this; this
	 * accessor exists so the resolver above can answer it too, without the catalogue having to
	 * hold a service it does not otherwise need.
	 *
	 * @param int $blog_id Site.
	 * @return string
	 */
	protected function site_business_type( int $blog_id ): string {

		/*
		 * A BUSINESS TYPE ONLY EXISTS ON A SITE THAT CAN CONSUME IT.
		 *
		 * MEASURED (Phase 22 audit, tests/_phase22-audit3.php)
		 * ----------------------------------------------------
		 * Site 1 runs Astra - it is not a Business Builder site - yet its options
		 * row carried a business type:
		 *
		 *     SITE 1 (builder.test, Astra)   bb_business_type = medical
		 *
		 * The option is written by `BusinessType::set_current()`, which has no notion
		 * of "only meaningful when the canonical Theme is active". Because the value
		 * survives a theme change, it then made the catalogue OFFER every Medical
		 * design to a site that cannot render one:
		 *
		 *     non-builder site 1 (astra) is offered no business-specific design
		 *       -> lawfirm-aurora, medical-clarity, medical-modern, …
		 * The Theme is the authority on whether a site is a Business Builder site
		 * (`stylesheet`), so the type is reported as EMPTY unless that site is
		 * running the canonical Theme. Nothing is deleted - the stored value is left
		 * where it is, so an admin who switches the theme back finds their selection
		 * again - but the catalogue stops acting on it in a context where it is
		 * meaningless. That is the §32 isolation guarantee, enforced at the reading
		 * layer rather than by hoping no one writes the option.
		 */
		$stylesheet = sanitize_key( $this->read_option_for_blog( $blog_id, 'stylesheet' ) );

		if ( self::CANONICAL_THEME !== $stylesheet ) {
			return '';
		}

		if ( class_exists( '\BusinessBuilderCore\Settings\BusinessType' ) ) {

			$service = new \BusinessBuilderCore\Settings\BusinessType();

			$direct = $this->read_option_for_blog( $blog_id, $service->get_option_name() );

			if ( '' !== $direct ) {
				return $service->exists( $direct ) ? $direct : '';
			}
		}

		return '';
	}

	/**
	 * Whether a design slug exists AND is valid for a business type.
	 *
	 * This is the SERVER-SIDE gate used before any write (§26, §37). The UI hiding an invalid
	 * design is not sufficient; this predicate is what actually prevents it.
	 *
	 * CONTEXT NOTE (the Phase 21 apply-time bug): `bb_theme_presets()` reflects the packs booted
	 * in the CURRENT request. A design registered by another business type's pack therefore does
	 * not exist in this request's registry, and `isset( $all[ $slug ] )` alone would wrongly
	 * reject it. `is_registered()` below handles that distinction without weakening the gate.
	 *
	 * @param string $slug          Design slug.
	 * @param string $business_type Business type slug.
	 * @return bool
	 */
	public function is_valid_for( string $slug, string $business_type ): bool {

		$slug = sanitize_key( $slug );

		$all = $this->all();

		if ( ! isset( $all[ $slug ] ) ) {
			return false;
		}

		return $this->is_compatible( $all[ $slug ], $business_type );
	}

	/**
	 * Whether a design is REGISTERED in a given site's own context (Phase 21 apply-time fix).
	 *
	 * WHY THIS EXISTS — measured failure
	 * ---------------------------------
	 * `apply()` validates the requested design against the TARGET site's registry, but
	 * `bb_theme_presets()` only contains the packs booted in the CALLING request. So applying
	 * `medical-clinical` to the Medical site from a request where the Medical pack is not booted
	 * failed with `invalid`, even though the design genuinely exists for that site.
	 *
	 * The fix is NOT to weaken the gate. It is to ask the question in the right context:
	 *   - if the slug is registered HERE, validate compatibility as before;
	 *   - if it is not registered here, the design belongs to a pack that is not booted in this
	 *     request. We then confirm the TARGET site genuinely owns that design by consulting the
	 *     catalogue in the TARGET SITE's context (packs boot per-site in a real request, so a
	 *     site whose business type the design declares is exactly the case we must accept).
	 *
	 * The business-type compatibility check is still enforced in both branches, so a LawFirm
	 * design can never be applied to a Medical site and an unknown slug is still rejected.
	 *
	 * @param string $slug          Design slug.
	 * @param string $business_type The TARGET site's business type.
	 * @param int    $blog_id       The TARGET site.
	 * @return bool
	 */
	protected function is_valid_in_site_context( string $slug, string $business_type, int $blog_id ): bool {

		$slug = sanitize_key( $slug );

		if ( '' === $slug ) {
			return false;
		}

		/* The design is registered in THIS request: validate it exactly as before. */
		if ( isset( $this->all()[ $slug ] ) ) {
			return $this->is_valid_for( $slug, $business_type );
		}

		/*
		 * Not registered here. Only trust it when the TARGET site's business type is known and
		 * non-empty: an unknown business type is never a licence to accept an unknown design.
		 */
		$business_type = sanitize_key( $business_type );

		if ( '' === $business_type ) {
			return false;
		}

		/*
		 * Re-ask the registry inside the target site's context. `switch_to_blog()` re-runs the
		 * site's pack boot, which is exactly how the design became available for that site in the
		 * first place.
		 */
		$switched = false;

		if ( is_multisite() && $blog_id > 0 && $blog_id !== get_current_blog_id() ) {
			switch_to_blog( $blog_id );
			$switched = true;
		}

		$target_all = $this->all();

		if ( $switched ) {
			restore_current_blog();
		}

		if ( isset( $target_all[ $slug ] ) ) {
			return $this->is_compatible( $target_all[ $slug ], $business_type );
		}

		/*
		 * Still unknown: the pack is not booted in either context. We cannot prove the design's
		 * business-type compatibility, so we refuse rather than guess. The Default design is the
		 * one exception — it is global by contract.
		 */
		return self::DEFAULT_DESIGN === $slug;
	}

	/**
	 * Read the business type of a site (server-side; never from the browser).
	 *
	 * @param BusinessType $business_type Registry service.
	 * @param int          $blog_id       Site (0 = current).
	 * @return string
	 */
	public function business_type_of( BusinessType $business_type, int $blog_id = 0 ): string {

		$blog_id = $blog_id > 0 ? absint( $blog_id ) : get_current_blog_id();

		/*
		 * A BUSINESS TYPE ONLY EXISTS ON A SITE RUNNING THE CANONICAL THEME.
		 *
		 * MEASURED (Phase 22, tests/_phase22-probe-isolation.php)
		 * ------------------------------------------------------
		 * Site 1 runs Astra, yet its options row carries `bb_business_type = medical`
		 * because the option survives a theme change and `set_current()` has no
		 * notion of "only meaningful on a Business Builder site". Reporting that value
		 * made the catalogue OFFER every Medical design to a site that cannot render
		 * one:
		 *
		 *     SITE 1 (builder.test) theme=astra raw=medical
		 *       designs offered = 7 (medical-clarity, medical-modern, …)
		 *
		 * The Theme is the authority on whether a site is a Business Builder site, so
		 * the type is reported as EMPTY unless that site runs the canonical Theme.
		 * Nothing is deleted - an admin who switches the theme back finds the
		 * selection again - but the platform stops acting on a value that is
		 * meaningless in this context. This is the §32 isolation guarantee, enforced
		 * where the question is asked rather than by trusting the stored option.
		 */
		$stylesheet = sanitize_key( $this->read_option_for_blog( $blog_id, 'stylesheet' ) );

		if ( self::CANONICAL_THEME !== $stylesheet ) {
			return '';
		}

		/*
		 * READ THE OPTION FROM THE SOURCE OF TRUTH, NOT THROUGH THE CACHE.
		 *
		 * MEASURED FAILURE (tests/_phase21-fix-fixtures.php):
		 *   database bb_business_type = 'law_firm'
		 *   get_option('bb_business_type') = ''      <-- after switch_to_blog()
		 *
		 * WordPress caches the whole `alloptions` row per blog, and `switch_to_blog()` does not
		 * always re-prime it before the first read. The BusinessType service reads through
		 * `get_option()`, so it returned `null` and `apply()` correctly refused a design that was
		 * genuinely valid - the design simply could not be applied.
		 *
		 * A real HTTP request starts with a clean cache for ONE blog and is unaffected, but the
		 * network admin, the Design screen and any tooling that inspects OTHER sites all hit this
		 * path. Reading the option directly makes the answer correct in every context, and it
		 * cannot return a stale value for the wrong site.
		 *
		 * The registry service is still consulted when the direct read is unavailable, so the
		 * behaviour degrades safely rather than failing.
		 */
		$direct = $this->read_option_for_blog( $blog_id, $business_type->get_option_name() );

		if ( '' !== $direct ) {

			/* Only report a type the registry actually recognises. */
			return $business_type->exists( $direct ) ? $direct : '';
		}

		$switched = false;

		if ( is_multisite() && $blog_id !== get_current_blog_id() ) {
			switch_to_blog( $blog_id );
			$switched = true;
		}

		$type = $business_type->get_current();

		if ( $switched ) {
			restore_current_blog();
		}

		return null === $type ? '' : (string) $type;
	}

	/**
	 * Read an option for a specific blog straight from the database.
	 *
	 * Deliberately bypasses the WordPress option cache: this is used to answer questions about
	 * OTHER sites, where the cache is not guaranteed to hold the target blog's values.
	 *
	 * @param int    $blog_id Blog id.
	 * @param string $name    Option name.
	 * @return string Raw option value, or '' when absent.
	 */
	protected function read_option_for_blog( int $blog_id, string $name ): string {

		global $wpdb;

		if ( ! is_object( $wpdb ) || $blog_id <= 0 ) {
			return '';
		}

		$table = $wpdb->get_blog_prefix( $blog_id ) . 'options';

		$value = $wpdb->get_var(
			$wpdb->prepare( "SELECT option_value FROM {$table} WHERE option_name = %s", $name )
		);

		return null === $value ? '' : (string) $value;
	}

	/**
	 * Apply a design to a site (§12).
	 *
	 * Writes ONLY the existing `bb_theme_preset` theme mod, and optionally clears customization
	 * overrides via the EXISTING reset API (§13). It cannot touch business type, pack, domain,
	 * content or pack data — it performs no other write.
	 *
	 * @param BusinessType $business_type    Registry service (for compatibility checking).
	 * @param string       $slug             Design slug.
	 * @param bool         $reset_overrides  Whether to restore the design's authored values.
	 * @param int          $blog_id          Target site (0 = current site — the normal case).
	 * @return array{ok:bool,error:string,previous:string,applied:string,reset:bool}
	 */
	public function apply( BusinessType $business_type, string $slug, bool $reset_overrides = true, int $blog_id = 0 ): array {

		$blog_id = $blog_id > 0 ? absint( $blog_id ) : get_current_blog_id();

		/* The caller must be allowed to customize THIS site. */
		if ( ! $this->current_user_can_customize( $blog_id ) ) {
			return array( 'ok' => false, 'error' => 'forbidden', 'previous' => '', 'applied' => '', 'reset' => false );
		}

		$slug = sanitize_key( $slug );

		$switched = false;

		if ( is_multisite() && $blog_id !== get_current_blog_id() ) {
			switch_to_blog( $blog_id );
			$switched = true;
		}

		$previous = sanitize_key( (string) get_theme_mod( self::ACTIVE_MOD, '' ) );

		/*
		 * Read the TARGET site's business type through the cache-independent accessor.
		 *
		 * Using `$business_type->get_current()` here was the measured apply-time bug: that service
		 * reads `get_option()`, which is not guaranteed to hold the switched blog's value, so a
		 * perfectly valid design was refused with `invalid`.
		 */
		$current_type = $this->business_type_of( $business_type, $blog_id );

		/*
		 * Validate against the registry AND business-type compatibility INSIDE the target
		 * site's context, so the check uses that site's packs, not the caller's (§26, §37).
		 */
		if ( ! $this->is_valid_in_site_context( $slug, $current_type, $blog_id ) ) {
			if ( $switched ) {
				restore_current_blog();
			}

			return array( 'ok' => false, 'error' => 'invalid', 'previous' => $previous, 'applied' => '', 'reset' => false );
		}

		set_theme_mod( self::ACTIVE_MOD, $slug );

		$reset = false;

		if ( $reset_overrides && function_exists( 'bb_theme_reset_all_overrides' ) ) {
			bb_theme_reset_all_overrides();
			$reset = true;
		}

		if ( $switched ) {
			restore_current_blog();
		}

		return array( 'ok' => true, 'error' => '', 'previous' => $previous, 'applied' => $slug, 'reset' => $reset );
	}

	/**
	 * Restore the Default design (§16).
	 *
	 * @param BusinessType $business_type Registry service.
	 * @param bool         $reset_overrides Clear overrides too.
	 * @param int          $blog_id        Target site.
	 * @return array{ok:bool,error:string,previous:string,applied:string,reset:bool}
	 */
	public function restore_default( BusinessType $business_type, bool $reset_overrides = true, int $blog_id = 0 ): array {

		return $this->apply( $business_type, self::DEFAULT_DESIGN, $reset_overrides, $blog_id );
	}

	/**
	 * Reset ALL customization overrides for a site (§16).
	 *
	 * @param int $blog_id Site (0 = current).
	 * @return array{ok:bool,error:string,removed:int,design:string}
	 */
	public function reset_customization( int $blog_id = 0 ): array {

		$blog_id = $blog_id > 0 ? absint( $blog_id ) : get_current_blog_id();

		if ( ! $this->current_user_can_customize( $blog_id ) ) {
			return array( 'ok' => false, 'error' => 'forbidden', 'removed' => 0, 'design' => '' );
		}

		$switched = false;

		if ( is_multisite() && $blog_id !== get_current_blog_id() ) {
			switch_to_blog( $blog_id );
			$switched = true;
		}

		$removed = function_exists( 'bb_theme_reset_all_overrides' ) ? (int) bb_theme_reset_all_overrides() : 0;

		$design = $this->active();

		if ( $switched ) {
			restore_current_blog();
		}

		return array( 'ok' => true, 'error' => '', 'removed' => $removed, 'design' => $design );
	}

	/**
	 * Reset ONE customization control to the active design's value (§16).
	 *
	 * @param int    $blog_id Site (0 = current).
	 * @param string $key     Design control key (e.g. `color_primary`).
	 * @return array{ok:bool,error:string,key:string}
	 */
	public function reset_control( int $blog_id, string $key ): array {

		$blog_id = $blog_id > 0 ? absint( $blog_id ) : get_current_blog_id();

		if ( ! $this->current_user_can_customize( $blog_id ) ) {
			return array( 'ok' => false, 'error' => 'forbidden', 'key' => '' );
		}

		if ( ! function_exists( 'bb_theme_design_mod_name' ) || ! function_exists( 'bb_theme_design_schema' ) ) {
			return array( 'ok' => false, 'error' => 'unavailable', 'key' => '' );
		}

		$key = sanitize_key( $key );

		if ( '' === $key ) {
			return array( 'ok' => false, 'error' => 'invalid', 'key' => '' );
		}

		/*
		 * bb_theme_design_schema() is a LIST of control entries, each carrying a `key` field —
		 * not a map keyed by control name. Look the control up by its key so an unknown control
		 * can never reach remove_theme_mod().
		 */
		$known = false;

		foreach ( bb_theme_design_schema() as $control ) {

			if ( is_array( $control ) && isset( $control['key'] ) && $key === (string) $control['key'] ) {
				$known = true;
				break;
			}
		}

		if ( ! $known ) {
			return array( 'ok' => false, 'error' => 'invalid', 'key' => '' );
		}

		$switched = false;

		if ( is_multisite() && $blog_id !== get_current_blog_id() ) {
			switch_to_blog( $blog_id );
			$switched = true;
		}

		remove_theme_mod( bb_theme_design_mod_name( $key ) );

		if ( $switched ) {
			restore_current_blog();
		}

		return array( 'ok' => true, 'error' => '', 'key' => $key );
	}

	/**
	 * Whether the current user may customize a site's design (§26).
	 *
	 * Mirrors the Theme's own customization capability (`edit_theme_options`) so the Design
	 * screen and the existing Customizer cannot drift apart.
	 *
	 * @param int $blog_id Target site.
	 * @return bool
	 */
	public function current_user_can_customize( int $blog_id ): bool {

		$blog_id = absint( $blog_id );

		if ( $blog_id <= 0 ) {
			return false;
		}

		$switched = false;

		if ( is_multisite() && $blog_id !== get_current_blog_id() ) {
			switch_to_blog( $blog_id );
			$switched = true;
		}

		$can = current_user_can( 'edit_theme_options' );

		if ( $switched ) {
			restore_current_blog();
		}

		return (bool) $can;
	}

	/**
	 * The number of designs available for a business type.
	 *
	 * @param string $business_type Business type slug.
	 * @return int
	 */
	public function count_for( string $business_type ): int {

		return count( $this->for_business_type( $business_type ) );
	}
}