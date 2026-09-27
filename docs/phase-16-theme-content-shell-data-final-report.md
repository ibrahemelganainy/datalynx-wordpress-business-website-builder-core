# PHASE 16 — THEME CONTENT & SHELL DATA SYSTEM — FINAL REPORT

Method: audit first (`docs/phase-16-theme-content-shell-data-audit.md`), implement the
minimum the audit justified. No subsystem was rebuilt. No business file was changed.

---

## 1. Audit Findings

- **WordPress already owns identity** (name, tagline, logo, home URL, menus) and the
  shell already consumes it. No change needed.
- **The plugin already owns business contact/social data.** `includes/Settings/SiteSettings.php`
  (option `bb_site_settings`) is the authoritative store for `phone`, `email`, `address`,
  `whatsapp`, `facebook/instagram/youtube/linkedin/twitter`, plus `show_*` visibility
  toggles — with a real per-field sanitizer. This is **business-owned data (classification C)**.
- **The Theme contained no reference to it.** Grep confirmed the theme has no
  `SiteSettings` / `bb_site_settings` / plugin-class usage — it was already domain-agnostic
  but consequently could not present business contact chrome at all.
- **No bridge existed** between `bb_site_settings` and the Theme's prepared shell data,
  even though the Theme already fires `bb_theme_shell_header_data` / `bb_theme_shell_footer_data`.
- **The established integration precedent is "Theme fires a filter, the plugin hooks it"**
  (`bb_theme_is_builder_page`, Phase 9). Phase 16 reuses it — it did not invent a new one.
- **No authoritative source exists for a Header CTA or utility links.** The plugin's
  `header`/`contact`/`cta_band` content lives in **per-page** builder sections
  (`_bb_page_sections`), i.e. page-level (classification D). `bb_site_settings` has no CTA.
- Per-lawyer contact data is **pack-owned** (post meta) and out of scope for global chrome.

## 2. Data Ownership Matrix

| Data | Current Owner | Desired Owner | Theme Reads? | Storage | Phase 16 action |
| --- | --- | --- | --- | --- | --- |
| Site Name / Tagline / Logo / Home URL / Menus | WordPress | WordPress | Yes (native) | WP option / nav | none |
| Business phone | Plugin `SiteSettings` | Plugin | via filter | `bb_site_settings` | **bridge** |
| Business email | Plugin `SiteSettings` | Plugin | via filter | `bb_site_settings` | **bridge** |
| Business address | Plugin `SiteSettings` | Plugin | via filter | `bb_site_settings` | **bridge** |
| Social links | Plugin `SiteSettings` | Plugin | via filter | `bb_site_settings` | **bridge** |
| `show_*` visibility | Plugin `SiteSettings` | Plugin | via filter | `bb_site_settings` | **bridge honours it** |
| Per-lawyer contact | LawFirm pack | LawFirm pack | no | post meta | out of scope |
| Header CTA | *none* | (future owner) | slot only | — | **deferred, slot exposed** |
| Utility links | *none* | (future owner) | slot only | — | **deferred, slot exposed** |
| Page CTA / contact | Builder sections | Builder sections | no | `_bb_page_sections` | untouched |

## 3. Architectural Decision

Keep the dependency direction **pack → plugin → theme**. The Theme learns only a
**generic** presentation contract (`contact`, `social`, `cta`, `utility_links`); the
**plugin** bridges its business settings down through the Theme's existing filters.
No business data was copied into the Theme; no CTA/social content was invented
(the CTA/utility slots are exposed but render nothing until a real owner fills them).

Concretely, the audit justified exactly three pieces of new infrastructure:
1. extend the Theme's prepared-data contract into named groups with **optional, empty-safe** slots,
2. add **shared presentation helpers** so all 11 shell variants consume one contract,
3. add **one plugin bridge** that maps `bb_site_settings` → those slots.

## 4. Implemented Data Contract

Theme-side (`inc/shell-data.php`), all keys backward-compatible (`site_name`, `tagline`,
`has_logo`, `home_url`, `has_menu`, `year` unchanged):

```php
bb_theme_shell_header_data() → [
  'site_name', 'tagline', 'home_url', 'has_logo',   // identity (WordPress)
  'utility_links' => [],                             // optional
  'cta'           => null,                           // optional
  'contact'       => null,                           // optional {phone,email}
]
bb_theme_shell_footer_data() → [
  'site_name', 'tagline', 'has_menu', 'year',         // identity (WordPress)
  'contact'       => null,                           // optional {phone,email,address}
  'social'        => [],                             // optional [ {label,url} ]
  'utility_links' => [],                             // optional [ {label,url} ]
]
```

