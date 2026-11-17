# Release: Bolt delivery and returns, dark (production checklist)

This puts the Bolt delivery and returns code on `api.sokoni.co.tz` **switched off**. Nobody sees anything new: the apps in the stores (Android 1.0.0, iOS 1.0 (9) in App Review) keep working exactly as now. Switching it on, for test phones first, is a separate step later ([BOLT.md](BOLT.md) § 7). There is no staging run of this exact package on MariaDB, so follow the steps in order and stop wherever a step says **STOP**.

- **Branch:** `feature/bolt-delivery`. It is production's code (`fix/ugc-moderation`, the 1.0 (9) release) plus delivery by Bolt and returns. The Sokoni Driver app and own-driver dispatch are not in it.
- **Code commit in the zips:** `5fa4c801a34a578f484f10bc241813ffcef62f41`
- **Production base it was built against:** `d7b30d44e1449ad5bc8a020b1b399adbc0e571eb` (what [../moderation/RELEASE.md](../moderation/RELEASE.md) deploys)
- **Zips:** `D:\Projects\sokoni-release-bolt-dark\` on the Windows PC:
  - `1-migrations.zip`: the 14 new database migration files
  - `2-code.zip`: the 95 new or changed code files, plus `RELEASE-bolt-dark.txt`
  - `rollback-code.zip`: production's version of the 23 files this release changes or deletes
- **Tools:** cPanel File Manager, phpMyAdmin, cPanel Cron Jobs, and PowerShell on your PC for the checks. No SSH needed.
- **Time:** about 40 minutes. Pick a quiet hour. Nothing should go down: the order below never runs new code against an old database.

**Do the 1.0 (9) moderation release first.** This release builds on it. Step 2.1 stops you if it isn't live yet.

**Why two uploads.** The new code reads new database columns, so it must not go live before the migrations have run. The old code doesn't care about the new tables and columns. So: upload the migrations, run them, check, and only then upload the code.

### What changes for the live apps

- **Nothing they show.** With `BOLT_ENABLED=false` every delivery and returns route answers exactly like a route that doesn't exist, checkout ignores the new fields, and no shop, product, order, user or conversation response gains or loses a field. This was checked by replaying 26 requests the live apps make against production's code and this release and comparing the answers.
- **One new route,** `GET /api/features`, which the live apps never call. It answers `delivery_enabled: false, returns_enabled: false`.
- **One existing answer is corrected:** `POST /api/orders` (placing an order) now says `"status": "pending"` and `"payment_status": "unpaid"` for the new order, where production says `null` today. Every later read of that order already said `pending` / `unpaid`; only the first answer was wrong, because Eloquent doesn't read database defaults back after an insert. App 1.1 showed "type 'Null' is not a subtype of type 'String'" after placing an order because of it, and the 1.0 apps read that answer the same way, so they should stop showing that error too. The change is three default values in `app/Models/Order.php` (`$attributes`), so it can be taken out on its own if it ever causes trouble.
- **The Terms page stays version 1.1**, with no new clause, until returns are switched on. Even then `users.terms_version` is never changed, so iOS 1.0 (9) is never asked to re-accept anything ([RETURNS.md](RETURNS.md) § 5).
- **`public/setup.php` is deleted.** It is an old one-time setup script with a password written in it, and that password is in the git history. Step 5 removes it wherever it is.

---

## 1. (a) Back up

- [ ] **1.1 Database.** cPanel → phpMyAdmin → click the Sokoni database in the left column → **Export** → Export method **Custom** → under *Object creation options* tick **Add DROP TABLE / VIEW / PROCEDURE / FUNCTION / EVENT / TRIGGER statement** → Compression **gzipped** → **Export**. Keep the downloaded `.sql.gz` file on your PC.
- [ ] **1.2 Code.** File Manager → `/home/sokoftsn/sokoni-api` → select the folders `app`, `bootstrap`, `config`, `database`, `lang`, `public`, `resources` and `routes` (Ctrl-click) → **Compress** → Zip Archive → name it `backup-before-bolt-dark.zip` → **Compress Files**. Right-click the zip → **Download**. Then delete the zip from the server.
- [ ] **1.3 `.env`.** In the same folder, right-click `.env` → **Download**. (Turn on *Settings → Show Hidden Files* if you can't see it.)

## 2. Pre-flight checks (nothing changes yet)

Run these in phpMyAdmin: select the Sokoni database → **SQL** tab → paste → **Go**.

- [ ] **2.1 The database is where this release expects it.**
  ```sql
  SELECT migration, batch FROM migrations ORDER BY id DESC LIMIT 3;
  ```
  The top row must be `2026_11_04_100400_create_notifications_table`.
  - Top row `2026_11_17_100500_add_returns_terms_to_users_table`: the migrations already ran. Skip steps 3 and 4.
  - Top row `2026_10_06_014500_create_trusted_devices_table`: **STOP.** The 1.0 (9) moderation release isn't live yet. Do [../moderation/RELEASE.md](../moderation/RELEASE.md) first.
  - Anything else: **STOP.** Send the three rows to your developer.
- [ ] **2.2 No delivery tables yet.**
  ```sql
  SELECT TABLE_NAME FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('driver_profiles','vehicles','driver_payouts','delivery_jobs','delivery_job_events','delivery_offers',
                       'driver_locations','payments','payment_webhook_events','delivery_ratings','return_requests','return_request_photos');
  ```
  Expected: **no rows**. Any row: **STOP**. Something from staging reached production; send the list to your developer.
- [ ] **2.3 Database server and table type.**
  ```sql
  SELECT @@version AS server, @@default_storage_engine AS default_engine;
  SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('users','orders','seller_profiles','devices');
  ```
  Expected: a `10.x-MariaDB` or `11.x-MariaDB` server (10.3 or newer) and four rows, all `InnoDB`. If any row says `MyISAM`, **STOP**: the new foreign keys need InnoDB.
- [ ] **2.4 The App Review account** (it must never see delivery or returns, so the server must recognise it).
  ```sql
  SELECT id, phone, username, terms_version, banned_at FROM users WHERE phone = '+255783029233';
  ```
  Expected: one row, `username` = `appreview`, `banned_at` = NULL.

In File Manager:

- [ ] **2.5 The code on the server is the 1.0 (9) release.**
  - `/home/sokoftsn/sokoni-api/app/Http/Controllers/Api/BlockController.php` must exist. If it doesn't, **STOP**: the moderation release isn't live.
  - `/home/sokoftsn/sokoni-api/app/Http/Controllers/Api/Delivery` must **not** exist. If it does, **STOP**.
  - Note whether `/home/sokoftsn/sokoni-api/public/setup.php` or `/home/sokoftsn/public_html/setup.php` exists. Step 5 deletes them.
- [ ] **2.6 `.env`.** Right-click `/home/sokoftsn/sokoni-api/.env` → **View**. Check:
  - `APP_ENV=production` (this also keeps the sandbox payment gateway refused)
  - `REVIEW_ACCOUNT_PHONE=+255783029233` is there
  - no line starts with `BOLT_` or `DELIVERY_RETURNS_` yet. If one does, note its value.
  - note the `PAYMENT_PROVIDER=` value, if any (expected: missing or `none`). Leave it as it is.

## 3. (b) Upload part 1: the 14 migration files

- [ ] File Manager → open `/home/sokoftsn/sokoni-api` → **Upload** → choose `1-migrations.zip` → when it reaches 100%, click **Go Back**.
- [ ] Right-click `1-migrations.zip` → **Extract** → the path must be `/home/sokoftsn/sokoni-api` → **Extract Files**.
- [ ] Open `database/migrations`. These 14 files are now there:
  ```
  2026_10_29_090000_create_driver_profiles_table.php
  2026_10_29_090100_create_driver_payouts_table.php
  2026_10_29_090200_create_delivery_jobs_table.php
  2026_10_29_090300_create_driver_locations_table.php
  2026_10_29_090400_create_payments_tables.php
  2026_10_29_090500_create_delivery_ratings_table.php
  2026_10_29_090600_add_delivery_columns_to_existing_tables.php
  2026_11_04_090000_add_delivery_phone_to_orders_table.php
  2026_11_17_100000_add_bolt_columns_to_delivery_jobs_table.php
  2026_11_17_100100_add_return_policy_to_seller_profiles_table.php
  2026_11_17_100200_add_return_policy_to_orders_table.php
  2026_11_17_100300_create_return_requests_table.php
  2026_11_17_100400_add_return_request_id_to_payments_table.php
  2026_11_17_100500_add_returns_terms_to_users_table.php
  ```
- [ ] Delete `1-migrations.zip` from `/home/sokoftsn/sokoni-api`.

Nothing has changed for users yet. These files only do something when step 4 runs them.

## 4. (c) Run the migrations with a one-time cron job

What they do, all additive:

- **New, empty tables:** `driver_profiles`, `vehicles`, `driver_payouts`, `delivery_jobs`, `delivery_job_events`, `delivery_offers`, `driver_locations`, `payments`, `payment_webhook_events`, `delivery_ratings`, `return_requests`, `return_request_photos`. The driver tables are history from the retired Sokoni Driver work: they stay empty, but `delivery_jobs` points at them, and the brief keeps historical migrations as they are.
- **New columns on existing tables,** each empty or with a default that keeps today's behaviour: `orders.fulfilment`, `orders.delivery_phone`, `orders.return_policy` (all empty), `devices.app` (empty), `seller_profiles.return_policy` (default `none`, so no shop accepts returns until its owner chooses), `seller_profiles.return_notes` (empty), `users.returns_terms_version` and `users.returns_terms_accepted_at` (empty).
- No existing column changes, no row is changed or deleted, and every index is under 767 bytes (the longest is 724), within every MySQL and MariaDB limit.

The command names the 14 files, so nothing else can run by accident. The last six (`2026_11_17_...`) check what already exists and clean up after a part-way failure; the first eight (from the earlier delivery work) don't, so if one of those fails, Appendix B removes what it made before a retry.

- [ ] cPanel → **Cron Jobs** → *Add New Cron Job* → Common Settings: **Once Per Minute (\* \* \* \* \*)** → paste this into **Command**, as one line → **Add New Cron Job**:
  ```
  cd /home/sokoftsn/sokoni-api && [ ! -e storage/app/release-bolt-dark-migrate.lock ] && touch storage/app/release-bolt-dark-migrate.lock && { /usr/local/bin/php -v | head -n 1; /usr/local/bin/php artisan migrate --force --no-interaction --path=database/migrations/2026_10_29_090000_create_driver_profiles_table.php --path=database/migrations/2026_10_29_090100_create_driver_payouts_table.php --path=database/migrations/2026_10_29_090200_create_delivery_jobs_table.php --path=database/migrations/2026_10_29_090300_create_driver_locations_table.php --path=database/migrations/2026_10_29_090400_create_payments_tables.php --path=database/migrations/2026_10_29_090500_create_delivery_ratings_table.php --path=database/migrations/2026_10_29_090600_add_delivery_columns_to_existing_tables.php --path=database/migrations/2026_11_04_090000_add_delivery_phone_to_orders_table.php --path=database/migrations/2026_11_17_100000_add_bolt_columns_to_delivery_jobs_table.php --path=database/migrations/2026_11_17_100100_add_return_policy_to_seller_profiles_table.php --path=database/migrations/2026_11_17_100200_add_return_policy_to_orders_table.php --path=database/migrations/2026_11_17_100300_create_return_requests_table.php --path=database/migrations/2026_11_17_100400_add_return_request_id_to_payments_table.php --path=database/migrations/2026_11_17_100500_add_returns_terms_to_users_table.php; echo "EXIT CODE: $?"; } > storage/logs/release-bolt-dark-migrate.log 2>&1
  ```
  The lock file makes it run exactly once, even though the job fires every minute.
- [ ] Wait 2 minutes. File Manager → `/home/sokoftsn/sokoni-api/storage/logs/release-bolt-dark-migrate.log` → right-click → **View**. You should see:
  ```
  PHP 8.3.x (cli) ...
     INFO  Running migrations.
    2026_10_29_090000_create_driver_profiles_table ......... DONE
    2026_10_29_090100_create_driver_payouts_table ......... DONE
    2026_10_29_090200_create_delivery_jobs_table ......... DONE
    2026_10_29_090300_create_driver_locations_table ......... DONE
    2026_10_29_090400_create_payments_tables ......... DONE
    2026_10_29_090500_create_delivery_ratings_table ......... DONE
    2026_10_29_090600_add_delivery_columns_to_existing_tables ......... DONE
    2026_11_04_090000_add_delivery_phone_to_orders_table ......... DONE
    2026_11_17_100000_add_bolt_columns_to_delivery_jobs_table ......... DONE
    2026_11_17_100100_add_return_policy_to_seller_profiles_table ......... DONE
    2026_11_17_100200_add_return_policy_to_orders_table ......... DONE
    2026_11_17_100300_create_return_requests_table ......... DONE
    2026_11_17_100400_add_return_request_id_to_payments_table ......... DONE
    2026_11_17_100500_add_returns_terms_to_users_table ......... DONE
  EXIT CODE: 0
  ```
  - **The log doesn't exist after 3 minutes:** check the cron job was saved with *Once Per Minute*.
  - **The first line isn't PHP 8.2 or newer:** edit the cron job and replace both `/usr/local/bin/php` with `/opt/alt/php83/usr/bin/php`. Delete `storage/app/release-bolt-dark-migrate.lock`, then wait 2 minutes again.
  - **`EXIT CODE` isn't 0:** **STOP.** Delete the cron job and don't upload part 2. The site is still on the old code and still works. Send the log to your developer. Before any retry, run Appendix B.
- [ ] **Delete the cron job:** cPanel → Cron Jobs → *Current Cron Jobs* → **Delete** on this line. Leave the lock file where it is.
- [ ] Confirm in phpMyAdmin:
  ```sql
  SELECT migration, batch FROM migrations WHERE migration LIKE '2026_10_29_09%' OR migration LIKE '2026_11_04_09%' OR migration LIKE '2026_11_17_1%';
  ```
  Expected: 14 rows with the same `batch` number.

The live apps keep working at this point: the old code ignores the new tables and columns.

## 5. (b) Upload part 2: the code

- [ ] File Manager → `/home/sokoftsn/sokoni-api` → **Upload** `2-code.zip` → **Go Back** → right-click → **Extract** → path `/home/sokoftsn/sokoni-api` → **Extract Files**. It overwrites the old files. The full list is in [Appendix A](#appendix-a-what-is-in-each-zip).
- [ ] Open `/home/sokoftsn/sokoni-api/RELEASE-bolt-dark.txt`. It must say `commit: 5fa4c801a34a578f484f10bc241813ffcef62f41`.
- [ ] Delete `2-code.zip` from the server.
- [ ] **Delete these files**, which this release removes (skip any that isn't there):
  - `/home/sokoftsn/sokoni-api/app/Services/Payment/PaymentResult.php`
  - `/home/sokoftsn/sokoni-api/app/Services/Payment/UnimplementedPaymentGateway.php`
  - `/home/sokoftsn/sokoni-api/public/setup.php`
  - `/home/sokoftsn/public_html/setup.php`
- [ ] Never delete `.env`, `vendor/`, `storage/` or anything in `public_html/uploads`. `vendor/` doesn't change in this release (`composer.json` and `composer.lock` are the same), and the new classes load the same way the 1.0 (9) release's did.

The new code is live from this moment. Do steps 6 and 7 straight away.

## 6. (d) `.env` lines

Right-click `/home/sokoftsn/sokoni-api/.env` → **Edit**. Add at the end, exactly like this, with no spaces around `=`:

```
# Delivery by Bolt and returns (docs/delivery/BOLT.md). All off.
BOLT_ENABLED=false
DELIVERY_RETURNS_ENABLED=false
BOLT_TEST_PHONES=
BOLT_CLIENT_ID=
BOLT_CLIENT_SECRET=
BOLT_ENV=sandbox
BOLT_SANDBOX_UUID=
BOLT_WEBHOOK_TOKEN=
```

- If step 2.6 found any of these already there, keep only one of each, with the values above.
- **Leave `BOLT_CLIENT_ID` and `BOLT_CLIENT_SECRET` empty for this release.** With both `BOLT_ENABLED=false` and no client id, the scheduler runs exactly the jobs it runs today; the 10-second Bolt tracker is only added once Bolt is switched on or has credentials. You enter the credentials yourself when you switch on ([BOLT.md](BOLT.md) § 3).
- Don't change `PAYMENT_PROVIDER`. A `DELIVERY_ENABLED` line, if one exists, is no longer read; leave it or delete it.

**Save Changes.** No other configuration file needs editing.

## 7. Clear the cached files

Laravel may be serving cached copies of the old routes, settings and pages. In File Manager:

- [ ] `/home/sokoftsn/sokoni-api/bootstrap/cache/`: delete these **if they are there**: `config.php`, `routes-v7.php`, `events.php` and the folder `filament`.
  - **Do not** delete `packages.php`, `services.php` or `.gitignore`.
- [ ] `/home/sokoftsn/sokoni-api/storage/framework/views/`: **Select All**, Ctrl-click `.gitignore` to leave it out → **Delete**. These are compiled page templates and they rebuild themselves on the next visit.

Why: a cached `routes-v7.php` would hide `/api/features`, a cached `config.php` would ignore the `.env` lines from step 6 (and could leave `config/delivery.php` unread), a `filament` cache would hide the two new admin pages, and an old compiled Terms page would show stale text.

## 8. (e) Post-deploy checks

### 8.1 PowerShell checks

Open **Windows PowerShell** on your PC and paste the blocks below **in order into the same window**. Each prints what to expect next to what it got. They change nothing on the server except signing the review account in and out.

**Setup**

```powershell
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$api   = 'https://api.sokoni.co.tz/api'
$plain = @{ Accept = 'application/json' }
function Status($scriptBlock) { try { & $scriptBlock | Out-Null; 200 } catch { [int]$_.Exception.Response.StatusCode } }
```

**Check 1: the new code is live, and everything is off**

```powershell
try { $f = (Invoke-RestMethod -Uri "$api/features" -Headers $plain).data; "features: delivery $($f.delivery_enabled), returns $($f.returns_enabled)   (expect False / False)" }
catch { "FAIL: /api/features answered $([int]$_.Exception.Response.StatusCode). 404 means old code or a cached routes-v7.php: redo steps 5 and 7" }
```

**Check 2: the delivery and returns routes look like they don't exist**

```powershell
foreach ($u in @('delivery/config', 'delivery/orders/1', 'returns')) {
  try { Invoke-RestMethod -Uri "$api/$u" -Headers $plain | Out-Null; "FAIL: /api/$u answered" }
  catch { $r = $_.ErrorDetails.Message; "/api/$u : $([int]$_.Exception.Response.StatusCode) $r   (expect 404 The route api/$u could not be found.)" }
}
"webhook: $(Status { Invoke-RestMethod -Method Post -Uri "$api/webhooks/bolt" -Headers $plain })   (expect 404)"
```

A `401` here means the cached routes or config weren't cleared: redo step 7.

**Check 3: the App Store review account sees nothing new, and isn't asked to accept Terms again**

```powershell
$login = Invoke-RestMethod -Method Post -Uri "$api/auth/login" -Headers $plain -ContentType 'application/json' -Body (@{ login = 'appreview'; password = 'Review2026'; terms_version = '1.1' } | ConvertTo-Json)
$token = $login.token
$auth  = @{ Accept = 'application/json'; Authorization = "Bearer $token" }
$me    = (Invoke-RestMethod -Uri "$api/auth/me" -Headers $auth).data
"signed in: $([bool]$token)   terms_accepted $($me.terms_accepted)   terms_version $($me.terms_version)   (expect True / True / 1.1)"
$rf = Invoke-WebRequest -Uri "$api/features" -Headers $auth -UseBasicParsing
"features as review account: $($rf.Content)   (expect {""data"":{""delivery_enabled"":false,""returns_enabled"":false}})"
try { Invoke-RestMethod -Uri "$api/delivery/config" -Headers $auth | Out-Null; 'FAIL: delivery config answered for the review account' }
catch { "delivery config signed in: $([int]$_.Exception.Response.StatusCode)   (expect 404)" }
```

**Check 4: shop and product pages carry no new fields**

```powershell
$product = (Invoke-RestMethod -Uri "$api/products" -Headers $plain).data | Select-Object -First 1
$p = (Invoke-WebRequest -Uri "$api/products/$($product.id)" -Headers $auth -UseBasicParsing).Content
$s = (Invoke-WebRequest -Uri "$api/sellers/$($product.seller.handle)" -Headers $auth -UseBasicParsing).Content
"product #$($product.id): return_policy $($p -match '"return_policy"')   shop: return_policy $($s -match '"return_policy"')   (expect False / False)"
```

**Check 5: the Terms page is unchanged, and the setup script is gone**

```powershell
$terms = (Invoke-WebRequest -Uri 'https://sokoni.co.tz/terms' -UseBasicParsing).Content
"Terms version 1.1: $($terms -match 'version 1\.1'), return policy clause: $($terms -match 'Return Policy|Sera ya Kurudisha')   (expect True / False)"
"api setup.php: $(Status { Invoke-WebRequest -Uri 'https://api.sokoni.co.tz/setup.php' -UseBasicParsing })   site setup.php: $(Status { Invoke-WebRequest -Uri 'https://sokoni.co.tz/setup.php' -UseBasicParsing })   (expect 404 / 404)"
```

A `403` for either `setup.php` means the file is still there: delete it (step 5).

**Check 6: sign the review account out**

```powershell
Invoke-RestMethod -Method Post -Uri "$api/auth/logout" -Headers $auth | Out-Null; 'signed out'
```

If any check prints `FAIL`, an unexpected value, or a red PowerShell error with a 500 status, go to step 9 (rollback).

### 8.2 Admin panel (browser)

- [ ] Sign in at `https://api.sokoni.co.tz/admin`. The dashboard loads.
- [ ] The menu has **Commerce → Bolt deliveries** and **Trust & Safety → Returns**. Both open and are empty, with no badge.
- [ ] **Trust & Safety → Reports**, **Trust & Safety → Seller Verification** and **Commerce → Orders** open as before.

