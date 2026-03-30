<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\WhatsAppChatArchiveImportJob;
use App\Models\WhatsAppChatAccessLog;
use App\Notifications\WhatsAppChatImportProgressNotification;
use App\Models\WhatsAppChatArchive;
use App\Models\WhatsAppChatArchiveUploadFile;
use App\Services\WhatsApp\WhatsAppChatEncryptionService;
use App\Services\WhatsApp\WhatsAppChatPathNormalizer;
use App\Services\WhatsApp\WhatsAppTotpService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
        $select = [
            'id',
            'title',
            'original_zip_name',
            'imported_at',
            'message_parts_count',
            'import_status',
            'import_error',
        ];

        if (Schema::hasColumn('whatsapp_chat_archives', 'folder_total_files')) {
            $select[] = 'folder_total_files';
        }
        if (Schema::hasColumn('whatsapp_chat_archives', 'folder_uploaded_files')) {
            $select[] = 'folder_uploaded_files';
        }

        $chats = WhatsAppChatArchive::query()
            ->select($select)
            ->orderByDesc('imported_at')
            ->paginate(15)
            ->withQueryString();

        $this->audit($request, null, 'list');

        return view('admin.whatsapp-chats.index', [
            'pageTitle' => 'Chats WhatsApp',
            'pageDescription' => 'Respaldo cifrado de exportaciones. Requiere permiso y código TOTP (una vez por sesión).',
            'chats' => $chats,
            'maxUploadMb' => (int) config('whatsapp_chats.max_upload_mb', 768),
            'folderUploadRequestMaxFiles' => (int) config('whatsapp_chats.folder_import_request_max_files', 8),
            'folderUploadParallelRequests' => (int) config('whatsapp_chats.folder_import_parallel_requests', 4),
            'folderUploadRequestTargetBytes' => (int) config('whatsapp_chats.folder_import_request_target_bytes', 24 * 1024 * 1024),
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
        $zipUuid = (string) Str::uuid();

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

    public function storeFolderFile(Request $request): JsonResponse
    {
        if ($schemaError = $this->ensureFolderUploadSchemaReady()) {
            return $schemaError;
        }

        $maxKb = (int) config('whatsapp_chats.max_upload_kb', 786432);
        $maxMb = (int) config('whatsapp_chats.max_upload_mb', 768);
        $maxFiles = (int) config('whatsapp_chats.folder_import_max_files', 25000);
        $maxRequestFiles = (int) config('whatsapp_chats.folder_import_request_max_files', 8);

        $validated = $request->validate([
            'batch_token' => ['required', 'uuid'],
            'folder_signature' => ['required', 'string', 'size:64'],
            'folder_total_files' => ['required', 'integer', 'min:1', 'max:'.$maxFiles],
            'label' => ['nullable', 'string', 'max:255'],
            'root_name' => ['nullable', 'string', 'max:255'],
            'relative_paths' => ['required', 'array', 'min:1', 'max:'.$maxRequestFiles],
            'relative_paths.*' => ['required', 'string', 'max:4096'],
            'file_sizes' => ['nullable', 'array'],
            'file_sizes.*' => ['nullable', 'integer', 'min:0'],
            'last_modifieds' => ['nullable', 'array'],
            'last_modifieds.*' => ['nullable', 'integer', 'min:0'],
            'files' => ['required', 'array', 'min:1', 'max:'.$maxRequestFiles],
            'files.*' => ['required', 'file', 'max:'.$maxKb],
        ], [
            'files.*.max' => 'Cada archivo supera el límite permitido (~'.$maxMb.' MB).',
        ]);

        $relativePaths = array_values($validated['relative_paths'] ?? []);
        $files = array_values($request->file('files', []));
        $fileSizes = array_values($validated['file_sizes'] ?? []);
        $lastModifieds = array_values($validated['last_modifieds'] ?? []);

        if (count($relativePaths) !== count($files)) {
            return response()->json([
                'message' => 'El lote enviado no coincide: faltan rutas o archivos.',
            ], 422);
        }

        $folderSignature = strtolower((string) $validated['folder_signature']);
        $folderTotalFiles = (int) $validated['folder_total_files'];
        $label = trim((string) ($validated['label'] ?? ''));
        $rootName = trim((string) ($validated['root_name'] ?? ''));
        $diskName = (string) config('whatsapp_chats.storage_disk', 'whatsapp_chats');
        $disk = Storage::disk($diskName);

        $archive = $this->resolveFolderUploadArchiveLocked(
            $request,
            $folderSignature,
            $label,
            $rootName,
            $folderTotalFiles,
            $diskName
        );

        if ($archive->import_status === WhatsAppChatArchive::IMPORT_STATUS_READY) {
            return response()->json([
                'ok' => true,
                'archive_id' => $archive->id,
                'already_imported' => true,
                'uploaded_in_archive' => (int) ($archive->folder_uploaded_files ?? 0),
                'message' => 'La carpeta ya estaba importada y cifrada. Se reutiliza el registro existente.',
            ]);
        }

        if ($archive->import_status === WhatsAppChatArchive::IMPORT_STATUS_PROCESSING) {
            return response()->json([
                'message' => 'Esta carpeta ya se está importando en segundo plano. Espera a que termine antes de reenviarla.',
            ], 409);
        }

        $normalizedItems = [];
        foreach ($relativePaths as $index => $relativePath) {
            try {
                $relativeSafe = $this->sanitizeFolderRelativePath((string) $relativePath);
            } catch (ValidationException $e) {
                return response()->json([
                    'message' => 'Ruta inválida.',
                    'errors' => $e->errors(),
                ], 422);
            }

            $lower = mb_strtolower($relativeSafe);
            if (str_contains($lower, '__macosx/') || $lower === '.ds_store' || str_ends_with($lower, '/.ds_store') || $lower === 'thumbs.db' || str_ends_with($lower, '/thumbs.db')) {
                continue;
            }

            $normalizedItems[] = [
                'relative_path' => $relativeSafe,
                'file' => $files[$index] ?? null,
                'file_size' => (int) ($fileSizes[$index] ?? (($files[$index] ?? null)?->getSize() ?? 0)),
                'last_modified' => isset($lastModifieds[$index]) ? (int) $lastModifieds[$index] : null,
            ];
        }

        if ($normalizedItems === []) {
            return response()->json([
                'ok' => true,
                'archive_id' => $archive->id,
                'uploaded' => 0,
                'skipped' => count($relativePaths),
                'uploaded_in_archive' => (int) ($archive->folder_uploaded_files ?? 0),
            ]);
        }

        $existingByPath = $archive->uploadFiles()
            ->whereIn('relative_path', array_column($normalizedItems, 'relative_path'))
            ->get()
            ->keyBy('relative_path');

        $rowsToUpsert = [];
        $uploadedCount = 0;
        $skippedCount = 0;

        foreach ($normalizedItems as $item) {
            $uploadedFile = $item['file'];
            if ($uploadedFile === null) {
                continue;
            }

            $relativeSafe = (string) $item['relative_path'];
            $targetRel = trim((string) $archive->storage_root_path, '/').'/'.$relativeSafe;
            $absoluteTarget = $disk->path($targetRel);
            $existing = $existingByPath->get($relativeSafe);

            if ($existing && $disk->exists($targetRel)
                && (int) $existing->file_size === (int) $item['file_size']
                && (int) ($existing->client_last_modified_at ?? 0) === (int) ($item['last_modified'] ?? 0)) {
                $skippedCount++;
                continue;
            }

            File::ensureDirectoryExists(dirname($absoluteTarget));
            if (is_file($absoluteTarget)) {
                File::delete($absoluteTarget);
            }

            $uploadedFile->move(dirname($absoluteTarget), basename($absoluteTarget));

            $rowsToUpsert[] = [
                'whatsapp_chat_archive_id' => $archive->id,
                'relative_path' => $relativeSafe,
                'relative_path_hash' => hash('sha256', $relativeSafe),
                'file_size' => (int) $item['file_size'],
                'client_last_modified_at' => $item['last_modified'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $uploadedCount++;
        }

        if ($rowsToUpsert !== []) {
            WhatsAppChatArchiveUploadFile::query()->upsert(
                $rowsToUpsert,
                ['whatsapp_chat_archive_id', 'relative_path_hash'],
                ['file_size', 'client_last_modified_at', 'updated_at']
            );
        }

        $uploadedInArchive = $archive->uploadFiles()->count();
        $archive->forceFill([
            'folder_total_files' => max($folderTotalFiles, (int) ($archive->folder_total_files ?? 0)),
            'folder_uploaded_files' => $uploadedInArchive,
            'import_status' => WhatsAppChatArchive::IMPORT_STATUS_UPLOADING,
            'import_error' => null,
            'import_progress' => 0,
            'import_phase' => 'Recibiendo archivos…',
        ])->save();

        return response()->json([
            'ok' => true,
            'archive_id' => $archive->id,
            'uploaded' => $uploadedCount,
            'skipped' => $skippedCount,
            'uploaded_in_archive' => $uploadedInArchive,
            'total_expected' => max($folderTotalFiles, (int) ($archive->folder_total_files ?? 0)),
        ]);
    }

    public function finalizeFolderUpload(Request $request): JsonResponse
    {
        if ($schemaError = $this->ensureFolderUploadSchemaReady()) {
            return $schemaError;
        }

        $validated = $request->validate([
            'folder_signature' => ['required', 'string', 'size:64'],
            'folder_total_files' => ['required', 'integer', 'min:1'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $userId = (int) $user->id;
        $folderSignature = strtolower((string) $validated['folder_signature']);

        $lock = Cache::lock('wa-folder-archive:'.$userId.':'.$folderSignature, 120);
        $chat = $lock->block(90, function () use ($userId, $folderSignature) {
            $this->consolidateDuplicateFolderArchives($userId, $folderSignature);

            return $this->findFolderUploadArchive($userId, $folderSignature);
        });

        if (! $chat) {
            return response()->json([
                'message' => 'No existe un lote previo para esa carpeta. Vuelve a elegirla para iniciar la subida.',
            ], 422);
        }

        if ($chat->import_status === WhatsAppChatArchive::IMPORT_STATUS_READY) {
            return response()->json([
                'ok' => true,
                'redirect' => route('whatsapp-chats.admin.show', ['chat' => $chat->id]),
                'message' => 'La carpeta ya estaba importada. Se abrió el chat existente.',
            ]);
        }

        if ($chat->import_status === WhatsAppChatArchive::IMPORT_STATUS_PROCESSING) {
            return response()->json([
                'ok' => true,
                'redirect' => route('whatsapp-chats.admin.index'),
                'message' => 'La importación de esta carpeta ya está en proceso.',
            ]);
        }

        $uploadedFilesCount = $chat->uploadFiles()->count();
        $expectedFilesCount = max($uploadedFilesCount, (int) ($validated['folder_total_files'] ?? 0), (int) ($chat->folder_total_files ?? 0));

        $chat->forceFill([
            'original_zip_name' => trim((string) ($validated['label'] ?? '')) ?: (string) ($chat->original_zip_name ?? 'Carpeta importada'),
            'folder_total_files' => $expectedFilesCount,
            'folder_uploaded_files' => $uploadedFilesCount,
        ])->save();

        if ($uploadedFilesCount < 1) {
            return response()->json([
                'message' => 'La carpeta todavía no tiene archivos registrados. Reintenta la subida antes de finalizar.',
            ], 422);
        }

        if ($uploadedFilesCount < $expectedFilesCount) {
            return response()->json([
                'message' => 'Faltan '.($expectedFilesCount - $uploadedFilesCount).' archivos por subir. Repite la selección de carpeta y solo se enviará lo pendiente.',
            ], 422);
        }

        $chat->forceFill([
            'title' => 'Importando…',
            'import_status' => WhatsAppChatArchive::IMPORT_STATUS_PROCESSING,
            'import_error' => null,
            'import_progress' => 0,
            'import_phase' => 'En cola…',
        ])->save();

        $user->notify(new WhatsAppChatImportProgressNotification($chat->id, (string) $chat->original_zip_name));

        WhatsAppChatArchiveImportJob::dispatch($chat->id, (int) $user->id, true)->afterResponse();

        $this->audit($request, $chat->id, 'import_queued_folder', (string) $chat->storage_root_path);

        return response()->json([
            'ok' => true,
            'redirect' => route('whatsapp-chats.admin.index'),
            'message' => 'Carpeta validada. La importación continúa en segundo plano; revisa la campana de notificaciones.',
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function sanitizeFolderRelativePath(string $raw): string
    {
        $raw = str_replace('\\', '/', trim($raw));
        $raw = ltrim($raw, '/');
        $segments = [];
        foreach (explode('/', $raw) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }

            $seg = mb_convert_encoding($seg, 'UTF-8', 'UTF-8,ISO-8859-1,Windows-1252');

            if ($seg === '..') {
                throw ValidationException::withMessages([
                    'relative_path' => ['La ruta del archivo no es válida.'],
                ]);
            }
            if (str_contains($seg, "\0")) {
                throw ValidationException::withMessages([
                    'relative_path' => ['La ruta del archivo no es válida.'],
                ]);
            }
            if (! mb_check_encoding($seg, 'UTF-8') || preg_match('/[\x00-\x1F\x7F]/u', $seg) === 1) {
                throw ValidationException::withMessages([
                    'relative_path' => ['La ruta del archivo no es válida.'],
                ]);
            }
            $segments[] = $seg;
        }
        if ($segments === []) {
            throw ValidationException::withMessages([
                'relative_path' => ['Ruta vacía.'],
            ]);
        }

        return implode('/', $segments);
    }

    private function resolveFolderUploadArchiveLocked(
        Request $request,
        string $folderSignature,
        string $label,
        string $rootName,
        int $totalFiles,
        string $diskName
    ): WhatsAppChatArchive {
        $userId = (int) $request->user()->id;
        $lock = Cache::lock('wa-folder-archive:'.$userId.':'.$folderSignature, 120);

        return $lock->block(90, function () use ($userId, $folderSignature, $label, $rootName, $totalFiles, $diskName) {
            $this->consolidateDuplicateFolderArchives($userId, $folderSignature);

            return $this->resolveFolderUploadArchive($userId, $folderSignature, $label, $rootName, $totalFiles, $diskName);
        });
    }

    private function resolveFolderUploadArchive(
        int $userId,
        string $folderSignature,
        string $label,
        string $rootName,
        int $totalFiles,
        string $diskName
    ): WhatsAppChatArchive {
        $existing = $this->findFolderUploadArchive($userId, $folderSignature);
        if ($existing) {
            $displayName = $label !== '' ? $label : ($rootName !== '' ? $rootName : (string) ($existing->original_zip_name ?? 'Carpeta importada'));

            $existing->forceFill([
                'original_zip_name' => $displayName,
                'folder_root_name' => $rootName !== '' ? $rootName : (string) ($existing->folder_root_name ?? ''),
                'folder_total_files' => max($totalFiles, (int) ($existing->folder_total_files ?? 0)),
            ])->save();

            return $existing;
        }

        $displayName = $label !== '' ? $label : ($rootName !== '' ? $rootName : 'Carpeta importada');

        return WhatsAppChatArchive::create([
            'title' => 'Recibiendo archivos…',
            'original_zip_name' => $displayName,
            'storage_root_path' => 'whatsapp-chats/'.Str::uuid(),
            'message_parts' => [],
            'message_parts_count' => 0,
            'created_by' => $userId,
            'is_encrypted' => false,
            'wrapped_dek' => null,
            'encrypted_key_version' => 1,
            'storage_disk' => $diskName,
            'import_status' => WhatsAppChatArchive::IMPORT_STATUS_UPLOADING,
            'import_error' => null,
            'import_progress' => 0,
            'import_phase' => 'Recibiendo archivos…',
            'folder_source_signature' => $folderSignature,
            'folder_root_name' => $rootName !== '' ? $rootName : null,
            'folder_total_files' => $totalFiles,
            'folder_uploaded_files' => 0,
        ]);
    }

    private function consolidateDuplicateFolderArchives(int $userId, string $folderSignature): void
    {
        $archives = WhatsAppChatArchive::query()
            ->where('created_by', $userId)
            ->where('folder_source_signature', $folderSignature)
            ->where('import_status', WhatsAppChatArchive::IMPORT_STATUS_UPLOADING)
            ->orderBy('id')
            ->get();

        if ($archives->count() < 2) {
            return;
        }

        $master = $archives->first();
        $maxTotal = (int) $archives->max(fn (WhatsAppChatArchive $a) => (int) ($a->folder_total_files ?? 0));

        foreach ($archives->skip(1) as $slave) {
            $this->mergeFolderUploadSlaveIntoMaster($slave, $master);
        }

        $master->refresh();
        $master->forceFill([
            'folder_uploaded_files' => $master->uploadFiles()->count(),
            'folder_total_files' => max((int) ($master->folder_total_files ?? 0), $maxTotal),
        ])->save();
    }

    private function mergeFolderUploadSlaveIntoMaster(WhatsAppChatArchive $slave, WhatsAppChatArchive $master): void
    {
        if ((int) $slave->id === (int) $master->id) {
            return;
        }

        $diskName = (string) ($master->storage_disk ?? config('whatsapp_chats.storage_disk', 'whatsapp_chats'));
        $disk = Storage::disk($diskName);
        $masterRoot = trim((string) $master->storage_root_path, '/');
        $slaveRoot = trim((string) $slave->storage_root_path, '/');

        $files = WhatsAppChatArchiveUploadFile::query()
            ->where('whatsapp_chat_archive_id', $slave->id)
            ->get();

        foreach ($files as $uf) {
            $rel = (string) $uf->relative_path;
            $srcKey = $slaveRoot !== '' ? $slaveRoot.'/'.$rel : $rel;
            $destKey = $masterRoot !== '' ? $masterRoot.'/'.$rel : $rel;

            $existsOnMaster = WhatsAppChatArchiveUploadFile::query()
                ->where('whatsapp_chat_archive_id', $master->id)
                ->where('relative_path_hash', (string) $uf->relative_path_hash)
                ->exists();

            if ($existsOnMaster) {
                if ($disk->exists($srcKey)) {
                    $disk->delete($srcKey);
                }
                $uf->delete();

                continue;
            }

            if ($disk->exists($srcKey)) {
                $absoluteDest = $disk->path($destKey);
                File::ensureDirectoryExists(dirname($absoluteDest));
                if ($disk->exists($destKey)) {
                    $disk->delete($destKey);
                }
                $disk->move($srcKey, $destKey);
            }

            $uf->forceFill(['whatsapp_chat_archive_id' => $master->id])->save();
        }

        if ($slaveRoot !== '' && $slaveRoot !== $masterRoot && $disk->exists($slaveRoot)) {
            $disk->deleteDirectory($slaveRoot);
        }

        $slave->delete();
    }

    private function findFolderUploadArchive(int $userId, string $folderSignature): ?WhatsAppChatArchive
    {
        return WhatsAppChatArchive::query()
            ->where('created_by', $userId)
            ->where('folder_source_signature', $folderSignature)
            ->orderBy('id')
            ->first();
    }


    private function ensureFolderUploadSchemaReady(): ?JsonResponse
    {
        $missing = [];

        if (! Schema::hasTable('whatsapp_chat_archives')) {
            $missing[] = 'whatsapp_chat_archives';
        } else {
            foreach (['folder_source_signature', 'folder_root_name', 'folder_total_files', 'folder_uploaded_files'] as $column) {
                if (! Schema::hasColumn('whatsapp_chat_archives', $column)) {
                    $missing[] = 'whatsapp_chat_archives.'.$column;
                }
            }
        }

        if (! Schema::hasTable('whatsapp_chat_archive_upload_files')) {
            $missing[] = 'whatsapp_chat_archive_upload_files';
        } elseif (! Schema::hasColumn('whatsapp_chat_archive_upload_files', 'relative_path_hash')) {
            $missing[] = 'whatsapp_chat_archive_upload_files.relative_path_hash';
        }

        if ($missing === []) {
            return null;
        }

        Log::warning('WhatsApp folder upload blocked due to incomplete schema.', [
            'missing' => $missing,
        ]);

        return response()->json([
            'message' => 'La carga por carpeta requiere completar migraciones de base de datos. Ejecuta php artisan migrate y vuelve a intentar.',
            'missing_schema' => $missing,
        ], 409);
    }

    public function importStatus(Request $request, WhatsAppChatArchive $chat): JsonResponse
    {
        return response()->json([
            'progress' => (int) ($chat->import_progress ?? 0),
            'phase' => (string) ($chat->import_phase ?? ''),
            'status' => (string) $chat->import_status,
            'done' => in_array($chat->import_status, [WhatsAppChatArchive::IMPORT_STATUS_READY, WhatsAppChatArchive::IMPORT_STATUS_FAILED], true),
        ]);
    }

    public function show(Request $request, WhatsAppChatArchive $chat)
    {
        if (in_array($chat->import_status, [WhatsAppChatArchive::IMPORT_STATUS_UPLOADING, WhatsAppChatArchive::IMPORT_STATUS_PROCESSING], true)) {
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
        $txtPreviewTruncated = false;
        $txtPreviewSkippedLargeFile = false;

        if (is_string($txtPartPath) && $txtPartPath !== '') {
            $maxTxtBytes = (int) config('whatsapp_chats.txt_preview_max_file_bytes', 15728640);
            try {
                $txtSize = $disk->size($txtPartPath);
            } catch (\Throwable) {
                $txtSize = PHP_INT_MAX;
            }

            if ($txtSize > $maxTxtBytes) {
                $txtPreviewSkippedLargeFile = true;
            } else {
                $maxMsgs = (int) config('whatsapp_chats.txt_preview_max_messages', 5000);
                $parsed = $this->buildTxtMessagesCollection($chat, $disk, $txtPartPath, (string) $chat->storage_root_path, $dek, $maxMsgs);
                $txtMessages = $parsed['collection'];
                $txtPreviewTruncated = $parsed['truncated'];
            }
        }

        $txtMessages = $txtMessages->map(function (array $msg) use ($chat) {
            $msg['media_items'] = collect($msg['media_items'] ?? [])->map(function (array $item) use ($chat) {
                if (! empty($item['media_rel_path'])) {
                    $item['media_url'] = route('whatsapp-chats.admin.media', [
                        'chat' => $chat->id,
                        'file' => (string) $item['media_rel_path'],
                    ]);
                } else {
                    $item['media_url'] = null;
                }

                return $item;
            })->values()->all();

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
            'txtPreviewTruncated' => $txtPreviewTruncated,
            'txtPreviewSkippedLargeFile' => $txtPreviewSkippedLargeFile,
            'txtPreviewMaxMessages' => (int) config('whatsapp_chats.txt_preview_max_messages', 5000),
            'txtPreviewMaxFileMb' => (int) config('whatsapp_chats.txt_preview_max_file_mb', 15),
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
        abort_unless((int) $chat->created_by === (int) $request->user()->id, 403);

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
        }
    }

    private function buildTxtMessagesCollection(
        WhatsAppChatArchive $chat,
        \Illuminate\Contracts\Filesystem\Filesystem $disk,
        string $txtRelativePath,
        string $chatRootRelativePath,
        ?string $dek,
        ?int $maxMessages = null
    ): array {
        $raw = '';
        if ($chat->is_encrypted && $dek) {
            $raw = $this->encryption->decryptDiskFileToString($disk, $txtRelativePath, $dek, false);
        } else {
            $raw = (string) $disk->get($txtRelativePath);
        }

        $mediaIndex = $this->buildMediaIndex($disk, $chatRootRelativePath);

        return $this->parseTxtRawToMessages($raw, $mediaIndex, $maxMessages);
    }


    private function parseTxtRawToMessages(string $raw, array $mediaIndex, ?int $maxMessages = null): array
    {
        $lines = preg_split('/\\r\\n|\\r|\\n/u', $raw) ?: [];

        $messages = [];
        $current = null;
        $truncated = false;

        foreach ($lines as $line) {
            $line = (string) $line;
            $line = (string) preg_replace('/^[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]+/u', '', $line);

            if (preg_match('/^\\[(.*?)\\]\\s([^:]+):\\s?(.*)$/u', $line, $m)) {
                if ($current !== null) {
                    $messages[] = $current;
                    if ($maxMessages !== null && count($messages) >= $maxMessages) {
                        $truncated = true;
                        $current = null;
                        break;
                    }
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
            if ($maxMessages === null || count($messages) < $maxMessages) {
            $messages[] = $current;
            } else {
                $truncated = true;
            }
        }

        $collection = collect($messages)->values()->map(function (array $msg, int $idx) use ($mediaIndex) {
            $rawText = (string) ($msg['text'] ?? '');
            $author = (string) ($msg['author'] ?? '');
            $mediaFilenames = $this->extractMediaFilenamesFromText($rawText);
            $cleanText = $this->stripAttachmentTokensFromText($rawText);

            $reactions = [];
            $textReactions = $this->extractEmojiTokens($cleanText);
            if (! empty($textReactions)) {
                $textWithoutEmoji = trim((string) preg_replace('/[\x{2600}-\x{27BF}\x{1F300}-\x{1FAFF}]|\x{FE0F}|\x{200D}|\x{1F3FB}|\x{1F3FC}|\x{1F3FD}|\x{1F3FE}|\x{1F3FF}/u', '', $cleanText));
                if ($textWithoutEmoji === '') {
                    $reactions = $textReactions;
                    $cleanText = '';
                }
            }

            if ($cleanText === '' && empty($reactions)) {
                $authorReactions = $this->extractEmojiTokens($author);
                if (! empty($authorReactions)) {
                    $reactions = $authorReactions;
                    $cleanAuthor = trim((string) preg_replace('/[\x{2600}-\x{27BF}\x{1F300}-\x{1FAFF}]|\x{FE0F}|\x{200D}|\x{1F3FB}|\x{1F3FC}|\x{1F3FD}|\x{1F3FE}|\x{1F3FF}/u', '', $author));
                    if ($cleanAuthor !== '') {
                        $author = $cleanAuthor;
                    }
                }
            }

            $mediaItems = collect($mediaFilenames)->map(function (string $filename) use ($mediaIndex) {
                $key = mb_strtolower($filename);
                $mediaRelPath = $mediaIndex[$key] ?? null;
                $exists = is_string($mediaRelPath) && $mediaRelPath !== '';

                return [
                    'filename' => $filename,
                    'media_rel_path' => $exists ? $mediaRelPath : null,
                    'media_kind' => $this->resolveMediaKindFromFilename($filename),
                    'media_is_sticker' => $this->isStickerFilename($filename),
                ];
            })->values()->all();

            $firstMedia = $mediaItems[0] ?? null;

            return [
                'index' => $idx + 1,
                'datetime_raw' => (string) ($msg['datetime_raw'] ?? ''),
                'author' => $author,
                'text' => $cleanText,
                'media_items' => $mediaItems,
                'media_filename' => is_array($firstMedia) ? (string) ($firstMedia['filename'] ?? '') : null,
                'media_rel_path' => is_array($firstMedia) ? ($firstMedia['media_rel_path'] ?? null) : null,
                'media_kind' => is_array($firstMedia) ? ($firstMedia['media_kind'] ?? null) : null,
                'media_is_sticker' => is_array($firstMedia) ? (bool) ($firstMedia['media_is_sticker'] ?? false) : false,
                'reactions' => $reactions,
            ];
        });

        return [
            'collection' => $collection,
            'truncated' => $truncated,
        ];
    }

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

    private function extractMediaFilenamesFromText(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $result = [];

        if (preg_match_all('/<\\s*adjunto\\s*:\\s*([^<>\\\\\/]+\\.(?:jpg|jpeg|png|gif|webp|bmp|mp4|mov|avi|mkv|3gp|opus|ogg|oga|mp3|m4a|aac|pdf|doc|docx|xls|xlsx|ppt|pptx|txt|vcf))\\s*>/iu', $text, $matches)) {
            foreach (($matches[1] ?? []) as $filename) {
                $filename = trim((string) $filename);
                if ($filename !== '') {
                    $result[] = $filename;
                }
            }
        }

        if (empty($result) && preg_match_all('/([A-Za-z0-9._\\-]+\\.(?:jpg|jpeg|png|gif|webp|bmp|mp4|mov|avi|mkv|3gp|opus|ogg|oga|mp3|m4a|aac|pdf|doc|docx|xls|xlsx|ppt|pptx|txt|vcf))/iu', $text, $matches)) {
            foreach (($matches[1] ?? []) as $filename) {
                $filename = trim((string) $filename);
                if ($filename !== '') {
                    $result[] = $filename;
                }
            }
        }

        $seen = [];
        $unique = [];
        foreach ($result as $filename) {
            $key = mb_strtolower($filename);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $filename;
        }

        return $unique;
    }

    private function extractMediaFilenameFromText(string $text): ?string
    {
        $all = $this->extractMediaFilenamesFromText($text);

        return $all[0] ?? null;
    }

    private function stripAttachmentTokensFromText(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $clean = (string) preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $text);
        $clean = (string) preg_replace('/<\\s*adjunto\\s*:\\s*[^>]+>/iu', ' ', $clean);
        $clean = (string) preg_replace('/\\h+/u', ' ', $clean);
        $clean = (string) preg_replace('/\\s*\\n\\s*/u', "\n", $clean);

        return trim($clean);
    }


    private function extractEmojiTokens(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $matches = [];
        @preg_match_all('/[\\x{2600}-\\x{27BF}\\x{1F300}-\\x{1FAFF}](?:\\x{FE0F}|\\x{1F3FB}|\\x{1F3FC}|\\x{1F3FD}|\\x{1F3FE}|\\x{1F3FF})*/u', $value, $matches);

        $raw = array_values(array_filter(array_map(static function ($token) {
            return trim((string) $token);
        }, $matches[0] ?? []), static function ($token) {
            return $token !== '';
        }));

        if (empty($raw)) {
            return [];
        }

        $seen = [];
        $unique = [];
        foreach ($raw as $token) {
            if (isset($seen[$token])) {
                continue;
            }
            $seen[$token] = true;
            $unique[] = $token;
        }

        return $unique;
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
