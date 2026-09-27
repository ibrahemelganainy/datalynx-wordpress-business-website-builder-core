# PHASE 20 — NETWORK ADMINISTRATION, SITE PROVISIONING & DOMAIN CONTROL — FINAL REPORT

**Verdict: the Network control plane is built and the ownership boundary is enforced —
including a real, pre-existing security defect that was found and fixed.**

**Phase 20 suite: 94/94 PASS.** UI render assertions: 30/30 PASS. All prior suites: green.

Environment (measured): WordPress Multisite, `SUBDOMAIN_INSTALL = true`, base
`builder.test`. Site 1 `datalynx` (Astra, no business type), Site 2 LawFirm (`law_firm`),
Site 3 Medical (`medical`).

---

## 1. Executive Summary

Phase 18 proved the platform was architecturally multi-business. Phase 19 proved it by adding
a second real pack with no Core or Theme edits. **Phase 20 builds the control plane on top of
that**, so a platform owner can actually run the product commercially — and it closes the one
real security hole that would have made the product unsellable.

### The defect that mattered

The audit found that **any site administrator could reclassify their own website**:

```php
// includes/Admin/SiteSettingsPage.php — BEFORE Phase 20
if ( ! current_user_can( 'manage_options' ) ) { wp_die( ... ); }   // ← site capability
...
if ( isset( $_POST['business_type'] ) ) {                          // ← no network check
    $this->plugin->get_business_type()->set_current( sanitize_key( ... ) );
}
```

A customer could POST `business_type=medical` (or `ecommerce`, or anything in the registry)
and **change what their website fundamentally is** — its pack, its sections, its data models.
Hiding the dropdown would not have fixed it; the request can be crafted directly. This is now
enforced **server-side**, and a deliberate negative test proves it fails.

### What was built

| Capability | Implementation | Owner |
| --- | --- | --- |
| Network admin menu + screens | `NetworkProvisioning` (`network_admin_menu`) | Network |
| Site provisioning (real WP sites) | `SiteProvisioner` + `wpmu_create_blog()` | Network |
| Business Type authority | `BusinessTypeGuard` | **Network** |
| Custom domain registry | `DomainRegistry` (network-scoped) | Network |
| Customer domain connection | `SiteDomainPanel` (site admin, own site only) | Site (request) |
| Site settings (content) | `SiteSettings` — unchanged | Site |

### Reused, not replaced

`BusinessType` · `PackManager` · `bb_register_packs` · theme preset registry ·
`bb_theme_preset` theme mod · `wpmu_create_blog()` · `wp_blogs` · `get_sites()` ·
`domain_exists()` · the existing admin-pattern (`admin_post_*` + nonce + capability).

**No second business-type registry. No second pack registry. No second design system. No
second theme. No CRM.**

---

## 2. Pre-Implementation Audit

Full audit: `docs/phase-20-network-provisioning-audit.md` (written before any code).

It inspected and measured, from the real repository and the live environment:

`SiteSettingsPage` (both the render and the save handler) · `BusinessType` · `PackManager` ·
`ServiceProvider` · the `bb_theme_presets` registry in both network and pack context ·
`SiteSettings` · `Plugin` wiring · `wp_blogs` columns · multisite constants ·
`wpmu_create_blog()` / `domain_exists()` / `get_site_by_path()` availability ·
`network_admin_menu` usage (zero).

Two findings:

1. **Class C defect (§2):** the Business Type write was authorised by `manage_options`, which
   every site admin holds. The audit enumerated **every** path that can write
   `bb_business_type` and confirmed there was **exactly one** production write path — the
   defect — with no REST, AJAX or other form involved. That made the fix surgical.
2. **No domain infrastructure of any kind.** WordPress has no custom-domain concept, no
   lifecycle state and no DNS verification. `bb_network_domains` / `bb_domain_registry` did not
   exist. This justified a small network-scoped registry and *ruled out* pretending that
   writing `wp_blogs.domain` was a mapping.

**No architectural conflict. No stop condition triggered.**

---

## 3. Network Architecture

