<?php
/**
 * WebsiteTailors - Public Footer Template
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) {
    die('Direct access not permitted.');
}

$siteSettings = $settings ?? get_all_settings();
?>
  <!-- =======================================================
       FOOTER
  ======================================================== -->
  <footer>
    <div class="container">
      <div class="footer-top">
        <div class="footer-logo">
          <?= e($siteSettings['company_name'] ?? 'Website Tailors') ?>
        </div>

        <div class="footer-links">
          <div class="footer-col">
            <div class="footer-col-title">Navigate</div>
            <a href="<?= e(BASE_URL) ?>/#home">Home</a>
            <a href="<?= e(BASE_URL) ?>/#services">Services</a>
            <a href="<?= e(BASE_URL) ?>/#work">Work</a>
            <a href="<?= e(BASE_URL) ?>/#process">Process</a>
            <a href="<?= e(BASE_URL) ?>/#about">About</a>
            <a href="<?= e(BASE_URL) ?>/#contact">Contact</a>
          </div>

          <div class="footer-col">
            <div class="footer-col-title">Connect</div>
            <?php if (!empty($siteSettings['email'])): ?>
              <a href="mailto:<?= e($siteSettings['email']) ?>"><?= e($siteSettings['email']) ?></a>
            <?php endif; ?>
            <?php if (!empty($siteSettings['phone'])): ?>
              <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $siteSettings['phone'])) ?>"><?= e($siteSettings['phone']) ?></a>
            <?php endif; ?>
            <?php if (!empty($siteSettings['linkedin'])): ?>
              <a href="<?= e($siteSettings['linkedin']) ?>" target="_blank" rel="noopener noreferrer">LinkedIn ↗</a>
            <?php endif; ?>
            <?php if (!empty($siteSettings['github'])): ?>
              <a href="<?= e($siteSettings['github']) ?>" target="_blank" rel="noopener noreferrer">GitHub ↗</a>
            <?php endif; ?>
            <?php if (!empty($siteSettings['twitter'])): ?>
              <a href="<?= e($siteSettings['twitter']) ?>" target="_blank" rel="noopener noreferrer">Twitter / X ↗</a>
            <?php endif; ?>
            <?php if (!empty($siteSettings['instagram'])): ?>
              <a href="<?= e($siteSettings['instagram']) ?>" target="_blank" rel="noopener noreferrer">Instagram ↗</a>
            <?php endif; ?>
            <?php if (!empty($siteSettings['facebook'])): ?>
              <a href="<?= e($siteSettings['facebook']) ?>" target="_blank" rel="noopener noreferrer">Facebook ↗</a>
            <?php endif; ?>
            <?php if (!empty($siteSettings['youtube'])): ?>
              <a href="<?= e($siteSettings['youtube']) ?>" target="_blank" rel="noopener noreferrer">YouTube ↗</a>
            <?php endif; ?>
          </div>

          <?php if (!empty($siteSettings['address']) || !empty($siteSettings['business_hours'])): ?>
            <div class="footer-col">
              <div class="footer-col-title">Office</div>
              <?php if (!empty($siteSettings['address'])): ?>
                <p style="font-size: 14px; color: var(--muted); line-height: 1.6; margin-bottom: 12px;">
                  <?= nl2br(e($siteSettings['address'])) ?>
                </p>
              <?php endif; ?>
              <?php if (!empty($siteSettings['business_hours'])): ?>
                <p style="font-size: 13px; font-family: 'DM Mono', monospace; color: var(--muted);">
                  <?= e($siteSettings['business_hours']) ?>
                </p>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="footer-bottom">
        <span>
          &copy; <?= date('Y') ?> <?= e(strtoupper($siteSettings['company_name'] ?? 'WEBSITE TAILORS')) ?>. ALL RIGHTS RESERVED.
        </span>
        <span>
          <?= e(strtoupper($siteSettings['tagline'] ?? 'AFFORDABLE BUSINESS WEBSITES & WHATSAPP AUTOMATION IN BANGALORE')) ?>
        </span>
      </div>
    </div>
  </footer>

  <!-- Floating WhatsApp CTA (Phase 17) -->
  <a href="https://wa.me/919380552034?text=Hi%20Website%20Tailors%2C%20I%20would%20like%20a%20free%20quote" class="whatsapp-float" target="_blank" rel="noopener noreferrer" aria-label="Chat on WhatsApp">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" aria-hidden="true"><path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3 18.6-68.1-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.9-186.6 184.9zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/></svg>
  </a>

  <!-- Vanilla JavaScript -->
  <script src="<?= e(ASSETS_URL . '/js/main.js') ?>" defer></script>
</body>
</html>
