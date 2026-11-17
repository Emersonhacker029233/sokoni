# Returns

A buyer can send back an order that **Bolt delivered**, within the shop's return window. A Bolt driver carries it from the buyer back to the shop. Delivery itself is in [BOLT.md](BOLT.md); the production deploy is [RELEASE.md](RELEASE.md).

Switched on with `DELIVERY_RETURNS_ENABLED=true`, which needs `BOLT_ENABLED=true` and follows the same `BOLT_TEST_PHONES` list. The App Store review account never sees returns. With the flag off, every returns route is a 404 like a route that doesn't exist, no shop, product, quote or order response has a `return_policy` field, and the Terms page has no clause 14.

## 1. The shop's policy

| Code | Window, from Bolt's COMPLETED |
|---|---|
| `none` (default) | No returns |
| `24h` | 24 hours |
| `3d` | 3 days |
| `7d` | 7 days |

Plus optional free-text `return_notes` ("Unworn, with the tag").

- Every existing shop starts on `none`, so nothing changes for anyone until a shop owner chooses otherwise: on their own shop page, **Return policy → Change** (`PUT /api/shop/return-policy`).
- Shown on the **shop page**, the **product page** and at **checkout before payment** (in the Bolt quote).
- The policy is **copied onto the order when it is placed** (`orders.return_policy`). A shop changing its policy later doesn't change orders already placed.
- Only Bolt deliveries can be returned. Pickup orders and the shop's own deliveries can't, because there is no delivery time or place to start from.

## 2. The flow

```
requested ─┬─ accepted ── return_in_transit ── received ── refunded
           │      │              └──────────────────────────┘
           └─ rejected ── disputed ── (Sokoni) accepted | closed
any open state ── closed (withdrawn by the buyer, or closed by Sokoni)
```

1. **Buyer asks.** On a delivered order, inside the window: **Return this item** → reason, 1 to 3 photos (JPEG/PNG/WebP, 5 MB each), optional comment. One request per order.
   | Reason | Ride back paid by |
   |---|---|
   | Changed my mind (`changed_mind`) | Buyer |
   | Wrong item (`wrong_item`) | Shop |
   | Damaged or defective (`damaged`) | Shop |
   | Not as described (`not_as_described`) | Shop |
2. **Shop decides within 48 hours**: **Accept**, or **Reject** with a reason (required). No answer in 48 hours: the request is **escalated** to Sokoni support (`delivery:tick` checks every minute) and both sides are told.
3. **Buyer may dispute** a rejection, with a comment. Sokoni support then decides.
4. **Accepted:** Sokoni prices a Bolt ride from where the order was delivered to the shop. The payer owes the **upper bound** of the estimate, as for deliveries.
   - **Buyer pays:** Pay now in the app (same payment flow as the delivery fee), then **Book the ride back**.
   - **Shop pays:** the fee is recorded as **owed by seller** (`fee_status = owed_by_seller`) and the buyer can book at once. Sokoni pays Bolt and collects it from the shop outside the app. Admin → Trust & Safety → Returns shows every amount owed.
5. **The ride back:** the buyer is Bolt's rider (the driver collects from them), the shop is the destination. Note to the driver: `Sokoni RETURN of order SK…. Collect parcel from customer, deliver to shop: <shop> <phone>. Rudisha mzigo dukani.` Tracking and the driver card work as for a delivery. If the ride fails before pickup the return goes back to `accepted` and the buyer can book again. If Bolt doesn't serve the buyer's location, the buyer is told to arrange the return with the shop in chat, and the shop can still confirm the refund.
6. **Shop confirms "Item received, refund done".** The order shows *Returned · Refunded*. Sokoni doesn't move the goods money; the shop refunds the buyer the way they paid, as they were paid.
7. Both sides get a push and an in-app notification at every step (requested, accepted, rejected, escalated, disputed, decided, ride booked, driver on the way, delivered back, refunded, closed), in the user's language.

The buyer can **withdraw** an open request at any time before the ride back starts.

## 3. Trust and safety

- **Reports.** The shop can report a return's comment and photos (the flag button on the return → Report…, type `return_request`). Only that shop can; a return is private to its buyer and shop. Reports go to the existing moderation queue (Trust & Safety → Reports) with the 24-hour clock, and the existing "3 upheld reports auto-hide" rule hides the comment and photos (`return_requests.is_hidden`).
- **Blocks.**
  - **Buyer blocked the shop:** the buyer can't open a return until they unblock (the same rule as orders and chat). The app says Sokoni support can help.
  - **Shop blocked the buyer:** a block is not a way out of a return. The request goes straight to Sokoni support (`escalation_reason = seller_blocked_buyer`), the shop sees no content from that buyer, and Sokoni decides and confirms the refund with the shop.
- **Photos** are stored on the private `local` disk (`storage/app/private/returns/{id}/`) and served only to the buyer, the shop and admins, through short-lived signed links.

## 4. Admin: Trust & Safety → Returns

The list shows status, order, buyer, shop, who pays the ride back, fee and fee status, and whether it was escalated. The **Needs Sokoni** filter shows the ones support must decide (escalated or disputed). On a return:

| Action | When |
|---|---|
| **Accept return** | requested (escalated), rejected, disputed. The decision note is shown to both sides. |
| **Uphold rejection** / **Close return** | Any open state. The reason is shown to both sides. |
| **Who pays the ride back** | Before the ride is paid, to correct the reason-based default. |
| **Hide / Show comment and photos** | Moderation. |

