<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

if (!is_admin()) {
    redirect('attendance_records.php');
}

$title = 'Teacher Approvals';
$pdo = db();

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $teacherId = (int)($_POST['teacher_id'] ?? 0);

    if (!in_array($action, ['approve', 'decline'], true) || $teacherId <= 0) {
        $errors[] = 'Invalid request.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT teacher_id, approval_status FROM teachers WHERE teacher_id = :id LIMIT 1');
            $stmt->execute([':id' => $teacherId]);
            $t = $stmt->fetch();

            if (!$t) {
                $errors[] = 'Teacher not found.';
            } elseif (($t['approval_status'] ?? 'Approved') !== 'Pending') {
                $errors[] = 'Teacher is not pending approval.';
            } else {
                if ($action === 'approve') {
                    $stmt = $pdo->prepare(
                        'UPDATE teachers
                         SET approval_status = "Approved",
                             status = "Active",
                             approved_by = :admin_id,
                             approved_at = NOW(),
                             updated_at = CURRENT_TIMESTAMP
                         WHERE teacher_id = :id'
                    );
                    $stmt->execute([':admin_id' => (int)$_SESSION['teacher_id'], ':id' => $teacherId]);
                    $success = 'Teacher approved.';
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE teachers
                         SET approval_status = "Declined",
                             status = "Inactive",
                             approved_by = :admin_id,
                             approved_at = NOW(),
                             updated_at = CURRENT_TIMESTAMP
                         WHERE teacher_id = :id'
                    );
                    $stmt->execute([':admin_id' => (int)$_SESSION['teacher_id'], ':id' => $teacherId]);
                    $success = 'Teacher declined.';
                }
            }
        } catch (Throwable $e) {
            $errors[] = 'Failed to update approval.';
        }
    }
}

$stmt = $pdo->prepare(
    'SELECT teacher_id, employee_id, first_name, last_name, sex, email, department, created_at
     FROM teachers
     WHERE role = "Teacher"
       AND (approval_status = "Pending")
     ORDER BY created_at ASC, teacher_id ASC'
);
$stmt->execute();
$pending = $stmt->fetchAll();

require __DIR__ . '/partials/layout_top.php';
?>

<div class="row">
  <div class="col-12">
    <div class="bnhs-page-header">
      <h1 class="bnhs-page-title">Teacher Approvals</h1>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errors as $e): ?>
            <li><?= h($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="card shadow-sm">
      <div class="card-body">
        <?php if (!$pending): ?>
          <div class="bnhs-empty-state">
            <div class="bnhs-empty-icon" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M22 21v-2a4 4 0 0 0-3-3.87" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            No pending teacher registrations.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>Employee ID</th>
                  <th>Name</th>
                  <th>Sex</th>
                  <th>Email</th>
                  <th>Department</th>
                  <th>Registered</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pending as $t): ?>
                  <?php
                    $name = trim((string)$t['first_name'] . ' ' . (string)$t['last_name']);
                  ?>
                  <tr>
                    <td><?= h((string)$t['employee_id']) ?></td>
                    <td><?= h($name) ?></td>
                    <td><?= h((string)$t['sex']) ?></td>
                    <td><?= h((string)$t['email']) ?></td>
                    <td><?= h((string)($t['department'] ?? '')) ?></td>
                    <td><?= h((string)($t['created_at'] ?? '')) ?></td>
                    <td class="text-end">
                      <form method="post" class="d-inline" action="<?= h(url('teacher_approvals.php')) ?>" data-confirm="Approve this registration?" data-confirm-title="Approve Registration" data-confirm-ok="Approve" data-confirm-cancel="Cancel" data-confirm-icon="question">
                        <input type="hidden" name="teacher_id" value="<?= (int)$t['teacher_id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn btn-success btn-sm">Approve</button>
                      </form>
                      <form method="post" class="d-inline" action="<?= h(url('teacher_approvals.php')) ?>" data-confirm="Decline this registration?" data-confirm-title="Decline Registration" data-confirm-ok="Decline" data-confirm-cancel="Cancel" data-confirm-icon="warning">
                        <input type="hidden" name="teacher_id" value="<?= (int)$t['teacher_id'] ?>">
                        <input type="hidden" name="action" value="decline">
                        <button type="submit" class="btn btn-outline-danger btn-sm">Decline</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
require __DIR__ . '/partials/layout_bottom.php';
