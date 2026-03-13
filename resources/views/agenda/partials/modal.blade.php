<div id="agendaModal" class="modal-agenda-overlay" style="display: none;">
    <div class="modal-agenda-content">
        <div class="modal-agenda-header">
            <h3 id="modalTitle">Nuevo Asunto</h3>
            <button type="button" class="modal-close-btn" onclick="closeAgendaModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        
        <form id="agendaForm" method="POST" action="{{ route('agenda.store') }}">
            @csrf
            <input type="hidden" name="_method" id="formMethod" value="POST">
            <input type="hidden" name="tipo" id="modalTipo" value="asunto">
            <input type="hidden" name="subtipo" id="modalSubtipo" value="gira">
            
            <div class="modal-body-scroll">
                <div class="form-group-agenda agenda-type-switch" id="agendaTipoSelector" style="display: none;">
                    <label class="form-label-agenda">Tipo de evento</label>
                    <div class="agenda-type-pills">
                    <button type="button" class="agenda-type-pill" data-subtipo="pre-gira">Pre-Gira</button>
                        <button type="button" class="agenda-type-pill is-active" data-subtipo="gira">Gira</button>
                    </div>
                </div>

                <div class="form-group-agenda">
                    <label class="form-label-agenda">Asunto (Título) <span class="text-red-500">*</span></label>
                    <input type="text" name="asunto" id="modalAsunto" class="form-control-agenda" required placeholder="Título del evento">
                </div>

                <div class="form-group-agenda" id="rowDescripcion">
                    <label class="form-label-agenda">Descripción</label>
                    <textarea name="descripcion" id="modalDescripcion" rows="1" class="form-control-agenda" placeholder="Detalles..." style="min-height: 38px;"></textarea>
                </div>

                <!-- Fields for Gira only -->
                <div id="fieldsGira" style="display: none;">
                    <div class="agenda-row-two-cols mb-3">
                        <div class="agenda-col-40">
                            <label class="form-label-agenda">Microrregión</label>
                            <select name="microrregion" id="modalMicrorregion" class="form-control-agenda">
                                <option value="">Seleccione Microrregión</option>
                                @foreach($microrregiones as $micro)
                                    <option value="{{ $micro->microrregion }}"
                                            data-id="{{ $micro->id }}"
                                            data-delegado="{{ $micro->delegado_nombre ?? '' }}">
                                        MR {{ $micro->microrregion }} - {{ $micro->cabecera }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="agenda-col-municipio">
                            <label class="form-label-agenda">Municipio</label>
                            <select name="municipio" id="modalMunicipio" class="form-control-agenda">
                                <option value="">Seleccione Municipio</option>
                                @foreach($municipios as $muni)
                                    <option value="{{ $muni->municipio }}" data-micro="{{ $muni->microrregion_id ?? '' }}" data-micro-codigo="{{ $muni->microrregion_codigo ?? '' }}">{{ $muni->municipio }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="agenda-col-semaforo">
                            <label class="form-label-agenda">Semáforo</label>
                            <select name="semaforo" id="modalSemaforo" class="form-control-agenda">
                                <option value="">Sin definir</option>
                                <option value="rojo">Rojo</option>
                                <option value="amarillo">Amarillo</option>
                                <option value="verde">Verde</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group-agenda">
                        <label class="form-label-agenda">Lugar</label>
                        <input type="text" name="lugar" id="modalLugar" class="form-control-agenda" placeholder="Dirección o enlace de Google Maps">
                    </div>
                </div>

                <!-- Common temporal row -->
                <div style="display: flex !important; flex-direction: row !important; gap: 12px; align-items: flex-end; width: 100%; margin-bottom: 1rem;">
                    <div style="flex: 1; min-width: 120px;">
                        <label class="form-label-agenda">Fecha <span class="text-red-500">*</span></label>
                        <input type="date" name="fecha_inicio" id="modalFecha" class="form-control-agenda" required>
                    </div>
                    <div style="width: auto; flex-shrink: 0;">
                        <label class="form-label-agenda">Hora</label>
                        <div style="display: flex; align-items: center; gap: 8px; height: 34px;">
                            <label class="toggle-switch-mini">
                                <input type="checkbox" name="habilitar_hora" id="modalHabilitarHora" value="1">
                                <span class="slider-mini"></span>
                            </label>
                            <input type="time" name="hora" id="modalHora" class="form-control-agenda" style="display: none; width: 95px; padding: 2px 4px; font-size: 11px;">
                        </div>
                    </div>
                    <div style="flex: 1; min-width: 140px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                            <label class="form-label-agenda" style="margin-bottom: 0; font-size: 10px;">Recordatorio</label>
                            {{-- Número telefónico desactivado de momento
                            <button type="button" id="btnAddAddress" class="btn-add-mini" style="font-size: 10px; padding: 0;">
                                <i class="fa-solid fa-plus"></i> Teléfono
                            </button>
                            --}}
                        </div>
                        <div style="display: flex; align-items: center; gap: 4px; height: 34px;">
                            <select name="recordatorio_minutos" id="modalRecordatorio" class="form-control-agenda" style="min-width: 120px;">
                                <option value="30" selected>30 min</option>
                                <option value="60">1 h</option>
                                <option value="90">1 h 30 min</option>
                                <option value="120">2 h</option>
                                <option value="150">2 h 30 min</option>
                                <option value="180">3 h</option>
                                <option value="240">4 h</option>
                                <option value="300">5 h</option>
                                <option value="360">6 h</option>
                            </select>
                            <span style="font-size: 10px; color: #64748b; white-space: nowrap;">antes</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 agenda-row-two-cols">
                    {{-- Solo Gira/Pre-Gira: el delegado es el de la microrregión elegida --}}
                    <div class="agenda-col-40" id="agendaDelegadoLabelWrap" style="display: none;">
                        <p id="agendaDelegadoLabel" class="agenda-delegado-label">
                            Delegad@ encargado: —
                        </p>
                    </div>
                    <div>
                        <button type="button" id="btnOpenAssignModal" class="btn-toggle-unfold">
                            <i class="fa-solid fa-user-plus"></i> Asignar Usuario
                        </button>
                    </div>
                </div>

                {{-- Número telefónico desactivado de momento --}}
                {{-- <div id="extraAddressesContainer" class="agenda-phones-container mt-2">
                    <span class="agenda-phones-hint">Números telefónicos adicionales (opcional)</span>
                </div> --}}
            </div>


            <div class="modal-agenda-footer">
                <button type="button" class="btn-agenda btn-secondary-agenda" onclick="closeAgendaModal()">Cancelar</button>
                <button type="submit" class="btn-agenda btn-primary-agenda">Guardar Asunto</button>
            </div>
        </form>
    </div>
</div>

<div id="agendaAssignModal" class="modal-agenda-overlay" style="display:none;">
    <div class="modal-agenda-content modal-assign-content">
        <div class="modal-agenda-header">
            <h3>Asignar Usuario / Delegado</h3>
            <button type="button" class="modal-close-btn" onclick="closeAssignModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body-scroll">
            <div class="form-group-agenda">
                <label class="form-label-agenda">Selecciona usuarios o delegados</label>
                <input type="text" id="modalUserSearch" class="form-control-agenda py-1 text-sm mb-2" placeholder="Buscar usuario o delegado...">
            </div>
            <div class="user-selection-list-mini">
                @foreach($users as $user)
                    <label class="user-item-mini" data-name="{{ strtolower($user->name) }}" data-email="{{ strtolower($user->email) }}">
                        <input type="checkbox" name="usuarios_asignados[]" value="{{ $user->id }}" form="agendaForm">
                        <div class="user-info-mini">
                            <span class="u-name">{{ $user->name }}</span>
                            <span class="u-email">{{ $user->email }}</span>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>
        <div class="modal-agenda-footer">
            <button type="button" class="btn-agenda btn-secondary-agenda" onclick="closeAssignModal()">Cerrar</button>
        </div>
    </div>
</div>
