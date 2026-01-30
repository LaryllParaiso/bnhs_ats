<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';

$title = $title ?? 'BNH ATS';

 $bodyClass = trim((string)($bodyClass ?? ''));

$currentPage = basename((string)($_SERVER['PHP_SELF'] ?? ''));
$activeClass = static function (string $file) use ($currentPage): string {
    return $currentPage === $file ? ' active' : '';
};

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= h(url('assets/bicos.css')) ?>" rel="stylesheet">
</head>
<body<?= $bodyClass !== '' ? (' class="' . h($bodyClass) . '"') : '' ?>>
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
      <?php if (isset($_SESSION['teacher_id'])): ?>
        <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-lg-1 mt-3 mt-lg-0">
          <li class="nav-item">
            <a class="nav-link<?= $activeClass('attendance_records.php') ?>" href="<?= h(url('attendance_records.php')) ?>">Dashboard</a>
          </li>
          <li class="nav-item">
            <a class="nav-link<?= $activeClass('students.php') ?>" href="<?= h(url('students.php')) ?>">Students</a>
          </li>
          <li class="nav-item">
            <a class="nav-link<?= $activeClass('schedules.php') ?>" href="<?= h(url('schedules.php')) ?>">Schedules</a>
          </li>
          <?php if ((string)($_SESSION['role'] ?? '') !== 'Admin'): ?>
            <li class="nav-item">
              <a class="nav-link<?= $activeClass('attendance_start.php') ?>" href="<?= h(url('attendance_start.php')) ?>">Attendance</a>
            </li>
          <?php endif; ?>
          <li class="nav-item">
            <a class="nav-link<?= $activeClass('attendance_day_scanner.php') ?>" href="<?= h(url('attendance_day_scanner.php')) ?>">Day Scanner</a>
          </li>
          <?php if ((string)($_SESSION['role'] ?? '') === 'Admin'): ?>
            <li class="nav-item">
              <a class="nav-link<?= $activeClass('attendance_day_control.php') ?>" href="<?= h(url('attendance_day_control.php')) ?>">Day Control</a>
            </li>
          <?php endif; ?>
          <li class="nav-item">
            <a class="nav-link<?= $activeClass('settings.php') ?>" href="<?= h(url('settings.php')) ?>">Settings</a>
          </li>
          <?php if ((string)($_SESSION['role'] ?? '') === 'Admin'): ?>
            <li class="nav-item">
              <a class="nav-link<?= $activeClass('teacher_approvals.php') ?>" href="<?= h(url('teacher_approvals.php')) ?>">Teacher Approvals</a>
            </li>
          <?php endif; ?>
        </ul>

        <div class="d-flex align-items-center gap-2 ms-lg-3 pb-3 pb-lg-0">
          <span class="navbar-text small d-none d-lg-inline">
            <?= h((string)($_SESSION['teacher_name'] ?? '')) ?>
          </span>
          <a class="btn btn-light btn-sm" href="<?= h(url('logout.php')) ?>">Logout</a>
        </div>
      <?php else: ?>
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
      <?php endif; ?>
    </div>
  </div>
</nav>
<div class="container py-4">
