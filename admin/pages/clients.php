<?php
/**
 * Website Tailors Admin — Clients Management Module (Phase 2)
 *
 * Full Business CRM Client Management:
 * - Table: Client, Company, Service, Phone, Status, Last Contact, Revenue, Actions
 * - CRUD: Create, Read (Central Client Record), Update, Delete (via AdminModal)
 * - Statuses: New, Active, Inactive, Completed
 * - Instant live search, status filter, service filter, and pagination (AJAX fetch + server fallback)
 * - Central Client Detail Profile linking Contact, Revenue, Call history, Lead history, and Follow-ups
 * - Pure PHP 8, PDO, HTML5, Vanilla CSS, Vanilla JavaScript (Zero Frameworks)
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) { define('WebsiteTailors_INIT', true); }
require_once dirname(__DIR__) . '/includes/auth_guard.php';

$pageTitle = 'Clients Directory';
$breadcrumb = 'Clients';

$db = Database::getInstance();
$validStatuses = ['New', 'Active', 'Inactive', 'Completed'];
$validServices = ['Websites', 'Software', 'AI + Automation', 'Maintenance', 'Consulting', 'Other'];
$validSources  = ['Website Inquiry', 'Referral', 'LinkedIn', 'Cold Outreach', 'Existing Client', 'Other'];

// -----------------------------------------------------------------------------
// HELPER: Calculate Client Revenue & Last Contact
// -----------------------------------------------------------------------------
function get_client_revenue_summary(PDO|Database $db, string $clientName, ?string $companyName, int $clientId = 0): array {
    $cName = trim($clientName);
    $comp = trim((string)$companyName);
    $summary = ['total' => 0.0, 'paid' => 0.0, 'pending' => 0.0, 'invoices' => []];

    try {
        if ($db instanceof Database && $db->isConnected()) {
            $pdo = $db->getConnection();

            // 1. Check revenue table first
            $revRows = [];
            if ($clientId > 0) {
                $stmt = $pdo->prepare("SELECT id, amount, payment_status, payment_type, payment_date, service, notes FROM revenue WHERE client_id = ? ORDER BY payment_date DESC");
                $stmt->execute([$clientId]);
                $revRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            if (empty($revRows)) {
                $stmt = $pdo->prepare("
                    SELECT r.id, r.amount, r.payment_status, r.payment_type, r.payment_date, r.service, r.notes 
                    FROM revenue r
                    LEFT JOIN clients c ON r.client_id = c.id
                    WHERE c.client_name = :c1 OR (:comp1 != '' AND c.company_name = :comp2)
                    ORDER BY r.payment_date DESC
                ");
                $stmt->execute([':c1' => $cName, ':comp1' => $comp, ':comp2' => $comp]);
                $revRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            if (!empty($revRows)) {
                foreach ($revRows as $r) {
                    $amt = (float)$r['amount'];
                    $st = strtolower(trim((string)$r['payment_status']));
                    if ($st !== 'refunded') {
                        $summary['total'] += $amt;
                    }
                    if ($st === 'paid') {
                        $summary['paid'] += $amt;
                    } elseif ($st === 'pending' || $st === 'partially paid') {
                        $summary['pending'] += $amt;
                    }
                    $summary['invoices'][] = [
                        'id'             => $r['id'],
                        'invoice_number' => 'REV-' . $r['id'],
                        'amount'         => $amt,
                        'status'         => $r['payment_status'],
                        'due_date'       => $r['payment_date'],
                        'paid_at'        => ($st === 'paid' ? $r['payment_date'] : null),
                        'created_at'     => $r['payment_date']
                    ];
                }
            }
        }

        // 2. Invoices fallback / merge if no revenue rows
        if (empty($summary['invoices'])) {
            $sql = "SELECT id, invoice_number, amount, status, due_date, paid_at, created_at 
                    FROM invoices 
                    WHERE (client_name = :c1 OR (:comp1 != '' AND client_name = :comp2))
                    ORDER BY created_at DESC";
            $params = [':c1' => $cName, ':comp1' => $comp, ':comp2' => $comp];
            $invs = ($db instanceof Database) ? $db->fetchAll($sql, $params) : [];
            if ($invs) {
                $summary['invoices'] = $invs;
                foreach ($invs as $inv) {
                    $amt = (float)$inv['amount'];
                    $summary['total'] += $amt;
                    if ($inv['status'] === 'paid') {
                        $summary['paid'] += $amt;
                    } elseif ($inv['status'] === 'pending') {
                        $summary['pending'] += $amt;
                    }
                }
            }
        }
    } catch (\Throwable) {}

    return $summary;
}

function get_client_last_contact(PDO|Database $db, string $clientName, ?string $companyName, string $createdAt, int $clientId = 0): string {
    $lastDate = $createdAt;
    try {
        $cName = trim($clientName);
        $comp = trim((string)$companyName);

        if ($db instanceof Database && $db->isConnected()) {
            $pdo = $db->getConnection();
            $callDate = null;
            if ($clientId > 0) {
                $stmt = $pdo->prepare("SELECT COALESCE(call_datetime, scheduled_at, created_at) FROM calls WHERE client_id = ? ORDER BY COALESCE(call_datetime, scheduled_at, created_at) DESC LIMIT 1");
                $stmt->execute([$clientId]);
                $callDate = $stmt->fetchColumn();
            }
            if (!$callDate) {
                $stmt = $pdo->prepare("
                    SELECT COALESCE(call_datetime, scheduled_at, created_at) FROM calls 
                    WHERE contact_name = :c1 OR (:comp1 != '' AND company = :comp2) 
                    ORDER BY COALESCE(call_datetime, scheduled_at, created_at) DESC LIMIT 1
                ");
                $stmt->execute([':c1' => $cName, ':comp1' => $comp, ':comp2' => $comp]);
                $callDate = $stmt->fetchColumn();
            }

            if ($callDate && strtotime((string)$callDate) > strtotime($lastDate)) {
                $lastDate = (string)$callDate;
            }
        }
    } catch (\Throwable) {}

    return $lastDate;
}

// -----------------------------------------------------------------------------
// AJAX ENDPOINT: Fetch Single Client Detail for Central Profile Modal
// -----------------------------------------------------------------------------
if (($_GET['action'] ?? '') === 'get_detail') {
    header('Content-Type: application/json; charset=utf-8');
    $clientId = (int)($_GET['id'] ?? 0);
    $client = null;

    if ($clientId > 0 && $db->isConnected()) {
        $client = $db->fetch("SELECT * FROM clients WHERE id = :id", [':id' => $clientId]);
    }

    if (!$client) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Client not found']);
        exit;
    }

    $revenue = get_client_revenue_summary($db, $client['client_name'], $client['company_name'], (int)$client['id']);
    
    // Call history
    $calls = [];
    try {
        $calls = $db->fetchAll(
            "SELECT * FROM calls 
             WHERE contact_name = :c1 OR (:comp1 != '' AND company = :comp2)
             ORDER BY scheduled_at DESC LIMIT 10",
            [':c1' => $client['client_name'], ':comp1' => (string)$client['company_name'], ':comp2' => (string)$client['company_name']]
        );
    } catch (\Throwable) {}

    // Lead history
    $leads = [];
    try {
        $leads = $db->fetchAll(
            "SELECT * FROM leads 
             WHERE email = :email OR name = :cname 
             ORDER BY created_at DESC LIMIT 5",
            [':email' => $client['email'], ':cname' => $client['client_name']]
        );
    } catch (\Throwable) {}

    // Follow-ups (scheduled / pending)
    $followups = [];
    try {
        $followups = $db->fetchAll(
            "SELECT * FROM calls 
             WHERE (contact_name = :c1 OR (:comp1 != '' AND company = :comp2))
               AND status IN ('scheduled', 'pending')
             ORDER BY scheduled_at ASC LIMIT 5",
            [':c1' => $client['client_name'], ':comp1' => (string)$client['company_name'], ':comp2' => (string)$client['company_name']]
        );
    } catch (\Throwable) {}

    echo json_encode([
        'success'   => true,
        'client'    => $client,
        'revenue'   => $revenue,
        'calls'     => $calls,
        'leads'     => $leads,
        'followups' => $followups
    ]);
    exit;
}

// -----------------------------------------------------------------------------
// ACTION: POST Handlers (Create, Update, Delete)
// -----------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf()) {
        set_flash('error', 'Security session expired. Please refresh the page and try again.');
        redirect(ADMIN_URL . '/pages/clients.php');
    }

    $postAction = $_POST['action'] ?? '';

    // 1. CREATE CLIENT
    if ($postAction === 'create') {
        $clientName     = trim((string)($_POST['client_name'] ?? ''));
        $companyName    = trim((string)($_POST['company_name'] ?? ''));
        $email          = trim((string)($_POST['email'] ?? ''));
        $phone          = trim((string)($_POST['phone'] ?? ''));
        $alternatePhone = trim((string)($_POST['alternate_phone'] ?? ''));
        $service        = trim((string)($_POST['service'] ?? ''));
        $source         = trim((string)($_POST['source'] ?? ''));
        $status         = trim((string)($_POST['status'] ?? 'New'));
        $assignedTo     = trim((string)($_POST['assigned_to'] ?? ''));
        $notes          = trim((string)($_POST['notes'] ?? ''));

        $errors = [];

        // Validations
        if (empty($clientName)) { $errors[] = 'Client name is required.'; }
        if (mb_strlen($clientName) > 150) { $errors[] = 'Client name cannot exceed 150 characters.'; }
        if (!empty($companyName) && mb_strlen($companyName) > 150) { $errors[] = 'Company name cannot exceed 150 characters.'; }

        if (empty($email)) {
            $errors[] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (mb_strlen($email) > 191) {
            $errors[] = 'Email address cannot exceed 191 characters.';
        }

        if (empty($phone)) {
            $errors[] = 'Phone number is required.';
        } elseif (!preg_match('/^[0-9+\-\s().]{7,50}$/', $phone)) {
            $errors[] = 'Please provide a valid phone number (digits, +, -, parentheses).';
        }

        if (!empty($alternatePhone) && !preg_match('/^[0-9+\-\s().]{7,50}$/', $alternatePhone)) {
            $errors[] = 'Please provide a valid alternate phone number.';
        }

        if (!in_array($status, $validStatuses, true)) {
            $status = 'New';
        }

        if (mb_strlen($notes) > 5000) {
            $errors[] = 'Notes cannot exceed 5000 characters.';
        }

        if (!empty($errors)) {
            set_flash('error', implode(' ', $errors));
            redirect(ADMIN_URL . '/pages/clients.php');
        }

        try {
            $now = date('Y-m-d H:i:s');
            $db->execute(
                "INSERT INTO clients (client_name, company_name, email, phone, alternate_phone, service, source, status, assigned_to, notes, created_at, updated_at) 
                 VALUES (:client_name, :company_name, :email, :phone, :alternate_phone, :service, :source, :status, :assigned_to, :notes, :created_at, :updated_at)",
                [
                    ':client_name'     => $clientName,
                    ':company_name'    => !empty($companyName) ? $companyName : null,
                    ':email'           => $email,
                    ':phone'           => $phone,
                    ':alternate_phone' => !empty($alternatePhone) ? $alternatePhone : null,
                    ':service'         => !empty($service) ? $service : null,
                    ':source'          => !empty($source) ? $source : null,
                    ':status'          => $status,
                    ':assigned_to'     => !empty($assignedTo) ? $assignedTo : null,
                    ':notes'           => !empty($notes) ? $notes : null,
                    ':created_at'      => $now,
                    ':updated_at'      => $now
                ]
            );
            set_flash('success', "Client '{$clientName}' added successfully!");
        } catch (\Throwable $e) {
            error_log("Create client error: " . $e->getMessage());
            set_flash('error', 'Database error creating client. Please try again.');
        }

        redirect(ADMIN_URL . '/pages/clients.php');
    }

    // 2. UPDATE CLIENT
    if ($postAction === 'update') {
        $id             = (int)($_POST['id'] ?? 0);
        $clientName     = trim((string)($_POST['client_name'] ?? ''));
        $companyName    = trim((string)($_POST['company_name'] ?? ''));
        $email          = trim((string)($_POST['email'] ?? ''));
        $phone          = trim((string)($_POST['phone'] ?? ''));
        $alternatePhone = trim((string)($_POST['alternate_phone'] ?? ''));
        $service        = trim((string)($_POST['service'] ?? ''));
        $source         = trim((string)($_POST['source'] ?? ''));
        $status         = trim((string)($_POST['status'] ?? 'New'));
        $assignedTo     = trim((string)($_POST['assigned_to'] ?? ''));
        $notes          = trim((string)($_POST['notes'] ?? ''));

        $errors = [];

        if ($id <= 0) { $errors[] = 'Invalid client ID.'; }
        if (empty($clientName)) { $errors[] = 'Client name is required.'; }
        if (mb_strlen($clientName) > 150) { $errors[] = 'Client name cannot exceed 150 characters.'; }
        if (!empty($companyName) && mb_strlen($companyName) > 150) { $errors[] = 'Company name cannot exceed 150 characters.'; }

        if (empty($email)) {
            $errors[] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (mb_strlen($email) > 191) {
            $errors[] = 'Email address cannot exceed 191 characters.';
        }

        if (empty($phone)) {
            $errors[] = 'Phone number is required.';
        } elseif (!preg_match('/^[0-9+\-\s().]{7,50}$/', $phone)) {
            $errors[] = 'Please provide a valid phone number.';
        }

        if (!in_array($status, $validStatuses, true)) {
            $status = 'New';
        }

        if (mb_strlen($notes) > 5000) {
            $errors[] = 'Notes cannot exceed 5000 characters.';
        }

        if (!empty($errors)) {
            set_flash('error', implode(' ', $errors));
            redirect(ADMIN_URL . '/pages/clients.php');
        }

        try {
            $now = date('Y-m-d H:i:s');
            $db->execute(
                "UPDATE clients SET 
                    client_name = :client_name,
                    company_name = :company_name,
                    email = :email,
                    phone = :phone,
                    alternate_phone = :alternate_phone,
                    service = :service,
                    source = :source,
                    status = :status,
                    assigned_to = :assigned_to,
                    notes = :notes,
                    updated_at = :updated_at
                 WHERE id = :id",
                [
                    ':client_name'     => $clientName,
                    ':company_name'    => !empty($companyName) ? $companyName : null,
                    ':email'           => $email,
                    ':phone'           => $phone,
                    ':alternate_phone' => !empty($alternatePhone) ? $alternatePhone : null,
                    ':service'         => !empty($service) ? $service : null,
                    ':source'          => !empty($source) ? $source : null,
                    ':status'          => $status,
                    ':assigned_to'     => !empty($assignedTo) ? $assignedTo : null,
                    ':notes'           => !empty($notes) ? $notes : null,
                    ':updated_at'      => $now,
                    ':id'              => $id
                ]
            );
            set_flash('success', "Client '{$clientName}' updated successfully!");
        } catch (\Throwable $e) {
            error_log("Update client error: " . $e->getMessage());
            set_flash('error', 'Database error updating client.');
        }

        redirect(ADMIN_URL . '/pages/clients.php');
    }

    // 3. DELETE CLIENT
    if ($postAction === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $client = $db->fetch("SELECT client_name FROM clients WHERE id = :id", [':id' => $id]);
                $cName = $client['client_name'] ?? 'Record';
                // Decouple linked leads so no orphan references or deletion errors occur
                $db->execute("UPDATE leads SET client_id = NULL WHERE client_id = :id", [':id' => $id]);
                $db->execute("DELETE FROM clients WHERE id = :id", [':id' => $id]);
                set_flash('success', "Client '{$cName}' was permanently deleted.");
            } catch (\Throwable $e) {
                error_log("Delete client error: " . $e->getMessage());
                set_flash('error', 'Database error deleting client.');
            }
        }
        redirect(ADMIN_URL . '/pages/clients.php');
    }

    // 3b. BULK DELETE CLIENTS
    if ($postAction === 'bulk_delete') {
        $rawIds = $_POST['client_ids'] ?? [];
        $clientIds = array_values(array_filter(array_map('intval', (array)$rawIds), fn($v) => $v > 0));
        if (empty($clientIds)) {
            set_flash('error', 'No clients were selected for deletion.');
        } else {
            try {
                $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
                // Decouple linked leads
                $db->execute("UPDATE leads SET client_id = NULL WHERE client_id IN ($placeholders)", $clientIds);
                // Delete the clients
                $db->execute("DELETE FROM clients WHERE id IN ($placeholders)", $clientIds);
                set_flash('success', count($clientIds) . " client(s) were permanently deleted.");
            } catch (\Throwable $e) {
                error_log("Bulk delete client error: " . $e->getMessage());
                set_flash('error', 'Database error deleting clients.');
            }
        }
        redirect(ADMIN_URL . '/pages/clients.php');
    }
}

// -----------------------------------------------------------------------------
// FILTERING, SEARCHING & PAGINATION
// -----------------------------------------------------------------------------
$searchQuery   = trim((string)($_GET['search'] ?? $_GET['q'] ?? ''));
$statusFilter  = trim((string)($_GET['status'] ?? 'all'));
$serviceFilter = trim((string)($_GET['service'] ?? 'all'));
$dateFilter    = trim((string)($_GET['date'] ?? ''));
$currentPageNo = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 10;
$offset        = ($currentPageNo - 1) * $perPage;

$whereClauses = [];
$queryParams  = [];

if (!empty($searchQuery)) {
    $whereClauses[] = "(client_name LIKE :sq OR company_name LIKE :sq OR email LIKE :sq OR phone LIKE :sq OR notes LIKE :sq)";
    $queryParams[':sq'] = '%' . $searchQuery . '%';
}

if ($statusFilter !== 'all' && in_array($statusFilter, $validStatuses, true)) {
    $whereClauses[] = "status = :st";
    $queryParams[':st'] = $statusFilter;
}

if ($serviceFilter !== 'all' && in_array($serviceFilter, $validServices, true)) {
    $whereClauses[] = "service = :svc";
    $queryParams[':svc'] = $serviceFilter;
}

if (!empty($dateFilter)) {
    $whereClauses[] = "created_at LIKE :dt";
    $queryParams[':dt'] = $dateFilter . '%';
}

$whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

$totalFiltered = 0;
$clients = [];

try {
    if ($db->isConnected()) {
        $totalFiltered = (int)$db->fetchColumn("SELECT COUNT(*) FROM clients {$whereSql}", $queryParams);
        $clientsSql = "SELECT * FROM clients {$whereSql} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}";
        $clients = $db->fetchAll($clientsSql, $queryParams);
    }
} catch (\Throwable $e) {
    error_log("Clients query error: " . $e->getMessage());
}

$totalPages = max(1, (int)ceil($totalFiltered / $perPage));

// KPI counters across all clients
$kpiCounts = ['total' => 0, 'New' => 0, 'Active' => 0, 'Inactive' => 0, 'Completed' => 0];
try {
    if ($db->isConnected()) {
        $rows = $db->fetchAll("SELECT status, COUNT(*) as cnt FROM clients GROUP BY status");
        foreach ($rows as $r) {
            $st = $r['status'];
            $cnt = (int)$r['cnt'];
            $kpiCounts['total'] += $cnt;
            if (isset($kpiCounts[$st])) {
                $kpiCounts[$st] = $cnt;
            }
        }
    }
} catch (\Throwable) {}

// -----------------------------------------------------------------------------
// HELPER: Render Mobile Client Card
// -----------------------------------------------------------------------------
function render_mobile_client_card(PDO|Database $db, array $client): string {
    $rev = get_client_revenue_summary($db, $client['client_name'], $client['company_name'], (int)$client['id']);
    $lastContact = get_client_last_contact($db, $client['client_name'], $client['company_name'], $client['created_at'], (int)$client['id']);
    $initials = strtoupper(substr($client['client_name'], 0, 1));
    $nameParts = explode(' ', trim($client['client_name']));
    if (isset($nameParts[1])) { $initials .= strtoupper(substr($nameParts[1], 0, 1)); }
    $phone = trim((string)($client['phone'] ?? ''));
    $hasPhone = !empty($phone);

    ob_start();
    ?>
    <div class="mobile-client-card" data-client-id="<?= (int)$client['id'] ?>">
      <div class="mobile-client-card-header">
        <div style="display: flex; align-items: center; gap: 10px;">
          <div class="client-avatar-badge"><?= e($initials) ?></div>
          <div>
            <a href="javascript:void(0)" class="mobile-client-card-title btn-view-client" data-id="<?= (int)$client['id'] ?>">
              <?= e($client['client_name']) ?>
            </a>
            <div class="mobile-client-card-company"><?= e($client['company_name'] ?: 'Individual Client') ?></div>
          </div>
        </div>
        <span class="status-pill status-<?= strtolower(e($client['status'])) ?>">
          <?= e($client['status']) ?>
        </span>
      </div>

      <div class="mobile-client-card-body">
        <div class="mobile-card-row">
          <span class="mobile-card-label">Service</span>
          <span style="font-family: 'DM Mono', monospace; font-size: 11px; background: #ffffff; padding: 2px 8px; border-radius: 4px; border: 1px solid var(--border-light);">
            <?= e($client['service'] ?? 'General') ?>
          </span>
        </div>

        <?php if ($hasPhone): ?>
          <div class="mobile-card-row">
            <span class="mobile-card-label">Phone</span>
            <a href="tel:<?= e($phone) ?>" class="mobile-card-phone"><?= e($phone) ?></a>
          </div>
        <?php endif; ?>

        <div class="mobile-card-row">
          <span class="mobile-card-label">Revenue</span>
          <div>
            <strong style="font-family: 'DM Mono', monospace;">₹<?= number_format($rev['total'], 2) ?></strong>
            <?php if ($rev['pending'] > 0): ?>
              <span style="font-size: 10px; color: #b45309; margin-left: 4px;">(₹<?= number_format($rev['pending'], 0) ?> pend)</span>
            <?php endif; ?>
          </div>
        </div>

        <div class="mobile-card-row">
          <span class="mobile-card-label">Last Contact</span>
          <span style="font-family: 'DM Mono', monospace; font-size: 11px; color: var(--text-muted);"><?= e(format_date($lastContact, 'M j, Y')) ?></span>
        </div>
      </div>

      <div class="mobile-client-card-actions">
        <?php if ($hasPhone): ?>
          <a href="tel:<?= e($phone) ?>" class="btn-call-action btn-touch-44">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
            </svg>
            <span>Call</span>
          </a>
        <?php endif; ?>
        <button type="button" class="btn-action btn-view-client btn-touch-44" data-id="<?= (int)$client['id'] ?>">
          <span>View</span>
        </button>
        <button type="button" class="btn-action btn-edit-client btn-touch-44" 
                data-id="<?= (int)$client['id'] ?>"
                data-client='<?= htmlspecialchars(json_encode($client), ENT_QUOTES, 'UTF-8') ?>'>
          <span>Edit</span>
        </button>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

// -----------------------------------------------------------------------------
// AJAX MODE: Return table rows HTML, mobile cards HTML & pagination metadata
// -----------------------------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    
    // Render Desktop Table Rows
    ob_start();
    ?>
    <?php if (empty($clients)): ?>
      <tr>
        <td colspan="9">
          <div class="empty-state" style="padding: 40px 20px;">
            No clients found matching your search criteria.
          </div>
        </td>
      </tr>
    <?php else: ?>
      <?php foreach ($clients as $client): ?>
        <?php 
          $rev = get_client_revenue_summary($db, $client['client_name'], $client['company_name'], (int)$client['id']);
          $lastContact = get_client_last_contact($db, $client['client_name'], $client['company_name'], $client['created_at'], (int)$client['id']);
          $initials = strtoupper(substr($client['client_name'], 0, 1));
          $nameParts = explode(' ', trim($client['client_name']));
          if (isset($nameParts[1])) { $initials .= strtoupper(substr($nameParts[1], 0, 1)); }
        ?>
        <tr data-client-id="<?= (int)$client['id'] ?>">
          <td style="width: 40px; text-align: center;">
            <input type="checkbox" class="client-select-checkbox row-select-checkbox" value="<?= (int)$client['id'] ?>">
          </td>
          <td>
            <div style="display: flex; align-items: center; gap: 10px;">
              <div style="width: 34px; height: 34px; border-radius: 50%; background: #181922; color: var(--lime); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 11px; font-family: 'Space Grotesk', sans-serif; flex-shrink: 0; border: 1px solid #282a38;">
                <?= e($initials) ?>
              </div>
              <div>
                <strong style="color: var(--text-dark); font-size: 13.5px;"><?= e($client['client_name']) ?></strong>
                <div style="font-size: 11px; color: var(--text-muted);"><?= e($client['email']) ?></div>
              </div>
            </div>
          </td>
          <td>
            <strong style="color: var(--text-body); font-size: 13px;"><?= e($client['company_name'] ?? '—') ?></strong>
            <?php if (!empty($client['source'])): ?>
              <div style="font-size: 10.5px; color: var(--text-light); font-family: 'DM Mono', monospace;"><?= e($client['source']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-family: 'DM Mono', monospace; background: var(--main-bg); border: 1px solid var(--border-light); color: var(--text-dark);">
              <?= e($client['service'] ?? 'General') ?>
            </span>
          </td>
          <td>
            <a href="tel:<?= e($client['phone']) ?>" style="font-family: 'DM Mono', monospace; font-size: 12px; color: var(--text-body); text-decoration: none;">
              <?= e($client['phone']) ?>
            </a>
            <?php if (!empty($client['alternate_phone'])): ?>
              <div style="font-size: 10.5px; color: var(--text-muted); font-family: 'DM Mono', monospace;">Alt: <?= e($client['alternate_phone']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <span class="status-pill status-<?= strtolower(e($client['status'])) ?>">
              <?= e($client['status']) ?>
            </span>
          </td>
          <td style="font-family: 'DM Mono', monospace; font-size: 12px; color: var(--text-muted); white-space: nowrap;">
            <?= e(format_date($lastContact, 'M j, Y')) ?>
          </td>
          <td>
            <div style="font-family: 'DM Mono', monospace; font-weight: 700; font-size: 13px; color: var(--text-dark);">
              ₹<?= number_format($rev['total'], 2) ?>
            </div>
            <?php if ($rev['pending'] > 0): ?>
              <span style="font-size: 10.5px; color: #b45309; font-family: 'DM Mono', monospace;">
                ₹<?= number_format($rev['pending'], 0) ?> pend
              </span>
            <?php endif; ?>
          </td>
          <td style="text-align: right; white-space: nowrap;">
            <div style="display: inline-flex; gap: 6px;">
              <button type="button" class="btn-action btn-view-client" data-id="<?= (int)$client['id'] ?>" title="View Central Client Profile">
                View
              </button>
              <button type="button" class="btn-action btn-edit-client" 
                      data-id="<?= (int)$client['id'] ?>"
                      data-client='<?= htmlspecialchars(json_encode($client), ENT_QUOTES, 'UTF-8') ?>'
                      title="Edit Client Information">
                Edit
              </button>
              <form method="POST" action="clients.php" style="display: inline;" onsubmit="return false;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete" />
                <input type="hidden" name="id" value="<?= (int)$client['id'] ?>" />
                <button type="submit" class="btn-action" style="color: #ef4444; border-color: #fecaca;"
                        data-confirm="Are you sure you want to delete client '<?= e(addslashes($client['client_name'])) ?>'? All records and notes will be removed."
                        data-confirm-title="Delete Client"
                        data-confirm-btn="Delete Client"
                        title="Delete Client Record">
                  Delete
                </button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php
    $rowsHtml = ob_get_clean();

    // Render Mobile Cards
    ob_start();
    ?>
    <?php if (empty($clients)): ?>
      <div class="empty-state" style="padding: 40px 20px;">
        No clients found matching your search criteria.
      </div>
    <?php else: ?>
      <?php foreach ($clients as $client): ?>
        <?= render_mobile_client_card($db, $client) ?>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php
    $mobileCardsHtml = ob_get_clean();

    // Render pagination links
    ob_start();
    ?>
    <div class="pagination-info">
      Showing <?= min($offset + 1, $totalFiltered) ?> to <?= min($offset + $perPage, $totalFiltered) ?> of <?= $totalFiltered ?> clients
    </div>
    <div class="pagination-links">
      <?php if ($currentPageNo > 1): ?>
        <a href="?page=<?= $currentPageNo - 1 ?>&q=<?= urlencode($searchQuery) ?>&status=<?= urlencode($statusFilter) ?>&service=<?= urlencode($serviceFilter) ?>" class="page-btn">&larr; Prev</a>
      <?php endif; ?>
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="?page=<?= $p ?>&q=<?= urlencode($searchQuery) ?>&status=<?= urlencode($statusFilter) ?>&service=<?= urlencode($serviceFilter) ?>" class="page-btn <?= $p === $currentPageNo ? 'active' : '' ?>"><?= $p ?></a>
      <?php endfor; ?>
      <?php if ($currentPageNo < $totalPages): ?>
        <a href="?page=<?= $currentPageNo + 1 ?>&q=<?= urlencode($searchQuery) ?>&status=<?= urlencode($statusFilter) ?>&service=<?= urlencode($serviceFilter) ?>" class="page-btn">Next &rarr;</a>
      <?php endif; ?>
    </div>
    <?php
    $paginationHtml = ob_get_clean();

    echo json_encode([
        'success'      => true,
        'html'         => $rowsHtml,
        'mobile_cards' => $mobileCardsHtml,
        'pagination'   => $paginationHtml,
        'total'        => $totalFiltered,
        'page'         => $currentPageNo,
        'pages'        => $totalPages
    ]);
    exit;
}

require_once dirname(__DIR__) . '/includes/admin_header.php';
?>

  <!-- PAGE HEADER & PRIMARY ACTION -->
  <div class="page-header">
    <div>
      <h1 class="page-title">Client Management</h1>
      <p class="page-subtitle">Central business directory, client relationships, contracted services, and revenue history.</p>
    </div>
    <button type="button" class="btn-action" id="openAddClientBtn" style="padding: 10px 18px; background: var(--primary); color: #ffffff; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; border-radius: var(--radius-sm);">
      <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
      </svg>
      <span>+ Add Client</span>
    </button>
  </div>

  <!-- KPI STATUS CARDS -->
  <div class="kpi-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="kpi-card">
      <div class="kpi-top">
        <span class="kpi-label">Active Accounts</span>
        <div class="kpi-icon-wrap lime">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
      </div>
      <div class="kpi-value"><?= (int)$kpiCounts['Active'] ?></div>
      <div class="kpi-footer">
        <span class="kpi-tag positive">In Retainer</span>
        <span>Active projects</span>
      </div>
    </div>

    <div class="kpi-card">
      <div class="kpi-top">
        <span class="kpi-label">New Clients</span>
        <div class="kpi-icon-wrap blue">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
          </svg>
        </div>
      </div>
      <div class="kpi-value"><?= (int)$kpiCounts['New'] ?></div>
      <div class="kpi-footer">
        <span class="kpi-tag positive">Onboarding</span>
        <span>Recently added</span>
      </div>
    </div>

    <div class="kpi-card">
      <div class="kpi-top">
        <span class="kpi-label">Completed</span>
        <div class="kpi-icon-wrap purple">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
          </svg>
        </div>
      </div>
      <div class="kpi-value"><?= (int)$kpiCounts['Completed'] ?></div>
      <div class="kpi-footer">
        <span class="kpi-tag positive">Shipped</span>
        <span>Past delivery</span>
      </div>
    </div>

    <div class="kpi-card">
      <div class="kpi-top">
        <span class="kpi-label">Inactive</span>
        <div class="kpi-icon-wrap orange">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
          </svg>
        </div>
      </div>
      <div class="kpi-value"><?= (int)$kpiCounts['Inactive'] ?></div>
      <div class="kpi-footer">
        <span class="kpi-tag alert">On Hold</span>
        <span>Re-engagement list</span>
      </div>
    </div>
  </div>

  <!-- SEARCH & FILTER TOOLBAR -->
  <div class="filter-toolbar">
    <div class="filter-group" style="flex: 1;">
      <!-- Search Input -->
      <div class="search-box" style="position: relative; flex: 1; max-width: 420px;">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none;">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input
          type="search"
          id="clientSearchInput"
          placeholder="Search client, company, email, phone..."
          value="<?= e($searchQuery) ?>"
          style="width: 100%; padding: 10px 36px 10px 36px; border: 1px solid var(--border-light); border-radius: var(--radius-sm); font-size: 13px; background: #ffffff; outline: none;"
        />
        <button type="button" id="clearClientSearchBtn" aria-label="Clear search" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); display: none; background: transparent; border: 0; color: var(--text-muted); font-size: 14px; padding: 4px; cursor: pointer;">
          ✕
        </button>
      </div>

      <!-- Desktop Status Filter -->
      <select id="statusFilterSelect" class="filter-select desktop-only-inline">
        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
        <?php foreach ($validStatuses as $st): ?>
          <option value="<?= e($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= e($st) ?></option>
        <?php endforeach; ?>
      </select>

      <!-- Desktop Service Filter -->
      <select id="serviceFilterSelect" class="filter-select desktop-only-inline">
        <option value="all" <?= $serviceFilter === 'all' ? 'selected' : '' ?>>All Services</option>
        <?php foreach ($validServices as $svc): ?>
          <option value="<?= e($svc) ?>" <?= $serviceFilter === $svc ? 'selected' : '' ?>><?= e($svc) ?></option>
        <?php endforeach; ?>
      </select>

      <!-- Mobile Filter Trigger Button -->
      <button type="button" class="btn-action mobile-only-inline" id="openMobileFilterBtn" style="padding: 10px 14px; background: #ffffff; min-height: 44px; display: inline-flex; align-items: center; gap: 6px;">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
        </svg>
        <span>Filters</span>
      </button>
    </div>

    <div style="font-size: 12px; color: var(--text-muted); font-family: 'DM Mono', monospace;" id="searchIndicator">
      Live instant filter enabled
    </div>
  </div>

  <!-- MOBILE FILTER BOTTOM SHEET MODAL -->
  <div class="admin-modal-backdrop" id="mobileFilterModalBackdrop">
    <div class="admin-modal-box modal-md" role="dialog" aria-modal="true" style="border-radius: 20px 20px 0 0; margin-bottom: 0;">
      <div class="modal-header">
        <h3 class="modal-title">Filter Clients</h3>
        <button class="modal-close-btn" id="closeMobileFilterModal" aria-label="Close filters">&times;</button>
      </div>

      <div class="modal-body" style="display: flex; flex-direction: column; gap: 16px;">
        <div class="admin-form-group">
          <label class="admin-form-label">Client Status</label>
          <select id="mobileStatusFilterSelect" class="admin-form-input">
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
            <?php foreach ($validStatuses as $st): ?>
              <option value="<?= e($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= e($st) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="admin-form-group">
          <label class="admin-form-label">Service Contracted</label>
          <select id="mobileServiceFilterSelect" class="admin-form-input">
            <option value="all" <?= $serviceFilter === 'all' ? 'selected' : '' ?>>All Services</option>
            <?php foreach ($validServices as $svc): ?>
              <option value="<?= e($svc) ?>" <?= $serviceFilter === $svc ? 'selected' : '' ?>><?= e($svc) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="admin-form-group">
          <label class="admin-form-label">Created Date</label>
          <input type="date" id="mobileDateFilterInput" class="admin-form-input" value="<?= e($dateFilter) ?>">
        </div>
      </div>

      <div class="modal-footer" style="flex-direction: column; gap: 10px;">
        <button type="button" class="btn-modal-confirm primary" id="applyMobileFiltersBtn" style="width: 100%; min-height: 44px;">
          Apply Filters
        </button>
        <button type="button" class="btn-modal-cancel" id="resetMobileFiltersBtn" style="width: 100%; min-height: 44px;">
          Reset Filters
        </button>
      </div>
    </div>
  </div>

  <!-- CLIENTS DATA CONTAINER -->
  <div class="data-card">
    <div class="table-responsive desktop-only-table">
      <table class="admin-table">
        <thead>
          <tr>
            <th style="width: 40px; text-align: center;">
              <input type="checkbox" id="selectAllClients" class="bulk-select-all" title="Select All Clients">
            </th>
            <th>Client</th>
            <th>Company</th>
            <th>Service</th>
            <th>Phone</th>
            <th>Status</th>
            <th>Last Contact</th>
            <th>Revenue</th>
            <th style="text-align: right; width: 190px;">Actions</th>
          </tr>
        </thead>
        <tbody id="clientsTableBody">
          <?php if (empty($clients)): ?>
            <tr>
              <td colspan="9">
                <div class="empty-state">No clients found matching your search filters.</div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($clients as $client): ?>
              <?php 
                $rev = get_client_revenue_summary($db, $client['client_name'], $client['company_name'], (int)$client['id']);
                $lastContact = get_client_last_contact($db, $client['client_name'], $client['company_name'], $client['created_at'], (int)$client['id']);
                $initials = strtoupper(substr($client['client_name'], 0, 1));
                $nameParts = explode(' ', trim($client['client_name']));
                if (isset($nameParts[1])) { $initials .= strtoupper(substr($nameParts[1], 0, 1)); }
              ?>
              <tr data-client-id="<?= (int)$client['id'] ?>">
                <td style="width: 40px; text-align: center;">
                  <input type="checkbox" class="client-select-checkbox row-select-checkbox" value="<?= (int)$client['id'] ?>">
                </td>
                <td>
                  <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 34px; height: 34px; border-radius: 50%; background: #181922; color: var(--lime); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 11px; font-family: 'Space Grotesk', sans-serif; flex-shrink: 0; border: 1px solid #282a38;">
                      <?= e($initials) ?>
                    </div>
                    <div>
                      <strong style="color: var(--text-dark); font-size: 13.5px;"><?= e($client['client_name']) ?></strong>
                      <div style="font-size: 11px; color: var(--text-muted);"><?= e($client['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td>
                  <strong style="color: var(--text-body); font-size: 13px;"><?= e($client['company_name'] ?? '—') ?></strong>
                  <?php if (!empty($client['source'])): ?>
                    <div style="font-size: 10.5px; color: var(--text-light); font-family: 'DM Mono', monospace;"><?= e($client['source']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-family: 'DM Mono', monospace; background: var(--main-bg); border: 1px solid var(--border-light); color: var(--text-dark);">
                    <?= e($client['service'] ?? 'General') ?>
                  </span>
                </td>
                <td>
                  <a href="tel:<?= e($client['phone']) ?>" style="font-family: 'DM Mono', monospace; font-size: 12px; color: var(--text-body); text-decoration: none;">
                    <?= e($client['phone']) ?>
                  </a>
                  <?php if (!empty($client['alternate_phone'])): ?>
                    <div style="font-size: 10.5px; color: var(--text-muted); font-family: 'DM Mono', monospace;">Alt: <?= e($client['alternate_phone']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="status-pill status-<?= strtolower(e($client['status'])) ?>">
                    <?= e($client['status']) ?>
                  </span>
                </td>
                <td style="font-family: 'DM Mono', monospace; font-size: 12px; color: var(--text-muted); white-space: nowrap;">
                  <?= e(format_date($lastContact, 'M j, Y')) ?>
                </td>
                <td>
                  <div style="font-family: 'DM Mono', monospace; font-weight: 700; font-size: 13px; color: var(--text-dark);">
                    ₹<?= number_format($rev['total'], 2) ?>
                  </div>
                  <?php if ($rev['pending'] > 0): ?>
                    <span style="font-size: 10.5px; color: #b45309; font-family: 'DM Mono', monospace;">
                      ₹<?= number_format($rev['pending'], 0) ?> pend
                    </span>
                  <?php endif; ?>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                  <div style="display: inline-flex; gap: 6px;">
                    <button type="button" class="btn-action btn-view-client" data-id="<?= (int)$client['id'] ?>" title="View Central Client Profile">
                      View
                    </button>
                    <button type="button" class="btn-action btn-edit-client" 
                            data-id="<?= (int)$client['id'] ?>"
                            data-client='<?= htmlspecialchars(json_encode($client), ENT_QUOTES, 'UTF-8') ?>'
                            title="Edit Client Information">
                      Edit
                    </button>
                    <form method="POST" action="clients.php" style="display: inline;" onsubmit="return false;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete" />
                      <input type="hidden" name="id" value="<?= (int)$client['id'] ?>" />
                      <button type="submit" class="btn-action" style="color: #ef4444; border-color: #fecaca;"
                              data-confirm="Are you sure you want to delete client '<?= e(addslashes($client['client_name'])) ?>'? All records and notes will be removed."
                              data-confirm-title="Delete Client"
                              data-confirm-btn="Delete Client"
                              title="Delete Client Record">
                        Delete
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Mobile Client Cards Container -->
    <div class="mobile-client-cards-list mobile-only-block" id="mobileClientsCardsBody">
      <?php if (empty($clients)): ?>
        <div class="empty-state" style="padding: 40px 20px;">
          No clients found matching your search criteria.
        </div>
      <?php else: ?>
        <?php foreach ($clients as $client): ?>
          <?= render_mobile_client_card($db, $client) ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- PAGINATION -->
    <div class="pagination-bar" id="paginationContainer">
      <div class="pagination-info">
        Showing <?= min($offset + 1, $totalFiltered) ?> to <?= min($offset + $perPage, $totalFiltered) ?> of <?= $totalFiltered ?> clients
      </div>
      <div class="pagination-links">
        <?php if ($currentPageNo > 1): ?>
          <a href="?page=<?= $currentPageNo - 1 ?>&q=<?= urlencode($searchQuery) ?>&status=<?= urlencode($statusFilter) ?>&service=<?= urlencode($serviceFilter) ?>" class="page-btn">&larr; Prev</a>
        <?php endif; ?>
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <a href="?page=<?= $p ?>&q=<?= urlencode($searchQuery) ?>&status=<?= urlencode($statusFilter) ?>&service=<?= urlencode($serviceFilter) ?>" class="page-btn <?= $p === $currentPageNo ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($currentPageNo < $totalPages): ?>
          <a href="?page=<?= $currentPageNo + 1 ?>&q=<?= urlencode($searchQuery) ?>&status=<?= urlencode($statusFilter) ?>&service=<?= urlencode($serviceFilter) ?>" class="page-btn">Next &rarr;</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Bulk Delete Clients Form -->
  <form id="bulkDeleteClientsForm" method="POST" action="clients.php" style="display: none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="bulk_delete">
    <div id="bulkDeleteClientsInputs"></div>
  </form>

  <!-- Floating Bulk Actions Toolbar -->
  <div id="clientsBulkBar" class="bulk-actions-bar" style="display: none;">
    <div class="bulk-actions-info">
      <span class="bulk-count-badge" id="clientsSelectedCount">0</span>
      <span>client(s) selected</span>
    </div>
    <div class="bulk-actions-buttons">
      <button type="button" class="btn-bulk-delete" id="btnBulkDeleteClients">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
        Delete Selected (<span id="clientsDeleteCountBtnText">0</span>)
      </button>
      <button type="button" class="btn-bulk-cancel" id="btnBulkCancelClients">Deselect All</button>
    </div>
  </div>

  <!-- =========================================================
       MODAL 1: ADD CLIENT MODAL
  ========================================================= -->
  <div class="admin-modal-backdrop" id="addClientModalBackdrop">
    <div class="admin-modal-box modal-lg" role="dialog" aria-modal="true">
      <div class="modal-header">
        <div class="modal-title-group">
          <div class="modal-icon info">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
            </svg>
          </div>
          <h3 class="modal-title">Add New Client</h3>
        </div>
        <button class="modal-close-btn" id="closeAddClientModal" aria-label="Close dialog">&times;</button>
      </div>

      <form method="POST" action="clients.php" id="addClientForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create" />

        <div class="admin-modal-scrollable">
          <div class="form-grid-2">
            <div class="admin-form-group">
              <label class="admin-form-label" for="add_client_name">Client Name <span style="color:#ef4444;">*</span></label>
              <input type="text" id="add_client_name" name="client_name" class="admin-form-input" placeholder="e.g. Rajesh Menon" required maxlength="150" />
            </div>

            <div class="admin-form-group">
              <label class="admin-form-label" for="add_company_name">Company Name</label>
              <input type="text" id="add_company_name" name="company_name" class="admin-form-input" placeholder="e.g. Apex Logistics India" maxlength="150" />
            </div>
          </div>

          <div class="form-grid-2">
            <div class="admin-form-group">
              <label class="admin-form-label" for="add_email">Email Address <span style="color:#ef4444;">*</span></label>
              <input type="email" id="add_email" name="email" class="admin-form-input" placeholder="e.g. rajesh@apexlogistics.in" required maxlength="191" />
            </div>

            <div class="admin-form-group">
              <label class="admin-form-label" for="add_phone">Phone Number <span style="color:#ef4444;">*</span></label>
              <input type="tel" id="add_phone" name="phone" class="admin-form-input" placeholder="e.g. +1 (555) 567-8901" required maxlength="50" />
            </div>
          </div>

          <div class="form-grid-2">
            <div class="admin-form-group">
              <label class="admin-form-label" for="add_alternate_phone">Alternate Phone</label>
              <input type="tel" id="add_alternate_phone" name="alternate_phone" class="admin-form-input" placeholder="e.g. +1 (555) 567-8999" maxlength="50" />
            </div>

            <div class="admin-form-group">
              <label class="admin-form-label" for="add_service">Contracted / Interested Service</label>
              <select id="add_service" name="service" class="admin-form-input">
                <option value="">Select Service...</option>
                <?php foreach ($validServices as $svc): ?>
                  <option value="<?= e($svc) ?>"><?= e($svc) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-grid-2">
            <div class="admin-form-group">
              <label class="admin-form-label" for="add_source">Lead / Client Source</label>
              <select id="add_source" name="source" class="admin-form-input">
                <option value="">Select Source...</option>
                <?php foreach ($validSources as $src): ?>
                  <option value="<?= e($src) ?>"><?= e($src) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="admin-form-group">
              <label class="admin-form-label" for="add_status">Account Status <span style="color:#ef4444;">*</span></label>
              <select id="add_status" name="status" class="admin-form-input" required>
                <?php foreach ($validStatuses as $st): ?>
                  <option value="<?= e($st) ?>" <?= $st === 'New' ? 'selected' : '' ?>><?= e($st) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="admin-form-group">
            <label class="admin-form-label" for="add_assigned_to">Assigned Account Lead</label>
            <input type="text" id="add_assigned_to" name="assigned_to" class="admin-form-input" placeholder="e.g. Website Tailors Administrator" maxlength="100" />
          </div>

          <div class="admin-form-group">
            <label class="admin-form-label" for="add_notes">Relationship Notes &amp; Scope</label>
            <textarea id="add_notes" name="notes" class="admin-form-input" rows="3" placeholder="Key client requirements, milestones, communication preferences..." maxlength="5000"></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-modal-cancel" id="cancelAddClientModal">Cancel</button>
          <button type="submit" class="btn-modal-confirm primary">Save Client Record</button>
        </div>
      </form>
    </div>
  </div>

  <!-- =========================================================
       MODAL 2: EDIT CLIENT MODAL
  ========================================================= -->
  <div class="admin-modal-backdrop" id="editClientModalBackdrop">
    <div class="admin-modal-box modal-lg" role="dialog" aria-modal="true">
      <div class="modal-header">
        <div class="modal-title-group">
          <div class="modal-icon info">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
          </div>
          <h3 class="modal-title">Edit Client Details</h3>
        </div>
        <button class="modal-close-btn" id="closeEditClientModal" aria-label="Close dialog">&times;</button>
      </div>

      <form method="POST" action="clients.php" id="editClientForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update" />
        <input type="hidden" name="id" id="edit_client_id" value="" />

        <div class="admin-modal-scrollable">
          <div class="form-grid-2">
            <div class="admin-form-group">
              <label class="admin-form-label" for="edit_client_name">Client Name <span style="color:#ef4444;">*</span></label>
              <input type="text" id="edit_client_name" name="client_name" class="admin-form-input" required maxlength="150" />
            </div>

            <div class="admin-form-group">
              <label class="admin-form-label" for="edit_company_name">Company Name</label>
              <input type="text" id="edit_company_name" name="company_name" class="admin-form-input" maxlength="150" />
            </div>
          </div>

          <div class="form-grid-2">
            <div class="admin-form-group">
              <label class="admin-form-label" for="edit_email">Email Address <span style="color:#ef4444;">*</span></label>
              <input type="email" id="edit_email" name="email" class="admin-form-input" required maxlength="191" />
            </div>

            <div class="admin-form-group">
              <label class="admin-form-label" for="edit_phone">Phone Number <span style="color:#ef4444;">*</span></label>
              <input type="tel" id="edit_phone" name="phone" class="admin-form-input" required maxlength="50" />
            </div>
          </div>

          <div class="form-grid-2">
            <div class="admin-form-group">
              <label class="admin-form-label" for="edit_alternate_phone">Alternate Phone</label>
              <input type="tel" id="edit_alternate_phone" name="alternate_phone" class="admin-form-input" maxlength="50" />
            </div>

            <div class="admin-form-group">
              <label class="admin-form-label" for="edit_service">Service Contracted</label>
              <select id="edit_service" name="service" class="admin-form-input">
                <option value="">Select Service...</option>
                <?php foreach ($validServices as $svc): ?>
                  <option value="<?= e($svc) ?>"><?= e($svc) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-grid-2">
            <div class="admin-form-group">
              <label class="admin-form-label" for="edit_source">Lead / Client Source</label>
              <select id="edit_source" name="source" class="admin-form-input">
                <option value="">Select Source...</option>
                <?php foreach ($validSources as $src): ?>
                  <option value="<?= e($src) ?>"><?= e($src) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="admin-form-group">
              <label class="admin-form-label" for="edit_status">Account Status <span style="color:#ef4444;">*</span></label>
              <select id="edit_status" name="status" class="admin-form-input" required>
                <?php foreach ($validStatuses as $st): ?>
                  <option value="<?= e($st) ?>"><?= e($st) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="admin-form-group">
            <label class="admin-form-label" for="edit_assigned_to">Assigned Account Lead</label>
            <input type="text" id="edit_assigned_to" name="assigned_to" class="admin-form-input" maxlength="100" />
          </div>

          <div class="admin-form-group">
            <label class="admin-form-label" for="edit_notes">Relationship Notes &amp; Scope</label>
            <textarea id="edit_notes" name="notes" class="admin-form-input" rows="3" maxlength="5000"></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-modal-cancel" id="cancelEditClientModal">Cancel</button>
          <button type="submit" class="btn-modal-confirm primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <!-- =========================================================
       MODAL 3: CENTRAL CLIENT DETAIL PROFILE MODAL
  ========================================================= -->
  <div class="admin-modal-backdrop" id="clientDetailModalBackdrop">
    <div class="admin-modal-box modal-xl" role="dialog" aria-modal="true">
      <div class="modal-header">
        <div class="modal-title-group">
          <div style="width: 38px; height: 38px; border-radius: 50%; background: #181922; color: var(--lime); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; font-family: 'Space Grotesk', sans-serif; border: 1px solid #282a38;" id="detailAvatar">
            CL
          </div>
          <div>
            <h3 class="modal-title" id="detailClientName">Client Profile</h3>
            <div style="font-size: 12px; color: var(--text-muted);" id="detailCompanyName">Company</div>
          </div>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
          <span id="detailStatusBadge" class="status-pill status-active">Active</span>
          <button class="modal-close-btn" id="closeClientDetailModal" aria-label="Close dialog">&times;</button>
        </div>
      </div>

      <div class="admin-modal-scrollable" id="detailContent">
        <!-- Content injected dynamically via JavaScript -->
        <div style="padding: 40px; text-align: center; color: var(--text-muted);">
          Loading client profile...
        </div>
      </div>

      <div class="modal-footer" style="justify-content: space-between;">
        <div style="font-size: 11px; color: var(--text-muted); font-family: 'DM Mono', monospace;" id="detailMetaDates">
          Joined: —
        </div>
        <button type="button" class="btn-modal-cancel" id="closeDetailFooterBtn">Close Profile</button>
      </div>
    </div>
  </div>

  <!-- =========================================================
       VANILLA JAVASCRIPT: INSTANT SEARCH, MODALS & PROFILE
  ========================================================= -->
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const searchInput = document.getElementById('clientSearchInput');
    const statusSelect = document.getElementById('statusFilterSelect');
    const serviceSelect = document.getElementById('serviceFilterSelect');
    const tableBody = document.getElementById('clientsTableBody');
    const paginationBox = document.getElementById('paginationContainer');
    const searchIndicator = document.getElementById('searchIndicator');

    const mobileCardsBody = document.getElementById('mobileClientsCardsBody');
    const clearSearchBtn = document.getElementById('clearClientSearchBtn');
    
    // Mobile Filter Modal Elements
    const mobileFilterModal = document.getElementById('mobileFilterModalBackdrop');
    const openMobileFilterBtn = document.getElementById('openMobileFilterBtn');
    const closeMobileFilterBtn = document.getElementById('closeMobileFilterModal');
    const applyMobileFiltersBtn = document.getElementById('applyMobileFiltersBtn');
    const resetMobileFiltersBtn = document.getElementById('resetMobileFiltersBtn');
    const mobileStatusSelect = document.getElementById('mobileStatusFilterSelect');
    const mobileServiceSelect = document.getElementById('mobileServiceFilterSelect');
    const mobileDateInput = document.getElementById('mobileDateFilterInput');

    let debounceTimer = null;

    // Toggle clear search button visibility
    function toggleClearSearchBtn() {
      if (!clearSearchBtn || !searchInput) return;
      if (searchInput.value.trim().length > 0) {
        clearSearchBtn.style.display = 'block';
      } else {
        clearSearchBtn.style.display = 'none';
      }
    }

    if (clearSearchBtn && searchInput) {
      toggleClearSearchBtn();
      clearSearchBtn.addEventListener('click', () => {
        searchInput.value = '';
        toggleClearSearchBtn();
        fetchClients(1);
        searchInput.focus();
      });
    }

    // 1. Asynchronous Dynamic Search without full page reload
    function fetchClients(page = 1) {
      const q = searchInput ? searchInput.value.trim() : '';
      const status = statusSelect ? statusSelect.value : (mobileStatusSelect ? mobileStatusSelect.value : 'all');
      const service = serviceSelect ? serviceSelect.value : (mobileServiceSelect ? mobileServiceSelect.value : 'all');
      const dateVal = mobileDateInput ? mobileDateInput.value.trim() : '';

      if (searchIndicator) {
        searchIndicator.textContent = 'Filtering...';
      }

      const params = new URLSearchParams({
        ajax: '1',
        q: q,
        status: status,
        service: service,
        date: dateVal,
        page: page
      });

      fetch(`clients.php?${params.toString()}`)
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            if (tableBody) tableBody.innerHTML = data.html;
            if (mobileCardsBody && data.mobile_cards) mobileCardsBody.innerHTML = data.mobile_cards;
            if (paginationBox) paginationBox.innerHTML = data.pagination;
            if (searchIndicator) {
              searchIndicator.textContent = `${data.total} clients matched`;
            }
            // Bind actions on new rows & cards
            bindTableEvents();

            // Update browser URL without reload
            const viewParams = new URLSearchParams({
              q: q,
              status: status,
              service: service,
              date: dateVal,
              page: page
            });
            window.history.replaceState({}, '', `clients.php?${viewParams.toString()}`);
          }
        })
        .catch(err => {
          console.error('Fetch error:', err);
          if (searchIndicator) {
            searchIndicator.textContent = 'Search error';
          }
        });
    }

    if (searchInput) {
      searchInput.addEventListener('input', () => {
        toggleClearSearchBtn();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fetchClients(1), 300);
      });
    }

    if (statusSelect) {
      statusSelect.addEventListener('change', () => {
        if (mobileStatusSelect) mobileStatusSelect.value = statusSelect.value;
        fetchClients(1);
      });
    }

    if (serviceSelect) {
      serviceSelect.addEventListener('change', () => {
        if (mobileServiceSelect) mobileServiceSelect.value = serviceSelect.value;
        fetchClients(1);
      });
    }

    // Mobile Filter Modal Handlers
    if (openMobileFilterBtn && mobileFilterModal) {
      openMobileFilterBtn.addEventListener('click', () => {
        if (mobileStatusSelect && statusSelect) mobileStatusSelect.value = statusSelect.value;
        if (mobileServiceSelect && serviceSelect) mobileServiceSelect.value = serviceSelect.value;
        mobileFilterModal.classList.add('active');
      });

      const closeMobileFilter = () => mobileFilterModal.classList.remove('active');
      if (closeMobileFilterBtn) closeMobileFilterBtn.addEventListener('click', closeMobileFilter);
      mobileFilterModal.addEventListener('click', (e) => {
        if (e.target === mobileFilterModal) closeMobileFilter();
      });

      if (applyMobileFiltersBtn) {
        applyMobileFiltersBtn.addEventListener('click', () => {
          if (statusSelect && mobileStatusSelect) statusSelect.value = mobileStatusSelect.value;
          if (serviceSelect && mobileServiceSelect) serviceSelect.value = mobileServiceSelect.value;
          closeMobileFilter();
          fetchClients(1);
        });
      }

      if (resetMobileFiltersBtn) {
        resetMobileFiltersBtn.addEventListener('click', () => {
          if (statusSelect) statusSelect.value = 'all';
          if (serviceSelect) serviceSelect.value = 'all';
          if (mobileStatusSelect) mobileStatusSelect.value = 'all';
          if (mobileServiceSelect) mobileServiceSelect.value = 'all';
          if (mobileDateInput) mobileDateInput.value = '';
          closeMobileFilter();
          fetchClients(1);
        });
      }
    }

    // Pagination link clicks (delegate)
    paginationBox.addEventListener('click', (e) => {
      const target = e.target.closest('a.page-btn');
      if (target) {
        e.preventDefault();
        const url = new URL(target.href, window.location.origin);
        const page = url.searchParams.get('page') || 1;
        fetchClients(page);
      }
    });

    // 2. Add Client Modal Handling
    const addModal = document.getElementById('addClientModalBackdrop');
    const openAddBtn = document.getElementById('openAddClientBtn');
    const closeAddBtn = document.getElementById('closeAddClientModal');
    const cancelAddBtn = document.getElementById('cancelAddClientModal');

    if (openAddBtn && addModal) {
      openAddBtn.addEventListener('click', () => {
        addModal.classList.add('active');
        const firstInput = addModal.querySelector('input[name="client_name"]');
        if (firstInput) firstInput.focus();
      });

      const closeAdd = () => addModal.classList.remove('active');
      if (closeAddBtn) closeAddBtn.addEventListener('click', closeAdd);
      if (cancelAddBtn) cancelAddBtn.addEventListener('click', closeAdd);
      addModal.addEventListener('click', (e) => {
        if (e.target === addModal) closeAdd();
      });
    }

    // 3. Edit Client Modal Handling
    const editModal = document.getElementById('editClientModalBackdrop');
    const closeEditBtn = document.getElementById('closeEditClientModal');
    const cancelEditBtn = document.getElementById('cancelEditClientModal');

    const closeEdit = () => editModal.classList.remove('active');
    if (closeEditBtn) closeEditBtn.addEventListener('click', closeEdit);
    if (cancelEditBtn) cancelEditBtn.addEventListener('click', closeEdit);
    if (editModal) {
      editModal.addEventListener('click', (e) => {
        if (e.target === editModal) closeEdit();
      });
    }

    // 4. Central Client Detail Profile Modal Handling
    const detailModal = document.getElementById('clientDetailModalBackdrop');
    const closeDetailBtn = document.getElementById('closeClientDetailModal');
    const closeDetailFooterBtn = document.getElementById('closeDetailFooterBtn');
    const detailContent = document.getElementById('detailContent');
    const detailAvatar = document.getElementById('detailAvatar');
    const detailClientName = document.getElementById('detailClientName');
    const detailCompanyName = document.getElementById('detailCompanyName');
    const detailStatusBadge = document.getElementById('detailStatusBadge');
    const detailMetaDates = document.getElementById('detailMetaDates');

    const closeDetail = () => detailModal.classList.remove('active');
    if (closeDetailBtn) closeDetailBtn.addEventListener('click', closeDetail);
    if (closeDetailFooterBtn) closeDetailFooterBtn.addEventListener('click', closeDetail);
    if (detailModal) {
      detailModal.addEventListener('click', (e) => {
        if (e.target === detailModal) closeDetail();
      });
    }

    function openClientDetail(clientId) {
      if (!detailModal || !detailContent) return;
      detailModal.classList.add('active');
      detailContent.innerHTML = '<div style="padding: 40px; text-align: center; color: var(--text-muted);">Loading central client profile...</div>';

      fetch(`clients.php?action=get_detail&id=${clientId}`)
        .then(res => res.json())
        .then(data => {
          if (!data.success) {
            detailContent.innerHTML = `<div class="admin-alert alert-error">${data.error || 'Failed to load client'}</div>`;
            return;
          }

          const c = data.client;
          const rev = data.revenue;
          const calls = data.calls || [];
          const leads = data.leads || [];
          const followups = data.followups || [];

          // Initials
          let initials = c.client_name.substring(0, 1).toUpperCase();
          const parts = c.client_name.trim().split(' ');
          if (parts[1]) { initials += parts[1].substring(0, 1).toUpperCase(); }

          detailAvatar.textContent = initials;
          detailClientName.textContent = c.client_name;
          detailCompanyName.textContent = c.company_name ? c.company_name : 'Individual Client';

          const stClass = (c.status || 'new').toLowerCase();
          detailStatusBadge.className = `status-pill status-${stClass}`;
          detailStatusBadge.textContent = c.status;

          detailMetaDates.textContent = `Client Added: ${c.created_at} | Last Updated: ${c.updated_at}`;

          // Construct Profile HTML
          let html = `
            <div class="profile-grid">
              <!-- Card 1: Client & Contact Info -->
              <div class="profile-card">
                <div class="profile-card-title">
                  <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                  </svg>
                  <span>Contact Information</span>
                </div>
                <div class="profile-field">
                  <span class="profile-label">Primary Email</span>
                  <span class="profile-val"><a href="mailto:${encodeURIComponent(c.email)}" style="color: var(--text-dark);">${escapeHtml(c.email)}</a></span>
                </div>
                <div class="profile-field">
                  <span class="profile-label">Primary Phone</span>
                  <span class="profile-val"><a href="tel:${encodeURIComponent(c.phone)}" style="color: var(--text-dark); font-family: 'DM Mono', monospace;">${escapeHtml(c.phone)}</a></span>
                </div>
                <div class="profile-field">
                  <span class="profile-label">Alternate Phone</span>
                  <span class="profile-val" style="font-family: 'DM Mono', monospace;">${escapeHtml(c.alternate_phone || '—')}</span>
                </div>
                <div class="profile-field">
                  <span class="profile-label">Service Line</span>
                  <span class="profile-val">${escapeHtml(c.service || 'General')}</span>
                </div>
                <div class="profile-field">
                  <span class="profile-label">Client Source</span>
                  <span class="profile-val">${escapeHtml(c.source || 'Direct')}</span>
                </div>
                <div class="profile-field">
                  <span class="profile-label">Assigned Lead</span>
                  <span class="profile-val">${escapeHtml(c.assigned_to || 'Unassigned')}</span>
                </div>
              </div>

              <!-- Card 2: Financial & Revenue Summary -->
              <div class="profile-card">
                <div class="profile-card-title">
                  <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  <span>Revenue &amp; Invoices</span>
                </div>
                <div class="profile-field">
                  <span class="profile-label">Total Invoiced</span>
                  <span class="profile-val" style="font-family: 'DM Mono', monospace;">₹${Number(rev.total).toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
                </div>
                <div class="profile-field">
                  <span class="profile-label">Total Settled (Paid)</span>
                  <span class="profile-val" style="color: #047857; font-family: 'DM Mono', monospace;">₹${Number(rev.paid).toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
                </div>
                <div class="profile-field">
                  <span class="profile-label">Pending Balance</span>
                  <span class="profile-val" style="color: #b45309; font-family: 'DM Mono', monospace;">₹${Number(rev.pending).toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
                </div>
                <div style="margin-top: 10px; font-size: 11px; color: var(--text-muted);">
                  <strong>Invoice Ledger:</strong>
                  ${rev.invoices.length === 0 ? '<div style="margin-top:4px;">No invoices billed yet.</div>' : ''}
                  ${rev.invoices.map(inv => `
                    <div style="display:flex; justify-content:space-between; padding:3px 0; border-bottom:1px dashed #e2e8f0;">
                      <span style="font-family: 'DM Mono', monospace;">${escapeHtml(inv.invoice_number)} (${escapeHtml(inv.status)})</span>
                      <strong style="font-family: 'DM Mono', monospace;">₹${Number(inv.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</strong>
                    </div>
                  `).join('')}
                </div>
              </div>
            </div>

            <!-- Notes Section -->
            <div class="profile-card" style="margin-bottom: 20px;">
              <div class="profile-card-title">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                <span>Relationship Notes &amp; Account Scope</span>
              </div>
              <div style="font-size: 13px; line-height: 1.6; color: var(--text-body); background: #ffffff; padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
                ${c.notes ? escapeHtml(c.notes).replace(/\n/g, '<br>') : '<em style="color:var(--text-muted);">No notes recorded for this client.</em>'}
              </div>
            </div>

            <div class="profile-grid">
              <!-- Call History -->
              <div class="profile-card">
                <div class="profile-card-title">
                  <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                  </svg>
                  <span>Call History (${calls.length})</span>
                </div>
                <div class="profile-timeline">
                  ${calls.length === 0 ? '<div style="font-size: 12px; color: var(--text-muted);">No recorded call history.</div>' : ''}
                  ${calls.map(call => `
                    <div class="profile-timeline-item">
                      <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                        <strong style="text-transform: capitalize;">${escapeHtml(call.type.replace('_', ' '))}</strong>
                        <span style="font-family: 'DM Mono', monospace; color: var(--text-muted); font-size: 11px;">${call.scheduled_at}</span>
                      </div>
                      <div style="color: var(--text-muted);">${escapeHtml(call.notes || 'Routine touchpoint')}</div>
                      ${call.outcome ? `<div style="color: var(--success); font-size: 11px; margin-top: 2px;">&check; ${escapeHtml(call.outcome)}</div>` : ''}
                    </div>
                  `).join('')}
                </div>
              </div>

              <!-- Lead History & Follow-ups -->
              <div class="profile-card">
                <div class="profile-card-title">
                  <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  <span>Upcoming Follow-ups (${followups.length})</span>
                </div>
                <div class="profile-timeline">
                  ${followups.length === 0 ? '<div style="font-size: 12px; color: var(--text-muted); margin-bottom: 14px;">No upcoming follow-ups scheduled.</div>' : ''}
                  ${followups.map(f => `
                    <div class="profile-timeline-item" style="border-left: 3px solid #f59e0b; padding-left: 8px;">
                      <div style="display: flex; justify-content: space-between;">
                        <strong style="color: #b45309;">${escapeHtml(f.type.replace('_', ' '))}</strong>
                        <span style="font-family: 'DM Mono', monospace; font-size: 11px;">${f.scheduled_at}</span>
                      </div>
                      <div style="font-size: 11.5px; margin-top: 2px;">${escapeHtml(f.notes || 'Follow-up pending')}</div>
                    </div>
                  `).join('')}
                </div>

                <div class="profile-card-title" style="margin-top: 16px;">
                  <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                  </svg>
                  <span>Original Inquiries (${leads.length})</span>
                </div>
                <div class="profile-timeline">
                  ${leads.length === 0 ? '<div style="font-size: 12px; color: var(--text-muted);">No original web leads linked.</div>' : ''}
                  ${leads.map(ld => `
                    <div class="profile-timeline-item">
                      <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--text-muted);">
                        <span>${ld.service_interested || 'General'}</span>
                        <span style="font-family: 'DM Mono', monospace;">${ld.created_at}</span>
                      </div>
                      <div style="margin-top: 2px; font-size: 12px;">${escapeHtml(ld.message)}</div>
                    </div>
                  `).join('')}
                </div>
              </div>
            </div>
          `;

          detailContent.innerHTML = html;
        })
        .catch(err => {
          console.error(err);
          detailContent.innerHTML = '<div class="admin-alert alert-error">Error retrieving client profile.</div>';
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

    // Bind edit and view button listeners
    function bindTableEvents() {
      // View buttons
      document.querySelectorAll('.btn-view-client').forEach(btn => {
        btn.onclick = () => {
          const id = btn.getAttribute('data-id');
          if (id) openClientDetail(id);
        };
      });

      // Edit buttons
      document.querySelectorAll('.btn-edit-client').forEach(btn => {
        btn.onclick = () => {
          const raw = btn.getAttribute('data-client');
          if (!raw) return;
          try {
            const client = JSON.parse(raw);
            document.getElementById('edit_client_id').value = client.id;
            document.getElementById('edit_client_name').value = client.client_name || '';
            document.getElementById('edit_company_name').value = client.company_name || '';
            document.getElementById('edit_email').value = client.email || '';
            document.getElementById('edit_phone').value = client.phone || '';
            document.getElementById('edit_alternate_phone').value = client.alternate_phone || '';
            document.getElementById('edit_service').value = client.service || '';
            document.getElementById('edit_source').value = client.source || '';
            document.getElementById('edit_status').value = client.status || 'New';
            document.getElementById('edit_assigned_to').value = client.assigned_to || '';
            document.getElementById('edit_notes').value = client.notes || '';

            editModal.classList.add('active');
          } catch (e) {
            console.error('Parse error:', e);
          }
        };
      });

      // Re-evaluate bulk selection state whenever table rows are re-rendered
      if (typeof updateClientBulkState === 'function') {
        updateClientBulkState();
      }
    }

    // -----------------------------------------------------------
    // Bulk Clients Selection Controller
    // -----------------------------------------------------------
    const selectAllClients = document.getElementById('selectAllClients');
    const bulkBar = document.getElementById('clientsBulkBar');
    const selectedCountBadge = document.getElementById('clientsSelectedCount');
    const deleteCountBtnText = document.getElementById('clientsDeleteCountBtnText');
    const btnBulkDelete = document.getElementById('btnBulkDeleteClients');
    const btnBulkCancel = document.getElementById('btnBulkCancelClients');
    const bulkInputs = document.getElementById('bulkDeleteClientsInputs');
    const bulkForm = document.getElementById('bulkDeleteClientsForm');

    function getClientCheckboxes() {
      return document.querySelectorAll('.client-select-checkbox');
    }

    function updateClientBulkState() {
      const checkboxes = getClientCheckboxes();
      const checkedBoxes = Array.from(checkboxes).filter(cb => cb.checked);
      const count = checkedBoxes.length;

      if (selectedCountBadge) selectedCountBadge.textContent = count;
      if (deleteCountBtnText) deleteCountBtnText.textContent = count;

      if (count > 0) {
        if (bulkBar) bulkBar.style.display = 'flex';
      } else {
        if (bulkBar) bulkBar.style.display = 'none';
      }

      // Master checkbox
      if (selectAllClients) {
        selectAllClients.checked = checkboxes.length > 0 && count === checkboxes.length;
        selectAllClients.indeterminate = count > 0 && count < checkboxes.length;
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

    function toggleAllClients(checked) {
      getClientCheckboxes().forEach(cb => {
        cb.checked = checked;
      });
      updateClientBulkState();
    }

    if (selectAllClients) {
      selectAllClients.addEventListener('change', function() {
        toggleAllClients(this.checked);
      });
    }

    document.addEventListener('change', function(e) {
      if (e.target && e.target.classList.contains('client-select-checkbox')) {
        updateClientBulkState();
      }
    });

    if (btnBulkCancel) {
      btnBulkCancel.addEventListener('click', function() {
        toggleAllClients(false);
      });
    }

    if (btnBulkDelete) {
      btnBulkDelete.addEventListener('click', function() {
        const checkedBoxes = Array.from(getClientCheckboxes()).filter(cb => cb.checked);
        const count = checkedBoxes.length;
        if (count === 0) return;

        const confirmMsg = 'Are you sure you want to permanently delete ' + count + ' selected client(s)? Any linked leads will be unlinked safely. This action cannot be undone.';
        const confirmTitle = 'Delete Selected Clients (' + count + ')';
        const confirmBtn = 'Yes, Delete ' + count + ' Clients';

        const doSubmit = () => {
          bulkInputs.innerHTML = '';
          checkedBoxes.forEach(cb => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'client_ids[]';
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

    // Initial binding
    bindTableEvents();
  });
  </script>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>
