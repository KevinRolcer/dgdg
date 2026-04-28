<div id="agendaModal" class="modal-agenda-overlay" style="display: none;">
    <div class="modal-agenda-content">
        <div class="modal-agenda-header">
            <h3 id="modalTitle">Nuevo Asunto</h3>
            <button type="button" class="modal-close-btn" onclick="closeAgendaModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="agendaForm" method="POST" action="{{ route('agenda.store') }}">
                        <input type="hidden" name="delegado_encargado" id="modalDelegadoEncargado" value="">
            @csrf
            <input type="hidden" name="_method" id="formMethod" value="POST">
            <input type="hidden" name="tipo" id="modalTipo" value="asunto">
            <input type="hidden" name="subtipo" id="modalSubtipo" value="gira">

            <div class="modal-body-scroll">
                <div class="agenda-type-switch" id="agendaTipoSelector">
                    <div class="agenda-type-switch-main">
                        <label class="form-label-agenda agenda-type-switch-label">Tipo de evento</label>
                        <div class="agenda-type-pills">
                            <button type="button" class="agenda-type-pill" data-subtipo="pre-gira">Pre-Gira</button>
                            <button type="button" class="agenda-type-pill is-active" data-subtipo="gira">Gira</button>
                        </div>
                    </div>
                    <div class="agenda-type-switch-actions">
                        <button type="button" id="btnOpenDescModal" class="agenda-desc-trigger">
                            <i class="fa-solid fa-align-left" style="color: var(--clr-accent, #c79b66);"></i> Añadir Descripción
                        </button>
                    </div>
                </div>

                <div class="form-group-agenda">
                    <label class="form-label-agenda">Asunto (Título) <span class="text-red-500">*</span></label>
                    <input type="text" name="asunto" id="modalAsunto" class="form-control-agenda" required placeholder="Título del evento">
                </div>

                <div id="fieldsFichaPersonalizada" class="agenda-custom-ficha-box" style="display: none;">
                    <div class="agenda-row-two-cols mb-3">
                        <div class="agenda-custom-title-col">
                            <label class="form-label-agenda">Título de ficha <span class="text-red-500">*</span></label>
                            <input type="text" name="ficha_titulo" id="modalFichaTitulo" class="form-control-agenda" placeholder="Cumpleaños, Asamblea, Reunión...">
                        </div>
                        <div class="agenda-custom-bg-col">
                            <label class="form-label-agenda">Fondo de ficha</label>
                            <select name="ficha_fondo" id="modalFichaFondo" class="form-control-agenda">
                                <option value="tlaloc_a_beige">Tlaloc A beige</option>
                                <option value="tlaloc_a_rojo">Tlaloc A rojo</option>
                                <option value="tlaloc_a_verde">Tlaloc A verde</option>
                                <option value="beige">Tlaloc C beige</option>
                                <option value="blanco">Tlaloc C blanco</option>
                                <option value="rojo">Tlaloc C rojo</option>
                                <option value="verde">Tlaloc C verde</option>
                            </select>
                        </div>
                    </div>
                    <div class="agenda-ficha-bg-preview" id="agendaFichaBgPreview" aria-hidden="true">
                        <span>Vista del fondo</span>
                    </div>
                    <div class="form-group-agenda">
                        <label class="form-label-agenda">Ubicación</label>
                        <input type="text" name="lugar" id="modalLugarPersonalizado" class="form-control-agenda" placeholder="Lugar, dirección o enlace de Google Maps" disabled>
                    </div>
                </div>

                <div class="form-group-agenda" id="rowDescripcion">
                    <label class="form-label-agenda">Descripción</label>
                    <textarea name="descripcion" id="modalDescripcion" rows="1" class="form-control-agenda agenda-descripcion-textarea" placeholder="Detalles..."></textarea>
                </div>

                <!-- Fields for Gira only -->
                <div id="fieldsGira" style="display: none;">
                    <div class="agenda-row-two-cols mb-3">
                        <div class="agenda-col-40">
                            <label class="form-label-agenda">Microregión</label>
                            <select name="microrregion" id="modalMicrorregion" class="form-control-agenda">
                                <option value="">Seleccione Microregión</option>
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
                <div class="agenda-fecha-hora-row">
                    <div class="agenda-fecha-col">
                        <label class="form-label-agenda">Fecha <span class="text-red-500">*</span></label>
                        <input type="date" name="fecha_inicio" id="modalFecha" class="form-control-agenda" required>
                    </div>
                    <div class="agenda-hora-col">
                        <label class="form-label-agenda">Hora</label>
                        <div class="agenda-time-control">
                            <label class="toggle-switch-mini">
                                <input type="checkbox" name="habilitar_hora" id="modalHabilitarHora" value="1">
                                <span class="slider-mini"></span>
                            </label>
                            <input type="time" name="hora" id="modalHora" class="form-control-agenda agenda-time-input" style="display: none;">
                        </div>
                    </div>
                    <div class="agenda-recordatorio-col">
                        <div class="agenda-reminder-head">
                            <label class="form-label-agenda agenda-reminder-label">Recordatorio</label>
                            {{-- Número telefónico desactivado de momento
                            <button type="button" id="btnAddAddress" class="btn-add-mini" style="font-size: 10px; padding: 0;">
                                <i class="fa-solid fa-plus"></i> Teléfono
                            </button>
                            --}}
                        </div>
                        <div class="agenda-reminder-control">
                            <select name="recordatorio_minutos" id="modalRecordatorio" class="form-control-agenda agenda-reminder-select">
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
                            <span class="agenda-reminder-before">antes</span>
                        </div>
                    </div>
                </div>

                <div class="agenda-gira-delegado-aforo-row">
                    {{-- Solo Gira/Pre-Gira: delegado de la MR --}}
                    <div class="agenda-col-40" id="agendaDelegadoLabelWrap" style="display: none;">
                        <p id="agendaDelegadoLabel" class="agenda-delegado-label">
                            Delegad@ encargado: —
                        </p>
                    </div>
                    <div class="agenda-assign-aforo-wrap">
                        <button type="button" id="btnOpenAssignModal" class="btn-toggle-unfold">
                            <i class="fa-solid fa-user-plus"></i> Asignar Usuario
                        </button>
                        {{-- Aforo opcional (Gira/Pre-Gira); si >0 se guarda en descripción como "Aforo: N" --}}
                        <div class="agenda-aforo-field" id="agendaAforoWrap" style="display: none;" aria-hidden="true">
                            <label class="form-label-agenda agenda-aforo-label" for="modalAforo">Aforo</label>
                            <div class="agenda-aforo-input-row">
                                <input type="number" id="modalAforo" class="form-control-agenda agenda-aforo-input" min="0" step="1" placeholder="Opcional" inputmode="numeric" title="Número de personas; vacío o 0 no se añade a la descripción" aria-describedby="agendaAforoSuffix">
                                <span class="agenda-aforo-suffix" id="agendaAforoSuffix">personas</span>
                            </div>
                        </div>
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

