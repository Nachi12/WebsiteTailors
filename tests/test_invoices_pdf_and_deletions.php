<?php
/**
 * Test: Deletion Persistence in Calls, Revenue, Invoices & Client Invoice PDF Generation
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pdf_generator.php';

$pdo = Database::getInstance()->getConnection();

echo "========================================================\n";
echo "1. VERIFYING SEEDING FLAG & DELETION IMMUNITY\n";
echo "========================================================\n";

$isSeeded = $pdo->query("SELECT val FROM _db_meta WHERE key = 'crm_seeded'")->fetchColumn();
assert($isSeeded === '1', "Expected crm_seeded flag to be '1', got: " . var_export($isSeeded, true));
echo "✓ _db_meta.crm_seeded is '1'\n";

// -----------------------------------------------------------------
// Test Call Deletion Persistence
// -----------------------------------------------------------------
echo "\n--- Testing Call Deletion Persistence ---\n";
// Insert temporary test call
$pdo->prepare("INSERT INTO calls (contact_name, phone, status, scheduled_at, created_at, call_datetime) VALUES ('Test Deletion Call', '+91 99999 11111', 'scheduled', datetime('now'), datetime('now'), datetime('now'))")->execute();
$callId = (int)$pdo->lastInsertId();
echo "Inserted test call #{$callId}\n";

$check1 = (int)$pdo->query("SELECT COUNT(*) FROM calls WHERE id = {$callId}")->fetchColumn();
assert($check1 === 1, "Call should exist before deletion");

// Delete call
$pdo->exec("DELETE FROM calls WHERE id = {$callId}");
$check2 = (int)$pdo->query("SELECT COUNT(*) FROM calls WHERE id = {$callId}")->fetchColumn();
assert($check2 === 0, "Call should be deleted");
echo "Deleted test call #{$callId}\n";

// Re-run init_sqlite_database to simulate subsequent request/connection initialization
$sqliteFile = __DIR__ . '/../database/WebsiteTailors.sqlite';
require_once __DIR__ . '/../database/init_sqlite.php';
$reInitPdo = init_sqlite_database($sqliteFile);
$check3 = (int)$reInitPdo->query("SELECT COUNT(*) FROM calls WHERE id = {$callId}")->fetchColumn();
assert($check3 === 0, "Deleted call MUST NOT reoccur after database initialization!");
echo "✓ Call #{$callId} remains deleted after database re-init! No reoccurrence.\n";

// -----------------------------------------------------------------
// Test Revenue Deletion Persistence
// -----------------------------------------------------------------
echo "\n--- Testing Revenue Deletion Persistence ---\n";
$pdo->prepare("INSERT INTO revenue (amount, payment_type, payment_status, payment_date, service, notes, created_at, updated_at) VALUES (15000, 'UPI', 'Paid', datetime('now'), 'Websites', 'Test Deletion Revenue', datetime('now'), datetime('now'))")->execute();
$revId = (int)$pdo->lastInsertId();
echo "Inserted test revenue #{$revId}\n";

$pdo->exec("DELETE FROM revenue WHERE id = {$revId}");
$checkRev = (int)$pdo->query("SELECT COUNT(*) FROM revenue WHERE id = {$revId}")->fetchColumn();
assert($checkRev === 0, "Revenue should be deleted");

// Re-run init_sqlite_database
$reInitPdo = init_sqlite_database($sqliteFile);
$checkRev2 = (int)$reInitPdo->query("SELECT COUNT(*) FROM revenue WHERE id = {$revId}")->fetchColumn();
assert($checkRev2 === 0, "Deleted revenue MUST NOT reoccur after database initialization!");
echo "✓ Revenue #{$revId} remains deleted after database re-init! No reoccurrence.\n";

// -----------------------------------------------------------------
// Test Invoice Creation, PDF Export & Deletion Persistence
// -----------------------------------------------------------------
echo "\n========================================================\n";
echo "2. TESTING INVOICE GENERATION & PDF EXPORT\n";
echo "========================================================\n";

// Generate invoice
$testInvNum = 'INV-2026-TEST-' . time();
$insertStmt = $pdo->prepare("
    INSERT INTO invoices (invoice_number, client_name, service, amount, status, due_date, paid_at, notes, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
");
$insertStmt->execute([
    $testInvNum,
    'Acme Innovations Bangalore',
    'AI + Automation',
    145000.00,
    'paid',
    date('Y-m-d', time() + 86400 * 14),
    date('Y-m-d H:i:s'),
    'Milestone 1: Custom Generative AI Agent Orchestrator & Multi-channel Webhooks'
]);
$testInvId = (int)$pdo->lastInsertId();
echo "✓ Generated invoice #{$testInvId} ({$testInvNum}) for ₹1,45,000.00\n";

// Fetch generated invoice
$invRow = $pdo->query("SELECT * FROM invoices WHERE id = {$testInvId}")->fetch(PDO::FETCH_ASSOC);
assert($invRow !== false, "Invoice should exist in database");
assert($invRow['invoice_number'] === $testInvNum, "Invoice number matches");
assert((float)$invRow['amount'] === 145000.00, "Invoice amount matches");

// Generate PDF
$pdf = generate_invoice_pdf($invRow, [
    'client_name' => 'Acme Innovations Bangalore',
    'company_name' => 'Acme Innovations Pvt Ltd',
    'email' => 'finance@acme-innovations.in',
    'phone' => '+91 98450 12345'
]);

assert(str_starts_with($pdf, "%PDF-1.4"), "PDF must start with %PDF-1.4 header");
assert(str_contains($pdf, "%%EOF"), "PDF must end with %%EOF");
assert(str_contains($pdf, "INV-2026-TEST"), "PDF must contain invoice reference");
assert(str_contains($pdf, "Bangalore-560010"), "PDF must contain studio address Bangalore-560010");
assert(str_contains($pdf, "9380552034"), "PDF must contain studio phone 9380552034");
assert(str_contains($pdf, "Acme Innovations"), "PDF must contain client name");
assert(str_contains($pdf, "145,000.00"), "PDF must contain formatted amount INR 145,000.00");
echo "✓ PDF generated successfully! Length: " . strlen($pdf) . " bytes\n";

// Save PDF to temp file and verify with file command
$tmpPdf = "/tmp/test_generated_invoice.pdf";
file_put_contents($tmpPdf, $pdf);
$fileOutput = shell_exec("file " . escapeshellarg($tmpPdf));
echo "✓ File utility check: " . trim($fileOutput) . "\n";
assert(str_contains((string)$fileOutput, "PDF document, version 1.4"), "Must be recognized as PDF version 1.4");

// Delete invoice and verify it stays deleted
echo "\n--- Testing Invoice Deletion Persistence ---\n";
$pdo->exec("DELETE FROM invoices WHERE id = {$testInvId}");
$checkInv = (int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE id = {$testInvId}")->fetchColumn();
assert($checkInv === 0, "Invoice should be deleted");

$reInitPdo = init_sqlite_database($sqliteFile);
$checkInv2 = (int)$reInitPdo->query("SELECT COUNT(*) FROM invoices WHERE id = {$testInvId}")->fetchColumn();
assert($checkInv2 === 0, "Deleted invoice MUST NOT reoccur after database initialization!");
echo "✓ Invoice #{$testInvId} remains deleted after database re-init! No reoccurrence.\n";

echo "\n========================================================\n";
echo "ALL DELETION & PDF GENERATION TESTS PASSED!\n";
echo "========================================================\n";
