@extends('layouts.app')

@section('content')
<section class="border-b border-sokoni-outline bg-sokoni-surface-alt/40 px-16 py-48 text-center lg:px-24">
    @if (app()->getLocale() === 'sw')
        <h1 class="text-3xl font-extrabold">Anza kuuza kwenye Sokoni</h1>
        <p class="mx-auto mt-12 max-w-xl text-sokoni-black/60">Fikia wanunuzi kote Tanzania. Wasifu wa duka uliothibitishwa, hakuna ada ya kuorodhesha, malipo baada ya kuletewa.</p>
    @else
        <h1 class="text-3xl font-extrabold">Start selling on Sokoni</h1>
        <p class="mx-auto mt-12 max-w-xl text-sokoni-black/60">Reach buyers across Tanzania. A verified shop profile, no listing fees, cash on delivery.</p>
    @endif
    <a href="{{ route('web.login') }}" class="btn-primary mt-24 inline-flex px-24 py-14 text-base">
        {{ app()->getLocale() === 'sw' ? 'Anza sasa' : 'Get started' }}
    </a>
</section>

<section class="mx-auto max-w-5xl px-16 py-40 lg:px-24">
    <div class="grid gap-24 sm:grid-cols-3">
        @if (app()->getLocale() === 'sw')
            <div><h2 class="font-semibold">1. Sajili duka lako</h2><p class="mt-8 text-sm text-sokoni-black/70">Jina la duka, kundi, na maelezo — dakika chache tu.</p></div>
            <div><h2 class="font-semibold">2. Thibitishwa</h2><p class="mt-8 text-sm text-sokoni-black/70">Tuma NIDA na leseni ya biashara. Timu yetu inakagua kwa mikono.</p></div>
            <div><h2 class="font-semibold">3. Anza kuuza</h2><p class="mt-8 text-sm text-sokoni-black/70">Ongeza bidhaa na picha, pokea ujumbe na oda moja kwa moja.</p></div>
        @else
            <div><h2 class="font-semibold">1. Register your shop</h2><p class="mt-8 text-sm text-sokoni-black/70">Shop name, category, and a description — takes a few minutes.</p></div>
            <div><h2 class="font-semibold">2. Get verified</h2><p class="mt-8 text-sm text-sokoni-black/70">Submit your NIDA number and business licence. Our team reviews it by hand.</p></div>
            <div><h2 class="font-semibold">3. Start selling</h2><p class="mt-8 text-sm text-sokoni-black/70">Add products and photos, receive messages and orders directly.</p></div>
        @endif
    </div>
</section>
@endsection
