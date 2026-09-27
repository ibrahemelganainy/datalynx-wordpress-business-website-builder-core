# PHASE 10 — COMPONENT ARCHITECTURE — FINAL REPORT

## A. Audit Before Changes

The repeatable LawFirm card markup lived **inline** in
`packs/LawFirm/Sections/LawFirmSections.php` (5 render methods, string-concatenated
`echo` chains). A theme component layer did not exist. Full evidence is in
`docs/phase-10-component-inventory.md` (produced before any change).

Key audit facts:
- The 5 cards share **only a shell** (surface/border/radius/shadow/hover); their bodies
  differ completely (lawyer = photo + linked name + role/experience/phone/email/social;
  service/practice = image|icon + h3 + p; testimonial = image + rating + blockquote +
  author; FAQ = h3 + answer in a `<div>`, not an `<article>`).
- **No JavaScript reads any card class** — JS binds to `data-bb-*` and stable IDs. Card
  classes are a CSS-only contract.
- No hook is attached to an individual card (hooks are on the `<section>` wrapper).
- Two defects were found: (1) **mojibake rating stars** `'â˜…'` at line 990 (the real `★`
  had been double-encoded); (2) **hardcoded design values** in the luxury blocks of 4 CSS
  files, plus a competing `--bb-fe-luxury*` gap that forced literals.

## B. Component Inventory

| Existing markup | File | Domain | Reusable? | Component | Owner |
| --- | --- | --- |
| Lawyer card | `LawFirmSections.php` | LawFirm | Yes | `lawyer/card` | Pack |
| Service card | " | LawFirm | Yes | `service/card` | Pack |
| Practice area card | " | LawFirm | Yes | `practice-area/card` | Pack |
| Testimonial card | " | LawFirm | Yes | `testimonial/card` | Pack |
| FAQ item | " | LawFirm | Yes | `faq/item` | Pack |
| Section heading | " | Generic | Yes | `section-heading` | **Theme** |
| Empty state | " | Generic | Yes | `empty-state` | **Theme** |

## C. Components Created

**Theme (generic presentation):**
- `templates/components/section-heading.php`
- `templates/components/empty-state.php`
- `inc/components.php` — the resolver/render API

**LawFirm Pack (domain presentation):**
- `packs/LawFirm/Sections/components/lawyer/card.php`
- `packs/LawFirm/Sections/components/service/card.php`
- `packs/LawFirm/Sections/components/practice-area/card.php`
- `packs/LawFirm/Sections/components/testimonial/card.php`
- `packs/LawFirm/Sections/components/faq/item.php`

## D. Components Rejected (deliberately NOT abstracted)

| Candidate | Why rejected |
| --- | --- |
| `SectionRenderer` feature card (`bb-feature-card`) | Core-plugin markup, different structure (icon + h3 + p); out of Phase-10 scope; no repeated owner |
| `SectionRenderer` generic item card (`bb-section-item-card`) | Built from a dynamic key→element map — abstracting it would invent a contract that does not exist |
| Contact card (`bb-contact-card`) | One-off, no repetition |
| A single "mega card" for all 5 LawFirm cards | Their **bodies differ**; merging would create the forbidden "mega section" (§38) and change semantics (FAQ is not an `<article>`) |
| `hero`, `cta`, `slider`, `about` sections | Single-use, already cohesive |

## E. Files Created

```
theme/business-builder/inc/components.php
theme/business-builder/templates/components/section-heading.php
theme/business-builder/templates/components/empty-state.php
packs/LawFirm/Sections/components/lawyer/card.php
packs/LawFirm/Sections/components/service/card.php
packs/LawFirm/Sections/components/practice-area/card.php
packs/LawFirm/Sections/components/testimonial/card.php
packs/LawFirm/Sections/components/faq/item.php
docs/phase-10-component-inventory.md
tests/runtime-phase10-components.php
```

## F. Files Modified

| File | Reason |
| --- | --- |
| `packs/LawFirm/Sections/LawFirmSections.php` | Rewired the 5 card renderers + `render_heading()`/`render_empty()` to call components; registered the pack component root via `bb_component_roots`; **fixed the mojibake stars** |
| `assets/css/frontend/section-base.css` | Bridged the new luxury tokens into the `--bb-fe-*` component layer |
| `assets/css/frontend/section-lawyers.css` | Luxury block → tokens |
| `assets/css/frontend/section-legal-services.css` | Luxury block → tokens |
| `assets/css/frontend/section-practice-areas.css` | Luxury block → tokens |
| `assets/css/frontend/section-testimonials.css` | Luxury block + decorative quote font → tokens |
| `assets/css/frontend/section-faq.css` | Luxury block → tokens |
| `theme/business-builder/assets/css/tokens.css` | Added canonical tokens: `--bb-color-surface-translucent`, `--bb-color-luxury*`, `--bb-font-heading-serif` |
| `theme/business-builder/functions.php` | Load `inc/components.php` |

**No payment / consultation / appointment / notification / activity / dashboard / receipt
file was touched.**

## G. Files Deleted
None.

## H. Component Contracts

`bb_render_component( string $component, array $args = [], ?string $variant = null ): string`
and the echo-wrapper `bb_component(...)`. Unknown component → empty (debug comment when
`WP_DEBUG`); path traversal rejected; **no fatal** for missing optional values.

