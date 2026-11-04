# Delivery API reference (phase 2)

Base URL `https://api.sokoni.co.tz/api`. Every route below needs `Authorization: Bearer <sanctum token>` and returns **404 while `DELIVERY_ENABLED=false`**. Drivers sign in through the existing `/auth/*` routes (phone OTP or username/password) and use a normal Sokoni token.

Errors from delivery actions share one shape. The apps switch on `code` and may show `message`, which is localised from the user's locale (EN/SW):

```json
{ "code": "already_taken", "message": "Another driver has already taken this job." }
```

Validation errors keep Laravel's usual `422 { message, errors: {field: [..]} }` shape.

## Customer app (Sokoni 1.1+)

### `GET /delivery/config`
Read by Sokoni 1.1 before checkout. **404 while `DELIVERY_ENABLED=false`**, which the app reads as "keep the 1.0 checkout".
```json
{ "data": { "enabled": true, "min_app_version": "1.1.0", "currency": "TZS", "max_distance_km": 10, "unpaid_timeout_minutes": 20,
  "payment": { "mode": "online", "online_available": true, "sandbox": true, "channels": ["mpesa", "airtel", "mixx", "halopesa"],
               "covers": ["delivery_fee"], "goods_paid_to": "shop" },
  "tracking": { "poll_seconds": 5, "realtime": false } } }
```
`payment.mode` is `cash` when the setting `delivery_fee_cash_enabled` is on (buyer pays the rider; no Pay now). `online_available` is false when no payment provider is configured (`PAYMENT_PROVIDER=none`, or the sandbox `fake` on production, which is always refused).

### Checkout opt-in (existing endpoint, new optional fields)
`POST /orders` with `"fulfilment": "dispatch"`, `delivery_method: "delivery"`, and `delivery_lat` / `delivery_lng` (required for dispatch). Optional `delivery_phone` (E.164 `+2556…`/`+2557…`): the number the rider calls; empty means the buyer's account phone. The order is created with `delivery_fee: 0` and `total = subtotal`, because the delivery fee is paid to Sokoni separately. Orders now include `"fulfilment": null | "dispatch"`, and `delivery_phone` for the buyer of a dispatch order only.

| Error | When |
|---|---|
| 422 `delivery_lat` | No map pin, or drop-off is beyond the max distance (10 km) |
| 422 `fulfilment` | The shop has no map location |
| 422 `delivery_phone` | Not a Tanzanian mobile number in E.164 |

**Privacy on dispatch orders:** a seller reading the order (`GET /orders/{id}`, `/shop/orders`) or its chat thread gets the buyer's usual user object with `phone`, `email` and `username` set to null. The rider, not the seller, meets the buyer. Legacy orders are unchanged.

### `GET /delivery/quote?seller_id=&lat=&lng=`
Estimate for the checkout screen. The binding quote is frozen when the seller accepts.
```json
{ "data": { "distance_km": 4.23, "distance_source": "straight_line", "base_fee": 1500, "per_km_fee": 700, "fee": 4500, "currency": "TZS", "estimate": true } }
```
`422 { code: "too_far" | "shop_has_no_location" }`.

### `GET /delivery/orders/{order}`
For the order's buyer or seller only. Poll every ~5 s on the tracking screen; it also advances dispatch waves.

Buyer view:
```json
{ "data": {
  "id": 12, "order_id": 345, "order_code": "SKAB12CD34", "status": "on_the_way", "is_active": true,
  "distance_km": 4.23, "currency": "TZS", "fee": 4500, "payment_status": "paid", "amount_due": 0,
  "pay_cash_to_driver": false, "delivery_pin": "4821", "pin_locked": false, "dispatch_wave": null,
  "pickup": { "shop_name": "Duka la Juma", "lat": -6.819, "lng": 39.276 },
  "dropoff": { "lat": -6.79, "lng": 39.28, "address": "Mtaa wa Uhuru 12", "phone": "+255765000123" },
  "payment": { "mode": "online", "status": "paid", "amount_due": 0, "covers": ["delivery_fee"], "goods_paid_to": "shop",
               "goods_total": 20000, "delivery_fee": 4500, "total": 24500, "deadline": null,
               "online_available": true, "sandbox": true, "channels": ["mpesa", "airtel", "mixx", "halopesa"],
               "attempt": { "id": 7, "status": "succeeded", "amount": 4500, "channel": "mpesa", "msisdn": "+25575••••456",
                            "failure_reason": null, "created_at": "...", "expires_at": null },
               "receipt": { "amount": 4500, "channel": "mpesa", "reference": "FAKE-approve-...", "paid_at": "..." } },
  "driver": { "name": "Asha", "photo_url": "https://...signed", "rating_avg": 4.8, "rating_count": 31,
              "phone": "+255754000000", "vehicle": { "type": "boda_boda", "plate_number": "MC123ABC", "make": "TVS", "colour": "Red" } },
  "driver_location": { "lat": -6.80, "lng": 39.279, "at": "2026-10-29T10:01:02Z" },
  "tracking": { "channel": "tracking/12" },
  "refund": null,
  "actions": { "can_pay": false, "can_cancel": false, "can_retry": false, "can_switch_to_pickup": false, "can_rate": false },
  "timeline": [ { "status": "pending_payment", "at": "...", "by": "system", "reason": null } ],
  "cancelled_by": null, "cancel_reason": null, "failure_reason": null, "created_at": "..."
} }
```
- `driver.phone`, `driver_location` and `tracking` are null unless the job is active (`driver_assigned` through `arrived`).
- `delivery_pin` is shown from `pending_driver` until the job ends.
- `timeline[].reason` is a machine code or null. Today it is `seller_cancelled_after_assign` (seller cancelled after a driver took the job; the driver is paid by Sokoni and the buyer refunded in full) or `payment_timeout`.
- A job left in `pending_payment` for 20 minutes (setting `delivery_unpaid_timeout_minutes`) is cancelled with its order by the minute cron: `cancelled_by: "system"`, `cancel_reason: "payment_timeout"`. Buyer and seller both get a push.