Accepted payload shapes (documented in the source):
- `contact`  → associative `[ 'phone' => …, 'email' => …, 'address' => … ]` (any subset).
- `social`   → list of `[ 'label','url' ]` **or** map `[ 'facebook' => 'https://…' ]`.
- `utility_links` → list of `[ 'label','url' ]` **or** map `[ 'Label' => 'url' ]`.
- `cta`      → `[ 'label' => …, 'url' => …, 'new_tab' => bool ]`.

Filters may **add or replace** any key; the bridge is **non-destructive** (it only fills
a slot that is still empty).

## 5. Storage & Configuration

- **No new storage.** Business contact/social stays in the plugin's existing
  `bb_site_settings` option. Shell *structure* stays in the Phase-15 theme mods.
  Design tokens stay in the Phase-14 mods. No new tables, CPTs or taxonomies.
- **No new Customizer settings were added** — the audit found no Theme-owned shell
  content is justified (business data already has an owner; CTA/utility have none).

## 6. Sanitization & Escaping

- **Sanitization (owner side):** business values are sanitized by `SiteSettings`
  (`sanitize_text_field`, `sanitize_email`, `esc_url_raw`, whatsapp `/[^0-9+]/`,
  `show_*` bool). The bridge re-runs `esc_url_raw` on social URLs as defence in depth.
- **Escaping (presentation side, at output):** the Theme's helpers escape everything —
  text → `esc_html()`, URLs → `esc_url()`, attributes → `esc_attr()`,
  class names → `sanitize_html_class()`. No value is printed unescaped; there is no
  `*_html` key in the contract. No double-escaping (data is stored raw-but-sane).

## 7. Shell Integration

Flow: `prepared data → bb_theme_shell_render(part) → variant template → shared helper → escaped markup`.
Every shell variant (header + footer) consumes the **same** helpers; none re-implements
CTA/contact/social/utility markup. When a slot is absent/null/empty, the helper prints
**nothing** (no wrapper, no spacing) — so a site with no business data is byte-for-byte
the pre-Phase-16 shell (verified).

## 8. Header Integration

- All four header variants (`default`, `centered`, `split`, `minimal`) render, inside
  `.bb-header-actions`: the optional header **contact** (phone/email only), optional
  **utility links**, optional **CTA**, then the unchanged menu toggle.
- When empty, only the toggle appears — identical to before.
- The bridge supplies header contact from `show_phone`/`show_email`; the address is
  intentionally not placed in the header.

## 9. Navigation Integration

- Navigation is unchanged (no business content belongs in the nav). The nav templates
  still delegate to `bb_theme_navigation()`; `#bb-primary-nav` and `.bb-nav` hooks intact.

## 10. Footer Integration

- `default` & `centered`: contact + social + utility links in the brand column (empty-safe).
- `columns`: a dedicated contact/social column appears **only** when it has content.
- `minimal`: contact/social rendered inline in the compact bar.
- The `footer` menu and copyright are untouched.

## 11. Extension Points

| Hook | Type | Fired by | Args | Purpose |
| --- | --- | --- | --- | --- |
| `bb_theme_shell_header_data` | filter | Theme | `array $data` | Add/replace header slots |
| `bb_theme_shell_footer_data` | filter | Theme | `array $data` | Add/replace footer slots |
| `bb_theme_shell_nav_location` | filter | Theme | `string $loc` | Nav menu location (pre-existing) |
| `bb_theme_header_variant` / `bb_theme_footer_variant` | filter | Theme | `string $slug` | Legacy shell variant hooks (**preserved**) |
| `bb_theme_shell_variant` / `bb_theme_shell_variants` | filter | Theme | — | Phase-15 hooks (**preserved**) |

**Ownership:** the *Theme* owns and documents these filters. A pack/plugin/child theme
may add data to any slot. The Theme never queries a business source itself.

## 12. Backward Compatibility

- Default shell output with **no business data** is structurally identical to pre-Phase-16
  (whitespace-normalised compare: IDENTICAL). Only insignificant whitespace changed.
- All Phase 9–15 behaviour intact: header/nav/footer variants, unknown/empty fallbacks,
  Phase-14 preset/override cascade, Phase-15 shell selection, section/component variants.
- Existing theme mods remain valid; no hook renamed or removed; no class renamed.

## 13. Security

- URLs: social/links/CTA only accept `http(s)` (links also accept a leading-slash
  site-relative path); `javascript:`, `data:`, protocol-relative and `..` traversal are
  **dropped** (verified).
