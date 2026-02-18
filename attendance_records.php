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

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

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

if (in_array($status, ['Present', 'Late', 'Absent', 'Suspended'], true)) {
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

if ($export !== '') {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} else {
    $countSql =
        'SELECT COUNT(*) AS cnt
         FROM attendance a
         JOIN students st ON st.student_id = a.student_id
         JOIN schedules sch ON sch.schedule_id = a.schedule_id' .
        $sqlWhere;

    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $rowTotal = (int)(($stmt->fetch()['cnt'] ?? 0));

    $rowPg = paginate($rowTotal, $page, $perPage);
    $page = (int)$rowPg['page'];
    $limit = (int)$rowPg['per_page'];
    $offset = (int)$rowPg['offset'];

    $pagedSql = $sql . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($pagedSql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
}

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
    $schoolName = 'Bicos National High School';
    $schoolId = '300789';
    $schoolYear = date('Y') . ' - ' . (date('Y') + 1);

    $pdf = new TCPDF('L', 'mm', 'LEGAL', true, 'UTF-8', false);
    $pdf->SetCreator('BNHS Attendance System');
    $pdf->SetAuthor('BNHS');
    $pdf->SetTitle('Attendance Records');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(8, 8, 8);
    $pdf->SetAutoPageBreak(true, 8);
    $pdf->AddPage();

    $pageW = $pdf->getPageWidth() - 16;

    // School header
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell($pageW, 7, 'Attendance Records', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell($pageW, 4, $schoolName, 0, 1, 'C');
    $pdf->Ln(3);

    // Info row
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(20, 5, 'School ID:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(25, 5, $schoolId, 'B', 0);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(22, 5, 'School Year:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(30, 5, $schoolYear, 'B', 0);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(22, 5, 'Date Range:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(50, 5, $from . ' to ' . $to, 'B', 0);
    $pdf->Ln(6);

    // Filter summary
    $filters = [];
    if ($scheduleId > 0) $filters[] = 'Schedule #' . $scheduleId;
    if ($grade !== '') $filters[] = 'Grade: ' . $grade;
    if ($section !== '') $filters[] = 'Section: ' . $section;
    if ($status !== '') $filters[] = 'Status: ' . $status;
    if ($filters) {
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(15, 5, 'Filters:', 0, 0);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(100, 5, implode(' | ', $filters), 0, 0);
        $pdf->Ln(6);
    }

    // Totals
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(25, 5, 'Present: ' . $present, 0, 0);
    $pdf->Cell(25, 5, 'Late: ' . $late, 0, 0);
    $pdf->Cell(25, 5, 'Absent: ' . $absent, 0, 0);
    $pdf->Cell(25, 5, 'Total: ' . $total, 0, 0);
    $pdf->Ln(7);

    // Table header
    $colW = [24, 60, 28, 55, 22, 18, 18, 40];
    $headers = ['Date', 'Subject', 'LRN', 'Name', 'Grade-Sec', 'Status', 'Time', 'Remarks'];

    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->SetFillColor(41, 50, 97);
    $pdf->SetTextColor(255, 255, 255);
    for ($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($colW[$i], 6, $headers[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 7);

    $fill = false;
    foreach ($rows as $r) {
        $suffix = trim((string)($r['suffix'] ?? ''));
        $name = trim((string)$r['last_name'] . ', ' . (string)$r['first_name']);
        if ($suffix !== '') {
            $name .= ' ' . $suffix;
        }
        $sub = (string)$r['subject_name'];
        $timeWindow = (string)$r['start_time'] . '-' . (string)$r['end_time'];
        $subjectLabel = $sub . ' (' . (string)$r['day_of_week'] . ' ' . $timeWindow . ')';

        if ($fill) {
            $pdf->SetFillColor(240, 240, 245);
        }

        $pdf->Cell($colW[0], 5, (string)$r['date'], 1, 0, 'C', $fill);
        $pdf->Cell($colW[1], 5, $subjectLabel, 1, 0, 'L', $fill);
        $pdf->Cell($colW[2], 5, (string)$r['lrn'], 1, 0, 'C', $fill);
        $pdf->Cell($colW[3], 5, $name, 1, 0, 'L', $fill);
        $pdf->Cell($colW[4], 5, (string)$r['grade_level'] . '-' . (string)$r['section'], 1, 0, 'C', $fill);
        $pdf->Cell($colW[5], 5, (string)$r['status'], 1, 0, 'C', $fill);
        $pdf->Cell($colW[6], 5, (string)($r['time_scanned'] ?? ''), 1, 0, 'C', $fill);
        $pdf->Cell($colW[7], 5, (string)($r['remarks'] ?? ''), 1, 0, 'L', $fill);
        $pdf->Ln();
        $fill = !$fill;
    }

    $pdf->Ln(5);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->Cell($pageW, 4, 'Generated: ' . date('Y-m-d H:i:s') . ' | BNHS Attendance System', 0, 1, 'R');

    $file = 'attendance_records_' . $from . '_to_' . $to . '.pdf';
    $pdf->Output($file, 'D');
    exit;
}

if ($export === 'xlsx') {
    $schoolName = 'Bicos National High School';
    $schoolId = '300789';
    $schoolYear = date('Y') . ' - ' . (date('Y') + 1);

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Attendance Records');

    $thinBorder = [
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
    ];
    $headerFill = [
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '293261']],
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
    ];

    // Title
    $row = 1;
    $sheet->mergeCells("A{$row}:K{$row}");
    $sheet->setCellValue("A{$row}", 'Attendance Records');
    $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    $row = 2;
    $sheet->mergeCells("A{$row}:K{$row}");
    $sheet->setCellValue("A{$row}", $schoolName);
    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("A{$row}")->getFont()->setSize(9);

    // Info row
    $row = 4;
    $sheet->setCellValue("A{$row}", 'School ID:');
    $sheet->setCellValue("B{$row}", $schoolId);
    $sheet->getStyle("B{$row}")->getFont()->setBold(true);
    $sheet->setCellValue("C{$row}", 'School Year:');
    $sheet->setCellValue("D{$row}", $schoolYear);
    $sheet->getStyle("D{$row}")->getFont()->setBold(true);
    $sheet->setCellValue("E{$row}", 'Date Range:');
    $sheet->setCellValue("F{$row}", $from . ' to ' . $to);
    $sheet->getStyle("F{$row}")->getFont()->setBold(true);

    // Filters
    $row = 5;
    $filters = [];
    if ($scheduleId > 0) $filters[] = 'Schedule #' . $scheduleId;
    if ($grade !== '') $filters[] = 'Grade: ' . $grade;
    if ($section !== '') $filters[] = 'Section: ' . $section;
    if ($status !== '') $filters[] = 'Status: ' . $status;
    if ($filters) {
        $sheet->setCellValue("A{$row}", 'Filters:');
        $sheet->setCellValue("B{$row}", implode(' | ', $filters));
        $sheet->getStyle("B{$row}")->getFont()->setBold(true);
    }

    // Totals
    $row = 6;
    $sheet->setCellValue("A{$row}", 'Present: ' . $present);
    $sheet->getStyle("A{$row}")->getFont()->setBold(true);
    $sheet->setCellValue("B{$row}", 'Late: ' . $late);
    $sheet->getStyle("B{$row}")->getFont()->setBold(true);
    $sheet->setCellValue("C{$row}", 'Absent: ' . $absent);
    $sheet->getStyle("C{$row}")->getFont()->setBold(true);
    $sheet->setCellValue("D{$row}", 'Total: ' . $total);
    $sheet->getStyle("D{$row}")->getFont()->setBold(true);

    // Table header
    $row = 8;
    $headers = ['Date', 'Subject', 'Day', 'Time', 'LRN', 'Name', 'Grade', 'Section', 'Status', 'Time Scanned', 'Remarks'];
    $sheet->fromArray([$headers], null, "A{$row}");
    $lastCol = chr(64 + count($headers));
    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray($headerFill);
    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray($thinBorder);

    // Data rows
    $rowNum = 9;
    foreach ($rows as $r) {
        $suffix = trim((string)($r['suffix'] ?? ''));
        $name = trim((string)$r['last_name'] . ', ' . (string)$r['first_name']);
        if ($suffix !== '') {
            $name .= ' ' . $suffix;
        }

        $sheet->fromArray([[
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
        ]], null, 'A' . $rowNum);

        if ($rowNum % 2 === 0) {
            $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F0F0F5');
        }
        $rowNum++;
    }

    // Borders on data area
    $lastDataRow = $rowNum - 1;
    if ($lastDataRow >= 9) {
        $sheet->getStyle("A9:{$lastCol}{$lastDataRow}")->applyFromArray($thinBorder);
    }

    // Footer
    $rowNum++;
    $sheet->setCellValue("A{$rowNum}", 'Generated: ' . date('Y-m-d H:i:s') . ' | BNHS Attendance System');
    $sheet->getStyle("A{$rowNum}")->getFont()->setSize(8)->setItalic(true);

    // Auto-size columns
    foreach (range('A', $lastCol) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="attendance_records_' . $from . '_to_' . $to . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Fetch grade/section lists for filter datalists
$filterGrades = [];
$filterSections = [];
try {
    $filterGrades = $pdo->query('SELECT DISTINCT grade_level FROM grade_sections WHERE status = "Active" ORDER BY grade_level ASC')->fetchAll(PDO::FETCH_COLUMN);
    $filterSections = $pdo->query('SELECT DISTINCT section FROM grade_sections WHERE status = "Active" ORDER BY section ASC')->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {}

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
        <div class="bnhs-metric metric-green">
          <div class="bnhs-metric-icon icon-green"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
          <div class="bnhs-metric-label">Present</div>
          <div class="bnhs-metric-value" id="metricPresent"><?= (int)$present ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="bnhs-metric metric-orange">
          <div class="bnhs-metric-icon icon-orange"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
          <div class="bnhs-metric-label">Late</div>
          <div class="bnhs-metric-value" id="metricLate"><?= (int)$late ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="bnhs-metric metric-red">
          <div class="bnhs-metric-icon icon-red"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
          <div class="bnhs-metric-label">Absent</div>
          <div class="bnhs-metric-value" id="metricAbsent"><?= (int)$absent ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="bnhs-metric metric-blue">
          <div class="bnhs-metric-icon icon-blue"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></div>
          <div class="bnhs-metric-label">Total</div>
          <div class="bnhs-metric-value" id="metricTotal"><?= (int)$total ?></div>
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
          <option value="Present" <?= $status === 'Present' ? 'selected' : '' ?>>Present</option>
          <option value="Late" <?= $status === 'Late' ? 'selected' : '' ?>>Late</option>
          <option value="Absent" <?= $status === 'Absent' ? 'selected' : '' ?>>Absent</option>
          <option value="Suspended" <?= $status === 'Suspended' ? 'selected' : '' ?>>Suspended</option>
        </select>
      </div>
      <div class="col-md-12 d-grid d-md-block">
        <a class="btn btn-link" href="<?= h(url('attendance_records.php')) ?>">Reset</a>
      </div>
    </form>
    <script>
    (function(){
      var form = document.querySelector('.bnhs-filter-card form');
      if(!form) return;
      var timer = null;
      function submit(){ form.submit(); }
      form.querySelectorAll('select, input[type="date"]').forEach(function(el){
        el.addEventListener('change', submit);
      });
      form.querySelectorAll('input[list], input[type="text"], input:not([type])').forEach(function(el){
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
      <table class="table table-striped mb-0" id="dashboardTable">
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
        <tbody id="dashboardTbody">
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
                <td><span class="bnhs-status-dot <?= strtolower((string)$r['status']) ?>"></span><?= h((string)$r['status']) ?></td>
                <td><?= h((string)($r['time_scanned'] ?? '')) ?></td>
                <td><?= h((string)($r['remarks'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($export === ''): ?>
    <div class="d-flex justify-content-between align-items-center p-2 border-top">
      <div class="text-muted small">
        <span id="dashboardPagInfo">Showing <?= (int)$rowPg['from'] ?>-<?= (int)$rowPg['to'] ?> of <?= (int)$rowPg['total'] ?></span>
      </div>
      <?= pagination_html('attendance_records.php', $_GET, (int)$rowPg['page'], (int)$rowPg['per_page'], (int)$rowPg['total']) ?>
    </div>
  <?php endif; ?>
</div>

<script>
(function(){
  var POLL_INTERVAL = 5000;
  var baseUrl = <?= json_encode(url('api_poll.php')) ?>;
  var currentParams = <?= json_encode(array_filter([
      'type' => 'dashboard',
      'from' => $from,
      'to' => $to,
      'schedule_id' => $scheduleId > 0 ? (string)$scheduleId : '',
      'grade' => $grade,
      'section' => $section,
      'status' => $status,
      'page' => (string)$page,
  ])) ?>;
  var studentHistoryBase = <?= json_encode(url('student_history.php?id=')) ?>;

  function escH(s){ var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

  function poll(){
    var qs = new URLSearchParams(currentParams).toString();
    fetch(baseUrl + '?' + qs, {headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){ return r.json(); })
      .then(function(d){
        if(!d.ok) return;
        var m = d.metrics;
        var el;
        el = document.getElementById('metricPresent'); if(el) el.textContent = m.present;
        el = document.getElementById('metricLate'); if(el) el.textContent = m.late;
        el = document.getElementById('metricAbsent'); if(el) el.textContent = m.absent;
        el = document.getElementById('metricTotal'); if(el) el.textContent = m.total;

        var tbody = document.getElementById('dashboardTbody');
        if(tbody && d.rows){
          var html = '';
          if(d.rows.length === 0){
            html = '<tr><td colspan="8" class="p-0"><div class="bnhs-empty-state">No attendance records found.</div></td></tr>';
          } else {
            d.rows.forEach(function(r){
              html += '<tr>';
              html += '<td>' + escH(r.date) + '</td>';
              html += '<td>' + escH(r.subject) + '</td>';
              html += '<td>' + escH(r.lrn) + '</td>';
              html += '<td><a href="' + escH(studentHistoryBase + r.student_id) + '">' + escH(r.name) + '</a></td>';
              html += '<td>' + escH(r.grade_section) + '</td>';
              html += '<td>' + escH(r.status) + '</td>';
              html += '<td>' + escH(r.time) + '</td>';
              html += '<td>' + escH(r.remarks) + '</td>';
              html += '</tr>';
            });
          }
          tbody.innerHTML = html;
        }

        var pInfo = document.getElementById('dashboardPagInfo');
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