The seller view has no PIN, fee or location. It shows `driver` (name, photo, vehicle, and phone only before pickup) and `pickup_code` (the last 4 characters of the order code) while the driver is coming or at the shop.

### Buyer actions
| Route | Allowed from | Result |
|---|---|---|
| `POST /delivery/orders/{order}/cancel {reason?}` | anything before pickup | `cancelled`; refund per section 6 rules |
| `POST /delivery/orders/{order}/retry` | `no_driver_found`, `failed` (not unreachable) | back to `pending_driver`, wave 1, no new payment |
| `POST /delivery/orders/{order}/switch-to-pickup` | `pending_payment`, `pending_driver`, `no_driver_found`, `failed` (not unreachable) | job `cancelled`, full fee refund, order becomes pickup |
| `POST /delivery/orders/{order}/rating {rating 1-5, comment?}` | `delivered` | 201; once only |
| `POST /delivery/realtime-token` | buyer or driver of an active job | `{ token, uid, database_url, expires_in }`; `503 { fallback: "poll" }` when Firebase isn't configured |

### Paying the delivery fee: `POST /delivery/orders/{order}/pay`
`{ channel: "mpesa"|"airtel"|"mixx"|"halopesa", msisdn: "+2557XXXXXXXX", idempotency_key: "<8-100 chars [A-Za-z0-9_-]>" }`, buyer only, while `actions.can_pay`.

