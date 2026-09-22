# Phase 5 — Customer Management

## Audit Report (Audit-First)

**Project:** Business Builder Core — LawFirm Pack
**Scope:** Determine whether a Customer entity exists, is needed, and which
relationships are real. **No implementation is performed in this phase.**

> Guiding rule (Phase 5): *Do not invent the Customer domain. The existing
> project must determine the Customer architecture.*

---

## 1. CUSTOMER AUDIT — Complete Inventory

### 1.1 Where customer information currently lives

| Source | Customer data stored | Storage | Written by | Read by |
| --- | --- | --- | --- | --- |
| **Consultation** (`bb_consultation`) | `_bb_consultation_name`, `_bb_consultation_phone`, `_bb_consultation_email`, `_bb_consultation_preferred_contact`, `_bb_consultation_public_reference` | Post meta (posts table) | `ConsultationForm::store()` | `ConsultationAdmin`, `LookupHandler`, `Receipt`, `ReceiptRenderer`, `NotificationManager`, admin search |
| **Appointment** (`bb_appointment`) | `_bb_appointment_client_name`, `_bb_appointment_client_phone`, `_bb_appointment_client_email`, `_bb_appointment_public_reference` | Post meta (posts table) | `Availability::insert()` via `BookingForm` | `AppointmentAdmin`, `LookupHandler`, `Receipt` (free invoice), notifications |
| **Payment transaction** (`bb_transaction` CPT) | **No customer fields.** Only `object_type` + `object_id` (+ legacy `consultation_id`), gateway, amount, currency, reference, status | Post meta | `PaymentCheckout` / `TransactionRepository` | `Receipt`, `LookupHandler`, dashboards |
| **Manual payment submission** | Customer name/phone **resolved at render time** from the owning object's meta (`get_post_meta( ..., client_name/client_phone )`) — never copied | Derived, not stored | — | `ManualPaymentsAdmin`, `ManualPaymentSubmission` |
| **Free invoice (`Receipt::from_object()`)** | Name/phone **read from the object's own meta** (`_bb_consultation_name/phone` or `_bb_appointment_client_name/phone`) | Derived, not stored | — | Receipt renderer |
| **Notifications & Activity** | A single `customer` **string** (the display name) inside the notification feed option — a snapshot, not an entity reference | `bb_notifications_feed` site option | `ConsultationForm`, `BookingForm`, admin actions | `NotificationsAdmin`, `DashboardAdmin` |
| **Audit log** | Records admin `user_id`/`user_login` (the *actor*), not the customer | Activity store | `AuditLog::record()` | Notifications/Activity |
| **WordPress users / user meta** | **None.** No `get_user_meta`, `wp_insert_user`, `user_register`, or any buyer/role mapping | — | — | — |
| **Testimonials** (`bb_testimonial`) | `_bb_testimonial_client_name` / `_bb_testimonial_client_title` — **marketing content**, unrelated to operational customers | Post meta | `TestimonialFields` | `LawFirmSections` |

### 1.2 Confirmed: no canonical Customer entity

There is **no** Customer CPT, no customer DB table, no Customer class /
repository / service / manager, no customer REST endpoint, no `customer_id`,
and no `bb_customer*` meta key anywhere in the codebase. The string
"customer" appears only in UI copy, notification `data['customer']` display
name, and doc comments.

---

## 2. CLASSIFICATION

| Component | Verdict |
| --- | --- |
| Consultation customer fields (name/phone/email) | **EXISTS AND WORKS** |
| Appointment customer fields (client_name/phone/email) | **EXISTS AND WORKS** |
| Public reference (`CNS-…` / `APT-…`) as de-facto customer identifier | **EXISTS AND WORKS** |
| Identity normalization (phone) | **PARTIALLY EXISTS** — `LookupHandler::normalize_phone()` strips non-digits + leading zeros; used only for lookup verification, not for identity |
| Payment customer identity | **MISSING** (by design — owned by the object) |
| Receipt customer data | **EXISTS AND WORKS** (derived from the owning object; correct single-sourcing) |
| Notification/Activity customer reference | **PARTIALLY EXISTS** — display-name snapshot only, no id |
| Linked Consultation ↔ Appointment | **PARTIALLY EXISTS / INERT** — `_bb_appointment_consultation_id` field exists but no template posts it, so it is always `0` |
| Canonical Customer entity | **MISSING** |
| Customer admin screen / list / search | **MISSING** |
| Customer ↔ WordPress User relationship | **MISSING** (no user linkage at all) |
| Customer merge / duplicate detection | **MISSING** |
| Customer privacy / capabilities | **PARTIALLY EXISTS** — records are capability-gated via the owning CPT (`edit_post`/`edit_pages`); no dedicated customer capability |

