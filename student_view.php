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
           AND EXISTS (
             SELECT 1
             FROM student_schedules ss
             JOIN schedules sch ON sch.schedule_id = ss.schedule_id
             WHERE ss.student_id = students.student_id
               AND ss.status = "Active"
               AND sch.teacher_id = :teacher_id
               AND sch.status != "Archived"
           )'
    );
    $stmt->execute([':id' => $id, ':teacher_id' => $teacherId]);
}
$student = $stmt->fetch();

if (!$student) {
    redirect('students.php');
}

$stmt = $pdo->prepare(
    'SELECT s.subject_name, s.day_of_week, s.start_time, s.end_time, s.grade_level, s.section, s.status AS schedule_status,
            t.first_name, t.last_name, t.suffix AS teacher_suffix,
            ss.status AS enrollment_status
     FROM student_schedules ss
     JOIN schedules s ON s.schedule_id = ss.schedule_id
     JOIN teachers t ON t.teacher_id = s.teacher_id
     WHERE ss.student_id = :student_id
       AND s.teacher_id = :teacher_id
       AND s.status != "Archived"
     ORDER BY FIELD(s.day_of_week, "Monday","Tuesday","Wednesday","Thursday","Friday"), s.start_time'
);
$stmt->execute([':student_id' => $id, ':teacher_id' => $teacherId]);
$enrollments = $stmt->fetchAll();

if ($isAdmin) {
    $stmt = $pdo->prepare(
        'SELECT schedule_id, subject_name, day_of_week, start_time, end_time, grade_level, section
         FROM schedules
         WHERE status != "Archived"
           AND grade_level = :grade_level
           AND section = :section
         ORDER BY subject_name, FIELD(day_of_week, "Monday","Tuesday","Wednesday","Thursday","Friday"), start_time'
    );
    $stmt->execute([':grade_level' => (int)$student['grade_level'], ':section' => (string)$student['section']]);
    $availableSchedules = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare(
        'SELECT schedule_id, subject_name, day_of_week, start_time, end_time, grade_level, section
         FROM schedules
         WHERE teacher_id = :teacher_id
           AND status != "Archived"
           AND grade_level = :grade_level
           AND section = :section
         ORDER BY subject_name, FIELD(day_of_week, "Monday","Tuesday","Wednesday","Thursday","Friday"), start_time'
    );
    $stmt->execute([':teacher_id' => $teacherId, ':grade_level' => (int)$student['grade_level'], ':section' => (string)$student['section']]);
    $availableSchedules = $stmt->fetchAll();
}

$suffix = trim((string)($student['suffix'] ?? ''));
$fullName = trim((string)$student['first_name'] . ' ' . (string)$student['last_name']);
if ($suffix !== '') {
    $fullName .= ', ' . $suffix;
}

$title = 'Student Profile';
require __DIR__ . '/partials/layout_top.php';
?>

<div class="bnhs-page-header">
  <h1 class="bnhs-page-title">Student Profile</h1>
  <div class="bnhs-page-actions">
    <a class="btn btn-outline-secondary btn-sm" href="<?= h(url('students.php')) ?>">Back</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= h(url('student_history.php?id=' . (int)$student['student_id'])) ?>">History</a>
    <a class="btn btn-primary btn-sm" href="<?= h(url('student_form.php?id=' . (int)$student['student_id'])) ?>">Edit</a>
  </div>
</div>

<?php if (isset($_GET['saved'])): ?>
  <div class="alert alert-success">Student saved.</div>
<?php endif; ?>

<?php if (isset($_GET['regen'])): ?>
  <div class="alert alert-info">QR token regenerated. Old QR codes are now invalid.</div>
<?php endif; ?>

<?php if (isset($_GET['enrolled'])): ?>
  <div class="alert alert-success">Student enrolled.</div>
<?php endif; ?>

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
      <div class="col-md-3">
        <div class="text-muted">Sex</div>
        <div class="fw-semibold"><?= h((string)$student['sex']) ?></div>
      </div>
      <div class="col-md-3">
        <div class="text-muted">Status</div>
        <div class="fw-semibold"><?= h((string)$student['status']) ?></div>
      </div>
      <div class="col-md-6">
        <div class="text-muted">QR Token</div>
        <div class="fw-semibold"><code><?= h((string)$student['qr_token']) ?></code></div>
      </div>
    </div>
  </div>
</div>

