# CLAUDE.md — Sokoni Project Specification

This file is the original bootstrap specification for the Sokoni project, preserved verbatim so any future Claude Code session has full context without needing the original prompt file. Progress against it is tracked in `PROGRESS.md`, judgement calls in `DECISIONS.md`, and open items in `BLOCKERS.md`.

---

You are building **Sokoni**, a Tanzanian mobile marketplace app, from scratch in this currently-empty folder (`C:\Projects\sokoni`, Windows, VS Code). This message is the complete specification. Read all of it, then execute it end to end.

## Autonomy rules — read these first

- Work continuously through **Phase 0 → Phase 10**. Do not ask permission. Do not ask "should I continue". Do not stop between phases to summarise.
- If a command fails, debug and fix it yourself. If a package version conflicts, resolve it. If a design decision is ambiguous, pick the better-looking option and log it in `DECISIONS.md`.
- If a tool is missing (Flutter, PHP, Composer, Node), detect it, print the install command, log it in `BLOCKERS.md`, and continue with everything that doesn't depend on it.
- The **only** things you stop and ask me about are the four credentials in the "Blockers" section — and even then, stub them, mock the call, and keep building.
- After each phase: run the verification commands, fix every error and warning, then append one line to `PROGRESS.md` and move straight to the next phase.
- This is going in front of a paying client. Motion design, empty states, loading skeletons and micro-interactions matter as much as features.

---

## PHASE 0 — Bootstrap the repository

Create this exact structure. Every file listed gets real content, not a placeholder.

```
C:\Projects\sokoni\
├── CLAUDE.md                 # write this spec into it verbatim, so future sessions have context
├── README.md                 # project overview, setup steps, how to run both halves
├── PROGRESS.md               # phase log — you append one line per completed phase
├── DECISIONS.md              # every judgement call you make, with a one-line rationale
├── BLOCKERS.md               # anything needing my input, with what you stubbed
├── .gitignore                # Flutter + Laravel + IDE + .env + keystores
├── .editorconfig
├── assets/
│   └── brand/
│       └── sokoni_logo.png   # copy from repo root if present, else generate a placeholder
├── app/                      # Flutter
│   ├── lib/
│   │   ├── main.dart
│   │   ├── core/
│   │   │   ├── theme/        # colors.dart, typography.dart, app_theme.dart, dimens.dart
│   │   │   ├── motion/       # the 12 primitives — one file each + motion.dart barrel
│   │   │   ├── network/      # dio_client.dart, auth_interceptor.dart, api_exception.dart
│   │   │   ├── storage/      # secure_storage.dart, app_database.dart (drift)
│   │   │   ├── location/     # location_service.dart, distance.dart
│   │   │   ├── l10n/         # app_en.arb, app_sw.arb
│   │   │   ├── router/       # app_router.dart, transitions.dart, routes.dart
│   │   │   └── utils/        # formatters.dart (TZS, phone), validators.dart
│   │   ├── data/
│   │   │   ├── models/       # freezed models mirroring the API resources
│   │   │   ├── api/          # retrofit service classes, one per domain
│   │   │   └── repositories/ # one per domain, cache-aware
│   │   ├── features/
│   │   │   ├── auth/         #   each feature folder: presentation/ + providers/ + widgets/
│   │   │   ├── discovery/
│   │   │   ├── product/
│   │   │   ├── seller/
│   │   │   ├── orders/
│   │   │   ├── chat/
│   │   │   ├── reviews/
│   │   │   └── profile/
│   │   └── shared/
│   │       ├── widgets/      # buttons, chips, cards, sheets, empty_state, error_state
│   │       └── skeletons/    # one shimmer skeleton per real layout
│   ├── test/
│   ├── pubspec.yaml
│   └── analysis_options.yaml
├── api/                      # Laravel 12
│   ├── app/
│   │   ├── Models/
│   │   ├── Http/Controllers/Api/
│   │   ├── Http/Requests/
│   │   ├── Http/Resources/
│   │   ├── Services/         # NidaVerifier, SmsGateway, PaymentGateway interfaces
│   │   ├── Observers/
│   │   └── Filament/         # admin resources
│   ├── database/migrations/
│   ├── database/factories/
│   ├── database/seeders/
│   ├── routes/api.php
│   ├── tests/Feature/
│   └── .env.example
└── docs/
    ├── API.md                # generated endpoint reference
    ├── DEPLOY.md             # cPanel deployment runbook
    └── DEMO.md               # client demo script
```

Scaffold commands (adapt if a tool is missing):

```powershell
git init
flutter create --org tz.co.sokoni --project-name sokoni --platforms android,ios app
composer create-project laravel/laravel api
```

