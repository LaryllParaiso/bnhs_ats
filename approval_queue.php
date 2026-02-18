<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

if (!is_super_admin()) {
    redirect('attendance_records.php');
}

$title = 'Approval Queue';
$pdo = db();

$errors = [];
$success = null;

// Handle approve / reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $reason = trim((string)($_POST['reason'] ?? ''));
    $adminId = (int)$_SESSION['teacher_id'];

    if ($requestId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
        $errors[] = 'Invalid request.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM change_requests WHERE request_id = :id AND status = "Pending" LIMIT 1');
        $stmt->execute([':id' => $requestId]);
        $cr = $stmt->fetch();

        if (!$cr) {
            $errors[] = 'Request not found or already processed.';
        } else {
            if ($action === 'approve') {
                try {
                    $pdo->beginTransaction();

                    $type = (string)$cr['request_type'];
                    $payload = json_decode((string)$cr['payload'], true) ?: [];
                    $crTeacherId = (int)$cr['teacher_id'];
                    $targetId = $cr['target_id'] !== null ? (int)$cr['target_id'] : null;

                    if ($type === 'account_settings') {
                        $newVals = $payload['new'] ?? [];
                        if ($newVals) {
                            $stmt = $pdo->prepare(
                                'UPDATE teachers
                                 SET first_name = :first_name,
                                     middle_name = :middle_name,
                                     last_name = :last_name,
                                     suffix = :suffix,
                                     sex = :sex,
                                     email = :email,
                                     department = :department,
                                     grade_levels_taught = :grade_levels_taught,
                                     updated_at = CURRENT_TIMESTAMP
                                 WHERE teacher_id = :id'
                            );
                            $stmt->execute([
                                ':first_name' => (string)($newVals['first_name'] ?? ''),
                                ':middle_name' => ($newVals['middle_name'] ?? '') !== '' ? (string)$newVals['middle_name'] : null,
                                ':last_name' => (string)($newVals['last_name'] ?? ''),
                                ':suffix' => ($newVals['suffix'] ?? '') !== '' ? (string)$newVals['suffix'] : null,
                                ':sex' => (string)($newVals['sex'] ?? ''),
                                ':email' => (string)($newVals['email'] ?? ''),
                                ':department' => ($newVals['department'] ?? '') !== '' ? (string)$newVals['department'] : null,
                                ':grade_levels_taught' => ($newVals['grade_levels_taught'] ?? '') !== '' ? (string)$newVals['grade_levels_taught'] : null,
                                ':id' => $crTeacherId,
                            ]);
                        }
                    } elseif ($type === 'password') {
                        $hash = (string)($payload['password_hash'] ?? '');
                        if ($hash !== '') {
                            $stmt = $pdo->prepare('UPDATE teachers SET password_hash = :hash, updated_at = CURRENT_TIMESTAMP WHERE teacher_id = :id');
                            $stmt->execute([':hash' => $hash, ':id' => $crTeacherId]);
                        }
                    } elseif ($type === 'schedule_edit' && $targetId !== null) {
                        $newVals = $payload['new'] ?? [];
                        if ($newVals) {
                            $stmt = $pdo->prepare(
                                'UPDATE schedules
                                 SET subject_name = :subject_name,
                                     grade_level = :grade_level,
                                     section = :section,
                                     day_of_week = :day_of_week,
                                     start_time = :start_time,
                                     end_time = :end_time,
                                     room = :room,
                                     school_year = :school_year,
                                     status = :status,
                                     updated_at = CURRENT_TIMESTAMP
                                 WHERE schedule_id = :id'
                            );
                            $stmt->execute([
                                ':subject_name' => (string)($newVals['subject_name'] ?? ''),
                                ':grade_level' => (int)($newVals['grade_level'] ?? 0),
                                ':section' => (string)($newVals['section'] ?? ''),
                                ':day_of_week' => (string)($newVals['day_of_week'] ?? ''),
                                ':start_time' => (string)($newVals['start_time'] ?? ''),
                                ':end_time' => (string)($newVals['end_time'] ?? ''),
                                ':room' => ($newVals['room'] ?? '') !== '' ? (string)$newVals['room'] : null,
                                ':school_year' => (string)($newVals['school_year'] ?? ''),
                                ':status' => (string)($newVals['status'] ?? 'Active'),
                                ':id' => $targetId,
                            ]);
                        }
                    } elseif ($type === 'schedule_deactivate' && $targetId !== null) {
                        $newStatus = (string)($payload['new_status'] ?? '');
                        if (in_array($newStatus, ['Active', 'Inactive'], true)) {
                            $stmt = $pdo->prepare('UPDATE schedules SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE schedule_id = :id');
                            $stmt->execute([':status' => $newStatus, ':id' => $targetId]);
                        }
                    } elseif ($type === 'schedule_archive' && $targetId !== null) {
                        $stmt = $pdo->prepare('UPDATE schedules SET status = "Archived", updated_at = CURRENT_TIMESTAMP WHERE schedule_id = :id');
                        $stmt->execute([':id' => $targetId]);
                    }

                    // Mark request as approved
                    $stmt = $pdo->prepare(
                        'UPDATE change_requests SET status = "Approved", reviewed_by = :admin_id, reviewed_at = NOW() WHERE request_id = :id'
                    );
                    $stmt->execute([':admin_id' => $adminId, ':id' => $requestId]);

                    $pdo->commit();
                    $success = 'Request approved and changes applied.';
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = 'Failed to approve request: ' . $e->getMessage();
                }
            } else {
                // Reject
                try {
                    $stmt = $pdo->prepare(
                        'UPDATE change_requests SET status = "Rejected", reviewed_by = :admin_id, reviewed_at = NOW(), reason = :reason WHERE request_id = :id'
                    );
                    $stmt->execute([':admin_id' => $adminId, ':reason' => $reason !== '' ? $reason : null, ':id' => $requestId]);
                    $success = 'Request rejected.';
                } catch (Throwable $e) {
                    $errors[] = 'Failed to reject request.';
                }
            }
        }
    }
}

