<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$pdo = db();

$q = trim((string)($_GET['q'] ?? ''));
$page = (int)($_GET['page'] ?? 1);
$perPage = (int)($_GET['per_page'] ?? 20);

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

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS attendance_day_scanning (
        scan_date DATE PRIMARY KEY,
        status ENUM("Active","Ended") NOT NULL DEFAULT "Active",
        late_threshold_minutes INT NOT NULL DEFAULT 0,
        absent_threshold_minutes INT NOT NULL DEFAULT 0,
        late_threshold_enabled TINYINT(1) NOT NULL DEFAULT 1,
        absent_threshold_enabled TINYINT(1) NOT NULL DEFAULT 1,
        started_by INT NOT NULL,
        started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ended_at DATETIME NULL,
        CONSTRAINT fk_attendance_day_scanning_started_by FOREIGN KEY (started_by) REFERENCES teachers (teacher_id) ON DELETE RESTRICT,
        INDEX idx_attendance_day_scanning_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci'
);

try {
    $pdo->exec('ALTER TABLE attendance_day_scanning ADD COLUMN late_threshold_minutes INT NOT NULL DEFAULT 0');
} catch (Throwable $e) {
}

try {
    $pdo->exec('ALTER TABLE attendance_day_scanning ADD COLUMN absent_threshold_minutes INT NOT NULL DEFAULT 0');
} catch (Throwable $e) {
}

try {
    $pdo->exec('ALTER TABLE attendance_day_scanning ADD COLUMN late_threshold_enabled TINYINT(1) NOT NULL DEFAULT 1');
} catch (Throwable $e) {
}

try {
    $pdo->exec('ALTER TABLE attendance_day_scanning ADD COLUMN absent_threshold_enabled TINYINT(1) NOT NULL DEFAULT 1');
} catch (Throwable $e) {
}

$stmt = $pdo->prepare('SELECT status FROM attendance_day_scanning WHERE scan_date = CURDATE() LIMIT 1');
$stmt->execute();
$row = $stmt->fetch();

if (!$row || (string)$row['status'] !== 'Active') {
    echo json_encode(['ok' => false, 'message' => 'Day scanning is not active']);
    exit;
}

$stmt = $pdo->prepare('SELECT absent_threshold_minutes, absent_threshold_enabled FROM attendance_day_scanning WHERE scan_date = CURDATE() LIMIT 1');
$stmt->execute();
$cfg = $stmt->fetch() ?: [];

$absentThresholdMinutes = (int)($cfg['absent_threshold_minutes'] ?? 0);
$absentThresholdEnabled = (int)($cfg['absent_threshold_enabled'] ?? 1) === 1;
if ($absentThresholdMinutes < 0) {
    $absentThresholdMinutes = 0;
}
if ($absentThresholdMinutes > 480) {
    $absentThresholdMinutes = 480;
}

if ($absentThresholdEnabled && $absentThresholdMinutes > 0) {
    $todayDay = date('l');
    $absentSeconds = $absentThresholdMinutes * 60;

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO attendance (student_id, schedule_id, teacher_id, date, time_scanned, status)
             SELECT ss.student_id, sch.schedule_id, sch.teacher_id, CURDATE(), NULL, "Absent"
             FROM schedules sch
             JOIN student_schedules ss ON ss.schedule_id = sch.schedule_id
             JOIN students st ON st.student_id = ss.student_id
             WHERE sch.status = "Active"
               AND sch.day_of_week = :today
               AND ss.status = "Active"
               AND st.status = "Active"
               AND TIME(NOW()) > ADDTIME(sch.start_time, SEC_TO_TIME(:absent_seconds))
               AND NOT EXISTS (
                 SELECT 1
                 FROM attendance a
                 WHERE a.student_id = ss.student_id
                   AND a.schedule_id = sch.schedule_id
                   AND a.date = CURDATE()
               )'
        );

        $stmt->execute([':today' => $todayDay, ':absent_seconds' => $absentSeconds]);
    } catch (Throwable $e) {
    }
}

$stmt = $pdo->prepare(
    'SELECT
        SUM(CASE WHEN a.status = "Present" THEN 1 ELSE 0 END) AS present,
        SUM(CASE WHEN a.status = "Late" THEN 1 ELSE 0 END) AS late,
        COUNT(*) AS total
     FROM attendance a
     WHERE a.date = CURDATE()'
);
try {
    $ok = $stmt->execute();
    if (!$ok) {
        echo json_encode(['ok' => false, 'message' => 'Failed to load scans']);
        exit;
    }
    $counts = $stmt->fetch() ?: [];
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => 'Failed to load scans']);
    exit;
}

$where = 'WHERE a.date = CURDATE() AND a.time_scanned IS NOT NULL';
$params = [];
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
     JOIN schedules sch ON sch.schedule_id = a.schedule_id
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
    'SELECT a.time_scanned, a.status,
            st.lrn, st.first_name, st.last_name, st.suffix,
            sch.subject_name, sch.grade_level, sch.section
     FROM attendance a
     JOIN students st ON st.student_id = a.student_id
     JOIN schedules sch ON sch.schedule_id = a.schedule_id
     ' . $where . '
     ORDER BY a.time_scanned DESC, a.attendance_id DESC
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

$items = [];
foreach ($rows as $r) {
    $suffix = trim((string)($r['suffix'] ?? ''));
    $name = trim((string)($r['first_name'] ?? '') . ' ' . (string)($r['last_name'] ?? ''));
    if ($suffix !== '') {
        $name .= ', ' . $suffix;
    }

    $items[] = [
        'time_scanned' => (string)($r['time_scanned'] ?? ''),
        'lrn' => (string)($r['lrn'] ?? ''),
        'name' => $name,
        'status' => (string)($r['status'] ?? ''),
        'subject_name' => (string)($r['subject_name'] ?? ''),
        'grade_level' => (string)($r['grade_level'] ?? ''),
        'section' => (string)($r['section'] ?? ''),
    ];
}

echo json_encode([
    'ok' => true,
    'counts' => [
        'present' => (int)($counts['present'] ?? 0),
        'late' => (int)($counts['late'] ?? 0),
        'total' => (int)($counts['total'] ?? 0),
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
