// Real API responses, captured verbatim from the Laravel API (feature-test
// run of POST /api/orders with the exact payload Sokoni 1.1 sends for a
// Sokoni-delivery order), BEFORE the 2026-11-04 server fix. The staging
// server answered the same shape for order #72: the order was created,
// but "status" and "payment_status" came back null because Eloquent never
// reads database defaults back after an insert, and the app's
// `json['status'] as String` threw "type 'Null' is not a subtype of type
// 'String' in type cast" after Place order. Names and numbers are test
// data from the API's factories. Keep these byte-for-byte: a hand-written
// response is how the bug slipped through the first time.

/// POST /api/orders, 201, buyer's view of a new dispatch order: no job,
/// driver, payment or confirmed fee yet (delivery_fee 0 until the shop
/// accepts).
const dispatchOrderCreatedResponse = r'''
{
    "data": {
        "id": 1,
        "code": "SKBJZLELSS",
        "status": null,
        "subtotal": 20000,
        "delivery_fee": 0,
        "total": 20000,
        "delivery_method": "delivery",
        "fulfilment": "dispatch",
        "address": "Mtaa wa Uhuru 12, Kariakoo",
        "delivery_phone": "+255765000123",
        "notes": "Blue gate",
        "payment_method": "cash_on_delivery",
        "payment_status": null,
        "cancelled_reason": null,
        "buyer": {
            "id": 1,
            "name": "Pedro Kiehn",
            "email": "uullrich@example.org",
            "email_verified": false,
            "phone": "+255711000111",
            "username": null,
            "needs_credential_setup": true,
            "two_factor_enabled": false,
            "avatar": null,
            "locale": "en",
            "is_seller": false,
            "seller_status": null,
            "seller_handle": null,
            "account_intent": null,
            "terms_accepted": true,
            "created_at": "2026-11-04T09:49:33.000000Z",
            "notify_orders": true,
            "notify_messages": true,
            "notify_offers": true,
            "notify_marketing": false
        },
        "seller": {
            "id": 1,
            "shop_name": "Duka la Juma",
            "logo": null,
            "handle": "ie9p1",
            "is_verified": true,
            "rating_avg": 0,
            "rating_count": 0,
            "lat": -6.819,
            "lng": 39.276,
            "whatsapp": "+255797421765",
            "is_following": false
        },
        "items": [
            {
                "id": 1,
                "product_id": 1,
                "title": "Architecto illum fugit",
                "price": 20000,
                "qty": 1,
                "line_total": 20000
            }
        ],
        "conversation_id": 1,
        "timeline": [
            {
                "status": "pending",
                "at": "2026-11-04T09:49:36.000000Z"
            }
        ],
        "created_at": "2026-11-04T09:49:36.000000Z"
    }
}''';

/// GET /api/delivery/orders/{id} right after placing: the shop hasn't
/// accepted, so there is no delivery job yet.
const noDeliveryJobResponse = r'''
{
    "code": "no_delivery_job",
    "message": "Oda hii bado haina usafirishaji."
}
''';
