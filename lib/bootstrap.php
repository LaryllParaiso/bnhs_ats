<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

if (defined('APP_TIMEZONE')) {
    date_default_timezone_set(APP_TIMEZONE);
}

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');

$isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $cookieParams['path'] ?? '/',
    'domain' => $cookieParams['domain'] ?? '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

if (!isset($_SESSION['__initiated'])) {
    session_regenerate_id(true);
    $_SESSION['__initiated'] = time();
}

if (isset($_SESSION['teacher_id'])) {
    $now = time();
    $last = (int)($_SESSION['last_activity'] ?? $now);

    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $timeoutExemptScripts = [
        'attendance_day_scanner.php',
        'attendance_session.php',
        'attendance_scan.php',
        'attendance_day_feed.php',
        'attendance_feed.php',
    ];
    $skipTimeout = in_array($script, $timeoutExemptScripts, true);

    if (!$skipTimeout && (($now - $last) > AUTH_SESSION_TIMEOUT_SECONDS)) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $xhr = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        $wantsJson = str_contains($accept, 'application/json') || $xhr === 'xmlhttprequest' || str_contains($contentType, 'application/json');

        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'Session timed out. Please login again.']);
            exit;
        }

        header('Location: ' . rtrim(APP_BASE_URL, '/') . '/login.php?timeout=1');
        exit;
    }

    $_SESSION['last_activity'] = $now;
}
