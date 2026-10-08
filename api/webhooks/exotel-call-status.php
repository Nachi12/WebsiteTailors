<?php
/**
 * WebsiteTailors Telephony — Exotel Call Status Webhook Endpoint
 * 
 * Asynchronously processes status callback events from Exotel (e.g. ringing, connected, completed, failed)
 * and updates CRM call logs and lead status.
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) {
    define('WebsiteTailors_INIT', true);
}
require_once dirname(__DIR__, 2) . '/includes/init.php';

header('Content-Type: application/json; charset=utf-8');

$callSid      = trim((string)($_POST['CallSid'] ?? $_GET['CallSid'] ?? ''));
$status       = strtolower(trim((string)($_POST['Status'] ?? $_GET['Status'] ?? '')));
$duration     = (int)($_POST['Duration'] ?? $_GET['Duration'] ?? 0);
$recordingUrl = trim((string)($_POST['RecordingUrl'] ?? $_GET['RecordingUrl'] ?? ''));

if (empty($callSid)) {
    json_response(['success' => false, 'error' => 'Missing CallSid'], 400);
}

$db = Database::getInstance();
if (!$db->isConnected()) {
    json_response(['success' => false, 'error' => 'Database connection unavailable'], 500);
}

try {
    $nowFormatted = date('Y-m-d H:i:s');
    
    // Update crm_call_logs
    $db->update('crm_call_logs', [
        'status'        => $status,
        'duration'      => $duration,
        'recording_url' => !empty($recordingUrl) ? $recordingUrl : null,
        'updated_at'    => $nowFormatted
    ], 'call_sid = :sid', [':sid' => $callSid]);

    // Find linked log to update calls table
    $log = $db->fetch("SELECT lead_id FROM crm_call_logs WHERE call_sid = :sid", [':sid' => $callSid]);
    if (!empty($log['lead_id'])) {
        $outcome = match ($status) {
            'completed' => 'Connected',
            'busy'      => 'No Answer',
            'no-answer' => 'No Answer',
            'failed'    => 'Failed',
            default     => ucfirst($status)
        };

        $db->update('calls', [
            'status'           => $status,
            'duration_minutes' => (int)ceil($duration / 60),
            'outcome'          => $outcome
        ], 'lead_id = :lead_id AND notes LIKE :sid_like', [
            ':lead_id'  => $log['lead_id'],
            ':sid_like' => '%' . $callSid . '%'
        ]);

        if ($status === 'completed') {
            $db->update('leads', [
                'call_status' => 'Called',
                'updated_at'  => $nowFormatted
            ], 'id = :id', [':id' => $log['lead_id']]);
        }
    }

    json_response(['success' => true, 'message' => 'Status recorded'], 200);

} catch (\Throwable $e) {
    error_log("exotel-call-status webhook error: " . $e->getMessage());
    json_response(['success' => false, 'error' => 'Server processing error'], 500);
}
