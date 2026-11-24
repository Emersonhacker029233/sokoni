# Delivery decisions log

Judgement calls made while building the delivery system, one line of rationale each. Client answers from 2026-10-29 are marked **(client)**.

## Phase 2 (backend), 2026-10-29

- **The delivery fee is never added to `orders.total` for dispatch orders.** (client) Customers pay only the delivery fee online, and item money goes to the seller directly. Writing the fee into the order would show sellers on Sokoni 1.0 money that isn't theirs. The fee lives on `delivery_jobs.fee`.
- **Opt-in per order via `fulfilment: "dispatch"`.** With the flag on, Sokoni 1.0 clients still get the flat TSh 3,000 legacy flow because they never send the field.
- **No new enum values on existing columns.** 1.0 parses `orders.status` and `payment_method` as plain strings and would show raw labels. New tables use string columns validated in code, so adding a state later needs no `ALTER` on live MySQL.
- **Job created by an `Order` model observer on `accepted`.** The existing order endpoints stay byte-for-byte the same, and admin status changes are covered too. The delivery side writes orders with `saveQuietly()` so it never loops.
- **Fee rounding.** `ceil` to the next TSh 100 after rounding away float noise, then the minimum fee. Commission is rounded and driver earnings is fee minus commission, so the two always sum to the fee exactly.
- **Quote frozen at seller acceptance; checkout shows an estimate.** Max distance is enforced at checkout. At acceptance the job is priced even if the shop has since moved, because the seller already committed.
- **Race-safe accept.** A conditional `UPDATE ... WHERE status='pending_driver' AND driver_profile_id IS NULL` picks exactly one winner. The driver's own row is locked first, so one driver can't win two jobs at once. This works the same on MySQL, MariaDB and SQLite.
- **Waves advance from the cron and lazily on reads.** There is no queue worker on shared hosting. Driver offer polling and buyer job polling also advance due waves, behind a short cache lock.
- **Drivers whose heartbeat is older than 120 s are not offered jobs.** The app sends one every ~30 s.
- **Pickup confirmation is the last 4 characters of the existing order code.** Sellers already see it in 1.0, so no seller-app release is needed. It is also returned in the seller's delivery view for 1.1.
- **Delivery PIN locks after 5 wrong attempts.** It is stored encrypted, and the buyer gets a push and support is logged. Unlocking is an admin action (phase 5).
- **Real FCM for delivery only, through a separate `DeliveryNotifier`.** (client) `PushNotifier` and `LogPushNotifier` are untouched. Customer-app delivery pushes also go to the existing in-app inbox. Driver-app pushes do not.
- **`devices.app` column.** One phone may have both apps installed. Driver offers must reach the driver app only, and existing rows stay `null`, meaning the customer app.
- **Firebase is used only when the credentials file exists.** Otherwise pushes are logged and the realtime-token endpoint returns `503 { fallback: "poll" }`. Nothing breaks before credentials arrive.
- **Seller cancelling after assignment compensates the driver with the cancellation fee, platform-funded, and refunds the buyer in full.** (client, confirmed 2026-11-01) Section 6 didn't cover the seller-cancel case. This is a setting (`delivery_seller_cancel_compensates_driver`, default on).
- **Cash-fee jobs cancelled by the buyer after assignment earn the driver nothing.** No money was collected, so there's nothing to pay a cancellation fee from. A seller cancel still pays the driver, because Sokoni funds that one.
- **Driver-side failure after pickup earns 0, and the order stays open.** The buyer can retry, switch to pickup, or cancel. An unreachable customer cancels the order, the driver keeps earnings, and the fee is not refunded. (client)
- **Driver documents go on the private `local` disk.** Driver photos are exposed only through 30-minute signed URLs. ID and licence images never leave the server except to the admin panel.
- **The national ID number is encrypted at rest.** Only the last 4 digits are returned to the driver's own app.
- **Breadcrumbs are sampled every 30 s while on a job and purged after 72 h by an hourly command.** (client) The 4-second live stream never touches MySQL.
- **Separate decisions file.** The root `DECISIONS.md` had uncommitted edits on `main` when this branch was cut, so delivery decisions live here to avoid mixing changes.

## Phase 2 follow-up, client answers 2026-11-01

- **Every seller cancel after assignment is tagged `seller_cancelled_after_assign`.** (client) The `cancelled` status event carries `meta.reason`, which the shared timeline returns as `reason`. A second event of that type records the driver, the compensation, who funds it and the buyer refund, so admins can query every case and spot repeat offenders. Seller cancels before a driver is assigned are not tagged, because they cost the platform nothing.
- **Unpaid jobs auto-cancel after 20 minutes.** (client) The timeout is the `delivery_unpaid_timeout_minutes` setting (env `SOKONI_DELIVERY_UNPAID_TIMEOUT_MINUTES`). `delivery:dispatch-tick` runs it every minute, so a job can live up to one minute past the timeout. It is measured from job creation, which is the moment the seller accepted.
- **The order is cancelled together with the job.** The buyer chose dispatch and never paid, so there is nothing to deliver. The seller gets a push saying not to prepare it, because App 1.0 sellers would otherwise only see the status change. The order's `cancelled_reason` is written in the seller's language, since the seller reads it in 1.0.
- **A payment that lands at the same moment always wins.** The cancel re-checks `pending_payment` under the row lock. A payment confirmed after the cancel is kept as paid with a pending full refund, using the existing late-payment path.

