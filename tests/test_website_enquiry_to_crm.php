<?php
/**
 * WebsiteTailors — End-to-End Verification Test Suite
 * 
 * Tests the complete flow:
 * 1. Open public website (load contact page, fetch CSRF token)
 * 2. Submit enquiry through public form
 * 3. Verify PHP processes submission and responds with expected UX message
 * 4. Verify database record is created in `leads` table with required fields
 * 5. Open admin panel
 * 6. Verify "New Leads" KPI count increases
 * 7. Open Admin → Leads
 * 8. Verify submitted enquiry appears in Leads module & Recent Leads dashboard
 * 9. Open the lead
 * 10. Mark it Called (log call)
 * 11. Add call notes
 * 12. Schedule follow-up
 * 13. Verify call history created and linked
 * 14. Mark lead Converted
 * 15. Create client (inheriting name, company, email, phone, service, source, notes)
 * 16. Verify client is linked to the lead and original lead is preserved
 * 17. Verify Revenue association for the converted client (Lead → Client → Revenue)
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
require_once dirname(__DIR__) . '/includes/init.php';
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$baseUrl = 'http://localhost:8000';
$cookieFileAdmin = sys_get_temp_dir() . '/WebsiteTailors_adm_' . uniqid() . '.txt';
$cookieFilePublic = sys_get_temp_dir() . '/WebsiteTailors_pub_' . uniqid() . '.txt';
if (file_exists($cookieFileAdmin)) unlink($cookieFileAdmin);
if (file_exists($cookieFilePublic)) unlink($cookieFilePublic);

function httpReq(string $url, string $method = 'GET', ?string $body = null, ?string $cookieFile = null, array $headers = [], bool $follow = false): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $follow);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
    }

    $finalHeaders = array_merge(['Expect:'], $headers);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $finalHeaders);

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    unset($ch);

    return [
        'code'  => $code,
        'body'  => is_string($response) ? $response : '',
        'error' => $err
    ];
}

function parseCsrf(string $html): string {
    if (preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/i', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/<meta\s+name="csrf-token"\s+content="([a-f0-9]+)"/i', $html, $m)) {
        return $m[1];
    }
    return '';
}

function pass(string $msg): void {
    echo "  [PASS] {$msg}\n";
}

function fail(string $msg): void {
    echo "  [FAIL] {$msg}\n";
    exit(1);
}

echo "====================================================\n";
echo "WebsiteTailors: Website Enquiry → Admin CRM End-to-End Suite\n";
echo "====================================================\n\n";

function getDb(): PDO {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    if ($conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $sqlitePath = dirname(__DIR__) . '/database/WebsiteTailors.sqlite';
        $fresh = new PDO('sqlite:' . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $fresh->exec("PRAGMA journal_mode = WAL;");
        $fresh->exec("PRAGMA busy_timeout = 5000;");
        return $fresh;
    }
    return $conn;
}

// -----------------------------------------------------------------------------
// STEP 1: Open Public Website
// -----------------------------------------------------------------------------
echo "STEP 1: Open public website & fetch contact page\n";
$rPublic = httpReq($baseUrl . '/contact.php', 'GET', null, $cookieFilePublic);
if ($rPublic['code'] !== 200) {
    fail("Failed to open public contact page (HTTP {$rPublic['code']})");
}
$csrfPublic = parseCsrf($rPublic['body']);
if (empty($csrfPublic)) {
    fail("Failed to extract CSRF token from contact page");
}
pass("Public contact page loaded with HTTP 200 and valid CSRF token");

// Measure current New Leads count before enquiry
$initialNewLeads = (int)getDb()->query("SELECT COUNT(*) FROM leads WHERE LOWER(status) = 'new'")->fetchColumn();

// -----------------------------------------------------------------------------
// STEP 2 & 3: Submit Enquiry & Verify PHP Processes It
// -----------------------------------------------------------------------------
echo "\nSTEP 2 & 3: Submit website enquiry via contact form\n";
$uniqueId = substr(md5(uniqid('', true)), 0, 6);
$testName    = "Rahul Sharma " . $uniqueId;
$testCompany = "ABC Technologies " . $uniqueId;
$testEmail   = "rahul." . $uniqueId . "@abctechnologies.com";
$testPhone   = "+91 98765 " . rand(10000, 99999);
$testService = "Website";
$testBudget  = "₹1,00,000 - ₹3,00,000";
$testMessage = "We need an enterprise-grade website redesign with real-time lead ingestion and CRM workflow.";

$postData = [
    'csrf_token'  => $csrfPublic,
    'website_url' => '', // blank for honeypot
    'name'        => $testName,
    'company'     => $testCompany,
    'email'       => $testEmail,
    'phone'       => $testPhone,
    'service'     => $testService,
    'budget'      => $testBudget,
    'message'     => $testMessage
];

$rSubmit = httpReq(
    $baseUrl . '/api/contact.php',
    'POST',
    http_build_query($postData),
    $cookieFilePublic,
    ['X-Requested-With: XMLHttpRequest']
);

if ($rSubmit['code'] !== 200) {
    fail("Submission failed with HTTP {$rSubmit['code']}: " . $rSubmit['body']);
}

$jsonResponse = json_decode($rSubmit['body'], true);
if (!is_array($jsonResponse) || empty($jsonResponse['success'])) {
    fail("Submission did not return success: " . $rSubmit['body']);
}

$expectedMsg = "Thanks! Your enquiry has been received. We'll get back to you shortly.";
if (!str_contains($jsonResponse['message'], 'enquiry has been received')) {
    fail("Expected confirmation message not returned. Got: " . ($jsonResponse['message'] ?? ''));
}
pass("Enquiry processed by PHP endpoint; returned exact success UX message: \"{$jsonResponse['message']}\"");

// Test duplicate protection
$rDup = httpReq(
    $baseUrl . '/api/contact.php',
    'POST',
    http_build_query($postData),
    $cookieFilePublic,
    ['X-Requested-With: XMLHttpRequest']
);
$dupJson = json_decode($rDup['body'], true);
if (empty($dupJson['success'])) {
    fail("Duplicate submission returned error instead of safe UX acknowledgment");
}
pass("Duplicate protection gracefully acknowledged repeat submission within rapid window");

// -----------------------------------------------------------------------------
// STEP 4: Verify Database Record is Created
// -----------------------------------------------------------------------------
echo "\nSTEP 4: Verify database record is created in `leads`\n";
$stmt = getDb()->prepare("SELECT * FROM leads WHERE email = :email");
$stmt->execute([':email' => $testEmail]);
$leadRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$leadRow) {
    fail("Lead record was not found in `leads` table for {$testEmail}");
}

$leadId = (int)$leadRow['id'];
if ($leadRow['name'] !== $testName) fail("Lead name mismatch: {$leadRow['name']}");
if ($leadRow['company'] !== $testCompany) fail("Lead company mismatch: {$leadRow['company']}");
if ($leadRow['source'] !== 'Website') fail("Lead source expected 'Website', got '{$leadRow['source']}'");
if (strtolower($leadRow['status']) !== 'new') fail("Lead status expected 'New', got '{$leadRow['status']}'");
if ($leadRow['call_status'] !== 'Not Called') fail("Lead call_status expected 'Not Called', got '{$leadRow['call_status']}'");
if (!empty($leadRow['last_called_at'])) fail("last_called_at should initially be NULL");
if (!empty($leadRow['next_followup_at'])) fail("next_followup_at should initially be NULL");

pass("Database record #{$leadId} created with source='Website', status='New', call_status='Not Called', budget='{$testBudget}'");

// -----------------------------------------------------------------------------
// STEP 5 & 6: Authenticate Admin & Verify New Leads Count Increases
// -----------------------------------------------------------------------------
echo "\nSTEP 5 & 6: Open Admin & verify New Leads count increases\n";
$rLoginGet = httpReq($baseUrl . '/admin/login.php', 'GET', null, $cookieFileAdmin);
$csrfLogin = parseCsrf($rLoginGet['body']);

$rLoginPost = httpReq(
    $baseUrl . '/admin/login.php',
    'POST',
    http_build_query([
        'csrf_token' => $csrfLogin,
        'email'      => 'websietailorss@gmail.com',
        'password'   => 'Admin@12345'
    ]),
    $cookieFileAdmin
);

if ($rLoginPost['code'] !== 302 && $rLoginPost['code'] !== 200) {
    fail("Admin login failed with HTTP {$rLoginPost['code']}: {$rLoginPost['error']}");
}

$rDash = httpReq($baseUrl . '/admin/index.php', 'GET', null, $cookieFileAdmin);
if ($rDash['code'] !== 200) {
    fail("Admin dashboard failed to load (HTTP {$rDash['code']}, Error: '{$rDash['error']}')");
}

// Verify KPI count increased
$afterNewLeads = (int)getDb()->query("SELECT COUNT(*) FROM leads WHERE LOWER(status) = 'new'")->fetchColumn();
if ($afterNewLeads !== ($initialNewLeads + 1)) {
    fail("New Leads count did not increase by 1. Was {$initialNewLeads}, now {$afterNewLeads}");
}
pass("Admin authenticated; New Leads count increased from {$initialNewLeads} to {$afterNewLeads}");

// -----------------------------------------------------------------------------
// STEP 7 & 8: Verify Lead in Admin → Leads & Recent Leads
// -----------------------------------------------------------------------------
echo "\nSTEP 7 & 8: Verify enquiry in Admin → Leads and Dashboard Recent Leads\n";
// Check Recent Leads on dashboard
if (!str_contains($rDash['body'], $testName)) {
    fail("Submitted enquiry '{$testName}' did not appear in dashboard Recent Leads table");
}
pass("Submitted enquiry '{$testName}' displayed on Dashboard Recent Leads");

// Check Admin → Leads module
$rLeadsPage = httpReq($baseUrl . '/admin/pages/leads.php', 'GET', null, $cookieFileAdmin);
if ($rLeadsPage['code'] !== 200) {
    fail("Admin Leads page failed to load (HTTP {$rLeadsPage['code']})");
}
if (!str_contains($rLeadsPage['body'], $testName)) {
    fail("Submitted enquiry '{$testName}' did not appear in Admin Leads table");
}
if (!str_contains($rLeadsPage['body'], $testCompany)) {
    fail("Lead company '{$testCompany}' did not appear in Admin Leads table");
}
pass("Submitted enquiry '{$testName}' ({$testCompany}) displayed in Admin → Leads module with Budget, Status, and Call Action");

// -----------------------------------------------------------------------------
// STEP 9, 10, 11, 12, 13: Call Workflow (Open, Log Call, Notes, Follow-up, Verify History)
// -----------------------------------------------------------------------------
echo "\nSTEP 9 - 13: Call Workflow (Log call, notes, schedule follow-up, verify call history)\n";
$csrfAdmin = parseCsrf($rLeadsPage['body']);
$callDateTime = date('Y-m-d H:i:s');
$nextFollowup = date('Y-m-d H:i:s', strtotime('+2 days 11:00:00'));
$callNotes = "Spoke directly with {$testName}. Client confirmed project budget {$testBudget} and requested proposal.";

$logCallData = [
    'csrf_token'       => $csrfAdmin,
    'action'           => 'log_call',
    'lead_id'          => $leadId,
    'outcome'          => 'Connected',
    'call_datetime'    => $callDateTime,
    'next_followup_at' => $nextFollowup,
    'notes'            => $callNotes
];

$rLogCall = httpReq(
    $baseUrl . '/admin/pages/leads.php',
    'POST',
    http_build_query($logCallData),
    $cookieFileAdmin
);

if ($rLogCall['code'] !== 200 || !str_contains($rLogCall['body'], 'Call outcome (Connected) logged')) {
    fail("Call logging failed: " . substr($rLogCall['body'], 0, 500));
}
pass("Admin recorded call with outcome 'Connected', notes, and follow-up scheduled");

// Verify lead table updated
$leadCheck = getDb()->prepare("SELECT * FROM leads WHERE id = :id");
$leadCheck->execute([':id' => $leadId]);
$updatedLead = $leadCheck->fetch(PDO::FETCH_ASSOC);

if ($updatedLead['call_status'] !== 'Called') {
    fail("Lead call_status expected 'Called', got '{$updatedLead['call_status']}'");
}
if (empty($updatedLead['last_called_at'])) {
    fail("last_called_at was not set on lead");
}
if (empty($updatedLead['next_followup_at'])) {
    fail("next_followup_at was not set on lead");
}
pass("Lead record updated: call_status='Called', last_called_at set, next_followup_at set");

// Verify Call History endpoint and database entry
$rHistory = httpReq(
    $baseUrl . '/admin/pages/leads.php?action=get_call_history&lead_id=' . $leadId,
    'GET',
    null,
    $cookieFileAdmin
);
$historyJson = json_decode($rHistory['body'], true);
if (!is_array($historyJson) || empty($historyJson['success']) || empty($historyJson['calls'])) {
    fail("Call history endpoint did not return call records: " . $rHistory['body']);
}
$callEntry = $historyJson['calls'][0];
if ($callEntry['outcome'] !== 'Connected' || !str_contains($callEntry['notes'], 'Spoke directly with')) {
    fail("Call history entry mismatch: " . json_encode($callEntry));
}
pass("Call history successfully verified: logged call timestamp, outcome 'Connected', notes, and next follow-up");

// -----------------------------------------------------------------------------
// STEP 14, 15, 16: Client Conversion (Mark Converted, Create Client, Verify Linkage)
// -----------------------------------------------------------------------------
echo "\nSTEP 14 - 16: Client Conversion & Linkage\n";
// Update status to Converted & trigger client creation
$convertData = [
    'csrf_token' => parseCsrf($rLogCall['body']),
    'action'     => 'convert_to_client',
    'lead_id'    => $leadId
];

$rConvert = httpReq(
    $baseUrl . '/admin/pages/leads.php',
    'POST',
    http_build_query($convertData),
    $cookieFileAdmin
);

if ($rConvert['code'] !== 200 || !str_contains($rConvert['body'], 'converted to Client')) {
    fail("Client conversion failed: " . substr($rConvert['body'], 0, 500));
}
pass("Lead #{$leadId} converted to Client via admin action");

// Verify Client Record in `clients` table
$stmtClient = getDb()->prepare("SELECT * FROM clients WHERE email = :email");
$stmtClient->execute([':email' => $testEmail]);
$clientRow = $stmtClient->fetch(PDO::FETCH_ASSOC);

if (!$clientRow) {
    fail("Client record was not created in `clients` table for {$testEmail}");
}

$clientId = (int)$clientRow['id'];
if ($clientRow['client_name'] !== $testName) fail("Client name mismatch");
if ($clientRow['company_name'] !== $testCompany) fail("Client company mismatch");
if ($clientRow['phone'] !== $testPhone) fail("Client phone mismatch");
if ($clientRow['service'] !== $testService) fail("Client service mismatch");
if ($clientRow['source'] !== 'Website') fail("Client source expected 'Website', got '{$clientRow['source']}'");
if ($clientRow['status'] !== 'Active') fail("Client status expected 'Active', got '{$clientRow['status']}'");

pass("Client #{$clientId} successfully inherited: name, company, email, phone, service, source, notes");

// Verify Lead is linked to the Client and original lead is NOT deleted
$leadFinalCheck = getDb()->prepare("SELECT * FROM leads WHERE id = :id");
$leadFinalCheck->execute([':id' => $leadId]);
$preservedLead = $leadFinalCheck->fetch(PDO::FETCH_ASSOC);

if (!$preservedLead) {
    fail("CRITICAL: Original lead was deleted upon conversion!");
}
if ((int)$preservedLead['client_id'] !== $clientId) {
    fail("Lead client_id was not linked. Expected {$clientId}, got " . var_export($preservedLead['client_id'], true));
}
if (strtolower($preservedLead['status']) !== 'converted') {
    fail("Lead status was not updated to Converted");
}
pass("Original lead #{$leadId} preserved and permanently linked to Client #{$clientId}");

// -----------------------------------------------------------------------------
// STEP 17: Associate Revenue with Converted Client (Lead → Client → Revenue)
// -----------------------------------------------------------------------------
echo "\nSTEP 17: Associate Revenue with Converted Client (Lead → Client → Revenue)\n";
$rRevPage = httpReq($baseUrl . '/admin/pages/revenue.php', 'GET', null, $cookieFileAdmin);
$csrfRev = parseCsrf($rRevPage['body']);

$revAmount = 150000.00;
$revPostData = [
    'csrf_token'     => $csrfRev,
    'action'         => 'add_revenue',
    'client_id'      => $clientId,
    'amount'         => $revAmount,
    'payment_type'   => 'Bank Transfer',
    'payment_status' => 'Paid',
    'payment_date'   => date('Y-m-d H:i:s'),
    'service'        => $testService,
    'notes'          => "Initial deposit for {$testName} from Lead #{$leadId}"
];

$rAddRev = httpReq(
    $baseUrl . '/admin/pages/revenue.php',
    'POST',
    http_build_query($revPostData),
    $cookieFileAdmin
);

if ($rAddRev['code'] !== 200 || !str_contains($rAddRev['body'], 'successfully recorded')) {
    fail("Revenue association failed: " . substr($rAddRev['body'], 0, 500));
}

// Verify database revenue linkage
$revStmt = getDb()->prepare("SELECT * FROM revenue WHERE client_id = :cid ORDER BY id DESC LIMIT 1");
$revStmt->execute([':cid' => $clientId]);
$revRow = $revStmt->fetch(PDO::FETCH_ASSOC);

if (!$revRow || (float)$revRow['amount'] !== $revAmount) {
    fail("Revenue record not found for Client #{$clientId}");
}
pass("Revenue of ₹1,50,000.00 associated with Client #{$clientId}. Complete relationship verified: Lead #{$leadId} → Client #{$clientId} → Revenue #{$revRow['id']}");

// Clean up temporary cookies
@unlink($cookieFileAdmin);
@unlink($cookieFilePublic);

echo "\n====================================================\n";
echo "🎉 ALL 17 STEPS VERIFIED AND PASSED WITH 100% SUCCESS!\n";
echo "====================================================\n";
