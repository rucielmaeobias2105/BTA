# Data Flow Diagram — Notes for Drawing

**System:** Salon Booking & Inventory System
**Purpose:** everything you need to draw a Level-0 and Level-1 DFD in draw.io / EdrawMax,
without inventing a process or data store that does not exist in the code.

> **No real personal data appears in this document.** Field names are schema column names.

---

## 1. External entities

Draw one rectangle per entity. There are **five**.

| Symbol | Entity | What it sends in | What it receives out |
|---|---|---|---|
| **E1** | **Customer** | Registration data; booking request (services, variants, technician, date/time, allergies, special request); cancellation / reschedule request; profile edits; contact message | Booking reference; confirmation mail; status notifications; catalogue; availability; own appointment list |
| **E2** | **Salon Staff / Administrator** | Catalogue, technician, promo and Terms edits; appointment status changes; internal notes; down-payment verification; archive/restore; inventory updates; report exports; customer account removal | Dashboard counts; appointment queue; low-stock warnings; reports; CSV exports; enquiry inbox |
| **E3** | **SMTP Mail Server** *(external system)* | Delivered mail: confirmations, status changes, reminders, promo announcements, password reset codes | — (one-way outbound; the app never receives mail) |
| **E4** | **Database Server** *(external system in DFD terms)* | Persisted and retrieved records | All read/write traffic |
| **E5** | **Uploaded Image Files** *(if you model storage as an entity)* | Binary write on upload | Binary read on page render |

> **Drawing note.** Many textbook DFDs treat E4 as a data store rather than an external
> entity. Both are accepted; be consistent. If you draw D1/D2/D3 as data stores **and** E4,
> say in your caption that the data stores are logical views of the MySQL server at E4.

---

## 2. Processes (P-numbered)

### Level-0 (context) — draw 4 processes

| # | Process | Trigger |
|---|---|---|
| **P1** | Manage Service Catalogue | Staff opens or edits a service, category, variant or technician |
| **P2** | Manage Booking | Customer submits the booking wizard |
| **P3** | Manage Appointment Lifecycle | Customer or staff cancels, reschedules, or staff changes status / verifies payment / archives |
| **P4** | Manage Enquiries, Promos & Reporting | Customer sends a message; staff manages promos and Terms; staff runs a report or export |

### Level-1 detail — the processes inside each

#### P1 · Manage Service Catalogue
1.1 Read catalogue (grouped by category, with price and duration)
1.2 Read service variants and stock linkage
1.3 Create / update / soft-delete a service
1.4 Create / update / delete a variant
1.5 Create / update / order a category, upload its photo
1.6 Create / update / deactivate a technician, upload its photo
1.7 Upload an image (validate type + size, store with a generated filename)

#### P2 · Manage Booking
2.1 Register a customer account
2.2 Authenticate (email **or** username) / log out
2.3 Reset password via a 4-step wizard (email → hashed 6-digit code → new password)
2.4 Retrieve live slot availability *(public — runs before sign-in)*
2.5 Validate the booking: lead time, operating hours, slot interval, availability
2.6 Validate Terms & Conditions acceptance
2.7 **Snapshot** service name, price and duration onto the booking line
2.8 Compute the booking total; generate a unique reference number
2.9 Persist the booking and its lines
2.10 Notify the customer (booking confirmation)

#### P3 · Manage Appointment Lifecycle
3.1 List a customer's own appointments *(ownership enforced)*
3.2 View one appointment *(ownership enforced)*
3.3 Request cancellation with a reason; validate the status permits it
3.4 Reschedule: re-validate availability for the new date/time
3.5 Clear a settled booking (single or bulk)
3.6 Staff: update status, recording actor + note in the audit trail
3.7 Staff: attach internal notes *(never customer-visible)*
3.8 Staff: verify the down payment against a manual reference
3.9 Staff: archive / restore / permanently delete *(settled statuses only)*
3.10 Notify the customer of every status change
3.11 **Nightly job:** auto-archive appointments completed more than 30 days ago

#### P4 · Manage Enquiries, Promos & Reporting
4.1 Customer submits an enquiry *(public, rate-limited)*
4.2 Staff reads, replies to and deletes enquiries
4.3 Staff creates / updates / deletes a promo, with an image
4.4 Push a promo to a chosen customer audience
4.5 Staff writes and publishes a versioned Terms & Conditions document
4.6 Staff views the sales / usage report
4.7 Export a report or the inventory list to CSV
4.8 Staff reviews and removes a registered customer *(anonymise, then soft-delete)*
4.9 Staff reads, marks read, and deletes enquiries via the notification bell

---

## 3. Data stores

| # | Store | Contents | Written by |
|---|---|---|---|
| **D1** | Customers | Identity, contact, hashed password, activity stamps | P2.1, P2.3, P4.8 |
| **D2** | Staff accounts | Identity, hashed password, role, activity stamps | Admin login, profile |
| **D3** | Appointments | Booking header + **SPI**: allergies, history, special request, GCash reference, internal notes | P2.9, P3.3–P3.9 |
| **D4** | Appointment lines | Snapshotted service, variant, price, duration, quantity | P2.7 |
| **D5** | Status history | Actor, from/to status, note, timestamp | P3.6 |
| **D6** | Catalogue | Services, variants, categories, technicians | P1.3–P1.6 |
| **D7** | Inventory | Items, SKU, quantity, threshold, supplier, expiry, status tag; the service↔item junction | P1.4, admin inventory |
| **D8** | Content | Promos, versioned Terms & Conditions | P4.3, P4.5 |
| **D9** | Enquiries | Contact name/email, topic, message, staff reply | P4.1, P4.2 |
| **D10** | Notifications | Polymorphic inbox rows for customers and enquiries | P2.10, P3.10, P4.4, P4.9 |
| **D11** | Password reset codes | Email, **hashed** code, expiry, use marker, attempt count | P2.3 |
| **D12** | Salon settings | Operating hours, slot interval, lead days, down-payment terms | Read-only in the UI; drives validation |
| **D13** | Sessions & cache | Session payload, IP, user agent; cache; queue jobs | Framework |

