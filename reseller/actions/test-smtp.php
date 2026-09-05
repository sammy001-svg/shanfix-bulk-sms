<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('reseller');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

if (!csrf_verify()) {
    echo json_encode(['success' => false, 'error' => 'Security token mismatch.']);
    exit;
}

$me  = current_user();
$uid = $me['id'];

$row = DB::queryOne(
    "SELECT smtp_host, smtp_port, smtp_encryption, smtp_user, smtp_pass FROM reseller_settings WHERE reseller_id = ?",
    [$uid]
);

if (!$row || empty($row['smtp_host']) || empty($row['smtp_user'])) {
    echo json_encode(['success' => false, 'error' => 'SMTP is not configured. Save your email settings first.']);
    exit;
}

$toEmail = $me['email'] ?? '';
if (!$toEmail) {
    echo json_encode(['success' => false, 'error' => 'Cannot determine your account email address.']);
    exit;
}

$host    = $row['smtp_host'];
$port    = (int)($row['smtp_port'] ?: 587);
$enc     = strtolower($row['smtp_encryption'] ?: 'tls');
$smtpUser = $row['smtp_user'];
$smtpPass = $row['smtp_pass'];
$from     = $smtpUser;
$fromName = $me['name'] ?? 'Reseller Panel';
$domain   = $_SERVER['HTTP_HOST'] ?? 'localhost';

// ── Build message ─────────────────────────────────────────────────────────────
$subject = 'Test Email — SMTP Config Verified';
$msgId   = '<test.' . time() . '@' . $domain . '>';
$date    = date('r');
$html    = "<html><body style='font-family:sans-serif;color:#333;max-width:560px;margin:auto;padding:32px'>
  <h2 style='color:#00c896;margin-top:0'>&#9989; SMTP Test Successful</h2>
  <p>Your SMTP configuration is working correctly. Transactional emails will be delivered from your own domain.</p>
  <table style='border-collapse:collapse;font-size:13px;width:100%'>
    <tr><td style='padding:6px 0;color:#666;width:120px'>Host</td><td><strong>{$host}</strong></td></tr>
    <tr><td style='padding:6px 0;color:#666'>Port</td><td><strong>{$port}</strong></td></tr>
    <tr><td style='padding:6px 0;color:#666'>Encryption</td><td><strong>" . strtoupper($enc) . "</strong></td></tr>
    <tr><td style='padding:6px 0;color:#666'>Sender</td><td><strong>{$from}</strong></td></tr>
  </table>
  <p style='color:#888;font-size:12px;margin-top:24px'>Sent from your reseller panel at <strong>{$domain}</strong>.</p>
</body></html>";

$rawMessage = "From: {$fromName} <{$from}>\r\n"
            . "To: {$toEmail}\r\n"
            . "Subject: {$subject}\r\n"
            . "Date: {$date}\r\n"
            . "Message-ID: {$msgId}\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "\r\n"
            . $html;

// ── SMTP connection ───────────────────────────────────────────────────────────
$ctx  = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
$dsn  = ($enc === 'ssl') ? "ssl://{$host}" : "tcp://{$host}";
$sock = @stream_socket_client("{$dsn}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);

if (!$sock) {
    echo json_encode(['success' => false, 'error' => "Cannot connect to {$host}:{$port} — {$errstr}"]);
    exit;
}

stream_set_timeout($sock, 15);

$read = static function () use ($sock): string {
    $buf = '';
    while ($line = fgets($sock, 1024)) {
        $buf .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') break;
    }
    return $buf;
};
$cmd = static function (string $c) use ($sock, $read): string {
    fwrite($sock, $c . "\r\n");
    return $read();
};

try {
    $read(); // server greeting
    $ehlo = $domain;
    $cmd("EHLO {$ehlo}");

    if ($enc === 'tls') {
        $r = $cmd("STARTTLS");
        if (strpos($r, '220') === false) {
            throw new RuntimeException("STARTTLS rejected: " . trim($r));
        }
        if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException("TLS handshake failed — check your port and encryption settings.");
        }
        $cmd("EHLO {$ehlo}");
    }

    if ($smtpUser && $smtpPass) {
        $cmd("AUTH LOGIN");
        $cmd(base64_encode($smtpUser));
        $r = $cmd(base64_encode($smtpPass));
        if (strpos($r, '235') === false) {
            throw new RuntimeException("Authentication failed — check your username and password. ({$r})");
        }
    }

    $r = $cmd("MAIL FROM:<{$from}>");
    if (strpos($r, '250') === false) {
        throw new RuntimeException("MAIL FROM rejected: " . trim($r));
    }

    $r = $cmd("RCPT TO:<{$toEmail}>");
    if (strpos($r, '250') === false && strpos($r, '251') === false) {
        throw new RuntimeException("Recipient rejected: " . trim($r));
    }

    $cmd("DATA");
    fwrite($sock, $rawMessage . "\r\n.\r\n");
    $r = $read();
    if (strpos($r, '250') === false) {
        throw new RuntimeException("Message rejected by server: " . trim($r));
    }

    $cmd("QUIT");
    fclose($sock);

    echo json_encode(['success' => true, 'message' => "Test email sent to {$toEmail}"]);

} catch (RuntimeException $e) {
    @fclose($sock);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
