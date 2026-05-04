<?php
/**
 * Admin Action: Create/Delete Notification
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('danger', 'CSRF token validation failed.');
        redirect('../notifications.php');
    }

    $title   = sanitize($_POST['title'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    $type    = sanitize($_POST['type'] ?? 'info');
    $userId  = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
    $isPopup = isset($_POST['is_popup']) ? 1 : 0;
    $isBanner = isset($_POST['is_banner']) ? 1 : 0;
    $imageUrl = null;

    // Handle Image Upload
    if (!empty($_FILES['image']['name'])) {
        $targetDir = __DIR__ . '/../../uploads/notifications/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $fileName = time() . '_' . basename($_FILES['image']['name']);
        $targetPath = $targetDir . $fileName;
        $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

        // Allow certain file formats
        $allowTypes = ['jpg', 'png', 'jpeg', 'gif'];
        if (in_array($fileType, $allowTypes)) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                $imageUrl = '/uploads/notifications/' . $fileName;
            }
        }
    }

    if ($title && $message) {
        $success = notify($userId, $title, $message, $type, (bool)$isPopup, $imageUrl, (bool)$isBanner);
        if ($success) {
            flash_set('success', 'Notification created and sent successfully.');
        } else {
            flash_set('danger', 'Failed to save notification to database.');
        }
    } else {
        flash_set('warning', 'Title and message are required.');
    }

    redirect('../notifications.php');
}

// Handle Delete
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        DB::execute("DELETE FROM notifications WHERE id = ?", [$id]);
        flash_set('success', 'Notification deleted.');
    }
    redirect('../notifications.php');
}
