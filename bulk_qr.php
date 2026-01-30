<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

$title = 'Bulk QR';

$teacherId = (int)$_SESSION['teacher_id'];
$scheduleId = (int)($_GET['schedule_id'] ?? 0);

$pdo = db();

$stmt = $pdo->prepare(
    'SELECT schedule_id, subject_name, grade_level, section, day_of_week, start_time, end_time, school_year, status
     FROM schedules
     WHERE teacher_id = :teacher_id AND status != "Archived"
     ORDER BY FIELD(day_of_week, "Monday","Tuesday","Wednesday","Thursday","Friday"), start_time'
);
$stmt->execute([':teacher_id' => $teacherId]);
$schedules = $stmt->fetchAll();

$selectedSchedule = null;
$students = [];

if ($scheduleId > 0) {
    $stmt = $pdo->prepare(
        'SELECT schedule_id, subject_name, grade_level, section, day_of_week, start_time, end_time, school_year, status
         FROM schedules
         WHERE schedule_id = :id AND teacher_id = :teacher_id AND status != "Archived"
         LIMIT 1'
    );
    $stmt->execute([':id' => $scheduleId, ':teacher_id' => $teacherId]);
    $selectedSchedule = $stmt->fetch();

    if ($selectedSchedule) {
        $stmt = $pdo->prepare(
            'SELECT st.*
             FROM student_schedules ss
             JOIN students st ON st.student_id = ss.student_id
             WHERE ss.schedule_id = :schedule_id
               AND ss.status = "Active"
               AND st.status = "Active"
             ORDER BY st.last_name, st.first_name, st.lrn'
        );
        $stmt->execute([':schedule_id' => $scheduleId]);
        $students = $stmt->fetchAll();
    }
}

require __DIR__ . '/partials/layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Bulk QR</h1>
  <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">Print</button>
</div>

<form method="get" action="<?= h(url('bulk_qr.php')) ?>" class="row g-2 mb-3">
  <div class="col-md-6">
    <select class="form-select" name="schedule_id" required>
      <option value="">Select schedule</option>
      <?php foreach ($schedules as $s): ?>
        <?php
          $sid = (int)$s['schedule_id'];
          $label = (string)$s['subject_name'] . ' | G' . (string)$s['grade_level'] . '-' . (string)$s['section'] . ' | ' . (string)$s['day_of_week'] . ' ' . (string)$s['start_time'] . '-' . (string)$s['end_time'];
        ?>
        <option value="<?= $sid ?>" <?= $sid === $scheduleId ? 'selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <button class="btn btn-primary" type="submit">Load</button>
  </div>
</form>

<?php if ($scheduleId > 0 && !$selectedSchedule): ?>
  <div class="alert alert-danger">Schedule not found.</div>
<?php elseif ($selectedSchedule && !$students): ?>
  <div class="alert alert-info" data-no-toast>No enrolled active students for this schedule.</div>
<?php endif; ?>

<?php if ($selectedSchedule && $students): ?>
  <div class="row g-3" id="qrGrid">
    <?php foreach ($students as $idx => $st): ?>
      <?php
        $suffix = trim((string)($st['suffix'] ?? ''));
        $fullName = trim((string)$st['first_name'] . ' ' . (string)$st['last_name']);
        if ($suffix !== '') {
            $fullName .= ', ' . $suffix;
        }

        $payload = [
            'lrn' => (string)$st['lrn'],
            'name' => $fullName,
            'grade' => (int)$st['grade_level'],
            'section' => (string)$st['section'],
            'token' => (string)$st['qr_token'],
        ];
        $qrText = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $qrId = 'qr_' . (string)$idx;
      ?>

      <div class="col-md-4">
        <div class="card">
          <div class="card-body text-center">
            <div id="<?= h($qrId) ?>" class="d-inline-block p-2 bg-white border rounded" data-qr-text="<?= h($qrText) ?>"></div>
            <div class="mt-2 fw-semibold"><?= h($fullName) ?></div>
            <div class="text-muted small">LRN: <?= h((string)$st['lrn']) ?></div>
            <div class="text-muted small">G<?= h((string)$st['grade_level']) ?>-<?= h((string)$st['section']) ?></div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
  <script>
    (function () {
      document.querySelectorAll('[data-qr-text]').forEach(function (el) {
        const text = el.getAttribute('data-qr-text') || '';
        el.innerHTML = '';
        new QRCode(el, {
          text: text,
          width: 300,
          height: 300,
          correctLevel: QRCode.CorrectLevel.L
        });
      });
    })();
  </script>
<?php endif; ?>

<?php require __DIR__ . '/partials/layout_bottom.php';
