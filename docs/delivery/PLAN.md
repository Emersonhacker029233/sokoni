# Sokoni Delivery + Sokoni Driver: Phase 1 plan (draft for approval)

Status: **proposal, no code yet.** Branch `feature/delivery`. Nothing here is deployed.

## 0. Ground rules for the live 1.0 apps

- Every backend change is additive: new tables, new endpoints under `/api/driver/*` and `/api/delivery/*`, new **nullable** columns only. No existing column, enum value, route or response field is renamed, removed or re-typed.
- `DELIVERY_ENABLED=false` (default) means new routes return 404, `POST /orders` behaves exactly as today (flat TSh 3,000 fee), no dispatch, no new pushes.
- Even with the flag on, the new flow is **opt-in per order**. Only clients that send `fulfilment: "dispatch"` on `POST /orders` (Sokoni 1.1+) get it. App 1.0 buyers keep the current flow.
- No new enum values in existing columns (`orders.status`, `orders.payment_method`, `payment_status`). App 1.0 parses these as strings and would show raw labels to sellers. Delivery state lives in new tables.
- `ReviewAccount`, the OTP review bypass and the `+255783029233` account are not touched.

## 1. What already exists

| Area | Today | Consequence |
|---|---|---|
| Orders | `pending > accepted > ready > completed / cancelled`, flat `DELIVERY_FEE = 3000` set at checkout, COD / pay-on-pickup only | Delivery fee is quoted *after* acceptance, for new-flow orders only |
| Seller location | `seller_profiles.lat/lng` + MySQL generated `shop_location` POINT; `DistanceQuery` has MySQL / MariaDB / SQLite (Haversine) paths | Reuse the same dialect pattern for "drivers near shop" |
| Push | `PushNotifier` is bound to `LogPushNotifier`, which **only logs; nothing is sent via FCM** server-side. It does write `app_notifications` | Delivery needs real FCM sending; driver offers cannot be log-only |
| Firebase | Project `sokoni-71910`, Android + iOS configs for `tz.co.sokoni.sokoni`; `kreait/laravel-firebase` installed but unused; `FIREBASE_CREDENTIALS` empty | Need a service-account JSON on the server and a new Firebase app for `tz.co.sokoni.driver` |
| Maps | `google_maps_flutter` removed; `flutter_map` + OSM tiles, no key | Driver/customer maps use `flutter_map`; "Navigate" opens Google Maps via URL |
| Payments | `PaymentGateway::charge(Order)` + `UnimplementedPaymentGateway`, unused | Keep it untouched; add a new collection interface beside it |
| Settings | `App\Support\Settings` (DB override, falls back to `config('sokoni.*')`) + Filament Settings page | Delivery pricing/dispatch values go here |
| Admin | Filament **v5** (spec said v3), `SellerVerificationQueue` page, admin/staff roles | Driver queue mirrors the seller queue |
| Hosting | cPanel shared: cron every minute, no queue worker, no long-running process | Rules out Reverb/WebSockets; dispatch waves driven by cron + lazy advancement |

## 2. Real-time: Firebase Realtime Database (recommended) vs Reverb

**Recommend Firebase RTDB for live location only.** Laravel stays the source of truth for jobs, status and money.

- Reverb needs a permanently running PHP process and an open port. Namecheap shared hosting kills long-running processes, so Reverb would need a separate VPS (about $5-10/month plus ops work).
- Polling Laravel every 4 s from both apps costs about 0.5 requests/second per active job. Twenty concurrent jobs would hit shared-hosting entry-process limits.
- RTDB is already in the Firebase project, scales independently of cPanel, and handles flaky connections well.

How it works:
1. Laravel mints a Firebase custom token (`kreait`) for the signed-in user: `POST /api/delivery/realtime-token`.
2. On assignment, Laravel writes `/tracking/{jobId}/members/{uid}: true` for the driver and the buyer only.
3. The driver app writes `/tracking/{jobId}/loc = {lat,lng,heading,ts}` every 4 s while on a job.
4. Security rules allow reads only to `members`, and writes to `loc` only by the job's driver.
5. On delivered/cancelled/failed, Laravel deletes `/tracking/{jobId}`. No history stays in Firebase.

