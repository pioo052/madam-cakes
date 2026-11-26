# MADAM CAKES — Database

Database name: **`madam_cakes_db`**
Engine: InnoDB · Charset: `utf8mb4_unicode_ci` · Money: `DECIMAL(12,2)`

## 1. Create and import

### Option A — phpMyAdmin
1. Open <http://localhost/phpmyadmin/>
2. **Databases** → create `madam_cakes_db` (collation `utf8mb4_unicode_ci`)
3. Select it → **Import** → choose `database/schema.sql` → Go
4. **Import** again → choose `database/seed.sql` → Go

### Option B — command line
```bash
C:/xampp/mysql/bin/mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS madam_cakes_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
C:/xampp/mysql/bin/mysql.exe -u root madam_cakes_db < database/schema.sql
C:/xampp/mysql/bin/mysql.exe -u root madam_cakes_db < database/seed.sql
```

> `schema.sql` starts with `DROP TABLE IF EXISTS`, so re-importing **erases all data**.
> Never re-import on a live database without a backup (`mysqldump`).

### Upgrading an existing installation

`schema.sql` is for new installations only. An existing database is upgraded with the
additive migration, which never drops a table:

```bash
C:/xampp/mysql/bin/mysql.exe -u root madam_cakes_db < database/migrate_v2.sql
```

It adds `users.avatar`, `users.closed_at` and the `contact_messages` table, and sets
`schema_version` to `2.0.0`. It is safe to run more than once.

## 2. Initial administrator

There is **no default admin account** and **no default password** in this repository.
Create the first administrator once, in the browser:

`http://localhost/madam-cakes/admin/setup.php`

The setup screen refuses to run if an administrator already exists. Passwords are
stored with `password_hash()` (bcrypt) and are never recoverable in plain text.

## 3. Table map

| Group | Tables |
|---|---|
| System | `app_meta` |
| Accounts | `users`, `user_addresses`, `contact_messages` |
| Catalogue | `categories`, `products`, `product_images`, `product_sizes`, `product_flavours`, `product_addons` |
| Cart | `carts`, `cart_items` |
| Custom cake | `cake_options`, `cake_layer_prices`, `custom_orders` |
| Delivery | `delivery_zones` |
| Orders | `orders`, `order_items`, `order_status_history`, `payments` |
| Social proof | `reviews`, `gallery`, `favourites` |
| Promotions | `coupons`, `coupon_usage` |
| Content | `business_settings`, `site_content`, `newsletter_subscribers` |
| Ops | `notifications`, `admin_activity_logs` |

## 4. Pricing model (owner-controlled)

* `products.base_price` — starting price of a cake, set by the owner (`0.00` = not priced yet).
* `product_sizes.price` — **full price for that size**, set by the owner.
* `product_flavours.surcharge`, `product_addons.price` — owner-defined extras.
* `cake_options.price` — per custom-cake option (occasion, shape, size, flavour, decoration).
* `cake_layer_prices.price` — per layer count.
* `delivery_zones.fee` / `free_over` — owner-defined delivery charges.
* `coupons` — percentage or fixed discount with limits and validity dates.

The server always recalculates totals from these tables. Browser-submitted prices are
ignored entirely. When a custom-cake estimate is `0` or the request needs special work,
the request is stored as `awaiting_quotation` and the owner enters `quoted_price`.

## 5. Order lifecycle

`pending → awaiting_quotation → awaiting_payment → confirmed → preparing → ready →
out_for_delivery → delivered`, plus `cancelled` / `rejected`.

Every status change is appended to `order_status_history` with the acting user and time.
Order items copy product name, size, flavour and unit price at the time of purchase, so
later price edits never rewrite history.

## 6. Payment recording

`payments` stores method, amount, provider reference and status
(`pending / verified / rejected / refunded`). No gateway is integrated in this release;
an administrator verifies mobile-money references manually and only then may the order
`payment_status` become `paid`.
