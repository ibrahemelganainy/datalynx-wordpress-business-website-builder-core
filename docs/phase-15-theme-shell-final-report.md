# PHASE 15 — THEME SHELL VARIANTS (Header / Navigation / Footer) — FINAL REPORT

## 1. Audit Results

Full audit written **before** any change: `docs/phase-15-theme-shell-audit.md`.

Key findings (re-verified against source, not carried over from Phase 14):

- The Theme shell is `header.php` / `footer.php` → `template-parts/header|footer|navigation/*`.
- `header.php` already called `get_template_part('template-parts/header/header', bb_theme_header_variant())`;
  the slug came from `bb_theme_header_variant()` / `bb_theme_footer_variant()` — **filters** in
  `inc/preset-resolver.php` that returned only `default` and used `sanitize_file_name()` as the
  only guard.
- Navigation was a fixed one-line part (`primary.php` → `bb_theme_navigation()`); **no** navigation
  variant hook existed.
- **No Theme-side whitelist registry/resolver existed** — the slug went straight to
  `get_template_part()`, so an unknown slug rendered nothing (and would notice in debug). The
  theme needed the same registry+resolver discipline the plugin ships for section variants.
- Header/footer colours were already token-driven (`--bb-header-bg`, `--bb-footer-bg`, …);
  **zero hardcoded colours** in `header.css`/`footer.css`.
- One JS file (`assets/js/theme.js`), dependency-free, bound to `.bb-menu-toggle` + `#bb-primary-nav`.
- One CSS pipeline (`inc/enqueue.php`), front-end only, dependency-chained.

## 2. Shell Architecture

```
site owner choice (theme mod per part)
        ↓
bb_theme_shell_part_variant( 'header'|'navigation'|'footer' )
        ↓  (legacy bb_theme_header_variant / footer_variant filters still honoured)
bb_theme_shell_resolve( part, requested )      ← sanitize_key + whitelist
        ↓
bb_theme_shell_variants()[ part ][ slug ]['template']  ← REGISTERED absolute path only
        ↓
include  ( bb_theme_shell_render(part) )
```

`header.php` → `bb_theme_shell_render('header')`; `footer.php` → `bb_theme_shell_render('footer')`;
each header variant renders the selected navigation via `bb_theme_shell_render('navigation')`.

The three parts are **independent** (no combined slug): header, navigation and footer are selected
separately and any combination is valid.

Files: `inc/shell-variants.php` (registry + resolver + renderer), `inc/shell-data.php`
(prepared presentation data), the variant templates, `assets/css/shell-variants.css`.

## 3. Variant Catalog

`bb_theme_shell_variants()` is the single source of truth (drives both the resolver and the
Customizer choices).

### Header
| slug | label | structural difference | mobile behaviour |
| --- | --- | --- | --- |
| `default` | Default | Existing row: brand left, nav right, toggle right | toggle (unchanged) |
| `centered` | Centered | Brand centered on its own row; navigation centered beneath | toggle (wraps to the default row layout below 1024px) |
| `split` | Split | Three-column grid: brand start, nav center, actions end | toggle (falls back to space-between row below 1024px) |
| `minimal` | Minimal | Compact single bar; inline nav hidden on desktop | toggle on **all** widths; the toggle opens the nav panel (desktop too) |

### Navigation
| slug | label | structural difference | mobile behaviour |
| --- | --- | --- | --- |
| `default` | Default | Existing inline menu (`bb-nav`) | toggle (unchanged) |
| `centered` | Centered | Menu centered as a group | toggle (unchanged) |
| `minimal` | Minimal | Tighter gap, single-line (no wrap) | toggle (unchanged) |

### Footer
| slug | label | structural difference | mobile behaviour |
| --- | --- | --- | --- |
| `default` | Default | Existing auto-fit columns (brand/tagline, menu) | stack (auto-fit) |
| `columns` | Multi-column | Fixed grid `2fr 1fr` (brand + links) | stack at ≤767px |
| `centered` | Centered | One centered column: brand, horizontal menu, copyright | stack (still centered) |
| `minimal` | Minimal | Single compact bar: brand + copyright, no menu | stack at ≤767px |

