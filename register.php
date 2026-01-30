<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

if (is_logged_in()) {
    redirect('attendance_records.php');
}

$errors = [];
$values = [
    'employee_id' => '',
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'suffix' => '',
    'sex' => '',
    'email' => '',
    'department' => '',
];

$selectedGrades = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $k => $_) {
        $values[$k] = trim((string)($_POST[$k] ?? ''));
    }

    $selectedGrades = array_values(array_unique(array_map('intval', (array)($_POST['grade_levels_taught'] ?? []))));
    sort($selectedGrades);

    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($values['employee_id'] === '') {
        $errors[] = 'Employee ID is required.';
    }

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

    $validGrades = [];
    foreach ($selectedGrades as $g) {
        if ($g >= 7 && $g <= 12) {
            $validGrades[] = $g;
        }
    }
    $selectedGrades = array_values(array_unique($validGrades));
    sort($selectedGrades);

    if (!$selectedGrades) {
        $errors[] = 'Please select at least one grade level taught.';
    }

    if (!password_is_strong($password)) {
        $errors[] = 'Password must be at least 8 characters and include uppercase, lowercase, number, and special character.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (!$errors) {
        $pdo = db();

        $stmt = $pdo->prepare('SELECT teacher_id FROM teachers WHERE employee_id = :employee_id OR email = :email LIMIT 1');
        $stmt->execute([
            ':employee_id' => $values['employee_id'],
            ':email' => $values['email'],
        ]);

        if ($stmt->fetch()) {
            $errors[] = 'Employee ID or Email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            $gradesCsv = implode(',', $selectedGrades);

            $stmt = $pdo->prepare(
                'INSERT INTO teachers (employee_id, first_name, middle_name, last_name, suffix, sex, email, password_hash, department, grade_levels_taught, role, status, approval_status)
                 VALUES (:employee_id, :first_name, :middle_name, :last_name, :suffix, :sex, :email, :password_hash, :department, :grade_levels_taught, :role, :status, :approval_status)'
            );

            $stmt->execute([
                ':employee_id' => $values['employee_id'],
                ':first_name' => $values['first_name'],
                ':middle_name' => $values['middle_name'] !== '' ? $values['middle_name'] : null,
                ':last_name' => $values['last_name'],
                ':suffix' => $values['suffix'] !== '' ? $values['suffix'] : null,
                ':sex' => $values['sex'],
                ':email' => $values['email'],
                ':password_hash' => $hash,
                ':department' => $values['department'] !== '' ? $values['department'] : null,
                ':grade_levels_taught' => $gradesCsv,
                ':role' => 'Teacher',
                ':status' => 'Inactive',
                ':approval_status' => 'Pending',
            ]);

            redirect('login.php?registered=1');
        }
    }
}

$title = 'Teacher Registration';
require __DIR__ . '/partials/layout_top.php';
?>

<div class="row justify-content-center">
  <div class="col-md-7 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h1 class="bnhs-page-title mb-3">Teacher Registration</h1>

        <?php if ($errors): ?>
          <div class="alert alert-danger">
            <ul class="mb-0">
              <?php foreach ($errors as $e): ?>
                <li><?= h($e) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= h(url('register.php')) ?>">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Employee ID *</label>
              <input class="form-control" name="employee_id" value="<?= h($values['employee_id']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Department</label>
              <input class="form-control" name="department" value="<?= h($values['department']) ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Grade Levels Taught *</label>
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
              <input class="form-control" name="suffix" value="<?= h($values['suffix']) ?>" placeholder="e.g., Jr., Sr., III">
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
            <div class="col-md-6">
              <label class="form-label">Password *</label>
              <input type="password" class="form-control" name="password" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Confirm Password *</label>
              <input type="password" class="form-control" name="confirm_password" required>
            </div>
          </div>

          <div class="d-grid mt-3">
            <button class="btn btn-primary" type="submit">Create Account</button>
          </div>

          <div class="mt-3">
            <a href="<?= h(url('login.php')) ?>">Back to login</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
require __DIR__ . '/partials/layout_bottom.php';
