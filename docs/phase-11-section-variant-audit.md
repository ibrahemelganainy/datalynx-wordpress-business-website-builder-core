# PHASE 11 — SECTION VARIANT ARCHITECTURE — AUDIT

Read-only audit performed before any change. Evidence quoted from source.

## 1. Section architecture (how sections are registered)

- Registry: `includes/Builder/SectionRegistry.php` — `register( string $slug, array $args )`.
- A section declares: `name`, `label`, `description`, `category`, `icon`, `supports`,
  `settings` (schema), `content` (schema), optional `render` callable, `enabled`.
- **`settings` is a typed field schema.** Supported types (from `SectionRegistry` +
  `PageBuilderAjax::sanitize_single_value`): `text, textarea, number, url, color, image,
  checkbox, multicheck, select (single + multiple), repeater`.
- A `select` field carries `options` (value => label).

## 2. Section data flow (per LawFirm section)

```
SectionRenderer::render_section($section)
   → SectionRegistry::get($type) → $config
   → if $config['render'] callable → call_it($section, $settings, $content)
   → LawFirmSections::render_<type>_section()
         → new LawFirmQueries()-><type>($settings)   ← the ONLY query (once)
         → per-item meta reads + preparation
         → bb_component('<domain>/card', $prepared)  ← Phase 10 component
```

Confirmed: the query happens **once** at the section layer; components receive prepared
data. This is exactly the boundary Phase 11 must preserve.

## 3. Existing section configuration (storage + serialization)

| Item | Mechanism |
| --- | --- |
| Storage | Per-page post meta `_bb_page_sections` (array of `{type,id,settings,content}`) — `PageManager` |
| Editor | `REST/PageBuilderAjax.php` → `sanitize_fields($values, $schema)` |
| Select sanitizer | **Enforces the declared option set** (lines 1266-1316): a value not in `options` is dropped |
| Admin UI | JS-rendered from `get_editor_schema()`; renders each `settings` field by `type` |

**Key finding:** adding a `variant` setting of `type => 'select'` with `options` gives a
whitelist-enforced, auto-sanitized, auto-rendered field with **no** change to
`SectionRegistry`, `SectionRenderer`, `PageBuilderAjax` or the builder JS.

## 4. Existing variant-like concepts

| Concept | Where | What it is |
| --- | --- | --- |
| `layout` setting | `slider`, `header` (core sections) | A `select` that switches a CSS class — precedent for a settings-driven layout choice |
| `columns` setting | lawyers/services/practice/testimonials | Grid column count (already a layout control) |
| `_bb_page_template` | page meta | A **page-level** cosmetic preset; **NOT** a section variant (§52 — must stay independent) |
| Phase 10 component `variant` arg | `bb_render_component($c,$args,$variant)` + `.variant.php` resolution | A **component** variant mechanism (different concept — §14) |

→ A **section** variant mechanism does not yet exist. It must be built, reusing the
existing `settings` + resolver patterns rather than inventing new infrastructure.

## 5. Variant candidates (justified by data, not symmetry)

| Section | Candidate variants | Justification | Risk |
| --- | --- | --- | --- |
| **lawyers** | `default` (grid), `list`, `featured` | Items are people with a photo + rich body; a list + a featured layout are genuinely useful and the existing `columns` setting already varies the grid | low |
| **legal_services** | `default` (grid), `list` | Services are text/icon items; list is a natural, meaningful second layout | low |
| **practice_areas** | `default` (grid), `list` | Same as services (taxonomy terms with icon/description) | low |
| **testimonials** | `default` (grid) only | A quote card is a fixed unit; list/featured add no clear value — **do not invent** | — |
| **faq** | `default` (list) only | Already a list; an accordion would need JS (§26/§38 of Phase 10 forbid new JS) | — |

Decision: implement Section Variants for **lawyers, legal_services, practice_areas**
(default + list; lawyers also featured). Leave testimonials and FAQ single-layout.

## 6. Existing CSS (reusable layout classes)

- `%s`: `section-base.css` defines `.bb-grid-columns-N` (+ responsive collapse) and the
  `--bb-fe-*` bridge; `.bb-lawyers-grid`, `.bb-services-grid`, `.bb-practice-areas-grid`,
  `.bb-testimonials-grid`, `.bb-faq-list` are the grids.
- No list/featured layout classes exist yet — Phase 11 adds them, token-based.
- Tokens available: `--bb-space-*`, `--bb-radius-*`, `--bb-shadow-*`, `--bb-color-*`,
  `--bb-fe-*` (bridge), `--bb-container-*`, `--bb-section-spacing*`.

## 7. Existing JS

- **No JS touches any card or section wrapper.** JS binds to `data-bb-*` and stable IDs
  (lookup, manual-payment, receipt-modal, billing). Section wrappers carry
  `data-section-id` / `data-section-type` (set by `SectionRenderer`), which the builder JS
  uses for edit targeting — **these must not change**.

## 8. Hooks

- `bb_section_classes` / `bb_section_attributes` — on the `<section>` wrapper
  (`SectionRenderer::render_section`). Must stay there (§38).
- `bb_render_section_{type}` — dispatches to the pack renderer.
- `bb_before_component` / `bb_after_component` — Phase 10 component hooks.

## 9. Ownership / boundaries (unchanged)

Theme = shell/tokens/presets/generic components. Plugin = builder/registry/storage.
Pack = LawFirm sections + domain components + section CSS. Dependency: **Pack → Theme**.

## 10. Plan

1. Add a **`variant` setting** (`type: select`) to the 3 candidate sections via a shared
   helper — declarative, no infra change.
2. Add **`includes/Builder/SectionVariants.php`** (Core, generic infra): a whitelist
   registry + `bb_resolve_section_variant()` + `bb_render_section_variant()` that locates
   `variants/{type}/{variant}.php` safely (no traversal, whitelist only, fallback default).
3. Pack registers its variants via `bb_section_variants` filter; extracts the current
   markup into `packs/LawFirm/Sections/variants/{type}/default.php` and adds `list.php`
   (+ `featured.php` for lawyers).
4. Rewire `render_<type>_section()` to: query → prepare → resolve variant → render.
5. Variant CSS in `packs/LawFirm/Sections/variants/...` (or the section CSS) using `--bb-*`.
6. Regression: default must be byte-equivalent to Phase 10 output.