```
NETWORK ADMIN  (network_admin_menu · manage_network / manage_sites)
    │
    ├── Business Sites            list: Site · Business Type · Pack · Platform URL ·
    │                                   Custom Domain · Design · Status
    ├── Add New Site              provisioning form
    └── Custom Domains            approve / reject / reset / remove
             │
             ▼
        CUSTOMER SITE  (site admin · manage_options)
             │
             ├── Business Builder → Site Settings   business type is READ-ONLY
             └── Business Builder → Custom Domain   request / remove OWN domain only
```

`NetworkProvisioning::register()` returns immediately when `! is_multisite()`, so the plugin
remains safe on a single site. Every network screen calls `require_network_authority()` and
`wp_die(..., 403)` on failure.

---

## 4. Site Provisioning

The exact flow (`SiteProvisioner::provision()`), in the audited order:

```
1.  authorise            current_user_can_provision()  (network admin only)
2.  validate EVERYTHING  title · address · business type · pack · preset · custom domain
                         → nothing is created if any check fails
3.  resolve the pack     PackManager::exists()/get()   (registry, never branched)
4.  resolve the address  normalize_address() + check_address() (reserved / taken)
5.  create the site      wpmu_create_blog( $domain, '/', $title, $admin, … )
                         → a REAL WP_Site, or a WP_Error that is reported
6.  switch_to_blog( $new_id )        ← everything below is site-local
7.  theme                switch_theme( 'business-builder' ) if required
8.  business type        BusinessType::set_current( $type )   (registry-validated)
9.  design               set_theme_mod( 'bb_theme_preset', … ) (existing mod)
10. initialisation       blog_public, permalink_structure
11. finally { restore_current_blog() }        ← always, including on failure
12. custom domain        recorded in the NETWORK registry (wp_blogs is NOT touched)
13. record outcome       complete | needs_attention
```

Steps 6–11 run inside a single `try { … } finally { restore_current_blog(); }`, and the only
blog id used is the one `wpmu_create_blog()` returned server-side. Never a posted value.

**Failure policy (§22):** validation up front removes the realistic failure causes. If a
post-creation step still fails, the site is **not** silently continued and **not**
auto-deleted — the warning is recorded and the site appears in the list as `needs attention`
so the operator can finish or remove it deliberately.

**What provisioning deliberately does NOT do (§25/§26):** it creates no pages, no sections, no
doctors, no lawyers. The Builder and the pack own that; the network owns the platphorm site.

---

## 5. Business Type Ownership

### Why the site admin cannot change it

A business type determines **what the website is** — which pack boots, which capabilities
exist, which data models are available. That is a platform decision, not site content. A
customer who could switch `medical → ecommerce` would be converting a purchased product into
a different product, with no migration.

### How it is enforced (not hidden)

```php
// includes/Network/BusinessTypeGuard.php
public static function required_capability(): string { return 'manage_network'; }

public static function current_user_can_assign(): bool {
    if ( is_multisite() && is_super_admin() ) { return true; }
    return current_user_can( self::required_capability() );
}

public static function assign( BusinessType $bt, string $slug, int $blog_id = 0 ): bool {
    if ( ! self::current_user_can_assign_for_site( $blog_id ) ) { return false; }  // ← server-side
    if ( '' !== $slug && ! $bt->exists( $slug ) ) { return false; }                 // ← registry
    … switch_to_blog … set_current … restore_current_blog …
}
```

`SiteSettingsPage::save_settings()` now compares the posted value against the stored one and
**only** writes when the requester holds network authority:

```php
if ( '' !== $requested_type && $requested_type !== $current_type ) {
    if ( BusinessTypeGuard::current_user_can_assign_for_site( get_current_blog_id() ) ) {
        BusinessTypeGuard::assign( ... );
    } else {
        $type_refused = true;   // refused — but the site-owned fields still save
    }
}
```

The settings screen renders the business type as **read-only information** and contains **no**
`<select name="business_type">`. Crucially, the authorization does not depend on that: even a
hand-crafted POST is refused, and the test proves it by calling the write path **directly**.

**The storage contract is untouched:** `bb_business_type` remains the single, site-local
source of truth. Only the *write* gained an owner.

---

## 6. Pack Resolution

The Network never branches on a business type:

