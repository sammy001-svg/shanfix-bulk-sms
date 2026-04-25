<?php
/**
 * One-time password reset utility
 * DELETE THIS FILE after running!
 */
require_once __DIR__ . '/includes/db.php';

$updates = [
    ['email' => 'admin@bulksms.com',    'password' => 'Admin@1234'],
    ['email' => 'reseller@bulksms.com', 'password' => 'Reseller@1234'],
    ['email' => 'client@bulksms.com',   'password' => 'Client@1234'],
];

$results = [];
foreach ($updates as $u) {
    $hash = password_hash($u['password'], PASSWORD_BCRYPT, ['cost' => 10]);
    $rows = DB::execute(
        "UPDATE users SET password_hash = ? WHERE email = ?",
        [$hash, $u['email']]
    );
    $results[] = [
        'email'   => $u['email'],
        'updated' => $rows > 0 ? '✅ OK' : '❌ Not found',
        'hash'    => substr($hash, 0, 30) . '...',
    ];
}

echo '<pre style="font-family:monospace;font-size:14px;padding:20px;background:#111;color:#0f0;">';
echo "=== Password Reset Results ===\n\n";
foreach ($results as $r) {
    echo "Email:   {$r['email']}\n";
    echo "Status:  {$r['updated']}\n";
    echo "Hash:    {$r['hash']}\n\n";
}
echo "=== Done! DELETE this file now: /reset-passwords.php ===\n";
echo '</pre>';

// Self-delete after running
@unlink(__FILE__);
echo '<p style="color:orange;font-family:monospace;padding:0 20px;">✅ This file has been automatically deleted for security.</p>';
echo '<p style="font-family:monospace;padding:0 20px;"><a href="/login.php">→ Go to Login</a></p>';
