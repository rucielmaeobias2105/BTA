# Data Dictionary

**System:** Salon Booking & Inventory System (Laravel 11 / MySQL 8 / InnoDB / utf8mb4_unicode_ci)
**Basis:** the schema produced by `php artisan migrate` from a clean database, cross-checked
against the migration definitions for MySQL type, length, nullability and default.

> **Note on the development database.** The live development MySQL database contains 11
> legacy tables and several extra `users` columns that **no migration creates** (see
> Appendix A of `audit-report.md`). This dictionary documents the **authoritative
> migration-built schema** — i.e. what a fresh deployment actually produces. That is the
> schema your ERD must show.

Conventions used below:
- **PK** primary key · **FK** foreign key · **UQ** unique · **IDX** non-unique index
- `AI` = auto-increment · `NN` = NOT NULL · `NULL` = nullable
- Every table has `created_at` / `updated_at` (`timestamp NULL`); tables marked *soft delete*
  also carry `deleted_at` (`timestamp NULL`).

---

## The price model (read this first)

Three migrations changed how money is stored. Understanding it prevents most ERD mistakes.

| Column pair | Type | Meaning |
|---|---|---|
| `services.price` | `varchar(50)` NULL | Free-text price **as displayed** to a customer — allows "Ask the salon" |
| `services.base_price` | `decimal(10,2)` NULL | The numeric part, used for sorting, filtering and arithmetic |
| `service_variants.price` | `varchar(50)` NULL | Same display rule |
| `service_variants.base_price` | `decimal(10,2)` NULL | Same numeric rule |
| `appointment_service.price` | `decimal(10,2)` NOT NULL | **Snapshotted numeric** price charged at booking time |
| `appointment_service.display_price` | `varchar(50)` NULL | **Snapshotted display text**, so an old booking still shows what the customer saw |

The split means a price is captured on the appointment line and does not change when the
catalogue is later edited. That is the point of the design, and it is a good Chapter IV
justification.

---

## 1. `users` — customer accounts *(soft delete)*

| Column | Type | Null | Default | Key | Constraint / purpose |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NN | — | **PK** | AI |
| `first_name` | varchar(255) | NN | — | | PI |
| `last_name` | varchar(255) | NN | — | | PI; IDX with `first_name` |
| `email` | varchar(255) | NN | — | **UQ** | PI; login + notifications |
| `username` | varchar(64) | NN | — | **UQ** | PI; derived from email at registration |
| `contact_number` | varchar(32) | NN | — | | PI |
| `password` | varchar(255) | NN | — | | **SPI** — bcrypt hash (model cast `hashed`) |
| `profile_photo_path` | varchar(255) | NULL | NULL | | Optional avatar, on `public` disk |
| `is_active` | boolean | NN | `1` | IDX | Access switch |
| `last_login_at` | timestamp | NULL | NULL | | PI (behavioural) |
| `remember_token` | varchar(100) | NULL | NULL | | Laravel "remember me" |

Indexes: `PRIMARY(id)`, `UQ email`, `UQ username`, `IDX (last_name, first_name)`, `IDX is_active`

---

## 2. `admins` — staff portal accounts *(soft delete)*

Separate table, separate auth provider and separate guard from `users`. They are never mixed.

| Column | Type | Null | Default | Key | Constraint / purpose |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NN | — | **PK** | AI |
| `first_name` | varchar(255) | NN | — | | PI |
| `last_name` | varchar(255) | NN | — | | PI |
| `username` | varchar(64) | NN | — | **UQ** | PI |
| `email` | varchar(255) | NN | — | **UQ** | PI |
| `password` | varchar(255) | NN | — | | **SPI** — bcrypt hash |
| `role` | **enum('admin')** | NN | `'admin'` | IDX | **Single value.** Narrowed by `2026_09_27_000000_collapse_admin_roles_to_admin` |
| `profile_photo_path` | varchar(255) | NULL | NULL | | Optional |
| `is_active` | boolean | NN | `1` | IDX | Access switch |
| `last_login_at` | timestamp | NULL | NULL | | PI (behavioural) |
| `remember_token` | varchar(100) | NULL | NULL | | |

