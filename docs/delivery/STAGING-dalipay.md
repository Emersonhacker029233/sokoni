# Staging: Dalipay test mode on staging-api.sokoni.co.tz (checklist)

This brings **staging** up to `feature/dalipay` so Dalipay can be tested with **test keys**. It is for `staging-api.sokoni.co.tz` (`/home/sokoftsn/staging-api`, `APP_ENV=staging`) only. **Never run any of it on production** (`api.sokoni.co.tz`, `/home/sokoftsn/sokoni-api`). Bolt stays switched off. Follow the steps in order and stop wherever a step says **STOP**.

- **Branch:** `feature/dalipay`: production's code (the 1.0 (9) moderation release and the Bolt dark release) plus live tracking, the flat delivery fee, the Android 1.1 server parts and Dalipay.
- **Code commit in the zips:** `0d737707d48b81085be0ee3874a574bd2fcb0aa3`
- **Staging base it was built against:** `1d99196721a748cf41780d98a62e2f5f4f143cbd` (`feature/delivery`, the last staging packages; see step 2.2)
- **Zips:** `D:\Projects\sokoni-release-staging-dalipay\` on the Windows PC:
  - `1-migrations.zip`: the 11 database migrations staging hasn't run
  - `2-code.zip`: the 157 new or changed code files, plus `BUILD.txt` and `STAGING-DELETE.txt`
  - `rollback-code.zip`: staging's version of the 136 files this update changes or deletes, plus `BUILD.txt` and `ROLLBACK-DELETE.txt`
  - `staging-dalipay-test.ps1`: the test script (step 9), the same file as `tools/staging-dalipay-test.ps1` in the commit
- **Tools:** cPanel File Manager, phpMyAdmin, cPanel Cron Jobs, and PowerShell on your PC. No SSH needed.
- **Time:** about 45 minutes, most of it waiting for cron jobs.

**Why so many files.** Staging is still on the old `feature/delivery` line (Sokoni Driver). This update brings the moderation release, Bolt, returns, live tracking and Dalipay at once, and removes the 44 Sokoni Driver files, which would otherwise break the admin panel.

**Why two uploads.** The new code reads new database columns, so it must not go live before the migrations have run. The old code doesn't care about the new tables and columns. So: upload the migrations, run them, check, and only then upload the code.

**How Dalipay is tested with Bolt off.** With `BOLT_ENABLED=false` the app's Pay now routes answer 404, so the test runs through a staging-only endpoint, `/api/staging/payments` (Appendix C). It pays a test delivery fee through the real payment service, Dalipay gateway, webhook handler and cron. It exists only with `APP_ENV=staging` (or local) and the `STAGING_PAYMENTS_TEST_TOKEN`; on production it answers 404, and it refuses anything but Dalipay's test mode.

---

## 1. Back up

- [ ] **1.1 Database.** phpMyAdmin → click the **staging** database (the `DB_DATABASE` in `/home/sokoftsn/staging-api/.env`) → **Export** → **Custom** → tick **Add DROP TABLE / VIEW / PROCEDURE / FUNCTION / EVENT / TRIGGER statement** → Compression **gzipped** → **Export**. Keep the `.sql.gz` on your PC.
- [ ] **1.2 Code.** File Manager → **Settings** → tick **Show Hidden Files** → Save. Open `/home/sokoftsn/staging-api` → select `app`, `bootstrap`, `config`, `database`, `lang`, `resources`, `routes` and `BUILD.txt` (Ctrl-click) → **Compress** → Zip Archive → `backup-before-staging-dalipay.zip` → **Compress Files**. Right-click it → **Download**, then delete it from the server.
- [ ] **1.3 `.env`.** Right-click `/home/sokoftsn/staging-api/.env` → **Download**.

## 2. Pre-flight checks (nothing changes yet)

- [ ] **2.1 The staging database.** phpMyAdmin → the staging database → **SQL**:
  ```sql
  SELECT migration FROM migrations ORDER BY id DESC LIMIT 1;
  ```
  Expected: `2026_11_04_090000_add_delivery_phone_to_orders_table`.
  - `2026_11_17_100500_add_returns_terms_to_users_table`: the migrations already ran. Skip steps 3 and 4.
  - Anything else: **STOP.** Send the row to your developer.
- [ ] **2.2 The staging code.** File Manager → `/home/sokoftsn/staging-api/BUILD.txt` → right-click → **View**. The `commit:` line must be one of these (the API files are the same apart from `Order.php`, which this update replaces):
  ```
  1d99196721a748cf41780d98a62e2f5f4f143cbd
  fda91b80e315af537e3f7cc421d4bcad33bce2f8
  f30265d6898a9fe1dc752b5ad7ae27f91c0c829b
  ```
  Any other commit, or no `BUILD.txt`: **STOP** and send it to your developer.
- [ ] **2.3 Old code, no new code yet.** `/home/sokoftsn/staging-api/app/Filament/Resources/Drivers` exists, and `/home/sokoftsn/staging-api/app/Services/Payment/DalipayGateway.php` does **not**. If `DalipayGateway.php` exists, **STOP**.
- [ ] **2.4 Never production.** In `/home/sokoftsn/staging-api/.env` (right-click → **View**), `APP_ENV` must not be `production`, and the folder must be `staging-api`. Note the `DB_DATABASE` value: it must be the staging database, not production's.
- [ ] **2.5 PHP.** cPanel → **MultiPHP Manager**: `staging-api.sokoni.co.tz` is on **PHP 8.4** (8.3 also works).

## 3. Upload part 1: the 11 migration files

- [ ] File Manager → `/home/sokoftsn/staging-api` → **Upload** → `1-migrations.zip` → at 100%, **Go Back**.
- [ ] Right-click `1-migrations.zip` → **Extract** → path `/home/sokoftsn/staging-api` → **Extract Files**.
- [ ] `database/migrations` now has these 11 files:
  ```
  2026_11_04_100000_create_user_blocks_table.php
  2026_11_04_100100_add_is_hidden_to_moderated_content.php
  2026_11_04_100200_add_actor_id_to_app_notifications_table.php
  2026_11_04_100300_add_moderation_timestamps_to_reports_table.php
  2026_11_04_100400_create_notifications_table.php
  2026_11_17_100000_add_bolt_columns_to_delivery_jobs_table.php
  2026_11_17_100100_add_return_policy_to_seller_profiles_table.php
  2026_11_17_100200_add_return_policy_to_orders_table.php
  2026_11_17_100300_create_return_requests_table.php
  2026_11_17_100400_add_return_request_id_to_payments_table.php
  2026_11_17_100500_add_returns_terms_to_users_table.php
  ```
- [ ] Delete `1-migrations.zip` from the server.

## 4. Run the migrations with a one-time cron job

What they do, all additive: new tables `user_blocks`, `notifications`, `return_requests`, `return_request_photos`; new columns on `seller_profiles`, `messages`, `showcases` and `updates` (`is_hidden`), `app_notifications` (`actor_id`), `reports` (moderation timestamps), `delivery_jobs` (Bolt), `seller_profiles` and `orders` (return policy), `payments` (`return_request_id`), `users` (returns terms). No existing column changes and no row is changed or deleted. Each one checks what already exists and cleans up after a part-way failure. The same 11 ran on production.

- [ ] cPanel → **Cron Jobs** → *Add New Cron Job* → Common Settings: **Once Per Five Minutes (\*/5 \* \* \* \*)** → paste this into **Command**, as one line → **Add New Cron Job**:
  ```
  cd /home/sokoftsn/staging-api && [ ! -e storage/app/staging-dalipay-migrate.lock ] && touch storage/app/staging-dalipay-migrate.lock && PHP=$(ls /opt/cpanel/ea-php84/root/usr/bin/php /opt/alt/php84/usr/bin/php /usr/local/bin/php 2>/dev/null | head -n 1) && { "$PHP" -v | head -n 1; "$PHP" artisan migrate --force --no-interaction --path=database/migrations/2026_11_04_100000_create_user_blocks_table.php --path=database/migrations/2026_11_04_100100_add_is_hidden_to_moderated_content.php --path=database/migrations/2026_11_04_100200_add_actor_id_to_app_notifications_table.php --path=database/migrations/2026_11_04_100300_add_moderation_timestamps_to_reports_table.php --path=database/migrations/2026_11_04_100400_create_notifications_table.php --path=database/migrations/2026_11_17_100000_add_bolt_columns_to_delivery_jobs_table.php --path=database/migrations/2026_11_17_100100_add_return_policy_to_seller_profiles_table.php --path=database/migrations/2026_11_17_100200_add_return_policy_to_orders_table.php --path=database/migrations/2026_11_17_100300_create_return_requests_table.php --path=database/migrations/2026_11_17_100400_add_return_request_id_to_payments_table.php --path=database/migrations/2026_11_17_100500_add_returns_terms_to_users_table.php; echo "EXIT CODE: $?"; } > storage/logs/staging-dalipay-migrate.log 2>&1
  ```
  The command names the 11 files, so nothing else can run. The lock file makes it run exactly once, although the job fires every five minutes.
- [ ] Wait **6 minutes**. File Manager → `/home/sokoftsn/staging-api/storage/logs/staging-dalipay-migrate.log` → **View**:
  ```
  PHP 8.4.x (cli) ...
     INFO  Running migrations.
    2026_11_04_100000_create_user_blocks_table ......... DONE
    ... (11 lines in all, each DONE)
    2026_11_17_100500_add_returns_terms_to_users_table ......... DONE
  EXIT CODE: 0
  ```
  - **No log after 11 minutes:** check the job was saved with *Once Per Five Minutes*.
  - **The first line is empty or not PHP 8.2+:** delete `storage/app/staging-dalipay-migrate.lock`, edit the job to put the right PHP path first in the `ls` list (ask the host for the PHP 8.4 CLI path), and wait again.
  - **`EXIT CODE` isn't 0:** **STOP.** Delete the cron job and don't upload part 2. Staging still runs the old code. Send the log to your developer.
- [ ] **Delete the cron job:** cPanel → Cron Jobs → *Current Cron Jobs* → **Delete** on this line. Leave the lock file.
- [ ] phpMyAdmin:
  ```sql
  SELECT migration, batch FROM migrations WHERE migration LIKE '2026_11_04_10%' OR migration LIKE '2026_11_17_1%';
  ```
  Expected: 11 rows with the same `batch`.

## 5. Upload part 2: the code

- [ ] File Manager → `/home/sokoftsn/staging-api` → **Upload** `2-code.zip` → **Go Back** → right-click → **Extract** → path `/home/sokoftsn/staging-api` → **Extract Files** (it overwrites the old files; the list is in [Appendix A](#appendix-a-what-is-in-each-zip)).
- [ ] Open `/home/sokoftsn/staging-api/BUILD.txt`. It must say `commit: 0d737707d48b81085be0ee3874a574bd2fcb0aa3`. If it still shows the old commit, Extract kept the old files: extract again.
- [ ] Delete `2-code.zip` from the server.
- [ ] Never delete `.env`, `vendor/`, `storage/`, `public/uploads`, `public/.htaccess` (the admin directory password) or `public/.user.ini`. `vendor/` doesn't change (`composer.json` and `composer.lock` are the same as staging's), and nothing goes into `public/`.

The new code is live from this moment. The 44 Sokoni Driver files are removed in step 7; do steps 6 and 7 straight away.

## 6. `.env` lines for staging

- [ ] **Make a token for the test script.** In PowerShell on your PC:
  ```powershell
  -join ((48..57) + (65..90) + (97..122) | Get-Random -Count 40 | ForEach-Object { [char]$_ })
  ```
  Copy the 40 characters. You type it in `.env` below and give it to the test script in step 9.
- [ ] Right-click `/home/sokoftsn/staging-api/.env` → **Edit**. Set these, one of each, no spaces around `=`. Change a line that is already there instead of adding a second one:
  ```
  APP_ENV=staging
  APP_DEBUG=false

  # Payments: Dalipay TEST mode (docs/delivery/BOLT.md section 8)
  PAYMENT_PROVIDER=dalipay
  DALIPAY_ENV=test
  DALIPAY_BASE_URL=
  DALIPAY_PUBLIC_KEY=
  DALIPAY_SECRET_KEY=
  DALIPAY_WEBHOOK_SECRET=
  STAGING_PAYMENTS_TEST_TOKEN=<the 40 characters from above>

  # Bolt and returns: off for now
  BOLT_ENABLED=false
  BOLT_ENV=sandbox
  DELIVERY_RETURNS_ENABLED=false
  ```
  - **You fill in the four Dalipay values** yourself from Dalipay's dashboard (step 8): the base URL (the API host Dalipay gives you, without `/api/v1`; its documentation only shows a placeholder), the test public key (`gw_pk_test_…`), the test secret key (`gw_sk_test_…`) and the callback secret. Never paste them anywhere else.
  - **Leave `BOLT_CLIENT_ID` and `BOLT_CLIENT_SECRET` empty or unset**, so the Bolt cron jobs aren't scheduled.
  - **No longer read, safe to delete:** `DELIVERY_ENABLED`, `SOKONI_DELIVERY_FEE_CASH_ENABLED`, `PAYMENT_FAKE_APPROVE_AFTER_SECONDS`, and any `OPENROUTESERVICE_*` or `DRIVER_*` lines from the Sokoni Driver days. `PAYMENT_ENV` is informational.
  - With an empty key, Pay now and the test endpoint are simply off (the log says `[payments] Dalipay is not used: …`). Nothing else breaks.
- [ ] **Save Changes.**

## 7. Remove the Sokoni Driver files and clear the caches (one-time cron job)

`2-code.zip` brought `STAGING-DELETE.txt`: the 44 files this update deletes (Appendix A). Left on the server, the old admin Drivers screen would point at models that no longer exist and break the admin panel. This job deletes exactly those 44 files, the empty Drivers folders, and every cached file (routes, config, the Filament component cache, compiled pages), which Laravel rebuilds on the next request.

- [ ] cPanel → **Cron Jobs** → **Once Per Five Minutes (\*/5 \* \* \* \*)** → **Command**, as one line:
  ```
  cd /home/sokoftsn/staging-api && [ -f STAGING-DELETE.txt ] && [ ! -e storage/app/staging-dalipay-cleanup.lock ] && touch storage/app/staging-dalipay-cleanup.lock && { while IFS= read -r f; do case "$f" in ''|/*|*..*) continue;; esac; [ -e "$f" ] && rm -v -- "$f"; done < STAGING-DELETE.txt; find app/Filament/Resources/Drivers resources/views/filament/drivers -depth -type d -empty -delete 2>/dev/null; rm -rf bootstrap/cache/filament; find bootstrap/cache -maxdepth 1 -type f -name '*.php' -delete; find storage/framework/views -maxdepth 1 -type f -name '*.php' -delete; echo "DONE"; } > storage/logs/staging-dalipay-cleanup.log 2>&1
  ```
- [ ] Wait **6 minutes**. `storage/logs/staging-dalipay-cleanup.log` lists `removed '…'` lines (44 if staging had every file) and ends with `DONE`. `app/Filament/Resources/Drivers` is gone, and `bootstrap/cache` holds only `.gitignore`.
- [ ] **Delete the cron job.** Leave the lock file.
- [ ] **Whenever you edit `.env` again** (step 8), delete `bootstrap/cache/config.php` if it is there.

If cron can't be used, delete the files in [Appendix A](#appendix-a-what-is-in-each-zip) ("Deleted") by hand, then in `bootstrap/cache` everything except `.gitignore`, and in `storage/framework/views` every `.php` file.

## 8. Dalipay's dashboard (test keys)

- [ ] Sign in to Dalipay's merchant dashboard and open the **test** keys.
- [ ] **Settings → Webhook URL:**
  ```
  https://staging-api.sokoni.co.tz/api/payments/webhook/dalipay
  ```
  Save, then copy the **callback secret** shown next to it.
- [ ] **IP whitelist:** leave it empty for staging, or add the staging server's outgoing IP (ask the host; shared servers often send from a different address than the site's). With a wrong IP listed, every call gets 403 and the log says so.
- [ ] Put the four values into `/home/sokoftsn/staging-api/.env` (step 6): `DALIPAY_BASE_URL`, `DALIPAY_PUBLIC_KEY`, `DALIPAY_SECRET_KEY`, `DALIPAY_WEBHOOK_SECRET`. **Save Changes**, then delete `bootstrap/cache/config.php` if it is there.

## 9. Checks from PowerShell

### 9.1 Quick checks

Open **Windows PowerShell** on your PC and paste these blocks in order into the same window:

```powershell
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$api   = 'https://staging-api.sokoni.co.tz/api'
$plain = @{ Accept = 'application/json' }
function Status($scriptBlock) { try { & $scriptBlock | Out-Null; 200 } catch { [int]$_.Exception.Response.StatusCode } }
"features: $(Status { Invoke-RestMethod -Uri "$api/features" -Headers $plain })   (expect 200: the new code is live)"
"delivery config: $(Status { Invoke-RestMethod -Uri "$api/delivery/config" -Headers $plain })   (expect 404: Bolt is off)"
"staging test without token: $(Status { Invoke-RestMethod -Method Post -Uri "$api/staging/payments" -Headers $plain })   (expect 404)"
"Dalipay webhook, bad signature: $(Status { Invoke-RestMethod -Method Post -Uri "$api/payments/webhook/dalipay" -Headers ($plain + @{ 'X-Signature' = 'bad' }) -ContentType 'application/json' -Body '{}' })   (expect 401)"
"admin (directory password): $(Status { Invoke-WebRequest -Uri 'https://staging-api.sokoni.co.tz/admin/login' -UseBasicParsing })   (expect 401: the directory password still protects /admin)"
```

A `500` on any line: look at `storage/logs/laravel-*.log` (or `laravel.log`), then step 11 if it can't be fixed.

### 9.2 The Dalipay test script

On your PC, with your own test phone number on the network you name:

```powershell
powershell -ExecutionPolicy Bypass -File D:\Projects\sokoni-release-staging-dalipay\staging-dalipay-test.ps1 -Msisdn +2557XXXXXXXX -Channel mpesa
```

It asks for the staging token from step 6 (it never prints it), and refuses any address but staging. It runs:

| Check | What it does | Expected |
|---|---|---|
| Pre-flight | `/api/features`, Bolt routes, the webhook route with a bad signature, the test endpoint without the token | PASS on each. A webhook `WARN` "Webhook not configured" means `DALIPAY_WEBHOOK_SECRET` is empty |
| Test fee | A TSh 1,000 test delivery fee (`-Amount` changes it) pushed through Dalipay, polled until it ends | `PASS: paid`, with Dalipay's `col_…` reference |
| Dalipay webhook | Whether Dalipay's own webhook reached staging for that payment | `PASS` with the result. `WARN` means it didn't arrive: check the webhook URL in step 8 (the status checks confirm payments without it) |
| Declined | The same number sent with a different network | `PASS: failed`. A `WARN` means Dalipay's test mode approved it: its documentation names no test number that declines. Ask Dalipay for one and pass `-DeclineMsisdn +2557…` |
| Timeout | A push sent to an unreachable address, so no answer comes | stays pending, then `PASS: expired as timeout` after about 2 minutes (`-SkipTimeout` skips it) |
| Duplicate webhook | The same signed webhook delivered twice, signed on the server | `PASS: answered 200 and processed once` |

The script ends with a summary table and exits with code 1 if any check failed. Each run leaves test orders marked `STAGING PAYMENT TEST` from the shop "Staging payments test"; with test keys no real money moves.

### 9.3 Admin panel (browser)

- [ ] `https://staging-api.sokoni.co.tz/admin` → directory password → staging admin. The dashboard loads, and **Trust & Safety → Drivers** is gone.
- [ ] **Commerce → Bolt deliveries** lists the test deliveries (drop-off `STAGING PAYMENT TEST`). Open one: **Payments (mobile money)** shows the attempt with its Dalipay uuid, `col_…` reference, `external_id`, status, and the webhooks received with their results.
- [ ] **Trust & Safety → Reports**, **Trust & Safety → Returns** and **Commerce → Orders** open.

### 9.4 Logs

- [ ] `storage/logs/`: search `[payments]`. `Dalipay is not used: <reason>` means a key or the base URL is refused (the reason says which); it never shows a key. No `ERROR` should mention `Driver`, `Dispatch` or `LiveTracking`.

## 10. If a changed screen still looks old

Wait 5 minutes (PHP re-checks changed files on a timer). If it is still old: cPanel → **MultiPHP Manager** → tick only `staging-api.sokoni.co.tz` → switch to another PHP version → Apply → switch back to **PHP 8.4** → Apply.

## 11. Rollback

**When:** after step 5, staging breaks and steps 7 and 10 don't fix it.

### 11.1 Code

1. [ ] Upload `rollback-code.zip` → **Extract** into `/home/sokoftsn/staging-api` → delete the zip. This puts back staging's version (`1d99196`) of all 136 changed or deleted files, the Sokoni Driver files included, and a `BUILD.txt` saying so.
2. [ ] **Once Per Five Minutes** cron job, one line: it deletes the 65 files this update added (`ROLLBACK-DELETE.txt`) and the caches:
   ```
   cd /home/sokoftsn/staging-api && [ -f ROLLBACK-DELETE.txt ] && [ ! -e storage/app/staging-dalipay-rollback.lock ] && touch storage/app/staging-dalipay-rollback.lock && { while IFS= read -r f; do case "$f" in ''|/*|*..*) continue;; esac; [ -e "$f" ] && rm -v -- "$f"; done < ROLLBACK-DELETE.txt; for d in app/Filament/Resources/BoltDeliveries app/Filament/Resources/ReturnRequests app/Services/Delivery/Bolt app/Services/Payment/Dalipay app/Services/Returns app/Http/Controllers/Api/Payments; do find "$d" -depth -type d -empty -delete 2>/dev/null; done; rm -rf bootstrap/cache/filament; find bootstrap/cache -maxdepth 1 -type f -name '*.php' -delete; find storage/framework/views -maxdepth 1 -type f -name '*.php' -delete; echo "DONE"; } > storage/logs/staging-dalipay-rollback.log 2>&1
   ```
   Wait 6 minutes, check the log ends with `DONE`, delete the cron job.
3. [ ] Put back the `.env` from step 1.3 (or set `PAYMENT_PROVIDER=fake` again), then delete `bootstrap/cache/config.php` if it is there.
4. [ ] Check: `BUILD.txt` says `ROLLBACK`, and `https://staging-api.sokoni.co.tz/api/features` answers 404 (the old code has no such route).

### 11.2 Database: normally leave it

The old code works with the new tables and columns, because they are additive. To undo them anyway (only after 11.1, and only while these 11 are still the last batch), use a **Once Per Five Minutes** cron job:

```
cd /home/sokoftsn/staging-api && [ ! -e storage/app/staging-dalipay-migrate-rollback.lock ] && touch storage/app/staging-dalipay-migrate-rollback.lock && PHP=$(ls /opt/cpanel/ea-php84/root/usr/bin/php /opt/alt/php84/usr/bin/php /usr/local/bin/php 2>/dev/null | head -n 1) && { "$PHP" artisan migrate:rollback --force --no-interaction --path=database/migrations/2026_11_04_100000_create_user_blocks_table.php --path=database/migrations/2026_11_04_100100_add_is_hidden_to_moderated_content.php --path=database/migrations/2026_11_04_100200_add_actor_id_to_app_notifications_table.php --path=database/migrations/2026_11_04_100300_add_moderation_timestamps_to_reports_table.php --path=database/migrations/2026_11_04_100400_create_notifications_table.php --path=database/migrations/2026_11_17_100000_add_bolt_columns_to_delivery_jobs_table.php --path=database/migrations/2026_11_17_100100_add_return_policy_to_seller_profiles_table.php --path=database/migrations/2026_11_17_100200_add_return_policy_to_orders_table.php --path=database/migrations/2026_11_17_100300_create_return_requests_table.php --path=database/migrations/2026_11_17_100400_add_return_request_id_to_payments_table.php --path=database/migrations/2026_11_17_100500_add_returns_terms_to_users_table.php; echo "EXIT CODE: $?"; } > storage/logs/staging-dalipay-migrate-rollback.log 2>&1
```

The log should list the 11, newest first, each `DONE`, then `EXIT CODE: 0`. Delete the cron job afterwards.

### 11.3 Last resort

phpMyAdmin → **Import** the `.sql.gz` from step 1.1. This throws away everything done on staging since the backup.

---

## Appendix A: what is in each zip

All paths are relative to `/home/sokoftsn/staging-api`.

**`1-migrations.zip`:** the 11 files in `database/migrations/` listed in step 3.

**`2-code.zip`** has 157 files plus `BUILD.txt` and `STAGING-DELETE.txt`: every runtime file added or changed since staging's code (`1d99196`). Tests, factories and `.env.example` are left out. ✱ = changed, the rest are new.

- `app/Console/Commands/`: BoltSandbox.php, DeliveryTick.php, DeliveryTrack.php, PaymentsReconcile.php, SendWaitingReportsSummary.php
- `app/Filament/Resources/BoltDeliveries/`: BoltDeliveryResource.php
- `app/Filament/Resources/BoltDeliveries/Pages/`: ListBoltDeliveries.php, ViewBoltDelivery.php
- `app/Filament/Resources/BoltDeliveries/Schemas/`: BoltDeliveryInfolist.php
- `app/Filament/Resources/BoltDeliveries/Tables/`: BoltDeliveriesTable.php
- `app/Filament/Resources/Reports/Pages/`: ViewReport.php ✱
- `app/Filament/Resources/Reports/`: ReportActions.php, ReportResource.php ✱
- `app/Filament/Resources/Reports/Schemas/`: ReportInfolist.php ✱
- `app/Filament/Resources/Reports/Tables/`: ReportsTable.php ✱
- `app/Filament/Resources/ReturnRequests/Pages/`: ListReturnRequests.php, ViewReturnRequest.php
- `app/Filament/Resources/ReturnRequests/`: ReturnRequestResource.php
- `app/Filament/Resources/ReturnRequests/Schemas/`: ReturnRequestInfolist.php
- `app/Filament/Resources/ReturnRequests/Tables/`: ReturnRequestsTable.php
- `app/Filament/Support/`: LivePosition.php, PaymentAttempts.php
- `app/Filament/Widgets/`: ModerationQueueTable.php ✱, SokoniStatsOverview.php ✱
- `app/Http/Controllers/Api/`: AuthController.php ✱, BlockController.php, CommentController.php ✱, ConversationController.php ✱, FeatureController.php, FeedController.php ✱, MessageController.php ✱, NotificationController.php ✱, OfferController.php ✱, OrderController.php ✱, PasswordAuthController.php ✱, ProductController.php ✱, ReportController.php ✱, ReturnController.php, ReviewController.php ✱, SellerProfileController.php ✱, ShowcaseController.php ✱, UpdateController.php ✱
- `app/Http/Controllers/Api/Delivery/`: BoltWebhookController.php, CustomerDeliveryController.php ✱
- `app/Http/Controllers/Api/Payments/`: DalipayWebhookController.php, StagingPaymentTestController.php
- `app/Http/Controllers/Web/Account/`: ReportController.php ✱
- `app/Http/Controllers/Web/Auth/`: AppleSignInCallbackController.php
- `app/Http/Controllers/Web/`: HomeController.php ✱, ProductController.php ✱, SearchController.php ✱, SeoController.php ✱, ShopController.php ✱, StoresController.php ✱
- `app/Http/Middleware/`: EnsureDeliveryAccess.php, EnsureDeliveryEnabled.php ✱, EnsureStagingPaymentsTest.php
- `app/Http/Requests/`: CheckoutRequest.php ✱, StoreBlockRequest.php, StoreReportRequest.php ✱
- `app/Http/Requests/Delivery/`: PayDeliveryRequest.php ✱
- `app/Http/Requests/Returns/`: StoreReturnRequest.php
- `app/Http/Resources/`: BlockedUserResource.php, ConversationResource.php ✱, OrderResource.php ✱, ProductResource.php ✱, PublicUserResource.php, ReviewResource.php ✱, SellerProfileResource.php ✱, UserResource.php ✱
- `app/Http/Resources/Delivery/`: DeliveryJobResource.php ✱
- `app/Http/Resources/Returns/`: ReturnRequestResource.php
- `app/Models/`: AppNotification.php ✱, Comment.php ✱, Conversation.php ✱, DeliveryJob.php ✱, DeliveryJobEvent.php ✱, Message.php ✱, Offer.php ✱, Order.php ✱, Payment.php ✱, Product.php ✱, Report.php ✱, ReturnRequest.php, ReturnRequestPhoto.php, Review.php ✱, SellerProfile.php ✱, Showcase.php ✱, Update.php ✱, User.php ✱, UserBlock.php
- `app/Models/Concerns/`: HidesBlockedAuthors.php
- `app/Notifications/`: ModerationTime.php, NewReportNotification.php, WaitingReportsSummaryNotification.php
- `app/Observers/`: OrderDeliveryObserver.php ✱, ReportObserver.php ✱, ReviewObserver.php ✱
- `app/Policies/`: DeliveryJobPolicy.php ✱
- `app/Providers/`: AppServiceProvider.php ✱
- `app/Providers/Filament/`: AdminPanelProvider.php ✱
- `app/Services/Catalog/`: ProductSearchFilters.php ✱, ProductSearchService.php ✱
- `app/Services/Delivery/`: BaseDeliveryNotifier.php ✱, DeliveryJobService.php ✱, DeliveryMessages.php ✱, DeliveryNotifier.php ✱, DeliveryPaymentService.php ✱, DeliveryQuoter.php, DeliveryStateMachine.php ✱, DriverLocationService.php, FcmDeliveryNotifier.php ✱, FirebaseConnection.php ✱, LogDeliveryNotifier.php ✱
- `app/Services/Delivery/Bolt/`: BoltApiException.php, BoltClient.php, BoltDriverLocation.php, BoltEstimate.php, BoltReceipt.php, BoltRideBooker.php, BoltRideDetails.php, BoltStates.php
- `app/Services/Feed/`: FeedService.php ✱
- `app/Services/Moderation/`: BlockList.php, ModerationAlerts.php
- `app/Services/Payment/`: CollectionResult.php ✱, CollectionStatus.php ✱, DalipayGateway.php, PaymentGatewayFactory.php
- `app/Services/Payment/Dalipay/`: DalipayConfig.php, DalipayException.php, DalipayWebhookHandler.php
- `app/Services/Push/`: LogPushNotifier.php ✱, PushNotifier.php ✱
- `app/Services/Returns/`: ReturnActionException.php, ReturnMessages.php, ReturnService.php
- `app/Support/`: DeliveryAccess.php, DeliverySettings.php ✱, Legal.php ✱, ReturnPolicy.php, TermsAcceptance.php
- `bootstrap/`: app.php ✱
- `config/`: delivery.php ✱, payments.php ✱, services.php ✱, sokoni.php ✱
- `lang/en/`: delivery.php ✱, returns.php
- `lang/sw/`: delivery.php ✱, returns.php
- `resources/views/web/pages/`: terms.blade.php ✱
- `routes/`: api.php ✱, api_delivery.php ✱, api_returns.php, console.php ✱, web.php ✱

**Deleted by this update** (step 7, from `STAGING-DELETE.txt`): the Sokoni Driver code.

- `app/Console/Commands/`: DeliveryDispatchTick.php, PurgeDriverLocations.php
- `app/Filament/Resources/Drivers/`: DriverResource.php
- `app/Filament/Resources/Drivers/Pages/`: ListDrivers.php, ViewDriver.php
- `app/Filament/Resources/Drivers/Schemas/`: DriverInfolist.php
- `app/Filament/Resources/Drivers/Tables/`: DriversTable.php
- `app/Http/Controllers/Admin/`: DriverDocumentController.php
- `app/Http/Controllers/Api/Delivery/`: DriverAvailabilityController.php, DriverJobController.php, DriverOfferController.php, DriverProfileController.php
- `app/Http/Middleware/`: EnsureApprovedDriver.php
- `app/Http/Requests/Delivery/`: DriverDocumentsRequest.php, DriverJobStatusRequest.php, DriverLocationRequest.php, RateDeliveryRequest.php, StoreDriverProfileRequest.php, UpsertVehicleRequest.php
- `app/Http/Resources/Delivery/`: DeliveryOfferResource.php, DriverProfileResource.php
- `app/Models/`: DeliveryOffer.php, DeliveryRating.php, DriverLocation.php, DriverPayout.php, DriverProfile.php, Vehicle.php
- `app/Policies/`: DriverProfilePolicy.php
- `app/Services/Delivery/`: DeliveryQuote.php, DispatchService.php, DistanceResult.php, DriverAccountMessages.php, DriverEarnings.php, DriverReviewException.php, DriverReviewService.php, FeeCalculator.php, FirebaseLiveTracking.php, LiveTracking.php, NullLiveTracking.php, OpenRouteServiceDistance.php, RouteDistance.php, StraightLineDistance.php
- `app/Support/`: DriverDocuments.php
- `resources/views/filament/drivers/`: documents.blade.php

**`rollback-code.zip`:** staging's (`1d99196`) version of the 92 changed files marked ✱ above and of the 44 deleted ones, plus `BUILD.txt` and `ROLLBACK-DELETE.txt` (the 65 new files, removed by 11.1).

## Appendix B: how the zips were made

From the committed branches only (`git archive`), on the Windows PC, in Git Bash:

```bash
cd /d/Projects/sokoni-bolt-live
C=0d737707d48b81085be0ee3874a574bd2fcb0aa3   # feature/dalipay, code
P=1d99196721a748cf41780d98a62e2f5f4f143cbd   # staging (feature/delivery)
git diff --name-status --no-renames $P..$C -- api/ | grep -v -E 'api/tests/|api/database/factories/|api/\.env\.example$' | sed 's#\tapi/#\t#' > all.txt
grep -P '^[AM]\tdatabase/migrations/' all.txt | cut -f2 > migrations.txt
grep -P '^[AM]\t' all.txt | cut -f2 | grep -v '^database/migrations/' > code.txt
grep -P '^D\t' all.txt | cut -f2 > STAGING-DELETE.txt
grep -P '^A\t' all.txt | cut -f2 | grep -v '^database/migrations/' > ROLLBACK-DELETE.txt
(cat code.txt STAGING-DELETE.txt) | while read f; do git cat-file -e $P:api/$f 2>/dev/null && echo $f; done > rollback.txt
git archive --format=zip -o 1-migrations.zip $C:api $(cat migrations.txt)
git archive --format=zip -o 2-code.zip --add-file=BUILD.txt --add-file=STAGING-DELETE.txt $C:api $(cat code.txt)
git archive --format=zip -o rollback-code.zip --add-file=rollback/BUILD.txt --add-file=ROLLBACK-DELETE.txt $P:api $(cat rollback.txt)
git show $C:tools/staging-dalipay-test.ps1 > staging-dalipay-test.ps1
```

No migration that staging already ran has changed since `1d99196`, and `composer.json` / `composer.lock` are unchanged, so `vendor/` stays as it is.

**Byte-for-byte check.** Each zip was extracted into an empty folder and every file compared with the blob in the commit it came from (`git hash-object --no-filters` against `git rev-parse <commit>:api/<file>`, then `cmp`), and the extracted file list compared with the expected list:

```
1-migrations.zip: 11 identical to 0d73770, 0 different; extra files: none; file list exactly as expected: yes
2-code.zip: 157 identical to 0d73770, 0 different; extra files: BUILD.txt STAGING-DELETE.txt; file list exactly as expected: yes
rollback-code.zip: 136 identical to 1d99196, 0 different; extra files: BUILD.txt ROLLBACK-DELETE.txt; file list exactly as expected: yes
staging-dalipay-test.ps1: identical to 0d73770:tools/staging-dalipay-test.ps1
```

The script was also run end to end against a local copy of the API with a stand-in that follows Dalipay's documented API: every check passed (the webhook check warns locally, since the stand-in sends no webhook). If the code changes after `0d73770`, rebuild the zips from the new commit and update the commit lines in this file.

## Appendix C: the staging test endpoint

`StagingPaymentTestController`, behind `EnsureStagingPaymentsTest`. Every request needs `X-Staging-Token: <STAGING_PAYMENTS_TEST_TOKEN>`; the token must be at least 24 characters and `APP_ENV` local or staging, otherwise every route answers 404. It refuses (409 `not_dalipay_test`) unless `PAYMENT_PROVIDER=dalipay` with `DALIPAY_ENV=test` and valid test keys.

| Route | |
|---|---|
| `POST /api/staging/payments` | `{ msisdn: "+2557…", channel, amount: 100–10000, mode: "normal" \| "no_answer" }`. Makes a test order and delivery job waiting for payment, and starts Pay now. `no_answer` sends the push to `192.0.2.1` (unreachable) to test the timeout. 201 with the payment |
| `GET /api/staging/payments/{id}` | Asks Dalipay about the payment, as the app's polling does, and returns it with its webhooks |
| `POST /api/staging/payments/{id}/replay-webhook` | Signs Dalipay's webhook for a finished payment with `DALIPAY_WEBHOOK_SECRET` on the server and delivers it twice to the real handler; the second must be a duplicate |

The payment answer: `payment_id, status, failure_reason, amount, channel, mode, dalipay_uuid, dalipay_reference, dalipay_status, external_id, late_after, fee_paid, order_code, created_at, webhooks[]`. Throttled to 30 requests a minute. Nothing is booked on Bolt: the test order never becomes ready.
