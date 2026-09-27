# PHASE 21 — DESIGN CATALOGUE, SITE DESIGN UI & FULL CUSTOMIZATION — AUDIT

Read-only audit performed **before** any Phase 21 code. Every value below is **measured** from
the live system (`tests/_phase21-audit.php`), not assumed.

Environment: WordPress Multisite · `builder.test` · site 1 Astra (no type), site 2 LawFirm
(`law_firm`), site 3 Medical (`medical`).

---

## 0. Audit verdict — WHAT EXISTS / CAN BE REUSED / IS MISSING / MUST CHANGE / MUST NOT CHANGE

### WHAT EXISTS (and is correct)

| Concern | Implementation | Evidence |
| --- | --- | --- |
| Token namespace | `--bb-*` only | `preset-resolver.php`, 17 tokens per preset |
| Preset registry | `bb_theme_presets()` + `bb_theme_presets` filter | filter confirmed present |
| Preset resolver | `bb_theme_get_preset()`, `bb_theme_preset_config()`, `bb_theme_preset_css()` | filters `bb_theme_active_preset`, `bb_theme_preset_config` |
| Active design storage | theme mod **`bb_theme_preset`** (per site) | blog 2=`default`, blog 3=`medical-modern` |
| Customization storage | theme mods **`bb_design_<key>`** (per site) | `bb_theme_design_mod_name()` |
| Design schema | **28 controls** in 5 groups | colors 14, typography 5, layout 2, radius 3, shell 4 |
| Override collection | `bb_theme_customization_overrides()` (+ filter) | blog 1 = 0 overrides |
| Reset (all) | `bb_theme_reset_all_overrides()` | measured: removed=1 → preset value restored |
| Reset (one) | `remove_theme_mod( bb_theme_design_mod_name( $key ) )` | same mechanism |
| Safe fallback | resolver validates **after** filtering, falls back to `default` | `bb_theme_get_preset()` |
| Shell variants | header 4, navigation 3, footer 4 | `bb_theme_shell_options()` |
| Section layouts | `SectionVariants` whitelist resolver | Phase 11 |
| Component variants | `bb_component_path()` whitelist resolver | Phase 10/12 |
| Pack preset contribution | `bb_theme_presets` / `bb_theme_preset_config` filters | proven in Phase 19 (`medical-modern`) |

### CAN BE REUSED (no new architecture)

- The **entire** preset + customization + reset cascade — Phase 21 only *adds presets*.
- `bb_theme_preset` as the active-design value — **no new storage key.**
- The existing `bb_design_<key>` theme mods as the customization store.
- The existing admin pattern (`admin_menu` + `admin_post_*` + `check_admin_referer` + caps).
- The existing Card/Component API for preview rendering.

### WHAT IS MISSING (the real Phase 21 gaps)

1. **No catalogue metadata on presets.** Measured: `default` / `modern` / `luxury` carry only
   `label` — there is **no `business_types`**, no `description`, no `default` flag.
   `array_keys(bb_theme_presets())` in the audit output shows `business_types=(none)` for all
   three. A Design therefore cannot declare which Business Types it belongs to.
2. **Only 3 global presets exist**, none of them business-specific (except Medical's single
   proof preset from Phase 19). The product target is 3–5 **per Business Type**.
3. **No site-facing Design UI.** There is no `Business Builder → Design` screen; the only
   customization surface is the WordPress Customizer.
4. **No preview of another Design.** The Customizer previews what is already active; there is
   no way to see Design B while Design A is live.

### MUST CHANGE (minimum)

- **Extend** `bb_theme_presets()` entries with optional, backward-compatible metadata
  (`description`, `business_types`, `preview`, `default`, `version`). Purely additive.
- **Add** a filter for Business-Type-aware catalogue discovery that does not require the
  Design layer to know business types.
- **Add** pack-contributed designs (LawFirm × 5, Medical × 5) through the existing
  `bb_theme_presets` filter — **no Core/Theme edits.**
- **Add** one site-level Design screen that consumes the registry.
- **Add** a read-only preview endpoint.

### MUST NOT CHANGE

- `--bb-*` namespace · token names · the resolver cascade · `bb_theme_preset` as the active
  value · `bb_design_<key>` mods · `bb_theme_reset_all_overrides()` · shell variants · the
  Customizer · SectionRenderer / SectionRegistry / SectionVariants · `PackManager` ·
  `BusinessType` · Phase 20 network authority · both packs' business logic.

---

## 1. Design system (measured)

### 1.1 Token vocabulary (17 tokens per preset, `--bb-*`)

