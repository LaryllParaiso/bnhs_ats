<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

$title = 'Bulk QR';

$isAdmin = is_admin();

$teacherId = (int)$_SESSION['teacher_id'];
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;

$requestMethod = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');

$scheduleId = (int)(
    $requestMethod === 'POST'
        ? (int)($_POST['schedule_id'] ?? 0)
        : (int)($_GET['schedule_id'] ?? 0)
);

$q = trim((string)(
    $requestMethod === 'POST'
        ? (string)($_POST['q'] ?? ($_GET['q'] ?? ''))
        : (string)($_GET['q'] ?? '')
));
$section = trim((string)(
    $requestMethod === 'POST'
        ? (string)($_POST['section'] ?? ($_GET['section'] ?? ''))
        : (string)($_GET['section'] ?? '')
));

$pdo = db();

if ($isAdmin) {
    $stmt = $pdo->prepare(
        'SELECT s.schedule_id, s.subject_name, s.grade_level, s.section, s.day_of_week, s.start_time, s.end_time, s.school_year, s.status,
                t.first_name AS teacher_first_name, t.last_name AS teacher_last_name, t.suffix AS teacher_suffix
         FROM schedules s
         JOIN teachers t ON t.teacher_id = s.teacher_id
         WHERE s.status != "Archived"
         ORDER BY FIELD(s.day_of_week, "Monday","Tuesday","Wednesday","Thursday","Friday"), s.start_time'
    );
    $stmt->execute();
    $schedules = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare(
        'SELECT schedule_id, subject_name, grade_level, section, day_of_week, start_time, end_time, school_year, status
         FROM schedules
         WHERE teacher_id = :teacher_id AND status != "Archived"
         ORDER BY FIELD(day_of_week, "Monday","Tuesday","Wednesday","Thursday","Friday"), start_time'
    );
    $stmt->execute([':teacher_id' => $teacherId]);
    $schedules = $stmt->fetchAll();
}

$selectedSchedule = null;
$students = [];
$studentPg = null;
$sections = [];

