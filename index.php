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

$pageTitle = 'Website Design Company in Bangalore | Website Tailors';
$pageDescription = 'Website Tailors designs mobile-friendly, SEO-ready websites for clinics, restaurants, salons and small businesses in Bangalore. Website design, redesign and WhatsApp enquiry setup. Free consultation.';
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
          <!-- LEFT COLUMN: Eyebrow, Headline, Subtitle, CTAs & Reassurance Labels -->
          <div class="hero-copy hero-left">
            <div class="hero-eyebrow-pill">
              <span class="eyebrow-dot" aria-hidden="true"></span>
              <span>WEB DESIGN · DEVELOPMENT · WHATSAPP AUTOMATION</span>
            </div>

            <h1 class="hero-heading">
              <span class="hero-line">Your website should</span>
              <span class="hero-line"><span class="hero-highlight mark">bring you business.</span></span>
            </h1>

            <p class="hero-subheading">
              Thoughtfully designed websites for Bengaluru businesses that need to earn trust, explain their value, and turn more visits into enquiries.
            </p>

            <div class="hero-ctas">
              <a href="#contact" class="btn btn-primary" aria-label="Get a Free Website Consultation">
                <span>Get a Free Website Consultation</span>
                <svg class="btn-arrow arrow-up-right" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="7" y1="17" x2="17" y2="7"></line><polyline points="7 7 17 7 17 17"></polyline></svg>
              </a>

              <a href="#work" class="btn btn-secondary" aria-label="Explore Our Work">
                <span>Explore Our Work</span>
                <svg class="btn-arrow arrow-down" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>
              </a>
            </div>

            <div class="hero-trust-row" aria-label="Reassurance Points">
              <div class="trust-item">
                <svg class="trust-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span>Clear project scope</span>
              </div>
              <div class="trust-item">
                <svg class="trust-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span>Mobile-first design</span>
              </div>
              <div class="trust-item">
                <svg class="trust-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span>Built around enquiries</span>
              </div>
            </div>
          </div>

          <!-- RIGHT COLUMN: Responsive Website Preview Composition -->
          <div class="hero-visual hero-right" id="heroVisualStage">
            <!-- Organic Green Glow Backdrop & Bursts -->
            <div class="hero-glow-blob" aria-hidden="true"></div>

            <div class="hero-doodle doodle-tr" aria-hidden="true">
              <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                <path d="M12 6L26 2" stroke="#C8F03C" stroke-width="3" stroke-linecap="round"/>
                <path d="M28 14L38 18" stroke="#C8F03C" stroke-width="3" stroke-linecap="round"/>
              </svg>
            </div>
            <div class="hero-doodle doodle-bl" aria-hidden="true">
              <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
                <path d="M6 26L2 14" stroke="#C8F03C" stroke-width="3" stroke-linecap="round"/>
                <path d="M20 32L14 20" stroke="#C8F03C" stroke-width="3" stroke-linecap="round"/>
              </svg>
            </div>
            <div class="hero-doodle doodle-phone" aria-hidden="true">
              <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                <path d="M8 20L18 8" stroke="#C8F03C" stroke-width="3" stroke-linecap="round"/>
                <path d="M22 26L28 16" stroke="#C8F03C" stroke-width="3" stroke-linecap="round"/>
              </svg>
            </div>

            <!-- Floating Top Badges -->
            <!-- 1. SEO-ready Structure Badge -->
            <div class="hero-floating-badge badge-seo" id="badgeSeo">
              <div class="badge-icon icon-lightning">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="#00EA64" stroke="#00EA64" stroke-width="1" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
              </div>
              <div class="badge-content">
                <strong class="badge-title">SEO-ready structure</strong>
                <span class="badge-sub">Built on clean, search-friendly code so Google can read your pages properly.</span>
              </div>
            </div>

            <!-- 2. Mobile-friendly Badge -->
            <div class="hero-floating-badge badge-mobile" id="badgeMobile">
              <div class="badge-icon icon-phone">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#00EA64" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
              </div>
              <div class="badge-content">
                <strong class="badge-title">Mobile-friendly</strong>
                <span class="badge-sub">Easy to use on phones, tablets and desktops.</span>
              </div>
            </div>

            <!-- Main Desktop / Laptop Monitor Mockup -->
            <div class="device-laptop">
              <div class="laptop-screen">
                <div class="laptop-bezel-top">
                  <span class="laptop-camera" aria-hidden="true"></span>
                </div>

                <div class="mockup-site">
                  <!-- Mockup Clinic Header -->
                  <div class="site-header">
                    <div class="site-brand">
                      <span class="brand-plus">+</span>
                      <span class="brand-name">Sample Clinic</span>
                    </div>
                    <nav class="site-nav" aria-label="Sample site preview navigation">
                      <span>Home</span>
                      <span>About</span>
                      <span>Services</span>
                      <span>Doctors</span>
                      <span>Contact</span>
                    </nav>
                    <div class="site-cta-btn">Book Appointment</div>
                  </div>

                  <!-- Mockup Clinic Hero Body -->
                  <div class="site-hero">
                    <div class="site-hero-left">
                      <span class="site-eyebrow">YOUR HEALTH, OUR PRIORITY</span>
                      <h2 class="site-title">Quality Care for a Healthier Tomorrow</h2>
                      <p class="site-desc">Trusted family healthcare with experienced doctors and modern facilities in Bangalore.</p>
                      <div class="site-actions">
                        <div class="site-btn-wa">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="#FFFFFF" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.335-1.662a11.87 11.87 0 005.705 1.454h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                          <span>Book on WhatsApp</span>
                        </div>
                        <div class="site-btn-secondary">View Services</div>
                      </div>
                    </div>
                    <div class="site-hero-right">
                      <div class="site-doctor-wrap">
                        <img src="assets/images/hero/sample-clinic-doctor.jpg" alt="Sample Clinic concept website preview" class="site-doctor-img" loading="eager" decoding="async">
                      </div>
                    </div>
                  </div>

                  <!-- Mockup Clinic 4 Feature Cards -->
                  <div class="site-cards-row">
                    <div class="site-card-item is-featured">
                      <div class="site-card-icon">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                      </div>
                      <div class="site-card-text">
                        <strong>General Medicine</strong>
                        <span>Complete care for all ages</span>
                      </div>
                    </div>
                    <div class="site-card-item">
                      <div class="site-card-icon">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="5"/><path d="M3 21v-2a7 7 0 0 1 14 0v2"/></svg>
                      </div>
                      <div class="site-card-text">
                        <strong>Pediatrics</strong>
                        <span>Child healthcare &amp; vaccinations</span>
                      </div>
                    </div>
                    <div class="site-card-item">
                      <div class="site-card-icon">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                      </div>
                      <div class="site-card-text">
                        <strong>Dental Care</strong>
                        <span>Modern dental treatments</span>
                      </div>
                    </div>
                    <div class="site-card-item">
                      <div class="site-card-icon">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                      </div>
                      <div class="site-card-text">
                        <strong>Skin &amp; Hair</strong>
                        <span>Expert dermatology care</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Laptop Bottom Hinge / Base -->
              <div class="laptop-base" aria-hidden="true">
                <span class="laptop-notch"></span>
              </div>
            </div>

            <!-- Mobile Phone Mockup (Overlapping right) -->
            <div class="device-phone" aria-hidden="true">
              <div class="phone-frame">
                <div class="phone-dynamic-island"></div>
                <div class="phone-screen-inner">
                  <div class="phone-site-header">
                    <div class="phone-site-brand">
                      <span class="brand-plus">+</span>
                      <span class="brand-name">Sample Clinic</span>
                    </div>
                    <div class="phone-hamburger">
                      <span></span>
                      <span></span>
                      <span></span>
                    </div>
                  </div>

                  <div class="phone-site-hero">
                    <span class="phone-eyebrow">YOUR HEALTH, OUR PRIORITY</span>
                    <h3 class="phone-title">Quality Care for a Healthier Tomorrow</h3>
                    <p class="phone-desc">Trusted family healthcare with experienced doctors in Bangalore.</p>
                    <div class="phone-wa-btn">
                      <svg width="10" height="10" viewBox="0 0 24 24" fill="#FFFFFF"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.335-1.662a11.87 11.87 0 005.705 1.454h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                      <span>Book on WhatsApp</span>
                    </div>

                    <div class="phone-icons-grid">
                      <div class="phone-grid-cell">
                        <span class="cell-ico">🛡️</span>
                        <span>General Medicine</span>
                      </div>
                      <div class="phone-grid-cell">
                        <span class="cell-ico">👶</span>
                        <span>Pediatrics</span>
                      </div>
                      <div class="phone-grid-cell">
                        <span class="cell-ico">🦷</span>
                        <span>Dental Care</span>
                      </div>
                      <div class="phone-grid-cell">
                        <span class="cell-ico">🌿</span>
                        <span>Skin &amp; Hair</span>
                      </div>
                    </div>

                    <div class="phone-doctor-card">
                      <span class="doc-icon">👨‍⚕️</span>
                      <div class="doc-info">
                        <strong>Experienced Doctors</strong>
                        <span>Personalized treatment plans</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Compact Before-and-After Redesign Card (Bottom-left) -->
            <div class="hero-ba-card" id="heroBaCard">
              <div class="ba-card-inner">
                <div class="ba-thumb-col">
                  <div class="ba-thumb thumb-before">
                    <div class="dummy-site dummy-before">
                      <div class="dummy-bar"></div>
                      <div class="dummy-box"></div>
                      <div class="dummy-lines">
                        <span></span><span></span>
                      </div>
                    </div>
                  </div>
                  <span class="ba-tag tag-before">Before</span>
                </div>

                <div class="ba-thumb-arrow" aria-hidden="true">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                    <path d="M4 12C9 6 15 6 20 10M20 10L16 7M20 10L16 13" stroke="#00EA64" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </div>

                <div class="ba-thumb-col">
                  <div class="ba-thumb thumb-after">
                    <div class="dummy-site dummy-after">
                      <div class="dummy-bar-green"></div>
                      <div class="dummy-hero-flex">
                        <div class="dummy-text-col">
                          <span></span><span></span>
                        </div>
                        <div class="dummy-img-doc"></div>
                      </div>
                      <div class="dummy-chips-row">
                        <span></span><span></span>
                      </div>
                    </div>
                  </div>
                  <span class="ba-tag tag-after">After</span>
                </div>
              </div>
              <div class="ba-card-caption">
                <span class="script-text">From outdated to customer-ready</span>
                <svg class="ba-caption-arrow" width="24" height="16" viewBox="0 0 24 16" fill="none" aria-hidden="true">
                  <path d="M2 13C8 15 15 12 21 4M21 4L15 4M21 4L22 9" stroke="#00EA64" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </div>
            </div>
          </div>
        </div>
      </div>


      <script>
      document.addEventListener('DOMContentLoaded', function() {
        var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var heroVisual = document.getElementById('heroVisualStage');
        var primaryBtn = document.querySelector('.hero-ctas .btn-primary');
        var heroSection = document.getElementById('home');

        if (reducedMotion) {
          if (heroSection) heroSection.classList.add('page-ready');
          return;
        }

        // Staggered entrance
        setTimeout(function() {
          if (heroSection) heroSection.classList.add('page-ready');
        }, 50);

        // Subtle desktop tilt on hover
        if (heroVisual && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
          var tiltX = 0, tiltY = 0, currentX = 0, currentY = 0;
          var isHovered = false;

          heroVisual.addEventListener('mousemove', function(e) {
            var rect = heroVisual.getBoundingClientRect();
            var x = e.clientX - rect.left - rect.width / 2;
            var y = e.clientY - rect.top - rect.height / 2;
            tiltY = (x / (rect.width / 2)) * 2.5;
            tiltX = -(y / (rect.height / 2)) * 2.5;
            isHovered = true;
          });

          heroVisual.addEventListener('mouseleave', function() {
            tiltX = 0;
            tiltY = 0;
            isHovered = false;
          });

          function updateTilt() {
            currentX += (tiltX - currentX) * 0.08;
            currentY += (tiltY - currentY) * 0.08;
            if (isHovered || Math.abs(currentX) > 0.02 || Math.abs(currentY) > 0.02) {
              heroVisual.style.transform = 'perspective(1200px) rotateX(' + currentX.toFixed(2) + 'deg) rotateY(' + currentY.toFixed(2) + 'deg)';
            } else {
              heroVisual.style.transform = 'none';
            }
            requestAnimationFrame(updateTilt);
          }
          requestAnimationFrame(updateTilt);
        }

        // Primary Button Magnetic Hover
        if (primaryBtn && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
          primaryBtn.addEventListener('mousemove', function(e) {
            var rect = primaryBtn.getBoundingClientRect();
            var x = e.clientX - (rect.left + rect.width / 2);
            var y = e.clientY - (rect.top + rect.height / 2);
            var distanceX = Math.max(-5, Math.min(5, x * 0.15));
            var distanceY = Math.max(-5, Math.min(5, y * 0.15));
            primaryBtn.style.transform = 'translate(' + distanceX.toFixed(1) + 'px, ' + distanceY.toFixed(1) + 'px)';
          });

          primaryBtn.addEventListener('mouseleave', function() {
            primaryBtn.style.transform = 'translate(0, 0)';
          });
        }
      });
      </script>

      </section>

    <!-- =======================================================
         MARQUEE TICKER
    ======================================================== -->
    <section class="marquee-section" aria-label="Services Ticker">
      <div class="marquee">
        <div class="marquee-item">Website Design <span class="marquee-dot"></span></div>
        <div class="marquee-item">Website Redesign <span class="marquee-dot"></span></div>
        <div class="marquee-item">WhatsApp Automation <span class="marquee-dot"></span></div>
        <div class="marquee-item">Small Business Websites <span class="marquee-dot"></span></div>
        <div class="marquee-item">Bangalore &amp; Bengaluru <span class="marquee-dot"></span></div>
        <div class="marquee-item">SEO-Ready Websites <span class="marquee-dot"></span></div>
        <!-- Loop duplication for seamless scroll (aria-hidden prevents duplicate screen-reader announcements) -->
        <div class="marquee-item" aria-hidden="true">Website Design <span class="marquee-dot"></span></div>
        <div class="marquee-item" aria-hidden="true">Website Redesign <span class="marquee-dot"></span></div>
        <div class="marquee-item" aria-hidden="true">WhatsApp Automation <span class="marquee-dot"></span></div>
        <div class="marquee-item" aria-hidden="true">Small Business Websites <span class="marquee-dot"></span></div>
        <div class="marquee-item" aria-hidden="true">Bangalore &amp; Bengaluru <span class="marquee-dot"></span></div>
        <div class="marquee-item" aria-hidden="true">SEO-Ready Websites <span class="marquee-dot"></span></div>
      </div>
    </section>

    <!-- =======================================================
         WHO WE BUILD FOR (INDUSTRY STRIP)
    ======================================================== -->
    <!-- =======================================================
         WHO WE BUILD FOR (INDUSTRY SECTION)
    ======================================================== -->
    <section class="industries-section" id="industries">
      <div class="container">
        <div class="industries-heading reveal">
          <div class="eyebrow">LOCAL BENGALURU BUSINESSES</div>
          <h2 class="industries-title">
            Websites for Bangalore Businesses Like Yours
          </h2>
          <p class="industries-desc">
            From clinics and cafés to boutiques and professional firms, we build websites that help Bangalore customers understand your services quickly and get in touch with you directly.
          </p>
        </div>

        <div class="industries-grid">
          <!-- 01: Clinics & Dentists -->
          <div class="industry-card reveal" tabindex="0">
            <div class="industry-card-meta">
              <span class="industry-card-num">01</span>
              <span class="industry-card-tag">HEALTHCARE</span>
            </div>
            <div class="industry-card-visual" aria-hidden="true">
              <svg viewBox="0 0 160 80" fill="none" xmlns="http://www.w3.org/2000/svg" class="industry-card-svg" aria-hidden="true">
                <rect x="8" y="10" width="144" height="60" rx="8" class="p-glow" />
                <rect x="14" y="14" width="82" height="52" rx="6" class="p-paper p-stroke" />
                <line x1="26" y1="26" x2="60" y2="26" class="p-stroke" stroke-width="2" />
                <line x1="26" y1="35" x2="78" y2="35" class="p-muted" />
                <line x1="26" y1="43" x2="68" y2="43" class="p-muted" />
                <rect x="26" y="50" width="18" height="10" rx="2" class="p-lime p-stroke-thin" />
                <path d="M35 52v6M32 55h6" stroke="#14161A" stroke-width="1.4" stroke-linecap="round" />
                <path d="M106 18v22c0 10 8 16 18 16s18-6 18-16V18" class="p-stroke" stroke-width="1.8" />
                <circle cx="106" cy="18" r="3" class="p-lime p-stroke-thin" />
                <circle cx="142" cy="18" r="3" class="p-lime p-stroke-thin" />
                <path d="M124 56v8" class="p-stroke" stroke-width="1.8" />
                <circle cx="124" cy="68" r="6" class="p-paper p-stroke" />
                <circle cx="124" cy="68" r="2.5" class="p-lime" />
              </svg>
            </div>
            <div class="industry-card-body">
              <h3 class="industry-card-title">Websites for Clinics &amp; Dentists</h3>
              <p class="industry-card-desc">Show patients your treatments, timings, location and contact details, with appointment enquiries built in where you need them.</p>
            </div>
            <div class="industry-card-arrow" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="7" y1="17" x2="17" y2="7"></line>
                <polyline points="7 7 17 7 17 17"></polyline>
              </svg>
            </div>
          </div>

          <!-- 02: Restaurants & Cafes -->
          <div class="industry-card reveal" tabindex="0">
            <div class="industry-card-meta">
              <span class="industry-card-num">02</span>
              <span class="industry-card-tag">FOOD &amp; DINING</span>
            </div>
            <div class="industry-card-visual" aria-hidden="true">
              <svg viewBox="0 0 160 80" fill="none" xmlns="http://www.w3.org/2000/svg" class="industry-card-svg" aria-hidden="true">
                <rect x="8" y="10" width="144" height="60" rx="8" class="p-glow" />
                <ellipse cx="64" cy="58" rx="42" ry="10" class="p-paper p-stroke" />
                <path d="M34 56c0-18 14-30 30-30s30 12 30 30" class="p-paper p-stroke" />
                <circle cx="64" cy="22" r="3.5" class="p-lime p-stroke-thin" />
                <path d="M18 22v14c0 4 3 6 6 6v22" class="p-stroke" stroke-width="1.6" />
                <line x1="21" y1="22" x2="21" y2="32" class="p-stroke-thin" />
                <line x1="27" y1="22" x2="27" y2="32" class="p-stroke-thin" />
                <path d="M138 22c-4 10-4 16-1 20v22h4V22z" class="p-paper p-stroke" stroke-width="1.5" />
                <rect x="110" y="42" width="22" height="18" rx="4" class="p-paper p-stroke" />
                <path d="M132 46c3 0 5 2 5 5s-2 5-5 5" class="p-stroke-thin" />
                <path d="M115 36c1-3 0-5 2-7M121 36c1-3 0-5 2-7" class="p-lime-stroke" stroke-width="1.3" />
              </svg>
            </div>
            <div class="industry-card-body">
              <h3 class="industry-card-title">Websites for Restaurants &amp; Cafés</h3>
              <p class="industry-card-desc">Put your menu, opening hours, location and dining details in one clear, mobile-friendly place.</p>
            </div>
            <div class="industry-card-arrow" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="7" y1="17" x2="17" y2="7"></line>
                <polyline points="7 7 17 7 17 17"></polyline>
              </svg>
            </div>
          </div>

          <!-- 03: Salons & Spas -->
          <div class="industry-card reveal" tabindex="0">
            <div class="industry-card-meta">
              <span class="industry-card-num">03</span>
              <span class="industry-card-tag">BEAUTY &amp; WELLNESS</span>
            </div>
            <div class="industry-card-visual" aria-hidden="true">
              <svg viewBox="0 0 160 80" fill="none" xmlns="http://www.w3.org/2000/svg" class="industry-card-svg" aria-hidden="true">
                <rect x="8" y="10" width="144" height="60" rx="8" class="p-glow" />
                <circle cx="28" cy="58" r="6" class="p-paper p-stroke" />
                <circle cx="44" cy="58" r="6" class="p-paper p-stroke" />
                <line x1="33" y1="53" x2="52" y2="24" class="p-stroke" stroke-width="1.8" />
                <line x1="39" y1="53" x2="20" y2="24" class="p-stroke" stroke-width="1.8" />
                <circle cx="36" cy="42" r="2.5" class="p-lime p-stroke-thin" />
                <path d="M96 62c0-14 12-22 12-22s12 8 12 22z" class="p-paper p-stroke" />
                <path d="M88 62c0-10 8-16 16-18-2 8 0 14-4 18z" class="p-lime p-stroke-thin" />
                <path d="M128 62c0-10-8-16-16-18 2 8 0 14 4 18z" class="p-lime p-stroke-thin" />
                <rect x="62" y="32" width="20" height="30" rx="4" class="p-paper p-stroke" />
                <rect x="67" y="24" width="10" height="8" rx="2" class="p-stroke-thin" />
                <path d="M72 18v6" class="p-stroke" stroke-width="1.5" />
                <circle cx="72" cy="46" r="3" class="p-lime" />
              </svg>
            </div>
            <div class="industry-card-body">
              <h3 class="industry-card-title">Websites for Salons &amp; Spas</h3>
              <p class="industry-card-desc">Display your services, packages and work gallery, and let clients send booking enquiries easily.</p>
            </div>
            <div class="industry-card-arrow" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="7" y1="17" x2="17" y2="7"></line>
                <polyline points="7 7 17 7 17 17"></polyline>
              </svg>
            </div>
          </div>

          <!-- 04: Retail & Boutiques -->
          <div class="industry-card reveal" tabindex="0">
            <div class="industry-card-meta">
              <span class="industry-card-num">04</span>
              <span class="industry-card-tag">RETAIL &amp; COMMERCE</span>
            </div>
            <div class="industry-card-visual" aria-hidden="true">
              <svg viewBox="0 0 160 80" fill="none" xmlns="http://www.w3.org/2000/svg" class="industry-card-svg" aria-hidden="true">
                <rect x="8" y="10" width="144" height="60" rx="8" class="p-glow" />
                <rect x="36" y="26" width="56" height="42" rx="6" class="p-paper p-stroke" />
                <path d="M50 26V18a14 14 0 0 1 28 0v8" class="p-stroke" stroke-width="1.8" />
                <circle cx="64" cy="44" r="8" class="p-lime p-stroke-thin" />
                <path d="M64 40v8M60 44h8" stroke="#14161A" stroke-width="1.5" stroke-linecap="round" />
                <path d="M108 26l24 24-14 14-24-24v-14h14z" class="p-paper p-stroke" />
                <circle cx="103" cy="31" r="2.5" class="p-lime p-stroke-thin" />
                <line x1="114" y1="42" x2="124" y2="52" class="p-muted" stroke-width="1.4" />
              </svg>
            </div>
            <div class="industry-card-body">
              <h3 class="industry-card-title">Websites for Retail Stores &amp; Boutiques</h3>
              <p class="industry-card-desc">Showcase your products, store details and contact options so shoppers can check availability before they visit.</p>
            </div>
            <div class="industry-card-arrow" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="7" y1="17" x2="17" y2="7"></line>
                <polyline points="7 7 17 7 17 17"></polyline>
              </svg>
            </div>
          </div>

          <!-- 05: Coaching & Tuition -->
          <div class="industry-card reveal" tabindex="0">
            <div class="industry-card-meta">
              <span class="industry-card-num">05</span>
              <span class="industry-card-tag">EDUCATION</span>
            </div>
            <div class="industry-card-visual" aria-hidden="true">
              <svg viewBox="0 0 160 80" fill="none" xmlns="http://www.w3.org/2000/svg" class="industry-card-svg" aria-hidden="true">
                <rect x="8" y="10" width="144" height="60" rx="8" class="p-glow" />
                <polygon points="56,20 104,32 56,44 8,32" class="p-lime p-stroke" stroke-width="1.8" />
                <path d="M26 38v16c0 6 14 10 30 10s30-4 30-10V38" class="p-paper p-stroke" />
                <path d="M104 32v18" class="p-stroke" stroke-width="1.6" />
                <circle cx="104" cy="52" r="3" class="p-stroke" fill="#14161A" />
                <path d="M96 46c8-3 18-3 26 0v20c-8-3-18-3-26 0z" class="p-paper p-stroke-thin" />
                <path d="M122 46c8-3 18-3 26 0v20c-8-3-18-3-26 0z" class="p-paper p-stroke-thin" />
                <line x1="102" y1="52" x2="116" y2="52" class="p-muted" />
                <line x1="128" y1="52" x2="142" y2="52" class="p-muted" />
              </svg>
            </div>
            <div class="industry-card-body">
              <h3 class="industry-card-title">Websites for Coaching Centres &amp; Tuition</h3>
              <p class="industry-card-desc">Explain your courses, batches, schedules and admission process clearly for students and parents.</p>
            </div>
            <div class="industry-card-arrow" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="7" y1="17" x2="17" y2="7"></line>
                <polyline points="7 7 17 7 17 17"></polyline>
              </svg>
            </div>
          </div>

          <!-- 06: Interior & Real Estate -->
          <div class="industry-card reveal" tabindex="0">
            <div class="industry-card-meta">
              <span class="industry-card-num">06</span>
              <span class="industry-card-tag">PROPERTY &amp; DESIGN</span>
            </div>
            <div class="industry-card-visual" aria-hidden="true">
              <svg viewBox="0 0 160 80" fill="none" xmlns="http://www.w3.org/2000/svg" class="industry-card-svg" aria-hidden="true">
                <rect x="8" y="10" width="144" height="60" rx="8" class="p-glow" />
                <polygon points="20,40 54,16 88,40" class="p-paper p-stroke" stroke-width="1.8" />
                <rect x="28" y="40" width="52" height="28" rx="2" class="p-paper p-stroke" />
                <rect x="48" y="48" width="14" height="20" rx="2" class="p-lime p-stroke-thin" />
                <circle cx="59" cy="58" r="1.5" fill="#14161A" />
                <line x1="36" y1="46" x2="42" y2="46" class="p-muted" />
                <line x1="68" y1="46" x2="74" y2="46" class="p-muted" />
                <rect x="100" y="24" width="44" height="44" rx="6" class="p-paper p-stroke" />
                <path d="M106 58l10-14 8 10 10-12 6 16" class="p-muted" stroke-width="1.4" />
                <circle cx="132" cy="36" r="3.5" class="p-lime p-stroke-thin" />
              </svg>
            </div>
            <div class="industry-card-body">
              <h3 class="industry-card-title">Websites for Interior Designers &amp; Real Estate</h3>
              <p class="industry-card-desc">Present your portfolio, listings, services and project photos in a clean, visual layout.</p>
            </div>
            <div class="industry-card-arrow" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="7" y1="17" x2="17" y2="7"></line>
                <polyline points="7 7 17 7 17 17"></polyline>
              </svg>
            </div>
          </div>

          <!-- 07: CA & Legal Firms -->
          <div class="industry-card reveal" tabindex="0">
            <div class="industry-card-meta">
              <span class="industry-card-num">07</span>
              <span class="industry-card-tag">FINANCE &amp; LEGAL</span>
            </div>
            <div class="industry-card-visual" aria-hidden="true">
              <svg viewBox="0 0 160 80" fill="none" xmlns="http://www.w3.org/2000/svg" class="industry-card-svg" aria-hidden="true">
                <rect x="8" y="10" width="144" height="60" rx="8" class="p-glow" />
                <rect x="18" y="16" width="48" height="52" rx="4" class="p-paper p-stroke" />
                <line x1="26" y1="26" x2="48" y2="26" class="p-stroke" stroke-width="2" />
                <line x1="26" y1="34" x2="56" y2="34" class="p-muted" />
                <line x1="26" y1="42" x2="52" y2="42" class="p-muted" />
                <rect x="26" y="50" width="16" height="10" rx="2" class="p-lime p-stroke-thin" />
                <path d="M29 55l3 3 6-5" stroke="#14161A" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
                <line x1="116" y1="18" x2="116" y2="64" class="p-stroke" stroke-width="2" />
                <line x1="106" y1="64" x2="126" y2="64" class="p-stroke" stroke-width="2" />
                <line x1="94" y1="28" x2="138" y2="28" class="p-stroke" stroke-width="1.8" />
                <path d="M94 28l-6 16h12z" class="p-paper p-stroke-thin" />
                <path d="M138 28l-6 16h12z" class="p-paper p-stroke-thin" />
                <circle cx="116" cy="28" r="3" class="p-lime p-stroke-thin" />
              </svg>
            </div>
            <div class="industry-card-body">
              <h3 class="industry-card-title">Websites for CA &amp; Law Firms</h3>
              <p class="industry-card-desc">Explain your services and practice areas, and make it simple for clients to contact you, on a professional website they can trust.</p>
            </div>
            <div class="industry-card-arrow" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="7" y1="17" x2="17" y2="7"></line>
                <polyline points="7 7 17 7 17 17"></polyline>
              </svg>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- =======================================================
         TRUST & IMPACT METRICS STRIP
    ======================================================== -->
    <section class="trust-metrics-strip" aria-label="Company Performance Highlights">
      <div class="container">
        <div class="metrics-strip-card reveal">
          <!-- Metric 1: Total Clients -->
          <div class="metric-item">
            <div class="metric-icon-wrap" aria-hidden="true">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
            </div>
            <div class="metric-content">
              <div class="metric-number-row">
                <span class="metric-number">40<span class="metric-plus">+</span></span>
              </div>
              <div class="metric-label">TOTAL CLIENTS</div>
              <p class="metric-sub">Local Bengaluru businesses &amp; founders</p>
            </div>
          </div>

          <div class="metric-divider" aria-hidden="true"></div>

          <!-- Metric 2: Total Projects Delivered -->
          <div class="metric-item">
            <div class="metric-icon-wrap" aria-hidden="true">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                <polyline points="2 17 12 22 22 17"></polyline>
                <polyline points="2 12 12 17 22 12"></polyline>
              </svg>
            </div>
            <div class="metric-content">
              <div class="metric-number-row">
                <span class="metric-number">50<span class="metric-plus">+</span></span>
              </div>
              <div class="metric-label">PROJECTS DELIVERED</div>
              <p class="metric-sub">Custom websites &amp; automations launched</p>
            </div>
          </div>

          <div class="metric-divider" aria-hidden="true"></div>

          <!-- Metric 3: 24hrs Reply -->
          <div class="metric-item">
            <div class="metric-icon-wrap" aria-hidden="true">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
              </svg>
            </div>
            <div class="metric-content">
              <div class="metric-number-row">
                <span class="metric-number">24<span class="metric-unit">hrs</span></span>
              </div>
              <div class="metric-label">REPLY GUARANTEE</div>
              <p class="metric-sub">Direct response on WhatsApp &amp; email</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- =======================================================
         OUR APPROACH & PRINCIPLES (SERVICES)
    ======================================================== -->
    <section class="principles" id="approach">
      <div class="container">
        <div class="principles-heading reveal">
          <div class="eyebrow">A CLEAR PLAN</div>
          <h2 class="section-title" style="margin-top: 25px;">
            Website Services in Bangalore, Built Around a Clear Plan
          </h2>
          <p style="color: var(--muted); font-size: 16px; line-height: 1.7; max-width: 680px; margin-top: 16px;">
            Your website should help people understand your business, find what they need and contact you. Before any work starts, we agree on the scope, deliverables and goals with you.
          </p>
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




    <!-- =======================================================
         WORK / PORTFOLIO (PHASE 13) - TEMPORARILY COMMENTED OUT
    ======================================================== -->
    <!--
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

                <?php // Visual Screenshot Showcase ?>
                <div class="project-visual">
                  <div class="browser">
                    <div class="browser-top">
                      <div class="browser-dot"></div>
                      <div class="browser-dot"></div>
                      <div class="browser-dot"></div>
                    </div>
                    <div class="browser-viewport">
                      <img src="<?= e($projectImg) ?>" alt="<?= e($project['title']) ?> – <?= e($project['category']) ?> demonstration interface" class="project-screenshot" loading="lazy" width="800" height="500" />
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

            <?php /*
            TODO: Add 2-3 local Bangalore business design concepts to replace logistics dashboards
            CONCEPT 1 (Dental Clinic): Title: "Indiranagar Dental Care", Category: "Clinic Concept Design", Description: "Clean patient portal concept with appointment booking, OPD hours, and WhatsApp inquiry."
            CONCEPT 2 (Restaurant / Cafe): Title: "The Roasted Bean Cafe & Bistro", Category: "Restaurant Concept Design", Description: "Warm, visual cafe menu concept with table reservation CTA and Google Maps integration."
            CONCEPT 3 (Salon & Spa): Title: "Aura Unisex Salon & Spa", Category: "Salon Concept Design", Description: "Modern salon service rate card concept with package selection and WhatsApp booking."
            */ ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
    -->

    <!-- =======================================================
         PROCESS (PHASE 8)
    ======================================================== -->
    <section class="process" id="process">
      <div class="container">
        <div class="process-header reveal">
          <div class="process-header-left">
            <div class="eyebrow">OUR PROCESS</div>
            <h2 class="section-title process-title" style="margin-top: 25px;">
              From First Conversation to Website Launch
            </h2>
          </div>

          <p class="process-copy">
            A simple four-step process, with no surprises along the way.
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
         WHY CHOOSE WEBSITE TAILORS (HONEST DIFFERENTIATORS)
    ======================================================== -->
    <section class="why-choose-section" id="why-choose" data-section="why-choose">
      <span id="testimonials" class="section-anchor" aria-hidden="true"></span>
      <div class="container">
        <div class="principles-heading reveal">
          <div class="eyebrow">HONEST PARTNERSHIP</div>
          <h2 class="section-title" style="margin-top: 25px;">
            Why Bangalore Businesses Choose Website Tailors
          </h2>
          <p style="color: var(--muted); font-size: 16px; line-height: 1.7; max-width: 680px; margin-top: 16px;">
            Website projects should be simple. We offer clear pricing, realistic timelines, direct communication and an agreed scope before work begins.
          </p>
        </div>

        <div class="why-grid">
          <!-- 01: Fixed, Upfront Pricing -->
          <div class="why-card reveal" tabindex="0">
            <div class="why-card-meta">
              <span class="why-card-num">01</span>
              <span class="why-card-tag">PRICING</span>
            </div>
            <div class="why-card-body">
              <h3 class="why-card-title">Fixed, Upfront Pricing</h3>
              <p class="why-card-desc">You know the full project cost before work begins. Domain, hosting and any third-party charges are listed separately.</p>
            </div>
            <div class="why-card-arrow" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="7" y1="17" x2="17" y2="7"></line>
                <polyline points="7 7 17 7 17 17"></polyline>
              </svg>
            </div>
          </div>

          <!-- 02: A Clear Launch Timeline -->
          <div class="why-card reveal" tabindex="0">
            <div class="why-card-meta">
              <span class="why-card-num">02</span>
              <span class="why-card-tag">TIMELINE</span>
            </div>
            <div class="why-card-body">
              <h3 class="why-card-title">A Clear Launch Timeline</h3>
              <p class="why-card-desc">Starter websites can go live within seven business days, as long as content and approvals are ready on time.</p>
            </div>
            <div class="why-card-arrow" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="7" y1="17" x2="17" y2="7"></line>
                <polyline points="7 7 17 7 17 17"></polyline>
              </svg>
            </div>
          </div>

          <!-- 03: You Own Your Website -->
          <div class="why-card reveal" tabindex="0">
            <div class="why-card-meta">
              <span class="why-card-num">03</span>
              <span class="why-card-tag">OWNERSHIP</span>
            </div>
            <div class="why-card-body">
              <h3 class="why-card-title">You Own Your Website</h3>
              <p class="why-card-desc">Your domain, hosting access and project files are handed over according to the ownership terms we agree at the start.</p>
            </div>
            <div class="why-card-arrow" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="7" y1="17" x2="17" y2="7"></line>
                <polyline points="7 7 17 7 17 17"></polyline>
              </svg>
            </div>
          </div>

          <!-- 04: Designed for Mobile First -->
          <div class="why-card reveal" tabindex="0">
            <div class="why-card-meta">
              <span class="why-card-num">04</span>
              <span class="why-card-tag">MOBILE-FIRST</span>
            </div>
            <div class="why-card-body">
              <h3 class="why-card-title">Designed for Mobile First</h3>
              <p class="why-card-desc">Most local customers will find you on their phones, so we design for mobile first, with readable content, responsive layouts and fast loading.</p>
            </div>
            <div class="why-card-arrow" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="7" y1="17" x2="17" y2="7"></line>
                <polyline points="7 7 17 7 17 17"></polyline>
              </svg>
            </div>
          </div>

          <!-- 05: Talk to the Person Building Your Site -->
          <div class="why-card reveal" tabindex="0">
            <div class="why-card-meta">
              <span class="why-card-num">05</span>
              <span class="why-card-tag">DIRECT ACCESS</span>
            </div>
            <div class="why-card-body">
              <h3 class="why-card-title">Talk to the Person Building Your Site</h3>
              <p class="why-card-desc">You deal directly with the person designing and developing your website. Nothing gets lost passing between departments.</p>
            </div>
            <div class="why-card-arrow" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="7" y1="17" x2="17" y2="7"></line>
                <polyline points="7 7 17 7 17 17"></polyline>
              </svg>
            </div>
          </div>

          <!-- 06: Clear Payment Milestones -->
          <div class="why-card reveal" tabindex="0">
            <div class="why-card-meta">
              <span class="why-card-num">06</span>
              <span class="why-card-tag">PAYMENT TERMS</span>
            </div>
            <div class="why-card-body">
              <h3 class="why-card-title">Clear Payment Milestones</h3>
              <p class="why-card-desc">We agree on the scope and payment schedule together, so you always know what each payment covers and when it is due.</p>
            </div>
            <div class="why-card-arrow" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="7" y1="17" x2="17" y2="7"></line>
                <polyline points="7 7 17 7 17 17"></polyline>
              </svg>
            </div>
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
            Website Design in Bangalore: Frequently Asked Questions
          </h2>
          <p style="color: var(--muted); font-size: 16px; line-height: 1.7; max-width: 680px; margin-top: 16px;">
            Answers about cost, timelines, ownership and what to expect when you work with Website Tailors.
          </p>
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
         ABOUT / FOUNDER SECTION
    ======================================================== -->
    <section class="founder-section" id="about">
      <div class="container">
        <div class="principles-heading reveal">
          <div class="eyebrow">LOCAL &amp; ACCOUNTABLE</div>
          <h2 class="section-title" style="margin-top: 25px;">
            Meet the Team Behind Website Tailors
          </h2>
          <p style="color: var(--muted); font-size: 16px; line-height: 1.7; max-width: 680px; margin-top: 16px;">
            When you hire Website Tailors, you work directly with the people who design and code your website — no account managers, no jargon.
          </p>
        </div>

        <div class="founder-cards-list">

          <!-- Founder 1 -->
          <div class="founder-row reveal">
            <div class="founder-portrait">
              <img src="assets/images/NACHIKETA%20NR.jpg" alt="NACHIKETA NR — Founder &amp; Lead Developer" class="founder-portrait-img" width="220" height="293" loading="lazy">
            </div>
            <div class="founder-bio">
              <h3 class="founder-name">NACHIKETA NR</h3>
              <hr class="founder-divider">
              <div class="founder-role">Lead Software Developer · Bangalore</div>
              <p class="founder-story">
                I started Website Tailors because I saw too many Bangalore businesses stuck between two bad options — agencies that overcharged for slow websites, or freelancers who disappeared after taking an advance.
              </p>
              <p class="founder-story">
                With a background in full-stack web development, I personally design, build and fine-tune every project. I work directly with clients over WhatsApp, so changes happen fast and sites launch on schedule.
              </p>
              <a href="https://www.linkedin.com/in/nachiketa12/" class="founder-linkedin" target="_blank" rel="noopener noreferrer" aria-label="Nachiketa NR on LinkedIn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="#0A66C2" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
              </a>
            </div>
          </div>

          <!-- Founder 2 -->
          <div class="founder-row reveal">
            <div class="founder-portrait">
              <img src="assets/images/Vinay%20Kumar%20.jpeg" alt="VINAY KUMAR — Co-Founder &amp; Design Lead" class="founder-portrait-img" width="220" height="293" loading="lazy">
            </div>
            <div class="founder-bio">
              <h3 class="founder-name">VINAY KUMAR</h3>
              <hr class="founder-divider">
              <div class="founder-role">Co-Founder &amp; Design Lead · Bangalore</div>
              
