<?php
/**
 * Root index.php — redirects based on session role
 */
require_once __DIR__ . '/includes/auth.php';

$user = auth_user();
if ($user) {
    $dest = match ($user['role']) {
        'admin'    => '/admin/',
        'reseller' => '/reseller/',
        'client'   => '/client/',
        default    => '/login.php',
    };
    redirect($dest);
} else {
    redirect('/login.php');
}
