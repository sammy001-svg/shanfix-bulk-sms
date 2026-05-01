<?php
/**
 * Action: Download Centralized CSV Template - Shanfix Technology
 * Optimized for Excel and Browser compatibility
 */

// Capture output to calculate length
ob_start();

// Add UTF-8 BOM for Excel compatibility
echo "\xEF\xBB\xBF";

$output = fopen('php://temp', 'r+');
// Add CSV headers
fputcsv($output, ['phone', 'name', 'email']);

// Add a few example rows
fputcsv($output, ['254712345678', 'John Doe', 'john@example.com']);
fputcsv($output, ['254798765432', 'Jane Smith', '']);
fputcsv($output, ['0111862053', 'Peter Parker', 'peter@example.com']);

rewind($output);
echo stream_get_contents($output);
fclose($output);

$csv_content = ob_get_contents();
$csv_length = ob_get_length();
ob_end_clean();

// Set robust headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="shanfix_bulk_import_template.csv"');
header('Content-Length: ' . $csv_length);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo $csv_content;
exit();