Then in `app/`: add `flutter_riverpod`, `riverpod_annotation`, `go_router`, `dio`, `retrofit`, `freezed`, `json_serializable`, `drift`, `sqlite3_flutter_libs`, `flutter_secure_storage`, `google_maps_flutter`, `geolocator`, `geocoding`, `cached_network_image`, `flutter_image_compress`, `video_player`, `chewie`, `video_compress`, `visibility_detector`, `shimmer`, `google_fonts`, `intl`, `share_plus`, `url_launcher`, `image_picker`, `file_picker`, `firebase_core`, `firebase_messaging`, `google_sign_in`, `flutter_facebook_auth`, `sign_in_with_apple`, `flutter_launcher_icons`, `flutter_native_splash`, plus `build_runner`, `riverpod_generator`, `retrofit_generator`, `drift_dev`, `custom_lint`, `riverpod_lint` as dev deps.

In `api/`: install `laravel/sanctum`, `filament/filament:^3`, `intervention/image`, `laravel/socialite`, `kreait/laravel-firebase`.

**Verify:** `flutter analyze` clean on the empty scaffold, `php artisan --version` responds, `git log` shows an initial commit.

---

## The product

**Sokoni** ("marketplace" in Kiswahili) — buyers browse products and verified sellers near them, check ratings, chat, and order. Sellers register with verified identity, post product photos and short videos, and receive orders and messages.

Market: Tanzania, starting Dar es Salaam. Bilingual EN/SW from day one. Mid-range Android on patchy 3G/4G — performance and offline resilience are features.

## Stack — decided, do not re-litigate

Flutter 3.x (Android + iOS) · Riverpod 2 + generator · go_router · dio + retrofit + freezed · drift for offline cache · flutter_secure_storage for tokens · Laravel 12 API on PHP 8.3 · Sanctum tokens · MySQL 8 with `ST_Distance_Sphere` for geo · Filament v3 admin · Firebase Cloud Messaging · google_maps_flutter + geolocator.

Deployment target is **Namecheap cPanel shared hosting** (`sokoni.co.tz`, cPanel user `sokoftsn`, home `/home/sokoftsn`). No Docker, no queue workers, no WebSocket server — design within that.

## Brand and design system

Sample the exact palette from `assets/brand/sokoni_logo.png`. Expected values:

```dart
static const sokoniYellow = Color(0xFFFAC902);  // primary / CTA
static const sokoniBlack  = Color(0xFF0A0A0A);  // text, headers, nav
static const surface      = Color(0xFFFFFFFF);
static const surfaceAlt   = Color(0xFFF7F7F5);  // cards, chips
static const outline      = Color(0xFFE6E6E1);
static const success      = Color(0xFF12A150);
static const danger       = Color(0xFFE5484D);
```

Yellow is a **highlight, not a background wash** — primary CTA, active chip, rating stars, verified badge, notification dot. Everything else is black-on-white with generous whitespace. Dark mode required: `#0E0E0E` surfaces, same yellow.

Type: Plus Jakarta Sans via `google_fonts`. Display 32/700, Title 20/600, Body 15/400, Caption 13/500, tight letter spacing (-0.5) on headings.

Shape: 16dp cards, 12dp chips and fields, 28dp primary button. No drop shadows — 1px `outline` border, plus a soft ambient shadow (`blurRadius: 24, opacity: 0.04`) only on floating elements.

### Motion — build all twelve as reusable primitives in `core/motion/`

1. **Page transitions** — shared-axis X for siblings, shared-axis Z for drill-down, fade-through for tabs, via `go_router` `CustomTransitionPage`. 280ms `Curves.easeOutCubic`.
2. **Hero product images** — card → detail with a `RectTween` + `ClipRRect` so the corner radius animates too.
3. **Staggered list reveal** — fade + translateY(16→0), 40ms stagger, capped at 8 items.
4. **Shimmer skeletons** — every list, card and detail screen has one matching its real layout. No spinners except pull-to-refresh.
5. **Animated category chips** — selected chip morphs to yellow with a sliding indicator; the feed cross-fades rather than blanking.
6. **Cart/order badge** — spring scale bounce (`Curves.elasticOut`, 400ms) plus a single yellow pulse dot.
7. **Bottom sheets** — draggable, physics-based, rubber-band overscroll. Used for filters, seller quick-view, order confirmation.
8. **Success moments** — order placed and seller verified get a full-screen drawn checkmark (`CustomPainter` path-drawing + scale-in), auto-dismiss at 1.6s.
9. **Nav bar** — icon scale + colour tween on tab change, label fades in only for the active tab.
10. **Pull-to-refresh** — custom indicator using the Sokoni bag mark rotating, not the stock spinner.
11. **Image carousel** — parallax on horizontal scroll, page dots that stretch for the active dot.
12. **Splash → home** — logo scale-and-settle, then the mark shrinks into the nav bar position. 900ms max, skippable.

