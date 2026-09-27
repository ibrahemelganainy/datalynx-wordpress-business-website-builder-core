# PHASE 14 — THEME DESIGN SYSTEM & GLOBAL CUSTOMIZATION — AUDIT

Read-only audit performed before any change. Evidence quoted from source.

## 1. Current architecture

```
tokens.css (:root defaults)
        ↓  (CSS cascade)
preset resolver  bb_theme_presets() → bb_theme_get_preset() → bb_theme_preset_config()
        ↓
bb_theme_customization_overrides()   ← the extension point (currently EMPTY)
        ↓
bb_theme_preset_css()   →  wp_add_inline_style('bb-theme-tokens')  (front end only)
        ↓
Theme shell + plugin `.bb-template` sections + LawFirm card components
```

**The cascade Phase 14 needs already exists.** `bb_theme_preset_config()` merges
`bb_theme_customization_overrides()` over the preset (preset-resolver.php:150-158), and
`bb_theme_preset_css()` emits `:root{…}` with a safe-value guard (rejects `{`, `}`, `<`, `;`,
newlines; only `--bb-*` properties). The override layer is simply unfilled.

## 2. Token inventory (existing — `assets/css/tokens.css`)

- **Colors:** primary(+hover/light/pale), secondary(+hover/light), accent(+hover),
  background, surface, surface-muted, text, text-muted, text-inverse, heading, border,
  border-strong, gray-50…900, surface-translucent, luxury(+soft/pale/strong/border),
  success/warning/danger/info (+light).
- **Typography:** font-body, font-heading, font-mono, font-size-xs…4xl, font-weight-light…bold,
  line-height-tight/normal/relaxed, font-heading-serif.
- **Spacing:** space-1…16 (4/8 system).
- **Layout:** container-width, container-width-wide, container-padding,
  section-spacing(-sm/-lg).
- **Radius:** sm/md/lg/xl/full, button-radius.
- **Shadows:** sm/md/lg.
- **Borders/motion:** border-width, transition-fast/base.
- **Shell:** header-bg, header-color, footer-bg, footer-color.
- **Breakpoints:** bp-sm…2xl.

## 3. Preset inventory

`default` / `modern` / `luxury` (preset-resolver.php). Each sets a **coherent subset**:
primary(+hover), accent, background, surface, surface-muted, text, border, font-heading,
radius-md/lg, shadow-md, button-radius, header-bg/color, footer-bg/color. They are
meaningfully different (modern = rounded/cool surfaces; luxury = gold + serif + tight radius),
not one-color diffs. **Preserved unchanged.**

## 4. Customization extension point

`bb_theme_customization_overrides()` (customization.php:27) — returns `array<token,value>`,
empty, filterable via `bb_theme_customization_overrides`. Consumed **only** by
`bb_theme_preset_config()`. **This is the correct central override mechanism; Phase 14 fills it.**

## 5. Storage mechanism

- Preset: theme mod `bb_theme_preset` (site-specific).
- Customizer: `customize_register` → a single `bb_theme_preset` select control.
- Resolution: emitted inline on `:root`, front end only (`is_admin()` guard).
- **No tables/CPTs/taxonomies.** Theme mods are the established mechanism → Phase 14 reuses them.

## 6. CSS cascade

`tokens.css` (defaults) → inline `:root` preset+override block. No `!important` wars; single
namespace `--bb-*`. The `.bb-template` scoped block in the **plugin** (`frontend/design-tokens.css`)
re-defines `--bb-*` under `.bb-template` for plugin sections — values match the theme, so they
inherit harmlessly. `--bb-fe-*` is a **bridge** in the plugin (`section-base.css`) mapping to
`--bb-*`.

## 7. Theme shell architecture

`header.php` / `footer.php` / templates → `template-parts/header|footer|navigation/*`.
Shell colours already tokenised (`--bb-header-bg`, `--bb-footer-bg`, …). `header.css`,
`footer.css`, `components.css`, `typography.css`, `layout.css`, `responsive.css`, `theme.css`
consume `--bb-*`.

