# Phase 6 — Dashboard Completion, Audit & UI Modernization

## Audit Report (Audit-First)

**Project:** Business Builder Core — LawFirm Pack
**Scope:** Audit the existing LawFirm admin Dashboard, complete what is
actually missing, fix what is actually broken, modernize the UI in place using
the supplied design system. **No rebuild. No new domain systems.**

---

## 1. DASHBOARD AUDIT — Classification

The Dashboard is a **server-rendered, real-data operational console** mounted on
the independent top-level "Law Firm" menu (`bb-law-firm`). There is no Dashboard
AJAX, no Dashboard JS, and no client-side charting — PHP renders everything from
`DashboardStats`, `NotificationManager` and the canonical domain classes.

| Component | Status | Evidence | Action |
| --- | --- | --- | --- |
| Dashboard menu (`DashboardMenu`) | **EXISTS AND WORKS** | `add_menu_page( bb-law-firm )`, cap `manage_options`, `show_in_menu` repointing for CPTs | Preserve |
| Dashboard controller (`DashboardAdmin`) | **EXISTS AND WORKS** | Renders header, KPI grid, charts, quick actions, notifications, action center, recent consultations | Extend (minor) |
| KPI statistics (`DashboardStats::get_kpis`) | **EXISTS AND WORKS** | 12 KPIs from real queries | Extend (add manual-review KPI) |
| Consultation KPI | **EXISTS AND WORKS** | total/new/pending/paid/unpaid via meta loop | Preserve |
| Appointment KPI | **EXISTS AND WORKS** | total/upcoming/today | Preserve |
| Payment (revenue) KPI | **EXISTS AND WORKS** | `revenue_metrics()` over `paid` transactions | Preserve |
| Manual Payment KPI | **MISSING** | No manual-review counter on the dashboard; `ManualPaymentsAdmin` exists with its own screen | **Add** (count of `on_hold` transactions) |
| Lawyer KPI | **EXISTS AND WORKS** | total (published) + active (`show_on_website != 0`) | Preserve |
| Notifications widget | **EXISTS AND WORKS** | `NotificationManager::feed(5)` + unread badge + real actions | Preserve |
| Action Center | **EXISTS AND WORKS** | New consultations + unpaid consultations + pending appointments with real admin-post actions | Preserve |
| Activity / recent records | **PARTIALLY EXISTS** | Only "Recent Consultation Requests" — no recent appointments/payments | **Extend** (recent appointments) |
| Charts (CSS bar charts) | **EXISTS AND WORKS** | 8 real distributions/time-series; honest empty states | Preserve |
| Quick Actions | **EXISTS AND WORKS** | 7 links, all real destinations | Preserve |
| Date range / period filter | **MISSING** | No filter UI; time-series is fixed at 6 months | **NOT ADDED** — see §6 |
| DB migration | **NOT APPLICABLE** | Uses posts/meta/options only | None |
| Dashboard CSS (`assets/css/admin/dashboard.css`) | **EXISTS BUT NEEDS EXTENSION** | Self-contained `--bbd-*` tokens; uses WP blue `#2271b1`, not the design system palette | **Modernize** (CSS only) |
| Cross-screen header styles | **EXISTS BUT BUGGY** | `bb-dashboard-header`/`bb-btn` defined ONLY in `dashboard.css`, but used by Notifications/PaymentLogs/ManualPayments screens that never load it | **Fix** (CSS-only: move shared shell to a file loaded by all four OR scope) |
| Responsive layout | **PARTIALLY EXISTS** | One breakpoint (1100px) collapses the 2-col grid; KPI grid is auto-fill | **Extend** (breakpoints per design system) |
| RTL | **EXISTS AND WORKS** | `.bb-rtl` from `is_rtl()`; logical props (`inset-inline-start`, `margin-inline-start`) | Preserve + extend |
| Accessibility | **PARTIALLY EXISTS** | Semantic markup, `dashicons` text icons; no focus-visible ring on `bb-btn`; no reduced-motion block | **Extend** (CSS only) |
| Placeholder metrics / fake data | **EXISTS AND WORKS (i.e. NO fake data — good)** | Every number traced to a real query | Preserve — do not break |

### Data source mapping (every KPI → canonical query)

```text
Consultations  → get_posts( bb_consultation, publish ) → _bb_consultation_status / _bb_consultation_payment_status
Appointments   → get_posts( bb_appointment, publish )  → _bb_appointment_status / _bb_appointment_date
Revenue        → PaymentManager::transactions(500) filter status=paid → amount/currency/created_at
Lawyers        → wp_count_posts( bb_lawyer ) + _bb_lawyer_show_on_website
Notifications  → NotificationManager::feed / unread_count  (bb_notifications_feed option)
Manual review  → PaymentManager::transactions → status = on_hold   (same source ManualPaymentsAdmin uses)
```

