<?php
/**
 * Website Tailors - Admin Panel Login
 * 
 * Features:
 * - Email and Password authentication
 * - password_verify()
 * - PHP session management with session_regenerate_id(true)
 * - CSRF token verification
 * - Rate limiting / brute-force protection
 * - Generic authentication error messages
 * - Zero sensitive data in cookies
 * - Never stores plaintext passwords
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) { define('WebsiteTailors_INIT', true); }
require_once dirname(__DIR__) . '/includes/init.php';

// If already authenticated, redirect to admin dashboard
if (is_admin_logged_in()) {
    redirect(ADMIN_URL . '/index.php');
}

$error = null;
$redirectTarget = $_GET['redirect'] ?? (ADMIN_URL . '/index.php');

// Validate local redirect to prevent open redirect attacks
if (str_starts_with($redirectTarget, '//') || (!str_starts_with($redirectTarget, '/') && !str_starts_with($redirectTarget, BASE_URL))) {
    $redirectTarget = ADMIN_URL . '/index.php';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } else {
        $email = sanitize_text($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            $error = 'Please enter both your email address and password.';
        } else {
            $result = attempt_admin_login($email, $password);

            if ($result['success']) {
                set_flash('success', 'Welcome back! You have successfully signed in.');
                redirect($redirectTarget);
            } else {
                // Generic authentication error message
                $error = $result['error'] ?? 'Invalid email or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Sign In — Website Tailors CRM</title>
  <meta name="robots" content="noindex, nofollow" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet" />

  <!-- Admin Stylesheet -->
  <link rel="stylesheet" href="<?= e(ASSETS_URL . '/css/admin.css') ?>" />
</head>
<body class="login-viewport">

  <div class="login-box">
    <div class="login-brand">
      <span>WEBSITE TAILORS</span>
      <span class="brand-dot"></span>
      <span class="brand-badge">CRM</span>
    </div>

    <h1 class="login-title">Admin Sign In</h1>
    <p class="login-subtitle">Sign in with your administrative credentials to continue.</p>

    <?php if ($error): ?>
      <div class="admin-alert alert-error" style="margin-bottom: 24px;">
        <span><?= e($error) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="login.php?redirect=<?= e(urlencode($redirectTarget)) ?>" novalidate>
      <?= csrf_field() ?>

      <div class="admin-form-group">
        <label for="email" class="admin-form-label">Email Address</label>
        <input
          type="email"
          id="email"
          name="email"
          class="admin-form-input"
          placeholder="websietailorss@gmail.com"
          required
          autocomplete="email"
          value="<?= e($_POST['email'] ?? '') ?>"
        />
      </div>

      <div class="admin-form-group">
        <label for="password" class="admin-form-label">Password</label>
        <input
          type="password"
          id="password"
          name="password"
          class="admin-form-input"
          placeholder="••••••••••••"
          required
          autocomplete="current-password"
        />
      </div>

      <button type="submit" class="btn-admin-submit">
        Sign In to Dashboard &rarr;
      </button>
    </form>

    <div style="margin-top: 24px; text-align: center; font-size: 12px; color: #6e7282;">
      <a href="../index.php" style="color: var(--lime); text-decoration: none;">&larr; Return to Public Website</a>
    </div>
  </div>

</body>
</html>
