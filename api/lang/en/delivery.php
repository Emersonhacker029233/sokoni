<?php

// Delivery by Bolt (docs/delivery/BOLT.md). Every key here must also exist
// in lang/sw/delivery.php (DeliveryLanguageFilesTest checks).
return [
    'too_far' => 'Delivery is available up to :km km from the shop. Choose pickup instead.',
    'your_driver' => 'Your Bolt driver',

    'reasons' => [
        'delivery_cancelled' => 'Delivery cancelled',
        'payment_timeout' => 'Delivery fee not paid in time',
    ],

    'errors' => [
        'invalid_transition' => 'This delivery cannot move from ":from" to ":to".',
        'cannot_cancel_in_transit' => 'The parcel has already left the shop, so this cannot be cancelled here. Please contact Sokoni support.',
        'cannot_retry' => 'This delivery cannot be booked again.',
        'cannot_switch_to_pickup' => 'This order can no longer be switched to pickup.',
        'shop_has_no_location' => 'This shop has not set its map location, so delivery is not available. Choose pickup instead.',
        'dropoff_location_required' => 'Drop a pin on the map for the delivery location.',
        'no_delivery_job' => 'This order has no delivery yet.',
        'not_payable' => 'This delivery fee cannot be paid now.',
        'payment_in_progress' => 'A payment request is already on your phone. Approve it, or wait a moment and try again.',
        'payments_unavailable' => 'Online payment is not available right now.',
        'idempotency_key_reused' => 'This payment request was already used for another order.',
        'not_available' => 'Delivery is not available from this shop yet. Choose pickup instead.',
        'quote_unavailable' => 'We could not get a delivery price from Bolt just now. Try again in a moment.',
        'area_not_serviced' => 'Bolt does not deliver in this area yet. Choose pickup instead.',
        'no_driver_available' => 'No Bolt driver is available nearby right now. Try again shortly or choose pickup.',
    ],

    'push' => [
        'buyer_pay_fee' => ['title' => 'Order :code accepted', 'body' => 'Pay the Bolt delivery fee of TSh :fee in the app. The shop then packs your order.'],
        'buyer_fee_paid' => ['title' => 'Delivery paid', 'body' => 'We book a Bolt driver as soon as :shop has packed order :code.'],
        'buyer_finding_driver' => ['title' => 'Booking a Bolt driver', 'body' => 'Bolt is finding a driver to collect order :code from :shop.'],
        'buyer_driver_changed' => ['title' => 'Finding another driver', 'body' => 'Your Bolt driver dropped the trip. Bolt is finding another one.'],
        'buyer_driver_assigned' => ['title' => 'Driver on the way to the shop', 'body' => ':driver (:plate) will collect order :code.'],
        'buyer_arrived_at_shop' => ['title' => 'Driver at the shop', 'body' => ':driver has reached :shop.'],
        'buyer_on_the_way' => ['title' => 'On the way to you', 'body' => ':driver has your order :code. Keep your phone close.'],
        'buyer_delivered' => ['title' => 'Delivered', 'body' => 'Order :code was delivered.'],
        'buyer_no_driver_found' => ['title' => 'No Bolt driver available', 'body' => 'No Bolt driver could take order :code. Try again, switch to pickup, or cancel. A paid delivery fee is refunded.'],
        'buyer_not_serviced' => ['title' => 'Bolt does not deliver here', 'body' => 'Bolt does not serve this route for order :code. Switch to pickup or cancel.'],
        'buyer_failed' => ['title' => 'Delivery problem', 'body' => 'The delivery of order :code could not be completed. Open the app to see your options.'],
        'buyer_cancelled' => ['title' => 'Delivery cancelled', 'body' => 'The delivery for order :code was cancelled.'],
        'buyer_payment_expired' => ['title' => 'Order cancelled', 'body' => 'The delivery fee for order :code was not paid in time, so the order was cancelled.'],
        'buyer_payment_failed' => ['title' => 'Payment not completed', 'body' => 'The delivery fee for order :code was not paid. Open the app to try again.'],
        'seller_fee_paid' => ['title' => 'Delivery paid', 'body' => 'The buyer paid for delivery of order :code. Pack it and tap "Mark ready": a Bolt driver is booked straight away.'],
        'seller_driver_assigned' => ['title' => 'Bolt driver coming', 'body' => ':driver (:plate) is coming to collect order :code.'],
        'seller_driver_at_shop' => ['title' => 'Bolt driver has arrived', 'body' => ':driver (:plate) is at your shop. Hand over order :code.'],
        'seller_picked_up' => ['title' => 'Order collected', 'body' => ':driver has collected order :code.'],
        'seller_delivered' => ['title' => 'Order delivered', 'body' => 'Order :code was delivered to the customer.'],
        'seller_no_driver' => ['title' => 'No Bolt driver yet', 'body' => 'No Bolt driver could take order :code. Keep it ready; the buyer has been told.'],
        'seller_failed' => ['title' => 'Delivery problem', 'body' => 'The Bolt delivery of order :code did not complete. Sokoni support will help if the parcel left your shop.'],
        'seller_cancelled' => ['title' => 'Delivery cancelled', 'body' => 'The delivery of order :code was cancelled. Do not hand it over.'],
        'seller_payment_expired' => ['title' => 'Order cancelled', 'body' => 'Order :code was cancelled because the buyer did not pay the delivery fee in time. Do not prepare it.'],
    ],
];
