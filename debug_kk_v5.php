<?php
/**
 * IMPROVED DEBUGGER (v5) - Testing the actual Class Method
 */
require_once 'includes/db.php';
require_once 'includes/gateways/kopokopo.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Kopo Kopo Connection Debugger (v5)</h2>";

echo "<h4>Step 1: Testing via Class Method Directly</h4>";
$phoneNumber = '0700000000'; // Test number
$amount = 10;
$purchaseId = 9999;

echo "Calling KopoKopo::initiateSTKPush($phoneNumber, $amount, $purchaseId)...<br>";
$result = KopoKopo::initiateSTKPush($phoneNumber, $amount, $purchaseId);

echo "<h4>Result:</h4>";
echo "<pre>";
print_r($result);
echo "</pre>";

if ($result['success']) {
    echo "<span style='color:green;font-size:20px'>SUCCESS! The class method works perfectly.</span>";
} else {
    echo "<span style='color:red;font-size:20px'>FAILED! The class method failed.</span>";
    echo "<p>This means the problem is inside the <code>initiateSTKPush</code> logic in <code>kopokopo.php</code>.</p>";
}
?>
