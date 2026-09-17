@extends('layouts.app')

@section('content')
<x-breadcrumb :items="[['label' => 'Sokoni', 'url' => route('web.home')], ['label' => __('site.footer_about_link'), 'url' => null]]" />

<article class="mx-auto max-w-3xl px-16 pb-64 lg:px-24">
@if (app()->getLocale() === 'sw')
    <h1 class="text-2xl font-bold">Kuhusu Sokoni</h1>
    <div class="prose prose-sm mt-24 max-w-none space-y-16 text-sokoni-black/80">
        <p>Sokoni ni soko la Tanzania linalounganisha wanunuzi na wauzaji waliothibitishwa, kuanzia Dar es Salaam. Tuliamini kwamba kununua na kuuza mtandaoni Tanzania kunapaswa kuhisi kama duka la kweli — picha halisi, watu halisi, na uwazi kuhusu wauzaji unaowaamini.</p>
        <p>Kila muuzaji kwenye Sokoni hupitia uthibitisho wa kibinafsi kabla bidhaa zake hazijaonekana hadharani — namba ya NIDA hukaguliwa na timu yetu, sio kanuni ya kompyuta pekee. Hii ndiyo ulinzi wetu mkuu dhidi ya maduka ya udanganyifu.</p>
        <p>Kutoka Dar es Salaam hadi miji na mikoa mingine nchini, Sokoni ipo kuwasaidia wauzaji wa Kitanzania kuwafikia wanunuzi wengi zaidi, na kuwasaidia wanunuzi kupata wanachohitaji karibu na walipo.</p>
    </div>
@else
    <h1 class="text-2xl font-bold">About Sokoni</h1>
    <div class="prose prose-sm mt-24 max-w-none space-y-16 text-sokoni-black/80">
        <p>Sokoni is Tanzania's marketplace for verified sellers, starting in Dar es Salaam. We believe buying and selling online in Tanzania should feel like a real market — real photos, real people, and real clarity about who you're dealing with.</p>
        <p>Every seller on Sokoni goes through manual identity verification before their products go live — a NIDA number, reviewed by our team, not just a form. That's our main defence against fraudulent shops, and the reason a verified badge here actually means something.</p>
        <p>From Dar es Salaam to towns and regions across the country, Sokoni exists to help Tanzanian sellers reach more buyers, and help buyers find what they need close to home.</p>
    </div>
@endif
</article>
@endsection
