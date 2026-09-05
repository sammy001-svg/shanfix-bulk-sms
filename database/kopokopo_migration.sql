-- ============================================================
-- Migration: replace the Payhero payment gateway with Kopo Kopo
--
-- Run once on an existing installation. Safe to re-run: the INSERT
-- is IGNORE-guarded and the DELETE is idempotent.
--
--   mysql -u <user> -p <database> < database/kopokopo_migration.sql
--
-- After running, set the credentials in Admin -> Settings -> Billing.
-- ============================================================

-- 1. Add the Kopo Kopo settings keys (existing values are left untouched).
INSERT IGNORE INTO `system_settings` (`key`, `value`) VALUES
  ('kk_client_id',      ''),
  ('kk_client_secret',  ''),
  ('kk_till_number',    ''),
  ('kk_base_url',       'https://api.kopokopo.com'),
  ('kk_webhook_secret', ''),
  ('kk_webhook_token',  '');

-- 2. Drop the retired Payhero credentials. Nothing reads these any more, and
--    leaving live API secrets in the settings table is an unnecessary risk.
DELETE FROM `system_settings`
WHERE `key` IN (
  'payhero_api_username',
  'payhero_api_password',
  'payhero_channel_id',
  'payhero_api_channel_id',
  'payhero_webhook_token'
);

-- 3. Verify.
SELECT `key`, IF(`value` = '', '(empty - needs configuring)', 'set') AS state
FROM `system_settings`
WHERE `key` LIKE 'kk\_%'
ORDER BY `key`;
