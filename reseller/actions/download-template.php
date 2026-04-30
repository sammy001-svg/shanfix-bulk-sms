<?php
/**
 * Action: Download CSV Template - Shanfix Technology
 */
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="shanfix_bulk_import_template.csv"');

$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, ['phone', 'name', 'email']);

// Add a few example rows
fputcsv($output, ['+254712345678', 'John Doe', 'john@example.com']);
fputcsv($output, ['+254798765432', 'Jane Smith', '']);
fputcsv($output, ['0111862053', 'Peter Parker', 'peter@example.com']);

fclose($output);
exit();