---

## 3. RELATIONSHIP MATRIX

Evidence column cites the exact code that proves the current state.

| Relationship | Exists Today | Evidence | Recommended |
| --- | --- | --- | --- |
| Customer ↔ Consultation | **No (data only)** | Customer fields stored on `bb_consultation`; no customer id/reference | **Reject** — keep Consultation as owner of its own contact snapshot |
| Customer ↔ Appointment | **No (data only)** | Customer fields stored on `bb_appointment` (`client_name/phone/email`) | **Reject** — keep Appointment independent |
| Customer ↔ Payment | **No** | `PaymentTransaction` has no customer fields; identity is `object_type/object_id` | **Reject** — Payment stays owned by Consultation/Appointment |
| Customer ↔ Receipt | **Indirect** | `Receipt::customer_name()/customer_phone()` read from the related object | **Reject** — no duplicate receipt customer storage (already correct) |
| Customer ↔ Lawyer | **No** | No reference between customer data and `bb_lawyer` | **Reject** — a lawyer is reached only through a consultation/appointment |
| Customer ↔ Notification | **Weak** | `NotificationManager::store()` keeps a `customer` *string* only | **Reject** — keep Notification referencing the underlying entity |
| Customer ↔ Activity | **No** | `AuditLog` records the admin actor, not a customer | **Reject** — no second activity system |
| Customer ↔ WordPress User | **No** | Zero `get_user_meta`/`wp_insert_user` usage | **Reject** — forms run for logged-out visitors (`admin_post_nopriv_*`) |
| Consultation ↔ Appointment | **Inert** | `consultation_id` meta exists; no form ever posts `bb_consultation_id` | **Note only** — do not build Customer on top of an unused link |

---

## 4. DATA FLOW (current, real)

```text
Consultation form (logged-out OK)
        ↓  admin-post.php + nonce
ConsultationForm  →  bb_consultation post (name/phone/email + CNS-… reference)
        ↓  (optional, section-driven)
PaymentCheckout   →  bb_transaction post (object_type=consultation, object_id)
        ↓  verified callback/webhook
Receipt / Free invoice  →  reads name/phone FROM the consultation object
        ↓
NotificationManager (dashboard feed) / AuditLog (actor) / e-mail admin
```

```text
Booking form (logged-out OK)
        ↓  admin-post.php + nonce
BookingForm → Availability::create() → bb_appointment post
              (client_name/phone/email + APT-… reference)
        ↓  (optional) same PaymentCheckout path with object_type=appointment
        ↓
Receipt / notification  →  reads client_name/client_phone FROM the appointment
```

```text
Lookup (public)  →  reference + phone  →  returns a safe summary
                    of a consultation OR an appointment
```

**Customer is explicitly absent from every flow.** There is no node to insert
it into, because no subsystem references a customer identity — each entity
carries an immutable contact snapshot for its own record.

---

## 5. PROPOSED CUSTOMER ARCHITECTURE

### Verdict

```text
Canonical Customer Entity: NOT REQUIRED AT THIS STAGE
```

### Why (evidence-based)

1. **No consumer needs it.** Every subsystem that displays customer data
   (Receipt, Manual Payments, Lookup, Notifications, admin lists) already
   resolves it correctly from the owning object. None of them asks for a
   cross-record identity.
2. **The domain is snapshot-based by design.** Receipts/payments must retain
   historical contact data for integrity (Phase 12) — the current model
   already does exactly that. A canonical entity would create a second source
   of truth and a merge/identity problem the business has not asked to solve.
3. **Identity cannot be reliably established.** Email and phone are both
   optional in the consultation form (`phone OR email` is required, not both).
   Phone formats vary (Arabic, international, shared family numbers), and the
   existing normalizer is deliberately lenient for *lookup*, not for *merging*.
   Building identity on it would risk incorrect auto-merges (Phase 7 / 13 / 14).
4. **Multisite.** Posts-table storage is already per-site, so the current
   model is correctly site-isolated with zero extra work (Phase 19).
5. **Forms are anonymous by design.** Consultation and booking work for
   logged-out visitors; there is no registration, no login, no account — so
   a Customer ↔ User link has nothing to link (Phase 5 / 16 / 21).

