# PHASE 19 — SECOND BUSINESS PACK PROOF (MEDICAL) — FINAL REPORT

**Verdict: the platform thesis is PROVEN.** A genuinely different business type was added as
an independent Pack with **zero Core edits and zero Theme edits**, and **no second design
system**.

**MEDICAL PACK: 76/76 assertions PASS.**

Environment: WordPress Multisite.

| Site | Domain | Theme | Business type |
| --- | --- | --- | --- |
| 1 — `datalynx` | `http://builder.test/` | Astra | (none) |
| 2 — Law Firm Demo | `http://lawfirm.builder.test/` | `business-builder` | `law_firm` |
| 3 — Medical Demo | `http://medical.builder.test/` | `business-builder` | `medical` |

---

## 1. Executive Summary

Phase 18 established that adding a second business type *should* need no Core/Theme change.
Phase 19 **proves it by doing it**.

The Medical Pack is a real, independent Pack:

- it **self-registers** through `bb_register_packs` — the Core never hardcodes it;
- it owns its own domain (`bb_doctor`, `bb_medical_service`, `bb_specialty`);
- it registers its sections into the **one shared** `SectionRegistry`;
- it **claims its own section types** through `bb_render_section` — the Core renderer names
  **no** business concept (neither LawFirm nor Medical);
- it ships its own card components through `bb_component_roots` — the theme resolver was not
  touched;
- it registers its own section layouts through `bb_register_section_variants`;
- it contributes a **design style** through `bb_theme_presets` using **only the existing
  `--bb-*` tokens** — no `--medical-*`, no second resolver, no second CSS namespace.

**The decisive demonstration** — one real page at `http://medical.builder.test/` composes
**global sections and Medical pack sections in the same builder-driven page**, laid out by
the shared builder, styled by the shared design system:

```
Medical page (live, HTTP 200, 0 console errors, 0 failed requests)
  hero            ← GLOBAL  (Core)
  about           ← GLOBAL  (Core)
  doctors         ← MEDICAL (pack section, grid layout, compact cards)
  medical_services← MEDICAL (pack section, featured layout, featured cards)
  features        ← GLOBAL  (Core)
  cta             ← GLOBAL  (Core)
  contact         ← GLOBAL  (Core)
  footer          ← GLOBAL  (Core)
```

**Nothing in the Theme or the Core knows the word "Medical".**

| Success criterion (phase §31) | Result |
| --- | --- |
| A second business type can be added as an independent Pack | **YES** — `packs/Medical/` only |
| …without modifying the Core Theme | **YES** — theme source unchanged, 0 pack references |
| …without introducing a second design system | **YES** — same `--bb-*` tokens, same resolver |

---

## 2. Pre-Implementation Audit

Full audit: `docs/phase-19-medical-pack-audit.md` (written **before** any Medical code).

It inspected, in the actual source (not filenames): `BusinessType`, `PackManager`,
`bb_register_packs`, `Autoloader`, `Container`, `SectionRegistry`, `SectionRenderer` +
`bb_render_section`, `SectionVariants` + `bb_register_section_variants`, the component API +
`bb_component_roots`, `bb_theme_presets` / `bb_theme_preset_config`, the theme design schema
and customization, shell variants, `PageManager` / `Plugin::render_builder_content`, the
LawFirm pack structure, the admin pack-activation flow, and the existing test harness.

**Audit verdict: no architectural conflict. All 19 required extension points already
existed and none required modification.**

Two **non-blocking observations** were recorded (and one of them mattered — see §24):

1. `BusinessType['pack']` is decorative: it holds the label `'Medical'`, while `PackManager`
   is keyed by the business-type **slug** (`medical`). `PackManager` never reads that array
   key, so the correct registration key is the slug. No Core change needed.
2. Network-level pack/design assignment does not exist (Phase 18 Class D) — the Medical proof
   therefore uses the existing **site-level** mechanism.

---

## 3. Extension Points Reused

Every one of these is **pre-existing**. Not one was modified for Medical.

