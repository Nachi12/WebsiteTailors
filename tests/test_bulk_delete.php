<?php
/**
 * WebsiteTailors Admin — Multi-Select Batch Deletion Test Suite
 *
 * Verifies:
 * 1. Bulk Delete Leads (cascade deletes associated calls, deletes leads, preserves unrelated leads)
 * 2. Bulk Delete Calls (deletes calls, preserves unrelated calls)
 * 3. Bulk Delete Clients (decouples linked leads, deletes clients, preserves other clients)
 * 4. CSRF Validation & Security (rejects invalid tokens, rejects empty selection)
 * 5. Sanitization & Resilience (filters out non-positive and invalid IDs safely)
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/security.php';

$pdo = Database::getInstance()->getConnection();
if (!$pdo) {
    echo "\033[31mFAIL: Could not establish database connection.\033[0m\n";
    exit(1);
}

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $description): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  \033[32m✔ PASS:\033[0m {$description}\n";
    } else {
        $failed++;
        echo "  \033[31m✖ FAIL:\033[0m {$description}\n";
    }
}

echo "\n=======================================================\n";
echo "  WebsiteTailors ADMIN — MULTI-SELECT BATCH DELETE TEST SUITE  \n";
echo "=======================================================\n\n";

// Ensure CSRF token is available in session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$validCsrf = $_SESSION['csrf_token'];

// -------------------------------------------------------------
// TEST 1: BULK DELETE LEADS & ASSOCIATED CALLS
// -------------------------------------------------------------
echo "1. Bulk Delete Leads\n";

// Insert 3 test leads
$stmt = $pdo->prepare("INSERT INTO leads (name, email, phone, message, ip_address, status, call_status, created_at, updated_at) VALUES (?, ?, ?, ?, '127.0.0.1', ?, ?, datetime('now'), datetime('now'))");
$stmt->execute(['Bulk Lead Alpha', 'lead_alpha@example.com', '9998881111', 'Inquiry alpha', 'New', 'Not Called']);
$leadId1 = (int)$pdo->lastInsertId();

$stmt->execute(['Bulk Lead Beta', 'lead_beta@example.com', '9998882222', 'Inquiry beta', 'Contacted', 'Called']);
$leadId2 = (int)$pdo->lastInsertId();

$stmt->execute(['Bulk Lead Gamma', 'lead_gamma@example.com', '9998883333', 'Inquiry gamma', 'Qualified', 'Call Back']);
$leadId3 = (int)$pdo->lastInsertId();

// Insert a control lead that must NOT be deleted
$stmt->execute(['Bulk Lead Keeper', 'lead_keeper@example.com', '9998884444', 'Inquiry keeper', 'New', 'Not Called']);
$keeperLeadId = (int)$pdo->lastInsertId();

// Insert calls for lead 1 and lead 2
$stmtCall = $pdo->prepare("INSERT INTO calls (lead_id, contact_name, phone, scheduled_at, outcome, status, created_at) VALUES (?, ?, ?, datetime('now'), 'Called', 'completed', datetime('now'))");
$stmtCall->execute([$leadId1, 'Bulk Lead Alpha', '9998881111']);
$callId1 = (int)$pdo->lastInsertId();

$stmtCall->execute([$leadId2, 'Bulk Lead Beta', '9998882222']);
$callId2 = (int)$pdo->lastInsertId();

// Control call for keeper lead
$stmtCall->execute([$keeperLeadId, 'Bulk Lead Keeper', '9998884444']);
$keeperCallId = (int)$pdo->lastInsertId();

assertTest($leadId1 > 0 && $leadId2 > 0 && $leadId3 > 0, "Created 3 test leads with IDs #{$leadId1}, #{$leadId2}, #{$leadId3}");
assertTest($callId1 > 0 && $callId2 > 0, "Created calls associated with leads #{$leadId1} and #{$leadId2}");

// Execute bulk delete on leads 1, 2, 3
$rawIds = [$leadId1, (string)$leadId2, $leadId3];
$leadIds = array_values(array_filter(array_map('intval', (array)$rawIds), fn($v) => $v > 0));
$placeholders = implode(',', array_fill(0, count($leadIds), '?'));

$delCalls = $pdo->prepare("DELETE FROM calls WHERE lead_id IN ($placeholders)");
$delCalls->execute($leadIds);

$delLeads = $pdo->prepare("DELETE FROM leads WHERE id IN ($placeholders)");
$delLeads->execute($leadIds);

// Verify test leads are deleted
$checkLeads = $pdo->query("SELECT COUNT(*) FROM leads WHERE id IN ({$leadId1}, {$leadId2}, {$leadId3})")->fetchColumn();
assertTest((int)$checkLeads === 0, "Selected leads #{$leadId1}, #{$leadId2}, #{$leadId3} deleted from 'leads' table");

// Verify associated calls are deleted
$checkCalls = $pdo->query("SELECT COUNT(*) FROM calls WHERE id IN ({$callId1}, {$callId2})")->fetchColumn();
assertTest((int)$checkCalls === 0, "Associated calls for deleted leads were cascaded and removed");

// Verify keeper lead and call still exist
$keeperLeadExists = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE id = {$keeperLeadId}")->fetchColumn();
$keeperCallExists = (int)$pdo->query("SELECT COUNT(*) FROM calls WHERE id = {$keeperCallId}")->fetchColumn();
assertTest($keeperLeadExists === 1, "Unselected keeper lead #{$keeperLeadId} remains safe and untouched");
assertTest($keeperCallExists === 1, "Keeper lead call #{$keeperCallId} remains intact");

// Clean up keeper
$pdo->exec("DELETE FROM calls WHERE id = {$keeperCallId}");
$pdo->exec("DELETE FROM leads WHERE id = {$keeperLeadId}");

// -------------------------------------------------------------
// TEST 2: BULK DELETE CALLS
// -------------------------------------------------------------
echo "\n2. Bulk Delete Calls\n";

// Insert 3 standalone test calls
$stmtCall->execute([null, 'Direct Call 1', '9876543210']);
$testCall1 = (int)$pdo->lastInsertId();

$stmtCall->execute([null, 'Direct Call 2', '9876543211']);
$testCall2 = (int)$pdo->lastInsertId();

$stmtCall->execute([null, 'Direct Call 3', '9876543212']);
$testCall3 = (int)$pdo->lastInsertId();

// Control call that should NOT be deleted
$stmtCall->execute([null, 'Keeper Call', '9876543299']);
$testCallKeeper = (int)$pdo->lastInsertId();

assertTest($testCall1 > 0 && $testCall2 > 0 && $testCall3 > 0, "Created 3 standalone calls #{$testCall1}, #{$testCall2}, #{$testCall3}");

// Execute bulk delete on calls 1 and 2
$callIds = [$testCall1, $testCall2];
$placeholders = implode(',', array_fill(0, count($callIds), '?'));
$delCallsStmt = $pdo->prepare("DELETE FROM calls WHERE id IN ($placeholders)");
$delCallsStmt->execute($callIds);

$checkCallsDeleted = (int)$pdo->query("SELECT COUNT(*) FROM calls WHERE id IN ({$testCall1}, {$testCall2})")->fetchColumn();
$checkCall3Exists = (int)$pdo->query("SELECT COUNT(*) FROM calls WHERE id = {$testCall3}")->fetchColumn();
$checkKeeperCallExists = (int)$pdo->query("SELECT COUNT(*) FROM calls WHERE id = {$testCallKeeper}")->fetchColumn();

assertTest($checkCallsDeleted === 0, "Selected calls #{$testCall1} and #{$testCall2} deleted successfully");
assertTest($checkCall3Exists === 1, "Unselected call #{$testCall3} was preserved");
assertTest($checkKeeperCallExists === 1, "Unselected keeper call #{$testCallKeeper} was preserved");

// Clean up
$pdo->exec("DELETE FROM calls WHERE id IN ({$testCall3}, {$testCallKeeper})");

// -------------------------------------------------------------
// TEST 3: BULK DELETE CLIENTS & LEAD UNLINKING
// -------------------------------------------------------------
echo "\n3. Bulk Delete Clients & Lead Decoupling\n";

// Insert 3 test clients
$stmtClient = $pdo->prepare("INSERT INTO clients (client_name, company_name, email, phone, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, datetime('now'), datetime('now'))");
$stmtClient->execute(['Client Acme Corp', 'Acme Corp', 'contact@acmecorp.com', '9112233445', 'Active']);
$clientId1 = (int)$pdo->lastInsertId();

$stmtClient->execute(['Client Beta Solutions', 'Beta Solutions', 'info@betasol.com', '9112233446', 'Active']);
$clientId2 = (int)$pdo->lastInsertId();

$stmtClient->execute(['Client Gamma Industries', 'Gamma Ind', 'hello@gammaind.com', '9112233447', 'Lead']);
$clientId3 = (int)$pdo->lastInsertId();

// Control client
$stmtClient->execute(['Client Delta Keeper', 'Delta Keeper', 'keep@deltakeeper.com', '9112233448', 'Active']);
$keeperClientId = (int)$pdo->lastInsertId();

assertTest($clientId1 > 0 && $clientId2 > 0 && $clientId3 > 0, "Created 3 test clients #{$clientId1}, #{$clientId2}, #{$clientId3}");

// Create leads linked to Client 1 and Client 2
$stmtLeadLink = $pdo->prepare("INSERT INTO leads (name, email, phone, message, ip_address, client_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, '127.0.0.1', ?, 'Converted', datetime('now'), datetime('now'))");
$stmtLeadLink->execute(['Acme Prospect', 'lead_acme@example.com', '9112233445', 'Acme linked inquiry', $clientId1]);
$linkedLeadId1 = (int)$pdo->lastInsertId();

$stmtLeadLink->execute(['Beta Prospect', 'lead_beta@example.com', '9112233446', 'Beta linked inquiry', $clientId2]);
$linkedLeadId2 = (int)$pdo->lastInsertId();

assertTest($linkedLeadId1 > 0 && $linkedLeadId2 > 0, "Created leads linked to clients (lead #{$linkedLeadId1} -> client #{$clientId1}, lead #{$linkedLeadId2} -> client #{$clientId2})");

// Execute bulk delete on clients 1, 2, 3
$clientIds = [$clientId1, $clientId2, $clientId3];
$placeholders = implode(',', array_fill(0, count($clientIds), '?'));

// 1. Decouple linked leads
$stmtUnlink = $pdo->prepare("UPDATE leads SET client_id = NULL WHERE client_id IN ($placeholders)");
$stmtUnlink->execute($clientIds);

// 2. Delete clients
$stmtDelClients = $pdo->prepare("DELETE FROM clients WHERE id IN ($placeholders)");
$stmtDelClients->execute($clientIds);

// Verify clients are deleted
$checkClients = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE id IN ({$clientId1}, {$clientId2}, {$clientId3})")->fetchColumn();
assertTest($checkClients === 0, "Clients #{$clientId1}, #{$clientId2}, #{$clientId3} deleted from 'clients' table");

// Verify linked leads still exist but client_id is NULL
$leadsData = $pdo->query("SELECT id, client_id FROM leads WHERE id IN ({$linkedLeadId1}, {$linkedLeadId2})")->fetchAll(PDO::FETCH_ASSOC);
$allUnlinked = true;
foreach ($leadsData as $ld) {
    if (!empty($ld['client_id'])) {
        $allUnlinked = false;
    }
}
assertTest(count($leadsData) === 2 && $allUnlinked, "Linked leads #{$linkedLeadId1} and #{$linkedLeadId2} preserved with client_id safely set to NULL");

// Verify keeper client untouched
$checkKeeperClient = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE id = {$keeperClientId}")->fetchColumn();
assertTest($checkKeeperClient === 1, "Unselected keeper client #{$keeperClientId} remains completely intact");

// Clean up
$pdo->exec("DELETE FROM clients WHERE id = {$keeperClientId}");
$pdo->exec("DELETE FROM leads WHERE id IN ({$linkedLeadId1}, {$linkedLeadId2})");

// -------------------------------------------------------------
// TEST 4: SECURITY & INPUT RESILIENCE
// -------------------------------------------------------------
echo "\n4. Security, CSRF & Edge Case Handling\n";

// Test 4.1: Empty array handling
$rawEmpty = [];
$sanitizedEmpty = array_values(array_filter(array_map('intval', (array)$rawEmpty), fn($v) => $v > 0));
assertTest(empty($sanitizedEmpty), "Empty selection array cleanly identified without errors");

// Test 4.2: Malformed and negative IDs filtered out
$rawMixed = ['abc', -5, 0, '12', '99_drop_table', '007'];
$sanitizedMixed = array_values(array_filter(array_map('intval', (array)$rawMixed), fn($v) => $v > 0));
assertTest($sanitizedMixed === [12, 99, 7], "Malformed and non-positive IDs correctly filtered to positive integers: [12, 99, 7]");

// Test 4.3: verify_csrf rejects invalid or missing token
assertTest(verify_csrf($validCsrf) === true, "verify_csrf() accepts valid session CSRF token");
assertTest(verify_csrf('invalid_token_12345') === false, "verify_csrf() rejects forged CSRF token");
assertTest(verify_csrf('') === false, "verify_csrf() rejects missing CSRF token");

// -------------------------------------------------------------
// SUMMARY
// -------------------------------------------------------------
echo "\n=======================================================\n";
echo "  TEST SUMMARY: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n\n";

if ($failed > 0) {
    exit(1);
}
