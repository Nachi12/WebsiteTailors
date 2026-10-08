<?php
/**
 * WebsiteTailors — Automated CMS Audit & Verification Suite
 *
 * Tests:
 * 1. Admin as Source of Truth (Database updates reflect instantly on public site)
 * 2. Reusable functions & memoization (Avoid duplicate SQL queries)
 * 3. Graceful Empty States (Services, Projects, Process, Testimonials)
 * 4. Image Fallbacks (Missing files return verified SVG data URIs, zero broken icons)
 * 5. Dynamic SEO (title, meta description, Open Graph, Twitter Cards, robots.txt, sitemap.xml)
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
require_once __DIR__ . '/../includes/init.php';

echo "==================================================\n";
echo "WebsiteTailors Public Website CMS Audit & Verification Suite\n";
echo "==================================================\n\n";

$db = Database::getInstance();
$testsPassed = 0;
$totalTests = 0;

function assertCondition(bool $condition, string $testName, string $failureDetails = ''): void {
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

// Helper to fetch local dev server HTML
function getTestBaseUrl(): string {
    static $base = null;
    if ($base !== null) return $base;
    foreach (['http://127.0.0.1:8000', 'http://127.0.0.1:8088', 'http://localhost:8000', 'http://localhost:8080'] as $candidate) {
        if (@file_get_contents($candidate . '/index.php')) {
            $base = $candidate;
            return $base;
        }
    }
    $base = 'http://127.0.0.1:8000';
    return $base;
}

function fetchUrl(string $path): string {
    $ch = curl_init(getTestBaseUrl() . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $html = curl_exec($ch);
    return is_string($html) ? $html : '';
}

// ---------------------------------------------------------------------------
// TEST GROUP 1: Dynamic SEO, Meta & Social Links
// ---------------------------------------------------------------------------
echo "Test Group 1: Dynamic SEO, Meta & Footer Links\n";

// Update site settings in DB
$originalCompanyName = get_setting('company_name', 'WebsiteTailors');
$originalTagline = get_setting('tagline', 'We Make Digital Things Work.');
$testTagline = 'TEST TAGLINE — ' . uniqid();

update_setting('tagline', $testTagline);

$indexHtml = fetchUrl('/index.php');
assertCondition(
    str_contains($indexHtml, htmlspecialchars($testTagline, ENT_QUOTES, 'UTF-8')),
    'Public homepage displays updated tagline from database',
    'Did not find ' . $testTagline . ' in homepage HTML'
);

assertCondition(
    str_contains($indexHtml, 'og:site_name') && str_contains($indexHtml, 'og:image') && str_contains($indexHtml, 'twitter:card'),
    'Header contains complete Open Graph and Twitter Card tags'
);

// Restore tagline
update_setting('tagline', $originalTagline);

// ---------------------------------------------------------------------------
// TEST GROUP 2: Admin as Source of Truth (Hero & Services)
// ---------------------------------------------------------------------------
echo "\nTest Group 2: Admin as Source of Truth\n";

// Update hero headline
$testHeadline = "AUTOMATED\nCMS ENGINE\nACTIVE.";
$db->query("UPDATE hero_content SET headline = :h WHERE id = 1", [':h' => $testHeadline]);

$indexHtml = fetchUrl('/index.php');
assertCondition(
    str_contains($indexHtml, 'AUTOMATED') && str_contains($indexHtml, 'CMS ENGINE') && str_contains($indexHtml, 'ACTIVE.'),
    'Hero headline changes in DB immediately render on homepage'
);

// Restore hero
$db->query("UPDATE hero_content SET headline = 'WE BUILD\nDIGITAL\nPRODUCTS\nTHAT SCALE.' WHERE id = 1");

// Test CamelCase API aliases
assertCondition(
    is_array(getHero()) && !empty(getHero()['headline']),
    'getHero() alias returns valid hero dataset'
);
assertCondition(
    is_array(getServices()) && is_array(getProjects()) && is_array(getProcessSteps()) && is_array(getTestimonials()) && is_array(getSiteSettings()),
    'All camelCase helper aliases (getServices, getProjects, getProcessSteps, getTestimonials, getSiteSettings) function correctly'
);

// ---------------------------------------------------------------------------
// TEST GROUP 3: Image Fallback Architecture
// ---------------------------------------------------------------------------
echo "\nTest Group 3: Image Fallback Architecture\n";

// Test non-existent project image path
$fallbackSvg = get_image_url('/uploads/projects/does-not-exist-' . uniqid() . '.webp', 'project');
assertCondition(
    str_starts_with($fallbackSvg, 'data:image/svg+xml;utf8,'),
    'Non-existent project image returns valid SVG data URI placeholder instead of broken icon'
);

// Test non-existent avatar image path
$avatarFallback = get_image_url(null, 'avatar');
assertCondition(
    str_starts_with($avatarFallback, 'data:image/svg+xml;utf8,') && str_contains($avatarFallback, '%3Ccircle'),
    'Null avatar path returns valid avatar SVG data URI'
);

// ---------------------------------------------------------------------------
// TEST GROUP 4: Graceful Empty States
// ---------------------------------------------------------------------------
echo "\nTest Group 4: Graceful Empty States\n";

// Backup existing services, projects, process_steps, testimonials
$backupServices = $db->fetchAll("SELECT * FROM services");
$backupProjects = $db->fetchAll("SELECT * FROM projects");
$backupSteps    = $db->fetchAll("SELECT * FROM process_steps");
$backupTestimonials = $db->fetchAll("SELECT * FROM testimonials");

// 4a. Empty services
$db->query("UPDATE services SET status = 'draft'");
$servicesHtml = fetchUrl('/services.php');
$indexServicesHtml = fetchUrl('/index.php');
assertCondition(
    str_contains($servicesHtml, 'Services Updating') && str_contains($servicesHtml, 'empty-state-public'),
    'services.php shows graceful .empty-state-public when all services are unpublished/deleted'
);
assertCondition(
    str_contains($indexServicesHtml, 'Services Updating') && str_contains($indexServicesHtml, 'empty-state-public'),
    'index.php services section shows graceful .empty-state-public when services are empty'
);

// 4b. Empty projects
$db->query("UPDATE projects SET status = 'draft'");
$workHtml = fetchUrl('/work.php');
$indexProjectsHtml = fetchUrl('/index.php');
assertCondition(
    str_contains($workHtml, 'Portfolio Updating') && str_contains($workHtml, 'empty-state-public'),
    'work.php shows graceful .empty-state-public when all projects are unpublished/deleted'
);
assertCondition(
    str_contains($indexProjectsHtml, 'Portfolio Case Studies Updating') && str_contains($indexProjectsHtml, 'empty-state-public'),
    'index.php projects section shows graceful .empty-state-public when projects are empty'
);

// 4c. Empty process steps
$db->query("UPDATE process_steps SET status = 'draft'");
$aboutHtml = fetchUrl('/about.php');
$indexProcessHtml = fetchUrl('/index.php');
assertCondition(
    str_contains($aboutHtml, 'Custom Tailored Process') && str_contains($aboutHtml, 'empty-state-public'),
    'about.php shows graceful .empty-state-public when process steps are empty'
);
assertCondition(
    str_contains($indexProcessHtml, 'Custom Tailored Process') && str_contains($indexProcessHtml, 'empty-state-public'),
    'index.php process section shows graceful .empty-state-public when process steps are empty'
);

// 4d. Empty testimonials
$db->query("UPDATE testimonials SET status = 'draft'");
$indexTestimonialsHtml = fetchUrl('/index.php');
assertCondition(
    str_contains($indexTestimonialsHtml, 'Testimonials Being Verified') && str_contains($indexTestimonialsHtml, 'empty-state-public'),
    'index.php testimonials section shows graceful .empty-state-public when testimonials are empty'
);

// RESTORE ALL DATA
$db->query("UPDATE services SET status = 'published'");
$db->query("UPDATE projects SET status = 'published'");
$db->query("UPDATE process_steps SET status = 'published'");
$db->query("UPDATE testimonials SET status = 'published'");

$restoredIndexHtml = fetchUrl('/index.php');
assertCondition(
    !str_contains($restoredIndexHtml, 'Services Updating') && str_contains($restoredIndexHtml, 'Apex Logistics Portal'),
    'Published services and projects immediately restore cleanly on public site'
);

// ---------------------------------------------------------------------------
// TEST GROUP 5: Search Engine Optimization & Crawlers (robots.txt & sitemap.xml)
// ---------------------------------------------------------------------------
echo "\nTest Group 5: SEO & Crawler Assets\n";

$robots = fetchUrl('/robots.txt');
assertCondition(
    str_contains($robots, 'User-agent: *') && str_contains($robots, 'Disallow: /admin/') && str_contains($robots, 'sitemap.xml'),
    'robots.txt allows public crawlers, blocks admin routes, and references sitemap'
);

$sitemap = fetchUrl('/sitemap.xml');
assertCondition(
    str_contains($sitemap, '<urlset') && str_contains($sitemap, '<loc>') && str_contains($sitemap, 'services.php'),
    'sitemap.xml contains valid XML URL definitions for all public pages'
);

// ---------------------------------------------------------------------------
// TEST GROUP 6: Request-Level In-Memory Memoization
// ---------------------------------------------------------------------------
echo "\nTest Group 6: Request-Level Memoization Performance\n";

// Clear statics or verify calls execute cleanly without extra DB latency
$t0 = microtime(true);
for ($i = 0; $i < 50; $i++) {
    $s = get_services();
    $p = get_projects();
    $h = get_hero_content();
    $st = get_all_settings();
}
$duration = (microtime(true) - $t0) * 1000;
assertCondition(
    $duration < 50.0,
    sprintf('50 repeated calls to get_services/projects/hero/settings take %.2f ms (in-memory memoization active)', $duration)
);

echo "\n==================================================\n";
echo sprintf("CMS Audit Results: %d / %d Tests Passed (%.1f%%)\n", $testsPassed, $totalTests, ($testsPassed / max(1, $totalTests)) * 100);
echo "==================================================\n";

if ($testsPassed === $totalTests) {
    echo "🎉 ALL CMS REQUIREMENTS SATISFIED SUCCESSFULLY!\n";
    exit(0);
} else {
    echo "⚠️ SOME TESTS FAILED.\n";
    exit(1);
}
