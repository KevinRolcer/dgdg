@if(!empty($puedeAsignarModuloAgenda))
<div id="agendaModuloModal" class="modal-agenda-overlay agenda-modulo-overlay-top" style="display: none;" aria-hidden="true">
    <div class="modal-agenda-content agenda-modulo-modal" style="max-width: 520px;">
        <div class="modal-agenda-header">
            <h3>Asignar módulo Agenda Directiva</h3>
            <button type="button" class="modal-close-btn" onclick="closeAgendaModuloModal()" aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body-scroll" style="max-height: 60vh;">
            <p class="agenda-modulo-hint">Solo usuarios con rol <strong>Enlace</strong>. Al asignar, verán <strong>Agenda Directiva</strong> en el menú y podrán usar el módulo (sin acceso a administración de módulos temporales).</p>
            <div id="agendaModuloLoading" class="agenda-modulo-loading" style="display: none;">Cargando enlaces…</div>
            <ul id="agendaModuloList" class="agenda-modulo-list"></ul>
        </div>
    </div>
</div>
@endif