```php
public static function resolve_pack( PackManager $pm, string $business_type ): array {
    $slug  = sanitize_key( $business_type );
    $class = $pm->get( $slug );
    return array( 'slug' => $slug, 'class' => (string) $class, 'available' => $pm->exists( $slug ) );
}
```

A verified scan of every file in `includes/Network/` for `'law_firm'`, `'medical'`,
`LawFirmPack`, `MedicalPack`, `'doctors'`, `'lawyers'` (comments stripped) returns
**0 hits** — asserted as `network code contains NO hardcoded pack/business branches`.

A future pack appears in the provisioning dropdown the moment it self-registers via
`bb_register_packs`, with **no Network UI change**. The UI even labels a business type whose
pack is not installed as *"pack not installed"*, and provisioning refuses it
(`errors['pack'] = 'missing'`).

---

## 7. Design Assignment

The initial design uses **the existing Theme preset system** — nothing new:

```
Network Admin → Add New Site → Initial Design (discovered from bb_theme_presets())
        ↓
SiteProvisioner::validate()   is_valid_preset()  ← rejected if unregistered
        ↓
inside switch_to_blog():  set_theme_mod( 'bb_theme_preset', $preset )
        ↓
the Theme's existing resolver renders it
```

The preset list is **discovered**, never hardcoded. Measured in a real admin context the
registry returns `default`, `modern`, `luxury` (plus `medical-modern` when the Medical pack is
booted). The theme mod `bb_theme_preset` was confirmed assigned to the new site.

**No second design field was created.** An assertion scans the provisioner for
`network_design`, `site_design`, `business_design` → **0 hits**.

---

## 8. Custom Domain Architecture

### Why `wp_blogs.domain` is not a custom-domain store

`wp_blogs.domain` **is** the site's routing identity. Writing a customer's purchased domain
there would move the site and is exactly the "fake mapping by changing an option" the phase
forbids. WordPress offers no custom-domain concept, no lifecycle state and no DNS verification.

### The model built

```
CUSTOMER SITE                            NETWORK
    │                                       │
    │  Business Builder → Custom Domain     │
    │  ────────────────────────────────     │
    │  "exampleclinic.com"                  │
    │            │                          │
    │            ▼                          │
    │    DomainRegistry::request()          │
    │    · normalize                        │
    │    · validate (reserved / platform)   │
    │    · uniqueness check                 │
    │    · blog id = get_current_blog_id()  │   ← server-resolved, never posted
    │            │                          │
    │            └──────►  status: pending  │
    │                                       │
    │                          Custom Domains screen
    │                          · Approve → active
    │                          · Reject  → rejected
    │                          · Remove
    │                          (manage_network only)
```

Stored **network-scoped**, unique by normalized domain, associated with an explicit `blog_id`,
auditable (`requested_by`, `requested_at`, `updated_at`), and **never** in `bb_site_settings`.

**States are limited to what is genuinely knowable here:**

| State | Meaning |
| --- | --- |
| `pending` | Customer requested it; the network has not approved |
| `active` | Network approved it |
| `rejected` | Network declined it |

There is deliberately **no `verified` state**: nothing in this repository or environment can
verify DNS, and claiming verification because a string was stored would be a lie.

Normalization handles scheme, credentials, port, path, query, case, `www.`, trailing dot and
invalid markup; reserved protection covers the base domain, platform hosts from `wp_blogs`,
and system labels (`www`, `admin`, `network`, `mail`, `ns1`, `dns`, `cdn`, `api`, `staging`, …).

---

## 9. Security

| Action | Capability required | Verified |
| --- | --- | --- |
| Reach any network screen | `manage_network` (+ `is_super_admin()`) | ✅ |
| Provision a site | `manage_sites` **and** `manage_network` | ✅ |
| Change a Business Type | `manage_network`, per target site | ✅ |
| Approve / reject / remove a domain | `manage_network` | ✅ |
| Request / remove **own** domain | `manage_options` **on that site only** | ✅ |

Every mutation: **capability check → nonce check → server-side ownership check → mutate.**

- No network action relies on `edit_posts` or any site capability.
- No handler trusts a posted `blog_id`: `handle_assign_type()` authorises the posted id
  server-side, and the site panel derives its target from `get_current_blog_id()`.
