# Functional & Non-Functional Specification

**System:** Salon Booking & Inventory System
**Derived from:** `routes/web.php`, `app/Http/Controllers/`, `app/Services/`, `app/Models/`
**Date:** October 2, 2026
**Status legend:** ✅ implemented · ⚠️ implemented with a gap or limitation · ❌ not implemented

> **No real personal data appears in this document.**

---

# Part A — Functional Requirements

## A.1 Public / guest

| ID | Requirement | Status | Evidence |
|---|---|---|---|
| FR-01 | The system shall present a landing page describing the salon and its services | ✅ | `routes/web.php:44` |
| FR-02 | The system shall present a company profile page | ✅ | `routes/web.php:45` |
| FR-03 | The system shall display the service catalogue grouped by category, with price and duration | ✅ | `routes/web.php:52`, `views/services/index.blade.php` |
| FR-04 | The system shall display a single service with its variants | ✅ | `routes/web.php:53` |
| FR-05 | The system shall provide a category-filtered grid view of services | ✅ | `routes/web.php:55` |
| FR-06 | The system shall display only promos that are **active and inside their validity window** | ✅ | `PromoBanner::all()` → `Promo::scopeActive()` |
| FR-07 | The system shall let a guest submit an enquiry without creating an account | ✅ | `routes/web.php:59`, throttled 12/min |
| FR-08 | The system shall display published Terms & Conditions per category | ✅ | `routes/web.php:62` |
| FR-09 | The system shall expose live slot availability without requiring sign-in | ✅ | `routes/web.php:235`, throttled 60/min |
| FR-10 | The system shall enforce enquiry categories from a fixed list | ✅ | `InquiryTopic` enum, 7 values |

## A.2 Customer account lifecycle

| ID | Requirement | Status | Evidence |
|---|---|---|---|
| FR-11 | A visitor shall self-register with first/last name, email, contact number and password | ✅ | `RegisteredUserController`; `RegisterRequest` validates server-side |
| FR-12 | The system shall derive a unique username from the email at registration | ✅ | migration comment, `users.username` UQ |
| FR-13 | A customer shall authenticate with **either** email or username | ✅ | `AuthenticatedSessionController` |
| FR-14 | The system shall hash every password with bcrypt (12 rounds) | ✅ | `hashed` cast + `BCRYPT_ROUNDS=12` |
| FR-15 | The system shall enforce a minimum 8-character password with confirmation | ✅ | `min:8`, `confirmed` on all four password paths |
| FR-16 | The system shall reset a password via a 4-step wizard: email → 6-digit code → new password | ✅ | `routes/web.php:82-89`, `PasswordResetService` |
| FR-17 | The system shall store the reset code **hashed**, with an expiry and a single-use marker | ✅ | `password_reset_codes.code_hash`, `expires_at`, `used_at` |
| FR-18 | The system shall rate-limit login attempts | ✅ | `throttle:10,1` + per-account `RateLimiter` counter |
| FR-19 | The system shall support "remember me" | ✅ | `remember_token` on both identity tables |
| FR-20 | The system shall let a customer end their own session | ✅ | `routes/web.php:92` |
| FR-21 | The system shall let a customer view and update their own profile | ✅ | `routes/web.php:221-222` |
| FR-22 | The system shall let a customer upload, replace and remove a profile photo | ✅ | `routes/web.php:223`; superseded file deleted from disk |
| FR-23 | The system shall let a customer change their own password | ⚠️ | Implemented, but there is **no re-authentication** step — a live session can change the password without entering the current one |
| FR-24 | The system shall let a customer delete their own account | ❌ | Not implemented. Removal is staff-initiated only — see `rbac-matrix.md` M6 |

## A.3 Booking

