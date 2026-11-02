<?php

declare(strict_types=1);

/**
 * Antispam y controles de la petición: método, tamaño, origen, honeypot,
 * trampa de tiempo y límite de peticiones por IP.
 */

/**
 * Comprueba el método HTTP.
 */
function afg_require_post(): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method !== 'POST') {
        if (!headers_sent()) {
            header('Allow: POST');
        }
        afg_fail(405, 'method_not_allowed');
    }
}

/**
 * Normaliza un origen (esquema + host + puerto) para compararlo.
 */
function afg_normalize_origin(string $origin): string
{
    $origin = strtolower(trim($origin));
    $origin = rtrim($origin, '/');

    return (string) preg_replace('#^(\w+://[^/]+).*$#', '$1', $origin);
}

/**
 * Valida Origin (o, en su defecto, Referer) contra la lista permitida.
 */
function afg_origin_allowed(array $config): bool
{
    $allowed = afg_cfg($config, 'security.allowed_origins', []);
    if (!is_array($allowed) || $allowed === []) {
        return true;
    }

    $normalized = [];
    foreach ($allowed as $origin) {
        if (is_string($origin) && $origin !== '') {
            $normalized[] = afg_normalize_origin($origin);
        }
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (is_string($origin) && $origin !== '') {
        return in_array(afg_normalize_origin($origin), $normalized, true);
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (is_string($referer) && $referer !== '') {
        return in_array(afg_normalize_origin($referer), $normalized, true);
    }

    // Sin Origin ni Referer no se puede distinguir un cliente legítimo de un bot;
    // se permite y se registra (los demás controles siguen activos).
    return true;
}

/**
 * Trampa de tiempo: el formulario debe tardar al menos minFillMs en rellenarse.
 * Los POST sin JavaScript no envían el campo, así que se omite la comprobación.
 *
 * @param array<string, mixed> $input
 */
function afg_timing_ok(array $input, array $rules): bool
{
    $raw = $input['t'] ?? '';
    if (!is_scalar($raw) || trim((string) $raw) === '') {
        return true;
    }

    if (!is_numeric($raw)) {
        return false;
    }

    $renderedAt = (float) $raw;
    if ($renderedAt <= 0) {
        return false;
    }

    $minFillMs = (int) ($rules['minFillMs'] ?? 1500);
    $elapsedMs = (microtime(true) * 1000) - $renderedAt;

    return $elapsedMs >= $minFillMs;
}

/**
 * Cuenta (y opcionalmente registra) una petición dentro de una ventana.
 *
 * @return array{allowed: bool, retry: int}
 */
function afg_rate_touch(string $name, int $window, int $max, bool $record): array
{
    $dir = afg_storage_path('rate');
    if (!afg_ensure_dir($dir)) {
        return ['allowed' => true, 'retry' => 0];
    }

    $path = $dir . '/' . sha1($name) . '.json';
    $handle = @fopen($path, 'c+');
    if ($handle === false) {
        return ['allowed' => true, 'retry' => 0];
    }

    $result = ['allowed' => true, 'retry' => 0];

    if (flock($handle, LOCK_EX)) {
        $raw = stream_get_contents($handle);
        $decoded = $raw !== false && $raw !== '' ? json_decode($raw, true) : [];
        $hits = [];

        if (is_array($decoded)) {
            foreach ($decoded as $hit) {
                if (is_int($hit) || is_float($hit)) {
                    $hits[] = (int) $hit;
                }
            }
        }

        $now = time();
        $hits = array_values(array_filter($hits, static fn (int $hit): bool => $hit > $now - $window));

        if (count($hits) >= $max) {
            $oldest = $hits === [] ? $now : min($hits);
            $result = ['allowed' => false, 'retry' => max(1, $window - ($now - $oldest))];
        } elseif ($record) {
            $hits[] = $now;
        }

        $encoded = json_encode($hits);
        if ($encoded !== false) {
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, $encoded);
            fflush($handle);
        }

        flock($handle, LOCK_UN);
    }

    fclose($handle);

    return $result;
}

/**
 * Comprueba los dos límites configurados (por IP y global) sin registrar.
 *
 * @return array{allowed: bool, retry: int}
 */
function afg_rate_peek(array $config, string $ipHash): array
{
    $window = (int) afg_cfg($config, 'security.rate_limit.window', 600);
    $max = (int) afg_cfg($config, 'security.rate_limit.max', 5);
    $globalWindow = (int) afg_cfg($config, 'security.rate_limit.global_window', 3600);
    $globalMax = (int) afg_cfg($config, 'security.rate_limit.global_max', 30);

    $perIp = afg_rate_touch('ip:' . $ipHash, $window, $max, false);
    if (!$perIp['allowed']) {
        return $perIp;
    }

    return afg_rate_touch('global', $globalWindow, $globalMax, false);
}

/**
 * Registra una petición válida en los dos límites.
 */
function afg_rate_hit(array $config, string $ipHash): void
{
    $window = (int) afg_cfg($config, 'security.rate_limit.window', 600);
    $max = (int) afg_cfg($config, 'security.rate_limit.max', 5);
    $globalWindow = (int) afg_cfg($config, 'security.rate_limit.global_window', 3600);
    $globalMax = (int) afg_cfg($config, 'security.rate_limit.global_max', 30);

    afg_rate_touch('ip:' . $ipHash, $window, $max, true);
    afg_rate_touch('global', $globalWindow, $globalMax, true);
}
