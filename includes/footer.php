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
  <footer class="site-footer">
    <div class="site-footer__inner container">
      <!-- Giant Brand Wordmark -->
      <h2 class="footer-brand-title">
        <?= e($siteSettings['company_name'] ?? 'Website Tailors') ?>
      </h2>
      <!-- Middle Content Grid: Tagline Left, Direct Contact Right -->
      <div class="footer-content-grid">
        <div class="footer-brand-col">
          <p class="footer-tagline">
            Website Tailors: Website design, redesign and WhatsApp automation for small businesses in Bangalore.
          </p>
        </div>

        <div class="footer-contact-col">
          <div class="footer-direct-label">DIRECT CONTACT</div>
          <div class="footer-contact-row">
            <a href="mailto:<?= e(defined('BUSINESS_EMAIL') ? BUSINESS_EMAIL : ($siteSettings['email'] ?? 'websitetailorss@gmail.com')) ?>" class="footer-contact-link">
              <?= e(defined('BUSINESS_EMAIL') ? BUSINESS_EMAIL : ($siteSettings['email'] ?? 'websitetailorss@gmail.com')) ?>
            </a>
            <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $siteSettings['phone'] ?? '+919380552034')) ?>" class="footer-contact-link">
              +91 93805 52034
            </a>
            <a href="https://wa.me/919380552034?text=Hi%2C%20I%27d%20like%20a%20quote%20for%20a%20website" class="footer-contact-link footer-contact-wa" target="_blank" rel="noopener noreferrer">
              WhatsApp Us ↗
            </a>
          </div>
        </div>
      </div>


      <!-- Legal & Copyright Bar -->
      <div class="footer-bottom-bar">
        <span class="footer-copy">
          &copy; 2026 Website Tailors. All rights reserved.
        </span>
        <div class="footer-legal">
          <a href="<?= e(BASE_URL) ?>/privacy-policy">Privacy Policy</a>
          <span class="legal-dot">·</span>
          <a href="<?= e(BASE_URL) ?>/terms">Terms of Service</a>
        </div>
      </div>
    </div>
  </footer>

  <!-- Sticky Mobile Action Bar (Requirement 7) -->
  <nav class="mobile-sticky-bar" aria-label="Mobile Quick Action Bar">
    <a href="tel:+919380552034" class="mobile-sticky-btn mobile-call-btn" aria-label="Call Website Tailors">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
      <span>Call</span>
    </a>
    <a href="https://wa.me/919380552034?text=Hi%2C%20I%27d%20like%20a%20quote%20for%20a%20website" class="mobile-sticky-btn mobile-wa-btn" target="_blank" rel="noopener noreferrer" aria-label="Chat on WhatsApp">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.335-1.662a11.87 11.87 0 005.705 1.454h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
      <span>WhatsApp</span>
    </a>
  </nav>

  <!-- Floating WhatsApp CTA (Phase 17) -->
  <a href="https://wa.me/919380552034?text=Hi%2C%20I%27d%20like%20a%20quote%20for%20a%20website" class="whatsapp-float" target="_blank" rel="noopener noreferrer" aria-label="Chat on WhatsApp">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" aria-hidden="true"><path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3 18.6-68.1-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.9-186.6 184.9zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/></svg>
  </a>

  <!-- Vanilla JavaScript -->
  <script src="<?= e(ASSETS_URL . '/js/main.js?v=' . APP_VERSION) ?>" defer></script>
</body>
</html>
<?php
// Release session lock immediately upon completing HTML output
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
?>
