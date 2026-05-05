{{-- Toolbar + rejilla de fichas (reutilizado en partial mensual y en carga AJAX fichas_only). --}}
<div class="agenda-cal-fichas-toolbar">
    <button type="button" class="agenda-btn agenda-btn-secondary agenda-cal-fichas-print-btn" data-agenda-cal-print-fichas>
        <i class="fa-solid fa-print" aria-hidden="true"></i>
        Imprimir / PDF
    </button>
</div>
<div class="agenda-cal-cards">
    @forelse ($cards as $card)
        @php
            $fichaKind = $card['kind'] ?? 'agenda';
            $fichaKindLabel = $card['kind_label'] ?? match ($fichaKind) {
                'pre_gira' => 'Pre-gira',
                'gira' => 'Gira',
                'personalizada' => 'Ficha personalizada',
                default => 'Agenda',
            };
            $fichaBg = $fichaKind === 'personalizada' ? (string) ($card['ficha_bg'] ?? '') : '';
            $fichaBgFile = $fichaBg !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $fichaBg) && File::exists(public_path('images/Texturas/'.$fichaBg.'.png')) ? $fichaBg : '';
            $fichaBgTone = str_contains(strtolower($fichaBgFile), 'blanco') ? 'blanco' : 'color';
            $fichaBgStyle = $fichaBgFile !== '' ? "background-image: url('".asset('images/Texturas/'.$fichaBgFile.'.png')."');" : '';
            $fichaShowUrl = route('agenda.show', ['agenda' => $card['agenda_id'], 'return' => $previewReturn, 'preview' => 'ficha']);
        @endphp
        <article class="agenda-cal-card">
            <div class="agenda-cal-card-head agenda-cal-card-head--{{ $fichaKind }}{{ $fichaBgTone === 'blanco' ? ' agenda-cal-card-head--bg-blanco' : '' }}" style="{{ $fichaBgStyle }}">
                <div class="agenda-cal-card-head-inner">
                    <span class="agenda-cal-card-eyebrow">{{ $fichaKindLabel }}</span>
                </div>
                <div class="agenda-cal-card-head-date" aria-hidden="true">
                    <span class="agenda-cal-card-head-daynum">{{ $card['badge_day'] }}</span>
                    <span class="agenda-cal-card-head-dateline">{{ e($card['month_year_label'] ?? '') }}</span>
                    @if (! empty($card['hora_ficha']))
                        <span class="agenda-cal-card-head-time">{{ e($card['hora_ficha']) }}</span>
                    @endif
                </div>
            </div>
            <div class="agenda-cal-card-body">
                <h3 class="agenda-cal-card-title">{{ e($card['title']) }}</h3>
                @if(!empty($card['lugar']) || !empty($card['descripcion']))
                    <div class="agenda-cal-card-loc-row">
                        <span class="agenda-cal-card-loc-ico" title="Ubicación">
                            <span class="agenda-cal-sr-only">Ubicación</span>
                            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                        </span>
                        <div class="agenda-cal-card-detail">
                            @if(!empty($card['lugar']))
                                <p class="agenda-cal-card-address">{{ e($card['lugar']) }}</p>
                            @endif
                            @if(!empty($card['descripcion']))
                                <p class="agenda-cal-card-desc agenda-cal-card-desc--clamped">{{ e($card['descripcion']) }}</p>
                            @endif
                        </div>
                    </div>
                @endif
                <footer class="agenda-cal-card-foot {{ empty($card['aforo_label']) ? 'agenda-cal-card-foot--empty' : '' }}">
                    @if(!empty($card['aforo_label']))
                        <p class="agenda-cal-card-aforo">{{ e($card['aforo_label']) }}</p>
                    @endif
                </footer>
            </div>
            <a
                href="{{ $fichaShowUrl }}"
                class="agenda-cal-card-stretch"
                aria-label="Abrir ficha: {{ e($card['title']) }}"
            ></a>
        </article>
    @empty
        <p class="agenda-cal-empty">No hay eventos en este mes con los filtros actuales.</p>
    @endforelse
</div>
