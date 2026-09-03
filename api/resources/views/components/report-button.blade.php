@props(['type', 'id'])

{{-- CLAUDE.md feature 11: "Report button on every product, shop and message" —
     previously only ever built in the app; the website had no report mechanism
     at all (tester feedback A3's audit). One shared component so every surface
     (product, shop, message) gets the identical reason picker → submit → real
     confirmation flow, reusing the same reason set the app already uses. --}}
@auth('web')
    <div x-data="{ open: false }" class="inline-block">
        <button type="button" @click="open = true" class="inline-flex items-center gap-4 text-xs text-sokoni-black/40 hover:text-sokoni-black/70">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-14 w-14"><path fill-rule="evenodd" d="M4 2a.75.75 0 01.75.75v.316a3.78 3.78 0 011.653-.379c.955 0 1.869.29 2.617.29.759 0 1.408-.192 1.925-.373a.75.75 0 01.995.708v6.376a.75.75 0 01-.5.707c-.548.194-1.322.454-2.42.454-.849 0-1.667-.29-2.377-.29-.71 0-1.339.146-1.893.353V17.25a.75.75 0 01-1.5 0V2.75A.75.75 0 014 2z" clip-rule="evenodd" /></svg>
            {{ __('site.report_action') }}
        </button>

        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-16" @click.self="open = false">
            <div class="w-full max-w-sm rounded-card bg-white p-24">
                <h3 class="text-base font-semibold">{{ __('site.report_title') }}</h3>
                <form action="{{ route('web.account.reports.store') }}" method="post" class="mt-16 space-y-12" x-data="{ reason: '' }">
                    @csrf
                    <input type="hidden" name="reportable_type" value="{{ $type }}">
                    <input type="hidden" name="reportable_id" value="{{ $id }}">
                    <input type="hidden" name="reason" :value="reason">
                    <div class="space-y-4">
                        @foreach ([
                            __('site.report_reason_not_business'),
                            __('site.report_reason_prohibited'),
                            __('site.report_reason_misleading'),
                            __('site.report_reason_spam'),
                        ] as $reasonOption)
                            <label class="flex items-center gap-8 rounded-chip border border-sokoni-outline p-8 text-sm has-[:checked]:border-sokoni-black">
                                <input type="radio" @change="reason = '{{ $reasonOption }}'" class="accent-sokoni-black">
                                {{ $reasonOption }}
                            </label>
                        @endforeach
                    </div>
                    <textarea name="note" rows="2" placeholder="{{ __('site.report_note_placeholder') }}" class="input-field w-full"></textarea>
                    <div class="flex gap-8">
                        <button type="button" @click="open = false" class="btn-secondary flex-1 text-sm">{{ __('site.report_cancel') }}</button>
                        <button type="submit" class="btn-primary flex-1 text-sm" :disabled="reason === ''" :class="reason === '' ? 'opacity-50' : ''">{{ __('site.report_submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endauth
