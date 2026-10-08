<?php
/**
 * WebsiteTailors - Application Master Bootstrapper
 * 
 * Initializes core constants, environment config, database singleton,
 * session management, and all essential security and utility modules.
 */

declare(strict_types=1);

// Set initialization guard flag
if (!defined('WebsiteTailors_INIT')) {
    define('WebsiteTailors_INIT', true);
}

// Load Composer Autoloader if present
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// 1. Load Application Configuration
require_once __DIR__ . '/../config/config.php';

// 2. Load Database Manager
require_once __DIR__ . '/../config/database.php';

// 3. Load Security Module (CSRF, XSS, Rate-Limiting)
require_once __DIR__ . '/security.php';

// 4. Load Authentication Module (Sessions, Login, RBAC)
require_once __DIR__ . '/auth.php';

// 5. Load File Upload Module
require_once __DIR__ . '/upload.php';

// 6. Load General Helper Functions
require_once __DIR__ . '/functions.php';

// 7. Load Mailer Service
require_once __DIR__ . '/mailer.php';

// 8. Start Hardened Session
start_secure_session();

// 9. Dispatch Security Headers
send_security_headers();

// 10. Global Production Exception Handler
if (defined('APP_ENV') && APP_ENV === 'production') {
    set_exception_handler(function (\Throwable $e) {
        error_log(sprintf(
            "[%s] Uncaught Exception: %s in %s:%d\nStack Trace:\n%s",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));

        if (!headers_sent()) {
            http_response_code(500);
        }

        $errorPage = ROOT_PATH . '/500.php';
        if (file_exists($errorPage)) {
            require $errorPage;
        } else {
            echo "An internal server error occurred. Please try again later.";
        }
        exit;
    });
}
