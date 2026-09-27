# PHASE 19 — MEDICAL PACK AUDIT (PRE-IMPLEMENTATION)

Read-only audit performed **before** writing any Medical code. Every claim below is quoted
from the actual repository (file + line), not inferred from filenames.

Environment: WordPress Multisite — site 1 = `datalynx` (`http://builder.test/`, Astra,
no business type), site 2 = `Law Firm Demo` (`http://lawfirm.builder.test/`,
`business-builder`, `bb_business_type = law_firm`).

Purpose of Phase 19: prove that a genuinely different business type (**Medical**) can be
added as an independent Pack **without modifying the Core Theme and without introducing a
second design system**.

---

## 0. Audit verdict (up front)

| Question | Answer |
| --- | --- |
| Can a new business type be added without editing Core? | **YES** — `bb_register_packs` |
| Can it render its own sections without editing Core? | **YES** — `bb_render_section` (Phase 18) |
| Can it ship cards without editing the Theme? | **YES** — `bb_component_roots` |
| Can it ship section layouts without editing Core/Theme? | **YES** — `bb_register_section_variants` |
| Can it contribute a design style on the SAME token system? | **YES** — `bb_theme_presets` / `bb_theme_preset_config` |
| Does any of this require a Core or Theme edit? | **NO** |

**No architectural conflict found. Proceed with implementation.**

---

## 1. `BusinessType` — the business-type registry

`includes/Settings/BusinessType.php`

- Option name: `bb_business_type` (per site) — `private const OPTION_NAME` (line 14).
- `register_default_types()` (line 33) already declares **`medical`**:

```php
$this->register(
    'medical',
    array(
        'name'        => 'Medical',
        'label'       => 'Medical',
        'description' => 'Website for doctors, clinics and medical businesses.',
        'pack'        => 'Medical',
        'enabled'     => true,
    )
);
```

- Public API: `register()`, `get_all()`, `get($slug)`, `exists($slug)`, `get_current()`,
  `set_current($slug)`, `get_current_config()`, `get_option_name()`.
- `set_current()` **rejects** any slug not registered via `exists()` (line 171).

**Consequence:** the `medical` business type already exists and already maps to a pack slug
`Medical`. **No Core change is needed to declare the business type.** The Pet/`medical`
type is currently a *dangling* declaration — the pack class does not exist yet, so
`PackManager::boot()` returns `false` and the site stays inert. Phase 19 supplies that class.

**Discrepancy found and documented (not a defect):** `BusinessType` declares the pack as the
string `'Medical'` (a *label*), while `PackManager` is keyed by the **business-type slug**
(`medical`). The core registers `law_firm` → class; so Medical must be registered under the
**slug** `medical`, not the label `Medical`. The pre-existing `'pack' => 'Medical'` array key
is unused by `PackManager` (verified: `PackManager` never reads `$type['pack']`). Documented
as an observation; the correct registration key is the business-type slug.

---

## 2. `PackManager` — the pack registry + lifecycle

`includes/Core/PackManager.php`

| Method | Behaviour (verified) |
| --- | --- |
| `register( $slug, $class )` (61) | `sanitize_key()` on slug; rejects empty slug/class; requires `class_exists()`; stores `packs[$slug] = $class` |
| `boot_current()` (163) | reads `BusinessType::get_current()`; returns early when empty; else `boot($current_type)` |
| `boot( $slug )` (187) | requires `exists()`; **idempotent** (`instances[$slug]` guard, line 206); instantiates via the DI `Container`; then calls `register()` then `boot()` if the methods exist |
| `get_all()/get()/exists()/get_instance()` | read accessors |

**Consequence:** Medical needs **only** a class whose name it registers. Core does not need
to know Medical exists. The lifecycle (`register()` then `boot()`) is the pack contract.

---

## 3. `bb_register_packs` — the pack self-registration extension point

`includes/Core/ServiceProvider.php:296-318`

```php
$this->pack_manager->register(
    'law_firm',
    'BusinessBuilderCore\\Packs\\LawFirm\\LawFirmPack'
);

do_action( 'bb_register_packs', $this->pack_manager );
```

**Consequence:** a Pack (or companion plugin) registers itself with:

```php
add_action( 'bb_register_packs', function ( $pack_manager ) {
    $pack_manager->register( 'medical', 'BusinessBuilderCore\\Packs\\Medical\\MedicalPack' );
} );
```

The Core fires the hook generically. **Core must not be edited to recognise Medical.**

---

