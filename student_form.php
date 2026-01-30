<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/tokens.php';

require_login();

$teacherId = (int)$_SESSION['teacher_id'];
$isAdmin = is_admin();
$taughtGrades = teacher_grade_levels_taught($teacherId);
$id = (int)($_GET['id'] ?? 0);

$values = [
    'lrn' => '',
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'suffix' => '',
    'sex' => '',
    'grade_level' => '',
    'section' => '',
    'contact_number' => '',
    'email' => '',
    'status' => 'Active',
];

$errors = [];

$pdo = db();

$gradeSectionsActive = [];
$masterGrades = [];
$sectionsByGrade = [];
try {
    $stmt = $pdo->prepare('SELECT grade_level, section FROM grade_sections WHERE status = "Active" ORDER BY grade_level ASC, section ASC');
    $stmt->execute();
    $gradeSectionsActive = $stmt->fetchAll();
} catch (Throwable $e) {
    $gradeSectionsActive = [];
}

foreach ($gradeSectionsActive as $r) {
    $g = (int)($r['grade_level'] ?? 0);
    $s = (string)($r['section'] ?? '');
    if ($g < 7 || $g > 12 || $s === '') {
        continue;
    }
    $sectionsByGrade[$g] = $sectionsByGrade[$g] ?? [];
    $sectionsByGrade[$g][] = $s;
}

$masterGrades = array_keys($sectionsByGrade);
sort($masterGrades);

$loadAdminSchedules = static function (PDO $pdo, string $grade, string $section): array {
    if ($grade === '' || $section === '' || !ctype_digit($grade)) {
        return [];
    }

    $g = (int)$grade;
    if ($g < 7 || $g > 12) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT s.schedule_id, s.teacher_id, s.subject_name, s.grade_level, s.section, s.day_of_week, s.start_time, s.end_time, s.school_year, s.status,
                t.first_name AS teacher_first_name, t.last_name AS teacher_last_name, t.suffix AS teacher_suffix
         FROM schedules s
         JOIN teachers t ON t.teacher_id = s.teacher_id
         WHERE s.status = "Active"
           AND s.grade_level = :grade_level
           AND s.section = :section
         ORDER BY FIELD(s.day_of_week, "Monday","Tuesday","Wednesday","Thursday","Friday"), s.start_time'
    );
    $stmt->execute([':grade_level' => $g, ':section' => $section]);
    return $stmt->fetchAll();
};

$loadTeacherSchedules = static function (PDO $pdo, int $teacherId): array {
    $stmt = $pdo->prepare(
        'SELECT *
         FROM schedules
         WHERE teacher_id = :teacher_id AND status != "Archived"
         ORDER BY FIELD(day_of_week, "Monday","Tuesday","Wednesday","Thursday","Friday"), start_time'
    );
    $stmt->execute([':teacher_id' => $teacherId]);
    return $stmt->fetchAll();
};

$availableSchedules = [];

$selectedScheduleIds = [];

$existing = null;
if ($id > 0) {
    if ($isAdmin) {
        $stmt = $pdo->prepare('SELECT * FROM students WHERE student_id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $existing = $stmt->fetch();
    } elseif ($taughtGrades) {
        $placeholders = [];
        $params = [':id' => $id];
        foreach ($taughtGrades as $i => $g) {
            $ph = ':tg' . $i;
            $placeholders[] = $ph;
            $params[$ph] = $g;
        }
        $stmt = $pdo->prepare('SELECT * FROM students WHERE student_id = :id AND grade_level IN (' . implode(',', $placeholders) . ') LIMIT 1');
        $stmt->execute($params);
        $existing = $stmt->fetch();
    } else {
        $stmt = $pdo->prepare(
            'SELECT *
             FROM students
             WHERE student_id = :id
               AND EXISTS (
                 SELECT 1
                 FROM student_schedules ss
                 JOIN schedules sch ON sch.schedule_id = ss.schedule_id
                 WHERE ss.student_id = students.student_id
                   AND ss.status = "Active"
                   AND sch.teacher_id = :teacher_id
                   AND sch.status != "Archived"
               )
             LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':teacher_id' => $teacherId]);
        $existing = $stmt->fetch();
    }

    if (!$existing) {
        redirect('students.php');
    }

    foreach ($values as $k => $_) {
        $values[$k] = (string)($existing[$k] ?? $values[$k]);
    }
}

