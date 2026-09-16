@php($user = auth('web')->user())
@php($linkedAccounts = app(\App\Services\Auth\WebAccountSwitcher::class)->linkedAccounts(request()))

<nav class="mb-24 flex flex-wrap items-center gap-8 border-b border-sokoni-outline pb-16">
    <a href="{{ route('web.account.dashboard') }}" class="chip {{ request()->routeIs('web.account.dashboard') ? 'chip-active' : '' }}">{{ __('site.nav_my_account') }}</a>
    <a href="{{ route('web.account.orders') }}" class="chip {{ request()->routeIs('web.account.orders*') ? 'chip-active' : '' }}">{{ __('site.account_orders') }}</a>
    <a href="{{ route('web.account.saved') }}" class="chip {{ request()->routeIs('web.account.saved') ? 'chip-active' : '' }}">{{ __('site.account_saved') }}</a>
    <a href="{{ route('web.account.messages') }}" class="chip {{ request()->routeIs('web.account.messages*') ? 'chip-active' : '' }}">{{ __('site.account_messages') }}</a>
    <a href="{{ route('web.account.notifications') }}" class="chip {{ request()->routeIs('web.account.notifications') ? 'chip-active' : '' }}">{{ __('site.account_notifications') }}</a>
    @if ($user->isSeller())
        <a href="{{ route('web.account.shop') }}" class="chip {{ request()->routeIs('web.account.shop*') ? 'chip-active' : '' }}">{{ __('site.account_shop') }}</a>
    @else
        <a href="{{ route('web.account.shop.register') }}" class="chip {{ request()->routeIs('web.account.shop.register') ? 'chip-active' : '' }}">{{ __('site.nav_start_selling') }}</a>
    @endif
    <a href="{{ route('web.account.settings') }}" class="chip {{ request()->routeIs('web.account.settings') ? 'chip-active' : '' }}">{{ __('site.account_settings') }}</a>

    {{-- Part 5 (client feedback): "Instagram-style account switching...
         the same behaviour using multiple sessions" — every account this
         browser has signed into without fully logging out, plus "Add
         account". See WebAccountSwitcher's own docblock for why the
         website's version of this carries none of the app's
         cart/drafts-leakage risk: nothing here is per-user PHP session
         state, it's all DB relations keyed by whichever user
         `Auth::loginUsingId()` makes active. --}}
    <div x-data="{ open: false }" class="relative ml-auto">
        <button type="button" @click="open = !open" @click.outside="open = false" class="chip flex items-center gap-4">
            {{ $user->name }}
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5"/></svg>
        </button>
        <div x-show="open" x-cloak x-transition class="absolute right-0 z-10 mt-4 w-64 rounded-card border border-sokoni-outline bg-sokoni-surface p-8 shadow-lg">
            <p class="px-8 py-4 text-xs font-medium uppercase tracking-wide text-sokoni-black/40">{{ __('site.account_switch_accounts') }}</p>
            @foreach ($linkedAccounts as $account)
                <div class="flex items-center gap-8 rounded-chip px-8 py-8 {{ $account->id === $user->id ? '' : 'hover:bg-sokoni-surface-alt' }}">
                    <div class="flex h-32 w-32 shrink-0 items-center justify-center rounded-full bg-sokoni-surface-alt text-sm font-medium">
                        {{ mb_strtoupper(mb_substr($account->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $account->name }}</p>
                        @if ($account->sellerProfile?->handle)
                            <p class="truncate text-xs text-sokoni-black/40">@{{ $account->sellerProfile->handle }}</p>
                        @endif
                    </div>
                    @if ($account->id === $user->id)
                        <span class="shrink-0 rounded-chip bg-sokoni-yellow px-8 py-2 text-xs font-medium">{{ __('site.account_switch_current') }}</span>
                    @else
                        <form action="{{ route('web.account.switch', $account) }}" method="post">
                            @csrf
                            <button type="submit" class="text-xs font-medium underline">{{ __('site.nav_sign_in') }}</button>
                        </form>
                    @endif
                </div>
            @endforeach
            <a href="{{ route('web.login', ['add_account' => 1, 'redirect' => 'profile']) }}" class="mt-4 flex items-center gap-8 rounded-chip px-8 py-8 text-sm font-medium hover:bg-sokoni-surface-alt">
                <span class="flex h-32 w-32 shrink-0 items-center justify-center rounded-full border border-dashed border-sokoni-outline">+</span>
                {{ __('site.account_add_account') }}
            </a>
            <form action="{{ route('web.logout') }}" method="post" class="mt-4 border-t border-sokoni-outline pt-4">
                @csrf
                <button type="submit" class="w-full rounded-chip px-8 py-8 text-left text-sm font-medium hover:bg-sokoni-surface-alt">{{ __('site.nav_sign_out') }}</button>
            </form>
        </div>
    </div>
</nav>
