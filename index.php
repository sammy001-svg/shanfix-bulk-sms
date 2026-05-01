<?php
/**
 * Root index.php — redirects based on session role
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/auth.php';

$user = auth_user();
if ($user) {
    switch ($user['role']) {
        case 'admin':    $dest = '/admin/'; break;
        case 'reseller': $dest = '/reseller/'; break;
        case 'client':   $dest = '/client/'; break;
        default:         $dest = '/login.php'; break;
    }
    redirect($dest);
} else {
    redirect('/login.php');
}
