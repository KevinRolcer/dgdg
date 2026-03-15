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
                                        <tr class="agenda-seg-row">
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
