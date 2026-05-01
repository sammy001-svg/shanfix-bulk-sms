<?php
/**
 * System Doctor - Shanfix Technology
 * Run this on your cPanel server to diagnose the blank page issue.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>System Diagnostic</h1>";

// 1. Check PHP Version
echo "<h2>1. PHP Environment</h2>";
echo "Current PHP Version: " . PHP_VERSION . "<br>";
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    echo "<b style='color:red'>WARNING: Your PHP version is below 8.0. The code uses 'match' expressions which require PHP 8.0+.</b><br>";
} else {
    echo "<b style='color:green'>SUCCESS: PHP version is compatible.</b><br>";
}

// 2. Check Database Connection
echo "<h2>2. Database Connection</h2>";
if (!file_exists('config.php')) {
    echo "<b style='color:red'>ERROR: config.php is missing!</b><br>";
} else {
    require_once 'config.php';
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo "<b style='color:green'>SUCCESS: Connected to database '" . DB_NAME . "'.</b><br>";
        
        // 3. Check Tables and Columns
        echo "<h2>3. Schema Integrity</h2>";
        $tables = ['users', 'reseller_settings', 'purchases', 'notifications'];
        foreach ($tables as $table) {
            $check = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
            if ($check) {
                echo "<b style='color:green'>SUCCESS: Table '$table' exists.</b><br>";
                
                // Check new columns in reseller_settings
                if ($table === 'reseller_settings') {
                    $cols = $pdo->query("DESCRIBE reseller_settings")->fetchAll(PDO::FETCH_COLUMN);
                    $new_cols = ['sidebar_color', 'unit_price', 'payment_instructions'];
                    foreach ($new_cols as $c) {
                        if (in_array($c, $cols)) {
                            echo " - Column '$c': <span style='color:green'>OK</span><br>";
                        } else {
                            echo " - Column '$c': <b style='color:red'>MISSING!</b> (Run migration)<br>";
                        }
                    }
                }
            } else {
                echo "<b style='color:red'>ERROR: Table '$table' is MISSING!</b><br>";
            }
        }
        
    } catch (PDOException $e) {
        echo "<b style='color:red'>ERROR: Connection Failed: " . $e->getMessage() . "</b><br>";
    }
}

echo "<h2>4. File Permissions</h2>";
$dirs = ['uploads', 'uploads/branding', 'tmp'];
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        echo "Directory '$dir': " . (is_writable($dir) ? "<span style='color:green'>Writable</span>" : "<b style='color:red'>Not Writable</b>") . "<br>";
    } else {
        echo "Directory '$dir': <b style='color:orange'>Missing</b> (Will be created automatically if parent is writable)<br>";
    }
}

echo "<br><hr><p>Please fix the red errors above to restore your site.</p>";
