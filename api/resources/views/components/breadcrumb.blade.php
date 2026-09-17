@props(['items'])
{{-- $items: array of ['label' => string, 'url' => string|null] — the last item (url null) is the current page. --}}

<nav aria-label="{{ __('site.a11y_breadcrumb') }}" class="mx-auto max-w-7xl px-16 py-12 text-xs text-sokoni-black/50 lg:px-24">
    <ol class="flex flex-wrap items-center gap-4" itemscope itemtype="https://schema.org/BreadcrumbList">
        @foreach ($items as $index => $item)
            <li class="flex items-center gap-4" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                @if ($item['url'] ?? null)
                    <a href="{{ $item['url'] }}" itemprop="item" class="hover:underline"><span itemprop="name">{{ $item['label'] }}</span></a>
                @else
                    <span aria-current="page" itemprop="name" class="text-sokoni-black">{{ $item['label'] }}</span>
                @endif
                <meta itemprop="position" content="{{ $index + 1 }}">
                @if (! $loop->last)
                    <span aria-hidden="true">/</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
