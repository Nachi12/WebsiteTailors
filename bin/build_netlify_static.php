<?php
/**
 * WebsiteTailors — Netlify Static HTML & Asset Synchronization Generator
 * 
 * Renders the canonical PHP homepage (/index.php) in an output buffer,
 * post-processes static links, updates Netlify form handling, and synchronizes
 * all canonical CSS, JS, and image assets to /netlify.
 */

declare(strict_types=1);

// Set mock environment variables for clean relative URL generation
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['SCRIPT_NAME']    = '/index.php';

$rootDir = dirname(__DIR__);
$netlifyDir = $rootDir . '/netlify';

// 1. Ensure target directory structure exists
if (!is_dir($netlifyDir . '/assets/css')) {
    mkdir($netlifyDir . '/assets/css', 0755, true);
}
if (!is_dir($netlifyDir . '/assets/js')) {
    mkdir($netlifyDir . '/assets/js', 0755, true);
}
if (!is_dir($netlifyDir . '/assets/images')) {
    mkdir($netlifyDir . '/assets/images', 0755, true);
}

// 2. Synchronize CSS, JS, and Image Assets from Canonical Source
$assetsToCopy = [
    '/assets/css/style.css' => '/netlify/assets/css/style.css',
    '/assets/js/main.js'    => '/netlify/assets/js/main.js',
    '/assets/js/config.js'  => '/netlify/assets/js/config.js',
];

$copyLog = [];
foreach ($assetsToCopy as $src => $dest) {
    $srcPath = $rootDir . $src;
    $destPath = $rootDir . $dest;
    if (file_exists($srcPath)) {
        copy($srcPath, $destPath);
        $copyLog[] = "COPIED ASSET: " . $src . " -> " . $dest . " (" . filesize($srcPath) . " bytes)";
    }
}

// Copy image assets
if (is_dir($rootDir . '/assets/images')) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootDir . '/assets/images', RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $subPath = $iterator->getSubPathName();
        $destPath = $netlifyDir . '/assets/images/' . $subPath;
        if ($item->isDir()) {
            if (!is_dir($destPath)) {
                mkdir($destPath, 0755, true);
            }
        } else {
            copy($item->getPathname(), $destPath);
        }
    }
}

// 3. Render index.php Output via Output Buffering
ob_start();
require $rootDir . '/index.php';
$rawHtml = ob_get_clean();

// 4. Post-Process HTML for Static Deployment
// A. Replace local server URLs (e.g. http://localhost/ or http://localhost:8080/) with relative anchor/path
$baseUrlPattern = '#http://localhost(:[0-9]+)?/#i';
$processedHtml = preg_replace($baseUrlPattern, './', $rawHtml);
$processedHtml = str_replace('./#', '#', $processedHtml);

// B. Convert form action from PHP endpoint to static Netlify form handler
$processedHtml = str_replace(
    'action="api/contact.php"',
    'action="#contact" data-netlify="true" name="contact"',
    $processedHtml
);

// C. Replace CSRF hidden input field with static field for netlify
$processedHtml = preg_replace(
    '/<input type="hidden" name="csrf_token" value="[^"]*">/i',
    '<input type="hidden" name="form_type" value="contact_submission">',
    $processedHtml
);

// D. Replace CSRF meta tag with static placeholder
$processedHtml = preg_replace('/<meta name="csrf-token" content="[^"]*" \/>/i', '<meta name="csrf-token" content="static-build" />', $processedHtml);

// 5. Write to /netlify/index.html
$outputHtmlFile = $netlifyDir . '/index.html';
file_put_contents($outputHtmlFile, $processedHtml);

foreach ($copyLog as $logLine) {
    echo $logLine . "\n";
}
echo "SUCCESS: Wrote " . strlen($processedHtml) . " bytes to " . $outputHtmlFile . "\n";
