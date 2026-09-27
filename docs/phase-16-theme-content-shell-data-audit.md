# PHASE 16 — THEME CONTENT & SHELL DATA SYSTEM — AUDIT

Read-only audit performed **before** any change. Evidence is quoted from the actual
source on disk. Environment: WordPress Multisite. Site 1 = `datalynx` (theme `astra`);
Site 2 = `Law Firm Demo` (theme `business-builder`).

---

## A. Current data sources (shell-relevant)

### A.1 WordPress-native identity (site 2)

| Source | API | Currently used by the shell? |
| --- | --- | --- |
| Site name | `get_bloginfo('name')` / `get_option('blogname')` | Yes — `bb_theme_site_identity()`, footer `h2`, data contract |
| Tagline | `get_bloginfo('description')` | Yes — footer variants |
| Home URL | `home_url('/')` | Yes — brand link, header data |
| Custom logo | `has_custom_logo()` / `the_custom_logo()` | Yes — `bb_theme_site_identity()` |
| Site icon | `get_site_icon_url()` | **No** — not used anywhere in the shell |
| Admin email | `get_option('admin_email')` | **No** — not used in the shell |
| Menus | `wp_nav_menu()` (locations `primary`, `footer`) | Yes |

### A.2 Theme-owned configuration (site 2, theme mods)

- Design system (Phase 14): `bb_theme_preset` + `bb_design_*` (28 tokens incl.
  `bb_design_header_bg/color`, `bb_design_footer_bg/color`).
- Shell variants (Phase 15): `bb_theme_shell_header|navigation|footer`.
- **No** theme-owned CTA / contact / social / utility-link settings exist today.

### A.3 Plugin-owned business data — `SiteSettings` (the decisive finding)

`includes/Settings/SiteSettings.php` (option **`bb_site_settings`**, per-site) is the
**authoritative owner** of:

```
business_name, tagline, logo_id, favicon_id,
primary_color, secondary_color, accent_color,
phone, email, address,
whatsapp,
facebook, instagram, youtube, linkedin, twitter,
show_phone, show_email, show_address, show_whatsapp,
require_consultation_payment, require_appointment_payment,
consultation_fee, consultation_currency, notification_email
```

It has a real sanitizer: phone/address/name/tagline → `sanitize_text_field`;
email → `sanitize_email`; whatsapp → `/[^0-9+]/`; social → `esc_url_raw`;
colors → `sanitize_hex_color`; `show_*` → bool cast.

**This is business-owned data (classification C).** It is exposed only through the
plugin: `Plugin::get_site_settings()` and the service container. There is **no**
public theme-facing accessor (no `bb_get_site_settings()`), and the Theme must not
depend upward on the plugin class.

### A.4 LawFirm pack data

Per-lawyer meta (`_bb_lawyer_phone`, `_bb_lawyer_email`, `_bb_lawyer_whatsapp`,
`_bb_lawyer_facebook/instagram/...`) is **pack-owned, per-post** (classification D/E).
Per-item business data; out of scope for global shell chrome.

### A.5 Plugin Page-Builder sections (page-level, classification D)

`SectionRenderer` already renders **`header`**, **`contact`** and **`cta_band`**
sections from per-page `_bb_page_sections`. These own page-level CTA/contact content
and are **global-shell-independent**. They must not be moved into the Theme.

### A.6 Current Theme shell data (`inc/shell-data.php`)

```php
bb_theme_shell_header_data()   → ['site_name','tagline','home_url','has_logo']  (filter bb_theme_shell_header_data)
bb_theme_shell_nav_location()  → 'primary'                                        (filter bb_theme_shell_nav_location)
bb_theme_shell_footer_data()   → ['site_name','tagline','has_menu','year']        (filter bb_theme_shell_footer_data)
bb_theme_shell_footer_menu( $class )  → wp_nav_menu('footer') when assigned
bb_theme_shell_copyright()            → "Copyright YYYY Site. All rights reserved."
```

Consumers: the header/footer variant templates only. Escaping: each helper escapes
its own output (`esc_html`/`esc_url`); the data arrays hold **raw-ish** strings
(already WordPress-sourced). The `*_data()` functions are `apply_filters`-able but
**no** plugin/pack/child-theme currently hooks them.

