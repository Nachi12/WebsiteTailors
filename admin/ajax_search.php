<?php
/**
 * WebsiteTailors Admin — Global CRM Search Endpoint
 *
 * Searches across:
 * - Clients (name, company, phone, email)
 * - Leads (name, company, phone, email)
 *
 * Returns JSON grouped results for real-time topbar search.
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) {
    define('WebsiteTailors_INIT', true);
}
require_once __DIR__ . '/includes/auth_guard.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$q = trim(sanitize_text($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) {
    echo json_encode([
        'success' => true,
        'query'   => $q,
        'clients' => [],
        'leads'   => [],
        'total'   => 0
    ]);
    exit;
}

$pdo = Database::getInstance()->getConnection();
$searchTerm = '%' . $q . '%';

$clients = [];
$leads = [];

if ($pdo !== null) {
    try {
        // 1. Search Clients
        $clientStmt = $pdo->prepare("
            SELECT id, client_name, company_name, phone, email, service, status
            FROM clients
            WHERE client_name LIKE :q1 
               OR company_name LIKE :q2 
               OR phone LIKE :q3 
               OR email LIKE :q4
            ORDER BY id DESC
            LIMIT 5
        ");
        $clientStmt->execute([
            ':q1' => $searchTerm,
            ':q2' => $searchTerm,
            ':q3' => $searchTerm,
            ':q4' => $searchTerm
        ]);
        $clients = $clientStmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Search Leads
        $leadStmt = $pdo->prepare("
            SELECT id, name, company, phone, email, service, status, call_status
            FROM leads
            WHERE name LIKE :q1 
               OR company LIKE :q2 
               OR phone LIKE :q3 
               OR email LIKE :q4
            ORDER BY id DESC
            LIMIT 5
        ");
        $leadStmt->execute([
            ':q1' => $searchTerm,
            ':q2' => $searchTerm,
            ':q3' => $searchTerm,
            ':q4' => $searchTerm
        ]);
        $leads = $leadStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        error_log("Global Search Error: " . $e->getMessage());
    }
}

echo json_encode([
    'success' => true,
    'query'   => $q,
    'clients' => $clients,
    'leads'   => $leads,
    'total'   => count($clients) + count($leads)
]);
exit;
