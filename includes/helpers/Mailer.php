<?php
/**
 * Mailer — minimal SMTP email sender for Shanfix Technology.
 *
 * Uses raw PHP streams (no Composer, no PHPMailer dependency) so it works
 * on cPanel shared hosting without any extra packages.
 *
 * Configuration keys read from system_settings:
 *   smtp_host, smtp_port, smtp_encryption (tls|ssl),
 *   smtp_user, smtp_pass, smtp_from_name,
 *   site_name (fallback from-name), support_email (fallback from-address)
 */
class Mailer {
    private static ?array $cfg = null;

    private static function cfg(): array {
        if (self::$cfg !== null) return self::$cfg;
        $keys = "'smtp_host','smtp_port','smtp_encryption','smtp_user','smtp_pass','smtp_from_name','site_name','support_email','site_url'";
        $rows = DB::query("SELECT `key`, `value` FROM system_settings WHERE `key` IN ($keys)");
        $s = [];
        foreach ($rows as $r) $s[$r['key']] = $r['value'];
        self::$cfg = $s;
        return $s;
    }

    /**
     * Send an HTML email.
     * Always returns true/false — never throws to the caller.
     */
    public static function send(string $toAddr, string $toName, string $subject, string $html): bool {
        $cfg      = self::cfg();
        $fromAddr = trim($cfg['smtp_user'] ?? ($cfg['support_email'] ?? ''));
        $fromName = trim($cfg['smtp_from_name'] ?? ($cfg['site_name'] ?? 'Shanfix Technology'));

        if (!$fromAddr) {
            error_log("Mailer: smtp_user not configured — cannot send email to $toAddr");
            return false;
        }

        try {
            return self::smtp($toAddr, $toName, $fromAddr, $fromName, $subject, $html);
        } catch (Throwable $e) {
            error_log("Mailer::send() to $toAddr failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Wrap content in a branded HTML email shell.
     *
     * @param string $title    Shown in the green header bar.
     * @param string $bodyHtml Inner HTML — use <p>, <a>, <strong>, etc.
     */
    public static function html(string $title, string $bodyHtml): string {
        $cfg      = self::cfg();
        $siteName = htmlspecialchars($cfg['site_name'] ?? 'Shanfix Technology');
        $year     = date('Y');
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{margin:0;padding:0;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif}
.wrap{max-width:600px;margin:40px auto;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.09)}
.hdr{background:#00c896;padding:28px 36px}
.hdr h2{margin:0;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-.3px}
.body{padding:32px 36px;color:#2c3e50;font-size:15px;line-height:1.65}
.body a{color:#00c896}
.body p{margin:0 0 16px}
.btn-link{display:inline-block;background:#00c896;color:#ffffff !important;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:700;font-size:15px}
.footer{padding:18px 36px;background:#f4f5f7;font-size:12px;color:#999;text-align:center;border-top:1px solid #e8e8e8}
</style>
</head>
<body>
<div class="wrap">
  <div class="hdr"><h2>$siteName</h2></div>
  <div class="body">
    <h3 style="margin:0 0 20px;color:#1a252f;font-size:18px">$title</h3>
    $bodyHtml
  </div>
  <div class="footer">&copy; $year $siteName &mdash; This is an automated message, please do not reply.</div>
</div>
</body>
</html>
HTML;
    }

    // ─── Low-level SMTP ───────────────────────────────────────────────────────

    private static function smtp(
        string $toAddr, string $toName,
        string $fromAddr, string $fromName,
        string $subject, string $html
    ): bool {
        $cfg  = self::cfg();
        $host = $cfg['smtp_host'] ?? 'smtp.gmail.com';
        $port = (int)($cfg['smtp_port'] ?? 587);
        $enc  = strtolower($cfg['smtp_encryption'] ?? 'tls');
        $user = $cfg['smtp_user'] ?? '';
        $pass = $cfg['smtp_pass'] ?? '';

        $ctx        = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $socketDsn  = ($enc === 'ssl') ? "ssl://{$host}" : "tcp://{$host}";
        $sock       = @stream_socket_client("{$socketDsn}:{$port}", $errno, $errstr, 15,
                                            STREAM_CLIENT_CONNECT, $ctx);
        if (!$sock) {
            throw new \RuntimeException("SMTP connect to {$host}:{$port} failed: $errstr ($errno)");
        }
        stream_set_timeout($sock, 15);

        // Read a full (possibly multi-line) SMTP response; returns the last status code line.
        $read = static function () use ($sock): string {
            $data = '';
            while ($line = fgets($sock, 1024)) {
                $data .= $line;
                // Multi-line responses: continuation lines have '-' at pos 3; last line has ' '.
                if (strlen($line) < 4 || $line[3] === ' ') break;
            }
            return $data;
        };
        $cmd = static function (string $c) use ($sock, $read): string {
            fwrite($sock, $c . "\r\n");
            return $read();
        };

        $ehlo = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $read();          // server greeting
        $cmd("EHLO $ehlo");

        if ($enc === 'tls') {
            $r = $cmd("STARTTLS");
            if (strpos($r, '220') === false) {
                throw new \RuntimeException("STARTTLS rejected: " . trim($r));
            }
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException("TLS handshake failed");
            }
            $cmd("EHLO $ehlo");
        }

        if ($user && $pass) {
            $cmd("AUTH LOGIN");
            $cmd(base64_encode($user));
            $r = $cmd(base64_encode($pass));
            if (strpos($r, '235') === false) {
                throw new \RuntimeException("SMTP AUTH LOGIN failed: " . trim($r));
            }
        }

        $cmd("MAIL FROM:<{$fromAddr}>");
        $r = $cmd("RCPT TO:<{$toAddr}>");
        if (strpos($r, '250') === false && strpos($r, '251') === false) {
            throw new \RuntimeException("RCPT TO rejected for $toAddr: " . trim($r));
        }

        $cmd("DATA");

        $msgId      = bin2hex(random_bytes(8)) . '@' . $ehlo;
        $b64body    = chunk_split(base64_encode($html), 76, "\r\n");
        $toDisplay  = $toName
            ? '=?UTF-8?B?' . base64_encode($toName) . '?= <' . $toAddr . '>'
            : $toAddr;
        $fromDisplay = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromAddr . '>';
        $subjectEnc  = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $message = "From: $fromDisplay\r\n"
            . "To: $toDisplay\r\n"
            . "Subject: $subjectEnc\r\n"
            . "Message-ID: <$msgId>\r\n"
            . "Date: " . date('r') . "\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "\r\n"
            . $b64body
            . "\r\n.\r\n";

        fwrite($sock, $message);
        $read();  // 250 OK

        $cmd("QUIT");
        fclose($sock);
        return true;
    }
}
