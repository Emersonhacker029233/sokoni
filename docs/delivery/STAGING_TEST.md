# Testing the delivery loop on staging (Sokoni 1.1 + Sokoni Driver)

For whoever runs the end-to-end test against `https://staging-api.sokoni.co.tz`. Nothing here touches production, the live apps, the iOS build in App Review or the App Review account. The staging setup itself is in `staging-deploy/STAGING-STEPS.md` (kept outside git because it holds the staging admin password).

## 1. Build and upload the API package

```powershell
cd D:\Projects\sokoni
powershell -ExecutionPolicy Bypass -File tools\build-api-staging.ps1
```

It builds `staging-deploy\api-staging.zip` from the current commit and prints its SHA-256. The zip never contains `bootstrap/cache` files, compiled views, sessions, logs, `.env` or tests. It contains `BUILD.txt` with the commit it was built from. It also leaves out `public/.htaccess` and `public/.user.ini`, so the server keeps its own (the `.htaccess` there holds the admin password block from STAGING-STEPS.md step 10). Use `-FirstInstall` only for a brand-new server.

### Upload in cPanel File Manager

1. File Manager → **Settings** → tick **Show Hidden Files** → Save.
2. Open `/home/sokoftsn/staging-api`. **Upload** `api-staging.zip` and wait for 100%.
3. Right-click the zip → **Extract** → `/home/sokoftsn/staging-api` → confirm overwriting.
4. Open `BUILD.txt` (right-click → **View**). The `commit:` line must match the one the build script printed. If it shows an older commit, Extract kept the old files. Then delete these from `staging-api` and extract the zip again: the folders `app`, `bootstrap`, `config`, `database`, `lang`, `resources`, `routes`, `vendor`, `public/js`, `public/css`, `public/fonts`, and the files `public/index.php`, `artisan`, `composer.json`, `composer.lock`, `BUILD.txt`. Never delete `.env`, `storage`, `public/uploads`, `public/.htaccess` or `public/.user.ini`.
5. Delete `api-staging.zip` from the server.

### Clear every cached file (do this after every upload)

These files freeze what Laravel and the admin panel see. A Filament cache from before the Drivers screen existed is why Drivers was missing.

1. Open `/home/sokoftsn/staging-api/bootstrap/cache`.
2. Select **everything except `.gitignore`**: the `filament` folder (if there) and any of `config.php`, `routes-v7.php`, `events.php`, `packages.php`, `services.php`, `blade-icons.php`. Click **Delete** (tick "Skip the trash"). Laravel rebuilds what it needs on the next request.
3. Open `/home/sokoftsn/staging-api/storage/framework/views` and delete every `.php` file. Keep `.gitignore`.
4. Don't touch `storage/app`, `storage/logs` or `.env`.

### `.env` additions (once)

Edit `/home/sokoftsn/staging-api/.env` and add, or change:

```
SOKONI_DELIVERY_FEE_CASH_ENABLED=false
PAYMENT_PROVIDER=fake
PAYMENT_ENV=sandbox
PAYMENT_FAKE_APPROVE_AFTER_SECONDS=8
```

`false` tests "Pay now" with the sandbox gateway. Set it back to `true` to test the cash flow (the buyer pays the rider; no payment step).

### Run the new migration (once)

This package adds one nullable column, `orders.delivery_phone`. In **Cron Jobs**, add a job for **Once Per Minute**:

```
cd /home/sokoftsn/staging-api && [ ! -f storage/app/migrated-2026-11-04 ] && PHP=$(ls /opt/cpanel/ea-php84/root/usr/bin/php /opt/alt/php84/usr/bin/php 2>/dev/null | head -n 1) && "$PHP" artisan migrate --force >> storage/logs/setup.log 2>&1 && touch storage/app/migrated-2026-11-04
```

After 2 minutes, `storage/logs/setup.log` ends with `add_delivery_phone_to_orders_table ... DONE` and `storage/app/migrated-2026-11-04` exists. **Delete this cron job.**

### OPcache

If a changed screen still looks old after the steps above, wait 5 minutes (PHP re-checks changed files on a timer). If it's still old, cPanel → **MultiPHP Manager** → tick only `staging-api.sokoni.co.tz` → switch to another PHP version → Apply → switch back to **PHP 8.4** → Apply. That restarts PHP for the staging subdomain only.

### Check the admin Drivers screen

Open `https://staging-api.sokoni.co.tz/admin/drivers`, enter the directory password, sign in as the staging admin.

- Signed-in admin: the Drivers list opens on **Pending review**, and **Trust & Safety → Drivers** is in the menu.
- Not signed in: redirect to `/admin/login`.
- A staff account: 403.
- 404 means the old cache is still there (repeat "Clear every cached file") or the files weren't replaced (check `BUILD.txt`).