Cost estimate (verify on Firebase's pricing page): one location write is about 150 bytes. A 30-minute job is about 450 writes, roughly 70 KB downloaded by the customer. 1,000 jobs/day is about 2 GB/month, inside the free download quota. The free Spark plan caps **100 simultaneous connections**, which you will hit early. Blaze (pay-as-you-go, with a budget alert) is recommended before launch. Expected bill at this scale: $0-5/month.

The online-driver heartbeat (every ~30 s) goes to Laravel, not RTDB, because dispatch queries driver positions in SQL.

## 3. Data model (all new tables)

```
driver_profiles     id, user_id (unique FK users), status enum(pending, approved, rejected, suspended),
                    rejection_reason, suspended_reason, photo, national_id_number (encrypted),
                    national_id_image, licence_number, licence_image, licence_expires_on,
                    is_online, last_lat, last_lng, last_located_at, rating_avg, rating_count,
                    jobs_completed, cancellations_count, approved_at, approved_by, terms_accepted_at, timestamps
vehicles            id, driver_profile_id, type enum(boda_boda, bajaj, car, bicycle),
                    plate_number (unique, nullable for bicycle), make, colour, photo, is_active, timestamps
delivery_jobs       id, order_id (unique), driver_profile_id nullable, vehicle_id nullable,
                    status enum(pending_payment, pending_driver, driver_assigned, arrived_at_shop, picked_up,
                                on_the_way, arrived, delivered, cancelled, failed, no_driver_found),
                    pickup_lat/lng/address (snapshot), dropoff_lat/lng/address (snapshot),
                    distance_km, distance_source enum(road, straight_line),
                    base_fee, per_km_rate, fee, commission_pct, commission, driver_earnings  -- frozen at quote time
                    delivery_pin (encrypted), pin_attempts, pickup_confirmed_at, proof_photo,
                    dispatch_wave, wave_started_at, cancelled_by, cancel_reason, failure_reason,
                    payout_id nullable, one <status>_at timestamp per status, timestamps
delivery_job_events id, delivery_job_id, actor_user_id nullable, actor_role enum(buyer, seller, driver, admin, system),
                    from_status, to_status, note, meta json, created_at          -- the shared timeline
delivery_offers     id, delivery_job_id, driver_profile_id, wave, distance_km,
                    status enum(offered, declined, expired, won, lost), offered_at, responded_at; unique(job, driver)
driver_locations    id, driver_profile_id, delivery_job_id nullable, lat, lng, heading, speed, accuracy, recorded_at
                    -- sampled every ~30 s server-side for disputes; purged after N hours
payments            id, order_id, payer_user_id, provider, channel (mpesa/airtel/mixx/halopesa/cash), msisdn (encrypted),
                    amount, currency, status enum(pending, succeeded, failed, refunded, cancelled),
                    idempotency_key (unique), provider_reference (unique nullable), failure_reason,
                    paid_at, refunded_at, meta json, timestamps
payment_webhook_events id, provider, event_id, payload json, signature_valid, processed_at, result;
                    unique(provider, event_id)
driver_payouts      id, driver_profile_id, amount, period_start, period_end, method, reference,
                    status enum(pending, paid, void), paid_at, created_by, note, timestamps
delivery_ratings    id, delivery_job_id, rater_user_id, ratee_user_id, direction enum(buyer_to_driver, driver_to_buyer),
                    rating 1-5, comment, timestamps; unique(delivery_job_id, direction)
```

One new nullable column on an existing table: `orders.fulfilment` (`null` = legacy flow, `'dispatch'` = new flow). For dispatch orders, `orders.delivery_fee` and `orders.total` are written once at quote time, so sellers on App 1.0 still see the correct total.

Drivers are ordinary `users` with a `driver_profiles` row. They sign in through the existing phone OTP and password routes and get a normal Sanctum token. A driver can never be assigned a job for an order they placed or a shop they own.

## 4. Flow and state machine

```
Buyer places order (fulfilment=dispatch, delivery_fee=0, total=subtotal)
Seller accepts (existing endpoint) -> job created, fee quoted, order.delivery_fee/total updated
   |- mobile money: job=pending_payment -> webhook succeeded -> pending_driver
   '- COD (if enabled): job=pending_driver immediately
pending_driver: wave 1 (3 km) -60s- wave 2 (5 km) -60s- wave 3 (8 km) -60s- no_driver_found
driver accepts -> driver_assigned -> arrived_at_shop -[pickup code]-> picked_up -> on_the_way -> arrived -[PIN]-> delivered
                                                                                   order.status = completed
cancelled / failed reachable per the rules in section 6
```

- **Fee** = `round_up_to_100(base + per_km x km)`, clamped to a minimum fee and a maximum distance. `km` comes from a `RouteDistance` interface: an OpenRouteService driver if a key is set, otherwise Haversine x 1.3.
- **Race-safe accept**: a single conditional update, `UPDATE delivery_jobs SET driver_profile_id=?, status='driver_assigned' WHERE id=? AND status='pending_driver' AND driver_profile_id IS NULL`. Exactly one driver gets an affected row; everyone else gets `409 already_taken`. A second guard rejects drivers who already have an active job. This behaves the same on MySQL and SQLite, so it is testable.
- **Waves without a queue worker**: the cron command `delivery:dispatch-tick` runs every minute. Any API read of a job (buyer polling, driver offers list) also calls `DispatchService::advanceIfDue($job)` under a row lock, so waves advance on time between cron ticks.
- **Job disappears for other drivers**: an FCM data message `offer_withdrawn`, plus the offers list re-fetch.
- **Pickup confirmation**: the driver enters the last 4 characters of the existing order code (`SKxxxxxxxx`). Sellers already see this code in App 1.0, so no seller-app release is needed.
- **Delivery PIN**: 4 digits, stored encrypted, shown only to the buyer. Five wrong attempts lock PIN entry and alert support.
- Every transition goes through one `DeliveryStateMachine`, writes a `delivery_job_events` row, and pushes to the other parties.

## 5. API (all behind `delivery.enabled` + `auth:sanctum`)

Driver app (`/api/driver`; `[approved]` = `EnsureApprovedDriver` middleware):
```
POST   /driver/profile                  create driver profile (after sign-in via existing /auth/* routes)
POST   /driver/profile/documents        national ID, licence, photo (multipart)
PUT    /driver/vehicle                  type + plate
GET    /driver/me                       profile, status, vehicle, today's stats
PATCH  /driver/availability             {online: bool}                       [approved]
POST   /driver/location                 {lat,lng,heading,speed,accuracy}     [approved, throttled]
GET    /driver/offers                   open offers for me                   [approved]
POST   /driver/offers/{job}/accept      race-safe                            [approved]
POST   /driver/offers/{job}/decline
GET    /driver/jobs/active ; /driver/jobs?status=past
POST   /driver/jobs/{job}/status        {status, pickup_code?, pin?, photo?, reason?}
POST   /driver/jobs/{job}/rating        rate buyer
GET    /driver/earnings?period=day|week totals, commission, per-job list, payouts
```
Customer app (`/api/delivery`):
```
GET    /delivery/orders/{order}         job, quote, status, driver card (phone only while active), PIN (buyer only)
POST   /delivery/orders/{order}/pay     {channel, msisdn, idempotency_key} -> starts USSD push
POST   /delivery/orders/{order}/retry ; /switch-to-pickup ; /cancel
POST   /delivery/orders/{order}/rating  rate driver
POST   /delivery/realtime-token         Firebase custom token for tracking
```
Webhook (no auth, signature-verified, throttled): `POST /api/payments/webhook/{provider}`.

A `DeliveryJobPolicy` restricts access to the order's buyer, the assigned driver, the order's seller (read-only, no live location) and admins. A driver sees offer details only for jobs offered to them: shop area and approximate drop-off area before accepting, full address and phone only after winning the job.

## 6. Proposed cancellation and failure rules (needs sign-off: money)

| Case | Proposal |
|---|---|
| Buyer cancels before a driver is assigned | Free; full refund if prepaid |
| Buyer cancels after assignment, before pickup | Driver gets a configurable cancellation fee (default TSh 1,000) out of the delivery fee; rest refunded |
| Buyer cancels after pickup | Not allowed in-app; contact support |
| Driver cancels before pickup | Job returns to `pending_driver` and the waves restart; driver's cancellation count goes up, auto-flag for admin at N in 7 days |
| Driver cannot continue after pickup | Marks `failed` with a reason; admin alerted; goods return to the shop |
| No driver after the last wave | `no_driver_found`; buyer chooses retry, switch to pickup (delivery fee refunded), or cancel (full refund) |
| Buyer unreachable at drop-off | After `arrived` + 10 min + at least 2 logged call attempts, driver may mark `failed (customer_unreachable)`; driver keeps earnings; item refund held for admin decision |

## 7. Payments recommendation (needs sign-off: money)

Use an aggregator behind a new `CollectionGateway` interface (`initiate`, `parseWebhook`, `queryStatus`, `refund`), with a `FakeCollectionGateway` and a local sandbox page that fires signed webhooks.

| Aggregator | Strengths | Weaknesses |
|---|---|---|
| **ClickPesa** | Already named in the original spec; USSD push across major networks; payouts API (useful later for driver payouts); sandbox | Smaller company; confirm M-Pesa coverage and settlement times in writing |
| **AzamPay** | Clean REST API and sandbox; MNO checkout for several networks | Disbursement maturity varies; confirm card support |
| **Selcom** | Most established; mobile money, cards, bank, widest coverage | Heaviest merchant onboarding; dated API ergonomics |

Recommendation: **ClickPesa first.** The codebase already planned for it, and its payouts API could automate driver payouts later. Rates and network coverage change often, so confirm them during merchant onboarding.

Whichever provider: only a verified webhook (or a server-side status query) marks a payment paid; the app is never trusted. Idempotency comes from a unique client `idempotency_key` per attempt, unique `(provider, event_id)` on webhook events, and `lockForUpdate` on the payment row.

**Regulatory flag:** if Sokoni collects the full order amount and later pays sellers and drivers, the platform is holding customer funds. Ask the aggregator (and if needed a lawyer) whether their split/escrow product covers this under Bank of Tanzania rules.

## 8. Driver app (`/driver_app`, `tz.co.sokoni.driver`)

Same stack and conventions as `/app`: Riverpod + generator, go_router, dio/retrofit/freezed, drift (offline queue of status updates), `flutter_secure_storage`, Plus Jakarta Sans, Sokoni palette, EN/SW ARB files defaulting to Swahili. Adds `flutter_map`, `geolocator` (Android foreground service with a persistent notification; iOS `location` background mode), `firebase_messaging`, `firebase_auth` + `firebase_database` for tracking, `url_launcher` for Google Maps navigation and calls, `image_picker` for documents and the proof photo.

iOS background location gets extra App Review scrutiny. Plan: ask "While Using" first, request "Always" only when going online, show a plain-language explainer before each system prompt, and stop location updates entirely when offline.

## 9. Phases (each ends with tests + your approval)

1. This plan.
2. Backend: migrations, fee calculator, job creation on accept, dispatch waves, race-safe accept, state machine, PIN/pickup code, events, pushes, policies, tests.
3. Driver app.
4. Sokoni app 1.1: quote/total screen, pay, tracking map, driver card, PIN, ratings.
5. Payment aggregator integration + Filament jobs, payouts, delivery settings. (The Filament driver review queue shipped early as phase 5a; see DECISIONS.md.)
6. Hardening, rate limits, logging, DEPLOY.md, store-submission notes.
