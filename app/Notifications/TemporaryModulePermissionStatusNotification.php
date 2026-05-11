<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TemporaryModulePermissionStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $status,
        private readonly string $moduleName,
        private readonly string $permissionLabel,
        private readonly ?string $expiresAt = null,
        private readonly ?string $url = null
    ) {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $approved = $this->status === 'approved';
        $title = $approved
            ? 'Permiso autorizado: '.$this->permissionLabel
            : 'Permiso denegado: '.$this->permissionLabel;

        if ($approved && $this->expiresAt) {
            $title .= ' hasta '.$this->expiresAt;
        }

        return [
            'icon' => $approved ? 'fa-solid fa-key' : 'fa-solid fa-ban',
            'title' => $title.' en '.$this->moduleName,
            'url' => $this->url,
            'temporary_module_permission_status' => $this->status,
        ];
    }
}
