# PHASE 17 — SHELL CONTENT OWNER SLOTS / PACK SHELL-DATA PROVIDERS — AUDIT

Read-only audit performed **before** any change. Evidence quoted from source.
WordPress Multisite. Site 1 = `datalynx` (Astra, `bb_business_type` unset);
Site 2 = `Law Firm Demo` (business-builder theme, LawFirm pack active).

---

## A. Existing shell-data architecture (Phase 16 — inspected, must be preserved)

`themes/business-builder/inc/shell-data.php` exposes (all filterable):

```
bb_theme_shell_header_data()  → site_name, tagline, home_url, has_logo,
                                 utility_links[], cta|null, contact|null
bb_theme_shell_footer_data()  → site_name, tagline, has_menu, year,
                                 contact|null, social[], utility_links[]
bb_theme_shell_nav_location() → 'primary'
```

Filters: `bb_theme_shell_header_data`, `bb_theme_shell_footer_data`,
`bb_theme_shell_nav_location`. Presentation helpers (empty-safe, escaping at output):
`bb_theme_shell_render_contact|social|links|cta()`.

The **plugin** already bridges its business settings down:
`includes/Theme/theme-shell-data.php` hooks `bb_theme_shell_header_data` and
`bb_theme_shell_footer_data` on `after_setup_theme` and fills `contact`/`social`
from `bb_site_settings`, **non-destructively** (only when the slot is empty).

**This is the canonical contract. Phase 17 must reuse it.**

## B. LawFirm source inventory (what actually exists)

| Candidate | Exists? | Where (evidence) | Owner | Global-safe? |
| --- | --- | --- | --- | --- |
| Consultation **form** | Yes | `packs/LawFirm/Frontend/ConsultationForm.php` + `templates/consultation-form.php`, rendered by `LawFirmSections::render_consultation_section()` | Pack / **page section** | No |
| Consultation **public URL** | **No** | The form posts to `admin-post.php` (`action=bb_consultation`) and `wp_safe_redirect`s back to the **referring page** (`get_redirect_url()` → `wp_get_referer()` / `home_url('/')`, appended `#bb-consultation-form`). There is no rewrite rule, page, or permalink for "consultation". | — | — |
| Booking / appointment **form** | Yes | `packs/LawFirm/Appointments/BookingForm.php` + booking section | Pack / **page section** | No |
| Booking **public URL** | **No** | Same pattern as consultation (a section-embedded form on a page) | — | — |
| Appointment/consultation **receipt/status** routes | Yes | `Frontend/StatusPage.php`, `BillingPage.php`, `ReceiptRoute.php` | Pack | **No** — these are per-request, **token/reference-gated** pages for an individual customer's pending payment/receipt, not a global destination |
| "Contact" **page** | Yes (seeded) | `Starter/StarterSite.php` seeds a fixed page set: `home`, `about`, `services`, `lawyers`, `contact` | Pack **seed content** (per-page post) | No — user-owned/editable/deletable, not configuration |
| "Contact" **section** | Yes | `CoreSections::register('contact', …)` (Core, not LawFirm) | Core section, **page-level** | No |
| `header` builder section **CTA** | Yes | `SectionRenderer::render_header_section()` reads per-page `$content['cta_text']`/`cta_url` (default `'#'`) | Page Builder / **page-level** | **No** |
| `cta` builder section | Yes | `CoreSections::register('cta', …)`, `SectionRenderer::render_cta_section()` | Page Builder / **page-level** | No |
| Global **phone / email / address / whatsapp / social** | Yes | `bb_site_settings` (`Settings\SiteSettings`) | **Plugin** | Yes — already bridged (Phase 16) |
| Per-lawyer contact / profile | Yes | `_bb_lawyer_phone|email|whatsapp|…` post meta, `Frontend/LawyerProfile.php` | **Pack, per-post** | No — individual, not business-wide |
| Any `*_url` / CTA **setting** | **No** | `SiteSettings::get_defaults()` has **no** CTA/booking/consultation URL field. `packs/LawFirm/config.php` = `{name, slug}` only. | — | — |
| Client portal / login / account | **No** | No such system exists | — | — |

### Pack activation (isolation evidence)

`includes/Core/ServiceProvider.php:296` registers the pack conditionally:

```php
$this->pack_manager->register( 'law_firm', LawFirmPack::class );
```

`PackManager` only instantiates/`register()`s a pack whose slug matches the site's active
`bb_business_type`. So LawFirm code — and any LawFirm provider — **loads only when the
LawFirm pack is active on that site**. An unset/different business type means the pack is
absent, so a LawFirm provider is naturally absent. That is exactly the isolation Phase 17
requires, with no new mechanism.

