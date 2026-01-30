<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

$teacherId = (int)$_SESSION['teacher_id'];
$isAdmin = is_admin();
$taughtGrades = teacher_grade_levels_taught($teacherId);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect('students.php');
}

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$scheduleId = (int)($_GET['schedule_id'] ?? 0);
$status = trim((string)($_GET['status'] ?? ''));
$export = trim((string)($_GET['export'] ?? ''));

$today = new DateTimeImmutable('today');
$defaultFrom = $today->sub(new DateInterval('P29D'))->format('Y-m-d');
$defaultTo = $today->format('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = $defaultFrom;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = $defaultTo;
}

$pdo = db();

if ($isAdmin) {
    $stmt = $pdo->prepare('SELECT * FROM students WHERE student_id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
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
} else {
    $stmt = $pdo->prepare(
        'SELECT *
         FROM students
         WHERE student_id = :id
          AND (
            EXISTS (
              SELECT 1
              FROM attendance a
              WHERE a.student_id = students.student_id
                AND a.teacher_id = :teacher_id
            )
            OR EXISTS (
              SELECT 1
              FROM student_schedules ss
              JOIN schedules sch ON sch.schedule_id = ss.schedule_id
              WHERE ss.student_id = students.student_id
                AND sch.teacher_id = :teacher_id
            )
          )'
    );
    $stmt->execute([':id' => $id, ':teacher_id' => $teacherId]);
}
$student = $stmt->fetch();

if (!$student) {
    redirect('students.php');
}

$stmt = $pdo->prepare(
    $isAdmin
        ? 'SELECT schedule_id, subject_name, grade_level, section
           FROM schedules
           WHERE status != "Archived"
           ORDER BY subject_name, grade_level, section'
        : 'SELECT schedule_id, subject_name, grade_level, section
           FROM schedules
           WHERE teacher_id = :teacher_id AND status != "Archived"
           ORDER BY subject_name, grade_level, section'
);
$stmt->execute($isAdmin ? [] : [':teacher_id' => $teacherId]);
$schedules = $stmt->fetchAll();

$params = [
    ':student_id' => $id,
    ':from' => $from,
    ':to' => $to,
];

$where = [
    'a.student_id = :student_id',
    'a.date BETWEEN :from AND :to',
];

if (!$isAdmin) {
    $where[] = 'a.teacher_id = :teacher_id';
    $params[':teacher_id'] = $teacherId;
}

if ($scheduleId > 0) {
    $where[] = 'a.schedule_id = :schedule_id';
    $params[':schedule_id'] = $scheduleId;
}

if (in_array($status, ['Present', 'Late', 'Absent'], true)) {
    $where[] = 'a.status = :status';
    $params[':status'] = $status;
}

$sqlWhere = ' WHERE ' . implode(' AND ', $where);

$sql =
    'SELECT a.attendance_id, a.date, a.time_scanned, a.status, a.remarks,
            sch.subject_name, sch.grade_level AS schedule_grade, sch.section AS schedule_section
     FROM attendance a
     JOIN schedules sch ON sch.schedule_id = a.schedule_id' .
    $sqlWhere .
    ' ORDER BY a.date DESC, sch.subject_name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$aggSql =
    'SELECT
        SUM(CASE WHEN a.status = "Present" THEN 1 ELSE 0 END) AS present,
        SUM(CASE WHEN a.status = "Late" THEN 1 ELSE 0 END) AS late,
        SUM(CASE WHEN a.status = "Absent" THEN 1 ELSE 0 END) AS absent,
        COUNT(*) AS total
     FROM attendance a' .
    $sqlWhere;

$stmt = $pdo->prepare($aggSql);
$stmt->execute($params);
$agg = $stmt->fetch();

$present = (int)($agg['present'] ?? 0);
$late = (int)($agg['late'] ?? 0);
$absent = (int)($agg['absent'] ?? 0);
$total = (int)($agg['total'] ?? 0);

$suffix = trim((string)($student['suffix'] ?? ''));
$fullName = trim((string)$student['first_name'] . ' ' . (string)$student['last_name']);
if ($suffix !== '') {
    $fullName .= ', ' . $suffix;
}

if ($export === 'pdf' || $export === 'xlsx') {
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Export dependencies are not installed. Run composer install first.';
        exit;
    }
    require_once $autoload;

    if ($export === 'xlsx') {
        if (!extension_loaded('zip')) {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Excel export requires the PHP zip extension.';
            exit;
        }
        if (!extension_loaded('gd')) {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Excel export requires the PHP gd extension.';
            exit;
        }
    }
}

