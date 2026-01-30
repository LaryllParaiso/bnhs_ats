<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$teacherId = (int)$_SESSION['teacher_id'];
$sessionId = (int)($_GET['session_id'] ?? 0);

if ($sessionId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid session']);
    exit;
}

$pdo = db();

$stmt = $pdo->prepare('SELECT session_id, teacher_id, schedule_id, session_date, status FROM attendance_sessions WHERE session_id = :id AND teacher_id = :teacher_id LIMIT 1');
$stmt->execute([':id' => $sessionId, ':teacher_id' => $teacherId]);
$session = $stmt->fetch();

if (!$session || (string)$session['status'] !== 'Active') {
    echo json_encode(['ok' => false, 'message' => 'Session not found']);
    exit;
}

$scheduleId = (int)$session['schedule_id'];
$sessionDate = (string)$session['session_date'];

$stmt = $pdo->prepare(
    'SELECT COUNT(*) AS cnt
     FROM student_schedules
     WHERE schedule_id = :schedule_id AND status = "Active"'
);
$stmt->execute([':schedule_id' => $scheduleId]);
$enrolled = (int)($stmt->fetch()['cnt'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT a.time_scanned, a.status, st.lrn, st.first_name, st.last_name, st.suffix
     FROM attendance a
     JOIN students st ON st.student_id = a.student_id
     WHERE a.schedule_id = :schedule_id
       AND a.teacher_id = :teacher_id
       AND a.date = :date
       AND a.status IN ("Present","Late")
     ORDER BY a.time_scanned DESC
     LIMIT 50'
);
$stmt->execute([':schedule_id' => $scheduleId, ':teacher_id' => $teacherId, ':date' => $sessionDate]);
$rows = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT
        SUM(CASE WHEN status = "Present" THEN 1 ELSE 0 END) AS present,
        SUM(CASE WHEN status = "Late" THEN 1 ELSE 0 END) AS late
     FROM attendance
     WHERE schedule_id = :schedule_id
       AND teacher_id = :teacher_id
       AND date = :date
       AND status IN ("Present","Late")'
);
$stmt->execute([':schedule_id' => $scheduleId, ':teacher_id' => $teacherId, ':date' => $sessionDate]);
$agg = $stmt->fetch();

$items = [];
$present = (int)($agg['present'] ?? 0);
$late = (int)($agg['late'] ?? 0);

foreach ($rows as $r) {
    $suffix = trim((string)($r['suffix'] ?? ''));
    $name = trim((string)$r['first_name'] . ' ' . (string)$r['last_name']);
    if ($suffix !== '') {
        $name .= ', ' . $suffix;
    }

    $st = (string)$r['status'];

    $items[] = [
        'time_scanned' => (string)($r['time_scanned'] ?? ''),
        'lrn' => (string)($r['lrn'] ?? ''),
        'name' => $name,
        'status' => $st,
    ];
}

echo json_encode([
    'ok' => true,
    'counts' => [
        'present' => $present,
        'late' => $late,
        'enrolled' => $enrolled,
    ],
    'items' => $items,
]);
