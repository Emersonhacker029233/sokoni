<x-filament-panels::page>
    <div class="fi-section rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-gray-900">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div>
                <label class="text-sm font-medium text-gray-950 dark:text-white">Audience</label>
                <select wire:model.live="audience" class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/20">
                    <option value="verified_sellers">All verified sellers</option>
                    <option value="buyers">All buyers</option>
                    <option value="all">Everyone</option>
                </select>

                <label class="mt-4 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" wire:model.live="consentOnly" class="fi-checkbox-input rounded">
                    Consenting users only (marketing_consent)
                </label>

                <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-800">
                    <p><span class="font-medium text-gray-950 dark:text-white">{{ $this->recipientCount() }}</span> recipients match this audience.</p>
                    <p class="mt-1 text-gray-500 dark:text-gray-400">Cap: {{ $this->maxBlastSize() }} per blast.</p>
                </div>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-950 dark:text-white">Message</label>
                <textarea wire:model.live="message" rows="6" class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/20" placeholder="e.g. New Kids category is live on Sokoni — check it out!"></textarea>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    {{ $this->characterCount() }} characters &middot; {{ $this->messageUnits() }} SMS unit(s) each &middot;
                    ~{{ $this->estimatedTotalUnits() }} total units for this audience
                </p>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-gray-200 pt-4 dark:border-white/10">
            @if (! $confirming)
                <x-filament::button wire:click="confirm">
                    Preview &amp; confirm
                </x-filament::button>
            @else
                <div class="w-full rounded-lg bg-warning-50 p-4 text-sm text-warning-800 dark:bg-warning-500/10 dark:text-warning-400">
                    <p class="font-medium">Send to {{ $this->recipientCount() }} recipients now?</p>
                    <p class="mt-1">Estimated {{ $this->estimatedTotalUnits() }} SMS units. This queues the blast — it goes out over the next few minutes, not instantly.</p>
                    <div class="mt-3 flex gap-2">
                        <x-filament::button color="success" wire:click="send" wire:confirm="Send this message to {{ $this->recipientCount() }} recipients?">
                            Send now
                        </x-filament::button>
                        <x-filament::button color="gray" outlined wire:click="cancel">
                            Cancel
                        </x-filament::button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="fi-section mt-6 rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-white/10">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Recent blasts</h3>
        </div>

        @php $recent = $this->getRecentBlasts(); @endphp

        @if ($recent->isEmpty())
            <p class="p-6 text-sm text-gray-500 dark:text-gray-400">No blasts sent yet.</p>
        @else
            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($recent as $blast)
                    <div wire:key="blast-{{ $blast->id }}" class="p-6">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-sm text-gray-950 dark:text-white">{{ Str::limit($blast->message, 80) }}</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ ucfirst(str_replace('_', ' ', $blast->audience)) }}
                                    @if ($blast->consent_only) &middot; consenting only @endif
                                    &middot; by {{ $blast->sender?->name ?? 'Unknown' }}
                                    &middot; {{ $blast->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <x-filament::badge :color="match ($blast->status) { 'completed' => 'success', 'processing' => 'warning', default => 'gray' }">
                                {{ ucfirst($blast->status) }}
                            </x-filament::badge>
                        </div>
                        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                            {{ $blast->total_count }} total &middot;
                            <span class="text-success-600 dark:text-success-400">{{ $blast->sent_count }} sent</span> &middot;
                            <span class="text-danger-600 dark:text-danger-400">{{ $blast->failed_count }} failed</span>
                        </p>
                        @if ($blast->failed_count > 0)
                            <button type="button" wire:click="toggleFailures({{ $blast->id }})" class="mt-2 text-xs font-medium text-primary-600 underline dark:text-primary-400">
                                {{ ($showingFailures[$blast->id] ?? false) ? 'Hide failures' : 'View failures' }}
                            </button>
                            @if ($showingFailures[$blast->id] ?? false)
                                <ul class="mt-2 space-y-1 text-xs text-gray-600 dark:text-gray-400">
                                    @foreach ($this->failuresFor($blast->id) as $failure)
                                        <li>{{ $failure->user?->name ?? 'Unknown' }} ({{ $failure->phone }}) — {{ $failure->error }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
