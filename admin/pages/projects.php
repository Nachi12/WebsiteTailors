<?php
/**
 * Website Tailors Admin — Projects Portfolio Management
 *
 * Full CRUD for projects with secure image upload handling, live preview,
 * category assignment, publishing toggle, and display reordering.
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) { define('WebsiteTailors_INIT', true); }
require_once dirname(__DIR__) . '/includes/auth_guard.php';

$pageTitle = 'Project Management';
$breadcrumb = 'Projects';
$db = Database::getInstance();

$error = null;
$success = null;

// Handle CRUD Operations
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } else {
        $action = sanitize_text($_POST['action'] ?? '');

        // 1. DELETE PROJECT
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $proj = $db->fetch("SELECT image FROM projects WHERE id = :id", [':id' => $id]);
                    if ($proj && !empty($proj['image'])) {
                        delete_uploaded_file($proj['image']);
                    }
                    $db->delete('projects', 'id = :id', [':id' => $id]);
                    $success = 'Project successfully deleted.';
                } catch (\Throwable $e) {
                    $error = 'Failed to delete project: ' . $e->getMessage();
                }
            }
        }
        // 2. TOGGLE PUBLISH / UNPUBLISH
        elseif ($action === 'toggle_publish') {
            $id = (int)($_POST['id'] ?? 0);
            $currentStatus = sanitize_text($_POST['current_status'] ?? 'published');
            $newStatus = ($currentStatus === 'published') ? 'draft' : 'published';

            if ($id > 0) {
                try {
                    $db->update('projects', ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $id]);
                    $success = 'Project visibility updated to ' . ucfirst($newStatus) . '.';
                } catch (\Throwable $e) {
                    $error = 'Failed to toggle status: ' . $e->getMessage();
                }
            }
        }
        // 3. CREATE OR UPDATE PROJECT
        elseif ($action === 'save') {
            $id           = (int)($_POST['id'] ?? 0);
            $title        = sanitize_text($_POST['title'] ?? '');
            $category     = sanitize_text($_POST['category'] ?? 'Website');
            $clientName   = sanitize_text($_POST['client_name'] ?? '');
            $description  = sanitize_text($_POST['description'] ?? '');
            $projectUrl   = sanitize_text($_POST['project_url'] ?? '');
            $tags         = sanitize_text($_POST['tags'] ?? '');
            $displayOrder = (int)($_POST['display_order'] ?? 0);
            $status       = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';

            if (empty($title)) {
                $error = 'Project title is required.';
            } elseif (empty($description)) {
                $error = 'Project description is required.';
            } else {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
                if (empty($slug)) { $slug = 'project-' . time(); }

                // Ensure unique slug
                $origSlug = $slug;
                $counter = 1;
                while ($db->fetch("SELECT id FROM projects WHERE slug = :s AND id != :id", [':s' => $slug, ':id' => $id])) {
                    $slug = $origSlug . '-' . $counter;
                    $counter++;
                }

                // Existing image reference
                $imagePath = sanitize_text($_POST['existing_image'] ?? '');

                // Handle Image File Upload if supplied
                if (!empty($_FILES['project_image']) && $_FILES['project_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = handle_file_upload($_FILES['project_image'], 'projects');
                    if ($uploadResult['success']) {
                        // Delete previous image if exists
                        if (!empty($imagePath)) {
                            delete_uploaded_file($imagePath);
                        }
                        $imagePath = $uploadResult['path'];
                    } else {
                        $error = 'Image upload error: ' . $uploadResult['error'];
                    }
                }

                if (!$error) {
                    $projectData = [
                        'title'         => $title,
                        'slug'          => $slug,
                        'category'      => $category,
                        'client_name'   => $clientName,
                        'description'   => $description,
                        'image'         => $imagePath,
                        'project_url'   => $projectUrl,
                        'tags'          => $tags,
                        'display_order' => $displayOrder,
                        'status'        => $status,
                        'updated_at'    => date('Y-m-d H:i:s')
                    ];

                    try {
                        if ($id > 0) {
                            $db->update('projects', $projectData, 'id = :id', [':id' => $id]);
                            $success = 'Project "' . $title . '" successfully updated.';
                        } else {
                            $projectData['created_at'] = date('Y-m-d H:i:s');
                            $projectData['is_featured'] = 1;
                            $db->insert('projects', $projectData);
                            $success = 'New project "' . $title . '" published.';
                        }
                    } catch (\Throwable $e) {
                        $error = 'Database operation failed: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Fetch all projects
$projects = [];
try {
    $projects = $db->fetchAll("SELECT * FROM projects ORDER BY display_order ASC, id DESC");
} catch (\Throwable $e) {
    error_log("Projects query notice: " . $e->getMessage());
}

// Check edit mode
$editingProject = null;
$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    foreach ($projects as $p) {
        if ((int)$p['id'] === $editId) {
            $editingProject = $p;
            break;
        }
    }
}

require_once dirname(__DIR__) . '/includes/admin_header.php';
?>

  <div class="page-header">
    <div>
      <h1 class="page-title">Project Portfolio Management</h1>
      <p class="page-subtitle">Showcase recent client work, case studies, technologies used, and direct live links.</p>
    </div>
    <div>
      <?php if ($editingProject): ?>
        <a href="projects.php" class="btn-action">&larr; Back to Projects List</a>
      <?php else: ?>
        <a href="#projectFormCard" class="btn-primary-admin" id="btnOpenCreateModal">
          + Add New Project
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
  <div class="data-card" style="padding: 28px; margin-bottom: 32px; <?= $editingProject ? '' : 'display: none;' ?>" id="projectFormCard">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
      <h2 style="font-size: 18px; font-weight: 700; margin: 0;">
        <?= $editingProject ? 'Edit Project: ' . e($editingProject['title']) : 'Add New Portfolio Project' ?>
      </h2>
      <?php if (!$editingProject): ?>
        <button type="button" class="btn-edit-admin" id="btnCloseFormCard">Cancel</button>
      <?php endif; ?>
    </div>

    <form method="POST" action="projects.php" enctype="multipart/form-data" class="admin-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save" />
      <input type="hidden" name="id" value="<?= (int)($editingProject['id'] ?? 0) ?>" />
      <input type="hidden" name="existing_image" value="<?= e($editingProject['image'] ?? '') ?>" />

      <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 16px;">
        <div class="admin-form-group">
          <label for="title" class="admin-form-label">Project Title</label>
          <input
            type="text"
            id="title"
            name="title"
            class="admin-form-input"
            required
            placeholder="e.g. Apex Logistics Portal"
            value="<?= e($editingProject['title'] ?? '') ?>"
          />
        </div>

        <div class="admin-form-group">
          <label for="category" class="admin-form-label">Category</label>
          <select id="category" name="category" class="admin-form-input">
            <option value="Website" <?= ($editingProject['category'] ?? '') === 'Website' ? 'selected' : '' ?>>Website</option>
            <option value="Software" <?= ($editingProject['category'] ?? '') === 'Software' ? 'selected' : '' ?>>Software</option>
            <option value="AI + Automation" <?= ($editingProject['category'] ?? '') === 'AI + Automation' ? 'selected' : '' ?>>AI + Automation</option>
            <option value="Digital System" <?= ($editingProject['category'] ?? '') === 'Digital System' ? 'selected' : '' ?>>Digital System</option>
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
            value="<?= (int)($editingProject['display_order'] ?? (count($projects) + 1)) ?>"
          />
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <div class="admin-form-group">
          <label for="client_name" class="admin-form-label">Client / Brand Name</label>
          <input
            type="text"
            id="client_name"
            name="client_name"
            class="admin-form-input"
            placeholder="e.g. Apex Global Freight"
            value="<?= e($editingProject['client_name'] ?? '') ?>"
          />
        </div>

        <div class="admin-form-group">
          <label for="project_url" class="admin-form-label">Live Project URL</label>
          <input
            type="url"
            id="project_url"
            name="project_url"
            class="admin-form-input"
            placeholder="https://example.com"
            value="<?= e($editingProject['project_url'] ?? '') ?>"
          />
        </div>
      </div>

      <div class="admin-form-group">
        <label for="description" class="admin-form-label">Project Description</label>
        <textarea
          id="description"
          name="description"
          rows="3"
          class="admin-form-input"
          required
          placeholder="Brief summary of the challenge, engineering approach, and outcome."
        ><?= e($editingProject['description'] ?? '') ?></textarea>
      </div>

      <div class="admin-form-group">
        <label for="tags" class="admin-form-label">Tech Stack Tags (Comma separated)</label>
        <input
          type="text"
          id="tags"
          name="tags"
          class="admin-form-input"
          placeholder="PHP 8, MySQL, Custom Dashboard, Vanilla JS"
          value="<?= e($editingProject['tags'] ?? '') ?>"
        />
      </div>

      <!-- IMAGE UPLOAD WITH INSTANT VANILLA JS PREVIEW -->
      <div class="admin-form-group">
        <label class="admin-form-label">Project Showcase Image</label>
        <div style="display: flex; gap: 24px; align-items: center; flex-wrap: wrap;">
          <div class="image-preview-box <?= !empty($editingProject['image']) ? 'has-image' : '' ?>" id="projectImgPreview">
            <?php if (!empty($editingProject['image'])): ?>
              <img src="<?= e(BASE_URL . $editingProject['image']) ?>" alt="Preview" />
            <?php else: ?>
              <div class="image-preview-placeholder">
                <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <div>Select image to preview</div>
              </div>
            <?php endif; ?>
          </div>

          <div style="flex: 1; min-width: 260px;">
            <input
              type="file"
              id="project_image"
              name="project_image"
              accept=".jpg,.jpeg,.png,.webp"
              data-preview="#projectImgPreview"
              class="admin-form-input"
              style="padding: 8px;"
            />
            <small style="display: block; margin-top: 6px; color: var(--text-muted); font-size: 11px;">
              Accepted formats: JPG, JPEG, PNG, WebP (Max: 8MB). Filename is randomized and stored securely in <code>/uploads/projects/</code>.
            </small>
          </div>
        </div>
      </div>

      <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-light);">
        <div style="display: flex; align-items: center; gap: 12px;">
          <label class="switch-toggle">
            <input type="checkbox" name="status" value="published" <?= ($editingProject['status'] ?? 'published') === 'published' ? 'checked' : '' ?> />
            <span class="switch-slider"></span>
          </label>
          <span style="font-size: 13px; font-weight: 500;">Published on Public Portfolio</span>
        </div>

        <div style="display: flex; gap: 10px;">
          <?php if ($editingProject): ?>
            <a href="projects.php" class="btn-edit-admin">Cancel</a>
          <?php endif; ?>
          <button type="submit" class="btn-primary-admin">
            <?= $editingProject ? 'Update Project &rarr;' : 'Save Project &rarr;' ?>
          </button>
        </div>
      </div>
    </form>
  </div>

  <!-- PROJECTS TABLE -->
  <div class="data-card">
    <div class="data-card-header">
      <h2 class="data-card-title">Portfolio Projects (<?= count($projects) ?>)</h2>
      <span style="font-size: 12px; color: var(--text-muted); font-family: 'DM Mono', monospace;">
        Ordered by display sequence
      </span>
    </div>

    <div class="table-responsive">
      <?php if (empty($projects)): ?>
        <div class="empty-state">No projects in portfolio yet. Click "+ Add New Project" above.</div>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th style="width: 70px;">Order</th>
              <th style="width: 80px;">Preview</th>
              <th>Project Details</th>
              <th>Category</th>
              <th>Status</th>
              <th style="text-align: right; width: 180px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($projects as $p): ?>
              <tr>
                <td style="font-family: 'DM Mono', monospace; font-weight: 600;">
                  #<?= (int)$p['display_order'] ?>
                </td>
                <td>
                  <?php if (!empty($p['image'])): ?>
                    <img
                      src="<?= e(BASE_URL . $p['image']) ?>"
                      alt="<?= e($p['title']) ?>"
                      style="width: 60px; height: 40px; object-fit: cover; border-radius: 4px; border: 1px solid var(--border-light);"
                    />
                  <?php else: ?>
                    <div style="width: 60px; height: 40px; background: var(--main-bg); border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: var(--text-muted);">
                      No Img
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <strong style="font-size: 15px; color: var(--text-dark);"><?= e($p['title']) ?></strong>
                  <?php if (!empty($p['client_name'])): ?>
                    <div style="font-size: 12px; color: var(--text-muted);">Client: <?= e($p['client_name']) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($p['project_url'])): ?>
                    <a href="<?= e($p['project_url']) ?>" target="_blank" style="font-size: 11px; color: #3b82f6; text-decoration: underline;">
                      <?= e($p['project_url']) ?> ↗
                    </a>
                  <?php endif; ?>
                </td>
                <td>
                  <span style="font-family: 'DM Mono', monospace; font-size: 11px; background: var(--main-bg); padding: 4px 8px; border-radius: 4px;">
                    <?= e($p['category']) ?>
                  </span>
                </td>
                <td>
                  <form method="POST" action="projects.php" style="display: inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_publish" />
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>" />
                    <input type="hidden" name="current_status" value="<?= e($p['status']) ?>" />
                    <button
                      type="submit"
                      class="status-pill <?= $p['status'] === 'published' ? 'status-closed' : 'status-contacted' ?>"
                      title="Click to toggle publish status"
                      style="cursor: pointer;"
                    >
                      <?= $p['status'] === 'published' ? '● Published' : '○ Draft' ?>
                    </button>
                  </form>
                </td>
                <td style="text-align: right;">
                  <div style="display: inline-flex; gap: 8px;">
                    <a href="projects.php?edit=<?= (int)$p['id'] ?>" class="btn-edit-admin">
                      Edit
                    </a>
                    <form method="POST" action="projects.php" style="display: inline;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete" />
                      <input type="hidden" name="id" value="<?= (int)$p['id'] ?>" />
                      <button
                        type="submit"
                        class="btn-danger-admin"
                        data-confirm="Are you sure you want to permanently delete '<?= e($p['title']) ?>'? Any uploaded image will also be removed from the server."
                        data-confirm-title="Delete Project"
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
    const formCard = document.getElementById('projectFormCard');
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
