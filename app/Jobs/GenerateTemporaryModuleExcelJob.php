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
    ) {
    }

    public function handle(TemporaryModuleExportService $exportService): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            return;
        }

        try {
            $result = $exportService->exportExcel($this->moduleId, $this->mode, $this->includeAnalysis);

            if (is_array($result) && isset($result['name'], $result['url'])) {
                $user->notify(new ExcelExportCompleted($result['name'], $result['url']));
            }
        } catch (\Throwable $e) {
            Log::error('Fallo al generar excel en segundo plano: '.$e->getMessage(), [
                'module_id' => $this->moduleId,
                'mode' => $this->mode,
                'user_id' => $this->userId,
            ]);
        }
    }
}

