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

$pagedSql = $sql . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
$stmt = $pdo->prepare($pagedSql);
$stmt->execute($params);
$schedules = $stmt->fetchAll();

$title = 'Schedules';
require __DIR__ . '/partials/layout_top.php';
?>

<div class="bnhs-page-header">
  <h1 class="bnhs-page-title">Schedules</h1>
  <div class="bnhs-page-actions">
    <a class="btn btn-primary btn-sm" href="<?= h(url('schedule_form.php')) ?>">Create Schedule</a>
  </div>
</div>

<div class="card shadow-sm mb-3 bnhs-filter-card">
  <div class="card-body">
    <form class="row g-2" method="get" action="<?= h(url('schedules.php')) ?>">
      <div class="col-md-3">
        <select class="form-select" name="day">
          <option value="" <?= $day === '' ? 'selected' : '' ?>>All Days</option>
          <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday'] as $d): ?>
            <option value="<?= h($d) ?>" <?= $day === $d ? 'selected' : '' ?>><?= h($d) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <input class="form-control" name="grade" placeholder="Grade" value="<?= h($grade) ?>">
      </div>
      <div class="col-md-2">
        <input class="form-control" name="section" placeholder="Section" value="<?= h($section) ?>">
      </div>
      <div class="col-md-3">
        <select class="form-select" name="status">
          <option value="" <?= $status === '' ? 'selected' : '' ?>>All Status</option>
          <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
          <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
          <option value="Archived" <?= $status === 'Archived' ? 'selected' : '' ?>>Archived</option>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-outline-primary" type="submit">Filter</button>
      </div>
    </form>
  </div>
</div>

<?php if (isset($_GET['saved'])): ?>
  <div class="alert alert-success">Schedule saved.</div>
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
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$schedules): ?>
            <tr>
              <td colspan="6" class="p-0">
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
                <td><?= h((string)$s['status']) ?></td>
                <td class="text-end">
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
