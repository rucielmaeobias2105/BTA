# Role-Based Access Control (RBAC) Matrix

**System:** Salon Booking & Inventory System
**Derived from:** `routes/web.php`, `app/Enums/AdminRole.php`, `bootstrap/app.php`,
`app/Providers/AppServiceProvider.php`, `config/auth.php`
**Date:** October 2, 2026

> **No real personal data appears in this document.**

---

## 1. User types the system actually has

There are **three**, not four.

| # | Role | Identity store | Auth guard | Reached by |
|---|---|---|---|---|
| 1 | **Guest** | — | none | Anyone not signed in |
| 2 | **Customer** | `users` table | `web` | Self-registration + email/username login |
| 3 | **Administrator** | `admins` table | `admin` | Staff-only portal login |

Customers and administrators live in **separate tables, separate providers and separate
guards** (`config/auth.php:38-49`). A customer session cannot reach an admin route and an
admin session cannot reach a customer route, because `auth.admin` checks the `admin` guard
and `auth.customer` checks the `web` guard.

### ⚠ Read this before you draw the matrix

`app/Enums/AdminRole.php` declares **one** admin role:

```php
enum AdminRole: string {
    case Admin = 'admin';
    // abilitiesFor() returns every ability for it.
}
```

and migration `2026_09_27_000000_collapse_admin_roles_to_admin.php` narrowed the database
column to `enum('admin')`.

**The route comments in `routes/web.php:289-293` still describe a three-tier Super Admin /
Manager / Staff model that no longer exists.** Do **not** draw three admin roles. Drawing
that matrix would be a factual error, and an instructor who reads those comments and asks
to see the Manager role will find nothing.

The 15 abilities below are real and enforced. They are a *capability* model, not a
*hierarchical role* model — which is the more defensible design, and worth saying so in
Chapter IV.

---

## 2. Ability table (the enforcement mechanism)

`AdminRole::abilities()` is the single source of truth. `AppServiceProvider:32-45` turns
each entry into a Gate named `admin.<ability>`, which routes enforce through
`admin.role:<ability>` middleware and views use through `@can`.

| # | Ability | Guards |
|---|---|---|
| 1 | `admin.dashboard.view` | Admin dashboard |
| 2 | `admin.appointments.manage` | Entire appointment queue, archive, status, notes, down payment |
| 3 | `admin.catalog.view` | Read-only catalogue browsing |
| 4 | `admin.catalog.manage` | Service + category writes, variants |
| 5 | `admin.technicians.view` | Read-only technician roster |
| 6 | `admin.technicians.manage` | Technician writes |
| 7 | `admin.inventory.view` | Inventory list **and** the inventory CSV export |
| 8 | `admin.inventory.manage` | Inventory writes |
| 9 | `admin.tags.manage` | Low-stock / sold-out tagging, single and bulk |
| 10 | `admin.users.view` | Registered users list, detail, **and deletion** |
| 11 | `admin.terms.view` | Terms list |
| 12 | `admin.terms.manage` | Terms create/edit/publish/delete |
| 13 | `admin.reports.view` | Sales/usage reports **and** CSV export |
| 14 | `admin.promos.manage` | Promo CRUD and announcement push |
| 15 | `admin.messages.manage` | Enquiry inbox read/reply/delete |

**Read/write asymmetry is deliberate.** `catalog`, `technicians` and `inventory` each split
into a `.view` and a `.manage` ability, so a role can browse a reference screen without
being able to change it. `routes/web.php:369-371` states this intent explicitly: the index
route is declared separately from the resource precisely so `*.view` can be granted alone.

**Two abilities have no `.view`/`.manage` pair:** `users` (one ability) and
`appointments` (one ability). `routes/web.php:462-466` explains the users case.

---

## 3. The matrix

Legend: **R** read · **C** create · **U** update · **D** delete · **—** no access
· 🔒 additional restriction noted in §5

### 3.1 Customer-facing modules

