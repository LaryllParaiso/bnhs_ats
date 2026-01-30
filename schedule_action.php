<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('schedules.php');
}

$teacherId = (int)$_SESSION['teacher_id'];
$isAdmin = is_admin();
$id = (int)($_POST['id'] ?? 0);
$action = (string)($_POST['action'] ?? '');

if ($id <= 0) {
    redirect('schedules.php');
}

$pdo = db();

if ($isAdmin) {
    $stmt = $pdo->prepare('SELECT schedule_id, status FROM schedules WHERE schedule_id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $schedule = $stmt->fetch();
} else {
    $stmt = $pdo->prepare('SELECT schedule_id, status FROM schedules WHERE schedule_id = :id AND teacher_id = :teacher_id LIMIT 1');
    $stmt->execute([':id' => $id, ':teacher_id' => $teacherId]);
    $schedule = $stmt->fetch();
}

if (!$schedule) {
    redirect('schedules.php');
}

if ($action === 'toggle_status') {
    $newStatus = $schedule['status'] === 'Active' ? 'Inactive' : 'Active';

    if ($schedule['status'] === 'Archived') {
        redirect('schedules.php');
    }

    if ($isAdmin) {
        $stmt = $pdo->prepare('UPDATE schedules SET status = :status WHERE schedule_id = :id');
        $stmt->execute([':status' => $newStatus, ':id' => $id]);
    } else {
        $stmt = $pdo->prepare('UPDATE schedules SET status = :status WHERE schedule_id = :id AND teacher_id = :teacher_id');
        $stmt->execute([':status' => $newStatus, ':id' => $id, ':teacher_id' => $teacherId]);
    }

    redirect('schedules.php');
}

if ($action === 'archive') {
    if ($isAdmin) {
        $stmt = $pdo->prepare('UPDATE schedules SET status = "Archived" WHERE schedule_id = :id');
        $stmt->execute([':id' => $id]);
    } else {
        $stmt = $pdo->prepare('UPDATE schedules SET status = "Archived" WHERE schedule_id = :id AND teacher_id = :teacher_id');
        $stmt->execute([':id' => $id, ':teacher_id' => $teacherId]);
    }

    redirect('schedules.php');
}

redirect('schedules.php');
