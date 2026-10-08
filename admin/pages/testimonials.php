<?php
/**
 * Website Tailors Admin — Testimonials Management
 *
 * Full CRUD for client endorsements:
 * Add, Edit, Delete, Publish/Unpublish, with image upload and live preview.
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) { define('WebsiteTailors_INIT', true); }
require_once dirname(__DIR__) . '/includes/auth_guard.php';

$pageTitle = 'Testimonials Management';
$breadcrumb = 'Testimonials';
$db = Database::getInstance();

$error = null;
$success = null;

// Handle CRUD Operations
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } else {
        $action = sanitize_text($_POST['action'] ?? '');

        // 1. DELETE TESTIMONIAL
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $item = $db->fetch("SELECT image FROM testimonials WHERE id = :id", [':id' => $id]);
                    if ($item && !empty($item['image'])) {
                        delete_uploaded_file($item['image']);
                    }
                    $db->delete('testimonials', 'id = :id', [':id' => $id]);
                    $success = 'Testimonial removed successfully.';
                } catch (\Throwable $e) {
                    $error = 'Failed to delete testimonial: ' . $e->getMessage();
                }
            }
        }
        // 2. TOGGLE STATUS
        elseif ($action === 'toggle_publish') {
            $id = (int)($_POST['id'] ?? 0);
            $currentStatus = sanitize_text($_POST['current_status'] ?? 'published');
            $newStatus = ($currentStatus === 'published') ? 'draft' : 'published';

            if ($id > 0) {
                try {
                    $db->update('testimonials', ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $id]);
                    $success = 'Testimonial status updated to ' . ucfirst($newStatus) . '.';
                } catch (\Throwable $e) {
                    $error = 'Failed to toggle status: ' . $e->getMessage();
                }
            }
        }
        // 3. CREATE OR UPDATE TESTIMONIAL
        elseif ($action === 'save') {
            $id         = (int)($_POST['id'] ?? 0);
            $clientName = sanitize_text($_POST['client_name'] ?? '');
            $company    = sanitize_text($_POST['company'] ?? '');
            $position   = sanitize_text($_POST['position'] ?? '');
            $content    = sanitize_text($_POST['content'] ?? '');
            $rating     = max(1, min(5, (int)($_POST['rating'] ?? 5)));
            $status     = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';

            if (empty($clientName)) {
                $error = 'Client name is required.';
            } elseif (empty($company)) {
                $error = 'Client company name is required.';
            } elseif (empty($content)) {
                $error = 'Testimonial statement text is required.';
            } else {
                $imagePath = sanitize_text($_POST['existing_image'] ?? '');

                // Handle Image File Upload
                if (!empty($_FILES['client_image']) && $_FILES['client_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = handle_file_upload($_FILES['client_image'], 'testimonials');
                    if ($uploadResult['success']) {
                        if (!empty($imagePath)) {
                            delete_uploaded_file($imagePath);
                        }
                        $imagePath = $uploadResult['path'];
                    } else {
                        $error = 'Avatar upload failed: ' . $uploadResult['error'];
                    }
                }

                if (!$error) {
                    $testimonialData = [
                        'client_name' => $clientName,
                        'company'     => $company,
                        'position'    => $position,
                        'content'     => $content,
                        'rating'      => $rating,
                        'image'       => $imagePath,
                        'status'      => $status,
                        'updated_at'  => date('Y-m-d H:i:s')
                    ];

                    try {
                        if ($id > 0) {
                            $db->update('testimonials', $testimonialData, 'id = :id', [':id' => $id]);
                            $success = 'Testimonial for ' . $clientName . ' updated successfully.';
                        } else {
                            $testimonialData['created_at'] = date('Y-m-d H:i:s');
                            $testimonialData['display_order'] = 0;
                            $db->insert('testimonials', $testimonialData);
                            $success = 'New testimonial from ' . $clientName . ' added.';
                        }
                    } catch (\Throwable $e) {
                        $error = 'Database operation failed: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Fetch all testimonials
$testimonials = [];
try {
    $testimonials = $db->fetchAll("SELECT * FROM testimonials ORDER BY display_order ASC, id DESC");
} catch (\Throwable $e) {
    error_log("Testimonials fetch notice: " . $e->getMessage());
}

// Edit mode
$editingItem = null;
$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    foreach ($testimonials as $t) {
        if ((int)$t['id'] === $editId) {
            $editingItem = $t;
            break;
        }
    }
}

require_once dirname(__DIR__) . '/includes/admin_header.php';
?>

  <div class="page-header">
    <div>
      <h1 class="page-title">Testimonials Management</h1>
      <p class="page-subtitle">Curate client endorsements, customer quotes, and satisfaction ratings.</p>
    </div>
    <div>
      <?php if ($editingItem): ?>
        <a href="testimonials.php" class="btn-action">&larr; Back to Testimonials List</a>
      <?php else: ?>
        <a href="#testimonialFormCard" class="btn-primary-admin" id="btnOpenCreateModal">
          + Add New Testimonial
        </a>
      <?php endif; ?>
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

  <!-- CREATE / EDIT FORM SECTION -->
  <div class="data-card" style="padding: 28px; margin-bottom: 32px; <?= $editingItem ? '' : 'display: none;' ?>" id="testimonialFormCard">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
      <h2 style="font-size: 18px; font-weight: 700; margin: 0;">
        <?= $editingItem ? 'Edit Testimonial: ' . e($editingItem['client_name']) : 'Add Client Testimonial' ?>
      </h2>
      <?php if (!$editingItem): ?>
        <button type="button" class="btn-edit-admin" id="btnCloseFormCard">Cancel</button>
      <?php endif; ?>
    </div>

    <form method="POST" action="testimonials.php" enctype="multipart/form-data" class="admin-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save" />
      <input type="hidden" name="id" value="<?= (int)($editingItem['id'] ?? 0) ?>" />
      <input type="hidden" name="existing_image" value="<?= e($editingItem['image'] ?? '') ?>" />

      <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
        <div class="admin-form-group">
          <label for="client_name" class="admin-form-label">Client Full Name</label>
          <input
            type="text"
            id="client_name"
            name="client_name"
            class="admin-form-input"
            required
            placeholder="e.g. Rajesh Menon"
            value="<?= e($editingItem['client_name'] ?? '') ?>"
          />
        </div>

        <div class="admin-form-group">
          <label for="company" class="admin-form-label">Company / Organization</label>
          <input
            type="text"
            id="company"
            name="company"
            class="admin-form-input"
            required
            placeholder="e.g. Apex Logistics India"
            value="<?= e($editingItem['company'] ?? '') ?>"
          />
        </div>

        <div class="admin-form-group">
          <label for="position" class="admin-form-label">Role / Position</label>
          <input
            type="text"
            id="position"
            name="position"
            class="admin-form-input"
            placeholder="e.g. Chief Technology Officer"
            value="<?= e($editingItem['position'] ?? '') ?>"
          />
        </div>
      </div>

      <div class="admin-form-group">
        <label for="content" class="admin-form-label">Client Statement / Quote</label>
        <textarea
          id="content"
          name="content"
          rows="3"
          class="admin-form-input"
          required
          placeholder="Website Tailors transformed our dispatch platform into a blisteringly fast powerhouse..."
        ><?= e($editingItem['content'] ?? '') ?></textarea>
      </div>

      <div style="display: grid; grid-template-columns: 180px 1fr; gap: 24px; align-items: center;">
        <div class="admin-form-group">
          <label for="rating" class="admin-form-label">Star Rating (1-5)</label>
          <select id="rating" name="rating" class="admin-form-input">
            <option value="5" <?= (int)($editingItem['rating'] ?? 5) === 5 ? 'selected' : '' ?>>★★★★★ (5 Stars)</option>
            <option value="4" <?= (int)($editingItem['rating'] ?? 5) === 4 ? 'selected' : '' ?>>★★★★☆ (4 Stars)</option>
            <option value="3" <?= (int)($editingItem['rating'] ?? 5) === 3 ? 'selected' : '' ?>>★★★☆☆ (3 Stars)</option>
          </select>
        </div>

        <!-- AVATAR UPLOAD WITH LIVE PREVIEW -->
        <div class="admin-form-group">
          <label class="admin-form-label">Client Avatar Image</label>
          <div style="display: flex; align-items: center; gap: 16px;">
            <div
              class="image-preview-box <?= !empty($editingItem['image']) ? 'has-image' : '' ?>"
              id="testimonialImgPreview"
              style="width: 56px; height: 56px; border-radius: 50%; min-width: 56px;"
            >
              <?php if (!empty($editingItem['image'])): ?>
                <img src="<?= e(BASE_URL . $editingItem['image']) ?>" alt="Avatar" style="border-radius: 50%;" />
              <?php else: ?>
                <div class="image-preview-placeholder" style="font-size: 9px;">Avatar</div>
              <?php endif; ?>
            </div>

            <div style="flex: 1;">
              <input
                type="file"
                id="client_image"
                name="client_image"
                accept=".jpg,.jpeg,.png,.webp"
                data-preview="#testimonialImgPreview"
                class="admin-form-input"
                style="padding: 6px;"
              />
              <small style="color: var(--text-muted); font-size: 11px;">Square portrait recommended (Max 8MB).</small>
            </div>
          </div>
        </div>
      </div>

      <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-light);">
        <div style="display: flex; align-items: center; gap: 12px;">
          <label class="switch-toggle">
            <input type="checkbox" name="status" value="published" <?= ($editingItem['status'] ?? 'published') === 'published' ? 'checked' : '' ?> />
            <span class="switch-slider"></span>
          </label>
          <span style="font-size: 13px; font-weight: 500;">Published on Public Website</span>
        </div>

        <div style="display: flex; gap: 10px;">
          <?php if ($editingItem): ?>
            <a href="testimonials.php" class="btn-edit-admin">Cancel</a>
          <?php endif; ?>
          <button type="submit" class="btn-primary-admin">
            <?= $editingItem ? 'Update Testimonial &rarr;' : 'Save Testimonial &rarr;' ?>
          </button>
        </div>
      </div>
    </form>
  </div>

  <!-- TESTIMONIALS LIST -->
  <div class="data-card">
    <div class="data-card-header">
      <h2 class="data-card-title">Client Testimonials (<?= count($testimonials) ?>)</h2>
    </div>

    <div class="table-responsive">
      <?php if (empty($testimonials)): ?>
        <div class="empty-state">No testimonials recorded yet. Click "+ Add New Testimonial" above.</div>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th style="width: 60px;">Avatar</th>
              <th>Client</th>
              <th>Company</th>
              <th>Statement</th>
              <th>Rating</th>
              <th>Status</th>
              <th style="text-align: right; width: 180px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($testimonials as $t): ?>
              <tr>
                <td>
                  <?php if (!empty($t['image'])): ?>
                    <img
                      src="<?= e(BASE_URL . $t['image']) ?>"
                      alt="<?= e($t['client_name']) ?>"
                      style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;"
                    />
                  <?php else: ?>
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--main-bg); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; color: var(--text-muted);">
                      <?= strtoupper(substr($t['client_name'], 0, 1)) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <strong><?= e($t['client_name']) ?></strong>
                  <?php if (!empty($t['position'])): ?>
                    <div style="font-size: 11px; color: var(--text-muted);"><?= e($t['position']) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= e($t['company']) ?></td>
                <td style="font-size: 13px; color: var(--text-body); max-width: 400px;">
                  "<?= e($t['content']) ?>"
                </td>
                <td>
                  <span style="color: #f59e0b; letter-spacing: 2px;">
                    <?= str_repeat('★', (int)$t['rating']) ?>
                  </span>
                </td>
                <td>
                  <form method="POST" action="testimonials.php" style="display: inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_publish" />
                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>" />
                    <input type="hidden" name="current_status" value="<?= e($t['status']) ?>" />
                    <button
                      type="submit"
                      class="status-pill <?= $t['status'] === 'published' ? 'status-closed' : 'status-contacted' ?>"
                      title="Click to toggle publish status"
                      style="cursor: pointer;"
                    >
                      <?= $t['status'] === 'published' ? '● Published' : '○ Draft' ?>
                    </button>
                  </form>
                </td>
                <td style="text-align: right;">
                  <div style="display: inline-flex; gap: 8px;">
                    <a href="testimonials.php?edit=<?= (int)$t['id'] ?>" class="btn-edit-admin">
                      Edit
                    </a>
                    <form method="POST" action="testimonials.php" style="display: inline;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete" />
                      <input type="hidden" name="id" value="<?= (int)$t['id'] ?>" />
                      <button
                        type="submit"
                        class="btn-danger-admin"
                        data-confirm="Are you sure you want to permanently delete the testimonial from <?= e($t['client_name']) ?>?"
                        data-confirm-title="Delete Testimonial"
                        data-confirm-btn="Yes, Delete"
                      >
                        Delete
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const btnOpen = document.getElementById('btnOpenCreateModal');
    const formCard = document.getElementById('testimonialFormCard');
    const btnClose = document.getElementById('btnCloseFormCard');

    if (btnOpen && formCard) {
      btnOpen.addEventListener('click', (e) => {
        e.preventDefault();
        formCard.style.display = 'block';
        formCard.scrollIntoView({ behavior: 'smooth' });
      });
    }

    if (btnClose && formCard) {
      btnClose.addEventListener('click', () => {
        formCard.style.display = 'none';
      });
    }
  });
  </script>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>
