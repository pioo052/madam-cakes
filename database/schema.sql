-- ============================================================================
--  MADAM CAKES  —  Database schema
--  "Every Cake Tells a Sweet Story."
-- ----------------------------------------------------------------------------
--  Engine : InnoDB (MariaDB 10.11 / MySQL 8.x)
--  Charset: utf8mb4 / utf8mb4_unicode_ci
--  Money  : DECIMAL(12,2) everywhere. Never FLOAT.
--  Usage  : import into an EMPTY database named `madam_cakes_db`.
--           phpMyAdmin -> Import, or:
--           mysql -u root madam_cakes_db < database/schema.sql
--  NOTE   : this script drops existing MADAM CAKES tables first.
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+03:00';          -- Africa/Dar_es_Salaam
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS admin_activity_logs;
DROP TABLE IF EXISTS contact_messages;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS newsletter_subscribers;
DROP TABLE IF EXISTS site_content;
DROP TABLE IF EXISTS business_settings;
DROP TABLE IF EXISTS favourites;
DROP TABLE IF EXISTS coupon_usage;
DROP TABLE IF EXISTS coupons;
DROP TABLE IF EXISTS gallery;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS delivery_zones;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS order_status_history;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS custom_orders;
DROP TABLE IF EXISTS cake_layer_prices;
DROP TABLE IF EXISTS cake_options;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS carts;
DROP TABLE IF EXISTS product_addons;
DROP TABLE IF EXISTS product_flavours;
DROP TABLE IF EXISTS product_sizes;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS user_addresses;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS app_meta;

-- ============================================================================
-- 1. SYSTEM
-- ============================================================================

