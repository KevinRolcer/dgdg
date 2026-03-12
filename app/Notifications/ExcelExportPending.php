<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class ExcelExportPending extends Notification
{
    public function __construct(
        public string $exportRequestId,
        public string $fileName = 'archivo'
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $name = $this->fileName !== '' ? $this->fileName : 'archivo';

        return [
            'type' => 'excel_export_pending',
            'export_request_id' => $this->exportRequestId,
            'icon' => 'fa-solid fa-spinner fa-spin',
            'title' => 'Generando "'.$name.'"....xlsx',
            'url' => null,
        ];
    }
}
