# Deploying the sokoni.co.tz website to cPanel shared hosting

This adds the public marketing/discovery website (`sokoni.co.tz`) on top of the API/admin deployment already documented in [`docs/DEPLOY.md`](DEPLOY.md) — read that first if the API isn't live yet. This doc assumes it already is: `/home/sokoftsn/sokoni-api` exists, is migrated, and already serves `api.sokoni.co.tz` correctly from `sokoni-api/public`.

**Both hostnames run the exact same Laravel application.** `sokoni.co.tz` is not a second install — it's a second *docroot* pointed at a second, much smaller front controller that requires the same `vendor/autoload.php` and `bootstrap/app.php` the API already uses. One codebase, one `.env`, one database. Routing (`routes/web.php` vs `routes/api.php`) and the two session/auth guards (`web` for the website, `sanctum` for the API) are what actually separate the two surfaces — not the filesystem.

## 1. Directory layout after this deploy

```
/home/sokoftsn/sokoni-api/                ← unchanged, already live (api.sokoni.co.tz)
/home/sokoftsn/sokoni-api/public/         ← docroot for api.sokoni.co.tz, unchanged
/home/sokoftsn/public_html/               ← docroot for sokoni.co.tz — THIS is what's new
/home/sokoftsn/public_html/index.php      ← new: front controller, points at ../sokoni-api
/home/sokoftsn/public_html/.htaccess      ← new: rewrite rules + compression/cache headers
/home/sokoftsn/public_html/build/         ← new: compiled Tailwind/Alpine assets (Vite output)
/home/sokoftsn/public_html/images/brand/  ← new: logo + favicon source images
/home/sokoftsn/public_html/uploads/       ← EXISTING — product photos, NIDA/licence files, chat
                                             attachments. Do not touch, delete, or overwrite
                                             anything under this folder. See step 3.
```

`public_html/uploads` is the `public` filesystem disk's real target (`PUBLIC_UPLOADS_ROOT`/`PUBLIC_UPLOADS_URL` in `.env`, set up in the original API deploy) — every product photo and shop logo URL already served on the live app points at `https://sokoni.co.tz/uploads/...`. This deploy adds files *alongside* it in the same directory; nothing in the zip touches or overlaps that folder.

## 2. What's currently in `public_html` — back this up first

