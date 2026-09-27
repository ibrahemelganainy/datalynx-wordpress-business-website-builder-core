# PHASE 17 — SHELL CONTENT OWNER SLOTS / PACK SHELL-DATA PROVIDERS — FINAL REPORT

## 1. Audit Results

Audit written before any change: `docs/phase-17-shell-content-provider-audit.md`.

The decisive finding: **no authoritative domain-level shell source exists in the
LawFirm pack.** Everything that could *look* like a global CTA or utility link is either
(page-level) or (already owned by the plugin) or (per-lawyer). Concretely:

- Consultation and booking are **page-builder sections** with **no public URL** — the forms
  POST to `admin-post.php` and redirect back to the *referring page*.
- Receipt/status/billing routes exist but are **token/reference-gated per-customer** pages,
  not global destinations.
- A `contact` page exists only as **pack seed content** (user-owned, deletable).
- The builder `header`/`cta` sections carry per-page CTA text/URL — **page-level by design**.
- Business phone/email/address/whatsapp/social are owned by **plugin `SiteSettings`** and
  **already bridged** in Phase 16.
- Per-lawyer contact/profile is **pack-owned, per-post**.
- There is **no** CTA/booking/consultation URL setting anywhere (`SiteSettings::get_defaults()`
  has none; `packs/LawFirm/config.php` = `{name, slug}` only).
- There is **no** client portal / account / appointment area to link to.

**Decision: implement no pack provider now.** This is the explicitly-valid "no
implementation" outcome (§48). The Phase-16 contract remains the ready mechanism.

## 2. Existing LawFirm Domain Sources

| Candidate | Exists? | Source | Owner | Global-safe? |
| --- | --- | --- | --- | --- |
| Consultation form | Yes | `Frontend/ConsultationForm.php`, rendered by a page section | Pack / page section | No |
| Consultation **URL** | **No** | posts to `admin-post.php`; redirects to `wp_get_referer()` + `#bb-consultation-form` | — | — |
| Booking/appointment form | Yes | `Appointments/BookingForm.php` + booking section | Pack / page section | No |
| Booking **URL** | **No** | same pattern (section-embedded) | — | — |
| Receipt / status / billing route | Yes | `Frontend/StatusPage.php`, `BillingPage.php`, `ReceiptRoute.php` | Pack | No — token/reference-gated, per-customer |
| Contact page | Yes (seed) | `Starter/StarterSite.php` seeds `home/about/services/lawyers/contact` | Pack seed (per-page) | No |
| Contact section | Yes | Core section (`contact`) | Page Builder / page-level | No |
| Header/CTA builder sections | Yes | `SectionRenderer::render_header_section()` (`cta_text`/`cta_url`) | Page Builder / page-level | No |
| Business phone/email/address/whatsapp/social | Yes | `bb_site_settings` | **Plugin** | Yes — already bridged |
| Per-lawyer contact/profile | Yes | `_bb_lawyer_*` meta | Pack, per-post | No |
| CTA/booking **setting** | **No** | — | — | — |
| Client portal / login | **No** | — | — | — |

**Pack activation (isolation evidence):** `ServiceProvider` registers the pack conditionally
(`PackManager::register('law_firm', LawFirmPack::class)`); the pack only loads when the site's
`bb_business_type` matches. So any future LawFirm provider is naturally gated — no new
mechanism needed.

## 3. Ownership Decisions

- WordPress owns identity (name/tagline/logo/URL/menus).
- **Plugin `SiteSettings` owns business contact/social** — unchanged; the Phase-16 bridge remains authoritative. LawFirm does **not** duplicate it.
- **Page Builder owns page-level CTA/contact** (incl. the consultation/booking forms and the `contact`/`header`/`cta` sections) — not promoted to the global shell.
- **No owner exists** for a global CTA or utility links. Therefore `header.cta = empty` and `utility_links = []` are the correct values.
- Per-lawyer data stays per-post; never promoted to global chrome.

## 4. Provider Architecture

