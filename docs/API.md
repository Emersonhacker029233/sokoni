# Sokoni API Reference

Base URL: `https://api.sokoni.co.tz` in production (see [DEPLOY.md](DEPLOY.md)), `http://localhost:8000/api` for local dev.

All request/response bodies are JSON. Every endpoint below is prefixed with `/api`.

## Conventions

- **Auth**: Bearer tokens (Laravel Sanctum). Send `Authorization: Bearer <token>` on every authenticated request. Tokens are issued by the OTP/social login endpoints and never expire server-side (revoked explicitly on logout, or by an admin ban/suspend — see below).
- **Pagination**: paginated list endpoints return `{"data": [...], "links": {...}, "meta": {"current_page", "last_page", "total", ...}}` (Laravel's standard `paginate()` shape). Request a page with `?page=N`.
- **Single-resource envelope**: non-list endpoints return `{"data": {...}}`.
- **Errors**: `422` for validation errors (`{"message": "...", "errors": {"field": ["reason"]}}`), `401` unauthenticated, `403` forbidden, `404` not found, `429` rate-limited.
- **Currency**: prices are integers in TZS (no decimal places, e.g. `45000` = TSh 45,000).
- **Coordinates**: `lat`/`lng` as decimal degrees.
- **Media URLs**: every image/video/attachment field (`product_media.path`/`thumb_path`/`card_path`, `message.attachment`) is a ready-to-use absolute URL — never construct one from a relative path.
- **Rate limits**: general authenticated traffic 60 req/min per user (or IP if unauthenticated); OTP requests 3 per 10 minutes per phone number; write endpoints (create/update/delete on products, product media) 30 req/min per user.

---

## Auth

Browsing (categories, products, sellers) works with no account at all. An account is only required to order, chat, favourite, or sell (CLAUDE.md feature 4).

| Method | Path | Auth | Body | Notes |
|---|---|---|---|---|
| POST | `/auth/otp/request` | — | `{phone: "+255712345678"}` | Sends a 6-digit code (logged, not SMS-sent yet — see `SmsGateway`/BLOCKERS.md). Rate-limited: 3 per 10 min per phone. |
| POST | `/auth/otp/verify` | — | `{phone, code, name?}` | `name` required only for a brand-new account. Returns `{token, user}`. |
| POST | `/auth/social` | — | `{provider: "google"\|"facebook"\|"apple", token}` | `token` is the provider's own token (Google id_token, Facebook access_token, Apple identityToken) — verified server-side, never trusted as-is. Returns `{token, user}`. |
| GET | `/auth/me` | ✓ | — | Current user. |
| POST | `/auth/logout` | ✓ | — | Revokes the current token only. |
| POST | `/auth/terms/accept` | ✓ | `{version: "1.0"}` | Records `terms_accepted_at`/`terms_version`. |

**User object**: `id, name, email?, phone?, avatar?, locale, is_seller, seller_status?, seller_handle?, terms_accepted, created_at`.

## Categories

| Method | Path | Auth | Notes |
|---|---|---|---|
| GET | `/categories` | — | Active categories, sorted. `{id, parent_id, name_en, name_sw, icon, sort_order}` each. |

## Products (discovery)

| Method | Path | Auth | Query/Body | Notes |
|---|---|---|---|---|
| GET | `/products` | — | `lat?, lng?, radius_km?, category_id?, q?, sort?, page?` | `sort`: `nearby` (default when `lat`/`lng` given), `trending`, `newest`. 20/page. Only verified sellers' visible products. |
| GET | `/products/{id}` | — | — | Increments view count. Owner can view their own hidden/pending product; everyone else gets 404 if not publicly visible. |
| GET | `/shop/products` | ✓ | `page?` | The signed-in seller's own products, **including** hidden/pending ones. |
| POST | `/products` | ✓ (seller) | `category_id, title, description?, price, stock, condition` | `condition`: `new`\|`used`. Rate-limited (write). |
| PATCH | `/products/{id}` | ✓ (owner) | any subset of the above + `is_active` | Rate-limited (write). |
| DELETE | `/products/{id}` | ✓ (owner) | — | Rate-limited (write). |
| POST | `/products/{id}/media` | ✓ (owner) | multipart: `type: "image"\|"video", file, thumbnail? (video only), duration? (video only), sort?` | Max 8 media items/product. Images get 3 generated sizes (`path`=1600px, `card_path`=800px, `thumb_path`=300px). Video needs a client-supplied `thumbnail` image (no server-side frame extraction). Rate-limited (write). |
| DELETE | `/products/{id}/media/{mediaId}` | ✓ (owner) | — | Rate-limited (write). |

**Product object**: `id, title, description?, price, currency, stock, condition, views, is_active, is_hidden, distance_km? (nearby search only), category, seller, media[], is_favorited?, created_at`. `media[]` items: `{id, type, path, thumb_path?, card_path?, duration?, sort}`.

## Sellers / shops

| Method | Path | Auth | Body | Notes |
|---|---|---|---|---|
| GET | `/sellers` | — | `lat?, lng?, radius_km?` | Verified sellers, "New Sellers" (newest first) or nearby. |
| GET | `/sellers/{handle}` | — | — | Full shop profile. |
| GET | `/sellers/{handle}/reviews` | — | `page?` | Paginated, `meta.distribution` = `{"1": n, ..., "5": n}` star counts. |
| POST | `/sellers` | ✓ | `shop_name, handle, category_id, bio?, whatsapp?` | Onboarding step 1 — creates the profile in `pending` status. |
| PATCH | `/sellers/{id}/location` | ✓ (owner) | `lat, lng, address, region, district` | Onboarding step 2. |
| PATCH | `/sellers/{id}/identity` | ✓ (owner) | multipart: `nida_number (20 digits), nida_image` | Onboarding step 3. Format-validated only — see `NidaVerifier`/BLOCKERS.md, no live NIDA API check. |
| PATCH | `/sellers/{id}/licence` | ✓ (owner) | multipart: `licence_file` (image or PDF, max 5MB) | Onboarding step 4 — completing this puts the seller in the admin verification queue. |
| PATCH | `/sellers/{id}` | ✓ (owner) | any of `bio, whatsapp, show_whatsapp` | General profile edits. |

**Seller profile object**: `id, shop_name, handle, bio?, category, whatsapp? (only if show_whatsapp), lat?, lng?, address?, region?, district?, status ("pending"|"verified"|"rejected"), rejection_reason? (owner only), verified_at?, rating_avg, rating_count, is_owner?, created_at`. NIDA number/image and licence file are never exposed via this resource — visible only in the Filament admin.

## Favourites

| Method | Path | Auth | Notes |
|---|---|---|---|
| GET | `/favorites` | ✓ | Paginated products. |
| POST | `/products/{id}/favorite` | ✓ | Idempotent add. |
| DELETE | `/products/{id}/favorite` | ✓ | Idempotent remove. |

## Orders / checkout

Cart holds items from one seller at a time — enforced server-side (`422` if `items` spans more than one seller).

| Method | Path | Auth | Body | Notes |
|---|---|---|---|---|
| GET | `/orders` | ✓ | `page?` | The signed-in user's own orders (as buyer). |
| GET | `/shop/orders` | ✓ (seller) | `page?` | Orders placed with the signed-in user's shop. |
| POST | `/orders` | ✓ | `items: [{product_id, qty}], delivery_method: "pickup"\|"delivery", address? (required if delivery), delivery_lat?, delivery_lng?, notes?, payment_method: "cash_on_delivery"\|"pay_on_pickup"` | Prices computed server-side from current product prices, never trusted from the client. Auto-creates (or reuses) a conversation with the seller — `conversation_id` in the response. Pushes a notification to the seller. |
| GET | `/orders/{id}` | ✓ (buyer or seller) | — | — |
| PATCH | `/orders/{id}/status` | ✓ | `{status, reason? (cancel only)}` | Forward transitions `pending→accepted→ready→completed` are seller-only; `cancelled` is reachable from `pending`/`accepted`/`ready` by either party. Pushes a notification to the other party. |
| POST | `/orders/{id}/review` | ✓ (buyer) | `{rating: 1-5, comment?}` | Only once the order is `completed`, only once per order — the entire defence against fake reviews. |

**Order object**: `id, code, status, subtotal, delivery_fee, total, delivery_method, address?, notes?, payment_method, payment_status?, cancelled_reason?, buyer, seller, items[], conversation_id?, timeline: [{status, at}], has_review?, created_at`.

## Reviews

| Method | Path | Auth | Body | Notes |
|---|---|---|---|---|
| PATCH | `/reviews/{id}/reply` | ✓ (seller) | `{reply}` | Once only per review — `422` on a second attempt. |

## Chat

5-second client polling while a thread is open, plus FCM push for background delivery — no WebSocket server (shared-hosting constraint).

| Method | Path | Auth | Body | Notes |
|---|---|---|---|---|
| GET | `/conversations` | ✓ | `page?` | `last_message`, `unread_count`, `other_party_typing` per conversation. |
| POST | `/conversations` | ✓ | `{seller_id, product_id?}` | Starts (or reuses) a thread — pinned product context if `product_id` given. |
| GET | `/conversations/{id}` | ✓ (participant) | — | Also carries `other_party_typing`. |
| PATCH | `/conversations/{id}/typing` | ✓ (participant) | — | Refreshes the caller's typing signal (6s TTL) — call on keystrokes, debounced. |
| GET | `/conversations/{id}/messages` | ✓ (participant) | `page?` | Marks the other party's unread messages as read as a side effect (this is the read-receipt mechanism). |
| POST | `/conversations/{id}/messages` | ✓ (participant) | multipart or JSON: `body?, attachment?` (image, max 5MB) — at least one required | Pushes a notification to the other party. |

**Message object**: `id, conversation_id, sender_id, body?, attachment? (full URL), is_mine, read_at?, created_at`.

## Reports (trust & safety)

| Method | Path | Auth | Body | Notes |
|---|---|---|---|---|
| POST | `/reports` | ✓ | `{reportable_type: "product"\|"shop"\|"message", reportable_id, reason, note?}` | Three upheld reports against the same content auto-hide it pending admin review. |

## Devices (push notifications)

| Method | Path | Auth | Body | Notes |
|---|---|---|---|---|
| POST | `/devices` | ✓ | `{fcm_token, platform: "android"\|"ios"}` | Registers/refreshes this device's push token. |

---

## Admin (Filament, not this REST API)

`https://api.sokoni.co.tz/admin` — session-authenticated (not Sanctum), staff accounts only (`users.is_admin`). Seller verification queue, moderation queue (reports + hide/warn/suspend/ban), user and category management, dashboard stats. See the app's own `DECISIONS.md` for what each queue does; there's no public API surface for admin actions.

## What's stubbed, and behind which interface

Per CLAUDE.md's blockers, these are real, complete implementations behind an interface, with only the external call mocked — see `BLOCKERS.md` for exactly what's missing and where to plug in real credentials:

- **SMS** (`App\Services\Sms\SmsGateway`): OTP codes are logged, not sent, until an SMS provider is configured.
- **NIDA verification** (`App\Services\Nida\NidaVerifier`): submissions are stored for manual review in the Filament queue; no live government API integration exists (requires an agreement not yet in place).
- **Payments** (`App\Services\Payment\PaymentGateway`): v1 is cash-on-delivery/pay-on-pickup only; the interface exists for a future mobile-money gateway (ClickPesa/Mixx/Airtel Money).
- **Push notifications** (`App\Services\Push\PushNotifier`): logged, not sent, until a Firebase project is configured (BLOCKERS.md item 1) — the client's registration flow is fully real and will start working the moment credentials land.
