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

$attendanceCounts = [];
if ($students) {
    $studentIds = array_map(function ($s) { return (int)$s['student_id']; }, $students);
    $inPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
    $attSql = "SELECT student_id,
                      SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) AS total_absent,
                      SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) AS total_late
               FROM attendance
               WHERE student_id IN ($inPlaceholders)
               GROUP BY student_id";
    $attStmt = $pdo->prepare($attSql);
    $attStmt->execute($studentIds);
    foreach ($attStmt->fetchAll() as $row) {
        $attendanceCounts[(int)$row['student_id']] = $row;
    }
}

// Fetch grade/section lists for filter datalists
$filterGrades = [];
$filterSections = [];
try {
    $filterGrades = $pdo->query('SELECT DISTINCT grade_level FROM grade_sections WHERE status = "Active" ORDER BY grade_level ASC')->fetchAll(PDO::FETCH_COLUMN);
    $filterSections = $pdo->query('SELECT DISTINCT section FROM grade_sections WHERE status = "Active" ORDER BY section ASC')->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {}

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
  <div class="bnhs-page-actions d-flex gap-2 flex-wrap align-items-center">
    <input type="month" id="exportMonth" class="form-control form-control-sm" style="width:160px" value="<?= h(date('Y-m')) ?>">
    <a class="btn btn-success btn-sm" id="btnExportExcel" href="#">Export Excel</a>
    <a class="btn btn-danger btn-sm" id="btnExportPdf" href="#">Export PDF</a>
    <a class="btn btn-primary btn-sm" href="<?= h(url('student_form.php')) ?>">Add Student</a>
  </div>
  <script>
  (function(){
    var base = <?= json_encode(url('export_sf2.php')) ?>;
    var fixedParams = <?= json_encode(array_filter(['grade' => $grade, 'section' => $section, 'status' => $status, 'q' => $q])) ?>;
    function buildUrl(fmt){
      var m = document.getElementById('exportMonth').value;
      var p = Object.assign({}, fixedParams, {format: fmt, month: m});
      var qs = new URLSearchParams(p).toString();
      return base + '?' + qs;
    }
    document.getElementById('btnExportExcel').addEventListener('click', function(e){ e.preventDefault(); window.location.href = buildUrl('excel'); });
    document.getElementById('btnExportPdf').addEventListener('click', function(e){ e.preventDefault(); window.location.href = buildUrl('pdf'); });
  })();
  </script>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-6 col-md-4">
        <div class="bnhs-metric metric-green">
          <div class="bnhs-metric-icon icon-green"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></div>
          <div class="bnhs-metric-label">Active</div>
          <div class="bnhs-metric-value" id="metricActive"><?= (int)$activeCount ?></div>
        </div>
      </div>
      <div class="col-6 col-md-4">
        <div class="bnhs-metric metric-orange">
          <div class="bnhs-metric-icon icon-orange"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg></div>
          <div class="bnhs-metric-label">Inactive</div>
          <div class="bnhs-metric-value" id="metricInactive"><?= (int)$inactiveCount ?></div>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="bnhs-metric metric-blue">
          <div class="bnhs-metric-icon icon-blue"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg></div>
          <div class="bnhs-metric-label">Total</div>
          <div class="bnhs-metric-value" id="metricStudentTotal"><?= (int)$total ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm mb-3 bnhs-filter-card">
  <div class="card-body">
    <form class="row g-2" method="get" action="<?= h(url('students.php')) ?>" id="studentsFilterForm">
      <div class="col-md-4">
        <input class="form-control" name="q" placeholder="Search LRN / Name" value="<?= h($q) ?>">
      </div>
      <div class="col-md-2">
        <input class="form-control" name="grade" placeholder="Grade" value="<?= h($grade) ?>" list="dlGrades" autocomplete="off">
        <datalist id="dlGrades">
          <?php foreach ($filterGrades as $fg): ?>
            <option value="<?= h((string)$fg) ?>">
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="col-md-2">
        <input class="form-control" name="section" placeholder="Section" value="<?= h($section) ?>" list="dlSections" autocomplete="off">
        <datalist id="dlSections">
          <?php foreach ($filterSections as $fs): ?>
            <option value="<?= h((string)$fs) ?>">
          <?php endforeach; ?>
        </datalist>
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
        <a class="btn btn-outline-secondary" href="<?= h(url('students.php')) ?>">Reset</a>
      </div>
    </form>
    <script>
    (function(){
      var form = document.getElementById('studentsFilterForm');
      if(!form) return;
      var timer = null;
      function submit(){ form.submit(); }
      form.querySelectorAll('select').forEach(function(el){
        el.addEventListener('change', submit);
      });
      form.querySelectorAll('input[list], input[type="text"], input[name="q"], input:not([type])').forEach(function(el){
        el.addEventListener('input', function(){
          clearTimeout(timer);
          timer = setTimeout(submit, 600);
        });
        el.addEventListener('change', submit);
      });
    })();
    </script>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped mb-0" id="studentsTable">
        <thead>
          <tr>
            <th>LRN</th>
            <th>Name</th>
            <th>Grade-Section</th>
            <th>Sex</th>
            <th>Status</th>
            <th class="text-center">Total Absent</th>
            <th class="text-center">Total Late</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody id="studentsTbody">
          <?php if (!$students): ?>
            <tr>
              <td colspan="8" class="p-0">
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
                <?php
                  $sid = (int)$s['student_id'];
                  $absCount = (int)($attendanceCounts[$sid]['total_absent'] ?? 0);
                  $lateCount = (int)($attendanceCounts[$sid]['total_late'] ?? 0);
                ?>
                <td><span class="bnhs-status-dot <?= strtolower((string)$s['status']) ?>"></span><?= h((string)$s['status']) ?></td>
                <td class="text-center"><?= $absCount ?></td>
                <td class="text-center"><?= $lateCount ?></td>
                <td class="text-end">
                  <div class="bnhs-actions">
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
                  </div>
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
      <span id="studentsPagInfo">Showing <?= (int)$pg['from'] ?>-<?= (int)$pg['to'] ?> of <?= (int)$pg['total'] ?></span>
    </div>
    <?= pagination_html('students.php', $_GET, (int)$pg['page'], (int)$pg['per_page'], (int)$pg['total']) ?>
  </div>
</div>

<script>
(function(){
  var POLL_INTERVAL = 5000;
  var baseUrl = <?= json_encode(url('api_poll.php')) ?>;
  var currentParams = <?= json_encode(array_filter([
      'type' => 'students',
      'q' => $q,
      'grade' => $grade,
      'section' => $section,
      'status' => $status,
      'page' => (string)$page,
  ])) ?>;

  function escH(s){ var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

  function poll(){
    var qs = new URLSearchParams(currentParams).toString();
    fetch(baseUrl + '?' + qs, {headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){ return r.json(); })
      .then(function(d){
        if(!d.ok) return;
        var m = d.metrics;
        var el;
        el = document.getElementById('metricActive'); if(el) el.textContent = m.active;
        el = document.getElementById('metricInactive'); if(el) el.textContent = m.inactive;
        el = document.getElementById('metricStudentTotal'); if(el) el.textContent = m.total;

        var tbody = document.getElementById('studentsTbody');
        if(tbody && d.rows){
          var html = '';
          if(d.rows.length === 0){
            html = '<tr><td colspan="8" class="p-0"><div class="bnhs-empty-state">No students found.</div></td></tr>';
          } else {
            d.rows.forEach(function(r){
              html += '<tr>';
              html += '<td>' + escH(r.lrn) + '</td>';
              html += '<td>' + escH(r.name) + '</td>';
              html += '<td>' + escH(r.grade_section) + '</td>';
              html += '<td>' + escH(r.sex) + '</td>';
              html += '<td>' + escH(r.status) + '</td>';
              html += '<td class="text-center">' + r.total_absent + '</td>';
              html += '<td class="text-center">' + r.total_late + '</td>';
              html += '<td class="text-end">';
              html += '<a class="btn btn-sm btn-outline-secondary" href="' + escH(r.view_url) + '">View</a> ';
              html += '<a class="btn btn-sm btn-outline-primary" href="' + escH(r.edit_url) + '">Edit</a> ';
              if(r.status === 'Active'){
                html += '<form class="d-inline" method="post" action="' + escH(r.archive_url) + '" data-confirm="Archive this student?" data-confirm-title="Archive Student" data-confirm-ok="Archive" data-confirm-cancel="Cancel" data-confirm-icon="warning">';
                html += '<input type="hidden" name="id" value="' + r.student_id + '">';
                html += '<button class="btn btn-sm btn-outline-warning" type="submit">Archive</button>';
                html += '</form> ';
              }
              html += '<form class="d-inline" method="post" action="' + escH(r.delete_url) + '" data-confirm="Delete this student permanently?" data-confirm-title="Delete Student" data-confirm-ok="Delete" data-confirm-cancel="Cancel" data-confirm-icon="danger">';
              html += '<input type="hidden" name="id" value="' + r.student_id + '">';
              html += '<button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>';
              html += '</form>';
              html += '</td></tr>';
            });
          }
          tbody.innerHTML = html;
        }

        var pInfo = document.getElementById('studentsPagInfo');
        if(pInfo && d.pagination){
          pInfo.textContent = 'Showing ' + d.pagination.from + '-' + d.pagination.to + ' of ' + d.pagination.total;
        }
      })
      .catch(function(){});
  }

  setInterval(poll, POLL_INTERVAL);
})();
</script>

<?php
require __DIR__ . '/partials/layout_bottom.php';
