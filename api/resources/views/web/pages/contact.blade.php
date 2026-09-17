@extends('layouts.app')

@section('content')
<x-breadcrumb :items="[['label' => 'Sokoni', 'url' => route('web.home')], ['label' => __('site.footer_contact_link'), 'url' => null]]" />

<article class="mx-auto max-w-2xl px-16 pb-64 lg:px-24">
    <h1 class="text-2xl font-bold">{{ app()->getLocale() === 'sw' ? 'Wasiliana nasi' : 'Contact us' }}</h1>
    <div class="mt-24 space-y-16 text-sm text-sokoni-black/80">
        <p>{{ app()->getLocale() === 'sw' ? 'Kwa msaada wowote kuhusu akaunti yako, oda, au ripoti, tuandikie:' : 'For anything about your account, an order, or a report, reach us at:' }}</p>
        <a href="mailto:{{ config('sokoni.support_email') }}" class="btn-secondary inline-flex">{{ config('sokoni.support_email') }}</a>
        <p class="text-sokoni-black/60">{{ app()->getLocale() === 'sw' ? 'Dar es Salaam, Tanzania' : 'Dar es Salaam, Tanzania' }}</p>
    </div>
</article>
@endsection
