@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/agenda.css') }}?v={{ @filemtime(public_path('assets/css/modules/agenda.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/agenda-seguimiento.css') }}?v={{ @filemtime(public_path('assets/css/modules/agenda-seguimiento.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/agenda-preview.css') }}?v={{ @filemtime(public_path('assets/css/modules/agenda-preview.css')) ?: time() }}">
@endpush

@php
    $pageTitle = 'Actividad: ' . \Illuminate\Support\Str::limit($agenda->asunto, 48);
    $hidePageHeader = true;
    $backUrl = $returnUrl ?? route('agenda.calendar');
@endphp

@section('content')
<section class="agenda-preview-page agenda-shell app-density-compact">
    <div class="agenda-shell-main">
        <header class="agenda-preview-head">
            <a href="{{ $backUrl }}" class="agenda-btn agenda-btn-secondary agenda-preview-back">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Volver
            </a>
            <div class="agenda-preview-head-text">
                <h1 class="agenda-shell-title agenda-preview-title">Vista previa</h1>
                <p class="agenda-shell-desc">Detalle de la actividad (solo lectura).</p>
            </div>
        </header>

        <article class="agenda-preview-card">
            <div class="agenda-preview-badges">
                @if ($agenda->es_actualizacion)
                    <span class="agenda-seg-badge agenda-seg-badge--act">Actualización</span>
                @endif
                @if ($agenda->tipo === 'gira')
                    <span class="agenda-seg-badge agenda-seg-badge--gira">{{ strtolower((string) ($agenda->subtipo ?? '')) === 'pre-gira' ? 'Pre-gira' : 'Gira' }}</span>
                @else
                    <span class="agenda-seg-badge agenda-seg-badge--asunto">Agenda</span>
                @endif
                @if ($agenda->repite)
                    <span class="agenda-pill-recurrente"><i class="fa-solid fa-repeat"></i> Recurrente</span>
                @endif
            </div>

            <h2 class="agenda-preview-subject">{{ $agenda->asunto }}</h2>

            @if ($agenda->semaforo)
                @php
                    $semaClass = match ($agenda->semaforo) {
                        'rojo' => 'dot-rojo',
                        'amarillo' => 'dot-amarillo',
                        'verde' => 'dot-verde',
                        default => null,
                    };
                @endphp
                <p class="agenda-preview-line">
                    <span class="status-dot {{ $semaClass }}" aria-hidden="true"></span>
                    Semáforo: <strong>{{ ucfirst($agenda->semaforo) }}</strong>
                </p>
            @endif

            <dl class="agenda-preview-dl">
                <div>
                    <dt>Fecha inicio</dt>
                    <dd>{{ $agenda->fecha_inicio->format('d/m/Y') }}</dd>
                </div>
                @if ($agenda->fecha_fin)
                    <div>
                        <dt>Fecha fin</dt>
                        <dd>{{ $agenda->fecha_fin->format('d/m/Y') }}</dd>
                    </div>
                @endif
                @if ($agenda->habilitar_hora && $agenda->hora)
                    <div>
                        <dt>Hora</dt>
                        <dd>{{ \Carbon\Carbon::parse($agenda->hora)->format('H:i') }}</dd>
                    </div>
                @endif
                @if (filled($agenda->microrregion))
                    <div>
                        <dt>Microrregión</dt>
                        <dd>{{ $agenda->microrregion }}</dd>
                    </div>
                @endif
                @if (filled($agenda->municipio))
                    <div>
                        <dt>Municipio</dt>
                        <dd>{{ $agenda->municipio }}</dd>
                    </div>
                @endif
                @if (filled($agenda->lugar))
                    <div class="agenda-preview-dl-full">
                        <dt><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Ubicación</dt>
                        <dd>{{ $agenda->lugar }}</dd>
                    </div>
                @endif
                <div>
                    <dt>Recordatorio</dt>
                    <dd>{{ $agenda->reminder_label }}</dd>
                </div>
            </dl>

            @if (filled($agenda->seguimiento))
                <div class="agenda-preview-block">
                    <h3 class="agenda-preview-h3">Seguimiento</h3>
                    <p class="agenda-preview-seguimiento">{{ $agenda->seguimiento }}</p>
                </div>
            @endif

            @if (filled($agenda->descripcion))
                <div class="agenda-preview-block">
                    <h3 class="agenda-preview-h3">Descripción</h3>
                    <div class="agenda-preview-desc">{{ $agenda->descripcionConAforoPersonas() }}</div>
                </div>
            @endif

            <div class="agenda-preview-block">
                <h3 class="agenda-preview-h3">Asignados</h3>
                @if ($agenda->usuariosAsignados->isEmpty())
                    <p class="agenda-seg-text-muted">Sin asignar</p>
                @else
                    <ul class="agenda-preview-users">
                        @foreach ($agenda->usuariosAsignados as $u)
                            <li>{{ $u->name }} <span class="agenda-preview-email">&lt;{{ $u->email }}&gt;</span></li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @if ($agenda->creador)
                <p class="agenda-preview-meta">Creado por: {{ $agenda->creador->name }}</p>
            @endif

            <div class="agenda-preview-actions">
                <a href="{{ $agenda->getGoogleCalendarUrl() }}" target="_blank" rel="noopener" class="agenda-btn agenda-btn-secondary">
                    <i class="fa-brands fa-google" aria-hidden="true"></i> Google Calendar
                </a>
                @if (!empty($puedeEditarAgenda))
                    <a href="{{ route('agenda.index', ['buscar' => $agenda->asunto]) }}" class="agenda-btn agenda-btn-primary">
                        <i class="fa-solid fa-pen" aria-hidden="true"></i> Editar desde el listado
                    </a>
                @endif
            </div>
        </article>
    </div>
</section>
@endsection
