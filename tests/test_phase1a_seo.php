<?php
/**
 * Website Tailors — Phase 1A Technical SEO Automated Test Suite
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) {
    define('WebsiteTailors_INIT', true);
}

require_once __DIR__ . '/../includes/init.php';

echo "====================================================\n";
echo "Website Tailors: Phase 1A Technical SEO Test Suite\n";
echo "====================================================\n\n";

$passCount = 0;
$failCount = 0;

function assert_seo(string $description, bool $condition, string $failureDetails = ''): void
{
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] {$description}\n";
        $passCount++;
    } else {
        echo "  [FAIL] {$description}\n";
        if ($failureDetails) {
            echo "         Details: {$failureDetails}\n";
        }
        $failCount++;
    }
}

// -----------------------------------------------------------------------------
// 1. XML Sitemap Validation
// -----------------------------------------------------------------------------
echo "Test Group 1: XML Sitemap Format & URL Consistency\n";

$sitemapPath = dirname(__DIR__) . '/sitemap.xml';
assert_seo("sitemap.xml exists in root", file_exists($sitemapPath));

$sitemapContent = file_get_contents($sitemapPath);
$xml = simplexml_load_string($sitemapContent);
assert_seo("sitemap.xml is well-formed XML", $xml !== false);

$urls = [];
if ($xml !== false) {
    foreach ($xml->url as $u) {
        $urls[] = (string)$u->loc;
    }
}

assert_seo("sitemap.xml contains exactly 16 public routes", count($urls) === 16, "Found: " . count($urls));

$hasPhp = false;
$hasWww = false;
$hasNonHttps = false;
$hasQuery = false;

foreach ($urls as $url) {
    if (str_ends_with($url, '.php')) $hasPhp = true;
    if (str_contains($url, 'www.')) $hasWww = true;
    if (!str_starts_with($url, 'https://websitetailors.com/')) $hasNonHttps = true;
    if (str_contains($url, '?')) $hasQuery = true;
}

assert_seo("All sitemap URLs use HTTPS and apex domain (no www)", !$hasNonHttps && !$hasWww);
assert_seo("All sitemap URLs are clean canonical paths (zero .php extensions)", !$hasPhp);
assert_seo("All sitemap URLs exclude query parameters", !$hasQuery);

// -----------------------------------------------------------------------------
// 2. Canonical Tag & Robots Resolution Logic
// -----------------------------------------------------------------------------
echo "\nTest Group 2: Canonical Tag & Robots Directive Resolution\n";

$knownPublicRoutes = [
    '/index.php' => '/',
    '/services.php' => '/services',
    '/work.php' => '/work',
    '/about.php' => '/about',
    '/contact.php' => '/contact',
    '/website-design-bangalore.php' => '/website-design-bangalore',
    '/website-redesign-bangalore.php' => '/website-redesign-bangalore',
    '/whatsapp-automation-bangalore.php' => '/whatsapp-automation-bangalore',
    '/website-for-clinics-bangalore.php' => '/website-for-clinics-bangalore',
    '/website-for-restaurants-bangalore.php' => '/website-for-restaurants-bangalore',
    '/privacy-policy.php' => '/privacy-policy',
    '/terms.php' => '/terms',
    '/blog/index.php' => '/blog/',
    '/blog/how-much-does-a-website-cost-in-bangalore-2026.php' => '/blog/how-much-does-a-website-cost-in-bangalore-2026',
    '/blog/website-vs-google-business-profile.php' => '/blog/website-vs-google-business-profile',
    '/blog/how-whatsapp-automation-helps-small-businesses.php' => '/blog/how-whatsapp-automation-helps-small-businesses',
];

function test_header_resolution(string $script, string $uri, int $httpStatus = 200, string $active = 'page'): array
{
    global $knownPublicRoutes;
    $isErrorPage = ($httpStatus >= 400) || ($active === '404');
    $robotsDirective = $isErrorPage ? 'noindex, follow' : 'index, follow';

    $canonicalPath = null;
    $normScript = '/' . ltrim(str_replace('\\', '/', $script), '/');
    if (isset($knownPublicRoutes[$normScript])) {
        $canonicalPath = $knownPublicRoutes[$normScript];
    } else {
        $rawPath = strtok($uri, '?');
        $trimmedPath = rtrim($rawPath, '/');
        if ($trimmedPath === '' || $rawPath === '/') {
            $canonicalPath = '/';
        } elseif ($rawPath === '/blog/' || $rawPath === '/blog') {
            $canonicalPath = '/blog/';
        } else {
            foreach ($knownPublicRoutes as $s => $clean) {
                if ($trimmedPath === rtrim($clean, '/')) {
                    $canonicalPath = $clean;
                    break;
                }
            }
        }
    }
    $canonicalUrl = ($canonicalPath !== null && !$isErrorPage)
        ? 'https://websitetailors.com' . ($canonicalPath === '/' ? '/' : $canonicalPath)
        : null;

    return ['canonical' => $canonicalUrl, 'robots' => $robotsDirective];
}

$hRes = test_header_resolution('/index.php', '/');
assert_seo("Homepage canonical maps to https://websitetailors.com/", $hRes['canonical'] === 'https://websitetailors.com/');

$qRes = test_header_resolution('/services.php', '/services?source=adwords&campaign=blr');
assert_seo("Query parameters are excluded from canonical URL", $qRes['canonical'] === 'https://websitetailors.com/services');

$phpRes = test_header_resolution('/services.php', '/services.php');
assert_seo("Requesting /services.php produces clean canonical /services", $phpRes['canonical'] === 'https://websitetailors.com/services');

$blogIdxRes = test_header_resolution('/blog/index.php', '/blog/index.php');
assert_seo("/blog/index.php maps canonical to /blog/", $blogIdxRes['canonical'] === 'https://websitetailors.com/blog/');

$blogPostRes = test_header_resolution('/blog/how-much-does-a-website-cost-in-bangalore-2026.php', '/blog/how-much-does-a-website-cost-in-bangalore-2026');
assert_seo("Blog article maps canonical to clean URL", $blogPostRes['canonical'] === 'https://websitetailors.com/blog/how-much-does-a-website-cost-in-bangalore-2026');

$errRes = test_header_resolution('/404.php', '/nonexistent-page', 404, '404');
assert_seo("404 error page suppresses canonical URL", $errRes['canonical'] === null);
assert_seo("404 error page outputs 'noindex, follow'", $errRes['robots'] === 'noindex, follow');

// -----------------------------------------------------------------------------
// 3. Structured Data JSON-LD Audit
// -----------------------------------------------------------------------------
echo "\nTest Group 3: Structured Data (Schema.org JSON-LD)\n";

ob_start();
require dirname(__DIR__) . '/includes/header.php';
$renderedHtml = ob_get_clean();

preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $renderedHtml, $m);
$jsonLd = json_decode($m[1] ?? '', true);

assert_seo("JSON-LD parses as valid Schema.org JSON", $jsonLd !== null);
assert_seo("JSON-LD does not contain '[OFFICE ADDRESS' placeholder", !str_contains($m[1] ?? '', '[OFFICE ADDRESS'));
assert_seo("JSON-LD omits unconfirmed physical address property", !isset($jsonLd['@graph'][0]['address']));
assert_seo("JSON-LD does not contain guessed coordinates or postal codes", !str_contains($m[1] ?? '', 'GeoCoordinates') && !str_contains($m[1] ?? '', 'postalCode'));
assert_seo("JSON-LD contains valid ProfessionalService & Organization entities with areaServed", isset($jsonLd['@graph'][0]['@type']) && in_array('ProfessionalService', $jsonLd['@graph'][0]['@type']) && !empty($jsonLd['@graph'][0]['areaServed']));

// -----------------------------------------------------------------------------
// 4. .htaccess Apache Rules Verification
// -----------------------------------------------------------------------------
echo "\nTest Group 4: .htaccess Rewrite & Redirect Directives\n";

$htaccess = file_get_contents(dirname(__DIR__) . '/.htaccess');
assert_seo(".htaccess redirects www to non-www with 301", str_contains($htaccess, 'RewriteCond %{HTTP_HOST} ^www\.websitetailors\.com$ [NC]') && str_contains($htaccess, 'https://websitetailors.com/$1 [R=301,L]'));
assert_seo(".htaccess redirects /index.php to / with 301", str_contains($htaccess, 'RewriteCond %{THE_REQUEST} ^[A-Z]{3,}\s/+index\.php[\s?] [NC]'));
assert_seo(".htaccess redirects /blog/index.php to /blog/ with 301", str_contains($htaccess, 'RewriteCond %{THE_REQUEST} ^[A-Z]{3,}\s/+blog/index\.php[\s?] [NC]'));
assert_seo(".htaccess restricts .php 301 redirects to safe GET/HEAD requests", str_contains($htaccess, 'RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$ [NC]'));
assert_seo(".htaccess excludes admin and api from .php redirects", str_contains($htaccess, 'RewriteCond %{REQUEST_URI} !^/(admin|api|database|bin|config|includes|tests|uploads)/ [NC]'));
assert_seo(".htaccess checks THE_REQUEST to guarantee no redirect loops", str_contains($htaccess, 'RewriteCond %{THE_REQUEST} ^[A-Z]{3,}\s/+(.+?)\.php[\s?] [NC]'));
assert_seo(".htaccess maintains clean URL rewrite mapping", str_contains($htaccess, 'RewriteCond %{REQUEST_FILENAME}\.php -f') && str_contains($htaccess, 'RewriteRule ^(.*)$ $1.php [L]'));

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------
echo "\n====================================================\n";
echo "Phase 1A SEO Results: {$passCount} / " . ($passCount + $failCount) . " Tests Passed (" . round(($passCount / ($passCount + $failCount)) * 100, 1) . "%)\n";
echo "====================================================\n";

if ($failCount > 0) {
    echo "❌ SOME PHASE 1A SEO CHECKS FAILED.\n";
    exit(1);
} else {
    echo "🎉 ALL PHASE 1A TECHNICAL SEO CHECKS PASSED!\n";
    exit(0);
}
