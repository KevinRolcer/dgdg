<?php

return [
    'menu' => [
        [
            'icon' => 'fa-solid fa-house',
            'title' => 'Inicio',
            'route' => 'home',
        ],
        [
            'icon' => 'fa-solid fa-people-group',
            'title' => 'Mesas de Paz y Seguridad',
            'route' => 'mesas-paz',
            'permission_any' => ['Mesas-Paz', 'Mesas-Paz-consulta'],
            'hidden_for_emails' => ['dgdg.admon@gmail.com'],
        ],
        [
            'icon' => 'fa-solid fa-table-columns',
            'title' => 'Evidencias Mesas de Paz',
            'route' => 'mesas-paz.evidencias',
            'permission' => 'Tableros-incidencias',
        ],
        [
            'icon' => 'fa-solid fa-calendar-days',
            'title' => 'Agenda Directiva',
            'route' => 'agenda.index',
            'active_routes' => [
                'agenda.index',
                'agenda.calendar',
                'agenda.show',
                'agenda.create',
                'agenda.edit',
            ],
            'permission_any' => ['Modulos-Temporales-Admin', 'Agenda-Directiva', 'Agenda-Seguimiento', 'Agenda-consulta'],
        ],
        [
            'icon' => 'fa-solid fa-note-sticky',
            'title' => 'Agenda Personal',
            'route' => 'personal-agenda.index',
        ],
        [
            'icon' => 'fa-solid fa-clipboard-check',
            'title' => 'Seguimiento de Agenda',
            'route' => 'agenda.seguimiento.index',
            'permission_any' => ['Modulos-Temporales-Admin', 'Agenda-Directiva', 'Agenda-Seguimiento', 'Agenda-consulta'],
            'hidden_if_can' => 'Modulos-Temporales-Admin',
        ],
        /*
         * Eventos temporales: enlaces (Modulos-Temporales) capturan y ven registros;
         * admin (Modulos-Temporales-Admin) gestiona y exporta.
         */
        [
            'icon' => 'fa-solid fa-layer-group',
            'title' => 'Eventos temporales',
            'permission_any' => [
                'Modulos-Temporales-Admin',
                'Modulos-Temporales',
                'Modulos-Temporales-Admin-consulta',
                'Modulos-Temporales-consulta',
            ],
            'children' => [
                [
                    'title' => 'Subir información',
                    'route' => 'temporary-modules.upload',
                    'permission' => 'Modulos-Temporales',
                    'hidden_if_can' => 'Modulos-Temporales-Admin',
                ],
                [
                    'title' => 'Mis registros',
                    'route' => 'temporary-modules.records',
                    'permission' => 'Modulos-Temporales',
                    'hidden_if_can' => 'Modulos-Temporales-Admin',
                ],
                [
                    'title' => 'Administración',
                    'route' => 'temporary-modules.admin.index',
                    'permission_any' => ['Modulos-Temporales-Admin', 'Modulos-Temporales-Admin-consulta'],
                ],
                [
                    'title' => 'Registros y exportación',
                    'route' => 'temporary-modules.admin.records',
                    'permission_any' => ['Modulos-Temporales-Admin', 'Modulos-Temporales-Admin-consulta'],
                ],
            ],
        ],
        [
            'icon' => 'fa-brands fa-whatsapp',
            'title' => 'Chats WhatsApp',
            'route' => 'whatsapp-chats.admin.index',
            'permission' => 'Chats-WhatsApp-Sensible',
        ],
    ],
];