- Answers **202** with the buyer view; `payment.attempt.status` is `pending` while the prompt is on the phone. The amount is never in the request: the server charges the fee frozen on the job (`amount_due`).
- The app never marks anything paid. The payment becomes `succeeded` only when the server asks the gateway (on the buyer's next poll, at most every 3 s, and on every minute cron tick). Then the job moves to `pending_driver` and dispatch starts.
- The same `idempotency_key` returns the same attempt and never sends a second prompt. A new key while an attempt is still open answers `409 payment_in_progress`; an open attempt expires after 120 s (`failure_reason: "timeout"`) and the buyer can try again.
- `failure_reason`: `declined`, `timeout`, `gateway_error`. The buyer also gets a `buyer_payment_failed` push and inbox entry.
- `409 not_payable` (already paid, cash mode, or not waiting for payment), `409 idempotency_key_reused`, `503 payments_unavailable`.
- A payment confirmed after the 20-minute unpaid cancel is kept, and the job shows `refund: { amount, status: "pending" }`.
- While the wallet is off (WALLET_PLAN.md section 4) the payment covers the delivery fee only; goods are paid to the shop directly (`goods_paid_to: "shop"`).

**Sandbox gateway** (`PAYMENT_PROVIDER=fake`, local and staging only, refused in production): the prompt is approved by itself after `PAYMENT_FAKE_APPROVE_AFTER_SECONDS` (default 8). A paying number ending `0001` is declined; ending `0002` never answers (times out). The real provider (Dalipay) replaces it in Part B behind the same `PaymentGateway` interface.

**Pushes to the customer app** (FCM when configured, always an inbox entry with `order_id`): buyer on every status change plus `buyer_payment_failed`; seller on fee paid, driver assigned, driver at shop, picked up, delivered, failed, and cancelled (unless the seller cancelled it).

## Driver app (Sokoni Driver)

### Onboarding (any signed-in account with a phone)
| Route | Body |
|---|---|
| `GET /driver/me` | `data: null` until a profile exists |
| `POST /driver/profile` | `national_id_number` (20 digits; dashes/spaces stripped), `licence_number?`, `licence_expires_on?`, `accept_terms: true`, `terms_version` |
| `POST /driver/profile/documents` (multipart) | any of `photo`, `national_id_image` (jpg/png/webp), `licence_image` (+pdf); max 5 MB each |
| `PUT /driver/vehicle` | `type` (`boda_boda`, `bajaj`, `car`, `bicycle`), `plate_number` (required unless bicycle; normalised, e.g. "mc 123 abc" becomes "MC123ABC"), `make?`, `colour?` |
| `POST /driver/profile/submit` | `422 { code: "profile_incomplete", missing: [...] }` until complete |
| `POST /driver/devices` | `fcm_token`, `platform` (`android`/`ios`); kept separate from the customer app's tokens |

The profile resource includes `status` (`pending`, `approved`, `rejected`, `suspended`), `missing_requirements`, `can_submit`, `can_go_online`, `vehicle.verified`, `active_job_id` and `national_id_last4`. It never includes the full ID or document images.

Uploading new ID or licence images, or changing vehicle, sends an approved driver back to review or vehicle verification and takes them offline.

### Work (approved drivers only; otherwise `403 { code: "not_a_driver" | "driver_not_approved", driver_status }`)
| Route | Notes |
|---|---|
| `PATCH /driver/availability {online, lat, lng}` | `lat`/`lng` required when going online. `403 vehicle_unverified`, `licence_expired` |
| `POST /driver/location {lat, lng, heading?, speed?, accuracy?}` | Every ~30 s while online. Ignored (`accepted: false`) when offline with no job. Throttled at 30/min |
| `GET /driver/offers` | Open offers. Poll ~10 s while online; FCM is the fast path |
| `POST /driver/offers/{job}/accept` | Returns the full driver job view. `409 already_taken`, `409 driver_busy`, `403 not_offered`, `403 vehicle_unverified` |
| `POST /driver/offers/{job}/decline` | Not offered this job again |
| `GET /driver/jobs/active` | `data: null` when idle |
| `GET /driver/jobs` | Past jobs, 20 per page |
| `GET /driver/jobs/{job}` | Own jobs only |
| `POST /driver/jobs/{job}/status` | See below |
| `POST /driver/jobs/{job}/call-attempts` | Tap "Call customer". Counts toward the unreachable rule |
| `POST /driver/jobs/{job}/rating {rating, comment?}` | Rate the buyer after delivery |
| `GET /driver/earnings?period=day\|week&date=YYYY-MM-DD` | `summary` (counts, gross, commission, earnings, by_day, jobs), `balance`, `payouts`, `commission_pct` |

Offer preview (before accepting) has no customer name, phone or exact address:
```json
{ "job_id": 12, "offered_at": "...", "wave": 1, "distance_to_shop_km": 1.2, "trip_km": 4.23,
  "driver_earnings": 3825, "fee": 4500, "collect_cash": 0, "items_count": 2,
  "shop": { "name": "Duka la Juma", "district": "Ilala", "lat": -6.819, "lng": 39.276 },
  "dropoff_area": { "lat": -6.79, "lng": 39.28 } }
```

### Status changes: `POST /driver/jobs/{job}/status`
| `status` | Extra fields | Errors |
|---|---|---|
| `arrived_at_shop` | none | |
| `picked_up` | `pickup_code` (last 4 of the order code, case-insensitive) | `422 wrong_pickup_code`, `409 order_cancelled` |
| `on_the_way` | none | |
| `arrived` | none | |
| `delivered` | `pin` (4 digits), `proof_photo` (multipart; required only if the setting is on) | `422 wrong_pin { attempts_left }`, `423 pin_locked` after 5 wrong tries, `422 proof_photo_required` |
| `failed` | `reason` (`customer_unreachable`, `vehicle_breakdown`, `accident`, `item_damaged`, `wrong_address`, `other`), `note?` | `409 unreachable_too_early { minutes }`, `409 unreachable_needs_calls { calls }` |
| `cancelled` | `note?` | Only before pickup; the job is re-dispatched without you. `409 cannot_cancel_in_transit` |

Illegal or out-of-order steps return `409 invalid_transition`.

## Push data payloads (FCM `data`, all strings)

| `type` | App | Fields |
|---|---|---|
| `delivery_offer` | Driver | `job_id`, `earnings`, `distance_km` |
| `delivery_offer_withdrawn` | Driver (silent) | `job_id`. Remove the offer from screen |
| `delivery_status` | Both | `order_id`, `job_id`, `status` |

## Live tracking (Firebase Realtime Database)

Path `/tracking/{jobId}`. Laravel writes `members/{uid}` and `driver_uid` on assignment and deletes the whole node when the job ends.

- The driver app signs in with the custom token from `/delivery/realtime-token` and writes `loc = {lat, lng, heading?, ts}` every 3-5 s.
- The buyer app listens to `loc`.

Security rules are in [firebase-database.rules.json](firebase-database.rules.json). If the token endpoint returns 503, poll `GET /delivery/orders/{order}` instead; it carries `driver_location` from the 30 s heartbeat.
