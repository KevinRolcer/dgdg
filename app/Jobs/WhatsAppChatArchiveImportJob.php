<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\WhatsAppChatAccessLog;
use App\Models\WhatsAppChatArchive;
use App\Notifications\WhatsAppChatImportProgressNotification;
use App\Services\WhatsApp\WhatsAppChatArchiveImportService;
use App\Services\WhatsApp\WhatsAppImportProgressReporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WhatsAppChatArchiveImportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 14400;

    public int $tries = 1;

    public function __construct(
        public readonly int $archiveId,
        public readonly int $userId
    ) {}

    public function handle(WhatsAppChatArchiveImportService $importService): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        @ini_set('max_execution_time', '0');

        $archive = WhatsAppChatArchive::query()->find($this->archiveId);
        $user = User::query()->find($this->userId);

        if (! $archive || ! $user) {
            return;
        }

        if ($archive->import_status !== WhatsAppChatArchive::IMPORT_STATUS_PROCESSING) {
            return;
        }

        $reporter = new WhatsAppImportProgressReporter($user, $archive);
        $reporter->report(6, 'Iniciando importación…', true);

        try {
            $importService->processFromPendingZip($archive, $reporter->asCallable());
            $archive->refresh();

            try {
                WhatsAppChatAccessLog::create([
                    'whatsapp_chat_archive_id' => $archive->id,
                    'user_id' => $this->userId,
                    'action' => 'import',
                    'resource_path' => $archive->storage_root_path,
                    'ip_address' => null,
                    'user_agent' => 'queue:WhatsAppChatArchiveImportJob',
                    'created_at' => now(),
                ]);
            } catch (\Throwable) {
                // no bloquear
            }

            WhatsAppChatImportProgressNotification::markCompleted($user, $archive);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            $this->markFailedAndNotify($archive, $user, (string) $message);
        } catch (\Throwable $e) {
            Log::error('WhatsApp import job failed: '.$e->getMessage(), [
                'archive_id' => $this->archiveId,
                'user_id' => $this->userId,
            ]);
            $this->markFailedAndNotify($archive, $user, 'Error inesperado al procesar el ZIP.');
        }
    }

    public function failed(?\Throwable $e): void
    {
        $archive = WhatsAppChatArchive::query()->find($this->archiveId);
        $user = User::query()->find($this->userId);

        if (! $archive || $archive->import_status !== WhatsAppChatArchive::IMPORT_STATUS_PROCESSING) {
            return;
        }

        $msg = $e ? substr($e->getMessage(), 0, 2000) : 'El trabajo de importación falló o excedió el tiempo.';
        $this->markFailedAndNotify($archive, $user, $msg);
    }

    private function markFailedAndNotify(WhatsAppChatArchive $archive, User $user, string $message): void
    {
        if ($archive->import_status !== WhatsAppChatArchive::IMPORT_STATUS_PROCESSING) {
            return;
        }

        $archive->forceFill([
            'import_status' => WhatsAppChatArchive::IMPORT_STATUS_FAILED,
            'import_error' => $message,
            'import_phase' => 'Error',
        ])->save();

        $archive->refresh();

        WhatsAppChatImportProgressNotification::markFailed($user, $archive, $message);
    }
}