---

## B. Data Ownership Matrix

| Data | Current Owner | Desired Owner | Theme Can Read? | Storage | Extension Needed? |
| --- | --- | --- | --- | --- | --- |
| Site Name | WordPress | WordPress | Yes | WP option | No |
| Tagline | WordPress | WordPress | Yes | WP option | No |
| Logo | WordPress (custom_logo) | WordPress | Yes | WP | No |
| Site icon | WordPress | WordPress | Yes | WP | No (not shell-relevant) |
| Home URL | WordPress | WordPress | Yes | WP | No |
| Menus | WordPress | WordPress | Yes | WP nav | No |
| **Business phone** | Plugin `SiteSettings` | Plugin | via filter | `bb_site_settings` | **Yes** |
| **Business email** | Plugin `SiteSettings` | Plugin | via filter | `bb_site_settings` | **Yes** |
| **Business address** | Plugin `SiteSettings` | Plugin | via filter | `bb_site_settings` | **Yes** |
| **WhatsApp** | Plugin `SiteSettings` | Plugin | via filter | `bb_site_settings` | **Yes** |
| **Social links** | Plugin `SiteSettings` | Plugin | via filter | `bb_site_settings` | **Yes** |
| **show_* toggles** | Plugin `SiteSettings` | Plugin | via filter | `bb_site_settings` | **Yes** |
| Per-lawyer contact | LawFirm pack | LawFirm pack | no (per-post) | post meta | out of scope |
| Header CTA | **None authoritative** | Theme (generic slot) / Pack | n/a | — | **defer** (no source) |
| Utility links | **None** | Theme (if justified) | n/a | — | **defer** (no source) |
| Page CTA / contact | Plugin sections (page) | Plugin sections | no | `_bb_page_sections` | No (page-level) |

---

## C. Existing extension points

- Theme fires, the ecosystem may hook:
  - `bb_theme_header_variant`, `bb_theme_footer_variant` (Phase 15, preserved),
    `bb_theme_navigation_variant`, `bb_theme_shell_variant`, `bb_theme_shell_variants`,
    `bb_theme_shell_parts`.
  - **`bb_theme_shell_header_data`, `bb_theme_shell_footer_data`, `bb_theme_shell_nav_location`**
    — the prepared-data filters (Phase 15, currently unhooked).
  - `bb_theme_is_builder_page` (Phase 9) — **the exact precedent** for plugin→theme
    integration without upward dependency.
- Plugin public hooks: `bb_register_section_variants`, the `the_content` /
  `bb_theme_is_builder_page` integrations.

The pattern the codebase already uses for "plugin provides data the Theme presents"
is: **the Theme fires a filter; the plugin adds a callback.** Phase 16 must reuse
this, not invent a new one.

---

## D. Missing infrastructure (objectively missing)

1. **No bridge** exposes plugin `SiteSettings` contact/social data to the Theme's
   prepared shell data. Nothing in the Theme reads `bb_site_settings`; nothing in the
   plugin hooks `bb_theme_shell_*_data`. So the Theme cannot presently present
   business contact/social chrome, even though the business data exists.
2. **No shared empty-safe presentation helpers** for optional shell content
   (contact block, social block). Adding anything inline per variant would duplicate
   markup across 10 variants.
3. **No authoritative source** exists for a Header CTA label/URL or utility links
   (the builder `header` section is page-level; `bb_site_settings` has no CTA).

Everything else (identity, menus, storage, escaping, resolver) already exists.

---

## E. Recommended architecture (minimal, evidence-based)

Keep the Theme domain-agnostic and reuse the existing filter precedent:

```
Plugin SiteSettings (owner: business)
        │  add_filter('bb_theme_shell_footer_data', …)   ← plugin bridges DOWN
        │  add_filter('bb_theme_shell_header_data', …)
        ▼
Theme: bb_theme_shell_*_data()            (raw, prepared, filterable)
        ▼
Theme: optional-content presentation helpers (empty-safe, escaping at output)
        ▼
Shell variant templates (composition only)
```

