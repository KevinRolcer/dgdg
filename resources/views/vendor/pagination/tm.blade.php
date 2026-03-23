{{-- Paginación módulos temporales: firstItem/lastItem son null si la página actual no trae ítems --}}
@if ($paginator->hasPages())
    <nav class="tm-paginator" aria-label="Paginación">
        <p class="tm-paginator-info">
            @php
                $total = (int) $paginator->total();
                $n = $paginator->count();
                $first = $n > 0 ? (int) $paginator->firstItem() : null;
                $last = $n > 0 ? (int) $paginator->lastItem() : null;
            @endphp
            @if ($total === 0)
                Sin registros.
            @elseif ($n === 0)
                Esta página está vacía; vuelve a la <a href="{{ $paginator->url(1) }}">primera página</a>.
                (Total en listado: <strong>{{ $total }}</strong>)
            @else
                Mostrando <strong>{{ $first }}</strong>–<strong>{{ $last }}</strong>
                de <strong>{{ $total }}</strong> {{ $total === 1 ? 'elemento' : 'elementos' }}
            @endif
        </p>
        <ul class="tm-paginator-list">
            @if ($paginator->onFirstPage())
                <li><span class="tm-paginator-btn is-disabled" aria-disabled="true">Anterior</span></li>
            @else
                <li><a class="tm-paginator-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a></li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="tm-paginator-ellipsis">{{ $element }}</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li><span class="tm-paginator-btn is-active" aria-current="page">{{ $page }}</span></li>
                        @else
                            <li><a class="tm-paginator-btn" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li><a class="tm-paginator-btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente</a></li>
            @else
                <li><span class="tm-paginator-btn is-disabled" aria-disabled="true">Siguiente</span></li>
            @endif
        </ul>
    </nav>
@endif
