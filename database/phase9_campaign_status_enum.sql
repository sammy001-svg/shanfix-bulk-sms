-- Phase 9: Expand campaigns.status ENUM to include 'scheduled' and 'running'
-- These statuses are used by the application code but were missing from the schema,
-- causing silent data corruption on strict MySQL.
ALTER TABLE `campaigns`
  MODIFY `status` ENUM('draft','scheduled','queued','running','sending','completed','failed')
    NOT NULL DEFAULT 'draft';
