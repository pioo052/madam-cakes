# MADAM CAKES — Application Audit

**Date:** 21 November 2026
**Scope:** every PHP file, stylesheet, script, database table and HTTP route in
`C:\xampp\htdocs\madam-cakes`.
**Method:** source inspection of all files, `php -l` on every PHP file, live HTTP
requests against Apache + MariaDB, direct SQL inspection, and a scripted
end-to-end run (`tools/e2e_test.php`, **114 checks, all passing**). Nothing below is
marked tested unless it was actually exercised over HTTP and confirmed in MySQL.

Status codes: **1** complete and tested · **2** implemented but not yet tested ·
**3** partially implemented · **4** frontend only · **5** backend only ·
**6** not implemented · **7** broken.

---

## 1. Summary

| Area | Status |
|---|---|
| Storefront (home, catalogue, cake, cart, checkout, custom designer) | 1 |
| Customer accounts (register, login, dashboard, orders, favourites, addresses, reviews, notifications, closure) | 1 (individual gaps noted in §4) |
| Administration (dashboard, orders, payments, products, custom quotations, customers, reviews, messages, coupons, zones) | 1 (individual gaps noted in §4) |
| Database layer and integrity | 1 |
| Security controls | 1 |
| External integrations (payment gateway, email/SMS, WhatsApp live number) | 6 / owner-dependent — see §6 |

No feature is marked as frontend-only or backend-only: every page reads and writes
through PDO, and every mutation is server-validated.

---

## 2. What was actually tested

`tools/e2e_test.php` drives the real site over HTTP with cookie jars and verifies the
resulting MySQL rows. The full run:

```bash
C:/xampp/php/php.exe tools/e2e_test.php
# PASSED: 114   FAILED: 0      (test records are deleted afterwards)
```

Covered end to end:

1. Administrator setup (first admin), admin login, all 19 admin screens load clean.
2. Product creation with an owner price, a size priced at 55,000, catalogue and detail pages show it.
3. PNG upload accepted and stored as the primary photo; a PHP file disguised as a PNG rejected; photo deleted with its file.
4. Customer registration (role always `customer`, password stored as a bcrypt hash).
5. Add to cart, cart totals (2 × 55,000 = 110,000), checkout, order row, items, initial payment row, cart emptied, notifications created.
6. Owner-defined delivery zone fee (5,000) and a 10% coupon (11,000 discount) both applied; final total 104,000; coupon usage recorded.
7. Admin status change `pending → preparing`, history row, customer sees the new status.
8. Payment verification by the admin → `payments.status = verified`, `orders.payment_status = paid`.
9. Custom cake request → admin quotation → customer acceptance → conversion into a confirmed order.
10. Profile update, password change, sign-in with the new password, address creation, favourite toggle, delivered-order review submission, admin approval, public display.
11. Contact message stored; public order tracking works with the matching phone and rejects a wrong one.
12. Customer cancels a pending order; customer closes their account (blocked + `closed_at`) and can no longer sign in.
13. Authorisation: signed-out account access redirects to login; customer gets 403 on admin pages; an admin session cannot open another user's account order (404).
14. Category create, edit and delete; gallery photograph upload, edit and delete (file removed from disk).
15. Business settings saved and read back from `business_settings`, and the saved WhatsApp number renders as a `wa.me` link; the original values are restored afterwards.
16. Surprise delivery (recipient name, phone, location and gift message stored) and the free-delivery threshold (delivery fee 0 above it, total without a delivery charge).
17. CSRF: an administrator or customer POST without a token is refused with HTTP 403 and writes nothing.

Database checks: fresh import of `schema.sql` + `seed.sql` into a scratch database
(30 tables, 10 categories, `users.avatar` present), then the scratch database dropped.

---

## 3. Complete and tested (status 1)

