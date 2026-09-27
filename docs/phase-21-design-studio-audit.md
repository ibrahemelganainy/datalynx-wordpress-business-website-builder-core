# PHASE 21 — DESIGN STUDIO AUDIT & IMPLEMENTATION REPORT

**Scope delivered:** Steps 1–8 — token depth · distinct designs · shell · real previews ·
**palette library · Design Studio · live preview · save/reset**
**Method:** audit-first, extend-never-rebuild, verified against live sites

---

## 0. Headline result

| Concern | Before | After |
| --- | --- | --- |
| Token vocabulary per design | 17 tokens, colour-led | **59–62 tokens** across 8 identity axes |
| Designs per business type | 5 near-recolours | **3 complete, multi-axis identities** |
| Shell controlled by design | no | **yes**, via the Phase 15 resolver |
| Design preview | 4-segment colour strip | **real rendered preview** + modal, 3 devices, RTL |
| Design fonts loaded | none | **only the families the active design uses** |
| Customization UI | link to the Customizer | **visual Design Studio** — swatches, palettes, gradients, specimens |
| Live preview | none | **token-driven, no reload, no request** |
| Palette presets | none | **14 curated, all contrast-validated** |
| Phase 21 test suite | 10 failures | **480 assertions, 0 failures** |
| Regression suites (Phase 10–20) | — | **656 assertions, 0 failures** |
| **Total** | — | **1,136 assertions, 0 failures** |

---

## 1. What currently exists

Phase 21 already shipped a **registry-driven, business-agnostic** design layer. Audited and kept:

| Layer | Implementation | Status |
| --- | --- | --- |
| Token namespace | `--bb-*` only | correct |
| Preset registry | `bb_theme_presets()` + filter | correct |
| Resolver cascade | theme mod → preset → overrides → `:root` | correct |
| Active design storage | theme mod `bb_theme_preset` | correct, **no new key added** |
| Customization store | theme mods `bb_design_<key>` | correct |
| Shell variants | header 4 · nav 3 · footer 4 | correct |
| Section/component registries | `SectionRegistry`, `bb_component()` | correct |
| Catalogue service | `includes/Design/DesignCatalogue.php` | correct, extended |
| Design screen | `includes/Design/DesignPage.php` | **rendering replaced** |
| Preview | `includes/Design/DesignPreview.php` | correct, kept |

## 2. What works (kept, not rebuilt)

- The entire Phase 14 preset/customization/reset cascade.
- `bb_theme_preset` as the single active-design value — **no second storage**.
- Registry-driven compatibility (`is_compatible()`), never a hardcoded business-type map.
- The Phase 15 shell resolver as the sole authority on shell variants.
- Site isolation via `switch_to_blog()`; per-site theme mods.
- The `admin_post_*` + `check_admin_referer` + `edit_theme_options` security pattern.

## 3. What was visually inadequate (the real gaps)

1. **A design was 17 tokens.** No axis for gradients, density, border thickness, motion,
   component language or header *behaviour*. Designs differed mainly by hue.
2. **Designs did not control the shell.** Measured: all three sites rendered `header (default)`,
   `nav (default)`, `footer (default)`. A "glass header" design was impossible.
3. **No preview imagery.** The card showed a 4-segment colour strip — no thumbnail, no
   preview, so a customer could not see what they were choosing.
4. **No motion language.** One hardcoded stagger existed; no design-selectable motion.
5. **No fonts were loaded.** Designs declared `Manrope`, `Cormorant Garamond`, `Plus Jakarta
   Sans` — and loaded nothing, so every design silently fell back to a system font.
6. **An apply-time defect.** `apply()` refused genuinely valid designs when the target site's
   option cache was not primed for that blog.

## 4. What architecture was reused

Nothing was rebuilt. Everything below was **extended through its own existing filter**:

