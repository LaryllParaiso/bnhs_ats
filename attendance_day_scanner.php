<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

$title = 'Day Scanner';

$pdo = db();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS attendance_day_scanning (
        scan_date DATE PRIMARY KEY,
        status ENUM("Active","Ended") NOT NULL DEFAULT "Active",
        started_by INT NOT NULL,
        started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ended_at DATETIME NULL,
        CONSTRAINT fk_attendance_day_scanning_started_by FOREIGN KEY (started_by) REFERENCES teachers (teacher_id) ON DELETE RESTRICT,
        INDEX idx_attendance_day_scanning_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci'
);

$stmt = $pdo->prepare('SELECT status FROM attendance_day_scanning WHERE scan_date = CURDATE() LIMIT 1');
$stmt->execute();
$row = $stmt->fetch();
$isActive = $row && (string)$row['status'] === 'Active';

$bodyClass = 'bnhs-hide-nav';

require __DIR__ . '/partials/layout_top.php';
?>

<div class="bnhs-page-header">
  <div>
    <h1 class="bnhs-page-title">Day Scanner</h1>
    <div class="text-muted small">
      Status:
      <?php if ($isActive): ?>
        <span class="badge text-bg-success">ACTIVE</span>
      <?php else: ?>
        <span class="badge text-bg-secondary">INACTIVE</span>
      <?php endif; ?>
    </div>
  </div>
  <div class="bnhs-page-actions">
    <?php if ((string)($_SESSION['role'] ?? '') === 'Admin'): ?>
      <a class="btn btn-outline-secondary btn-sm" href="<?= h(url('attendance_day_control.php')) ?>">Control</a>
    <?php else: ?>
      <a class="btn btn-outline-secondary btn-sm" href="<?= h(url('attendance_records.php')) ?>">Dashboard</a>
    <?php endif; ?>
  </div>
</div>

