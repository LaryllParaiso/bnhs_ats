<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

if (is_logged_in()) {
    redirect('attendance_records.php');
}

$errors = [];
$email = trim((string)($_POST['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    if (!$errors) {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT * FROM teachers WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $teacher = $stmt->fetch();

        $now = new DateTimeImmutable('now');

        if (!$teacher) {
            $errors[] = 'Invalid email or password.';
        } elseif (($teacher['approval_status'] ?? 'Approved') === 'Pending') {
            $errors[] = 'Account pending approval.';
        } elseif (($teacher['approval_status'] ?? 'Approved') === 'Declined') {
            $errors[] = 'Registration was declined.';
        } elseif (($teacher['status'] ?? 'Inactive') !== 'Active') {
            $errors[] = 'Account is inactive.';
        } else {
            $lockoutUntilRaw = $teacher['lockout_until'] ?? null;
            if ($lockoutUntilRaw) {
                $lockoutUntil = new DateTimeImmutable((string)$lockoutUntilRaw);
                if ($lockoutUntil > $now) {
                    $errors[] = 'Too many failed attempts. Try again later.';
                }
            }

            if (!$errors) {
                $ok = password_verify($password, (string)$teacher['password_hash']);

                if (!$ok) {
                    $attempts = (int)($teacher['failed_login_attempts'] ?? 0);
                    $attempts++;

                    $lockoutUntil = null;
                    if ($attempts >= AUTH_MAX_FAILED_LOGINS) {
                        $lockoutUntil = $now->modify('+' . AUTH_LOCKOUT_MINUTES . ' minutes')->format('Y-m-d H:i:s');
                    }

                    $stmt = $pdo->prepare(
                        'UPDATE teachers
                         SET failed_login_attempts = :attempts,
                             lockout_until = :lockout_until,
                             updated_at = CURRENT_TIMESTAMP
                         WHERE teacher_id = :teacher_id'
                    );

                    $stmt->execute([
                        ':attempts' => $attempts,
                        ':lockout_until' => $lockoutUntil,
                        ':teacher_id' => $teacher['teacher_id'],
                    ]);

                    $errors[] = 'Invalid email or password.';
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE teachers
                         SET failed_login_attempts = 0,
                             lockout_until = NULL,
                             last_login_at = :last_login_at,
                             updated_at = CURRENT_TIMESTAMP
                         WHERE teacher_id = :teacher_id'
                    );

                    $stmt->execute([
                        ':last_login_at' => $now->format('Y-m-d H:i:s'),
                        ':teacher_id' => $teacher['teacher_id'],
                    ]);

                    session_regenerate_id(true);

                    $_SESSION['teacher_id'] = (int)$teacher['teacher_id'];
                    $_SESSION['role'] = (string)$teacher['role'];
                    $suffix = trim((string)($teacher['suffix'] ?? ''));
                    $baseName = trim((string)$teacher['first_name'] . ' ' . (string)$teacher['last_name']);
                    $_SESSION['teacher_name'] = $suffix !== '' ? ($baseName . ', ' . $suffix) : $baseName;
                    $_SESSION['grade_levels_taught'] = (string)($teacher['grade_levels_taught'] ?? '');
                    $_SESSION['notif_seen_at'] = (string)($teacher['notif_seen_at'] ?? '');
                    $_SESSION['last_activity'] = time();

                    redirect('attendance_records.php');
                }
            }
        }
    }
}

$title = 'Teacher Login';
require __DIR__ . '/partials/layout_top.php';
?>

<div class="row justify-content-center align-items-center" style="min-height:calc(100vh - 120px)">
  <div class="col-md-6 col-lg-5 col-xl-4">
    <div class="text-center mb-4">
      <img src="<?= h(url('ui_ux_design/bicos%20logo.png')) ?>" alt="BNHS Logo" style="width:64px;height:64px;border-radius:50%;box-shadow:0 4px 16px rgba(0,0,0,.1)">
      <h2 class="mt-3 mb-1" style="font-size:1.1rem;font-weight:700;color:var(--primary-navy)">Bicos National High School</h2>
      <p class="text-muted small mb-0">Attendance Tracking System</p>
    </div>
    <div class="card shadow-sm">
      <div class="card-body">
        <h1 class="bnhs-page-title mb-3">Teacher Login</h1>

        <?php if (isset($_GET['registered'])): ?>
          <div class="alert alert-success">Registration submitted. Please wait for admin approval.</div>
        <?php endif; ?>

        <?php if (isset($_GET['loggedout'])): ?>
          <div class="alert alert-info">You have been logged out.</div>
        <?php endif; ?>

        <?php if (isset($_GET['timeout'])): ?>
          <div class="alert alert-warning">Session timed out. Please login again.</div>
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

        <form method="post" action="<?= h(url('login.php')) ?>">
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="email" value="<?= h($email) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" class="form-control" name="password" required>
          </div>
          <div class="d-grid">
            <button class="btn btn-primary" type="submit">Login</button>
          </div>
        </form>

        <div class="mt-3">
          <a href="<?= h(url('register.php')) ?>">Create teacher account</a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
require __DIR__ . '/partials/layout_bottom.php';
