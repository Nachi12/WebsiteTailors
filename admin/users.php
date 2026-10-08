<?php
/**
 * WebsiteTailors Admin — Users Manager (Shell)
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) { define('WebsiteTailors_INIT', true); }
require_once __DIR__ . '/includes/auth_guard.php';

$pageTitle = 'Admin Users';
$breadcrumb = 'Users';

$admins = [];
try {
    $db = Database::getInstance();
    if ($db->isConnected()) {
        $admins = $db->fetchAll("SELECT id, username, email, full_name, role, is_active, last_login_at, created_at FROM admins");
    }
} catch (\Throwable $e) {}

require_once __DIR__ . '/includes/admin_header.php';
?>

  <div class="page-header">
    <div>
      <h1 class="page-title">Admin Users</h1>
      <p class="page-subtitle">Authorized administrators with dashboard access.</p>
    </div>
  </div>

  <div class="data-card">
    <div class="data-card-header">
      <h2 class="data-card-title">Registered Administrators (<?= count($admins) ?>)</h2>
    </div>

    <div class="table-responsive">
      <?php if (empty($admins)): ?>
        <div class="empty-state">No administrators found in database.</div>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Full Name</th>
              <th>Username</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Last Login</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($admins as $user): ?>
              <tr>
                <td style="font-family: 'DM Mono', monospace; font-size: 11px;"><?= (int)$user['id'] ?></td>
                <td><strong><?= e($user['full_name']) ?></strong></td>
                <td><?= e($user['username']) ?></td>
                <td><?= e($user['email']) ?></td>
                <td>
                  <span style="font-family: 'DM Mono', monospace; font-size: 11px; text-transform: uppercase;">
                    <?= e($user['role']) ?>
                  </span>
                </td>
                <td>
                  <span class="status-pill status-<?= (int)$user['is_active'] === 1 ? 'new' : 'closed' ?>">
                    <?= (int)$user['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                  </span>
                </td>
                <td style="font-family: 'DM Mono', monospace; font-size: 11px; color: var(--text-muted);">
                  <?= e(format_date($user['last_login_at'], 'M j, Y H:i')) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
