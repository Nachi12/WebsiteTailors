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

$pageTitle = 'About Website Tailors | Our Process & Approach';
$pageDescription = 'Learn about Website Tailors, our approach to website design and development, and how we plan, build, review and hand over each project.';
$activePage = 'about';

require_once __DIR__ . '/includes/header.php';
?>

  <main id="main-content" style="padding-top: 140px;">
    <!-- Statement Section -->
    <section class="statement" style="padding: 120px 0;">
      <div class="container">
        <div class="eyebrow">Our Approach</div>

        <h1 class="statement-title" style="margin-top: 40px;">
          <div>
            <span>YOUR BUSINESS</span>
          </div>
          <div>
            <span>SHOULDN'T</span>
          </div>
          <div>
            <span>STAY IN <strong class="lime">IDLE.</strong></span>
          </div>
        </h1>

        <p class="statement-copy">
          We believe business websites should be simple, fast, and transparent. Too many small businesses in Bangalore get locked into bloated website builders or overcomplicated monthly subscriptions. Website Tailors builds clean, custom websites that you own completely—with no ongoing lock-ins, clear upfront scopes, and direct communication.
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
          <?php foreach ($principles as $index => $principle): ?>
            <div class="principle reveal">
              <div class="principle-body">
                <div class="principle-content">
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
                <?php if (!empty($principle['illustration'])): ?>
                  <div class="principle-illustration" aria-hidden="true">
                    <?= get_principle_illustration_svg($principle['illustration']) ?>
                  </div>
                <?php endif; ?>
              </div>
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
              FROM FIRST CONVERSATION<br>
              <span class="outline">TO WEBSITE LAUNCH.</span>
            </h2>
          </div>

          <p class="process-copy">
            A clear, collaborative 4-step delivery process with agreed deliverables and direct communication.
          </p>
        </div>

        <?php if (empty($processSteps)): ?>
          <div class="empty-state-public">
            <div class="empty-state-icon">✦</div>
            <h3>Custom Tailored Process</h3>
            <p>Every website project is planned around your specific business milestones and timeline requirements.</p>
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

    <!-- Working Standards & Ownership Guarantees -->
    <section class="testimonials" style="padding: 120px 0;">
      <div class="container">
        <div class="testimonials-header reveal">
          <div>
            <div class="eyebrow">Client Working Standards</div>
            <h2 class="section-title" style="margin-top: 30px;">
              TRANSPARENT PROCESS.<br>
              <span class="outline">ZERO LOCK-IN.</span>
            </h2>
          </div>
          <p style="color: var(--muted); max-width: 480px; font-size: 15px; line-height: 1.6;">
            We operate with clear communication, documented milestones, and full asset handover on every project.
          </p>
        </div>

        <div class="testimonials-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-top: 48px;">
          <div class="testimonial-card reveal" style="padding: 36px 32px;">
            <div style="font-family: 'DM Mono', monospace; font-size: 12px; color: var(--accent); margin-bottom: 12px;">01 / DISCOVERY</div>
            <h3 style="font-size: 18px; margin-bottom: 12px; color: var(--white, #fff);">Free Consultation</h3>
            <p style="color: var(--muted); font-size: 14px; line-height: 1.7;">
              We discuss your target audience, services, and requirements before any financial commitment is required.
            </p>
          </div>

          <div class="testimonial-card reveal" style="padding: 36px 32px;">
            <div style="font-family: 'DM Mono', monospace; font-size: 12px; color: var(--accent); margin-bottom: 12px;">02 / CERTAINTY</div>
            <h3 style="font-size: 18px; margin-bottom: 12px; color: var(--white, #fff);">Fixed Scope &amp; Quote</h3>
            <p style="color: var(--muted); font-size: 14px; line-height: 1.7;">
              You receive a written scope, itemized deliverables, and an agreed timeline before work commences.
            </p>
          </div>

          <div class="testimonial-card reveal" style="padding: 36px 32px;">
            <div style="font-family: 'DM Mono', monospace; font-size: 12px; color: var(--accent); margin-bottom: 12px;">03 / FEEDBACK</div>
            <h3 style="font-size: 18px; margin-bottom: 12px; color: var(--white, #fff);">Review &amp; Revisions</h3>
            <p style="color: var(--muted); font-size: 14px; line-height: 1.7;">
              Review staging previews on mobile and desktop, provide feedback, and verify all functionality before launch.
            </p>
          </div>

          <div class="testimonial-card reveal" style="padding: 36px 32px;">
            <div style="font-family: 'DM Mono', monospace; font-size: 12px; color: var(--accent); margin-bottom: 12px;">04 / OWNERSHIP</div>
            <h3 style="font-size: 18px; margin-bottom: 12px; color: var(--white, #fff);">100% Asset Handover</h3>
            <p style="color: var(--muted); font-size: 14px; line-height: 1.7;">
              All source code, media assets, domain records, and hosting credentials belong entirely to you with zero proprietary lock-in.
            </p>
          </div>
        </div>
      </div>
    </section>

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
