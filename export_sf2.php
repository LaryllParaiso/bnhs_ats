<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/vendor/autoload.php';

require_login();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

$teacherId = (int)$_SESSION['teacher_id'];
$isAdmin = is_admin();
$taughtGrades = teacher_grade_levels_taught($teacherId);

$format = strtolower(trim((string)($_GET['format'] ?? 'excel')));
$grade = trim((string)($_GET['grade'] ?? ''));
$section = trim((string)($_GET['section'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));
$month = trim((string)($_GET['month'] ?? date('Y-m')));

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
$sql .= ' ORDER BY sex DESC, last_name, first_name, lrn';

$pdo = db();
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allStudents = $stmt->fetchAll();

$maleStudents = array_filter($allStudents, fn($s) => $s['sex'] === 'Male');
$femaleStudents = array_filter($allStudents, fn($s) => $s['sex'] === 'Female');
$maleStudents = array_values($maleStudents);
$femaleStudents = array_values($femaleStudents);

$monthStart = date('Y-m-01', strtotime($month . '-01'));
$monthEnd = date('Y-m-t', strtotime($month . '-01'));
$reportMonth = date('F Y', strtotime($monthStart));

$schoolDays = [];
$d = new DateTime($monthStart);
$end = new DateTime($monthEnd);
while ($d <= $end) {
    $dow = (int)$d->format('N');
    if ($dow >= 1 && $dow <= 5) {
        $schoolDays[] = $d->format('Y-m-d');
    }
    $d->modify('+1 day');
}

$studentIds = array_map(fn($s) => (int)$s['student_id'], $allStudents);
$attendanceMap = [];
$attendanceTotals = [];

if ($studentIds) {
    $inPh = implode(',', array_fill(0, count($studentIds), '?'));
    $attSql = "SELECT student_id, date, status FROM attendance
               WHERE student_id IN ($inPh) AND date BETWEEN ? AND ?
               ORDER BY date";
    $attParams = array_merge($studentIds, [$monthStart, $monthEnd]);
    $attStmt = $pdo->prepare($attSql);
    $attStmt->execute($attParams);
    foreach ($attStmt->fetchAll() as $row) {
        $sid = (int)$row['student_id'];
        $attendanceMap[$sid][$row['date']] = $row['status'];
    }

    $totSql = "SELECT student_id,
                      SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) AS total_absent,
                      SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS total_present,
                      SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) AS total_late,
                      SUM(CASE WHEN status = 'Suspended' THEN 1 ELSE 0 END) AS total_suspended
               FROM attendance
               WHERE student_id IN ($inPh) AND date BETWEEN ? AND ?
               GROUP BY student_id";
    $totStmt = $pdo->prepare($totSql);
    $totStmt->execute($attParams);
    foreach ($totStmt->fetchAll() as $row) {
        $attendanceTotals[(int)$row['student_id']] = $row;
    }
}

$gradeLabel = $grade !== '' ? $grade : 'All';
if ($section !== '') {
    $sectionLabel = $section;
} else {
    $sections = array_unique(array_map(fn($s) => (string)$s['section'], $allStudents));
    sort($sections);
    $sectionLabel = $sections ? implode('/', $sections) : 'All';
}
$gradeLevelText = '';
if ($grade !== '') {
    $romanMap = [7 => 'Year I', 8 => 'Year II', 9 => 'Year III', 10 => 'Year IV', 11 => 'Year V', 12 => 'Year VI'];
    $gradeLevelText = 'Grade ' . $grade . ' (' . ($romanMap[(int)$grade] ?? '') . ')';
}

$schoolName = 'Bicos National High School';
$schoolId = '300789';
$schoolYear = date('Y') . ' - ' . (date('Y') + 1);

$dayNames = ['M', 'T', 'W', 'Th', 'F'];

