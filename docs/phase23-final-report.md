# Phase 23 — Enterprise Visual Control System: FINAL REPORT

Phase 23 turned the Design Studio from "a palette picker with some extras" into a **complete,
audited visual control system** — Global Layout, Typography, Motion, Navbar, Scrollbar, a
capability contract, a Font Awesome icon vocabulary, a Global Services section and a Hero
upgrade — **without rebuilding anything**. Every existing system was extended through its own
documented extension point, and every claim below is backed by a command that was actually run.

Companion documents:

* `docs/phase23-audit.md` — the read-only audit produced **before** any edit, plus its
  post-implementation verification.
* `tests/_phase23-audit.php` — the committed measurement tool (control inventory, consumer map,
  indirect-consumer map, collision detector).
* `tests/runtime-phase23-design-system.php` — the Phase 23 suite (108 assertions).

---

## 1. Method: audit first, measured throughout

The specification requires an audit before any change, and forbids guessing. The audit is
therefore a **program**, not a document: `tests/_phase23-audit.php` walks 286 source files and,
for every control, reports its token, type, group, owner and **who consumes it** — separating
real frontend consumption (`var(--token)` in CSS, a token named by frontend JS) from author-side
references (schema, presets, resolvers).

That is how the defects were found rather than suspected:

| Category | Found | Evidence |
|---|---|---|
| Section controls that changed NOTHING | 5 | `--bb-section-glass-blur/-opacity/-border-opacity`, `--bb-section-reveal-duration/-delay`: FE = 0 |
| A global control with no consumer | 1 | `--bb-color-danger`: FE = 0 |
| Token names owned by more than one control | 2 | `--bb-section-align`, `--bb-section-padding-block` — established as intentional cascade pairs |

## 2. What was deliberately NOT done (the anti-duplication discipline)

The specification's hardest requirement is *"no two controls modify the same property"*. Three
cases deserved an explicit decision rather than a new control:

### 2.1 No second background system for the Hero

The Hero's background (colour / gradient / overlay / image) is **already** controlled once, by
the Studio's per-section **Background** group, which applies to every section including the
Hero. Adding `hero_background_*` controls would have created two sources of truth for one
property, so they were **not** added — and the suite asserts their absence.

### 2.2 No second layout control for Services

Layout is chosen through the **existing Phase 11 variant registry**, which is also what the
Studio's layout picker reads. A parallel `presentation` setting was considered and rejected: it
would have let the builder show one layout list while the Studio showed another. The section
declares a `variant` setting whose options come from the registry, and a test asserts that
`presentation` does **not** exist.

### 2.3 Body line-height was reused, not duplicated

Global Typography adds body **weight** and **letter-spacing**, but not body **line-height**: the
Theme's existing `line_height_normal` control *is* the body line-height and is consumed by
`.bb-template`. A second control would have been a duplicate.

### 2.4 Alias bridges instead of a rename (D4)

Three new dedicated controls take precedence over the design system's SCALE tokens, with the
scale token as the fallback — the same pattern Phase 22 used for border width and container
padding, so there is still exactly ONE effective value:

```
--bb-font-weight-heading  ->  --bb-font-weight-bold    ->  700
--bb-font-weight-body     ->  --bb-font-weight-normal  ->  400
--bb-heading-line-height   ->  --bb-line-height-tight  ->  1.2
```

Nothing was renamed, nothing was removed, and the Theme still works standalone.

## 3. The 23 new global controls

All 23 are added through the **existing** `bb_theme_design_schema` filter, stored as the
**existing** `bb_design_<key>` theme mods, and validated by the **existing** sanitizer. No new
schema, no new storage, no new namespace.

