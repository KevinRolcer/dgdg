{{-- Nueva carpeta al inicio (misma fila desplazable que las tarjetas) --}}
<div class="pa-card pa-card--folder pa-card--placeholder" style="border-style: dashed; padding: 12px;" onclick="openFolderModal()">
    <div class="pa-placeholder-icon">
        <i class="fa-solid fa-folder-plus"></i>
    </div>
    <span style="font-size: 0.75rem; font-weight: 700;">Nueva carpeta</span>
</div>

@foreach($folders as $folder)
    <div class="pa-card pa-card--folder {{ $folder->getContrastClass() }} {{ ($folder->is_pinned ?? false) ? 'is-folder-pinned' : '' }}"
         style="background-color: {{ $folder->color ?? 'var(--pa-blue)' }}; position: relative;"
         data-id="{{ $folder->id }}">
        <div class="pa-folder-card-actions" onclick="event.stopPropagation();">
            <button type="button" class="pa-folder-action-btn pa-folder-pin-btn {{ ($folder->is_pinned ?? false) ? 'is-pinned' : '' }}"
                    title="{{ ($folder->is_pinned ?? false) ? 'Quitar fijación' : 'Fijar carpeta (máx. 6)' }}"
                    aria-pressed="{{ ($folder->is_pinned ?? false) ? 'true' : 'false' }}"
                    aria-label="{{ ($folder->is_pinned ?? false) ? 'Quitar fijación' : 'Fijar carpeta' }}"
                    onclick='toggleFolderPin({{ $folder->id }})'>
                <i class="fa-solid fa-thumbtack" style="font-size: 0.62rem;"></i>
            </button>
            <button type="button" class="pa-folder-action-btn" title="Editar carpeta" aria-label="Editar carpeta" onclick='openFolderModal({{ $folder->id }})'>
                <i class="fa-solid fa-pen" style="font-size: 0.62rem;"></i>
            </button>
            <button type="button" class="pa-folder-action-btn" title="Archivar carpeta" aria-label="Archivar carpeta" onclick='archiveFolder({{ $folder->id }}, @json($folder->name))'>
                <i class="fa-solid fa-box-archive" style="font-size: 0.62rem;"></i>
            </button>
            <button type="button" class="pa-folder-action-btn pa-folder-action-btn--danger" title="Eliminar carpeta" aria-label="Eliminar carpeta" onclick='deleteFolder({{ $folder->id }}, @json($folder->name))'>
                <i class="fa-solid fa-trash-can" style="font-size: 0.62rem;"></i>
            </button>
        </div>
        <div class="pa-folder-icon">
            <i class="fa-solid {{ $folder->icon ?: 'fa-folder' }}"></i>
        </div>
        <h3 class="pa-card-title" style="margin: 0; font-size: 0.9rem;">{{ $folder->name }}</h3>
        <span class="pa-folder-count-num">{{ $folder->notes_count }} notas</span>
    </div>
@endforeach
