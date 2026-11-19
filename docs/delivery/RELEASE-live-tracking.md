# Release: Bolt live tracking, flat delivery fee and Android sign-in, dark (production checklist)

This updates `api.sokoni.co.tz` from the Bolt dark release to the live-tracking code. **Bolt and returns stay switched off.** Nobody sees anything new: the apps in the stores keep working exactly as now. The one thing that starts working at once is the server half of **Sign in with Apple on Android**, which Android 1.1.0+11 needs. Switching Bolt on is still a separate step later ([BOLT.md](BOLT.md) § 7).

- **Branch:** `feature/bolt-live-tracking`. It is the Bolt dark release plus live Bolt tracking, the flat delivery fee, the returns fixes, richer admin pages, and `release/android-1.1` (Google/Apple sign-in on Android, report and block everywhere).
- **Code commit in the zip:** `86b818136fa18d537bef4889186891e99260978f`
- **Production base it was built against:** `5fa4c801a34a578f484f10bc241813ffcef62f41` (the Bolt dark release, [RELEASE.md](RELEASE.md))
- **Zips:** `D:\Projects\sokoni-release-live-tracking\` on the Windows PC:
  - `code.zip`: the 26 new or changed code files, plus `RELEASE-live-tracking.txt`
  - `rollback-code.zip`: production's version of the 22 files this release changes
- **No migration.** No file in `database/migrations` changed since `5fa4c80`, and every column the new code reads was created by the dark release. There is no database step, so there is only one upload.
- **Tools:** cPanel File Manager, phpMyAdmin (one read-only query), and PowerShell on your PC for the checks. No SSH needed.
- **Time:** about 20 minutes.

**The Bolt dark release must be live first.** Step 2 stops you if it isn't.

**Do this before Android 1.1.0+11 reaches users.** Its Sign in with Apple sends people through `https://sokoni.co.tz/auth/apple/callback`, which only exists from this release. § 7 lists the two console settings Android sign-in also needs.

### What changes for the live apps

- **Nothing they show.** With `BOLT_ENABLED=false` every delivery and returns route still answers exactly like a route that doesn't exist, including the new `GET /api/delivery/orders/{order}/location` (the live map). No shop, product, order, user or conversation response gains or loses a field.
- **One new route, always on:** `POST https://sokoni.co.tz/auth/apple/callback`. Apple posts the Android sign-in result there and it is sent straight back to the app (`intent://callback?…`). It stores nothing, checks nothing, takes only `code`, `id_token`, `state`, `user` and `error`, and is limited to 30 requests a minute per address. The token is still verified by `POST /api/auth/social`, as before.
- **When Bolt is switched on later,** buyers and shops get the flat delivery fee: the delivery views and the checkout quote no longer carry Bolt's estimate range or `final_cost`. Bolt's estimate, receipt, `final_cost` and `cost_difference` stay in the admin. Only app 1.1 and later read these answers.
- **Admin:** Bolt deliveries and Returns gain the driver, costs, refunds, a timeline and the driver's live position, plus **Record ride fee paid** and **Mark ride fee refunded** on returns.

---

## 1. Back up

