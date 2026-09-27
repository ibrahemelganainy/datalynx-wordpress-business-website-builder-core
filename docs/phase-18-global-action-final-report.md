# PHASE 18 — BUSINESS ACTION / GLOBAL CTA OWNERSHIP — FINAL REPORT

**Phase 18 implementation: NONE.**

The audit found no authoritative, business-wide, business-type-agnostic global action in the
product, and no product feature that consumes one beyond a presentation slot that already
exists. Per the phase's own rules, the correct outcome is to change nothing.

---

## 1. Audit Results

Audit written before any change: `docs/phase-18-global-action-audit.md`.

- Every CTA-like destination in the product today is **page-level** (Page Builder
  `header`/`cta`/hero sections) or **per-entity** (LawFirm `legal_service` CTA meta) or
  **already global as contact/social** (Plugin `SiteSettings`).
- The Plugin's business-wide store (`bb_site_settings`) has **no** CTA/action field.
- A project-wide search for `primary_action`, `global_cta`, `cta_label`, `cta_url`,
  `action_url`, `call_to_action`, `business_action` as *fields* found only the per-page
  section content and per-service meta — **no global action concept exists**.
- The seed architecture deliberately puts a page-level `header` section at the top of **every**
  page (`Starter/StarterSite.php`), so the product's "site header / CTA" is a **repeated page
  section**, each page owning its own CTA text/URL.
- The plugin already ships a **`BusinessType` registry with 5 types** (Law Firm, Medical,
  Real Estate, Education, default), making generality a real question — and the answer is that
  the *label* is generic but the *destination/meaning* is domain- or page-specific in every case.
- The only generic, reusable thing is the **presentation slot** (`header.cta`), which Phase 16
  already provides and Phase 17 proved is ready.

## 2. Existing CTA / Action Inventory

| Concept | Location | Scope | Owner |
| --- | --- | --- | --- |
| `header` section CTA (`cta_text`/`cta_url`) | `SectionRenderer::render_header_section()` | Page-level section | Page Builder |
| `cta` section | `render_cta_section()` | Page-level section | Page Builder |
| Hero/slider CTA (`button_url`) | section renderers | Page-level section | Page Builder |
| Service CTA (`_bb_legal_service_cta_text/_url`) | `LegalServiceFields` | Per-entity | LawFirm Pack |
| Consultation form submit | `Frontend/ConsultationForm.php` | Page section (a form) | LawFirm Pack |
| Booking form submit | `Appointments/BookingForm.php` | Page section (a form) | LawFirm Pack |
| Footer "Quick Links" menu | WP nav menu (location `footer`) | Global menu | WordPress |
| Brand link | `home_url('/')` | Global | WordPress |
| Business phone/email | `bb_site_settings` | Global business-wide | Plugin |
| Social links | `bb_site_settings` → footer slot | Global business-wide | Plugin |

No `primary_action*` / `global_cta` / `*cta_url` **setting** exists.

## 3. Global vs Page-Level Analysis

Page-level: #1–#6 above (all real CTAs). Global: only WordPress identity/menu and Plugin
contact/social. **No CTA is global, and none was intended to be.** Promoting a page-level CTA
into the shell would pick an arbitrary page's content and break the established per-page
ownership.

## 4. Business-Type Generality

| Business Type | Natural action | Generic fit | Domain-specific? |
| --- | --- | --- | --- |
| Law Firm | Book a consultation | Label yes | Destination/meaning yes |
| Medical | Book an appointment | Label yes | Destination yes (scheduling) |
| Real Estate | Request a viewing | Label yes | Destination yes (per listing) |
| Education | Enroll / Contact | Label yes | Destination yes (per program) |
| Company | Request a quote | Label yes | Destination yes (quote form) |

The **label** generalizes; the **destination and meaning** do not. There is no generic
*business action*, only a generic *presentation slot*.

## 5. Potential Global Action Consumers

| Consumer | Exists? | Needs global action? | Decision |
| --- | --- | --- | --- |
| Theme header shell | Yes (`bb-header-actions`) | Slot ready; nothing authoritative to fill it | Keep slot, leave empty |
| Theme footer shell | Yes | No evidence | No |
| Navigation | Yes (WP menu) | No — nav is menu-owned | Do not modify |
| Mobile menu | Yes | No | No |
| Hero / page sections | Yes | Already satisfied per page | Keep page-level |
| Floating / sticky action | **No** | No such feature | Out of scope |

## 6. Action-Type Analysis

The existing shell `cta` = `label + url (+ new_tab → _blank, rel hardened)` is the smallest
abstraction that matches every real CTA. **No** action registry/handler/resolver is needed or
present. Nothing to build.

