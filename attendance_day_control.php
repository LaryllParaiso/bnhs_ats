<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

if (!in_array((string)($_SESSION['role'] ?? ''), ['Admin', 'Super Admin'], true)) {
    redirect('attendance_records.php');
}

$title = 'Day Scanning Control';

$pdo = db();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS attendance_day_scanning (
        scan_date DATE PRIMARY KEY,
        status ENUM("Active","Ended") NOT NULL DEFAULT "Active",
        late_threshold_minutes INT NOT NULL DEFAULT 0,
        absent_threshold_minutes INT NOT NULL DEFAULT 0,
        late_threshold_enabled TINYINT(1) NOT NULL DEFAULT 1,
        absent_threshold_enabled TINYINT(1) NOT NULL DEFAULT 1,
        started_by INT NOT NULL,
        started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ended_at DATETIME NULL,
        CONSTRAINT fk_attendance_day_scanning_started_by FOREIGN KEY (started_by) REFERENCES teachers (teacher_id) ON DELETE RESTRICT,
        INDEX idx_attendance_day_scanning_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci'
);

try {
    $pdo->exec('ALTER TABLE attendance_day_scanning ADD COLUMN late_threshold_minutes INT NOT NULL DEFAULT 0');
} catch (Throwable $e) {
}

try {
    $pdo->exec('ALTER TABLE attendance_day_scanning ADD COLUMN absent_threshold_minutes INT NOT NULL DEFAULT 0');
} catch (Throwable $e) {
}

try {
    $pdo->exec('ALTER TABLE attendance_day_scanning ADD COLUMN late_threshold_enabled TINYINT(1) NOT NULL DEFAULT 1');
} catch (Throwable $e) {
}

try {
    $pdo->exec('ALTER TABLE attendance_day_scanning ADD COLUMN absent_threshold_enabled TINYINT(1) NOT NULL DEFAULT 1');
} catch (Throwable $e) {
}

$today = date('Y-m-d');
$todayDay = date('l');

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'start') {
        $lateEnabled = isset($_POST['late_threshold_enabled']) ? 1 : 0;
        $absentEnabled = isset($_POST['absent_threshold_enabled']) ? 1 : 0;

        $threshold = (int)($_POST['late_threshold_minutes'] ?? 0);
        if ($threshold < 0) {
            $threshold = 0;
        }
        if ($threshold > 240) {
            $threshold = 240;
        }

        $absentThreshold = (int)($_POST['absent_threshold_minutes'] ?? 0);
        if ($absentThreshold < 0) {
            $absentThreshold = 0;
        }
        if ($absentThreshold > 480) {
            $absentThreshold = 480;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO attendance_day_scanning (scan_date, status, late_threshold_minutes, absent_threshold_minutes, late_threshold_enabled, absent_threshold_enabled, started_by, started_at, ended_at)
             VALUES (CURDATE(), "Active", :late_threshold_minutes, :absent_threshold_minutes, :late_threshold_enabled, :absent_threshold_enabled, :started_by, NOW(), NULL)
             ON DUPLICATE KEY UPDATE status = "Active", late_threshold_minutes = VALUES(late_threshold_minutes), absent_threshold_minutes = VALUES(absent_threshold_minutes), late_threshold_enabled = VALUES(late_threshold_enabled), absent_threshold_enabled = VALUES(absent_threshold_enabled), started_by = VALUES(started_by), started_at = VALUES(started_at), ended_at = NULL'
        );
        $stmt->execute([
            ':late_threshold_minutes' => $threshold,
            ':absent_threshold_minutes' => $absentThreshold,
            ':late_threshold_enabled' => $lateEnabled,
            ':absent_threshold_enabled' => $absentEnabled,
            ':started_by' => (int)$_SESSION['teacher_id'],
        ]);
        $success = 'Day scanning is now ACTIVE.';
    } elseif ($action === 'stop') {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO attendance (student_id, schedule_id, teacher_id, date, time_scanned, status)
                 SELECT ss.student_id, sch.schedule_id, sch.teacher_id, CURDATE(), NULL, "Absent"
                 FROM schedules sch
                 JOIN student_schedules ss ON ss.schedule_id = sch.schedule_id
                 JOIN students st ON st.student_id = ss.student_id
                 WHERE sch.status = "Active"
                   AND sch.day_of_week = :today
                   AND ss.status = "Active"
                   AND st.status = "Active"
                   AND NOT EXISTS (
                     SELECT 1
                     FROM attendance a
                     WHERE a.student_id = ss.student_id
                       AND a.schedule_id = sch.schedule_id
                       AND a.date = CURDATE()
                   )'
            );
            $stmt->execute([':today' => $todayDay]);

            $stmt = $pdo->prepare(
                'UPDATE attendance_day_scanning
                 SET status = "Ended", ended_at = NOW()
                 WHERE scan_date = CURDATE()'
            );
            $stmt->execute();

            $pdo->commit();
            $success = 'Day scanning is now ENDED.';
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Failed to stop day scanning.';
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM attendance_day_scanning WHERE scan_date = CURDATE() LIMIT 1');
$stmt->execute();
$state = $stmt->fetch();

