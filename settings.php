<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

$title = 'Settings';
$pdo = db();

$isAdmin = is_admin();
$selfId = (int)($_SESSION['teacher_id'] ?? 0);

$targetId = $selfId;
if ($isAdmin) {
    $targetId = (int)($_GET['teacher_id'] ?? $selfId);
    if ($targetId <= 0) {
        $targetId = $selfId;
    }
}

$errors = [];
$success = null;

$gradeSections = [];

$gsEdit = null;
$gsEditId = 0;
if ($isAdmin) {
    $gsEditId = (int)($_GET['gs_edit'] ?? 0);
    if ($gsEditId > 0) {
        try {
            $stmt = $pdo->prepare('SELECT grade_section_id, grade_level, section, status FROM grade_sections WHERE grade_section_id = :id LIMIT 1');
            $stmt->execute([':id' => $gsEditId]);
            $gsEdit = $stmt->fetch();
        } catch (Throwable $e) {
            $gsEdit = null;
        }
    }
}

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['form'] ?? '') === 'grade_sections') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'add') {
        $g = (int)($_POST['gs_grade_level'] ?? 0);
        $section = trim((string)($_POST['gs_section'] ?? ''));

        if ($g < 7 || $g > 12) {
            $errors[] = 'Invalid grade level.';
        }
        if ($section === '') {
            $errors[] = 'Section is required.';
        }
        if (strlen($section) > 20) {
            $errors[] = 'Section is too long.';
        }

        if (!$errors) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO grade_sections (grade_level, section, status)
                     VALUES (:grade_level, :section, "Active")'
                );
                $stmt->execute([':grade_level' => $g, ':section' => $section]);
                $success = 'Grade/Section added.';
            } catch (PDOException $e) {
                if (($e->getCode() ?? '') === '23000') {
                    $errors[] = 'That Grade/Section already exists.';
                } else {
                    $errors[] = 'Failed to add Grade/Section.';
                }
            } catch (Throwable $e) {
                $errors[] = 'Failed to add Grade/Section.';
            }
        }
    } elseif ($action === 'update') {
        $gsId = (int)($_POST['grade_section_id'] ?? 0);
        $g = (int)($_POST['gs_grade_level'] ?? 0);
        $section = trim((string)($_POST['gs_section'] ?? ''));

        if ($gsId <= 0) {
            $errors[] = 'Invalid request.';
        }
        if ($g < 7 || $g > 12) {
            $errors[] = 'Invalid grade level.';
        }
        if ($section === '') {
            $errors[] = 'Section is required.';
        }
        if (strlen($section) > 20) {
            $errors[] = 'Section is too long.';
        }

        if (!$errors) {
            try {
                $stmt = $pdo->prepare('SELECT grade_level, section FROM grade_sections WHERE grade_section_id = :id LIMIT 1');
                $stmt->execute([':id' => $gsId]);
                $existingGs = $stmt->fetch();

                if (!$existingGs) {
                    $errors[] = 'Grade/Section not found.';
                } else {
                    $oldG = (int)($existingGs['grade_level'] ?? 0);
                    $oldSection = (string)($existingGs['section'] ?? '');

                    if (!$errors) {
                        $pdo->beginTransaction();
                        try {
                            $stmt = $pdo->prepare(
                                'UPDATE grade_sections
                                 SET grade_level = :grade_level,
                                     section = :section,
                                     updated_at = CURRENT_TIMESTAMP
                                 WHERE grade_section_id = :id'
                            );
                            $stmt->execute([':grade_level' => $g, ':section' => $section, ':id' => $gsId]);

                            if ($oldG !== $g || $oldSection !== $section) {
                                $stmt = $pdo->prepare(
                                    'UPDATE students
                                     SET grade_level = :new_g,
                                         section = :new_s,
                                         updated_at = CURRENT_TIMESTAMP
                                     WHERE grade_level = :old_g AND section = :old_s'
                                );
                                $stmt->execute([
                                    ':new_g' => $g,
                                    ':new_s' => $section,
                                    ':old_g' => $oldG,
                                    ':old_s' => $oldSection,
                                ]);

                                $stmt = $pdo->prepare(
                                    'UPDATE schedules
                                     SET grade_level = :new_g,
                                         section = :new_s,
                                         updated_at = CURRENT_TIMESTAMP
                                     WHERE grade_level = :old_g AND section = :old_s'
                                );
                                $stmt->execute([
                                    ':new_g' => $g,
                                    ':new_s' => $section,
                                    ':old_g' => $oldG,
                                    ':old_s' => $oldSection,
                                ]);
                            }

                            $pdo->commit();
                            $success = 'Grade/Section updated.';
                        } catch (PDOException $e) {
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            if (($e->getCode() ?? '') === '23000') {
                                $errors[] = 'That Grade/Section already exists.';
                            } else {
                                $errors[] = 'Failed to update Grade/Section.';
                            }
                        } catch (Throwable $e) {
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            $errors[] = 'Failed to update Grade/Section.';
                        }
                    }
                }
            } catch (Throwable $e) {
                $errors[] = 'Failed to update Grade/Section.';
            }
        }
    } elseif ($action === 'delete') {
        $gsId = (int)($_POST['grade_section_id'] ?? 0);
        if ($gsId <= 0) {
            $errors[] = 'Invalid request.';
        } else {
            try {
                $stmt = $pdo->prepare('SELECT grade_level, section FROM grade_sections WHERE grade_section_id = :id LIMIT 1');
                $stmt->execute([':id' => $gsId]);
                $existingGs = $stmt->fetch();

                if (!$existingGs) {
                    $errors[] = 'Grade/Section not found.';
                } else {
                    $oldG = (int)($existingGs['grade_level'] ?? 0);
                    $oldSection = (string)($existingGs['section'] ?? '');

                    $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM students WHERE grade_level = :g AND section = :s');
                    $stmt->execute([':g' => $oldG, ':s' => $oldSection]);
                    $studentsCnt = (int)($stmt->fetch()['cnt'] ?? 0);

                    $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM schedules WHERE grade_level = :g AND section = :s');
                    $stmt->execute([':g' => $oldG, ':s' => $oldSection]);
                    $schedulesCnt = (int)($stmt->fetch()['cnt'] ?? 0);

                    if ($studentsCnt > 0 || $schedulesCnt > 0) {
                        $errors[] = 'Cannot delete this Grade/Section because it is already used by existing students or schedules. Deactivate it instead.';
                    } else {
                        $stmt = $pdo->prepare('DELETE FROM grade_sections WHERE grade_section_id = :id');
                        $stmt->execute([':id' => $gsId]);
                        $success = 'Grade/Section deleted.';
                    }
                }
            } catch (Throwable $e) {
                $errors[] = 'Failed to delete Grade/Section.';
            }
        }
    } elseif ($action === 'toggle') {
        $gsId = (int)($_POST['grade_section_id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        if ($gsId <= 0 || !in_array($status, ['Active', 'Inactive'], true)) {
            $errors[] = 'Invalid request.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    'UPDATE grade_sections
                     SET status = :status, updated_at = CURRENT_TIMESTAMP
                     WHERE grade_section_id = :id'
                );
                $stmt->execute([':status' => $status, ':id' => $gsId]);
                $success = 'Grade/Section updated.';
            } catch (Throwable $e) {
                $errors[] = 'Failed to update Grade/Section.';
            }
        }
    } else {
        $errors[] = 'Invalid request.';
    }
}

