<?php
/**
 * WebsiteTailors - Authentication & Session Security Handler
 * 
 * Provides session lifecycle management, credential validation,
 * admin authorization, brute-force mitigation, and secure logout.
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) {
    die('Direct access not permitted.');
}

/**
 * Configure and start session with hardened settings
 */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

    // Hardened session configuration
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_name(SESSION_NAME);
    session_start();
}

/**
 * Check if an admin is currently authenticated
 *
 * @return bool
 */
function is_admin_logged_in(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    return !empty($_SESSION['admin_user_id']) 
        && !empty($_SESSION['admin_logged_in']) 
        && $_SESSION['admin_logged_in'] === true;
}

/**
 * Get the currently logged-in admin user record
 *
 * @return array<string, mixed>|null
 */
function current_admin(): ?array
{
    if (!is_admin_logged_in()) {
        return null;
    }

    // Cached in session for performance, or fetched fresh from DB
    if (!empty($_SESSION['admin_data']) && is_array($_SESSION['admin_data'])) {
        return $_SESSION['admin_data'];
    }

    try {
        $db = Database::getInstance();
        $admin = $db->fetch(
            "SELECT id, username, email, full_name, role, is_active, last_login_at 
             FROM admins 
             WHERE id = :id AND is_active = 1",
            [':id' => $_SESSION['admin_user_id']]
        );

        if ($admin) {
            $_SESSION['admin_data'] = $admin;
            return $admin;
        }
    } catch (\Throwable $e) {
        error_log("current_admin error: " . $e->getMessage());
    }

    // Inactive or deleted user
    admin_logout();
    return null;
}

/**
 * Require admin authentication; redirect to login if unauthenticated
 *
 * @param string|null $requiredRole 'superadmin' | null
 */
function require_admin(?string $requiredRole = null): void
{
    if (!is_admin_logged_in()) {
        $redirectUrl = urlencode($_SERVER['REQUEST_URI'] ?? '/admin/index.php');
        redirect(ADMIN_URL . '/login.php?redirect=' . $redirectUrl);
        exit;
    }

    $admin = current_admin();
    if (!$admin) {
        redirect(ADMIN_URL . '/login.php');
        exit;
    }

    if ($requiredRole !== null && ($admin['role'] ?? '') !== $requiredRole) {
        http_response_code(403);
        die("Access Denied: You do not have permission to view this resource.");
    }
}

/**
 * Attempt to authenticate an admin with brute-force prevention
 *
 * @param string $usernameOrEmail
 * @param string $password
 * @return array{success: bool, error: ?string}
 */
function attempt_admin_login(string $usernameOrEmail, string $password): array
{
    $usernameOrEmail = trim($usernameOrEmail);
    $ip = get_client_ip();

    if (empty($usernameOrEmail) || empty($password)) {
        return ['success' => false, 'error' => 'Please provide both username/email and password.'];
    }

    // Rate Limiting check
    if (is_login_rate_limited($ip, $usernameOrEmail)) {
        return [
            'success' => false, 
            'error' => sprintf('Too many failed login attempts. Please try again in %d minutes.', LOGIN_LOCKOUT_MINUTES)
        ];
    }

    try {
        $db = Database::getInstance();

        if (!$db->isConnected()) {
            return ['success' => false, 'error' => 'Database connection unavailable. Please try again later.'];
        }

        $admin = $db->fetch(
            "SELECT id, username, email, password_hash, full_name, role, is_active 
             FROM admins 
             WHERE (username = :u1 OR email = :u2)",
            [':u1' => $usernameOrEmail, ':u2' => $usernameOrEmail]
        );

        // Timing-attack safe dummy check if user not found
        $dummyHash = '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ012345';
        $hashToCheck = $admin ? $admin['password_hash'] : $dummyHash;
        $passwordMatches = password_verify($password, $hashToCheck);

        if (!$admin || !$passwordMatches) {
            record_login_attempt($ip, $usernameOrEmail, false);
            return ['success' => false, 'error' => 'Invalid email or password.'];
        }

        if ((int)$admin['is_active'] !== 1) {
            return ['success' => false, 'error' => 'This account has been deactivated. Please contact an administrator.'];
        }

        // Authentication Succeeded
        record_login_attempt($ip, $usernameOrEmail, true);

        // Regenerate session ID to prevent session fixation
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }

        // Set session state
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user_id']   = (int)$admin['id'];
        $_SESSION['admin_username']  = $admin['username'];
        $_SESSION['admin_role']      = $admin['role'];
        $_SESSION['admin_data']      = [
            'id'        => (int)$admin['id'],
            'username'  => $admin['username'],
            'email'     => $admin['email'],
            'full_name' => $admin['full_name'],
            'role'      => $admin['role']
        ];

        // Update last login timestamp
        $db->update('admins', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $admin['id']]);

        // Rehash password if algorithm cost updated
        if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $db->update('admins', ['password_hash' => $newHash], 'id = :id', [':id' => $admin['id']]);
        }

        return ['success' => true, 'error' => null];

    } catch (\Throwable $e) {
        error_log("Login error: " . $e->getMessage());
        return ['success' => false, 'error' => 'An internal authentication error occurred. Please try again.'];
    }
}

/**
 * Destroy admin session and clear cookies securely
 */
function admin_logout(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];

        if (ini_get("session.use_cookies") && !headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            session_destroy();
        }
    }
}

/**
 * Programmatically create an admin account
 *
 * @param string $username
 * @param string $email
 * @param string $password
 * @param string $fullName
 * @param string $role
 * @return array{success: bool, error: ?string, id: ?int}
 */
function create_admin_user(
    string $username,
    string $email,
    string $password,
    string $fullName,
    string $role = 'admin'
): array {
    $username = trim($username);
    $email = trim(strtolower($email));
    $fullName = trim($fullName);

    if (strlen($username) < 3 || strlen($username) > 50) {
        return ['success' => false, 'error' => 'Username must be between 3 and 50 characters.', 'id' => null];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid email address provided.', 'id' => null];
    }

    if (strlen($password) < 8) {
        return ['success' => false, 'error' => 'Password must be at least 8 characters long.', 'id' => null];
    }

    if (!in_array($role, ['superadmin', 'admin'], true)) {
        $role = 'admin';
    }

    try {
        $db = Database::getInstance();

        // Check if username or email already exists
        $existing = $db->fetch(
            "SELECT id FROM admins WHERE username = :u OR email = :e LIMIT 1",
            [':u' => $username, ':e' => $email]
        );

        if ($existing) {
            return ['success' => false, 'error' => 'An admin with this username or email already exists.', 'id' => null];
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $id = $db->insert('admins', [
            'username'      => $username,
            'email'         => $email,
            'password_hash' => $passwordHash,
            'full_name'     => $fullName,
            'role'          => $role,
            'is_active'     => 1,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s')
        ]);

        return ['success' => true, 'error' => null, 'id' => $id];

    } catch (\Throwable $e) {
        error_log("create_admin_user error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error creating admin user.', 'id' => null];
    }
}
