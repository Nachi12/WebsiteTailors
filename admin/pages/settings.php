<?php
/**
 * Website Tailors Admin — Global Site Settings Management
 *
 * Allows updating business identity, contact channels, social profiles,
 * and brand assets (Logo & Favicon) with secure image upload handling.
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) { define('WebsiteTailors_INIT', true); }
require_once dirname(__DIR__) . '/includes/auth_guard.php';

$pageTitle = 'Site Settings';
$breadcrumb = 'Settings';
$db = Database::getInstance();

$error = null;
$success = null;

// Handle Form Submission
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } else {
        $settingsToSave = [
            'company_name' => sanitize_text($_POST['company_name'] ?? ''),
            'tagline'      => sanitize_text($_POST['tagline'] ?? ''),
            'email'        => sanitize_email($_POST['email'] ?? '') ?? '',
            'phone'        => sanitize_text($_POST['phone'] ?? ''),
            'address'      => sanitize_text($_POST['address'] ?? ''),
            'linkedin'     => sanitize_text($_POST['linkedin'] ?? ''),
            'instagram'    => sanitize_text($_POST['instagram'] ?? ''),
            'facebook'     => sanitize_text($_POST['facebook'] ?? ''),
            'github'       => sanitize_text($_POST['github'] ?? ''),
        ];

        if (empty($settingsToSave['company_name'])) {
            $error = 'Company name cannot be blank.';
        } elseif (empty($settingsToSave['email'])) {
            $error = 'A valid business contact email is required.';
        } else {
            // 1. Handle Brand Logo Upload
            if (!empty($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
                $logoResult = handle_file_upload($_FILES['logo_file'], 'settings');
                if ($logoResult['success']) {
                    $settingsToSave['logo'] = $logoResult['path'];
                } else {
                    $error = 'Logo upload error: ' . $logoResult['error'];
                }
            }

            // 2. Handle Favicon Upload
            if (!empty($_FILES['favicon_file']) && $_FILES['favicon_file']['error'] === UPLOAD_ERR_OK) {
                $favResult = handle_file_upload($_FILES['favicon_file'], 'settings');
                if ($favResult['success']) {
                    $settingsToSave['favicon'] = $favResult['path'];
                } else {
                    $error = 'Favicon upload error: ' . $favResult['error'];
                }
            }

            if (!$error) {
                try {
                    $now = date('Y-m-d H:i:s');
                    foreach ($settingsToSave as $key => $val) {
                        // Check if key exists
                        $exists = $db->fetch("SELECT id FROM site_settings WHERE setting_key = :k", [':k' => $key]);
                        if ($exists) {
                            $db->update('site_settings', ['setting_value' => $val, 'updated_at' => $now], 'setting_key = :k', [':k' => $key]);
                        } else {
                            $group = in_array($key, ['linkedin', 'instagram', 'facebook', 'github'], true) ? 'social' : (in_array($key, ['logo', 'favicon'], true) ? 'branding' : 'general');
                            $db->insert('site_settings', [
                                'setting_key'   => $key,
                                'setting_value' => $val,
                                'setting_group' => $group,
                                'created_at'    => $now,
                                'updated_at'    => $now
                            ]);
                        }
                    }
                    $success = 'Site settings updated successfully!';
                } catch (\Throwable $e) {
                    $error = 'Failed to save settings: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch all current settings into key-value map
$settings = [];
try {
    $rows = $db->fetchAll("SELECT setting_key, setting_value FROM site_settings");
    foreach ($rows as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
} catch (\Throwable $e) {
    error_log("Settings fetch notice: " . $e->getMessage());
}

require_once dirname(__DIR__) . '/includes/admin_header.php';
?>

  <div class="page-header">
    <div>
      <h1 class="page-title">Global Site Settings</h1>
      <p class="page-subtitle">Manage company contact info, social accounts, and public branding assets.</p>
    </div>
  </div>

  <?php if ($success): ?>
    <div class="admin-alert alert-success" style="margin-bottom: 24px;">
      <span><?= e($success) ?></span>
      <button class="alert-dismiss-btn">&times;</button>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="admin-alert alert-error" style="margin-bottom: 24px;">
      <span><?= e($error) ?></span>
      <button class="alert-dismiss-btn">&times;</button>
    </div>
  <?php endif; ?>

  <form method="POST" action="settings.php" enctype="multipart/form-data" class="admin-form">
    <?= csrf_field() ?>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
      <!-- Left Column: Business & Social Details -->
      <div style="display: flex; flex-direction: column; gap: 24px;">
        <!-- General Information -->
        <div class="data-card" style="padding: 28px;">
          <h2 style="font-size: 16px; font-weight: 700; margin-bottom: 20px; color: var(--text-dark); border-bottom: 1px solid var(--border-light); padding-bottom: 12px;">
            1. Business Profile & Identity
          </h2>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div class="admin-form-group">
              <label for="company_name" class="admin-form-label">Company Name</label>
              <input
                type="text"
                id="company_name"
                name="company_name"
                class="admin-form-input"
                required
                value="<?= e($settings['company_name'] ?? 'Website Tailors') ?>"
              />
            </div>

            <div class="admin-form-group">
              <label for="tagline" class="admin-form-label">Brand Tagline</label>
              <input
                type="text"
                id="tagline"
                name="tagline"
                class="admin-form-input"
                value="<?= e($settings['tagline'] ?? 'Engineering Digital Precision.') ?>"
              />
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div class="admin-form-group">
              <label for="email" class="admin-form-label">Contact Email</label>
              <input
                type="email"
                id="email"
                name="email"
                class="admin-form-input"
                required
                value="<?= e($settings['email'] ?? 'websietailorss@gmail.com') ?>"
              />
            </div>

            <div class="admin-form-group">
              <label for="phone" class="admin-form-label">Phone Number</label>
              <input
                type="text"
                id="phone"
                name="phone"
                class="admin-form-input"
                value="<?= e($settings['phone'] ?? '9380552034') ?>"
              />
            </div>
          </div>

          <div class="admin-form-group">
            <label for="address" class="admin-form-label">Studio Physical Location</label>
            <input
              type="text"
              id="address"
              name="address"
              class="admin-form-input"
              value="<?= e($settings['address'] ?? 'Bangalore-560010, karnataka. India') ?>"
            />
          </div>
        </div>

        <!-- Social Channels -->
        <div class="data-card" style="padding: 28px;">
          <h2 style="font-size: 16px; font-weight: 700; margin-bottom: 20px; color: var(--text-dark); border-bottom: 1px solid var(--border-light); padding-bottom: 12px;">
            2. Social Profiles & Community
          </h2>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div class="admin-form-group">
              <label for="linkedin" class="admin-form-label">LinkedIn Profile URL</label>
              <input
                type="url"
                id="linkedin"
                name="linkedin"
                class="admin-form-input"
                placeholder="https://linkedin.com/company/websitetailors"
                value="<?= e($settings['linkedin'] ?? '') ?>"
              />
            </div>

            <div class="admin-form-group">
              <label for="instagram" class="admin-form-label">Instagram URL</label>
              <input
                type="url"
                id="instagram"
                name="instagram"
                class="admin-form-input"
                placeholder="https://instagram.com/websitetailors"
                value="<?= e($settings['instagram'] ?? '') ?>"
              />
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div class="admin-form-group">
              <label for="facebook" class="admin-form-label">Facebook Page URL</label>
              <input
                type="url"
                id="facebook"
                name="facebook"
                class="admin-form-input"
                placeholder="https://facebook.com/websitetailors"
                value="<?= e($settings['facebook'] ?? '') ?>"
              />
            </div>

            <div class="admin-form-group">
              <label for="github" class="admin-form-label">GitHub Organization URL</label>
              <input
                type="url"
                id="github"
                name="github"
                class="admin-form-input"
                placeholder="https://github.com/websitetailors"
                value="<?= e($settings['github'] ?? '') ?>"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column: Logo & Favicon Assets -->
      <div style="display: flex; flex-direction: column; gap: 24px;">
        <div class="data-card" style="padding: 24px;">
          <h3 style="font-size: 15px; font-weight: 700; margin-bottom: 16px;">Brand Assets</h3>

          <!-- Logo Upload -->
          <div class="admin-form-group">
            <label class="admin-form-label">Website Logo</label>
            <div class="image-preview-box <?= !empty($settings['logo']) ? 'has-image' : '' ?>" id="logoPreview" style="height: 100px; margin-bottom: 8px;">
              <?php if (!empty($settings['logo'])): ?>
                <img src="<?= e(BASE_URL . $settings['logo']) ?>" alt="Logo" style="object-fit: contain; padding: 12px;" />
              <?php else: ?>
                <div class="image-preview-placeholder">Logo Preview</div>
              <?php endif; ?>
            </div>
            <input
              type="file"
              id="logo_file"
              name="logo_file"
              accept=".jpg,.jpeg,.png,.webp,.svg"
              data-preview="#logoPreview"
              class="admin-form-input"
              style="padding: 6px;"
            />
            <small style="color: var(--text-muted); font-size: 11px;">Recommended: Transparent PNG or WebP</small>
          </div>

          <!-- Favicon Upload -->
          <div class="admin-form-group" style="margin-top: 20px;">
            <label class="admin-form-label">Browser Favicon</label>
            <div class="image-preview-box <?= !empty($settings['favicon']) ? 'has-image' : '' ?>" id="faviconPreview" style="width: 48px; height: 48px; margin-bottom: 8px;">
              <?php if (!empty($settings['favicon'])): ?>
                <img src="<?= e(BASE_URL . $settings['favicon']) ?>" alt="Favicon" style="object-fit: contain; padding: 6px;" />
              <?php else: ?>
                <div class="image-preview-placeholder" style="font-size: 9px;">Favicon</div>
              <?php endif; ?>
            </div>
            <input
              type="file"
              id="favicon_file"
              name="favicon_file"
              accept=".png,.ico,.webp,.svg"
              data-preview="#faviconPreview"
              class="admin-form-input"
              style="padding: 6px;"
            />
            <small style="color: var(--text-muted); font-size: 11px;">Square PNG, ICO, or WebP</small>
          </div>

          <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-light);">
            <button type="submit" class="btn-primary-admin" style="width: 100%; justify-content: center;">
              Save All Settings &rarr;
            </button>
          </div>
        </div>
      </div>
    </div>
  </form>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>