- Labels/HTML: a `<script>` label renders escaped (`&lt;script&gt;`) (verified).
- Phone: a `tel:` href is only emitted for a plausible number and collapses to digits/`+`.
- No filesystem operation is ever driven by a configurable value; shell template
  selection remains the Phase-15 whitelist resolver only.
- The bridge reads `bb_site_settings` (already sanitized) and adds nothing unsafe.

## 14. Performance

- No new queries. `get_option('bb_site_settings')` is WordPress-object-cached; the bridge
  reads it at most once per shell render and does no remote calls. The bridge registers on
  `after_setup_theme` (no admin cost). Shell helpers are pure functions over prepared data.

## 15. Accessibility

- Contact uses semantic `<ul>/<li>`; phone/email are real `<a href="tel:|mailto:">`.
- Social links are real `<a>` with **visible text labels** (e.g. “Facebook”), `rel="noopener noreferrer"`,
  `target="_blank"`; no clickable `<div>`s, no redundant ARIA.
- Landmarks, skip link, nav `aria-label`, toggle `aria-controls/expanded` all unchanged
  (re-verified live).

## 16. RTL / LTR

- `shell-content.css` uses **0** physical-direction properties; flex/grid + logical
  alignment only. Live `dir="rtl"` (split header + columns footer + contact/social):
  no overflow, 0 console errors. LTR verified throughout.

## 17. Responsive

Live with demo content at **390 / 768 / 1024 / 1440 px**: no page overflow and no
contact/social overflow at any width; utility links hide on the tightest widths;
footer optional blocks stack cleanly. No hardcoded dimensions introduced.

## 18. Multisite

- `bb_site_settings` and all theme mods are per-site. Verified: a site-2 business phone
  does **not** appear on site 1; site 1 (Astra) shows no BB shell and no fatal.
- Replacing the active business system requires **no** Theme change — the bridge is the
  only business-aware code and it lives in the plugin.

## 19. Files Created

```
includes/Theme/theme-shell-data.php                       (plugin bridge: bb_site_settings → shell slots)
themes/business-builder/assets/css/shell-content.css      (token-only styles for the optional slots)
tests/runtime-phase16-theme-content.php                   (Phase 16 suite)
docs/phase-16-theme-content-shell-data-audit.md
docs/phase-16-theme-content-shell-data-final-report.md
```

## 20. Files Modified

| File | Change | Why |
| --- | --- | --- |
| `themes/business-builder/inc/shell-data.php` | Added optional `contact`/`social`/`cta`/`utility_links` groups + shared empty-safe helpers + normalisers | The prepared-data contract + single markup source |
| `themes/business-builder/inc/enqueue.php` | Enqueue `shell-content.css` (one entry) | Load the optional-slot styles |
| `themes/business-builder/template-parts/header/{default,centered,split,minimal}.php` | Render optional contact/utility/CTA in `.bb-header-actions` | Consume the shared contract, empty-safe |
| `themes/business-builder/template-parts/footer/{default,columns,centered,minimal}.php` | Render optional contact/social/utility | Consume the shared contract, empty-safe |
| `business-builder-core.php` | `require_once` the new bridge file | Load the bridge (autoloader resolves classes only) |

## 21. Files Intentionally Untouched

- Phase 14 design system (`tokens.css`, `preset-resolver.php`, `design-schema.php`,
  `customization.php`) — unchanged.
- Phase 15 shell resolver (`shell-variants.php`, `bb_theme_shell_render`) — unchanged.
- Navigation templates, `theme.js`, `header.css`, `footer.css`, `responsive.css`.
- Page Builder (`SectionRegistry`, `SectionRenderer`, `PageBuilderAjax`, `PageManager`,
  `page-admin.js`), `SiteSettings` (read-only use), all LawFirm sections/components/variants.
- All business systems (consultation, appointment, payment, notification, activity,
  dashboard, receipts).

## 22. Automated Tests

