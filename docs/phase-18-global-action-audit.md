# PHASE 18 — BUSINESS ACTION / GLOBAL CTA OWNERSHIP — AUDIT

Read-only audit performed **before** any change. Evidence quoted from source.
WordPress Multisite. Site 1 = `datalynx` (Astra); Site 2 = `Law Firm Demo`
(business-builder theme, `bb_business_type = law_firm`).

The governing rule of this phase: **do not implement a global action unless the audit
proves a genuine, generic product need with a real authoritative source.**

---

## 1. Current CTA inventory (every CTA-like thing that actually exists)

| # | Concept | Where it lives (evidence) | Scope | Owner | Label + URL? |
| --- | --- | --- |
| 1 | `header` section CTA | `SectionRenderer::render_header_section()` reads `$content['cta_text']` / `$content['cta_url']` (default `'#'`) | **Page-level section** | Page Builder | Yes — but per page |
| 2 | `cta` section | `render_cta_section()` (`cta_text`/`cta_url`) | **Page-level section** | Page Builder | Yes — but per page |
| 3 | Hero/slider CTA | renderer `$content['button_url']` etc. | **Page-level section** | Page Builder | Yes — but per page |
| 4 | `legal_service` CTA | `_bb_legal_service_cta_text` + `_bb_legal_service_cta_url` (`LegalServiceFields`) | **Per-entity** (each service) | LawFirm Pack | Yes — per service record |
| 5 | Consultation form submit | `Frontend/ConsultationForm.php` (posts to `admin-post.php`; redirects to referring page) | **Page-level section** | LawFirm Pack | It is a form, not a CTA |
| 6 | Booking form submit | `Appointments/BookingForm.php` | **Page-level section** | LawFirm Pack | Form, not a CTA |
| 7 | Footer `Quick Links` menu | `bb_theme_shell_footer_menu()` (WP nav menu location `footer`) | **Global (menu)** | WordPress | Labels + URLs, but only a nav menu |
| 8 | Brand link | `bb_theme_site_identity()` → `home_url('/')` | **Global** | WordPress | URL only (home) |
| 9 | Business phone/email | `bb_site_settings` | **Global business-wide** | Plugin | Actionable (`tel:`/`mailto:`) but not a "CTA" |
| 10 | Social links | `bb_site_settings` → footer social slot | **Global business-wide** | Plugin | Links to social profiles |

**There is no field anywhere named `primary_action`, `global_cta`, `cta_label`, `cta_url`,
`action_url`, `call_to_action`, or `business_action`.** A project-wide search for those
identifiers returns only the per-page section content and the per-service meta above.

