# PHASE 13 — FINAL REPORT: Component Variant Catalog & Selection UX

## 1. Audit Results

Full audit: `docs/phase-13-component-variant-catalog-audit.md` (written before any change).

**Already existed (reused, not rebuilt):**
- Phase-10 component API `bb_component($component, $args, $variant)` + traversal-safe,
  whitelist-by-file resolver (`bb_component_path`).
- Phase-11 section variant API + whitelist registry.
- Phase-12 `card_variant` **select** setting on the 3 card sections, auto-rendered by the
  builder and **whitelist-sanitized** by `PageBuilderAjax::sanitize_single_value()`.
- A variant CSS layer (`assets/css/frontend/section-variants.css`).
- A builder **live-preview window** (`bb_preview=1`) that renders the real selected combination.

**Missing before Phase 13:** only `compact` existed per card — too thin to demonstrate real
design freedom. No descriptive labels, no catalog metadata.

## 2. Current Design Architecture (boundaries held)

```
THEME      → global tokens (--bb-*), presets (default/modern/luxury), shell. Domain-agnostic.
SECTION    → layout/composition (Phase 11): grid | list | featured.
COMPONENT  → one content item's visual design (Phase 12/13): default|compact|featured|minimal|horizontal.
CUSTOMIZE  → future Theme Customizer (tokens); not built here.
Bus. logic → untouched, upstream of all three.
```

## 3. Component Variant Catalog

| Component | Variant | Design purpose | Status |
| --- | --- | --- | --- |
| lawyer/card | default | Balanced profile card | implemented (unchanged) |
| lawyer/card | compact | Dense card, shorter photo | implemented (P12) |
| lawyer/card | featured | High-emphasis card + contact button | **implemented (P13)** |
| lawyer/card | minimal | Typography-first, no chrome, one contact line | **implemented (P13)** |
| lawyer/card | horizontal | Directory row (photo beside details) | **implemented (P13)** |
| service/card | default / compact | Balanced / dense | implemented |
| service/card | featured | Larger media treatment | **implemented (P13)** |
| service/card | minimal | Text-first, inline icon | **implemented (P13)** |
| service/card | horizontal | Media/icon beside copy | **implemented (P13)** |
| practice-area/card | default / compact | Balanced / dense | implemented |
| practice-area/card | featured | Larger media treatment | **implemented (P13)** |
| practice-area/card | minimal | Text-first, inline icon | **implemented (P13)** |
| practice-area/card | horizontal | Media/icon beside copy | **implemented (P13)** |
| testimonial/card | default only | Fixed semantic unit | unchanged (no variant) |
| faq/item | default only | Fixed disclosure unit | unchanged (no variant) |
| section-heading / empty-state | default only | Theme-owned generic primitives | unchanged |

## 4. Design Coverage

- **Lawyers:** 5 designs (default, compact, featured, minimal, horizontal).
- **Services:** 5 designs.
- **Practice Areas:** 5 designs.

Each is a distinct **composition / information hierarchy**, not a token tweak.

## 5. Section × Component Orthogonality (verified)

| Section | Component | Result |
| --- | --- | --- |
| default | featured | grid wrapper + featured card ✓ (live `/home/`) |
| list | horizontal | list wrapper + horizontal card ✓ |
| list | featured | list wrapper unchanged, featured card ✓ |
| default | compact | grid + compact ✓ (P12) |
| featured | compact | featured wrapper + compact cards ✓ (P12) |

Key property re-proven: a card variant **never changes the section wrapper** — no combinatorial
section templates were created.

## 6. Selection UX

