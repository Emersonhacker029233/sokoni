{{--
    Mobile fixed bottom nav — the same 5 primary destinations as the
    desktop header's nav, as icons, matching the Flutter app's own bottom
    nav so the two feel like one product (Part 3 nav-restructure spec).
    Hidden at lg+, where the header's own text nav takes over instead.
--}}
@php
    $items = [
        ['route' => 'web.home', 'pattern' => 'web.home', 'label' => __('site.nav_home')],
        ['route' => 'web.stores', 'pattern' => 'web.stores', 'label' => __('site.nav_stores')],
        ['route' => 'web.explore', 'pattern' => 'web.explore', 'label' => __('site.nav_explore')],
        ['route' => 'web.chats', 'pattern' => 'web.chats*|web.account.messages*', 'label' => __('site.nav_chats')],
        ['route' => 'web.profile', 'pattern' => 'web.profile*|web.account.dashboard|web.account.orders*|web.account.saved|web.account.settings|web.account.shop*', 'label' => __('site.nav_profile')],
    ];
@endphp
<nav aria-label="{{ __('site.a11y_primary_nav') }}" class="fixed inset-x-0 bottom-0 z-40 flex h-56 border-t border-sokoni-outline bg-white lg:hidden">
    @foreach ($items as $item)
        @php($isActive = request()->routeIs(...explode('|', $item['pattern'])))
        <a href="{{ route($item['route']) }}" class="relative flex flex-1 flex-col items-center justify-center gap-2 {{ $isActive ? 'text-sokoni-yellow' : 'text-sokoni-black/50' }}">
            @switch($item['route'])
                @case('web.home')
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="h-24 w-24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>
                    @break
                @case('web.stores')
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="h-24 w-24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21M3 10.5V21a.75.75 0 00.75.75H6a.75.75 0 00.75-.75v-4.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21a.75.75 0 00.75.75h2.25M21 10.5V21a.75.75 0 01-.75.75H18a.75.75 0 01-.75-.75V15M3 6l1.72-3.44a.75.75 0 01.67-.41h13.22a.75.75 0 01.67.41L21 6m-18 0v.75a2.25 2.25 0 002.25 2.25h.008a2.25 2.25 0 002.24-2.033M3 6h18m-18 0v.75a2.25 2.25 0 002.25 2.25h.008a2.25 2.25 0 002.24-2.033m8.005 0A2.25 2.25 0 0015.75 9h.008a2.25 2.25 0 002.242-2.033M21 6v.75a2.25 2.25 0 01-2.25 2.25h-.008a2.25 2.25 0 01-2.242-2.033" /></svg>
                    @break
                @case('web.explore')
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="h-24 w-24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42" /></svg>
                    @break
                @case('web.chats')
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="h-24 w-24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.24 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" /></svg>
                    {{-- A4 (tester feedback): now reads from the shared live
                         $store.messages count (see Alpine.data('messageNotifier')
                         in app.js) instead of a static per-page-load value, so
                         it updates without a navigation — same store the
                         desktop header's own Chats badge reads from. --}}
                    <span
                        x-show="$store.messages.count > 0"
                        x-text="$store.messages.count > 9 ? '9+' : $store.messages.count"
                        style="display: {{ ($unreadMessagesCount ?? 0) > 0 ? 'flex' : 'none' }}"
                        class="absolute right-[22%] top-2 flex h-14 min-w-14 items-center justify-center rounded-full bg-sokoni-danger px-2 text-[10px] font-semibold text-white"
                    >{{ ($unreadMessagesCount ?? 0) > 9 ? '9+' : $unreadMessagesCount }}</span>
                    @break
                @default
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="h-24 w-24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.964 0a9 9 0 10-11.964 0m11.964 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
            @endswitch
            <span class="text-[11px] font-medium">{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
