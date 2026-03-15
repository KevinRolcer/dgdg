@extends('layouts.app')

@section('title', 'Seguimiento de Agenda')
@php
    $pageTitle = 'Seguimiento de Agenda';
    $hidePageHeader = true;
    $agendaSegFiltrosAbiertos = trim((string)($buscar ?? '')) !== '' || filled($fechaDia ?? null) || (int)($perPage ?? 15) !== 15;
@endphp

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/agenda.css') }}?v={{ @filemtime(public_path('assets/css/modules/agenda.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/agenda-seguimiento.css') }}?v={{ @filemtime(public_path('assets/css/modules/agenda-seguimiento.css')) ?: time() }}">
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-agenda-seg-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var wrap = this.closest('.agenda-seg-actions-wrap');
            if (!wrap) return;
            var isOpen = wrap.classList.toggle('is-open');
            this.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    });
});
</script>
@endpush

@section('content')
<section class="agenda-seg-page agenda-shell app-density-compact">
    <div class="agenda-shell-main">
        <header class="agenda-shell-head">
            <h1 class="agenda-shell-title">Seguimiento de Agenda</h1>
            <p class="agenda-shell-desc">Solo ves eventos <strong>asignados a ti</strong>. Pre-gira → pasar a Gira. Agenda (asunto) → actualizaciones. Filtra y abre el formulario desde la lista.</p>
        </header>

        @if (session('toast'))
            <div class="inline-alert inline-alert-success" role="status">{{ session('toast') }}</div>
        @endif

        <article class="agenda-card agenda-card-in-shell">
            <form method="get" action="{{ route('agenda.seguimiento.index') }}" class="agenda-filters-form agenda-filters-compact" id="agendaSegFiltersForm">
                <input type="hidden" name="clasificacion" id="agendaSegFilterClasificacion" value="{{ $clasificacion ?? 'todos' }}">
                <div class="agenda-filters-row agenda-filters-row-head">
                    <div class="agenda-filters-head-left">
                        <span class="agenda-filters-label">Clasificar</span>
                        <div class="tm-module-filters agenda-filters-chips" role="group" aria-label="Clasificación">
                            <button type="button" class="tm-module-chip {{ ($clasificacion ?? 'todos') === 'todos' ? 'is-active' : '' }}" data-agenda-seg-clasificacion="todos">Todos</button>
                            <button type="button" class="tm-module-chip {{ ($clasificacion ?? '') === 'agenda' ? 'is-active' : '' }}" data-agenda-seg-clasificacion="agenda">Agenda</button>
                            <button type="button" class="tm-module-chip {{ ($clasificacion ?? '') === 'pre_gira' ? 'is-active' : '' }}" data-agenda-seg-clasificacion="pre_gira">Pre-gira</button>
                            <button type="button" class="tm-module-chip {{ ($clasificacion ?? '') === 'gira' ? 'is-active' : '' }}" data-agenda-seg-clasificacion="gira">Gira</button>
                            <a href="{{ route('agenda.seguimiento.index') }}" class="tm-module-chip tm-btn-clear" title="Quitar filtros">
                                <i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i> Borrar
                            </a>
                        </div>
                    </div>
                    <div class="agenda-filters-head-right">
                        <button type="button" class="agenda-btn agenda-btn-secondary agenda-btn-mas-filtros" id="agendaSegBtnMasFiltros" aria-expanded="{{ $agendaSegFiltrosAbiertos ? 'true' : 'false' }}" aria-controls="agendaSegFiltersAdvanced">
                            <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                            <span id="agendaSegBtnMasFiltrosText">{{ $agendaSegFiltrosAbiertos ? 'Menos filtros' : 'Más filtros' }}</span>
                        </button>
                    </div>
                </div>
                <div class="agenda-filters-advanced {{ $agendaSegFiltrosAbiertos ? 'is-open' : '' }}" id="agendaSegFiltersAdvanced" @if(!$agendaSegFiltrosAbiertos) hidden @endif>
                    <div class="agenda-filters-row agenda-filters-inputs agenda-filters-inputs-inline">
                        <label class="agenda-filter-field agenda-filter-buscar">
                            <span>Buscar</span>
                            <input type="search" name="buscar" value="{{ $buscar }}" placeholder="Asunto o descripción…" class="form-control-agenda form-control-agenda-sm" autocomplete="off">
                        </label>
                        <label class="agenda-filter-field agenda-filter-fecha">
                            <span>Un día</span>
                            <input type="date" name="fecha" value="{{ $fechaDia }}" class="form-control-agenda form-control-agenda-sm">
                        </label>
                        <label class="agenda-filter-field agenda-filter-per-page">
                            <span>Por pág.</span>
                            <select name="per_page" class="form-control-agenda form-control-agenda-sm">
                                @foreach([10, 15, 25, 50] as $n)
                                    <option value="{{ $n }}" @selected(($perPage ?? 15) === $n)>{{ $n }}</option>
                                @endforeach
                            </select>
                        </label>
                        <a href="{{ route('agenda.seguimiento.index') }}" class="agenda-btn agenda-btn-clear-extra" title="Quitar búsqueda y fecha">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            </form>

            @if ($items->isEmpty())
                <p class="agenda-seg-empty">No hay eventos que coincidan con los filtros. Cuando un administrativo te asigne uno en Agenda Directiva, aparecerá aquí.</p>
            @else
                <div class="agenda-seg-table-wrap">
                    <table class="agenda-table agenda-seg-table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Asunto</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
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
                                        <div class="agenda-seg-actions-cell">
                                            @if ($item->tipo === 'gira' && strtolower((string)($item->subtipo ?? '')) === 'pre-gira')
                                                <button type="button" class="agenda-btn agenda-btn-secondary agenda-btn-sm" data-agenda-seg-row-toggle aria-expanded="false" aria-controls="agenda-seg-detail-{{ $item->id }}">
                                                    Pasar a Gira
                                                </button>
                                            @elseif (($item->tipo ?? 'asunto') === 'asunto')
                                                <button type="button" class="agenda-btn agenda-btn-secondary agenda-btn-sm" data-agenda-seg-row-toggle aria-expanded="false" aria-controls="agenda-seg-detail-{{ $item->id }}">
                                                    Registrar actualización
                                                </button>
                                            @else
                                                <span class="agenda-seg-text-muted">Gira activa</span>
                                            @endif
                                            <a href="{{ $item->getGoogleCalendarUrl() }}" target="_blank" rel="noopener" class="agenda-btn agenda-btn-table agenda-btn-icon" title="Agregar a calendario">
                                                <i class="fa-brands fa-google"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @if ($item->tipo === 'gira' && strtolower((string)($item->subtipo ?? '')) === 'pre-gira')
                                    <tr class="agenda-seg-detail-row" id="agenda-seg-detail-{{ $item->id }}" hidden>
                                        <td colspan="4">
                                            <div class="agenda-seg-actions agenda-seg-actions-wrap">
                                                <button type="button" class="agenda-seg-actions-toggle" aria-expanded="false" aria-controls="agenda-seg-gira-{{ $item->id }}" data-agenda-seg-toggle>
                                                    <span>Pasar a Gira</span>
                                                    <i class="fa-solid fa-chevron-down agenda-seg-actions-chevron" aria-hidden="true"></i>
                                                </button>
                                                <div class="agenda-seg-actions-body" id="agenda-seg-gira-{{ $item->id }}">
                                                    <p class="agenda-seg-hint">Nuevo registro como Gira; esta pre-gira queda concluida.</p>
                                                    <form method="POST" action="{{ route('agenda.seguimiento.pasar-gira', $item) }}" class="agenda-seg-form">
                                                        @csrf
                                                        <div class="agenda-seg-grid">
                                                            <label>Asunto <input type="text" name="asunto" value="{{ old('asunto', $item->asunto) }}" required class="form-control"></label>
                                                            <label>Fecha inicio <input type="date" name="fecha_inicio" value="{{ old('fecha_inicio', $item->fecha_inicio->format('Y-m-d')) }}" required class="form-control"></label>
                                                            <label>Fecha fin <input type="date" name="fecha_fin" value="{{ old('fecha_fin', optional($item->fecha_fin)->format('Y-m-d')) }}" class="form-control"></label>
                                                            <label>Microrregión <input type="text" name="microrregion" value="{{ old('microrregion', $item->microrregion) }}" class="form-control"></label>
                                                            <label>Municipio <input type="text" name="municipio" value="{{ old('municipio', $item->municipio) }}" class="form-control"></label>
                                                            <label>Lugar <input type="text" name="lugar" value="{{ old('lugar', $item->lugar) }}" class="form-control"></label>
                                                            <label>Semáforo
                                                                <select name="semaforo" class="form-control">
                                                                    <option value="">—</option>
                                                                    @foreach (['rojo','amarillo','verde'] as $s)
                                                                        <option value="{{ $s }}" @selected(old('semaforo', $item->semaforo) === $s)>{{ ucfirst($s) }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </label>
                                                            <label class="agenda-seg-full">Descripción <textarea name="descripcion" rows="2" class="form-control">{{ old('descripcion', $item->descripcion) }}</textarea></label>
                                                            <label class="agenda-seg-full">Seguimiento <textarea name="seguimiento" rows="2" class="form-control">{{ old('seguimiento', $item->seguimiento) }}</textarea></label>
                                                            <label><input type="checkbox" name="habilitar_hora" value="1" @checked(old('habilitar_hora', $item->habilitar_hora))> Con hora</label>
                                                            <label>Hora <input type="time" name="hora" value="{{ old('hora', $item->hora ? \Carbon\Carbon::parse($item->hora)->format('H:i') : '') }}" class="form-control"></label>
                                                            <label>Recordatorio (min) <input type="number" name="recordatorio_minutos" min="30" step="30" value="{{ old('recordatorio_minutos', $item->recordatorio_minutos ?? 30) }}" class="form-control"></label>
                                                        </div>
                                                        <button type="submit" class="agenda-btn agenda-btn-primary">Guardar como Gira</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @elseif (($item->tipo ?? 'asunto') === 'asunto')
                                    <tr class="agenda-seg-detail-row" id="agenda-seg-detail-{{ $item->id }}" hidden>
                                        <td colspan="4">
                                            <div class="agenda-seg-actions agenda-seg-actions-wrap">
                                                <button type="button" class="agenda-seg-actions-toggle" aria-expanded="false" aria-controls="agenda-seg-act-{{ $item->id }}" data-agenda-seg-toggle>
                                                    <span>Registrar actualización</span>
                                                    <i class="fa-solid fa-chevron-down agenda-seg-actions-chevron" aria-hidden="true"></i>
                                                </button>
                                                <div class="agenda-seg-actions-body" id="agenda-seg-act-{{ $item->id }}">
                                                    <p class="agenda-seg-hint">Nuevo registro como Actualización; el anterior queda concluido.</p>
                                                    <form method="POST" action="{{ route('agenda.seguimiento.actualizacion', $item) }}" class="agenda-seg-form">
                                                        @csrf
                                                        <div class="agenda-seg-grid">
                                                            <label>Asunto <input type="text" name="asunto" value="{{ old('asunto', $item->asunto) }}" required class="form-control"></label>
                                                            <label>Fecha inicio <input type="date" name="fecha_inicio" value="{{ old('fecha_inicio', $item->fecha_inicio->format('Y-m-d')) }}" required class="form-control"></label>
                                                            <label>Fecha fin <input type="date" name="fecha_fin" value="{{ old('fecha_fin', optional($item->fecha_fin)->format('Y-m-d')) }}" class="form-control"></label>
                                                            <label class="agenda-seg-full">Descripción <textarea name="descripcion" rows="2" class="form-control">{{ old('descripcion', $item->descripcion) }}</textarea></label>
                                                            <label class="agenda-seg-full">Seguimiento <textarea name="seguimiento" rows="2" class="form-control">{{ old('seguimiento', $item->seguimiento) }}</textarea></label>
                                                            <label><input type="checkbox" name="habilitar_hora" value="1" @checked(old('habilitar_hora', $item->habilitar_hora))> Con hora</label>
                                                            <label>Hora <input type="time" name="hora" value="{{ old('hora', $item->hora ? \Carbon\Carbon::parse($item->hora)->format('H:i') : '') }}" class="form-control"></label>
                                                            <label>Recordatorio (min) <input type="number" name="recordatorio_minutos" min="30" step="30" value="{{ old('recordatorio_minutos', $item->recordatorio_minutos ?? 30) }}" class="form-control"></label>
                                                        </div>
                                                        <button type="submit" class="agenda-btn agenda-btn-primary">Registrar actualización</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if (method_exists($items, 'links'))
                    <div class="agenda-pagination-wrap">
                        <p class="agenda-pagination-info">Mostrando {{ $items->firstItem() ?? 0 }}–{{ $items->lastItem() ?? 0 }} de {{ $items->total() }}</p>
                        <div class="agenda-pagination">{{ $items->withQueryString()->links() }}</div>
                    </div>
                @endif
            @endif
        </article>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('agendaSegFiltersForm');
    if (!form) return;
    var hidden = form.querySelector('#agendaSegFilterClasificacion');
    form.querySelectorAll('[data-agenda-seg-clasificacion]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            if (btn.tagName === 'A') return;
            e.preventDefault();
            var v = btn.getAttribute('data-agenda-seg-clasificacion');
            if (hidden) hidden.value = v;
            form.submit();
        });
    });
    var adv = document.getElementById('agendaSegFiltersAdvanced');
    var btnMas = document.getElementById('agendaSegBtnMasFiltros');
    var textMas = document.getElementById('agendaSegBtnMasFiltrosText');
    if (btnMas && adv) {
        btnMas.addEventListener('click', function () {
            var open = !adv.classList.contains('is-open');
            adv.classList.toggle('is-open', open);
            adv.hidden = !open;
            btnMas.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (textMas) textMas.textContent = open ? 'Menos filtros' : 'Más filtros';
        });
    }
    document.querySelectorAll('[data-agenda-seg-row-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = this.closest('tr');
            if (!row) return;
            var next = row.nextElementSibling;
            if (next && next.classList.contains('agenda-seg-detail-row')) {
                next.hidden = !next.hidden;
                next.classList.toggle('is-open', !next.hidden);
                btn.setAttribute('aria-expanded', next.hidden ? 'false' : 'true');
                var wrap = next.querySelector('.agenda-seg-actions-wrap');
                if (wrap) wrap.classList.toggle('is-open', !next.hidden);
            }
        });
    });
});
</script>
@endsection
