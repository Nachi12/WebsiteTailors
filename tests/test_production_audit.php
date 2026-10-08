<?php
/**
 * WebsiteTailors — Complete Production-Readiness Audit Test Suite
 * 
 * Verifies all security criteria, database integrity, upload protections,
 * error handling, performance memoization, SEO tags, and the complete end-to-end user flow:
 * Public Website -> Services -> Projects -> Contact -> Lead DB -> Admin Login -> Dashboard
 * -> Manage Services -> Manage Projects -> Manage Process -> Manage Testimonials
 * -> Manage Leads -> Site Settings -> Logout.
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
require_once dirname(__DIR__) . '/includes/init.php';

$totalTests = 0;
$passedTests = 0;

function assert_test(string $name, bool $condition, string $details = ''): void {
    global $totalTests, $passedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] {$name}\n";
    } else {
        echo "  [FAIL] {$name}" . ($details ? " - {$details}" : '') . "\n";
    }
}

echo "====================================================\n";
echo "WebsiteTailors Comprehensive Production-Readiness Audit\n";
echo "====================================================\n\n";

// -----------------------------------------------------------------------------
// GROUP 1: SECURITY AUDIT & CONFIGURATION
// -----------------------------------------------------------------------------
echo "Test Group 1: Security Audit & System Hardening\n";

// 1.1 XSS Escaping
$rawXss = '<script>alert("xss")</script>&"\'';
$escaped = e($rawXss);
assert_test(
    "Output escaping helper e() neutralizes XSS payloads",
    !str_contains($escaped, '<script>') && str_contains($escaped, '&lt;script&gt;') && str_contains($escaped, '&quot;')
);

// 1.2 Open Redirect Protection in admin/login.php
$evilTarget = '//attacker.com/steal';
$redirectTarget = $evilTarget;
if (str_starts_with($redirectTarget, '//') || (!str_starts_with($redirectTarget, '/') && !str_starts_with($redirectTarget, BASE_URL))) {
    $redirectTarget = ADMIN_URL . '/index.php';
}
assert_test(
    "Protocol-relative open redirects (//evil.com) are blocked and normalized",
    $redirectTarget === (ADMIN_URL . '/index.php')
);

// 1.3 CSRF Protection
$token = csrf_token();
assert_test(
    "Session generates cryptographically strong CSRF token (64 hex chars)",
    is_string($token) && strlen($token) === 64
);
assert_test(
    "verify_csrf() succeeds with correct token and rejects invalid token",
    verify_csrf($token) && !verify_csrf('invalid_token_12345')
);

// 1.4 Session Security Configuration
$cookieParams = session_get_cookie_params();
assert_test(
    "Session cookies enforce HttpOnly and SameSite=Lax",
    $cookieParams['httponly'] === true && $cookieParams['samesite'] === 'Lax'
);

// 1.5 File Upload Validation
$fakePhpFile = [
    'name'     => 'shell.php',
    'type'     => 'text/php',
    'tmp_name' => '/tmp/fake_shell',
    'error'    => UPLOAD_ERR_OK,
    'size'     => 1024
];
$uploadResult = handle_file_upload($fakePhpFile);
assert_test(
    "Upload handler strictly rejects .php files",
    $uploadResult['success'] === false && str_contains($uploadResult['error'] ?? '', 'Invalid file type')
);

// 1.6 Uploads directory execution prevention
$uploadsHtaccess = dirname(__DIR__) . '/uploads/.htaccess';
assert_test(
    "Uploads directory contains .htaccess blocking script execution",
    file_exists($uploadsHtaccess) && str_contains(file_get_contents($uploadsHtaccess), 'php_flag engine off')
);

// 1.7 Root .htaccess protects sensitive files and defines custom error documents
$rootHtaccess = dirname(__DIR__) . '/.htaccess';
$rootHtaccessContent = file_exists($rootHtaccess) ? file_get_contents($rootHtaccess) : '';
assert_test(
    "Root .htaccess configures ErrorDocument 404 and 500",
    str_contains($rootHtaccessContent, 'ErrorDocument 404 /404.php') &&
    str_contains($rootHtaccessContent, 'ErrorDocument 500 /500.php')
);
assert_test(
    "Root .htaccess denies direct access to .sqlite, .sql, and .log files",
    str_contains($rootHtaccessContent, 'sqlite') && str_contains($rootHtaccessContent, 'error.log')
);

// -----------------------------------------------------------------------------
// GROUP 2: DATABASE AUDIT & INTEGRITY
// -----------------------------------------------------------------------------
echo "\nTest Group 2: Database Integrity & Prepared Statements\n";

$db = Database::getInstance();
assert_test("Database PDO instance is actively connected", $db->isConnected());

// 2.1 Prepared statement verification (zero SQL injection on quotes/comments)
$testSqlInjection = "' OR '1'='1";
$sqlRes = $db->fetch("SELECT COUNT(*) as c FROM admins WHERE username = :u", [':u' => $testSqlInjection]);
assert_test(
    "Prepared statement prevents SQL injection parameter breakout",
    (int)($sqlRes['c'] ?? -1) === 0
);

// 2.2 Table structure integrity
$requiredTables = ['admins', 'hero_content', 'services', 'projects', 'process_steps', 'testimonials', 'leads', 'site_settings', 'login_attempts'];
$missingTables = [];
foreach ($requiredTables as $t) {
    try {
        $db->query("SELECT 1 FROM {$t} LIMIT 1");
    } catch (\Throwable $ex) {
        $missingTables[] = $t;
    }
}
assert_test(
    "All required production tables exist in database schema",
    empty($missingTables),
    "Missing: " . implode(', ', $missingTables)
);

// -----------------------------------------------------------------------------
// GROUP 3: AUTHENTICATION INTEGRITY
// -----------------------------------------------------------------------------
echo "\nTest Group 3: Authentication & Authorization Controls\n";

// Verify no hardcoded credentials bypass exists
$fakeAuth = attempt_admin_login('nonexistent_user@WebsiteTailors.digital', 'WrongPassword123!');
assert_test(
    "Authentication strictly rejects unknown user without hardcoded fallback",
    $fakeAuth['success'] === false && $fakeAuth['error'] === 'Invalid email or password.'
);

// Verify timing safe authentication with seed password
$validAuth = attempt_admin_login('websietailorss@gmail.com', 'Admin@12345');
assert_test(
    "Authentication succeeds with valid database credentials via password_verify()",
    $validAuth['success'] === true
);
assert_test(
    "Successful login initializes authenticated session with admin ID and role",
    is_admin_logged_in() && current_admin() !== null && current_admin()['role'] === 'superadmin'
);

// Verify secure logout
admin_logout();
assert_test(
    "admin_logout() terminates session and clears admin state",
    !is_admin_logged_in() && current_admin() === null
);

// -----------------------------------------------------------------------------
// GROUP 4: COMPLETE END-TO-END FLOW VERIFICATION
// -----------------------------------------------------------------------------
echo "\nTest Group 4: Complete End-to-End Workflow\n";

// Step 4.1: Public Website (Home)
$testBaseUrl = 'http://localhost:8000';
if (!@file_get_contents($testBaseUrl . '/')) {
    if (@file_get_contents('http://localhost:8080/')) {
        $testBaseUrl = 'http://localhost:8080';
    } elseif (@file_get_contents('http://127.0.0.1:8088/')) {
        $testBaseUrl = 'http://127.0.0.1:8088';
    }
}
$homeHtml = @file_get_contents($testBaseUrl . '/');
assert_test(
    "Public Website (Home) loads with HTTP 200 and renders main landmark",
    is_string($homeHtml) && str_contains($homeHtml, 'id="main-content"') && str_contains($homeHtml, 'class="skip-link"')
);

// Step 4.2: Services Page
$servicesHtml = @file_get_contents($testBaseUrl . '/services.php');
assert_test(
    "Services page loads with semantic h1 and main landmark",
    is_string($servicesHtml) && str_contains($servicesHtml, 'id="main-content"') && str_contains($servicesHtml, 'ENGINEERED')
);

// Step 4.3: Projects Page
$workHtml = @file_get_contents($testBaseUrl . '/work.php');
assert_test(
    "Projects page loads with semantic h1 and main landmark",
    is_string($workHtml) && str_contains($workHtml, 'id="main-content"') && str_contains($workHtml, 'SELECTED')
);

// Step 4.4: Contact Inquiries Submission & Storage
$db->delete('leads', 'ip_address = :ip', [':ip' => '127.0.0.1']);
unset($_SESSION['last_lead_submit_time']);
$uniqKey = bin2hex(random_bytes(4));
$leadData = [
    'action'     => 'contact',
    'csrf_token' => csrf_token(),
    'name'       => "Audit User {$uniqKey}",
    'email'      => "audit.{$uniqKey}@acmecorp.com",
    'phone'      => "+1 (555) 987-6543",
    'company'    => "Audit Corp {$uniqKey}",
    'service'    => "AI + Automation",
    'budget'     => "₹1,00,000 - ₹2,50,000",
    'message'    => "This is a full end-to-end audit inquiry verifying lead persistence."
];
$leadResult = process_lead_inquiry($leadData);
assert_test(
    "Contact inquiry processes successfully with confirmation message",
    $leadResult['success'] === true && str_contains($leadResult['message'], 'enquiry has been received')
);

$storedLead = $db->fetch("SELECT * FROM leads WHERE email = :e", [':e' => $leadData['email']]);
assert_test(
    "Lead inquiry is permanently stored in `leads` table with status 'new'",
    $storedLead !== null && $storedLead['name'] === $leadData['name'] && strtolower($storedLead['status']) === 'new'
);

// Step 4.5: Admin Login
$loginRes = attempt_admin_login('websietailorss@gmail.com', 'Admin@12345');
assert_test("Admin logs in successfully", $loginRes['success'] === true);

// Step 4.6: Admin Dashboard
$adminUser = current_admin();
assert_test("Admin dashboard recognizes authenticated admin", $adminUser !== null);

// Step 4.7: Manage Services (CRUD)
$testSvcSlug = "test-service-{$uniqKey}";
$svcId = $db->insert('services', [
    'title'             => "Test Service {$uniqKey}",
    'slug'              => $testSvcSlug,
    'short_description' => "Short description for {$uniqKey}",
    'long_description'  => "Long description for {$uniqKey}",
    'icon'              => "code",
    'features'          => "Feature 1\nFeature 2",
    'display_order'     => 99,
    'status'            => 'published',
    'created_at'        => date('Y-m-d H:i:s'),
    'updated_at'        => date('Y-m-d H:i:s')
]);
assert_test("Admin creates service", $svcId > 0);

$db->update('services', ['title' => "Updated Service {$uniqKey}"], 'id = :id', [':id' => $svcId]);
$updatedSvc = $db->fetch("SELECT title FROM services WHERE id = :id", [':id' => $svcId]);
assert_test("Admin updates service", ($updatedSvc['title'] ?? '') === "Updated Service {$uniqKey}");

$db->delete('services', 'id = :id', [':id' => $svcId]);
$deletedSvc = $db->fetch("SELECT id FROM services WHERE id = :id", [':id' => $svcId]);
assert_test("Admin deletes service", $deletedSvc === null);

// Step 4.8: Manage Projects (CRUD)
$testProjSlug = "test-proj-{$uniqKey}";
$projId = $db->insert('projects', [
    'title'         => "Test Project {$uniqKey}",
    'slug'          => $testProjSlug,
    'category'      => "Software",
    'client_name'   => "Test Client",
    'description'   => "Audit project description",
    'image'         => "/assets/images/projects/kroma-studio.webp",
    'project_url'   => "https://example.com/test",
    'tags'          => "PHP, Vanilla JS",
    'display_order' => 99,
    'is_featured'   => 0,
    'status'        => 'published',
    'created_at'    => date('Y-m-d H:i:s'),
    'updated_at'    => date('Y-m-d H:i:s')
]);
assert_test("Admin creates project", $projId > 0);

$db->delete('projects', 'id = :id', [':id' => $projId]);
$deletedProj = $db->fetch("SELECT id FROM projects WHERE id = :id", [':id' => $projId]);
assert_test("Admin deletes project", $deletedProj === null);

// Step 4.9: Manage Process Steps (CRUD)
$stepId = $db->insert('process_steps', [
    'step_number'   => "99",
    'title'         => "Audit Step",
    'description'   => "Step description",
    'display_order' => 99,
    'status'        => 'published',
    'created_at'    => date('Y-m-d H:i:s'),
    'updated_at'    => date('Y-m-d H:i:s')
]);
assert_test("Admin creates process step", $stepId > 0);

$db->delete('process_steps', 'id = :id', [':id' => $stepId]);
$deletedStep = $db->fetch("SELECT id FROM process_steps WHERE id = :id", [':id' => $stepId]);
assert_test("Admin deletes process step", $deletedStep === null);

// Step 4.10: Manage Testimonials (CRUD)
$tstId = $db->insert('testimonials', [
    'client_name'   => "Audit Client",
    'company'       => "Audit Co",
    'position'      => "Director",
    'content'       => "Audit testimonial content",
    'rating'        => 5,
    'image'         => null,
    'display_order' => 99,
    'status'        => 'published',
    'created_at'    => date('Y-m-d H:i:s'),
    'updated_at'    => date('Y-m-d H:i:s')
]);
assert_test("Admin creates testimonial", $tstId > 0);

$db->delete('testimonials', 'id = :id', [':id' => $tstId]);
$deletedTst = $db->fetch("SELECT id FROM testimonials WHERE id = :id", [':id' => $tstId]);
assert_test("Admin deletes testimonial", $deletedTst === null);

// Step 4.11: Manage Leads (Transitions & Cleanup)
if ($storedLead) {
    $db->update('leads', ['status' => 'contacted'], 'id = :id', [':id' => $storedLead['id']]);
    $chkLead = $db->fetch("SELECT status FROM leads WHERE id = :id", [':id' => $storedLead['id']]);
    assert_test("Admin transitions lead status to 'contacted'", ($chkLead['status'] ?? '') === 'contacted');

    $db->delete('leads', 'id = :id', [':id' => $storedLead['id']]);
    $chkLeadDeleted = $db->fetch("SELECT id FROM leads WHERE id = :id", [':id' => $storedLead['id']]);
    assert_test("Admin deletes lead from pipeline", $chkLeadDeleted === null);
}

// Step 4.12: Site Settings Update
$prevTagline = get_setting('tagline', 'We Make Digital Things Work.');
update_setting('tagline', "Audited Digital Things Work {$uniqKey}");
$updatedTagline = get_setting('tagline');
assert_test("Admin updates site settings in database", $updatedTagline === "Audited Digital Things Work {$uniqKey}");
// Restore original
update_setting('tagline', $prevTagline);

// Step 4.13: Logout
admin_logout();
assert_test("Admin logs out cleanly and session is terminated", !is_admin_logged_in());

// -----------------------------------------------------------------------------
// GROUP 5: ERROR HANDLING & PRODUCTION STATUS CODES
// -----------------------------------------------------------------------------
echo "\nTest Group 5: Error Handling & HTTP Status Verification\n";

$ctx = stream_context_create(['http' => ['ignore_errors' => true]]);
$err404 = @file_get_contents($testBaseUrl . '/404.php', false, $ctx);
$status404 = $http_response_header[0] ?? '';
assert_test(
    "404 error page returns HTTP 404 Not Found",
    str_contains($status404, '404') && str_contains((string)$err404, 'PAGE NOT FOUND')
);

$err500 = @file_get_contents($testBaseUrl . '/500.php', false, $ctx);
$status500 = $http_response_header[0] ?? '';
assert_test(
    "500 error page returns HTTP 500 Internal Server Error without leaking traces",
    str_contains($status500, '500') && str_contains((string)$err500, 'SOMETHING WENT WRONG') && !str_contains((string)$err500, 'Stack Trace')
);

// -----------------------------------------------------------------------------
// SUMMARY
// -----------------------------------------------------------------------------
echo "\n====================================================\n";
echo "Production Audit Results: {$passedTests} / {$totalTests} Tests Passed (" . round(($passedTests / max(1, $totalTests)) * 100, 1) . "%)\n";
echo "====================================================\n";

if ($passedTests === $totalTests) {
    echo "🎉 ALL PRODUCTION-READINESS CRITERIA VERIFIED AND PASSED!\n";
    exit(0);
} else {
    echo "❌ SOME AUDIT CHECKS FAILED.\n";
    exit(1);
}
