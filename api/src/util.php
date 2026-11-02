<?php

declare(strict_types=1);

/**
 * Utilidades HTTP (cURL) y helpers de texto.
 */

/**
 * Petición HTTP con cURL.
 *
 * @param array<int, string> $headers
 * @return array{status: int, body: string, headers: array<string, string>, error: ?string}
 */
function afg_http(
    string $method,
    string $url,
    array $headers = [],
    ?string $body = null,
    int $timeout = 15
): array {
    $responseHeaders = [];
    $ch = curl_init();

    if ($ch === false) {
        return ['status' => 0, 'body' => '', 'headers' => [], 'error' => 'curl_init'];
    }

    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return strlen($line);
        },
    ];

    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = $body;
    }

    curl_setopt_array($ch, $options);

    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($raw === false) {
        return ['status' => $status, 'body' => '', 'headers' => $responseHeaders, 'error' => $error !== '' ? $error : 'curl'];
    }

    return ['status' => $status, 'body' => (string) $raw, 'headers' => $responseHeaders, 'error' => null];
}

/**
 * Codifica un array asociativo como application/x-www-form-urlencoded.
 *
 * @param array<string, string> $fields
 */
function afg_form_body(array $fields): string
{
    return http_build_query($fields, '', '&', PHP_QUERY_RFC1738);
}

/**
 * Recorta y normaliza espacios. Devuelve '' si el valor no es escalar.
 *
 * @param mixed $value
 */
function afg_text($value, int $maxLength = 0): string
{
    if (!is_scalar($value)) {
        return '';
    }

    $text = (string) $value;
    if (function_exists('mb_scrub')) {
        $text = mb_scrub($text, 'UTF-8');
    }

    // Quita caracteres de control y normaliza espacios.
    $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
    $text = trim((string) preg_replace('/[ \t]+/u', ' ', $text));
    $text = (string) preg_replace('/\R{3,}/u', "\n\n", $text);

    if ($maxLength > 0 && mb_strlen($text, 'UTF-8') > $maxLength) {
        $text = mb_substr($text, 0, $maxLength, 'UTF-8');
    }

    return $text;
}

/**
 * Deja solo dígitos (el código de país se guarda aparte).
 */
function afg_digits(string $value): string
{
    return (string) preg_replace('/\D+/', '', $value);
}

/**
 * Normaliza el código de país: "+57" o "+1 268".
 */
function afg_dial(string $value): string
{
    $value = trim($value);

    return preg_match('/^\+\d{1,4}(?: \d{1,4})?$/', $value) === 1 ? $value : '';
}

/**
 * @param array<string, mixed> $payload
 */
function afg_json_encode(array $payload): string
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $json === false ? '{}' : $json;
}
