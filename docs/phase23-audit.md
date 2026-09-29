# Phase 23 — Enterprise Visual Control System: AUDIT (produced BEFORE any edit)

Read-only investigation, per the specification's "audit first" rule. **Every row below was
produced by executing code, not by reading it and guessing.** The measurement tool is
committed as `tests/_phase23-audit.php` (read-only); its raw output is
`tests/_phase23-audit.txt` and can be regenerated at any time:

```bash
php tests/_phase23-audit.php
```

## 1. What the audit actually measured

| Measurement | Method |
|---|---|
| Control inventory | `bb_theme_design_schema()` (Theme) + `DesignSchema::controls()` (plugin) |
| Storage per control | `bb_theme_design_mod_name()` — one theme mod `bb_design_<key>` per control |
| Frontend consumer | every `var(--token)` / quoted token reference across 286 source files, split into **FE** (css/js) and **PHP** (author-side), minus the token's own declaration |
| Duplicate source of truth | any token claimed by more than one control |
| Orphans | controls with **FE = 0** |
| Sections / variants / capabilities | `SectionRegistry::get_all()`, `bb_section_variants()->all()` |
| Multisite isolation | `switch_to_blog()` walk over `get_sites()` |

## 2. Control inventory — headline numbers

| Layer | Count | Owner | Storage |
|---|---|---|---|
| Global controls (Theme) | 28 | `themes/business-builder/inc/design-schema.php` | theme mod `bb_design_<key>` |
| Global controls (plugin extension) | 55 | `includes/Design/DesignSchema.php` | same mods, same sanitizer |
| **Global total** | **83** | one schema, one sanitizer, one namespace | per-site by construction |
| Section-scoped controls | 39 across 9 groups | `includes/Design/SectionStyleSchema.php` | theme mod `bb_design_section_<type>`, token prefix `--bb-section-` |
| Registered core sections | 8 | `SectionRegistry` | post meta `_bb_page_sections` |
| Registered core section variants | **0** | `SectionVariants` | — |

### 2.1 Duplicate / conflicting controls

| Check | Result |
|---|---|
| Two controls writing the same token | **none** (measured across all 83) |
| Two token names for one visual property | **3 found, already bridged in Phase 22** — see §3 |
| Conflicting controls (same property, different storage) | **none found** |

### 2.2 Orphaned global controls (FE = 0) — "UI that changes nothing"

| Control | Token | Group | Verdict |
|---|---|---|---|
| `color_danger` | `--bb-color-danger` | colors | **Missing consumer** — declared by the Theme, read by no stylesheet. |
| `reveal_kind` | `--bb-reveal-kind` | motion | **Working, but INDIRECT.** Read server-side by `bb_section_presentation_state()`, emitted as `data-bb-reveal`, consumed by `design-sections.css`. Measured FE=0 only because the consumption is an attribute, not a `var()`. |
| `hover_effect` | `--bb-hover-effect` | motion | Same indirect path via `data-bb-hover`. |

### 2.3 Orphaned SECTION controls (FE = 0) — these ARE defects

| Section control | Token | Consequence |
|---|---|---|
| Glass → blur | `--bb-section-glass-blur` | Only **read in PHP** to decide `data-bb-glass=on/off`. The CSS applies the GLOBAL `--bb-glass-blur`, so "Section → Glass → strength" is a control that changes nothing. |
| Glass → transparency | `--bb-section-glass-opacity` | **No consumer anywhere.** |
| Glass → border visibility | `--bb-section-glass-border-opacity` | **No consumer anywhere.** |
| Motion → entrance speed | `--bb-section-reveal-duration` | **No consumer.** Per-section reveal timing is inert. |
| Motion → entrance delay | `--bb-section-reveal-delay` | **No consumer.** Per-section delay is inert. |
| Motion → entrance kind | `--bb-section-reveal` | Indirect (PHP → `data-bb-reveal`), OK. |
| Motion → hover | `--bb-section-hover` | Indirect (PHP → `data-bb-hover`), OK. |

## 3. Property-level duplication already resolved (Phase 22 aliases)

Three visual properties had **two token names**: the name the Theme consumes and the name the
Studio writes. Phase 22 (committed; verified in `assets/css/frontend/design-tokens.css`)
already collapsed each pair onto ONE control plus an alias on `:root`:

| Property | Theme's name (authoritative) | Studio's control | Alias |
|---|---|---|---|
| Border width | `--bb-border-width` | `border_thickness` | `--bb-border-width: var(--bb-border-thickness)` |
| Container gutter | `--bb-container-padding` | `container_padding_block` | `--bb-container-padding: var(--bb-container-padding-block)` |
| Section rhythm | `--bb-section-spacing` | `section_padding` | `--bb-section-spacing: var(--bb-section-padding-block)` |

