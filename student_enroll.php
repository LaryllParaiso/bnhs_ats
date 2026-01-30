<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('students.php');
}

$teacherId = (int)$_SESSION['teacher_id'];
$isAdmin = is_admin();
$taughtGrades = teacher_grade_levels_taught($teacherId);

$studentId = (int)($_POST['student_id'] ?? 0);
$scheduleId = (int)($_POST['schedule_id'] ?? 0);

if ($studentId <= 0 || $scheduleId <= 0) {
    redirect('students.php');
}

$pdo = db();

if ($isAdmin) {
    $stmt = $pdo->prepare('SELECT student_id, grade_level, section FROM students WHERE student_id = :id LIMIT 1');
    $stmt->execute([':id' => $studentId]);
    $student = $stmt->fetch();
} elseif ($taughtGrades) {
    $placeholders = [];
    $params = [':id' => $studentId];
    foreach ($taughtGrades as $i => $g) {
        $ph = ':tg' . $i;
        $placeholders[] = $ph;
        $params[$ph] = $g;
    }
    $stmt = $pdo->prepare('SELECT student_id, grade_level, section FROM students WHERE student_id = :id AND grade_level IN (' . implode(',', $placeholders) . ') LIMIT 1');
    $stmt->execute($params);
    $student = $stmt->fetch();
} else {
    $stmt = $pdo->prepare(
        'SELECT students.student_id, students.grade_level, students.section
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
    $stmt->execute([':id' => $studentId, ':teacher_id' => $teacherId]);
    $student = $stmt->fetch();
}

if (!$student) {
    redirect('students.php');
}

if ($isAdmin) {
    $stmt = $pdo->prepare('SELECT schedule_id, teacher_id, grade_level, section, status FROM schedules WHERE schedule_id = :id AND status != "Archived" LIMIT 1');
    $stmt->execute([':id' => $scheduleId]);
    $schedule = $stmt->fetch();
} else {
    $stmt = $pdo->prepare('SELECT schedule_id, teacher_id, grade_level, section, status FROM schedules WHERE schedule_id = :id AND teacher_id = :teacher_id AND status != "Archived" LIMIT 1');
    $stmt->execute([':id' => $scheduleId, ':teacher_id' => $teacherId]);
    $schedule = $stmt->fetch();
}

if (!$schedule) {
    redirect('student_view.php?id=' . urlencode((string)$studentId));
}

if ((int)$schedule['grade_level'] !== (int)$student['grade_level'] || (string)$schedule['section'] !== (string)$student['section']) {
    redirect('student_view.php?id=' . urlencode((string)$studentId));
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO student_schedules (student_id, schedule_id, status)
         VALUES (:student_id, :schedule_id, "Active")
         ON DUPLICATE KEY UPDATE status = "Active"'
    );
    $stmt->execute([':student_id' => $studentId, ':schedule_id' => $scheduleId]);
    redirect('student_view.php?id=' . urlencode((string)$studentId) . '&enrolled=1');
} catch (Throwable $e) {
    redirect('student_view.php?id=' . urlencode((string)$studentId));
}
