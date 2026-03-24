{{-- Fragmento AJAX: tabla + paginación. $agendas = LengthAwarePaginator --}}
<div id="agendaAjaxRoot">
    <div class="agenda-table-wrap">
        <table class="agenda-table">
            <thead>
                <tr>
                    <th>Asunto</th>
                    <th>Descripción</th>
                    <th>Fecha y Hora</th>
                    <th>Asignados</th>
                    <th>Recordatorio</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($agendas as $item)
                    <tr>
                        <td>
                            @php
                                $semaforoClass = match($item->semaforo ?? null) {
                                    'rojo' => 'dot-rojo',
                                    'amarillo' => 'dot-amarillo',
                                    'verde' => 'dot-verde',
                                    default => null
                                };
                            @endphp
                            @if($semaforoClass)
                                <span class="status-dot {{ $semaforoClass }}" title="Semáforo: {{ ucfirst($item->semaforo) }}"></span>
                            @endif
                            @if (!empty($item->es_actualizacion))
                                <span class="agenda-pill-actualizacion" title="Seguimiento">Actualización</span>
                            @endif
                            <strong>{{ $item->asunto }}</strong>
                            @if($item->repite)
                                <span class="agenda-pill-recurrente"><i class="fa-solid fa-repeat"></i> Recurrente</span>
                            @endif
                        </td>
                        <td class="agenda-cell-descripcion">
                            @if($item->descripcion)
                                <button type="button" class="agenda-btn agenda-btn-ver-desc" data-descripcion="{{ e($item->descripcionConAforoPersonas()) }}" onclick="window.agendaVerDescripcion(this)" title="Ver descripción">
                                    Ver
                                </button>
                            @else
                                <span class="agenda-text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            {{ $item->fecha_inicio->format('d/m/Y') }}
                            @if($item->fecha_fin)
                                – {{ $item->fecha_fin->format('d/m/Y') }}
                            @endif
                            @if($item->habilitar_hora && $item->hora)
                                <small><i class="fa-regular fa-clock"></i> {{ \Carbon\Carbon::parse($item->hora)->format('H:i') }}</small>
                            @endif
                        </td>
                        <td>
                            @if($item->usuariosAsignados->isEmpty())
                                <span class="agenda-text-muted">Sin asignar</span>
                            @else
                                <button type="button"
                                        class="agenda-btn agenda-btn-secondary agenda-btn-icon agenda-btn-ver-usuarios"
                                        title="Ver usuarios asignados"
                                        onclick="window.agendaVerUsuariosAsignados(this)"
                                        data-users='@json($item->usuariosAsignados->map(fn($u) => ["id" => $u->id, "name" => $u->name, "email" => $u->email])->values())'>
                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                </button>
                            @endif
                        </td>
                        <td>
                            {{ $item->reminder_label }}
                            @if(count($item->direcciones_adicionales ?? []) > 0)
                                <small>+{{ count($item->direcciones_adicionales) }} correo(s)</small>
                            @endif
                        </td>
                        <td>
                            @php
                                $estadoActivo = $item->fecha_inicio->isFuture() || $item->fecha_inicio->isToday();
                            @endphp
                            <span class="agenda-badge {{ $estadoActivo ? 'is-active' : 'is-pasado' }}">
                                {{ $estadoActivo ? 'Programado' : 'Realizado' }}
                            </span>
                        </td>
                        <td>
                            <div class="agenda-actions-cell">
                            @if (!empty($puedeEditarAgenda))
                            <button type="button" class="agenda-btn agenda-btn-table agenda-btn-icon"
                                    onclick="openAgendaModal({{ $item->id }})"
                                    data-id="{{ $item->id }}"
                                    data-tipo="{{ $item->tipo }}"
                                    data-subtipo="{{ $item->subtipo ?? 'gira' }}"
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
                                    data-addresses='@json($item->direcciones_adicionales ?? [])'
                                    title="Editar">
                                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                            </button>
                            <form action="{{ route('agenda.destroy', $item->id) }}" method="POST" id="delete-form-{{ $item->id }}" class="agenda-inline-form">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="agenda-btn agenda-btn-danger agenda-btn-icon" onclick="confirmDelete({{ $item->id }})" title="Eliminar">
                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                </button>
                            </form>
                            @endif
                            <a href="{{ $item->getGoogleCalendarUrl() }}" target="_blank" class="agenda-btn agenda-btn-table agenda-btn-icon" title="Google Calendar">
                                <i class="fa-brands fa-google"></i>
                            </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="agenda-table-empty">
                            No hay asuntos registrados en la agenda para mostrar.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="agenda-pagination-wrap">
        {{ $agendas->withQueryString()->links('pagination::tm') }}
    </div>
</div>