**Diagram precision points:**
- **D3 must be flagged as containing SPI.** Mark allergies and the down-payment reference
  on the data store itself. This is the single most valuable annotation on the DFD for the
  DPA Compliance criterion.
- **D1 is never hard-deleted.** Removal is anonymise-then-soft-delete, so D1 → D3 must show
  a **broken** referential line, not a cascade. This is a genuinely good DFD detail.
- **D4 is a cascade child of D3.** Deleting a booking deletes its lines; the junction D7 is
  not deleted.

---

## 4. The six flows to draw in detail

### Flow 1 — Booking (the core To-Be flow)

```
E1 Customer
  └─(2.1 registration data)──► P2.1 Register ──► D1
  └─(2.5 date + service choice)──► P2.4 Availability ──► D12 ──►(slots)──► E1
  └─(2.5 full booking request)──► P2.5 Validate ──► D12, D6
                                    │
                                    ├─► P2.6 Terms check ──► D8
                                    ├─► P2.7 Snapshot ──► D4
                                    ├─► P2.8 Total + reference
                                    └─► P2.9 Persist ──► D3, D4, D5
                                             │
                                             └─► P2.10 Notify ──► D10 ──► E3 SMTP ──► E1
```

**Data to label on the arrows:** registration data; login credentials; service selection
with variant and quantity; chosen technician; preferred date/time; **allergies**; **special
request**; T&C acceptance; booking reference; confirmation email.

### Flow 2 — Reschedule

```
E1 ─(appointment id)──► P3.1/3.2 Ownership check (403 if not owner)
   └─(new date/time + reason)──► P3.4 Re-validate ──► D12, D6
                                └─► D3 updated
                                └─► D5 audit row written
                                └─► P3.10 Notify ──► D10 ──► E3 ──► E1
```
**Key point for Chapter III:** the new date is re-validated through the *same* availability
logic as a new booking. The customer is not promised a slot that was never checked.

### Flow 3 — Cancel

```
E1 ─(appointment id + reason)──► P3.3 Status check
   └─ not cancellable ──► refusal returned (no write)
   └─ cancellable ──► D3 status + cancelled_at + reason
                      └─► D5 audit row (actor = customer)
                      └─► P3.10 Notify ──► E3 ──► E1 (and staff)
```

### Flow 4 — Admin status update (with payment verification)

```
E2 ─(appointment id, new status)──► P3.6
   └─► D5 audit row (actor = admin, note)
   └─► D3 status + confirmed_at / started_at / completed_at
   └─► P3.10 Notify ──► E3 ──► E1

E2 ─(reference + decision)──► P3.7 down-payment verify
   └─► D3 down_payment_status ∈ {unverified, verified, rejected, not_required}
```

**Distinguish P3.6 from P3.7** — they are separate processes in the code, and conflating
them is the usual DFD mistake. **P3.7 writes SPI-adjacent financial data.**

### Flow 5 — Notification fan-out

```
D10 ◄──write── P2.10, P3.10, P4.4, P4.9
E1/E2 ◄──poll── P3 (feed)      [throttled]
E3   ◄──out─── all notification classes
```
Every one of the 12 notification classes writes here. The bell badge is driven by polling,
not push — **state that on the diagram**, because "no WebSocket server" is a decision an
examiner may question.

### Flow 6 — Report and CSV export

```
E2 ─(report filters)──► P4.6 ──► D3, D4, D5 ──► aggregates ──► E2
E2 ─(export click)────► P4.7 ──► D3 or D7 ──► CSV file ──► E2
```
Guard P4.7 twice on the diagram — reports and inventory are **different abilities**
(`admin.reports.view` vs `admin.inventory.view`), which is a nice detail for the RBAC
cross-reference.

---

## 5. Things a correct DFD must **not** show

| Must not appear | Why |
|---|---|
| A calendar / blocked-dates process | That screen was **removed**; the tables were dropped |
| A reviews / rating process | Dropped in `2026_09_30_000000_drop_reviews_table` |
| A cart or checkout process | No cart tables in the migrations |
| A payment gateway | GCash is verified **manually** against a reference number — no external payment flow exists |
| A push/websocket server | Notifications poll |
| A customer Dashboard | Removed; `appointments.index` took its place |
| Registration as an admin function | Customers self-register; there is no admin create-user route |

**Drawing a process for a deleted feature is the most likely way to lose marks on
"Operational Analysis"** — an examiner comparing your DFD against your live system will
notice.

---

## 6. Chapter III framing

For the **As-Is** DFD (Chapter II), draw the manual process your DFD is contrasted against:
telephone/paper booking, a physical appointment book, and a spreadsheet for stock. For the
**To-Be** DFD (Chapter III), the flows above. The gap matrix in Chapter III is then simply
the difference between the two — the manual system has no authorisation layer, no audit
trail, no availability validation, and stores PI in an unsecured book.

**Do not invent metrics.** If you have not measured the As-Is latencies at the salon, say
they are unknown rather than quoting a number you cannot defend — the rubric scores
"accuracy metrics".
