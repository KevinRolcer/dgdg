<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Agenda extends Model
{
    public const ESTADO_ACTIVO = 'activo';

    public const ESTADO_CONCLUIDO = 'concluido';

    use HasFactory;

    protected $fillable = [
        'asunto',
        'descripcion',
        'tipo',
        'subtipo',
        'microrregion',
        'municipio',
        'lugar',
        'semaforo',
        'seguimiento',
        'fecha_inicio',
        'fecha_fin',
        'hora',
        'habilitar_hora',
        'repite',
        'dias_repeticion',
        'recordatorio_minutos',
        'direcciones_adicionales',
        'creado_por',
        'parent_id',
        'estado_seguimiento',
        'es_actualizacion',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'habilitar_hora' => 'boolean',
        'repite' => 'boolean',
        'dias_repeticion' => 'array',
        'recordatorio_minutos' => 'integer',
        'direcciones_adicionales' => 'array',
        'es_actualizacion' => 'boolean',
    ];

    public function scopeActivas($query)
    {
        if (!Schema::hasColumn((new static)->getTable(), 'estado_seguimiento')) {
            return $query;
        }

        return $query->where(function ($q) {
            $q->where('estado_seguimiento', self::ESTADO_ACTIVO)
                ->orWhereNull('estado_seguimiento')
                ->orWhere('estado_seguimiento', '');
        });
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }


    /**
     * Users assigned to this agenda item.
     */
    public function usuariosAsignados(): BelongsToMany
    {
        return $this->belongsToMany(User::class , 'agenda_user', 'agenda_id', 'user_id');
    }

    /**
     * The user who created this agenda item.
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class , 'creado_por');
    }

    /**
     * Formato para mostrar el recordatorio: "30 min", "1 h", "1 h 30 min", "2 h", etc.
     */
    public function getReminderLabelAttribute(): string
    {
        $min = (int) ($this->recordatorio_minutos ?? 0);
        if ($min <= 0) {
            return '—';
        }
        if ($min < 60) {
            return $min . ' min';
        }
        $h = (int) floor($min / 60);
        $m = $min % 60;
        if ($m === 0) {
            return $h . ' h';
        }
        return $h . ' h ' . $m . ' min';
    }

    /**
     * Descripción para vista/calendar: líneas antiguas "Aforo: N" pasan a "Aforo: N personas".
     */
    public function descripcionConAforoPersonas(): string
    {
        $d = (string) ($this->descripcion ?? '');

        return preg_replace('/^\s*Aforo:\s*(\d+)\s*$/mi', 'Aforo: $1 personas', $d);
    }

    /**
     * Emoji + banner HTML para semáforo en Google Calendar (título y descripción).
     *
     * @return array{emoji: string, label: string, banner_html: string}|null
     */
    public static function semaforoCalendarVisual(?string $semaforo): ?array
    {
        if (empty($semaforo)) {
            return null;
        }
        return match ($semaforo) {
            'rojo' => [
                'emoji' => '🔴',
                'label' => 'Rojo',
                'banner_html' => '<div style="background:#ffebee;color:#b71c1c;padding:10px 12px;border-radius:8px;border-left:4px solid #c62828;margin-bottom:10px;font-weight:700;">🔴 Semáforo: Rojo</div>',
            ],
            'amarillo' => [
                'emoji' => '🟡',
                'label' => 'Amarillo',
                'banner_html' => '<div style="background:#fff8e1;color:#f57f17;padding:10px 12px;border-radius:8px;border-left:4px solid #fbc02d;margin-bottom:10px;font-weight:700;">🟡 Semáforo: Amarillo</div>',
            ],
            'verde' => [
                'emoji' => '🟢',
                'label' => 'Verde',
                'banner_html' => '<div style="background:#e8f5e9;color:#1b5e20;padding:10px 12px;border-radius:8px;border-left:4px solid #2e7d32;margin-bottom:10px;font-weight:700;">🟢 Semáforo: Verde</div>',
            ],
            default => [
                'emoji' => '⚪',
                'label' => ucfirst((string) $semaforo),
                'banner_html' => '<div style="background:#f5f5f5;color:#424242;padding:10px 12px;border-radius:8px;margin-bottom:10px;font-weight:700;">Semáforo: ' . htmlspecialchars(ucfirst((string) $semaforo), ENT_QUOTES, 'UTF-8') . '</div>',
            ],
        };
    }

    /**
     * Generate a Google Calendar event link.
     * La hora guardada (en zona de la app) se convierte a UTC y se envía con sufijo Z
     * para que Google Calendar la muestre correctamente en la zona del usuario.
     */
    public function getGoogleCalendarUrl(): string
    {
        $baseUrl = 'https://www.google.com/calendar/render?action=TEMPLATE';

        $semaforoVis = self::semaforoCalendarVisual($this->semaforo);
        $title = $this->asunto;
        if ($this->tipo === 'gira') {
            $prefix = (strtolower((string) ($this->subtipo ?? 'gira')) === 'pre-gira') ? 'Pre-gira - ' : 'Gira - ';
            $title = $prefix . $title;
        }
        // Al inicio del título: semáforo (emoji) y municipio, juntos, luego el resto (Gira/Pre-gira + asunto)
        $leadParts = [];
        if ($semaforoVis !== null) {
            $leadParts[] = $semaforoVis['emoji'];
        }
        $muni = trim((string) ($this->municipio ?? ''));
        if ($muni !== '') {
            $leadParts[] = $muni;
        }
        if ($leadParts !== []) {
            $title = implode(' ', $leadParts) . ' ' . $title;
        }
        $text = urlencode($title);

        $detailsText = $this->descripcionConAforoPersonas();
        if ($this->tipo === 'gira') {
            $delegado = Delegado::whereHas('microrregion', function ($q) {
                $q->where('microrregion', $this->microrregion);
            })->first();
            $delegadoNombre = $delegado ? $delegado->nombre_completo : 'Sin asignar';
            $block = '';
            if ($semaforoVis !== null) {
                $block .= $semaforoVis['banner_html'] . "\n";
            }
            $block .= "Microrregión: " . ($this->microrregion ?? '') . "\n";
            $block .= "Municipio: " . ($this->municipio ?? '') . "\n";
            $block .= "Ubicación: " . ($this->lugar ?? '') . "\n";
            $block .= "Delegado a cargo: {$delegadoNombre}\n";
            if ($semaforoVis === null && !empty($this->semaforo)) {
                $block .= 'Semáforo: ' . ucfirst((string) $this->semaforo) . "\n";
            }
            if ($this->seguimiento) {
                $block .= "Seguimiento: {$this->seguimiento}\n";
            }
            $detailsText = $block . $detailsText;
        } elseif ($semaforoVis !== null) {
            $detailsText = $semaforoVis['banner_html'] . "\n\n" . $detailsText;
        }
        $details = urlencode($detailsText);
        $location = urlencode($this->lugar ?? '');

        $start = $this->fecha_inicio->format('Ymd');
        $endDate = $this->fecha_fin ?? $this->fecha_inicio;
        $end = $endDate->format('Ymd');

        if ($this->habilitar_hora && $this->hora) {
            $tz = config('app.timezone', 'UTC');
            $horaNormalized = trim($this->hora);
            if (substr_count($horaNormalized, ':') === 1) {
                $horaNormalized .= ':00';
            }
            $startDt = Carbon::parse($this->fecha_inicio->format('Y-m-d') . ' ' . $horaNormalized, $tz);
            if ($start === $end) {
                $endDt = $startDt->copy()->addHour();
            } else {
                $endDt = Carbon::parse($endDate->format('Y-m-d') . ' ' . $horaNormalized, $tz);
            }
            $startDt->setTimezone('UTC');
            $endDt->setTimezone('UTC');
            $dates = $startDt->format('Ymd\THis') . 'Z/' . $endDt->format('Ymd\THis') . 'Z';
            return "{$baseUrl}&text={$text}&details={$details}&location={$location}&dates={$dates}";
        }

        $dates = "{$start}/" . ($this->fecha_fin ? $this->fecha_fin->copy()->addDay()->format('Ymd') : $this->fecha_inicio->copy()->addDay()->format('Ymd'));
        return "{$baseUrl}&text={$text}&details={$details}&location={$location}&dates={$dates}";
    }
}