The ride's Bolt receipt, `final_cost` and `cost_difference` are shown as for deliveries.

## 5. Terms

The Terms page gets **clause 14, Return Policy** (Swahili first, English below, like the rest of the page) only while `DELIVERY_RETURNS_ENABLED` is on, and then shows version **1.2**.

This never makes iOS 1.0 (9) ask to re-accept anything: that build compares `users.terms_version` with its own `1.1` and has no Return Policy text to show. So `users.terms_version` and `Legal::TERMS_VERSION` stay `1.1`, and agreement to 1.2 is stored separately in `users.returns_terms_version` and `users.returns_terms_accepted_at`. App 1.1 shows the Return Policy and asks the buyer to agree once, before their first return request (`POST /api/returns/terms/accept`). This is an app step: the server records the agreement but doesn't refuse a request without it. `GET /api/features` tells the app whether it is needed (`returns_terms_version`, `returns_terms_accepted`) only while returns are on.

## 6. API

All need `Authorization: Bearer <token>` and a user inside the launch; otherwise 404.

| Route | Who | |
|---|---|---|
| `PUT /api/shop/return-policy` | Shop owner | `{ return_policy: none\|24h\|3d\|7d, return_notes? }` |
| `POST /api/orders/{order}/returns` | Buyer | multipart: `reason`, `comment?`, `photos[]` (1 to 3). `409 { code }`: `return_not_available` (not a delivered Bolt order), `no_return_policy`, `return_window_closed`, `return_exists`; `403 blocked` |
| `GET /api/returns` | Both | The user's returns, as buyer and as shop |
| `GET /api/returns/{id}` | Both | Status, reason, photos, decision, payer, fee, ride (status, driver, tracking link), `actions` |
| `POST /api/returns/{id}/accept` | Shop | |
| `POST /api/returns/{id}/reject` | Shop | `{ reason }` |
| `POST /api/returns/{id}/dispute` | Buyer | `{ comment }` |
| `POST /api/returns/{id}/withdraw` | Buyer | |
| `POST /api/returns/{id}/pay` | Buyer | `{ channel, msisdn, idempotency_key }`, when the buyer pays the ride |
| `POST /api/returns/{id}/book` | Buyer | Books Bolt. `409 ride_unavailable`, `503 ride_booking_failed` |
| `POST /api/returns/{id}/refunded` | Shop | `{ note? }`: "Item received, refund done" |
| `POST /api/returns/terms/accept` | Both | Records Terms 1.2 |

The delivery screen (`GET /api/delivery/orders/{order}`) also carries a `return` block while returns are on: `{ policy, eligible_until, can_request, request }`.

Bolt states for the ride back map to `ride_status`: SEARCHING → `pending_driver`, DRIVER_ON_ROUTE_TO_CLIENT → `driver_assigned`, ARRIVED_AT_CLIENT → `at_pickup`, DRIVING_WITH_CLIENT → `on_the_way`, COMPLETED → `delivered` (the return becomes `received`), NO_DRIVER_FOUND → `no_driver_found`, the rest → `failed`.

## 7. Data

- `seller_profiles.return_policy` (varchar 10, default `none`), `seller_profiles.return_notes` (text, null)
- `orders.return_policy` (varchar 10, null): the snapshot
- `return_requests`: one per order (`order_id` unique), buyer, shop, `reason`, `comment`, `status`, `is_hidden`, `payer`, `fee`, `fee_status` (`unpaid | paid | owed_by_seller | waived`), the decision (`decided_by`, `decided_role`, `decided_at`, `decision_note`), `escalated_at`/`escalation_reason`, `disputed_at`/`dispute_comment`, `resolution_note`, `received_at`, `refunded_at`, `closed_at`/`closed_reason`, and the Bolt ride (`ride_status`, `provider_ref`, `provider_state`, `provider_driver`, estimate, `final_cost`, `cost_difference`, `receipt`)
- `return_request_photos`: `return_request_id`, `path` (private disk), `sort`
- `payments.return_request_id` (null): a buyer-paid ride back
- `users.returns_terms_version`, `users.returns_terms_accepted_at` (null)

All additive. Code: `app/Services/Returns/ReturnService.php`, `app/Models/ReturnRequest.php`, `app/Http/Controllers/Api/ReturnController.php`, `routes/api_returns.php`, `app/Filament/Resources/ReturnRequests/`; tests in `api/tests/Feature/Returns/`.

## 8. Switching on

After delivery works for the test phones ([BOLT.md](BOLT.md) § 7):

1. `.env`: `DELIVERY_RETURNS_ENABLED=true`. Clear the config cache.
2. The test shop sets a policy (e.g. 24 hours). Check it shows on the shop page, product page and checkout.
3. Deliver an order to the test buyer, request a return with a photo, accept as the shop, book the ride back (sandbox: move it with `php artisan bolt:sandbox state <order code> …`), confirm the refund.
4. Try a reject → dispute → Admin decides, and a 48-hour escalation (or wait for it).
5. Check `https://sokoni.co.tz/terms` shows clause 14 and version 1.2.

Switching off (`DELIVERY_RETURNS_ENABLED=false`) hides every return screen and route again. Open requests stay in Admin → Returns, and rides back already booked keep being tracked to the end.
