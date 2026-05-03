<?php
/**
 * Shanfix Debug Tool
 * Upload this to cPanel and visit yourdomain.com/debug.php
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Shanfix System Debug</h1>";
echo "PHP Version: " . PHP_VERSION . "<br>";

echo "<h3>Testing Core Files...</h3>";

$files = [
    'includes/db.php',
    'includes/auth.php',
    'includes/topbar.php',
    'client/layout.php'
];

foreach ($files as $file) {
    echo "Checking $file: ";
    if (!file_exists($file)) {
        echo "<span style='color:red'>MISSING</span><br>";
        continue;
    }
    
    // Attempt to include
    try {
        // We use a separate process to catch fatal syntax errors
        $output = [];
        $return_var = 0;
        exec("php -l " . escapeshellarg($file), $output, $return_var);
        
        if ($return_var === 0) {
            echo "<span style='color:green'>SYNTAX OK</span><br>";
        } else {
            echo "<span style='color:red'>SYNTAX ERROR: " . implode(" ", $output) . "</span><br>";
        }
    } catch (Exception $e) {
        echo "<span style='color:red'>ERROR: " . $e->getMessage() . "</span><br>";
    }
}

echo "<h3>Testing DB Connection...</h3>";
require_once 'includes/db.php';
try {
    DB::getInstance();
    echo "<span style='color:green'>DB CONNECTED</span><br>";
} catch (Exception $e) {
    echo "<span style='color:red'>DB FAILED: " . $e->getMessage() . "</span><br>";
}

echo "<h3>Done. If you see RED above, that is why your page is blank.</h3>";