None implemented. The architecture is **ready**: any pack can subscribe to the Theme's
existing filters (`bb_theme_shell_header_data`, `bb_theme_shell_footer_data`) and add
generic `cta`/`utility_links`/`contact`/`social` data, and every shell variant renders it
through the Phase-16 empty-safe helpers — **with zero Theme changes.**

## 5. Provider Location Decision

N/A (no provider). Should a source ever exist, the correct owner would be the **LawFirm pack**
(`packs/LawFirm/…`) for domain-only data, or the **plugin Theme layer** (`includes/Theme/…`)
for business-wide data — never the Theme.

## 6. Shell Data Contract

Unchanged from Phase 16 (header: identity + `utility_links`/`cta`/`contact`;
footer: identity + `contact`/`social`/`utility_links`). No key was added, renamed or removed.

## 7. CTA Source Decision

**No CTA implemented.** No authoritative source. Every candidate is page-level (section
content) or per-customer (token-gated) or does not exist. Promoting any of them would invent
a destination and violate page-level ownership (§6/§16). `header.cta` stays empty → no CTA markup.

## 8. Utility Links Decision

**No utility links implemented.** No client portal / account / booking area exists to link to.
`utility_links` stays `[]` → no utility markup.

## 9. Contact/Social Ownership

**Preserved.** `SiteSettings` owns business contact/social; the plugin bridge
(`includes/Theme/theme-shell-data.php`) remains the only reader of `bb_site_settings` and is
**non-destructive** (only fills an empty slot). LawFirm adds none of this.

## 10. Merge and Precedence Rules

