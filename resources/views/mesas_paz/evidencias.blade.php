@extends('layouts.app')

@section('title', 'Mesas de Paz - Evidencias')

@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<link href="{{ asset('assets/css/mesas_paz/mesas-paz-shell.css') }}?v={{ @filemtime(public_path('assets/css/mesas_paz/mesas-paz-shell.css')) ?: time() }}" rel="stylesheet" />
<link href="{{ asset('assets/css/mesas_paz/mesaPazSupervision.css') }}?v={{ @filemtime(public_path('assets/css/mesas_paz/mesaPazSupervision.css')) ?: time() }}" rel="stylesheet" />
<link href="{{ asset('assets/css/mesas_paz/mesaPaz.css') }}?v={{ @filemtime(public_path('assets/css/mesas_paz/mesaPaz.css')) ?: time() }}" rel="stylesheet" />
<link href="{{ asset('assets/css/theme-dark-mesas-paz.css') }}?v={{ @filemtime(public_path('assets/css/theme-dark-mesas-paz.css')) ?: time() }}" rel="stylesheet" />
<link href="{{ asset('assets/css/mesas_paz/evidencias.css') }}?v={{ @filemtime(public_path('assets/css/mesas_paz/evidencias.css')) ?: time() }}" rel="stylesheet" />
@endpush

@push('scripts')
<script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
@endpush

@section('content')
@php
    $hidePageHeader = true;
@endphp
<div class="mesas-paz-shell app-density-compact">
    <div class="mesas-paz-shell-main">
        <header class="mesas-paz-shell-head">
            <h1 class="mesas-paz-shell-title">Evidencias Mesas de Paz</h1>
            <p class="mesas-paz-shell-desc">Consulta de evidencias y asistencias por delegado. Filtros por fecha y análisis por microregión.</p>
        </header>

