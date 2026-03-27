<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\WhatsAppChatArchiveImportJob;
use App\Models\WhatsAppChatAccessLog;
use App\Notifications\WhatsAppChatImportProgressNotification;
use App\Models\WhatsAppChatArchive;
use App\Services\WhatsApp\WhatsAppChatEncryptionService;
use App\Services\WhatsApp\WhatsAppChatPathNormalizer;
use App\Services\WhatsApp\WhatsAppTotpService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppChatArchiveController extends Controller
{
    public function __construct(
        private readonly WhatsAppChatEncryptionService $encryption,
        private readonly WhatsAppTotpService $totp,
    ) {}

    public function totpForm(Request $request)
    {
        $user = $request->user();
        $needsSetup = $user->whatsapp_totp_confirmed_at === null;

        $qrSvg = null;
        $pendingSecret = null;
        $manualSecret = null;

        if ($needsSetup) {
            $pendingSecret = $request->session()->get('whatsapp_totp_pending_secret');
            if (! is_string($pendingSecret) || $pendingSecret === '') {
                $pendingSecret = $this->totp->generateSecret();
                $request->session()->put('whatsapp_totp_pending_secret', $pendingSecret);
            }
            $manualSecret = $pendingSecret;
            $holder = $this->totp->holderLabel($user);
            $uri = $this->totp->otpauthUri($pendingSecret, $holder);
            $qrSvg = $this->totp->qrCodeSvg($uri);
        }

        return view('admin.whatsapp-chats.totp', [
            'hidePageHeader' => true,
            'pageTitle' => 'Autenticación — Chats WhatsApp',
            'pageDescription' => $needsSetup
                ? 'Configura Google Authenticator con el QR o la clave manual para comenzar.'
                : '',
            'redirect' => $request->query('redirect'),
            'needsSetup' => $needsSetup,
            'qrSvg' => $qrSvg,
            'manualSecret' => $manualSecret,
            'totpIssuer' => $this->totp->issuer(),
            'totpHolder' => $this->totp->holderLabel($user),
        ]);
    }

    public function totpSubmit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'redirect' => ['nullable', 'string', 'max:2048'],
        ]);

        $user = $request->user();
        $code = (string) $validated['code'];

        if ($user->whatsapp_totp_confirmed_at === null) {
            $pending = $request->session()->get('whatsapp_totp_pending_secret');
            if (! is_string($pending) || $pending === '' || ! $this->totp->verify($pending, $code)) {
                return back()
                    ->withErrors(['code' => 'Código incorrecto o caducado. Verifica la hora del teléfono e inténtalo de nuevo.'])
                    ->withInput();
            }
            $user->forceFill([
                'whatsapp_totp_secret' => $pending,
                'whatsapp_totp_confirmed_at' => now(),
            ])->save();
            $request->session()->forget('whatsapp_totp_pending_secret');
            $this->audit($request, null, 'totp_setup');
        } else {
            $secret = $user->whatsapp_totp_secret;
            if (! is_string($secret) || $secret === '' || ! $this->totp->verify($secret, $code)) {
                return back()
                    ->withErrors(['code' => 'Código incorrecto o caducado.'])
                    ->withInput();
            }
            $this->audit($request, null, 'totp_verify');
        }

        $request->session()->put('whatsapp_totp_session_ok', true);

        $target = $validated['redirect'] ?? null;
        if (is_string($target) && $target !== '') {
            $appUrl = rtrim((string) config('app.url'), '/');
            if (str_starts_with($target, $appUrl)) {
                return redirect()->to($target);
            }
            $path = parse_url($target, PHP_URL_PATH);
            if (is_string($path) && str_starts_with($path, '/')) {
                $query = parse_url($target, PHP_URL_QUERY);
                $fragment = parse_url($target, PHP_URL_FRAGMENT);
                $to = $path.($query ? '?'.$query : '').($fragment ? '#'.$fragment : '');

                return redirect()->to($to);
            }
        }

        return redirect()->route('whatsapp-chats.admin.index');
    }

    public function index(Request $request)
    {
        $chats = WhatsAppChatArchive::query()
            ->select([
                'id',
                'title',
                'original_zip_name',
                'imported_at',
                'message_parts_count',
                'import_status',
                'import_error',
            ])
            ->orderByDesc('imported_at')
            ->paginate(15)
            ->withQueryString();

        $this->audit($request, null, 'list');

        return view('admin.whatsapp-chats.index', [
            'pageTitle' => 'Chats WhatsApp',
            'pageDescription' => 'Respaldo cifrado de exportaciones. Requiere permiso y código TOTP (una vez por sesión).',
            'chats' => $chats,
            'maxUploadMb' => (int) config('whatsapp_chats.max_upload_mb', 768),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $maxKb = (int) config('whatsapp_chats.max_upload_kb', 786432);
        $maxMb = (int) config('whatsapp_chats.max_upload_mb', 768);

        $validated = $request->validate([
            'archivo_zip' => ['required', 'file', 'mimes:zip', 'max:'.$maxKb],
        ], [
            'archivo_zip.required' => 'Selecciona un archivo ZIP de exportación de WhatsApp.',
            'archivo_zip.mimes' => 'El archivo debe ser un ZIP (.zip).',
            'archivo_zip.max' => 'El ZIP supera el límite permitido por la aplicación (~'.$maxMb.' MB). Si necesitas más, sube WHATSAPP_CHATS_MAX_UPLOAD_MB en .env y aumenta upload_max_filesize y post_max_size en PHP (y el límite del servidor web).',
        ]);

        $user = $request->user();

        $diskName = (string) config('whatsapp_chats.storage_disk', 'whatsapp_chats');
        $disk = Storage::disk($diskName);

        $uploadedFile = $validated['archivo_zip'];
        $originalZipName = $uploadedFile->getClientOriginalName();
        $zipUuid = (string) \Illuminate\Support\Str::uuid();

        $extractDirRelative = 'whatsapp-chats/'.$zipUuid;
        $extractDirAbsolute = $disk->path($extractDirRelative);
        File::ensureDirectoryExists($extractDirAbsolute);

        $uploadedFile->move($extractDirAbsolute, 'upload.zip');

        $chat = WhatsAppChatArchive::create([
            'title' => 'Importando…',
            'original_zip_name' => $originalZipName,
            'storage_root_path' => $extractDirRelative,
            'message_parts' => [],
            'message_parts_count' => 0,
            'created_by' => $user->id,
            'is_encrypted' => false,
            'wrapped_dek' => null,
            'encrypted_key_version' => 1,
            'storage_disk' => $diskName,
            'import_status' => WhatsAppChatArchive::IMPORT_STATUS_PROCESSING,
            'import_error' => null,
            'import_progress' => 0,
            'import_phase' => 'En cola…',
        ]);

        $user->notify(new WhatsAppChatImportProgressNotification($chat->id, $originalZipName));

        WhatsAppChatArchiveImportJob::dispatch($chat->id, (int) $user->id)->afterResponse();

        $this->audit($request, $chat->id, 'import_queued', $extractDirRelative);

        return redirect()
            ->route('whatsapp-chats.admin.index')
            ->with('status', 'El ZIP se está procesando en segundo plano. Sigue el avance en notificaciones (campana).');
    }

    public function importStatus(Request $request, WhatsAppChatArchive $chat): JsonResponse
    {
        return response()->json([
            'progress' => (int) ($chat->import_progress ?? 0),
            'phase' => (string) ($chat->import_phase ?? ''),
            'status' => (string) $chat->import_status,
            'done' => $chat->import_status !== WhatsAppChatArchive::IMPORT_STATUS_PROCESSING,
        ]);
    }

    public function show(Request $request, WhatsAppChatArchive $chat)
    {
        if ($chat->import_status === WhatsAppChatArchive::IMPORT_STATUS_PROCESSING) {
            return redirect()
                ->route('whatsapp-chats.admin.index')
                ->with('error', 'Esta importación sigue procesándose. Te avisaremos por notificación cuando esté lista.');
        }
        if ($chat->import_status === WhatsAppChatArchive::IMPORT_STATUS_FAILED) {
            return redirect()
                ->route('whatsapp-chats.admin.index')
                ->with('error', 'Esta importación falló: '.(string) ($chat->import_error ?? 'sin detalle'));
        }

        $disk = $chat->disk();
        $messageParts = WhatsAppChatPathNormalizer::expandStoragePaths(
            is_array($chat->message_parts) ? $chat->message_parts : [],
            (string) $chat->storage_root_path
        );
        $dek = null;
        if ($chat->is_encrypted) {
            if (empty($chat->wrapped_dek)) {
                abort(500, 'Registro cifrado sin DEK envuelta.');
            }
            $dek = $this->encryption->unwrapDek((string) $chat->wrapped_dek);
        }

        $messageUrls = collect($messageParts)->map(function (string $relPath) use ($chat) {
            return route('whatsapp-chats.admin.media', [
                'chat' => $chat->id,
                'file' => $relPath,
            ]);
        });

        if ($messageUrls->isEmpty()) {
            abort(404, 'No se encontraron mensajes en el ZIP importado.');
        }

        $txtPartPath = collect($messageParts)->first(function (string $path) {
            return str_ends_with(mb_strtolower($path), '.txt');
        });

        $txtMessages = collect();
        if (is_string($txtPartPath) && $txtPartPath !== '') {
            $txtMessages = $this->buildTxtMessagesCollection($chat, $disk, $txtPartPath, (string) $chat->storage_root_path, $dek);
        }

        $txtMessages = $txtMessages->map(function (array $msg) use ($chat) {
            if (! empty($msg['media_rel_path'])) {
                $msg['media_url'] = route('whatsapp-chats.admin.media', [
                    'chat' => $chat->id,
                    'file' => (string) $msg['media_rel_path'],
                ]);
            } else {
                $msg['media_url'] = null;
            }

            return $msg;
        })->values();

        $txtMessages = $txtMessages->map(function (array $msg) {
            $msg['datetime_ts'] = $this->parseMessageDatetimeToTimestamp((string) ($msg['datetime_raw'] ?? ''));

            return $msg;
        });

        $waPreviewMode = $txtMessages->isNotEmpty() ? 'txt' : 'html';

        $this->audit($request, $chat->id, 'view');

        return view('admin.whatsapp-chats.show', [
            'pageTitle' => 'Chat: '.$chat->title,
            'chat' => $chat,
            'messageParts' => $messageParts,
            'messageUrls' => $messageUrls,
            'activePartIndex' => 0,
            'txtPartPath' => $txtPartPath,
            'txtMessages' => $txtMessages,
            'waPreviewMode' => $waPreviewMode,
        ]);
    }

    public function media(Request $request, WhatsAppChatArchive $chat): Response
    {
        abort_unless($chat->import_status === WhatsAppChatArchive::IMPORT_STATUS_READY, 404);

        $file = trim((string) $request->query('file', ''), '/');
        abort_if($file === '', 404);

        $file = str_replace('\\', '/', $file);
        $root = trim(str_replace('\\', '/', (string) $chat->storage_root_path), '/');
        abort_if($root === '', 404);

        if (! str_starts_with($file, $root.'/')) {
            abort(403);
        }

        $disk = $chat->disk();
        abort_unless($disk->exists($file), 404);

        $dek = null;
        if ($chat->is_encrypted) {
            $dek = $this->encryption->unwrapDek((string) $chat->wrapped_dek);
        }

        [$contents, $mime] = $chat->is_encrypted && $dek
            ? $this->encryption->decryptDiskFileForHttp($disk, $file, $dek, false)
            : [(string) $disk->get($file), $this->guessMimeFromPath($file)];

        $this->audit($request, $chat->id, 'media', $file);

        return response($contents, 200, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="'.basename($file).'"',
        ]);
    }

    public function destroy(Request $request, WhatsAppChatArchive $chat): RedirectResponse
    {
        $storageRoot = trim((string) $chat->storage_root_path, '/');
        $disk = $chat->disk();

        if ($storageRoot !== '') {
            $disk->deleteDirectory($storageRoot);
        } else {
            $parts = WhatsAppChatPathNormalizer::expandStoragePaths(
                is_array($chat->message_parts) ? $chat->message_parts : [],
                $storageRoot
            );
            foreach ($parts as $relPath) {
                $relPath = trim((string) $relPath, '/');
                if ($relPath !== '') {
                    $disk->delete($relPath);
                }
            }
        }

        $rootSegments = explode('/', str_replace('\\', '/', $storageRoot));
        if (count($rootSegments) >= 2) {
            $container = implode('/', array_slice($rootSegments, 0, 2));
            if ($container !== '' && count($disk->allFiles($container)) === 0) {
                $disk->deleteDirectory($container);
            }
        }

        $this->audit($request, $chat->id, 'delete', $storageRoot);

        $chat->delete();

        return redirect()
            ->route('whatsapp-chats.admin.index')
            ->with('status', 'Exportación de chat eliminada correctamente.');
    }

    private function audit(Request $request, ?int $chatId, string $action, ?string $resourcePath = null): void
    {
        try {
            WhatsAppChatAccessLog::create([
                'whatsapp_chat_archive_id' => $chatId,
                'user_id' => $request->user()?->id,
                'action' => $action,
                'resource_path' => $resourcePath,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 2000),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // no bloquear flujo si falla auditoría
        }
    }

    private function buildTxtMessagesCollection(
        WhatsAppChatArchive $chat,
        \Illuminate\Contracts\Filesystem\Filesystem $disk,
        string $txtRelativePath,
        string $chatRootRelativePath,
        ?string $dek
    ): Collection {
        $raw = '';
        if ($chat->is_encrypted && $dek) {
            $raw = $this->encryption->decryptDiskFileToString($disk, $txtRelativePath, $dek, false);
        } else {
            $raw = (string) $disk->get($txtRelativePath);
        }

        $mediaIndex = $this->buildMediaIndex($disk, $chatRootRelativePath);

        return $this->parseTxtRawToMessages($raw, $mediaIndex);
    }

    /**
     * @param  array<string,string>  $mediaIndex
     * @return Collection<int, array<string,mixed>>
     */
    private function parseTxtRawToMessages(string $raw, array $mediaIndex): Collection
    {
        $lines = preg_split('/\\r\\n|\\r|\\n/u', $raw) ?: [];

        $messages = [];
        $current = null;

        foreach ($lines as $line) {
            $line = (string) $line;

            if (preg_match('/^\\[(.*?)\\]\\s([^:]+):\\s(.*)$/u', $line, $m)) {
                if ($current !== null) {
                    $messages[] = $current;
                }

                $current = [
                    'datetime_raw' => trim((string) $m[1]),
                    'author' => trim((string) $m[2]),
                    'text' => trim((string) $m[3]),
                ];

                continue;
            }

            if ($current !== null) {
                $current['text'] .= "\n".$line;
            }
        }

        if ($current !== null) {
            $messages[] = $current;
        }

        return collect($messages)->values()->map(function (array $msg, int $idx) use ($mediaIndex) {
            $filename = $this->extractMediaFilenameFromText((string) ($msg['text'] ?? ''));
            $mediaRelPath = null;
            $mediaKind = null;
            $mediaIsSticker = false;

            if (is_string($filename) && $filename !== '') {
                $key = mb_strtolower($filename);
                $mediaRelPath = $mediaIndex[$key] ?? null;
                if (is_string($mediaRelPath) && $mediaRelPath !== '') {
                    $mediaKind = $this->resolveMediaKindFromFilename($filename);
                    $mediaIsSticker = $this->isStickerFilename($filename);
                }
            }

            return [
                'index' => $idx + 1,
                'datetime_raw' => (string) ($msg['datetime_raw'] ?? ''),
                'author' => (string) ($msg['author'] ?? ''),
                'text' => (string) ($msg['text'] ?? ''),
                'media_filename' => $filename,
                'media_rel_path' => $mediaRelPath,
                'media_kind' => $mediaKind,
                'media_is_sticker' => $mediaIsSticker,
            ];
        });
    }

    /**
     * @return array<string,string>
     */
    private function buildMediaIndex(\Illuminate\Contracts\Filesystem\Filesystem $disk, string $chatRootRelativePath): array
    {
        $chatRootRelativePath = trim(str_replace('\\', '/', $chatRootRelativePath), '/');
        if ($chatRootRelativePath === '' || ! $disk->exists($chatRootRelativePath)) {
            return [];
        }

        $files = $disk->allFiles($chatRootRelativePath);
        $index = [];

        foreach ($files as $fileRelPath) {
            $fileRelPath = str_replace('\\', '/', (string) $fileRelPath);
            $base = basename($fileRelPath);
            if ($base === '' || strcasecmp($base, 'upload.zip') === 0) {
                continue;
            }
            $index[mb_strtolower($base)] = $fileRelPath;
        }

        return $index;
    }

    private function extractMediaFilenameFromText(string $text): ?string
    {
        if ($text === '') {
            return null;
        }

        if (preg_match('/([A-Za-z0-9._\\-]+\\.(?:jpg|jpeg|png|gif|webp|bmp|mp4|mov|avi|mkv|3gp|opus|ogg|oga|mp3|m4a|aac|pdf|doc|docx|xls|xlsx|ppt|pptx|txt|vcf))/iu', $text, $m)) {
            return trim((string) $m[1]);
        }

        return null;
    }

    private function resolveMediaKindFromFilename(string $filename): string
    {
        $ext = mb_strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)) {
            return 'image';
        }
        if (in_array($ext, ['mp4', 'mov', 'avi', 'mkv', '3gp'], true)) {
            return 'video';
        }
        if (in_array($ext, ['opus', 'ogg', 'oga', 'mp3', 'm4a', 'aac'], true)) {
            return 'audio';
        }

        return 'file';
    }

    private function isStickerFilename(string $filename): bool
    {
        $name = mb_strtolower($filename);

        return str_contains($name, '-sticker-') || str_ends_with($name, '.webp');
    }

    /**
     * Intento de parsear la marca de tiempo del export TXT (WhatsApp) para filtros por fecha.
     */
    private function parseMessageDatetimeToTimestamp(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->timestamp;
        } catch (\Throwable) {
            return null;
        }
    }

    private function guessMimeFromPath(string $relativePath): string
    {
        $ext = mb_strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'opus', 'ogg', 'oga' => 'audio/ogg',
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain; charset=UTF-8',
            'html', 'htm' => 'text/html; charset=UTF-8',
            default => 'application/octet-stream',
        };
    }
}
