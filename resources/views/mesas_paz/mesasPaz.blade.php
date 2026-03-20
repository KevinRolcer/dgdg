@extends('layouts.app')

@section('title', 'Mesas de Paz y Seguridad')

@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link href="{{ asset('assets/css/mesas_paz/mesas-paz-shell.css') }}?v={{ @filemtime(public_path('assets/css/mesas_paz/mesas-paz-shell.css')) ?: time() }}" rel="stylesheet" />
<link href="{{ asset('assets/css/mesas_paz/mesaPaz.css') }}?v={{ @filemtime(public_path('assets/css/mesas_paz/mesaPaz.css')) ?: time() }}" rel="stylesheet" />
<link href="{{ asset('assets/css/theme-dark-mesas-paz.css') }}?v={{ @filemtime(public_path('assets/css/theme-dark-mesas-paz.css')) ?: time() }}" rel="stylesheet" />
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
<script src="{{ asset('assets/js/mesas_paz/mesasPaz.js') }}?v={{ @filemtime(public_path('assets/js/mesas_paz/mesasPaz.js')) ?: time() }}"></script>
@endpush

@section('content')
@php
    $hidePageHeader = true;
@endphp
<div class="mesas-paz-shell app-density-compact">
    <div class="mesas-paz-shell-main">
        <header class="mesas-paz-shell-head">
            <h1 class="mesas-paz-shell-title">Mesas de Paz y Seguridad</h1>
            <p class="mesas-paz-shell-desc">Registro de asistencias por microregión, evidencias y acuerdos de sesión.</p>
        </header>

<div
    id="mesasPazApp"
    class="row"
    data-guardar-municipio-url="{{ route('mesas-paz.guardar-municipio') }}"
    data-guardar-acuerdo-hoy-url="{{ route('mesas-paz.guardar-acuerdo-hoy') }}"
    data-guardar-evidencia-hoy-url="{{ route('mesas-paz.guardar-evidencia-hoy') }}"
    data-eliminar-evidencia-hoy-url="{{ route('mesas-paz.eliminar-evidencia-hoy') }}"
    data-historial-detalle-url="{{ route('mesas-paz.historial-detalle') }}"
    data-importar-excel-url="{{ url('/mesas-paz/importar-excel') }}"
    data-vaciar-microrregion-url="{{ url('/mesas-paz/vaciar-microrregion') }}"
    data-evidencias-hoy='@json($evidenciasActuales ?? [])'
    data-max-evidencias-hoy="{{ $maxEvidenciasHoy ?? 3 }}"
    data-csrf-token="{{ csrf_token() }}"
    data-selected-microrregion-id="{{ (int) ($microrregionSeleccionadaId ?? 0) }}"
    data-fecha-hoy-iso="{{ $fechaHoyIso }}"