- Nonces: `check_admin_referer()` on every `admin_post_*` handler.
- URL input is normalized and validated against a charset, reserved list and uniqueness before
  it is stored.

**The decisive test (§40):** a fixture user with `manage_options` on site 2 calls
`BusinessTypeGuard::assign( $bt, 'medical', 2 )` **directly**. Result: `false`, and site 2
remains `law_firm`. Hiding the control is not the mechanism; authorization is.

**The decisive domain test (§41):** site 2 requests `phase20owned.example`; a different site
attempting the same domain is rejected with `duplicate`; a site calling
`remove_for_blog()` for another site is refused.

---

## 10. Multisite Isolation

Phase 19 produced a real contamination defect by writing outside `switch_to_blog()`. Phase 20
treats that as a hard rule:

- every site-local write is inside `switch_to_blog( $target )`;
- `switch_theme()`, `set_theme_mod()`, `update_option()` run only there;
- `restore_current_blog()` runs in a `finally`, so a failure cannot leave the context switched;
- the target id always comes from `wpmu_create_blog()`'s server-side return.

Verified: provisioning a new site left **site 1, 2 and 3 completely unchanged** (business
type, theme, title all identical before/after), and the new site inherited **no** LawFirm or
Medical data.

---

## 11. Files Created

```
includes/Network/BusinessTypeGuard.php              server-side Business Type authority
includes/Network/DomainRegistry.php                 network-scoped custom-domain registry
includes/Network/SiteProvisioner.php                validated real-site provisioning
includes/Network/NetworkProvisioning.php            network admin menu + 3 screens + handlers
includes/Network/SiteDomainPanel.php                site-admin custom-domain panel
tests/runtime-phase20-network-provisioning.php      94 assertions
docs/phase-20-network-provisioning-audit.md         pre-implementation audit
docs/phase-20-network-provisioning-final-report.md  this file
```

## 12. Files Modified

```
includes/Admin/SiteSettingsPage.php   business type control → read-only;
                                      write path guarded server-side; refusal notice added
includes/Core/Plugin.php              construct + register the two network services
includes/Core/Autoloader.php          map the Network\ namespace to includes/Network/
```

**Three modified files, all surgical.** No other file was touched.

## 13. Files Untouched

```
themes/business-builder/**                  (ENTIRE theme — zero edits)
includes/Builder/SectionRegistry.php
includes/Builder/SectionRenderer.php
includes/Builder/SectionVariants.php
includes/Builder/section-variants.php
includes/Builder/CoreSections.php
includes/Builder/PageManager.php
includes/Core/ServiceProvider.php
includes/Core/PackManager.php
includes/Settings/BusinessType.php          (storage contract preserved)
includes/Settings/SiteSettings.php
packs/LawFirm/**   packs/Medical/**         (both packs untouched)
assets/**  includes/REST/**  includes/Core/Payments/**
```

No visual appearance changed. No CSS was written. No section, layout or component logic moved.

---

## 14. Tests

Command:

```
C:\MAMP\bin\php\php8.2.14\php.exe tests\runtime-phase20-network-provisioning.php
```

Result — **94 PASS / 0 FAIL**:

| Section | Assertions | Result |
| --- | --- | --- |
| 1. Network vs site authority (capability distinction) | 11 | all PASS |
| 2. Business Type protection incl. the direct negative test | 14 | all PASS |
| 3. Pack resolution + no business-branch scan | 4 | all PASS |
| 4. Design assignment + no second design store | 6 | all PASS |
| 5. Custom domain (normalize, reserved, uniqueness, ownership) | 24 | all PASS |
| 6. Site provisioning (real site, registry-driven, isolation) | 27 | all PASS |
| 7. Network surface (menu, capabilities, nonces, scoping) | 8 | all PASS |

A separate UI render pass (30/30 PASS, run during development and then folded into the
permanent checks) proved the screens actually render: the provisioning form, the site list
with Business Type / Pack / Platform URL / Custom Domain / Design columns, the domains
screen, the customer panel (which exposes no other site), and the site settings screen with a
**read-only** business type and intact site-owned fields.

