<?php

declare(strict_types=1);

/**
 * Persistencia en Google Sheets con una cuenta de servicio (JWT RS256 firmado
 * con openssl) y el método values:append.
 */

/**
 * Cabeceras de la hoja (15 columnas, A1:O1).
 *
 * @return array<int, string>
 */
function afg_sheet_header(): array
{
    return [
        'fecha',
        'nombre',
        'empresa',
        'email',
        'codigo_pais',
        'telefono',
        'pais',
        'produccion',
        'mensaje',
        'consentimiento',
        'consent_version',
        'origen',
        'ip_hash',
        'user_agent',
        'id',
    ];
}

function afg_base64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

/**
 * Obtiene un token de acceso para Sheets, con caché en archivo.
 */
function afg_sheets_token(array $config): string
{
    $cached = afg_cache_read('sheets-token');
    if (is_array($cached) && isset($cached['token'], $cached['expires_at'])) {
        if (is_string($cached['token']) && (int) $cached['expires_at'] > time() + 60) {
            return $cached['token'];
        }
    }

    $email = (string) afg_cfg($config, 'sheets.service_account.client_email', '');
    $privateKey = (string) afg_cfg($config, 'sheets.service_account.private_key', '');

    if ($email === '' || $privateKey === '') {
        throw new RuntimeException('Falta la cuenta de servicio de Sheets');
    }

    $now = time();
    $header = afg_base64url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claims = afg_base64url((string) json_encode([
        'iss' => $email,
        'scope' => 'https://www.googleapis.com/auth/spreadsheets',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ]));

    $unsigned = $header . '.' . $claims;
    $signature = '';
    if (!openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('No se pudo firmar el JWT de Sheets');
    }

    $assertion = $unsigned . '.' . afg_base64url($signature);

    $response = afg_http(
        'POST',
        'https://oauth2.googleapis.com/token',
        ['Content-Type: application/x-www-form-urlencoded'],
        afg_form_body([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]),
        15
    );

    if ($response['error'] !== null || $response['status'] !== 200) {
        throw new RuntimeException('Sheets token HTTP ' . $response['status'] . ' ' . ($response['error'] ?? ''));
    }

    $decoded = json_decode($response['body'], true);
    $token = is_array($decoded) ? ($decoded['access_token'] ?? null) : null;
    if (!is_string($token) || $token === '') {
        throw new RuntimeException('Sheets token sin access_token');
    }

    $expiresIn = is_array($decoded) ? (int) ($decoded['expires_in'] ?? 3600) : 3600;
    afg_cache_write('sheets-token', [
        'token' => $token,
        'expires_at' => time() + max(60, $expiresIn - 120),
    ]);

    return $token;
}

/**
 * Rango A1 de la hoja, con el nombre escapado.
 */
function afg_sheets_range(array $config, string $cells): string
{
    $sheet = (string) afg_cfg($config, 'sheets.sheet_name', 'Consultas');
    $escaped = str_replace("'", "''", $sheet);

    return "'" . $escaped . "'!" . $cells;
}

/**
 * Cabeceras HTTP para las llamadas a Sheets.
 *
 * @return array<int, string>
 */
function afg_sheets_headers(array $config): array
{
    return [
        'Authorization: Bearer ' . afg_sheets_token($config),
        'Content-Type: application/json',
    ];
}

/**
 * Garantiza que la primera fila tenga las cabeceras.
 */
function afg_sheets_ensure_header(array $config): void
{
    $spreadsheetId = (string) afg_cfg($config, 'sheets.spreadsheet_id', '');
    if ($spreadsheetId === '') {
        throw new RuntimeException('Falta sheets.spreadsheet_id');
    }

    $base = 'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode($spreadsheetId) . '/values/';
    $range = rawurlencode(afg_sheets_range($config, 'A1:O1'));

    $current = afg_http('GET', $base . $range, afg_sheets_headers($config), null, 15);
    if ($current['error'] !== null || $current['status'] !== 200) {
        throw new RuntimeException('Sheets lectura HTTP ' . $current['status']);
    }

    $decoded = json_decode($current['body'], true);
    $values = is_array($decoded) ? ($decoded['values'] ?? []) : [];
    if (is_array($values) && $values !== []) {
        return;
    }

    $body = afg_json_encode(['values' => [afg_sheet_header()]]);
    $response = afg_http(
        'PUT',
        $base . $range . '?valueInputOption=RAW',
        array_merge(afg_sheets_headers($config), ['Expect:']),
        $body,
        15
    );

    if ($response['error'] !== null || $response['status'] < 200 || $response['status'] >= 300) {
        throw new RuntimeException('Sheets cabecera HTTP ' . $response['status']);
    }
}

/**
 * Añade una fila al final de la hoja.
 *
 * @param array<string, string> $data
 */
function afg_sheets_append(array $config, array $data, string $ipHash, string $userAgent, string $submissionId): void
{
    $spreadsheetId = (string) afg_cfg($config, 'sheets.spreadsheet_id', '');
    if ($spreadsheetId === '') {
        throw new RuntimeException('Falta sheets.spreadsheet_id');
    }

    afg_sheets_ensure_header($config);

    $consentVersion = (string) afg_cfg($config, 'consent_version', 'v1-2026-11');
    $row = [
        date('c'),
        $data['nombre'],
        $data['empresa'],
        $data['email'],
        $data['codigo_pais'],
        $data['telefono'],
        $data['pais'],
        $data['produccion'],
        $data['mensaje'],
        $consentVersion !== '' ? 'sí (' . $consentVersion . ')' : 'sí',
        $consentVersion,
        $data['origen'],
        $ipHash,
        mb_substr($userAgent, 0, 300, 'UTF-8'),
        $submissionId,
    ];

    $body = afg_json_encode(['values' => [$row]]);
    $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode($spreadsheetId)
        . '/values/' . rawurlencode(afg_sheets_range($config, 'A1'))
        . ':append?valueInputOption=RAW&insertDataOption=INSERT_ROWS';

    $response = afg_http(
        'POST',
        $url,
        array_merge(afg_sheets_headers($config), ['Expect:']),
        $body,
        15
    );

    if ($response['error'] !== null || $response['status'] < 200 || $response['status'] >= 300) {
        throw new RuntimeException('Sheets append HTTP ' . $response['status'] . ' ' . ($response['error'] ?? ''));
    }
}
