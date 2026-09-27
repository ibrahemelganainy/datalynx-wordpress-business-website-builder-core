# PHASE 15 — THEME SHELL VARIANTS — AUDIT

Read-only audit performed **before** any change. All evidence is quoted from the
actual source found on disk (`wp-content/themes/business-builder`). Nothing here
is carried over from the Phase-14 report without re-verification.

Environment: WordPress Multisite. **Site 1 = `datalynx` (theme `astra`)**,
**Site 2 = `Law Firm Demo` (theme `business-builder`, home `http://lawfirm.builder.test/`)**.

---

## 1. Current Header architecture

- `header.php` opens the document, prints `wp_head()`, the skip link and
  `get_template_part( 'template-parts/header/header', bb_theme_header_variant() )`.
- The markup lives in `template-parts/header/header-default.php`:

```php
<header id="bb-site-header" class="bb-site-header">
  <div class="bb-site-header-inner">
    <?php bb_theme_site_identity(); ?>
    <?php get_template_part( 'template-parts/navigation/primary' ); ?>
    <div class="bb-header-actions"><?php bb_theme_menu_toggle(); ?></div>
  </div>
</header>
```

- `bb_theme_site_identity()` (`inc/template-functions.php`) prints `.bb-brand`
  containing either `the_custom_logo()` or a `.bb-brand-link` to `home_url('/')`.
- **No CTA slot exists.** There is no prepared data object — the template calls
  the helper functions directly.

## 2. Current Navigation architecture

- `template-parts/navigation/primary.php` is a **one-line wrapper**:
  `bb_theme_navigation( 'primary', 'bb-nav' );`
- `bb_theme_navigation()` (`inc/template-functions.php`) renders `wp_nav_menu()`
  with `container=nav`, `container_id=bb-primary-nav`, `menu_class=bb-nav-menu`,
  `depth=2`, `fallback_cb=false`; if no menu is assigned it builds a
  `get_pages()` fallback list using the **same** `.bb-nav-menu` class.
- The mobile toggle is a **separate** helper `bb_theme_menu_toggle()`
  (`inc/navigation.php`) rendered by the header, not by the nav template.

## 3. Current Footer architecture

- `footer.php` prints `get_template_part( 'template-parts/footer/footer', bb_theme_footer_variant() )`.
- `template-parts/footer/footer-default.php`:

```php
<footer id="bb-site-footer" class="bb-site-footer">
  <div class="bb-site-footer-inner">
    <div class="bb-footer-col"><h2 class="bb-footer-heading">{site name}</h2>{tagline?}</div>
    <?php if ( has_nav_menu('footer') ) : ?>
      <div class="bb-footer-col"><h2 class="bb-footer-heading">Quick Links</h2>{wp_nav_menu footer depth=1}</div>
    <?php endif; ?>
  </div>
  <div class="bb-site-footer-bottom">Copyright {Y} {site}. All rights reserved.</div>
</footer>
```

- Footer content is site name + tagline + optional `footer` menu + copyright.
  **No social links, no contact block** exist today.

## 4. Template hierarchy

```
header.php (opens doc, get_template_part header/<variant>)
  └─ index.php | page.php | front-page.php | single.php | archive.php | 404.php
       (each: get_header() → <main id="bb-main" class="bb-main bb-container"> → get_footer())
footer.php (get_template_part footer/<variant>, closes doc)
```

`style.css` is a **declaration-only** theme header (no rules).

## 5. Template-parts hierarchy

```
template-parts/
  header/header-default.php
  footer/footer-default.php
  navigation/primary.php
templates/
  components/empty-state.php
  components/section-heading.php
```

## 6. Existing shell hooks

| Hook | Type | Location | Notes |
| --- | --- | --- | --- |
| `bb_theme_provides_shell` | filter | `inc/template-hooks.php` | returns `true` (theme owns shell) |
| `body_class` | filter | `inc/template-hooks.php` | adds `bb-has-builder` |
| `bb_theme_is_builder_page` | filter | `inc/template-functions.php` | builder-page detection |
| `bb_theme_header_variant` | **filter** | `inc/preset-resolver.php` | `apply_filters(..., 'default')` then `sanitize_file_name()` |
| `bb_theme_footer_variant` | **filter** | `inc/preset-resolver.php` | same pattern |
| `bb_theme_body_classes` | function | `inc/theme-support.php` | `bb-theme`, `bb-theme-preset-<slug>`, `bb-rtl`/`bb-ltr`, `bb-no-sidebar` |