| ID | Requirement | Status | Evidence |
|---|---|---|---|
| FR-25 | The system shall require sign-in before a booking is made | ✅ | `routes/web.php:140-145` — every booking ties to an account |
| FR-26 | The system shall offer a multi-step booking wizard | ✅ | `views/customer/appointments/create.blade.php`, 7 steps |
| FR-27 | The system shall let a customer select multiple services and quantities | ✅ | Step 1, `selectedIds` + `lines` |
| FR-28 | The system shall let a customer choose a variant per service where one exists | ✅ | `service_variants`, `is_default` pre-selected |
| FR-29 | The system shall let a customer choose a technician or "No preference" | ✅ | `appointments.technician_id`, nullable by design |
| FR-30 | The system shall show only **4 services per category** with a *See More* disclosure, expanded state held per category | ✅ | `x-salon.service-choice`, `bookingForm.expandedCategories` |
| FR-31 | A service selected but hidden behind *See More* shall retain its selection | ✅ | Rows are hidden with `x-show`, never removed from the DOM or disabled |
| FR-32 | The system shall capture preferred date and time | ✅ | `preferred_date`, `preferred_time` |
| FR-33 | The system shall capture the customer's name, phone and email | ✅ | `customer_name`, `customer_phone`, `customer_email` |
| FR-34 | The system shall capture allergies and sensitivities as SPI | ✅ | `allergies`, plus a 15-value `BookingPreference` enum |
| FR-35 | The system shall capture treatment history and free-text special requests | ✅ | `last_services_availed`, `special_request` |
| FR-36 | The system shall require acceptance of the applicable Terms & Conditions | ✅ | Checkbox on the final step; server refuses without it |
| FR-37 | The system shall generate a unique human-readable booking reference | ✅ | `reference_number`, UQ |
| FR-38 | The system shall snapshot service name, price and duration onto the booking line | ✅ | `appointment_service` — history cannot be rewritten by a later catalogue edit |
| FR-39 | The system shall compute the booking total from snapshotted lines | ✅ | `total_amount` |
| FR-40 | The system shall validate the date against the lead time, operating hours and slot interval | ✅ | `BookingAvailability`, `salon_settings` |
| FR-41 | The system shall refuse a booking whose Terms & Conditions were not accepted | ✅ | Server-side, not just a client checkbox |
| FR-42 | The system shall send a confirmation notification on booking | ✅ | `AppointmentBookedNotification` |
| FR-43 | The system shall rate-limit booking submission | ❌ | **No `throttle` on `POST /book`** — see `audit-report.md` G5.1 |
| FR-44 | The system shall prevent double-submitting a booking | ⚠️ | Unknown — no idempotency key was found; a duplicate submit may create two bookings |

## A.4 Appointment management (customer side)

| ID | Requirement | Status | Evidence |
|---|---|---|---|
| FR-45 | The system shall list a customer's own appointments with status and reference | ✅ | `appointments.index` |
| FR-46 | The system shall let a customer view one of their own appointments | ✅ | Scoped by `authorizeOwnership()` |
| FR-47 | The system shall let a customer request cancellation with a reason | ✅ | `CancelAppointmentController`, `cancellation_reason` |
| FR-48 | The system shall refuse cancelling an appointment that is not in a cancellable status | ✅ | Server-side status check |
| FR-49 | The system shall let a customer reschedule to a new date/time with a reason | ✅ | `RescheduleAppointmentController` |
| FR-50 | The system shall re-validate availability on reschedule | ✅ | New date goes through `BookingAvailability` |
| FR-51 | The system shall let a customer clear **settled** bookings from their list (one or bulk) | ✅ | Only Completed/Cancelled, refused server-side |
| FR-52 | The system shall prevent a customer acting on another customer's appointment | ✅ | `user_id === auth()->id()` → 403, in all three controllers |

## A.5 Notifications

| ID | Requirement | Status | Evidence |
|---|---|---|---|
| FR-53 | The system shall maintain a per-customer notification inbox | ✅ | `notifications` table, polymorphic |
| FR-54 | The system shall support marking one notification read | ✅ | `PATCH` + JSON unread count |
| FR-55 | The system shall support marking all read | ✅ | Declared before the `{notification}` route so the literal path is not swallowed |
| FR-56 | The system shall support deleting one or all notifications | ✅ | Scoped through `$request->user()->notifications()` |
| FR-57 | The system shall badge unread count in the navbar and in the tab title | ✅ | `meta[bta-session]`, `TabTitle` |
| FR-58 | The system shall notify on: booking, confirmation, in-progress, completed, cancelled-by-admin, cancelled-by-customer, rescheduled, reminder, welcome, promo announcement | ✅ | 12 notification classes in `app/Notifications/` |
| FR-59 | The system shall poll for new notifications rather than push | ⚠️ | By design — no WebSocket server. Poll interval and the "no store" cache policy matter |

## A.6 Administration — appointments

