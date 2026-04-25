UPDATE users SET password_hash='$2y$10$sygHJaZb8JbDNUpNg6utO.ZqQo/wSEpbnkxk/Iuj4TADSo8ch/F6.' WHERE email='admin@bulksms.com';
UPDATE users SET password_hash='$2y$10$Zff4or2fKCMkzCRcOr60de/2ggOO7qyeh6L9Bn44wfXOrX7rVRqfi' WHERE email='reseller@bulksms.com';
UPDATE users SET password_hash='$2y$10$ut805c4TdQt21auZZkMho.Xs9eAWIabBatVIEUpDWro49jCwaHziC' WHERE email='client@bulksms.com';
SELECT email, LEFT(password_hash,12) as hash_start FROM users;
