<?php

// Returns (docs/delivery/RETURNS.md). Every key here must also exist in
// lang/sw/returns.php (DeliveryLanguageFilesTest checks).
return [
    'policy' => [
        'none' => 'No returns',
        '24h' => 'Returns within 24 hours',
        '3d' => 'Returns within 3 days',
        '7d' => 'Returns within 7 days',
    ],

    'reasons' => [
        'changed_mind' => 'Changed my mind',
        'wrong_item' => 'Wrong item',
        'damaged' => 'Damaged or defective',
        'not_as_described' => 'Not as described',
    ],

    'errors' => [
        'not_your_return' => 'This return is not yours to act on.',
        'return_not_available' => 'Returns are only possible for orders delivered by Bolt through Sokoni.',
        'no_return_policy' => 'This shop does not accept returns.',
        'return_window_closed' => 'The return window for this order has closed.',
        'return_exists' => 'A return has already been requested for this order.',
        'invalid_transition' => 'This return cannot move from ":from" to ":to".',
        'seller_must_decide' => 'The shop decides this return. Sokoni support steps in if they do not answer within 48 hours.',
        'handled_by_support' => 'Sokoni support is handling this return.',
        'not_payable' => 'There is nothing to pay for this return now.',
        'ride_not_bookable' => 'The return pickup cannot be booked yet.',
        'ride_unavailable' => 'Bolt cannot collect from your location. Please arrange the return with the shop in chat.',
        'ride_booking_failed' => 'We could not book the Bolt pickup just now. Try again in a moment.',
        'cannot_withdraw' => 'This return can no longer be withdrawn.',
        'terms_version' => 'Please agree to the current Return Policy.',
    ],

    'push' => [
        'seller_requested' => ['title' => 'Return requested', 'body' => 'The buyer of order :code wants to return it (:reason). Accept or reject within 48 hours.'],
        'buyer_requested' => ['title' => 'Return requested', 'body' => 'We sent your return request for order :code to :shop. They have 48 hours to answer.'],
        'buyer_accepted_buyer_pays' => ['title' => 'Return accepted', 'body' => ':shop accepted your return of order :code. Pay the Bolt pickup fee of TSh :fee in the app, then book the pickup.'],
        'buyer_accepted_seller_pays' => ['title' => 'Return accepted', 'body' => ':shop accepted your return of order :code and pays the Bolt pickup. Book it in the app when you are ready.'],
        'buyer_accepted_no_ride' => ['title' => 'Return accepted', 'body' => ':shop accepted your return of order :code. Bolt cannot collect from your location: arrange the return with the shop in chat.'],
        'buyer_rejected' => ['title' => 'Return rejected', 'body' => ':shop rejected your return of order :code: :note. You can open a dispute in the app.'],
        'seller_disputed' => ['title' => 'Return disputed', 'body' => 'The buyer disputed your rejection of the return of order :code. Sokoni support will decide.'],
        'buyer_disputed' => ['title' => 'Dispute opened', 'body' => 'Sokoni support will review the return of order :code and decide.'],
        'buyer_escalated' => ['title' => 'Return passed to Sokoni', 'body' => ':shop did not answer your return request for order :code within 48 hours. Sokoni support will decide.'],
        'seller_escalated' => ['title' => 'Return passed to Sokoni', 'body' => 'You did not answer the return request for order :code within 48 hours. Sokoni support will decide.'],
        'buyer_admin_accepted' => ['title' => 'Return approved by Sokoni', 'body' => 'Sokoni support approved your return of order :code. Open the app for the next step.'],
        'seller_admin_accepted' => ['title' => 'Return approved by Sokoni', 'body' => 'Sokoni support approved the return of order :code. Refund the buyer once the item is back.'],
        'buyer_closed' => ['title' => 'Return closed', 'body' => 'The return of order :code is closed: :note'],
        'seller_closed' => ['title' => 'Return closed', 'body' => 'The return of order :code is closed: :note'],
        'seller_withdrawn' => ['title' => 'Return withdrawn', 'body' => 'The buyer withdrew the return of order :code.'],
        'buyer_fee_paid' => ['title' => 'Return pickup paid', 'body' => 'Book the Bolt pickup for order :code in the app when you are ready.'],
        'buyer_payment_failed' => ['title' => 'Payment not completed', 'body' => 'The return pickup fee for order :code was not paid. Open the app to try again.'],
        'seller_ride_booked' => ['title' => 'Return on its way', 'body' => 'A Bolt driver is bringing back order :code from the buyer.'],
        'buyer_ride_booked' => ['title' => 'Bolt pickup booked', 'body' => 'Bolt is finding a driver to collect order :code from you.'],
        'buyer_ride_driver' => ['title' => 'Driver coming for your return', 'body' => ':driver (:plate) will collect order :code from you.'],
        'buyer_ride_failed' => ['title' => 'Return pickup did not happen', 'body' => 'The Bolt pickup for order :code did not go ahead. Book it again in the app.'],
        'seller_received' => ['title' => 'Return delivered to your shop', 'body' => 'Order :code is back at your shop. Check it, refund the buyer, then confirm in the app.'],
        'buyer_received' => ['title' => 'Return delivered', 'body' => 'Your return of order :code reached :shop. They will now refund you.'],
        'buyer_refunded' => ['title' => 'Refund confirmed', 'body' => ':shop confirmed your refund for order :code.'],
    ],
];
