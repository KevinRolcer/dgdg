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
            $fichaKindLabel = match ($fichaKind) {
                'pre_gira' => 'Pre-gira',
                'gira' => 'Gira',
                default => 'Agenda',
            };
            $fichaShowUrl = route('agenda.show', ['agenda' => $card['agenda_id'], 'return' => $previewReturn]);
        @endphp
        <article class="agenda-cal-card">
            <div class="agenda-cal-card-head agenda-cal-card-head--{{ $fichaKind }}">
                <div class="agenda-cal-card-head-inner">
                    <span class="agenda-cal-card-eyebrow">{{ $fichaKindLabel }}</span>
                </div>
                <div class="agenda-cal-card-head-date" aria-hidden="true">
                    <span class="agenda-cal-card-head-daynum">{{ $card['badge_day'] }}</span>
                    <span class="agenda-cal-card-head-dateline">{{ e($card['month_year_label'] ?? '') }}</span>
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
