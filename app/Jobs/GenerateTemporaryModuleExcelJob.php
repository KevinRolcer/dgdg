<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

use App\Models\User;
use App\Services\TemporaryModules\TemporaryModuleExportService;
use App\Notifications\ExcelExportCompleted;

class GenerateTemporaryModuleExcelJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function __construct(
        public readonly int $moduleId,
        public readonly string $mode,
        public readonly int $userId,
        public readonly bool $includeAnalysis = false,
        public readonly ?string $exportRequestId = null,
        public readonly ?array $exportConfig = null,
    ) {
    }

    public function handle(TemporaryModuleExportService $exportService): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            return;
        }

        try {
            $result = $exportService->exportExcel($this->moduleId, $this->mode, $this->includeAnalysis, $this->exportConfig);

            if (is_array($result) && isset($result['name'], $result['url'])) {
                if ($this->exportRequestId !== null) {
                    $this->updatePendingNotificationToCompleted($user, $result['name'], $result['url']);
                } else {
                    $user->notify(new ExcelExportCompleted($result['name'], $result['url']));
                }
            }
        } catch (\Throwable $e) {
            Log::error('Fallo al generar excel en segundo plano: '.$e->getMessage(), [
                'module_id' => $this->moduleId,
                'mode' => $this->mode,
                'user_id' => $this->userId,
            ]);

            if ($this->exportRequestId !== null) {
                $this->updatePendingNotificationToFailed($user);
            }
        }
    }

    private function updatePendingNotificationToCompleted(User $user, string $fileName, string $downloadUrl): void
    {
        $notification = $user->notifications()
            ->where('type', \App\Notifications\ExcelExportPending::class)
            ->where('data->export_request_id', $this->exportRequestId)
            ->first();

        if ($notification) {
            $notification->update([
                'type' => ExcelExportCompleted::class,
                'data' => [
                    'export_request_id' => $this->exportRequestId,
                    'icon' => 'fa-solid fa-file-excel',
                    'title' => 'Excel listo: '.$fileName,
                    'url' => $downloadUrl,
                    'file_name' => $fileName,
                    'export_status' => 'completed',
                ],
            ]);
        }
    }

    private function updatePendingNotificationToFailed(User $user): void
    {
        $notification = $user->notifications()
            ->where('type', \App\Notifications\ExcelExportPending::class)
            ->where('data->export_request_id', $this->exportRequestId)
            ->first();

        if ($notification) {
            $notification->update([
                'type' => ExcelExportCompleted::class,
                'data' => [
                    'export_request_id' => $this->exportRequestId,
                    'icon' => 'fa-solid fa-circle-exclamation',
                    'title' => 'Error al generar el Excel',
                    'url' => null,
                    'export_status' => 'failed',
                ],
            ]);
        }
    }
}