`SiteSettings::get_defaults()` (the plugin's business-wide store) contains **no** CTA/action
field: `business_name, tagline, logo_id, favicon_id, primary_color, secondary_color,
accent_color, phone, email, address, whatsapp, facebook, instagram, youtube, linkedin,
twitter, show_phone, show_email, show_address, show_whatsapp,
require_consultation_payment, require_appointment_payment, consultation_fee,
consultation_currency, notification_email`.

## 2. Global vs page-level analysis

- **Every CTA-like destination in the product today is page-level or per-entity** (#1–#6).
  That is not accidental: `Starter/StarterSite.php` seeds the `header` section at the **top of
  every page** and `cta`/`footer` sections per page. The product's "site header / CTA" is
  therefore implemented as a **repeated page section**, and each page owns its own CTA text/URL.
- The only genuinely **global** chrome is: site identity/brand link (WordPress), the footer
  nav menu (WordPress), and business contact/social (Plugin `SiteSettings`, already bridged).
- **Therefore no CTA is currently "global" — and nothing was ever *intended* to be global.**
  Promoting a page-level `header`/`cta` section URL into the Theme shell would (a) pick an
  arbitrary page's content, (b) break the per-page ownership model, and (c) violate Phase 16/17
  rules.

## 3. Business-type generality analysis

The plugin already ships a real **`BusinessType` registry** with five entries — Law Firm,
Medical, Real Estate, Education, and a generic default (`includes/Settings/BusinessType.php`).
So "is a global action generic?" is a genuine product question, not hypothetical:

| Business Type | Natural primary action | Fit for one generic concept? | Domain-specific? |
| --- | --- | --- | --- |
| Law Firm | Book a consultation | Partially — as a *label*, yes | The **destination/meaning** is LawFirm-specific (form + practice area) |
| Medical | Book an appointment | Partially | Destination is Medical-specific (availability/scheduling) |
| Real Estate | Request a viewing | Partially | Destination is listing-specific (per property) |
| Education | Enroll / Contact | Partially | Destination is program-specific |
| Company | Request a quote | Partially | Destination is a quote form (page-level) |

**Conclusion:** a *label* like "Primary action" is generic, but the **destination and meaning
are domain- or page-specific in every case**. What is genuinely generic is only the *presentation
slot* (`header.cta`) — which Phase 16 **already provides**. There is no evidence of a generic
*business action* concept beyond "a link the owner wants in the header", and no authoritative
source that would populate it.

## 4. Potential consumers

| Consumer | Exists? | Needs a global action? | Evidence | Decision |
| --- | --- | --- | --- | --- |
| Theme Header shell | Yes (`bb-header-actions`) | Slot exists and is **ready**, but nothing authoritative to fill it | `bb_theme_shell_render_cta()` + `header.cta` | Keep slot; leave empty |
| Theme Footer shell | Yes | No evidence the footer should carry a CTA | Footer contract has `utility_links`, no CTA | No |
| Navigation | Yes (`primary` menu) | No — nav is WP-menu-owned; a CTA there is a menu item | `bb_theme_navigation()` | Do not modify |
| Mobile menu | Yes (toggle panel) | No — same nav | `theme.js` / `.bb-nav` | No |
| Hero / page sections | Yes — **this is where CTAs actually live** | Already satisfied per page | `render_header_section()` / `render_cta_section()` | Keep page-level |
| Floating / sticky action | **No** | No existing feature | — | Out of scope |

The single existing consumer of a *global* CTA is the **Theme header actions area**, and it is
already built (Phase 15/16). No other consumer exists or is planned.

## 5. Action-type analysis

The existing shell contract's `cta` is `label + url (+ new_tab → target=_blank, rel)`. That is
the smallest abstraction that matches every real CTA in the product. There is **no evidence**
that a URL + label is insufficient — but there is also **no authoritative source** to fill it.

Do **not** build an action registry/handler/resolver/type-manager. No such architecture exists
and nothing requires it.

## 6. Ownership matrix

| Concept | WordPress | Plugin | Pack | Page Builder | Theme |
| --- | --- | --- | --- | --- | --- |
| Site identity | ✓ | | | | presentation |
| Business phone / email / address / whatsapp | | ✓ | | | presentation |
| Social links | | ✓ | | | presentation |
| Page CTA (header/cta/hero sections) | | | | ✓ | presentation |
| Service-level CTA | | | ✓ | | presentation |
| Consultation / appointment (domain) | | | ✓ | (page form) | presentation |
| **Global business action** | **— none exists —** | **—** | **—** | **—** | presentation slot only |

**The final row has no owner because the concept has no authoritative instance in the product.**
The only generic thing is the presentation slot, owned by the Theme, fed by the Phase-16
filters — which is already correct.

## 7. Storage analysis

- There is no CTA/action field in `bb_site_settings`. Adding `primary_action_label` /
  `primary_action_url` would be **new generic business storage** — but the audit finds **no
  product requirement and no source** for it. Per §1/§39, do not add it.
- `bb_global_cta` / `bb_business_actions` / `bb_action_settings` must **not** be invented.

## 8. Theme Customizer analysis

A CTA label/URL is **business content**, not presentation/design. The Theme Customizer is
scoped (Phase 14) to tokens, presets, shell layout and variants. Business content does **not**
belong there. Even if a global action existed, its home would be Plugin business settings, not
the Customizer — and the audit finds nothing to add to either.

## 9. Localization analysis

The product already has a `Language` service (`default_language = 'ar'`, with direction
detection) and relies on WordPress localization. A label typed by the owner is **user-entered
business content** (single value, admin-language-agnostic), exactly like `business_name`. A
pack-provided label would use pack translations. Either way, **no duplicated Arabic/English
fields are warranted** — and since nothing is being added, this is moot.

## 10. URL ownership analysis

If a global action existed, its URL could be internal, external, `tel:`, `mailto:` or WhatsApp.
The existing shell `cta` normaliser already: accepts only `http`/`https`, requires label+url,
supports `new_tab` → `_blank` with hardened `rel`. That is sufficient. No new URL machinery is
needed — and **no URL may be invented** (§16 of Phase 17, §19 here).

## 11. Multisite implications

Any future action setting would be a theme mod or `bb_site_settings` entry — **site-specific by
construction**. No network-level storage. Nothing is being added, so isolation is unchanged
(and remains verified).

## 12. Product Decision

**OUTCOME A — NO GLOBAL ACTION REQUIRED.**

There is no authoritative, business-wide, business-type-agnostic destination to promote, and no
product feature that consumes one beyond a presentation slot that already exists. Every real CTA
in the product is intentionally **page-level** (Page Builder) or **per-entity** (Service meta) or
already covered by **Plugin contact/social** (phone/email/social). Inventing `booking_url` /
`consultation_url` / `primary_action_url` would:
- put page/domain meaning into generic Plugin storage, or
- propose a destination that does not exist (no public consultation/booking route — Phase 17).

## 13. Architecture Decision

No new abstraction. The correct architecture is the one already in place:

```
Page Builder      → page-level CTA/contact/form (header/cta/hero/contact sections)
Pack (LawFirm)    → domain meaning + service-level CTA + consultation/booking forms
Plugin            → business-wide contact/social (SiteSettings) → existing bridge
Theme             → presentation only (header.cta / utility_links slots, empty-safe)
```

If, in a future phase, a **genuine business-wide destination** is confirmed (e.g. a documented
`booking_url`-style Plugin setting, or a pack that ships a real public booking route), it would
flow: **owner → existing Phase-16 filters → `header.cta` → existing shell variants**, with **no
Theme changes**. That path is already proven ready by the Phase-17 test suite.

## 14. Implementation Decision

**Phase 18 implementation: NONE.**

- No new setting, option, Customizer control, CPT, taxonomy or table.
- No new route, form or booking system.
- No pack provider (no authoritative source — Phase 17 conclusion stands).
- No Theme change (the slot already exists and is empty-safe).
- No Plugin change.

**Future condition that would justify implementation:** a confirmed, authoritative
**business-wide** destination — specifically, either (a) a product decision to add a Plugin-owned
business setting such as `booking_url` (business-wide, generic, sanitized, site-specific), or
(b) a pack that ships a genuine public booking/consultation route. Until then, leaving
`header.cta` empty is the correct, evidence-backed outcome.

---

## Appendix — Phase 16/17 infrastructure is sufficient (why)

- `bb_theme_shell_header_data` / `bb_theme_shell_footer_data` filters exist and are documented.
- The Theme's `cta` / `utility_links` / `contact` / `social` slots are **empty-safe** (no wrapper
  when empty) and render through **every** header/footer variant.
- The Phase-17 suite proves a provider can populate `cta` through the existing filters with
  **zero Theme changes**, and that unsafe input is rejected/escaped.

Nothing needs to be built now; the boundary is correct and the mechanism is ready.