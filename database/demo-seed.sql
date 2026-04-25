-- ============================================================
-- Demo Users Seed
-- Run AFTER schema.sql in phpMyAdmin to add demo reseller + client
-- ============================================================

-- Demo Reseller (password: Reseller@1234)
INSERT INTO `users` (`name`,`email`,`phone`,`password_hash`,`role`,`parent_id`,`company`,`sms_units`,`status`)
VALUES (
  'Shanfix Technology',
  'reseller@bulksms.com',
  '+254711222333',
  '$2y$12$DuURRTChFfNegvIJarK0MeY6DUthCGG5r7WBCZHFGvYjb4B.ZV6ne',
  'reseller', 1, 'Shanfix Technology Ltd', 8.01, 'active'
);

SET @reseller_id = LAST_INSERT_ID();

-- Demo Client (password: Client@1234)
INSERT INTO `users` (`name`,`email`,`phone`,`password_hash`,`role`,`parent_id`,`company`,`sms_units`,`status`)
VALUES (
  'Demo Client',
  'client@bulksms.com',
  '+254722333444',
  '$2y$12$qOETROqsaeMqWsW5BHzjuejpeDETEqFiWMLXWK.e0bBwGQ7/Xk.by',
  'client', @reseller_id, 'Demo Company', 50.00, 'active'
);

SET @client_id = LAST_INSERT_ID();

-- Approved Sender ID for reseller
INSERT INTO `sender_ids` (`user_id`,`sender_id`,`purpose`,`status`,`approved_by`,`approved_at`)
VALUES (@reseller_id, 'SHANFIX', 'Marketing campaigns for retail clients', 'approved', 1, NOW());

-- Demo contact group
INSERT INTO `contact_groups` (`user_id`,`name`,`description`)
VALUES (@reseller_id, 'VIP Customers', 'Top 100 loyal customers'), (@reseller_id, 'Newsletter Subscribers', 'Email and SMS newsletter list');

-- Demo pricing purchases
INSERT INTO `purchases` (`user_id`,`plan_id`,`units`,`amount`,`currency`,`payment_method`,`transaction_ref`,`status`)
VALUES
  (@reseller_id, 1, 100, 50.00, 'KES', 'mpesa', 'QHX7A3BS12', 'completed'),
  (@reseller_id, 2, 500, 200.00, 'KES', 'mpesa', 'QRT9B5CV34', 'completed');

-- Demo campaign
INSERT INTO `campaigns` (`user_id`,`name`,`sender_id`,`message`,`total_count`,`sent_count`,`failed_count`,`units_used`,`status`,`sent_at`)
VALUES
  (@reseller_id, 'Welcome Promo April', 'SHANFIX', 'Dear customer, enjoy 20% off all products this April! Use code APRIL20. Valid till 30th April.', 500, 498, 2, 500, 'completed', NOW()),
  (@reseller_id, 'Flash Sale May', 'SHANFIX', 'FLASH SALE! 50% off selected items today only. Shop now at shanfix.ke', 0, 0, 0, 0, 'draft', NULL);
