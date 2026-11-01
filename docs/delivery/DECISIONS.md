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
