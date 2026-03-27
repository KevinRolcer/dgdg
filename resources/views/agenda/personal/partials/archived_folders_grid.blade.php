@if($folders->isEmpty())
    <div class="pa-archived-folders-empty" style="padding: 16px 8px; font-size: 0.85rem; color: var(--clr-text-light); text-align: center; width: 100%;">
        No hay carpetas archivadas. Al archivar una carpeta desde el listado principal, aparecerá aquí.
    </div>
@else
    @foreach($folders as $folder)
        <div class="pa-card pa-card--folder pa-card--folder-archived {{ $folder->getContrastClass() }}"
             style="background-color: {{ $folder->color ?? 'var(--pa-blue)' }}; position: relative; opacity: 0.95;"
             data-id="{{ $folder->id }}">
            <div class="pa-folder-card-actions" onclick="event.stopPropagation();">
                <button type="button" class="pa-folder-action-btn" title="Restaurar carpeta" aria-label="Restaurar carpeta" onclick="restoreArchivedFolder({{ $folder->id }})">
                    <i class="fa-solid fa-rotate-left" style="font-size: 0.62rem;"></i>
                </button>
            </div>
            <div class="pa-folder-icon">
                <i class="fa-solid {{ $folder->icon ?: 'fa-folder' }}"></i>
            </div>
            <h3 class="pa-card-title" style="margin: 0; font-size: 0.9rem;">{{ $folder->name }}</h3>
            <span class="pa-folder-count-num">{{ $folder->notes_count }} notas</span>
        </div>
    @endforeach
@endif
