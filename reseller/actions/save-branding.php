<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('reseller');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/reseller/branding.php');
}

if (!csrf_verify()) {
    flash_set('danger', 'Security token mismatch. Please try again.');
    redirect('/reseller/branding.php');
}

$uid = current_user()['id'];
$tab = in_array($_POST['active_tab'] ?? '', ['identity','colors','contact','email','billing'])
       ? $_POST['active_tab'] : 'identity';

// ── Text field sanitisation ──────────────────────────────────────────────────
$systemName   = sanitize($_POST['system_name'] ?? '');
$supportEmail = sanitize($_POST['support_email'] ?? '');
$supportPhone = sanitize($_POST['support_phone'] ?? '');
$smtpHost     = sanitize($_POST['smtp_host'] ?? '');
$smtpPort     = (int)($_POST['smtp_port'] ?? 587) ?: null;
$smtpUser     = sanitize($_POST['smtp_user'] ?? '');
$smtpEnc      = in_array($_POST['smtp_encryption'] ?? '', ['none', 'ssl', 'tls'], true)
                ? $_POST['smtp_encryption'] : 'tls';
$unitPrice    = ($_POST['unit_price'] ?? '') !== '' ? max(0, (float)$_POST['unit_price']) : null;
$payInstr     = sanitize($_POST['payment_instructions'] ?? '');

// ── Validate color fields (must be 6-digit hex) ──────────────────────────────
$primaryColor = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['primary_color'] ?? '')
                ? $_POST['primary_color'] : '#00c896';
$sidebarColor = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['sidebar_color'] ?? '')
                ? $_POST['sidebar_color'] : '#0e1726';

// ── Basic validation ─────────────────────────────────────────────────────────
if (!$systemName) {
    flash_set('danger', 'Platform name is required.');
    redirect('/reseller/branding.php?tab=' . $tab);
}

if ($supportEmail && !filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
    flash_set('danger', 'Invalid support email address.');
    redirect('/reseller/branding.php?tab=contact');
}

// ── SMTP password — preserve existing if blank ────────────────────────────────
$smtpPass = null;
$rawPass  = $_POST['smtp_pass'] ?? '';
if ($rawPass !== '') {
    $smtpPass = $rawPass;
}

// ── Logo upload ───────────────────────────────────────────────────────────────
$logoPath = null; // null = don't touch DB column

if (!empty($_POST['remove_logo'])) {
    $logoPath = ''; // empty string = clear the column
} elseif (isset($_FILES['system_logo']) && $_FILES['system_logo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['system_logo'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file['size'] > 2 * 1024 * 1024) {
        flash_set('danger', 'Logo file is too large. Maximum size is 2 MB.');
        redirect('/reseller/branding.php?tab=identity');
    }

    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'webp'], true)) {
        flash_set('danger', 'Logo must be PNG, JPG, SVG, or WebP.');
        redirect('/reseller/branding.php?tab=identity');
    }

    $uploadDir = __DIR__ . '/../../uploads/branding/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'logo_' . $uid . '_' . time() . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        $logoPath = '/uploads/branding/' . $filename;
    } else {
        flash_set('danger', 'Failed to save logo. Check server upload permissions.');
        redirect('/reseller/branding.php?tab=identity');
    }
}

// ── Build UPDATE — only include columns that have new data ────────────────────
$sets   = [];
$params = [];

$always = [
    'system_name'          => $systemName,
    'primary_color'        => $primaryColor,
    'sidebar_color'        => $sidebarColor,
    'support_email'        => $supportEmail ?: null,
    'support_phone'        => $supportPhone ?: null,
    'smtp_host'            => $smtpHost ?: null,
    'smtp_port'            => $smtpPort,
    'smtp_encryption'      => $smtpEnc,
    'smtp_user'            => $smtpUser ?: null,
    'unit_price'           => $unitPrice,
    'payment_instructions' => $payInstr ?: null,
];

foreach ($always as $col => $val) {
    $sets[]   = "`$col` = ?";
    $params[] = $val;
}

if ($logoPath !== null) {
    $sets[]   = "`system_logo` = ?";
    $params[] = $logoPath ?: null;
}

if ($smtpPass !== null) {
    $sets[]   = "`smtp_pass` = ?";
    $params[] = $smtpPass;
}

$params[] = $uid;

DB::execute(
    "UPDATE reseller_settings SET " . implode(', ', $sets) . " WHERE reseller_id = ?",
    $params
);

flash_set('success', 'Branding settings saved successfully.');
redirect('/reseller/branding.php?tab=' . $tab);
