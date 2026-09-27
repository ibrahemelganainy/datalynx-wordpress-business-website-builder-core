# PHASE 18 — PLATFORM ARCHITECTURE & PRODUCT BOUNDARY AUDIT

Read-only audit performed **before** any change. Evidence quoted from source.
WordPress Multisite. Site 1 = `datalynx` (Astra, no business type);
Site 2 = `Law Firm Demo` (business-builder theme, `bb_business_type = law_firm`).

Goal of this phase: verify the current architecture can become a **reusable Business
Website Platform** (many business types, ONE core theme, per-type design styles) without
domain leakage, duplicated systems or conflicting configuration layers.

---

## 1. Existing architecture (what actually exists)

### 1.1 Network → Site → Pack layering

| Layer | Implementation | Evidence |
| --- | --- | --- |
| Site/business type | `BusinessType` (option `bb_business_type`, **per site**) | `includes/Settings/BusinessType.php` — registers `law_firm`, `medical`, `real_estate`, `education`, each with a `pack` slug |
| Pack loading | `PackManager` maps `bb_business_type` → pack slug → class → Container → `register()`+`boot()` | `includes/Core/PackManager.php:163-291` |
| Pack registration | **Hardcoded** in `ServiceProvider::register()` | `includes/Core/ServiceProvider.php:296-299` — registers **only** `law_firm` |
| Services | DI `Container` + `ServiceProvider` | `includes/Core/ServiceProvider.php` |

