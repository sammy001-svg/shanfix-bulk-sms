<?php
/**
 * Action: Download Centralized CSV Template - Shanfix Technology
 * Optimized for Excel compatibility (includes UTF-8 BOM)
 */

// Clean any previous output to avoid corrupted file
if (ob_get_level()) ob_end_clean();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="shanfix_bulk_import_template.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add CSV headers
fputcsv($output, ['phone', 'name', 'email']);

// Add a few example rows
fputcsv($output, ['254712345678', 'John Doe', 'john@example.com']);
fputcsv($output, ['254798765432', 'Jane Smith', '']);
fputcsv($output, ['0111862053', 'Peter Parker', 'peter@example.com']);

fclose($output);
exit();
