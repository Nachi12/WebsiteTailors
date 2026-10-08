<?php
/**
 * Website Tailors Admin — CRM & Performance Reports (Phase 1)
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) { define('WebsiteTailors_INIT', true); }
require_once dirname(__DIR__) . '/includes/auth_guard.php';

$pageTitle = 'Performance Reports';
$breadcrumb = 'Reports';

// Acquisition Channels
$acquisitionChannels = [
    ['channel' => 'Direct Website Inquiries', 'count' => 14, 'percent' => 50, 'conversion' => '32%'],
    ['channel' => 'Client Referrals & Network', 'count' => 8, 'percent' => 28, 'conversion' => '65%'],
    ['channel' => 'Inbound Social & Portfolio', 'count' => 4, 'percent' => 14, 'conversion' => '25%'],
    ['channel' => 'Outreach & Partnerships', 'count' => 2, 'percent' => 8, 'conversion' => '15%']
];

// Quarterly CRM Summaries
$quarterlySummaries = [
    [
        'quarter' => '2026 Q2 (Current)',
        'leads' => 18,
        'calls' => 24,
        'clients_won' => 4,
        'revenue' => 48500.00,
        'conversion' => '22.2%',
        'status' => 'In Progress'
    ],
    [
        'quarter' => '2026 Q1',
        'leads' => 22,
        'calls' => 31,
        'clients_won' => 6,
        'revenue' => 62000.00,
        'conversion' => '27.3%',
        'status' => 'Completed'
    ],
    [
        'quarter' => '2025 Q4',
        'leads' => 19,
        'calls' => 28,
        'clients_won' => 5,
        'revenue' => 54000.00,
        'conversion' => '26.3%',
        'status' => 'Completed'
    ]
];

require_once dirname(__DIR__) . '/includes/admin_header.php';
?>

  <div class="page-header">
    <div>
      <h1 class="page-title">Reports &amp; Performance</h1>
      <p class="page-subtitle">Executive summaries of lead conversion rates, client retention, and acquisition channels.</p>
    </div>
    <div style="font-family: 'DM Mono', monospace; font-size: 11px; color: var(--text-muted); background: #ffffff; border: 1px solid var(--border-light); padding: 8px 14px; border-radius: var(--radius-sm);">
      Phase 1 CRM Shell
    </div>
  </div>

  <!-- Reports KPI Grid -->
  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-top">
        <span class="kpi-label">Lead Conversion Rate</span>
        <div class="kpi-icon-wrap lime">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
          </svg>
        </div>
      </div>
      <div class="kpi-value">28.4%</div>
      <div class="kpi-footer">
        <span class="kpi-tag positive">+4.2% QoQ</span>
        <span>Inquiry to paying client</span>
      </div>
    </div>

    <div class="kpi-card">
      <div class="kpi-top">
        <span class="kpi-label">Average Deal Velocity</span>
        <div class="kpi-icon-wrap blue">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
      </div>
      <div class="kpi-value">14 Days</div>
      <div class="kpi-footer">
        <span class="kpi-tag positive">-3 days</span>
        <span>First inquiry to contract</span>
      </div>
    </div>

    <div class="kpi-card">
      <div class="kpi-top">
        <span class="kpi-label">Client Retention</span>
        <div class="kpi-icon-wrap purple">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
          </svg>
        </div>
      </div>
      <div class="kpi-value">98.5%</div>
      <div class="kpi-footer">
        <span class="kpi-tag positive">Studio Standard</span>
        <span>Active recurring contracts</span>
      </div>
    </div>

    <div class="kpi-card">
      <div class="kpi-top">
        <span class="kpi-label">Target Completion</span>
        <div class="kpi-icon-wrap orange">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
          </svg>
        </div>
      </div>
      <div class="kpi-value">114%</div>
      <div class="kpi-footer">
        <span class="kpi-tag positive">Exceeding Goal</span>
        <span>Revenue target on track</span>
      </div>
    </div>
  </div>

  <!-- Channel Distribution Grid -->
  <div class="chart-card" style="margin-bottom: 32px;">
    <div class="chart-header">
      <div>
        <h2 class="chart-title">Client Acquisition by Channel</h2>
        <div class="chart-desc">Where high-value studio leads originate</div>
      </div>
    </div>

    <div class="status-dist-list">
      <?php foreach ($acquisitionChannels as $ch): ?>
        <div class="dist-item" style="margin-bottom: 16px;">
          <div class="dist-meta" style="display: flex; justify-content: space-between; margin-bottom: 6px;">
            <div>
              <strong style="font-size: 13px; color: var(--text-dark);"><?= e($ch['channel']) ?></strong>
              <span style="font-size: 11px; color: var(--text-muted); margin-left: 6px;">(<?= e($ch['conversion']) ?> conv rate)</span>
            </div>
            <span style="font-family: 'DM Mono', monospace; font-weight: 600; font-size: 12px; color: var(--text-dark);">
              <?= (int)$ch['count'] ?> inquiries (<?= (int)$ch['percent'] ?>%)
            </span>
          </div>
          <div class="dist-bar-track" style="height: 10px; background: #e2e8f0; border-radius: 999px; overflow: hidden;">
            <div class="dist-bar-fill" style="width: <?= (int)$ch['percent'] ?>%; height: 100%; background-color: var(--primary); border-radius: 999px;"></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Quarterly Table -->
  <div class="data-card">
    <div class="data-card-header">
      <div>
        <h2 class="data-card-title">Quarterly Studio Performance Summary</h2>
        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
          Historical overview of business growth and conversion efficiency
        </div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Quarter</th>
            <th>Total Inquiries</th>
            <th>Calls Logged</th>
            <th>Clients Closed</th>
            <th>Total Revenue</th>
            <th>Conversion Rate</th>
            <th>Quarter Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($quarterlySummaries as $q): ?>
            <tr>
              <td><strong><?= e($q['quarter']) ?></strong></td>
              <td style="font-family: 'DM Mono', monospace; font-size: 12px;"><?= (int)$q['leads'] ?></td>
              <td style="font-family: 'DM Mono', monospace; font-size: 12px;"><?= (int)$q['calls'] ?></td>
              <td style="font-family: 'DM Mono', monospace; font-size: 12px;"><?= (int)$q['clients_won'] ?></td>
              <td style="font-family: 'DM Mono', monospace; font-weight: 600; font-size: 13px; color: var(--text-dark);">
                ₹<?= number_format((float)$q['revenue'], 2) ?>
              </td>
              <td style="font-family: 'DM Mono', monospace; font-size: 12px; color: var(--success); font-weight: 600;">
                <?= e($q['conversion']) ?>
              </td>
              <td>
                <span class="status-pill status-<?= $q['status'] === 'Completed' ? 'published' : 'qualified' ?>">
                  <?= e($q['status']) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>
