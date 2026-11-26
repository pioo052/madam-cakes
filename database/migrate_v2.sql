-- ============================================================================
--  MADAM CAKES — migration 2: customer accounts, messages
-- ----------------------------------------------------------------------------
--  Safe to run more than once. Adds columns and tables only; never drops.
--  Import: mysql -u root madam_cakes_db < database/migrate_v2.sql
-- ============================================================================

SET NAMES utf8mb4;

-- Profile photo and privacy-safe account closure (records are retained).
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) NULL AFTER phone,
  ADD COLUMN IF NOT EXISTS closed_at DATETIME NULL AFTER last_login_at;

-- Contact form submissions. Handled by an administrator, not by email.
CREATE TABLE IF NOT EXISTS contact_messages (
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

INSERT INTO app_meta (meta_key, meta_value) VALUES ('schema_version', '2.0.0')
ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value);
