<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

redirect('attendance_records.php');

$title = 'Dashboard';
require __DIR__ . '/partials/layout_top.php';
?>

<div class="card shadow-sm">
  <div class="card-body">
    <h1 class="h4 mb-2"><?= is_admin() ? 'Admin Dashboard' : 'Teacher Dashboard' ?></h1>
    <div class="text-muted mb-3">Welcome, <?= h((string)($_SESSION['teacher_name'] ?? 'Teacher')) ?>.</div>

    <div class="row g-3">
      <div class="col-md-4">
        <div class="border rounded p-3 bg-white">
          <div class="fw-semibold">Phase 1 Status</div>
          <div class="text-muted">Auth + DB connection ready.</div>
        </div>
      </div>
      <div class="col-md-8">
        <div class="border rounded p-3 bg-white">
          <div class="fw-semibold">Next (Phase 2)</div>
          <div class="text-muted">Student CRUD, Schedule CRUD, Enrollment.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
require __DIR__ . '/partials/layout_bottom.php';
