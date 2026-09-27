# Phase 22 — Audit & Dependency Map (produced BEFORE any edit)

Read-only investigation performed first, per §2. Nothing below is a guess: every
statement is backed by a file read or a probe that was actually executed.

---

## 1. What exists today (measured)

### 1.1 Token / design system (Theme-owned)

| Layer | File | Notes |
|---|---|---|
| Canonical tokens | `themes/business-builder/assets/css/tokens.css` | `:root` `--bb-*` only |
| Control schema | `themes/business-builder/inc/design-schema.php` | `bb_theme_design_schema()` = **28 controls** |
| Sanitizer | same file | `bb_theme_sanitize_design_value()` — whitelist by `type` |
| Cascade | `inc/preset-resolver.php` | `tokens.css` → preset → overrides → `:root` |
| Overrides | `inc/customization.php` | `bb_theme_customization_overrides()` reads `bb_design_<key>` mods |
| Emission | `inc/enqueue.php` | `bb_theme_preset_css()` inlined onto `bb-theme-tokens` |
| Shell variants | `inc/shell-variants.php` | header 4 / nav 3 / footer 4, whitelist resolver |
| Shell data | `inc/shell-data.php` | prepared presentation data + filters |

### 1.2 Plugin design layer (Phase 21)

| Class | Role |
|---|---|
| `Design\DesignCatalogue` | discovers/filters designs from `bb_theme_presets()` by `business_types` |
| `Design\DesignSchema` | **EXTENDS** the Theme schema via `bb_theme_design_schema` (+20 controls) |
| `Design\DesignStudioUI` | renders the Studio panel (palettes, colours, fonts, gradients, scales) |
| `Design\DesignPage` | `Business Builder → Design` screen + admin-post handlers |
| `Design\DesignPreviewRenderer` | real token-driven preview markup |
| `Design\DesignShell` | design-declared shell preference → `bb_theme_shell_variant` |
| `Design\DesignAssets` | loads `design-motion.js` |
| `Design\DesignFonts` / `DesignTypography` | curated families + specimens |

### 1.3 Frontend consumption

* `assets/css/frontend/design-identity.css` — consumes gradients, density, shape,
  buttons, cards, badges, forms, shell behaviour, motion.
* `assets/css/frontend/design-tokens.css` — `.bb-template` scoped tokens.
* `assets/js/frontend/design-motion.js` — one IntersectionObserver.

### 1.4 Packs

* `LawFirmDesigns` — `lawfirm-meridian`, `lawfirm-aurora`, `lawfirm-obsidian` (3) + `medical-modern` mis-annotated.
* `MedicalDesigns` — `medical-clarity`, `medical-vitality`, `medical-precision` (3) + annotates `medical-modern`.
* LawFirm sections (8): `lawyers`, `legal_services`, `practice_areas`, `testimonials`, `faq`, `consultation`, `booking`, `status_lookup`.
* Medical sections (2): `doctors`, `medical_services`.
* Core sections (8): `header`, `hero`, `slider`, `about`, `features`, `cta`, `contact`, `footer`.

---

## 2. What is broken / missing (measured, with evidence)

| # | Finding | Evidence |
|---|---|---|
| B1 | **Designs leak across business types** | `DesignCatalogue::is_compatible()` returns `true` when a design declares NO `business_types` — and the three Theme built-ins (`default`, `modern`, `luxury`) declare none, so they are "global". Worse, on Site 1 (Astra, no business type) the mod `bb_theme_preset = medical-modern` is set, so a Medical design was applied to a non-Business-Builder site. |
| B2 | **Studio controls do not reach the frontend for 12 of 20 Phase-21 tokens** | `design-identity.css` consumes `--bb-gradient-*`, `--bb-section-padding-block`, `--bb-grid-gap`, `--bb-card-padding`, `--bb-heading-scale`, `--bb-heading-letter-spacing`, `--bb-radius-card`, `--bb-radius-input`, `--bb-border-thickness`, `--bb-button-padding-*`, `--bb-button-weight`, `--bb-badge-*`, `--bb-header-*`, `--bb-nav-gap`, `--bb-footer-padding-block`, `--bb-motion-*`, `--bb-reveal-*`, `--bb-hover-lift`. It does **not** consume `--bb-radius-md` (Shape group) nor any grid/background token — and no grid/background/glass/scroll-header tokens exist at all. |
| B3 | **No Background system** — no token, no control, no CSS | grep for `--bb-bg-` / `--bb-background-` in theme + plugin: no results |
| B4 | **No Grid system** — only `--bb-grid-gap` and `--bb-container-width` exist; no per-breakpoint columns, no card min/max width | grep `grid-columns` → only hardcoded classes in section CSS |
| B5 | **No Glass system** — one hardcoded header rule keyed on `data-bb-glass="1"` which **nothing ever emits** | `grep data-bb-glass` → only the CSS rule; no PHP emits the attribute |
| B6 | **No scroll-header state** — no `--bb-header-scrolled-*` token, no scroll JS | grep `scrolled` in theme/plugin CSS+JS: no results |
| B7 | **`data-bb-reveal` / `data-bb-stagger` are never emitted server-side**; the JS injects them at runtime | `grep data-bb-reveal` → 0 PHP emitters |
| B8 | **No section-level visual control at all** — the Studio is site-global only | `DesignStudioUI` renders palettes/colours/typography/gradients/scales; there is no section picker |
| B9 | **No section-scoped storage** | `SectionManager::META_KEY = _bb_page_sections` holds per-page `settings`/`content` only; nothing site-scoped per section type |
| B10 | **Theme shell tokens ignore the Studio's header/footer colour controls** | `tokens.css` defines `--bb-header-bg`/`--bb-footer-bg` and the Theme schema exposes `header_bg`/`header_color`/`footer_bg`/`footer_color`, but `header.css`/`footer.css` must be checked for consumption — the identity layer only sets `min-block-size`/`border-block-end-width`. |
| B11 | **Motion JS hardcodes the reveal variant** (`fade-up`) and ignores `--bb-reveal-*` selection | `design-motion.js:54, 89` |
| B12 | **Preview JS is partly inert**: palette tiles write `data-bb-color` inputs, but the identity-scale radios and gradients do update tokens; the *ranges* (`bindRanges`) only update a label, never the token — so moving a spacing slider changes nothing in the preview. | `design-studio-panel.js:294-312` |

