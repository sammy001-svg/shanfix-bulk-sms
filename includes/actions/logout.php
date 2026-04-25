<?php
/**
 * Auth Action: Logout - Shanfix Technology
 */
require_once __DIR__ . '/../auth.php';
auth_logout();
redirect('/login.php');
