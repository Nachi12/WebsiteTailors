<?php
/**
 * WebsiteTailors - Contact Form API Endpoint
 * 
 * Processes lead inquiries with server-side validation, CSRF verification,
 * anti-bot honeypot detection, sanitization, and PDO storage into `leads` table.
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
require_once dirname(__DIR__) . '/includes/init.php';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        json_response(['success' => false, 'error' => 'Method not allowed.'], 405);
    } else {
        redirect(BASE_URL . '/index.php#contact');
    }
}

// Process lead inquiry through centralized validation & storage helper
$result = process_lead_inquiry($_POST);

if ($isAjax) {
    json_response($result, $result['success'] ? 200 : 422);
} else {
    if ($result['success']) {
        set_flash('contact_success', $result['message']);
    } else {
        set_flash('contact_error', $result['error']);
    }
    redirect(BASE_URL . '/index.php#contact');
}
