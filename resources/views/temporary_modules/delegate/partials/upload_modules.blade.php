<div class="tm-module-grid" id="tmFragmentUpload">
    @forelse ($modules as $module)
        <article class="content-card tm-card tm-module-card tm-upload-card">
            <div class="tm-upload-card-head">
                <h2>{{ $module->name }}</h2>
                <p>{{ $module->description ?: 'Sin descripcion adicional.' }}</p>
            </div>
            <div class="tm-upload-meta-row">
                <span class="tm-upload-meta-pill"><strong>Vence:</strong> {{ optional($module->expires_at)->format('d/m/Y H:i') ?? 'Sin limite' }}</span>
                <span class="tm-upload-meta-pill"><strong>Mis registros:</strong> {{ $module->my_entries_count }}</span>
            </div>
            <div class="tm-upload-card-foot">
                <button type="button" class="tm-btn tm-btn-primary" data-open-module-preview="delegate-preview-{{ $module->id }}">Registrar informacion</button>
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
