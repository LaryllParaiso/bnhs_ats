<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

$teacherId = (int)$_SESSION['teacher_id'];
$isAdmin = is_admin();
$taughtGrades = teacher_grade_levels_taught($teacherId);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('students.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    redirect('students.php');
}

$pdo = db();

if ($isAdmin) {
    $stmt = $pdo->prepare('SELECT student_id FROM students WHERE student_id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
} elseif ($taughtGrades) {
    $placeholders = [];
    $params = [':id' => $id];
    foreach ($taughtGrades as $i => $g) {
        $ph = ':tg' . $i;
        $placeholders[] = $ph;
        $params[$ph] = $g;
    }
    $stmt = $pdo->prepare('SELECT student_id FROM students WHERE student_id = :id AND grade_level IN (' . implode(',', $placeholders) . ') LIMIT 1');
    $stmt->execute($params);
} else {
    $stmt = $pdo->prepare(
        'SELECT students.student_id
         FROM students
         WHERE students.student_id = :id
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
    $stmt->execute([':id' => $id, ':teacher_id' => $teacherId]);
}

if (!$stmt->fetch()) {
    redirect('students.php');
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('DELETE FROM attendance WHERE student_id = :id');
    $stmt->execute([':id' => $id]);

    $stmt = $pdo->prepare('DELETE FROM student_schedules WHERE student_id = :id');
    $stmt->execute([':id' => $id]);

    $stmt = $pdo->prepare('DELETE FROM students WHERE student_id = :id');
    $stmt->execute([':id' => $id]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    redirect('students.php');
}

redirect('students.php?deleted=1');
