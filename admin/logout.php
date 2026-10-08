<?php
/**
 * WebsiteTailors - Admin Panel Secure Logout
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) { define('WebsiteTailors_INIT', true); }
require_once dirname(__DIR__) . '/includes/init.php';

admin_logout();

redirect(ADMIN_URL . '/login.php');
