<?php
/**
 * Action: Download Sender ID Application Template
 */

$content = "SHANFIX TECHNOLOGY - SENDER ID APPLICATION TEMPLATE\n";
$content .= "===================================================\n\n";
$content .= "Date: [Insert Date]\n\n";
$content .= "To: The Administrator,\n";
$content .= "    Shanfix Technology.\n\n";
$content .= "RE: APPLICATION FOR SENDER ID: [INSERT_SENDER_ID_HERE]\n\n";
$content .= "Dear Sir/Madam,\n\n";
$content .= "We, [Company Name], hereby apply for the registration of the Sender ID '[INSERT_SENDER_ID_HERE]' for our official communication purposes. \n\n";
$content .= "The purpose of this Sender ID is [e.g., sending appointment reminders, marketing updates, etc.]. We confirm that we have the right to use this name and will not use it for any illegal or fraudulent activities.\n\n";
$content .= "Attached find our Business Registration / Incorporation certificate for your reference.\n\n";
$content .= "Thank you for your assistance.\n\n";
$content .= "Yours faithfully,\n\n";
$content .= "---------------------------\n";
$content .= "[Signature]\n";
$content .= "[Full Name]\n";
$content .= "[Designation]\n";
$content .= "[Company Stamp]\n";

header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="sender_id_application_template.txt"');
header('Content-Length: ' . strlen($content));

echo $content;
exit();