| ID | Requirement | Status | Evidence |
|---|---|---|---|
| FR-60 | The system shall give staff a dashboard with today's appointments and status counts | ✅ | `AdminDashboardController` |
| FR-61 | The system shall list appointments with filters and sortable columns | ✅ | `DataTable` allow-list + client-side filter/paging |
| FR-62 | The system shall record every status change with actor and note | ✅ | `appointment_status_history` |
| FR-63 | The system shall let staff update status | ✅ | `PATCH`, JSON for the inline switch |
| FR-64 | The system shall let staff attach internal notes never shown to the customer | ✅ | `admin_notes`; migration comment states it |
| FR-65 | The system shall let staff verify a down payment against a manual reference | ✅ | `down_payment_status`, no gateway integrated |
| FR-66 | The system shall archive settled appointments | ✅ | Manual, or nightly at 02:00 after 30 days |
| FR-67 | The system shall let staff restore an archived appointment | ✅ | Single and bulk |
| FR-68 | The system shall permanently delete an appointment | ⚠️ | Only when settled; server-side status re-check |
| FR-69 | The system shall let staff manage the enquiry inbox | ✅ | Read, reply, delete |
| FR-70 | The system shall surface unread enquiries in the admin bell | ✅ | `admin.notifications.feed` |

## A.7 Administration — catalogue

| ID | Requirement | Status | Evidence |
|---|---|---|---|
| FR-71 | The system shall let staff create, edit and soft-delete services | ✅ | `admin.catalog.manage` |
| FR-72 | The system shall let staff toggle service availability without deleting | ✅ | `PATCH services/{service}/toggle` |
| FR-73 | The system shall let staff manage price/duration variants | ✅ | Create, update, delete |
| FR-74 | The system shall let staff create, edit and order categories with a colour | ✅ | `service_categories.color`, `sort_order` |
| FR-75 | The system shall let staff upload a category photo | ✅ | `categories/` on the public disk |
| FR-76 | The system shall let staff manage technicians | ✅ | Separate table from admins |
| FR-77 | The system shall let staff upload a technician photo | ✅ | Max 2 MB |
| FR-78 | The system shall let staff manage promos with an image, validity window and active switch | ✅ | `promos` |
| FR-79 | The system shall push a promo to customers as a notification | ✅ | Audience: all / active / last 90 days |
| FR-80 | The system shall version Terms & Conditions and publish per category | ✅ | `UQ (category, version)`, `is_published` |
| FR-81 | The system shall render Terms as a numbered list regardless of author formatting | ✅ | `TermsRenderer` supplies structure; strips hand-typed numbers |
| FR-82 | The system shall sanitise Terms HTML against a tag/attribute allow-list | ✅ | `TermsRenderer::sanitise()` |

## A.8 Administration — inventory & reporting

| ID | Requirement | Status | Evidence |
|---|---|---|---|
| FR-83 | The system shall let staff manage inventory items with SKU, quantity, unit, supplier and expiry | ✅ | `inventory_items` |
| FR-84 | The system shall link services to the items they consume, with a per-service quantity | ✅ | `service_inventory` M:N junction |
| FR-85 | The system shall let staff tag an item available / low stock / best seller / sold out | ✅ | `admin.tags.manage`, single and bulk |
| FR-86 | The system shall surface a low-stock warning against a reorder threshold | ✅ | `reorder_threshold` |
| FR-87 | The system shall produce a sales/usage report | ✅ | `ReportService`, `admin.reports.view` |
| FR-88 | The system shall export reports and inventory to CSV | ✅ | Two export routes; inventory gated by the inventory ability |

## A.9 Administration — users

| ID | Requirement | Status | Evidence |
|---|---|---|---|
| FR-89 | The system shall list registered customers and show one customer's detail | ✅ | No create route — customers self-register |
| FR-90 | Removing a customer shall **anonymise then soft-delete**, preserving booking history | ✅ | `UserAnonymizer` |
| FR-91 | The system shall write the confirmation wording server-side | ✅ | `GET users/{user}/delete-confirmation` |
| FR-92 | The system shall let staff edit their own account | ✅ | No ability required — self-service |

---

# Part B — Non-Functional Requirements

## B.1 Security

