# Delivery by Bolt

Every Sokoni delivery is carried out by a Bolt driver, booked by Sokoni through the **Bolt Ride Booker API** and paid from Sokoni's Bolt Business account. The Sokoni Driver app and own-driver dispatch are retired (git tag `pre-bolt-sokoni-drivers` keeps them). Returns are in [RETURNS.md](RETURNS.md); the production deploy is [RELEASE.md](RELEASE.md).

Everything here is **dark**: off by default, and invisible to everyone outside the test until you switch it on.

## 1. How a delivery goes

```
buyer checks out with "Delivery" (Bolt)         Bolt estimate shown: range, the upper amount, ETA,
                                                and the shop's return policy
shop accepts (existing button)                  job priced: Bolt's cheapest category with an ETA,
                                                fee = the estimate's upper bound          pending_quote -> pending_payment
buyer pays the delivery fee in the app          Pay now (mobile money)                    -> awaiting_ready
shop marks the order ready (existing button)    Bolt ride booked at once, fresh fare_id   -> pending_driver (SEARCHING)
Bolt                                            DRIVER_ON_ROUTE_TO_CLIENT                 -> driver_assigned
                                                ARRIVED_AT_CLIENT (at the shop)           -> arrived_at_shop
                                                DRIVING_WITH_CLIENT (parcel in the car)   -> on_the_way
                                                COMPLETED                                 -> delivered, order completed, receipt fetched
```

- **Goods money** goes from the buyer to the shop directly, as before. Only the delivery fee goes through Sokoni.
- **Payment and readiness can come in either order.** The ride is booked when the second one happens.
- **Bolt's "client" is the shop.** Sokoni books the ride with the shop as the rider (shop name + phone) and the customer as the destination. The note to the driver says: `Sokoni order SK…. Collect parcel from shop, hand to customer: <name> <phone>. Chukua mzigo dukani, mkabidhi mteja.` The phone is the one the buyer gave at checkout (or their account phone).
- **What the buyer sees:** the Bolt driver's name, car, plate and photo, a call button while the driver is on the job, **Track on Bolt** (Bolt's tracking page) while the ride runs, and, from driver assigned until Bolt completes the ride, **Track live on the map**: Sokoni's own map with the shop, the drop-off and the driver moving, the ETA, the timeline, the call button and Track on Bolt (§ 4, "Live tracking").
- **What the shop sees:** whether the fee is paid ("pack it and tap Mark as Ready"), then the Bolt driver and the tracking link until pickup. Never the fee, the drop-off or the buyer's phone.

### Money

