<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';

$title = $title ?? 'BNH ATS';

 $bodyClass = trim((string)($bodyClass ?? ''));

$currentPage = basename((string)($_SERVER['PHP_SELF'] ?? ''));
$activeClass = static function (string $file) use ($currentPage): string {
    return $currentPage === $file ? ' active' : '';
};

$_pendingCrCount = 0;
$_notifBadge = 0;
if (isset($_SESSION['teacher_id'])) {
    try {
        $__pdo = db();
        $__role = (string)($_SESSION['role'] ?? '');
        if ($__role === 'Super Admin') {
            $__stmt = $__pdo->prepare('SELECT COUNT(*) AS cnt FROM change_requests WHERE status = "Pending"');
            $__stmt->execute();
            $_pendingCrCount = (int)($__stmt->fetch()['cnt'] ?? 0);

            $__stmt = $__pdo->prepare('SELECT COUNT(*) AS cnt FROM teachers WHERE approval_status = "Pending"');
            $__stmt->execute();
            $_notifBadge = $_pendingCrCount + (int)($__stmt->fetch()['cnt'] ?? 0);
        } elseif ($__role === 'Admin') {
            $__stmt = $__pdo->prepare('SELECT COUNT(*) AS cnt FROM teachers WHERE approval_status = "Pending"');
            $__stmt->execute();
            $_notifBadge = (int)($__stmt->fetch()['cnt'] ?? 0);
        } else {
            $__lastSeen = (string)($_SESSION['notif_seen_at'] ?? '2000-01-01 00:00:00');
            $__stmt = $__pdo->prepare('SELECT COUNT(*) AS cnt FROM change_requests WHERE teacher_id = :id AND status IN ("Approved","Rejected") AND reviewed_at > :seen');
            $__stmt->execute([':id' => (int)$_SESSION['teacher_id'], ':seen' => $__lastSeen]);
            $_notifBadge = (int)($__stmt->fetch()['cnt'] ?? 0);
        }
    } catch (Throwable $e) {
        $_pendingCrCount = 0;
        $_notifBadge = 0;
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?></title>
  <meta name="base-url" content="<?= h(rtrim(APP_BASE_URL, '/')) ?>/">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= h(url('assets/bicos.css')) ?>" rel="stylesheet">
</head>
<body<?= $bodyClass !== '' ? (' class="' . h($bodyClass) . '"') : '' ?>>

<?php if (isset($_SESSION['teacher_id']) && strpos($bodyClass, 'bnhs-hide-nav') === false): ?>
<?php
  $__dispRole = (string)($_SESSION['role'] ?? 'Teacher');
  $__roleBg = match($__dispRole) {
    'Super Admin' => 'background:rgba(239,68,68,.85);color:#fff',
    'Admin' => 'background:rgba(245,158,11,.9);color:#fff',
    default => 'background:rgba(255,255,255,.15);color:rgba(255,255,255,.85)',
  };
?>

<!-- Sidebar Overlay (mobile) -->
<div class="bnhs-sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<aside class="bnhs-sidebar" id="bnhsSidebar">
  <div class="bnhs-sidebar-brand">
    <a href="<?= h(url('index.php')) ?>">
      <img class="bnhs-brand-logo" src="<?= h(url('ui_ux_design/bicos%20logo.png')) ?>" alt="Bicos NHS Logo">
      <span class="bnhs-sidebar-brand-text">BNHS Attendance</span>
    </a>
  </div>

  <nav class="bnhs-sidebar-nav">
    <ul>
      <li class="bnhs-sidebar-label">Main</li>
      <li>
        <a class="bnhs-sidebar-link<?= $activeClass('attendance_records.php') ?>" href="<?= h(url('attendance_records.php')) ?>">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
          <span>Dashboard</span>
        </a>
      </li>
      <li>
        <a class="bnhs-sidebar-link<?= $activeClass('students.php') ?>" href="<?= h(url('students.php')) ?>">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
          <span>Students</span>
        </a>
      </li>
      <li>
        <a class="bnhs-sidebar-link<?= $activeClass('schedules.php') ?>" href="<?= h(url('schedules.php')) ?>">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          <span>Schedules</span>
        </a>
      </li>

      <?php if ((string)($_SESSION['role'] ?? '') === 'Teacher'): ?>
      <li class="bnhs-sidebar-label">Attendance</li>
      <li>
        <a class="bnhs-sidebar-link<?= $activeClass('attendance_start.php') ?>" href="<?= h(url('attendance_start.php')) ?>">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
          <span>Attendance</span>
        </a>
      </li>
      <?php endif; ?>

      <li>
        <a class="bnhs-sidebar-link<?= $activeClass('attendance_day_scanner.php') ?>" href="<?= h(url('attendance_day_scanner.php')) ?>">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7V5a2 2 0 012-2h2M20 7V5a2 2 0 00-2-2h-2M4 17v2a2 2 0 002 2h2M20 17v2a2 2 0 01-2 2h-2M7 12h10"/></svg>
          <span>Day Scanner</span>
        </a>
      </li>

      <?php if (in_array((string)($_SESSION['role'] ?? ''), ['Admin', 'Super Admin'], true)): ?>
      <li class="bnhs-sidebar-label">Administration</li>
      <li>
        <a class="bnhs-sidebar-link<?= $activeClass('attendance_day_control.php') ?>" href="<?= h(url('attendance_day_control.php')) ?>">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
          <span>Day Control</span>
        </a>
      </li>
      <li>
        <a class="bnhs-sidebar-link<?= $activeClass('teacher_approvals.php') ?>" href="<?= h(url('teacher_approvals.php')) ?>">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 11l2 2 4-4"/></svg>
          <span>Teacher Approvals</span>
        </a>
      </li>
      <?php endif; ?>

      <?php if ((string)($_SESSION['role'] ?? '') === 'Super Admin'): ?>
      <li>
        <a class="bnhs-sidebar-link<?= $activeClass('approval_queue.php') ?>" href="<?= h(url('approval_queue.php')) ?>">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
          <span>Approval Queue<?php if ($_pendingCrCount > 0): ?> <span class="badge bg-danger ms-1"><?= $_pendingCrCount ?></span><?php endif; ?></span>
        </a>
      </li>
      <li>
        <a class="bnhs-sidebar-link<?= $activeClass('class_suspension.php') ?>" href="<?= h(url('class_suspension.php')) ?>">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
          <span>Class Suspension</span>
        </a>
      </li>
      <?php endif; ?>

      <li class="bnhs-sidebar-label">Account</li>
      <li>
        <a class="bnhs-sidebar-link<?= $activeClass('settings.php') ?>" href="<?= h(url('settings.php')) ?>">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.573-1.066z"/><circle cx="12" cy="12" r="3"/></svg>
          <span>Settings</span>
        </a>
      </li>
    </ul>
  </nav>

  <div class="bnhs-sidebar-footer">
    <div class="bnhs-sidebar-user">
      <div class="bnhs-sidebar-avatar"><?= strtoupper(mb_substr(trim((string)($_SESSION['teacher_name'] ?? 'U')), 0, 1)) ?></div>
      <div class="bnhs-sidebar-user-info">
        <div class="bnhs-sidebar-user-name"><?= h((string)($_SESSION['teacher_name'] ?? '')) ?></div>
        <span class="bnhs-role-badge" style="<?= $__roleBg ?>"><?= h($__dispRole) ?></span>
      </div>
    </div>
    <a class="bnhs-sidebar-logout" href="<?= h(url('logout.php')) ?>" title="Logout">
      <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
    </a>
  </div>
</aside>

<!-- Top Bar -->
<header class="bnhs-topbar" id="bnhsTopbar">
  <button class="bnhs-topbar-toggle" id="sidebarToggle" type="button" aria-label="Toggle sidebar">
    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
  </button>
  <div class="bnhs-topbar-title"><?= h($title) ?></div>
  <div class="bnhs-topbar-actions">
    <div class="dropdown" id="notifDropdown">
      <button class="bnhs-topbar-icon-btn position-relative" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" id="notifBell" title="Notifications">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
          <path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2m.995-14.901a1 1 0 1 0-1.99 0A5 5 0 0 0 3 6c0 1.098-.5 6-2 7h14c-1.5-1-2-5.902-2-7 0-2.42-1.72-4.44-4.005-4.901"/>
        </svg>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger<?= $_notifBadge > 0 ? '' : ' d-none' ?>" id="notifBadge" style="font-size:.6rem"><?= $_notifBadge ?></span>
      </button>
      <div class="dropdown-menu dropdown-menu-end shadow" style="width:340px;max-height:400px;overflow-y:auto" id="notifMenu">
        <h6 class="dropdown-header d-flex justify-content-between align-items-center">
          Notifications
          <small class="text-primary" role="button" id="notifMarkRead" style="cursor:pointer">Mark all read</small>
        </h6>
        <div id="notifList">
          <div class="dropdown-item-text text-muted small text-center py-3">Loading...</div>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- Main Content -->
<main class="bnhs-main" id="bnhsMain">
<div class="container-fluid bnhs-content-pad">

<?php elseif (strpos($bodyClass, 'bnhs-hide-nav') !== false): ?>
<!-- Scanner / hidden-nav page: no sidebar, no topbar -->
<div class="container py-4">

<?php else: ?>
<!-- Not logged in: public nav -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top bnhs-sticky-nav">
  <div class="container">
    <a class="navbar-brand" href="<?= h(url('index.php')) ?>">
      <img class="bnhs-brand-logo" src="<?= h(url('ui_ux_design/bicos%20logo.png')) ?>" alt="Bicos NHS Logo">
      <span>BNHS Attendance</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNavbar">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 gap-lg-1 mt-3 mt-lg-0">
        <li class="nav-item">
          <a class="nav-link<?= $activeClass('login.php') ?>" href="<?= h(url('login.php')) ?>">Login</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= $activeClass('register.php') ?>" href="<?= h(url('register.php')) ?>">Teacher Register</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= $activeClass('student_register.php') ?>" href="<?= h(url('student_register.php')) ?>">Student Register</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= $activeClass('qr_regenerate.php') ?>" href="<?= h(url('qr_regenerate.php')) ?>">Regenerate QR</a>
        </li>
      </ul>
    </div>
  </div>
</nav>
<div class="container py-4">
<?php endif; ?>
