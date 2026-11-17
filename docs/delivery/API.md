# Delivery API reference (Bolt)

Base URL `https://api.sokoni.co.tz/api`. Every route below needs `Authorization: Bearer <sanctum token>`, except `GET /features` and the Bolt webhook. How delivery works is in [BOLT.md](BOLT.md), returns in [RETURNS.md](RETURNS.md).

> The phase 2 reference for the Sokoni Driver app (`/driver/*`, dispatch waves, PINs, `/delivery/realtime-token`, Firebase live tracking, rider ratings) was removed with the driver app. It is in git at tag `pre-bolt-sokoni-drivers`.

**Dark launch.** While `BOLT_ENABLED=false`, or for a user outside `BOLT_TEST_PHONES`, or for the App Store review account, every `/delivery/*` route answers exactly like a route that doesn't exist: `404 { "message": "The route api/delivery/... could not be found." }`, before authentication, so a guest gets the same 404. Returns routes do the same while `DELIVERY_RETURNS_ENABLED=false`.

Errors from delivery and return actions share one shape. The apps switch on `code` and may show `message`, localised from the user's locale (EN/SW):

```json
{ "code": "no_driver_available", "message": "No Bolt driver is available nearby right now. Try again shortly or choose pickup." }
```

Validation errors keep Laravel's usual `422 { message, errors: {field: [..]} }` shape. Rate limits: quotes 15/min, actions 20/min per user.

## `GET /features`

Public. What this user may see. Guests always get `false`.

```json
{ "data": { "delivery_enabled": true, "returns_enabled": true,
            "returns_terms_version": "1.2", "returns_terms_accepted": false } }
```

`returns_terms_*` are present only while `returns_enabled` is true. The app treats any error as "all off".

## `GET /delivery/config`

```json
{ "data": { "enabled": true, "provider": "bolt", "min_app_version": "1.1.0", "currency": "TZS",
  "max_distance_km": 10, "unpaid_timeout_minutes": 20,
  "payment": { "mode": "online", "online_available": true, "sandbox": true, "channels": ["mpesa", "airtel", "mixx", "halopesa"],
               "covers": ["delivery_fee"], "goods_paid_to": "shop" },
  "tracking": { "poll_seconds": 10 },
  "returns_enabled": false } }
```

`online_available` is false when no payment provider is configured (`PAYMENT_PROVIDER=none`, or the sandbox `fake` on production, which is always refused). The fee is then recorded by an admin (Bolt deliveries → Record fee paid).

## `GET /delivery/quote?seller_id=&lat=&lng=`

Bolt's estimate for the checkout screen, shop → drop-off. The fee the buyer pays is the upper bound, rounded up. The binding fee is set when the shop accepts.

```json
{ "data": { "provider": "bolt", "distance_km": 4.23, "currency": "TZS", "min": 5200, "max": 6400, "fee": 6400,
  "category": "Bolt", "eta_seconds": 300, "estimate": true,
  "return_policy": { "code": "24h", "hours": 24, "label": "Returns within 24 hours", "notes": null } } }
```

`return_policy` is null unless returns are on for both the buyer and the shop.

| Error | When |
|---|---|
| `422 not_available` | The shop's owner is outside the test (`BOLT_TEST_PHONES`), or the shop is the review account's |
| `422 too_far` | Drop-off beyond `SOKONI_DELIVERY_MAX_DISTANCE_KM` (10) in a straight line |
| `422 shop_has_no_location` | The shop has no map pin |
| `422 area_not_serviced` | Bolt doesn't serve this route |
| `422 no_driver_available` | No Bolt category has an ETA right now |
| `503 quote_unavailable` | Bolt didn't answer |

## Checkout (existing `POST /orders`, optional fields)

`"fulfilment": "dispatch"`, `delivery_method: "delivery"`, `delivery_lat` / `delivery_lng`, optional `delivery_phone` (E.164 `+2556…`/`+2557…`: the number Bolt's driver calls; empty means the buyer's account phone).

- Only read for a buyer **and** shop inside the launch. For anyone else the fields are ignored without validation and an ordinary order is placed.
- A dispatch order is created with `delivery_fee: 0` and `total = subtotal`: the delivery fee is paid to Sokoni separately. Its responses include `"fulfilment": "dispatch"`; ordinary orders don't carry the key at all.
- The shop's return policy is copied onto the order.
- If Bolt can't be reached at checkout the order is still accepted; the price is set when the shop accepts.

