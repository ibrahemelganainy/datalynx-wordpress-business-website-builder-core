# PHASE 8 — FRONTEND ARCHITECTURE AUDIT REPORT

**Read-only audit.** No files modified. Evidence is quoted from source.

---

## 1. Executive Summary

The public frontend of a Business Builder site is currently owned by **three layers**,
none of which is a Business Builder Theme:

1. **An arbitrary third-party WordPress theme** (Astra on site 1, Twenty Twenty-Five on
   site 2 — verified via `get_option('stylesheet')`).
2. **The plugin's own CSS bundle** `assets/css/frontend.css`, only enqueued on *builder
   pages* (`is_builder_page()`), scoped to a `.bb-template` wrapper the plugin injects.
3. **PHP markup hardcoded inside `SectionRenderer` + each section's render method**
   (no template files for cards; only a few form templates exist).

Key facts established by code:

- **A `business-builder` theme EXISTS as an empty stub** — every PHP file is **0 bytes**;
  `style.css` contains only a header. It is **not the active theme on any site**.
  → classification: **UNUSED / OBSOLETE**.
- **`Default` / `Modern` / `Luxury` are NOT themes and NOT page templates.** They are a
  **wrapper CSS class** (`bb-template-{default|modern|luxury}`) emitted by
  `SectionRenderer::render_page()` from page meta `_bb_page_template`. Classification:
  **Style Preset (partial)**.
- **That preset system is effectively broken**: the shell rules for `Modern`/`Luxury`
  live in `assets/css/page-admin/components/template-shell.css`, an **admin** file
  **never enqueued on the frontend**. Only per-card "Luxury tweaks" in
  `frontend/section-*.css` reach the public site. **`Modern` is a frontend no-op.**
- **Sections are PHP-rendered with hardcoded markup.** Cards are **embedded inside
  section render methods** (e.g. `bb-lawyer-card` built by string concatenation in
  `LawFirmSections::render_lawyers_section()`), not reusable components.
- **No Section Variant system, no Component/Card Variant system, no global Theme
  customization** exist. → **MISSING**.
- The plugin **does** have a design-token layer (`frontend/design-tokens.css`, scoped to
  `.bb-template`) — so tokenization is **PARTIALLY EXISTS**.

---

## 2. Current Frontend Architecture

```
Browser request for a builder page
   │
   ▼
Active WP theme (Astra / Twenty Twenty-Five)   ← template hierarchy, header, footer, page shell
   │  renders the_content
   ▼
Plugin::render_builder_content()  (the_content filter, is_builder_page())
   │
   ▼
SectionRenderer::render_page($page_id)
   │  reads _bb_page_template → wrapper class bb-template-{default|modern|luxury}
   │  reads _bb_page_sections  → array of {type,id,settings,content}
   ▼
per section: SectionRegistry::get($type)
   │  if $config['render'] callable  → section's own PHP method (string-built markup)
   │  else                           → SectionRenderer generic renderer (hardcoded cards)
   ▼
HTML printed inside <div class="bb-template bb-template-X"> (inside the theme's content area)
   │
   ▼
assets/css/frontend.css (only on builder pages) styles .bb-template .bb-*
```

Render pipeline as actually implemented (matches the prompt's hypothesis, with the
addition that the **host theme is still the outer shell**):

```
Page → PageManager(is_builder_page) → SectionRenderer → SectionRegistry → Section PHP method → HTML
                                                                        (NO template file for cards)
```

---

## 3. WordPress Theme Audit

| Item | Finding | Classification |
| --- | --- | --- |
| Active theme (site 1) | `astra` (third-party) | EXISTS AND WORKS (foreign) |
| Active theme (site 2) | `twentytwentyfive` (WP default) | EXISTS AND WORKS (foreign) |
| `business-builder` theme | Exists as a directory; **all PHP files 0 bytes**; `style.css` header-only; **not active anywhere** | **UNUSED / OBSOLETE** |
| `business-builder` functions.php / index.php / page.php / single.php / header.php / footer.php | All **0 bytes** | **MISSING (placeholder)** |

