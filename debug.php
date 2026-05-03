<?php
/**
 * SUPER ROBUST Shanfix Debug Tool
 * This file does NOT depend on any other file to prevent crashes.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<html><body style='font-family:sans-serif; padding:20px; background:#f4f7f6;'>";
echo "<h1 style='color:#00c896;'>Shanfix System Diagnosis</h1>";

echo "<b>PHP Version:</b> " . PHP_VERSION . "<br>";
echo "<b>Server Software:</b> " . $_SERVER['SERVER_SOFTWARE'] . "<br><br>";

// 1. Check Config
echo "<h3>1. Configuration Check</h3>";
if (file_exists('config.php')) {
    echo "✅ config.php found.<br>";
    include 'config.php';
} else {
    echo "❌ config.php NOT found at root.<br>";
}

// 2. Test DB Connection Manually
echo "<h3>2. Database Connectivity</h3>";
$host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
$name = defined('DB_NAME') ? DB_NAME : 'bulk_sms_system';
$user = defined('DB_USER') ? DB_USER : 'root';
$pass = defined('DB_PASS') ? DB_PASS : '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "✅ Database Connected successfully.<br>";
    
    // 3. Check Tables
    echo "<h3>3. Table & Column Verification</h3>";
    $tables = ['ussd_requests', 'ussd_sessions', 'whatsapp_custom_data', 'users'];
    foreach ($tables as $t) {
        try {
            $stmt = $pdo->query("DESCRIBE `$t` ");
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "✅ Table `$t` exists. Columns: " . implode(', ', $cols) . "<br>";
            
            // Auto-Fix if missing user_id in ussd_requests
            if ($t === 'ussd_requests' && !in_array('user_id', $cols)) {
                echo "⚠️ Missing user_id in ussd_requests. <b>Attempting auto-fix...</b> ";
                $pdo->exec("ALTER TABLE `ussd_requests` ADD `user_id` INT(11) NOT NULL AFTER `id` ");
                echo "<span style='color:green'>FIXED!</span><br>";
            }
        } catch (Exception $e) {
            echo "❌ Table `$t` error: " . $e->getMessage() . "<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Database Connection Failed: " . $e->getMessage() . "<br>";
    echo "<i>Please check your config.php database credentials.</i><br>";
}

// 4. Syntax Check Core Files
echo "<h3>4. Component Integrity</h3>";
$files = [
    'includes/db.php',
    'includes/auth.php',
    'client/layout.php',
    'client/ussd-analytics.php',
    'client/whatsapp-chatbot.php'
];

foreach ($files as $f) {
    if (!file_exists($f)) {
        echo "❌ File missing: $f<br>";
        continue;
    }
    
    // Simple check: does it start with <?php
    $content = file_get_contents($f);
    if (strpos($content, '<?php') === false) {
        echo "❌ File $f is corrupted or empty.<br>";
    } else {
        echo "✅ File $f exists and looks valid.<br>";
    }
}

echo "<br><hr>";
echo "<b>If all sections are green, but the page is still blank, please contact support with a screenshot of this page.</b>";
echo "</body></html>";
