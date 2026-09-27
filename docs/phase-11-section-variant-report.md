# PHASE 11 — SECTION VARIANT ARCHITECTURE — FINAL REPORT

## A. Audit Before Changes

Full audit in `docs/phase-11-section-variant-audit.md` (written before any change).

- **Registry:** `SectionRegistry::register($slug, $args)` with a typed `settings` schema.
- **Data flow (verified):** `SectionRenderer::render_section()` → `$config['render']($section,$settings,$content)`
  → `LawFirmQueries` (**one query**) → per-item prep → `bb_component('<domain>/card', …)`.
- **Config storage:** per-page post meta `_bb_page_sections` (`{type,id,settings,content}`);
  the editor schema is JS-rendered from `get_editor_schema()`.
- **KEY FINDING:** the `select` sanitizer in `PageBuilderAjax::sanitize_single_value()`
  (lines 1266-1316) **already enforces the declared option set** — so a `variant` setting of
  `type=select`+`options` is whitelist-enforced, auto-rendered and auto-sanitized with **zero
  builder changes**.
- **Variant-like precedents:** `layout` (slider/header) and `columns` are existing
  settings-driven layout controls; `_bb_page_template` is a **page-level** preset (left
  independent — §52); the Phase-10 component `variant` arg is a different concept (§14).
- **JS:** nothing binds to card/section wrappers (JS uses `data-bb-*` + IDs); the builder JS
  uses `data-section-id`/`data-section-type` on the wrapper, which are untouched.
- **Hooks:** `bb_section_classes`/`bb_section_attributes` stay on the wrapper.

## B. Variant Inventory (only what was actually implemented)

| Section | Variant | Purpose | Owner |
| --- | --- | --- | --- |
| lawyers | `default` | Existing columns grid (unchanged) | LawFirm |
| lawyers | `list` | Single-column list; card goes row (media beside copy) | LawFirm |
| lawyers | `featured` | First item full-width featured card, rest in grid | LawFirm |
| legal_services | `default` | Existing columns grid (unchanged) | LawFirm |
| legal_services | `list` | Single-column list | LawFirm |
| practice_areas | `default` | Existing columns grid (unchanged) | LawFirm |
| practice_areas | `list` | Single-column list | LawFirm |
| testimonials | — | **Single layout** (a quote card is a fixed unit) | — |
| faq | — | **Single layout** (already a list; no JS introduced) | — |

## C. Variant Architecture

```
registration : add_action('bb_register_section_variants', …)  ← the pack registers its own
               $variants->register_many('lawyers', ['default'=>…, 'list'=>…, 'featured'=>…])
resolution   : bb_resolve_section_variant($type, $requested) → WHITELIST → 'default' fallback
fallback     : unknown / null / '' / '../..' → 'default'  (never used as a path)
loading      : bb_render_section_variant($type, $requested, $args)
               → SectionVariants::template() returns a REGISTERED absolute path only
               → include that path
safety       : returns false (renders nothing) when the theme component API is absent,
               so the caller's inline default path runs instead of a fatal
```
Infrastructure: `includes/Builder/SectionVariants.php` (class) +
`includes/Builder/section-variants.php` (API), loaded from `business-builder-core.php`.
Core is **domain-agnostic**: it knows nothing about LawFirm.

## D. Data Flow (unchanged shape)

```
LawFirmQueries->lawyers($settings)          ← ONE query (section layer)
        ↓
prepare $card_args[] (photo, meta, links)   ← preparation (section layer)
        ↓
bb_resolve_section_variant('lawyers', $settings['variant'])
        ↓
variants/lawyers/{default|list|featured}.php
        ↓
bb_component('lawyer/card', $args)          ← Phase-10 component REUSED, never duplicated
```
No variant template contains a query, `get_post_meta`, `get_terms`, or business logic.

## E. Files Created

```
includes/Builder/SectionVariants.php                     (Core: registry + safe resolver)
includes/Builder/section-variants.php                    (Core: public API)
packs/LawFirm/Sections/variants/lawyers/default.php
packs/LawFirm/Sections/variants/lawyers/list.php
packs/LawFirm/Sections/variants/lawyers/featured.php
packs/LawFirm/Sections/variants/services/default.php
packs/LawFirm/Sections/variants/services/list.php
packs/LawFirm/Sections/variants/practice-areas/default.php
packs/LawFirm/Sections/variants/practice-areas/list.php
assets/css/frontend/section-variants.css
docs/phase-11-section-variant-audit.md
tests/runtime-phase11-variants.php
tests/runtime-phase11-schema.php
```

## F. Files Modified

| File | Reason |
| --- | --- |
| `business-builder-core.php` | Require the section-variants API file (autoloader only resolves classes) |
| `packs/LawFirm/Sections/LawFirmSections.php` | Register variants; add `variant` setting to 3 schemas; rewire 3 renderers to query→prepare→resolve→render |
| `assets/css/frontend.css` | Import `frontend/section-variants.css` |

**No** payment / consultation / appointment / notification / activity / dashboard / receipt
file was touched. `SectionRegistry`, `SectionRenderer`, `PageBuilderAjax`, `PageManager`
were **not modified**.