**Decision:** the header/footer variant values come from a **theme mod**, not from
a filter only. To preserve the existing public filter as an override extension
point, the resolver keeps firing `bb_theme_header_variant` / `bb_theme_footer_variant`
**after** reading the theme mod, so an existing filter still wins.

## 7. Existing shell classes

`bb-site`, `bb-site-header`, `bb-site-header-inner`, `bb-brand`, `bb-brand-link`,
`bb-header-actions`, `bb-menu-toggle`, `bb-menu-toggle-bar`, `bb-nav`,
`bb-nav-menu`, `bb-site-footer`, `bb-site-footer-inner`, `bb-footer-col`,
`bb-footer-heading`, `bb-site-footer-bottom`, `bb-skip-link`, `bb-main`,
`bb-theme`, `bb-rtl`/`bb-ltr`.

## 8. Existing shell data attributes

The shell markup has **no `data-bb-*` attributes**. JS targets `.bb-menu-toggle`
(class) + `#bb-primary-nav` (id). The plugin's builder JS uses
`data-section-id` / `data-section-type`, which are **not** part of the shell.

## 9. Existing responsive behavior

`assets/css/responsive.css`:
- `<= 1023px`: shows `.bb-menu-toggle`, collapses `.bb-nav` (hidden until
  `.is-open`), stacks `.bb-nav-menu` vertically, wraps `.bb-site-header-inner`,
  gives `.bb-nav-menu a` a full-width block hit area.
- `.bb-no-js .bb-nav { display:block }` — no-JS menu fallback.
- `<= 767px`: shrinks heading sizes and container/section spacing via token overrides.
- `prefers-reduced-motion`: disables animation.

## 10. Existing mobile navigation behavior

- A real `<button class="bb-menu-toggle" aria-controls="bb-primary-nav" aria-expanded="false">`.
- `assets/js/theme.js` toggles `.is-open` on `#bb-primary-nav` and updates
  `aria-expanded`; **Escape** closes and returns focus to the toggle.
- **No overlay/drawer** — the panel simply expands inside the wrapped header.
- `document.documentElement.classList.remove('bb-no-js')` on load.

## 11. Existing RTL behavior

- Logical properties throughout (`inset-inline-start`, `margin-inline`,
  `padding-inline`, `border-inline-start`).
- Body class `bb-rtl` / `bb-ltr` from `bb_theme_body_classes()`.
- Submenu uses `inset-inline-start: 0`.

## 12. Existing accessibility behavior

- `<header>`/`<footer>` landmarks; skip link `.bb-skip-link` → `#bb-main`.
- `wp_nav_menu()` prints `aria-label="Primary navigation"` (or the manual fallback does).
- `.screen-reader-text` utility; `:focus-visible` outline in `typography.css`.
- Toggle is a button with `aria-controls`/`aria-expanded` and Escape handling.
- Custom logo path prints a screen-reader site name on the front page.

## 13. Existing JS dependencies

Exactly **one** file, `assets/js/theme.js`, **dependency-free, no jQuery**, loaded
in the footer (`true`) as handle `bb-theme`. It binds only `.bb-menu-toggle` and
`#bb-primary-nav`.

## 14. Existing CSS dependencies

`inc/enqueue.php` loads, **in order, front end only** (admin returns early):
`tokens → typography → layout → components → header → footer → responsive → theme`.
Each depends on the previous handle. `bb-theme-preset-css()` is added as an
**inline style after `bb-theme-tokens`** (priority 20). All rules are scoped to
`.bb-theme`, so wp-admin is never restyled.

## 15. Existing Theme variant hooks

Only the two slug filters (`bb_theme_header_variant`, `bb_theme_footer_variant`).
**Navigation has no equivalent** — there is no `bb_theme_navigation_variant`.

## 16. Existing variant resolver infrastructure

- **Plugin side (Phase 11/12):** `includes/Builder/SectionVariants.php`
  (class: `register`, `register_many`, `available`, `has_variants`, `resolve`,
  `template`, `all`) + `includes/Builder/section-variants.php` (API:
  `bb_section_variants()`, `bb_resolve_section_variant()`,
  `bb_section_variant_options()`, `bb_render_section_variant()`), fired via
  `do_action('bb_register_section_variants')`.
- **Theme side:** **none.** The theme currently has **no** resolver — it hands the
  sanitized slug straight to `get_template_part()`, which silently falls back to
  nothing if the file does not exist (and prints a PHP notice in debug).

