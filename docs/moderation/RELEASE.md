# Release 1.0 (9): production checklist (Apple Guideline 1.2)

This puts the moderation fix live on `api.sokoni.co.tz` and `sokoni.co.tz`, then Oswald builds iOS 1.0 (9) on the Mac. There is no staging run, so follow the steps in order and stop wherever a step says **STOP**.

- **Branch:** `fix/ugc-moderation`. It is `main` plus the moderation work only: no delivery code, and Google and Apple sign-in stay hidden on iOS exactly as in build 7.
- **Code commit in the zips:** `d7b30d44e1449ad5bc8a020b1b399adbc0e571eb`
- **Zips:** `D:\Projects\sokoni-release-1.0.9\` on the Windows PC:
  - `1-migrations.zip`: the 5 new database migration files
  - `2-code.zip`: the 74 changed code files, plus `RELEASE-1.0.9.txt`
  - `rollback-code.zip`: the previous version of the files `2-code.zip` overwrites
- **Tools:** cPanel File Manager, phpMyAdmin, cPanel Cron Jobs, and PowerShell on your PC for the checks. No SSH needed.
- **Time:** about 45 minutes. Pick a quiet hour. Nothing should go down: the order below never runs new code against an old database.

**Why two uploads.** The new code reads new database columns, so it must not go live before the migrations have run. The old code doesn't care about the new columns. So: upload the migrations, run them, check, and only then upload the code.

**Safe for the apps already in the stores.** iOS build 7 and Android 1.0.0+8 keep working. No response loses a field they need: public reviews drop the reviewer's phone, email and username, and those fields are optional in those builds. They now also stop seeing content from banned users and from hidden shops, messages and posts.

---

## 1. (a) Back up

- [ ] **1.1 Database.** cPanel → phpMyAdmin → click the Sokoni database in the left column → **Export** → Export method **Custom** → under *Object creation options* tick **Add DROP TABLE / VIEW / PROCEDURE / FUNCTION / EVENT / TRIGGER statement** → Compression **gzipped** → **Export**. Keep the downloaded `.sql.gz` file on your PC.
- [ ] **1.2 Code.** File Manager → `/home/sokoftsn/sokoni-api` → select the folders `app`, `config`, `database`, `resources` and `routes` (Ctrl-click) → **Compress** → Zip Archive → name it `backup-before-1.0.9.zip` → **Compress Files**. Right-click the zip → **Download**. Then delete the zip from the server.
- [ ] **1.3 `.env`.** In the same folder, right-click `.env` → **Download**. (Turn on *Settings → Show Hidden Files* if you can't see it.)

## 2. Pre-flight checks (nothing changes yet)

Run these in phpMyAdmin: select the Sokoni database → **SQL** tab → paste → **Go**.

- [ ] **2.1 The database is where this release expects it.**
  ```sql
  SELECT migration, batch FROM migrations ORDER BY id DESC LIMIT 3;
  ```
  The top row must be `2026_10_06_014500_create_trusted_devices_table`.
  - Top rows start with `2026_11_04_`: the migrations already ran. Skip steps 3 and 4.
  - Anything else, such as `2026_10_29_..._driver_...` or `..._delivery_...`: **STOP**. Production is not on `main`.
- [ ] **2.2 Database server and table type.**
  ```sql
  SELECT @@version AS server, @@default_storage_engine AS default_engine;
  SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('users','app_notifications','messages','seller_profiles','showcases','updates','reports');
  ```
  Expected: a `...-MariaDB` server, and every row `InnoDB`. If any row says `MyISAM`, **STOP**: the new foreign keys need InnoDB.
- [ ] **2.3 The App Review account.**
  ```sql
  SELECT id, phone, username, two_factor_enabled, terms_version, banned_at,
         password IS NOT NULL AS has_password
  FROM users WHERE phone = '+255783029233';
  ```
  Expected: exactly one row, `username` = `appreview`, `banned_at` = NULL, `has_password` = 1.
  - `banned_at` filled in: **STOP**.
  - `username` empty: run the query below, then repeat 2.3.
    ```sql
    UPDATE users SET username = 'appreview'
    WHERE phone = '+255783029233'
      AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM users WHERE username = 'appreview') AS taken);
    ```

In File Manager:

- [ ] **2.4 The code on the server is `main`.**
  - `/home/sokoftsn/sokoni-api/app/Http/Controllers/Api/PasswordAuthController.php` must exist.
  - `/home/sokoftsn/sokoni-api/app/Http/Controllers/Api/Delivery` must **not** exist. If it does, **STOP**: `2-code.zip` would remove the delivery routes.
- [ ] **2.5 The review account lines in `.env`.** Right-click `/home/sokoftsn/sokoni-api/.env` → **View**. Look for these three lines:
  ```
  REVIEW_ACCOUNT_PHONE=+255783029233
  REVIEW_ACCOUNT_PASSWORD=Review2026
  REVIEW_ACCOUNT_CODE=123456
  ```
  Note any that are missing or different. You fix them in step 6. Also check that `MAIL_MAILER=smtp`.

## 3. (b) Upload part 1: the five migration files

- [ ] File Manager → open `/home/sokoftsn/sokoni-api` → **Upload** → choose `1-migrations.zip` → when it reaches 100%, click **Go Back**.
- [ ] Right-click `1-migrations.zip` → **Extract** → the path must be `/home/sokoftsn/sokoni-api` → **Extract Files**.
- [ ] Open `database/migrations`. These five files are now there:
  ```
  2026_11_04_100000_create_user_blocks_table.php
  2026_11_04_100100_add_is_hidden_to_moderated_content.php
  2026_11_04_100200_add_actor_id_to_app_notifications_table.php
  2026_11_04_100300_add_moderation_timestamps_to_reports_table.php
  2026_11_04_100400_create_notifications_table.php
  ```
- [ ] Delete `1-migrations.zip` from `/home/sokoftsn/sokoni-api`.

Nothing has changed for users yet. These files only do something when step 4 runs them.

## 4. (c) Run the migrations with a one-time cron job

What they do, all additive: a new `user_blocks` table, an `is_hidden` column (default 0, so everything stays visible) on `seller_profiles`, `messages`, `showcases` and `updates`, an empty `actor_id` column on `app_notifications`, two empty date columns on `reports`, and a new `notifications` table for the admin bell. No existing column changes, no row is deleted, and every index is short (the longest is 772 bytes, under every MySQL and MariaDB limit). The command names the five files, so nothing else can run by accident. If one fails part-way, it removes its own half-made table or column, so a retry starts clean.

- [ ] cPanel → **Cron Jobs** → *Add New Cron Job* → Common Settings: **Once Per Minute (\* \* \* \* \*)** → paste this into **Command**, as one line → **Add New Cron Job**:
  ```
  cd /home/sokoftsn/sokoni-api && [ ! -e storage/app/release-1.0.9-migrate.lock ] && touch storage/app/release-1.0.9-migrate.lock && { /usr/local/bin/php -v | head -n 1; /usr/local/bin/php artisan migrate --force --no-interaction --path=database/migrations/2026_11_04_100000_create_user_blocks_table.php --path=database/migrations/2026_11_04_100100_add_is_hidden_to_moderated_content.php --path=database/migrations/2026_11_04_100200_add_actor_id_to_app_notifications_table.php --path=database/migrations/2026_11_04_100300_add_moderation_timestamps_to_reports_table.php --path=database/migrations/2026_11_04_100400_create_notifications_table.php; echo "EXIT CODE: $?"; } > storage/logs/release-1.0.9-migrate.log 2>&1
  ```
  The lock file makes it run exactly once, even though the job fires every minute.
- [ ] Wait 2 minutes. File Manager → `/home/sokoftsn/sokoni-api/storage/logs/release-1.0.9-migrate.log` → right-click → **View**. You should see:
  ```
  PHP 8.3.x (cli) ...
     INFO  Running migrations.
    2026_11_04_100000_create_user_blocks_table ......... DONE
    2026_11_04_100100_add_is_hidden_to_moderated_content ......... DONE
    2026_11_04_100200_add_actor_id_to_app_notifications_table ......... DONE
    2026_11_04_100300_add_moderation_timestamps_to_reports_table ......... DONE
    2026_11_04_100400_create_notifications_table ......... DONE
  EXIT CODE: 0
  ```
  - **The log doesn't exist after 3 minutes:** check the cron job was saved with *Once Per Minute*.
  - **The first line isn't PHP 8.2 or newer:** edit the cron job and replace both `/usr/local/bin/php` with `/opt/alt/php83/usr/bin/php`. Delete `storage/app/release-1.0.9-migrate.lock`, then wait 2 minutes again.
  - **`EXIT CODE` isn't 0:** **STOP.** Delete the cron job and don't upload part 2. The site is still on the old code and still works. Send the log to your developer.
- [ ] **Delete the cron job:** cPanel → Cron Jobs → *Current Cron Jobs* → **Delete** on this line. Leave the lock file where it is.
- [ ] Confirm in phpMyAdmin:
  ```sql
  SELECT migration, batch FROM migrations WHERE migration LIKE '2026_11_04_%';
  ```
  Expected: 5 rows with the same `batch` number.

The live apps keep working at this point: the old code ignores the new tables and columns.

## 5. (b) Upload part 2: the code

- [ ] File Manager → `/home/sokoftsn/sokoni-api` → **Upload** `2-code.zip` → **Go Back** → right-click → **Extract** → path `/home/sokoftsn/sokoni-api` → **Extract Files**. It overwrites the old files. The full list is in [Appendix A](#appendix-a-what-is-in-each-zip).
- [ ] Open `/home/sokoftsn/sokoni-api/RELEASE-1.0.9.txt`. It must say `commit: d7b30d44e1449ad5bc8a020b1b399adbc0e571eb`.
- [ ] Delete `2-code.zip` from the server.
- [ ] Never delete `.env`, `vendor/`, `storage/` or anything in `public_html/uploads`. `vendor/` doesn't change in this release.

The new code is live from this moment. Do steps 6 and 7 straight away.

## 6. (d) `.env` lines

Right-click `/home/sokoftsn/sokoni-api/.env` → **Edit**. Add at the end, with no spaces around `=`:

```
MODERATION_ALERT_EMAIL=support@sokoni.co.tz
```

- Replace `support@sokoni.co.tz` with the inbox a person actually reads every day, and make sure that mailbox exists. For more than one, separate with commas: `MODERATION_ALERT_EMAIL=a@sokoni.co.tz,b@sokoni.co.tz`.
- Every new report and every block is emailed there at once, plus a daily 08:00 summary of anything still waiting after 20 hours. If the line is missing, alerts go to `SOKONI_SUPPORT_EMAIL`.

If step 2.5 found any of these missing or different, set them exactly like this:

```
REVIEW_ACCOUNT_PHONE=+255783029233
REVIEW_ACCOUNT_PASSWORD=Review2026
REVIEW_ACCOUNT_CODE=123456
```

**Save Changes.** No other configuration file needs editing.

## 7. Clear the cached files

Laravel may be serving cached copies of the old routes, settings and pages. In File Manager:

- [ ] `/home/sokoftsn/sokoni-api/bootstrap/cache/`: delete these **if they are there**: `config.php`, `routes-v7.php`, `events.php` and the folder `filament`.
  - **Do not** delete `packages.php`, `services.php` or `.gitignore`.
- [ ] `/home/sokoftsn/sokoni-api/storage/framework/views/`: **Select All**, Ctrl-click `.gitignore` to leave it out → **Delete**. These are compiled page templates and they rebuild themselves on the next visit.

Why: a cached `routes-v7.php` would hide the new `/api/blocks` endpoint, a cached `config.php` would ignore the `.env` line from step 6, a `filament` cache would hide the new admin tools, and an old compiled Terms page could keep showing version 1.0.

## 8. (e) Post-deploy checks

### 8.1 PowerShell checks

Open **Windows PowerShell** on your PC and paste the blocks below **in order into the same window**. Each prints what to expect next to what it got.

These checks sign the review account in the way App Review will, which records Terms 1.1 on it. They file one test report and one test block, and you dismiss both in 8.2. The block is undone right away.

**Setup**

```powershell
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$api   = 'https://api.sokoni.co.tz/api'
$plain = @{ Accept = 'application/json' }
```

**Check 1: the new code and routes are live**

```powershell
try { Invoke-WebRequest -Uri "$api/blocks" -Headers $plain -UseBasicParsing | Out-Null; 'FAIL: /api/blocks answered without sign-in' }
catch { $code = [int]$_.Exception.Response.StatusCode; if ($code -eq 401) { 'PASS: /api/blocks exists and needs sign-in (401)' } else { "FAIL: /api/blocks answered $code. 404 means old code or a cached routes-v7.php: redo steps 5 and 7" } }
```

**Check 2: review account, username `appreview`, first screen, no code**

```powershell
$login = Invoke-RestMethod -Method Post -Uri "$api/auth/login" -Headers $plain -ContentType 'application/json' -Body (@{ login = 'appreview'; password = 'Review2026'; terms_version = '1.1' } | ConvertTo-Json)
$token = $login.token
$auth  = @{ Accept = 'application/json'; Authorization = "Bearer $token" }
"token: $([bool]$token)   asks for a code: $([bool]$login.requires_code)   terms on file: $($login.user.terms_version)   (expect True / False / 1.1)"
(Invoke-RestMethod -Uri "$api/auth/me" -Headers $auth).data | Select-Object id, name, username, terms_accepted, terms_version, needs_credential_setup
```

Expected from the last line: `terms_accepted True`, `terms_version 1.1`, `needs_credential_setup False`. That means the app shows nothing more after sign-in.

If `asks for a code` is `True`, the `REVIEW_ACCOUNT_PASSWORD` line is missing or wrong, or `config.php` is still cached. Fix steps 6 and 7. A reviewer could still finish with 123456, but don't submit like that.

**Check 3: phone number, and a build 7 style sign-in (no `terms_version`)**

```powershell
$phone = Invoke-RestMethod -Method Post -Uri "$api/auth/login" -Headers $plain -ContentType 'application/json' -Body (@{ login = '+255783029233'; password = 'Review2026'; terms_version = '1.1' } | ConvertTo-Json)
$old   = Invoke-RestMethod -Method Post -Uri "$api/auth/login" -Headers $plain -ContentType 'application/json' -Body (@{ login = '783 029 233'; password = 'Review2026' } | ConvertTo-Json)
"phone: token $([bool]$phone.token), asks for a code $([bool]$phone.requires_code)   (expect True / False)"
"build 7 style: token $([bool]$old.token), terms_accepted $($old.user.terms_accepted)   (expect True / True)"
```

**Check 4: "Sign in with phone number instead" with the fixed code 123456**

```powershell
Invoke-RestMethod -Method Post -Uri "$api/auth/otp/request" -Headers $plain -ContentType 'application/json' -Body (@{ phone = '+255783029233' } | ConvertTo-Json) | Select-Object is_new_account, expires_at
$otp = Invoke-RestMethod -Method Post -Uri "$api/auth/otp/verify" -Headers $plain -ContentType 'application/json' -Body (@{ phone = '+255783029233'; code = '123456'; terms_version = '1.1' } | ConvertTo-Json)
"code 123456: token $([bool]$otp.token), terms on file $($otp.user.terms_version)   (expect True / 1.1)"
```

`is_new_account` must be `False`. No SMS is sent for this one number.

**Check 5: the report endpoint**

```powershell
try { Invoke-WebRequest -Method Post -Uri "$api/reports" -Headers $plain -UseBasicParsing | Out-Null; 'FAIL: report accepted without sign-in' }
catch { "report without sign-in: $([int]$_.Exception.Response.StatusCode)   (expect 401)" }
$product = (Invoke-RestMethod -Uri "$api/products" -Headers $plain).data | Select-Object -First 1
"test product: #$($product.id) $($product.title), shop #$($product.seller.id) $($product.seller.shop_name)"
Invoke-RestMethod -Method Post -Uri "$api/reports" -Headers $auth -ContentType 'application/json' -Body (@{ reportable_type = 'product'; reportable_id = $product.id; reason = 'other'; note = 'Release 1.0 (9) check, please dismiss' } | ConvertTo-Json)
```

Expected last line: `message : Report submitted for review.`

**Check 6: the block endpoint**

```powershell
$me = (Invoke-RestMethod -Uri "$api/auth/me" -Headers $auth).data
try { Invoke-RestMethod -Method Post -Uri "$api/blocks" -Headers $auth -ContentType 'application/json' -Body (@{ user_id = $me.id } | ConvertTo-Json) | Out-Null; 'FAIL: self-block accepted' }
catch { "block yourself: $([int]$_.Exception.Response.StatusCode)   (expect 422)" }

