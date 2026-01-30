<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/tokens.php';

$title = 'Student Registration';

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
];

$errors = [];
$pdo = db();

$selectedScheduleIds = [];

$schedules = [];

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

$loadSchedules = static function (PDO $pdo, string $grade, string $section): array {
    if ($grade === '' || $section === '' || !ctype_digit($grade)) {
        return [];
    }

    $g = (int)$grade;
    if ($g < 7 || $g > 12) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT s.schedule_id, s.subject_name, s.day_of_week, s.start_time, s.end_time, s.room, s.semester, s.school_year,
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? 'load'));

    foreach ($values as $k => $_) {
        $values[$k] = trim((string)($_POST[$k] ?? ''));
    }

    $selectedScheduleIds = array_map('intval', (array)($_POST['schedule_ids'] ?? []));

    $schedules = $loadSchedules($pdo, $values['grade_level'], $values['section']);

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

        $allowedScheduleIds = array_map(static fn ($r) => (int)$r['schedule_id'], $schedules);
        $allowed = array_flip($allowedScheduleIds);

        if (!$errors) {
            if (!$allowedScheduleIds) {
                $errors[] = 'No active schedules are available for your grade and section. Please contact your teacher.';
            }
        }

        if (!$errors) {
            foreach ($selectedScheduleIds as $sid) {
                if (!isset($allowed[$sid])) {
                    $errors[] = 'Invalid schedule selection.';
                    break;
                }
            }
        }

        if (!$errors && !$selectedScheduleIds) {
            $errors[] = 'Please select at least one subject schedule.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('SELECT student_id FROM students WHERE lrn = :lrn LIMIT 1');
            $stmt->execute([':lrn' => $values['lrn']]);
            $dup = $stmt->fetch();

            if ($dup) {
                $errors[] = 'LRN already registered. Use QR regeneration instead.';
            }
        }

        if (!$errors) {
            $pdo->beginTransaction();
            try {
                $qrToken = '';
                $studentId = 0;

                for ($i = 0; $i < 3; $i++) {
                    $qrToken = generate_qr_token();

                    try {
                        $stmt = $pdo->prepare(
                            'INSERT INTO students (lrn, first_name, middle_name, last_name, suffix, sex, grade_level, section, contact_number, email, qr_token, status)
                             VALUES (:lrn, :first_name, :middle_name, :last_name, :suffix, :sex, :grade_level, :section, :contact_number, :email, :qr_token, "Active")'
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
                        ]);

                        $studentId = (int)$pdo->lastInsertId();
                        break;
                    } catch (PDOException $e) {
                        if (($e->getCode() ?? '') !== '23000') {
                            throw $e;
                        }
                    }
                }

                if ($studentId <= 0) {
                    throw new RuntimeException('Failed to generate unique QR token.');
                }

                $stmtEnroll = $pdo->prepare('INSERT INTO student_schedules (student_id, schedule_id, status) VALUES (:student_id, :schedule_id, "Active")');
                foreach ($selectedScheduleIds as $sid) {
                    $stmtEnroll->execute([':student_id' => $studentId, ':schedule_id' => (int)$sid]);
                }

                $pdo->commit();
                redirect('student_qr.php?lrn=' . urlencode($values['lrn']) . '&token=' . urlencode($qrToken) . '&registered=1');
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = 'Registration failed.';
            }
        }
    }
} else {
    $schedules = [];
}

if (!$schedules && $values['grade_level'] !== '' && $values['section'] !== '') {
    $schedules = $loadSchedules($pdo, $values['grade_level'], $values['section']);
}

