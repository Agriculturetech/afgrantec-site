# Deuda técnica del formulario de contacto

Decisiones tomadas durante la implementación que dejan trabajo pendiente y conocido.
Ninguna bloquea el funcionamiento actual del formulario.

## 1. `Mail.Send` sin acotar a un solo buzón (ApplicationAccessPolicy)

**Estado:** pendiente. Se saltó a propósito porque requiere rol *Exchange Administrator*.

El permiso de aplicación `Mail.Send` permite a la app enviar como **cualquier** buzón del
tenant. Con la política de acceso, la app solo puede enviar como `sebastian.lora@afgrantec.com`.

Mitigaciones actuales:

- El remitente está fijo en `api/config.php`; no es un parámetro del endpoint.
- `client_secret` solo vive en GitHub Secrets y en el `config.php` generado en CI.
- El secreto se rota al caducar (24 meses) y el fallo del correo es visible para el usuario.
- Cada envío queda en el log interno del endpoint con `ip_hash`.

Cómo cerrarla, cuando haya una cuenta con rol de Exchange:

```powershell
Install-Module -Name ExchangeOnlineManagement -Scope CurrentUser -Force -AllowClobber
Import-Module ExchangeOnlineManagement
Connect-ExchangeOnline -UserPrincipalName admin@afgrantec.com -ShowBanner:$false

New-ApplicationAccessPolicy `
  -AppId "<GRAPH_CLIENT_ID>" `
  -PolicyScopeGroupId "sebastian.lora@afgrantec.com" `
  -AccessRight RestrictAccess `
  -Description "Formulario web AFGRANTEC"

Test-ApplicationAccessPolicy -Identity "sebastian.lora@afgrantec.com" -AppId "<GRAPH_CLIENT_ID>"   # Granted
Test-ApplicationAccessPolicy -Identity "nicolas.oliveros@afgrantec.com" -AppId "<GRAPH_CLIENT_ID>"  # Denied
```

La política tarda hasta 30–60 minutos en surtir efecto. Si cambia el buzón remitente,
hay que recrearla.

## 2. Rotación del client secret de Entra ID

El secreto creado para `afgrantec-web-contacto` caduca a los **24 meses** (noviembre de 2028).
Cuando caduque, el formulario devolverá error 502 y el log registrará `mail_failed`.

Procedimiento:

1. Entra ID → App registrations → `afgrantec-web-contacto` → Certificates & secrets.
2. New client secret → copiar el **Value**.
3. Actualizar el secret `GRAPH_CLIENT_SECRET` en GitHub → re-ejecutar el deploy.
4. Actualizar `api/config.php` local si se usa para pruebas reales.

## 3. DMARC en `p=none`

`_dmarc.afgrantec.com` está en `v=DMARC1; p=none`. Los envíos por Graph pasan SPF/DKIM/DMARC,
pero conviene endurecerlo cuando el correo lleve semanas estable:

1. `p=quarantine` con `pct=25`, revisar reportes.
2. Subir a `p=quarantine` completo y luego `p=reject`.

Es solo un cambio de DNS; no requiere tocar el código.

## 4. Versión de PHP del hosting sin confirmar

El código evita sintaxis posterior a PHP 8.2 y el CI valida con `php -l` sobre 8.2.
Verificar en hPanel que el dominio use PHP 8.2 o superior; si usa 8.1 o menos,
`str_contains`/`str_starts_with` siguen existiendo (8.0+), pero conviene alinear la versión.

## 5. Cuenta de servicio de Google sobre un Drive personal

La hoja vive en un Drive personal gratuito y se comparte con la cuenta de servicio como
*Editor*. Riesgos: cuota del Drive personal, pérdida de acceso si se mueve la hoja y
dependencia de que nadie revoque el permiso.

Mitigación: la hoja es solo `append`; el correo es la fuente principal. Si Sheets falla,
el envío se registra en el log y la consulta no se pierde.

## 6. Antispam sin captcha

Se usa honeypot + trampa de tiempo + límite por IP (5/10 min y 30/hora global).
Un bot distribuido con IPs rotativas y un navegador real podría pasar. Si aparece spam:

1. Revisar `api/storage/logs/` y los patrones de `origen`.
2. Añadir Cloudflare Turnstile (invisible) o subir el mínimo de `minFillMs`.
3. Ajustar `security.rate_limit` en `api/config.php`.

## 7. Rate limit en archivos locales

Funciona en un solo servidor. Si algún día hay varios nodos o se migra a otro hosting,
habría que mover el contador a un almacén compartido (Redis, base de datos).

## 8. Sin Composer ni PHPMailer

El envío va por Microsoft Graph con `curl`, así que no hay dependencias PHP que instalar.
Si se cambiara a SMTP, habría que añadir PHPMailer y `vendor/` al pipeline.
