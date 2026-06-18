<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');
csrf_verify();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/notifications.php');
}

$title    = sanitize($_POST['title'] ?? '');
$message  = sanitize($_POST['message'] ?? '');
$type     = in_array($_POST['type'] ?? '', ['info','success','warning','danger']) ? $_POST['type'] : 'info';
$audience = sanitize($_POST['audience'] ?? 'all');
$userId   = ($audience === 'user') ? ((int)($_POST['user_id'] ?? 0) ?: null) : null;
$isPopup  = isset($_POST['is_popup'])  ? 1 : 0;
$isBanner = isset($_POST['is_banner']) ? 1 : 0;
$imageUrl = null;

if (!$title || !$message) {
    flash_set('warning', 'Title and message are required.');
    redirect('/admin/notifications.php');
}

// ── Image upload ─────────────────────────────────────────────────
if (!empty($_FILES['image']['name'])) {
    $targetDir = __DIR__ . '/../../uploads/notifications/';
    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

    $ext        = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowedExt = ['jpg','jpeg','png','gif'];
    if (in_array($ext, $allowedExt) && $_FILES['image']['size'] <= 2 * 1024 * 1024) {
        $fileName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetDir . $fileName)) {
            $imageUrl = '/uploads/notifications/' . $fileName;
        }
    }
}

// ── Resolve target user list ─────────────────────────────────────
$sent = 0;
if ($audience === 'all') {
    // Single broadcast row — user_id = NULL reaches everyone
    $ok = notify(null, $title, $message, $type, (bool)$isPopup, $imageUrl, (bool)$isBanner);
    $sent = $ok ? 1 : 0;
} elseif ($audience === 'resellers' || $audience === 'clients') {
    $role    = $audience === 'resellers' ? 'reseller' : 'client';
    $targets = DB::query(
        "SELECT id FROM users WHERE role = ? AND status = 'active'", [$role]
    );
    foreach ($targets as $t) {
        if (notify($t['id'], $title, $message, $type, (bool)$isPopup, $imageUrl, (bool)$isBanner)) {
            $sent++;
        }
    }
} elseif ($audience === 'user' && $userId) {
    $exists = DB::queryOne("SELECT id FROM users WHERE id = ? AND status = 'active'", [$userId]);
    if ($exists) {
        $ok = notify($userId, $title, $message, $type, (bool)$isPopup, $imageUrl, (bool)$isBanner);
        $sent = $ok ? 1 : 0;
    }
}

if ($sent > 0) {
    $label = $audience === 'all' ? 'broadcast to all users' : ($audience === 'user' ? 'sent to user' : "sent to $sent $audience");
    flash_set('success', "Notification $label successfully.");
} else {
    flash_set('danger', 'No notifications were sent. Check that target users exist and are active.');
}

redirect('/admin/notifications.php');
