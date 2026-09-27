# PHASE 12 — COMPONENT (CARD) VARIANTS — AUDIT

Read-only audit performed before any change. Evidence quoted from source.

## A. Component architecture (what already exists)

| Item | Reality | Source |
| --- | --- | --- |
| API | `bb_render_component( string $component, array $args = [], ?string $variant = null ): string` + echo-wrapper `bb_component(...)` | `themes/business-builder/inc/components.php` |
| Resolver | `bb_component_path($component, $variant)` → tries `{group}/{name}-{variant}.php`, then `{group}/{name}.php` across registered roots | same |
| **Variant support** | **ALREADY FUNCTIONAL.** The 3rd `$variant` arg already selects a `{name}-{variant}.php` file. No new resolver needed. | same |
| Fallback | unknown / null / '' variant → base file (default) | same |
| Security | `str_replace('..','',$component)` + strict regex `^[a-z0-9_\-]+(/[a-z0-9_\-]+)?$`; variant through `sanitize_file_name()`. Traversal/slashes cannot survive. | same |
| Hooks | `bb_before_component` / `bb_after_component` fire inside `bb_render_component()`, so any variant inherits them automatically. | same |
| Roots | theme `templates/components` first, then pack roots via `bb_component_roots` filter | same |

**Conclusion:** Phase 12 does **not** need a new resolver, a new API, or a new directory convention. It needs **variant template files + wiring + CSS**.

## B. File-convention finding (critical)

The established convention is **flat**: a variant is `{name}-{variant}.php` beside `{name}.php`
(e.g. `lawyer/card.php` → `lawyer/card-compact.php`). There is **no** `card/default.php`
subdirectory convention. Phase 12 must follow the flat convention (do not invent a second one).

## C. Section vs component file distinction (§32)

```
packs/LawFirm/Sections/variants/lawyers/featured.php   ← SECTION variant (layout)
packs/LawFirm/Sections/components/lawyer/card.php      ← COMPONENT (card)
packs/LawFirm/Sections/components/lawyer/card-compact.php ← COMPONENT variant (NEW in P12)
```
`featured.php` under `variants/` is a **section** variant and must not be confused with a
component variant.

## D. Component inventory

| Component | Owner | Template | Args (prepared upstream) | Current variants | Candidate | Decision | Reason |
| --- | --- | --- | --- | --- |
| `lawyer/card` | LawFirm | `components/lawyer/card.php` | name, profile_url, is_public, photo_html, show_photo, role, experience, phone, email, profile_link | default only | `compact` | **Implement `compact`** | Lawyers appear in dense grids/lists; a lower-density card is genuinely useful. `minimal` rejected (would drop the core contact info the domain always shows). |
| `service/card` | LawFirm | `components/service/card.php` | title, summary, image_html, icon | default only | `compact` | **Implement `compact`** | Services are text/icon units; a compact variant suits dense/service-list contexts. |
| `practice-area/card` | LawFirm | `components/practice-area/card.php` | title, summary, image_html, icon | default only | `compact` | **Implement `compact`** | Same rationale as services (taxonomy terms, icon/description). |
| `testimonial/card` | LawFirm | `components/testimonial/card.php` | author, author_title, quote, rating, image_html | default only | — | **No variant** | A quote card is a fixed semantic unit; a second design adds no architectural value. |
| `faq/item` | LawFirm | `components/faq/item.php` | question, answer | default only | — | **No variant** | Already a minimal disclosure unit. |
| `section-heading` | **Theme** | `templates/components/section-heading.php` | title, description | default only | — | **No variant** | Theme-owned generic primitive; must stay domain-agnostic. |
| `empty-state` | **Theme** | `templates/components/empty-state.php` | message | default only | — | **No variant** | Same. |

Implemented variants: **`compact`** for `lawyer/card`, `service/card`, `practice-area/card`.
Nothing else. (Few meaningful variants over a catalog — §6/§50.)

## E. Section → component data flow (real, now)

```
LawFirmQueries->…($settings)            ← ONE query
        ↓
prepare $card_args[] (all items)        ← section layer
        ↓
bb_render_section_variant('lawyers', $settings['variant'], $args)
        ↓
variants/lawyers/{default|list|featured}.php
        ↓
bb_component('lawyer/card', $item)      ← currently NO 3rd arg → always default card
```
**Gap for Phase 12:** the section variant templates call `bb_component()` without a variant
argument, and the prepared `$args` does not yet carry one. Phase 12 threads a
**`component_variant`** value through `$args` → the `bb_component()` call.

## F. Configuration (where a component variant can be selected)

The Phase-11 audit proved the Page Builder `select` schema **already whitelist-sanitizes**
options (`PageBuilderAjax::sanitize_single_value`, lines 1266-1316). So the smallest
integration is: add a **`card_variant`** `select` setting to the section schema, exactly like
the existing `variant` setting. No builder/core changes.

Storage: `_bb_page_sections` → `settings.card_variant` (per-page post meta). Site-scoped,
saved per section. **No** new table, CPT, taxonomy, or option.

Two independent dimensions:
```
section variant   = settings['variant']       (layout: default/list/featured)
component variant = settings['card_variant']  (card design: default/compact)
```

## G. Compatibility contracts to preserve

- Default output byte-identical when `card_variant` is absent/`default`.
- Component classes (`bb-lawyer-card`, …), hooks, JS (`data-bb-*`), URLs, semantic HTML.
- Phase-11 section variants keep calling `bb_component()` (no card markup duplication).
- `--bb-*` tokens canonical; `--bb-fe-*` bridge retained, not extended into a 2nd system.
- Theme stays domain-agnostic; LawFirm owns LawFirm card variants.

## H. Pre-existing issues observed (NOT Phase-12 scope)

- **Mojibake in theme comment blocks**: the Phase-9 PowerShell writes double-encoded
  punctuation in **comments only** (e.g. `â€”`, `Ã‚Â§`). Code is unaffected. Classified:
  `LIMITATION`. Document only; do not touch (out of scope, no functional impact).
- Plugin header/footer **sections** unstyled on the front end (admin-only CSS) — reported in
  Phases 8–11. Classified: `DEFERRED`. Not Phase-12.

## I. Implementation plan

1. `component_variant` setting helper (`card_variant`) → add to lawyers/services/practice-areas schemas.
2. Callers pass `settings['card_variant']` into `$args['component_variant']`.
3. Section variant templates pass it as `bb_component(..., $args['component_variant'] ?? null)`.
4. Add 3 variant templates: `lawyer/card-compact.php`, `service/card-compact.php`,
   `practice-area/card-compact.php` (same args, presentation-only).
5. Add scoped CSS in a new `packs` CSS file using `--bb-*` tokens.
6. Verify: default parity, orthogonality matrix, RTL, responsive, suites.