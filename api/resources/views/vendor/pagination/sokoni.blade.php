@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('site.a11y_pagination') }}" class="flex items-center justify-center gap-4 py-24">
        @if ($paginator->onFirstPage())
            <span class="chip pointer-events-none opacity-40">&laquo;</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="chip">&laquo;</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-8 text-sm text-sokoni-black/40">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="chip chip-active">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="chip">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="chip">&raquo;</a>
        @else
            <span class="chip pointer-events-none opacity-40">&raquo;</span>
        @endif
    </nav>
@endif
