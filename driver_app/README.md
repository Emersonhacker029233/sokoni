# Sokoni Driver

Flutter app for Sokoni delivery drivers (boda boda, bajaj, car, bicycle). Android `tz.co.sokoni.driver`, iOS bundle `tz.co.sokoni.driver`. Plan: [docs/delivery/PLAN.md](../docs/delivery/PLAN.md). API: [docs/delivery/API.md](../docs/delivery/API.md).

Same stack and conventions as the customer app in `/app`: Riverpod, go_router, dio + retrofit, freezed models, drift, flutter_secure_storage, Plus Jakarta Sans, the Sokoni palette, and EN/SW strings. Swahili is the default language.

## What it does

- **Sign in** with the existing Sokoni accounts: phone + SMS code (creates the account for someone new), or username/password.
- **Onboarding** in four saved steps: NIDA and licence, vehicle, document photos (compressed before upload), review and submit. Then a status screen while Sokoni reviews.
- **Shift**: go online with a location explainer before each system prompt. Offers arrive by FCM and a 10 s poll. Each offer shows pay, distance to the shop, trip length, shop and rough drop-off area only.
- **Job**: map of both stops, Google Maps navigation, calls, pickup code from the seller, delivery PIN from the customer, optional proof photo, problem reports with the unreachable-customer rule, cancel before pickup, rate the customer.
- **Offline**: arrived-at-shop, on-the-way, arrived and call attempts are queued in drift and replayed in order. The profile, active job and earnings open from the last good copy.
- **Earnings**: day and week totals, commission, balance owed either way, payouts.

## Background location

Location runs only while online or on a job. Android uses a geolocator foreground service with a persistent notification (no `ACCESS_BACKGROUND_LOCATION`). iOS uses the `location` background mode and asks for "Always" only when going online, after an explainer. The server gets a heartbeat every 30 s. During a job the app writes to Firebase Realtime Database every 4 s. Without Firebase, the heartbeat drops to every 10 s so the customer's map still moves.

## Run

The server needs `DELIVERY_ENABLED=true`. Otherwise every driver route returns 404 and the app shows "Deliveries aren't open yet".

```powershell
cd driver_app
flutter pub get
dart run build_runner build --delete-conflicting-outputs
flutter run                                   # debug: http://10.0.2.2:8000/api (emulator)
flutter run --dart-define=API_BASE_URL=http://192.168.1.20:8000/api   # phone on the same Wi-Fi
```

Debug builds allow plain HTTP for a laptop API. Release builds are HTTPS-only and default to `https://api.sokoni.co.tz/api`.

## Firebase

Not configured yet. Drop `android/app/google-services.json` and `ios/Runner/GoogleService-Info.plist` for a Firebase app registered as `tz.co.sokoni.driver` in project `sokoni-71910`. The Gradle plugin applies itself only when the file exists. Without them the app runs on polling, with no push and no live tracking stream.

## Tests

```powershell
flutter analyze
flutter test
```

## Icon

`dart run tool/make_icon.dart` derives the driver icon from the customer app's icon (the bag mark on black). Then run `dart run flutter_launcher_icons`.