- The buyer pays the **upper bound** of Bolt's estimate when the shop accepted, rounded up to a whole shilling. The app says so at checkout ("Bolt estimate: TSh 5,200 to TSh 6,400 · You pay for delivery: TSh 6,400 · Bolt's final price for the trip can be lower"), again on Pay now, and after delivery shows the fee paid next to **Bolt's receipt** (`final_cost`), or "Waiting for Bolt" until it arrives. The difference is not refunded to the buyer automatically (see DECISIONS.md, 2026-11-19).
- After COMPLETED, Bolt's receipt is fetched (retried for 3 days; Bolt says usually minutes, sometimes 24 h+). `final_cost` and `cost_difference = fee − final_cost` are stored. A negative difference means Sokoni paid more than it collected. Admin → **Commerce → Bolt deliveries** has a "Bolt charged more than the customer paid" filter.
- **Refunds are recorded, not paid automatically.** A paid fee is marked `refund_status = pending` when the buyer switches to pickup, when the delivery is cancelled before the parcel leaves the shop (including after Bolt couldn't deliver), or when a payment arrives after the delivery was already cancelled. An admin pays it and taps **Mark refund paid**.
- **Buyer cancels after Bolt assigned a driver:** Sokoni keeps `SOKONI_DELIVERY_BUYER_CANCEL_FEE` (default TSh 1,000) to cover Bolt's own cancellation charge, and refunds the rest. A shop or admin cancel refunds in full.
- **Unpaid fee:** a delivery still unpaid 20 minutes after the price was set (`SOKONI_DELIVERY_UNPAID_TIMEOUT_MINUTES`) is cancelled with its order. Nothing is ever booked on Bolt before payment, so there is nothing to cancel there.

### When Bolt can't do it

| What happened | Buyer sees | Buyer can |
|---|---|---|
| No category with an ETA, or Bolt `NO_DRIVER_FOUND` | No Bolt driver available | Book again, switch to pickup, cancel |
| `AREA_NOT_SERVICED` / `NO_MATCHING_CITY` | Bolt does not deliver on this route | Switch to pickup, cancel |
| `CLIENT_DID_NOT_SHOW` (driver couldn't collect at the shop) | Couldn't collect at the shop | Book again, switch to pickup, cancel |
| `PAYMENT_BOOKING_FAILED` (Sokoni's Bolt account) | We could not book a driver | Book again, switch to pickup, cancel. **Support is alerted (critical log).** |
| `CANCELLED` / `CLIENT_CANCELLED` before pickup | Bolt cancelled the trip | Book again, switch to pickup, cancel |
| `CANCELLED` with the parcel in the car | Sokoni support will contact you | Nothing in the app. **Critical log; an admin handles it.** |
| Bolt 429 / 5xx / unreachable | (nothing yet) | Retried 1, 2, 4, 8… minutes apart by `delivery:tick`, up to `BOLT_MAX_BOOKING_ATTEMPTS` (5), then "could not book" |
| Bolt refuses the request (400, landline, validation) | We could not book a driver | Book again, switch to pickup, cancel |

A paid fee is refunded in full on switch-to-pickup or cancel in all of these. "Book again" reuses the paid fee and books with a new `fare_id`.

**Cancelling:** before the parcel leaves the shop the Bolt ride is cancelled first, after a fresh read of its state, so a parcel that left the shop a few seconds ago is never cancelled from a stale status. Once the parcel is in the car only an admin can cancel. If Bolt refuses a cancel, the delivery is still cancelled in Sokoni and the failure goes on the timeline (`provider_cancel_failed`) for an admin to check the bill.

## 2. Flags (dark launch)

| `.env` | Default | Meaning |
|---|---|---|
| `BOLT_ENABLED` | `false` | Master switch. Replaces the old `DELIVERY_ENABLED`, which is no longer read. |
| `BOLT_TEST_PHONES` | empty | Comma-separated E.164 numbers. **Set:** only these accounts see delivery and returns, and a delivery order needs **both** the buyer and the shop owner on the list, so no real shop is ever sent a driver it doesn't expect. **Empty:** everyone. |
| `DELIVERY_RETURNS_ENABLED` | `false` | Returns ([RETURNS.md](RETURNS.md)). Needs `BOLT_ENABLED` too. |
| `REVIEW_ACCOUNT_PHONE` | (existing) | The App Store review account **never** sees delivery or returns, even if it is in `BOLT_TEST_PHONES`. |

For anyone outside the launch the API behaves exactly as it did before delivery existed:

- every `/api/delivery/*`, `/api/returns*`, `/api/shop/return-policy` and `/api/webhooks/bolt` route answers like a route that doesn't exist (404 with Laravel's "The route … could not be found.", signed in or not)
- checkout ignores `fulfilment` and `delivery_phone` without validating them
- no order, shop or product response carries a delivery or return field (orders only show `fulfilment` on a Bolt order)
- the scheduler registers nothing new while there is neither `BOLT_ENABLED` nor a `BOLT_CLIENT_ID`

This was checked by replaying 26 requests the live apps make against production's code and this branch: identical, apart from the new `GET /api/features` and one fix already on `feature/delivery` (`POST /api/orders` now returns `status: "pending"` and `payment_status: "unpaid"` instead of `null`; see [RELEASE.md](RELEASE.md) § "What changes for the live apps").

**The app asks the server.** `GET /api/features` returns `{ delivery_enabled, returns_enabled }` for the signed-in user (plus the returns Terms state while returns are on). The app shows delivery and return UI only when it says so. Guests, older servers and errors all mean "off".

## 3. `.env` keys

Enter them on the server yourself. They are never committed, and none of them ever ships inside the app.

```
BOLT_ENABLED=false
DELIVERY_RETURNS_ENABLED=false
BOLT_TEST_PHONES=
BOLT_CLIENT_ID=
BOLT_CLIENT_SECRET=
BOLT_ENV=sandbox
BOLT_SANDBOX_UUID=
BOLT_WEBHOOK_TOKEN=
```

| Key | Where it comes from |
|---|---|
| `BOLT_CLIENT_ID`, `BOLT_CLIENT_SECRET` | ridebooker.bolt.eu → Ride Booker API → **Renew**. Write the secret down: Bolt shows it once. |
| `BOLT_ENV` | `sandbox` or `live`. |
| `BOLT_SANDBOX_UUID` | `php artisan bolt:sandbox create` (sandbox only; see § 5). |
| `BOLT_WEBHOOK_TOKEN` | A long random string you make up (e.g. 48 characters) and send to Bolt when asking them to enable webhooks. Empty = the webhook route doesn't exist. |

Optional overrides (defaults in `api/config/delivery.php`): `BOLT_LIVE_BASE_URL`, `BOLT_SANDBOX_BASE_URL`, `BOLT_CANCEL_PATH`, `BOLT_LOCATION_PATH` (default `rides/location`), `BOLT_LOCATION_CACHE_SECONDS` (5), `BOLT_WEBHOOK_IPS` (default Bolt's three: `18.197.164.122,18.184.149.227,3.120.3.19`), `BOLT_MAX_BOOKING_ATTEMPTS`, `BOLT_TIMEOUT_SECONDS`, plus Sokoni's own `SOKONI_DELIVERY_MAX_DISTANCE_KM` (10), `SOKONI_DELIVERY_BUYER_CANCEL_FEE` (1000), `SOKONI_DELIVERY_UNPAID_TIMEOUT_MINUTES` (20).

**Payment:** the delivery fee is collected with the existing Pay now flow. On production that needs a real `PAYMENT_PROVIDER`: today it is `none`, and the sandbox `fake` gateway is refused in production whatever `.env` says. Until a real provider exists, an admin can record a fee paid outside the app (M-Pesa to the company number) in **Bolt deliveries → Record fee paid**, which books the ride exactly like an in-app payment.

## 4. Under the hood

- **Auth:** OAuth2 client credentials at `https://oidc.bolt.eu/token`, scope `ridebooker:api`. The token (10 minutes) is cached and replaced 2 minutes before it expires, and once more on a 401.
- **Estimate → book:** `POST /rides/estimations` with the two stops (shop, customer). The cheapest category whose `eta` is not null wins. Booking always estimates **again** and creates the ride with that new `fare_id` at once (`fare_id`s expire in 5 minutes and are single use). The checkout price is cached for 2 minutes without its `fare_id`. If Bolt refuses a `fare_id` (`INVALID_FARE_ID`, `FARE_ID_DECRYPTION_ERROR`) it estimates once more.
- **Tracking:** `delivery:track` runs every 10 seconds (Bolt's fair-use limit for ride details) and reads every running ride. The buyer's and the shop's own screen polls refresh a ride too, at most once per 10 seconds per ride. `delivery:tick` (every minute) settles payments, cancels unpaid deliveries, retries quotes and bookings, and fetches receipts. Both are registered only once Bolt is switched on or has credentials, because a sub-minute task keeps `schedule:run` alive for the whole minute. On cPanel, check the account's CPU/entry-process limits when you switch Bolt on.
- **Live tracking:** the buyer's map polls `GET /api/delivery/orders/{order}/location` every 5 seconds while it is open and the app is in the foreground, and stops when the ride ends. The server asks Bolt's ride-location endpoint (`GET {base}/reseller/v1/{BOLT_LOCATION_PATH}?ride_id=…`; Bolt allows it every second) at most once per ride per 5 seconds, whoever polls: the answer is cached per ride. No fix (`UNABLE_TO_LOCATE_DRIVER`) or any other error shows the last known position as stale; a 429 leaves Bolt alone for its `Retry-After`; one attempt per poll, never a backoff sleep in a web request. The same poll refreshes the ride's state within the order screen's 10-second window, so it adds no ride-details calls. The map is OpenStreetMap through flutter_map, as at checkout: no map key. Admins see the same position (with a link that opens it on OpenStreetMap) on a running delivery's or return ride's page.
- **Webhook:** `POST /api/webhooks/bolt`, `Authorization: Bearer <BOLT_WEBHOOK_TOKEN>`, from Bolt's IPs only, body `{ ride_id, type, state }`. The body only says "this ride changed": the details are read back from Bolt, and the state from the body is used only if that read fails. Unknown rides answer 200 so Bolt doesn't retry. Bolt sends webhooks for **live** accounts only, after you ask them; polling keeps running as the safety net.
- **Errors:** every failed Bolt call is logged with `x-bolt-error-code`, `x-bolt-error-message` and `x-bolt-tracking` (what Bolt support asks for), and booking errors also go on the delivery's timeline (Admin → Bolt deliveries → the delivery). Retries: 3 tries per call with backoff (honouring `Retry-After`), then the minute cron's 1, 2, 4… minute backoff.
- **Code:** `app/Services/Delivery/Bolt/` (client, booker, state map), `DeliveryJobService` (the lifecycle), `DeliveryAccess` (who sees what), `routes/api_delivery.php`, `BoltWebhookController`.

### API (app)

All need `Authorization: Bearer <token>` and the user inside the launch.

| Route | |
|---|---|
| `GET /api/features` | Public. `{ delivery_enabled, returns_enabled }` |
| `GET /api/delivery/config` | Payment channels, sandbox, poll seconds, `returns_enabled` |
| `GET /api/delivery/quote?seller_id&lat&lng` | `{ fee, min, max, currency, category, eta_seconds, distance_km, return_policy }`. `422 { code }`: `not_available` (shop outside the test), `too_far`, `shop_has_no_location`, `area_not_serviced`, `no_driver_available`; `503 quote_unavailable` |
| `POST /api/orders` | Existing; add `fulfilment: "dispatch"`, `delivery_lat/lng`, optional `delivery_phone` |
| `GET /api/delivery/orders/{order}` | Buyer or shop view (statuses above, `driver`, `tracking_url`, `payment`, `actions`, `timeline`, `return`; buyer also `live_location`, `final_cost`) |
| `GET /api/delivery/orders/{order}/location` | Buyer only. The live map: `{ status, active, location: { lat, lng, bearing, recorded_at }, stale, reason, eta, pickup, dropoff, poll_seconds }` ([API.md](API.md)) |
| `POST /api/delivery/orders/{order}/pay` | `{ channel, msisdn, idempotency_key }` |
| `POST …/cancel`, `…/retry`, `…/switch-to-pickup` | Buyer |

## 5. Sandbox, end to end

On staging (or locally):

1. `.env`: `BOLT_ENABLED=true`, `BOLT_ENV=sandbox`, `BOLT_CLIENT_ID`/`BOLT_CLIENT_SECRET`, `PAYMENT_PROVIDER=fake`, `BOLT_TEST_PHONES=<buyer phone>,<shop owner phone>`.
2. `php artisan bolt:sandbox create`. Put the printed `BOLT_SANDBOX_UUID=…` in `.env` and clear the config cache.
3. In the app (1.1, signed in as the test buyer): checkout → Delivery → pin → the Bolt estimate shows (sandbox prices are Bolt's mock values) → Place order.
4. As the test shop: Accept. As the buyer: Pay now (the fake gateway approves after 8 s). As the shop: **Mark as Ready**. The ride is booked: the order shows "Finding a Bolt driver".
5. Move the ride as a Bolt driver would, by order code or ride id. Each command also syncs the delivery straight away:
   ```
   php artisan bolt:sandbox state SK7KX2M9QD DRIVER_ON_ROUTE_TO_CLIENT
   php artisan bolt:sandbox state SK7KX2M9QD ARRIVED_AT_CLIENT
   php artisan bolt:sandbox state SK7KX2M9QD DRIVING_WITH_CLIENT
   php artisan bolt:sandbox state SK7KX2M9QD COMPLETED
   ```
   Try `NO_DRIVER_FOUND`, `CLIENT_DID_NOT_SHOW`, `PAYMENT_BOOKING_FAILED` and `CANCELLED` on other orders to see the problem screens.
6. `php artisan bolt:sandbox destroy` when done. The command refuses to run with `BOLT_ENV=live`.

The full flow also runs in the test suite without network (`api/tests/Feature/Delivery/Bolt/`, faked with `Http::fake`).

## 6. Confirm before going live

Bolt's PDF documents the sandbox paths but leaves the live base URL and the on-demand cancel path to its Swagger file, which wasn't available here. Before `BOLT_ENV=live`:

- [ ] **Live base URL.** Default `https://node.bolt.eu/ride-booker-api` (the sandbox one without `-sandbox`). Override with `BOLT_LIVE_BASE_URL` if the Swagger says otherwise.
- [ ] **Cancel path.** Default `POST {base}/reseller/v1/rides/cancel` with `{ ride_id }`. Override with `BOLT_CANCEL_PATH`.
- [ ] **Ride-location path and body.** Default `GET {base}/reseller/v1/rides/location?ride_id=…`. Override with `BOLT_LOCATION_PATH`. The body is read as `lat`/`lng` or `latitude`/`longitude`, at the top level or under `location`, `driver_location`, `position` or `data`, with an optional time and bearing; a body without coordinates is treated as "unavailable", never as a pin. Check the Swagger's field names; if Bolt nests them somewhere else, `BoltDriverLocation::CONTAINERS` is the one list to extend. In the sandbox, try it with `bolt:sandbox state … DRIVER_ON_ROUTE_TO_CLIENT` and the map; if the sandbox has no such endpoint the map shows "Locating the driver…" and everything else still works.
- [ ] **Parcels on Ride Booker.** Ride Booker is a passenger product; Bolt's docs ask partners not to use multi-stop rides for independent riders. Confirm in writing with your Bolt representative that sending a parcel with no passenger (shop as rider, customer as destination) is allowed in Tanzania, and whether Bolt Send / a delivery category exists there.
- [ ] **Currency.** Live estimates must be in TZS; a live estimate in any other currency is refused (logged) rather than charged.
- [ ] **Bolt Business payment method** set up and in good standing (`PAYMENT_BOOKING_FAILED` otherwise).
- [ ] **Webhooks:** send Bolt `https://api.sokoni.co.tz/api/webhooks/bolt` and your `BOLT_WEBHOOK_TOKEN`.
- [ ] **A real payment provider** (`PAYMENT_PROVIDER`), or agree that admins record fees paid outside the app for the test period.

## 7. Rollout

1. **Deploy dark** ([RELEASE.md](RELEASE.md)): code and migrations live, all flags off. Nothing changes for the apps in the stores.
2. **Staging, sandbox:** § 5 with the 1.1 app pointed at staging.
3. **Production, test phones, live Bolt account:** set `BOLT_ENV=live`, credentials, `BOLT_TEST_PHONES=<staff buyer>,<staff shop>`, `BOLT_ENABLED=true`. Clear the config cache. Only those two accounts see anything. Do a few real deliveries; compare fee against receipt in Admin → Bolt deliveries.
4. **Returns, same test phones:** `DELIVERY_RETURNS_ENABLED=true` ([RETURNS.md](RETURNS.md)).
5. **Release app 1.1** to the stores (see the final report for the recommended version), still behind the flags.
6. **Open up:** empty `BOLT_TEST_PHONES`. Everyone but the review account sees Bolt delivery.

**Switching off again:** set `BOLT_ENABLED=false` and clear the config cache. New delivery orders stop at once and the routes disappear. Deliveries already in progress keep being tracked and completed in the background while the Bolt credentials stay in `.env`, so no paid order is stranded, but their order screens lose the delivery card until it is switched on again. Prefer switching off when Admin → Bolt deliveries shows nothing in progress.