// Handle role change (Super Admin only)
if (is_super_admin() && $_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['form'] ?? '') === 'role_change') {
    $roleTeacherId = (int)($_POST['role_teacher_id'] ?? 0);
    $newRole = (string)($_POST['new_role'] ?? '');

    if ($roleTeacherId <= 0) {
        $errors[] = 'Invalid teacher.';
    } elseif (!in_array($newRole, ['Teacher', 'Admin', 'Super Admin'], true)) {
        $errors[] = 'Invalid role.';
    } elseif ($roleTeacherId === $selfId) {
        $errors[] = 'You cannot change your own role.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT teacher_id, role FROM teachers WHERE teacher_id = :id LIMIT 1');
            $stmt->execute([':id' => $roleTeacherId]);
            $roleTarget = $stmt->fetch();

            if (!$roleTarget) {
                $errors[] = 'Teacher not found.';
            } else {
                $stmt = $pdo->prepare('UPDATE teachers SET role = :role, updated_at = CURRENT_TIMESTAMP WHERE teacher_id = :id');
                $stmt->execute([':role' => $newRole, ':id' => $roleTeacherId]);
                $success = 'Role updated to ' . $newRole . '.';
            }
        } catch (Throwable $e) {
            $errors[] = 'Failed to update role.';
        }
    }
}

$stmt = $pdo->prepare('SELECT teacher_id, employee_id, first_name, middle_name, last_name, suffix, sex, email, department, grade_levels_taught, role FROM teachers WHERE teacher_id = :id LIMIT 1');
$stmt->execute([':id' => $targetId]);
$teacher = $stmt->fetch();

if (!$teacher) {
    redirect('attendance_records.php');
}

// Admin can edit any teacher. Teacher can only edit self.
if (!$isAdmin && (int)$teacher['teacher_id'] !== $selfId) {
    redirect('attendance_records.php');
}

