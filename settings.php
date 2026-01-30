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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['form'] ?? '') !== 'grade_sections') {
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

    <?php if ($isAdmin): ?>
      <div class="card shadow-sm mb-3 bnhs-filter-card">
        <div class="card-body">
          <form method="get" action="<?= h(url('settings.php')) ?>" class="row g-2 align-items-end">
            <div class="col-md-8">
              <label class="form-label">Select teacher</label>
              <select class="form-select" name="teacher_id">
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
            <div class="col-md-4">
              <button class="btn btn-primary w-100" type="submit">Load</button>
            </div>
          </form>
        </div>
      </div>

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
            <div class="col-md-3">
              <label class="form-label">Grade Level</label>
              <select class="form-select" name="gs_grade_level" required>
                <option value="">Select...</option>
                <?php for ($g = 7; $g <= 12; $g++): ?>
                  <option value="<?= $g ?>">Grade <?= $g ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Section</label>
              <input class="form-control" name="gs_section" placeholder="e.g., A, STE, ICT" required>
            </div>
            <div class="col-md-3">
              <button class="btn btn-primary w-100" type="submit" name="action" value="add">Add</button>
            </div>
          </form>

          <hr>

          <?php if (!$gradeSections): ?>
            <div class="bnhs-empty-state">
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
                <tbody>
                  <?php foreach ($gradeSections as $gs): ?>
                    <tr>
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
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
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
  </div>
</div>

<?php
require __DIR__ . '/partials/layout_bottom.php';