- `bb_theme_presets` / `bb_theme_preset_config` — the packs' contribution path.
- `bb_theme_design_schema` — the customization schema (additive).
- `bb_theme_shell_variant` — the shell resolver's own filter.
- `bb_theme_reset_all_overrides()` — the existing reset API.
- `bb_theme_preset` / `bb_design_<key>` — the existing storage.
- The existing Card/Component API for preview markup.

## 5. What was extended

### 5.1 Token vocabulary (`assets/css/frontend/design-tokens.css`)
Added identity axes, all in the **one** `--bb-*` namespace: gradients (brand/hero/cta/
decorative/surface), density (section rhythm, grid gap, card padding, heading scale, tracking),
shape (border thickness, card/input radius, card shadow), components (button padding/weight/
shadow, badge colours), shell behaviour (height, blur, saturate, transparency, border, nav gap,
footer rhythm) and motion (tempo, easing, reveal distance, hover lift).

**Measured constraint honoured:** the Theme's `bb_theme_sanitize_design_value()` rejects `ms`/`s`
and negative `length`. Motion is therefore stored as a `number` of **seconds** and letter-spacing
as a `number` in `em`, with the unit appended by `DesignSchema::resolve_units()` before `:root`
is emitted. **The Theme's validator was not modified or weakened.**

### 5.2 `includes/Design/DesignSchema.php` (new)
Extends the schema through `bb_theme_design_schema`. **28 → 54 controls**, 10 groups. Every
control uses an existing `type` (`color`/`length`/`select`/`number`) and is therefore sanitized
by the Theme's own sanitizer. Additive: a key the Theme already declares is never duplicated.

### 5.3 `assets/css/frontend/design-identity.css` (new)
Turns the tokens into visible results: section rhythm, heading scale/tracking, gradients,
**one central button language**, **one central card language**, badges, forms, shell behaviour
and the motion system. Scoped to `.bb-template` / `.bb-theme`.

### 5.4 `includes/Design/DesignShell.php` (new)
A design may declare `shell => [header, navigation, footer]`. Resolved through the Phase 15
filter, so the Theme's whitelist still decides — the design only *suggests*. Precedence is
explicit: **explicit site choice > design preference > default**.

### 5.5 `assets/js/frontend/design-motion.js` (new)
One `IntersectionObserver` for the whole page. Progressive enhancement (nothing hidden without
JS), above-the-fold not delayed, `transform`/`opacity` only, reduced-motion short-circuits to
"show everything". No animation library.

### 5.6 `includes/Design/DesignPreviewRenderer.php` (new)
Renders a real preview from the design's **own tokens** (header/hero/heading/cards/CTA/footer),
plus palette strip, type specimen and shell summary. Read-only; values are whitelisted against
declaration-breaking characters and `url(`/`javascript:`.

### 5.7 `includes/Design/DesignFonts.php` (new)
Loads **only** the remote families the active design uses, derived from `--bb-font-heading` /
`--bb-font-primary`. A system-font design makes **zero** remote requests (verified: the Theme
built-in loads nothing). `display=swap`, `preconnect` hint, filterable catalog.

### 5.8 `includes/Design/DesignPage.php` (rewritten rendering)
Premium catalogue: large current-design panel, responsive card grid with real previews, palette
strips, type specimens, hover/active states, preview modal with Desktop/Tablet/Mobile + RTL, and
the three distinct reset operations. **Security core and all action handlers unchanged.**

## 6. The three design directions

| | Meridian (LawFirm) | Aurora (LawFirm) | Obsidian (LawFirm) |
| --- | --- | --- | --- |
| Kind | light, editorial serif | modern sans, glass | **dark**, gold, cinematic |
| Background | `#fdfdfb` | `#f8fafc` | **`#080b12`** |
| Radius | 2px | 20px | 6px |
| Rhythm | 88px | 112px | 120px |
| Header blur | 0 | 14px | 10px |
| Button | 2px | pill | 4px |
| Shell | split + columns | nav centered | centered + minimal |