| Module | Guest | Customer | Notes |
|---|:---:|:---:|---|
| Home / About / Services catalogue | R | R | Public; `routes/web.php:44-55` |
| Promo offers | R | R | Public, active + in-window only |
| Contact form | C | C | `POST /contact` — a guest may send an enquiry without an account |
| Published Terms & Conditions | R | R | By category (`booking`, `cancellation`, `rescheduling`) |
| Live slot availability | R | R | `GET /book/slots` — deliberately public so availability shows before sign-in |
| **Register** | **C** | — | `guest` middleware group |
| **Login / logout** | **C**/— | R (logout) | |
| **Forgot password** (4-step wizard) | C | — | email → code → reset |
| **Booking wizard** | — | **R/C** | Sign-in required so every booking ties to an account |
| **My appointments** | — | **R** | 🔒 own rows only |
| **View appointment** | — | **R** | 🔒 own rows only — `AppointmentController:507` |
| **Cancel appointment** | — | **R/U** | 🔒 own rows only — `CancelAppointmentController:106`; status-restricted |
| **Reschedule appointment** | — | **R/U** | 🔒 own rows only — `RescheduleAppointmentController:92` |
| **Clear settled bookings** | — | **D** | 🔒 own rows only; only Completed or Cancelled |
| **Notifications** (list, read, delete) | — | **R/U/D** | 🔒 scoped through `$request->user()->notifications()` |
| **Profile** (view, edit) | — | **R/U** | Own account only |
| **Profile photo** (upload, remove) | — | **C/D** | Own account only |
| **Change password** | — | **U** | Requires current password to change email/phone |

### 3.2 Administrative modules

| Module | Ability required | Guest | Customer | Admin |
|---|---|:---:|:---:|:---:|
| Admin dashboard | `admin.dashboard.view` | — | — | R |
| Appointments — list / view | `admin.appointments.manage` | — | — | R |
| Appointments — status update | `admin.appointments.manage` | — | — | U |
| Appointments — internal notes | `admin.appointments.manage` | — | — | U |
| Appointments — down payment verify | `admin.appointments.manage` | — | — | U |
| Appointments — archive / restore | `admin.appointments.manage` | — | — | U |
| Appointments — permanent delete | `admin.appointments.manage` | — | — | D 🔒¹ |
| Services — list | `admin.catalog.view` | — | — | R |
| Services — create / edit / delete | `admin.catalog.manage` | — | — | C/U/D |
| Service variants | `admin.catalog.manage` | — | — | C/U/D |
| Service availability toggle | `admin.catalog.manage` | — | — | U |
| Categories — list | `admin.catalog.view` | — | — | R |
| Categories — create / edit / delete | `admin.catalog.manage` | — | — | C/U/D |
| Technicians — list | `admin.technicians.view` | — | — | R |
| Technicians — create / edit / delete | `admin.technicians.manage` | — | — | C/U/D |
| Inventory — list | `admin.inventory.view` | — | — | R |
| Inventory CSV export | `admin.inventory.view` | — | — | R 🔒² |
| Inventory — create / edit / delete | `admin.inventory.manage` | — | — | C/U/D |
| Low-stock / sold-out tagging | `admin.tags.manage` | — | — | U |
| Bulk tagging | `admin.tags.manage` | — | — | U |
| Registered users — list / view | `admin.users.view` | — | — | R |
| Registered users — delete | `admin.users.view` | — | — | D 🔒³ |
| Terms — list | `admin.terms.view` | — | — | R |
| Terms — create / edit / publish / delete | `admin.terms.manage` | — | — | C/U/D |
| Reports | `admin.reports.view` | — | — | R |
| Reports CSV export | `admin.reports.view` | — | — | R |
| Promo — CRUD | `admin.promos.manage` | — | — | C/U/D |
| Promo — announce to customers | `admin.promos.manage` | — | — | C 🔒⁴ |
| Contact messages — read / reply / delete | `admin.messages.manage` | — | — | R/U/D |
| **Own admin profile** | *(no ability needed)* | — | — | **R/U** |
| Admin notification feed | *(no ability needed)* | — | — | R/U |

¹ Permanent delete re-checks the status server-side: a booking that is `pending`,
`confirmed` or `in_progress` can be neither archived nor permanently deleted
(`app/Services/AppointmentArchiver.php`). Restoring and bulk-restoring share the same rule.

² Guarded by the *inventory* ability, not the reports one, because the export reads the
stock table rather than sales data (`routes/web.php:515-521`).