<!-- Modal Solo Lectura para Usuarios Asignados -->
<div id="agendaVerUsuariosModal" class="modal-agenda-overlay" style="display:none; z-index: 10000;">
    <div class="modal-agenda-content" style="max-width: 520px; padding: 20px;">
        <div class="modal-agenda-header">
            <h3>Usuarios asignados</h3>
            <button type="button" class="modal-close-btn" onclick="window.agendaCerrarVerUsuariosAsignados()" aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body-scroll" style="max-height: 50vh; overflow-y: auto;">
            <div id="agendaVerUsuariosBody" class="agenda-assigned-users-body"></div>
        </div>
        <div class="modal-agenda-footer modal-agenda-footer--readonly">
            <button type="button" class="btn-agenda btn-secondary-agenda" onclick="window.agendaCerrarVerUsuariosAsignados()">Cerrar</button>
        </div>
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
</div>

<!-- Modal Pequeño para Descripción -->
<div id="agendaDescModal" class="modal-agenda-overlay" style="display:none; z-index: 10000;">
    <div class="modal-agenda-content" style="max-width: 500px; padding: 20px;">
        <div class="modal-agenda-header">
            <h3>Añadir Descripción</h3>
            <button type="button" class="modal-close-btn" onclick="closeDescModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body-scroll" style="max-height: 400px; overflow-y: auto;">
            <div class="form-group-agenda">
                <label class="form-label-agenda">Escribe los detalles adicionales de este evento:</label>
                <textarea id="modalMiniDescripcion" rows="6" class="form-control-agenda" placeholder="Agrega aquí la descripción..." style="resize: vertical; min-height: 120px;"></textarea>
            </div>
        </div>
        <div class="modal-agenda-footer" style="margin-top: 15px;">
            <button type="button" class="btn-agenda btn-secondary-agenda" onclick="closeDescModal()">Cerrar</button>
            <button type="button" class="btn-agenda btn-primary-agenda" id="btnSaveDescModal">Aceptar</button>
        </div>
    </div>
</div>
