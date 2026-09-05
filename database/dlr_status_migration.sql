-- ============================================================
-- Migration: capture the granular carrier DLR status per message
--
-- messages.status is a 5-value ENUM, which cannot express the carrier-level
-- delivery states the Onfon portal reports on (DelivredToTerminal, Submitted,
-- AbsentSubscriber, DeliveryImpossible, DELIVRD, REJECTD, Sendername
-- blacklisted, ...). This column stores whatever raw status string the DLR
-- webhook receives so the Delivery Reports page can pivot on it.
--
--   mysql -u <user> -p <database> < database/dlr_status_migration.sql
--
-- Existing rows keep dlr_status = NULL; the report derives a status for those
-- from messages.status so historical data is not blank.
-- ============================================================

ALTER TABLE `messages`
  ADD COLUMN IF NOT EXISTS `dlr_status` VARCHAR(60) DEFAULT NULL
    COMMENT 'Raw carrier delivery status from the DLR webhook (DELIVRD, AbsentSubscriber, ...)'
    AFTER `failed_reason`;

-- Drives the per-day / per-status pivot in the Delivery Reports page.
ALTER TABLE `messages`
  ADD INDEX `idx_user_created_dlr` (`user_id`, `created_at`, `dlr_status`);

SELECT COUNT(*) AS messages_awaiting_dlr
FROM `messages`
WHERE `dlr_status` IS NULL;
