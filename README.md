# Sokoni

**Sokoni** ("marketplace" in Kiswahili) is a Tanzanian mobile marketplace app: buyers discover verified sellers and products near them, chat, and order; sellers register with verified identity and post photos/video of what they sell. Bilingual (English/Kiswahili) from day one, built for mid-range Android on patchy 3G/4G.

Full product specification: [CLAUDE.md](CLAUDE.md). Build progress: [PROGRESS.md](PROGRESS.md). Judgement calls made along the way: [DECISIONS.md](DECISIONS.md). Items needing the project owner's input: [BLOCKERS.md](BLOCKERS.md).

## Repository layout

```
app/     Flutter client (Android + iOS)
api/     Laravel API + Filament admin
docs/    API reference, deployment runbook, demo script
assets/  Shared brand assets (logo, palette source)
```

## Stack

- **Client**: Flutter 3.x, Riverpod 2/3 + code generation, go_router, dio + retrofit + freezed, drift (offline cache), flutter_secure_storage.
- **API**: Laravel 13 (PHP 8.3+ target, developed here on PHP 8.4 via Laravel Herd), Sanctum tokens, MySQL 8 with `ST_Distance_Sphere` for geo queries, Filament v5 admin (v3 isn't compatible with Laravel 13 — see DECISIONS.md).
- **Push**: Firebase Cloud Messaging. **Maps**: google_maps_flutter + geolocator.
- **Deploy target**: Namecheap cPanel shared hosting (`sokoni.co.tz`) — no Docker, no queue workers, no WebSocket server. See [docs/DEPLOY.md](docs/DEPLOY.md).

## Prerequisites

- Flutter SDK (stable channel) — `flutter doctor` should show no blocking issues for Android/iOS.
- PHP 8.3+ and Composer. This project was set up using [Laravel Herd](https://herd.laravel.com/windows), which bundles both plus a local dev proxy — recommended on Windows.
- MySQL 8 for anything beyond local SQLite dev (spatial queries require it — SQLite is fine for quick iteration but does not support `ST_Distance_Sphere`).
- A Firebase project, Google Maps API key, and Facebook/Apple sign-in credentials for the features that need them — see [BLOCKERS.md](BLOCKERS.md) for what's stubbed in their absence.

## Running the API

```powershell
cd api
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

The API serves at `http://127.0.0.1:8000` by default. Admin panel (Filament) is at `/admin`.

## Running the app

```powershell
cd app
flutter pub get
flutter pub run build_runner build --delete-conflicting-outputs
flutter run
```

Point the app at your local API via the base URL in `lib/core/network/dio_client.dart` (defaults to an emulator-reachable loopback address — Android emulators use `10.0.2.2` instead of `127.0.0.1`).

## Tests

```powershell
cd api && php artisan test
cd app && flutter test
```

## Debug routes

- `/motion-gallery` — demonstrates all twelve motion primitives used throughout the app (page transitions, hero images, staggered reveal, shimmer skeletons, animated chips, badge bounce, bottom sheets, success moments, nav bar, pull-to-refresh, carousel parallax, splash).

## Building a release

```powershell
cd app
flutter build apk --release --dart-define=API_BASE_URL=https://api.sokoni.co.tz/api
flutter build appbundle --release --dart-define=API_BASE_URL=https://api.sokoni.co.tz/api
```

Needs `android/key.properties` + `android/sokoni-release.jks` (both gitignored — not in this repo). Without them, the release build falls back to debug signing. See [docs/DEPLOY.md](docs/DEPLOY.md) for the full deployment runbook and [docs/DEMO.md](docs/DEMO.md) for a client demo script.
