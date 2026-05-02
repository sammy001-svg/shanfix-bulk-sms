<?php
require_once __DIR__ . '/../includes/db.php';

$sqlFile = __DIR__ . '/../database/whatsapp_module.sql';
if (!file_exists($sqlFile)) {
    die("SQL file not found: $sqlFile\n");
}

$sql = file_get_contents($sqlFile);

try {
    $pdo = DB::getInstance();
    $pdo->exec($sql);
    echo "WhatsApp migration applied successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    
    // Fallback: try splitting by semicolon
    echo "Attempting fallback split execution...\n";
    $statements = explode(';', $sql);
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt)) continue;
        try {
            $pdo->exec($stmt);
            echo "Executed: " . substr($stmt, 0, 50) . "...\n";
        } catch (Exception $e2) {
            echo "Failed statement: " . substr($stmt, 0, 50) . "...\n";
            echo "Error: " . $e2->getMessage() . "\n";
        }
    }
}
