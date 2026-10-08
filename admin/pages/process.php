<?php
/**
 * Website Tailors Admin — Process Steps Management
 *
 * Full CRUD for the 4-step workflow process:
 * Add, Edit, Delete, Reorder, Activate/Deactivate.
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) { define('WebsiteTailors_INIT', true); }
require_once dirname(__DIR__) . '/includes/auth_guard.php';

$pageTitle = 'Process Steps Management';
$breadcrumb = 'Process';
$db = Database::getInstance();

$error = null;
$success = null;

// Handle CRUD operations
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } else {
        $action = sanitize_text($_POST['action'] ?? '');

        // 1. DELETE STEP
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $db->delete('process_steps', 'id = :id', [':id' => $id]);
                    $success = 'Process step deleted.';
                } catch (\Throwable $e) {
                    $error = 'Failed to delete process step: ' . $e->getMessage();
                }
            }
        }
        // 2. TOGGLE STATUS
        elseif ($action === 'toggle_status') {
            $id = (int)($_POST['id'] ?? 0);
            $currentStatus = sanitize_text($_POST['current_status'] ?? 'published');
            $newStatus = ($currentStatus === 'published') ? 'draft' : 'published';

            if ($id > 0) {
                try {
                    $db->update('process_steps', ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $id]);
                    $success = 'Step visibility updated to ' . ucfirst($newStatus) . '.';
                } catch (\Throwable $e) {
                    $error = 'Failed to toggle status: ' . $e->getMessage();
                }
            }
        }
        // 3. CREATE OR UPDATE STEP
        elseif ($action === 'save') {
            $id           = (int)($_POST['id'] ?? 0);
            $stepNumber   = sanitize_text($_POST['step_number'] ?? '01');
            $title        = sanitize_text($_POST['title'] ?? '');
            $description  = sanitize_text($_POST['description'] ?? '');
            $displayOrder = (int)($_POST['display_order'] ?? 0);
            $status       = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';

            if (empty($stepNumber)) {
                $error = 'Step number is required (e.g. 01, 02).';
            } elseif (empty($title)) {
                $error = 'Step title is required.';
            } elseif (empty($description)) {
                $error = 'Step description is required.';
            } else {
                $stepData = [
                    'step_number'   => $stepNumber,
                    'title'         => $title,
                    'description'   => $description,
                    'display_order' => $displayOrder,
                    'status'        => $status,
                    'updated_at'    => date('Y-m-d H:i:s')
                ];

                try {
                    if ($id > 0) {
                        $db->update('process_steps', $stepData, 'id = :id', [':id' => $id]);
                        $success = 'Process step "' . $title . '" updated successfully.';
                    } else {
                        $stepData['created_at'] = date('Y-m-d H:i:s');
                        $db->insert('process_steps', $stepData);
                        $success = 'New process step "' . $title . '" created.';
                    }
                } catch (\Throwable $e) {
                    $error = 'Database operation failed: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch all steps ordered by display order
$steps = [];
try {
    $steps = $db->fetchAll("SELECT * FROM process_steps ORDER BY display_order ASC, id ASC");
} catch (\Throwable $e) {
    error_log("Process fetch notice: " . $e->getMessage());
}

// Edit mode check
$editingStep = null;
$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    foreach ($steps as $st) {
        if ((int)$st['id'] === $editId) {
            $editingStep = $st;
            break;
        }
    }
}

require_once dirname(__DIR__) . '/includes/admin_header.php';
?>

  <div class="page-header">
    <div>
      <h1 class="page-title">Process Management</h1>
      <p class="page-subtitle">Define the client workflow stages displayed on the public process timeline.</p>
    </div>
    <div>
      <?php if ($editingStep): ?>
        <a href="process.php" class="btn-action">&larr; Back to Steps List</a>
      <?php else: ?>
        <a href="#stepFormCard" class="btn-primary-admin" id="btnOpenCreateModal">
          + Add New Step
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

  <!-- CREATE / EDIT STEP FORM -->
  <div class="data-card" style="padding: 28px; margin-bottom: 32px; <?= $editingStep ? '' : 'display: none;' ?>" id="stepFormCard">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
      <h2 style="font-size: 18px; font-weight: 700; margin: 0;">
        <?= $editingStep ? 'Edit Step: ' . e($editingStep['step_number']) . ' — ' . e($editingStep['title']) : 'Add Process Step' ?>
      </h2>
      <?php if (!$editingStep): ?>
        <button type="button" class="btn-edit-admin" id="btnCloseFormCard">Cancel</button>
      <?php endif; ?>
    </div>

    <form method="POST" action="process.php" class="admin-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save" />
      <input type="hidden" name="id" value="<?= (int)($editingStep['id'] ?? 0) ?>" />

      <div style="display: grid; grid-template-columns: 120px 2fr 1fr; gap: 16px;">
        <div class="admin-form-group">
          <label for="step_number" class="admin-form-label">Step #</label>
          <input
            type="text"
            id="step_number"
            name="step_number"
            class="admin-form-input"
            required
            placeholder="01"
            value="<?= e($editingStep['step_number'] ?? sprintf('%02d', count($steps) + 1)) ?>"
            style="font-family: 'DM Mono', monospace; font-weight: 700; text-align: center;"
          />
        </div>

        <div class="admin-form-group">
          <label for="title" class="admin-form-label">Step Headline</label>
          <input
            type="text"
            id="title"
            name="title"
            class="admin-form-input"
            required
            placeholder="e.g. Tell us."
            value="<?= e($editingStep['title'] ?? '') ?>"
          />
        </div>

        <div class="admin-form-group">
          <label for="display_order" class="admin-form-label">Display Order</label>
          <input
            type="number"
            id="display_order"
            name="display_order"
            class="admin-form-input"
            min="0"
            value="<?= (int)($editingStep['display_order'] ?? (count($steps) + 1)) ?>"
          />
        </div>
      </div>

      <div class="admin-form-group">
        <label for="description" class="admin-form-label">Description Copy</label>
        <textarea
          id="description"
          name="description"
          rows="3"
          class="admin-form-input"
          required
          placeholder="Tell us what you're trying to build, fix or improve."
        ><?= e($editingStep['description'] ?? '') ?></textarea>
      </div>

      <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-light);">
        <div style="display: flex; align-items: center; gap: 12px;">
          <label class="switch-toggle">
            <input type="checkbox" name="status" value="published" <?= ($editingStep['status'] ?? 'published') === 'published' ? 'checked' : '' ?> />
            <span class="switch-slider"></span>
          </label>
          <span style="font-size: 13px; font-weight: 500;">Visible on Public Timeline</span>
        </div>

        <div style="display: flex; gap: 10px;">
          <?php if ($editingStep): ?>
            <a href="process.php" class="btn-edit-admin">Cancel</a>
          <?php endif; ?>
          <button type="submit" class="btn-primary-admin">
            <?= $editingStep ? 'Update Step &rarr;' : 'Save Step &rarr;' ?>
          </button>
        </div>
      </div>
    </form>
  </div>

  <!-- PROCESS STEPS TABLE -->
  <div class="data-card">
    <div class="data-card-header">
      <h2 class="data-card-title">Workflow Steps (<?= count($steps) ?>)</h2>
      <span style="font-size: 12px; color: var(--text-muted); font-family: 'DM Mono', monospace;">
        Ordered by sequence number
      </span>
    </div>

    <div class="table-responsive">
      <?php if (empty($steps)): ?>
        <div class="empty-state">No process steps found. Click "+ Add New Step" above.</div>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th style="width: 80px;">Step #</th>
              <th>Title</th>
              <th>Description</th>
              <th>Status</th>
              <th style="text-align: right; width: 180px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($steps as $st): ?>
              <tr>
                <td>
                  <span style="font-family: 'DM Mono', monospace; font-size: 14px; font-weight: 700; background: var(--main-bg); padding: 4px 10px; border-radius: 4px;">
                    <?= e($st['step_number']) ?>
                  </span>
                </td>
                <td>
                  <strong style="font-size: 15px; color: var(--text-dark);"><?= e($st['title']) ?></strong>
                </td>
                <td style="color: var(--text-body); font-size: 13px; max-width: 480px;">
                  <?= e($st['description']) ?>
                </td>
                <td>
                  <form method="POST" action="process.php" style="display: inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_status" />
                    <input type="hidden" name="id" value="<?= (int)$st['id'] ?>" />
                    <input type="hidden" name="current_status" value="<?= e($st['status']) ?>" />
                    <button
                      type="submit"
                      class="status-pill <?= $st['status'] === 'published' ? 'status-closed' : 'status-contacted' ?>"
                      title="Click to toggle status"
                      style="cursor: pointer;"
                    >
                      <?= $st['status'] === 'published' ? '● Active' : '○ Draft' ?>
                    </button>
                  </form>
                </td>
                <td style="text-align: right;">
                  <div style="display: inline-flex; gap: 8px;">
                    <a href="process.php?edit=<?= (int)$st['id'] ?>" class="btn-edit-admin">
                      Edit
                    </a>
                    <form method="POST" action="process.php" style="display: inline;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete" />
                      <input type="hidden" name="id" value="<?= (int)$st['id'] ?>" />
                      <button
                        type="submit"
                        class="btn-danger-admin"
                        data-confirm="Are you sure you want to delete step '<?= e($st['step_number']) ?> — <?= e($st['title']) ?>'?"
                        data-confirm-title="Delete Process Step"
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
    const formCard = document.getElementById('stepFormCard');
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
