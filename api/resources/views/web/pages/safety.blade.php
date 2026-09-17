@extends('layouts.app')

@section('content')
<x-breadcrumb :items="[['label' => 'Sokoni', 'url' => route('web.home')], ['label' => __('site.footer_safety_link'), 'url' => null]]" />

<article class="mx-auto max-w-3xl px-16 pb-64 lg:px-24">
@if (app()->getLocale() === 'sw')
    <h1 class="text-2xl font-bold">Ushauri wa usalama</h1>
    <div class="prose prose-sm mt-24 max-w-none space-y-24 text-sokoni-black/80">
        <section>
            <h2 class="font-semibold text-sokoni-black">Kwa wanunuzi</h2>
            <ul class="mt-8 list-disc space-y-8 pl-20">
                <li>Nunua kutoka kwa wauzaji waliothibitishwa pekee (angalia alama ya njano ya uthibitisho) — wamekaguliwa dhidi ya kitambulisho chao cha taifa (NIDA) kabla ya kuanza kuonekana.</li>
                <li>Kutana mahali pa umma, penye watu, hasa kwa bidhaa za thamani kubwa. Duka la kimwili la muuzaji ni sehemu nzuri.</li>
                <li>Kagua bidhaa kabla ya kulipa — malipo yote kwa sasa ni baada ya kuletewa au wakati wa kuchukua, hivyo huna sababu ya kulipa mapema.</li>
                <li>Soma maoni ya wanunuzi wengine kabla ya kuagiza kutoka duka fulani.</li>
                <li>Kama kitu kinaonekana kizuri mno kuwa kweli — bei ya chini sana, muuzaji anasisitiza malipo ya haraka — ripoti bidhaa hiyo.</li>
                <li>Usiwahi kutuma malipo kabla ya kupokea bidhaa kupitia njia za nje ya programu.</li>
            </ul>
        </section>
        <section>
            <h2 class="font-semibold text-sokoni-black">Kwa wauzaji</h2>
            <ul class="mt-8 list-disc space-y-8 pl-20">
                <li>Weka picha halisi za bidhaa zako — sio picha za mtandaoni. Wanunuzi wanaamini zaidi maduka yenye picha halisi.</li>
                <li>Thibitisha malipo kabla ya kutoa bidhaa kwa mikono ya mnunuzi, hasa kwa uwasilishaji.</li>
                <li>Weka mawasiliano yako sahihi ili wanunuzi waweze kukufikia kwa urahisi.</li>
                <li>Ripoti tabia yoyote ya kutiliwa shaka kupitia kitufe cha ripoti kwenye ujumbe au wasifu wa mtumiaji.</li>
            </ul>
        </section>
        <p class="text-sm text-sokoni-black/50">Ukiona kitu kinachokutia wasiwasi, tumia kitufe cha "Ripoti" kwenye bidhaa, duka, au ujumbe — timu yetu inakagua kila ripoti.</p>
    </div>
@else
    <h1 class="text-2xl font-bold">Safety tips</h1>
    <div class="prose prose-sm mt-24 max-w-none space-y-24 text-sokoni-black/80">
        <section>
            <h2 class="font-semibold text-sokoni-black">For buyers</h2>
            <ul class="mt-8 list-disc space-y-8 pl-20">
                <li>Buy from verified sellers only (look for the yellow verified badge) — they've been checked against their national ID (NIDA) before going live.</li>
                <li>Meet in a public place with people around, especially for higher-value items. A seller's real shop location is a good choice.</li>
                <li>Inspect the item before paying — all payment today is cash on delivery or pay on pickup, so there's never a reason to pay upfront.</li>
                <li>Read other buyers' reviews of a shop before ordering.</li>
                <li>If something looks too good to be true — an unusually low price, a seller pushing you to pay quickly — report the listing.</li>
                <li>Never send payment before receiving the item through channels outside the app.</li>
            </ul>
        </section>
        <section>
            <h2 class="font-semibold text-sokoni-black">For sellers</h2>
            <ul class="mt-8 list-disc space-y-8 pl-20">
                <li>Use real photos of your actual products, not stock images — buyers trust shops with genuine photos more.</li>
                <li>Confirm payment before handing over goods, especially for delivery orders.</li>
                <li>Keep your contact details accurate so buyers can reach you easily.</li>
                <li>Report any suspicious behaviour using the report button on a message or user profile.</li>
            </ul>
        </section>
        <p class="text-sm text-sokoni-black/50">If something feels wrong, use the "Report" button on a product, shop, or message — our team reviews every report.</p>
    </div>
@endif
</article>
@endsection
