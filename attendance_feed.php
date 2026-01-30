<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$teacherId = (int)$_SESSION['teacher_id'];
$sessionId = (int)($_GET['session_id'] ?? 0);

$q = trim((string)($_GET['q'] ?? ''));
$page = (int)($_GET['page'] ?? 1);
$perPage = (int)($_GET['per_page'] ?? 50);

if ($page < 1) {
    $page = 1;
}
if ($perPage < 5) {
    $perPage = 5;
}
if ($perPage > 200) {
    $perPage = 200;
}

$offset = ($page - 1) * $perPage;

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
try {
    $ok = $stmt->execute([':schedule_id' => $scheduleId]);
    if (!$ok) {
        echo json_encode(['ok' => false, 'message' => 'Failed to load scans']);
        exit;
    }
    $row = $stmt->fetch();
    $enrolled = (int)(is_array($row) ? ($row['cnt'] ?? 0) : 0);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => 'Failed to load scans']);
    exit;
}

$where =
    'WHERE a.schedule_id = :schedule_id
      AND a.teacher_id = :teacher_id
      AND a.date = :date
      AND a.status IN ("Present","Late")';

$params = [
    ':schedule_id' => $scheduleId,
    ':teacher_id' => $teacherId,
    ':date' => $sessionDate,
];

if ($q !== '') {
    $where .= ' AND (LOWER(st.first_name) LIKE :q1 OR LOWER(st.last_name) LIKE :q2 OR LOWER(CONCAT(st.first_name, \' \', st.last_name)) LIKE :q3 OR LOWER(st.lrn) LIKE :q4)';
    $like = '%' . strtolower($q) . '%';
    $params[':q1'] = $like;
    $params[':q2'] = $like;
    $params[':q3'] = $like;
    $params[':q4'] = $like;
}

$stmt = $pdo->prepare(
    'SELECT COUNT(*) AS cnt
     FROM attendance a
     JOIN students st ON st.student_id = a.student_id
     ' . $where
);
try {
    $ok = $stmt->execute($params);
    if (!$ok) {
        echo json_encode(['ok' => false, 'message' => 'Failed to load scans']);
        exit;
    }
    $row = $stmt->fetch();
    $totalItems = (int)(is_array($row) ? ($row['cnt'] ?? 0) : 0);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => 'Failed to load scans']);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT a.time_scanned, a.status, st.lrn, st.first_name, st.last_name, st.suffix
     FROM attendance a
     JOIN students st ON st.student_id = a.student_id
     ' . $where . '
     ORDER BY a.time_scanned DESC
     LIMIT ' . $offset . ', ' . $perPage
);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
try {
    $ok = $stmt->execute();
    if (!$ok) {
        echo json_encode(['ok' => false, 'message' => 'Failed to load scans']);
        exit;
    }
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => 'Failed to load scans']);
    exit;
}

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
try {
    $ok = $stmt->execute([':schedule_id' => $scheduleId, ':teacher_id' => $teacherId, ':date' => $sessionDate]);
    if (!$ok) {
        echo json_encode(['ok' => false, 'message' => 'Failed to load scans']);
        exit;
    }
    $agg = $stmt->fetch();
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => 'Failed to load scans']);
    exit;
}

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
    'pagination' => [
        'q' => $q,
        'page' => $page,
        'per_page' => $perPage,
        'total_items' => $totalItems,
        'total_pages' => $perPage > 0 ? (int)ceil($totalItems / $perPage) : 1,
    ],
    'items' => $items,
]);