Phase 23 must **not** re-introduce a second control for any of these.

## 4. Inheritance cascade (measured, in emission order)

```
1. Theme tokens.css  :root defaults                                     (theme, :root)
2. design preset `bb_theme_presets` config `tokens`                     (theme + packs)
3. slug/unit resolution  (DesignSchema::resolve_gradients/resolve_units) (plugin filter)
4. global user overrides  theme mods `bb_design_<key>`                   (Studio writes these)
5. SECTION overrides  theme mod `bb_design_section_<type>`               (print_styles, scoped)
6. component layer  card_variant / component templates                   (existing)
7. rendered markup reads tokens to emit data-bb-* attributes              (presentation state)
```

Steps 1–4 are emitted as one inline `:root { … }` block attached to the `bb-theme-tokens`
handle. Step 5 is a scoped `.bb-section-<type> { … }` block emitted **after** it, so it wins
by source order with no `!important`. This ordering is load-bearing, which is why `bb-frontend`
declares `bb-theme-tokens` as a dependency (see the PHASE 23 comment in `includes/Core/Plugin.php`).

**Isolation:** steps 1–6 are per-site by construction (theme mods and post meta are per-blog).
Measured per-site state: site 1 = `astra`, no business type, 0 design mods; site 2 =
`business-builder` / `law_firm`, 16 design mods; site 3 = `business-builder` / `medical`,
## 5. Sections, capabilities and presentation (measured)

| Section | Category | `supports` | settings | content fields |
|---|---|---|---|---|
| header | navigation | — | 0 | 7 |
| hero | content | title, description, button, image, background | 2 | 6 |
| slider | marketing | — | 4 | 1 |
| about | content | title, description, image, button | 1 | 5 |
| features | content | title, description, items, icons | 1 | 4 |
| cta | marketing | title, description, button, background | 1 | 4 |
| contact | communication | title, description, phone, email, address, whatsapp, map | 1 | 6 |
| footer | navigation | — | 0 | 6 |

### 5.1 Gaps found

| # | Gap | Evidence |
|---|---|---|
| G1 | **No Services section at all** | absent from `SectionRegistry` |
| G2 | **No core section registers a variant** | `bb_section_variants()->all()` is empty, so the Studio's layout picker appears only for pack sections; hero / slider / features / services have ONE layout |
| G3 | **`supports` is decorative** | nothing reads `supports`; card controls are offered on a heuristic (`is_card_capable()`), so an "icons" capability has no control and a non-card section can be offered card controls |
| G4 | **No icon vocabulary** | grep for `fontawesome` / `fa-` across 286 files: 0 matches; `features` items accept an attachment `image`, not an icon |
| G5 | **Hero has no background / overlay / content image controls** | hero settings = `alignment`, `min_height` only |
| G6 | **Hero cannot present as a slider** | `slider` is a separate marketing section |
| G7 | **No scrollbar control** | grep for scrollbar tokens: 0 results |
| G8 | **No global motion master** | `motion` group has tempo/kind/duration/distance/stagger/lift/hover but no single Off/Subtle/Balanced/Expressive choice and no easing |
| G9 | **Global Layout incomplete** | `container_width`, `section_padding`, `container_padding_block`, `grid_*`, `content_width`, `section_align` exist; no site gutter, no space-between-sections, no boxed/full layout mode |
| G10 | **Global Typography incomplete** | no body/heading weight, letter-spacing, heading line-height or heading case |
| G11 | **Navbar incomplete** | no alignment, link size/weight/tracking, hover/active colour, active indicator, nav radius or logo height |
| G12 | **`--bb-color-danger` unused** | FE = 0 (§2.2) |

## 6. What is REUSED (nothing is rebuilt)

* ONE control schema: `bb_theme_design_schema` (the plugin EXTENDS it).
* ONE sanitizer: `bb_theme_sanitize_design_value()`.
* ONE storage mechanism: `bb_design_<key>` theme mods (per site).
* ONE token namespace: `--bb-*`.
* ONE cascade and ONE emitter (Theme `:root`, plugin scoped block).
* ONE section registry, ONE variant resolver, ONE component resolver, ONE pack registry.
* ONE frontend entry stylesheet (`assets/css/frontend.css`) with `design-sections.css` as the
  single Phase-22/23 consumer.

## 7. Phase 23 work queue (derived strictly from the gaps above)

