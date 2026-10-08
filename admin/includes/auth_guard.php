<?php
/**
 * WebsiteTailors - Admin Panel Access Authorization Guard
 * 
 * Must be included at the top of every protected admin page.
 * Enforces authenticated session, active status check, and session timeout.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/init.php';

// Enforce login
require_admin();

// Populate global current admin object for UI
$currentAdmin = current_admin();
if (!$currentAdmin) {
    admin_logout();
    redirect(ADMIN_URL . '/login.php');
    exit;
}
