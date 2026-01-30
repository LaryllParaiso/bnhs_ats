<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

$title = 'Start Attendance';

$teacherId = (int)$_SESSION['teacher_id'];
$pdo = db();

$errors = [];

$stmt = $pdo->prepare('SELECT * FROM attendance_sessions WHERE teacher_id = :teacher_id AND status = "Active" ORDER BY started_at DESC LIMIT 1');
$stmt->execute([':teacher_id' => $teacherId]);
$activeSession = $stmt->fetch();

$stmt = $pdo->prepare(
    'SELECT schedule_id, subject_name, grade_level, section, day_of_week, start_time, end_time, room, semester, school_year, status
     FROM schedules
     WHERE teacher_id = :teacher_id AND status = "Active"
     ORDER BY FIELD(day_of_week, "Monday","Tuesday","Wednesday","Thursday","Friday"), start_time'
);
$stmt->execute([':teacher_id' => $teacherId]);
$schedules = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($activeSession) {
        redirect('attendance_session.php?session_id=' . urlencode((string)$activeSession['session_id']));
    }

    $scheduleId = (int)($_POST['schedule_id'] ?? 0);

    if ($scheduleId <= 0) {
        $errors[] = 'Schedule is required.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM schedules WHERE schedule_id = :id AND teacher_id = :teacher_id LIMIT 1');
        $stmt->execute([':id' => $scheduleId, ':teacher_id' => $teacherId]);
        $schedule = $stmt->fetch();

        if (!$schedule) {
            $errors[] = 'Schedule not found.';
        } elseif ((string)$schedule['status'] !== 'Active') {
            $errors[] = 'Schedule must be Active.';
        } else {
            $today = date('l');
            if ((string)$schedule['day_of_week'] !== $today) {
                $errors[] = 'Today does not match the selected schedule day.';
            } else {
                $grace = (int)ATTENDANCE_GRACE_MINUTES;
                $now = new DateTimeImmutable('now');

                $start = new DateTimeImmutable(date('Y-m-d') . ' ' . (string)$schedule['start_time']);
                $end = new DateTimeImmutable(date('Y-m-d') . ' ' . (string)$schedule['end_time']);

                $startAllowed = $start->modify('-' . $grace . ' minutes');
                $endAllowed = $end->modify('+' . $grace . ' minutes');

                if ($now < $startAllowed || $now > $endAllowed) {
                    $errors[] = 'Current time is outside the allowed schedule time window.';
                }
            }
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO attendance_sessions (teacher_id, schedule_id, session_date, started_at, status)
             VALUES (:teacher_id, :schedule_id, CURDATE(), NOW(), "Active")'
        );
        $stmt->execute([':teacher_id' => $teacherId, ':schedule_id' => $scheduleId]);
        $sid = (int)$pdo->lastInsertId();
        redirect('attendance_session.php?session_id=' . urlencode((string)$sid));
    }
}

require __DIR__ . '/partials/layout_top.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h4 mb-0">Start Attendance Session</h1>
      <?php if ($activeSession): ?>
        <a class="btn btn-primary btn-sm" href="<?= h(url('attendance_session.php?session_id=' . (string)$activeSession['session_id'])) ?>">Resume Active Session</a>
      <?php endif; ?>
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

    <?php if (!$schedules): ?>
      <div class="alert alert-info" data-no-toast>No active schedules found. Create an active schedule first.</div>
    <?php else: ?>
      <div class="card shadow-sm">
        <div class="card-body">
          <form method="post" action="<?= h(url('attendance_start.php')) ?>">
            <div class="mb-3">
              <label class="form-label">Select Schedule (Active only)</label>
              <select class="form-select" name="schedule_id" <?= $activeSession ? 'disabled' : '' ?> required>
                <option value="">Select</option>
                <?php foreach ($schedules as $s): ?>
                  <?php
                    $label = (string)$s['subject_name'] . ' | G' . (string)$s['grade_level'] . '-' . (string)$s['section'] . ' | ' . (string)$s['day_of_week'] . ' ' . (string)$s['start_time'] . '-' . (string)$s['end_time'];
                  ?>
                  <option value="<?= (int)$s['schedule_id'] ?>"><?= h($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <?php if ($activeSession): ?>
              <div class="alert alert-warning mb-3" data-no-toast>You already have an active session. Please resume it instead of starting a new one.</div>
            <?php endif; ?>

            <button class="btn btn-primary" type="submit">Start Session</button>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/partials/layout_bottom.php';