## 4. `Autoloader` — namespace → path resolution

`includes/Core/Autoloader.php`

- Root prefix `BusinessBuilderCore\` (line 14).
- Pack branch (line 104): any class starting `Packs\` is resolved to
  `<plugin>/packs/<relative path>.php`.

**Consequence:** `BusinessBuilderCore\Packs\Medical\MedicalPack` resolves automatically to
`packs/Medical/MedicalPack.php`, and nested namespaces (e.g.
`Packs\Medical\Sections\MedicalSections`) to `packs/Medical/Sections/MedicalSections.php`.
**No autoloader registration needed.** A new pack is drop-in by convention.

---

## 5. `SectionRegistry` — the single section registry

`includes/Builder/SectionRegistry.php`

- `register( $slug, $args )` (19): `sanitize_key()`; defaults for `name/label/description/
  category/icon/supports/settings/content/render/enabled`; normalises the `settings` and
  `content` schemas into typed field descriptors (`normalize_schema`, 105); backward-
  compatible with bare scalar defaults.
- Accessors: `exists()`, `get()`, `get_all()`, `get_enabled()`, `get_by_category()`,
  `get_categories()`, `get_content_schema()`, `get_settings_schema()`, `get_editor_schema()`.

**Consequence:** Medical registers its sections into the **same** registry. There is no
`MedicalSectionRegistry`, and none is needed.

---

## 6. `SectionRenderer` — the generic renderer + `bb_render_section`

`includes/Builder/SectionRenderer.php`

- `render_page()` reads `_bb_page_template` (page meta) and `_bb_page_sections` (page meta),
  emitting a `.bb-template` wrapper.
- `render_section()` (76): resolves the registry entry by type, skips disabled sections,
  builds classes (`bb-section bb-section-{type}`) + `data-section-id` / `data-section-type`
  attributes (filterable via `bb_section_classes` / `bb_section_attributes`), then:
  1. uses the section's own `render` callback when registered, else
  2. calls `render_generic()`.
- `render_generic()` (228) dispatches the **global** types by name (`hero`, `header`,
  `about`, `features`, `cta`, `contact`, `slider`, `footer`) — these are Core-owned global
  sections. It then runs the **generic pack claim** (Phase 18):

```php
$claimed = apply_filters( 'bb_render_section', false, $type, $section, $settings, $content );
if ( true === $claimed ) {
    return;
}
```

…and otherwise falls through to generic content rendering (`title`/`description`/`items`).

**Consequence:** Medical claims `doctors` and `medical_services` by returning `true` from
`bb_render_section`. **The Core renderer must remain unaware of Medical.** (Phase 18 removed
the previous hardcoded LawFirm slug list; Phase 19 must not reintroduce one for Medical.)

---

## 7. `SectionVariants` — the section-layout registry

`includes/Builder/SectionVariants.php` + public API `includes/Builder/section-variants.php`

- `register( $section_type, $variant, $template )` (48): sanitises both slugs, requires
  `is_readable( $template )`.
- `register_many( $section_type, array $variants )` (72).
- `available()` (85) always includes `default`; `has_variants()` (106).
- `resolve()` (121) is **whitelist-based** — an unregistered/`../../etc` request always
  resolves to `default`; the requested value is **never** used as a path.
- `template()` (148) returns only a **registered** absolute path.
- Hook: `do_action( 'bb_register_section_variants', $registry )` (section-variants.php:55).
- `bb_render_section_variant( $type, $requested, $args )` (140) resolves then `include`s the
  template with `$args` in scope. It returns `false` when the component API is absent, so the
  caller's own default path runs instead of fataling.
- `bb_section_variant_options( $type )` (88) returns `[]` unless ≥2 variants exist — so the
  builder never shows a pointless single-choice dropdown. Labels are filterable via
  `bb_section_variant_labels`.

**Consequence:** Medical registers its own layouts (`doctors/featured`, etc.) through the
same registry, with the same traversal safety. The documented data contract is
**prepared data only — variant templates never query the database**.

---

## 8. Component API — `bb_component_roots` and the resolver

Theme: `themes/business-builder/inc/components.php`

- `bb_component_roots()` (approximately line 33): roots array seeded with
  `BB_THEME_PATH . 'templates/components'`, then `apply_filters( 'bb_component_roots', $roots )`.
  **Later roots are consulted only when an earlier root does not have the file.**
- `bb_component_path( $component, $variant )`: strips `..`, requires
  `#^[a-z0-9_\-]+(/[a-z0-9_\-]+)?$#`, builds candidates `{group}/{name}-{variant}.php` then
  `{group}/{name}.php`, returns the first readable path.
