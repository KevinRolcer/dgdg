<?php

namespace App\Services\MesasPaz;

use App\Models\MesaPazAsistencia;
use App\Models\Microrregione;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MesasPazSupervisionService
{
    public function puedeSupervisarEvidencias($usuario): bool
    {
        if (!$usuario) {
            return false;
        }

        if ($usuario->hasRole('Superadmin') || $usuario->hasRole('superadmin')) {
            return true;
        }

        if ($usuario->hasRole('Enlace')) {
            return true;
        }

        return $usuario->can('Tableros')
            || $usuario->can('Tableros-incidencias')
            || $usuario->can('Tablero Incidencias');
    }

    public function construirVistaEvidencias(Request $request, $usuario = null): array
    {
        $fechaLista = trim((string) $request->query('fecha_lista', Carbon::today()->toDateString()));
        $fechaAnalisis = trim((string) $request->query('fecha_analisis', Carbon::today()->toDateString()));
        $analisisAsiste = trim((string) $request->query('analisis_asiste', 'Presidente'));
        $analisisMicrorregionId = trim((string) $request->query('analisis_microrregion_id', ''));
        $analisisMicrorregionIdInt = $analisisMicrorregionId !== '' ? (int) $analisisMicrorregionId : null;

        $opcionesAsiste = [
            'Presidente',
            'Director de seguridad',
            'Secretario/Regidor de gobernación',
            'Secretario de Ayuntamiento',
            'Ninguno',
        ];

        $validator = Validator::make([
            'fecha_lista' => $fechaLista,
            'fecha_analisis' => $fechaAnalisis,
            'analisis_asiste' => $analisisAsiste,
            'analisis_microrregion_id' => $analisisMicrorregionId,
        ], [
            'fecha_lista' => ['required', 'date'],
            'fecha_analisis' => ['required', 'date'],
            'analisis_asiste' => ['nullable', Rule::in($opcionesAsiste)],
            'analisis_microrregion_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors(),
            ];
        }

        $query = MesaPazAsistencia::query()
            ->select(['asist_id', 'municipio_id', 'delegado_id', 'user_id', 'microrregion_id', 'fecha_asist', 'created_at', 'presidente', 'asiste', 'delegado_asistio', 'evidencia', 'acuerdo_observacion', 'parte_observacion'])
            ->with([
                'municipio:id,municipio',
                'microrregion:id,microrregion,cabecera',
                'delegado:id,nombre,ap_paterno,ap_materno,microrregion_id',
                'delegado.microrregion:id,microrregion,cabecera',
                'user:id,name,email',
            ]);

        $fechaListaFiltro = Carbon::parse($fechaLista)->toDateString();
        $fechaAnalisisFiltro = Carbon::parse($fechaAnalisis)->toDateString();

        if ($fechaListaFiltro === $fechaAnalisisFiltro) {
            $registrosLista = (clone $query)
                ->whereDate('fecha_asist', $fechaListaFiltro)
                ->orderByDesc('created_at')
                ->paginate(25);

            $registrosAnalisisBase = (clone $query)
                ->whereDate('fecha_asist', $fechaAnalisisFiltro)
                ->get();
        } else {
            $registrosLista = (clone $query)
                ->whereDate('fecha_asist', $fechaListaFiltro)
                ->orderByDesc('created_at')
                ->paginate(25);

            $registrosAnalisisBase = (clone $query)
                ->whereDate('fecha_asist', $fechaAnalisisFiltro)
                ->get();
        }

        $representantesDisponibles = $registrosAnalisisBase
            ->filter(function ($registro) {
                return $registro->presidente === 'Representante';
            })
            ->pluck('asiste')
            ->map(function ($valor) {
                return trim((string) $valor);
            })
            ->filter(function ($valor) {
                $normalizado = mb_strtolower($valor);

                return $normalizado !== '' && !in_array($normalizado, [
                    'no',
                    'srd',
                    'sie',
                    'suspención',
                    'suspencion',
                ], true);
            })
            ->unique()
            ->sort()
            ->values();

        $microrregionesDisponibles = Microrregione::query()
            ->select(['id', 'cabecera', 'microrregion'])
            ->orderBy('id')
            ->get()
            ->map(function ($microrregion) {
                $nombre = $microrregion->cabecera ?: $microrregion->microrregion;
                return [
                    'id' => (int) $microrregion->id,
                    'label' => $microrregion->id.' - '.$nombre,
                ];
            })
            ->values();

        $allowedMicroregionIds = $this->allowedMicrorregionIdsForUser($usuario);
        if (!empty($allowedMicroregionIds)) {
            $registrosLista = $registrosLista
                ->filter(fn ($registro) => in_array((int) $registro->microrregion_id, $allowedMicroregionIds, true))
                ->values();

            $registrosAnalisisBase = $registrosAnalisisBase
                ->filter(fn ($registro) => in_array((int) $registro->microrregion_id, $allowedMicroregionIds, true))
                ->values();

            $microrregionesDisponibles = $microrregionesDisponibles
                ->filter(fn ($item) => in_array((int) ($item['id'] ?? 0), $allowedMicroregionIds, true))
                ->values();
        }

        $aplicaFiltroMicrorregionAnalisis = function ($registro) use ($analisisMicrorregionIdInt) {
            if (!empty($analisisMicrorregionIdInt) && (int) $registro->microrregion_id !== (int) $analisisMicrorregionIdInt) {
                return false;
            }

            return true;
        };

        $normalizarAsiste = function ($registro) {
            return mb_strtolower(trim((string) $registro->asiste));
        };

        $esNoPresente = function ($registro) use ($normalizarAsiste) {
            $asiste = $normalizarAsiste($registro);

            return in_array($asiste, [
                'no',
                'srd',
                'sie',
                'suspención',
                'suspencion',
            ], true);
        };

        $esPresente = function ($registro) use ($esNoPresente) {
            return !$esNoPresente($registro);
        };

        $coincideAsiste = function ($registro) use ($analisisAsiste, $esNoPresente) {
            $valorAsiste = mb_strtolower(trim((string) $registro->asiste));

            if ($analisisAsiste === 'Ninguno') {
                return $esNoPresente($registro);
            }

            if ($analisisAsiste === 'Presidente') {
                return in_array((string) $registro->presidente, ['Si', 'Ambos'], true)
                    || $valorAsiste === 'presidente'
                    || mb_strpos($valorAsiste, 'presidente y representante') !== false;
            }

            if ($analisisAsiste === 'Director de seguridad') {
                return in_array($valorAsiste, [
                    'director de seguridad',
                    'director de seguridad municipal',
                ], true) || mb_strpos($valorAsiste, 'director de seguridad') !== false;
            }

            if ($analisisAsiste === 'Secretario/Regidor de gobernación') {
                return $valorAsiste === mb_strtolower($analisisAsiste)
                    || mb_strpos($valorAsiste, mb_strtolower('Secretario/Regidor de gobernación')) !== false;
            }

            if ($analisisAsiste === 'Secretario de Ayuntamiento') {
                return $valorAsiste === mb_strtolower($analisisAsiste)
                    || mb_strpos($valorAsiste, mb_strtolower('Secretario de Ayuntamiento')) !== false;
            }

            return $valorAsiste === mb_strtolower($analisisAsiste);
        };

        $registrosAnalisisScope = $registrosAnalisisBase
            ->filter($aplicaFiltroMicrorregionAnalisis)
            ->values();

        $calcularAnalisis = function ($items) use ($esPresente, $esNoPresente) {
            $totalMunicipiosRegistrados = $items
                ->pluck('municipio_id')
                ->filter()
                ->unique()
                ->count();

            $municipiosPresentes = $items
                ->filter($esPresente)
                ->pluck('municipio_id')
                ->filter()
                ->unique()
                ->count();

            $presidentesPresentes = $items
                ->filter(function ($registro) {
                    return in_array((string) $registro->presidente, ['Si', 'Ambos'], true);
                })
                ->pluck('municipio_id')
                ->filter()
                ->unique()
                ->count();

            $representantesPresentes = $items
                ->filter(function ($registro) {
                    return in_array((string) $registro->presidente, ['Representante', 'Ambos'], true);
                })
                ->pluck('municipio_id')
                ->filter()
                ->unique()
                ->count();

            $municipiosNoPresentes = $items
                ->filter($esNoPresente)
                ->pluck('municipio_id')
                ->filter()
                ->unique()
                ->count();

            $totalAutoridadesPresentes = $presidentesPresentes + $representantesPresentes;
            $tasaPresenciaMunicipal = $totalMunicipiosRegistrados > 0
                ? round(($municipiosPresentes / $totalMunicipiosRegistrados) * 100, 1)
                : 0;
            $tasaAutoridadesSobrePresentes = $municipiosPresentes > 0
                ? round(($totalAutoridadesPresentes / $municipiosPresentes) * 100, 1)
                : 0;

            return [
                'total_municipios_registrados' => $totalMunicipiosRegistrados,
                'municipios_presentes' => $municipiosPresentes,
                'municipios_no_presentes' => $municipiosNoPresentes,
                'presidentes_presentes' => $presidentesPresentes,
                'representantes_presentes' => $representantesPresentes,
                'total_autoridades_presentes' => $totalAutoridadesPresentes,
                'tasa_presencia_municipal' => $tasaPresenciaMunicipal,
                'tasa_autoridades_sobre_presentes' => $tasaAutoridadesSobrePresentes,
            ];
        };

        $resolverTiposAsistente = function ($registro) use ($normalizarAsiste, $esNoPresente) {
            $asiste = $normalizarAsiste($registro);
            $presStored = (string) $registro->presidente;
            $tipos = [];

            if ($esNoPresente($registro)) {
                return ['Ninguno'];
            }

            // Presidente Municipal
            if (
                in_array($presStored, ['Si', 'Ambos'], true)
                || $asiste === 'presidente'
                || mb_strpos($asiste, 'presidente y representante') !== false
            ) {
                $tipos[] = 'Presidente';
            }

            // Director de Seguridad (o su Representante según la nueva regla)
            if (
                in_array($presStored, ['Representante', 'Ambos'], true)
                || in_array($asiste, ['director de seguridad', 'director de seguridad municipal', 'representante'], true)
                || mb_strpos($asiste, 'director de seguridad') !== false
            ) {
                $tipos[] = 'Director de seguridad';
            }

            // Soporte para registros antiguos o manuales de Secretarios
            if ($asiste === mb_strtolower('Secretario/Regidor de gobernación') || mb_strpos($asiste, mb_strtolower('Secretario/Regidor de gobernación')) !== false) {
                $tipos[] = 'Secretario/Regidor de gobernación';
            }

            if ($asiste === mb_strtolower('Secretario de Ayuntamiento') || mb_strpos($asiste, mb_strtolower('Secretario de Ayuntamiento')) !== false) {
                $tipos[] = 'Secretario de Ayuntamiento';
            }

            if (empty($tipos)) {
                return ['Otros'];
            }

            return array_values(array_unique($tipos));
        };

        $microrregionLabel = function ($registro) {
            $id = (int) ($registro->microrregion_id ?? 0);
            
            // Intentar obtener de la relación directa, sino de la del delegado (respaldo)
            $micro = $registro->microrregion ?: optional($registro->delegado)->microrregion;
            $nombre = optional($micro)->cabecera ?: optional($micro)->microrregion;

            if ($id > 0 && !empty($nombre)) {
                return $id.' - '.$nombre;
            }

            return $id > 0 ? (string) $id : 'Sin microrregión';
        };

        $agruparListadoPorMicrorregion = function ($items, $callbackSi, $callbackNo) use ($microrregionLabel) {
            return $items
                ->groupBy(function ($registro) use ($microrregionLabel) {
                    return $microrregionLabel($registro);
                })
                ->map(function ($grupo) use ($callbackSi, $callbackNo) {
                    $municipiosSi = $grupo
                        ->filter($callbackSi)
                        ->pluck('municipio.municipio')
                        ->filter()
                        ->unique()
                        ->values();

                    $municipiosNo = $grupo
                        ->filter($callbackNo)
                        ->pluck('municipio.municipio')
                        ->filter()
                        ->unique()
                        ->values();

                    return [
                        'municipios_si' => $municipiosSi,
                        'municipios_no' => $municipiosNo,
                    ];
                })
                ->sortKeys()
                ->toArray();
        };

        $asistentesPorMicrorregion = $registrosAnalisisScope
            ->groupBy(function ($registro) use ($microrregionLabel) {
                return $microrregionLabel($registro);
            })
            ->map(function ($grupo) use ($resolverTiposAsistente, $esPresente) {
                $grupo = collect($grupo);

                $totalRegistrados = $grupo
                    ->pluck('municipio_id')
                    ->filter()
                    ->unique()
                    ->count();

                $presentes = $grupo
                    ->filter($esPresente)
                    ->pluck('municipio_id')
                    ->filter()
                    ->unique()
                    ->count();

                $delegadoAsiste = $grupo
                    ->filter(function ($registro) {
                        return mb_strtolower(trim((string) $registro->delegado_asistio)) === 'si';
                    })
                    ->pluck('municipio_id')
                    ->filter()
                    ->unique()
                    ->count();

                $conteoPorTipo = [
                    'Presidente' => 0,
                    'Director de seguridad' => 0,
                    'Secretario/Regidor de gobernación' => 0,
                    'Secretario de Ayuntamiento' => 0,
                    'Ninguno' => 0,
                    'Otros' => 0,
                ];

                foreach ($grupo as $registro) {
                    $tiposRegistro = $resolverTiposAsistente($registro);
                    foreach ($tiposRegistro as $tipo) {
                        if (!array_key_exists($tipo, $conteoPorTipo)) {
                            $tipo = 'Otros';
                        }

                        $conteoPorTipo[$tipo]++;
                    }
                }

                $promedioPorTipo = collect($conteoPorTipo)
                    ->map(function ($conteo) use ($totalRegistrados) {
                        if ($totalRegistrados <= 0) {
                            return 0;
                        }

                        return round(($conteo / $totalRegistrados) * 100, 1);
                    })
                    ->toArray();

                return [
                    'total_registrados' => $totalRegistrados,
                    'presentes' => $presentes,
                    'delegado_asiste' => $delegadoAsiste,
                    'delegado_asiste_porcentaje' => $totalRegistrados > 0 ? round(($delegadoAsiste / $totalRegistrados) * 100, 1) : 0,
                    'conteo_por_tipo' => $conteoPorTipo,
                    'promedio_por_tipo' => $promedioPorTipo,
                ];
            })
            ->sortKeys()
            ->toArray();

        $asistentesPorMicrorregion = collect($microrregionesDisponibles)
            ->mapWithKeys(function ($microrregion) use ($asistentesPorMicrorregion) {
                $label = (string) ($microrregion['label'] ?? 'Sin microrregión');

                return [
                    $label => $asistentesPorMicrorregion[$label] ?? [
                        'total_registrados' => 0,
                        'presentes' => 0,
                        'delegado_asiste' => 0,
                        'delegado_asiste_porcentaje' => 0,
                        'conteo_por_tipo' => [
                            'Presidente' => 0,
                            'Director de seguridad' => 0,
                            'Secretario/Regidor de gobernación' => 0,
                            'Secretario de Ayuntamiento' => 0,
                            'Ninguno' => 0,
                            'Otros' => 0,
                        ],
                        'promedio_por_tipo' => [
                            'Presidente' => 0,
                            'Director de seguridad' => 0,
                            'Secretario/Regidor de gobernación' => 0,
                            'Secretario de Ayuntamiento' => 0,
                            'Ninguno' => 0,
                            'Otros' => 0,
                        ],
                    ],
                ];
            })
            ->toArray();

        $listadoPresidente = $agruparListadoPorMicrorregion(
            $registrosAnalisisScope,
            function ($registro) use ($coincideAsiste) {
                return $coincideAsiste($registro);
            },
            function ($registro) use ($analisisAsiste, $esNoPresente) {
                if ($analisisAsiste === 'Ninguno') {
                    return false;
                }

                return $esNoPresente($registro);
            }
        );

        $asisteSeleccionado = mb_strtolower($analisisAsiste);
        $listadoRepresentante = $agruparListadoPorMicrorregion(
            $registrosAnalisisScope,
            function ($registro) use ($asisteSeleccionado, $esNoPresente) {
                $asisteRegistro = mb_strtolower(trim((string) $registro->asiste));

                if ($asisteSeleccionado === 'ninguno') {
                    return $esNoPresente($registro);
                }

                if ($asisteSeleccionado === '' || $asisteSeleccionado === 'presidente') {
                    return in_array((string) $registro->presidente, ['Si', 'Ambos'], true)
                        || $asisteRegistro === 'presidente'
                        || mb_strpos($asisteRegistro, 'presidente y representante') !== false;
                }

                if ($asisteSeleccionado === mb_strtolower('Director de seguridad')) {
                    return in_array($asisteRegistro, ['director de seguridad', 'director de seguridad municipal'], true)
                        || mb_strpos($asisteRegistro, 'director de seguridad') !== false;
                }

                if ($asisteSeleccionado === mb_strtolower('Secretario/Regidor de gobernación')) {
                    return $asisteRegistro === $asisteSeleccionado
                        || mb_strpos($asisteRegistro, $asisteSeleccionado) !== false;
                }

                if ($asisteSeleccionado === mb_strtolower('Secretario de Ayuntamiento')) {
                    return $asisteRegistro === $asisteSeleccionado
                        || mb_strpos($asisteRegistro, $asisteSeleccionado) !== false;
                }

                return $asisteRegistro === $asisteSeleccionado;
            },
            function ($registro) use ($esNoPresente) {
                return $esNoPresente($registro);
            }
        );

        // Agrupar y mapear evidencias paginadas
        $evidencias = collect($registrosLista->items())
            ->groupBy(function ($registro) {
                return Carbon::parse($registro->fecha_asist)->toDateString().'|'.$registro->user_id.'|'.$registro->microrregion_id;
            })
            ->map(function ($items, $grupo) use ($esPresente, $esNoPresente) {
                $items = collect($items);
                $primero = $items->first();
                $evidenciaPaths = $items
                    ->flatMap(function ($item) {
                        return $this->decodeEvidencePaths($item->evidencia);
                    })
                    ->filter(function ($path) {
                        return !empty($path);
                    })
                    ->unique()
                    ->values();
                $evidenciaUrls = $evidenciaPaths->map(function ($path) {
                    return $this->toLocalPublicUrl((string) $path);
                })->values();
                [$fecha] = explode('|', (string) $grupo);
                $municipiosConAsistencia = $items
                    ->filter($esPresente)
                    ->pluck('municipio.municipio')
                    ->filter(function ($nombre) {
                        return !empty($nombre);
                    })
                    ->unique()
                    ->values();

                $municipiosNoPresentes = $items
                    ->filter($esNoPresente)
                    ->pluck('municipio.municipio')
                    ->filter(function ($nombre) {
                        return !empty($nombre);
                    })
                    ->unique()
                    ->values();

                $acuerdosObservaciones = $items
                    ->flatMap(function ($registro) {
                        return collect($registro->acuerdo_items ?? []);
                    })
                    ->map(function ($item) {
                        return trim((string) $item);
                    })
                    ->filter(function ($item) {
                        return $item !== '';
                    })
                    ->unique()
                    ->values();

                $partesObservaciones = $items
                    ->flatMap(function ($registro) {
                        return collect($registro->parte_items ?? []);
                    })
                    ->map(function ($item) {
                        return trim((string) $item);
                    })
                    ->filter(function ($item) {
                        return $item !== '';
                    })
                    ->unique()
                    ->values();

                $delegadoNombre = trim((string) (
                    optional($primero->delegado)->nombre.' '.optional($primero->delegado)->ap_paterno.' '.optional($primero->delegado)->ap_materno
                ));
                if ($delegadoNombre === '') {
                    $delegadoNombre = optional($primero->user)->name ?: 'Sin delegado asignado';
                }

                $microrregionId = $primero->microrregion_id;
                $microrregionNombre = optional($primero->microrregion)->cabecera
                    ?: optional($primero->microrregion)->microrregion;
                $microrregionLabel = null;
                if (!empty($microrregionId) && !empty($microrregionNombre)) {
                    $microrregionLabel = $microrregionId.' - '.$microrregionNombre;
                } elseif (!empty($microrregionId)) {
                    $microrregionLabel = (string) $microrregionId;
                }

                return [
                    'fecha_asist' => $fecha,
                    'tiene_evidencia' => $evidenciaUrls->isNotEmpty(),
                    'evidencia_url' => $evidenciaUrls->first(),
                    'evidencia_urls' => $evidenciaUrls,
                    'total_registros' => $items->count(),
                    'municipios' => $municipiosConAsistencia,
                    'municipios_con_asistencia' => $municipiosConAsistencia,
                    'municipios_no_presentes' => $municipiosNoPresentes,
                    'partes_observaciones' => $partesObservaciones,
                    'acuerdos_observaciones' => $acuerdosObservaciones,
                    'delegado' => $delegadoNombre,
                    'usuario' => optional($primero->user)->email,
                    'microrregion_id' => $microrregionId,
                    'microrregion_label' => $microrregionLabel,
                    'ultima_captura' => optional($items->max('created_at')),
                ];
            })
            ->sortByDesc('fecha_asist')
            ->values();

        // Obtener fechas que tienen registros para el calendario (filtrado por permisos)
        $fechasConDatosQuery = MesaPazAsistencia::query();
        if (!empty($allowedMicroregionIds)) {
            $fechasConDatosQuery->whereIn('microrregion_id', $allowedMicroregionIds);
        }
        $fechasConDatos = $fechasConDatosQuery
            ->select('fecha_asist')
            ->distinct()
            ->orderBy('fecha_asist', 'desc')
            ->get()
            ->pluck('fecha_asist')
            ->map(function ($f) {
                if (is_string($f)) return $f;
                return Carbon::parse($f)->toDateString();
            })
            ->unique()
            ->values()
            ->all();

        return [
            'valid' => true,
            'errors' => new MessageBag(),
            'data' => [
                'evidencias' => $evidencias,
                'fechaLista' => $fechaListaFiltro,
                'fechaAnalisis' => $fechaAnalisisFiltro,
                'analisisAsiste' => $analisisAsiste,
                'analisisMicrorregionId' => $analisisMicrorregionIdInt,
                'representantesDisponibles' => $representantesDisponibles,
                'microrregionesDisponibles' => $microrregionesDisponibles,
                'analisisHoy' => $calcularAnalisis($registrosAnalisisScope),
                'analisisFecha' => $calcularAnalisis($registrosAnalisisScope),
                'asistentesPorMicrorregion' => $asistentesPorMicrorregion,
                'listadoPresidente' => $listadoPresidente,
                'listadoRepresentante' => $listadoRepresentante,
                'opcionesAsiste' => $opcionesAsiste,
                'fechasConDatos' => $fechasConDatos,
            ],
        ];
    }

    private function allowedMicrorregionIdsForUser($usuario): array
    {
        if (!$usuario || !$usuario->hasRole('Enlace')) {
            return [];
        }

        return $usuario->microrregionesAsignadas()
            ->pluck('microrregiones.id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function toLocalPublicUrl(string $pathOrUrl): string
    {
        if (filter_var($pathOrUrl, FILTER_VALIDATE_URL)) {
            return $pathOrUrl;
        }

        $normalized = $this->normalizeEvidencePath($pathOrUrl);
        if ($normalized === null) {
            return '';
        }

        return route('mesas-paz.evidencia.preview', ['path' => $this->encodePathForPreview($normalized)]);
    }

    private function decodeEvidencePaths($value): array
    {
        if ($value === null) {
            return [];
        }

        $texto = trim((string) $value);
        if ($texto === '') {
            return [];
        }

        $decoded = json_decode($texto, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $items = $decoded;
        } else {
            $items = [$texto];
        }

        return collect($items)
            ->map(function ($item) {
                $pathOrUrl = (string) $item;
                if (filter_var($pathOrUrl, FILTER_VALIDATE_URL)) {
                    $queryPath = (string) parse_url($pathOrUrl, PHP_URL_QUERY);
                    parse_str($queryPath, $query);
                    $encoded = isset($query['path']) ? (string) $query['path'] : '';
                    if ($encoded !== '') {
                        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
                        if (is_string($decoded) && $decoded !== '') {
                            return $this->normalizeEvidencePath($decoded);
                        }
                    }

                    $parsedPath = ltrim((string) parse_url($pathOrUrl, PHP_URL_PATH), '/');
                    return $this->normalizeEvidencePath($parsedPath);
                }

                return $this->normalizeEvidencePath($pathOrUrl);
            })
            ->filter(function ($path) {
                return !empty($path);
            })
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeEvidencePath(string $rawPath): ?string
    {
        $path = ltrim(str_replace('\\', '/', trim($rawPath)), '/');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        if (str_starts_with($path, 'localstorage/segob/mesas_paz/evidencias/')) {
            return $path;
        }

        if (str_starts_with($path, 'mesas_paz/evidencias/')) {
            return $path;
        }

        return null;
    }

    private function encodePathForPreview(string $path): string
    {
        return rtrim(strtr(base64_encode($path), '+/', '-_'), '=');
    }
}
