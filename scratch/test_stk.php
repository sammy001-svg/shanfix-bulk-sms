<?php
require_once 'includes/db.php';
require_once 'includes/gateways/kopokopo.php';

$phone = '254700000000'; // Dummy phone
$amount = 10.00;
$purchaseId = 9999;

$res = KopoKopo::initiateSTKPush($phone, $amount, $purchaseId);
echo json_encode($res, JSON_PRETTY_PRINT);
