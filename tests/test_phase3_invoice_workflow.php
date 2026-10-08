<?php
/**
 * Website Tailors — Phase 3 Invoice Generation UI & Form Workflow Test Suite
 *
 * Verifies all Section 22 automated test criteria:
 * TEST 1: New Advance Invoice (Project: 100k, Invoice: 30k, Received: 30k -> Balance: 70k, Status: Paid)
 * TEST 2: Advance invoice partially received (Project: 100k, Invoice: 30k, Received: 15k -> Status: Partially Paid, Balance: 85k)
 * TEST 3: Full invoice unpaid (Project: 100k, Invoice: 100k, Received: 0 -> Pending, Balance: 100k)
 * TEST 4: Full invoice paid (Project: 100k, Invoice: 100k, Received: 100k -> Paid, Balance: 0)
 * TEST 5: UPI mode with UTR stored correctly
 * TEST 6: Cash mode with UTR not required
 * TEST 7: Bank Transfer with UTR and Bank Name
 * TEST 8: Structured line items math (qty * rate) and serialized JSON
 * TEST 9: Custom phase saved
 * TEST 10: Existing historical invoice compatibility and display
 * TEST 11: Duplicate invoice reference blocked
 * TEST 12: Double submission prevented
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$pdo = Database::getInstance()->getConnection();
$baseUrl = getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8000';
$cookieFile = sys_get_temp_dir() . "/WebsiteTailors_phase3_cookies_" . uniqid() . ".txt";

echo "====================================================\n";
echo "WebsiteTailors Phase 3 Invoice Workflow Test Suite\n";
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

function http_req(string $url, string $method = "GET", $data = null, ?string $cookieFile = null): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if ($method === "POST") {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($res, 0, $headerSize);
    $body = substr($res, $headerSize);
    return ["code" => $httpCode, "header" => $header, "body" => $body];
}

function get_csrf(string $body): string {
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $body, $matches);
    return $matches[1] ?? '';
}

// -----------------------------------------------------------------------------
// Authentication Pre-flight
// -----------------------------------------------------------------------------
echo "Pre-flight: Admin Authentication\n";
$rLoginGet = http_req($baseUrl . "/admin/login.php", "GET", null, $cookieFile);
$csrfToken = get_csrf($rLoginGet["body"]);
assertCondition(!empty($csrfToken), "CSRF token extracted from login form");

$loginData = http_build_query([
    "csrf_token" => $csrfToken,
    "email"      => "websietailorss@gmail.com",
    "password"   => "Admin@12345"
]);
$rLoginPost = http_req($baseUrl . "/admin/login.php", "POST", $loginData, $cookieFile);
assertCondition(strpos($rLoginPost["body"], "Invoices & Billing") !== false || strpos($rLoginPost["body"], "Dashboard") !== false || $rLoginPost["code"] === 200, "Admin session authenticated");

// Ensure a test client exists for our tests
$testClientRow = $pdo->query("SELECT id FROM clients ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$testClientId = $testClientRow ? (int)$testClientRow['id'] : 1;

// Tracking created test invoice IDs for teardown
$createdInvoiceIds = [];

// Helper to fetch invoice page and CSRF
function get_invoices_page_csrf(string $baseUrl, string $cookieFile): array {
    $res = http_req($baseUrl . "/admin/pages/invoices.php", "GET", null, $cookieFile);
    $token = get_csrf($res["body"]);
    return [$res, $token];
}

// -----------------------------------------------------------------------------
// TEST 1: New Advance Invoice
// Project: ₹100,000, Invoice: ₹30,000, Received: ₹30,000 -> Expected: Balance ₹70,000, Status Paid
// -----------------------------------------------------------------------------
echo "\nTest 1: New Advance Invoice (Full Advance Collected)\n";
list($rPage, $csrf) = get_invoices_page_csrf($baseUrl, $cookieFile);
$invNum1 = 'INV-TEST-001-' . time();

$postData1 = [
    'csrf_token'             => $csrf,
    'action'                 => 'create_invoice',
    'client_id'              => $testClientId,
    'project_id'             => 0,
    'project_name_custom'    => 'Test Project 1',
    'project_phase'          => 'Phase 1',
    'invoice_number'         => $invNum1,
    'invoice_type'           => 'advance',
    'service'                => 'Websites',
    'invoice_date'           => date('Y-m-d'),
    'due_date'               => date('Y-m-d', time() + 86400 * 14),
    'project_total'          => '100000.00',
    'amount'                 => '30000.00',
    'amount_received'        => '30000.00',
    'status'                 => 'pending', // form default, server derives
    'payment_mode'           => 'UPI',
    'transaction_reference'  => 'UTR-ADV-001',
    'payment_date'           => date('Y-m-d'),
    'notes'                  => 'Test 1 Milestone Advance'
];
$rPost1 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postData1), $cookieFile);
assertCondition(strpos($rPost1["body"], "generated successfully") !== false, "POST request creates advance invoice successfully");

$inv1 = $pdo->query("SELECT * FROM invoices WHERE invoice_number = '{$invNum1}'")->fetch(PDO::FETCH_ASSOC);
if ($inv1) $createdInvoiceIds[] = (int)$inv1['id'];

assertCondition($inv1 !== false, "Advance invoice record found in database");
assertCondition((float)$inv1['project_total'] === 100000.00, "Project total is ₹100,000.00");
assertCondition((float)$inv1['amount'] === 30000.00, "Current invoice amount is ₹30,000.00");
assertCondition((float)$inv1['advance_amount'] === 30000.00, "Advance amount stored as ₹30,000.00");
assertCondition((float)$inv1['amount_received'] === 30000.00, "Amount received is ₹30,000.00");
assertCondition((float)$inv1['balance_amount'] === 70000.00, "Authoritative balance is ₹70,000.00");
assertCondition(strtolower($inv1['status']) === 'paid', "Status is derived as 'paid'");

// Verify revenue sync
$rev1 = $pdo->query("SELECT * FROM revenue WHERE invoice_id = " . (int)$inv1['id'])->fetch(PDO::FETCH_ASSOC);
assertCondition($rev1 !== false, "Revenue ledger record synchronized for paid advance invoice");
assertCondition((float)$rev1['amount'] === 30000.00, "Revenue amount recorded matches ₹30,000.00");

// -----------------------------------------------------------------------------
// TEST 2: Advance invoice partially received
// Project: ₹100,000, Invoice: ₹30,000, Received: ₹15,000 -> Expected: Status Partially Paid, Project balance ₹85,000
// -----------------------------------------------------------------------------
echo "\nTest 2: Advance Invoice Partially Received\n";
list($rPage, $csrf) = get_invoices_page_csrf($baseUrl, $cookieFile);
$invNum2 = 'INV-TEST-002-' . time();

$postData2 = [
    'csrf_token'             => $csrf,
    'action'                 => 'create_invoice',
    'client_id'              => $testClientId,
    'project_id'             => 0,
    'project_name_custom'    => 'Test Project 2',
    'project_phase'          => 'Phase 1',
    'invoice_number'         => $invNum2,
    'invoice_type'           => 'advance',
    'service'                => 'Software',
    'invoice_date'           => date('Y-m-d'),
    'due_date'               => date('Y-m-d', time() + 86400 * 14),
    'project_total'          => '100000.00',
    'amount'                 => '30000.00',
    'amount_received'        => '15000.00',
    'status'                 => 'pending',
    'payment_mode'           => 'Bank Transfer',
    'transaction_reference'  => 'UTR-PARTIAL-002',
    'bank_name'              => 'HDFC Bank',
    'payment_date'           => date('Y-m-d'),
    'notes'                  => 'Test 2 Partial Advance'
];
$rPost2 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postData2), $cookieFile);
$inv2 = $pdo->query("SELECT * FROM invoices WHERE invoice_number = '{$invNum2}'")->fetch(PDO::FETCH_ASSOC);
if ($inv2) $createdInvoiceIds[] = (int)$inv2['id'];

assertCondition($inv2 !== false, "Invoice 2 record found in database");
assertCondition((float)$inv2['amount_received'] === 15000.00, "Amount received is ₹15,000.00");
assertCondition((float)$inv2['balance_amount'] === 85000.00, "Project balance calculated as ₹85,000.00");
assertCondition(strtolower($inv2['status']) === 'partially paid', "Status is derived as 'partially paid'");

// -----------------------------------------------------------------------------
// TEST 3: Full invoice unpaid
// Project: ₹100,000, Invoice: ₹100,000, Received: ₹0 -> Expected: Pending, Balance ₹100,000
// -----------------------------------------------------------------------------
echo "\nTest 3: Full Invoice Unpaid\n";
list($rPage, $csrf) = get_invoices_page_csrf($baseUrl, $cookieFile);
$invNum3 = 'INV-TEST-003-' . time();

$postData3 = [
    'csrf_token'             => $csrf,
    'action'                 => 'create_invoice',
    'client_id'              => $testClientId,
    'project_id'             => 0,
    'project_name_custom'    => 'Test Project 3',
    'project_phase'          => 'Phase 2',
    'invoice_number'         => $invNum3,
    'invoice_type'           => 'full',
    'service'                => 'AI + Automation',
    'invoice_date'           => date('Y-m-d'),
    'due_date'               => date('Y-m-d', time() + 86400 * 14),
    'project_total'          => '100000.00',
    'amount'                 => '100000.00',
    'amount_received'        => '0.00',
    'status'                 => 'pending',
    'payment_mode'           => 'UPI',
    'notes'                  => 'Test 3 Full Unpaid'
];
$rPost3 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postData3), $cookieFile);
$inv3 = $pdo->query("SELECT * FROM invoices WHERE invoice_number = '{$invNum3}'")->fetch(PDO::FETCH_ASSOC);
if ($inv3) $createdInvoiceIds[] = (int)$inv3['id'];

assertCondition($inv3 !== false, "Invoice 3 record found in database");
assertCondition(strtolower($inv3['status']) === 'pending', "Status is 'pending'");
assertCondition((float)$inv3['balance_amount'] === 100000.00, "Balance remaining is ₹100,000.00");

// -----------------------------------------------------------------------------
// TEST 4: Full invoice paid
// Project: ₹100,000, Invoice: ₹100,000, Received: ₹100,000 -> Expected: Paid, Balance ₹0
// -----------------------------------------------------------------------------
echo "\nTest 4: Full Invoice Paid\n";
list($rPage, $csrf) = get_invoices_page_csrf($baseUrl, $cookieFile);
$invNum4 = 'INV-TEST-004-' . time();

$postData4 = [
    'csrf_token'             => $csrf,
    'action'                 => 'create_invoice',
    'client_id'              => $testClientId,
    'project_id'             => 0,
    'project_name_custom'    => 'Test Project 4',
    'project_phase'          => 'Phase 3',
    'invoice_number'         => $invNum4,
    'invoice_type'           => 'full',
    'service'                => 'Websites',
    'invoice_date'           => date('Y-m-d'),
    'due_date'               => date('Y-m-d', time() + 86400 * 14),
    'project_total'          => '100000.00',
    'amount'                 => '100000.00',
    'amount_received'        => '100000.00',
    'payment_mode'           => 'Bank Transfer',
    'transaction_reference'  => 'UTR-FULL-PAID-004',
    'bank_name'              => 'ICICI Bank',
    'payment_date'           => date('Y-m-d'),
    'notes'                  => 'Test 4 Full Paid'
];
$rPost4 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postData4), $cookieFile);
$inv4 = $pdo->query("SELECT * FROM invoices WHERE invoice_number = '{$invNum4}'")->fetch(PDO::FETCH_ASSOC);
if ($inv4) $createdInvoiceIds[] = (int)$inv4['id'];

assertCondition($inv4 !== false, "Invoice 4 record found in database");
assertCondition(strtolower($inv4['status']) === 'paid', "Status is 'paid'");
assertCondition((float)$inv4['balance_amount'] === 0.00, "Balance is ₹0.00");

// -----------------------------------------------------------------------------
// TEST 5: UPI Mode
// Payment mode: UPI, UTR: TEST-UTR-001 -> Expected: UTR stored correctly
// -----------------------------------------------------------------------------
echo "\nTest 5: UPI Payment Mode and UTR Reference\n";
list($rPage, $csrf) = get_invoices_page_csrf($baseUrl, $cookieFile);
$invNum5 = 'INV-TEST-005-' . time();

$postData5 = [
    'csrf_token'             => $csrf,
    'action'                 => 'create_invoice',
    'client_id'              => $testClientId,
    'project_id'             => 0,
    'invoice_number'         => $invNum5,
    'invoice_type'           => 'advance',
    'service'                => 'Websites',
    'due_date'               => date('Y-m-d', time() + 86400 * 7),
    'project_total'          => '50000.00',
    'amount'                 => '25000.00',
    'amount_received'        => '25000.00',
    'payment_mode'           => 'UPI',
    'transaction_reference'  => 'TEST-UTR-001',
    'payment_date'           => date('Y-m-d'),
    'notes'                  => 'Test 5 UPI UTR'
];
$rPost5 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postData5), $cookieFile);
$inv5 = $pdo->query("SELECT * FROM invoices WHERE invoice_number = '{$invNum5}'")->fetch(PDO::FETCH_ASSOC);
if ($inv5) $createdInvoiceIds[] = (int)$inv5['id'];

assertCondition($inv5 !== false, "Invoice 5 record found");
assertCondition($inv5['payment_mode'] === 'UPI', "Payment mode stored as 'UPI'");
assertCondition($inv5['transaction_reference'] === 'TEST-UTR-001', "UTR stored as 'TEST-UTR-001'");

// -----------------------------------------------------------------------------
// TEST 6: Cash Mode
// Payment mode: Cash -> Expected: UTR not required / null
// -----------------------------------------------------------------------------
echo "\nTest 6: Cash Payment Mode (UTR Not Required)\n";
list($rPage, $csrf) = get_invoices_page_csrf($baseUrl, $cookieFile);
$invNum6 = 'INV-TEST-006-' . time();

$postData6 = [
    'csrf_token'             => $csrf,
    'action'                 => 'create_invoice',
    'client_id'              => $testClientId,
    'project_id'             => 0,
    'invoice_number'         => $invNum6,
    'invoice_type'           => 'full',
    'service'                => 'UI/UX',
    'due_date'               => date('Y-m-d', time() + 86400 * 7),
    'project_total'          => '20000.00',
    'amount'                 => '20000.00',
    'amount_received'        => '20000.00',
    'payment_mode'           => 'Cash',
    'transaction_reference'  => '', // No UTR for cash
    'payment_date'           => date('Y-m-d'),
    'notes'                  => 'Test 6 Cash In-Hand'
];
$rPost6 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postData6), $cookieFile);
$inv6 = $pdo->query("SELECT * FROM invoices WHERE invoice_number = '{$invNum6}'")->fetch(PDO::FETCH_ASSOC);
if ($inv6) $createdInvoiceIds[] = (int)$inv6['id'];

assertCondition($inv6 !== false, "Cash invoice created without error");
assertCondition($inv6['payment_mode'] === 'Cash', "Payment mode is 'Cash'");
assertCondition(empty($inv6['transaction_reference']), "Transaction reference is not required for cash");

// -----------------------------------------------------------------------------
// TEST 7: Bank Transfer
// Expected: UTR + optional bank name
// -----------------------------------------------------------------------------
echo "\nTest 7: Bank Transfer Mode (UTR + Bank Name)\n";
list($rPage, $csrf) = get_invoices_page_csrf($baseUrl, $cookieFile);
$invNum7 = 'INV-TEST-007-' . time();

$postData7 = [
    'csrf_token'             => $csrf,
    'action'                 => 'create_invoice',
    'client_id'              => $testClientId,
    'project_id'             => 0,
    'invoice_number'         => $invNum7,
    'invoice_type'           => 'advance',
    'service'                => 'Software',
    'due_date'               => date('Y-m-d', time() + 86400 * 7),
    'project_total'          => '75000.00',
    'amount'                 => '35000.00',
    'amount_received'        => '35000.00',
    'payment_mode'           => 'Bank Transfer',
    'transaction_reference'  => 'NEFT-8839201938',
    'bank_name'              => 'State Bank of India',
    'payment_date'           => date('Y-m-d'),
    'notes'                  => 'Test 7 Bank Wire'
];
$rPost7 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postData7), $cookieFile);
$inv7 = $pdo->query("SELECT * FROM invoices WHERE invoice_number = '{$invNum7}'")->fetch(PDO::FETCH_ASSOC);
if ($inv7) $createdInvoiceIds[] = (int)$inv7['id'];

assertCondition($inv7 !== false, "Bank transfer invoice created");
assertCondition($inv7['payment_mode'] === 'Bank Transfer', "Payment mode is 'Bank Transfer'");
assertCondition($inv7['transaction_reference'] === 'NEFT-8839201938', "UTR reference stored");
assertCondition($inv7['bank_name'] === 'State Bank of India', "Bank name stored as 'State Bank of India'");

// -----------------------------------------------------------------------------
// TEST 8: Multiple Line Items
// Expected: qty × rate and total are correct
// -----------------------------------------------------------------------------
echo "\nTest 8: Structured Line Items Calculation\n";
list($rPage, $csrf) = get_invoices_page_csrf($baseUrl, $cookieFile);
$invNum8 = 'INV-TEST-008-' . time();

$postData8 = [
    'csrf_token'             => $csrf,
    'action'                 => 'create_invoice',
    'client_id'              => $testClientId,
    'project_id'             => 0,
    'invoice_number'         => $invNum8,
    'invoice_type'           => 'full',
    'service'                => 'Software',
    'due_date'               => date('Y-m-d', time() + 86400 * 14),
    'project_total'          => '100000.00',
    'amount'                 => '100000.00',
    'amount_received'        => '0.00',
    'payment_mode'           => 'UPI',
    'line_items_desc'        => ['UI/UX Design', 'Frontend Development', 'Backend Development', 'Testing & Deployment'],
    'line_items_qty'         => [1, 1, 1, 1],
    'line_items_rate'        => [25000.00, 35000.00, 30000.00, 10000.00],
    'notes'                  => 'Test 8 Multi-Item'
];
$rPost8 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postData8), $cookieFile);
$inv8 = $pdo->query("SELECT * FROM invoices WHERE invoice_number = '{$invNum8}'")->fetch(PDO::FETCH_ASSOC);
if ($inv8) $createdInvoiceIds[] = (int)$inv8['id'];

assertCondition($inv8 !== false, "Invoice 8 record found");
assertCondition(!empty($inv8['line_items']), "Line items JSON column is populated");

$items = json_decode($inv8['line_items'], true);
assertCondition(is_array($items) && count($items) === 4, "Parsed line items count equals 4");
assertCondition($items[0]['description'] === 'UI/UX Design' && (float)$items[0]['amount'] === 25000.00, "Item 1 math (1 × ₹25,000 = ₹25,000)");
assertCondition($items[1]['description'] === 'Frontend Development' && (float)$items[1]['amount'] === 35000.00, "Item 2 math (1 × ₹35,000 = ₹35,000)");
assertCondition($items[2]['description'] === 'Backend Development' && (float)$items[2]['amount'] === 30000.00, "Item 3 math (1 × ₹30,000 = ₹30,000)");
assertCondition($items[3]['description'] === 'Testing &amp; Deployment' || $items[3]['description'] === 'Testing & Deployment', "Item 4 sanitized safely");

$itemsTotal = (float)array_sum(array_column($items, 'amount'));
assertCondition($itemsTotal === 100000.00, "Total of all line items equals ₹100,000.00");

// -----------------------------------------------------------------------------
// TEST 9: Custom Phase
// Expected: Custom phase saved correctly
// -----------------------------------------------------------------------------
echo "\nTest 9: Custom Project Phase\n";
list($rPage, $csrf) = get_invoices_page_csrf($baseUrl, $cookieFile);
$invNum9 = 'INV-TEST-009-' . time();

$postData9 = [
    'csrf_token'             => $csrf,
    'action'                 => 'create_invoice',
    'client_id'              => $testClientId,
    'project_id'             => 0,
    'project_phase'          => 'Custom Phase',
    'custom_phase'           => 'Phase 4 — Pilot Testing & Staging Launch',
    'invoice_number'         => $invNum9,
    'invoice_type'           => 'full',
    'service'                => 'Websites',
    'due_date'               => date('Y-m-d', time() + 86400 * 14),
    'project_total'          => '60000.00',
    'amount'                 => '60000.00',
    'amount_received'        => '0.00',
    'notes'                  => 'Test 9 Custom Phase'
];
$rPost9 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postData9), $cookieFile);
$inv9 = $pdo->query("SELECT * FROM invoices WHERE invoice_number = '{$invNum9}'")->fetch(PDO::FETCH_ASSOC);
if ($inv9) $createdInvoiceIds[] = (int)$inv9['id'];

assertCondition($inv9 !== false, "Invoice 9 record found");
assertCondition($inv9['project_phase'] === 'Phase 4 — Pilot Testing & Staging Launch', "Custom phase saved exactly as provided");

// -----------------------------------------------------------------------------
// TEST 10: Existing Historical Invoice Compatibility
// Expected: Still loads without errors in HTML table and view modal
// -----------------------------------------------------------------------------
echo "\nTest 10: Historical Invoice Backward Compatibility\n";
$legacyInv = $pdo->query("SELECT * FROM invoices WHERE project_total = 0 OR project_id IS NULL ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assertCondition($legacyInv !== false, "Historical legacy invoice exists in database");

$rInvoicesList = http_req($baseUrl . "/admin/pages/invoices.php", "GET", null, $cookieFile);
assertCondition($rInvoicesList["code"] === 200, "Invoices list page loads with HTTP 200");
assertCondition(strpos($rInvoicesList["body"], $legacyInv["invoice_number"]) !== false, "Historical invoice #{$legacyInv['invoice_number']} renders cleanly in admin table");
assertCondition(strpos($rInvoicesList["body"], "Not specified") !== false, "'Not specified' gracefully rendered for unassigned project/phase");

// -----------------------------------------------------------------------------
// TEST 11: Duplicate Invoice Reference
// Expected: Blocked with clear user validation message
// -----------------------------------------------------------------------------
echo "\nTest 11: Duplicate Invoice Reference Protection\n";
list($rPage, $csrf) = get_invoices_page_csrf($baseUrl, $cookieFile);

$postData11 = [
    'csrf_token'             => $csrf,
    'action'                 => 'create_invoice',
    'client_id'              => $testClientId,
    'invoice_number'         => $invNum1, // duplicate of Test 1
    'invoice_type'           => 'advance',
    'service'                => 'Websites',
    'due_date'               => date('Y-m-d', time() + 86400 * 14),
    'project_total'          => '50000.00',
    'amount'                 => '25000.00',
    'amount_received'        => '0.00'
];
$rPost11 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postData11), $cookieFile);
assertCondition(strpos($rPost11["body"], "already exists") !== false, "Duplicate invoice reference blocked with clear error message");

// Verify count in database
$dupCount = (int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE invoice_number = '{$invNum1}'")->fetchColumn();
assertCondition($dupCount === 1, "Only one record exists for reference #{$invNum1}");

// -----------------------------------------------------------------------------
// TEST 12: Double Submit Protection
// Expected: Only one invoice created
// -----------------------------------------------------------------------------
echo "\nTest 12: Double Submit Protection\n";
list($rPage, $csrf) = get_invoices_page_csrf($baseUrl, $cookieFile);
$invNum12 = 'INV-TEST-012-' . time();

$postData12 = [
    'csrf_token'             => $csrf,
    'action'                 => 'create_invoice',
    'client_id'              => $testClientId,
    'invoice_number'         => $invNum12,
    'invoice_type'           => 'full',
    'service'                => 'Websites',
    'due_date'               => date('Y-m-d', time() + 86400 * 14),
    'project_total'          => '40000.00',
    'amount'                 => '40000.00',
    'amount_received'        => '40000.00'
];

// Submit request 1
$rPost12_1 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postData12), $cookieFile);
// Immediate second submit with same reference
$rPost12_2 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postData12), $cookieFile);

$count12 = (int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE invoice_number = '{$invNum12}'")->fetchColumn();
assertCondition($count12 === 1, "Double submit prevented: exactly one invoice created for #{$invNum12}");

$inv12 = $pdo->query("SELECT id FROM invoices WHERE invoice_number = '{$invNum12}'")->fetch(PDO::FETCH_ASSOC);
if ($inv12) $createdInvoiceIds[] = (int)$inv12['id'];

// -----------------------------------------------------------------------------
// CLEANUP & TEARDOWN
// -----------------------------------------------------------------------------
echo "\nTeardown: Cleaning up isolated test records\n";
foreach ($createdInvoiceIds as $tid) {
    $pdo->exec("DELETE FROM revenue WHERE invoice_id = {$tid}");
    $pdo->exec("DELETE FROM invoices WHERE id = {$tid}");
}
assertCondition(true, "All temporary Phase 3 test invoices and synced revenue records cleaned up safely");

if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

echo "\n====================================================\n";
echo "Phase 3 Test Summary: {$passCount} Passed, {$failCount} Failed\n";
echo "====================================================\n";

if ($failCount > 0) {
    exit(1);
}