## G. Files Deleted
None.

## H. Contracts

**Section contract (unchanged):** `render_<type>_section($section, $settings, $content)`.
Owns the query, the preparation, and the section-level hooks.

**Variant contract:**
```
$args['items']    array<int, array>  Prepared component args (same data for every variant)
$args['columns']  int                Grid columns (from settings)
$args['settings'] array              Section settings
$args['content']  array              Section content (title/description)
Output:            presentation markup
```
A variant MUST call `bb_component('<domain>/card', …)` — never reimplement card markup.

**Component contract (unchanged, Phase 10):** `bb_render_component($c, $args, $variant)`.

## I. Backward Compatibility

| Concern | Status |
| --- | --- |
| Existing section data (no `variant`) | Renders `default` — **verified** |
| `default` output vs pre-Phase-11 | **Byte-identical** wrapper + inner markup (verified live) |
| Classes (`bb-lawyers-grid`, `bb-service-card`, …) | Preserved |
| Hooks (`bb_section_classes`, `bb_render_section_*`) | Preserved |
| JS hooks (`data-section-id/type`) | Unchanged |
| URLs / semantic HTML | Unchanged |
| Plugin without the theme | `bb_render_section_variant()` returns false; inline default path runs |

## J. CSS Architecture

`section-variants.css` (frontend bundle) styles `.bb-lawyers-list`,
`.bb-services-list`, `.bb-practice-areas-list`, `.bb-lawyers-featured` using the canonical
`--bb-*` tokens via the `--bb-fe-*` bridge. **Zero bare hardcoded values**; the `--bb-fe-*`
bridge was not expanded into a second token system. Row layout uses `flex-direction` (safe
under RTL); no `left/right` is hardcoded.

## K. RTL / LTR

| Test | Result |
| --- | --- |
| LTR `/services/` (list variant) | PASS — 0 errors, 0 failed requests, no overflow |
| RTL (`WPLANG=ar`, list variant) | PASS — `dir="rtl"`, `bb-rtl`, list row renders correctly, no overflow |

## L. Responsive

List variant measured at **390 / 768 / 1024 / 1440px**: no horizontal overflow at any
width, card present and usable, 0 console errors, 0 failed requests.

## M. Testing

```
PHP lint (core + pack + 7 variant templates): PASS (0 errors)
CSS sanity (section-variants.css 13/13 braces): PASS
Phase 9 suite:      24/24 PASS
Phase 10 suite:     31/31 PASS
Phase 11 variants:  39/39 PASS
Phase 11 schema:    12/12 PASS
payment-completion: 32/0 PASS
manual-review:      29/0 PASS
markpaid-sync:       6/0 PASS
Live default parity (/services/, /home/): PASS (byte-identical wrappers)
Live variants:
  /services/ list   → .bb-services-list + service card, no fatal
  /home/ featured   → .bb-lawyers-featured + lawyer card, no fatal
Live routes / /home/ /about/ /services/ /practice-areas/ /lawyers/: 200, shell, no fatal
Live 404: PASS
Mojibake: none (live HTML clean)
Preset test (luxury + list variant): PASS — tokens drive the variant
Multisite: site 1 = astra, no BB shell, no fatal (isolation restored & verified)
Console / network: 0 errors / 0 failed requests (desktop, mobile, RTL)
Real gateway / live money: NOT TESTED (no credentials; out of scope)
```

## N. Known Limitations (deferred)

- Component (card) **design** variants — the extension point exists (Phase-10 `variant` arg +
  the new `lawyers/featured` card variant file) but no card design variants are shipped.
- Variants for **testimonials/FAQ** — deliberately none (no meaningful second layout).
- Header/Footer/Nav variants; full Customizer.
- `_bb_page_template` page-level semantics untouched (§52).
- **Pre-existing, not fixed (out of scope):** the plugin's header/footer *sections* remain
  unstyled on the front end (their CSS lives in the admin-only `template-shell.css`) —
  reported in Phase 8/9/10; that file was not touched.
- One test side-effect found and corrected: an earlier phase's test had switched **site 1**
  to the BB theme; site 1 was restored to **astra** and re-verified.

## O. Recommended Next Phase

**PHASE 12 — COMPONENT (CARD) VARIANTS.** The section layer is now clean (query → prepare →
variant → component), so the next natural layer is letting a *card* have multiple visual
designs (e.g. `lawyer/card` default vs compact vs minimal) selected independently of the
section layout — the same whitelisted-resolver pattern, applied at the component level where
Phase 10 already built the `.variant.php` resolution and the `variant` argument. Section
variants and component variants are then orthogonal, exactly as §14 of this phase specifies.

---

### Defects / notes this phase
1. **Robustness fix:** `bb_render_section_variant()` now returns `false` (renders nothing)
   when the theme component API is absent, so the plugin never fatals without the canonical
   theme — the caller's inline default path runs instead.
2. **Multisite hygiene:** an earlier test had left **site 1** on the BB theme; restored to
   `astra` and verified (isolation).