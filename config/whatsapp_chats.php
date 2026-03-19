<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disco de almacenamiento (local / shared_uploads)
    |--------------------------------------------------------------------------
    |
    | Por defecto usa el disco "whatsapp_chats" (fuera de public/). En HostGator
    | puedes apuntar WHATSAPP_CHATS_DISK=secure_shared y definir SHARED_UPLOADS_PATH.
    |
    */
    'storage_disk' => env('WHATSAPP_CHATS_DISK', 'whatsapp_chats'),

    /*
    |--------------------------------------------------------------------------
    | KEK (clave maestra) — preferir variable de entorno en producción
    |--------------------------------------------------------------------------
    |
    | Genera 32 bytes aleatorios y codifica en base64:
    | php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
    |
    | Si WHATSAPP_CHATS_MASTER_KEY_BASE64 está vacío, se deriva de APP_KEY
    | (menos ideal; documentado para desarrollo local).
    |
    */
    'master_key_base64' => env('WHATSAPP_CHATS_MASTER_KEY_BASE64'),

    /*
    |--------------------------------------------------------------------------
    | TOTP (Google Authenticator) — acceso al módulo sensible
    |--------------------------------------------------------------------------
    |
    | issuer: nombre que verás en la app. holder_override: si no está vacío,
    | sustituye al email del usuario como etiqueta de la cuenta en Authenticator.
    |
    */
    'totp_issuer' => env('WHATSAPP_CHATS_TOTP_ISSUER') ?: env('APP_NAME', 'App'),

    'totp_holder_override' => env('WHATSAPP_CHATS_TOTP_HOLDER'),

    /*
    |--------------------------------------------------------------------------
    | (Legado) TTL de desbloqueo por contraseña — ya no se usa con TOTP
    |--------------------------------------------------------------------------
    */
    'unlock_ttl_minutes' => (int) env('WHATSAPP_CHATS_UNLOCK_TTL', 30),

    /*
    |--------------------------------------------------------------------------
    | Retención automática (0 = desactivado)
    |--------------------------------------------------------------------------
    |
    | El comando programado whatsapp-chats:prune elimina registros y archivos
    | más antiguos que N días (según imported_at).
    |
    */
    'retention_days' => (int) env('WHATSAPP_CHATS_RETENTION_DAYS', 0),

    /*
    |--------------------------------------------------------------------------
    | Respaldo cifrado (artisan whatsapp-chats:backup)
    |--------------------------------------------------------------------------
    */
    'backup_path' => env('WHATSAPP_CHATS_BACKUP_PATH', storage_path('app/backups/whatsapp_chats')),

];
