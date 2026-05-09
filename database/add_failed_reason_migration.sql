-- Migration: add failed_reason to messages table
-- Run once on production DB before deploying the matching code changes.

ALTER TABLE messages
    ADD COLUMN failed_reason VARCHAR(500) NULL DEFAULT NULL AFTER sent_at;
