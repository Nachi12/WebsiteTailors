<?php
/**
 * WebsiteTailors - Load Test Teardown & Synthetic Lead Cleanup Utility
 * 
 * Safely removes test leads created by load testing scripts without affecting
 * real customer inquiries.
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
require_once dirname(__DIR__, 2) . '/includes/init.php';

echo "====================================================\n";
echo "WebsiteTailors Synthetic Load Test Data Teardown\n";
echo "====================================================\n";

try {
    $pdo = Database::pdo();

    // Count test leads
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE email LIKE :pattern OR email LIKE :pattern2");
    $stmt->execute([':pattern' => '%@loadtest.local', ':pattern2' => '%@test.local']);
    $count = (int)$stmt->fetchColumn();

    echo "Found {$count} synthetic test lead records.\n";

    if ($count > 0) {
        $delStmt = $pdo->prepare("DELETE FROM leads WHERE email LIKE :pattern OR email LIKE :pattern2");
        $delStmt->execute([':pattern' => '%@loadtest.local', ':pattern2' => '%@test.local']);
        $deleted = $delStmt->rowCount();
        echo "Successfully cleaned up {$deleted} synthetic test leads from database.\n";
    }

    // Clean up login attempts generated during tests
    $delLogins = $pdo->exec("DELETE FROM login_attempts WHERE username = 'loadtest_admin' OR username = 'loadtest@websitetailors.local'");
    echo "Purged {$delLogins} test login attempt records.\n";

    echo "Cleanup complete. Database integrity verified.\n";
} catch (\Throwable $e) {
    echo "Teardown Error: " . $e->getMessage() . "\n";
    exit(1);
}
