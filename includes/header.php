<?php
/**
 * WebsiteTailors - Public Header Template
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) {
    die('Direct access not permitted.');
}

$siteSettings = $settings ?? get_all_settings();
$pageTitle = $pageTitle ?? ($siteSettings['meta_title'] ?? 'Affordable Website Design in Bangalore | Website Tailors');
$pageDescription = $pageDescription ?? ($siteSettings['meta_description'] ?? 'Need a website for your business? Website Tailors designs and redesigns business websites in Bangalore, with mobile-friendly layouts and clear ways to enquire.');
$activePage = $activePage ?? 'home';

// Normalized canonical URL calculation
$cleanPath = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
if ($cleanPath === '/index.php') {
    $cleanPath = '/';
}
$canonicalUrl = 'https://websitetailors.com' . $cleanPath;
$ogImageUrl = 'https://websitetailors.com/assets/images/website-tailors-og.jpg';
?>
<!DOCTYPE html>
<html lang="en-IN">
<head>
  <script>document.documentElement.classList.add('js');</script>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($pageDescription) ?>" />
  <meta name="robots" content="index, follow" />
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>" />

  <!-- Canonical URL -->
  <link rel="canonical" href="<?= e($canonicalUrl) ?>" />

  <!-- Dynamic SEO & Open Graph / Twitter Cards -->
  <meta property="og:type" content="website" />
  <meta property="og:site_name" content="<?= e($siteSettings['company_name'] ?? 'Website Tailors') ?>" />
  <meta property="og:title" content="<?= e($pageTitle) ?>" />
  <meta property="og:description" content="<?= e($pageDescription) ?>" />
  <meta property="og:url" content="<?= e($canonicalUrl) ?>" />
  <meta property="og:image" content="<?= e($ogImageUrl) ?>" />
  <meta property="og:image:width" content="1200" />
  <meta property="og:image:height" content="630" />

  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<?= e($pageTitle) ?>" />
  <meta name="twitter:description" content="<?= e($pageDescription) ?>" />
  <meta name="twitter:image" content="<?= e($ogImageUrl) ?>" />

  <!-- Favicon & Touch Icons -->
  <link rel="icon" type="image/svg+xml" href="<?= e(get_image_url($siteSettings['favicon_path'] ?? null, 'favicon')) ?>" />
  <link rel="apple-touch-icon" href="<?= e(BASE_URL) ?>/assets/images/favicon.svg" />

  <!-- HackerRank Local Font Preloads (0ms latency, self-hosted) -->
  <link rel="preload" href="<?= e(ASSETS_URL) ?>/fonts/satoshi-bold.woff2" as="font" type="font/woff2" crossorigin />
  <link rel="preload" href="<?= e(ASSETS_URL) ?>/fonts/satoshi-regular.woff2" as="font" type="font/woff2" crossorigin />
  <link rel="preload" href="<?= e(ASSETS_URL) ?>/fonts/satoshi-medium.woff2" as="font" type="font/woff2" crossorigin />

  <!-- Google Fonts & Fontshare Fallback Preload & Swap -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="preconnect" href="https://api.fontshare.com" crossorigin />
  <link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,600,700,900&display=swap" rel="stylesheet" />
  <link
    href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@400;500;600;700&family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap"
    rel="stylesheet"
  />

  <!-- Main Stylesheet -->
  <link rel="stylesheet" href="<?= e(ASSETS_URL . '/css/style.css?v=' . APP_VERSION) ?>" />

  <!-- Structured Data (JSON-LD) -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": ["ProfessionalService", "LocalBusiness"],
        "@id": "https://websitetailors.com/#organization",
        "name": "Website Tailors",
        "url": "https://websitetailors.com/",
        "logo": "https://websitetailors.com/assets/images/favicon.svg",
        "image": "https://websitetailors.com/assets/images/website-tailors-og.jpg",
        "description": "Website Tailors designs and builds fast, affordable business websites and WhatsApp automation for small businesses in Bangalore / Bengaluru.",
        "telephone": "+919380552034",
        "email": "<?= e(defined('BUSINESS_EMAIL') ? BUSINESS_EMAIL : 'websitetailorss@gmail.com') ?>",
        "address": {
          "@type": "PostalAddress",
          "streetAddress": "[OFFICE ADDRESS / LOCALITY, e.g. Rajajinagar, Bengaluru]",
          "addressLocality": "Bengaluru",
          "addressRegion": "Karnataka",
          "postalCode": "560010",
          "addressCountry": "IN"
        },
        "geo": {
          "@type": "GeoCoordinates",
          "latitude": 12.9716,
          "longitude": 77.5946
        },
        "areaServed": [
          {
            "@type": "City",
            "name": "Bangalore"
          },
          {
            "@type": "City",
            "name": "Bengaluru"
          }
        ],
        "priceRange": "₹₹",
        "openingHoursSpecification": {
          "@type": "OpeningHoursSpecification",
          "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"],
          "opens": "09:00",
          "closes": "19:00"
        },
        "sameAs": [
          "https://www.instagram.com/websitetailors",
          "https://www.linkedin.com/company/websitetailors"
        ]
      },
      {
        "@type": "WebSite",
        "@id": "https://websitetailors.com/#website",
        "url": "https://websitetailors.com/",
        "name": "Website Tailors",
        "publisher": {
          "@id": "https://websitetailors.com/#organization"
        },
        "inLanguage": "en-IN"
      },
      {
        "@type": "Service",
        "@id": "https://websitetailors.com/#service-website-design",
        "name": "Website Design for Small Businesses",
        "provider": {
          "@id": "https://websitetailors.com/#organization"
        },
        "serviceType": "Website Design",
        "description": "Complete 5-page mobile-first business website with WhatsApp integration, on-page SEO setup, and fast 7-day delivery.",
        "offers": {
          "@type": "Offer",
          "price": "9999",
          "priceCurrency": "INR",
          "url": "https://websitetailors.com/#services",
          "availability": "https://schema.org/InStock"
        }
      },
      {
        "@type": "Service",
        "@id": "https://websitetailors.com/#service-website-redesign",
        "name": "Website Redesign Services",
        "provider": {
          "@id": "https://websitetailors.com/#organization"
        },
        "serviceType": "Website Redesign",
        "description": "Modern redesign, faster loading speeds, and clearer enquiry forms to turn visitors into enquiries."
      },
      {
        "@type": "Service",
        "@id": "https://websitetailors.com/#service-whatsapp-automation",
        "name": "WhatsApp Automation for Business",
        "provider": {
          "@id": "https://websitetailors.com/#organization"
        },
        "serviceType": "WhatsApp Automation",
        "description": "Auto-replies, lead capture, appointment booking reminders and instant customer follow-ups on WhatsApp."
      }
<?php if (!empty($faqs)): ?>
      ,
      {
        "@type": "FAQPage",
        "@id": "https://websitetailors.com/#faq",
        "mainEntity": [
          <?php 
          $faqItems = [];
          foreach ($faqs as $faqItem) {
              $faqItems[] = json_encode([
                  '@type' => 'Question',
                  'name' => $faqItem['question'],
                  'acceptedAnswer' => [
                      '@type' => 'Answer',
                      'text' => $faqItem['answer']
                  ]
              ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
          }
          echo implode(",\n          ", $faqItems);
          ?>
        ]
      }
<?php endif; ?>
    ]
  }
  </script>
</head>
<body>

  <!-- Accessible Skip Link -->
  <a href="#main-content" class="skip-link">Skip to main content</a>

  <!-- =======================================================
       PRELOADER
  ======================================================== -->
  <div class="loader" id="loader" aria-hidden="true">
    <div class="loader-inner">
      <div class="loader-logo">
        <span>Website Tailors</span>
      </div>
      <div class="loader-line">
        <span></span>
      </div>
    </div>
  </div>

  <!-- =======================================================
       CUSTOM CURSOR
  ======================================================== -->
  <div class="cursor" aria-hidden="true"></div>
  <div class="cursor-ring" aria-hidden="true"></div>

  <!-- =======================================================
       STICKY NAVIGATION
  ======================================================== -->
  <header class="nav" id="navbar">
    <div class="nav-inner">
      <a href="<?= e(BASE_URL) ?>/#home" class="logo magnetic" aria-label="Website Tailors Home">
        Website Tailors
        <span class="logo-dot"></span>
      </a>

      <nav class="nav-links" aria-label="Primary Navigation">
        <a href="<?= e(BASE_URL) ?>/services.php" class="nav-link magnetic <?= $activePage === 'services' ? 'active' : '' ?>">Services</a>

        <a href="<?= e(BASE_URL) ?>/#process" class="nav-link magnetic <?= $activePage === 'process' ? 'active' : '' ?>">Process</a>
        <a href="<?= e(BASE_URL) ?>/#about" class="nav-link magnetic <?= $activePage === 'about' ? 'active' : '' ?>">About</a>
      </nav>

      <a href="<?= e(BASE_URL) ?>/#contact" class="nav-cta magnetic">
        <span>Let's Talk</span>
        <svg class="icon-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="7" y1="17" x2="17" y2="7"></line><polyline points="7 7 17 7 17 17"></polyline></svg>
      </a>

      <button class="menu" id="menuButton" aria-label="Toggle navigation menu" aria-expanded="false">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" y1="12" x2="20" y2="12"></line><line x1="4" y1="6" x2="20" y2="6"></line><line x1="4" y1="18" x2="20" y2="18"></line></svg>
      </button>
    </div>
  </header>

  <!-- =======================================================
       ANIMATED FULL-SCREEN MOBILE MENU
  ======================================================== -->
  <div class="mobile-menu" id="mobileMenu" aria-hidden="true">
    <a href="<?= e(BASE_URL) ?>/services.php">Services</a>
    <a href="<?= e(BASE_URL) ?>/#process">Process</a>
    <a href="<?= e(BASE_URL) ?>/#about">About</a>
    <a href="<?= e(BASE_URL) ?>/#contact" class="accent">Let's Talk ↗</a>
  </div>
