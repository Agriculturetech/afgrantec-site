<?php

declare(strict_types=1);

/**
 * Núcleo del endpoint de contacto: configuración, respuestas, log y almacenamiento.
 *
 * Este archivo se incluye siempre primero desde contact.php.
 */

define('AFG_CONTACT', true);
define('AFG_ROOT', dirname(__DIR__));
define('AFG_STORAGE', AFG_ROOT . '/storage');

/**
 * Configuración del endpoint (api/config.php).
 *
 * @return array<string, mixed>
 */
function afg_config(): array
{
    static $config = null;

    if ($config === null) {
        $path = AFG_ROOT . '/config.php';
        if (!is_file($path)) {
            throw new RuntimeException('No existe api/config.php');
        }

        /** @var mixed $loaded */
        $loaded = require $path;
        if (!is_array($loaded)) {
            throw new RuntimeException('api/config.php debe devolver un array');
        }

        $config = $loaded;

        $tz = isset($config['timezone']) && is_string($config['timezone']) ? $config['timezone'] : 'America/Bogota';
        if (!@date_default_timezone_set($tz)) {
            date_default_timezone_set('America/Bogota');
        }
    }

    return $config;
}

/**
 * @param array<string, mixed> $config
 * @return mixed
 */
function afg_cfg(array $config, string $path, $default = null)
{
    $value = $config;
    foreach (explode('.', $path) as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) {
            return $default;
        }
        $value = $value[$key];
    }
    return $value;
}

function afg_is_dry_run(): bool
{
    return (bool) afg_cfg(afg_config(), 'dry_run', false);
}

/**
 * Indica si la petición espera JSON (fetch) o un POST de formulario sin JS.
 */
function afg_wants_json(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

    return stripos($accept, 'application/json') !== false
        || strtolower($requestedWith) === 'fetch';
}

/**
 * Responde JSON y termina la ejecución.
 *
 * @param array<string, mixed> $payload
 */
function afg_json(int $status, array $payload): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Respuesta de error uniforme: JSON para fetch, redirección para formularios sin JS.
 *
 * @param array<string, string> $errors
 */
function afg_fail(int $status, string $error, array $errors = []): void
{
    if (afg_wants_json()) {
        $payload = ['ok' => false, 'error' => $error];
        if ($errors !== []) {
            $payload['errors'] = $errors;
        }
        afg_json($status, $payload);
    }

    afg_redirect($status === 200 ? 'ok' : 'error');
}

function afg_redirect(string $estado): void
{
    if (!headers_sent()) {
        $target = $estado === 'ok' ? '/?estado=ok#contacto' : '/?estado=error#contacto';
        header('Location: ' . $target, true, 303);
    }
    exit;
}

function afg_storage_path(string $name): string
{
    return AFG_STORAGE . '/' . ltrim($name, '/');
}

/**
 * Crea un directorio de almacenamiento si no existe.
 */
function afg_ensure_dir(string $path): bool
{
    if (is_dir($path)) {
        return true;
    }

    // El .htaccess de cada carpeta impide el acceso HTTP directo.
    return @mkdir($path, 0755, true) || is_dir($path);
}

/**
 * Registra un evento sin datos personales. Nunca debe romper la petición.
 *
 * @param array<string, mixed> $context
 */
function afg_log(string $level, string $event, array $context = []): void
{
    $dir = afg_storage_path('logs');
    if (!afg_ensure_dir($dir)) {
        return;
    }

    $line = json_encode([
        'ts' => date('c'),
        'level' => $level,
        'event' => $event,
        'context' => $context,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($line === false) {
        return;
    }

    @file_put_contents(
        $dir . '/' . date('Y-m') . '.log',
        $line . "\n",
        FILE_APPEND | LOCK_EX
    );
}

/**
 * Hash de IP con sal (nunca se guarda la IP cruda).
 */
function afg_ip_hash(string $ip): string
{
    $salt = (string) afg_cfg(afg_config(), 'security.salt', '');

    return hash('sha256', $salt . '|' . $ip);
}

function afg_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    return is_string($ip) && $ip !== '' ? $ip : '0.0.0.0';
}

/**
 * @return array<string, mixed>|null
 */
function afg_cache_read(string $name): ?array
{
    $path = afg_storage_path('cache/' . $name . '.json');
    if (!is_file($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : null;
}

/**
 * @param array<string, mixed> $value
 */
function afg_cache_write(string $name, array $value): void
{
    $dir = afg_storage_path('cache');
    if (!afg_ensure_dir($dir)) {
        return;
    }

    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return;
    }

    @file_put_contents($dir . '/' . $name . '.json', $json, LOCK_EX);
}

function afg_cache_forget(string $name): void
{
    $path = afg_storage_path('cache/' . $name . '.json');
    if (is_file($path)) {
        @unlink($path);
    }
}