**Finding:** the layering is correct and generic. `BusinessType` already declares four
business types whose packs (`Medical`, `RealEstate`, `Education`) do **not** exist yet. The
only way to add a pack today is to edit `ServiceProvider` (see §5 gap #1).

### 1.2 Section architecture (global vs pack)

- `SectionRegistry` (`includes/Builder/SectionRegistry.php`) is a **generic** registry:
  `register($slug, $args)`, typed `settings`/`content` schemas, `category`, optional
  `render` callback, `get_enabled()`, `get_by_category()`, `get_editor_schema()`.
- `CoreSections` registers the **generic/global** sections under generic categories.
- `LawFirmSections` registers the **domain** sections.
- Both are wired at `init` priority 5 (`ServiceProvider::register_core_sections()`),
  and packs register their own sections in `LawFirmPack::register()` → `SectionManager`.

**The registry cleanly supports "global sections + pack sections" with no core change per
pack** — a pack just calls `$registry->register(...)`.

### 1.3 Theme

- The Theme is a **single core theme** (`themes/business-builder`).
- It contains **zero** references to `LawFirm`, `bb_lawyer`, `bb_legal_service`,
  `bb_practice_area`, `bb_consultation`, pack namespaces or `bb_business_type`
  (verified by a comment-stripped source scan of `inc/`, `template-parts/`, `assets/`).
- Theme APIs consumed by packs: `bb_theme_*` shell-data filters, `bb_component(...)`
  (component API), design tokens.

### 1.4 Design system (Phase 14)

```
tokens.css (defaults)
   ↓
bb_theme_presets()  → bb_theme_get_preset() → bb_theme_preset_config()
   ↓
bb_theme_customization_overrides()   (theme mods, per site)
   ↓
bb_theme_preset_css() → inline :root (front end only)
   ↓
shell / sections / components
```

`bb_theme_presets()`, `bb_theme_active_preset` and `bb_theme_preset_config()` are all
**filters** — so additional presets can be registered without touching the theme.

### 1.5 Section variants (Phase 11), component variants (Phase 12), component catalog (Phase 13)

- `SectionVariants` (plugin) + `bb_register_section_variants` action; safe whitelist resolver.
- Component API (`bb_component`, `bb_render_component`, `bb_component_path` — `inc/components.php`)
  resolves `domain/name/variant` generically and is pack-agnostic.
- Both are **domain-agnostic**; a pack registers its own.

### 1.6 Shell data (Phase 16/17)

- `inc/shell-data.php` (theme) exposes `bb_theme_shell_header_data`,
  `bb_theme_shell_footer_data`, `bb_theme_shell_nav_location` filters, empty-safe helpers.
- `includes/Theme/theme-shell-data.php` (plugin) bridges `bb_site_settings` → those filters.
- Phase 17 concluded no pack shell provider is justified yet.

---

## 2. Ownership model (verified)

```
NETWORK / MULTISITE      → site creation, domain (WordPress native; no BB network layer yet)
        ↓
SITE / BUSINESS TYPE     → bb_business_type (per-site option)                     [Plugin]
        ↓
BUSINESS PACK            → domain concepts, sections, components, data models      [Pack]
        ↓
CORE PLUGIN / BUILDER    → registry, renderer, page manager, payments, settings    [Plugin]
        ↓
CORE THEME               → presentation, shell, variants, components API           [Theme]
        ↓
DESIGN SYSTEM            → tokens, presets, user overrides                          [Theme]
        ↓
FINAL PRESENTATION
```

Dependency direction **verified correct**: Pack → Plugin → Theme. The Theme never
references a pack or business concept.

---

## 3. Required architecture matrix

| Capability | Network | Site | Plugin | Theme | Pack | Page Builder |
| --- | --- | --- | --- |
| Business Type | — | ● (option) | owner of registry | — | — | — |
| Domain | ● (WP native) | ● | — | — | — | — |
| Pack | — | ● (via type) | owner (PackManager) | — | ● (implements) | — |
| Design Style (preset) | — | ● (theme mod) | — | **owner** | may *extend* via filter | — |
| Colors/Typography/Spacing/Radius | — | ● (theme mods) | — | **owner** | — | — |
| Sections | — | — | **registry owner** | — | registers domain sections | composes |
| Section Layout (variant) | — | ● (per section setting) | owner (SectionVariants) | — | registers domain variants | selects |
| Component Variant | — | ● (per card) | owner (component API in theme) | **owner** | ships domain cards | selects |
| Business Data | — | ● (per site) | generic infra (payments/notifications) | — | **owner** | — |
| Global CTA | — | — | — | presentation slot only | — | — (page CTA owner) |
| Page CTA | — | — | — | presentation | — | **owner** |
| Header | — | ● (variant theme mod) | — | **owner** | — | — |
| Footer | — | ● (variant theme mod) | — | **owner** | — | — |

Every row has exactly one owner (or a documented split for "Design Style" and "Sections").

---

## 4. Global vs pack sections

**Global (CoreSections):** `header`, `hero`, `about`, `features`, `cta`, `slider`,
`footer`, `contact` — categories `content`/`marketing`/`navigation`/`communication`.

**Pack (LawFirmSections):** `lawyers`, `legal_services`, `practice_areas`, `testimonials`,
`faq`, `consultation`, `booking`, `status_lookup` — category `law-firm`.

**Observation (not a defect):** `testimonials` and `faq` are registered under the
`law-firm` category although they are generic in nature. A future pack would have to
re-register equivalents. This is a **naming/placement observation**, not a blocker; the
registry already supports any pack registering these under a generic category.

**Conclusion: the registry already supports the required model with no core change.**

---

## 5. Problems / gaps found

### Gap #1 (Class B) — no pack-registration extension point

`ServiceProvider::register()` hardcodes exactly one pack:

```php
$this->pack_manager->register( 'law_firm', 'BusinessBuilderCore\\Packs\\LawFirm\\LawFirmPack' );
```

`BusinessType` already declares `medical`, `real_estate`, `education` (with pack slugs), so
the platform's core requirement — *"a site's business type determines its pack"* — cannot be
satisfied for any pack beyond LawFirm without **editing core plugin code**.

This is a **small, clearly-defined missing extension point** that would otherwise block the
product architecture. A single filter (`bb_register_packs`) lets a pack (or a child plugin)
self-register, exactly like the existing `bb_register_section_variants`,
`bb_register_payment_gateways` and `bb_register_notification_channels` hooks.

### Observation #2 — two "style" concepts share names (not a defect)

- Theme preset (`bb_theme_preset`, theme mod): `default` / `modern` / `luxury` — **visual
  identity** of the whole site.
- LawFirm page template (`_bb_page_template`, per page meta): `classic` / `modern` / `luxury`
  (pack `Templates`), applied as a wrapper class per page by `SectionRenderer`.

Both exist, are owned by different layers, and operate at different scopes (site vs page).
**They are independent and correct**, but the overlapping names (`modern`, `luxury`) invite
confusion. Not a blocker; documented for future naming discipline.

### Observation #3 — no network-admin layer yet

There is **no** BB network-administration UI (no `network_admin_menu`, no site-creation
flow, no business-type assignment screen). Business type is a per-site option currently set
(by the audit environment) directly. This is a **product requirement not yet defined**
(Class D) — do not implement.

---

## 6. Design style / preset architecture — the key product question

Requirement: each business type may have ~5 predefined designs, applied to the **one** core
theme, plus full user customization.

Audit result:

- The preset system is a **filter-based registry** (`bb_theme_presets`,
  `bb_theme_preset_config`, `bb_theme_active_preset`). It therefore **already supports**
  Model B/C (global + pack-provided presets) **without any theme change**.
- Presets and section layouts are already independent (a preset never selects a layout).
- A pack that wants its own design styles can `add_filter('bb_theme_presets', ...)` and
  `bb_theme_preset_config` — no theme modification, no second design system.
- **No pack currently extends presets** (LawFirm does not), so the capability is unused but
  present.

**Selected model: Model C (Theme = design framework; packs *may* contribute presets via the
existing theme filters).** Rationale: single design system, correct dependency direction
(Pack → Theme API), Multisite isolation (theme mods per site), backward compatible (default
preset always present), no duplicate token namespace.

Not implemented in this phase: no pack-specific presets are shipped (the audit found no
current requirement), and no preset architecture change is needed.

---

## 7. Design Style vs Section Layout vs Component Variant (independence check)

| Layer | Owner | Storage | Evidence |
| --- | --- | --- | --- |
| Design Style (preset) | Theme | theme mod `bb_theme_preset` | `preset-resolver.php` |
| Section Layout (variant) | Plugin registry | per-section setting `variant` | `SectionVariants`, builder schema |
| Component Variant | Theme component API | per-item arg | `bb_render_component($c,$args,$variant)` |

They are **independent**: selecting a preset does not change a section's variant, and a
section variant does not change a card variant. Verified by the Phase 11/12/13/14 suites
(all green). The required combination
`LawFirm → Luxury Style → Lawyers Section → Featured Layout → Compact Card` is therefore
expressible today.

---

## 8. Multisite / isolation

All configuration is **per-site**: `bb_business_type` (option), `bb_site_settings` (option),
`bb_theme_preset` + `bb_design_*` + `bb_theme_shell_*` (theme mods), builder data
(`_bb_page_sections`, `_bb_page_template` — post meta), business data (pack post types/meta).
No network/global storage is used. Isolation verified in Phases 14–17.

---

## 9. Network vs site administration

- **Site admin** already owns: design style (preset), colors/typography/spacing/radius
  (Customizer), header/footer shell variants, sections, layouts, component variants, content
  — via the Customizer + Page Builder UI.
- **Network admin** owns nothing BB-specific yet. §3 "site type / pack / domain" management is
  **not implemented** (Class D — product requirement not yet defined).

No dashboard is implemented in this phase.

---

## 10. Global CTA / business action

Re-confirmed (Phase 18-lite): no authoritative business-wide destination exists. Page-level
CTAs are owned by the Page Builder; the `header.cta` slot exists and is empty-safe.
**Decision: Class D — do not implement.**

---

## 11. Future pack compatibility (conceptual)

| Pack | Domain concepts | Generic sections reused | Needs new infra? |
| --- | --- | --- | --- |
| LawFirm | lawyers, practice areas, legal services, consultation, booking | header/hero/about/features/cta/contact/footer/slider | No (reference) |
| Medical | doctors, departments, medical services, appointments | same generic set | Section registration only |
| Doctor/Clinic | doctors, clinic info, visits | same | Section registration only |
| Company | team, departments, products/services | same | Section registration only |
| Real Estate | properties, agents, viewings | same | Section registration only |
| Education | courses, teachers, programs | same | Section registration only |
| Ecommerce | products, categories, cart | same | Would need real business infra (out of scope) |
| Single Page | one page, anchors | same | No |

**Every pack can be added by (a) registering its pack class and (b) registering its
sections/components — with the current architecture, once Gap #1 is closed.**

---

## 12. Decision gates

| Area | Class | Decision |
| --- | --- | --- |
| Business type registry | A (correct) | No change |
| Pack loading / isolation | A (correct) | No change |
| **Pack self-registration** | **B (missing hook)** | **Add `bb_register_packs` filter (small, backward-compatible)** |
| **Core renderer domain leakage** | **C (architectural conflict)** | **Replace hardcoded slug list with generic `bb_render_section` filter** |
| Section registry / global vs pack | A (correct) | No change |
| Theme domain-agnosticism | A (correct) | No change |
| Design system cascade | A (correct) | No change |
| Design presets extensibility | A (correct — filterable) | No change; document Model C |
| Section variants | A (correct) | No change |
| Component API / variants / catalog | A (correct) | No change |
| Shell data | A (correct) | No change |
| Multisite isolation | A (correct) | No change |
| Network admin layer | D (undefined) | Do not implement |
| Global CTA / business action | D (undefined) | Do not implement |
| Pack-specific design presets | D (undefined) | Do not implement (mechanism already exists) |
| Naming overlap (page template vs preset) | B (observation) | Document only; no code change |

---

## 13. Implementation decision

**Implement two things, both small and backward-compatible:**

1. the minimal **`bb_register_packs`** extension point, so a pack can register itself
   without editing core plugin code — the single blocker to the platform vision
   ("one core, many business types/packs");
2. removal of the **hardcoded LawFirm slug list** in the core `SectionRenderer` (see §5
   Gap #3 below), replaced by a generic `bb_render_section` filter.

Everything else is already correct or a product requirement not yet defined.

### Gap #3 (Class C) — core Builder contained a hardcoded LawFirm slug list

> **Discovered during the Phase-18 re-audit of the actual source.** The earlier revision of
> this document marked the core renderer as "A (correct)". Reading the real code shows it
> was not: `SectionRenderer::render_generic()` name-matched five pack slugs.

`includes/Builder/SectionRenderer.php` (before the fix) contained, inside the **generic**
core renderer:

```php
if (
    in_array(
        $type,
        array(
            'lawyers',
            'legal_services',
            'practice_areas',
            'testimonials',
            'faq',
        ),
        true
    )
) {
    do_action( 'bb_render_section_' . $type, $section, $settings, $content );
    return;
}
```

This is **domain leakage into Core**, exactly the condition §11 of the phase forbids:

- The Core Plugin must not know a `lawyers` / `legal_services` / `practice_areas` concept —
  those are LawFirm domain nouns.
- A future pack section (e.g. Medical `doctors`) would fall through to the generic content
  renderer and render **nothing**, so each new pack would require editing core — the same
  class of blocker as Gap #1, but in the render path.
- It also mixed two incompatible mechanisms: pack sections were dispatched by hardcoded
  slug list, while `consultation`, `booking` and `status_lookup` correctly used the
  per-section `render` callback. One pack, two dispatch mechanisms.

**Surgical correction (implemented):** the slug list was replaced by a single generic,
type-agnostic filter. Core no longer names any business concept; the *pack* owns its own
slug matching:

```php
// Core (SectionRenderer) — knows no business type.
$claimed = apply_filters( 'bb_render_section', false, $type, $section, $settings, $content );
if ( true === $claimed ) {
    return;
}

// Pack (LawFirmSections) — owns the domain knowledge.
add_filter( 'bb_render_section', array( $this, 'render_section' ), 10, 5 );
// render_section() switches on 'lawyers' | 'legal_services' | ... and returns true.
```

This follows the **existing** self-registration pattern of the platform
(`bb_register_packs`, `bb_register_section_variants`, `bb_register_payment_gateways`), so
it introduces no new concept: a pack claims a section type by returning `true`; an
unclaimed type falls through to core's generic renderer (unchanged legacy behaviour).

**Evidence the defect was real, not theoretical:** a container-wide search for
`bb_render_section_(lawyers|legal_services|practice_areas|testimonials|faq)` returned
results in **exactly two files** — the core renderer (the hardcoded list) and the LawFirm
pack (a matching `add_action` per type). No other pack, theme or test depended on those
five hooks, so the correction is confined to those two files.

### Proposed file impact

**CREATED**
```
tests/runtime-phase18-platform-architecture.php
docs/phase-18-platform-architecture-audit.md
docs/phase-18-platform-architecture-final-report.md
```

**MODIFIED**
```
includes/Core/ServiceProvider.php            — fire bb_register_packs($pack_manager)
includes/Builder/SectionRenderer.php         — remove hardcoded LawFirm slug list,
                                               add generic bb_render_section filter
packs/LawFirm/Sections/LawFirmSections.php   — claim the pack's types via bb_render_section
```

**MUST NOT TOUCH**
Theme design schema / tokens / presets / shell resolver / shell templates; SectionRegistry,
SectionRenderer, PageBuilderAjax, PageManager; LawFirm sections/components/variants; payment,
consultation, appointment, notification, activity, dashboard systems; Phase 16/17 shell-data
bridge; `BusinessType`; `SiteSettings`.

### Exact change specification

1. **Owner:** Core plugin (`ServiceProvider`).
2. **Data model:** none (a hook, not storage).
3. **Storage:** none.
4. **Contract:** `do_action('bb_register_packs', PackManager $pack_manager)` — a pack calls
   `$pack_manager->register($business_type_slug, $pack_class)`; slug must match a
   `BusinessType` slug and the class must exist (enforced inside `PackManager::register()`).
5. **Consumers:** packs / child plugins.
6. **Sanitization:** unchanged — `PackManager::register()` already `sanitize_key()`s and
   `class_exists()`-checks.
7. **Fallback:** if no pack registers, behaviour is identical to today (law_firm only).
8. **Multisite:** unchanged (per-site `bb_business_type`).
9. **Files changed:** `includes/Core/ServiceProvider.php` only.
10. **Files not changed:** everything else (see above).

---

## 14. Recommended next phase

**Phase 19 — PACK SCAFFOLDING & BUSINESS-TYPE PROVISIONING (product-gated).** With
`bb_register_packs` in place, the next natural step is either (a) a real second pack
(Medical/Dentist) built purely by registering a pack + its sections (proving the platform
thesis), or (b) the network-admin site-type/pack provisioning flow — **whichever the product
confirms first**. Pack-specific design presets can follow using the existing
`bb_theme_presets` filter once a pack actually needs them.

Audit complete. **Implementation scope: 1 core file, 1 hook.**