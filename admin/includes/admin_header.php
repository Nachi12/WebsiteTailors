<?php
/**
 * Website Tailors - Admin Panel Header & Layout Shell
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) {
    die('Direct access not permitted.');
}

$currentAdmin = current_admin();
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$pageTitle = $pageTitle ?? 'Admin Control Center';
$breadcrumb = $breadcrumb ?? 'Dashboard';

// Check unread leads for sidebar badge
$unreadLeadsCount = 0;
try {
    $db = Database::getInstance();
    if ($db->isConnected()) {
        $unreadLeadsCount = (int)$db->fetchColumn("SELECT COUNT(*) FROM leads WHERE status = 'new'");
    } else {
        $unreadLeadsCount = 4;
    }
} catch (\Throwable) {
    $unreadLeadsCount = 4;
}

// Admin initials for avatar
$adminName = $currentAdmin['full_name'] ?? ($currentAdmin['username'] ?? 'Admin');
$nameParts = explode(' ', trim($adminName));
$initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($pageTitle) ?> — Website Tailors CRM</title>
  <meta name="robots" content="noindex, nofollow" />
  
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet" />

  <!-- Admin Stylesheet -->
  <link rel="stylesheet" href="<?= e(ASSETS_URL . '/css/admin.css') ?>" />
  <script>
    (function() {
      try {
        if (localStorage.getItem('admin_sidebar_collapsed') === 'true' && window.innerWidth > 992) {
          document.documentElement.classList.add('sidebar-is-collapsed');
        }
      } catch(e) {}
    })();
  </script>
</head>
<body>

<div class="admin-layout">
  <!-- Mobile Off-Canvas Backdrop -->
  <div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

  <!-- =======================================================
       DARK SIDEBAR
  ======================================================== -->
  <aside class="sidebar" id="adminSidebar">
    <div class="sidebar-header">
      <a href="<?= e(ADMIN_URL . '/index.php') ?>" class="sidebar-brand">
        <span>WEBSITE TAILORS</span>
        <span class="brand-dot"></span>
        <span class="brand-badge">CRM</span>
      </a>
      <button class="sidebar-close-btn" id="sidebarClose" aria-label="Close sidebar">✕</button>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-group-title">Main</div>

      <a href="<?= e(ADMIN_URL . '/index.php') ?>" class="sidebar-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" data-tooltip="Dashboard">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
        <span>Dashboard</span>
      </a>

      <div class="nav-group-title" style="margin-top: 18px;">Clients</div>

      <a href="<?= e(ADMIN_URL . '/clients.php') ?>" class="sidebar-link <?= in_array($currentPage, ['clients.php'], true) ? 'active' : '' ?>" data-tooltip="Clients">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
        </svg>
        <span>Clients</span>
      </a>

      <a href="<?= e(ADMIN_URL . '/leads.php') ?>" class="sidebar-link <?= in_array($currentPage, ['leads.php'], true) ? 'active' : '' ?>" data-tooltip="Leads">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
        </svg>
        <span>Leads</span>
        <?php if ($unreadLeadsCount > 0): ?>
          <span class="sidebar-badge"><?= (int)$unreadLeadsCount ?></span>
        <?php endif; ?>
      </a>

      <a href="<?= e(ADMIN_URL . '/calls.php') ?>" class="sidebar-link <?= in_array($currentPage, ['calls.php'], true) ? 'active' : '' ?>" data-tooltip="Calls">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
        </svg>
        <span>Calls</span>
      </a>

      <div class="nav-group-title" style="margin-top: 18px;">Business</div>

      <a href="<?= e(ADMIN_URL . '/revenue.php') ?>" class="sidebar-link <?= in_array($currentPage, ['revenue.php'], true) ? 'active' : '' ?>" data-tooltip="Revenue">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>Revenue</span>
      </a>

      <a href="<?= e(ADMIN_URL . '/invoices.php') ?>" class="sidebar-link <?= in_array($currentPage, ['invoices.php'], true) ? 'active' : '' ?>" data-tooltip="Invoices">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <span>Invoices</span>
      </a>

      <div class="nav-group-title" style="margin-top: 18px;">Analytics</div>

      <a href="<?= e(ADMIN_URL . '/reports.php') ?>" class="sidebar-link <?= in_array($currentPage, ['reports.php'], true) ? 'active' : '' ?>" data-tooltip="Reports">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        <span>Reports</span>
      </a>

      <div class="nav-group-title" style="margin-top: 18px;">Configuration</div>

      <a href="<?= e(ADMIN_URL . '/settings.php') ?>" class="sidebar-link <?= in_array($currentPage, ['settings.php'], true) ? 'active' : '' ?>" data-tooltip="Settings">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        <span>Settings</span>
      </a>

      <a href="<?= e(ADMIN_URL . '/users.php') ?>" class="sidebar-link <?= in_array($currentPage, ['users.php'], true) ? 'active' : '' ?>" data-tooltip="Admin Users">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
        </svg>
        <span>Admin Users</span>
      </a>
    </nav>

    <!-- Sidebar Bottom Profile & Logout -->
    <div class="sidebar-footer">
      <div class="user-profile-box">
        <div class="user-avatar" title="<?= e($adminName) ?>">
          <?= e($initials) ?>
        </div>
        <div class="user-meta">
          <div class="user-name"><?= e($adminName) ?></div>
          <div class="user-role"><?= e($currentAdmin['role'] ?? 'admin') ?></div>
        </div>
        <a href="logout.php" class="btn-sidebar-logout" title="Sign Out">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
          </svg>
        </a>
      </div>
    </div>
  </aside>

  <!-- =======================================================
       LIGHT CONTENT AREA & TOP BAR
  ======================================================== -->
  <div class="admin-main">
    <header class="topbar">
      <div class="topbar-left">
        <button class="btn-collapse-sidebar" id="sidebarToggle" aria-label="Toggle sidebar menu">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
          </svg>
        </button>

        <nav class="topbar-breadcrumb" aria-label="Breadcrumb">
          <span>WebsiteTailors</span>
          <span class="sep">/</span>
          <span>CRM</span>
          <span class="sep">/</span>
          <span class="current"><?= e($breadcrumb) ?></span>
        </nav>
      </div>

      <!-- Global CRM Search Bar -->
      <div class="topbar-search-wrap">
        <div class="topbar-search-box">
          <svg class="topbar-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <input type="text"
                 id="globalCrmSearchInput"
                 placeholder="Search clients, leads, companies..."
                 autocomplete="off"
                 class="topbar-search-input" />
          <span class="topbar-search-badge">/</span>
        </div>

        <!-- Dropdown Results Preview -->
        <div id="globalSearchDropdown" class="global-search-dropdown">
        </div>
      </div>

      <div class="topbar-right">
        <div class="topbar-date">
          <?= date('D, M j, Y') ?>
        </div>

        <a href="../index.php" target="_blank" class="btn-view-site">
          <span>View Website</span>
          <span>↗</span>
        </a>
      </div>
    </header>

    <main class="admin-content">
      <!-- Flash Alert Messenger -->
      <?php $flashSuccess = get_flash('success'); ?>
      <?php if (!empty($flashSuccess)): ?>
        <?php foreach ($flashSuccess as $msg): ?>
          <div class="admin-alert alert-success">
            <span><?= e($msg) ?></span>
            <button class="alert-dismiss-btn" aria-label="Dismiss alert">✕</button>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php $flashError = get_flash('error'); ?>
      <?php if (!empty($flashError)): ?>
        <?php foreach ($flashError as $msg): ?>
          <div class="admin-alert alert-error">
            <span><?= e($msg) ?></span>
            <button class="alert-dismiss-btn" aria-label="Dismiss alert">✕</button>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
