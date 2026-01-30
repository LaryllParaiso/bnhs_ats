<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$teacherId = (int)$_SESSION['teacher_id'];

$raw = (string)file_get_contents('php://input');
$data = json_decode($raw, true);

$mode = trim((string)($data['mode'] ?? ''));
$sessionId = (int)($data['session_id'] ?? 0);
$qrText = trim((string)($data['qr_text'] ?? ''));

if ($qrText === '' || ($mode !== 'day' && $sessionId <= 0)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid request']);
    exit;
}

$pdo = db();

$lateThresholdMinutes = null;
$absentThresholdMinutes = null;
$lateThresholdEnabled = true;
$absentThresholdEnabled = true;

if ($mode === 'day') {
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

    $stmt = $pdo->prepare('SELECT status, late_threshold_minutes, absent_threshold_minutes, late_threshold_enabled, absent_threshold_enabled FROM attendance_day_scanning WHERE scan_date = CURDATE() LIMIT 1');
    $stmt->execute();
    $day = $stmt->fetch();
    if (!$day || (string)$day['status'] !== 'Active') {
        echo json_encode(['ok' => false, 'message' => 'Day scanning is not active']);
        exit;
    }

    $lateThresholdMinutes = (int)($day['late_threshold_minutes'] ?? 0);
    if ($lateThresholdMinutes < 0) {
        $lateThresholdMinutes = 0;
    }
    if ($lateThresholdMinutes > 240) {
        $lateThresholdMinutes = 240;
    }

    $absentThresholdMinutes = (int)($day['absent_threshold_minutes'] ?? 0);
    if ($absentThresholdMinutes < 0) {
        $absentThresholdMinutes = 0;
    }
    if ($absentThresholdMinutes > 480) {
        $absentThresholdMinutes = 480;
    }

    $lateThresholdEnabled = (int)($day['late_threshold_enabled'] ?? 1) === 1;
    $absentThresholdEnabled = (int)($day['absent_threshold_enabled'] ?? 1) === 1;
}

if ($mode !== 'day') {
    $stmt = $pdo->prepare(
        'SELECT session_id, teacher_id, schedule_id, session_date, status
         FROM attendance_sessions
         WHERE session_id = :session_id AND teacher_id = :teacher_id
         LIMIT 1'
    );
    $stmt->execute([':session_id' => $sessionId, ':teacher_id' => $teacherId]);
    $session = $stmt->fetch();

    if (!$session || (string)$session['status'] !== 'Active') {
        echo json_encode(['ok' => false, 'message' => 'Session not found or not active']);
        exit;
    }
}

$payload = json_decode($qrText, true);
if (!is_array($payload)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid QR payload']);
    exit;
}

$lrn = trim((string)($payload['lrn'] ?? ''));
$token = trim((string)($payload['token'] ?? ''));