## 7. Ownership Decision

| Concept | WordPress | Plugin | Pack | Page Builder | Theme |
| --- | --- | --- | --- | --- | --- |
| Site identity | ✓ | | | | presentation |
| Business phone/email/address/whatsapp | | ✓ | | | presentation |
| Social links | | ✓ | | | presentation |
| Page CTA | | | | ✓ | presentation |
| Service-level CTA | | | ✓ | | presentation |
| Consultation/appointment (domain) | | | ✓ | (page form) | presentation |
| **Global business action** | — | — | — | — | presentation slot only (no instance) |

The Theme presents; it does not decide business meaning. The Plugin never interprets
"consultation". The Pack owns LawFirm meaning. The Page Builder owns page actions. **No layer
gains a global action, because no authoritative instance exists.**

## 8. Storage Decision

**No new storage.** `bb_site_settings` gained nothing; `bb_global_cta` / `bb_business_actions` /
`bb_action_settings` were not created. No CPT/taxonomy/table/option/theme-mod added.

## 9. Customizer Decision

**No Customizer change.** A CTA label/URL is business content, not presentation; the Theme
Customizer stays scoped to tokens/presets/shell layout/variants. Business content would belong
in Plugin business settings — and there is nothing to add there either.

## 10. Localization Decision

No label is being added, so nothing to localize. The existing pattern stands: owner-typed
content (like `business_name`) is user-entered; pack labels use pack translations; WordPress
admin language governs UI. **No duplicated Arabic/English fields.**

## 11. URL Ownership Decision

Nothing added. The existing shell `cta` normaliser already enforces the correct rules: `http`/
`https` only, label+url required, `new_tab` → `_blank` with `rel="noopener noreferrer"`. No URL
was invented and no route was created.

## 12. Multisite Decision

Nothing added → isolation unchanged. Any future action would be site-specific (theme mod /
`bb_site_settings`); **no network-level storage** is warranted. Verified: site-1 and site-2
remain isolated.

## 13. Product Decision

**OUTCOME A — NO GLOBAL ACTION REQUIRED.** The product has no authoritative business-wide
destination, and its real CTAs are intentionally page-level or per-entity or already covered by
contact/social.

## 14. Architecture Decision

Adopt no new abstraction. Keep the existing layered model:

```
Page Builder   → page-level CTA/contact/form
Pack (LawFirm) → domain meaning, service CTA, consultation/booking forms
Plugin         → business-wide contact/social → existing Phase-16 bridge
Theme          → presentation only; header.cta / utility_links slots, empty-safe
```

If a genuine business-wide destination is confirmed later, it flows
**owner → existing Phase-16 filters → `header.cta` → existing shell variants** with **zero Theme
changes** (proven ready by the Phase-17 suite).

## 15. Implementation Decision

**NONE.** No files created, modified or deleted. No setting, storage, provider, route, form or
UI. The `header.cta` slot remains empty and empty-safe.

**Future condition that would justify implementation:** a confirmed, authoritative
**business-wide** destination — either (a) a product decision to add a Plugin-owned business
setting (e.g. `booking_url`, business-wide/generic/sanitized/site-specific), or (b) a pack that
ships a genuine public booking/consultation route.

## 16. Files Created

```
docs/phase-18-global-action-audit.md
docs/phase-18-global-action-final-report.md
```

(No code, test, config or asset files were created. No Phase-18 runtime test file was created
because there is no new behaviour to test; the Phase-17 suite already covers the ready
mechanism and the empty-state invariants.)

## 17. Files Modified

**None.**

## 18. Files Untouched

Confirmed untouched: Theme design schema, Theme token system, Theme customization system,
Theme shell resolver, Theme shell templates, Page Builder (`SectionRegistry`, `SectionRenderer`,
`PageBuilderAjax`, `PageManager`, `page-admin.js`), LawFirm sections, LawFirm component
variants, payment systems, consultation processing, appointment processing, the Phase-16 shell
data bridge (`includes/Theme/theme-shell-data.php`), `SiteSettings`, `BusinessType`, and all
business systems.

## 19. Tests

No Phase-18 test file (no new behaviour). All previous suites were run and **pass**:

```
Phase 9  (theme):                                24/24 PASS
Phase 10 (components):                           31/31 PASS
Phase 11 (variants / schema):                    39/39, 12/12 PASS
Phase 12 (card variants):                         39/39 PASS
Phase 13 (catalog):                              127/127 PASS
Phase 14 (design system / admin):                 42/42, 14/14 PASS
Phase 15 (shell variants):                        65/65 PASS
Phase 16 (theme content & shell data):            52/52 PASS
Phase 17 (shell content providers):               37/37 PASS
payment-completion:                              32/0 PASS
manual-review:                                   29/0 PASS
markpaid-sync:                                    6/0 PASS
activity & isolation:                            11/0 PASS
free-invoice:                                    13/0 PASS
receipt-page-free:                                5/0 PASS
```

