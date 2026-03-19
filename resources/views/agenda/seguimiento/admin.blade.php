@extends('layouts.app')

@section('title', 'Asignaciones de agenda')
@php
    $pageTitle = 'Asignaciones de agenda';
    $hidePageHeader = true;
@endphp

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/agenda.css') }}?v={{ @filemtime(public_path('assets/css/modules/agenda.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/agenda-seguimiento.css') }}?v={{ @filemtime(public_path('assets/css/modules/agenda-seguimiento.css')) ?: time() }}">
@endpush

@section('content')
<section class="agenda-seg-page agenda-shell app-density-compact">
    <div class="agenda-shell-main">
        <header class="agenda-shell-head">
            <h1 class="agenda-shell-title">Asignaciones de agenda</h1>
            <p class="agenda-shell-desc">Vista por usuario: qué actividades tiene asignadas cada quien y cómo les dan seguimiento.</p>
        </header>

        @if (session('toast'))
            <div class="inline-alert inline-alert-success" role="status">{{ session('toast') }}</div>
        @endif

        <article class="agenda-card agenda-card-in-shell">
            @if (empty($porUsuario))
                <p class="agenda-seg-empty">No hay usuarios con actividades asignadas en seguimiento.</p>
            @else
                <div class="agenda-seg-week-planner-card" id="agendaSegWeekPlannerCard">
                    <div class="agenda-seg-week-toolbar">
                        <button type="button" class="agenda-seg-week-nav" id="agendaSegWeekPrev" aria-label="Semana anterior" title="Semana anterior">
                            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                        </button>
                        <div class="agenda-seg-week-title" id="agendaSegWeekTitle">—</div>
                        <button type="button" class="agenda-seg-week-nav" id="agendaSegWeekNext" aria-label="Semana siguiente" title="Semana siguiente">
                            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="agenda-seg-week-days" id="agendaSegWeekDays" role="grid" aria-label="Calendario semanal de asignaciones de agenda"></div>
                    <div id="agendaSegWeekPopover" class="agenda-seg-week-popover" aria-hidden="true"></div>
                </div>

                <div class="agenda-seg-users-mini" id="agendaSegUsersMini" aria-label="Usuarios asignados (minimal)">
                    @foreach ($porUsuario as $userId => $bloque)
                        @php
                            $user = $bloque['user'];
                            $agendas = $bloque['agendas'];
                            $dates = $agendas
                                ->pluck('fecha_inicio')
                                ->map(fn($d) => $d ? $d->format('Y-m-d') : null)
                                ->filter()
                                ->unique()
                                ->sort()
                                ->values();
                        @endphp
                        <div class="agenda-seg-users-mini-user">
                            <div class="agenda-seg-users-mini-name">{{ $user->name }}</div>
                            <div class="agenda-seg-users-mini-days">
                                @foreach ($dates as $iso)
                                    <button type="button" class="agenda-seg-user-day" data-date="{{ $iso }}" title="Ir a {{ $iso }}">
                                        {{ \Carbon\Carbon::parse($iso)->format('d') }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                @foreach ($porUsuario as $userId => $bloque)
                    @php
                        $user = $bloque['user'];
                        $agendas = $bloque['agendas'];
                    @endphp
                    <div class="agenda-seg-admin-user">
                        <h2 class="agenda-seg-admin-user-title">
                            <span class="agenda-seg-admin-user-name">{{ $user->name }}</span>
                            <span class="agenda-seg-admin-user-count">({{ $agendas->count() }} {{ $agendas->count() === 1 ? 'actividad' : 'actividades' }})</span>
                        </h2>
                        <div class="agenda-seg-table-wrap">
                            <table class="agenda-table agenda-seg-table">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Asunto</th>
                                        <th>Fecha</th>
                                        <th>Seguimiento</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($agendas as $item)
                                        <tr class="agenda-seg-row"
                                            data-agenda-seg-date="{{ $item->fecha_inicio->format('Y-m-d') }}"
                                            data-agenda-seg-kind="{{ $item->es_actualizacion ? 'act' : ($item->tipo === 'gira' ? (strtolower((string)($item->subtipo ?? '')) === 'pre-gira' ? 'pre-gira' : 'gira') : 'asunto') }}">
                                            <td>
                                                @if ($item->es_actualizacion)
                                                    <span class="agenda-seg-badge agenda-seg-badge--act">Actualización</span>
                                                @endif
                                                @if ($item->tipo === 'gira')
                                                    <span class="agenda-seg-badge agenda-seg-badge--gira">{{ strtolower((string)($item->subtipo ?? '')) === 'pre-gira' ? 'Pre-gira' : 'Gira' }}</span>
                                                @else
                                                    <span class="agenda-seg-badge agenda-seg-badge--asunto">Agenda</span>
                                                @endif
                                            </td>
                                            <td>
                                                <strong>{{ $item->asunto }}</strong>
                                                @if ($item->descripcion)
                                                    <small>{{ Str::limit($item->descripcionConAforoPersonas(), 56) }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                {{ $item->fecha_inicio->format('d/m/Y') }}
                                                @if ($item->habilitar_hora && $item->hora)
                                                    <small>{{ \Carbon\Carbon::parse($item->hora)->format('H:i') }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                @if (filled($item->seguimiento))
                                                    <span class="agenda-seg-seguimiento-text">{{ $item->seguimiento }}</span>
                                                @else
                                                    <span class="agenda-seg-text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            @endif
        </article>
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/modules/agenda-seguimiento-admin.js') }}?v={{ @filemtime(public_path('assets/js/modules/agenda-seguimiento-admin.js')) ?: time() }}"></script>
@endpush
