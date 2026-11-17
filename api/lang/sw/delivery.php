<?php

// Usafirishaji kwa Bolt (docs/delivery/BOLT.md). Kila ufunguo hapa lazima
// uwepo pia katika lang/en/delivery.php (DeliveryLanguageFilesTest hukagua).
return [
    'too_far' => 'Usafirishaji unapatikana hadi km :km kutoka dukani. Chagua kuchukua mwenyewe badala yake.',
    'your_driver' => 'Dereva wako wa Bolt',

    'reasons' => [
        'delivery_cancelled' => 'Usafirishaji umeghairiwa',
        'payment_timeout' => 'Ada ya usafirishaji haikulipwa kwa wakati',
    ],

    'errors' => [
        'invalid_transition' => 'Usafirishaji huu hauwezi kutoka ":from" kwenda ":to".',
        'cannot_cancel_in_transit' => 'Mzigo tayari umetoka dukani, kwa hiyo hauwezi kughairiwa hapa. Tafadhali wasiliana na huduma kwa wateja ya Sokoni.',
        'cannot_retry' => 'Usafirishaji huu hauwezi kuagizwa tena.',
        'cannot_switch_to_pickup' => 'Oda hii haiwezi tena kubadilishwa kuwa ya kuchukua mwenyewe.',
        'shop_has_no_location' => 'Duka hili halijaweka mahali lilipo kwenye ramani, kwa hiyo usafirishaji haupatikani. Chagua kuchukua mwenyewe badala yake.',
        'dropoff_location_required' => 'Weka alama kwenye ramani mahali pa kufikisha.',
        'no_delivery_job' => 'Oda hii bado haina usafirishaji.',
        'not_payable' => 'Ada hii ya usafirishaji haiwezi kulipwa sasa.',
        'payment_in_progress' => 'Ombi la malipo tayari liko kwenye simu yako. Likubali, au subiri kidogo ujaribu tena.',
        'payments_unavailable' => 'Malipo ya mtandaoni hayapatikani kwa sasa.',
        'idempotency_key_reused' => 'Ombi hili la malipo tayari limetumika kwa oda nyingine.',
        'not_available' => 'Usafirishaji bado haupatikani kutoka duka hili. Chagua kuchukua mwenyewe badala yake.',
        'quote_unavailable' => 'Hatukuweza kupata bei ya usafirishaji kutoka Bolt kwa sasa. Jaribu tena baada ya muda mfupi.',
        'area_not_serviced' => 'Bolt bado haisafirishi eneo hili. Chagua kuchukua mwenyewe badala yake.',
        'no_driver_available' => 'Hakuna dereva wa Bolt aliye karibu kwa sasa. Jaribu tena baadaye au chagua kuchukua mwenyewe.',
    ],

    'push' => [
        'buyer_pay_fee' => ['title' => 'Oda :code imekubaliwa', 'body' => 'Lipa ada ya usafirishaji wa Bolt ya TSh :fee kwenye programu. Kisha duka litafunga oda yako.'],
        'buyer_fee_paid' => ['title' => 'Usafirishaji umelipiwa', 'body' => 'Tutaagiza dereva wa Bolt mara tu :shop watakapomaliza kufunga oda :code.'],
        'buyer_finding_driver' => ['title' => 'Tunaagiza dereva wa Bolt', 'body' => 'Bolt inatafuta dereva wa kuchukua oda :code kutoka :shop.'],
        'buyer_driver_changed' => ['title' => 'Tunatafuta dereva mwingine', 'body' => 'Dereva wako wa Bolt ameacha safari. Bolt inatafuta mwingine.'],
        'buyer_driver_assigned' => ['title' => 'Dereva anaelekea dukani', 'body' => ':driver (:plate) atachukua oda :code.'],
        'buyer_arrived_at_shop' => ['title' => 'Dereva yuko dukani', 'body' => ':driver amefika :shop.'],
        'buyer_on_the_way' => ['title' => 'Yuko njiani kuja kwako', 'body' => ':driver ana oda yako :code. Weka simu yako karibu.'],
        'buyer_delivered' => ['title' => 'Imefikishwa', 'body' => 'Oda :code imefikishwa.'],
        'buyer_no_driver_found' => ['title' => 'Hakuna dereva wa Bolt', 'body' => 'Hakuna dereva wa Bolt aliyeweza kuchukua oda :code. Jaribu tena, chagua kuchukua mwenyewe, au ghairi. Ada ya usafirishaji iliyolipwa inarudishwa.'],
        'buyer_not_serviced' => ['title' => 'Bolt haisafirishi hapa', 'body' => 'Bolt haihudumii njia hii kwa oda :code. Chagua kuchukua mwenyewe au ghairi.'],
        'buyer_failed' => ['title' => 'Tatizo la usafirishaji', 'body' => 'Usafirishaji wa oda :code haukukamilika. Fungua programu kuona machaguo yako.'],
        'buyer_cancelled' => ['title' => 'Usafirishaji umeghairiwa', 'body' => 'Usafirishaji wa oda :code umeghairiwa.'],
        'buyer_payment_expired' => ['title' => 'Oda imeghairiwa', 'body' => 'Ada ya usafirishaji ya oda :code haikulipwa kwa wakati, kwa hiyo oda imeghairiwa.'],
        'buyer_payment_failed' => ['title' => 'Malipo hayakukamilika', 'body' => 'Ada ya usafirishaji ya oda :code haikulipwa. Fungua programu ujaribu tena.'],
        'seller_fee_paid' => ['title' => 'Usafirishaji umelipiwa', 'body' => 'Mnunuzi amelipia usafirishaji wa oda :code. Ifunge kisha gusa "Weka kama Tayari": dereva wa Bolt ataagizwa mara moja.'],
        'seller_driver_assigned' => ['title' => 'Dereva wa Bolt anakuja', 'body' => ':driver (:plate) anakuja kuchukua oda :code.'],
        'seller_driver_at_shop' => ['title' => 'Dereva wa Bolt amefika', 'body' => ':driver (:plate) yuko dukani kwako. Mkabidhi oda :code.'],
        'seller_picked_up' => ['title' => 'Oda imechukuliwa', 'body' => ':driver amechukua oda :code.'],
        'seller_delivered' => ['title' => 'Oda imefikishwa', 'body' => 'Oda :code imemfikia mteja.'],
        'seller_no_driver' => ['title' => 'Bado hakuna dereva wa Bolt', 'body' => 'Hakuna dereva wa Bolt aliyeweza kuchukua oda :code. Iweke tayari; mnunuzi ameshaambiwa.'],
        'seller_failed' => ['title' => 'Tatizo la usafirishaji', 'body' => 'Usafirishaji wa Bolt wa oda :code haukukamilika. Huduma kwa wateja ya Sokoni itasaidia kama mzigo ulitoka dukani kwako.'],
        'seller_cancelled' => ['title' => 'Usafirishaji umeghairiwa', 'body' => 'Usafirishaji wa oda :code umeghairiwa. Usiukabidhi.'],
        'seller_payment_expired' => ['title' => 'Oda imeghairiwa', 'body' => 'Oda :code imeghairiwa kwa sababu mnunuzi hakulipa ada ya usafirishaji kwa wakati. Usiiandae.'],
    ],
];
