<?php
/**
 * Action: Save Reseller Branding Settings
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
$user = auth_user();
require_role('reseller');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid CSRF token.');
        redirect('/reseller/branding.php');
    }

    $uid = $user['id'];
    $section = $_POST['section'] ?? 'branding';

    if ($section === 'billing') {
        $unitPrice = (float)($_POST['unit_price'] ?? 0);
        $instructions = sanitize($_POST['payment_instructions'] ?? '');

        // Check if settings exist
        $exists = DB::queryOne("SELECT id FROM reseller_settings WHERE reseller_id = ?", [$uid]);
        if ($exists) {
            $sql = "UPDATE reseller_settings SET unit_price = ?, payment_instructions = ? WHERE reseller_id = ?";
            $res = DB::execute($sql, [$unitPrice, $instructions, $uid]);
        } else {
            $sql = "INSERT INTO reseller_settings (reseller_id, unit_price, payment_instructions) VALUES (?, ?, ?)";
            $res = DB::execute($sql, [$uid, $unitPrice, $instructions]);
        }
        
        if ($res !== false) {
            flash_set('success', 'Billing settings updated successfully.');
        } else {
            flash_set('danger', 'Failed to update billing settings.');
        }
    } else {
        $systemName   = sanitize($_POST['system_name']);
        $primaryColor = sanitize($_POST['primary_color']);
        $sidebarColor = sanitize($_POST['sidebar_color']);
        $supportEmail = sanitize($_POST['support_email']);
        $supportPhone = sanitize($_POST['support_phone']);
        $smtpHost     = sanitize($_POST['smtp_host']);
        $smtpPort     = sanitize($_POST['smtp_port']);
        $smtpUser     = sanitize($_POST['smtp_user']);
        $smtpPass     = (!empty($_POST['smtp_pass']) && $_POST['smtp_pass'] !== '********') ? $_POST['smtp_pass'] : null;

        // Handle Logo Upload
        $logoPath = null;
        if (isset($_FILES['system_logo']) && $_FILES['system_logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/branding/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $ext = strtolower(pathinfo($_FILES['system_logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg'])) {
                $filename = 'logo_' . $uid . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['system_logo']['tmp_name'], $uploadDir . $filename)) {
                    $logoPath = '/uploads/branding/' . $filename;
                }
            }
        }

        // Check if settings exist
        $exists = DB::queryOne("SELECT id FROM reseller_settings WHERE reseller_id = ?", [$uid]);

        if ($exists) {
            $sql = "UPDATE reseller_settings SET 
                    system_name = ?, 
                    primary_color = ?, 
                    sidebar_color = ?,
                    support_email = ?, 
                    support_phone = ?,
                    smtp_host = ?,
                    smtp_port = ?,
                    smtp_user = ?";
            
            $params = [$systemName, $primaryColor, $sidebarColor, $supportEmail, $supportPhone, $smtpHost, $smtpPort, $smtpUser];

            if ($logoPath) {
                $sql .= ", system_logo = ?";
                $params[] = $logoPath;
            }
            if ($smtpPass) {
                $sql .= ", smtp_pass = ?";
                $params[] = $smtpPass;
            }

            $sql .= " WHERE reseller_id = ?";
            $params[] = $uid;
            $res = DB::execute($sql, $params);
        } else {
            $sql = "INSERT INTO reseller_settings (reseller_id, system_name, primary_color, sidebar_color, system_logo, support_email, support_phone, smtp_host, smtp_port, smtp_user, smtp_pass) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $res = DB::execute($sql, [$uid, $systemName, $primaryColor, $sidebarColor, $logoPath, $supportEmail, $supportPhone, $smtpHost, $smtpPort, $smtpUser, $smtpPass]);
        }

        if ($res !== false) {
            flash_set('success', 'Branding settings updated successfully.');
        } else {
            flash_set('danger', 'Failed to update branding settings.');
        }
    }
    redirect('/reseller/branding.php');
}