> **Draw as a single-valued enum in your ERD.** The codebase's own route comments still
> describe a three-tier Super Admin / Manager / Staff model that **no longer exists**.
> Drawing three roles would be a factual error (see audit §5.4).

---

## 3. `appointments` — a booking *(soft delete)*

The densest PI/SPI table in the system.

| Column | Type | Null | Default | Key | Constraint / purpose |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NN | — | **PK** | AI |
| `reference_number` | varchar(32) | NN | — | **UQ** | Human-facing booking code |
| `user_id` | bigint unsigned | NULL | NULL | **FK** → `users.id` | `ON DELETE SET NULL` — NULL allows walk-in/guest bookings |
| `customer_name` | varchar(255) | NN | — | | PI; read at the chair |
| `customer_phone` | varchar(32) | NN | — | | PI |
| `customer_email` | varchar(255) | NULL | NULL | | PI |
| `preferred_date` | date | NN | — | IDX | IDX with `preferred_time` |
| `preferred_time` | time | NN | — | IDX | |
| `allergies` | text | NULL | NULL | | **SPI — health.** Safety-critical for a nail salon |
| `last_services_availed` | text | NULL | NULL | | **SPI — treatment history** |
| `preferred_stylist_id` | bigint unsigned | NULL | NULL | **FK** → `admins.id` | `ON DELETE SET NULL` |
| `technician_id` | bigint unsigned | NULL | NULL | **FK** → `technicians.id` | `ON DELETE SET NULL`; NULL = "No preference" |
| `special_request` | text | NULL | NULL | | **SPI — may contain health information** |
| `down_payment_reference` | varchar(64) | NULL | NULL | | **SPI — financial** (manual GCash reference) |
| `down_payment_amount` | decimal(10,2) | NULL | NULL | | **SPI — financial** |
| `down_payment_status` | enum(`unverified`,`verified`,`rejected`,`not_required`) | NN | `'unverified'` | IDX | |
| `total_amount` | decimal(12,2) | NN | `0` | | **SPI — financial** |
| `status` | enum(`pending`,`confirmed`,`in_progress`,`completed`,`cancelled`) | NN | `'pending'` | IDX | IDX with `user_id` |
| `source` | varchar(24) | NN | `'web'` | | Channel the booking arrived on |
| `admin_notes` | text | NULL | NULL | | Internal only; never rendered customer-facing |
| `cancellation_reason` | varchar(255) | NULL | NULL | | PI |
| `reschedule_reason` | varchar(255) | NULL | NULL | | PI |
| `admin_seen_at` | timestamp | NULL | NULL | IDX | When staff first viewed it |
| `cancelled_at` / `confirmed_at` / `completed_at` / `started_at` | timestamp | NULL | NULL | | Lifecycle stamps |
| `archived_at` | timestamp | NULL | NULL | | Auto-archived after 30 days post-completion |
| `archived_by` | bigint unsigned | NULL | NULL | **FK** → `admins.id` | `ON DELETE SET NULL` |

Indexes: `PRIMARY(id)`, `UQ reference_number`, `IDX preferred_date`, `IDX preferred_time`,
`IDX (preferred_date, preferred_time)`, `IDX status`, `IDX (user_id, status)`,
`IDX is_active`-equivalent `IDX admin_seen_at`, `IDX down_payment_status`, `IDX source`

---

## 4. `appointment_service` — line items on a booking

Price and duration are **snapshotted** so a later catalogue edit cannot alter history.

| Column | Type | Null | Default | Key | Constraint / purpose |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NN | — | **PK** | AI |
| `appointment_id` | bigint unsigned | NN | — | **FK** → `appointments.id` | **`ON DELETE CASCADE`** |
| `service_id` | bigint unsigned | NULL | NULL | **FK** → `services.id` | `ON DELETE SET NULL` |
| `service_variant_id` | bigint unsigned | NULL | NULL | **FK** → `service_variants.id` | `ON DELETE SET NULL` |
| `service_name` | varchar(255) | NN | — | | Snapshot of the name |
| `variant_name` | varchar(255) | NULL | NULL | | Snapshot |
| `price` | decimal(10,2) | NN | — | | Snapshotted numeric price |
| `display_price` | varchar(50) | NULL | NULL | | Snapshotted display text |
| `duration_minutes` | smallint unsigned | NN | — | | Snapshotted |
| `quantity` | smallint unsigned | NN | `1` | | |

