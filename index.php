<?php
/**
 * WebsiteTailors — Homepage & Public Experience
 * 
 * "Affordable Websites for Businesses in Bangalore"
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
require_once __DIR__ . '/includes/init.php';

// Retrieve dynamic CMS content
$settings     = get_all_settings();
$hero         = get_hero_content();
$services     = get_services();
$projects     = get_projects();
$processSteps = get_process_steps();
$principles   = get_principles();
$testimonials = get_testimonials();
$pricing      = get_pricing_packages();
$faqs         = get_faqs();

// Handle direct non-AJAX fallback submission if JavaScript is disabled
$contactSuccess = get_flash('contact_success')[0] ?? null;
$contactError   = get_flash('contact_error')[0] ?? null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'contact') {
    $result = process_lead_inquiry($_POST);
    if ($result['success']) {
        $contactSuccess = $result['message'];
    } else {
        $contactError = $result['error'];
    }
}

$pageTitle = 'Affordable Website Design in Bangalore | Website Tailors';
$pageDescription = 'Don\'t have a website yet? Or is your current one outdated? Website Tailors builds and redesigns fast, affordable business websites in Bangalore. Get a free quote.';
$activePage = 'home';

require_once __DIR__ . '/includes/header.php';
?>

<?php
$heroConfig = [
    'PRICE_FROM' => '9,999',
    'DELIVERY_DAYS' => '7',
    'WHATSAPP_NUMBER' => '919380552034'
];
?>
<script>
window.HERO_CONFIG = {
    PRICE_FROM: "9,999",
    DELIVERY_DAYS: "7",
    WHATSAPP_NUMBER: "919380552034"
};

document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        var mark = document.querySelector('.hero-heading .mark');
        if (mark) mark.classList.add('is-in');
    }, 400);
});
</script>

  <main id="main-content">
    <!-- =======================================================
         HERO SECTION
    ======================================================== -->
    <section class="hero" id="home">
      <div class="hero-grid" aria-hidden="true"></div>

      <div class="container hero-content">
        <div class="hero-layout">
          <!-- LEFT: Headline, Subheading, CTAs & Trust Row -->
          <div class="hero-copy hero-left">
            <div class="hero-eyebrow-pill">
              <span class="eyebrow-dot" aria-hidden="true"></span>
              <span>Website design · Redesign · WhatsApp automation — Bangalore</span>
            </div>

            <h1 class="hero-heading"><span class="mark">AFFORDABLE</span> WEBSITES FOR BANGALORE BUSINESSES</h1>

            <p class="hero-subheading">
              No website yet? Or one that looks outdated? We build and redesign fast, mobile-friendly websites that bring you more customers.
            </p>

            <div class="hero-ctas">
              <a href="#contact" class="btn btn-primary" aria-label="Get a Free Quote">
                <span>Get a Free Quote</span>
                <svg class="btn-arrow arrow-up-right" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="7" y1="17" x2="17" y2="7"></line><polyline points="7 7 17 7 17 17"></polyline></svg>
              </a>

              <a href="#work" class="btn btn-secondary" aria-label="See Our Work">
                <span>See Our Work</span>
                <svg class="btn-arrow arrow-down" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>
              </a>
            </div>

            <div class="hero-trust-row" aria-label="Trust Signals">
              <div class="trust-item">
                <svg class="trust-check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--trust)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span>Websites from ₹<?= e($heroConfig['PRICE_FROM']) ?></span>
              </div>
              <span class="trust-divider" aria-hidden="true"></span>
              <div class="trust-item">
                <svg class="trust-check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--trust)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span>Live in <?= e($heroConfig['DELIVERY_DAYS']) ?> days</span>
              </div>
              <span class="trust-divider" aria-hidden="true"></span>
              <div class="trust-item">
                <svg class="trust-check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--trust)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span>Free consultation</span>
              </div>
            </div>
          </div>

          <!-- RIGHT: Hero Visual Column Showcase -->
          <div class="hero-visual hero-right" id="heroVisualStage">
            <div class="browser">
              <div class="browser-bar">
                <div class="browser-dots" aria-hidden="true">
                  <span class="dot"></span>
                  <span class="dot"></span>
                  <span class="dot"></span>
                </div>
                <div class="url-box">
                  <span>sample-clinic.in</span>
                </div>
                <div class="concept-tag">Concept redesign</div>
              </div>

              <div class="compare" id="compareContainer" style="--pos: 50%;">
                <span class="compare-badge badge-before">BEFORE</span>
                <span class="compare-badge badge-after">AFTER</span>

                <!-- BEFORE LAYER (Base) -->
                <div class="compare-layer layer-before">
                  <div class="old-header">
                    <div class="old-title">Welcome to our website</div>
                    <div class="old-nav">Home | About Us | Services | Contact</div>
                  </div>
                  <div class="old-body">
                    <div class="old-img-placeholder">[ Photo Placeholder ]</div>
                    <p class="old-text">Serving Rajajinagar since 2008. Please call during OPD hours.</p>
                    <div class="old-btn-row">
                      <button type="button" class="old-btn">Services</button>
                      <button type="button" class="old-btn">Timings</button>
                      <button type="button" class="old-btn">Enquiry</button>
                    </div>
                    <div class="old-counter">Visitors: 004521</div>
                  </div>
                </div>

                <!-- AFTER LAYER (Top, clipped) -->
                <div class="compare-layer layer-after">
                  <div class="new-header">
                    <div class="new-logo">
                      <span class="logo-icon">+</span> Sample Clinic
                    </div>
                  </div>
                  <div class="new-body">
                    <h3 class="new-heading">Quality family care, close to home</h3>
                    <p class="new-subtext">Book a visit online or message us on WhatsApp.</p>
                    <div class="new-btn-row">
                      <a href="#contact" class="new-btn btn-trust">Book Visit Online</a>
                      <a href="#contact" class="new-btn btn-wa">WhatsApp Us</a>
                    </div>
                    <div class="new-cards">
                      <div class="card-item">
                        <svg class="card-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <span>Pediatrics</span>
                      </div>
                      <div class="card-item">
                        <svg class="card-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                        <span>Lab Tests</span>
                      </div>
                      <div class="card-item">
                        <svg class="card-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        <span>Dental</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- HANDLE & RANGE INPUT -->
                <div class="compare-line">
                  <div class="compare-handle">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
                  </div>
                </div>

                <input type="range" min="0" max="100" value="50" class="compare-slider" id="compareSlider" aria-label="Before and after redesign of a sample clinic website" />
              </div>
            </div>

            <!-- OVERLAYS (Direct children of .hero-visual) -->
            <!-- 1. Mobile-friendly Chip -->
            <div class="hero-overlay-chip chip-mobile chip--mobile" id="chipMobile">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
              <span>Mobile-friendly</span>
            </div>

            <!-- 2. Built to rank on Google Chip -->
            <div class="hero-overlay-chip chip-seo chip--google" id="chipSeo">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
              <span>Built to rank on Google</span>
            </div>

            <!-- 3. Mobile Phone Overlay -->
            <div class="phone-overlay phone" aria-hidden="true">
              <div class="phone-notch"></div>
              <div class="phone-screen">
                <div class="phone-header">
                  <span class="phone-logo">+ Sample Clinic</span>
                </div>
                <div class="phone-body">
                  <h4 class="phone-title">Quality family care</h4>
                  <div class="phone-btn">WhatsApp Us</div>
                  <div class="phone-card">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span>Pediatrics</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- 4. WhatsApp Notification Card -->
            <div class="wa-card-overlay wa-card" id="waCard">
              <div class="wa-card-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="var(--wa)"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.335-1.662a11.87 11.87 0 005.705 1.454h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
              </div>
              <div class="wa-card-content">
                <strong class="wa-card-title">New enquiry from your website</strong>
                <span class="wa-card-time">Just now</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Floating WhatsApp Button -->
      <a href="https://wa.me/<?= e($heroConfig['WHATSAPP_NUMBER']) ?>?text=Hi%20Website%20Tailors%2C%20I%20need%20a%20website." class="floating-wa-btn" target="_blank" rel="noopener noreferrer" aria-label="Chat on WhatsApp">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="#FFFFFF"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.335-1.662a11.87 11.87 0 005.705 1.454h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
      </a>

      <script>
      document.addEventListener('DOMContentLoaded', function() {
        var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        
        var slider = document.getElementById('compareSlider');
        var compare = document.getElementById('compareContainer');
        var heroVisual = document.getElementById('heroVisualStage');
        var primaryBtn = document.querySelector('.hero-ctas .btn-primary');
        var mark = document.querySelector('.hero-heading .mark');
        var waCard = document.getElementById('waCard');
        var heroSection = document.getElementById('home');

        var isInteracting = false;
        var sweepAnimation = null;

        if (slider && compare) {
          var updatePos = function() {
            compare.style.setProperty('--pos', slider.value + '%');
          };
          slider.addEventListener('input', function() {
            isInteracting = true;
            if (sweepAnimation) cancelAnimationFrame(sweepAnimation);
            updatePos();
          });
          slider.addEventListener('change', updatePos);
          slider.addEventListener('touchstart', function() {
            isInteracting = true;
            if (sweepAnimation) cancelAnimationFrame(sweepAnimation);
          });
          updatePos();
        }

        if (reducedMotion) {
          if (heroSection) heroSection.classList.add('page-ready');
          if (mark) mark.classList.add('is-in');
          if (waCard) waCard.classList.add('is-in');
          return;
        }

        // 1. Load sequence
        setTimeout(function() {
          if (heroSection) heroSection.classList.add('page-ready');
        }, 50);

        setTimeout(function() {
          if (mark) mark.classList.add('is-in');
        }, 500);

        // 1200ms: Auto-sweep slider from 92% to 50% over 1400ms
        setTimeout(function() {
          if (isInteracting || !slider || !compare) return;
          var startPos = 92;
          var targetPos = 50;
          var startTime = null;
          var duration = 1400;

          function easeInOut(t) {
            return t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t;
          }

          function animateSweep(timestamp) {
            if (isInteracting) return;
            if (!startTime) startTime = timestamp;
            var elapsed = timestamp - startTime;
            var progress = Math.min(elapsed / duration, 1);
            var eased = easeInOut(progress);
            var currentPos = startPos + (targetPos - startPos) * eased;

            slider.value = currentPos;
            compare.style.setProperty('--pos', currentPos + '%');

            if (progress < 1) {
              sweepAnimation = requestAnimationFrame(animateSweep);
            }
          }

          sweepAnimation = requestAnimationFrame(animateSweep);
        }, 1200);

        // 1800ms: WhatsApp notification card slides in from below
        setTimeout(function() {
          if (waCard) waCard.classList.add('is-in');
        }, 1800);

        // 2. 3D Tilt on .hero-visual
        if (heroVisual && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
          var tiltX = 0, tiltY = 0, currentX = 0, currentY = 0;

          heroVisual.addEventListener('mousemove', function(e) {
            if (isInteracting) return;
            var rect = heroVisual.getBoundingClientRect();
            var x = e.clientX - rect.left - rect.width / 2;
            var y = e.clientY - rect.top - rect.height / 2;
            tiltY = (x / (rect.width / 2)) * 4;
            tiltX = -(y / (rect.height / 2)) * 4;
          });

          heroVisual.addEventListener('mouseleave', function() {
            tiltX = 0;
            tiltY = 0;
          });

          function updateTilt() {
            if (!isInteracting) {
              currentX += (tiltX - currentX) * 0.1;
              currentY += (tiltY - currentY) * 0.1;
              heroVisual.style.transform = 'perspective(1000px) rotateX(' + currentX.toFixed(2) + 'deg) rotateY(' + currentY.toFixed(2) + 'deg)';
            } else {
              heroVisual.style.transform = 'none';
            }
            requestAnimationFrame(updateTilt);
          }
          updateTilt();
        }

        // 3. Primary Button Magnetic Hover
        if (primaryBtn && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
          primaryBtn.addEventListener('mousemove', function(e) {
            var rect = primaryBtn.getBoundingClientRect();
            var x = e.clientX - (rect.left + rect.width / 2);
            var y = e.clientY - (rect.top + rect.height / 2);
            var distanceX = Math.max(-6, Math.min(6, x * 0.2));
            var distanceY = Math.max(-6, Math.min(6, y * 0.2));
            primaryBtn.style.transform = 'translate(' + distanceX.toFixed(1) + 'px, ' + distanceY.toFixed(1) + 'px)';
          });

          primaryBtn.addEventListener('mouseleave', function() {
            primaryBtn.style.transform = 'translate(0, 0)';
          });
        }
      });
      </script>

      <!-- SCROLL CUE -->
      <div class="scroll-cue" id="scrollCue" aria-hidden="true">
        <span class="scroll-text">EXPLORE</span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>
      </div>
    </section>

    <!-- =======================================================
         MARQUEE TICKER
    ======================================================== -->
    <section class="marquee-section" aria-hidden="true">
      <div class="marquee">
        <div class="marquee-item">WEBSITE DESIGN <span class="marquee-dot"></span></div>
        <div class="marquee-item">WEBSITE REDESIGN <span class="marquee-dot"></span></div>
        <div class="marquee-item">WHATSAPP AUTOMATION <span class="marquee-dot"></span></div>
        <div class="marquee-item">BUSINESS WEBSITES <span class="marquee-dot"></span></div>
        <div class="marquee-item">BANGALORE <span class="marquee-dot"></span></div>
        <div class="marquee-item">SEO READY <span class="marquee-dot"></span></div>
        <!-- Loop duplication for seamless scroll -->
        <div class="marquee-item">WEBSITE DESIGN <span class="marquee-dot"></span></div>
        <div class="marquee-item">WEBSITE REDESIGN <span class="marquee-dot"></span></div>
        <div class="marquee-item">WHATSAPP AUTOMATION <span class="marquee-dot"></span></div>
        <div class="marquee-item">BUSINESS WEBSITES <span class="marquee-dot"></span></div>
        <div class="marquee-item">BANGALORE <span class="marquee-dot"></span></div>
        <div class="marquee-item">SEO READY <span class="marquee-dot"></span></div>
      </div>
    </section>

    <!-- =======================================================
         WHO WE HELP (PHASE 5)
    ======================================================== -->
    <section class="principles" id="about">
      <div class="container">
        <div class="principles-heading reveal">
          <div class="eyebrow">Who We Help</div>
          <h2 class="section-title" style="margin-top: 25px;">
            Whether You're Starting Online or Fixing What You Have
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

    <!-- =======================================================
         SERVICES SECTION (PHASE 6)
    ======================================================== -->
    <section class="services" id="services">
      <div class="container">
        <div class="services-heading reveal">
          <div>
            <div class="eyebrow">Our Services</div>
            <h2 class="section-title" style="margin-top: 25px;">
              Website Design, Redesign and WhatsApp Automation
            </h2>
          </div>

          <p class="services-description">
            Practical, mobile-friendly websites and WhatsApp automation built for businesses in Bangalore.
          </p>
        </div>

        <?php if (empty($services)): ?>
          <div class="empty-state-public">
            <div class="empty-state-icon">✦</div>
            <h3>Services Updating</h3>
            <p>We are currently updating our service capabilities. Please reach out directly for a quote.</p>
            <a href="#contact" class="button button-primary magnetic">Get a Free Quote ↗</a>
          </div>
        <?php else: ?>
          <div class="services-list">
            <?php foreach ($services as $index => $service): ?>
              <?php $serviceNum = str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT); ?>
              <a href="#contact" class="service reveal" aria-label="<?= e($service['title']) ?>">
                <div class="service-number">
                  <?= e($serviceNum) ?>
                </div>

                <div class="service-content">
                  <h3 class="service-title">
                    <?= e($service['title']) ?>
                  </h3>

                  <p class="service-text">
                    <?= e($service['short_description']) ?>
                  </p>
                </div>

                <div class="service-arrow" aria-hidden="true">
                  ↗
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- =======================================================
         PRICING SECTION (PHASE 7)
    ======================================================== -->
    <section class="pricing-section" id="pricing">
      <div class="container">
        <div class="principles-heading reveal">
          <div class="eyebrow">Transparent Pricing</div>
          <h2 class="section-title" style="margin-top: 25px;">
            Simple, Affordable Pricing
          </h2>
        </div>

        <div class="pricing-grid">
          <?php foreach ($pricing as $plan): ?>
            <div class="pricing-card reveal">
              <div class="pricing-badge"><?= e($plan['best_for']) ?></div>
              <h3 class="pricing-title"><?= e($plan['name']) ?></h3>
              <div class="pricing-price"><?= e($plan['price']) ?></div>
              <ul class="pricing-features">
                <?php foreach ($plan['features'] as $feature): ?>
                  <li><span class="pricing-check">✓</span> <?= e($feature) ?></li>
                <?php endforeach; ?>
              </ul>
              <a href="#contact" class="button button-primary magnetic pricing-btn">Get Started ↗</a>
            </div>
          <?php endforeach; ?>
        </div>

        <p class="pricing-footnote reveal">
          No hidden charges. Pay in two parts. Free consultation before you commit.
        </p>
      </div>
    </section>

    <!-- =======================================================
         WORK / PORTFOLIO (PHASE 13)
    ======================================================== -->
    <section class="work" id="work">
      <div class="container">
        <div class="work-header reveal">
          <div>
            <div class="eyebrow">Portfolio</div>
            <h2 class="section-title" style="margin-top: 25px;">
              Our Work
            </h2>
          </div>

          <p class="work-note">
            A selection of website design concepts, redesign prototypes, and automation systems.
          </p>
        </div>

        <?php if (empty($projects)): ?>
          <div class="empty-state-public">
            <div class="empty-state-icon">✦</div>
            <h3>Work Showcase Updating</h3>
            <p>Our recent website projects are being updated. Contact us to view recent design previews.</p>
            <a href="#contact" class="button button-primary magnetic">Get a Free Quote ↗</a>
          </div>
        <?php else: ?>
          <div class="projects">
            <?php foreach ($projects as $index => $project): ?>
              <?php 
                $projNum = str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT); 
                $totalCount = str_pad((string)count($projects), 2, '0', STR_PAD_LEFT);
                $projectImg = get_image_url($project['image'] ?? null, 'project');
              ?>
              <article class="project" style="z-index: <?= (int)($index + 5) ?>;">
                <div class="project-top">
                  <div class="project-number">
                    <?= e($projNum) ?> / <?= e($totalCount) ?>
                  </div>

                  <div class="project-type">
                    <?= e($project['category']) ?>
                  </div>
                </div>

                <!-- Visual Screenshot Showcase -->
                <div class="project-visual">
                  <div class="browser">
                    <div class="browser-top">
                      <div class="browser-dot"></div>
                      <div class="browser-dot"></div>
                      <div class="browser-dot"></div>
                    </div>
                    <div class="browser-viewport">
                      <img src="<?= e($projectImg) ?>" alt="<?= e($project['title']) ?> website design for a business" class="project-screenshot" loading="lazy" width="800" height="500" />
                    </div>
                  </div>
                </div>

                <div class="project-bottom">
                  <div>
                    <h3 class="project-title">
                      <?= e($project['title']) ?>
                    </h3>
                    <p style="color: var(--muted); font-size: 14px; margin-top: 8px; max-width: 540px;">
                      <?= e($project['description']) ?>
                    </p>
                  </div>

                  <a href="#contact" class="project-link magnetic" aria-label="Inquire about <?= e($project['title']) ?>">
                    ↗
                  </a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- =======================================================
         PROCESS (PHASE 8)
    ======================================================== -->
    <section class="process" id="process">
      <div class="container">
        <div class="process-header reveal">
          <div class="process-header-left">
            <div class="eyebrow">Our Process</div>
            <h2 class="section-title process-title" style="margin-top: 25px;">
              How We Build Your Website in 4 Steps
            </h2>
          </div>

          <p class="process-copy">
            A clear, practical process designed for fast delivery and zero hassle.
          </p>
        </div>

        <?php if (empty($processSteps)): ?>
          <div class="empty-state-public">
            <div class="empty-state-icon">✦</div>
            <h3>Process Updating</h3>
            <p>Reach out to discuss your project requirements with our team.</p>
            <a href="#contact" class="button button-primary magnetic">Get a Free Quote ↗</a>
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

    <!-- =======================================================
         TRUST / TESTIMONIALS (PHASE 14)
    ======================================================== -->
    <section class="testimonials" id="testimonials">
      <div class="container">
        <div class="testimonials-header reveal">
          <div>
            <div class="eyebrow">Trust &amp; Reliability</div>
            <h2 class="section-title" style="margin-top: 25px;">
              Built on Trust
            </h2>
          </div>

          <p style="color: var(--muted); max-width: 480px; font-size: 15px; line-height: 1.6;">
            We believe in honest, practical website development with clear communication and fast turnaround.
          </p>
        </div>

        <div class="empty-state-public reveal" style="margin-top: 40px;">
          <div class="empty-state-icon">✦</div>
          <h3>Client Testimonials Coming Soon</h3>
          <p>We are gathering verified client reviews. In the meantime, we offer free consultations so you can evaluate our work before committing.</p>
          <div class="trust-points-grid" style="margin-top: 24px; display: flex; flex-wrap: wrap; gap: 16px; justify-content: center;">
            <span class="trust-pill">✓ Fast turnarounds</span>
            <span class="trust-pill">✓ Clean code</span>
            <span class="trust-pill">✓ Mobile-friendly</span>
            <span class="trust-pill">✓ Responsive support</span>
          </div>
        </div>
      </div>
    </section>

    <!-- =======================================================
         FAQ SECTION (PHASE 9)
    ======================================================== -->
    <section class="faq-section" id="faq">
      <div class="container">
        <div class="principles-heading reveal">
          <div class="eyebrow">FAQ</div>
          <h2 class="section-title" style="margin-top: 25px;">
            Frequently Asked Questions
          </h2>
        </div>

        <div class="faq-accordion reveal">
          <?php foreach ($faqs as $i => $faq): ?>
            <div class="faq-item">
              <button type="button" class="faq-question" aria-expanded="false" aria-controls="faq-ans-<?= $i ?>">
                <span><?= e($faq['question']) ?></span>
                <span class="faq-icon" aria-hidden="true">+</span>
              </button>
              <div id="faq-ans-<?= $i ?>" class="faq-answer" hidden>
                <p><?= e($faq['answer']) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- =======================================================
         CONTACT / QUOTE FORM (PHASE 15 & 16)
    ======================================================== -->
    <section class="cta" id="contact">
      <div class="container cta-grid">
        <div class="cta-inner reveal">
          <div class="cta-eyebrow">GET IN TOUCH</div>
          <h2 class="cta-title">
            Get a Free Quote<br>
            for Your Website
          </h2>

          <p class="cta-subtitle">
            Tell us what you need. We'll review your requirements and get back to you with a plan and price.
          </p>

          <div class="cta-contact-info">
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
              <a href="tel:+919380552034">
                <span>Call:</span> +91 9380552034 ↗
              </a>
            <?php endif; ?>
            <span>Location: <?= e($settings['address'] ?? 'Bangalore, Karnataka, India') ?></span>
          </div>
        </div>

        <!-- Streamlined 3-Step Project Questionnaire Container -->
        <div class="questionnaire-card reveal">
          <div class="questionnaire-wrapper" id="projectQuestionnaire">
            <!-- Progress Header -->
            <div class="qn-header">
              <div class="qn-header-top">
                <div class="qn-step-badge" id="qnStepBadge">STEP 1 OF 3</div>
                <button type="button" class="qn-back-btn" id="qnBackBtn" aria-label="Go to previous step" style="display: none;">
                  ← BACK
                </button>
              </div>
              <div class="qn-progress-bar-wrap" aria-hidden="true">
                <div class="qn-progress-bar" id="qnProgressBar" style="width: 33%;"></div>
              </div>
            </div>

            <form id="contactForm" method="POST" action="api/contact.php" novalidate>
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="contact">
              <input type="hidden" name="source" value="Website Quote Form">

              <!-- Anti-Bot Spam Honeypot Field -->
              <div class="hp-field" aria-hidden="true">
                <label for="website_url">Leave this field blank</label>
                <input type="text" id="website_url" name="website_url" autocomplete="off" tabindex="-1">
              </div>

              <!-- Hidden Storage Inputs for Questionnaire Selections -->
              <input type="hidden" name="service" id="qnInputService" value="">
              <input type="hidden" name="company" id="qnInputCompany" value="">

              <div class="qn-steps-container">
                <!-- STEP 1: NAME & BUSINESS -->
                <div class="qn-step active" data-step="1">
                  <h3 class="qn-question-title">TELL US ABOUT<br><span class="lime-text">YOUR BUSINESS.</span></h3>
                  <p class="qn-question-sub">What is your name and business name?</p>

                  <div class="qn-contact-form-grid" style="grid-template-columns: 1fr;">
                    <div class="qn-input-group">
                      <label for="qnNameInput" class="qn-field-label">Your Name *</label>
                      <input type="text" id="qnNameInput" name="name" class="qn-text-input" placeholder="e.g. Rahul Sharma" required minlength="2" maxlength="100" autocomplete="name">
                    </div>

                    <div class="qn-input-group">
                      <label for="qnBusinessInput" class="qn-field-label">Business / Brand Name (Optional)</label>
                      <input type="text" id="qnBusinessInput" class="qn-text-input" placeholder="e.g. Sharma Dental Clinic" maxlength="100" autocomplete="organization">
                    </div>
                  </div>

                  <div class="qn-actions">
                    <button type="button" class="qn-next-btn magnetic" data-next="2">
                      <span>NEXT ↗</span>
                    </button>
                  </div>
                </div>

                <!-- STEP 2: SERVICE NEEDED -->
                <div class="qn-step" data-step="2">
                  <h3 class="qn-question-title">WHAT DO YOU<br><span class="lime-text">NEED HELP WITH?</span></h3>
                  <p class="qn-question-sub">Select the service that best describes your project.</p>

                  <div class="qn-options-grid">
                    <button type="button" class="qn-option-card" data-value="Website Design for New Businesses">
                      <span class="qn-option-num">01</span>
                      <span class="qn-option-text">Website Design for New Businesses</span>
                      <span class="qn-option-arrow">↗</span>
                    </button>
                    <button type="button" class="qn-option-card" data-value="Website Redesign">
                      <span class="qn-option-num">02</span>
                      <span class="qn-option-text">Website Redesign</span>
                      <span class="qn-option-arrow">↗</span>
                    </button>
                    <button type="button" class="qn-option-card" data-value="WhatsApp Automation">
                      <span class="qn-option-num">03</span>
                      <span class="qn-option-text">WhatsApp Automation</span>
                      <span class="qn-option-arrow">↗</span>
                    </button>
                    <button type="button" class="qn-option-card" data-value="Website + WhatsApp Package">
                      <span class="qn-option-num">04</span>
                      <span class="qn-option-text">Website + WhatsApp Package</span>
                      <span class="qn-option-arrow">↗</span>
                    </button>
                    <button type="button" class="qn-option-card" data-value="Other">
                      <span class="qn-option-num">05</span>
                      <span class="qn-option-text">Other / Not Sure</span>
                      <span class="qn-option-arrow">↗</span>
                    </button>
                  </div>

                  <div class="qn-actions">
                    <button type="button" class="qn-next-btn magnetic" data-next="3">
                      <span>NEXT ↗</span>
                    </button>
                  </div>
                </div>

                <!-- STEP 3: CONTACT & DETAILS -->
                <div class="qn-step" data-step="3">
                  <h3 class="qn-question-title">HOW CAN WE<br><span class="lime-text">REACH YOU?</span></h3>
                  <p class="qn-question-sub">Enter your phone or email so we can reply with a plan and price.</p>

                  <div class="qn-contact-form-grid">
                    <div class="qn-input-group">
                      <label for="qnPhoneInput" class="qn-field-label">Phone / WhatsApp *</label>
                      <input type="tel" id="qnPhoneInput" name="phone" class="qn-text-input" placeholder="9380552034" required maxlength="50" autocomplete="tel">
                    </div>

                    <div class="qn-input-group">
                      <label for="qnEmailInput" class="qn-field-label">Email Address (Optional)</label>
                      <input type="email" id="qnEmailInput" name="email" class="qn-text-input" placeholder="rahul@company.com" maxlength="150" autocomplete="email">
                    </div>

                    <div class="qn-input-group full">
                      <label for="qnDetailsInput" class="qn-field-label">Project Details (Optional)</label>
                      <textarea id="qnDetailsInput" name="project_details" class="qn-textarea" rows="3" placeholder="Tell us about your requirements or timeline..." maxlength="5000"></textarea>
                    </div>
                  </div>

                  <div id="qnFeedback" class="qn-feedback-msg" style="display: none;"></div>

                  <div class="qn-actions">
                    <button type="submit" class="qn-submit-btn magnetic" id="qnSubmitBtn">
                      <span>GET YOUR FREE QUOTE ↗</span>
                    </button>
                  </div>
                </div>
              </div>
            </form>

            <!-- SUCCESS SCREEN (DYNAMICALLY REVEALED AFTER SUBMISSION) -->
            <div class="qn-success-screen" id="qnSuccessScreen" style="display: none;">
              <div class="qn-success-badge">✓ QUOTE REQUEST RECEIVED</div>
              <h2 class="qn-success-title">THANK YOU.<br><span class="lime-text">WE HAVE YOUR DETAILS.</span></h2>
              <p class="qn-success-desc">We will review your requirements and reply with a plan and price.</p>
              <button type="button" class="button button-primary magnetic qn-reset-btn" id="qnResetBtn">
                Back to Website ↗
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Modal Wrapper for Interactive Project Questionnaire -->
    <div class="questionnaire-modal" id="questionnaireModal" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Website Tailors Free Quote Form">
      <div class="qn-modal-backdrop" id="qnModalBackdrop"></div>
      <div class="qn-modal-content">
        <button type="button" class="qn-modal-close" id="qnModalClose" aria-label="Close Form">✕</button>
        <div id="qnModalContainer"></div>
      </div>
    </div>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
