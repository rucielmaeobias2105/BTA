# Deployment & Migration Checklist

**System:** Salon Booking & Inventory System
**Target deployment:** ░░░ **TO BE FILLED IN** — hosting platform
**Plan subscribed:** ░░░ **TO BE FILLED IN** — e.g. free / shared / VPS tier
**Prepared:** October 2, 2026

> Placeholders marked ░░░ are yours to complete. Everything else was verified against the
> repository.
>
> **Repository and Project Deployment is 30% of the rubric — the largest single criterion.**
> Fill in the two placeholders above, then work the checklist. §1.3 is the highest-risk
> item on this page.

---

## 1. Version requirements (verified)

### 1.1 Runtime

| Component | Required | Notes |
|---|---|---|
| PHP | **8.3** or newer | Project verified on 8.3.33 (ZTS build) |
| Laravel | **11.x** | Verified on 11.56.1 |
| PHP extensions | `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `curl`, `bcmath`, `zip`, `gd` (image validation) | `gd` is needed by the `image` validation rule |
| Composer | 2.x | |
| Node.js | 20 LTS or newer | Only needed to **build** assets; not needed at runtime if `public/build` is committed |
| npm | 10+ | |
| Database | **MySQL 8.0** or MariaDB 10.6+ | Migrations use `enum`, `json`, `change()` (requires doctrine/dbal behaviour), and `information_schema` queries |
| Web server | Apache or Nginx | See §2 |
| Composer packages | `laravel/framework ^11`, `laravel/tinker`, plus dev: `phpunit`, `mockery`, `faker`, `nunomaduro/collision`, `spatie/laravel-ignition`, `laravel/sail`, `pdo_sqlite` | |

**Verify before deploying:**

```bash
php -v
php -m | grep -Ei "pdo_mysql|mbstring|openssl|gd|fileinfo|curl|bcmath|zip"
composer --version
node -v && npm -v
mysql --version
```

### 1.2 Storage and cache drivers

Already configured for shared-hosting friendliness — both default to the **database**, so no
Redis or extra service is required for a first deployment:

| Setting | `.env.example` value | Note |
|---|---|---|
| `SESSION_DRIVER` | `database` | No file-permission issues on shared hosting |
| `CACHE_STORE` | `database` | |
| `QUEUE_CONNECTION` | `database` | ⚠ See §1.3 |
| `FILESYSTEM_DISK` | `local` | Uploads go on an explicit `public` disk |

### 1.3 ⚠ Queue worker and scheduler — the most commonly missed step

Two background processes are configured but neither is started by a plain deployment:

| Process | Command | Why it matters |
|---|---|---|
| **Scheduler** | `php artisan schedule:run` **every minute** via cron | Runs the nightly auto-archive of appointments completed >30 days ago (`routes/console.php`, 02:00 daily). Without it, archiving never happens. |
| **Queue worker** | `php artisan queue:work --tries=3` as a supervised daemon | Notifications and mail. **Without it, no confirmation or status email is ever sent.** |

With `QUEUE_CONNECTION=database`, jobs sit in the `jobs` table until a worker picks them up.
On a shared host with no daemon control, either:

- **Option A (simplest):** set `QUEUE_CONNECTION=sync` so jobs run inline during the
  request. Mail then depends on the request succeeding, but nothing is left stranded.
- **Option B:** run the worker via cron every minute:
  `* * * * * cd /path/to/project && php artisan queue:work --stop-when-empty --max-jobs=50 >> /dev/null 2>&1`

**Also note:** `MAIL_MAILER` defaults to `log` (`config/mail.php:19`). Unless you configure
SMTP, **mail is written to the log file and never sent.** The app looks complete while
silently delivering nothing. See §4.

### 1.4 Cron entry

```cron
* * * * * cd /path/to/BTA && php artisan schedule:run >> /dev/null 2>&1
```

---

## 2. Server configuration

### 2.1 Document root

Must point at **`public/`**, never the project root:

| Server | Setting |
|---|---|
| Apache | `DocumentRoot /path/to/BTA/public` |
| Nginx | `root /path/to/BTA/public;` |

Note: this project was developed under `C:\xampp\htdocs\BTA`, i.e. served from a
**subdirectory**. If you deploy to a domain root, remove any subdirectory assumptions; if
you deploy to a subdirectory, ensure `asset()` and `url()` generate the prefix. The app
already avoids one root-absolute assumption — the session-status probe is generated via
`route()` specifically because of this (`layouts/customer.blade.php:30`).

### 2.2 Required writable paths

| Path | Permission | Needed for |
|---|---|---|
| `storage/` and `storage/framework/{cache,sessions,views}` | **writable** | Caches, compiled views, logs |
| `bootstrap/cache/` | **writable** | Cached config/routes |
| `public/storage` | symlink | Serving uploaded images |

```bash
php artisan storage:link     # creates public/storage -> storage/app/public
```

### 2.3 Apache modules

`mod_rewrite` is **required** (clean URLs). `mod_headers` is required for the security and
no-store headers the app emits.

```apache
<VirtualHost *:80>
    ServerName ░░░
    DocumentRoot /path/to/BTA/public

    <Directory /path/to/BTA/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  /path/to/BTA/storage/logs/error.log
    CustomLog /path/to/BTA/storage/logs/access.log combined
