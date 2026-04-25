-- ============================================================
-- Update Admin Credentials
-- Email: info@shanfixtechnology.com
-- Password: [The one you provided]
-- ============================================================

-- If admin exists, update it. If not, insert it.
INSERT INTO `users` (`name`, `email`, `phone`, `password_hash`, `role`, `sms_units`, `status`)
VALUES (
  'Shanfix Admin',
  'info@shanfixtechnology.com',
  '+254700000000',
  '$2y$12$CR7NANdPPdjMuG0lUc13yeLiM4DtIIBm7RnJAO1w4hIK8eLX6RJgy', -- Generated hash for Shan@123@1s
  'admin',
  999999.00,
  'active'
)
ON DUPLICATE KEY UPDATE 
  `name` = 'Shanfix Admin',
  `password_hash` = '$2y$12$CR7NANdPPdjMuG0lUc13yeLiM4DtIIBm7RnJAO1w4hIK8eLX6RJgy',
  `role` = 'admin';

-- Optionally remove the default demo admin if it exists
DELETE FROM `users` WHERE `email` = 'admin@bulksms.com';
