<?php
/**
 * WebsiteTailors — Phase 6 Business Analytics Verification Test Suite
 *
 * Asserts all Phase 6 requirements:
 * 1. Top KPI Cards (TOTAL CLIENTS, NEW LEADS, REVENUE THIS MONTH with ₹, PENDING FOLLOW-UPS)
 * 2. Revenue Overview line chart with 4 periods (Last 7 Days, Last 30 Days, Last 6 Months, This Year)
 * 3. Call Activity bar chart with 2 periods (7 Days, 30 Days) & 4 categories (Calls, Connected, No Answer, Call Back)
 * 4. 6-Stage Lead Pipeline (New, Contacted, Qualified, Proposal Sent, Converted, Lost)
 * 5. Revenue Breakdown by Service (Website, Software, AI + Automation, UI/UX, Other) in ₹
 * 6. Recent Activity Feed (Recent Leads, Recent Calls, Recent Payments)
 * 7. FOLLOW-UPS NEEDING ATTENTION Panel with Overdue Highlighting
 * 8. Zero-Fake-Data Empty State Enforcement
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
echo "WebsiteTailors Phase 6 Business Analytics Verification\n";
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

// 1. Render admin/index.php
ob_start();
require dirname(__DIR__) . '/admin/index.php';
$html = ob_get_clean();

echo "Group 1: Top 4 KPI Cards (Business Metrics)\n";
assertCondition(strpos($html, 'TOTAL CLIENTS') !== false, "TOTAL CLIENTS card rendered");
assertCondition(strpos($html, 'NEW LEADS') !== false, "NEW LEADS card rendered");
assertCondition(strpos($html, 'REVENUE THIS MONTH') !== false, "REVENUE THIS MONTH card rendered");
assertCondition(strpos($html, 'PENDING FOLLOW-UPS') !== false, "PENDING FOLLOW-UPS card rendered");
assertCondition(preg_match('/₹[0-9,]+(\.[0-9]{2})?/', $html) === 1, "Revenue displayed in ₹ (INR)");

echo "\nGroup 2: Revenue Overview (Smooth Line Chart)\n";
assertCondition(strpos($html, 'Revenue Overview') !== false, "Revenue Overview section rendered");
assertCondition(strpos($html, 'data-period="7days"') !== false, "Last 7 Days period switch exists");
assertCondition(strpos($html, 'data-period="30days"') !== false, "Last 30 Days period switch exists");
assertCondition(strpos($html, 'data-period="6months"') !== false, "Last 6 Months period switch exists");
assertCondition(strpos($html, 'data-period="year"') !== false, "This Year period switch exists");
assertCondition(strpos($html, 'revenueSvg') !== false, "Responsive SVG element present");

echo "\nGroup 3: Call Activity (Bar Chart)\n";
assertCondition(strpos($html, 'Call Activity') !== false, "Call Activity section rendered");
assertCondition(strpos($html, 'Connected') !== false, "Connected category present");
assertCondition(strpos($html, 'No Answer') !== false, "No Answer category present");
assertCondition(strpos($html, 'Call Back') !== false, "Call Back category present");
assertCondition(strpos($html, 'callPeriodTabs') !== false, "Call period tabs exist");

echo "\nGroup 4: 6-Stage Visual Lead Pipeline\n";
assertCondition(strpos($html, 'Lead Pipeline') !== false, "Lead Pipeline section rendered");
$pipelineStages = ['New', 'Contacted', 'Qualified', 'Proposal Sent', 'Converted', 'Lost'];
foreach ($pipelineStages as $stage) {
    assertCondition(strpos($html, $stage) !== false, "Pipeline stage '{$stage}' present");
}

echo "\nGroup 5: Revenue Breakdown by Service\n";
assertCondition(strpos($html, 'Revenue Breakdown by Service') !== false, "Revenue Breakdown section rendered");
$services = ['Website', 'Software', 'AI + Automation', 'UI/UX', 'Other'];
foreach ($services as $srv) {
    assertCondition(strpos($html, $srv) !== false, "Service category '{$srv}' present");
}

echo "\nGroup 6: Recent Activity Unified Feed\n";
assertCondition(strpos($html, 'Recent Activity') !== false, "Recent Activity section rendered");
assertCondition(strpos($html, 'activity-type-lead') !== false, "Lead activity type badge present");
assertCondition(strpos($html, 'activity-type-call') !== false, "Call activity type badge present");
assertCondition(strpos($html, 'activity-type-payment') !== false, "Payment activity type badge present");

echo "\nGroup 7: FOLLOW-UPS NEEDING ATTENTION Panel\n";
assertCondition(strpos($html, 'FOLLOW-UPS NEEDING ATTENTION') !== false, "Follow-up panel title rendered");
assertCondition(strpos($html, 'badge-overdue') !== false, "Overdue badge rendered for past due follow-ups");
assertCondition(strpos($html, 'badge-upcoming') !== false, "Upcoming badge rendered for future follow-ups");

echo "\nGroup 8: Strict Zero-Fake-Data Empty States Verification\n";
// Create temporary empty SQLite DB to verify zero-data behavior
$tempDb = sys_get_temp_dir() . '/WebsiteTailors_empty_audit_' . uniqid() . '.sqlite';
$tempPdo = new PDO('sqlite:' . $tempDb);
$tempPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$tempPdo->exec('
    CREATE TABLE clients (id INTEGER PRIMARY KEY, client_name TEXT, status TEXT, created_at TEXT);
    CREATE TABLE leads (id INTEGER PRIMARY KEY, name TEXT, status TEXT, created_at TEXT);
    CREATE TABLE calls (id INTEGER PRIMARY KEY, contact_name TEXT, status TEXT, scheduled_at TEXT, created_at TEXT, outcome TEXT);
    CREATE TABLE invoices (id INTEGER PRIMARY KEY, invoice_number TEXT, client_name TEXT, service TEXT, amount REAL, status TEXT, paid_at TEXT, created_at TEXT);
');

require_once dirname(__DIR__) . '/config/database.php';
$dbInstance = Database::getInstance();
$ref = new ReflectionProperty('Database', 'pdo');
$origPdo = $ref->getValue($dbInstance);
$ref->setValue($dbInstance, $tempPdo);

ob_start();
require dirname(__DIR__) . '/admin/index.php';
$emptyHtml = ob_get_clean();

$ref->setValue($dbInstance, $origPdo);
@unlink($tempDb);

assertCondition(strpos($emptyHtml, 'No revenue recorded yet.') !== false, "Strict empty state: 'No revenue recorded yet.'");
assertCondition(strpos($emptyHtml, 'No calls recorded yet.') !== false, "Strict empty state: 'No calls recorded yet.'");
assertCondition(strpos($emptyHtml, 'No follow-ups scheduled.') !== false, "Strict empty state: 'No follow-ups scheduled.'");

echo "\n====================================================\n";
echo "Phase 6 Analytics Results: {$passCount} Passed, {$failCount} Failed\n";
echo "====================================================\n";

if ($failCount === 0) {
    echo "🎉 ALL PHASE 6 REQUIREMENTS VERIFIED AND PASSED!\n";
    exit(0);
} else {
    echo "❌ SOME PHASE 6 CHECKS FAILED!\n";
    exit(1);
}