<?php
  $qrPayload = [
      'lrn' => (string)$student['lrn'],
      'name' => $fullName,
      'grade' => (int)$student['grade_level'],
      'section' => (string)$student['section'],
      'token' => (string)$student['qr_token'],
  ];
  $qrText = json_encode($qrPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h2 class="h6 mb-0">Student QR Code</h2>
      <form method="post" action="<?= h(url('student_qr_regenerate.php')) ?>" data-confirm="Regenerate this student's QR token? Old QR codes will stop working." data-confirm-title="Regenerate QR" data-confirm-ok="Regenerate" data-confirm-cancel="Cancel" data-confirm-icon="warning">
        <input type="hidden" name="student_id" value="<?= (int)$student['student_id'] ?>">
        <button class="btn btn-sm btn-outline-danger" type="submit">Regenerate QR</button>
      </form>
    </div>

    <div class="row align-items-center">
      <div class="col-md-4 text-center">
        <div id="qrcode" class="d-inline-block p-3 bg-white border rounded"></div>
        <div id="qrcodeDownload" style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;"></div>
        <div class="mt-3 d-flex justify-content-center gap-2">
          <button id="btnDownloadQr" type="button" class="btn btn-outline-primary btn-sm">Download PNG</button>
          <button id="btnPrintQr" type="button" class="btn btn-outline-secondary btn-sm">Print</button>
        </div>
      </div>
      <div class="col-md-8">
        <div class="mb-2"><span class="text-muted">Name:</span> <span class="fw-semibold"><?= h($fullName) ?></span></div>
        <div class="mb-2"><span class="text-muted">LRN:</span> <span class="fw-semibold"><?= h((string)$student['lrn']) ?></span></div>
        <div class="mb-2"><span class="text-muted">Grade/Section:</span> <span class="fw-semibold"><?= h((string)$student['grade_level']) ?> - <?= h((string)$student['section']) ?></span></div>
        <div class="text-muted small">Use this QR for scanning attendance. If you regenerate the QR token, old QR codes will become invalid.</div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
  (function () {
    const qrText = <?= json_encode($qrText ?? '') ?>;
    const studentName = <?= json_encode($fullName) ?>;
    const studentLrn = <?= json_encode((string)$student['lrn']) ?>;
    const studentGs = <?= json_encode((string)$student['grade_level'] . '-' . (string)$student['section']) ?>;
    const el = document.getElementById('qrcode');
    const dlEl = document.getElementById('qrcodeDownload');
    const DL_QR_SIZE = 1024;
    const CARD_W = 1200;
    const CARD_H = 1500;

    if (!el || !qrText) return;

    el.innerHTML = '';
    new QRCode(el, {
      text: qrText,
      width: 240,
      height: 240,
      correctLevel: QRCode.CorrectLevel.L
    });

    function getQrDataUrl() {
      const canvas = el.querySelector('canvas');
      if (canvas) {
        return canvas.toDataURL('image/png');
      }
      const img = el.querySelector('img');
      if (img) {
        return img.src;
      }
      return '';
    }

    function downloadDataUrl(url) {
      if (!url) return;
      const a = document.createElement('a');
      a.href = url;
      a.download = 'BNH_ATS_QR_<?= h((string)($student['lrn'] ?? 'student')) ?>.png';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    }

    function canvasFromImg(img) {
      return new Promise(function (resolve) {
        const tmp = document.createElement('canvas');
        const w = img.naturalWidth || img.width || DL_QR_SIZE;
        const h = img.naturalHeight || img.height || DL_QR_SIZE;
        tmp.width = w;
        tmp.height = h;
        const ctx = tmp.getContext('2d');
        ctx.imageSmoothingEnabled = false;
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, w, h);
        ctx.drawImage(img, 0, 0, w, h);
        resolve(tmp);
      });
    }

    function drawCenteredText(ctx, text, y, font, color) {
      ctx.font = font;
      ctx.fillStyle = color;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'top';
      ctx.fillText(text, Math.floor(CARD_W / 2), y);
    }

    function composeCardPng(qrCanvas) {
      const out = document.createElement('canvas');
      out.width = CARD_W;
      out.height = CARD_H;

      const ctx = out.getContext('2d');
      ctx.imageSmoothingEnabled = false;

      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, CARD_W, CARD_H);

      ctx.strokeStyle = '#d9d9d9';
      ctx.lineWidth = 2;
      ctx.strokeRect(10, 10, CARD_W - 20, CARD_H - 20);

      const outerPad = 70;
      const qrBoxPad = 18;
      const qrBoxSize = CARD_W - (outerPad * 2);

      const qrBoxX = outerPad;
      const qrBoxY = outerPad;

      ctx.strokeStyle = '#e6e6e6';
      ctx.lineWidth = 2;
      ctx.strokeRect(qrBoxX, qrBoxY, qrBoxSize, qrBoxSize);

      const qrSize = qrBoxSize - (qrBoxPad * 2);
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(qrBoxX + qrBoxPad, qrBoxY + qrBoxPad, qrSize, qrSize);

      ctx.imageSmoothingEnabled = false;
      ctx.drawImage(qrCanvas, qrBoxX + qrBoxPad, qrBoxY + qrBoxPad, qrSize, qrSize);

      const textTop = qrBoxY + qrBoxSize + 70;
      drawCenteredText(ctx, String(studentName || '').trim(), textTop, 'bold 44px Arial', '#111111');
      drawCenteredText(ctx, 'LRN: ' + String(studentLrn || '').trim(), textTop + 62, '28px Arial', '#2c3e50');
      drawCenteredText(ctx, String(studentGs || '').trim(), textTop + 104, '28px Arial', '#2c3e50');

      return out.toDataURL('image/png');
    }

    const btnDl = document.getElementById('btnDownloadQr');
    if (btnDl) {
      btnDl.addEventListener('click', async function () {
        try {
          let baseCanvas = null;

          if (dlEl) {
            dlEl.innerHTML = '';
            new QRCode(dlEl, {
              text: qrText,
              width: DL_QR_SIZE,
              height: DL_QR_SIZE,
              correctLevel: QRCode.CorrectLevel.L
            });

            await new Promise(function (r) { window.requestAnimationFrame(r); });

            const canvas = dlEl.querySelector('canvas');
            if (canvas) {
              baseCanvas = canvas;
            } else {
              const img = dlEl.querySelector('img');
              if (img) {
                if (!img.complete) {
                  await new Promise(function (resolve) { img.onload = resolve; img.onerror = resolve; });
                }
                baseCanvas = await canvasFromImg(img);
              }
            }
          }

          if (!baseCanvas) {
            const fallbackUrl = getQrDataUrl();
            downloadDataUrl(fallbackUrl);
            return;
          }

          const url = composeCardPng(baseCanvas);
          downloadDataUrl(url);
        } catch (e) {
          const fallbackUrl = getQrDataUrl();
          downloadDataUrl(fallbackUrl);
        }
      });
    }

    const btnPrint = document.getElementById('btnPrintQr');
    if (btnPrint) {
      btnPrint.addEventListener('click', function () {
        window.print();
      });
    }
  })();