$values = [
    'employee_id' => (string)($teacher['employee_id'] ?? ''),
    'first_name' => (string)($teacher['first_name'] ?? ''),
    'middle_name' => (string)($teacher['middle_name'] ?? ''),
    'last_name' => (string)($teacher['last_name'] ?? ''),
    'suffix' => (string)($teacher['suffix'] ?? ''),
    'sex' => (string)($teacher['sex'] ?? ''),
    'email' => (string)($teacher['email'] ?? ''),
    'department' => (string)($teacher['department'] ?? ''),
    'grade_levels_taught' => (string)($teacher['grade_levels_taught'] ?? ''),
];

$selectedGrades = [];
foreach (explode(',', (string)($values['grade_levels_taught'] ?? '')) as $p) {
    $p = trim($p);
    if ($p === '' || !ctype_digit($p)) {
        continue;
    }
    $g = (int)$p;
    if ($g >= 7 && $g <= 12) {
        $selectedGrades[] = $g;
    }
}
$selectedGrades = array_values(array_unique($selectedGrades));
sort($selectedGrades);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array((string)($_POST['form'] ?? ''), ['grade_sections', 'role_change'], true)) {
    foreach ($values as $k => $_) {
        if ($k === 'grade_levels_taught') {
            continue;
        }
        $values[$k] = trim((string)($_POST[$k] ?? ''));
    }

    $selectedGrades = array_values(array_unique(array_map('intval', (array)($_POST['grade_levels_taught'] ?? []))));
    $validGrades = [];
    foreach ($selectedGrades as $g) {
        if ($g >= 7 && $g <= 12) {
            $validGrades[] = $g;
        }
    }
    $selectedGrades = array_values(array_unique($validGrades));
    sort($selectedGrades);

    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    $currentPassword = (string)($_POST['current_password'] ?? '');
    $wantsPasswordChange = $newPassword !== '' || $confirmPassword !== '';

    // If changing email or password, require current password (except admin editing someone else: allow reset).
    $emailChanged = $values['email'] !== (string)($teacher['email'] ?? '');
    $needsCurrentPassword = (!$isAdmin || $targetId === $selfId) && ($emailChanged || $wantsPasswordChange);

    if ($values['first_name'] === '') {
        $errors[] = 'First name is required.';
    }
    if ($values['last_name'] === '') {
        $errors[] = 'Last name is required.';
    }
    if (!in_array($values['sex'], ['Male', 'Female'], true)) {
        $errors[] = 'Sex is required.';
    }
    if ($values['email'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email is required.';
    }

    // Only admin can change employee_id.
    if (!$isAdmin) {
        $values['employee_id'] = (string)($teacher['employee_id'] ?? '');
    }

    $values['grade_levels_taught'] = implode(',', $selectedGrades);

    if ($wantsPasswordChange) {
        if ($newPassword === '' || $confirmPassword === '') {
            $errors[] = 'Password confirmation is required.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'Password confirmation does not match.';
        } elseif (!password_is_strong($newPassword)) {
            $errors[] = 'Password must be at least 8 characters and include uppercase, lowercase, number, and special character.';
        }
    }

    if ($needsCurrentPassword) {
        if ($currentPassword === '') {
            $errors[] = 'Current password is required.';
        } else {
            $stmt = $pdo->prepare('SELECT password_hash FROM teachers WHERE teacher_id = :id LIMIT 1');
            $stmt->execute([':id' => $targetId]);
            $row = $stmt->fetch();
            if (!$row || !password_verify($currentPassword, (string)$row['password_hash'])) {
                $errors[] = 'Current password is incorrect.';
            }
        }
    }

    if (!$errors) {
        // Teachers submit change requests; Admins/Super Admins apply directly.
        $teacherRole = (string)($_SESSION['role'] ?? '');
        $isTeacherRole = ($teacherRole === 'Teacher');

        if ($isTeacherRole) {
            // Submit as change request(s) for Super Admin approval
            try {
                $oldValues = [
                    'first_name' => (string)($teacher['first_name'] ?? ''),
                    'middle_name' => (string)($teacher['middle_name'] ?? ''),
                    'last_name' => (string)($teacher['last_name'] ?? ''),
                    'suffix' => (string)($teacher['suffix'] ?? ''),
                    'sex' => (string)($teacher['sex'] ?? ''),
                    'email' => (string)($teacher['email'] ?? ''),
                    'department' => (string)($teacher['department'] ?? ''),
                    'grade_levels_taught' => (string)($teacher['grade_levels_taught'] ?? ''),
                ];

                // Check if account settings actually changed
                $settingsChanged = false;
                foreach ($oldValues as $k => $v) {
                    if ($values[$k] !== $v) {
                        $settingsChanged = true;
                        break;
                    }
                }

                if ($settingsChanged) {
                    $payload = json_encode(['old' => $oldValues, 'new' => $values], JSON_UNESCAPED_UNICODE);
                    $stmt = $pdo->prepare(
                        'INSERT INTO change_requests (teacher_id, request_type, payload)
                         VALUES (:teacher_id, "account_settings", :payload)'
                    );
                    $stmt->execute([':teacher_id' => $selfId, ':payload' => $payload]);
                }

                if ($wantsPasswordChange) {
                    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                    $payload = json_encode(['password_hash' => $hash], JSON_UNESCAPED_UNICODE);
                    $stmt = $pdo->prepare(
                        'INSERT INTO change_requests (teacher_id, request_type, payload)
                         VALUES (:teacher_id, "password", :payload)'
                    );
                    $stmt->execute([':teacher_id' => $selfId, ':payload' => $payload]);
                }

                if ($settingsChanged || $wantsPasswordChange) {
                    $success = 'Your change request has been submitted for Super Admin approval.';
                } else {
                    $success = 'No changes detected.';
                }
            } catch (Throwable $e) {
                $errors[] = 'Failed to submit change request.';
            }
        } else {
            // Admin / Super Admin: apply directly
            try {
                // Ensure unique email/employee_id.
                $stmt = $pdo->prepare('SELECT teacher_id FROM teachers WHERE (email = :email OR employee_id = :employee_id) AND teacher_id <> :id LIMIT 1');
                $stmt->execute([
                    ':email' => $values['email'],
                    ':employee_id' => $values['employee_id'],
                    ':id' => $targetId,
                ]);
                if ($stmt->fetch()) {
                    $errors[] = 'Employee ID or Email already exists.';
                } else {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        'UPDATE teachers
                         SET employee_id = :employee_id,
                             first_name = :first_name,
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
                        ':employee_id' => $values['employee_id'],
                        ':first_name' => $values['first_name'],
                        ':middle_name' => $values['middle_name'] !== '' ? $values['middle_name'] : null,
                        ':last_name' => $values['last_name'],
                        ':suffix' => $values['suffix'] !== '' ? $values['suffix'] : null,
                        ':sex' => $values['sex'],
                        ':email' => $values['email'],
                        ':department' => $values['department'] !== '' ? $values['department'] : null,
                        ':grade_levels_taught' => $values['grade_levels_taught'] !== '' ? $values['grade_levels_taught'] : null,
                        ':id' => $targetId,
                    ]);

                    if ($wantsPasswordChange) {
                        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                        $stmt = $pdo->prepare('UPDATE teachers SET password_hash = :hash, updated_at = CURRENT_TIMESTAMP WHERE teacher_id = :id');
                        $stmt->execute([':hash' => $hash, ':id' => $targetId]);
                    }

                    $pdo->commit();

                    // If updating self, refresh session name + grade levels.
                    if ($targetId === $selfId) {
                        $suffix = trim($values['suffix']);
                        $baseName = trim($values['first_name'] . ' ' . $values['last_name']);
                        $_SESSION['teacher_name'] = $suffix !== '' ? ($baseName . ', ' . $suffix) : $baseName;
                        $_SESSION['grade_levels_taught'] = $values['grade_levels_taught'];
                    }

                    $success = 'Settings updated.';

                    // Refresh teacher record for rendering.
                    $stmt = $pdo->prepare('SELECT teacher_id, employee_id, first_name, middle_name, last_name, suffix, sex, email, department, grade_levels_taught, role FROM teachers WHERE teacher_id = :id LIMIT 1');
                    $stmt->execute([':id' => $targetId]);
                    $teacher = $stmt->fetch() ?: $teacher;
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Failed to update settings.';
            }
        }
    }
}

