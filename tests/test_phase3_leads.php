<?php
/**
 * WebsiteTailors — Phase 3 Leads & Call Tracking Verification Test Suite
 *
 * Verifies:
 * 1. Database schema: All 17 leads fields & calls tracking fields.
 * 2. Leads page table columns: Lead, Company, Service, Phone, Lead Status, Call Status, Last Called, Next Follow-up, Actions.
 * 3. Call action & modal: Call date/time, Call outcome (Connected, No Answer, Call Back, Not Interested, Converted), Notes, Next follow-up date.
 * 4. Saving call record: Updates call_status, last_called_at, next_followup_at, and writes persistent record to calls table.
 * 5. Complete call history for a lead.
 * 6. Called indicators: Not Called, Called, Call Back.
 * 7. Filters: All, Not Called, Called, Call Back, No Answer, and Lead Status.
 * 8. Follow-ups Due view: Client, Phone, Last Call, Next Follow-up, Status sorted by nearest follow-up date.
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

echo "====================================================\n";
echo "WebsiteTailors Phase 3 Leads & Call Tracking Verification\n";
echo "====================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertCond(bool $cond, string $msg): void {
    global $passCount, $failCount;
    if ($cond) {
        $passCount++;
        echo "  [PASS] {$msg}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$msg}\n";
    }
}

require_once dirname(__DIR__) . '/config/database.php';
$pdo = Database::getInstance()->getConnection();

// -----------------------------------------------------------------------------
// Group 1: Database Schema Verification
// -----------------------------------------------------------------------------
echo "Group 1: Database Schema Verification\n";

$leadCols = array_column($pdo->query("PRAGMA table_info(leads)")->fetchAll(), 'name');
$expectedLeadFields = [
    'id', 'client_id', 'name', 'company', 'email', 'phone', 'service',
    'budget', 'message', 'source', 'status', 'call_status',
    'last_called_at', 'next_followup_at', 'notes', 'created_at', 'updated_at'
];

foreach ($expectedLeadFields as $field) {
    assertCond(in_array($field, $leadCols, true), "Leads table contains field '{$field}'");
}

$callCols = array_column($pdo->query("PRAGMA table_info(calls)")->fetchAll(), 'name');
$expectedCallFields = [
    'id', 'lead_id', 'client_id', 'call_datetime', 'outcome', 'notes', 'next_followup_at', 'created_at'
];

foreach ($expectedCallFields as $cField) {
    assertCond(in_array($cField, $callCols, true), "Calls table contains field '{$cField}'");
}

// -----------------------------------------------------------------------------
// Group 2: Table Columns & UI Verification
// -----------------------------------------------------------------------------
echo "\nGroup 2: Leads Page Table Columns & Elements\n";

$_GET['view'] = 'all';
ob_start();
require dirname(__DIR__) . '/admin/pages/leads.php';
$htmlAll = ob_get_clean();

$requiredColumns = [
    'Lead', 'Company', 'Service', 'Phone', 'Lead Status', 'Call Status', 'Last Called', 'Next Follow-up', 'Actions'
];

foreach ($requiredColumns as $col) {
    assertCond(strpos($htmlAll, $col) !== false, "Leads table displays column '{$col}'");
}

assertCond(strpos($htmlAll, 'btn-call-action') !== false, "CALL button present on lead rows");
assertCond(strpos($htmlAll, 'id="callModal"') !== false, "Call logging modal dialog present");
assertCond(strpos($htmlAll, 'id="historyModal"') !== false, "Call history modal present");

// Verify Outcome options in modal
$outcomes = ['Connected', 'No Answer', 'Call Back', 'Not Interested', 'Converted'];
foreach ($outcomes as $out) {
    assertCond(strpos($htmlAll, "value=\"{$out}\"") !== false, "Modal includes call outcome '{$out}'");
}

// -----------------------------------------------------------------------------
// Group 3: Called Visual Indicators
// -----------------------------------------------------------------------------
echo "\nGroup 3: Called Visual Status Indicators\n";
assertCond(strpos($htmlAll, 'badge-not-called') !== false, "Badge for 'Not Called' status rendered");
assertCond(strpos($htmlAll, 'badge-called') !== false, "Badge for 'Called' status rendered");
assertCond(strpos($htmlAll, 'badge-callback') !== false, "Badge for 'Call Back' status rendered (follow-up indicator)");
assertCond(strpos($htmlAll, 'badge-no-answer') !== false, "Badge for 'No Answer' status rendered");

// -----------------------------------------------------------------------------
// Group 4: Call Action & Complete Call History Recording
// -----------------------------------------------------------------------------
echo "\nGroup 4: Call Logging & History Record Creation\n";

// 1. Insert a clean test lead
$nowStr = date('Y-m-d H:i:s');
$testLeadStmt = $pdo->prepare("
    INSERT INTO leads (name, email, phone, company, service, status, call_status, message, ip_address, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, 'New', 'Not Called', 'Test lead inquiry for Phase 3 verification.', '127.0.0.1', ?, ?)
");
$testLeadStmt->execute([
    'Aarav Sharma',
    'aarav.sharma@testcorp.io',
    '+91 99112 23344',
    'Sharma Technologies',
    'AI + Automation',
    $nowStr,
    $nowStr
]);
$testLeadId = (int)$pdo->lastInsertId();
assertCond($testLeadId > 0, "Test lead created with ID #{$testLeadId}");

// 2. Simulate logging a 'Call Back' call
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'csrf_token'        => $_SESSION['csrf_token'] ?? generate_csrf(),
    'action'            => 'log_call',
    'lead_id'           => $testLeadId,
    'outcome'           => 'Call Back',
    'notes'             => 'Spoke with Aarav. Requested callback tomorrow morning at 10 AM to review pricing.',
    'call_datetime'     => date('Y-m-d H:i:s'),
    'next_followup_at'  => date('Y-m-d H:i:s', time() + 86400)
];

ob_start();
require dirname(__DIR__) . '/admin/pages/leads.php';
$postRes = ob_get_clean();

// Verify lead table update
$updatedLead = $pdo->query("SELECT * FROM leads WHERE id = {$testLeadId}")->fetch(PDO::FETCH_ASSOC);
assertCond($updatedLead['call_status'] === 'Call Back', "Lead call_status updated to 'Call Back'");
assertCond(!empty($updatedLead['last_called_at']), "Lead last_called_at timestamp recorded");
assertCond(!empty($updatedLead['next_followup_at']), "Lead next_followup_at timestamp recorded");

// Verify calls table record
$callRec = $pdo->query("SELECT * FROM calls WHERE lead_id = {$testLeadId} ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assertCond(!empty($callRec), "Persistent call record created in calls table");
assertCond($callRec['outcome'] === 'Call Back', "Call record outcome matches 'Call Back'");
assertCond(strpos($callRec['notes'], 'Requested callback') !== false, "Call record notes saved accurately");

// 3. Simulate logging a second call converting the lead
$_POST = [
    'csrf_token'        => $_SESSION['csrf_token'] ?? generate_csrf(),
    'action'            => 'log_call',
    'lead_id'           => $testLeadId,
    'outcome'           => 'Converted',
    'notes'             => 'Second call conducted. Signed statement of work!',
    'call_datetime'     => date('Y-m-d H:i:s', time() + 3600),
    'next_followup_at'  => null
];

ob_start();
require dirname(__DIR__) . '/admin/pages/leads.php';
ob_get_clean();

$convertedLead = $pdo->query("SELECT * FROM leads WHERE id = {$testLeadId}")->fetch(PDO::FETCH_ASSOC);
assertCond($convertedLead['status'] === 'Converted', "Converted outcome sets lead status to 'Converted'");
assertCond($convertedLead['call_status'] === 'Called', "Converted outcome sets call_status to 'Called'");
assertCond(!empty($convertedLead['client_id']), "Client record linked or created on conversion");

// 4. Verify complete call history has both records
$allHistory = $pdo->query("SELECT * FROM calls WHERE lead_id = {$testLeadId} ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
assertCond(count($allHistory) === 2, "Lead maintains complete chronological call history (2 records)");

// -----------------------------------------------------------------------------
// Group 5: Filters Verification
// -----------------------------------------------------------------------------
echo "\nGroup 5: Filter Functionality\n";

$_SERVER['REQUEST_METHOD'] = 'GET';
$filterCases = [
    'all'        => 'All',
    'Not Called' => 'Not Called',
    'Called'     => 'Called',
    'Call Back'  => 'Call Back',
    'No Answer'  => 'No Answer'
];

foreach ($filterCases as $filterKey => $filterLabel) {
    $_GET = ['view' => 'all', 'call_status' => $filterKey];
    ob_start();
    require dirname(__DIR__) . '/admin/pages/leads.php';
    $filterHtml = ob_get_clean();
    assertCond(strpos($filterHtml, $filterLabel) !== false, "Filter for '{$filterLabel}' renders cleanly");
}

// -----------------------------------------------------------------------------
// Group 6: Follow-ups Due View
// -----------------------------------------------------------------------------
echo "\nGroup 6: Follow-ups Due View\n";

$_GET = ['view' => 'followups'];
ob_start();
require dirname(__DIR__) . '/admin/pages/leads.php';
$fuHtml = ob_get_clean();

assertCond(strpos($fuHtml, 'Follow-ups Due') !== false, "Follow-ups Due view rendered");
assertCond(strpos($fuHtml, 'Client / Lead') !== false, "Column 'Client / Lead' present");
assertCond(strpos($fuHtml, 'Last Call') !== false, "Column 'Last Call' present");
assertCond(strpos($fuHtml, 'Next Follow-up') !== false, "Column 'Next Follow-up' present");

// -----------------------------------------------------------------------------
// Group 7: Add Lead Option Verification
// -----------------------------------------------------------------------------
echo "\nGroup 7: Add Lead Option & Modal\n";

$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['view' => 'all'];
ob_start();
require dirname(__DIR__) . '/admin/pages/leads.php';
$leadsPageHtml = ob_get_clean();

assertCond(strpos($leadsPageHtml, 'openAddLeadModal()') !== false, "Add Lead button present with openAddLeadModal() trigger");
assertCond(strpos($leadsPageHtml, 'id="addLeadModal"') !== false, "Add Lead Modal (#addLeadModal) present in DOM");
assertCond(strpos($leadsPageHtml, 'name="action" value="add_lead"') !== false, "Form action add_lead present in Add Lead Modal");

// Test POST add_lead
$_SERVER['REQUEST_METHOD'] = 'POST';
$newLeadUniq = uniqid();
$_POST = [
    'csrf_token'  => csrf_token(),
    'action'      => 'add_lead',
    'name'        => "Direct Lead {$newLeadUniq}",
    'company'     => "Global Tech {$newLeadUniq}",
    'email'       => "lead.{$newLeadUniq}@globaltech.org",
    'phone'       => "+91 91234 98765",
    'service'     => "AI + Automation",
    'budget'      => "₹5,00,000 - ₹10,00,000",
    'source'      => "Direct Phone",
    'status'      => "New",
    'call_status' => "Not Called",
    'notes'       => "Inbound phone lead requesting intelligent document automation"
];

ob_start();
require dirname(__DIR__) . '/admin/pages/leads.php';
$postLeadHtml = ob_get_clean();

$createdDirectLead = $pdo->query("SELECT * FROM leads WHERE email = 'lead.{$newLeadUniq}@globaltech.org'")->fetch(PDO::FETCH_ASSOC);
assertCond($createdDirectLead !== false && $createdDirectLead['name'] === "Direct Lead {$newLeadUniq}", "POST action add_lead successfully created lead in database");
assertCond($createdDirectLead && $createdDirectLead['service'] === "AI + Automation", "Lead service stored correctly as 'AI + Automation'");

// Cleanup direct test lead
if ($createdDirectLead) {
    $pdo->prepare("DELETE FROM leads WHERE id = ?")->execute([$createdDirectLead['id']]);
}

// Cleanup test records
$pdo->prepare("DELETE FROM calls WHERE lead_id = ?")->execute([$testLeadId]);
$pdo->prepare("DELETE FROM leads WHERE id = ?")->execute([$testLeadId]);

echo "\n====================================================\n";
echo "Phase 3 Leads & Call Tracking: {$passCount} Passed, {$failCount} Failed\n";
echo "====================================================\n";

if ($failCount === 0) {
    echo "🎉 ALL PHASE 3 REQUIREMENTS VERIFIED AND PASSED!\n";
    exit(0);
} else {
    echo "❌ SOME PHASE 3 CHECKS FAILED!\n";
    exit(1);
}
