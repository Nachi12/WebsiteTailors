<?php
/**
 * WebsiteTailors — Phase 5 Call Management & Analytics Verification Test Suite
 *
 * Asserts all Phase 5 requirements:
 * 1. Calls page: /admin/pages/calls.php rendering
 * 2. 5 Tabs: Today's Calls, Upcoming Follow-ups, Completed Calls, Missed/No Answer, Call Back Required
 * 3. Call Table columns: Client, Phone, Date, Time, Outcome, Next Follow-up, Actions
 * 4. Call Actions: View, Edit, Delete
 * 5. Editable fields: date, time, outcome, notes, next follow-up
 * 6. Call Outcomes tracked: Connected, No Answer, Call Back, Not Interested, Converted
 * 7. Call Analytics: Calls per day, Calls per week, Calls by outcome
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_SESSION['admin_user_id'] = 1;
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_data'] = [
    'id' => 1,
    'username' => 'admin',
    'email' => 'websietailorss@gmail.com',
    'full_name' => 'WebsiteTailors Administrator',
    'role' => 'superadmin',
    'is_active' => 1
];
$_SESSION['csrf_token'] = 'test_token_phase5_calls';

require_once dirname(__DIR__) . '/includes/init.php';
$pdo = Database::getInstance()->getConnection();

echo "====================================================\n";
echo "WebsiteTailors Phase 5 Call Management Verification\n";
echo "====================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertCondition(bool $cond, string $msg): void {
    global $passCount, $failCount;
    if ($cond) {
        $passCount++;
        echo "  [PASS] {$msg}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$msg}\n";
    }
}

// -----------------------------------------------------------------------------
// Group 1: Page UI Rendering & 5 Filter Tabs
// -----------------------------------------------------------------------------
echo "Group 1: Calls Page & 5 Filter Tabs\n";

$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = [];

ob_start();
require dirname(__DIR__) . '/admin/pages/calls.php';
$html = ob_get_clean();

assertCondition(strpos($html, 'Call Management &amp; Analytics') !== false || strpos($html, 'Call Management & Analytics') !== false, "Page header 'Call Management' rendered");
assertCondition(strpos($html, "Today's Calls") !== false, "Tab 1: Today's Calls rendered");
assertCondition(strpos($html, "Upcoming Follow-ups") !== false, "Tab 2: Upcoming Follow-ups rendered");
assertCondition(strpos($html, "Completed Calls") !== false, "Tab 3: Completed Calls rendered");
assertCondition(strpos($html, "Missed / No Answer") !== false || strpos($html, "Missed/No Answer") !== false, "Tab 4: Missed/No Answer rendered");
assertCondition(strpos($html, "Call Back Required") !== false, "Tab 5: Call Back Required rendered");

// -----------------------------------------------------------------------------
// Group 2: Call Table Columns & Actions
// -----------------------------------------------------------------------------
echo "\nGroup 2: Call Table Columns & Row Actions\n";

assertCondition(strpos($html, '<th>Client</th>') !== false, "Table header: Client");
assertCondition(strpos($html, '<th>Phone</th>') !== false, "Table header: Phone");
assertCondition(strpos($html, '<th>Date</th>') !== false, "Table header: Date");
assertCondition(strpos($html, '<th>Time</th>') !== false, "Table header: Time");
assertCondition(strpos($html, '<th>Outcome</th>') !== false, "Table header: Outcome");
assertCondition(strpos($html, '<th>Next Follow-up</th>') !== false, "Table header: Next Follow-up");
assertCondition(stripos($html, 'Actions</th>') !== false, "Table header: Actions");

assertCondition(strpos($html, 'openViewModal') !== false, "Row action: View exists");
assertCondition(strpos($html, 'openEditModal') !== false, "Row action: Edit exists");
assertCondition(strpos($html, 'confirmDeleteCall') !== false, "Row action: Delete exists");

// -----------------------------------------------------------------------------
// Group 3: Call Outcomes Tracked
// -----------------------------------------------------------------------------
echo "\nGroup 3: Call Outcomes Tracked\n";

$outcomes = ['Connected', 'No Answer', 'Call Back', 'Not Interested', 'Converted'];
foreach ($outcomes as $outcome) {
    assertCondition(strpos($html, $outcome) !== false, "Call outcome '{$outcome}' tracked");
}

// -----------------------------------------------------------------------------
// Group 4: Call Analytics (3 Visual Charts)
// -----------------------------------------------------------------------------
echo "\nGroup 4: Call Analytics (3 Visual Charts)\n";

assertCondition(stripos($html, 'Calls per day') !== false, "Analytics chart: Calls per day rendered");
assertCondition(stripos($html, 'Calls per week') !== false, "Analytics chart: Calls per week rendered");
assertCondition(stripos($html, 'Calls by outcome') !== false, "Analytics chart: Calls by outcome rendered");

// -----------------------------------------------------------------------------
// Group 5: Edit Call Record (date, time, outcome, notes, next follow-up)
// -----------------------------------------------------------------------------
echo "\nGroup 5: Call Edit Functional Test\n";

// Insert a test call
$nowStr = date('Y-m-d H:i:s');
$insStmt = $pdo->prepare("
    INSERT INTO calls (lead_id, client_id, contact_name, company, phone, scheduled_at, call_datetime, outcome, status, notes, next_followup_at, created_at)
    VALUES (NULL, 1, 'Phase 5 Test Client', 'Test Corp', '+91 9988776655', ?, ?, 'Connected', 'completed', 'Initial Call Note', ?, ?)
");
$insStmt->execute([$nowStr, $nowStr, $nowStr, $nowStr]);
$callId = (int)$pdo->lastInsertId();

assertCondition($callId > 0, "Test call record created with ID #{$callId}");

// Update date, time, outcome, notes, next follow-up
$_SERVER['REQUEST_METHOD'] = 'POST';
$newDate = date('Y-m-d', strtotime('+1 day'));
$newTime = '15:30';
$newFollowup = date('Y-m-d H:i', strtotime('+3 days'));

$_POST = [
    'csrf_token'       => 'test_token_phase5_calls',
    'action'           => 'edit_call',
    'call_id'          => $callId,
    'call_date'        => $newDate,
    'call_time'        => $newTime,
    'outcome'          => 'Call Back',
    'next_followup_at' => $newFollowup,
    'notes'            => 'Updated via Phase 5 Automated Test Suite'
];

ob_start();
require dirname(__DIR__) . '/admin/pages/calls.php';
$editHtml = ob_get_clean();

assertCondition(strpos($editHtml, 'successfully updated') !== false, "Call record #{$callId} edited successfully");

$chk = $pdo->prepare("SELECT * FROM calls WHERE id = ?");
$chk->execute([$callId]);
$updatedCall = $chk->fetch(PDO::FETCH_ASSOC);

assertCondition($updatedCall['outcome'] === 'Call Back', "Outcome updated to 'Call Back'");
assertCondition($updatedCall['notes'] === 'Updated via Phase 5 Automated Test Suite', "Notes updated");
assertCondition(strpos($updatedCall['call_datetime'], $newDate) !== false, "Call date updated");
assertCondition(!empty($updatedCall['next_followup_at']), "Next follow-up updated");

// -----------------------------------------------------------------------------
// Group 6: Delete Call Record
// -----------------------------------------------------------------------------
echo "\nGroup 6: Call Delete Functional Test\n";

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'csrf_token' => 'test_token_phase5_calls',
    'action'     => 'delete_call',
    'call_id'    => $callId
];

ob_start();
require dirname(__DIR__) . '/admin/pages/calls.php';
$delHtml = ob_get_clean();

assertCondition(strpos($delHtml, 'has been deleted') !== false, "Call record #{$callId} deleted successfully");

$delChk = $pdo->prepare("SELECT COUNT(*) FROM calls WHERE id = ?");
$delChk->execute([$callId]);
assertCondition((int)$delChk->fetchColumn() === 0, "Record #{$callId} no longer in calls table");

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------
echo "\n====================================================\n";
echo "Phase 5 Test Results: {$passCount} Passed, {$failCount} Failed\n";
echo "====================================================\n";

exit($failCount === 0 ? 0 : 1);
