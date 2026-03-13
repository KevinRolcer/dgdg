<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\ExcelExportCompleted;
use App\Services\TemporaryModules\TemporaryModuleAnalysisWordService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateTemporaryModuleAnalysisWordJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function __construct(
        public readonly int $moduleId,
        public readonly int $userId,
        public readonly string $exportRequestId,
        /** @var array<string,mixed> */
        public readonly array $analysisConfig,
    ) {}

    public function handle(TemporaryModuleAnalysisWordService $service): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        try {
            $result = $service->exportWord($this->moduleId, $this->analysisConfig);
            if (isset($result['name'], $result['url'])) {
                $notification = $user->notifications()
                    ->where('type', \App\Notifications\ExcelExportPending::class)
                    ->where('data->export_request_id', $this->exportRequestId)
                    ->first();
                if ($notification) {
                    $notification->update([
                        'type' => ExcelExportCompleted::class,
                        'data' => [
                            'export_request_id' => $this->exportRequestId,
                            'icon' => 'fa-solid fa-file-word',
                            'title' => 'Word listo — descargar: '.$result['name'],
                            'url' => $result['url'],
                            'file_name' => $result['name'],
                            'export_status' => 'completed',
                        ],
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Fallo Word análisis módulo temporal: '.$e->getMessage(), [
                'module_id' => $this->moduleId,
                'user_id' => $this->userId,
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
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
                        'title' => 'Error al generar el Word de análisis',
                        'url' => null,
                        'export_status' => 'failed',
                    ],
                ]);
            }
        }
    }
}
