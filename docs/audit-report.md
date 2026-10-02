# Code Audit Report — Salon Booking & Inventory System

**System under audit:** Laravel booking and inventory system for a nail salon
**Framework:** Laravel 11.56.1 · PHP 8.3.33 · MySQL (InnoDB, utf8mb4_unicode_ci)
**Audit date:** October 2, 2026
**Auditor scope:** static code review, live-schema inspection, and a fresh-database
`migrate --seed` rehearsal. No changes were made to the application.
**Regulatory frame:** RA 10173 (Data Privacy Act of 2012), AIAS Level 3.

> **No real personal data appears in this document.** Where a finding concerns a real
> name, mobile number, email address or health record, the value is described by location
> only and deliberately not reproduced.

---

## Summary

| # | Area | Result |
|---|---|---|
| 1 | SQL injection safety | **PASS** |
| 2 | Password hashing | **PASS** |
| 3 | CSRF | **PASS** |
| 4 | XSS & upload safety | **PASS** |
| 5 | Authorization, IDOR, rate limiting | **PARTIAL** — IDOR sound; 2 gaps |
| 6 | Session & configuration | **PARTIAL** — 3 gaps |
| 7 | Data privacy (PI/SPI inventory) | **PARTIAL** — inventory below; masking gaps |
| 8 | Seed & demo data | **FAIL** — real PI committed; seeders silently no-op |

Areas 1–4 are in good shape and are the strongest part of the codebase: raw SQL is either
static or allow-listed, hashing is bcrypt throughout, CSRF is complete on both form and
AJAX paths, and the one place user-authored HTML is rendered raw is fronted by a real
allow-list sanitiser.

Areas 5–8 are where the work is. The most serious finding is **not** in the application's
runtime logic at all — it is that **a real individual's personal information and a
plaintext password are committed to version control**, and the live development database
contains real health data. That collides directly with §3.1 of the instruction sheet.

---

## 1. SQL injection safety — PASS

**Method:** searched the whole of `app/`, `routes/` and `database/` for `DB::raw`,
`selectRaw`, `whereRaw`, `havingRaw`, `orderByRaw`, `groupByRaw`, `DB::statement`,
`DB::select`, `DB::insert`, `unprepared`, and for `where`/`join`/`orderBy` calls whose
first argument interpolates a variable.

### 1.1 Every raw query found, and why it is safe

| Location | Expression | Verdict |
|---|---|---|
| `app/Support/DataTable.php:33-42` | `DataTable::sort()` — reads `?sort=` / `?direction=` from the request | **Safe.** Strict `in_array(..., true)` against a per-screen allow-list; anything unrecognised falls back to the screen's default column. |
| `app/Support/DataTable.php:50-62` | `applySort()` — places that value into `orderBy()` | **Safe.** Re-checks the allow-list a second time before it reaches `orderBy()`, so it cannot be called with an unvalidated column. |
| `app/Http/Controllers/Admin/AppointmentController.php:91` | `selectRaw('status, COUNT(*) as total')` | **Safe.** Static SQL fragment, no bound values. |
| `app/Http/Controllers/Customer/AppointmentController.php:91` | same | **Safe.** Static. |
| `app/Services/ReportService.php:108` | same | **Safe.** Static. |
| `app/Http/Controllers/Admin/CatalogController.php:32` | `whereRaw('1 = 0')` | **Safe.** Constant; a deliberate "match nothing" for an unsupported catalogue type. |
| `app/Models/ServiceCategory.php:114` | `whereRaw("photo_path <> 'NULL'")` | **Safe.** Static; guards against a literal four-character string left by a hand-edited query. |
| `database/migrations/2026_10_01_000100_split_service_price_into_display_and_base.php:114` | `DB::raw('price')` | **Safe.** Column name literal in a data migration. |
| `database/migrations/2026_10_02_000200_repair_legacy_down_payment_status.php:102,126,161` | `DB::selectOne(...)`, `DB::statement("ALTER TABLE … '{$target}'")` | **Safe.** `$target` is a hard-coded enum case value (`DownPaymentStatus::NotRequired->value`), never user input. |

