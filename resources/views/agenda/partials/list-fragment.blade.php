{{-- Fragmento AJAX: tabla + paginación. $agendas = LengthAwarePaginator --}}
<div id="agendaAjaxRoot">
    <div class="ag-chats-grid">
        @forelse ($agendas as $item)
            @php
                $semaforoClass = match($item->semaforo ?? null) {
                    'rojo' => '#ef4444',
                    'amarillo' => '#f59e0b',
                    'verde' => '#10b981',
                    default => 'var(--clr-primary, #1e293b)'
                };
                $estadoActivo = $item->fecha_inicio->isFuture() || $item->fecha_inicio->isToday();
                $letraInicial = mb_strtoupper(mb_substr($item->asunto, 0, 1));
            @endphp
            <div class="ag-chat-card">
                {{-- Card Head --}}
                <div class="ag-card-head">
                    <div class="ag-card-info">
                        <h3 class="ag-card-title">{{ $item->asunto }}</h3>
                        <p class="ag-card-subtitle">
                            <i class="fa-regular fa-file" aria-hidden="true"></i>
                            {{ ucfirst(str_replace('_', '-', $item->tipo)) }}
                            @if($item->tipo === 'personalizado' && $item->ficha_titulo)
                                - {{ $item->ficha_titulo }}
                            @elseif($item->subtipo && $item->tipo != 'agenda')
                                — {{ ucfirst($item->subtipo) }}
                            @endif
                            @if (!empty($item->es_actualizacion))
                                <span class="ag-badge-inline">Actualización</span>
                            @endif
                            @if($item->repite)
                                <span class="ag-badge-inline" title="Recurrente"><i class="fa-solid fa-repeat"></i></span>
                            @endif
                        </p>
                    </div>
                    <span class="ag-status-badge {{ $estadoActivo ? 'ag-status-badge--active' : 'ag-status-badge--past' }}">
                        <i class="{{ $estadoActivo ? 'fa-regular fa-circle-check' : 'fa-solid fa-check-double' }}"></i>
                        {{ $estadoActivo ? 'Programado' : 'Realizado' }}
                    </span>
                </div>

                {{-- Card Meta Info --}}
                <div class="ag-card-meta">
                    <div class="ag-card-meta-item" title="Fecha y Hora">
                        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                        {{ $item->fecha_inicio->format('d/m/Y') }}
                        @if($item->fecha_fin)
                            – {{ $item->fecha_fin->format('d/m/Y') }}
                        @endif
                        @if($item->habilitar_hora && $item->hora)
                            a las {{ \Carbon\Carbon::parse($item->hora)->format('H:i') }}
                        @endif
                    </div>
                    <div class="ag-card-meta-item" title="Recordatorio">
                        <i class="fa-regular fa-bell" aria-hidden="true"></i>
                        {{ $item->reminder_label }}
                    </div>
                    <div class="ag-card-meta-item" title="Asignados">
                        <i class="fa-regular fa-user" aria-hidden="true"></i>
                        @if($item->usuariosAsignados->isEmpty())
                            Sin asignar
                        @else
                            {{ $item->usuariosAsignados->count() }} usuario(s)
                            @if(count($item->direcciones_adicionales ?? []) > 0)
                                +{{ count($item->direcciones_adicionales) }} correo(s)
                            @endif
                        @endif
                    </div>
                </div>

                {{-- Popup Flotante (solo CSS y JS) --}}
                <div class="ag-card-popup" role="menu">
                    @if (!empty($puedeEditarAgenda))
                        <button type="button" class="ag-popup-item"
                                onclick="openAgendaModal({{ $item->id }})"
                                data-id="{{ $item->id }}"
                                data-tipo="{{ $item->tipo }}"
                                data-subtipo="{{ $item->subtipo ?? 'gira' }}"
                                data-ficha-titulo="{{ e($item->ficha_titulo) }}"
                                data-ficha-fondo="{{ e($item->ficha_fondo) }}"
                                data-asunto="{{ e($item->asunto) }}"
                                data-microrregion="{{ e($item->microrregion) }}"
                                data-municipio="{{ e($item->municipio) }}"
                                data-lugar="{{ e($item->lugar) }}"
                                data-semaforo="{{ $item->semaforo ?? '' }}"
                                data-seguimiento="{{ e($item->seguimiento) }}"
                                data-descripcion="{{ e($item->descripcion) }}"
                                data-fecha="{{ $item->fecha_inicio->format('Y-m-d') }}"
                                data-hora="{{ $item->hora }}"
                                data-recordatorio="{{ $item->recordatorio_minutos ?? 30 }}"
                                data-repite="{{ $item->repite ? '1' : '0' }}"
                                data-days='@json($item->dias_repeticion ?? [])'
                                data-users='@json($item->usuariosAsignados->pluck('id'))'
                                data-addresses='@json($item->direcciones_adicionales ?? [])'>
                            <i class="fa-solid fa-pen" aria-hidden="true"></i> Editar evento
                        </button>
                    @endif

                    @if($item->descripcion)
                        <button type="button" class="ag-popup-item" data-descripcion="{{ e($item->descripcionConAforoPersonas()) }}" onclick="window.agendaVerDescripcion(this)">
                            <i class="fa-solid fa-align-left" aria-hidden="true"></i> Ver descripción
                        </button>
                    @endif

                    @if($item->usuariosAsignados->isNotEmpty())
                        <button type="button" class="ag-popup-item" onclick="window.agendaVerUsuariosAsignados(this)" data-users='@json($item->usuariosAsignados->map(fn($u) => ["id" => $u->id, "name" => $u->name, "email" => $u->email])->values())'>
                            <i class="fa-solid fa-users" aria-hidden="true"></i> Ver asignados
                        </button>
                    @endif

                    <a href="{{ $item->getGoogleCalendarUrl() }}" target="_blank" class="ag-popup-item">
                        <i class="fa-brands fa-google" aria-hidden="true"></i> Guardar en Google
                    </a>

                    @if (!empty($puedeEditarAgenda))
                        <div class="ag-popup-divider"></div>
                        <form action="{{ route('agenda.destroy', $item->id) }}" method="POST" id="delete-form-{{ $item->id }}" class="js-ag-card-delete" style="margin:0;">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="ag-popup-item ag-popup-item--danger" onclick="confirmDelete({{ $item->id }})">
                                <i class="fa-solid fa-trash" aria-hidden="true"></i> Eliminar
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="ag-empty-state">
                <div class="ag-empty-icon"><i class="fa-regular fa-calendar-xmark"></i></div>
                <p class="ag-empty-title">Sin eventos registrados</p>
                <p class="ag-empty-desc">No se encontraron eventos en la agenda que coincidan con la búsqueda.</p>
            </div>
        @endforelse
    </div>

    <div class="agenda-pagination-wrap">
        @php
            $total = (int) $agendas->total();
            $currentPage = (int) $agendas->currentPage();
            $lastPage = max(1, (int) $agendas->lastPage());
            $n = (int) $agendas->count();
            $first = $n > 0 ? (int) $agendas->firstItem() : 0;
            $last = $n > 0 ? (int) $agendas->lastItem() : 0;
        @endphp

        <p class="agenda-pagination-info">
            @if ($total === 0)
                Sin registros.
            @else
                Página <strong>{{ $currentPage }}</strong> de <strong>{{ $lastPage }}</strong>
                · Mostrando <strong>{{ $first }}</strong>–<strong>{{ $last }}</strong>
                de <strong>{{ $total }}</strong> {{ $total === 1 ? 'registro' : 'registros' }}
            @endif
        </p>

        @if ($agendas->hasPages())
            {{ $agendas->withQueryString()->links('pagination::tm') }}
        @endif
    </div>
</div>
