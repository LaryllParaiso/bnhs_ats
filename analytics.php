<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

$teacherId = (int)$_SESSION['teacher_id'];
$isAdmin = is_admin();

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$scheduleId = (int)($_GET['schedule_id'] ?? 0);

$today = new DateTimeImmutable('today');
$defaultFrom = $today->sub(new DateInterval('P29D'))->format('Y-m-d');
$defaultTo = $today->format('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = $defaultFrom;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = $defaultTo;
}

$pdo = db();
$stmt = $pdo->prepare(
    $isAdmin
        ? 'SELECT schedule_id, subject_name, grade_level, section
           FROM schedules
           WHERE status != "Archived"
           ORDER BY subject_name, grade_level, section'
        : 'SELECT schedule_id, subject_name, grade_level, section
           FROM schedules
           WHERE teacher_id = :teacher_id AND status != "Archived"
           ORDER BY subject_name, grade_level, section'
);
$stmt->execute($isAdmin ? [] : [':teacher_id' => $teacherId]);
$schedules = $stmt->fetchAll();

$title = 'Analytics';
require __DIR__ . '/partials/layout_top.php';
?>

<div class="bnhs-page-header">
  <h1 class="bnhs-page-title">Analytics</h1>
  <div class="bnhs-page-actions">
    <a class="btn btn-outline-secondary btn-sm" href="<?= h(url('attendance_records.php?from=' . urlencode($from) . '&to=' . urlencode($to))) ?>">Attendance Records</a>
    <button class="btn btn-primary btn-sm" type="button" onclick="window.print();">Print</button>
  </div>
</div>

