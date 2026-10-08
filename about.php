<?php
/**
 * Website Tailors — About & Agency Philosophy Page
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
require_once __DIR__ . '/includes/init.php';

$settings     = get_all_settings();
$principles   = get_principles();
$processSteps = get_process_steps();
$testimonials = get_testimonials();

$pageTitle = 'About Website Tailors | Website Design Studio in Bangalore';
$pageDescription = 'Website Tailors designs and builds fast, practical, affordable business websites and WhatsApp automation for businesses in Bangalore.';
$activePage = 'about';

require_once __DIR__ . '/includes/header.php';
?>

  <main id="main-content" style="padding-top: 140px;">
    <!-- Statement Section -->
    <section class="statement" style="padding: 120px 0;">
      <div class="container">
        <div class="eyebrow">Our Philosophy</div>

        <h1 class="statement-title" style="margin-top: 40px;">
          <div>
            <span>YOUR BUISINESS</span>
          </div>
          <div>
            <span>SHOULDN'T</span>
          </div>
          <div>
            <span>STAY IN <strong class="lime">IDLE.</strong></span>
          </div>
        </h1>

        <p class="statement-copy">
          Most digital projects fail due to unnecessary complexity, bloated dependencies, or lack of architectural discipline. Website Tailors was founded to provide a refreshing alternative: lean, blisteringly fast, beautifully designed systems that do their job flawlessly.
        </p>
      </div>
    </section>

    <!-- Principles Section -->
    <section class="principles" style="padding: 120px 0;">
      <div class="container">
        <div class="principles-heading reveal">
          <div class="eyebrow">Guiding Principles</div>
          <h2 class="section-title" style="margin-top: 30px;">
            NO BIG WORDS.<br>
            <span class="outline">JUST GOOD WORK.</span>
          </h2>
        </div>

        <div class="principles-grid">
          <?php foreach ($principles as $principle): ?>
            <div class="principle reveal">
              <div class="principle-number">
                <?= e($principle['number']) ?>
              </div>
              <h3>
                <?= e($principle['title']) ?>
              </h3>
              <p>
                <?= e($principle['description']) ?>
              </p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- Process Section -->
    <section class="process" style="padding: 120px 0;">
      <div class="container">
        <div class="process-header reveal">
          <div class="process-header-left">
            <div class="eyebrow">How we work</div>
            <h2 class="section-title process-title">
              FROM IDEA<br>
              <span class="outline">TO LIVE.</span>
            </h2>
          </div>

          <p class="process-copy">
            A battle-tested 4-step delivery pipeline with zero friction and clear communication.
          </p>
        </div>

        <?php if (empty($processSteps)): ?>
          <div class="empty-state-public">
            <div class="empty-state-icon">✦</div>
            <h3>Custom Tailored Process</h3>
            <p>Every digital engagement is architected around your specific engineering milestones and delivery requirements.</p>
            <a href="contact.php" class="button button-primary magnetic">Discuss Engagement ↗</a>
          </div>
        <?php else: ?>
          <div class="process-track">
            <div class="process-line" aria-hidden="true"></div>
            <div class="process-progress" id="processProgress" aria-hidden="true"></div>

            <?php foreach ($processSteps as $step): ?>
              <div class="process-step reveal">
                <div class="process-dot">
                  <?= e($step['step_number']) ?>
                </div>
                <h3>
                  <?= e($step['title']) ?>
                </h3>
                <p>
                  <?= e($step['description']) ?>
                </p>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- Testimonials Section -->
    <?php if (!empty($testimonials)): ?>
      <section class="testimonials" style="padding: 120px 0;">
        <div class="container">
          <div class="testimonials-header reveal">
            <div>
              <div class="eyebrow">Client Feedback</div>
              <h2 class="section-title" style="margin-top: 30px;">
                WHAT FOUNDERS<br>
                <span class="outline">SAY ABOUT US.</span>
              </h2>
            </div>
            <p style="color: var(--muted); max-width: 440px; font-size: 15px; line-height: 1.6;">
              Direct feedback from leaders who have partnered with Website Tailors.
            </p>
          </div>

          <div class="testimonials-grid">
            <?php foreach ($testimonials as $t): ?>
              <div class="testimonial-card reveal">
                <div>
                  <div class="testimonial-rating" aria-label="Rating: <?= (int)($t['rating'] ?? 5) ?> of 5 stars">
                    <?= str_repeat('★', max(1, min(5, (int)($t['rating'] ?? 5)))) ?>
                  </div>
                  <p class="testimonial-quote">
                    <?= e($t['content']) ?>
                  </p>
                </div>

                <div class="testimonial-meta">
                  <img src="<?= e(get_image_url($t['image'] ?? null, 'avatar')) ?>" alt="<?= e($t['client_name']) ?>" class="testimonial-avatar" loading="lazy" />
                  <div>
                    <div class="testimonial-author-name"><?= e($t['client_name']) ?></div>
                    <div class="testimonial-author-sub">
                      <?= e($t['position'] ?? '') ?><?= (!empty($t['position']) && !empty($t['company'])) ? ', ' : '' ?><?= e($t['company'] ?? '') ?>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <!-- Call to action -->
    <section class="cta" style="padding: 100px 0;">
      <div class="container cta-grid" style="grid-template-columns: 1fr auto; align-items: center;">
        <div>
          <h2 class="cta-title" style="font-size: clamp(3rem, 6vw, 6rem); margin-top: 0;">
            LET'S MAKE<br>IT WORK.
          </h2>
          <p class="cta-subtitle" style="margin-top: 20px;">
            Have a project in mind? We'd love to learn about what you're building.
          </p>
        </div>
        <div>
          <a href="contact.php" class="cta-button magnetic" style="margin-top: 0; background: var(--black); color: white; padding: 22px 36px;">
            Start a Conversation ↗
          </a>
        </div>
      </div>
    </section>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
