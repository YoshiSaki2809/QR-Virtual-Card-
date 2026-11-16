-- =============================================================================
--  vCard Studio — MySQL Schema
--  Import this file into phpMyAdmin to set up the database from scratch.
--
--  Steps:
--    1. Open phpMyAdmin → create a new database called: vcard_app
--    2. Select that database → click "Import" tab
--    3. Choose this file → click "Go"
--    4. Done — all tables are ready, no dummy data included.
--
--  After importing, update config.php:
--    DB_HOST  → your MySQL host (usually 'localhost')
--    DB_NAME  → vcard_app
--    DB_USER  → your MySQL username
--    DB_PASS  → your MySQL password
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `vcard_app`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `vcard_app`;

-- -----------------------------------------------------------------------------
--  Table: settings
--  Stores global config values (admin PIN, admin email, etc.)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `key`   VARCHAR(100) NOT NULL,
  `value` TEXT         NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default admin credentials (PIN: 1234 — change this after first login)
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
  ('admin_pin',   '1234'),
  ('admin_email', 'admin@yourcompany.com');

-- -----------------------------------------------------------------------------
--  Table: cards
--  One row per staff digital vCard
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cards` (
  `id`               INT          NOT NULL AUTO_INCREMENT,
  `slug`             VARCHAR(100) NOT NULL,
  `first_name`       VARCHAR(100) NOT NULL,
  `last_name`        VARCHAR(100) NOT NULL DEFAULT '',
  `company`          VARCHAR(150) DEFAULT NULL,
  `job_title`        VARCHAR(150) DEFAULT NULL,
  `dob`              VARCHAR(20)  DEFAULT NULL,
  `phone_type`       VARCHAR(30)  DEFAULT 'Mobile',
  `phone`            VARCHAR(50)  DEFAULT NULL,
  `email`            VARCHAR(150) DEFAULT NULL,
  `website`          VARCHAR(255) DEFAULT NULL,
  `address`          VARCHAR(255) DEFAULT NULL,
  `city`             VARCHAR(100) DEFAULT NULL,
  `state`            VARCHAR(100) DEFAULT NULL,
  `postal_code`      VARCHAR(20)  DEFAULT NULL,
  `country`          VARCHAR(100) DEFAULT NULL,
  `photo_url`        TEXT         DEFAULT NULL,
  `theme_color`      VARCHAR(20)  NOT NULL DEFAULT '#2563eb',
  `secondary_color`  VARCHAR(20)  NOT NULL DEFAULT '#1d4ed8',
  `qr_color`         VARCHAR(20)  NOT NULL DEFAULT '#000000',
  `qr_pattern`       VARCHAR(30)  NOT NULL DEFAULT 'square',
  `qr_corner_style`  VARCHAR(30)  NOT NULL DEFAULT 'extra-rounded',
  `edit_pin`         VARCHAR(20)  NOT NULL DEFAULT '1234',
  `is_dynamic`       TINYINT(1)   NOT NULL DEFAULT 1,
  `otp_code`         VARCHAR(10)  DEFAULT NULL,
  `otp_expires_at`   DATETIME     DEFAULT NULL,
  `edit_token`       VARCHAR(255) DEFAULT NULL,
  `scans`            INT          NOT NULL DEFAULT 0,
  `last_scanned_at`  DATETIME     DEFAULT NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
--  Table: scan_logs
--  One row per QR scan event (for analytics)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `scan_logs` (
  `id`          INT          NOT NULL AUTO_INCREMENT,
  `card_id`     INT          NOT NULL,
  `scanned_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address`  VARCHAR(45)  DEFAULT NULL,
  `user_agent`  TEXT         DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_card_id` (`card_id`),
  CONSTRAINT `fk_scan_card`
    FOREIGN KEY (`card_id`) REFERENCES `cards` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
--  Table: pin_resets
--  Temporary tokens for admin PIN reset via email
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pin_resets` (
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `token`      VARCHAR(255) NOT NULL,
  `email`      VARCHAR(150) NOT NULL,
  `expires_at` DATETIME     NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