require __DIR__ . '/partials/layout_top.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-body">
        <h1 class="h4 mb-3">Student Registration</h1>

        <?php if ($errors): ?>
          <div class="alert alert-danger">
            <ul class="mb-0">
              <?php foreach ($errors as $e): ?>
                <li><?= h($e) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= h(url('student_register.php')) ?>">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">LRN (12 digits)</label>
              <input class="form-control" name="lrn" value="<?= h($values['lrn']) ?>" maxlength="12" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Sex</label>
              <select class="form-select" name="sex" required>
                <option value="" <?= $values['sex'] === '' ? 'selected' : '' ?>>Select</option>
                <option value="Male" <?= $values['sex'] === 'Male' ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= $values['sex'] === 'Female' ? 'selected' : '' ?>>Female</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">First Name</label>
              <input class="form-control" name="first_name" value="<?= h($values['first_name']) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Middle Name</label>
              <input class="form-control" name="middle_name" value="<?= h($values['middle_name']) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Last Name</label>
              <input class="form-control" name="last_name" value="<?= h($values['last_name']) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Suffix</label>
              <input class="form-control" name="suffix" value="<?= h($values['suffix']) ?>" placeholder="e.g., Jr., III">
            </div>

            <div class="col-md-4">
              <label class="form-label">Grade Level</label>
              <select class="form-select" name="grade_level" id="grade_level" required>
                <option value="" <?= $values['grade_level'] === '' ? 'selected' : '' ?>>Select</option>
                <?php if ($masterGrades): ?>
                  <?php foreach ($masterGrades as $g): ?>
                    <option value="<?= (int)$g ?>" <?= (string)$g === $values['grade_level'] ? 'selected' : '' ?>>Grade <?= (int)$g ?></option>
                  <?php endforeach; ?>
                <?php else: ?>
                  <?php for ($g = 7; $g <= 12; $g++): ?>
                    <option value="<?= $g ?>" <?= (string)$g === $values['grade_level'] ? 'selected' : '' ?>>Grade <?= $g ?></option>
                  <?php endfor; ?>
                <?php endif; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Section</label>
              <?php if ($masterGrades && $values['grade_level'] !== '' && ctype_digit($values['grade_level'])): ?>
                <?php $gSel = (int)$values['grade_level']; ?>
                <select class="form-select" name="section" id="section" required>
                  <option value="" <?= $values['section'] === '' ? 'selected' : '' ?>>Select</option>
                  <?php foreach (($sectionsByGrade[$gSel] ?? []) as $sec): ?>
                    <option value="<?= h($sec) ?>" <?= $values['section'] === $sec ? 'selected' : '' ?>><?= h($sec) ?></option>
                  <?php endforeach; ?>
                </select>
                <div class="form-text">Change Grade Level then click Refresh to update sections.</div>
              <?php elseif ($masterGrades): ?>
                <select class="form-select" name="section" id="section" required>
                  <option value="" selected>Select grade level first</option>
                </select>
              <?php else: ?>
                <input class="form-control" name="section" value="<?= h($values['section']) ?>" required>
              <?php endif; ?>
            </div>

            <div class="col-md-6">
              <label class="form-label">Contact Number</label>
              <input class="form-control" name="contact_number" value="<?= h($values['contact_number']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email (optional)</label>
              <input class="form-control" name="email" value="<?= h($values['email']) ?>">
            </div>

            <div class="col-12">
              <div class="border rounded p-3 bg-light">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <div class="fw-semibold">Select your subjects</div>
                    <div class="text-muted small">Schedules are shown for your grade and section.</div>
                  </div>
                  <button type="submit" name="action" value="load" class="btn btn-outline-secondary btn-sm" formnovalidate>Refresh</button>
                </div>

                <hr>

                <?php if (!$values['grade_level'] || !$values['section']): ?>
                  <div class="text-muted">Enter Grade Level and Section, then click Refresh to load schedules.</div>
                <?php elseif (!$schedules): ?>
                  <div class="text-muted">No schedules found for Grade <?= h($values['grade_level']) ?> - <?= h($values['section']) ?>.</div>
                <?php else: ?>
                  <div class="row g-2">
                    <?php foreach ($schedules as $sch): ?>
                      <?php
                        $tSuffix = trim((string)($sch['teacher_suffix'] ?? ''));
                        $tName = trim((string)$sch['teacher_first_name'] . ' ' . (string)$sch['teacher_last_name']);
                        if ($tSuffix !== '') {
                            $tName .= ', ' . $tSuffix;
                        }
                        $sid = (int)$sch['schedule_id'];
                        $checked = in_array($sid, $selectedScheduleIds, true);
                      ?>
                      <div class="col-md-6">
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" name="schedule_ids[]" value="<?= $sid ?>" id="sch<?= $sid ?>" <?= $checked ? 'checked' : '' ?>>
                          <label class="form-check-label" for="sch<?= $sid ?>">
                            <div class="fw-semibold"><?= h((string)$sch['subject_name']) ?></div>
                            <div class="small text-muted"><?= h((string)$sch['day_of_week']) ?> <?= h((string)$sch['start_time']) ?>-<?= h((string)$sch['end_time']) ?> | <?= h($tName) ?></div>
                          </label>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="col-12 d-flex justify-content-between align-items-center">
              <a class="btn btn-link" href="<?= h(url('qr_regenerate.php')) ?>">Already registered? Regenerate QR</a>
              <button class="btn btn-primary" type="submit" name="action" value="register">Register & Generate QR</button>
            </div>
          </div>
        </form>
      </div>
    </div>
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
      opt0.textContent = list.length ? 'Select' : 'Select grade level first';
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