$availableSchedules = $isAdmin
    ? $loadAdminSchedules($pdo, (string)$values['grade_level'], (string)$values['section'])
    : $loadTeacherSchedules($pdo, $teacherId);

if ($id > 0 && $availableSchedules) {
    $scheduleIds = array_map(static fn ($s) => (int)$s['schedule_id'], $availableSchedules);
    $in = implode(',', array_fill(0, count($scheduleIds), '?'));

    $stmt = $pdo->prepare(
        'SELECT schedule_id FROM student_schedules
         WHERE student_id = ? AND status = "Active" AND schedule_id IN (' . $in . ')'
    );

    $stmt->execute(array_merge([$id], $scheduleIds));
    $selectedScheduleIds = array_map(static fn ($r) => (int)$r['schedule_id'], $stmt->fetchAll());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? 'save'));

    foreach ($values as $k => $_) {
        $values[$k] = trim((string)($_POST[$k] ?? ''));
    }

    $availableSchedules = $isAdmin
        ? $loadAdminSchedules($pdo, (string)$values['grade_level'], (string)$values['section'])
        : $loadTeacherSchedules($pdo, $teacherId);

    $selectedScheduleIds = array_map('intval', (array)($_POST['schedule_ids'] ?? []));

    if ($action === 'load') {
    } else {

    if ($values['lrn'] === '' || !ctype_digit($values['lrn']) || strlen($values['lrn']) !== 12) {
        $errors[] = 'LRN must be exactly 12 digits.';
    }

    if ($values['first_name'] === '') {
        $errors[] = 'First name is required.';
    }

    if ($values['last_name'] === '') {
        $errors[] = 'Last name is required.';
    }

    if (!in_array($values['sex'], ['Male', 'Female'], true)) {
        $errors[] = 'Sex is required.';
    }

    if ($values['grade_level'] === '' || !ctype_digit($values['grade_level'])) {
        $errors[] = 'Grade level is required.';
    } else {
        $g = (int)$values['grade_level'];
        if ($g < 7 || $g > 12) {
            $errors[] = 'Grade level must be between 7 and 12.';
        }
        if (!$isAdmin && $taughtGrades && !in_array($g, $taughtGrades, true)) {
            $errors[] = 'Grade level is not allowed.';
        }
    }

    if ($values['section'] === '') {
        $errors[] = 'Section is required.';
    }

    if (!$errors && $masterGrades) {
        $g = (int)$values['grade_level'];
        $ok = isset($sectionsByGrade[$g]) && in_array($values['section'], $sectionsByGrade[$g], true);
        if (!$ok) {
            $errors[] = 'Invalid grade level and section.';
        }
    }

    if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email is invalid.';
    }

    if (!in_array($values['status'], ['Active', 'Inactive', 'Graduated'], true)) {
        $errors[] = 'Invalid status.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT student_id, lrn FROM students WHERE lrn = :lrn LIMIT 1');
        $stmt->execute([':lrn' => $values['lrn']]);
        $dup = $stmt->fetch();

        if ($dup && (int)$dup['student_id'] !== $id) {
            $errors[] = 'LRN already exists.';
        }
    }

    if (!$errors) {
        $allowedScheduleIds = array_map(static fn ($s) => (int)$s['schedule_id'], $availableSchedules);
        $allowed = array_flip($allowedScheduleIds);
        foreach ($selectedScheduleIds as $sid) {
            if (!isset($allowed[$sid])) {
                $errors[] = 'Invalid schedule selection.';
                break;
            }
        }
    }

    if (!$errors && $id === 0 && $availableSchedules && !$selectedScheduleIds) {
        $errors[] = 'Please select at least one schedule for enrollment.';
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $qrToken = null;

            if ($id > 0) {
                $qrToken = (string)($existing['qr_token'] ?? '');

                if ((string)($existing['lrn'] ?? '') !== $values['lrn']) {
                    $qrToken = generate_qr_token();
                }

                if ($values['status'] !== 'Active') {
                    $qrToken = generate_qr_token();
                }

                $stmt = $pdo->prepare(
                    'UPDATE students
                     SET lrn = :lrn,
                         first_name = :first_name,
                         middle_name = :middle_name,
                         last_name = :last_name,
                         suffix = :suffix,
                         sex = :sex,
                         grade_level = :grade_level,
                         section = :section,
                         contact_number = :contact_number,
                         email = :email,
                         status = :status,
                         qr_token = :qr_token
                     WHERE student_id = :id'
                );

                $stmt->execute([
                    ':lrn' => $values['lrn'],
                    ':first_name' => $values['first_name'],
                    ':middle_name' => $values['middle_name'] !== '' ? $values['middle_name'] : null,
                    ':last_name' => $values['last_name'],
                    ':suffix' => $values['suffix'] !== '' ? $values['suffix'] : null,
                    ':sex' => $values['sex'],
                    ':grade_level' => (int)$values['grade_level'],
                    ':section' => $values['section'],
                    ':contact_number' => $values['contact_number'] !== '' ? $values['contact_number'] : null,
                    ':email' => $values['email'] !== '' ? $values['email'] : null,
                    ':status' => $values['status'],
                    ':qr_token' => $qrToken,
                    ':id' => $id,
                ]);
            } else {
                $qrToken = generate_qr_token();

                $stmt = $pdo->prepare(
                    'INSERT INTO students (lrn, first_name, middle_name, last_name, suffix, sex, grade_level, section, contact_number, email, qr_token, status)
                     VALUES (:lrn, :first_name, :middle_name, :last_name, :suffix, :sex, :grade_level, :section, :contact_number, :email, :qr_token, :status)'
                );

                $stmt->execute([
                    ':lrn' => $values['lrn'],
                    ':first_name' => $values['first_name'],
                    ':middle_name' => $values['middle_name'] !== '' ? $values['middle_name'] : null,
                    ':last_name' => $values['last_name'],
                    ':suffix' => $values['suffix'] !== '' ? $values['suffix'] : null,
                    ':sex' => $values['sex'],
                    ':grade_level' => (int)$values['grade_level'],
                    ':section' => $values['section'],
                    ':contact_number' => $values['contact_number'] !== '' ? $values['contact_number'] : null,
                    ':email' => $values['email'] !== '' ? $values['email'] : null,
                    ':qr_token' => $qrToken,
                    ':status' => $values['status'],
                ]);

                $id = (int)$pdo->lastInsertId();
            }

            if ($availableSchedules) {
                $scopeScheduleIds = array_map(static fn ($s) => (int)$s['schedule_id'], $availableSchedules);
                $in = implode(',', array_fill(0, count($scopeScheduleIds), '?'));

                $stmt = $pdo->prepare('DELETE FROM student_schedules WHERE student_id = ? AND schedule_id IN (' . $in . ')');
                $stmt->execute(array_merge([$id], $scopeScheduleIds));

                if ($selectedScheduleIds) {
                    $stmt = $pdo->prepare('INSERT INTO student_schedules (student_id, schedule_id, status) VALUES (:student_id, :schedule_id, "Active")');
                    foreach ($selectedScheduleIds as $sid) {
                        $stmt->execute([':student_id' => $id, ':schedule_id' => (int)$sid]);
                    }
                }
            }

            $pdo->commit();
            redirect('student_view.php?id=' . (int)$id . '&saved=1');
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Failed to save student.';
        }
    }

    }
}