```
PHP lint (theme + plugin new/changed files):        PASS (0 errors)
Phase 9 (theme):                                    24/24 PASS
Phase 10 (components):                              31/31 PASS
Phase 11 (variants / schema):                       39/39, 12/12 PASS
Phase 12 (card variants):                           39/39 PASS
Phase 13 (catalog):                                127/127 PASS
Phase 14 (design system / admin):                   42/42, 14/14 PASS
Phase 15 (shell variants):                          65/65 PASS
Phase 16 (theme content & shell data):              52/52 PASS
payment-completion:                                 32/0 PASS
manual-review:                                      29/0 PASS
markpaid-sync:                                       6/0 PASS
activity & isolation:                               11/0 PASS
free-invoice:                                       13/0 PASS
receipt-page-free:                                   5/0 PASS
CSS sanity (shell-content.css braces):              balanced PASS
CSS hardcode / !important (shell-content.css):      0 hex / 0 real !important PASS
RTL physical-property scan (shell-content.css):     0 PASS
Mojibake scan (new/changed files):                  none PASS
Default shell parity (empty data, pre vs post):     IDENTICAL (normalised) PASS
Live gateway / live money:                          NOT TESTED (no credentials; out of scope)
```

Phase 16 suite covers: all variants render; unknown/empty fallback; empty-safe rendering
(no markup when empty); the prepared-data contract; normaliser edge cases; valid-data
rendering (`tel:`/`mailto:`); security (unsafe URL schemes dropped, script label escaped,
phone href safe, traversal rejected); legacy hooks preserved; filter non-destructiveness;
domain-agnostic source scan; Phase 14/15 still working; multisite isolation.

## 23. Live Verification

Performed against `http://lawfirm.builder.test/` (site 2) and `http://builder.test/` (site 1):

| Scenario | Result |
| --- | --- |
| Homepage, business settings **empty** | 200, no contact/social markup, default header class, 0 console errors, 0 failed requests |
| Demo business settings set | contact + social render; `tel:+15551234567`, `mailto:hello@example.com` correct |
| Routes `/ /home/ /services/ /lawyers/ /about/` (demo data) | all 200, contact+social present, no fatal |
| Browser: social accessible labels | “Facebook”, “Instagram”; nav `aria-label` intact; no overflow; 0 console errors |
| Responsive 390/768/1024/1440 (split header, columns footer, demo data) | no page/contact/social overflow |
| RTL (`dir="rtl"`) with optional content | no overflow, 0 console errors |
| Site 1 (Astra) | 200, no BB shell, no fatal |
| Cleanup | demo settings cleared, shell mods reset, preset `default` — re-verified clean |

## 24. Known Limitations

- The **interactive** Customizer click-through was not driven (no admin-session harness);
  it was verified by API + live output + reset (as in Phases 14–15). No new Customizer
  controls were added in Phase 16.
- **Header CTA and utility links are unpopulated by default** — no authoritative source
  exists, so they render nothing until a pack/plugin (or a future, explicitly justified
  Theme setting) supplies data. This is deliberate, not a defect.
- The site’s real business settings are empty, so the *default live site* shows no
  optional shell content (correct empty behaviour); content rendering was verified with
  temporary demo values that were removed afterward.
- Two legacy Phase-9 suites (`render`, `rtl-preset`) remain environment-limited
  (pre-existing, documented in Phase 15).

## 25. Deferred Features

Header CTA management, social-media/contact/business-profile management platforms, CRM,
mega menu, header/footer builders, per-page or per-device shell content, shell
export/import, Theme marketplace/theme packs, custom CSS/HTML editors. Also deferred: a
Theme-owned copy of business contact data (explicitly avoided) and any invented content.

## 26. Recommended Phase 17

**PHASE 17 — SHELL CONTENT OWNER SLOTS / PACK SHELL-DATA PROVIDERS.** The contract is now
domain-agnostic and the bridge pattern is proven. The natural next step is to let the
**active business pack** own its own shell-data contributions through the same filters
(e.g. LawFirm providing a consultation CTA from its own domain data), and — only if a real
owner is identified — to formalise the currently-empty `header.cta` / `utility_links` slots
with a minimal, sanitized Theme setting. This keeps business meaning in the pack and
presentation in the Theme, requires no change to the resolver, and completes the long-term
diagram (WordPress/Theme-owned data + Pack-provided data → one prepared shell data set
→ shell variants).

---

### Notes this phase
1. **No business behaviour changed.** The bridge only reads (never writes) `bb_site_settings`;
   Page Builder, section/component variants, and all business systems are untouched.
2. **Scope held:** 1 plugin file created + 1 bootstrap line; 10 theme files touched
   (2 helpers/CSS + 8 variant templates); 2 docs + 1 test created. No token, hook, class or
   storage was renamed; no new storage introduced.
3. **Boundary preserved:** the Theme contains no business/pack coupling (verified by a
   source scan that strips comments); the plugin is the only reader of `bb_site_settings`.
4. **Environment hygiene:** demo settings removed, shell/preset mods reset, site 1
   re-verified on Astra, all temporary build/probe files deleted.