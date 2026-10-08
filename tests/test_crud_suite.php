<?php
/**
 * Automated CRUD Test Suite for WebsiteTailors Admin Panel
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
$db = Database::getInstance();

$baseUrl = getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8000';
$cookieFile = sys_get_temp_dir() . "/WebsiteTailors_crud_cookies.txt";
if (file_exists($cookieFile)) unlink($cookieFile);

function http_req($url, $method = "GET", $data = null, $cookieFile = null, $follow = false, $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $follow);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
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

function get_csrf($body) {
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $body, $matches);
    return $matches[1] ?? '';
}

echo "=== STEP 1: Log in to Admin Panel ===\n";
$r = http_req($baseUrl . "/admin/login.php", "GET", null, $cookieFile);
$csrf = get_csrf($r["body"]);
assert(!empty($csrf), "Could not extract login CSRF token");

$loginData = http_build_query([
    "csrf_token" => $csrf,
    "email"      => "websietailorss@gmail.com",
    "password"   => "Admin@12345"
]);
$rLogin = http_req($baseUrl . "/admin/login.php", "POST", $loginData, $cookieFile, false);
assert($rLogin["code"] === 302, "Login failed: expected 302");
echo "✔ Authenticated successfully as superadmin\n\n";

echo "=== STEP 2: Test Hero Section CRUD ===\n";
$rHero = http_req($baseUrl . "/admin/pages/hero.php", "GET", null, $cookieFile);
assert($rHero["code"] === 200, "Hero page failed to load");
$csrfHero = get_csrf($rHero["body"]);

// Update Hero
$updateHeroData = http_build_query([
    "csrf_token"            => $csrfHero,
    "action"                => "save",
    "headline"              => "WE BUILD DIGITAL PRODUCTS THAT SCALE.",
    "subheadline"           => "Award-winning software engineering and architecture.",
    "description"           => "WebsiteTailors transforms complex systems into high-performing platforms.",
    "primary_button_text"   => "Get in Touch",
    "primary_button_link"   => "#contact",
    "secondary_button_text" => "View Showcase",
    "secondary_button_link" => "#work"
]);
$rHeroPost = http_req($baseUrl . "/admin/pages/hero.php", "POST", $updateHeroData, $cookieFile);
assert(strpos($rHeroPost["body"], "Hero section successfully updated") !== false, "Hero update message missing");
assert(strpos($rHeroPost["body"], "WE BUILD DIGITAL PRODUCTS THAT SCALE.") !== false, "New headline not in hero page");
echo "✔ Hero Section updated and verified\n\n";

echo "=== STEP 3: Test Services CRUD ===\n";
$rSvc = http_req($baseUrl . "/admin/pages/services.php", "GET", null, $cookieFile);
$csrfSvc = get_csrf($rSvc["body"]);

// Create new service
$newSvcData = http_build_query([
    "csrf_token"        => $csrfSvc,
    "action"            => "save",
    "id"                => 0,
    "title"             => "Cloud DevOps & Architecture",
    "icon"              => "cpu",
    "display_order"     => 4,
    "short_description" => "Resilient AWS and GCP infrastructure scaling.",
    "long_description"  => "Automated CI/CD pipelines, Docker container orchestration, and serverless optimization.",
    "status"            => "published"
]);
$rSvcCreate = http_req($baseUrl . "/admin/pages/services.php", "POST", $newSvcData, $cookieFile);
assert(strpos($rSvcCreate["body"], "Cloud DevOps") !== false, "Created service not found in table");
echo "✔ Service created successfully\n";

// Toggle status of created service
preg_match('/name="id"\s+value="(\d+)"[^>]*>\s*<input[^>]*name="current_status"\s+value="published"/', $rSvcCreate["body"], $matches);
$createdSvc = $db->fetch("SELECT id, status FROM services WHERE title = 'Cloud DevOps & Architecture'");
assert(!empty($createdSvc), "Created service not in DB");
$svcId = (int)$createdSvc['id'];

$toggleData = http_build_query([
    "csrf_token"     => get_csrf($rSvcCreate["body"]),
    "action"         => "toggle_status",
    "id"             => $svcId,
    "current_status" => "published"
]);
$rSvcToggle = http_req($baseUrl . "/admin/pages/services.php", "POST", $toggleData, $cookieFile);
assert(strpos($rSvcToggle["body"], "Service status updated to Draft") !== false, "Toggle status failed");
echo "✔ Service status toggle verified\n";

// Delete service
$deleteSvcData = http_build_query([
    "csrf_token" => get_csrf($rSvcToggle["body"]),
    "action"     => "delete",
    "id"         => $svcId
]);
$rSvcDelete = http_req($baseUrl . "/admin/pages/services.php", "POST", $deleteSvcData, $cookieFile);
assert(strpos($rSvcDelete["body"], "Service successfully deleted") !== false, "Service delete failed");
echo "✔ Service deleted successfully\n\n";

echo "=== STEP 4: Test Project Management CRUD ===\n";
$rProj = http_req($baseUrl . "/admin/pages/projects.php", "GET", null, $cookieFile);
$csrfProj = get_csrf($rProj["body"]);

// Create project
$newProjData = [
    "csrf_token"    => $csrfProj,
    "action"        => "save",
    "id"            => "0",
    "title"         => "Fintech Alpha Gateway",
    "category"      => "Software",
    "client_name"   => "Alpha Payments Ltd",
    "project_url"   => "https://alpha.example.com",
    "display_order" => "5",
    "description"   => "High-throughput transaction ledger with bank-grade encryption.",
    "tags"          => "PHP 8, Microservices, Redis",
    "status"        => "published"
];
$rProjCreate = http_req($baseUrl . "/admin/pages/projects.php", "POST", $newProjData, $cookieFile);
assert(strpos($rProjCreate["body"], "Fintech Alpha Gateway") !== false, "Created project missing in table");
echo "✔ Project created successfully\n";

$createdProj = $db->fetch("SELECT id FROM projects WHERE title = 'Fintech Alpha Gateway'");
$projId = (int)$createdProj['id'];

// Delete project
$delProjData = http_build_query([
    "csrf_token" => get_csrf($rProjCreate["body"]),
    "action"     => "delete",
    "id"         => $projId
]);
$rProjDelete = http_req($baseUrl . "/admin/pages/projects.php", "POST", $delProjData, $cookieFile);
assert(strpos($rProjDelete["body"], "Project successfully deleted") !== false, "Project delete failed");
echo "✔ Project deleted successfully\n\n";

echo "=== STEP 5: Test Process Steps CRUD ===\n";
$rProc = http_req($baseUrl . "/admin/pages/process.php", "GET", null, $cookieFile);
$csrfProc = get_csrf($rProc["body"]);

$newStepData = http_build_query([
    "csrf_token"    => $csrfProc,
    "action"        => "save",
    "id"            => 0,
    "step_number"   => "05",
    "title"         => "We optimize.",
    "description"   => "Continuous monitoring, load testing, and conversion tuning.",
    "display_order" => 5,
    "status"        => "published"
]);
$rProcCreate = http_req($baseUrl . "/admin/pages/process.php", "POST", $newStepData, $cookieFile);
assert(strpos($rProcCreate["body"], "We optimize.") !== false, "Created step missing in table");
echo "✔ Process step created\n";

$createdStep = $db->fetch("SELECT id FROM process_steps WHERE step_number = '05'");
$stepId = (int)$createdStep['id'];

$delStepData = http_build_query([
    "csrf_token" => get_csrf($rProcCreate["body"]),
    "action"     => "delete",
    "id"         => $stepId
]);
$rProcDelete = http_req($baseUrl . "/admin/pages/process.php", "POST", $delStepData, $cookieFile);
assert(strpos($rProcDelete["body"], "Process step deleted") !== false, "Process delete failed");
echo "✔ Process step deleted\n\n";

echo "=== STEP 6: Test Testimonials CRUD ===\n";
$rTestim = http_req($baseUrl . "/admin/pages/testimonials.php", "GET", null, $cookieFile);
$csrfTestim = get_csrf($rTestim["body"]);

$newTestimData = [
    "csrf_token"  => $csrfTestim,
    "action"      => "save",
    "id"          => "0",
    "client_name" => "Samantha Reed",
    "company"     => "Horizon Ventures",
    "position"    => "Partner",
    "content"     => "WebsiteTailors delivered our digital portal ahead of deadline with exceptional quality.",
    "rating"      => "5",
    "status"      => "published"
];
$rTestimCreate = http_req($baseUrl . "/admin/pages/testimonials.php", "POST", $newTestimData, $cookieFile);
assert(strpos($rTestimCreate["body"], "Samantha Reed") !== false, "Testimonial not in table");
echo "✔ Testimonial created\n";

$createdTestim = $db->fetch("SELECT id FROM testimonials WHERE client_name = 'Samantha Reed'");
$testimId = (int)$createdTestim['id'];

$delTestimData = http_build_query([
    "csrf_token" => get_csrf($rTestimCreate["body"]),
    "action"     => "delete",
    "id"         => $testimId
]);
$rTestimDelete = http_req($baseUrl . "/admin/pages/testimonials.php", "POST", $delTestimData, $cookieFile);
assert(strpos($rTestimDelete["body"], "Testimonial removed successfully") !== false, "Testimonial delete failed");
echo "✔ Testimonial deleted\n\n";

echo "=== STEP 7: Test Site Settings ===\n";
$rSet = http_req($baseUrl . "/admin/pages/settings.php", "GET", null, $cookieFile);
$csrfSet = get_csrf($rSet["body"]);

$newSettings = [
    "csrf_token"   => $csrfSet,
    "company_name" => "Website Tailors",
    "tagline"      => "Engineering Digital Precision.",
    "email"        => "websietailorss@gmail.com",
    "phone"        => "9380552034",
    "address"      => "Bangalore-560010, karnataka. India",
    "linkedin"     => "https://linkedin.com/company/websitetailors",
    "instagram"    => "https://instagram.com/websitetailors",
    "facebook"     => "https://facebook.com/websitetailors",
    "github"       => "https://github.com/websitetailors"
];
$rSetPost = http_req($baseUrl . "/admin/pages/settings.php", "POST", $newSettings, $cookieFile);
assert(strpos($rSetPost["body"], "Site settings updated successfully") !== false, "Settings save message missing");
assert(strpos($rSetPost["body"], "Website Tailors") !== false, "Updated company name missing");
echo "✔ Settings updated successfully\n\n";

echo "=== STEP 8: Test Leads Filter, Search, and Status Transition ===\n";
// Insert test lead to manipulate
$testLeadId = $db->insert('leads', [
    'name'               => 'Test Lead Automated',
    'email'              => 'automated@testing.io',
    'phone'              => '+1 555 111 2222',
    'company'            => 'Automated Corp',
    'service_interested' => 'Software',
    'budget'             => '₹2,00,000',
    'message'            => 'Looking for full-stack developers.',
    'ip_address'         => '127.0.0.1',
    'status'             => 'new',
    'created_at'         => date('Y-m-d H:i:s'),
    'updated_at'         => date('Y-m-d H:i:s')
]);

// Search test lead
$rSearch = http_req($baseUrl . "/admin/pages/leads.php?q=Automated", "GET", null, $cookieFile);
assert(strpos($rSearch["body"], "Test Lead Automated") !== false, "Search failed to find lead");
echo "✔ Search functionality verified\n";

// Move to Contacted
$rLeadPage = http_req($baseUrl . "/admin/pages/leads.php", "GET", null, $cookieFile);
$csrfLead = get_csrf($rLeadPage["body"]);

$statusChangeData = http_build_query([
    "csrf_token" => $csrfLead,
    "action"     => "set_status",
    "id"         => $testLeadId,
    "status"     => "contacted"
]);
$rStatusChange = http_req($baseUrl . "/admin/pages/leads.php", "POST", $statusChangeData, $cookieFile);
assert(strpos($rStatusChange["body"], "marked as Contacted") !== false, "Status change to contacted failed");
echo "✔ Status change to 'Contacted' verified\n";

// Move to Archived
$statusArchiveData = http_build_query([
    "csrf_token" => get_csrf($rStatusChange["body"]),
    "action"     => "set_status",
    "id"         => $testLeadId,
    "status"     => "archived"
]);
$rArchive = http_req($baseUrl . "/admin/pages/leads.php", "POST", $statusArchiveData, $cookieFile);
assert(strpos($rArchive["body"], "marked as Archived") !== false, "Status change to archived failed");
echo "✔ Status change to 'Archived' verified\n";

// Filter by archived
$rArchivedFilter = http_req($baseUrl . "/admin/pages/leads.php?status=archived", "GET", null, $cookieFile);
assert(strpos($rArchivedFilter["body"], "Test Lead Automated") !== false, "Archived filter failed");
echo "✔ Status filter tabs verified\n";

// Delete test lead
$delLeadData = http_build_query([
    "csrf_token" => get_csrf($rArchivedFilter["body"]),
    "action"     => "delete",
    "id"         => $testLeadId
]);
$rDelLead = http_req($baseUrl . "/admin/pages/leads.php", "POST", $delLeadData, $cookieFile);
assert(strpos($rDelLead["body"], "successfully deleted") !== false, "Lead delete failed");
echo "✔ Lead delete verified\n\n";

echo "=== STEP 9: Test Dual Route Compatibility ===\n";
$routes = [
    "/admin/hero.php",
    "/admin/services.php",
    "/admin/projects.php",
    "/admin/process.php",
    "/admin/testimonials.php",
    "/admin/settings.php",
    "/admin/leads.php"
];
foreach ($routes as $route) {
    $rRoute = http_req($baseUrl . $route, "GET", null, $cookieFile);
    assert($rRoute["code"] === 200, "Route $route failed to return 200");
}
echo "✔ All dual routes forward cleanly without broken links\n\n";

echo "🎉 ALL 9 CRUD MODULE SUITES PASSED FLAWLESSLY!\n";