Rules: 60fps on mid-range Android, nothing over 400ms, respect `MediaQuery.disableAnimations`, one `AnimationController` per screen driving an `AnimatedBuilder` — never animate on every rebuild. Build a `/motion-gallery` debug route demonstrating all twelve.

## Features

**1. Discovery.** Home feed with Near You (radius 1/5/10/25km/All, default 5km), Trending, New Sellers. Cards show distance ("2.3 km away"), TZS price, seller rating, verified tick. List ⇄ map toggle; map pins cluster, tap opens a seller quick-view sheet. If location permission is denied, fall back to a region/district picker — never block the app. Cache last known position. Backend: `GET /api/products?lat=&lng=&radius_km=&category_id=&q=&sort=` using `ST_Distance_Sphere`, paginated 20/page, spatial index on `seller_profiles.shop_location`.

**2. Seller ratings.** 1–5 stars + optional text, **only leaveable by a buyer with a completed order from that seller** — enforce server-side, this is the entire defence against fake reviews. Profile shows average, count, and a distribution bar. Sellers reply once per review. Denormalise `rating_avg`/`rating_count` via an observer.

**3. Seller handles.** Unique `@handle`, 3–20 chars `[a-z0-9_]`, reserved-word blocklist. Deep-linkable at `sokoni.co.tz/@handle` with a web fallback card. Searchable alongside products. Shareable via `share_plus`.

**4. Registration.** Buyer: Google / Facebook / Apple / phone OTP + full name, under 30 seconds. Browsing works fully **without** an account — auth is only required at order, chat, or favourite. Seller: all of the above plus a 4-step wizard with progress bar and saved drafts — (1) business details: shop name, handle, category, description, WhatsApp; (2) location: map pin + reverse-geocoded address, user-confirmed; (3) identity: NIDA number (20 digits, format-validated) + ID photo; (4) licence: business/trading licence, image or PDF, max 5MB. Status `pending → verified | rejected(reason)`. Pending sellers **can** build their shop and add products, but products stay hidden from the public feed until verified. Verification is manual via the Filament queue.

> Do not attempt live NIDA API verification — programmatic access requires a NIDA agreement that isn't in place. Store the number and image for a human reviewer, behind a `NidaVerifier` interface so real verification can drop in later.

**5. Chat.** In-app is the default: conversation list, per-product thread context pinned at top, read receipts, typing indicator, image attachments. Transport is 5s polling while a thread is open plus FCM data messages for background delivery — **no WebSocket server**, shared hosting can't hold one. Keep the repository interface clean so Reverb/Pusher can replace polling later. WhatsApp deep link as a secondary button on product and shop pages: `https://wa.me/255XXXXXXXXX?text=<prefilled product name and link>`, toggleable by the seller.

**6. Buyer → seller upgrade.** Every buyer sees "Start selling" in their profile, launching the same wizard. One account, an optional `seller_profile` relation. The nav gains a "My Shop" tab once one exists.

**7. Media.** Up to 8 items per product, images and video mixed in one carousel. Video max 60s, compressed client-side to ~720p, max 20MB, autoplay muted in-feed at >60% visibility (`visibility_detector`), tap to unmute. Shop profile has a mixed grid tab with play badges. Generate 3 image sizes on upload (thumb 300, card 800, full 1600) with Intervention Image.

**8. Orders.** Cart holds items from **one seller at a time**; a second seller starts a second cart — multi-vendor split checkout is explicitly out of scope. Checkout collects delivery method (pickup/delivery), address or map pin, notes, payment method. **Payment v1 is cash on delivery / pay on pickup** — build a `PaymentGateway` interface so ClickPesa USSD push (Mixx by Yas, Airtel Money) drops in later, but do not build the gateway now. On placement: FCM push to the seller, in-app notification, and the order thread opens automatically in chat. Statuses `pending → accepted → ready → completed`, plus `cancelled(reason)`, with a timeline both parties see.

**9. Categories at the top.** Horizontal chip row pinned under the search bar, sticky via `SliverPersistentHeader`, with the animated selection above. Seed two-level categories with EN and SW names: Electronics, Fashion, Food & Groceries, Home & Furniture, Beauty & Health, Phones & Accessories, Vehicles & Parts, Agriculture, Services, Other. "All" is always first.

