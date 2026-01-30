<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/tokens.php';

$title = 'Regenerate Student QR';

$values = [
    'lrn' => '',
];

$errors = [];
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['lrn'] = trim((string)($_POST['lrn'] ?? ''));

    if ($values['lrn'] === '' || !ctype_digit($values['lrn']) || strlen($values['lrn']) !== 12) {
        $errors[] = 'LRN must be exactly 12 digits.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT student_id, lrn, status FROM students WHERE lrn = :lrn LIMIT 1');
        $stmt->execute([':lrn' => $values['lrn']]);
        $student = $stmt->fetch();

        if (!$student) {
            $errors[] = 'Student not found.';
        } elseif ((string)$student['status'] !== 'Active') {
            $errors[] = 'Student is not Active. Please contact your teacher.';
        } else {
            $newToken = generate_qr_token();
            $stmt = $pdo->prepare('UPDATE students SET qr_token = :token WHERE student_id = :id');
            $stmt->execute([':token' => $newToken, ':id' => (int)$student['student_id']]);

            redirect('student_qr.php?lrn=' . urlencode((string)$student['lrn']) . '&token=' . urlencode($newToken) . '&regen=1');
        }
    }
}

require __DIR__ . '/partials/layout_top.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h1 class="h4 mb-3">Regenerate QR</h1>

        <?php if ($errors): ?>
          <div class="alert alert-danger">
            <ul class="mb-0">
              <?php foreach ($errors as $e): ?>
                <li><?= h($e) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= h(url('qr_regenerate.php')) ?>">
          <div class="mb-3">
            <label class="form-label">LRN (12 digits)</label>
            <input class="form-control" name="lrn" value="<?= h($values['lrn']) ?>" maxlength="12" required>
          </div>

          <div class="d-flex justify-content-between align-items-center">
            <a class="btn btn-link" href="<?= h(url('student_register.php')) ?>">Back to Student Registration</a>
            <button class="btn btn-primary" type="submit">Regenerate</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/partials/layout_bottom.php';
