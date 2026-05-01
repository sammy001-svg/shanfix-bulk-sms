<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

echo "Testing key generation for User ID 1...\n";
$res = generate_user_api_keys(1);
echo "Result: " . ($res ? "Success" : "Failed") . "\n";

$u = DB::queryOne("SELECT api_client_id, api_key FROM users WHERE id = 1");
echo "Client ID: " . ($u['api_client_id'] ?: 'NULL') . "\n";
echo "Key: " . ($u['api_key'] ?: 'NULL') . "\n";