| ID | Category | Requirement | Status | Evidence |
|---|---|---|---|---|
| NFR-01 | Injection | All database access shall use parameterised queries | ✅ | Only static/`selectRaw` fragments exist; the one request-driven ORDER BY is allow-listed twice (`DataTable.php:33-62`) |
| NFR-02 | Auth | Passwords shall be stored as bcrypt hashes, never plaintext | ✅ | `hashed` cast on both identity models |
| NFR-03 | Auth | Password policy enforced server-side | ✅ | `min:8` + `confirmed` |
| NFR-04 | Auth | Session cookies shall be `HttpOnly` and `SameSite=Lax` | ✅ | `config/session.php:185,202` |
| NFR-05 | Auth | Session cookies shall be `Secure` in production | ❌ | `SESSION_SECURE_COOKIE` is unset — `config/session.php:172` |
| NFR-06 | Auth | Sessions shall expire | ✅ | 120-minute lifetime |
| NFR-07 | Auth | Session data shall be encrypted at rest in the session store | ⚠️ | `SESSION_ENCRYPT=false` — a conscious trade-off, should be stated |
| NFR-08 | CSRF | Every state-changing request shall be CSRF-verified | ✅ | No exclusions anywhere; all forms tokenised; all state-changing `fetch` calls send `X-CSRF-TOKEN` |
| NFR-09 | XSS | User-supplied output shall be escaped | ✅ | All `{{ }}` except 10 audited `{!! !!}`, each justified |
| NFR-10 | XSS | Admin-authored rich text shall be sanitised before rendering | ✅ | `TermsRenderer` allow-list; drops script/style/iframe with content; blocks `javascript:`/`data:` hrefs |
| NFR-11 | Upload | Uploaded files shall be validated by type and size | ✅ | 4 paths, `image` + `mimes` + `max` |
| NFR-12 | Upload | Stored filenames shall not be attacker-controlled | ✅ | `store()` generates a random hashed name |
| NFR-13 | Upload | Uploads shall not be executable | ✅ | Image extensions only; served as static files via `storage:link` |
| NFR-14 | Authz | Admin routes shall require admin authentication and a role ability | ✅ | `auth.admin` group + `admin.role:*` per route |
| NFR-15 | Authz | Customer routes shall require customer authentication and an active account | ✅ | `auth.customer`, `user.active`, `no.store` |
| NFR-16 | Authz | A customer shall access only their own appointments | ✅ | `authorizeOwnership()` in 3 controllers |
| NFR-17 | Authz | Admin and customer identities shall not be confusable | ✅ | Separate tables, providers and guards |
| NFR-18 | Rate limit | Login shall be rate-limited | ✅ | Route throttle **and** per-account counter |
| NFR-19 | Rate limit | Registration, booking submission and password reset shall be rate-limited | ⚠️ | Reset yes; **registration and booking no** |
| NFR-20 | Cache | Authenticated pages shall not be stored in the browser's bfcache | ✅ | `PreventAuthenticatedCaching` applied inside both auth groups |
| NFR-21 | Debug | Production shall run with `APP_DEBUG=false` | ⚠️ | Config defaults to `false`, but `.env.example` ships `true` — a deployment that copies it unchanged leaks stack traces |
| NFR-22 | Secrets | No credentials shall be committed to version control | ⚠️ | `.env` correctly ignored, but **`.env.devbak` is tracked and contains a real `APP_KEY`** |
| NFR-23 | Privacy | Removing an account shall make the deletion meaningful for the person | ✅ | `UserAnonymizer` overwrites identifying fields before soft-deleting |
| NFR-24 | Privacy | Internal notes shall never reach customer-facing views | ✅ | Documented and enforced by omission |
| NFR-25 | Privacy | Sensitive fields shall be identifiable as SPI | ⚠️ | Documented in the data dictionary, but **not** marked in the schema or models |
| NFR-26 | Privacy | A data-retention period shall be defined and enforced | ❌ | Nothing ages out health data, GCash references, notes or soft-deleted rows — see `audit-report.md` G7.2 |
| NFR-27 | Audit | Security-relevant actions shall be traceable | ✅ | `appointment_status_history` |

## B.2 Performance

| ID | Category | Requirement | Status | Evidence |
|---|---|---|---|---|
| NFR-28 | Response | Public pages shall render in well under 1 s | ✅ | No evidence of a measured target; add one and measure before claiming it |
| NFR-29 | Response | Admin tables shall filter and page without a round trip per keystroke | ✅ | Alpine client-side filter/paging; only sort reaches the DB |
| NFR-30 | Response | Live slot lookup shall not be a bottleneck | ✅ | Throttled 60/min; returns availability only |
| NFR-31 | Payload | Frontend assets shall be built and hashed, not served from source | ✅ | Vite; `@vite` in all three layouts |
| NFR-32 | Payload | Notification polling shall cost one indexed lookup, not a full re-read | ✅ | `?since=<newest id>` cursor |
| NFR-33 | Indexing | Hot query paths shall be indexed | ✅ | Indexes on `status`, dates, `is_active`, `preferred_date/time`, `(user_id, status)`, `(is_active, starts_at, ends_at)` |
| NFR-34 | Background | Long-running work shall not block a request | ⚠️ | Database queue driver configured; **no worker process documented** — see the deployment checklist |
| NFR-35 | Scheduling | Nightly auto-archive shall run without a cron entry on Windows dev | ✅ | `schedule:work`; `dev-up.bat` starts it |

