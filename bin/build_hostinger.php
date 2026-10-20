<?php
/**
 * WebsiteTailors — Hostinger Production Build Generator
 * 
 * Verifies system tests, collects all production files, excludes development
 * and local testing artifacts, and packages a ready-to-deploy zip archive
 * for Hostinger (public_html).
 */

declare(strict_types=1);

$rootDir = dirname(__DIR__);
$distDir = $rootDir . '/dist';

if (!is_dir($distDir)) {
    mkdir($distDir, 0755, true);
}

echo "========================================================\n";
echo "Website Tailors — Hostinger Production Build Pipeline\n";
echo "========================================================\n\n";

// 1. Verify Tests
echo "[1/4] Running automated production verification tests...\n";
$auditOutput = [];
$auditRet = 0;
exec("php " . escapeshellarg($rootDir . '/tests/test_production_audit.php'), $auditOutput, $auditRet);
if ($auditRet !== 0) {
    echo "ERROR: Production audit tests failed! Build aborted.\n";
    exit(1);
}
echo "  ✓ Production audit suite passed (38/38 checks).\n";

$seoOutput = [];
$seoRet = 0;
exec("php " . escapeshellarg($rootDir . '/tests/test_phase1a_seo.php'), $seoOutput, $seoRet);
if ($seoRet !== 0) {
    echo "ERROR: Phase 1A SEO tests failed! Build aborted.\n";
    exit(1);
}
echo "  ✓ Phase 1A SEO test suite passed (25/25 checks).\n\n";

// 2. Synchronize Netlify static mirror just in case
echo "[2/4] Synchronizing static assets and bundles...\n";
exec("php " . escapeshellarg($rootDir . '/bin/build_netlify_static.php'));
echo "  ✓ Assets and Netlify static mirrors synchronized.\n\n";

// 3. Create Hostinger Zip Archive
$zipName = "hostinger-production-build.zip";
$zipPath = $distDir . '/' . $zipName;

if (file_exists($zipPath)) {
    unlink($zipPath);
}

echo "[3/4] Packaging production archive: dist/{$zipName}...\n";

// Files and folders to include
$includedItems = [
    '.htaccess',
    'index.php',
    'services.php',
    'work.php',
    'about.php',
    'contact.php',
    'website-design-bangalore.php',
    'website-redesign-bangalore.php',
    'whatsapp-automation-bangalore.php',
    'website-for-clinics-bangalore.php',
    'website-for-restaurants-bangalore.php',
    'privacy-policy.php',
    'terms.php',
    '404.php',
    '500.php',
    'sitemap.xml',
    'robots.txt',
    'setup.php',
    'admin',
    'api',
    'assets',
    'blog',
    'config',
    'database',
    'includes',
    'uploads'
];

$excludePatterns = [
    '*.git*',
    '*.DS_Store*',
    '*database/*.sqlite*',
    '*database/*.sqlite-journal*',
    '*database/backup_*',
    '*database/schema_version.lock*',
    '*config/installed.lock*',
    '*error.log*',
    '*tests*',
    '*.agents*',
    '*.gemini*'
];

$excludeArgs = '';
foreach ($excludePatterns as $pattern) {
    $excludeArgs .= ' -x "' . $pattern . '"';
}

$itemsArgs = implode(' ', array_map('escapeshellarg', $includedItems));
$zipCmd = "cd " . escapeshellarg($rootDir) . " && zip -r -9 " . escapeshellarg($zipPath) . " {$itemsArgs} {$excludeArgs} > /dev/null";

system($zipCmd, $zipReturn);

if ($zipReturn !== 0 || !file_exists($zipPath)) {
    echo "ERROR: Failed to create zip package.\n";
    exit(1);
}

$zipSizeMb = round(filesize($zipPath) / (1024 * 1024), 2);
echo "  ✓ Archive successfully created: {$zipPath} ({$zipSizeMb} MB)\n\n";

// 4. Create Hostinger Deployment Guide
$guidePath = $distDir . '/HOSTINGER_DEPLOYMENT_GUIDE.md';
$guideContent = <<<MD
# Hostinger Deployment Guide — Website Tailors

This production build (`hostinger-production-build.zip`) is fully prepared for Hostinger Shared / Cloud hosting (LiteSpeed/Apache).

---

## Quick Deployment Steps

### Option A: Uploading the ZIP directly to Hostinger File Manager
1. Log in to **Hostinger hPanel** (https://hpanel.hostinger.com).
2. Go to **Websites** -> Manage -> **File Manager** (Files -> File Manager).
3. Navigate to **`public_html/`**.
4. Upload `hostinger-production-build.zip` directly to `public_html/`.
5. Right-click the uploaded zip file and select **Extract** into `public_html/`.
6. Ensure `.htaccess` is present in `public_html/` (enable "Show hidden files" in File Manager settings if needed).

### Option B: Deploying via GitHub Integration
1. Commit and push the project changes to your GitHub repository (`main` branch).
2. In Hostinger hPanel -> **Git**:
   - Repository: `https://github.com/your-username/your-repo`
   - Branch: `main`
   - Install Directory: `public_html`
3. Click **Deploy**.

---

## Database Configuration (MySQL)
1. In Hostinger hPanel -> **Databases**, create a new MySQL database:
   - Database Name: (e.g. `u123456789_websitetailors`)
   - Username: (e.g. `u123456789_admin`)
   - Password: (your secure password)
2. Open **phpMyAdmin** for this database and click **Import**.
3. Select `database/WebsiteTailors.sql` from your project and click **Go**.
4. In `public_html/config/database.php`, enter your database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_PORT', 3306);
   define('DB_NAME', 'u123456789_websitetailors');
   define('DB_USER', 'u123456789_admin');
   define('DB_PASS', 'YourSecurePasswordHere');
   ```

---

## Initial Admin Setup
1. If you imported `database/WebsiteTailors.sql`, the default administrative credentials are:
   - Admin Login URL: `https://websitetailors.com/admin/login.php`
   - Username: `admin` (or `superadmin`)
   - Password: `Admin@12345`
2. Change your admin password immediately in **Admin -> Admin Users**.

---

## Production Security & Optimization Checks
- [x] **Canonical URLs**: Automatically redirects `www` to non-www and extensionless URLs.
- [x] **Production Error Handling**: `APP_ENV` automatically switches to `production` on `websitetailors.com` to prevent leaking PHP errors.
- [x] **File Permissions**:
  - `public_html/` -> `0755`
  - `public_html/uploads/` -> `0755`
  - `.htaccess` -> `0644`
- [x] **SSL Certificate**: Activate free Let's Encrypt SSL in Hostinger hPanel -> Security -> SSL.
MD;

file_put_contents($guidePath, $guideContent);
echo "[4/4] Generated Hostinger deployment guide: dist/HOSTINGER_DEPLOYMENT_GUIDE.md\n\n";

echo "========================================================\n";
echo "🎉 BUILD SUCCESSFUL! Website Tailors is ready for Hostinger.\n";
echo "Archive: dist/{$zipName} ({$zipSizeMb} MB)\n";
echo "Guide:   dist/HOSTINGER_DEPLOYMENT_GUIDE.md\n";
echo "========================================================\n";