## 15. Regression

All existing suites re-run after Phase 20:

```
runtime-phase19-medical-pack.php                  76 PASS / 0 FAIL
runtime-phase18-platform-architecture.php         42 PASS / 0 FAIL
runtime-phase17-shell-content-providers.php       37 PASS / 0 FAIL
runtime-phase16-theme-content.php                 52 PASS / 0 FAIL
runtime-phase15-theme-shell.php                   65 PASS / 0 FAIL
runtime-phase14-theme-design-system.php           42 PASS / 0 FAIL
runtime-phase14-admin.php                         14 PASS / 0 FAIL
runtime-phase13-card-catalog.php                 127 PASS / 0 FAIL
runtime-phase12-card-variants.php                 39 PASS / 0 FAIL
runtime-phase11-variants.php                      39 PASS / 0 FAIL
runtime-phase11-schema.php                        12 PASS / 0 FAIL
runtime-phase10-components.php                    31 PASS / 0 FAIL
runtime-phase9-theme.php                          24 PASS / 0 FAIL

runtime-lawyer-management.php                     26 PASS / 0 FAIL
runtime-practice-area.php / -fix.php              PASS / 0 FAIL
runtime-activity-and-isolation.php                11 PASS / 0 FAIL
runtime-payment-completion.php                    32 PASS / 0 FAIL
runtime-manual-review.php                         29 PASS / 0 FAIL
runtime-consultation-markpaid-sync.php             6 PASS / 0 FAIL
runtime-free-invoice.php                          13 PASS / 0 FAIL
runtime-receipt-page-free.php                      5 PASS / 0 FAIL
```

**Pre-existing and unchanged:** `runtime-phase9-render.php` and `runtime-phase9-rtl-preset.php`
still report their known environment-limited failures — proven pre-existing in Phase 19 by
running them against a stashed baseline. Phase 20 does not touch that path. Not reported as
passing.

---

## 16. Live Verification

| Check | Result |
| --- | --- |
| `http://builder.test/` (site 1) | **200**, "datalynx", Astra, **0** BB sections, **0** doctors/lawyers, **0 console errors, 0 failed requests** |
| `http://lawfirm.builder.test/` (site 2) | **200**, "Law Firm Demo", preset `bb-theme-preset-default`, **2 lawyer cards, 0 doctors**, **0 console errors, 0 failed requests** |
| `http://medical.builder.test/` (site 3) | **200**, "Medical Demo", preset `bb-theme-preset-medical-modern`, **3 doctor cards, 0 lawyers**, **0 console errors, 0 failed requests** |
| Network screens | rendered in an admin request: provisioning form, site list (all 3 sites + resolved packs + `medical-modern`), domains screen |
| Site admin screens | read-only business type; site-owned fields (business name, phone, email) still editable; Custom Domain panel shows only the current site |

No site's business type, theme, title, design or business data changed as a result of Phase 20.

---

## 17. Problems Found

1. **Class C security defect (pre-existing, fixed):** any site administrator could change
   their site's Business Type via `SiteSettingsPage::save_settings()`, guarded only by
   `manage_options`. Reproduced by inspection of the single production write path; fixed with
   `BusinessTypeGuard` and proven fixed by a negative test that calls the write path directly.
2. **No domain infrastructure existed at all.** Resolved by a small network-scoped registry,
   with the honest limitation that DNS/SSL/aliases remain external.
