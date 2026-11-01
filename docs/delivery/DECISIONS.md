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