### 8.3 The apps already in the stores

- [ ] On a phone with the live Android app (1.0.0), and on an iPhone or iPad with the iOS build (TestFlight 1.0 (9) if you have it): the feed loads, a shop and a product open, an existing chat opens and you can send a message. Checkout shows the same options as before, with no Bolt or return wording.
- [ ] If you have a staff buyer account and a staff shop: place one small order between them, check it appears for both, then cancel it from the shop. (This exercises the corrected `POST /api/orders` answer.)

### 8.4 Scheduler and logs

- [ ] cPanel → **Cron Jobs**: the original scheduler line is still there, unchanged:
  ```
  * * * * * /usr/local/bin/php /home/sokoftsn/sokoni-api/artisan schedule:run >> /dev/null 2>&1
  ```
- [ ] After 10 minutes: File Manager → `/home/sokoftsn/sokoni-api/storage/logs/` → open today's `laravel-*.log` (if there is one) and look for `ERROR` lines written since the deploy. None should mention `delivery`, `Bolt`, `returns` or `features`.

When all of step 8 passes, the release is done. Delivery stays off until you follow [BOLT.md](BOLT.md) § 7.

## 9. (f) Rollback

**When:** after step 5, the app or website breaks (feed doesn't load, sign-in fails, 500 errors) and step 7 didn't fix it.

### 9.1 Code: about 3 minutes, and usually all you need

1. [ ] File Manager → `/home/sokoftsn/sokoni-api` → **Upload** `rollback-code.zip` → **Extract** into `/home/sokoftsn/sokoni-api` → delete the zip. This puts back production's version of every file `2-code.zip` changed, and the two payment files step 5 deleted. (`setup.php` is deliberately not in it.)
2. [ ] **Delete these two folders** (the admin panel finds pages by folder, and these need the new code): `/home/sokoftsn/sokoni-api/app/Filament/Resources/BoltDeliveries` and `/home/sokoftsn/sokoni-api/app/Filament/Resources/ReturnRequests`.
3. [ ] Repeat step 7 (clear the cached files).
4. [ ] Check: `https://api.sokoni.co.tz/api/features` shows a 404 page (the old code has no such route), and the app's feed and sign-in work again.

The other files that are new in this release stay on the server, but nothing in the old code loads them. If `rollback-code.zip` isn't to hand, extract the step 1.2 backup zip the same way, then do 2 and 3.

### 9.2 Database: normally leave it

The old code works with the new tables and columns, because they are additive. Only roll the database back if the release is abandoned for good, and only after 9.1. Nothing is lost while delivery is off: the new tables are empty.

**By cron** (same pattern as step 4): add a *Once Per Minute* cron job with this single line, wait 2 minutes, check the log, then delete the cron job.

```
cd /home/sokoftsn/sokoni-api && [ ! -e storage/app/release-bolt-dark-rollback.lock ] && touch storage/app/release-bolt-dark-rollback.lock && { /usr/local/bin/php artisan migrate:rollback --force --no-interaction --path=database/migrations/2026_10_29_090000_create_driver_profiles_table.php --path=database/migrations/2026_10_29_090100_create_driver_payouts_table.php --path=database/migrations/2026_10_29_090200_create_delivery_jobs_table.php --path=database/migrations/2026_10_29_090300_create_driver_locations_table.php --path=database/migrations/2026_10_29_090400_create_payments_tables.php --path=database/migrations/2026_10_29_090500_create_delivery_ratings_table.php --path=database/migrations/2026_10_29_090600_add_delivery_columns_to_existing_tables.php --path=database/migrations/2026_11_04_090000_add_delivery_phone_to_orders_table.php --path=database/migrations/2026_11_17_100000_add_bolt_columns_to_delivery_jobs_table.php --path=database/migrations/2026_11_17_100100_add_return_policy_to_seller_profiles_table.php --path=database/migrations/2026_11_17_100200_add_return_policy_to_orders_table.php --path=database/migrations/2026_11_17_100300_create_return_requests_table.php --path=database/migrations/2026_11_17_100400_add_return_request_id_to_payments_table.php --path=database/migrations/2026_11_17_100500_add_returns_terms_to_users_table.php; echo "EXIT CODE: $?"; } > storage/logs/release-bolt-dark-rollback.log 2>&1
```

`storage/logs/release-bolt-dark-rollback.log` should list the 14, newest first, each `DONE`, then `EXIT CODE: 0`. It only works while these 14 are still the last batch (step 4's), which they are unless another release ran migrations since. Keep the 14 migration files on the server either way.

**Or by hand:** run Appendix B.

### 9.3 Last resort

phpMyAdmin → **Import** the `.sql.gz` from step 1.1. This also throws away every order, message and sign-up made since the backup.

---

## Appendix A: what is in each zip

All paths are relative to `/home/sokoftsn/sokoni-api`.

**`1-migrations.zip`:** the 14 files in `database/migrations/` listed in step 3.

**`2-code.zip`** has 95 files plus `RELEASE-bolt-dark.txt`: every runtime file added or changed since production's code (`d7b30d4`). Tests, factories and `.env.example` are left out. ✱ = changed, the rest are new.

- `app/Console/Commands/`: `BoltSandbox.php`, `DeliveryTick.php`, `DeliveryTrack.php`
- `app/Filament/Resources/BoltDeliveries/`: `BoltDeliveryResource.php`, `Pages/ListBoltDeliveries.php`, `Pages/ViewBoltDelivery.php`, `Schemas/BoltDeliveryInfolist.php`, `Tables/BoltDeliveriesTable.php`
- `app/Filament/Resources/ReturnRequests/`: `ReturnRequestResource.php`, `Pages/ListReturnRequests.php`, `Pages/ViewReturnRequest.php`, `Schemas/ReturnRequestInfolist.php`, `Tables/ReturnRequestsTable.php`
- `app/Http/Controllers/Api/`: `ConversationController.php` ✱, `FeatureController.php`, `OrderController.php` ✱, `ReportController.php` ✱, `ReturnController.php`
- `app/Http/Controllers/Api/Delivery/`: `BoltWebhookController.php`, `CustomerDeliveryController.php`
- `app/Http/Middleware/`: `EnsureDeliveryAccess.php`, `EnsureDeliveryEnabled.php`
- `app/Http/Requests/`: `CheckoutRequest.php` ✱, `Delivery/DeliveryQuoteRequest.php`, `Delivery/PayDeliveryRequest.php`, `Returns/StoreReturnRequest.php`
- `app/Http/Resources/`: `ConversationResource.php` ✱, `OrderResource.php` ✱, `ProductResource.php` ✱, `SellerProfileResource.php` ✱, `UserResource.php` ✱, `Delivery/DeliveryJobResource.php`, `Returns/ReturnRequestResource.php`
- `app/Models/`: `DeliveryJob.php`, `DeliveryJobEvent.php`, `Device.php` ✱, `Order.php` ✱, `Payment.php`, `PaymentWebhookEvent.php`, `Report.php` ✱, `ReturnRequest.php`, `ReturnRequestPhoto.php`
- `app/Observers/`: `OrderDeliveryObserver.php`
- `app/Policies/`: `DeliveryJobPolicy.php`
- `app/Providers/`: `AppServiceProvider.php` ✱
- `app/Services/Delivery/`: `BaseDeliveryNotifier.php`, `DeliveryActionException.php`, `DeliveryJobService.php`, `DeliveryMessages.php`, `DeliveryNotifier.php`, `DeliveryPaymentService.php`, `DeliveryQuoter.php`, `DeliveryStateMachine.php`, `DeliveryUnavailableException.php`, `FcmDeliveryNotifier.php`, `FirebaseConnection.php`, `Haversine.php`, `LogDeliveryNotifier.php`
- `app/Services/Delivery/Bolt/`: `BoltApiException.php`, `BoltClient.php`, `BoltEstimate.php`, `BoltReceipt.php`, `BoltRideBooker.php`, `BoltRideDetails.php`, `BoltStates.php`
- `app/Services/Payment/`: `CollectionRequest.php`, `CollectionResult.php`, `CollectionStatus.php`, `FakePaymentGateway.php`, `GatewayCapabilities.php`, `PaymentGateway.php` ✱, `PaymentUnavailableException.php`, `UnavailablePaymentGateway.php`
- `app/Services/Returns/`: `ReturnActionException.php`, `ReturnMessages.php`, `ReturnService.php`
- `app/Support/`: `DeliveryAccess.php`, `DeliverySettings.php`, `Legal.php` ✱, `PrivateFileUrl.php`, `ReturnPolicy.php`
- `bootstrap/`: `app.php` ✱
- `config/`: `delivery.php`, `payments.php`, `sokoni.php` ✱
- `database/seeders/`: `DemoSeeder.php` ✱ (never run on production; included so the folder matches the branch)
- `lang/en/`: `delivery.php`, `returns.php`; `lang/sw/`: `delivery.php`, `returns.php`
- `resources/views/web/pages/`: `terms.blade.php` ✱
- `routes/`: `api.php` ✱, `api_delivery.php`, `api_returns.php`, `console.php` ✱

**Removed by this release** (step 5): `app/Services/Payment/PaymentResult.php`, `app/Services/Payment/UnimplementedPaymentGateway.php`, `public/setup.php`.

The website (`sokoni.co.tz`) runs from this same folder through `public_html/index.php`, so nothing goes into `public_html`. No CSS rebuild is needed: the Terms clause, when it is switched on, uses only styles the site already has.

**`rollback-code.zip`:** production's (`d7b30d4`) version of the 21 changed files marked ✱ above, plus `PaymentResult.php` and `UnimplementedPaymentGateway.php`.

## Appendix B: undo the migrations by hand

Use this in phpMyAdmin → SQL only when step 4 failed part-way (before a retry), or for 9.2 when the cron rollback can't run. Paste it all at once. Every line is safe to run even if that table or column was never made, so it also cleans up a half-finished step 4.

```sql
DROP TABLE IF EXISTS `return_request_photos`;
DROP TABLE IF EXISTS `payment_webhook_events`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `return_requests`;
DROP TABLE IF EXISTS `delivery_ratings`;
DROP TABLE IF EXISTS `driver_locations`;
DROP TABLE IF EXISTS `delivery_offers`;
DROP TABLE IF EXISTS `delivery_job_events`;
DROP TABLE IF EXISTS `delivery_jobs`;
DROP TABLE IF EXISTS `driver_payouts`;
DROP TABLE IF EXISTS `vehicles`;
DROP TABLE IF EXISTS `driver_profiles`;
ALTER TABLE `users` DROP COLUMN IF EXISTS `returns_terms_version`, DROP COLUMN IF EXISTS `returns_terms_accepted_at`;
ALTER TABLE `seller_profiles` DROP COLUMN IF EXISTS `return_policy`, DROP COLUMN IF EXISTS `return_notes`;
ALTER TABLE `orders` DROP COLUMN IF EXISTS `return_policy`, DROP COLUMN IF EXISTS `delivery_phone`, DROP COLUMN IF EXISTS `fulfilment`;
ALTER TABLE `devices` DROP COLUMN IF EXISTS `app`;
DELETE FROM `migrations` WHERE `migration` IN (
  '2026_10_29_090000_create_driver_profiles_table', '2026_10_29_090100_create_driver_payouts_table',
  '2026_10_29_090200_create_delivery_jobs_table', '2026_10_29_090300_create_driver_locations_table',
  '2026_10_29_090400_create_payments_tables', '2026_10_29_090500_create_delivery_ratings_table',
  '2026_10_29_090600_add_delivery_columns_to_existing_tables', '2026_11_04_090000_add_delivery_phone_to_orders_table',
  '2026_11_17_100000_add_bolt_columns_to_delivery_jobs_table', '2026_11_17_100100_add_return_policy_to_seller_profiles_table',
  '2026_11_17_100200_add_return_policy_to_orders_table', '2026_11_17_100300_create_return_requests_table',
  '2026_11_17_100400_add_return_request_id_to_payments_table', '2026_11_17_100500_add_returns_terms_to_users_table');
```

The tables go children first, so no foreign key blocks a drop. `DROP COLUMN IF EXISTS` needs MariaDB (step 2.3). If a statement still errors, stop, note which one, and send it to your developer.

There is no hand-written SQL for running the migrations: if the cron job can't run PHP at all, stop at step 4. Nothing has changed for anyone at that point.

## Appendix C: how the zips were made

From the committed branch only (`git archive`), on the Windows PC, in Git Bash:

```bash
cd /d/Projects/sokoni-bolt
C=5fa4c801a34a578f484f10bc241813ffcef62f41   # feature/bolt-delivery, code
P=d7b30d44e1449ad5bc8a020b1b399adbc0e571eb   # production (fix/ugc-moderation)
git diff --name-status $P..$C -- api/ | grep -v -E 'api/tests/|api/database/factories/|api/\.env\.example$' | sed 's#\tapi/#\t#' > all.txt
grep -P '^[AM]\tdatabase/migrations/' all.txt | cut -f2 > step1.txt
grep -P '^[AM]\t' all.txt | cut -f2 | grep -v '^database/migrations/' > step2.txt
grep -P '^D\t' all.txt | cut -f2 > deleted.txt
(cat step2.txt deleted.txt) | grep -v '^public/setup.php$' | while read f; do git cat-file -e $P:api/$f 2>/dev/null && echo $f; done > rollback.txt
git archive --format=zip -o 1-migrations.zip $C:api $(cat step1.txt)
git archive --format=zip -o 2-code.zip --add-file=RELEASE-bolt-dark.txt $C:api $(cat step2.txt)
git archive --format=zip -o rollback-code.zip $P:api $(cat rollback.txt)
```

Every file in `2-code.zip` and `rollback-code.zip` was compared byte for byte with the commit it came from. If the code changes after `5fa4c80`, rebuild the zips from the new commit and update the commit lines in this file.
