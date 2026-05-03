<?php
/**
 * Advanced Debug & Error Revealer for Shanfix Bulk SMS
 * This script will show you EXACTLY why pages are blank.
 */

// 1. Force Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<div style='font-family:monospace; background:#000; color:#0f0; padding:20px; border-radius:10px; line-height:1.5;'>";
echo "<h2>--- SHANFIX ADVANCED DEBUG ---</h2>";

// 2. Environment Check
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Current File: " . __FILE__ . "<br>";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "<br><br>";

// 3. Test Connection
try {
    require_once __DIR__ . '/includes/db.php';
    echo "[PASS] includes/db.php loaded.<br>";
    
    $pdo = DB::getInstance();
    echo "[PASS] Database connected successfully.<br>";
    
    // 4. Test USSD Analytics Queries
    echo "<br>Testing USSD Analytics Queries:<br>";
    try {
        $count = DB::queryValue("SELECT COUNT(*) FROM ussd_requests");
        echo "[PASS] ussd_requests table accessible. Count: $count<br>";
    } catch (Exception $e) {
        echo "[FAIL] ussd_requests error: " . $e->getMessage() . "<br>";
    }

    // 5. Test WhatsApp Chatbot Queries
    echo "<br>Testing WhatsApp Chatbot Queries:<br>";
    try {
        $count = DB::queryValue("SELECT COUNT(*) FROM whatsapp_chatbots");
        echo "[PASS] whatsapp_chatbots table accessible. Count: $count<br>";
    } catch (Exception $e) {
        echo "[FAIL] whatsapp_chatbots error: " . $e->getMessage() . "<br>";
    }

    // 6. Check for PHP Incompatibilities
    echo "<br>Checking for PHP syntax blockers:<br>";
    if (version_compare(PHP_VERSION, '7.4.0', '<')) {
        echo "[WARN] Your PHP version is below 7.4. Some features like arrow functions or typed properties might crash. I have refactored most of them, but please consider upgrading to 7.4 or 8.0+.<br>";
    } else {
        echo "[PASS] PHP version is modern enough.<br>";
    }

} catch (Throwable $e) {
    echo "<br><span style='color:red; font-weight:bold;'>CRITICAL ERROR CAUGHT:</span><br>";
    echo "Message: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}

echo "<br>--- DEBUG END ---";
echo "</div>";
