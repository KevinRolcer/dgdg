<?php

namespace App\Services\Home;

class HomeService
{
    public function indexData(): array
    {
        return [
            'pageTitle' => 'Inicio',
            'pageDescription' => 'Panel principal del sistema',
            'topbarNotifications' => [],
            'upcomingEvents' => [],
            'pastEvents' => [],
        ];
    }
}