`public_html` currently holds a coming-soon placeholder page (whatever static `index.html`/`index.php` cPanel's default account setup put there, plus possibly a default `.htaccess`) and the live `uploads/` folder.

**Before uploading anything:**

1. In cPanel → **File Manager**, select everything currently in `public_html` **except `uploads/`** and compress it to a zip (e.g. `public_html-coming-soon-backup.zip`), then download that zip somewhere safe off-server. This is the entire "what was here before" backup — a few small placeholder files, not a large operation.
2. Do **not** back up or touch `uploads/` — it isn't changing, and it's the one folder in there with real, irreplaceable data (live product/seller media).

**The coming-soon page must be removed, not left alongside the new files.** Two concrete reasons: (a) cPanel's default `DirectoryIndex` typically resolves `index.html` before `index.php` when both exist, so a leftover `index.html` would keep serving the placeholder forever regardless of what else is uploaded; (b) a stale `.htaccess` from the placeholder (if it has one) would conflict with the rewrite rules the Laravel front controller needs to route every request through `index.php`. Delete (or, given the backup above, simply overwrite) every file the coming-soon page left behind — everything except `uploads/`.

## 3. Upload the website files

From `sokoni-web-deploy.zip` (built alongside this doc — see the file list below), upload and extract into `public_html` via File Manager, or via SSH if available:

```bash
# with SSH
cd /home/sokoftsn/public_html
unzip -o /path/to/sokoni-web-deploy.zip
```

This creates/overwrites exactly: `index.php`, `.htaccess`, `build/` (compiled CSS/JS/fonts), and `images/brand/` (logo files) — nothing else in `public_html`, and nothing inside `uploads/`.

## 4. `index.php` — why it's different from the API's

The API's `sokoni-api/public/index.php` is Laravel's stock front controller: it `require`s `../vendor/autoload.php` and `../bootstrap/app.php`, both one level up from `public/`. `public_html` isn't a subfolder of `sokoni-api` — it's a *sibling* directory under `/home/sokoftsn` — so the paths have to reach across, not just up. The version in the zip:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../sokoni-api/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../sokoni-api/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../sokoni-api/bootstrap/app.php';

$app->handleRequest(Request::capture());
```

Everything else about how Laravel boots (`bootstrap/app.php`'s own `basePath: dirname(__DIR__)`) resolves relative to *that file's* location inside `sokoni-api/`, so it correctly finds `sokoni-api/routes`, `sokoni-api/storage`, etc. regardless of which docroot the request came in through — no other file needs a path change.

If SSH/File Manager access to place this exact file isn't available for some reason, it can be hand-typed via cPanel's File Manager code editor — it's short by design for exactly that reason.

## 5. `.htaccess`

Same rewrite rules as the API's (route everything through `index.php`, strip trailing slashes, forward the Authorization/XSRF headers), plus compression and cache headers added specifically for the website's SEO/performance requirements (gzip on HTML/CSS/JS/JSON/XML, a 1-year immutable cache on the content-hashed `build/assets/*` files, a 1-week cache on uploaded images/fonts). It's already present in the zip — no manual edits needed. It's scoped by content type/filename pattern, so it doesn't affect the API's JSON responses even though the *same* `.htaccess` content also lives at `sokoni-api/public/.htaccess` (both were updated together in this change).

## 6. One shared `.env`, no new environment variables

The website reads the exact same `sokoni-api/.env` the API already uses — same `APP_KEY`, same database, same `PUBLIC_UPLOADS_ROOT`/`PUBLIC_UPLOADS_URL`. Nothing new to configure. `APP_URL` doesn't need to change and doesn't need to "pick" one hostname: Laravel's `asset()`/`url()`/`route()` helpers build absolute URLs from the *actual incoming request's* host by default (nothing in this app calls `URL::forceRootUrl()`), so a request to `sokoni.co.tz` generates `sokoni.co.tz/...` links and a request to `api.sokoni.co.tz` generates `api.sokoni.co.tz/...` links automatically, from the one shared codebase.

## 7. Subdomain / domain setup in cPanel

`sokoni.co.tz` (the primary/parked domain on this account) needs its document root pointed at `/home/sokoftsn/public_html` — this is very likely already the case (it's the cPanel account's default docroot), so this step is usually just a confirmation, not a change:

1. cPanel → **Domains**: confirm `sokoni.co.tz`'s document root is `/home/sokoftsn/public_html`.
2. Confirm "Force HTTPS Redirect" is on (same AutoSSL certificate already covers the whole account, per `docs/DEPLOY.md`).
3. PHP version/extensions: `public_html` is served by the account's default PHP handler — confirm it's the same **PHP 8.3** with the same extension set (`bcmath, fileinfo, gd, intl, mbstring, zip, pdo_mysql, exif`) already configured for `api.sokoni.co.tz` in the original deploy. If cPanel lets PHP versions differ per-domain rather than per-account, set it explicitly here too.

## 8. Deploy the backend code, then run the new migrations

Everything in this doc so far only covers `public_html` — the small docroot glue (front controller, `.htaccess`, compiled assets, brand images). The actual PHP source behind the website (new `Web\*` controllers, middleware, services, support helpers, views) is a separate deploy into `sokoni-api/` itself, since that's where the shared app already lives: `sokoni-api-update-4.zip` (103 files, built the same way as the prior `sokoni-api-update-N.zip` deploys — diffed against the reconstructed baseline of every zip before it, not just the last one).

```bash
cd /home/sokoftsn/sokoni-api
unzip -o /path/to/sokoni-api-update-4.zip
composer dump-autoload --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache
```

**`composer dump-autoload`, not `composer install`, and not skipped entirely.** `composer.json`/`composer.lock` are unchanged by this deploy — no new package, so no need to touch Packagist or reinstall `vendor/`. But every new class this deploy adds (`App\Http\Controllers\Web\*`, `App\Services\Catalog\*`, `App\Support\*`, etc.) is missing from whatever `autoload_classmap.php` was generated the last time `--optimize-autoloader` ran on this server — a bare `dump-autoload` rescans `app/` and regenerates that classmap from what's already on disk, with no network calls and no package changes. Skipping it entirely is *usually* harmless (Composer's PSR-4 fallback still finds a class that's missing from a stale classmap, unless this server's install ever used the stricter `--classmap-authoritative` flag, which has never been part of this project's documented command) — but there's no reason to rely on that when the correct fix is one cheap, side-effect-free command. See `docs/DEPLOY.md`'s "Updating a deployed instance" section for the same guidance stated generally.

Two new migrations ship with this change:

1. `2026_08_19_080000_add_opening_hours_to_seller_profiles_table`
2. `2026_08_19_090000_create_leads_table`

Both are additive (a new nullable column, a new table) — no data migration risk, safe to run against the live database.

## 9. What Filament admin assets are *not* included here, on purpose

`sokoni-api/public/css|js|fonts/filament/` (the compiled admin-panel CSS/JS, ~4.2MB) aren't part of this zip. The admin panel is reachable at `api.sokoni.co.tz/admin`, where those assets already exist — `sokoni.co.tz/admin` would technically also route to the same Filament panel (routes aren't host-restricted), but its static assets would 404 from that docroot since they were never copied here. This is a deliberate scope decision, not an oversight: the task is the public website, and duplicating 4+MB of admin-only assets into the marketing docroot for a URL nobody is meant to actually use has no upside. If `sokoni.co.tz/admin` needs to work too, copy `sokoni-api/public/{css,js,fonts}/filament` into `public_html` the same way `build/` was copied here.

## 10. Verify

- `https://sokoni.co.tz/` loads the real home page (not the coming-soon placeholder) — hero search, category grid with real counts, live Offers/featured shops rows.
- `https://sokoni.co.tz/uploads/...` still resolves existing product/seller media exactly as before (proves `uploads/` survived untouched).
- `https://sokoni.co.tz/c/electronics` and `https://sokoni.co.tz/search?q=phone` return real filtered/sorted results.
- Open any real product's page and confirm the photo gallery, price, and seller card render, and `view-source:` shows a `<script type="application/ld+json">` block with real `Product` schema (not empty/null fields).
- `https://sokoni.co.tz/@<a-real-verified-handle>` resolves to that seller's shop page.
- `https://sokoni.co.tz/sitemap.xml` and `https://sokoni.co.tz/robots.txt` both return real XML/text (not 404, not the API's static placeholder `robots.txt` — that file only exists under `sokoni-api/public`, so it can't shadow the dynamic route here).
- Sign in with a real phone number via OTP end to end, confirm the terms/intent flow, and land on `/account`.
- Confirm `api.sokoni.co.tz/api/categories` and `api.sokoni.co.tz/admin` still work exactly as before — this deploy shouldn't have touched anything under `sokoni-api/public`.

## Updating the website later

Since it's the same app, an update follows `docs/DEPLOY.md`'s existing "Updating a deployed instance" section for any backend/route/controller change (`git pull` or re-upload, `composer install` if `composer.json`/`composer.lock` changed, `composer dump-autoload` if only new first-party classes were added, `migrate --force` for new migrations, `config:cache`/`route:cache`). The only *website-specific* extra step is: if `resources/css`/`resources/js` changed, rebuild locally (`npm run build`) and re-upload the new `public/build/` contents to **both** `sokoni-api/public/build` and `public_html/build` — they're two independent physical copies of the same compiled output, since the two docroots don't share a filesystem location.
