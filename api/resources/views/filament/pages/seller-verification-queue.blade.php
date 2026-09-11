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
                    {{-- D2 (tester feedback): "Approve/Reject prominent at
                         top, not buried" — these used to sit in a footer
                         bar below both columns of content, so a reviewer
                         had to read (or scroll past) everything below
                         before finding them. Now in the same header row as
                         the shop name, the first thing on the card. --}}
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 px-6 py-4 dark:border-white/10">
                        <div class="min-w-0">
                            <h3 class="truncate text-base font-semibold text-gray-950 dark:text-white">
                                {{ $seller->shop_name }}
                                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">{{ '@'.$seller->handle }}</span>
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $seller->user->name }} &middot; {{ $seller->category?->name_en ?? 'No category' }}
                                &middot; Waiting {{ $seller->created_at->diffForHumans(null, true) }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
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

                    {{-- D2: "two columns desktop — shop details left,
                         evidence right." After Part A there's no document
                         evidence left to show at all (NIDA photo/licence
                         both removed entirely) — the NIDA number itself is
                         the whole of "evidence" now, so it anchors the
                         right column; business/location details anchor
                         the left. Room for images only if any ever remain
                         (none do today), capped ~300px with a lightbox —
                         see the shop logo below, the one image this page
                         still shows. --}}
                    <div class="grid grid-cols-1 gap-6 p-6 md:grid-cols-2">
                        <div class="space-y-4">
                            @if ($seller->logo)
                                <div>
                                    <p class="mb-2 text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Shop logo</p>
                                    <button
                                        type="button"
                                        x-on:click="$dispatch('open-modal', { id: 'seller-logo-{{ $seller->id }}' })"
                                        class="block overflow-hidden rounded-lg ring-1 ring-gray-950/10 dark:ring-white/20"
                                    >
                                        <img src="{{ $seller->logo }}" alt="{{ $seller->shop_name }} logo" class="h-[120px] w-[120px] object-cover" loading="lazy">
                                    </button>
                                    <x-filament::modal id="seller-logo-{{ $seller->id }}" width="lg">
                                        <img src="{{ $seller->logo }}" alt="{{ $seller->shop_name }} logo" class="w-full rounded-lg">
                                    </x-filament::modal>
                                </div>
                            @endif

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

                            <div>
                                <p class="mb-2 text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Location</p>
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
                        </div>

                        <div>
                            {{-- D2: "NIDA number as large copyable text" —
                                 this used to be a plain small line of text.
                                 A reviewer typically needs to paste this
                                 into a separate lookup, so it's sized to
                                 read at a glance and copyable in one click. --}}
                            <p class="mb-2 text-xs font-medium uppercase text-gray-500 dark:text-gray-400">NIDA number (basis of verification)</p>
                            @if ($seller->nida_number)
                                <div
                                    x-data="{ copied: false }"
                                    x-on:click="
                                        navigator.clipboard.writeText('{{ $seller->nida_number }}');
                                        copied = true;
                                        setTimeout(() => copied = false, 1500);
                                    "
                                    class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 transition hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10"
                                >
                                    <span class="select-all font-mono text-2xl font-semibold tracking-wide text-gray-950 dark:text-white">{{ $seller->nida_number }}</span>
                                    <x-filament::icon
                                        x-show="! copied"
                                        icon="heroicon-o-clipboard-document"
                                        class="h-5 w-5 shrink-0 text-gray-400"
                                    />
                                    <x-filament::icon
                                        x-cloak
                                        x-show="copied"
                                        icon="heroicon-o-check"
                                        class="h-5 w-5 shrink-0 text-success-500"
                                    />
                                </div>
                            @else
                                <p class="text-sm text-gray-400">Not provided</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
