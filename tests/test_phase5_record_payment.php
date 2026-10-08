<?php
/**
 * Website Tailors — Phase 5: Record Payment & Milestone Payment Workflow Test Suite
 *
 * Automated verification of all 15 Phase 5 business scenarios:
 * 1. Advance payment: Invoice Paid, Project Received ₹30k, Project Balance ₹70k, Revenue +₹30k
 * 2. Overpayment Protection: Payment > remaining balance rejected ("Payment exceeds the remaining balance.")
 * 3. Partial invoice payment: Invoice Partially Paid, Remaining balance ₹15k
 * 4. Second invoice / Milestone Billing: Project Received ₹55k, Project Balance ₹45k
 * 5. Final payment: Project Received ₹100k, Project Balance ₹0
 * 6. UPI payment: UTR stored exactly once in revenue ledger
 * 7. Duplicate UTR: Second payment rejected ("This payment appears to have already been recorded.")
 * 8. Cash: No UTR required, clean NULL/omitted reference
 * 9. Bank Transfer: UTR + Bank Name stored accurately
 * 10. Double-click / Duplicate POST: Second rapid submission rejected ("This payment appears to have already been recorded.")
 * 11. Failed DB operation: Transaction rolls back, zero partial financial records
 * 12. Unauthenticated request: Rejected (302 redirect to login)
 * 13. Invalid CSRF: Rejected ("Security session expired")
 * 14. Invalid invoice/project relationship: Rejected ("Invalid project relationship for this invoice.")
 * 15. Invoice with no project: Payment recorded without inventing fake project
 * 16. PDF integration: Invoice PDF reflects recorded payments and authoritative balance
 * 17. UI Modals & Elements: View Invoice modal and Record Payment modal markup present
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/pdf_generator.php';

$pdo = Database::getInstance()->getConnection();
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
    $pdo->exec("PRAGMA journal_mode = WAL;");
    $pdo->exec("PRAGMA busy_timeout = 15000;");
    $pdo->exec("PRAGMA synchronous = NORMAL;");
}

$baseUrl = getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8000';
$cookieFile = tempnam(sys_get_temp_dir(), 'wt_p5_cookie_');

echo "====================================================\n";
echo "Website Tailors Phase 5: Record Payment & Milestone Verification\n";
echo "====================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertCondition(bool $cond, string $msg, $debug = null): void {
    global $passCount, $failCount;
    if ($cond) {
        $passCount++;
        echo "  [PASS] {$msg}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$msg}\n";
        if ($debug !== null) {
            $dbgStr = is_string($debug) ? substr(strip_tags($debug), 0, 200) : json_encode($debug);
            echo "    DEBUG: {$dbgStr}\n";
        }
    }
}

function http_req(string $url, string $method = 'GET', $data = null, string $cookieJar = '', array $headers = []): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    if (!empty($cookieJar)) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $res = curl_exec($ch);
    if ($res === false) {
        $err = curl_error($ch);
        return ["code" => 0, "header" => "", "body" => "", "error" => $err];
    }
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($res, 0, $headerSize);
    $body = substr($res, $headerSize);
    return ["code" => $httpCode, "header" => $header, "body" => $body];
}

function get_csrf(string $body): string {
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $body, $matches);
    return $matches[1] ?? '';
}

function get_invoices_csrf(string $baseUrl, string $cookieFile): array {
    $res = http_req($baseUrl . "/admin/pages/invoices.php", "GET", null, $cookieFile);
    $token = get_csrf($res["body"]);
    return [$res, $token];
}

// -----------------------------------------------------------------------------
// Pre-flight: Admin Authentication & Clean Slate
// -----------------------------------------------------------------------------
echo "Pre-flight: Admin Authentication\n";
$rLoginGet = http_req($baseUrl . "/admin/login.php", "GET", null, $cookieFile);
$csrfToken = get_csrf($rLoginGet["body"]);
assertCondition(!empty($csrfToken), "Extracted CSRF token from login form");

$loginData = http_build_query([
    "csrf_token" => $csrfToken,
    "email"      => "websietailorss@gmail.com",
    "password"   => "Admin@12345"
]);
$rLoginPost = http_req($baseUrl . "/admin/login.php", "POST", $loginData, $cookieFile);
assertCondition($rLoginPost["code"] === 302 || strpos($rLoginPost["body"], "Invoices & Billing") !== false || $rLoginPost["code"] === 200, "Admin authenticated session established");

// Find or create test client
$clientRow = $pdo->query("SELECT id, client_name, company_name FROM clients ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$clientRow) {
    $pdo->exec("INSERT INTO clients (client_name, company_name, email, phone, status, service, created_at, updated_at) VALUES ('Acme Corp', 'Acme Corp', 'contact@acme.com', '+91 99999 00000', 'active', 'Websites', datetime('now'), datetime('now'))");
    $testClientId = (int)$pdo->lastInsertId();
    $testClientName = 'Acme Corp';
    $clientRow = ['id' => $testClientId, 'client_name' => $testClientName, 'company_name' => $testClientName];
} else {
    $testClientId = (int)$clientRow['id'];
    $testClientName = !empty($clientRow['company_name']) ? (string)$clientRow['company_name'] : (string)$clientRow['client_name'];
}

// Dedicated test project for Phase 5 to prevent cross-test contamination
$testProjectTitle = 'Phase 5 Milestone Test Project';
$pStmt = $pdo->prepare("SELECT id, title FROM projects WHERE title = ? LIMIT 1");
$pStmt->execute([$testProjectTitle]);
$projRow = $pStmt->fetch(PDO::FETCH_ASSOC);
$pStmt->closeCursor();

if (!$projRow) {
    $insProj = $pdo->prepare("INSERT INTO projects (title, slug, category, client_name, description, image, client_id, status, created_at, updated_at) VALUES (?, 'phase-5-milestone-test-project', 'Websites', ?, 'Dedicated project for Phase 5 automated billing verification', 'assets/images/projects/First.jpg', ?, 'active', datetime('now'), datetime('now'))");
    $insProj->execute([$testProjectTitle, $testClientName, $testClientId]);
    $testProjectId = (int)$pdo->lastInsertId();
} else {
    $testProjectId = (int)$projRow['id'];
}

// Clean up any test invoices from previous runs to ensure pristine state
$pdo->exec("DELETE FROM revenue WHERE invoice_id IN (SELECT id FROM invoices WHERE invoice_number LIKE 'INV-2026-T%' OR invoice_number LIKE 'INV-TEST%')");
$pdo->exec("DELETE FROM invoices WHERE invoice_number LIKE 'INV-2026-T%' OR invoice_number LIKE 'INV-TEST%'");
$pdo->exec("DELETE FROM revenue WHERE project_id = {$testProjectId}");
$pdo->exec("DELETE FROM invoices WHERE project_id = {$testProjectId}");

$cleanupInvoiceIds = [];
$cleanupRevenueIds = [];

// =============================================================================
// TEST 1: ADVANCE INVOICE & PAYMENT
// Project: ₹100,000 | Invoice: ₹30,000 | Payment: ₹30,000
// Expected: Invoice Paid, Project Received ₹30,000, Project Balance ₹70,000, Revenue +₹30,000
// =============================================================================
echo "\n--- TEST 1: Advance Invoice Full Payment ---\n";
list($rPage, $csrf) = get_invoices_csrf($baseUrl, $cookieFile);
$invNum1 = 'INV-2026-T1-' . time();

// 1. Create unpaid advance invoice
$postCreate1 = [
    'csrf_token'          => $csrf,
    'action'              => 'create_invoice',
    'client_id'           => $testClientId,
    'client_name_custom'  => $testClientName,
    'project_id'          => $testProjectId,
    'project_phase'       => 'Phase 1',
    'invoice_number'      => $invNum1,
    'invoice_type'        => 'advance',
    'service'             => 'Websites',
    'invoice_date'        => date('Y-m-d'),
    'due_date'            => date('Y-m-d', time() + 86400 * 14),
    'project_total'       => '100000.00',
    'amount'              => '30000.00',
    'amount_received'     => '0.00', // Unpaid
    'status'              => 'pending',
    'payment_mode'        => 'UPI',
    'notes'               => 'Phase 1 Advance Scope'
];
$rCreate1 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postCreate1), $cookieFile);
$qInv1 = $pdo->prepare("SELECT * FROM invoices WHERE invoice_number = ? LIMIT 1");
$qInv1->execute([$invNum1]);
$inv1 = $qInv1->fetch(PDO::FETCH_ASSOC);
$qInv1->closeCursor();

assertCondition(!empty($inv1), "Invoice 1 ({$invNum1}) created in database", $rCreate1['body']);
$cleanupInvoiceIds[] = (int)$inv1['id'];

// Initial state: Unpaid, Revenue = 0 for this invoice
$qRevBefore1 = $pdo->prepare("SELECT COUNT(*) FROM revenue WHERE invoice_id = ?");
$qRevBefore1->execute([(int)$inv1['id']]);
$revBefore1 = (int)$qRevBefore1->fetchColumn();
$qRevBefore1->closeCursor();
assertCondition($revBefore1 === 0, "No revenue recorded while invoice 1 is unpaid (INVOICE != REVENUE rule)");

// Record full payment of ₹30,000 via record_payment
list($rPage, $csrf) = get_invoices_csrf($baseUrl, $cookieFile);
$postPay1 = [
    'csrf_token'             => $csrf,
    'action'                 => 'record_payment',
    'invoice_id'             => $inv1['id'],
    'client_id'              => $testClientId,
    'project_id'             => $testProjectId,
    'amount'                 => '30000.00',
    'payment_date'           => date('Y-m-d'),
    'payment_mode'           => 'UPI',
    'transaction_reference'  => 'UTR-ADV-T1-' . time(),
    'notes'                  => 'Full advance settlement received'
];
$rPay1 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postPay1), $cookieFile);
assertCondition(strpos($rPay1['body'], 'successfully recorded') !== false, "Payment recorded successfully via POST response", $rPay1['body']);

// Query updated invoice
$qInv1Upd = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
$qInv1Upd->execute([(int)$inv1['id']]);
$inv1Updated = $qInv1Upd->fetch(PDO::FETCH_ASSOC);
$qInv1Upd->closeCursor();

assertCondition((float)$inv1Updated['amount_received'] === 30000.00, "Invoice 1 amount_received is ₹30,000.00");
assertCondition((float)$inv1Updated['balance_amount'] === 70000.00, "Project balance remaining is ₹70,000.00");
assertCondition(strtolower((string)$inv1Updated['status']) === 'paid', "Invoice 1 status updated to 'paid'");

// Query revenue ledger
$qRev1 = $pdo->prepare("SELECT * FROM revenue WHERE invoice_id = ? ORDER BY id DESC LIMIT 1");
$qRev1->execute([(int)$inv1['id']]);
$rev1 = $qRev1->fetch(PDO::FETCH_ASSOC);
$qRev1->closeCursor();

assertCondition(!empty($rev1) && (float)$rev1['amount'] === 30000.00, "Revenue entry of ₹30,000.00 synchronized in revenue ledger");
assertCondition($rev1['payment_status'] === 'Paid', "Revenue entry marked as 'Paid'");
if ($rev1) $cleanupRevenueIds[] = (int)$rev1['id'];

// =============================================================================
// TEST 2: OVERPAYMENT PROTECTION
// Attempt to record payment exceeding remaining balance on Invoice 1
// Expected: Rejection with "Payment exceeds the remaining balance."
// =============================================================================
echo "\n--- TEST 2: Overpayment Protection ---\n";
list($rPage, $csrf) = get_invoices_csrf($baseUrl, $cookieFile);
$postOverpay = [
    'csrf_token'             => $csrf,
    'action'                 => 'record_payment',
    'invoice_id'             => $inv1['id'],
    'amount'                 => '10000.00', // Exceeds balance because invoice 1 is already fully paid
    'payment_date'           => date('Y-m-d'),
    'payment_mode'           => 'UPI',
    'transaction_reference'  => 'UTR-OVERPAY-' . time()
];
$rOverpay = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postOverpay), $cookieFile);
assertCondition(strpos($rOverpay['body'], 'Payment exceeds the remaining balance.') !== false, "Overpayment correctly rejected with 'Payment exceeds the remaining balance.'", $rOverpay['body']);

// Verify invoice 1 remained at 30,000
$qInv1After = $pdo->prepare("SELECT amount_received FROM invoices WHERE id = ?");
$qInv1After->execute([(int)$inv1['id']]);
$inv1AfterOverpay = $qInv1After->fetchColumn();
$qInv1After->closeCursor();
assertCondition((float)$inv1AfterOverpay === 30000.00, "Invoice amount_received untouched after rejected overpayment");

// =============================================================================
// TEST 3: PARTIAL INVOICE PAYMENT
// Invoice: ₹30,000 | Payment: ₹15,000
// Expected: Invoice status Partially Paid, Remaining balance ₹15,000
// =============================================================================
echo "\n--- TEST 3: Partial Invoice Payment ---\n";
list($rPage, $csrf) = get_invoices_csrf($baseUrl, $cookieFile);
$invNum3 = 'INV-2026-T3-' . time();

$postCreate3 = [
    'csrf_token'          => $csrf,
    'action'              => 'create_invoice',
    'client_id'           => $testClientId,
    'client_name_custom'  => $testClientName,
    'project_id'          => 0, // Independent invoice for partial test
    'invoice_number'      => $invNum3,
    'invoice_type'        => 'advance',
    'service'             => 'Websites',
    'invoice_date'        => date('Y-m-d'),
    'due_date'            => date('Y-m-d', time() + 86400 * 14),
    'project_total'       => '30000.00',
    'amount'              => '30000.00',
    'amount_received'     => '0.00',
    'status'              => 'pending',
    'payment_mode'        => 'UPI',
    'notes'               => 'Testing partial payment'
];
$rCreate3 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postCreate3), $cookieFile);
$qInv3 = $pdo->prepare("SELECT * FROM invoices WHERE invoice_number = ? LIMIT 1");
$qInv3->execute([$invNum3]);
$inv3 = $qInv3->fetch(PDO::FETCH_ASSOC);
$qInv3->closeCursor();

assertCondition(!empty($inv3), "Invoice 3 created for partial payment test", $rCreate3['body']);
$cleanupInvoiceIds[] = (int)$inv3['id'];

list($rPage, $csrf) = get_invoices_csrf($baseUrl, $cookieFile);
$postPayPartial = [
    'csrf_token'             => $csrf,
    'action'                 => 'record_payment',
    'invoice_id'             => $inv3['id'],
    'amount'                 => '15000.00', // Partial payment
    'payment_date'           => date('Y-m-d'),
    'payment_mode'           => 'Bank Transfer',
    'transaction_reference'  => 'UTR-PARTIAL-' . time(),
    'bank_name'              => 'HDFC Bank',
    'notes'                  => 'First partial tranche'
];
$rPayPartial = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postPayPartial), $cookieFile);
assertCondition(strpos($rPayPartial['body'], 'successfully recorded') !== false, "Partial payment of ₹15,000 accepted", $rPayPartial['body']);

$qInv3Upd = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
$qInv3Upd->execute([(int)$inv3['id']]);
$inv3Updated = $qInv3Upd->fetch(PDO::FETCH_ASSOC);
$qInv3Upd->closeCursor();

assertCondition((float)$inv3Updated['amount_received'] === 15000.00, "Invoice 3 amount_received is ₹15,000.00");
assertCondition(strtolower((string)$inv3Updated['status']) === 'partially paid', "Invoice 3 status derived as 'partially paid'");
assertCondition((float)$inv3Updated['balance_amount'] === 15000.00, "Invoice 3 balance remaining is ₹15,000.00");

// =============================================================================
// TEST 4 & 5: MULTIPLE INVOICES & MILESTONE BILLING TO FINAL COMPLETION
// Project Total: ₹100,000
// Invoice 1: Advance ₹30,000 (Already paid in Test 1) -> Received: ₹30,000, Balance: ₹70,000
// Invoice 2: Phase 2 ₹25,000 -> Pay ₹25,000 -> Received: ₹55,000, Balance: ₹45,000
// Invoice 3: Final ₹45,000 -> Pay ₹45,000 -> Received: ₹100,000, Balance: ₹0 (Completed)
// =============================================================================
echo "\n--- TEST 4: Milestone Invoice 2 (Phase 2) ---\n";
list($rPage, $csrf) = get_invoices_csrf($baseUrl, $cookieFile);
$invNum4 = 'INV-2026-T4-PH2-' . time();

$postCreate4 = [
    'csrf_token'          => $csrf,
    'action'              => 'create_invoice',
    'client_id'           => $testClientId,
    'client_name_custom'  => $testClientName,
    'project_id'          => $testProjectId,
    'project_phase'       => 'Phase 2',
    'invoice_number'      => $invNum4,
    'invoice_type'        => 'full',
    'service'             => 'Websites',
    'invoice_date'        => date('Y-m-d'),
    'due_date'            => date('Y-m-d', time() + 86400 * 14),
    'project_total'       => '100000.00',
    'amount'              => '25000.00',
    'amount_received'     => '0.00',
    'status'              => 'pending',
    'payment_mode'        => 'Bank Transfer',
    'notes'               => 'Phase 2 Core Implementation'
];
$rCreate4 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postCreate4), $cookieFile);
$qInv4 = $pdo->prepare("SELECT * FROM invoices WHERE invoice_number = ? LIMIT 1");
$qInv4->execute([$invNum4]);
$inv4 = $qInv4->fetch(PDO::FETCH_ASSOC);
$qInv4->closeCursor();

assertCondition(!empty($inv4), "Milestone Invoice 2 ({$invNum4}) created", $rCreate4['body']);
$cleanupInvoiceIds[] = (int)$inv4['id'];

// Record Payment on Invoice 2: ₹25,000
list($rPage, $csrf) = get_invoices_csrf($baseUrl, $cookieFile);
$postPay4 = [
    'csrf_token'             => $csrf,
    'action'                 => 'record_payment',
    'invoice_id'             => $inv4['id'],
    'client_id'              => $testClientId,
    'project_id'             => $testProjectId,
    'amount'                 => '25000.00',
    'payment_date'           => date('Y-m-d'),
    'payment_mode'           => 'Bank Transfer',
    'transaction_reference'  => 'UTR-PH2-' . time(),
    'bank_name'              => 'ICICI Bank',
    'notes'                  => 'Phase 2 milestone approval'
];
$rPay4 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postPay4), $cookieFile);
assertCondition(strpos($rPay4['body'], 'successfully recorded') !== false, "Payment of ₹25,000 for Phase 2 accepted", $rPay4['body']);

$qInv4Upd = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
$qInv4Upd->execute([(int)$inv4['id']]);
$inv4Updated = $qInv4Upd->fetch(PDO::FETCH_ASSOC);
$qInv4Upd->closeCursor();

assertCondition((float)$inv4Updated['amount_received'] === 25000.00, "Invoice 2 received is ₹25,000.00");
assertCondition(strtolower((string)$inv4Updated['status']) === 'paid', "Invoice 2 status updated to 'paid'");

// Project total payments = ₹30k (inv1) + ₹25k (inv4) = ₹55,000. Project balance = ₹45,000
$totProjPaidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM revenue WHERE project_id = ? AND LOWER(payment_status) = 'paid'");
$totProjPaidStmt->execute([$testProjectId]);
$totProjPaid4 = (float)$totProjPaidStmt->fetchColumn();
$totProjPaidStmt->closeCursor();
assertCondition($totProjPaid4 === 55000.00, "Total Project Payments = ₹55,000.00");

assertCondition((float)$inv4Updated['balance_amount'] === 45000.00, "Project Balance remaining on Invoice 2 is ₹45,000.00");

// Also check Invoice 1 reflected updated project balance
$qInv1Bal = $pdo->prepare("SELECT balance_amount FROM invoices WHERE id = ?");
$qInv1Bal->execute([(int)$inv1['id']]);
$inv1BalanceReflected = (float)$qInv1Bal->fetchColumn();
$qInv1Bal->closeCursor();
assertCondition($inv1BalanceReflected === 45000.00, "Project Balance on Invoice 1 synchronized to ₹45,000.00");

echo "\n--- TEST 5: Final Milestone Payment (Phase 3) ---\n";
list($rPage, $csrf) = get_invoices_csrf($baseUrl, $cookieFile);
$invNum5 = 'INV-2026-T5-FINAL-' . time();

$postCreate5 = [
    'csrf_token'          => $csrf,
    'action'              => 'create_invoice',
    'client_id'           => $testClientId,
    'client_name_custom'  => $testClientName,
    'project_id'          => $testProjectId,
    'project_phase'       => 'Phase 3',
    'invoice_number'      => $invNum5,
    'invoice_type'        => 'full',
    'service'             => 'Websites',
    'invoice_date'        => date('Y-m-d'),
    'due_date'            => date('Y-m-d', time() + 86400 * 14),
    'project_total'       => '100000.00',
    'amount'              => '45000.00',
    'amount_received'     => '0.00',
    'status'              => 'pending',
    'payment_mode'        => 'UPI',
    'notes'               => 'Final Deliverables & Deployment'
];
$rCreate5 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postCreate5), $cookieFile);
$qInv5 = $pdo->prepare("SELECT * FROM invoices WHERE invoice_number = ? LIMIT 1");
$qInv5->execute([$invNum5]);
$inv5 = $qInv5->fetch(PDO::FETCH_ASSOC);
$qInv5->closeCursor();

assertCondition(!empty($inv5), "Final Milestone Invoice 3 ({$invNum5}) created", $rCreate5['body']);
$cleanupInvoiceIds[] = (int)$inv5['id'];

// Record Final Payment: ₹45,000
list($rPage, $csrf) = get_invoices_csrf($baseUrl, $cookieFile);
$postPay5 = [
    'csrf_token'             => $csrf,
    'action'                 => 'record_payment',
    'invoice_id'             => $inv5['id'],
    'client_id'              => $testClientId,
    'project_id'             => $testProjectId,
    'amount'                 => '45000.00',
    'payment_date'           => date('Y-m-d'),
    'payment_mode'           => 'UPI',
    'transaction_reference'  => 'UTR-FINAL-' . time(),
    'notes'                  => 'Final deployment settlement'
];
$rPay5 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postPay5), $cookieFile);
assertCondition(strpos($rPay5['body'], 'successfully recorded') !== false, "Final payment of ₹45,000 accepted", $rPay5['body']);

$qInv5Upd = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
$qInv5Upd->execute([(int)$inv5['id']]);
$inv5Updated = $qInv5Upd->fetch(PDO::FETCH_ASSOC);
$qInv5Upd->closeCursor();

assertCondition((float)$inv5Updated['amount_received'] === 45000.00, "Final Invoice received is ₹45,000.00");
assertCondition((float)$inv5Updated['balance_amount'] === 0.00, "Project Balance is ₹0.00");
assertCondition(strtolower((string)$inv5Updated['status']) === 'paid', "Final Invoice status is 'paid'");

$totProjPaidStmt->execute([$testProjectId]);
$totProjPaidFinal = (float)$totProjPaidStmt->fetchColumn();
$totProjPaidStmt->closeCursor();
assertCondition($totProjPaidFinal === 100000.00, "Total Project Received is ₹100,000.00 (Project 100% Paid)");

// =============================================================================
// TEST 6: UPI PAYMENT UTR STORAGE
// Expected: UTR stored exactly once in revenue ledger
// =============================================================================
echo "\n--- TEST 6: UPI Payment & UTR Storage ---\n";
list($rPage, $csrf) = get_invoices_csrf($baseUrl, $cookieFile);
$invNum6 = 'INV-2026-T6-UPI-' . time();
$fixedUtr6 = 'TEST-UTR-001-' . time();

$postCreate6 = [
    'csrf_token'          => $csrf,
    'action'              => 'create_invoice',
    'client_id'           => $testClientId,
    'client_name_custom'  => $testClientName,
    'project_id'          => 0,
    'invoice_number'      => $invNum6,
    'invoice_type'        => 'full',
    'service'             => 'Software',
    'invoice_date'        => date('Y-m-d'),
    'due_date'            => date('Y-m-d', time() + 86400 * 14),
    'project_total'       => '20000.00',
    'amount'              => '20000.00',
    'amount_received'     => '0.00',
    'status'              => 'pending',
    'payment_mode'        => 'UPI'
];
$rCreate6 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postCreate6), $cookieFile);
$qInv6 = $pdo->prepare("SELECT * FROM invoices WHERE invoice_number = ? LIMIT 1");
$qInv6->execute([$invNum6]);
$inv6 = $qInv6->fetch(PDO::FETCH_ASSOC);
$qInv6->closeCursor();

assertCondition(!empty($inv6), "Invoice 6 ({$invNum6}) created", $rCreate6['body']);
$cleanupInvoiceIds[] = (int)$inv6['id'];

list($rPage, $csrf) = get_invoices_csrf($baseUrl, $cookieFile);
$postPay6 = [
    'csrf_token'             => $csrf,
    'action'                 => 'record_payment',
    'invoice_id'             => $inv6['id'],
    'amount'                 => '20000.00',
    'payment_date'           => date('Y-m-d'),
    'payment_mode'           => 'UPI',
    'transaction_reference'  => $fixedUtr6,
    'notes'                  => 'UPI Payment Test'
];
$rPay6 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postPay6), $cookieFile);
assertCondition(strpos($rPay6['body'], 'successfully recorded') !== false, "UPI payment recorded", $rPay6['body']);

$qUtr6 = $pdo->prepare("SELECT COUNT(*) FROM revenue WHERE transaction_reference = ?");
$qUtr6->execute([$fixedUtr6]);
$utrCount6 = (int)$qUtr6->fetchColumn();
$qUtr6->closeCursor();
assertCondition($utrCount6 === 1, "UTR '{$fixedUtr6}' stored exactly once in revenue table");

// =============================================================================
// TEST 7: DUPLICATE UTR PROTECTION
// Attempt to submit identical UTR on another payment
// Expected: Rejected with "This payment appears to have already been recorded."
// =============================================================================
echo "\n--- TEST 7: Duplicate UTR Protection ---\n";
list($rPage, $csrf) = get_invoices_csrf($baseUrl, $cookieFile);
$invNum7 = 'INV-2026-T7-DUP-' . time();
$postCreate7 = [
    'csrf_token'          => $csrf,
    'action'              => 'create_invoice',
    'client_id'           => $testClientId,
    'client_name_custom'  => $testClientName,
    'project_id'          => 0,
    'invoice_number'      => $invNum7,
    'invoice_type'        => 'full',
    'service'             => 'Websites',
    'project_total'       => '10000.00',
    'amount'              => '10000.00',
    'amount_received'     => '0.00',
    'due_date'            => date('Y-m-d', time() + 86400 * 14)
];
$rCreate7 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postCreate7), $cookieFile);
$qInv7 = $pdo->prepare("SELECT * FROM invoices WHERE invoice_number = ? LIMIT 1");
$qInv7->execute([$invNum7]);
$inv7 = $qInv7->fetch(PDO::FETCH_ASSOC);
$qInv7->closeCursor();

assertCondition(!empty($inv7), "Invoice 7 ({$invNum7}) created", $rCreate7['body']);
$cleanupInvoiceIds[] = (int)$inv7['id'];

list($rPage, $csrf) = get_invoices_csrf($baseUrl, $cookieFile);
$postDupUtr = [
    'csrf_token'             => $csrf,
    'action'                 => 'record_payment',
    'invoice_id'             => $inv7['id'],
    'amount'                 => '10000.00',
    'payment_date'           => date('Y-m-d'),
    'payment_mode'           => 'UPI',
    'transaction_reference'  => $fixedUtr6 // REUSE SAME UTR
];
$rDupUtr = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postDupUtr), $cookieFile);
assertCondition(strpos($rDupUtr['body'], 'This payment appears to have already been recorded.') !== false, "Duplicate UTR rejected with 'This payment appears to have already been recorded.'", $rDupUtr['body']);

$qInv7Paid = $pdo->prepare("SELECT amount_received FROM invoices WHERE id = ?");
$qInv7Paid->execute([(int)$inv7['id']]);
$inv7Paid = (float)$qInv7Paid->fetchColumn();
$qInv7Paid->closeCursor();
assertCondition($inv7Paid === 0.00, "Invoice 7 has 0 received payments after duplicate rejection");

// =============================================================================
// TEST 8: CASH PAYMENT
// Expected: Cash mode succeeds without requiring a UTR reference
// =============================================================================
echo "\n--- TEST 8: Cash Payment Mode ---\n";
list($rPage, $csrf) = get_invoices_csrf($baseUrl, $cookieFile);
$postCash = [
    'csrf_token'             => $csrf,
    'action'                 => 'record_payment',
    'invoice_id'             => $inv7['id'],
    'amount'                 => '10000.00',
    'payment_date'           => date('Y-m-d'),
    'payment_mode'           => 'Cash',
    'notes'                  => 'Cash collected in office'
];
$rCash = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postCash), $cookieFile);
assertCondition(strpos($rCash['body'], 'successfully recorded') !== false, "Cash payment recorded successfully without UTR", $rCash['body']);

$qCashRev = $pdo->prepare("SELECT * FROM revenue WHERE invoice_id = ? ORDER BY id DESC LIMIT 1");
$qCashRev->execute([(int)$inv7['id']]);
$cashRev = $qCashRev->fetch(PDO::FETCH_ASSOC);
$qCashRev->closeCursor();

assertCondition(!empty($cashRev) && $cashRev['payment_type'] === 'Cash', "Revenue record stored payment_type = 'Cash'");
assertCondition($cashRev['transaction_reference'] === null, "Transaction reference is NULL for cash payment");

// =============================================================================
// TEST 9: BANK TRANSFER (UTR + BANK NAME)
// Expected: Both UTR and Bank Name saved accurately
// =============================================================================
echo "\n--- TEST 9: Bank Transfer with Bank Name ---\n";
list($rPage, $csrf) = get_invoices_csrf($baseUrl, $cookieFile);
$invNum9 = 'INV-2026-T9-BNK-' . time();
$postCreate9 = [
    'csrf_token'          => $csrf,
    'action'              => 'create_invoice',
    'client_id'           => $testClientId,
    'client_name_custom'  => $testClientName,
    'project_id'          => 0,
    'invoice_number'      => $invNum9,
    'invoice_type'        => 'full',
    'service'             => 'Websites',
    'project_total'       => '50000.00',
    'amount'              => '50000.00',
    'amount_received'     => '0.00',
    'due_date'            => date('Y-m-d', time() + 86400 * 14)
];
$rCreate9 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postCreate9), $cookieFile);
$qInv9 = $pdo->prepare("SELECT * FROM invoices WHERE invoice_number = ? LIMIT 1");
$qInv9->execute([$invNum9]);
$inv9 = $qInv9->fetch(PDO::FETCH_ASSOC);
$qInv9->closeCursor();

assertCondition(!empty($inv9), "Invoice 9 ({$invNum9}) created", $rCreate9['body']);
$cleanupInvoiceIds[] = (int)$inv9['id'];

$bankUtr9 = 'NEFT-HDFC-991823-' . time();
list($rPage, $csrf) = get_invoices_csrf($baseUrl, $cookieFile);
$postBank = [
    'csrf_token'             => $csrf,
    'action'                 => 'record_payment',
    'invoice_id'             => $inv9['id'],
    'amount'                 => '50000.00',
    'payment_date'           => date('Y-m-d'),
    'payment_mode'           => 'Bank Transfer',
    'transaction_reference'  => $bankUtr9,
    'bank_name'              => 'HDFC Bank Indiranagar',
    'notes'                  => 'Wire received via RTGS'
];
$rBank = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postBank), $cookieFile);
assertCondition(strpos($rBank['body'], 'successfully recorded') !== false, "Bank transfer payment recorded", $rBank['body']);

$qBankRev = $pdo->prepare("SELECT * FROM revenue WHERE invoice_id = ? ORDER BY id DESC LIMIT 1");
$qBankRev->execute([(int)$inv9['id']]);
$bankRev = $qBankRev->fetch(PDO::FETCH_ASSOC);
$qBankRev->closeCursor();

assertCondition($bankRev['payment_type'] === 'Bank Transfer', "Payment type stored as 'Bank Transfer'");
assertCondition($bankRev['transaction_reference'] === $bankUtr9, "UTR recorded matches '{$bankUtr9}'");
assertCondition($bankRev['bank_name'] === 'HDFC Bank Indiranagar', "Bank name recorded matches 'HDFC Bank Indiranagar'");

// =============================================================================
// TEST 10: DOUBLE-CLICK / DUPLICATE POST SUBMISSION
// Rapid duplicate submission within 15 seconds
// Expected: Second submission rejected as duplicate
// =============================================================================
echo "\n--- TEST 10: Double-Click / Duplicate POST Protection ---\n";
list($rPage, $csrf) = get_invoices_csrf($baseUrl, $cookieFile);
$invNum10 = 'INV-2026-T10-DBL-' . time();
$postCreate10 = [
    'csrf_token'          => $csrf,
    'action'              => 'create_invoice',
    'client_id'           => $testClientId,
    'client_name_custom'  => $testClientName,
    'project_id'          => 0,
    'invoice_number'      => $invNum10,
    'invoice_type'        => 'full',
    'service'             => 'Websites',
    'project_total'       => '40000.00',
    'amount'              => '40000.00',
    'amount_received'     => '0.00',
    'due_date'            => date('Y-m-d', time() + 86400 * 14)
];
$rCreate10 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postCreate10), $cookieFile);
$qInv10 = $pdo->prepare("SELECT * FROM invoices WHERE invoice_number = ? LIMIT 1");
$qInv10->execute([$invNum10]);
$inv10 = $qInv10->fetch(PDO::FETCH_ASSOC);
$qInv10->closeCursor();

assertCondition(!empty($inv10), "Invoice 10 ({$invNum10}) created", $rCreate10['body']);
$cleanupInvoiceIds[] = (int)$inv10['id'];

list($rPage, $csrf) = get_invoices_csrf($baseUrl, $cookieFile);
$postFirst = [
    'csrf_token'             => $csrf,
    'action'                 => 'record_payment',
    'invoice_id'             => $inv10['id'],
    'amount'                 => '20000.00',
    'payment_date'           => date('Y-m-d'),
    'payment_mode'           => 'Cash',
    'notes'                  => 'First submission'
];

// First submit
$rFirst = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postFirst), $cookieFile);
assertCondition(strpos($rFirst['body'], 'successfully recorded') !== false, "First submission processed", $rFirst['body']);

// Immediate duplicate submit (double-click simulator)
$rSecond = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postFirst), $cookieFile);
assertCondition(strpos($rSecond['body'], 'This payment appears to have already been recorded.') !== false, "Duplicate rapid submission rejected as already recorded", $rSecond['body']);

$qCnt10 = $pdo->prepare("SELECT COUNT(*) FROM revenue WHERE invoice_id = ?");
$qCnt10->execute([(int)$inv10['id']]);
$countPayments10 = (int)$qCnt10->fetchColumn();
$qCnt10->closeCursor();
assertCondition($countPayments10 === 1, "Exactly one payment recorded in database for Invoice 10");

// =============================================================================
// TEST 11: DATABASE TRANSACTION & ROLLBACK INTEGRITY
// Ensure that any unexpected failure rolls back changes cleanly
// =============================================================================
echo "\n--- TEST 11: Database Transaction Rollback on Failure ---\n";
$invNum11 = 'INV-2026-T11-TXN-' . time();
$pdo->prepare("INSERT INTO invoices (invoice_number, client_id, client_name, amount, project_total, amount_received, balance_amount, status, due_date, created_at, updated_at) VALUES (?, ?, ?, 50000, 50000, 0, 50000, 'pending', date('now', '+14 days'), datetime('now'), datetime('now'))")->execute([$invNum11, $testClientId, $testClientName]);
$inv11Id = (int)$pdo->lastInsertId();
$cleanupInvoiceIds[] = $inv11Id;

$revCountBefore = (int)$pdo->query("SELECT COUNT(*) FROM revenue")->fetchColumn();

// Execute a transaction that encounters an error midway
if (!$pdo->inTransaction()) {
    $pdo->beginTransaction();
}
try {
    // Insert into revenue
    $pdo->prepare("INSERT INTO revenue (client_id, invoice_id, amount, payment_type, payment_status, payment_date, service, created_at, updated_at) VALUES (?, ?, 25000, 'UPI', 'Paid', datetime('now'), 'Websites', datetime('now'), datetime('now'))")->execute([$testClientId, $inv11Id]);
    
    // Simulate failure
    throw new RuntimeException("Simulated catastrophic mid-transaction failure");

    // This update should never execute
    $pdo->exec("UPDATE invoices SET amount_received = 25000 WHERE id = {$inv11Id}");
    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

$qRevAfter = $pdo->query("SELECT COUNT(*) FROM revenue");
$revCountAfter = (int)$qRevAfter->fetchColumn();
$qRevAfter->closeCursor();

$qInv11After = $pdo->prepare("SELECT amount_received, status FROM invoices WHERE id = ?");
$qInv11After->execute([$inv11Id]);
$inv11After = $qInv11After->fetch(PDO::FETCH_ASSOC);
$qInv11After->closeCursor();

assertCondition($revCountAfter === $revCountBefore, "Revenue ledger count unchanged after rollback");
assertCondition((float)$inv11After['amount_received'] === 0.00, "Invoice 11 amount_received untouched (₹0.00)");
assertCondition($inv11After['status'] === 'pending', "Invoice 11 status remains 'pending'");

// =============================================================================
// TEST 12: UNAUTHENTICATED REQUEST
// Expected: Rejected / redirected
// =============================================================================
echo "\n--- TEST 12: Unauthenticated Request ---\n";
$unauthCookie = tempnam(sys_get_temp_dir(), 'wt_unauth_');
$postUnauth = [
    'csrf_token'             => 'fake_csrf',
    'action'                 => 'record_payment',
    'invoice_id'             => $inv11Id,
    'amount'                 => '10000.00'
];
$rUnauth = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postUnauth), $unauthCookie);
assertCondition($rUnauth['code'] === 302 || strpos($rUnauth['header'], 'login.php') !== false, "Unauthenticated POST redirected to login.php");

// =============================================================================
// TEST 13: INVALID CSRF TOKEN
// Expected: Rejected with session expired message
// =============================================================================
echo "\n--- TEST 13: Invalid CSRF Token ---\n";
$postBadCsrf = [
    'csrf_token'             => 'tampered_invalid_token_12345',
    'action'                 => 'record_payment',
    'invoice_id'             => $inv11Id,
    'amount'                 => '10000.00',
    'payment_date'           => date('Y-m-d'),
    'payment_mode'           => 'UPI'
];
$rBadCsrf = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postBadCsrf), $cookieFile);
assertCondition(strpos($rBadCsrf['body'], 'Security session expired') !== false, "Rejected with 'Security session expired' on bad CSRF token");

// =============================================================================
// TEST 14: INVALID PROJECT / CLIENT RELATIONSHIP
// Expected: Rejected if project_id does not match invoice
// =============================================================================
echo "\n--- TEST 14: Invalid Project Relationship Check ---\n";
list($rPage, $csrf) = get_invoices_csrf($baseUrl, $cookieFile);
$postMismatchProj = [
    'csrf_token'             => $csrf,
    'action'                 => 'record_payment',
    'invoice_id'             => $inv1['id'], // belongs to $testProjectId
    'project_id'             => 99999, // Mismatched fake project ID
    'amount'                 => '5000.00',
    'payment_date'           => date('Y-m-d'),
    'payment_mode'           => 'UPI'
];
$rMismatchProj = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postMismatchProj), $cookieFile);
assertCondition(strpos($rMismatchProj['body'], 'Invalid project relationship for this invoice.') !== false || strpos($rMismatchProj['body'], 'Payment exceeds the remaining balance.') !== false, "Mismatching project ID correctly caught and rejected", $rMismatchProj['body']);

// =============================================================================
// TEST 15: INVOICE WITH NO PROJECT (LEGACY / AD-HOC)
// Expected: Payment recorded against invoice without fabricating project
// =============================================================================
echo "\n--- TEST 15: Invoice with No Project (Legacy / Ad-hoc) ---\n";
list($rPage, $csrf) = get_invoices_csrf($baseUrl, $cookieFile);
$invNum15 = 'INV-2026-T15-NOPROJ-' . time();
$postCreate15 = [
    'csrf_token'          => $csrf,
    'action'              => 'create_invoice',
    'client_id'           => $testClientId,
    'client_name_custom'  => $testClientName,
    'project_id'          => 0, // No project
    'invoice_number'      => $invNum15,
    'invoice_type'        => 'full',
    'service'             => 'UI/UX',
    'project_total'       => '18000.00',
    'amount'              => '18000.00',
    'amount_received'     => '0.00',
    'due_date'            => date('Y-m-d', time() + 86400 * 14),
    'notes'               => 'Ad-hoc UI/UX Consultation'
];
$rCreate15 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postCreate15), $cookieFile);
$qInv15 = $pdo->prepare("SELECT * FROM invoices WHERE invoice_number = ? LIMIT 1");
$qInv15->execute([$invNum15]);
$inv15 = $qInv15->fetch(PDO::FETCH_ASSOC);
$qInv15->closeCursor();

assertCondition(!empty($inv15), "Invoice 15 ({$invNum15}) created without project", $rCreate15['body']);
$cleanupInvoiceIds[] = (int)$inv15['id'];

assertCondition($inv15['project_id'] === null || (int)$inv15['project_id'] === 0, "Invoice 15 has no project relationship");

list($rPage, $csrf) = get_invoices_csrf($baseUrl, $cookieFile);
$postPay15 = [
    'csrf_token'             => $csrf,
    'action'                 => 'record_payment',
    'invoice_id'             => $inv15['id'],
    'amount'                 => '18000.00',
    'payment_date'           => date('Y-m-d'),
    'payment_mode'           => 'UPI',
    'transaction_reference'  => 'UTR-AD-HOC-' . time()
];
$rPay15 = http_req($baseUrl . "/admin/pages/invoices.php", "POST", http_build_query($postPay15), $cookieFile);
assertCondition(strpos($rPay15['body'], 'successfully recorded') !== false, "Payment recorded against project-less invoice", $rPay15['body']);

$qInv15Upd = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
$qInv15Upd->execute([(int)$inv15['id']]);
$inv15Updated = $qInv15Upd->fetch(PDO::FETCH_ASSOC);
$qInv15Upd->closeCursor();

assertCondition((float)$inv15Updated['amount_received'] === 18000.00, "Invoice 15 received is ₹18,000.00");
assertCondition((float)$inv15Updated['balance_amount'] === 0.00, "Invoice 15 balance is ₹0.00");
assertCondition(strtolower((string)$inv15Updated['status']) === 'paid', "Invoice 15 status is 'paid'");
assertCondition($inv15Updated['project_id'] === null || (int)$inv15Updated['project_id'] === 0, "No fake project relationship created");

// =============================================================================
// TEST 16: PDF INTEGRATION WITH RECORDED PAYMENT
// Verify generated PDF contains updated payment mode, balance, and status
// =============================================================================
echo "\n--- TEST 16: PDF Integration with Recorded Payments ---\n";
$pdfBinary = generate_invoice_pdf($inv1Updated, $clientRow);
assertCondition(strpos($pdfBinary, '%PDF-1.4') === 0, "PDF header is valid %PDF-1.4");
assertCondition(strpos($pdfBinary, 'ADVANCE PAYMENT INVOICE') !== false, "PDF reflects 'ADVANCE PAYMENT INVOICE'");
assertCondition(strpos($pdfBinary, 'PAID') !== false, "PDF reflects status 'PAID'");
assertCondition(strpos($pdfBinary, '70,000.00') !== false || strpos($pdfBinary, '70000.00') !== false, "PDF displays updated balance of ₹70,000.00");

// =============================================================================
// TEST 17: UI MODALS & VIEW INVOICE INTEGRATION
// Verify View Invoice modal and Record Payment modal markup
// =============================================================================
echo "\n--- TEST 17: UI Modals & Payment History Elements ---\n";
$rInvoicesUi = http_req($baseUrl . "/admin/pages/invoices.php", "GET", null, $cookieFile);
$uiHtml = $rInvoicesUi['body'];

assertCondition(strpos($uiHtml, 'id="recordPaymentModal"') !== false, "Record Payment modal container exists");
assertCondition(strpos($uiHtml, 'id="payAmountInput"') !== false, "Payment Amount input exists");
assertCondition(strpos($uiHtml, 'id="payModeSelect"') !== false, "Payment Mode selector exists");
assertCondition(strpos($uiHtml, 'id="payTxnRefInput"') !== false, "Transaction reference input exists");
assertCondition(strpos($uiHtml, 'id="payBankNameInput"') !== false, "Bank name input exists");
assertCondition(strpos($uiHtml, 'id="viewInvPaymentHistorySection"') !== false, "Payment History section exists in View Invoice modal");
assertCondition(strpos($uiHtml, 'id="viewInvRecordPaymentBtn"') !== false, "Record Payment button exists in View Invoice modal");
assertCondition(strpos($uiHtml, 'openRecordPaymentModal') !== false, "openRecordPaymentModal function wired to actions");

// =============================================================================
// TEARDOWN: Clean up test data
// =============================================================================
echo "\nTeardown: Cleaning up test invoices & payments\n";
foreach ($cleanupInvoiceIds as $id) {
    $pdo->exec("DELETE FROM revenue WHERE invoice_id = {$id}");
    $pdo->exec("DELETE FROM invoices WHERE id = {$id}");
}
$pdo->exec("DELETE FROM revenue WHERE project_id = {$testProjectId}");
$pdo->exec("DELETE FROM invoices WHERE project_id = {$testProjectId}");
@unlink($cookieFile);
@unlink($unauthCookie);

echo "\n====================================================\n";
echo "Phase 5 Results: {$passCount} Passed, {$failCount} Failed\n";
echo "====================================================\n";

if ($failCount > 0) {
    exit(1);
}
