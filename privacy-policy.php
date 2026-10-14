<?php
/**
 * WebsiteTailors - Privacy Policy
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
require_once __DIR__ . '/includes/init.php';

$siteSettings = get_all_settings();
$pageTitle = 'Privacy Policy | Website Tailors';
$pageDescription = 'Privacy Policy for Website Tailors. We respect your privacy and never sell or share your contact information.';
$activePage = 'privacy';

require_once __DIR__ . '/includes/header.php';
?>

  <main id="main-content" class="service-page">
    <section class="page-hero">
      <div class="container">
        <div class="page-hero-eyebrow">
          <span class="eyebrow-dot" aria-hidden="true"></span>
          <span>Legal &amp; Transparency</span>
        </div>
        <h1 class="page-hero-title">Privacy Policy</h1>
        <p class="page-hero-lead">
          Last updated: October 2026. This policy explains how Website Tailors collects, uses, and protects your information when you visit our website or submit an enquiry.
        </p>
      </div>
    </section>

    <section class="service-content-section">
      <div class="container content-container">
        <div class="content-block">
          <h2>1. Information We Collect</h2>
          <p>We collect information only when you voluntarily provide it through our website quote form, email, or WhatsApp chat. This typically includes:</p>
          <ul class="check-list">
            <li>Your name and contact phone / WhatsApp number</li>
            <li>Your business or brand name</li>
            <li>Your email address (if provided)</li>
            <li>Project details, preferences, and requirements you submit</li>
          </ul>
        </div>

        <div class="content-block">
          <h2>2. How We Use Your Information</h2>
          <p>We use your contact details solely for the following business purposes:</p>
          <ul class="check-list">
            <li>To review your requirements and provide an accurate project proposal and quote</li>
            <li>To communicate with you regarding your website design or WhatsApp automation project</li>
            <li>To coordinate project feedback, staging previews, domain settings, and handover</li>
          </ul>
          <p><strong>We do not sell, rent, trade, or share your personal information with third-party marketers or advertisers under any circumstances.</strong></p>
        </div>

        <div class="content-block">
          <h2>3. Cookies &amp; Tracking Technologies</h2>
          <p>
            Website Tailors uses only essential session cookies necessary for website security and CSRF protection. We do not use intrusive third-party cross-site advertising cookies or tracking pixels.
          </p>
        </div>

        <div class="content-block">
          <h2>4. Data Security &amp; Retention</h2>
          <p>
            We implement security measures including parameterized database queries, HTTPS encryption, and access controls to prevent unauthorized access to your information. We retain your contact information only as long as necessary to fulfill our business services or comply with Indian legal accounting obligations.
          </p>
        </div>

        <div class="content-block">
          <h2>5. Contacting Us About Your Data</h2>
          <p>
            If you have any questions about this Privacy Policy or wish to have your contact details updated or deleted from our records, please contact our founder directly:
          </p>
          <p style="margin-top: 12px; color: var(--text);">
            <strong>Website Tailors</strong><br>
            Email: <?= e(defined('BUSINESS_EMAIL') ? BUSINESS_EMAIL : 'websitetailorss@gmail.com') ?><br>
            Phone / WhatsApp: +91 93805 52034<br>
            Bengaluru, Karnataka 560010, India
          </p>
        </div>
      </div>
    </section>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
