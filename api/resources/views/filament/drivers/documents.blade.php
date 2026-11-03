@php
    /** @var \App\Models\DriverProfile $driver */
    $driver = $getRecord();
    $cards = \App\Support\DriverDocuments::forAdmin($driver);
@endphp

{{-- Driver identity documents, side by side. Every URL here is the
     admin-only DriverDocumentController route, never a public or signed
     storage URL. --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @foreach ($cards as $card)
        <div wire:key="driver-doc-{{ $driver->id }}-{{ $card['key'] }}" class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5">
            <div class="mb-2 flex items-center justify-between gap-2">
                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $card['label'] }}</p>
                @if ($card['present'])
                    <a href="{{ $card['download_url'] }}" class="fi-link inline-flex items-center gap-1 text-xs font-medium text-primary-600 dark:text-primary-400">
                        <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-4 w-4" />
                        Download
                    </a>
                @endif
            </div>

            @if (! $card['present'])
                <div class="flex h-56 items-center justify-center rounded-lg border border-dashed border-gray-300 text-sm text-gray-500 dark:border-white/20 dark:text-gray-400">
                    Not uploaded
                </div>
            @elseif ($card['is_pdf'])
                <a href="{{ $card['url'] }}" target="_blank" rel="noopener" class="flex h-56 flex-col items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-white/10 dark:bg-gray-900 dark:text-gray-200">
                    <x-filament::icon icon="heroicon-o-document-text" class="h-10 w-10 text-gray-400" />
                    Open PDF
                </a>
            @else
                <button
                    type="button"
                    x-on:click="$dispatch('open-modal', { id: 'driver-doc-{{ $driver->id }}-{{ $card['key'] }}' })"
                    class="block w-full overflow-hidden rounded-lg ring-1 ring-gray-950/10 dark:ring-white/20"
                >
                    <img src="{{ $card['url'] }}" alt="{{ $card['label'] }}" class="h-56 w-full bg-white object-contain dark:bg-gray-900" loading="lazy">
                </button>
                <x-filament::modal id="driver-doc-{{ $driver->id }}-{{ $card['key'] }}" width="5xl">
                    <x-slot name="heading">{{ $card['label'] }}</x-slot>
                    <img src="{{ $card['url'] }}" alt="{{ $card['label'] }}" class="w-full rounded-lg">
                </x-filament::modal>
            @endif
        </div>
    @endforeach
</div>