Every variant is a genuine layout/composition change — none is a colour-only difference.

## 4. Header

- Templates: `template-parts/header/header-{default,centered,split,minimal}.php`.
- **Default is byte-identical** to the pre-Phase-15 header (same `<header id="bb-site-header"
  class="bb-site-header">`, same helpers, same order). Verified: live homepage shell HTML is
  identical before/after under the default configuration (§21).
- Non-default variants add one structural class (`bb-header--centered|split|minimal`); the CSS keys
  off that class. **No** markup contract change: the toggle, `#bb-primary-nav` and the actions slot
  are identical.

## 5. Navigation

- Templates: `template-parts/navigation/nav-{default,centered,minimal}.php`.
- All three delegate to the **single** `bb_theme_navigation()` helper — no `wp_nav_menu()` logic is
  duplicated per variant. The default nav template emits exactly the previous call
  (`bb_theme_navigation('primary','bb-nav')`) — verified byte-identical to that helper's output.
- Variants only add a modifier class (`bb-nav--centered|minimal`). `#bb-primary-nav` and `.bb-nav`
  (the mobile-collapse contract) are preserved on every variant.
- `template-parts/navigation/primary.php` is kept as a compatibility entry point and now delegates
  to `bb_theme_shell_render('navigation')`.

## 6. Footer

- Templates: `template-parts/footer/footer-{default,columns,centered,minimal}.php`.
- The menu and the copyright line are centralised in `bb_theme_shell_footer_menu()` /
  `bb_theme_shell_copyright()` so no variant duplicates them.
- **Default is byte-identical** to the pre-Phase-15 footer (identical markup; verified live).
- Non-default variants add `bb-footer--columns|centered|minimal`.

## 7. Resolver (validation & fallback)

`bb_theme_shell_resolve( part, requested )`:
1. `sanitize_key( $part )`, `sanitize_key( $requested )`.
2. If `$requested` is a **registered** slug for that part → return it.
3. Otherwise → `default`.

The requested value is **never** used as a filesystem path. `bb_theme_shell_template()` only ever
returns a registry path (`is_readable()`-checked inside the catalog builder). If a filter empties a
part's catalog, the renderer prints nothing rather than fatalling.

Verified fallbacks: unknown, empty, `null`, `../..`, `../../wp-config`, `<script>alert(1)</script>`,
unknown part — all resolve to `default` (or empty template for an unknown part). §15 of the test
suite.

## 8. Customizer

- One new section in the existing **"Business Builder Theme"** panel: **Shell Layout**
  (`bb_theme_shell_structure`), added by the existing `bb_theme_customize_register()`.
- Three `select` controls — Header, Navigation, Footer — whose choices are **derived from**
  `bb_theme_shell_options()` (the same catalog the resolver validates against). No duplicated list.
- The Phase-14 colour section was relabelled **"Header & Footer Colors"** to avoid confusion with
  the new structural section.
- **Storage:** theme mods `bb_theme_shell_header|navigation|footer` (site-specific).
- **Sanitize callback** per control → `bb_theme_shell_sanitize()` → whitelist resolver.
- **Reset:** `bb_theme_reset_shell_variants()` removes only the shell mods (design overrides and the
  preset are untouched).
- No second settings interface was created; the existing Phase-14 Customizer, storage and sanitizer
  patterns are reused.

## 9. Design System Integration

```
preset  →  user overrides  →  resolved --bb-* tokens  →  shell
```

Shell variants consume `--bb-*` tokens exclusively. Verified live: with the `luxury` preset active
and Header = `centered`, the header background resolves to the luxury token value while the
`centered` **structure** stays selected (`bb-header--centered`). Structural selection and visual
language are independent, exactly as required.

`assets/css/shell-variants.css` contains **zero** hardcoded colours (verified: 0 hex matches) and no
`!important`; it only changes layout/alignment/composition.

## 10. RTL / LTR

- `shell-variants.css`: **0** physical-direction properties (`margin-left`, `left:`, etc.);
  **12** logical/neutral ones (`inset-inline`, `margin-inline`, `border-block-end`,
  `text-align: center|start|end`, `justify-content`).