No `DB::unprepared()` call exists anywhere in the project. No string-concatenated
`where`/`join`/`orderBy` was found.

**This is worth showing in Chapter IV** — the `DataTable` allow-list is exactly the
"parameterized queries protecting databases against SQL injections" evidence §3.1 asks
for, and the double-check on line 57 is a good viva answer.

### 1.2 Everything else

All other data access goes through Eloquent or the query builder with bound parameters.

---

## 2. Password hashing — PASS

**Method:** searched for `Hash::make`, `Hash::check`, `bcrypt`, `password_hash`, the
`hashed` cast, and any direct write to a `password` column.

| Location | Purpose |
|---|---|
| `app/Models/User.php:39` | `'password' => 'hashed'` cast |
| `app/Models/Admin.php:58` | `'password' => 'hashed'` cast |
| `app/Http/Controllers/Auth/RegisteredUserController.php:38` | `Hash::make()` on registration |
| `app/Http/Controllers/Customer/ProfileController.php` (profile update) | `Hash::make()` on password change |
| `app/Http/Controllers/Admin/ProfileController.php:48` | `Hash::make()` on admin password change |
| `app/Services/PasswordResetService.php:44,94` | Hashes the reset code and the new password |
| `app/Services/PasswordResetService.php:68` | `Hash::check()` on the reset code |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php:46` | `Hash::check()` on login |
| `app/Http/Controllers/Admin/Auth/AdminSessionController.php:59` | `Hash::check()` on admin login |

- Algorithm: `config/hashing.php` is **not published**, so the framework default applies
  (bcrypt). `.env.example:17` sets `BCRYPT_ROUNDS=12`.
- The `hashed` cast means a plaintext value handed to the model is hashed on the way in,
  which is why `UserSeeder` can pass a plaintext string safely. No plaintext password is
  stored in any column.
- **The six-digit reset code is also bcrypt-hashed** (`password_reset_codes.code_hash`) —
  a good detail for the viva, because it means a database dump does not yield usable
  reset codes.
- Password policy is server-side: `min:8` + `confirmed` on registration, customer profile
  update, admin profile update and both reset paths.

---

## 3. CSRF — PASS

### 3.1 Middleware

`bootstrap/app.php` contains **no** `validateCsrfTokens(except: [...])` call and no CSRF
exclusion of any kind, so the framework default applies to the whole `web` group: every
`POST`/`PUT`/`PATCH`/`DELETE` is verified. There are **no excluded routes to justify**.

`bootstrap/app.php:31-38` aliases six application middleware. The comment at lines 22-30
records a deliberate safety decision: `PreventAuthenticatedCaching` is **not** registered
as a group named `auth`, because `MiddlewareNameResolver` resolves groups before aliases
and a same-named group would silently shadow the `auth` alias and un-guard every protected
route. That is a subtle, correct piece of engineering.

### 3.2 Forms

39 Blade files contain a `<form>`. Every form that uses a state-changing verb has `@csrf`:

- The three component-rendered action forms carry their own token:
  `resources/views/components/ui/icon-action.blade.php:26-28`,
  `components/ui/confirm-dialog.blade.php`, `components/ui/table-toggle.blade.php`.
- Four files contain a `<form>` with no `@csrf`, and all four are **`method="GET"` filter
  forms** — CSRF does not apply to safe methods, so this is correct, not a gap:
  `admin/appointments/archived.blade.php:37`, `admin/messages/index.blade.php:55`,
  `admin/reports/index.blade.php:82`, `services/refined.blade.php:29`.
- `@method('PUT'|'PATCH'|'DELETE')` is present on the matching forms, so verb spoofing is
  done through the framework rather than by exposing hidden-method endpoints.

### 3.3 AJAX

The `<meta name="csrf-token">` tag is present in all three layouts
(`layouts/customer.blade.php:6`, `layouts/admin.blade.php:6`, `layouts/auth.blade.php:6`).

`resources/js/app.js` contains 12 `fetch()` calls. Every state-changing one sends
`X-CSRF-TOKEN` — lines 864 (`PATCH` appointment status), 1071 and 1119 (`PATCH` message
read / read-all), 1649, 1832 (`PATCH`) and 1882 (`POST`). The five calls that do **not**
send it are all default-method `GET` reads: the session liveness probe (236), the
notification feed poll (1568), the admin unread-count poll (1905) and the booking slot
lookup (2206). This split is correct.

---

## 4. XSS and upload safety — PASS

### 4.1 Every `{!! !!}` in the project (10 total)

| Location | Content | Verdict |
|---|---|---|
| `resources/views/customer/terms/show.blade.php:45` | `{!! TermsRenderer::numbered($terms->content) !!}` | **Safe** — see §4.2 |
| `resources/views/components/terms/modal.blade.php:40` | same renderer, injected into Alpine `x-html` | **Safe** — see §4.2 |
| `admin/terms/index.blade.php:88` | `{!! $latest->content !!}` (admin preview) | **Acceptable** — same trust boundary as the edit form it previews |
| `admin/terms/_form.blade.php:165`, `customer/appointments/create.blade.php:874`, `reschedule.blade.php:58` | `json_encode(..., JSON_HEX_TAG\|JSON_HEX_AMP\|JSON_HEX_APOS\|JSON_HEX_QUOT)` inside `<script type="application/json">` or an Alpine object | **Safe** — `</script>` and quotes cannot be broken out of |
| `components/salon/service-choice.blade.php:71` | `{!! $reveal !!}` — an Alpine attribute string assembled in PHP with `Js::from()` on a category slug | **Safe** — `Js::from` emits single-quoted, hex-escaped output |
| `components/ui/count-badge.blade.php:54` | `{!! $extra !!}`, `{!! $live ? '' : $count !!}` | **Safe** — `$count` is an integer count; `$extra` is developer-authored markup |
| `layouts/auth.blade.php:88,90`, `partials/admin/sidebar.blade.php:163` | layout slots / static JS snippet | **Safe** — not user input |

### 4.2 The one real raw-HTML sink, and why it is sound

Terms & Conditions content is authored in an admin rich-text editor and rendered as
**markup**, which is the intended behaviour — the customer must see the bold in "up to 24
hours", not literal tags. `app/Support/TermsRenderer.php` is a genuine allow-list
sanitiser:

- `ALLOWED_TAGS` is exactly the set the editor toolbar can produce (`p, br, hr, h2, h3,
  ul, ol, li, strong, b, em, i, u, a, blockquote`).
- `script`, `style`, `iframe`, `object`, `embed` are removed **with their content**
  first, so a `<script>` body is never unwrapped into visible text.
- Any other tag is unwrapped (its text survives, the tag does not).
- Only `a` may keep attributes, limited to `href, title, target, rel`.
- `href` values matching `javascript:`, `data:` or `vbscript:` are dropped.
- Surviving attribute values are escaped with `e()`.

The sanitiser runs **server-side before the value reaches the browser**
(`components/terms/modal.blade.php:40` calls `TermsRenderer::numbered()`, then `x-html`
injects the already-sanitised string). There is no client-side bypass path.

### 4.3 User-supplied text fields

Names, notes, messages, promo descriptions, cancellation reasons and admin replies are all
output with `{{ }}` (auto-escaped). No `v-html` exists. The only `innerHTML` writes are:

- `components/ui/toast-stack.blade.php:118` — a **static** template string; the message is
  then set with `textContent` at line 130, with a comment noting that some messages are
  customer-supplied block reasons.
- `admin/terms/_form.blade.php:173,179,211,216` — the editor's own `contenteditable`
  round-trip, admin-only.

### 4.4 File uploads

Four upload paths. All four validate mime **and** size, and all use Laravel's `store()`,
which generates a random hashed filename — the client never controls the stored name or
extension.

| Path | Validation | Storage |
|---|---|---|
| Promo image | `image`, `mimes:jpg,jpeg,png,webp`, `max:4096` — `Admin/PromoController.php:173` | `promos/` on `public` disk |
| Service category photo | `image`, `mimes:jpg,jpeg,png,webp`, `max:4096` — `Admin/ServiceCategoryController.php:191` | `categories/` |
| Technician photo | `image`, `mimes:jpg,jpeg,png,webp`, `max:2048` — `Requests/Admin/TechnicianRequest.php:31` | `technician-photos/` |
| Customer profile photo | `image`, `mimes:jpg,jpeg,png,webp`, `max:10240` — `Requests/Customer/UpdateProfileRequest.php:36` | `profile-photos/` |

Only image extensions are accepted, uploads land on the `public` disk behind
`storage:link` (served as static files, never executed as PHP), and each of the three
admin-owned paths deletes the superseded file from disk.

**Minor observation:** the profile photo ceiling (10 MB) is five times the others
(2–4 MB). Not a vulnerability, but worth a one-line justification in the viva.

---

## 5. Authorization, IDOR and rate limiting — PARTIAL

### 5.1 Route protection — PASS

- **Customer area:** `routes/web.php:140` wraps everything in
  `['auth.customer', 'user.active', 'no.store']`. Booking, appointments, cancel,
  reschedule, notifications, profile.
- **Admin area:** `routes/web.php:308` wraps everything in `['auth.admin', 'no.store']`,
  and every screen additionally carries `admin.role:<ability>`.
- Two separate guards with separate providers (`config/auth.php:38-49`): `web` → `users`,
  `admin` → `admins`. An admin session and a customer session can never be confused, and
  `AppServiceProvider:41-45` reads the `admin` guard explicitly rather than the default.
- Deliberately public: `GET /book/slots` (`routes/web.php:235`) so availability can be
  shown before sign-in, and `GET /session-status` (`118`). Both are read-only, the latter
  returns only which guard is active, and the code comments say why.

### 5.2 IDOR — PASS

Customer-side resource routes use route-model binding, so the ownership question has to be
answered in the controller. It is, in all three controllers that take an appointment:

- `app/Http/Controllers/Customer/AppointmentController.php:507`
- `app/Http/Controllers/Customer/CancelAppointmentController.php:106`
- `app/Http/Controllers/Customer/RescheduleAppointmentController.php:92`

Each is an identical `abort_unless($appointment->user_id === auth()->id(), 403)` and each
is called before any state change. `destroyMany` scopes through
`$request->user()->appointments()` (`AppointmentController.php:251`), and the
notifications controller reads exclusively through `$request->user()->notifications()`.

**No `app/Policies` directory exists.** Authorisation is enforced by explicit controller
checks plus Gates rather than policies. Functionally equivalent; if an instructor asks
"where are your policies?", the honest answer is "we use explicit ownership assertions and
a Gate-driven ability table instead" — which is defensible, but be ready to say it.

### 5.3 Rate limiting — PARTIAL

| Route | Limit |
|---|---|
| Customer login `POST /login` (`:78`) | `throttle:10,1` |
| Admin login `POST /admin/login` (`:250`) | `throttle:10,1` |
| Customer password email (`:84`) | `throttle:6,1` |
| Customer code verify (`:86`) | `throttle:12,1` |
| Admin password email (`:272`) | `throttle:3,1` |
| Contact message (`:59`) | `throttle:12,1` |
| Session probe (`:119`) | `throttle:120,1` |
| Notification feeds (`:207`, `:551`) | `throttle:120,1` |
| Slot lookup (`:236`) | `throttle:60,1` |

Both login controllers additionally implement their own per-account attempt counter with
`RateLimiter::hit/tooManyAttempts` (`Auth/AuthenticatedSessionController.php`,
`Admin/Auth/AdminSessionController.php:142-155`), which is stronger than route throttling
alone because it keys on the account rather than the IP.

**Two gaps:**

- **G5.1 — booking submission is unthrottled.** `POST /book` (`routes/web.php:145`) has no
  `throttle` middleware. An authenticated script can create appointments without limit.
  The GET slot lookup beside it is limited to 60/min, so the asymmetry looks like an
  oversight rather than a decision.
- **G5.2 — registration is unthrottled.** `POST /register` (`routes/web.php:73`) has no
  throttle, so account creation is unbounded on a public deployment.

### 5.4 Role model mismatch — FAIL (documentation, not enforcement)

`app/Enums/AdminRole.php` contains a **single** case, `Admin = 'admin'`, and
`abilitiesFor()` returns every ability for it. Migration
`2026_09_27_000000_collapse_admin_roles_to_admin.php` narrowed the column to `enum('admin')`
to match.

However the route comments at `routes/web.php:289-293` still describe the removed
three-tier model: *"Super Admin holds everything, Manager runs the salon but cannot edit
legal text or destroy customer records, and Staff get the appointment queue plus read-only
reference screens."* No Manager or Staff tier exists.

**Consequences for the manuscript:**
1. The RBAC matrix in Chapter IV must show **one** admin role, not three. Drawing a
   three-tier matrix would be a factual error.
2. These comments are a viva risk — an instructor reading "Manager cannot edit legal
   text" and asking to see that role will find nothing.

Related: `POST/DELETE /admin/users/{user}` is gated on `admin.users.view`
(`routes/web.php:477-482`) rather than a `users.manage` ability, so the ability table has
no delete entry. The comment explains the intent (one ability per screen), and with a
single role holding everything it currently grants nothing extra — but if a second role
is ever added, "view" would silently include "delete".

---

## 6. Session and configuration — PARTIAL

### 6.1 Secrets

- `.env` is ignored (`.gitignore:9`) and confirmed untracked. `.env` appears nowhere in
  git history.
- **G6.1 — `.env.devbak` is tracked in git and contains a real `APP_KEY`.**
  `.gitignore:9` lists `.env.backup` but not `.env.devbak`. The committed file carries a
  51-character `APP_KEY` (base64), which is the key that signs session cookies and encrypts
  any encrypted model casts. `DB_PASSWORD` in that file is empty, so the blast radius today
  is limited — but if the same key is used on the deployed instance, cookie/session forgery
  becomes possible. Fix: untrack the file, add it to `.gitignore`, and rotate `APP_KEY`.
- **G6.2 — `.env.example` commits a real email address.** Lines 93 and 95 carry the salon's
  live Gmail address as `MAIL_USERNAME` and `MAIL_FROM_ADDRESS`. §3.1 requires that real
  identifiers be replaced with synthetic or masked values in anything submitted, and the
  example file is the one file every reviewer opens first.

### 6.2 Debug flag

`config/app.php:42` defaults `debug` to `false`, and `config/app.php:29` defaults `env` to
`production` — both safe. But `.env.example:2,4` set `APP_ENV=local` and `APP_DEBUG=true`,
and there is no production override documented anywhere.

**G6.3 —** if the deployment copies `.env.example` and does not change these two lines, the
site runs publicly with `APP_DEBUG=true`, which exposes stack traces, environment values and
file paths. For a submission that must be deployed online, this is the single most likely
misconfiguration. It is the first item on the deployment checklist for that reason.

### 6.3 Session cookie

| Setting | Value | Source |
|---|---|---|
| `http_only` | `true` | `config/session.php:185` (default) |
| `same_site` | `lax` | `config/session.php:202` (default) |
| `secure` | **unset** | `config/session.php:172` reads `SESSION_SECURE_COOKIE`, which is absent from `.env.example`, so it resolves to `null` → cookie sent over plain HTTP too |
| `encrypt` | `false` | `.env.example:33` |
| driver / lifetime | `database` / `120` min | `.env.example:31-32` |

**G6.4 —** `SESSION_SECURE_COOKIE` should be set to `true` on the HTTPS deployment. Low
severity on its own; relevant mainly if the site is ever served over HTTP.

**G6.5 —** `SESSION_ENCRYPT=false` means the `sessions.payload` column in the database
stores session data unencrypted. The mitigation is real (the payload is server-side only,
and `sessions.ip_address` / `user_agent` are the only PI in that table), so this is a
judgement call rather than a defect — but it should be a conscious one, and stated as one.

---

## 7. Data privacy (RA 10173) — inventory below, masking gaps

### 7.1 PI and SPI collected

Classification: **PI** = personal information; **SPI** = sensitive personal information
(health, genetic/biological, and specifically-identified credentials).

#### `users` — customer accounts

| Column | Class | Needed? | Used by |
|---|---|---|---|
| `first_name`, `last_name` | PI | Yes | Greeting the customer, addressing them on an appointment, reports |
| `email` | PI (identifier) | Yes | Login, notifications, password reset |
| `username` | PI (identifier) | Yes | Login; derived from email at registration |
| `contact_number` | PI | Yes | Appointment reminders; salon contacting the customer |
| `password` | **SPI** (credential) | Yes | Authentication — stored bcrypt-hashed |
| `profile_photo_path` | **SPI** (biometric-adjacent image) | Optional | Avatar only |
| `is_active`, `last_login_at`, `remember_token`, timestamps, `deleted_at` | PI (behavioural) | Yes | Access control, audit |

#### `appointments` — the densest PI/SPI table

| Column | Class | Needed? | Used by |
|---|---|---|---|
| `customer_name` | PI | Yes | Salon reads it at the chair |
| `customer_phone` | PI | Yes | Confirming and rescheduling |
| `customer_email` | PI | Yes | Confirmation mail |
| `allergies` | **SPI — health** | **Yes** | The single most safety-relevant field in the system; a nail salon must know about latex/nickel reactions |
| `last_services_avail` | **SPI — health/history** | Yes | Service continuity |
| `special_request` | **SPI — may contain health info** | Yes | Free-text customer instructions |
| `admin_notes` | PI + possible SPI | Yes | Internal; explicitly never rendered customer-facing (see the comment at migration line 187) |
| `down_payment_reference` | **SPI — financial** | Yes | Manual GCash reference verification; no gateway is integrated |
| `down_payment_amount`, `total_amount` | **SPI — financial** | Yes | Revenue reports |
| `user_id`, `technician_id`, `preferred_stylist_id`, `archived_by` | PI (linkage) | Yes | Ownership and audit |
| `cancellation_reason`, `reschedule_reason` | PI | Yes | History and reporting |
| `status` + status timestamps, `source`, `archived_at`, `admin_seen_at` | PI (behavioural) | Yes | Workflow and SLA tracking |

#### Other tables

| Table / column | Class | Notes |
|---|---|---|
| `contact_messages.name`, `.email`, `.message` | PI; `.message` may carry SPI | Enquiry inbox; `.admin_reply` is staff-authored |
| `password_reset_codes.email`, `.ip_address`, `.attempts` | PI | Email plus network identifier; code itself is hashed |
| `sessions.ip_address`, `.user_agent` | PI | Laravel's database session driver |
| `admins.first_name`, `.last_name`, `.username`, `.email`, `.password`, `.profile_photo_path`, `.last_login_at` | PI + SPI | Staff accounts — §3.1 explicitly prohibits displaying **actual staff personnel accounts** |
| `technicians.name`, `.photo_path` | PI | Staff-adjacent; the booking form lists them |
| `appointment_status_history.changed_by_name`, `.note` | PI + possible SPI | Audit trail |

**Assessment:** the collection is proportionate. Every field above has a stated operational
purpose, and nothing is collected that the booking process does not need — no ID numbers,
no nationality, no payment-card data, no biometrics beyond an optional avatar. Health data
is limited to what a treatment provider genuinely requires.

### 7.2 Privacy-by-design features present — good

- `app/Services/UserAnonymizer.php` implements **anonymise-then-soft-delete** on account
  removal: identifying fields are overwritten *before* the row is marked deleted, so there
  is no window where a removed account still carries a live email address. The appointment
  rows are deliberately left intact because they are the salon's business records. This is
  a genuinely good answer for Chapter I's PTA section.
- `appointment_status_history` gives an immutable audit trail of who changed what.
- `admin_notes` is documented as never rendered on customer-facing views.
- Authenticated pages set `no-store` cache headers so a logged-out browser cannot re-read
  them from the back/forward cache.

### 7.3 Gaps

- **G7.1 — real SPI is stored in the live development database.** The development MySQL
  database contains 9 appointment rows carrying one real individual's name, mobile number,
  email address and **allergy/health text** (latex, nickel and other sensitivities).
  §3.1 requires that such records never be displayed and that gathered data be purged after
  final grading. This data is the developer's own test data, but it is real health
  information sitting in an unencrypted local database.
- **G7.2 — no retention policy is implemented.** `UserAnonymizer` handles deletion on
  request, but nothing ages out `allergies`, `special_request`, `down_payment_reference`,
  `admin_notes` or soft-deleted rows. RA 10173 §11 requires a reasonable retention period.
  Nothing in the code states or enforces one.
- **G7.3 — the developer's real credentials are in the seed data.** See §8.

---

## 8. Seed and demo data — FAIL

This is the weakest area and the one with the most direct RA 10173 exposure.

### 8.1 G8.1 — real personal information committed to the repository (HIGH)

`database/seeders/UserSeeder.php:45-56` hardcodes a single account whose `first_name`,
`last_name`, `username`, `email`, `contact_number` and `password` are a **real individual's
details**, including a genuine Philippine mobile number in the `09xx` range. The values are
deliberately not reproduced here.

The seeder's own comment describes this account as "the developer's own, so there is
always something to sign in as". That is a reasonable local-development convenience, but it
creates three problems:

1. **The spec.** §3.1 forbids displaying "real citizen identifying markers, phone
   registries, actual staff personnel accounts" in submitted material, and requires
   pseudonyms such as `Client Delta` in its place.
2. **Deployment.** The submission requires "a deployed and working online application". If
   the deployed instance is seeded, a publicly reachable site ships with a real person's
   name, mobile number, email address **and a known plaintext password**.
3. **The repository.** The repo is meant to be private with the instructor as collaborator,
   so this is contained — but it is one mis-set visibility flag away from being public.

### 8.2 G8.2 — business contact details in `config/salon.php` and `.env.example`

`config/salon.php` carries the salon's real street address, mobile number and email, and
`.env.example:93,95` carries the live Gmail address. A business's own public contact
details are a defensible exception — the address and phone are published on the About page
and in the footer by design — but the spec's blanket wording ("do not use actual names,
addresses and other personal data") makes this a decision to make deliberately rather than
by omission. Flagged for your judgement, not asserted as a defect.

### 8.3 G8.3 — the AI interaction log itself contains real PI (HIGH)

`prompt.log` and `PROMPT_LOG.md` are **both tracked in git** (neither is in `.gitignore`),
and both contain real personal data in the recorded prompt text — a real individual's full
name, Gmail address and Philippine mobile number appear verbatim in 5 and 3 lines
respectively.

This matters more than a normal code finding, because §3.2 of the instruction sheet requires
you to submit the AI interaction log as **transparency evidence**. The artefact you are
required to hand in is therefore itself a RA 10173 exposure: submitting it unredacted would
publish a real person's name, email address and phone number inside the documentation that
is supposed to demonstrate compliance with the law forbidding exactly that.

**Mask before you submit.** Replace the identifying strings with placeholders such as
`[DEVELOPER EMAIL REDACTED]`, `[DEVELOPER MOBILE REDACTED]`, `[NAME REDACTED]`, and keep the
technical content — the prompts are what the rubric wants, not the identifiers.

Note the instruction sheet also requires the repository to be **private**, which contains
this file, so the exposure is bounded — but a private repo is not the same as a compliant
document.

### 8.4 (no defect) — synthetic data that is correctly synthetic

- `AdminSeeder.php:24,54` — `.test` addresses (RFC 2606 reserved TLD) and a `+63 900 000 0000`
  placeholder number.
- `AppointmentSeeder.php:36-50` and `ContentSeeder.php:72-86` — all `*@example.test`.
- `README.md` demo account tables — all `.test` addresses.
- No real customer names appear in any seeder other than §8.1.

### 8.5 G8.4 — the seeders silently no-op, and the README describes a system that no longer exists (HIGH)

I ran `php artisan migrate --seed` against a throwaway database and counted the result.
The README's "Demo accounts" and "Seeded content" sections do not match it.

| README claim | Actual on a fresh seed |
|---|---|
| 5 admins: `admin`, `manager`, `jade`, `marco`, `aling` | **1** — `admin` only |
| 7 customers: `juan`, `maria`, `angeline`, `paolo`, `kristine`, `diego`, `inactive` | **1** — the developer's account |
| 19 services, 24 variants | 19 / 24 — correct |
| 27 inventory items linked to services | **0 / 0** |
| 15 appointments across every status | **0** |
| 6 customer reviews | table dropped (`2026_09_30_000000_drop_reviews_table.php`) |
| 4 blocked dates | table dropped (`2026_10_01_000200_drop_blocked_dates_table.php`) |
| 3 promos, 6 T&C, 3 contact messages | 3 / 6 / 3 — correct |
| (not mentioned) technicians | **0** |

Two independent causes:

1. **`AppointmentSeeder.php:57-61`** looks each planned booking up by a hard-coded
   `*@example.test` email and `continue`s when no such user exists. Since `UserSeeder` now
   creates only one different account, **all 15 planned appointments are skipped with no
   error**. Its own docblock claims it "picks its customers from the active users" — that
   is not what the code does, and the comment is wrong.
2. **No seeder creates `InventoryItem` or `Technician` rows at all.** `grep` for
   `InventoryItem` and `Technician` across `database/seeders/` returns nothing. The admin
   Inventory screen, the low-stock tagging screen and the booking form's technician picker
   are therefore empty on a freshly seeded deployment.

**Why this matters for grading:** "Repository and Project Deployment" is **30%** — the
largest single criterion. An instructor who follows the README, runs `migrate --seed` and
tries to sign in as `manager` or `juan` gets a failed login, and lands on an appointment
queue, inventory list and technician list that are all empty. Two options, your call:
restore fictional seed data for the demo accounts (and add inventory/technician seeders),
or rewrite the README to describe the single seeded account honestly.

---

## Appendix A — Schema drift between the dev database and the migrations

Not a compliance finding, but it affects Chapter IV's migration and cutover section.

A fresh `php artisan migrate` creates **24** tables. The development database contains
**35**. The extra 11 are legacy tables with **no migration at all**:

`activity_logs`, `appointment_items`, `cart_items`, `carts`, `categories`,
`homepage_settings`, `inventory_logs`, `otp_codes`, `packages`, `staff_members`,
`user_notifications`

Only one piece of code refers to them: `app/Console/Commands/ClearUsersCommand.php:50`,
whose `DETACH` list is correctly guarded by `Schema::hasTable()` at line 106 and documented
at lines 46-49, so it degrades cleanly on a database that never had them.

The `users` table has also drifted: the development copy carries legacy `name`, `phone`,
`role` and `email_verified_at` columns that no migration creates, and its
`first_name`/`last_name` are nullable where the migration declares them `NOT NULL`.

**Implication:** the development database cannot be reproduced from the migrations, and a
fresh deployment will not look like the development one. Worth stating plainly in the
cutover strategy rather than discovering it on deploy day.

---

## Appendix B — Verified-by-execution summary

Commands run during this audit, and what each established:

| Command | Result |
|---|---|
| `php artisan migrate` on a throwaway SQLite file | Succeeded; 24 tables — establishes the authoritative schema |
| `php artisan db:seed` on that database | Succeeded; row counts in §8.4 |
| `php artisan migrate:status` (dev database) | 21 migrations, none pending |
| Information-schema query (dev database) | 35 tables — establishes the drift in Appendix A |
| `php artisan test` | Not re-run; last full run in this session: 902 passed, 1 skipped |
