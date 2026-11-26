# MADAM CAKES — Project Progress

Every milestone is recorded here so development can resume from the last verified
checkpoint, on any machine or session.

---

## Current status

| Item | Value |
|---|---|
| Phase | **Complete — storefront, customer accounts and administration** |
| State | **Implemented and verified** (2026-11-26) |
| App URL | http://localhost/madam-cakes/ |
| Admin | http://localhost/madam-cakes/admin/login.php (setup once via `admin/setup.php`) |
| Verification | `tools/e2e_test.php` — **114 checks, 0 failures** |
| Deployment | `Dockerfile`, `docker-compose.yml` (added 2026-11-26; not built on the dev machine — Docker is not installed there) |
| Audit | `APPLICATION_AUDIT.md` — status of every feature and remaining checks |

---

## 1. Environment (verified)

| Check | Result |
|---|---|
| OS / dev stack | Windows, XAMPP at `C:\xampp` |
| PHP CLI | 8.4.27 (ZTS, VC22 x64) |
| PHP extensions | `pdo_mysql`, `PDO`, `mbstring`, `fileinfo`, `openssl`, `curl` present |
| GD extension | disabled in `php.ini` (no server-side thumbnails; the app sizes images with CSS) |
| Apache | 2.4.65, port 80 |
| MySQL/MariaDB | 10.11.16, port 3306 |
| DB credentials | `root`, empty password (development only) |
| Upload limits | `upload_max_filesize` 40M, `post_max_size` 40M, `memory_limit` 512M |
| Schema version | 2.0.0 (`app_meta`) |

### Restart the stack

```bash
"C:/xampp/mysql/bin/mysqld.exe" --defaults-file="C:/xampp/mysql/bin/my.ini" --console
"C:/xampp/apache/bin/httpd.exe"
```

### Fresh import / upgrade

```bash
C:/xampp/mysql/bin/mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS madam_cakes_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
C:/xampp/mysql/bin/mysql.exe -u root madam_cakes_db < database/schema.sql
C:/xampp/mysql/bin/mysql.exe -u root madam_cakes_db < database/seed.sql
# existing installations only (additive, never drops):
C:/xampp/mysql/bin/mysql.exe -u root madam_cakes_db < database/migrate_v2.sql
```

---

## 2. Build history

### Phase 1 — Foundation (complete)

- [x] Environment audit (PHP, Apache, MySQL, extensions, upload limits)
- [x] UI reference retrieved and inspected (`1147×860`, magenta/cream/gold, arch hero)
- [x] `schema.sql` (now 30 tables, InnoDB, utf8mb4, FKs, DECIMAL money), `seed.sql`
      (10 categories, 38 custom options, 22 settings, 12 content blocks — no invented data)
- [x] `config/config.php`, `config/database.php` (PDO, transactions), `includes/functions.php`,
      `includes/auth.php`, `admin/setup.php`, `assets/css/theme.css`, `.htaccess`

### Phase 2 — Storefront foundation (complete)

- [x] `includes/header.php`, `includes/footer.php`, real `index.php`
- [x] `assets/css/site.css`, `assets/js/site.js`, generated brand imagery
- [x] DB-backed cart badge, mobile drawer, reduced-motion aware reveal/parallax
- [x] Bugs fixed: heading used body text; GSAP could hide the hero; reveal safety net
  fired too early; hero parallax could expose a white edge; duplicated social/payment
  maps and inline styles removed.

### Phase 3 — Catalogue and products (complete)

- [x] `cakes.php` (category filter, search, sort, pagination, empty states)
- [x] `cake.php` (gallery, sizes, flavours, extras, favourites, reviews)
- [x] `includes/store.php` — price calculation, cart, coupons, orders, reviews
- [x] Admin products (sizes, flavours, extras, photos), categories
- [x] `assets/css/store.css`

### Phase 4 — Customer accounts (complete)

- [x] Registration, login, POST-only logout
- [x] Account overview, profile, password change
- [x] Orders list/detail with status timeline, public tracking
- [x] Addresses, favourites, custom requests, reviews, notifications
- [x] Privacy-safe account closure (records retained); `users.avatar`, `users.closed_at`
- [x] `database/migrate_v2.sql`, `contact_messages` table

### Phase 5 — Ordering, payments and administration (complete)

- [x] Cart, coupon application, checkout with delivery zones and surprise delivery
- [x] Transactional order creation, server-side totals, notifications
- [x] Custom cake designer with reference upload, quotation, acceptance, conversion
- [x] Admin dashboard, orders, payments (manual verification), custom requests
- [x] Customers and account status, reviews moderation, messages
- [x] Coupons, delivery zones, reports (SQL aggregates), content, settings
- [x] `assets/css/admin.css`, admin layout, login/logout, audit logging

---

## 3. Verification log