| Group | Control | Token | Type | Consumed by |
|---|---|---|---|---|
| Layout | `layout_mode` | `--bb-layout-mode` | select | body class → `design-sections.css` (boxed layout) |
| Layout | `site_margin` | `--bb-site-margin` | length | `design-sections.css` |
| Layout | `section_gap` | `--bb-section-gap` | length | `design-sections.css` |
| Typography | `body_weight` | `--bb-font-weight-body` | select | `design-sections.css` |
| Typography | `heading_weight` | `--bb-font-weight-heading` | select | `design-sections.css` |
| Typography | `body_letter_spacing` | `--bb-body-letter-spacing` | number | `design-sections.css` |
| Typography | `heading_line_height` | `--bb-heading-line-height` | number | `design-sections.css` |
| Typography | `heading_transform` | `--bb-heading-transform` | select | `design-sections.css` |
| Navbar | `nav_position` | `--bb-nav-position` | select | body class → `design-sections.css` |
| Navbar | `nav_link_size` | `--bb-nav-link-size` | length | `design-sections.css` |
| Navbar | `nav_link_weight` | `--bb-nav-link-weight` | select | `design-sections.css` |
| Navbar | `nav_link_tracking` | `--bb-nav-link-tracking` | number | `design-sections.css` |
| Navbar | `nav_link_hover` | `--bb-nav-link-hover` | color | `design-sections.css` |
| Navbar | `nav_link_active` | `--bb-nav-link-active` | color | `design-sections.css` |
| Navbar | `nav_indicator` | `--bb-nav-indicator` | select | body class → `design-sections.css` |
| Navbar | `nav_radius` | `--bb-nav-radius` | length | `design-sections.css` |
| Navbar | `logo_height` | `--bb-logo-height` | length | `design-sections.css` |
| Motion | `motion_mode` | `--bb-motion-mode` | select | master preset + body class |
| Motion | `motion_ease` | `--bb-ease-standard` | select | every transition in the layer |
| Scrollbar | `scrollbar_width` | `--bb-scrollbar-width` | length | inner scroll areas + conditional `html` |
| Scrollbar | `scrollbar_track` | `--bb-scrollbar-track` | color | same |
| Scrollbar | `scrollbar_thumb` | `--bb-scrollbar-thumb` | color | same |
| Scrollbar | `scrollbar_thumb_hover` | `--bb-scrollbar-thumb-hover` | color | same |

Each control also gained a **named, visual** picker where a numeric or keyword choice benefits
from one (`section_gap`, `site_margin`, `nav_link_size`, `nav_radius`, `logo_height`,
`scrollbar_width`), so the Studio shows meaning rather than raw values. All 23 were added to the
Studio's group map, and the old duplicate `header` group was folded into the new **Navbar**
group so no control can be rendered twice.

## 4. The five features in detail

### 4.1 Global Layout (§9)