Conclusion:

> **Business Builder Theme: MISSING (empty stub only).**
> The public frontend runs on whatever foreign theme the site admin chose.

The only theme coupling is `templates/single-bb_lawyer.php`, which calls
`get_header()` / `get_footer()` → the **lawyer profile inherits the host theme's chrome**.

---

## 4. Plugin Frontend Ownership

| Frontend Responsibility | Current Owner | Evidence | Classification |
| --- | --- | --- | --- |
| Global CSS | Plugin (`frontend.css` bundle) | `Plugin::enqueue_builder_frontend_assets()` | PARTIALLY EXISTS (builder pages only) |
| Typography | Plugin tokens + host theme | `frontend/design-tokens.css` `.bb-template` | PARTIALLY EXISTS |
| Colors | Plugin tokens (`--bb-color-*`) | `design-tokens.css` | EXISTS AND WORKS |
| Container | Plugin (`layout.css`) + theme | `.bb-template` scoped | PARTIALLY EXISTS |
| Header | **Plugin section** (`header` section) — *and* host theme header | `SectionRenderer::render_header_section()` | DUPLICATED |
| Footer | **Plugin section** (`footer` section) — *and* host theme footer | `SectionRenderer::render_footer_section()` | DUPLICATED |
| Navigation | Plugin header section markup + theme menu | `bb-header-nav` | PARTIALLY EXISTS |
| Page Layout | Host theme (page.php / templates) | template hierarchy | EXISTS AND WORKS (foreign) |
| Section Rendering | Plugin `SectionRenderer` | `render_page()/render_section()` | EXISTS AND WORKS |
| Section Styling | Plugin CSS (`section-*.css`) | `.bb-section-*` rules | EXISTS AND WORKS |
| Cards | Plugin — **embedded in section methods** | see §9 | EXISTS BUT NEEDS EXTENSION |
| Buttons | Plugin CSS (`.bb-primary-button`) | `components.css` | EXISTS AND WORKS |
| Forms | Plugin templates + `esc_*` | `templates/*.php` | EXISTS AND WORKS |
| Responsive | Plugin CSS media queries | `layout.css`, `section-*.css` | EXISTS AND WORKS |
| RTL/LTR | Plugin (`is_rtl()` class + logical props) | `Plugin` + CSS | EXISTS AND WORKS |

---

## 5. Default / Modern / Luxury Audit

| Name | Actual Role | Storage | Used By | Rendering Effect | Classification |
| --- | --- | --- | --- | --- | --- |
| Default (`Classic`) | Wrapper **CSS class** `bb-template-default` | page meta `_bb_page_template='default'` | `SectionRenderer::render_page()` | Base look (the only fully-loaded preset) | **Style Preset (partial)** |
| Modern | Wrapper class `bb-template-modern` | `_bb_page_template='modern'` | same | Shell rules live in **admin** CSS → **no frontend effect** | **EXISTS BUT BUGGY / effectively UNUSED** |
| Luxury | Wrapper class `bb-template-luxury` | `_bb_page_template='luxury'` | same | Per-card tweaks in `frontend/section-*.css` (loaded) + shell rules (NOT loaded) | **PARTIALLY EXISTS** |

Evidence:
- Descriptor classes (`packs/LawFirm/Templates/Classic.php`) hold **only** `SLUG` + `WRAPPER_CLASS` — explicit comment: *"This class therefore holds no content logic."*
- `SectionRenderer::render_page()` lines 26-46 map the meta to one of three wrapper classes.
- `template-shell.css` (which defines `.bb-template-modern`/`.bb-template-luxury` shell styling) lives under `assets/css/page-admin/` and is **not in the `frontend.css` bundle** nor referenced by any enqueue → **never reaches the frontend**.

---

## 6. Page Builder Rendering Pipeline

