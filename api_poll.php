<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

if (!is_logged_in()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Not authenticated.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$type = (string)($_GET['type'] ?? '');
$pdo = db();
$teacherId = (int)$_SESSION['teacher_id'];
$isAdmin = is_admin();

if ($type === 'dashboard') {
    $from = trim((string)($_GET['from'] ?? ''));
    $to = trim((string)($_GET['to'] ?? ''));
    $scheduleId = (int)($_GET['schedule_id'] ?? 0);
    $grade = trim((string)($_GET['grade'] ?? ''));
    $section = trim((string)($_GET['section'] ?? ''));
    $status = trim((string)($_GET['status'] ?? ''));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 50;

    $today = new DateTimeImmutable('today');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $from = $today->sub(new DateInterval('P6D'))->format('Y-m-d');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $to = $today->format('Y-m-d');
    }

    $params = [':from' => $from, ':to' => $to];
    $where = ['a.date BETWEEN :from AND :to'];

    if (!$isAdmin) {
        $where[] = 'a.teacher_id = :teacher_id';
        $params[':teacher_id'] = $teacherId;
    }
    if ($scheduleId > 0) {
        $where[] = 'a.schedule_id = :schedule_id';
        $params[':schedule_id'] = $scheduleId;
    }
    if ($grade !== '' && ctype_digit($grade)) {
        $where[] = 'st.grade_level = :grade';
        $params[':grade'] = (int)$grade;
    }
    if ($section !== '') {
        $where[] = 'st.section = :section';
        $params[':section'] = $section;
    }
    if (in_array($status, ['Present', 'Late', 'Absent'], true)) {
        $where[] = 'a.status = :status';
        $params[':status'] = $status;
    }

    $sqlWhere = ' WHERE ' . implode(' AND ', $where);

    $aggSql = 'SELECT
        SUM(CASE WHEN a.status = "Present" THEN 1 ELSE 0 END) AS present_count,
        SUM(CASE WHEN a.status = "Late" THEN 1 ELSE 0 END) AS late_count,
        SUM(CASE WHEN a.status = "Absent" THEN 1 ELSE 0 END) AS absent_count,
        COUNT(*) AS total_count
     FROM attendance a
     JOIN students st ON st.student_id = a.student_id' . $sqlWhere;

    $stmt = $pdo->prepare($aggSql);
    $stmt->execute($params);
    $agg = $stmt->fetch();

    $countSql = 'SELECT COUNT(*) AS cnt FROM attendance a JOIN students st ON st.student_id = a.student_id JOIN schedules sch ON sch.schedule_id = a.schedule_id' . $sqlWhere;
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $rowTotal = (int)(($stmt->fetch()['cnt'] ?? 0));
    $pg = paginate($rowTotal, $page, $perPage);

    $sql = 'SELECT a.attendance_id, a.date, a.time_scanned, a.status, a.remarks,
            st.student_id, st.lrn, st.first_name, st.last_name, st.suffix, st.grade_level, st.section,
            sch.subject_name, sch.day_of_week, sch.start_time, sch.end_time
     FROM attendance a
     JOIN students st ON st.student_id = a.student_id
     JOIN schedules sch ON sch.schedule_id = a.schedule_id' .
        $sqlWhere .
        ' ORDER BY a.date DESC, sch.subject_name, st.last_name, st.first_name, st.lrn' .
        ' LIMIT ' . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset'];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $tableRows = [];
    foreach ($rows as $r) {
        $suffix = trim((string)($r['suffix'] ?? ''));
        $name = trim((string)$r['last_name'] . ', ' . (string)$r['first_name']);
        if ($suffix !== '') $name .= ' ' . $suffix;
        $sub = (string)$r['subject_name'];
        $timeWindow = (string)$r['start_time'] . '-' . (string)$r['end_time'];

        $tableRows[] = [
            'date' => (string)$r['date'],
            'subject' => $sub . ' (' . (string)$r['day_of_week'] . ' ' . $timeWindow . ')',
            'lrn' => (string)$r['lrn'],
            'name' => $name,
            'student_id' => (int)$r['student_id'],
            'grade_section' => (string)$r['grade_level'] . '-' . (string)$r['section'],
            'status' => (string)$r['status'],
            'time' => (string)($r['time_scanned'] ?? ''),
            'remarks' => (string)($r['remarks'] ?? ''),
        ];
    }

    echo json_encode([
        'ok' => true,
        'metrics' => [
            'present' => (int)($agg['present_count'] ?? 0),
            'late' => (int)($agg['late_count'] ?? 0),
            'absent' => (int)($agg['absent_count'] ?? 0),
            'total' => (int)($agg['total_count'] ?? 0),
        ],
        'rows' => $tableRows,
        'pagination' => [
            'from' => (int)$pg['from'],
            'to' => (int)$pg['to'],
            'total' => (int)$pg['total'],
            'page' => (int)$pg['page'],
            'pages' => (int)$pg['pages'],
        ],
    ]);
    exit;
}

