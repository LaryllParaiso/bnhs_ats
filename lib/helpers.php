<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim(APP_BASE_URL, '/');
    $path = ltrim($path, '/');
    return $path === '' ? $base . '/' : $base . '/' . $path;
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}
