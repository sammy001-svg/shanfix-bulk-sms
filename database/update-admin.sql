-- Clean and Update Kopo Kopo Settings
UPDATE system_settings SET value = TRIM('-jNf19cpu9BRQWfqjKcRMPGPXZvv9k-M9Eh-xKfHQJk') WHERE `key` = 'kk_client_id';
UPDATE system_settings SET value = TRIM('7cYIWiB6dk4ThVkzeZNt-252PoXkU4SbxJhn0L3XnEI') WHERE `key` = 'kk_client_secret';
UPDATE system_settings SET value = TRIM('https://api.kopokopo.com') WHERE `key` = 'kk_base_url';
UPDATE system_settings SET value = TRIM('5698666') WHERE `key` = 'kk_till_number';

