<?php
/**
 * Website Tailors Admin — Phase 5: Call Management + Call Analytics
 *
 * 100% database-driven call management, visual analytics, category filters,
 * and complete record management (View, Edit, Delete):
 * - Top Dashboard Cards: Calls Today, Calls This Week, Pending Follow-ups, Conversions
 * - Visual Call Analytics: Calls per day, Calls per week, Calls by outcome
 * - Filters: Today's Calls, Upcoming Follow-ups, Completed Calls, Missed/No Answer, Call Back Required
 * - Table columns: Client, Phone, Date, Time, Outcome, Next Follow-up, Actions
 * - Full CRUD: View, Edit (date, time, outcome, notes, next follow-up), Delete
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) {
    define('WebsiteTailors_INIT', true);
}
require_once dirname(__DIR__) . '/includes/auth_guard.php';

$pageTitle = 'Call Management & Analytics';
$breadcrumb = 'Calls & CRM';
$pdo = Database::getInstance()->getConnection();

$error = null;
$success = null;

$validOutcomes = ['Connected', 'No Answer', 'Call Back', 'Not Interested', 'Converted'];

// -----------------------------------------------------------------------------
// POST ACTIONS: Edit Call, Delete Call
// -----------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } else {
        $action = sanitize_text($_POST['action'] ?? '');
        $callId = (int)($_POST['call_id'] ?? 0);

        // 1. EDIT CALL ACTION
        if ($action === 'edit_call' && $callId > 0) {
            $dateInput     = trim($_POST['call_date'] ?? '');
            $timeInput     = trim($_POST['call_time'] ?? '');
            $outcomeInput  = sanitize_text($_POST['outcome'] ?? '');
            $notesInput    = sanitize_text($_POST['notes'] ?? '');
            $followupDate  = trim($_POST['next_followup_date'] ?? '');
            $followupTime  = trim($_POST['next_followup_time'] ?? '');
            if (empty($followupDate) && !empty($_POST['next_followup_at'])) {
                $fuTimestamp = strtotime((string)$_POST['next_followup_at']);
                if ($fuTimestamp !== false) {
                    $followupDate = date('Y-m-d', $fuTimestamp);
                    $followupTime = date('H:i', $fuTimestamp);
                }
            }

            if (empty($dateInput)) {
                $error = 'Please provide a valid call date.';
            } else {
                $timeFormatted = !empty($timeInput) ? $timeInput . ':00' : '10:00:00';
                $callDatetime = date('Y-m-d H:i:s', strtotime($dateInput . ' ' . $timeFormatted));

                $nextFollowupAt = null;
                if (!empty($followupDate)) {
                    $fuTimeFormatted = !empty($followupTime) ? $followupTime . ':00' : '10:00:00';
                    $nextFollowupAt = date('Y-m-d H:i:s', strtotime($followupDate . ' ' . $fuTimeFormatted));
                }

                $statusVal = (!empty($outcomeInput) && in_array($outcomeInput, $validOutcomes, true)) ? 'completed' : 'scheduled';

                try {
                    // Update call record
                    $updateStmt = $pdo->prepare("
                        UPDATE calls
                        SET call_datetime = :call_datetime,
                            scheduled_at = :scheduled_at,
                            outcome = :outcome,
                            status = :status,
                            notes = :notes,
                            next_followup_at = :next_followup_at
                        WHERE id = :id
                    ");
                    $updateStmt->execute([
                        ':call_datetime'    => $callDatetime,
                        ':scheduled_at'     => $callDatetime,
                        ':outcome'          => !empty($outcomeInput) ? $outcomeInput : null,
                        ':status'           => $statusVal,
                        ':notes'            => $notesInput,
                        ':next_followup_at' => $nextFollowupAt,
                        ':id'               => $callId
                    ]);

                    // If call is linked to a lead, update lead synchronization
                    $leadId = (int)$pdo->query("SELECT lead_id FROM calls WHERE id = {$callId}")->fetchColumn();
                    if ($leadId > 0 && !empty($outcomeInput)) {
                        $mappedLeadCallStatus = match ($outcomeInput) {
                            'Connected'       => 'Called',
                            'No Answer'      => 'No Answer',
                            'Call Back'      => 'Call Back',
                            'Not Interested' => 'Not Interested',
                            'Converted'      => 'Called',
                            default           => 'Called'
                        };

                        $leadUpd = $pdo->prepare("
                            UPDATE leads
                            SET call_status = :call_status,
                                last_called_at = :last_called_at,
                                next_followup_at = :next_followup_at,
                                status = CASE WHEN :outcome = 'Converted' THEN 'Converted' ELSE status END,
                                updated_at = :updated_at
                            WHERE id = :lead_id
                        ");
                        $leadUpd->execute([
                            ':call_status'      => $mappedLeadCallStatus,
                            ':last_called_at'   => $callDatetime,
                            ':next_followup_at' => $nextFollowupAt,
                            ':outcome'          => $outcomeInput,
                            ':updated_at'       => date('Y-m-d H:i:s'),
                            ':lead_id'          => $leadId
                        ]);
                    }

                    $success = "Call record #{$callId} successfully updated.";
                } catch (\Throwable $e) {
                    $error = 'Failed to update call: ' . $e->getMessage();
                }
            }
        }
        // 2. DELETE CALL ACTION
        elseif ($action === 'delete_call' && $callId > 0) {
            try {
                $delStmt = $pdo->prepare("DELETE FROM calls WHERE id = :id");
                $delStmt->execute([':id' => $callId]);
                $success = "Call record #{$callId} has been deleted.";
            } catch (\Throwable $e) {
                $error = 'Failed to delete call: ' . $e->getMessage();
            }
        }
        // 2b. BULK DELETE CALLS ACTION
        elseif ($action === 'bulk_delete_calls') {
            $rawIds = $_POST['call_ids'] ?? [];
            $callIds = array_values(array_filter(array_map('intval', (array)$rawIds), fn($v) => $v > 0));
            if (empty($callIds)) {
                $error = 'No calls were selected for deletion.';
            } else {
                try {
                    $placeholders = implode(',', array_fill(0, count($callIds), '?'));
                    $delStmt = $pdo->prepare("DELETE FROM calls WHERE id IN ($placeholders)");
                    $delStmt->execute($callIds);
                    $success = count($callIds) . ' call record(s) were successfully deleted.';
                } catch (\Throwable $e) {
                    $error = 'Failed to delete calls: ' . $e->getMessage();
                }
            }
        }
    }
}

// -----------------------------------------------------------------------------
// TOP DASHBOARD CARDS (100% Real Database Queries)
// -----------------------------------------------------------------------------
$todayStr = date('Y-m-d');
$weekStartStr = date('Y-m-d 00:00:00', strtotime('monday this week'));
$weekEndStr   = date('Y-m-d 23:59:59', strtotime('sunday this week'));
$nowStr       = date('Y-m-d H:i:s');

// 1. Calls Today
$callsTodayCount = (int)$pdo->query("
    SELECT COUNT(*) FROM calls 
    WHERE call_datetime LIKE '{$todayStr}%' OR scheduled_at LIKE '{$todayStr}%'
")->fetchColumn();

// 2. Calls This Week
$callsThisWeekCount = (int)$pdo->query("
    SELECT COUNT(*) FROM calls 
    WHERE (call_datetime >= '{$weekStartStr}' AND call_datetime <= '{$weekEndStr}')
       OR (scheduled_at >= '{$weekStartStr}' AND scheduled_at <= '{$weekEndStr}')
")->fetchColumn();

// 3. Pending Follow-ups
$pendingFollowupsCount = (int)$pdo->query("
    SELECT COUNT(*) FROM calls 
    WHERE (status IN ('scheduled', 'pending') OR outcome = 'Call Back')
")->fetchColumn();

// 4. Conversions
$conversionsCount = (int)$pdo->query("
    SELECT COUNT(*) FROM calls WHERE outcome = 'Converted'
")->fetchColumn();

// -----------------------------------------------------------------------------
// VISUAL CALL ANALYTICS (7-Day Daily Trend, 4-Week Volume, Outcome Distribution)
// -----------------------------------------------------------------------------

// A. Calls per day (Past 7 Days)
$dailyCallTrend = [];
$maxDailyCalls = 1;
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $lbl = date('D, j M', strtotime($d));
    $cnt = (int)$pdo->query("
        SELECT COUNT(*) FROM calls 
        WHERE call_datetime LIKE '{$d}%' OR scheduled_at LIKE '{$d}%'
    ")->fetchColumn();
    if ($cnt > $maxDailyCalls) {
        $maxDailyCalls = $cnt;
    }
    $dailyCallTrend[] = ['date' => $d, 'label' => $lbl, 'short' => date('D', strtotime($d)), 'count' => $cnt];
}

// B. Calls per week (Past 4 Weeks)
$weeklyCallTrend = [];
$maxWeeklyCalls = 1;
for ($w = 3; $w >= 0; $w--) {
    $wStart = date('Y-m-d 00:00:00', strtotime("-$w weeks monday this week"));
    $wEnd   = date('Y-m-d 23:59:59', strtotime("-$w weeks sunday this week"));
    $wLabel = date('j M', strtotime($wStart)) . ' - ' . date('j M', strtotime($wEnd));
    $wCnt = (int)$pdo->query("
        SELECT COUNT(*) FROM calls 
        WHERE (call_datetime >= '{$wStart}' AND call_datetime <= '{$wEnd}')
           OR (scheduled_at >= '{$wStart}' AND scheduled_at <= '{$wEnd}')
    ")->fetchColumn();
    if ($wCnt > $maxWeeklyCalls) {
        $maxWeeklyCalls = $wCnt;
    }
    $weeklyCallTrend[] = ['label' => $wLabel, 'count' => $wCnt];
}

// C. Calls by outcome
$outcomesData = [];
$totalCallsWithOutcome = (int)$pdo->query("SELECT COUNT(*) FROM calls WHERE outcome IS NOT NULL AND outcome != ''")->fetchColumn();
$outcomeColorMap = [
    'Connected'      => '#10b981',
    'No Answer'     => '#f59e0b',
    'Call Back'     => '#8b5cf6',
    'Not Interested'=> '#64748b',
    'Converted'     => '#059669'
];

foreach ($validOutcomes as $out) {
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM calls WHERE outcome = '{$out}'")->fetchColumn();
    $pct = $totalCallsWithOutcome > 0 ? round(($cnt / $totalCallsWithOutcome) * 100, 1) : 0.0;
    $outcomesData[$out] = [
        'count' => $cnt,
        'pct'   => $pct,
        'color' => $outcomeColorMap[$out] ?? '#12141a'
    ];
}

// -----------------------------------------------------------------------------
// FILTER TABS & PAGINATED CALL LIST
// -----------------------------------------------------------------------------
$activeTab = sanitize_text($_GET['tab'] ?? 'all');
$validTabs = ['all', 'today', 'upcoming', 'completed', 'missed', 'callback'];
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'all';
}

$searchQuery = trim(sanitize_text($_GET['search'] ?? $_GET['q'] ?? ''));
$outcomeFilter = trim(sanitize_text($_GET['outcome'] ?? ''));
$dateFilter = trim(sanitize_text($_GET['date'] ?? ''));

// Counts for tabs
$tabCounts = [
    'all'       => (int)$pdo->query("SELECT COUNT(*) FROM calls")->fetchColumn(),
    'today'     => $callsTodayCount,
    'upcoming'  => (int)$pdo->query("SELECT COUNT(*) FROM calls WHERE next_followup_at >= '{$nowStr}' OR (status IN ('scheduled', 'pending') AND scheduled_at >= '{$nowStr}')")->fetchColumn(),
    'completed' => (int)$pdo->query("SELECT COUNT(*) FROM calls WHERE status = 'completed' OR (outcome IS NOT NULL AND outcome != '')")->fetchColumn(),
    'missed'    => (int)$pdo->query("SELECT COUNT(*) FROM calls WHERE LOWER(outcome) = 'no answer'")->fetchColumn(),
    'callback'  => (int)$pdo->query("SELECT COUNT(*) FROM calls WHERE LOWER(outcome) = 'call back'")->fetchColumn(),
];

// Query builder
$whereClauses = [];
$params = [];

if ($activeTab === 'today') {
    $whereClauses[] = "(call_datetime LIKE :today OR scheduled_at LIKE :today)";
    $params[':today'] = $todayStr . '%';
} elseif ($activeTab === 'upcoming') {
    $whereClauses[] = "(next_followup_at >= :now OR (status IN ('scheduled', 'pending') AND scheduled_at >= :now))";
    $params[':now'] = $nowStr;
} elseif ($activeTab === 'completed') {
    $whereClauses[] = "(status = 'completed' OR (outcome IS NOT NULL AND outcome != ''))";
} elseif ($activeTab === 'missed') {
    $whereClauses[] = "LOWER(outcome) = 'no answer'";
} elseif ($activeTab === 'callback') {
    $whereClauses[] = "LOWER(outcome) = 'call back'";
}

if (!empty($outcomeFilter)) {
    $whereClauses[] = "LOWER(outcome) = :outcome_filter";
    $params[':outcome_filter'] = strtolower($outcomeFilter);
}

if (!empty($dateFilter)) {
    $whereClauses[] = "(call_datetime LIKE :date_filter OR scheduled_at LIKE :date_filter)";
    $params[':date_filter'] = $dateFilter . '%';
}

if (!empty($searchQuery)) {
    $whereClauses[] = "(contact_name LIKE :q OR company LIKE :q OR phone LIKE :q OR notes LIKE :q)";
    $params[':q'] = '%' . $searchQuery . '%';
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$totalRecords = (int)$pdo->prepare("SELECT COUNT(*) FROM calls {$whereSql}")->execute($params) ? (int)$pdo->query("SELECT COUNT(*) FROM calls {$whereSql}")->fetchColumn() : 0;
// Re-execute prepared for count accurately
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM calls {$whereSql}");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $perPage));

$listStmt = $pdo->prepare("
    SELECT id, lead_id, client_id, contact_name, company, phone,
           COALESCE(call_datetime, scheduled_at, created_at) as call_time,
           status, duration_minutes, priority, notes, outcome, next_followup_at, created_at
    FROM calls
    {$whereSql}
    ORDER BY COALESCE(call_datetime, scheduled_at, created_at) DESC, id DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$listStmt->execute($params);
$callsList = $listStmt->fetchAll(PDO::FETCH_ASSOC);

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

  <!-- Header -->
  <div class="page-header">
    <div>
      <h1 class="page-title">Call Management &amp; Analytics</h1>
      <p class="page-subtitle">Track, record, analyze, and manage commercial outreach calls and scheduled follow-ups.</p>
    </div>
    <a href="leads.php" class="btn-action" style="padding: 8px 14px; background: #ffffff;">
      &larr; View Leads Pipeline
    </a>
  </div>

  <!-- =========================================================
       TOP DASHBOARD CARDS (DATABASE-DRIVEN)
  ========================================================= -->
  <div class="kpi-grid" style="margin-bottom: 28px;">
    <!-- 1. Calls Today -->
    <div class="kpi-card">
      <div class="kpi-top">
        <span class="kpi-label">Calls Today</span>
        <div class="kpi-icon-wrap blue">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
      </div>
      <div class="kpi-value"><?= (int)$callsTodayCount ?></div>
      <div class="kpi-footer">
        <span class="kpi-tag positive">Today</span>
        <span>Scheduled &amp; logged</span>
      </div>
    </div>

    <!-- 2. Calls This Week -->
    <div class="kpi-card">
      <div class="kpi-top">
        <span class="kpi-label">Calls This Week</span>
        <div class="kpi-icon-wrap lime">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
          </svg>
        </div>
      </div>
      <div class="kpi-value"><?= (int)$callsThisWeekCount ?></div>
      <div class="kpi-footer">
        <span class="kpi-tag positive">Active Sprint</span>
        <span>Current calendar week</span>
      </div>
    </div>

    <!-- 3. Pending Follow-ups -->
    <div class="kpi-card">
      <div class="kpi-top">
        <span class="kpi-label">Pending Follow-ups</span>
        <div class="kpi-icon-wrap orange">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
          </svg>
        </div>
      </div>
      <div class="kpi-value"><?= (int)$pendingFollowupsCount ?></div>
      <div class="kpi-footer">
        <?php if ($pendingFollowupsCount > 0): ?>
          <span class="kpi-tag alert">Action Required</span>
          <span>Awaiting call queue</span>
        <?php else: ?>
          <span class="kpi-tag positive">Up to date</span>
          <span>Queue clear</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- 4. Conversions -->
    <div class="kpi-card">
      <div class="kpi-top">
        <span class="kpi-label">Conversions</span>
        <div class="kpi-icon-wrap purple">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
      </div>
      <div class="kpi-value" style="color: #047857;"><?= (int)$conversionsCount ?></div>
      <div class="kpi-footer">
        <span class="kpi-tag positive">Won Accounts</span>
        <span>Converted via sales calls</span>
      </div>
    </div>
  </div>

  <!-- =========================================================
       VISUAL CALL ANALYTICS GRID
       1. Calls per day (7 Days)
       2. Calls per week (4 Weeks)
       3. Calls by outcome
  ========================================================= -->
  <div class="dashboard-charts-grid" style="grid-template-columns: 1fr 1fr 1.2fr; margin-bottom: 28px;">
    <!-- 1. Calls per day -->
    <div class="chart-card">
      <div class="chart-header" style="margin-bottom: 12px;">
        <div>
          <h2 class="chart-title">Calls per day</h2>
          <div class="chart-desc">7-day daily activity volume</div>
        </div>
      </div>

      <div style="display: flex; align-items: flex-end; justify-content: space-between; height: 160px; padding-top: 15px; border-bottom: 1px solid var(--border-light); gap: 8px;">
        <?php foreach ($dailyCallTrend as $day): ?>
          <?php $barH = ($day['count'] / $maxDailyCalls) * 100; ?>
          <div style="flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; position: relative;">
            <?php if ($day['count'] > 0): ?>
              <span style="font-family: 'DM Mono', monospace; font-size: 10px; font-weight: 700; color: var(--text-dark); position: absolute; top: -18px;">
                <?= $day['count'] ?>
              </span>
            <?php endif; ?>
            <div style="width: 100%; max-width: 24px; height: 100%; background: var(--border-subtle); border-radius: 4px 4px 0 0; display: flex; align-items: flex-end;">
              <div style="width: 100%; height: <?= max(4, (int)$barH) ?>%; background: <?= $day['date'] === $todayStr ? 'var(--lime)' : '#12141a' ?>; border-radius: 4px 4px 0 0; transition: height 0.4s ease;"></div>
            </div>
            <span style="font-family: 'DM Mono', monospace; font-size: 10px; color: var(--text-muted); margin-top: 6px;">
              <?= e($day['short']) ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- 2. Calls per week -->
    <div class="chart-card">
      <div class="chart-header" style="margin-bottom: 12px;">
        <div>
          <h2 class="chart-title">Calls per week</h2>
          <div class="chart-desc">4-week sprint progression</div>
        </div>
      </div>

      <div style="display: flex; align-items: flex-end; justify-content: space-between; height: 160px; padding-top: 15px; border-bottom: 1px solid var(--border-light); gap: 12px;">
        <?php foreach ($weeklyCallTrend as $wIdx => $week): ?>
          <?php $wBarH = ($week['count'] / $maxWeeklyCalls) * 100; ?>
          <div style="flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; position: relative;">
            <?php if ($week['count'] > 0): ?>
              <span style="font-family: 'DM Mono', monospace; font-size: 10px; font-weight: 700; color: var(--text-dark); position: absolute; top: -18px;">
                <?= $week['count'] ?>
              </span>
            <?php endif; ?>
            <div style="width: 100%; max-width: 34px; height: 100%; background: var(--border-subtle); border-radius: 4px 4px 0 0; display: flex; align-items: flex-end;">
              <div style="width: 100%; height: <?= max(4, (int)$wBarH) ?>%; background: #3b82f6; border-radius: 4px 4px 0 0; transition: height 0.4s ease;"></div>
            </div>
            <span style="font-family: 'DM Mono', monospace; font-size: 10px; color: var(--text-muted); margin-top: 6px; white-space: nowrap;">
              Wk <?= $wIdx + 1 ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- 3. Calls by outcome -->
    <div class="chart-card">
      <div class="chart-header" style="margin-bottom: 12px;">
        <div>
          <h2 class="chart-title">Calls by outcome</h2>
          <div class="chart-desc"><?= $totalCallsWithOutcome ?> recorded outcomes</div>
        </div>
      </div>

      <div style="display: flex; flex-direction: column; gap: 10px; padding-top: 6px;">
        <?php foreach ($outcomesData as $outName => $outInfo): ?>
          <div>
            <div style="display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 4px;">
              <span style="font-weight: 600; color: var(--text-dark); display: flex; align-items: center; gap: 6px;">
                <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background: <?= $outInfo['color'] ?>;"></span>
                <?= e($outName) ?>
              </span>
              <span style="font-family: 'DM Mono', monospace; color: var(--text-muted);">
                <?= $outInfo['count'] ?> (<?= number_format($outInfo['pct'], 1) ?>%)
              </span>
            </div>
            <div style="height: 6px; background: var(--border-subtle); border-radius: 999px; overflow: hidden;">
              <div style="width: <?= $outInfo['pct'] ?>%; height: 100%; background: <?= $outInfo['color'] ?>; border-radius: 999px;"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- =========================================================
       FILTER TABS (Today's Calls, Upcoming Follow-ups, etc.)
  ========================================================= -->
  <div class="filter-bar" style="margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 16px; justify-content: space-between; align-items: center;">
    <div class="filter-pills" style="display: flex; flex-wrap: wrap; gap: 6px;">
      <a href="?tab=all<?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?>"
         class="filter-pill <?= $activeTab === 'all' ? 'active' : '' ?>">
        All Calls (<?= $tabCounts['all'] ?>)
      </a>
      <a href="?tab=today<?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?>"
         class="filter-pill <?= $activeTab === 'today' ? 'active' : '' ?>">
        Today's Calls (<?= $tabCounts['today'] ?>)
      </a>
      <a href="?tab=upcoming<?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?>"
         class="filter-pill <?= $activeTab === 'upcoming' ? 'active' : '' ?>">
        Upcoming Follow-ups (<?= $tabCounts['upcoming'] ?>)
      </a>
      <a href="?tab=completed<?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?>"
         class="filter-pill <?= $activeTab === 'completed' ? 'active' : '' ?>">
        Completed Calls (<?= $tabCounts['completed'] ?>)
      </a>
      <a href="?tab=missed<?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?>"
         class="filter-pill <?= $activeTab === 'missed' ? 'active' : '' ?>">
        Missed/No Answer (<?= $tabCounts['missed'] ?>)
      </a>
      <a href="?tab=callback<?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?>"
         class="filter-pill <?= $activeTab === 'callback' ? 'active' : '' ?>">
        Call Back Required (<?= $tabCounts['callback'] ?>)
      </a>
    </div>

    <!-- Search box -->
    <form method="GET" action="calls.php" style="display: flex; gap: 8px;">
      <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
      <input type="text" name="q" value="<?= e($searchQuery) ?>" placeholder="Search calls..." class="search-input" style="padding: 7px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; width: 220px;">
      <button type="submit" class="btn-action" style="padding: 7px 12px;">Search</button>
      <?php if (!empty($searchQuery)): ?>
        <a href="calls.php?tab=<?= e($activeTab) ?>" class="btn-action" style="padding: 7px 10px; background: #ffffff;">✕</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- =========================================================
       CALL TABLE
       Columns: Client, Phone, Date, Time, Outcome, Next Follow-up, Actions
  ========================================================= -->
  <div class="data-card">
    <div class="table-responsive">
      <?php if (empty($callsList)): ?>
        <div class="empty-state-banner">
          <div class="empty-state-icon">📞</div>
          <div class="empty-state-title">No calls recorded yet.</div>
          <p>No calls match the selected filter criteria.</p>
        </div>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th style="width: 40px; text-align: center;">
                <input type="checkbox" id="selectAllCalls" class="bulk-select-all" title="Select All Calls">
              </th>
              <th>Client</th>
              <th>Phone</th>
              <th>Date</th>
              <th>Time</th>
              <th>Outcome</th>
              <th>Next Follow-up</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($callsList as $call): ?>
              <?php
                $callTimestamp = strtotime($call['call_time']);
                $dateDisplay   = date('M j, Y', $callTimestamp);
                $timeDisplay   = date('H:i', $callTimestamp);

                $isOverdue = !empty($call['next_followup_at']) && (strtotime($call['next_followup_at']) < time());
                $outcomeVal = $call['outcome'] ?: ($call['status'] === 'scheduled' ? 'Scheduled' : 'Logged');

                $badgeClass = match ($call['outcome'] ?? '') {
                    'Connected'      => 'badge-called',
                    'No Answer'     => 'badge-no-answer',
                    'Call Back'     => 'badge-callback',
                    'Not Interested'=> 'badge-not-interested',
                    'Converted'     => 'badge-called',
                    default          => 'badge-not-called'
                };
              ?>
              <tr class="<?= $isOverdue ? 'row-overdue' : '' ?>">
                <td style="width: 40px; text-align: center;">
                  <input type="checkbox" class="call-select-checkbox row-select-checkbox" value="<?= (int)$call['id'] ?>">
                </td>
                <!-- 1. Client (Name & Company) -->
                <td>
                  <strong><?= e($call['contact_name']) ?></strong>
                  <?php if (!empty($call['company'])): ?>
                    <div style="font-size: 11px; color: var(--text-muted);"><?= e($call['company']) ?></div>
                  <?php endif; ?>
                </td>

                <!-- 2. Phone -->
                <td>
                  <a href="tel:<?= e($call['phone']) ?>" style="font-family: 'DM Mono', monospace; font-size: 12px; color: var(--text-dark); text-decoration: underline; text-underline-offset: 2px;">
                    <?= e($call['phone']) ?>
                  </a>
                </td>

                <!-- 3. Date -->
                <td style="font-family: 'DM Mono', monospace; font-size: 11px; color: var(--text-dark);">
                  <?= e($dateDisplay) ?>
                </td>

                <!-- 4. Time -->
                <td style="font-family: 'DM Mono', monospace; font-size: 11px; color: var(--text-muted);">
                  <?= e($timeDisplay) ?>
                </td>

                <!-- 5. Outcome -->
                <td>
                  <span class="badge-call-status <?= $badgeClass ?>">
                    <span class="dot"></span>
                    <?= e($outcomeVal) ?>
                  </span>
                </td>

                <!-- 6. Next Follow-up -->
                <td>
                  <?php if (!empty($call['next_followup_at'])): ?>
                    <div style="font-family: 'DM Mono', monospace; font-size: 11px; <?= $isOverdue ? 'color: #dc2626; font-weight: 700;' : 'color: var(--text-dark);' ?>">
                      <?= e(format_date($call['next_followup_at'], 'M j, H:i')) ?>
                      <?php if ($isOverdue): ?>
                        <span class="badge-overdue" style="font-size: 9px; padding: 1px 4px; margin-left: 4px;">Overdue</span>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <span style="color: var(--text-light); font-family: 'DM Mono', monospace; font-size: 11px;">—</span>
                  <?php endif; ?>
                </td>

                <!-- 7. Actions: View, Edit, Delete -->
                <td style="text-align: right; white-space: nowrap;">
                  <!-- View Button -->
                  <button type="button"
                          class="btn-action"
                          style="padding: 4px 8px; font-size: 11px;"
                          onclick='openViewModal(<?= json_encode($call, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                          title="View Call Details">
                    View
                  </button>

                  <!-- Edit Button -->
                  <button type="button"
                          class="btn-action"
                          style="padding: 4px 8px; font-size: 11px; background: #ffffff;"
                          onclick='openEditModal(<?= json_encode($call, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                          title="Edit Call Record">
                    Edit
                  </button>

                  <!-- Delete Button -->
                  <button type="button"
                          onclick="confirmDeleteCall(<?= (int)$call['id'] ?>, '<?= e(addslashes($call['contact_name'])) ?>')"
                          style="color: var(--text-muted); padding: 4px 6px; font-size: 12px; cursor: pointer;"
                          title="Delete Call Record">
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
          Showing <?= $offset + 1 ?> to <?= min($totalRecords, $offset + $perPage) ?> of <?= $totalRecords ?> calls
        </div>
        <div class="pagination-links">
          <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?tab=<?= e($activeTab) ?>&page=<?= $p ?>&q=<?= urlencode($searchQuery) ?>"
               class="page-btn <?= $p === $page ? 'active' : '' ?>">
              <?= $p ?>
            </a>
          <?php endfor; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- =========================================================
       MODAL 1: VIEW CALL MODAL
  ========================================================= -->
  <div id="viewCallModal" class="modal-overlay" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-header">
        <div>
          <div class="modal-title">Call Details</div>
          <div id="viewCallSubtitle" style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"></div>
        </div>
        <button type="button" class="modal-close-btn" onclick="closeViewModal()">✕</button>
      </div>

      <div class="modal-body" style="font-size: 13px;">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
          <div>
            <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase;">Contact</span>
            <div id="viewContactName" style="font-weight: 700; color: var(--text-dark);"></div>
            <div id="viewCompany" style="color: var(--text-muted); font-size: 12px;"></div>
          </div>
          <div>
            <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase;">Phone</span>
            <div id="viewPhone" style="font-family: 'DM Mono', monospace; font-weight: 600;"></div>
          </div>
          <div>
            <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase;">Call Date &amp; Time</span>
            <div id="viewCallTime" style="font-family: 'DM Mono', monospace;"></div>
          </div>
          <div>
            <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase;">Outcome</span>
            <div id="viewOutcome"></div>
          </div>
        </div>

        <div style="margin-bottom: 14px;">
          <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase;">Next Follow-up</span>
          <div id="viewNextFollowup" style="font-family: 'DM Mono', monospace; margin-top: 2px;"></div>
        </div>

        <div>
          <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase;">Notes / Discussion</span>
          <div id="viewNotes" style="background: var(--main-bg); padding: 12px; border-radius: 8px; border: 1px solid var(--border-light); margin-top: 6px; line-height: 1.5; white-space: pre-wrap;"></div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-action" style="background: var(--main-bg);" onclick="closeViewModal()">Close</button>
      </div>
    </div>
  </div>

  <!-- =========================================================
       MODAL 2: EDIT CALL MODAL
       Allows editing: Date, Time, Outcome, Notes, Next follow-up
  ========================================================= -->
  <div id="editCallModal" class="modal-overlay" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-header">
        <div>
          <div class="modal-title">Edit Call Record</div>
          <div id="editCallSubtitle" style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"></div>
        </div>
        <button type="button" class="modal-close-btn" onclick="closeEditModal()">✕</button>
      </div>

      <form method="POST" action="calls.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="edit_call">
        <input type="hidden" name="call_id" id="editCallId" value="0">

        <div class="modal-body">
          <!-- Date & Time Row -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">
                Call Date *
              </label>
              <input type="date" name="call_date" id="editCallDate" class="form-control" required style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace; font-size: 12px;">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">
                Call Time *
              </label>
              <input type="time" name="call_time" id="editCallTime" class="form-control" required style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace; font-size: 12px;">
            </div>
          </div>

          <!-- Outcome Selector -->
          <div class="form-group" style="margin-bottom: 16px;">
            <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">
              Call Outcome *
            </label>
            <select name="outcome" id="editOutcome" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 12px; background: #ffffff;">
              <option value="">-- Select Outcome --</option>
              <?php foreach ($validOutcomes as $out): ?>
                <option value="<?= e($out) ?>"><?= e($out) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Next Follow-up Date & Time Row -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">
                Next Follow-up Date (Optional)
              </label>
              <input type="date" name="next_followup_date" id="editNextFollowupDate" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace; font-size: 12px;">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">
                Next Follow-up Time
              </label>
              <input type="time" name="next_followup_time" id="editNextFollowupTime" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-family: 'DM Mono', monospace; font-size: 12px;">
            </div>
          </div>

          <!-- Notes -->
          <div class="form-group" style="margin-bottom: 8px;">
            <label class="form-label" style="font-weight: 600; font-size: 12px; color: var(--text-dark); margin-bottom: 6px; display: block;">
              Notes &amp; Discussion
            </label>
            <textarea name="notes" id="editNotes" rows="3" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-light); font-size: 13px; line-height: 1.5; resize: vertical;"></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-action" style="background: var(--main-bg);" onclick="closeEditModal()">Cancel</button>
          <button type="submit" class="btn-primary-admin" style="padding: 8px 18px; font-size: 13px;">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete Form -->
  <form id="deleteCallForm" method="POST" action="calls.php" style="display: none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_call">
    <input type="hidden" name="call_id" id="deleteCallId" value="0">
  </form>

  <!-- Bulk Delete Calls Form -->
  <form id="bulkDeleteCallsForm" method="POST" action="calls.php" style="display: none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="bulk_delete_calls">
    <div id="bulkDeleteCallsInputs"></div>
  </form>

  <!-- Floating Bulk Actions Toolbar -->
  <div id="callsBulkBar" class="bulk-actions-bar" style="display: none;">
    <div class="bulk-actions-info">
      <span class="bulk-count-badge" id="callsSelectedCount">0</span>
      <span>call(s) selected</span>
    </div>
    <div class="bulk-actions-buttons">
      <button type="button" class="btn-bulk-delete" id="btnBulkDeleteCalls">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
        Delete Selected (<span id="callsDeleteCountBtnText">0</span>)
      </button>
      <button type="button" class="btn-bulk-cancel" id="btnBulkCancelCalls">Deselect All</button>
    </div>
  </div>

  <script>
  function openViewModal(call) {
    document.getElementById('viewCallSubtitle').textContent = '#' + call.id + ' — ' + call.contact_name;
    document.getElementById('viewContactName').textContent = call.contact_name;
    document.getElementById('viewCompany').textContent = call.company || '—';
    document.getElementById('viewPhone').textContent = call.phone || '—';
    document.getElementById('viewCallTime').textContent = call.call_time || '—';
    document.getElementById('viewOutcome').textContent = call.outcome || (call.status || 'Logged');
    document.getElementById('viewNextFollowup').textContent = call.next_followup_at ? call.next_followup_at : 'None scheduled';
    document.getElementById('viewNotes').textContent = call.notes ? call.notes : 'No notes recorded.';

    const modal = document.getElementById('viewCallModal');
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeViewModal() {
    const modal = document.getElementById('viewCallModal');
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
  }

  function openEditModal(call) {
    document.getElementById('editCallId').value = call.id;
    document.getElementById('editCallSubtitle').textContent = '#' + call.id + ' — ' + call.contact_name;

    // Parse call date & time
    const callDateObj = new Date(call.call_time);
    if (!isNaN(callDateObj.getTime())) {
      const pad = n => String(n).padStart(2, '0');
      document.getElementById('editCallDate').value = callDateObj.getFullYear() + '-' + pad(callDateObj.getMonth() + 1) + '-' + pad(callDateObj.getDate());
      document.getElementById('editCallTime').value = pad(callDateObj.getHours()) + ':' + pad(callDateObj.getMinutes());
    } else {
      document.getElementById('editCallDate').value = '';
      document.getElementById('editCallTime').value = '10:00';
    }

    // Outcome
    document.getElementById('editOutcome').value = call.outcome || '';

    // Next follow-up
    if (call.next_followup_at) {
      const fuObj = new Date(call.next_followup_at);
      if (!isNaN(fuObj.getTime())) {
        const pad = n => String(n).padStart(2, '0');
        document.getElementById('editNextFollowupDate').value = fuObj.getFullYear() + '-' + pad(fuObj.getMonth() + 1) + '-' + pad(fuObj.getDate());
        document.getElementById('editNextFollowupTime').value = pad(fuObj.getHours()) + ':' + pad(fuObj.getMinutes());
      }
    } else {
      document.getElementById('editNextFollowupDate').value = '';
      document.getElementById('editNextFollowupTime').value = '10:00';
    }

    // Notes
    document.getElementById('editNotes').value = call.notes || '';

    const modal = document.getElementById('editCallModal');
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeEditModal() {
    const modal = document.getElementById('editCallModal');
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
  }

  function confirmDeleteCall(callId, name) {
    if (window.AdminModal && typeof window.AdminModal.confirm === 'function') {
      window.AdminModal.confirm({
        title: 'Delete Call Record',
        message: 'Are you sure you want to delete call record #' + callId + ' for "' + name + '"?',
        confirmText: 'Delete Call',
        isDanger: true,
        onConfirm: () => {
          document.getElementById('deleteCallId').value = callId;
          document.getElementById('deleteCallForm').submit();
        }
      });
    } else {
      document.getElementById('deleteCallId').value = callId;
      document.getElementById('deleteCallForm').submit();
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

  // -----------------------------------------------------------
  // Bulk Call Selection Controller
  // -----------------------------------------------------------
  function initBulkCallsSelection() {
    const selectAllCalls = document.getElementById('selectAllCalls');
    const bulkBar = document.getElementById('callsBulkBar');
    const selectedCountBadge = document.getElementById('callsSelectedCount');
    const deleteCountBtnText = document.getElementById('callsDeleteCountBtnText');
    const btnBulkDelete = document.getElementById('btnBulkDeleteCalls');
    const btnBulkCancel = document.getElementById('btnBulkCancelCalls');
    const bulkInputs = document.getElementById('bulkDeleteCallsInputs');
    const bulkForm = document.getElementById('bulkDeleteCallsForm');

    function getCheckboxes() {
      return document.querySelectorAll('.call-select-checkbox');
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

      // Master checkbox
      if (selectAllCalls) {
        selectAllCalls.checked = checkboxes.length > 0 && count === checkboxes.length;
        selectAllCalls.indeterminate = count > 0 && count < checkboxes.length;
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

    if (selectAllCalls) {
      selectAllCalls.addEventListener('change', function() {
        toggleAll(this.checked);
      });
    }

    document.addEventListener('change', function(e) {
      if (e.target && e.target.classList.contains('call-select-checkbox')) {
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

        const confirmMsg = 'Are you sure you want to permanently delete ' + count + ' selected call record(s)? This action cannot be undone.';
        const confirmTitle = 'Delete Selected Calls (' + count + ')';
        const confirmBtn = 'Yes, Delete ' + count + ' Calls';

        const doSubmit = () => {
          bulkInputs.innerHTML = '';
          checkedBoxes.forEach(cb => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'call_ids[]';
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
    document.addEventListener('DOMContentLoaded', initBulkCallsSelection);
  } else {
    initBulkCallsSelection();
  }
  </script>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>
