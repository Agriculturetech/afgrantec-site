<?php

declare(strict_types=1);

/**
 * Endpoint del formulario de contacto.
 *
 * Contrato:
 *   200 { ok: true }
 *   422 { ok: false, errors: { campo: "mensaje" } }
 *   403 forbidden (origen, honeypot o trampa de tiempo)
 *   413 payload demasiado grande
 *   429 rate_limited (+ Retry-After)
 *   502 mail (Microsoft Graph rechazó el envío)
 *   500 server (error inesperado)
 *
 * Sin JavaScript responde 303 a /?estado=ok|error#contacto.
 * Orden: método → tamaño → honeypot/tiempo/origen → rate limit → validación
 *        → Sheets (no bloqueante) → Graph sendMail (bloqueante).
 */

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/util.php';
require __DIR__ . '/src/validate.php';
require __DIR__ . '/src/security.php';
require __DIR__ . '/src/mail-graph.php';
require __DIR__ . '/src/sheets.php';

try {
    $config = afg_config();
    $rules = afg_rules();
    $maxBodyBytes = (int) ($rules['maxBodyBytes'] ?? 16384);

    afg_require_post();

    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > $maxBodyBytes) {
        afg_fail(413, 'too_large');
    }

    $rawBody = @file_get_contents('php://input');
    $rawBody = is_string($rawBody) ? $rawBody : '';
    if (strlen($rawBody) > $maxBodyBytes) {
        afg_fail(413, 'too_large');
    }

    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    $input = [];

    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode($rawBody, true);
        $input = is_array($decoded) ? $decoded : [];
    } else {
        $input = is_array($_POST) ? $_POST : [];
        if ($input === []) {
            // Algunos clientes envían JSON sin la cabecera correcta.
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $input = $decoded;
            }
        }
    }

    $ipHash = afg_ip_hash(afg_client_ip());
    $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

    // Honeypot: campo oculto que solo rellenan los bots.
    $honeypot = $input['website'] ?? '';
    if (is_scalar($honeypot) && trim((string) $honeypot) !== '') {
        afg_log('warn', 'blocked', ['reason' => 'honeypot', 'ip_hash' => $ipHash]);
        afg_fail(403, 'forbidden');
    }

    if (!afg_timing_ok($input, $rules)) {
        afg_log('warn', 'blocked', ['reason' => 'timing', 'ip_hash' => $ipHash]);
        afg_fail(403, 'forbidden');
    }

    if (!afg_origin_allowed($config)) {
        afg_log('warn', 'blocked', [
            'reason' => 'origin',
            'origin' => substr((string) ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? ''), 0, 160),
            'ip_hash' => $ipHash,
        ]);
        afg_fail(403, 'forbidden');
    }

    $rate = afg_rate_peek($config, $ipHash);
    if (!$rate['allowed']) {
        if (!headers_sent()) {
            header('Retry-After: ' . $rate['retry']);
        }
        afg_log('warn', 'blocked', ['reason' => 'rate_limit', 'ip_hash' => $ipHash]);
        afg_fail(429, 'rate_limited');
    }

    $result = afg_validate_contact($input);
    $data = $result['data'];
    $errors = $result['errors'];

    if ($errors !== []) {
        afg_log('info', 'validation_failed', ['fields' => array_keys($errors), 'ip_hash' => $ipHash]);
        afg_fail(422, 'validation', $errors);
    }

    afg_rate_hit($config, $ipHash);

    $submissionId = bin2hex(random_bytes(8));
    $context = [
        'id' => $submissionId,
        'pais' => $data['pais'],
        'produccion' => $data['produccion'],
        'ip_hash' => $ipHash,
    ];

    if (afg_is_dry_run()) {
        afg_log('info', 'dry_run', $context);
        afg_json(200, ['ok' => true, 'dry_run' => true]);
    }

    // Sheets no es bloqueante: si falla, la consulta ya está en el correo.
    try {
        afg_sheets_append($config, $data, $ipHash, $userAgent, $submissionId);
    } catch (Throwable $exception) {
        afg_log('error', 'sheets_failed', $context + ['message' => $exception->getMessage()]);
    }

    try {
        afg_send_mail($config, $data);
    } catch (Throwable $exception) {
        afg_log('error', 'mail_failed', $context + ['message' => $exception->getMessage()]);
        afg_fail(502, 'mail');
    }

    afg_log('info', 'sent', $context);

    if (afg_wants_json()) {
        afg_json(200, ['ok' => true]);
    }

    afg_redirect('ok');
} catch (Throwable $exception) {
    afg_log('error', 'fatal', ['message' => $exception->getMessage()]);
    afg_fail(500, 'server');
}
