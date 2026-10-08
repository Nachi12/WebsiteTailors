<?php
/**
 * Website Tailors - One-Time Admin Setup Wizard
 * 
 * Used to provision the initial Superadmin account securely on shared hosting
 * environments where SSH/CLI is unavailable. Self-locks once completed.
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
require_once __DIR__ . '/includes/init.php';

$lockFile = CONFIG_PATH . '/installed.lock';
$isLocked = file_exists($lockFile);

// Check if an admin already exists in the database
$existingAdminsCount = 0;
$dbError = null;

try {
    $db = Database::getInstance();
    $existingAdminsCount = (int)$db->fetchColumn("SELECT COUNT(*) FROM admins");
} catch (\Throwable $e) {
    $dbError = $e->getMessage();
}

// If already installed or admins exist, lock down access
if ($isLocked || $existingAdminsCount > 0) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Setup Locked — Website Tailors</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f1013; color: #f4f3ee; display: grid; place-items: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
            .card { background: #18191e; border: 1px solid #2a2b32; border-radius: 16px; padding: 40px; max-width: 480px; width: 100%; box-shadow: 0 20px 40px rgba(0,0,0,0.5); text-align: center; }
            .badge { display: inline-block; background: rgba(255, 75, 75, 0.15); color: #ff5c5c; padding: 6px 14px; border-radius: 999px; font-size: 13px; font-weight: 600; margin-bottom: 20px; border: 1px solid rgba(255, 75, 75, 0.3); }
            h1 { font-size: 24px; margin: 0 0 12px; }
            p { color: #9c9da6; line-height: 1.6; font-size: 14px; margin-bottom: 24px; }
            .btn { display: inline-block; background: #b8ff3d; color: #111; font-weight: 600; padding: 12px 24px; border-radius: 8px; text-decoration: none; transition: transform 0.2s; }
            .btn:hover { transform: translateY(-2px); }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="badge">SECURITY NOTICE</div>
            <h1>Setup is Locked</h1>
            <p>The initial administration account has already been created. For your security, this setup wizard has been permanently disabled.</p>
            <p>If you need to log in to the Website Tailors control panel, proceed below:</p>
            <a href="<?= e(ADMIN_URL . '/login.php') ?>" class="btn">Go to Admin Login &rarr;</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$error = null;
$success = false;

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session validation failed. Please reload and try again.';
    } else {
        $username = sanitize_text($_POST['username'] ?? '');
        $email    = sanitize_email($_POST['email'] ?? '');
        $fullName = sanitize_text($_POST['full_name'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if (!$email) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            $result = create_admin_user($username, $email, $password, $fullName, 'superadmin');
            if ($result['success']) {
                $success = true;
                // Write lockfile
                @file_put_contents($lockFile, date('Y-m-d H:i:s'));
            } else {
                $error = $result['error'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Tailors — Initial Admin Setup</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #0d0e11;
            color: #f4f3ee;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
        }
        .setup-card {
            background: #17181c;
            border: 1px solid #282930;
            border-radius: 16px;
            max-width: 520px;
            width: 100%;
            padding: 40px;
            box-shadow: 0 24px 48px rgba(0,0,0,0.6);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }
        .brand-pill {
            background: #b8ff3d;
            color: #111;
            font-weight: 800;
            font-size: 14px;
            padding: 4px 10px;
            border-radius: 6px;
            letter-spacing: 0.05em;
        }
        .brand-tagline {
            font-size: 13px;
            color: #888995;
        }
        h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        p.subtitle {
            color: #9293a0;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 28px;
        }
        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.4;
            margin-bottom: 24px;
        }
        .alert-error {
            background: rgba(255, 75, 75, 0.12);
            color: #ff6b6b;
            border: 1px solid rgba(255, 75, 75, 0.25);
        }
        .alert-success {
            background: rgba(184, 255, 61, 0.12);
            color: #b8ff3d;
            border: 1px solid rgba(184, 255, 61, 0.25);
        }
        .form-group {
            margin-bottom: 18px;
        }
        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #c9cad4;
        }
        input {
            width: 100%;
            background: #212229;
            border: 1px solid #32343e;
            color: #fff;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        input:focus {
            border-color: #b8ff3d;
        }
        .btn-submit {
            width: 100%;
            background: #b8ff3d;
            color: #111;
            font-weight: 700;
            padding: 14px;
            border-radius: 8px;
            border: none;
            font-size: 15px;
            cursor: pointer;
            margin-top: 10px;
            transition: filter 0.2s, transform 0.1s;
        }
        .btn-submit:hover {
            filter: brightness(1.05);
            transform: translateY(-1px);
        }
        .db-status {
            font-size: 12px;
            color: #666775;
            margin-top: 24px;
            text-align: center;
            border-top: 1px solid #23242c;
            padding-top: 16px;
        }
    </style>
</head>
<body>
    <div class="setup-card">
        <div class="brand">
            <span class="brand-pill">WEBSITE TAILORS</span>
            <span class="brand-tagline">Engineering Digital Precision.</span>
        </div>

        <h1>Initialize Superadmin Account</h1>
        <p class="subtitle">Welcome to Website Tailors. Create your primary administrative credentials to access the admin dashboard.</p>

        <?php if ($dbError): ?>
            <div class="alert alert-error">
                <strong>Database Error:</strong> <?= e($dbError) ?><br>
                Please verify database credentials in <code>config/database.php</code> and import <code>database/WebsiteTailors.sql</code>.
            </div>
        <?php elseif ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>Superadmin Created Successfully!</strong><br>
                The setup wizard is now self-locked. For maximum security, you may delete <code>setup.php</code>.
            </div>
            <a href="<?= e(ADMIN_URL . '/login.php') ?>" class="btn-submit" style="display:block; text-align:center; text-decoration:none;">Proceed to Admin Login &rarr;</a>
        <?php else: ?>
            <form method="POST" action="setup.php">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required value="<?= e($_POST['full_name'] ?? 'Website Tailors Administrator') ?>">
                </div>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required value="<?= e($_POST['username'] ?? 'admin') ?>">
                </div>

                <div class="form-group">
                    <label for="email">Admin Email</label>
                    <input type="email" id="email" name="email" required value="<?= e($_POST['email'] ?? 'websietailorss@gmail.com') ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password (Minimum 8 Characters)</label>
                    <input type="password" id="password" name="password" required minlength="8">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                </div>

                <button type="submit" class="btn-submit">Create Superadmin Account</button>
            </form>
        <?php endif; ?>

        <div class="db-status">
            Production Architecture &bull; PHP <?= e(PHP_VERSION) ?> &bull; PDO MySQL
        </div>
    </div>
</body>
</html>