$block = (Invoke-RestMethod -Method Post -Uri "$api/blocks" -Headers $auth -ContentType 'application/json' -Body (@{ seller_id = $product.seller.id } | ConvertTo-Json)).data
"blocked: user #$($block.user_id) ($($block.shop_name))"
try { Invoke-RestMethod -Uri "$api/products/$($product.id)" -Headers $auth | Out-Null; 'FAIL: the blocked shop is still visible' }
catch { "blocked shop's product: $([int]$_.Exception.Response.StatusCode)   (expect 403)" }
"blocked accounts: $(@((Invoke-RestMethod -Uri "$api/blocks" -Headers $auth).data).Count)   (expect 1)"

Invoke-RestMethod -Method Delete -Uri "$api/blocks/$($block.user_id)" -Headers $auth
"after unblock: $(@((Invoke-RestMethod -Uri "$api/blocks" -Headers $auth).data).Count)   (expect 0)"
"product visible again: $((Invoke-RestMethod -Uri "$api/products/$($product.id)" -Headers $auth).data.id -eq $product.id)   (expect True)"
```

Blocking a shop also unfollows it, so if the review account followed that shop, it no longer does.

**Check 7: public reviews show no private details, and the Terms page is 1.1**

```powershell
$raw = (Invoke-WebRequest -Uri "$api/sellers/$($product.seller.handle)/reviews" -Headers $plain -UseBasicParsing).Content
"reviews show phone: $($raw -match '"phone"'), email: $($raw -match '"email"')   (expect False / False)"
$terms = (Invoke-WebRequest -Uri 'https://sokoni.co.tz/terms' -UseBasicParsing).Content
"Terms page version 1.1: $($terms -match 'version 1\.1'), zero-tolerance clause: $($terms -match 'Zero tolerance')   (expect True / True)"
```

**Check 8: sign the test sessions out**

```powershell
foreach ($t in @($token, $phone.token, $old.token, $otp.token)) { if ($t) { Invoke-RestMethod -Method Post -Uri "$api/auth/logout" -Headers @{ Accept = 'application/json'; Authorization = "Bearer $t" } | Out-Null } }
'signed out the 4 test sessions'
```

If any check prints `FAIL`, or a red PowerShell error with a 500 status appears, go to step 10 (rollback).

### 8.2 Admin panel and inbox (browser)

- [ ] Sign in at `https://api.sokoni.co.tz/admin`. Within a minute, the **bell** at the top right shows **"New report"** and **"A user was blocked"**.
- [ ] **Trust & Safety → Reports** lists both with a green **"23h left"** badge. Open the product report: it shows the product's text and photos.
- [ ] The inbox from step 6 has two emails, one per report. If not, mail isn't going out: check the `MAIL_*` lines in `.env` and `storage/logs/mail-*.log`. The reports are saved and the bell works either way.
- [ ] **Dismiss** both test reports.

