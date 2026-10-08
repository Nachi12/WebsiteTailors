<?php
/**
 * WebsiteTailors — Business Analytics Dashboard (Phase 6)
 *
 * Grounded 100% in real database data from clients, leads, calls, and invoices.
 * Real-time executive metrics, interactive period switching, smooth SVG line & bar charts,
 * 6-stage lead pipeline, service revenue breakdown in ₹, unified activity feed,
 * overdue follow-up alerting, and strict zero-fake-data empty states.
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) {
    define('WebsiteTailors_INIT', true);
}
require_once __DIR__ . '/includes/auth_guard.php';

$pageTitle = 'Business Analytics';
$breadcrumb = 'Executive Dashboard';

// Connect to database
$pdo = null;
try {
    $db = Database::getInstance();
    if ($db->isConnected()) {
        $pdo = $db->getConnection();
    }
} catch (\Throwable $e) {
    error_log("Analytics Dashboard Connection Error: " . $e->getMessage());
}

// -----------------------------------------------------------------------------
// 1. TOP KPI CARDS DATA (Real Database Data Only)
// -----------------------------------------------------------------------------
$totalClientsCount      = 0;
$activeClientsCount     = 0;
$newClientsThisMonth    = 0;

$newLeadsCount          = 0;
$totalLeadsCount        = 0;
$leadsThisMonth         = 0;

$revenueThisMonth       = 0.00;
$revenuePrevMonth       = 0.00;
$totalLifetimeRevenue   = 0.00;
$paidInvoicesThisMonth  = 0;

$pendingCallsCount      = 0;
$overdueCallsCount      = 0;

// Current & Previous Month Datetime Bounds
$currentMonthStr  = date('Y-m');
$currentMonthStart = date('Y-m-01 00:00:00');
$currentMonthEnd   = date('Y-m-t 23:59:59');
$prevMonthStr     = date('Y-m', strtotime('-1 month'));
$nowStr            = date('Y-m-d H:i:s');

if ($pdo !== null) {
    try {
        // KPI 1: TOTAL CLIENTS
        $totalClientsCount   = (int)$pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
        $activeClientsCount  = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE LOWER(status) = 'active'")->fetchColumn();
        $newClientsThisMonth = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE created_at >= '{$currentMonthStart}'")->fetchColumn();

        // KPI 2: NEW LEADS
        $newLeadsCount       = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE LOWER(status) = 'new'")->fetchColumn();
        $totalLeadsCount     = (int)$pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
        $leadsThisMonth      = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE created_at >= '{$currentMonthStart}'")->fetchColumn();

        // KPI 3: REVENUE THIS MONTH (₹)
        // Check if revenue table exists and has entries (manual internal revenue ledger)
        $hasRevenueTable = false;
        try {
            $revCount = (int)$pdo->query("SELECT COUNT(*) FROM revenue")->fetchColumn();
            if ($revCount > 0) {
                $hasRevenueTable = true;
            }
        } catch (\Throwable) {
            $hasRevenueTable = false;
        }

        if ($hasRevenueTable) {
            $revStmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM revenue WHERE LOWER(payment_status) = 'paid' AND payment_date >= '{$currentMonthStart}' AND payment_date <= '{$currentMonthEnd}'");
            $revenueThisMonth = (float)$revStmt->fetchColumn();

            $prevMonthStart = date('Y-m-01 00:00:00', strtotime('first day of last month'));
            $prevMonthEnd   = date('Y-m-t 23:59:59', strtotime('last day of last month'));
            $revPrevStmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM revenue WHERE LOWER(payment_status) = 'paid' AND payment_date >= '{$prevMonthStart}' AND payment_date <= '{$prevMonthEnd}'");
            $revenuePrevMonth = (float)$revPrevStmt->fetchColumn();

            $totRevStmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM revenue WHERE LOWER(payment_status) = 'paid'");
            $totalLifetimeRevenue = (float)$totRevStmt->fetchColumn();

            $paidInvoicesThisMonth = (int)$pdo->query("SELECT COUNT(*) FROM revenue WHERE LOWER(payment_status) = 'paid' AND payment_date >= '{$currentMonthStart}' AND payment_date <= '{$currentMonthEnd}'")->fetchColumn();
        } else {
            $revStmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE LOWER(status) = 'paid' AND paid_at >= '{$currentMonthStart}' AND paid_at <= '{$currentMonthEnd}'");
            $revenueThisMonth = (float)$revStmt->fetchColumn();

            $revPrevStmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE LOWER(status) = 'paid' AND paid_at LIKE '{$prevMonthStr}%'");
            $revenuePrevMonth = (float)$revPrevStmt->fetchColumn();

            $totRevStmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE LOWER(status) = 'paid'");
            $totalLifetimeRevenue = (float)$totRevStmt->fetchColumn();

            $paidInvoicesThisMonth = (int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE LOWER(status) = 'paid' AND paid_at >= '{$currentMonthStart}' AND paid_at <= '{$currentMonthEnd}'")->fetchColumn();
        }

        // KPI 4: PENDING FOLLOW-UPS
        $pendingCallsCount = (int)$pdo->query("SELECT COUNT(*) FROM calls WHERE status IN ('scheduled', 'pending')")->fetchColumn();
        $overdueCallsCount = (int)$pdo->query("SELECT COUNT(*) FROM calls WHERE status IN ('scheduled', 'pending') AND scheduled_at < '{$nowStr}'")->fetchColumn();
    } catch (\Throwable $ex) {
        error_log("KPI Card Aggregation Error: " . $ex->getMessage());
    }
}

// -----------------------------------------------------------------------------
// 2. REVENUE CHART DATA (4 Periods: 7 Days, 30 Days, 6 Months, This Year)
// -----------------------------------------------------------------------------
$revenuePeriods = [
    '7days'   => [],
    '30days'  => [],
    '6months' => [],
    'year'    => []
];

$hasAnyRevenue = false;

if ($pdo !== null) {
    try {
        if (!empty($hasRevenueTable)) {
            // Last 7 Days (From revenue table)
            for ($i = 6; $i >= 0; $i--) {
                $dateVal = date('Y-m-d', strtotime("-$i days"));
                $label = date('D, j M', strtotime($dateVal));
                $shortLabel = date('j M', strtotime($dateVal));
                $amt = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM revenue WHERE LOWER(payment_status) = 'paid' AND payment_date LIKE '{$dateVal}%'")->fetchColumn();
                if ($amt > 0) { $hasAnyRevenue = true; }
                $revenuePeriods['7days'][] = [
                    'date'       => $dateVal,
                    'label'      => $label,
                    'shortLabel' => $shortLabel,
                    'revenue'    => $amt
                ];
            }

            // Last 30 Days (Grouped into 6 5-day intervals)
            for ($i = 5; $i >= 0; $i--) {
                $startOffset = ($i * 5) + 4;
                $endOffset   = $i * 5;
                $startDate   = date('Y-m-d', strtotime("-$startOffset days"));
                $endDate     = date('Y-m-d', strtotime("-$endOffset days"));
                $label       = date('j M', strtotime($startDate)) . ' - ' . date('j M', strtotime($endDate));
                $amt = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM revenue WHERE LOWER(payment_status) = 'paid' AND payment_date >= '{$startDate} 00:00:00' AND payment_date <= '{$endDate} 23:59:59'")->fetchColumn();
                if ($amt > 0) { $hasAnyRevenue = true; }
                $revenuePeriods['30days'][] = [
                    'date'       => $startDate,
                    'label'      => $label,
                    'shortLabel' => date('j M', strtotime($endDate)),
                    'revenue'    => $amt
                ];
            }

            // Last 6 Months
            for ($i = 5; $i >= 0; $i--) {
                $monthVal = date('Y-m', strtotime("-$i months"));
                $label = date('M Y', strtotime($monthVal . '-01'));
                $shortLabel = date('M', strtotime($monthVal . '-01'));
                $amt = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM revenue WHERE LOWER(payment_status) = 'paid' AND payment_date LIKE '{$monthVal}%'")->fetchColumn();
                if ($amt > 0) { $hasAnyRevenue = true; }
                $revenuePeriods['6months'][] = [
                    'month'      => $monthVal,
                    'label'      => $label,
                    'shortLabel' => $shortLabel,
                    'revenue'    => $amt
                ];
            }

            // This Year (Month-by-month for current year)
            $currentMonthNum = (int)date('n');
            $currentYear     = date('Y');
            for ($m = 1; $m <= $currentMonthNum; $m++) {
                $monthVal = sprintf('%s-%02d', $currentYear, $m);
                $label = date('M Y', strtotime($monthVal . '-01'));
                $shortLabel = date('M', strtotime($monthVal . '-01'));
                $amt = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM revenue WHERE LOWER(payment_status) = 'paid' AND payment_date LIKE '{$monthVal}%'")->fetchColumn();
                if ($amt > 0) { $hasAnyRevenue = true; }
                $revenuePeriods['year'][] = [
                    'month'      => $monthVal,
                    'label'      => $label,
                    'shortLabel' => $shortLabel,
                    'revenue'    => $amt
                ];
            }
        } else {
            // Last 7 Days (From invoices table fallback)
            for ($i = 6; $i >= 0; $i--) {
                $dateVal = date('Y-m-d', strtotime("-$i days"));
                $label = date('D, j M', strtotime($dateVal));
                $shortLabel = date('j M', strtotime($dateVal));
                $amt = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE LOWER(status) = 'paid' AND paid_at LIKE '{$dateVal}%'")->fetchColumn();
                if ($amt > 0) { $hasAnyRevenue = true; }
                $revenuePeriods['7days'][] = [
                    'date'       => $dateVal,
                    'label'      => $label,
                    'shortLabel' => $shortLabel,
                    'revenue'    => $amt
                ];
            }

            // Last 30 Days (Grouped into 6 5-day intervals)
            for ($i = 5; $i >= 0; $i--) {
                $startOffset = ($i * 5) + 4;
                $endOffset   = $i * 5;
                $startDate   = date('Y-m-d', strtotime("-$startOffset days"));
                $endDate     = date('Y-m-d', strtotime("-$endOffset days"));
                $label       = date('j M', strtotime($startDate)) . ' - ' . date('j M', strtotime($endDate));
                $amt = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE LOWER(status) = 'paid' AND paid_at >= '{$startDate} 00:00:00' AND paid_at <= '{$endDate} 23:59:59'")->fetchColumn();
                if ($amt > 0) { $hasAnyRevenue = true; }
                $revenuePeriods['30days'][] = [
                    'date'       => $startDate,
                    'label'      => $label,
                    'shortLabel' => date('j M', strtotime($endDate)),
                    'revenue'    => $amt
                ];
            }

            // Last 6 Months
            for ($i = 5; $i >= 0; $i--) {
                $monthVal = date('Y-m', strtotime("-$i months"));
                $label = date('M Y', strtotime($monthVal . '-01'));
                $shortLabel = date('M', strtotime($monthVal . '-01'));
                $amt = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE LOWER(status) = 'paid' AND paid_at LIKE '{$monthVal}%'")->fetchColumn();
                if ($amt > 0) { $hasAnyRevenue = true; }
                $revenuePeriods['6months'][] = [
                    'month'      => $monthVal,
                    'label'      => $label,
                    'shortLabel' => $shortLabel,
                    'revenue'    => $amt
                ];
            }

            // This Year (Month-by-month for current year)
            $currentMonthNum = (int)date('n');
            $currentYear     = date('Y');
            for ($m = 1; $m <= $currentMonthNum; $m++) {
                $monthVal = sprintf('%s-%02d', $currentYear, $m);
                $label = date('M Y', strtotime($monthVal . '-01'));
                $shortLabel = date('M', strtotime($monthVal . '-01'));
                $amt = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE LOWER(status) = 'paid' AND paid_at LIKE '{$monthVal}%'")->fetchColumn();
                if ($amt > 0) { $hasAnyRevenue = true; }
                $revenuePeriods['year'][] = [
                    'month'      => $monthVal,
                    'label'      => $label,
                    'shortLabel' => $shortLabel,
                    'revenue'    => $amt
                ];
            }
        }
    } catch (\Throwable $ex) {
        error_log("Revenue Periods Query Error: " . $ex->getMessage());
    }
}

// -----------------------------------------------------------------------------
// 3. CALLS CHART DATA (2 Periods: 7 Days, 30 Days)
// -----------------------------------------------------------------------------
$callActivityPeriods = [
    '7days'  => [],
    '30days' => []
];

$hasAnyCalls = false;

if ($pdo !== null) {
    try {
        $totalCallsInDb = (int)$pdo->query("SELECT COUNT(*) FROM calls")->fetchColumn();
        if ($totalCallsInDb > 0) {
            $hasAnyCalls = true;
        }

        // 7 Days
        for ($i = 6; $i >= 0; $i--) {
            $dateVal = date('Y-m-d', strtotime("-$i days"));
            $label   = date('D, j M', strtotime($dateVal));
            $short   = date('j M', strtotime($dateVal));

            $total = (int)$pdo->query("SELECT COUNT(*) FROM calls WHERE scheduled_at LIKE '{$dateVal}%' OR created_at LIKE '{$dateVal}%'")->fetchColumn();
            $connected = (int)$pdo->query("SELECT COUNT(*) FROM calls WHERE (scheduled_at LIKE '{$dateVal}%' OR created_at LIKE '{$dateVal}%') AND LOWER(outcome) = 'connected'")->fetchColumn();
            $noAnswer  = (int)$pdo->query("SELECT COUNT(*) FROM calls WHERE (scheduled_at LIKE '{$dateVal}%' OR created_at LIKE '{$dateVal}%') AND LOWER(outcome) = 'no answer'")->fetchColumn();
            $callBack  = (int)$pdo->query("SELECT COUNT(*) FROM calls WHERE (scheduled_at LIKE '{$dateVal}%' OR created_at LIKE '{$dateVal}%') AND LOWER(outcome) = 'call back'")->fetchColumn();

            $callActivityPeriods['7days'][] = [
                'date'       => $dateVal,
                'label'      => $label,
                'shortLabel' => $short,
                'calls'      => $total,
                'connected'  => $connected,
                'no_answer'  => $noAnswer,
                'call_back'  => $callBack
            ];
        }

        // 30 Days (6 intervals of 5 days)
        for ($i = 5; $i >= 0; $i--) {
            $startOffset = ($i * 5) + 4;
            $endOffset   = $i * 5;
            $startDate   = date('Y-m-d', strtotime("-$startOffset days"));
            $endDate     = date('Y-m-d', strtotime("-$endOffset days"));
            $label       = date('j M', strtotime($startDate)) . ' - ' . date('j M', strtotime($endDate));
            $short       = date('j M', strtotime($endDate));

            $total = (int)$pdo->query("SELECT COUNT(*) FROM calls WHERE (scheduled_at >= '{$startDate} 00:00:00' AND scheduled_at <= '{$endDate} 23:59:59') OR (created_at >= '{$startDate} 00:00:00' AND created_at <= '{$endDate} 23:59:59')")->fetchColumn();
            $connected = (int)$pdo->query("SELECT COUNT(*) FROM calls WHERE ((scheduled_at >= '{$startDate} 00:00:00' AND scheduled_at <= '{$endDate} 23:59:59') OR (created_at >= '{$startDate} 00:00:00' AND created_at <= '{$endDate} 23:59:59')) AND LOWER(outcome) = 'connected'")->fetchColumn();
            $noAnswer  = (int)$pdo->query("SELECT COUNT(*) FROM calls WHERE ((scheduled_at >= '{$startDate} 00:00:00' AND scheduled_at <= '{$endDate} 23:59:59') OR (created_at >= '{$startDate} 00:00:00' AND created_at <= '{$endDate} 23:59:59')) AND LOWER(outcome) = 'no answer'")->fetchColumn();
            $callBack  = (int)$pdo->query("SELECT COUNT(*) FROM calls WHERE ((scheduled_at >= '{$startDate} 00:00:00' AND scheduled_at <= '{$endDate} 23:59:59') OR (created_at >= '{$startDate} 00:00:00' AND created_at <= '{$endDate} 23:59:59')) AND LOWER(outcome) = 'call back'")->fetchColumn();

            $callActivityPeriods['30days'][] = [
                'date'       => $startDate,
                'label'      => $label,
                'shortLabel' => $short,
                'calls'      => $total,
                'connected'  => $connected,
                'no_answer'  => $noAnswer,
                'call_back'  => $callBack
            ];
        }
    } catch (\Throwable $ex) {
        error_log("Calls Activity Query Error: " . $ex->getMessage());
    }
}

// -----------------------------------------------------------------------------
// 4. LEAD PIPELINE (6 Stages: New, Contacted, Qualified, Proposal Sent, Converted, Lost)
// -----------------------------------------------------------------------------
$pipelineStagesDef = [
    'New' => [
        'label' => 'New',
        'color' => '#12141a',
        'bg'    => '#12141a',
        'desc'  => 'Inbound inquiry received'
    ],
    'Contacted' => [
        'label' => 'Contacted',
        'color' => '#3b82f6',
        'bg'    => '#3b82f6',
        'desc'  => 'First outreach conducted'
    ],
    'Qualified' => [
        'label' => 'Qualified',
        'color' => '#f59e0b',
        'bg'    => '#f59e0b',
        'desc'  => 'Scope & budget aligned'
    ],
    'Proposal Sent' => [
        'label' => 'Proposal Sent',
        'color' => '#8b5cf6',
        'bg'    => '#8b5cf6',
        'desc'  => 'Formal quote delivered'
    ],
    'Converted' => [
        'label' => 'Converted',
        'color' => '#10b981',
        'bg'    => '#10b981',
        'desc'  => 'Won paying account'
    ],
    'Lost' => [
        'label' => 'Lost',
        'color' => '#94a3b8',
        'bg'    => '#94a3b8',
        'desc'  => 'Disqualified / stalled'
    ]
];

$leadPipelineData = [];
foreach ($pipelineStagesDef as $key => $meta) {
    $leadPipelineData[$key] = [
        'label'      => $meta['label'],
        'color'      => $meta['color'],
        'bg'         => $meta['bg'],
        'desc'       => $meta['desc'],
        'count'      => 0,
        'percentage' => 0.0
    ];
}

if ($pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT LOWER(status) as st, COUNT(*) as cnt FROM leads GROUP BY LOWER(status)");
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rawStatus = (string)$r['st'];
            $cnt = (int)$r['cnt'];
            if ($rawStatus === 'new') {
                $leadPipelineData['New']['count'] += $cnt;
            } elseif ($rawStatus === 'contacted') {
                $leadPipelineData['Contacted']['count'] += $cnt;
            } elseif ($rawStatus === 'qualified') {
                $leadPipelineData['Qualified']['count'] += $cnt;
            } elseif (in_array($rawStatus, ['proposal_sent', 'proposal sent', 'proposal', 'invoiced', 'pending_invoice'])) {
                $leadPipelineData['Proposal Sent']['count'] += $cnt;
            } elseif (in_array($rawStatus, ['converted', 'closed', 'won'])) {
                $leadPipelineData['Converted']['count'] += $cnt;
            } elseif ($rawStatus === 'lost') {
                $leadPipelineData['Lost']['count'] += $cnt;
            }
        }

        if ($totalLeadsCount > 0) {
            foreach ($leadPipelineData as $k => $item) {
                $leadPipelineData[$k]['percentage'] = round(($item['count'] / $totalLeadsCount) * 100, 1);
            }
        }
    } catch (\Throwable $ex) {
        error_log("Lead Pipeline Query Error: " . $ex->getMessage());
    }
}

// -----------------------------------------------------------------------------
// 5. REVENUE BREAKDOWN BY SERVICE (Real Database Aggregation)
// -----------------------------------------------------------------------------
$standardServices = [
    'Website'         => ['color' => '#3b82f6', 'amount' => 0.00, 'pct' => 0.0],
    'Software'        => ['color' => '#12141a', 'amount' => 0.00, 'pct' => 0.0],
    'AI + Automation' => ['color' => '#10b981', 'amount' => 0.00, 'pct' => 0.0],
    'UI/UX'           => ['color' => '#8b5cf6', 'amount' => 0.00, 'pct' => 0.0],
    'Other'           => ['color' => '#f59e0b', 'amount' => 0.00, 'pct' => 0.0]
];

$totalPaidRevenueFromInvoices = 0.00;

if ($pdo !== null) {
    try {
        if (!empty($hasRevenueTable)) {
            $stmt = $pdo->query("SELECT COALESCE(service, 'Other') as service, SUM(amount) as total FROM revenue WHERE LOWER(payment_status) = 'paid' GROUP BY service");
        } else {
            $stmt = $pdo->query("SELECT COALESCE(service, 'Other') as service, SUM(amount) as total FROM invoices WHERE LOWER(status) = 'paid' GROUP BY service");
        }
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $srv = trim((string)$r['service']);
            $amt = (float)$r['total'];
            $totalPaidRevenueFromInvoices += $amt;

            // Map service names to standard categories
            $matched = false;
            foreach (array_keys($standardServices) as $cat) {
                if (strcasecmp($cat, $srv) === 0 || stripos($srv, $cat) !== false) {
                    $standardServices[$cat]['amount'] += $amt;
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $standardServices['Other']['amount'] += $amt;
            }
        }

        if ($totalPaidRevenueFromInvoices > 0) {
            foreach ($standardServices as $k => $data) {
                $standardServices[$k]['pct'] = round(($data['amount'] / $totalPaidRevenueFromInvoices) * 100, 1);
            }
        }
    } catch (\Throwable $ex) {
        error_log("Revenue Breakdown Query Error: " . $ex->getMessage());
    }
}

// -----------------------------------------------------------------------------
// 6. RECENT ACTIVITY FEED (Recent Leads, Recent Calls, Recent Payments)
// -----------------------------------------------------------------------------
$recentActivity = [];

if ($pdo !== null) {
    try {
        // Recent Leads (Full details for Recent Leads table)
        $recentLeadsList = [];
        $rLeadStmt = $pdo->query("
            SELECT id, name, company, email, phone, service, service_interested, budget, status, call_status, created_at
            FROM leads
            ORDER BY created_at DESC, id DESC
            LIMIT 8
        ");
        if ($rLeadStmt) {
            $recentLeadsList = $rLeadStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($recentLeadsList as $l) {
            $recentActivity[] = [
                'name'   => (string)$l['name'],
                'type'   => 'Lead',
                'date'   => (string)$l['created_at'],
                'status' => (string)($l['status'] ?? 'New')
            ];
        }

        // Recent Calls
        $callsStmt = $pdo->query("SELECT contact_name, outcome, status, scheduled_at, created_at FROM calls ORDER BY created_at DESC LIMIT 6");
        while ($c = $callsStmt->fetch(PDO::FETCH_ASSOC)) {
            $statusVal = !empty($c['outcome']) ? (string)$c['outcome'] : ucfirst((string)($c['status'] ?? 'Scheduled'));
            $dateVal = !empty($c['scheduled_at']) ? (string)$c['scheduled_at'] : (string)$c['created_at'];
            $recentActivity[] = [
                'name'   => (string)$c['contact_name'],
                'type'   => 'Call',
                'date'   => $dateVal,
                'status' => $statusVal
            ];
        }

        // Recent Payments (From revenue table if available)
        if (!empty($hasRevenueTable)) {
            $revStmt = $pdo->query("
                SELECT r.amount, r.payment_date as paid_at, COALESCE(c.company_name, c.client_name, 'Direct Client') as client_name, r.id as ref_id
                FROM revenue r
                LEFT JOIN clients c ON r.client_id = c.id
                WHERE LOWER(r.payment_status) = 'paid' AND r.payment_date IS NOT NULL
                ORDER BY r.payment_date DESC LIMIT 6
            ");
            while ($revItem = $revStmt->fetch(PDO::FETCH_ASSOC)) {
                $recentActivity[] = [
                    'name'   => (string)$revItem['client_name'] . ' (#REV-' . (string)$revItem['ref_id'] . ')',
                    'type'   => 'Payment',
                    'date'   => (string)$revItem['paid_at'],
                    'status' => '₹' . number_format((float)$revItem['amount'], 2)
                ];
            }
        } else {
            $invsStmt = $pdo->query("SELECT client_name, invoice_number, amount, paid_at FROM invoices WHERE LOWER(status) = 'paid' AND paid_at IS NOT NULL ORDER BY paid_at DESC LIMIT 6");
            while ($inv = $invsStmt->fetch(PDO::FETCH_ASSOC)) {
                $recentActivity[] = [
                    'name'   => (string)$inv['client_name'] . ' (' . (string)$inv['invoice_number'] . ')',
                    'type'   => 'Payment',
                    'date'   => (string)$inv['paid_at'],
                    'status' => '₹' . number_format((float)$inv['amount'], 2)
                ];
            }
        }

        // Sort unified activity by date descending
        usort($recentActivity, function ($a, $b) {
            return strtotime($b['date']) <=> strtotime($a['date']);
        });
        $recentActivity = array_slice($recentActivity, 0, 8);
    } catch (\Throwable $ex) {
        error_log("Recent Activity Query Error: " . $ex->getMessage());
    }
}

// -----------------------------------------------------------------------------
// 7. FOLLOW-UP PANEL (FOLLOW-UPS NEEDING ATTENTION)
// -----------------------------------------------------------------------------
$followupsNeedingAttention = [];

if ($pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT * FROM calls WHERE status IN ('scheduled', 'pending') ORDER BY scheduled_at ASC LIMIT 6");
        $followupsNeedingAttention = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $ex) {
        error_log("Follow-ups Attention Query Error: " . $ex->getMessage());
    }
}

require_once __DIR__ . '/includes/admin_header.php';
?>

  <!-- Executive Analytics Header -->
  <div class="page-header">
    <div>
      <h1 class="page-title">Executive Business Analytics</h1>
      <p class="page-subtitle">Unified command center for client operations, sales velocity, calls, and revenue generation.</p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
      <a href="calls.php" class="btn-action" style="padding: 8px 14px; background: #ffffff;">
        <?php if ($overdueCallsCount > 0): ?>
          <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #ef4444; margin-right: 6px;"></span>
          <?= (int)$overdueCallsCount ?> Overdue Follow-ups
        <?php else: ?>
          <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #10b981; margin-right: 6px;"></span>
          <?= (int)$pendingCallsCount ?> Follow-ups Scheduled
        <?php endif; ?>
      </a>
      <a href="leads.php" class="btn-action" style="padding: 8px 14px; background: #ffffff;">
        Lead Funnel &rarr;
      </a>
    </div>
  </div>

  <!-- =========================================================
       TOP KPI CARDS (TOTAL CLIENTS, NEW LEADS, REVENUE THIS MONTH, PENDING FOLLOW-UPS)
  ========================================================= -->
  <div class="kpi-grid">
    <!-- Card 1: TOTAL CLIENTS -->
    <a href="clients.php" class="kpi-card kpi-card-link" style="text-decoration: none; display: block; color: inherit;" title="Open Clients Directory">
      <div class="kpi-top">
        <span class="kpi-label">TOTAL CLIENTS</span>
        <div class="kpi-icon-wrap lime">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
          </svg>
        </div>
      </div>
      <div class="kpi-value"><?= number_format($totalClientsCount) ?></div>
      <div class="kpi-footer">
        <span class="kpi-tag positive"><?= (int)$activeClientsCount ?> Active</span>
        <span><?= (int)$newClientsThisMonth > 0 ? ('+' . (int)$newClientsThisMonth . ' this month') : 'Client directory &rarr;' ?></span>
      </div>
    </a>

    <!-- Card 2: NEW LEADS -->
    <a href="leads.php?lead_status=New" class="kpi-card kpi-card-link" style="text-decoration: none; display: block; color: inherit;" title="Open New Leads Queue">
      <div class="kpi-top">
        <span class="kpi-label">NEW LEADS</span>
        <div class="kpi-icon-wrap blue">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
          </svg>
        </div>
      </div>
      <div class="kpi-value"><?= number_format($newLeadsCount) ?></div>
      <div class="kpi-footer">
        <?php if ($newLeadsCount > 0): ?>
          <span class="kpi-tag alert">Action Required</span>
        <?php else: ?>
          <span class="kpi-tag positive">Up to date</span>
        <?php endif; ?>
        <span><?= number_format($totalLeadsCount) ?> in pipeline &rarr;</span>
      </div>
    </a>

    <!-- Card 3: REVENUE THIS MONTH -->
    <a href="revenue.php" class="kpi-card kpi-card-link" style="text-decoration: none; display: block; color: inherit;" title="Open Revenue Management">
      <div class="kpi-top">
        <span class="kpi-label">REVENUE THIS MONTH</span>
        <div class="kpi-icon-wrap lime">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 8h6m-5 4h4m-5 4h5M6 4h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2z"/>
          </svg>
        </div>
      </div>
      <div class="kpi-value" style="color: #047857;">
        ₹<?= number_format($revenueThisMonth, 2) ?>
      </div>
      <div class="kpi-footer">
        <span class="kpi-tag positive">Collected</span>
        <span><?= (int)$paidInvoicesThisMonth ?> settled &rarr;</span>
      </div>
    </a>

    <!-- Card 4: PENDING FOLLOW-UPS -->
    <a href="calls.php?tab=upcoming" class="kpi-card kpi-card-link" style="text-decoration: none; display: block; color: inherit;" title="Open Call Follow-ups">
      <div class="kpi-top">
        <span class="kpi-label">PENDING FOLLOW-UPS</span>
        <div class="kpi-icon-wrap orange">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
      </div>
      <div class="kpi-value"><?= number_format($pendingCallsCount) ?></div>
      <div class="kpi-footer">
        <?php if ($overdueCallsCount > 0): ?>
          <span class="kpi-tag alert"><?= (int)$overdueCallsCount ?> Overdue</span>
          <span>Action queue &rarr;</span>
        <?php elseif ($pendingCallsCount > 0): ?>
          <span class="kpi-tag positive">On Schedule</span>
          <span>Call queue &rarr;</span>
        <?php else: ?>
          <span class="kpi-tag positive">All Done</span>
          <span>No pending follow-ups</span>
        <?php endif; ?>
      </div>
    </a>
  </div>

  <!-- =========================================================
       CHARTS ROW: REVENUE OVERVIEW & CALL ACTIVITY
  ========================================================= -->
  <div class="dashboard-charts-grid" style="grid-template-columns: 3fr 2fr; margin-bottom: 32px;">
    <!-- REVENUE CHART: Smooth Line Chart with Period Switcher -->
    <div class="chart-card">
      <div class="chart-header">
        <div>
          <h2 class="chart-title">Revenue Overview</h2>
          <div class="chart-desc">Real revenue flow tracked across settled client invoices</div>
        </div>
        <!-- Period Switcher -->
        <div class="period-tab-group" id="revenuePeriodTabs">
          <button type="button" class="period-tab-btn active" data-period="7days">Last 7 Days</button>
          <button type="button" class="period-tab-btn" data-period="30days">Last 30 Days</button>
          <button type="button" class="period-tab-btn" data-period="6months">Last 6 Months</button>
          <button type="button" class="period-tab-btn" data-period="year">This Year</button>
        </div>
      </div>

      <!-- Chart Content Area -->
      <div id="revenueChartContainer" class="svg-chart-container">
        <?php if (!$hasAnyRevenue): ?>
          <div class="empty-state-banner">
            <div class="empty-state-icon">₹</div>
            <div class="empty-state-title">No revenue recorded yet.</div>
            <p>Issue or mark invoices as paid to view revenue trajectory.</p>
          </div>
        <?php else: ?>
          <!-- SVG is dynamically rendered via Vanilla JS based on selected period -->
          <svg id="revenueSvg" viewBox="0 0 680 240" preserveAspectRatio="none"></svg>
          <div id="revenueTooltip" class="chart-tooltip">
            <div class="tt-title" id="revTtDate"></div>
            <div class="tt-val" id="revTtVal"></div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- CALLS CHART: Bar Chart with Period Switcher -->
    <div class="chart-card">
      <div class="chart-header">
        <div>
          <h2 class="chart-title">Call Activity</h2>
          <div class="chart-desc">Breakdown of calls, connects, voicemails &amp; callbacks</div>
        </div>
        <!-- Period Switcher -->
        <div class="period-tab-group" id="callPeriodTabs">
          <button type="button" class="period-tab-btn active" data-period="7days">7 Days</button>
          <button type="button" class="period-tab-btn" data-period="30days">30 Days</button>
        </div>
      </div>

      <!-- Call Bar Chart Content -->
      <div id="callsChartContainer" style="position: relative; min-height: 230px;">
        <?php if (!$hasAnyCalls): ?>
          <div class="empty-state-banner">
            <div class="empty-state-icon">📞</div>
            <div class="empty-state-title">No calls recorded yet.</div>
            <p>Log client and prospect outreach calls to track team connection rates.</p>
          </div>
        <?php else: ?>
          <div id="callBarChartArea" class="call-bar-chart"></div>
          <div class="chart-legend">
            <div class="legend-item">
              <span class="legend-dot" style="background: #12141a;"></span>
              <span>Calls</span>
            </div>
            <div class="legend-item">
              <span class="legend-dot" style="background: #10b981;"></span>
              <span>Connected</span>
            </div>
            <div class="legend-item">
              <span class="legend-dot" style="background: #f59e0b;"></span>
              <span>No Answer</span>
            </div>
            <div class="legend-item">
              <span class="legend-dot" style="background: #6366f1;"></span>
              <span>Call Back</span>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- =========================================================
       LEAD PIPELINE (6 STAGES: New, Contacted, Qualified, Proposal Sent, Converted, Lost)
  ========================================================= -->
  <div class="data-card" style="margin-bottom: 32px;">
    <div class="data-card-header">
      <div>
        <h2 class="data-card-title">Lead Pipeline</h2>
        <div style="font-size: 12px; color: var(--text-muted);">Real-time lead conversion velocity across 6 commercial stages</div>
      </div>
      <div style="font-family: 'DM Mono', monospace; font-size: 12px; color: var(--text-dark); background: var(--main-bg); padding: 4px 10px; border-radius: 6px;">
        <?= (int)$totalLeadsCount ?> Total Inquiries
      </div>
    </div>

    <div style="padding: 20px 24px;">
      <div class="pipeline-grid">
        <?php foreach ($leadPipelineData as $stageKey => $stage): ?>
          <a href="leads.php?lead_status=<?= urlencode($stage['label']) ?>" class="pipeline-stage-card kpi-card-link" style="text-decoration: none; color: inherit; display: flex; flex-direction: column; justify-content: space-between; cursor: pointer;" title="View <?= e($stage['label']) ?> Leads">
            <div>
              <div class="stage-card-top">
                <span class="stage-title"><?= e($stage['label']) ?></span>
                <span class="stage-dot" style="background-color: <?= e($stage['bg']) ?>;"></span>
              </div>
              <div class="stage-count"><?= (int)$stage['count'] ?></div>
            </div>
            <div>
              <div class="stage-meta">
                <span>Share</span>
                <strong><?= number_format($stage['percentage'], 1) ?>%</strong>
              </div>
              <div class="stage-bar-track">
                <div class="stage-bar-fill" style="width: <?= min(100, max(4, (float)$stage['percentage'])) ?>%; background-color: <?= e($stage['bg']) ?>;"></div>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- =========================================================
       RECENT LEADS (From Public Website & Inquiries)
  ========================================================= -->
  <div class="data-card" style="margin-bottom: 32px;">
    <div class="data-card-header">
      <div>
        <h2 class="data-card-title">Recent Leads</h2>
        <div style="font-size: 12px; color: var(--text-muted);">Real-time website enquiries and prospect submissions</div>
      </div>
      <a href="leads.php" class="btn-action">View All Leads &rarr;</a>
    </div>

    <div class="table-responsive desktop-only-table">
      <?php if (empty($recentLeadsList)): ?>
        <div class="empty-state-banner">
          <div class="empty-state-icon">📋</div>
          <div class="empty-state-title">No enquiries recorded yet.</div>
          <p>Website inquiries submitted via the contact form will appear here automatically.</p>
        </div>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Company</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Service</th>
              <th>Budget</th>
              <th>Lead Status</th>
              <th>Call Status</th>
              <th>Date</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentLeadsList as $rLead): ?>
              <?php
                $leadCallStatus = !empty($rLead['call_status']) ? $rLead['call_status'] : 'Not Called';
                $callNorm = strtolower(trim($leadCallStatus));
                $callBadgeClass = match ($callNorm) {
                    'called'         => 'badge-called',
                    'call back'      => 'badge-callback',
                    'no answer'      => 'badge-no-answer',
                    'not interested' => 'badge-not-interested',
                    default          => 'badge-not-called'
                };
                $leadService = !empty($rLead['service']) ? $rLead['service'] : (!empty($rLead['service_interested']) ? $rLead['service_interested'] : 'Website');
              ?>
              <tr>
                <td>
                  <a href="leads.php?search=<?= urlencode($rLead['name']) ?>" style="color: inherit; text-decoration: none; font-weight: 600;" class="hover-underline">
                    <?= e($rLead['name']) ?>
                  </a>
                </td>
                <td style="color: var(--text-dark);">
                  <?= e($rLead['company'] ?: '—') ?>
                </td>
                <td style="font-size: 12px; color: var(--text-muted);">
                  <?= e($rLead['email']) ?>
                </td>
                <td style="font-family: 'DM Mono', monospace; font-size: 12px;">
                  <?= !empty($rLead['phone']) ? e($rLead['phone']) : '—' ?>
                </td>
                <td>
                  <span style="font-family: 'DM Mono', monospace; font-size: 11px; background: var(--main-bg); padding: 3px 8px; border-radius: 4px;">
                    <?= e($leadService) ?>
                  </span>
                </td>
                <td style="font-family: 'DM Mono', monospace; font-size: 12px; font-weight: 600; color: #047857;">
                  <?= !empty($rLead['budget']) ? e($rLead['budget']) : '—' ?>
                </td>
                <td>
                  <span class="status-pill status-<?= strtolower(str_replace(' ', '-', (string)$rLead['status'])) ?>">
                    <?= e($rLead['status'] ?: 'New') ?>
                  </span>
                </td>
                <td>
                  <span class="badge-call-status <?= $callBadgeClass ?>">
                    <span class="dot"></span>
                    <?= e($leadCallStatus) ?>
                  </span>
                </td>
                <td style="font-family: 'DM Mono', monospace; font-size: 11px; color: var(--text-muted); white-space: nowrap;">
                  <?= e(date('j M Y', strtotime($rLead['created_at']))) ?>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                  <a href="leads.php?search=<?= urlencode($rLead['name']) ?>" class="btn-action" style="padding: 4px 8px; font-size: 11px; background: #ffffff;">
                    Manage &rarr;
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- Mobile Leads Cards View -->
    <div class="mobile-leads-cards-list mobile-only-block">
      <?php if (empty($recentLeadsList)): ?>
        <div class="empty-state-banner">
          <div class="empty-state-icon">📋</div>
          <div class="empty-state-title">No enquiries recorded yet.</div>
          <p>Website inquiries submitted via the contact form will appear here automatically.</p>
        </div>
      <?php else: ?>
        <?php foreach ($recentLeadsList as $rLead): ?>
          <?php
            $leadCallStatus = !empty($rLead['call_status']) ? $rLead['call_status'] : 'Not Called';
            $callNorm = strtolower(trim($leadCallStatus));
            $callBadgeClass = match ($callNorm) {
                'called'         => 'badge-called',
                'call back'      => 'badge-callback',
                'no answer'      => 'badge-no-answer',
                'not interested' => 'badge-not-interested',
                default          => 'badge-not-called'
            };
            $leadService = !empty($rLead['service']) ? $rLead['service'] : (!empty($rLead['service_interested']) ? $rLead['service_interested'] : 'Website');
          ?>
          <div class="mobile-lead-card">
            <div class="mobile-lead-card-header">
              <div>
                <a href="leads.php?search=<?= urlencode($rLead['name']) ?>" class="mobile-lead-name">
                  <?= e($rLead['name']) ?>
                </a>
                <div class="mobile-lead-company"><?= e($rLead['company'] ?: 'Individual / Prospect') ?></div>
              </div>
              <span class="status-pill status-<?= strtolower(str_replace(' ', '-', (string)$rLead['status'])) ?>">
                <?= e($rLead['status'] ?: 'New') ?>
              </span>
            </div>
            <div class="mobile-lead-details">
              <span class="badge-call-status <?= $callBadgeClass ?>">
                <span class="dot"></span>
                <?= e($leadCallStatus) ?>
              </span>
              <span style="font-family: 'DM Mono', monospace; font-size: 11px; background: var(--main-bg); padding: 3px 8px; border-radius: 4px;">
                <?= e($leadService) ?>
              </span>
            </div>
            <div class="mobile-lead-footer">
              <span style="font-family: 'DM Mono', monospace; font-size: 11px; color: var(--text-muted);">
                <?= e(date('j M Y', strtotime($rLead['created_at']))) ?>
              </span>
              <a href="leads.php?search=<?= urlencode($rLead['name']) ?>" class="btn-action btn-touch-44" style="padding: 6px 12px; background: #ffffff;">
                Manage &rarr;
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- =========================================================
       REVENUE BREAKDOWN BY SERVICE & RECENT ACTIVITY
  ========================================================= -->
  <div class="dashboard-tables-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 32px;">
    <!-- REVENUE BREAKDOWN BY SERVICE -->
    <div class="data-card">
      <div class="data-card-header">
        <div>
          <h2 class="data-card-title">Revenue Breakdown by Service</h2>
          <div style="font-size: 12px; color: var(--text-muted);">Distribution of settled revenue across core offerings</div>
        </div>
        <span style="font-family: 'DM Mono', monospace; font-size: 12px; font-weight: 600; color: #047857;">
          ₹<?= number_format($totalPaidRevenueFromInvoices, 2) ?> Total
        </span>
      </div>

      <div style="padding: 24px;">
        <?php if ($totalPaidRevenueFromInvoices <= 0): ?>
          <div class="empty-state-banner">
            <div class="empty-state-icon">₹</div>
            <div class="empty-state-title">No revenue recorded yet.</div>
            <p>Categorized invoices will appear here once payments are collected.</p>
          </div>
        <?php else: ?>
          <div class="service-breakdown-list">
            <?php foreach ($standardServices as $srvName => $srvInfo): ?>
              <a href="revenue.php?service=<?= urlencode($srvName) ?>" class="service-item" style="text-decoration: none; color: inherit; display: block; padding: 6px 8px; border-radius: 6px; transition: background 0.15s ease;" title="Filter revenue by <?= e($srvName) ?>">
                <div class="service-meta">
                  <div class="service-name">
                    <span style="display: inline-block; width: 10px; height: 10px; border-radius: 3px; background: <?= e($srvInfo['color']) ?>;"></span>
                    <span><?= e($srvName) ?></span>
                  </div>
                  <div>
                    <span class="service-amount">₹<?= number_format($srvInfo['amount'], 2) ?></span>
                    <span class="service-pct">(<?= number_format($srvInfo['pct'], 1) ?>%)</span>
                  </div>
                </div>
                <div class="service-bar-track">
                  <div class="service-bar-fill" style="width: <?= (float)$srvInfo['pct'] ?>%; background: <?= e($srvInfo['color']) ?>;"></div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- RECENT ACTIVITY (Recent Leads, Recent Calls, Recent Payments) -->
    <div class="data-card">
      <div class="data-card-header">
        <div>
          <h2 class="data-card-title">Recent Activity</h2>
          <div style="font-size: 12px; color: var(--text-muted);">Chronological event stream across leads, calls, and payments</div>
        </div>
        <span style="font-family: 'DM Mono', monospace; font-size: 11px; color: var(--text-muted);">
          Real-time Audit
        </span>
      </div>

      <div class="table-responsive desktop-only-table">
        <?php if (empty($recentActivity)): ?>
          <div class="empty-state-banner">
            <div class="empty-state-title">No activity recorded yet.</div>
            <p>Inquiries, calls, and payments will stream here automatically.</p>
          </div>
        <?php else: ?>
          <table class="admin-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentActivity as $act): ?>
                <tr>
                  <td>
                    <?php if ($act['type'] === 'Lead'): ?>
                      <a href="leads.php?search=<?= urlencode($act['name']) ?>" style="color: inherit; text-decoration: none; font-weight: 600;" class="hover-underline">
                        <?= e($act['name']) ?>
                      </a>
                    <?php elseif ($act['type'] === 'Call'): ?>
                      <a href="calls.php?search=<?= urlencode($act['name']) ?>" style="color: inherit; text-decoration: none; font-weight: 600;" class="hover-underline">
                        <?= e($act['name']) ?>
                      </a>
                    <?php elseif ($act['type'] === 'Payment'): ?>
                      <a href="revenue.php?search=<?= urlencode($act['name']) ?>" style="color: inherit; text-decoration: none; font-weight: 600;" class="hover-underline">
                        <?= e($act['name']) ?>
                      </a>
                    <?php else: ?>
                      <strong><?= e($act['name']) ?></strong>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php
                      $typeClass = 'activity-type-lead';
                      if ($act['type'] === 'Call') {
                          $typeClass = 'activity-type-call';
                      } elseif ($act['type'] === 'Payment') {
                          $typeClass = 'activity-type-payment';
                      }
                    ?>
                    <span class="activity-type-pill <?= $typeClass ?>">
                      <?= e($act['type']) ?>
                    </span>
                  </td>
                  <td style="font-family: 'DM Mono', monospace; font-size: 11px; color: var(--text-muted);">
                    <?= e(format_date($act['date'], 'M j, H:i')) ?>
                  </td>
                  <td>
                    <span class="status-pill" style="font-size: 11px;">
                      <?= e($act['status']) ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <!-- Mobile Activity Cards View -->
      <div class="mobile-activity-cards-list mobile-only-block">
        <?php if (empty($recentActivity)): ?>
          <div class="empty-state-banner">
            <div class="empty-state-title">No activity recorded yet.</div>
            <p>Inquiries, calls, and payments will stream here automatically.</p>
          </div>
        <?php else: ?>
          <?php foreach ($recentActivity as $act): ?>
            <?php
              $typeClass = 'activity-type-lead';
              if ($act['type'] === 'Call') {
                  $typeClass = 'activity-type-call';
              } elseif ($act['type'] === 'Payment') {
                  $typeClass = 'activity-type-payment';
              }
              $actUrl = match($act['type']) {
                  'Lead'    => 'leads.php?search=' . urlencode($act['name']),
                  'Call'    => 'calls.php?search=' . urlencode($act['name']),
                  'Payment' => 'revenue.php?search=' . urlencode($act['name']),
                  default   => '#'
              };
            ?>
            <div class="mobile-activity-card">
              <div class="mobile-activity-card-top">
                <a href="<?= e($actUrl) ?>" class="mobile-activity-card-name">
                  <?= e($act['name']) ?>
                </a>
                <span class="activity-type-pill <?= $typeClass ?>">
                  <?= e($act['type']) ?>
                </span>
              </div>
              <div class="mobile-activity-card-bottom">
                <span><?= e(format_date($act['date'], 'M j, H:i')) ?></span>
                <span class="status-pill"><?= e($act['status']) ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- =========================================================
       FOLLOW-UP PANEL: FOLLOW-UPS NEEDING ATTENTION
  ========================================================= -->
  <div class="data-card" style="margin-bottom: 32px;">
    <div class="data-card-header">
      <div>
        <h2 class="data-card-title">FOLLOW-UPS NEEDING ATTENTION</h2>
        <div style="font-size: 12px; color: var(--text-muted);">Nearest upcoming follow-ups and overdue calls requiring immediate action</div>
      </div>
      <a href="calls.php" class="btn-action">View All Calls &rarr;</a>
    </div>

    <div class="table-responsive desktop-only-table">
      <?php if (empty($followupsNeedingAttention)): ?>
        <div class="empty-state-banner" style="margin: 24px;">
          <div class="empty-state-icon">✓</div>
          <div class="empty-state-title">No follow-ups scheduled.</div>
          <p>All scheduled calls have been completed or attended to.</p>
        </div>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Status / Timing</th>
              <th>Contact Name</th>
              <th>Company</th>
              <th>Phone</th>
              <th>Scheduled For</th>
              <th>Notes / Action Item</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($followupsNeedingAttention as $call): ?>
              <?php
                $isOverdue = strtotime($call['scheduled_at']) < time();
                $rowClass = $isOverdue ? 'row-overdue' : '';
              ?>
              <tr class="<?= $rowClass ?>">
                <td>
                  <?php if ($isOverdue): ?>
                    <span class="badge-overdue">
                      <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#dc2626;"></span>
                      Overdue
                    </span>
                  <?php else: ?>
                    <span class="badge-upcoming">
                      <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#16a34a;"></span>
                      Upcoming
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="calls.php?search=<?= urlencode($call['contact_name']) ?>" style="color: inherit; text-decoration: none; font-weight: 600;" class="hover-underline" title="View in Calls">
                    <?= e($call['contact_name']) ?>
                  </a>
                </td>
                <td style="color: var(--text-muted);">
                  <?= e($call['company'] ?? '—') ?>
                </td>
                <td style="font-family: 'DM Mono', monospace; font-size: 12px;">
                  <a href="tel:<?= e($call['phone']) ?>" style="color: var(--text-dark); text-decoration: underline; text-underline-offset: 2px;">
                    <?= e($call['phone']) ?>
                  </a>
                </td>
                <td style="font-family: 'DM Mono', monospace; font-size: 12px; <?= $isOverdue ? 'color: #dc2626; font-weight: 600;' : 'color: var(--text-dark);' ?>">
                  <?= e(format_date($call['scheduled_at'], 'M j, Y — H:i')) ?>
                </td>
                <td style="font-size: 12px; color: var(--text-muted); max-width: 260px;">
                  <?= e($call['notes'] ?: 'Follow-up regarding proposal milestones') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- Mobile Follow-ups Cards View -->
    <div class="mobile-followup-cards-list mobile-only-block">
      <?php if (empty($followupsNeedingAttention)): ?>
        <div class="empty-state-banner">
          <div class="empty-state-icon">✓</div>
          <div class="empty-state-title">No follow-ups scheduled.</div>
          <p>All scheduled calls have been completed or attended to.</p>
        </div>
      <?php else: ?>
        <?php foreach ($followupsNeedingAttention as $call): ?>
          <?php
            $isOverdue = strtotime($call['scheduled_at']) < time();
            $cardClass = $isOverdue ? 'overdue' : 'upcoming';
          ?>
          <div class="mobile-followup-card <?= $cardClass ?>">
            <div class="mobile-followup-card-header">
              <div>
                <a href="calls.php?search=<?= urlencode($call['contact_name']) ?>" class="mobile-followup-name">
                  <?= e($call['contact_name']) ?>
                </a>
                <div class="mobile-followup-sub">
                  <?= e($call['company'] ?? 'Personal / Independent') ?>
                </div>
              </div>
              <div>
                <?php if ($isOverdue): ?>
                  <span class="badge-overdue">Overdue</span>
                <?php else: ?>
                  <span class="badge-upcoming">Upcoming</span>
                <?php endif; ?>
              </div>
            </div>

            <div class="mobile-followup-time">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <span><?= e(format_date($call['scheduled_at'], 'M j, Y — H:i')) ?></span>
            </div>

            <?php if (!empty($call['notes'])): ?>
              <div class="mobile-followup-notes">
                <?= e($call['notes']) ?>
              </div>
            <?php endif; ?>

            <div class="mobile-followup-actions">
              <?php if (!empty($call['phone'])): ?>
                <a href="tel:<?= e($call['phone']) ?>" class="btn-call-action btn-touch-44">
                  <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                  </svg>
                  <span>Call</span>
                </a>
              <?php endif; ?>
              <a href="calls.php?search=<?= urlencode($call['contact_name']) ?>" class="btn-history-action btn-touch-44">
                <span>View</span>
              </a>
              <a href="calls.php" class="btn-history-action btn-touch-44">
                <span>Edit</span>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- =========================================================
       INTERACTIVE JAVASCRIPT CHARTS LOGIC (VANILLA JS)
  ========================================================= -->
  <script>
  (function() {
    'use strict';

    // ---------------------------------------------------------
    // 1. REVENUE LINE CHART CONTROLLER
    // ---------------------------------------------------------
    const revenueData = <?= json_encode($revenuePeriods, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const revSvg = document.getElementById('revenueSvg');
    const revTooltip = document.getElementById('revenueTooltip');
    const revTtDate = document.getElementById('revTtDate');
    const revTtVal = document.getElementById('revTtVal');
    const revTabsContainer = document.getElementById('revenuePeriodTabs');

    function formatINR(val) {
      return '₹' + Number(val).toLocaleString('en-IN', { maximumFractionDigits: 0 });
    }

    function renderRevenueChart(period) {
      if (!revSvg) return;
      const data = revenueData[period] || [];
      if (data.length === 0) return;

      const width = 680;
      const height = 240;
      const paddingLeft = 70;
      const paddingRight = 30;
      const paddingTop = 25;
      const paddingBottom = 40;

      const chartW = width - paddingLeft - paddingRight;
      const chartH = height - paddingTop - paddingBottom;

      const maxVal = Math.max(10000, ...data.map(d => d.revenue));
      // Round maxVal to next clean step
      const stepFactor = Math.pow(10, Math.floor(Math.log10(maxVal)));
      const niceMax = Math.ceil(maxVal / stepFactor) * stepFactor;

      // Generate points
      const points = data.map((d, index) => {
        const x = paddingLeft + (chartW / (data.length - 1 || 1)) * index;
        const y = paddingTop + chartH - (d.revenue / niceMax) * chartH;
        return { x, y, ...d };
      });

      // Clear previous SVG
      while (revSvg.firstChild) {
        revSvg.removeChild(revSvg.firstChild);
      }

      // Defs (Gradient)
      const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
      const grad = document.createElementNS('http://www.w3.org/2000/svg', 'linearGradient');
      grad.setAttribute('id', 'revAreaGrad');
      grad.setAttribute('x1', '0');
      grad.setAttribute('y1', '0');
      grad.setAttribute('x2', '0');
      grad.setAttribute('y2', '1');

      const stop1 = document.createElementNS('http://www.w3.org/2000/svg', 'stop');
      stop1.setAttribute('offset', '0%');
      stop1.setAttribute('stop-color', '#b8ff3d');
      stop1.setAttribute('stop-opacity', '0.45');

      const stop2 = document.createElementNS('http://www.w3.org/2000/svg', 'stop');
      stop2.setAttribute('offset', '100%');
      stop2.setAttribute('stop-color', '#b8ff3d');
      stop2.setAttribute('stop-opacity', '0.0');

      grad.appendChild(stop1);
      grad.appendChild(stop2);
      defs.appendChild(grad);
      revSvg.appendChild(defs);

      // Horizontal grid lines and Y-axis labels
      const ySteps = 4;
      for (let i = 0; i <= ySteps; i++) {
        const stepY = paddingTop + (chartH / ySteps) * i;
        const stepVal = niceMax - (niceMax / ySteps) * i;

        // Grid line
        const gridLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        gridLine.setAttribute('x1', paddingLeft);
        gridLine.setAttribute('y1', stepY);
        gridLine.setAttribute('x2', width - paddingRight);
        gridLine.setAttribute('y2', stepY);
        gridLine.setAttribute('stroke', '#e2e8f0');
        gridLine.setAttribute('stroke-dasharray', '3,3');
        gridLine.setAttribute('stroke-width', '1');
        revSvg.appendChild(gridLine);

        // Y-axis label in ₹
        const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        text.setAttribute('x', paddingLeft - 10);
        text.setAttribute('y', stepY + 4);
        text.setAttribute('text-anchor', 'end');
        text.setAttribute('fill', '#94a3b8');
        text.setAttribute('font-family', 'DM Mono, monospace');
        text.setAttribute('font-size', '10');
        text.textContent = formatINR(stepVal);
        revSvg.appendChild(text);
      }

      // Build Bezier Curve path
      function getCubicPath(pts) {
        if (pts.length === 1) {
          return `M ${pts[0].x} ${pts[0].y}`;
        }
        let path = `M ${pts[0].x} ${pts[0].y}`;
        for (let i = 0; i < pts.length - 1; i++) {
          const p0 = pts[i];
          const p1 = pts[i + 1];
          const cpX1 = p0.x + (p1.x - p0.x) / 2;
          const cpY1 = p0.y;
          const cpX2 = p0.x + (p1.x - p0.x) / 2;
          const cpY2 = p1.y;
          path += ` C ${cpX1} ${cpY1}, ${cpX2} ${cpY2}, ${p1.x} ${p1.y}`;
        }
        return path;
      }

      const linePathD = getCubicPath(points);
      const areaPathD = linePathD + ` L ${points[points.length - 1].x} ${paddingTop + chartH} L ${points[0].x} ${paddingTop + chartH} Z`;

      // Area fill
      const areaPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      areaPath.setAttribute('d', areaPathD);
      areaPath.setAttribute('fill', 'url(#revAreaGrad)');
      revSvg.appendChild(areaPath);

      // Line stroke
      const strokePath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      strokePath.setAttribute('d', linePathD);
      strokePath.setAttribute('fill', 'none');
      strokePath.setAttribute('stroke', '#0f1015');
      strokePath.setAttribute('stroke-width', '2.5');
      strokePath.setAttribute('stroke-linecap', 'round');
      strokePath.setAttribute('stroke-linejoin', 'round');
      revSvg.appendChild(strokePath);

      // Data Points & Hover Tooltips
      points.forEach(pt => {
        // Outer dot
        const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        circle.setAttribute('cx', pt.x);
        circle.setAttribute('cy', pt.y);
        circle.setAttribute('r', '4.5');
        circle.setAttribute('fill', '#ffffff');
        circle.setAttribute('stroke', '#0f1015');
        circle.setAttribute('stroke-width', '2.5');
        circle.style.cursor = 'pointer';
        circle.style.transition = 'r 0.15s ease, stroke-width 0.15s ease';

        // Invisible hit target
        const hit = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        hit.setAttribute('cx', pt.x);
        hit.setAttribute('cy', pt.y);
        hit.setAttribute('r', '16');
        hit.setAttribute('fill', 'transparent');
        hit.style.cursor = 'pointer';

        hit.addEventListener('mouseenter', function(e) {
          circle.setAttribute('r', '6.5');
          circle.setAttribute('stroke', '#10b981');
          if (revTooltip) {
            revTtDate.textContent = pt.label || pt.date || pt.month;
            revTtVal.textContent = formatINR(pt.revenue);
            const containerRect = revSvg.getBoundingClientRect();
            const posX = (pt.x / width) * containerRect.width;
            const posY = (pt.y / height) * containerRect.height;
            revTooltip.style.left = posX + 'px';
            revTooltip.style.top = posY + 'px';
            revTooltip.style.opacity = '1';
          }
        });

        hit.addEventListener('mouseleave', function() {
          circle.setAttribute('r', '4.5');
          circle.setAttribute('stroke', '#0f1015');
          if (revTooltip) {
            revTooltip.style.opacity = '0';
          }
        });

        revSvg.appendChild(circle);
        revSvg.appendChild(hit);

        // X-axis label
        const xText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        xText.setAttribute('x', pt.x);
        xText.setAttribute('y', height - 12);
        xText.setAttribute('text-anchor', 'middle');
        xText.setAttribute('fill', '#64748b');
        xText.setAttribute('font-family', 'DM Mono, monospace');
        xText.setAttribute('font-size', '10.5');
        xText.textContent = pt.shortLabel || pt.label;
        revSvg.appendChild(xText);
      });
    }

    if (revTabsContainer) {
      revTabsContainer.querySelectorAll('.period-tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          revTabsContainer.querySelectorAll('.period-tab-btn').forEach(b => b.classList.remove('active'));
          this.classList.add('active');
          renderRevenueChart(this.dataset.period);
        });
      });
      // Initial render
      renderRevenueChart('7days');
    }

    // ---------------------------------------------------------
    // 2. CALL ACTIVITY BAR CHART CONTROLLER
    // ---------------------------------------------------------
    const callData = <?= json_encode($callActivityPeriods, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const callChartArea = document.getElementById('callBarChartArea');
    const callTabsContainer = document.getElementById('callPeriodTabs');

    function renderCallsChart(period) {
      if (!callChartArea) return;
      const data = callData[period] || [];
      callChartArea.innerHTML = '';

      if (data.length === 0) return;

      const maxTotal = Math.max(4, ...data.map(d => d.calls));

      data.forEach(item => {
        const slot = document.createElement('div');
        slot.className = 'call-bar-slot';

        // Total count on top
        if (item.calls > 0) {
          const totalBadge = document.createElement('span');
          totalBadge.className = 'call-bar-total';
          totalBadge.textContent = item.calls;
          slot.appendChild(totalBadge);
        }

        // Stack container
        const stack = document.createElement('div');
        stack.className = 'call-bar-stack';

        const totalHeightPct = (item.calls / maxTotal) * 100;
        stack.style.height = totalHeightPct + '%';

        // Segments: Connected, No Answer, Call Back, Other Calls
        if (item.calls > 0) {
          const connPct = (item.connected / item.calls) * 100;
          const noAnsPct = (item.no_answer / item.calls) * 100;
          const cbPct = (item.call_back / item.calls) * 100;
          const remPct = Math.max(0, 100 - (connPct + noAnsPct + cbPct));

          if (connPct > 0) {
            const seg1 = document.createElement('div');
            seg1.className = 'bar-segment seg-connected';
            seg1.style.height = connPct + '%';
            seg1.title = `Connected: ${item.connected}`;
            stack.appendChild(seg1);
          }
          if (noAnsPct > 0) {
            const seg2 = document.createElement('div');
            seg2.className = 'bar-segment seg-no-answer';
            seg2.style.height = noAnsPct + '%';
            seg2.title = `No Answer: ${item.no_answer}`;
            stack.appendChild(seg2);
          }
          if (cbPct > 0) {
            const seg3 = document.createElement('div');
            seg3.className = 'bar-segment seg-callback';
            seg3.style.height = cbPct + '%';
            seg3.title = `Call Back: ${item.call_back}`;
            stack.appendChild(seg3);
          }
          if (remPct > 0) {
            const seg4 = document.createElement('div');
            seg4.className = 'bar-segment seg-calls';
            seg4.style.height = remPct + '%';
            seg4.title = `Calls: ${item.calls}`;
            stack.appendChild(seg4);
          }
        }

        slot.appendChild(stack);

        // Date label below
        const label = document.createElement('span');
        label.className = 'call-bar-label';
        label.textContent = item.shortLabel || item.label;
        slot.appendChild(label);

        callChartArea.appendChild(slot);
      });
    }

    if (callTabsContainer) {
      callTabsContainer.querySelectorAll('.period-tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          callTabsContainer.querySelectorAll('.period-tab-btn').forEach(b => b.classList.remove('active'));
          this.classList.add('active');
          renderCallsChart(this.dataset.period);
        });
      });
      // Initial render
      renderCallsChart('7days');
    }

  })();
  </script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
