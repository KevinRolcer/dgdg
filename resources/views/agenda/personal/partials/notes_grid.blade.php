{{-- notes_grid.blade.php --}}
@php
    if (!function_exists('getContrastClass')) {
        function getContrastClass($hexColor) {
            if (!$hexColor || !str_starts_with($hexColor, '#')) return 'text-dark';
            $hexColor = str_replace('#', '', $hexColor);
            if (strlen($hexColor) == 3) {
                $r = hexdec(substr($hexColor, 0, 1) . substr($hexColor, 0, 1));
                $g = hexdec(substr($hexColor, 1, 1) . substr($hexColor, 1, 1));
                $b = hexdec(substr($hexColor, 2, 1) . substr($hexColor, 2, 1));
            } else {
                $r = hexdec(substr($hexColor, 0, 2));
                $g = hexdec(substr($hexColor, 2, 2));
                $b = hexdec(substr($hexColor, 4, 2));
            }
            $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
            return $luminance > 0.6 ? 'text-dark' : 'text-light';
        }
    }
@endphp

{{-- Nueva Nota Placeholder (Solo en vista 'all', 'calendar' o 'folder') --}}
@if(!isset($filter) || in_array($filter, ['all', 'calendar', 'folder']))
<div class="pa-card pa-card--note pa-card--placeholder" style="min-height: 180px; border-style: dashed;" onclick="openPersonalNoteModal()">
    <div class="pa-placeholder-icon">
        <i class="fa-solid fa-plus-circle" style="font-size: 1.8rem;"></i>
    </div>
    <span style="font-weight: 700; font-size: 0.85rem;">Nueva Nota</span>
</div>
@endif

@php
    $icons = [
        'archive' => 'fa-box-archive',
        'trash' => 'fa-trash-can',
        'calendar' => 'fa-calendar-day',
        'all' => 'fa-clipboard-question'
    ];
    $icon = $icons[$filter ?? 'all'] ?? 'fa-clipboard-question';
@endphp

@forelse($notes as $note)
    @php
        $contrastClass = getContrastClass($note->color);
        $defaultColors = ['var(--pa-blue)', 'var(--pa-green)', 'var(--pa-yellow)', 'var(--pa-red)', 'var(--pa-purple)'];
        $bgColor = $note->color ?: $defaultColors[$loop->index % 5];
        $isTrashed = $note->trashed();
    @endphp
@php
    if (!function_exists('linkifyPersonalAgenda')) {
        function linkifyPersonalAgenda($text) {
            $text = preg_replace('!(https?://[a-z0-9_./?=&-]+)!i', '<a href="$1" target="_blank" style="color: inherit; text-decoration: underline; opacity: 0.9;">$1</a>', $text);
            return $text;
        }
    }
