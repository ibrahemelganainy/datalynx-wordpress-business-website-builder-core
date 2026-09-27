# PHASE 20 — NETWORK ADMINISTRATION, SITE PROVISIONING & DOMAIN CONTROL — AUDIT

Read-only audit performed **before** any Phase 20 code. Every claim is quoted from the actual
repository or measured from the live Multisite environment.

Environment (measured, not assumed — `tests/_phase20-audit-env.php`):

```
is_multisite            = true
MULTISITE               = true
SUBDOMAIN_INSTALL       = true          ← subdomain network
DOMAIN_CURRENT_SITE     = builder.test
PATH_CURRENT_SITE       = /
SITE_ID_CURRENT_SITE    = 1
BLOG_ID_CURRENT_SITE    = 1

blog 1  datalynx       builder.test            theme=astra             type=(none)
blog 2  Law Firm Demo  lawfirm.builder.test    theme=business-builder  type=law_firm
blog 3  Medical Demo   medical.builder.test    theme=business-builder  type=medical
```

---

## 0. Audit verdict

| Question | Answer |
| --- | --- |
| Can a **site admin** change Business Type today? | **YES — this is a real defect.** §2 |
| Does a Network Admin UI exist? | **NO** — zero `network_admin_menu` hooks |
| Is there any domain-mapping infrastructure? | **NO** — no registry, no option, no table |
| Can WordPress create real sites here? | **YES** — subdomain network, `wpmu_create_blog()` available |
| Do the extension points Phases 18–19 proved suffice? | **YES** — all six are reusable network-side |
| Any architectural conflict? | **None.** Build the control plane on the proven architecture |

**Proceed with implementation.** No stop condition from §49 is triggered.

---

## 1. Existing admin architecture (reused, not replaced)

`includes/Admin/SiteSettingsPage.php`:

- `register()` hooks `admin_menu` → `register_menu()` and
  `admin_post_bb_save_site_settings` → `save_settings()`.
- `register_menu()` creates **one top-level site menu** (`add_menu_page`, slug
  `business-builder`, capability `manage_options`, icon `dashicons-admin-customizer`) plus a
  `business-builder-settings` submenu.
- `render_page()` and the form are all gated by `manage_options`.

`includes/Core/Plugin.php:51` constructs the page; `ServiceProvider` wires the rest.

**Consequence:** the existing admin pattern (menu + `admin_post_*` handler + `check_admin_referer`
+ `current_user_can`) is the convention to follow for the Network screens. The plugin currently
registers **no network admin screen at all**
(`grep network_admin_menu` → 0 results).

---

## 2. THE DEFECT — Business Type is writable by any site admin

### 2.1 Every path that can write `bb_business_type`

A project-wide search for `bb_business_type|set_current|get_option_name` found **41 hits**;
filtering to production writes:

| Path | Verdict |
| --- | --- |
| `SiteSettingsPage::save_settings()` (line 518) | **WRITABLE — the defect** |
| `BusinessType::set_current()` (line 167) | the single setter; validates the slug |
| `business-builder-core.php` bootstrap | read-only declaration |
| tests (`_phase19-provision`, `runtime-phase18/19`) | test-only |
| No REST route, no AJAX handler, no other form | clean |

**There is exactly one production write path, and it is the defect.**

### 2.2 The defect itself

`includes/Admin/SiteSettingsPage.php:432-527`:

```php
public function save_settings(): void {

    if ( ! current_user_can( 'manage_options' ) ) {   // ← SITE capability
        wp_die( ... );
    }

    check_admin_referer( 'bb_save_site_settings' );

    // ... sanitize business_name, tagline, phone, email, ... (site-owned values)

    /*
     * Business Type is stored separately because
     * it controls which Business Pack is loaded.
     */
    if ( isset( $_POST['business_type'] ) ) {         // ← NO network authorization

        $this->plugin
            ->get_business_type()
            ->set_current(
                sanitize_key( wp_unslash( $_POST['business_type'] ) )
            );
    }

    wp_safe_redirect( ... );
    exit;
}
```

and the form (`render_page()`, line 117) renders an editable `<select name="business_type">`
with every registered business type.

**Measured fact:** the `administrator` role **has `manage_options`**
(`_phase20-audit-env.php` → `has manage_options: true`), and a multisite site administrator
holds that role on their own site.

**Therefore:** a customer with site-admin access can POST `business_type=medical` to
`admin-post.php` with a valid nonce for their own site and **silently reclassify the entire
website** — the pack, its sections, its data models. Hiding the dropdown is not a fix (the
request can be crafted directly). This violates Phase 20 §2, §3, §16, §24 and the final
architectural rule *"Site Admin cannot change Business Type"*.

