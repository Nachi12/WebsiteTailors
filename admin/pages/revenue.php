<?php
/**
 * Website Tailors Admin — Phase 4: Revenue Management
 *
 * Internal revenue tracking system (Zero payment gateways / 100% manual ledger).
 * - Real database tracking from `revenue` table
 * - Top Metrics: Total Revenue, Paid Revenue, Pending Revenue, This Month, Last Month (in ₹)
 * - Table Columns: Client, Service, Amount, Payment Status, Payment Type, Payment Date, Actions
 * - Actions: View, Edit, Delete
 * - Add Revenue with strict numeric validation (amount is never stored as string)
 * - Edits automatically synchronize with dashboard totals
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) {
    define('WebsiteTailors_INIT', true);
}
require_once dirname(__DIR__) . '/includes/auth_guard.php';

$pageTitle = 'Revenue Management';
$breadcrumb = 'Business & Revenue';
$pdo = Database::getInstance()->getConnection();

$error = null;
$success = null;

$validPaymentStatuses = ['Pending', 'Partially Paid', 'Paid', 'Refunded'];
$validPaymentTypes    = ['UPI', 'Bank Transfer', 'Cash', 'Card', 'Other'];
$validServices        = ['Website', 'Software', 'AI + Automation', 'UI/UX', 'Other'];

// -----------------------------------------------------------------------------
// POST ACTIONS: Add Revenue, Edit Revenue, Delete Revenue
// -----------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } else {
        $action = sanitize_text($_POST['action'] ?? '');
        $revenueId = (int)($_POST['revenue_id'] ?? 0);

        // 1. ADD REVENUE
        if ($action === 'add_revenue') {
            $clientIdInput = (int)($_POST['client_id'] ?? 0);
            $clientNameInput = sanitize_text($_POST['client_name_custom'] ?? '');
            $serviceInput = sanitize_text($_POST['service'] ?? '');
            $amountRaw    = trim($_POST['amount'] ?? '');
            $typeInput    = sanitize_text($_POST['payment_type'] ?? '');
            $statusInput  = sanitize_text($_POST['payment_status'] ?? '');
            $dateInput    = trim($_POST['payment_date'] ?? '');
            $notesInput   = sanitize_text($_POST['notes'] ?? '');

            // Strict Numeric Validation
            if (!is_numeric($amountRaw) || (float)$amountRaw <= 0) {
                $error = 'Please enter a valid numeric amount greater than 0.';
            } elseif (!in_array($typeInput, $validPaymentTypes, true)) {
                $error = 'Please select a valid payment type.';
            } elseif (!in_array($statusInput, $validPaymentStatuses, true)) {
                $error = 'Please select a valid payment status.';
            } elseif (empty($dateInput)) {
                $error = 'Please provide a valid payment date.';
            } else {
                $amountNumeric = (float)$amountRaw; // Never stored as string
                $paymentDatetime = date('Y-m-d H:i:s', strtotime($dateInput));

                // Resolve client
                $finalClientId = null;
                $storedClientName = null;
                if ($clientIdInput > 0) {
                    $finalClientId = $clientIdInput;
                } elseif (!empty($clientNameInput)) {
                    // Check if existing client matches name; do NOT automatically create new client
                    $chk = $pdo->prepare("SELECT id FROM clients WHERE company_name = :name OR client_name = :name LIMIT 1");
                    $chk->execute([':name' => $clientNameInput]);
                    $existingId = $chk->fetchColumn();
                    if ($existingId) {
                        $finalClientId = (int)$existingId;
                    } else {
                        $finalClientId = null;
                        $storedClientName = $clientNameInput;
                    }
                }

                $selectedService = in_array($serviceInput, $validServices, true) ? $serviceInput : 'Website';

                try {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO revenue (client_id, client_name, lead_id, invoice_id, amount, payment_type, payment_status, payment_date, service, notes, created_at, updated_at)
                        VALUES (?, ?, NULL, NULL, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $nowStr = date('Y-m-d H:i:s');
                    $insertStmt->execute([
                        $finalClientId,
                        $storedClientName,
                        $amountNumeric,
                        $typeInput,
                        $statusInput,
                        $paymentDatetime,
                        $selectedService,
                        $notesInput,
                        $nowStr,
                        $nowStr
                    ]);

                    $success = "Revenue entry of ₹" . number_format($amountNumeric, 2) . " successfully recorded.";
                } catch (\Throwable $e) {
                    $error = 'Failed to add revenue: ' . $e->getMessage();
                }
            }
        }
        // 2. EDIT REVENUE
        elseif ($action === 'edit_revenue' && $revenueId > 0) {
            $serviceInput = sanitize_text($_POST['service'] ?? '');
            $amountRaw    = trim($_POST['amount'] ?? '');
            $typeInput    = sanitize_text($_POST['payment_type'] ?? '');
            $statusInput  = sanitize_text($_POST['payment_status'] ?? '');
            $dateInput    = trim($_POST['payment_date'] ?? '');
            $notesInput   = sanitize_text($_POST['notes'] ?? '');

            // Strict Numeric Validation
            if (!is_numeric($amountRaw) || (float)$amountRaw <= 0) {
                $error = 'Please enter a valid numeric amount greater than 0.';
            } elseif (!in_array($typeInput, $validPaymentTypes, true)) {
                $error = 'Please select a valid payment type.';
            } elseif (!in_array($statusInput, $validPaymentStatuses, true)) {
                $error = 'Please select a valid payment status.';
            } elseif (empty($dateInput)) {
                $error = 'Please provide a valid payment date.';
            } else {
                $amountNumeric = (float)$amountRaw;
                $paymentDatetime = date('Y-m-d H:i:s', strtotime($dateInput));
                $selectedService = in_array($serviceInput, $validServices, true) ? $serviceInput : 'Website';

                try {
                    $updateStmt = $pdo->prepare("
                        UPDATE revenue
                        SET amount = :amount,
                            payment_type = :payment_type,
                            payment_status = :payment_status,
                            payment_date = :payment_date,
                            service = :service,
                            notes = :notes,
                            updated_at = :updated_at
                        WHERE id = :id
                    ");
                    $updateStmt->execute([
                        ':amount'         => $amountNumeric,
                        ':payment_type'   => $typeInput,
                        ':payment_status' => $statusInput,
                        ':payment_date'   => $paymentDatetime,
                        ':service'        => $selectedService,
                        ':notes'          => $notesInput,
                        ':updated_at'     => date('Y-m-d H:i:s'),
                        ':id'             => $revenueId
                    ]);

                    // Sync linked invoice if present
                    $linkedInvId = (int)$pdo->query("SELECT invoice_id FROM revenue WHERE id = {$revenueId}")->fetchColumn();
                    if ($linkedInvId > 0) {
                        $totPaid = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM revenue WHERE invoice_id = {$linkedInvId} AND LOWER(payment_status) = 'paid'")->fetchColumn();
                        $invRow = $pdo->query("SELECT amount, project_id, project_total, due_date, status FROM invoices WHERE id = {$linkedInvId}")->fetch(PDO::FETCH_ASSOC);
                        if ($invRow) {
                            $invAmt = (float)$invRow['amount'];
                            $projId = !empty($invRow['project_id']) ? (int)$invRow['project_id'] : null;
                            $projTot = (float)($invRow['project_total'] ?? 0);
                            $dueFmt = date('Y-m-d', strtotime((string)$invRow['due_date']));

                            if (!in_array(strtolower((string)$invRow['status']), ['cancelled', 'refunded'], true)) {
                                if ($totPaid >= $invAmt) {
                                    $newSt = 'paid';
                                } elseif ($totPaid > 0) {
                                    $newSt = 'partially paid';
                                } else {
                                    $newSt = (strtotime($dueFmt) < strtotime(date('Y-m-d'))) ? 'overdue' : 'pending';
                                }
                            } else {
                                $newSt = $invRow['status'];
                            }

                            if ($projId && $projTot > 0) {
                                $pPaid = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM revenue WHERE project_id = {$projId} AND LOWER(payment_status) = 'paid'")->fetchColumn();
                                $newBal = max(0.00, round($projTot - $pPaid, 2));
                                $pdo->prepare("UPDATE invoices SET balance_amount = :bal WHERE project_id = :pid AND project_total > 0")->execute([':bal' => $newBal, ':pid' => $projId]);
                            } else {
                                $newBal = max(0.00, round($invAmt - $totPaid, 2));
                            }

                            $pdo->prepare("UPDATE invoices SET amount_received = :paid, balance_amount = :bal, status = :st, updated_at = :upd WHERE id = :id")->execute([
                                ':paid' => $totPaid,
                                ':bal'  => $newBal,
                                ':st'   => $newSt,
                                ':upd'  => date('Y-m-d H:i:s'),
                                ':id'   => $linkedInvId
                            ]);
                        }
                    }

                    $success = "Revenue record #{$revenueId} successfully updated. Dashboard totals synchronized.";
                } catch (\Throwable $e) {
                    $error = 'Failed to update revenue: ' . $e->getMessage();
                }
            }
        }
        // 3. DELETE REVENUE
        elseif ($action === 'delete_revenue' && $revenueId > 0) {
            try {
                $linkedInvId = (int)$pdo->query("SELECT invoice_id FROM revenue WHERE id = {$revenueId}")->fetchColumn();
                $delStmt = $pdo->prepare("DELETE FROM revenue WHERE id = :id");
                $delStmt->execute([':id' => $revenueId]);

                if ($linkedInvId > 0) {
                    $totPaid = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM revenue WHERE invoice_id = {$linkedInvId} AND LOWER(payment_status) = 'paid'")->fetchColumn();
                    $invRow = $pdo->query("SELECT amount, project_id, project_total, due_date, status FROM invoices WHERE id = {$linkedInvId}")->fetch(PDO::FETCH_ASSOC);
                    if ($invRow) {
                        $invAmt = (float)$invRow['amount'];
                        $projId = !empty($invRow['project_id']) ? (int)$invRow['project_id'] : null;
                        $projTot = (float)($invRow['project_total'] ?? 0);
                        $dueFmt = date('Y-m-d', strtotime((string)$invRow['due_date']));

                        if (!in_array(strtolower((string)$invRow['status']), ['cancelled', 'refunded'], true)) {
                            if ($totPaid >= $invAmt) {
                                $newSt = 'paid';
                            } elseif ($totPaid > 0) {
                                $newSt = 'partially paid';
                            } else {
                                $newSt = (strtotime($dueFmt) < strtotime(date('Y-m-d'))) ? 'overdue' : 'pending';
                            }
                        } else {
                            $newSt = $invRow['status'];
                        }

                        if ($projId && $projTot > 0) {
                            $pPaid = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM revenue WHERE project_id = {$projId} AND LOWER(payment_status) = 'paid'")->fetchColumn();
                            $newBal = max(0.00, round($projTot - $pPaid, 2));
                            $pdo->prepare("UPDATE invoices SET balance_amount = :bal WHERE project_id = :pid AND project_total > 0")->execute([':bal' => $newBal, ':pid' => $projId]);
                        } else {
                            $newBal = max(0.00, round($invAmt - $totPaid, 2));
                        }

                        $pdo->prepare("UPDATE invoices SET amount_received = :paid, balance_amount = :bal, status = :st, updated_at = :upd WHERE id = :id")->execute([
                            ':paid' => $totPaid,
                            ':bal'  => $newBal,
                            ':st'   => $newSt,
                            ':upd'  => date('Y-m-d H:i:s'),
                            ':id'   => $linkedInvId
                        ]);
                    }
                }

                $success = "Revenue record #{$revenueId} has been deleted. Dashboard totals synchronized.";
            } catch (\Throwable $e) {
                $error = 'Failed to delete revenue: ' . $e->getMessage();
            }
        }
    }
}

// -----------------------------------------------------------------------------
// TOP 5 METRIC CARDS (Real Database Calculations in ₹)
// -----------------------------------------------------------------------------
$currentMonthStart = date('Y-m-01 00:00:00');
$currentMonthEnd   = date('Y-m-t 23:59:59');
$prevMonthStart    = date('Y-m-01 00:00:00', strtotime('first day of last month'));
$prevMonthEnd      = date('Y-m-t 23:59:59', strtotime('last day of last month'));

// 1. Total Revenue (non-refunded)
$totalRev = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM revenue WHERE LOWER(payment_status) != 'refunded'")->fetchColumn();

// 2. Paid Revenue
$paidRev = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM revenue WHERE LOWER(payment_status) = 'paid'")->fetchColumn();

// 3. Pending Revenue
$pendingRev = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM revenue WHERE LOWER(payment_status) = 'pending'")->fetchColumn();

// 4. This Month (Paid in current month)
$thisMonthRev = (float)$pdo->query("
    SELECT COALESCE(SUM(amount), 0) FROM revenue 
    WHERE LOWER(payment_status) = 'paid' AND payment_date >= '{$currentMonthStart}' AND payment_date <= '{$currentMonthEnd}'
")->fetchColumn();

// 5. Last Month (Paid in previous month)
$lastMonthRev = (float)$pdo->query("
    SELECT COALESCE(SUM(amount), 0) FROM revenue 
    WHERE LOWER(payment_status) = 'paid' AND payment_date >= '{$prevMonthStart}' AND payment_date <= '{$prevMonthEnd}'
")->fetchColumn();

// -----------------------------------------------------------------------------
// REVENUE TABLE QUERY & FILTERS
// -----------------------------------------------------------------------------
$statusFilter = sanitize_text($_GET['status'] ?? 'all');
$serviceFilter = sanitize_text($_GET['service'] ?? 'all');
$typeFilter = sanitize_text($_GET['type'] ?? 'all');
$searchQuery = trim(sanitize_text($_GET['search'] ?? $_GET['q'] ?? ''));
$dateFrom = trim(sanitize_text($_GET['date_from'] ?? ''));
$dateTo = trim(sanitize_text($_GET['date_to'] ?? ''));

$whereClauses = [];
$params = [];

if ($statusFilter !== 'all') {
    $whereClauses[] = "LOWER(r.payment_status) = :status";
    $params[':status'] = strtolower($statusFilter);
}

if ($serviceFilter !== 'all') {
    $whereClauses[] = "LOWER(r.service) = :service";
    $params[':service'] = strtolower($serviceFilter);
}

if ($typeFilter !== 'all') {
    $whereClauses[] = "LOWER(r.payment_type) = :ptype";
    $params[':ptype'] = strtolower($typeFilter);
}

if (!empty($dateFrom)) {
    $whereClauses[] = "r.payment_date >= :date_from";
    $params[':date_from'] = $dateFrom . ' 00:00:00';
}

if (!empty($dateTo)) {
    $whereClauses[] = "r.payment_date <= :date_to";
    $params[':date_to'] = $dateTo . ' 23:59:59';
}

if (!empty($searchQuery)) {
    $whereClauses[] = "(c.client_name LIKE :q OR c.company_name LIKE :q OR inv.client_name LIKE :q OR r.client_name LIKE :q OR r.service LIKE :q OR r.notes LIKE :q)";
    $params[':q'] = '%' . $searchQuery . '%';
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$countSql = "
    SELECT COUNT(*) 
    FROM revenue r
    LEFT JOIN clients c ON r.client_id = c.id
    LEFT JOIN invoices inv ON r.invoice_id = inv.id
    {$whereSql}
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $perPage));

$listSql = "
    SELECT r.id, r.client_id, r.amount, r.payment_type, r.payment_status,
           r.payment_date, r.service, r.notes, r.created_at,
           COALESCE(c.company_name, c.client_name, inv.client_name, r.client_name, 'Direct Client') as client_name,
           c.phone as client_phone
    FROM revenue r
    LEFT JOIN clients c ON r.client_id = c.id
    LEFT JOIN invoices inv ON r.invoice_id = inv.id
    {$whereSql}
    ORDER BY r.payment_date DESC, r.id DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$revenueList = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch clients list for Add Revenue modal dropdown
$clientsOptions = $pdo->query("SELECT id, client_name, company_name FROM clients ORDER BY client_name ASC")->fetchAll(PDO::FETCH_ASSOC);

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

  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h1 class="page-title">Revenue Management</h1>
      <p class="page-subtitle">Internal financial ledger for client billings, payments, cash flow, and manual collections.</p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
      <button type="button" class="btn-primary-admin" onclick="openAddModal()" style="padding: 9px 16px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
        <span style="font-size: 16px; line-height: 1;">+</span> Record Revenue
      </button>
    </div>
  </div>

  <!-- =========================================================
       TOP 5 REVENUE METRIC CARDS (₹)
       Total Revenue, Paid Revenue, Pending Revenue, This Month, Last Month
  ========================================================= -->
  <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 28px;">
    <!-- 1. Total Revenue -->
    <div class="kpi-card" style="padding: 20px;">
      <span class="kpi-label" style="font-size: 11px;">Total Revenue</span>
      <div class="kpi-value" style="font-size: 26px; margin-top: 8px;">₹<?= number_format($totalRev, 2) ?></div>
      <div class="kpi-footer" style="margin-top: 8px; font-size: 11px;">All recorded billings</div>
    </div>

    <!-- 2. Paid Revenue -->
    <div class="kpi-card" style="padding: 20px; border-left: 3px solid #10b981;">
      <span class="kpi-label" style="font-size: 11px;">Paid Revenue</span>
      <div class="kpi-value" style="font-size: 26px; color: #047857; margin-top: 8px;">₹<?= number_format($paidRev, 2) ?></div>
      <div class="kpi-footer" style="margin-top: 8px; font-size: 11px;">Settled collections</div>
    </div>

    <!-- 3. Pending Revenue -->
    <div class="kpi-card" style="padding: 20px; border-left: 3px solid #f59e0b;">
      <span class="kpi-label" style="font-size: 11px;">Pending Revenue</span>
      <div class="kpi-value" style="font-size: 26px; color: #b45309; margin-top: 8px;">₹<?= number_format($pendingRev, 2) ?></div>
      <div class="kpi-footer" style="margin-top: 8px; font-size: 11px;">Awaiting payment</div>
    </div>

    <!-- 4. This Month -->
    <div class="kpi-card" style="padding: 20px;">
      <span class="kpi-label" style="font-size: 11px;">This Month</span>
      <div class="kpi-value" style="font-size: 26px; color: #12141a; margin-top: 8px;">₹<?= number_format($thisMonthRev, 2) ?></div>
      <div class="kpi-footer" style="margin-top: 8px; font-size: 11px;"><?= date('F Y') ?></div>
    </div>

    <!-- 5. Last Month -->
    <div class="kpi-card" style="padding: 20px;">
      <span class="kpi-label" style="font-size: 11px;">Last Month</span>
      <div class="kpi-value" style="font-size: 26px; color: var(--text-muted); margin-top: 8px;">₹<?= number_format($lastMonthRev, 2) ?></div>
      <div class="kpi-footer" style="margin-top: 8px; font-size: 11px;"><?= date('F Y', strtotime('last month')) ?></div>
    </div>
  </div>

  <!-- =========================================================
       TOOLBAR & FILTERS
  ========================================================= -->
  <div class="filter-bar" style="margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 16px; justify-content: space-between; align-items: center;">
    <div class="filter-pills" style="display: flex; flex-wrap: wrap; gap: 6px;">
      <a href="?status=all<?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?><?= $serviceFilter !== 'all' ? '&service=' . urlencode($serviceFilter) : '' ?>"
         class="filter-pill <?= $statusFilter === 'all' ? 'active' : '' ?>">
        All Statuses
      </a>
      <a href="?status=Paid<?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?><?= $serviceFilter !== 'all' ? '&service=' . urlencode($serviceFilter) : '' ?>"
         class="filter-pill <?= strtolower($statusFilter) === 'paid' ? 'active' : '' ?>">
        Paid
      </a>
      <a href="?status=Pending<?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?><?= $serviceFilter !== 'all' ? '&service=' . urlencode($serviceFilter) : '' ?>"
         class="filter-pill <?= strtolower($statusFilter) === 'pending' ? 'active' : '' ?>">
        Pending
      </a>
      <a href="?status=Partially+Paid<?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?><?= $serviceFilter !== 'all' ? '&service=' . urlencode($serviceFilter) : '' ?>"
         class="filter-pill <?= strtolower($statusFilter) === 'partially paid' ? 'active' : '' ?>">
        Partially Paid
      </a>
      <a href="?status=Refunded<?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?><?= $serviceFilter !== 'all' ? '&service=' . urlencode($serviceFilter) : '' ?>"
         class="filter-pill <?= strtolower($statusFilter) === 'refunded' ? 'active' : '' ?>">
        Refunded
      </a>
    </div>

    <!-- Service & Search Form -->
    <form method="GET" action="revenue.php" style="display: flex; gap: 10px; align-items: center;">
      <input type="hidden" name="status" value="<?= e($statusFilter) ?>">

      <select name="service" class="filter-select" onchange="this.form.submit()" style="padding: 7px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; background: #ffffff;">
        <option value="all" <?= $serviceFilter === 'all' ? 'selected' : '' ?>>All Services</option>
        <?php foreach ($validServices as $svc): ?>
          <option value="<?= e($svc) ?>" <?= strcasecmp($serviceFilter, $svc) === 0 ? 'selected' : '' ?>><?= e($svc) ?></option>
        <?php endforeach; ?>
      </select>

      <select name="type" class="filter-select" onchange="this.form.submit()" style="padding: 7px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; background: #ffffff;">
        <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>All Payment Types</option>
        <?php foreach ($validPaymentTypes as $pt): ?>
          <option value="<?= e($pt) ?>" <?= strcasecmp($typeFilter, $pt) === 0 ? 'selected' : '' ?>><?= e($pt) ?></option>
        <?php endforeach; ?>
      </select>

      <input type="text" name="q" value="<?= e($searchQuery) ?>" placeholder="Search client or notes..." class="search-input" style="padding: 7px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; width: 200px;">
      <button type="submit" class="btn-action" style="padding: 7px 12px;">Search</button>
      <?php if (!empty($searchQuery) || $statusFilter !== 'all' || $serviceFilter !== 'all' || $typeFilter !== 'all'): ?>
        <a href="revenue.php" class="btn-action" style="padding: 7px 10px; background: #ffffff;">✕</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- =========================================================
       REVENUE TABLE
       Columns: Client, Service, Amount, Payment Status, Payment Type, Payment Date, Actions
  ========================================================= -->
  <div class="data-card">
    <div class="table-responsive">
      <?php if (empty($revenueList)): ?>
        <div class="empty-state-banner">
          <div class="empty-state-icon">₹</div>
          <div class="empty-state-title">No revenue recorded yet.</div>
          <p>Click "Record Revenue" above to record business earnings.</p>
        </div>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Client</th>
              <th>Service</th>
              <th>Amount</th>
              <th>Payment Status</th>
              <th>Payment Type</th>
              <th>Payment Date</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($revenueList as $rev): ?>
              <?php
                $statusNorm = strtolower(trim($rev['payment_status']));
                $statusClass = match ($statusNorm) {
                    'paid'           => 'status-completed',
                    'pending'        => 'status-scheduled',
                    'partially paid' => 'status-contacted',
                    'refunded'       => 'status-archived',
                    default          => 'status-default'
                };
              ?>
              <tr>
                <!-- 1. Client -->
                <td>
                  <strong><?= e($rev['client_name']) ?></strong>
                  <?php if (!empty($rev['client_phone'])): ?>
                    <div style="font-family: 'DM Mono', monospace; font-size: 11px; color: var(--text-light);"><?= e($rev['client_phone']) ?></div>
                  <?php endif; ?>
                </td>

                <!-- 2. Service -->
                <td>
                  <span style="font-family: 'DM Mono', monospace; font-size: 11px; background: var(--main-bg); padding: 3px 8px; border-radius: 4px;">
                    <?= e($rev['service']) ?>
                  </span>
                </td>

                <!-- 3. Amount (₹) -->
                <td>
                  <strong style="font-family: 'Space Grotesk', sans-serif; font-size: 14px; <?= $statusNorm === 'paid' ? 'color: #047857;' : 'color: var(--text-dark);' ?>">
                    ₹<?= number_format((float)$rev['amount'], 2) ?>
                  </strong>
                </td>

                <!-- 4. Payment Status -->
                <td>
                  <span class="status-pill <?= $statusClass ?>">
                    <?= e($rev['payment_status']) ?>
                  </span>
                </td>

                <!-- 5. Payment Type -->
                <td>
                  <span style="font-family: 'DM Mono', monospace; font-size: 11px; color: var(--text-muted);">
                    <?= e($rev['payment_type']) ?>
                  </span>
                </td>

                <!-- 6. Payment Date -->
                <td style="font-family: 'DM Mono', monospace; font-size: 11px; color: var(--text-muted);">
                  <?= e(format_date($rev['payment_date'], 'M j, Y')) ?>
                </td>

                <!-- 7. Actions: View, Edit, Delete -->
                <td style="text-align: right; white-space: nowrap;">
                  <!-- View -->
                  <button type="button"
                          class="btn-action"
                          style="padding: 4px 8px; font-size: 11px;"
                          onclick='openViewModal(<?= json_encode($rev, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                          title="View Revenue Entry">
                    View
                  </button>

                  <!-- Edit -->
                  <button type="button"
                          class="btn-action"
                          style="padding: 4px 8px; font-size: 11px; background: #ffffff;"
                          onclick='openEditModal(<?= json_encode($rev, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                          title="Edit Revenue Entry">
                    Edit
                  </button>

                  <!-- Delete -->
                  <button type="button"
                          onclick="confirmDeleteRevenue(<?= (int)$rev['id'] ?>, '₹<?= number_format((float)$rev['amount'], 2) ?>')"
                          style="color: var(--text-muted); padding: 4px 6px; font-size: 12px; cursor: pointer;"
                          title="Delete Revenue Entry">
                    ✕
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <div class="pagination-bar">
        <div class="pagination-info">
          Showing <?= $offset + 1 ?> to <?= min($totalRecords, $offset + $perPage) ?> of <?= $totalRecords ?> revenue entries
        </div>
        <div class="pagination-links">
          <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?status=<?= e($statusFilter) ?>&service=<?= e($serviceFilter) ?>&page=<?= $p ?>&q=<?= urlencode($searchQuery) ?>"
               class="page-btn <?= $p === $page ? 'active' : '' ?>">
              <?= $p ?>
            </a>
          <?php endfor; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- =========================================================
       MODAL 1: ADD REVENUE MODAL
       Fields: Client, Service, Amount, Payment Type, Payment Status, Payment Date, Notes
  ========================================================= -->
  <div id="addModal" class="modal-overlay" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-header">
        <div class="modal-title">Record Business Revenue</div>
        <button type="button" class="modal-close-btn" onclick="closeAddModal()">✕</button>
      </div>

      <form method="POST" action="revenue.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_revenue">

        <div class="modal-body">
          <!-- Client Selector or Custom Name -->
          <div class="form-group" style="margin-bottom: 14px;">
            <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">
              Client Account *
            </label>
            <select name="client_id" id="addClientId" onchange="toggleCustomClientInput(this.value)" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; background: #ffffff;">
              <option value="0">-- Select Existing Client or Enter New --</option>
              <?php foreach ($clientsOptions as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= e($c['company_name'] ?: $c['client_name']) ?></option>
              <?php endforeach; ?>
              <option value="-1">+ Enter New Client / Brand Name</option>
            </select>
          </div>

          <div class="form-group" id="customClientGroup" style="display: none; margin-bottom: 14px;">
            <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">
              New Client Name *
            </label>
            <input type="text" name="client_name_custom" id="addClientCustom" placeholder="e.g. Acme Corp" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px;">
          </div>

          <!-- Service & Amount Grid -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">
                Service Category *
              </label>
              <select name="service" required class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; background: #ffffff;">
                <?php foreach ($validServices as $svc): ?>
                  <option value="<?= e($svc) ?>"><?= e($svc) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Amount (Strict Numeric Validation) -->
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">
                Amount (₹) *
              </label>
              <input type="number" step="0.01" min="0.01" name="amount" required placeholder="50000.00" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace; font-size: 12px;">
            </div>
          </div>

          <!-- Payment Type & Payment Status Grid -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">
                Payment Type *
              </label>
              <select name="payment_type" required class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; background: #ffffff;">
                <?php foreach ($validPaymentTypes as $pt): ?>
                  <option value="<?= e($pt) ?>" <?= $pt === 'Bank Transfer' ? 'selected' : '' ?>><?= e($pt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">
                Payment Status *
              </label>
              <select name="payment_status" required class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; background: #ffffff;">
                <?php foreach ($validPaymentStatuses as $ps): ?>
                  <option value="<?= e($ps) ?>" <?= $ps === 'Paid' ? 'selected' : '' ?>><?= e($ps) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Payment Date -->
          <div class="form-group" style="margin-bottom: 14px;">
            <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">
              Payment Date *
            </label>
            <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace; font-size: 12px;">
          </div>

          <!-- Notes -->
          <div class="form-group" style="margin-bottom: 6px;">
            <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">
              Notes / Reference (Optional)
            </label>
            <textarea name="notes" rows="2" placeholder="e.g. Milestone 1 sign-off, invoice ref INV-2026-015" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; line-height: 1.5; resize: vertical;"></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-action" style="background: var(--main-bg);" onclick="closeAddModal()">Cancel</button>
          <button type="submit" class="btn-primary-admin" style="padding: 8px 18px; font-size: 13px;">Save Revenue</button>
        </div>
      </form>
    </div>
  </div>

  <!-- =========================================================
       MODAL 2: EDIT REVENUE MODAL
  ========================================================= -->
  <div id="editModal" class="modal-overlay" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-header">
        <div>
          <div class="modal-title">Edit Revenue Entry</div>
          <div id="editSubtitle" style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"></div>
        </div>
        <button type="button" class="modal-close-btn" onclick="closeEditModal()">✕</button>
      </div>

      <form method="POST" action="revenue.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="edit_revenue">
        <input type="hidden" name="revenue_id" id="editRevenueId" value="0">

        <div class="modal-body">
          <!-- Service & Amount Grid -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">
                Service Category *
              </label>
              <select name="service" id="editService" required class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; background: #ffffff;">
                <?php foreach ($validServices as $svc): ?>
                  <option value="<?= e($svc) ?>"><?= e($svc) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">
                Amount (₹) *
              </label>
              <input type="number" step="0.01" min="0.01" name="amount" id="editAmount" required class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace; font-size: 12px;">
            </div>
          </div>

          <!-- Payment Type & Payment Status Grid -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">
                Payment Type *
              </label>
              <select name="payment_type" id="editPaymentType" required class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; background: #ffffff;">
                <?php foreach ($validPaymentTypes as $pt): ?>
                  <option value="<?= e($pt) ?>"><?= e($pt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">
                Payment Status *
              </label>
              <select name="payment_status" id="editPaymentStatus" required class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; background: #ffffff;">
                <?php foreach ($validPaymentStatuses as $ps): ?>
                  <option value="<?= e($ps) ?>"><?= e($ps) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Payment Date -->
          <div class="form-group" style="margin-bottom: 14px;">
            <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">
              Payment Date *
            </label>
            <input type="date" name="payment_date" id="editPaymentDate" required class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace; font-size: 12px;">
          </div>

          <!-- Notes -->
          <div class="form-group" style="margin-bottom: 6px;">
            <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">
              Notes / Reference
            </label>
            <textarea name="notes" id="editNotes" rows="2" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; line-height: 1.5; resize: vertical;"></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-action" style="background: var(--main-bg);" onclick="closeEditModal()">Cancel</button>
          <button type="submit" class="btn-primary-admin" style="padding: 8px 18px; font-size: 13px;">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <!-- =========================================================
       MODAL 3: VIEW REVENUE MODAL
  ========================================================= -->
  <div id="viewModal" class="modal-overlay" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-header">
        <div>
          <div class="modal-title">Revenue Entry Details</div>
          <div id="viewSubtitle" style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"></div>
        </div>
        <button type="button" class="modal-close-btn" onclick="closeViewModal()">✕</button>
      </div>

      <div class="modal-body" style="font-size: 13px;">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
          <div>
            <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase;">Client</span>
            <div id="viewClientName" style="font-weight: 700; color: var(--text-dark); font-size: 14px;"></div>
          </div>
          <div>
            <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase;">Amount</span>
            <div id="viewAmount" style="font-family: 'Space Grotesk', sans-serif; font-size: 20px; font-weight: 700; color: #047857;"></div>
          </div>
          <div>
            <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase;">Service</span>
            <div id="viewService" style="font-family: 'DM Mono', monospace;"></div>
          </div>
          <div>
            <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase;">Payment Type</span>
            <div id="viewPaymentType"></div>
          </div>
          <div>
            <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase;">Payment Status</span>
            <div id="viewPaymentStatus"></div>
          </div>
          <div>
            <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase;">Payment Date</span>
            <div id="viewPaymentDate" style="font-family: 'DM Mono', monospace;"></div>
          </div>
        </div>

        <div>
          <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase;">Notes</span>
          <div id="viewNotes" style="background: var(--main-bg); padding: 12px; border-radius: 8px; border: 1px solid var(--border-light); margin-top: 6px; line-height: 1.5; white-space: pre-wrap;"></div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-action" style="background: var(--main-bg);" onclick="closeViewModal()">Close</button>
      </div>
    </div>
  </div>

  <!-- Delete Form -->
  <form id="deleteRevenueForm" method="POST" action="revenue.php" style="display: none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_revenue">
    <input type="hidden" name="revenue_id" id="deleteRevenueId" value="0">
  </form>

  <script>
  function toggleCustomClientInput(val) {
    const customGrp = document.getElementById('customClientGroup');
    const customInp = document.getElementById('addClientCustom');
    if (val === '-1') {
      customGrp.style.display = 'block';
      customInp.required = true;
      customInp.focus();
    } else {
      customGrp.style.display = 'none';
      customInp.required = false;
    }
  }

  function openAddModal() {
    document.getElementById('addClientId').value = '0';
    toggleCustomClientInput('0');
    const modal = document.getElementById('addModal');
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeAddModal() {
    const modal = document.getElementById('addModal');
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
  }

  function openViewModal(rev) {
    document.getElementById('viewSubtitle').textContent = '#' + rev.id;
    document.getElementById('viewClientName').textContent = rev.client_name;
    document.getElementById('viewAmount').textContent = '₹' + Number(rev.amount).toLocaleString('en-IN', { minimumFractionDigits: 2 });
    document.getElementById('viewService').textContent = rev.service;
    document.getElementById('viewPaymentType').textContent = rev.payment_type;
    document.getElementById('viewPaymentStatus').textContent = rev.payment_status;
    document.getElementById('viewPaymentDate').textContent = rev.payment_date;
    document.getElementById('viewNotes').textContent = rev.notes ? rev.notes : 'No notes recorded.';

    const modal = document.getElementById('viewModal');
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeViewModal() {
    const modal = document.getElementById('viewModal');
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
  }

  function openEditModal(rev) {
    document.getElementById('editRevenueId').value = rev.id;
    document.getElementById('editSubtitle').textContent = '#' + rev.id + ' — ' + rev.client_name;
    document.getElementById('editService').value = rev.service;
    document.getElementById('editAmount').value = Number(rev.amount).toFixed(2);
    document.getElementById('editPaymentType').value = rev.payment_type;
    document.getElementById('editPaymentStatus').value = rev.payment_status;

    // Date
    const d = new Date(rev.payment_date);
    if (!isNaN(d.getTime())) {
      const pad = n => String(n).padStart(2, '0');
      document.getElementById('editPaymentDate').value = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    } else {
      document.getElementById('editPaymentDate').value = '';
    }

    document.getElementById('editNotes').value = rev.notes || '';

    const modal = document.getElementById('editModal');
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeEditModal() {
    const modal = document.getElementById('editModal');
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
  }

  function confirmDeleteRevenue(id, formattedAmt) {
    if (window.AdminModal && typeof window.AdminModal.confirm === 'function') {
      window.AdminModal.confirm({
        title: 'Delete Revenue Record',
        message: 'Are you sure you want to delete revenue record #' + id + ' (' + formattedAmt + ')? This will update dashboard totals immediately.',
        confirmText: 'Delete Record',
        isDanger: true,
        onConfirm: () => {
          document.getElementById('deleteRevenueId').value = id;
          document.getElementById('deleteRevenueForm').submit();
        }
      });
    } else {
      document.getElementById('deleteRevenueId').value = id;
      document.getElementById('deleteRevenueForm').submit();
    }
  }

  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
      if (e.target === this) {
        this.classList.remove('active');
        this.setAttribute('aria-hidden', 'true');
      }
    });
  });
  </script>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>