>
    @if (!empty($esAnalistaEnlace) && isset($microrregionesAsignadas) && $microrregionesAsignadas->count() > 1)
        <div class="col-12 mb-2">
            <div class="mesa-micro-pagination-wrap">
                <div class="mesa-micro-pagination-title">Microrregiones</div>
                <div class="mesa-micro-pagination" role="tablist" aria-label="Selector de microregión">
                    @foreach ($microrregionesAsignadas as $micro)
                        <a
                            href="{{ request()->fullUrlWithQuery(['microrregion_id' => $micro->id]) }}"
                            class="mesa-micro-page @if((int) ($microrregionSeleccionadaId ?? 0) === (int) $micro->id) is-active @endif"
                            role="tab"
                            aria-selected="@if((int) ($microrregionSeleccionadaId ?? 0) === (int) $micro->id) true @else false @endif"
                        >
                            MR {{ str_pad((string) $micro->microrregion, 2, '0', STR_PAD_LEFT) }} — {{ $micro->cabecera }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="col-lg-7 order-1 order-lg-1">
        <div class="mesas-paz-panel panel panel-inverse inline-asistencia">
            <div class="mesas-paz-panel-head panel-heading">
                @php
                    $fechaSolo = \Carbon\Carbon::parse($fechaHoyIso ?? now()->toDateString())
                        ->locale('es')
                        ->translatedFormat('d \\d\\e F \\d\\e Y');
                @endphp
                <div class="mesa-heading-row">
                    <h4 class="panel-title">
                        Microregión asignada
                        @if (!empty($microrregionNumero))
                            <span class="ms-1">#{{ $microrregionNumero }}</span>
                        @endif
                        <span class="ms-1">{{ $microrregionNombre }}</span>
                    </h4>
                    @if (!empty($esAnalistaEnlace))
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <div class="mesa-date-btn-wrapper" id="fechaSelectorWrapper" title="Cambiar fecha de consulta">
                                <span id="fechaDisplay">{{ \Carbon\Carbon::parse($fechaHoyIso)->format('d/m/Y') }}</span>
                                <i class="ms-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M11 6.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1z"/>
                                        <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
                                    </svg>
                                </i>
                                <input type="date" id="fechaSelectorMesas" class="mesa-date-input-hidden" value="{{ $fechaHoyIso }}" max="{{ \Carbon\Carbon::today()->toDateString() }}">
                            </div>
                            <div class="d-inline-flex align-items-center gap-1 flex-shrink-0 mesa-excel-actions">
                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#importarExcelModal">
                                    Cargar Excel
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger mesa-btn-vaciar-registros"
                                    id="btnVaciarMicrorregion"
                                    title="Vaciar registros de esta fecha en tu microrregión"
                                    aria-label="Vaciar registros"
                                >
                                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    @else
                        <span class="mesa-heading-date">{{ ucfirst($fechaSolo) }}</span>
                    @endif
                </div>
            </div>
            <div class="mesas-paz-panel-body panel-body">
                <div class="border rounded p-3 mb-3">
                    <label for="modalidad_global" class="form-label fw-bold mb-1">Modalidad de la sesión (obligatoria antes de registrar)</label>
                    <select id="modalidad_global" class="form-select">
                        <option value="">Seleccionar</option>
                        <option value="Virtual" @if(($modalidadActual ?? '') === 'Virtual') selected @endif>Virtual</option>
                        <option value="Presencial" @if(($modalidadActual ?? '') === 'Presencial') selected @endif>Presencial</option>
                        {{-- <option value="Sin reporte de Delegado" @if(($modalidadActual ?? '') === 'Sin reporte de Delegado') selected @endif>Sin reporte de Delegado</option> --}}
                        {{-- <option value="Sin información de enlace" @if(($modalidadActual ?? '') === 'Sin información de enlace') selected @endif>Sin información de enlace</option> --}}
                        <option value="Suspención de mesa de Seguridad" @if(($modalidadActual ?? '') === 'Suspención de mesa de Seguridad') selected @endif>Suspención de mesa de Seguridad</option>
                    </select>

                    <div id="delegadoAsistenciaGroup" class="mt-3">
                        <label for="delegado_asistio_global" class="form-label fw-bold mb-1">¿Asistió el delegado?</label>
                        <select id="delegado_asistio_global" class="form-select">
                            <option value="">Seleccionar</option>
                            <option value="Si" @if(($delegadoAsistioActual ?? '') === 'Si') selected @endif>Sí</option>
                            <option value="No" @if(($delegadoAsistioActual ?? '') === 'No') selected @endif>No</option>
                        </select>
                    </div>

                    <div id="capturaPrereqInfo" class="alert alert-info mt-3 mb-0 @if(!empty($modalidadActual) && !empty($delegadoAsistioActual)) d-none @endif">
                        Para capturar municipios primero debes seleccionar la modalidad de la sesión y confirmar si el delegado asistió.
                    </div>
                    <small id="modalidadInfo" class="text-muted d-none">Puedes cambiar modalidad y asistencia del delegado para nuevas reuniones del mismo día.</small>
                </div>

                <div id="specialModeMunicipiosMsg" class="alert alert-warning mb-3 d-none">
                    Anexa parte/observaciones y presiona Guardar Parte y Acuerdos para aplicar los cambios a todas las respuestas.
                </div>

                @if ($municipios->isEmpty())
                    <div class="alert alert-warning mb-3" id="municipiosCapturaSection">No hay municipios asignados.</div>
                @else
                    <div id="municipiosCapturaSection">
                    <div class="mb-3" id="municipiosAccordion">
                        @foreach ($municipios as $municipio)
                            @php $registroMunicipio = $registrosHoyByMunicipio->get($municipio->id); @endphp
                            <div
                                id="municipio_card_{{ $municipio->id }}"
                                class="card mb-3 municipio-card municipio-card-separador"
                                data-registrado="{{ $registroMunicipio ? 1 : 0 }}"
                                data-municipio-id="{{ $municipio->id }}"
                                data-microrregion-id="{{ $municipio->microrregion_id }}"
                            >
                                <div class="card-header py-2" id="municipioHeading{{ $municipio->id }}">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <span class="fw-bold">{{ $municipio->municipio }}</span>
                                        </div>
                                        <button
                                            class="btn btn-link btn-sm text-decoration-none p-0 btn-ver-mas-municipio"
                                            type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#municipioDetalles{{ $municipio->id }}"
                                            aria-expanded="false"
                                            aria-controls="municipioDetalles{{ $municipio->id }}"
                                        >
                                            Ver detalle
                                        </button>
                                    </div>
                                </div>

                                <div class="card-body pt-2">
                                        <div id="municipioDetalles{{ $municipio->id }}" class="collapse mb-2">
                                            <div class="row">
                                                <div class="col-md-6 mb-2">
                                                    <small class="text-muted d-block">Clave INEGI</small>
                                                    <strong>{{ $municipio->cve_inegi ?: 'N/D' }}</strong>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <small class="text-muted d-block">Región</small>
                                                    <strong>{{ $municipio->region ?: 'N/D' }}</strong>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-2">
                                            <label class="form-label fw-bold">Asiste:</label>
                                            <div class="d-flex flex-wrap gap-2 mb-2 opciones-asistencia" data-municipio-id="{{ $municipio->id }}">
                                                @php
                                                    $presidenteValue = optional($registroMunicipio)->presidente;
                                                    $isNinguno = in_array($presidenteValue, ['No', 'Ninguno']);
                                                    $isRepresentante = $presidenteValue === 'Representante';
                                                    $isAmbos = $presidenteValue === 'Ambos';
                                                    $isPresidente = in_array($presidenteValue, ['Si', 'Presidente']);
                                                @endphp
                                                <input type="radio" class="btn-check presidente-option-input" name="presidente_option_{{ $municipio->id }}" id="presidente_opt_si_{{ $municipio->id }}" value="Si" data-municipio-id="{{ $municipio->id }}" @if($isPresidente) checked @endif @if($registroMunicipio || empty($modalidadActual) || empty($delegadoAsistioActual)) disabled @endif>
                                                <label class="btn btn-outline-secondary btn-sm" for="presidente_opt_si_{{ $municipio->id }}">Presidente Municipal</label>

                                                <input type="radio" class="btn-check presidente-option-input" name="presidente_option_{{ $municipio->id }}" id="presidente_opt_rep_{{ $municipio->id }}" value="Representante" data-municipio-id="{{ $municipio->id }}" @if($isRepresentante) checked @endif @if($registroMunicipio || empty($modalidadActual) || empty($delegadoAsistioActual)) disabled @endif>
                                                <label class="btn btn-outline-secondary btn-sm" for="presidente_opt_rep_{{ $municipio->id }}">Representante</label>

                                                <input type="radio" class="btn-check presidente-option-input" name="presidente_option_{{ $municipio->id }}" id="presidente_opt_ambos_{{ $municipio->id }}" value="Ambos" data-municipio-id="{{ $municipio->id }}" @if($isAmbos) checked @endif @if($registroMunicipio || empty($modalidadActual) || empty($delegadoAsistioActual)) disabled @endif>
                                                <label class="btn btn-outline-secondary btn-sm" for="presidente_opt_ambos_{{ $municipio->id }}">Ambos (Presidente y Representante)</label>

                                                <input type="radio" class="btn-check presidente-option-input" name="presidente_option_{{ $municipio->id }}" id="presidente_opt_ninguno_{{ $municipio->id }}" value="Ninguno" data-municipio-id="{{ $municipio->id }}" @if($isNinguno) checked @endif @if($registroMunicipio || empty($modalidadActual) || empty($delegadoAsistioActual)) disabled @endif>
                                                <label class="btn btn-outline-secondary btn-sm btn-ninguno-option" for="presidente_opt_ninguno_{{ $municipio->id }}">Ninguno</label>
                                            </div>
                                            <select
                                                id="presidente_{{ $municipio->id }}"
                                                class="form-select presidente-select d-none"
                                                data-municipio-id="{{ $municipio->id }}"
                                                @if($registroMunicipio || empty($modalidadActual) || empty($delegadoAsistioActual)) disabled @endif
                                            >
                                                <option value="">Seleccionar</option>
                                                <option value="Si" @if(optional($registroMunicipio)->presidente === 'Si') selected @endif>Sí</option>
                                                <option value="Representante" @if(optional($registroMunicipio)->presidente === 'Representante') selected @endif>Representante</option>
                                                <option value="Ambos" @if(optional($registroMunicipio)->presidente === 'Ambos') selected @endif>Ambos (Presidente y Representante)</option>
                                                {{-- Municipio no presente" == "No" --}}
                                                <option value="Ninguno" @if(in_array(optional($registroMunicipio)->presidente, ['No', 'Ninguno'])) selected @endif>Municipio no presente</option>
                                                <option value="No" hidden @if(optional($registroMunicipio)->presidente === 'No') selected @endif>No</option>
                                            </select>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center gap-2">
                                            <small id="status_municipio_{{ $municipio->id }}" class="status-municipio @if($registroMunicipio) text-success @else text-muted @endif" data-registrado="{{ $registroMunicipio ? 1 : 0 }}">
                                                @if($registroMunicipio) Registrado hoy @else Pendiente @endif
                                            </small>
                                            <button type="button" class="btn btn-sm btn-primary btn-guardar-municipio" data-municipio-id="{{ $municipio->id }}" @if($registroMunicipio || empty($modalidadActual) || empty($delegadoAsistioActual)) disabled @endif>
                                                @if($registroMunicipio) Registrado @else Guardar @endif
                                            </button>
                                        </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div id="sinMunicipiosPendientes" class="alert alert-success mb-3 d-none">Todos los municipios ya fueron registrados hoy.</div>
                    </div>
                @endif

                <div class="border rounded p-3" id="municipiosContestadosSection">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <h6 class="mb-0">Municipios contestados hoy</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary @if(!(isset($registrosHoy) && $registrosHoy->isNotEmpty())) d-none @endif" id="btnToggleContestadosDetalle">
                            Ver detalle
                        </button>
                    </div>
                    <ul id="listaMunicipiosContestados" class="list-group">
                        @if(isset($registrosHoy) && $registrosHoy->isNotEmpty())
                            @foreach($registrosHoy as $item)
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                                        <div>
                                            <div class="fw-bold">{{ optional($item->municipio)->municipio ?: 'N/D' }}</div>
                                        </div>
                                        <span class="badge bg-success">Registrado</span>
                                    </div>
                                    <div class="small text-muted mt-2 d-none contestado-detalle-item">
                                        {{-- Compatibilidad visual: si existe histórico en BD con "No"/"Ninguno", se presenta como "Municipio no presente". --}}
                                        Asistió Presidente Municipal: {{ $item->presidente === 'Si' ? 'Sí' : ($item->presidente === 'Representante' ? 'Representante' : ($item->presidente === 'Ambos' ? 'Ambos (Presidente y Representante)' : (in_array($item->presidente, ['No', 'Ninguno']) ? 'Municipio no presente' : $item->presidente))) }}@if(!empty($item->asiste)) · Asiste: {{ str_ireplace(['Presidente y Representante', 'Director de seguridad', 'Secretario/Regidor de gobernación', 'Presidente'], ['Presidente y Representante', 'Director de Seguridad Municipal', 'Secretario/Regidor de Gobernación', 'Presidente Municipal'], $item->asiste) }}@endif
                                    </div>
                                </li>
                            @endforeach
                        @else
                            <li class="list-group-item text-muted">Sin municipios contestados aún.</li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5 order-2 order-lg-2 historial-col">
        <div class="mesas-paz-panel panel panel-inverse">
            <div class="mesas-paz-panel-head panel-heading">
                <h4 class="panel-title">Historial</h4>
            </div>
            <div class="mesas-paz-panel-body panel-body">
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <h6 class="mb-0">Herramientas de sesión ({{ $fechaHoyIso }})</h6>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-primary h-40px px-3" id="btnCargarEvidencia" @if(empty($canEditarEvidenciaHoy)) disabled @endif>
                                <i class="fa fa-upload me-1"></i> Cargar
                            </button>
                            <button type="button" class="btn btn-outline-primary h-40px px-3" id="btnPegarEvidencia" @if(empty($canEditarEvidenciaHoy)) disabled @endif title="Pegar imagen desde el portapapeles">
                                <i class="fa fa-paste"></i>
                            </button>
                        </div>
                    </div>

                    <input type="file" id="inputEvidenciaHoy" class="d-none" accept="image/jpeg,image/png,image/webp" multiple>
                    @if(!empty($canEditarEvidenciaHoy))
                        <small class="text-muted d-block mb-3">Puedes cargar hasta 3 imágenes. Arrastra aquí o usa los botones.</small>
                    @else
                        <small class="text-muted d-block mb-3">Para habilitar evidencia, primero registra al menos un municipio hoy.</small>
                    @endif
                    
                    <div id="dropzoneEvidencia" class="border border-2 border-dashed rounded p-3 mb-3 text-center @if(empty($canEditarEvidenciaHoy)) d-none @endif" style="min-height: 120px; background-color: rgba(72, 71, 71, 0.02); display: flex; flex-direction: column; justify-content: center; align-items: center;">
                        <div id="evidenciaActualBox" class="w-100"></div>
                        <div id="dropzonePlaceholder" class="py-3 @if(count($evidenciasActuales) > 0) d-none @endif">
                            <i class="fa fa-images fa-2x text-muted mb-2"></i>
                            <p class="mb-0 text-muted small">Suelta tus imágenes aquí</p>
                        </div>
                    </div>

                    <div>
                        @php
                            $suspensionActivaActual = in_array((string)($modalidadActual ?? ''), ['Suspención de mesa de Seguridad', 'Suspención de la Mesa de Seguridad'], true);
                        @endphp
                        <div id="parte_observacion_group" class="@if($suspensionActivaActual) d-none @endif">
                        <label for="parte_observacion_hoy" class="form-label fw-bold mb-1">Parte</label>
                        @php
                            $parteTextoInicial = collect($parteItemsActual ?? [])->map(function ($item) {
                                return '• '.$item;
                            })->implode("\n");
                            if (trim($parteTextoInicial) === '') {
                                $parteTextoInicial = '• ';
                            }
                        @endphp
                        <textarea
                            id="parte_observacion_hoy"
                            class="form-control mb-2"
                            rows="4"
                            maxlength="5000"
                            placeholder="Redacta aquí el parte de la sesión de hoy..."
                        >{{ $parteTextoInicial }}</textarea>
                        <small class="text-muted d-block mt-1 mb-2">Presiona Enter para agregar otro punto en la lista de parte.</small>
                        </div>

                        <label id="acuerdo_observacion_label" for="acuerdo_observacion_hoy" class="form-label fw-bold mb-1">{{ $suspensionActivaActual ? 'Nota/Observación' : 'Acuerdos/Observaciones' }}</label>
                        @php
                            // Edición dinamica de los acuerdos (Se ajustan a manera de lista y se almacenan como array en BD)
                            $acuerdoTextoInicial = collect($acuerdoItemsActual ?? [])->map(function ($item) {
                                return '• '.$item;
                            })->implode("\n");
                            if (trim($acuerdoTextoInicial) === '') {
                                $acuerdoTextoInicial = '• ';
                            }
                        @endphp
                        <div class="alert alert-warning py-2 px-3 mb-2">
                            Este apartado se captura al final y se aplica para todos los municipios registrados hoy.
                        </div>
                        <textarea
                            id="acuerdo_observacion_hoy"
                            class="form-control"
                            rows="4"
                            maxlength="5000"
                            placeholder="Redacta aquí los acuerdos de la sesión de hoy..."
                        >{{ $acuerdoTextoInicial }}</textarea>
                        <small class="text-muted d-block mt-1">Presiona Enter para agregar otro acuerdo en la lista.</small>
                        <div class="d-flex justify-content-end mt-2">
                            <button type="button" class="btn btn-primary" id="btnGuardarAcuerdoHoy">{{ $suspensionActivaActual ? 'Guardar Nota/Observación' : 'Guardar Parte y Acuerdos' }}</button>
                        </div>
                    </div>
                </div>

                <div id="historialHoyWrapper">
                @if(isset($historialAgrupado) && $historialAgrupado->isNotEmpty())
                    <div class="list-group" id="historialHoyList">
                        @foreach($historialAgrupado as $grupo)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                    <div>
                                        <div class="fw-bold">{{ \Carbon\Carbon::parse($grupo->fecha_asist)->format('d/m/Y') }}</div>
                                        <div class="small text-muted">Registros: {{ $grupo->total_registros }} · Última captura: {{ \Carbon\Carbon::parse($grupo->ultima_captura)->format('H:i') }}</div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-ver-historial-fecha" data-fecha="{{ $grupo->fecha_asist }}">Ver detalle</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="alert alert-secondary mb-0" id="historialHoyEmpty">Sin registros por el momento.</div>
                @endif
                </div>
            </div>
        </div>
    </div>
</div>
    </div>
</div>

<div class="modal fade" id="historialDetalleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de asistencias - <span id="historialDetalleFecha"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="historialDetalleBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="evidenciaPreviewDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="evidenciaPreviewDeleteTitle">Vista previa de evidencia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <img id="evidenciaPreviewDeleteImg" src="" alt="Vista previa de evidencia" class="img-fluid rounded border w-100">
                <p id="evidenciaPreviewDeleteText" class="mb-0 mt-3 text-muted"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger d-none" id="btnConfirmarEliminarEvidencia">Eliminar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Importar Excel -->
<div class="modal fade" id="importarExcelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cargar Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="formImportarExcel">
                    <div class="mb-3">
                        <label for="fechaImportacionModal" class="form-label fw-bold">Fecha de importación</label>
                        <input type="date" id="fechaImportacionModal" class="form-control" name="fecha_importacion" value="{{ $fechaHoyIso }}" max="{{ \Carbon\Carbon::today()->toDateString() }}" required>
                        <small class="text-muted">Se sobreescribirán/guardarán las asistencias en la fecha seleccionada.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Archivo Excel (.xls, .xlsx)</label>
                        <div id="dropzoneExcel" class="mesa-dropzone-excel border border-2 border-dashed rounded p-4 text-center position-relative">
                            <p class="mb-1 text-muted">Arrastra y suelta aquí tu archivo Excel o haz clic para seleccionarlo.</p>
                            <div id="excelFileStatus" class="d-none align-items-center justify-content-center gap-2">
                                <span id="excelFileNameDisplay" class="fw-bold text-success"></span>
                                <button type="button" id="btnRemoveExcel" class="btn-close" style="font-size: 0.75rem;" aria-label="Remover archivo"></button>
                            </div>
                        </div>
                        <input type="file" id="inputExcelHidden" name="archivo_excel" accept=".xls,.xlsx" class="d-none" required>
                    </div>
                </form>
                <div id="importarExcelError" class="alert alert-danger d-none mb-0"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnConfirmarImportacion">
                    <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true" id="spinnerImportacion"></span>
                    Cargar Excel
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const selector = document.getElementById('microrregionSelectorMesas');
        if (!selector) {
            return;
        }

        selector.addEventListener('change', function () {
            const value = String(selector.value || '').trim();
            if (!value) {
                return;
            }

            const url = new URL(window.location.href);
            url.searchParams.set('microrregion_id', value);
            window.location.href = url.toString();
        });
    });
</script>
@endpush
