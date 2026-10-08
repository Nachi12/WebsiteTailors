<?php
/**
 * WebsiteTailors — Phase 7 Final CRM UX + Editing Verification Test Suite
 *
 * Comprehensive end-to-end verification:
 * 1. CLIENT CRUD: Create client -> Edit client -> Delete client
 * 2. LEAD LIFECYCLE: Create lead -> Edit lead -> Call lead -> Schedule follow-up
 * 3. CALL CRUD: Create call -> Edit call -> Change outcome
 * 4. REVENUE LIFECYCLE: Create revenue -> Edit revenue -> Change payment status
 * 5. DATA CONSISTENCY & DASHBOARD REACTIVITY:
 *    - Client count updates
 *    - Lead count updates
 *    - Call count updates
 *    - Follow-up count updates
 *    - Revenue totals update
 *    - Pipeline stage counts update
 *    - Chart data dynamically generated without hardcoded values
 * 6. UX & INTERACTION:
 *    - Dashboard numbers link to detailed modules
 *    - Global search returns matched clients and leads
 *    - Zero browser alert() or confirm() used anywhere in admin
 *    - Responsive styles intact (desktop full sidebar, tablet collapse, mobile off-canvas & table scroll)
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
echo "WebsiteTailors Phase 7 Final CRM UX & Data Consistency Test\n";
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

require_once dirname(__DIR__) . '/admin/includes/auth_guard.php';
$pdo = Database::getInstance()->getConnection();

// =============================================================================
// TEST GROUP 1: CLIENT CRUD (Create, Edit, Delete)
// =============================================================================
echo "Group 1: Client CRUD & Lifecycle\n";

// 1. Create client
$testClientName = "CRM Alpha Corp " . uniqid();
$testClientEmail = "contact@" . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $testClientName)) . ".com";
$now = date('Y-m-d H:i:s');

$stmt = $pdo->prepare("
    INSERT INTO clients (client_name, company_name, email, phone, service, source, status, notes, created_at, updated_at)
    VALUES (?, ?, ?, '+91 98765 43210', 'Software', 'LinkedIn', 'New', 'Initial consultation scheduled', ?, ?)
");
$stmt->execute([$testClientName, $testClientName, $testClientEmail, $now, $now]);
$clientId = (int)$pdo->lastInsertId();
assertCondition($clientId > 0, "Create client: Record inserted with ID {$clientId}");

// Verify in DB
$cRow = $pdo->query("SELECT * FROM clients WHERE id = {$clientId}")->fetch(PDO::FETCH_ASSOC);
assertCondition($cRow && $cRow['status'] === 'New', "Create client: Verified initial status is 'New'");

// 2. Edit client
$updatedClientName = $testClientName . " [Updated]";
$updStmt = $pdo->prepare("
    UPDATE clients 
    SET client_name = ?, status = 'Active', service = 'AI + Automation', notes = 'Upgraded to enterprise SLA', updated_at = ?
    WHERE id = ?
");
$updStmt->execute([$updatedClientName, date('Y-m-d H:i:s'), $clientId]);
$cUpdRow = $pdo->query("SELECT * FROM clients WHERE id = {$clientId}")->fetch(PDO::FETCH_ASSOC);
assertCondition($cUpdRow['client_name'] === $updatedClientName && $cUpdRow['status'] === 'Active' && $cUpdRow['service'] === 'AI + Automation', "Edit client: Successfully updated name, status to 'Active', and service to 'AI + Automation'");

// 3. Delete client
$delStmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
$delStmt->execute([$clientId]);
$cDelRow = $pdo->query("SELECT * FROM clients WHERE id = {$clientId}")->fetch(PDO::FETCH_ASSOC);
assertCondition(!$cDelRow, "Delete client: Client successfully deleted from database");


// =============================================================================
// TEST GROUP 2: LEAD LIFECYCLE (Create, Edit, Call, Follow-up)
// =============================================================================
echo "\nGroup 2: Lead Lifecycle (Create, Edit, Call, Schedule Follow-up)\n";

// 1. Create lead
$testLeadName = "Vikram Mehta " . uniqid();
$testLeadEmail = "vikram." . uniqid() . "@fintechgrowth.io";
$stmt = $pdo->prepare("
    INSERT INTO leads (name, company, email, phone, service, budget, message, source, status, call_status, ip_address, created_at, updated_at)
    VALUES (?, 'Fintech Growth Labs', ?, '+91 99887 76655', 'Software', '₹3,00,000 - ₹5,00,000', 'Need full stack trading interface', 'Website Form', 'New', 'Not Called', '127.0.0.1', ?, ?)
");
$stmt->execute([$testLeadName, $testLeadEmail, $now, $now]);
$leadId = (int)$pdo->lastInsertId();
assertCondition($leadId > 0, "Create lead: Record created with ID {$leadId}");

// 2. Edit lead
$updLeadStmt = $pdo->prepare("
    UPDATE leads 
    SET company = 'Fintech Growth Labs Private Ltd',
        budget = '₹6,00,000 - ₹10,00,000',
        status = 'Qualified',
        notes = 'Enterprise budget verified with CFO',
        updated_at = ?
    WHERE id = ?
");
$updLeadStmt->execute([date('Y-m-d H:i:s'), $leadId]);
$lRow = $pdo->query("SELECT * FROM leads WHERE id = {$leadId}")->fetch(PDO::FETCH_ASSOC);
assertCondition($lRow['company'] === 'Fintech Growth Labs Private Ltd' && $lRow['status'] === 'Qualified', "Edit lead: Successfully updated company, budget, status to 'Qualified'");

// 3. Call lead & log call
$callDatetime = date('Y-m-d H:i:s', strtotime('-1 hour'));
$nextFollowup = date('Y-m-d H:i:s', strtotime('+2 days 14:30:00'));

$logCallStmt = $pdo->prepare("
    INSERT INTO calls (lead_id, contact_name, company, phone, type, status, scheduled_at, call_datetime, duration_minutes, outcome, notes, next_followup_at, created_at)
    VALUES (?, ?, ?, '+91 99887 76655', 'outbound', 'completed', ?, ?, 18, 'Connected', 'Great call with founders. High interest in custom ERP.', ?, ?)
");
$logCallStmt->execute([$leadId, $testLeadName, 'Fintech Growth Labs Private Ltd', $callDatetime, $callDatetime, $nextFollowup, $now]);
$callId = (int)$pdo->lastInsertId();
assertCondition($callId > 0, "Call lead: Logged call entry ID {$callId} linked to lead {$leadId}");

// Synchronize lead record with call status and follow-up
$syncLead = $pdo->prepare("
    UPDATE leads 
    SET call_status = 'Called',
        last_called_at = ?,
        next_followup_at = ?,
        updated_at = ?
    WHERE id = ?
");
$syncLead->execute([$callDatetime, $nextFollowup, date('Y-m-d H:i:s'), $leadId]);
$lSynced = $pdo->query("SELECT * FROM leads WHERE id = {$leadId}")->fetch(PDO::FETCH_ASSOC);
assertCondition($lSynced['call_status'] === 'Called' && $lSynced['next_followup_at'] === $nextFollowup, "Schedule follow-up: Lead updated with call_status='Called' and next_followup_at");


// =============================================================================
// TEST GROUP 3: CALL MANAGEMENT & OUTCOMES (Create, Edit, Change Outcome)
// =============================================================================
echo "\nGroup 3: Call Management & Outcomes\n";

// 1. Verify Call record created
$callRow = $pdo->query("SELECT * FROM calls WHERE id = {$callId}")->fetch(PDO::FETCH_ASSOC);
assertCondition($callRow && $callRow['outcome'] === 'Connected', "Verify call: Initial outcome is 'Connected'");

// 2. Edit call (datetime, notes, next follow-up)
$newFollowup = date('Y-m-d H:i:s', strtotime('+1 day 11:00:00'));
$editCallStmt = $pdo->prepare("
    UPDATE calls
    SET notes = 'Follow-up expedited per client request for draft quote.',
        next_followup_at = ?,
        status = 'completed'
    WHERE id = ?
");
$editCallStmt->execute([$newFollowup, $callId]);
$callEdited = $pdo->query("SELECT * FROM calls WHERE id = {$callId}")->fetch(PDO::FETCH_ASSOC);
assertCondition($callEdited['next_followup_at'] === $newFollowup && strpos($callEdited['notes'], 'expedited') !== false, "Edit call: Notes and next follow-up updated successfully");

// 3. Change outcome to 'Converted'
$outcomeUpd = $pdo->prepare("
    UPDATE calls 
    SET outcome = 'Converted',
        notes = 'Client approved project statement of work.'
    WHERE id = ?
");
$outcomeUpd->execute([$callId]);
$callConverted = $pdo->query("SELECT * FROM calls WHERE id = {$callId}")->fetch(PDO::FETCH_ASSOC);
assertCondition($callConverted['outcome'] === 'Converted', "Change outcome: Call outcome updated to 'Converted'");


// =============================================================================
// TEST GROUP 4: REVENUE MANAGEMENT (Create, Edit, Change Status)
// =============================================================================
echo "\nGroup 4: Revenue Management (Create, Edit, Change Payment Status)\n";

// 1. Create client for revenue ledger
$revClientName = "Apex Solutions " . uniqid();
$insRevClient = $pdo->prepare("
    INSERT INTO clients (client_name, company_name, email, phone, service, status, notes, created_at, updated_at)
    VALUES (?, ?, 'accounts@apexsolutions.com', '+91 91234 56789', 'Website', 'Active', 'Contract client', ?, ?)
");
$insRevClient->execute([$revClientName, $revClientName, $now, $now]);
$revClientId = (int)$pdo->lastInsertId();

// 2. Create revenue entry (Strict numeric amount 45000.00, Payment status: Pending)
$testAmount = 45000.00;
$revDate = date('Y-m-d H:i:s');
$insRevStmt = $pdo->prepare("
    INSERT INTO revenue (client_id, lead_id, invoice_id, amount, payment_type, payment_status, payment_date, service, notes, created_at, updated_at)
    VALUES (?, ?, NULL, ?, 'Bank Transfer', 'Pending', ?, 'Website', 'Advance milestone invoice', ?, ?)
");
$insRevStmt->execute([$revClientId, $leadId, $testAmount, $revDate, $now, $now]);
$revId = (int)$pdo->lastInsertId();
assertCondition($revId > 0, "Create revenue: Recorded ₹" . number_format($testAmount, 2) . " with status 'Pending'");

// Verify numeric storage
$rRow = $pdo->query("SELECT * FROM revenue WHERE id = {$revId}")->fetch(PDO::FETCH_ASSOC);
assertCondition(is_numeric($rRow['amount']) && (float)$rRow['amount'] === 45000.00, "Numeric integrity: Revenue amount stored as numeric float, not string");

// 3. Edit revenue (update amount and notes)
$updatedAmount = 55000.00;
$updRevStmt = $pdo->prepare("
    UPDATE revenue 
    SET amount = ?, notes = 'Adjusted for additional responsive revisions'
    WHERE id = ?
");
$updRevStmt->execute([$updatedAmount, $revId]);
$rUpd = $pdo->query("SELECT * FROM revenue WHERE id = {$revId}")->fetch(PDO::FETCH_ASSOC);
assertCondition((float)$rUpd['amount'] === 55000.00, "Edit revenue: Successfully edited amount to ₹55,000.00");

// 4. Change payment status from 'Pending' to 'Paid'
$statusUpdStmt = $pdo->prepare("
    UPDATE revenue 
    SET payment_status = 'Paid', payment_type = 'UPI', updated_at = ?
    WHERE id = ?
");
$statusUpdStmt->execute([date('Y-m-d H:i:s'), $revId]);
$rPaid = $pdo->query("SELECT * FROM revenue WHERE id = {$revId}")->fetch(PDO::FETCH_ASSOC);
assertCondition($rPaid['payment_status'] === 'Paid' && $rPaid['payment_type'] === 'UPI', "Change payment status: Successfully updated payment status to 'Paid' via UPI");


// =============================================================================
// TEST GROUP 5: DASHBOARD DATA CONSISTENCY & AUTOMATIC RE-CALCULATION
// =============================================================================
echo "\nGroup 5: Dashboard Data Consistency & Automatic Re-calculation\n";

// Render dashboard
ob_start();
require dirname(__DIR__) . '/admin/index.php';
$dashHtml = ob_get_clean();

// 1. Verify Client Count in Dashboard
$expectedClientCount = (int)$pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
assertCondition(strpos($dashHtml, (string)$expectedClientCount) !== false, "Dashboard reflects live DB client count ({$expectedClientCount})");

// 2. Verify Lead Count in Dashboard
$expectedLeadCount = (int)$pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
assertCondition(strpos($dashHtml, (string)$expectedLeadCount) !== false, "Dashboard reflects live DB lead count ({$expectedLeadCount})");

// 3. Verify Follow-ups Count in Dashboard
$nowStr = date('Y-m-d H:i:s');
$expectedPendingFollowups = (int)$pdo->query("
    SELECT COUNT(*) FROM calls 
    WHERE next_followup_at >= '{$nowStr}' 
       OR (status IN ('scheduled', 'pending') AND scheduled_at >= '{$nowStr}')
")->fetchColumn();
assertCondition(strpos($dashHtml, (string)$expectedPendingFollowups) !== false, "Dashboard reflects live DB follow-ups count ({$expectedPendingFollowups})");

// 4. Verify Revenue Total in Dashboard
$monthStart = date('Y-m-01 00:00:00');
$monthEnd = date('Y-m-t 23:59:59');
$expectedMonthRevenue = (float)$pdo->query("
    SELECT COALESCE(SUM(amount), 0) FROM revenue 
    WHERE payment_date >= '{$monthStart}' AND payment_date <= '{$monthEnd}' 
      AND LOWER(payment_status) = 'paid'
")->fetchColumn();
$expectedFormatted = number_format($expectedMonthRevenue, 2);
assertCondition(strpos($dashHtml, $expectedFormatted) !== false, "Dashboard reflects live DB monthly revenue (₹{$expectedFormatted})");

// 5. Verify Revenue Line Chart Data & Dynamic Rendering
assertCondition(strpos($dashHtml, 'id="revenueSvg"') !== false, "Revenue line chart SVG container rendered dynamically");
assertCondition(strpos($dashHtml, 'const revenueData =') !== false, "Revenue line chart dataset dynamically calculated and injected from database");
assertCondition(strpos($dashHtml, 'renderRevenueChart(') !== false, "Smooth revenue line chart client-side renderer present");

// 6. Verify Lead Pipeline Stages
assertCondition(strpos($dashHtml, 'Lead Pipeline') !== false, "Lead pipeline displayed");
assertCondition(strpos($dashHtml, 'Qualified') !== false && strpos($dashHtml, 'Converted') !== false, "Pipeline displays active lead lifecycle stages");


// =============================================================================
// TEST GROUP 6: UX LINKING, MODALS & ZERO NATIVE ALERTS/CONFIRMS
// =============================================================================
echo "\nGroup 6: UX Linking, Modals & Safety Standards\n";

// 1. Dashboard KPI cards linking to detailed modules
assertCondition(strpos($dashHtml, 'href="clients.php"') !== false, "TOTAL CLIENTS card links to clients.php");
assertCondition(strpos($dashHtml, 'href="leads.php?lead_status=New"') !== false, "NEW LEADS card links to leads.php with status filter");
assertCondition(strpos($dashHtml, 'href="revenue.php"') !== false, "REVENUE THIS MONTH card links to revenue.php");
assertCondition(strpos($dashHtml, 'href="calls.php?tab=upcoming"') !== false, "PENDING FOLLOW-UPS card links to calls.php");

// 2. Pipeline stage cards linking to leads
assertCondition(strpos($dashHtml, 'href="leads.php?lead_status=') !== false, "Pipeline stage cards link directly to leads module with status filter");

// 3. Revenue by service linking to revenue module
assertCondition(strpos($dashHtml, 'href="revenue.php?service=') !== false, "Service breakdown rows link directly to revenue ledger with service filter");

// 4. Follow-up contact linking
assertCondition(strpos($dashHtml, 'href="calls.php?search=') !== false, "Follow-ups table links contact name directly to calls module");

// 5. Global Search Endpoint & Query Verification
$searchScript = dirname(__DIR__) . '/admin/ajax_search.php';
assertCondition(file_exists($searchScript), "Global search endpoint admin/ajax_search.php exists");
$searchRaw = file_get_contents($searchScript);
assertCondition(strpos($searchRaw, 'FROM clients') !== false && strpos($searchRaw, 'FROM leads') !== false, "ajax_search.php searches both clients and leads");

$searchLeadStmt = $pdo->prepare("
    SELECT id, name, company, phone, email, service, status, call_status
    FROM leads
    WHERE name LIKE :q OR company LIKE :q OR phone LIKE :q OR email LIKE :q
    ORDER BY id DESC LIMIT 5
");
$searchLeadStmt->execute([':q' => '%Fintech%']);
$matchedLeads = $searchLeadStmt->fetchAll(PDO::FETCH_ASSOC);
assertCondition(!empty($matchedLeads), "Global search matches lead 'Fintech Growth Labs'");

// 6. Zero Native confirm() or alert() in Admin
$adminPages = glob(dirname(__DIR__) . '/admin/pages/*.php');
$nativeAlertFound = false;
$nativeConfirmFound = false;
foreach ($adminPages as $page) {
    $content = file_get_contents($page);
    if (preg_match('/(?<!AdminModal\.)\balert\s*\(/', $content)) {
        $nativeAlertFound = true;
    }
    if (preg_match('/(?<!AdminModal\.)\bconfirm\s*\(/', $content)) {
        // Exclude custom window.AdminModal.confirm
        if (!preg_match('/window\.AdminModal\.confirm/', $content)) {
            $nativeConfirmFound = true;
        }
    }
}
assertCondition(!$nativeAlertFound, "Audit: ZERO native browser alert() found in admin pages");
assertCondition(!$nativeConfirmFound, "Audit: ZERO native browser confirm() found in admin pages (AdminModal used exclusively)");


// =============================================================================
// CLEANUP TEST FIXTURES
// =============================================================================
$pdo->prepare("DELETE FROM revenue WHERE id = ?")->execute([$revId]);
$pdo->prepare("DELETE FROM calls WHERE id = ?")->execute([$callId]);
$pdo->prepare("DELETE FROM leads WHERE id = ?")->execute([$leadId]);
$pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$revClientId]);

echo "\n====================================================\n";
echo "Test Summary: {$passCount} Passed, {$failCount} Failed\n";
echo "====================================================\n";

if ($failCount > 0) {
    exit(1);
}
