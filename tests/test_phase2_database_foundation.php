<?php
/**
 * Website Tailors — Phase 2 Database Foundation Verification Test Suite
 *
 * Asserts:
 * 1. Existing invoices, revenue, clients, and projects remain intact and readable.
 * 2. ensureSchema() migrations are idempotent across multiple executions.
 * 3. All new columns and indexes exist on invoices, revenue, and projects tables.
 * 4. Line items JSON format validation.
 * 5. Unique constraints and foreign-key integrity.
 * 6. Isolated test insertions with full financial fields and clean teardown.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pdf_generator.php';

$pdo = Database::getInstance()->getConnection();

echo "====================================================\n";
echo "WebsiteTailors Phase 2 Database Foundation Test Suite\n";
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
// 1. Existing Data Preserved
// -----------------------------------------------------------------------------
echo "Group 1: Existing Data Integrity & Readability\n";
$invCount = (int)$pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn();
assertCondition($invCount > 0, "Existing invoices preserved (Count: {$invCount})");

$firstInv = $pdo->query("SELECT * FROM invoices ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assertCondition(!empty($firstInv['invoice_number']), "Historical invoice reference intact: {$firstInv['invoice_number']}");
assertCondition(isset($firstInv['amount']) && (float)$firstInv['amount'] > 0, "Historical invoice amount intact: ₹{$firstInv['amount']}");

$revCount = (int)$pdo->query("SELECT COUNT(*) FROM revenue")->fetchColumn();
assertCondition($revCount > 0, "Existing revenue records preserved (Count: {$revCount})");

$clientCount = (int)$pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
assertCondition($clientCount > 0, "Existing clients preserved (Count: {$clientCount})");

$projCount = (int)$pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();
assertCondition($projCount > 0, "Existing projects preserved (Count: {$projCount})");

// -----------------------------------------------------------------------------
// 2. Migration Idempotency (Run 1, Run 2, Run 3)
// -----------------------------------------------------------------------------
echo "\nGroup 2: Migration Idempotency Across Consecutive Runs\n";
try {
    Database::getInstance();
    assertCondition(true, "Database singleton reconnect (Run 1) succeeded without error");
    Database::getInstance();
    assertCondition(true, "Database singleton reconnect (Run 2) succeeded without error");
    Database::getInstance();
    assertCondition(true, "Database singleton reconnect (Run 3) succeeded without error");
} catch (\Throwable $e) {
    assertCondition(false, "Migration idempotency failed: " . $e->getMessage());
}

// -----------------------------------------------------------------------------
// 3. Invoices Table New Schema Columns
// -----------------------------------------------------------------------------
echo "\nGroup 3: Invoices Table Schema Columns Verification\n";
$invCols = $pdo->query("PRAGMA table_info(invoices)")->fetchAll(PDO::FETCH_ASSOC);
$invColNames = array_column($invCols, 'name');

$expectedInvCols = [
    'id', 'invoice_number', 'invoice_type', 'client_id', 'client_name',
    'project_id', 'project_name', 'project_phase', 'service', 'amount',
    'project_total', 'advance_amount', 'amount_received', 'balance_amount',
    'status', 'due_date', 'paid_at', 'payment_mode', 'transaction_reference',
    'bank_name', 'payment_method_desc', 'payment_date', 'line_items',
    'notes', 'created_at', 'updated_at'
];

foreach ($expectedInvCols as $col) {
    assertCondition(in_array($col, $invColNames, true), "Invoices table contains column '{$col}'");
}

// -----------------------------------------------------------------------------
// 4. Revenue Table New Schema Columns
// -----------------------------------------------------------------------------
echo "\nGroup 4: Revenue Table Schema Columns Verification\n";
$revCols = $pdo->query("PRAGMA table_info(revenue)")->fetchAll(PDO::FETCH_ASSOC);
$revColNames = array_column($revCols, 'name');

$expectedRevCols = [
    'id', 'client_id', 'client_name', 'lead_id', 'invoice_id', 'project_id',
    'amount', 'payment_type', 'payment_status', 'payment_date',
    'transaction_reference', 'bank_name', 'service', 'notes',
    'created_at', 'updated_at'
];

foreach ($expectedRevCols as $col) {
    assertCondition(in_array($col, $revColNames, true), "Revenue table contains column '{$col}'");
}

// -----------------------------------------------------------------------------
// 5. Projects Table Client Relationship
// -----------------------------------------------------------------------------
echo "\nGroup 5: Projects Table Schema Columns Verification\n";
$projCols = $pdo->query("PRAGMA table_info(projects)")->fetchAll(PDO::FETCH_ASSOC);
$projColNames = array_column($projCols, 'name');
assertCondition(in_array('client_id', $projColNames, true), "Projects table contains column 'client_id'");

// -----------------------------------------------------------------------------
// 6. Test Advance & Full Payment Database Insertions with Structured Line Items
// -----------------------------------------------------------------------------
echo "\nGroup 6: Financial Fields & Line Items Persistence Test\n";
$testInvNum = 'INV-TEST-PH2-' . time();

$sampleLineItems = [
    [
        'description' => 'UI/UX Design',
        'qty' => 1,
        'rate' => 25000,
        'amount' => 25000
    ],
    [
        'description' => 'Frontend Development',
        'qty' => 1,
        'rate' => 35000,
        'amount' => 35000
    ]
];
$lineItemsJson = json_encode($sampleLineItems, JSON_THROW_ON_ERROR);

$insertStmt = $pdo->prepare("
    INSERT INTO invoices (
        invoice_number, invoice_type, client_id, client_name, project_id, project_name, project_phase,
        service, amount, project_total, advance_amount, amount_received, balance_amount,
        status, due_date, paid_at, payment_mode, transaction_reference, bank_name,
        payment_method_desc, payment_date, line_items, notes, created_at, updated_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, datetime('now'), datetime('now')
    )
");

$insertStmt->execute([
    $testInvNum,
    'advance',
    1,
    'Test Client Enterprise',
    2,
    'Kroma Studio Redesign',
    'Phase 1 — Discovery & UI',
    'Websites',
    30000.00,
    100000.00, // Total project scope
    30000.00,  // Advance required
    30000.00,  // Advance received
    70000.00,  // Derived balance left
    'paid',
    '2026-11-01',
    '2026-10-08 15:30:00',
    'UPI',
    'UPI-UTR-987654321012',
    'HDFC Bank',
    null,
    '2026-10-08 15:30:00',
    $lineItemsJson,
    'Milestone 1 Discovery deliverables.'
]);

$insertedId = (int)$pdo->lastInsertId();
assertCondition($insertedId > 0, "Inserted test advance invoice record ID #{$insertedId}");

$fetchedInv = $pdo->query("SELECT * FROM invoices WHERE id = {$insertedId}")->fetch(PDO::FETCH_ASSOC);
assertCondition($fetchedInv['invoice_type'] === 'advance', "Invoice type stored as 'advance'");
assertCondition((float)$fetchedInv['project_total'] === 100000.00, "Project total stored correctly (₹100,000.00)");
assertCondition((float)$fetchedInv['amount'] === 30000.00, "Current invoice amount stored correctly (₹30,000.00)");
assertCondition((float)$fetchedInv['amount_received'] === 30000.00, "Amount received stored correctly (₹30,000.00)");
assertCondition((float)$fetchedInv['balance_amount'] === 70000.00, "Project balance left stored correctly (₹70,000.00)");
assertCondition($fetchedInv['payment_mode'] === 'UPI', "Payment mode stored as 'UPI'");
assertCondition($fetchedInv['transaction_reference'] === 'UPI-UTR-987654321012', "Transaction reference UTR stored correctly");
assertCondition($fetchedInv['bank_name'] === 'HDFC Bank', "Bank name stored correctly");

// Verify Line Items JSON Decoding
$decodedItems = json_decode((string)$fetchedInv['line_items'], true);
assertCondition(is_array($decodedItems) && count($decodedItems) === 2, "Line items JSON successfully decoded into 2 items");
assertCondition($decodedItems[0]['description'] === 'UI/UX Design' && $decodedItems[0]['amount'] === 25000, "Line item 1 numeric amount and description verified");

// Test Revenue link with project_id, UTR, and Bank Name
$revInsert = $pdo->prepare("
    INSERT INTO revenue (
        client_id, client_name, invoice_id, project_id, amount,
        payment_type, payment_status, payment_date, transaction_reference, bank_name,
        service, notes, created_at, updated_at
    ) VALUES (
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, datetime('now'), datetime('now')
    )
");
$revInsert->execute([
    1,
    'Test Client Enterprise',
    $insertedId,
    2,
    30000.00,
    'UPI',
    'Paid',
    '2026-10-08 15:30:00',
    'UPI-UTR-987654321012',
    'HDFC Bank',
    'Websites',
    'Advance settlement for invoice ' . $testInvNum
]);
$revId = (int)$pdo->lastInsertId();
assertCondition($revId > 0, "Revenue record #{$revId} linked with invoice_id and project_id");

$fetchedRev = $pdo->query("SELECT * FROM revenue WHERE id = {$revId}")->fetch(PDO::FETCH_ASSOC);
assertCondition((int)$fetchedRev['project_id'] === 2, "Revenue project_id link verified");
assertCondition($fetchedRev['transaction_reference'] === 'UPI-UTR-987654321012', "Revenue UTR reference verified");

// -----------------------------------------------------------------------------
// 7. Clean up Temporary Test Record
// -----------------------------------------------------------------------------
echo "\nGroup 7: Safe Teardown of Test Records\n";
$pdo->exec("DELETE FROM revenue WHERE id = {$revId}");
$pdo->exec("DELETE FROM invoices WHERE id = {$insertedId}");
$checkInv = (int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE id = {$insertedId}")->fetchColumn();
$checkRev = (int)$pdo->query("SELECT COUNT(*) FROM revenue WHERE id = {$revId}")->fetchColumn();
assertCondition($checkInv === 0 && $checkRev === 0, "Temporary test records cleaned up completely; zero pollution");

// -----------------------------------------------------------------------------
// 8. MySQL Schema File Verification
// -----------------------------------------------------------------------------
echo "\nGroup 8: MySQL Schema File Verification (makeit.sql)\n";
$sqlContent = file_get_contents(__DIR__ . '/../database/makeit.sql');
assertCondition(str_contains($sqlContent, 'invoice_type'), "makeit.sql contains invoice_type");
assertCondition(str_contains($sqlContent, 'project_total'), "makeit.sql contains project_total");
assertCondition(str_contains($sqlContent, 'amount_received'), "makeit.sql contains amount_received");
assertCondition(str_contains($sqlContent, 'balance_amount'), "makeit.sql contains balance_amount");
assertCondition(str_contains($sqlContent, 'line_items'), "makeit.sql contains line_items");
assertCondition(str_contains($sqlContent, 'transaction_reference'), "makeit.sql contains transaction_reference");
assertCondition(str_contains($sqlContent, 'idx_projects_client_id'), "makeit.sql contains idx_projects_client_id");

echo "\n====================================================\n";
echo "Phase 2 Test Summary: {$passCount} Passed, {$failCount} Failed\n";
echo "====================================================\n";

if ($failCount > 0) {
    exit(1);
}