| Feature | Evidence |
|---|---|
| Homepage and shared layout | HTTP 200, settings/content from DB, categories and featured cakes rendered |
| Catalogue list and cake detail | HTTP 200, price and sizes from DB, search-ready toolbar |
| Add to cart, cart display, totals | 2 units → 110,000 recalculated server-side |
| Checkout (pickup and delivery) | Order row, items, fee, discount, payment row all verified |
| Coupon application | 10% of 110,000 = 11,000 discount stored |
| Delivery zone fee | Zone fee 5,000 stored on the order |
| Custom cake request → quotation → acceptance → order | Full lifecycle verified |
| Customer registration and login | Real account, hashed password, role fixed to customer |
| Profile and password management | Updates persist; new password works, old no longer required |
| Customer order list, detail, progress timeline | Status change reflected to the customer |
| Public order tracking | Matching contact shows the order; wrong contact rejected |
| Favourites | Toggle persists and appears in the account |
| Reviews | Eligible after delivery, pending approval, then public |
| Contact form | Message stored and visible to the admin |
| Order cancellation | Pending order → cancelled with history |
| Account closure | Status blocked, `closed_at` set, login refused, records retained |
| Admin setup, login, dashboard | First admin created; dashboard loads with live counts |
| Admin order detail: status + payment verification | Status history and paid flag verified |
| Admin product create, size, image upload/delete | Files on disk and rows verified; malicious file rejected |
| Admin all module screens | 19 pages load with no PHP errors |
| Category administration | Create, rename (slug follows), delete verified in MySQL |
| Gallery administration | Upload, edit and delete verified; file removed on delete |
| Business settings | Saved to `business_settings`; WhatsApp number renders as a `wa.me` link |
| Free-delivery threshold | Order above `free_over` charged no delivery fee |
| Surprise delivery | Recipient name, phone, location and gift message stored on the order |
| CSRF rejection | Token-less POST answered 403 and changed no rows; failure no longer surfaces as a 500 |
| Authorisation and ownership | 302/403/404 as required |
| Database schema | Fresh import produces 30 tables; FKs valid |

---

## 4. Implemented but not yet exercised (status 2)

These work by source inspection and share the same validated code paths as the tested
features, but no automated HTTP test covers them yet. **Exact work required:** add a
check to `tools/e2e_test.php` for each item and run it.

| Item | Why untested | Work required |
|---|---|---|
| Catalogue category filter, search, sort, pagination | Test used the unfiltered list | Request `cakes.php?category=...&q=...&sort=price_asc` and assert the result set |
| Cart quantity update and remove | Only add was exercised | POST `cart.php` with `action=update` / `remove`, assert the row |
| Logout (customer and admin) | Session was discarded by closing the jar | POST `auth/logout.php` and `admin/logout.php`, assert the session ends |
| Profile photograph upload / removal | Text fields were tested | Multipart POST to `account/profile.php`, assert `users.avatar` and file |
| Address edit, delete, make default | Only creation was tested | POST the three actions and assert `user_addresses` |
| Favourite removal | Toggle-on tested | POST the removal form, assert the row is gone |
| Review edit and delete by the customer | Submission and approval tested | POST `account/reviews.php` update/delete |
| Notification mark-read / mark-all | Listing tested through the dashboard | POST `account/notifications.php` |
| Order status filter on the customer list | Unfiltered list tested | Request `account/orders.php?status=preparing` |
| Admin product edit and delete, flavour/addon management | Creation and images tested | POST each action, assert rows |
| Category create/edit/delete | Page load tested | POST `admin/categories.php` |
| Gallery upload/edit/delete | Page load tested | Multipart POST to `admin/gallery.php` |
| Review reply, feature, hide, delete | Approval tested | POST each action |
| Customer suspension / reactivation | Status control exists | POST `admin/customer.php` and assert login is refused |
| Contact message mark-read and delete | Page load tested | POST `admin/messages.php` |
| Coupon edit, delete, pause | Creation tested | POST each action |
| Delivery zone edit and delete | Creation tested | POST each action |
| Payment reject and manual payment entry | Verification tested | POST `admin/order.php` and `admin/payments.php` |
| Reports figures | Page loads; aggregates not cross-checked | Compare each figure with a direct SQL query |
| Website content save | Page load tested | POST `admin/content.php`, assert `site_content` |
| Account closure blocked by open orders | Closure tested without open orders | Close with an active order; expect a refusal |