| Error | When |
|---|---|
| `422 delivery_lat` | No map pin, or beyond the max distance |
| `422 fulfilment` | The shop has no map location |
| `422 delivery_phone` | Not a Tanzanian mobile number in E.164 |

**Privacy on dispatch orders:** a shop reading the order or its chat thread gets the buyer's user object with `phone`, `email` and `username` null. Bolt's driver, not the shop, meets the buyer.

## `GET /delivery/orders/{order}`

For the order's buyer or shop only (`403` otherwise, `404 no_delivery_job` on an ordinary order). Poll every `tracking.poll_seconds` (10); a poll also refreshes a running ride from Bolt, at most once per 10 s per ride.

**Statuses:** `pending_quote` (shop accepted, pricing) → `pending_payment` → `awaiting_ready` (paid, waiting for the shop's Ready) → `pending_driver` (booked, Bolt searching) → `driver_assigned` → `arrived_at_shop` → `on_the_way` → `delivered`. Off the path: `no_driver_found`, `failed`, `cancelled`. (When the shop marked the order Ready before the fee was paid, the job passes through `awaiting_ready` and is booked at once.)

Buyer view:

```json
{ "data": {
  "id": 12, "order_id": 345, "order_code": "SKAB12CD34", "provider": "bolt", "status": "driver_assigned", "is_active": true,
  "distance_km": 4.23, "currency": "TZS", "fee": 6400,
  "estimate": { "min": 5200, "max": 6400, "category": "Bolt" },
  "payment_status": "paid", "amount_due": 0,
  "pickup": { "shop_name": "Duka la Juma", "lat": -6.819, "lng": 39.276 },
  "dropoff": { "lat": -6.79, "lng": 39.28, "address": "Mtaa wa Uhuru 12", "phone": "+255765000123" },
  "payment": { "mode": "online", "status": "paid", "amount_due": 0, "covers": ["delivery_fee"], "goods_paid_to": "shop",
               "goods_total": 20000, "delivery_fee": 6400, "total": 26400, "deadline": null,
               "online_available": true, "sandbox": true, "channels": ["mpesa", "airtel", "mixx", "halopesa"],
               "attempt": { "id": 7, "status": "succeeded", "amount": 6400, "channel": "mpesa", "msisdn": "+25575••••456",
                            "failure_reason": null, "created_at": "...", "expires_at": null },
               "receipt": { "amount": 6400, "channel": "mpesa", "reference": "...", "paid_at": "..." } },
  "driver": { "name": "Juma", "phone": "+255754000000", "plate": "T123ABC", "model": "Toyota IST",
              "photo_url": "https://...", "eta_seconds": 240 },
  "tracking_url": "https://bolt.eu/...",
  "refund": null,
  "actions": { "can_pay": false, "can_cancel": true, "can_retry": false, "can_switch_to_pickup": false },
  "timeline": [ { "status": "pending_payment", "at": "...", "by": "system", "reason": null } ],
  "cancelled_by": null, "cancel_reason": null, "failure_reason": null, "created_at": "...",
  "return": { "policy": { "code": "24h", "hours": 24, "label": "...", "notes": null },
              "eligible_until": null, "can_request": false, "request": null }
} }
```

- `driver` appears once Bolt assigns one (and stays after delivery, without phone or ETA). `driver.phone` only while the driver is on the job.
- `tracking_url` (Bolt's tracking page) only while the ride runs (`pending_driver` to `on_the_way`).
- `failure_reason` on `no_driver_found` / `failed`: `no_driver_found`, `no_driver_available`, `area_not_serviced`, `shop_no_show`, `payment_booking_failed`, `provider_cancelled`, `client_cancelled`, `provider_cancelled_in_transit`, `client_cancelled_in_transit`, `booking_failed`, `quote_failed`. `can_retry` is true for all of them except `area_not_serviced` and the two `_in_transit` ones (the parcel was in the car: Sokoni support handles those).
- `timeline[].reason`: `payment_timeout`, `seller_cancelled_after_assign`, `provider_cancel_failed` (Bolt refused the cancel; an admin checks the bill), or null.
- `return` only while returns are on for the viewer ([RETURNS.md](RETURNS.md)).

Shop view: the same base fields (no `fee`, `estimate`, `payment`, `dropoff` or `refund`) plus

```json
{ "fee_paid": true,
  "driver": { "name": "Juma", "phone": "+255754000000", "plate": "T123ABC", "model": "Toyota IST", "photo_url": "...", "eta_seconds": 240 },
  "tracking_url": "https://bolt.eu/..." }
```

`driver.phone` only while the driver is coming to or at the shop; `tracking_url` only until pickup.

## Buyer actions

| Route | Allowed | Result |
|---|---|---|
| `POST /delivery/orders/{order}/pay` | `actions.can_pay` | See below |
| `POST /delivery/orders/{order}/cancel {reason?}` | Before the parcel leaves the shop | Bolt ride cancelled (after a fresh read of its state); job and order `cancelled`. Paid fee refunded in full, minus `SOKONI_DELIVERY_BUYER_CANCEL_FEE` (1,000) if Bolt had already assigned a driver. `409 cannot_cancel_in_transit` once picked up |
| `POST /delivery/orders/{order}/retry` | `actions.can_retry` | Books Bolt again with a new `fare_id`; no new payment |
| `POST /delivery/orders/{order}/switch-to-pickup` | `actions.can_switch_to_pickup` | Job `cancelled`, order becomes pickup, paid fee refunded in full |

Refunds are recorded (`refund: { amount, status: "pending" }`) and paid by an admin.

### `POST /delivery/orders/{order}/pay`

`{ channel: "mpesa"|"airtel"|"mixx"|"halopesa", msisdn: "+2557XXXXXXXX", idempotency_key: "<8-100 chars [A-Za-z0-9_-]>" }`.

- Answers **202** with the buyer view; `payment.attempt.status` is `pending` while the prompt is on the phone. The amount is never in the request: the server charges the fee set on the job (`amount_due`).
- Only the server marks a fee paid, after asking the gateway (on the buyer's polls, at most every 3 s, and every minute). The job then moves to `awaiting_ready`, or straight to booking if the shop already marked the order Ready.
- The same `idempotency_key` returns the same attempt. A new key while one is open: `409 payment_in_progress`; an open attempt expires after 120 s.
- `failure_reason`: `declined`, `timeout`, `gateway_error`. `409 not_payable`, `409 idempotency_key_reused`, `503 payments_unavailable`.
- Unpaid 20 minutes after the price was set (`payment.deadline`): job and order cancelled (`cancel_reason: "payment_timeout"`). A payment confirmed after that is kept and marked for refund.

**Sandbox gateway** (`PAYMENT_PROVIDER=fake`, local and staging only): approved by itself after `PAYMENT_FAKE_APPROVE_AFTER_SECONDS` (8). A number ending `0001` is declined; `0002` never answers.

## Shop side

No new shop endpoints for delivery: the shop uses the existing order buttons. **Accept** prices the delivery, **Mark as Ready** books Bolt once the fee is paid. While a dispatch delivery is open, the order can't be completed by hand unless the delivery failed (Bolt's COMPLETED completes it), and can't be cancelled once the parcel has left the shop.

## Bolt webhook: `POST /webhooks/bolt`

For Bolt only. `Authorization: Bearer <BOLT_WEBHOOK_TOKEN>`, from `BOLT_WEBHOOK_IPS`, body `{ "ride_id": 123, "type": "ON_DEMAND", "state": "COMPLETED" }`. Sokoni reads the ride back from Bolt and applies that. `200` for unknown rides; `401` for a wrong token, `403` for an unknown IP; `404` while Bolt is off or no token is set. Throttled at 120/min.

## Pushes (FCM `data`, all strings, plus an inbox entry)

| `type` | Fields |
|---|---|
| `delivery_status` | `order_id`, `job_id`, `status` |
| `return_status` | `order_id`, `return_id`, `status` |

Buyer: price set (pay the fee), fee paid, finding a driver, driver assigned (name and plate), driver changed, at the shop, on the way, delivered, no driver, route not served, failed, cancelled, payment failed or expired. Shop: fee paid ("pack it and tap Mark as Ready"), driver assigned, driver at the shop, picked up, delivered, no driver, failed, cancelled, payment expired.

## Returns

See [RETURNS.md](RETURNS.md) § 6.