Additional targeted check: a project-wide scan for new CTA/action field identifiers
(`primary_action_label|url`, `global_cta`, `bb_global_cta`, `bb_business_actions`,
`bb_action_settings`, `call_to_action`) returns **0 code hits** — confirming no field was
introduced.

(Phase 9 `render` / `rtl-preset` remain environment-limited — pre-existing, documented in
Phases 15–17.)

## 20. Live Verification

Environment: Multisite. Site 2 = `Law Firm Demo` (`http://lawfirm.builder.test/`,
`business-builder`, `law_firm`); Site 1 = `datalynx` (`http://builder.test/`, `astra`).

| Check | Result |
| --- | --- |
| Routes `/ /home/ /services/ /lawyers/ /about/ /contact/` | all **HTTP 200**, **no** `bb-shell-cta`, **no** fatal |
| Browser `/` | title "Law Firm Demo"; header class `bb-site-header`; `bb-shell-cta` absent; **0 console errors, 0 failed requests** |
| Site 1 (Astra) | 200, no BB shell |
| Multisite isolation | intact |

Not tested / not applicable: no global-action destination exists to verify; live payment/gateway
out of scope. No browser interaction beyond DOM/eval assertions was performed.

## 21. Security

No new surface. No URL, setting, callback or route was added. The existing shell `cta` normaliser
remains the only CTA path and already rejects `javascript:`/`data:`/`vbscript:`, protocol-relative
and traversal URLs, and escapes labels (validated in the Phase-16/17 suites).

## 22. Performance

No change. Nothing new runs. No queries, scans or remote requests were introduced.

## 23. Accessibility

No change. No interactive element was added; the empty `header.cta` slot renders nothing.

## 24. RTL / LTR

No change. No CSS was touched. The existing shell content CSS remains RTL-safe (0 physical-
direction properties).

## 25. Responsive Verification

No change. No CSS/markup was added; the shell's existing responsive behaviour (verified at
390/768/1024/1440 in Phase 16) is unaffected.

## 26. Backward Compatibility

Fully preserved. Phase 9–17 behaviour is unchanged; the empty `header.cta` slot remains
empty-safe; no hook, mod, class, token or contract was altered.

## 27. Known Limitations

- No global header CTA is available on the live site — **by design**, because no authoritative
  business-wide destination exists.
- The conclusion is specific to the **current** product state; a future confirmed business-wide
  destination or a pack-shipped public booking route would change the justification.

## 28. Deferred Features

A Plugin-owned business-wide action/CTA setting (e.g. `booking_url`); a pack shell-data provider
that supplies a real domain CTA; public consultation/booking routes; a client portal/account
area; a CTA management UI; floating/sticky actions. All deferred pending a real owner and source.

## 29. Recommended Phase 19

**Recommendation: move to the next _business-feature_ phase, and treat a global CTA as a
product-gated follow-up rather than an architecture task.** The ownership boundary is now fully
settled across Phases 16–18:

- WordPress owns identity; the Plugin owns business-wide contact/social; packs own domain
  meaning; the Page Builder owns page actions; the Theme presents.
- The shell-data extension points are proven ready.

The only thing that could justify a global action is a **product decision** on an authoritative
business-wide destination. Two concrete candidates, in order of cleanliness:

1. **Business-level booking destination** (Plugin-owned): add a single sanitized
   `booking_url` (and optional label) to `SiteSettings`, bridge it to `header.cta` through the
   existing Phase-16 filters. No Theme change, no new abstraction.
2. **Pack provider** if a future pack ships a genuine public booking/consultation route.

If neither is confirmed, the correct next phase is the next **domain/business capability**
(e.g. a Medical or RealEstate pack, or a concrete LawFirm feature), since the Theme/Shell layer
is now complete and stable. Do not build a CTA management system speculatively.

---

### Notes this phase
1. **Primary outcome is a documentation-only, no-code result** — the evidence does not justify a
   global action, so nothing was built. The Phase-16/17 infrastructure remains ready.
2. **Scope held:** 2 docs created; **0** code/config/asset files created, modified or deleted.
3. **Invariants preserved:** Theme domain-agnostic; SiteSettings ownership intact; Page Builder
   ownership intact; no Theme→Pack dependency; no business feature added; no route/setting/storage
   invented.
4. **Environment hygiene:** no environment changes were made; all suites re-run green; live
   routes verified clean; site 1 re-verified on Astra.