- Data filters are applied in order; each contributor returns the array it received.
- The plugin bridge is **additive/non-destructive**: it fills `contact`/`social` only when empty, so a provider that supplies its own value wins.
- The Theme normalisers are **last-writer-wins per slot** (a provider's `cta` replaces an empty one), and every value is validated at render time.
- Empty from a provider means "add nothing", never "clear what another provider set" (the bridge explicitly checks `empty()` before filling).

## 11. Validation and Security

- Scheme allow-list: `http`/`https` only (utility links also allow a leading-slash
  site-relative path). `javascript:`, `data:`, `vbscript:`, protocol-relative and `..`
  traversal are **dropped**.
- `cta` requires both label and URL, else `null`.
- Target is only ever `_blank` (opted in) with `rel="noopener noreferrer"`; no arbitrary target.
- Labels are escaped at output (`esc_html`); a `<script>` label renders as `&lt;script&gt;`.
- No filesystem/path input; no arbitrary includes; no user-controlled callbacks; no remote requests.

## 12. Empty-State Behavior

Every optional slot is empty-safe in every variant: no CTA/utility/contact/social markup,
no empty wrapper, no blank spacing. Verified live (0 shell-content nodes on all routes) and
by rendering every header/footer variant with empty data.

## 13. Theme Integration

Unchanged. The Theme consumes only the generic contract. Verified: the Theme's `inc/` code
contains **no** `LawFirm` / `bb_lawyer` / `bb_consultation` / `bb_site_settings` reference
(comment-stripped source scan).

## 14. Header Variant Compatibility

`default`, `centered`, `split`, `minimal` all consume the same header data. Test: a stand-in
provider's CTA + utility links render through **all four** variants with no Theme change.

## 15. Footer Variant Compatibility

`default`, `columns`, `centered`, `minimal` all consume the same footer data. Test: a
stand-in provider's utility links render through **all four** variants.

> Incidental consistency fix: in Phase 16 the `columns`, `centered` and `minimal` footer
> variants rendered `contact`/`social` but not `utility_links`. Phase 17 added the missing
> `bb_theme_shell_render_links()` call to those three templates so **every** variant honours
> the full contract. This is empty-safe (verified: no markup when empty) and does not change
> default output.

## 16. Navigation Compatibility

Unchanged. `bb_theme_shell_nav_location()` and the nav templates are untouched; no mega menu,
builder, or navigation CPT.

## 17. Multisite Isolation

Verified: site-2 shell selection does not appear on site 1; site 1 (Astra) has no BB shell
and no fatal. The pack (and any future provider) loads only on sites whose `bb_business_type`
matches, so no cross-site contribution.

## 18. Responsive / RTL / LTR

No new presentation was added, so nothing changed visually. The Phase-16 shell-content CSS
(tested at 390/768/1024/1440 and under `dir="rtl"`) remains the only shell-content styling, and
the changed footer templates introduce no CSS or physical-direction properties.

## 19. Accessibility

No CTA was populated, so no new interactive element exists. The changed footer templates only
add an empty-safe helper call identical to the ones already in use (`<ul>/<li>` + real `<a>`
when populated).

## 20. Performance

No new queries. The provider was not implemented, so nothing runs. The bridge/pack loading is
unchanged; the pack already gates on business type.

## 21. Files Created

```
tests/runtime-phase17-shell-content-providers.php
docs/phase-17-shell-content-provider-audit.md
docs/phase-17-shell-content-provider-final-report.md
```

## 22. Files Modified

| File | Change | Why |
| --- | --- | --- |
| `themes/business-builder/template-parts/footer/footer-columns.php` | Added `bb_theme_shell_render_links(utility_links)` (brand col) + included links in the contact/social column condition | Contract consistency: every footer variant consumes `utility_links` |
| `themes/business-builder/template-parts/footer/footer-centered.php` | Added `bb_theme_shell_render_links(utility_links)` | same |
| `themes/business-builder/template-parts/footer/footer-minimal.php` | Added `bb_theme_shell_render_links(utility_links)` | same |

No plugin file, no Theme resolver/design-system/token file, no Page Builder file, no business
file was modified.

## 23. Files Untouched

Confirmed untouched: Theme design schema, Theme customization system, Theme token system,
Theme shell resolver (`shell-variants.php`), Page Builder (`PageBuilderAjax`, `PageManager`,
`SectionRegistry`, `SectionRenderer`, `page-admin.js`), LawFirm sections, LawFirm component
variants, payment systems, consultation processing, appointment processing,
`includes/Theme/theme-shell-data.php` (Phase-16 bridge), `SiteSettings`, and all business
systems.

## 24. Regression Tests

```
Phase 9  (theme):                                24/24 PASS
Phase 10 (components):                           31/31 PASS
Phase 11 (variants / schema):                    39/39, 12/12 PASS
Phase 12 (card variants):                        39/39 PASS
Phase 13 (catalog):                             127/127 PASS
Phase 14 (design system / admin):                42/42, 14/14 PASS
Phase 15 (shell variants):                       65/65 PASS
Phase 16 (theme content & shell data):           52/52 PASS
payment-completion:                              32/0 PASS
manual-review:                                   29/0 PASS
markpaid-sync:                                    6/0 PASS
activity & isolation:                            11/0 PASS
free-invoice:                                    13/0 PASS
receipt-page-free:                                5/0 PASS
```

(Phase 9 `render` / `rtl-preset` remain environment-limited — pre-existing, documented in
Phases 15/16.)

## 25. Phase 17 Tests

```
tests/runtime-phase17-shell-content-providers.php:   37/37 PASS
```

Covers:
- **No-provider baseline:** header/footer `cta`/`utility_links`/`contact`/`social` empty; no
  optional markup in header/footer.
- **Architecture readiness:** a stand-in external provider adds `cta` + `utility_links`
  through the **existing** filters; renders through **all 4 header** and **all 4 footer**
  variants; removing the provider restores the empty state.
- **Safety:** `javascript:`/`data:`/`vbscript:` rejected; label-only / url-only CTA rejected;
  `new_tab` → `_blank` + hardened `rel`; `<script>` label escaped; malformed payload → no
  markup, no fatal.
- **Merge/precedence:** provider CTA + plugin contact coexist; bridge does not overwrite a
  provider-supplied contact; identity keys remain WordPress-owned.
- **Invariants:** Theme `inc/` code has no LawFirm/business coupling; Phase-15 resolver and
  Phase-14 preset intact.
- **Multisite:** site-2 selection not visible on site 1.

## 26. Live Verification

Environment: WordPress Multisite; site 2 = `Law Firm Demo`
(`http://lawfirm.builder.test/`, theme `business-builder`, pack `law_firm` active);
site 1 = `datalynx` (`http://builder.test/`, theme `astra`).

| Check | Result |
| --- | --- |
| Routes `/ /home/ /services/ /lawyers/ /about/ /contact/` | all **HTTP 200**, no `bb-shell-cta`/`bb-shell-links`/`bb-shell-contact`, **no fatal** |
| Browser `/` | title "Law Firm Demo"; header class `bb-site-header`, footer `bb-site-footer`; `bb-shell-cta` absent; 0 `.bb-shell-links`; **0 console errors, 0 failed requests** |
| Header/footer variants rendered with a stand-in provider (PHP render) | provider CTA/links present in **all** variants; absent when the provider is removed |
| Footer variants with empty data | all **clean** (no utility markup), empty-safe |
| Site 1 (Astra) | 200, no BB shell, no fatal |
| Multisite isolation | site-2 selection not on site 1 |

Not tested: a real consultation/booking **URL** (none exists — by audit); live payment/gateway
(no credentials; out of scope). No browser *interaction* beyond DOM/eval assertions was performed.

## 27. Backward Compatibility

- No provider data ⇒ **Phase 16 behavior exactly** (verified: empty slots, no markup,
  identical default shell classes).
- All existing hooks, theme mods, presets, shell selections, section/component variants,
  Page Builder and business logic remain valid.
- The three footer-template additions are empty-safe and change no default output.

## 28. Known Limitations

- No global CTA / utility links can be shown until an **authoritative domain-level source**
  exists (a real booking URL setting, a genuine public consultation route, or a genuine
  client area). This is intentional.
- The audit's conclusion is specific to the **current** LawFirm pack; a future pack feature
  (e.g. a public booking page) would make a provider justified.
- The interactive admin/Customizer flow was not driven (no new Customizer controls were added).

## 29. Deferred Features

A LawFirm (or generic pack) shell-data provider; a business-level CTA/booking URL setting;
public consultation/booking routes; a client portal/account area; per-page/per-device shell
content; CTA management UI; mega menu / shell builders. All deferred until a real owner and
source exist.

## 30. Recommended Phase 18

**PHASE 18 — SHELL CONTENT SOURCE SETTINGS (BUSINESS-LEVEL CTA/BOOKING DESTINATION), IF AND
ONLY IF PRODUCT CONFIRMS ONE.** The boundary work is complete: the Theme presents, the
plugin owns business settings, packs own domain meaning, and the extension points are proven
ready. The single missing enabler for a real header CTA is an **authoritative business-level
destination** — e.g. a documented `SiteSettings` value like `booking_url` / `consultation_url`
owned by the plugin (a business-wide concern, not a LawFirm one) with strict sanitization,
bridged through the existing filters. That would let the currently-empty `header.cta` slot
populate from a legitimate owner **without inventing content**. Alternative: if the next pack
(Medical/RealEstate/…) ships a genuine public booking route, build the first real **pack
provider** then. Recommended: confirm the product decision on a business-level booking/CTA
destination first; implement the minimal, sanitized setting + bridge (no builder, no
management UI) only if confirmed.

---

### Notes this phase
1. **Primary outcome is a "no implementation" result** — the correct architecture is to leave
   the slots empty rather than invent a source. The audit and tests prove the infra is ready.
2. **Scope held:** 3 footer templates received one empty-safe helper call each (contract
   consistency); 1 test + 2 docs created. No plugin/builder/business file changed; no new
   storage, route, setting or Customizer control.
3. **Invariants preserved:** Theme domain-agnostic; SiteSettings ownership intact;
   no Theme→Pack dependency; no business feature added.
4. **Environment hygiene:** temp files removed; site 2 mods/settings restored; site 1
   re-verified on Astra.