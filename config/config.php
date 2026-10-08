<?php
/**
 * WebsiteTailors - Core Application Configuration
 * 
 * Scalable configuration supporting local development and production shared hosting (e.g. Hostinger, cPanel).
 */

declare(strict_types=1);

// Prevent direct script execution if accessed outside the application
if (!defined('WebsiteTailors_INIT') && php_sapi_name() !== 'cli') {
    // If loaded directly, define initialization flag if it's the main entry point
    define('WebsiteTailors_INIT', true);
}

// -----------------------------------------------------------------------------
// ENVIRONMENT & ERROR HANDLING
// -----------------------------------------------------------------------------
// Options: 'development' | 'production'
// Set to 'production' on live shared hosting
define('APP_ENV', getenv('APP_ENV') ?: 'development');

if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('log_errors', '1');
    ini_set('error_log', dirname(__DIR__) . '/error.log');
}

// Set default timezone
date_default_timezone_set('UTC');

// -----------------------------------------------------------------------------
// PATHS & URLS
// -----------------------------------------------------------------------------
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('STORAGE_PATH', ROOT_PATH . '/uploads'); // Alias for upload storage

// Dynamic Base URL Detection
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

// Normalize base path for subfolder installations or root domains
$baseSubdir = preg_replace('#/(admin|api|pages).*$#', '', $scriptDir);
$baseSubdir = rtrim($baseSubdir, '/');

define('BASE_URL', rtrim($protocol . $host . $baseSubdir, '/'));
define('ADMIN_URL', BASE_URL . '/admin');
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');

// -----------------------------------------------------------------------------
// SECURITY & SESSION CONFIGURATION
// -----------------------------------------------------------------------------
define('SESSION_NAME', 'WebsiteTailors_sess');
define('SESSION_LIFETIME', 86400 * 7); // 7 days

// Brute-force Login Protection
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

// File Upload Constraints
define('MAX_UPLOAD_SIZE', 8 * 1024 * 1024); // 8 Megabytes
define('ALLOWED_UPLOAD_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp', 'svg']);
define('ALLOWED_UPLOAD_MIMES', [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/svg+xml'
]);

// -----------------------------------------------------------------------------
// BRANDING & DEFAULTS
// -----------------------------------------------------------------------------
define('APP_NAME', 'Website Tailors');
define('APP_TAGLINE', 'Engineering Digital Precision.');
define('APP_VERSION', '1.0.0');

// -----------------------------------------------------------------------------
// TELEPHONY / EXOTEL CLICK-TO-CALL CONFIGURATION
// -----------------------------------------------------------------------------
define('WebsiteTailors_AGENT_PHONE', getenv('WebsiteTailors_AGENT_PHONE') ?: '9380552034');
define('EXOTEL_ACCOUNT_SID', getenv('EXOTEL_ACCOUNT_SID') ?: 'WebsiteTailors1');
define('EXOTEL_API_KEY', getenv('EXOTEL_API_KEY') ?: '');
define('EXOTEL_API_TOKEN', getenv('EXOTEL_API_TOKEN') ?: '');
define('EXOTEL_SUBDOMAIN', getenv('EXOTEL_SUBDOMAIN') ?: 'api.exotel.com');
define('EXOTEL_VIRTUAL_NUMBER', getenv('EXOTEL_VIRTUAL_NUMBER') ?: '08045678900');
