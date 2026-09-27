# PHASE 12 — FINAL REPORT: Component (Card) Variant Architecture

## 1. Audit Results

Full audit: `docs/phase-12-component-variant-audit.md` (written before any change).

**The Phase-10 component API already supports variants — nothing was rebuilt.**
`bb_render_component( string $component, array $args = [], ?string $variant = null )` and
`bb_component(...)` already resolve `{group}/{name}-{variant}.php` (then fall back to
`{group}/{name}.php`) with a traversal-safe, whitelist path check and `sanitize_file_name()`
on the variant. `bb_before_component` / `bb_after_component` fire inside the resolver, so
variants inherit them automatically.

Established file convention: **flat** — `lawyer/card.php` → `lawyer/card-compact.php`.

## 2. Component Inventory

| Component | Owner | Args (prepared upstream) | Variants after P12 | Decision |
| --- | --- | --- | --- | --- |
| `lawyer/card` | LawFirm | name, profile_url, is_public, photo_html, show_photo, role, experience, phone, email, profile_link | default, **compact** | implement compact |
| `service/card` | LawFirm | title, summary, image_html, icon | default, **compact** | implement compact |
| `practice-area/card` | LawFirm | title, summary, image_html, icon | default, **compact** | implement compact |
| `testimonial/card` | LawFirm | author, author_title, quote, rating, image_html | default only | no variant (fixed unit) |
| `faq/item` | LawFirm | question, answer | default only | no variant |
| `section-heading` | **Theme** | title, description | default only | no variant (Theme-owned) |
| `empty-state` | **Theme** | message | default only | no variant (Theme-owned) |

## 3. Implemented Variants

| Component | Variant | Purpose | Owner | Template |
| --- | --- | --- | --- | --- |
| `lawyer/card` | `compact` | Lower-density lawyer card (shorter photo, tighter body) | LawFirm | `packs/LawFirm/Sections/components/lawyer/card-compact.php` |
| `service/card` | `compact` | Denser service card (reduced padding/type) | LawFirm | `packs/LawFirm/Sections/components/service/card-compact.php` |
| `practice-area/card` | `compact` | Denser practice-area card | LawFirm | `packs/LawFirm/Sections/components/practice-area/card-compact.php` |

Nothing else. No `minimal`, no variants on testimonials/FAQ/theme components.

## 4. Architecture

```
Section data → Query (once) → Prepared card args
    → SECTION VARIANT (layout: default|list|featured)   ← settings['variant']
        → COMPONENT RESOLVER (bb_component, reused)
            → COMPONENT VARIANT (default|compact)        ← settings['card_variant']
                → base component contract → HTML + --bb-* tokens
```
Two independent dimensions threaded through one path: the section caller puts
`card_variant` into `$args['component_variant']`; each section-variant template passes it as
the 3rd argument to `bb_component()`.

## 5. Resolver

Reused (Phase 10), not rebuilt.
- **Input:** component path + optional variant slug.
- **Whitelist/security:** strict regex on the component; `sanitize_file_name()` + `str_replace('..','')` on the variant; only a resolved path under a registered root is included.
- **Fallback:** unknown / null / '' / `default` → base file.
- **No directory scanning** — direct path candidates only (efficient, §44).

## 6. Argument Contract

Unchanged and shared: default and compact consume the **same prepared args**; the variant only
alters presentation (adds a BEM modifier class; the compact template reads the same keys).
Verified: the compact card renders the same name/link/phone/data as the default.

## 7. Orthogonality Matrix (verified)

| Section | Component | Result |
| --- | --- | --- |
| default | default | grid + default card ✓ |
| default | compact | grid + `.bb-*-card--compact` ✓ |
| list | default | list wrapper + default card ✓ |
| list | compact | list wrapper + compact card ✓ |
| featured | (compact) | featured wrapper + compact cards in the rest grid ✓ |

Key property proven: **changing the card variant never changes the section wrapper**, and
`/services/` was rendered live with `list` + `compact` simultaneously.

## 8. CSS

- `assets/css/frontend/section-variants.css` — appended a clearly-labelled
  **Component (card) variants** block (`.bb-lawyer-card--compact`,
  `.bb-service-card--compact`, `.bb-practice-area-card--compact`).
- **Tokens only** (`--bb-space-*`, `--bb-font-size-*`, `--bb-fe-*`); no hardcoded values, no
  second token namespace.
- **RTL/LTR:** no directional properties added; layout stays logical.
- **Responsive:** verified 390 / 768 / 1024 / 1440 — no overflow.

## 9. Compatibility

| Contract | Status |
| --- | --- |
| Default output | `null` == `''` == unknown == `'default'` == base (byte-identical, verified) |
| Base classes | Kept (`bb-lawyer-card bb-lawyer-card--compact` — base preserved) |
| Component hooks | Fire for variants (resolver-owned) |
| JS hooks (`data-bb-*`) | Untouched |
| URLs / semantic HTML | Unchanged (`article`/`h3`/`a`/`blockquote`) |
| Page Builder | Unchanged — reused existing `select` schema + sanitizer |
| Section variants (P11) | Unchanged, still call `bb_component()` |
| Theme presets | Variants read `--bb-*`; no preset special-case |
| `_bb_page_template` | Untouched |