**The required correction** (§3's preferred result):

```
NETWORK ADMIN  → may assign/change Business Type
SITE ADMIN     → may SEE it (read-only) ; cannot change it
```

Enforced **server-side**, not by hiding the control.

---

## 3. Business Type storage contract (preserved)

`includes/Settings/BusinessType.php`:

- `private const OPTION_NAME = 'bb_business_type'` — a **site-local** option, per site.
- `get_current()` reads it; returns `null` when empty or unrecognised.
- `set_current( $slug )` **rejects** any slug failing `exists()` — validation already exists;
  only the *authorization* is missing.
- `get_all()`, `get( $slug )`, `exists( $slug )` — the authoritative registry.
- Declares `law_firm`, `medical`, `real_estate`, `education` (each with a `pack` label).

**Consequence:**

- Keep `bb_business_type` exactly as it is — **the authority for what a site is**. Do NOT
  create a second storage system, a network copy, or a duplicate registry (Phase 20 §2).
- The site must keep **reading** it (that is how the pack boots — `PackManager::boot_current()`).
- Only the **write** must become network-authorized.

---

## 4. Pack resolution (registry-driven, no branching)

`includes/Core/PackManager.php`:

- `register( $slug, $class )` — `sanitize_key()`, `class_exists()`, rejects blanks.
- `get_all()`, `get( $slug )`, `exists( $slug )`, `get_instance( $slug )`.
- `boot_current()` → `BusinessType::get_current()` → `boot( $slug )`.
- `boot()` instantiates through the DI `Container`, calls `register()` then `boot()`, idempotent.

`includes/Core/ServiceProvider.php:288-318`: creates the manager, registers `law_firm`, fires
`do_action( 'bb_register_packs', $pack_manager )`. Phase 19 added Medical's self-registration;
the Core names only its own pack.

**Consequence — the Network layer must derive packs through this architecture:**

```php
$pack_manager->exists( $business_type_slug )   // "is there a pack for this type?"
$pack_manager->get( $business_type_slug )       // the class name
```

**No `if ( $business_type === 'law_firm' )` / `if ( … === 'medical' )` anywhere in Network code.**
A future pack becomes visible the moment it self-registers — no Network UI edit (Phase 20 §4, §29).

---

## 5. Design preset system (reused; discovered, not hardcoded)

Measured registry (`_phase20-audit-presets.php`):

| Context | Presets returned |
| --- | --- |
| Network context (no pack booted) | `default`, `modern`, `luxury` |
| Medical site context (Medical pack booted) | `default`, `modern`, `luxury`, `medical-modern` |

- `bb_theme_presets()` is a **filter** — `bb_theme_presets` — so packs contribute presets
  (Phase 19 proved this).
- `bb_theme_get_preset()` reads the theme mod `bb_theme_preset`, applies
  `bb_theme_active_preset`, then **validates against the registry and falls back to `default`**.
- A preset is stored per site as a **theme mod** (`theme_mods_business-builder`).

Measured per site: blog 1 = `''`, blog 2 = `default`, blog 3 = `medical-modern`.

**Consequence:**

- The Network provisioning UI must **discover** presets from `bb_theme_presets()` — never a
  hardcoded `default/modern/luxury/medical-modern` list (Phase 20 §8).
- Assignment must write the **existing** theme mod `bb_theme_preset` on the new site. Do NOT
  add `network_design` / `site_design` / `business_design` storage (Phase 20 §7).
- Because the filter only fires for the pack booted in the *current* context, the provisioning
  screen offers the Theme's base presets (always valid, always registered). A pack-contributed
  preset is validated by the **same** `bb_theme_get_preset()` validator before assignment, so
  an unregistered value can never be written. Note this honestly as a limitation (§16/§18 of
  the final report): pack-specific presets appear in the picker once the target site's pack is
  booted context.

---

## 6. Multisite facts that determine the provisioning design

Measured:

```
SUBDOMAIN_INSTALL = true     → sites are <sub>.builder.test
DOMAIN_CURRENT_SITE = builder.test
WP_ALLOW_MULTISITE  = set
domain_exists()     = available   → duplicate domain/path can be detected
get_site_by_path()  = available   → reverse lookup host → site
get_sites()         = available
wpmu_create_blog()  = available   → REAL site creation
wp_blogs columns    = blog_id, site_id, domain, path, registered, last_updated,
                      public, archived, mature, spam, deleted, lang_id
```

**Consequence — site creation must use `wpmu_create_blog()`** (Phase 20 §14: "The result must
be an actual WordPress Multisite site. Not a fake record."). The Phase 19 defect (mutating the
wrong blog) also proves §21's demand for strict `switch_to_blog()` discipline.

---

## 7. Domain model — WordPress vs a network registry

### 7.1 What WordPress provides

- `wp_blogs.domain` + `path` — the **platform address** (`medical.builder.test`). This is the
  routing identity; changing it changes how WordPress resolves the request.
- `domain_exists( $domain, $path )` — duplicate detection.
- `get_site_by_path()` — reverse lookup.
- `get_site_option()` / `update_site_option()` — network-scoped storage.

### 7.2 What WordPress does NOT provide

- **No custom-domain mapping table.** WordPress core has no concept of "a customer's purchased
  domain attached to this site" separate from the routing `domain` column.
- **No domain lifecycle state** (`pending` / `verified` / `active` / `rejected`).
- **No DNS verification.** Nothing in the repository, no DNS API, no TXT-record checker.
- No audit trail of domain associations.

`grep bb_network_domains|bb_domain_registry` → **NOT SET** (neither exists).

### 7.3 Decision

Phase 20 §31 permits a small registry **only if justified**, and §10/§13 require
normalize → validate → uniqueness → authorize → associate with `wp_blogs.domain` explicitly
called out as insufficient on its own. It is insufficient: it carries no ownership, no state,
and no protection against a site admin repointing their own site at someone else's host.

**Therefore: a small NETWORK-scoped domain registry is justified**, with:

| Property | Requirement (§31) |
| --- | --- |
| Scope | Network — `get_site_option` / `update_site_option` |
| Uniqueness | unique by **normalized** domain (one domain ⇒ at most one site) |
| Association | an explicit `blog_id` |
| Protection | capability-checked writes; no site-local storage |
| Auditability | `requested` / `requested_by` / timestamps |
| Independence | NOT stored in `bb_site_settings` (that would break ownership) |

**Explicitly NOT done:** writing the custom domain into `wp_blogs.domain`. That would break the
platform address and is not a real mapping. Phase 20 §12 forbids faking a mapping by changing
an option. Actual DNS + server alias + SSL is deployment infrastructure (§19) and is documented
as external.

States (Phase 20 §32), minimal and matched to what the environment can actually support:

```
pending    customer requested it; Network has not approved
active     Network approved it
rejected   Network declined it
```

No fake `verified` state — nothing here can verify DNS.

---

## 8. Reserved platform domains

The Network must never let a customer claim platform infrastructure (Phase 20 §33). Derived
from the **measured** environment, not hardcoded assumption:

- `DOMAIN_CURRENT_SITE` (`builder.test`) and the network's own host.
- Every host already registered in `wp_blogs` (all 3 sites).
- `www` + the base domain, and `www.<site>` variants.
- System subdomains: `admin`, `network`, `www`, `mail`, `ns1`, `ns2`, `localhost`, `ip`.

Checking `domain_exists()` **and** the registry **and** the reserved list is required; none
alone is sufficient.

---

## 9. Site-local customization (must NOT be locked down)

`includes/Settings/SiteSettings.php` owns a **site-scoped** option `bb_site_settings` with
defaults: `business_name`, `tagline`, `logo_id`, `favicon_id`, `primary_color`,
`secondary_color`, `accent_color`, `phone`, `email`, `address`, `whatsapp`, `facebook`,
`instagram`, `youtube`, `linkedin`, `twitter`, `show_*` toggles, consultation/appointment
payment flags, `notification_email`.

**It contains no business-type key** and no architectural identity.

**Consequence:** `bb_site_settings` is entirely **site-controlled** and must keep working
exactly as it does. Saving it must be untouched. The only change to the settings screen is
removing the *editable* business-type control and replacing it with read-only information —
nothing else in that form may change (Phase 20 §17, §30).

Theme customization (colours/typography/spacing/radius via the Customizer) is likewise
site-owned and must remain fully available.

---

## 10. Ownership matrix for Phase 20

| Capability | Network | Site | Existing authority |
| --- | --- | --- | --- |
| Site creation / provisioning | **●** | — | `wpmu_create_blog()` |
| Business Type (assignment) | **●** | read-only | `bb_business_type` (site-local, network-written) |
| Pack | derived | — | `PackManager` (no branch) |
| Platform address (subdomain) | **●** | — | `wp_blogs.domain/path` |
| Custom domain association | **●** (approve/remove) | **request** own only | **new** network registry |
| Initial design preset | **●** (assign) | may change preset | theme mod `bb_theme_preset` |
| Design tokens / colours / type / spacing | — | **●** | theme mods (`bb_design_*`) |
| Business info (name, phone, social) | — | **●** | `bb_site_settings` |
| Pages / builder / sections / layouts / card variants | — | **●** | page meta |
| Pack domain data | — | **●** | pack CPTs |
| Site lifecycle (archive/delete) | **●** | — | WordPress |

---

## 11. Security model

| Action | Capability | Notes |
| --- | --- | --- |
| See/enter Network screens | `manage_network` (super admin) | `network_admin_menu` only loads there anyway |
| Create a site | `manage_sites` + `manage_network` | WP `create_sites` policy also applies |
| Change Business Type | `manage_network` | replaces the current `manage_options` check |
| Assign initial design | `manage_network` | during provisioning |
| Request own custom domain | `manage_options` **on that site only** | ownership enforced server-side |
| Approve/reject/remove a domain | `manage_network` | Network authority |

Every mutation: capability check → nonce check → server-side ownership check → then mutate.
Never trust a posted `blog_id` (Phase 20 §20, §48 rule 25/26).

---

## 12. Multisite context discipline

Phase 19 produced a real contamination defect by mutating the wrong blog. Phase 20 repeats the
rule as a hard requirement:

- every site-local write happens **inside** `switch_to_blog( $target )`;
- `switch_theme()`, `update_option()`, `set_theme_mod()`, `flush_rewrite_rules()` only run there;
- `restore_current_blog()` in all paths, including failures;
- the target blog id comes from the **server-side return of `wpmu_create_blog()`**, never from
  the browser.

---

## 13. Failure / rollback policy (Phase 20 §22)

`wpmu_create_blog()` succeeds → later configuration (theme, business type, preset) can fail.
Audit finding: WordPress site creation is **not** transactionally reversible without risk
(deleting a freshly created site while the request is still in flight can orphan tables/uploads).
The safe policy, therefore:

1. Validate **everything** before creating the site (business type, pack, preset, domain,
   path) — this removes the realistic failure causes.
2. If a post-creation step fails: do **not** silently continue and do **not** auto-delete.
   Record the outcome on the site (a network-scoped status marker), report the failure to the
   operator, and leave the site visible in the Network list as **needs attention** with enough
   information to finish or remove it deliberately.

---

## 14. Files required

```
docs/phase-20-network-provisioning-audit.md          (this file)
docs/phase-20-network-provisioning-final-report.md
tests/runtime-phase20-network-provisioning.php

includes/Network/NetworkProvisioning.php   Network admin menu + screens + site list
includes/Network/SiteProvisioner.php       validated site creation (WPMU)
includes/Network/DomainRegistry.php        network-scoped custom-domain registry
includes/Network/BusinessTypeGuard.php     server-side Business Type write protection
includes/Network/SiteDomainPanel.php       the site-admin Custom Domain panel
```

Modified (surgical only):

```
includes/Admin/SiteSettingsPage.php   business type control → read-only; write path guarded
includes/Core/ServiceProvider.php     construct the network services
includes/Core/Plugin.php              expose them
business-builder-core.php             (nothing, unless a bootstrap hook is required)
```

**Must remain untouched:** the entire theme, `SectionRegistry`, `SectionRenderer`,
`SectionVariants`, `CoreSections`, `PageManager`, `BusinessType`, `SiteSettings`, both packs,
`PackManager`, the Autoloader, payments/notifications/audit.

---

## 15. Test plan (evidence required by §37/§40/§41)

- **Network**: super admin sees provisioning; a site-only admin cannot reach it; unauthorized
  POSTs fail.
- **Site creation**: real `WP_Site` returned; correct domain/path/title; belongs to the network;
  theme applied.
- **Business Type**: taken from the registry; valid accepted; invalid rejected; **site admin
  cannot change it** (tested by calling the handler directly, not just the UI); network admin can.
- **Pack**: resolved through `PackManager`; **no LawFirm/Medical literal in Network code**;
  both packs still function.
- **Design**: valid preset assigned to the intended site; invalid rejected; site-scoped; no leakage.
- **Domain**: normalization; duplicates rejected; reserved rejected; site admin can only affect
  their own site; cross-site attempts rejected; network admin can approve/remove.
- **Isolation**: Business Type / preset / settings / domain / business data across sites.
- **Regression**: Phases 18, 19, 10–17, and the business suites.

Audit complete. **Proceed to implementation — the control plane, not a redesign.**