<div id="supervisionEvidenciasPage" data-url-ppt="{{ route('ppt.generar-presentacion') }}" data-fechas-datos="{{ json_encode($fechasConDatos ?? []) }}">
<div class="mesas-paz-panel panel panel-inverse">
    <div class="mesas-paz-panel-head panel-heading">
        <h4 class="panel-title">Filtros de búsqueda</h4>
    </div>
    <div class="mesas-paz-panel-body panel-body">
        <form id="supervisionFiltersForm" method="GET" action="{{ route('mesas-paz.evidencias') }}" class="row g-3 align-items-end">
            <div class="col-md-4 col-lg-3">
                <label for="fecha_lista" class="form-label">Fecha (lista de evidencias)</label>
                <input
                    type="text"
                    id="fecha_lista"
                    name="fecha_lista"
                    class="form-control @error('fecha_lista') is-invalid @enderror"
                    value="{{ old('fecha_lista', $fechaLista ?? \Carbon\Carbon::today()->toDateString()) }}"
                    placeholder="Seleccionar fecha"
                >
                @error('fecha_lista')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-7 d-flex gap-2 flex-wrap align-items-end">
                <a href="{{ route('mesas-paz.evidencias') }}" class="btn btn-default" id="btnLimpiarSupervision">Limpiar</a>
                <button
                    type="button"
                    class="btn btn-outline-primary btn-sm"
                    data-bs-toggle="collapse"
                    data-bs-target="#analisisFiltrosCollapse"
                    aria-expanded="false"
                    aria-controls="analisisFiltrosCollapse"
                >
                    Análisis / desglose <i class="fa fa-chevron-down ms-1" aria-hidden="true"></i>
                </button>
                <button
                    type="button"
                    class="btn btn-primary btn-sm"
                    id="btnAbrirRangoFechasPresentacion"
                >
                    Generar Presentación
                </button>
            </div>
        </form>

        <div class="collapse mt-3" id="analisisFiltrosCollapse">
            <div id="analisisDesgloseContent" class="border rounded p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h5 class="mb-0">Comparativo de presencia y efectividad</h5>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="collapse"
                        data-bs-target="#analisisFiltrosCollapse"
                        aria-expanded="true"
                        aria-controls="analisisFiltrosCollapse"
                    >
                        <i class="fa fa-chevron-up" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4 col-lg-3">
                        <label for="fecha_analisis" class="form-label">Fecha</label>
                        <input
                            type="text"
                            id="fecha_analisis"
                            name="fecha_analisis"
                            form="supervisionFiltersForm"
                            class="form-control"
                            value="{{ old('fecha_analisis', $fechaAnalisis ?? \Carbon\Carbon::today()->toDateString()) }}"
                            placeholder="Seleccionar fecha"
                        >
                    </div>
                    <div class="col-md-5 col-lg-4 ms-md-auto">
                        <label for="analisis_microrregion_id" class="form-label">Microregión</label>
                        <select id="analisis_microrregion_id" name="analisis_microrregion_id" form="supervisionFiltersForm" class="form-select">
                            <option value="">Todas</option>
                            @foreach(($microrregionesDisponibles ?? []) as $microrregion)
                                <option value="{{ $microrregion['id'] }}" @if((int)($analisisMicrorregionId ?? 0) === (int)$microrregion['id']) selected @endif>
                                    {{ $microrregion['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @php
                    $tasaMunicipalFecha = number_format((float)($analisisFecha['tasa_presencia_municipal'] ?? 0), 1);
                    $totalMunicipios = (int)($analisisFecha['total_municipios_registrados'] ?? 0);
                    $municipiosPresentes = (int)($analisisFecha['municipios_presentes'] ?? 0);
                    $municipiosNoPresentes = (int)($analisisFecha['municipios_no_presentes'] ?? 0);
                @endphp

                <div class="row g-2 mb-3 align-items-start">
                    <div class="col-12">
                        <div class="border rounded p-2 bg-white">
                            <h6 class="mb-2">Asistentes por microregión</h6>
                            @if(!empty($asistentesPorMicrorregion))
                                @php
                                    $microrregionFiltroLabel = '';
                                    if (!empty($analisisMicrorregionId) && !empty($microrregionesDisponibles)) {
                                        foreach ($microrregionesDisponibles as $micro) {
                                            if ((int)($micro['id'] ?? 0) === (int)$analisisMicrorregionId) {
                                                $microrregionFiltroLabel = (string)($micro['label'] ?? '');
                                                break;
                                            }
                                        }
                                    }
                                @endphp
                                <div class="mb-2">
                                    <div class="row g-2">
                                        <div class="col-12 col-md-6">
                                            <label for="asistentesMicroSearch" class="form-label small mb-1">Buscar microregión</label>
                                            <input
                                                type="text"
                                                id="asistentesMicroSearch"
                                                class="form-control form-control-sm"
                                                placeholder="Ej. 6 - TEZIUTLAN"
                                                value="{{ $microrregionFiltroLabel }}"
                                            >
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <label for="asistentesMicroLimit" class="form-label small mb-1">Mostrar</label>
                                            <select id="asistentesMicroLimit" class="form-select form-select-sm">
                                                <option value="1">1</option>
                                                <option value="5" selected>5 microrregiones</option>
                                                <option value="10">10</option>
                                                <option value="all">Todas</option>
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <label for="asistentesMicroSort" class="form-label small mb-1">Orden</label>
                                            <select id="asistentesMicroSort" class="form-select form-select-sm">
                                                <option value="asc" selected>1 - 9</option>
                                                <option value="desc">9 - 1</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="asistentes-micro-scroll">
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th class="text-nowrap">Microregión</th>
                                                    <th class="text-nowrap">Presentes</th>
                                                    <th class="text-nowrap" title="Presidente Municipal">Pres. Mpal.</th>
                                                    <th class="text-nowrap" title="Director de Seguridad Municipal">Dir. Seg. Mpal.</th>
                                                    <th class="text-nowrap" title="Asistencia del Delegado">Asist. Delegado</th>
                                                    <th class="text-nowrap">Ninguno</th>
                                                </tr>
                                            </thead>
                                            <tbody id="asistentesMicrorregionTableBody">
                                                @foreach($asistentesPorMicrorregion as $microLabel => $dato)
                                                    @php
                                                        $microIdRaw = 0;
                                                        if (preg_match('/^(\d+)/', (string) $microLabel, $matchesMicroId)) {
                                                            $microIdRaw = (int) $matchesMicroId[1];
                                                        }
                                                    @endphp
                                                    <tr data-micro-label="{{ mb_strtolower((string)$microLabel) }}" data-micro-id="{{ $microIdRaw }}">
                                                        <td>
                                                            <div class="fw-bold small">{{ $microLabel }}</div>
                                                            <div class="text-muted supervision-text-xs">Total municipios: {{ $dato['total_registrados'] ?? 0 }}</div>
                                                        </td>
                                                        <td>{{ $dato['presentes'] ?? 0 }}</td>
                                                        <td>
                                                            {{ $dato['conteo_por_tipo']['Presidente'] ?? 0 }}
                                                            <div class="text-muted supervision-text-xs">{{ number_format((float)($dato['promedio_por_tipo']['Presidente'] ?? 0), 1) }}%</div>
                                                        </td>
                                                        <td>
                                                            {{ $dato['conteo_por_tipo']['Director de seguridad'] ?? 0 }}
                                                            <div class="text-muted supervision-text-xs">{{ number_format((float)($dato['promedio_por_tipo']['Director de seguridad'] ?? 0), 1) }}%</div>
                                                        </td>
                                                        <td>
                                                            {{ $dato['delegado_asiste'] ?? 0 }}
                                                            <div class="text-muted supervision-text-xs">{{ number_format((float)($dato['delegado_asiste_porcentaje'] ?? 0), 1) }}%</div>
                                                        </td>
                                                        <td>
                                                            {{ $dato['conteo_por_tipo']['Ninguno'] ?? 0 }}
                                                            <div class="text-muted supervision-text-xs">{{ number_format((float)($dato['promedio_por_tipo']['Ninguno'] ?? 0), 1) }}%</div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <div id="asistentesMicroPagination" class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
                                        <small id="asistentesMicroPageInfo" class="text-muted"></small>
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Paginación microrregiones">
                                            <button type="button" id="asistentesMicroPrev" class="btn btn-outline-secondary">Anterior</button>
                                            <button type="button" id="asistentesMicroNext" class="btn btn-outline-secondary">Siguiente</button>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <small class="text-muted">Sin datos de asistentes para la combinación de filtros seleccionada.</small>
                            @endif

                        </div>
                    </div>

                    <div class="col-12">
                        <div class="border rounded p-2 bg-white">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <h6 class="mb-0">Estadísticas de municipios</h6>
                                <span class="badge bg-primary">Resumen</span>
                            </div>

                            <div class="row g-1 mb-1 text-center">
                                <div class="col-4">
                                    <div class="border rounded bg-component p-1 h-100">
                                        <div class="small text-muted">Total</div>
                                        <div class="h5 mb-0">{{ $totalMunicipios }}</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border rounded bg-component p-1 h-100">
                                        <div class="small text-muted">Presentes</div>
                                        <div class="h5 mb-0 text-success">{{ $municipiosPresentes }}</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border rounded bg-component p-1 h-100">
                                        <div class="small text-muted">No presentes</div>
                                        <div class="h5 mb-0 text-danger">{{ $municipiosNoPresentes }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="border rounded bg-component p-1">
                                <div class="d-flex justify-content-between align-items-center mb-0">
                                    <div class="small fw-bold">Efectividad de presencia</div>
                                    <div class="fw-bold">{{ $tasaMunicipalFecha }}%</div>
                                </div>
                                <div class="progress mt-1 supervision-progress-thin">
                                    <div class="progress-bar" role="progressbar" style="width: {{ max(0, min(100, (float)$tasaMunicipalFecha)) }}%;" aria-valuenow="{{ (float)$tasaMunicipalFecha }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3 d-none">
                    <div class="col-12">
                        <h6 class="mb-2">Representante por microregión</h6>
                    </div>
                    @if(!empty($listadoRepresentante))
                        <div class="accordion" id="accordionRepresentante">
                            @foreach($listadoRepresentante as $microLabel => $bloque)
                                <div class="accordion-item mb-2 border-0 bg-component rounded">
                                    <h2 class="accordion-header" id="headingRep{{ $loop->index }}">
                                        <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRep{{ $loop->index }}" aria-expanded="false" aria-controls="collapseRep{{ $loop->index }}">
                                            <span class="small fw-bold">{{ $microLabel }}</span>
                                        </button>
                                    </h2>
                                    <div id="collapseRep{{ $loop->index }}" class="accordion-collapse collapse" aria-labelledby="headingRep{{ $loop->index }}" data-bs-parent="#accordionRepresentante">
                                        <div class="accordion-body p-2">
                                            <div class="row g-2">
                                                <div class="col-12 col-md-6">
                                                    <small class="text-primary d-block mb-1">Sí asistieron</small>
                                                    @if(collect($bloque['municipios_si'] ?? [])->isNotEmpty())
                                                        <div class="d-flex flex-wrap gap-1">
                                                            @foreach($bloque['municipios_si'] as $municipioSi)
                                                                <span class="badge bg-primary">{{ $municipioSi }}</span>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <small class="text-muted">—</small>
                                                    @endif
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <small class="text-secondary d-block mb-1">No asistieron</small>
                                                    @if(collect($bloque['municipios_no'] ?? [])->isNotEmpty())
                                                        <div class="d-flex flex-wrap gap-1">
                                                            @foreach($bloque['municipios_no'] as $municipioNo)
                                                                <span class="badge bg-secondary">{{ $municipioNo }}</span>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <small class="text-muted">—</small>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="col-12"><small class="text-muted">Sin datos para mostrar en esta combinación de filtros.</small></div>
                    @endif
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="collapse"
                        data-bs-target="#analisisFiltrosCollapse"
                        aria-expanded="true"
                        aria-controls="analisisFiltrosCollapse"
                    >
                        <i class="fa fa-chevron-up" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mesas-paz-panel panel panel-inverse">
    <div class="mesas-paz-panel-head panel-heading">
        <h4 class="panel-title">Evidencias de asistencias (todos los delegados)</h4>
    </div>
    <div id="evidenciasPanelBody" class="mesas-paz-panel-body panel-body">
        @if(isset($evidencias) && collect($evidencias)->isNotEmpty())
            <div class="evidencias-list-header d-none d-md-flex px-3 pb-2 small border-bottom mb-2 text-muted fw-bold">
                <div class="col-md-2">Delegado</div>
                <div class="col-md-2 text-center">Microregión</div>
                <div class="col-md-3 text-center">Asistencias</div>
                <div class="col-md-3 text-center">Parte/Acuerdos</div>
                <div class="col-md-2 text-center">Evidencia</div>
            </div>

            <div class="d-flex flex-column gap-2">
                @foreach($evidencias as $item)
                    <div class="row align-items-center g-2 py-2 supervision-table-row mx-0">
                        <div class="col-12 col-md-2">
                            <small class="text-muted d-md-none d-block">Delegado</small>
                            <div class="delegado-nombre fw-bold" style="font-size: 0.9rem;" title="{{ $item['delegado'] }}">{{ $item['delegado'] }}</div>
                            <div class="text-muted" style="font-size: 0.75rem; word-break: break-all;">{{ $item['usuario'] }}</div>
                        </div>

                        <div class="col-12 col-md-2 text-center text-md-start">
                            <small class="text-muted d-md-none d-block">Microregión</small>
                            @if(!empty($item['microrregion_label']))
                                <span class="text-muted" style="font-size: 0.8rem;" title="{{ $item['microrregion_label'] }}">{{ $item['microrregion_label'] }}</span>
                            @elseif(!empty($item['microrregion_id']))
                                <span class="text-muted" style="font-size: 0.8rem;">{{ $item['microrregion_id'] }}</span>
                            @else
                                <span class="text-muted" style="font-size: 0.8rem;">N/D</span>
                            @endif
                        </div>

                        <div class="col-12 col-md-3 text-center text-md-start">
                            <small class="text-muted d-md-none d-block">Asistencias</small>
                            <div class="d-flex align-items-center justify-content-center justify-content-md-start gap-2">
                                <div class="flex-grow-1 text-md-start">
                                    <div class="text-success fw-bold" style="font-size: 0.8rem;">
                                        <i class="fa fa-check-circle me-1"></i> {{ count($item['municipios_con_asistencia']) }} Presentes
                                    </div>
                                    @if(count($item['municipios_no_presentes']) > 0)
                                        <div class="text-danger fw-bold" style="font-size: 0.8rem;">
                                            <i class="fa fa-times-circle me-1"></i> {{ count($item['municipios_no_presentes']) }} No presentes
                                        </div>
                                    @endif
                                </div>
                                <button 
                                    type="button" 
                                    class="btn btn-xs btn-outline-info rounded-circle" 
                                    style="width: 24px; height: 24px; padding: 0; display: flex; align-items: center; justify-content: center;"
                                    title="Ver municipios"
                                    onclick="verDetalleMunicipios('{{ addslashes($item['delegado']) }}', {{ json_encode($item['municipios_con_asistencia']) }}, {{ json_encode($item['municipios_no_presentes']) }})"
                                >
                                    <i class="fa fa-eye" style="font-size: 0.7rem;"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-12 col-md-3 text-center text-md-start">
                            <small class="text-muted d-md-none d-block">Parte/Acuerdos</small>
                            <div class="d-flex flex-column gap-1 align-items-center align-items-md-start">
                                @php $partesCollapseId = 'partes_' . $loop->index; @endphp
                                @if(collect($item['partes_observaciones'] ?? [])->isNotEmpty())
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary text-start py-0"
                                        style="font-size: 0.7rem;"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#{{ $partesCollapseId }}"
                                        aria-expanded="false"
                                        aria-controls="{{ $partesCollapseId }}"
                                    >
                                        <i class="fa fa-file-alt me-1"></i> Parte ({{ count($item['partes_observaciones']) }})
                                    </button>

                                    <div class="collapse mt-1" id="{{ $partesCollapseId }}">
                                        <div class="border rounded p-2 bg-white">
                                            <ul class="mb-0 ps-3 small text-muted">
                                                @foreach($item['partes_observaciones'] as $parte)
                                                    <li>{{ $parte }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                @endif

                                @php $acuerdosCollapseId = 'acuerdos_' . $loop->index; @endphp
                                @if(collect($item['acuerdos_observaciones'] ?? [])->isNotEmpty())
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary text-start py-0"
                                        style="font-size: 0.7rem;"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#{{ $acuerdosCollapseId }}"
                                        aria-expanded="false"
                                        aria-controls="{{ $acuerdosCollapseId }}"
                                    >
                                        <i class="fa fa-handshake me-1"></i> Acuerdos ({{ count($item['acuerdos_observaciones']) }})
                                    </button>

                                    <div class="collapse mt-1" id="{{ $acuerdosCollapseId }}">
                                        <div class="border rounded p-2 bg-white">
                                            <ul class="mb-0 ps-3 small text-muted">
                                                @foreach($item['acuerdos_observaciones'] as $acuerdo)
                                                    <li>{{ $acuerdo }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                @endif

                                @if(collect($item['partes_observaciones'] ?? [])->isEmpty())
                                    <div class="badge-supervision-soft" style="font-size: 0.75rem;">Parte: Aún no registrada.</div>
                                @endif

                                @if(collect($item['acuerdos_observaciones'] ?? [])->isEmpty())
                                    <div class="badge-supervision-soft mt-1" style="font-size: 0.75rem;">Acuerdos: Aún no registrados.</div>
                                @endif
                            </div>
                        </div>

                        <div class="col-12 col-md-2 text-center">
                            <small class="text-muted d-md-none d-block text-center">Evidencia</small>
                            @php
                                $evidenciasUrls = collect($item['evidencia_urls'] ?? [])->filter()->values();
                            @endphp
                            @if(!empty($item['tiene_evidencia']) && $evidenciasUrls->isNotEmpty())
                                <div class="evidencia-thumbs d-flex flex-wrap gap-1">
                                    @foreach($evidenciasUrls as $indexEvidencia => $evidenciaUrl)
                                        <img
                                            src="{{ $evidenciaUrl }}"
                                            alt="Evidencia {{ $indexEvidencia + 1 }}"
                                            class="rounded border"
                                            style="width: 45px; height: 45px; object-fit: cover; cursor: pointer;"
                                            onclick="mostrarVistaPreviaEvidencia('{{ $evidenciaUrl }}')"
                                        >
                                    @endforeach
                                </div>
                            @else
                                <span class="badge-supervision-soft small">Sin evidencia</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if(isset($registrosPaginator) && $registrosPaginator instanceof \Illuminate\Pagination\LengthAwarePaginator)
                <div class="mt-4 d-flex justify-content-center" id="evidenciasPagination">
                    {{ $registrosPaginator->withQueryString()->links() }}
                </div>
            @endif
        @push('scripts')
<script src="{{ asset('assets/js/mesas_paz/evidencias.js') }}?v={{ @filemtime(public_path('assets/js/mesas_paz/evidencias.js')) ?: time() }}"></script>
        @endpush
        @else
            <div class="alert alert-secondary mb-0">
                No se encontraron evidencias con los filtros seleccionados.
            </div>
        @endif
    </div>
</div>

<div class="modal fade" id="evidenciaPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Vista previa de evidencia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body text-center">
                <img id="evidenciaPreviewModalImg" src="" alt="Vista previa" class="img-fluid rounded border">
            </div>
        </div>
    </div>
</div>

<!-- Modal para Detalle de Municipios -->
<div class="modal fade" id="modalDetalleMunicipios" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Municipios - <span id="detalleMunicipiosDelegado"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <h6 class="text-success border-bottom pb-1"><i class="fa fa-check-circle me-1"></i> Presentes</h6>
                    <div id="listaMunicipiosPresentes" class="d-flex flex-wrap gap-2 mt-2"></div>
                </div>
                <div>
                    <h6 class="text-danger border-bottom pb-1"><i class="fa fa-times-circle me-1"></i> No presentes</h6>
                    <div id="listaMunicipiosNoPresentes" class="d-flex flex-wrap gap-2 mt-2"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

<div class="modal fade" id="rangoFechasPresentacionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Selecciona la semana a evaluar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="formRangoFechasPresentacion" class="d-flex flex-column align-items-center">
                    <div class="calendar-wrapper presentation-calendar-wrap w-100 d-flex justify-content-center">
                        <input type="text" id="fechaRangoPresentacion" name="fecha_rango" class="d-none" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarRangoFechasPresentacion">Generar</button>
            </div>
        </div>
    </div>
</div>



@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/es.js"></script>
<script src="{{ asset('assets/js/mesas_paz/mesaPazSupervicion.js') }}?v={{ @filemtime(public_path('assets/js/mesas_paz/mesaPazSupervicion.js')) ?: time() }}"></script>
@endpush
