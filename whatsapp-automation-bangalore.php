<?php
/**
 * WebsiteTailors - WhatsApp Automation in Bangalore
 * Target Keyword: WhatsApp automation for business Bangalore, WhatsApp marketing automation
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
require_once __DIR__ . '/includes/init.php';

$siteSettings = get_all_settings();
$pageTitle = 'WhatsApp Automation for Business in Bangalore | Website Tailors';
$pageDescription = 'WhatsApp automation for small businesses in Bangalore from ₹4,999. Instant auto-replies, lead capture alerts, appointment reminders, and follow-ups.';
$activePage = 'services';

require_once __DIR__ . '/includes/header.php';
?>

  <main id="main-content" class="service-page">
    <!-- HERO SECTION -->
    <section class="page-hero">
      <div class="container">
        <div class="page-hero-eyebrow">
          <span class="eyebrow-dot" aria-hidden="true"></span>
          <span>Bangalore WhatsApp Automation</span>
        </div>
        <h1 class="page-hero-title">WhatsApp Automation for Small Businesses in Bangalore</h1>
        <p class="page-hero-lead">
          Stop losing potential customers who visit your website and leave without calling. We set up automated WhatsApp workflows that capture enquiries instantly, send welcome replies, share service menus, and notify you in real time. Standalone from ₹[4,999] or bundled with a website from ₹[12,999].
        </p>

        <div class="page-hero-actions">
          <a href="<?= e(BASE_URL) ?>/#contact" class="button button-primary magnetic">Get Automation Pricing ↗</a>
          <a href="https://wa.me/919380552034?text=Hi%2C%20I%27d%20like%20to%20learn%20more%20about%20WhatsApp%20automation%20for%20my%20business" class="button button-secondary magnetic" target="_blank" rel="noopener noreferrer">Test Demo on WhatsApp ↗</a>
        </div>
      </div>
    </section>

    <!-- CONTENT SECTION -->
    <section class="service-content-section">
      <div class="container content-container">
        <div class="content-block">
          <h2>Why WhatsApp Is the #1 Conversion Channel in India</h2>
          <p>
            In Bangalore, practically every consumer uses WhatsApp daily. When someone browses your website for a dental checkup, home salon service, or catering menu, they rarely want to fill out a 7-field contact form and wait 24 hours for an email response.
          </p>
          <p>
            Enabling automated WhatsApp interactions delivers distinct commercial advantages:
          </p>
          <ul class="check-list">
            <li><strong>98% Open Rate:</strong> WhatsApp messages are opened within minutes, compared to less than 20% for marketing emails.</li>
            <li><strong>Zero Friction for Indian Buyers:</strong> Customers click one button on your website, their WhatsApp app opens with a pre-filled enquiry text, and the conversation begins.</li>
            <li><strong>Instant Automated Replies:</strong> Even if a customer visits your website at 10 PM on Sunday, automated greeting replies acknowledge their query and capture their requirements.</li>
            <li><strong>Real-Time Owner Notifications:</strong> Your personal or business phone gets pinged immediately whenever a qualified lead requests a quote or consultation.</li>
          </ul>
        </div>

        <div class="content-block">
          <h2>How Local Bangalore Businesses Use WhatsApp Automation</h2>
          <div class="features-two-col">
            <div class="feature-item">
              <h3>Clinics &amp; Healthcare</h3>
              <p>Patients send a message to check doctor availability, receive consultation fees, book appointment slots, and receive reminder messages to reduce no-shows.</p>
            </div>
            <div class="feature-item">
              <h3>Restaurants &amp; Cafes</h3>
              <p>Diners view the visual menu PDF, check table availability for large groups, and receive instant location and parking instructions.</p>
            </div>
            <div class="feature-item">
              <h3>Salons &amp; Wellness Spas</h3>
              <p>Clients choose styling or spa packages from an interactive list and confirm appointment timings with automatic reminders.</p>
            </div>
            <div class="feature-item">
              <h3>Coaching Centres &amp; Tutors</h3>
              <p>Parents download the course prospectus, view weekly class schedules, and schedule an in-person campus visit or trial class.</p>
            </div>
            <div class="feature-item">
              <h3>Real Estate &amp; Interior Studios</h3>
              <p>Prospective homeowners receive portfolio photos, project brochures, and book a site visit consultation with the design lead.</p>
            </div>
            <div class="feature-item">
              <h3>Retail &amp; Custom Boutiques</h3>
              <p>Local shoppers check size availability, view the latest festive collection catalog, and request delivery timelines.</p>
            </div>
          </div>
        </div>

        <div class="content-block">
          <h2>Setup &amp; Pricing Options</h2>
          <p>
            We provide practical, transparent automation setups that work either with the free WhatsApp Business app or official WhatsApp Cloud API:
          </p>
          <div class="pricing-mini-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 24px;">
            <div class="pricing-mini-card" style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 24px;">
              <span style="font-family: 'DM Mono', monospace; font-size: 12px; color: #c8ff00;">STANDALONE</span>
              <h3 style="margin: 8px 0 12px 0;">WhatsApp Automation Starter</h3>
              <div style="font-size: 26px; font-weight: 700; color: var(--text); margin-bottom: 12px;">₹[4,999] <span style="font-size: 13px; font-weight: normal; color: var(--muted);">one-time setup</span></div>
              <ul style="font-size: 14px; color: var(--muted); line-height: 1.8; padding-left: 18px;">
                <li>Website click-to-chat triggers with pre-filled texts</li>
                <li>Automated welcome &amp; away messages</li>
                <li>Quick-reply catalog shortcuts</li>
                <li>Lead notification webhook to your phone</li>
              </ul>
            </div>

            <div class="pricing-mini-card" style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 24px;">
              <span style="font-family: 'DM Mono', monospace; font-size: 12px; color: #c8ff00;">POPULAR BUNDLE</span>
              <h3 style="margin: 8px 0 12px 0;">Website + WhatsApp Package</h3>
              <div style="font-size: 26px; font-weight: 700; color: var(--text); margin-bottom: 12px;">₹[12,999] <span style="font-size: 13px; font-weight: normal; color: var(--muted);">complete package</span></div>
              <ul style="font-size: 14px; color: var(--muted); line-height: 1.8; padding-left: 18px;">
                <li>Everything in Starter Website (5 pages, 7 days)</li>
                <li>Full WhatsApp chat flow integration</li>
                <li>Automatic website enquiry alerts to WhatsApp</li>
                <li>Full source code and ownership transfer</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- WHATSAPP FAQS -->
        <div class="content-block service-faqs">
          <h2>Frequently Asked Questions About WhatsApp Automation</h2>
          <div class="faq-accordion">
            <div class="faq-item">
              <button type="button" class="faq-question" aria-expanded="false">
                <span>Do I need a separate phone number for WhatsApp automation?</span>
                <span class="faq-icon" aria-hidden="true">+</span>
              </button>
              <div class="faq-answer" hidden>
                <p>For standard click-to-chat and WhatsApp Business app setups, you can use your existing business phone number. For official WhatsApp Cloud API setups with advanced bot menus, an exclusive virtual or landline number is recommended. We guide you through the ideal setup based on your daily conversation volume.</p>
              </div>
            </div>

            <div class="faq-item">
              <button type="button" class="faq-question" aria-expanded="false">
                <span>Are there recurring monthly fees for WhatsApp automation?</span>
                <span class="faq-icon" aria-hidden="true">+</span>
              </button>
              <div class="faq-answer" hidden>
                <p>Our direct website integration with the WhatsApp Business app carries zero monthly fees. If you choose an advanced enterprise Cloud API setup with third-party automation tools, Meta charges a small fee per conversation (typically 30 to 80 paise for marketing/service templates) which is paid directly to Meta.</p>
              </div>
            </div>

            <div class="faq-item">
              <button type="button" class="faq-question" aria-expanded="false">
                <span>How quickly can WhatsApp automation be set up?</span>
                <span class="faq-icon" aria-hidden="true">+</span>
              </button>
              <div class="faq-answer" hidden>
                <p>Standard website WhatsApp triggers and automated response flows can be set up and tested within 48 to 72 hours. When combined with a new website project, everything goes live together in 7 days.</p>
              </div>
            </div>

            <div class="faq-item">
              <button type="button" class="faq-question" aria-expanded="false">
                <span>Can customers still talk to a human if they have complex questions?</span>
                <span class="faq-icon" aria-hidden="true">+</span>
              </button>
              <div class="faq-answer" hidden>
                <p>Yes. Automation handles greeting, basic qualification, and information delivery. You or your staff can step in and reply directly in the chat window at any moment just like a regular conversation.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- BOTTOM CTA STRIP -->
        <div class="service-cta-card">
          <h2>Test WhatsApp Automation for Your Business</h2>
          <p>Send us a quick WhatsApp message to see how an automated flow works in practice, and get a tailored plan for your business.</p>
          <div class="service-cta-buttons">
            <a href="https://wa.me/919380552034?text=Hi%2C%20I%27d%20like%20to%20test%20the%20WhatsApp%20automation%20demo" class="button button-primary magnetic" target="_blank" rel="noopener noreferrer">Test WhatsApp Demo ↗</a>
            <a href="<?= e(BASE_URL) ?>/#contact" class="button button-secondary magnetic">Request a Free Quote ↗</a>
          </div>
        </div>
      </div>
    </section>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
