<?php
/**
 * WebsiteTailors - Security Helper Functions
 * 
 * Provides CSRF protection, output escaping, input sanitization,
 * security headers, and rate-limiting routines.
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) {
    die('Direct access not permitted.');
}

/**
 * Output escaping helper to prevent XSS attacks
 *
 * @param mixed $value
 * @return string
 */
function e(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Sanitize general text input
 *
 * @param mixed $value
 * @return string
 */
function sanitize_text(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    // Remove null bytes and trim whitespace
    $clean = str_replace(chr(0), '', (string)$value);
    return trim($clean);
}

/**
 * Sanitize and validate email address
 *
 * @param mixed $email
 * @return string|null Valid email or null if invalid
 */
function sanitize_email(mixed $email): ?string
{
    if ($email === null) {
        return null;
    }
    $clean = filter_var(trim((string)$email), FILTER_SANITIZE_EMAIL);
    return filter_var($clean, FILTER_VALIDATE_EMAIL) ? $clean : null;
}

/**
 * Get or generate the current session's CSRF token
 *
 * @return string
 */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden HTML input field containing the CSRF token
 *
 * @return string
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify a submitted CSRF token
 *
 * Checks explicit token, $_POST['csrf_token'], or 'HTTP_X_CSRF_TOKEN' request header.
 *
 * @param string|null $token
 * @return bool
 */
function verify_csrf(?string $token = null): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($sessionToken) || !is_string($sessionToken)) {
        return false;
    }

    $candidate = $token;
    if ($candidate === null) {
        if (!empty($_POST['csrf_token']) && is_string($_POST['csrf_token'])) {
            $candidate = $_POST['csrf_token'];
        } elseif (!empty($_SERVER['HTTP_X_CSRF_TOKEN']) && is_string($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $candidate = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
    }

    if (empty($candidate) || !is_string($candidate)) {
        return false;
    }

    return hash_equals($sessionToken, $candidate);
}

/**
 * Enforce CSRF verification or terminate with 403 Forbidden
 */
function require_csrf(): void
{
    if (!verify_csrf()) {
        http_response_code(403);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'CSRF verification failed or token expired.']);
        } else {
            die('Invalid or missing security token (CSRF). Please refresh the page and try again.');
        }
        exit;
    }
}

/**
 * Send standard defensive HTTP security headers
 */
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}

/**
 * Check if an IP / identifier has exceeded rate limits for login
 *
 * @param string $ipAddress
 * @param string $username
 * @param int $maxAttempts
 * @param int $windowMinutes
 * @return bool True if rate limited (blocked), false otherwise
 */
function is_login_rate_limited(
    string $ipAddress,
    string $username,
    int $maxAttempts = MAX_LOGIN_ATTEMPTS,
    int $windowMinutes = LOGIN_LOCKOUT_MINUTES
): bool {
    try {
        $db = Database::getInstance();
        $cutoff = date('Y-m-d H:i:s', time() - ($windowMinutes * 60));

        // Check attempts by IP or by username
        $sql = "SELECT COUNT(*) as attempts FROM login_attempts 
                WHERE (ip_address = :ip OR username = :username) 
                AND is_successful = 0 
                AND attempted_at >= :cutoff";

        $count = (int)$db->fetchColumn($sql, [
            ':ip' => $ipAddress,
            ':username' => $username,
            ':cutoff' => $cutoff
        ]);

        return $count >= $maxAttempts;
    } catch (\Throwable $e) {
        error_log("Rate limit check error: " . $e->getMessage());
        return false; // Fail open if DB unreachable during check
    }
}

/**
 * Record a login attempt
 *
 * @param string $ipAddress
 * @param string $username
 * @param bool $isSuccessful
 */
function record_login_attempt(string $ipAddress, string $username, bool $isSuccessful): void
{
    try {
        $db = Database::getInstance();
        $db->insert('login_attempts', [
            'ip_address' => substr($ipAddress, 0, 45),
            'username' => substr($username, 0, 100),
            'attempted_at' => date('Y-m-d H:i:s'),
            'is_successful' => $isSuccessful ? 1 : 0
        ]);

        // If successful, purge previous failed attempts for this username/IP to reset counter
        if ($isSuccessful) {
            $db->delete(
                'login_attempts',
                '(ip_address = :ip OR username = :username) AND is_successful = 0',
                [':ip' => $ipAddress, ':username' => $username]
            );
        }
    } catch (\Throwable $e) {
        error_log("Record login attempt error: " . $e->getMessage());
    }
}

/**
 * Get client IP address accurately handling standard proxies
 *
 * @return string
 */
function get_client_ip(): string
{
    $headers = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header]) && is_string($_SERVER[$header])) {
            foreach (explode(',', $_SERVER[$header]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}