- [ ] **1.1 Database.** Nothing in the database changes, but take the usual export: cPanel → phpMyAdmin → the Sokoni database → **Export** → **Custom** → tick **Add DROP TABLE / VIEW / PROCEDURE / FUNCTION / EVENT / TRIGGER statement** → Compression **gzipped** → **Export**. Keep the `.sql.gz` on your PC.
- [ ] **1.2 Code.** File Manager → `/home/sokoftsn/sokoni-api` → select `app`, `bootstrap`, `config`, `lang` and `routes` (Ctrl-click) → **Compress** → Zip Archive → `backup-before-live-tracking.zip` → **Compress Files**. Right-click it → **Download**, then delete it from the server.
- [ ] **1.3 `.env`.** Right-click `/home/sokoftsn/sokoni-api/.env` → **Download**. (Turn on *Settings → Show Hidden Files* if you can't see it.)

## 2. Pre-flight checks (nothing changes yet)

- [ ] **2.1 The database is the dark release's.** phpMyAdmin → Sokoni database → **SQL**:
  ```sql
  SELECT migration FROM migrations ORDER BY id DESC LIMIT 1;
  ```
  Expected: `2026_11_17_100500_add_returns_terms_to_users_table`.
  - `2026_11_04_100400_create_notifications_table`: **STOP.** The Bolt dark release isn't live. Do [RELEASE.md](RELEASE.md) first.
  - Anything else: **STOP.** Send the row to your developer.
- [ ] **2.2 The code is the dark release's.** File Manager:
  - Open `/home/sokoftsn/sokoni-api/RELEASE-bolt-dark.txt`. It must say `commit: 5fa4c801a34a578f484f10bc241813ffcef62f41`. Missing or different: **STOP**.
  - `/home/sokoftsn/sokoni-api/app/Services/Delivery/DriverLocationService.php` must **not** exist. If it does, this release (or part of it) is already there: **STOP** and ask your developer.
- [ ] **2.3 `.env`.** Right-click `/home/sokoftsn/sokoni-api/.env` → **View**. Note, and leave as they are:
  - `BOLT_ENABLED` and `DELIVERY_RETURNS_ENABLED` (expected `false`)
  - `GOOGLE_CLIENT_ID` and `APPLE_SERVICE_ID` (step 4 uses them)

## 3. Upload the code

- [ ] File Manager → `/home/sokoftsn/sokoni-api` → **Upload** `code.zip` → when it reaches 100%, **Go Back** → right-click `code.zip` → **Extract** → path `/home/sokoftsn/sokoni-api` → **Extract Files**. It overwrites the old files. The full list is in [Appendix A](#appendix-a-what-is-in-each-zip).
- [ ] Open `/home/sokoftsn/sokoni-api/RELEASE-live-tracking.txt`. It must say `commit: 86b818136fa18d537bef4889186891e99260978f`.
- [ ] Delete `code.zip` from the server.
- [ ] Nothing to delete: this release removes no file. Never delete `.env`, `vendor/`, `storage/` or anything in `public_html/uploads`. `vendor/` doesn't change (`composer.json` and `composer.lock` are the same as the dark release's).

The new code is live from this moment. Do steps 4 and 5 straight away.

## 4. `.env` lines

**No new line is required.** Three optional settings have working defaults; leave them out:

```
# APPLE_ANDROID_PACKAGE=tz.co.sokoni.sokoni     (where /auth/apple/callback sends Android users back)
# BOLT_LOCATION_PATH=rides/location             (Bolt's live-location endpoint; check against Bolt's Swagger before BOLT_ENV=live)
# BOLT_LOCATION_CACHE_SECONDS=5                 (at most one Bolt location call per ride in this many seconds)
```

Don't change `BOLT_ENABLED`, `DELIVERY_RETURNS_ENABLED` or any other `BOLT_` line.

For **Android 1.1 sign-in**, the two values you noted in step 2.3 must be the ones the app was built with:

| Key | Must be |
|---|---|
| `APPLE_SERVICE_ID` | `tz.co.sokoni.signin` |
| `GOOGLE_CLIENT_ID` | `825257290618-pis0jcmn11bo5n3vpi5q0nchagrlhbuq.apps.googleusercontent.com` |

- **`APPLE_SERVICE_ID` empty or missing:** right-click `.env` → **Edit** → add `APPLE_SERVICE_ID=tz.co.sokoni.signin` (no spaces around `=`) → **Save Changes**. Nothing else uses it.
- **`APPLE_SERVICE_ID` set to something else:** don't change it; tell your developer. Apple sign-in on Android fails until the two match.
- **`GOOGLE_CLIENT_ID` is a different value:** **don't change it here.** The website's own "Sign in with Google" uses it with `GOOGLE_CLIENT_SECRET`, and the secret belongs to that client. Tell your developer: Google sign-in on Android 1.1 fails ("audience mismatch") until the app and the server use the same Web client. The rest of this release is unaffected.

## 5. Clear the cached files

In File Manager:

- [ ] `/home/sokoftsn/sokoni-api/bootstrap/cache/`: delete these **if they are there**: `config.php`, `routes-v7.php`, `events.php` and the folder `filament`.
  - **Do not** delete `packages.php`, `services.php` or `.gitignore`.

Why: a cached `routes-v7.php` would hide `/auth/apple/callback` and the new location route, a cached `config.php` would ignore the new settings in `config/delivery.php` and `config/services.php` and any `.env` edit from step 4, and a `filament` cache could show the old admin pages. No page template changed in this release, so `storage/framework/views/` can stay; clearing it too is harmless.

## 6. Post-deploy checks

### 6.1 PowerShell checks

Open **Windows PowerShell** on your PC and paste the blocks below **in order into the same window**. Each prints what to expect next to what it got. They change nothing on the server except signing the review account in and out.

**Setup**

```powershell
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$api   = 'https://api.sokoni.co.tz/api'
$site  = 'https://sokoni.co.tz'
$plain = @{ Accept = 'application/json' }
function Status($scriptBlock) { try { & $scriptBlock | Out-Null; 200 } catch { [int]$_.Exception.Response.StatusCode } }
function AppleCallback($method, $body) {
  $req = [System.Net.HttpWebRequest]::Create("$site/auth/apple/callback")
  $req.Method = $method; $req.AllowAutoRedirect = $false
  if ($body) {
    $bytes = [Text.Encoding]::ASCII.GetBytes($body)
    $req.ContentType = 'application/x-www-form-urlencoded'; $req.ContentLength = $bytes.Length
    $s = $req.GetRequestStream(); $s.Write($bytes, 0, $bytes.Length); $s.Close()
  }
  try { $res = $req.GetResponse() } catch [System.Net.WebException] { $res = $_.Exception.Response }
  $out = @{ status = [int]$res.StatusCode; location = $res.Headers['Location'] }; $res.Close(); $out
}
```

**Check 1: Bolt and returns are still off**

```powershell
try { $f = (Invoke-RestMethod -Uri "$api/features" -Headers $plain).data; "features: delivery $($f.delivery_enabled), returns $($f.returns_enabled)   (expect False / False)" }
catch { "FAIL: /api/features answered $([int]$_.Exception.Response.StatusCode)" }
```

**Check 2: the delivery and returns routes, including the new live map, look like they don't exist**

```powershell
foreach ($u in @('delivery/config', 'delivery/orders/1', 'delivery/orders/1/location', 'returns')) {
  try { Invoke-RestMethod -Uri "$api/$u" -Headers $plain | Out-Null; "FAIL: /api/$u answered" }
  catch { "/api/$u : $([int]$_.Exception.Response.StatusCode)   (expect 404)" }
}
"webhook: $(Status { Invoke-RestMethod -Method Post -Uri "$api/webhooks/bolt" -Headers $plain })   (expect 404)"
```

A `401` here means the cached routes or config weren't cleared: redo step 5.

**Check 3: Sign in with Apple's Android callback is live**

```powershell
$a = AppleCallback 'POST' 'code=check&state=s1'
"apple callback: $($a.status)  $($a.location)"
"   (expect 307  intent://callback?code=check&state=s1#Intent;package=tz.co.sokoni.sokoni;scheme=signinwithapple;end)"
"apple callback by GET: $((AppleCallback 'GET' $null).status)   (expect 405)"
```

- `404`: old code or a cached `routes-v7.php`: redo steps 3 and 5.
- `419`: the CSRF exemption isn't loaded, so `bootstrap/app.php` is the old one: redo step 3.
- `500`: go to step 8 (rollback) and send `storage/logs/laravel-*.log` to your developer.

**Check 4: the App Store review account still signs in and sees nothing new**

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

**Check 5: the feed and a shop still load**

```powershell
$product = (Invoke-RestMethod -Uri "$api/products" -Headers $plain).data | Select-Object -First 1
"product #$($product.id): $(Status { Invoke-RestMethod -Uri "$api/products/$($product.id)" -Headers $auth })   shop @$($product.seller.handle): $(Status { Invoke-RestMethod -Uri "$api/sellers/$($product.seller.handle)" -Headers $auth })   (expect 200 / 200)"
```

**Check 6: sign the review account out**

```powershell
Invoke-RestMethod -Method Post -Uri "$api/auth/logout" -Headers $auth | Out-Null; 'signed out'
```

If any check prints `FAIL`, an unexpected value, or a red PowerShell error with a 500 status, go to step 8 (rollback).

### 6.2 Admin panel (browser)

- [ ] Sign in at `https://api.sokoni.co.tz/admin`. The dashboard loads.
- [ ] **Commerce → Bolt deliveries** and **Trust & Safety → Returns** open (empty while Bolt is off, unless staff tested). If a row exists, open it: the page loads with its new sections.
- [ ] **Trust & Safety → Reports** and **Commerce → Orders** open as before.

### 6.3 The apps already in the stores

- [ ] On the live Android app and the iOS build: the feed loads, a product and a shop open, an existing chat opens and you can send a message.

### 6.4 Logs

- [ ] After 10 minutes: `/home/sokoftsn/sokoni-api/storage/logs/` → today's `laravel-*.log` (if any). No `ERROR` line written since the deploy should mention `DriverLocation`, `LivePosition`, `AppleSignIn`, `delivery` or `returns`.

When all of step 6 passes, the server release is done.

## 7. Before Android 1.1.0+11 (and later 1.2.0+12) reaches users

These are console settings, not server files, but Android sign-in fails without them:

- [ ] **Apple Developer** → Certificates, Identifiers & Profiles → **Identifiers** → filter **Services IDs** → `tz.co.sokoni.signin` → **Configure** next to Sign in with Apple: `sokoni.co.tz` under *Domains and Subdomains* and `https://sokoni.co.tz/auth/apple/callback` under *Return URLs* → **Save**. Without it, Apple shows an `invalid_request` error page instead of the sign-in form.
- [ ] **Google Play App Signing key in Firebase.** Play Console → the app → **Test and release → App integrity → App signing** → copy the **SHA-1** of the *App signing key certificate*. Firebase console → project `sokoni-71910` → Project settings → the Android app `tz.co.sokoni.sokoni` → **Add fingerprint** → paste → Save. Store installs are signed with this key, not the upload key, so Google sign-in fails on them until it is added. (The upload key, SHA-1 `06:E8:90:E4:BF:F2:A8:59:38:6A:E2:A6:41:C1:93:9D:B6:F5:F7:30`, is already registered.)
- [ ] Step 4's `.env` table matches.

## 8. Rollback

**When:** after step 3, the app or website breaks (feed doesn't load, sign-in fails, 500 errors) and step 5 didn't fix it.

**Code only, about 3 minutes.** There is no database part: nothing in the database changed.

1. [ ] File Manager → `/home/sokoftsn/sokoni-api` → **Upload** `rollback-code.zip` → **Extract** into `/home/sokoftsn/sokoni-api` → delete the zip. This puts back production's (`5fa4c80`) version of all 22 changed files.
2. [ ] **Delete these four new files** (nothing in the old code loads them; removing them keeps the folder identical to the dark release):
   - `app/Filament/Support/LivePosition.php`
   - `app/Http/Controllers/Web/Auth/AppleSignInCallbackController.php`
   - `app/Services/Delivery/Bolt/BoltDriverLocation.php`
   - `app/Services/Delivery/DriverLocationService.php`
   - and `RELEASE-live-tracking.txt` in the same folder.
3. [ ] Repeat step 5 (clear the cached files).
4. [ ] Check with the **Setup** block from 6.1, then:
   ```powershell
   "apple callback after rollback: $((AppleCallback 'POST' 'code=check').status)   (expect 404 or 405: the route is gone)"
   try { $f = (Invoke-RestMethod -Uri "$api/features" -Headers $plain).data; "features: delivery $($f.delivery_enabled), returns $($f.returns_enabled)   (expect False / False: the dark release answers)" } catch { "FAIL: /api/features $([int]$_.Exception.Response.StatusCode)" }
   ```
   and the app's feed and sign-in work again.
5. [ ] Any `.env` line you added in step 4 can stay; the dark release reads `APPLE_SERVICE_ID` the same way.

If `rollback-code.zip` isn't to hand, extract the step 1.2 backup zip the same way, then do 2 and 3.

Android 1.1.0+11's Apple button stops working after a rollback (the callback route is gone); everything else in the app keeps working against the dark release.

---

## Appendix A: what is in each zip

All paths are relative to `/home/sokoftsn/sokoni-api`.

**`code.zip`** has 26 files plus `RELEASE-live-tracking.txt`: every runtime file added or changed since production's code (`5fa4c80`). Tests, factories and `.env.example` are left out. ✱ = changed, the rest are new.

- `app/Filament/Resources/BoltDeliveries/`: `Schemas/BoltDeliveryInfolist.php` ✱, `Tables/BoltDeliveriesTable.php` ✱
- `app/Filament/Resources/ReturnRequests/`: `ReturnRequestResource.php` ✱, `Pages/ViewReturnRequest.php` ✱, `Schemas/ReturnRequestInfolist.php` ✱, `Tables/ReturnRequestsTable.php` ✱
- `app/Filament/Support/`: `LivePosition.php`
- `app/Http/Controllers/Api/Delivery/`: `CustomerDeliveryController.php` ✱
- `app/Http/Controllers/Web/Auth/`: `AppleSignInCallbackController.php`
- `app/Http/Resources/`: `Delivery/DeliveryJobResource.php` ✱, `Returns/ReturnRequestResource.php` ✱
- `app/Models/`: `DeliveryJob.php` ✱, `ReturnRequest.php` ✱
- `app/Providers/`: `AppServiceProvider.php` ✱
- `app/Services/Delivery/`: `DriverLocationService.php`; `Bolt/BoltApiException.php` ✱, `Bolt/BoltClient.php` ✱, `Bolt/BoltDriverLocation.php`
- `app/Services/Returns/`: `ReturnService.php` ✱
- `bootstrap/`: `app.php` ✱ (the Apple callback's CSRF exemption)
- `config/`: `delivery.php` ✱, `services.php` ✱
- `lang/en/`: `returns.php` ✱; `lang/sw/`: `returns.php` ✱
- `routes/`: `api_delivery.php` ✱ (the location route), `web.php` ✱ (the Apple callback)

**Removed by this release:** nothing.

The website (`sokoni.co.tz`) runs from this same folder through `public_html/index.php`, so nothing goes into `public_html`. No CSS, page template or `vendor/` change.

**`rollback-code.zip`:** production's (`5fa4c80`) version of the 22 files marked ✱ above.

## Appendix B: how the zips were made

From the committed branch only (`git archive`), on the Windows PC, in Git Bash:

```bash
cd /d/Projects/sokoni-bolt
C=86b818136fa18d537bef4889186891e99260978f   # feature/bolt-live-tracking, code
P=5fa4c801a34a578f484f10bc241813ffcef62f41   # production (Bolt dark release)
git diff --name-status $P..$C -- api/ | grep -v -E 'api/tests/|api/database/factories/|api/\.env\.example$' | sed 's#\tapi/#\t#' > all.txt
grep -P '^[AM]\tdatabase/migrations/' all.txt | cut -f2 > migrations.txt     # empty: no migration
grep -P '^[AM]\t' all.txt | cut -f2 | grep -v '^database/migrations/' > code.txt
grep -P '^D\t' all.txt | cut -f2 > deleted.txt                               # empty: nothing removed
(cat code.txt deleted.txt) | while read f; do git cat-file -e $P:api/$f 2>/dev/null && echo $f; done > rollback.txt
git archive --format=zip -o code.zip --add-file=RELEASE-live-tracking.txt $C:api $(cat code.txt)
git archive --format=zip -o rollback-code.zip $P:api $(cat rollback.txt)
```

**Byte-for-byte check.** Each zip was extracted into an empty folder and every file compared with the blob in the commit it came from (`git hash-object --no-filters` against `git rev-parse <commit>:api/<file>`, then `cmp` against `git cat-file blob`), and the extracted file list compared with the expected list:

```
code.zip: 26 identical, 0 different, file list exactly as expected: yes
rollback-code.zip: 22 identical, 0 different, file list exactly as expected: yes
```

`api/.gitattributes` sets `eol=lf`, so `git archive` writes the committed bytes even with `core.autocrlf=true`. If the code changes after `86b8181`, rebuild both zips from the new commit and update the commit lines in this file.
