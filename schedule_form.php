<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

$teacherId = (int)$_SESSION['teacher_id'];
$id = (int)($_GET['id'] ?? 0);

$isAdmin = is_admin();
$taughtGrades = teacher_grade_levels_taught($teacherId);

$values = [
    'teacher_id' => (string)$teacherId,
    'subject_name' => '',
    'grade_level' => '',
    'section' => '',
    'day_of_week' => '',
    'start_time' => '',
    'end_time' => '',
    'room' => '',
    'school_year' => '',
    'status' => 'Active',
];

if ($isAdmin && $id === 0) {
    $values['teacher_id'] = '';
}

$selectedDays = [];

$errors = [];
$pdo = db();

$teachers = [];
if ($isAdmin) {
    try {
        $stmt = $pdo->prepare(
            'SELECT teacher_id, first_name, last_name, suffix, email
             FROM teachers
             WHERE status = "Active"
             ORDER BY last_name, first_name'
        );
        $stmt->execute();
        $teachers = $stmt->fetchAll();
    } catch (Throwable $e) {
        $teachers = [];
    }
}

try {
    $pdo->exec('ALTER TABLE schedules MODIFY semester ENUM("1st","2nd") NULL');
} catch (Throwable $e) {
}

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

if ($id > 0) {
    if ($isAdmin) {
        $stmt = $pdo->prepare('SELECT * FROM schedules WHERE schedule_id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $existing = $stmt->fetch();
    } else {
        $stmt = $pdo->prepare('SELECT * FROM schedules WHERE schedule_id = :id AND teacher_id = :teacher_id LIMIT 1');
        $stmt->execute([':id' => $id, ':teacher_id' => $teacherId]);
        $existing = $stmt->fetch();
    }

    if (!$existing) {
        redirect('schedules.php');
    }

    foreach ($values as $k => $_) {
        $values[$k] = (string)($existing[$k] ?? $values[$k]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $k => $_) {
        $values[$k] = trim((string)($_POST[$k] ?? ''));
    }

    $effectiveTeacherId = $teacherId;
    if ($isAdmin) {
        if ($values['teacher_id'] === '' || !ctype_digit($values['teacher_id'])) {
            $errors[] = 'Teacher is required.';
        } else {
            $effectiveTeacherId = (int)$values['teacher_id'];
            if ($effectiveTeacherId <= 0) {
                $errors[] = 'Teacher is required.';
            }
        }
    }

    if ($id > 0) {
        $selectedDays = [$values['day_of_week']];
    } else {
        $selectedDays = array_values(array_unique(array_map('strval', (array)($_POST['days_of_week'] ?? []))));
    }

    if ($values['subject_name'] === '') {
        $errors[] = 'Subject name is required.';
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

    if ($id > 0) {
        if (!in_array($values['day_of_week'], ['Monday','Tuesday','Wednesday','Thursday','Friday'], true)) {
            $errors[] = 'Day of week is required.';
        }
    } else {
        $validDays = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
        $finalDays = [];
        foreach ($selectedDays as $d) {
            if (in_array($d, $validDays, true)) {
                $finalDays[] = $d;
            }
        }
        $selectedDays = array_values(array_unique($finalDays));
        if (!$selectedDays) {
            $errors[] = 'Select at least one day.';
        }
    }

    if ($values['start_time'] === '' || $values['end_time'] === '') {
        $errors[] = 'Start and End time are required.';
    } elseif (strtotime($values['end_time']) <= strtotime($values['start_time'])) {
        $errors[] = 'End time must be after start time.';
    }

    if ($values['school_year'] === '') {
        $errors[] = 'School year is required.';
    }

    if (!in_array($values['status'], ['Active','Inactive','Archived'], true)) {
        $errors[] = 'Invalid status.';
    }

    if (!$errors) {
        $conflictSql =
            'SELECT COUNT(*) AS cnt
             FROM schedules
             WHERE teacher_id = :teacher_id
               AND day_of_week = :day_of_week
               AND status != "Archived"
               AND schedule_id != :schedule_id
               AND start_time < :end_time
               AND end_time > :start_time';

        $stmt = $pdo->prepare($conflictSql);
        foreach ($selectedDays as $d) {
            $stmt = $pdo->prepare($conflictSql);
            $stmt->execute([
                ':teacher_id' => $effectiveTeacherId,
                ':day_of_week' => $d,
                ':schedule_id' => $id,
                ':start_time' => $values['start_time'],
                ':end_time' => $values['end_time'],
            ]);
            $cnt = (int)($stmt->fetch()['cnt'] ?? 0);

            if ($cnt > 0) {
                $errors[] = 'You have a conflicting schedule at this time.';
                break;
            }
        }
    }

    if (!$errors) {
        if ($id > 0) {
            $currentRole = (string)($_SESSION['role'] ?? '');
            if ($currentRole === 'Teacher') {
                // Teacher: submit schedule edit as a change request
                try {
                    $oldData = [
                        'subject_name' => (string)($existing['subject_name'] ?? ''),
                        'grade_level' => (string)($existing['grade_level'] ?? ''),
                        'section' => (string)($existing['section'] ?? ''),
                        'day_of_week' => (string)($existing['day_of_week'] ?? ''),
                        'start_time' => (string)($existing['start_time'] ?? ''),
                        'end_time' => (string)($existing['end_time'] ?? ''),
                        'room' => (string)($existing['room'] ?? ''),
                        'school_year' => (string)($existing['school_year'] ?? ''),
                        'status' => (string)($existing['status'] ?? ''),
                    ];
                    $newData = [
                        'subject_name' => $values['subject_name'],
                        'grade_level' => $values['grade_level'],
                        'section' => $values['section'],
                        'day_of_week' => $values['day_of_week'],
                        'start_time' => $values['start_time'],
                        'end_time' => $values['end_time'],
                        'room' => $values['room'],
                        'school_year' => $values['school_year'],
                        'status' => $values['status'],
                    ];
                    $payload = json_encode(['old' => $oldData, 'new' => $newData], JSON_UNESCAPED_UNICODE);
                    $stmt = $pdo->prepare(
                        'INSERT INTO change_requests (teacher_id, request_type, target_id, payload)
                         VALUES (:teacher_id, "schedule_edit", :target_id, :payload)'
                    );
                    $stmt->execute([
                        ':teacher_id' => $teacherId,
                        ':target_id' => $id,
                        ':payload' => $payload,
                    ]);
                    redirect('schedules.php?requested=edit');
                } catch (Throwable $e) {
                    $errors[] = 'Failed to submit schedule edit request.';
                }
            } elseif ($isAdmin) {
                $stmt = $pdo->prepare(
                    'UPDATE schedules
                     SET teacher_id = :teacher_id,
                         subject_name = :subject_name,
                         grade_level = :grade_level,
                         section = :section,
                         day_of_week = :day_of_week,
                         start_time = :start_time,
                         end_time = :end_time,
                         room = :room,
                         school_year = :school_year,
                         status = :status
                     WHERE schedule_id = :id'
                );

                $stmt->execute([
                    ':teacher_id' => $effectiveTeacherId,
                    ':subject_name' => $values['subject_name'],
                    ':grade_level' => (int)$values['grade_level'],
                    ':section' => $values['section'],
                    ':day_of_week' => $values['day_of_week'],
                    ':start_time' => $values['start_time'],
                    ':end_time' => $values['end_time'],
                    ':room' => $values['room'] !== '' ? $values['room'] : null,
                    ':school_year' => $values['school_year'],
                    ':status' => $values['status'],
                    ':id' => $id,
                ]);
            }
        } else {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO schedules (teacher_id, subject_name, grade_level, section, day_of_week, start_time, end_time, room, school_year, status)
                     VALUES (:teacher_id, :subject_name, :grade_level, :section, :day_of_week, :start_time, :end_time, :room, :school_year, :status)'
                );

                foreach ($selectedDays as $d) {
                    $stmt->execute([
                        ':teacher_id' => $effectiveTeacherId,
                        ':subject_name' => $values['subject_name'],
                        ':grade_level' => (int)$values['grade_level'],
                        ':section' => $values['section'],
                        ':day_of_week' => $d,
                        ':start_time' => $values['start_time'],
                        ':end_time' => $values['end_time'],
                        ':room' => $values['room'] !== '' ? $values['room'] : null,
                        ':school_year' => $values['school_year'],
                        ':status' => $values['status'],
                    ]);
                }

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = 'Failed to save schedule.';
            }
        }

        if (!$errors) {
            redirect('schedules.php?saved=1');
        }
    }
}

$title = $id > 0 ? 'Edit Schedule' : 'Create Schedule';
require __DIR__ . '/partials/layout_top.php';
?>

<div class="bnhs-page-header">
  <h1 class="bnhs-page-title"><?= h($title) ?></h1>
  <div class="bnhs-page-actions">
    <a class="btn btn-outline-secondary btn-sm" href="<?= h(url('schedules.php')) ?>">Back</a>
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
    <form method="post" action="<?= h(url($id > 0 ? ('schedule_form.php?id=' . $id) : 'schedule_form.php')) ?>">
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Subject Name *</label>
          <input class="form-control" name="subject_name" value="<?= h($values['subject_name']) ?>" required>
        </div>
        <?php if ($isAdmin): ?>
          <div class="col-md-3">
            <label class="form-label">Teacher *</label>
            <select class="form-select" name="teacher_id" required>
              <option value="">Select...</option>
              <?php foreach ($teachers as $t): ?>
                <?php
                  $tid = (int)($t['teacher_id'] ?? 0);
                  $tSuffix = trim((string)($t['suffix'] ?? ''));
                  $tName = trim((string)($t['last_name'] ?? '') . ', ' . (string)($t['first_name'] ?? ''));
                  if ($tSuffix !== '') {
                      $tName .= ' ' . $tSuffix;
                  }
                ?>
                <option value="<?= (int)$tid ?>" <?= (string)$values['teacher_id'] === (string)$tid ? 'selected' : '' ?>><?= h($tName) ?></option>
              <?php endforeach; ?>
              <?php if ($id > 0 && $values['teacher_id'] !== '' && ctype_digit($values['teacher_id'])): ?>
                <?php
                  $found = false;
                  foreach ($teachers as $t) {
                      if ((string)($t['teacher_id'] ?? '') === (string)$values['teacher_id']) {
                          $found = true;
                          break;
                      }
                  }
                ?>
                <?php if (!$found): ?>
                  <option value="<?= (int)$values['teacher_id'] ?>" selected>Teacher #<?= (int)$values['teacher_id'] ?> (current)</option>
                <?php endif; ?>
              <?php endif; ?>
            </select>
          </div>
        <?php endif; ?>
        <div class="col-md-3">
          <label class="form-label">Grade Level *</label>
          <select class="form-select" name="grade_level" id="grade_level" required>
            <option value="">Select...</option>
            <?php if ($masterGrades): ?>
              <?php if (!$isAdmin && $taughtGrades): ?>
                <?php foreach ($taughtGrades as $g): ?>
                  <?php if (!in_array((int)$g, $masterGrades, true)) continue; ?>
                  <option value="<?= (int)$g ?>" <?= (string)$values['grade_level'] === (string)$g ? 'selected' : '' ?>><?= (int)$g ?></option>
                <?php endforeach; ?>
              <?php else: ?>
                <?php foreach ($masterGrades as $g): ?>
                  <option value="<?= (int)$g ?>" <?= (string)$values['grade_level'] === (string)$g ? 'selected' : '' ?>><?= (int)$g ?></option>
                <?php endforeach; ?>
              <?php endif; ?>
            <?php elseif (!$isAdmin && $taughtGrades): ?>
              <?php foreach ($taughtGrades as $g): ?>
                <option value="<?= (int)$g ?>" <?= (string)$values['grade_level'] === (string)$g ? 'selected' : '' ?>><?= (int)$g ?></option>
              <?php endforeach; ?>
            <?php else: ?>
              <?php for ($g = 7; $g <= 12; $g++): ?>
                <option value="<?= $g ?>" <?= (string)$values['grade_level'] === (string)$g ? 'selected' : '' ?>><?= $g ?></option>
              <?php endfor; ?>
            <?php endif; ?>
          </select>
        </div>
        <div class="col-md-3">
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
        <div class="col-md-3">
          <?php if ($id > 0): ?>
            <label class="form-label">Day *</label>
            <select class="form-select" name="day_of_week" required>
              <option value="">Select...</option>
              <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday'] as $d): ?>
                <option value="<?= h($d) ?>" <?= $values['day_of_week'] === $d ? 'selected' : '' ?>><?= h($d) ?></option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <label class="form-label">Days *</label>
            <div class="border rounded p-2 bg-white">
              <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday'] as $d): ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="days_of_week[]" value="<?= h($d) ?>" id="dow_<?= h($d) ?>" <?= in_array($d, $selectedDays, true) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="dow_<?= h($d) ?>"><?= h($d) ?></label>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="col-md-3">
          <label class="form-label">Room</label>
          <input class="form-control" name="room" value="<?= h($values['room']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Start Time *</label>
          <input type="time" class="form-control" name="start_time" value="<?= h($values['start_time']) ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">End Time *</label>
          <input type="time" class="form-control" name="end_time" value="<?= h($values['end_time']) ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">School Year *</label>
          <input class="form-control" name="school_year" value="<?= h($values['school_year']) ?>" placeholder="e.g., 2025-2026" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Status *</label>
          <select class="form-select" name="status" required>
            <option value="Active" <?= $values['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
            <option value="Inactive" <?= $values['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
            <option value="Archived" <?= $values['status'] === 'Archived' ? 'selected' : '' ?>>Archived</option>
          </select>
        </div>
      </div>

      <div class="d-grid mt-3">
        <button class="btn btn-primary" type="submit">Save Schedule</button>
      </div>
    </form>
  </div>
</div>

<?php
if ($masterGrades):
    $sectionsJson = json_encode($sectionsByGrade, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $currentSection = (string)$values['section'];
    $currentGrade = (string)$values['grade_level'];
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

    // initial
    setOptions(gradeEl.value, <?= json_encode($currentSection, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
  })();
</script>
<?php
endif;

require __DIR__ . '/partials/layout_bottom.php';

