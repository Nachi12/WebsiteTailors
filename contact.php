<?php
/**
 * Website Tailors — Contact & Project Inquiries Page
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
require_once __DIR__ . '/includes/init.php';

$settings = get_all_settings();
$services = get_services();

$preselectedService = sanitize_text($_GET['service'] ?? '');
$preselectedProject = sanitize_text($_GET['project'] ?? '');

$contactSuccess = null;
$contactError   = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $result = process_lead_inquiry($_POST);
    if ($result['success']) {
        $contactSuccess = $result['message'];
    } else {
        $contactError = $result['error'];
    }
}

$pageTitle = 'Get a Free Website Quote | Website Tailors Bangalore';
$pageDescription = 'Get a free quote for website design, website redesign, or WhatsApp automation in Bangalore. Contact Website Tailors today.';
$activePage = 'contact';

require_once __DIR__ . '/includes/header.php';
?>

  <main id="main-content" style="padding-top: 140px;">
    <section class="cta" style="padding-top: 40px; padding-bottom: 140px; min-height: 80vh;">
      <div class="container cta-grid">
        <div class="cta-inner reveal">
          <div class="cta-eyebrow">GET IN TOUCH</div>
          <h1 class="cta-title">
            LET'S<br>
            Website Tailors.
          </h1>

          <p class="cta-subtitle">
            Tell us about your next project, challenge, or digital vision. We'll examine the technical architecture and get back to you with actionable recommendations.
          </p>

          <div class="cta-contact-info" style="margin-top: 50px;">
            <?php if (!empty($settings['email'])): ?>
              <a href="mailto:<?= e($settings['email']) ?>">
                <span>Email:</span> <?= e($settings['email']) ?> ↗
              </a>
            <?php endif; ?>
            <?php if (!empty($settings['phone'])): ?>
              <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $settings['phone'])) ?>">
                <span>Call:</span> <?= e($settings['phone']) ?> ↗
              </a>
            <?php else: ?>
              <a href="tel:9380552034">
                <span>Call:</span> 9380552034 ↗
              </a>
            <?php endif; ?>
            <span>Location: <?= e($settings['address'] ?? 'Bangalore-560010, karnataka. India') ?></span>
          </div>
        </div>

        <!-- Contact Form Card -->
        <div class="contact-form-card reveal">
          <h2 class="contact-form-title">Start a Project</h2>
          <p class="contact-form-desc">Fill out the details below and we will get back to you within 24 hours.</p>

          <div id="formFeedback">
            <?php if ($contactSuccess): ?>
              <div class="form-alert form-alert-success">
                <strong>Success!</strong> <?= e($contactSuccess) ?>
              </div>
            <?php endif; ?>
            <?php if ($contactError): ?>
              <div class="form-alert form-alert-error">
                <strong>Notice:</strong> <?= e($contactError) ?>
              </div>
            <?php endif; ?>
          </div>

          <form id="contactForm" method="POST" action="contact.php" novalidate>
            <?= csrf_field() ?>

            <!-- Anti-Bot Spam Honeypot Field -->
            <div class="hp-field" aria-hidden="true">
              <label for="website_url">Leave this field blank</label>
              <input type="text" id="website_url" name="website_url" autocomplete="off" tabindex="-1">
            </div>

            <div class="form-grid">
              <!-- Name -->
              <div class="form-group">
                <label for="name" class="form-label">Name *</label>
                <input type="text" id="name" name="name" class="form-control" placeholder="Rahul Sharma" required minlength="2" maxlength="100" value="<?= e($_POST['name'] ?? '') ?>">
              </div>

              <!-- Email -->
              <div class="form-group">
                <label for="email" class="form-label">Email Address *</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="rahul@company.com" required maxlength="150" value="<?= e($_POST['email'] ?? '') ?>">
              </div>

              <!-- Phone -->
              <div class="form-group">
                <label for="phone" class="form-label">Phone</label>
                <input type="tel" id="phone" name="phone" class="form-control" placeholder="9380552034" maxlength="50" value="<?= e($_POST['phone'] ?? '') ?>">
              </div>

              <!-- Company -->
              <div class="form-group">
                <label for="company" class="form-label">Company</label>
                <input type="text" id="company" name="company" class="form-control" placeholder="ABC Technologies" maxlength="100" value="<?= e($_POST['company'] ?? '') ?>">
              </div>

              <!-- Service -->
              <div class="form-group">
                <label for="service" class="form-label">Service of Interest</label>
                <select id="service" name="service" class="form-control">
                  <?php if (!empty($services)): ?>
                    <?php foreach ($services as $srv): ?>
                      <option value="<?= e($srv['title']) ?>" <?= ($preselectedService === $srv['title'] || ($_POST['service'] ?? '') === $srv['title']) ? 'selected' : '' ?>><?= e($srv['title']) ?></option>
                    <?php endforeach; ?>
                    <option value="Consulting / Other" <?= ($preselectedService === 'Consulting / Other' || ($_POST['service'] ?? '') === 'Consulting / Other') ? 'selected' : '' ?>>Consulting / Other</option>
                  <?php else: ?>
                    <option value="Website Development" <?= ($preselectedService === 'Website Development' || ($_POST['service'] ?? '') === 'Website Development') ? 'selected' : '' ?>>Website Development</option>
                    <option value="Website Refinement" <?= ($preselectedService === 'Website Refinement' || ($_POST['service'] ?? '') === 'Website Refinement') ? 'selected' : '' ?>>Website Refinement</option>
                    <option value="WhatsApp Automation" <?= ($preselectedService === 'WhatsApp Automation' || ($_POST['service'] ?? '') === 'WhatsApp Automation') ? 'selected' : '' ?>>WhatsApp Automation</option>
                    <option value="Consulting / Other" <?= ($preselectedService === 'Consulting / Other' || ($_POST['service'] ?? '') === 'Consulting / Other') ? 'selected' : '' ?>>Consulting / Other</option>
                  <?php endif; ?>
                </select>
              </div>

              <!-- Budget -->
              <div class="form-group">
                <label for="budget" class="form-label">Estimated Budget</label>
                <select id="budget" name="budget" class="form-control">
                  <option value="< ₹1,00,000" <?= (($_POST['budget'] ?? '') === '< ₹1,00,000') ? 'selected' : '' ?>>&lt; ₹1,00,000</option>
                  <option value="₹1,00,000 - ₹3,00,000" <?= (($_POST['budget'] ?? '') === '₹1,00,000 - ₹3,00,000' || empty($_POST['budget'])) ? 'selected' : '' ?>>₹1,00,000 – ₹3,00,000</option>
                  <option value="₹3,00,000 - ₹5,00,000" <?= (($_POST['budget'] ?? '') === '₹3,00,000 - ₹5,00,000') ? 'selected' : '' ?>>₹3,00,000 – ₹5,00,000</option>
                  <option value="₹5,00,000+" <?= (($_POST['budget'] ?? '') === '₹5,00,000+') ? 'selected' : '' ?>>₹5,00,000+</option>
                </select>
              </div>

              <!-- Message -->
              <div class="form-group full">
                <label for="message" class="form-label">Project Details *</label>
                <textarea id="message" name="message" class="form-control" rows="4" placeholder="Tell us what you're trying to build or improve..." required minlength="5" maxlength="5000"><?= e($_POST['message'] ?? ($preselectedProject ? "Inquiring regarding: " . $preselectedProject : '')) ?></textarea>
              </div>
            </div>

            <button type="submit" class="btn-form-submit magnetic">
              <span>Submit Inquiry</span>
              <span aria-hidden="true">↗</span>
            </button>
          </form>
        </div>
      </div>
    </section>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
