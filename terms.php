<?php
/**
 * WebsiteTailors - Terms of Service
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
require_once __DIR__ . '/includes/init.php';

$siteSettings = get_all_settings();
$pageTitle = 'Terms of Service | Website Tailors';
$pageDescription = 'Terms of Service for Website Tailors. Fair, honest, and transparent terms for small business website design and WhatsApp automation.';
$activePage = 'terms';

require_once __DIR__ . '/includes/header.php';
?>

  <main id="main-content" class="service-page">
    <section class="page-hero">
      <div class="container">
        <div class="page-hero-eyebrow">
          <span class="eyebrow-dot" aria-hidden="true"></span>
          <span>Legal &amp; Transparency</span>
        </div>
        <h1 class="page-hero-title">Terms of Service</h1>
        <p class="page-hero-lead">
          Last updated: October 2026. These straightforward terms govern our website design, redesign, and WhatsApp automation services for business clients.
        </p>
      </div>
    </section>

    <section class="service-content-section">
      <div class="container content-container">
        <div class="content-block">
          <h2>1. Engagement &amp; Project Scope</h2>
          <p>
            All website design, redesign, and automation projects are agreed upon in writing with a clear statement of deliverables, page counts, features, and timeline before work begins. Any features requested outside the initial agreed scope will be quoted transparently before execution.
          </p>
        </div>

        <div class="content-block">
          <h2>2. Payment Terms &amp; Milestones</h2>
          <ul class="check-list">
            <li><strong>Two-Part Payment:</strong> Projects are divided into a 50% advance deposit to commence development and reserve sprint time, and the remaining 50% due only after you review the completed staging preview and approve the site for live launch.</li>
            <li><strong>No Hidden Fees:</strong> Quotations clearly state what is included. Third-party domain registration and hosting fees are billed directly by your registrar so you retain ownership.</li>
          </ul>
        </div>

        <div class="content-block">
          <h2>3. Ownership &amp; Intellectual Property</h2>
          <p>
            Upon receipt of final payment, <strong>100% full ownership</strong> of all custom website code, design assets, and administrative credentials transfers directly to you. We do not hold client domains or code hostage, and there is zero platform lock-in.
          </p>
        </div>

        <div class="content-block">
          <h2>4. Project Timeline &amp; Client Assets</h2>
          <p>
            Our standard Starter Website sprint is 7 business days. This timeline relies on prompt submission of necessary business information, service descriptions, and photos. Delays in receiving essential client content may naturally extend the scheduled launch date.
          </p>
        </div>

        <div class="content-block">
          <h2>5. 14-Day Post-Launch Warranty</h2>
          <p>
            We include a complimentary 14-day warranty period immediately following live launch. During this time, any layout inconsistencies, broken links, or form bugs will be fixed promptly at no additional charge.
          </p>
        </div>

        <div class="content-block">
          <h2>6. Governing Law</h2>
          <p>
            These terms are governed by the laws of Karnataka, India. Any disputes shall be subject to the exclusive jurisdiction of the competent courts in Bengaluru, Karnataka.
          </p>
        </div>
      </div>
    </section>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