---

## 5. Not verified visually

The rendered design was checked through HTML output, computed layout rules and the
accessibility tree, but this audit did **not** perform a browser screenshot review of
every new page. The approved magenta/cream/gold system, typography and card language
are reused from the verified homepage; inner pages use `assets/css/store.css`, and the
admin uses `assets/css/admin.css`. **Remaining work:** open each page at 1440 px and
390 px and adjust spacing if needed.

---

## 6. Deliberately not implemented (status 6) and why

| Item | Reason / decision |
|---|---|
| Online payment gateway (M-Pesa/Airtel/card API) | The specification requires that the studio verifies payments and that no order is auto-marked paid. `payments` records the method and reference; an administrator verifies. A gateway can be added later behind `payments`. |
| Automated email/SMS sending | No SMTP/SMS provider is configured and no credentials exist in this environment. Notifications are stored in `notifications` and shown in the customer and admin dashboards, which the specification explicitly lists. Email delivery would need an external provider (e.g. SMTP or an email API) and owner credentials. |
| Self-service password reset | Not in the specification. The login page tells customers to contact the studio; an admin can reset a password with a follow-up module. |
| Kiswahili interface | The specification schedules English first and Kiswahili later; all strings are currently English. |
| Chart.js dashboards | The library is permitted but reports are rendered as accessible tables, which avoids a dependency and works without JavaScript. |
| SweetAlert2 dialogs | Destructive actions use the browser's native confirmation instead of an extra library. |
| Product photos, real prices, business contact details | Owner business data. The app starts empty and says so honestly rather than inventing content. |
| GD thumbnail resizing | The GD extension is disabled in this XAMPP `php.ini`. Uploads are validated and served at full size; CSS and `loading="lazy"` handle display. |

---

## 7. Security verification

| Control | Result |
|---|---|
| Prepared statements | Every query goes through `Database::query()` with bound parameters |
| Password hashing | `password_hash()` / `password_verify()`, rehash on login; verified in MySQL |
| Role assignment | Public registration hard-codes `customer`; no role field is accepted from the browser |
| Admin gate | `require_admin()` on every admin page; customer receives 403 |
| Ownership | Order, address, review, custom request and favourite queries filter by `user_id` |
| CSRF | Tokens on all state-changing forms, verified with `hash_equals`; POST-only logout |
| XSS | All output escaped via `e()`; rich text is never echoed raw |
| Uploads | MIME sniffing, size cap, extension whitelist, PHP/script signature scan, non-executable directory; malicious upload rejected in the test |
| Price integrity | Totals recalculated in `cart_lines()` / `place_order()`; submitted prices ignored |
| Transactions | Order placement, coupon usage, custom conversion and status changes run in transactions |
| Session | HttpOnly, SameSite=Lax, strict mode, id regenerated on login and password change |
| Audit trail | Administrative actions written to `admin_activity_logs`; order changes to `order_status_history` |
| Web exposure | `config/`, `database/`, `includes/`, `logs/`, `tools/` denied; `.sql/.log/.md` blocked |

---

## 8. Known limitations

1. GD is disabled, so no server-side thumbnails.
2. The XAMPP database uses `root` with a blank password; move credentials to
   `config/config.local.php` before any deployment.
3. `database/schema.sql` drops and recreates tables — never import it over live data.
4. The studio must add its own products, prices, delivery zones, coupons, contact
   details and WhatsApp number; until then the storefront shows honest empty states.
5. Visual QA at small screen sizes remains outstanding (§5).

---

## 9. Re-running the audit

```bash
cd C:/xampp/htdocs/madam-cakes
"C:/xampp/php/php.exe" -l index.php          # lint any file
"C:/xampp/php/php.exe" tools/e2e_test.php    # 114 end-to-end checks
```

The test creates its own administrator and customer, exercises the workflows above and
removes every record it created, leaving the database as it found it.
