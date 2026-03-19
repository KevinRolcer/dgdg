# Chats WhatsApp — seguridad implementada

## Resumen de los 5 pasos

1. **Permiso dedicado**  
   - Permiso Spatie: `Chats-WhatsApp-Sensible` (guard `web`).  
   - El menú y las rutas `/admin/whatsapp-chats/*` lo exigen.  
   - La migración lo asigna al rol **Administrador** (ajusta otros roles en BD si aplica).

2. **Cifrado por exportación (DEK + KEK)**  
   - Cada importación genera una **DEK** (32 bytes) que cifra todos los archivos del chat con **AES-256-GCM** (prefijo mágico `WA1`).  
   - La DEK se guarda **envuelta** con la **KEK** en columna `wrapped_dek`.  
   - **KEK local (dev):** si `WHATSAPP_CHATS_MASTER_KEY_BASE64` está vacío, se deriva de `APP_KEY` (solo para desarrollo).  
   - **Producción:** define `WHATSAPP_CHATS_MASTER_KEY_BASE64` con 32 bytes aleatorios en base64:  
     `php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"`

3. **Auditoría**  
   - Tabla `whatsapp_chat_access_logs`: acciones `list`, `view`, `media`, `import`, `delete`, `totp_setup`, `totp_verify`, `totp_reset` (usuario, IP, user-agent, ruta opcional).

4. **Sin caché en cliente**  
   - Middleware `WhatsAppNoStoreResponse`: `Cache-Control: no-store` (además de las cabeceras globales).

5. **Respaldo y retención**  
   - **Respaldo:** `php artisan whatsapp-chats:backup` — ZIP en `whatsapp_chats.backup_path` (archivos ya cifrados en disco). Programado semanal en `bootstrap/app.php`.  
   - **Retención:** `WHATSAPP_CHATS_RETENTION_DAYS` > `0` + `php artisan whatsapp-chats:prune` (diario programado). `0` = desactivado.

## Step-up (TOTP / Google Authenticator)

Tras iniciar sesión, la **primera vez** que entras al módulo en esa sesión debes introducir un código de **Google Authenticator** (TOTP). No se vuelve a pedir hasta **cerrar sesión** (nueva sesión = nuevo código).

- **Primera vez (usuario):** se muestra un **QR** para registrar la cuenta en la app; el secreto se guarda cifrado en `users.whatsapp_totp_secret` y se marca `whatsapp_totp_confirmed_at`.
- **Siguientes sesiones:** solo campo de código (sin QR).
- **Etiqueta en la app:** por defecto el **email del usuario** de Laravel; opcionalmente fija `WHATSAPP_CHATS_TOTP_HOLDER` (p. ej. `dgdg.admon@gmail.com`) para que todos vean esa etiqueta en Authenticator.
- **Emisor (issuer):** `WHATSAPP_CHATS_TOTP_ISSUER` o, si vacío, `APP_NAME`.

Si pierdes el dispositivo, puedes **restablecer el autenticador** en **Ajustes → Chats WhatsApp (autenticador)** (requiere permiso `Chats-WhatsApp-Sensible` y confirmar con la contraseña de la cuenta, sin pegar en el campo). También puedes poner en BD `whatsapp_totp_secret` y `whatsapp_totp_confirmed_at` en `NULL` para forzar un nuevo registro por QR.

## Almacenamiento (local / HostGator)

- Disco `whatsapp_chats`: por defecto `storage/app/whatsapp_chats_private` (no servido por `/storage` público).  
- **Recomendación producción:** no acumular muchos GB dentro del repo/hosting del proyecto. Usa carpeta compartida:
  - `WHATSAPP_CHATS_USE_SHARED=true`
  - `SHARED_UPLOADS_PATH=/ruta/absoluta/al/shared` (sin barra final)
  - Los archivos quedan en `{SHARED_UPLOADS_PATH}/whatsapp_chats_private` (crea la carpeta con permisos de escritura para PHP).
- Tras cambiar `.env`: `php artisan config:clear && php artisan config:cache`.

## Base de datos (tamaño y listados)

- El **contenido** del chat (HTML, TXT, medios) está en **disco cifrado**, no en filas enormes de texto en la BD.
- En `message_parts` se guardan rutas **compactas**: relativas a `storage_root_path` (p. ej. `messages/message_1.html`), para no repetir el prefijo largo (`whatsapp-chats/…`) miles de veces en el JSON.
- La columna **`message_parts_count`** guarda el número de partes; el **listado** del módulo no carga el JSON completo solo para mostrar “Partes”.

## ZIP grandes (respaldos de muchos mensajes)

- La app limita el tamaño con **`WHATSAPP_CHATS_MAX_UPLOAD_MB`** (por defecto **768** MB). Eso genera la regla `max` de Laravel en **kilobytes** (MB × 1024).
- Aunque subas ese valor, **PHP** debe permitir la subida:
  - `upload_max_filesize` ≥ el tamaño del ZIP (ej. `800M`)
  - `post_max_size` un poco **mayor** que `upload_max_filesize` (ej. `810M`)
  - `max_execution_time` y `memory_limit` altos para descomprimir e indexar (ej. `600`, `512M` o más según el host)
- **Nginx:** `client_max_body_size 800m;` (o superior) en el `server` o `location` del sitio.
- **Apache:** `LimitRequestBody` si aplica.
- **Hosting compartido:** a veces hay tope fijo (p. ej. 128M) que no puedes subir; en ese caso toca subir por FTP/SFTP a `whatsapp_chats_private` y registrar en BD manualmente **no** está implementado — habría que subir por trozos o pedir aumento de límite al proveedor.
- En la pantalla de importación se muestra el límite configurado en la aplicación; si falla con error de tamaño vacío o 413, revisa primero PHP y el proxy/servidor web.

## Registros antiguos (antes de esta migración)

Filas con `is_encrypted = 0` y `storage_disk = public` siguen funcionando en **texto plano** en `public/storage`. Las **nuevas** importaciones van cifradas al disco privado. Para máxima seguridad, vuelve a importar chats sensibles o borra los legados.

## Variables `.env` recomendadas

```env
WHATSAPP_CHATS_MASTER_KEY_BASE64=
WHATSAPP_CHATS_TOTP_ISSUER=
WHATSAPP_CHATS_TOTP_HOLDER=
WHATSAPP_CHATS_UNLOCK_TTL=30
WHATSAPP_CHATS_RETENTION_DAYS=0
WHATSAPP_CHATS_BACKUP_PATH=
WHATSAPP_CHATS_DISK=whatsapp_chats
WHATSAPP_CHATS_USE_SHARED=false
SHARED_UPLOADS_PATH=
WHATSAPP_CHATS_MAX_UPLOAD_MB=768
```
