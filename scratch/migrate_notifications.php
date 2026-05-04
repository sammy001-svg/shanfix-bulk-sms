<?php
require_once 'includes/db.php';
try {
    DB::execute("ALTER TABLE notifications ADD COLUMN is_banner TINYINT(1) DEFAULT 0 AFTER is_popup");
    echo "Migration successful: is_banner column added.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
