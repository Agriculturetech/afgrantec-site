<?php

declare(strict_types=1);

/**
 * Router para el servidor PHP de desarrollo (`php -S 127.0.0.1:8081`).
 *
 * El servidor de desarrollo de Astro no ejecuta PHP, así que Vite reenvía
 * /api/* a este proceso (ver astro.config.mjs).
 */

$root = dirname(__DIR__);
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = is_string($uri) ? rawurldecode($uri) : '/';

if (!str_starts_with($uri, '/api/')) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Solo /api/*';
    return true;
}

$relative = substr($uri, strlen('/api/'));
$blocked = ['config.php', 'config.example.php', 'rules.json'];

if ($relative === '' || in_array($relative, $blocked, true)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No encontrado';
    return true;
}

$path = realpath($root . '/api/' . $relative);
$base = realpath($root . '/api');

if ($path === false || $base === false || !str_starts_with($path, $base) || !is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No encontrado';
    return true;
}

if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No encontrado';
    return true;
}

require $path;

return true;