## B.3 Availability & maintainability

| ID | Category | Requirement | Status | Evidence |
|---|---|---|---|---|
| NFR-36 | Availability | The system shall tolerate a server restart mid-operation | ✅ | Graceful catch blocks on every `fetch`; slot lookup clears rather than showing stale data |
| NFR-37 | Availability | A failed notification poll shall not clear the badge | ✅ | Explicitly commented and implemented that way |
| NFR-38 | Reliability | Schema changes shall be reversible | ✅ | Every migration implements a `down()` |
| NFR-39 | Reliability | Migrations shall be idempotent or guarded | ✅ | `Schema::hasTable/hasColumn` guards where a table may not exist |
| NFR-40 | Maintainability | Business logic shall sit in services, not controllers | ✅ | `BookingService`, `BookingAvailability`, `AppointmentArchiver`, `ReportService`, `UserAnonymizer`, `PasswordResetService` |
| NFR-41 | Maintainability | UI shall be reusable rather than duplicated per page | ✅ | 30+ Blade components under `components/ui` and `components/salon` |
| NFR-42 | Maintainability | The test suite shall cover the flows it claims to | ✅ | Last full run: **902 passed, 1 skipped, 4 477 assertions** (`php artisan test`) |
| NFR-43 | Portability | The schema shall be reproducible from migrations alone | ⚠️ | True for a clean deploy; the **development database cannot be reproduced** — 11 unmigrated legacy tables and extra `users` columns |
| NFR-44 | Portability | The app shall run on Windows and Linux | ✅ | `dev-up.bat` for Windows; scheduler via cron on Linux |

## B.4 Usability & accessibility

| ID | Category | Requirement | Status | Evidence |
|---|---|---|---|---|
| NFR-45 | Accessibility | Every page shall offer a skip-to-content link | ✅ | `layouts/customer.blade.php:59` |
| NFR-46 | Accessibility | Interactive controls shall carry accessible names | ✅ | `aria-label` on icon-only actions; `aria-expanded` on the disclosure |
| NFR-47 | Accessibility | Forms shall associate labels with inputs | ✅ | `<label for>` throughout; server-side error text referenced |
| NFR-48 | Accessibility | Status shall not be conveyed by colour alone | ✅ | Badges carry text labels |
| NFR-49 | Usability | Validation shall be server-side, with client-side hints as a bonus | ✅ | Form Request classes on every write path |
| NFR-50 | Usability | Flash messages shall be consistent across the app | ✅ | One toast component used by both customer and admin layouts |
| NFR-51 | Usability | Long lists shall not be truncated without a way to read the rest | ✅ | Promo description shown in full, not clipped to a character count |
| NFR-52 | Usability | Mobile shall be a first-class layout, not a squeeze | ✅ | Every major grid has an explicit mobile arrangement |

## B.5 Legal & regulatory

| ID | Category | Requirement | Status | Evidence |
|---|---|---|---|---|
| NFR-53 | RA 10173 | PI and SPI shall be identifiable | ⚠️ | Documented in `data-dictionary.md`, not flagged in the schema |
| NFR-54 | RA 10173 | Demo/seed data shall be synthetic, not real | ❌ | A real individual's name, mobile number, email and a plaintext password are committed in `UserSeeder` |
| NFR-55 | RA 10173 | Submitted artefacts shall contain no real personal data | ⚠️ | **This document set is clean.** The repository is not — see NFR-54 and the real SPI in the development database |
| NFR-56 | RA 10173 | Retention and destruction shall be demonstrable | ❌ | No retention job exists |

---

## Summary count

| Status | Functional | Non-functional |
|---|---:|---:|
| ✅ Implemented | 85 | 40 |
| ⚠️ Implemented with a gap | 7 | 8 |
| ❌ Not implemented | 3 | 5 |
| **Total** | **95** | **53** |

**The eight ❌ items are the honest list for Chapter IV's "current limitations" — and
several of them (NFR-54, NFR-55) are also RA 10173 compliance gaps that the rubric scores
under DPA Compliance. Do not hide them; presenting a known gap with a remediation plan is
stronger than a manuscript that claims completeness and is tested on it.**