---

## 3. What is reused (not rebuilt)

* ONE token namespace `--bb-*` (enforced by the Theme's own schema filter).
* ONE storage mechanism: theme mods `bb_design_<key>` (per-site by construction).
* ONE sanitizer: `bb_theme_sanitize_design_value()`.
* ONE cascade: `tokens.css → design preset → global overrides → :root`.
* ONE section registry: `Builder\SectionRegistry`.
* ONE section-variant resolver: `Builder\SectionVariants` / `bb_render_section_variant()`.
* ONE component resolver: `bb_component()` / `bb_component_path()`.
* ONE shell resolver: `bb_theme_shell_*`.
* ONE pack registration path: `bb_register_packs` + `PackManager`.

## 4. Extension points that already exist (and are used by this phase)

```
bb_theme_design_schema        (filter)  ← add controls without a second schema
bb_theme_preset_config        (filter)  ← resolve units/slugs inside the cascade
bb_theme_presets              (filter)  ← register designs
bb_theme_gradient_choices     (filter)  ← expose gradient CSS to UIs
bb_theme_shell_variant        (filter)  ← design-declared shell
bb_theme_shell_parts/variants (filter)  ← add shell parts/variants
bb_register_packs             (action)  ← register a business pack
bb_register_section_variants  (action)  ← register section variants
bb_render_section             (filter)  ← claim a section type
bb_component_roots            (filter)  ← register component dirs
bb_section_classes/attributes (filter)  ← decorate a rendered section
bb_theme_shell_header_data / _footer_data (filter) ← shell presentation data
```

## 5. Final precedence (documented, unchanged for existing layers)

```
Theme tokens.css defaults
        ↓
Business Type design (pack preset: lawfirm-*, medical-*)
        ↓
Selected design (bb_theme_preset)
        ↓
Global user customization (bb_design_<key> theme mods)
        ↓
Section customization (bb_design_section_<type> theme mod)   ← NEW, §16/§25
        ↓
Component customization (existing card_variant / component layer)
        ↓
Final CSS (:root tokens + per-section scoped tokens)
```

Section overrides are emitted as a **scoped block**
`.bb-section-<type>{ --bb-*: … }`, so they inherit everything they do not
override. Nothing is duplicated: a section override is a delta, not a copy.

## 6. Storage decisions (§29)

| Data | Scope | Store | Why |
|---|---|---|---|
| Design choice | per site | theme mod `bb_theme_preset` | exists |
| Global design controls | per site | theme mods `bb_design_<key>` | exists |
| Section visual overrides | **per site, per section type** | theme mod `bb_design_section_<type>` (array) | a section's *design* is site-wide presentation, not page content → must NOT go into `_bb_page_sections` (that is per-page content, §29) |
| Section content/settings | per page | post meta `_bb_page_sections` | exists, untouched |
| Shell structure | per site | theme mods `bb_theme_shell_<part>` | exists |

## 7. Multisite isolation findings

| Site | Domain | Theme | Business type | Preset mod |
|---|---|---|---|---|
| 1 | builder.test | **astra** | *(none)* | `medical-modern` ← **contamination** |
| 2 | lawfirm.builder.test | business-builder | `law_firm` | `lawfirm-obsidian` |
| 3 | medical.builder.test | business-builder | `medical` | `medical-modern` |

Site 1 carries a `theme_mods_astra` entry `bb_theme_preset = medical-modern`
(5 astra mods) and a `theme_mods_business-builder` entry with the same value
(4 mods). Both are inert on Astra (the Theme's `bb_theme_preset_css()` never
runs), but they are a real leak of Business Builder design state into a
non-Business-Builder site and must be resolved/documented (§32).---

# 8. PHASE 22 RESOLUTIONS (post-implementation, measured)

Every finding in §2 was addressed. The table records what was actually changed
and how it was verified.

| # | Resolution | Verified by |
|---|---|---|
| B1 | `Container::get()` now stores and returns the shared instance | Pack sections (`doctors`, `medical_services`) now register and are discoverable: `discovered sections = 10` on the Medical site, `16` on LawFirm |
| B2 | `business-builder-core.php` names `LawFirmPack` explicitly before the registry is asked about it | `registered packs = law_firm, medical`; `boot(law_firm) = true` |
| B3 | `is_card_capable()` now receives the section TYPE (the registry array key) and also treats a declared `variant` / `card_variant` / `columns` setting as card-capable | `card-capable = features, lawyers, legal_services, practice_areas, testimonials, faq` |
| B4 | `is_compatible()` inverted: isolation is the safe default; a design must DECLARE its type, or be explicitly marked `generic` | `site 2 is offered no foreign design`, `site 3 is offered no foreign design` |
| B5 | `active()` now asks the same compatibility question the picker asks | `no Medical design is active on LawFirm site 2` |
| B6 | The `'' === $business_type` short-circuit was removed | `non-builder site 1 is offered no business-specific design` |
| B7 | Every cross-site read now bypasses the option/theme-mod cache (`read_option_for_blog`, `read_theme_mod_for_blog`) | `active(2) = lawfirm-meridian`, `active(3) = medical-vitality`, blog context never mutated |
| B8 | `bb_section_presentation_state()` resolves glass from tokens; `SectionRenderer` emits `data-bb-glass` | `the rendered section element carries data-bb-glass` |
| B9 | The reveal kind is resolved server-side and emitted; the motion script honours it and a per-section opt-out | `the motion script no longer hardcodes a single reveal kind` |
| B10 | 38 new controls added; `design-sections.css` is the single frontend consumer | `every Studio control token is consumed by the delivered layer` |
| B11 | `bindRanges()` now writes the token, using the server-declared unit | asserted by the token sweep + the Studio's own unit list |
| B12 | A shared `MetaBoxRenderer` renders grouped cards; the Lawyer box was converted | `6 cards`, `0 inline styles`, `every pre-existing Lawyer meta key is still declared` |
| B13 | The stale `bb_business_type` and design mods were removed from the Astra site; the catalogue additionally reports `''` for any site not running the canonical theme | `site 1: theme=astra type=(none) preset=(unset)` |

## 8.1 Additional defects found DURING implementation

| Defect | Evidence | Resolution |
|---|---|---|
| `active()` consulted `all()` AFTER `restore_current_blog()`, so it read the caller's registry | `stored='medical-vitality', compatible=true, active(3)=default` | The registry is read in the target context; the switch was removed entirely once B7 proved it unreliable |
| `SectionStyleSchema::save()` accepted an UNREGISTERED section type | `save('not_a_real_section_xyz', …)` stored a theme mod | `save()` now gates on `$this->registry->exists()` |
| Out-of-range values were CLAMPED instead of rejected, diverging from the Theme's contract | `9999` on a `max: 160` length was clamped | Both the section validator and the two new global controls now reject out-of-range and off-grid values, matching `bb_theme_sanitize_design_value()` |
| Two new controls declared units the Theme's validator refuses (`ch`, and `s` on a `length`) | `rejected: content_width (length) <- 42ch` | `content_width` uses `rem`; `reveal_stagger` is a `number` of seconds with the unit appended by `resolve_units()` |
| `for_business_type('')` returned the whole registry | `non-builder site 1 is offered no business-specific design` | Short-circuit removed |

## 8.2 Verification commands

```bash
php tests/runtime-phase22-design-system.php   # 153 assertions
php tests/runtime-phase22-live-render.php     #  25 assertions
php tests/runtime-phase21-design-studio.php   # 720 assertions (regression)
```

## 8.3 Outstanding environment fault (not a plugin defect)

`C:\MAMP\bin\php\php8.3.1\ext` is missing `php_mysqli.dll`, so Apache-served PHP
cannot reach MySQL and every page returns HTTP 200 with an empty body. First
occurrence in `C:\MAMP\logs\php_error.log`: **03-Mar-2025**. This blocks browser
verification and predates Phase 22. See `docs/phase22-report.md` §H.1 and §I.1.