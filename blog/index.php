<?php
/**
 * WebsiteTailors - Blog Index & Resource Center
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
require_once dirname(__DIR__) . '/includes/init.php';

$siteSettings = get_all_settings();
$pageTitle = 'Blog & Guides for Bangalore Business Owners | Website Tailors';
$pageDescription = 'Practical guides on website costs in Bangalore, Google Business Profiles, and WhatsApp automation for local business owners.';
$activePage = 'blog';

require_once dirname(__DIR__) . '/includes/header.php';

$articles = [
    [
        'title' => 'How Much Does a Website Cost in Bangalore in 2026? Honest Price Guide',
        'slug' => 'how-much-does-a-website-cost-in-bangalore-2026.php',
        'excerpt' => 'A realistic breakdown of website development costs in Bengaluru: freelancers vs agencies vs DIY builders, hidden annual fees, and what you should expect to pay for a 5-page small business site.',
        'tag' => 'Pricing Guide',
        'read_time' => '5 min read',
        'date' => 'October 2026'
    ],
    [
        'title' => 'Website vs Google Business Profile: Do Bangalore Businesses Need Both?',
        'slug' => 'website-vs-google-business-profile.php',
        'excerpt' => 'Why relying solely on a Google Maps listing leaves your business vulnerable to competitors, and how connecting a fast website drives higher local rankings and direct enquiries.',
        'tag' => 'Local SEO',
        'read_time' => '4 min read',
        'date' => 'October 2026'
    ],
    [
        'title' => 'How WhatsApp Automation Helps Small Businesses Get More Enquiries',
        'slug' => 'how-whatsapp-automation-helps-small-businesses.php',
        'excerpt' => 'Why Indian customers prefer messaging over filling out long web forms, and how automated greeting replies and enquiry alerts double conversion rates for clinics, salons, and retail shops.',
        'tag' => 'Conversion Strategy',
        'read_time' => '5 min read',
        'date' => 'October 2026'
    ]
];
?>

  <main id="main-content" class="service-page">
    <section class="page-hero">
      <div class="container">
        <div class="page-hero-eyebrow">
          <span class="eyebrow-dot" aria-hidden="true"></span>
          <span>Knowledge &amp; Practical Advice</span>
        </div>
        <h1 class="page-hero-title">Guides &amp; Insights for Bangalore Businesses</h1>
        <p class="page-hero-lead">
          Honest, practical guides on website costs, search rankings, and WhatsApp lead generation — without confusing technical jargon.
        </p>
      </div>
    </section>

    <section class="service-content-section">
      <div class="container content-container">
        <div class="blog-articles-grid">
          <?php foreach ($articles as $article): ?>
            <article class="blog-card">
              <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                  <span style="font-family: 'DM Mono', monospace; font-size: 11.5px; color: #c8ff00; letter-spacing: 0.05em;"><?= e($article['tag']) ?></span>
                  <span style="font-family: 'DM Mono', monospace; font-size: 12px; color: var(--muted);"><?= e($article['read_time']) ?></span>
                </div>
                <h2 style="font-size: 20px; font-weight: 700; line-height: 1.4; margin-bottom: 12px; color: var(--text);">
                  <a href="<?= e(BASE_URL . '/blog/' . $article['slug']) ?>" style="color: inherit; text-decoration: none;">
                    <?= e($article['title']) ?>
                  </a>
                </h2>
                <p style="font-size: 14.5px; line-height: 1.6; color: var(--muted); margin-bottom: 20px;">
                  <?= e($article['excerpt']) ?>
                </p>
              </div>

              <div style="border-top: 1px solid var(--border); padding-top: 16px; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 13px; color: var(--muted);"><?= e($article['date']) ?></span>
                <a href="<?= e(BASE_URL . '/blog/' . $article['slug']) ?>" style="color: #c8ff00; font-size: 13.5px; font-weight: 600; text-decoration: none;">
                  Read Article ↗
                </a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <div class="service-cta-card" style="margin-top: 50px;">
          <h2>Need a Website for Your Business in Bangalore?</h2>
          <p>Get a mobile-first, conversion-ready website in 7 days for ₹9,999 with zero hidden charges.</p>
          <div class="service-cta-buttons">
            <a href="<?= e(BASE_URL) ?>/#contact" class="button button-primary magnetic">Get a Free Quote ↗</a>
            <a href="https://wa.me/919380552034?text=Hi%2C%20I%20have%20a%20question%20about%20a%20website" class="button button-secondary magnetic" target="_blank" rel="noopener noreferrer">WhatsApp Us ↗</a>
          </div>
        </div>
      </div>
    </section>
  </main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
