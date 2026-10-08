<?php
/**
 * Website Tailors Admin — Services Management
 *
 * Full CRUD for services: Create, Edit, Delete, Activate/Deactivate, and Order.
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) { define('WebsiteTailors_INIT', true); }
require_once dirname(__DIR__) . '/includes/auth_guard.php';

$pageTitle = 'Services Management';
$breadcrumb = 'Services';
$db = Database::getInstance();

$error = null;
$success = null;

// Handle CRUD Actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } else {
        $action = sanitize_text($_POST['action'] ?? '');

        // 1. DELETE SERVICE
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $db->delete('services', 'id = :id', [':id' => $id]);
                    $success = 'Service successfully deleted.';
                } catch (\Throwable $e) {
                    $error = 'Failed to delete service: ' . $e->getMessage();
                }
            }
        }
        // 2. TOGGLE STATUS (Activate/Deactivate)
        elseif ($action === 'toggle_status') {
            $id = (int)($_POST['id'] ?? 0);
            $currentStatus = sanitize_text($_POST['current_status'] ?? 'published');
            $newStatus = ($currentStatus === 'published') ? 'draft' : 'published';

            if ($id > 0) {
                try {
                    $db->update('services', ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $id]);
                    $success = 'Service status updated to ' . ucfirst($newStatus) . '.';
                } catch (\Throwable $e) {
                    $error = 'Failed to toggle status: ' . $e->getMessage();
                }
            }
        }
        // 3. CREATE OR UPDATE SERVICE
        elseif ($action === 'save') {
            $id               = (int)($_POST['id'] ?? 0);
            $title            = sanitize_text($_POST['title'] ?? '');
            $shortDescription = sanitize_text($_POST['short_description'] ?? '');
            $longDescription  = sanitize_text($_POST['long_description'] ?? '');
            $icon             = sanitize_text($_POST['icon'] ?? 'code');
            $displayOrder     = (int)($_POST['display_order'] ?? 0);
            $status           = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';

            if (empty($title)) {
                $error = 'Service title is required.';
            } elseif (empty($shortDescription)) {
                $error = 'Short description is required.';
            } else {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
                if (empty($slug)) { $slug = 'service-' . time(); }

                // Ensure unique slug
                $origSlug = $slug;
                $counter = 1;
                while ($db->fetch("SELECT id FROM services WHERE slug = :s AND id != :id", [':s' => $slug, ':id' => $id])) {
                    $slug = $origSlug . '-' . $counter;
                    $counter++;
                }

                $serviceData = [
                    'title'             => $title,
                    'slug'              => $slug,
                    'short_description' => $shortDescription,
                    'long_description'  => $longDescription,
                    'icon'              => $icon,
                    'display_order'     => $displayOrder,
                    'status'            => $status,
                    'updated_at'        => date('Y-m-d H:i:s')
                ];

                try {
                    if ($id > 0) {
                        // Update
                        $db->update('services', $serviceData, 'id = :id', [':id' => $id]);
                        $success = 'Service "' . $title . '" updated successfully.';
                    } else {
                        // Create
                        $serviceData['created_at'] = date('Y-m-d H:i:s');
                        $db->insert('services', $serviceData);
                        $success = 'New service "' . $title . '" created successfully.';
                    }
                } catch (\Throwable $e) {
                    $error = 'Database operation failed: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch all services ordered by display_order
$services = [];
try {
    $services = $db->fetchAll("SELECT * FROM services ORDER BY display_order ASC, id ASC");
} catch (\Throwable $e) {
    error_log("Services fetch notice: " . $e->getMessage());
}

// Check if editing a specific service
$editingService = null;
$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    foreach ($services as $s) {
        if ((int)$s['id'] === $editId) {
            $editingService = $s;
            break;
        }
    }
}

require_once dirname(__DIR__) . '/includes/admin_header.php';
?>

  <div class="page-header">
    <div>
      <h1 class="page-title">Services Management</h1>
      <p class="page-subtitle">Configure studio capabilities, features, icon representations, and display priority.</p>
    </div>
    <div>
      <?php if ($editingService): ?>
        <a href="services.php" class="btn-action">&larr; Back to Services List</a>
      <?php else: ?>
        <a href="#serviceModal" class="btn-primary-admin" id="btnOpenCreateModal">
          + Add New Service
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
  <div class="data-card" style="padding: 28px; margin-bottom: 32px; <?= $editingService ? '' : 'display: none;' ?>" id="serviceFormCard">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
      <h2 style="font-size: 18px; font-weight: 700; margin: 0;">
        <?= $editingService ? 'Edit Service: ' . e($editingService['title']) : 'Create New Service' ?>
      </h2>
      <?php if (!$editingService): ?>
        <button type="button" class="btn-edit-admin" id="btnCloseFormCard">Cancel</button>
      <?php endif; ?>
    </div>

    <form method="POST" action="services.php" class="admin-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save" />
      <input type="hidden" name="id" value="<?= (int)($editingService['id'] ?? 0) ?>" />

      <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 16px;">
        <div class="admin-form-group">
          <label for="title" class="admin-form-label">Service Title</label>
          <input
            type="text"
            id="title"
            name="title"
            class="admin-form-input"
            required
            placeholder="e.g. Websites"
            value="<?= e($editingService['title'] ?? '') ?>"
          />
        </div>

        <div class="admin-form-group">
          <label for="icon" class="admin-form-label">Icon Type</label>
          <select id="icon" name="icon" class="admin-form-input">
            <option value="code" <?= ($editingService['icon'] ?? '') === 'code' ? 'selected' : '' ?>>Code & Websites</option>
            <option value="cpu" <?= ($editingService['icon'] ?? '') === 'cpu' ? 'selected' : '' ?>>CPU & Software</option>
            <option value="zap" <?= ($editingService['icon'] ?? '') === 'zap' ? 'selected' : '' ?>>Zap & Automation</option>
            <option value="globe" <?= ($editingService['icon'] ?? '') === 'globe' ? 'selected' : '' ?>>Globe & Digital</option>
          </select>
        </div>

        <div class="admin-form-group">
          <label for="display_order" class="admin-form-label">Display Order</label>
          <input
            type="number"
            id="display_order"
            name="display_order"
            class="admin-form-input"
            min="0"
            value="<?= (int)($editingService['display_order'] ?? (count($services) + 1)) ?>"
          />
        </div>
      </div>

      <div class="admin-form-group">
        <label for="short_description" class="admin-form-label">Short Description (Card Summary)</label>
        <textarea
          id="short_description"
          name="short_description"
          rows="2"
          class="admin-form-input"
          required
          placeholder="High-performing websites and landing pages designed to make your business stand out."
        ><?= e($editingService['short_description'] ?? '') ?></textarea>
      </div>

      <div class="admin-form-group">
        <label for="long_description" class="admin-form-label">Long Description (Detail Showcase)</label>
        <textarea
          id="long_description"
          name="long_description"
          rows="4"
          class="admin-form-input"
          placeholder="We construct scalable web platforms with clean, semantic markup..."
        ><?= e($editingService['long_description'] ?? '') ?></textarea>
      </div>

      <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-light);">
        <div style="display: flex; align-items: center; gap: 12px;">
          <label class="switch-toggle">
            <input type="checkbox" name="status" value="published" <?= ($editingService['status'] ?? 'published') === 'published' ? 'checked' : '' ?> />
            <span class="switch-slider"></span>
          </label>
          <span style="font-size: 13px; font-weight: 500;">Active & Visible on Public Site</span>
        </div>

        <div style="display: flex; gap: 10px;">
          <?php if ($editingService): ?>
            <a href="services.php" class="btn-edit-admin">Cancel Edit</a>
          <?php endif; ?>
          <button type="submit" class="btn-primary-admin">
            <?= $editingService ? 'Update Service &rarr;' : 'Create Service &rarr;' ?>
          </button>
        </div>
      </div>
    </form>
  </div>

  <!-- SERVICES TABLE -->
  <div class="data-card">
    <div class="data-card-header">
      <h2 class="data-card-title">All Studio Services (<?= count($services) ?>)</h2>
      <span style="font-size: 12px; color: var(--text-muted); font-family: 'DM Mono', monospace;">
        Ordered by display sequence
      </span>
    </div>

    <div class="table-responsive">
      <?php if (empty($services)): ?>
        <div class="empty-state">No services registered yet. Click "+ Add New Service" above.</div>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th style="width: 70px;">Order</th>
              <th>Service Details</th>
              <th>Icon</th>
              <th>Status</th>
              <th style="text-align: right; width: 180px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($services as $s): ?>
              <tr>
                <td style="font-family: 'DM Mono', monospace; font-weight: 600;">
                  #<?= (int)$s['display_order'] ?>
                </td>
                <td>
                  <strong style="font-size: 15px; color: var(--text-dark);"><?= e($s['title']) ?></strong>
                  <div style="font-size: 12px; color: var(--text-muted); max-width: 520px; margin-top: 4px; line-height: 1.4;">
                    <?= e($s['short_description']) ?>
                  </div>
                </td>
                <td>
                  <span style="font-family: 'DM Mono', monospace; font-size: 11px; background: var(--main-bg); padding: 4px 8px; border-radius: 4px;">
                    <?= e($s['icon']) ?>
                  </span>
                </td>
                <td>
                  <form method="POST" action="services.php" style="display: inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_status" />
                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>" />
                    <input type="hidden" name="current_status" value="<?= e($s['status']) ?>" />
                    <button
                      type="submit"
                      class="status-pill <?= $s['status'] === 'published' ? 'status-closed' : 'status-contacted' ?>"
                      title="Click to toggle status"
                      style="cursor: pointer;"
                    >
                      <?= $s['status'] === 'published' ? '● Active' : '○ Inactive' ?>
                    </button>
                  </form>
                </td>
                <td style="text-align: right;">
                  <div style="display: inline-flex; gap: 8px;">
                    <a href="services.php?edit=<?= (int)$s['id'] ?>" class="btn-edit-admin">
                      Edit
                    </a>
                    <form method="POST" action="services.php" style="display: inline;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete" />
                      <input type="hidden" name="id" value="<?= (int)$s['id'] ?>" />
                      <button
                        type="submit"
                        class="btn-danger-admin"
                        data-confirm="Are you sure you want to permanently delete the '<?= e($s['title']) ?>' service? This will remove it from the public homepage."
                        data-confirm-title="Delete Service"
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
    const formCard = document.getElementById('serviceFormCard');
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