## Phase 3 (Sokoni Driver app), 2026-11-01

- **The driver job view masks the order code until pickup.** (backend fix) The pickup code is the last 4 characters of the order code, and the driver view returned the full code. A driver could have "confirmed" a pickup without meeting the seller. Drivers now see `SKAB12••••` until `picked_up`. Buyers and sellers are unchanged.
- **Same stack and conventions as `/app`.** Freezed models, retrofit APIs returning raw JSON parsed in repositories, hand-written Riverpod notifiers (the customer app doesn't use the generator either), drift, the same theme files copied verbatim and the same Android toolchain (Gradle 8.14, Android Gradle Plugin 8.11.1, Kotlin 2.2.20). The newer Flutter template defaults were dropped so both apps build with one setup.
- **Phone + SMS code is the main sign-in; password sign-in is the secondary link.** Drivers are phone-first, and the existing OTP route creates the account for someone new. A password user on a new device gets the usual one SMS step.
- **Swahili by default, whatever the phone's language.** (plan) An explicit choice in Profile wins and survives sign-out.
- **Onboarding order is details, vehicle, documents, review.** `PUT /driver/vehicle` needs the profile row that `POST /driver/profile` creates, so details come first. Licence fields are optional on screen with a "not needed for bicycles" hint. The review step lists whatever the server still reports missing, and tapping an item jumps to its step.
- **Each document uploads the moment it's taken,** compressed to 1600 px JPEG 80. A dropped connection loses one photo, never the whole step.
- **No countdown on offers.** An offer stays open until another driver takes the job or dispatch gives up (`openOffersFor` only filters on `offered` + `pending_driver`). A ticking timer would lie. New offers buzz and play the system alert sound.
- **Polling cadence.** Offers every 10 s while online and idle, the active job every 15 s (catches a cancellation if the push is lost), heartbeat every 30 s. During a job without the Firebase stream the heartbeat drops to 10 s, which the throttle (30/min) allows, so the buyer's map still moves.
- **Offline queue covers only steps with no secret.** Arrived at shop, on the way, arrived and call attempts are queued in drift and replayed in order. The screen advances straight away from a local copy of the transition table. Pickup and delivery need the seller's code or the customer's PIN checked live, so they ask for a connection. Before any live step the queue is flushed first, so steps never reach the server out of order. A queued step the server refuses (for example the job was cancelled meanwhile) is dropped and shown, never retried forever.
- **"Customer unreachable" is greyed out in the app until 10 minutes and 2 calls.** The server enforces it; the app only explains why the option isn't available yet.
- **Location without `ACCESS_BACKGROUND_LOCATION` on Android.** A location foreground service started while the app is open keeps running in the background, and Play treats the background permission as a sensitive declaration. iOS asks for "Always" only when going online, after an explainer, and keeps working with "While using" if refused.
- **Firebase is optional at build and run time.** The Google Services Gradle plugin is applied only when `google-services.json` exists, and every Firebase call checks one bootstrap first. Without it the app polls and uses the faster heartbeat.
- **Release builds are HTTPS-only.** `usesCleartextTraffic` is true only for debug builds, so a laptop running `php artisan serve` can be used for testing.
- **Driver icon is the customer icon inverted onto black,** generated by `driver_app/tool/make_icon.dart`, so the two apps are told apart at a glance on one phone.
- **Map tiles come from the public OpenStreetMap server,** as in the customer app. That's fine for testing. A driver app shows a map for hours a day, which the OSM tile policy discourages at scale. A paid tile provider (MapTiler, Stadia, Mapbox) is a one-line `urlTemplate` change before launch.

## Open items after phase 3

- **Firebase app for `tz.co.sokoni.driver`.** Register it in project `sokoni-71910`, download `google-services.json` and `GoogleService-Info.plist` into `driver_app/android/app/` and `driver_app/ios/Runner/`. Also put the service-account JSON on the server (`FIREBASE_CREDENTIALS`) and the database URL, then deploy `firebase-database.rules.json`. Until then: no push, no live tracking stream.
- **Release keystore for the driver app.** Release builds are debug-signed until `driver_app/android/key.properties` exists. Fine for a test APK, not for Play.
- **iOS build not verified.** No Mac here. Info.plist has the location, camera and background-mode strings. Run `pod install` and a build on a Mac before TestFlight. Apple reviews background location closely; the explainer copy is in the ARB files.
- **Flutter 3.47 needs Android NDK 28.2.13676358 to build any APK, for both apps.** It was installed on this machine on 2026-11-01 with `android sdk install ndk/28.2.13676358`; the old `sdkmanager` syntax no longer works. Any other build machine needs the same.
- **The production API has `DELIVERY_ENABLED=false`.** A test APK pointed at production shows "Deliveries aren't open yet". Test against a local or staging server with the flag on.

## Phase 5a: driver review in the admin panel, 2026-11-03

Brought forward from phase 5 at the client's request. Payments, payouts and delivery settings stay in phase 5.

- **A Filament resource, not a custom queue page.** Admin > Trust & Safety > **Drivers** opens on "Pending review" (submitted applications plus approved drivers whose new vehicle hasn't been checked), oldest submission first. Other filters: not submitted yet, approved, rejected, suspended. The nav item shows how many are waiting. Decisions happen on the review page, where the documents are, never from the list.
- **Admin role only.** (client) `DriverProfilePolicy` and the document route require `role = admin`. Staff, who can verify sellers, can't see drivers or their identity documents. Widening it to staff is a one-line policy change.
- **One service for every decision.** `DriverReviewService` (approve, reject, suspend, reinstate, unlock PIN) re-reads the driver under a row lock, changes the state, writes `activity_logs` with the admin as causer, then notifies. Same shape as `SellerVerificationService`.
- **Approve also verifies the active vehicle.** For an already approved driver who changed vehicle, the same button reads "Approve vehicle" and only verifies the vehicle. Approval is refused while anything is missing or the licence has expired.
- **Reject needs a reason, at most 250 characters.** The app already shows it on the "needs changes" screen with "Fix and resubmit". Reject also works on a vehicle check, which takes the driver back to resubmission.
- **Suspend needs a reason and is refused while the driver has a delivery in progress.** Suspended drivers are refused on every work route, so they couldn't finish the job. Their open offers are marked withdrawn. Reinstate returns them to approved.
- **Unlock delivery PIN** (the phase-2 "admin action") resets the attempt counter and the lock, keeps the same PIN (the customer already has it), records a `pin_unlocked` event on the job timeline with the admin, and tells both driver and buyer.
- **How the driver hears about a decision:** an in-app record in `app_notifications` every time; a push to Sokoni Driver when real FCM is configured and the phone has registered; otherwise an SMS through the configured SMS driver (Swahili or English by the driver's language; the reason is cut to 100 characters in the SMS). The activity log records which channels were used. Suspensions and reinstatements notify the same way.
- **The in-app record lands in the Sokoni customer-app inbox** of the same account, the only inbox that exists. That inbox shows title and body only, so the new rows are safe for Sokoni 1.0.
- **Sokoni Driver registers for push as soon as the driver signs in,** not only once approved. Otherwise a pending driver's phone could never receive the approval push. A `driver_account` push re-reads the profile; returning to the app on the status screen re-checks too, which covers the SMS case.
- **Documents never get a public URL.** They stay on the private `local` disk, whose file route needs a signature the API only issues for the driver photo. Admins read them through `/admin/driver-documents/{driver}/{document}`, inside the panel's login, with an admin-role check, `Cache-Control: private, no-store` and a download option. The full NIDA number is shown to admins only, on the review page.
- **Not logged: document views.** Opening the review page loads every image, which would flood the activity log. Revisit if a privacy audit asks who viewed which ID.

## Staging: Drivers screen missing from the admin panel, 2026-11-04

- **Cause: a stale Filament component cache on the server.** Filament writes the list of panel resources to `bootstrap/cache/filament/panels/admin.php` when `php artisan optimize` or `filament:optimize` runs, and from then on uses that list instead of scanning `app/Filament`. A cache written before 76d003a doesn't contain `DriverResource`, so the menu item and the `/admin/drivers` route simply don't exist, while every other admin page works. Reproduced locally: with such a cache `/admin/drivers` answers 404, and deleting the file brings it back. Extracting a new zip over the folder never removes that file.
- **Ruled out:** the policy (admin role plus `is_admin`, as the account has), the navigation group (same `Trust & Safety` string as Users, Reports and Seller verification), discovery (recursive over `app/Filament/Resources`; the package's autoloader knows the class), and OPcache (it can keep an old copy of a changed file, but it can't hide a new resource, because discovery reads the folder).
- **Staging package is now built by `tools/build-api-staging.ps1`** from a git commit, never from the working folder. It installs `vendor/` with Composer scripts off, ships no file under `bootstrap/cache` except `.gitignore`, no compiled views, sessions, logs, `.env` or tests, writes `BUILD.txt` with the commit, and refuses to leave a zip behind that breaks any of this. Laravel rebuilds `packages.php` and `services.php` on the first request.
- **Update packages leave out `public/.htaccess` and `public/.user.ini`.** The 2026-11-03 package included them, and "extract with overwrite" replaces the staging `.htaccess`, whose admin directory-password block (STAGING-STEPS.md step 10) cPanel added there. `-FirstInstall` puts both back in for a new server.
- **`BUILD.txt` also detects a skipped extract.** Earlier deploy notes say cPanel's Extract sometimes keeps existing files. If `BUILD.txt` on the server still shows an older commit after extracting, the files weren't replaced.
- **Test:** the Drivers resource is discovered, admins see it under Trust & Safety, staff don't. Staff are checked first in their own test because Filament keeps the built navigation on the panel for the rest of the process, so a second account in the same test would see the first account's menu.

## Phase 4 (Sokoni 1.1 customer app), 2026-11-04

- **"Pay now" charges what the server says is due, and today that is the delivery fee only.** The client decision of 2026-10-29 (goods are paid to the shop directly) still holds, and WALLET_PLAN.md section 4 keeps it until the wallet is switched on in phase W2. The buyer view returns the breakdown (goods, delivery fee, total) and `amount_due`, plus `covers` and `goods_paid_to`, so when W2 changes what is collected the app shows it without a new release. Every number on screen is a stored one: the order's subtotal, the fee frozen at acceptance, a recorded payment, a recorded refund.
- **`PaymentGateway` replaced by the collection half of WALLET_PLAN.md section 6** (`name`, `capabilities`, `initiateCollection`, `queryCollection`). Refunds, payouts, webhooks and statements are added with the real provider instead of as empty methods now.
- **Only the server marks a fee paid.** The app starts an attempt; the server asks the gateway on the buyer's polls (throttled to once per 3 s per payment) and on every minute cron tick, under a row lock, and calls the existing `markFeePaid`. The cron also finishes any confirmed payment whose job somehow stayed unpaid. A payment confirmed after the 20-minute cancel uses the existing late-payment path (kept, refund pending).
- **Sandbox gateway outcome by the paying number** (WALLET_PLAN.md section 6): `…0001` declines, `…0002` never answers, anything else is approved after 8 s. It is stateless (outcome and send time are in the reference only the server stores) and the container never binds it in production.
- **Idempotency:** the app generates one key per tap and reuses it if the request has to be re-sent; the same key returns the same attempt. A second prompt is refused while one is open; an open prompt counts as failed after 120 s.
- **Staging keeps the cash switch.** `SOKONI_DELIVERY_FEE_CASH_ENABLED=true` skips the payment step as before (the app says "pay the rider in cash"); `false` plus `PAYMENT_PROVIDER=fake` tests Pay now.
- **The rider's phone at checkout is a new nullable `orders.delivery_phone`.** Optional for the API (empty means the buyer's phone, so existing clients and tests are unchanged), required by the 1.1 app. Only the buyer sees it back on the order; the driver gets it as `customer.phone` while the job is active.
- **Sellers don't get the buyer's contact details on dispatch orders.** On those orders the seller's order and chat payloads keep the usual user object with phone, email and username null, a shape Sokoni 1.0 already handles because both fields are optional on every account. Legacy orders are unchanged, since there the seller delivers.
- **The 1.1 flow is gated twice:** the app checks its own version (1.1.0+) and `GET /delivery/config`, which 404s while `DELIVERY_ENABLED` is off. A network failure is shown as "couldn't check delivery options" with a retry, never as a silent fallback to the flat-fee flow.
- **The goods payment method stays `cash_on_delivery` on dispatch orders,** the value Sokoni 1.0 sellers already understand as "the buyer pays me". The 1.1 screens say instead that the delivery fee is paid in the app (or to the rider in cash) and the goods to the shop directly.
- **Checkout map is OpenStreetMap with a centre pin,** like the shop-location step of seller onboarding: GPS jump, reverse-geocoded address that the buyer edits, required before placing. The fee shown is the server's quote and is labelled as an estimate until the shop accepts.
- **Tracking is polled** (3 s while a prompt is open, 4 s while a rider is active, 6 s otherwise, 8 s before the shop accepts, stopped when delivered or cancelled) and refreshed at once by a matching push. The rider's position comes from the Firebase stream when `/delivery/realtime-token` returns credentials, otherwise from the polled `driver_location`. The customer app gained `firebase_auth` and `firebase_database` for that, at the driver app's versions.
- **Seller notifications for 1.1:** fee paid (prepare the order), picked up, and cancelled (unless the seller cancelled it) were added beside the existing ones. Buyer cancels used to reach the seller only as a status change on the order.
- **A dispatch order's "Mark as completed" is hidden for the seller;** the server already refuses it while the delivery is open, because completion comes from the customer's PIN. The plain order cancel is offered to the buyer only before the shop accepts; after that the delivery's own cancel applies the refund rules.
- **The customer app's push service now hands `order_id` from a tapped notification to the router,** and a push received in the foreground refreshes that order. The inbox opens the order on tap.

## Fix: "type 'Null' is not a subtype of type 'String'" after Place order, 2026-11-04

- **Cause:** `POST /api/orders` answered with `"status": null` and `"payment_status": null`. Laravel never reads database defaults back after an insert, and the controller returned the in-memory order. Sokoni 1.1 parsed `status` as a required string, so the order was created (staging order #72) while the app showed the error, kept the cart and invited a second order. The code is unchanged since Phase 2, so Sokoni 1.0 reads the same response the same way.
- **Server:** the `Order` model now carries the table defaults (`status` pending, `payment_status` unpaid, `delivery_fee` 0) from the moment it is created, so the create response matches what a later fetch returns. Feature tests compare the two.
- **App, null-safe text, strict amounts:** text fields and flags in the order, order item, user, seller summary and notification models are read through `json_readers.dart`; a missing one becomes '' (a missing order status becomes `pending`) instead of failing the screen. Prices, fees and totals stay strict on purpose: a missing amount stays an error rather than showing a TSh 0 the server never recorded. The hand-written delivery models (job, driver, payment, rating, timeline) already read every field this way.
- **The regression tests replay the API's real create response byte for byte,** captured before the fix. The earlier checkout test used a hand-written response with `status: 'pending'`, which is how this got through.
- **Customer app build 1.1.0+10** carries the fix and installs over build 9.

## Delivery by Bolt only, and returns, 2026-11-17

Branch `feature/bolt-delivery` (from `feature/delivery`, with production's `fix/ugc-moderation` merged in so the release is a superset of what is live). Tag `pre-bolt-sokoni-drivers` marks the last commit with the driver app.

**Removing Sokoni Drivers**

- **Driver app, driver endpoints, dispatch waves and offers, heartbeat, the driver review queue, PINs and pickup codes are deleted.** The historical migrations stay unchanged (production has never run them, and `delivery_jobs` points at `driver_profiles` and `vehicles`), so the driver tables are created empty. Bolt jobs write 0 or `''` into the own-driver `NOT NULL` columns and never read them, rather than altering those columns.
- **The customer app lost the delivery-PIN and driver-pickup-code screens and the rider rating.** Orders keep their order code, which is also what the note to Bolt's driver quotes.
- **`firebase_auth` and `firebase_database` were removed from the customer app.** They existed only for the driver's live position; Bolt's tracking page replaces it.

**Dark launch**

- **`BOLT_ENABLED` replaces `DELIVERY_ENABLED`, which is no longer read.** A server that still has `DELIVERY_ENABLED=true` from staging can't switch Bolt on by accident.
- **The allowlist applies to both sides of an order.** A tester could otherwise send a real Bolt driver to a real shop that has never heard of the feature. Quotes answer `not_available`; checkout silently places an ordinary order.
- **The App Store review account is excluded in code** (`REVIEW_ACCOUNT_PHONE`), even when listed in `BOLT_TEST_PHONES` or at full launch, as a buyer and as a shop.
- **Switched-off routes are indistinguishable from missing ones.** The switch runs before authentication and route-model binding (middleware priority), and throws the same `NotFoundHttpException` text Laravel uses for an unknown route. A first version answered 401 to guests and "No query results" for unknown ids, which a replay against production's code caught.
- **`GET /api/features` is the app's only switch.** Public, per user, every failure means "off". 1.0 builds never call it, and 1.1 shows nothing until the server says so.
- **Checked against production byte for byte.** 26 requests the live apps make were replayed against production's code and this branch (flags off), with random values masked. Identical apart from the new `/features` and the earlier `POST /orders` status fix (2026-11-04 above), which stays because 1.0 parses that answer the same way.
- **The scheduler only gets the Bolt jobs when Bolt is on or has credentials.** A sub-minute task (`everyTenSeconds`) keeps `schedule:run` alive for the whole minute on shared hosting; production pays for that only once Bolt is used. Credentials alone keep it running so deliveries in progress finish after a switch-off.

**Bolt**

- **The shop is Bolt's rider; the customer is the destination.** Ride Booker books passengers, so the person at the pickup must be the "client". The shop's name and phone go in the rider fields, and the note to the driver carries the order code and the customer's name and phone, in English and Swahili. Whether Bolt allows parcel-only rides in Tanzania is an open question for Bolt (BOLT.md § 6).
- **Cheapest category with a non-null ETA; the customer pays its upper bound, rounded up.** Bolt's price is a range and the final cost comes later; charging the top keeps Sokoni from paying the difference in the normal case. Every difference is stored against the receipt for admins.
- **Priced when the shop accepts, booked when paid and Ready (whichever comes second).** Booking earlier would leave a driver waiting at a shop that hasn't packed. A cache lock stops a payment and a Ready tap from booking twice.
- **A fresh `fare_id` for every booking, never cached.** Bolt's fare ids last 5 minutes and are single use. Checkout quotes are cached for 2 minutes without their `fare_id`. One re-estimate when Bolt rejects a fare.
- **Booking failures:** transient errors (429, 5xx, timeouts) retry on the minute cron after 1, 2, 4, 8 minutes, up to 5 tries; Bolt refusing the request gives up at once. Either way the buyer is offered book again, pickup or cancel.
- **The unpaid timeout counts from when the price was set,** not from when the shop accepted: Bolt may be unreachable for a while in `pending_quote`, and the buyer can't pay before there is a price.
- **Cancelling reads Bolt's state first.** The local status can be up to 10 s old; a parcel that has just left the shop must not be cancelled as if it were still there. If Bolt refuses the cancel, Sokoni still cancels and records `provider_cancel_failed` for an admin.
- **A buyer who cancels after Bolt assigned a driver pays `SOKONI_DELIVERY_BUYER_CANCEL_FEE`** (TSh 1,000) out of the refund, to cover Bolt's own cancellation charge. Shop and admin cancels refund in full.
- **Refunds are recorded, not paid.** There is no payout API yet; admins pay and mark them.
- **Webhooks only say "this ride changed".** Sokoni reads the ride back from Bolt and uses the body's state only if that read fails, so deliveries follow Bolt's own record and an old or replayed body can't move one backwards. Token compared in constant time, Bolt's three IPs only, unknown rides answer 200.
- **Polling every 10 s stays on even with webhooks** (Bolt sends them for live accounts only), plus a lazy refresh when a buyer or shop is looking, within Bolt's 10-second fair-use rule.
- **A live estimate in any currency but TZS is refused,** so a misconfigured account can't charge a buyer in another currency.
- **Live base URL and cancel path are configurable.** Bolt's PDF leaves them to its Swagger file; the defaults are the sandbox paths without `-sandbox`.
- **Production has no payment provider,** so admins (admin role only) can record a fee paid outside the app, with a reference, which books the ride exactly like an in-app payment.

**Returns**

- **Only Bolt-delivered orders can be returned,** because the window starts at Bolt's COMPLETED and the ride back starts at the delivery pin.
- **The policy is copied onto the order at checkout.** A shop changing its policy later can't shorten a window the buyer already relied on.
- **Who pays the ride back follows the reason:** the buyer for "changed my mind", the shop for everything the shop got wrong. Admins can override. The shop's share is recorded as owed by seller, because Sokoni can't charge a shop in the app yet.
- **The buyer books the ride back when ready,** and is Bolt's rider for it. Booking at acceptance would send a driver to someone who may not be home.
- **48 hours for the shop, then Sokoni support.** Checked by the minute cron.
- **Blocks:** a buyer who blocked the shop can't open a return (same as chat and orders); a shop that blocked the buyer can't escape one: the request goes straight to support and the shop sees none of that buyer's content.
- **Return photos and comments are user content:** reportable by the shop only, auto-hidden by the existing upheld-reports rule, stored on the private disk and served through 30-minute signed links.
- **Terms 1.2 lives in its own columns** (`users.returns_terms_version`, `returns_terms_accepted_at`). iOS 1.0 (9) asks again whenever `users.terms_version` isn't its own `1.1`, and has no Return Policy text to show, so `terms_version` and `Legal::TERMS_VERSION` stay `1.1`. The Terms page shows clause 14 and version 1.2 only while returns are on.

**App**

- **Version stays `1.1.0+10` in this branch,** as asked. Recommended for release: **1.1.0+11** on both stores: a new build number above every build either store has seen, and a minor version because 1.1 adds features over the 1.0 in the stores.
- **iOS social sign-in stays hidden;** nothing the 1.0 (9) review build uses was changed.

## Live tracking, prices everywhere and the returns check, 2026-11-19

Branch `feature/bolt-live-tracking`. The brief named `release/android-1.1` as the base, but no branch of that name existed then; it was cut from `feature/bolt-delivery` (ed5efa6), the only branch with `BOLT_ENABLED`/`BOLT_TEST_PHONES` and app build 1.1.0+10. `release/android-1.1` was later created from the same ed5efa6 and merged in (see "Merge" below). No migrations; everything stays behind the existing flags. The deploy on top of the dark release (5fa4c80) is [RELEASE-live-tracking.md](RELEASE-live-tracking.md).

**Live tracking**

- **Bolt's ride-location endpoint, path configurable (`BOLT_LOCATION_PATH`, default `rides/location`).** Bolt's PDF names the endpoint (active rides only, callable every second) but leaves path and fields to the Swagger file. The default mirrors `rides/receipt`; the body is read in every likely shape, and one without usable coordinates (missing, out of range, or 0,0) is "unavailable", never a pin.
- **One Bolt call per ride per 5 s, whatever polls.** A cache gate lets the first caller in each window ask Bolt; everyone else reads the cached answer. The last good position is kept 15 minutes and shown as stale when Bolt can't give a fresh one.
- **Bolt's errors are answers, not error pages.** `UNABLE_TO_LOCATE_DRIVER` and anything unexpected: last known position, stale. 429: the gate stays shut for `Retry-After` (5 to 30 s). One attempt per poll and no backoff sleep, unlike the other Bolt calls, because a web request must not hang and the app asks again in 5 s. Each failed Bolt call is logged by the client as usual (at most one per ride per 5 s, with Bolt's tracking headers); the service adds one "unavailable" line per ride per minute for unexpected errors.
- **`active` means "the ride runs", the point only from driver assigned.** A driver who drops the ride sends Bolt back to SEARCHING; the map keeps polling ("finding a driver") instead of closing. It turns false when the ride is over, which is the app's signal to stop.
- **The location poll also refreshes the ride's state,** sharing the order screen's 10-second ride-details window (`delivery:lazy-sync:{job}`), so the map's status is never older than the order screen's and Bolt's 10-second rule still holds.
- **ETA:** Bolt's `eta_to_client` before pickup (for a delivery the "client" is the shop), less the seconds since it was read. Bolt gives none with the parcel in the car, so the app shows "about N min to you" from the straight-line distance × 1.4 at 20 km/h, labelled as an estimate. Both factors are config values (`delivery.tracking`).
- **Buyer only.** The shop never sees the driver's position, in line with it never seeing the drop-off.
- **Map: flutter_map with OpenStreetMap tiles,** the package and tiles the checkout pin already uses, so no key is added. The earlier note on OSM's tile policy at scale applies here too.
- **Polling in the app:** every 5 s (the server's `poll_seconds`, kept to 5 to 10) only while the screen is open and the app in the foreground; 10 s after a failed poll, keeping the map and saying "reconnecting"; none once `active` is false. A status change re-reads the order's delivery view at once.
- **Admins see the live position on a running delivery's or return ride's page only.** List pages link to Bolt's tracking page instead, so opening a list never calls Bolt.

**Price** (superseded the same day by the flat delivery fee below)

- ~~The buyer sees Bolt's receipt (`final_cost`) after delivery, next to the fee paid, with the note at checkout and on Pay now that Bolt's final price can be lower.~~ Replaced: the buyer pays one fixed fee and never sees Bolt's range, receipt or a "can be lower" note. The difference question is settled the same way: Sokoni keeps it.
- **Every number is a stored one:** the fixed fee at checkout, on Pay now and on the order, and the recorded payment.

**Returns**

- **A buyer-paid ride fee is owed back whenever its ride never carried the item** (`picked_up_at` empty): withdrawn, closed by Sokoni, or refunded by the shop before pickup. A payment confirmed after the return ended is owed back too. New `fee_status` values `refund_pending` and `refunded` in the existing string column; an admin pays and marks it, as for delivery refunds.
- **A ride back that fails with the item in the car is a support case:** it counts as `needsAdmin()`, so both sides see "handled by support" and the admin queue flags it, and both are told.
- **Admins can record a ride-back fee paid outside the app,** as for delivery fees, because production has no payment provider yet and the buyer could otherwise never book the pickup.
- **What is still incomplete** is listed in RETURNS.md § 9.

## Flat delivery fee, Android 1.1.0+11 (`release/android-1.1`), 2026-11-19

- **The buyer pays one fixed delivery fee: the quoted amount.** It is still set when the shop accepts (the upper bound of Bolt's estimate then, rounded up), shown before the buyer pays, and never changes after that, whatever Bolt's trip finally costs. Sokoni keeps any difference and covers any shortfall.
- **The checkout fee is not locked at checkout.** The shop's acceptance re-prices it, as before, and the checkout note says so. Locking the checkout figure would need the quote stored on the order (a new column and a migration) and would make Sokoni absorb any Bolt price change between checkout and acceptance. Left as a business decision.
- **Bolt's own prices never leave the admin.** The quote no longer sends `min`, `max` or `estimate: true`; the buyer's delivery view no longer sends `estimate`; a return's `ride` no longer sends `estimate`. `final_cost` and `cost_difference` were already admin-only, and tests now lock that in for both sides, including after Bolt's receipt arrives. `payment.receipt` stays: it is the buyer's own payment of the fixed fee.
- **Wording, English and Swahili, same keys:** "Fixed delivery fee" on checkout, Pay now and the order screen; checkout's "Bolt estimate X to Y. You pay the upper amount." and "Estimated total" are gone; the checkout note and Pay now both say the fee is fixed and won't change after paying.

## Merge: `release/android-1.1` into `feature/bolt-live-tracking`, app 1.2.0+12, 2026-11-19

- **The flat-fee rule wins over this branch's price screens.** Removed: checkout's Bolt-estimate box ("Bolt estimate … · You pay for delivery … · Bolt's final price for the trip can be lower"), the range and "can be lower" line on Pay now, and the "Bolt's receipt" row and note on the order screen, with their strings (`checkoutBoltRange`, `checkoutBoltYouPay`, `checkoutBoltFinalCanBeLower`, `orderBoltReceipt`, `orderBoltReceiptPending`, `orderBoltReceiptNote`) in both languages. `final_cost` left the buyer's delivery view again. The paid line reads "Fixed delivery fee, paid" and shows the fee, not a receipt amount.
- **The live tracking screen shows no price,** only the driver, the status and the ETA ("estimated" there is the ETA, not a price).
- **Admin keeps everything:** estimate range, `final_cost`, `cost_difference`, invoice, on deliveries and return rides.
- **Version 1.2.0+12:** above the Play bundle 1.1.0+11 built from `release/android-1.1`, and a minor version for live tracking.

## Dalipay collections for the delivery and return ride fees (`feature/dalipay`), 2026-11-20

- **Dalipay behind the existing `PaymentGateway`.** Read from https://dalipay.co.tz/documentation: `X-Public-Key`/`X-Secret-Key` headers, `POST /api/v1/collections`, `GET /api/v1/collections/{uuid}/status` (`processing | success | failed`), webhooks `collection.success|failed` signed with hex HMAC-SHA256 of the raw body in `X-Signature`, no event id, no timestamp, no retries, no refund API.
- **Keys are checked before any call** (`DalipayConfig`): production keys only with `DALIPAY_ENV=live` (as asked), test keys never with `live`, no mixed or swapped pairs, https for live, and test mode refused when `APP_ENV=production` (it approves payments that never happened). A refused configuration turns Pay now off and logs the reason, never a key; the secret is redacted from dumps and stack traces (`#[SensitiveParameter]`, `__debugInfo`).
- **The webhook is a hint, the status API decides.** With no timestamp to bound replays, a verified webhook only makes the server ask Dalipay; a payment only ever moves towards paid; `{uuid}:{event}` in `payment_webhook_events` makes duplicates no-ops. Invalid signatures are logged, never stored, so they can't occupy a genuine event's id.
- **Each payment is asked about through its own provider** (`PaymentGatewayFactory::forProvider`), so a pending Dalipay payment still settles if `PAYMENT_PROVIDER` changes.
- **No answer when sending is "unknown", not "failed".** The prompt may be on the phone; the attempt stays open (no second prompt) and the webhook's `external_id` attaches it. `external_id` is the app's idempotency key (24 characters) or a 30-character hash of a longer one.
- **Confirmed money is never dropped.** Late success after the local 120 s timeout is applied (rechecked for 60 minutes by `payments:reconcile`). A success when the fee is already settled (another attempt, or an admin's Record fee paid) becomes `payments.status = refund_pending`. Decided under a lock on the job or return, taken before the payment, the same order as starting a payment. An amount that differs from the attempt is never applied.
- **`payments:reconcile` runs every minute whenever Dalipay is configured,** independent of the Bolt flags; `delivery:tick` keeps its own payment pass.
- **No customer name is sent to Dalipay.** It is optional in their API and not needed.
- **Mixx by Yas is sent as `Tigo`** and AzamPesa is added as a channel; to be confirmed with Dalipay.
- **Status query failures past the timeout fail the attempt as `timeout`,** so a buyer is never stuck behind an attempt nobody can check; the late check still catches a success.

## Dalipay: no provider, longer create timeout, staging live test, 2026-11-24

- **No `provider` in the create request.** Dalipay answered "we don't need provider, just the number", and a collection sent as Airtel for a Vodacom number was recorded as Mpesa. The body is the documented `account_number`, `amount`, `currency`, `external_id`. The buyer's network choice stays in the app and on the payment for display, is optional in `POST /pay`, and the admin page notes when Dalipay recorded a different network (`dalipay_provider`).
- **The create call waits up to 40 s** (`DALIPAY_CREATE_TIMEOUT_SECONDS`, 15 to 60), status checks 15 s. Staging collections failed on Dalipay's side with "caller disconnected during a slow AzamPay round-trip": our 15 s total limit could hang up while Dalipay waited on AzamPay. 40 s keeps the answer inside the app's 45-second wait. No retry on create (a second prompt), and every create logs and stores how long Dalipay took.
- **Dalipay's `failed` is `provider_failed`, shown as "The payment didn't go through."** Its API gives no reason (only its dashboard does), so "declined" claimed more than we know.
- **Live keys outside production never power the app's Pay now.** Dalipay says its test mode is unreliable, so staging needs live keys to test. Only the staging test endpoint may use them, with all of: APP_ENV=staging, the token, STAGING_PAYMENTS_ALLOW_LIVE=true, `live: true` in the request (the script's -Live, after typing YES PAY), a normal payment, at most TSh 2,000, and exactly STAGING_PAYMENTS_LIVE_MSISDN. The explicit `live: true` means the ordinary test script can never charge real money by mistake on a server that has live keys. Every live collection is logged as a warning, with the number masked. Production answers 404 to the staging routes whatever the configuration.