- **Storage**: page meta `_bb_page_sections` (array) + `_bb_page_template` (slug) + `_bb_builder_enabled` (flag). (`PageManager`.)
- **Retrieval**: `SectionRenderer::render_page()` reads the meta directly.
- **Schema**: defined per section in `SectionRegistry::register($id, $schema)`; `settings` + `content` field maps.
- **Settings storage**: inside each section array entry.
- **Frontend selection**: `$config['render']` callable if present, else the generic renderer.
- **Template resolution**: **none for section bodies** — markup is built in PHP methods; a handful of *forms* use `include templates/*.php`.
- **CSS/JS**: a single `frontend.css` bundle (enqueued on builder pages) + `assets/js/frontend/*.js`.
- **Theme awareness**: `SectionRenderer` knows **only** the template *class*; it has **no knowledge of a Theme object, tokens, or variants**.

The Page Builder does **not** know about a Theme. There is no Theme abstraction.

---

## 7. Section Architecture

| Section | Source | Renderer | Template | CSS | JS | Variant Support | Classification |
| --- | --- | --- | --- | --- |
| Header | CoreSections | `SectionRenderer::render_header_section` | inline PHP | section-base? | — | layout field only | EXISTS AND WORKS |
| Hero | CoreSections | `render_hero_section` | inline PHP | section-base | — | alignment/min-height | EXISTS AND WORKS |
| About | CoreSections | `render_about_section` | inline PHP | section-base | — | image position | EXISTS AND WORKS |
| Features | CoreSections | `render_features_section` | inline PHP | section-base | — | columns | EXISTS AND WORKS |
| CTA | CoreSections | `render_cta_section` | inline PHP | section-base | — | — | EXISTS AND WORKS |
| Contact | CoreSections | `render_contact_section` | inline PHP | section-base | — | show_map | EXISTS AND WORKS |
| Slider | CoreSections | `render_slider_section` | inline PHP | section-base | slider JS | layout/height/overlay | EXISTS AND WORKS |
| Footer | CoreSections | `render_footer_section` | inline PHP | section-base | — | — | EXISTS AND WORKS |
| Lawyers | LawFirmSections | `render_lawyers_section` | inline PHP | section-lawyers.css | — | columns/photo | EXISTS BUT NEEDS EXTENSION |
| Legal Services | LawFirmSections | `render_legal_services_section` | inline PHP | section-legal-services.css | — | columns | EXISTS BUT NEEDS EXTENSION |
| Practice Areas | LawFirmSections | `render_practice_areas_section` | inline PHP | section-practice-areas.css | — | columns | EXISTS BUT NEEDS EXTENSION |
| Testimonials | LawFirmSections | `render_testimonials_section` | inline PHP | section-testimonials.css | — | columns | EXISTS BUT NEEDS EXTENSION |
| FAQ | LawFirmSections | `render_faq_section` | inline PHP | section-faq.css | — | — | EXISTS BUT NEEDS EXTENSION |
| Consultation | LawFirmSections | `render_consultation_section` | **include template** | section-consultation-form.css | frontend JS | payment | EXISTS AND WORKS |
| Booking | LawFirmSections | `render_booking_section` | **include template** | section-booking-form.css | frontend JS | availability | EXISTS AND WORKS |
| Status Lookup | LawFirmSections | `render_lookup_section` | **include template** | status-lookup.css | lookup JS | toggles | EXISTS AND WORKS |
| Lawyer Profile (single) | LawyerProfile | template | **`single-bb_lawyer.php`** | section-lawyer-profile.css | — | — | EXISTS AND WORKS (theme-coupled) |

Markup is **embedded in PHP**; only *form* sections are template-based. Section bodies
are **not** variant-aware.

---

## 8. Section Variant Capability

Searched the registry, schemas, `PageManager`, `SectionRenderer`, and page meta for any
of: `section_variant`, `layout_variant`, `design_variant`, `style_variant`,
`template_variant`.

Finding: **none exist.** The only per-section presentation knobs are a few ad-hoc
settings (`layout`, `columns`, `alignment`, `min_height`, `overlay`) — they are **section
content settings**, not a variant registry.

> **Section Variant Architecture: MISSING.**

---

## 9. Component/Card Architecture

Cards are built by **string concatenation inside each section's render method**. Example
(`LawFirmSections::render_lawyers_section`, lines ~772-824):

