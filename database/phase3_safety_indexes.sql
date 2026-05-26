-- ============================================================
-- Phase 3: Safety & Index Migration
-- Run once; safe to re-run if the index already exists.
-- ============================================================

-- 1. Composite index for contact deduplication queries:
--    SELECT id FROM contacts WHERE user_id = ? AND phone = ?
--    Without this, every import dedup check is a full table scan.
ALTER TABLE `contacts`
  ADD INDEX `idx_user_phone` (`user_id`, `phone`);
