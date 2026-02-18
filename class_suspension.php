<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

if (!is_super_admin()) {
    redirect('attendance_records.php');
}

$title = 'Class Suspension';
$pdo = db();

$errors = [];
$success = null;

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'delete') {
    $suspId = (int)($_POST['suspension_id'] ?? 0);
    if ($suspId <= 0) {
        $errors[] = 'Invalid request.';
    } else {
        try {
            // Remove any attendance records marked Suspended for this suspension
            $stmt = $pdo->prepare('SELECT * FROM class_suspensions WHERE suspension_id = :id LIMIT 1');
            $stmt->execute([':id' => $suspId]);
            $susp = $stmt->fetch();

            if (!$susp) {
                $errors[] = 'Suspension not found.';
            } else {
                $pdo->beginTransaction();

                // Delete suspended attendance records that were auto-created
                $delWhere = ['a.status = "Suspended"', 'a.date BETWEEN :start AND :end'];
                $delParams = [':start' => (string)$susp['start_date'], ':end' => (string)$susp['end_date']];

                $scope = (string)$susp['scope'];
                if ($scope === 'grade' && $susp['grade_level'] !== null) {
                    $delWhere[] = 'st.grade_level = :grade';
                    $delParams[':grade'] = (int)$susp['grade_level'];
                } elseif ($scope === 'section' && $susp['grade_level'] !== null && $susp['section'] !== null) {
                    $delWhere[] = 'st.grade_level = :grade';
                    $delWhere[] = 'st.section = :section';
                    $delParams[':grade'] = (int)$susp['grade_level'];
                    $delParams[':section'] = (string)$susp['section'];
                } elseif ($scope === 'subject' && $susp['schedule_id'] !== null) {
                    $delWhere[] = 'a.schedule_id = :schedule_id';
                    $delParams[':schedule_id'] = (int)$susp['schedule_id'];
                }

                $delSql = 'DELETE a FROM attendance a JOIN students st ON st.student_id = a.student_id WHERE ' . implode(' AND ', $delWhere);
                $stmt = $pdo->prepare($delSql);
                $stmt->execute($delParams);

                $stmt = $pdo->prepare('DELETE FROM class_suspensions WHERE suspension_id = :id');
                $stmt->execute([':id' => $suspId]);

                $pdo->commit();
                $success = 'Suspension deleted and related attendance records removed.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Failed to delete suspension.';
        }
    }
}

// Handle create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'create') {
    $startDate = trim((string)($_POST['start_date'] ?? ''));
    $endDate = trim((string)($_POST['end_date'] ?? ''));
    $reason = trim((string)($_POST['reason'] ?? ''));
    $scope = (string)($_POST['scope'] ?? 'school');

    // Multi-select arrays
    $gradeLevels = (array)($_POST['grade_levels'] ?? []);
    $sections = (array)($_POST['sections'] ?? []);
    $scheduleIds = (array)($_POST['schedule_ids'] ?? []);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        $errors[] = 'Start date is required.';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        $errors[] = 'End date is required.';
    }
    if (!$errors && $endDate < $startDate) {
        $errors[] = 'End date must be on or after start date.';
    }
    if (!in_array($scope, ['school', 'grade', 'section', 'subject'], true)) {
        $errors[] = 'Invalid scope.';
    }

    // Build list of suspension combos to insert
    $combos = [];

    if ($scope === 'school') {
        $combos[] = ['grade_level' => null, 'section' => null, 'schedule_id' => null];
    } elseif ($scope === 'grade') {
        $validGrades = array_filter($gradeLevels, fn($g) => ctype_digit((string)$g) && (int)$g >= 7 && (int)$g <= 12);
        if (!$validGrades) {
            $errors[] = 'At least one grade level is required.';
        }
        foreach ($validGrades as $g) {
            $combos[] = ['grade_level' => (int)$g, 'section' => null, 'schedule_id' => null];
        }
    } elseif ($scope === 'section') {
        $validGrades = array_filter($gradeLevels, fn($g) => ctype_digit((string)$g) && (int)$g >= 7 && (int)$g <= 12);
        $validSections = array_filter($sections, fn($s) => trim((string)$s) !== '');
        if (!$validGrades) {
            $errors[] = 'At least one grade level is required.';
        }
        if (!$validSections) {
            $errors[] = 'At least one section is required.';
        }
        foreach ($validGrades as $g) {
            foreach ($validSections as $s) {
                $combos[] = ['grade_level' => (int)$g, 'section' => trim((string)$s), 'schedule_id' => null];
            }
        }
    } elseif ($scope === 'subject') {
        $validSchedules = array_filter($scheduleIds, fn($id) => (int)$id > 0);
        if (!$validSchedules) {
            $errors[] = 'At least one subject/schedule is required.';
        }
        foreach ($validSchedules as $sid) {
            $combos[] = ['grade_level' => null, 'section' => null, 'schedule_id' => (int)$sid];
        }
    }

    if (!$errors && $combos) {
        try {
            $pdo->beginTransaction();

            $remarkText = 'Class Suspended' . ($reason !== '' ? ': ' . $reason : '');
            $createdCount = 0;

            foreach ($combos as $combo) {
                $stmt = $pdo->prepare(
                    'INSERT INTO class_suspensions (start_date, end_date, reason, scope, grade_level, section, schedule_id, created_by)
                     VALUES (:start_date, :end_date, :reason, :scope, :grade_level, :section, :schedule_id, :created_by)'
                );
                $stmt->execute([
                    ':start_date' => $startDate,
                    ':end_date' => $endDate,
                    ':reason' => $reason !== '' ? $reason : null,
                    ':scope' => $scope,
                    ':grade_level' => $combo['grade_level'],
                    ':section' => $combo['section'],
                    ':schedule_id' => $combo['schedule_id'],
                    ':created_by' => (int)$_SESSION['teacher_id'],
                ]);

                // Auto-mark affected attendance records as Suspended
                $updWhere = ['a.date BETWEEN :start AND :end'];
                $updParams = [':start' => $startDate, ':end' => $endDate];

                if ($scope === 'grade' && $combo['grade_level'] !== null) {
                    $updWhere[] = 'st.grade_level = :grade';
                    $updParams[':grade'] = $combo['grade_level'];
                } elseif ($scope === 'section' && $combo['grade_level'] !== null && $combo['section'] !== null) {
                    $updWhere[] = 'st.grade_level = :grade';
                    $updWhere[] = 'st.section = :section';
                    $updParams[':grade'] = $combo['grade_level'];
                    $updParams[':section'] = $combo['section'];
                } elseif ($scope === 'subject' && $combo['schedule_id'] !== null) {
                    $updWhere[] = 'a.schedule_id = :schedule_id';
                    $updParams[':schedule_id'] = $combo['schedule_id'];
                }

                $updSql = 'UPDATE attendance a JOIN students st ON st.student_id = a.student_id SET a.status = "Suspended", a.remarks = :remark WHERE ' . implode(' AND ', $updWhere);
                $updParams[':remark'] = $remarkText;
                $stmt = $pdo->prepare($updSql);
                $stmt->execute($updParams);

                $createdCount++;
            }

            $pdo->commit();
            $success = $createdCount . ' suspension(s) created. Existing attendance records in the affected range have been marked as Suspended.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Failed to create suspension.';
        }
    }
}