| # | Extension point | Owner | Medical's use |
| --- | --- | --- | --- |
| 1 | `bb_register_packs` | Core | MedicalPack self-registers (declared in the plugin bootstrap) |
| 2 | `BusinessType` (`medical` already declared) | Core | read-only |
| 3 | `PackManager` lifecycle | Core | `register()` / `boot()` implemented by the pack |
| 4 | `Autoloader` `Packs\` convention | Core | drop-in path resolution |
| 5 | `Container` DI | Core | `SectionRegistry` injected |
| 6 | `SectionRegistry::register()` | Core | `doctors`, `medical_services` |
| 7 | `bb_render_section` | Core | the pack claims its two types |
| 8 | `bb_register_section_variants` + `SectionVariants` | Core | `doctors` × 3, `medical_services` × 2 |
| 9 | `bb_component_roots` | Theme | `packs/Medical/Sections/components` |
| 10 | `bb_component` / `bb_component_path` | Theme | the six Medical card templates |
| 11 | `bb_theme_presets` | Theme | the `medical-modern` design style |
| 12 | `bb_theme_preset_config` | Theme | additive refinement of only its own preset |
| 13 | `--bb-*` token namespace | Theme | the only tokens used |
| 14 | `SiteSettingsPage` business-type select | Core | the existing admin selection UI |
| 15 | `PageManager` / `Plugin::render_builder_content` | Core | renders the pack's sections on the page |
| 16 | Pack rewrite flush pattern | LawFirm | mirrored for the pack's permalinks |

**Not used (and documented as available but unnecessary):** `bb_theme_customization_overrides`
(the pack ships a preset, not controls) and shell variants (Theme-owned, shared as-is).

---

## 4. Medical Pack Architecture

```
packs/Medical/
├── config.php                      metadata (name/slug/business/version)
├── MedicalPack.php                 DI ctor + register() + boot()
├── PostTypes/
│   ├── Doctor.php                  bb_doctor          (title, specialty, photo, summary, profile URL)
│   └── MedicalService.php          bb_medical_service (title, summary, image, optional URL)
├── Taxonomies/
│   └── Specialty.php               bb_specialty       (attached to both post types)
├── Sections/
│   ├── MedicalSections.php         registry + bb_render_section claim + prepared data
│   ├── MedicalQueries.php          ALL business querying (never in templates)
│   ├── components/
│   │   ├── doctor/card.php             default
│   │   ├── doctor/card-compact.php     compact
│   │   ├── doctor/card-horizontal.php  horizontal
│   │   ├── medical-service/card.php    default
│   │   └── medical-service/card-featured.php  featured
│   └── variants/
│       ├── doctors/default.php         fixed-columns grid
│       ├── doctors/grid.php            auto-fit grid
│       ├── doctors/list.php            stacked directory
│       ├── medical-services/default.php    grid
│       └── medical-services/featured.php   lead card + auto-fit remainder
└── Design/
    └── MedicalPresets.php          the `medical-modern` style (existing --bb-* tokens)
```

**Deliberately smaller than LawFirm's structure**, not a second architecture: LawFirm splits
each entity into `PostTypes/X` + `XFields` (~700 lines/entity); for a minimal proof entity
that would be pure ceremony, so Medical uses **one class per entity** that registers both the
post type and its two-to-three admin fields. The *mechanisms* are identical: Container DI,
the shared `SectionRegistry`, the `bb_render_section` claim, filter-registered components,
variants and presets.

**Registration path (the only place Medical is named, and it is not Core):**

```php
// business-builder-core.php  (bootstrap — must declare before the Core fires the hook)
add_action( 'bb_register_packs', array(
    'BusinessBuilderCore\Packs\Medical\MedicalPack', 'register_pack',
) );

