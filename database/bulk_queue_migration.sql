-- Migration: Queue-based bulk SMS processing
-- Run once on your database to enable fast file-based campaign sending.

ALTER TABLE `campaigns`
  ADD COLUMN `file_path` VARCHAR(600) DEFAULT NULL
    COMMENT 'Absolute server path to the uploaded CSV file for queue processing'
    AFTER `recipients`,
  ADD COLUMN `processed_rows` INT UNSIGNED NOT NULL DEFAULT 0
    COMMENT 'Number of rows already processed (for progress tracking)'
    AFTER `total_count`;
