<?php

declare(strict_types=1);

/**
 * Validación en servidor. Es la frontera de confianza: replica las reglas de
 * api/rules.json (las mismas que usa el cliente) y normaliza los valores.
 */

/**
 * @return array<string, mixed>
 */
function afg_rules(): array
{
    static $rules = null;

    if ($rules === null) {
        $raw = @file_get_contents(AFG_ROOT . '/rules.json');
        if ($raw === false) {
            throw new RuntimeException('No se pudo leer api/rules.json');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('api/rules.json no es válido');
        }

        $rules = $decoded;
    }

    return $rules;
}

/**
 * @param array<string, mixed> $rules
 * @return array<string, mixed>
 */
function afg_limit(array $rules, string $field): array
{
    $limits = $rules['limits'] ?? [];

    return is_array($limits) && isset($limits[$field]) && is_array($limits[$field])
        ? $limits[$field]
        : [];
}

/**
 * Devuelve el nombre del país a partir de su código telefónico.
 */
function afg_country_name(string $dial): string
{
    $rules = afg_rules();

    foreach (['paises', 'territorios'] as $group) {
        $list = $rules[$group] ?? [];
        if (!is_array($list)) {
            continue;
        }

        foreach ($list as $country) {
            if (is_array($country) && ($country['dial'] ?? null) === $dial) {
                return (string) ($country['nombre'] ?? '');
            }
        }
    }

    return '';
}

/**
 * Valida y normaliza la entrada del formulario.
 *
 * @param array<string, mixed> $input
 * @return array{data: array<string, string>, errors: array<string, string>}
 */
function afg_validate_contact(array $input): array
{
    $rules = afg_rules();
    $messages = is_array($rules['messages'] ?? null) ? $rules['messages'] : [];

    $msg = static function (string $key, string $fallback) use ($messages): string {
        $value = $messages[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $fallback;
    };

    $errors = [];

    $nombreLimit = afg_limit($rules, 'nombre');
    $nombre = afg_text($input['nombre'] ?? '', (int) ($nombreLimit['max'] ?? 100));
    if (mb_strlen($nombre, 'UTF-8') < (int) ($nombreLimit['min'] ?? 2)) {
        $errors['nombre'] = $msg('nombre', 'Escribe tu nombre (mínimo 2 caracteres).');
    }

    $empresaLimit = afg_limit($rules, 'empresa');
    $empresa = afg_text($input['empresa'] ?? '', (int) ($empresaLimit['max'] ?? 150));

    $emailLimit = afg_limit($rules, 'email');
    $email = mb_strtolower(afg_text($input['email'] ?? '', (int) ($emailLimit['max'] ?? 254)), 'UTF-8');
    if ($email === '') {
        $errors['email'] = $msg('email_required', 'Escribe tu correo electrónico.');
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = $msg('email_invalid', 'Escribe un correo electrónico válido.');
    }

    $paisLimit = afg_limit($rules, 'pais');
    $rawPais = afg_text($input['pais'] ?? '', (int) ($paisLimit['max'] ?? 16));
    $codigoPais = afg_dial($rawPais);
    $esOtro = $rawPais === 'otro';

    $paisNombre = afg_text($input['pais_nombre'] ?? '', 80);
    if ($esOtro) {
        $codigoPais = '';
        if ($paisNombre === '') {
            $paisNombre = 'Otro país o territorio';
        }
    } elseif ($codigoPais !== '') {
        $resolved = afg_country_name($codigoPais);
        if ($resolved !== '') {
            $paisNombre = $resolved;
        }
    } else {
        $paisNombre = '';
    }

    if (!$esOtro && $codigoPais === '') {
        $errors['pais'] = $msg('pais', 'Selecciona un país o territorio.');
    }

    $telefonoLimit = afg_limit($rules, 'telefono');
    $telefonoRaw = afg_text($input['telefono'] ?? '', (int) ($telefonoLimit['max'] ?? 32));
    $telefono = afg_digits($telefonoRaw);
    $minDigits = (int) ($telefonoLimit['minDigits'] ?? 6);
    $maxDigits = (int) ($telefonoLimit['maxDigits'] ?? 15);

    if ($telefono === '') {
        $errors['telefono'] = $msg('telefono_required', 'Escribe tu número de teléfono.');
    } elseif (strlen($telefono) < $minDigits || strlen($telefono) > $maxDigits) {
        $errors['telefono'] = $msg('telefono_invalid', 'Escribe un teléfono válido (entre 6 y 15 dígitos).');
    }

    $produccionLimit = afg_limit($rules, 'produccion');
    $produccion = afg_text($input['produccion'] ?? '', (int) ($produccionLimit['max'] ?? 60));
    $allowed = is_array($produccionLimit['allowed'] ?? null) ? $produccionLimit['allowed'] : [];
    if (!in_array($produccion, $allowed, true)) {
        $errors['produccion'] = $msg('produccion', 'Selecciona el tipo de producción.');
        $produccion = '';
    }

    $mensajeLimit = afg_limit($rules, 'mensaje');
    $mensaje = afg_text($input['mensaje'] ?? '', (int) ($mensajeLimit['max'] ?? 1800));
    $mensajeMin = (int) ($mensajeLimit['min'] ?? 10);
    if ($mensaje === '') {
        $errors['mensaje'] = $msg('mensaje_required', 'Cuéntanos qué equipo o proyecto necesitas.');
    } elseif (mb_strlen($mensaje, 'UTF-8') < $mensajeMin) {
        $errors['mensaje'] = $msg('mensaje_short', 'Cuéntanos un poco más (mínimo 10 caracteres).');
    }

    $consent = afg_text($input['consent'] ?? '', 8);
    if ($consent !== '1') {
        $errors['consent'] = $msg('consent', 'Debes aceptar la política de privacidad para continuar.');
        $consent = '';
    } else {
        $consent = '1';
    }

    $data = [
        'nombre' => $nombre,
        'empresa' => $empresa,
        'email' => $email,
        'codigo_pais' => $codigoPais,
        'telefono' => $telefono,
        'telefono_completo' => $codigoPais !== '' ? $codigoPais . ' ' . $telefono : $telefono,
        'pais' => $paisNombre,
        'produccion' => $produccion,
        'mensaje' => $mensaje,
        'consent' => $consent,
        'origen' => afg_text($input['origen'] ?? '', 160),
    ];

    return ['data' => $data, 'errors' => $errors];
}
