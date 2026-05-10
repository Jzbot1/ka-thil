-- ============================================================
-- SMTP Migration: Add email notification columns to fav_setting
-- Run this once in phpMyAdmin or MySQL CLI
-- ============================================================

ALTER TABLE `fav_setting`
    ADD COLUMN IF NOT EXISTS `smtp_enabled`    TINYINT(1)   NOT NULL DEFAULT 0   AFTER `whatsapp_group`,
    ADD COLUMN IF NOT EXISTS `smtp_from_email` VARCHAR(255) NOT NULL DEFAULT ''  AFTER `smtp_enabled`,
    ADD COLUMN IF NOT EXISTS `smtp_from_name`  VARCHAR(255) NOT NULL DEFAULT ''  AFTER `smtp_from_email`,
    ADD COLUMN IF NOT EXISTS `smtp_host`       VARCHAR(255) NOT NULL DEFAULT ''  AFTER `smtp_from_name`,
    ADD COLUMN IF NOT EXISTS `smtp_port`       INT          NOT NULL DEFAULT 587 AFTER `smtp_host`,
    ADD COLUMN IF NOT EXISTS `smtp_username`   VARCHAR(255) NOT NULL DEFAULT ''  AFTER `smtp_port`,
    ADD COLUMN IF NOT EXISTS `smtp_password`   VARCHAR(255) NOT NULL DEFAULT ''  AFTER `smtp_username`;
