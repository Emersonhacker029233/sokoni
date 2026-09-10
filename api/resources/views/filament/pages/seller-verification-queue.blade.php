<x-filament-panels::page>
    @php $pending = $this->getPending(); @endphp

    @if ($pending->isEmpty())
        <div class="fi-section rounded-xl border border-gray-200 bg-white p-12 text-center dark:border-white/10 dark:bg-gray-900">
            <x-filament::icon icon="heroicon-o-check-badge" class="mx-auto h-10 w-10 text-gray-400" />
            <h3 class="mt-4 text-base font-semibold text-gray-950 dark:text-white">Queue is empty</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No sellers are waiting on verification right now.</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach ($pending as $seller)
                <div wire:key="seller-{{ $seller->id }}" class="fi-section rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-6 py-4 dark:border-white/10">
                        <div>
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                                {{ $seller->shop_name }}
                                {{-- Same fix as the public shop page (tester feedback A1) — `@{{ }}` is
                                     Blade's literal-braces escape, not an "@" prefix; it always printed
                                     the raw template text instead of the handle. --}}
                                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">{{ '@'.$seller->handle }}</span>
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $seller->user->name }} &middot; {{ $seller->category?->name_en ?? 'No category' }}
                            </p>
                        </div>
                        <x-filament::badge color="warning">
                            Waiting {{ $seller->created_at->diffForHumans(null, true) }}
                        </x-filament::badge>
                    </div>

                    <div class="grid grid-cols-1 gap-6 p-6 md:grid-cols-2">
                        <div>
                            {{-- B1/B2 (tester feedback): the NIDA photo and business licence
                                 evidence are gone entirely — the typed NIDA number alone is
                                 now the whole basis of verification. --}}
                            <p class="mb-2 text-xs font-medium uppercase text-gray-500 dark:text-gray-400">NIDA number (basis of verification)</p>
                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $seller->nida_number ?? '—' }}</p>

                            <p class="mb-2 mt-4 text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Location</p>
                            @if ($seller->hasLocation())
                                <a href="https://www.google.com/maps?q={{ $seller->lat }},{{ $seller->lng }}" target="_blank" rel="noopener" class="fi-link inline-flex items-center gap-1 text-sm font-medium text-primary-600 dark:text-primary-400">
                                    <x-filament::icon icon="heroicon-o-map-pin" class="h-4 w-4" />
                                    Open in Google Maps
                                </a>
                            @else
                                <p class="text-sm text-gray-400">Not set</p>
                            @endif
                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $seller->address ?? '—' }}</p>
                        </div>

                        <div>
                            <p class="mb-2 text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Business details</p>
                            <dl class="space-y-1 text-sm">
                                <div><dt class="inline text-gray-500 dark:text-gray-400">Owner phone:</dt> <dd class="inline text-gray-700 dark:text-gray-300">{{ $seller->user->phone ?? '—' }}</dd></div>
                                <div><dt class="inline text-gray-500 dark:text-gray-400">WhatsApp:</dt> <dd class="inline text-gray-700 dark:text-gray-300">{{ $seller->whatsapp ?? '—' }}</dd></div>
                                <div><dt class="inline text-gray-500 dark:text-gray-400">Region:</dt> <dd class="inline text-gray-700 dark:text-gray-300">{{ $seller->region ?? '—' }}</dd></div>
                                <div><dt class="inline text-gray-500 dark:text-gray-400">District:</dt> <dd class="inline text-gray-700 dark:text-gray-300">{{ $seller->district ?? '—' }}</dd></div>
                            </dl>
                            @if ($seller->bio)
                                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $seller->bio }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 px-6 py-4 dark:border-white/10">
                        <x-filament::button color="success" wire:click="approve({{ $seller->id }})" wire:confirm="Verify {{ $seller->shop_name }}?">
                            Verify
                        </x-filament::button>

                        @if (! empty($rejecting[$seller->id]))
                            <div class="flex flex-1 flex-wrap items-center gap-2">
                                <input
                                    type="text"
                                    wire:model="rejectReasons.{{ $seller->id }}"
                                    placeholder="Reason for rejection (required)"
                                    class="fi-input block min-w-64 flex-1 rounded-lg border-none bg-white px-3 py-1.5 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/20"
                                />
                                <x-filament::button color="danger" wire:click="reject({{ $seller->id }})">
                                    Confirm reject
                                </x-filament::button>
                                <x-filament::button color="gray" outlined wire:click="cancelReject({{ $seller->id }})">
                                    Cancel
                                </x-filament::button>
                            </div>
                        @else
                            <x-filament::button color="danger" outlined wire:click="startReject({{ $seller->id }})">
                                Reject
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