@endphp

    <div class="pa-card pa-card--note {{ $contrastClass }}"
         style="background-color: {{ $bgColor }}; --note-color: {{ $bgColor }};"
         draggable="true"
         data-id="{{ $note->id }}"
         data-search-content="{{ strtolower($note->title . ' ' . ($note->is_encrypted ? '' : (string)$note->content)) }}"
         data-note-data="{{ json_encode([
             'id' => $note->id,
             'title' => $note->title,
             'content' => $note->is_encrypted ? null : (string)$note->content,
             'priority' => $note->priority,
             'color' => $note->color,
             'folder_id' => $note->folder_id,
             'is_encrypted' => $note->is_encrypted,
             'is_archived' => $note->is_archived,
             'scheduled_date' => $note->scheduled_date ? $note->scheduled_date->format('Y-m-d') : null,
             'scheduled_time' => $note->scheduled_time,
             'attachments' => $note->attachments->map(fn($a) => [
                 'id' => $a->id,
                 'file_name' => $a->file_name,
                 'file_path' => asset('storage/' . $a->file_path),
                 'file_type' => $a->file_type
             ])
         ]) }}"
         @if($note->is_encrypted && !$isTrashed) onclick="decryptNote({{ $note->id }})" @endif>

        <div class="pa-card-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
            <div class="pa-card-date">{{ $note->created_at->format('d/m/Y') }}</div>
            <div class="pa-card-actions" style="display: flex; gap: 8px; opacity: 0; transition: opacity 0.2s;">
                @if($isTrashed)
                    <i class="fa-solid fa-rotate-left" title="Restaurar" onclick="event.stopPropagation(); restoreNote({{ $note->id }})"></i>
                @else
                    <i class="fa-regular fa-pen-to-square" title="Editar" onclick="event.stopPropagation(); editNote({{ $note->id }})"></i>
                    @if($note->is_archived)
                        <i class="fa-solid fa-box-open" title="Desarchivar" onclick="event.stopPropagation(); restoreNote({{ $note->id }})"></i>
                    @else
                        <i class="fa-solid fa-box-archive" title="Archivar" onclick="event.stopPropagation(); archiveNote({{ $note->id }})"></i>
                    @endif
                @endif
                <i class="fa-solid fa-trash-can" title="Eliminar" onclick="event.stopPropagation(); deleteNote({{ $note->id }}, {{ $isTrashed ? 'true' : 'false' }})"></i>
            </div>
        </div>

        <h4 class="pa-card-title" style="word-break: break-all;">{{ $note->title ?? ($note->is_encrypted ? 'Nota Cifrada' : 'Sin título') }}</h4>

        <div class="pa-card-body" style="font-weight: 500;">
            @if($note->is_encrypted && !$isTrashed)
                <div style="display: flex; flex-direction: column; align-items: center; gap: 12px; margin-top: 10px; padding: 10px 0;">
                    <div style="display: flex; align-items: center; gap: 8px; opacity: 0.7;">
                        <i class="fa-solid fa-lock"></i>
                        <span style="font-size: 0.75rem;">Cifrada</span>
                    </div>
                    <button class="pa-btn-unlock" onclick="event.stopPropagation(); decryptNote({{ $note->id }})">
                        <i class="fa-solid fa-key"></i> Ver nota
                    </button>
                </div>
            @else
                {!! nl2br(linkifyPersonalAgenda(e($note->content))) !!}
            @endif
        </div>

        @if(!$note->is_encrypted && $note->attachments->count() > 0)
            <div class="pa-card-attachments" style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 8px;">
                @foreach($note->attachments as $att)
                    <a href="{{ asset('storage/' . $att->file_path) }}" target="_blank" onclick="event.stopPropagation()"
                       title="{{ $att->file_name }}"
                       style="width: 32px; height: 32px; background: rgba(0,0,0,0.05); border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; color: inherit; text-decoration: none;">
                        @if($att->file_type === 'image')
                            <img src="{{ asset('storage/' . $att->file_path) }}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 4px;">
                        @else
                            <i class="fa-solid fa-file-lines" style="opacity: 0.7;"></i>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif

        <div class="pa-card-footer" style="padding-top: 12px; display: flex; align-items: center; margin-top: auto;">
            @if($note->folder)
                <span style="font-size: 0.65rem; background: rgba(0,0,0,0.05); padding: 1px 6px; border-radius: 6px;">
                    <i class="fa-solid {{ $note->folder->icon ?: 'fa-folder' }}"></i> {{ $note->folder->name }}
                </span>
            @endif
            @if($note->priority === 'high')
                <span style="margin-left: auto; color: #d93025; font-size: 0.7rem; font-weight: 700;">Alta</span>
            @endif
        </div>
    </div>
@empty
    <div style="grid-column: 1 / -1; text-align: center; padding: 30px 10px; color: #ccc; display: flex; flex-direction: column; align-items: center; gap: 8px;">
        <i class="fa-solid {{ $icon }}" style="font-size: 2.2rem; opacity: 0.2; margin-bottom: 2px;"></i>
        <div style="font-weight: 700; font-size: 0.9rem; color: #999;">Esta sección está vacía</div>
        <p style="font-size: 0.75rem; max-width: 250px; margin: 0; color: #bbb; opacity: 0.8;">No hay notas guardadas aquí actualmente.</p>
    </div>
@endforelse