- Live `dir="rtl"` simulation with Header = `centered`, Footer = `columns`: **no horizontal
  overflow**, centered header remains centered, 0 console errors.
- LTR is the site's normal mode and was verified throughout.

## 11. Responsive

Measured live at **390 / 768 / 1024 / 1440 px** with Header = `split`, Navigation = `minimal`,
Footer = `columns`:

| width | scrollWidth | overflow |
| --- | --- | --- |
| 390 | 390 | no |
| 768 | 768 | no |
| 1024 | 1024 | no |
| 1440 | 1440 | no |

No horizontal overflow at any width; header and footer span the viewport correctly. Mobile strategy
is defined for every variant (header/nav → toggle; footer → stack).

## 12. Accessibility

Verified live on a non-default combination:

- Landmarks: exactly **1** `<header>`, **1** `<footer>`, **1** `<nav>`, **1** `<main>`.
- `#bb-primary-nav` keeps `aria-label="Primary navigation"`.
- The toggle button keeps `aria-controls="bb-primary-nav"` and `aria-expanded`.
- Skip link present.
- Mobile menu on the **minimal** header (narrow viewport): toggle opens
  (`aria-expanded="true"`, `.is-open` set, panel visible) and **Escape** closes it and resets
  `aria-expanded="false"`. The pre-existing keyboard contract is fully intact on the new variants.

## 13. Security

- Every selection goes through `bb_theme_shell_resolve()` (whitelist + `sanitize_key`).
- Templates are only ever registry paths; a requested value never reaches `include`/`require`.
- Verified rejections: `../../wp-config`, `../..`, `<script>…</script>`, unknown part → `default`;
  array input → `default`; a legacy filter returning junk → `default`.
- The template locator returns a path **inside the theme dir** for every variant (verified with
  `realpath`).

## 14. Multisite

- Storage = theme mods → per-site.
- Site 2 selection (`split`) does **not** appear on site 1 (verified).
- Site 1 = `datalynx` (Astra): live HTTP 200, **no** BB shell, **no** BB variant class, **no** fatal.
  A site on another theme is unaffected.

## 15. Backward Compatibility

| Concern | Result |
| --- | --- |
| Default shell output (header + footer) vs pre-Phase-15 | **Byte-identical** (live, whitespace-normalised compare) |
| Existing hooks (`bb_theme_provides_shell`, `body_class`, `bb_theme_header_variant`, `bb_theme_footer_variant`) | Preserved; the header/footer filters still win and their junk values still resolve safely |
| Classes / IDs / data attributes | Unchanged for the default variant |
| JS hooks (`.bb-menu-toggle`, `#bb-primary-nav`, `.is-open`, Escape) | Unchanged & verified on new variants |
| Section variants / component variants / builder data | Untouched, all suites green |
| `_bb_page_template` (per-page preset) | Independent, not touched |

## 16. Files Created

```
themes/business-builder/inc/shell-variants.php                 (registry + resolver + renderer)
themes/business-builder/inc/shell-data.php                     (prepared presentation data)
themes/business-builder/assets/css/shell-variants.css          (shared foundation + structural rules)
themes/business-builder/template-parts/header/header-centered.php
themes/business-builder/template-parts/header/header-split.php
themes/business-builder/template-parts/header/header-minimal.php
themes/business-builder/template-parts/navigation/nav-default.php
themes/business-builder/template-parts/navigation/nav-centered.php
themes/business-builder/template-parts/navigation/nav-minimal.php
themes/business-builder/template-parts/footer/footer-columns.php
themes/business-builder/template-parts/footer/footer-centered.php
themes/business-builder/template-parts/footer/footer-minimal.php
docs/phase-15-theme-shell-audit.md
docs/phase-15-theme-shell-final-report.md
tests/runtime-phase15-theme-shell.php
```

(Note: `header-default.php`, `footer-default.php`, `nav-default.php` are listed under *Modified*
below because the originals existed and were re-homed into the variant architecture.)

## 17. Files Modified