```php
echo '<article class="bb-lawyer-card">';
if ( $show_photo && $photo_id ) { echo '<div class="bb-lawyer-photo">…'; }
echo '<div class="bb-lawyer-body">';
echo '<h3>…</h3>'; echo '<p class="bb-lawyer-role">…</p>'; …
echo '</div></article>';
```

The **generic** renderer in `SectionRenderer::render_generic()` builds
`bb-section-item-card` with a fixed key→element mapping. Card class names:

`bb-lawyer-card`, `bb-service-card`, `bb-practice-area-card`, `bb-testimonial-card`,
`bb-faq-item`, `bb-feature-card`, `bb-section-item-card`, `bb-contact-card`.

None of these is a **reusable component class** — each is emitted inline by one section.

> **Component/Card Variant Architecture: MISSING.**

---

## 10. Component Variant Capability

No component abstraction exists. There is no `Component` class, no component registry, no
component template, no `component_variant` field. What exists is CSS class naming that
*happens* to be reusable (`bb-primary-button`), but no PHP-level component or variant.

> **Component Variant Architecture: MISSING.**

---

## 11. Global Design System Audit

- **Tokens: PARTIALLY EXISTS.** `assets/css/frontend/design-tokens.css` defines a full
  palette, type scale, spacing, radii, shadows, breakpoints — but **scoped to `.bb-template`**
  so they apply only to builder output, not the surrounding theme.
- **Distribution: MIXED.** Tokens are used in `frontend/section-*.css` (var(--bb-color-…)),
  **but** hardcoded values also exist: e.g. Luxury card tweaks hardcode `#b8843c`,
  `Georgia, "Times New Roman", serif`, `rgba(255,255,255,0.9)`; the admin template-shell
  hardcodes `#0f172a`, `#475569`.
- **Buttons/forms/cards/alerts/modals:** defined in `frontend/components.css` and
  `frontend/receipt-modal.css`, scoped to `.bb-template`.

Classification: **PARTIALLY EXISTS** (good token layer, not global, with hardcoded leaks).

---

## 12. Design Token Audit

Present in `frontend/design-tokens.css`: `--bb-color-*` (primary/secondary/accent/neutral/
semantic), `--bb-font-size-*`, `--bb-font-weight-*`, `--bb-line-height-*`, `--bb-space-*`,
`--bb-radius-*`, `--bb-shadow-*`, `--bb-breakpoint-*`, `--bb-transition-*`.

Missing vs. the requested set: **no `--container-width` token** (container is a fixed
`.bb-template` rule), no `--font-heading` (single `--bb-font-primary`).

Owner: **Plugin**, scoped to `.bb-template`. Not available to the theme.

---

## 13. Theme Customization Audit

| Setting | Exists | Storage | UI | Frontend Effect | Classification |
| --- | --- | --- |
| Primary/secondary/accent color | No (fixed tokens) | CSS only | — | — | MISSING |
| Background/text color | No | CSS only | — | — | MISSING |
| Font family | No (fixed stack) | CSS only | — | — | MISSING |
| Font sizes/weights/line-heights | No | CSS only | — | — | MISSING |
| Spacing | No | CSS only | — | — | MISSING |
| Container width | No | CSS only | — | — | MISSING |
| Button style/radius | No | CSS only | — | — | MISSING |
| Card radius/shadows/borders | No | CSS only | — | — | MISSING |
| Header/Footer design choice | Partial: `header.layout`, template preset | section settings / page meta | Page Builder | limited | PARTIALLY EXISTS |
| Site business info | Yes (`bb_site_settings`) | option | Site Settings | via sections | EXISTS AND WORKS |

There is **no global Theme customization surface**. Page Builder content settings and site
settings exist, but no design-customization layer. Classification: **Global Theme
Customization: MISSING.**

---

## 14. Header Architecture

- Implemented as a **plugin section** (`header`) rendered by `render_header_section()`.
- One markup shape + `bb-header-{classic|enterprise}` class (a `layout` content field).
- Mobile menu: none in plugin markup (relies on theme/CSS).
- RTL/LTR: handled via logical CSS.
- **Multiple header designs:** only two class names, both within one hardcoded markup.