| Order | Item | Fixes |
|---|---|---|
| 1 | Consume the 5 orphaned section tokens (glass blur/opacity/border, reveal duration/delay) | §2.3 |
| 2 | Consume `--bb-color-danger` | G12 |
| 3 | Global Layout: `layout_mode`, `site_margin`, `section_gap` | G9 |
| 4 | Global Typography: `body_weight`, `heading_weight`, `body_letter_spacing`, `heading_line_height`, `heading_transform`, `body_line_height` | G10 |
| 5 | Navbar: `nav_align`, `nav_link_size`, `nav_link_weight`, `nav_link_tracking`, `nav_link_hover`, `nav_link_active`, `nav_indicator`, `nav_radius`, `logo_height` | G11 |
| 6 | Global Motion: `motion_mode` (Off/Subtle/Balanced/Expressive), `motion_ease`, `motion_entrance` | G8 |
| 7 | Scrollbar: `scrollbar_width`, `scrollbar_track`, `scrollbar_thumb`, `scrollbar_thumb_hover` | G7 |
| 8 | Capabilities contract on `SectionRegistry`, honoured by `SectionStudioUI` / `SectionStyleSchema` | G3 |
| 9 | Font Awesome icon library + `icon` control type + picker UI (inline SVG, no library asset) | G4 |
| 10 | Global Services section (presentation modes, cards, icons, CTA) | G1 |
| 11 | Core section variants for hero / slider / features / services | G2 |
| 12 | Hero upgrade: background layers, overlay, content image, slider mode | G5, G6 |
| 13 | Section borders off by default | spec |

Nothing in this queue rebuilds an existing system: every item either adds a control to the
EXISTING schema, or consumes a token an existing stylesheet already declares.

---

## 8. Post-implementation verification (same tools, re-run after the work)

The audit script is not a one-off: it is committed and was re-run AFTER the changes, so every
claim in the final report is a measurement, not an intention.

| Measurement | Before | After |
|---|---|---|
| Global controls | 83 | **106** (23 added, none removed) |
| Controls writing a token already owned by another control | 0 | **0** |
| Section-scoped controls with NO frontend consumer | 5 | **0** |
| Global controls with NO consumer (direct or indirect) | 3 | **0** |
| Core section variants registered | 0 | **5** (the `services` layouts) |
| Core sections | 8 | **9** (`services`) |

### 8.1 The five measured section orphans are closed

| Token | Now consumed by |
|---|---|
| `--bb-section-glass-blur` | `design-sections.css` — the section-scoped glass blur |
| `--bb-section-glass-opacity` | `design-sections.css` — the section-scoped glass tint |
| `--bb-section-glass-border-opacity` | `design-sections.css` — the section-scoped glass border |
| `--bb-section-reveal-duration` | `design-sections.css` — per-section entrance speed |
| `--bb-section-reveal-delay` | `design-sections.css` — per-section entrance delay |
| `--bb-color-danger` (global orphan) | `design-sections.css` — error states |

### 8.2 Indirect consumers are now MEASURED, not assumed

`tests/_phase23-audit.php` prints an **INDIRECT CONSUMERS** table. A keyword control
(`boxed`, `pill`, `off`) cannot be branched on from CSS, so it is delivered in two steps:

```
token  ->  PHP state emitter  ->  class / data attribute  ->  CSS
```

For each such token the tool asserts BOTH that the token is read by a state emitter AND that
the class/attribute it drives has a stylesheet consumer. All nine are `CONSUMED (indirect)`:

`--bb-reveal-kind`, `--bb-section-reveal`, `--bb-hover-effect`, `--bb-section-hover`,
`--bb-layout-mode`, `--bb-nav-position`, `--bb-nav-indicator`, `--bb-motion-mode`,
`--bb-bg-overlay-opacity`.

### 8.3 Two intentional cascade pairs (documented, not accidental)

| Token | Global control (site default) | Section control (override) |
|---|---|---|
| `--bb-section-align` | `section_align` | `align` |
| `--bb-section-padding-block` | `section_padding` | `padding_block` |

The section value wins because it is emitted later in a narrower scope, which is the intended
"site default, section override" cascade — the same relationship the specification asks for.
The test suite asserts this set is CLOSED, so a new, unintended collision fails the build.

## 9. Verification commands

```bash
php tests/_phase23-audit.php                  # control inventory + consumer map
php tests/runtime-phase23-design-system.php   # 108 assertions
php tests/runtime-phase22-design-system.php   # 153 assertions (regression)
php tests/runtime-phase22-live-render.php     #  25 assertions (regression)
php tests/runtime-phase21-design-studio.php   # 720 assertions (regression)
```



