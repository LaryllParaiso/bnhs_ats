<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';

$title = 'Student QR';

$lrn = trim((string)($_GET['lrn'] ?? ''));
$token = trim((string)($_GET['token'] ?? ''));

$registered = (string)($_GET['registered'] ?? '') === '1';
$regen = (string)($_GET['regen'] ?? '') === '1';

$pdo = db();
$student = null;

if ($lrn !== '' && $token !== '') {
    $stmt = $pdo->prepare('SELECT * FROM students WHERE lrn = :lrn AND qr_token = :token LIMIT 1');
    $stmt->execute([':lrn' => $lrn, ':token' => $token]);
    $student = $stmt->fetch();
}

require __DIR__ . '/partials/layout_top.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-body">
        <h1 class="h4 mb-3">Universal QR Code</h1>

        <?php if ($registered): ?>
          <div class="alert alert-success">Registration successful. Save your QR code.</div>
        <?php elseif ($regen): ?>
          <div class="alert alert-info">QR token regenerated. Old QR codes are now invalid.</div>
        <?php endif; ?>

        <?php if (!$student): ?>
          <div class="alert alert-danger">Invalid or expired QR link.</div>
          <div class="d-flex gap-2">
            <a class="btn btn-primary" href="<?= h(url('student_register.php')) ?>">Student Registration</a>
            <a class="btn btn-outline-secondary" href="<?= h(url('qr_regenerate.php')) ?>">Regenerate QR</a>
          </div>
        <?php else: ?>
          <?php
            $suffix = trim((string)($student['suffix'] ?? ''));
            $fullName = trim((string)$student['first_name'] . ' ' . (string)$student['last_name']);
            if ($suffix !== '') {
                $fullName .= ', ' . $suffix;
            }

            $payload = [
                'lrn' => (string)$student['lrn'],
                'name' => $fullName,
                'grade' => (int)$student['grade_level'],
                'section' => (string)$student['section'],
                'token' => (string)$student['qr_token'],
            ];
            $qrText = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
          ?>

          <div class="row align-items-center">
            <div class="col-md-6 text-center">
              <div id="qrcode" class="d-inline-block p-3 bg-white border rounded"></div>
              <div id="qrcodeDownload" style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;"></div>
              <div class="mt-3 d-flex justify-content-center gap-2">
                <button id="btnDownload" type="button" class="btn btn-outline-primary btn-sm">Download PNG</button>
                <button id="btnPrint" type="button" class="btn btn-outline-secondary btn-sm">Print</button>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-2"><span class="text-muted">Name:</span> <span class="fw-semibold"><?= h($fullName) ?></span></div>
              <div class="mb-2"><span class="text-muted">LRN:</span> <span class="fw-semibold"><?= h((string)$student['lrn']) ?></span></div>
              <div class="mb-2"><span class="text-muted">Grade/Section:</span> <span class="fw-semibold"><?= h((string)$student['grade_level']) ?> - <?= h((string)$student['section']) ?></span></div>
              <div class="text-muted small">This QR code is valid only if the token matches the server record.</div>
              <hr>
              <div class="d-flex gap-2">
                <a class="btn btn-link" href="<?= h(url('student_register.php')) ?>">Back to Registration</a>
                <a class="btn btn-link" href="<?= h(url('qr_regenerate.php')) ?>">Regenerate QR</a>
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

              document.getElementById('btnDownload').addEventListener('click', async function () {
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

              document.getElementById('btnPrint').addEventListener('click', function () {
                window.print();
              });
            })();
          </script>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/partials/layout_bottom.php';
