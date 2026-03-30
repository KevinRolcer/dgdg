<?php

$waMaxUploadMb = (int) env('WHATSAPP_CHATS_MAX_UPLOAD_MB', 768);
if ($waMaxUploadMb < 50) {
    $waMaxUploadMb = 768;
}
if ($waMaxUploadMb > 3072) {
    $waMaxUploadMb = 3072;
}

$waTxtPreviewMaxFileMb = max(1, (int) env('WHATSAPP_CHATS_TXT_PREVIEW_MAX_FILE_MB', 15));
$waTxtPreviewMaxMessages = (int) env('WHATSAPP_CHATS_TXT_PREVIEW_MAX_MESSAGES', 999999);

return [

    /*
    |--------------------------------------------------------------------------
    | Disco de almacenamiento (local / shared_uploads)
    |--------------------------------------------------------------------------
    |
    | Por defecto usa el disco "whatsapp_chats" (fuera de public/). Para no
    | hinchar el proyecto en hosting: WHATSAPP_CHATS_USE_SHARED=true y
    | SHARED_UPLOADS_PATH apuntando a una carpeta fuera de public_html.
    |
    */
    'storage_disk' => env('WHATSAPP_CHATS_DISK', 'whatsapp_chats'),

    /*
    |--------------------------------------------------------------------------
    | Tamaño máximo del ZIP de importación
    |--------------------------------------------------------------------------
    |
    | Laravel valida en kilobytes (MB × 1024). Tope en app: 3072 MB (3 GB).
    | Debe ser coherente con PHP (upload_max_filesize, post_max_size) y el
    | servidor web (p. ej. Nginx client_max_body_size). La importación pesada
    | corre en cola (queue worker) para evitar timeouts HTTP.
    |
    */
    'max_upload_mb' => $waMaxUploadMb,

    'max_upload_kb' => $waMaxUploadMb * 1024,

    /*
    |--------------------------------------------------------------------------
    | Vista previa web (_chat.txt) — evitar cuelgues del navegador (OOM)
    |--------------------------------------------------------------------------
    |
    | Si el TXT supera el tamaño en bytes, se usa solo la vista por partes HTML.
    | Si entra en tamaño pero hay muchos mensajes, solo se parsean los primeros N
    | para el JSON embebido y el render progresivo.
    |
    */
    'txt_preview_max_file_mb' => $waTxtPreviewMaxFileMb,

    'txt_preview_max_file_bytes' => $waTxtPreviewMaxFileMb * 1024 * 1024,

    'txt_preview_max_messages' => $waTxtPreviewMaxMessages,

    /*
    |--------------------------------------------------------------------------
    | Importación por carpeta (export descomprimido, archivo por archivo)
    |--------------------------------------------------------------------------
    */
    'folder_import_max_files' => max(50, min(100000, (int) env('WHATSAPP_FOLDER_IMPORT_MAX_FILES', 25000))),

    'folder_import_request_max_files' => max(1, min(20, (int) env('WHATSAPP_FOLDER_IMPORT_REQUEST_MAX_FILES', 20))),

    'folder_import_parallel_requests' => max(1, min(8, (int) env('WHATSAPP_FOLDER_IMPORT_PARALLEL_REQUESTS', 4))),

    'folder_import_request_target_bytes' => max(
        1024 * 1024,
        min(128 * 1024 * 1024, (int) env('WHATSAPP_FOLDER_IMPORT_REQUEST_TARGET_MB', 24) * 1024 * 1024)
    ),

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