The administrator selects the card design in the existing builder **Select** (`Card design`),
populated from the pack's catalog. Options are translated labels; each option also carries a
**description** (`option_titles` schema metadata: "*Directory row: photo beside the details.
Pairs well with the List layout.*"). The existing builder renders it without any JS change.

## 7. Preview

**Reused, not rebuilt.** The builder already has a **live preview window** (`bb_preview=1`)
that renders the exact real page — heading, sections and the selected card design. Phase 13
did **not** add a preview endpoint or modify `page-admin.js` (the audit showed that would be a
new surface for no gain — §17/§19/§40). A dedicated inline per-variant preview is documented
as **deferred**, not built.

## 8. Resolver

**Reused (Phase 10), unchanged.** `card_variant` → validated choice → `bb_component()` →
`card-{variant}.php`. Unknown/null/''/`default` → base `card.php`.

## 9. Configuration

**Reused.** `settings.card_variant` (existing `select` schema + existing sanitizer). The catalog
only feeds the option whitelist. No new field, table, CPT or taxonomy.

## 10. Theme Integration

All 9 new designs consume `--bb-*` / `--bb-fe-*` only (verified live: under the **luxury**
preset the horizontal card inherited the gold primary + the preset card radius). No
component-specific token namespace; no hardcoded colours (CSS scan: 0 hardcoded hex).

## 11. Responsive / RTL / LTR

| Test | Result |
| --- | --- |
| featured card, live `/home/` | 322px card / 320px capped photo — proportionate (fixed an initial oversized first pass) |
| horizontal, 390/768/1024/1440 | PASS — no overflow, 2 cards, 0 console errors, 0 failed requests |
| RTL (`ar`) + horizontal | PASS — `dir=rtl`, no overflow, layout mirrors via flex-direction |
| LTR | PASS |

## 12. Accessibility

Each design keeps one `<h3>`, real `<a>` links, semantic `article`, unchanged alt behaviour and
the base class name (so existing focusable elements/hooks are untouched). `minimal` intentionally
removes decorative chrome only — content and links remain.

## 13. Files Created

```
packs/LawFirm/Sections/components/lawyer/card-featured.php
packs/LawFirm/Sections/components/lawyer/card-minimal.php
packs/LawFirm/Sections/components/lawyer/card-horizontal.php
packs/LawFirm/Sections/components/service/card-featured.php
packs/LawFirm/Sections/components/service/card-minimal.php
packs/LawFirm/Sections/components/service/card-horizontal.php
packs/LawFirm/Sections/components/practice-area/card-featured.php
packs/LawFirm/Sections/components/practice-area/card-minimal.php
packs/LawFirm/Sections/components/practice-area/card-horizontal.php
docs/phase-13-component-variant-catalog-audit.md
tests/runtime-phase13-card-catalog.php
```

## 14. Files Modified

| File | Reason |
| --- | --- |
| `packs/LawFirm/Sections/LawFirmSections.php` | Added `card_design_catalog()` (labels + descriptions), `card_variant_descriptions()`, `card_setting_with_descriptions()`; expanded `card_variant_choices()`; 3 schemas now use the description-aware setter |
| `assets/css/frontend/section-variants.css` | Added featured/minimal/horizontal CSS for the 3 card components (tokens only) |
| `tests/runtime-phase11-variants.php` | **Fixed a faulty assertion**: it counted the bare substring `bb-lawyer-card`, which a legitimate modifier (`bb-lawyer-card--featured`) duplicates; now counts the element (`class="bb-lawyer-card`) — its actual intent. This restored the P11 baseline. |

**No** `SectionRegistry` / `SectionRenderer` / `PageBuilderAjax` / `PageManager` /
`page-admin.js` change. **No** payment/consultation/appointment/notification/activity/
dashboard/receipt change. **No** DB/CPT/taxonomy change.

## 15. Files Deleted
None.

## 16. Tests

```
PHP lint (pack + 15 component/variant templates):   PASS (0 errors)
CSS braces (section-variants.css):                  PASS (60/60); 0 hardcoded colours
Phase 9:                                             24/24 PASS
Phase 10:                                            31/31 PASS
Phase 11 (variants):                                 39/39 PASS   (baseline restored)
Phase 11 (schema):                                   12/12 PASS
Phase 12 (card variants):                            39/39 PASS
Phase 13 (catalog):                                 127/127 PASS
payment-completion / manual-review / markpaid-sync:  32/0, 29/0, 6/0 PASS
activity+isolation:                                  11/0 PASS
RTL / LTR:                                           PASS
Responsive 390 / 768 / 1024 / 1440:                  PASS (no overflow)
Multisite (site 1 astra, no bb card classes):        PASS
Console / network:                                   0 errors / 0 failed requests
Live gateway / live money:                           NOT TESTED (no credentials)
```

## 17. Live Verification

| Check | Result |
| --- | --- |
| Routes `/ /home/ /about/ /services/ /practice-areas/ /lawyers/` | 200, theme shell, no fatal |
| Card design **featured** set on `/home/` lawyers | `bb-lawyer-card--featured` rendered; section wrapper unchanged |
| Card design **horizontal** set | `bb-lawyer-card--horizontal` rendered (directory rows), section grid unchanged |
| RTL + horizontal | `dir=rtl`, no overflow |
| luxury preset + horizontal | gold primary, preset radius inherited by the card |
| Multisite | site 1 (astra) shows no BB card classes |
| Builder admin flow | **NOT fully exercised** — no automated admin-session harness; the schema/sanitizer reuse was verified at the PHP level instead |

## 18. Known Limitations

- The featured card needed a `max-height` cap: an initial full-width treatment made it a
  disproportionate banner in a grid; corrected before sign-off (verified 320px cap).
- No inline per-variant preview thumbnail in the builder (the live-preview window is the
  current preview path).
- Test-harness note: rapid successive `update_post_meta` calls in one script can read stale
  object-cache; live verification used a `clean_post_cache()` + explicit re-set, not product code.

## 19. Deferred Work (not defects)

- **Header / Footer / Navigation variants** — part of the wider Theme roadmap (§26).
- **Full Theme Customizer** (colors/typography/spacing/buttons/… as user controls).
- **Inline per-variant preview** in the builder.
- Pre-existing: mojibake in *theme comment blocks* (code unaffected); plugin header/footer
  **sections** unstyled on the front end (admin-only CSS). Reported in earlier phases; untouched.

## 20. Scope Verification (explicitly NOT touched)

```
payments · consultations · appointments · notifications · activity · dashboard · receipts
database schema (no tables/CPTs/taxonomies/migrations)
SectionRegistry · SectionRenderer · PageBuilderAjax · PageManager · page-admin.js
Theme presets · _bb_page_template · Theme shell
```
Only `LawFirmSections.php`, `section-variants.css`, and one test assertion were modified.

## 21. Recommended Next Phase

**PHASE 14 — THEME CUSTOMIZER (GLOBAL DESIGN CONTROLS).** The three orthogonal layers are now
in place and proven live: **Theme tokens/presets × Section layout × Component design**. The
clear next dependency is the layer the vision diagram puts *above* them: letting the site owner
adjust the **Theme tokens** (colors, typography, spacing, radius, buttons, container) and
choose a preset, so that every section layout and every card design inherits their brand
automatically. The `bb_theme_customization_overrides()` extension point (Phase 9) already
exists for exactly this, so it can be built without touching component or section code.

---

### Notes
- **No product defect required a code fix** this phase — the resolver and schema were already
  correct; Phase 13 was additive (templates + catalog metadata + CSS).
- **One test-quality defect fixed:** the Phase-11 featured-card count assertion was counting a
  substring that a legitimate modifier duplicates; corrected to count the element, restoring
  the required 39/39 baseline without weakening the check.
- **Environment hygiene:** the test `/home/` lawyers section was restored to
  `variant=default`, `card_variant=default`; preset back to `default`; locale back to English;
  site 1 re-verified on `astra`.