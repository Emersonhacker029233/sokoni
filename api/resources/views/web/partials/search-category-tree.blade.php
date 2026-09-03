{{-- Category + subcategory filter in the search sidebar (tester feedback C3):
     single-select drill-down (one category active at a time via ?category_id=),
     each with a real count reflecting every other currently-active filter. --}}
<div class="rounded-card border border-sokoni-outline p-16">
    <h3 class="text-sm font-semibold">{{ __('site.filter_category') }}</h3>
    <ul class="mt-8 space-y-2">
        <li>
            <a href="{{ request()->fullUrlWithQuery(['category_id' => null, 'page' => null]) }}"
               class="flex items-center justify-between rounded-chip px-8 py-6 text-sm {{ $filters->categoryId === null ? 'bg-sokoni-yellow/20 font-semibold' : 'hover:bg-sokoni-surface-alt' }}">
                {{ __('site.filter_category_all') }}
            </a>
        </li>
        @foreach ($categoryTree as $category)
            @php
                $childIds = $category->children->pluck('id');
                $isActiveParent = $filters->categoryId === $category->id;
                $isActiveChild = $filters->categoryId !== null && $childIds->contains($filters->categoryId);
                $isExpanded = $isActiveParent || $isActiveChild;
            @endphp
            <li>
                <a href="{{ request()->fullUrlWithQuery(['category_id' => $category->id, 'page' => null]) }}"
                   class="flex items-center justify-between rounded-chip px-8 py-6 text-sm {{ $isActiveParent ? 'bg-sokoni-yellow/20 font-semibold' : 'hover:bg-sokoni-surface-alt' }}">
                    <span>{{ $category->name(app()->getLocale()) }}</span>
                    <span class="text-xs text-sokoni-black/40">{{ $categoryCounts[$category->id] ?? 0 }}</span>
                </a>
                @if ($isExpanded && $category->children->isNotEmpty())
                    <ul class="ml-16 mt-2 space-y-2 border-l border-sokoni-outline pl-12">
                        @foreach ($category->children as $child)
                            <li>
                                <a href="{{ request()->fullUrlWithQuery(['category_id' => $child->id, 'page' => null]) }}"
                                   class="flex items-center justify-between rounded-chip px-8 py-4 text-xs {{ $filters->categoryId === $child->id ? 'bg-sokoni-yellow/20 font-semibold' : 'hover:bg-sokoni-surface-alt' }}">
                                    <span>{{ $child->name(app()->getLocale()) }}</span>
                                    <span class="text-sokoni-black/40">{{ $categoryCounts[$child->id] ?? 0 }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @endforeach
    </ul>
</div>