### 8.3 The apps already in the stores

- [ ] On a phone with the live Android app (1.0.0+8), and on an iPhone or iPad with build 7 if you have one: the feed loads, a shop and its reviews open, an existing chat opens and you can send a message.

### 8.4 Scheduler

- [ ] cPanel → **Cron Jobs**: this line from the original deployment is still there (it sends the daily summary at 08:00 Dar es Salaam time):
  ```
  * * * * * /usr/local/bin/php /home/sokoftsn/sokoni-api/artisan schedule:run >> /dev/null 2>&1
  ```

When all of step 8 passes, tell Oswald to build (step 9).

## 9. iOS 1.0 (9) on the Mac (Oswald)

Only start after Emerson confirms step 8 passed: the new app needs the new API.

### 9.1 Get the branch

The branch isn't on GitHub unless Emerson pushes it. Use whichever applies:

- **Emerson pushed it:**
  ```bash
  cd ~/sokoni
  git fetch origin
  git checkout fix/ugc-moderation
  git pull --ff-only
  ```
- **Emerson sent `sokoni-1.0.9.bundle`** (saved to `~/Downloads`):
  ```bash
  cd ~/sokoni
  git fetch origin
  git bundle verify ~/Downloads/sokoni-1.0.9.bundle
  git fetch ~/Downloads/sokoni-1.0.9.bundle fix/ugc-moderation:fix/ugc-moderation
  git checkout fix/ugc-moderation
  ```
  `git fetch origin` comes first because the bundle builds on `main`.

