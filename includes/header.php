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
$pageDescription = $pageDescription ?? ($siteSettings['meta_description'] ?? 'Don\'t have a website yet? Or is your current one outdated? Website Tailors builds and redesigns fast, affordable business websites in Bangalore. Get a free quote.');
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
<html lang="en">
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

  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="<?= e(get_image_url($siteSettings['favicon_path'] ?? null, 'favicon')) ?>" />

  <!-- Google Fonts Preload & Swap -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Inter:wght@400;500;600;700&family=Inter+Tight:wght@700;800&family=Space+Grotesk:wght@500;600;700&display=swap" />
  <link
    href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Inter:wght@400;500;600;700&family=Inter+Tight:wght@700;800&family=Space+Grotesk:wght@500;600;700&display=swap"
    rel="stylesheet"
  />

  <!-- Main Stylesheet -->
  <link rel="stylesheet" href="<?= e(ASSETS_URL . '/css/style.css') ?>" />

  <!-- Structured Data (JSON-LD) -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": ["Organization", "LocalBusiness"],
        "@id": "https://websitetailors.com/#organization",
        "name": "Website Tailors",
        "url": "https://websitetailors.com/",
        "logo": "https://websitetailors.com/assets/images/favicon.svg",
        "image": "https://websitetailors.com/assets/images/website-tailors-og.jpg",
        "description": "Website Tailors builds and redesigns fast, affordable business websites and WhatsApp automation for businesses in Bangalore.",
        "telephone": "+919380552034",
        "email": "websietailorss@gmail.com",
        "address": {
          "@type": "PostalAddress",
          "addressLocality": "Bangalore",
          "addressRegion": "Karnataka",
          "addressCountry": "IN"
        },
        "priceRange": "$$"
      },
      {
        "@type": "WebSite",
        "@id": "https://websitetailors.com/#website",
        "url": "https://websitetailors.com/",
        "name": "Website Tailors",
        "publisher": {
          "@id": "https://websitetailors.com/#organization"
        },
        "inLanguage": "en"
      },
      {
        "@type": "Service",
        "@id": "https://websitetailors.com/#service-website-design",
        "name": "Website Design for New Businesses",
        "provider": {
          "@id": "https://websitetailors.com/#organization"
        },
        "serviceType": "Website Design",
        "description": "A complete business website for shops, clinics, studios, restaurants, coaches and service providers."
      },
      {
        "@type": "Service",
        "@id": "https://websitetailors.com/#service-website-redesign",
        "name": "Website Redesign",
        "provider": {
          "@id": "https://websitetailors.com/#organization"
        },
        "serviceType": "Website Redesign",
        "description": "Better design, faster loading and a layout that makes it easier for visitors to contact your business."
      },
      {
        "@type": "Service",
        "@id": "https://websitetailors.com/#service-whatsapp-automation",
        "name": "WhatsApp Automation",
        "provider": {
          "@id": "https://websitetailors.com/#organization"
        },
        "serviceType": "WhatsApp Automation",
        "description": "Auto-replies, enquiry capture, booking reminders and follow-ups on WhatsApp."
      }
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
        <a href="<?= e(BASE_URL) ?>/#services" class="nav-link magnetic <?= $activePage === 'services' ? 'active' : '' ?>">Services</a>
        <a href="<?= e(BASE_URL) ?>/#work" class="nav-link magnetic <?= $activePage === 'work' ? 'active' : '' ?>">Work</a>
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
    <a href="<?= e(BASE_URL) ?>/#services">Services</a>
    <a href="<?= e(BASE_URL) ?>/#work">Work</a>
    <a href="<?= e(BASE_URL) ?>/#process">Process</a>
    <a href="<?= e(BASE_URL) ?>/#about">About</a>
    <a href="<?= e(BASE_URL) ?>/#contact" class="accent">Let's Talk ↗</a>
  </div>