Indexes: `PRIMARY(id)`, `IDX appointment_id` (via FK), `IDX (appointment_id, service_id)`

---

## 5. `appointment_status_history` — audit trail

| Column | Type | Null | Default | Key | Constraint / purpose |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NN | — | **PK** | AI |
| `appointment_id` | bigint unsigned | NN | — | **FK** → `appointments.id` | **`ON DELETE CASCADE`** |
| `from_status` | enum(5 statuses) | NULL | NULL | | NULL on first entry |
| `to_status` | enum(5 statuses) | NN | — | | |
| `changed_by` | enum(`admin`,`customer`,`system`) | NN | `'system'` | | Actor type |
| `changed_by_id` | bigint unsigned | NULL | NULL | | Deliberately **not** an FK — survives account deletion |
| `changed_by_name` | varchar(255) | NULL | NULL | | Name snapshot, so the trail stays readable |
| `note` | text | NULL | NULL | | Free-text reason |

Indexes: `PRIMARY(id)`, `IDX appointment_id`, `IDX (appointment_id, created_at)`

---

## 6. `service_categories`

| Column | Type | Null | Default | Key | Constraint / purpose |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NN | — | **PK** | AI |
| `name` | varchar(255) | NN | — | **UQ** | |
| `color` | varchar(7) | NN | `'#7A241B'` | | Hex, for admin calendar chips |
| `sort_order` | int unsigned | NN | `0` | IDX | Display order |
| `is_active` | boolean | NN | `1` | IDX | |
| `photo` | varchar(255) | NULL | NULL | | Path on `public` disk, `categories/` |

> **Relationship note for your ERD:** `services` carries **both** `category` (a free-text
> string, indexed) **and** `service_category_id` (a real FK). The migration that added the
> FK deliberately left the string column in place. So the ERD must show a **1:M** from
> `service_categories` to `services` *and* a redundant denormalised `category` string on
> `services`. This is a genuine design artefact worth explaining in Chapter IV rather than
> hiding.

---

## 7. `services` *(soft delete)*

| Column | Type | Null | Default | Key | Constraint / purpose |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NN | — | **PK** | AI |
| `name` | varchar(255) | NN | — | | |
| `slug` | varchar(255) | NN | — | **UQ** | URL segment |
| `category` | varchar(255) | NN | — | IDX | Legacy free-text (see note above) |
| `service_category_id` | bigint unsigned | NULL | NULL | **FK** → `service_categories.id` | `ON DELETE SET NULL`; IDX with `category` and `is_active` |
| `price` | varchar(50) | NULL | NULL | | **Display** text |
| `base_price` | decimal(10,2) | NULL | NULL | | **Numeric** price |
| `duration_minutes` | smallint unsigned | NN | `60` | | |
| `description` | text | NULL | NULL | | |
| `photo_path` | varchar(255) | NULL | NULL | | |
| `is_active` | boolean | NN | `1` | IDX | |
| `is_featured` | boolean | NN | `0` | IDX | Promoted on the landing page |

---

## 8. `service_variants` — price/duration options per service

| Column | Type | Null | Default | Key | Constraint / purpose |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NN | — | **PK** | AI |
| `service_id` | bigint unsigned | NN | — | **FK** → `services.id` | **`ON DELETE CASCADE`** |
| `name` | varchar(255) | NN | — | | **UQ** with `service_id` |
| `price` | varchar(50) | NULL | NULL | | Display text |
| `base_price` | decimal(10,2) | NULL | NULL | | Numeric |
| `duration_minutes` | smallint unsigned | NULL | NULL | | |
| `is_default` | boolean | NN | `0` | | Pre-selected in the booking form |

Composite unique key: `UQ (service_id, name)`

---

## 9. `inventory_items` *(soft delete)*

