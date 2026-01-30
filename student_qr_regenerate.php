<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/tokens.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('students.php');
}

$teacherId = (int)$_SESSION['teacher_id'];
$isAdmin = is_admin();
$taughtGrades = teacher_grade_levels_taught($teacherId);

$studentId = (int)($_POST['student_id'] ?? 0);

if ($studentId <= 0) {
    redirect('students.php');
}

$pdo = db();

if ($isAdmin) {
    $stmt = $pdo->prepare('SELECT student_id, status FROM students WHERE student_id = :id LIMIT 1');
    $stmt->execute([':id' => $studentId]);
} elseif ($taughtGrades) {
    $placeholders = [];
    $params = [':id' => $studentId];
    foreach ($taughtGrades as $i => $g) {
        $ph = ':tg' . $i;
        $placeholders[] = $ph;
        $params[$ph] = $g;
    }
    $stmt = $pdo->prepare('SELECT student_id, status FROM students WHERE student_id = :id AND grade_level IN (' . implode(',', $placeholders) . ') LIMIT 1');
    $stmt->execute($params);
} else {
    $stmt = $pdo->prepare(
        'SELECT student_id, status
         FROM students
         WHERE student_id = :id
           AND EXISTS (
             SELECT 1
             FROM student_schedules ss
             JOIN schedules sch ON sch.schedule_id = ss.schedule_id
             WHERE ss.student_id = students.student_id
               AND ss.status = "Active"
               AND sch.teacher_id = :teacher_id
               AND sch.status != "Archived"
           )
         LIMIT 1'
    );
    $stmt->execute([':id' => $studentId, ':teacher_id' => $teacherId]);
}

$student = $stmt->fetch();

if (!$student) {
    redirect('students.php');
}

$newToken = generate_qr_token();
$stmt = $pdo->prepare('UPDATE students SET qr_token = :token WHERE student_id = :id');
$stmt->execute([':token' => $newToken, ':id' => $studentId]);

redirect('student_view.php?id=' . $studentId . '&regen=1');