if ($lrn === '' || $token === '') {
    echo json_encode(['ok' => false, 'message' => 'QR payload missing required fields']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM students WHERE lrn = :lrn LIMIT 1');
$stmt->execute([':lrn' => $lrn]);
$student = $stmt->fetch();

if (!$student) {
    echo json_encode(['ok' => false, 'message' => 'Student not found']);
    exit;
}

if ((string)$student['status'] !== 'Active') {
    echo json_encode(['ok' => false, 'message' => 'Student is not Active']);
    exit;
}

if (!hash_equals((string)$student['qr_token'], $token)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid QR token']);
    exit;
}

$suffix = trim((string)($student['suffix'] ?? ''));
$fullName = trim((string)$student['first_name'] . ' ' . (string)$student['last_name']);
if ($suffix !== '') {
    $fullName .= ', ' . $suffix;
}

$studentId = (int)$student['student_id'];

$scheduleId = 0;
$sessionDate = date('Y-m-d');
$scheduleTeacherId = $teacherId;
$scheduleLabel = '';

if ($mode === 'day') {
    $todayName = date('l');
    $grace = (int)ATTENDANCE_GRACE_MINUTES;
    $graceSeconds = $grace * 60;

    $stmt = $pdo->prepare(
        'SELECT sch.schedule_id, sch.teacher_id, sch.subject_name, sch.grade_level, sch.section, sch.start_time
         FROM schedules sch
         JOIN student_schedules ss ON ss.schedule_id = sch.schedule_id
         WHERE ss.student_id = :student_id
           AND ss.status = "Active"
           AND sch.status = "Active"
           AND sch.day_of_week = :today
           AND TIME(NOW()) BETWEEN SUBTIME(sch.start_time, SEC_TO_TIME(:grace_seconds_start)) AND ADDTIME(sch.end_time, SEC_TO_TIME(:grace_seconds_end))
         ORDER BY
           CASE WHEN sch.start_time <= TIME(NOW()) THEN 0 ELSE 1 END ASC,
           ABS(TIMESTAMPDIFF(SECOND, CONCAT(CURDATE(), " ", sch.start_time), NOW())) ASC
         LIMIT 2'
    );
    $stmt->execute([
        ':student_id' => $studentId,
        ':today' => $todayName,
        ':grace_seconds_start' => $graceSeconds,
        ':grace_seconds_end' => $graceSeconds,
    ]);
    $matches = $stmt->fetchAll();

    if (!$matches) {
        echo json_encode(['ok' => false, 'message' => 'No matching schedule found for this student at the current time']);
        exit;
    }

    $picked = $matches[0];
    $scheduleId = (int)$picked['schedule_id'];
    $scheduleTeacherId = (int)$picked['teacher_id'];
    $scheduleLabel = (string)$picked['subject_name'] . ' | G' . (string)$picked['grade_level'] . '-' . (string)$picked['section'];
} else {
    $scheduleId = (int)$session['schedule_id'];
    $sessionDate = (string)$session['session_date'];
}

if ($scheduleId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Schedule not found']);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT COUNT(*) AS cnt
     FROM student_schedules
     WHERE student_id = :student_id AND schedule_id = :schedule_id AND status = "Active"'
);
$stmt->execute([':student_id' => $studentId, ':schedule_id' => $scheduleId]);
$enrolled = (int)($stmt->fetch()['cnt'] ?? 0);

if ($enrolled <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Student is not enrolled in this schedule']);
    exit;
}

$stmt = $pdo->prepare('SELECT start_time FROM schedules WHERE schedule_id = :id LIMIT 1');
$stmt->execute([':id' => $scheduleId]);
$schedule = $stmt->fetch();

if (!$schedule) {
    echo json_encode(['ok' => false, 'message' => 'Schedule not found']);
    exit;
}

$grace = (int)ATTENDANCE_GRACE_MINUTES;
$startTs = strtotime($sessionDate . ' ' . (string)$schedule['start_time']);
$nowTs = strtotime($sessionDate . ' ' . date('H:i:s'));

if ($mode === 'day') {
    $thresholdMinutes = $lateThresholdMinutes !== null ? (int)$lateThresholdMinutes : $grace;
    $cutoffTs = $startTs + ($thresholdMinutes * 60);
    $absentCutoffTs = null;
    if ($absentThresholdEnabled && $absentThresholdMinutes !== null && (int)$absentThresholdMinutes > 0) {
        $absentCutoffTs = $startTs + ((int)$absentThresholdMinutes * 60);
    }

    if ($absentCutoffTs !== null && $nowTs > $absentCutoffTs) {
        $status = 'Absent';
    } else {
        if ($lateThresholdEnabled) {
            $status = ($nowTs > $cutoffTs) ? 'Late' : 'Present';
        } else {
            $status = 'Present';
        }
    }
} else {
    $status = ($nowTs > ($startTs + ($grace * 60))) ? 'Late' : 'Present';
}

if ($mode === 'day' && $status === 'Absent' && $absentThresholdEnabled) {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO attendance (student_id, schedule_id, teacher_id, date, time_scanned, status)
             VALUES (:student_id, :schedule_id, :teacher_id, :date, NULL, "Absent")'
        );
        $stmt->execute([
            ':student_id' => $studentId,
            ':schedule_id' => $scheduleId,
            ':teacher_id' => $scheduleTeacherId,
            ':date' => $sessionDate,
        ]);
    } catch (PDOException $e) {
        if (($e->getCode() ?? '') !== '23000') {
            echo json_encode(['ok' => false, 'message' => 'Failed to record attendance']);
            exit;
        }
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Marked Absent (cutoff passed)' . ($scheduleLabel !== '' ? (' (' . $scheduleLabel . ')') : ''),
        'status' => 'Absent',
        'schedule' => $scheduleLabel,
        'student' => [
            'lrn' => (string)$student['lrn'],
            'name' => $fullName,
        ],
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO attendance (student_id, schedule_id, teacher_id, date, time_scanned, status)
         VALUES (:student_id, :schedule_id, :teacher_id, :date, CURTIME(), :status)'
    );

    $stmt->execute([
        ':student_id' => $studentId,
        ':schedule_id' => $scheduleId,
        ':teacher_id' => $mode === 'day' ? $scheduleTeacherId : $teacherId,
        ':date' => $sessionDate,
        ':status' => $status,
    ]);
} catch (PDOException $e) {
    if (($e->getCode() ?? '') === '23000') {
        $stmt = $pdo->prepare(
            'SELECT attendance_id, time_scanned
             FROM attendance
             WHERE student_id = :student_id AND schedule_id = :schedule_id AND date = :date
             LIMIT 1'
        );
        $stmt->execute([':student_id' => $studentId, ':schedule_id' => $scheduleId, ':date' => $sessionDate]);
        $existingAttendance = $stmt->fetch();

        if ($existingAttendance && $existingAttendance['time_scanned'] === null) {
            $stmt = $pdo->prepare(
                'UPDATE attendance
                 SET teacher_id = :teacher_id,
                     time_scanned = CURTIME(),
                     status = :status,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE attendance_id = :attendance_id'
            );
            $stmt->execute([
                ':teacher_id' => $mode === 'day' ? $scheduleTeacherId : $teacherId,
                ':status' => $status,
                ':attendance_id' => (int)$existingAttendance['attendance_id'],
            ]);

            echo json_encode([
                'ok' => true,
                'message' => 'Recorded: ' . $status . ($scheduleLabel !== '' ? (' (' . $scheduleLabel . ')') : ''),
                'status' => $status,
                'schedule' => $scheduleLabel,
                'student' => [
                    'lrn' => (string)$student['lrn'],
                    'name' => $fullName,
                ],
            ]);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'already_scanned' => true,
            'message' => 'Already scanned today',
            'status' => $status,
            'schedule' => $scheduleLabel,
            'student' => [
                'lrn' => (string)$student['lrn'],
                'name' => $fullName,
            ],
        ]);
        exit;
    }

    echo json_encode(['ok' => false, 'message' => 'Failed to record attendance']);
    exit;
}
echo json_encode([
    'ok' => true,
    'message' => 'Recorded: ' . $status . ($scheduleLabel !== '' ? (' (' . $scheduleLabel . ')') : ''),
    'status' => $status,
    'schedule' => $scheduleLabel,
    'student' => [
        'lrn' => (string)$student['lrn'],
        'name' => $fullName,
    ],
]);