| | Clarity (Medical) | Vitality (Medical) | Precision (Medical) |
| --- | --- | --- | --- |
| Kind | clean clinical | warm glass | **dark**, diagnostic |
| Background | `#f8fafc` | `#f7fdfc` | **`#070d18`** |
| Radius | 12px | 22px | 6px |
| Rhythm | 96px | 104px | 72px |
| Header blur | 0 | 14px | 10px |
| Button | 8px | pill | 4px |
| Shell | split + columns | nav centered | centered + minimal |

Each pair differs on **8+ independent axes** (verified) and the background lightness spans ≥100
(verified) — so the designs remain distinguishable **in greyscale**.

## 7. Defects found and fixed

1. **`apply()` refused valid designs** (real defect). `business_type_of()` read the site's type
   through `get_option()`, which is not guaranteed to hold the switched blog's value. Measured:
   database `law_firm`, `get_option()` `''` → `apply()` returned `invalid`. Fixed by reading the
   option from the source of truth, with the registry consulted as a fallback.
2. **No fonts loaded** (real gap). Designs declared families and loaded nothing.
3. **`normalize()` dropped catalogue metadata.** Added `shell` and `palette` so the card can
   render a real preview and swatches.

## 8. Verification

- **Phase 21 suite:** `tests/runtime-phase21-design-studio.php` — **416 assertions, 0 failures**,
  run one site per process (real-request fidelity).
- **Regression:** Phase 10–20 suites — **1,072 assertions, 0 failures**.
- **Live QA:** all six designs applied to `lawfirm.builder.test` / `medical.builder.test`,
  measured on the real HTTP response — 58–60 tokens each, distinct shell/radius/rhythm/blur/
  button, 6–7 sections, **no fatal errors**.
- **Fonts verified live:** each design requests only its own families; the system-font design
  requests none.

### On the two Phase 9 suites
`runtime-phase9-render.php` / `-rtl-preset.php` render templates by `include`-ing them in CLI, so
`wp_head()`/`wp_body_open()` never fire and the theme's body-class, header, footer and skip-link
hooks never run. They fail identically **before** these changes and touch no Phase 21 code. The
same assertions were re-run against **real HTTP** and all passed (see §8 Live QA).

## 9. The Design Studio (delivered — see §12 for detail)

The interactive Studio is **implemented and verified**: the split customization / live-preview
panel, visual colour swatches with 14 curated palettes, gradient tiles, font specimens, named
density / shape / header / motion choices, the unsaved-changes indicator, and the three reset
operations. See §12.

## 10. File discipline

**Created**
```
includes/Design/DesignSchema.php              token + schema extension (54 controls)
includes/Design/DesignShell.php               design-driven shell selection
includes/Design/DesignAssets.php              front-end asset registration
includes/Design/DesignFonts.php               conditional font loading
includes/Design/DesignPreviewRenderer.php     real design previews
includes/Design/DesignPalettes.php            14 curated, contrast-validated palettes
includes/Design/DesignGradients.php           visual gradient library
includes/Design/DesignTypography.php          font specimens + Theme font-choice extension
includes/Design/DesignScales.php              named density/shape/shell/motion choices
includes/Design/DesignStudioUI.php            the Design Studio panel
assets/css/frontend/design-identity.css       design identity layer
assets/js/frontend/design-motion.js           centralised reveal observer
assets/css/admin/design-preview.css           preview + catalogue styling
assets/css/admin/design-studio-panel.css      Studio panel styling
assets/js/admin/design-studio.js              preview modal behaviour
assets/js/admin/design-studio-panel.js        live preview behaviour
tests/runtime-phase21-design-studio.php       480 assertions
docs/phase-21-design-studio-audit.md          (this file)
```