```
--bb-color-primary          --bb-color-primary-hover   --bb-color-accent
--bb-color-background       --bb-color-surface         --bb-color-surface-muted
--bb-color-text             --bb-color-border          --bb-font-heading
--bb-radius-md              --bb-radius-lg             --bb-shadow-md
--bb-button-radius          --bb-header-bg             --bb-header-color
--bb-footer-bg              --bb-footer-color
```

**A Design is exactly a map of these keys.** No new token namespace is needed: every visual
axis the product asks for (colors, typography, spacing, radius, shell, surfaces, shadows)
is already expressible.

### 1.2 Cascade (locked, unchanged)

```
assets/css/tokens.css defaults
        ↓
bb_theme_get_preset()   → bb_theme_presets() → bb_theme_active_preset filter → validated fallback 'default'
        ↓
bb_theme_preset_config() = preset merged with bb_theme_customization_overrides()
        ↓
bb_theme_preset_css()   → inline :root (front end only), skipping non --bb- / empty / dangerous values
        ↓
shell · sections · components
```

### 1.3 Customization controls (28, measured)

| Group | Count | Keys |
| --- | --- | --- |
| colors | 14 | `color_primary`, `color_primary_hover`, `color_secondary`, `color_accent`, `color_background`, `color_surface`, `color_surface_muted`, `color_text`, `color_text_muted`, `color_heading`, `color_border`, `color_success`, `color_warning`, `color_danger` |
| typography | 5 | `font_heading`, `font_body`, `font_size_md`, `font_weight_bold`, `line_height_normal` |
| layout | 2 | `container_width`, `section_spacing` |
| radius | 3 | `radius_md`, `radius_lg`, `button_radius` |
| shell | 4 | `header_bg`, `header_color`, `footer_bg`, `footer_color` |

Storage: theme mod `bb_design_<key>` (e.g. `bb_design_color_primary`). Verified against the
Phase 19/20 suites, which already set `bb_design_color_primary` and observed it override the
preset.

**Conclusion for §14:** the full customization surface the product asks for **already exists**.
Phase 21 must *expose* it, not invent it. No new control is required.

---

## 2. The §13 decision — measured, not assumed

The phase demands the preset-switching vs. customization behaviour be **audited, not
assumed**. Measured on site 3:

```
set preset = medical-modern, override primary = #b91c1c
  → resolved --bb-color-primary = #b91c1c          (override wins)

switch preset to default, override UNTOUCHED
  → resolved --bb-color-primary = #b91c1c          (override STILL wins)

bb_theme_reset_all_overrides()
  → removed = 1
  → resolved --bb-color-primary = #2563eb          (preset inherited again)
```

### Finding: the system currently behaves as **Option B** — overrides survive a preset switch.

Theme mods (`bb_design_*`) are independent of the preset value (`bb_theme_preset`), so changing
the preset does **not** clear overrides. A site that customized `primary = red` will keep red
after switching Design — which means **Design B would silently appear with Design A's
customizations applied**.

### Decision (explicit, predictable, reversible)

Present Design selection as **two clearly-labelled operations** rather than changing the
underlying (correct, standard WordPress) storage semantics:

| Operation | Behaviour | Why |
| --- | --- | --- |
| **Use this Design** (default) | Sets `bb_theme_preset` **and** calls the existing `bb_theme_reset_all_overrides()`, so the new Design appears exactly as authored | A Design is a *prepared visual identity*; it must not inherit another Design's leftovers (§13 "Design A customizations do not silently corrupt Design B") |
| **Keep my customizations** (explicit opt-in offered on the same screen) | Sets only `bb_theme_preset`; overrides are preserved | Honours Option B for an admin who deliberately wants to keep their values |

This is **explicit, predictable and reversible**: the reset uses the *existing* reset API, the
opt-out preserves the *existing* behaviour, and the UI states which will happen. No storage
semantics are changed and nothing is silently destroyed without the admin choosing it.

---

## 3. Existing UI

- **Site admin:** `admin_menu` → top-level `business-builder` (cap `manage_options`) +
  `business-builder-settings` submenu + Phase 20's `business-builder-domain` submenu.
  So **`Business Builder → Design` is a natural new submenu** — the audit found the exact
  extension point the phase asks for.
- **Customizer:** `bb_theme_design_sections()` drives 28 controls with
  `bb_theme_color_choices()` (13 swatches) and `bb_theme_font_stack_choices()`
  (system / serif / sans / mono). **One authoritative customization system already exists**;
  the Design screen must link to it, not duplicate it (§15).
- **Network admin:** Phase 20 `BusinessTypeGuard` (`manage_network`), `SiteProvisioner`,
  `DomainRegistry`, site list showing Design per site. Phase 21 must not weaken these.

---

## 4. Layout system (must stay independent)

