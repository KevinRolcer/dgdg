<?php

namespace App\Jobs;

use App\Models\Agenda;
use App\Models\User;
use App\Notifications\ExcelExportCompleted;
use App\Notifications\ExcelExportPending;
use App\Services\Agenda\AgendaFichasPdfBuilderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class GenerateSingleAgendaFichaPdfJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 600;

    public function __construct(
        public readonly int $userId,
        public readonly string $exportRequestId,
        public readonly int $agendaId,
        public readonly string $storageBaseName,
        public readonly string $downloadFileName,
    ) {}

    public function handle(AgendaFichasPdfBuilderService $builder): void
    {
        @ini_set('memory_limit', '512M');

        $user = User::find($this->userId, ['*']);
        $agenda = Agenda::find($this->agendaId, ['*']);
        
        if (!$user || !$agenda) {
            return;
        }

        $dir = storage_path('app/agenda-ficha-single-cache');
        File::ensureDirectoryExists($dir);
        $fullPath = $dir.DIRECTORY_SEPARATOR.$this->storageBaseName;

        try {
            $builder->renderSingleFichaToFile($user, $agenda, $fullPath);
            File::put($fullPath.'.dlname', $this->downloadFileName);

            $url = route('agenda.ficha.download', ['agenda' => $agenda->id, 'direct' => '1'], true);
            $this->updatePendingToCompleted($user, $this->downloadFileName, $url);
        } catch (\Throwable $e) {
            Log::error('Fallo al generar PDF de ficha individual agenda: '.$e->getMessage(), [
                'user_id' => $this->userId,
                'agenda_id' => $this->agendaId,
                'export_request_id' => $this->exportRequestId,
                'exception' => $e::class,
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
                    'title' => 'Ficha individual lista: '.$fileName,
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
                    'title' => 'Error al generar la ficha individual',
                    'url' => null,
                    'export_status' => 'failed',
                ],
            ]);
        }
    }
}
