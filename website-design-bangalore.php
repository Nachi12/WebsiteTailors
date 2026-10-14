<?php
/**
 * WebsiteTailors - Website Design in Bangalore
 * Target Keyword: website design company in Bangalore / Bengaluru, affordable website design Bangalore
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
require_once __DIR__ . '/includes/init.php';

$siteSettings = get_all_settings();
$pageTitle = 'Website Design Company in Bangalore | From ₹9,999 – Website Tailors';
$pageDescription = 'Affordable, mobile-first website design for small businesses in Bangalore from ₹9,999. 5-page sites, 7-day delivery, direct WhatsApp support, 100% code ownership.';
$activePage = 'services';

require_once __DIR__ . '/includes/header.php';
?>

  <main id="main-content" class="service-page">
    <!-- HERO SECTION -->
    <section class="page-hero">
      <div class="container">
        <div class="page-hero-eyebrow">
          <span class="eyebrow-dot" aria-hidden="true"></span>
          <span>Bangalore Website Design Services</span>
        </div>
        <h1 class="page-hero-title">Affordable Website Design Company in Bangalore</h1>
        <p class="page-hero-lead">
          We design fast, mobile-friendly websites for clinics, salons, restaurants, retail stores, and service businesses across Bengaluru. Fixed pricing starting at ₹9,999, live in 7 days, and you own 100% of your domain and code.
        </p>

        <div class="page-hero-actions">
          <a href="<?= e(BASE_URL) ?>/#contact" class="button button-primary magnetic">Get a Free Quote ↗</a>
          <a href="https://wa.me/919380552034?text=Hi%2C%20I%27d%20like%20a%20quote%20for%20a%20website%20in%20Bangalore" class="button button-secondary magnetic" target="_blank" rel="noopener noreferrer">WhatsApp Founder ↗</a>
        </div>
      </div>
    </section>

    <!-- VALUE PROPOSITION -->
    <section class="service-content-section">
      <div class="container content-container">
        <div class="content-block">
          <h2>Why Small Businesses in Bangalore Need a Dedicated Website</h2>
          <p>
            Many business owners in Bengaluru rely entirely on Instagram, Facebook pages, or Justdial listings to attract customers. While social media is useful for discovery, having your own dedicated website provides permanence and credibility that social media profiles cannot match.
          </p>
          <p>
            When a patient in Jayanagar searches for a dentist, a homeowner in Whitefield looks for an interior designer, or a diner in Indiranagar searches for weekend table reservations, Google prioritizes verified local websites. A clear, mobile-optimized website gives potential customers immediate answers to the four questions they care about most:
          </p>
          <ul class="check-list">
            <li><strong>What services or products do you offer?</strong> Clearly explained without jargon.</li>
            <li><strong>Where are you located?</strong> Embedded Google Map with your exact Bengaluru address and landmarks.</li>
            <li><strong>What are your opening hours or OPD timings?</strong> Up-to-date and reliable.</li>
            <li><strong>How can they reach you immediately?</strong> One-tap phone call and WhatsApp chat links.</li>
          </ul>
        </div>

        <div class="content-block">
          <h2>What Is Included in Our ₹9,999 Starter Website</h2>
          <p>
            Unlike agencies that bill thousands for features you will never use, our Starter Website is engineered specifically for local businesses that want genuine enquiries:
          </p>
          <div class="features-two-col">
            <div class="feature-item">
              <h3>5 Custom Designed Pages</h3>
              <p>Home, About Us, Services/Menu, Gallery/Portfolio, and Contact. Each page is written with clear copy focused on converting visitors into customers.</p>
            </div>
            <div class="feature-item">
              <h3>Mobile-First Responsive Layout</h3>
              <p>Over 80% of local Bengaluru traffic browses on smartphones. We build lightweight layouts that load in under a second on 4G and 5G connections.</p>
            </div>
            <div class="feature-item">
              <h3>Direct WhatsApp &amp; Call Integration</h3>
              <p>Prominent click-to-chat WhatsApp buttons and tap-to-call links so visitors can message your business with pre-filled enquiry texts.</p>
            </div>
            <div class="feature-item">
              <h3>On-Page SEO Structure</h3>
              <p>Semantic HTML5 tags, unique title and meta tags, Open Graph preview tags, and an XML sitemap submitted to Google Search Console for proper indexing.</p>
            </div>
            <div class="feature-item">
              <h3>Zero Platform Lock-in</h3>
              <p>You own your domain, hosting, source code, and administrative credentials completely. No monthly builder fees like Wix or Shopify if you don't need them.</p>
            </div>
            <div class="feature-item">
              <h3>7-Day Turnaround &amp; 14-Day Warranty</h3>
              <p>From initial design brief to live deployment in one week, followed by 14 days of complimentary support to fix any minor tweaks or adjustments.</p>
            </div>
          </div>
        </div>

        <div class="content-block">
          <h2>Our 4-Step Launch Sprint</h2>
          <p>We keep the development process simple and structured so you can stay focused on running your business:</p>
          <ol class="steps-list">
            <li>
              <strong>1. Discovery &amp; Scope:</strong> You share your business details, photos, and preferred references via WhatsApp or a short phone call.
            </li>
            <li>
              <strong>2. Design &amp; Prototype:</strong> We build the responsive layout and share a live staging preview link for your feedback within 4 business days.
            </li>
            <li>
              <strong>3. Revisions &amp; Testing:</strong> We refine text, test all contact forms, verify mobile responsiveness, and connect WhatsApp integration.
            </li>
            <li>
              <strong>4. Domain Setup &amp; Handover:</strong> We connect your domain, deploy the files, submit your sitemap to Google, and hand over all credentials.
            </li>
          </ol>
        </div>

        <!-- SERVICE SPECIFIC FAQS -->
        <div class="content-block service-faqs">
          <h2>Frequently Asked Questions About Website Design in Bangalore</h2>
          <div class="faq-accordion">
            <div class="faq-item">
              <button type="button" class="faq-question" aria-expanded="false">
                <span>How much does a website design cost for a small business in Bangalore?</span>
                <span class="faq-icon" aria-hidden="true">+</span>
              </button>
              <div class="faq-answer" hidden>
                <p>Our complete Starter Website costs ₹9,999. This includes 5 pages, mobile-responsive layout, WhatsApp click-to-chat integration, on-page SEO setup, and 7-day delivery. Domain registration (approx. ₹800–₹1,200/year) and web hosting (approx. ₹2,500–₹3,500/year) are billed directly by your registrar so you retain 100% ownership.</p>
              </div>
            </div>

            <div class="faq-item">
              <button type="button" class="faq-question" aria-expanded="false">
                <span>Can I update the website content myself after it launches?</span>
                <span class="faq-icon" aria-hidden="true">+</span>
              </button>
              <div class="faq-answer" hidden>
                <p>Yes. We provide clear documentation and straightforward file access. Furthermore, we include 14 days of free post-launch support for any minor text or photo updates. For ongoing monthly updates, we offer flexible pay-as-you-go maintenance without expensive lock-in contracts.</p>
              </div>
            </div>

            <div class="faq-item">
              <button type="button" class="faq-question" aria-expanded="false">
                <span>Do I have to pay the entire ₹9,999 upfront?</span>
                <span class="faq-icon" aria-hidden="true">+</span>
              </button>
              <div class="faq-answer" hidden>
                <p>No. We operate on a two-part payment structure: 50% advance deposit to commence design and development, and the remaining 50% only after you review the staging preview and approve the completed site for public launch.</p>
              </div>
            </div>

            <div class="faq-item">
              <button type="button" class="faq-question" aria-expanded="false">
                <span>Will my website rank number one on Google immediately?</span>
                <span class="faq-icon" aria-hidden="true">+</span>
              </button>
              <div class="faq-answer" hidden>
                <p>We build your website with an SEO-ready structure, semantic headings, fast load times, and XML sitemaps. However, no honest agency can guarantee a #1 ranking on Google, as search algorithms depend on local competition, reviews, and domain authority. We give your website the optimal technical foundation to rank locally over time.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- BOTTOM CTA STRIP -->
        <div class="service-cta-card">
          <h2>Ready to Launch Your Business Website in Bangalore?</h2>
          <p>Talk directly with the founder to discuss your requirements, get an honest quote, and go live in 7 days.</p>
          <div class="service-cta-buttons">
            <a href="<?= e(BASE_URL) ?>/#contact" class="button button-primary magnetic">Get a Free Quote ↗</a>
            <a href="https://wa.me/919380552034?text=Hi%2C%20I%27d%20like%20a%20quote%20for%20a%20website%20in%20Bangalore" class="button button-secondary magnetic" target="_blank" rel="noopener noreferrer">WhatsApp Us Directly ↗</a>
          </div>
        </div>
      </div>
    </section>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