if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="student_history_' . (string)$student['lrn'] . '_' . $from . '_to_' . $to . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Subject', 'Grade-Section', 'Status', 'Time Scanned', 'Remarks']);

    foreach ($rows as $r) {
        $label = (string)$r['subject_name'] . ' (G' . (string)$r['schedule_grade'] . '-' . (string)$r['schedule_section'] . ')';
        fputcsv($out, [
            (string)$r['date'],
            (string)$r['subject_name'],
            (string)$r['schedule_grade'] . '-' . (string)$r['schedule_section'],
            (string)$r['status'],
            (string)($r['time_scanned'] ?? ''),
            (string)($r['remarks'] ?? ''),
        ]);
    }

    fclose($out);
    exit;
}

if ($export === 'pdf') {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('BNH ATS');
    $pdf->SetAuthor('BNH ATS');
    $pdf->SetTitle('Student Attendance History');
    $pdf->SetMargins(12, 12, 12);
    $pdf->AddPage();

    $h = function (string $v): string {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    $html = '';
    $html .= '<h2>Student Attendance History</h2>';
    $html .= '<div><strong>Name:</strong> ' . $h($fullName) . '</div>';
    $html .= '<div><strong>LRN:</strong> ' . $h((string)$student['lrn']) . '</div>';
    $html .= '<div><strong>Date range:</strong> ' . $h($from) . ' to ' . $h($to) . '</div>';
    $html .= '<div><strong>Totals:</strong> Present ' . $h((string)$present) . ' | Late ' . $h((string)$late) . ' | Absent ' . $h((string)$absent) . ' | Total ' . $h((string)$total) . '</div>';
    $html .= '<br>';
    $html .= '<table border="1" cellpadding="4">
      <thead>
        <tr style="font-weight:bold;background-color:#f2f2f2;">
          <th width="16%">Date</th>
          <th width="34%">Subject</th>
          <th width="14%">G-S</th>
          <th width="12%">Status</th>
          <th width="14%">Time</th>
          <th width="10%">Remarks</th>
        </tr>
      </thead>
      <tbody>';

    foreach ($rows as $r) {
        $gs = (string)$r['schedule_grade'] . '-' . (string)$r['schedule_section'];
        $html .= '<tr>';
        $html .= '<td>' . $h((string)$r['date']) . '</td>';
        $html .= '<td>' . $h((string)$r['subject_name']) . '</td>';
        $html .= '<td>' . $h($gs) . '</td>';
        $html .= '<td>' . $h((string)$r['status']) . '</td>';
        $html .= '<td>' . $h((string)($r['time_scanned'] ?? '')) . '</td>';
        $html .= '<td>' . $h((string)($r['remarks'] ?? '')) . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    $pdf->writeHTML($html, true, false, true, false, '');

    $file = 'student_history_' . (string)$student['lrn'] . '_' . $from . '_to_' . $to . '.pdf';
    $pdf->Output($file, 'D');
    exit;
}

if ($export === 'xlsx') {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Student History');

    $sheet->fromArray([
        ['Student Name', $fullName],
        ['LRN', (string)$student['lrn']],
        ['Date From', $from],
        ['Date To', $to],
        [],
        ['Date', 'Subject', 'Grade-Section', 'Status', 'Time Scanned', 'Remarks'],
    ], null, 'A1');

    $rowNum = 7;
    foreach ($rows as $r) {
        $sheet->fromArray([
            [
                (string)$r['date'],
                (string)$r['subject_name'],
                (string)$r['schedule_grade'] . '-' . (string)$r['schedule_section'],
                (string)$r['status'],
                (string)($r['time_scanned'] ?? ''),
                (string)($r['remarks'] ?? ''),
            ],
        ], null, 'A' . $rowNum);
        $rowNum++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="student_history_' . (string)$student['lrn'] . '_' . $from . '_to_' . $to . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

$title = 'Student Attendance History';
require __DIR__ . '/partials/layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Student Attendance History</h1>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="<?= h(url('student_view.php?id=' . (int)$student['student_id'])) ?>">Back to Profile</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= h(url('student_history.php?' . http_build_query(array_merge($_GET, ['export' => 'pdf'])))) ?>">Export PDF</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= h(url('student_history.php?' . http_build_query(array_merge($_GET, ['export' => 'xlsx'])))) ?>">Export Excel</a>
    <a class="btn btn-outline-primary btn-sm" href="<?= h(url('student_history.php?' . http_build_query(array_merge($_GET, ['export' => 'csv'])))) ?>">Export CSV</a>
    <button class="btn btn-primary btn-sm" type="button" onclick="window.print();">Print</button>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <div class="text-muted">Name</div>
        <div class="fw-semibold"><?= h($fullName) ?></div>
      </div>
      <div class="col-md-3">
        <div class="text-muted">LRN</div>
        <div class="fw-semibold"><?= h((string)$student['lrn']) ?></div>
      </div>
      <div class="col-md-3">
        <div class="text-muted">Grade-Section</div>
        <div class="fw-semibold"><?= h((string)$student['grade_level'] . '-' . (string)$student['section']) ?></div>
      </div>
    </div>
  </div>
</div>

<form class="row g-2 mb-3" method="get" action="<?= h(url('student_history.php')) ?>">
  <input type="hidden" name="id" value="<?= h((string)$id) ?>">
  <div class="col-md-2">
    <input class="form-control" type="date" name="from" value="<?= h($from) ?>">
  </div>
  <div class="col-md-2">
    <input class="form-control" type="date" name="to" value="<?= h($to) ?>">
  </div>
  <div class="col-md-4">
    <select class="form-select" name="schedule_id">
      <option value="0" <?= $scheduleId === 0 ? 'selected' : '' ?>>All Subjects</option>
      <?php foreach ($schedules as $s): ?>
        <?php $label = (string)$s['subject_name'] . ' (G' . (string)$s['grade_level'] . '-' . (string)$s['section'] . ')'; ?>
        <option value="<?= h((string)$s['schedule_id']) ?>" <?= $scheduleId === (int)$s['schedule_id'] ? 'selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <select class="form-select" name="status">
      <option value="" <?= $status === '' ? 'selected' : '' ?>>All Status</option>
      <option value="Present" <?= $status === 'Present' ? 'selected' : '' ?>>Present</option>
      <option value="Late" <?= $status === 'Late' ? 'selected' : '' ?>>Late</option>
      <option value="Absent" <?= $status === 'Absent' ? 'selected' : '' ?>>Absent</option>
    </select>
  </div>
  <div class="col-md-2 d-grid">
    <button class="btn btn-outline-primary" type="submit">Filter</button>
  </div>
</form>

<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="border rounded p-3 bg-white">
      <div class="text-muted">Present</div>
      <div class="h5 mb-0"><?= h((string)$present) ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="border rounded p-3 bg-white">
      <div class="text-muted">Late</div>
      <div class="h5 mb-0"><?= h((string)$late) ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="border rounded p-3 bg-white">
      <div class="text-muted">Absent</div>
      <div class="h5 mb-0"><?= h((string)$absent) ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="border rounded p-3 bg-white">
      <div class="text-muted">Total</div>
      <div class="h5 mb-0"><?= h((string)$total) ?></div>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped mb-0">
        <thead>
          <tr>
            <th>Date</th>
            <th>Subject</th>
            <th>Grade-Section</th>
            <th>Status</th>
            <th>Time Scanned</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="6" class="text-center py-4 text-muted">No attendance records found.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= h((string)$r['date']) ?></td>
                <td><?= h((string)$r['subject_name']) ?></td>
                <td><?= h((string)$r['schedule_grade'] . '-' . (string)$r['schedule_section']) ?></td>
                <td><?= h((string)$r['status']) ?></td>
                <td><?= h((string)($r['time_scanned'] ?? '')) ?></td>
                <td><?= h((string)($r['remarks'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
require __DIR__ . '/partials/layout_bottom.php';