**2026-11-26 — extended run (114 checks)**

```
same suite, plus the paths previously listed as untested
                                                     -> PASSED: 114   FAILED: 0
```

New coverage: category create/edit/delete; gallery upload/edit/delete with the file
removed; business settings saved and reloaded (original values restored afterwards) and
the saved WhatsApp number rendering as a `wa.me` link; surprise-delivery recipient fields;
the free-delivery threshold waiving the fee and the total; CSRF refusal on administrator
and customer POSTs. The run now also works on a site that already has its owner
administrator: first-time setup stays closed (403) and the test administrator is added
directly, so no owner account is touched. Two fixes came out of this pass: a CSRF
refusal was answered by Apache as HTTP 500 because of the unofficial 419 status (now
403), and four unused helpers (`csrf_input`, `category_by_id`, `product_by_id`,
`json_response`, `order_status_class`) were removed.

The live database now holds the owner's own records, so it is no longer empty after a
run; the script only deletes the rows it created.

**2026-11-21 — full application run**

```
PHP lint (all 51 PHP files)                          -> No syntax errors
Fresh schema import (scratch database)               -> 30 tables, avatar column, 10 categories
GET public pages (15 routes)                         -> 200 / expected 302, no PHP warnings
temporary e2e run: tools/e2e_test.php                -> PASSED: 93   FAILED: 0  (later extended to 114)
```

The 93 checks cover: admin setup/login and all 19 admin screens; product + size
creation; PNG upload accepted, PHP-disguised upload rejected, photo deletion; catalogue
and detail rendering; customer registration with hashed password and customer role;
cart totals; coupon discount; delivery fee; checkout and order rows; payment
verification; status update and customer-visible history; custom request → quotation →
acceptance → conversion; profile, password, address, favourite; review submission and
approval; contact message; order cancellation; account closure; tracking with correct
and incorrect contact; authorisation boundaries (redirect, 403, 404).

Test records are removed by the script; the live database was verified afterwards to
contain 0 users, 0 products, 0 orders, 0 coupons and 0 messages.

Not verified visually: the new inner pages were not screenshot-reviewed at every
breakpoint (see `APPLICATION_AUDIT.md` §5).

---

## 4. Known gaps and owner actions

1. **Business data is empty by design.** The owner adds categories, cakes, prices,
   delivery zones, coupons, gallery, contact details and the WhatsApp number.
2. **GD disabled** — no server-side resizing; enable `extension=gd` if thumbnails are wanted.
3. **Database credentials** are XAMPP defaults; use `config/config.local.php` for anything
   beyond local development.
4. **No payment gateway** — payments are verified manually, as the specification requires
   that no order is auto-marked paid.
5. **No email/SMS delivery** — notifications are stored and shown in the dashboards.
   A provider is an external dependency.
6. **Self-service password reset** is not implemented; the login page directs customers to
   the studio.
7. **English only** for now, as scheduled; Kiswahili is a later localisation phase.
8. **Visual QA** at 390 px / 1440 px across the new pages remains outstanding.
9. `database/schema.sql` drops tables on import — never run it over live data.

---

## 5. Next recommended tasks

1. Owner data entry and one real end-to-end order.
2. Visual QA pass on catalogue, account and admin screens at mobile widths.
3. Promote the remaining "implemented but untested" items in `APPLICATION_AUDIT.md` §4 to
   tested by extending `tools/e2e_test.php`.
4. Optional integrations when the owner chooses providers: SMTP/email notifications,
   mobile-money API, password-reset email, Kiswahili translations.

---

## 6. Docker deployment (2026-11-26)

Added a deployment stack without changing how the application runs:

* `Dockerfile` — `php:8.4-apache` with `pdo_mysql` and `mbstring`, `mod_rewrite`,
  `AllowOverride All`, PHP upload limits of 8 MB and non-executable uploads.
* `docker-compose.yml` — the app plus `mariadb:10.11`; `database/schema.sql` and
  `database/seed.sql` are imported when the data volume is first created, and the
  database, uploads and logs live in named volumes.
* `docker/entrypoint.sh` — waits up to 120 s for the database, fixes volume ownership,
  then starts Apache.
* `config/config.php` — database credentials and the HTTPS flag can now come from
  `MADAM_DB_*` / `MADAM_HTTPS` environment variables (containers); `config.local.php`
  still wins on a normal XAMPP install and the previous defaults are unchanged.
* `.env.example`, `.dockerignore`, `.gitignore` and `DOCKER.md` (deploy, HTTPS, backups,
  updates, running the e2e suite inside the container).

Verified locally: `php -l config/config.php` clean, `bash -n docker/entrypoint.sh` clean,
and the existing suite still passes. **Not verified:** the image has not been built or
run — Docker is not installed on this development machine, so the compose stack is
untested here.