// packs/Medical/MedicalPack.php
public static function register_pack( $pack_manager ): void {
    $pack_manager->register( self::BUSINESS_TYPE, self::class ); // 'medical' => MedicalPack
}
```

`ServiceProvider` still registers `law_firm` and fires `bb_register_packs`; it does not
mention Medical. `assert: Core does NOT hardcode the medical pack registration` — **PASS**.

---

## 5. Medical Business Type

The `medical` business type is **already declared** by the existing `BusinessType` registry
(Phase 18 §1) and it is the **single source of truth** — no second business-type system was
created.

`assert: business type 'medical' declared in the existing BusinessType registry` — **PASS**
`assert: BusinessType remains the single source of truth (4+ types)` — **PASS**

Selection uses the **existing** admin flow (`SiteSettingsPage` → `bb_business_type` option,
per site). No new UI.

---

## 6. Medical Sections

Two sections, registered in the **one shared** `SectionRegistry` under the pack's own
`medical` category:

| Section | Category | Settings | Content |
| --- | --- | --- | --- |
| `doctors` | `medical` | `variant`, `card_variant`, `columns`, `limit`, `featured`, `specialty`, `order` | `title`, `description` |
| `medical_services` | `medical` | `variant`, `card_variant`, `columns`, `limit`, `featured`, `specialty`, `order` | `title`, `description` |

`assert: doctors section registered` / `medical_services section registered` — **PASS**
`assert: Medical sections use the medical category` — **PASS**
`assert: Medical sections expose typed settings + content schemas` — **PASS**

**No `MedicalSectionRegistry` exists** (phase §9). Both layouts and card designs are derived
from the live registries, so a mis-declared option can never be offered (the same discipline
LawFirm uses).

---

## 7. Medical Section Rendering

Medical claims its types through the **generic** `bb_render_section` filter established in
Phase 18 — the exactly mandated extension point.

```php
// packs/Medical/Sections/MedicalSections.php
switch ( sanitize_key( (string) $type ) ) {
    case 'doctors':          $this->render_doctors_section( ... );          return true;
    case 'medical_services': $this->render_medical_services_section( ... ); return true;
}
return (bool) $claimed;   // anything else falls through to core
```

**The Core `SectionRenderer` contains no `if ($type === 'doctors')`** — and, after Phase 18,
no LawFirm slug either.

`assert: Core SectionRenderer has NO pack section slugs (LawFirm or Medical)` — **PASS**
`assert: Core SectionRenderer offers the generic bb_render_section filter` — **PASS**
`assert: pack claims doctors / medical_services through bb_render_section` — **PASS**
`assert: pack does NOT claim foreign types (consultation belongs to LawFirm)` — **PASS**
`assert: an unclaimed section type falls through (no core hardcoding)` — **PASS**

The Core search list was exactly the one the phase specified:
`lawyers, legal_services, practice_areas, testimonials, faq, doctors, medical_services,
departments` → **0 hits**.

---

## 8. Medical Components

`doctor/card` and `medical-service/card`, shipped from the pack and resolved by the
**unmodified** theme component API.

- **Prepared args only.** Every value is prepared once in `MedicalSections` from
  `MedicalQueries`; the templates render and nothing else.
- **No queries, no business logic, no Page Builder knowledge.**
  `assert: no Medical component queries data or knows the Page Builder` — **PASS** (0 hits for
  `WP_Query`, `get_posts(`, `get_terms(`, `get_post_meta(`, `SectionRenderer`, `PageManager`).
- **Consume Theme tokens only.**
  `assert: Medical components introduce NO second token namespace` — **PASS** (0 hits for
  `--medical-`/`--med-`).
- **RTL/LTR safe.** No physical-direction CSS was added; the components inherit the theme's
  logical-property tokens and classes.
- **Accessibility preserved.** Semantic `<article>`/`<h3>` structure, escaped output,
  `rel="noopener noreferrer"` on external links, `tel:`/`mailto:` where applicable.

`assert: pack registers its component root via bb_component_roots (when booted)` — **PASS**
`assert: doctor/card … medical-service/card-featured ship` — **PASS** (5 templates)

**The generic component resolver was not modified.**

---

## 9. Component Variants

The phase required at least one Medical component to demonstrate more than one visual variant.
Medical ships **five**:

```
doctor/card
├── default
├── compact       ← dense, smaller media, no summary
└── horizontal    ← photo beside details (directory row)

medical-service/card
├── default
└── featured      ← larger media + eyebrow
```

Proven in the browser on the live page: setting the doctors section to `card_variant=compact`
rendered **3** `.bb-doctor-card-compact` elements; the medical-services section at
`card_variant=featured` rendered **3** `.bb-medical-service-card-featured` elements.

This demonstrates the phase's real point: **component variants are Pack-independent
infrastructure.** A pack declares a variant by shipping `card-{variant}.php`; the resolver,
the whitelist and the fallback are the theme's, unchanged.

---

## 10. Section Variants

Section layouts, registered by the pack in the shared `SectionVariants` registry:

```
doctors
├── default   fixed-columns grid
├── grid      genuinely different auto-fit grid (ignores the columns setting)
└── list      stacked directory, one doctor per row

medical_services
├── default   grid
└── featured  lead card + auto-fit remainder
```

The required flow holds exactly:

```
Medical query (MedicalQueries)
      ↓
Prepared doctor args (MedicalSections — ONCE)
      ↓
Section variant (include, $args in scope)
      ↓
Component variant (bb_component)
      ↓
HTML
```

`assert: no Medical variant template performs a business query` — **PASS** (0 hits for
`WP_Query`, `get_posts(`, `get_terms(`, `get_post_meta(`, `new MedicalQueries`, `wp_query`).

Traversal safety is **inherited from the core resolver, not re-implemented**:

`assert: traversal-unsafe variant request resolves to default` (`'../../etc/passwd'`) — **PASS**
`assert: unknown variant resolves to default` (`'not_a_layout'`) — **PASS**

---

## 11. Design Preset Validation

The phase asked the central question first: **does the Theme own the framework while Packs
*contribute* presets (Model C)?**

**Answer: yes — verified by using it.**

`MedicalPresets` adds one preset (`medical-modern`) through the existing filters only:

```php
add_filter( 'bb_theme_presets',        array( $this, 'add_preset' ) );
add_filter( 'bb_theme_preset_config',  array( $this, 'refine_config' ), 10, 2 );
```

The audit caught a real mismatch before it shipped: my first draft nested the tokens under a
`tokens` key, but the Theme's preset shape is **flat** (`--bb-*` keys beside `label`, exactly
like the built-in `default`/`modern`/`luxury`). The preset was corrected to match — which is
itself evidence the contribution must follow the Theme's contract rather than invent one.

It introduces **none** of the forbidden things:

| Forbidden | Status |
| --- | --- |
| a second token system | **none** — every token is `--bb-*` |
| a second CSS variable namespace | **none** — 0 hits for `--medical-*` |
| a second preset resolver | **none** — the theme's resolver is used |
| `Theme → Medical` dependency | **none** — the theme never references the pack |
| duplicate customization logic | **none** — one customization system |

`assert: Medical preset registered in the theme preset registry` — **PASS**
`assert: Theme built-in presets remain present (default fallback intact)` — **PASS**
`assert: every Medical preset token is in the --bb-* namespace` — **PASS**
`assert: Medical preset declares no --medical-* token` — **PASS**
`assert: Theme design layer contains NO Medical references` — **PASS**

The preset is a **proof**, deliberately small: it overrides colour, surface, border, radius
and header/footer tokens — **not** five complete designs (the phase forbade inventing five
designs to satisfy a number).

---

## 12. Design Customization Validation

The critical sequence was tested end to end on the live Medical site:

```
Medical preset        --bb-color-primary = #0f766e   (teal)
User override         --bb-color-primary = #b91c1c   (set as the bb_design_color_primary theme mod)
FINAL (rendered CSS)  --bb-color-primary = #b91c1c   ← the USER value wins
```

and the tokens the user did **not** override still came from the preset:

```
--bb-radius-md = 0.75rem   ← from the Medical preset
```

Reset restored inheritance: removing the theme mod returned `--bb-color-primary` to the
preset's `#0f766e`.

`assert: user customization OVERRIDES the Medical preset` — **PASS**
`assert: non-overridden preset tokens survive the override` — **PASS**
`assert: reset restores preset inheritance` — **PASS**

There is **one customization system**; no Medical Customizer was created. The live page's
body class confirms the selection is real: `bb-theme-preset-medical-modern`.

---

## 13. Design/Layout/Component Orthogonality

Tested by changing **only** the layout/variant layer on the live site, with the design preset
untouched, and observing the rendered HTML:

| Combination | Layout rendered | Card variant rendered | Design token |
| --- | --- | --- | --- |
| A — `variant=grid`, `card_variant=compact` | `.bb-doctors-grid-auto` | `.bb-doctor-card-compact` ×3 | `#0f766e` |
| B — `variant=list`, `card_variant=horizontal` | `.bb-doctors-list` | `.bb-doctor-card-horizontal` ×3 | `#0f766e` (unchanged) |

The service section, configured independently at `variant=featured` / `card_variant=featured`,
stayed `featured` in **both** runs.

A third layer held constant throughout: the global sections (Core) rendered identically under
the Medical preset.

**Conclusion:**

```
Design Style     ≠   Section Layout   ≠   Component Variant
(theme mod)          (section setting)     (per-card setting)
```

Changing one did **not** silently change another. The phase's example combinations
(`Medical → Medical preset → Doctors grid → Doctor compact`,
`Medical → Medical preset → Doctors list → Doctor horizontal`) are both real, verified, live
pages.

---

## 14. Network vs Site Scope

| Question (phase §17) | Answer |
| --- | --- |
| How does the Medical proof select its preset? | `set_theme_mod( 'bb_theme_preset', 'medical-modern' )` — the existing site-level mechanism |
| Where is the value stored? | The **theme mod** `bb_theme_preset` on that site (`theme_mods_business-builder` option) |
| Is the selection site-scoped? | **Yes** — verified: Medical is `medical-modern`, LawFirm is not |
| Does the Network layer control it? | **No** — network-level design assignment does not exist |

`assert: Medical site selected the Medical preset` — **PASS**
`assert: LawFirm site did NOT inherit the Medical preset` — **PASS**

**Network administration remains deferred**, per the phase's explicit instruction not to
invent a Network UI to complete this phase. The separation is respected: Network decides
*what kind of site is being sold*; Site decides *how it looks*; both layers already have their
storage, and nothing in Phase 19 crosses that line.

---

## 15. Multisite Isolation

A temporary Medical site (blog 3) was created and reconciled. Isolation verified in both
directions:

| Assertion | Result |
| --- | --- |
| Medical site business type = `medical` | **PASS** |
| LawFirm site business type = `law_firm` | **PASS** |
| Business types do not leak across sites | **PASS** |
| Design presets do not leak across sites | **PASS** |
| Medical site has doctors | **PASS** |
| Medical site has NO lawyers (LawFirm data did not leak in) | **PASS** |
| LawFirm site has NO doctors (Medical data did not leak in) | **PASS** |
| Site 1 has no BB business type | **PASS** |
| Site 1 has no Medical data | **PASS** |

Live cross-check (HTTP + headless browser):

| Site | Status | Title | Preset | Doctors | Lawyers | BB sections |
| --- | --- | --- | --- |
| 1 | 200 | datalynx | none | 0 | 0 | 0 |
| 2 | 200 | Law Firm Demo | default | 0 | 2 | 7 |
| 3 | 200 | Medical Demo | `medical-modern` | 3 | 0 | 8 |

Zero console errors and zero failed requests on all three.

---

## 16. LawFirm Regression

LawFirm was **not modified to make Medical work**. Verified:

| Assertion | Result |
| --- | --- |
| `bb_register_packs` contributes Medical without touching `law_firm` | **PASS** |
| Core still registers the `law_firm` pack | **PASS** |
| Core still maps `law_firm` → `LawFirmPack` | **PASS** |
| LawFirm pack registers in the real (Core-shaped) manager | **PASS** |
| LawFirm pack boots | **PASS** |
| Both packs coexist in one manager | **PASS** |
| LawFirm still claims `lawyers` / `legal_services` / `practice_areas` / `testimonials` / `faq` | **PASS** |
| LawFirm still uses the generic `bb_render_section` hook | **PASS** |
| LawFirm site business type unchanged (`law_firm`) | **PASS** |
| LawFirm site theme unchanged (`business-builder`) | **PASS** |
| LawFirm site did NOT inherit the Medical preset | **PASS** |

Live: `http://lawfirm.builder.test/` returns 200 with all 7 original section types, 2 lawyer
cards, its own blue primary token (`#2563eb`) and 0 doctor cards, 0 console errors.

Full LawFirm/business suite results in §22.

---

## 17. Core Domain-Agnostic Verification

Mandated search of the Core rendering infrastructure for
`lawyers`, `legal_services`, `practice_areas`, `testimonials`, `faq`, `doctors`,
`medical_services`, `departments`:

**Result: 0 hits** (comments stripped).

Additionally, the Core's pre-hook region was checked to contain **exactly one**
`pack_manager->register(` call (`law_firm`) and **no** `'medical'` literal.

`assert: Core SectionRenderer has NO pack section slugs (LawFirm or Medical)` — **PASS**
`assert: Core does NOT hardcode the medical pack registration` — **PASS**
`assert: Core hardcodes ONLY its own law_firm pack` — **PASS**

The Pack claims its own types. The Core owns none.

---

## 18. Theme Domain-Agnostic Verification

A comment-stripped scan of the **entire theme** for
`LawFirm`, `Medical`, `bb_lawyer`, `bb_doctor`, `bb_legal_service`, `bb_medical_service`,
`bb_practice_area`, `bb_specialty`, `bb_business_type`, `Packs\`:

**Result: 0 hits.**

`assert: Theme source has NO pack/business coupling (LawFirm or Medical)` — **PASS**

The one class of hit that would have been *legitimate* — a generic API reference — did not
occur, so no classification was needed. The Theme remains a pure presentation framework.

Note on RTL/LTR, responsive and accessibility: **no theme CSS, markup or token was changed in
Phase 19** (the theme is untouched), so those properties are exactly as verified in Phases
15–18. The Medical page inherits them; no new CSS was added for Medical.

---

## 19. Files Created

**Pack (18 files)**

```
packs/Medical/config.php
packs/Medical/MedicalPack.php
packs/Medical/PostTypes/Doctor.php
packs/Medical/PostTypes/MedicalService.php
packs/Medical/Taxonomies/Specialty.php
packs/Medical/Sections/MedicalSections.php
packs/Medical/Sections/MedicalQueries.php
packs/Medical/Design/MedicalPresets.php
packs/Medical/Sections/components/doctor/card.php
packs/Medical/Sections/components/doctor/card-compact.php
packs/Medical/Sections/components/doctor/card-horizontal.php
packs/Medical/Sections/components/medical-service/card.php
packs/Medical/Sections/components/medical-service/card-featured.php
packs/Medical/Sections/variants/doctors/default.php
packs/Medical/Sections/variants/doctors/grid.php
packs/Medical/Sections/variants/doctors/list.php
packs/Medical/Sections/variants/medical-services/default.php
packs/Medical/Sections/variants/medical-services/featured.php
```

**Test + docs + environment helper (4 files)**

```
tests/runtime-phase19-medical-pack.php       76 assertions
tests/_phase19-provision.php                 idempotent Multisite provisioning
docs/phase-19-medical-pack-audit.md          pre-implementation audit
docs/phase-19-medical-pack-final-report.md   this file
```

All 18 pack files pass `php -l`.

---

## 20. Files Modified

**Only one file, and only to declare the pack's self-registration hook:**

```
business-builder-core.php   + an add_action( 'bb_register_packs', …MedicalPack::register_pack )
```

No other file was modified for Phase 19. (Phase 18's changes to `ServiceProvider.php`,
`SectionRenderer.php` and `LawFirmSections.php` were verified still intact and are unchanged
by this phase.)

---

## 21. Files Explicitly Untouched

```
themes/business-builder/**                        (entire theme — ZERO edits)
includes/Core/ServiceProvider.php
includes/Core/PackManager.php
includes/Core/Autoloader.php
includes/Core/Container.php
includes/Core/Plugin.php
includes/Builder/SectionRegistry.php
includes/Builder/SectionRenderer.php              (Medical-unaware)
includes/Builder/SectionVariants.php
includes/Builder/section-variants.php
includes/Builder/CoreSections.php
includes/Builder/PageManager.php
includes/Builder/SectionManager.php
includes/Settings/BusinessType.php
includes/Settings/SiteSettings.php
includes/Admin/**  includes/REST/**  includes/Theme/**
includes/Core/Payments/**  includes/Core/Notifications/**  includes/Core/Audit/**
packs/LawFirm/**                                  (unchanged)
assets/**
```

No visual appearance was changed. No CSS was written for Medical — the pack's cards rely on
existing theme structural classes and tokens.

---

## 22. Exact Test Results

PHP 8.2.14 (CLI). `php -l` clean on every modified or created PHP file.

```
tests/runtime-phase19-medical-pack.php                76 PASS / 0 FAIL
```

Breakdown:

| Section | Assertions | Result |
| --- | --- | --- |
| 1. Core (self-registration, activation, generic rendering, no slugs, fallback) | 9 | all PASS |
| 2. Medical (type, boot, sections, claim, entities, components, variants, contracts) | 27 | all PASS |
| 3. Design (preset, tokens, one system, override, reset) | 13 | all PASS |
| 4. LawFirm regression | 14 | all PASS |
| 5. Theme domain-agnosticism | 1 | PASS |
| 6. Multisite isolation | 12 | all PASS |

**Regression (unchanged suites, re-run after Phase 19):**

```
runtime-phase18-platform-architecture.php            42 PASS / 0 FAIL
runtime-phase17-shell-content-providers.php          37 PASS / 0 FAIL
runtime-phase16-theme-content.php                    52 PASS / 0 FAIL
runtime-phase15-theme-shell.php                      65 PASS / 0 FAIL
runtime-phase14-theme-design-system.php              42 PASS / 0 FAIL
runtime-phase14-admin.php                            14 PASS / 0 FAIL
runtime-phase13-card-catalog.php                    127 PASS / 0 FAIL
runtime-phase12-card-variants.php                    39 PASS / 0 FAIL
runtime-phase11-variants.php                         39 PASS / 0 FAIL
runtime-phase11-schema.php                           12 PASS / 0 FAIL
runtime-phase10-components.php                       31 PASS / 0 FAIL
runtime-phase9-theme.php                             24 PASS / 0 FAIL
runtime-lawyer-management.php                        26 PASS / 0 FAIL
runtime-practice-area.php                            PASS / 0 FAIL
runtime-activity-and-isolation.php                   11 PASS / 0 FAIL
runtime-payment-completion.php                       32 PASS / 0 FAIL
runtime-manual-review.php                            29 PASS / 0 FAIL
runtime-consultation-markpaid-sync.php                6 PASS / 0 FAIL
runtime-free-invoice.php                             13 PASS / 0 FAIL
runtime-receipt-page-free.php                         5 PASS / 0 FAIL
```

**Pre-existing, environment-limited (NOT caused by Phase 19) — reported exactly as observed:**

`runtime-phase9-render.php` and `runtime-phase9-rtl-preset.php` report failures in this
environment. **This was proven pre-existing by experiment:** all Phase-19 and Phase-18 source
changes were stashed (`git stash`) and the suite was re-run against the untouched baseline — it
produced **the identical 7 FAILs** (`home has bb-theme body class`, `home has theme header`,
`theme footer`, `skip link`, `main landmark`, `enqueues theme tokens`, `404 renders with theme
shell`; plus the RTL locale skip). Cause: the suite's own harness calls `switch_theme()` on
blog 2 while this environment serves that site's front end differently. It is a harness
limitation documented in Phases 15–18, not a product regression. These results are **not**
reported as passing.

---

## 23. Live Verification

| Check | Result |
| --- | --- |
| `http://medical.builder.test/` | **200**, title "Medical Demo", 0 fatal |
| Medical page section types | `hero, about, doctors, medical_services, features, cta, contact, footer` |
| Global + pack coexistence | **8 sections in one builder page** (6 Core + 2 Medical) |
| `doctors` section | rendered via the `bb_render_section` claim; `.bb-doctors-grid-auto` (grid variant) |
| `medical_services` section | `.bb-medical-services-lead` + rest (featured variant) |
| Component variants | 3 × `.bb-doctor-card-compact`; 3 × `.bb-medical-service-card-featured` |
| Design style | body class `bb-theme-preset-medical-modern` |
| Design tokens | `--bb-color-primary: #0f766e`; `--bb-radius-md: 0.75rem` |
| User override run | `--bb-color-primary` became `#b91c1c`; radius stayed `0.75rem` |
| Reset run | `--bb-color-primary` returned to `#0f766e` |
| Orthogonality run B | `list` + `horizontal` (preset unchanged) |
| Browser (Medical) | **0 console errors, 0 failed requests** |
| Browser (LawFirm) | 200, "Law Firm Demo", 2 lawyer cards, `#2563eb`, **0 console errors, 0 failed requests** |
| Browser (site 1) | 200, "datalynx", no BB shell, **0 console errors, 0 failed requests** |
| `http://lawfirm.builder.test/` | 200, 7 original sections, 0 doctor cards, 0 fatal |
| `http://builder.test/` | 200, 0 BB sections (Astra), 0 fatal |

---

## 24. Problems Found

1. **Real defect discovered during implementation — and it was mine, in a throwaway helper.**
   My first provisioning script called `update_blog_option()` before the Medical blog id had
   resolved, so it rethemed **site 1** (Astra → business-builder) and later seeded the Medical
   demo entities **into site 1**. This was caught by the Phase 19 isolation assertions
   (`site 1 has no Medical data` → FAIL), diagnosed, and fully repaired: site 1's theme, URLs,
   business type and theme mods were restored, and the 3 stray Medical entities were deleted
   (site 1 doctor count = 0). This is exactly the kind of cross-site contamination the
   isolation tests exist to catch — and they caught it.
   *Correction to the permanent helper:* `switch_theme()` only ever runs inside
   `switch_to_blog()`, and the provision script is now idempotent and order-safe.

2. **Preset shape mismatch (self-corrected during implementation).** My first `MedicalPresets`
   nested the tokens under a `tokens` key; the Theme's preset contract is **flat**. Corrected
   before shipping — evidence that a pack must follow the Theme's contract, not invent one.

3. **Two non-blocking observations from the audit** (`BusinessType['pack']` is a decorative
   label; no network-level design assignment). Both documented, neither blocking, neither
   requiring a Core change.

**No Core or Theme architectural conflict was found.** The phase's stop condition ("if the
audit discovers that this is not currently possible, stop and report the exact architectural
conflict") was never triggered.

---

## 25. Known Limitations

- The Medical Pack is a **proof**, not a product: 2 entities + 1 taxonomy, no clinical
  workflows (by design — see §26).
- No network-level site/pack/design assignment; the Medical site's preset is set by the
  site-level theme mod.
- The Medical demo site (blog 3) is a **test artefact** created by
  `tests/_phase19-provision.php`; it can be removed without affecting sites 1–2.
- Medical cards intentionally add no CSS; they render with the theme's existing structural
  classes and tokens, so their visual polish is bounded by the shared design system (which is
  the point of the proof, but means Medical is not yet a "designed" pack).
- The preset is a single proof style, not the ~5 per business type the product allows.

---

## 26. Deferred Features

**Medical domain modules (explicitly out of scope per phase §24):** patients, medical records,
prescriptions, insurance, medical billing, hospital/lab/pharmacy management, clinical
workflows, appointment scheduling engine, patient accounts/portal, payment-gateway integration
for medical appointments, medical-specific notifications.

**Platform features:** network-admin provisioning UI, domain assignment UI, pack selector,
network design assignment; pack-contributed preset *sets* (the mechanism exists and is now
proven, so a future Medical design family is a data task, not an architecture task); a
third/fourth pack (RealEstate, Education, Company, Ecommerce, Single Page).

---

## 27. Product Decisions Still Required

1. **Design catalog per business type.** The requirement allows ~5 designs per type. The
   *mechanism* is now proven (Model C, `bb_theme_presets`, one `--bb-*` namespace). The
   **product** must decide whether those 5 styles live in the Theme (shared, recommended) or
   are contributed per Pack, and who selects them.
2. **Who selects the design — network or site?** Today: site, via theme mod. If the platform
   owner must choose on the customer's behalf, that is a Network-layer product decision.
3. **Medical scope.** Which Medical modules (if any) become real: appointments, departments,
   multi-location? None were built.
4. **Business Action / Global CTA.** Still Class D from Phase 18. For Medical the natural
   action is "Book Appointment"; if a business-wide destination is ever authoritative, it
   flows through the existing `header.cta` slot with zero Theme changes.
5. **Ecommerce architecture.** Whether a future Ecommerce pack reuses this exact pack pattern
   or needs genuinely new infrastructure (catalog/cart/checkout).

---

## 28. Recommended Phase 20

The phase's own success criterion is now demonstrably true. The most valuable next step is to
**make the platform's remaining product surfaces real, in this order:**

1. **Phase 20 — NETWORK ADMINISTRATION & SITE PROVISIONING (product-gated).** The one layer
   still missing: create a site → choose business type → choose Pack → assign design. All the
   per-site storage it needs already exists and is proven isolated (§14, §15), so this is
   purely additive: no change to the Core builder, the Theme, or any pack.
2. **Phase 21 — MEDICAL PACK PRODUCTISATION** (or a third pack: RealEstate/Company). The
   platform is proven; if the product wants Medical as a sellable pack, add its real sections
   and its ~5 design styles using the mechanism already validated here.
3. **Phase 21-alt — DESIGN STYLE CATALOG.** Turn the now-proven preset-contribution path into
   a curated catalogue per business type (a data/UX task, not an architecture task).

**Do not** build a Network UI, a second design system, or a Medical-specific anything without
the corresponding product decision.

---

### Closing statement

> **A second business type can be added as an independent Pack without modifying the Core
> Theme or introducing a second design system.**

This is no longer an architectural claim. It is a running, tested, isolated deployment:

```
                    CORE PLATFORM
                         │
          ┌──────────────┼──────────────┐
          │              │              │
       GLOBAL         LAW FIRM       MEDICAL        ← added in Phase 19,
       SECTIONS          PACK           PACK           pack-only change
          │              │              │
          └──────────────┼──────────────┘
                         │
                 CORE BUILDER  (unaware of both packs)
                         │
             ┌───────────┴───────────┐
        SECTION LAYOUT         COMPONENT VARIANT
             └───────────┬───────────┘
                         │
                    CORE THEME  (unaware of both packs)
                         │
                  DESIGN SYSTEM  (one --bb-* namespace)
                         │
             ┌───────────┴───────────┐
     PREDEFINED STYLES        USER CUSTOMIZATION
        (medical-modern)      (overrides the preset)
             └───────────┬───────────┘
                   FINAL WEBSITE
```

The Core Theme, Builder, Section Registry, Variant System, Component System and Design System
**continued to work without becoming Medical-specific.**

**Medical was added. The Core did not change. The Theme did not change.**