@extends('layouts.app')

@section('content')
<x-breadcrumb :items="[['label' => 'Sokoni', 'url' => route('web.home')], ['label' => 'How it works', 'url' => null]]" />

<article class="mx-auto max-w-3xl px-16 pb-64 lg:px-24">
@if (app()->getLocale() === 'sw')
    <h1 class="text-2xl font-bold">Sokoni inafanyaje kazi</h1>
    <div class="mt-24 space-y-24">
        <div><h2 class="font-semibold">1. Vinjari bila akaunti</h2><p class="mt-4 text-sm text-sokoni-black/70">Tafuta bidhaa, linganisha bei, na tazama maduka bila kuhitaji kujisajili.</p></div>
        <div><h2 class="font-semibold">2. Wasiliana na muuzaji</h2><p class="mt-4 text-sm text-sokoni-black/70">Tuma ujumbe, piga simu, au tumia WhatsApp — kwa akaunti ya bure inayochukua sekunde chache kuunda.</p></div>
        <div><h2 class="font-semibold">3. Agiza kwa uhakika</h2><p class="mt-4 text-sm text-sokoni-black/70">Chagua kuchukua au kuletewa, lipa unapopokea bidhaa. Fuatilia hali ya oda yako hadi imekamilika.</p></div>
        <div><h2 class="font-semibold">4. Toa maoni</h2><p class="mt-4 text-sm text-sokoni-black/70">Baada ya oda kukamilika, toa tathmini ya nyota kusaidia wanunuzi wengine.</p></div>
    </div>
@else
    <h1 class="text-2xl font-bold">How Sokoni works</h1>
    <div class="mt-24 space-y-24">
        <div><h2 class="font-semibold">1. Browse without an account</h2><p class="mt-4 text-sm text-sokoni-black/70">Search products, compare prices, and look through shops with nothing to sign up for.</p></div>
        <div><h2 class="font-semibold">2. Contact the seller</h2><p class="mt-4 text-sm text-sokoni-black/70">Message, call, or WhatsApp — with a free account that takes seconds to create.</p></div>
        <div><h2 class="font-semibold">3. Order with confidence</h2><p class="mt-4 text-sm text-sokoni-black/70">Choose pickup or delivery, pay when you receive the item. Track your order's status until it's complete.</p></div>
        <div><h2 class="font-semibold">4. Leave a review</h2><p class="mt-4 text-sm text-sokoni-black/70">Once your order is complete, leave a star rating to help the next buyer.</p></div>
    </div>
@endif
</article>
@endsection