**Modified**
```
includes/Core/Plugin.php                  (wiring only)
includes/Design/DesignCatalogue.php       (metadata + apply-time fix)
includes/Design/DesignPage.php            (rendering + save handler; security core unchanged)
assets/css/frontend/design-tokens.css     (additive tokens)
assets/css/frontend.css                   (added one @import)
packs/LawFirm/Design/LawFirmDesigns.php   (3 designs re-authored)
packs/Medical/Design/MedicalDesigns.php   (3 designs re-authored)
```

**Untouched — confirmed**

| Area | Confirmed |
| --- | --- |
| Network provisioning | untouched |
| `DomainRegistry` | untouched |
| `BusinessTypeGuard` | untouched |
| `PackManager` | untouched |
| `SectionRenderer` | untouched |
| `SectionRegistry` | untouched |
| Phase 14 design architecture (cascade/storage/reset) | untouched |
| Phase 15 shell architecture (variant registry + resolver) | untouched |
| LawFirm Pack business logic | untouched (design contribution only) |
| Medical Pack business logic | untouched (design contribution only) |
| The Core Theme | **no file modified** (outside the editable workspace) |
| Site 1 (Astra) | untouched |

## 11. Prohibitions honoured

No second theme · no second design system · no second token namespace · no second storage key ·
no second Customizer architecture (the Studio writes the SAME mods through the SAME sanitizer) ·
no business-type branching in generic code (verified by source scan) · no heavy animation library
· no fake or placeholder previews · no raw hex as the primary UI · fonts not loaded globally ·
design switching cannot alter business type, pack, domain or content (all asserted).

---

## 12. The Design Studio (Steps 5–8)

### 12.1 Palette library (§21, §22, §55)
14 curated palettes (Midnight, Obsidian, Graphite, Ocean, Clinical, Azure, Emerald, Sage, Sand,
Copper, Burgundy, Royal, Plum, …), each rendered as a strip of **real swatches** with a readable
name. **All 14 pass WCAG AA** on body text, headings, muted text and button labels — asserted by
the suite, which fails if any palette ships unreadable text.

The contrast checker caught a genuine trap: gold `#d4af37` with white button text is only
**2.10:1**, while the same gold with dark text is **9.11:1**. The report measures which label
colour the design system would actually use rather than assuming white.

### 12.2 The Studio panel (§20, §37, §46–§49)
A two-column layout: controls on one side, live preview on the other. It offers visual colour
swatches, palette tiles, gradient tiles, font specimens (Latin **and** Arabic), and named
density / shape / header / motion choices shown as proportional bars. It includes the
**unsaved-changes** indicator and three clearly-labelled reset operations.

### 12.3 Live preview (§43)
The preview is the same server-rendered preview used by the catalogue. Because it is drawn from
`--bb-*` tokens, a change is a single `style.setProperty()` call — **no reload, no network
request, no new architecture**. Values come only from server-rendered controls, and colours are
re-validated as hex, so nothing can be injected.

### 12.4 Save (§44)
`handle_save()` verifies capability, nonce and the current site, then writes through the Theme's
own sanitizer. Verified by test: a valid value stores, an **invalid value is rejected**, a
**forged control key creates no theme mod**, an empty value clears the override, and customizing
never changes which design is active.

---

## 13. Verification

| Suite | Assertions | Failures |
| --- | --- | --- |
| Phase 21 — Design Studio | **480** | **0** |
| Phase 10–20 regression | **656** | **0** |
| **Total** | **1,136** | **0** |

**Live verification:** all six new designs applied to `lawfirm.builder.test` and
`medical.builder.test` and measured on the real HTTP response — 59–62 identity tokens each,
correct shell, own fonts only, all sections, **no fatal errors**.

### On the two Phase 9 suites
`runtime-phase9-render.php` / `-rtl-preset.php` render templates by `include`-ing them in CLI, so
`wp_head()`/`wp_body_open()` never fire and the theme's body-class, header, footer and skip-link
hooks never run. They fail identically **before** these changes and touch no Phase 21 code. The
same assertions were re-run against **real HTTP** and all passed.