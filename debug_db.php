<?php
/**
 * DB Debug Tool - Bulk SMS
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/db.php';

echo "<h2>Database Connection Debug</h2>";
echo "Testing credentials...<br>";
echo "HOST: " . DB_HOST . "<br>";
echo "DB: " . DB_NAME . "<br>";
echo "USER: " . DB_USER . "<br>";

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "<h3 style='color:green'>Success! Connection established.</h3>";
} catch (PDOException $e) {
    echo "<h3 style='color:red'>Connection Failed!</h3>";
    echo "<b>Error Message:</b> " . $e->getMessage() . "<br>";
    echo "<b>Error Code:</b> " . $e->getCode() . "<br>";
    
    if ($e->getCode() == 1045) {
        echo "<i>Hint: Access Denied. Check your username and password.</i>";
    } elseif ($e->getCode() == 1049) {
        echo "<i>Hint: Unknown Database. Check your DB name (did you include the cPanel prefix?).</i>";
    } elseif ($e->getCode() == 2002) {
        echo "<i>Hint: Cannot connect to server. Try using '127.0.0.1' instead of 'localhost'.</i>";
    }
}

echo "<hr><h3>Testing DB::getInstance() class method...</h3>";
try {
    $instance = DB::getInstance();
    echo "<h3 style='color:green'>Success! DB::getInstance() returned a valid PDO instance.</h3>";
} catch (Throwable $t) {
    echo "<h3 style='color:red'>DB::getInstance() FAILED!</h3>";
    echo "<b>Exception:</b> " . $t->getMessage() . "<br>";
}
