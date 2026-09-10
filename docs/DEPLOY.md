# Deploying Sokoni to cPanel Shared Hosting

Target: `sokoni.co.tz`, cPanel user `sokoftsn`, home `/home/sokoftsn`, shared IP `198.54.126.252`. No Docker, no persistent queue workers, no WebSocket server — the app is built to work within those constraints (see CLAUDE.md's stack notes).

> **What's actually live right now (2026-09-08):** the current client-testing site runs from a single combined docroot, `/home/sokoftsn/beta.sokoni.co.tz` — not yet split into the separate `api.sokoni.co.tz`/root-domain subdomains the rest of this document describes for eventual production. `APP_URL` and `PUBLIC_UPLOADS_URL`/`PUBLIC_UPLOADS_ROOT` on that host should both be based on `https://beta.sokoni.co.tz`, not `sokoni.co.tz`. This value has moved more than once (tester feedback D2) — every media URL (`product_media`, seller logos, avatars, ...) is a full absolute URL baked in at upload time, so changing this again fixes nothing for rows already uploaded under the old value. After any change, run `https://beta.sokoni.co.tz/setup.php?step=rewrite-media-host&from=<old, URL-encoded>&to=<new, URL-encoded>&confirm=1` (dry run without `&confirm=1`) to actually rewrite them — see `public/setup.php`'s own `rewrite-media-host` step for the full column list it covers. The rest of this document is the eventual production runbook (a real subdomain split); follow it when the client is ready to leave the beta subdomain.

This cPanel account exposes **Manage Shell** and **Setup Node.js App**, so check for SSH access first — it makes Composer and `artisan` far easier. Everything below also works via File Manager + phpMyAdmin if SSH is disabled; the File-Manager alternative is noted at each step.

## 1. Directory layout

```
/home/sokoftsn/sokoni-api/           ← Laravel app root, outside the web root
/home/sokoftsn/sokoni-api/public/    ← docroot for the api.sokoni.co.tz subdomain
/home/sokoftsn/public_html/uploads/  ← public disk target for media (see step 7)
```

## 2. Subdomain and PHP

1. cPanel → **Subdomains**: create `api.sokoni.co.tz` with docroot `/home/sokoftsn/sokoni-api/public`.
2. cPanel → **Select PHP Version**: set **PHP 8.3** for that subdomain (the app was built and tested against Laravel Herd's PHP 8.4, but 8.3+ is the deploy target — no version-specific syntax is used). Enable extensions: `bcmath, fileinfo, gd, intl, mbstring, zip, pdo_mysql, exif`.
   - `gd` is required — `Intervention Image` (product photo processing) uses the GD driver.

## 3. Database

1. cPanel → **MySQL Databases**: create a database and a user, grant the user **All Privileges** on it.
2. Note the full names — cPanel prefixes both with the account username (e.g. `sokoftsn_sokoni`, `sokoftsn_sokoniuser`).

## 4. Upload the application

**With SSH:**
```bash
ssh sokoftsn@sokoni.co.tz
git clone <repo-url> sokoni-api    # or upload+extract a zip
cd sokoni-api/api
composer install --no-dev --optimize-autoloader
```

**Without SSH:** build `vendor/` locally (`composer install --no-dev --optimize-autoloader` on your machine), zip the entire `api/` directory including `vendor/`, upload via File Manager, and extract into `/home/sokoftsn/sokoni-api`.

## 5. Configure `.env`

Copy `api/.env.example` to `api/.env` and fill in real values. At minimum:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.sokoni.co.tz
APP_KEY=                          # generate with: php artisan key:generate

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=sokoftsn_sokoni
DB_USERNAME=sokoftsn_sokoniuser
DB_PASSWORD=<from step 3>

FILESYSTEM_DISK=public
PUBLIC_UPLOADS_ROOT=/home/sokoftsn/public_html/uploads
PUBLIC_UPLOADS_URL=https://sokoni.co.tz/uploads

SESSION_DRIVER=database
QUEUE_CONNECTION=sync             # no persistent worker on shared hosting — see step 9
```

Plus the credentials for whichever of the BLOCKERS.md items are ready by launch (`GOOGLE_CLIENT_ID`, `APPLE_SERVICE_ID`, `FIREBASE_CREDENTIALS`, `GOOGLE_MAPS_SERVER_KEY`) — everything works with these left blank, just with social login/push/NIDA-adjacent server-side geocoding disabled until they're supplied. Facebook sign-in was removed entirely (see DECISIONS.md), not just left as a pending credential — there's no `FACEBOOK_APP_ID`/`FACEBOOK_APP_SECRET` to set anymore.

If `php artisan key:generate` isn't runnable (no SSH), generate a 32-byte base64 key any other way and set `APP_KEY=base64:<key>` directly.

## 6. Run migrations

**With SSH:**
```bash
php artisan migrate --force
php artisan db:seed --force   # optional — demo data (20 sellers, 120 products, etc.); skip for a real launch
```

**Without SSH:** export the schema from a local run of the same migrations (`php artisan schema:dump` or just run `php artisan migrate` locally against a MySQL connection matching production, then export via `mysqldump --no-data`) and import the `.sql` file through phpMyAdmin. Import the seed data the same way if wanted.

## 7. Public media disk — no `storage:link`

Symlinks (what `php artisan storage:link` creates) are frequently blocked on shared hosting. The app avoids this entirely: `config/filesystems.php`'s `public` disk reads its root and URL from `PUBLIC_UPLOADS_ROOT`/`PUBLIC_UPLOADS_URL` (set in step 5), pointing product photos, licence uploads, and chat attachments directly at a real folder under `public_html` rather than through a symlink into `storage/app/public`.

1. Create `/home/sokoftsn/public_html/uploads` (File Manager or `mkdir` over SSH).
2. `chmod 775` on that folder so PHP-FPM can write to it.
3. Nothing else needed — every upload path in the codebase already goes through `Storage::disk('public')`, which now resolves to this folder automatically once the two env vars are set.

## 8. File permissions

```bash
find /home/sokoftsn/sokoni-api -type d -exec chmod 755 {} \;
find /home/sokoftsn/sokoni-api -type f -exec chmod 644 {} \;
chmod -R 775 /home/sokoftsn/sokoni-api/storage /home/sokoftsn/sokoni-api/bootstrap/cache
```

## 9. Cron (scheduler)

cPanel → **Cron Jobs** → add:

```
* * * * * /usr/local/bin/php /home/sokoftsn/sokoni-api/artisan schedule:run >> /dev/null 2>&1
```

There's nothing scheduled in the app yet beyond Laravel's own housekeeping, but this keeps the door open (e.g. a future job to expire stale conversation "typing" signals, or clean up unattached media) without needing a persistent worker — `QUEUE_CONNECTION=sync` means queued jobs (if any get added later) run inline on the request that dispatches them rather than needing `queue:work`, which shared hosting can't keep running anyway.

## 10. HTTPS

The account's AutoSSL certificate is already active for `sokoni.co.tz` and its subdomains. In cPanel → **Domains**, confirm "Force HTTPS Redirect" is on for `api.sokoni.co.tz`.

## 11. Admin panel

Once migrated and seeded (or with a manually-created admin row), the Filament admin is reachable at `https://api.sokoni.co.tz/admin`. Sign in with whichever user has `is_admin = true` (the seeder creates `admin@sokoni.co.tz` / `password` — **change this password immediately in production**, or delete that seeded row and create a real admin account through `php artisan tinker` / a database insert instead of seeding it at all).

## 12. Verify

- `https://api.sokoni.co.tz/api/categories` returns the seeded/real categories.
- `https://api.sokoni.co.tz/admin` loads the Filament login page.
- Upload a product photo through the app or `POST /products/{id}/media`, then confirm the returned URL (`https://sokoni.co.tz/uploads/products/...`) actually loads.
- Place a test order end to end; confirm `LogPushNotifier`'s log entries appear in `storage/logs/laravel.log` (until Firebase credentials are supplied, pushes are logged, not sent — see BLOCKERS.md item 1).

## Web fallback for `sokoni.co.tz/@handle` — now a full website, not a stub card

This section used to note that the `/@handle` fallback wasn't built yet. It now is, as part of the full public website (`sokoni.co.tz`) — a real, server-rendered shop page with schema.org markup, not the small static/PHP redirect card originally sketched here. See `docs/DEPLOY_WEB.md` for that deploy — it runs from the same app this doc sets up, on a second docroot (`public_html`) alongside the API's own (`sokoni-api/public`).

## Updating a deployed instance

```bash
git pull                                          # or re-upload changed files
composer install --no-dev --optimize-autoloader   # if composer.json/composer.lock changed
composer dump-autoload --no-dev --optimize-autoloader # if only new first-party classes were added
php artisan migrate --force                       # if new migrations exist
php artisan config:cache && php artisan route:cache
```

Skip `config:cache`/`route:cache` while actively debugging a deploy — they mask `.env` changes until cleared with `php artisan config:clear`.

**New classes with no `composer.json` change still need an autoloader refresh — don't skip both lines above.** `--optimize-autoloader` (`-o`) generates a static classmap for speed, but Composer's `ClassLoader` keeps the PSR-4 fallback registered too, so a class missing from a stale classmap still resolves (just via the slower directory-lookup path) rather than fataling — *unless* this server's install ever used `--classmap-authoritative` (`-a`) instead, which removes that fallback entirely and would turn a stale classmap into a hard "Class not found" on every new file. Since this repo's documented command has only ever specified plain `-o`, the safe assumption is that the fallback is intact — but if `composer.json`/`composer.lock` are unchanged, running the cheap `composer dump-autoload` line costs nothing (no network, no package changes) and removes the question entirely rather than relying on that assumption.

---

## Flutter app release build

The signed release APK/AAB is built locally (or in CI), not on the cPanel server — cPanel only hosts the API/admin. See the repo root `README.md` for the local build commands and `android/key.properties` (gitignored) for where the release keystore credentials live. Once built, the AAB goes to the Play Console; the APK can be distributed directly for a client demo.

The app's API base URL (`app/lib/core/network/dio_client.dart`) defaults to a local-dev loopback address in debug/profile builds and to the live production API automatically in any `--release` build — no flag needed for the common case. `--dart-define=API_BASE_URL=...` still overrides either default, for a staging build or pointing a debug build at a real server:

```bash
flutter build appbundle --release --flavor prod
flutter build apk --release --flavor prod
```

`--flavor prod` is required as of the `diagnostic` build variant (see DECISIONS.md) — any flavor being declared at all means Gradle no longer has a flavor-less default. Output paths gain the flavor name too: `build/app/outputs/flutter-apk/app-prod-release.apk`, `build/app/outputs/bundle/prodRelease/app-prod-release.aab`.
