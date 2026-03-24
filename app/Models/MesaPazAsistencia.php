<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MesaPazAsistencia extends Model
{
    // Entidad persistente para asistencias de Mesas de Paz.
    protected $table = 'mesas_paz_asistencias';

    // PK personalizada de la tabla.
    protected $primaryKey = 'asist_id';

    // La tabla administra created_at manualmente, sin updated_at.
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'delegado_id',
        'microrregion_id',
        'municipio_id',
        'fecha_asist',
        'presidente',
        'delegado_asistio',
        'asiste',
        'modalidad',
        'evidencia',
        'parte_observacion',
        'acuerdo_observacion',
        'created_at',
    ];

    protected $casts = [
        'fecha_asist' => 'date',
        'created_at' => 'datetime',
    ];

    // Datos derivados: expone los acuerdos como colección de cadenas para frontend y reportes.
    protected $appends = [
        'parte_items',
        'acuerdo_items',
    ];

    /**
     * Accessor: obtiene parte normalizado como arreglo de strings.
     */
    public function getParteItemsAttribute(): array
    {
        return static::normalizeAcuerdoItems($this->attributes['parte_observacion'] ?? null);
    }

    /**
     * Mutator: guarda parte_observacion serializado como JSON array.
     */
    public function setParteObservacionAttribute($value): void
    {
        $this->attributes['parte_observacion'] = static::encodeAcuerdoItems($value);
    }

    /**
     * Accessor: obtiene acuerdos normalizados como arreglo de strings.
     */
    public function getAcuerdoItemsAttribute(): array
    {
        return static::normalizeAcuerdoItems($this->attributes['acuerdo_observacion'] ?? null);
    }

    /**
     * Mutator: guarda acuerdo_observacion serializado como JSON array.
     */
    public function setAcuerdoObservacionAttribute($value): void
    {
        $this->attributes['acuerdo_observacion'] = static::encodeAcuerdoItems($value);
    }

    /**
     * Normaliza el input a una colección de cadenas limpia y consistente.
     */
    public static function normalizeAcuerdoItems($value): array
    {
        if ($value === null) {
            return [];
        }

        $items = [];
        $esOrigenEstructurado = false;

        if (is_array($value)) {
            $items = $value;
            $esOrigenEstructurado = true;
        } elseif (is_string($value)) {
            $texto = trim($value);
            if ($texto === '') {
                return [];
            }

            $patternBloquesVinyeta = '/(?:^|\R)\s*(?:[-*\x{2022}]|\d+[\.)])\s+(.*?)(?=(?:\R\s*(?:[-*\x{2022}]|\d+[\.)])\s+)|$)/us';
            if (preg_match_all($patternBloquesVinyeta, $texto, $coincidencias) && ! empty($coincidencias[1])) {
                $items = array_map(static function ($bloque) {
                    $compactado = preg_replace('/\s*\R\s*/u', ' ', (string) $bloque);

                    return trim((string) $compactado);
                }, $coincidencias[1]);
            } else {
                $decoded = json_decode($texto, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $items = $decoded;
                    $esOrigenEstructurado = true;
                } else {
                    $items = preg_split('/\r\n|\r|\n/u', $texto) ?: [];
                }
            }
        }

        $lineas = [];
        foreach ($items as $item) {
            $lineas[] = trim((string) $item);
        }

        $lineas = array_values(array_filter($lineas, static function ($linea) {
            return $linea !== '';
        }));

        if (empty($lineas)) {
            return [];
        }

        if ($esOrigenEstructurado) {
            $regexVinyeta = '/^([\-*\x{2022}]|\d+[\.)])\s+/u';
            $limpios = [];

            foreach ($lineas as $linea) {
                $textoItem = trim((string) preg_replace($regexVinyeta, '', $linea));
                if ($textoItem !== '' && preg_match('/[\p{L}\p{N}]/u', $textoItem)) {
                    $limpios[] = $textoItem;
                }
            }

            return array_values($limpios);
        }

        $regexVinyeta = '/^([\-*\x{2022}]|\d+[\.)])\s+/u';
        $hayVinyetas = false;
        foreach ($lineas as $linea) {
            if (preg_match($regexVinyeta, $linea)) {
                $hayVinyetas = true;
                break;
            }
        }

        if (! $hayVinyetas) {
            $limpios = [];
            foreach ($lineas as $linea) {
                $textoItem = trim((string) preg_replace('/^[\-*\x{2022}]\s*/u', '', $linea));
                if ($textoItem !== '' && preg_match('/[\p{L}\p{N}]/u', $textoItem)) {
                    $limpios[] = $textoItem;
                }
            }

            if (empty($limpios)) {
                return [];
            }

            $recompuestos = [];
            foreach ($limpios as $fragmento) {
                if (empty($recompuestos)) {
                    $recompuestos[] = $fragmento;

                    continue;
                }

                $indice = count($recompuestos) - 1;
                $anterior = trim((string) $recompuestos[$indice]);
                $actual = trim((string) $fragmento);

                $esContinuacion = false;
                if (preg_match('/[,;:]$/u', $anterior)) {
                    $esContinuacion = true;
                } elseif (preg_match('/^[a-záéíóúñ]/u', $actual)) {
                    $esContinuacion = true;
                } elseif (
                    preg_match('/[.!?]$/u', $anterior) !== 1
                    && preg_match('/^[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+,/u', $actual) === 1
                ) {
                    $esContinuacion = true;
                }

                if ($esContinuacion) {
                    $recompuestos[$indice] = trim($anterior.' '.$actual);
                } else {
                    $recompuestos[] = $actual;
                }
            }

            return array_values($recompuestos);
        }

        $normalizados = [];
        foreach ($lineas as $linea) {
            $esVinyeta = preg_match($regexVinyeta, $linea) === 1;
            $texto = trim((string) preg_replace($regexVinyeta, '', $linea));

            if ($texto === '' || preg_match('/[\p{L}\p{N}]/u', $texto) !== 1) {
                continue;
            }

            if ($esVinyeta || empty($normalizados)) {
                $normalizados[] = $texto;
            } else {
                $indice = count($normalizados) - 1;
                $normalizados[$indice] = trim($normalizados[$indice].' '.$texto);
            }
        }

        return array_values($normalizados);
    }

    /**
     * Inasistencias explícitas (misma lógica que MesasPazSupervisionService).
     */
    public static function asistenciaEsNoPresente(?string $asiste): bool
    {
        $v = mb_strtolower(trim((string) $asiste));

        return in_array($v, [
            'no',
            'srd',
            'sie',
            'suspención',
            'suspencion',
        ], true);
    }

    /**
     * Presencia para totales de reporte: cualquier valor que no sea inasistencia explícita.
     */
    public static function asistenciaEsPresente(?string $asiste): bool
    {
        return ! static::asistenciaEsNoPresente($asiste);
    }

    /**
     * Adjunta los acuerdos como JSON
     */
    public static function encodeAcuerdoItems($value): ?string
    {
        $items = static::normalizeAcuerdoItems($value);
        if (empty($items)) {
            return null;
        }

        return json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // Relación: cada asistencia pertenece a un municipio.
    public function municipio()
    {
        return $this->belongsTo(Municipio::class, 'municipio_id');
    }

    // Relación: cada asistencia pertenece a una microrregión.
    public function microrregion()
    {
        return $this->belongsTo(Microrregione::class, 'microrregion_id');
    }

    // Relación: cada asistencia pertenece al perfil delegado que la capturó.
    public function delegado()
    {
        return $this->belongsTo(Delegado::class, 'delegado_id');
    }

    // Relación: cada asistencia pertenece al usuario autenticado que registró la captura.
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
