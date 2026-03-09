<?php

namespace App\Services\MesasPaz;

use App\Models\Delegado;
use App\Models\MesaPazAsistencia;
use App\Models\Municipio;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MesasPazService
{
    private const LEGACY_PUBLIC_DIR = 'localstorage/segob/mesas_paz/evidencias';
    private const SHARED_STORAGE_DIR = 'mesas_paz/evidencias';
    private const SHARED_DISK = 'secure_shared';
    private const MAX_EVIDENCIAS = 3;
    private ?bool $parteColumnAvailable = null;

    public function indexData(int $userId): array
    {
        $delegado = $this->delegadoPorUsuario($userId);
        $selectedMicrorregionId = request()->filled('microrregion_id')
            ? (int) request()->input('microrregion_id')
            : null;

        $microrregionIds = $this->microrregionesPermitidasPorUsuario($userId, $delegado);
        $microrregionesAsignadas = collect();
        if (!empty($microrregionIds)) {
            $microrregionesAsignadas = DB::table('microrregiones')
                ->whereIn('id', $microrregionIds)
                ->select(['id', 'microrregion', 'cabecera'])
                ->orderByRaw('CAST(microrregion AS UNSIGNED)')
                ->get();

            // Keep only microrregiones that actually exist to avoid null access downstream.
            $microrregionIds = $microrregionesAsignadas
                ->pluck('id')
                ->map(function ($id) {
                    return (int) $id;
                })
                ->values()
                ->all();
        }

        if (count($microrregionIds) > 1) {
            if ($selectedMicrorregionId === null || !in_array($selectedMicrorregionId, $microrregionIds, true)) {
                $selectedMicrorregionId = (int) ($microrregionesAsignadas->first()->id ?? 0);
            }
        } elseif (count($microrregionIds) === 1) {
            $selectedMicrorregionId = (int) $microrregionIds[0];
        }

        $microrregionIdsFiltradas = $microrregionIds;
        if ($selectedMicrorregionId !== null && in_array($selectedMicrorregionId, $microrregionIds, true)) {
            $microrregionIdsFiltradas = [$selectedMicrorregionId];
        }

        $municipios = collect();
        if (!empty($microrregionIdsFiltradas)) {
            $municipios = DB::table('municipios')
                ->select(['id', 'municipio', 'cve_inegi', 'region', 'dl', 'padron', 'microrregion_id'])
                ->whereIn('microrregion_id', $microrregionIdsFiltradas)
                ->orderBy('municipio')
                ->limit(500)
                ->get();
        }

        $microrregionNumero = null;
        $microrregionNombre = 'Sin microrregión asignada';
        if (count($microrregionIdsFiltradas) === 1) {
            $micro = DB::table('microrregiones')->where('id', $microrregionIdsFiltradas[0])->first();
            if ($micro) {
                $microrregionNumero = (string) ($micro->microrregion ?? '');
                $microrregionNombre = $micro->cabecera
                    ?? $micro->microrregion
                    ?? 'Sin microrregión asignada';
            }
        } elseif (count($microrregionIds) > 1) {
            $microrregionNombre = 'Múltiples microrregiones asignadas ('.count($microrregionIds).')';
        }

        $esAnalistaEnlace = count($microrregionIds) > 1;
        $fechaReq = request()->input('fecha');
        $fechaCarbon = Carbon::today();

        if ($esAnalistaEnlace && $fechaReq && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaReq)) {
            try {
                $parsedDate = Carbon::createFromFormat('Y-m-d', $fechaReq)->startOfDay();
                if ($parsedDate->lte(Carbon::today()) && !$parsedDate->isWeekend()) {
                    $fechaCarbon = $parsedDate;
                }
            } catch (\Exception $e) {
                // Ignore invalid date
            }
        }

        $fechaHoyIso = $fechaCarbon->toDateString();
        $fechaHoy = $fechaCarbon->copy()->locale('es');
        $historialFecha = $fechaHoyIso;

        $registrosHoyQuery = MesaPazAsistencia::with('municipio:id,municipio')
            ->where('user_id', $userId)
            ->whereDate('fecha_asist', $fechaHoyIso)
            ->orderBy('municipio_id');

        if (!empty($microrregionIdsFiltradas)) {
            $registrosHoyQuery->whereIn('microrregion_id', $microrregionIdsFiltradas);
        }

        $registrosHoy = $registrosHoyQuery->get();

        $registrosHoyByMunicipio = $registrosHoy->keyBy('municipio_id');
        $modalidadActual = optional($registrosHoy->first())->modalidad;
        $delegadoAsistioActual = optional($registrosHoy->first())->delegado_asistio;
        $parteItemsActual = [];
        if ($this->tieneColumnaParte()) {
            $parteItemsActual = optional($registrosHoy->first(function ($registro) {
                if (empty($registro->parte_items)) return false;
                if (count($registro->parte_items) === 1) {
                    $val = mb_strtolower(trim($registro->parte_items[0]), 'UTF-8');
                    if (in_array($val, ['s/r', 'no se ha anotado nada', 'sin parte'], true)) {
                        return false;
                    }
                }
                return true;
            }))->parte_items ?? [];

            if (empty($parteItemsActual)) {
                $parteItemsActual = optional($registrosHoy->first(function ($registro) {
                    return !empty($registro->parte_items);
                }))->parte_items ?? [];
            }
        }

        $acuerdoItemsActual = optional($registrosHoy->first(function ($registro) {
            if (empty($registro->acuerdo_items)) return false;
            if (count($registro->acuerdo_items) === 1) {
                $val = mb_strtolower(trim($registro->acuerdo_items[0]), 'UTF-8');
                if (in_array($val, ['no se ha anotado nada', 'sin acuerdos', 'motivo no registrado'], true)) {
                    return false;
                }
            }
            return true;
        }))->acuerdo_items ?? [];

        if (empty($acuerdoItemsActual)) {
            // Check if there is a 'Sin Acuerdos' explicitly
            $acuerdoItemsActual = optional($registrosHoy->first(function ($registro) {
                if (!empty($registro->acuerdo_items) && count($registro->acuerdo_items) === 1) {
                    $val = mb_strtolower(trim($registro->acuerdo_items[0]), 'UTF-8');
                    if ($val === 'sin acuerdos') {
                        return true;
                    }
                }
                return false;
            }))->acuerdo_items ?? [];
            
            // Default fallback to whatever is available
            if (empty($acuerdoItemsActual)) {
                $acuerdoItemsActual = optional($registrosHoy->first(function ($registro) {
                    return !empty($registro->acuerdo_items);
                }))->acuerdo_items ?? [];
            }
        }

        $evidenciasActualesPaths = $this->resolverEvidenciasHoy($registrosHoy);
        $evidenciasActuales = $this->mapEvidenceItems($evidenciasActualesPaths);
        $evidenciaActualUrl = !empty($evidenciasActuales[0]['url'] ?? null)
            ? (string) $evidenciasActuales[0]['url']
            : null;

        $historialAgrupado = $registrosHoy->isNotEmpty()
            ? collect([
                (object) [
                    'fecha_asist' => $fechaHoyIso,
                    'total_registros' => $registrosHoy->count(),
                    'ultima_captura' => $registrosHoy->max('created_at'),
                ],
            ])
            : collect();

        return [
            'fechaActual' => 'Hoy, '.$fechaHoy->format('d').' de '.ucfirst($fechaHoy->translatedFormat('F')).' de '.$fechaHoy->format('Y'),
            'microrregionNumero' => $microrregionNumero,
            'microrregionNombre' => $microrregionNombre,
            'microrregionSeleccionadaId' => $selectedMicrorregionId,
            'microrregionesAsignadas' => $microrregionesAsignadas,
            'esAnalistaEnlace' => $esAnalistaEnlace,
            'municipios' => $municipios,
            'historialAgrupado' => $historialAgrupado,
            'historialFecha' => $historialFecha,
            'fechaHoyIso' => $fechaHoyIso,
            'registrosHoy' => $registrosHoy,
            'registrosHoyByMunicipio' => $registrosHoyByMunicipio,
            'modalidadActual' => $modalidadActual,
            'delegadoAsistioActual' => $delegadoAsistioActual,
            'parteItemsActual' => $parteItemsActual,
            'acuerdoItemsActual' => $acuerdoItemsActual,
            'evidenciaActualUrl' => $evidenciaActualUrl,
            'evidenciasActuales' => $evidenciasActuales,
            'maxEvidenciasHoy' => self::MAX_EVIDENCIAS,
            'canEditarEvidenciaHoy' => $registrosHoy->isNotEmpty(),
        ];
    }

    public function guardarEvidenciaHoy(int $userId, UploadedFile $archivo, ?int $selectedMicrorregionId = null, ?string $fecha = null): array
    {
        $fechaAsistencia = $this->resolverFechaAsistencia($userId, $fecha);
        $registrosHoyQuery = MesaPazAsistencia::where('user_id', $userId)
            ->whereDate('fecha_asist', $fechaAsistencia);

        $microrregionId = $this->resolveMicrorregionIdParaEvidencia($userId, $selectedMicrorregionId);
        if ($microrregionId !== null) {
            $registrosHoyQuery->where('microrregion_id', $microrregionId);
        }

        $registrosHoy = $registrosHoyQuery->get();

        if ($registrosHoy->isEmpty()) {
            throw new MesasPazServiceException('Primero registra al menos un municipio hoy para adjuntar evidencia.', 422);
        }

        if (!$archivo->isValid()) {
            throw new MesasPazServiceException('El archivo de evidencia no es válido o está corrupto.', 422);
        }

        $evidenciasActuales = $this->resolverEvidenciasHoy($registrosHoy);
        if (count($evidenciasActuales) >= self::MAX_EVIDENCIAS) {
            throw new MesasPazServiceException('Solo se permiten hasta ' . self::MAX_EVIDENCIAS . ' imágenes por usuario para la sesión de hoy.', 422);
        }

        $ext = strtolower((string) $archivo->getClientOriginalExtension());
        $nombreUnico = 'MP_EVID_' . $userId . '_' . str_replace('-', '', $fechaAsistencia) . '_' . time() . '_' . Str::random(6) . '.' . $ext;
        $rutaRelativa = self::SHARED_STORAGE_DIR . '/' . $nombreUnico;
        $evidenciasNuevas = $evidenciasActuales;
        $evidenciasNuevas[] = $rutaRelativa;
        $evidenciasNuevas = array_values(array_unique($evidenciasNuevas));

        DB::beginTransaction();
        try {
            Storage::disk(self::SHARED_DISK)->putFileAs(self::SHARED_STORAGE_DIR, $archivo, $nombreUnico);
            $rutaCompletaArchivo = Storage::disk(self::SHARED_DISK)->path($rutaRelativa);
            if (!is_file($rutaCompletaArchivo)) {
                throw new \RuntimeException('No se pudo guardar la evidencia en el almacenamiento compartido.');
            }

            MesaPazAsistencia::where('user_id', $userId)
                ->whereDate('fecha_asist', $fechaAsistencia)
                ->when($microrregionId !== null, function ($query) use ($microrregionId) {
                    $query->where('microrregion_id', $microrregionId);
                })
                ->update([
                    'evidencia' => $this->encodeEvidencePaths($evidenciasNuevas),
                ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new MesasPazServiceException(
                'No fue posible guardar la evidencia en almacenamiento compartido. Intenta nuevamente.',
                500,
                [
                    'log' => [
                        'user_id' => $userId,
                        'fecha_asist' => $fechaAsistencia,
                        'ruta_intento' => $rutaRelativa,
                        'error' => $e->getMessage(),
                    ],
                ]
            );
        }

        $evidencias = $this->mapEvidenceItems($evidenciasNuevas);

        return [
            'success' => true,
            'message' => 'Evidencia guardada correctamente para la sesión de hoy.',
            'fecha_asist' => $fechaAsistencia,
            'microrregion_id' => $microrregionId,
            'evidencia_url' => $evidencias[0]['url'] ?? null,
            'evidencias' => $evidencias,
            'max_evidencias' => self::MAX_EVIDENCIAS,
        ];
    }

    public function eliminarEvidenciaHoy(int $userId, string $evidenciaPathOrUrl, ?int $selectedMicrorregionId = null, ?string $fecha = null): array
    {
        $fechaAsistencia = $this->resolverFechaAsistencia($userId, $fecha);
        $registrosHoyQuery = MesaPazAsistencia::where('user_id', $userId)
            ->whereDate('fecha_asist', $fechaAsistencia);

        $microrregionId = $this->resolveMicrorregionIdParaEvidencia($userId, $selectedMicrorregionId);
        if ($microrregionId !== null) {
            $registrosHoyQuery->where('microrregion_id', $microrregionId);
        }

        $registrosHoy = $registrosHoyQuery->get();

        if ($registrosHoy->isEmpty()) {
            throw new MesasPazServiceException('No hay sesión registrada hoy para eliminar evidencia.', 422);
        }

        $pathObjetivo = $this->extractLocalPath($evidenciaPathOrUrl);
        if (empty($pathObjetivo)) {
            throw new MesasPazServiceException('La evidencia seleccionada no es válida.', 422);
        }

        $evidenciasActuales = $this->resolverEvidenciasHoy($registrosHoy);
        if (empty($evidenciasActuales)) {
            throw new MesasPazServiceException('No hay evidencias registradas hoy para eliminar.', 422);
        }

        if (!in_array($pathObjetivo, $evidenciasActuales, true)) {
            throw new MesasPazServiceException('La evidencia seleccionada ya no existe o no pertenece a la sesión de hoy.', 422);
        }

        $evidenciasNuevas = array_values(array_filter($evidenciasActuales, function ($path) use ($pathObjetivo) {
            return $path !== $pathObjetivo;
        }));

        DB::beginTransaction();
        try {
            MesaPazAsistencia::where('user_id', $userId)
                ->whereDate('fecha_asist', $fechaAsistencia)
                ->when($microrregionId !== null, function ($query) use ($microrregionId) {
                    $query->where('microrregion_id', $microrregionId);
                })
                ->update([
                    'evidencia' => $this->encodeEvidencePaths($evidenciasNuevas),
                ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new MesasPazServiceException(
                'No fue posible eliminar la evidencia. Intenta nuevamente.',
                500,
                [
                    'log' => [
                        'user_id' => $userId,
                        'fecha_asist' => $fechaAsistencia,
                        'path_objetivo' => $pathObjetivo,
                        'error' => $e->getMessage(),
                    ],
                ]
            );
        }

        $this->deleteEvidenceFile($pathObjetivo);

        $evidencias = $this->mapEvidenceItems($evidenciasNuevas);

        return [
            'success' => true,
            'message' => 'Evidencia eliminada correctamente.',
            'fecha_asist' => $fechaAsistencia,
            'microrregion_id' => $microrregionId,
            'evidencias' => $evidencias,
            'max_evidencias' => self::MAX_EVIDENCIAS,
        ];
    }

    private function resolveMicrorregionIdParaEvidencia(int $userId, ?int $selectedMicrorregionId): ?int
    {
        $microrregionIds = $this->microrregionesPermitidasPorUsuario($userId);
        if (empty($microrregionIds)) {
            return null;
        }

        if ($selectedMicrorregionId !== null) {
            if (!in_array($selectedMicrorregionId, $microrregionIds, true)) {
                throw new MesasPazServiceException('La microrregión seleccionada no está dentro de tus asignaciones.', 422);
            }

            return $selectedMicrorregionId;
        }

        return count($microrregionIds) === 1 ? (int) $microrregionIds[0] : null;
    }

    public function guardarMunicipio(int $userId, array $data): array
    {
        $delegado = $this->delegadoPorUsuario($userId);
        $microrregionIds = $this->microrregionesPermitidasPorUsuario($userId, $delegado);
        if (empty($microrregionIds)) {
            throw new MesasPazServiceException('No cuentas con una microrregión válida para registrar asistencias.', 422);
        }

        $municipio = Municipio::query()
            ->select(['id', 'microrregion_id'])
            ->where('id', (int) $data['municipio_id'])
            ->first();

        if (!$municipio || !in_array((int) $municipio->microrregion_id, $microrregionIds, true)) {
            throw new MesasPazServiceException('El municipio seleccionado no pertenece a tus microrregiones asignadas.', 422);
        }

        $fechaAsistencia = $this->resolverFechaAsistencia($userId, $data['fecha_asist'] ?? null);
        $presidente = $this->mapPresidenteForStorage($data['presidente'] ?? null);
        $asiste = $this->resolverAsisteDesdePresidente($presidente, $data['representante'] ?? null);
        $delegadoAsistio = (string) ($data['delegado_asistio'] ?? '');

        $override = $this->resolverReglaEspecialPorModalidad((string) ($data['modalidad'] ?? ''));
        if ($override !== null) {
            $presidente = $override['presidente'];
            $asiste = $override['asiste'];
            $delegadoAsistio = $override['delegado_asistio'];
        }

        DB::beginTransaction();
        try {
            $registro = MesaPazAsistencia::where('user_id', $userId)
                ->where('municipio_id', (int) $data['municipio_id'])
                ->whereDate('fecha_asist', $fechaAsistencia)
                ->first();

            if ($registro) {
                throw new MesasPazServiceException('La asistencia de este municipio ya fue registrada hoy y no puede editarse nuevamente.', 422);
            }

            $payloadCrear = [
                'user_id' => $userId,
                'delegado_id' => $delegado?->id,
                'microrregion_id' => (int) $municipio->microrregion_id,
                'municipio_id' => (int) $data['municipio_id'],
                'fecha_asist' => $fechaAsistencia,
                'presidente' => $presidente,
                'delegado_asistio' => $delegadoAsistio,
                'asiste' => $asiste,
                'modalidad' => $data['modalidad'],
                'evidencia' => null,
                'acuerdo_observacion' => null,
                'created_at' => Carbon::now(),
            ];

            if ($this->tieneColumnaParte()) {
                $payloadCrear['parte_observacion'] = null;
            }

            MesaPazAsistencia::create($payloadCrear);

            $respondidos = MesaPazAsistencia::with('municipio:id,municipio')
                ->where('user_id', $userId)
                ->whereDate('fecha_asist', $fechaAsistencia)
                ->orderBy('municipio_id')
                ->get();

            $historialHoy = $respondidos->isNotEmpty()
                ? (object) [
                    'fecha_asist' => $fechaAsistencia,
                    'total_registros' => $respondidos->count(),
                    'ultima_captura' => $respondidos->max('created_at'),
                ]
                : null;

            DB::commit();
        } catch (MesasPazServiceException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            if ((string) $e->getCode() === '23000') {
                throw new MesasPazServiceException('Ya existe asistencia para este municipio en la fecha actual.', 422);
            }

            throw new MesasPazServiceException('No fue posible guardar la asistencia del municipio. Intenta nuevamente.', 500);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new MesasPazServiceException('No fue posible guardar la asistencia del municipio. Intenta nuevamente.', 500);
        }

        return [
            'success' => true,
            'message' => 'Asistencia registrada correctamente.',
            'accion' => 'guardado',
            'fecha_asist' => $fechaAsistencia,
            'historial_hoy' => $historialHoy ? [
                'fecha_asist' => Carbon::parse($historialHoy->fecha_asist)->toDateString(),
                'total_registros' => (int) $historialHoy->total_registros,
                'ultima_captura' => optional($historialHoy->ultima_captura)
                    ? Carbon::parse($historialHoy->ultima_captura)->format('H:i')
                    : null,
            ] : null,
            'respondidos' => $respondidos->map(function ($item) {
                return [
                    'municipio_id' => $item->municipio_id,
                    'municipio' => optional($item->municipio)->municipio,
                    'presidente' => $this->mapPresidenteForDisplay($item->presidente),
                    'asiste' => $item->asiste,
                ];
            })->values(),
        ];
    }

    public function guardarAcuerdoHoy(int $userId, array $data): array
    {
        $fechaAsistencia = $this->resolverFechaAsistencia($userId, $data['fecha_asist'] ?? null);
        $usarParte = $this->tieneColumnaParte();
        $parteItems = $usarParte ? $this->resolverParteItemsDesdeData($data) : [];
        $acuerdoItems = $this->resolverAcuerdoItemsDesdeData($data);

        $override = $this->resolverReglaEspecialPorModalidad((string) ($data['modalidad'] ?? ''));
        $modalidad = trim((string) ($data['modalidad'] ?? ''));
        $esSuspension = in_array($modalidad, ['Suspención de mesa de Seguridad', 'Suspención de la Mesa de Seguridad'], true);

        if ($usarParte && $esSuspension) {
            $parteItems = ['S/R'];
        }

        if ($esSuspension && empty($acuerdoItems)) {
            $acuerdoItems = ['Motivo no registrado'];
        }

        if (!$esSuspension && empty($parteItems) && empty($acuerdoItems)) {
            $acuerdoItems = ['No se ha anotado nada'];
        }

        $parteObservacion = $usarParte ? MesaPazAsistencia::encodeAcuerdoItems($parteItems) : null;
        $acuerdoObservacion = MesaPazAsistencia::encodeAcuerdoItems($acuerdoItems);

        if ($override !== null) {
            $delegado = $this->delegadoPorUsuario($userId);
            $microrregionIds = $this->microrregionesPermitidasPorUsuario($userId, $delegado);
            if (empty($microrregionIds)) {
                throw new MesasPazServiceException('No cuentas con una microrregión válida para registrar asistencias.', 422);
            }

            $municipioIds = $this->municipiosPermitidosPorUsuario($userId, $delegado);
            if (empty($municipioIds)) {
                throw new MesasPazServiceException('No hay municipios asignados para aplicar esta modalidad.', 422);
            }

            $municipioToMicro = Municipio::query()
                ->whereIn('id', $municipioIds)
                ->pluck('microrregion_id', 'id')
                ->mapWithKeys(function ($microId, $municipioId) {
                    return [(int) $municipioId => (int) $microId];
                })
                ->all();

            DB::transaction(function () use ($municipioIds, $municipioToMicro, $delegado, $userId, $fechaAsistencia, $data, $override, $usarParte, $parteObservacion, $acuerdoObservacion) {
                foreach ($municipioIds as $municipioId) {
                    $registro = MesaPazAsistencia::where('user_id', $userId)
                        ->where('municipio_id', (int) $municipioId)
                        ->whereDate('fecha_asist', $fechaAsistencia)
                        ->first();

                    if ($registro) {
                        $registro->presidente = $override['presidente'];
                        $registro->asiste = $override['asiste'];
                        $registro->delegado_asistio = $override['delegado_asistio'];
                        $registro->modalidad = (string) ($data['modalidad'] ?? $registro->modalidad);
                        if ($usarParte) {
                            $registro->setAttribute('parte_observacion', $parteObservacion);
                        }
                        $registro->setAttribute('acuerdo_observacion', $acuerdoObservacion);
                        $registro->save();
                        continue;
                    }

                    $payloadCrear = [
                        'user_id' => $userId,
                        'delegado_id' => $delegado?->id,
                        'microrregion_id' => (int) ($municipioToMicro[(int) $municipioId] ?? 0),
                        'municipio_id' => (int) $municipioId,
                        'fecha_asist' => $fechaAsistencia,
                        'presidente' => $override['presidente'],
                        'delegado_asistio' => $override['delegado_asistio'],
                        'asiste' => $override['asiste'],
                        'modalidad' => (string) ($data['modalidad'] ?? ''),
                        'evidencia' => null,
                        'acuerdo_observacion' => $acuerdoObservacion,
                        'created_at' => Carbon::now(),
                    ];

                    if ($usarParte) {
                        $payloadCrear['parte_observacion'] = $parteObservacion;
                    }

                    MesaPazAsistencia::create($payloadCrear);
                }
            });

            return [
                'success' => true,
                'message' => 'Parte y acuerdos guardados y respuestas aplicadas automáticamente para todos los municipios.',
                'fecha_asist' => $fechaAsistencia,
                'parte_observacion' => $parteObservacion,
                'parte_observacion_items' => $parteItems,
                'acuerdo_observacion' => $acuerdoObservacion,
                'acuerdo_observacion_items' => $acuerdoItems,
            ];
        }

        if (!MesaPazAsistencia::where('user_id', $userId)
            ->whereDate('fecha_asist', $fechaAsistencia)
            ->exists()) {
            throw new MesasPazServiceException('Primero registra al menos un municipio para guardar acuerdos al final.', 422);
        }

        $payloadUpdate = [
            'acuerdo_observacion' => $acuerdoObservacion,
        ];
        if ($usarParte) {
            $payloadUpdate['parte_observacion'] = $parteObservacion;
        }

        MesaPazAsistencia::where('user_id', $userId)
            ->whereDate('fecha_asist', $fechaAsistencia)
            ->update($payloadUpdate);

        return [
            'success' => true,
            'message' => 'Parte y acuerdos actualizados correctamente.',
            'fecha_asist' => $fechaAsistencia,
            'parte_observacion' => $parteObservacion,
            'parte_observacion_items' => $parteItems,
            'acuerdo_observacion' => $acuerdoObservacion,
            'acuerdo_observacion_items' => $acuerdoItems,
        ];
    }

    public function detallePorFecha(int $userId, string $fecha): array
    {
        $fechaNormalizada = Carbon::parse($fecha)->toDateString();
        $registros = MesaPazAsistencia::with(['municipio:id,municipio', 'microrregion:id,microrregion,cabecera'])
            ->where('user_id', $userId)
            ->whereDate('fecha_asist', $fechaNormalizada)
            ->orderBy('microrregion_id')
            ->orderBy('municipio_id')
            ->get();

        return [
            'success' => true,
            'fecha' => $fechaNormalizada,
            'total' => $registros->count(),
            'registros' => $registros->map(function ($registro) {
                $evidenciaPaths = $this->decodeEvidencePaths($registro->evidencia);
                $evidenciaUrls = array_filter(array_map(function ($path) {
                    return $this->toLocalPublicUrl((string) $path);
                }, $evidenciaPaths));

                return [
                    'municipio' => optional($registro->municipio)->municipio,
                    'microrregion_id' => $registro->microrregion_id,
                    'microrregion_nombre' => optional($registro->microrregion)->microrregion,
                    'microrregion_cabecera' => optional($registro->microrregion)->cabecera,
                    'presidente' => $this->mapPresidenteForDisplay($registro->presidente),
                    'delegado_asistio' => $registro->delegado_asistio,
                    'asiste' => $registro->asiste,
                    'modalidad' => $registro->modalidad,
                    'parte_observacion' => $registro->parte_observacion,
                    'parte_observacion_items' => $registro->parte_items,
                    'acuerdo_observacion' => $registro->acuerdo_observacion,
                    'acuerdo_observacion_items' => $registro->acuerdo_items,
                    'evidencia_disponible' => !empty($evidenciaUrls),
                    'evidencia_url' => $evidenciaUrls[0] ?? null,
                    'evidencia_urls' => array_values($evidenciaUrls),
                ];
            })->values(),
        ];
    }

    public function storeAsistencias(int $userId, array $data): array
    {
        $delegado = $this->delegadoPorUsuario($userId);
        $microrregionIds = $this->microrregionesPermitidasPorUsuario($userId, $delegado);
        if (empty($microrregionIds)) {
            throw new MesasPazServiceException('No cuentas con una microrregión válida para registrar asistencias.', 422);
        }

        $registros = (array) ($data['registros'] ?? []);
        $fechaAsistencia = $this->resolverFechaAsistencia($userId, $data['fecha_asist'] ?? null);
        $usarParte = $this->tieneColumnaParte();
        $parteItems = $usarParte ? $this->resolverParteItemsDesdeData($data) : [];
        $parteObservacion = $usarParte ? MesaPazAsistencia::encodeAcuerdoItems($parteItems) : null;
        $acuerdoItems = $this->resolverAcuerdoItemsDesdeData($data);
        $acuerdoObservacion = MesaPazAsistencia::encodeAcuerdoItems($acuerdoItems);

        if (MesaPazAsistencia::where('user_id', $userId)
            ->whereDate('fecha_asist', $fechaAsistencia)
            ->exists()) {
            throw new MesasPazServiceException('Ya existe una asistencia registrada para hoy ('.$fechaAsistencia.'). No es posible capturar nuevamente.', 422);
        }

        $municipioIds = collect($registros)->pluck('municipio_id')->map(function ($id) {
            return (int) $id;
        })->values()->all();

        $municipioToMicro = Municipio::query()
            ->whereIn('id', $municipioIds)
            ->pluck('microrregion_id', 'id')
            ->mapWithKeys(function ($microId, $municipioId) {
                return [(int) $municipioId => (int) $microId];
            })
            ->all();

        foreach ($municipioIds as $municipioId) {
            $microId = (int) ($municipioToMicro[$municipioId] ?? 0);
            if ($microId <= 0 || !in_array($microId, $microrregionIds, true)) {
                throw new MesasPazServiceException('Hay municipios fuera de tus microrregiones asignadas. Verifica la selección.', 422);
            }
        }

        $municipiosYaRegistrados = MesaPazAsistencia::whereDate('fecha_asist', $fechaAsistencia)
            ->whereIn('municipio_id', $municipioIds)
            ->pluck('municipio_id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->values()
            ->all();

        if (!empty($municipiosYaRegistrados)) {
            $nombresMunicipios = Municipio::whereIn('id', $municipiosYaRegistrados)
                ->orderBy('municipio')
                ->pluck('municipio')
                ->values()
                ->all();

            throw new MesasPazServiceException(
                'Ya existe una asistencia registrada para uno o más municipios en la fecha '.$fechaAsistencia.'.',
                422,
                ['municipios' => $nombresMunicipios]
            );
        }

        try {
            DB::transaction(function () use ($registros, $municipioToMicro, $data, $delegado, $userId, $fechaAsistencia, $usarParte, $parteObservacion, $acuerdoObservacion) {
                $override = $this->resolverReglaEspecialPorModalidad((string) ($data['modalidad'] ?? ''));
                $delegadoAsistioGlobal = (string) ($data['delegado_asistio'] ?? '');

                if ($override !== null) {
                    $delegadoAsistioGlobal = $override['delegado_asistio'];
                }

                foreach ($registros as $registro) {
                    $presidente = $this->mapPresidenteForStorage($registro['presidente'] ?? null);
                    $asiste = $this->resolverAsisteDesdePresidente($presidente, $registro['representante'] ?? null);

                    if ($override !== null) {
                        $presidente = $override['presidente'];
                        $asiste = $override['asiste'];
                    }

                    $payloadCrear = [
                        'user_id' => $userId,
                        'delegado_id' => $delegado?->id,
                        'microrregion_id' => (int) ($municipioToMicro[(int) $registro['municipio_id']] ?? 0),
                        'municipio_id' => (int) $registro['municipio_id'],
                        'fecha_asist' => $fechaAsistencia,
                        'presidente' => $presidente,
                        'delegado_asistio' => $delegadoAsistioGlobal,
                        'asiste' => $asiste,
                        'modalidad' => $data['modalidad'],
                        'evidencia' => null,
                        'acuerdo_observacion' => $acuerdoObservacion,
                        'created_at' => Carbon::now(),
                    ];

                    if ($usarParte) {
                        $payloadCrear['parte_observacion'] = $parteObservacion;
                    }

                    MesaPazAsistencia::create($payloadCrear);
                }
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                throw new MesasPazServiceException('No se pudo guardar porque ya existe una asistencia para algún municipio en esta fecha.', 422);
            }

            throw new MesasPazServiceException('No fue posible guardar la asistencia. Intenta nuevamente.', 500);
        } catch (\Throwable $e) {
            throw new MesasPazServiceException('No fue posible guardar la asistencia. Intenta nuevamente.', 500);
        }

        return [
            'success' => true,
            'message' => 'Asistencia registrada correctamente.',
            'fecha_asist' => $fechaAsistencia,
            'registros_guardados' => count($registros),
        ];
    }

    public function vaciarRegistrosMicrorregion(int $userId, string $fecha, int $microrregionId): array
    {
        $fechaObjetivo = $this->resolverFechaAsistencia($userId, $fecha);

        $delegado = $this->delegadoPorUsuario($userId);
        $microrregionesRoles = $this->microrregionesPermitidasPorUsuario($userId, $delegado);

        if (!in_array($microrregionId, $microrregionesRoles, true)) {
            throw new MesasPazServiceException('No tienes permiso para modificar la información de esta microrregión.', 403);
        }

        try {
            DB::beginTransaction();
            $registrosEliminados = MesaPazAsistencia::where('user_id', $userId)
                ->where('microrregion_id', $microrregionId)
                ->whereDate('fecha_asist', $fechaObjetivo)
                ->delete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new MesasPazServiceException('Error al vaciar los registros en la base de datos: ' . $e->getMessage(), 500);
        }

        return [
            'success' => true,
            'message' => 'Se han eliminado ' . $registrosEliminados . ' registros de esta microrregión exitosamente.',
            'registros_eliminados' => $registrosEliminados,
            'fecha_asist' => $fechaObjetivo,
        ];
    }

    public function importarExcelData(int $userId, string $fechaImportacion, UploadedFile $archivo): array
    {
        $delegado = $this->delegadoPorUsuario($userId);
        $microrregionIds = $this->microrregionesPermitidasPorUsuario($userId, $delegado);

        if (empty($microrregionIds)) {
            throw new MesasPazServiceException('No tienes microrregiones asignadas para importar.', 403);
        }
        
        $fechaObjetivo = $this->resolverFechaAsistencia($userId, $fechaImportacion);

        try {
            $spreadsheet = IOFactory::load($archivo->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
        } catch (\Throwable $e) {
            throw new MesasPazServiceException('Error al intentar leer el Excel. Detalle del servidor: ' . $e->getMessage(), 422);
        }

        if (empty($rows)) {
            throw new MesasPazServiceException('El archivo Excel está vacío.', 422);
        }

        $rowFechaRaw = trim((string) ($rows[1]['A'] ?? ''));
        $fechaExcelExtraida = null;

        if ($rowFechaRaw) {
            $meses = [
                'ENERO' => '01', 'FEBRERO' => '02', 'MARZO' => '03', 'ABRIL' => '04',
                'MAYO' => '05', 'JUNIO' => '06', 'JULIO' => '07', 'AGOSTO' => '08',
                'SEPTIEMBRE' => '09', 'OCTUBRE' => '10', 'NOVIEMBRE' => '11', 'DICIEMBRE' => '12'
            ];

            if (preg_match('/(\d{1,2})\s+DE\s+([A-Za-z]+)\s+DE\s+(\d{4})/u', mb_strtoupper($rowFechaRaw, 'UTF-8'), $matches)) {
                $dia = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $mesNombre = $matches[2];
                $anio = $matches[3];

                if (isset($meses[$mesNombre])) {
                    $fechaExcelExtraida = "$anio-{$meses[$mesNombre]}-$dia";
                }
            } else {
                $dateFormats = ['Y-m-d', 'd/m/Y', 'Y/m/d', 'd-m-Y'];
                foreach($dateFormats as $format) {
                    try {
                        $tempStr = $rowFechaRaw;
                        if (strpos((string)$tempStr, ' ') !== false) {
                            $tempStr = explode(' ', $tempStr)[0];
                        }
                        $fechaExcelExtraida = Carbon::createFromFormat($format, $tempStr)->toDateString();
                        break;
                    } catch (\Exception $e) { }
                }
            }
            
            if ($fechaExcelExtraida) {
                if ($fechaExcelExtraida !== $fechaObjetivo) {
                    throw new MesasPazServiceException("La fecha del documento ($fechaExcelExtraida) es diferente a la marcada en el filtro ($fechaObjetivo).", 422);
                }
            } elseif (preg_match('/\d{2,4}[-\/]\d{2}[-\/]\d{2,4}/', $rowFechaRaw)) {
                throw new MesasPazServiceException("La fecha del documento tiene un formato irreconocible y parece ser diferente a la marcada ($fechaObjetivo).", 422);
            }
        }

        $municipiosMap = DB::table('municipios')
            ->whereIn('microrregion_id', $microrregionIds)
            ->select('id', 'municipio', 'microrregion_id')
            ->get();
            
        $microrregionesMap = DB::table('microrregiones')
            ->whereIn('id', $microrregionIds)
            ->select('id', 'microrregion')
            ->get();
            
        $municipiosPorNombre = [];
        foreach ($municipiosMap as $m) {
            $nombreNormalizado = $this->quitarAcentos(mb_strtoupper((string) $m->municipio, 'UTF-8'));
            $municipiosPorNombre[$nombreNormalizado] = $m;
        }

        $microPorNombre = [];
        foreach ($microrregionesMap as $m) {
            $nombreNormalizado = $this->quitarAcentos(mb_strtoupper((string) $m->microrregion, 'UTF-8'));
            $microPorNombre[$nombreNormalizado] = $m->id;
        }

        $usarParte = $this->tieneColumnaParte();
        $registrosParseados = [];
        $municipioIdsYaVistos = [];

        // Auto-detectar la columna de 'Acuerdos' en las filas cabecera (usualmente 1 o 2)
        $acuerdosCol = 'J'; // Default por si no se encuentra
        for ($i = 1; $i <= 2; $i++) {
            if (!empty($rows[$i])) {
                foreach ($rows[$i] as $colLetter => $val) {
                    $head = $this->quitarAcentos(mb_strtoupper(trim((string) $val), 'UTF-8'));
                    if (strpos((string)$head, 'ACUERDO') !== false || strpos((string)$head, 'OBSERVACION') !== false) {
                        $acuerdosCol = $colLetter;
                        break 2;
                    }
                }
            }
        }

        foreach ($rows as $index => $row) {
            // Ignorar cabecera(s) (asumiendo que los datos inician en fila 2 o despues)
            if ($index < 2) continue;

            $colMicrorregion = $this->quitarAcentos(mb_strtoupper(trim((string) ($row['B'] ?? '')), 'UTF-8'));
            $colMunicipio = $this->quitarAcentos(mb_strtoupper(trim((string) ($row['D'] ?? '')), 'UTF-8'));
            $colAsiste = mb_strtoupper(trim((string) ($row['H'] ?? '')), 'UTF-8');
            $colModalidad = mb_strtoupper(trim((string) ($row['I'] ?? '')), 'UTF-8');
            $colAcuerdosRaw = trim((string) ($row[$acuerdosCol] ?? ''));

            // Primero, asegurarnos de que el texto no esté vacío.
            if (empty($colAsiste) || empty($colMunicipio)) {
                // Si asiste está vacío, o no hay municipio, ignoramos.
                continue;
            }

            // Validar la microrregión si es posible (Ej. "14 CIUDAD SERDAN" o "CHALCHICOMULA DE SESMA MR 14")
            $colMicroMatchId = null;
            if (preg_match('/\b(\d+)\b/', $colMicrorregion, $matchesMicro)) {
                $colMicroMatchId = (int) $matchesMicro[1];
            } else {
                // Si no hay numero, checamos el nombre (por buscar flexibilidad, aunque el user ya definió que usa numeros).
                if (isset($microPorNombre[$colMicrorregion])) {
                    $colMicroMatchId = $microPorNombre[$colMicrorregion];
                }
            }

            // Mapa para alias comunes que en Excel vienen distintos a la BD
            $aliasMunicipios = [
                'CIUDAD SERDAN' => 'CHALCHICOMULA DE SESMA',
                // Agregar otros alias aquí si el usuario lo reporta en el futuro
            ];

            if (isset($aliasMunicipios[$colMunicipio])) {
                $colMunicipio = $aliasMunicipios[$colMunicipio];
            }

            // Flexibilizar validación intentando con y sin acentos
            $municipioDB = null;
            if (isset($municipiosPorNombre[$colMunicipio])) {
                $municipioDB = $municipiosPorNombre[$colMunicipio];
            } else {
                // Iterar para encontrar coincidencias borrosas ignorando acentos y espacios extra
                $colMuniLimpio = $this->quitarAcentos(preg_replace('/\s+/', ' ', $colMunicipio));
                foreach ($municipiosPorNombre as $nombreClave => $mDB) {
                    $dbLimpio = $this->quitarAcentos(preg_replace('/\s+/', ' ', $nombreClave));
                    if ($dbLimpio === $colMuniLimpio) {
                        $municipioDB = $mDB;
                        break;
                    }
                }
            }

            if (!$municipioDB) {
                // Municipio no permitido o no pertenece a enlaces asignadas.
                continue;
            }

            // Verificación extra cruzada: El municipioDB debe corresponder a la microrregión si logramos parsearla.
            // Si $colMicroMatchId existe y el user tiene microrregiones asignadas, nos cercioramos que concuerde.
            if ($colMicroMatchId !== null && $municipioDB->microrregion_id !== $colMicroMatchId) {
                // La microrregión leída en la columna B no concuerda con la microrregión del municipio D en la base de datos
                continue;
            }

            if (in_array($municipioDB->id, $municipioIdsYaVistos)) {
                continue; // Prevent duplicates in the same file
            }
            
            $presidente = null;
            $representante = null;

            if ($colAsiste === 'DIRECTOR DE SEGURIDAD MUNICIPAL' || strpos((string)$colAsiste, 'DIRECTOR') !== false) {
                $presidente = 'Representante';
                $representante = 'Director de seguridad municipal'; // o similar
            } elseif ($colAsiste === 'NO' || $colAsiste === 'NINGUNO') {
                $presidente = 'No';
            } elseif ($colAsiste === 'SI' || strpos((string)$colAsiste, 'PRESIDENTE') !== false) {
                // Handle 'PRESIDENTE', 'PRESIDENTE MUNICIPAL', 'SI', etc.
                $presidente = 'Si';
            } else {
                $presidente = 'Representante'; // Fallback a representante con cargo custom
                $representante = $colAsiste;
            }
            
            $modalidadValida = 'Presencial'; // Default
            if (str_contains($colModalidad, 'VIRTUAL')) {
                $modalidadValida = 'Virtual';
            } elseif (str_contains($colModalidad, 'SUSPENDI') || str_contains($colModalidad, 'SUSPENSION')) {
                $modalidadValida = 'Suspención de mesa de Seguridad';
            }

            $acuerdosList = $this->normalizarAcuerdoItemsDesdeTexto($colAcuerdosRaw);

            $registrosParseados[] = [
                'municipio_id' => $municipioDB->id,
                'microrregion_id' => $municipioDB->microrregion_id,
                'presidente' => $presidente,
                'representante' => $representante,
                'modalidad' => $modalidadValida,
                'acuerdosList' => $acuerdosList
            ];
            
            $municipioIdsYaVistos[] = $municipioDB->id;
        }

        if (empty($registrosParseados)) {
            throw new MesasPazServiceException('No se encontraron registros de municipios válidos asignados a ti en el archivo.', 422);
        }

        $registrosInsertados = 0;

        DB::beginTransaction();
        try {
            foreach ($registrosParseados as $rp) {
                $presidenteFinal = $rp['presidente'];
                $asisteFinal = $this->resolverAsisteDesdePresidente($rp['presidente'], $rp['representante']);
                $delegadoAsistioFinal = 'Si'; // asumimos Si si no viene de reglas

                $override = $this->resolverReglaEspecialPorModalidad($rp['modalidad']);
                if ($override !== null) {
                    $presidenteFinal = $override['presidente'];
                    $asisteFinal = $override['asiste'];
                    $delegadoAsistioFinal = $override['delegado_asistio'];
                }

                $parteItems = [];
                if ($usarParte && strpos((string)$rp['modalidad'], 'Suspención') !== false) {
                    $parteItems = ['S/R'];
                }
                
                $acuerdoItems = $rp['acuerdosList'];
                if (empty($acuerdoItems)) {
                    $acuerdoItems = strpos((string)$rp['modalidad'], 'Suspención') !== false ? ['Motivo no registrado'] : ['No se ha anotado nada'];
                }

                $parteObservacion = $usarParte ? MesaPazAsistencia::encodeAcuerdoItems($parteItems) : null;
                $acuerdoObservacion = MesaPazAsistencia::encodeAcuerdoItems($acuerdoItems);

                $registro = MesaPazAsistencia::where('user_id', $userId)
                    ->where('municipio_id', $rp['municipio_id'])
                    ->whereDate('fecha_asist', $fechaObjetivo)
                    ->first();

                if ($registro) {

                    continue;
                } else {
                    $payloadCrear = [
                        'user_id' => $userId,
                        'delegado_id' => $delegado ? $delegado->id : null,
                        'microrregion_id' => $rp['microrregion_id'],
                        'municipio_id' => $rp['municipio_id'],
                        'fecha_asist' => $fechaObjetivo,
                        'presidente' => $presidenteFinal,
                        'delegado_asistio' => $delegadoAsistioFinal,
                        'asiste' => $asisteFinal,
                        'modalidad' => $rp['modalidad'],
                        'evidencia' => null,
                        'acuerdo_observacion' => $acuerdoObservacion,
                        'created_at' => Carbon::now(),
                    ];

                    if ($usarParte) {
                        $payloadCrear['parte_observacion'] = $parteObservacion;
                    }

                    MesaPazAsistencia::create($payloadCrear);
                    $registrosInsertados++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new MesasPazServiceException('Error al importar en base de datos: ' . $e->getMessage(), 500);
        }

        return [
            'success' => true,
            'message' => 'Se han importado ' . $registrosInsertados . ' registros exitosamente.',
            'registros_importados' => $registrosInsertados,
            'fecha_asist' => $fechaObjetivo,
        ];
    }
    
    private function normalizarAcuerdoItemsDesdeTexto(?string $texto): array
    {
        if (empty($texto)) {
            return [];
        }

        // Use unicode escape sequences to prevent cPanel/FTP encoding corruption (preg_replace compilation error)
        $soloLetras = preg_replace('/[^a-z\x{00F1}\x{00E1}\x{00E9}\x{00ED}\x{00F3}\x{00FA}]/u', '', mb_strtolower($texto, 'UTF-8'));
        if (strpos((string)$soloLetras, 'sinacuerdos') !== false) {
            return ['Sin Acuerdos'];
        }

        $textoNormalizado = str_replace(["\r\n", "\r"], "\n", $texto);
        
        $bloques = [];
        $lineas = explode("\n", $textoNormalizado);
        
        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if ($linea !== '') {
                $lineaLimpia = trim(preg_replace('/^([\-\*\x{2022}]|\d+[\.)])\s*/u', '', $linea));
                if ($lineaLimpia !== '') {
                    $bloques[] = $lineaLimpia;
                }
            }
        }

        return $bloques;
    }


    private function quitarAcentos(string $cadena): string
    {
        $no_permitidas = array ("á","é","í","ó","ú","Á","É","Í","Ó","Ú","ñ","À","Ã","Ì","Ò","Ù","Ã™","Ã ","Ã¨","Ã¬","Ã²","Ã¹","ç","Ç","Ã¢","ê","Ã®","Ã´","Ã»","Ã‚","ÃŠ","ÃŽ","Ã”","Ã›","ü","Ã¶","Ã–","Ã¯","Ã¤","«","Ò","Ã","Ã„","Ã‹");
        $permitidas = array ("a","e","i","o","u","A","E","I","O","U","n","N","A","E","I","O","U","a","e","i","o","u","c","C","a","e","i","o","u","A","E","I","O","U","u","o","O","i","a","e","U","I","A","E");
        return str_replace($no_permitidas, $permitidas ,$cadena);
    }

    private function delegadoPorUsuario(int $userId): ?Delegado
    {
        return Delegado::with('microrregion')
            ->where('user_id', $userId)
            ->first();
    }

    private function microrregionesPermitidasPorUsuario(int $userId, ?Delegado $delegado = null): array
    {
        if ($delegado && $delegado->microrregion_id && $delegado->microrregion) {
            return [(int) $delegado->microrregion_id];
        }

        return DB::table('user_microrregion')
            ->where('user_id', $userId)
            ->pluck('microrregion_id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->filter(function ($id) {
                return $id > 0;
            })
            ->unique()
            ->values()
            ->all();
    }

    private function municipiosPermitidosPorUsuario(int $userId, ?Delegado $delegado = null): array
    {
        $microrregionIds = $this->microrregionesPermitidasPorUsuario($userId, $delegado);
        if (empty($microrregionIds)) {
            return [];
        }

        return DB::table('municipios')
            ->whereIn('microrregion_id', $microrregionIds)
            ->pluck('id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->values()
            ->all();
    }

    private function resolverFechaAsistencia(int $userId, ?string $fechaReq): string
    {
        $delegado = $this->delegadoPorUsuario($userId);
        $microrregionIds = $this->microrregionesPermitidasPorUsuario($userId, $delegado);
        $esAnalistaEnlace = count($microrregionIds) > 1;

        if ($esAnalistaEnlace && $fechaReq && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaReq)) {
            try {
                $parsedDate = Carbon::createFromFormat('Y-m-d', $fechaReq)->startOfDay();
                if ($parsedDate->isWeekend()) {
                    throw new MesasPazServiceException('No está permitido registrar asistencias en fines de semana (sábado y domingo).', 422);
                }
                if ($parsedDate->lte(Carbon::today())) {
                    return $parsedDate->toDateString();
                }
            } catch (MesasPazServiceException $e) {
                throw $e;
            } catch (\Exception $e) {
            }
        }

        return Carbon::today()->toDateString();
    }

    private function resolverAcuerdoItemsDesdeData(array $data): array
    {
        if (array_key_exists('acuerdo_observacion_items', $data)) {
            return MesaPazAsistencia::normalizeAcuerdoItems($data['acuerdo_observacion_items'] ?? []);
        }

        return MesaPazAsistencia::normalizeAcuerdoItems($data['acuerdo_observacion'] ?? null);
    }

    private function resolverParteItemsDesdeData(array $data): array
    {
        if (array_key_exists('parte_observacion_items', $data)) {
            return MesaPazAsistencia::normalizeAcuerdoItems($data['parte_observacion_items'] ?? []);
        }

        return MesaPazAsistencia::normalizeAcuerdoItems($data['parte_observacion'] ?? null);
    }

    private function mapPresidenteForStorage(?string $presidente): ?string
    {
        $valor = trim((string) $presidente);

        if (in_array($valor, ['Presidente', 'Si'], true)) {
            return 'Si';
        }

        if (in_array($valor, ['Ninguno', 'No'], true)) {
            return 'No';
        }

        if ($valor === 'Ambos') {
            return 'Ambos';
        }

        return $valor === '' ? null : $valor;
    }

    private function mapPresidenteForDisplay(?string $presidente): ?string
    {
        if ($presidente === 'Si') {
            return 'Sí';
        }

        if ($presidente === 'Representante') {
            return 'Representante';
        }

        if (in_array($presidente, ['No', 'Ninguno'], true)) {
            return 'Municipio no presente';
        }

        if ($presidente === 'Ambos') {
            return 'Ambos (Presidente y Representante)';
        }

        return $presidente;
    }

    private function resolverAsisteDesdePresidente(?string $presidente, ?string $representante): ?string
    {
        if ($presidente === 'Si') {
            return 'Presidente';
        }
        if ($presidente === 'Representante') {
            return 'Director de seguridad';
        }
        if ($presidente === 'Ambos') {
            return 'Presidente y Director de seguridad';
        }
        if ($presidente === 'No') {
            return 'NO';
        }
        return null;
    }

    private function resolverReglaEspecialPorModalidad(string $modalidad): ?array
    {
        $valor = mb_strtolower(trim($modalidad));
        $mod = $this->quitarAcentos(mb_strtoupper($modalidad, 'UTF-8'));

        if (strpos((string)$mod, 'SUSPENCION') !== false) {
            return [
                'asiste' => 'Ninguno',
                'presidente' => 'S/R',
                'delegado_asistio' => 'S/R',
            ];
        }

        if ($valor === mb_strtolower('Sin reporte de Delegado')) {
            return [
                'asiste' => 'SRD',
                'presidente' => 'S/R',
                'delegado_asistio' => 'No',
            ];
        }

        if ($valor === mb_strtolower('Sin información de enlace')) {
            return [
                'asiste' => 'SIE',
                'presidente' => 'S/R',
                'delegado_asistio' => 'No',
            ];
        }

        if (
            $valor === mb_strtolower('Suspención de mesa de Seguridad')
            || $valor === mb_strtolower('Suspención de la Mesa de Seguridad')
        ) {
            return [
                'asiste' => 'Suspención',
                'presidente' => 'No',
                'delegado_asistio' => 'No',
            ];
        }

        return null;
    }

    private function resolverEvidenciasHoy($registrosHoy): array
    {
        return collect($registrosHoy)
            ->pluck('evidencia')
            ->flatMap(function ($valor) {
                return $this->decodeEvidencePaths($valor);
            })
            ->filter(function ($path) {
                return !empty($path);
            })
            ->unique()
            ->values()
            ->all();
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
                return $this->extractLocalPath((string) $item);
            })
            ->filter(function ($path) {
                return !empty($path);
            })
            ->unique()
            ->values()
            ->all();
    }

    private function encodeEvidencePaths(array $paths): ?string
    {
        $normalizados = collect($paths)
            ->map(function ($item) {
                return $this->extractLocalPath((string) $item);
            })
            ->filter(function ($path) {
                return !empty($path);
            })
            ->unique()
            ->values()
            ->all();

        if (empty($normalizados)) {
            return null;
        }

        return json_encode($normalizados, JSON_UNESCAPED_UNICODE);
    }

    private function mapEvidenceItems(array $paths): array
    {
        return collect($paths)
            ->map(function ($path) {
                $url = $this->toLocalPublicUrl((string) $path);

                return [
                    'path' => (string) $path,
                    'url' => $url,
                ];
            })
            ->filter(function ($item) {
                return !empty($item['path']) && !empty($item['url']);
            })
            ->values()
            ->all();
    }

    private function toLocalPublicUrl(string $pathOrUrl): string
    {
        if (filter_var($pathOrUrl, FILTER_VALIDATE_URL)) {
            return $pathOrUrl;
        }

        $normalized = $this->extractLocalPath($pathOrUrl);
        if ($normalized === null) {
            return '';
        }

        return route('mesas-paz.evidencia.preview', ['path' => $this->encodePathForPreview($normalized)]);
    }

    private function extractLocalPath(string $pathOrUrl): ?string
    {
        if (!filter_var($pathOrUrl, FILTER_VALIDATE_URL)) {
            return $this->normalizeEvidencePath($pathOrUrl);
        }

        $queryPath = (string) parse_url($pathOrUrl, PHP_URL_QUERY);
        parse_str($queryPath, $query);
        $encoded = isset($query['path']) ? (string) $query['path'] : '';
        if ($encoded !== '') {
            $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
            if (is_string($decoded) && $decoded !== '') {
                $normalized = $this->normalizeEvidencePath($decoded);
                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        $path = ltrim((string) parse_url($pathOrUrl, PHP_URL_PATH), '/');
        $normalized = $this->normalizeEvidencePath($path);
        if ($normalized !== null) {
            return $normalized;
        }

        return null;
    }

    public function resolveEvidenceFilePath(string $path): ?string
    {
        $normalized = $this->normalizeEvidencePath($path);
        if ($normalized === null) {
            return null;
        }

        if (strpos((string)$normalized, self::SHARED_STORAGE_DIR.'/') === 0 && Storage::disk(self::SHARED_DISK)->exists($normalized)) {
            return Storage::disk(self::SHARED_DISK)->path($normalized);
        }

        $legacyPublicPath = public_path($normalized);
        if (is_file($legacyPublicPath)) {
            return $legacyPublicPath;
        }

        return null;
    }

    public function canUserPreviewEvidence(int $userId, string $path): bool
    {
        $normalized = $this->normalizeEvidencePath($path);
        if ($normalized === null) {
            return false;
        }

        $user = auth()->user();
        if (!$user) {
            return false;
        }

        if ($user->can('Tableros-incidencias')) {
            return MesaPazAsistencia::query()
                ->whereNotNull('evidencia')
                ->where('evidencia', 'like', '%'.$normalized.'%')
                ->exists();
        }

        if (!$user->can('Mesas-Paz')) {
            return false;
        }

        return MesaPazAsistencia::query()
            ->where('user_id', $userId)
            ->whereNotNull('evidencia')
            ->where('evidencia', 'like', '%'.$normalized.'%')
            ->exists();
    }

    private function deleteEvidenceFile(string $path): void
    {
        $normalized = $this->normalizeEvidencePath($path);
        if ($normalized === null) {
            return;
        }

        if (strpos((string)$normalized, self::SHARED_STORAGE_DIR.'/') === 0) {
            Storage::disk(self::SHARED_DISK)->delete($normalized);
            return;
        }

        $legacyPublicPath = public_path($normalized);
        if (is_file($legacyPublicPath)) {
            @unlink($legacyPublicPath);
        }
    }

    private function normalizeEvidencePath(string $rawPath): ?string
    {
        $path = ltrim(str_replace('\\', '/', trim($rawPath)), '/');
        if ($path === '' || strpos((string)$path, '..') !== false) {
            return null;
        }

        if (strpos((string)$path, self::LEGACY_PUBLIC_DIR.'/') === 0) {
            return $path;
        }

        if (strpos((string)$path, self::SHARED_STORAGE_DIR.'/') === 0) {
            return $path;
        }

        return null;
    }

    private function encodePathForPreview(string $path): string
    {
        return rtrim(strtr(base64_encode($path), '+/', '-_'), '=');
    }

    private function tieneColumnaParte(): bool
    {
        if ($this->parteColumnAvailable !== null) {
            return $this->parteColumnAvailable;
        }

        $this->parteColumnAvailable = Schema::hasColumn('mesas_paz_asistencias', 'parte_observacion');
        return $this->parteColumnAvailable;
    }
}