³ **Deletion is gated on a *view* ability.** `routes/web.php:462-466` explains why: the
ability table carries one entry for that screen, and inventing a second would mean the
panel could display a button it had not checked. See §5 for the risk this carries.

⁴ Pushing a promo sends a notification to a chosen audience — all customers, active
customers, or the last 90 days. This is the single most PI-revealing action in the admin
panel, since it emails customers.

---

## 4. Row deleted: customer dashboard

`routes/web.php:147-156` documents the removal of the customer Dashboard. It duplicated the
landing page's hero call to action behind a second URL, and a protected page duplicating
public content is a second authenticated surface to keep in step. The capability moved to
`appointments.index`.

**Not in the matrix** — it does not exist. If you draw it, it is an error.

---

## 5. Mismatches, risks and judgement calls

These are the points to discuss. None is currently exploitable, but each is a legitimate
"what would you improve?" answer.

### M1 — A *view* ability grants *delete* (Registered Users)

`routes/web.php:477-482` gates both `GET /admin/users/{user}/delete-confirmation` and
`DELETE /admin/users/{user}` on `admin.users.view`. Today this is harmless: the only role
holds every ability, and removing a customer triggers `UserAnonymizer` (anonymise, then
soft-delete) rather than a destructive hard delete.

**The risk is structural.** The first time a second role is added — a "front desk" role
that can read the customer list but must not delete records — `users.view` silently confers
deletion. The ability table has no `users.manage` to prevent it.

*Suggested remedy:* add `users.manage`, gate the DELETE on it, keep the list on `users.view`.

### M2 — Route comments describe a role model that was deleted

`routes/web.php:289-293` still promises a Manager who "cannot edit legal text or destroy
customer records" and Staff with "read-only reference screens". Neither exists.

**Risk type:** documentation, not security — but the rubric awards 10% for documentation
integrity, and the §3.2 technical viva requires line-by-line defence of code you show. Stale
comments are the easiest thing to be caught on.

*Suggested remedy:* rewrite the comment to describe the single-role capability model.

### M3 — No Laravel Policies

Authorisation is enforced by three explicit `authorizeOwnership()` helpers plus a
Gate-defined ability table — not by `app/Policies/*`. Functionally sound and arguably more
explicit, but if an instructor asks "where are your policies?", say plainly: *"We use
explicit ownership assertions in the controller and a Gate-driven ability table; we did not
adopt policy classes."* Do not claim policies exist.

### M4 — Own-profile and notification routes carry no ability

`GET/PATCH /admin/profile` (`routes/web.php:543-544`) and the three admin notification
routes (`:550-563`) sit inside `auth.admin` but have no `admin.role:*`. Correct — every
role may edit its own account, and the bell is chrome — but note it, because "every admin
route has an ability check" is otherwise an untrue summary.

### M5 — Appointment deletion is status-gated, not ability-gated twice

Both `DELETE /admin/appointments/{appointment}` and `archive` require
`admin.appointments.manage` *and* pass a server-side status check. The comment at
`routes/web.php:314-318` says the route is re-declared explicitly so the rule is visible
rather than implied by a resource default. Good practice; mention it.

### M6 — No self-service account deletion for customers

A customer can edit their profile but cannot delete their own account. Data subject rights
under RA 10173 §11 include the right to be informed and object, and having removal depend
on staff action is a defensible but *chosen* position. State it as a decision rather than
an oversight.

---

## 6. Visual note for EdrawMax / draw.io

Draw the matrix as:

- **Columns:** Guest · Customer · Administrator (three columns — not four)
- **Rows:** one per module, grouped into the customer-facing block and the administrative
  block as in §3.1 and §3.2
- **Cell contents:** the CRUD letters, not ticks — a tick hides the difference between
  "read-only access granted" and "no access at all", which is the whole point of splitting
  `*.view` from `*.manage`
- **One callout box** under the Administrator column listing the 15 abilities verbatim, so
  the enforcement mechanism is visible on the diagram rather than only in the text

If your instructor expects more than three roles, the honest answer is that the salon runs
a single staff account, so extra tiers would only create ways to lock a real user out of a
screen they need — and that is quoted from `app/Enums/AdminRole.php`.
