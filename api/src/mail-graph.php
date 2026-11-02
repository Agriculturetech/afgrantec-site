<?php

declare(strict_types=1);

/**
 * Envío de correo con Microsoft Graph (sendMail, permiso de aplicación Mail.Send).
 * No usa SMTP ni mail(): el mensaje lo envía Microsoft 365, así que SPF/DKIM/DMARC
 * del dominio siguen alineados.
 */

/**
 * Obtiene un token de aplicación para Graph, con caché en archivo.
 */
function afg_graph_token(array $config): string
{
    $cached = afg_cache_read('graph-token');
    if (is_array($cached) && isset($cached['token'], $cached['expires_at'])) {
        if (is_string($cached['token']) && (int) $cached['expires_at'] > time() + 60) {
            return $cached['token'];
        }
    }

    $tenant = (string) afg_cfg($config, 'graph.tenant_id', '');
    $clientId = (string) afg_cfg($config, 'graph.client_id', '');
    $clientSecret = (string) afg_cfg($config, 'graph.client_secret', '');

    if ($tenant === '' || $clientId === '' || $clientSecret === '') {
        throw new RuntimeException('Faltan credenciales de Graph');
    }

    $response = afg_http(
        'POST',
        'https://login.microsoftonline.com/' . rawurlencode($tenant) . '/oauth2/v2.0/token',
        ['Content-Type: application/x-www-form-urlencoded'],
        afg_form_body([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => 'https://graph.microsoft.com/.default',
            'grant_type' => 'client_credentials',
        ]),
        15
    );

    if ($response['error'] !== null || $response['status'] !== 200) {
        throw new RuntimeException('Graph token HTTP ' . $response['status'] . ' ' . ($response['error'] ?? ''));
    }

    $decoded = json_decode($response['body'], true);
    $token = is_array($decoded) ? ($decoded['access_token'] ?? null) : null;
    if (!is_string($token) || $token === '') {
        throw new RuntimeException('Graph token sin access_token');
    }

    $expiresIn = is_array($decoded) ? (int) ($decoded['expires_in'] ?? 3600) : 3600;
    afg_cache_write('graph-token', [
        'token' => $token,
        'expires_at' => time() + max(60, $expiresIn - 120),
    ]);

    return $token;
}

/**
 * Construye el cuerpo HTML del correo (valores escapados).
 *
 * @param array<string, string> $data
 */
function afg_mail_html(array $data): string
{
    $rows = [
        'Nombre' => $data['nombre'],
        'Empresa' => $data['empresa'] !== '' ? $data['empresa'] : 'No indicada',
        'Correo' => $data['email'],
        'País o territorio' => $data['pais'],
        'Teléfono' => $data['telefono_completo'],
        'Producción' => $data['produccion'],
    ];

    $html = '<h2 style="font:600 18px/1.3 Arial,sans-serif;margin:0 0 16px">Nueva consulta desde afgrantec.com</h2>';
    $html .= '<table style="border-collapse:collapse;font:14px/1.5 Arial,sans-serif">';

    foreach ($rows as $label => $value) {
        $html .= '<tr>'
            . '<th style="text-align:left;padding:6px 16px 6px 0;vertical-align:top;color:#48586e">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</th>'
            . '<td style="padding:6px 0;vertical-align:top">' . nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8')) . '</td>'
            . '</tr>';
    }

    $html .= '</table>';
    $html .= '<h3 style="font:600 15px/1.4 Arial,sans-serif;margin:20px 0 6px">Mensaje</h3>';
    $html .= '<p style="font:14px/1.6 Arial,sans-serif;white-space:pre-line;margin:0">'
        . htmlspecialchars($data['mensaje'], ENT_QUOTES, 'UTF-8') . '</p>';

    if ($data['origen'] !== '') {
        $html .= '<p style="font:12px/1.5 Arial,sans-serif;color:#606f84;margin:18px 0 0">Origen: '
            . htmlspecialchars($data['origen'], ENT_QUOTES, 'UTF-8') . '</p>';
    }

    return $html;
}

/**
 * Envía la consulta con Graph. Lanza excepción si el correo no se acepta.
 *
 * @param array<string, string> $data
 */
function afg_send_mail(array $config, array $data): void
{
    $sender = (string) afg_cfg($config, 'graph.sender', '');
    $to = (string) afg_cfg($config, 'mail.to', '');
    $bcc = afg_cfg($config, 'mail.bcc', []);
    $prefix = (string) afg_cfg($config, 'mail.subject_prefix', 'Consulta AFGRANTEC');
    $fromName = (string) afg_cfg($config, 'mail.from_name', 'AFGRANTEC');

    if ($sender === '' || $to === '') {
        throw new RuntimeException('Falta remitente o destinatario');
    }

    $subject = $prefix;
    if ($data['pais'] !== '') {
        $subject .= ' | ' . $data['pais'];
    }
    if ($data['produccion'] !== '') {
        $subject .= ' | ' . $data['produccion'];
    }

    $bccRecipients = [];
    foreach (is_array($bcc) ? $bcc : [] as $address) {
        if (is_string($address) && filter_var($address, FILTER_VALIDATE_EMAIL) !== false) {
            $bccRecipients[] = ['emailAddress' => ['address' => $address]];
        }
    }

    $message = [
        'subject' => $subject,
        'body' => [
            'contentType' => 'HTML',
            'content' => afg_mail_html($data),
        ],
        'toRecipients' => [
            ['emailAddress' => ['address' => $to, 'name' => $fromName]],
        ],
        'replyTo' => [
            ['emailAddress' => ['address' => $data['email'], 'name' => $data['nombre']]],
        ],
    ];

    if ($bccRecipients !== []) {
        $message['bccRecipients'] = $bccRecipients;
    }

    // Nota: internetMessageHeaders solo se acepta al crear un borrador, no en
    // sendMail; el identificador del envío queda en el log interno del endpoint.

    $payload = afg_json_encode([
        'message' => $message,
        'saveToSentItems' => false,
    ]);

    $url = 'https://graph.microsoft.com/v1.0/users/' . rawurlencode($sender) . '/sendMail';

    for ($attempt = 0; $attempt < 2; $attempt++) {
        $token = afg_graph_token($config);

        $response = afg_http(
            'POST',
            $url,
            [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Expect:',
            ],
            $payload,
            15
        );

        if ($response['status'] >= 200 && $response['status'] < 300) {
            return;
        }

        if ($response['status'] === 401) {
            afg_cache_forget('graph-token');
        }

        $retryable = $response['status'] === 429 || $response['status'] >= 500;
        if ($attempt === 0 && $retryable) {
            $retryAfter = (int) ($response['headers']['retry-after'] ?? 1);
            @sleep(min(5, max(1, $retryAfter)));
            continue;
        }

        throw new RuntimeException('Graph sendMail HTTP ' . $response['status']);
    }
}
