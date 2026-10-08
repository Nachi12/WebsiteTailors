<?php
/**
 * WebsiteTailors Admin — Exotel Click-to-Call API Endpoint
 * 
 * Secure server-side handler for initiating two-leg telephony calls (Agent -> Client)
 * via Exotel. Strictly authenticated for admin session only.
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) {
    define('WebsiteTailors_INIT', true);
}
require_once dirname(__DIR__, 2) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Method not allowed.'], 405);
}

// 1. Parse JSON or POST payload
$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true) ?: $_POST;

$leadId = (int)($inputData['lead_id'] ?? 0);
if ($leadId <= 0) {
    json_response(['success' => false, 'error' => 'Invalid or missing lead ID.'], 422);
}

// 2. Fetch Lead from MySQL database securely
$db = Database::getInstance();
if (!$db->isConnected()) {
    json_response(['success' => false, 'error' => 'Database connection unavailable.'], 500);
}

try {
    $lead = $db->fetch("SELECT id, name, company, email, phone, status, call_status FROM leads WHERE id = :id", [':id' => $leadId]);
    if (!$lead) {
        json_response(['success' => false, 'error' => 'Lead not found.'], 444);
    }

    $rawPhone = trim((string)($lead['phone'] ?? ''));
    if (empty($rawPhone)) {
        json_response(['success' => false, 'error' => 'This lead does not have a valid phone number recorded.'], 422);
    }

    $clientPhone = normalize_phone_number($rawPhone);
    if (empty($clientPhone)) {
        json_response(['success' => false, 'error' => 'Invalid phone number format for telephony.'], 422);
    }

    // 3. Retrieve Agent Phone Number from configuration (Default: 9380552034)
    $agentPhone = defined('WebsiteTailors_AGENT_PHONE') ? WebsiteTailors_AGENT_PHONE : '9380552034';
    $callbackUrl = BASE_URL . '/api/webhooks/exotel-call-status.php';

    // 4. Initiate Two-Leg Call via Exotel API
    $result = exotel_click_to_call($agentPhone, $clientPhone, $callbackUrl);

    if (!$result['success']) {
        json_response(['success' => false, 'error' => $result['error'] ?? 'Unable to start the call. Please try again.'], 422);
    }

    // 5. Log Call Attempt in `crm_call_logs` and `calls` tables
    $nowFormatted = date('Y-m-d H:i:s');
    
    try {
        $db->insert('crm_call_logs', [
            'lead_id'      => $leadId,
            'agent_phone'  => $agentPhone,
            'client_phone' => $clientPhone,
            'provider'     => 'exotel',
            'call_sid'     => $result['call_sid'],
            'status'       => $result['status'] ?? 'initiated',
            'created_at'   => $nowFormatted,
            'updated_at'   => $nowFormatted
        ]);
    } catch (\Throwable $logEx) {
        error_log("crm_call_logs insert notice: " . $logEx->getMessage());
    }

    try {
        $db->insert('calls', [
            'lead_id'          => $leadId,
            'contact_name'     => $lead['name'],
            'company'          => $lead['company'] ?? null,
            'phone'            => $rawPhone,
            'type'             => 'click_to_call',
            'status'           => 'initiated',
            'scheduled_at'     => $nowFormatted,
            'call_datetime'    => $nowFormatted,
            'notes'            => 'Click-to-call initiated via Exotel. SID: ' . $result['call_sid'],
            'outcome'          => 'Initiated',
            'created_at'       => $nowFormatted
        ]);
    } catch (\Throwable $callEx) {
        error_log("calls insert notice: " . $callEx->getMessage());
    }

    // 6. Update Lead Record (`last_called_at` & `call_status`)
    try {
        $db->update('leads', [
            'last_called_at' => $nowFormatted,
            'call_status'    => 'Called',
            'updated_at'     => $nowFormatted
        ], 'id = :id', [':id' => $leadId]);
    } catch (\Throwable $leadEx) {
        error_log("leads update notice: " . $leadEx->getMessage());
    }

    json_response([
        'success'     => true,
        'message'     => 'Calling your phone...',
        'call_sid'    => $result['call_sid'],
        'status'      => 'initiated',
        'last_called' => date('M j, H:i')
    ], 200);

} catch (\Throwable $e) {
    error_log("call-lead.php exception: " . $e->getMessage());
    json_response(['success' => false, 'error' => 'Unable to start the call. Please try again.'], 500);
}
