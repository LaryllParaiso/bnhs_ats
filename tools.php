<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

$title = 'Tools';
require __DIR__ . '/partials/layout_top.php';
?>

<div class="bnhs-page-header">
  <h1 class="bnhs-page-title">Tools</h1>
</div>

<div class="text-muted mb-3">Quick actions for imports and QR management.</div>

<div class="row g-3">
  <div class="col-md-6">
    <div class="bnhs-feature h-100 d-flex flex-column">
      <div class="bnhs-feature-icon blue">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M12 3v12" stroke="white" stroke-width="2" stroke-linecap="round"/>
          <path d="M7 10l5 5 5-5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M5 21h14" stroke="white" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </div>
      <h3 class="bnhs-feature-title">Import Students</h3>
      <p class="bnhs-feature-text">Upload a file to add multiple students at once.</p>
      <div class="mt-auto pt-3">
        <a class="btn btn-primary" href="<?= h(url('import_students.php')) ?>">Open Import</a>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="bnhs-feature h-100 d-flex flex-column">
      <div class="bnhs-feature-icon red">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M4 7V5a2 2 0 0 1 2-2h2" stroke="white" stroke-width="2" stroke-linecap="round"/>
          <path d="M20 7V5a2 2 0 0 0-2-2h-2" stroke="white" stroke-width="2" stroke-linecap="round"/>
          <path d="M4 17v2a2 2 0 0 0 2 2h2" stroke="white" stroke-width="2" stroke-linecap="round"/>
          <path d="M20 17v2a2 2 0 0 1-2 2h-2" stroke="white" stroke-width="2" stroke-linecap="round"/>
          <path d="M7 12h10" stroke="white" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </div>
      <h3 class="bnhs-feature-title">Bulk QR</h3>
      <p class="bnhs-feature-text">Generate and download QR codes for multiple students.</p>
      <div class="mt-auto pt-3">
        <a class="btn btn-primary" href="<?= h(url('bulk_qr.php')) ?>">Open Bulk QR</a>
      </div>
    </div>
  </div>
</div>

<?php
require __DIR__ . '/partials/layout_bottom.php';
