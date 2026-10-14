<?php
/**
 * WebsiteTailors - Industry Landing Page Scaffold: Website for Clinics in Bangalore
 * Target Keyword: website for clinic in Bangalore, dental clinic website Bangalore
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
require_once __DIR__ . '/includes/init.php';

$siteSettings = get_all_settings();
$pageTitle = 'Website Design for Clinics & Doctors in Bangalore | Website Tailors';
$pageDescription = 'Custom, mobile-friendly websites for clinics, dental practices, and doctors in Bangalore from ₹9,999. Online appointment booking, OPD hours, and WhatsApp integration.';
$activePage = 'services';

require_once __DIR__ . '/includes/header.php';
?>

  <main id="main-content" class="service-page">
    <!-- HERO SECTION -->
    <section class="page-hero">
      <div class="container">
        <div class="page-hero-eyebrow">
          <span class="eyebrow-dot" aria-hidden="true"></span>
          <span>Healthcare &amp; Clinic Website Solutions</span>
        </div>
        <h1 class="page-hero-title">Website Design for Clinics &amp; Doctors in Bangalore</h1>
        <p class="page-hero-lead">
          Patients in Bengaluru search online before visiting a clinic. We build clean, fast, and trustworthy websites for dental clinics, pediatricians, dermatologists, and diagnostic centers with online appointment booking and WhatsApp integration. From ₹9,999.
        </p>

        <div class="page-hero-actions">
          <a href="<?= e(BASE_URL) ?>/#contact" class="button button-primary magnetic">Get Clinic Website Quote ↗</a>
          <a href="https://wa.me/919380552034?text=Hi%2C%20I%27d%20like%20a%20website%20quote%20for%20my%20clinic%20in%20Bangalore" class="button button-secondary magnetic" target="_blank" rel="noopener noreferrer">WhatsApp Us ↗</a>
        </div>
      </div>
    </section>

    <!-- CONTENT SECTION -->
    <section class="service-content-section">
      <div class="container content-container">
        <div class="content-block">
          <h2>What Every Clinic Website in Bangalore Needs</h2>
          <p>
            When a patient is suffering from a toothache in Jayanagar or looking for an evening pediatrician in Whitefield, they want clear, reassuring information without friction:
          </p>
          <div class="features-two-col">
            <div class="feature-item">
              <h3>Doctor Credentials &amp; Degrees</h3>
              <p>Display your medical qualifications, certifications, years of practice, and areas of specialization clearly to build patient confidence.</p>
            </div>
            <div class="feature-item">
              <h3>Accurate OPD &amp; Consultation Timings</h3>
              <p>Prevent walk-in confusion by clearly publishing morning and evening consultation slots, Sunday hours, and holiday schedules.</p>
            </div>
            <div class="feature-item">
              <h3>WhatsApp Appointment Booking</h3>
              <p>Patients can click one button to request an appointment slot directly with your clinic reception on WhatsApp.</p>
            </div>
            <div class="feature-item">
              <h3>Google Maps Location &amp; Parking Tips</h3>
              <p>Embedded interactive map showing your exact clinic entrance, nearby landmarks, and parking availability for patients arriving by car.</p>
            </div>
          </div>
        </div>

        <!-- TEMPLATE SCAFFOLD WITH EDITABLE PLACEHOLDERS -->
        <div class="content-block" style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 32px; margin-top: 30px;">
          <div style="font-family: 'DM Mono', monospace; font-size: 12px; color: #c8ff00; margin-bottom: 8px;">CLINIC STRUCTURE TEMPLATE</div>
          <h2>Standard Blueprint for Your Clinic Website</h2>
          <p style="color: var(--muted); margin-bottom: 20px;">We customize the following blueprint for your practice in 7 days:</p>

          <div style="display: flex; flex-direction: column; gap: 16px;">
            <div style="padding: 14px; background: rgba(255,255,255,0.03); border-radius: 8px;">
              <strong>1. Header / Navigation:</strong> Clinic Logo · [CLINIC NAME] · Services · Doctors · Timings · Emergency / WhatsApp Button
            </div>
            <div style="padding: 14px; background: rgba(255,255,255,0.03); border-radius: 8px;">
              <strong>2. Hero Banner:</strong> "Compassionate, Expert Healthcare in [LOCALITY: e.g. Indiranagar, Bengaluru]" · Call [PHONE] · Book on WhatsApp
            </div>
            <div style="padding: 14px; background: rgba(255,255,255,0.03); border-radius: 8px;">
              <strong>3. Doctor Profile:</strong> Dr. [DOCTOR NAME], [DEGREES: e.g. MBBS, MD, BDS] · [SPECIALTY: e.g. Orthodontics &amp; Implantology]
            </div>
            <div style="padding: 14px; background: rgba(255,255,255,0.03); border-radius: 8px;">
              <strong>4. Treatments &amp; Services:</strong> [LIST OF 6–8 CORE TREATMENTS: e.g. Root Canal, Teeth Whitening, Pediatric Care]
            </div>
            <div style="padding: 14px; background: rgba(255,255,255,0.03); border-radius: 8px;">
              <strong>5. Timings &amp; Location:</strong> Mon–Sat: [OPD HOURS: e.g. 9:00 AM – 1:00 PM, 5:00 PM – 9:00 PM] · Address: [CLINIC ADDRESS, BENGALURU]
            </div>
          </div>
        </div>

        <!-- BOTTOM CTA STRIP -->
        <div class="service-cta-card" style="margin-top: 40px;">
          <h2>Launch Your Clinic Website in 7 Days</h2>
          <p>Get a complete 5-page mobile-friendly clinic website with appointment booking and WhatsApp integration for ₹9,999.</p>
          <div class="service-cta-buttons">
            <a href="<?= e(BASE_URL) ?>/#contact" class="button button-primary magnetic">Get a Free Quote ↗</a>
            <a href="https://wa.me/919380552034?text=Hi%2C%20I%27d%20like%20a%20website%20for%20my%20clinic" class="button button-secondary magnetic" target="_blank" rel="noopener noreferrer">WhatsApp Us Directly ↗</a>
          </div>
        </div>
      </div>
    </section>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
