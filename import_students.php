<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/tokens.php';

require_login();

$teacherId = (int)$_SESSION['teacher_id'];
$pdo = db();

$stmt = $pdo->prepare(
    'SELECT schedule_id, subject_name, grade_level, section, day_of_week, start_time, end_time, status
     FROM schedules
     WHERE teacher_id = :teacher_id AND status != "Archived"
     ORDER BY FIELD(day_of_week, "Monday","Tuesday","Wednesday","Thursday","Friday"), start_time'
);
$stmt->execute([':teacher_id' => $teacherId]);
$teacherSchedules = $stmt->fetchAll();

$selectedScheduleIds = array_map('intval', (array)($_POST['schedule_ids'] ?? []));

$errors = [];
$results = [
    'inserted' => 0,
    'skipped' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['csv_file']) || !is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
        $errors[] = 'Please upload a CSV file.';
    }

    $teacherScheduleIds = array_map(static fn ($s) => (int)$s['schedule_id'], $teacherSchedules);
    $allowed = array_flip($teacherScheduleIds);
    foreach ($selectedScheduleIds as $sid) {
        if (!isset($allowed[$sid])) {
            $errors[] = 'Invalid schedule selection.';
            break;
        }
    }

    if (!$errors) {
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if ($handle === false) {
            $errors[] = 'Unable to read uploaded file.';
        } else {
            $pdo->beginTransaction();
            try {
                $rowIndex = 0;
                while (($row = fgetcsv($handle)) !== false) {
                    $rowIndex++;

                    if ($rowIndex === 1) {
                        $maybeHeader = array_map('strtolower', array_map('trim', $row));
                        if (in_array('lrn', $maybeHeader, true)) {
                            continue;
                        }
                    }

                    $row = array_map('trim', $row);

                    $lrn = (string)($row[0] ?? '');
                    $first = (string)($row[1] ?? '');
                    $middle = (string)($row[2] ?? '');
                    $last = (string)($row[3] ?? '');
                    $suffix = (string)($row[4] ?? '');
                    $sex = (string)($row[5] ?? '');
                    $grade = (string)($row[6] ?? '');
                    $section = (string)($row[7] ?? '');
                    $contact = (string)($row[8] ?? '');
                    $email = (string)($row[9] ?? '');

                    $valid = true;

                    if ($lrn === '' || !ctype_digit($lrn) || strlen($lrn) !== 12) {
                        $valid = false;
                    }

                    if ($first === '' || $last === '') {
                        $valid = false;
                    }

                    if (!in_array($sex, ['Male', 'Female'], true)) {
                        $valid = false;
                    }

                    if ($grade === '' || !ctype_digit($grade)) {
                        $valid = false;
                    } else {
                        $g = (int)$grade;
                        if ($g < 7 || $g > 12) {
                            $valid = false;
                        }
                    }

                    if ($section === '') {
                        $valid = false;
                    }

                    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $valid = false;
                    }

                    if (!$valid) {
                        $results['skipped']++;
                        continue;
                    }

                    $stmt = $pdo->prepare('SELECT student_id FROM students WHERE lrn = :lrn LIMIT 1');
                    $stmt->execute([':lrn' => $lrn]);
                    if ($stmt->fetch()) {
                        $results['skipped']++;
                        continue;
                    }

                    $stmt = $pdo->prepare(
                        'INSERT INTO students (lrn, first_name, middle_name, last_name, suffix, sex, grade_level, section, contact_number, email, qr_token, status)
                         VALUES (:lrn, :first_name, :middle_name, :last_name, :suffix, :sex, :grade_level, :section, :contact_number, :email, :qr_token, "Active")'
                    );

                    $stmt->execute([
                        ':lrn' => $lrn,
                        ':first_name' => $first,
                        ':middle_name' => $middle !== '' ? $middle : null,
                        ':last_name' => $last,
                        ':suffix' => $suffix !== '' ? $suffix : null,
                        ':sex' => $sex,
                        ':grade_level' => (int)$grade,
                        ':section' => $section,
                        ':contact_number' => $contact !== '' ? $contact : null,
                        ':email' => $email !== '' ? $email : null,
                        ':qr_token' => generate_qr_token(),
                    ]);

                    $studentId = (int)$pdo->lastInsertId();

                    if ($selectedScheduleIds) {
                        $stmtEnroll = $pdo->prepare('INSERT INTO student_schedules (student_id, schedule_id, status) VALUES (:student_id, :schedule_id, "Active")');
                        foreach ($selectedScheduleIds as $sid) {
                            $stmtEnroll->execute([':student_id' => $studentId, ':schedule_id' => (int)$sid]);
                        }
                    }

                    $results['inserted']++;
                }

                fclose($handle);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                fclose($handle);
                $errors[] = 'Import failed.';
            }
        }
    }
}

$title = 'Import Students';
require __DIR__ . '/partials/layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Import Students (CSV)</h1>
  <a class="btn btn-outline-secondary btn-sm" href="<?= h(url('students.php')) ?>">Back</a>
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

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors): ?>
  <div class="alert alert-success">
    Imported: <?= h((string)$results['inserted']) ?>, Skipped: <?= h((string)$results['skipped']) ?>
  </div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post" enctype="multipart/form-data" action="<?= h(url('import_students.php')) ?>">
      <div class="mb-3">
        <label class="form-label">CSV File *</label>
        <input class="form-control" type="file" name="csv_file" accept=".csv" required>
        <div class="form-text">Columns (order): LRN, First Name, Middle Name, Last Name, Suffix, Sex, Grade, Section, Contact, Email</div>
      </div>

      <div class="mb-3">
        <label class="form-label">Auto-enroll imported students to your schedules (optional)</label>
        <div class="border rounded p-3 bg-white">
          <?php if (!$teacherSchedules): ?>
            <div class="text-muted">No schedules available.</div>
          <?php else: ?>
            <?php foreach ($teacherSchedules as $sch): ?>
              <?php
                $sid = (int)$sch['schedule_id'];
                $checked = in_array($sid, $selectedScheduleIds, true);
                $label = (string)$sch['subject_name'] . ' — ' . (string)$sch['day_of_week'] . ' ' . (string)$sch['start_time'] . '-' . (string)$sch['end_time'] . ' (' . (string)$sch['grade_level'] . '-' . (string)$sch['section'] . ')';
              ?>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="schedule_ids[]" value="<?= h((string)$sid) ?>" id="imp_sch_<?= h((string)$sid) ?>" <?= $checked ? 'checked' : '' ?>>
                <label class="form-check-label" for="imp_sch_<?= h((string)$sid) ?>"><?= h($label) ?></label>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="d-grid">
        <button class="btn btn-primary" type="submit">Import</button>
      </div>
    </form>
  </div>
</div>

<?php
require __DIR__ . '/partials/layout_bottom.php';