### If a Customer entity is ever approved (future, minimum viable)

Only if a *concrete* business requirement appears (e.g. "returning customer
history", "customer portal login", "cross-visit search"). Then, and only then,
the minimum safe shape would be:

- A `bb_customer` private CPT storing **reference only** (`CUS-…`, non
  sequential — reuse `ConsultationMeta::generate_public_reference()` pattern),
  plus `created/updated`.
- **No contact fields copied.** Contact stays on the records; the customer
  entity holds an *optional* account link, not duplicate PII.
- Relationships added **only** where a consumer needs them (not "logically
  nice").
- A **manual, reviewable** link step initiated from a consultation/appointment
  — never automatic identity guessing.
- No merge in the first iteration.

**This is a proposal for a later phase, not this one.** Per the phase's own
Phase 32 rule, implementation does not follow this audit because the audit
concludes no standalone entity is currently required.

---

## 6. FILES INSPECTED

**Consultation / customer data**
- `packs/LawFirm/PostTypes/Consultation.php`
- `packs/LawFirm/PostTypes/ConsultationMeta.php`
- `packs/LawFirm/Frontend/ConsultationForm.php`
- `packs/LawFirm/Admin/ConsultationAdmin.php`
- `packs/LawFirm/Frontend/LookupHandler.php`

**Appointment**
- `packs/LawFirm/Appointments/Appointment.php`
- `packs/LawFirm/Appointments/AppointmentMeta.php`
- `packs/LawFirm/Appointments/Availability.php`
- `packs/LawFirm/Appointments/BookingForm.php`
- `packs/LawFirm/Appointments/AppointmentAdmin.php`

**Payment / Receipt**
- `includes/Core/Payments/PaymentTransaction.php`
- `includes/Core/Payments/Receipt/Receipt.php`
- `includes/Core/Payments/Receipt/ReceiptRenderer.php`
- `includes/Core/Payments/Receipt/ReceiptPage.php`
- `includes/Core/Payments/Transaction/Reference.php`
- `includes/Core/Payments/Transaction/TransactionRepository.php`
- `includes/Core/Payments/Checkout/PaymentCheckout.php`
- `includes/Core/Payments/PaymentManager.php`

**Notifications / Activity / Audit**
- `includes/Core/Notifications/NotificationManager.php`
- `includes/Core/Audit/AuditLog.php`

**Platform / Multisite / Admin**
- `includes/Core/Plugin.php`
- `includes/Core/Activator.php`
- `packs/LawFirm/LawFirmPack.php`
- `packs/LawFirm/config.php`
- `packs/LawFirm/Admin/DashboardMenu.php`
- `packs/LawFirm/Admin/DashboardAdmin.php`
- `packs/LawFirm/Admin/ManualPaymentsAdmin.php`
- `packs/LawFirm/Payments/ManualPaymentSubmission.php`

Searches run: `customer|client_name|billing_name|payer|patient|visitor`,
`customer_id|bb_customer|customer_ref|Customers|consultation_id`,
`get_user_meta|wp_insert_user|wp_get_current_user|user_register` (whole codebase).

## 7. FILES MODIFIED

```text
No source files were modified.
```

Only this audit document was added:
`docs/phase-5-customer-audit.md`

## 8. DATABASE CHANGES

```text
No database changes were required.
```

## 9. TEST RESULTS

```text
No tests were executed for this phase.
```

Reason (Phase 30): Customer Management does not exist and was **not approved
for implementation**. The phase explicitly forbids writing tests that pretend
a Customer entity exists. No behaviour was changed, so there is nothing new to
test. The previously completed phases are untouched and their existing tests
remain valid.

## 10. REMAINING QUESTIONS

These require a **product/business decision** — they cannot be answered from
the code, and per Phase 5 must not be guessed:

1. Is there a real business need to recognize a *returning* customer across
   multiple consultations/appointments? (If no → customer entity stays out.)
2. Will customers ever get an account / portal / login? (Drives Phase 5 &
   Phase 21.)
3. Is a per-customer payment/receipt history ever needed, or is per-record
   history sufficient?
4. Should consultation and appointment ever be explicitly linked (the inert
   `consultation_id`)? If yes, that is an Appointment-phase change, not a
   Customer change.
5. Multisite: does the business ever need a customer to be visible across
   sites, or is per-site isolation the permanent model? (Current default:
   isolated.)