**10. Clean and user-friendly.** Bottom nav: Home · Search · Sell (+) · Orders · Profile, with the centre + as a yellow FAB-style button. Every empty state gets an illustration, one sentence, one action — no blank screens ever. Every error state offers retry; network failure shows the cached feed under a "showing saved results" banner. Full EN/SW via ARB files with a settings switcher defaulting to device locale. 44dp minimum tap targets, semantic labels, contrast checked against the yellow.

**11. Business content only.** Inline guidance at first upload: "Business content only — products, your shop, your work. No personal photos." Report button on every product, shop and message → reason picker → admin moderation queue. Admin can hide, warn, suspend or ban with a logged reason pushed to the user. Terms and Privacy screens with acceptance recorded (timestamp + version) at signup. Three upheld reports auto-hide content pending review.

## Data model

```
users              id, name, email?, phone?, avatar, provider, provider_id,
                   locale, fcm_token, terms_accepted_at, terms_version, banned_at
seller_profiles    user_id, shop_name, handle(unique), bio, category_id, whatsapp,
                   shop_location(POINT, SPATIAL INDEX), address, region, district,
                   nida_number, nida_image, licence_file, status, rejection_reason,
                   verified_at, rating_avg, rating_count, show_whatsapp
categories         id, parent_id, name_en, name_sw, icon, sort_order, is_active
products           id, seller_id, category_id, title, description, price, currency,
                   stock, condition, is_active, is_hidden, views, timestamps
product_media      id, product_id, type(image|video), path, thumb_path, duration, sort
orders             id, buyer_id, seller_id, code, status, subtotal, delivery_fee, total,
                   delivery_method, address, delivery_location(POINT), notes,
                   payment_method, payment_status, cancelled_reason, status timestamps
order_items        order_id, product_id, title_snapshot, price_snapshot, qty
reviews            id, order_id(unique), seller_id, buyer_id, rating, comment, reply, replied_at
conversations      id, buyer_id, seller_id, product_id?, order_id?, last_message_at
messages           id, conversation_id, sender_id, body, attachment, read_at
favorites          user_id, product_id
reports            id, reporter_id, reportable_type, reportable_id, reason, note,
                   status, resolved_by, resolution
devices            user_id, fcm_token, platform, last_seen_at
```

## Build phases

**Phase 1 — Design foundation.** Extract the palette from the logo. Build `core/theme/` (light + dark), all twelve motion primitives as standalone widgets, the `/motion-gallery` route, and l10n with EN + SW ARB files wired up.
*Verify:* `flutter analyze` clean, app runs, gallery demonstrates all twelve.

