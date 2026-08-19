@php($user = auth('web')->user())

<nav class="mb-24 flex flex-wrap gap-8 border-b border-sokoni-outline pb-16">
    <a href="{{ route('web.account.dashboard') }}" class="chip {{ request()->routeIs('web.account.dashboard') ? 'chip-active' : '' }}">{{ __('site.nav_my_account') }}</a>
    <a href="{{ route('web.account.orders') }}" class="chip {{ request()->routeIs('web.account.orders*') ? 'chip-active' : '' }}">{{ __('site.account_orders') }}</a>
    <a href="{{ route('web.account.saved') }}" class="chip {{ request()->routeIs('web.account.saved') ? 'chip-active' : '' }}">{{ __('site.account_saved') }}</a>
    <a href="{{ route('web.account.messages') }}" class="chip {{ request()->routeIs('web.account.messages*') ? 'chip-active' : '' }}">{{ __('site.account_messages') }}</a>
    @if ($user->isSeller())
        <a href="{{ route('web.account.shop') }}" class="chip {{ request()->routeIs('web.account.shop*') ? 'chip-active' : '' }}">{{ __('site.account_shop') }}</a>
    @endif
    <a href="{{ route('web.account.settings') }}" class="chip {{ request()->routeIs('web.account.settings') ? 'chip-active' : '' }}">{{ __('site.account_settings') }}</a>
    <form action="{{ route('web.logout') }}" method="post" class="ml-auto">
        @csrf
        <button type="submit" class="chip">{{ __('site.nav_sign_out') }}</button>
    </form>
</nav>
