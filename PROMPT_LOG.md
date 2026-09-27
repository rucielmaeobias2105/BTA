# Prompt Log

### Task: Building the Balai ti Arjud salon & spa booking + inventory platform (first prompt)
* Prompt used: "Balai ti Arjud — Online Appointment & Inventory Management System

You are building a full production-ready web application for Balai ti Arjud (Glow & Beauty Lounge), a salon/spa booking and inventory management platform. Follow every instruction below precisely. Work incrementally, module by module, and confirm each module compiles/migrates/tests cleanly before moving to the next.

1. Tech Stack (mandatory)
Backend Framework: Laravel (latest stable LTS version)
Database: MySQL
Frontend: Laravel Blade + Tailwind CSS (or Laravel's default frontend scaffolding), with Alpine.js/vanilla JS for interactivity. Use Livewire if it simplifies dynamic UI (calendar, filters, toggles) — your call, but keep it consistent across the app.
Auth: Laravel Breeze or Fortify for customer + a separate guarded admin auth flow (do not mix customer and admin sessions/roles).
File storage: Laravel's filesystem (local storage/app/public with symlink) for profile pictures, service photos, promo images.
Migrations & Seeders: Provide full migrations for every table implied below, plus seeders for demo data (sample services, inventory items, admin account, sample customers).
2. Logging Requirement (mandatory)

Create a prompt.log file at the project root. Every time this application (or any AI-assisted tooling working on it) processes a prompt/instruction during development, append an entry to prompt.log with a timestamp and the prompt text/summary. Implement this as a simple helper (e.g., App\Support\PromptLogger::log(string $prompt)) that appends [YYYY-MM-DD HH:MM:SS] prompt lines to storage/logs/prompt.log (or project-root prompt.log if preferred) — do not overwrite previous entries, always append.

3. Design System — Follow the Attached Mockup Exactly

A reference mockup image has been provided showing all 15 customer-side flows, 14 admin-side flows, and a UI component sheet (buttons, status badges, card styles, typography, and three theme palette swatches). Match this design system precisely — do not substitute a generic Bootstrap/Tailwind default theme.

Typography:

Headings: Playfair Display (serif, elegant)
Body/UI text: Poppins or Inter (clean sans-serif)
Pull both from Google Fonts.

Color Palette: The mockup shows a warm, earthy salon palette — deep maroon/burgundy as the primary brand color, gold/mustard as an accent, and cream/linen/beige as the background and card tones, with secondary sienna/tan/blush swatches for status accents. Use these as starting hex values (sample/adjust against the actual mockup image for exact matches before finalizing):

--color-primary:      #6E2A33;  /* deep maroon/burgundy - headers, primary buttons, nav */
--color-primary-dark: #4E1D24;  /* hover/active state */
--color-gold:         #C9A24B;  /* accent - highlights, icons, "Best Seller" tags */
--color-gold-light:   #E8D8A8;
--color-linen:        #F6EFE3;  /* page background */
--color-cream:        #FBF7EF;  /* card background */
--color-sienna:       #8C5B3E;  /* secondary accent */
--color-blush:        #E8C9C0;  /* soft status/badge background */
--color-text-dark:    #2E2220;
--color-text-muted:   #6B5A52;

Status Badge Colors (from UI component sheet):

Pending → muted gold/tan
Confirmed → green
In Progress → amber/orange
Completed → green (solid)
Cancelled → red/maroon
Low Stock → tan/orange warning
Best Seller → gold
Sold Out → red

Component styles to replicate:

Rounded card containers with soft shadow, cream background, thin gold or maroon border accent
Primary buttons: solid deep maroon, white text, rounded corners
Secondary buttons: outlined maroon or gold
"Show/Hide" toggle links styled as small text links, not buttons
Service cards: image thumbnail, service name, price, duration, "Book Now" button
Sidebar navigation for admin panel (icons + labels, maroon active state)
Logo/header: circular emblem badge with "Balai ti Arjud" wordmark in Playfair Display

Build a shared Blade layout/partial system (e.g., resources/views/layouts/customer.blade.php, layouts/admin.blade.php, and a components/ directory for buttons, badges, cards, form-fields) so this design system is applied consistently rather than repeated per page.

4. Customer-Side Modules (15 flows)

Build each as its own controller + routes + Blade views (+ Livewire component where interactivity is needed). Apply validation rules as noted.

Register — First Name, Last Name, Email (unique, validated), Contact Number, Password (min 8 chars, confirmed, show/hide toggle via JS), Confirm Password.
Login — Username or Email, Password (show/hide toggle). Rate-limit login attempts.
Forgot Password — Multi-step: (1) Email input, (2) 6-digit verification code (generate, store hashed with expiry, email via Laravel Notifications), (3) New Password, (4) Confirm New Password.
Browse Services — Public read-only grid/list. Search bar + filters (category, price range). No auth required to view.
Book Appointment — Selected Service (from list/cart), Full Name, Phone Number, Preferred Date (must be within salon operating hours/days — validate against admin-configured blocked dates), Preferred Time, Allergies (checklist or free text), Last Service(s) Availed (dropdown from booking history or free text), Preferred Stylist (optional select), Special Request (free text), Down Payment Reference Number, Agree to Terms & Conditions (checkbox, required, links to T&C editor content). On submit, auto-generate a Booking Summary (read-only): Customer Name, Service(s), Date & Time, Total Amount, Down Payment Status, Reference Number.
My Appointments / Transactions — List view filterable by status (Pending, Confirmed, Completed, Cancelled) — no input fields, action buttons only (view, cancel, reschedule, rate).
Cancel Appointment — Appointment reference (auto-filled from context), Reason for Cancellation (optional dropdown/free text), Agree to Cancellation Policy (checkbox).
Reschedule Appointment — Current Date/Time (read-only), New Preferred Date, New Preferred Time, Reason (optional). Re-validate against blocked dates/availability.
Notifications — Read-only, system-generated (booking confirmed, reminder, cancelled by admin, promo announcement, etc.). Use Laravel's notification system + a bell icon with unread count.
Rate Service — Star Rating (1–5, required), Review Message (text). Only available for Completed appointments; one review per completed appointment.
Profile Management — First Name, Last Name, Email, Contact Number, Password (optional change), Profile Picture (image upload, validate type/size).
Contact Us — Name, Email, Inquiry Topic (dropdown: General Inquiry, Booking Question, Service Question, etc.), Message. Store in DB + optionally email admin.
Customer Dashboard — Snapshot of upcoming appointment, recent activity, quick links (as shown in mockup's dashboard-style card).
Landing/Home page — Hero section matching mockup (salon photo, tagline, CTA "Book Appointment", nav with Login/Register).
Review/browse refined grid view — Category-filtered service grid variant as shown in mockup ("Refined Grid View").
5. Admin-Side Modules (14 flows)
Admin Login — Username, Password. Separate guard/middleware from customer auth.
Admin Dashboard — Read-only metrics: today's appointments, low-stock alert count, revenue chart, upcoming appointments list, quick stats cards (matching mockup's two dashboard variants).
Appointment Management — List/table of all appointments with Approve/Decline/Update Status action: Status (dropdown: Pending, Confirmed, In Progress, Completed, Cancelled), Admin Notes (optional, internal-only field).
Service & Item Management (overview) — Combined list/search view of services and inventory (as shown in mockup).
Service Management (Add/Edit/Delete) — Service Name, Category, Price, Duration, Description, Photo Upload, Variants (e.g., short/long hair pricing — implement as a related service_variants table).
Inventory/Item Management (Add/Edit/Delete) — Item Name, Category, Quantity/Stock Level, Unit (pcs, ml, bottles, etc.), Reorder Threshold, Supplier (optional), Linked Service(s) (many-to-many pivot: which services consume this item — enables auto low-stock flagging when services are booked).
Low-Stock / Not-Available Tagging — Select existing item, assign Status Tag (Low Stock, Sold Out/Unavailable, Best Seller). Auto-suggest "Low Stock" when quantity ≤ reorder threshold; allow manual override.
Calendar & Blocked Dates — Date(s) to Block (single or range), Scope (All services / specific service), Reason (optional internal note). This feeds validation on customer-side booking date pickers.
Registered Users Management — Search/filter by name or email, view profile details, delete/deactivate user. No new-entity input fields (view/delete only).
Terms & Conditions Editor — Rich text editor (e.g., TipTap, Quill, or TinyMCE) per category: Booking, Cancellation, Rescheduling. Store as versioned content, render on relevant customer-side checkboxes.
Review/Ratings Moderation — View all customer reviews, delete inappropriate ones. No create/edit — view + delete only.
Sales/Usage Reports — Date Range (from–to), Report Type (Daily, Weekly, Monthly, Annual), optional Filter by Service/Item. Generate summary tables/charts; allow export (CSV/PDF is a nice-to-have).
Promo/Announcements Management — Promo Title, Description, Validity Period (start–end date), Promo Image. Active promos surface as customer-side notifications/banners.
Admin Navigation Shell — Persistent sidebar (Dashboard, Appointments, Services, Inventory, Calendar, Users, T&C, Reviews, Reports, Promo, Logout) matching the mockup's admin sidebar layout.
6. Data Model Notes

Design normalized MySQL tables for at least: users (customers), admins, services, service_variants, inventory_items, service_inventory (pivot), appointments, appointment_status_history, reviews, notifications (or use Laravel's built-in notifications table), contact_messages, blocked_dates, terms_and_conditions (with category + versioning), promos, password_reset_codes. Use foreign keys, soft deletes where appropriate (services, inventory items, users), and enums or lookup tables for status fields.

7. Non-Functional Requirements
Validate all forms server-side (Form Request classes) in addition to client-side hints.
Protect admin routes with auth:admin + role middleware; protect customer routes with auth middleware where needed.
CSRF protection on all forms (Laravel default).
Responsive design — mobile-first for customer side (mockup shows mobile-width customer screens), desktop-optimized for admin side.
Seed a demo admin account and demo data so the app is testable immediately after php artisan migrate --seed.
Write PHPUnit/Pest feature tests for at least: registration, login, booking creation, appointment status update, low-stock tagging logic.
Document setup steps in README.md (env setup, migration, seeding, running the dev server).
8. Delivery / Working Style
Work module by module in the order listed above (Section 4 then Section 5), committing logically after each module.
After each module, log what was built to prompt.log per Section 2.
Ask for clarification only if something is truly ambiguous (e.g., payment gateway integration for the "Down Payment Reference Number" field — assume manual admin verification against GCash unless told otherwise, no live payment gateway integration required).
Do not fabricate scope beyond what's listed — no features outside this spec unless explicitly requested later."

### Task: Running the app locally via XAMPP/Apache at localhost/BTA/public/
* Prompt used: "run locally eg switch localhost/BTA/public/"

### Task: Fixing "Firefox can't connect" on http://localhost:8000
* Prompt used: "The site at http://localhost:8000 keeps failing with Firefox saying it is unable to connect. Make it always reachable. Set up a detached always-on server."

### Task: Building the detached dev-server supervisor
* Prompt used: "Pick the detached always-on server option (not a Windows login autostart) and keep http://localhost:8000 up, restarting it automatically if it dies."

### Task: Finishing the launcher scripts
* Prompt used: "continue pllease"

### Task: Tracking prompts in PROMPT_LOG.md
* Prompt used: "Prompt Log Format - Create a file named PROMPT_LOG.md in the root directory of your project using this format: ### Task: [e.g., Creating the Education Section] * Prompt used: \"Create a responsive Blade component for my educational history using Tailwind CSS."

### Task: the navbar logo use the C:\xampp\htdocs\BTA\public\images\logo.png
* Prompt used: "the navbar logo use the C:\\xampp\\htdocs\\BTA\\public\\images\\logo.png"
* Recorded: 2026-09-27 14:20:05 (session backfill)

### Task: same in the footer logo 
* Prompt used: "same in the footer logo "
* Recorded: 2026-09-27 14:22:41 (session backfill)

### Task: then make sure that all my prompts even i clocse or back will record all
* Prompt used: "then make sure that all my prompts even i clocse or back will record all my prompts"
* Recorded: 2026-09-27 14:24:20 (session backfill)

### Task: tab name mus tbe
* Prompt used: "tab name mus tbe \n\nHOME | Balai ti Arjud\nLOG IN | Balai ti Arjud \n\ndo it to all sections/features"
* Recorded: 2026-09-27 14:29:30 (session backfill)

### Task: home sections only remove the following
* Prompt used: "home sections only  remove the following \n- About Section \n- Inside the Lounge\nBeautiful moments, captured\n\nStep in, relax, and let our team handle the rest.  (this section) \n- Book Online card)\n\nin the tabs\n- HOME | Balai ti Arjud (showing) instead PROMO | Balai ti Arjud \n- BROWSE SERVICES to SERVICES | Balai ti Arjud"
* Recorded: 2026-09-27 14:35:10 (session backfill)

### Task: no once i click the promo it directs me to the promo section and the ...
* Prompt used: "no once i click the promo it directs me to the promo section and the tab name must be \nPROMO | Balai ti Arjud"
* Recorded: 2026-09-27 14:38:30 (session backfill)

### Task: no it change to the promo section not to remain
* Prompt used: "no it change to the promo section not to remain"
* Recorded: 2026-09-27 14:41:30 (session backfill)

### Task: no its not
* Prompt used: "no its not \n\nthe default public is HOME then is i click the services it goes to services then it must be same on the promo it directcs me to the promo "
* Recorded: 2026-09-27 14:52:00 (session backfill)

### Task: continue
* Prompt used: "continue"
* Recorded: 2026-09-27 14:54:00 (session backfill)

### Task: is all my prompts saved??
* Prompt used: "is all my prompts saved??"
* Recorded: 2026-09-27 14:57:00 (session backfill)
