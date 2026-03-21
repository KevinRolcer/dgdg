<div class="tm-module-grid" id="tmFragmentUpload">
    @forelse ($modules as $module)
        <article class="content-card tm-card tm-module-card tm-upload-card">
            <div class="tm-upload-card-head">
                <div class="tm-card-title-row">
                    <h2>{{ $module->name }}</h2>
                    <button type="button"
                            class="tm-btn-refresh"
                            data-refresh-module="{{ $module->id }}"
                            data-refresh-url="{{ route('temporary-modules.module-status', $module->id) }}"
                            title="Actualizar estado del módulo"
                            aria-label="Actualizar estado del módulo">
                        <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
                    </button>
                </div>
                <p>{{ $module->description ?: 'Sin descripcion adicional.' }}</p>
            </div>
            <div class="tm-upload-meta-row">
                <span class="tm-upload-meta-pill"><strong>Vence:</strong> {{ optional($module->expires_at)->format('d/m/Y H:i') ?? 'Sin limite' }}</span>
                <span class="tm-upload-meta-pill"><strong>Mis registros:</strong> {{ $module->my_entries_count }}</span>
            </div>
            <div class="tm-upload-card-foot">
                <button type="button" class="tm-btn tm-btn-primary tm-btn-sm" data-open-module-preview="delegate-preview-{{ $module->id }}">Registrar</button>
                <a href="{{ route('temporary-modules.records') }}?module={{ $module->id }}" class="tm-btn tm-btn-outline tm-btn-sm" title="Historial de registros">
                    <i class="fa-solid fa-clock" aria-hidden="true"></i>
                </a>
                <button type="button" class="tm-btn tm-btn-outline tm-btn-sm tm-btn-session-errors tm-hidden" 
                        id="tmBtnSessionErrors-{{ $module->id }}" 
                        data-session-errors-module="{{ $module->id }}"
                        title="Errores pendientes de importación">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span class="tm-error-count">0</span>
                </button>
            </div>
        </article>
    @empty
        <article class="content-card tm-card">
            <p>No hay modulos temporales activos en este momento.</p>
        </article>
    @endforelse
    @if ($modules->hasPages())
        <div class="tm-pagination tm-pagination--footer">
            {{ $modules->links('vendor.pagination.tm') }}
        </div>
    @endif
</div>