**Phase 2 — API skeleton.** All migrations, factories and seeders (10 categories, 20 sellers, 120 products with placeholder media, 40 orders, 60 reviews), Sanctum auth, social login endpoints, phone OTP behind an `SmsGateway` interface (log the code, don't send), API resources, form requests, rate limiting.
*Verify:* `php artisan test` green; a smoke script hits every endpoint successfully.

**Phase 3 — App shell.** go_router with five tabs and the custom transitions, dio client with auth interceptor and token refresh, Riverpod providers, drift cache, error handling, connectivity banner, splash animation, wired to the local API.
*Verify:* navigation uses the custom transitions; offline shows cached state.

**Phase 4 — Discovery.** Home feed, sticky animated chips, debounced search with history, filters sheet, nearby logic with permission flow and district fallback, clustered map view, product cards with distance and rating, staggered reveal, shimmer skeletons.
*Verify:* radius changes re-query; loading/empty/error/data all render.

**Phase 5 — Product & shop.** Product detail with hero transition, media carousel with in-feed video, seller quick-view sheet, shop profile with media grid and review distribution, favourites, share with deep link.
*Verify:* `sokoni.co.tz/@handle` resolves; video plays and pauses correctly on scroll.

**Phase 6 — Auth & seller onboarding.** All four sign-in methods, the 4-step wizard with draft persistence, map pin picker, NIDA and licence upload with compression and preview, pending-state UI, buyer→seller upgrade path.
*Verify:* a fresh account completes onboarding, lands in pending, can create products that stay hidden.

**Phase 7 — Selling & orders.** Product create/edit with multi-media upload progress, my-shop dashboard, cart, checkout, order placement, animated success moment, order lists and timelines both sides, status transitions, FCM push on new order.
*Verify:* end to end — buyer orders, seller gets push, advances status to completed.

**Phase 8 — Chat & reviews.** Conversation list, thread with pinned product context, polling + FCM, image attachments, read receipts, WhatsApp button, order-gated review submission, seller replies.
*Verify:* a feature test proves the API rejects a review without a completed order.

**Phase 9 — Trust & admin.** Report flows, Filament admin with the seller verification queue (NIDA image + licence + map location side by side), moderation queue, user and category management, dashboard stats, Terms and Privacy screens with acceptance recording.
*Verify:* verifying a seller in admin makes their products appear in the feed.

**Phase 10 — Polish & ship.** Every empty and error state illustrated. Performance pass: `flutter build apk --analyze-size`, const constructors, image cache limits, no jank. Full SW translation review. App icon and splash from the logo. Signed release APK + AAB with a generated keystore. Write `docs/API.md`, `docs/DEPLOY.md` (the cPanel runbook below) and `docs/DEMO.md`.
*Verify:* the release build installs and runs against the deployed API.

## cPanel deployment runbook — write this into docs/DEPLOY.md

Host `sokoni.co.tz`, cPanel user `sokoftsn`, home `/home/sokoftsn`, shared IP `198.54.126.252`. This cPanel exposes **Manage Shell** and **Setup Node.js App**, so check for SSH first — it makes composer and artisan far easier. Everything below also works via File Manager + phpMyAdmin if SSH is off.

```
/home/sokoftsn/sokoni-api/           ← Laravel app root, outside the web root
/home/sokoftsn/sokoni-api/public/    ← docroot for the api.sokoni.co.tz subdomain
/home/sokoftsn/public_html/uploads/  ← public disk target for media
```

1. Create subdomain `api.sokoni.co.tz` with docroot `/home/sokoftsn/sokoni-api/public`
2. Set PHP 8.3 in Select PHP Version; enable `bcmath`, `fileinfo`, `gd`, `intl`, `mbstring`, `zip`, `pdo_mysql`, `exif`
3. Create the MySQL DB and user in cPanel, grant all
4. Upload as a zip and extract via File Manager, with `vendor/` included — build locally using `composer install --no-dev --optimize-autoloader` if there's no SSH
5. `.env`: `APP_ENV=production`, `APP_DEBUG=false`, real DB creds, `FILESYSTEM_DISK=public`
6. Import the schema through phpMyAdmin if artisan isn't available
7. Point the `public` disk at `/home/sokoftsn/public_html/uploads` with `'url' => 'https://sokoni.co.tz/uploads'` — **do not rely on `storage:link`**, symlinks are often blocked on shared hosting
8. `chmod` 755 directories, 644 files, 775 on `storage/` and `bootstrap/cache/`
9. Scheduler cron: `* * * * * /usr/local/bin/php /home/sokoftsn/sokoni-api/artisan schedule:run >> /dev/null 2>&1`
10. Force HTTPS; the AutoSSL cert on the account is already active

**Secrets rule:** no API keys, gateway credentials or service accounts ever ship inside the Flutter app. Everything sensitive lives server-side in `.env`. Commit `.env.example` only.

## Working rules

- **Commit discipline.** Conventional commits after each meaningful unit. Never commit `.env`, keystores, `google-services.json`, or `GoogleService-Info.plist`.
- **No placeholder code.** Every function you write is implemented. Where something genuinely can't work yet, write the real implementation and mock only the external call, marked `// MOCK:` and listed in `BLOCKERS.md`.
- **Tests.** Feature tests for every API endpoint; widget tests for the motion primitives and the product card. Not 100% coverage — cover the money paths: auth, orders, reviews, verification.
- **Bilingual as you go.** Never hardcode a user-facing string. Every string enters both ARB files at the moment you write it, not in a cleanup pass.
- **TZS formatting:** `TSh 45,000` — separators, no decimals, via an `intl` helper.
- **Phone numbers:** store E.164 (`+255...`), display local (`0754 123 456`).

## Blockers — stop and ask me only for these four

1. Firebase project — `google-services.json` / `GoogleService-Info.plist`
2. Google Maps API key (Android + iOS, with SHA-1 fingerprint)
3. Facebook App ID and Apple Sign-In service ID
4. Production DB credentials for `sokoni.co.tz`

For each: stub in `.env.example`, mock the call, log in `BLOCKERS.md`, keep building.

## Definition of done

A client demo on a real device where you can: open the app cold into a smooth animated splash and a populated feed; tap a category and watch the feed cross-fade; tap a product and watch the image fly into the detail page; scroll a shop's video grid; register as a seller through all four steps; post a product with three photos and a clip; order it from a second device; watch the seller's phone light up with a push; chat about it; complete it; and leave a five-star review — all in Kiswahili if you flip the language switch.

Begin with Phase 0 now.

