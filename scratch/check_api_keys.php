<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Check current user if possible, or just the last 5 users
$users = DB::query("SELECT id, name, email, api_client_id, api_key FROM users ORDER BY id DESC LIMIT 5");

echo "Recent Users:\n";
foreach ($users as $u) {
    echo "ID: {$u['id']} | Name: {$u['name']} | Email: {$u['email']} | Client ID: " . ($u['api_client_id'] ?: 'NULL') . " | Key: " . ($u['api_key'] ?: 'NULL') . "\n";
}

// Test generation for a specific user ID if needed
// generate_user_api_keys(123);
