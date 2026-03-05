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
            'permission' => 'Mesas-Paz',
            'hidden_if_can' => 'Tableros-incidencias',
        ],
        [
            'icon' => 'fa-solid fa-table-columns',
            'title' => 'Evidencias Mesas de Paz',
            'route' => 'mesas-paz.evidencias',
            'permission' => 'Tableros-incidencias',
        ],
        [
            'icon' => 'fa-solid fa-layer-group',
            'title' => 'Módulos temporales',
            'children' => [
                [
                    'title' => 'Subir información',
                    'route' => 'temporary-modules.upload',
                    'permission' => 'Modulos-Temporales',
                    'hidden_if_can' => 'Modulos-Temporales-Admin',
                ],
                [
                    'title' => 'Ver mis registros',
                    'route' => 'temporary-modules.records',
                    'permission' => 'Modulos-Temporales',
                    'hidden_if_can' => 'Modulos-Temporales-Admin',
                ],
                [
                    'title' => 'Administrar módulos',
                    'route' => 'temporary-modules.admin.index',
                    'permission' => 'Modulos-Temporales-Admin',
                ],
                [
                    'title' => 'Registros y exportación',
                    'route' => 'temporary-modules.admin.records',
                    'permission' => 'Modulos-Temporales-Admin',
                ],
                [
                    'title' => 'Configuración',
                    'route' => 'admin.settings.index',
                    'permission' => 'Modulos-Temporales-Admin',
                ],
            ],
        ],
    ],
];
