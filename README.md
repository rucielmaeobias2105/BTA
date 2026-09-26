# Balai ti Arjud — Online Appointment & Inventory Management System

A production-ready web application for **Balai ti Arjud — Glow & Co. Beauty Lounge**, a
salon/spa offering online appointment booking plus inventory, staff, review, promo and
reporting management.

Built with **Laravel 11 (LTS)**, **MySQL**, **Blade + Tailwind CSS 3** and **Alpine.js**.

---

## Table of contents

- [Requirements](#requirements)
- [Setup](#setup)
- [Demo accounts](#demo-accounts)
- [Running the app](#running-the-app)
- [Running the tests](#running-the-tests)
- [Feature map](#feature-map)
- [Architecture](#architecture)
- [Design system](#design-system)
- [Data model](#data-model)
- [Key behaviours](#key-behaviours)
- [Prompt logging](#prompt-logging)
- [Environment notes](#environment-notes)

---

## Requirements

| Tool | Version |
| --- | --- |
| PHP | 8.2+ (tested on 8.3) with `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd` |
| Composer | 2.x |
| MySQL | 5.7+ / 8.x (MariaDB 10.4+ works) |
| Node.js | 18+ (tested on 24) |
| npm | 9+ |

---

## Setup

```bash
# 1. Install PHP dependencies
composer install

# 2. Install frontend dependencies
npm install

# 3. Create the environment file
cp .env.example .env

# 4. Generate an application key
php artisan key:generate

# 5. Create the database (MySQL)
mysql -u root -e "CREATE DATABASE bta CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 6. Point .env at your database
#    DB_CONNECTION=mysql
#    DB_HOST=127.0.0.1
#    DB_PORT=3306
#    DB_DATABASE=bta
#    DB_USERNAME=root
#    DB_PASSWORD=

# 7. Create the schema and load demo data
php artisan migrate --seed

# 8. Expose uploaded files (profile photos, service photos, promo images)
php artisan storage:link

# 9. Build the frontend assets
npm run build
```

For local development with hot reloading, run `npm run dev` in a second terminal
instead of `npm run build`.

> **XAMPP users:** MySQL runs on port `3306` by default and the root password is
> usually empty — that is what `.env.example` ships with.

---

## Demo accounts

`php artisan migrate --seed` creates a fully populated demo environment.

### Admin (staff portal — `/admin/login`)

| Username | Email | Password | Role |
| --- | --- | --- | --- |
| `admin` | admin@balaitiarjud.test | `password` | Super Admin |
| `manager` | manager@balaitiarjud.test | `password` | Manager |
| `jade` | jade@balaitiarjud.test | `password` | Staff / therapist |
| `marco` | marco@balaitiarjud.test | `password` | Staff / therapist |
| `aling` | aling@balaitiarjud.test | `password` | Staff / therapist |

### Customers

| Email | Password | Notes |
| --- | --- | --- |
| juan@example.test | `password` | Active account with booking history |
| maria@example.test | `password` | Active account |
| angeline@example.test | `password` | Active account |
| paolo@example.test | `password` | Active account |
| kristine@example.test | `password` | Active account |
| diego@example.test | `password` | Active account |
| inactive@example.test | `password` | **Deactivated** — demonstrates the admin deactivate flow |

Customers can sign in with either their **email or username** (e.g. `juan`).

### Seeded content

19 services (with 24 short/long-hair style variants), 27 inventory items linked to the
services that consume them, 15 appointments spread across every status, 6 customer
reviews, 3 promos (active, upcoming, expired), 6 versioned Terms & Conditions, 4 blocked
dates and 3 contact messages — enough to exercise every screen and report.

---

## Running the app

```bash
php artisan serve
```

- Customer site: <http://localhost:8000>
- Admin panel: <http://localhost:8000/admin/login>

Customer and admin sessions are **completely isolated** — they use separate guards
(`web`/`users` and `admin`/`admins`), separate tables and separate session state. Being
logged in as a customer grants no access to `/admin`, and admin credentials are rejected
by the customer login form. This is enforced by `auth.admin` middleware and covered by
tests in `tests/Feature/LoginTest.php`.

---

## Running the tests

```bash
php artisan test
```

**100 tests, 338 assertions**, all passing. The suite defaults to an **in-memory SQLite**
database (see `phpunit.xml`) so it needs no database server and is fully isolated.

To run the same suite against MySQL, override the connection in the environment:

```bash
# Linux / macOS
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=bta_test \
  php artisan test

# Windows (PowerShell)
$env:DB_CONNECTION="mysql"; $env:DB_DATABASE="bta_test"; php artisan test
```

The full suite has been verified green on **both** SQLite and MySQL/MariaDB.

### Test coverage

| Suite | Tests | Focus |
| --- | --- | --- |
| `RegistrationTest` | 12 | Registration screen, successful signup, hashed password, derived username, unique + valid email, min-8 password, confirmation match, terms acceptance, welcome notification |
| `LoginTest` | 15 | Email **and** username login, case-insensitivity, wrong password, unknown account, **rate limiting**, deactivated accounts, logout, guest redirects, **admin/customer guard isolation** |
| `BookingCreationTest` | 21 | Booking creation, guest bookings, reference format, price snapshotting, variant pricing, multi-service totals, every validation rule, operating hours, admin-blocked dates, slot collisions, allergy merging, slot-lookup endpoint |
| `AppointmentStatusUpdateTest` | 17 | Approve / decline / advance status, status history with `from` → `to` and actor, internal-only admin notes, manual GCash verification, authorization, customer cancellation |
| `LowStockTaggingTest` | 20 | The `quantity <= threshold` rule, sold-out derivation, Best Seller preservation, manual-override detection, single + bulk tagging, **auto-flagging when a service is booked**, stock restored on cancellation |
| `PublicPagesTest` | 15 | Landing, both service views, filters, contact, and the full 4-step password reset (hash-only storage, no account enumeration, single-use codes) |

### HTTP smoke tests

Two end-to-end scripts drive a real dev server with a cookie jar:

```bash
php artisan serve &                       # in another terminal
php storage/app/tmp/smoke.php             # 33 read-path assertions
php storage/app/tmp/write_smoke.php       # 16 write-path assertions
```

`smoke.php` walks the public pages, logs in as a customer, checks every
customer-authenticated page, then logs in as an admin **in the same browser** to prove
the guards are isolated, then requests all 21 admin pages. `write_smoke.php` exercises
service/variant creation, item creation, date blocking, T&C versioning, promo creation,
CSV export, registration, booking and cancellation.

---

## Feature map

### Customer side (15 flows)

| # | Flow | Route |
| --- | --- | --- |
| 1 | Register | `GET/POST /register` |
| 2 | Login (username or email, rate limited) | `GET/POST /login` |
| 3 | Forgot password — 4 steps: email → 6-digit code → new password → confirm | `GET/POST /password`, `/password/code`, `/password/reset` |
| 4 | Browse services (public, search + category + price filters) | `GET /services` |
| 5 | Book appointment + auto-generated booking summary | `GET/POST /book`, `GET /appointments`, `/appointments/{id}` |
| 6 | My appointments / transactions (status filter, action buttons only) | `GET /appointments?status=` |
| 7 | Cancel appointment (auto-filled reference, reason, policy checkbox) | `GET/PATCH /appointments/{id}/cancel` |
| 8 | Reschedule appointment (current slot read-only, re-validated) | `GET/PATCH /appointments/{id}/reschedule` |
| 9 | Notifications (read-only, bell with unread count) | `GET /notifications` |
| 10 | Rate service (1–5 stars, completed appointments only, one per booking) | `GET/POST /appointments/{id}/rate` |
| 11 | Profile management (details, optional password, picture upload) | `GET/PATCH /profile` |
| 12 | Contact us (name, email, topic dropdown, message) | `GET/POST /contact` |
| 13 | Customer dashboard (upcoming snapshot, recent activity, quick links) | `GET /dashboard` |
| 14 | Landing / home page | `GET /` |
| 15 | Refined grid view (category-filtered service grid) | `GET /services-grid` |

### Admin side (14 flows)

| # | Flow | Route |
| --- | --- | --- |
| 1 | Admin login (separate guard) | `GET/POST /admin/login` |
| 2 | Admin dashboard (metrics, revenue chart, low-stock alerts) | `GET /admin` |
| 3 | Appointment management (approve / decline / update status, internal notes) | `GET /admin/appointments` |
| 4 | Service & item management overview (combined) | `GET /admin/catalog` |
| 5 | Service management (add / edit / delete + variants) | `GET /admin/services` |
| 6 | Inventory / item management (add / edit / delete + linked services) | `GET /admin/inventory` |
| 7 | Low-stock / not-available tagging (single + bulk) | `GET /admin/tags` |
| 8 | Calendar & blocked dates (+ operating hours) | `GET /admin/calendar` |
| 9 | Registered users management (view / deactivate / delete) | `GET /admin/users` |
| 10 | Terms & conditions editor (versioned, per category) | `GET /admin/terms` |
| 11 | Review moderation (view + delete only) | `GET /admin/reviews` |
| 12 | Sales / usage reports (+ CSV export) | `GET /admin/reports` |
| 13 | Promo / announcements management (+ push to customers) | `GET /admin/promos` |
| 14 | Admin navigation shell (persistent sidebar) | layout `resources/views/layouts/admin.blade.php` |

---

## Architecture

```
app/
├── Console/Commands/LogPromptCommand.php    php artisan prompts:log
├── Enums/                                   AppointmentStatus, ItemTag,
│                                            DownPaymentStatus, AdminRole,
│                                            TermsCategory, InquiryTopic, ChangedBy
├── Http/
│   ├── Controllers/
│   │   ├── Admin/                            14 admin flows (+ Admin\Auth\AdminSessionController)
│   │   ├── Auth/                             register, login, 4-step password reset
│   │   └── Customer/                         appointments, cancel, reschedule,
│   │                                        rate, notifications, profile, dashboard
│   ├── Middleware/                          AuthenticateAdmin, EnsureUserIsActive,
│   │                                        RedirectIfAuthenticated{,Admin}
│   └── Requests/                            Form Request validation
│                                            (Auth/, Admin/, Customer/)
├── Models/                                  15 Eloquent models
├── Notifications/                           12 notification classes
├── Services/
│   ├── BookingAvailability.php              operating hours + blocked dates + slots
│   ├── BookingService.php                   transactional booking, stock tracking
│   ├── PasswordResetService.php             hashed 6-digit codes with expiry
│   └── ReportService.php                    sales/usage aggregation
└── Support/
    ├── Nav.php                              active-nav-item resolution
    ├── PromoBanner.php                      site-wide active promo
    └── PromptLogger.php                     append-only prompt.log
```

**Design decisions worth knowing:**

- **Form Requests everywhere.** Client-side hints are for convenience only; every rule
  is enforced server-side in `app/Http/Requests/`.
- **Blade component library.** `resources/views/components/` holds the whole design
  system (`ui.button`, `ui.badge`, `ui.card`, `ui.form.*`, `ui.star-rating`,
  `ui.errors`, `brand.logo`, `ui.service-card`). Pages compose components rather than
  repeating markup.
- **Alpine for interactivity only.** Booking baskets, star ratings, dropdowns, the rich
  text editor toolbar and the date/slot picker are Alpine components. Server-rendered
  HTML stays the source of truth — Alpine only mirrors state.
- **Password show/hide is a text link**, not a button, matching the mockup.
- **Admin notes never leak.** `admin_notes` is rendered only in `admin/appointments/*`;
  a test asserts the customer-facing view does not contain it.
- **Price is snapshotted.** `appointment_service` stores the service name, variant,
  price and duration at booking time, so later catalogue edits never rewrite history.

---

## Design system

The design system is derived from the supplied reference mockup (`UI.jpg` +
`Balai ti Arjud Beauty Lounge Homepage.png`) and lives in **one place**:
the CSS custom properties in `resources/css/app.css`.

### Typography

- Headings: **Playfair Display** (serif, elegant)
- Body / UI: **Poppins**
- Both loaded from Google Fonts

### Colour tokens

| Token | Value | Role |
| --- | --- | --- |
| `--color-primary` | `#6E211B` | Deep maroon — headers, primary buttons, nav |
| `--color-primary-dark` | `#4A1712` | Hover / active |
| `--color-gold` | `#C9A24B` | Accent — highlights, icons, tags |
| `--color-gold-light` | `#E8D8A8` | Soft gold fills |
| `--color-sienna` | `#8C5B3E` | Secondary accent, italic taglines |
| `--color-blush` | `#E8C9C0` | Soft badge background |
| `--color-linen` | `#F6EFE3` | Page background |
| `--color-cream` | `#FBF7EF` | Card background |
| `--color-text-dark` | `#2E2220` | Body text |
| `--color-text-muted` | `#6B5A52` | Secondary text |

> **On the sampled primary.** The brief supplied `#6E2A33` as a *starting* value and
> asked for exact matches to be sampled from the mockup. Pixel-sampling the reference
> artwork (the "Book an Appointment" CTA, the H1 glyphs and the circular emblem) gives a
> consistent `#6E211B` family, so that is what ships. If you prefer the supplied value,
> change one line in `resources/css/app.css` — every component follows.

### Status badges

| Status | Treatment |
| --- | --- |
| Pending | Muted gold / tan |
| Confirmed | Green |
| In Progress | Amber / orange |
| Completed | Solid green |
| Cancelled | Red / maroon |
| Low Stock | Tan / orange warning |
| Best Seller | Gold |
| Sold Out | Red |

### Component styles

- Rounded cards, soft shadow, cream background, thin gold or maroon border
- Primary buttons: solid deep maroon, cream text, fully rounded
- Secondary buttons: outlined maroon or gold
- Show/Hide toggles: small text links (never buttons)
- Service cards: image thumbnail, name, price, duration, "Book Now"
- Admin sidebar: icons + labels, maroon active state, live pending/low-stock badges
- Logo: circular gold-ringed emblem with the Playfair wordmark

### Responsive strategy

- **Customer side** is mobile-first (the mockup's customer screens are phone-width)
- **Admin side** is desktop-optimised, with an off-canvas drawer on small screens

---

## Data model

16 application tables plus Laravel's `sessions`, `cache`, `jobs` and `notifications`.

| Table | Notes |
| --- | --- |
| `users` | Customers. Soft deletes, `is_active` for deactivation, derived `username` |
| `admins` | Staff. Separate table/guard, `role` enum, soft deletes |
| `salon_settings` | Single row: operating hours, slot interval, booking horizon, down-payment rules |
| `services` | Soft deletes, `slug` for public URLs, `is_featured` |
| `service_variants` | Short/long-hair style pricing, unique per service |
| `inventory_items` | Quantity + unit + reorder threshold, `status_tag` enum, soft deletes |
| `service_inventory` | Many-to-many pivot with `quantity_per_service` |
| `blocked_dates` | Date or range, scoped to one service or all, optional internal reason |
| `appointments` | Reference number, snapshotted total, down-payment fields, `admin_notes` (internal), soft deletes |
| `appointment_service` | Per-appointment service lines with price/duration snapshots |
| `appointment_status_history` | Every transition with `from`/`to`, actor type, actor id and name |
| `reviews` | One per appointment (`unique` on `appointment_id`), soft deletes |
| `contact_messages` | Topic enum, read flag, internal reply |
| `terms_and_conditions` | `unique(category, version)`, single published version per category |
| `promos` | Validity window, image, soft deletes, `notified` flag |
| `password_reset_codes` | Stores only `code_hash`, with `expires_at` and `attempts` |
| `notifications` | Laravel's database channel, polymorphic notifiable |

Conventions: foreign keys throughout, soft deletes where history matters, and backed PHP
enums for every status field.

---

## Key behaviours

### Booking validation

`App\Services\BookingAvailability` is the single source of truth for "can this be booked?".
A date/time must satisfy **all** of:

1. Not in the past
2. Within the configured booking horizon
3. On a day the salon is open (per `salon_settings.operating_hours`)
4. Not blocked by an admin (`blocked_dates`, salon-wide or service-specific)
5. The time falls inside that day's opening hours
6. The exact slot is not already taken by a pending / confirmed / in-progress booking
7. At least one hour in the future

The same rules run on the **booking form**, the **reschedule form** (excluding the
appointment's own slot) and the **live `/book/slots` JSON endpoint** that feeds the date
picker. Re-checking inside a transaction means two people cannot take the same slot.

### Low-stock tagging

The suggestion is derived **purely from stock level**:

| Condition | Suggested tag |
| --- | --- |
| `quantity <= 0` | Sold Out |
| `quantity <= reorder_threshold` | Low Stock |
| otherwise | Available (or Best Seller, if that tag is set) |

`Best Seller` survives quantity changes — it is a marketing decision, not a stock state —
until the item actually runs out. Any explicit tag an admin picks is treated as a manual
override, and the UI labels it as such (`hasManualOverride()`).

Booking a service **decrements every linked inventory item** and re-derives its tag;
cancelling or rescheduling returns the reserved stock.

### Down payment

Per the brief, **no payment gateway is integrated**. The booking form captures a GCash
reference number, the booking summary shows the status as *Awaiting Verification*, and an
admin verifies it by hand from the appointment detail screen. The configured percentage
is editable under **Admin → Calendar → Operating Hours & Rules**.

### Password reset

A four-step, session-bound wizard. Only `Hash::make()` of the six-digit code is stored
(`password_reset_codes.code_hash`), with a 15-minute expiry, one active code per email, a
5-requests-per-hour cap and a 5-wrong-attempts burn. The email form never reveals whether
an address is registered.

---

## Prompt logging

Per the brief, a development-time audit trail lives at the project root.

- **`prompt.log`** — append-only. Every entry is `[YYYY-MM-DD HH:MM:SS] <prompt or summary>`.
  Previous entries are never overwritten.
- **`App\Support\PromptLogger`** — `log()`, `module()` and `many()` helpers.
- **`php artisan prompts:log "…" --module="…"`** — the CLI entry point.

```bash
php artisan prompts:log "Add a coupon code field to the booking form"
php artisan prompts:log --module="Customer Flows 5-9" "Built cancel, reschedule and rate"
```

The file is tracked in git so the history is reviewable alongside the code.

---

## Environment notes

**`php artisan test` uses SQLite by default.** The application, migrations and seeders all
target MySQL, and `migrate --seed` plus the full test suite were verified against
MySQL/MariaDB. SQLite is only the default *test* connection so the suite runs anywhere
with no database server; see [Running the tests](#running-the-tests) to point it at MySQL.

**Layout compatibility.** The schema avoids MySQL-only column types in the queries that
run during tests. Aggregations in `ReportService` are bucketed in PHP rather than with
`DATE_FORMAT`, so report output is identical on both engines.

**Scheduled reminders.** `AppointmentReminderNotification` is dispatched from the
appointment lifecycle. To send reminders automatically, add a scheduled job that calls
it for tomorrow's confirmed appointments:

```php
// routes/console.php or app/Console/Commands
Schedule::command('appointments:send-reminders')->dailyAt('09:00');
```

**Mail.** `MAIL_MAILER=log` is the default, so emails (welcome, password reset codes,
booking updates, promo announcements) are written to `storage/logs/laravel.log` instead of
being sent. Configure a real transport in `.env` for production.

---

## License

Proprietary — built for Balai ti Arjud.