// Fetch pending requests
$stmt = $pdo->prepare(
    'SELECT cr.*, t.employee_id, t.first_name, t.last_name
     FROM change_requests cr
     JOIN teachers t ON t.teacher_id = cr.teacher_id
     WHERE cr.status = "Pending"
     ORDER BY cr.created_at ASC'
);
$stmt->execute();
$pendingRequests = $stmt->fetchAll();

// Fetch recent processed requests (last 20)
$stmt = $pdo->prepare(
    'SELECT cr.*, t.employee_id, t.first_name AS req_first, t.last_name AS req_last,
            rv.first_name AS rev_first, rv.last_name AS rev_last
     FROM change_requests cr
     JOIN teachers t ON t.teacher_id = cr.teacher_id
     LEFT JOIN teachers rv ON rv.teacher_id = cr.reviewed_by
     WHERE cr.status != "Pending"
     ORDER BY cr.reviewed_at DESC
     LIMIT 20'
);
$stmt->execute();
$recentRequests = $stmt->fetchAll();

require __DIR__ . '/partials/layout_top.php';
?>

<div class="row">
  <div class="col-12">
    <div class="bnhs-page-header">
      <h1 class="bnhs-page-title">Approval Queue</h1>
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

    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold">Pending Requests (<?= count($pendingRequests) ?>)</div>
      <div class="card-body p-0">
        <?php if (!$pendingRequests): ?>
          <div class="bnhs-empty-state">
            <div class="bnhs-empty-icon" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M9 11l3 3L22 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            No pending requests.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Teacher</th>
                  <th>Type</th>
                  <th>Details</th>
                  <th>Submitted</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pendingRequests as $cr): ?>
                  <?php
                    $crName = trim((string)$cr['first_name'] . ' ' . (string)$cr['last_name']);
                    $crType = (string)$cr['request_type'];
                    $crPayload = json_decode((string)$cr['payload'], true) ?: [];

                    $typeLabel = match($crType) {
                        'password' => 'Password Change',
                        'account_settings' => 'Account Settings',
                        'schedule_edit' => 'Schedule Edit',
                        'schedule_deactivate' => 'Schedule Status Change',
                        'schedule_archive' => 'Schedule Archive',
                        default => ucfirst(str_replace('_', ' ', $crType)),
                    };

                    $typeBadge = match($crType) {
                        'password' => 'text-bg-warning',
                        'account_settings' => 'text-bg-info',
                        'schedule_edit' => 'text-bg-primary',
                        'schedule_deactivate' => 'text-bg-secondary',
                        'schedule_archive' => 'text-bg-danger',
                        default => 'text-bg-secondary',
                    };

                    // Build details summary
                    $details = '';
                    if ($crType === 'password') {
                        $details = 'Wants to change password.';
                    } elseif ($crType === 'account_settings') {
                        $changes = [];
                        $old = $crPayload['old'] ?? [];
                        $new = $crPayload['new'] ?? [];
                        foreach ($new as $k => $v) {
                            if (($old[$k] ?? '') !== $v) {
                                $changes[] = ucfirst(str_replace('_', ' ', $k)) . ': "' . ($old[$k] ?? '') . '" → "' . $v . '"';
                            }
                        }
                        $details = $changes ? implode('; ', $changes) : 'No visible changes.';
                    } elseif ($crType === 'schedule_edit') {
                        $changes = [];
                        $old = $crPayload['old'] ?? [];
                        $new = $crPayload['new'] ?? [];
                        foreach ($new as $k => $v) {
                            if (($old[$k] ?? '') !== $v) {
                                $changes[] = ucfirst(str_replace('_', ' ', $k)) . ': "' . ($old[$k] ?? '') . '" → "' . $v . '"';
                            }
                        }
                        $details = 'Schedule #' . (int)$cr['target_id'] . '. ' . ($changes ? implode('; ', $changes) : 'No visible changes.');
                    } elseif ($crType === 'schedule_deactivate') {
                        $details = 'Schedule #' . (int)$cr['target_id'] . ': ' . ($crPayload['old_status'] ?? '?') . ' → ' . ($crPayload['new_status'] ?? '?');
                    } elseif ($crType === 'schedule_archive') {
                        $details = 'Schedule #' . (int)$cr['target_id'] . ': ' . ($crPayload['old_status'] ?? '?') . ' → Archived';
                    }
                  ?>
                  <tr>
                    <td><?= (int)$cr['request_id'] ?></td>
                    <td><?= h($crName) ?><br><small class="text-muted"><?= h((string)$cr['employee_id']) ?></small></td>
                    <td><span class="badge <?= $typeBadge ?>"><?= h($typeLabel) ?></span></td>
                    <td><small><?= h($details) ?></small></td>
                    <td><small><?= h((string)$cr['created_at']) ?></small></td>
                    <td class="text-end">
                      <form method="post" action="<?= h(url('approval_queue.php')) ?>" class="d-inline" data-confirm="Approve this request?" data-confirm-title="Approve Request" data-confirm-ok="Approve" data-confirm-cancel="Cancel" data-confirm-icon="question">
                        <input type="hidden" name="request_id" value="<?= (int)$cr['request_id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn btn-success btn-sm">Approve</button>
                      </form>
                      <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal<?= (int)$cr['request_id'] ?>">Reject</button>

                      <div class="modal fade" id="rejectModal<?= (int)$cr['request_id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                          <div class="modal-content">
                            <form method="post" action="<?= h(url('approval_queue.php')) ?>">
                              <input type="hidden" name="request_id" value="<?= (int)$cr['request_id'] ?>">
                              <input type="hidden" name="action" value="reject">
                              <div class="modal-header">
                                <h5 class="modal-title">Reject Request #<?= (int)$cr['request_id'] ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                              </div>
                              <div class="modal-body">
                                <label class="form-label">Reason (optional)</label>
                                <textarea class="form-control" name="reason" rows="3" placeholder="Why is this request being rejected?"></textarea>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">Reject</button>
                              </div>
                            </form>
                          </div>
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($recentRequests): ?>
      <div class="card shadow-sm">
        <div class="card-header fw-semibold">Recently Processed</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Teacher</th>
                  <th>Type</th>
                  <th>Status</th>
                  <th>Reviewed By</th>
                  <th>Date</th>
                  <th>Reason</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentRequests as $cr): ?>
                  <?php
                    $crName = trim((string)$cr['req_first'] . ' ' . (string)$cr['req_last']);
                    $revName = $cr['rev_first'] ? trim((string)$cr['rev_first'] . ' ' . (string)$cr['rev_last']) : '—';
                    $crType = (string)$cr['request_type'];
                    $typeLabel = match($crType) {
                        'password' => 'Password',
                        'account_settings' => 'Account',
                        'schedule_edit' => 'Sched. Edit',
                        'schedule_deactivate' => 'Sched. Status',
                        'schedule_archive' => 'Sched. Archive',
                        default => ucfirst(str_replace('_', ' ', $crType)),
                    };
                    $statusBadge = (string)$cr['status'] === 'Approved' ? 'text-bg-success' : 'text-bg-danger';
                  ?>
                  <tr>
                    <td><?= (int)$cr['request_id'] ?></td>
                    <td><?= h($crName) ?></td>
                    <td><small><?= h($typeLabel) ?></small></td>
                    <td><span class="badge <?= $statusBadge ?>"><?= h((string)$cr['status']) ?></span></td>
                    <td><small><?= h($revName) ?></small></td>
                    <td><small><?= h((string)($cr['reviewed_at'] ?? '')) ?></small></td>
                    <td><small><?= h((string)($cr['reason'] ?? '')) ?></small></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php
require __DIR__ . '/partials/layout_bottom.php';
