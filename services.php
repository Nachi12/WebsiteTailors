<?php
/**
 * WebsiteTailors — Services Showcase Page
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
require_once __DIR__ . '/includes/init.php';

$settings = get_all_settings();
$services = get_services();

$pageTitle = 'Website Design & Redesign Services in Bangalore | Website Tailors';
$pageDescription = 'Website Tailors offers Website Design for New Businesses, Website Redesign, WhatsApp Automation, and Website + WhatsApp packages in Bangalore.';
$activePage = 'services';

require_once __DIR__ . '/includes/header.php';
?>

  <main id="main-content" style="padding-top: 140px;">
    <section class="services" style="padding-top: 40px; padding-bottom: 120px;">
      <div class="container">
        <div class="eyebrow" style="margin-bottom: 20px;">Core Capabilities</div>
        <h1 class="section-title" style="margin-bottom: 30px;">
          ENGINEERED<br>
          <span class="outline">SERVICES.</span>
        </h1>
        <p class="services-description" style="max-width: 580px; width: 100%; font-size: 16px; margin-bottom: 60px;">
          We build reliable digital systems designed to drive measurable business impact. Every service is delivered with clean vanilla technologies, high performance, and long-term scalability.
        </p>

        <?php if (empty($services)): ?>
          <div class="empty-state-public">
            <div class="empty-state-icon">✦</div>
            <h3>Services Updating</h3>
            <p>We are currently updating our bespoke service capabilities. Please reach out directly to discuss your specific technical needs.</p>
            <a href="contact.php" class="button button-primary magnetic">Inquire Directly ↗</a>
          </div>
        <?php else: ?>
          <div class="services-list">
            <?php foreach ($services as $index => $service): ?>
              <?php $serviceNum = str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT); ?>
              <div class="service reveal" style="padding: 40px 0; grid-template-columns: 80px 1fr auto;">
                <div class="service-number" style="font-size: 14px;">
                  <?= e($serviceNum) ?>
                </div>

                <div class="service-content" style="display: block;">
                  <h2 class="service-title" style="font-size: clamp(2.5rem, 5vw, 4.5rem);">
                    <?= e($service['title']) ?>
                  </h2>

                  <p class="service-text" style="max-width: 600px; font-size: 15px; margin-top: 12px; color: #575751;">
                    <?= e($service['short_description']) ?>
                  </p>

                  <?php if (!empty($service['long_description'])): ?>
                    <p style="font-size: 14px; color: var(--muted); margin-top: 12px; line-height: 1.7; max-width: 650px;">
                      <?= e($service['long_description']) ?>
                    </p>
                  <?php endif; ?>

                  <?php if (!empty($service['features'])): ?>
                    <div style="margin-top: 18px; display: flex; flex-wrap: wrap; gap: 8px;">
                      <?php foreach (explode("\n", (string)$service['features']) as $feat): ?>
                        <?php if (trim($feat)): ?>
                          <span style="display: inline-block; background: #e5e4dc; padding: 4px 10px; border-radius: 6px; font-family: 'DM Mono', monospace; font-size: 11px; color: #444;">
                            <?= e(trim($feat)) ?>
                          </span>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>

                <div style="padding-left: 20px;">
                  <a href="contact.php?service=<?= urlencode($service['title']) ?>" class="button button-primary magnetic" style="min-width: 140px; padding: 12px 20px;">
                    Start ↗
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div style="margin-top: 80px; text-align: center;" class="reveal">
          <a href="contact.php" class="button button-primary magnetic" style="padding: 20px 36px; font-size: 15px;">
            Discuss a Custom Project ↗
          </a>
        </div>
      </div>
    </section>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