All queries are **site-scoped** (posts/options are per-site on Multisite) and
bounded (`fields=ids`, `no_found_rows`, `posts_per_page=-1` on small private
CPTs). No cross-site leak. No duplicate storage exists.

### Problems found

1. **Design-system mismatch (visual).** `dashboard.css` hardcodes WP admin blue
   `#2271b1` and ad-hoc greens/ambers, not the supplied design system
   (`#2563EB` primary, `#10B981` emerald, `#F59E0B` amber, `#EF4444` red,
   `#3B82F6` info, 8px spacing, 12/14/16px type). The design tokens already
   exist in `assets/css/frontend/design-tokens.css` but are scoped to
   `.bb-template` (frontend) and never reach wp-admin.
2. **Cross-screen style gap (functional/bug).** `.bb-dashboard-header`,
   `.bb-dashboard-title`, `.bb-dashboard-header-actions` and `.bb-btn*` live
   only in `dashboard.css`. The Notifications, Payment Logs and Manual Payments
   screens render those exact classes but do **not** enqueue `dashboard.css`, so
   their headers/buttons are unstyled. (Confirmed: grep of `assets/css/admin/*.css`
   shows the classes are defined nowhere else.)
3. **Missing operational KPI.** Manual payment review is the single most
   actionable queue in the phase brief (§13, §20) yet it has no dashboard
   counter.
4. **Thin recent-records coverage.** Only consultations are surfaced; the brief
   (§17) expects recent appointments too.
5. **Accessibility gaps.** No `:focus-visible` treatment on `bb-btn` /
   `bb-quick-item` / KPI cards; no `prefers-reduced-motion` block; hover-only
   affordances on some interactive cards.
6. **Responsive gaps.** Single 1100px breakpoint; bar-chart label column is a
   fixed `120px` and can crowd on tablet.

### Duplicate systems

**None.** The Dashboard consumes existing services/queries only. It defines no
CPT, no table, no option store, no second notification/activity/receipt system.

### Components that must NOT change (protected)

Consultation · Appointment · Payment · Payment Gateways · Manual Payment ·
Receipt/ReceiptRenderer · Notifications & Activity · Lawyer · Page Builder ·
SectionRegistry/SectionManager/SectionRenderer · Multisite architecture ·
`DashboardMenu` menu slug/capability · all `DashboardStats` domain queries
(the numbers are correct).

---

## 2. Recommended changes (audit-justified only)

| Change | File | Type | Reason | Risk |
| --- | --- | --- | --- | --- |
| Retoken + restyle the dashboard shell to the design system | `assets/css/admin/dashboard.css` | CSS | §6/§29–§44: match supplied visual language | Low (scoped to `.bb-dashboard`) |
| Extract shared header/`bb-btn` shell into a shared stylesheet loaded by all 4 LawFirm admin screens | `assets/css/admin/dashboard.css` (shared handle) + `enqueue_assets` in `DashboardAdmin`, `NotificationsAdmin`, `PaymentLogsAdmin`, `ManualPaymentsAdmin` | CSS + tiny PHP | Fix the unstyled sibling headers | Low |
| Add "Manual Payments Awaiting Review" KPI | `DashboardStats` + `DashboardAdmin` | PHP | §13/§20 operational priority; sourced from `on_hold` transactions | Low (reuses PaymentManager) |
| Add "Recent Appointments" section | `DashboardStats` + `DashboardAdmin` | PHP | §17 recent records | Low (read-only) |
| Add focus-visible, reduced-motion, better responsive | `dashboard.css` | CSS | §43/§44/§40/§62 | Low |

**Explicitly NOT doing** (no justification found): date-range filter
(§21 — optional; time-series is fixed 6-month by design and a filter would
require touching every stats query for marginal value), new charting library
(§22 — no data/new need), customer metrics (§78 — no Customer entity), second
activity system (§76), dashboard AJAX/JS (none exists; none needed).

---

## 3. Files inspected

`packs/LawFirm/Admin/DashboardAdmin.php`,
`packs/LawFirm/Admin/DashboardStats.php`,
`packs/LawFirm/Admin/DashboardMenu.php`,
`packs/LawFirm/Admin/NotificationsAdmin.php`,
`packs/LawFirm/Admin/PaymentLogsAdmin.php`,
`packs/LawFirm/Admin/ManualPaymentsAdmin.php`,
`packs/LawFirm/LawFirmPack.php`,
`includes/Core/Notifications/NotificationManager.php`,
`includes/Core/Payments/PaymentManager.php`,
`assets/css/admin/dashboard.css`,
`assets/css/admin/notifications.css`,
`assets/css/admin/manual-payments.css`,
`assets/css/frontend/design-tokens.css`,
`tests/runtime-admin-pages.php`, `tests/runtime-admin-render.php`.