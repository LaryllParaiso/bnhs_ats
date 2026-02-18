<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

$teacherId = (int)$_SESSION['teacher_id'];
$isAdmin = is_admin();

$day = trim((string)($_GET['day'] ?? ''));
$grade = trim((string)($_GET['grade'] ?? ''));
$section = trim((string)($_GET['section'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$subject = trim((string)($_GET['subject'] ?? ''));
$export = trim((string)($_GET['export'] ?? ''));

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$where = [];
$params = [];

if (!$isAdmin) {
    $where[] = 'teacher_id = :teacher_id';
    $params[':teacher_id'] = $teacherId;
}

if (in_array($day, ['Monday','Tuesday','Wednesday','Thursday','Friday'], true)) {
    $where[] = 'day_of_week = :day';
    $params[':day'] = $day;
}

if ($grade !== '' && ctype_digit($grade)) {
    $where[] = 'grade_level = :grade';
    $params[':grade'] = (int)$grade;
}

if ($section !== '') {
    $where[] = 'section = :section';
    $params[':section'] = $section;
}

if (in_array($status, ['Active','Inactive','Archived'], true)) {
    $where[] = 'status = :status';
    $params[':status'] = $status;
}

if ($subject !== '') {
    $where[] = 'subject_name = :subject';
    $params[':subject'] = $subject;
}

$sql = 'SELECT * FROM schedules' . ($where ? (' WHERE ' . implode(' AND ', $where)) : '') . ' ORDER BY FIELD(day_of_week, "Monday","Tuesday","Wednesday","Thursday","Friday"), start_time';

$pdo = db();

$countSql = 'SELECT COUNT(*) AS cnt FROM schedules' . ($where ? (' WHERE ' . implode(' AND ', $where)) : '');
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = (int)(($stmt->fetch()['cnt'] ?? 0));

$pg = paginate($total, $page, $perPage);
$page = (int)$pg['page'];
$limit = (int)$pg['per_page'];
$offset = (int)$pg['offset'];

// Get distinct subject names for filter dropdown
$subjectListSql = 'SELECT DISTINCT subject_name FROM schedules';
if (!$isAdmin) {
    $subjectListSql .= ' WHERE teacher_id = ' . (int)$teacherId;
}
$subjectListSql .= ' ORDER BY subject_name';
$subjectList = $pdo->query($subjectListSql)->fetchAll(PDO::FETCH_COLUMN);

if ($export !== '') {
    // Fetch all rows (no pagination) for export
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $schedules = $stmt->fetchAll();
} else {
    $pagedSql = $sql . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($pagedSql);
    $stmt->execute($params);
    $schedules = $stmt->fetchAll();
}

// Handle exports
if ($export === 'pdf' || $export === 'xlsx') {
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Export dependencies are not installed. Run composer install first.';
        exit;
    }
    require_once $autoload;
}

if ($export === 'pdf') {
    $schoolName = 'Bicos National High School';
    $schoolId = '300789';
    $schoolYear = date('Y') . ' - ' . (date('Y') + 1);

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('BNHS Attendance System');
    $pdf->SetAuthor('BNHS');
    $pdf->SetTitle('Schedules');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 10);
    $pdf->AddPage();

    $pageW = $pdf->getPageWidth() - 20;

    // School header
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell($pageW, 7, 'Class Schedules', 0, 1, 'C');
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
    $pdf->Cell(25, 5, 'Total Schedules:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(15, 5, (string)count($schedules), 'B', 0);
    $pdf->Ln(6);

    // Filter summary
    $filters = [];
    if ($day !== '') $filters[] = 'Day: ' . $day;
    if ($grade !== '') $filters[] = 'Grade: ' . $grade;
    if ($section !== '') $filters[] = 'Section: ' . $section;
    if ($subject !== '') $filters[] = 'Subject: ' . $subject;
    if ($status !== '') $filters[] = 'Status: ' . $status;
    if ($filters) {
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(15, 5, 'Filters:', 0, 0);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(100, 5, implode(' | ', $filters), 0, 0);
        $pdf->Ln(6);
    }

    // Table header
    $colW = [55, 30, 30, 45, 30, 25];
    $headers = ['Subject', 'Grade-Section', 'Day', 'Time', 'Room', 'Status'];

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(41, 50, 97);
    $pdf->SetTextColor(255, 255, 255);
    for ($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($colW[$i], 6, $headers[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 8);

    $fill = false;
    foreach ($schedules as $s) {
        if ($fill) {
            $pdf->SetFillColor(240, 240, 245);
        }

        $pdf->Cell($colW[0], 5, (string)$s['subject_name'], 1, 0, 'L', $fill);
        $pdf->Cell($colW[1], 5, (string)$s['grade_level'] . '-' . (string)$s['section'], 1, 0, 'C', $fill);
        $pdf->Cell($colW[2], 5, (string)$s['day_of_week'], 1, 0, 'C', $fill);
        $pdf->Cell($colW[3], 5, (string)$s['start_time'] . ' - ' . (string)$s['end_time'], 1, 0, 'C', $fill);
        $pdf->Cell($colW[4], 5, (string)($s['room'] ?? ''), 1, 0, 'C', $fill);
        $pdf->Cell($colW[5], 5, (string)$s['status'], 1, 0, 'C', $fill);
        $pdf->Ln();
        $fill = !$fill;
    }

    $pdf->Ln(5);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->Cell($pageW, 4, 'Generated: ' . date('Y-m-d H:i:s') . ' | BNHS Attendance System', 0, 1, 'R');

    $pdf->Output('schedules.pdf', 'D');
    exit;
}

if ($export === 'xlsx') {
    $schoolName = 'Bicos National High School';
    $schoolId = '300789';
    $schoolYear = date('Y') . ' - ' . (date('Y') + 1);

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Schedules');

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
    $sheet->mergeCells("A{$row}:F{$row}");
    $sheet->setCellValue("A{$row}", 'Class Schedules');
    $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    $row = 2;
    $sheet->mergeCells("A{$row}:F{$row}");
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
    $sheet->setCellValue("E{$row}", 'Total:');
    $sheet->setCellValue("F{$row}", count($schedules));
    $sheet->getStyle("F{$row}")->getFont()->setBold(true);

    // Filters
    $row = 5;
    $filters = [];
    if ($day !== '') $filters[] = 'Day: ' . $day;
    if ($grade !== '') $filters[] = 'Grade: ' . $grade;
    if ($section !== '') $filters[] = 'Section: ' . $section;
    if ($subject !== '') $filters[] = 'Subject: ' . $subject;
    if ($status !== '') $filters[] = 'Status: ' . $status;
    if ($filters) {
        $sheet->setCellValue("A{$row}", 'Filters:');
        $sheet->setCellValue("B{$row}", implode(' | ', $filters));
        $sheet->getStyle("B{$row}")->getFont()->setBold(true);
    }

    // Table header
    $row = 7;
    $headers = ['Subject', 'Grade-Section', 'Day', 'Time', 'Room', 'Status'];
    $sheet->fromArray([$headers], null, "A{$row}");
    $sheet->getStyle("A{$row}:F{$row}")->applyFromArray($headerFill);
    $sheet->getStyle("A{$row}:F{$row}")->applyFromArray($thinBorder);

    // Data rows
    $rowNum = 8;
    foreach ($schedules as $s) {
        $sheet->fromArray([[
            (string)$s['subject_name'],
            (string)$s['grade_level'] . '-' . (string)$s['section'],
            (string)$s['day_of_week'],
            (string)$s['start_time'] . ' - ' . (string)$s['end_time'],
            (string)($s['room'] ?? ''),
            (string)$s['status'],
        ]], null, 'A' . $rowNum);

        if ($rowNum % 2 === 0) {
            $sheet->getStyle("A{$rowNum}:F{$rowNum}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F0F0F5');
        }
        $rowNum++;
    }

    // Borders on data area
    $lastDataRow = $rowNum - 1;
    if ($lastDataRow >= 8) {
        $sheet->getStyle("A8:F{$lastDataRow}")->applyFromArray($thinBorder);
    }

    // Footer
    $rowNum++;
    $sheet->setCellValue("A{$rowNum}", 'Generated: ' . date('Y-m-d H:i:s') . ' | BNHS Attendance System');
    $sheet->getStyle("A{$rowNum}")->getFont()->setSize(8)->setItalic(true);

    // Auto-size columns
    foreach (range('A', 'F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="schedules.xlsx"');
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

$title = 'Schedules';
require __DIR__ . '/partials/layout_top.php';
?>

<div class="bnhs-page-header">
  <h1 class="bnhs-page-title">Schedules</h1>
  <div class="bnhs-page-actions d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-success btn-sm" href="<?= h(url('schedules.php?' . http_build_query(array_merge($_GET, ['export' => 'xlsx'])))) ?>">Export Excel</a>
    <a class="btn btn-outline-danger btn-sm" href="<?= h(url('schedules.php?' . http_build_query(array_merge($_GET, ['export' => 'pdf'])))) ?>">Export PDF</a>
    <a class="btn btn-primary btn-sm" href="<?= h(url('schedule_form.php')) ?>">Create Schedule</a>
  </div>
</div>

<div class="card shadow-sm mb-3 bnhs-filter-card">
  <div class="card-body">
    <form class="row g-2" method="get" action="<?= h(url('schedules.php')) ?>" id="schedulesFilterForm">
      <div class="col-md-2">
        <select class="form-select" name="subject">
          <option value="" <?= $subject === '' ? 'selected' : '' ?>>All Subjects</option>
          <?php foreach ($subjectList as $subj): ?>
            <option value="<?= h($subj) ?>" <?= $subject === $subj ? 'selected' : '' ?>><?= h($subj) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select class="form-select" name="day">
          <option value="" <?= $day === '' ? 'selected' : '' ?>>All Days</option>
          <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday'] as $d): ?>
            <option value="<?= h($d) ?>" <?= $day === $d ? 'selected' : '' ?>><?= h($d) ?></option>
          <?php endforeach; ?>
        </select>
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
          <option value="Archived" <?= $status === 'Archived' ? 'selected' : '' ?>>Archived</option>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <a class="btn btn-outline-secondary" href="<?= h(url('schedules.php')) ?>">Reset</a>
      </div>
    </form>
    <script>
    (function(){
      var form = document.getElementById('schedulesFilterForm');
      if(!form) return;
      var timer = null;
      function submit(){ form.submit(); }
      form.querySelectorAll('select').forEach(function(el){
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

<?php if (isset($_GET['saved'])): ?>
  <div class="alert alert-success">Schedule saved.</div>
<?php endif; ?>

<?php if (isset($_GET['requested'])): ?>
  <?php
    $reqType = (string)$_GET['requested'];
    $reqMsg = match($reqType) {
        'edit' => 'Your schedule edit request has been submitted for Super Admin approval.',
        'deactivate' => 'Your schedule status change request has been submitted for Super Admin approval.',
        'archive' => 'Your schedule archive request has been submitted for Super Admin approval.',
        default => 'Your request has been submitted for Super Admin approval.',
    };
  ?>
  <div class="alert alert-info"><?= h($reqMsg) ?></div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped mb-0">
        <thead>
          <tr>
            <th>Subject</th>
            <th>Grade-Section</th>
            <th>Day</th>
            <th>Time</th>
            <th>Room</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$schedules): ?>
            <tr>
              <td colspan="7" class="p-0">
                <div class="bnhs-empty-state">
                  <div class="bnhs-empty-icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path d="M8 7V3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                      <path d="M16 7V3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                      <path d="M3 11h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                      <path d="M5 7h14a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </div>
                  No schedules found.
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($schedules as $s): ?>
              <tr>
                <td><?= h((string)$s['subject_name']) ?></td>
                <td><?= h((string)$s['grade_level'] . '-' . (string)$s['section']) ?></td>
                <td><?= h((string)$s['day_of_week']) ?></td>
                <td><?= h((string)$s['start_time'] . '-' . (string)$s['end_time']) ?></td>
                <td><?= h((string)($s['room'] ?? '')) ?></td>
                <td><span class="bnhs-status-dot <?= strtolower((string)$s['status']) ?>"></span><?= h((string)$s['status']) ?></td>
                <td class="text-end">
                  <div class="bnhs-actions">
                  <a class="btn btn-sm btn-outline-primary" href="<?= h(url('schedule_form.php?id=' . (int)$s['schedule_id'])) ?>">Edit</a>

                  <?php if ($s['status'] !== 'Archived'): ?>
                    <form class="d-inline" method="post" action="<?= h(url('schedule_action.php')) ?>">
                      <input type="hidden" name="id" value="<?= h((string)$s['schedule_id']) ?>">
                      <input type="hidden" name="action" value="toggle_status">
                      <button class="btn btn-sm btn-outline-secondary" type="submit">
                        <?= $s['status'] === 'Active' ? 'Deactivate' : 'Activate' ?>
                      </button>
                    </form>

                    <form class="d-inline" method="post" action="<?= h(url('schedule_action.php')) ?>" data-confirm="Archive this schedule?" data-confirm-title="Archive Schedule" data-confirm-ok="Archive" data-confirm-cancel="Cancel" data-confirm-icon="warning">
                      <input type="hidden" name="id" value="<?= h((string)$s['schedule_id']) ?>">
                      <input type="hidden" name="action" value="archive">
                      <button class="btn btn-sm btn-outline-danger" type="submit">Archive</button>
                    </form>
                  <?php endif; ?>
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
      Showing <?= (int)$pg['from'] ?>-<?= (int)$pg['to'] ?> of <?= (int)$pg['total'] ?>
    </div>
    <?= pagination_html('schedules.php', $_GET, (int)$pg['page'], (int)$pg['per_page'], (int)$pg['total']) ?>
  </div>
</div>

<?php
require __DIR__ . '/partials/layout_bottom.php';
