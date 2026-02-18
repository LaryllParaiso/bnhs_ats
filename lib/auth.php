<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function is_logged_in(): bool
{
    return isset($_SESSION['teacher_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $xhr = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        $wantsJson = str_contains($accept, 'application/json') || $xhr === 'xmlhttprequest' || str_contains($contentType, 'application/json');

        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'Not authenticated. Please login again.']);
            exit;
        }

        redirect('login.php');
    }
}

function logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }

    session_destroy();
}

function password_is_strong(string $password): bool
{
    if (strlen($password) < 8) {
        return false;
    }

    $hasUpper = preg_match('/[A-Z]/', $password) === 1;
    $hasLower = preg_match('/[a-z]/', $password) === 1;
    $hasDigit = preg_match('/\d/', $password) === 1;
    $hasSpecial = preg_match('/[^A-Za-z0-9]/', $password) === 1;

    return $hasUpper && $hasLower && $hasDigit && $hasSpecial;
}

function is_super_admin(): bool
{
    return (string)($_SESSION['role'] ?? '') === 'Super Admin';
}

function is_admin(): bool
{
    return in_array((string)($_SESSION['role'] ?? ''), ['Admin', 'Super Admin'], true);
}

function is_admin_only(): bool
{
    return (string)($_SESSION['role'] ?? '') === 'Admin';
}

function teacher_grade_levels_taught(int $teacherId): array
{
    $csv = (string)($_SESSION['grade_levels_taught'] ?? '');

    if ($csv === '' && $teacherId > 0) {
        try {
            $pdo = db();
            $stmt = $pdo->prepare('SELECT grade_levels_taught FROM teachers WHERE teacher_id = :id LIMIT 1');
            $stmt->execute([':id' => $teacherId]);
            $row = $stmt->fetch();
            $csv = (string)($row['grade_levels_taught'] ?? '');
            $_SESSION['grade_levels_taught'] = $csv;
        } catch (Throwable $e) {
            $csv = '';
        }
    }

    $grades = [];
    foreach (explode(',', $csv) as $p) {
        $p = trim($p);
        if ($p === '' || !ctype_digit($p)) {
            continue;
        }
        $g = (int)$p;
        if ($g >= 7 && $g <= 12) {
            $grades[] = $g;
        }
    }

    $grades = array_values(array_unique($grades));
    sort($grades);
    return $grades;
}