| File | What changed | Why |
| --- | --- | --- |
| `themes/business-builder/header.php` | `get_template_part(…, bb_theme_header_variant())` → `bb_theme_shell_render('header')` | Route through the shell resolver |
| `themes/business-builder/footer.php` | same for footer | Route through the shell resolver |
| `themes/business-builder/functions.php` | require `shell-variants` + `shell-data` | Load the new layer |
| `themes/business-builder/inc/enqueue.php` | added `assets/css/shell-variants.css` (after responsive, before theme) | Load the structural CSS |
| `themes/business-builder/inc/customization.php` | relabel the colour section; add the **Shell Layout** section + 3 select controls; add `bb_theme_reset_shell_variants()` | Customizer integration |
| `themes/business-builder/template-parts/header/header-default.php` | nav include → `bb_theme_shell_render('navigation')` (markup otherwise identical) | Consume the selected navigation |
| `themes/business-builder/template-parts/footer/footer-default.php` | menu/copyright routed through the shared helpers (markup otherwise identical) | Centralise menu + copyright |
| `themes/business-builder/template-parts/navigation/primary.php` | now delegates to `bb_theme_shell_render('navigation')` | Compatibility entry point |

**No** plugin file, no builder file (`SectionRegistry` / `SectionRenderer` / `PageBuilderAjax` /
`PageManager` / `page-admin.js`), no LawFirm section/component/variant, and no business system was
touched. `assets/js/theme.js` was **not** modified.

## 18. Files Deleted

None.

## 19. Business Logic

**Unchanged.** No payment, consultation, appointment, notification, activity, dashboard or receipt
file was modified. The shell is domain-agnostic: no shell file references `bb_lawyer`,
`bb_legal_service`, `LawFirmQueries`, `BusinessBuilderCore`, `SectionRegistry`, `WP_Query` or
`bb_component` (the only textual "WP_Query" is a docblock stating it must **not** be used).

## 20. Page Builder

**Unchanged.** `SectionRegistry`, `SectionRenderer`, `PageBuilderAjax`, `PageManager` and
`page-admin.js` were not touched. Shell selection is Theme configuration, not builder section
configuration.

## 21. Tests (exact results)

```
PHP lint (all theme PHP incl. 19 new/changed files):        PASS (0 errors)
Phase 9  (theme):                                           24/24 PASS
Phase 10 (components):                                      31/31 PASS
Phase 11 (variants):                                        39/39 PASS
Phase 11 (schema):                                          12/12 PASS
Phase 12 (card variants):                                   39/39 PASS
Phase 13 (catalog):                                        127/127 PASS
Phase 14 (design system):                                   42/42 PASS
Phase 14 (admin/Customizer):                                14/14 PASS
Phase 15 (theme shell):                                     65/65 PASS
payment-completion:                                         32/0 PASS
manual-review:                                              29/0 PASS
markpaid-sync:                                               6/0 PASS
activity & isolation:                                      11/0 PASS
free-invoice:                                              13/0 PASS
receipt-page-free:                                          5/0 PASS
CSS sanity (shell-variants.css braces):                     42/42 balanced PASS
CSS hardcode scan (shell-variants.css):                     0 hex PASS
JS validation:                                              N/A (theme.js unchanged)
Hardcode scan (new shell PHP):                              0 hex PASS
Mojibake scan (new/changed theme files):                    none PASS
Default shell parity (pre vs post, live):                   IDENTICAL (header+footer) PASS
Live variant matrix (4 combos, HTTP):                       all PASS
Live preset inheritance (luxury + centered header):         PASS
Responsive 390/768/1024/1440:                               PASS (no overflow)
RTL (dir=rtl, centered/columns):                            PASS (no overflow)
Accessibility (landmarks/aria/skip/Escape):                 PASS
Mobile menu JS on minimal variant:                          PASS (open + Escape close)
Multisite isolation:                                        PASS
Customizer flow (save → reload → preset switch → reset):    PASS
Live gateway / live money:                                  NOT TESTED (no credentials; out of scope)
```