CREATE TABLE app_meta (
  meta_key    VARCHAR(64)  NOT NULL,
  meta_value  VARCHAR(255) NOT NULL DEFAULT '',
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (meta_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. ACCOUNTS
-- ============================================================================

CREATE TABLE users (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role           ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  full_name      VARCHAR(120) NOT NULL,
  email          VARCHAR(190) NOT NULL,
  phone          VARCHAR(30)  NULL,
  avatar         VARCHAR(255) NULL,
  password_hash  VARCHAR(255) NOT NULL,
  status         ENUM('active','blocked') NOT NULL DEFAULT 'active',
  last_login_at  DATETIME     NULL,
  closed_at      DATETIME     NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role_status (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_addresses (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  label       VARCHAR(60)  NOT NULL DEFAULT 'Home',
  region      VARCHAR(80)  NOT NULL,
  city        VARCHAR(80)  NOT NULL,
  area        VARCHAR(120) NOT NULL DEFAULT '',
  street      VARCHAR(255) NOT NULL DEFAULT '',
  is_default  TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_addresses_user (user_id),
  CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. CATALOGUE
-- ============================================================================

CREATE TABLE categories (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(120) NOT NULL,
  slug         VARCHAR(140) NOT NULL,
  description  TEXT         NULL,
  image        VARCHAR(255) NULL,
  sort_order   INT          NOT NULL DEFAULT 0,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug),
  KEY idx_categories_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id      INT UNSIGNED NULL,
  name             VARCHAR(160)  NOT NULL,
  slug             VARCHAR(180)  NOT NULL,
  short_description VARCHAR(300) NULL,
  description      LONGTEXT      NULL,
  base_price       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  preparation_days SMALLINT UNSIGNED NOT NULL DEFAULT 2,
  is_active        TINYINT(1)    NOT NULL DEFAULT 1,
  is_featured      TINYINT(1)    NOT NULL DEFAULT 0,
  is_bestseller    TINYINT(1)    NOT NULL DEFAULT 0,
  created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_slug (slug),
  KEY idx_products_category (category_id, is_active),
  KEY idx_products_flags (is_active, is_featured, is_bestseller),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_images (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id  INT UNSIGNED NOT NULL,
  image_path  VARCHAR(255) NOT NULL,
  alt_text    VARCHAR(190) NULL,
  sort_order  INT          NOT NULL DEFAULT 0,
  is_primary  TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_images_product (product_id, sort_order),
  CONSTRAINT fk_images_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Size price is the FULL owner-defined price for that size (0.00 = not set yet).
CREATE TABLE product_sizes (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id  INT UNSIGNED NOT NULL,
  name        VARCHAR(80)  NOT NULL,
  price       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  sort_order  INT          NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_sizes_product (product_id, sort_order),
  CONSTRAINT fk_sizes_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_flavours (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id  INT UNSIGNED NOT NULL,
  name        VARCHAR(80)  NOT NULL,
  surcharge   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_flavours_product (product_id),
  CONSTRAINT fk_flavours_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_addons (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id  INT UNSIGNED NOT NULL,
  name        VARCHAR(120) NOT NULL,
  price       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_addons_product (product_id),
  CONSTRAINT fk_addons_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. CART
-- ============================================================================

CREATE TABLE carts (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NULL,
  session_key  VARCHAR(64)  NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_carts_session (session_key),
  KEY idx_carts_user (user_id),
  CONSTRAINT fk_carts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cart_items (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cart_id     INT UNSIGNED NOT NULL,
  product_id  INT UNSIGNED NOT NULL,
  size_id     INT UNSIGNED NULL,
  flavour_id  INT UNSIGNED NULL,
  quantity    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  unit_price  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  addons_json TEXT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cart_items_cart (cart_id),
  CONSTRAINT fk_cart_items_cart    FOREIGN KEY (cart_id)    REFERENCES carts (id)    ON DELETE CASCADE,
  CONSTRAINT fk_cart_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_cart_items_size    FOREIGN KEY (size_id)    REFERENCES product_sizes (id)   ON DELETE SET NULL,
  CONSTRAINT fk_cart_items_flavour FOREIGN KEY (flavour_id) REFERENCES product_flavours (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. CUSTOM CAKE DESIGNER (owner-managed options + manual quotations)
-- ============================================================================

CREATE TABLE cake_options (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_key   ENUM('occasion','shape','size','flavour','decoration','cream_colour') NOT NULL,
  name        VARCHAR(90)  NOT NULL,
  price       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  image       VARCHAR(255) NULL,
  sort_order  INT          NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_cake_options_group (group_key, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cake_layer_prices (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  layers      TINYINT UNSIGNED NOT NULL,
  price       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_layer_count (layers)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE custom_orders (
  id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_reference         VARCHAR(24)  NOT NULL,
  user_id                 INT UNSIGNED NULL,
  customer_name           VARCHAR(120) NOT NULL,
  customer_phone          VARCHAR(30)  NOT NULL,
  customer_email          VARCHAR(190) NULL,
  occasion_option_id      INT UNSIGNED NULL,
  shape_option_id         INT UNSIGNED NULL,
  size_option_id          INT UNSIGNED NULL,
  flavour_option_id       INT UNSIGNED NULL,
  decoration_option_id    INT UNSIGNED NULL,
  cream_colour            VARCHAR(40)  NULL,
  layers                  TINYINT UNSIGNED NOT NULL DEFAULT 1,
  cake_message            VARCHAR(200) NULL,
  additional_instructions TEXT         NULL,
  required_date           DATE         NULL,
  reference_image         VARCHAR(255) NULL,
  estimated_price         DECIMAL(12,2) NULL,
  estimate_breakdown      TEXT         NULL,
  quoted_price            DECIMAL(12,2) NULL,
  quotation_note          TEXT         NULL,
  quoted_at               DATETIME     NULL,
  status                  ENUM('pending','awaiting_quotation','quoted','accepted','rejected','converted','cancelled')
                          NOT NULL DEFAULT 'pending',
  admin_notes             TEXT         NULL,
  converted_order_id      INT UNSIGNED NULL,
  created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_custom_reference (order_reference),
  KEY idx_custom_status (status, created_at),
  KEY idx_custom_user (user_id),
  CONSTRAINT fk_custom_user       FOREIGN KEY (user_id)              REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_custom_occasion   FOREIGN KEY (occasion_option_id)   REFERENCES cake_options (id) ON DELETE SET NULL,
  CONSTRAINT fk_custom_shape      FOREIGN KEY (shape_option_id)      REFERENCES cake_options (id) ON DELETE SET NULL,
  CONSTRAINT fk_custom_size       FOREIGN KEY (size_option_id)       REFERENCES cake_options (id) ON DELETE SET NULL,
  CONSTRAINT fk_custom_flavour    FOREIGN KEY (flavour_option_id)    REFERENCES cake_options (id) ON DELETE SET NULL,
  CONSTRAINT fk_custom_decoration FOREIGN KEY (decoration_option_id) REFERENCES cake_options (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. DELIVERY
-- ============================================================================

CREATE TABLE delivery_zones (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(120) NOT NULL,
  region      VARCHAR(80)  NOT NULL,
  city        VARCHAR(80)  NOT NULL DEFAULT '',
  fee         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  free_over   DECIMAL(12,2) NULL,
  notes       VARCHAR(255) NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_zones_active (is_active, region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. ORDERS
-- ============================================================================

CREATE TABLE orders (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_reference      VARCHAR(24)  NOT NULL,
  user_id              INT UNSIGNED NULL,
  customer_name        VARCHAR(120) NOT NULL,
  customer_phone       VARCHAR(30)  NOT NULL,
  customer_email       VARCHAR(190) NULL,
  fulfilment           ENUM('delivery','pickup') NOT NULL DEFAULT 'delivery',
  delivery_zone_id     INT UNSIGNED NULL,
  region               VARCHAR(80)  NULL,
  city                 VARCHAR(80)  NULL,
  area                 VARCHAR(120) NULL,
  street_address       VARCHAR(255) NULL,
  delivery_date        DATE         NULL,
  delivery_time        VARCHAR(40)  NULL,
  delivery_instructions TEXT        NULL,
  is_surprise          TINYINT(1)   NOT NULL DEFAULT 0,
  recipient_name       VARCHAR(120) NULL,
  recipient_phone      VARCHAR(30)  NULL,
  recipient_location   VARCHAR(255) NULL,
  gift_message         TEXT         NULL,
  gift_wrapping        TINYINT(1)   NOT NULL DEFAULT 0,
  anonymous_sender     TINYINT(1)   NOT NULL DEFAULT 0,
  subtotal             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount_total       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  delivery_fee         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total                DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  coupon_id            INT UNSIGNED NULL,
  coupon_code          VARCHAR(40)  NULL,
  status               ENUM('pending','awaiting_quotation','awaiting_payment','confirmed','preparing','ready','out_for_delivery','delivered','cancelled','rejected')
                       NOT NULL DEFAULT 'pending',
  payment_status       ENUM('unpaid','pending','paid','refunded') NOT NULL DEFAULT 'unpaid',
  payment_method       VARCHAR(40)  NULL,
  customer_notes       TEXT         NULL,
  admin_notes          TEXT         NULL,
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_reference (order_reference),
  KEY idx_orders_status (status, created_at),
  KEY idx_orders_user (user_id),
  KEY idx_orders_payment (payment_status),
  KEY idx_orders_created (created_at),
  KEY idx_orders_phone (customer_phone),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_orders_zone FOREIGN KEY (delivery_zone_id) REFERENCES delivery_zones (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id        INT UNSIGNED NOT NULL,
  item_type       ENUM('product','custom') NOT NULL DEFAULT 'product',
  product_id      INT UNSIGNED NULL,
  custom_order_id INT UNSIGNED NULL,
  product_name    VARCHAR(190) NOT NULL,
  size_name       VARCHAR(80)  NULL,
  flavour_name    VARCHAR(80)  NULL,
  addons_json     TEXT         NULL,
  unit_price      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  quantity        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  line_total      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (id),
  KEY idx_order_items_order (order_id),
  CONSTRAINT fk_order_items_order  FOREIGN KEY (order_id)        REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_order_items_product FOREIGN KEY (product_id)     REFERENCES products (id) ON DELETE SET NULL,
  CONSTRAINT fk_order_items_custom  FOREIGN KEY (custom_order_id) REFERENCES custom_orders (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_status_history (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id      INT UNSIGNED NOT NULL,
  status        VARCHAR(40)  NOT NULL,
  note          VARCHAR(255) NULL,
  changed_by    INT UNSIGNED NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status_history_order (order_id, created_at),
  CONSTRAINT fk_status_history_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_status_history_user  FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id           INT UNSIGNED NOT NULL,
  method             ENUM('cash','mpesa','airtel_money','mixx_by_yas','bank_transfer','other') NOT NULL DEFAULT 'cash',
  amount             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  provider_reference VARCHAR(120) NULL,
  payer_phone        VARCHAR(30)  NULL,
  status             ENUM('pending','verified','rejected','refunded') NOT NULL DEFAULT 'pending',
  paid_at            DATETIME     NULL,
  verified_by        INT UNSIGNED NULL,
  verified_at        DATETIME     NULL,
  notes              VARCHAR(255) NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_payments_order (order_id, status),
  CONSTRAINT fk_payments_order    FOREIGN KEY (order_id)    REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_payments_verifier FOREIGN KEY (verified_by) REFERENCES users (id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. REVIEWS / GALLERY / FAVOURITES
-- ============================================================================

CREATE TABLE reviews (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id    INT UNSIGNED NULL,
  order_id      INT UNSIGNED NULL,
  user_id       INT UNSIGNED NULL,
  customer_name VARCHAR(120) NOT NULL,
  rating        TINYINT UNSIGNED NOT NULL DEFAULT 5,
  title         VARCHAR(160) NULL,
  body          TEXT         NOT NULL,
  photo         VARCHAR(255) NULL,
  is_verified   TINYINT(1)   NOT NULL DEFAULT 0,
  is_approved   TINYINT(1)   NOT NULL DEFAULT 0,
  is_featured   TINYINT(1)   NOT NULL DEFAULT 0,
  admin_reply   TEXT         NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reviews_product (product_id, is_approved),
  KEY idx_reviews_moderation (is_approved, created_at),
  CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_order   FOREIGN KEY (order_id)   REFERENCES orders (id)   ON DELETE SET NULL,
  CONSTRAINT fk_reviews_user    FOREIGN KEY (user_id)    REFERENCES users (id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gallery (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title       VARCHAR(160) NOT NULL,
  caption     VARCHAR(300) NULL,
  image       VARCHAR(255) NOT NULL,
  category    ENUM('birthday','wedding','graduation','anniversary','baby_shower','custom','other')
              NOT NULL DEFAULT 'custom',
  sort_order  INT          NOT NULL DEFAULT 0,
  is_featured TINYINT(1)   NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_gallery_category (category, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE favourites (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  product_id  INT UNSIGNED NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_favourite (user_id, product_id),
  CONSTRAINT fk_fav_user    FOREIGN KEY (user_id)    REFERENCES users (id)    ON DELETE CASCADE,
  CONSTRAINT fk_fav_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9. PROMOTIONS
-- ============================================================================

CREATE TABLE coupons (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code             VARCHAR(40)  NOT NULL,
  description      VARCHAR(190) NULL,
  discount_type    ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  discount_value   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  min_order_value  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  starts_at        DATETIME     NULL,
  expires_at       DATETIME     NULL,
  usage_limit      INT UNSIGNED NULL,
  per_user_limit   INT UNSIGNED NULL DEFAULT 1,
  used_count       INT UNSIGNED NOT NULL DEFAULT 0,
  is_active        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_coupons_code (code),
  KEY idx_coupons_active (is_active, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE coupon_usage (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  coupon_id       INT UNSIGNED NOT NULL,
  order_id        INT UNSIGNED NULL,
  user_id         INT UNSIGNED NULL,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_coupon_usage_coupon (coupon_id),
  CONSTRAINT fk_coupon_usage_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE CASCADE,
  CONSTRAINT fk_coupon_usage_order  FOREIGN KEY (order_id)  REFERENCES orders (id)  ON DELETE SET NULL,
  CONSTRAINT fk_coupon_usage_user   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 10. CONTENT, SETTINGS, NOTIFICATIONS, AUDIT
-- ============================================================================

CREATE TABLE business_settings (
  setting_key   VARCHAR(64) NOT NULL,
  setting_value LONGTEXT    NULL,
  group_name    VARCHAR(40) NOT NULL DEFAULT 'general',
  updated_at    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key),
  KEY idx_settings_group (group_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_content (
  content_key VARCHAR(64) NOT NULL,
  title       VARCHAR(190) NULL,
  body        LONGTEXT     NULL,
  image       VARCHAR(255) NULL,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (content_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE newsletter_subscribers (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email       VARCHAR(190) NOT NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_newsletter_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_messages (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NULL,
  name        VARCHAR(120) NOT NULL,
  email       VARCHAR(190) NOT NULL,
  phone       VARCHAR(30)  NULL,
  subject     VARCHAR(160) NULL,
  message     TEXT         NOT NULL,
  is_read     TINYINT(1)   NOT NULL DEFAULT 0,
  replied_at  DATETIME     NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_contact_read (is_read, created_at),
  CONSTRAINT fk_contact_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NULL,
  audience    ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  type        VARCHAR(40)  NOT NULL DEFAULT 'info',
  title       VARCHAR(160) NOT NULL,
  message     TEXT         NULL,
  link        VARCHAR(255) NULL,
  is_read     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_user (user_id, is_read),
  KEY idx_notifications_admin (audience, is_read, created_at),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_activity_logs (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id    INT UNSIGNED NULL,
  action      VARCHAR(80)  NOT NULL,
  entity_type VARCHAR(40)  NULL,
  entity_id   INT UNSIGNED NULL,
  details     TEXT         NULL,
  ip_address  VARCHAR(45)  NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_admin (admin_id, created_at),
  KEY idx_audit_entity (entity_type, entity_id),
  CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 11. Deferred foreign key (circular reference resolved after both tables exist)
-- ============================================================================

ALTER TABLE custom_orders
  ADD CONSTRAINT fk_custom_converted_order
  FOREIGN KEY (converted_order_id) REFERENCES orders (id) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;