</VirtualHost>
```

### 2.4 Nginx essentials

```nginx
root /path/to/BTA/public;
index index.php;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}

# Never serve dotfiles
location ~ /\. { deny all; }
```

**If you deploy to a subdirectory** (`/BTA/`), add
`location /BTA/public/ { alias /path/to/BTA/public/; }` and adjust `root`. Test carefully —
this is the most common deployment failure for this project.

---

## 3. Environment variables

**Names only — no values.** Full list with the values that ship in `.env.example` is in that
file. Create `.env` on the server; **never** commit it.

### 3.1 Must be set for production

| Variable | Why |
|---|---|
| `APP_ENV` | **`production`** — `.env.example` ships `local` |
| `APP_DEBUG` | **`false`** — `.env.example` ships `true` ⚠ **see §3.4** |
| `APP_URL` | Full public URL, `https://…` |
| `APP_KEY` | `php artisan key:generate --show`. **Never copy the one from any committed file** ⚠ |
| `APP_TIMEZONE` | Match the salon's timezone — booking times depend on it |
| `LOG_CHANNEL` / `LOG_LEVEL` | `stack` / `info` or `warning` in production |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Database credentials |
| `SESSION_DRIVER` | `database` |
| `SESSION_LIFETIME` | Minutes |
| `SESSION_SECURE_COOKIE` | **`true`** ⚠ not in `.env.example`; see §3.4 |
| `SESSION_SAME_SITE` | `lax` (default) or `strict` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `sync` or `database` — see §1.3 |
| `MAIL_MAILER` | `smtp` ⚠ see §4 |
| `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` / `MAIL_ENCRYPTION` | SMTP credentials |
| `MAIL_FROM_ADDRESS` | Must be a mailbox you control |

### 3.2 Optional

`APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE`, `BCRYPT_ROUNDS` (12),
`LOG_STACK`, `LOG_DEPRECATIONS_CHANNEL`, `BROADCAST_CONNECTION`, `FILESYSTEM_DISK`,
`CACHE_PREFIX`, `MEMCACHED_HOST`, `REDIS_*`, `MAIL_SCHEME`, `MAIL_FROM_NAME`,
`VITE_APP_NAME`, `PHP_CLI_SERVER_WORKERS`, `DB_QUEUE`.

### 3.3 Never commit

`.env`, `.env.backup`, `.env.production`, `.env.devbak`, `storage/*.key`, `public/storage`,
`node_modules`, `vendor`, `public/build` (if you build on the server).

> ⚠ **`.env.devbak` is currently tracked in git and contains a real `APP_KEY`.** Remove it
> from tracking, add it to `.gitignore`, and rotate `APP_KEY` before this goes live. See
> `audit-report.md` §6.1.

### 3.4 ⚠ Three production overrides the example file does not give you

The application defaults are safe; `.env.example` is not. Copy it and change these three:

```dotenv
APP_ENV=production        # was: local
APP_DEBUG=false           # was: true    <-- leaks stack traces, env values, file paths
SESSION_SECURE_COOKIE=true # absent       <-- session cookie over HTTPS only
```