<p class="founder-story">
  I lead marketing and client relationships at Website Tailors — connecting with potential clients, understanding their business goals, and turning conversations into valuable opportunities.
</p>
<p class="founder-story">
  From generating qualified leads to building lasting client relationships, I focus on clear communication, tailored solutions, and helping businesses take the next step toward their digital growth.
</p>

              <a href="https://linkedin.com" class="founder-linkedin" target="_blank" rel="noopener noreferrer" aria-label="VINAY KUMAR on LinkedIn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="#0A66C2" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
              </a>
            </div>
          </div>

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
            Let's Plan Your Website
          </h2>

          <p class="cta-subtitle">
            Tell us what your business does and what you need. We'll suggest the right scope and the next steps, with no obligation.
          </p>
          <p style="font-size: 13.5px; color: #c8ff00; font-family: 'DM Mono', monospace; margin-top: 14px;">
            ⚡ We reply within 24 hours. No spam, no obligation.
          </p>

          <div class="cta-contact-info">
            <a href="mailto:websitetailorss@gmail.com">
              <span>Email:</span> websitetailorss@gmail.com ↗
            </a>
            <a href="tel:+919380552034">
              <span>Call:</span> +91 93805 52034 ↗
            </a>
            <span>Location: Bengaluru, Karnataka 560010, India</span>
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
              <div class="hp-field" aria-hidden="true" style="display: none !important;">
                <label for="website_url">Leave this field blank</label>
                <input type="text" id="website_url" name="website_url" autocomplete="off" tabindex="-1">
              </div>

              <!-- Hidden Storage Inputs for Questionnaire Selections -->
              <input type="hidden" name="service" id="qnInputService" value="">
              <input type="hidden" name="company" id="qnInputCompany" value="">

              <div class="qn-steps-container">
                <!-- STEP 1: NAME & BUSINESS -->
                <div class="qn-step active" data-step="1">
                  <h3 class="qn-question-title">TELL US ABOUT<br><span class="lime-text">YOUR BUSINESS</span></h3>
                  <p class="qn-question-sub">What's your name and business name?</p>

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

                  <div class="qn-reassurance-line" style="font-size: 13px; color: var(--muted); margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <span>We reply within 24 hours. No spam, no obligation.</span>
                  </div>

                  <div class="qn-actions">
                    <button type="submit" class="qn-submit-btn magnetic" id="qnSubmitBtn">
                      <span>Get a Free Quote ↗</span>
                    </button>
                  </div>
                </div>
              </div>
            </form>

            <!-- SUCCESS SCREEN (DYNAMICALLY REVEALED AFTER SUBMISSION) -->
            <div class="qn-success-screen" id="qnSuccessScreen" style="display: none;">
              <div class="qn-success-badge">✓ QUOTE REQUEST RECEIVED</div>
              <h2 class="qn-success-title">WHOOOO HOOOO!<br><span class="lime-text">You Made First Move.</span></h2>
              <p class="qn-success-desc">We will review your requirements and reply within 24hrs.</p>
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
