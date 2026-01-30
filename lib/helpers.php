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

function paginate(int $total, int $page, int $perPage): array
{
    $total = max(0, $total);
    $perPage = max(1, $perPage);
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;

    $from = $total === 0 ? 0 : ($offset + 1);
    $to = $total === 0 ? 0 : min($total, $offset + $perPage);

    return [
        'total' => $total,
        'per_page' => $perPage,
        'page' => $page,
        'pages' => $pages,
        'offset' => $offset,
        'from' => $from,
        'to' => $to,
    ];
}

function pagination_html(string $path, array $query, int $page, int $perPage, int $total): string
{
    $pg = paginate($total, $page, $perPage);
    $pages = (int)$pg['pages'];
    $page = (int)$pg['page'];

    if ($pages <= 1) {
        return '';
    }

    $query = array_filter($query, function ($v) {
        if (is_array($v)) {
            return false;
        }
        return $v !== null && $v !== '';
    });

    unset($query['page']);

    $mkUrl = function (int $p) use ($path, $query): string {
        $q = $query;
        $q['page'] = $p;
        $qs = http_build_query($q);
        return url($path . ($qs !== '' ? ('?' . $qs) : ''));
    };

    $item = function (string $label, ?string $href, bool $active = false, bool $disabled = false): string {
        $cls = 'page-item';
        if ($active) {
            $cls .= ' active';
        }
        if ($disabled) {
            $cls .= ' disabled';
        }
        $link = $href === null
            ? '<span class="page-link">' . $label . '</span>'
            : '<a class="page-link" href="' . h($href) . '">' . $label . '</a>';
        return '<li class="' . $cls . '">' . $link . '</li>';
    };

    $html = '<nav aria-label="Pagination"><ul class="pagination pagination-sm mb-0">';

    $html .= $item('&laquo;', $page > 1 ? $mkUrl(1) : null, false, $page <= 1);
    $html .= $item('&lsaquo;', $page > 1 ? $mkUrl($page - 1) : null, false, $page <= 1);

    $start = max(1, $page - 2);
    $end = min($pages, $page + 2);

    if ($start > 1) {
        $html .= $item('1', $mkUrl(1), $page === 1, false);
        if ($start > 2) {
            $html .= $item('…', null, false, true);
        }
    }

    for ($p = $start; $p <= $end; $p++) {
        $html .= $item((string)$p, $mkUrl($p), $p === $page, false);
    }

    if ($end < $pages) {
        if ($end < ($pages - 1)) {
            $html .= $item('…', null, false, true);
        }
        $html .= $item((string)$pages, $mkUrl($pages), $page === $pages, false);
    }

    $html .= $item('&rsaquo;', $page < $pages ? $mkUrl($page + 1) : null, false, $page >= $pages);
    $html .= $item('&raquo;', $page < $pages ? $mkUrl($pages) : null, false, $page >= $pages);

    $html .= '</ul></nav>';
    return $html;
}
