<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\ExcelExportCompleted;
use App\Notifications\ExcelExportPending;
use App\Services\Agenda\AgendaFichasPdfBuilderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class GenerateAgendaFichasPdfJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 600;

    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $exportRequestId,
        public readonly array $params,
        public readonly string $storageBaseName,
        public readonly string $downloadFileName,
    ) {}

    public function handle(AgendaFichasPdfBuilderService $builder): void
    {
        @ini_set('memory_limit', '512M');

        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $dir = storage_path('app/agenda-fichas-exports');
        File::ensureDirectoryExists($dir);
        $fullPath = $dir.DIRECTORY_SEPARATOR.$this->storageBaseName;

        try {
            $builder->renderPdfToFile($user, $this->params, $fullPath);
            File::put($fullPath.'.dlname', $this->downloadFileName);

            $url = route('agenda.calendar.fichas-export.download', ['file' => $this->storageBaseName], true);
            $this->updatePendingToCompleted($user, $this->downloadFileName, $url);
        } catch (\Throwable $e) {
            Log::error('Fallo al generar PDF de fichas agenda: '.$e->getMessage(), [
                'user_id' => $this->userId,
                'export_request_id' => $this->exportRequestId,
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $this->updatePendingToFailed($user);
        }
    }

    private function updatePendingToCompleted(User $user, string $fileName, string $downloadUrl): void
    {
        $notification = $user->notifications()
            ->where('type', ExcelExportPending::class)
            ->where('data->export_request_id', $this->exportRequestId)
            ->first();

        if ($notification) {
            $notification->update([
                'type' => ExcelExportCompleted::class,
                'data' => [
                    'export_request_id' => $this->exportRequestId,
                    'icon' => 'fa-solid fa-file-pdf',
                    'title' => 'PDF de fichas listo',
                    'url' => $downloadUrl,
                    'file_name' => $fileName,
                    'export_status' => 'completed',
                ],
            ]);
        }
    }

    private function updatePendingToFailed(User $user): void
    {
        $notification = $user->notifications()
            ->where('type', ExcelExportPending::class)
            ->where('data->export_request_id', $this->exportRequestId)
            ->first();

        if ($notification) {
            $notification->update([
                'type' => ExcelExportCompleted::class,
                'data' => [
                    'export_request_id' => $this->exportRequestId,
                    'icon' => 'fa-solid fa-circle-exclamation',
                    'title' => 'Error al generar el PDF de fichas',
                    'url' => null,
                    'export_status' => 'failed',
                ],
            ]);
        }
    }
}