`~/sokoni` stands for your clone. Use its real path. Then check:

```bash
git status --short                                         # prints nothing
git merge-base --is-ancestor d7b30d4 HEAD && echo OK       # prints OK
grep '^version:' app/pubspec.yaml                          # version: 1.0.0+9
ls app/ios/Runner/GoogleService-Info.plist                 # must exist: the same file build 7 used (it is not in git)
```

### 9.2 Build

Use the same Flutter and Xcode as build 7. The branch was tested with Flutter 3.47.7.

```bash
cd ~/sokoni/app
flutter --version
flutter clean
flutter pub get
cd ios
pod install
cd ..
flutter build ipa --release
```

- **This is the prod build.** iOS has one scheme, `Runner`, and it is the production app. Release builds call `https://api.sokoni.co.tz/api` by default.
- **Don't add `--flavor prod`.** Only Android has flavors. On iOS, Flutter stops with "The Xcode project does not define custom schemes. You cannot use the --flavor option."
- **Don't add any `--dart-define`.** That keeps Google and Apple sign-in hidden exactly as in build 7, and keeps the production API.
- **If `pod install` fails** with a CocoaPods spec error, run `pod install --repo-update` instead.

Output:

- `build/ios/ipa/*.ipa`
- `build/ios/archive/Runner.xcarchive`

