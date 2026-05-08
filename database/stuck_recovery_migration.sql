-- Migration: Add locked_at for stuck-campaign detection and recovery.
-- Run once after bulk_queue_migration.sql has been applied.

ALTER TABLE `campaigns`
  ADD COLUMN `locked_at` DATETIME NULL DEFAULT NULL
    COMMENT 'Timestamp when cron locked this campaign for processing; NULL when idle'
    AFTER `sent_at`;