if ($type === 'students') {
    $taughtGrades = teacher_grade_levels_taught($teacherId);
    $q = trim((string)($_GET['q'] ?? ''));
    $grade = trim((string)($_GET['grade'] ?? ''));
    $section = trim((string)($_GET['section'] ?? ''));
    $status = trim((string)($_GET['status'] ?? ''));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 25;

    $params = [];
    $where = [];

    if (!$isAdmin) {
        if ($taughtGrades) {
            $gradePlaceholders = [];
            foreach ($taughtGrades as $i => $g) {
                $ph = ':tg' . $i;
                $gradePlaceholders[] = $ph;
                $params[$ph] = $g;
            }
            $where[] = 'grade_level IN (' . implode(',', $gradePlaceholders) . ')';
        } else {
            $params[':teacher_id'] = $teacherId;
            $where[] = 'EXISTS (SELECT 1 FROM student_schedules ss JOIN schedules sch ON sch.schedule_id = ss.schedule_id WHERE ss.student_id = students.student_id AND ss.status = "Active" AND sch.teacher_id = :teacher_id AND sch.status != "Archived")';
        }
    }

    if ($q !== '') {
        $where[] = '(lrn LIKE :q1 OR first_name LIKE :q2 OR last_name LIKE :q3)';
        $qLike = '%' . $q . '%';
        $params[':q1'] = $qLike;
        $params[':q2'] = $qLike;
        $params[':q3'] = $qLike;
    }
    if ($grade !== '' && ctype_digit($grade)) {
        $where[] = 'grade_level = :grade';
        $params[':grade'] = (int)$grade;
    }
    if ($section !== '') {
        $where[] = 'section = :section';
        $params[':section'] = $section;
    }
    if (in_array($status, ['Active', 'Inactive', 'Graduated'], true)) {
        $where[] = 'status = :status';
        $params[':status'] = $status;
    }

    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $countSql = 'SELECT COUNT(*) AS cnt FROM students' . $whereSql;
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = (int)(($stmt->fetch()['cnt'] ?? 0));

    $aggSql = 'SELECT
        SUM(CASE WHEN status = "Active" THEN 1 ELSE 0 END) AS active_count,
        SUM(CASE WHEN status = "Inactive" THEN 1 ELSE 0 END) AS inactive_count,
        COUNT(*) AS total_count
     FROM students' . $whereSql;
    $stmt = $pdo->prepare($aggSql);
    $stmt->execute($params);
    $agg = $stmt->fetch() ?: [];

    $pg = paginate($total, $page, $perPage);

    $sql = 'SELECT * FROM students' . $whereSql . ' ORDER BY last_name, first_name, lrn LIMIT ' . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset'];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();

    $attendanceCounts = [];
    if ($students) {
        $studentIds = array_map(fn($s) => (int)$s['student_id'], $students);
        $inPh = implode(',', array_fill(0, count($studentIds), '?'));
        $attSql = "SELECT student_id,
                          SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) AS total_absent,
                          SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) AS total_late
                   FROM attendance WHERE student_id IN ($inPh) GROUP BY student_id";
        $attStmt = $pdo->prepare($attSql);
        $attStmt->execute($studentIds);
        foreach ($attStmt->fetchAll() as $row) {
            $attendanceCounts[(int)$row['student_id']] = $row;
        }
    }

    $baseUrl = rtrim(APP_BASE_URL, '/');
    $tableRows = [];
    foreach ($students as $s) {
        $suffix = trim((string)($s['suffix'] ?? ''));
        $name = trim((string)$s['last_name'] . ', ' . (string)$s['first_name']);
        if ($suffix !== '') $name .= ' ' . $suffix;
        $sid = (int)$s['student_id'];

        $tableRows[] = [
            'student_id' => $sid,
            'lrn' => (string)$s['lrn'],
            'name' => $name,
            'grade_section' => (string)$s['grade_level'] . '-' . (string)$s['section'],
            'sex' => (string)$s['sex'],
            'status' => (string)$s['status'],
            'total_absent' => (int)($attendanceCounts[$sid]['total_absent'] ?? 0),
            'total_late' => (int)($attendanceCounts[$sid]['total_late'] ?? 0),
            'view_url' => $baseUrl . '/student_view.php?id=' . $sid,
            'edit_url' => $baseUrl . '/student_form.php?id=' . $sid,
            'archive_url' => $baseUrl . '/student_archive.php',
            'delete_url' => $baseUrl . '/student_delete.php',
        ];
    }

    echo json_encode([
        'ok' => true,
        'metrics' => [
            'active' => (int)($agg['active_count'] ?? 0),
            'inactive' => (int)($agg['inactive_count'] ?? 0),
            'total' => (int)($agg['total_count'] ?? 0),
        ],
        'rows' => $tableRows,
        'pagination' => [
            'from' => (int)$pg['from'],
            'to' => (int)$pg['to'],
            'total' => (int)$pg['total'],
            'page' => (int)$pg['page'],
            'pages' => (int)$pg['pages'],
        ],
    ]);
    exit;
}