## 2. Build the two apps

Customer app, Sokoni 1.1.0 (10), pointed at staging. Build 10 fixes the "Unexpected error … type 'Null' is not a subtype of type 'String'" shown after Place order in build 9, and installs over it:

```powershell
cd D:\Projects\sokoni\app
flutter pub get
flutter build apk --release --flavor prod --split-per-abi --dart-define=API_BASE_URL=https://staging-api.sokoni.co.tz/api
```

Install `app\build\app\outputs\flutter-apk\app-arm64-v8a-prod-release.apk` (`app-armeabi-v7a-prod-release.apk` on an old 32-bit phone). With `android\key.properties` present it is signed with the release key and installs over the Play Store Sokoni on that phone, now talking to staging. Without it, it is debug-signed and Android asks you to uninstall the Play Store Sokoni first.

Driver app, pointed at staging:

```powershell
cd D:\Projects\sokoni\driver_app
flutter pub get
flutter build apk --release --split-per-abi --dart-define=API_BASE_URL=https://staging-api.sokoni.co.tz/api
```

Install `driver_app\build\app\outputs\flutter-apk\app-arm64-v8a-release.apk`.

## 3. The loop with two phones

Accounts: a **buyer**, a **seller** with a verified shop that has a map location, and a **driver**. OTP codes are not sent on staging; find them in `staging-api/storage/logs/laravel.log` (search `MOCK SMS`). The shop and the driver must be within 8 km of each other, and the drop-off within 10 km of the shop. To move a test shop to where you are: STAGING-STEPS.md step 12.

- **Phone A:** Sokoni (buyer).
- **Phone B:** Sokoni Driver (driver) and Sokoni (seller). One phone can hold both apps.

1. **B, Sokoni Driver:** sign in, complete onboarding, Submit. Approve the driver in `/admin/drivers`. Go online.
2. **A, Sokoni:** add a product from the test shop to the cart → Checkout → **Delivery**. "Delivered by a Sokoni rider" appears.
3. Tap **Choose delivery location**, move the map under the pin or tap **Use my location**, fix the address text, **Confirm location**. Check the phone number. The fee line shows the server's quote with the distance; the total is goods + fee.
4. **Place order.** The order screen opens: "Waiting for the shop to accept your order."
5. **B, Sokoni (seller):** Orders → **Shop orders** → the order (marked "Sokoni delivery") → **Accept order**. The seller screen shows "Waiting for the buyer to pay for delivery."
6. **A:** within a few seconds the order shows **Pay for delivery**: goods, delivery fee, total, the 20-minute deadline, "Test mode". Pick a network, keep a number not ending in 0001 or 0002, tap **Pay now**. The card says to approve on the phone; after about 8 seconds the server confirms and the screen moves to **Finding a driver** with the 4-digit **delivery PIN**.
7. **B, Sokoni Driver:** the offer arrives (polling every 10 s). Accept it.
8. **A:** the rider card appears (name, vehicle, plate, Call) and the live map shows the rider (the server heartbeat every ~10 s on staging, since Firebase isn't configured).
9. **B, Sokoni (seller):** the order shows the rider and the **pickup code**. **B, Sokoni Driver:** Arrived at shop → type the pickup code → Picked up → On the way → Arrived.
10. **B, Sokoni Driver:** enter the PIN that phone A shows → Delivered. **A:** "Delivered", then **Rate your rider** → stars → Send rating.
11. Both apps' Notifications screens list every step; tapping one opens the order.

## 4. The failure states

| To see | Do |
|---|---|
| Payment declined | Pay with a number ending `0001`. After ~8 s: "The payment was declined." and **Try again**. |
| Payment never answered | Pay with a number ending `0002`. After 2 minutes: "The payment request expired…", **Try again**. |
| Unpaid timeout | Don't pay. After 20 minutes (next cron minute): "The delivery fee wasn't paid within 20 minutes, so the order was cancelled." The seller sees "Delivery cancelled". |
| No driver found | Keep every driver offline after paying. After 3 waves (~3 minutes): "No driver available" with Try again, Switch to pickup, Cancel delivery. |
| Driver cancelled | Driver accepts, then cancels before pickup: "Your rider had to cancel. We're finding you another one." |
| Refund pending | Pay, then cancel before a driver accepts: the order shows "Refund of TSh … pending". |
| Cash mode | Set `SOKONI_DELIVERY_FEE_CASH_ENABLED=true`: no Pay now; the order says to pay the rider in cash. |

Switch the phone's app language to Kiswahili in Settings to check every message in Swahili.
