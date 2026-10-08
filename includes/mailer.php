<?php
/**
 * WebsiteTailors - Email Notification Service
 *
 * Lightweight, production-ready email dispatcher designed for shared hosting.
 * Supports:
 * 1. Native hosting-compatible PHP mail() with compliant MIME multi-part headers.
 * 2. Secure standalone SMTP socket transport (zero frameworks or third-party dependencies).
 * 3. Safe environment-variable / constant configuration (never exposes credentials in public files).
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) {
    die('Direct access not permitted.');
}

/**
 * Dispatch an email notification for a newly received lead inquiry
 *
 * @param array<string, mixed> $lead
 * @return bool
 */
function send_lead_notification(array $lead): bool
{
    $mailEnabled = defined('MAIL_NOTIFICATIONS_ENABLED') ? MAIL_NOTIFICATIONS_ENABLED : false;
    $adminEmail  = defined('MAIL_ADMIN_ADDRESS') ? MAIL_ADMIN_ADDRESS : get_setting('email', 'websietailorss@gmail.com');
    $fromEmail   = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'websietailorss@gmail.com';
    $fromName    = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'WebsiteTailors Notifications';
    $adminUrl    = defined('ADMIN_URL') ? ADMIN_URL : '/admin';

    $leadName    = htmlspecialchars((string)($lead['name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8');
    $leadEmail   = htmlspecialchars((string)($lead['email'] ?? ''), ENT_QUOTES, 'UTF-8');
    $leadPhone   = htmlspecialchars((string)($lead['phone'] ?? 'None provided'), ENT_QUOTES, 'UTF-8');
    $leadCompany = htmlspecialchars((string)($lead['company'] ?? 'None provided'), ENT_QUOTES, 'UTF-8');
    $leadService = htmlspecialchars((string)($lead['service_interested'] ?? $lead['service'] ?? 'General'), ENT_QUOTES, 'UTF-8');
    $leadBudget  = htmlspecialchars((string)($lead['budget'] ?? 'Not specified'), ENT_QUOTES, 'UTF-8');
    $leadMsg     = nl2br(htmlspecialchars((string)($lead['message'] ?? ''), ENT_QUOTES, 'UTF-8'));
    $leadIp      = htmlspecialchars((string)($lead['ip_address'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8');
    $dateStr     = date('Y-m-d H:i:s T');

    $subject = "New Project Inquiry from {$leadName} — WebsiteTailors";

    // Plain text version
    $textBody = "New Lead Inquiry Received on WebsiteTailors Website\n";
    $textBody .= "==========================================\n\n";
    $textBody .= "Name:     {$lead['name']}\n";
    $textBody .= "Email:    {$lead['email']}\n";
    $textBody .= "Phone:    {$leadPhone}\n";
    $textBody .= "Company:  {$leadCompany}\n";
    $textBody .= "Service:  {$leadService}\n";
    $textBody .= "Budget:   {$leadBudget}\n";
    $textBody .= "Date:     {$dateStr}\n";
    $textBody .= "IP:       {$leadIp}\n\n";
    $textBody .= "Message:\n";
    $textBody .= "{$lead['message']}\n\n";
    $textBody .= "View in Admin Panel: " . ADMIN_URL . "/leads.php\n";

    // HTML Email Template
    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>{$subject}</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f4f3ee; color: #111111; margin: 0; padding: 30px 15px; }
    .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #d8d7d0; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
    .header { background: #111111; color: #ffffff; padding: 25px 30px; }
    .header h1 { margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.02em; }
    .header .tagline { color: #b8ff3d; font-size: 12px; font-family: monospace; margin-top: 4px; }
    .content { padding: 30px; }
    .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 14px; }
    .meta-table td { padding: 10px 12px; border-bottom: 1px solid #f0efe8; vertical-align: top; }
    .meta-table td.label { font-weight: 600; color: #70706b; width: 130px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; }
    .message-box { background: #faf9f5; border: 1px solid #e5e4dc; border-radius: 8px; padding: 18px; margin-top: 15px; font-size: 14px; line-height: 1.6; color: #222; }
    .button-wrap { text-align: center; margin-top: 30px; }
    .btn { display: inline-block; background: #111111; color: #b8ff3d; text-decoration: none; padding: 12px 26px; border-radius: 6px; font-weight: 600; font-size: 14px; }
    .footer { background: #faf9f5; padding: 15px 30px; text-align: center; font-size: 11px; color: #999; border-top: 1px solid #eee; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>WebsiteTailors Lead Dispatch</h1>
      <div class="tagline">NEW PROJECT INQUIRY RECEIVED</div>
    </div>
    <div class="content">
      <table class="meta-table">
        <tr>
          <td class="label">Name</td>
          <td><strong>{$leadName}</strong></td>
        </tr>
        <tr>
          <td class="label">Email</td>
          <td><a href="mailto:{$leadEmail}" style="color: #111; font-weight: 500;">{$leadEmail}</a></td>
        </tr>
        <tr>
          <td class="label">Phone</td>
          <td>{$leadPhone}</td>
        </tr>
        <tr>
          <td class="label">Company</td>
          <td>{$leadCompany}</td>
        </tr>
        <tr>
          <td class="label">Service</td>
          <td>{$leadService}</td>
        </tr>
        <tr>
          <td class="label">Budget</td>
          <td>{$leadBudget}</td>
        </tr>
        <tr>
          <td class="label">Received</td>
          <td>{$dateStr} (IP: {$leadIp})</td>
        </tr>
      </table>

      <div style="font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; color: #70706b;">
        Project Message:
      </div>
      <div class="message-box">
        {$leadMsg}
      </div>

      <div class="button-wrap">
        <a href="{$adminUrl}/leads.php" class="btn">Open Lead in Admin Panel &rarr;</a>
      </div>
    </div>
    <div class="footer">
      This is an automated notification from your WebsiteTailors CMS lead management engine.
    </div>
  </div>
</body>
</html>
HTML;

    // If notifications are disabled (e.g. local dev without configured mail server), log safely
    if (!$mailEnabled) {
        error_log(sprintf(
            "[WebsiteTailors Mailer (Mock)] Lead notification prepared for %s | From: %s <%s> | Service: %s",
            $adminEmail, $leadName, $leadEmail, $leadService
        ));
        return true;
    }

    // Check driver: 'smtp' or 'mail'
    $driver = defined('MAIL_DRIVER') ? MAIL_DRIVER : 'mail';

    if ($driver === 'smtp' && defined('SMTP_HOST') && SMTP_HOST !== 'localhost' && !empty(SMTP_HOST)) {
        return send_via_smtp($adminEmail, $subject, $htmlBody, $textBody, $fromEmail, $fromName, $lead['email'] ?? null);
    }

    // Default to PHP native mail()
    return send_via_native_mail($adminEmail, $subject, $htmlBody, $textBody, $fromEmail, $fromName, $lead['email'] ?? null);
}

/**
 * Send email using PHP native mail() with proper MIME headers
 */
function send_via_native_mail(
    string $to,
    string $subject,
    string $htmlBody,
    string $textBody,
    string $fromEmail,
    string $fromName,
    ?string $replyTo = null
): bool {
    try {
        $boundary = "WebsiteTailors_boundary_" . md5(uniqid((string)time(), true));

        $headers = [];
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>";
        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = "Reply-To: {$replyTo}";
        }
        $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
        $headers[] = "X-Mailer: WebsiteTailors-PHP/" . PHP_VERSION;

        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $textBody . "\r\n\r\n";

        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $htmlBody . "\r\n\r\n";

        $message .= "--{$boundary}--";

        $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
        return @mail($to, $encodedSubject, $message, implode("\r\n", $headers));
    } catch (\Throwable $e) {
        error_log("send_via_native_mail notice: " . $e->getMessage());
        return false;
    }
}

/**
 * Standalone SMTP socket transport (No third-party framework required)
 */
function send_via_smtp(
    string $to,
    string $subject,
    string $htmlBody,
    string $textBody,
    string $fromEmail,
    string $fromName,
    ?string $replyTo = null
): bool {
    $host = defined('SMTP_HOST') ? SMTP_HOST : 'localhost';
    $port = defined('SMTP_PORT') ? SMTP_PORT : 587;
    $user = defined('SMTP_USER') ? SMTP_USER : '';
    $pass = defined('SMTP_PASS') ? SMTP_PASS : '';
    $secure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';

    $prefix = ($secure === 'ssl') ? 'ssl://' : '';
    $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);
    if (!$socket) {
        error_log("SMTP connection failed: {$errstr} ({$errno})");
        return send_via_native_mail($to, $subject, $htmlBody, $textBody, $fromEmail, $fromName, $replyTo);
    }

    $read = function() use ($socket): string {
        $data = '';
        while ($str = fgets($socket, 515)) {
            $data .= $str;
            if (substr($str, 3, 1) === ' ') break;
        }
        return $data;
    };

    $write = function(string $cmd) use ($socket): void {
        fputs($socket, $cmd . "\r\n");
    };

    $read(); // Initial greeting
    $write("EHLO " . gethostname());
    $read();

    if ($secure === 'tls') {
        $write("STARTTLS");
        $res = $read();
        if (str_starts_with($res, '220')) {
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $write("EHLO " . gethostname());
            $read();
        }
    }

    if (!empty($user) && !empty($pass)) {
        $write("AUTH LOGIN");
        $read();
        $write(base64_encode($user));
        $read();
        $write(base64_encode($pass));
        $authRes = $read();
        if (!str_starts_with($authRes, '235')) {
            error_log("SMTP authentication failed: {$authRes}");
            fclose($socket);
            return send_via_native_mail($to, $subject, $htmlBody, $textBody, $fromEmail, $fromName, $replyTo);
        }
    }

    $write("MAIL FROM:<{$fromEmail}>");
    $read();
    $write("RCPT TO:<{$to}>");
    $read();
    $write("DATA");
    $read();

    $boundary = "WebsiteTailors_smtp_" . md5(uniqid((string)time(), true));
    $headers = [
        "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>",
        "To: <{$to}>",
        "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"{$boundary}\""
    ];
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = "Reply-To: {$replyTo}";
    }

    $emailData = implode("\r\n", $headers) . "\r\n\r\n";
    $emailData .= "--{$boundary}\r\n";
    $emailData .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $emailData .= $textBody . "\r\n\r\n";
    $emailData .= "--{$boundary}\r\n";
    $emailData .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $emailData .= $htmlBody . "\r\n\r\n";
    $emailData .= "--{$boundary}--\r\n.";

    $write($emailData);
    $read();

    $write("QUIT");
    $read();
    fclose($socket);

    return true;
}
