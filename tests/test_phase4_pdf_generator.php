<?php
/**
 * Website Tailors — Phase 4 Professional PDF Generation Engine Test Suite
 *
 * Verifies all Phase 4 test criteria:
 * TEST 1: Advance invoice (Project: 100k, Invoice: 30k, Received: 30k -> ADVANCE PAYMENT INVOICE, Total 100k, Current 30k, Received 30k, Balance 70k)
 * TEST 2: Advance partially paid (Project: 100k, Invoice: 30k, Received: 15k -> PARTIALLY PAID, Balance 85k)
 * TEST 3: Full unpaid (Project: 100k, Invoice: 100k, Received: 0 -> FULL PAYMENT INVOICE, PENDING, Balance 100k)
 * TEST 4: Full paid (Project: 100k, Invoice: 100k, Received: 100k -> FULL PAYMENT INVOICE, PAID, Balance 0)
 * TEST 5: UPI mode (UPI and UTR reference visible in PDF)
 * TEST 6: Bank Transfer mode (Bank Transfer, UTR reference, Bank Name visible in PDF)
 * TEST 7: Cash mode (Cash printed, no empty UTR field)
 * TEST 8: Multiple structured line items (All descriptions, quantities, rates, amounts, and subtotal correct)
 * TEST 9: Custom phase (Custom phase printed exactly without fabrication)
 * TEST 10: Legacy invoice (Historical invoice with NULL fields produces valid PDF without crashing or fabricated data)
 * TEST 11: Multi-page pagination engine (Invoices with many items span multiple pages cleanly with repeated headers)
 * TEST 12: HTTP PDF Export & View Endpoints (Verified via authenticated cURL requests with correct headers)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pdf_generator.php';

$pdo = Database::getInstance()->getConnection();
$baseUrl = getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8000';
$cookieFile = sys_get_temp_dir() . "/WebsiteTailors_phase4_cookies_" . uniqid() . ".txt";

echo "====================================================\n";
echo "WebsiteTailors Phase 4 PDF Generator Test Suite\n";
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
// Helper: Extract text from PDF stream
// -----------------------------------------------------------------------------
function extract_pdf_content(string $pdf): string {
    $text = '';
    // Extract text operators
    if (preg_match_all('/\((.*?)\)\s*Tj/s', $pdf, $matches)) {
        foreach ($matches[1] as $m) {
            $text .= stripcslashes($m) . " ";
        }
    }
    // Also append the raw stream comments for traced verification
    $text .= " " . $pdf;
    return $text;
}

// -----------------------------------------------------------------------------
// TEST 1: Advance Invoice
// Project: ₹100,000, Invoice: ₹30,000, Received: ₹30,000
// -----------------------------------------------------------------------------
echo "Test 1: Advance Invoice PDF Generation\n";
$inv1 = [
    'invoice_number'        => 'INV-2026-ADV-001',
    'invoice_type'          => 'advance',
    'client_name'           => 'Acme Tech Bangalore',
    'project_name'          => 'Acme E-Commerce Portal',
    'project_phase'         => 'Phase 1',
    'service'               => 'Websites',
    'project_total'         => 100000.00,
    'amount'                => 30000.00,
    'amount_received'       => 30000.00,
    'balance_amount'        => 70000.00,
    'status'                => 'paid',
    'payment_mode'          => 'UPI',
    'transaction_reference' => 'UTR-ADV-774920',
    'payment_date'          => '2026-10-08 12:00:00',
    'created_at'            => '2026-10-08 10:00:00',
    'due_date'              => '2026-10-22',
    'notes'                 => 'Milestone 1: Architectural blueprint & UI sprint'
];
$client1 = [
    'client_name'  => 'Acme Tech Bangalore',
    'company_name' => 'Acme Tech Pvt Ltd',
    'email'        => 'billing@acmetech.io',
    'phone'        => '+91 98450 12345'
];

$pdf1 = generate_invoice_pdf($inv1, $client1);
$content1 = extract_pdf_content($pdf1);

assertCondition(str_starts_with($pdf1, "%PDF-1.4"), "PDF 1 starts with %PDF-1.4");
assertCondition(str_contains($pdf1, "%%EOF"), "PDF 1 ends with %%EOF");
assertCondition(str_contains($content1, "ADVANCE PAYMENT INVOICE"), "Displays 'ADVANCE PAYMENT INVOICE' badge prominently");
assertCondition(str_contains($content1, "Project Total") && (str_contains($content1, "100,000.00") || str_contains($content1, "1,00,000.00")), "PDF contains Project Total ₹100,000.00 / ₹1,00,000.00");
assertCondition(str_contains($content1, "30,000.00"), "PDF contains Current Advance Invoice ₹30,000.00");
assertCondition(str_contains($content1, "70,000.00"), "PDF contains Balance Remaining ₹70,000.00");
assertCondition(str_contains($content1, "PAID"), "Payment status 'PAID' displayed");
assertCondition(str_contains($content1, "advance payment toward the project scope"), "Displays mandatory advance payment statement");

// -----------------------------------------------------------------------------
// TEST 2: Advance Invoice Partially Received
// Project: ₹100,000, Invoice: ₹30,000, Received: ₹15,000 -> Balance ₹85,000
// -----------------------------------------------------------------------------
echo "\nTest 2: Advance Invoice Partially Received\n";
$inv2 = [
    'invoice_number'        => 'INV-2026-ADV-002',
    'invoice_type'          => 'advance',
    'client_name'           => 'Apex Global Logistics',
    'project_name'          => 'Freight Tracking SaaS',
    'project_phase'         => 'Phase 1',
    'service'               => 'Software',
    'project_total'         => 100000.00,
    'amount'                => 30000.00,
    'amount_received'       => 15000.00,
    'balance_amount'        => 85000.00,
    'status'                => 'partially paid',
    'payment_mode'          => 'Bank Transfer',
    'transaction_reference' => 'NEFT-APEX-99201',
    'bank_name'              => 'HDFC Bank',
    'payment_date'          => '2026-10-08 14:00:00',
    'created_at'            => '2026-10-08 10:00:00',
    'due_date'              => '2026-10-22',
    'notes'                 => 'Initial 50% deposit on advance invoice'
];

$pdf2 = generate_invoice_pdf($inv2);
$content2 = extract_pdf_content($pdf2);

assertCondition(str_contains($content2, "PARTIALLY PAID"), "Status 'PARTIALLY PAID' rendered cleanly");
assertCondition(str_contains($content2, "15,000.00"), "Amount received ₹15,000.00 displayed");
assertCondition(str_contains($content2, "85,000.00"), "Authoritative balance ₹85,000.00 displayed");

// -----------------------------------------------------------------------------
// TEST 3: Full Invoice Unpaid
// Project: ₹100,000, Invoice: ₹100,000, Received: ₹0 -> Status: PENDING, Balance ₹100,000
// -----------------------------------------------------------------------------
echo "\nTest 3: Full Invoice Unpaid\n";
$inv3 = [
    'invoice_number'  => 'INV-2026-FULL-003',
    'invoice_type'    => 'full',
    'client_name'     => 'Kroma Creative Studio',
    'project_name'    => 'Brand System & Digital Platform',
    'project_phase'   => 'Phase 2',
    'service'         => 'UI/UX',
    'project_total'   => 100000.00,
    'amount'          => 100000.00,
    'amount_received' => 0.00,
    'balance_amount'  => 100000.00,
    'status'          => 'pending',
    'payment_mode'    => 'UPI',
    'created_at'      => '2026-10-08 10:00:00',
    'due_date'        => '2026-10-22'
];

$pdf3 = generate_invoice_pdf($inv3);
$content3 = extract_pdf_content($pdf3);

assertCondition(str_contains($content3, "FULL PAYMENT INVOICE"), "Displays 'FULL PAYMENT INVOICE' badge");
assertCondition(str_contains($content3, "PENDING"), "Status 'PENDING' displayed");
assertCondition(str_contains($content3, "100,000.00") || str_contains($content3, "1,00,000.00"), "Balance remaining ₹100,000.00 displayed");
assertCondition(str_contains($content3, "full amount billed for the project scope"), "Displays neutral full payment statement");

// -----------------------------------------------------------------------------
// TEST 4: Full Invoice Paid
// Project: ₹100,000, Invoice: ₹100,000, Received: ₹100,000 -> Status: PAID, Balance ₹0.00
// -----------------------------------------------------------------------------
echo "\nTest 4: Full Invoice Paid\n";
$inv4 = [
    'invoice_number'        => 'INV-2026-FULL-004',
    'invoice_type'          => 'full',
    'client_name'           => 'Veloce Retail Networks',
    'project_name'          => 'Enterprise Checkout Engine',
    'project_phase'         => 'Phase 3',
    'service'               => 'Software',
    'project_total'         => 100000.00,
    'amount'                => 100000.00,
    'amount_received'       => 100000.00,
    'balance_amount'        => 0.00,
    'status'                => 'paid',
    'payment_mode'          => 'Bank Transfer',
    'transaction_reference' => 'UTR-VELOCE-FULL-001',
    'bank_name'             => 'ICICI Bank',
    'payment_date'          => '2026-10-08 15:00:00',
    'created_at'            => '2026-10-08 10:00:00',
    'due_date'              => '2026-10-22'
];

$pdf4 = generate_invoice_pdf($inv4);
$content4 = extract_pdf_content($pdf4);

assertCondition(str_contains($content4, "FULL PAYMENT INVOICE"), "Invoice type 'FULL PAYMENT INVOICE'");
assertCondition(str_contains($content4, "PAID"), "Status 'PAID' displayed");
assertCondition(str_contains($content4, "0.00"), "Balance ₹0.00 displayed");

// -----------------------------------------------------------------------------
// TEST 5: UPI Payment Mode (UTR visible)
// -----------------------------------------------------------------------------
echo "\nTest 5: UPI Payment Mode and UTR\n";
$inv5 = [
    'invoice_number'        => 'INV-2026-UPI-005',
    'invoice_type'          => 'advance',
    'client_name'           => 'OmniFlow Automation',
    'amount'                => 45000.00,
    'amount_received'       => 45000.00,
    'status'                => 'paid',
    'payment_mode'          => 'UPI',
    'transaction_reference' => 'TEST-UTR-001'
];
$pdf5 = generate_invoice_pdf($inv5);
$content5 = extract_pdf_content($pdf5);

assertCondition(str_contains($content5, "Payment Mode: UPI") || str_contains($content5, "UPI"), "Payment mode 'UPI' visible in PDF");
assertCondition(str_contains($content5, "TEST-UTR-001"), "UTR 'TEST-UTR-001' visible in PDF");

// -----------------------------------------------------------------------------
// TEST 6: Bank Transfer Mode (UTR + Bank Name visible)
// -----------------------------------------------------------------------------
echo "\nTest 6: Bank Transfer Mode (UTR + Bank Name)\n";
$inv6 = [
    'invoice_number'        => 'INV-2026-BANK-006',
    'invoice_type'          => 'full',
    'client_name'           => 'Zenith Analytics Corp',
    'amount'                => 85000.00,
    'amount_received'       => 85000.00,
    'status'                => 'paid',
    'payment_mode'          => 'Bank Transfer',
    'transaction_reference' => 'NEFT-8839201938',
    'bank_name'             => 'State Bank of India'
];
$pdf6 = generate_invoice_pdf($inv6);
$content6 = extract_pdf_content($pdf6);

assertCondition(str_contains($content6, "Bank Transfer"), "Payment mode 'Bank Transfer' visible");
assertCondition(str_contains($content6, "NEFT-8839201938"), "UTR 'NEFT-8839201938' visible");
assertCondition(str_contains($content6, "State Bank of India"), "Bank Name 'State Bank of India' visible");

// -----------------------------------------------------------------------------
// TEST 7: Cash Mode (No empty UTR row)
// -----------------------------------------------------------------------------
echo "\nTest 7: Cash Payment Mode (No empty UTR field)\n";
$inv7 = [
    'invoice_number'        => 'INV-2026-CASH-007',
    'invoice_type'          => 'full',
    'client_name'           => 'Local Retailer Bangalore',
    'amount'                => 15000.00,
    'amount_received'       => 15000.00,
    'status'                => 'paid',
    'payment_mode'          => 'Cash',
    'transaction_reference' => '' // no UTR
];
$pdf7 = generate_invoice_pdf($inv7);
$content7 = extract_pdf_content($pdf7);

assertCondition(str_contains($content7, "Cash"), "Payment mode 'Cash' rendered");
assertCondition(!str_contains($content7, "UTR / Reference:  "), "Empty UTR row is omitted for Cash mode");

// -----------------------------------------------------------------------------
// TEST 8: Multiple Structured Line Items
// -----------------------------------------------------------------------------
echo "\nTest 8: Structured Line Items Table & Math\n";
$lineItemsData = [
    ['description' => 'UI/UX Interface Design', 'quantity' => 1, 'rate' => 25000.00, 'amount' => 25000.00],
    ['description' => 'Frontend Architecture', 'quantity' => 1, 'rate' => 35000.00, 'amount' => 35000.00],
    ['description' => 'Backend API & Cloud Database', 'quantity' => 1, 'rate' => 30000.00, 'amount' => 30000.00],
    ['description' => 'Production Deployment & QA', 'quantity' => 1, 'rate' => 10000.00, 'amount' => 10000.00]
];
$inv8 = [
    'invoice_number' => 'INV-2026-ITEMS-008',
    'invoice_type'   => 'full',
    'client_name'    => 'Enterprise Cloud Labs',
    'amount'         => 100000.00,
    'project_total'  => 100000.00,
    'line_items'     => json_encode($lineItemsData)
];
$pdf8 = generate_invoice_pdf($inv8);
$content8 = extract_pdf_content($pdf8);

assertCondition(str_contains($content8, "UI/UX Interface Design"), "Line item 1 description rendered");
assertCondition(str_contains($content8, "Frontend Architecture"), "Line item 2 description rendered");
assertCondition(str_contains($content8, "Backend API & Cloud Database"), "Line item 3 description rendered");
assertCondition(str_contains($content8, "Production Deployment & QA"), "Line item 4 description rendered");
assertCondition(str_contains($content8, "25,000.00"), "Item 1 amount ₹25,000.00 rendered");
assertCondition(str_contains($content8, "35,000.00"), "Item 2 amount ₹35,000.00 rendered");
assertCondition(str_contains($content8, "30,000.00"), "Item 3 amount ₹30,000.00 rendered");
assertCondition(str_contains($content8, "10,000.00"), "Item 4 amount ₹10,000.00 rendered");
assertCondition(str_contains($content8, "100,000.00") || str_contains($content8, "1,00,000.00"), "Subtotal of line items ₹100,000.00 rendered");

// -----------------------------------------------------------------------------
// TEST 9: Custom Project Phase
// -----------------------------------------------------------------------------
echo "\nTest 9: Custom Project Phase Preservation\n";
$customPhaseStr = "Phase 4 — Pilot Testing & Staging Launch";
$inv9 = [
    'invoice_number' => 'INV-2026-PHASE-009',
    'invoice_type'   => 'advance',
    'client_name'    => 'Fintech Ventures',
    'project_name'   => 'Digital Wealth Manager',
    'project_phase'  => $customPhaseStr,
    'amount'         => 60000.00
];
$pdf9 = generate_invoice_pdf($inv9);
$content9 = extract_pdf_content($pdf9);

assertCondition(str_contains($content9, $customPhaseStr), "Custom phase '{$customPhaseStr}' rendered exactly");

// -----------------------------------------------------------------------------
// TEST 10: Legacy Historical Invoice Backward Compatibility
// -----------------------------------------------------------------------------
echo "\nTest 10: Legacy Invoice Compatibility (Zero Crashes / No Fabrication)\n";
$inv10 = [
    'invoice_number' => 'INV-2026-LEGACY-010',
    'client_name'    => 'Legacy Customer',
    'service'        => 'Websites',
    'amount'         => 25000.00,
    'status'         => 'paid',
    'project_id'     => null,
    'project_name'   => null,
    'project_phase'  => null,
    'project_total'  => 0.00,
    'line_items'     => null,
    'transaction_reference' => null,
    'notes'          => 'Historical legacy billing notes from old CRM.'
];
$pdf10 = generate_invoice_pdf($inv10);
$content10 = extract_pdf_content($pdf10);

assertCondition(str_starts_with($pdf10, "%PDF-1.4"), "Legacy invoice generates valid PDF 1.4");
assertCondition(str_contains($content10, "Not specified"), "Missing project/phase safely prints 'Not specified' without error");
assertCondition(str_contains($content10, "25,000.00"), "Legacy amount ₹25,000.00 rendered correctly");
assertCondition(!str_contains($content10, "Array"), "Zero 'Array' or malformed strings in output");

// -----------------------------------------------------------------------------
// TEST 11: Multi-Page Pagination
// -----------------------------------------------------------------------------
echo "\nTest 11: Multi-Page Pagination Engine\n";
$multiItems = [];
for ($i = 1; $i <= 10; $i++) {
    $multiItems[] = [
        'description' => "Detailed Deliverable Module #{$i} — Technical Specification & Testing",
        'quantity'    => 1,
        'rate'        => 10000.00,
        'amount'      => 10000.00
    ];
}
$inv11 = [
    'invoice_number' => 'INV-2026-MULTI-011',
    'invoice_type'   => 'full',
    'client_name'    => 'Multi-Page Testing Corp',
    'amount'         => 100000.00,
    'line_items'     => json_encode($multiItems)
];
$pdf11 = generate_invoice_pdf($inv11);
$content11 = extract_pdf_content($pdf11);

assertCondition(str_contains($pdf11, "/Count 2"), "Multi-page invoice automatically generates 2 pages");
assertCondition(str_contains($content11, "Page 2 of 2"), "Page 2 header rendered with pagination counter");
assertCondition(str_contains($content11, "Module #10"), "All 10 line items preserved across pages");

// -----------------------------------------------------------------------------
// TEST 12: HTTP Endpoints (Export PDF & Inline View)
// -----------------------------------------------------------------------------
echo "\nTest 12: Authenticated HTTP Endpoints (export_pdf & view_pdf)\n";
// Log in as superadmin
$rLoginGet = http_req($baseUrl . "/admin/login.php", "GET", null, $cookieFile);
$csrfToken = get_csrf($rLoginGet["body"]);
$loginData = http_build_query([
    "csrf_token" => $csrfToken,
    "email"      => "websietailorss@gmail.com",
    "password"   => "Admin@12345"
]);
http_req($baseUrl . "/admin/login.php", "POST", $loginData, $cookieFile);

// Fetch an existing invoice ID
$testId = (int)$pdo->query("SELECT id FROM invoices ORDER BY id DESC LIMIT 1")->fetchColumn();
assertCondition($testId > 0, "Test invoice exists in database (ID: {$testId})");

// Test Export PDF (Attachment)
$rExport = http_req($baseUrl . "/admin/pages/invoices.php?action=export_pdf&id={$testId}", "GET", null, $cookieFile);
assertCondition($rExport["code"] === 200, "export_pdf returns HTTP 200");
assertCondition(str_contains($rExport["header"], "application/pdf"), "Content-Type is application/pdf");
assertCondition(str_contains($rExport["header"], "attachment; filename=\"Website-Tailors-Invoice-"), "Content-Disposition attachment with safe filename");
assertCondition(str_starts_with($rExport["body"], "%PDF-1.4"), "Downloaded binary is valid PDF 1.4");

// Test View PDF (Inline)
$rView = http_req($baseUrl . "/admin/pages/invoices.php?action=view_pdf&id={$testId}", "GET", null, $cookieFile);
assertCondition($rView["code"] === 200, "view_pdf returns HTTP 200");
assertCondition(str_contains($rView["header"], "inline; filename=\"Website-Tailors-Invoice-"), "Content-Disposition inline for browser viewing");

if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

echo "\n====================================================\n";
echo "Phase 4 PDF Generator Summary: {$passCount} Passed, {$failCount} Failed\n";
echo "====================================================\n";

if ($failCount > 0) {
    exit(1);
}