3. **Bugs in my own Phase 20 code, caught before completion** (worth recording because they
   show the tests were doing work):
   - `Network\NetworkProvisioning` inside `namespace BusinessBuilderCore\Core` resolved to the
     wrong namespace (needed a leading `\`), and the `Network\` prefix was missing from the
     autoloader.
   - `$plugin->get_pack_manager()` does not exist — corrected to
     `get_service_provider()->get_pack_manager()`.
   - A `#` regex delimiter collided with a literal `#` inside a character class
     (`[:/?#]`), silently breaking domain normalization; switched to `~`.
   - `update_option()` returns `false` when the value is **unchanged**, so re-assigning a site
     the type it already had was reported as a failure; corrected.
   - `normalize_address()` + `is_reserved()` over-restricted **every** subdomain of the base
     domain, which would have blocked all legitimate site creation; split into
     `reserved_labels()` (protected) vs. ordinary tenant addresses (allowed).
   - `upgrade_network_option()` does not exist; replaced with `update_site_option()`, moved
     outside the `switch_to_blog` scope.

No architectural conflict was found in the product. Nothing required a new abstraction layer.

---

## 18. Known Limitations

- **Custom-domain DNS is external.** The registry records ownership, state and the
  Network's approval — nothing here verifies DNS, creates server aliases or issues SSL. A
  custom domain becomes truly *resolvable* only when the hosting layer (not this repository)
  maps it to the network. Reported honestly rather than faked.
- **One custom domain per site** in this phase.
- **Subdomain network only.** Site addresses are `<label>.<base>` (measured
  `SUBDOMAIN_INSTALL = true`); subdirectory provisioning is not implemented.
- **Pack-contributed presets and the provisioning picker.** The Theme's preset filter fires
  for the pack booted in the *current* context, so a Medical-only preset appears in the
  provisioning dropdown when that context is available. Assignment is still validated against
  the registry, so an unregistered value can never be written.
- **Business Type conversion** (e.g. `medical → law_firm` for an existing, populated site) is
  intentionally absent — it needs migration rules and is out of scope.
- The site list shows the resolved pack **slug**; a friendlier label would come from pack
  metadata.

## 19. Deferred Features

Billing / subscriptions / invoices · customer CRM · hosting provisioning · DNS provider and
registrar automation · SSL automation · email provisioning · analytics · customer portal ·
marketplace · ecommerce · medical workflows · **Business Type migration** · site cloning ·
backups · staging · a full design catalogue per business type · domain verification via DNS.

## 20. Recommended Next Phase

**Phase 21 — BUSINESS TYPE CONVERSION & DESIGN CATALOGUE (product-gated)**, or a third pack.

Evidence-based reasoning:

- The architecture is now complete: Core generic, Theme generic, packs independent, Network
  authoritative, isolation proven. There is no remaining architectural gap to close.
- Two product surfaces remain intentionally undefined and each needs a **product** decision,
  not another abstraction:
  1. **A design catalogue** — the requirement is ~5 styles per business type; the mechanism
     (presets on the one `--bb-*` system) is proven, so this is curation, not architecture.
  2. **Business Type conversion** — moving an existing populated site between types. This is
     the only remaining operation that *could* tempt a redesign, and it must be specified as a
     Network-level migration with explicit data rules before any code exists.
- If the product would rather prove breadth first, **a third pack** (RealEstate or Company) is
  now a pure pack-only addition — Phase 19's method, repeated, with zero Core, Theme or
  Network changes.

**Do not** build billing, a CRM, DNS automation or a second design system next.

---

### Closing statement

> A platform administrator can create a real customer website from the Network layer, assign
> its Business Type, resolve its Pack, assign its initial Design, retain control of the site's
> platform identity and domain infrastructure — while the customer manages their own content,
> customization and custom-domain connection **without being able to change the architectural
> type of the purchased website.**

This is now demonstrably true:

```
                     NETWORK ADMIN
                          │
         ┌────────────────┼─────────────────┐
         │                │                 │
   SITE PROVISIONING   BUSINESS TYPE      DOMAIN
         │                │                 │
         │                ▼                 │
         │              PACK                │
         │                │                 │
         │                └────────┐        │
         ▼                         ▼        ▼
   CUSTOMER SITE            PACK SECTIONS  DOMAIN MAP
         │                  (pack-owned)   (network-owned)
         ├── Site Admin
         ├── Content / Builder / Layouts / Card Variants
         ├── Theme Customization
         └── Custom Domain (own site only)
                          │
                     CORE THEME
                          │
                 ONE DESIGN SYSTEM
                          │
         ┌────────────────┴────────────────┐
  PREDEFINED STYLES              USER CUSTOMIZATION
         └────────────────┬────────────────┘
                     FINAL WEBSITE
```

**The Network controls *what the site is*. The customer controls *what it contains* — and
cannot change the former.** One Theme, one design system, one `--bb-*` namespace, many sites.