- `bb_render_component( $component, $args, $variant )`: renders in an output buffer with
  `$args` scoped to the include; fires `bb_before_component` / `bb_after_component`; returns
  `''` (plus an HTML comment when `WP_DEBUG`) for an unknown component instead of fataling.
- `bb_component()` = echo wrapper.

**Consequence:** Medical registers its component directory via `bb_component_roots` and ships
`doctor/card.php`, `doctor/card-compact.php`, … The resolver is **not modified**.

---

## 9. Theme design system — presets, tokens, customization

- `bb_theme_presets()` returns `default` / `modern` / `luxury`, each a map of **existing**
  `--bb-*` tokens (`--bb-color-primary`, `--bb-color-accent`, `--bb-radius-md`,
  `--bb-header-bg`, …), then `apply_filters( 'bb_theme_presets', $presets )`.
- `bb_theme_get_preset()` reads theme mod `bb_theme_preset`, applies
  `bb_theme_active_preset`, then **validates against the registry and falls back to
  `default`** — so a filter can never introduce an unknown preset.
- `bb_theme_preset_config()` merges `bb_theme_customization_overrides()` **over** the preset
  values (user customization wins), then `apply_filters( 'bb_theme_preset_config', … )`.
- `bb_theme_preset_css()` emits the merged tokens as an inline `:root` block, skipping
  non-`--bb-` keys, empty values and values containing `{`, `}`, `<`.

**Consequence:** a Medical preset is a **filter contribution** using the **same `--bb-*`
namespace** and the **same resolver**. No second token system, no second CSS variable
namespace, no second preset resolver, no Theme→Medical dependency, no duplicate
customization logic. Because `bb_theme_preset_config()` merges user overrides last, a user
customization **still overrides the Medical preset** — the critical Phase 19 test.

Customization: `bb_theme_customization_overrides()` (customization.php:35) ends with
`apply_filters( 'bb_theme_customization_overrides', $overrides )` (line 69) — the documented
seam a pack could use, though Medical does **not** need it (it ships a preset, not controls).
`bb_theme_design_sections()` + `design-schema.php` define **28** token controls; Medical adds
**none**, because it reuses those tokens.

---

## 10. Theme shell variants

`themes/business-builder/inc/shell-variants.php`

`bb_theme_shell_parts()` (45), `bb_theme_shell_variants()` (73), `bb_theme_shell_resolve()`
(199, whitelist + fallback), `bb_theme_shell_template()` (222), `bb_theme_shell_options()`
(248), and `bb_theme_shell_register( $part, $slug, $meta )` (275) — a pack **can** register a
shell variant if it wants one.

**Consequence:** Medical needs **no** shell variant for this proof. Header/footer remain
Theme-owned and shared, selected per site via theme mods. Shell variants are **not**
domain-specific; Medical reuses them unchanged.

---

## 11. Theme domain-agnosticism (baseline verified)

A comment-stripped scan of the whole theme for `LawFirm`, `bb_lawyer`, `bb_legal_service`,
`bb_practice_area`, `bb_consultation`, `bb_business_type`, `Packs\` returns **0 hits** (Phase
18 suite: `PASS — Theme source has NO pack/business coupling`).

**Consequence:** adding Medical must keep this at 0 hits. Medical names must never appear in
the theme. **The theme should require zero edits for Phase 19.**

---

## 12. LawFirm Pack structure (reference, not a template to copy blindly)

```
packs/LawFirm/
├── config.php                    (name/slug metadata)
├── LawFirmPack.php               (DI container + register()/boot())
├── PostTypes/                    Lawyer, LegalService, Testimonial, Faq, Consultation (+ *Fields)
├── Taxonomies/                   PracticeArea, FaqCategory (+ *Fields)
├── Sections/
│   ├── LawFirmSections.php       (schema registration + render callbacks + bb_render_section claim)
│   ├── LawFirmQueries.php        (WP_Query centralisation — query lives HERE, not in templates)
│   ├── components/<group>/card[-variant].php
│   └── variants/<section>/<layout>.php
├── Templates/                    Classic / Modern / Luxury (page-template wrapper classes)
├── Frontend/ Admin/ Payments/ Appointments/ Starter/
```

Verified dispatch mechanisms in `LawFirmSections::register()`:

1. **`render` callback** — `consultation`, `booking`, `status_lookup` declare their renderer
   inline in the registry entry; Core never learns the type.
2. **`bb_render_section` claim** — `lawyers`, `legal_services`, `practice_areas`,
   `testimonials`, `faq` (Phase 18 replaced five hardcoded Core hooks with this one filter).

Both are legitimate; Medical uses mechanism 2 (plus the registry `render` callback where a
form/subtree is involved — not needed in this proof).

**Packaging decision for Medical:** LawFirm's `PostTypes/` + `*Fields` split (a class per
entity plus a separate metabox class) is ~300 + ~400 lines per entity. For a **proof** pack
with a deliberately minimal domain model, Medical uses **one class per entity** that both
registers the post type and its (small) admin fields. This is a *smaller structure*, not a
*second architecture*: same `Container` resolution, same `SectionRegistry`, same
`bb_render_section` claim, same component/variant/via filters.

---

## 13. Pack activation flow (verified end to end)

```
wp-admin → Business Builder Settings (SiteSettingsPage)
  → $_POST['business_type'] → sanitize_key() → BusinessType::set_current()
  → update_option( 'bb_business_type', $slug )              [per site]
        ↓ (next request)