<?php if (!$isActive): ?>
  <div class="alert alert-warning" data-no-toast>
    Day scanning is not active yet.
    <?php if ((string)($_SESSION['role'] ?? '') === 'Admin'): ?>
      Please start it from <a href="<?= h(url('attendance_day_control.php')) ?>">Day Scanning Control</a>.
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="fw-semibold">Scanner</div>
          <div class="text-muted small"><?= h(date('l, F j, Y')) ?></div>
        </div>

        <div class="bnhs-scanner-preview">
          <div id="reader"></div>
        </div>
        <div class="bnhs-scanner-caption">POSITION QR CODE IN THE FRAME</div>

        <div class="mt-3">
          <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#scannerOptions" aria-expanded="false" aria-controls="scannerOptions">
            Options
          </button>
        </div>

        <div class="collapse mt-3" id="scannerOptions">
          <div class="mb-2">
            <label class="form-label small mb-1">Camera</label>
            <select class="form-select form-select-sm" id="cameraSelect"></select>
          </div>

          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" id="chkDisableFlip">
            <label class="form-check-label small" for="chkDisableFlip">Disable flip</label>
          </div>

          <div class="small text-muted mt-2" id="lastDecoded"></div>

          <div class="mt-3 d-flex gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" id="btnStart" <?= $isActive ? '' : 'disabled' ?>>Start Camera</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnStop" disabled>Stop</button>
          </div>

          <hr>

          <div class="fw-semibold mb-2">Manual QR Text</div>
          <textarea class="form-control" id="qrText" rows="4" placeholder='Paste QR JSON here' <?= $isActive ? '' : 'disabled' ?>></textarea>
          <div class="mt-2">
            <button type="button" class="btn btn-primary btn-sm" id="btnSubmit" <?= $isActive ? '' : 'disabled' ?>>Submit</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div class="fw-semibold">Scans Today (All Schedules)</div>
          <input class="form-control form-control-sm" type="text" id="scanSearch" placeholder="Search Name" style="max-width: 160px;">
        </div>

        <div class="text-muted small mt-1">
          Present: <span id="cntPresent">0</span> |
          Late: <span id="cntLate">0</span> |
          Total: <span id="cntTotal">0</span>
        </div>

        <div class="table-responsive mt-3">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>Time</th>
                <th>Name</th>
                <th>Section</th>
              </tr>
            </thead>
            <tbody id="scanRows"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row mt-4">
  <div class="col-lg-5">
    <div class="bnhs-scan-notice" id="scanAlert"></div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
  const isActive = <?= $isActive ? 'true' : 'false' ?>;

  const alertEl = document.getElementById('scanAlert');
  const txtEl = document.getElementById('qrText');
  const cameraSelect = document.getElementById('cameraSelect');
  const chkDisableFlip = document.getElementById('chkDisableFlip');
  const lastDecodedEl = document.getElementById('lastDecoded');
  const searchEl = document.getElementById('scanSearch');

  let lastItems = [];

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function iconSvg(kind) {
    if (kind === 'success') {
      return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9.0 16.2 4.8 12 3.4 13.4 9.0 19 21 7 19.6 5.6 9.0 16.2Z" fill="currentColor"/></svg>';
    }
    if (kind === 'warning') {
      return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2a10 10 0 1 0 .001 20.001A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 .001-16.001A8 8 0 0 1 12 20Zm.75-13h-1.5v6l5.2 3.1.75-1.23-4.45-2.62V7Z" fill="currentColor"/></svg>';
    }
    return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="m13.41 12 5.3-5.29-1.42-1.42L12 10.59 6.71 5.29 5.29 6.71 10.59 12l-5.3 5.29 1.42 1.42L12 13.41l5.29 5.3 1.42-1.42L13.41 12Z" fill="currentColor"/></svg>';
  }

  function showScanCard(kind, title, subtitle) {
    if (!alertEl) return;
    const safeTitle = escapeHtml(title || '');
    const safeSub = escapeHtml(subtitle || '');
    alertEl.innerHTML =
      '<div class="bnhs-scan-card ' + kind + '">' +
        '<div class="bnhs-scan-icon">' + iconSvg(kind) + '</div>' +
        '<div>' +
          '<div class="bnhs-scan-title">' + safeTitle + '</div>' +
          (safeSub ? ('<div class="bnhs-scan-sub">' + safeSub + '</div>') : '') +
        '</div>' +
      '</div>';
  }

  function showAlert(type, msg) {
    if (type === 'success') {
      showScanCard('success', 'Recorded', msg || '');
      return;
    }
    if (type === 'warning') {
      showScanCard('warning', 'Notice', msg || '');
      return;
    }
    showScanCard('danger', 'Scan Failed', msg || '');
  }

  function formatTime(t) {
    const s = String(t || '');
    if (s.length >= 5 && s.indexOf(':') !== -1) {
      return s.slice(0, 5);
    }
    return s;
  }

  function renderRows() {
    const rowsEl = document.getElementById('scanRows');
    if (!rowsEl) return;
    const q = String((searchEl && searchEl.value) ? searchEl.value : '').trim().toLowerCase();

    rowsEl.innerHTML = '';
    (lastItems || []).forEach(function (r) {
      const nm = String(r.name || '');
      if (q && nm.toLowerCase().indexOf(q) === -1) {
        return;
      }

      const sec = (r.grade_level ? (r.grade_level + '-') : '') + (r.section || '');
      const tr = document.createElement('tr');
      tr.innerHTML =
        '<td>' + escapeHtml(formatTime(r.time_scanned || '')) + '</td>' +
        '<td>' + escapeHtml(nm) + '</td>' +
        '<td>' + escapeHtml(sec) + '</td>';
      rowsEl.appendChild(tr);
    });
  }

  async function readJsonResponse(res) {
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch (e) {
      const snippet = (text || '').slice(0, 400);
      throw new Error('Server returned non-JSON response. ' + snippet);
    }
  }

  async function refreshFeed() {
    let data = null;
    try {
      const res = await fetch(<?= json_encode(url('attendance_day_feed.php')) ?>, {
        cache: 'no-store',
        headers: { 'Accept': 'application/json' }
      });
      data = await readJsonResponse(res);
    } catch (e) {
      document.getElementById('cntPresent').textContent = '0';
      document.getElementById('cntLate').textContent = '0';
      document.getElementById('cntTotal').textContent = '0';
      lastItems = [];
      renderRows();
      return;
    }

    if (!data.ok) {
      document.getElementById('cntPresent').textContent = '0';
      document.getElementById('cntLate').textContent = '0';
      document.getElementById('cntTotal').textContent = '0';
      lastItems = [];
      renderRows();
      return;
    }

    document.getElementById('cntPresent').textContent = data.counts ? (data.counts.present || 0) : 0;
    document.getElementById('cntLate').textContent = data.counts ? (data.counts.late || 0) : 0;
    document.getElementById('cntTotal').textContent = data.counts ? (data.counts.total || 0) : 0;

    lastItems = data.items || [];
    renderRows();
  }

  cameraSelect.innerHTML = '<option value="">Default camera</option>';
  if (typeof Html5Qrcode !== 'undefined' && typeof Html5Qrcode.getCameras === 'function') {
    Html5Qrcode.getCameras().then(function (cameras) {
      cameraSelect.innerHTML = '';

      const opt0 = document.createElement('option');
      opt0.value = '';
      opt0.textContent = 'Default camera';
      cameraSelect.appendChild(opt0);

      (cameras || []).forEach(function (c) {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.label || c.id;
        cameraSelect.appendChild(opt);
      });
    }).catch(function () {
      cameraSelect.innerHTML = '<option value="">Default camera</option>';
    });
  }

  async function submitScan(qrText) {
    if (!isActive) return;
    if (!qrText) return;

    let data = null;
    try {
      const res = await fetch(<?= json_encode(url('attendance_scan.php')) ?>, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ mode: 'day', qr_text: qrText })
      });
      data = await readJsonResponse(res);
    } catch (e) {
      const msg = (e && e.message) ? e.message : 'Server returned invalid response.';
      showAlert('danger', msg);
      return;
    }

    if (!data.ok) {
      showAlert('danger', data.message || 'Scan failed');
      return;
    }

    const st = String(data.status || '').toLowerCase();
    const nm = data.student && data.student.name ? String(data.student.name) : '';
    const sched = data.schedule ? String(data.schedule) : '';

    if (st === 'late') {
      showScanCard('warning', 'WELCOME ' + nm, sched);
    } else if (st === 'absent') {
      showScanCard('danger', 'WELCOME ' + nm, sched);
    } else {
      showScanCard('success', 'WELCOME ' + nm, sched);
    }

    txtEl.value = '';
    await refreshFeed();
  }

  document.getElementById('btnSubmit').addEventListener('click', function () {
    submitScan((txtEl.value || '').trim());
  });

  let html5Qr = null;
  let isProcessing = false;

  document.getElementById('btnStart').addEventListener('click', async function () {
    if (!isActive) return;
    if (html5Qr) return;

    if (typeof Html5Qrcode === 'undefined') {
      showAlert('danger', 'Scanner library failed to load. Please refresh the page and check your internet connection.');
      return;
    }

    html5Qr = new Html5Qrcode('reader');

    try {
      const cameraId = (cameraSelect && cameraSelect.value) ? cameraSelect.value : '';
      const source = cameraId !== '' ? cameraId : { facingMode: 'environment' };

      const qrbox = function (viewfinderWidth, viewfinderHeight) {
        const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
        const size = Math.floor(Math.min(340, minEdge * 0.8));
        return { width: size, height: size };
      };

      await html5Qr.start(
        source,
        { fps: 12, qrbox: qrbox, disableFlip: !!(chkDisableFlip && chkDisableFlip.checked) },
        function (decodedText) {
          if (isProcessing) {
            return;
          }
          isProcessing = true;
          if (lastDecodedEl) {
            lastDecodedEl.textContent = decodedText;
          }
          submitScan(decodedText).finally(function () {
            window.setTimeout(function () {
              isProcessing = false;
            }, 700);
          });
        }
      );

      document.getElementById('btnStart').disabled = true;
      document.getElementById('btnStop').disabled = false;
    } catch (e) {
      html5Qr = null;
      const msg = (e && e.message) ? e.message : '';
      showAlert('danger', 'Camera start failed' + (msg ? (': ' + msg) : ''));
    }
  });

  document.getElementById('btnStop').addEventListener('click', async function () {
    if (!html5Qr) return;
    try {
      await html5Qr.stop();
      html5Qr.clear();
    } catch (e) {
    }
    html5Qr = null;
    document.getElementById('btnStart').disabled = false;
    document.getElementById('btnStop').disabled = true;
  });

  refreshFeed();
  setInterval(refreshFeed, 2000);

  if (searchEl) {
    searchEl.addEventListener('input', function () {
      renderRows();
    });
  }
})();
</script>

<?php require __DIR__ . '/partials/layout_bottom.php';