**Note on Phase 9 `render` / `rtl-preset` suites:** these two legacy suites report failures
(`home has theme header`, `RTL locale yields bb-rtl`). They were run against the **original**
theme (backup restored) and fail **identically** — they are a pre-existing environment/harness
artifact (the suite calls `switch_theme()` inside a CLI boot where Astra is loaded on blog 1, and
the `ar` locale does not flip `is_rtl()` in this environment), **not** a Phase-15 regression. The
canonical Phase-9 theme suite (`runtime-phase9-theme.php`) is 24/24 green.

## 22. Live Verification

Actual scenarios executed against `http://lawfirm.builder.test/` (site 2) and `http://builder.test/`
(site 1):

| Scenario | Result |
| --- | --- |
| Default config homepage (`/`) | 200, shell == pre-Phase-15, 0 console errors, 0 failed requests |
| Header=centered / Nav=centered / Footer=columns | 200, all three structural classes present, no fatal |
| Header=split / Nav=minimal / Footer=centered | 200, classes present |
| Header=minimal / Nav=default / Footer=minimal | 200, classes present |
| Header=centered / Nav=minimal / Footer=minimal | 200, classes present |
| Routes `/ /home/ /services/ /lawyers/ /about/` with non-default variants | all 200, shell consistent, no fatal |
| Luxury preset + centered header | luxury tokens applied **and** `bb-header--centered` retained |
| Mobile menu (minimal header, 390px) | toggle opens (aria-expanded true, panel visible), Escape closes |
| Responsive 390/768/1024/1440 | no horizontal overflow |
| `dir="rtl"` (centered/columns) | no overflow, header centered |
| Site 1 (Astra) | 200, no BB shell, no BB variant class, no fatal |
| Customizer section + 3 settings + choices == catalog | PASS (WP_Customize_Manager API) |
| Customizer save → reload → preset switch → reset | PASS |

**Known limitation:** the *interactive* Customizer click-through was not automated in a real browser
session (no admin-session harness); it was verified via the `WP_Customize_Manager` API, theme-mod
persistence, live output, and reset — the same approach Phase 14 used.

## 23. Known Limitations

- The interactive admin Customizer was verified by API + live output, not by driving browser clicks.
- The `minimal` header hides the inline desktop navigation by design (a structural choice); the menu
  is reachable via the toggle on all widths — this is intentional, not a defect.
- The site has no `footer` menu assigned, so the footer menu column is absent in every footer variant
  on this site (correct behaviour — no fake content was invented).
- Two legacy Phase-9 suites (`render`, `rtl-preset`) fail in this environment for pre-existing
  reasons (documented in §21).
- No per-page or per-device shell variants (out of scope by design).

## 24. Deferred Work

- Child-theme / plugin **shell variant registration** is already supported
  (`bb_theme_shell_variants` filter + `bb_theme_shell_register()`), but no third-party variant ships.
- Header CTA slot, utility links, social links: not implemented (the current Header/Footer have no
  such data — inventing it would be fake business data).
- Mega menu, header/footer builder, drag-and-drop shell builder, per-page/per-device shell variants,
  theme export/import — explicitly out of scope.

## 25. Recommended Next Phase

**PHASE 16 — SHELL DATA EXTENSION / NAVIGATION PRESENTATION**, or a **Header CTA & Footer utility
slots** phase. The shell now has a clean, whitelist-validated, independently-composable variant
architecture with three parts and multiple variants each; the natural next step is to enrich the
*prepared data* the variants consume (an optional header CTA, footer contact/social slots sourced
from real Customizer settings rather than invented content), reusing the exact same
prepare → resolve → render contract established here. This keeps business logic in packs and
presentation in the shell, and requires no change to the resolver.

---

### Defects / notes this phase
1. **No product defect required a code fix.** The shell architecture was a clean extension of the
   existing (empty) extension points plus a new theme-side registry that mirrors the plugin's
   proven `SectionVariants` pattern.
2. **Scope held:** only **8 theme files** modified (4 of them minimal re-homing of the default
   parts) plus **12 created** (2 inc + 1 CSS + 9 variant templates); **no** plugin/builder/business
   file touched; `theme.js` untouched.
3. **Environment hygiene:** shell mods reset to `default`, design overrides cleared, preset returned
   to `default`; site 1 re-verified on Astra; all temporary build/probe files removed.