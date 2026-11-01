# Delivery API reference (phase 2)

Base URL `https://api.sokoni.co.tz/api`. Every route below needs `Authorization: Bearer <sanctum token>` and returns **404 while `DELIVERY_ENABLED=false`**. Drivers sign in through the existing `/auth/*` routes (phone OTP or username/password) and use a normal Sokoni token.

Errors from delivery actions share one shape. The apps switch on `code` and may show `message`, which is localised from the user's locale (EN/SW):

```json
{ "code": "already_taken", "message": "Another driver has already taken this job." }
```

Validation errors keep Laravel's usual `422 { message, errors: {field: [..]} }` shape.

## Customer app (Sokoni 1.1+)

### Checkout opt-in (existing endpoint, one new field)
`POST /orders` with `"fulfilment": "dispatch"`, `delivery_method: "delivery"`, and `delivery_lat` / `delivery_lng` (required for dispatch). The order is created with `delivery_fee: 0` and `total = subtotal`, because the delivery fee is paid to Sokoni separately. Orders now include `"fulfilment": null | "dispatch"`.

| Error | When |
|---|---|
| 422 `delivery_lat` | No map pin, or drop-off is beyond the max distance (10 km) |
| 422 `fulfilment` | The shop has no map location |

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
  "dropoff": { "lat": -6.79, "lng": 39.28, "address": "Mtaa wa Uhuru 12" },
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

Paying the fee (`POST /delivery/orders/{order}/pay`) lands in phase 5.

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
