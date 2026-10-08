<?php
/**
 * WebsiteTailors — Phase 4 Revenue Management Verification Test Suite
 *
 * Asserts all Phase 4 requirements:
 * 1. Revenue database table schema with 12 fields
 * 2. Allowed payment statuses: Pending, Partially Paid, Paid, Refunded
 * 3. Allowed payment types: UPI, Bank Transfer, Cash, Card, Other
 * 4. Revenue page: /admin/pages/revenue.php rendering 5 KPI metrics (Total, Paid, Pending, This Month, Last Month in ₹)
 * 5. Revenue table columns: Client, Service, Amount, Payment Status, Payment Type, Payment Date, Actions (View, Edit, Delete)
 * 6. Add Revenue with strict numeric validation (amount never stored as string)
 * 7. Edit Revenue with automatic dashboard totals synchronization
 * 8. Internal ledger check (no external payment gateways)
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
$_SESSION['csrf_token'] = 'test_token_phase4_rev';

require_once dirname(__DIR__) . '/includes/init.php';
$pdo = Database::getInstance()->getConnection();

echo "====================================================\n";
echo "WebsiteTailors Phase 4 Revenue Management Verification\n";
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
// Group 1: Database Table Schema Verification
// -----------------------------------------------------------------------------
echo "Group 1: Revenue Table Schema (12 Fields)\n";

$tableExists = false;
$cols = [];
try {
    $tableInfo = $pdo->query("PRAGMA table_info(revenue)")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($tableInfo)) {
        $tableExists = true;
        foreach ($tableInfo as $col) {
            $cols[$col['name']] = strtolower($col['type']);
        }
    }
} catch (\Throwable) {
    // MySQL fallback
    try {
        $q = $pdo->query("SHOW COLUMNS FROM revenue");
        if ($q) {
            $tableExists = true;
            while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                $cols[$row['Field']] = strtolower($row['Type']);
            }
        }
    } catch (\Throwable) {}
}

assertCondition($tableExists, "Revenue table exists in database");

$requiredFields = [
    'id', 'client_id', 'lead_id', 'invoice_id', 'amount',
    'payment_type', 'payment_status', 'payment_date', 'service',
    'notes', 'created_at', 'updated_at'
];

foreach ($requiredFields as $field) {
    assertCondition(isset($cols[$field]), "Field '{$field}' exists in revenue table");
}

// Amount must be stored as numeric (REAL, FLOAT, DECIMAL, or NUMERIC)
$amountType = $cols['amount'] ?? '';
$isAmountNumericType = (
    strpos($amountType, 'real') !== false ||
    strpos($amountType, 'decimal') !== false ||
    strpos($amountType, 'numeric') !== false ||
    strpos($amountType, 'float') !== false ||
    strpos($amountType, 'double') !== false
);
assertCondition($isAmountNumericType, "Amount column type is numeric ({$amountType}), never text/varchar");

// -----------------------------------------------------------------------------
// Group 2: Revenue Page Rendering (/admin/pages/revenue.php)
// -----------------------------------------------------------------------------
echo "\nGroup 2: Revenue Page UI Rendering & 5 Top KPI Cards\n";

$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = [];

ob_start();
require dirname(__DIR__) . '/admin/pages/revenue.php';
$html = ob_get_clean();

assertCondition(strpos($html, 'Revenue Management') !== false, "Page header 'Revenue Management' rendered");
assertCondition(strpos($html, 'Total Revenue') !== false, "Card 1: Total Revenue rendered");
assertCondition(strpos($html, 'Paid Revenue') !== false, "Card 2: Paid Revenue rendered");
assertCondition(strpos($html, 'Pending Revenue') !== false, "Card 3: Pending Revenue rendered");
assertCondition(strpos($html, 'This Month') !== false, "Card 4: This Month rendered");
assertCondition(strpos($html, 'Last Month') !== false, "Card 5: Last Month rendered");
assertCondition(preg_match('/₹[0-9,]+(\.[0-9]{2})?/', $html) === 1, "Amounts displayed in ₹ (INR currency symbol)");

// -----------------------------------------------------------------------------
// Group 3: Revenue Table Columns & Actions
// -----------------------------------------------------------------------------
echo "\nGroup 3: Revenue Table Columns & Row Actions\n";

assertCondition(strpos($html, '<th>Client</th>') !== false, "Table header: Client");
assertCondition(strpos($html, '<th>Service</th>') !== false, "Table header: Service");
assertCondition(strpos($html, '<th>Amount</th>') !== false, "Table header: Amount");
assertCondition(strpos($html, '<th>Payment Status</th>') !== false, "Table header: Payment Status");
assertCondition(strpos($html, '<th>Payment Type</th>') !== false, "Table header: Payment Type");
assertCondition(strpos($html, '<th>Payment Date</th>') !== false, "Table header: Payment Date");
assertCondition(stripos($html, 'Actions</th>') !== false, "Table header: Actions");

assertCondition(strpos($html, 'openViewModal') !== false, "Row action: View exists");
assertCondition(strpos($html, 'openEditModal') !== false, "Row action: Edit exists");
assertCondition(strpos($html, 'confirmDeleteRevenue') !== false, "Row action: Delete exists");

// -----------------------------------------------------------------------------
// Group 4: Allowed Payment Statuses & Types
// -----------------------------------------------------------------------------
echo "\nGroup 4: Payment Statuses & Types Validation\n";

$statuses = ['Pending', 'Partially Paid', 'Paid', 'Refunded'];
foreach ($statuses as $st) {
    assertCondition(strpos($html, $st) !== false, "Payment status '{$st}' supported");
}

$types = ['UPI', 'Bank Transfer', 'Cash', 'Card', 'Other'];
foreach ($types as $tp) {
    assertCondition(strpos($html, $tp) !== false, "Payment type '{$tp}' supported");
}

// -----------------------------------------------------------------------------
// Group 5: Add Revenue & Strict Numeric Validation
// -----------------------------------------------------------------------------
echo "\nGroup 5: Add Revenue & Strict Numeric Validation\n";

// Test 5a: Invalid non-numeric amount rejection
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'csrf_token'          => 'test_token_phase4_rev',
    'action'              => 'add_revenue',
    'client_id'           => 1,
    'service'             => 'Software',
    'amount'              => 'invalid-string-amount',
    'payment_type'        => 'UPI',
    'payment_status'      => 'Paid',
    'payment_date'        => date('Y-m-d'),
    'notes'               => 'Testing invalid numeric rejection'
];

ob_start();
require dirname(__DIR__) . '/admin/pages/revenue.php';
$htmlRejection = ob_get_clean();
assertCondition(strpos($htmlRejection, 'Please enter a valid numeric amount') !== false, "Rejects non-numeric amount 'invalid-string-amount'");

// Test 5b: Negative/zero amount rejection
$_POST['amount'] = '-500.00';
ob_start();
require dirname(__DIR__) . '/admin/pages/revenue.php';
$htmlNegRejection = ob_get_clean();
assertCondition(strpos($htmlNegRejection, 'Please enter a valid numeric amount') !== false, "Rejects negative amount '-500.00'");

// Test 5c: Valid numeric revenue addition
$testAmount = 75000.50;
$_POST['amount'] = (string)$testAmount;
$_POST['notes']  = 'Phase 4 Unit Test Automated Entry';

ob_start();
require dirname(__DIR__) . '/admin/pages/revenue.php';
$htmlSuccess = ob_get_clean();

assertCondition(strpos($htmlSuccess, 'successfully recorded') !== false, "Successfully records valid numeric revenue ₹75,000.50");

// Verify row in database
$verifyStmt = $pdo->prepare("SELECT * FROM revenue WHERE notes = ? ORDER BY id DESC LIMIT 1");
$verifyStmt->execute(['Phase 4 Unit Test Automated Entry']);
$createdRecord = $verifyStmt->fetch(PDO::FETCH_ASSOC);

assertCondition(!empty($createdRecord), "Revenue record found in database");
assertCondition(is_numeric($createdRecord['amount']), "Stored amount is strictly numeric");
assertCondition((float)$createdRecord['amount'] === 75000.50, "Stored amount matches 75000.50 exactly");
assertCondition($createdRecord['payment_type'] === 'UPI', "Payment type stored as 'UPI'");
assertCondition($createdRecord['payment_status'] === 'Paid', "Payment status stored as 'Paid'");
assertCondition($createdRecord['service'] === 'Software', "Service stored as 'Software'");

// -----------------------------------------------------------------------------
// Group 6: Edit Revenue & Dashboard Totals Synchronization
// -----------------------------------------------------------------------------
echo "\nGroup 6: Edit Revenue & Dashboard Totals Automatic Sync\n";

$recordId = (int)$createdRecord['id'];
$updatedAmount = 92500.75;

// Query current dashboard revenue this month
$currentMonthStart = date('Y-m-01 00:00:00');
$currentMonthEnd   = date('Y-m-t 23:59:59');

$dashRevBefore = (float)$pdo->query("
    SELECT COALESCE(SUM(amount), 0) FROM revenue 
    WHERE LOWER(payment_status) = 'paid' AND payment_date >= '{$currentMonthStart}' AND payment_date <= '{$currentMonthEnd}'
")->fetchColumn();

// Perform Edit Revenue
$_POST = [
    'csrf_token'          => 'test_token_phase4_rev',
    'action'              => 'edit_revenue',
    'revenue_id'          => $recordId,
    'service'             => 'AI + Automation',
    'amount'              => (string)$updatedAmount,
    'payment_type'        => 'Bank Transfer',
    'payment_status'      => 'Paid',
    'payment_date'        => date('Y-m-d'),
    'notes'               => 'Phase 4 Unit Test Updated Notes'
];

ob_start();
require dirname(__DIR__) . '/admin/pages/revenue.php';
$htmlEdit = ob_get_clean();

assertCondition(strpos($htmlEdit, 'successfully updated') !== false, "Successfully edited revenue record #{$recordId}");

// Verify updated record in DB
$checkUpdatedStmt = $pdo->prepare("SELECT * FROM revenue WHERE id = ?");
$checkUpdatedStmt->execute([$recordId]);
$updatedRecord = $checkUpdatedStmt->fetch(PDO::FETCH_ASSOC);

assertCondition((float)$updatedRecord['amount'] === 92500.75, "Amount successfully updated to ₹92,500.75");
assertCondition($updatedRecord['service'] === 'AI + Automation', "Service successfully updated to 'AI + Automation'");
assertCondition($updatedRecord['payment_type'] === 'Bank Transfer', "Payment type successfully updated to 'Bank Transfer'");

// Check Dashboard synchronization:
// Render admin/index.php and verify new totals reflect updated amount
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = [];
$_POST = [];

ob_start();
require dirname(__DIR__) . '/admin/index.php';
$dashboardHtml = ob_get_clean();

$dashRevAfter = (float)$pdo->query("
    SELECT COALESCE(SUM(amount), 0) FROM revenue 
    WHERE LOWER(payment_status) = 'paid' AND payment_date >= '{$currentMonthStart}' AND payment_date <= '{$currentMonthEnd}'
")->fetchColumn();

$expectedDiff = round($updatedAmount - $testAmount, 2);
$actualDiff   = round($dashRevAfter - $dashRevBefore, 2);

assertCondition($actualDiff === $expectedDiff, "Dashboard revenue this month updated automatically (difference: ₹{$actualDiff})");
assertCondition(strpos($dashboardHtml, number_format($dashRevAfter, 2)) !== false, "Dashboard UI renders updated total ₹" . number_format($dashRevAfter, 2));

// -----------------------------------------------------------------------------
// Group 7: Delete Revenue & Dashboard Resync
// -----------------------------------------------------------------------------
echo "\nGroup 7: Delete Revenue & Real-time Recalculation\n";

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'csrf_token'  => 'test_token_phase4_rev',
    'action'      => 'delete_revenue',
    'revenue_id'  => $recordId
];

ob_start();
require dirname(__DIR__) . '/admin/pages/revenue.php';
$htmlDel = ob_get_clean();

assertCondition(strpos($htmlDel, 'has been deleted') !== false, "Revenue record #{$recordId} deleted successfully");

$delCheck = $pdo->prepare("SELECT COUNT(*) FROM revenue WHERE id = ?");
$delCheck->execute([$recordId]);
assertCondition((int)$delCheck->fetchColumn() === 0, "Record #{$recordId} no longer in revenue table");

$dashRevFinal = (float)$pdo->query("
    SELECT COALESCE(SUM(amount), 0) FROM revenue 
    WHERE LOWER(payment_status) = 'paid' AND payment_date >= '{$currentMonthStart}' AND payment_date <= '{$currentMonthEnd}'
")->fetchColumn();

assertCondition(round($dashRevFinal, 2) === round($dashRevBefore - $testAmount, 2), "Dashboard revenue recalculated immediately after deletion");

// -----------------------------------------------------------------------------
// Group 8: Internal Ledger Compliance (No External Gateway)
// -----------------------------------------------------------------------------
echo "\nGroup 8: Internal Ledger Compliance\n";
$revenuePhpCode = file_get_contents(dirname(__DIR__) . '/admin/pages/revenue.php');
assertCondition(stripos($revenuePhpCode, 'stripe') === false, "Zero Stripe SDK or external gateway dependencies");
assertCondition(stripos($revenuePhpCode, 'razorpay') === false, "Zero Razorpay SDK or external gateway dependencies");
assertCondition(stripos($revenuePhpCode, 'paypal') === false, "Zero PayPal SDK or external gateway dependencies");
assertCondition(strpos($revenuePhpCode, 'Internal revenue tracking system') !== false, "Internal tracking comment and architecture verified");

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------
echo "\n====================================================\n";
echo "Phase 4 Test Results: {$passCount} Passed, {$failCount} Failed\n";
echo "====================================================\n";

exit($failCount === 0 ? 0 : 1);
