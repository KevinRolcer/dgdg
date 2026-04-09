<?php

namespace App\Jobs;

use App\Models\TemporaryModule;
use App\Models\User;
use App\Notifications\ExcelExportCompleted;
use App\Services\TemporaryModules\TemporaryModuleWordPdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateTemporaryModuleWordPdfJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function __construct(
        public readonly int $moduleId,
        public readonly string $format,
        public readonly int $userId,
        public readonly ?string $exportRequestId = null,
        public readonly ?array $exportConfig = null,
    ) {
    }

    public function handle(TemporaryModuleWordPdfService $exportService): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            return;
        }

        try {
            $result = $exportService->export($this->moduleId, $this->format, $this->exportConfig);

            if (is_array($result) && isset($result['name'], $result['url'])) {
                TemporaryModule::query()->whereKey($this->moduleId)->update(['exported_at' => now()]);

                if ($this->exportRequestId !== null) {
                    $this->updatePendingNotificationToCompleted($user, $result['name'], $result['url']);
                } else {
                    $user->notify(new ExcelExportCompleted($result['name'], $result['url']));
                }
            }
        } catch (\Throwable $e) {
            Log::error('Fallo al generar ' . $this->format . ' en segundo plano: '.$e->getMessage(), [
                'module_id' => $this->moduleId,
                'format' => $this->format,
                'user_id' => $this->userId,
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            if ($this->exportRequestId !== null) {
                $this->updatePendingNotificationToFailed($user, $e->getMessage());
            } else {
                $user->notify(new ExcelExportCompleted('Error al generar ' . strtoupper($this->format), null));
            }
        }
    }

    private function updatePendingNotificationToCompleted(User $user, string $fileName, string $downloadUrl): void
    {
        $notification = $this->findPendingNotification($user);

        if ($notification) {
            $icon = $this->format === 'word' ? 'fa-solid fa-file-word' : 'fa-solid fa-file-pdf';
            $docType = $this->format === 'word' ? 'Word' : 'PDF';
            $notification->update([
                'type' => ExcelExportCompleted::class,
                'data' => [
                    'export_request_id' => $this->exportRequestId,
                    'icon' => $icon,
                    'title' => $docType . ' listo: '.$fileName,
                    'url' => $downloadUrl,
                    'file_name' => $fileName,
                    'export_status' => 'completed',
                ],
            ]);
        }
    }

    private function updatePendingNotificationToFailed(User $user, string $error): void
    {
        $notification = $this->findPendingNotification($user);

        if ($notification) {
            $docType = strtoupper($this->format);
            $notification->update([
                'type' => ExcelExportCompleted::class,
                'data' => [
                    'export_request_id' => $this->exportRequestId,
                    'icon' => 'fa-solid fa-circle-exclamation',
                    'title' => 'Error al generar el ' . $docType,
                    'url' => null,
                    'export_status' => 'failed',
                ],
            ]);
        }
    }

    private function findPendingNotification(User $user): ?\Illuminate\Notifications\DatabaseNotification
    {
        $notification = $user->notifications()
            ->where('type', \App\Notifications\ExcelExportPending::class)
            ->where('data->export_request_id', $this->exportRequestId)
            ->first();

        if (! $notification) {
            $notification = $user->notifications()
                ->where('type', \App\Notifications\ExcelExportPending::class)
                ->orderByDesc('created_at')
                ->limit(400)
                ->get()
                ->first(function ($n) {
                    $d = $n->data;

                    return is_array($d)
                        && (string) ($d['export_request_id'] ?? '') === (string) $this->exportRequestId;
                });
        }

        return $notification;
    }
}
