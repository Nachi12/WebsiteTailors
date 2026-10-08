<?php
/**
 * Website Tailors — 500 Server Error Page
 * 
 * Branded, production-ready user-facing 500 error page.
 * Strictly avoids leaking database credentials, server filepaths, or stack traces.
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) {
    define('WebsiteTailors_INIT', true);
}

// Attempt to load core configuration safely
if (file_exists(__DIR__ . '/config/config.php')) {
    require_once __DIR__ . '/config/config.php';
}

if (!headers_sent()) {
    http_response_code(500);
}

$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$assetsUrl = defined('ASSETS_URL') ? ASSETS_URL : ($baseUrl . '/assets');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>500 — Server Error | Website Tailors</title>
  <meta name="robots" content="noindex, nofollow" />
  
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?= htmlspecialchars($assetsUrl, ENT_QUOTES, 'UTF-8') ?>/css/style.css" />
</head>
<body>

  <main id="main-content">
    <section class="hero" style="min-height: 100vh; display: flex; align-items: center; justify-content: center; text-align: center; padding: 120px 20px;">
      <div class="container" style="max-width: 720px; margin: 0 auto;">
        
        <div class="eyebrow" style="display: inline-flex; margin-bottom: 24px;">
          <span>ERROR 500 / TECHNICAL NOTICE</span>
        </div>

        <h1 class="hero-heading" style="font-size: clamp(3rem, 11vw, 8rem); line-height: 0.88; margin-bottom: 28px; letter-spacing: -0.08em;">
          SOMETHING WENT WRONG<span style="color: var(--lime);">.</span>
        </h1>

        <p class="hero-intro" style="max-width: 500px; margin: 0 auto 40px; font-size: 16px; line-height: 1.7; color: var(--muted);">
          Our application encountered an unexpected internal error. The incident has been recorded in our server logs and our engineering team has been notified.
        </p>

        <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
          <a href="<?= htmlspecialchars($baseUrl ?: '/', ENT_QUOTES, 'UTF-8') ?>" class="button primary magnetic">
            Return to Home <span>↗</span>
          </a>
          <a href="<?= htmlspecialchars($baseUrl ?: '/', ENT_QUOTES, 'UTF-8') ?>/#contact" class="button magnetic">
            Contact Support
          </a>
        </div>

      </div>
    </section>
  </main>

</body>
</html>
