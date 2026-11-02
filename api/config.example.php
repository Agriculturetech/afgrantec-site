<?php

declare(strict_types=1);

/**
 * Plantilla de configuración del endpoint de contacto.
 *
 * En local: copia este archivo a api/config.php y rellena los valores.
 * En producción NO se copia: scripts/build-api.mjs genera dist/api/config.php
 * a partir de las variables de entorno (GitHub Secrets).
 */
return [
    'timezone' => 'America/Bogota',

    // true = no envía correo ni escribe en la hoja, solo registra en el log.
    'dry_run' => true,

    'mail' => [
        'from_name' => 'AFGRANTEC',
        'to' => 'sebastian.lora@afgrantec.com',
        'bcc' => [
            'nicolas.oliveros@afgrantec.com',
            'fernando.munoz@afgrantec.com',
        ],
        'subject_prefix' => 'Consulta AFGRANTEC',
    ],

    // Aplicación de Entra ID con permiso de aplicación Mail.Send.
    'graph' => [
        'tenant_id' => '',
        'client_id' => '',
        'client_secret' => '',
        'sender' => 'sebastian.lora@afgrantec.com',
    ],

    // Cuenta de servicio de Google con acceso de editor a la hoja.
    'sheets' => [
        'spreadsheet_id' => '',
        'sheet_name' => 'Consultas',
        'service_account' => [
            'client_email' => '',
            'private_key' => '',
        ],
    ],

    'security' => [
        // Orígenes permitidos para el POST (esquema + host + puerto).
        'allowed_origins' => [
            'https://afgrantec.com',
            'https://www.afgrantec.com',
            'http://localhost:4321',
            'http://127.0.0.1:4321',
        ],
        // Cadena aleatoria propia; se usa para el hash de IP.
        'salt' => '',
        'rate_limit' => [
            'window' => 600,
            'max' => 5,
            'global_window' => 3600,
            'global_max' => 30,
        ],
    ],

    // Versión del consentimiento que se guarda como evidencia en la hoja.
    'consent_version' => 'v1-2026-11',
];
