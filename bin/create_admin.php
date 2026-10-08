<?php
/**
 * WebsiteTailors - CLI Admin Account Provisioning Script
 * 
 * Usage:
 *   php bin/create_admin.php [username] [email] [password] [full_name] [role]
 * Or run interactively:
 *   php bin/create_admin.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

define('WebsiteTailors_INIT', true);
require_once dirname(__DIR__) . '/includes/init.php';

echo "========================================================\n";
echo " WebsiteTailors - Admin User Provisioning CLI\n";
echo "========================================================\n\n";

$args = array_slice($argv, 1);

if (count($args) >= 3) {
    $username = $args[0];
    $email    = $args[1];
    $password = $args[2];
    $fullName = $args[3] ?? 'WebsiteTailors Administrator';
    $role     = $args[4] ?? 'superadmin';
} else {
    // Interactive prompt
    echo "Enter Username (min 3 chars): ";
    $username = trim((string)fgets(STDIN));

    echo "Enter Email: ";
    $email = trim((string)fgets(STDIN));

    echo "Enter Full Name: ";
    $fullName = trim((string)fgets(STDIN));
    if (empty($fullName)) {
        $fullName = 'WebsiteTailors Administrator';
    }

    echo "Enter Role [superadmin/admin] (default: superadmin): ";
    $role = trim((string)fgets(STDIN));
    if (empty($role) || !in_array($role, ['superadmin', 'admin'], true)) {
        $role = 'superadmin';
    }

    echo "Enter Secure Password (min 8 chars): ";
    // Read password
    $password = trim((string)fgets(STDIN));
}

echo "\nValidating and creating admin account...\n";

$result = create_admin_user($username, $email, $password, $fullName, $role);

if ($result['success']) {
    echo "✔ SUCCESS: Admin user created successfully!\n";
    echo "  - ID:       " . $result['id'] . "\n";
    echo "  - Username: " . $username . "\n";
    echo "  - Email:    " . $email . "\n";
    echo "  - Role:     " . $role . "\n";
    echo "  - Login at: " . ADMIN_URL . "/login.php\n\n";
    
    // Touch install lock file
    @file_put_contents(CONFIG_PATH . '/installed.lock', date('Y-m-d H:i:s'));
    exit(0);
} else {
    echo "✖ ERROR: " . $result['error'] . "\n\n";
    exit(1);
}