| Column | Type | Null | Default | Key | Constraint / purpose |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NN | — | **PK** | AI |
| `name` | varchar(255) | NN | — | | |
| `sku` | varchar(64) | NN | — | **UQ** | Stock-keeping unit |
| `category` | varchar(255) | NN | — | IDX | Free-text |
| `quantity` | decimal(12,2) | NN | `0` | | Fractional stock allowed |
| `unit` | varchar(24) | NN | `'pcs'` | | |
| `reorder_threshold` | decimal(12,2) | NULL | NULL | | Triggers the low-stock tag |
| `date_in` / `expiry_date` | date | NULL | NULL | | Shelf-life tracking |
| `supplier` | varchar(255) | NULL | NULL | | |
| `status_tag` | enum(`available`,`low_stock`,`best_seller`,`sold_out`) | NN | `'available'` | IDX | |
| `notes` | text | NULL | NULL | | |
| `is_active` | boolean | NN | `1` | IDX | |

---

## 10. `service_inventory` — M:N junction, which service consumes which item

| Column | Type | Null | Default | Key | Constraint / purpose |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NN | — | **PK** | AI |
| `service_id` | bigint unsigned | NN | — | **FK** → `services.id` | **`ON DELETE CASCADE`** |
| `inventory_item_id` | bigint unsigned | NULL→NN | — | **FK** → `inventory_items.id` | **`ON DELETE CASCADE`** |
| `quantity_per_service` | decimal(12,2) | NN | `1` | | Consumption per booking |

Composite unique key: `UQ (service_id, inventory_item_id)`

---

## 11. `technicians` *(soft delete)*

Separate from `admins` on purpose: an *admin* holds the panel login, a *technician* is
someone a customer can ask for.

| Column | Type | Null | Default | Key | Constraint / purpose |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NN | — | **PK** | AI |
| `name` | varchar(120) | NN | — | | PI (staff-adjacent) |
| `photo_path` | varchar(255) | NULL | NULL | | |
| `is_active` | boolean | NN | `1` | IDX | Only active technicians reach the booking form |

---

## 12. `promos` *(soft delete)*

| Column | Type | Null | Default | Key | Constraint / purpose |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NN | — | **PK** | AI |
| `title` | varchar(255) | NN | — | | |
| `description` | text | NN | — | | Full text, not truncated, on the customer page |
| `starts_at` / `ends_at` | date | NN | — | IDX each | Validity window; composite `IDX (is_active, starts_at, ends_at)` |
| `image_path` | varchar(255) | NULL | NULL | | On `public` disk, `promos/` |
| `is_active` | boolean | NN | `1` | IDX | |
| `notified` | boolean | NN | `0` | IDX | Whether it has been pushed to customers |

---

## 13. `terms_and_conditions` — versioned legal text

| Column | Type | Null | Default | Key | Constraint / purpose |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NN | — | **PK** | AI |
| `category` | enum(`booking`,`cancellation`,`rescheduling`) | NN | — | IDX | **UQ** with `version` |
| `version` | int unsigned | NN | — | | Monotonic per category |
| `content` | longtext | NN | — | | Admin rich text; rendered through an allow-list sanitiser |
| `is_published` | boolean | NN | `0` | IDX | |
| `published_at` | timestamp | NULL | NULL | | |
| `created_by` | bigint unsigned | NULL | NULL | **FK** → `admins.id` | `ON DELETE SET NULL` |

Indexes: `PRIMARY(id)`, `UQ (category, version)`, `IDX category`, `IDX is_published`,
`IDX (category, is_published)`

---

## 14. `contact_messages` — public enquiry inbox

| Column | Type | Null | Default | Key | Constraint / purpose |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NN | — | **PK** | AI |
| `name` | varchar(255) | NN | — | | PI |
| `email` | varchar(255) | NN | — | | PI |
| `topic` | enum(7 topics) | NN | — | IDX | |
| `message` | text | NN | — | | PI; may contain SPI |
| `is_read` | boolean | NN | `0` | IDX | |
| `admin_reply` | text | NULL | NULL | | Staff-authored |
| `replied_at` | timestamp | NULL | NULL | | |

> **No soft delete** — enquiries are hard-deleted when staff remove them.

---

## 15. `salon_settings` — operating configuration (single row)

Drives booking availability. **No admin UI currently edits it** (the calendar screen was
removed), but `BookingAvailability` reads it.

