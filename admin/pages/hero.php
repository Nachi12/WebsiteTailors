<?php
/**
 * Website Tailors Admin — Hero Content Management
 *
 * Allows administrators to update the homepage headline, subheadline,
 * description, primary & secondary CTAs, and links.
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) { define('WebsiteTailors_INIT', true); }
require_once dirname(__DIR__) . '/includes/auth_guard.php';

$pageTitle = 'Hero Management';
$breadcrumb = 'Hero Content';
$db = Database::getInstance();

// Fetch current hero content
$hero = null;
try {
    $hero = $db->fetch("SELECT * FROM hero_content WHERE id = 1");
} catch (\Throwable $e) {
    error_log("Hero fetch notice: " . $e->getMessage());
}

// Fallback defaults if table is empty
if (!$hero) {
    $hero = [
        'id'                    => 1,
        'badge_text'            => 'Digital studio / 2026',
        'headline'              => "WE MAKE\nDIGITAL\nTHINGS WORK.",
        'subheadline'           => 'We turn ideas into websites, software and digital experiences that actually work.',
        'description'           => 'From the first sketch to the final launch, Website Tailors designs and builds digital products around the way your business actually works.',
        'primary_button_text'   => 'Start a Project',
        'primary_button_link'   => '#contact',
        'secondary_button_text' => 'Explore Services',
        'secondary_button_link' => '#services'
    ];
}

$error = null;
$success = null;

// Handle Form Submission (Save or Reset)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } else {
        $action = sanitize_text($_POST['action'] ?? 'save');

        if ($action === 'reset') {
            // Reset to defaults
            $defaultData = [
                'badge_text'            => 'Digital studio / 2026',
                'headline'              => "WE MAKE\nDIGITAL\nTHINGS WORK.",
                'subheadline'           => 'We turn ideas into websites, software and digital experiences that actually work.',
                'description'           => 'From the first sketch to the final launch, Website Tailors designs and builds digital products around the way your business actually works.',
                'primary_button_text'   => 'Start a Project',
                'primary_button_link'   => '#contact',
                'secondary_button_text' => 'Explore Services',
                'secondary_button_link' => '#services',
                'updated_at'            => date('Y-m-d H:i:s')
            ];

            try {
                $db->update('hero_content', $defaultData, 'id = 1');
                $hero = array_merge($hero, $defaultData);
                $success = 'Hero content has been successfully reset to default copy.';
            } catch (\Throwable $e) {
                $error = 'Failed to reset hero content: ' . $e->getMessage();
            }
        } elseif ($action === 'save') {
            $headline            = sanitize_text($_POST['headline'] ?? '');
            $subheadline         = sanitize_text($_POST['subheadline'] ?? '');
            $description         = sanitize_text($_POST['description'] ?? '');
            $primaryCta          = sanitize_text($_POST['primary_button_text'] ?? '');
            $primaryCtaUrl       = sanitize_text($_POST['primary_button_link'] ?? '');
            $secondaryCta        = sanitize_text($_POST['secondary_button_text'] ?? '');
            $secondaryCtaUrl     = sanitize_text($_POST['secondary_button_link'] ?? '');

            if (empty($headline)) {
                $error = 'Headline cannot be left empty.';
            } elseif (empty($primaryCta) || empty($primaryCtaUrl)) {
                $error = 'Primary CTA label and target URL are required.';
            } else {
                $updateData = [
                    'headline'              => $headline,
                    'subheadline'           => $subheadline,
                    'description'           => $description,
                    'primary_button_text'   => $primaryCta,
                    'primary_button_link'   => $primaryCtaUrl,
                    'secondary_button_text' => $secondaryCta,
                    'secondary_button_link' => $secondaryCtaUrl,
                    'updated_at'            => date('Y-m-d H:i:s')
                ];

                try {
                    $db->update('hero_content', $updateData, 'id = 1');
                    $hero = array_merge($hero, $updateData);
                    $success = 'Hero section successfully updated!';
                } catch (\Throwable $e) {
                    $error = 'Database update failed: ' . $e->getMessage();
                }
            }
        }
    }

    // Support AJAX response for seamless UX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => empty($error),
            'message' => $error ?: $success,
            'data'    => $hero
        ]);
        exit;
    }
}

require_once dirname(__DIR__) . '/includes/admin_header.php';
?>

  <div class="page-header">
    <div>
      <h1 class="page-title">Hero Section Management</h1>
      <p class="page-subtitle">Configure homepage headline, supporting value proposition, and primary conversion call-to-actions.</p>
    </div>
    <div style="display: flex; gap: 10px;">
      <a href="<?= e(BASE_URL) ?>/#hero" target="_blank" class="btn-action">
        Preview on Live Site ↗
      </a>
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

  <div style="display: grid; grid-template-columns: 1fr 380px; gap: 24px; align-items: start;">
    <!-- Main Edit Form -->
    <div class="data-card" style="padding: 28px;">
      <form method="POST" action="hero.php" class="admin-form" id="heroForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" id="heroAction" value="save" />

        <div class="admin-form-group">
          <label for="headline" class="admin-form-label">
            Headline (Supports line breaks)
          </label>
          <textarea
            id="headline"
            name="headline"
            rows="3"
            class="admin-form-input"
            required
            style="font-family: 'Space Grotesk', sans-serif; font-size: 18px; font-weight: 700; line-height: 1.2;"
          ><?= e($hero['headline']) ?></textarea>
          <small style="color: var(--text-muted); font-size: 11px;">Shown as the primary hero statement on the homepage.</small>
        </div>

        <div class="admin-form-group">
          <label for="subheadline" class="admin-form-label">Subheadline / Supporting Copy</label>
          <input
            type="text"
            id="subheadline"
            name="subheadline"
            class="admin-form-input"
            value="<?= e($hero['subheadline']) ?>"
            placeholder="We turn ideas into websites, software and digital experiences that actually work."
          />
        </div>

        <div class="admin-form-group">
          <label for="description" class="admin-form-label">Extended Description</label>
          <textarea
            id="description"
            name="description"
            rows="4"
            class="admin-form-input"
          ><?= e($hero['description']) ?></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
          <div class="admin-form-group">
            <label for="primary_button_text" class="admin-form-label">Primary CTA Label</label>
            <input
              type="text"
              id="primary_button_text"
              name="primary_button_text"
              class="admin-form-input"
              value="<?= e($hero['primary_button_text']) ?>"
              required
            />
          </div>

          <div class="admin-form-group">
            <label for="primary_button_link" class="admin-form-label">Primary CTA Target URL</label>
            <input
              type="text"
              id="primary_button_link"
              name="primary_button_link"
              class="admin-form-input"
              value="<?= e($hero['primary_button_link']) ?>"
              required
            />
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
          <div class="admin-form-group">
            <label for="secondary_button_text" class="admin-form-label">Secondary CTA Label</label>
            <input
              type="text"
              id="secondary_button_text"
              name="secondary_button_text"
              class="admin-form-input"
              value="<?= e($hero['secondary_button_text']) ?>"
            />
          </div>

          <div class="admin-form-group">
            <label for="secondary_button_link" class="admin-form-label">Secondary CTA Target URL</label>
            <input
              type="text"
              id="secondary_button_link"
              name="secondary_button_link"
              class="admin-form-input"
              value="<?= e($hero['secondary_button_link']) ?>"
            />
          </div>
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-light);">
          <button
            type="button"
            class="btn-danger-admin"
            id="btnResetHero"
            data-confirm="Are you sure you want to reset the hero section back to initial default copy?"
            data-confirm-title="Reset Hero Defaults"
            data-confirm-btn="Yes, Reset"
          >
            Reset to Default Copy
          </button>

          <button type="submit" class="btn-primary-admin" id="btnSaveHero">
            Save Changes &rarr;
          </button>
        </div>
      </form>
    </div>

    <!-- Live Preview Sidebar Card -->
    <div class="data-card" style="padding: 24px; position: sticky; top: 90px; background: #0F1015; color: #FFFFFF; border-color: #222430;">
      <div style="font-family: 'DM Mono', monospace; font-size: 11px; text-transform: uppercase; color: var(--lime); margin-bottom: 12px; letter-spacing: 0.05em;">
        ✦ Real-Time Preview
      </div>

      <div style="font-size: 11px; color: #8F94A6; margin-bottom: 8px;">
        Badge: <span id="previewBadge"><?= e($hero['badge_text'] ?? 'Digital studio / 2026') ?></span>
      </div>

      <h2 id="previewHeadline" style="font-family: 'Space Grotesk', sans-serif; font-size: 22px; font-weight: 700; line-height: 1.15; color: #FFFFFF; margin-bottom: 12px; white-space: pre-line;">
        <?= e($hero['headline']) ?>
      </h2>

      <p id="previewSubheadline" style="font-size: 13px; color: #B3B7C6; line-height: 1.5; margin-bottom: 16px;">
        <?= e($hero['subheadline']) ?>
      </p>

      <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <span id="previewPrimaryCta" style="background: var(--lime); color: #111; font-weight: 700; font-size: 11px; padding: 8px 14px; border-radius: 6px;">
          <?= e($hero['primary_button_text']) ?>
        </span>
        <span id="previewSecondaryCta" style="background: rgba(255,255,255,0.1); color: #FFF; font-weight: 600; font-size: 11px; padding: 8px 14px; border-radius: 6px;">
          <?= e($hero['secondary_button_text']) ?>
        </span>
      </div>
    </div>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const headlineInput = document.getElementById('headline');
    const subheadlineInput = document.getElementById('subheadline');
    const primaryCtaInput = document.getElementById('primary_button_text');
    const secondaryCtaInput = document.getElementById('secondary_button_text');

    const previewHeadline = document.getElementById('previewHeadline');
    const previewSubheadline = document.getElementById('previewSubheadline');
    const previewPrimary = document.getElementById('previewPrimaryCta');
    const previewSecondary = document.getElementById('previewSecondaryCta');

    if (headlineInput && previewHeadline) {
      headlineInput.addEventListener('input', () => {
        previewHeadline.textContent = headlineInput.value || 'Headline';
      });
    }
    if (subheadlineInput && previewSubheadline) {
      subheadlineInput.addEventListener('input', () => {
        previewSubheadline.textContent = subheadlineInput.value || '';
      });
    }
    if (primaryCtaInput && previewPrimary) {
      primaryCtaInput.addEventListener('input', () => {
        previewPrimary.textContent = primaryCtaInput.value || 'CTA 1';
      });
    }
    if (secondaryCtaInput && previewSecondary) {
      secondaryCtaInput.addEventListener('input', () => {
        previewSecondary.textContent = secondaryCtaInput.value || 'CTA 2';
      });
    }

    // Reset button handler
    const btnReset = document.getElementById('btnResetHero');
    const form = document.getElementById('heroForm');
    const actionInput = document.getElementById('heroAction');

    if (btnReset) {
      btnReset.addEventListener('click', (e) => {
        e.preventDefault();
        window.AdminModal.confirm({
          title: "Reset Hero Copy",
          message: "Are you sure you want to reset the hero section back to default copy? All customized text will be replaced.",
          confirmText: "Yes, Reset",
          isDanger: true,
          onConfirm: () => {
            actionInput.value = 'reset';
            form.submit();
          }
        });
      });
    }
  });
  </script>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>
