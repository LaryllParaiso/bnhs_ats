<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

$title = 'Finalize Attendance';

$teacherId = (int)$_SESSION['teacher_id'];
$sessionId = (int)($_GET['session_id'] ?? 0);

$pdo = db();
$errors = [];

if ($sessionId <= 0) {
    redirect('attendance_start.php');
}

$stmt = $pdo->prepare(
    'SELECT s.session_id, s.teacher_id, s.schedule_id, s.session_date, s.started_at, s.status,
            sch.subject_name, sch.grade_level, sch.section, sch.day_of_week, sch.start_time, sch.end_time
     FROM attendance_sessions s
     JOIN schedules sch ON sch.schedule_id = s.schedule_id
     WHERE s.session_id = :session_id AND s.teacher_id = :teacher_id
     LIMIT 1'
);
$stmt->execute([':session_id' => $sessionId, ':teacher_id' => $teacherId]);
$session = $stmt->fetch();

if (!$session) {
    redirect('attendance_start.php');
}

if ((string)$session['status'] !== 'Active') {
    redirect('attendance_start.php');
}

$scheduleId = (int)$session['schedule_id'];
$sessionDate = (string)$session['session_date'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $statuses = (array)($_POST['status'] ?? []);
    $remarks = (array)($_POST['remarks'] ?? []);

    $pdo->beginTransaction();
    try {
        foreach ($statuses as $attendanceId => $st) {
            $attendanceId = (int)$attendanceId;
            $st = (string)$st;
            if ($attendanceId <= 0) {
                continue;
            }
            if (!in_array($st, ['Present','Late','Absent'], true)) {
                continue;
            }

            $rm = trim((string)($remarks[$attendanceId] ?? ''));

            $stmt = $pdo->prepare(
                'UPDATE attendance
                 SET status = :status,
                     remarks = :remarks
                 WHERE attendance_id = :id
                   AND teacher_id = :teacher_id
                   AND schedule_id = :schedule_id
                   AND date = :date'
            );

            $stmt->execute([
                ':status' => $st,
                ':remarks' => $rm !== '' ? $rm : null,
                ':id' => $attendanceId,
                ':teacher_id' => $teacherId,
                ':schedule_id' => $scheduleId,
                ':date' => $sessionDate,
            ]);
        }

        $stmt = $pdo->prepare('UPDATE attendance_sessions SET status = "Ended", ended_at = NOW() WHERE session_id = :id AND teacher_id = :teacher_id');
        $stmt->execute([':id' => $sessionId, ':teacher_id' => $teacherId]);

        $pdo->commit();
        redirect('attendance_start.php');
    } catch (Throwable $e) {
        $pdo->rollBack();
        $errors[] = 'Failed to finalize session.';
    }
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'SELECT st.student_id
         FROM student_schedules ss
         JOIN students st ON st.student_id = ss.student_id
         WHERE ss.schedule_id = :schedule_id
           AND ss.status = "Active"
           AND st.status = "Active"
           AND NOT EXISTS (
             SELECT 1
             FROM attendance a
             WHERE a.student_id = st.student_id
               AND a.schedule_id = :schedule_id
               AND a.date = :date
           )'
    );
    $stmt->execute([':schedule_id' => $scheduleId, ':date' => $sessionDate]);
    $missing = $stmt->fetchAll();

    if ($missing) {
        $stmtIns = $pdo->prepare(
            'INSERT INTO attendance (student_id, schedule_id, teacher_id, date, time_scanned, status)
             VALUES (:student_id, :schedule_id, :teacher_id, :date, NULL, "Absent")'
        );

        foreach ($missing as $m) {
            $stmtIns->execute([
                ':student_id' => (int)$m['student_id'],
                ':schedule_id' => $scheduleId,
                ':teacher_id' => $teacherId,
                ':date' => $sessionDate,
            ]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    $errors[] = 'Failed to prepare absent list.';
}

$stmt = $pdo->prepare(
    'SELECT a.attendance_id, a.time_scanned, a.status, a.remarks,
            st.lrn, st.first_name, st.last_name, st.suffix
     FROM attendance a
     JOIN students st ON st.student_id = a.student_id
     WHERE a.schedule_id = :schedule_id
       AND a.teacher_id = :teacher_id
       AND a.date = :date
     ORDER BY st.last_name, st.first_name, st.lrn'
);
$stmt->execute([':schedule_id' => $scheduleId, ':teacher_id' => $teacherId, ':date' => $sessionDate]);
$rows = $stmt->fetchAll();

require __DIR__ . '/partials/layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h4 mb-1">Finalize Attendance</h1>
    <div class="text-muted small">
      <?= h((string)$session['subject_name']) ?> | <?= h($sessionDate) ?> | G<?= h((string)$session['grade_level']) ?>-<?= h((string)$session['section']) ?>
    </div>
  </div>
  <a class="btn btn-outline-secondary btn-sm" href="<?= h(url('attendance_session.php?session_id=' . (string)$sessionId)) ?>">Back</a>
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

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post" action="<?= h(url('attendance_end.php?session_id=' . (string)$sessionId)) ?>">
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>LRN</th>
              <th>Name</th>
              <th>Time</th>
              <th>Status</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <?php
                $suffix = trim((string)($r['suffix'] ?? ''));
                $name = trim((string)$r['first_name'] . ' ' . (string)$r['last_name']);
                if ($suffix !== '') {
                    $name .= ', ' . $suffix;
                }
                $aid = (int)$r['attendance_id'];
                $st = (string)$r['status'];
              ?>
              <tr>
                <td><?= h((string)$r['lrn']) ?></td>
                <td><?= h($name) ?></td>
                <td><?= h((string)($r['time_scanned'] ?? '')) ?></td>
                <td style="min-width: 140px;">
                  <select class="form-select form-select-sm" name="status[<?= $aid ?>]">
                    <option value="Present" <?= $st === 'Present' ? 'selected' : '' ?>>Present</option>
                    <option value="Late" <?= $st === 'Late' ? 'selected' : '' ?>>Late</option>
                    <option value="Absent" <?= $st === 'Absent' ? 'selected' : '' ?>>Absent</option>
                  </select>
                </td>
                <td>
                  <input class="form-control form-control-sm" name="remarks[<?= $aid ?>]" value="<?= h((string)($r['remarks'] ?? '')) ?>">
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <button class="btn btn-primary" type="submit">Finalize Session</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/partials/layout_bottom.php';