<div class="card shadow-sm mb-3 bnhs-filter-card">
  <div class="card-body">
    <form class="row g-2" method="get" action="<?= h(url('analytics.php')) ?>">
      <div class="col-md-2">
        <input class="form-control" type="date" name="from" value="<?= h($from) ?>">
      </div>
      <div class="col-md-2">
        <input class="form-control" type="date" name="to" value="<?= h($to) ?>">
      </div>
      <div class="col-md-6">
        <select class="form-select" name="schedule_id">
          <option value="0" <?= $scheduleId === 0 ? 'selected' : '' ?>>All Subjects</option>
          <?php foreach ($schedules as $s): ?>
            <?php $label = (string)$s['subject_name'] . ' (G' . (string)$s['grade_level'] . '-' . (string)$s['section'] . ')'; ?>
            <option value="<?= h((string)$s['schedule_id']) ?>" <?= $scheduleId === (int)$s['schedule_id'] ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-outline-primary" type="submit">Apply</button>
      </div>
    </form>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="fw-semibold mb-2">Attendance by Subject</div>
        <div id="emptyBySubject" class="bnhs-empty-state d-none">
          <div class="bnhs-empty-icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M4 19V5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M8 19V11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M12 19V7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M16 19v-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M20 19V9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          No data found for this date range.
        </div>
        <canvas id="chartBySubject" height="220"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="fw-semibold mb-2">Daily Trend</div>
        <div id="emptyTrend" class="bnhs-empty-state d-none">
          <div class="bnhs-empty-icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M3 3v18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M7 14l3-3 3 2 4-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          No trend data for the selected filters.
        </div>
        <canvas id="chartTrend" height="220"></canvas>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-12">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="fw-semibold mb-2">Late Arrival (Average Minutes Late)</div>
        <div id="emptyLate" class="bnhs-empty-state d-none">
          <div class="bnhs-empty-icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 6v6l4 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          No late-scan data for the selected filters.
        </div>
        <canvas id="chartLate" height="160"></canvas>
        <div class="text-muted small mt-2">Average is computed only from Late scans with a recorded scan time.</div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  (function () {
    const params = new URLSearchParams({
      from: <?= json_encode($from) ?>,
      to: <?= json_encode($to) ?>,
      schedule_id: <?= json_encode((string)$scheduleId) ?>
    });

    const apiUrl = <?= json_encode(url('analytics_data.php')) ?> + '?' + params.toString();

    function safeNum(v) {
      const n = Number(v);
      return Number.isFinite(n) ? n : 0;
    }

    fetch(apiUrl, { credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => {
        if (!data || !data.ok) return;

        const by = Array.isArray(data.by_schedule) ? data.by_schedule : [];
        const trend = Array.isArray(data.trend) ? data.trend : [];

        const labels = by.map(x => x.label);
        const present = by.map(x => safeNum(x.present));
        const late = by.map(x => safeNum(x.late));
        const absent = by.map(x => safeNum(x.absent));
        const avgLate = by.map(x => (x.avg_minutes_late === null ? null : safeNum(x.avg_minutes_late)));

        const ctx1 = document.getElementById('chartBySubject');
        const emptyBy = document.getElementById('emptyBySubject');
        if (!labels.length) {
          if (ctx1) ctx1.style.display = 'none';
          if (emptyBy) emptyBy.classList.remove('d-none');
        } else {
          if (ctx1) ctx1.style.display = '';
          if (emptyBy) emptyBy.classList.add('d-none');
          new Chart(ctx1, {
            type: 'bar',
            data: {
              labels: labels,
              datasets: [
                { label: 'Present', data: present, backgroundColor: '#198754' },
                { label: 'Late', data: late, backgroundColor: '#ffc107' },
                { label: 'Absent', data: absent, backgroundColor: '#dc3545' }
              ]
            },
            options: {
              responsive: true,
              plugins: { legend: { position: 'bottom' } },
              scales: {
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true }
              }
            }
          });
        }

        const tLabels = trend.map(x => x.date);
        const tPresent = trend.map(x => safeNum(x.present));
        const tLate = trend.map(x => safeNum(x.late));
        const tAbsent = trend.map(x => safeNum(x.absent));

        const ctx2 = document.getElementById('chartTrend');
        const emptyTrend = document.getElementById('emptyTrend');
        if (!tLabels.length) {
          if (ctx2) ctx2.style.display = 'none';
          if (emptyTrend) emptyTrend.classList.remove('d-none');
        } else {
          if (ctx2) ctx2.style.display = '';
          if (emptyTrend) emptyTrend.classList.add('d-none');
          new Chart(ctx2, {
            type: 'line',
            data: {
              labels: tLabels,
              datasets: [
                { label: 'Present', data: tPresent, borderColor: '#198754', backgroundColor: 'rgba(25,135,84,0.15)', tension: 0.2 },
                { label: 'Late', data: tLate, borderColor: '#ffc107', backgroundColor: 'rgba(255,193,7,0.15)', tension: 0.2 },
                { label: 'Absent', data: tAbsent, borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,0.15)', tension: 0.2 }
              ]
            },
            options: {
              responsive: true,
              plugins: { legend: { position: 'bottom' } },
              scales: { y: { beginAtZero: true } }
            }
          });
        }

        const ctx3 = document.getElementById('chartLate');
        const emptyLate = document.getElementById('emptyLate');
        const hasAnyLate = avgLate.some(v => v !== null && Number.isFinite(Number(v)));
        if (!labels.length || !hasAnyLate) {
          if (ctx3) ctx3.style.display = 'none';
          if (emptyLate) emptyLate.classList.remove('d-none');
        } else {
          if (ctx3) ctx3.style.display = '';
          if (emptyLate) emptyLate.classList.add('d-none');
          new Chart(ctx3, {
            type: 'bar',
            data: {
              labels: labels,
              datasets: [
                { label: 'Avg Minutes Late', data: avgLate, backgroundColor: '#0d6efd' }
              ]
            },
            options: {
              responsive: true,
              plugins: { legend: { position: 'bottom' } },
              scales: { y: { beginAtZero: true } }
            }
          });
        }
      })
      .catch(() => {});
  })();
</script>

<?php
require __DIR__ . '/partials/layout_bottom.php';