> **Header Variant System: MISSING** (one markup, cosmetic layout class only).

## 15. Footer Architecture

- Also a **plugin section** (`footer`) rendered by `render_footer_section()`.
- Brand + meta + socials + copyright; single markup shape, no variants.

> **Footer Variant System: MISSING.**

---

## 16. Page Template Audit

`Default` / `Modern` / `Luxury` change **only a wrapper class**. They do **not** change
header, footer, container, structure, or section rendering.

| Template | What it changes | Evidence |
| --- | --- | --- |
| Default | Wrapper class `bb-template-default` (+ full base CSS) | `SectionRenderer` + `frontend/*.css` |
| Modern | Wrapper class only; shell rules in **unloaded admin CSS** → **no visible change** | `template-shell.css` (page-admin) |
| Luxury | Wrapper class + **per-card tweaks** loaded from `frontend/section-*.css` | grep evidence |

---

## 17. The missing architecture (summary of §8,§10,§13)

> **MISSING: Section Variants · Component/Card Variants · Global Theme Settings ·
> Header Variants · Footer Variants · Navigation Variants · canonical Theme ·
> Variant cascade / override mechanism.**

---

## 18. Theme vs Template vs Preset (where each concept currently lives)

| Concept | Current home |
| --- | --- |
| WordPress Theme | Third-party theme (host); BB theme is an empty stub |
| Page Template | **Not used** as a WP page template — `_bb_page_template` is a cosmetic preset key, not a file in the theme |
| Design Preset | The `Default/Modern/Luxury` wrapper class (partial) |
| Section Variant | **Does not exist** |
| Component Variant | **Does not exist** |

---

## 19. CSS Architecture

- Single bundle `assets/css/frontend.css` @imports 17 files (`frontend/*`).
- All selectors scoped to `.bb-template …` (no global leakage) — good.
- Enqueued **only on builder pages** (`is_builder_page()`), plus on `single-bb_lawyer`.
- Specificity: `.bb-template .bb-x` beats theme defaults — the plugin "wins" inside its wrapper.
- **RTL**: logical properties + `.bb-rtl`; sound.
- **Conflict risk:** the outer page (theme) styles vs plugin `.bb-template` are separated by
  scope, so conflicts are contained — **but** the Modern/Luxury shell rules being in an
  admin file is a concrete breakdown.

## 20. JavaScript Architecture

- `assets/js/frontend/`: `status-lookup.js`, `payment-billing.js`, `manual-payment.js`,
  `receipt-modal.js` + a slider behaviour in the bundle.
- Section JS is behaviour-only (no markup duplication). Variants could share JS safely
  because JS hooks are data attributes (`data-slide-index`, `data-section-type`), not styling.

## 21. Template Duplication Findings

- **DUPLICATED**: header/footer markup (plugin section **and** theme render equivalents).
- **DUPLICATED (pattern)**: card markup is re-authored per section (lawyer/service/
  practice/testimonial/faq/feature) — same structural shape, separate inline PHP.
- **DUPLICATED (styling)**: buttons/`.bb-primary-button` and card CSS re-specified per
  section file; Luxury colour `#b8843c` repeated across 6 files.

## 22. Starter Site Integration

`StarterSite`/`StarterAdmin` create **pages + sections + builder meta** via the Builder
(uses the same section storage). They:
- **do** select the default template class (page meta),
- **do** create sections with settings,
- **do not** create any Theme settings, variants, or global design config.
→ Once a Theme System exists, Starter will need to seed Theme/Preset choices too.

## 23. Multisite Frontend Architecture

- Presentation is **per-site**: `_bb_page_template`/`_bb_page_sections` are per-post meta;
  `bb_site_settings` is a per-site option; the active theme is per-site.
