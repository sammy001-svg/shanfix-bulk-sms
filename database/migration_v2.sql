-- ============================================================
-- MIGRATION V2: Reseller Pricing & Tiered Pricing Support
-- ============================================================
-- Run this script in phpMyAdmin if you already have the 
-- system installed and are getting 500 errors on pricing.
-- ============================================================

-- 1. Add custom_unit_price to users table (for tiered pricing)
ALTER TABLE `users` 
ADD COLUMN `custom_unit_price` DECIMAL(10,2) DEFAULT NULL 
AFTER `sms_units`;

-- 2. Add owner_id to pricing_plans table (for reseller-specific plans)
ALTER TABLE `pricing_plans` 
ADD COLUMN `owner_id` INT UNSIGNED DEFAULT NULL 
AFTER `id`;

-- 3. Add Foreign Key for owner_id
ALTER TABLE `pricing_plans` 
ADD CONSTRAINT `fk_plan_owner` 
FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) 
ON DELETE CASCADE;

-- 4. Clean up: Ensure all existing plans are marked as 'Global' (owner_id NULL)
UPDATE `pricing_plans` SET `owner_id` = NULL WHERE `owner_id` IS NULL;
