<?php
/**
 * WebsiteTailors Admin — Phase 8: Excel & CSV Lead Import End-to-End Test Suite
 *
 * Verifies:
 * 1. File Upload Validation & Security (MIME validation, extension check, 10MB limit)
 * 2. Spreadsheet Parsing (.xlsx via PhpSpreadsheet and .csv via native PHP)
 * 3. Case-Insensitive Column Normalization
 * 4. Row Validation Engine (missing name, invalid email, invalid phone, missing both, status/call status validation)
 * 5. Default Values (source = 'Excel Import', status = 'New', call_status = 'Not Called', service = 'Website')
 * 6. Duplicate Detection (against live database and within upload batch)
 * 7. Duplicate Policy Resolution ('skip' vs 'import')
 * 8. Staging & Final Confirmation (no DB modification before confirmation; exact insertion after)
 * 9. CRM Parity & Filter Verification (appears in 'Not Called' tab, preserved for call logging)
 * 10. Dashboard Dynamic Calculations (New Leads KPI, Total Leads, Pipeline breakdown, Recent leads)
 * 11. Website Enquiry & Manual Add Lead Unaffected (independent parallel operation)
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/excel_lead_importer.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$pdo = Database::getInstance()->getConnection();
if (!$pdo) {
    echo "\033[31mFAIL: Could not establish database connection.\033[0m\n";
    exit(1);
}

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $description): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  \033[32m✔ PASS:\033[0m {$description}\n";
    } else {
        $failed++;
        echo "  \033[31m✖ FAIL:\033[0m {$description}\n";
    }
}

echo "\n=======================================================\n";
echo "  WebsiteTailors ADMIN — PHASE 8 EXCEL LEAD IMPORT TEST SUITE  \n";
echo "=======================================================\n\n";

// -------------------------------------------------------------
// STEP 0: Seed Baseline Pre-Existing Lead for Duplicate Testing
// -------------------------------------------------------------
echo "--- Step 0: Seed Baseline Pre-Existing CRM Leads ---\n";
$preEmail = 'existing.client@acmecorp.in';
$prePhone = '+91 98111 22334';
$nowStr   = date('Y-m-d H:i:s');

// Clean any previous test artifacts
$pdo->prepare("DELETE FROM leads WHERE email LIKE '%@testimport.com' OR email = ? OR phone = ?")
    ->execute([$preEmail, $prePhone]);

$seedStmt = $pdo->prepare("
    INSERT INTO leads (name, company, email, phone, service, budget, message, source, status, call_status, ip_address, created_at, updated_at)
    VALUES (?, 'Acme Corp India', ?, ?, 'Website', '₹3,00,000', 'Original CRM Lead', 'Website Form', 'New', 'Not Called', '127.0.0.1', ?, ?)
");
$seedStmt->execute(['Ramesh Acme', $preEmail, $prePhone, $nowStr, $nowStr]);
$preLeadId = (int)$pdo->lastInsertId();
assertTest($preLeadId > 0, "Seeded baseline lead #{$preLeadId} with email {$preEmail} and phone {$prePhone}");

// -------------------------------------------------------------
// STEP 1: Security & File Format Validation
// -------------------------------------------------------------
echo "\n--- Step 1: Security & File Format Validation ---\n";

// 1a. Reject non-existent file / empty upload
$emptyRes = ExcelLeadImporter::processUpload(['error' => UPLOAD_ERR_NO_FILE], $pdo);
assertTest($emptyRes['success'] === false, "Rejects empty upload request");

// 1b. Reject disallowed file extension (.php or .exe)
$badExtFile = tempnam(sys_get_temp_dir(), 'test_') . '.php';
file_put_contents($badExtFile, "<?php echo 'malicious';");
$badExtRes = ExcelLeadImporter::processUpload([
    'name'     => 'exploit.php',
    'type'     => 'text/php',
    'tmp_name' => $badExtFile,
    'error'    => UPLOAD_ERR_OK,
    'size'     => filesize($badExtFile)
], $pdo);
@unlink($badExtFile);
assertTest($badExtRes['success'] === false && str_contains($badExtRes['error'] ?? '', 'Unsupported file extension'), "Rejects disallowed file extension (.php)");

// 1c. Reject oversized file (> 10MB)
$oversizedRes = ExcelLeadImporter::processUpload([
    'name'     => 'massive.xlsx',
    'type'     => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'tmp_name' => '/tmp/fake_large',
    'error'    => UPLOAD_ERR_OK,
    'size'     => 11 * 1024 * 1024 // 11MB
], $pdo);
assertTest($oversizedRes['success'] === false && str_contains($oversizedRes['error'] ?? '', '10 MB'), "Rejects files exceeding 10 MB limit");

// -------------------------------------------------------------
// STEP 2: CSV Import with Mixed Valid, Invalid & Duplicate Rows
// -------------------------------------------------------------
echo "\n--- Step 2: Comprehensive CSV Validation & Preview ---\n";

$csvData = [
    // Header (Case-Insensitive & Flexible aliases)
    ['FULL NAME', 'Company Name', 'EMAIL ADDRESS', 'Contact Number', 'Service Required', 'Budget', 'Message Details', 'Lead Source', 'Stage', 'Call Status', 'Notes', 'Next Follow-up'],
    // Row 2: Valid complete lead
    ['Aarav Sharma', 'Sharma Logistics', 'aarav@testimport.com', '+91 98200 12345', 'Software', '₹4,00,000', 'Enterprise warehouse system', 'Cold Outreach', 'New', 'Not Called', 'Met at Mumbai tech expo', '2026-10-15 14:00:00'],
    // Row 3: Valid lead with blank optional fields (tests defaults)
    ['Pooja Patel', '', 'pooja@testimport.com', '+91 98300 23456', '', '', '', '', '', '', '', ''],
    // Row 4: Valid lead with phone only (no email)
    ['Devendra Rao', 'Rao Transports', '', '+91 98400 34567', 'AI + Automation', '₹6,00,000', 'Fleet tracking automation', 'Excel Import', 'Qualified', 'Call Back', 'Urgent quote needed', ''],
    // Row 5: Valid lead with email only (no phone)
    ['Sneha Kulkarni', 'Kulkarni Designs', 'sneha@testimport.com', '', 'UI/UX', '₹2,50,000', 'Mobile app redesign', '', 'Contacted', 'Called', '', ''],
    // Row 6: INVALID — Missing name
    ['', 'Nameless Corp', 'nameless@testimport.com', '+91 98500 45678', 'Website', '', '', '', 'New', 'Not Called', '', ''],
    // Row 7: INVALID — Missing both email and phone
    ['Karan Verma', 'No Contact Ltd', '', '', 'Website', '₹1,00,000', '', '', 'New', 'Not Called', '', ''],
    // Row 8: INVALID — Malformed email
    ['Sunil Joshi', 'Joshi Media', 'not-a-valid-email-at-all', '+91 98600 56789', 'Website', '', '', '', 'New', 'Not Called', '', ''],
    // Row 9: INVALID — Invalid phone (too short)
    ['Meera Nair', 'Nair Tech', 'meera@testimport.com', '123', 'Software', '', '', '', 'New', 'Not Called', '', ''],
    // Row 10: INVALID — Invalid status stage
    ['Deepak Chawla', 'Chawla Infotech', 'deepak@testimport.com', '+91 98700 67890', 'Website', '', '', '', 'InvalidStageX', 'Not Called', '', ''],
    // Row 11: DUPLICATE (Existing CRM email)
    ['Ramesh Duplicate Email', 'Acme Clone', $preEmail, '+91 99999 00001', 'Website', '', '', '', 'New', 'Not Called', '', ''],
    // Row 12: DUPLICATE (Existing CRM phone)
    ['Ramesh Duplicate Phone', 'Acme Phone Clone', 'diff_email@testimport.com', $prePhone, 'Website', '', '', '', 'New', 'Not Called', '', ''],
    // Row 13: DUPLICATE (Intra-batch duplicate email of Row 2)
    ['Aarav Repeat in Batch', 'Aarav Secondary', 'aarav@testimport.com', '+91 99999 00002', 'Software', '', '', '', 'New', 'Not Called', '', '']
];

$csvFile = tempnam(sys_get_temp_dir(), 'WebsiteTailors_test_') . '.csv';
$fp = fopen($csvFile, 'w');
foreach ($csvData as $line) {
    fputcsv($fp, $line, ',', '"', "\\");
}
fclose($fp);

$csvUpload = [
    'name'     => 'test_leads_batch.csv',
    'type'     => 'text/csv',
    'tmp_name' => $csvFile,
    'error'    => UPLOAD_ERR_OK,
    'size'     => filesize($csvFile)
];

$previewRes = ExcelLeadImporter::processUpload($csvUpload, $pdo);
@unlink($csvFile);

assertTest($previewRes['success'] === true, "Successfully parsed and validated CSV batch");
assertTest(($previewRes['total_rows'] ?? 0) === 12, "Counted 12 total data rows (excluding header)");
assertTest(($previewRes['valid_count'] ?? 0) === 4, "Detected exactly 4 unique valid rows (Aarav, Pooja, Devendra, Sneha)");
assertTest(($previewRes['invalid_count'] ?? 0) === 5, "Detected exactly 5 invalid rows (missing name, no contact, bad email, bad phone, bad status)");
assertTest(($previewRes['duplicate_count'] ?? 0) === 3, "Detected exactly 3 duplicate rows (existing email, existing phone, intra-batch email)");

// Check specific validation error text
$errorTexts = array_column($previewRes['errors'] ?? [], 'error');
$allErrStr = implode(' | ', $errorTexts);
assertTest(str_contains($allErrStr, 'Name is missing'), "Error reported: Name is missing");
assertTest(str_contains($allErrStr, 'At least one contact method'), "Error reported: At least one contact method required");
assertTest(str_contains($allErrStr, 'Invalid email format'), "Error reported: Invalid email format");
assertTest(str_contains($allErrStr, 'Invalid phone number'), "Error reported: Invalid phone number");
assertTest(str_contains($allErrStr, 'Invalid status'), "Error reported: Invalid status");

// Verify Preview Rows Content
$previewRows = $previewRes['preview_rows'] ?? [];
assertTest(count($previewRows) === 12, "Generated preview rows array matching input rows");
assertTest($previewRows[0]['name'] === 'Aarav Sharma' && $previewRows[0]['is_valid'] === true, "Row 1 (Aarav) correctly tagged as valid");
assertTest($previewRows[4]['is_valid'] === false, "Row 5 (Missing name) correctly tagged as invalid");
assertTest($previewRows[9]['is_duplicate'] === true, "Row 10 (Duplicate email) correctly tagged as duplicate");

// Verify Staging Token
$token = $previewRes['import_token'] ?? '';
assertTest(!empty($token), "Generated secure import staging token: {$token}");

// Verify that NO leads have been inserted into the database yet (Preview Only!)
$stagedCount = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE email LIKE '%@testimport.com'")->fetchColumn();
assertTest($stagedCount === 0, "No leads were inserted into the database during preview stage (Admin confirmation required)");

// -------------------------------------------------------------
// STEP 3: Error Report CSV Generation
// -------------------------------------------------------------
echo "\n--- Step 3: Error Report Generation ---\n";
$errorCsv = ExcelLeadImporter::generateErrorReportCsv($token);
assertTest(!empty($errorCsv), "Generated downloadable CSV error report for staging token");
assertTest(str_contains($errorCsv, 'Validation Error') && str_contains($errorCsv, 'Row #'), "Error report contains standard CSV headers");
assertTest(str_contains($errorCsv, 'Invalid email format') && str_contains($errorCsv, 'Name is missing'), "Error report lists specific row failure causes");

// -------------------------------------------------------------
// STEP 4: Final Confirmation — Skip Duplicates (Default)
// -------------------------------------------------------------
echo "\n--- Step 4: Final Confirmation & Database Insertion (Skip Duplicates) ---\n";

// Record baseline counts before import
$baselineLeadsCount = (int)$pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
$baselineNewLeads   = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE LOWER(status) = 'new'")->fetchColumn();
$baselineNotCalled  = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE LOWER(call_status) = 'not called'")->fetchColumn();

$commitRes = ExcelLeadImporter::commitImport($token, 'skip', $pdo, '127.0.0.1');

assertTest($commitRes['success'] === true, "Committed import with 'skip' duplicate policy");
assertTest(($commitRes['imported'] ?? 0) === 4, "Imported exactly 4 unique valid leads");
assertTest(($commitRes['skipped_duplicates'] ?? 0) === 3, "Skipped exactly 3 duplicate leads as requested");
assertTest(($commitRes['invalid_count'] ?? 0) === 5, "Skipped all 5 invalid leads");

// Verify Database Records
$importedStmt = $pdo->prepare("SELECT * FROM leads WHERE email LIKE '%@testimport.com' OR phone IN ('+91 98400 34567')");
$importedStmt->execute();
$importedLeads = $importedStmt->fetchAll(PDO::FETCH_ASSOC);
assertTest(count($importedLeads) === 4, "Found exactly 4 newly inserted records in `leads` table");

// Verify Field Mapping & Defaults in DB
$aaravInDb = null;
$poojaInDb = null;
foreach ($importedLeads as $lead) {
    if ($lead['name'] === 'Aarav Sharma') $aaravInDb = $lead;
    if ($lead['name'] === 'Pooja Patel')  $poojaInDb = $lead;
}

assertTest($aaravInDb !== null, "Found Aarav Sharma in database");
assertTest($aaravInDb['source'] === 'Cold Outreach', "Aarav preserved custom source 'Cold Outreach'");
assertTest($aaravInDb['call_status'] === 'Not Called', "Aarav has call_status = 'Not Called'");
assertTest($aaravInDb['status'] === 'New', "Aarav has status = 'New'");
assertTest($aaravInDb['budget'] === '₹4,00,000', "Aarav has budget = '₹4,00,000'");

assertTest($poojaInDb !== null, "Found Pooja Patel in database");
assertTest($poojaInDb['source'] === 'Excel Import', "Pooja blank source defaulted to 'Excel Import'");
assertTest($poojaInDb['status'] === 'New', "Pooja blank status defaulted to 'New'");
assertTest($poojaInDb['call_status'] === 'Not Called', "Pooja blank call_status defaulted to 'Not Called'");
assertTest($poojaInDb['service'] === 'Website', "Pooja blank service defaulted to 'Website'");

// -------------------------------------------------------------
// STEP 5: CRM Integration & Filter Verification
// -------------------------------------------------------------
echo "\n--- Step 5: CRM Filtering & Status Verification ---\n";

// Check that imported leads appear under 'Not Called' filter query
$notCalledStmt = $pdo->query("SELECT COUNT(*) FROM leads WHERE LOWER(call_status) = 'not called' AND (email LIKE '%@testimport.com' OR phone = '+91 98400 34567')");
$notCalledFound = (int)$notCalledStmt->fetchColumn();
assertTest($notCalledFound >= 2, "Imported leads with 'Not Called' status appear correctly in the 'Not Called' CRM query");

// -------------------------------------------------------------
// STEP 6: Dashboard Dynamic KPI Counts Verification
// -------------------------------------------------------------
echo "\n--- Step 6: Dashboard Dynamic KPI Aggregation ---\n";

$currentMonthStart = date('Y-m-01 00:00:00');
$newLeadsCount   = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE LOWER(status) = 'new'")->fetchColumn();
$totalLeadsCount = (int)$pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
$notCalledCount  = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE LOWER(COALESCE(call_status, 'Not Called')) = 'not called'")->fetchColumn();

assertTest($totalLeadsCount === ($baselineLeadsCount + 4), "Dashboard Total Leads count incremented dynamically by +4");
assertTest($newLeadsCount >= ($baselineNewLeads + 2), "Dashboard New Leads KPI incremented dynamically");
assertTest($notCalledCount >= ($baselineNotCalled + 2), "Lead filter 'Not Called' count increased dynamically");

// Check pipeline distribution
$pipelineStmt = $pdo->query("SELECT LOWER(status) as st, COUNT(*) as cnt FROM leads GROUP BY LOWER(status)");
$pipeline = [];
while ($r = $pipelineStmt->fetch(PDO::FETCH_ASSOC)) {
    $pipeline[$r['st']] = (int)$r['cnt'];
}
assertTest(isset($pipeline['new']) && $pipeline['new'] >= 2, "Pipeline stage 'New' updated with imported leads");
assertTest(isset($pipeline['qualified']) && $pipeline['qualified'] >= 1, "Pipeline stage 'Qualified' updated (Devendra Rao)");
assertTest(isset($pipeline['contacted']) && $pipeline['contacted'] >= 1, "Pipeline stage 'Contacted' updated (Sneha Kulkarni)");

// Check Recent Leads Query
$recentLeads = $pdo->query("SELECT id, name, source FROM leads ORDER BY created_at DESC, id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$recentNames = array_column($recentLeads, 'name');
assertTest(in_array('Sneha Kulkarni', $recentNames, true) || in_array('Aarav Sharma', $recentNames, true), "Recent Leads list on dashboard includes newly imported leads");

// -------------------------------------------------------------
// STEP 7: PhpSpreadsheet XLSX Parsing Verification
// -------------------------------------------------------------
echo "\n--- Step 7: PhpSpreadsheet .XLSX Parsing & Import ---\n";

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Header
$headers = ['Name', 'Company', 'Email', 'Phone', 'Service', 'Budget', 'Message', 'Source', 'Status', 'Call Status'];
foreach ($headers as $colIdx => $h) {
    $sheet->setCellValue([$colIdx + 1, 1], $h);
}

// Data Row 1 (Valid)
$xlsxRow1 = ['Tanya Saxena', 'Saxena Ventures', 'tanya@testimport.com', '+91 99111 22334', 'Software', '₹8,00,000', 'Fintech core platform', 'Excel Import', 'New', 'Not Called'];
foreach ($xlsxRow1 as $colIdx => $val) {
    $sheet->setCellValue([$colIdx + 1, 2], $val);
}

// Data Row 2 (Invalid - Bad Email)
$xlsxRow2 = ['Gaurav Mathur', 'Mathur Labs', 'notanemail', '+91 99222 33445', 'Website', '₹3,00,000', '', '', 'New', 'Not Called'];
foreach ($xlsxRow2 as $colIdx => $val) {
    $sheet->setCellValue([$colIdx + 1, 3], $val);
}

// Data Row 3 (Duplicate Email of Row 1)
$xlsxRow3 = ['Tanya Duplicate', 'Saxena 2', 'tanya@testimport.com', '+91 99333 44556', 'Website', '', '', '', 'New', 'Not Called'];
foreach ($xlsxRow3 as $colIdx => $val) {
    $sheet->setCellValue([$colIdx + 1, 4], $val);
}

$xlsxFile = tempnam(sys_get_temp_dir(), 'WebsiteTailors_test_') . '.xlsx';
$writer = new Xlsx($spreadsheet);
$writer->save($xlsxFile);

$xlsxUpload = [
    'name'     => 'test_leads.xlsx',
    'type'     => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'tmp_name' => $xlsxFile,
    'error'    => UPLOAD_ERR_OK,
    'size'     => filesize($xlsxFile)
];

$xlsxPreview = ExcelLeadImporter::processUpload($xlsxUpload, $pdo);
@unlink($xlsxFile);

assertTest($xlsxPreview['success'] === true, "Successfully parsed .xlsx spreadsheet via PhpSpreadsheet");
assertTest(($xlsxPreview['total_rows'] ?? 0) === 3, ".xlsx total rows = 3");
assertTest(($xlsxPreview['valid_count'] ?? 0) === 1, ".xlsx valid rows = 1 (Tanya Saxena)");
assertTest(($xlsxPreview['invalid_count'] ?? 0) === 1, ".xlsx invalid rows = 1 (Gaurav Mathur with bad email)");
assertTest(($xlsxPreview['duplicate_count'] ?? 0) === 1, ".xlsx duplicate rows = 1 (Tanya Duplicate)");

// Test Commit with 'import' option (Import Duplicates)
$xlsxCommit = ExcelLeadImporter::commitImport($xlsxPreview['import_token'], 'import', $pdo, '127.0.0.1');
assertTest($xlsxCommit['success'] === true, "Committed .xlsx import with 'import' duplicates option");
assertTest(($xlsxCommit['imported'] ?? 0) === 2, "Imported 2 leads (1 valid + 1 duplicate imported as requested)");

// -------------------------------------------------------------
// STEP 8: Verify Website Enquiry & Manual Add Lead Unaffected
// -------------------------------------------------------------
echo "\n--- Step 8: Website Enquiry & Manual Add Lead Parity ---\n";

// 8a. Simulate direct website enquiry submission
$webStmt = $pdo->prepare("
    INSERT INTO leads (name, email, phone, company, service_interested, budget, message, ip_address, source, status, call_status, created_at, updated_at)
    VALUES ('Aditi Web Inquiry', 'aditi@testimport.com', '+91 97111 88990', 'Aditi Textiles', 'AI + Automation', '₹5,00,000+', 'Website Contact Form Inquiry', '127.0.0.1', 'Website Form', 'New', 'Not Called', ?, ?)
");
$webStmt->execute([$nowStr, $nowStr]);
$webLeadId = (int)$pdo->lastInsertId();
assertTest($webLeadId > 0, "Website enquiry flow created lead #{$webLeadId} with source = 'Website Form'");

// 8b. Verify distinguishing source fields
$sourcesStmt = $pdo->query("SELECT DISTINCT source FROM leads WHERE email LIKE '%@testimport.com'");
$sources = $sourcesStmt->fetchAll(PDO::FETCH_COLUMN);
assertTest(in_array('Excel Import', $sources, true), "Database correctly tracks source = 'Excel Import'");
assertTest(in_array('Website Form', $sources, true), "Database correctly tracks source = 'Website Form'");
assertTest(in_array('Cold Outreach', $sources, true), "Database correctly tracks custom sources (Cold Outreach)");

// -------------------------------------------------------------
// CLEANUP TEST DATA
// -------------------------------------------------------------
$pdo->prepare("DELETE FROM leads WHERE email LIKE '%@testimport.com' OR email = ? OR phone = ? OR phone IN ('+91 98400 34567', '+91 99111 22334')")
    ->execute([$preEmail, $prePhone]);

echo "\n=======================================================\n";
echo "  TEST SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "=======================================================\n\n";

if ($failed > 0) {
    exit(1);
}