- Therefore sites **can** differ in theme, pages, and preset — but **cannot** today differ
  in global design (no such settings) or section/component variants (they don't exist).
- The plugin's CSS/tokens are shared code, not per-site data.

## 24. Pack Isolation

- LawFirm presentation is **correctly isolated**: its CSS lives in the plugin and its
  sections register under the `law-firm` category; **no LawFirm styles are in Core CSS**.
- However, the **Modern/Luxury preset CSS mixes Core section styling and LawFirm card
  tweaks** in one mechanism, and the preset shell file lives in the *admin* layer — a
  layering violation.

## 25. Risks / Conflicts

| # | Risk | Severity |
| --- | --- | --- |
| 1 | Modern/Luxury shell CSS in unloaded admin file → preset has no frontend effect | **High** |
| 2 | Frontend depends on a foreign theme for the page shell (header/footer/layout) | **High** |
| 3 | Cards hardcoded in section methods → cannot be re-skinned without editing PHP | **High** |
| 4 | No global design customization → users cannot brand beyond presets | Medium |
| 5 | Duplicated header/footer (plugin + theme) → double chrome possible | Medium |
| 6 | Empty `business-builder` theme stub could confuse future work | Low |

## 26. Proposed Future Architecture (report only — DO NOT implement)

```
WORDPRESS THEME ("Business Builder" — real, canonical)
  ├─ Site shell (header.php/footer.php/index.php/page.php/single-bb_lawyer.php)
  ├─ Theme Presets (Default/Modern/Luxury)  → tokens + shell classes
  ├─ Design tokens (GLOBAL, theme-owned)    → --bb-* at :root/body scope
  ├─ Header variants / Footer variants / Navigation variants
  └─ Component templates (cards, buttons, badges) — overridable
            │  reads Theme Settings (Customizer/site option, per-site)
            ▼
BUSINESS BUILDER PLUGIN
  ├─ Data: CPTs, meta, taxonomy, business logic
  ├─ Section Registry: schema + DATA query + (variant id)
  └─ Section render: resolves a section VARIANT to a renderer/template;
       passes DATA (not markup) + variant + theme tokens
            ▼
SECTION VARIANT (per section type)  e.g. Lawyers: layout-01|02|03
            ▼
COMPONENT VARIANT (per card)        e.g. Lawyer Card: card-01|02|03
            ▼
FINAL FRONTEND
```

Ownership rules: tokens & shell = Theme; data & logic = Plugin; layout choice = Page
Builder (section meta `variant`); card skin = component variant token; cascade =
Theme Preset → Global Settings → Section Variant → Component Variant → section override.

## 27. Theme ↔ Plugin Responsibility Matrix (target)

| Responsibility | Theme | Plugin Core | Pack | Page Builder | Section | Component |
| --- | --- | --- | --- |
| Global Colors | **✓** | | | | | |
| Typography | **✓** | | | | | |
| Header/Footer/Nav | **✓** | | | | | |
| Page Layout | **✓** | | | | | |
| Section Data query | | **✓** | ✓ (pack) | | | |
| Section Layout (variant) | | | | **✓** (choose) | **✓** (render) | |
| Card Design (variant) | (templates) | | | | **✓** |
| Buttons/Badges | **✓** | (base) | | | | |
| Business Logic | | **✓** | ✓ | | | |
| Lawyer/Consultation/Appointment/Payment | | **✓** | ✓ | | | |

## 28. Recommended Implementation Phases (future)

1. **P8.1 — Fix the preset delivery bug** (move Modern/Luxury shell CSS into the frontend
   bundle) so presets actually render. *(Smallest, highest-value.)*
2. **P8.2 — Extract card markup into component templates** (no behaviour change).
3. **P8.3 — Introduce Section Variant field + renderer resolution** (registry additive).
4. **P8.4 — Introduce Component Variant tokens.**
5. **P8.5 — Build the canonical Theme (shell + tokens + customizer).**
6. **P8.6 — Header/Footer/Nav variants.**

## 29. Files that WOULD need modification (future — not now)

`SectionRenderer.php`, `SectionRegistry.php`, `PageManager.php`, `PageAdmin.php`,
`LawFirmSections.php`, `CoreSections.php`, `assets/css/frontend/*`,
`assets/js/frontend/*`, `Templates/*.php`, the theme directory, `StarterSite.php`.
Variant work is **additive** (new meta key + new resolution step), not a rewrite.

## 30. Files that must NOT be modified (this phase)

All payment/gateway/receipt/notification/activity/dashboard/consultation/appointment/
lawyer business logic, the transaction model, and all existing working sections'
*behaviour*. Phase 8 is read-only.

## 31. Decision

- **Option A (keep current):** rejected — the preset system is broken (Modern = no-op) and
  cards cannot be re-skinned without editing PHP; the frontend depends on a foreign theme.
- **Option B (canonical BB Theme):** needed for global design + header/footer variants,
  **but** must not move business logic out of the plugin.
- **Option C (Hybrid) — RECOMMENDED.** Evidence: the plugin already owns data, sections and
  scoped tokens; the missing pieces are (a) a **real theme** owning the shell + global
  tokens, and (b) **additive variant fields** in the Builder. This preserves every completed
  phase, keeps Multisite per-site semantics, and matches the existing `.bb-template`
  scoping model.

**Recommended: Option C — Hybrid (canonical Theme + Plugin sections + additive variants),
starting with the P8.1 preset-delivery fix.**

---

## 32. Complete File Inventory (frontend-related)

| File | Responsibility | Owner | Used By | Classification |
| --- | --- | --- | --- | --- |
| `wp-content/themes/business-builder/*` | Stub theme (0-byte files) | — | nothing | **UNUSED** |
| `includes/Core/Plugin.php` (`render_builder_content`, `enqueue_builder_frontend_assets`) | Bridge: theme content → sections; enqueue | Core | WP | EXISTS AND WORKS |
| `includes/Builder/SectionRenderer.php` | Section render + wrapper class + hardcoded core markup | Core | Plugin | EXISTS BUT NEEDS EXTENSION |
| `includes/Builder/SectionRegistry.php` | Section schema registry | Core | Builder | EXISTS AND WORKS |
| `includes/Builder/SectionManager.php` | Section storage helpers | Core | Builder | EXISTS AND WORKS |
| `includes/Builder/PageManager.php` | `is_builder_page`, meta keys | Core | Plugin | EXISTS AND WORKS |
| `includes/Builder/PageAdmin.php` / `REST/PageBuilderAjax.php` | Editor UI/AJAX | Core | Admin | EXISTS AND WORKS |
| `includes/Builder/CoreSections.php` | Core section schemas | Core | Registry | EXISTS AND WORKS |
| `packs/LawFirm/Sections/LawFirmSections.php` | Pack sections + card markup | Pack | Registry | EXISTS BUT NEEDS EXTENSION |
| `packs/LawFirm/Templates/{Classic,Modern,Luxury,Templates}.php` | Preset descriptors (slug+wrapper) | Pack | SectionRenderer/UI | PARTIALLY EXISTS |
| `packs/LawFirm/Frontend/LawyerProfile.php` + `templates/single-bb_lawyer.php` | Profile page (theme-coupled) | Pack | WP | EXISTS AND WORKS |
| `packs/LawFirm/Frontend/{ConsultationForm,BookingForm,BillingPage,StatusPage,LookupHandler,ReceiptRoute}.php` | Frontend forms/pages | Pack | WP | EXISTS AND WORKS |
| `templates/*.php` (9 files) | Form/partial templates | Pack | Sections | EXISTS AND WORKS |
| `assets/css/frontend.css` + `frontend/*.css` (19) | Frontend styles (scoped `.bb-template`) | Plugin | WP | EXISTS AND WORKS |
| `assets/css/page-admin/components/template-shell.css` | Modern/Luxury shell (ADMIN) | Plugin | Admin only | **EXISTS BUT BUGGY (should be frontend)** |
| `assets/js/frontend/*.js` (4) + bundled slider | Frontend behaviour | Plugin | WP | EXISTS AND WORKS |
| `packs/LawFirm/Starter/{StarterSite,StarterAdmin}.php` | Seed pages/sections | Pack | Admin | EXISTS AND WORKS |

---

**END OF PHASE 8 REPORT — no files were modified.**