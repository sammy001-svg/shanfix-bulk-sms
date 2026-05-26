-- Phase 12: Add columns that were in active use but missing from schema.sql
-- Safe to run on an existing database — ADD COLUMN IF NOT EXISTS is a no-op
-- when the column already exists (requires MySQL 8.0+; on 5.7 check manually).

-- contacts.metadata
-- Used by sms.php for CSV/XLSX personalization placeholders ({name}, {balance}, etc.)
ALTER TABLE `contacts`
  ADD COLUMN IF NOT EXISTS `metadata` JSON DEFAULT NULL
    COMMENT 'Extra CSV columns as JSON for personalisation'
    AFTER `email`;

-- campaigns.file_path + campaigns.processed_rows
-- file_path: absolute server path to uploaded CSV or XLSX file
-- processed_rows: progress cursor used alongside sent_count/failed_count
ALTER TABLE `campaigns`
  ADD COLUMN IF NOT EXISTS `file_path`      VARCHAR(600) DEFAULT NULL
    COMMENT 'Server path to uploaded CSV/XLSX for queue processing'
    AFTER `recipients`,
  ADD COLUMN IF NOT EXISTS `processed_rows` INT UNSIGNED NOT NULL DEFAULT 0
    COMMENT 'Rows already processed — used for progress tracking'
    AFTER `total_count`;

-- messages.failed_reason
-- Stores the gateway error or validation failure so the Failed drawer and
-- reports can show a human-readable reason per message.
ALTER TABLE `messages`
  ADD COLUMN IF NOT EXISTS `failed_reason` VARCHAR(500) DEFAULT NULL
    COMMENT 'Gateway error or validation failure description'
    AFTER `sent_at`;
