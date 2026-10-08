<?php
/**
 * WebsiteTailors — Public Leads API Endpoint
 * 
 * Allows the external static frontend (e.g. Netlify deployment) to securely
 * submit enquiries to the separate WebsiteTailors PHP backend and MySQL CRM.
 * 
 * Features:
 * - CORS support for cross-domain Netlify deployments
 * - Preflight OPTIONS handling
 * - Anti-bot spam honeypot validation
 * - Strict server-side data sanitization and length limits
 * - Rate limiting / rapid duplicate submission prevention
 * - Direct insertion into `leads` table
 * - Notification dispatch via Mailer (if configured)
 */

declare(strict_types=1);

// Enable CORS for external frontend calls
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

define('WebsiteTailors_INIT', true);
require_once dirname(__DIR__, 2) . '/includes/init.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// Support both multipart/form-data and application/json payloads
$input = $_POST;
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (empty($input) && str_contains($contentType, 'application/json')) {
    $rawBody = file_get_contents('php://input');
    $parsed = json_decode($rawBody, true);
    if (is_array($parsed)) {
        $input = $parsed;
    }
}

// 1. Honeypot anti-spam verification: website_url field must be blank
if (!empty($input['website_url'])) {
    // Silently accept bots without saving to database
    echo json_encode([
        'success' => true,
        'message' => "Thanks! Your enquiry has been received. We'll get back to you shortly."
    ]);
    exit;
}

// 2. Sanitize and trim inputs
$name     = sanitize_text($input['name'] ?? '');
$email    = sanitize_email($input['email'] ?? '');
$phone    = sanitize_text($input['phone'] ?? '');
$company  = sanitize_text($input['company'] ?? '');
$service  = sanitize_text($input['service'] ?? 'General Inquiry');
$budget   = sanitize_text($input['budget'] ?? '');
$message  = sanitize_text($input['message'] ?? '');

// 3. Strict Server-Side Validation
if (empty($name) || mb_strlen($name) < 2) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please provide your full name (minimum 2 characters).']);
    exit;
}
if (mb_strlen($name) > 100) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Name cannot exceed 100 characters.']);
    exit;
}

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || str_contains($email, "\r") || str_contains($email, "\n")) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please provide a valid email address.']);
    exit;
}
if (mb_strlen($email) > 150) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Email address cannot exceed 150 characters.']);
    exit;
}

if (!empty($phone)) {
    if (mb_strlen($phone) > 50 || !preg_match('/^[0-9+\-\s().]{0,50}$/', $phone)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Please provide a valid phone number format.']);
        exit;
    }
}

if (!empty($company) && mb_strlen($company) > 100) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Company name cannot exceed 100 characters.']);
    exit;
}

if (mb_strlen($service) > 100) {
    $service = mb_substr($service, 0, 100);
}
if (!empty($budget) && mb_strlen($budget) > 50) {
    $budget = mb_substr($budget, 0, 50);
}

if (empty($message) || mb_strlen($message) < 5) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please describe your project or inquiry (minimum 5 characters).']);
    exit;
}
if (mb_strlen($message) > 5000) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Project details message cannot exceed 5,000 characters.']);
    exit;
}

// 4. Duplicate Protection & Rapid Flood Prevention
$nowTime = time();
$ipAddress = get_client_ip();
$userAgent = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
$db = Database::getInstance();

if ($db->isConnected()) {
    try {
        $cutoffRapid = date('Y-m-d H:i:s', $nowTime - 60);
        $duplicateCount = (int)$db->fetchColumn(
            "SELECT COUNT(*) FROM leads WHERE LOWER(email) = :email AND message = :msg AND created_at >= :cutoff",
            [':email' => strtolower($email), ':msg' => $message, ':cutoff' => $cutoffRapid]
        );
        if ($duplicateCount > 0) {
            echo json_encode([
                'success' => true,
                'message' => "Thanks! Your enquiry has been received. We'll get back to you shortly."
            ]);
            exit;
        }
    } catch (\Throwable $e) {
        error_log("Duplicate check error: " . $e->getMessage());
    }
}

// 5. Store inside `leads` table
$currentDateTime = date('Y-m-d H:i:s');
try {
    $leadId = $db->insert('leads', [
        'name'       => $name,
        'email'      => $email,
        'phone'      => !empty($phone) ? $phone : null,
        'company'    => !empty($company) ? $company : null,
        'service'    => !empty($service) ? $service : 'General Inquiry',
        'budget'     => !empty($budget) ? $budget : null,
        'message'    => $message,
        'status'     => 'New',
        'source'     => 'Website',
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
        'created_at' => $currentDateTime,
        'updated_at' => $currentDateTime
    ]);

    // Attempt to send email notifications if mailer is configured
    try {
        if (class_exists('Mailer')) {
            Mailer::sendNewLeadNotification([
                'id'         => $leadId,
                'name'       => $name,
                'email'      => $email,
                'phone'      => $phone,
                'company'    => $company,
                'service'    => $service,
                'budget'     => $budget,
                'message'    => $message,
                'source'     => 'Website',
                'created_at' => $currentDateTime
            ]);
        }
    } catch (\Throwable $mailErr) {
        error_log("Lead mail notification warning: " . $mailErr->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => "Thanks! Your enquiry has been received. We'll get back to you shortly.",
        'lead_id' => (int)$leadId
    ]);
    exit;

} catch (\Throwable $e) {
    error_log("Lead creation failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'An error occurred while saving your inquiry. Please try again or email us directly.'
    ]);
    exit;
}