| Column | Type | Null | Default | Key | Constraint / purpose |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NN | — | **PK** | AI |
| `name` | varchar(255) | NN | `'Balai ti Arjud'` | | |
| `address` / `phone` / `email` | varchar | NULL | NULL | | Business contact |
| `operating_hours` | json | NN | — | | `{"monday": ["09:00","18:00"], …}`; a closed day is simply absent |
| `slot_interval_minutes` | smallint unsigned | NN | `30` | | Booking granularity |
| `booking_lead_days` | smallint unsigned | NN | `60` | | How far ahead a booking may be made |
| `down_payment_required` | boolean | NN | `1` | | |
| `down_payment_percentage` | tinyint unsigned | NN | `50` | | |

---

## 16. `password_reset_codes` — six-digit reset flow

| Column | Type | Null | Default | Key | Constraint / purpose |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NN | — | **PK** | AI |
| `email` | varchar(255) | NN | — | IDX | PI |
| `code_hash` | varchar(255) | NN | — | | **bcrypt hash of the code** — a DB dump yields no usable code |
| `expires_at` | timestamp | NN | — | IDX | |
| `used_at` | timestamp | NULL | NULL | | Single-use marker |
| `attempts` | tinyint unsigned | NN | `0` | | Brute-force counter |
| `ip_address` | varchar(45) | NULL | NULL | | PI |

---

## 17. `notifications` — Laravel database channel

| Column | Type | Null | Default | Key | Constraint / purpose |
|---|---|---|---|---|---|
| `id` | uuid | NN | — | **PK** | |
| `type` | varchar(255) | NN | — | | Notification class |
| `notifiable_type` | varchar(255) | NN | — | IDX | Polymorphic |
| `notifiable_id` | bigint unsigned | NN | — | IDX | Polymorphic — **not** a real FK |
| `data` | text | NN | — | | JSON payload |
| `read_at` | timestamp | NULL | NULL | | |

---

## 18. Framework tables (no action needed)

| Table | Purpose |
|---|---|
| `sessions` | Database session driver: `id` PK, `user_id`, `ip_address` (45), `user_agent`, `payload` (longtext), `last_activity` |
| `password_reset_tokens` | Laravel's token-broker table (`email` PK, `token`, `created_at`) |
| `cache`, `cache_locks` | Cache driver |
| `jobs`, `job_batches`, `failed_jobs` | Database queue driver |
| `migrations` | Migration ledger |

---

## Cardinality summary (for your ERD)

```
users            1 ──< 0..N appointments            (user_id, SET NULL)
admins           1 ──< 0..N appointments            (preferred_stylist_id, SET NULL)
admins           1 ──< 0..N appointments            (archived_by, SET NULL)
admins           1 ──< 0..N terms_and_conditions     (created_by, SET NULL)
technicians      1 ──< 0..N appointments            (technician_id, SET NULL)
appointments     1 ──< 1..N appointment_service      (appointment_id, CASCADE)
appointments     1 ──< 0..N appointment_status_history (appointment_id, CASCADE)
services         1 ──< 0..N appointment_service      (service_id, SET NULL)
services         1 ──< 0..N service_variants         (service_id, CASCADE)
services         1 ──< 0..N service_inventory        (service_id, CASCADE)
inventory_items  1 ──< 0..N service_inventory        (inventory_item_id, CASCADE)
service_categories 1 ──< 0..N services               (service_category_id, SET NULL)
service_variants  1 ──< 0..N appointment_service      (service_variant_id, SET NULL)
users            * ──< *  notifications             (polymorphic: no FK)
```

**Referential-integrity rules worth stating in Chapter IV:**
1. **CASCADE** is used only for *composition* rows that have no meaning without their parent —
   `appointment_service`, `appointment_status_history`, `service_variants`,
   `service_inventory`. Delete the booking and its lines go with it.
2. **SET NULL** is used everywhere a historical record must survive. Deleting a service, a
   technician or a **customer account** never destroys booking history.
3. **Two links to `admins`** (`preferred_stylist_id`, `archived_by`) plus one to
   `technicians`. Explain that `preferred_stylist_id` predates the `technicians` table and
   both are retained.
