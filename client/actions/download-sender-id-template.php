<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('client');

$siteName = get_setting('system_name', 'Shanfix Technology');
$filename = 'sender-id-application-template.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');

$fh = fopen('php://output', 'w');

// Header row
fputcsv($fh, ['SENDER ID APPLICATION TEMPLATE — ' . $siteName]);
fputcsv($fh, ['Fill in the fields below and submit this file along with your request.']);
fputcsv($fh, []);

// Template fields
fputcsv($fh, ['Field', 'Your Answer']);
fputcsv($fh, ['Sender ID (max 11 characters, no spaces)', '']);
fputcsv($fh, ['Business / Brand Name', '']);
fputcsv($fh, ['Business Registration Number (if applicable)', '']);
fputcsv($fh, ['Primary Use Case (e.g. OTP, Marketing, Alerts)', '']);
fputcsv($fh, ['Sample Message', '']);
fputcsv($fh, ['Estimated Monthly Volume (number of messages)', '']);
fputcsv($fh, ['Target Audience (e.g. customers, employees)', '']);
fputcsv($fh, ['Company Website (if applicable)', '']);
fputcsv($fh, ['Contact Email', '']);
fputcsv($fh, ['Contact Phone', '']);
fputcsv($fh, []);
fputcsv($fh, ['NOTE: Sender IDs must be 3–11 alphanumeric characters. No special characters allowed.']);
fputcsv($fh, ['Approval typically takes 1–2 business days.']);

fclose($fh);
exit;
