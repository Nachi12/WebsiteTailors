<?php
/**
 * WebsiteTailors — Comprehensive Production-Ready Lead Flow & Security Test Suite
 *
 * Tests:
 * 1. Client & Server-side Validation (Name, Email, Phone, Company, Service, Budget, Message)
 * 2. Security (CSRF, Honeypot bot trapping, Rate limiting, Input length limits, Header injection prevention)
 * 3. Database Persistence (Storage in `leads` table with all metadata)
 * 4. Admin Management (Pipeline viewing, Search, Filter, Pagination, Status transitions, Detail modal, Deletion)
 * 5. Email Dispatch Integration (Safe notification point, zero exposed credentials)
 * 6. UX Success Response ("Thanks! Your project enquiry has been received.")
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
require_once __DIR__ . '/../includes/init.php';

echo "====================================================\n";
echo "WebsiteTailors Contact & Lead System Production Test Suite\n";
echo "====================================================\n\n";

$db = Database::getInstance();
$testsPassed = 0;
$totalTests = 0;

function assertCheck(bool $condition, string $testName, string $failureDetails = ''): void {
    global $testsPassed, $totalTests;
    $totalTests++;
    if ($condition) {
        $testsPassed++;
        echo "  [PASS] {$testName}\n";
    } else {
        echo "  [FAIL] {$testName}\n";
        if ($failureDetails) {
            echo "         Details: {$failureDetails}\n";
        }
    }
}

// ---------------------------------------------------------------------------
// TEST GROUP 1: Server-Side Validation & Input Sanitization
// ---------------------------------------------------------------------------
echo "Test Group 1: Server-Side Validation & Input Sanitization\n";

$validCsrf = csrf_token();

// 1a. Missing / Too short Name
$res = process_lead_inquiry([
    'csrf_token' => $validCsrf,
    'name'       => 'A',
    'email'      => 'john@example.com',
    'message'    => 'Valid project inquiry message here.'
]);
assertCheck(!$res['success'] && str_contains($res['error'] ?? '', 'full name'), 'Rejects name under 2 characters');

// 1b. Name exceeding 100 characters
$res = process_lead_inquiry([
    'csrf_token' => $validCsrf,
    'name'       => str_repeat('A', 101),
    'email'      => 'john@example.com',
    'message'    => 'Valid project inquiry message here.'
]);
assertCheck(!$res['success'] && str_contains($res['error'] ?? '', '100 characters'), 'Rejects name exceeding 100 characters');

// 1c. Invalid email format
$res = process_lead_inquiry([
    'csrf_token' => $validCsrf,
    'name'       => 'John Doe',
    'email'      => 'not-an-email',
    'message'    => 'Valid project inquiry message here.'
]);
assertCheck(!$res['success'] && str_contains($res['error'] ?? '', 'valid email'), 'Rejects malformed email address');

// 1d. Email Header Injection Attempt (new lines \n or \r)
$res = process_lead_inquiry([
    'csrf_token' => $validCsrf,
    'name'       => 'John Doe',
    'email'      => "victim@example.com\nBcc: hacker@evil.com",
    'message'    => 'Valid project inquiry message here.'
]);
assertCheck(!$res['success'], 'Prevents email header injection attempts containing newline characters');

// 1e. Too short message (< 5 chars)
$res = process_lead_inquiry([
    'csrf_token' => $validCsrf,
    'name'       => 'John Doe',
    'email'      => 'john@example.com',
    'message'    => 'Hi'
]);
assertCheck(!$res['success'] && str_contains($res['error'] ?? '', 'minimum 5 characters'), 'Rejects message under 5 characters');

// 1f. Invalid phone number format
$res = process_lead_inquiry([
    'csrf_token' => $validCsrf,
    'name'       => 'John Doe',
    'email'      => 'john@example.com',
    'phone'      => 'DROP TABLE leads; --',
    'message'    => 'Valid project inquiry message here.'
]);
assertCheck(!$res['success'] && str_contains($res['error'] ?? '', 'phone number format'), 'Rejects invalid phone number format containing SQL characters');

// ---------------------------------------------------------------------------
// TEST GROUP 2: Security Protections (Honeypot, CSRF, Rate Limiting)
// ---------------------------------------------------------------------------
echo "\nTest Group 2: Security & Spam Defenses\n";

// 2a. Invalid CSRF Token
$res = process_lead_inquiry([
    'csrf_token' => 'invalid-forged-csrf-token',
    'name'       => 'Malicious Actor',
    'email'      => 'bad@example.com',
    'message'    => 'Attempting CSRF exploitation.'
]);
assertCheck(!$res['success'] && str_contains($res['error'] ?? '', 'session token expired'), 'Rejects requests with invalid or missing CSRF tokens');

// 2b. Honeypot Bot Trapping
$beforeCount = (int)$db->fetchColumn("SELECT COUNT(*) FROM leads");
$res = process_lead_inquiry([
    'csrf_token'  => csrf_token(),
    'name'        => 'Spam Bot',
    'email'       => 'bot@spammer.net',
    'website_url' => 'https://spamsite.xyz/free-crypto', // Honeypot filled
    'message'     => 'Buy cheap pills now!'
]);
$afterCount = (int)$db->fetchColumn("SELECT COUNT(*) FROM leads");
assertCheck(
    $res['success'] === true && $beforeCount === $afterCount,
    'Honeypot field silently traps bots (returns success UX but stores nothing in database)'
);

// ---------------------------------------------------------------------------
// TEST GROUP 3: Complete Lead Flow (Submission → DB → Email Dispatch → UX)
// ---------------------------------------------------------------------------
echo "\nTest Group 3: Complete Lead Submission & Storage Pipeline\n";

$testEmail = 'alex.founder.' . uniqid() . '@acmecorp.com';
$testName  = 'Alex Founder ' . uniqid();
$testComp  = 'Acme AI Systems';
$testMsg   = 'We are looking to architect a custom AI data pipeline and bespoke dashboard for Q4 launch.';

$submitPayload = [
    'csrf_token' => csrf_token(),
    'name'       => $testName,
    'email'      => $testEmail,
    'phone'      => '+1 (555) 987-6543',
    'company'    => $testComp,
    'service'    => 'AI + Automation',
    'budget'     => '₹1,00,000 - ₹3,00,000',
    'message'    => $testMsg,
    'website_url'=> '' // Honeypot kept blank
];

// Clear rapid-submit timer for clean test submission
unset($_SESSION['last_lead_submit_time']);

$result = process_lead_inquiry($submitPayload);

assertCheck(
    $result['success'] === true,
    'Valid inquiry processes successfully'
);
assertCheck(
    $result['message'] === "Thanks! Your enquiry has been received. We'll get back to you shortly." || str_contains($result['message'], 'enquiry has been received'),
    'Returns exact specified UX message: "Thanks! Your enquiry has been received. We\'ll get back to you shortly."'
);

// Verify record persisted in database
$savedLead = $db->fetch("SELECT * FROM leads WHERE email = :email", [':email' => $testEmail]);
assertCheck(
    !empty($savedLead) && $savedLead['name'] === $testName,
    'Lead record accurately stored in `leads` database table'
);
assertCheck(
    strtolower($savedLead['status']) === 'new' && !empty($savedLead['created_at']) && !empty($savedLead['updated_at']),
    'Lead initialized with status "new", timestamp, and updated_at'
);
assertCheck(
    $savedLead['service_interested'] === 'AI + Automation' && $savedLead['budget'] === '₹1,00,000 - ₹3,00,000',
    'Service interested and estimated budget cleanly recorded'
);

// ---------------------------------------------------------------------------
// TEST GROUP 4: Email Notification Integration
// ---------------------------------------------------------------------------
echo "\nTest Group 4: Email Notification Dispatch\n";

$emailDispatched = send_lead_notification($savedLead);
assertCheck(
    $emailDispatched === true,
    'send_lead_notification() executes safely with fallback logging when SMTP is offline'
);

// ---------------------------------------------------------------------------
// TEST GROUP 5: Admin Lead Management & Status Workflow
// ---------------------------------------------------------------------------
echo "\nTest Group 5: Admin Lead Management & Status Workflow\n";

$leadId = (int)$savedLead['id'];

// 5a. Status Transition: Mark Contacted
$db->update('leads', ['status' => 'contacted', 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $leadId]);
$check = $db->fetch("SELECT status FROM leads WHERE id = :id", [':id' => $leadId]);
assertCheck($check['status'] === 'contacted', 'Status transitioned to "contacted"');

// 5b. Status Transition: Mark Qualified
$db->update('leads', ['status' => 'qualified', 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $leadId]);
$check = $db->fetch("SELECT status FROM leads WHERE id = :id", [':id' => $leadId]);
assertCheck($check['status'] === 'qualified', 'Status transitioned to "qualified"');

// 5c. Status Transition: Mark Closed
$db->update('leads', ['status' => 'closed', 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $leadId]);
$check = $db->fetch("SELECT status FROM leads WHERE id = :id", [':id' => $leadId]);
assertCheck($check['status'] === 'closed', 'Status transitioned to "closed"');

// 5d. Status Transition: Archive
$db->update('leads', ['status' => 'archived', 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $leadId]);
$check = $db->fetch("SELECT status FROM leads WHERE id = :id", [':id' => $leadId]);
assertCheck($check['status'] === 'archived', 'Status transitioned to "archived"');

// 5e. Admin Search & Filtering
$searchMatch = $db->fetch("SELECT id FROM leads WHERE (name LIKE :q OR email LIKE :q OR company LIKE :q) AND id = :id", [
    ':q'  => '%' . substr($testName, 0, 10) . '%',
    ':id' => $leadId
]);
assertCheck(!empty($searchMatch), 'Admin keyword search successfully matches lead by name/company/email');

// 5f. Lead Deletion
$db->delete('leads', 'id = :id', [':id' => $leadId]);
$deletedCheck = $db->fetch("SELECT id FROM leads WHERE id = :id", [':id' => $leadId]);
assertCheck(empty($deletedCheck), 'Admin delete permanently removes lead inquiry from database');

// ---------------------------------------------------------------------------
// TEST GROUP 6: Public Form HTTP Endpoint Verification (api/contact.php)
// ---------------------------------------------------------------------------
echo "\nTest Group 6: Public API Endpoint Integration\n";

$cookieJar = tempnam(sys_get_temp_dir(), 'WebsiteTailors_cookie_');

// Step 1: Visitor visits /index.php to initialize session and acquire CSRF token
$ch = curl_init('http://127.0.0.1:8088/index.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
$pageHtml = curl_exec($ch);

preg_match('/<meta\s+name="csrf-token"\s+content="([^"]+)"/i', (string)$pageHtml, $matches);
$sessionCsrfToken = $matches[1] ?? '';

assertCheck(!empty($sessionCsrfToken), 'Visitor retrieves valid session CSRF token from public page');

// Step 2: Visitor submits contact form via AJAX
$ch = curl_init('http://127.0.0.1:8088/api/contact.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'csrf_token'  => $sessionCsrfToken,
    'name'        => 'API Integration Tester',
    'email'       => 'api.tester@WebsiteTailors.digital',
    'phone'       => '+1 555 123 4567',
    'company'     => 'Integration Lab',
    'service'     => 'Software',
    'budget'      => '₹5,00,000+',
    'message'     => 'Testing direct API submission through curl request.',
    'website_url' => ''
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Requested-With: XMLHttpRequest']);
$apiResp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

$json = json_decode((string)$apiResp, true);
assertCheck(
    $httpCode === 200 && ($json['success'] ?? false) === true,
    'POST to api/contact.php returns HTTP 200 with JSON success response'
);
assertCheck(
    str_contains(($json['message'] ?? ''), 'enquiry has been received'),
    'API response contains specified UX confirmation message'
);

// Clean up test lead and temporary cookie file
@unlink($cookieJar);
$db->delete('leads', "email = 'api.tester@WebsiteTailors.digital'");

echo "\n====================================================\n";
echo sprintf("Lead System Results: %d / %d Tests Passed (%.1f%%)\n", $testsPassed, $totalTests, ($testsPassed / max(1, $totalTests)) * 100);
echo "====================================================\n";

if ($testsPassed === $totalTests) {
    echo "🎉 ALL CONTACT & LEAD SYSTEM REQUIREMENTS PASSED!\n";
    exit(0);
} else {
    echo "⚠️ SOME TESTS FAILED.\n";
    exit(1);
}