This is the key gap: the theme needs a **whitelist registry + safe resolver**
mirroring the plugin's well-proven `SectionVariants` pattern, plus a
`bb_theme_navigation_variant` equivalent.

## 17. Existing Design System token usage

`header.css`/`footer.css` already consume `--bb-header-bg/-color`, `--bb-footer-bg/-color`,
`--bb-color-*`, `--bb-space-*`, `--bb-radius-*`, `--bb-shadow-md`,
`--bb-container-width`, `--bb-container-padding`, `--bb-border-width`. **Zero bare
hex values.** The header/footer tokens are already overridable (Phase 14 §shell).
**Shell variants must reuse these same tokens** — no new token namespace.

## 18. Existing Header/Footer configuration

Theme mods only: `bb_theme_preset` + `bb_design_*` (28 controls incl.
`bb_design_header_bg`, `bb_design_header_color`, `bb_design_footer_bg`,
`bb_design_footer_color`). Registered in `inc/customization.php` under the
**"Business Builder Theme"** panel with sections `Theme Preset`, `Colors`,
`Typography`, `Layout & Spacing`, `Corners`, `Header & Footer`.

## 19. Current preset interaction

`bb_theme_presets()` → `default` / `modern` / `luxury`; each sets header/footer
bg+color among other tokens. `bb_theme_preset_config()` merges
`bb_theme_customization_overrides()` over the preset. Emitted as one inline
`:root{}` block. **Shell variants must not introduce a second color layer** — they
consume the resolved `--bb-*` values untouched.

## 20. Multisite implications

Everything is a **theme mod** (`get_theme_mod`) → per-site by construction.
`bb_theme_get_preset()` also fires `bb_theme_active_preset`. Adding shell
selection as theme mods keeps isolation identical to Phase 14. Site 1 runs Astra:
the theme code never loads there (theme files only execute when the theme is active).

## 21. Potential backward-compatibility risks

| Risk | Mitigation |
| --- | --- |
| `header.php`/`footer.php` call `get_template_part(..., variant)`; an unregistered slug currently prints nothing | Resolver falls back to `default` **before** the slug reaches `get_template_part` |
| Existing `bb_theme_header_variant`/`footer_variant` filters | Keep firing them; theme mod is the base, filter still wins |
| Default output must stay byte-identical | Keep the exact existing markup for the `default` variants (extract, do not rewrite) |
| JS binds `.bb-menu-toggle` / `#bb-primary-nav` | Every nav variant must render those exact hooks |
| `.bb-nav` collapse is keyed on `.bb-nav` class in `responsive.css` | Every nav variant keeps `.bb-nav` |
| Header inner is `justify-content: space-between` flex | Variants must not break the default flex contract |

## 22. Recommended shell variant architecture

Mirror the proven `SectionVariants` pattern inside the theme (domain-agnostic,
whitelist-based):

```
inc/shell-variants.php
   bb_theme_shell_registry()            ← one registry, three parts: header|navigation|footer
   register( part, slug, template, meta )
   resolve( part, requested )           ← whitelist → 'default' fallback (sanitize_key)
   template( part, requested )          ← returns a REGISTERED absolute path only
   choices( part )                      ← slug => translated label (drives the Customizer)

inc/shell-data.php
   bb_theme_shell_header_data()         ← prepared presentation data (logo, name, tagline, nav, cta)
   bb_theme_shell_nav_data()
   bb_theme_shell_footer_data()

template-parts/header/header-{variant}.php
template-parts/navigation/nav-{variant}.php
template-parts/footer/footer-{variant}.php
assets/css/shell-variants.css           ← shared foundation + structural rules (tokens only)
```

- **One source of truth:** the registry's catalog drives both the resolver and the
  Customizer choices (`choices()`), exactly like `bb_section_variant_options()`.
- **Selection storage:** theme mods `bb_theme_shell_header`, `bb_theme_shell_navigation`,
  `bb_theme_shell_footer` (site-specific).
- **Independent composition:** header, navigation and footer are three separate
  parts → any combination is valid with no combined slug.
- **Default = reference implementation:** the `default` templates reuse the exact
  current helper calls so output parity is preserved.
- **CSS:** one shared foundation; per-variant rules are structural only and use
  the existing `--bb-*` tokens (including the Phase-14 shell colour controls).

**Deferred (not built):** mega menu, header/footer builder, per-page/per-device
shell variants, drag-and-drop, custom shell HTML.