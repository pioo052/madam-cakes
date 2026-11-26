# MADAM CAKES

**Every Cake Tells a Sweet Story.**

A complete cake-boutique application for a Tanzanian bakery: storefront, customer
accounts, online ordering, custom cake quotations and a full studio administration
system. Pure PHP 8 + PDO + MySQL — no framework, no build step, no simulated data.

| | |
|---|---|
| Stack | PHP 8.1+ (tested on 8.4.27), PDO, MySQL/MariaDB, HTML5, CSS3, vanilla JS |
| Currency | TZS — every price is entered by the owner |
| Local URL | http://localhost/madam-cakes/ |
| Admin | http://localhost/madam-cakes/admin/login.php |
| Verification | `tools/e2e_test.php` — 114 HTTP/database checks |

---

## 1. Requirements

* XAMPP with Apache, MySQL/MariaDB and PHP 8.1 or newer — **or** Docker Engine with the Compose plugin for deployment (see [DOCKER.md](DOCKER.md))
* PHP extensions: `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `curl`
* `gd` is optional (only for server-side image resizing, which this release does not use)

## 2. Install

1. Start **Apache** and **MySQL** in the XAMPP control panel.
2. Create and import the database:

   ```bash
   C:/xampp/mysql/bin/mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS madam_cakes_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   C:/xampp/mysql/bin/mysql.exe -u root madam_cakes_db < database/schema.sql
   C:/xampp/mysql/bin/mysql.exe -u root madam_cakes_db < database/seed.sql
   ```

   If you already have the earlier schema, apply the additive migration instead of
   re-importing (it never drops anything):

   ```bash
   C:/xampp/mysql/bin/mysql.exe -u root madam_cakes_db < database/migrate_v2.sql
   ```

3. Open <http://localhost/madam-cakes/admin/setup.php> and create the first
   administrator. That page disables itself once an administrator exists.
4. Sign in to the admin and complete the studio's own data — see §5.

There is **no default administrator, password, product or price** in this repository.

### Run with Docker (optional)

A deployment stack — Apache, PHP 8.4 and MariaDB — is included:

```bash
cp .env.example .env      # set DB_PASS, DB_ROOT_PASS and APP_URL
docker compose up -d --build
```

The site is then at <http://localhost:8080>. `database/schema.sql` and
`database/seed.sql` are imported on the first start, and the database, uploads and logs
live in named volumes. First-run setup, HTTPS, backups and updates are covered in
[DOCKER.md](DOCKER.md).

In containers the configuration comes from environment variables — `MADAM_ENV`,
`MADAM_URL`, `MADAM_HTTPS`, `MADAM_DB_HOST`, `MADAM_DB_PORT`, `MADAM_DB_NAME`,
`MADAM_DB_USER`, `MADAM_DB_PASS` — instead of `config/config.local.php`.

### Machine-specific configuration

Create `config/config.local.php` (already blocked from web access) to override
credentials or the base URL:

```php
<?php
return [
    'env'     => 'development',
    'url'     => 'http://localhost/madam-cakes',
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'madam_cakes_db',
    'db_user' => 'root',
    'db_pass' => '',
];
```

For production set `'env' => 'production'`, a real database password and an HTTPS URL.
PHP errors are logged to `logs/error.log` and hidden from visitors in production.

## 3. What the application does

### Storefront

* **Home** — hero, categories, signature cakes, custom-cake invitation, surprise
  delivery, why-choose-us tiles, story, published reviews, contact band. Copy and
  contact details come from the database.
* **Cakes** — category filters, search, sort (newest, most loved, price), pagination,
  honest empty states.
* **Cake page** — gallery, owner-set sizes and prices, flavours, extras, quantity,
  favourites, reviews (only from customers with a delivered order).
* **Custom cake designer** — occasion, shape, size, flavour, decoration, cream colour,
  layers, message, date, reference photograph; the studio replies with a quotation
  that the customer can accept or decline.
* **Cart** — database-backed, server-recalculated prices, quantity updates, coupon codes.
* **Checkout** — sign-in required, pickup or delivery, delivery zones with owner fees
  and free-over thresholds, surprise-delivery fields, payment method, order summary.
* **Order tracking** — public tracking with the reference *plus* the matching email or
  phone.
* **Gallery and reviews** — real content only; empty states until the studio publishes.
* **Contact** — stores messages for the administrator (no fake "sent" mail).
* **WhatsApp** — click-to-chat buttons built from the configured number; hidden while
  the number is empty. No message claims payment or confirmation.

### Customer account

Overview, orders with a live status timeline, custom requests and quotations,
favourites, saved addresses, reviews, notifications, profile (including photo),
password change, and privacy-safe account closure (records are retained).

### Administration

Dashboard with live counts; order management with status workflow and history;
manual payment verification and recording; custom cake quotations and conversion to
orders; products with sizes, flavours, extras and photographs; categories; gallery;
review moderation with public replies; customers and account status; contact messages;
coupons; delivery zones; reports over a chosen period; website content; business
settings (identity, contact, social, commerce, logo).

## 4. Project structure

```
madam-cakes/
├── index.php              storefront home
├── cakes.php  cake.php    catalogue and cake detail
├── cart.php  checkout.php cart and checkout
├── custom-cake.php        custom cake designer
├── track.php              public order tracking
├── about.php  gallery.php  reviews.php  contact.php
├── auth/                  register.php, login.php, logout.php
├── account/               customer dashboard pages
├── admin/                 administration (includes/, setup.php, login.php + modules)
├── includes/              header, footer, helpers, auth, store library (denied)
├── config/                configuration and PDO (denied)
├── database/              schema.sql, seed.sql, migrate_v2.sql (denied)
├── Dockerfile             production Apache + PHP image
├── docker-compose.yml     app + MariaDB stack (see DOCKER.md)
├── docker/                entrypoint, Apache config, PHP limits
├── assets/
│   ├── css/               theme.css, site.css, store.css, admin.css
│   ├── js/                site.js (drawer, reveal, parallax)
│   ├── img/               brand imagery
│   └── uploads/           owner uploads; scripts cannot execute
├── tools/e2e_test.php     end-to-end verification (CLI only, denied over HTTP)
├── logs/                  PHP error log (denied)
├── APPLICATION_AUDIT.md   feature-by-feature audit with evidence
└── PROJECT_PROGRESS.md    build history and verification log
```

## 5. First-run checklist for the owner

1. **Settings → Business identity**: name, tagline, logo.
2. **Settings → Contact details**: phone, email, address, opening hours, map link.
   Blank fields stay hidden instead of showing empty placeholders.
3. **Settings → Social profiles**: full profile URLs.
4. **Settings → Ordering and payments**: accepted payment codes, minimum notice days,
   delivery/collection availability, payment instructions.
5. **Categories**: adjust or add the seeded categories.
6. **Products**: add each cake, its base or per-size prices, flavours, extras and photos.
   A cake with no price is shown honestly as "price on enquiry" and cannot be ordered.
7. **Delivery zones**: name, region, fee, optional free-over threshold.
8. **Coupons** (optional), **Gallery**, **Website content**.
9. Place one real test order yourself and verify the studio workflow.

## 6. Verification

```bash
C:/xampp/php/php.exe -l path/to/file.php     # syntax check any PHP file
C:/xampp/php/php.exe tools/e2e_test.php      # full end-to-end run
```

The script creates a temporary administrator and customer, exercises registration,
login, catalogue, uploads, cart, coupon, delivery fee, checkout, payment verification,
status updates, custom quotation conversion, reviews, favourites, addresses, password
change, cancellation, account closure and the authorisation boundaries, then deletes
every test record. Result at the time of writing: **114 passed, 0 failed**.

`APPLICATION_AUDIT.md` lists each feature with its status and the exact check still
outstanding where one exists.

## 7. Security model

* PDO prepared statements everywhere; no concatenated SQL values.
* `password_hash()` / `password_verify()`, timing-safe login, rehash on sign-in.
* CSRF tokens on every state-changing form; POST-only logout.
* `require_admin()` / `require_login()` plus per-row ownership filters.
* All output escaped with `htmlspecialchars`; no raw HTML from the database.
* Uploads: size limit, MIME sniffing, extension whitelist, PHP/script signature scan,
  non-executable upload directory.
* Orders, coupon usage and conversions run in transactions; prices and totals are
  always recalculated from owner-managed rows, never taken from the browser.
* Administrative actions are written to `admin_activity_logs`; every order status
  change is appended to `order_status_history`.
* `config/`, `database/`, `includes/`, `logs/` and `tools/` are denied by Apache;
  `.sql`, `.log`, `.md` files cannot be downloaded.

## 8. Troubleshooting

| Symptom | Check |
|---|---|
| "Database connection failed" | MySQL running? credentials in `config/config.local.php` |
| 403 on every page | Apache rewrite/permissions; ensure `.htaccess` is honoured (`AllowOverride All`) |
| Uploads refused | `upload_max_filesize` / `post_max_size`, folder write permission on `assets/uploads/` |
| Prices show "on enquiry" | Set `products.base_price` or an active `product_sizes.price` |
| WhatsApp buttons missing | Set `whatsapp_number` in Settings (international format, digits only) |
| Admin setup page is disabled | An administrator already exists; use `admin/login.php` |
| Container has no database | `docker compose logs db`; changing `DB_PASS` after the first start requires rotating it inside MariaDB (DOCKER.md §2) |
| Styles look stale | Hard-refresh; asset cache headers are 7–30 days |

## 9. Deliberate boundaries

No payment gateway is integrated: the studio verifies each mobile-money or bank
reference manually, exactly as the business rules require. No email/SMS service is
configured; updates are delivered as in-app notifications. Product photographs, prices
and contact details are the owner's real business data and are never seeded.
