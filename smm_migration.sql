-- ============================================================
-- SMM Panel Integration - Full DB Migration v2
-- Run once in phpMyAdmin → SQL tab
-- ============================================================

-- 1. Add SMM columns to fav_setting
ALTER TABLE `fav_setting`
  ADD COLUMN IF NOT EXISTS `smm_api_url`    VARCHAR(255) DEFAULT 'https://cheapestsmmpanels.com/api/v2',
  ADD COLUMN IF NOT EXISTS `smm_api_key`    VARCHAR(255) DEFAULT '',
  ADD COLUMN IF NOT EXISTS `smm_cron_token` VARCHAR(64)  DEFAULT '';

-- 2. SMM Services Cache (fetched from provider, admin-customizable)
CREATE TABLE IF NOT EXISTS `smm_services` (
  `id`              INT(11)       NOT NULL AUTO_INCREMENT,
  `provider_id`     INT(11)       NOT NULL COMMENT 'Service ID from SMM panel',
  `category`        VARCHAR(128)  NOT NULL DEFAULT 'General',
  `original_name`   VARCHAR(255)  NOT NULL,
  `custom_name`     VARCHAR(255)  DEFAULT NULL COMMENT 'Admin override for display name',
  `original_rate`   DECIMAL(10,6) NOT NULL DEFAULT 0 COMMENT 'Provider rate per 1000',
  `custom_price`    DECIMAL(10,2) DEFAULT NULL COMMENT 'Admin selling price per 1000 (INR)',
  `min_order`       INT(11)       NOT NULL DEFAULT 10,
  `max_order`       INT(11)       NOT NULL DEFAULT 10000,
  `is_active`       TINYINT(1)    NOT NULL DEFAULT 1,
  `type`            VARCHAR(64)   DEFAULT 'Default',
  `synced_at`       DATETIME      DEFAULT NULL,
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_provider_id` (`provider_id`),
  KEY `idx_category` (`category`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. SMM Orders (placed by users)
CREATE TABLE IF NOT EXISTS `smm_orders` (
  `id`              INT(11)        NOT NULL AUTO_INCREMENT,
  `order_ref`       VARCHAR(64)    NOT NULL COMMENT 'Internal order reference',
  `user_id`         INT(11)        DEFAULT NULL,
  `service_id`      INT(11)        NOT NULL COMMENT 'smm_services.id',
  `provider_id`     INT(11)        NOT NULL COMMENT 'SMM panel service ID',
  `smm_order_id`    INT(11)        DEFAULT NULL COMMENT 'Order ID from SMM panel',
  `target_link`     VARCHAR(512)   NOT NULL,
  `quantity`        INT(11)        NOT NULL DEFAULT 0,
  `runs`            INT(11)        DEFAULT 0,
  `interval`        INT(11)        DEFAULT 0,
  `price_paid`      DECIMAL(10,2)  NOT NULL DEFAULT 0 COMMENT 'Amount charged to user (INR)',
  `payment_method`  VARCHAR(64)    DEFAULT 'wallet',
  `status`          VARCHAR(32)    NOT NULL DEFAULT 'pending',
  `remains`         INT(11)        DEFAULT NULL,
  `start_count`     INT(11)        DEFAULT NULL,
  `charge`          DECIMAL(10,4)  DEFAULT NULL COMMENT 'Actual cost from provider',
  `notes`           TEXT           DEFAULT NULL,
  `sent_at`         DATETIME       DEFAULT NULL,
  `last_checked`    DATETIME       DEFAULT NULL,
  `created_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_order_ref` (`order_ref`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_smm_order_id` (`smm_order_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
