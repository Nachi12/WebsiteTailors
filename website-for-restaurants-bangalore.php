<?php
/**
 * WebsiteTailors - Industry Landing Page Scaffold: Website for Restaurants in Bangalore
 * Target Keyword: website for restaurant in Bangalore, cafe website design Bangalore
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
require_once __DIR__ . '/includes/init.php';

$siteSettings = get_all_settings();
$pageTitle = 'Website Design for Restaurants & Cafes in Bangalore | Website Tailors';
$pageDescription = 'Mobile-first websites for restaurants, cafes, and cloud kitchens in Bangalore from ₹9,999. Digital menus, WhatsApp table reservations, and zero commission direct orders.';
$activePage = 'services';

require_once __DIR__ . '/includes/header.php';
?>

  <main id="main-content" class="service-page">
    <!-- HERO SECTION -->
    <section class="page-hero">
      <div class="container">
        <div class="page-hero-eyebrow">
          <span class="eyebrow-dot" aria-hidden="true"></span>
          <span>Food &amp; Beverage Website Solutions</span>
        </div>
        <h1 class="page-hero-title">Website Design for Restaurants &amp; Cafes in Bangalore</h1>
        <p class="page-hero-lead">
          Stop paying 30% commissions on regular repeat customers. We build fast, beautiful websites for cafes, family restaurants, cloud kitchens, and bakeries in Bengaluru with digital menus and instant WhatsApp reservations. From ₹9,999.
        </p>

        <div class="page-hero-actions">
          <a href="<?= e(BASE_URL) ?>/#contact" class="button button-primary magnetic">Get Restaurant Website Quote ↗</a>
          <a href="https://wa.me/919380552034?text=Hi%2C%20I%27d%20like%20a%20website%20quote%20for%20my%20restaurant%20in%20Bangalore" class="button button-secondary magnetic" target="_blank" rel="noopener noreferrer">WhatsApp Us ↗</a>
        </div>
      </div>
    </section>

    <!-- CONTENT SECTION -->
    <section class="service-content-section">
      <div class="container content-container">
        <div class="content-block">
          <h2>Why Bangalore Food Businesses Need Their Own Website</h2>
          <p>
            Bangalore is one of the most vibrant dining cities in India. When food lovers look for weekend brunch spots in Koramangala or authentic South Indian eateries in Basavanagudi, they look up your photos, prices, and location online before stepping out:
          </p>
          <div class="features-two-col">
            <div class="feature-item">
              <h3>Fast, Mobile-Friendly Digital Menu</h3>
              <p>No blurry PDFs that take 30 seconds to download. We build clean, responsive web menus with high-resolution food photos and clear pricing.</p>
            </div>
            <div class="feature-item">
              <h3>Instant Table Bookings on WhatsApp</h3>
              <p>Guests can tap a single button to reserve a table for their group directly with your floor manager, eliminating third-party portal fees.</p>
            </div>
            <div class="feature-item">
              <h3>Direct Inquiries for Parties &amp; Catering</h3>
              <p>Capture high-margin corporate lunch bookings, birthday parties, and bulk catering enquiries directly through an intake form.</p>
            </div>
            <div class="feature-item">
              <h3>Google Maps Location &amp; Parking Details</h3>
              <p>Help diners find your entrance easily with embedded maps and crucial details about valet or roadside parking availability.</p>
            </div>
          </div>
        </div>

        <!-- TEMPLATE SCAFFOLD WITH EDITABLE PLACEHOLDERS -->
        <div class="content-block" style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 32px; margin-top: 30px;">
          <div style="font-family: 'DM Mono', monospace; font-size: 12px; color: #c8ff00; margin-bottom: 8px;">RESTAURANT BLUEPRINT TEMPLATE</div>
          <h2>Standard Blueprint for Your Restaurant Website</h2>
          <p style="color: var(--muted); margin-bottom: 20px;">Easily customized with your food photography, menu, and branding in 7 days:</p>

          <div style="display: flex; flex-direction: column; gap: 16px;">
            <div style="padding: 14px; background: rgba(255,255,255,0.03); border-radius: 8px;">
              <strong>1. Header / Navigation:</strong> Brand Logo · [RESTAURANT NAME] · Menu · Ambience Photos · Location · Reserve a Table
            </div>
            <div style="padding: 14px; background: rgba(255,255,255,0.03); border-radius: 8px;">
              <strong>2. Hero Banner:</strong> "Authentic [CUISINE: e.g. Coastal Seafood / Woodfired Pizzas] in [LOCALITY: e.g. Indiranagar, Bengaluru]" · Call [PHONE]
            </div>
            <div style="padding: 14px; background: rgba(255,255,255,0.03); border-radius: 8px;">
              <strong>3. Menu Highlights:</strong> [5–8 POPULAR SIGNATURE DISHES WITH IMAGES &amp; PRICES: e.g. Ghee Roast, Artisanal Pasta, Cold Brew]
            </div>
            <div style="padding: 14px; background: rgba(255,255,255,0.03); border-radius: 8px;">
              <strong>4. Ambience &amp; Hygiene:</strong> Interior seating gallery, outdoor pet-friendly terrace info, FSSAI hygiene assurance.
            </div>
            <div style="padding: 14px; background: rgba(255,255,255,0.03); border-radius: 8px;">
              <strong>5. Timings &amp; Directions:</strong> Mon–Sun: [HOURS: e.g. 12:00 PM – 11:30 PM] · Address: [STREET ADDRESS, BENGALURU]
            </div>
          </div>
        </div>

        <!-- BOTTOM CTA STRIP -->
        <div class="service-cta-card" style="margin-top: 40px;">
          <h2>Launch Your Restaurant Website in 7 Days</h2>
          <p>Get a complete mobile-optimized restaurant website with digital menu and WhatsApp reservations for ₹9,999.</p>
          <div class="service-cta-buttons">
            <a href="<?= e(BASE_URL) ?>/#contact" class="button button-primary magnetic">Get a Free Quote ↗</a>
            <a href="https://wa.me/919380552034?text=Hi%2C%20I%27d%20like%20a%20website%20for%20my%20restaurant" class="button button-secondary magnetic" target="_blank" rel="noopener noreferrer">WhatsApp Us Directly ↗</a>
          </div>
        </div>
      </div>
    </section>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