Plugin::boot → ServiceProvider::register()
  → PackManager::register( 'law_firm', … )  +  do_action( 'bb_register_packs', $pack_manager )
  → ServiceProvider::boot() → PackManager::boot_current()
  → BusinessType::get_current() → 'medical'
  → PackManager::boot( 'medical' ) → Container::make( MedicalPack )
  → $pack->register(); $pack->boot();
```

**Consequence:** selecting `Medical` on a site is an **existing admin action** — no new UI is
required for Phase 19. The `SiteSettingsPage` select already renders every registered
business type (`$types` loop, line 126), so `Medical` appears automatically once its pack
registers; note the business type is *already declared*, so it appears today, and simply
becomes *functional* once the pack exists.

---

## 14. Page Builder schema + sanitisation

- `SectionRegistry::normalize_schema()` gives every field a typed descriptor with `default`,
  `options`, `required`, `min/max/step`, `rows`, `multiple`, `fields` (nested repeaters).
- `LawFirmSections::variant_setting()` and `card_variant_setting()` derive their `options`
  from the live registries — so a pack never hand-maintains a stale list, and a mis-declared
  option is filtered out by `bb_component_path()` returning `''`.
- `SectionRenderer` whitelists nothing itself; the **resolver** (`SectionVariants::resolve`)
  and the **component resolver** (`bb_component_path`) are the enforcement points, and both
  fall back safely.

**Consequence:** Medical uses the same schema shapes (`select`, `repeater`, `text`,
`textarea`, `image`, `checkbox`, `number`) and the existing sanitisation. **No builder change.**

---

## 15. Existing test harness conventions

`tests/runtime-*.php`, executed with `C:\MAMP\bin\php\php8.2.14\php.exe`.

Standard shape:

```php
define( 'WP_USE_THEMES', false );
require 'c:/MAMP/htdocs/wordpress/wp-load.php';
function check( string $label, bool $ok ): void {
    echo ( $ok ? 'PASS' : 'FAIL' ) . ' — ' . $label . PHP_EOL;
}
```

- Multisite suites use `switch_to_blog()` / `restore_current_blog()`.
- Theme-dependent suites `switch_theme()` + `require_once get_template_directory() . '/functions.php'`,
  or load the theme `inc/*.php` layer list directly (as `runtime-phase18-…` does).
- Suites end with `DONE`; results are asserted by counting `PASS` / `FAIL`.

**Consequence:** the Phase 19 suite follows the same shape (plus `switch_to_blog` isolation
assertions), so it drops into the existing harness with no runner change.

---

## 16. Extension points required by Medical (the complete list)

| # | Extension point | Owner | Medical use |
| --- | --- | --- | --- |
| 1 | `bb_register_packs` | Core plugin | register `medical` → `MedicalPack` |
| 2 | `BusinessType` (`medical` already declared) | Core plugin | read-only — no change |
| 3 | `PackManager` lifecycle (`register()`/`boot()`) | Core plugin | implemented by `MedicalPack` |
| 4 | `Autoloader` `Packs\` convention | Core plugin | drop-in path resolution |
| 5 | `Container` DI | Core plugin | constructor dependencies resolved automatically |
| 6 | `SectionRegistry::register()` | Core plugin | `doctors`, `medical_services` |
| 7 | `bb_render_section` | Core plugin | claim both types |
| 8 | `bb_register_section_variants` + `SectionVariants` | Core plugin | `doctors` layouts |
| 9 | `bb_component_roots` | Theme | Medical component directory |
| 10 | `bb_component` / `bb_render_component` | Theme | render cards + variants |
| 11 | `bb_theme_presets` | Theme | the Medical design preset |
| 12 | `bb_theme_preset_config` | Theme | (optional) preset refinement |
| 13 | Theme token namespace `--bb-*` | Theme | preset values only |
| 14 | `bb_theme_customization_overrides` | Theme | **not needed** (documented seam) |
| 15 | Shell variants | Theme | **not needed** (reused as-is) |
| 16 | `get_terms` / meta keys (pack-owned) | WordPress | specialty taxonomy, entity meta |
| 17 | `register_post_type` (pack-owned prefixes `bb_`) | WordPress | `bb_doctor`, `bb_medical_service`, `bb_specialty` |
| 18 | `SiteSettingsPage` business-type select | Core plugin | **existing** selection UI |
| 19 | `SectionRenderer` page/settings contract | Core plugin | `_bb_page_sections` / `_bb_page_template` |

**All 19 already exist. None requires modification.**

---

## 17. Files that MUST remain untouched

```
themes/business-builder/**                      (entire theme — zero edits)
includes/Core/ServiceProvider.php               (already fires bb_register_packs)
includes/Core/PackManager.php
includes/Core/Autoloader.php
includes/Core/Container.php
includes/Builder/SectionRegistry.php
includes/Builder/SectionRenderer.php            (must stay Medical-unaware)
includes/Builder/SectionVariants.php
includes/Builder/section-variants.php
includes/Builder/CoreSections.php
includes/Builder/PageManager.php
includes/Builder/Renderer.php
includes/Admin/**  includes/Settings/**  includes/REST/**
includes/Core/Payments/**  includes/Core/Notifications/**  includes/Core/Audit/**
packs/LawFirm/**
assets/**
```

Medical is added **only** under `packs/Medical/` (+ one test file + two docs).

---

## 18. New Medical files required

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
tests/runtime-phase19-medical-pack.php
docs/phase-19-medical-pack-audit.md            (this file)
docs/phase-19-medical-pack-final-report.md
```

---

## 19. Architectural gaps discovered

**None blocking.** Two non-blocking observations are recorded:

1. **`BusinessType['pack']` is decorative.** `BusinessType` declares `'pack' => 'Medical'`
   (a label), but `PackManager` is keyed by the **slug** (`medical`). The array key is never
   read by `PackManager` (verified). Correct registration key = business-type slug. Not worth
   a Core change in this phase; documented for naming discipline.
2. **No network-level design/pack assignment.** Phase 18 already classified this Class D
   (product requirement not yet defined). Phase 19 uses the existing **site-level** preset
   mechanism and documents network assignment as deferred.

---

## 20. Implementation plan (minimum required)

1. `MedicalPack` — DI constructor, `register()` (post types, taxonomy, sections, component
   root, preset contribution, `bb_render_section` claim), `boot()` (single rewrite flush for
   the pack's pretty permalinks, mirroring LawFirm's self-healing flush).
2. Register the pack through **`bb_register_packs`** only.
3. Two domain entities (`bb_doctor`, `bb_medical_service`) + one taxonomy (`bb_specialty`),
   minimal fields (name, specialty, photo, short description, optional URL).
4. Two sections (`doctors`, `medical_services`) registered in the **existing**
   `SectionRegistry`, claimed and rendered via **`bb_render_section`**.
5. Components `doctor/card` (+ `compact`, `horizontal`) and `medical-service/card`
   (+ `featured`) — prepared args only, no queries, `--bb-*` tokens, RTL-safe.
6. Section variants for `doctors` (`default`, `grid`, `list`) proving prepared-data contract.
7. One **proof preset** (`medical-modern`) using only `--bb-*` tokens — no `--medical-*`.
8. A temporary **Medical test site** in Multisite (created and removed by the test, with the
   LawFirm site and site 1 left untouched).
9. The Phase 19 runtime suite: Core, Medical, Design, LawFirm regression, Theme
   domain-agnosticism, Multisite isolation.
10. Live browser verification on a Medical site + LawFirm regression on site 2.

**Explicitly NOT implemented:** patients, records, prescriptions, insurance, billing, hospital
management, appointment engine, patient portal, scheduling, network admin, a second design
system, a Medical builder/customizer/registry.

Audit complete. **Proceed to implementation.**