// Fetch all suspensions
$stmt = $pdo->prepare(
    'SELECT cs.*, t.first_name, t.last_name
     FROM class_suspensions cs
     JOIN teachers t ON t.teacher_id = cs.created_by
     ORDER BY cs.start_date DESC, cs.created_at DESC'
);
$stmt->execute();
$suspensions = $stmt->fetchAll();

// Fetch schedules for subject scope dropdown
$schedulesList = $pdo->query(
    'SELECT schedule_id, subject_name, grade_level, section, day_of_week, start_time, end_time
     FROM schedules WHERE status = "Active"
     ORDER BY subject_name, grade_level, section'
)->fetchAll();

// Fetch grade sections for dropdowns
$gradeSectionsActive = [];
$sectionsByGrade = [];
try {
    $stmt = $pdo->prepare('SELECT grade_level, section FROM grade_sections WHERE status = "Active" ORDER BY grade_level ASC, section ASC');
    $stmt->execute();
    $gradeSectionsActive = $stmt->fetchAll();
} catch (Throwable $e) {
}
foreach ($gradeSectionsActive as $r) {
    $g = (int)($r['grade_level'] ?? 0);
    $s = (string)($r['section'] ?? '');
    if ($g >= 7 && $g <= 12 && $s !== '') {
        $sectionsByGrade[$g] = $sectionsByGrade[$g] ?? [];
        $sectionsByGrade[$g][] = $s;
    }
}