## 8. Header / footer / navigation architecture

Single default variants (`bb_theme_header_variant()` / `bb_theme_footer_variant()` hooks exist
but ship only `default`). **Structural header/footer/nav VARIANTS = deferred** (need their own
architecture). Phase 14 only makes their **shared primitives** token-driven (already true).

## 9. Existing hardcoded design values

- Presets/tokens: all values are intentional defaults (correct — they ARE the token layer).
- `--bb-font-heading: inherit` in `tokens.css` while presets set concrete stacks — fine.
- Sporadic one-offs in `section-*.css` guarded by `var(…, fallback)` — acceptable.
- **Action:** no broad hardcode purge; Phase 14 adds *controls* for the tokens that already exist.

## 10. Naming conflicts

None found. Single `--bb-*` namespace; `--bb-fe-*` is a documented bridge. **Do not** rename.

## 11. Reusable primitives already present

Buttons (`.bb-button*`), forms (`.bb-form-control`), `.bb-card`, badges, container/section
spacing, radius/shadow tokens — all token-driven.

## 12. Recommended customization architecture

```
bb_theme_design_schema()          ← NEW: the control registry (token, type, label, group, default, sanitizer)
        ↓ (drives)
Customizer controls  (theme mod per control, e.g. bb_design_<key>)
        ↓
bb_theme_customization_overrides()  ← filled from the mods (only when set → "inherit" = absent)
        ↓ (existing)
bb_theme_preset_config() → bb_theme_preset_css() → :root
```
- **One source of truth** for controls (`bb_theme_design_schema()`).
- Each control has a **strict sanitizer by type** (color / length / select / font-stack).
- **Absent mod = inherit preset** (so "Reset" = delete the mod, which is the documented semantics).
- Preset switching keeps overrides (overrides are independent), matching §23.

## 13. Files that MUST be modified

- `inc/customization.php` — fill `bb_theme_customization_overrides()`, register the controls.
- `inc/preset-resolver.php` — (only if needed) expose resolved config helpers.
- `functions.php` — require a new schema file.
- `assets/css/*` — only if a token needs to be added to `tokens.css` for a new control.

## 14. Files that MUST NOT be modified

Plugin builder (`SectionRegistry`, `SectionRenderer`, `PageBuilderAjax`, `PageManager`),
`page-admin.js`, all LawFirm sections/components/variants, business systems, DB schema.

## 15. Backward-compatibility risks

- Changing an existing token name → **avoid** (never rename; only add/add controls for existing tokens).
- Emitting an override for a token a preset already sets → must remain a **merge**, not a replace.
- `bb_theme_preset_css()` guard already prevents CSS injection; keep it.

## 16. Multisite risks

Theme mods are **per-site by construction** (`get_theme_mod`). Verified pattern already in use
for `bb_theme_preset`. No global option introduced → isolation preserved.

## 17. Security / sanitization risks

- Each control type needs a strict sanitizer (color → `sanitize_hex_color`/rgba allow-list;
  length → numeric + unit within min/max; select → whitelist; font → allow-list stack).
- `bb_theme_preset_css()` already blocks declaration-breaking characters. Keep.
- No "Custom CSS" field (§26).

## 18. Testing requirements

Preserve Phase 9 (24/24), 10 (31/31), 11 (39/39 + 12/12), 12 (39/39), 13 (127/127) and the
business suites. Add `tests/runtime-phase14-theme-design-system.php` covering resolution,
overrides, sanitization, reset, preset switching, output, RTL, multisite, backward compat.

## Decision

**Implement Phase 14 as a fill-in of the existing `bb_theme_customization_overrides()` layer
driven by one new design-control schema, surfaced through the WordPress Customizer** (already
the theme's settings mechanism) — no new storage, no builder changes, no token renames.

**Deferred:** header/footer/nav structural variants; theme export/import; preset marketplace;
per-page theme overrides; live design preview. Documented, not built.