if ($scheduleId > 0) {
    if ($isAdmin) {
        $stmt = $pdo->prepare(
            'SELECT s.schedule_id, s.subject_name, s.grade_level, s.section, s.day_of_week, s.start_time, s.end_time, s.school_year, s.status,
                    t.first_name AS teacher_first_name, t.last_name AS teacher_last_name, t.suffix AS teacher_suffix
             FROM schedules s
             JOIN teachers t ON t.teacher_id = s.teacher_id
             WHERE s.schedule_id = :id AND s.status != "Archived"
             LIMIT 1'
        );
        $stmt->execute([':id' => $scheduleId]);
        $selectedSchedule = $stmt->fetch();
    } else {
        $stmt = $pdo->prepare(
            'SELECT schedule_id, subject_name, grade_level, section, day_of_week, start_time, end_time, school_year, status
             FROM schedules
             WHERE schedule_id = :id AND teacher_id = :teacher_id AND status != "Archived"
             LIMIT 1'
        );
        $stmt->execute([':id' => $scheduleId, ':teacher_id' => $teacherId]);
        $selectedSchedule = $stmt->fetch();
    }

    if ($selectedSchedule) {
        if ($requestMethod === 'POST' && isset($_POST['download'])) {
            $download = (string)($_POST['download'] ?? '');
            $mode = $download;
            $format = 'pdf';

            if (str_contains($download, '_')) {
                $parts = explode('_', $download, 2);
                $mode = (string)($parts[0] ?? '');
                $format = (string)($parts[1] ?? 'pdf');
            }

            if (!in_array($mode, ['selected', 'all'], true)) {
                redirect('bulk_qr.php?schedule_id=' . (int)$scheduleId);
            }

            if (!in_array($format, ['pdf', 'png'], true)) {
                $format = 'pdf';
            }

            $studentIds = $_POST['student_ids'] ?? [];
            if (!is_array($studentIds)) {
                $studentIds = [];
            }
            $studentIds = array_values(array_unique(array_filter(array_map('intval', $studentIds), function (int $v): bool {
                return $v > 0;
            })));

            $jsonIdsRaw = trim((string)($_POST['selected_ids_json'] ?? ''));
            if ($jsonIdsRaw !== '') {
                $decoded = json_decode($jsonIdsRaw, true);
                if (is_array($decoded)) {
                    $jsonIds = array_values(array_unique(array_filter(array_map('intval', $decoded), function (int $v): bool {
                        return $v > 0;
                    })));
                    if ($jsonIds) {
                        $studentIds = array_values(array_unique(array_merge($studentIds, $jsonIds)));
                    }
                }
            }

            if ($mode === 'selected' && !$studentIds) {
                $err = ['schedule_id' => (int)$scheduleId, 'error' => 'select'];
                if ($q !== '') {
                    $err['q'] = $q;
                }
                if ($section !== '') {
                    $err['section'] = $section;
                }
                redirect('bulk_qr.php?' . http_build_query($err));
            }

            if ($mode === 'all') {
                $downloadWhere = [
                    'ss.schedule_id = :schedule_id',
                    'ss.status = "Active"',
                    'st.status = "Active"',
                ];
                $downloadParams = [':schedule_id' => $scheduleId];

                if ($q !== '') {
                    $downloadWhere[] = '(st.lrn LIKE :q OR st.first_name LIKE :q OR st.last_name LIKE :q)';
                    $downloadParams[':q'] = '%' . $q . '%';
                }

                if ($section !== '') {
                    $downloadWhere[] = 'st.section = :section';
                    $downloadParams[':section'] = $section;
                }

                $stmt = $pdo->prepare(
                    'SELECT st.*
                     FROM student_schedules ss
                     JOIN students st ON st.student_id = ss.student_id
                     WHERE ' . implode(' AND ', $downloadWhere) . '
                     ORDER BY st.last_name, st.first_name, st.lrn'
                );
                $stmt->execute($downloadParams);
            } else {
                $ph = [];
                $params = [':schedule_id' => $scheduleId];
                foreach ($studentIds as $i => $sid) {
                    $k = ':sid' . $i;
                    $ph[] = $k;
                    $params[$k] = $sid;
                }

                $stmt = $pdo->prepare(
                    'SELECT st.*
                     FROM student_schedules ss
                     JOIN students st ON st.student_id = ss.student_id
                     WHERE ss.schedule_id = :schedule_id
                       AND ss.status = "Active"
                       AND st.status = "Active"
                       AND st.student_id IN (' . implode(',', $ph) . ')
                     ORDER BY st.last_name, st.first_name, st.lrn'
                );
                $stmt->execute($params);
            }

            $students = $stmt->fetchAll();

            $autoload = __DIR__ . '/vendor/autoload.php';
            if (!is_file($autoload)) {
                header('Content-Type: text/plain; charset=utf-8');
                echo 'PDF export dependencies are not installed. Run composer install first.';
                exit;
            }
            require_once $autoload;

            if ($format === 'png') {
                if (!extension_loaded('gd')) {
                    header('Content-Type: text/plain; charset=utf-8');
                    echo 'PNG export requires the PHP gd extension.';
                    exit;
                }

                $barcodeFile = __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php';
                if (is_file($barcodeFile)) {
                    require_once $barcodeFile;
                }

                if (!class_exists('TCPDF2DBarcode')) {
                    header('Content-Type: text/plain; charset=utf-8');
                    echo 'PNG export is not available (TCPDF2DBarcode missing).';
                    exit;
                }

                if (!class_exists('ZipStream\\ZipStream')) {
                    header('Content-Type: text/plain; charset=utf-8');
                    echo 'ZIP export dependencies are not installed. Run composer install first.';
                    exit;
                }

                $safe = function (string $s): string {
                    $s = preg_replace('/[^A-Za-z0-9 _.-]+/', '', $s) ?? '';
                    $s = trim($s);
                    return $s === '' ? 'student' : $s;
                };

                $makeCardPng = function (string $qrPngData, string $studentName, string $studentLrn, string $studentGs): string {
                    $qrIm = @imagecreatefromstring($qrPngData);
                    if (!$qrIm) {
                        return $qrPngData;
                    }

                    $CARD_W = 1200;
                    $CARD_H = 1500;
                    $outerPad = 70;
                    $qrBoxPad = 18;
                    $qrBoxSize = $CARD_W - ($outerPad * 2);
                    $qrBoxX = $outerPad;
                    $qrBoxY = $outerPad;
                    $qrSize = $qrBoxSize - ($qrBoxPad * 2);

                    $out = imagecreatetruecolor($CARD_W, $CARD_H);
                    imagealphablending($out, true);
                    imagesavealpha($out, false);

                    $white = imagecolorallocate($out, 255, 255, 255);
                    imagefilledrectangle($out, 0, 0, $CARD_W, $CARD_H, $white);

                    $border = imagecolorallocate($out, 217, 217, 217);
                    $box = imagecolorallocate($out, 230, 230, 230);
                    $textDark = imagecolorallocate($out, 17, 17, 17);
                    $textMuted = imagecolorallocate($out, 44, 62, 80);

                    imagesetthickness($out, 2);
                    imagerectangle($out, 10, 10, $CARD_W - 10, $CARD_H - 10, $border);
                    imagerectangle($out, $qrBoxX, $qrBoxY, $qrBoxX + $qrBoxSize, $qrBoxY + $qrBoxSize, $box);

                    $qrW = imagesx($qrIm);
                    $qrH = imagesy($qrIm);
                    imagecopyresized(
                        $out,
                        $qrIm,
                        $qrBoxX + $qrBoxPad,
                        $qrBoxY + $qrBoxPad,
                        0,
                        0,
                        $qrSize,
                        $qrSize,
                        $qrW,
                        $qrH
                    );

                    $fontRegular = null;
                    $fontBold = null;
                    $regularCandidates = [
                        __DIR__ . '/assets/fonts/arial.ttf',
                        __DIR__ . '/assets/fonts/Arial.ttf',
                        'C:\\Windows\\Fonts\\arial.ttf',
                        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                    ];
                    foreach ($regularCandidates as $f) {
                        if (is_string($f) && $f !== '' && is_file($f)) {
                            $fontRegular = $f;
                            break;
                        }
                    }

                    $boldCandidates = [
                        __DIR__ . '/assets/fonts/arialbd.ttf',
                        __DIR__ . '/assets/fonts/Arial Bold.ttf',
                        'C:\\Windows\\Fonts\\arialbd.ttf',
                        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                    ];
                    foreach ($boldCandidates as $f) {
                        if (is_string($f) && $f !== '' && is_file($f)) {
                            $fontBold = $f;
                            break;
                        }
                    }

                    $drawCenteredTtf = function ($img, string $text, int $topY, int $size, $color, ?string $font): void {
                        $text = trim($text);
                        if ($text === '' || !$font || !function_exists('imagettftext') || !function_exists('imagettfbbox')) {
                            return;
                        }

                        $bbox = imagettfbbox($size, 0, $font, $text);
                        if (!is_array($bbox)) {
                            return;
                        }

                        $minX = min($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
                        $maxX = max($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
                        $w = (int)($maxX - $minX);
                        $x = (int)floor((imagesx($img) - $w) / 2);
                        $y = $topY + $size;
                        imagettftext($img, $size, 0, $x, $y, $color, $font, $text);
                    };

                    $drawCenteredBuiltin = function ($img, string $text, int $topY, int $font, $color): void {
                        $text = trim($text);
                        if ($text === '') {
                            return;
                        }
                        $w = imagefontwidth($font) * strlen($text);
                        $x = (int)max(0, floor((imagesx($img) - $w) / 2));
                        imagestring($img, $font, $x, $topY, $text, $color);
                    };

                    $textTop = $qrBoxY + $qrBoxSize + 70;
                    $name = trim($studentName);
                    $lrnLine = 'LRN: ' . trim($studentLrn);
                    $gsLine = trim($studentGs);

                    if ($fontRegular || $fontBold) {
                        $drawCenteredTtf($out, $name, $textTop, 44, $textDark, $fontBold ?: $fontRegular);
                        $drawCenteredTtf($out, $lrnLine, $textTop + 62, 28, $textMuted, $fontRegular ?: $fontBold);
                        $drawCenteredTtf($out, $gsLine, $textTop + 104, 28, $textMuted, $fontRegular ?: $fontBold);
                    } else {
                        $drawCenteredBuiltin($out, $name, $textTop + 10, 5, $textDark);
                        $drawCenteredBuiltin($out, $lrnLine, $textTop + 40, 4, $textMuted);
                        $drawCenteredBuiltin($out, $gsLine, $textTop + 60, 4, $textMuted);
                    }

                    ob_start();
                    imagepng($out);
                    $final = (string)ob_get_clean();
                    imagedestroy($qrIm);
                    imagedestroy($out);
                    return $final;
                };

                $zipName = 'bulk_qr_schedule_' . (int)$scheduleId . '_' . date('Ymd_His') . '.zip';
                $zip = new \ZipStream\ZipStream(outputName: $zipName, sendHttpHeaders: true);

                foreach ($students as $st) {
                    $suffix = trim((string)($st['suffix'] ?? ''));
                    $fullName = trim((string)$st['first_name'] . ' ' . (string)$st['last_name']);
                    if ($suffix !== '') {
                        $fullName .= ', ' . $suffix;
                    }

                    $payload = [
                        'lrn' => (string)$st['lrn'],
                        'name' => $fullName,
                        'grade' => (int)$st['grade_level'],
                        'section' => (string)$st['section'],
                        'token' => (string)$st['qr_token'],
                    ];
                    $qrText = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                    $barcode = new TCPDF2DBarcode((string)$qrText, 'QRCODE,L');
                    $png = $barcode->getBarcodePngData(8, 8, [0, 0, 0]);
                    if (!is_string($png) || $png === '') {
                        continue;
                    }

                    $png = $makeCardPng(
                        $png,
                        (string)$fullName,
                        (string)($st['lrn'] ?? ''),
                        (string)($st['grade_level'] ?? '') . '-' . (string)($st['section'] ?? '')
                    );

                    $fn = 'QR_' . $safe((string)$st['lrn']) . '_' . $safe($fullName) . '.png';
                    $zip->addFile(fileName: $fn, data: $png);
                }

                $zip->finish();
                exit;
            }

            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetCreator('BNH ATS');
            $pdf->SetAuthor('BNH ATS');
            $pdf->SetTitle('Bulk QR');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(10, 10, 10);
            $pdf->SetAutoPageBreak(true, 10);
            $pdf->AddPage();

            $style = [
                'border' => 0,
                'vpadding' => 0,
                'hpadding' => 0,
                'fgcolor' => [0, 0, 0],
                'bgcolor' => false,
                'module_width' => 1,
                'module_height' => 1,
            ];

            $cols = 2;
            $rowsPerPage = 3;
            $pageW = 210;
            $pageH = 297;
            $mx = 10;
            $my = 10;
            $innerW = $pageW - ($mx * 2);
            $innerH = $pageH - ($my * 2);
            $cardW = $innerW / $cols;
            $cardH = $innerH / $rowsPerPage;

            $h = function (string $v): string {
                return trim($v);
            };

            foreach ($students as $i => $st) {
                $idxOnPage = $i % ($cols * $rowsPerPage);
                if ($i > 0 && $idxOnPage === 0) {
                    $pdf->AddPage();
                }

                $col = $idxOnPage % $cols;
                $row = (int)floor($idxOnPage / $cols);

                $x = $mx + ($col * $cardW);
                $y = $my + ($row * $cardH);

                $pdf->Rect($x + 1, $y + 1, $cardW - 2, $cardH - 2);

                $suffix = trim((string)($st['suffix'] ?? ''));
                $fullName = trim((string)$st['first_name'] . ' ' . (string)$st['last_name']);
                if ($suffix !== '') {
                    $fullName .= ', ' . $suffix;
                }

                $payload = [
                    'lrn' => (string)$st['lrn'],
                    'name' => $fullName,
                    'grade' => (int)$st['grade_level'],
                    'section' => (string)$st['section'],
                    'token' => (string)$st['qr_token'],
                ];
                $qrText = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                $qrSize = min(60, $cardW - 12);
                $qrX = $x + (($cardW - $qrSize) / 2);
                $qrY = $y + 6;

                $pdf->write2DBarcode((string)$qrText, 'QRCODE,L', $qrX, $qrY, $qrSize, $qrSize, $style, 'N');

                $textY = $qrY + $qrSize + 4;
                $pdf->SetXY($x + 3, $textY);
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->MultiCell($cardW - 6, 5, $h($fullName), 0, 'C', false, 1);
                $pdf->SetFont('helvetica', '', 9);
                $pdf->SetX($x + 3);
                $pdf->MultiCell($cardW - 6, 4, 'LRN: ' . $h((string)$st['lrn']), 0, 'C', false, 1);
                $pdf->SetX($x + 3);
                $pdf->MultiCell($cardW - 6, 4, 'G' . $h((string)$st['grade_level']) . '-' . $h((string)$st['section']), 0, 'C', false, 1);
            }

            $file = 'bulk_qr_schedule_' . (int)$scheduleId . '_' . date('Ymd_His') . '.pdf';
            $pdf->Output($file, 'D');
            exit;
        }

        $secStmt = $pdo->prepare(
            'SELECT DISTINCT st.section
             FROM student_schedules ss
             JOIN students st ON st.student_id = ss.student_id
             WHERE ss.schedule_id = :schedule_id
               AND ss.status = "Active"
               AND st.status = "Active"
             ORDER BY st.section'
        );
        $secStmt->execute([':schedule_id' => $scheduleId]);
        $sections = array_values(array_filter(array_map('strval', array_column($secStmt->fetchAll(), 'section')), static function (string $v): bool {
            return $v !== '';
        }));

        $studentWhere = [
            'ss.schedule_id = :schedule_id',
            'ss.status = "Active"',
            'st.status = "Active"',
        ];
        $studentParams = [':schedule_id' => $scheduleId];

        if ($q !== '') {
            $studentWhere[] = '(st.lrn LIKE :q1 OR st.first_name LIKE :q2 OR st.last_name LIKE :q3)';
            $qLike = '%' . $q . '%';
            $studentParams[':q1'] = $qLike;
            $studentParams[':q2'] = $qLike;
            $studentParams[':q3'] = $qLike;
        }

        if ($section !== '') {
            $studentWhere[] = 'st.section = :section';
            $studentParams[':section'] = $section;
        }

        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) AS cnt
             FROM student_schedules ss
             JOIN students st ON st.student_id = ss.student_id
             WHERE ' . implode(' AND ', $studentWhere)
        );
        $countStmt->execute($studentParams);
        $studentTotal = (int)(($countStmt->fetch()['cnt'] ?? 0));

        $studentPg = paginate($studentTotal, $page, $perPage);
        $page = (int)$studentPg['page'];
        $limit = (int)$studentPg['per_page'];
        $offset = (int)$studentPg['offset'];

        $stmt = $pdo->prepare(
            'SELECT st.*
             FROM student_schedules ss
             JOIN students st ON st.student_id = ss.student_id
             WHERE ' . implode(' AND ', $studentWhere) . '
             ORDER BY st.last_name, st.first_name, st.lrn
             LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $stmt->execute($studentParams);
        $students = $stmt->fetchAll();
    }
}

require __DIR__ . '/partials/layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Bulk QR</h1>
  <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">Print</button>
</div>

<form method="get" action="<?= h(url('bulk_qr.php')) ?>" class="row g-2 mb-3">
  <div class="col-md-5">
    <select class="form-select" name="schedule_id" required>
      <option value="">Select schedule</option>
      <?php foreach ($schedules as $s): ?>
        <?php
          $sid = (int)$s['schedule_id'];
          $label = (string)$s['subject_name'];
          if ($isAdmin) {
              $tSuffix = trim((string)($s['teacher_suffix'] ?? ''));
              $tName = trim((string)($s['teacher_last_name'] ?? '') . ', ' . (string)($s['teacher_first_name'] ?? ''));
              if ($tSuffix !== '') {
                  $tName .= ' ' . $tSuffix;
              }
              $label .= ' | ' . $tName;
          }
          $label .= ' | G' . (string)$s['grade_level'] . '-' . (string)$s['section'] . ' | ' . (string)$s['day_of_week'] . ' ' . (string)$s['start_time'] . '-' . (string)$s['end_time'];
        ?>
        <option value="<?= $sid ?>" <?= $sid === $scheduleId ? 'selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <input class="form-control" name="q" placeholder="Search LRN / Name" value="<?= h($q) ?>" <?= $scheduleId > 0 ? '' : 'disabled' ?>>
  </div>
  <div class="col-md-2">
    <select class="form-select" name="section" <?= $scheduleId > 0 ? '' : 'disabled' ?>>
      <option value="" <?= $section === '' ? 'selected' : '' ?>>All Sections</option>
      <?php foreach ($sections as $sec): ?>
        <option value="<?= h($sec) ?>" <?= $section === $sec ? 'selected' : '' ?>><?= h($sec) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2 d-grid d-md-block">
    <button class="btn btn-primary" type="submit">Apply</button>
    <?php if ($scheduleId > 0 && ($q !== '' || $section !== '')): ?>
      <a class="btn btn-link" href="<?= h(url('bulk_qr.php?schedule_id=' . (int)$scheduleId)) ?>">Reset</a>
    <?php endif; ?>
  </div>
</form>

<?php if ($scheduleId > 0 && !$selectedSchedule): ?>
  <div class="alert alert-danger">Schedule not found.</div>
<?php elseif ($selectedSchedule && !$students): ?>
  <div class="alert alert-info" data-no-toast>No enrolled active students for this schedule.</div>
<?php endif; ?>

<?php if (isset($_GET['error']) && (string)$_GET['error'] === 'select'): ?>
  <div class="alert alert-warning" data-no-toast>Please select at least one student.</div>
<?php endif; ?>

<?php if ($selectedSchedule && $students): ?>
  <form id="bulkQrDownloadForm" method="post" action="<?= h(url('bulk_qr.php')) ?>" data-no-loading>
    <input type="hidden" name="schedule_id" value="<?= (int)$scheduleId ?>">
    <input type="hidden" name="q" value="<?= h($q) ?>">
    <input type="hidden" name="section" value="<?= h($section) ?>">
    <input type="hidden" name="selected_ids_json" id="bulkQrSelectedIdsJson" value="">

    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="selectAllPage">
          <label class="form-check-label" for="selectAllPage">Select all (this page)</label>
        </div>
        <div class="text-muted small" id="bulkQrSelectedCount"></div>
        <button class="btn btn-link btn-sm p-0" type="button" id="bulkQrClearSelection">Clear selection</button>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-outline-primary btn-sm" type="submit" name="download" value="selected_pdf" id="btnDownloadSelectedPdf">Selected PDF</button>
        <button class="btn btn-primary btn-sm" type="submit" name="download" value="all_pdf">All PDF</button>
        <button class="btn btn-outline-secondary btn-sm" type="submit" name="download" value="selected_png" id="btnDownloadSelectedPng">Selected PNG (ZIP)</button>
        <button class="btn btn-outline-secondary btn-sm" type="submit" name="download" value="all_png">All PNG (ZIP)</button>
      </div>
    </div>

    <div class="row g-3" id="qrGrid">
      <?php foreach ($students as $idx => $st): ?>
      <?php
        $suffix = trim((string)($st['suffix'] ?? ''));
        $fullName = trim((string)$st['first_name'] . ' ' . (string)$st['last_name']);
        if ($suffix !== '') {
            $fullName .= ', ' . $suffix;
        }

        $payload = [
            'lrn' => (string)$st['lrn'],
            'name' => $fullName,
            'grade' => (int)$st['grade_level'],
            'section' => (string)$st['section'],
            'token' => (string)$st['qr_token'],
        ];
        $qrText = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $qrId = 'qr_' . (string)$idx;
      ?>

      <div class="col-md-4">
        <div class="card">
          <div class="card-body text-center">
            <div class="d-flex justify-content-start">
              <div class="form-check">
                <input class="form-check-input js-student-select" type="checkbox" name="student_ids[]" value="<?= (int)$st['student_id'] ?>">
              </div>
            </div>
            <div id="<?= h($qrId) ?>" class="d-inline-block p-2 bg-white border rounded" data-qr-text="<?= h($qrText) ?>"></div>
            <div class="mt-2 fw-semibold"><?= h($fullName) ?></div>
            <div class="text-muted small">LRN: <?= h((string)$st['lrn']) ?></div>
            <div class="text-muted small">G<?= h((string)$st['grade_level']) ?>-<?= h((string)$st['section']) ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3">
      <div class="text-muted small">
        <?php if ($studentPg): ?>
          Showing <?= (int)$studentPg['from'] ?>-<?= (int)$studentPg['to'] ?> of <?= (int)$studentPg['total'] ?>
        <?php endif; ?>
      </div>
      <?php if ($studentPg): ?>
        <?= pagination_html('bulk_qr.php', $_GET, (int)$studentPg['page'], (int)$studentPg['per_page'], (int)$studentPg['total']) ?>
      <?php endif; ?>
    </div>
  </form>

  <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
  <script>
    (function () {
      const scheduleId = <?= json_encode((int)$scheduleId) ?>;
      const storageKey = 'bnhs_bulkqr_selected_' + String(scheduleId || 0);
      const form = document.getElementById('bulkQrDownloadForm');
      const selectedJson = document.getElementById('bulkQrSelectedIdsJson');
      const selectedCountEl = document.getElementById('bulkQrSelectedCount');
      const clearBtn = document.getElementById('bulkQrClearSelection');

      const selectAll = document.getElementById('selectAllPage');
      const boxes = Array.prototype.slice.call(document.querySelectorAll('.js-student-select'));
      const btnSelectedPdf = document.getElementById('btnDownloadSelectedPdf');
      const btnSelectedPng = document.getElementById('btnDownloadSelectedPng');

      let lastDownload = '';
      document.querySelectorAll('button[name="download"]').forEach(function (b) {
        b.addEventListener('click', function () {
          lastDownload = String(b.value || '');
        });
      });

      function loadSelectedSet() {
        try {
          const raw = window.localStorage ? window.localStorage.getItem(storageKey) : null;
          if (!raw) return new Set();
          const arr = JSON.parse(raw);
          if (!Array.isArray(arr)) return new Set();
          return new Set(arr.map(function (v) { return String(v); }).filter(Boolean));
        } catch (e) {
          return new Set();
        }
      }

      function saveSelectedSet(set) {
        try {
          if (!window.localStorage) return;
          window.localStorage.setItem(storageKey, JSON.stringify(Array.from(set)));
        } catch (e) {
        }
      }

      function selectedCount(set) {
        return set.size || 0;
      }

      let selectedSet = loadSelectedSet();

      function updateSelectedEnabled() {
        const any = selectedCount(selectedSet) > 0;
        if (btnSelectedPdf) btnSelectedPdf.disabled = !any;
        if (btnSelectedPng) btnSelectedPng.disabled = !any;

        if (selectedCountEl) {
          selectedCountEl.textContent = 'Selected: ' + String(selectedCount(selectedSet));
        }
      }

      function syncCheckboxesFromSet() {
        boxes.forEach(function (b) {
          const id = String(b.value || '');
          b.checked = selectedSet.has(id);
        });

        if (selectAll) {
          const all = boxes.length > 0 && boxes.every(function (x) { return x.checked; });
          selectAll.checked = all;
        }
      }

      if (selectAll) {
        selectAll.addEventListener('change', function () {
          boxes.forEach(function (b) {
            const id = String(b.value || '');
            b.checked = selectAll.checked;
            if (!id) return;
            if (b.checked) {
              selectedSet.add(id);
            } else {
              selectedSet.delete(id);
            }
          });
          saveSelectedSet(selectedSet);
          updateSelectedEnabled();
        });
      }

      boxes.forEach(function (b) {
        b.addEventListener('change', function () {
          const id = String(b.value || '');
          if (id) {
            if (b.checked) {
              selectedSet.add(id);
            } else {
              selectedSet.delete(id);
            }
            saveSelectedSet(selectedSet);
          }

          if (selectAll) {
            const all = boxes.length > 0 && boxes.every(function (x) { return x.checked; });
            selectAll.checked = all;
          }

          updateSelectedEnabled();
        });
      });

      syncCheckboxesFromSet();
      updateSelectedEnabled();

      if (clearBtn) {
        clearBtn.addEventListener('click', function () {
          selectedSet = new Set();
          saveSelectedSet(selectedSet);
          syncCheckboxesFromSet();
          updateSelectedEnabled();
        });
      }

      if (form) {
        form.addEventListener('submit', function () {
          if (!selectedJson) return;
          if (String(lastDownload || '').startsWith('selected_')) {
            selectedJson.value = JSON.stringify(Array.from(selectedSet));
          } else {
            selectedJson.value = '';
          }
        });
      }

      document.querySelectorAll('[data-qr-text]').forEach(function (el) {
        const text = el.getAttribute('data-qr-text') || '';
        el.innerHTML = '';
        new QRCode(el, {
          text: text,
          width: 300,
          height: 300,
          correctLevel: QRCode.CorrectLevel.L
        });
      });
    })();
  </script>
<?php endif; ?>

<?php require __DIR__ . '/partials/layout_bottom.php';