if ($format === 'excel') {
    exportExcel($allStudents, $maleStudents, $femaleStudents, $schoolDays, $attendanceMap, $attendanceTotals, $reportMonth, $gradeLevelText, $sectionLabel, $schoolName, $schoolId, $schoolYear, $dayNames);
} else {
    exportPdf($allStudents, $maleStudents, $femaleStudents, $schoolDays, $attendanceMap, $attendanceTotals, $reportMonth, $gradeLevelText, $sectionLabel, $schoolName, $schoolId, $schoolYear, $dayNames);
}

function exportExcel(array $allStudents, array $males, array $females, array $schoolDays, array $attendanceMap, array $attendanceTotals, string $reportMonth, string $gradeLevelText, string $sectionLabel, string $schoolName, string $schoolId, string $schoolYear, array $dayNames): void
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('SF2');

    $sheet->getDefaultColumnDimension()->setWidth(3.5);
    $sheet->getColumnDimension('A')->setWidth(4);
    $sheet->getColumnDimension('B')->setWidth(35);

    $thinBorder = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
    ];

    $headerFont = ['font' => ['bold' => true, 'size' => 14]];
    $subHeaderFont = ['font' => ['bold' => true, 'size' => 10]];

    $row = 1;
    $sheet->mergeCells("A{$row}:Z{$row}");
    $sheet->setCellValue("A{$row}", 'School Form 2 (SF2) Daily Attendance Report of Learners');
    $sheet->getStyle("A{$row}")->applyFromArray($headerFont);
    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $row = 2;
    $sheet->mergeCells("A{$row}:Z{$row}");
    $sheet->setCellValue("A{$row}", '(This replaces Form 1, Form 2 & STS Form 4 - Absenteeism and Dropout Profile)');
    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("A{$row}")->getFont()->setSize(8);

    $row = 4;
    $sheet->setCellValue("A{$row}", 'School ID:');
    $sheet->setCellValue("C{$row}", $schoolId);
    $sheet->getStyle("C{$row}")->getFont()->setBold(true);

    $sheet->setCellValue("E{$row}", 'School Year:');
    $sheet->setCellValue("G{$row}", $schoolYear);
    $sheet->getStyle("G{$row}")->getFont()->setBold(true);

    $sheet->setCellValue("I{$row}", 'Report for the Month of:');
    $sheet->setCellValue("L{$row}", $reportMonth);
    $sheet->getStyle("L{$row}")->getFont()->setBold(true);

    $row = 5;
    $sheet->setCellValue("A{$row}", 'Name of School:');
    $sheet->setCellValue("C{$row}", $schoolName);
    $sheet->getStyle("C{$row}")->getFont()->setBold(true);

    $sheet->setCellValue("I{$row}", 'Grade Level:');
    $sheet->setCellValue("L{$row}", $gradeLevelText);
    $sheet->getStyle("L{$row}")->getFont()->setBold(true);

    $sheet->setCellValue("O{$row}", 'Section:');
    $sheet->setCellValue("P{$row}", $sectionLabel);
    $sheet->getStyle("P{$row}")->getFont()->setBold(true);

    $row = 7;
    $sheet->setCellValue("A{$row}", 'No.');
    $sheet->getStyle("A{$row}")->applyFromArray($subHeaderFont);
    $sheet->setCellValue("B{$row}", 'NAME');
    $sheet->getStyle("B{$row}")->applyFromArray($subHeaderFont);

    $row = 8;
    $sheet->setCellValue("B{$row}", '(Last Name, First Name, Middle Name)');
    $sheet->getStyle("B{$row}")->getFont()->setSize(8);

    $dateCol = 2;
    $weekGroups = [];
    $currentWeek = [];
    $prevWeekNum = null;

    foreach ($schoolDays as $sd) {
        $dt = new DateTime($sd);
        $weekNum = (int)$dt->format('W');
        if ($prevWeekNum !== null && $weekNum !== $prevWeekNum) {
            $weekGroups[] = $currentWeek;
            $currentWeek = [];
        }
        $currentWeek[] = $sd;
        $prevWeekNum = $weekNum;
    }
    if ($currentWeek) {
        $weekGroups[] = $currentWeek;
    }

    $colIndex = 2;
    $dayColMap = [];

    foreach ($weekGroups as $wg) {
        foreach ($wg as $sd) {
            $colIndex++;
            $colLetter = numToCol($colIndex);
            $dayColMap[$sd] = $colLetter;

            $dt = new DateTime($sd);
            $dayNum = (int)$dt->format('j');
            $sheet->setCellValue("{$colLetter}7", $dayNum);
            $sheet->getStyle("{$colLetter}7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("{$colLetter}7")->getFont()->setBold(true)->setSize(9);

            $dow = (int)$dt->format('N');
            $dayLabel = $dayNames[$dow - 1] ?? '';
            $sheet->setCellValue("{$colLetter}8", $dayLabel);
            $sheet->getStyle("{$colLetter}8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("{$colLetter}8")->getFont()->setSize(8);
        }
    }

    $lastDataCol = $colIndex;
    $absentCol = numToCol($lastDataCol + 1);
    $presentCol = numToCol($lastDataCol + 2);

    $sheet->setCellValue("{$absentCol}7", 'Total for the');
    $sheet->setCellValue("{$absentCol}8", 'ABSENT');
    $sheet->getStyle("{$absentCol}7")->getFont()->setBold(true)->setSize(9);
    $sheet->getStyle("{$absentCol}8")->getFont()->setBold(true)->setSize(9);
    $sheet->getStyle("{$absentCol}7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("{$absentCol}8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue("{$presentCol}7", 'Month');
    $sheet->setCellValue("{$presentCol}8", 'PRESENT');
    $sheet->getStyle("{$presentCol}7")->getFont()->setBold(true)->setSize(9);
    $sheet->getStyle("{$presentCol}8")->getFont()->setBold(true)->setSize(9);
    $sheet->getStyle("{$presentCol}7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("{$presentCol}8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->getColumnDimension($absentCol)->setWidth(8);
    $sheet->getColumnDimension($presentCol)->setWidth(8);

    $currentRow = 9;

    $currentRow = writeStudentRows($sheet, $males, $currentRow, $dayColMap, $attendanceMap, $attendanceTotals, $absentCol, $presentCol, $schoolDays, $thinBorder);

    $maleCountRow = $currentRow;
    $sheet->setCellValue("A{$maleCountRow}", count($males) + 1);
    $sheet->setCellValue("B{$maleCountRow}", '<=== MALE | TOTAL Per Day===>');
    $sheet->getStyle("B{$maleCountRow}")->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('FF0000');

    foreach ($dayColMap as $date => $col) {
        $presentCount = 0;
        foreach ($males as $s) {
            $sid = (int)$s['student_id'];
            $st = $attendanceMap[$sid][$date] ?? '';
            if ($st === 'Present' || $st === 'Late') {
                $presentCount++;
            }
        }
        $sheet->setCellValue("{$col}{$maleCountRow}", $presentCount);
        $sheet->getStyle("{$col}{$maleCountRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("{$col}{$maleCountRow}")->getFont()->setBold(true);
    }

    $totalMaleAbsent = 0;
    $totalMalePresent = 0;
    foreach ($males as $s) {
        $sid = (int)$s['student_id'];
        $totalMaleAbsent += (int)($attendanceTotals[$sid]['total_absent'] ?? 0);
        $totalMalePresent += (int)($attendanceTotals[$sid]['total_present'] ?? 0) + (int)($attendanceTotals[$sid]['total_late'] ?? 0);
    }
    $sheet->setCellValue("{$absentCol}{$maleCountRow}", $totalMaleAbsent);
    $sheet->setCellValue("{$presentCol}{$maleCountRow}", $totalMalePresent);
    $sheet->getStyle("{$absentCol}{$maleCountRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("{$presentCol}{$maleCountRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $currentRow = $maleCountRow + 1;

    $currentRow = writeStudentRows($sheet, $females, $currentRow, $dayColMap, $attendanceMap, $attendanceTotals, $absentCol, $presentCol, $schoolDays, $thinBorder);

    $femaleCountRow = $currentRow;
    $sheet->setCellValue("A{$femaleCountRow}", count($females) + 1);
    $sheet->setCellValue("B{$femaleCountRow}", '<=== FEMALE | TOTAL Per Day===>');
    $sheet->getStyle("B{$femaleCountRow}")->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('FF0000');

    foreach ($dayColMap as $date => $col) {
        $presentCount = 0;
        foreach ($females as $s) {
            $sid = (int)$s['student_id'];
            $st = $attendanceMap[$sid][$date] ?? '';
            if ($st === 'Present' || $st === 'Late') {
                $presentCount++;
            }
        }
        $sheet->setCellValue("{$col}{$femaleCountRow}", $presentCount);
        $sheet->getStyle("{$col}{$femaleCountRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("{$col}{$femaleCountRow}")->getFont()->setBold(true);
    }

    $totalFemaleAbsent = 0;
    $totalFemalePresent = 0;
    foreach ($females as $s) {
        $sid = (int)$s['student_id'];
        $totalFemaleAbsent += (int)($attendanceTotals[$sid]['total_absent'] ?? 0);
        $totalFemalePresent += (int)($attendanceTotals[$sid]['total_present'] ?? 0) + (int)($attendanceTotals[$sid]['total_late'] ?? 0);
    }
    $sheet->setCellValue("{$absentCol}{$femaleCountRow}", $totalFemaleAbsent);
    $sheet->setCellValue("{$presentCol}{$femaleCountRow}", $totalFemalePresent);
    $sheet->getStyle("{$absentCol}{$femaleCountRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("{$presentCol}{$femaleCountRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $currentRow = $femaleCountRow + 1;
    $combinedRow = $currentRow;
    $sheet->setCellValue("A{$combinedRow}", count($allStudents));
    $sheet->setCellValue("B{$combinedRow}", 'Combined TOTAL Per Day');
    $sheet->getStyle("B{$combinedRow}")->getFont()->setBold(true)->setSize(9);

    foreach ($dayColMap as $date => $col) {
        $presentCount = 0;
        foreach ($allStudents as $s) {
            $sid = (int)$s['student_id'];
            $st = $attendanceMap[$sid][$date] ?? '';
            if ($st === 'Present' || $st === 'Late') {
                $presentCount++;
            }
        }
        $sheet->setCellValue("{$col}{$combinedRow}", $presentCount);
        $sheet->getStyle("{$col}{$combinedRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("{$col}{$combinedRow}")->getFont()->setBold(true);
    }

    $sheet->setCellValue("{$absentCol}{$combinedRow}", $totalMaleAbsent + $totalFemaleAbsent);
    $sheet->setCellValue("{$presentCol}{$combinedRow}", $totalMalePresent + $totalFemalePresent);
    $sheet->getStyle("{$absentCol}{$combinedRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("{$presentCol}{$combinedRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $lastCol = numToCol($lastDataCol + 2);
    $sheet->getStyle("A7:{$lastCol}{$combinedRow}")->applyFromArray($thinBorder);

    $currentRow = $combinedRow + 2;
    $sheet->setCellValue("A{$currentRow}", 'GUIDELINES:');
    $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(9);
    $currentRow++;
    $sheet->setCellValue("A{$currentRow}", '1. The attendance shall be accomplished daily. Refer to the codes for checking learners\' attendance.');
    $sheet->getStyle("A{$currentRow}")->getFont()->setSize(8);
    $currentRow++;
    $sheet->setCellValue("A{$currentRow}", '2. Dates shall be written in the columns after Learner\'s Name.');
    $sheet->getStyle("A{$currentRow}")->getFont()->setSize(8);
    $currentRow++;
    $sheet->setCellValue("A{$currentRow}", 'CODES: (blank) = Present | A = Absent | L = Late | S = Suspended / No Class');
    $sheet->getStyle("A{$currentRow}")->getFont()->setSize(8);

    $fileName = 'SF2_' . date('Y') . '_' . str_replace(' ', '_', $gradeLevelText ?: 'All') . '_' . $sectionLabel . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

function writeStudentRows(
    \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
    array $students,
    int $startRow,
    array $dayColMap,
    array $attendanceMap,
    array $attendanceTotals,
    string $absentCol,
    string $presentCol,
    array $schoolDays,
    array $thinBorder
): int {
    $row = $startRow;
    $num = 1;

    foreach ($students as $s) {
        $sid = (int)$s['student_id'];
        $suffix = trim((string)($s['suffix'] ?? ''));
        $name = strtoupper(trim((string)$s['last_name'] . ', ' . (string)$s['first_name'] . ', ' . (string)($s['middle_name'] ?? '')));
        if ($suffix !== '') {
            $name = strtoupper(trim((string)$s['last_name'] . ', ' . (string)$s['first_name'] . ' ' . $suffix . ', ' . (string)($s['middle_name'] ?? '')));
        }

        $sheet->setCellValue("A{$row}", $num);
        $sheet->setCellValue("B{$row}", $name);
        $sheet->getStyle("B{$row}")->getFont()->setSize(9);

        foreach ($dayColMap as $date => $col) {
            $st = $attendanceMap[$sid][$date] ?? '';
            $mark = '';
            if ($st === 'Absent') {
                $mark = 'A';
            } elseif ($st === 'Late') {
                $mark = 'L';
            } elseif ($st === 'Suspended') {
                $mark = 'S';
            }
            if ($mark !== '') {
                $sheet->setCellValue("{$col}{$row}", $mark);
                $sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                if ($st === 'Suspended') {
                    $sheet->getStyle("{$col}{$row}")->getFont()->getColor()->setRGB('888888');
                }
            }
        }

        $absent = (int)($attendanceTotals[$sid]['total_absent'] ?? 0);
        $present = (int)($attendanceTotals[$sid]['total_present'] ?? 0) + (int)($attendanceTotals[$sid]['total_late'] ?? 0);
        $sheet->setCellValue("{$absentCol}{$row}", $absent);
        $sheet->setCellValue("{$presentCol}{$row}", $present);
        $sheet->getStyle("{$absentCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("{$presentCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $num++;
        $row++;
    }

    return $row;
}

function numToCol(int $num): string
{
    $col = '';
    while ($num > 0) {
        $mod = ($num - 1) % 26;
        $col = chr(65 + $mod) . $col;
        $num = (int)(($num - $mod) / 26);
    }
    return $col;
}

function exportPdf(array $allStudents, array $males, array $females, array $schoolDays, array $attendanceMap, array $attendanceTotals, string $reportMonth, string $gradeLevelText, string $sectionLabel, string $schoolName, string $schoolId, string $schoolYear, array $dayNames): void
{
    $pdf = new TCPDF('L', 'mm', 'LEGAL', true, 'UTF-8', false);
    $pdf->SetCreator('BNHS Attendance System');
    $pdf->SetAuthor('BNHS');
    $pdf->SetTitle('SF2 - ' . $reportMonth);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(5, 5, 5);
    $pdf->SetAutoPageBreak(true, 5);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 14);

    $pageW = $pdf->getPageWidth() - 10;

    $pdf->Cell($pageW, 7, 'School Form 2 (SF2) Daily Attendance Report of Learners', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 7);
    $pdf->Cell($pageW, 4, '(This replaces Form 1, Form 2 & STS Form 4 - Absenteeism and Dropout Profile)', 0, 1, 'C');
    $pdf->Ln(3);

    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(20, 5, 'School ID:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(25, 5, $schoolId, 'B', 0);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(20, 5, 'School Year:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(25, 5, $schoolYear, 'B', 0);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(35, 5, 'Report for the Month of:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(30, 5, $reportMonth, 'B', 0);
    $pdf->Ln(6);

    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(25, 5, 'Name of School:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(50, 5, $schoolName, 'B', 0);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(20, 5, 'Grade Level:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(40, 5, $gradeLevelText, 'B', 0);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(15, 5, 'Section:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(30, 5, $sectionLabel, 'B', 0);
    $pdf->Ln(8);

    $nameColW = 55;
    $noColW = 8;
    $numDays = count($schoolDays);
    $absentColW = 12;
    $presentColW = 12;
    $remainingW = $pageW - $noColW - $nameColW - $absentColW - $presentColW;
    $dayColW = $numDays > 0 ? max(5, $remainingW / $numDays) : 6;

    $pdf->SetFont('helvetica', 'B', 7);
    $headerY = $pdf->GetY();

    $pdf->Cell($noColW, 8, 'No.', 1, 0, 'C');
    $pdf->Cell($nameColW, 8, 'NAME (Last Name, First Name, Middle Name)', 1, 0, 'C');

    foreach ($schoolDays as $sd) {
        $dt = new DateTime($sd);
        $dayNum = $dt->format('j');
        $pdf->Cell($dayColW, 4, $dayNum, 1, 0, 'C');
    }
    $pdf->Cell($absentColW, 4, 'ABSENT', 1, 0, 'C');
    $pdf->Cell($presentColW, 4, 'PRESENT', 1, 0, 'C');
    $pdf->Ln(4);

    $pdf->SetX(5 + $noColW + $nameColW);
    foreach ($schoolDays as $sd) {
        $dt = new DateTime($sd);
        $dow = (int)$dt->format('N');
        $dayLabel = $dayNames[$dow - 1] ?? '';
        $pdf->Cell($dayColW, 4, $dayLabel, 1, 0, 'C');
    }
    $pdf->Cell($absentColW, 4, '', 1, 0, 'C');
    $pdf->Cell($presentColW, 4, '', 1, 0, 'C');
    $pdf->Ln();

    $pdf->SetFont('helvetica', '', 7);

    $writeStudentRowsPdf = function (array $students) use ($pdf, $noColW, $nameColW, $dayColW, $absentColW, $presentColW, $schoolDays, $attendanceMap, $attendanceTotals) {
        $num = 1;
        foreach ($students as $s) {
            $sid = (int)$s['student_id'];
            $suffix = trim((string)($s['suffix'] ?? ''));
            $name = strtoupper(trim($s['last_name'] . ', ' . $s['first_name'] . ', ' . ($s['middle_name'] ?? '')));
            if ($suffix !== '') {
                $name = strtoupper(trim($s['last_name'] . ', ' . $s['first_name'] . ' ' . $suffix . ', ' . ($s['middle_name'] ?? '')));
            }

            $pdf->Cell($noColW, 5, (string)$num, 1, 0, 'C');
            $pdf->Cell($nameColW, 5, $name, 1, 0, 'L');

            foreach ($schoolDays as $sd) {
                $st = $attendanceMap[$sid][$sd] ?? '';
                $mark = '';
                if ($st === 'Absent') $mark = 'A';
                elseif ($st === 'Late') $mark = 'L';
                elseif ($st === 'Suspended') $mark = 'S';
                if ($st === 'Suspended') {
                    $pdf->SetTextColor(136, 136, 136);
                }
                $pdf->Cell($dayColW, 5, $mark, 1, 0, 'C');
                if ($st === 'Suspended') {
                    $pdf->SetTextColor(0, 0, 0);
                }
            }

            $absent = (int)($attendanceTotals[$sid]['total_absent'] ?? 0);
            $present = (int)($attendanceTotals[$sid]['total_present'] ?? 0) + (int)($attendanceTotals[$sid]['total_late'] ?? 0);
            $pdf->Cell($absentColW, 5, (string)$absent, 1, 0, 'C');
            $pdf->Cell($presentColW, 5, (string)$present, 1, 0, 'C');
            $pdf->Ln();
            $num++;
        }
    };

    $writeTotalRow = function (string $label, array $students, string $fontColor = '255,0,0') use ($pdf, $noColW, $nameColW, $dayColW, $absentColW, $presentColW, $schoolDays, $attendanceMap, $attendanceTotals) {
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->Cell($noColW, 5, (string)(count($students) + 1), 1, 0, 'C');
        $pdf->SetTextColor(255, 0, 0);
        $pdf->Cell($nameColW, 5, $label, 1, 0, 'L');
        $pdf->SetTextColor(0, 0, 0);

        foreach ($schoolDays as $sd) {
            $presentCount = 0;
            foreach ($students as $s) {
                $sid = (int)$s['student_id'];
                $st = $attendanceMap[$sid][$sd] ?? '';
                if ($st === 'Present' || $st === 'Late') $presentCount++;
            }
            $pdf->Cell($dayColW, 5, (string)$presentCount, 1, 0, 'C');
        }

        $totalAbsent = 0;
        $totalPresent = 0;
        foreach ($students as $s) {
            $sid = (int)$s['student_id'];
            $totalAbsent += (int)($attendanceTotals[$sid]['total_absent'] ?? 0);
            $totalPresent += (int)($attendanceTotals[$sid]['total_present'] ?? 0) + (int)($attendanceTotals[$sid]['total_late'] ?? 0);
        }
        $pdf->Cell($absentColW, 5, (string)$totalAbsent, 1, 0, 'C');
        $pdf->Cell($presentColW, 5, (string)$totalPresent, 1, 0, 'C');
        $pdf->Ln();
        $pdf->SetFont('helvetica', '', 7);
    };

    $writeStudentRowsPdf($males);
    $writeTotalRow('<=== MALE | TOTAL Per Day===>', $males);

    $writeStudentRowsPdf($females);
    $writeTotalRow('<=== FEMALE | TOTAL Per Day===>', $females);

    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->Cell($noColW, 5, (string)count($allStudents), 1, 0, 'C');
    $pdf->Cell($nameColW, 5, 'Combined TOTAL Per Day', 1, 0, 'L');

    foreach ($schoolDays as $sd) {
        $presentCount = 0;
        foreach ($allStudents as $s) {
            $sid = (int)$s['student_id'];
            $st = $attendanceMap[$sid][$sd] ?? '';
            if ($st === 'Present' || $st === 'Late') $presentCount++;
        }
        $pdf->Cell($dayColW, 5, (string)$presentCount, 1, 0, 'C');
    }

    $totalAbsent = 0;
    $totalPresent = 0;
    foreach ($allStudents as $s) {
        $sid = (int)$s['student_id'];
        $totalAbsent += (int)($attendanceTotals[$sid]['total_absent'] ?? 0);
        $totalPresent += (int)($attendanceTotals[$sid]['total_present'] ?? 0) + (int)($attendanceTotals[$sid]['total_late'] ?? 0);
    }
    $pdf->Cell($absentColW, 5, (string)$totalAbsent, 1, 0, 'C');
    $pdf->Cell($presentColW, 5, (string)$totalPresent, 1, 0, 'C');
    $pdf->Ln(10);

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell($pageW, 5, 'CODES FOR CHECKING ATTENDANCE', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 7);
    $pdf->Cell($pageW, 4, '(blank) - Present; (A) - Absent; (L) - Late; (S) - Suspended / No Class', 0, 1, 'L');

    $pdf->Ln(5);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->Cell($pageW, 4, 'Month: ' . $reportMonth, 0, 1, 'L');

    $fileName = 'SF2_' . date('Y') . '_' . str_replace(' ', '_', $gradeLevelText ?: 'All') . '_' . $sectionLabel . '.pdf';
    $pdf->Output($fileName, 'D');
    exit;
}
