# PHASE 13 — COMPONENT VARIANT CATALOG — AUDIT

Read-only audit performed before any change.

## A. Existing component variants

| Component | Template | Variants today |
| --- | --- | --- |
| `lawyer/card` | `packs/LawFirm/Sections/components/lawyer/card.php` | default, compact |
| `service/card` | `…/service/card.php` | default, compact |
| `practice-area/card` | `…/practice-area/card.php` | default, compact |
| `testimonial/card` | `…/testimonial/card.php` | default only |
| `faq/item` | `…/faq/item.php` | default only |
| `section-heading` / `empty-state` | Theme | default only |

## B. Existing section variants (Phase 11) — unchanged

lawyers: default/list/featured · legal_services: default/list · practice_areas: default/list.
Testimonials/FAQ intentionally single-layout.

## C. Builder schema

`SectionRegistry` typed `settings` schema; `select` with `options` is **auto-rendered** by
`page-admin.js` (`renderSelectField`) and **whitelist-sanitized** by
`PageBuilderAjax::sanitize_single_value()` (drops values not in `options`). Phase 12 already
added `card_variant` (select) to the 3 card sections — so the **selection control already
exists**; Phase 13 only enriches its option labels (translated) and the catalog it draws from.

## D. Component resolver (Phase 10) — reuse, do not rebuild

`bb_render_component(component, args, variant)` / `bb_component(...)` →
`bb_component_path()` tries `{group}/{name}-{variant}.php` then `{group}/{name}.php`, with
`str_replace('..','')` + strict regex + `sanitize_file_name()`. Unknown/null/'' → base file.
`bb_before/after_component` fire in the resolver. **Unchanged by Phase 13.**

## E. Theme presets

`default` / `modern` / `luxury` from `inc/preset-resolver.php`, emitted as `--bb-*` on `:root`.
Independent of component variants (must stay so).

## F. Token system

Canonical `--bb-*` (theme `tokens.css`) + the `--bb-fe-*` bridge (`section-base.css`).
Component variants consume these; no second token system.

## G. Preview capabilities

1. **Editor schematic preview** (`renderEditorPreview` in `page-admin.js`) — a JS wireframe
   driven by field values; **not** a real card render.
2. **Live preview window** (`bb_preview=1` query) — opens the real frontend page (real
   sections + real cards) in a popup. This already shows the selected card variant.

There is **no** per-variant card preview endpoint.

## H. Admin UX

`card_variant` renders as a plain `Select` with option labels. Enough to *choose*, but an
administrator cannot see what each design looks like without opening the live preview.

## I. CSS architecture

`assets/css/frontend/section-variants.css` already owns the variant layer (section + the
Phase-12 component modifiers). New component-variant CSS belongs there (not a new file).

---

## DECISION

**What exists:** resolver, whitelist sanitizer, `card_variant` schema field, `compact`
variant for 3 components, a variant CSS layer, and a live-preview window.

**What is missing (Phase 13 scope):**
1. A **meaningful catalog** — only `compact` exists; the product needs materially different
   designs (`featured`, `minimal`, `horizontal`).
2. **Self-documenting options** — descriptions so an admin understands each design.

**What should be extended:**
- 3 card components × 3 new designs each (featured / minimal / horizontal) = 9 new templates.
- The option labels (translated) fed from a **lightweight catalog** helper in the pack.
- The variant CSS layer (new modifiers, tokens only).

**What is lightweight-by-design (deliberately NOT built):**
- A **new preview endpoint + builder JS rewrite is NOT implemented** — the builder already has
  a real **live preview window** that renders the exact selected combination, and §17/§19 say
  to choose the *smallest* solution and not to overbuild. Documented as `DEFERRED` instead of
  building a second preview stack. (No new AJAX surface, no JS renderer changes.)

**What must NOT be touched:** `SectionRegistry`, `SectionRenderer`, `PageBuilderAjax`,
`PageManager`, `page-admin.js`, business systems, DB schema, presets, `_bb_page_template`.

## Design families to add (per §6-8/§28)

For `lawyer/card`, `service/card`, `practice-area/card`:

| Variant | Presentation pattern (materially different composition) |
| --- | --- |
| `featured` | High-emphasis: larger media, stronger title, prominent CTA/panel treatment |
| `minimal` | Low-decoration: no card chrome, small/absent media, typography-first |
| `horizontal` | Directory row: media beside the text block (list/directory contexts) |

`testimonial/card` and `faq/item` remain single-design (fixed units) — documented.

## Security / parity constraints

- All variants come from a **pack-owned whitelist**; the resolver + sanitizer already enforce it.
- `null` / `''` / `default` / unknown → unchanged default output (verified).
- Variants are presentation-only: no queries, no business logic, same args contract.
- Section × component variants stay orthogonal (no combinatorial section templates).