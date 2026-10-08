<?php
/**
 * WebsiteTailors — 404 Not Found Page
 * 
 * Branded, production-ready user-facing 404 error page.
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
require_once __DIR__ . '/includes/init.php';

http_response_code(404);

$pageTitle = '404 — Page Not Found | WebsiteTailors';
$pageDescription = 'The digital experience or page you requested could not be found. Return to WebsiteTailors home.';
$activePage = '404';

require_once __DIR__ . '/includes/header.php';
?>

  <main id="main-content">
    <section class="hero" style="min-height: 80vh; display: flex; align-items: center; justify-content: center; text-align: center; padding: 160px 0 100px;">
      <div class="container" style="max-width: 760px; margin: 0 auto;">
        
        <div class="eyebrow" style="display: inline-flex; margin-bottom: 24px;">
          <span>ERROR 404 / NOT FOUND</span>
        </div>

        <h1 class="hero-heading" style="font-size: clamp(3.5rem, 12vw, 9rem); line-height: 0.85; margin-bottom: 28px; letter-spacing: -0.08em;">
          PAGE NOT FOUND<span style="color: var(--lime);">.</span>
        </h1>

        <p class="hero-intro" style="max-width: 520px; margin: 0 auto 40px; font-size: 16px; line-height: 1.7; color: var(--muted);">
          The digital route or resource you are looking for has been moved, renamed, or does not exist in our architecture.
        </p>

        <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
          <a href="<?= e(BASE_URL) ?>/" class="button primary magnetic">
            Return to Home <span>↗</span>
          </a>
          <a href="<?= e(BASE_URL) ?>/#services" class="button magnetic">
            Explore Services
          </a>
          <a href="<?= e(BASE_URL) ?>/#contact" class="button magnetic">
            Contact Studio
          </a>
        </div>

      </div>
    </section>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