| Layer | Owner | Resolver | Design-influence |
| --- | --- | --- | --- |
| Section Layout | Plugin `SectionVariants` | whitelist → `default` | **none** |
| Grid/columns | per-section `columns` setting | schema `select` 1–4 | **none** |
| Component Variant | Theme `bb_component_path()` | whitelist → base file | **none** |

Audit conclusion: **no coupling exists** between Design and any layout layer. Phase 21 must
preserve that — a Design writes only `--bb-*` tokens and `bb_theme_preset`.

---

## 5. Pack design integration

- LawFirm contributes **no** presets today.
- Medical contributes exactly one (`medical-modern`) via `add_filter('bb_theme_presets')` +
  `add_filter('bb_theme_preset_config')` — Phase 19's proven mechanism.
- Measured: when the Medical pack is booted, `bb_theme_presets()` returns
  `default, modern, luxury, medical-modern`.

**Phase 21 uses this same mechanism for the full catalogues** — packs register their Designs,
Core/Theme stay business-agnostic.

Note the context caveat: `bb_theme_presets()` reflects the pack booted in the **current**
context. In a real request on a LawFirm site, LawFirm's filter runs; on a Medical site,
Medical's. This is correct behaviour and is why the Design UI must *also* filter by the site's
own business type.

---

## 6. Multisite

Measured: theme mods are per-site (`blog 1 preset=''`, `blog 2 'default'`, `blog 3
'medical-modern'`), each with its own override count. The Phase 19 contamination lesson stands:
every site-local write runs inside `switch_to_blog()` with `restore_current_blog()` in a
`finally`.

---

## 7. Implementation plan (minimum required)

```
KEEP    the theme's preset/customization/reset cascade — untouched
EXTEND  preset metadata (additive, backward compatible)
ADD     packs/LawFirm/Design/LawFirmDesigns.php   5 designs (filter-contributed)
ADD     packs/Medical/Design/MedicalDesigns.php   5 designs (extends Phase 19's class)
ADD     includes/Design/DesignCatalogue.php       generic, registry-driven discovery + filtering
ADD     includes/Design/DesignPage.php            Business Builder → Design (site admin)
ADD     includes/Design/DesignPreview.php         read-only preview endpoint
MODIFY  includes/Core/Plugin.php + Autoloader.php wiring only
```

**No Theme file is modified.** Metadata lives in the registry that already exists; the new
catalogue class only *reads* registries and never names a business type.

### Designs to ship (3–5 per Business Type, visually distinct, no new HTML)

**LawFirm** — Executive · Legal Luxury · Modern Counsel · Corporate Law · Minimal Legal
**Medical** — Clinical · Modern Care · Medical Luxury · Clean Health · Specialist

Each differs through real tokens only: palette, heading font/serif, radius language, shadow
depth, surface treatment, header/footer treatment. Every token used already exists.

---

## 8. Risks identified

| Risk | Mitigation |
| --- | --- |
| Design count inflated with colour swaps | Each design changes ≥4 independent axes (palette, font, radius, shadow/surface, shell) |
| Preset metadata breaks existing presets | Metadata is additive and optional; the resolver keeps validating exactly as before |
| Design UI becoming a second Customizer | The screen only *selects*; a link opens the existing Customizer for editing |
| Preview mutating state | Preview is GET-only and read-only; it renders with an overridden preset array in memory and saves nothing |
| Cross-site leakage | All writes inside `switch_to_blog`; every mutation targets `get_current_blog_id()` |
| Business type immutability weakened | The Design screen posts **only** `preset`; Phase 20's guard is untouched and a regression test re-asserts it |

---

## 9. Audit → Phase 21 requirement mapping

| Phase requirement | Audit finding | Action |
| --- | --- | --- |
| §6 catalogue, 3–5 per type | only 3 global + 1 medical | **add** 5 + 5 pack designs |
| §7 extend registry | registry is a filter | **extend** entries with metadata |
| §8 business-type compat | **no such field** | **add** optional `business_types` |
| §9 default design | `default` always present + validated fallback | **keep** |
| §10 site design UI | `business-builder` menu exists | **add** submenu |
| §11 preview | no preview of a non-active design | **add** read-only preview |
| §13 switching semantics | Option B (overrides survive) | **make explicit** via two labelled actions |
| §14 full customization | 28 controls already exist | **keep + link** |
| §15 one customization system | Customizer is authoritative | **do not duplicate** |
| §16 reset | 3 APIs exist (`remove_theme_mod`, `reset_all_overrides`, set `default`) | **expose all three distinctly** |
| §17–20 layout independence | no coupling found | **preserve + test** |
| §26 security | Phase 20 pattern exists | **reuse** |
| §27 isolation | per-site theme mods | **reuse + test** |
| §33–35 no new theme/namespace/storage | confirmed unnecessary | **add none** |

Audit complete. **Proceed to implementation — extend the catalogue, do not rebuild the system.**