Scope decisions justified by the audit:

- **Implement:** extend the prepared shell data contract with a **contact** and
  **social** group (both optional, both empty-safe), fed by the *existing* filters;
  add small shared presentation helpers so every existing variant consumes the same
  contract; **wire the plugin `SiteSettings` → `bb_theme_shell_*_data` bridge** (this
  is the one genuinely missing integration, and it belongs on the **plugin** side so
  the Theme never learns about `bb_site_settings`).
- **Defer:** Header CTA and utility links — **no authoritative source exists**, and
  §2.2 forbids inventing one. Provide the *contract slots* only if they cost nothing
  and understand they stay empty until a future owner or a documented Theme setting
  is justified. (Decision below.)
- **Not implement:** social framework, contact manager, business profile, mega menu,
  builders, per-page/per-device shell, export/import.

### CTA / utility-link decision (explicit)

The audit finds **no** authoritative CTA or utility-link source. Per §13/§15 the
correct action is to **not force a model**: expose the presentation slots
(`header.cta`, `header.utility_links`, `footer.utility_links`) in the prepared-data
contract so a pack or a future Theme setting can populate them, render **nothing**
when empty, and **ship no Theme setting and no fake data** in Phase 16. This is the
minimum that keeps the boundary clean without inventing content.

---

## F. Proposed file changes

### Theme (presentation)

| File | Action | Reason |
| --- | --- | --- |
| `themes/business-builder/inc/shell-data.php` | **Modify** | Extend the data contract (identity/navigation/header/footer groups incl. optional `contact`, `social`, `cta`, `utility_links`) and add shared, empty-safe presentation helpers (`bb_theme_shell_contact_block()`, `bb_theme_shell_social_block()`). |
| `themes/business-builder/assets/css/shell-content.css` | **Create** | Token-only structural CSS for the optional contact/social/cta slots. Loaded via the existing pipeline. |
| `themes/business-builder/inc/enqueue.php` | **Modify** | Enqueue the new stylesheet (one line) in the existing chain. |
| Shell variant templates (header/footer) | **Modify minimally** | Consume the shared helpers in the slots where contact/social naturally appear (footer variants, header actions), **empty-safe**. |

### Plugin (one bridge file)

| File | Action | Reason |
| --- | --- | --- |
| `includes/Theme/ThemeShellData.php` | **Create** | Bridges `SiteSettings` → `bb_theme_shell_header_data` / `bb_theme_shell_footer_data`. Reads `bb_site_settings` (business data) and maps it into the **generic** presentation contract. This keeps the Theme domain-agnostic (`phone`/`email`/`social`, never a LawFirm concept). |
| plugin bootstrap (`business-builder-core.php`) | **Modify** | Require/instantiate the bridge. |

### Explicitly NOT to touch

Design system, shell resolver, section/component variants, Page Builder
(`SectionRegistry`/`SectionRenderer`/`PageBuilderAjax`/`PageManager`/`page-admin.js`),
business systems (consultation/appointment/payment/notification/activity/dashboard),
LawFirm sections/components/variants, `assets/js/theme.js`.

---

## G. Explicit non-goals (Phase 16 will NOT implement)

Mega menu; drag-and-drop header/footer builder; CTA management system; social-media
platform; contact/BusinessProfile management platform; CRM; appointment/consultation
systems; page-specific shell builder; per-device shell config; shell export/import;
Theme marketplace/theme packs; custom CSS editor; arbitrary HTML editor; a Theme-owned
copy of business phone/email/address/social; any invented CTA/social/contact content.

---

## Ownership decision summary

- WordPress stays the owner of identity (name, tagline, logo, URL, menus).
- The **plugin** stays the owner of business contact/social (`bb_site_settings`).
- The **Theme** owns presentation only: the prepared-data contract, the helpers, and
  the markup. It reads business data **only** through the existing filters.
- Page-level CTA/contact stays with the Page Builder sections.
- Header CTA / utility links: **deferred** (no authoritative source); the contract
  slots are exposed, empty by default, and render nothing.

**Implementation may proceed with the scope in §E–§F.**