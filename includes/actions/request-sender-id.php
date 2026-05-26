<?php
/**
 * Action: Request Sender ID - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
$user = auth_user();
if (!$user) redirect('/login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senderId = strtoupper(sanitize($_POST['sender_id'] ?? ''));
    $purpose  = sanitize($_POST['purpose'] ?? '');

    // Basic Validation
    if (strlen($senderId) < 3 || strlen($senderId) > 11) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Sender ID must be between 3 and 11 characters.'];
        redirect(safe_referer('/'));
    }

    // Handle File Uploads
    $uploadDir = __DIR__ . '/../../uploads/documents/';
    $appLetterPath = null;
    $regCertPath = null;

    $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];

    if (isset($_FILES['application_letter']) && $_FILES['application_letter']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['application_letter']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExts)) {
            $filename = 'app_letter_' . $user['id'] . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['application_letter']['tmp_name'], $uploadDir . $filename)) {
                $appLetterPath = 'uploads/documents/' . $filename;
            }
        }
    }

    if (isset($_FILES['registration_cert']) && $_FILES['registration_cert']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['registration_cert']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExts)) {
            $filename = 'reg_cert_' . $user['id'] . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['registration_cert']['tmp_name'], $uploadDir . $filename)) {
                $regCertPath = 'uploads/documents/' . $filename;
            }
        }
    }

    if (!$appLetterPath || !$regCertPath) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Both Application Letter and Registration Certificate are required (PDF/JPG/PNG).'];
        redirect(safe_referer('/'));
    }

    $id = DB::insert("INSERT INTO sender_ids (user_id, sender_id, purpose, application_letter, registration_cert, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())", 
                     [$user['id'], $senderId, $purpose, $appLetterPath, $regCertPath]);

    if ($id) {
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Sender ID request submitted with documents. Pending review.'];
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Database error. Duplicate Sender ID requested?'];
    }

    redirect(safe_referer('/'));
}
