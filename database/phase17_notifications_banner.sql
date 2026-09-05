-- Phase 17: Add missing is_banner column to notifications table
-- The notify() function and create-notification.php both reference this column.
-- Without it every call to notify() with isBanner=true crashes with an SQL error.

ALTER TABLE notifications
    ADD COLUMN IF NOT EXISTS is_banner TINYINT(1) NOT NULL DEFAULT 0
    AFTER is_popup;
