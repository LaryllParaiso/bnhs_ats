<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

$teacherId = (int)$_SESSION['teacher_id'];
$isAdmin = is_admin();
$taughtGrades = teacher_grade_levels_taught($teacherId);

$q = trim((string)($_GET['q'] ?? ''));
$grade = trim((string)($_GET['grade'] ?? ''));
$section = trim((string)($_GET['section'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$params = [];
$where = [];

if (!$isAdmin) {
    if ($taughtGrades) {
        $gradePlaceholders = [];
        foreach ($taughtGrades as $i => $g) {
            $ph = ':tg' . $i;
            $gradePlaceholders[] = $ph;
            $params[$ph] = $g;
        }
        $where[] = 'grade_level IN (' . implode(',', $gradePlaceholders) . ')';
    } else {
        $params[':teacher_id'] = $teacherId;
        $where[] = 'EXISTS (SELECT 1 FROM student_schedules ss JOIN schedules sch ON sch.schedule_id = ss.schedule_id WHERE ss.student_id = students.student_id AND ss.status = "Active" AND sch.teacher_id = :teacher_id AND sch.status != "Archived")';
    }
}

if ($q !== '') {
    $where[] = '(lrn LIKE :q1 OR first_name LIKE :q2 OR last_name LIKE :q3)';
    $qLike = '%' . $q . '%';
    $params[':q1'] = $qLike;
    $params[':q2'] = $qLike;
    $params[':q3'] = $qLike;
}

if ($grade !== '' && ctype_digit($grade)) {
    $where[] = 'grade_level = :grade';
    $params[':grade'] = (int)$grade;
}

if ($section !== '') {
    $where[] = 'section = :section';
    $params[':section'] = $section;
}

if (in_array($status, ['Active', 'Inactive', 'Graduated'], true)) {
    $where[] = 'status = :status';
    $params[':status'] = $status;
}

$sql = 'SELECT * FROM students';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY last_name, first_name, lrn';

$pdo = db();

$countSql = 'SELECT COUNT(*) AS cnt FROM students' . ($where ? (' WHERE ' . implode(' AND ', $where)) : '');
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = (int)(($stmt->fetch()['cnt'] ?? 0));

$activeCount = 0;
$inactiveCount = 0;
try {
    $aggSql =
        'SELECT
            SUM(CASE WHEN status = "Active" THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN status = "Inactive" THEN 1 ELSE 0 END) AS inactive,
            COUNT(*) AS total
         FROM students' . ($where ? (' WHERE ' . implode(' AND ', $where)) : '');

    $stmt = $pdo->prepare($aggSql);
    $stmt->execute($params);
    $agg = $stmt->fetch() ?: [];
    $activeCount = (int)($agg['active'] ?? 0);
    $inactiveCount = (int)($agg['inactive'] ?? 0);
} catch (Throwable $e) {
    $activeCount = 0;
    $inactiveCount = 0;
}

$pg = paginate($total, $page, $perPage);
$page = (int)$pg['page'];
$limit = (int)$pg['per_page'];
$offset = (int)$pg['offset'];

$pagedSql = $sql . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
$stmt = $pdo->prepare($pagedSql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$title = 'Students';
require __DIR__ . '/partials/layout_top.php';
?>

<?php if (isset($_GET['saved'])): ?>
  <div class="alert alert-success">Student saved.</div>
<?php endif; ?>

<?php if (isset($_GET['archived'])): ?>
  <div class="alert alert-info">Student archived.</div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
  <div class="alert alert-success">Student deleted.</div>
<?php endif; ?>

<div class="bnhs-page-header">
  <h1 class="bnhs-page-title">Students</h1>
  <div class="bnhs-page-actions">
    <a class="btn btn-primary btn-sm" href="<?= h(url('student_form.php')) ?>">Add Student</a>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-6 col-md-4">
        <div class="bnhs-metric">
          <div class="bnhs-metric-label">Active</div>
          <div class="bnhs-metric-value"><?= (int)$activeCount ?></div>
        </div>
      </div>
      <div class="col-6 col-md-4">
        <div class="bnhs-metric">
          <div class="bnhs-metric-label">Inactive</div>
          <div class="bnhs-metric-value"><?= (int)$inactiveCount ?></div>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="bnhs-metric">
          <div class="bnhs-metric-label">Total</div>
          <div class="bnhs-metric-value"><?= (int)$total ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm mb-3 bnhs-filter-card">
  <div class="card-body">
    <form class="row g-2" method="get" action="<?= h(url('students.php')) ?>">
      <div class="col-md-4">
        <input class="form-control" name="q" placeholder="Search LRN / Name" value="<?= h($q) ?>">
      </div>
      <div class="col-md-2">
        <input class="form-control" name="grade" placeholder="Grade" value="<?= h($grade) ?>">
      </div>
      <div class="col-md-2">
        <input class="form-control" name="section" placeholder="Section" value="<?= h($section) ?>">
      </div>
      <div class="col-md-2">
        <select class="form-select" name="status">
          <option value="" <?= $status === '' ? 'selected' : '' ?>>All Status</option>
          <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
          <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
          <option value="Graduated" <?= $status === 'Graduated' ? 'selected' : '' ?>>Graduated</option>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-outline-primary" type="submit">Filter</button>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped mb-0">
        <thead>
          <tr>
            <th>LRN</th>
            <th>Name</th>
            <th>Grade-Section</th>
            <th>Sex</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$students): ?>
            <tr>
              <td colspan="6" class="p-0">
                <div class="bnhs-empty-state">
                  <div class="bnhs-empty-icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M22 21v-2a4 4 0 0 0-3-3.87" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </div>
                  No students found.
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($students as $s): ?>
              <?php
                $suffix = trim((string)($s['suffix'] ?? ''));
                $name = trim((string)$s['last_name'] . ', ' . (string)$s['first_name']);
                if ($suffix !== '') {
                    $name .= ' ' . $suffix;
                }
              ?>
              <tr>
                <td><?= h((string)$s['lrn']) ?></td>
                <td><?= h($name) ?></td>
                <td><?= h((string)$s['grade_level'] . '-' . (string)$s['section']) ?></td>
                <td><?= h((string)$s['sex']) ?></td>
                <td><?= h((string)$s['status']) ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-secondary" href="<?= h(url('student_view.php?id=' . (int)$s['student_id'])) ?>">View</a>
                  <a class="btn btn-sm btn-outline-primary" href="<?= h(url('student_form.php?id=' . (int)$s['student_id'])) ?>">Edit</a>
                  <?php if ($s['status'] === 'Active'): ?>
                    <form class="d-inline" method="post" action="<?= h(url('student_archive.php')) ?>" data-confirm="Archive this student?" data-confirm-title="Archive Student" data-confirm-ok="Archive" data-confirm-cancel="Cancel" data-confirm-icon="warning">
                      <input type="hidden" name="id" value="<?= h((string)$s['student_id']) ?>">
                      <button class="btn btn-sm btn-outline-warning" type="submit">Archive</button>
                    </form>
                  <?php endif; ?>
                  <form class="d-inline" method="post" action="<?= h(url('student_delete.php')) ?>" data-confirm="Delete this student permanently? This will also remove their enrollments and attendance records." data-confirm-title="Delete Student" data-confirm-ok="Delete" data-confirm-cancel="Cancel" data-confirm-icon="danger">
                    <input type="hidden" name="id" value="<?= h((string)$s['student_id']) ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="d-flex justify-content-between align-items-center p-2 border-top">
    <div class="text-muted small">
      Showing <?= (int)$pg['from'] ?>-<?= (int)$pg['to'] ?> of <?= (int)$pg['total'] ?>
    </div>
    <?= pagination_html('students.php', $_GET, (int)$pg['page'], (int)$pg['per_page'], (int)$pg['total']) ?>
  </div>
</div>

<?php
require __DIR__ . '/partials/layout_bottom.php';