Check the version:

```bash
/usr/libexec/PlistBuddy -c 'Print CFBundleShortVersionString' -c 'Print CFBundleVersion' build/ios/archive/Runner.xcarchive/Products/Applications/Runner.app/Info.plist
# 1.0.0
# 9
```

### 9.3 Upload to App Store Connect

Either:

- Open **Transporter**, sign in with the team's Apple ID, drag in `build/ios/ipa/*.ipa`, then **Deliver**.
- Or `open build/ios/archive/Runner.xcarchive` → Xcode Organizer → **Distribute App** → **App Store Connect** → **Upload** → keep the defaults → **Upload**.

Build **1.0 (9)** appears in App Store Connect → TestFlight after Apple finishes processing, usually 10 to 30 minutes.

### 9.4 Check on the iPad (TestFlight) before submitting

1. Install 1.0 (9) from TestFlight.
2. Profile → **Sign in**. The sheet opens on **Sokoni Terms of Use** with one **Agree and continue** button.
3. After Agree: only **Sign in** and **Create an account**. **No Google or Apple button.**
4. **Sign in** → `appreview` / `Review2026`. It goes straight in, with no code and no second Terms screen.
5. On any product, the **⋯** menu has **Report…** and **Block…**.
6. Run through [RECORDING_SCRIPT.md](RECORDING_SCRIPT.md) once, then record it for real.