$teachersList = [];
if ($isAdmin) {
    $stmt = $pdo->prepare('SELECT teacher_id, employee_id, first_name, last_name, role, status FROM teachers ORDER BY role DESC, last_name ASC, first_name ASC');
    $stmt->execute();
    $teachersList = $stmt->fetchAll();

    try {
        $stmt = $pdo->prepare('SELECT grade_section_id, grade_level, section, status FROM grade_sections ORDER BY grade_level ASC, section ASC');
        $stmt->execute();
        $gradeSections = $stmt->fetchAll();
    } catch (Throwable $e) {
        $gradeSections = [];
    }
}

// Fetch teacher's own pending change requests
$myPendingRequests = [];
if (!$isAdmin) {
    try {
        $stmt = $pdo->prepare(
            'SELECT request_id, request_type, status, reason, created_at, reviewed_at
             FROM change_requests
             WHERE teacher_id = :id
             ORDER BY created_at DESC
             LIMIT 10'
        );
        $stmt->execute([':id' => $selfId]);
        $myPendingRequests = $stmt->fetchAll();
    } catch (Throwable $e) {
        $myPendingRequests = [];
    }
}

require __DIR__ . '/partials/layout_top.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="bnhs-page-header">
      <h1 class="bnhs-page-title">Settings</h1>
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

    <?php if (!$isAdmin && $myPendingRequests): ?>
      <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold">My Change Requests</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Type</th>
                  <th>Status</th>
                  <th>Submitted</th>
                  <th>Reviewed</th>
                  <th>Reason</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($myPendingRequests as $myCr): ?>
                  <?php
                    $myType = match((string)$myCr['request_type']) {
                        'password' => 'Password Change',
                        'account_settings' => 'Account Settings',
                        'schedule_edit' => 'Schedule Edit',
                        'schedule_deactivate' => 'Schedule Status',
                        'schedule_archive' => 'Schedule Archive',
                        default => ucfirst(str_replace('_', ' ', (string)$myCr['request_type'])),
                    };
                    $myStatus = (string)$myCr['status'];
                    $myBadge = match($myStatus) {
                        'Pending' => 'text-bg-warning',
                        'Approved' => 'text-bg-success',
                        'Rejected' => 'text-bg-danger',
                        default => 'text-bg-secondary',
                    };
                  ?>
                  <tr>
                    <td><?= h($myType) ?></td>
                    <td><span class="badge <?= $myBadge ?>"><?= h($myStatus) ?></span></td>
                    <td><small><?= h((string)$myCr['created_at']) ?></small></td>
                    <td><small><?= (string)($myCr['reviewed_at'] ?? '') !== '' ? h((string)$myCr['reviewed_at']) : '—' ?></small></td>
                    <td><small><?= h((string)($myCr['reason'] ?? '')) ?></small></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
      <div class="card shadow-sm mb-3 bnhs-filter-card">
        <div class="card-body">
          <label class="form-label">Select teacher</label>
          <select class="form-select" id="teacherSelect">
            <?php foreach ($teachersList as $t): ?>
              <?php
                $label = trim((string)$t['last_name'] . ', ' . (string)$t['first_name']);
                $label .= ' (' . (string)$t['role'] . ')';
                $label .= ' - ' . (string)$t['employee_id'];
              ?>
              <option value="<?= (int)$t['teacher_id'] ?>" <?= (int)$t['teacher_id'] === (int)$targetId ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <script>
      document.getElementById('teacherSelect').addEventListener('change', function(){
        window.location.href = <?= json_encode(url('settings.php?teacher_id=')) ?> + encodeURIComponent(this.value);
      });
      </script>

      <?php
        $activeTab = trim((string)($_GET['tab'] ?? 'account'));
        if (!in_array($activeTab, ['account', 'grades', 'roles'], true)) $activeTab = 'account';
      ?>
      <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= $activeTab === 'account' ? 'active' : '' ?>" id="tab-account" data-bs-toggle="tab" data-bs-target="#pane-account" type="button" role="tab">Account Settings</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= $activeTab === 'grades' ? 'active' : '' ?>" id="tab-grades" data-bs-toggle="tab" data-bs-target="#pane-grades" type="button" role="tab">Grade & Section</button>
        </li>
        <?php if (is_super_admin()): ?>
          <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'roles' ? 'active' : '' ?>" id="tab-roles" data-bs-toggle="tab" data-bs-target="#pane-roles" type="button" role="tab">Role Management</button>
          </li>
        <?php endif; ?>
      </ul>

      <div class="tab-content">
      <div class="tab-pane fade <?= $activeTab === 'grades' ? 'show active' : '' ?>" id="pane-grades" role="tabpanel">
      <div class="card shadow-sm mb-3">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
              <div class="fw-semibold">Grade & Section Management</div>
              <div class="text-muted small">Used by student registration and schedule creation.</div>
            </div>
          </div>

          <form method="post" action="<?= h(url('settings.php')) ?>" class="row g-2 align-items-end">
            <input type="hidden" name="form" value="grade_sections">
            <?php if ($gsEdit): ?>
              <input type="hidden" name="grade_section_id" value="<?= (int)$gsEdit['grade_section_id'] ?>">
            <?php endif; ?>
            <div class="col-md-3">
              <label class="form-label">Grade Level</label>
              <select class="form-select" name="gs_grade_level" required>
                <option value="">Select...</option>
                <?php for ($g = 7; $g <= 12; $g++): ?>
                  <option value="<?= $g ?>" <?= $gsEdit && (int)$gsEdit['grade_level'] === $g ? 'selected' : '' ?>>Grade <?= $g ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Section</label>
              <input class="form-control" name="gs_section" placeholder="e.g., A, STE, ICT" value="<?= $gsEdit ? h((string)$gsEdit['section']) : '' ?>" required>
            </div>
            <div class="col-md-3">
              <button class="btn btn-primary w-100" type="submit" name="action" value="<?= $gsEdit ? 'update' : 'add' ?>"><?= $gsEdit ? 'Save' : 'Add' ?></button>
            </div>
            <?php if ($gsEdit): ?>
              <div class="col-12">
                <a class="btn btn-link px-0" href="<?= h(url('settings.php?teacher_id=' . (int)$targetId)) ?>">Cancel edit</a>
              </div>
            <?php endif; ?>
          </form>

          <hr>

          <?php if (!$gradeSections): ?>
            <div class="bnhs-empty-state" id="gsEmptyState">
              <div class="bnhs-empty-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M8 7V3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                  <path d="M16 7V3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                  <path d="M3 11h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                  <path d="M5 7h14a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </div>
              No grade/section entries yet.
            </div>
          <?php else: ?>
            <div id="gsTableContainer">
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Grade</th>
                      <th>Section</th>
                      <th>Status</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody id="gsTbody">
                    <?php foreach ($gradeSections as $gs): ?>
                      <tr class="gs-row">
                        <td><?= (int)$gs['grade_level'] ?></td>
                        <td><?= h((string)$gs['section']) ?></td>
                        <td>
                          <?php if ((string)$gs['status'] === 'Active'): ?>
                            <span class="badge text-bg-success">Active</span>
                          <?php else: ?>
                            <span class="badge text-bg-secondary">Inactive</span>
                          <?php endif; ?>
                        </td>
                        <td class="text-end">
                          <a class="btn btn-outline-primary btn-sm" href="<?= h(url('settings.php?teacher_id=' . (int)$targetId . '&gs_edit=' . (int)$gs['grade_section_id'])) ?>">Edit</a>
                          <?php if ((string)$gs['status'] === 'Active'): ?>
                            <form method="post" action="<?= h(url('settings.php')) ?>" class="d-inline">
                              <input type="hidden" name="form" value="grade_sections">
                              <input type="hidden" name="grade_section_id" value="<?= (int)$gs['grade_section_id'] ?>">
                              <button type="submit" name="action" value="toggle" class="btn btn-outline-secondary btn-sm" onclick="this.form.status.value='Inactive'">Deactivate</button>
                              <input type="hidden" name="status" value="">
                            </form>
                          <?php else: ?>
                            <form method="post" action="<?= h(url('settings.php')) ?>" class="d-inline">
                              <input type="hidden" name="form" value="grade_sections">
                              <input type="hidden" name="grade_section_id" value="<?= (int)$gs['grade_section_id'] ?>">
                              <button type="submit" name="action" value="toggle" class="btn btn-success btn-sm" onclick="this.form.status.value='Active'">Activate</button>
                              <input type="hidden" name="status" value="">
                            </form>
                          <?php endif; ?>
                          <form method="post" action="<?= h(url('settings.php')) ?>" class="d-inline" data-confirm="Delete this grade/section? This cannot be undone." data-confirm-title="Delete Grade/Section" data-confirm-ok="Delete" data-confirm-cancel="Cancel" data-confirm-icon="danger">
                            <input type="hidden" name="form" value="grade_sections">
                            <input type="hidden" name="grade_section_id" value="<?= (int)$gs['grade_section_id'] ?>">
                            <button type="submit" name="action" value="delete" class="btn btn-outline-danger btn-sm">Delete</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <div class="d-flex justify-content-between align-items-center pt-2" id="gsPagination">
                <div class="text-muted small" id="gsPagInfo"></div>
                <nav aria-label="Grade Section Pagination">
                  <ul class="pagination pagination-sm mb-0" id="gsPagLinks"></ul>
                </nav>
              </div>
            </div>
            <script>
            (function(){
              var PER_PAGE = 10;
              var currentPage = 1;
              var rows = document.querySelectorAll('#gsTbody .gs-row');
              var totalRows = rows.length;
              var totalPages = Math.max(1, Math.ceil(totalRows / PER_PAGE));

              function render(){
                var start = (currentPage - 1) * PER_PAGE;
                var end = start + PER_PAGE;
                for(var i = 0; i < rows.length; i++){
                  rows[i].style.display = (i >= start && i < end) ? '' : 'none';
                }
                var from = totalRows === 0 ? 0 : start + 1;
                var to = Math.min(totalRows, end);
                document.getElementById('gsPagInfo').textContent = 'Showing ' + from + '-' + to + ' of ' + totalRows;

                var links = document.getElementById('gsPagLinks');
                var html = '';
                var prevDis = currentPage <= 1 ? ' disabled' : '';
                var nextDis = currentPage >= totalPages ? ' disabled' : '';
                html += '<li class="page-item' + prevDis + '"><a class="page-link" href="#" data-gs-page="' + (currentPage - 1) + '">&lsaquo;</a></li>';
                for(var p = 1; p <= totalPages; p++){
                  var act = p === currentPage ? ' active' : '';
                  html += '<li class="page-item' + act + '"><a class="page-link" href="#" data-gs-page="' + p + '">' + p + '</a></li>';
                }
                html += '<li class="page-item' + nextDis + '"><a class="page-link" href="#" data-gs-page="' + (currentPage + 1) + '">&rsaquo;</a></li>';
                links.innerHTML = html;
              }

              document.getElementById('gsPagLinks').addEventListener('click', function(e){
                e.preventDefault();
                var a = e.target.closest('[data-gs-page]');
                if(!a) return;
                var p = parseInt(a.getAttribute('data-gs-page'), 10);
                if(p < 1 || p > totalPages) return;
                currentPage = p;
                render();
              });

              render();
            })();
            </script>
          <?php endif; ?>
        </div>
      </div>
      </div><!-- /pane-grades -->

      <?php if (is_super_admin()): ?>
        <div class="tab-pane fade <?= $activeTab === 'roles' ? 'show active' : '' ?>" id="pane-roles" role="tabpanel">
        <div class="card shadow-sm mb-3">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div>
                <div class="fw-semibold">Role Management</div>
                <div class="text-muted small">Assign roles to teachers. Only Super Admins can manage roles.</div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Current Role</th>
                    <th>Status</th>
                    <th class="text-end">Change Role</th>
                  </tr>
                </thead>
                <tbody id="rolesTbody">
                  <?php foreach ($teachersList as $t): ?>
                    <?php
                      $tId = (int)$t['teacher_id'];
                      $tName = trim((string)$t['last_name'] . ', ' . (string)$t['first_name']);
                      $tRole = (string)$t['role'];
                      $tStatus = (string)$t['status'];
                      $isSelf = ($tId === $selfId);
                    ?>
                    <tr class="role-row">
                      <td><?= h((string)$t['employee_id']) ?></td>
                      <td><?= h($tName) ?><?= $isSelf ? ' <span class="badge text-bg-info">You</span>' : '' ?></td>
                      <td>
                        <?php if ($tRole === 'Super Admin'): ?>
                          <span class="badge text-bg-danger">Super Admin</span>
                        <?php elseif ($tRole === 'Admin'): ?>
                          <span class="badge text-bg-warning">Admin</span>
                        <?php else: ?>
                          <span class="badge text-bg-secondary">Teacher</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($tStatus === 'Active'): ?>
                          <span class="badge text-bg-success">Active</span>
                        <?php else: ?>
                          <span class="badge text-bg-secondary"><?= h($tStatus) ?></span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <?php if ($isSelf): ?>
                          <span class="text-muted small">—</span>
                        <?php else: ?>
                          <form method="post" action="<?= h(url('settings.php?teacher_id=' . (int)$targetId)) ?>" class="d-inline" data-confirm="Change role of <?= h($tName) ?>?" data-confirm-title="Change Role" data-confirm-ok="Change" data-confirm-cancel="Cancel" data-confirm-icon="question">
                            <input type="hidden" name="form" value="role_change">
                            <input type="hidden" name="role_teacher_id" value="<?= $tId ?>">
                            <select name="new_role" class="form-select form-select-sm d-inline-block" style="width:auto" onchange="this.form.submit()">
                              <option value="Teacher" <?= $tRole === 'Teacher' ? 'selected' : '' ?>>Teacher</option>
                              <option value="Admin" <?= $tRole === 'Admin' ? 'selected' : '' ?>>Admin</option>
                              <option value="Super Admin" <?= $tRole === 'Super Admin' ? 'selected' : '' ?>>Super Admin</option>
                            </select>
                          </form>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <script>
            (function(){
              var PER_PAGE = 10;
              var currentPage = 1;
              var rows = document.querySelectorAll('#rolesTbody .role-row');
              var totalRows = rows.length;
              var totalPages = Math.max(1, Math.ceil(totalRows / PER_PAGE));
              if(totalRows <= PER_PAGE) return;

              var container = document.getElementById('rolesTbody').closest('.card-body');
              var pagDiv = document.createElement('div');
              pagDiv.className = 'd-flex justify-content-between align-items-center pt-2';
              pagDiv.innerHTML = '<div class="text-muted small" id="rolesPagInfo"></div><nav><ul class="pagination pagination-sm mb-0" id="rolesPagLinks"></ul></nav>';
              container.appendChild(pagDiv);

              function render(){
                var start = (currentPage - 1) * PER_PAGE;
                var end = start + PER_PAGE;
                for(var i = 0; i < rows.length; i++){
                  rows[i].style.display = (i >= start && i < end) ? '' : 'none';
                }
                document.getElementById('rolesPagInfo').textContent = 'Showing ' + (start+1) + '-' + Math.min(totalRows, end) + ' of ' + totalRows;
                var links = document.getElementById('rolesPagLinks');
                var html = '';
                var prevDis = currentPage <= 1 ? ' disabled' : '';
                var nextDis = currentPage >= totalPages ? ' disabled' : '';
                html += '<li class="page-item' + prevDis + '"><a class="page-link" href="#" data-role-page="' + (currentPage-1) + '">&lsaquo;</a></li>';
                for(var p = 1; p <= totalPages; p++){
                  html += '<li class="page-item' + (p===currentPage?' active':'') + '"><a class="page-link" href="#" data-role-page="' + p + '">' + p + '</a></li>';
                }
                html += '<li class="page-item' + nextDis + '"><a class="page-link" href="#" data-role-page="' + (currentPage+1) + '">&rsaquo;</a></li>';
                links.innerHTML = html;
              }

              document.getElementById('rolesPagLinks').addEventListener('click', function(e){
                e.preventDefault();
                var a = e.target.closest('[data-role-page]');
                if(!a) return;
                var p = parseInt(a.getAttribute('data-role-page'), 10);
                if(p < 1 || p > totalPages) return;
                currentPage = p;
                render();
              });

              render();
            })();
            </script>
          </div>
        </div>
        </div><!-- /pane-roles -->
      <?php endif; ?>

      <div class="tab-pane fade <?= $activeTab === 'account' ? 'show active' : '' ?>" id="pane-account" role="tabpanel">
    <?php endif; ?>

    <div class="card shadow-sm">
      <div class="card-body">
        <form method="post" action="<?= h(url('settings.php' . ($isAdmin ? ('?teacher_id=' . (int)$targetId) : ''))) ?>">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Employee ID</label>
              <input class="form-control" name="employee_id" value="<?= h($values['employee_id']) ?>" <?= $isAdmin ? '' : 'readonly' ?>>
            </div>
            <div class="col-md-6">
              <label class="form-label">Department</label>
              <input class="form-control" name="department" value="<?= h($values['department']) ?>">
            </div>

            <div class="col-md-4">
              <label class="form-label">First Name *</label>
              <input class="form-control" name="first_name" value="<?= h($values['first_name']) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Middle Name</label>
              <input class="form-control" name="middle_name" value="<?= h($values['middle_name']) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Last Name *</label>
              <input class="form-control" name="last_name" value="<?= h($values['last_name']) ?>" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Suffix</label>
              <input class="form-control" name="suffix" value="<?= h($values['suffix']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Sex *</label>
              <select class="form-select" name="sex" required>
                <option value="" <?= $values['sex'] === '' ? 'selected' : '' ?>>Select...</option>
                <option value="Male" <?= $values['sex'] === 'Male' ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= $values['sex'] === 'Female' ? 'selected' : '' ?>>Female</option>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label">Email *</label>
              <input type="email" class="form-control" name="email" value="<?= h($values['email']) ?>" required>
            </div>

            <div class="col-12">
              <label class="form-label">Grade Levels Taught</label>
              <div class="border rounded p-3 bg-white">
                <div class="row g-2">
                  <?php for ($g = 7; $g <= 12; $g++): ?>
                    <div class="col-6 col-md-2">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="grade_levels_taught[]" value="<?= $g ?>" id="gt_<?= $g ?>" <?= in_array($g, $selectedGrades, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="gt_<?= $g ?>">G<?= $g ?></label>
                      </div>
                    </div>
                  <?php endfor; ?>
                </div>
              </div>
            </div>

            <div class="col-12">
              <hr>
            </div>

            <div class="col-12">
              <div class="fw-semibold">Change Password</div>
              <div class="text-muted small">Leave blank if you don\'t want to change it.</div>
            </div>

            <?php if (!$isAdmin || $targetId === $selfId): ?>
              <div class="col-12">
                <label class="form-label">Current Password (required if changing email or password)</label>
                <input type="password" class="form-control" name="current_password" autocomplete="current-password">
              </div>
            <?php else: ?>
              <div class="col-12">
                <div class="alert alert-info mb-0">As Admin, you can reset the selected teacher\'s password without their current password.</div>
              </div>
            <?php endif; ?>

            <div class="col-md-6">
              <label class="form-label">New Password</label>
              <input type="password" class="form-control" name="new_password" autocomplete="new-password">
            </div>
            <div class="col-md-6">
              <label class="form-label">Confirm New Password</label>
              <input type="password" class="form-control" name="confirm_password" autocomplete="new-password">
            </div>
          </div>

          <div class="d-grid mt-3">
            <button class="btn btn-primary" type="submit">Save Changes</button>
          </div>
        </form>
      </div>
    </div>

    <?php if ($isAdmin): ?>
      </div><!-- /pane-account -->
      </div><!-- /tab-content -->
    <?php endif; ?>
  </div>
</div>

<?php
require __DIR__ . '/partials/layout_bottom.php';