**If `APP_DEBUG` is left `true` on a public deployment, any error page discloses the
framework version, the file system layout and — depending on the driver — database or mail
credentials.** This is the single most likely deployment mistake in this project.

---

## 4. Mail setup

The app sends 12 kinds of notification. With `MAIL_MAILER=log` (the default) they are
written to `storage/logs/laravel.log` and **never delivered**.

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.░░░
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=░░░
MAIL_PASSWORD=░░░
MAIL_FROM_ADDRESS=░░░
MAIL_FROM_NAME="${APP_NAME}"
```

**Verify with the included diagnostic command rather than by booking a test appointment:**

```bash
php artisan mail:check      # app/Console/Commands/CheckMailConfigCommand.php
```

Expect a real inbox message within seconds. Then confirm the queue is actually being worked
(§1.3) — a correct SMTP configuration with no worker still delivers nothing.

---

## 5. Deployment steps

```bash
# 1. Dependencies
composer install --no-dev --optimize-autoloader
npm ci && npm run build          # skip if public/build is committed
composer dump-autoload -o

# 2. Environment
cp .env.example .env
php artisan key:generate         # generates a FRESH key - do not reuse a committed one
# edit .env per section 3 above

# 3. Clear caches left over from development
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan optimize:clear

# 4. Schema
php artisan migrate --force

# 5. Uploads
php artisan storage:link

# 6. Seed  ⚠ READ audit-report.md section 8.4 FIRST
php artisan db:seed --force
```

### ⚠ Before you seed

`php artisan db:seed` creates **one** administrator (`admin@░░░.test`) and **one** customer
account whose details are a **real individual's** name, mobile number, email address and a
plaintext password, committed at `database/seeders/UserSeeder.php`.

Do **not** seed a public deployment until that is replaced with fictional data — see
`audit-report.md` §8.1. To deploy without seeding, create the administrator by hand:

```bash
php artisan tinker
>>> \App\Models\Admin::create([
  'first_name' => 'Fictional', 'last_name' => 'Administrator',
  'username' => 'admin', 'email' => 'admin@example.test',
  'password' => '░░░',        // cast hashes it
  'role' => 'admin', 'is_active' => true,
]);
```

Also note: seeding produces **0 inventory items, 0 technicians and 0 appointments**, so the
admin inventory list, the low-stock screen, the technician picker and the appointment queue
will all be empty on a freshly seeded deployment (`audit-report.md` §8.4).

### 7. Verify the deployment

```bash
php artisan about                # environment, drivers, cache status
php artisan migrate:status       # every migration must show "Ran"
php artisan route:list           # ~100 routes registered
curl -I https://░░░/up           # /up health endpoint, must return 200
```

Then, in a browser:

| Check | Expected |
|---|---|
| `/` | Landing page renders |
| `/services` | Catalogue renders; **images not broken** |
| `/admin/login` → sign in | Admin panel loads |
| Admin → Inventory, Technicians, Appointments | ⚠ Empty unless seeded (see above) |
| Book a test appointment end to end | Confirmation email arrives |
| View source on an authenticated page | No `APP_DEBUG` stack trace |
| `curl -I https://░░░` | `Set-Cookie` carries `Secure` and `HttpOnly` |

---

## 6. Backup & rollback checklist

Required for Chapter IV's "System Migration & Cutover Strategy", which the rubric scores
under Technical Design & Software Proposal Blueprinting. §6.3 is yours to complete from
your host's control panel.

### 6.1 What must be backed up

| # | Item | Why | Method |
|---|---|---|---|
| 1 | **Database** | Irreplaceable. Holds every booking, customer record, allergy note and amount | `mysqldump` or the host's backup tool |
| 2 | **`storage/app/public/`** | **All uploaded images.** Excluded from `public/build`, not in git | tar/zip |
| 3 | **`.env`** | Not in git. Without it, `APP_KEY` and all credentials are lost | Secure copy, encrypted |
| 4 | **Project source** | Recoverable from git — but only if it is pushed | git |
| 5 | **`public/build/`** | Rebuildable, but only with the same npm version | Rebuild or copy |

