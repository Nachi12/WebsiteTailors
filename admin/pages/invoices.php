<?php
/**
 * Website Tailors Admin — Invoices & Client Billing Management
 *
 * Full-featured client invoice generation, native PDF export, lifecycle status tracking,
 * project-based billing, structured line items, payment/UTR reconciliation,
 * and database-driven billing ledger integrated with CRM Clients & Revenue.
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) {
    define('WebsiteTailors_INIT', true);
}
require_once dirname(__DIR__) . '/includes/auth_guard.php';
require_once dirname(__DIR__, 2) . '/includes/pdf_generator.php';

$pageTitle = 'Invoices & Billing';
$breadcrumb = 'Invoices';
$pdo = Database::getInstance()->getConnection();

$error = null;
$success = null;

$validServices = ['Websites', 'Software', 'AI + Automation', 'UI/UX', 'Other'];
$validStatuses = ['pending', 'partially paid', 'paid', 'overdue', 'cancelled', 'refunded', 'draft'];
$validInvoiceTypes = ['advance', 'full'];
$validPaymentModes = ['UPI', 'Bank Transfer', 'Cash', 'Card', 'Other'];
$validPhases = ['Phase 1', 'Phase 2', 'Phase 3', 'Phase 4', 'Phase 5', 'Custom Phase'];

// -----------------------------------------------------------------------------
// 1. GET ACTIONS: Direct PDF Export & Inline View
// -----------------------------------------------------------------------------
$actionGet = sanitize_text($_GET['action'] ?? '');
$invoiceIdGet = (int)($_GET['id'] ?? 0);

if (($actionGet === 'export_pdf' || $actionGet === 'view_pdf') && $invoiceIdGet > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = :id");
        $stmt->execute([':id' => $invoiceIdGet]);
        $inv = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$inv) {
            die('Invoice not found.');
        }

        // Fetch client details if available
        $client = null;
        if (!empty($inv['client_id'])) {
            $cStmt = $pdo->prepare("SELECT * FROM clients WHERE id = :cid LIMIT 1");
            $cStmt->execute([':cid' => $inv['client_id']]);
            $client = $cStmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$client && !empty($inv['client_name'])) {
            $cStmt = $pdo->prepare("SELECT * FROM clients WHERE client_name = :name OR company_name = :name LIMIT 1");
            $cStmt->execute([':name' => $inv['client_name']]);
            $client = $cStmt->fetch(PDO::FETCH_ASSOC);
        }
        $client = is_array($client) ? $client : null;

        $pdfBinary = generate_invoice_pdf($inv, $client);
        $cleanInvNum = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$inv['invoice_number']);
        $filename = "Website-Tailors-Invoice-{$cleanInvNum}.pdf";

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        if ($actionGet === 'view_pdf') {
            header('Content-Disposition: inline; filename="' . $filename . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }
        header('Content-Length: ' . strlen($pdfBinary));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $pdfBinary;
        exit;
    } catch (\Throwable $e) {
        $error = 'Failed to generate PDF: ' . $e->getMessage();
    }
}

// -----------------------------------------------------------------------------
// Helper: Parse & Validate Line Items
// -----------------------------------------------------------------------------
function parse_line_items_input(array $post): ?string {
    $descArr = $post['line_items_desc'] ?? [];
    $qtyArr  = $post['line_items_qty'] ?? [];
    $rateArr = $post['line_items_rate'] ?? [];
    $items = [];
    if (is_array($descArr)) {
        for ($i = 0; $i < count($descArr); $i++) {
            $desc = trim((string)($descArr[$i] ?? ''));
            if ($desc === '') {
                continue;
            }
            $qty = (float)($qtyArr[$i] ?? 1);
            $rate = (float)($rateArr[$i] ?? 0);
            if ($qty <= 0 || $rate < 0 || is_nan($qty) || is_nan($rate)) {
                continue;
            }
            $amount = round($qty * $rate, 2);
            $items[] = [
                'description' => htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'),
                'quantity'    => $qty,
                'rate'        => $rate,
                'amount'      => $amount
            ];
        }
    }

    // Fallback: If line_items passed as JSON string
    if (empty($items) && !empty($post['line_items']) && is_string($post['line_items'])) {
        $decoded = json_decode($post['line_items'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $it) {
                if (!is_array($it)) continue;
                $desc = trim((string)($it['description'] ?? ''));
                if ($desc === '') continue;
                $qty = (float)($it['quantity'] ?? ($it['qty'] ?? 1));
                $rate = (float)($it['rate'] ?? 0);
                if ($qty <= 0 || $rate < 0 || is_nan($qty) || is_nan($rate)) continue;
                $amount = isset($it['amount']) && is_numeric($it['amount'])
                    ? round((float)$it['amount'], 2)
                    : round($qty * $rate, 2);
                if ($amount < 0) continue;
                $items[] = [
                    'description' => htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'),
                    'quantity'    => $qty,
                    'rate'        => $rate,
                    'amount'      => $amount
                ];
            }
        }
    }

    return !empty($items) ? json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
}

// -----------------------------------------------------------------------------
// 2. POST ACTIONS: Create Invoice, Edit Invoice, Delete Invoice
// -----------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } else {
        $action = sanitize_text($_POST['action'] ?? '');
        $invoiceId = (int)($_POST['invoice_id'] ?? 0);

        // =========================================================================
        // A. CREATE INVOICE
        // =========================================================================
        if ($action === 'create_invoice') {
            $clientIdInput     = (int)($_POST['client_id'] ?? 0);
            $clientNameCustom  = trim(sanitize_text($_POST['client_name_custom'] ?? ''));
            $projectIdInput    = (int)($_POST['project_id'] ?? 0);
            $projectNameCustom = trim(sanitize_text($_POST['project_name_custom'] ?? ''));
            $phaseInput        = trim(sanitize_text($_POST['project_phase'] ?? ''));
            $customPhaseInput  = trim(sanitize_text($_POST['custom_phase'] ?? ''));
            $invNumberInput    = trim(sanitize_text($_POST['invoice_number'] ?? ''));
            $invoiceTypeInput  = strtolower(trim(sanitize_text($_POST['invoice_type'] ?? 'full')));
            $serviceInput      = sanitize_text($_POST['service'] ?? 'Websites');
            $invoiceDateInput  = trim($_POST['invoice_date'] ?? date('Y-m-d'));
            $dueDateInput      = trim($_POST['due_date'] ?? '');
            $projectTotalRaw   = trim($_POST['project_total'] ?? '0');
            $amountRaw         = trim($_POST['amount'] ?? '');
            $amountReceivedRaw = trim($_POST['amount_received'] ?? '0');
            $paymentModeInput  = trim(sanitize_text($_POST['payment_mode'] ?? 'UPI'));
            $txnRefInput       = trim(sanitize_text($_POST['transaction_reference'] ?? ''));
            $bankNameInput     = trim(sanitize_text($_POST['bank_name'] ?? ''));
            $pmDescInput       = trim(sanitize_text($_POST['payment_method_desc'] ?? ''));
            $paymentDateInput  = trim($_POST['payment_date'] ?? '');
            $statusInput       = strtolower(trim(sanitize_text($_POST['status'] ?? 'pending')));
            $notesInput        = sanitize_text($_POST['notes'] ?? '');

            // 1. Client Validation
            $finalClientId = null;
            $finalClientName = '';
            if ($clientIdInput > 0) {
                $cStmt = $pdo->prepare("SELECT id, client_name, company_name FROM clients WHERE id = :id LIMIT 1");
                $cStmt->execute([':id' => $clientIdInput]);
                $cRow = $cStmt->fetch(PDO::FETCH_ASSOC);
                if ($cRow) {
                    $finalClientId = (int)$cRow['id'];
                    $finalClientName = !empty($cRow['company_name']) ? $cRow['company_name'] : $cRow['client_name'];
                }
            }
            if (empty($finalClientName)) {
                if (!empty($clientNameCustom)) {
                    $finalClientName = $clientNameCustom;
                    // Check if matching client exists
                    $chk = $pdo->prepare("SELECT id FROM clients WHERE company_name = :name OR client_name = :name LIMIT 1");
                    $chk->execute([':name' => $clientNameCustom]);
                    $existingCid = $chk->fetchColumn();
                    $finalClientId = $existingCid ? (int)$existingCid : null;
                } else {
                    $error = 'Please select a client or enter a custom client name.';
                }
            }

            // 2. Invoice Reference Validation
            if (!$error) {
                if (empty($invNumberInput)) {
                    $year = date('Y');
                    $maxNum = $pdo->query("SELECT invoice_number FROM invoices WHERE invoice_number LIKE 'INV-{$year}-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
                    $seq = 1;
                    if ($maxNum && preg_match('/INV-\d{4}-(\d+)/', (string)$maxNum, $m)) {
                        $seq = ((int)$m[1]) + 1;
                    }
                    $finalInvNumber = sprintf('INV-%s-%03d', $year, $seq);
                } else {
                    $finalInvNumber = strtoupper($invNumberInput);
                }

                // Check uniqueness
                $dupCheck = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE invoice_number = :num");
                $dupCheck->execute([':num' => $finalInvNumber]);
                if ((int)$dupCheck->fetchColumn() > 0) {
                    $error = "Invoice reference #{$finalInvNumber} already exists. Please provide a unique reference number.";
                }
            }

            // 3. Financial Inputs Validation
            if (!$error) {
                if (!is_numeric($amountRaw) || (float)$amountRaw <= 0) {
                    $error = 'Please provide a valid numeric invoice amount greater than 0.';
                } elseif (!is_numeric($projectTotalRaw) || (float)$projectTotalRaw < 0) {
                    $error = 'Project total must be a valid non-negative number.';
                } elseif (!is_numeric($amountReceivedRaw) || (float)$amountReceivedRaw < 0) {
                    $error = 'Amount received must be a valid non-negative number.';
                } elseif ((float)$amountReceivedRaw > (float)$amountRaw) {
                    $error = 'Amount received (₹' . number_format((float)$amountReceivedRaw, 2) . ') cannot exceed current invoice amount (₹' . number_format((float)$amountRaw, 2) . ').';
                } elseif (empty($dueDateInput)) {
                    $error = 'Please provide a valid payment due date.';
                }
            }

            // 4. Project & Phase Resolution
            if (!$error) {
                $finalProjectId = null;
                $finalProjectName = null;
                if ($projectIdInput > 0) {
                    $pStmt = $pdo->prepare("SELECT id, title, client_id FROM projects WHERE id = :pid LIMIT 1");
                    $pStmt->execute([':pid' => $projectIdInput]);
                    $pRow = $pStmt->fetch(PDO::FETCH_ASSOC);
                    if ($pRow) {
                        $finalProjectId = (int)$pRow['id'];
                        $finalProjectName = $pRow['title'];
                    }
                } elseif ($projectIdInput === -1) {
                    $finalProjectId = null;
                    $finalProjectName = !empty($projectNameCustom) ? $projectNameCustom : 'Custom Project';
                }

                // Phase
                $finalPhase = null;
                if (!empty($phaseInput)) {
                    if (strtolower($phaseInput) === 'custom phase' || strtolower($phaseInput) === 'custom') {
                        $finalPhase = !empty($customPhaseInput) ? $customPhaseInput : 'Custom Phase';
                    } else {
                        $finalPhase = $phaseInput;
                    }
                }

                $invoiceTypeVal = in_array($invoiceTypeInput, $validInvoiceTypes, true) ? $invoiceTypeInput : 'full';
                $serviceVal = in_array($serviceInput, $validServices, true) ? $serviceInput : 'Websites';
                $paymentModeVal = in_array($paymentModeInput, $validPaymentModes, true) ? $paymentModeInput : 'UPI';

                $projectTotalNumeric = (float)$projectTotalRaw;
                $amountNumeric = (float)$amountRaw;
                $amountReceivedNumeric = (float)$amountReceivedRaw;
                $advanceAmountNumeric = ($invoiceTypeVal === 'advance') ? $amountNumeric : 0.00;

                // 5. Authoritative Balance Calculation
                // Project balance = project_total - (prior_project_payments + new_amount_received)
                $priorProjectPayments = 0.00;
                if ($finalProjectId && $finalProjectId > 0) {
                    $priorStmt = $pdo->prepare("SELECT COALESCE(SUM(amount_received), 0) FROM invoices WHERE project_id = :pid");
                    $priorStmt->execute([':pid' => $finalProjectId]);
                    $priorProjectPayments = (float)$priorStmt->fetchColumn();
                }
                $totalProjectPayments = $priorProjectPayments + $amountReceivedNumeric;

                if ($projectTotalNumeric > 0) {
                    $balanceAmount = max(0.00, round($projectTotalNumeric - $totalProjectPayments, 2));
                } else {
                    $balanceAmount = max(0.00, round($amountNumeric - $amountReceivedNumeric, 2));
                }

                // 6. Authoritative Status Derivation
                $dueFormatted = date('Y-m-d', strtotime($dueDateInput));
                if (in_array($statusInput, ['cancelled', 'refunded', 'draft'], true)) {
                    $finalStatus = $statusInput;
                } else {
                    if ($amountReceivedNumeric <= 0) {
                        if (strtotime($dueFormatted) < strtotime(date('Y-m-d'))) {
                            $finalStatus = 'overdue';
                        } else {
                            $finalStatus = 'pending';
                        }
                    } elseif ($amountReceivedNumeric > 0 && $amountReceivedNumeric < $amountNumeric) {
                        $finalStatus = 'partially paid';
                    } else {
                        $finalStatus = 'paid';
                    }
                }

                // 7. Payment Date & Mode Details
                $finalPaymentDate = null;
                if ($amountReceivedNumeric > 0 || $finalStatus === 'paid') {
                    $finalPaymentDate = !empty($paymentDateInput) ? date('Y-m-d H:i:s', strtotime($paymentDateInput)) : date('Y-m-d H:i:s');
                }

                $txnRefVal = ($paymentModeVal === 'Cash') ? null : (!empty($txnRefInput) ? $txnRefInput : null);
                $bankNameVal = ($paymentModeVal === 'Bank Transfer') ? (!empty($bankNameInput) ? $bankNameInput : null) : null;
                $pmDescVal = ($paymentModeVal === 'Other') ? (!empty($pmDescInput) ? $pmDescInput : null) : null;

                // 8. Line Items
                $lineItemsJson = parse_line_items_input($_POST);

                $nowStr = date('Y-m-d H:i:s');
                $createdDateStr = !empty($invoiceDateInput) ? date('Y-m-d H:i:s', strtotime($invoiceDateInput)) : $nowStr;

                try {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO invoices (
                            invoice_number, invoice_type, client_id, client_name,
                            project_id, project_name, project_phase, service, amount,
                            project_total, advance_amount, amount_received, balance_amount,
                            status, due_date, paid_at, payment_mode, transaction_reference,
                            bank_name, payment_method_desc, payment_date, line_items,
                            notes, created_at, updated_at
                        ) VALUES (
                            ?, ?, ?, ?,
                            ?, ?, ?, ?, ?,
                            ?, ?, ?, ?,
                            ?, ?, ?, ?, ?,
                            ?, ?, ?, ?,
                            ?, ?, ?
                        )
                    ");
                    $insertStmt->execute([
                        $finalInvNumber,
                        $invoiceTypeVal,
                        $finalClientId,
                        $finalClientName,
                        $finalProjectId,
                        $finalProjectName,
                        $finalPhase,
                        $serviceVal,
                        $amountNumeric,
                        $projectTotalNumeric,
                        $advanceAmountNumeric,
                        $amountReceivedNumeric,
                        $balanceAmount,
                        $finalStatus,
                        $dueFormatted,
                        $finalPaymentDate,
                        $paymentModeVal,
                        $txnRefVal,
                        $bankNameVal,
                        $pmDescVal,
                        $finalPaymentDate,
                        $lineItemsJson,
                        $notesInput,
                        $createdDateStr,
                        $nowStr
                    ]);
                    $newInvoiceId = (int)$pdo->lastInsertId();

                    // 9. Sync with Revenue Ledger if money received or settled
                    if ($amountReceivedNumeric > 0 || $finalStatus === 'paid') {
                        $revAmount = ($amountReceivedNumeric > 0) ? $amountReceivedNumeric : $amountNumeric;
                        $revStatus = ($finalStatus === 'paid') ? 'Paid' : 'Partially Paid';
                        $revNotes = "Settlement for Invoice {$finalInvNumber}: " . ($notesInput ?: 'Project billing');

                        $revStmt = $pdo->prepare("
                            INSERT INTO revenue (
                                client_id, lead_id, invoice_id, project_id, amount,
                                payment_type, transaction_reference, bank_name,
                                payment_status, payment_date, service, notes,
                                created_at, updated_at
                            ) VALUES (
                                ?, NULL, ?, ?, ?,
                                ?, ?, ?,
                                ?, ?, ?, ?,
                                ?, ?
                            )
                        ");
                        $revStmt->execute([
                            $finalClientId,
                            $newInvoiceId,
                            $finalProjectId,
                            $revAmount,
                            $paymentModeVal,
                            $txnRefVal,
                            $bankNameVal,
                            $revStatus,
                            $finalPaymentDate,
                            $serviceVal,
                            $revNotes,
                            $nowStr,
                            $nowStr
                        ]);
                    }

                    $success = "Invoice #{$finalInvNumber} for ₹" . number_format($amountNumeric, 2) . " generated successfully. Ready to export in PDF.";
                } catch (\Throwable $e) {
                    $error = 'Failed to create invoice: ' . $e->getMessage();
                }
            }
        }

        // =========================================================================
        // B. EDIT INVOICE
        // =========================================================================
        elseif ($action === 'edit_invoice' && $invoiceId > 0) {
            $clientIdInput     = (int)($_POST['client_id'] ?? 0);
            $clientNameCustom  = trim(sanitize_text($_POST['client_name_custom'] ?? ''));
            $projectIdInput    = (int)($_POST['project_id'] ?? 0);
            $projectNameCustom = trim(sanitize_text($_POST['project_name_custom'] ?? ''));
            $phaseInput        = trim(sanitize_text($_POST['project_phase'] ?? ''));
            $customPhaseInput  = trim(sanitize_text($_POST['custom_phase'] ?? ''));
            $invoiceTypeInput  = strtolower(trim(sanitize_text($_POST['invoice_type'] ?? 'full')));
            $serviceInput      = sanitize_text($_POST['service'] ?? 'Websites');
            $dueDateInput      = trim($_POST['due_date'] ?? '');
            $projectTotalRaw   = trim($_POST['project_total'] ?? '0');
            $amountRaw         = trim($_POST['amount'] ?? '');
            $amountReceivedRaw = trim($_POST['amount_received'] ?? '0');
            $paymentModeInput  = trim(sanitize_text($_POST['payment_mode'] ?? 'UPI'));
            $txnRefInput       = trim(sanitize_text($_POST['transaction_reference'] ?? ''));
            $bankNameInput     = trim(sanitize_text($_POST['bank_name'] ?? ''));
            $pmDescInput       = trim(sanitize_text($_POST['payment_method_desc'] ?? ''));
            $paymentDateInput  = trim($_POST['payment_date'] ?? '');
            $statusInput       = strtolower(trim(sanitize_text($_POST['status'] ?? 'pending')));
            $notesInput        = sanitize_text($_POST['notes'] ?? '');

            // Fetch current invoice record
            $curInvStmt = $pdo->prepare("SELECT * FROM invoices WHERE id = :id LIMIT 1");
            $curInvStmt->execute([':id' => $invoiceId]);
            $curInv = $curInvStmt->fetch(PDO::FETCH_ASSOC);

            if (!$curInv) {
                $error = 'Invoice not found.';
            } elseif (!is_numeric($amountRaw) || (float)$amountRaw <= 0) {
                $error = 'Please enter a valid numeric amount greater than 0.';
            } elseif (!is_numeric($projectTotalRaw) || (float)$projectTotalRaw < 0) {
                $error = 'Project total must be a valid non-negative number.';
            } elseif (!is_numeric($amountReceivedRaw) || (float)$amountReceivedRaw < 0) {
                $error = 'Amount received must be a valid non-negative number.';
            } elseif ((float)$amountReceivedRaw > (float)$amountRaw) {
                $error = 'Amount received cannot exceed current invoice amount.';
            } elseif (empty($dueDateInput)) {
                $error = 'Please provide a valid due date.';
            } else {
                $amountNumeric = (float)$amountRaw;
                $projectTotalNumeric = (float)$projectTotalRaw;
                $amountReceivedNumeric = (float)$amountReceivedRaw;
                $invoiceTypeVal = in_array($invoiceTypeInput, $validInvoiceTypes, true) ? $invoiceTypeInput : ($curInv['invoice_type'] ?? 'full');
                $advanceAmountNumeric = ($invoiceTypeVal === 'advance') ? $amountNumeric : 0.00;
                $serviceVal = in_array($serviceInput, $validServices, true) ? $serviceInput : 'Websites';
                $paymentModeVal = in_array($paymentModeInput, $validPaymentModes, true) ? $paymentModeInput : 'UPI';
                $dueFormatted = date('Y-m-d', strtotime($dueDateInput));
                $nowStr = date('Y-m-d H:i:s');

                // Resolve client
                $finalClientId = $curInv['client_id'];
                $finalClientName = $curInv['client_name'];
                if ($clientIdInput > 0) {
                    $cRow = $pdo->query("SELECT id, client_name, company_name FROM clients WHERE id = {$clientIdInput}")->fetch(PDO::FETCH_ASSOC);
                    if ($cRow) {
                        $finalClientId = (int)$cRow['id'];
                        $finalClientName = !empty($cRow['company_name']) ? $cRow['company_name'] : $cRow['client_name'];
                    }
                } elseif (!empty($clientNameCustom)) {
                    $finalClientName = $clientNameCustom;
                }

                // Resolve project
                $finalProjectId = $curInv['project_id'];
                $finalProjectName = $curInv['project_name'];
                if ($projectIdInput > 0) {
                    $pRow = $pdo->query("SELECT id, title FROM projects WHERE id = {$projectIdInput}")->fetch(PDO::FETCH_ASSOC);
                    if ($pRow) {
                        $finalProjectId = (int)$pRow['id'];
                        $finalProjectName = $pRow['title'];
                    }
                } elseif ($projectIdInput === -1) {
                    $finalProjectId = null;
                    $finalProjectName = !empty($projectNameCustom) ? $projectNameCustom : ($curInv['project_name'] ?: 'Custom Project');
                } elseif ($projectIdInput === 0 && isset($_POST['project_id'])) {
                    $finalProjectId = null;
                    $finalProjectName = null;
                }

                // Phase
                $finalPhase = $curInv['project_phase'];
                if (!empty($phaseInput)) {
                    if (strtolower($phaseInput) === 'custom phase' || strtolower($phaseInput) === 'custom') {
                        $finalPhase = !empty($customPhaseInput) ? $customPhaseInput : 'Custom Phase';
                    } else {
                        $finalPhase = $phaseInput;
                    }
                }

                // Authoritative balance
                $priorProjectPayments = 0.00;
                if ($finalProjectId && $finalProjectId > 0) {
                    $priorStmt = $pdo->prepare("SELECT COALESCE(SUM(amount_received), 0) FROM invoices WHERE project_id = :pid AND id != :id");
                    $priorStmt->execute([':pid' => $finalProjectId, ':id' => $invoiceId]);
                    $priorProjectPayments = (float)$priorStmt->fetchColumn();
                }
                $totalProjectPayments = $priorProjectPayments + $amountReceivedNumeric;
                if ($projectTotalNumeric > 0) {
                    $balanceAmount = max(0.00, round($projectTotalNumeric - $totalProjectPayments, 2));
                } else {
                    $balanceAmount = max(0.00, round($amountNumeric - $amountReceivedNumeric, 2));
                }

                // Status derivation
                if (in_array($statusInput, ['cancelled', 'refunded', 'draft'], true)) {
                    $finalStatus = $statusInput;
                } else {
                    if ($amountReceivedNumeric <= 0) {
                        if (strtotime($dueFormatted) < strtotime(date('Y-m-d'))) {
                            $finalStatus = 'overdue';
                        } else {
                            $finalStatus = 'pending';
                        }
                    } elseif ($amountReceivedNumeric > 0 && $amountReceivedNumeric < $amountNumeric) {
                        $finalStatus = 'partially paid';
                    } else {
                        $finalStatus = 'paid';
                    }
                }

                $finalPaymentDate = null;
                if ($amountReceivedNumeric > 0 || $finalStatus === 'paid') {
                    $finalPaymentDate = !empty($paymentDateInput) ? date('Y-m-d H:i:s', strtotime($paymentDateInput)) : date('Y-m-d H:i:s');
                }

                $txnRefVal = ($paymentModeVal === 'Cash') ? null : (!empty($txnRefInput) ? $txnRefInput : null);
                $bankNameVal = ($paymentModeVal === 'Bank Transfer') ? (!empty($bankNameInput) ? $bankNameInput : null) : null;
                $pmDescVal = ($paymentModeVal === 'Other') ? (!empty($pmDescInput) ? $pmDescInput : null) : null;

                // Line items
                $lineItemsJson = parse_line_items_input($_POST);
                if ($lineItemsJson === null && !empty($curInv['line_items']) && empty($_POST['line_items_desc'])) {
                    $lineItemsJson = $curInv['line_items']; // preserve if not submitted
                }

                try {
                    $updStmt = $pdo->prepare("
                        UPDATE invoices
                        SET client_id = :client_id,
                            client_name = :client_name,
                            project_id = :project_id,
                            project_name = :project_name,
                            project_phase = :project_phase,
                            invoice_type = :invoice_type,
                            service = :service,
                            amount = :amount,
                            project_total = :project_total,
                            advance_amount = :advance_amount,
                            amount_received = :amount_received,
                            balance_amount = :balance_amount,
                            status = :status,
                            due_date = :due_date,
                            paid_at = :paid_at,
                            payment_mode = :payment_mode,
                            transaction_reference = :transaction_reference,
                            bank_name = :bank_name,
                            payment_method_desc = :payment_method_desc,
                            payment_date = :payment_date,
                            line_items = :line_items,
                            notes = :notes,
                            updated_at = :updated_at
                        WHERE id = :id
                    ");
                    $updStmt->execute([
                        ':client_id'             => $finalClientId,
                        ':client_name'           => $finalClientName,
                        ':project_id'            => $finalProjectId,
                        ':project_name'          => $finalProjectName,
                        ':project_phase'         => $finalPhase,
                        ':invoice_type'          => $invoiceTypeVal,
                        ':service'               => $serviceVal,
                        ':amount'                => $amountNumeric,
                        ':project_total'         => $projectTotalNumeric,
                        ':advance_amount'        => $advanceAmountNumeric,
                        ':amount_received'       => $amountReceivedNumeric,
                        ':balance_amount'        => $balanceAmount,
                        ':status'                => $finalStatus,
                        ':due_date'              => $dueFormatted,
                        ':paid_at'               => $finalPaymentDate,
                        ':payment_mode'          => $paymentModeVal,
                        ':transaction_reference' => $txnRefVal,
                        ':bank_name'             => $bankNameVal,
                        ':payment_method_desc'   => $pmDescVal,
                        ':payment_date'          => $finalPaymentDate,
                        ':line_items'            => $lineItemsJson,
                        ':notes'                 => $notesInput,
                        ':updated_at'            => $nowStr,
                        ':id'                    => $invoiceId
                    ]);

                    // Sync Revenue Ledger
                    $revExists = (int)$pdo->query("SELECT COUNT(*) FROM revenue WHERE invoice_id = {$invoiceId}")->fetchColumn();
                    if ($amountReceivedNumeric > 0 || $finalStatus === 'paid') {
                        $revAmount = ($amountReceivedNumeric > 0) ? $amountReceivedNumeric : $amountNumeric;
                        $revStatus = ($finalStatus === 'paid') ? 'Paid' : 'Partially Paid';
                        $revNotes = "Settlement for Invoice {$curInv['invoice_number']}: " . ($notesInput ?: 'Project billing');

                        if ($revExists === 0) {
                            $revStmt = $pdo->prepare("
                                INSERT INTO revenue (
                                    client_id, lead_id, invoice_id, project_id, amount,
                                    payment_type, transaction_reference, bank_name,
                                    payment_status, payment_date, service, notes,
                                    created_at, updated_at
                                ) VALUES (
                                    ?, NULL, ?, ?, ?,
                                    ?, ?, ?,
                                    ?, ?, ?, ?,
                                    ?, ?
                                )
                            ");
                            $revStmt->execute([
                                $finalClientId,
                                $invoiceId,
                                $finalProjectId,
                                $revAmount,
                                $paymentModeVal,
                                $txnRefVal,
                                $bankNameVal,
                                $revStatus,
                                $finalPaymentDate,
                                $serviceVal,
                                $revNotes,
                                $nowStr,
                                $nowStr
                            ]);
                        } else {
                            $pdo->prepare("
                                UPDATE revenue
                                SET client_id = ?,
                                    project_id = ?,
                                    amount = ?,
                                    payment_type = ?,
                                    transaction_reference = ?,
                                    bank_name = ?,
                                    payment_status = ?,
                                    payment_date = ?,
                                    service = ?,
                                    notes = ?,
                                    updated_at = ?
                                WHERE invoice_id = ?
                            ")->execute([
                                $finalClientId,
                                $finalProjectId,
                                $revAmount,
                                $paymentModeVal,
                                $txnRefVal,
                                $bankNameVal,
                                $revStatus,
                                $finalPaymentDate,
                                $serviceVal,
                                $revNotes,
                                $nowStr,
                                $invoiceId
                            ]);
                        }
                    } else {
                        // If payment removed/reverted, unlink or delete revenue row
                        if ($revExists > 0) {
                            $pdo->prepare("DELETE FROM revenue WHERE invoice_id = ?")->execute([$invoiceId]);
                        }
                    }

                    $success = "Invoice #{$curInv['invoice_number']} updated successfully.";
                } catch (\Throwable $e) {
                    $error = 'Failed to update invoice: ' . $e->getMessage();
                }
            }
        }

        // =========================================================================
        // C. DELETE INVOICE
        // =========================================================================
        elseif ($action === 'delete_invoice' && $invoiceId > 0) {
            try {
                $invRow = $pdo->query("SELECT invoice_number FROM invoices WHERE id = {$invoiceId}")->fetch(PDO::FETCH_ASSOC);
                $invNum = $invRow ? $invRow['invoice_number'] : "#{$invoiceId}";

                $delStmt = $pdo->prepare("DELETE FROM invoices WHERE id = :id");
                $delStmt->execute([':id' => $invoiceId]);

                // Unlink invoice from revenue ledger if exists
                $pdo->prepare("UPDATE revenue SET invoice_id = NULL WHERE invoice_id = :id")->execute([':id' => $invoiceId]);

                $success = "Invoice {$invNum} has been permanently deleted.";
            } catch (\Throwable $e) {
                $error = 'Failed to delete invoice: ' . $e->getMessage();
            }
        }

        // =========================================================================
        // D. RECORD PAYMENT (PHASE 5 WORKFLOW)
        // =========================================================================
        elseif ($action === 'record_payment' && $invoiceId > 0) {
            $amountRaw         = trim($_POST['amount'] ?? '');
            $paymentDateInput  = trim($_POST['payment_date'] ?? '');
            $paymentModeInput  = trim(sanitize_text($_POST['payment_mode'] ?? 'UPI'));
            $txnRefInput       = trim(sanitize_text($_POST['transaction_reference'] ?? ''));
            $bankNameInput     = trim(sanitize_text($_POST['bank_name'] ?? ''));
            $pmDescInput       = trim(sanitize_text($_POST['payment_method_desc'] ?? ''));
            $notesInput        = trim(sanitize_text($_POST['notes'] ?? ''));

            // Fetch target invoice
            $curInvStmt = $pdo->prepare("SELECT * FROM invoices WHERE id = :id LIMIT 1");
            $curInvStmt->execute([':id' => $invoiceId]);
            $curInv = $curInvStmt->fetch(PDO::FETCH_ASSOC);

            if (!$curInv) {
                $error = 'Invoice not found.';
            } elseif (in_array(strtolower((string)$curInv['status']), ['cancelled', 'refunded'], true)) {
                $error = 'Cannot record payment on a cancelled or refunded invoice.';
            } elseif (!is_numeric($amountRaw) || (float)$amountRaw <= 0) {
                $error = 'Please enter a valid numeric payment amount greater than 0.';
            } elseif (!in_array($paymentModeInput, $validPaymentModes, true)) {
                $error = 'Please select a valid payment mode.';
            } elseif (empty($paymentDateInput) || strtotime($paymentDateInput) === false) {
                $error = 'Please provide a valid payment date.';
            } elseif (isset($_POST['project_id']) && (int)$_POST['project_id'] > 0 && (int)$_POST['project_id'] !== (int)$curInv['project_id']) {
                $error = 'Invalid project relationship for this invoice.';
            } elseif (isset($_POST['client_id']) && (int)$_POST['client_id'] > 0 && (int)$_POST['client_id'] !== (int)$curInv['client_id']) {
                $error = 'Invalid client relationship for this invoice.';
            } else {
                $payAmount = (float)$amountRaw;
                $curInvBilled = (float)$curInv['amount'];
                $curInvReceived = (float)($curInv['amount_received'] ?? 0);
                $curInvRemaining = max(0.00, round($curInvBilled - $curInvReceived, 2));

                // 1. Overpayment Protection
                if ($payAmount > ($curInvRemaining + 0.0001)) {
                    $error = 'Payment exceeds the remaining balance.';
                }
                // 2. Duplicate UTR Protection
                elseif ($paymentModeInput !== 'Cash' && !empty($txnRefInput)) {
                    $chkUtr = $pdo->prepare("SELECT COUNT(*) FROM revenue WHERE transaction_reference = :utr");
                    $chkUtr->execute([':utr' => $txnRefInput]);
                    if ((int)$chkUtr->fetchColumn() > 0) {
                        $error = 'This payment appears to have already been recorded.';
                    }
                }

                // 3. Duplicate POST / Double-click Protection (within 15 seconds)
                if ($error === null) {
                    $recentThreshold = date('Y-m-d H:i:s', time() - 15);
                    $chkRecent = $pdo->prepare("
                        SELECT COUNT(*) FROM revenue 
                        WHERE invoice_id = :iid 
                          AND amount = :amt 
                          AND created_at >= :thresh
                    ");
                    $chkRecent->execute([
                        ':iid' => $invoiceId,
                        ':amt' => $payAmount,
                        ':thresh' => $recentThreshold
                    ]);
                    if ((int)$chkRecent->fetchColumn() > 0) {
                        $error = 'This payment appears to have already been recorded.';
                    }
                }

                // 4. Atomic Execution via Database Transaction
                if ($error === null) {
                    if (!$pdo->inTransaction()) {
                        $pdo->beginTransaction();
                    }
                    try {
                        $nowStr = date('Y-m-d H:i:s');
                        $paymentDatetime = date('Y-m-d H:i:s', strtotime($paymentDateInput));

                        $txnRefVal = ($paymentModeInput === 'Cash') ? null : (!empty($txnRefInput) ? $txnRefInput : null);
                        $bankNameVal = ($paymentModeInput === 'Bank Transfer') ? (!empty($bankNameInput) ? $bankNameInput : null) : null;
                        $pmDescVal = ($paymentModeInput === 'Other') ? (!empty($pmDescInput) ? $pmDescInput : null) : null;

                        $revNotes = "Payment for Invoice #{$curInv['invoice_number']}" . ($notesInput ? ": {$notesInput}" : "");

                        // A. Insert payment record into revenue ledger
                        $revStmt = $pdo->prepare("
                            INSERT INTO revenue (
                                client_id, client_name, lead_id, invoice_id, project_id, amount,
                                payment_type, transaction_reference, bank_name,
                                payment_status, payment_date, service, notes,
                                created_at, updated_at
                            ) VALUES (
                                ?, ?, NULL, ?, ?, ?,
                                ?, ?, ?,
                                'Paid', ?, ?, ?,
                                ?, ?
                            )
                        ");
                        $revStmt->execute([
                            $curInv['client_id'],
                            $curInv['client_name'],
                            $invoiceId,
                            $curInv['project_id'],
                            $payAmount,
                            $paymentModeInput,
                            $txnRefVal,
                            $bankNameVal,
                            $paymentDatetime,
                            $curInv['service'] ?: 'Websites',
                            $revNotes,
                            $nowStr,
                            $nowStr
                        ]);

                        // B. Recalculate Invoice Paid & Status
                        $newInvoicePaid = round($curInvReceived + $payAmount, 2);
                        $newInvoiceRemaining = max(0.00, round($curInvBilled - $newInvoicePaid, 2));

                        $dueFormatted = date('Y-m-d', strtotime((string)$curInv['due_date']));
                        if (in_array(strtolower((string)$curInv['status']), ['cancelled', 'refunded'], true)) {
                            $newInvStatus = $curInv['status'];
                        } else {
                            if ($newInvoicePaid >= $curInvBilled) {
                                $newInvStatus = 'paid';
                            } elseif ($newInvoicePaid > 0) {
                                $newInvStatus = 'partially paid';
                            } else {
                                $newInvStatus = (strtotime($dueFormatted) < strtotime(date('Y-m-d'))) ? 'overdue' : 'pending';
                            }
                        }

                        // C. Recalculate Project Balance
                        $projId = !empty($curInv['project_id']) ? (int)$curInv['project_id'] : null;
                        $projTotal = (float)($curInv['project_total'] ?? 0);

                        if ($projId && $projId > 0) {
                            $projPaidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM revenue WHERE project_id = :pid AND LOWER(payment_status) = 'paid'");
                            $projPaidStmt->execute([':pid' => $projId]);
                            $totalProjectPayments = (float)$projPaidStmt->fetchColumn();

                            if ($projTotal > 0) {
                                $newProjBalance = max(0.00, round($projTotal - $totalProjectPayments, 2));
                            } else {
                                $newProjBalance = $newInvoiceRemaining;
                            }

                            // Update all project invoices
                            $updAllProjInvs = $pdo->prepare("UPDATE invoices SET balance_amount = :bal WHERE project_id = :pid AND project_total > 0");
                            $updAllProjInvs->execute([':bal' => $newProjBalance, ':pid' => $projId]);

                            $invoiceBalanceVal = ($projTotal > 0) ? $newProjBalance : $newInvoiceRemaining;
                        } else {
                            if ($projTotal > 0) {
                                $invoiceBalanceVal = max(0.00, round($projTotal - $newInvoicePaid, 2));
                            } else {
                                $invoiceBalanceVal = $newInvoiceRemaining;
                            }
                        }

                        // D. Update the target Invoice
                        $updInvStmt = $pdo->prepare("
                            UPDATE invoices
                            SET amount_received = :received,
                                balance_amount = :balance,
                                status = :status,
                                paid_at = :paid_at,
                                payment_date = :payment_date,
                                payment_mode = :payment_mode,
                                transaction_reference = COALESCE(:txn_ref, transaction_reference),
                                bank_name = COALESCE(:bank_name, bank_name),
                                payment_method_desc = COALESCE(:pm_desc, payment_method_desc),
                                updated_at = :updated_at
                            WHERE id = :id
                        ");
                        $updInvStmt->execute([
                            ':received'     => $newInvoicePaid,
                            ':balance'      => $invoiceBalanceVal,
                            ':status'       => $newInvStatus,
                            ':paid_at'      => $paymentDatetime,
                            ':payment_date' => $paymentDatetime,
                            ':payment_mode' => $paymentModeInput,
                            ':txn_ref'      => $txnRefVal,
                            ':bank_name'    => $bankNameVal,
                            ':pm_desc'      => $pmDescVal,
                            ':updated_at'   => $nowStr,
                            ':id'           => $invoiceId
                        ]);

                        $pdo->commit();
                        $success = "Payment of ₹" . number_format($payAmount, 2) . " successfully recorded for Invoice #{$curInv['invoice_number']}.";

                        // AJAX/JSON support
                        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                            header('Content-Type: application/json');
                            echo json_encode([
                                'success' => true,
                                'message' => $success,
                                'invoice_number' => $curInv['invoice_number'],
                                'amount_received' => $newInvoicePaid,
                                'balance_amount' => $invoiceBalanceVal,
                                'status' => $newInvStatus
                            ]);
                            exit;
                        }
                    } catch (\Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $error = 'Failed to record payment: ' . $e->getMessage();
                        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                            header('Content-Type: application/json', true, 400);
                            echo json_encode(['success' => false, 'error' => $error]);
                            exit;
                        }
                    }
                }
            }

            if ($error !== null && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json', true, 400);
                echo json_encode(['success' => false, 'error' => $error]);
                exit;
            }
        }
    }
}

// -----------------------------------------------------------------------------
// 3. STATS & DATA QUERIES
// -----------------------------------------------------------------------------
$stats = [
    'total_amount'     => 0.0,
    'paid_amount'      => 0.0,
    'pending_amount'   => 0.0,
    'count_total'      => 0,
    'count_paid'       => 0,
    'count_pending'    => 0,
    'count_partially'  => 0
];

try {
    $statRows = $pdo->query("SELECT amount, amount_received, status FROM invoices")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($statRows as $row) {
        $amt = (float)$row['amount'];
        $received = isset($row['amount_received']) ? (float)$row['amount_received'] : 0.0;
        $st = strtolower((string)$row['status']);

        $stats['total_amount'] += $amt;
        $stats['count_total']++;

        if ($st === 'paid') {
            $stats['paid_amount'] += ($received > 0 ? $received : $amt);
            $stats['count_paid']++;
        } elseif ($st === 'partially paid') {
            $stats['paid_amount'] += $received;
            $stats['pending_amount'] += max(0, $amt - $received);
            $stats['count_partially']++;
        } elseif ($st === 'pending' || $st === 'overdue') {
            $stats['pending_amount'] += ($amt - $received);
            $stats['count_pending']++;
        }
    }
} catch (\Throwable $e) {
    error_log("Invoices stats error: " . $e->getMessage());
}

// Filters & Search
$statusFilter = strtolower(sanitize_text($_GET['status'] ?? 'all'));
$typeFilter = strtolower(sanitize_text($_GET['type'] ?? 'all'));
$modeFilter = sanitize_text($_GET['mode'] ?? 'all');
$serviceFilter = sanitize_text($_GET['service'] ?? 'all');
$searchQuery = trim(sanitize_text($_GET['q'] ?? $_GET['search'] ?? ''));

$whereClauses = [];
$params = [];

if ($statusFilter !== 'all' && in_array($statusFilter, $validStatuses, true)) {
    $whereClauses[] = "LOWER(i.status) = :status";
    $params[':status'] = $statusFilter;
}

if ($typeFilter !== 'all' && in_array($typeFilter, $validInvoiceTypes, true)) {
    $whereClauses[] = "LOWER(i.invoice_type) = :type";
    $params[':type'] = $typeFilter;
}

if ($modeFilter !== 'all' && in_array($modeFilter, $validPaymentModes, true)) {
    $whereClauses[] = "i.payment_mode = :mode";
    $params[':mode'] = $modeFilter;
}

if ($serviceFilter !== 'all' && in_array($serviceFilter, $validServices, true)) {
    $whereClauses[] = "LOWER(i.service) = :service";
    $params[':service'] = strtolower($serviceFilter);
}

if (!empty($searchQuery)) {
    $whereClauses[] = "(i.invoice_number LIKE :q OR i.client_name LIKE :q OR i.project_name LIKE :q OR i.service LIKE :q OR i.transaction_reference LIKE :q OR i.notes LIKE :q)";
    $params[':q'] = "%{$searchQuery}%";
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

$invoicesQuery = "
    SELECT i.*, 
           c.company_name as client_company, 
           c.email as client_email, 
           c.phone as client_phone
    FROM invoices i
    LEFT JOIN clients c ON i.client_id = c.id
    {$whereSql}
    ORDER BY i.id DESC
";

$invStmt = $pdo->prepare($invoicesQuery);
$invStmt->execute($params);
$invoices = $invStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all payment history linked to invoices
$allPayments = $pdo->query("SELECT * FROM revenue WHERE invoice_id IS NOT NULL ORDER BY payment_date ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
$invPaymentsMap = [];
$projPaymentsMap = [];
foreach ($allPayments as $p) {
    $iid = (int)$p['invoice_id'];
    $invPaymentsMap[$iid][] = $p;
    if (!empty($p['project_id'])) {
        $projPaymentsMap[(int)$p['project_id']][] = $p;
    }
}
foreach ($invoices as &$invItem) {
    $invItem['payments'] = $invPaymentsMap[(int)$invItem['id']] ?? [];
    if (!empty($invItem['project_id'])) {
        $invItem['project_payments'] = $projPaymentsMap[(int)$invItem['project_id']] ?? [];
    } else {
        $invItem['project_payments'] = [];
    }
}
unset($invItem);

// Fetch clients list
$clientsList = $pdo->query("SELECT id, client_name, company_name, email, phone, service FROM clients ORDER BY client_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch projects list with client_id
$projectsList = $pdo->query("SELECT id, title, client_id, status FROM projects ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

// Precompute client -> projects map & project payments map for immediate JS reactivity
$clientProjectsMap = [];
foreach ($projectsList as $p) {
    if (!empty($p['client_id'])) {
        $cid = (int)$p['client_id'];
        if (!isset($clientProjectsMap[$cid])) {
            $clientProjectsMap[$cid] = [];
        }
        $clientProjectsMap[$cid][] = [
            'id'     => (int)$p['id'],
            'title'  => $p['title'],
            'status' => $p['status'] ?? 'active'
        ];
    }
}

$projectPaymentsMap = [];
$ppRows = $pdo->query("SELECT project_id, COALESCE(SUM(amount_received), 0) as paid FROM invoices WHERE project_id IS NOT NULL AND project_id > 0 GROUP BY project_id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($ppRows as $r) {
    $projectPaymentsMap[(int)$r['project_id']] = (float)$r['paid'];
}

// Auto-generate next suggested invoice number
$year = date('Y');
$latestInv = $pdo->query("SELECT invoice_number FROM invoices WHERE invoice_number LIKE 'INV-{$year}-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
$suggestedSeq = 1;
if ($latestInv && preg_match('/INV-\d{4}-(\d+)/', (string)$latestInv, $m)) {
    $suggestedSeq = ((int)$m[1]) + 1;
}
$suggestedInvoiceNumber = sprintf('INV-%s-%03d', $year, $suggestedSeq);

require_once dirname(__DIR__) . '/includes/admin_header.php';
?>

<style>
/* Website Tailors Admin Invoice Modal & Ledger Styles */
.inv-section-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  color: #14161A;
  margin: 16px 0 10px 0;
  padding-bottom: 6px;
  border-bottom: 1px solid #E4E1D9;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.inv-section-title:first-of-type {
  margin-top: 0;
}
.financial-summary-card {
  background: #0F1115;
  color: #FFFFFF;
  border-radius: 10px;
  padding: 16px 20px;
  margin: 14px 0;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  border: 1px solid #222430;
}
.fin-metric-label {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.6px;
  color: #94A3B8;
  margin-bottom: 4px;
}
.fin-metric-value {
  font-family: 'DM Mono', monospace;
  font-size: 16px;
  font-weight: 700;
  color: #FFFFFF;
  white-space: nowrap;
}
.fin-metric-value.highlight-lime {
  color: #C8F03C;
}
.fin-metric-value.highlight-trust {
  color: #10B981;
}
.inv-type-pill-group {
  display: flex;
  gap: 10px;
  margin-bottom: 14px;
}
.inv-type-pill-label {
  flex: 1;
  border: 1.5px solid #E4E1D9;
  border-radius: 8px;
  padding: 10px 14px;
  cursor: pointer;
  background: #FFFFFF;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  font-weight: 600;
  color: #14161A;
  transition: all 0.15s ease;
}
.inv-type-pill-label:hover {
  border-color: #14161A;
}
.inv-type-pill-label.selected {
  border-color: #14161A;
  background: #F6F4EF;
}
.line-items-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 8px;
  font-size: 12px;
}
.line-items-table th {
  text-align: left;
  font-size: 11px;
  color: #5B6068;
  font-weight: 600;
  padding: 6px 8px;
  border-bottom: 1px solid #E4E1D9;
}
.line-items-table td {
  padding: 6px 4px;
  vertical-align: middle;
}
.btn-remove-line {
  background: #fee2e2;
  color: #dc2626;
  border: none;
  border-radius: 6px;
  width: 28px;
  height: 28px;
  display: grid;
  place-items: center;
  cursor: pointer;
  font-size: 13px;
  font-weight: bold;
}
.btn-remove-line:hover {
  background: #fecaca;
}
.status-pill.status-partially-paid {
  background: #fef3c7;
  color: #b45309;
}
.status-pill.status-overdue {
  background: #fee2e2;
  color: #dc2626;
}
@media (max-width: 768px) {
  .financial-summary-card {
    grid-template-columns: repeat(2, 1fr);
    gap: 14px;
  }
}
</style>

  <!-- Flash Alerts -->
  <?php if ($success): ?>
    <div class="admin-alert alert-success">
      <span><?= e($success) ?></span>
      <button type="button" onclick="this.parentElement.remove()" style="font-weight:bold; color:inherit;">✕</button>
    </div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="admin-alert alert-error">
      <span><?= e($error) ?></span>
      <button type="button" onclick="this.parentElement.remove()" style="font-weight:bold; color:inherit;">✕</button>
    </div>
  <?php endif; ?>

  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h1 class="page-title">Invoices &amp; Billing</h1>
      <p class="page-subtitle">Project-based billing, advance payments, milestone reconciliation, and high-resolution PDF exports.</p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
      <button type="button" class="btn-primary-admin" onclick="openGenerateInvoiceModal()" style="padding: 9px 18px; font-size: 13px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(200, 240, 60, 0.2);">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="stroke-width: 2.5;">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        <span>Generate Invoice</span>
      </button>
    </div>
  </div>

  <!-- Invoices KPI Grid -->
  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-top">
        <span class="kpi-label">Total Invoiced</span>
        <div class="kpi-icon-wrap blue">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
      </div>
      <div class="kpi-value" style="font-size: 24px;">₹<?= number_format($stats['total_amount'], 2) ?></div>
      <div class="kpi-footer">
        <span class="kpi-tag positive"><?= (int)$stats['count_total'] ?> Invoices</span>
        <span>Across all clients</span>
      </div>
    </div>

    <div class="kpi-card">
      <div class="kpi-top">
        <span class="kpi-label">Collected Payments</span>
        <div class="kpi-icon-wrap lime">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
          </svg>
        </div>
      </div>
      <div class="kpi-value" style="font-size: 24px; color: #1F5C4A;">₹<?= number_format($stats['paid_amount'], 2) ?></div>
      <div class="kpi-footer">
        <span class="kpi-tag positive"><?= (int)$stats['count_paid'] ?> Settled</span>
        <span>Funds realized</span>
      </div>
    </div>

    <div class="kpi-card">
      <div class="kpi-top">
        <span class="kpi-label">Outstanding Receivables</span>
        <div class="kpi-icon-wrap orange">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
      </div>
      <div class="kpi-value" style="font-size: 24px; color: #b45309;">₹<?= number_format($stats['pending_amount'], 2) ?></div>
      <div class="kpi-footer">
        <span class="kpi-tag alert"><?= (int)($stats['count_pending'] + $stats['count_partially']) ?> Pending</span>
        <span>Awaiting clearance</span>
      </div>
    </div>

    <div class="kpi-card">
      <div class="kpi-top">
        <span class="kpi-label">Studio Location</span>
        <div class="kpi-icon-wrap purple">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
        </div>
      </div>
      <div class="kpi-value" style="font-size: 20px; font-weight: 700;">Bangalore</div>
      <div class="kpi-footer">
        <span class="kpi-tag positive">Karnataka 560010</span>
        <span>Website Tailors Digital</span>
      </div>
    </div>
  </div>

  <!-- Invoices Search & Filter Card -->
  <div class="data-card" style="margin-bottom: 20px; padding: 14px 18px;">
    <form method="GET" action="invoices.php" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
      <!-- Search Input -->
      <div style="flex: 1; min-width: 220px; position: relative;">
        <input type="text" name="q" value="<?= e($searchQuery) ?>" placeholder="Search by invoice #, client, project, UTR..." class="form-control" style="width: 100%; padding: 8px 12px 8px 34px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px;">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="position: absolute; left: 10px; top: 10px; color: var(--text-muted);">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
      </div>

      <!-- Type Filter -->
      <div style="min-width: 130px;">
        <select name="type" class="form-control" onchange="this.form.submit()" style="width: 100%; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; background: #ffffff;">
          <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>All Types</option>
          <option value="advance" <?= $typeFilter === 'advance' ? 'selected' : '' ?>>Advance</option>
          <option value="full" <?= $typeFilter === 'full' ? 'selected' : '' ?>>Full Payment</option>
        </select>
      </div>

      <!-- Status Filter -->
      <div style="min-width: 140px;">
        <select name="status" class="form-control" onchange="this.form.submit()" style="width: 100%; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; background: #ffffff;">
          <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
          <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
          <option value="partially paid" <?= $statusFilter === 'partially paid' ? 'selected' : '' ?>>Partially Paid</option>
          <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
          <option value="overdue" <?= $statusFilter === 'overdue' ? 'selected' : '' ?>>Overdue</option>
          <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
          <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>
      </div>

      <!-- Payment Mode Filter -->
      <div style="min-width: 130px;">
        <select name="mode" class="form-control" onchange="this.form.submit()" style="width: 100%; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; background: #ffffff;">
          <option value="all" <?= $modeFilter === 'all' ? 'selected' : '' ?>>All Modes</option>
          <?php foreach ($validPaymentModes as $pm): ?>
            <option value="<?= e($pm) ?>" <?= $modeFilter === $pm ? 'selected' : '' ?>><?= e($pm) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Service Filter -->
      <div style="min-width: 130px;">
        <select name="service" class="form-control" onchange="this.form.submit()" style="width: 100%; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; background: #ffffff;">
          <option value="all" <?= $serviceFilter === 'all' ? 'selected' : '' ?>>All Services</option>
          <?php foreach ($validServices as $sv): ?>
            <option value="<?= e($sv) ?>" <?= $serviceFilter === $sv ? 'selected' : '' ?>><?= e($sv) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="submit" class="btn-action" style="padding: 8px 14px; font-size: 12px; background: var(--border-light);">Filter</button>
      <?php if (!empty($searchQuery) || $statusFilter !== 'all' || $typeFilter !== 'all' || $modeFilter !== 'all' || $serviceFilter !== 'all'): ?>
        <a href="invoices.php" class="btn-action" style="padding: 8px 14px; font-size: 12px; color: #dc2626;">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Invoices Table Data Card -->
  <div class="data-card">
    <div class="data-card-header" style="display: flex; justify-content: space-between; align-items: center;">
      <div>
        <h2 class="data-card-title">All Invoices (<?= count($invoices) ?>)</h2>
        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
          Client invoices, project phases, payment allocations, and one-click PDF generation.
        </div>
      </div>
    </div>

    <div class="table-responsive">
      <?php if (empty($invoices)): ?>
        <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
          <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="margin: 0 auto 12px; color: #cbd5e1;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          <div style="font-size: 16px; font-weight: 600; color: var(--text-dark); margin-bottom: 6px;">No invoices found</div>
          <p style="font-size: 13px; max-width: 420px; margin: 0 auto 16px;">
            <?= (!empty($searchQuery) || $statusFilter !== 'all' || $serviceFilter !== 'all') ? 'Try adjusting your search criteria or reset filters.' : 'Click below to generate the first invoice for a client.' ?>
          </p>
          <button type="button" class="btn-primary-admin" onclick="openGenerateInvoiceModal()" style="font-size: 13px; padding: 8px 18px;">
            + Generate First Invoice
          </button>
        </div>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Invoice #</th>
              <th>Client</th>
              <th>Project</th>
              <th>Phase</th>
              <th>Type</th>
              <th>Invoice Amount</th>
              <th>Paid</th>
              <th>Balance</th>
              <th>Status</th>
              <th>Payment Mode</th>
              <th>Invoice Date</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($invoices as $inv): ?>
              <?php 
                $st = strtolower((string)$inv['status']);
                $pillClass = match ($st) {
                    'paid' => 'status-paid',
                    'partially paid' => 'status-partially-paid',
                    'pending' => 'status-pending',
                    'overdue' => 'status-overdue',
                    'draft' => 'status-draft',
                    default => 'status-inactive'
                };
                $invType = strtolower((string)($inv['invoice_type'] ?? 'full'));
                $amt = (float)($inv['amount'] ?? 0);
                $paidAmt = (float)($inv['amount_received'] ?? 0);
                $balAmt = isset($inv['balance_amount']) ? (float)$inv['balance_amount'] : max(0, $amt - $paidAmt);
              ?>
              <tr>
                <!-- Invoice # -->
                <td style="font-family: 'DM Mono', monospace; font-weight: 700; font-size: 12px; color: var(--text-dark);">
                  <a href="invoices.php?action=export_pdf&id=<?= (int)$inv['id'] ?>" title="Download PDF" style="color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                    <?= e($inv['invoice_number']) ?>
                  </a>
                </td>

                <!-- Client -->
                <td>
                  <strong style="color: var(--text-dark); font-size: 13px;"><?= e($inv['client_name']) ?></strong>
                  <?php if (!empty($inv['client_company']) && strtolower($inv['client_company']) !== strtolower($inv['client_name'])): ?>
                    <div style="font-size: 11px; color: var(--text-muted);"><?= e($inv['client_company']) ?></div>
                  <?php endif; ?>
                </td>

                <!-- Project -->
                <td>
                  <span style="font-size: 12px; color: var(--text-dark);">
                    <?= !empty($inv['project_name']) ? e($inv['project_name']) : '<span style="color:var(--text-muted); font-size:11px;">Not specified</span>' ?>
                  </span>
                </td>

                <!-- Phase -->
                <td>
                  <span style="font-size: 12px; color: var(--text-muted);">
                    <?= !empty($inv['project_phase']) ? e($inv['project_phase']) : '<span style="color:var(--text-muted); font-size:11px;">Not specified</span>' ?>
                  </span>
                </td>

                <!-- Type -->
                <td>
                  <?php if ($invType === 'advance'): ?>
                    <span style="background: rgba(200, 240, 60, 0.25); color: #14161A; border: 1px solid #C8F03C; padding: 2px 7px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase;">
                      Advance
                    </span>
                  <?php else: ?>
                    <span style="background: #F0F2F6; color: #5B6068; padding: 2px 7px; border-radius: 4px; font-size: 10px; font-weight: 600; text-transform: uppercase;">
                      Full
                    </span>
                  <?php endif; ?>
                </td>

                <!-- Invoice Amount -->
                <td style="font-family: 'DM Mono', monospace; font-weight: 700; font-size: 12px; color: var(--text-dark);">
                  ₹<?= number_format($amt, 2) ?>
                </td>

                <!-- Paid -->
                <td style="font-family: 'DM Mono', monospace; font-size: 12px; color: #1F5C4A; font-weight: 600;">
                  ₹<?= number_format($paidAmt, 2) ?>
                </td>

                <!-- Balance -->
                <td style="font-family: 'DM Mono', monospace; font-size: 12px; color: <?= $balAmt > 0 ? '#b45309' : 'var(--text-muted)' ?>; font-weight: 600;">
                  ₹<?= number_format($balAmt, 2) ?>
                </td>

                <!-- Status -->
                <td>
                  <span class="status-pill <?= $pillClass ?>">
                    <?= e(ucwords($inv['status'])) ?>
                  </span>
                </td>

                <!-- Payment Mode -->
                <td>
                  <span style="font-size: 11px; color: var(--text-dark);">
                    <?= !empty($inv['payment_mode']) ? e($inv['payment_mode']) : '—' ?>
                  </span>
                  <?php if (!empty($inv['transaction_reference'])): ?>
                    <div style="font-family: 'DM Mono', monospace; font-size: 10px; color: var(--text-muted);" title="UTR / Reference">
                      <?= e(substr((string)$inv['transaction_reference'], 0, 16)) ?>
                    </div>
                  <?php endif; ?>
                </td>

                <!-- Invoice Date -->
                <td style="font-family: 'DM Mono', monospace; font-size: 11px; color: var(--text-muted);">
                  <?= e(format_date($inv['created_at'], 'M j, Y')) ?>
                </td>

                <!-- Actions -->
                <td style="text-align: right; white-space: nowrap;">
                  <div style="display: inline-flex; gap: 5px; align-items: center;">
                    <!-- 1. Export PDF -->
                    <a href="invoices.php?action=export_pdf&id=<?= (int)$inv['id'] ?>" class="btn-action" title="Download PDF" style="color: #0284c7; background: #e0f2fe; padding: 5px 8px; font-size: 11px; font-weight: 600; text-decoration: none; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                      <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="stroke-width: 2.2;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                      </svg>
                      <span>PDF</span>
                    </a>

                    <!-- 2. View Modal -->
                    <button type="button" class="btn-action" title="View Invoice" onclick="viewInvoice(<?= htmlspecialchars(json_encode($inv), ENT_QUOTES, 'UTF-8') ?>)" style="padding: 5px 7px; font-size: 11px; border-radius: 6px;">
                      👁️
                    </button>

                    <!-- 3. Record Payment Action -->
                    <button type="button" class="btn-action" title="Record Payment" onclick="openRecordPaymentModal(<?= htmlspecialchars(json_encode($inv), ENT_QUOTES, 'UTF-8') ?>)" style="color: #15803d; background: #dcfce7; padding: 5px 8px; font-size: 11px; font-weight: 600; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                      <span>+ Pay</span>
                    </button>

                    <!-- 3. Edit Modal -->
                    <button type="button" class="btn-action" title="Edit Invoice" onclick="editInvoice(<?= htmlspecialchars(json_encode($inv), ENT_QUOTES, 'UTF-8') ?>)" style="padding: 5px 7px; font-size: 11px; border-radius: 6px;">
                      ✏️
                    </button>

                    <!-- 4. Delete Modal -->
                    <button type="button" class="btn-action" title="Delete Invoice" onclick="confirmDeleteInvoice(<?= (int)$inv['id'] ?>, '<?= e(addslashes($inv['invoice_number'])) ?>')" style="color: #dc2626; background: #fee2e2; padding: 5px 7px; font-size: 11px; border-radius: 6px;">
                      🗑️
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- =========================================================
       MODAL 1: GENERATE INVOICE (PROJECT-BASED WORKFLOW)
  ========================================================= -->
  <div id="generateInvoiceModal" class="modal-overlay" aria-hidden="true" style="display: none;">
    <div class="modal-dialog" style="max-width: 780px; max-height: 92vh; display: flex; flex-direction: column;">
      <form id="genInvoiceForm" method="POST" action="invoices.php" style="display: flex; flex-direction: column; overflow: hidden; height: 100%;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_invoice">
        <input type="hidden" name="invoice_type" id="genInvoiceTypeInput" value="advance">

        <div class="modal-header">
          <div>
            <div class="modal-title">Generate Client Invoice</div>
            <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
              Project-based billing, milestone schedules, payment tracking, and balance ledger.
            </div>
          </div>
          <button type="button" class="modal-close-btn" onclick="closeGenerateInvoiceModal()">✕</button>
        </div>

        <div class="modal-body" style="font-size: 13px; overflow-y: auto; max-height: calc(92vh - 140px); padding: 20px 24px;">

          <!-- SECTION 1: CLIENT & PROJECT -->
          <div class="inv-section-title">
            <span>Section 1 — Client &amp; Project</span>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px;">
            <!-- Client Selection -->
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Client / Account *
              </label>
              <select name="client_id" id="genClientId" class="form-control" onchange="onGenClientChanged()" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px; background: #ffffff;">
                <option value="0">— Select Existing Client or Enter Below —</option>
                <?php foreach ($clientsList as $c): ?>
                  <?php $dispName = !empty($c['company_name']) ? "{$c['company_name']} ({$c['client_name']})" : $c['client_name']; ?>
                  <option value="<?= (int)$c['id'] ?>" data-service="<?= e($c['service'] ?? '') ?>" data-name="<?= e($dispName) ?>">
                    <?= e($dispName) ?>
                  </option>
                <?php endforeach; ?>
                <option value="-1">+ Enter New / Custom Client Name</option>
              </select>
            </div>

            <!-- Project Selection -->
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Project *
              </label>
              <select name="project_id" id="genProjectId" class="form-control" onchange="onGenProjectChanged()" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px; background: #ffffff;">
                <option value="0">Project not specified</option>
                <option value="-1">+ Custom Project Name</option>
              </select>
            </div>
          </div>

          <!-- Hidden inputs for custom client/project names -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px;">
            <div class="form-group" id="genCustomClientWrap" style="display: none;">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Custom Client / Company Name *
              </label>
              <input type="text" name="client_name_custom" id="genClientNameCustom" placeholder="e.g. Acme Tech Solutions" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px;">
            </div>

            <div class="form-group" id="genCustomProjectWrap" style="display: none;">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Custom Project Name *
              </label>
              <input type="text" name="project_name_custom" id="genProjectNameCustom" placeholder="e.g. Enterprise CRM Portal" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px;">
            </div>
          </div>

          <!-- Project Phase Selection -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Project Phase *
              </label>
              <select name="project_phase" id="genProjectPhase" class="form-control" onchange="onGenPhaseChanged()" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px; background: #ffffff;">
                <option value="Phase 1">Phase 1</option>
                <option value="Phase 2">Phase 2</option>
                <option value="Phase 3">Phase 3</option>
                <option value="Phase 4">Phase 4</option>
                <option value="Phase 5">Phase 5</option>
                <option value="Custom Phase">Custom Phase</option>
              </select>
            </div>

            <div class="form-group" id="genCustomPhaseWrap" style="display: none;">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Custom Phase Name *
              </label>
              <input type="text" name="custom_phase" id="genCustomPhase" placeholder="e.g. Discovery &amp; Architectural Blueprint" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px;">
            </div>
          </div>

          <!-- SECTION 2: INVOICE DETAILS -->
          <div class="inv-section-title">
            <span>Section 2 — Invoice Details</span>
          </div>

          <!-- Prominent Invoice Type Selection -->
          <div class="inv-type-pill-group">
            <div id="genPillAdvance" class="inv-type-pill-label selected" onclick="setGenInvoiceType('advance')">
              <input type="radio" name="inv_type_radio" value="advance" checked style="accent-color: #14161A;">
              <div>
                <div>Advance Payment Invoice</div>
                <div style="font-size: 10px; color: #5B6068; font-weight: normal;">Initial project deposit / milestone advance</div>
              </div>
            </div>
            <div id="genPillFull" class="inv-type-pill-label" onclick="setGenInvoiceType('full')">
              <input type="radio" name="inv_type_radio" value="full" style="accent-color: #14161A;">
              <div>
                <div>Full Payment Invoice</div>
                <div style="font-size: 10px; color: #5B6068; font-weight: normal;">Complete agreed contract settlement</div>
              </div>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 14px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Invoice Reference # *
              </label>
              <input type="text" name="invoice_number" id="genInvoiceNumber" value="<?= e($suggestedInvoiceNumber) ?>" required class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace; font-size: 13px; font-weight: 700;">
            </div>

            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Service Category *
              </label>
              <select name="service" id="genService" required class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px; background: #ffffff;">
                <?php foreach ($validServices as $sv): ?>
                  <option value="<?= e($sv) ?>"><?= e($sv) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Invoice Date *
              </label>
              <input type="date" name="invoice_date" id="genInvoiceDate" value="<?= date('Y-m-d') ?>" required class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px;">
            </div>
          </div>

          <!-- SECTION 3: PROJECT FINANCIALS -->
          <div class="inv-section-title">
            <span>Section 3 — Project Financials</span>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 10px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Total Project Scope (₹) *
              </label>
              <div style="position: relative;">
                <span style="position: absolute; left: 10px; top: 8px; font-weight: 700; color: var(--text-muted); font-size: 13px;">₹</span>
                <input type="number" step="0.01" min="0" name="project_total" id="genProjectTotal" placeholder="100000.00" oninput="recalcGenFinancials()" class="form-control" style="width: 100%; padding: 8px 12px 8px 24px; border-radius: 8px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace; font-size: 13px; font-weight: 700;">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" id="genAmountLabel" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Advance Amount (₹) *
              </label>
              <div style="position: relative;">
                <span style="position: absolute; left: 10px; top: 8px; font-weight: 700; color: var(--text-muted); font-size: 13px;">₹</span>
                <input type="number" step="0.01" min="1" name="amount" id="genAmount" placeholder="30000.00" required oninput="recalcGenFinancials()" class="form-control" style="width: 100%; padding: 8px 12px 8px 24px; border-radius: 8px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace; font-size: 13px; font-weight: 700;">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" id="genAmountReceivedLabel" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Advance Received (₹)
              </label>
              <div style="position: relative;">
                <span style="position: absolute; left: 10px; top: 8px; font-weight: 700; color: var(--text-muted); font-size: 13px;">₹</span>
                <input type="number" step="0.01" min="0" name="amount_received" id="genAmountReceived" value="0.00" oninput="recalcGenFinancials()" class="form-control" style="width: 100%; padding: 8px 12px 8px 24px; border-radius: 8px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace; font-size: 13px; font-weight: 700;">
              </div>
            </div>
          </div>

          <!-- Real-time Financial Summary Box -->
          <div class="financial-summary-card">
            <div>
              <div class="fin-metric-label">Project Total</div>
              <div class="fin-metric-value" id="genSumProjectTotal">₹0.00</div>
            </div>
            <div>
              <div class="fin-metric-label" id="genSumThisInvoiceLabel">This Invoice</div>
              <div class="fin-metric-value" id="genSumThisInvoice">₹0.00</div>
            </div>
            <div>
              <div class="fin-metric-label">Total Received</div>
              <div class="fin-metric-value highlight-trust" id="genSumTotalReceived">₹0.00</div>
            </div>
            <div>
              <div class="fin-metric-label">Balance Remaining</div>
              <div class="fin-metric-value highlight-lime" id="genSumBalance">₹0.00</div>
            </div>
          </div>

          <!-- SECTION 4: PAYMENT DETAILS -->
          <div class="inv-section-title">
            <span>Section 4 — Payment Details</span>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 12px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Payment Status *
              </label>
              <select name="status" id="genStatus" required class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px; background: #ffffff;">
                <option value="pending" selected>Pending</option>
                <option value="partially paid">Partially Paid</option>
                <option value="paid">Paid</option>
                <option value="overdue">Overdue</option>
                <option value="draft">Draft</option>
                <option value="cancelled">Cancelled</option>
                <option value="refunded">Refunded</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Payment Mode *
              </label>
              <select name="payment_mode" id="genPaymentMode" onchange="onGenPaymentModeChanged()" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px; background: #ffffff;">
                <option value="UPI" selected>UPI</option>
                <option value="Bank Transfer">Bank Transfer</option>
                <option value="Cash">Cash</option>
                <option value="Card">Card</option>
                <option value="Other">Other</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Payment Due Date *
              </label>
              <input type="date" name="due_date" id="genDueDate" value="<?= date('Y-m-d', time() + 86400 * 14) ?>" required class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px;">
            </div>
          </div>

          <!-- Conditional Payment Mode Fields -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
            <!-- UTR / Transaction Reference -->
            <div class="form-group" id="genTxnRefWrap">
              <label class="form-label" id="genTxnRefLabel" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                UTR / Transaction Reference
              </label>
              <input type="text" name="transaction_reference" id="genTxnRef" placeholder="e.g. 123456789012" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace; font-size: 12px;">
            </div>

            <!-- Bank Name (Bank Transfer only) -->
            <div class="form-group" id="genBankNameWrap" style="display: none;">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Bank Name
              </label>
              <input type="text" name="bank_name" id="genBankName" placeholder="e.g. HDFC Bank, ICICI Bank" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px;">
            </div>

            <!-- Payment Method Description (Other only) -->
            <div class="form-group" id="genPmDescWrap" style="display: none;">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Payment Method Description
              </label>
              <input type="text" name="payment_method_desc" id="genPmDesc" placeholder="e.g. Cheque #49201, Wire Transfer" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px;">
            </div>

            <!-- Payment Date (or Advance Received Date) -->
            <div class="form-group" id="genPaymentDateWrap">
              <label class="form-label" id="genPaymentDateLabel" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Advance Received Date
              </label>
              <input type="date" name="payment_date" id="genPaymentDate" value="<?= date('Y-m-d') ?>" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px;">
            </div>
          </div>

          <!-- SECTION 5: STRUCTURED LINE ITEMS -->
          <div class="inv-section-title">
            <span>Section 5 — Line Items</span>
            <button type="button" class="btn-action" onclick="addLineItemRow('genLineItemsBody')" style="font-size: 11px; padding: 4px 10px; background: #FFFFFF; border: 1px solid #E4E1D9;">
              + Add Line Item
            </button>
          </div>

          <table class="line-items-table">
            <thead>
              <tr>
                <th style="width: 45%;">Description</th>
                <th style="width: 15%;">Qty</th>
                <th style="width: 20%;">Rate (₹)</th>
                <th style="width: 15%;">Amount (₹)</th>
                <th style="width: 5%; text-align: center;"></th>
              </tr>
            </thead>
            <tbody id="genLineItemsBody">
              <tr>
                <td><input type="text" name="line_items_desc[]" placeholder="e.g. UI/UX Interface Design" class="form-control" style="width: 100%; padding: 6px 8px; font-size: 12px; border-radius: 6px; border: 1px solid var(--border-light);"></td>
                <td><input type="number" name="line_items_qty[]" value="1" step="0.01" min="0.01" oninput="recalcRow(this)" class="form-control line-qty" style="width: 100%; padding: 6px 8px; font-size: 12px; border-radius: 6px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace;"></td>
                <td><input type="number" name="line_items_rate[]" value="0.00" step="0.01" min="0" oninput="recalcRow(this)" class="form-control line-rate" style="width: 100%; padding: 6px 8px; font-size: 12px; border-radius: 6px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace;"></td>
                <td><input type="text" readonly name="line_items_amt[]" value="0.00" class="form-control line-amt" style="width: 100%; padding: 6px 8px; font-size: 12px; border-radius: 6px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace; background: #F8FAFC; font-weight: 600;"></td>
                <td style="text-align: center;"><button type="button" class="btn-remove-line" onclick="removeLineItemRow(this)">✕</button></td>
              </tr>
            </tbody>
          </table>

          <div style="display: flex; justify-content: space-between; align-items: center; margin: 10px 0 16px 0; padding-top: 8px; border-top: 1px dashed #E4E1D9;">
            <button type="button" onclick="syncLineItemsTotalToAmount('gen')" class="btn-action" style="font-size: 11px; padding: 4px 10px; background: #F6F4EF; border: 1px solid #E4E1D9;">
              Copy Line Items Total to Invoice Amount
            </button>
            <div style="font-size: 12px; font-weight: 600; color: var(--text-dark);">
              Line Items Total: <span id="genLineItemsTotal" style="font-family: 'DM Mono', monospace; font-weight: 700;">₹0.00</span>
            </div>
          </div>

          <!-- SECTION 6: ADDITIONAL NOTES -->
          <div class="inv-section-title">
            <span>Section 6 — Additional Notes</span>
          </div>

          <div class="form-group" style="margin-bottom: 6px;">
            <textarea name="notes" id="genNotes" rows="2" placeholder="e.g. Project milestones, payment terms, deliverable scope or bank wire instructions." class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; line-height: 1.5; resize: vertical;"></textarea>
          </div>
        </div>

        <div class="modal-footer" style="padding: 14px 24px; border-top: 1px solid var(--border-light); background: #fafafc; display: flex; justify-content: flex-end; gap: 10px;">
          <button type="button" class="btn-action" onclick="closeGenerateInvoiceModal()" style="background: var(--main-bg);">Cancel</button>
          <button type="submit" id="genSubmitBtn" class="btn-primary-admin" style="padding: 9px 22px; font-size: 13px;">
            Generate &amp; Save Invoice
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- =========================================================
       MODAL 2: VIEW INVOICE PREVIEW
  ========================================================= -->
  <div id="viewInvoiceModal" class="modal-overlay" aria-hidden="true" style="display: none;">
    <div class="modal-dialog" style="max-width: 740px; max-height: 92vh; display: flex; flex-direction: column;">
      <div class="modal-header">
        <div>
          <div class="modal-title" id="viewInvTitle">Invoice Details</div>
          <div id="viewInvSubtitle" style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"></div>
        </div>
        <button type="button" class="modal-close-btn" onclick="closeViewInvoiceModal()">✕</button>
      </div>

      <div class="modal-body" style="font-size: 13px; overflow-y: auto; max-height: calc(92vh - 140px); padding: 20px 24px;">
        <!-- Header Banner with Financial Summary -->
        <div style="background: #0F1115; color: #ffffff; padding: 18px 22px; border-radius: 10px; margin-bottom: 18px; border: 1px solid #222430;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px;">
            <div>
              <div style="font-size: 18px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px;">Website Tailors Digital</div>
              <div style="font-size: 11px; color: #94A3B8; margin-top: 2px;">Bangalore Studio, Karnataka 560010 — websietailorss@gmail.com</div>
            </div>
            <div style="text-align: right;">
              <span id="viewInvTypeBadge" style="display: inline-block; padding: 3px 8px; border-radius: 4px; font-weight: 700; font-size: 10px; text-transform: uppercase; background: #C8F03C; color: #14161A;"></span>
              <span id="viewInvStatusBadge" style="display: inline-block; padding: 3px 8px; border-radius: 4px; font-weight: 700; font-size: 10px; text-transform: uppercase; margin-left: 6px;"></span>
            </div>
          </div>

          <!-- 4-Column Financial Hierarchy -->
          <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; padding-top: 12px; border-top: 1px solid #222430;">
            <div>
              <div class="fin-metric-label">Project Total</div>
              <div class="fin-metric-value" id="viewInvProjectTotal">₹0.00</div>
            </div>
            <div>
              <div class="fin-metric-label">This Invoice</div>
              <div class="fin-metric-value" id="viewInvAmount">₹0.00</div>
            </div>
            <div>
              <div class="fin-metric-label">Total Received</div>
              <div class="fin-metric-value highlight-trust" id="viewInvReceived">₹0.00</div>
            </div>
            <div>
              <div class="fin-metric-label">Balance Remaining</div>
              <div class="fin-metric-value highlight-lime" id="viewInvBalance">₹0.00</div>
            </div>
          </div>
        </div>

        <!-- Project & Client Context Grid -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
          <div>
            <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">Billed To</span>
            <div id="viewInvClient" style="font-weight: 700; color: var(--text-dark); font-size: 14px; margin-top: 2px;"></div>
          </div>
          <div>
            <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">Project &amp; Phase</span>
            <div id="viewInvProjectPhase" style="font-weight: 600; color: var(--text-dark); margin-top: 2px;"></div>
          </div>
          <div>
            <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">Service Category</span>
            <div id="viewInvService" style="font-weight: 600; color: var(--text-dark); margin-top: 2px;"></div>
          </div>
          <div>
            <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">Payment Mode &amp; Reference</span>
            <div id="viewInvPaymentMode" style="font-weight: 600; color: var(--text-dark); margin-top: 2px;"></div>
          </div>
          <div>
            <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">Issue Date</span>
            <div id="viewInvIssueDate" style="font-family: 'DM Mono', monospace; color: var(--text-dark); margin-top: 2px;"></div>
          </div>
          <div>
            <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">Payment Due Date</span>
            <div id="viewInvDueDate" style="font-family: 'DM Mono', monospace; color: var(--text-dark); margin-top: 2px;"></div>
          </div>
        </div>

        <!-- Payment History Section -->
        <div id="viewInvPaymentHistorySection" style="margin-bottom: 16px;">
          <div class="inv-section-title" style="margin-top: 0;">
            <span>Payment History</span>
            <span id="viewInvPaymentCountBadge" style="font-size: 11px; text-transform: none; font-weight: 600; color: #5B6068;"></span>
          </div>
          <div id="viewInvPaymentHistoryContainer"></div>
        </div>

        <!-- Line Items Section -->
        <div style="margin-bottom: 16px;">
          <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px;">Structured Line Items</span>
          <div id="viewInvLineItemsContainer" style="background: #FAFAFC; border: 1px solid var(--border-light); border-radius: 8px; padding: 10px 14px;"></div>
        </div>

        <!-- Additional Notes -->
        <div style="margin-bottom: 16px;">
          <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">Additional Notes</span>
          <div id="viewInvNotes" style="background: var(--main-bg); padding: 10px 12px; border-radius: 8px; font-size: 12px; color: var(--text-dark); margin-top: 4px; line-height: 1.5;"></div>
        </div>

        <div style="border-top: 1px solid var(--border-light); padding-top: 12px; font-size: 11px; color: var(--text-muted); display: flex; justify-content: space-between;">
          <span>Studio Tel: +91 9380552034</span>
          <span>Payment via: HDFC Bank / 9380552034@upi</span>
        </div>
      </div>

      <div class="modal-footer" style="padding: 14px 24px; border-top: 1px solid var(--border-light); background: #fafafc; display: flex; justify-content: space-between;">
        <button type="button" class="btn-action" onclick="closeViewInvoiceModal()" style="background: var(--main-bg);">Close</button>
        <div style="display: flex; gap: 8px;">
          <button type="button" id="viewInvRecordPaymentBtn" class="btn-primary-admin" onclick="openRecordPaymentFromView()" style="background: #1F5C4A; padding: 8px 18px; font-size: 13px; font-weight: 600; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; border: none;">
            <span>💳 Record Payment</span>
          </button>
          <a id="viewInvPdfBtn" href="#" class="btn-primary-admin" style="padding: 8px 18px; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="stroke-width: 2.2;">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            <span>Download PDF</span>
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- =========================================================
       MODAL 3: EDIT INVOICE MODAL
  ========================================================= -->
  <div id="editInvoiceModal" class="modal-overlay" aria-hidden="true" style="display: none;">
    <div class="modal-dialog" style="max-width: 780px; max-height: 92vh; display: flex; flex-direction: column;">
      <form id="editInvoiceForm" method="POST" action="invoices.php" style="display: flex; flex-direction: column; overflow: hidden; height: 100%;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="edit_invoice">
        <input type="hidden" name="invoice_id" id="editInvoiceId">
        <input type="hidden" name="invoice_type" id="editInvoiceTypeInput" value="full">

        <div class="modal-header">
          <div>
            <div class="modal-title">Edit Invoice</div>
            <div id="editInvoiceSubtitle" style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"></div>
          </div>
          <button type="button" class="modal-close-btn" onclick="closeEditInvoiceModal()">✕</button>
        </div>

        <div class="modal-body" style="font-size: 13px; overflow-y: auto; max-height: calc(92vh - 140px); padding: 20px 24px;">

          <!-- SECTION 1: CLIENT & PROJECT -->
          <div class="inv-section-title">
            <span>Client &amp; Project</span>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Client / Account
              </label>
              <select name="client_id" id="editClientId" class="form-control" onchange="onEditClientChanged()" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px; background: #ffffff;">
                <option value="0">Not specified</option>
                <?php foreach ($clientsList as $c): ?>
                  <?php $dispName = !empty($c['company_name']) ? "{$c['company_name']} ({$c['client_name']})" : $c['client_name']; ?>
                  <option value="<?= (int)$c['id'] ?>"><?= e($dispName) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Project
              </label>
              <select name="project_id" id="editProjectId" class="form-control" onchange="onEditProjectChanged()" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px; background: #ffffff;">
                <option value="0">Project not specified</option>
                <option value="-1">+ Custom Project</option>
              </select>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Project Phase
              </label>
              <select name="project_phase" id="editProjectPhase" onchange="onEditPhaseChanged()" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px; background: #ffffff;">
                <option value="">Not specified</option>
                <option value="Phase 1">Phase 1</option>
                <option value="Phase 2">Phase 2</option>
                <option value="Phase 3">Phase 3</option>
                <option value="Phase 4">Phase 4</option>
                <option value="Phase 5">Phase 5</option>
                <option value="Custom Phase">Custom Phase</option>
              </select>
            </div>

            <div class="form-group" id="editCustomPhaseWrap" style="display: none;">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Custom Phase Name
              </label>
              <input type="text" name="custom_phase" id="editCustomPhase" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px;">
            </div>
          </div>

          <!-- SECTION 2: INVOICE & FINANCIALS -->
          <div class="inv-section-title">
            <span>Invoice &amp; Financials</span>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 12px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Invoice Type *
              </label>
              <select name="invoice_type_select" id="editInvoiceTypeSelect" onchange="onEditInvoiceTypeChanged()" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px; background: #ffffff;">
                <option value="advance">Advance Payment</option>
                <option value="full">Full Payment</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Service Category *
              </label>
              <select name="service" id="editService" required class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px; background: #ffffff;">
                <?php foreach ($validServices as $sv): ?>
                  <option value="<?= e($sv) ?>"><?= e($sv) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Payment Due Date *
              </label>
              <input type="date" name="due_date" id="editDueDate" required class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px;">
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 10px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Project Total (₹) *
              </label>
              <input type="number" step="0.01" min="0" name="project_total" id="editProjectTotal" oninput="recalcEditFinancials()" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace; font-size: 13px; font-weight: 700;">
            </div>

            <div class="form-group">
              <label class="form-label" id="editAmountLabel" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Invoice Amount (₹) *
              </label>
              <input type="number" step="0.01" min="1" name="amount" id="editAmount" required oninput="recalcEditFinancials()" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace; font-size: 13px; font-weight: 700;">
            </div>

            <div class="form-group">
              <label class="form-label" id="editAmountReceivedLabel" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Amount Received (₹)
              </label>
              <input type="number" step="0.01" min="0" name="amount_received" id="editAmountReceived" oninput="recalcEditFinancials()" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace; font-size: 13px; font-weight: 700;">
            </div>
          </div>

          <!-- Edit Financial Summary Card -->
          <div class="financial-summary-card">
            <div>
              <div class="fin-metric-label">Project Total</div>
              <div class="fin-metric-value" id="editSumProjectTotal">₹0.00</div>
            </div>
            <div>
              <div class="fin-metric-label">This Invoice</div>
              <div class="fin-metric-value" id="editSumThisInvoice">₹0.00</div>
            </div>
            <div>
              <div class="fin-metric-label">Total Received</div>
              <div class="fin-metric-value highlight-trust" id="editSumTotalReceived">₹0.00</div>
            </div>
            <div>
              <div class="fin-metric-label">Balance Remaining</div>
              <div class="fin-metric-value highlight-lime" id="editSumBalance">₹0.00</div>
            </div>
          </div>

          <!-- SECTION 3: PAYMENT DETAILS -->
          <div class="inv-section-title">
            <span>Payment Details</span>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Payment Status *
              </label>
              <select name="status" id="editStatus" required class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px; background: #ffffff;">
                <option value="pending">Pending</option>
                <option value="partially paid">Partially Paid</option>
                <option value="paid">Paid</option>
                <option value="overdue">Overdue</option>
                <option value="draft">Draft</option>
                <option value="cancelled">Cancelled</option>
                <option value="refunded">Refunded</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Payment Mode
              </label>
              <select name="payment_mode" id="editPaymentMode" onchange="onEditPaymentModeChanged()" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px; background: #ffffff;">
                <option value="UPI">UPI</option>
                <option value="Bank Transfer">Bank Transfer</option>
                <option value="Cash">Cash</option>
                <option value="Card">Card</option>
                <option value="Other">Other</option>
              </select>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px;">
            <div class="form-group" id="editTxnRefWrap">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                UTR / Reference #
              </label>
              <input type="text" name="transaction_reference" id="editTxnRef" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace; font-size: 12px;">
            </div>

            <div class="form-group" id="editBankNameWrap" style="display: none;">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Bank Name
              </label>
              <input type="text" name="bank_name" id="editBankName" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px;">
            </div>

            <div class="form-group" id="editPmDescWrap" style="display: none;">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Payment Method Description
              </label>
              <input type="text" name="payment_method_desc" id="editPmDesc" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px;">
            </div>

            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Payment Date
              </label>
              <input type="date" name="payment_date" id="editPaymentDate" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px;">
            </div>
          </div>

          <!-- SECTION 4: LINE ITEMS -->
          <div class="inv-section-title">
            <span>Structured Line Items</span>
            <button type="button" class="btn-action" onclick="addLineItemRow('editLineItemsBody')" style="font-size: 11px; padding: 4px 10px; background: #FFFFFF; border: 1px solid #E4E1D9;">
              + Add Line Item
            </button>
          </div>

          <table class="line-items-table">
            <thead>
              <tr>
                <th style="width: 45%;">Description</th>
                <th style="width: 15%;">Qty</th>
                <th style="width: 20%;">Rate (₹)</th>
                <th style="width: 15%;">Amount (₹)</th>
                <th style="width: 5%; text-align: center;"></th>
              </tr>
            </thead>
            <tbody id="editLineItemsBody">
            </tbody>
          </table>

          <div style="display: flex; justify-content: space-between; align-items: center; margin: 10px 0 16px 0; padding-top: 8px; border-top: 1px dashed #E4E1D9;">
            <button type="button" onclick="syncLineItemsTotalToAmount('edit')" class="btn-action" style="font-size: 11px; padding: 4px 10px; background: #F6F4EF; border: 1px solid #E4E1D9;">
              Copy Line Items Total to Invoice Amount
            </button>
            <div style="font-size: 12px; font-weight: 600; color: var(--text-dark);">
              Line Items Total: <span id="editLineItemsTotal" style="font-family: 'DM Mono', monospace; font-weight: 700;">₹0.00</span>
            </div>
          </div>

          <!-- SECTION 5: NOTES -->
          <div class="inv-section-title">
            <span>Additional Notes</span>
          </div>

          <div class="form-group" style="margin-bottom: 6px;">
            <textarea name="notes" id="editNotes" rows="2" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; line-height: 1.5; resize: vertical;"></textarea>
          </div>
        </div>

        <div class="modal-footer" style="padding: 14px 24px; border-top: 1px solid var(--border-light); background: #fafafc; display: flex; justify-content: flex-end; gap: 10px;">
          <button type="button" class="btn-action" onclick="closeEditInvoiceModal()" style="background: var(--main-bg);">Cancel</button>
          <button type="submit" id="editSubmitBtn" class="btn-primary-admin" style="padding: 9px 22px; font-size: 13px;">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <!-- =========================================================
       MODAL 4: RECORD PAYMENT (PHASE 5 WORKFLOW)
  ========================================================= -->
  <div id="recordPaymentModal" class="modal-overlay" aria-hidden="true" style="display: none;">
    <div class="modal-dialog" style="max-width: 600px; max-height: 92vh; display: flex; flex-direction: column;">
      <form id="recordPaymentForm" method="POST" action="invoices.php" onsubmit="return handleRecordPaymentSubmit(event)" style="display: flex; flex-direction: column; overflow: hidden; height: 100%;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="record_payment">
        <input type="hidden" name="invoice_id" id="payInvoiceId" value="0">
        <input type="hidden" name="client_id" id="payClientId" value="0">
        <input type="hidden" name="project_id" id="payProjectId" value="0">

        <div class="modal-header">
          <div>
            <div class="modal-title">Record Payment</div>
            <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
              Log partial settlement, milestone receipt, or full invoice payment into the revenue ledger.
            </div>
          </div>
          <button type="button" class="modal-close-btn" onclick="closeRecordPaymentModal()">✕</button>
        </div>

        <div class="modal-body" style="font-size: 13px; overflow-y: auto; max-height: calc(92vh - 140px); padding: 20px 24px;">

          <!-- READ-ONLY CONTEXT CARD -->
          <div style="background: #0F1115; color: #FFFFFF; border-radius: 10px; padding: 16px 20px; margin-bottom: 18px; border: 1px solid #222430;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
              <div>
                <div style="font-size: 11px; text-transform: uppercase; color: #94A3B8; letter-spacing: 0.5px;">Billed To</div>
                <div id="payContextClient" style="font-weight: 700; font-size: 14px; color: #FFFFFF; margin-top: 2px;">—</div>
                <div id="payContextProjectPhase" style="font-size: 12px; color: #CBD5E1; margin-top: 2px;">—</div>
              </div>
              <div style="text-align: right;">
                <div id="payContextInvoiceNum" style="font-family: 'DM Mono', monospace; font-size: 13px; font-weight: 700; color: #FFFFFF;">INV-000</div>
                <span id="payContextTypeBadge" style="display: inline-block; padding: 2px 7px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; background: #C8F03C; color: #14161A; margin-top: 4px;">Advance</span>
              </div>
            </div>

            <!-- Financial Metrics Grid -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; padding-top: 10px; border-top: 1px solid #222430;">
              <div>
                <div class="fin-metric-label">Invoice Amount</div>
                <div class="fin-metric-value" id="payContextAmount">₹0.00</div>
              </div>
              <div>
                <div class="fin-metric-label">Total Received</div>
                <div class="fin-metric-value highlight-trust" id="payContextReceived">₹0.00</div>
              </div>
              <div>
                <div class="fin-metric-label">Remaining Balance</div>
                <div class="fin-metric-value highlight-lime" id="payContextRemaining">₹0.00</div>
              </div>
            </div>
          </div>

          <!-- PAYMENT INPUTS -->
          <div class="inv-section-title">
            <span>Payment Details</span>
          </div>

          <!-- Payment Amount & Quick Fill -->
          <div class="form-group" style="margin-bottom: 14px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin: 0;">
                Payment Amount (₹) *
              </label>
              <button type="button" onclick="fillFullRemainingPayment()" style="background: none; border: none; color: #1F5C4A; font-size: 11px; font-weight: 600; cursor: pointer; text-decoration: underline; padding: 0;">
                Pay Full Balance (<span id="payBtnFillRemaining">₹0.00</span>)
              </button>
            </div>
            <div style="position: relative;">
              <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-family: 'DM Mono', monospace; font-weight: 700; color: #5B6068; font-size: 14px;">₹</span>
              <input type="number" step="0.01" min="0.01" name="amount" id="payAmountInput" required oninput="onPayAmountChanged()" placeholder="0.00" class="form-control" style="width: 100%; padding: 10px 12px 10px 28px; border-radius: 8px; border: 1.5px solid var(--border-light); font-size: 15px; font-family: 'DM Mono', monospace; font-weight: 700; background: #ffffff;">
            </div>
            <div id="payBalanceFeedback" style="font-size: 12px; margin-top: 6px; color: #5B6068; display: flex; justify-content: space-between;">
              <span>Remaining after payment: <strong id="payNewRemaining" style="font-family: 'DM Mono', monospace; color: #14161A;">₹0.00</strong></span>
              <span id="payExceedsWarning" style="color: #dc2626; font-weight: 600; display: none;">⚠️ Exceeds remaining balance!</span>
            </div>
          </div>

          <!-- Payment Date & Mode -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Payment Date *
              </label>
              <input type="date" name="payment_date" id="payDateInput" required value="<?= date('Y-m-d') ?>" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px; background: #ffffff;">
            </div>

            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
                Payment Mode *
              </label>
              <select name="payment_mode" id="payModeSelect" required onchange="onPayModeChanged()" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px; background: #ffffff;">
                <option value="UPI">UPI</option>
                <option value="Bank Transfer">Bank Transfer</option>
                <option value="Cash">Cash</option>
                <option value="Card">Card</option>
                <option value="Other">Other</option>
              </select>
            </div>
          </div>

          <!-- Conditional Payment Fields -->
          <!-- Container: UTR / Reference -->
          <div id="payUtrContainer" class="form-group" style="margin-bottom: 14px;">
            <label id="payUtrLabel" class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
              UTR / Transaction Reference *
            </label>
            <input type="text" name="transaction_reference" id="payTxnRefInput" placeholder="e.g. 238910482910 or UPI Ref ID" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px; font-family: 'DM Mono', monospace; background: #ffffff;">
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Used for automatic bank statement reconciliation and duplicate prevention.</div>
          </div>

          <!-- Container: Bank Name (for Bank Transfer) -->
          <div id="payBankContainer" class="form-group" style="margin-bottom: 14px; display: none;">
            <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
              Bank Name *
            </label>
            <input type="text" name="bank_name" id="payBankNameInput" placeholder="e.g. HDFC Bank, ICICI Bank, SBI" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px; background: #ffffff;">
          </div>

          <!-- Container: Payment Method Description (for Other) -->
          <div id="payOtherDescContainer" class="form-group" style="margin-bottom: 14px; display: none;">
            <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
              Payment Method Description *
            </label>
            <input type="text" name="payment_method_desc" id="payOtherDescInput" placeholder="e.g. Cheque #492019, International Wire, Escrow" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px; background: #ffffff;">
          </div>

          <!-- Notes -->
          <div class="form-group" style="margin-bottom: 10px;">
            <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 4px; display: block;">
              Notes / Ledger Memo (Optional)
            </label>
            <textarea name="notes" id="payNotesInput" rows="2" placeholder="e.g. Milestone 2 received against scope deliverables..." class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px; background: #ffffff;"></textarea>
          </div>

        </div>

        <div class="modal-footer" style="padding: 14px 24px; border-top: 1px solid var(--border-light); background: #fafafc; display: flex; justify-content: space-between; align-items: center;">
          <button type="button" class="btn-action" onclick="closeRecordPaymentModal()" style="background: var(--main-bg);">Cancel</button>
          <button type="submit" id="paySubmitBtn" class="btn-primary-admin" style="background: #1F5C4A; color: #ffffff; padding: 9px 22px; font-size: 13px; font-weight: 700; border-radius: 8px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
            <span>Record Payment</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- =========================================================
       MODAL 5: DELETE CONFIRMATION MODAL
  ========================================================= -->
  <div id="deleteInvoiceModal" class="modal-overlay" aria-hidden="true" style="display: none;">
    <div class="modal-dialog" style="max-width: 440px;">
      <form method="POST" action="invoices.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_invoice">
        <input type="hidden" name="invoice_id" id="deleteInvoiceId">

        <div class="modal-header">
          <div class="modal-title" style="color: #dc2626;">Confirm Invoice Deletion</div>
          <button type="button" class="modal-close-btn" onclick="closeDeleteInvoiceModal()">✕</button>
        </div>

        <div class="modal-body" style="font-size: 13px; color: var(--text-dark); line-height: 1.5;">
          Are you sure you want to permanently delete <strong id="deleteInvoiceNumText">this invoice</strong>?
          <div style="margin-top: 8px; font-size: 12px; color: var(--text-muted);">
            This record will be permanently removed from your billing ledger and will not reoccur.
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-action" onclick="closeDeleteInvoiceModal()" style="background: var(--main-bg);">Cancel</button>
          <button type="submit" class="btn-primary-admin" style="background: #dc2626; color: #ffffff; padding: 8px 18px; font-size: 13px;">
            Permanently Delete
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Embedded Data Stores
    const CLIENT_PROJECTS = <?= json_encode($clientProjectsMap, JSON_UNESCAPED_UNICODE) ?>;
    const PROJECT_PAYMENTS = <?= json_encode($projectPaymentsMap, JSON_UNESCAPED_UNICODE) ?>;

    function formatCurrency(num) {
      return '₹' + Number(num || 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    }

    // -------------------------------------------------------------
    // MODAL CONTROL
    // -------------------------------------------------------------
    function openGenerateInvoiceModal() {
      const m = document.getElementById('generateInvoiceModal');
      m.style.display = 'flex';
      m.setAttribute('aria-hidden', 'false');
      recalcGenFinancials();
    }

    function closeGenerateInvoiceModal() {
      const m = document.getElementById('generateInvoiceModal');
      m.style.display = 'none';
      m.setAttribute('aria-hidden', 'true');
    }

    // -------------------------------------------------------------
    // GENERATE INVOICE REACTIVITY
    // -------------------------------------------------------------
    function setGenInvoiceType(type) {
      document.getElementById('genInvoiceTypeInput').value = type;
      const pillAdv = document.getElementById('genPillAdvance');
      const pillFull = document.getElementById('genPillFull');

      if (type === 'advance') {
        pillAdv.classList.add('selected');
        pillFull.classList.remove('selected');
        pillAdv.querySelector('input').checked = true;
        document.getElementById('genAmountLabel').textContent = 'Advance Amount (₹) *';
        document.getElementById('genAmountReceivedLabel').textContent = 'Advance Received (₹)';
        document.getElementById('genPaymentDateLabel').textContent = 'Advance Received Date';
        document.getElementById('genSumThisInvoiceLabel').textContent = 'Advance Invoice';
      } else {
        pillFull.classList.add('selected');
        pillAdv.classList.remove('selected');
        pillFull.querySelector('input').checked = true;
        document.getElementById('genAmountLabel').textContent = 'Invoice Amount (₹) *';
        document.getElementById('genAmountReceivedLabel').textContent = 'Amount Received (₹)';
        document.getElementById('genPaymentDateLabel').textContent = 'Payment Date';
        document.getElementById('genSumThisInvoiceLabel').textContent = 'Full Invoice';

        // If amount is empty but project total is entered, auto-fill
        const projTot = parseFloat(document.getElementById('genProjectTotal').value) || 0;
        const curAmt = parseFloat(document.getElementById('genAmount').value) || 0;
        if (projTot > 0 && curAmt === 0) {
          document.getElementById('genAmount').value = projTot.toFixed(2);
        }
      }
      recalcGenFinancials();
    }

    function onGenClientChanged() {
      const clientSel = document.getElementById('genClientId');
      const customWrap = document.getElementById('genCustomClientWrap');
      const projSel = document.getElementById('genProjectId');
      const serviceSel = document.getElementById('genService');
      const cid = parseInt(clientSel.value, 10);

      // Handle custom client
      if (cid === -1) {
        customWrap.style.display = 'block';
        document.getElementById('genClientNameCustom').focus();
      } else {
        customWrap.style.display = 'none';
        const opt = clientSel.options[clientSel.selectedIndex];
        const sVal = opt.getAttribute('data-service');
        if (sVal) serviceSel.value = sVal;
      }

      // Populate projects for this client
      projSel.innerHTML = '';
      const defOpt = document.createElement('option');
      defOpt.value = '0';
      defOpt.textContent = 'Project not specified';
      projSel.appendChild(defOpt);

      if (cid > 0 && CLIENT_PROJECTS[cid] && CLIENT_PROJECTS[cid].length > 0) {
        CLIENT_PROJECTS[cid].forEach(p => {
          const opt = document.createElement('option');
          opt.value = p.id;
          opt.textContent = `${p.title} (${p.status})`;
          projSel.appendChild(opt);
        });
      }

      const customOpt = document.createElement('option');
      customOpt.value = '-1';
      customOpt.textContent = '+ Custom Project Name';
      projSel.appendChild(customOpt);

      onGenProjectChanged();
    }

    function onGenProjectChanged() {
      const projSel = document.getElementById('genProjectId');
      const customWrap = document.getElementById('genCustomProjectWrap');
      const val = parseInt(projSel.value, 10);

      if (val === -1) {
        customWrap.style.display = 'block';
        document.getElementById('genProjectNameCustom').focus();
      } else {
        customWrap.style.display = 'none';
      }
      recalcGenFinancials();
    }

    function onGenPhaseChanged() {
      const phaseVal = document.getElementById('genProjectPhase').value;
      const customWrap = document.getElementById('genCustomPhaseWrap');
      if (phaseVal === 'Custom Phase') {
        customWrap.style.display = 'block';
        document.getElementById('genCustomPhase').focus();
      } else {
        customWrap.style.display = 'none';
      }
    }

    function onGenPaymentModeChanged() {
      const mode = document.getElementById('genPaymentMode').value;
      const txnWrap = document.getElementById('genTxnRefWrap');
      const bankWrap = document.getElementById('genBankNameWrap');
      const descWrap = document.getElementById('genPmDescWrap');

      txnWrap.style.display = (mode === 'Cash') ? 'none' : 'block';
      bankWrap.style.display = (mode === 'Bank Transfer') ? 'block' : 'none';
      descWrap.style.display = (mode === 'Other') ? 'block' : 'none';
    }

    function recalcGenFinancials() {
      const projTotal = parseFloat(document.getElementById('genProjectTotal').value) || 0;
      const invoiceAmt = parseFloat(document.getElementById('genAmount').value) || 0;
      const amtReceived = parseFloat(document.getElementById('genAmountReceived').value) || 0;
      const projId = parseInt(document.getElementById('genProjectId').value, 10);

      // Prior payments for this project
      const priorPaid = (projId > 0 && PROJECT_PAYMENTS[projId]) ? PROJECT_PAYMENTS[projId] : 0;
      const totalReceived = priorPaid + amtReceived;

      // Balance
      let balance = 0;
      if (projTotal > 0) {
        balance = Math.max(0, projTotal - totalReceived);
      } else {
        balance = Math.max(0, invoiceAmt - amtReceived);
      }

      // Auto-suggest status if not locked
      const statusSel = document.getElementById('genStatus');
      const curStatus = statusSel.value;
      if (!['cancelled', 'refunded', 'draft'].includes(curStatus)) {
        if (amtReceived <= 0) {
          statusSel.value = 'pending';
        } else if (amtReceived > 0 && amtReceived < invoiceAmt) {
          statusSel.value = 'partially paid';
        } else {
          statusSel.value = 'paid';
        }
      }

      // Update financial summary
      document.getElementById('genSumProjectTotal').textContent = formatCurrency(projTotal > 0 ? projTotal : invoiceAmt);
      document.getElementById('genSumThisInvoice').textContent = formatCurrency(invoiceAmt);
      document.getElementById('genSumTotalReceived').textContent = formatCurrency(totalReceived);
      document.getElementById('genSumBalance').textContent = formatCurrency(balance);
    }

    // -------------------------------------------------------------
    // LINE ITEMS ENGINE
    // -------------------------------------------------------------
    function addLineItemRow(targetTbodyId, itemData = null) {
      const tbody = document.getElementById(targetTbodyId);
      const tr = document.createElement('tr');
      const escapeAttr = (value) => String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
      const desc = itemData ? (itemData.description || '') : '';
      const qty = itemData ? (itemData.quantity || 1) : 1;
      const rate = itemData ? (itemData.rate || 0) : 0;
      const amt = itemData && itemData.amount !== undefined
        ? Number(itemData.amount || 0).toFixed(2)
        : (qty * rate).toFixed(2);

      tr.innerHTML = `
        <td><input type="text" name="line_items_desc[]" value="${escapeAttr(desc)}" placeholder="Description of service/deliverable" class="form-control" style="width: 100%; padding: 6px 8px; font-size: 12px; border-radius: 6px; border: 1px solid var(--border-light);"></td>
        <td><input type="number" name="line_items_qty[]" value="${qty}" step="0.01" min="0.01" oninput="recalcRow(this)" class="form-control line-qty" style="width: 100%; padding: 6px 8px; font-size: 12px; border-radius: 6px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace;"></td>
        <td><input type="number" name="line_items_rate[]" value="${rate}" step="0.01" min="0" oninput="recalcRow(this)" class="form-control line-rate" style="width: 100%; padding: 6px 8px; font-size: 12px; border-radius: 6px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace;"></td>
        <td><input type="text" readonly name="line_items_amt[]" value="${amt}" class="form-control line-amt" style="width: 100%; padding: 6px 8px; font-size: 12px; border-radius: 6px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace; background: #F8FAFC; font-weight: 600;"></td>
        <td style="text-align: center;"><button type="button" class="btn-remove-line" onclick="removeLineItemRow(this)">✕</button></td>
      `;
      tbody.appendChild(tr);
      updateLineItemsTotal(targetTbodyId);
    }

    function removeLineItemRow(btn) {
      const tr = btn.closest('tr');
      const tbody = tr.parentElement;
      if (tbody.children.length > 1) {
        tr.remove();
      } else {
        // Clear last remaining row
        tr.querySelector('input[name="line_items_desc[]"]').value = '';
        tr.querySelector('.line-qty').value = '1';
        tr.querySelector('.line-rate').value = '0.00';
        tr.querySelector('.line-amt').value = '0.00';
      }
      updateLineItemsTotal(tbody.id);
    }

    function recalcRow(input) {
      const tr = input.closest('tr');
      const qty = parseFloat(tr.querySelector('.line-qty').value) || 0;
      const rate = parseFloat(tr.querySelector('.line-rate').value) || 0;
      const amtInput = tr.querySelector('.line-amt');
      const total = (qty * rate);
      amtInput.value = total.toFixed(2);
      updateLineItemsTotal(tr.parentElement.id);
    }

    function updateLineItemsTotal(tbodyId) {
      const tbody = document.getElementById(tbodyId);
      let sum = 0;
      tbody.querySelectorAll('.line-amt').forEach(inp => {
        sum += parseFloat(inp.value) || 0;
      });
      const labelId = (tbodyId === 'genLineItemsBody') ? 'genLineItemsTotal' : 'editLineItemsTotal';
      document.getElementById(labelId).textContent = formatCurrency(sum);
      return sum;
    }

    function normalizeLineItemsBeforeSubmit(tbodyId) {
      const tbody = document.getElementById(tbodyId);
      if (!tbody) return 0;

      tbody.querySelectorAll('tr').forEach(row => {
        const qtyInput = row.querySelector('.line-qty');
        const rateInput = row.querySelector('.line-rate');
        const amtInput = row.querySelector('.line-amt');
        if (!qtyInput || !rateInput || !amtInput) return;

        const qty = parseFloat(qtyInput.value) || 0;
        const rate = parseFloat(rateInput.value) || 0;
        amtInput.value = Math.max(0, qty * rate).toFixed(2);
      });

      return updateLineItemsTotal(tbodyId);
    }

    function syncLineItemsTotalToAmount(prefix) {
      const tbodyId = (prefix === 'gen') ? 'genLineItemsBody' : 'editLineItemsBody';
      const total = updateLineItemsTotal(tbodyId);
      if (total > 0) {
        const amtInput = document.getElementById(prefix + 'Amount');
        amtInput.value = total.toFixed(2);
        if (prefix === 'gen') {
          recalcGenFinancials();
        } else {
          recalcEditFinancials();
        }
      }
    }

    // -------------------------------------------------------------
    // VIEW INVOICE MODAL
    // -------------------------------------------------------------
    function viewInvoice(inv) {
      document.getElementById('viewInvTitle').textContent = 'Invoice ' + (inv.invoice_number || '');
      document.getElementById('viewInvSubtitle').textContent = 'Issued to ' + (inv.client_name || '');
      document.getElementById('viewInvClient').textContent = inv.client_name || 'Not specified';

      const projName = inv.project_name || 'Not specified';
      const projPhase = inv.project_phase || 'Not specified';
      document.getElementById('viewInvProjectPhase').textContent = `${projName} (${projPhase})`;
      document.getElementById('viewInvService').textContent = inv.service || 'Digital Solutions';

      const pMode = inv.payment_mode || '—';
      const pTxn = inv.transaction_reference ? ` [${inv.transaction_reference}]` : '';
      const pBank = inv.bank_name ? ` (${inv.bank_name})` : '';
      document.getElementById('viewInvPaymentMode').textContent = `${pMode}${pTxn}${pBank}`;

      document.getElementById('viewInvIssueDate').textContent = (inv.created_at || '').substring(0, 10);
      document.getElementById('viewInvDueDate').textContent = inv.due_date || '—';

      const amt = parseFloat(inv.amount || 0);
      const paid = parseFloat(inv.amount_received || 0);
      const projTotal = parseFloat(inv.project_total || 0);
      const balance = (inv.balance_amount !== undefined && inv.balance_amount !== null) ? parseFloat(inv.balance_amount) : Math.max(0, amt - paid);

      document.getElementById('viewInvProjectTotal').textContent = formatCurrency(projTotal > 0 ? projTotal : amt);
      document.getElementById('viewInvAmount').textContent = formatCurrency(amt);
      document.getElementById('viewInvReceived').textContent = formatCurrency(paid);
      document.getElementById('viewInvBalance').textContent = formatCurrency(balance);

      document.getElementById('viewInvNotes').textContent = inv.notes || 'Professional engineering and milestone deliverables.';

      // Badges
      const typeBadge = document.getElementById('viewInvTypeBadge');
      const invType = (inv.invoice_type || 'full').toLowerCase();
      typeBadge.textContent = (invType === 'advance') ? 'ADVANCE PAYMENT' : 'FULL PAYMENT';

      const statusBadge = document.getElementById('viewInvStatusBadge');
      const st = (inv.status || 'pending').toLowerCase();
      statusBadge.textContent = st.toUpperCase();
      if (st === 'paid') {
        statusBadge.style.background = '#059669';
        statusBadge.style.color = '#ffffff';
      } else if (st === 'partially paid') {
        statusBadge.style.background = '#d97706';
        statusBadge.style.color = '#ffffff';
      } else if (st === 'pending') {
        statusBadge.style.background = '#64748b';
        statusBadge.style.color = '#ffffff';
      } else {
        statusBadge.style.background = '#dc2626';
        statusBadge.style.color = '#ffffff';
      }

      // Render line items
      const lineContainer = document.getElementById('viewInvLineItemsContainer');
      let items = [];
      if (inv.line_items) {
        try {
          items = JSON.parse(inv.line_items);
        } catch (e) {
          items = [];
        }
      }

      if (Array.isArray(items) && items.length > 0) {
        let tableHtml = `
          <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
            <thead>
              <tr style="border-bottom: 1px solid #E4E1D9; color: #5B6068;">
                <th style="text-align: left; padding: 4px 6px;">Description</th>
                <th style="text-align: center; padding: 4px 6px; width: 60px;">Qty</th>
                <th style="text-align: right; padding: 4px 6px; width: 100px;">Rate</th>
                <th style="text-align: right; padding: 4px 6px; width: 100px;">Amount</th>
              </tr>
            </thead>
            <tbody>
        `;
        items.forEach(it => {
          tableHtml += `
            <tr style="border-bottom: 1px solid #F0F2F6;">
              <td style="padding: 6px;">${it.description || ''}</td>
              <td style="text-align: center; padding: 6px; font-family: 'DM Mono', monospace;">${it.quantity || 1}</td>
              <td style="text-align: right; padding: 6px; font-family: 'DM Mono', monospace;">${formatCurrency(it.rate || 0)}</td>
              <td style="text-align: right; padding: 6px; font-family: 'DM Mono', monospace; font-weight: 600;">${formatCurrency(it.amount || 0)}</td>
            </tr>
          `;
        });
        tableHtml += `</tbody></table>`;
        lineContainer.innerHTML = tableHtml;
      } else {
        lineContainer.innerHTML = `<div style="color: var(--text-muted); font-size: 12px;">${inv.notes || 'Standard project milestone scope.'}</div>`;
      }

      // Save current viewing invoice
      window.currentViewingInvoice = inv;

      // Render Payment History
      const payHistContainer = document.getElementById('viewInvPaymentHistoryContainer');
      const payCountBadge = document.getElementById('viewInvPaymentCountBadge');
      const payments = Array.isArray(inv.payments) ? inv.payments : [];

      if (payCountBadge) {
        payCountBadge.textContent = payments.length > 0 ? (payments.length + (payments.length === 1 ? ' payment recorded' : ' payments recorded')) : '0 payments';
      }

      if (payHistContainer) {
        if (payments.length > 0) {
          let payHtml = `
            <div style="background: #FAFAFC; border: 1px solid var(--border-light); border-radius: 8px; overflow-x: auto;">
              <table style="width: 100%; border-collapse: collapse; font-size: 12px; min-width: 500px;">
                <thead>
                  <tr style="background: #F1F3F7; border-bottom: 1px solid #E4E1D9; color: #5B6068;">
                    <th style="text-align: left; padding: 7px 10px;">Date</th>
                    <th style="text-align: right; padding: 7px 10px;">Amount</th>
                    <th style="text-align: left; padding: 7px 10px;">Mode</th>
                    <th style="text-align: left; padding: 7px 10px;">UTR / Reference</th>
                    <th style="text-align: left; padding: 7px 10px;">Bank</th>
                    <th style="text-align: center; padding: 7px 10px;">Status</th>
                    <th style="text-align: left; padding: 7px 10px;">Notes</th>
                  </tr>
                </thead>
                <tbody>
          `;
          payments.forEach(p => {
            const pAmt = parseFloat(p.amount || 0);
            const pDate = p.payment_date ? p.payment_date.substring(0, 10) : '—';
            const pMode = p.payment_type || '—';
            const pRef = p.transaction_reference ? `<span style="font-family:'DM Mono',monospace; background:#e2e8f0; padding:2px 6px; border-radius:4px; font-size:10px;">${p.transaction_reference}</span>` : '—';
            const pBank = p.bank_name || '—';
            const pStatus = (p.payment_status || 'Paid').toUpperCase();
            const pNotes = p.notes || '—';

            payHtml += `
              <tr style="border-bottom: 1px solid #EFEFEF;">
                <td style="padding: 7px 10px; font-family: 'DM Mono', monospace;">${pDate}</td>
                <td style="padding: 7px 10px; text-align: right; font-family: 'DM Mono', monospace; font-weight: 700; color: #1F5C4A;">${formatCurrency(pAmt)}</td>
                <td style="padding: 7px 10px; font-weight: 600;">${pMode}</td>
                <td style="padding: 7px 10px;">${pRef}</td>
                <td style="padding: 7px 10px; color: #5B6068;">${pBank}</td>
                <td style="padding: 7px 10px; text-align: center;"><span style="background: #dcfce7; color: #15803d; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 700;">${pStatus}</span></td>
                <td style="padding: 7px 10px; color: #5B6068; max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${pNotes}">${pNotes}</td>
              </tr>
            `;
          });
          payHtml += `</tbody></table></div>`;
          payHistContainer.innerHTML = payHtml;
        } else {
          payHistContainer.innerHTML = `
            <div style="background: #FAFAFC; border: 1px dashed #CBD5E1; border-radius: 8px; padding: 14px 18px; text-align: center; color: #64748B; font-size: 12px;">
              <div style="font-weight: 600; color: #1E293B; margin-bottom: 2px;">No payments recorded yet for this invoice</div>
              <div>Click &ldquo;Record Payment&rdquo; below to log an advance or milestone settlement.</div>
            </div>
          `;
        }
      }

      // Update Record Payment button in View modal
      const viewPayBtn = document.getElementById('viewInvRecordPaymentBtn');
      if (viewPayBtn) {
        const remainingToPay = Math.max(0, amt - paid);
        if (remainingToPay <= 0 || st === 'paid') {
          viewPayBtn.disabled = true;
          viewPayBtn.style.opacity = '0.6';
          viewPayBtn.style.cursor = 'not-allowed';
          viewPayBtn.style.background = '#e2e8f0';
          viewPayBtn.style.color = '#475569';
          viewPayBtn.innerHTML = '<span>✓ Fully Paid</span>';
        } else {
          viewPayBtn.disabled = false;
          viewPayBtn.style.opacity = '1';
          viewPayBtn.style.cursor = 'pointer';
          viewPayBtn.style.background = '#1F5C4A';
          viewPayBtn.style.color = '#ffffff';
          viewPayBtn.innerHTML = '<span>💳 Record Payment</span>';
        }
      }

      document.getElementById('viewInvPdfBtn').href = 'invoices.php?action=export_pdf&id=' + inv.id;

      const m = document.getElementById('viewInvoiceModal');
      m.style.display = 'flex';
      m.setAttribute('aria-hidden', 'false');
    }

    function closeViewInvoiceModal() {
      const m = document.getElementById('viewInvoiceModal');
      m.style.display = 'none';
      m.setAttribute('aria-hidden', 'true');
    }

    // -------------------------------------------------------------
    // EDIT INVOICE MODAL
    // -------------------------------------------------------------
    function editInvoice(inv) {
      document.getElementById('editInvoiceId').value = inv.id;
      document.getElementById('editInvoiceSubtitle').textContent = 'Editing ' + (inv.invoice_number || '') + ' (' + (inv.client_name || '') + ')';

      // Client & Project
      const clientSel = document.getElementById('editClientId');
      clientSel.value = inv.client_id || '0';
      onEditClientChanged(inv.project_id);

      // Phase
      const phaseSel = document.getElementById('editProjectPhase');
      const phases = ['Phase 1', 'Phase 2', 'Phase 3', 'Phase 4', 'Phase 5'];
      if (phases.includes(inv.project_phase)) {
        phaseSel.value = inv.project_phase;
        document.getElementById('editCustomPhaseWrap').style.display = 'none';
      } else if (inv.project_phase) {
        phaseSel.value = 'Custom Phase';
        document.getElementById('editCustomPhaseWrap').style.display = 'block';
        document.getElementById('editCustomPhase').value = inv.project_phase;
      } else {
        phaseSel.value = '';
        document.getElementById('editCustomPhaseWrap').style.display = 'none';
      }

      // Invoice Type
      const invType = (inv.invoice_type || 'full').toLowerCase();
      document.getElementById('editInvoiceTypeSelect').value = invType;
      document.getElementById('editInvoiceTypeInput').value = invType;

      // Amounts
      document.getElementById('editProjectTotal').value = parseFloat(inv.project_total || 0).toFixed(2);
      document.getElementById('editAmount').value = parseFloat(inv.amount || 0).toFixed(2);
      document.getElementById('editAmountReceived').value = parseFloat(inv.amount_received || 0).toFixed(2);

      // Status & Service
      document.getElementById('editStatus').value = (inv.status || 'pending').toLowerCase();
      document.getElementById('editService').value = inv.service || 'Websites';
      document.getElementById('editDueDate').value = inv.due_date || '';

      // Payment Details
      const pMode = inv.payment_mode || 'UPI';
      document.getElementById('editPaymentMode').value = pMode;
      document.getElementById('editTxnRef').value = inv.transaction_reference || '';
      document.getElementById('editBankName').value = inv.bank_name || '';
      document.getElementById('editPmDesc').value = inv.payment_method_desc || '';
      document.getElementById('editPaymentDate').value = (inv.payment_date || inv.paid_at || '').substring(0, 10);
      onEditPaymentModeChanged();

      // Notes
      document.getElementById('editNotes').value = inv.notes || '';

      // Line items
      const editTbody = document.getElementById('editLineItemsBody');
      editTbody.innerHTML = '';
      let lineItems = [];
      if (inv.line_items) {
        try {
          lineItems = JSON.parse(inv.line_items);
        } catch(e) {
          lineItems = [];
        }
      }
      if (Array.isArray(lineItems) && lineItems.length > 0) {
        lineItems.forEach(it => addLineItemRow('editLineItemsBody', it));
      } else {
        addLineItemRow('editLineItemsBody', null);
      }

      recalcEditFinancials();

      const m = document.getElementById('editInvoiceModal');
      m.style.display = 'flex';
      m.setAttribute('aria-hidden', 'false');
    }

    function closeEditInvoiceModal() {
      const m = document.getElementById('editInvoiceModal');
      m.style.display = 'none';
      m.setAttribute('aria-hidden', 'true');
    }

    function onEditClientChanged(selectedProjectId = null) {
      const cid = parseInt(document.getElementById('editClientId').value, 10);
      const projSel = document.getElementById('editProjectId');

      projSel.innerHTML = '';
      const defOpt = document.createElement('option');
      defOpt.value = '0';
      defOpt.textContent = 'Project not specified';
      projSel.appendChild(defOpt);

      if (cid > 0 && CLIENT_PROJECTS[cid]) {
        CLIENT_PROJECTS[cid].forEach(p => {
          const opt = document.createElement('option');
          opt.value = p.id;
          opt.textContent = `${p.title} (${p.status})`;
          projSel.appendChild(opt);
        });
      }

      const customOpt = document.createElement('option');
      customOpt.value = '-1';
      customOpt.textContent = '+ Custom Project';
      projSel.appendChild(customOpt);

      if (selectedProjectId) {
        projSel.value = selectedProjectId;
      }
    }

    function onEditProjectChanged() {
      recalcEditFinancials();
    }

    function onEditPhaseChanged() {
      const val = document.getElementById('editProjectPhase').value;
      document.getElementById('editCustomPhaseWrap').style.display = (val === 'Custom Phase') ? 'block' : 'none';
    }

    function onEditInvoiceTypeChanged() {
      const val = document.getElementById('editInvoiceTypeSelect').value;
      document.getElementById('editInvoiceTypeInput').value = val;
      if (val === 'advance') {
        document.getElementById('editAmountLabel').textContent = 'Advance Amount (₹) *';
        document.getElementById('editAmountReceivedLabel').textContent = 'Advance Received (₹)';
      } else {
        document.getElementById('editAmountLabel').textContent = 'Invoice Amount (₹) *';
        document.getElementById('editAmountReceivedLabel').textContent = 'Amount Received (₹)';
      }
      recalcEditFinancials();
    }

    function onEditPaymentModeChanged() {
      const mode = document.getElementById('editPaymentMode').value;
      document.getElementById('editTxnRefWrap').style.display = (mode === 'Cash') ? 'none' : 'block';
      document.getElementById('editBankNameWrap').style.display = (mode === 'Bank Transfer') ? 'block' : 'none';
      document.getElementById('editPmDescWrap').style.display = (mode === 'Other') ? 'block' : 'none';
    }

    function recalcEditFinancials() {
      const projTotal = parseFloat(document.getElementById('editProjectTotal').value) || 0;
      const invoiceAmt = parseFloat(document.getElementById('editAmount').value) || 0;
      const amtReceived = parseFloat(document.getElementById('editAmountReceived').value) || 0;
      const projId = parseInt(document.getElementById('editProjectId').value, 10);

      const priorPaid = (projId > 0 && PROJECT_PAYMENTS[projId]) ? PROJECT_PAYMENTS[projId] : 0;
      const totalReceived = priorPaid + amtReceived;

      let balance = 0;
      if (projTotal > 0) {
        balance = Math.max(0, projTotal - totalReceived);
      } else {
        balance = Math.max(0, invoiceAmt - amtReceived);
      }

      document.getElementById('editSumProjectTotal').textContent = formatCurrency(projTotal > 0 ? projTotal : invoiceAmt);
      document.getElementById('editSumThisInvoice').textContent = formatCurrency(invoiceAmt);
      document.getElementById('editSumTotalReceived').textContent = formatCurrency(totalReceived);
      document.getElementById('editSumBalance').textContent = formatCurrency(balance);
    }

    // -------------------------------------------------------------
    // DELETE MODAL
    // -------------------------------------------------------------
    function confirmDeleteInvoice(id, num) {
      document.getElementById('deleteInvoiceId').value = id;
      document.getElementById('deleteInvoiceNumText').textContent = 'Invoice ' + num;

      const m = document.getElementById('deleteInvoiceModal');
      m.style.display = 'flex';
      m.setAttribute('aria-hidden', 'false');
    }

    function closeDeleteInvoiceModal() {
      const m = document.getElementById('deleteInvoiceModal');
      m.style.display = 'none';
      m.setAttribute('aria-hidden', 'true');
    }

    // -------------------------------------------------------------
    // RECORD PAYMENT MODAL (PHASE 5)
    // -------------------------------------------------------------
    let currentPaymentInvoice = null;

    function openRecordPaymentModal(inv) {
      currentPaymentInvoice = inv;
      document.getElementById('payInvoiceId').value = inv.id;
      document.getElementById('payClientId').value = inv.client_id || 0;
      document.getElementById('payProjectId').value = inv.project_id || 0;

      // Context fields
      document.getElementById('payContextClient').textContent = inv.client_name || 'Not specified';
      const pName = inv.project_name || 'Not specified';
      const pPhase = inv.project_phase || 'General Scope';
      document.getElementById('payContextProjectPhase').textContent = `${pName} (${pPhase})`;
      document.getElementById('payContextInvoiceNum').textContent = inv.invoice_number || 'INV-000';

      const invType = (inv.invoice_type || 'full').toLowerCase();
      const typeBadge = document.getElementById('payContextTypeBadge');
      typeBadge.textContent = invType === 'advance' ? 'Advance Payment' : 'Full Payment';

      const amt = parseFloat(inv.amount || 0);
      const paid = parseFloat(inv.amount_received || 0);
      const remaining = Math.max(0, Math.round((amt - paid) * 100) / 100);

      document.getElementById('payContextAmount').textContent = formatCurrency(amt);
      document.getElementById('payContextReceived').textContent = formatCurrency(paid);
      document.getElementById('payContextRemaining').textContent = formatCurrency(remaining);
      document.getElementById('payBtnFillRemaining').textContent = formatCurrency(remaining);

      // Set default payment amount to remaining
      const amtInput = document.getElementById('payAmountInput');
      amtInput.value = remaining > 0 ? remaining.toFixed(2) : '0.00';
      amtInput.max = remaining.toFixed(2);

      // Reset date & mode
      document.getElementById('payDateInput').value = new Date().toISOString().substring(0, 10);
      document.getElementById('payModeSelect').value = 'UPI';
      document.getElementById('payTxnRefInput').value = '';
      document.getElementById('payBankNameInput').value = '';
      document.getElementById('payOtherDescInput').value = '';
      document.getElementById('payNotesInput').value = '';

      onPayModeChanged();
      onPayAmountChanged();

      const m = document.getElementById('recordPaymentModal');
      m.style.display = 'flex';
      m.setAttribute('aria-hidden', 'false');
    }

    function closeRecordPaymentModal() {
      const m = document.getElementById('recordPaymentModal');
      m.style.display = 'none';
      m.setAttribute('aria-hidden', 'true');
    }

    function openRecordPaymentFromView() {
      if (window.currentViewingInvoice) {
        closeViewInvoiceModal();
        openRecordPaymentModal(window.currentViewingInvoice);
      }
    }

    function fillFullRemainingPayment() {
      if (!currentPaymentInvoice) return;
      const amt = parseFloat(currentPaymentInvoice.amount || 0);
      const paid = parseFloat(currentPaymentInvoice.amount_received || 0);
      const remaining = Math.max(0, Math.round((amt - paid) * 100) / 100);
      document.getElementById('payAmountInput').value = remaining.toFixed(2);
      onPayAmountChanged();
    }

    function onPayAmountChanged() {
      if (!currentPaymentInvoice) return;
      const amt = parseFloat(currentPaymentInvoice.amount || 0);
      const paid = parseFloat(currentPaymentInvoice.amount_received || 0);
      const remaining = Math.max(0, Math.round((amt - paid) * 100) / 100);

      const entered = parseFloat(document.getElementById('payAmountInput').value || 0);
      const newRemaining = Math.max(0, Math.round((remaining - entered) * 100) / 100);

      document.getElementById('payNewRemaining').textContent = formatCurrency(newRemaining);

      const exceedsWarn = document.getElementById('payExceedsWarning');
      const submitBtn = document.getElementById('paySubmitBtn');
      const amtInput = document.getElementById('payAmountInput');

      if (entered > (remaining + 0.001)) {
        exceedsWarn.style.display = 'inline';
        amtInput.style.borderColor = '#dc2626';
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.6';
        submitBtn.style.cursor = 'not-allowed';
      } else if (entered <= 0 || isNaN(entered)) {
        exceedsWarn.style.display = 'none';
        amtInput.style.borderColor = 'var(--border-light)';
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.6';
        submitBtn.style.cursor = 'not-allowed';
      } else {
        exceedsWarn.style.display = 'none';
        amtInput.style.borderColor = 'var(--border-light)';
        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
        submitBtn.style.cursor = 'pointer';
      }
    }

    function onPayModeChanged() {
      const mode = document.getElementById('payModeSelect').value;
      const utrContainer = document.getElementById('payUtrContainer');
      const utrLabel = document.getElementById('payUtrLabel');
      const bankContainer = document.getElementById('payBankContainer');
      const otherContainer = document.getElementById('payOtherDescContainer');

      if (mode === 'UPI') {
        utrContainer.style.display = 'block';
        utrLabel.textContent = 'UTR / Transaction Reference *';
        bankContainer.style.display = 'none';
        otherContainer.style.display = 'none';
      } else if (mode === 'Bank Transfer') {
        utrContainer.style.display = 'block';
        utrLabel.textContent = 'UTR / Transaction Reference *';
        bankContainer.style.display = 'block';
        otherContainer.style.display = 'none';
      } else if (mode === 'Cash') {
        utrContainer.style.display = 'none';
        bankContainer.style.display = 'none';
        otherContainer.style.display = 'none';
      } else if (mode === 'Card') {
        utrContainer.style.display = 'block';
        utrLabel.textContent = 'Transaction / Authorization Reference *';
        bankContainer.style.display = 'none';
        otherContainer.style.display = 'none';
      } else {
        // Other
        utrContainer.style.display = 'none';
        bankContainer.style.display = 'none';
        otherContainer.style.display = 'block';
      }
    }

    function handleRecordPaymentSubmit(e) {
      const btn = document.getElementById('paySubmitBtn');
      if (btn.dataset.submitting === 'true' || btn.disabled) {
        e.preventDefault();
        return false;
      }
      btn.dataset.submitting = 'true';
      btn.disabled = true;
      btn.innerHTML = '<span>Recording Payment...</span>';
      return true;
    }

    // -------------------------------------------------------------
    // PREVENT DOUBLE SUBMISSIONS
    // -------------------------------------------------------------
    document.getElementById('genInvoiceForm').addEventListener('submit', function(e) {
      normalizeLineItemsBeforeSubmit('genLineItemsBody');
      recalcGenFinancials();
      const btn = document.getElementById('genSubmitBtn');
      if (btn.dataset.submitting === 'true') {
        e.preventDefault();
        return false;
      }
      btn.dataset.submitting = 'true';
      btn.disabled = true;
      btn.textContent = 'Saving Invoice...';
    });

    document.getElementById('editInvoiceForm').addEventListener('submit', function(e) {
      normalizeLineItemsBeforeSubmit('editLineItemsBody');
      recalcEditFinancials();
      const btn = document.getElementById('editSubmitBtn');
      if (btn.dataset.submitting === 'true') {
        e.preventDefault();
        return false;
      }
      btn.dataset.submitting = 'true';
      btn.disabled = true;
      btn.textContent = 'Saving Changes...';
    });

    // Close modals on clicking overlay background
    window.addEventListener('click', function(e) {
      if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
        e.target.setAttribute('aria-hidden', 'true');
      }
    });
  </script>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>