$isActive = $state && (string)$state['status'] === 'Active';
$currentThreshold = (int)($state['late_threshold_minutes'] ?? 0);
$currentAbsentThreshold = (int)($state['absent_threshold_minutes'] ?? 0);
$currentLateEnabled = (int)($state['late_threshold_enabled'] ?? 1) === 1;
$currentAbsentEnabled = (int)($state['absent_threshold_enabled'] ?? 1) === 1;

$stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM schedules WHERE status = "Active" AND day_of_week = :day');
$stmt->execute([':day' => $todayDay]);
$scheduleCountToday = (int)($stmt->fetch()['cnt'] ?? 0);

require __DIR__ . '/partials/layout_top.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h4 mb-0">Day Scanning Control</h1>
      <a class="btn btn-outline-secondary btn-sm" href="<?= h(url('attendance_day_scanner.php')) ?>">Open Scanner</a>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errors as $e): ?>
            <li><?= h($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
      <div class="card-body">
        <div class="mb-2 text-muted small">
          Date: <span class="fw-semibold"><?= h($today) ?></span> | Day: <span class="fw-semibold"><?= h($todayDay) ?></span>
        </div>

        <div class="mb-3">
          Status:
          <?php if ($isActive): ?>
            <span class="badge text-bg-success">ACTIVE</span>
          <?php else: ?>
            <span class="badge text-bg-secondary">INACTIVE</span>
          <?php endif; ?>
        </div>

        <div class="mb-3 text-muted small">
          Late threshold (minutes from start time):
          <span class="fw-semibold"><?= $currentLateEnabled ? 'ON' : 'OFF' ?></span>
          <span class="fw-semibold"><?= $currentLateEnabled ? (': ' . (int)$currentThreshold) : '' ?></span>
        </div>

        <div class="mb-3 text-muted small">
          Absent threshold (minutes from start time):
          <span class="fw-semibold"><?= $currentAbsentEnabled ? 'ON' : 'OFF' ?></span>
          <span class="fw-semibold"><?= $currentAbsentEnabled ? (': ' . (int)$currentAbsentThreshold) : '' ?></span>
        </div>

        <div class="mb-3 text-muted small">
          Active schedules today: <span class="fw-semibold"><?= (int)$scheduleCountToday ?></span>
        </div>

        <form method="post" action="<?= h(url('attendance_day_control.php')) ?>" class="d-flex gap-2 align-items-end" <?= $isActive ? 'data-confirm="Stop day scanning for today?" data-confirm-title="Stop Day Scanning" data-confirm-ok="Stop" data-confirm-cancel="Cancel" data-confirm-icon="warning"' : '' ?>>
          <?php if (!$isActive): ?>
            <div>
              <label class="form-label small mb-1">Late threshold (minutes)</label>
              <input class="form-control form-control-sm" type="number" name="late_threshold_minutes" min="0" max="240" value="<?= h((string)$currentThreshold) ?>" style="max-width: 180px;" required>
              <div class="form-check mt-1">
                <input class="form-check-input" type="checkbox" name="late_threshold_enabled" id="late_threshold_enabled" <?= $currentLateEnabled ? 'checked' : '' ?>>
                <label class="form-check-label small" for="late_threshold_enabled">Enable</label>
              </div>
            </div>

            <div>
              <label class="form-label small mb-1">Absent threshold (minutes)</label>
              <input class="form-control form-control-sm" type="number" name="absent_threshold_minutes" min="0" max="480" value="<?= h((string)$currentAbsentThreshold) ?>" style="max-width: 190px;" required>
              <div class="form-check mt-1">
                <input class="form-check-input" type="checkbox" name="absent_threshold_enabled" id="absent_threshold_enabled" <?= $currentAbsentEnabled ? 'checked' : '' ?>>
                <label class="form-check-label small" for="absent_threshold_enabled">Enable</label>
              </div>
            </div>
            <button class="btn btn-primary" type="submit" name="action" value="start">Start Day Scanning</button>
          <?php else: ?>
            <button class="btn btn-danger" type="submit" name="action" value="stop">Stop Day Scanning</button>
          <?php endif; ?>
        </form>

        <hr>

        <div class="alert alert-info mb-0" data-no-toast>
          While day scanning is <span class="fw-semibold">ACTIVE</span>, the scanner will automatically detect the correct schedule based on the current time and the student's enrollment.
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/partials/layout_bottom.php';