### 9.5 Submit

1. App Store Connect → Sokoni → iOS App → version **1.0** → **Build** → choose **1.0 (9)**.
2. **App Review Information:** Username `appreview`, Password `Review2026`. In **Notes**, keep the sign-in text and add the moderation note below.
3. **Resolution Center:** reply to the Guideline 1.2 message with the same note and attach the screen recording.
4. **Submit for Review.**

Note:

> Sign-in: enter username appreview and password Review2026 on the first sign-in screen (no code is needed). Alternatively tap "Sign in with phone number instead", enter 783 029 233, tap "Send code" and enter 123456.
>
> Guideline 1.2, what changed in 1.0 (9):
> 1. Terms (EULA): before any sign-in or account creation, the app shows the Sokoni Terms of Use with one "Agree and continue" button. They state zero tolerance for objectionable content and abusive users (Profile → Settings → Terms of Use, clause 2, and https://sokoni.co.tz/terms). Existing accounts agree to the new version once.
> 2. Flag content: every listing, shop, feed post, offer, video, update, comment, review and chat has a "⋯" button with "Report…", with reasons including offensive or abusive, harassment or hate, and sexual or violent content. Chat messages can also be reported by pressing and holding.
> 3. Block users: the same "⋯" menu has "Block…". The blocked user's content disappears from the blocker's feed, search, comments, reviews, chats and notifications at once, neither can message the other, and our moderators are notified of every block. Blocked accounts are managed in Settings → Blocked accounts.
> 4. Moderators are emailed and alerted in the admin panel for every report and block, and a daily summary flags anything older than 20 hours. We act on every report within 24 hours by removing the content and banning the user who posted it, which removes all of their content.

## 10. (f) Rollback

**When:** after step 5, the app or website breaks (feed doesn't load, sign-in fails, 500 errors) and step 7 didn't fix it.

### 10.1 Code: about 2 minutes, and usually all you need

1. [ ] File Manager → `/home/sokoftsn/sokoni-api` → **Upload** `rollback-code.zip` → **Extract** into `/home/sokoftsn/sokoni-api` → delete the zip. This puts back the previous version of every file `2-code.zip` changed.
2. [ ] Repeat step 7 (clear the cached files).
3. [ ] Check: in a browser, `https://api.sokoni.co.tz/api/blocks` shows a 404 page (the old code has no such route), and the app's feed and sign-in work again.

The 14 files that are new in this release stay on the server, but nothing in the old code uses them. If `rollback-code.zip` isn't to hand, extract the step 1.2 backup zip the same way.

### 10.2 Database: normally leave it

The old code works with the new tables and columns, because they are additive. Only roll the database back if the release is abandoned for good. It deletes every block users made, and un-hides anything moderators hid with the new tools.

**By cron** (same pattern as step 4): add a *Once Per Minute* cron job with this single line, wait 2 minutes, check the log, then delete the cron job.

```
cd /home/sokoftsn/sokoni-api && [ ! -e storage/app/release-1.0.9-rollback.lock ] && touch storage/app/release-1.0.9-rollback.lock && { /usr/local/bin/php artisan migrate:rollback --force --no-interaction --path=database/migrations/2026_11_04_100000_create_user_blocks_table.php --path=database/migrations/2026_11_04_100100_add_is_hidden_to_moderated_content.php --path=database/migrations/2026_11_04_100200_add_actor_id_to_app_notifications_table.php --path=database/migrations/2026_11_04_100300_add_moderation_timestamps_to_reports_table.php --path=database/migrations/2026_11_04_100400_create_notifications_table.php; echo "EXIT CODE: $?"; } > storage/logs/release-1.0.9-rollback.log 2>&1
```

`storage/logs/release-1.0.9-rollback.log` should list the five, newest first, each `DONE`, then `EXIT CODE: 0`. Keep the five migration files on the server either way.

**Or by hand** in phpMyAdmin → SQL, one statement at a time:

```sql
DROP TABLE IF EXISTS `notifications`;
ALTER TABLE `reports` DROP COLUMN `alerted_at`, DROP COLUMN `resolved_at`;
ALTER TABLE `app_notifications` DROP FOREIGN KEY `app_notifications_actor_id_foreign`;
ALTER TABLE `app_notifications` DROP COLUMN `actor_id`;
ALTER TABLE `seller_profiles` DROP COLUMN `is_hidden`;
ALTER TABLE `messages` DROP COLUMN `is_hidden`;
ALTER TABLE `showcases` DROP COLUMN `is_hidden`;
ALTER TABLE `updates` DROP COLUMN `is_hidden`;
DROP TABLE IF EXISTS `user_blocks`;
DELETE FROM `migrations` WHERE `migration` LIKE '2026_11_04_1%';
```

### 10.3 Last resort

phpMyAdmin → **Import** the `.sql.gz` from step 1.1. This also throws away every order, message and sign-up made since the backup.

---

## Appendix A: what is in each zip

All paths are relative to `/home/sokoftsn/sokoni-api`.

**`1-migrations.zip`:** the five files in `database/migrations/` listed in step 3.

**`2-code.zip`** has 74 files plus `RELEASE-1.0.9.txt`. It holds every runtime file changed since build 7's server code. Tests, factories and `.env.example` are left out.

- `app/Console/Commands/`: `SendWaitingReportsSummary.php`
- `app/Filament/Resources/Reports/`: `ReportActions.php`, `ReportResource.php`
- `app/Filament/Resources/Reports/Pages/`: `ViewReport.php`
- `app/Filament/Resources/Reports/Schemas/`: `ReportInfolist.php`
- `app/Filament/Resources/Reports/Tables/`: `ReportsTable.php`
- `app/Filament/Widgets/`: `ModerationQueueTable.php`, `SokoniStatsOverview.php`
- `app/Http/Controllers/Api/`: `AuthController.php`, `BlockController.php`, `CommentController.php`, `ConversationController.php`, `FeedController.php`, `MessageController.php`, `NotificationController.php`, `OfferController.php`, `OrderController.php`, `PasswordAuthController.php`, `ProductController.php`, `ReportController.php`, `ReviewController.php`, `SellerProfileController.php`, `ShowcaseController.php`, `UpdateController.php`
- `app/Http/Controllers/Web/`: `HomeController.php`, `ProductController.php`, `SearchController.php`, `SeoController.php`, `ShopController.php`, `StoresController.php`
- `app/Http/Controllers/Web/Account/`: `ReportController.php`
- `app/Http/Requests/`: `StoreBlockRequest.php`, `StoreReportRequest.php`
- `app/Http/Resources/`: `BlockedUserResource.php`, `ConversationResource.php`, `PublicUserResource.php`, `ReviewResource.php`, `UserResource.php`
- `app/Models/`: `AppNotification.php`, `Comment.php`, `Conversation.php`, `Message.php`, `Offer.php`, `Product.php`, `Report.php`, `Review.php`, `SellerProfile.php`, `Showcase.php`, `Update.php`, `User.php`, `UserBlock.php`
- `app/Models/Concerns/`: `HidesBlockedAuthors.php`
- `app/Notifications/`: `ModerationTime.php`, `NewReportNotification.php`, `WaitingReportsSummaryNotification.php`
- `app/Observers/`: `ReportObserver.php`, `ReviewObserver.php`
- `app/Providers/`: `AppServiceProvider.php`
- `app/Providers/Filament/`: `AdminPanelProvider.php`
- `app/Services/Auth/`: `ReviewAccount.php`
- `app/Services/Catalog/`: `ProductSearchFilters.php`, `ProductSearchService.php`
- `app/Services/Feed/`: `FeedService.php`
- `app/Services/Moderation/`: `BlockList.php`, `ModerationAlerts.php`
- `app/Services/Push/`: `LogPushNotifier.php`, `PushNotifier.php`
- `app/Support/`: `Legal.php`, `TermsAcceptance.php`
- `config/`: `services.php`, `sokoni.php`
- `resources/views/web/pages/`: `terms.blade.php`
- `routes/`: `api.php`, `console.php`

`ReviewAccount.php`, `config/services.php` and the review-account lines in `PasswordAuthController.php` and `User.php` come from the earlier App Review sign-in fix on `main`. They are included in case that fix never reached the server. If it did, they overwrite identical code.

The website (`sokoni.co.tz`) runs from this same folder through `public_html/index.php`, so nothing goes into `public_html`. No CSS rebuild is needed: the Terms page uses only styles the site already has.

**`rollback-code.zip`:** `main`'s version of the 60 files above that existed before this release.

## Appendix B: migrations by hand, only if the cron job can't run PHP

This SQL is what the five migrations run. Run it in phpMyAdmin → SQL **only** if step 4 failed because the cron job couldn't run PHP at all, and the log shows no migration ran. Paste it all at once.

```sql
CREATE TABLE `user_blocks` (`id` bigint unsigned not null auto_increment primary key, `blocker_id` bigint unsigned not null, `blocked_id` bigint unsigned not null, `reason` varchar(50) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
ALTER TABLE `user_blocks` ADD CONSTRAINT `user_blocks_blocker_id_foreign` FOREIGN KEY (`blocker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `user_blocks` ADD CONSTRAINT `user_blocks_blocked_id_foreign` FOREIGN KEY (`blocked_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `user_blocks` ADD UNIQUE `user_blocks_blocker_id_blocked_id_unique`(`blocker_id`, `blocked_id`);
ALTER TABLE `user_blocks` ADD INDEX `user_blocks_blocked_id_index`(`blocked_id`);

ALTER TABLE `seller_profiles` ADD `is_hidden` tinyint(1) not null default '0';
ALTER TABLE `messages` ADD `is_hidden` tinyint(1) not null default '0';
ALTER TABLE `showcases` ADD `is_hidden` tinyint(1) not null default '0';
ALTER TABLE `updates` ADD `is_hidden` tinyint(1) not null default '0';

ALTER TABLE `app_notifications` ADD `actor_id` bigint unsigned null;
ALTER TABLE `app_notifications` ADD CONSTRAINT `app_notifications_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
ALTER TABLE `app_notifications` ADD INDEX `app_notifications_actor_id_index`(`actor_id`);

ALTER TABLE `reports` ADD `alerted_at` timestamp null, ADD `resolved_at` timestamp null;

CREATE TABLE `notifications` (`id` char(36) not null, `type` varchar(191) not null, `notifiable_type` varchar(191) not null, `notifiable_id` bigint unsigned not null, `data` text not null, `read_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
ALTER TABLE `notifications` ADD INDEX `notifications_notifiable_type_notifiable_id_index`(`notifiable_type`, `notifiable_id`);

SET @batch = (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations);
INSERT INTO migrations (migration, batch) VALUES
  ('2026_11_04_100000_create_user_blocks_table', @batch),
  ('2026_11_04_100100_add_is_hidden_to_moderated_content', @batch),
  ('2026_11_04_100200_add_actor_id_to_app_notifications_table', @batch),
  ('2026_11_04_100300_add_moderation_timestamps_to_reports_table', @batch),
  ('2026_11_04_100400_create_notifications_table', @batch);
```

If any statement errors, stop, note which one, and run the step 10.2 "by hand" SQL to remove whatever was created before retrying.

## Appendix C: how the zips were made

From the committed branch only (`git archive`), on the Windows PC:

```bash
cd /d/Projects/sokoni-ugc
C=d7b30d44e1449ad5bc8a020b1b399adbc0e571eb
git diff --name-only 5d11e2b..$C -- api/ | grep -v -E '^api/tests/|^api/database/factories/|^api/\.env\.example$' | sed 's#^api/##' > all.txt
grep '^database/migrations/' all.txt > step1.txt; grep -v '^database/migrations/' all.txt > step2.txt
git archive --format=zip -o 1-migrations.zip $C:api $(cat step1.txt)
git archive --format=zip -o 2-code.zip --add-virtual-file="RELEASE-1.0.9.txt:..." $C:api $(cat step2.txt)
git archive --format=zip -o rollback-code.zip main:api $(for f in $(cat step2.txt); do git cat-file -e main:api/$f 2>/dev/null && echo $f; done)
```

`5d11e2b` is build 7. Its server code is the same as the username/password release (`432a296`) that production runs. Every file in the zips was compared byte for byte with the commit it came from.
