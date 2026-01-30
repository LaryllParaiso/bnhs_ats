<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$teacherId = (int)$_SESSION['teacher_id'];
$isAdmin = is_admin();

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$scheduleId = (int)($_GET['schedule_id'] ?? 0);

$today = new DateTimeImmutable('today');
$defaultFrom = $today->sub(new DateInterval('P29D'))->format('Y-m-d');
$defaultTo = $today->format('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = $defaultFrom;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = $defaultTo;
}

$pdo = db();

$params = [
    ':from' => $from,
    ':to' => $to,
];

$where = [
    'a.date BETWEEN :from AND :to',
];

if (!$isAdmin) {
    $where[] = 'a.teacher_id = :teacher_id';
    $params[':teacher_id'] = $teacherId;
}

if ($scheduleId > 0) {
    $where[] = 'a.schedule_id = :schedule_id';
    $params[':schedule_id'] = $scheduleId;
}

$sqlWhere = ' WHERE ' . implode(' AND ', $where);

$byScheduleSql =
    'SELECT sch.schedule_id, sch.subject_name, sch.grade_level, sch.section,
            SUM(CASE WHEN a.status = "Present" THEN 1 ELSE 0 END) AS present,
            SUM(CASE WHEN a.status = "Late" THEN 1 ELSE 0 END) AS late,
            SUM(CASE WHEN a.status = "Absent" THEN 1 ELSE 0 END) AS absent,
            COUNT(*) AS total,
            AVG(CASE WHEN a.status = "Late" AND a.time_scanned IS NOT NULL
                THEN TIMESTAMPDIFF(MINUTE, CONCAT(a.date, " ", sch.start_time), CONCAT(a.date, " ", a.time_scanned))
                ELSE NULL END) AS avg_minutes_late
     FROM attendance a
     JOIN schedules sch ON sch.schedule_id = a.schedule_id' .
    $sqlWhere .
    ' GROUP BY sch.schedule_id
      ORDER BY sch.subject_name, sch.grade_level, sch.section';

$stmt = $pdo->prepare($byScheduleSql);
$stmt->execute($params);
$bySchedule = $stmt->fetchAll();

$trendSql =
    'SELECT a.date,
            SUM(CASE WHEN a.status = "Present" THEN 1 ELSE 0 END) AS present,
            SUM(CASE WHEN a.status = "Late" THEN 1 ELSE 0 END) AS late,
            SUM(CASE WHEN a.status = "Absent" THEN 1 ELSE 0 END) AS absent,
            COUNT(*) AS total
     FROM attendance a' .
    $sqlWhere .
    ' GROUP BY a.date
      ORDER BY a.date';

$stmt = $pdo->prepare($trendSql);
$stmt->execute($params);
$trend = $stmt->fetchAll();

$outSchedules = [];
foreach ($bySchedule as $r) {
    $total = (int)($r['total'] ?? 0);
    $present = (int)($r['present'] ?? 0);
    $late = (int)($r['late'] ?? 0);
    $absent = (int)($r['absent'] ?? 0);

    $rate = $total > 0 ? round((($present + $late) / $total) * 100, 2) : 0.0;
    $avgLate = $r['avg_minutes_late'] !== null ? round((float)$r['avg_minutes_late'], 2) : null;

    $label = (string)$r['subject_name'] . ' (G' . (string)$r['grade_level'] . '-' . (string)$r['section'] . ')';

    $outSchedules[] = [
        'schedule_id' => (int)$r['schedule_id'],
        'label' => $label,
        'present' => $present,
        'late' => $late,
        'absent' => $absent,
        'total' => $total,
        'attendance_rate' => $rate,
        'avg_minutes_late' => $avgLate,
    ];
}

$outTrend = [];
foreach ($trend as $r) {
    $outTrend[] = [
        'date' => (string)$r['date'],
        'present' => (int)($r['present'] ?? 0),
        'late' => (int)($r['late'] ?? 0),
        'absent' => (int)($r['absent'] ?? 0),
        'total' => (int)($r['total'] ?? 0),
    ];
}

echo json_encode([
    'ok' => true,
    'from' => $from,
    'to' => $to,
    'by_schedule' => $outSchedules,
    'trend' => $outTrend,
]);