$title = $id > 0 ? 'Edit Student' : 'Add Student';
require __DIR__ . '/partials/layout_top.php';
?>

<div class="bnhs-page-header">
  <h1 class="bnhs-page-title"><?= h($title) ?></h1>
  <div class="bnhs-page-actions">
    <a class="btn btn-outline-secondary btn-sm" href="<?= h(url('students.php')) ?>">Back</a>
  </div>
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
    <form method="post" action="<?= h(url($id > 0 ? ('student_form.php?id=' . $id) : 'student_form.php')) ?>">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">LRN *</label>
          <input class="form-control" name="lrn" value="<?= h($values['lrn']) ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Grade Level *</label>
          <?php if ($masterGrades): ?>
            <select class="form-select" name="grade_level" id="grade_level" required>
              <option value="">Select...</option>
              <?php foreach ($masterGrades as $g): ?>
                <?php if (!$isAdmin && $taughtGrades && !in_array((int)$g, $taughtGrades, true)) continue; ?>
                <option value="<?= (int)$g ?>" <?= (string)$values['grade_level'] === (string)$g ? 'selected' : '' ?>><?= (int)$g ?></option>
              <?php endforeach; ?>
              <?php if ($id > 0 && $values['grade_level'] !== '' && ctype_digit($values['grade_level']) && !in_array((int)$values['grade_level'], $masterGrades, true)): ?>
                <option value="<?= (int)$values['grade_level'] ?>" selected><?= (int)$values['grade_level'] ?> (current)</option>
              <?php endif; ?>
            </select>
          <?php else: ?>
            <input class="form-control" name="grade_level" value="<?= h($values['grade_level']) ?>" required>
          <?php endif; ?>
        </div>
        <div class="col-md-4">
          <label class="form-label">Section *</label>
          <?php if ($masterGrades && $values['grade_level'] !== '' && ctype_digit($values['grade_level'])): ?>
            <?php $gSel = (int)$values['grade_level']; ?>
            <select class="form-select" name="section" id="section" required>
              <option value="">Select...</option>
              <?php foreach (($sectionsByGrade[$gSel] ?? []) as $sec): ?>
                <option value="<?= h($sec) ?>" <?= $values['section'] === $sec ? 'selected' : '' ?>><?= h($sec) ?></option>
              <?php endforeach; ?>
              <?php if ($id > 0 && $values['section'] !== '' && !in_array($values['section'], ($sectionsByGrade[$gSel] ?? []), true)): ?>
                <option value="<?= h($values['section']) ?>" selected><?= h($values['section']) ?> (current)</option>
              <?php endif; ?>
            </select>
            <div class="form-text">Change Grade Level then save to update sections.</div>
          <?php elseif ($masterGrades): ?>
            <select class="form-select" name="section" id="section" required>
              <option value="" selected>Select grade level first</option>
            </select>
          <?php else: ?>
            <input class="form-control" name="section" value="<?= h($values['section']) ?>" required>
          <?php endif; ?>
        </div>

        <div class="col-md-4">
          <label class="form-label">First Name *</label>
          <input class="form-control" name="first_name" value="<?= h($values['first_name']) ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Middle Name</label>
          <input class="form-control" name="middle_name" value="<?= h($values['middle_name']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Last Name *</label>
          <input class="form-control" name="last_name" value="<?= h($values['last_name']) ?>" required>
        </div>

        <div class="col-md-4">
          <label class="form-label">Suffix</label>
          <input class="form-control" name="suffix" value="<?= h($values['suffix']) ?>" placeholder="e.g., Jr., Sr., III">
        </div>
        <div class="col-md-4">
          <label class="form-label">Sex *</label>
          <select class="form-select" name="sex" required>
            <option value="" <?= $values['sex'] === '' ? 'selected' : '' ?>>Select...</option>
            <option value="Male" <?= $values['sex'] === 'Male' ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= $values['sex'] === 'Female' ? 'selected' : '' ?>>Female</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Status *</label>
          <select class="form-select" name="status" required>
            <option value="Active" <?= $values['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
            <option value="Inactive" <?= $values['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
            <option value="Graduated" <?= $values['status'] === 'Graduated' ? 'selected' : '' ?>>Graduated</option>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Contact Number</label>
          <input class="form-control" name="contact_number" value="<?= h($values['contact_number']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" name="email" value="<?= h($values['email']) ?>">
        </div>

        <div class="col-12">
          <label class="form-label"><?= $isAdmin ? 'Enroll to Schedules' : 'Enroll to Your Schedules' ?></label>
          <div class="border rounded p-3 bg-white">
            <?php if ($isAdmin): ?>
              <div class="d-flex justify-content-end">
                <button type="submit" name="action" value="load" class="btn btn-outline-secondary btn-sm" formnovalidate>Refresh schedules</button>
              </div>
              <hr>
            <?php endif; ?>
            <?php if (!$availableSchedules): ?>
              <div class="text-muted"><?= $isAdmin ? 'No schedules available for this grade and section.' : 'No schedules available. Create schedules first.' ?></div>
            <?php else: ?>
              <div class="row g-2">
                <?php foreach ($availableSchedules as $sch): ?>
                  <?php
                    $sid = (int)$sch['schedule_id'];
                    $checked = in_array($sid, $selectedScheduleIds, true);
                    $label = (string)$sch['subject_name'];
                    if ($isAdmin) {
                        $tSuffix = trim((string)($sch['teacher_suffix'] ?? ''));
                        $tName = trim((string)($sch['teacher_last_name'] ?? '') . ', ' . (string)($sch['teacher_first_name'] ?? ''));
                        if ($tSuffix !== '') {
                            $tName .= ' ' . $tSuffix;
                        }
                        if ($tName !== '') {
                            $label .= ' — ' . $tName;
                        }
                    }
                    $label .= ' — ' . (string)$sch['day_of_week'] . ' ' . (string)$sch['start_time'] . '-' . (string)$sch['end_time'] . ' (' . (string)$sch['grade_level'] . '-' . (string)$sch['section'] . ')';
                  ?>
                  <div class="col-12">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="schedule_ids[]" value="<?= h((string)$sid) ?>" id="sch_<?= h((string)$sid) ?>" <?= $checked ? 'checked' : '' ?>>
                      <label class="form-check-label" for="sch_<?= h((string)$sid) ?>"><?= h($label) ?></label>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="d-grid mt-3">
        <button class="btn btn-primary" type="submit">Save Student</button>
      </div>
    </form>
  </div>
</div>

<?php
if ($masterGrades):
    $sectionsJson = json_encode($sectionsByGrade, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<script>
  (function () {
    const sectionsByGrade = <?= $sectionsJson ?>;
    const gradeEl = document.getElementById('grade_level');
    const sectionEl = document.getElementById('section');
    if (!gradeEl || !sectionEl) return;

    function setOptions(grade, preserveValue) {
      const list = (grade && sectionsByGrade[grade]) ? sectionsByGrade[grade] : [];
      const prev = preserveValue !== undefined ? preserveValue : sectionEl.value;
      sectionEl.innerHTML = '';

      const opt0 = document.createElement('option');
      opt0.value = '';
      opt0.textContent = list.length ? 'Select...' : 'Select grade level first';
      sectionEl.appendChild(opt0);

      list.forEach(function (s) {
        const opt = document.createElement('option');
        opt.value = s;
        opt.textContent = s;
        sectionEl.appendChild(opt);
      });

      if (prev && list.indexOf(prev) !== -1) {
        sectionEl.value = prev;
      } else {
        sectionEl.value = '';
      }
    }

    gradeEl.addEventListener('change', function () {
      setOptions(gradeEl.value);
    });

    setOptions(gradeEl.value, <?= json_encode((string)$values['section'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
  })();
</script>
<?php
endif;

require __DIR__ . '/partials/layout_bottom.php';