`layout_mode` (full / boxed), `site_margin` (a gutter of background colour around the page) and
`section_gap` (space *between* sections, distinct from each section's own padding). Container
width, grid columns, text measure and section rhythm were already controllable and were left
alone.

Boxed mode is a genuine layout: the page becomes a centred card on the site background, capped
at the container width, using the design system's own radius and shadow tokens.

### 4.2 Global Typography (§10)

Body weight, heading weight, body letter-spacing, heading line-height and heading case — each
consumed on the body and headings of `.bb-template`. Letter-spacing is stored as a `number` in
`em` and unit-appended by the existing resolver, because the Theme's length validator accepts
only `px`/`rem`/`em`/`%` and the plugin must not loosen it.

### 4.3 Navbar (§12)

Logo height, menu position (beside the logo / centred / beside the buttons), link size, weight,
letter-spacing, hover and current-page colours, an active-link indicator (none / underline /
pill / dot) and link roundness — on top of the height, blur, border, gap and initial/scrolled
colours Phase 22 already delivered.

The navbar is **Theme markup**, so these rules target `.bb-theme .bb-site-header` — the same
scope Phase 22 uses. No Theme file was edited, and a site not running the canonical Theme never
matches the selector, so Astra is untouched by construction. Repositioning the menu changes only
which flex slot absorbs the free space, so the markup — and the accessibility tree — is never
reordered.

### 4.4 Global Motion (§14)

A single **intensity** decision (None / Subtle / Balanced / Expressive) plus a **curve**
(`motion_ease` → `--bb-ease-standard`, which every transition in the layer already reads).

`motion_mode` is a **master preset, not a competing control**: it writes values for tokens the
schema already declares, and it yields to any token the customer set explicitly. The precedence
rule is one-directional and documented:

```
design's authored value  ->  motion intensity  ->  explicit customer override
```

"None" is a real value (0 tempo, 0 duration, 0 distance, 0 lift, reveal kind `none`), never an
unset value — so a site that asks for no motion actually gets none. It is enforced twice: as
token values, and as a `has-bb-motion-off` body class plus a server-side `data-bb-reveal="none"`,
because a design-authored animation that no token governs must still be suppressed.

### 4.5 Scrollbar (§15)

Thickness, track, handle and handle-hover. This is the only Phase 23 feature whose rules cannot
live inside `.bb-template`: the page scrollbar belongs to `html`, which **wp-admin also has**.

The decision (measured, and asserted by the suite) is therefore:

* the `html` rules are emitted **only when the customer has deliberately stored a scrollbar
  value for THIS site** (`DesignShellState::has_custom_scrollbar()`), so no other site on the
  install and no admin screen is ever restyled;
* every value is re-validated through the Theme's own sanitizer before it reaches the CSS;
* the rules are attached to their own `bb-scrollbar` handle, so they are dequeueable;
* inner scrollable regions (`.bb-scroll-area`) are styled from `design-sections.css` inside the
  page wrapper, which is always safe.

## 5. Sections: capabilities, Services and the Hero

### 5.1 A capability contract that is actually READ (§7, §8)

`supports` was decorative: the audit measured that nothing read it, so the Studio decided which
controls to show from a heuristic and could offer a section controls it could not honour.

`SectionRegistry` now normalises a **closed** capability vocabulary (`cards`, `icons`, `slider`,
`media`, `background`, `presentation`, …). An unknown or misspelled capability is dropped at
registration, so a typo degrades to "not capable" rather than to a silently-enabled control, and
a pre-existing section inherits its own `supports` list as its capabilities — meaning every
existing pack section is described correctly with **no change at its registration site**.

`SectionStyleSchema::is_card_capable()` now consults the declaration first, so a section that
declares `cards` gets the Cards group and a section that does not cannot be offered card
controls.

### 5.2 Global Services (§11, §26) — a core section

A new `services` section, implemented **once** in the generic layer, so every pack — LawFirm,
Medical, Education, RealEstate and any future pack — gets it with no duplicated markup and no
business type in core.

* **One content model**: section title + description + a repeater of items, each with an
  **icon AND an image**, title, description, link and link text, plus an optional section button.
* **Five layouts** through the existing Phase 11 variant registry: `default` (cards in a grid),
  `list`, `featured` (the first item emphasised), `icon-text`, `image-text`.
* **Four card skins** (`elevated`, `bordered`, `flat`, `minimal`) plus column count and content
  alignment — all read from the shared card tokens, so the Studio's Cards / Shape / Glass /
  Colour controls apply on top.
* **One item renderer**: `bb_render_service_items()` is shared by the inline renderer and every
  variant template, so five layouts cannot drift apart.

### 5.3 Hero upgrade (§11)

* **Slider mode**: the Hero can now present as a slider. It does not grow a second slider
  implementation — it hands its settings and slides to the **existing** `render_slider_section()`,
  which owns layout, autoplay and overlay. With no slides yet, the standard hero renders so the
  section is never empty.
* **Content image placement**: beside the text (before / after), above, below, or hidden —
  expressed as classes and CSS `order`, so the markup is never duplicated or reordered.
* **Secondary call to action**: a quiet counterpart to the primary button.
* Background colour / gradient / overlay are deliberately **not** duplicated (see §2.1).

## 6. Icons: one vocabulary, conditional and de-duplicated Font Awesome (§18)

`Design\IconLibrary` owns the platform's single icon vocabulary: **137 icons across 14 semantic
categories** (general, business, finance, communication, people, health, justice, education,
home, technology, logistics, nature, time, ui) — semantic rather than business-typed, so the
generic layer stays generic, and extendable by a pack through `bb_icon_catalogue`.

* **Stored as a slug**, never as markup or a URL. `validate()` looks the slug up in a closed
  registry, so a forged value renders the empty string and can never become HTML. One validation
  rule serves the frontend, the admin and the tests.
* **Rendered from the registry's own data** as standard Font Awesome markup
  (`<i class="bb-icon bb-icon-gavel fa-solid fa-gavel" role="img" aria-label="…">`), with
  accessible naming.
* **Loaded only when needed**: the enqueue decision is made on `wp_enqueue_scripts` at priority
  30 — *after* the content exists — from a flag the renderer sets. A page with no icons makes no
  Font Awesome request at all.
* **Never duplicated**: if Font Awesome is already on the site (theme, Elementor, another plugin
  — a filterable handle list), that copy is reused and nothing is enqueued. Both properties are
  asserted by the suite: `queue unchanged` on a second pass, and `no second enqueue` when an
  existing Font Awesome is present.
* **Self-hostable**: the URL is filterable (`bb_icon_fontawesome_url`) and version-pinned, so a
  site can avoid any third-party request.
* **A visual picker without a new field type**: the icon control is the builder's existing
  `select`, so it works in the builder *and* inside repeaters with no change to `page-admin.js`.
  A dependency-free enhancement (`assets/js/admin/icon-picker.js`) upgrades it in place to a
  searchable, visual grid, keeping the select as the value holder and the no-JavaScript fallback,
  and re-scanning after DOM mutations so repeater rows added later are upgraded too. The
  slug → Font Awesome name translation comes from the server library, so the preview can never
  disagree with the frontend.

## 7. The fixes to measured defects (§2.3 of the audit)

| Section control | Before | After |
|---|---|---|
| `--bb-section-glass-blur` | Only read in PHP to decide `data-bb-glass=on/off`; the CSS used the GLOBAL blur | Section-scoped blur, with the global as fallback |
| `--bb-section-glass-opacity` | Nothing read it | Section-scoped glass tint |
| `--bb-section-glass-border-opacity` | Nothing read it | Section-scoped glass border |
| `--bb-section-reveal-duration` | Nothing read it | Per-section entrance speed |
| `--bb-section-reveal-delay` | Nothing read it | Per-section entrance delay |
| `--bb-color-danger` (global) | Nothing read it | Error / notice / field states |

### 7.1 Sections have no border by default

Sections are rhythm, not boxes. `design-sections.css` now sets
`border-width: var(--bb-section-border-width, 0)` on `.bb-section`, so the default page has no
boxed look — while a section that paints its own surface (detected from its own background
tokens and published as `data-bb-surface`) or carries glass still gets a delineating border.

## 8. The final inheritance cascade

```
1. Theme tokens.css :root defaults
2. design preset (packs) declared tokens
3. slug/unit resolution + motion master preset     (plugin filters, ordered 20 / 25 / 30)
4. global user overrides — theme mods bb_design_<key>
5. SECTION overrides — scoped .bb-section-<type> block (emitted after :root)
6. component layer (existing card_variant / component templates)
7. rendered markup reads RESOLVED tokens to emit data-bb-* attributes
8. keyword controls become body classes (DesignShellState) the stylesheet keys on
```

Steps 1–4 are one inline `:root` block on the `bb-theme-tokens` handle; step 5 is emitted after
it, so a section override wins by source order with no `!important`. Steps 7–8 exist because
glass, hover, reveal, motion, layout and the nav indicator are decisions a stylesheet cannot
derive from a value alone.

## 9. Multisite isolation — measured, with an honest constraint

Every Phase 23 control is a **theme mod**, so it is per-site by construction. The suite proves
it: it writes a Phase 23 control, confirms it is physically present in the owning site's options
table, and confirms it is absent from **every other** site's table.

**Measured environment constraint (carried over from Phase 22 §8.1):** `switch_to_blog()` does
not reliably switch the option tables on this install — a probe showed `$wpdb->options` and
`get_option('stylesheet')` unchanged for every blog id. An isolation test written naively would
have compared site 1 against site 1 and reported a false leak (it did, and that is how the
constraint was re-confirmed). The suite therefore uses the **direct, read-only per-blog option
reads** Phase 22 established, and additionally asserts that the other sites were genuinely
readable so that an absence is meaningful. The probe is committed as
`tests/_phase23-isolation-probe.php`.

## 10. Verification

| Suite | Result |
|---|---|
| `tests/runtime-phase23-design-system.php` | **108 / 108 PASS** |
| `tests/runtime-phase22-design-system.php` (regression) | **153 / 153 PASS** |
| `tests/runtime-phase22-live-render.php` (regression) | **25 / 25 PASS** |
| `tests/runtime-phase21-design-studio.php` (regression) | **720 / 720 PASS** across sites 2 and 3 |
| `php -l` on every edited PHP file | no syntax errors |
| `node --check` on every edited JS file | no syntax errors |

One expectation in the Phase 22 suite had to be **extended, not weakened**: its sweep asserted
that every control's token appears as a `var()` somewhere. Phase 23 introduced keyword controls
whose delivery mechanism is a body class, so the sweep now also recognises the **state
emitters** (`DesignShellState`, `section-presentation.php`) — a token with no consumer in *any*
of the four sources still fails.

## 11. Limitations and deferred items (stated plainly)

| Item | Status |
|---|---|
| **Browser verification** | Not performed. The pre-existing MAMP fault documented since Phase 22 (`C:\MAMP\bin\php\php8.3.1\ext` missing `php_mysqli.dll`, so Apache-served PHP cannot reach MySQL) blocks loading any page. Everything above is verified by the CLI suites, by static analysis of the emitted CSS/JS, and by rendering sections through the real renderer. |
| **Hero background controls** | Intentionally absent — the per-section Background group is the single source (§2.1). |
| **Variants for sections other than Services** | Only `services` registers variant templates. Hero slider mode delegates to the existing slider engine rather than registering hero variant templates; the other core sections keep their single layout until a layout is genuinely authored for them. Registering an empty variant would be worse than none. |
| **Scrollbar from a design preset** | A preset alone does not trigger the `html` rules, by design: browser chrome is a deliberate branding decision, and triggering it from a preset would restyle `html` on every site using that design. A customer choice always applies. |
| **Icon font source** | Defaults to the official Font Awesome CDN, loaded only when an icon renders. Self-hosting is one filter (`bb_icon_fontawesome_url`); no Font Awesome asset is vendored into the plugin. |
| **`tests/smoke-sections.php`** | Fails, **pre-existing and unrelated**: it is a standalone harness that runs without WordPress using hand-written shims whose list has no `add_filter()`, so the LawFirm pack's own registration call is undefined inside it (`Call to undefined function BusinessBuilderCore\Packs\LawFirm\Sections\add_filter()`). No Phase 23 file is involved; the real coverage lives in the runtime suites, which all pass. |

## 12. Files added and changed

### Added

| File | Purpose |
|---|---|
| `docs/phase23-audit.md` | the pre-change audit + post-implementation verification |
| `docs/phase23-final-report.md` | this report |
| `tests/_phase23-audit.php` | the committed control/consumer measurement tool |
| `tests/_phase23-audit.txt` | its raw output |
| `tests/_phase23-isolation-probe.php` | the multisite diagnostic that confirmed the `switch_to_blog` constraint |
| `tests/runtime-phase23-design-system.php` | the Phase 23 suite (108 assertions) |
| `includes/Design/IconLibrary.php` | the Font Awesome vocabulary, validator, renderer and conditional / de-duplicated enqueue |
| `includes/Design/icons.php` | the icon public API + a standard icon field definition |
| `includes/Builder/section-services.php` | the ONE service-item renderer |
| `includes/Builder/core-section-variants.php` | registers the core section layouts |
| `templates/sections/services/{default,list,featured,icon-text,image-text}.php` | the five Services layouts (thin wrappers over the shared item renderer) |
| `assets/css/frontend/section-services.css` | the Services presentation layer |
| `assets/css/page-admin/components/icon-picker.css` | the admin icon-picker styling |
| `assets/js/admin/icon-picker.js` | the searchable icon grid (progressive enhancement) |

### Changed

| File | Change |
|---|---|
| `includes/Design/DesignSchema.php` | +23 controls; `motion_mode_library()` and `resolve_motion_mode()` |
| `includes/Design/DesignScales.php` | +6 named visual scales for the new controls |
| `includes/Design/DesignStudioUI.php` | Layout / Typography / Navbar groups; Motion and Scrollbar keys; the duplicate `header` group folded into Navbar |
| `includes/Design/DesignShellState.php` | state classes for layout / nav position / nav indicator / motion, and the conditional, validated scrollbar emitter |
| `includes/Design/SectionStyleSchema.php` | `is_card_capable()` honours the declared capability |
| `includes/Builder/SectionRegistry.php` | the capability vocabulary, normalisation and query API |
| `includes/Builder/CoreSections.php` | the Services section, the Hero upgrade, hero capabilities |
| `includes/Builder/SectionRenderer.php` | the Services renderer; the Hero slider / secondary-CTA / media-position upgrade |
| `includes/Builder/section-presentation.php` | `data-bb-surface`, and the motion-master suppression of reveals |
| `includes/Builder/section-variants.php` | labels for the Services layouts |
| `includes/Core/Plugin.php` | registers the icon library |
| `includes/Admin/PageAdmin.php` | enqueues and localises the icon picker; requests the font through the library |
| `business-builder-core.php` | loads the new procedural files |
| `assets/css/frontend/design-sections.css` | the Phase 23 global layout / typography / navbar / motion / scrollbar / section-glass / border / hero layer |
| `assets/css/frontend.css` | imports the services stylesheet |
| `assets/css/page-admin.css` | imports the icon-picker stylesheet |
| `assets/js/page-admin.js` | emits the opt-in `data-bb-icon-picker` marker on top-level and repeater selects |
| `tests/runtime-phase22-design-system.php` | recognises the Phase 23 state emitters in its consumer sweep |

## 13. How to use the new controls

1. **WordPress admin → Business Builder → Design.** The panel now reads, top to bottom:
   Design identity, Background, Layout & grid, **Typography**, **Navbar**, Spacing & density,
   Shape, Glass effect, Footer, **Motion**, **Scrollbar**.
2. Choose a **motion intensity** first — it sets tempo, duration, distance, stagger, hover lift
   and reveal kind in one decision; every individual control then refines it.
3. Open **Sections** to give any section its own background, colours, spacing, cards, borders,
   glass or motion. **Services** additionally offers five layouts, four card skins and a column
   count.
4. Add a **Services** or **Hero** section in the page builder. For each service item choose an
   **icon** (a searchable, visual Font Awesome picker), an image, or both. The Hero can be
   switched to **Slider** mode and given slides.
5. **Reset** always means "delete the override and inherit from the design" — nothing is copied.