if ($type === 'approvals') {
    if (!$isAdmin) {
        echo json_encode(['ok' => false, 'message' => 'Forbidden.']);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT teacher_id, employee_id, first_name, last_name, sex, email, department, created_at
         FROM teachers
         WHERE role = "Teacher" AND approval_status = "Pending"
         ORDER BY created_at ASC, teacher_id ASC'
    );
    $stmt->execute();
    $pending = $stmt->fetchAll();

    $baseUrl = rtrim(APP_BASE_URL, '/');
    $rows = [];
    foreach ($pending as $t) {
        $rows[] = [
            'teacher_id' => (int)$t['teacher_id'],
            'employee_id' => (string)$t['employee_id'],
            'name' => trim((string)$t['first_name'] . ' ' . (string)$t['last_name']),
            'sex' => (string)$t['sex'],
            'email' => (string)$t['email'],
            'department' => (string)($t['department'] ?? ''),
            'created_at' => (string)($t['created_at'] ?? ''),
            'action_url' => $baseUrl . '/teacher_approvals.php',
        ];
    }

    echo json_encode([
        'ok' => true,
        'count' => count($rows),
        'rows' => $rows,
    ]);
    exit;
}

if ($type === 'notifications') {
    $role = (string)($_SESSION['role'] ?? '');
    $result = ['ok' => true, 'role' => $role, 'items' => [], 'badge' => 0];

    if ($role === 'Super Admin') {
        // Count pending change requests
        $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM change_requests WHERE status = "Pending"');
        $stmt->execute();
        $pendingCr = (int)($stmt->fetch()['cnt'] ?? 0);

        // Count pending teacher approvals
        $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM teachers WHERE approval_status = "Pending"');
        $stmt->execute();
        $pendingTa = (int)($stmt->fetch()['cnt'] ?? 0);

        $result['badge'] = $pendingCr + $pendingTa;
        $items = [];

        if ($pendingCr > 0) {
            $items[] = [
                'type' => 'pending_cr',
                'message' => $pendingCr . ' pending change request(s) awaiting review.',
                'url' => rtrim(APP_BASE_URL, '/') . '/approval_queue.php',
                'badge' => 'warning',
            ];
        }
        if ($pendingTa > 0) {
            $items[] = [
                'type' => 'pending_ta',
                'message' => $pendingTa . ' pending teacher registration(s).',
                'url' => rtrim(APP_BASE_URL, '/') . '/teacher_approvals.php',
                'badge' => 'info',
            ];
        }
        $result['items'] = $items;

    } elseif ($role === 'Admin') {
        // Count pending teacher approvals
        $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM teachers WHERE approval_status = "Pending"');
        $stmt->execute();
        $pendingTa = (int)($stmt->fetch()['cnt'] ?? 0);

        $result['badge'] = $pendingTa;
        if ($pendingTa > 0) {
            $result['items'][] = [
                'type' => 'pending_ta',
                'message' => $pendingTa . ' pending teacher registration(s).',
                'url' => rtrim(APP_BASE_URL, '/') . '/teacher_approvals.php',
                'badge' => 'info',
            ];
        }

    } else {
        // Teacher: show recent change request status updates
        $lastSeen = (string)($_SESSION['notif_seen_at'] ?? '2000-01-01 00:00:00');

        // Count unseen resolved requests (Approved/Rejected after last seen)
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS cnt FROM change_requests
             WHERE teacher_id = :id AND status IN ("Approved","Rejected") AND reviewed_at > :seen'
        );
        $stmt->execute([':id' => $teacherId, ':seen' => $lastSeen]);
        $unseenCount = (int)($stmt->fetch()['cnt'] ?? 0);

        // Also count pending
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS cnt FROM change_requests WHERE teacher_id = :id AND status = "Pending"'
        );
        $stmt->execute([':id' => $teacherId]);
        $pendingCount = (int)($stmt->fetch()['cnt'] ?? 0);

        $result['badge'] = $unseenCount;

        // Fetch recent items (last 10)
        $stmt = $pdo->prepare(
            'SELECT request_id, request_type, status, reason, created_at, reviewed_at
             FROM change_requests
             WHERE teacher_id = :id
             ORDER BY created_at DESC
             LIMIT 10'
        );
        $stmt->execute([':id' => $teacherId]);
        $reqs = $stmt->fetchAll();

        $baseUrl = rtrim(APP_BASE_URL, '/');
        foreach ($reqs as $cr) {
            $crStatus = (string)$cr['status'];
            $crType = match((string)$cr['request_type']) {
                'password' => 'Password Change',
                'account_settings' => 'Account Settings',
                'schedule_edit' => 'Schedule Edit',
                'schedule_deactivate' => 'Schedule Status',
                'schedule_archive' => 'Schedule Archive',
                default => ucfirst(str_replace('_', ' ', (string)$cr['request_type'])),
            };
            $badge = match($crStatus) {
                'Pending' => 'warning',
                'Approved' => 'success',
                'Rejected' => 'danger',
                default => 'secondary',
            };
            $msg = $crType . ': ' . $crStatus;
            if ($crStatus !== 'Pending' && (string)($cr['reason'] ?? '') !== '') {
                $msg .= ' — ' . (string)$cr['reason'];
            }

            $isNew = $crStatus !== 'Pending' && (string)($cr['reviewed_at'] ?? '') > $lastSeen;

            $result['items'][] = [
                'type' => 'cr_' . strtolower($crStatus),
                'message' => $msg,
                'time' => (string)($cr['reviewed_at'] ?? $cr['created_at']),
                'badge' => $badge,
                'is_new' => $isNew,
                'url' => $baseUrl . '/settings.php',
            ];
        }

        if ($pendingCount > 0) {
            $result['pending_count'] = $pendingCount;
        }
    }

    echo json_encode($result);
    exit;
}

if ($type === 'notifications_seen') {
    // Mark notifications as seen (update session timestamp)
    $nowSeen = date('Y-m-d H:i:s');
    $_SESSION['notif_seen_at'] = $nowSeen;

    if ($teacherId > 0) {
        try {
            $stmt = $pdo->prepare('UPDATE teachers SET notif_seen_at = :seen WHERE teacher_id = :id');
            $stmt->execute([':seen' => $nowSeen, ':id' => $teacherId]);
        } catch (Throwable $e) {
            // swallow errors to avoid breaking polling if DB update fails
        }
    }
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'message' => 'Unknown type.']);
