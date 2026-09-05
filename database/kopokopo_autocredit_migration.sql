-- ============================================================
-- Migration: reliable automatic crediting for Kopo Kopo payments
--
--   mysql -u <user> -p <database> < database/kopokopo_autocredit_migration.sql
--
-- Adds the Kopo Kopo API Key setting (used to verify webhook signatures) and
-- a place to store the payment resource URL returned by the STK push, so a
-- payment can be confirmed by asking Kopo Kopo directly instead of relying
-- solely on the webhook arriving.
-- Safe to re-run.
-- ============================================================

-- 1. Settings: the API Key from the Kopo Kopo dashboard.
INSERT IGNORE INTO `system_settings` (`key`, `value`) VALUES
  ('kk_api_key', '');

-- 2. Where the STK push resource lives, so we can poll it for the outcome.
ALTER TABLE `purchases`
  ADD COLUMN IF NOT EXISTS `gateway_ref` VARCHAR(255) DEFAULT NULL
    COMMENT 'Kopo Kopo payment resource URL returned by the STK push'
    AFTER `transaction_ref`;

ALTER TABLE `ussd_transactions`
  ADD COLUMN IF NOT EXISTS `gateway_ref` VARCHAR(255) DEFAULT NULL
    COMMENT 'Kopo Kopo payment resource URL returned by the STK push'
    AFTER `reference`;

-- 3. The reconciliation sweep looks up pending rows by status + age.
ALTER TABLE `purchases`
  ADD KEY IF NOT EXISTS `idx_status_created` (`status`, `created_at`);

ALTER TABLE `ussd_transactions`
  ADD KEY IF NOT EXISTS `idx_status_created` (`status`, `created_at`);
