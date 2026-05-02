<?php
require_once __DIR__ . '/../includes/db.php';

$sqlFile = __DIR__ . '/../database/ussd_full_integration.sql';
if (!file_exists($sqlFile)) {
    die("SQL file not found: $sqlFile\n");
}

$sql = file_get_contents($sqlFile);

// Split SQL by semicolon, but handle IF NOT EXISTS and multi-line statements simply by executing as a single block if possible, 
// or using the PDO exec method which can sometimes handle multiple statements depending on the driver config.
// However, it's safer to split or use a loop if the driver doesn't support multi-queries.

try {
    // For local development with MySQL, multi-query is often enabled or can be handled by exec.
    $pdo = DB::getInstance();
    $pdo->exec($sql);
    echo "Migration applied successfully.\n";
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