require __DIR__ . '/partials/layout_top.php';
?>

<div class="row">
  <div class="col-12">
    <div class="bnhs-page-header">
      <h1 class="bnhs-page-title">Class Suspension</h1>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errors as $e): ?>
            <li><?= h($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold">Declare Class Suspension</div>
      <div class="card-body">
        <form method="post" action="<?= h(url('class_suspension.php')) ?>">
          <input type="hidden" name="action" value="create">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">Start Date *</label>
              <input type="date" class="form-control" name="start_date" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">End Date *</label>
              <input type="date" class="form-control" name="end_date" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Scope *</label>
              <select class="form-select" name="scope" id="suspScope" required>
                <option value="school">Entire School</option>
                <option value="grade">Specific Grade(s)</option>
                <option value="section">Specific Section(s)</option>
                <option value="subject">Specific Subject(s)</option>
              </select>
            </div>

            <div class="col-12" id="suspGradeWrap" style="display:none">
              <label class="form-label">Grade Level(s) <small class="text-muted">(select one or more)</small></label>
              <div class="border rounded p-3 bg-white">
                <div class="row g-2">
                  <?php for ($g = 7; $g <= 12; $g++): ?>
                    <div class="col-4 col-md-2">
                      <div class="form-check">
                        <input class="form-check-input susp-grade-cb" type="checkbox" name="grade_levels[]" value="<?= $g ?>" id="suspG<?= $g ?>">
                        <label class="form-check-label" for="suspG<?= $g ?>">Grade <?= $g ?></label>
                      </div>
                    </div>
                  <?php endfor; ?>
                </div>
              </div>
            </div>

            <div class="col-12" id="suspSectionWrap" style="display:none">
              <label class="form-label">Section(s) <small class="text-muted">(select one or more — based on selected grades)</small></label>
              <div class="border rounded p-3 bg-white" id="suspSectionList">
                <span class="text-muted small">Select at least one grade level first.</span>
              </div>
            </div>

            <div class="col-12" id="suspScheduleWrap" style="display:none">
              <label class="form-label">Subject / Schedule(s) <small class="text-muted">(select one or more)</small></label>
              <div class="border rounded p-3 bg-white" style="max-height:200px;overflow-y:auto">
                <div class="row g-2">
                  <?php foreach ($schedulesList as $sch): ?>
                    <div class="col-12 col-md-6">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="schedule_ids[]" value="<?= (int)$sch['schedule_id'] ?>" id="suspSch<?= (int)$sch['schedule_id'] ?>">
                        <label class="form-check-label" for="suspSch<?= (int)$sch['schedule_id'] ?>"><?= h((string)$sch['subject_name'] . ' (G' . (string)$sch['grade_level'] . '-' . (string)$sch['section'] . ' ' . (string)$sch['day_of_week'] . ' ' . (string)$sch['start_time'] . '-' . (string)$sch['end_time'] . ')') ?></label>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label">Reason (optional)</label>
              <textarea class="form-control" name="reason" rows="2" placeholder="e.g., Typhoon signal #3"></textarea>
            </div>
          </div>
          <div class="d-grid mt-3">
            <button class="btn btn-danger" type="submit">Declare Suspension</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header fw-semibold">Suspension History</div>
      <div class="card-body p-0">
        <?php if (!$suspensions): ?>
          <div class="bnhs-empty-state">No suspensions declared yet.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Date Range</th>
                  <th>Scope</th>
                  <th>Details</th>
                  <th>Reason</th>
                  <th>Created By</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($suspensions as $susp): ?>
                  <?php
                    $scopeLabel = match((string)$susp['scope']) {
                        'school' => 'Entire School',
                        'grade' => 'Grade ' . (int)$susp['grade_level'],
                        'section' => 'Grade ' . (int)$susp['grade_level'] . '-' . (string)$susp['section'],
                        'subject' => 'Schedule #' . (int)$susp['schedule_id'],
                        default => ucfirst((string)$susp['scope']),
                    };
                    $createdBy = trim((string)$susp['first_name'] . ' ' . (string)$susp['last_name']);
                    $isFuture = (string)$susp['start_date'] >= date('Y-m-d');
                  ?>
                  <tr>
                    <td><?= h((string)$susp['start_date']) ?> — <?= h((string)$susp['end_date']) ?></td>
                    <td><span class="badge text-bg-secondary"><?= h($scopeLabel) ?></span></td>
                    <td>
                      <?php if ((string)$susp['scope'] === 'grade'): ?>
                        Grade <?= (int)$susp['grade_level'] ?>
                      <?php elseif ((string)$susp['scope'] === 'section'): ?>
                        Grade <?= (int)$susp['grade_level'] ?>-<?= h((string)$susp['section']) ?>
                      <?php elseif ((string)$susp['scope'] === 'subject'): ?>
                        Schedule #<?= (int)$susp['schedule_id'] ?>
                      <?php else: ?>
                        All classes
                      <?php endif; ?>
                    </td>
                    <td><small><?= h((string)($susp['reason'] ?? '')) ?></small></td>
                    <td><small><?= h($createdBy) ?></small></td>
                    <td class="text-end">
                      <form method="post" action="<?= h(url('class_suspension.php')) ?>" class="d-inline" data-confirm="Delete this suspension? Suspended attendance records will be removed." data-confirm-title="Delete Suspension" data-confirm-ok="Delete" data-confirm-cancel="Cancel" data-confirm-icon="danger">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="suspension_id" value="<?= (int)$susp['suspension_id'] ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  var scopeEl = document.getElementById('suspScope');
  var gradeWrap = document.getElementById('suspGradeWrap');
  var sectionWrap = document.getElementById('suspSectionWrap');
  var scheduleWrap = document.getElementById('suspScheduleWrap');
  var sectionList = document.getElementById('suspSectionList');
  var gradeCbs = document.querySelectorAll('.susp-grade-cb');

  var sectionsByGrade = <?= json_encode($sectionsByGrade, JSON_UNESCAPED_UNICODE) ?>;

  function updateVisibility(){
    var v = scopeEl.value;
    gradeWrap.style.display = (v === 'grade' || v === 'section') ? '' : 'none';
    sectionWrap.style.display = (v === 'section') ? '' : 'none';
    scheduleWrap.style.display = (v === 'subject') ? '' : 'none';
  }

  function updateSectionCheckboxes(){
    var selectedGrades = [];
    gradeCbs.forEach(function(cb){ if(cb.checked) selectedGrades.push(cb.value); });

    if(selectedGrades.length === 0){
      sectionList.innerHTML = '<span class="text-muted small">Select at least one grade level first.</span>';
      return;
    }

    var html = '<div class="row g-2">';
    var hasSections = false;
    selectedGrades.forEach(function(g){
      var list = sectionsByGrade[g] || [];
      list.forEach(function(s){
        hasSections = true;
        var id = 'suspSec_' + g + '_' + s.replace(/\s/g,'_');
        html += '<div class="col-6 col-md-3"><div class="form-check">';
        html += '<input class="form-check-input" type="checkbox" name="sections[]" value="' + s + '" id="' + id + '">';
        html += '<label class="form-check-label" for="' + id + '">G' + g + '-' + s + '</label>';
        html += '</div></div>';
      });
    });
    html += '</div>';

    if(!hasSections){
      sectionList.innerHTML = '<span class="text-muted small">No sections found for the selected grade(s).</span>';
    } else {
      sectionList.innerHTML = html;
    }
  }

  scopeEl.addEventListener('change', function(){
    updateVisibility();
    if(scopeEl.value === 'section') updateSectionCheckboxes();
  });

  gradeCbs.forEach(function(cb){
    cb.addEventListener('change', function(){
      if(scopeEl.value === 'section') updateSectionCheckboxes();
    });
  });

  updateVisibility();
})();
</script>

<?php
require __DIR__ . '/partials/layout_bottom.php';
