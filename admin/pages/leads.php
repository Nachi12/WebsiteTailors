<?php
/**
 * Website Tailors Admin — Phase 3: Leads + Call Tracking
 *
 * Grounded 100% in real database data from `leads` and `calls`.
 * Tracks potential customers, outreach history, and follow-up schedules:
 * - Table columns: Lead, Company, Service, Phone, Lead Status, Call Status, Last Called, Next Follow-up, Actions
 * - Call Modal for logging call date/time, outcome, notes, and next follow-up date
 * - Complete Call History per lead
 * - Clear visual indicators for Not Called, Called, and Call Back
 * - Filters for Call Status (All, Not Called, Called, Call Back, No Answer) and Lead Status
 * - Follow-ups Due view sorted by nearest follow-up date with overdue alerts
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) {
    define('WebsiteTailors_INIT', true);
}
require_once dirname(__DIR__) . '/includes/auth_guard.php';

$pageTitle = 'Leads & Call Tracking';
$breadcrumb = 'Leads & CRM';
$pdo = Database::getInstance()->getConnection();

$error = null;
$success = null;

// Valid Status Definitions
$validLeadStatuses = ['New', 'Contacted', 'Qualified', 'Proposal Sent', 'Converted', 'Lost'];
$validCallStatuses = ['Not Called', 'Called', 'Call Back', 'No Answer', 'Not Interested'];
$validCallOutcomes = ['Connected', 'No Answer', 'Call Back', 'Not Interested', 'Converted'];
$validServices     = ['Website', 'Software', 'AI + Automation', 'UI/UX', 'Maintenance', 'Consulting', 'Other'];
$validSources      = ['Website Form', 'Referral', 'LinkedIn', 'Cold Outreach', 'Direct Phone', 'Existing Client', 'Other'];

require_once dirname(__DIR__, 2) . '/includes/excel_lead_importer.php';

// -----------------------------------------------------------------------------
// AJAX/DOWNLOAD ENDPOINTS: Template & Error Report Downloads
// -----------------------------------------------------------------------------
if (($_GET['action'] ?? '') === 'download_import_template') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="websitetailors_leads_sample.csv"');
    echo ExcelLeadImporter::generateSampleCsv();
    exit;
}

if (($_GET['action'] ?? '') === 'download_import_errors') {
    $token = trim((string)($_GET['token'] ?? ''));
    $csv = ExcelLeadImporter::generateErrorReportCsv($token);
    if (!$csv) {
        header('Location: leads.php?error=' . urlencode('Error report expired or unavailable.'));
        exit;
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="lead_import_errors_' . date('Ymd_His') . '.csv"');
    echo $csv;
    exit;
}

// -----------------------------------------------------------------------------
// AJAX ENDPOINT: Fetch Call History for a Lead
// -----------------------------------------------------------------------------
if (($_GET['action'] ?? '') === 'get_call_history') {
    header('Content-Type: application/json; charset=utf-8');
    $leadId = (int)($_GET['lead_id'] ?? 0);
    if ($leadId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid lead ID']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, lead_id, client_id, contact_name, company, phone,
                   COALESCE(call_datetime, scheduled_at, created_at) as call_time,
                   outcome, notes, next_followup_at, created_at
            FROM calls
            WHERE lead_id = :lead_id
            ORDER BY COALESCE(call_datetime, scheduled_at, created_at) DESC, id DESC
        ");
        $stmt->execute([':lead_id' => $leadId]);
        $calls = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'calls' => $calls]);
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// -----------------------------------------------------------------------------
// AJAX ENDPOINT: Excel Preview Upload
// -----------------------------------------------------------------------------
if (($_POST['action'] ?? '') === 'excel_preview') {
    header('Content-Type: application/json; charset=utf-8');
    if (!verify_csrf()) {
        echo json_encode(['success' => false, 'error' => 'Security session expired. Please refresh the page.']);
        exit;
    }

    $file = $_FILES['excel_file'] ?? null;
    if (!$file) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded. Please select an Excel or CSV file.']);
        exit;
    }

    $res = ExcelLeadImporter::processUpload($file, $pdo);
    echo json_encode($res);
    exit;
}

// -----------------------------------------------------------------------------
// AJAX ENDPOINT: Excel Confirm Import
// -----------------------------------------------------------------------------
if (($_POST['action'] ?? '') === 'excel_confirm_import') {
    header('Content-Type: application/json; charset=utf-8');
    if (!verify_csrf()) {
        echo json_encode(['success' => false, 'error' => 'Security session expired. Please refresh the page.']);
        exit;
    }

    $importToken = trim((string)($_POST['import_token'] ?? ''));
    $duplicateOption = trim((string)($_POST['duplicate_option'] ?? 'skip'));

    $res = ExcelLeadImporter::commitImport($importToken, $duplicateOption, $pdo, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    echo json_encode($res);
    exit;
}

// -----------------------------------------------------------------------------
// POST ACTION HANDLER: Log Call, Update Status, Delete
// -----------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } else {
        $action = sanitize_text($_POST['action'] ?? '');
        $leadId = (int)($_POST['lead_id'] ?? ($_POST['id'] ?? 0));

        // 1. LOG CALL ACTION
        if ($action === 'log_call' && $leadId > 0) {
            $outcome = sanitize_text($_POST['outcome'] ?? '');
            $notes   = sanitize_text($_POST['notes'] ?? '');
            $callDateInput = trim($_POST['call_datetime'] ?? '');
            $callDateTime  = !empty($callDateInput) ? date('Y-m-d H:i:s', strtotime($callDateInput)) : date('Y-m-d H:i:s');
            
            $followupInput  = trim($_POST['next_followup_at'] ?? '');
            $nextFollowupAt = !empty($followupInput) ? date('Y-m-d H:i:s', strtotime($followupInput)) : null;

            if (!in_array($outcome, $validCallOutcomes, true)) {
                $error = 'Please select a valid call outcome.';
            } else {
                try {
                    // Fetch existing lead details
                    $leadStmt = $pdo->prepare("SELECT * FROM leads WHERE id = :id");
                    $leadStmt->execute([':id' => $leadId]);
                    $lead = $leadStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$lead) {
                        $error = 'Target lead not found.';
                    } else {
                        // Map Call Outcome to Lead Call Status
                        $mappedCallStatus = match ($outcome) {
                            'Connected'      => 'Called',
                            'No Answer'     => 'No Answer',
                            'Call Back'     => 'Call Back',
                            'Not Interested'=> 'Not Interested',
                            'Converted'     => 'Called',
                            default          => 'Called'
                        };

                        // Determine new Lead Status if outcome is Converted
                        $newLeadStatus = $lead['status'];
                        $clientId = $lead['client_id'] ? (int)$lead['client_id'] : null;

                        if ($outcome === 'Converted') {
                            $newLeadStatus = 'Converted';
                            // If no client record linked, link existing client if one already exists (do NOT auto-create)
                            if (!$clientId) {
                                $existingClient = $pdo->prepare("SELECT id FROM clients WHERE email = :email LIMIT 1");
                                $existingClient->execute([':email' => $lead['email']]);
                                $foundClientId = $existingClient->fetchColumn();

                                if ($foundClientId) {
                                    $clientId = (int)$foundClientId;
                                }
                            }
                        } elseif ($outcome === 'Connected' && in_array(strtolower($lead['status']), ['new'])) {
                            $newLeadStatus = 'Contacted';
                        }

                        // Combine notes
                        $timestampedNote = '[' . date('M j, Y H:i') . ' Call (' . $outcome . ')]: ' . ($notes ?: 'No notes recorded.');
                        $updatedNotes = !empty($lead['notes']) ? ($lead['notes'] . "\n\n" . $timestampedNote) : $timestampedNote;

                        // Insert into calls table (Complete Call History record)
                        $callInsert = $pdo->prepare("
                            INSERT INTO calls (lead_id, client_id, contact_name, company, phone, call_datetime, scheduled_at, type, status, duration_minutes, priority, notes, outcome, next_followup_at, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'follow_up', 'completed', 15, 'normal', ?, ?, ?, ?)
                        ");
                        $nowStr = date('Y-m-d H:i:s');
                        $callInsert->execute([
                            $leadId,
                            $clientId,
                            $lead['name'],
                            $lead['company'],
                            $lead['phone'] ?? '',
                            $callDateTime,
                            $nextFollowupAt ?: $callDateTime,
                            $notes,
                            $outcome,
                            $nextFollowupAt,
                            $nowStr
                        ]);

                        // Update leads table
                        $leadUpdate = $pdo->prepare("
                            UPDATE leads
                            SET call_status = :call_status,
                                last_called_at = :last_called_at,
                                next_followup_at = :next_followup_at,
                                status = :status,
                                client_id = :client_id,
                                notes = :notes,
                                updated_at = :updated_at
                            WHERE id = :id
                        ");
                        $leadUpdate->execute([
                            ':call_status'      => $mappedCallStatus,
                            ':last_called_at'   => $callDateTime,
                            ':next_followup_at' => $nextFollowupAt,
                            ':status'           => $newLeadStatus,
                            ':client_id'        => $clientId,
                            ':notes'            => $updatedNotes,
                            ':updated_at'       => $nowStr,
                            ':id'               => $leadId
                        ]);

                        $success = 'Call outcome (' . $outcome . ') logged for ' . $lead['name'] . '. Status updated to ' . $mappedCallStatus . '.';
                    }
                } catch (\Throwable $e) {
                    $error = 'Failed to log call: ' . $e->getMessage();
                }
            }
        }
        // 2. UPDATE LEAD STATUS
        elseif (($action === 'update_status' || $action === 'set_status') && $leadId > 0) {
            $newStatus = sanitize_text($_POST['status'] ?? '');
            $matchedStatus = null;
            foreach ($validLeadStatuses as $s) {
                if (strcasecmp($s, $newStatus) === 0) {
                    $matchedStatus = $s;
                    break;
                }
            }
            if (!$matchedStatus && in_array(strtolower($newStatus), ['archived', 'closed', 'lost', 'new', 'contacted', 'qualified'])) {
                $matchedStatus = ucfirst(strtolower($newStatus));
            }
            if ($matchedStatus) {
                try {
                    $nowStr = date('Y-m-d H:i:s');
                    $stmt = $pdo->prepare("UPDATE leads SET status = :status, updated_at = :now WHERE id = :id");
                    $stmt->execute([':status' => $matchedStatus, ':now' => $nowStr, ':id' => $leadId]);

                    // When status becomes Converted, link existing client if one exists, but do NOT auto-create client
                    if ($matchedStatus === 'Converted') {
                        $lCheck = $pdo->prepare("SELECT * FROM leads WHERE id = :id");
                        $lCheck->execute([':id' => $leadId]);
                        $leadRow = $lCheck->fetch(PDO::FETCH_ASSOC);
                        if ($leadRow && empty($leadRow['client_id'])) {
                            $chk = $pdo->prepare("SELECT id FROM clients WHERE email = :email LIMIT 1");
                            $chk->execute([':email' => $leadRow['email']]);
                            $foundCid = $chk->fetchColumn();
                            if ($foundCid) {
                                $pdo->prepare("UPDATE leads SET client_id = ? WHERE id = ?")->execute([(int)$foundCid, $leadId]);
                            }
                        }
                    }

                    $success = 'Lead #' . $leadId . ' marked as ' . $matchedStatus . ' (status updated).';
                } catch (\Throwable $e) {
                    $error = 'Failed to update status: ' . $e->getMessage();
                }
            }
        }
        // 2b. CONVERT LEAD TO CLIENT
        elseif (($action === 'convert_to_client' || $action === 'create_client_from_lead') && $leadId > 0) {
            try {
                $leadStmt = $pdo->prepare("SELECT * FROM leads WHERE id = :id");
                $leadStmt->execute([':id' => $leadId]);
                $lead = $leadStmt->fetch(PDO::FETCH_ASSOC);

                if (!$lead) {
                    $error = 'Target lead not found.';
                } else {
                    $nowStr = date('Y-m-d H:i:s');
                    $clientId = !empty($lead['client_id']) ? (int)$lead['client_id'] : 0;

                    if (!$clientId) {
                        $chk = $pdo->prepare("SELECT id FROM clients WHERE email = :email LIMIT 1");
                        $chk->execute([':email' => $lead['email']]);
                        $existingClientId = $chk->fetchColumn();

                        if ($existingClientId) {
                            $clientId = (int)$existingClientId;
                        } else {
                            $createClient = $pdo->prepare("
                                INSERT INTO clients (client_name, company_name, email, phone, service, source, status, notes, created_at, updated_at)
                                VALUES (?, ?, ?, ?, ?, ?, 'Active', ?, ?, ?)
                            ");
                            $createClient->execute([
                                $lead['name'],
                                !empty($lead['company']) ? $lead['company'] : $lead['name'],
                                $lead['email'],
                                !empty($lead['phone']) ? $lead['phone'] : '',
                                !empty($lead['service']) ? $lead['service'] : (!empty($lead['service_interested']) ? $lead['service_interested'] : 'Website'),
                                !empty($lead['source']) ? $lead['source'] : 'Website',
                                !empty($lead['notes']) ? $lead['notes'] : ('Converted from Lead #' . $leadId),
                                $nowStr,
                                $nowStr
                            ]);
                            $clientId = (int)$pdo->lastInsertId();
                        }

                        $pdo->prepare("UPDATE leads SET client_id = :cid, status = 'Converted', updated_at = :now WHERE id = :id")
                            ->execute([':cid' => $clientId, ':now' => $nowStr, ':id' => $leadId]);
                    } else {
                        $pdo->prepare("UPDATE leads SET status = 'Converted', updated_at = :now WHERE id = :id")
                            ->execute([':now' => $nowStr, ':id' => $leadId]);
                    }

                    $success = 'Lead #' . $leadId . ' (' . $lead['name'] . ') converted to Client #' . $clientId . ' successfully! Lead remains preserved and linked.';
                }
            } catch (\Throwable $e) {
                $error = 'Failed to convert lead to client: ' . $e->getMessage();
            }
        }
        // 3. DELETE LEAD
        elseif ($action === 'delete' && $leadId > 0) {
            try {
                // Delete associated calls first
                $pdo->prepare("DELETE FROM calls WHERE lead_id = :id")->execute([':id' => $leadId]);
                // Delete lead
                $pdo->prepare("DELETE FROM leads WHERE id = :id")->execute([':id' => $leadId]);
                $success = 'Lead inquiry #' . $leadId . ' was successfully deleted along with its call history.';
            } catch (\Throwable $e) {
                $error = 'Failed to delete lead: ' . $e->getMessage();
            }
        }
        // 3b. BULK DELETE LEADS
        elseif ($action === 'bulk_delete_leads') {
            $rawIds = $_POST['lead_ids'] ?? [];
            $leadIds = array_values(array_filter(array_map('intval', (array)$rawIds), fn($v) => $v > 0));
            if (empty($leadIds)) {
                $error = 'No leads were selected for deletion.';
            } else {
                try {
                    $placeholders = implode(',', array_fill(0, count($leadIds), '?'));
                    // Delete associated calls first
                    $delCalls = $pdo->prepare("DELETE FROM calls WHERE lead_id IN ($placeholders)");
                    $delCalls->execute($leadIds);
                    // Delete leads
                    $delLeads = $pdo->prepare("DELETE FROM leads WHERE id IN ($placeholders)");
                    $delLeads->execute($leadIds);
                    $success = count($leadIds) . ' lead(s) and their associated call history were successfully deleted.';
                } catch (\Throwable $e) {
                    $error = 'Failed to delete selected leads: ' . $e->getMessage();
                }
            }
        }
        // 4. EDIT LEAD
        elseif ($action === 'edit_lead' && $leadId > 0) {
            $nameInput    = sanitize_text($_POST['name'] ?? '');
            $companyInput = sanitize_text($_POST['company'] ?? '');
            $emailInput   = sanitize_text($_POST['email'] ?? '');
            $phoneInput   = sanitize_text($_POST['phone'] ?? '');
            $serviceInput = sanitize_text($_POST['service'] ?? '');
            $budgetInput  = sanitize_text($_POST['budget'] ?? '');
            $sourceInput  = sanitize_text($_POST['source'] ?? '');
            $statusInput  = sanitize_text($_POST['status'] ?? 'New');
            $callStatusIn = sanitize_text($_POST['call_status'] ?? 'Not Called');
            $notesInput   = sanitize_text($_POST['notes'] ?? '');

            if (empty($nameInput) || empty($emailInput)) {
                $error = 'Lead name and email are required.';
            } else {
                try {
                    $nowStr = date('Y-m-d H:i:s');
                    $updLeadStmt = $pdo->prepare("
                        UPDATE leads
                        SET name = :name,
                            company = :company,
                            email = :email,
                            phone = :phone,
                            service = :service,
                            budget = :budget,
                            source = :source,
                            status = :status,
                            call_status = :call_status,
                            notes = :notes,
                            updated_at = :updated_at
                        WHERE id = :id
                    ");
                    $updLeadStmt->execute([
                        ':name'        => $nameInput,
                        ':company'     => $companyInput,
                        ':email'       => $emailInput,
                        ':phone'       => $phoneInput,
                        ':service'     => $serviceInput,
                        ':budget'      => $budgetInput,
                        ':source'      => $sourceInput,
                        ':status'      => in_array($statusInput, $validLeadStatuses, true) ? $statusInput : 'New',
                        ':call_status' => in_array($callStatusIn, $validCallStatuses, true) ? $callStatusIn : 'Not Called',
                        ':notes'       => $notesInput,
                        ':updated_at'  => $nowStr,
                        ':id'          => $leadId
                    ]);

                    // If status is changed to Converted, link existing client if one exists (do NOT auto-create)
                    if ($statusInput === 'Converted') {
                        $chkClient = $pdo->prepare("SELECT id FROM clients WHERE email = :email LIMIT 1");
                        $chkClient->execute([':email' => $emailInput]);
                        $foundClientId = $chkClient->fetchColumn();
                        if ($foundClientId) {
                            $pdo->prepare("UPDATE leads SET client_id = ? WHERE id = ?")->execute([$foundClientId, $leadId]);
                        }
                    }

                    $success = 'Lead #' . $leadId . ' (' . $nameInput . ') successfully updated. Dashboard pipeline updated.';
                } catch (\Throwable $e) {
                    $error = 'Failed to update lead: ' . $e->getMessage();
                }
            }
        }
        // 5. ADD LEAD ACTION
        elseif ($action === 'add_lead') {
            $nameInput     = sanitize_text($_POST['name'] ?? '');
            $companyInput  = sanitize_text($_POST['company'] ?? '');
            $emailInput    = trim(sanitize_text($_POST['email'] ?? ''));
            $phoneInput    = sanitize_text($_POST['phone'] ?? '');
            $serviceInput  = sanitize_text($_POST['service'] ?? '');
            $budgetInput   = sanitize_text($_POST['budget'] ?? '');
            $sourceInput   = sanitize_text($_POST['source'] ?? 'Direct Phone');
            $statusInput   = sanitize_text($_POST['status'] ?? 'New');
            $callStatusIn  = sanitize_text($_POST['call_status'] ?? 'Not Called');
            $notesInput    = sanitize_text($_POST['notes'] ?? '');

            if (empty($nameInput)) {
                $error = 'Please provide the lead/prospect name.';
            } elseif (empty($phoneInput)) {
                $error = 'Please provide a contact phone number.';
            } elseif (!empty($emailInput) && !filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please provide a valid email address.';
            } else {
                try {
                    $nowStr = date('Y-m-d H:i:s');
                    $validService = !empty($serviceInput) ? $serviceInput : 'Website';
                    $validStatus = in_array($statusInput, $validLeadStatuses, true) ? $statusInput : 'New';
                    $validCallStatus = in_array($callStatusIn, $validCallStatuses, true) ? $callStatusIn : 'Not Called';
                    $ipAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

                    $insStmt = $pdo->prepare("
                        INSERT INTO leads (
                            name, company, email, phone, service, budget, message,
                            source, status, call_status, notes, ip_address, created_at, updated_at
                        ) VALUES (
                            :name, :company, :email, :phone, :service, :budget, :message,
                            :source, :status, :call_status, :notes, :ip, :created_at, :updated_at
                        )
                    ");
                    $insStmt->execute([
                        ':name'        => $nameInput,
                        ':company'     => $companyInput ?: null,
                        ':email'       => $emailInput ?: null,
                        ':phone'       => $phoneInput,
                        ':service'     => $validService,
                        ':budget'      => $budgetInput ?: null,
                        ':message'     => !empty($notesInput) ? $notesInput : 'Direct CRM Inquiry',
                        ':source'      => $sourceInput,
                        ':status'      => $validStatus,
                        ':call_status' => $validCallStatus,
                        ':notes'       => $notesInput ?: null,
                        ':ip'          => $ipAddr,
                        ':created_at'  => $nowStr,
                        ':updated_at'  => $nowStr
                    ]);

                    $newLeadId = (int)$pdo->lastInsertId();

                    // If added with Converted status, link existing client if one exists (do NOT auto-create)
                    if ($validStatus === 'Converted' && !empty($emailInput)) {
                        $chkClient = $pdo->prepare("SELECT id FROM clients WHERE email = :email LIMIT 1");
                        $chkClient->execute([':email' => $emailInput]);
                        $foundClientId = $chkClient->fetchColumn();
                        if ($foundClientId) {
                            $pdo->prepare("UPDATE leads SET client_id = ? WHERE id = ?")->execute([$foundClientId, $newLeadId]);
                        }
                    }

                    $success = 'New lead "' . $nameInput . '" successfully added (ID #' . $newLeadId . ').';
                } catch (\Throwable $e) {
                    $error = 'Failed to add new lead: ' . $e->getMessage();
                }
            }
        }
    }
}

// -----------------------------------------------------------------------------
// FILTERING, SEARCH, VIEW MODE & PAGINATION
// -----------------------------------------------------------------------------
$viewMode = sanitize_text($_GET['view'] ?? 'all');
if (!in_array($viewMode, ['all', 'followups'], true)) {
    $viewMode = 'all';
}

$callFilter = sanitize_text($_GET['call_status'] ?? 'all');
$leadFilter = sanitize_text($_GET['lead_status'] ?? $_GET['status'] ?? 'all');
$serviceFilter = sanitize_text($_GET['service'] ?? 'all');
$searchQuery = trim(sanitize_text($_GET['search'] ?? $_GET['q'] ?? ''));
$dateFilter = trim(sanitize_text($_GET['date'] ?? ''));

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Base query & filters
$whereClauses = [];
$params = [];

if ($viewMode === 'followups') {
    // Only leads with scheduled follow-up
    $whereClauses[] = "next_followup_at IS NOT NULL AND next_followup_at != ''";
} else {
    // Call status filter
    if ($callFilter !== 'all') {
        $whereClauses[] = "LOWER(call_status) = :call_status";
        $params[':call_status'] = strtolower($callFilter);
    }
    // Lead status filter
    if ($leadFilter !== 'all') {
        $whereClauses[] = "LOWER(status) = :lead_status";
        $params[':lead_status'] = strtolower($leadFilter);
    }
    // Service filter
    if ($serviceFilter !== 'all' && !empty($serviceFilter)) {
        $whereClauses[] = "(LOWER(service) LIKE :service_filter OR LOWER(service_interested) LIKE :service_filter)";
        $params[':service_filter'] = '%' . strtolower($serviceFilter) . '%';
    }
}

if (!empty($dateFilter)) {
    $whereClauses[] = "created_at LIKE :date_filter";
    $params[':date_filter'] = $dateFilter . '%';
}

if (!empty($searchQuery)) {
    $whereClauses[] = "(name LIKE :q OR company LIKE :q OR email LIKE :q OR phone LIKE :q OR service LIKE :q OR service_interested LIKE :q)";
    $params[':q'] = '%' . $searchQuery . '%';
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Count for pagination
$countSql = "SELECT COUNT(*) FROM leads {$whereSql}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $perPage));

// Main Query
if ($viewMode === 'followups') {
    // Sort by nearest follow-up date
    $orderBy = "ORDER BY next_followup_at ASC";
} else {
    $orderBy = "ORDER BY id DESC";
}

$querySql = "
    SELECT id, client_id, name, company, email, phone,
           COALESCE(service, service_interested, 'Website') as service,
           budget, message, source, status,
           COALESCE(call_status, 'Not Called') as call_status,
           last_called_at, next_followup_at, notes, created_at, updated_at
    FROM leads
    {$whereSql}
    {$orderBy}
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $pdo->prepare($querySql);
$stmt->execute($params);
$leadsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counts for filter pills
$counts = [
    'all'            => (int)$pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn(),
    'not_called'     => (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE LOWER(COALESCE(call_status, 'Not Called')) = 'not called'")->fetchColumn(),
    'called'         => (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE LOWER(call_status) = 'called'")->fetchColumn(),
    'callback'       => (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE LOWER(call_status) = 'call back'")->fetchColumn(),
    'no_answer'      => (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE LOWER(call_status) = 'no answer'")->fetchColumn(),
    'followups_due'  => (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE next_followup_at IS NOT NULL AND next_followup_at != ''")->fetchColumn(),
];

require_once dirname(__DIR__) . '/includes/admin_header.php';
?>

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

  <!-- Page Header & View Switcher -->
  <div class="page-header" style="align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
    <div>
      <h1 class="page-title">Leads &amp; Call Tracking</h1>
      <p class="page-subtitle">Track incoming prospects, monitor contact status, log calls, and manage follow-ups.</p>
    </div>

    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
      <!-- View Mode Switcher -->
      <div class="view-mode-tabs">
        <a href="?view=all" class="view-mode-btn <?= $viewMode === 'all' ? 'active' : '' ?>">
          <span>All Leads</span>
          <span class="badge-counter"><?= $counts['all'] ?></span>
        </a>
        <a href="?view=followups" class="view-mode-btn <?= $viewMode === 'followups' ? 'active' : '' ?>">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <span>Follow-ups Due</span>
          <span class="badge-counter" style="<?= $counts['followups_due'] > 0 ? 'background: #ef4444; color: #ffffff;' : '' ?>">
            <?= $counts['followups_due'] ?>
          </span>
        </a>
      </div>

      <!-- Actions: Add Lead + Import Excel -->
      <div style="display: flex; gap: 8px; align-items: center;">
        <button type="button" class="btn-primary-admin" onclick="openAddLeadModal()" style="padding: 9px 16px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;" title="Create new lead prospect">
          <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          <span>Add Lead</span>
        </button>

        <button type="button" class="btn-action" onclick="openExcelImportModal()" style="padding: 9px 16px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; background: #ffffff; border: 1px solid var(--border-light); border-radius: var(--radius-sm); color: var(--text-dark); font-weight: 600;" title="Import leads from Excel (.xlsx) or CSV">
          <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
          </svg>
          <span>Import Excel</span>
        </button>
      </div>
    </div>
  </div>

  <?php if ($viewMode === 'all'): ?>
    <!-- =========================================================
         ALL LEADS VIEW (FILTERS + TABLE)
    ========================================================= -->

    <!-- Filters & Search Toolbar -->
    <div class="filter-bar" style="margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 16px; justify-content: space-between; align-items: center;">
      <!-- Call Status Tabs -->
      <div class="filter-pills" style="display: flex; flex-wrap: wrap; gap: 6px;">
        <a href="?view=all&call_status=all<?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?><?= $leadFilter !== 'all' ? '&lead_status=' . urlencode($leadFilter) : '' ?>"
           class="filter-pill <?= $callFilter === 'all' ? 'active' : '' ?>">
          All (<?= $counts['all'] ?>)
        </a>
        <a href="?view=all&call_status=Not+Called<?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?><?= $leadFilter !== 'all' ? '&lead_status=' . urlencode($leadFilter) : '' ?>"
           class="filter-pill <?= strtolower($callFilter) === 'not called' ? 'active' : '' ?>">
          <span class="dot" style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#94a3b8; margin-right:4px;"></span>
          Not Called (<?= $counts['not_called'] ?>)
        </a>
        <a href="?view=all&call_status=Called<?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?><?= $leadFilter !== 'all' ? '&lead_status=' . urlencode($leadFilter) : '' ?>"
           class="filter-pill <?= strtolower($callFilter) === 'called' ? 'active' : '' ?>">
          <span class="dot" style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#10b981; margin-right:4px;"></span>
          Called (<?= $counts['called'] ?>)
        </a>
        <a href="?view=all&call_status=Call+Back<?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?><?= $leadFilter !== 'all' ? '&lead_status=' . urlencode($leadFilter) : '' ?>"
           class="filter-pill <?= strtolower($callFilter) === 'call back' ? 'active' : '' ?>">
          <span class="dot" style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#c026d3; margin-right:4px;"></span>
          Call Back (<?= $counts['callback'] ?>)
        </a>
        <a href="?view=all&call_status=No+Answer<?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?><?= $leadFilter !== 'all' ? '&lead_status=' . urlencode($leadFilter) : '' ?>"
           class="filter-pill <?= strtolower($callFilter) === 'no answer' ? 'active' : '' ?>">
          <span class="dot" style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#f59e0b; margin-right:4px;"></span>
          No Answer (<?= $counts['no_answer'] ?>)
        </a>
      </div>

      <!-- Lead Status & Search Form -->
      <form method="GET" action="leads.php" style="display: flex; gap: 10px; align-items: center;">
        <input type="hidden" name="view" value="all">
        <input type="hidden" name="call_status" value="<?= e($callFilter) ?>">

        <!-- Lead Status Dropdown -->
        <select name="lead_status" class="filter-select" onchange="this.form.submit()" style="padding: 7px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; background: #ffffff;">
          <option value="all" <?= $leadFilter === 'all' ? 'selected' : '' ?>>All Lead Stages</option>
          <?php foreach ($validLeadStatuses as $st): ?>
            <option value="<?= e($st) ?>" <?= strcasecmp($leadFilter, $st) === 0 ? 'selected' : '' ?>><?= e($st) ?></option>
          <?php endforeach; ?>
        </select>

        <!-- Search Input -->
        <div class="search-input-wrap" style="position: relative;">
          <input type="text" name="q" value="<?= e($searchQuery) ?>" placeholder="Search leads..." class="search-input" style="padding: 7px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; width: 200px;">
        </div>
        <button type="submit" class="btn-action" style="padding: 7px 12px;">Search</button>
        <?php if (!empty($searchQuery) || $leadFilter !== 'all' || $callFilter !== 'all'): ?>
          <a href="leads.php?view=all" class="btn-action" style="padding: 7px 10px; background: #ffffff;" title="Reset filters">✕</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Leads Table Card -->
    <div class="data-card">
      <div class="table-responsive">
        <?php if (empty($leadsList)): ?>
          <div class="empty-state-banner">
            <div class="empty-state-icon">📋</div>
            <div class="empty-state-title">No leads match your criteria</div>
            <p>Try adjusting your search query or status filter.</p>
          </div>
        <?php else: ?>
          <table class="admin-table">
            <thead>
              <tr>
                <th style="width: 40px; text-align: center;">
                  <input type="checkbox" id="selectAllLeads" class="bulk-select-all" title="Select All Leads">
                </th>
                <th>Lead</th>
                <th>Company</th>
                <th>Service</th>
                <th>Budget</th>
                <th>Phone</th>
                <th>Lead Status</th>
                <th>Call Status</th>
                <th>Last Called</th>
                <th>Next Follow-up</th>
                <th style="text-align: right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($leadsList as $lead): ?>
                <?php
                  $isOverdue = !empty($lead['next_followup_at']) && (strtotime($lead['next_followup_at']) < time());
                  $callStatusNorm = strtolower(trim($lead['call_status']));
                  $callBadgeClass = match ($callStatusNorm) {
                      'called'         => 'badge-called',
                      'call back'      => 'badge-callback',
                      'no answer'      => 'badge-no-answer',
                      'not interested' => 'badge-not-interested',
                      default          => 'badge-not-called'
                  };
                ?>
                <tr class="<?= $isOverdue ? 'row-overdue' : '' ?>">
                  <td style="width: 40px; text-align: center;">
                    <input type="checkbox" class="lead-select-checkbox row-select-checkbox" value="<?= (int)$lead['id'] ?>">
                  </td>
                  <!-- 1. Lead Name & Email -->
                  <td>
                    <strong><?= e($lead['name']) ?></strong>
                    <div style="font-size: 11px; color: var(--text-muted);"><?= e($lead['email']) ?></div>
                  </td>

                  <!-- 2. Company -->
                  <td style="color: var(--text-dark);">
                    <?= e($lead['company'] ?: '—') ?>
                  </td>

                  <!-- 3. Service -->
                  <td>
                    <span style="font-family: 'DM Mono', monospace; font-size: 11px; background: var(--main-bg); padding: 3px 8px; border-radius: 4px;">
                      <?= e($lead['service']) ?>
                    </span>
                  </td>

                  <!-- 3b. Budget -->
                  <td>
                    <span style="font-family: 'DM Mono', monospace; font-size: 11px; font-weight: 600; color: #047857;">
                      <?= !empty($lead['budget']) ? e($lead['budget']) : '—' ?>
                    </span>
                  </td>

                  <!-- 4. Phone -->
                  <td>
                    <?php if (!empty($lead['phone'])): ?>
                      <a href="tel:<?= e($lead['phone']) ?>" style="font-family: 'DM Mono', monospace; font-size: 12px; color: var(--text-dark); text-decoration: underline; text-underline-offset: 2px;">
                        <?= e($lead['phone']) ?>
                      </a>
                    <?php else: ?>
                      <span style="color: var(--text-light);">—</span>
                    <?php endif; ?>
                  </td>

                  <!-- 5. Lead Status -->
                  <td>
                    <span class="status-pill status-<?= strtolower(str_replace(' ', '-', $lead['status'])) ?>">
                      <?= e($lead['status']) ?>
                    </span>
                  </td>

                  <!-- 6. Call Status -->
                  <td>
                    <span class="badge-call-status <?= $callBadgeClass ?>">
                      <span class="dot"></span>
                      <?= e($lead['call_status']) ?>
                    </span>
                  </td>

                  <!-- 7. Last Called -->
                  <td class="col-last-called" style="font-family: 'DM Mono', monospace; font-size: 11px; color: var(--text-muted);">
                    <?= !empty($lead['last_called_at']) ? e(format_date($lead['last_called_at'], 'M j, H:i')) : '—' ?>
                  </td>

                  <!-- 8. Next Follow-up -->
                  <td>
                    <?php if (!empty($lead['next_followup_at'])): ?>
                      <div style="font-family: 'DM Mono', monospace; font-size: 11px; <?= $isOverdue ? 'color: #dc2626; font-weight: 700;' : 'color: var(--text-dark);' ?>">
                        <?= e(format_date($lead['next_followup_at'], 'M j, H:i')) ?>
                        <?php if ($isOverdue): ?>
                          <span class="badge-overdue" style="font-size: 9px; padding: 1px 4px; margin-left: 4px;">Overdue</span>
                        <?php endif; ?>
                      </div>
                    <?php else: ?>
                      <span style="color: var(--text-light); font-family: 'DM Mono', monospace; font-size: 11px;">—</span>
                    <?php endif; ?>
                  </td>

                  <!-- 9. Actions -->
                  <td style="text-align: right; white-space: nowrap;">
                    <!-- CALL Button -->
                    <button type="button"
                            class="btn-call-action"
                            onclick="triggerClickToCall(<?= (int)$lead['id'] ?>, this)"
                            title="Click-to-Call prospect via Exotel">
                      <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                      </svg>
                      <span class="btn-call-text">CALL</span>
                    </button>

                    <!-- History Button -->
                    <button type="button"
                            class="btn-history-action"
                            onclick="openHistoryModal(<?= (int)$lead['id'] ?>, '<?= e(addslashes($lead['name'])) ?>')"
                            title="View Complete Call History">
                      <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                    </button>

                    <!-- Client Convert / View Button -->
                    <?php if (!empty($lead['client_id'])): ?>
                      <a href="clients.php?search=<?= urlencode($lead['email']) ?>"
                         class="btn-action"
                         style="padding: 4px 8px; font-size: 11px; background: #ecfdf5; color: #065f46; border-color: #a7f3d0; text-decoration: none;"
                         title="View Linked Client #<?= (int)$lead['client_id'] ?>">
                        ✓ Client
                      </a>
                    <?php else: ?>
                      <button type="button"
                              class="btn-action"
                              style="padding: 4px 8px; font-size: 11px; background: #f0fdf4; color: #166534; border-color: #bbf7d0;"
                              onclick="convertToClient(<?= (int)$lead['id'] ?>, '<?= e(addslashes($lead['name'])) ?>')"
                              title="Create Client from this Lead">
                        + Client
                      </button>
                    <?php endif; ?>

                    <!-- Edit Button -->
                    <button type="button"
                            class="btn-action"
                            style="padding: 4px 8px; font-size: 11px; background: #ffffff;"
                            onclick='openEditLeadModal(<?= json_encode($lead, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                            title="Edit Lead Information">
                      Edit
                    </button>

                    <!-- Status Quick Selector -->
                    <select onchange="updateLeadStatus(<?= (int)$lead['id'] ?>, this.value)" style="padding: 4px 6px; font-size: 11px; border-radius: 6px; border: 1px solid var(--border-light); background: #ffffff; color: var(--text-dark); cursor: pointer;" title="Change Lead Stage">
                      <?php foreach ($validLeadStatuses as $st): ?>
                        <option value="<?= e($st) ?>" <?= strcasecmp($lead['status'], $st) === 0 ? 'selected' : '' ?>><?= e($st) ?></option>
                      <?php endforeach; ?>
                    </select>

                    <!-- Delete button -->
                    <button type="button"
                            onclick="confirmDeleteLead(<?= (int)$lead['id'] ?>, '<?= e(addslashes($lead['name'])) ?>')"
                            style="color: var(--text-muted); padding: 4px 6px; font-size: 12px; cursor: pointer;"
                            title="Delete Lead">
                      ✕
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <!-- Pagination Bar -->
      <?php if ($totalPages > 1): ?>
        <div class="pagination-bar">
          <div class="pagination-info">
            Showing <?= $offset + 1 ?> to <?= min($totalRecords, $offset + $perPage) ?> of <?= $totalRecords ?> leads
          </div>
          <div class="pagination-links">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
              <a href="?view=all&page=<?= $p ?>&call_status=<?= urlencode($callFilter) ?>&lead_status=<?= urlencode($leadFilter) ?>&q=<?= urlencode($searchQuery) ?>"
                 class="page-btn <?= $p === $page ? 'active' : '' ?>">
                <?= $p ?>
              </a>
            <?php endfor; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

  <?php else: ?>
    <!-- =========================================================
         FOLLOW-UPS DUE VIEW
         Show: Client, Phone, Last Call, Next Follow-up, Status
         Sort by nearest follow-up date
    ========================================================= -->
    <div class="data-card">
      <div class="data-card-header">
        <div>
          <h2 class="data-card-title">Follow-ups Due</h2>
          <div style="font-size: 12px; color: var(--text-muted);">Scheduled client and prospect calls sorted by nearest follow-up deadline</div>
        </div>
        <a href="?view=all" class="btn-action">&larr; Back to All Leads</a>
      </div>

      <div class="table-responsive">
        <?php if (empty($leadsList)): ?>
          <div class="empty-state-banner">
            <div class="empty-state-icon">✓</div>
            <div class="empty-state-title">No follow-ups scheduled.</div>
            <p>All scheduled lead calls have been completed or attended to.</p>
          </div>
        <?php else: ?>
          <table class="admin-table">
            <thead>
              <tr>
                <th style="width: 40px; text-align: center;">
                  <input type="checkbox" id="selectAllFollowupLeads" class="bulk-select-all" title="Select All Follow-ups">
                </th>
                <th>Client / Lead</th>
                <th>Phone</th>
                <th>Last Call</th>
                <th>Next Follow-up</th>
                <th>Status</th>
                <th style="text-align: right;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($leadsList as $lead): ?>
                <?php
                  $isOverdue = !empty($lead['next_followup_at']) && (strtotime($lead['next_followup_at']) < time());
                  $callBadgeClass = match (strtolower(trim($lead['call_status']))) {
                      'called'         => 'badge-called',
                      'call back'      => 'badge-callback',
                      'no answer'      => 'badge-no-answer',
                      'not interested' => 'badge-not-interested',
                      default          => 'badge-not-called'
                  };
                ?>
                <tr class="<?= $isOverdue ? 'row-overdue' : '' ?>">
                  <td style="width: 40px; text-align: center;">
                    <input type="checkbox" class="lead-select-checkbox row-select-checkbox" value="<?= (int)$lead['id'] ?>">
                  </td>
                  <!-- Client / Lead -->
                  <td>
                    <strong><?= e($lead['name']) ?></strong>
                    <?php if (!empty($lead['company'])): ?>
                      <div style="font-size: 11px; color: var(--text-muted);"><?= e($lead['company']) ?></div>
                    <?php endif; ?>
                    <div style="font-size: 11px; color: var(--text-light);"><?= e($lead['email']) ?></div>
                  </td>

                  <!-- Phone -->
                  <td>
                    <?php if (!empty($lead['phone'])): ?>
                      <a href="tel:<?= e($lead['phone']) ?>" style="font-family: 'DM Mono', monospace; font-size: 12px; color: var(--text-dark); text-decoration: underline; text-underline-offset: 2px;">
                        <?= e($lead['phone']) ?>
                      </a>
                    <?php else: ?>
                      <span style="color: var(--text-light);">—</span>
                    <?php endif; ?>
                  </td>

                  <!-- Last Call -->
                  <td style="font-family: 'DM Mono', monospace; font-size: 11px; color: var(--text-muted);">
                    <?= !empty($lead['last_called_at']) ? e(format_date($lead['last_called_at'], 'M j, H:i')) : '—' ?>
                  </td>

                  <!-- Next Follow-up (with Overdue indicator) -->
                  <td>
                    <div style="font-family: 'DM Mono', monospace; font-size: 12px; <?= $isOverdue ? 'color: #dc2626; font-weight: 700;' : 'color: var(--text-dark);' ?>">
                      <?= e(format_date($lead['next_followup_at'], 'M j, Y — H:i')) ?>
                      <?php if ($isOverdue): ?>
                        <span class="badge-overdue" style="margin-left: 6px;">Overdue</span>
                      <?php else: ?>
                        <span class="badge-upcoming" style="margin-left: 6px;">Upcoming</span>
                      <?php endif; ?>
                    </div>
                  </td>

                  <!-- Status -->
                  <td>
                    <div style="display: flex; gap: 6px; align-items: center;">
                      <span class="status-pill status-<?= strtolower(str_replace(' ', '-', $lead['status'])) ?>">
                        <?= e($lead['status']) ?>
                      </span>
                      <span class="badge-call-status <?= $callBadgeClass ?>">
                        <span class="dot"></span>
                        <?= e($lead['call_status']) ?>
                      </span>
                    </div>
                  </td>

                  <!-- Action -->
                  <td style="text-align: right;">
                    <button type="button"
                            class="btn-call-action"
                            onclick="triggerClickToCall(<?= (int)$lead['id'] ?>, this)"
                            title="Click-to-Call prospect via Exotel">
                      <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                      </svg>
                      <span class="btn-call-text">CALL</span>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- =========================================================
       MODAL 1: LOG CALL MODAL
  ========================================================= -->
  <div id="callModal" class="modal-overlay" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-header">
        <div>
          <div class="modal-title">Log Prospect Call</div>
          <div id="modalLeadSubtitle" style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"></div>
        </div>
        <button type="button" class="modal-close-btn" onclick="closeCallModal()">✕</button>
      </div>

      <form method="POST" action="leads.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="log_call">
        <input type="hidden" name="lead_id" id="callLeadId" value="0">

        <div class="modal-body">
          <!-- Call Date / Time -->
          <div class="form-group" style="margin-bottom: 16px;">
            <label class="form-label" style="font-weight: 600; font-size: 13px; color: var(--text-dark); margin-bottom: 6px; display: block;">
              Call Date &amp; Time *
            </label>
            <input type="datetime-local"
                   name="call_datetime"
                   id="callDateTimeInput"
                   class="form-control"
                   required
                   style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace; font-size: 12px;">
          </div>

          <!-- Call Outcome -->
          <div class="form-group" style="margin-bottom: 16px;">
            <label class="form-label" style="font-weight: 600; font-size: 13px; color: var(--text-dark); margin-bottom: 6px; display: block;">
              Call Outcome *
            </label>
            <div class="outcome-options-grid">
              <label class="outcome-radio-label">
                <input type="radio" name="outcome" value="Connected" checked onchange="handleOutcomeChange('Connected')">
                <span>Connected</span>
              </label>
              <label class="outcome-radio-label">
                <input type="radio" name="outcome" value="No Answer" onchange="handleOutcomeChange('No Answer')">
                <span>No Answer</span>
              </label>
              <label class="outcome-radio-label">
                <input type="radio" name="outcome" value="Call Back" onchange="handleOutcomeChange('Call Back')">
                <span>Call Back</span>
              </label>
              <label class="outcome-radio-label">
                <input type="radio" name="outcome" value="Not Interested" onchange="handleOutcomeChange('Not Interested')">
                <span>Not Interested</span>
              </label>
              <label class="outcome-radio-label">
                <input type="radio" name="outcome" value="Converted" onchange="handleOutcomeChange('Converted')">
                <span>Converted</span>
              </label>
            </div>
          </div>

          <!-- Next Follow-up Date -->
          <div class="form-group" id="nextFollowupGroup" style="margin-bottom: 16px;">
            <label class="form-label" style="font-weight: 600; font-size: 13px; color: var(--text-dark); margin-bottom: 6px; display: block;">
              Next Follow-up Date <span id="followupOptionalTag" style="font-weight: normal; color: var(--text-muted);">(Optional)</span>
            </label>
            <input type="datetime-local"
                   name="next_followup_at"
                   id="nextFollowupInput"
                   class="form-control"
                   style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace; font-size: 12px;">
          </div>

          <!-- Notes -->
          <div class="form-group" style="margin-bottom: 8px;">
            <label class="form-label" style="font-weight: 600; font-size: 13px; color: var(--text-dark); margin-bottom: 6px; display: block;">
              Call Notes &amp; Summary
            </label>
            <textarea name="notes"
                      rows="3"
                      class="form-control"
                      placeholder="Discussed scope requirements, pricing feedback, or next steps..."
                      style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px; line-height: 1.5; resize: vertical;"></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-action" style="background: var(--main-bg);" onclick="closeCallModal()">Cancel</button>
          <button type="submit" class="btn-primary-admin" style="padding: 8px 18px; font-size: 13px;">Save Call Record</button>
        </div>
      </form>
    </div>
  </div>

  <!-- =========================================================
       MODAL 2: COMPLETE CALL HISTORY MODAL
  ========================================================= -->
  <div id="historyModal" class="modal-overlay" aria-hidden="true">
    <div class="modal-dialog" style="max-width: 580px;">
      <div class="modal-header">
        <div>
          <div class="modal-title">Call History</div>
          <div id="historyLeadSubtitle" style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"></div>
        </div>
        <button type="button" class="modal-close-btn" onclick="closeHistoryModal()">✕</button>
      </div>

      <div class="modal-body">
        <div id="historyLoading" style="text-align: center; padding: 24px; color: var(--text-muted);">
          Loading call history...
        </div>
        <div id="historyContainer" class="call-history-list" style="display: none;"></div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-action" style="background: var(--main-bg);" onclick="closeHistoryModal()">Close</button>
      </div>
    </div>
  </div>

  <!-- =========================================================
       MODAL 3: EDIT LEAD MODAL
  ========================================================= -->
  <div id="editLeadModal" class="modal-overlay" aria-hidden="true">
    <div class="modal-dialog modal-dialog-lg">
      <div class="modal-header">
        <div>
          <div class="modal-title">Edit Lead Prospect</div>
          <div id="editLeadSubtitle" style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"></div>
        </div>
        <button type="button" class="modal-close-btn" onclick="closeEditLeadModal()">✕</button>
      </div>

      <form method="POST" action="leads.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="edit_lead">
        <input type="hidden" name="lead_id" id="editLeadId" value="0">

        <div class="modal-body">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">Full Name *</label>
              <input type="text" name="name" id="editLeadName" required class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px;">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">Company / Brand</label>
              <input type="text" name="company" id="editLeadCompany" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px;">
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">Email Address *</label>
              <input type="email" name="email" id="editLeadEmail" required class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px;">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">Phone Number</label>
              <input type="text" name="phone" id="editLeadPhone" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace; font-size: 12px;">
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">Service Required</label>
              <input type="text" name="service" id="editLeadService" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px;">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">Budget Range</label>
              <input type="text" name="budget" id="editLeadBudget" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px;">
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 14px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">Lead Stage</label>
              <select name="status" id="editLeadStatus" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; background: #ffffff;">
                <?php foreach ($validLeadStatuses as $st): ?>
                  <option value="<?= e($st) ?>"><?= e($st) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">Call Status</label>
              <select name="call_status" id="editLeadCallStatus" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; background: #ffffff;">
                <?php foreach ($validCallStatuses as $cs): ?>
                  <option value="<?= e($cs) ?>"><?= e($cs) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">Source</label>
              <input type="text" name="source" id="editLeadSource" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px;">
            </div>
          </div>

          <div class="form-group" style="margin-bottom: 6px;">
            <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">Notes / Scope</label>
            <textarea name="notes" id="editLeadNotes" rows="3" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; line-height: 1.5; resize: vertical;"></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-action" style="background: var(--main-bg);" onclick="closeEditLeadModal()">Cancel</button>
          <button type="submit" class="btn-primary-admin" style="padding: 8px 18px; font-size: 13px;">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <!-- =========================================================
       MODAL 4: ADD LEAD MODAL
  ========================================================= -->
  <div id="addLeadModal" class="modal-overlay" aria-hidden="true">
    <div class="modal-dialog modal-dialog-lg">
      <div class="modal-header">
        <div>
          <div class="modal-title">Add New Lead Prospect</div>
          <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Create a commercial inquiry record in the CRM pipeline</div>
        </div>
        <button type="button" class="modal-close-btn" onclick="closeAddLeadModal()">✕</button>
      </div>

      <form method="POST" action="leads.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_lead">

        <div class="modal-body">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">Contact / Full Name *</label>
              <input type="text" name="name" required class="form-control" placeholder="e.g. Rajesh Sharma" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px;">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">Company / Organization</label>
              <input type="text" name="company" class="form-control" placeholder="e.g. Nexus FinTech Ltd" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px;">
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">Phone Number *</label>
              <input type="text" name="phone" required class="form-control" placeholder="e.g. +91 98765 43210" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace; font-size: 12px;">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">Email Address</label>
              <input type="email" name="email" class="form-control" placeholder="e.g. rajesh@nexusfintech.com" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px;">
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">Service Required</label>
              <select name="service" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; background: #ffffff;">
                <?php foreach ($validServices as $svc): ?>
                  <option value="<?= e($svc) ?>"><?= e($svc) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">Estimated Budget</label>
              <input type="text" name="budget" class="form-control" placeholder="e.g. ₹2,50,000 - ₹5,00,000" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px;">
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 14px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">Initial Stage</label>
              <select name="status" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; background: #ffffff;">
                <?php foreach ($validLeadStatuses as $st): ?>
                  <option value="<?= e($st) ?>" <?= $st === 'New' ? 'selected' : '' ?>><?= e($st) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">Call Status</label>
              <select name="call_status" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; background: #ffffff;">
                <?php foreach ($validCallStatuses as $cs): ?>
                  <option value="<?= e($cs) ?>" <?= $cs === 'Not Called' ? 'selected' : '' ?>><?= e($cs) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">Lead Source</label>
              <select name="source" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; background: #ffffff;">
                <?php foreach ($validSources as $src): ?>
                  <option value="<?= e($src) ?>" <?= $src === 'Direct Phone' ? 'selected' : '' ?>><?= e($src) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-group" style="margin-bottom: 6px;">
            <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">Requirements / Internal Notes</label>
            <textarea name="notes" rows="3" class="form-control" placeholder="Project goals, timeline, initial inquiry specifics..." style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; line-height: 1.5; resize: vertical;"></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-action" style="background: var(--main-bg);" onclick="closeAddLeadModal()">Cancel</button>
          <button type="submit" class="btn-primary-admin" style="padding: 8px 20px; font-size: 13px;">Create Lead</button>
        </div>
      </form>
    </div>
  </div>

  <!-- =========================================================
       MODAL 5: EXCEL & CSV LEAD IMPORT MODAL (PHASE 8)
  ========================================================= -->
  <div id="excelImportModal" class="modal-overlay" aria-hidden="true">
    <div class="modal-dialog" style="max-width: 960px; width: 95%; max-height: 92vh; display: flex; flex-direction: column; overflow: hidden; padding: 0;">
      <!-- Modal Header -->
      <div class="modal-header" style="padding: 20px 24px; border-bottom: 1px solid var(--border-light); background: #ffffff;">
        <div>
          <div class="modal-title" style="display: flex; align-items: center; gap: 8px;">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color: var(--primary);">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span>Import Leads from Spreadsheet</span>
          </div>
          <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
            Upload commercial prospects via Excel (.xlsx) or CSV (.csv) with pre-import validation &amp; duplicate detection
          </div>
        </div>
        <button type="button" class="modal-close-btn" onclick="closeExcelImportModal()" style="font-size: 18px; line-height: 1;">✕</button>
      </div>

      <!-- Modal Body (Scrollable Container) -->
      <div class="modal-body" style="padding: 24px; overflow-y: auto; flex: 1;">

        <!-- =====================================================
             STEP 1: UPLOAD SPREADSHEET
        ===================================================== -->
        <div id="importStepUpload">
          <!-- Drop Area -->
          <div id="excelDropZone" style="border: 2px dashed #cbd5e1; border-radius: 12px; padding: 36px 20px; text-align: center; background: #f8fafc; cursor: pointer; transition: all 0.2s ease;" onclick="document.getElementById('excelFileInput').click()">
            <input type="file" id="excelFileInput" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" style="display: none;" onchange="handleExcelFileSelect(this)">
            <div style="width: 48px; height: 48px; background: #e0e7ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; color: #4338ca;">
              <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
              </svg>
            </div>
            <div style="font-size: 14px; font-weight: 600; color: var(--text-dark); margin-bottom: 4px;">
              Choose an Excel (.xlsx) or CSV (.csv) file
            </div>
            <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">
              or drag and drop your spreadsheet here (Maximum size: 10 MB)
            </div>
            <div style="display: inline-flex; align-items: center; gap: 8px;">
              <span class="status-pill status-active" style="font-size: 11px;">.XLSX</span>
              <span class="status-pill status-contacted" style="font-size: 11px;">.CSV</span>
              <span style="font-size: 11px; color: var(--text-muted); font-family: 'DM Mono', monospace;">Max 10 MB</span>
            </div>
          </div>

          <!-- Selected File Feedback -->
          <div id="selectedFileInfo" style="display: none; margin-top: 14px; padding: 12px 16px; background: #f1f5f9; border: 1px solid var(--border-light); border-radius: 8px; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
              <div style="font-size: 20px;">📄</div>
              <div>
                <div id="selectedFileName" style="font-weight: 600; font-size: 13px; color: var(--text-dark);">filename.xlsx</div>
                <div id="selectedFileSize" style="font-size: 11px; color: var(--text-muted); font-family: 'DM Mono', monospace;">0 KB</div>
              </div>
            </div>
            <button type="button" class="btn-action" style="padding: 5px 12px; font-size: 11px;" onclick="resetExcelFileSelection()">Change File</button>
          </div>

          <!-- Error Alert for Upload Step -->
          <div id="uploadStepAlert" class="admin-alert alert-error" style="display: none; margin-top: 14px;">
            <span id="uploadStepAlertText"></span>
          </div>

          <!-- Loading Spinner during upload -->
          <div id="uploadProgressBox" style="display: none; text-align: center; padding: 20px; color: var(--text-muted); font-size: 13px;">
            <div style="display: inline-block; width: 24px; height: 24px; border: 3px solid #e2e8f0; border-top-color: var(--primary); border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 8px;"></div>
            <div>Reading spreadsheet, checking columns, and validating rows...</div>
          </div>

          <!-- Column Guidance & Template Card -->
          <div style="margin-top: 20px; background: #ffffff; border: 1px solid var(--border-light); border-radius: 10px; padding: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
              <strong style="font-size: 12px; color: var(--text-dark); text-transform: uppercase; letter-spacing: 0.5px;">Supported Spreadsheet Columns</strong>
              <a href="leads.php?action=download_import_template" class="btn-action" style="padding: 5px 10px; font-size: 11px; background: #f8fafc;" title="Download ready-to-use CSV template">
                📥 Download Sample Template (.csv)
              </a>
            </div>
            <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px; line-height: 1.5;">
              Column headers are matched <strong>case-insensitively</strong>. Each lead requires a <strong>Name</strong> and at least one contact method (<strong>Email</strong> or <strong>Phone</strong>).
            </p>
            <div style="display: flex; flex-wrap: wrap; gap: 6px;">
              <span class="status-pill status-published" title="Mandatory field">Name *</span>
              <span class="status-pill status-contacted" title="Either Email or Phone is required">Email *</span>
              <span class="status-pill status-contacted" title="Either Email or Phone is required">Phone *</span>
              <span class="status-pill" style="background: #f1f5f9; color: #475569;">Company</span>
              <span class="status-pill" style="background: #f1f5f9; color: #475569;">Service</span>
              <span class="status-pill" style="background: #f1f5f9; color: #475569;">Budget</span>
              <span class="status-pill" style="background: #f1f5f9; color: #475569;">Message</span>
              <span class="status-pill" style="background: #f1f5f9; color: #475569;">Source</span>
              <span class="status-pill" style="background: #f1f5f9; color: #475569;">Status</span>
              <span class="status-pill" style="background: #f1f5f9; color: #475569;">Call Status</span>
              <span class="status-pill" style="background: #f1f5f9; color: #475569;">Notes</span>
              <span class="status-pill" style="background: #f1f5f9; color: #475569;">Next Follow-up</span>
            </div>
          </div>
        </div>

        <!-- =====================================================
             STEP 2: PREVIEW & VALIDATION BREAKDOWN
        ===================================================== -->
        <div id="importStepPreview" style="display: none;">
          <!-- Top KPI Metrics Counters -->
          <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px;">
            <div style="background: #f8fafc; border: 1px solid var(--border-light); border-radius: 8px; padding: 12px 14px;">
              <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Rows</div>
              <div id="kpiTotalRows" style="font-size: 22px; font-weight: 700; color: var(--text-dark); margin-top: 4px; font-family: 'DM Mono', monospace;">0</div>
            </div>
            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 12px 14px;">
              <div style="font-size: 11px; color: #065f46; font-weight: 600; text-transform: uppercase;">Valid Leads</div>
              <div id="kpiValidRows" style="font-size: 22px; font-weight: 700; color: #047857; margin-top: 4px; font-family: 'DM Mono', monospace;">0</div>
            </div>
            <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 14px;">
              <div style="font-size: 11px; color: #92400e; font-weight: 600; text-transform: uppercase;">Duplicates</div>
              <div id="kpiDuplicateRows" style="font-size: 22px; font-weight: 700; color: #b45309; margin-top: 4px; font-family: 'DM Mono', monospace;">0</div>
            </div>
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 14px;">
              <div style="font-size: 11px; color: #991b1b; font-weight: 600; text-transform: uppercase;">Invalid Rows</div>
              <div id="kpiInvalidRows" style="font-size: 22px; font-weight: 700; color: #b91c1c; margin-top: 4px; font-family: 'DM Mono', monospace;">0</div>
            </div>
          </div>

          <!-- Validation Errors Box (Shown if Invalid Rows > 0) -->
          <div id="validationErrorsCard" style="display: none; background: #fff1f2; border: 1px solid #fecdd3; border-radius: 8px; padding: 14px 16px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">
              <div style="font-weight: 600; font-size: 13px; color: #9f1239; display: flex; align-items: center; gap: 6px;">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span>Validation Errors Detected (<span id="errTotalCount">0</span> rows skipped)</span>
              </div>
              <a id="btnDownloadErrorReport" href="#" class="btn-action" style="padding: 4px 10px; font-size: 11px; background: #ffffff; border-color: #fda4af; color: #9f1239; text-decoration: none;">
                📥 Download Error Report (.csv)
              </a>
            </div>
            <div id="validationErrorsList" style="max-height: 110px; overflow-y: auto; font-family: 'DM Mono', monospace; font-size: 11px; color: #881337; line-height: 1.6;">
            </div>
          </div>

          <!-- Preview Table Card -->
          <div style="border: 1px solid var(--border-light); border-radius: 8px; overflow: hidden; margin-bottom: 20px;">
            <div style="padding: 10px 14px; background: #f8fafc; border-bottom: 1px solid var(--border-light); font-size: 12px; font-weight: 600; color: var(--text-dark); display: flex; justify-content: space-between; align-items: center;">
              <span>Spreadsheet Preview (Showing first rows)</span>
              <span style="font-size: 11px; color: var(--text-muted); font-weight: normal;">Review parsed columns before database insertion</span>
            </div>
            <div style="max-height: 240px; overflow-y: auto; overflow-x: auto;">
              <table class="admin-table" style="margin: 0; font-size: 12px;">
                <thead>
                  <tr>
                    <th style="width: 45px;">Row</th>
                    <th>Name</th>
                    <th>Company</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Service</th>
                    <th>Budget</th>
                    <th>Status</th>
                    <th>Call Status</th>
                    <th>Row Status</th>
                  </tr>
                </thead>
                <tbody id="previewTableBody">
                  <!-- Injected dynamically by JS -->
                </tbody>
              </table>
            </div>
          </div>

          <!-- Duplicate Resolution Policy Card -->
          <div style="background: #ffffff; border: 1px solid var(--border-light); border-radius: 8px; padding: 16px; margin-bottom: 20px;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text-dark); margin-bottom: 4px;">
              Duplicate Lead Handling
            </div>
            <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">
              Duplicates are flagged when an email or phone number matches an existing lead in the CRM or appears multiple times in this spreadsheet:
            </div>
            <div style="display: flex; flex-direction: column; gap: 8px;">
              <label style="display: flex; align-items: flex-start; gap: 8px; font-size: 12px; color: var(--text-dark); cursor: pointer;">
                <input type="radio" name="dupPolicy" value="skip" checked onchange="handleDupPolicyChange('skip')" style="margin-top: 2px;">
                <div>
                  <strong>Skip duplicates (Default — Recommended)</strong>
                  <div style="font-size: 11px; color: var(--text-muted);">Only import new, unique prospects. Duplicate rows will be safely excluded.</div>
                </div>
              </label>
              <label style="display: flex; align-items: flex-start; gap: 8px; font-size: 12px; color: var(--text-dark); cursor: pointer;">
                <input type="radio" name="dupPolicy" value="import" onchange="handleDupPolicyChange('import')" style="margin-top: 2px;">
                <div>
                  <strong>Import duplicates</strong>
                  <div style="font-size: 11px; color: var(--text-muted);">Force insertion of duplicate rows into the database alongside valid unique leads.</div>
                </div>
              </label>
            </div>
          </div>

          <!-- Final Confirmation Callout Bar -->
          <div style="padding: 14px 18px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
              <div style="font-size: 13px; font-weight: 700; color: #166534;">
                Ready to import: <span id="readyToImportCount">0</span> rows
              </div>
              <div style="font-size: 11px; color: #15803d; margin-top: 2px;">
                Valid: <span id="readyValidSpan">0</span> · Duplicates: <span id="readyDupSpan">0</span> · Invalid: <span id="readyInvSpan">0</span>
              </div>
            </div>
            <div style="font-size: 11px; font-family: 'DM Mono', monospace; color: #15803d; background: #dcfce7; padding: 4px 8px; border-radius: 4px;">
              source = "Excel Import" · call_status = "Not Called"
            </div>
          </div>

          <!-- Commit Error Alert -->
          <div id="commitStepAlert" class="admin-alert alert-error" style="display: none; margin-top: 14px;">
            <span id="commitStepAlertText"></span>
          </div>

          <!-- Commit Loading Spinner -->
          <div id="commitProgressBox" style="display: none; text-align: center; padding: 16px; color: var(--text-muted); font-size: 13px;">
            <div style="display: inline-block; width: 22px; height: 22px; border: 3px solid #e2e8f0; border-top-color: #047857; border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 6px;"></div>
            <div>Inserting leads into CRM database...</div>
          </div>
        </div>

        <!-- =====================================================
             STEP 3: IMPORT SUCCESS SUMMARY
        ===================================================== -->
        <div id="importStepComplete" style="display: none; text-align: center; padding: 24px 10px;">
          <div style="width: 56px; height: 56px; background: #dcfce7; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: #15803d;">
            <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
          </div>
          <h2 style="font-size: 20px; font-weight: 700; color: var(--text-dark); margin-bottom: 6px;">Import Completed</h2>
          <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 24px;">
            Leads have been successfully committed to the database and are now active in the CRM pipeline.
          </p>

          <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; max-width: 500px; margin: 0 auto 28px;">
            <div style="background: #f8fafc; border: 1px solid var(--border-light); border-radius: 8px; padding: 14px;">
              <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Imported</div>
              <div id="resCountImported" style="font-size: 24px; font-weight: 700; color: #047857; margin-top: 4px; font-family: 'DM Mono', monospace;">0</div>
            </div>
            <div style="background: #f8fafc; border: 1px solid var(--border-light); border-radius: 8px; padding: 14px;">
              <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Skipped Duplicates</div>
              <div id="resCountSkipped" style="font-size: 24px; font-weight: 700; color: #b45309; margin-top: 4px; font-family: 'DM Mono', monospace;">0</div>
            </div>
            <div style="background: #f8fafc; border: 1px solid var(--border-light); border-radius: 8px; padding: 14px;">
              <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Invalid (Skipped)</div>
              <div id="resCountInvalid" style="font-size: 24px; font-weight: 700; color: #b91c1c; margin-top: 4px; font-family: 'DM Mono', monospace;">0</div>
            </div>
          </div>

          <a href="leads.php?call_status=all" class="btn-primary-admin" style="padding: 10px 24px; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
            <span>View Imported Leads</span>
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
            </svg>
          </a>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer" id="importModalFooter" style="padding: 16px 24px; background: #f8fafc; border-top: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center;">
        <div id="footerStatusText" style="font-size: 12px; color: var(--text-muted);">
          Phase 8 Commercial Lead Ingestion
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
          <!-- Step 1 Buttons -->
          <button type="button" id="btnCancelUpload" class="btn-action" onclick="closeExcelImportModal()">Cancel</button>
          <button type="button" id="btnUploadSubmit" class="btn-primary-admin" onclick="uploadAndPreviewExcel()" disabled style="display: inline-flex; align-items: center; gap: 6px;">
            <span>Upload &amp; Preview</span>
          </button>

          <!-- Step 2 Buttons (Hidden initially) -->
          <button type="button" id="btnBackToUpload" class="btn-action" onclick="backToUploadStep()" style="display: none;">Upload Different File</button>
          <button type="button" id="btnConfirmImport" class="btn-primary-admin" onclick="confirmExcelImport()" style="display: none; background: #047857;">
            <span id="btnConfirmImportText">Import Leads</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Hidden Form for Quick Status Update -->
  <form id="statusUpdateForm" method="POST" action="leads.php" style="display: none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="lead_id" id="statusLeadId" value="0">
    <input type="hidden" name="status" id="statusValue" value="">
  </form>

  <!-- Hidden Form for Delete Lead -->
  <form id="deleteLeadForm" method="POST" action="leads.php" style="display: none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="lead_id" id="deleteLeadId" value="0">
  </form>

  <!-- Hidden Form for Bulk Delete Leads -->
  <form id="bulkDeleteLeadsForm" method="POST" action="leads.php" style="display: none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="bulk_delete_leads">
    <div id="bulkDeleteLeadsInputs"></div>
  </form>

  <!-- Floating Bulk Actions Toolbar -->
  <div id="leadsBulkBar" class="bulk-actions-bar" style="display: none;">
    <div class="bulk-actions-info">
      <span class="bulk-count-badge" id="leadsSelectedCount">0</span>
      <span>lead(s) selected</span>
    </div>
    <div class="bulk-actions-buttons">
      <button type="button" class="btn-bulk-delete" id="btnBulkDeleteLeads">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
        Delete Selected (<span id="leadsDeleteCountBtnText">0</span>)
      </button>
      <button type="button" class="btn-bulk-cancel" id="btnBulkCancelLeads">Deselect All</button>
    </div>
  </div>

  <!-- =========================================================
       JAVASCRIPT MODAL & INTERACTION CONTROLLER
  ========================================================= -->
  <script>
  // -----------------------------------------------------------
  // Excel & CSV Import Controller (Phase 8)
  // -----------------------------------------------------------
  let currentImportToken = null;
  let currentValidCount = 0;
  let currentDuplicateCount = 0;
  let currentInvalidCount = 0;
  let currentDupPolicy = 'skip';

  function openExcelImportModal() {
    resetExcelImportState();
    const modal = document.getElementById('excelImportModal');
    if (modal) {
      modal.classList.add('active');
      modal.setAttribute('aria-hidden', 'false');
    }
  }

  function closeExcelImportModal() {
    const modal = document.getElementById('excelImportModal');
    if (modal) {
      modal.classList.remove('active');
      modal.setAttribute('aria-hidden', 'true');
    }
  }

  function resetExcelImportState() {
    currentImportToken = null;
    currentValidCount = 0;
    currentDuplicateCount = 0;
    currentInvalidCount = 0;
    currentDupPolicy = 'skip';

    document.getElementById('importStepUpload').style.display = 'block';
    document.getElementById('importStepPreview').style.display = 'none';
    document.getElementById('importStepComplete').style.display = 'none';

    document.getElementById('btnCancelUpload').style.display = 'inline-flex';
    document.getElementById('btnUploadSubmit').style.display = 'inline-flex';
    document.getElementById('btnBackToUpload').style.display = 'none';
    document.getElementById('btnConfirmImport').style.display = 'none';

    document.getElementById('uploadStepAlert').style.display = 'none';
    document.getElementById('commitStepAlert').style.display = 'none';
    document.getElementById('uploadProgressBox').style.display = 'none';
    document.getElementById('commitProgressBox').style.display = 'none';

    resetExcelFileSelection();
  }

  function handleExcelFileSelect(input) {
    const file = input.files && input.files[0];
    const alertBox = document.getElementById('uploadStepAlert');
    alertBox.style.display = 'none';

    if (!file) {
      resetExcelFileSelection();
      return;
    }

    const ext = file.name.split('.').pop().toLowerCase();
    if (ext !== 'xlsx' && ext !== 'csv') {
      alertBox.style.display = 'block';
      document.getElementById('uploadStepAlertText').textContent = 'Only .xlsx and .csv files are supported. Selected: .' + ext;
      resetExcelFileSelection();
      return;
    }

    if (file.size > 10 * 1024 * 1024) {
      alertBox.style.display = 'block';
      document.getElementById('uploadStepAlertText').textContent = 'File size exceeds the 10 MB limit (' + (file.size / (1024*1024)).toFixed(2) + ' MB).';
      resetExcelFileSelection();
      return;
    }

    // Display selected file info
    document.getElementById('selectedFileName').textContent = file.name;
    document.getElementById('selectedFileSize').textContent = (file.size / 1024).toFixed(1) + ' KB · ' + ext.toUpperCase();
    document.getElementById('selectedFileInfo').style.display = 'flex';
    document.getElementById('excelDropZone').style.display = 'none';
    document.getElementById('btnUploadSubmit').disabled = false;
  }

  function resetExcelFileSelection() {
    const input = document.getElementById('excelFileInput');
    if (input) input.value = '';
    document.getElementById('selectedFileInfo').style.display = 'none';
    document.getElementById('excelDropZone').style.display = 'block';
    document.getElementById('btnUploadSubmit').disabled = true;
  }

  // Setup drag & drop
  const dropZone = document.getElementById('excelDropZone');
  if (dropZone) {
    ['dragenter', 'dragover'].forEach(eventName => {
      dropZone.addEventListener(eventName, e => {
        e.preventDefault();
        dropZone.style.borderColor = 'var(--primary)';
        dropZone.style.background = '#f0f9ff';
      });
    });
    ['dragleave', 'drop'].forEach(eventName => {
      dropZone.addEventListener(eventName, e => {
        e.preventDefault();
        dropZone.style.borderColor = '#cbd5e1';
        dropZone.style.background = '#f8fafc';
      });
    });
    dropZone.addEventListener('drop', e => {
      if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
        const fileInput = document.getElementById('excelFileInput');
        fileInput.files = e.dataTransfer.files;
        handleExcelFileSelect(fileInput);
      }
    });
  }

  function uploadAndPreviewExcel() {
    const fileInput = document.getElementById('excelFileInput');
    const file = fileInput.files && fileInput.files[0];
    const alertBox = document.getElementById('uploadStepAlert');
    const alertText = document.getElementById('uploadStepAlertText');
    const progressBox = document.getElementById('uploadProgressBox');
    const submitBtn = document.getElementById('btnUploadSubmit');

    alertBox.style.display = 'none';

    if (!file) {
      alertBox.style.display = 'block';
      alertText.textContent = 'Please choose an Excel or CSV file first.';
      return;
    }

    const csrfTokenEl = document.querySelector('input[name="csrf_token"]');
    const csrfToken = csrfTokenEl ? csrfTokenEl.value : '';

    const formData = new FormData();
    formData.append('action', 'excel_preview');
    formData.append('csrf_token', csrfToken);
    formData.append('excel_file', file);

    submitBtn.disabled = true;
    progressBox.style.display = 'block';

    fetch('leads.php', {
      method: 'POST',
      body: formData
    })
    .then(res => {
      if (!res.ok) {
        throw new Error('Server returned HTTP ' + res.status);
      }
      return res.json();
    })
    .then(data => {
      progressBox.style.display = 'none';
      submitBtn.disabled = false;

      if (!data.success) {
        alertBox.style.display = 'block';
        alertText.textContent = data.error || 'Failed to process spreadsheet.';
        return;
      }

      // Store results
      currentImportToken = data.import_token;
      currentValidCount = parseInt(data.valid_count, 10) || 0;
      currentDuplicateCount = parseInt(data.duplicate_count, 10) || 0;
      currentInvalidCount = parseInt(data.invalid_count, 10) || 0;
      currentDupPolicy = 'skip';

      // Populate KPI Counters
      document.getElementById('kpiTotalRows').textContent = data.total_rows || 0;
      document.getElementById('kpiValidRows').textContent = currentValidCount;
      document.getElementById('kpiDuplicateRows').textContent = currentDuplicateCount;
      document.getElementById('kpiInvalidRows').textContent = currentInvalidCount;

      // Populate Validation Errors List
      const errorsCard = document.getElementById('validationErrorsCard');
      const errorsList = document.getElementById('validationErrorsList');
      if (data.errors && data.errors.length > 0) {
        document.getElementById('errTotalCount').textContent = currentInvalidCount;
        errorsCard.style.display = 'block';
        errorsList.innerHTML = data.errors.map(err => {
          return '<div>• <strong>Row ' + escapeHtml(String(err.row)) + '</strong> (' + escapeHtml(err.name) + '): ' + escapeHtml(err.error) + '</div>';
        }).join('');
        const dlBtn = document.getElementById('btnDownloadErrorReport');
        dlBtn.href = 'leads.php?action=download_import_errors&token=' + encodeURIComponent(currentImportToken);
      } else {
        errorsCard.style.display = 'none';
      }

      // Populate Preview Table
      const tableBody = document.getElementById('previewTableBody');
      tableBody.innerHTML = '';
      if (data.preview_rows && data.preview_rows.length > 0) {
        data.preview_rows.forEach(r => {
          const tr = document.createElement('tr');
          let statusBadge = '';
          if (!r.is_valid) {
            statusBadge = '<span class="status-pill" style="background:#fee2e2; color:#991b1b; font-size:11px;" title="' + escapeHtml(r.errors ? r.errors.join('; ') : 'Invalid') + '">Invalid</span>';
          } else if (r.is_duplicate) {
            statusBadge = '<span class="status-pill" style="background:#fef3c7; color:#92400e; font-size:11px;" title="' + escapeHtml(r.duplicate_reasons ? r.duplicate_reasons.join('; ') : 'Duplicate') + '">Duplicate</span>';
          } else {
            statusBadge = '<span class="status-pill status-published" style="font-size:11px;">Valid</span>';
          }

          tr.innerHTML = `
            <td style="font-family: 'DM Mono', monospace; font-size: 11px; color: var(--text-muted);">${escapeHtml(String(r.row_number || ''))}</td>
            <td><strong>${escapeHtml(r.name || '')}</strong></td>
            <td>${escapeHtml(r.company || '—')}</td>
            <td style="font-family: 'DM Mono', monospace; font-size: 11px;">${escapeHtml(r.email || '—')}</td>
            <td style="font-family: 'DM Mono', monospace; font-size: 11px;">${escapeHtml(r.phone || '—')}</td>
            <td>${escapeHtml(r.service || 'Website')}</td>
            <td style="font-family: 'DM Mono', monospace; font-size: 11px;">${escapeHtml(r.budget || '—')}</td>
            <td><span class="status-pill status-${(r.status || 'New').toLowerCase() === 'new' ? 'qualified' : 'contacted'}" style="font-size: 11px;">${escapeHtml(r.status || 'New')}</span></td>
            <td><span class="status-pill" style="background: #f1f5f9; color: #475569; font-size: 11px;">${escapeHtml(r.call_status || 'Not Called')}</span></td>
            <td>${statusBadge}</td>
          `;
          tableBody.appendChild(tr);
        });
      }

      // Reset policy radio to skip
      const skipRadio = document.querySelector('input[name="dupPolicy"][value="skip"]');
      if (skipRadio) skipRadio.checked = true;
      updateConfirmationCounts();

      // Show Step 2 View
      document.getElementById('importStepUpload').style.display = 'none';
      document.getElementById('importStepPreview').style.display = 'block';

      // Update footer buttons
      document.getElementById('btnCancelUpload').style.display = 'none';
      document.getElementById('btnUploadSubmit').style.display = 'none';
      document.getElementById('btnBackToUpload').style.display = 'inline-flex';
      document.getElementById('btnConfirmImport').style.display = 'inline-flex';
    })
    .catch(err => {
      progressBox.style.display = 'none';
      submitBtn.disabled = false;
      alertBox.style.display = 'block';
      alertText.textContent = 'Upload failed: ' + err.message;
    });
  }

  function handleDupPolicyChange(policy) {
    currentDupPolicy = policy;
    updateConfirmationCounts();
  }

  function updateConfirmationCounts() {
    let importableCount = currentValidCount;
    if (currentDupPolicy === 'import') {
      importableCount += currentDuplicateCount;
    }

    document.getElementById('readyToImportCount').textContent = importableCount;
    document.getElementById('readyValidSpan').textContent = currentValidCount;
    document.getElementById('readyDupSpan').textContent = currentDuplicateCount;
    document.getElementById('readyInvSpan').textContent = currentInvalidCount;

    const btnConfirm = document.getElementById('btnConfirmImport');
    const btnText = document.getElementById('btnConfirmImportText');

    if (importableCount > 0) {
      btnConfirm.disabled = false;
      btnConfirm.style.opacity = '1';
      btnText.textContent = 'Import ' + importableCount + ' Leads';
    } else {
      btnConfirm.disabled = true;
      btnConfirm.style.opacity = '0.5';
      btnText.textContent = 'No Leads to Import';
    }
  }

  function backToUploadStep() {
    document.getElementById('importStepUpload').style.display = 'block';
    document.getElementById('importStepPreview').style.display = 'none';
    document.getElementById('btnCancelUpload').style.display = 'inline-flex';
    document.getElementById('btnUploadSubmit').style.display = 'inline-flex';
    document.getElementById('btnBackToUpload').style.display = 'none';
    document.getElementById('btnConfirmImport').style.display = 'none';
  }

  function confirmExcelImport() {
    if (!currentImportToken) {
      if (typeof AdminModal !== 'undefined' && AdminModal.alert) {
        AdminModal.alert('Staging session expired. Please upload file again.', 'Session Expired');
      }
      backToUploadStep();
      return;
    }

    const alertBox = document.getElementById('commitStepAlert');
    const alertText = document.getElementById('commitStepAlertText');
    const progressBox = document.getElementById('commitProgressBox');
    const btnConfirm = document.getElementById('btnConfirmImport');
    const btnBack = document.getElementById('btnBackToUpload');

    alertBox.style.display = 'none';
    btnConfirm.disabled = true;
    btnBack.disabled = true;
    progressBox.style.display = 'block';

    const csrfTokenEl = document.querySelector('input[name="csrf_token"]');
    const csrfToken = csrfTokenEl ? csrfTokenEl.value : '';

    const formData = new FormData();
    formData.append('action', 'excel_confirm_import');
    formData.append('csrf_token', csrfToken);
    formData.append('import_token', currentImportToken);
    formData.append('duplicate_option', currentDupPolicy);

    fetch('leads.php', {
      method: 'POST',
      body: formData
    })
    .then(res => {
      if (!res.ok) {
        throw new Error('Server error HTTP ' + res.status);
      }
      return res.json();
    })
    .then(data => {
      progressBox.style.display = 'none';

      if (!data.success) {
        btnConfirm.disabled = false;
        btnBack.disabled = false;
        alertBox.style.display = 'block';
        alertText.textContent = data.error || 'Failed to finalize lead import.';
        return;
      }

      // Transition to Step 3: Success
      document.getElementById('importStepPreview').style.display = 'none';
      document.getElementById('importStepComplete').style.display = 'block';

      document.getElementById('resCountImported').textContent = data.imported || 0;
      document.getElementById('resCountSkipped').textContent = data.skipped_duplicates || 0;
      document.getElementById('resCountInvalid').textContent = data.invalid_count || 0;

      // Hide modal footer buttons on complete
      document.getElementById('importModalFooter').style.display = 'none';
    })
    .catch(err => {
      progressBox.style.display = 'none';
      btnConfirm.disabled = false;
      btnBack.disabled = false;
      alertBox.style.display = 'block';
      alertText.textContent = 'Import error: ' + err.message;
    });
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatLocalDateForInput(d) {
    const pad = n => String(n).padStart(2, '0');
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
  }

  function openAddLeadModal() {
    const modal = document.getElementById('addLeadModal');
    if (modal) {
      modal.classList.add('active');
      modal.setAttribute('aria-hidden', 'false');
    }
  }

  function closeAddLeadModal() {
    const modal = document.getElementById('addLeadModal');
    if (modal) {
      modal.classList.remove('active');
      modal.setAttribute('aria-hidden', 'true');
    }
  }

  function openCallModal(leadId, name, company, phone) {
    document.getElementById('callLeadId').value = leadId;
    document.getElementById('modalLeadSubtitle').textContent = name + (company ? ' (' + company + ')' : '') + (phone ? ' · ' + phone : '');
    
    // Set current date/time
    document.getElementById('callDateTimeInput').value = formatLocalDateForInput(new Date());

    // Reset next follow-up
    document.getElementById('nextFollowupInput').value = '';

    // Show modal
    const modal = document.getElementById('callModal');
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeCallModal() {
    const modal = document.getElementById('callModal');
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
  }

  function triggerClickToCall(leadId, btnElement) {
    if (!leadId) return;

    if (btnElement && btnElement.disabled) return;

    const btnText = btnElement ? (btnElement.querySelector('.btn-call-text') || btnElement) : null;
    const originalHtml = btnElement ? btnElement.innerHTML : '';

    if (btnElement) {
      btnElement.disabled = true;
      btnElement.style.opacity = '0.75';
      if (btnText) btnText.textContent = 'CALLING...';
    }

    fetch('api/call-lead.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ lead_id: leadId })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        if (btnText) btnText.textContent = 'CALLING YOUR PHONE...';
        showCrmCallNotification('Connecting call: Your phone (9380552034) will ring shortly!', 'success');

        if (btnElement) {
          const row = btnElement.closest('tr');
          if (row) {
            const lastCalledCell = row.querySelector('.col-last-called');
            if (lastCalledCell) {
              lastCalledCell.innerHTML = '<div style="font-family: \'DM Mono\', monospace; font-size: 11px; color: var(--text-dark); font-weight: 500;">Just now</div>';
            }
            const callStatusBadge = row.querySelector('.badge-call-status');
            if (callStatusBadge) {
              callStatusBadge.className = 'badge-call-status badge-called';
              callStatusBadge.innerHTML = '<span class="dot"></span>Called';
            }
          }
        }

        setTimeout(() => {
          if (btnElement) {
            btnElement.disabled = false;
            btnElement.style.opacity = '1';
            btnElement.innerHTML = originalHtml;
          }
        }, 4000);
      } else {
        showCrmCallNotification(data.error || 'Unable to start the call. Please try again.', 'error');
        if (btnElement) {
          btnElement.disabled = false;
          btnElement.style.opacity = '1';
          btnElement.innerHTML = originalHtml;
        }
      }
    })
    .catch(err => {
      showCrmCallNotification('Unable to start the call. Please check network connection.', 'error');
      if (btnElement) {
        btnElement.disabled = false;
        btnElement.style.opacity = '1';
        btnElement.innerHTML = originalHtml;
      }
    });
  }

  function showCrmCallNotification(message, type) {
    let alertContainer = document.getElementById('crmCallBanner');
    if (!alertContainer) {
      alertContainer = document.createElement('div');
      alertContainer.id = 'crmCallBanner';
      alertContainer.style.position = 'fixed';
      alertContainer.style.bottom = '24px';
      alertContainer.style.right = '24px';
      alertContainer.style.zIndex = '9999';
      alertContainer.style.maxWidth = '380px';
      document.body.appendChild(alertContainer);
    }
    const bg = type === 'success' ? '#059669' : '#dc2626';
    const item = document.createElement('div');
    item.style.background = bg;
    item.style.color = '#ffffff';
    item.style.padding = '12px 18px';
    item.style.borderRadius = '10px';
    item.style.boxShadow = '0 10px 30px rgba(0,0,0,0.25)';
    item.style.fontSize = '13px';
    item.style.lineHeight = '1.4';
    item.style.fontFamily = "system-ui, -apple-system, sans-serif";
    item.style.marginTop = '8px';
    item.style.transition = 'all 0.3s ease';
    item.innerHTML = `<strong>${type === 'success' ? '📞 Click-to-Call' : '⚠️ Telephony Notice'}</strong><br>${message}`;
    alertContainer.appendChild(item);
    setTimeout(() => {
      item.style.opacity = '0';
      setTimeout(() => item.remove(), 400);
    }, 6000);
  }

  function openEditLeadModal(lead) {
    document.getElementById('editLeadId').value = lead.id;
    document.getElementById('editLeadSubtitle').textContent = '#' + lead.id + ' — ' + lead.name;
    document.getElementById('editLeadName').value = lead.name || '';
    document.getElementById('editLeadCompany').value = lead.company || '';
    document.getElementById('editLeadEmail').value = lead.email || '';
    document.getElementById('editLeadPhone').value = lead.phone || '';
    document.getElementById('editLeadService').value = lead.service || lead.service_interested || '';
    document.getElementById('editLeadBudget').value = lead.budget || '';
    document.getElementById('editLeadStatus').value = lead.status || 'New';
    document.getElementById('editLeadCallStatus').value = lead.call_status || 'Not Called';
    document.getElementById('editLeadSource').value = lead.source || '';
    document.getElementById('editLeadNotes').value = lead.notes || '';

    const modal = document.getElementById('editLeadModal');
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeEditLeadModal() {
    const modal = document.getElementById('editLeadModal');
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
  }

  function handleOutcomeChange(outcome) {
    const followupGroup = document.getElementById('nextFollowupGroup');
    const followupTag = document.getElementById('followupOptionalTag');
    const followupInput = document.getElementById('nextFollowupInput');

    if (outcome === 'Call Back') {
      followupTag.textContent = '(Required for Call Back)';
      followupTag.style.color = '#c026d3';
      followupInput.required = true;
      // Pre-fill next business day
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      tomorrow.setHours(10, 0, 0, 0);
      if (!followupInput.value) {
        followupInput.value = formatLocalDateForInput(tomorrow);
      }
    } else {
      followupTag.textContent = '(Optional)';
      followupTag.style.color = 'var(--text-muted)';
      followupInput.required = false;
    }
  }

  function openHistoryModal(leadId, name) {
    document.getElementById('historyLeadSubtitle').textContent = 'Complete record for ' + name;
    const modal = document.getElementById('historyModal');
    const loading = document.getElementById('historyLoading');
    const container = document.getElementById('historyContainer');

    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
    loading.style.display = 'block';
    container.style.display = 'none';
    container.innerHTML = '';

    fetch('leads.php?action=get_call_history&lead_id=' + encodeURIComponent(leadId))
      .then(res => res.json())
      .then(data => {
        loading.style.display = 'none';
        container.style.display = 'flex';

        if (!data.success || !data.calls || data.calls.length === 0) {
          container.innerHTML = '<div style="text-align:center; padding: 24px; color: var(--text-muted);">No calls logged for this prospect yet. Click CALL to record the first contact.</div>';
          return;
        }

        data.calls.forEach(call => {
          const item = document.createElement('div');
          item.className = 'call-history-item';

          const outcome = call.outcome || 'Call';
          let outcomeBadge = '<span class="status-pill status-contacted">' + outcome + '</span>';
          if (outcome === 'Connected') {
            outcomeBadge = '<span class="status-pill status-completed">Connected</span>';
          } else if (outcome === 'Call Back') {
            outcomeBadge = '<span class="badge-call-status badge-callback"><span class="dot"></span>Call Back</span>';
          } else if (outcome === 'No Answer') {
            outcomeBadge = '<span class="badge-call-status badge-no-answer"><span class="dot"></span>No Answer</span>';
          } else if (outcome === 'Converted') {
            outcomeBadge = '<span class="status-pill status-active">Converted</span>';
          }

          let followupHtml = '';
          if (call.next_followup_at) {
            followupHtml = '<div style="font-family: \'DM Mono\', monospace; font-size: 11px; color: #2563eb; margin-top: 4px;">Next Follow-up: ' + call.next_followup_at + '</div>';
          }

          let notesHtml = '';
          if (call.notes) {
            notesHtml = '<div class="call-history-notes">' + call.notes.replace(/\n/g, '<br>') + '</div>';
          }

          item.innerHTML = `
            <div class="call-history-header">
              <div>
                <span style="font-family: 'DM Mono', monospace; font-size: 12px; font-weight: 600; color: var(--text-dark);">
                  ${call.call_time}
                </span>
                ${followupHtml}
              </div>
              <div>${outcomeBadge}</div>
            </div>
            ${notesHtml}
          `;
          container.appendChild(item);
        });
      })
      .catch(err => {
        loading.style.display = 'none';
        container.style.display = 'block';
        container.innerHTML = '<div style="color: #ef4444; padding: 12px;">Failed to load call history: ' + err.message + '</div>';
      });
  }

  function closeHistoryModal() {
    const modal = document.getElementById('historyModal');
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
  }

  function updateLeadStatus(leadId, newStatus) {
    if (window.AdminModal && typeof window.AdminModal.confirm === 'function') {
      window.AdminModal.confirm({
        title: 'Update Lead Stage',
        message: 'Change lead #' + leadId + ' stage to ' + newStatus + '?',
        confirmText: 'Update Stage',
        isDanger: false,
        onConfirm: () => {
          document.getElementById('statusLeadId').value = leadId;
          document.getElementById('statusValue').value = newStatus;
          document.getElementById('statusUpdateForm').submit();
        }
      });
    } else {
      document.getElementById('statusLeadId').value = leadId;
      document.getElementById('statusValue').value = newStatus;
      document.getElementById('statusUpdateForm').submit();
    }
  }

  function convertToClient(leadId, name) {
    const doConvert = () => {
      const csrfInput = document.querySelector('input[name="csrf_token"]');
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = 'leads.php';
      form.innerHTML = `
        <input type="hidden" name="csrf_token" value="${csrfInput ? csrfInput.value : ''}">
        <input type="hidden" name="action" value="convert_to_client">
        <input type="hidden" name="lead_id" value="${leadId}">
      `;
      document.body.appendChild(form);
      form.submit();
    };

    if (window.AdminModal && typeof window.AdminModal.confirm === 'function') {
      window.AdminModal.confirm({
        title: 'Create Client from Lead',
        message: 'Convert "' + name + '" into an Active Client? Contact information will be saved in Clients and the lead will remain linked.',
        confirmText: 'Create Client',
        isDanger: false,
        onConfirm: doConvert
      });
    } else {
      doConvert();
    }
  }

  function confirmDeleteLead(leadId, name) {
    if (window.AdminModal && typeof window.AdminModal.confirm === 'function') {
      window.AdminModal.confirm({
        title: 'Delete Lead Prospect',
        message: 'Are you sure you want to delete lead "' + name + '" and its associated call history? This action cannot be undone.',
        confirmText: 'Delete Lead',
        isDanger: true,
        onConfirm: () => {
          document.getElementById('deleteLeadId').value = leadId;
          document.getElementById('deleteLeadForm').submit();
        }
      });
    } else {
      document.getElementById('deleteLeadId').value = leadId;
      document.getElementById('deleteLeadForm').submit();
    }
  }

  // Close modals on clicking overlay backdrop
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
      if (e.target === this) {
        this.classList.remove('active');
        this.setAttribute('aria-hidden', 'true');
      }
    });
  });

  // -----------------------------------------------------------
  // Bulk Lead Selection Controller
  // -----------------------------------------------------------
  function initBulkLeadsSelection() {
    const selectAllLeads = document.getElementById('selectAllLeads');
    const selectAllFollowupLeads = document.getElementById('selectAllFollowupLeads');
    const bulkBar = document.getElementById('leadsBulkBar');
    const selectedCountBadge = document.getElementById('leadsSelectedCount');
    const deleteCountBtnText = document.getElementById('leadsDeleteCountBtnText');
    const btnBulkDelete = document.getElementById('btnBulkDeleteLeads');
    const btnBulkCancel = document.getElementById('btnBulkCancelLeads');
    const bulkInputs = document.getElementById('bulkDeleteLeadsInputs');
    const bulkForm = document.getElementById('bulkDeleteLeadsForm');

    function getCheckboxes() {
      return document.querySelectorAll('.lead-select-checkbox');
    }

    function updateBulkState() {
      const checkboxes = getCheckboxes();
      const checkedBoxes = Array.from(checkboxes).filter(cb => cb.checked);
      const count = checkedBoxes.length;

      if (selectedCountBadge) selectedCountBadge.textContent = count;
      if (deleteCountBtnText) deleteCountBtnText.textContent = count;

      if (count > 0) {
        if (bulkBar) bulkBar.style.display = 'flex';
      } else {
        if (bulkBar) bulkBar.style.display = 'none';
      }

      // Update header master checkboxes
      if (selectAllLeads) {
        selectAllLeads.checked = checkboxes.length > 0 && count === checkboxes.length;
        selectAllLeads.indeterminate = count > 0 && count < checkboxes.length;
      }
      if (selectAllFollowupLeads) {
        selectAllFollowupLeads.checked = checkboxes.length > 0 && count === checkboxes.length;
        selectAllFollowupLeads.indeterminate = count > 0 && count < checkboxes.length;
      }

      // Row styling
      checkboxes.forEach(cb => {
        const tr = cb.closest('tr');
        if (tr) {
          if (cb.checked) tr.classList.add('row-selected');
          else tr.classList.remove('row-selected');
        }
      });
    }

    function toggleAll(checked) {
      getCheckboxes().forEach(cb => {
        cb.checked = checked;
      });
      updateBulkState();
    }

    if (selectAllLeads) {
      selectAllLeads.addEventListener('change', function() {
        toggleAll(this.checked);
      });
    }
    if (selectAllFollowupLeads) {
      selectAllFollowupLeads.addEventListener('change', function() {
        toggleAll(this.checked);
      });
    }

    document.addEventListener('change', function(e) {
      if (e.target && e.target.classList.contains('lead-select-checkbox')) {
        updateBulkState();
      }
    });

    if (btnBulkCancel) {
      btnBulkCancel.addEventListener('click', function() {
        toggleAll(false);
      });
    }

    if (btnBulkDelete) {
      btnBulkDelete.addEventListener('click', function() {
        const checkedBoxes = Array.from(getCheckboxes()).filter(cb => cb.checked);
        const count = checkedBoxes.length;
        if (count === 0) return;

        const confirmMsg = 'Are you sure you want to permanently delete ' + count + ' selected lead(s) and their associated call history? This action cannot be undone.';
        const confirmTitle = 'Delete Selected Leads (' + count + ')';
        const confirmBtn = 'Yes, Delete ' + count + ' Leads';

        const doSubmit = () => {
          bulkInputs.innerHTML = '';
          checkedBoxes.forEach(cb => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'lead_ids[]';
            hidden.value = cb.value;
            bulkInputs.appendChild(hidden);
          });
          bulkForm.submit();
        };

        if (window.AdminModal && typeof window.AdminModal.confirm === 'function') {
          window.AdminModal.confirm({
            title: confirmTitle,
            message: confirmMsg,
            confirmText: confirmBtn,
            isDanger: true,
            onConfirm: doSubmit
          });
        } else if (confirm(confirmMsg)) {
          doSubmit();
        }
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBulkLeadsSelection);
  } else {
    initBulkLeadsSelection();
  }
  </script>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>
