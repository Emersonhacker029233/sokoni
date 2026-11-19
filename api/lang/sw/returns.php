<?php

// Urejeshaji wa bidhaa (docs/delivery/RETURNS.md). Kila ufunguo hapa lazima
// uwepo pia katika lang/en/returns.php (DeliveryLanguageFilesTest hukagua).
return [
    'policy' => [
        'none' => 'Hakuna kurudisha',
        '24h' => 'Kurudisha ndani ya saa 24',
        '3d' => 'Kurudisha ndani ya siku 3',
        '7d' => 'Kurudisha ndani ya siku 7',
    ],

    'reasons' => [
        'changed_mind' => 'Nimebadili mawazo',
        'wrong_item' => 'Bidhaa isiyo sahihi',
        'damaged' => 'Imeharibika au ina kasoro',
        'not_as_described' => 'Si kama ilivyoelezwa',
    ],

    'errors' => [
        'not_your_return' => 'Urejeshaji huu si wako kuushughulikia.',
        'return_not_available' => 'Kurudisha kunawezekana tu kwa oda zilizofikishwa na Bolt kupitia Sokoni.',
        'no_return_policy' => 'Duka hili halipokei bidhaa zinazorudishwa.',
        'return_window_closed' => 'Muda wa kurudisha oda hii umekwisha.',
        'return_exists' => 'Ombi la kurudisha oda hii tayari limetumwa.',
        'invalid_transition' => 'Urejeshaji huu hauwezi kutoka ":from" kwenda ":to".',
        'seller_must_decide' => 'Duka ndilo huamua urejeshaji huu. Huduma kwa wateja ya Sokoni huingilia kati wasipojibu ndani ya saa 48.',
        'handled_by_support' => 'Huduma kwa wateja ya Sokoni inashughulikia urejeshaji huu.',
        'not_payable' => 'Hakuna cha kulipa kwa urejeshaji huu sasa.',
        'ride_not_bookable' => 'Uchukuaji wa kurudisha hauwezi kuagizwa bado.',
        'ride_unavailable' => 'Bolt haiwezi kuchukua mahali ulipo. Tafadhali panga urejeshaji na duka kwenye mazungumzo.',
        'ride_booking_failed' => 'Hatukuweza kuagiza uchukuaji wa Bolt kwa sasa. Jaribu tena baada ya muda mfupi.',
        'cannot_withdraw' => 'Urejeshaji huu hauwezi tena kuondolewa.',
        'terms_version' => 'Tafadhali kubali Sera ya Kurudisha ya sasa.',
    ],

    'push' => [
        'seller_requested' => ['title' => 'Ombi la kurudisha', 'body' => 'Mnunuzi wa oda :code anataka kuirudisha (:reason). Kubali au kataa ndani ya saa 48.'],
        'buyer_requested' => ['title' => 'Ombi la kurudisha limetumwa', 'body' => 'Tumetuma ombi lako la kurudisha oda :code kwa :shop. Wana saa 48 kujibu.'],
        'buyer_accepted_buyer_pays' => ['title' => 'Urejeshaji umekubaliwa', 'body' => ':shop wamekubali urejeshaji wa oda :code. Lipa ada ya uchukuaji wa Bolt ya TSh :fee kwenye programu, kisha agiza uchukuaji.'],
        'buyer_accepted_seller_pays' => ['title' => 'Urejeshaji umekubaliwa', 'body' => ':shop wamekubali urejeshaji wa oda :code na watalipia uchukuaji wa Bolt. Uagize kwenye programu ukiwa tayari.'],
        'buyer_accepted_no_ride' => ['title' => 'Urejeshaji umekubaliwa', 'body' => ':shop wamekubali urejeshaji wa oda :code. Bolt haiwezi kuchukua mahali ulipo: panga urejeshaji na duka kwenye mazungumzo.'],
        'buyer_rejected' => ['title' => 'Urejeshaji umekataliwa', 'body' => ':shop wamekataa urejeshaji wa oda :code: :note. Unaweza kufungua mgogoro kwenye programu.'],
        'seller_disputed' => ['title' => 'Mgogoro wa urejeshaji', 'body' => 'Mnunuzi amepinga ukataaji wako wa urejeshaji wa oda :code. Huduma kwa wateja ya Sokoni itaamua.'],
        'buyer_disputed' => ['title' => 'Mgogoro umefunguliwa', 'body' => 'Huduma kwa wateja ya Sokoni itakagua urejeshaji wa oda :code na kuamua.'],
        'buyer_escalated' => ['title' => 'Urejeshaji umepelekwa Sokoni', 'body' => ':shop hawakujibu ombi lako la kurudisha oda :code ndani ya saa 48. Huduma kwa wateja ya Sokoni itaamua.'],
        'seller_escalated' => ['title' => 'Urejeshaji umepelekwa Sokoni', 'body' => 'Hukujibu ombi la kurudisha oda :code ndani ya saa 48. Huduma kwa wateja ya Sokoni itaamua.'],
        'buyer_admin_accepted' => ['title' => 'Sokoni imekubali urejeshaji', 'body' => 'Huduma kwa wateja ya Sokoni imekubali urejeshaji wa oda :code. Fungua programu kuona hatua inayofuata.'],
        'seller_admin_accepted' => ['title' => 'Sokoni imekubali urejeshaji', 'body' => 'Huduma kwa wateja ya Sokoni imekubali urejeshaji wa oda :code. Mrudishie mnunuzi pesa bidhaa ikishafika.'],
        'buyer_closed' => ['title' => 'Urejeshaji umefungwa', 'body' => 'Urejeshaji wa oda :code umefungwa: :note'],
        'seller_closed' => ['title' => 'Urejeshaji umefungwa', 'body' => 'Urejeshaji wa oda :code umefungwa: :note'],
        'seller_withdrawn' => ['title' => 'Urejeshaji umeondolewa', 'body' => 'Mnunuzi ameondoa urejeshaji wa oda :code.'],
        'buyer_fee_paid' => ['title' => 'Uchukuaji umelipiwa', 'body' => 'Agiza uchukuaji wa Bolt wa oda :code kwenye programu ukiwa tayari.'],
        'buyer_payment_failed' => ['title' => 'Malipo hayakukamilika', 'body' => 'Ada ya uchukuaji wa kurudisha oda :code haikulipwa. Fungua programu ujaribu tena.'],
        'seller_ride_booked' => ['title' => 'Urejeshaji uko njiani', 'body' => 'Dereva wa Bolt analeta oda :code kutoka kwa mnunuzi.'],
        'buyer_ride_booked' => ['title' => 'Uchukuaji wa Bolt umeagizwa', 'body' => 'Bolt inatafuta dereva wa kuchukua oda :code kutoka kwako.'],
        'buyer_ride_driver' => ['title' => 'Dereva anakuja kuchukua', 'body' => ':driver (:plate) atachukua oda :code kutoka kwako.'],
        'buyer_ride_failed' => ['title' => 'Uchukuaji haukufanyika', 'body' => 'Uchukuaji wa Bolt wa oda :code haukuendelea. Uagize tena kwenye programu.'],
        'seller_received' => ['title' => 'Bidhaa imerudi dukani', 'body' => 'Oda :code imerudi dukani kwako. Ikague, mrudishie mnunuzi pesa, kisha thibitisha kwenye programu.'],
        'buyer_received' => ['title' => 'Bidhaa imefika', 'body' => 'Bidhaa uliyorudisha ya oda :code imefika :shop. Sasa watakurudishia pesa.'],
        'buyer_refunded' => ['title' => 'Pesa imerudishwa', 'body' => ':shop wamethibitisha kukurudishia pesa ya oda :code.'],
        'buyer_ride_failed_in_transit' => ['title' => 'Uchukuaji umesimama', 'body' => 'Safari ya Bolt iliyobeba bidhaa uliyorudisha ya oda :code imeisha kabla ya kufika :shop. Huduma kwa wateja ya Sokoni itawasiliana nawe.'],
        'seller_ride_failed_in_transit' => ['title' => 'Uchukuaji umesimama', 'body' => 'Safari ya Bolt iliyokuwa ikirudisha oda :code imeisha njiani. Huduma kwa wateja ya Sokoni itawasiliana nawe.'],
        'buyer_ride_fee_refunded' => ['title' => 'Ada ya uchukuaji imerudishwa', 'body' => 'Sokoni imekurudishia ada ya uchukuaji wa Bolt ya TSh :fee ya oda :code.'],
    ],
];
