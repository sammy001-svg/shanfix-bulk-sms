<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/gateways/kopokopo.php';

$token = KopoKopo::getToken();
if ($token) {
    echo "SUCCESS: Token obtained: " . substr($token, 0, 10) . "...";
} else {
    echo "FAILED: Still failing to obtain access token.";
}