| Component | Args |
| --- | --- |
| `section-heading` | `title` (raw), `description` (HTML via `wp_kses_post`) |
| `empty-state` | `message` (raw, escaped) |
| `lawyer/card` | `name, profile_url, is_public, photo_html, show_photo, role, experience, phone, email, profile_link` |
| `service/card` | `title, summary, image_html, icon` |
| `practice-area/card` | `title, summary, image_html, icon` |
| `testimonial/card` | `author, author_title, quote, rating, image_html` |
| `faq/item` | `question, answer` |

**Contract:** args are *raw presentation data*; the component escapes **once** (no double
escaping). `*_html` args are pre-rendered WordPress image HTML passed through unchanged.

## I. Ownership

```
THEME owns:    bb_render_component API, section-heading, empty-state, tokens, presets,
               shell/header/footer/nav
PLUGIN owns:   SectionRenderer core markup, Page Builder, section storage, business logic
LAWFIRM owns:  lawyer/service/practice-area/testimonial/faq components + section CSS
```
Dependency direction is one-way: **Pack → Theme** (verified: the theme never references a
LawFirm class; the pack registers its root via a filter).

## J. Compatibility preserved

- **CSS classes**: `bb-lawyer-card`, `bb-service-card`, `bb-practice-area-card`,
  `bb-testimonial-card`, `bb-faq-item`, `bb-lawyer-link`, `bb-section-heading`, … all **unchanged**.
- **JS selectors**: untouched (JS uses `data-bb-*`; no card class is a JS hook).
- **Hooks**: `bb_section_classes` / `bb_section_attributes` / `bb_render_section_*` preserved;
  new opt-in `bb_before_component` / `bb_after_component` added (only two, where beneficial).
- **IDs / URLs / semantic HTML**: identical (`article`/`blockquote`/`h3`/`a` kept; FAQ stays a `div`).
- **Standalone plugin**: `render_heading()`/`render_empty()` fall back to inline markup when
  the theme API is absent — the plugin still works without the theme.

## K. CSS Architecture

Components consume `--bb-*` (canonical) via the `--bb-fe-*` bridge in `section-base.css`.
**Zero bare hardcodes remain** in the card CSS (verified programmatically). The luxury
colors are now tokens: `--bb-color-luxury` → `--bb-fe-luxury` → `.bb-template-luxury .bb-X-card`.
No competing namespace was introduced.

## L. RTL / LTR

| Test | Result |
| --- | --- |
| LTR (`/services/`) | PASS — 0 errors, 0 failed requests, no overflow |
| RTL (`WPLANG=ar`) | PASS — `dir="rtl"`, body `bb-rtl`, card renders, no overflow, 0 errors |

## M. Responsive

| Width | Result |
| --- | --- |
| 390px | PASS — no horizontal overflow, cards usable |
| 768 / 1024 / 1440+ | PASS — auto-fit grid (`bb-grid-columns-N`), no overflow |

## N. Testing

```
PHP lint (all new + modified):        PASS (0 errors)
Phase 9 suite:                        24/24 PASS
Phase 10 component suite:             31/31 PASS
Regression payment-completion:        32/0 PASS
Regression manual-review:             29/0 PASS
Regression markpaid-sync:              6/0 PASS
Regression activity+isolation:        11/0 PASS
Live frontend /, /about/, /services/,
  /practice-areas/, /lawyers/          PASS (200, shell, no fatal)
Live 404                               PASS (theme 404 template)
Live cards rendered:
  /practice-areas/  2× bb-practice-area-card
  /services/        1× bb-service-card
  /home/            1× bb-lawyer-card + 1× bb-service-card
  /about/           2× bb-lawyer-card
Mojibake check (live HTML)             PASS (no Â / â)
Preset test (luxury→--bb-color-luxury→card) PASS
Multisite isolation (site 1 Astra)     PASS
Console / network (desktop, RTL)       0 errors / 0 failed requests
Real gateway / live-money              NOT TESTED (no credentials; out of scope)
```
No test was weakened or deleted.

## O. Known Limitations (deferred)

- **Section Variants**, **Component/Card Variants** (design variants) — the `variant`
  argument and `.variant` file-resolution exist, but no variants are shipped (§20).
- Theme **header/footer/nav variants**, full **Customizer**.
- Legacy per-page `_bb_page_template` semantics left **untouched** (§50).
- **Pre-existing, NOT fixed (out of scope):** the plugin's header/footer *sections*
  (`bb-header-bar`/`bb-footer-bar`) are unstyled on the front end because their CSS lives in
  the admin-only `assets/css/page-admin/components/template-shell.css`. This was reported in
  Phase 8/9 and is unrelated to component extraction (that file was not touched).

## P. Recommended Next Phase

**PHASE 11 — SECTION VARIANT ARCHITECTURE.** The audit shows the cleanest next step is not
card variants but **section layout variants**: the 5 section render methods now end in a
single component call, so a `variant` field on a section can select among layout templates
(grid / list / featured) while reusing the components that now exist. Card *design* variants
should follow once section layouts can choose them.

---

### Defects fixed this phase
1. **Mojibake rating stars** (`'â˜…'`) → replaced with the `★` code point (`\u{2605}`), source
   kept pure ASCII, plus an `aria-label` for assistive tech.
2. **12 bare hardcoded design values** in the 4 luxury CSS blocks + 1 decorative font →
   migrated to canonical `--bb-*` tokens (the theme `tokens.css` gained 6 tokens).