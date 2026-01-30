<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

$teacherId = (int)$_SESSION['teacher_id'];
$isAdmin = is_admin();

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$scheduleId = (int)($_GET['schedule_id'] ?? 0);
$grade = trim((string)($_GET['grade'] ?? ''));
$section = trim((string)($_GET['section'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$export = trim((string)($_GET['export'] ?? ''));

$today = new DateTimeImmutable('today');
$defaultFrom = $today->sub(new DateInterval('P6D'))->format('Y-m-d');
$defaultTo = $today->format('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = $defaultFrom;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = $defaultTo;
}

$pdo = db();

$stmt = $pdo->prepare(
    $isAdmin
        ? 'SELECT sch.schedule_id, sch.subject_name, sch.grade_level, sch.section, sch.day_of_week, sch.start_time, sch.end_time
           FROM schedules sch
           WHERE sch.status != "Archived"
           ORDER BY sch.subject_name, sch.grade_level, sch.section'
        : 'SELECT sch.schedule_id, sch.subject_name, sch.grade_level, sch.section, sch.day_of_week, sch.start_time, sch.end_time
           FROM schedules sch
           WHERE sch.teacher_id = :teacher_id AND sch.status != "Archived"
           ORDER BY sch.subject_name, sch.grade_level, sch.section'
);
$stmt->execute($isAdmin ? [] : [':teacher_id' => $teacherId]);
$schedules = $stmt->fetchAll();

$params = [':from' => $from, ':to' => $to];

$where = ['a.date BETWEEN :from AND :to'];

if (!$isAdmin) {
    $where[] = 'a.teacher_id = :teacher_id';
    $params[':teacher_id'] = $teacherId;
}

if ($scheduleId > 0) {
    $where[] = 'a.schedule_id = :schedule_id';
    $params[':schedule_id'] = $scheduleId;
}

if ($grade !== '' && ctype_digit($grade)) {
    $where[] = 'st.grade_level = :grade';
    $params[':grade'] = (int)$grade;
}

if ($section !== '') {
    $where[] = 'st.section = :section';
    $params[':section'] = $section;
}

if (in_array($status, ['Present', 'Late', 'Absent'], true)) {
    $where[] = 'a.status = :status';
    $params[':status'] = $status;
}

$sqlWhere = ' WHERE ' . implode(' AND ', $where);

$sql =
    'SELECT a.attendance_id, a.date, a.time_scanned, a.status, a.remarks,
            st.student_id, st.lrn, st.first_name, st.last_name, st.suffix, st.grade_level, st.section,
            sch.subject_name, sch.day_of_week, sch.start_time, sch.end_time
     FROM attendance a
     JOIN students st ON st.student_id = a.student_id
     JOIN schedules sch ON sch.schedule_id = a.schedule_id' .
    $sqlWhere .
    ' ORDER BY a.date DESC, sch.subject_name, st.last_name, st.first_name, st.lrn';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$aggSql =
    'SELECT
        SUM(CASE WHEN a.status = "Present" THEN 1 ELSE 0 END) AS present,
        SUM(CASE WHEN a.status = "Late" THEN 1 ELSE 0 END) AS late,
        SUM(CASE WHEN a.status = "Absent" THEN 1 ELSE 0 END) AS absent,
        COUNT(*) AS total
     FROM attendance a
     JOIN students st ON st.student_id = a.student_id' .
    $sqlWhere;

$stmt = $pdo->prepare($aggSql);
$stmt->execute($params);
$agg = $stmt->fetch();

$present = (int)($agg['present'] ?? 0);
$late = (int)($agg['late'] ?? 0);
$absent = (int)($agg['absent'] ?? 0);
$total = (int)($agg['total'] ?? 0);

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
    header('Content-Disposition: attachment; filename="attendance_records_' . $from . '_to_' . $to . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Subject', 'Day', 'Time', 'LRN', 'Name', 'Grade', 'Section', 'Status', 'Time Scanned', 'Remarks']);

    foreach ($rows as $r) {
        $suffix = trim((string)($r['suffix'] ?? ''));
        $name = trim((string)$r['last_name'] . ', ' . (string)$r['first_name']);
        if ($suffix !== '') {
            $name .= ' ' . $suffix;
        }

        fputcsv($out, [
            (string)$r['date'],
            (string)$r['subject_name'],
            (string)$r['day_of_week'],
            (string)$r['start_time'] . '-' . (string)$r['end_time'],
            (string)$r['lrn'],
            $name,
            (string)$r['grade_level'],
            (string)$r['section'],
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
    $pdf->SetTitle('Attendance Records');
    $pdf->SetMargins(12, 12, 12);
    $pdf->AddPage();

    $h = function (string $v): string {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    $html = '';
    $html .= '<h2>Attendance Records</h2>';
    $html .= '<div><strong>Date range:</strong> ' . $h($from) . ' to ' . $h($to) . '</div>';
    $html .= '<div><strong>Totals:</strong> Present ' . $h((string)$present) . ' | Late ' . $h((string)$late) . ' | Absent ' . $h((string)$absent) . ' | Total ' . $h((string)$total) . '</div>';
    $html .= '<br>';
    $html .= '<table border="1" cellpadding="4">
      <thead>
        <tr style="font-weight:bold;background-color:#f2f2f2;">
          <th width="13%">Date</th>
          <th width="22%">Subject</th>
          <th width="12%">LRN</th>
          <th width="23%">Name</th>
          <th width="10%">G-S</th>
          <th width="8%">Status</th>
          <th width="12%">Time</th>
        </tr>
      </thead>
      <tbody>';

    foreach ($rows as $r) {
        $suffix = trim((string)($r['suffix'] ?? ''));
        $name = trim((string)$r['last_name'] . ', ' . (string)$r['first_name']);
        if ($suffix !== '') {
            $name .= ' ' . $suffix;
        }
        $sub = (string)$r['subject_name'];
        $timeWindow = (string)$r['start_time'] . '-' . (string)$r['end_time'];
        $subjectLabel = $sub . ' (' . (string)$r['day_of_week'] . ' ' . $timeWindow . ')';

        $html .= '<tr>';
        $html .= '<td>' . $h((string)$r['date']) . '</td>';
        $html .= '<td>' . $h($subjectLabel) . '</td>';
        $html .= '<td>' . $h((string)$r['lrn']) . '</td>';
        $html .= '<td>' . $h($name) . '</td>';
        $html .= '<td>' . $h((string)$r['grade_level'] . '-' . (string)$r['section']) . '</td>';
        $html .= '<td>' . $h((string)$r['status']) . '</td>';
        $html .= '<td>' . $h((string)($r['time_scanned'] ?? '')) . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    $pdf->writeHTML($html, true, false, true, false, '');

    $file = 'attendance_records_' . $from . '_to_' . $to . '.pdf';
    $pdf->Output($file, 'D');
    exit;
}

if ($export === 'xlsx') {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Attendance Records');

    $sheet->fromArray([
        ['Date', 'Subject', 'Day', 'Time', 'LRN', 'Name', 'Grade', 'Section', 'Status', 'Time Scanned', 'Remarks'],
    ], null, 'A1');

    $rowNum = 2;
    foreach ($rows as $r) {
        $suffix = trim((string)($r['suffix'] ?? ''));
        $name = trim((string)$r['last_name'] . ', ' . (string)$r['first_name']);
        if ($suffix !== '') {
            $name .= ' ' . $suffix;
        }

        $sheet->fromArray([
            [
                (string)$r['date'],
                (string)$r['subject_name'],
                (string)$r['day_of_week'],
                (string)$r['start_time'] . '-' . (string)$r['end_time'],
                (string)$r['lrn'],
                $name,
                (string)$r['grade_level'],
                (string)$r['section'],
                (string)$r['status'],
                (string)($r['time_scanned'] ?? ''),
                (string)($r['remarks'] ?? ''),
            ],
        ], null, 'A' . $rowNum);
        $rowNum++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="attendance_records_' . $from . '_to_' . $to . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

$title = 'Dashboard';
require __DIR__ . '/partials/layout_top.php';
?>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
      <div>
        <h1 class="h4 mb-1"><?= $isAdmin ? 'Admin Dashboard' : 'Teacher Dashboard' ?></h1>
        <div class="text-muted">Welcome, <?= h((string)($_SESSION['teacher_name'] ?? ($isAdmin ? 'Admin' : 'Teacher'))) ?>.</div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-secondary btn-sm" href="<?= h(url('analytics.php?from=' . urlencode($from) . '&to=' . urlencode($to))) ?>">Analytics</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?= h(url('attendance_records.php?' . http_build_query(array_merge($_GET, ['export' => 'pdf'])))) ?>">Export PDF</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?= h(url('attendance_records.php?' . http_build_query(array_merge($_GET, ['export' => 'xlsx'])))) ?>">Export Excel</a>
        <a class="btn btn-outline-primary btn-sm" href="<?= h(url('attendance_records.php?' . http_build_query(array_merge($_GET, ['export' => 'csv'])))) ?>">Export CSV</a>
        <button class="btn btn-primary btn-sm" type="button" onclick="window.print();">Print</button>
      </div>
    </div>

    <div class="row g-3 mt-1">
      <div class="col-6 col-md-3">
        <div class="bnhs-metric">
          <div class="bnhs-metric-label">Present</div>
          <div class="bnhs-metric-value"><?= (int)$present ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="bnhs-metric">
          <div class="bnhs-metric-label">Late</div>
          <div class="bnhs-metric-value"><?= (int)$late ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="bnhs-metric">
          <div class="bnhs-metric-label">Absent</div>
          <div class="bnhs-metric-value"><?= (int)$absent ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
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
    <form class="row g-2" method="get" action="<?= h(url('attendance_records.php')) ?>">
      <div class="col-md-2">
        <input class="form-control" type="date" name="from" value="<?= h($from) ?>">
      </div>
      <div class="col-md-2">
        <input class="form-control" type="date" name="to" value="<?= h($to) ?>">
      </div>
      <div class="col-md-3">
        <select class="form-select" name="schedule_id">
          <option value="0" <?= $scheduleId === 0 ? 'selected' : '' ?>>All Subjects</option>
          <?php foreach ($schedules as $s): ?>
            <?php $label = (string)$s['subject_name'] . ' (G' . (string)$s['grade_level'] . '-' . (string)$s['section'] . ')'; ?>
            <option value="<?= h((string)$s['schedule_id']) ?>" <?= $scheduleId === (int)$s['schedule_id'] ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1">
        <input class="form-control" name="grade" placeholder="Grade" value="<?= h($grade) ?>">
      </div>
      <div class="col-md-2">
        <input class="form-control" name="section" placeholder="Section" value="<?= h($section) ?>">
      </div>
      <div class="col-md-2">
        <select class="form-select" name="status">
          <option value="" <?= $status === '' ? 'selected' : '' ?>>All Status</option>
          <option value="Present" <?= $status === 'Present' ? 'selected' : '' ?>>Present</option>
          <option value="Late" <?= $status === 'Late' ? 'selected' : '' ?>>Late</option>
          <option value="Absent" <?= $status === 'Absent' ? 'selected' : '' ?>>Absent</option>
        </select>
      </div>
      <div class="col-md-12 d-grid d-md-block">
        <button class="btn btn-outline-primary" type="submit">Apply Filters</button>
        <a class="btn btn-link" href="<?= h(url('attendance_records.php')) ?>">Reset</a>
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
            <th>Date</th>
            <th>Subject</th>
            <th>LRN</th>
            <th>Name</th>
            <th>Grade-Section</th>
            <th>Status</th>
            <th>Time</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr>
              <td colspan="8" class="p-0">
                <div class="bnhs-empty-state">
                  <div class="bnhs-empty-icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path d="M4 7V5a2 2 0 0 1 2-2h2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                      <path d="M20 7V5a2 2 0 0 0-2-2h-2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                      <path d="M4 17v2a2 2 0 0 0 2 2h2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                      <path d="M20 17v2a2 2 0 0 1-2 2h-2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                      <path d="M7 12h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                  </div>
                  No attendance records found.
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <?php
                $suffix = trim((string)($r['suffix'] ?? ''));
                $name = trim((string)$r['last_name'] . ', ' . (string)$r['first_name']);
                if ($suffix !== '') {
                    $name .= ' ' . $suffix;
                }

                $sub = (string)$r['subject_name'];
                $timeWindow = (string)$r['start_time'] . '-' . (string)$r['end_time'];
              ?>
              <tr>
                <td><?= h((string)$r['date']) ?></td>
                <td><?= h($sub . ' (' . (string)$r['day_of_week'] . ' ' . $timeWindow . ')') ?></td>
                <td><?= h((string)$r['lrn']) ?></td>
                <td>
                  <a href="<?= h(url('student_history.php?id=' . (int)$r['student_id'])) ?>"><?= h($name) ?></a>
                </td>
                <td><?= h((string)$r['grade_level'] . '-' . (string)$r['section']) ?></td>
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