## 10. Files Created

```
packs/LawFirm/Sections/components/lawyer/card-compact.php
packs/LawFirm/Sections/components/service/card-compact.php
packs/LawFirm/Sections/components/practice-area/card-compact.php
docs/phase-12-component-variant-audit.md
tests/runtime-phase12-card-variants.php
```

## 11. Files Modified

| File | Reason |
| --- | --- |
| `packs/LawFirm/Sections/LawFirmSections.php` | Added `card_variant_setting()` / `card_variant_choices()` / `card_setting_for()`; added `card_variant` to the 3 schemas; threaded `component_variant` into the 3 callers' `$args` + fallbacks |
| `packs/LawFirm/Sections/variants/lawyers/{default,list,featured}.php` | Pass `component_variant` to their `bb_component()` calls |
| `packs/LawFirm/Sections/variants/services/{default,list}.php` | same |
| `packs/LawFirm/Sections/variants/practice-areas/{default,list}.php` | same |
| `assets/css/frontend/section-variants.css` | Added the Component (card) variants block |

**No** payment / consultation / appointment / notification / activity / dashboard / receipt
file, and **no** builder core file (`SectionRegistry`, `SectionRenderer`, `PageBuilderAjax`,
`PageManager`), was touched.

## 12. Files Deleted
None.

## 13. Tests

```
PHP lint (changed + all variant/component templates): PASS (0 errors)
CSS braces (section-variants.css 23/23):              PASS
Phase 9:                                            24/24 PASS
Phase 10:                                           31/31 PASS
Phase 11 (variants):                                39/39 PASS
Phase 11 (schema):                                  12/12 PASS
Phase 12 (card variants):                           39/39 PASS
payment-completion:                                 32/0 PASS
manual-review:                                       29/0 PASS
markpaid-sync:                                        6/0 PASS
activity/isolation:                                  11/0 PASS
RTL (WPLANG=ar, 390/768/1024/1440):                 PASS (dir=rtl, no overflow)
LTR:                                                PASS
Responsive 390/768/1024/1440:                       PASS (no horizontal overflow)
Multisite: site 1 = astra (no compact classes, no fatal), site 2 = business-builder
Console / network:                                  0 errors / 0 failed requests
Live gateway / live money:                          NOT TESTED (no credentials; out of scope)
```

## 14. Live Verification

| Route | Result |
| --- | --- |
| `/ /home/ /about/ /services/ /practice-areas/ /lawyers/` | 200, theme shell, no fatal |
| `/services/` with `card_variant=compact` | `bb-service-card--compact` rendered, grid wrapper unchanged |
| `/services/` with `list` + `compact` | `.bb-services-list` **and** `.bb-service-card--compact` together (orthogonal) |
| RTL live (`ar`) | `dir="rtl"`, 0 console errors, 0 failed requests |
| Site 1 (`builder.test`) | astra, no compact classes, no fatal |

## 15. Known Limitations

- Only the `compact` card variant is shipped (a deliberate, minimal set — §6/§50).
- The `featured` **section** variant's first card keeps the Phase-11 `'featured'` card-variant
  call (which falls back to the base card since no `card-featured.php` exists) — preserved for
  default parity; the section's `card_variant` applies to the remaining grid cards.

## 16. Deferred Work (not defects)

- Component variants for testimonials / FAQ / theme components — intentionally none.
- Header/Footer/Nav variants; full Theme Customizer.
- **Pre-existing (out of scope):** mojibake in *theme comment blocks* (Phase-9 PowerShell
  writes double-encoded punctuation; code unaffected); plugin header/footer **sections**
  unstyled on the front end (admin-only CSS). Both reported in earlier phases; untouched.

## 17. Scope Verification (explicitly NOT changed)

```
payments            — not touched
consultations       — not touched
appointments        — not touched
notifications       — not touched
activity            — not touched
dashboard           — not touched
receipts            — not touched
database schema     — no change (no tables/CPTs/taxonomies/migrations)
unrelated builder   — SectionRegistry/SectionRenderer/PageBuilderAjax/PageManager untouched
```

## 18. Recommended Next Phase

**PHASE 13 — COMPONENT VARIANT CATALOG & SELECTION UX.** The architecture now cleanly
separates section layout from card design and both are configurable via the existing schema.
The next justified step is *depth*, not new architecture: add the remaining meaningful card
variants the audit deferred (e.g. a `featured` lawyer card, a `minimal` service/practice
variant), and give the builder a small live preview so an administrator can see the section ×
card combination before saving. This builds directly on Phase 12 without touching business
logic — and should only proceed if there is real product demand for more designs.

---

### Notes
- **No defect required a code fix this phase.** The resolver was already safe and complete;
  Phase 12 was purely additive (templates + wiring + CSS).
- **Environment hygiene:** a Phase-11 test had left the `/home/` lawyers section on
  `list`+`compact`; it was restored to `default`/`default` and re-verified.