### Where a shell contributor could hook (if justified)

`LawFirmPack::register()` is the pack's registration entry point (called at `init` via
`PackManager`). A provider would be registered there, or the pack could subscribe to
`bb_theme_shell_header_data` / `bb_theme_shell_footer_data` — the same filters the plugin
bridge already uses.

## C. Ownership matrix

| Data | Current Owner | Candidate Provider | Global Shell Safe? |
| --- | --- | --- | --- |
| Site name / tagline / logo / URL | WordPress | — | Yes (already in contract) |
| Phone / email / address / WhatsApp | Plugin `SiteSettings` | — (do **not** duplicate in pack) | Yes (already bridged) |
| Social links | Plugin `SiteSettings` | — (do **not** duplicate) | Yes (already bridged) |
| Consultation CTA | **none** (form is a page section; no URL) | — | **No authoritative source** |
| Appointment/Booking CTA | **none** (form is a page section; no URL) | — | **No authoritative source** |
| Utility links | **none** | — | **No authoritative source** |
| "Contact" page/CTA | Pack **seed** content (per-page) | — | No — page-level, user-owned |
| Header/CTA builder sections | Page Builder (per-page) | — | No — page-level by design |
| Per-lawyer contact/profile | Pack (per-post) | — | No — individual, not business-wide |

## D. Provider location decision

If a source existed, the correct owner would be one of:

- **LawFirm pack** (`packs/LawFirm/…`) for genuinely LawFirm-domain data, or
- the **plugin Theme layer** (`includes/Theme/…`) for business-wide data.

The audit found **neither** owns a global shell-only value that is not already covered:

- Business-wide contact/social is **already** owned by `SiteSettings` and **already**
  bridged by `includes/Theme/theme-shell-data.php`.
- Everything LawFirm-specific that could plausibly be a "CTA" is a **page-level section**
  (consultation form, booking form, contact section, header CTA), i.e. it belongs to the
  **Page Builder / page content** layer, which Phase 16 explicitly keeps out of the global
  shell.

## E. Implementation decision (per contribution)

| Contribution | Decision | Reason |
| --- | --- | --- |
| `header.cta` from a consultation/booking page | **Do NOT implement** | No authoritative source. The consultation/booking forms are page sections with no public URL; the seeded `contact` page is user-owned seed content; the builder CTA is page-level. Promoting any of these to a global header CTA would invent a destination (§16) and violate page-level ownership (§6). |
| `header.utility_links` | **Do NOT implement** | No client portal / account / appointment area exists to link to. |
| `footer.utility_links` | **Do NOT implement** | Same — no authoritative source. |
| `footer.contact` / `footer.social` / `header.contact` | **Already implemented (Phase 16)** | Owned by `SiteSettings`, bridged by the plugin. Nothing to add; LawFirm must **not** duplicate. |

### Result: **No provider implementation required yet.**

This is the explicitly-valid "no implementation" outcome (§48). The Phase-16 extension
points remain the ready mechanism; a LawFirm (or any pack) provider can be added later the
moment an **authoritative, domain-level** source actually exists (e.g. a real
business-level "booking URL" setting or a genuine public consultation route). Until then,
leaving `cta` empty and `utility_links` empty is correct, and the site behaves exactly as
in Phase 16.

## F. What Phase 17 therefore delivers

1. **This audit** documenting the real sources and the ownership decision.
2. A **Phase 17 runtime test** that *proves* the invariants that make a future provider
   safe, and that the no-provider outcome is correct:
   - the Theme contract is intact and empty-safe;
   - a hypothetical external provider (standing in for a future LawFirm provider) can add
     `cta`/`utility_links` **through the existing filters without any Theme change**;
   - unsafe provider data (bad scheme, bad target, raw HTML, malformed URLs) is rejected/escaped;
   - the plugin bridge's contact/social ownership is preserved (non-destructive merge);
   - a provider can be removed and the shell returns exactly to the no-provider state;
   - Theme remains domain-agnostic (no LawFirm references);
   - Multisite isolation.
3. A **final report** with the ownership record and the deferral rationale.

No Theme, Plugin, Page Builder, or business file is modified. No storage, routes, settings,
or Customizer controls are added.

## G. Explicit non-goals (Phase 17 will NOT do)

Create a consultation/booking route; add a CTA/utility-link setting or Customizer field;
duplicate phone/email/social in the pack; promote a page-level CTA to the global shell;
add a client portal/CRM/appointment feature; modify the shell resolver, design system, or
Page Builder; introduce new storage.

**Conclusion: implement no pack provider now; prove the architecture is ready and the
no-provider behavior is correct. Proceed to the test + report.**