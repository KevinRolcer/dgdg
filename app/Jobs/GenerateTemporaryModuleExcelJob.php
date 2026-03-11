<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

use App\Models\User;
use App\Models\TemporaryModule;
use App\Services\TemporaryModules\TemporaryModuleExportService;
use App\Notifications\ExcelExportCompleted;
use Illuminate\Support\Facades\Log;

class GenerateTemporaryModuleExcelJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $moduleId,
        public readonly string $mode,
        public readonly int $userId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TemporaryModuleExportService $exportService): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            return;
        }

        try {
            $result = $exportService->exportExcel($this->moduleId, $this->mode);
            // $result should return the file metadata (name and url)
            
            if (is_array($result) && isset($result['url'])) {
                $user->notify(new ExcelExportCompleted($result['name'], $result['url']));
            }
        } catch (\Throwable $e) {
            Log::error('Fallo al generar excel en segundo plano: ' . $e->getMessage());
        }
    }
}