> **The uploads folder is the one people forget.** `storage/app/public/` holds service,
> category, technician, promo and profile photos. Restoring the database without it leaves
> every booking's history intact but every image broken.

### 6.2 Backup commands

```bash
# Database
mysqldump -u ░░░ -p --single-transaction --routines --triggers ░░░ \
  > backup_$(date +%Y%m%d_%H%M).sql

# Uploads
tar -czf uploads_$(date +%Y%m%d_%H%M).tar.gz storage/app/public/

# Environment (securely - never commit)
cp .env .env.backup.$(date +%Y%m%d)     # store off-server, encrypted
```

Use `--single-transaction` on InnoDB so the dump is consistent without locking the site.

### 6.3 Platform backup features — ░░░ TO BE FILLED IN

| Question | Your answer |
|---|---|
| Hosting platform | ░░░ |
| Plan subscribed | ░░░ |
| Does the plan provide automated database backups? | ░░░ |
| Does it back up **files** as well as the database? | ░░░ |
| Backup frequency and retention window | ░░░ |
| Can a backup be restored to a specific point in time? | ░░░ |
| Where are backups stored, and are they encrypted? | ░░░ |
| Who else can access the account (instructor as collaborator)? | ░░░ |

### 6.4 Rollback procedure

**Before any deployment:**

1. Take all five backups in §6.1.
2. Note the current commit: `git rev-parse HEAD`
3. Note the current migration state: `php artisan migrate:status`

**If the deployment fails — before any migration ran:**

```bash
git checkout <previous-commit>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan optimize:clear
php artisan storage:link
# no database restore needed - schema was untouched
```

**If a migration already ran, roll the schema back:**

```bash
php artisan migrate:rollback --step=1   # every migration has a down()
# verify
php artisan migrate:status
```

> ⚠ Rolling back is not always safe. If new data was written under the newer schema, the
> `down()` will drop it. For anything beyond a single reversible step, **restore the
> database backup instead** — that is the reliable path.

**If the database must be restored:**

```bash
mysql -u ░░░ -p ░░░ < backup_YYYYMMDD_HHMM.sql
php artisan optimize:clear
php artisan storage:link
```

Then re-verify with the §5 checklist.

### 6.5 Rollback trigger criteria

Roll back if any of these are true after deployment:

- Any 500 error on a public page
- The health endpoint `/up` does not return 200
- Login fails for the administrator
- Confirmation emails are not being delivered
- An uploaded image is broken or missing
- `migrate:status` shows a pending or failed migration

---

## 7. Pre-submission checklist

Drawn from the instruction sheet — the items a grader will actually check.

| # | Requirement | Status |
|---|---|---|
| 1 | Application deployed and working online | ░░░ |
| 2 | Website link submitted in the instructor's Google Sheet | ░░░ |
| 3 | Repository link submitted in the same sheet | ░░░ |
| 4 | **Repository set to private** | ░░░ |
| 5 | **Instructor added as a collaborator** on the private repo | ░░░ |
| 6 | Hard-copy documentation submitted by **October 2, 2026, 2:00 PM** | ░░░ |
| 7 | All diagrams drawn in Draw.io / Lucidchart / EdrawMax — **no MS Word shapes or Paint** | ░░░ |
| 8 | No real names, addresses, phone numbers or personal data in the paper | ░░░ |
| 9 | AI interaction index log complete — **every** prompt, with manual refactoring noted | ░░░ |
| 10 | Be ready to defend any selected code line by line in the viva | ░░░ |

### ⚠ Two requirements that need action now

**§3.1 of the instruction sheet — mandatory destruction.** After the final grade is logged,
you must **delete everything gathered from the business, including the GitHub repository.**
Plan for this: keep a copy of your manuscript and diagrams outside the repository, because
they will go with it.

**§3.2 — the AIAS Level 3 interaction log.** "Make sure to log every prompt that was fed to
the coding agent." `docs/ai-interaction-log.md` in this folder is the template, seeded with
this session's entries. **Keep appending to it as you continue**, or you will not be able to
reconstruct it later — and this rubric line is checked directly.