</script>

<div class="card shadow-sm">
  <div class="card-body">
    <h2 class="h6">Enrollments</h2>

    <div class="mb-3">
      <form class="row g-2" method="post" action="<?= h(url('student_enroll.php')) ?>">
        <input type="hidden" name="student_id" value="<?= (int)$student['student_id'] ?>">
        <div class="col-md-8">
          <select class="form-select form-select-sm" name="schedule_id" required>
            <option value="">Enroll to schedule...</option>
            <?php foreach ($availableSchedules as $s): ?>
              <?php
                $label = (string)$s['subject_name'] . ' — ' . (string)$s['day_of_week'] . ' ' . (string)$s['start_time'] . '-' . (string)$s['end_time'] . ' (' . (string)$s['grade_level'] . '-' . (string)$s['section'] . ')';
              ?>
              <option value="<?= (int)$s['schedule_id'] ?>"><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 d-grid">
          <button class="btn btn-sm btn-primary" type="submit" <?= $availableSchedules ? '' : 'disabled' ?>>Enroll</button>
        </div>
      </form>
    </div>

    <?php if (!$enrollments): ?>
      <div class="text-muted">No enrollments found.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead>
            <tr>
              <th>Subject</th>
              <th>Schedule</th>
              <th>Teacher</th>
              <th>Enrollment</th>
              <th>Schedule Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($enrollments as $e): ?>
              <?php
                $tsfx = trim((string)($e['teacher_suffix'] ?? ''));
                $tname = trim((string)$e['first_name'] . ' ' . (string)$e['last_name']);
                if ($tsfx !== '') {
                    $tname .= ', ' . $tsfx;
                }
              ?>
              <tr>
                <td><?= h((string)$e['subject_name']) ?></td>
                <td><?= h((string)$e['day_of_week'] . ' ' . (string)$e['start_time'] . '-' . (string)$e['end_time']) ?></td>
                <td><?= h($tname) ?></td>
                <td><?= h((string)$e['enrollment_status']) ?></td>
                <td><?= h((string)$e['schedule_status']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php
require __DIR__ . '/partials/layout_bottom.php';
