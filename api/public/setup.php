<?php
/**
 * Sokoni one-time setup runner (v2).
 *
 * Upload to the actual live public docroot so it's reachable by URL —
 * currently /home/sokoftsn/public_html/setup.php (moved from the beta
 * subdomain; not the app root's own public/ folder some earlier notes
 * named; this file must sit wherever a browser GET request can actually
 * reach it). DELETE THIS FILE as soon as setup is finished.
 */

$TOKEN = 'dXfoxo0Eu6jcYMm8egb3D4wHu1WnuSzo';

if (($_GET['token'] ?? '') !== $TOKEN) {
    http_response_code(403);
    exit('Forbidden');
}

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(1800);
ini_set('memory_limit', '512M');

try {
    require __DIR__ . '/../vendor/autoload.php';
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
} catch (Throwable $e) {
    echo "BOOTSTRAP FAILED\n\n" . $e->getMessage() . "\n\n" . $e->getTraceAsString();
    exit;
}

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

$step = $_GET['step'] ?? 'check';

try {
    switch ($step) {

        case 'check':
            DB::connection()->getPdo();
            echo "DB connection: OK\n";
            echo "Database: " . DB::connection()->getDatabaseName() . "\n";
            echo "Driver:   " . DB::connection()->getDriverName() . "\n";
            echo "Version:  " . DB::selectOne('select version() as v')->v . "\n";
            echo "Engine setting: " . var_export(config('database.connections.mysql.engine'), true) . "\n";
            echo "PHP:      " . PHP_VERSION . "\n";
            echo "Laravel:  " . app()->version() . "\n";
            break;

        case 'fresh':
            // Drops every table, then re-runs all migrations from scratch.
            Artisan::call('migrate:fresh', ['--force' => true]);
            echo Artisan::output();
            echo "\nDone. Next: &step=seed\n";
            break;

        case 'migrate':
            Artisan::call('migrate', ['--force' => true]);
            echo Artisan::output();
            echo "\nDone. Next: &step=seed\n";
            break;

        case 'seed':
            Artisan::call('db:seed', ['--force' => true]);
            echo Artisan::output();
            echo "\nDone. Next: &step=admin&email=you@example.com&password=YourPassword&name=Emerson\n";
            break;

        case 'seed-categories':
            // Backfills the subcategory taxonomy (mega menu / search
            // sidebar feature) onto an already-live database without
            // re-running the full `db:seed` — CategorySeeder itself
            // handles both the 13 top-level categories and their ~76
            // children, keyed by name so it's safe to run any number of
            // times (updateOrCreate, never duplicates or touches
            // unrelated tables).
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\CategorySeeder', '--force' => true]);
            echo Artisan::output();
            echo "\nDone.\n";
            break;

        case 'demo-seed':
            // Realistic Tanzanian demo content (12 shops, 60 products, orders,
            // reviews, offers) for client demos — see database/seeders/DemoSeeder.php.
            //
            // Calls the seeder class directly (App\Console\Commands\SeedDemoContent,
            // the `php artisan demo:seed` wrapper, isn't on this server). DemoSeeder
            // itself takes no --fresh flag or any other input — all the "clear
            // previously seeded demo data first" logic lived in that missing
            // command class, so it's reproduced inline here instead: delete only
            // the fixed, hardcoded demo shop handles and buyer phone numbers
            // DemoSeeder itself exposes via demoHandles()/demoBuyerPhones() — the
            // entire identification mechanism (there's no is_demo column), so a
            // real account can never match it. Every relevant foreign key is
            // ON DELETE CASCADE, so deleting just these seller_profiles/users
            // rows removes everything downstream (products, media, offers,
            // showcases, updates, reviews, comments, orders, order items) too.
            if (($_GET['fresh'] ?? '') === '1') {
                $handles = Database\Seeders\DemoSeeder::demoHandles();
                $phones = Database\Seeders\DemoSeeder::demoBuyerPhones();

                $sellerUserIds = App\Models\SellerProfile::whereIn('handle', $handles)->pluck('user_id');
                $removedShops = App\Models\SellerProfile::whereIn('handle', $handles)->delete();
                App\Models\User::whereIn('id', $sellerUserIds)->delete();

                $removedBuyers = App\Models\User::whereIn('phone', $phones)->count();
                App\Models\User::whereIn('phone', $phones)->delete();

                echo "Cleared {$removedShops} prior demo shop(s) and {$removedBuyers} prior demo buyer(s).\n\n";
            }

            // Part 2 (client feedback): "provide a way to force-regenerate
            // every existing file, not only mismatched ones" — a plain
            // re-seed already replaces anything whose content has
            // drifted from what the generator currently produces (see
            // DemoSeeder::ensureFileExists); &force-media=1 bypasses that
            // comparison entirely, for when a stale *cached* copy (the
            // uploads disk serves with a 7-day Cache-Control) is
            // suspected rather than a stale file on disk.
            Database\Seeders\DemoSeeder::$forceMediaRegen = ($_GET['force-media'] ?? '') === '1';

            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DemoSeeder', '--force' => true]);
            echo Artisan::output();

            Database\Seeders\DemoSeeder::$forceMediaRegen = false;

            echo "\nDone.\n";
            break;

        case 'cleanup-original-seed':
            // Removes ONLY the original Phase 2 Faker DatabaseSeeder content
            // (20 sellers / ~120 products / 70 orders / 60 reviews) that's
            // still live in production alongside the newer, real-sounding
            // DemoSeeder shops — reported to be outranking them on the home
            // feed. Positive identification only, never exclusion: a
            // seller_profile is touched here if and only if BOTH its
            // nida_image AND licence_file paths start with the literal
            // "seed/" prefix SellerProfileFactory hardcodes
            // ('seed/nida/{uuid}.jpg', 'seed/licences/{uuid}.pdf') — grepped
            // across the whole app to confirm nothing else ever produces
            // that prefix. Every other path that can write these two
            // columns uses a different, non-overlapping prefix: real
            // sellers (app or web onboarding, SellerProfileController /
            // SellerRegistrationController) always store under
            // "sellers/nida/"+"sellers/licences/", and DemoSeeder always
            // stores under "demo/nida/"+"demo/licences/". A real seller or
            // a DemoSeeder shop can never match this filter, by construction
            // — this is never "not in the demo list" or an id-range guess,
            // either of which could accidentally catch a real account.
            //
            // Defaults to a dry run — lists exactly what was matched and
            // the counts that would be deleted, deletes nothing. Add
            // &confirm=1 once you've reviewed that list to actually run it.
            //
            // Deletes via the seller's `users` row, not the seller_profile
            // row directly: seller_profiles.user_id is itself
            // ON DELETE CASCADE from users, and every table below it
            // (products, product_media, orders, order_items, reviews,
            // comments, conversations, messages, favorites, customers,
            // updates, offers, showcases, leads) cascades transitively from
            // there via real database foreign keys — not application code
            // walking each table by hand. Wrapped in a transaction so a
            // failure partway through rolls back rather than leaving a
            // half-deleted mess.
            $demoHandles = Database\Seeders\DemoSeeder::demoHandles();

            $candidates = App\Models\SellerProfile::query()
                ->where('nida_image', 'like', 'seed/nida/%')
                ->where('licence_file', 'like', 'seed/licences/%')
                ->get(['id', 'user_id', 'handle', 'shop_name', 'status']);

            // Belt-and-suspenders: the prefix match above should already
            // make this impossible, but refuse outright rather than ever
            // proceed if a candidate also happens to carry a real
            // DemoSeeder handle or belong to an admin account.
            $overlapWithDemo = $candidates->pluck('handle')->intersect($demoHandles);
            $adminUserIds = App\Models\User::where('is_admin', true)->pluck('id');
            $overlapWithAdmin = $candidates->pluck('user_id')->intersect($adminUserIds);

            if ($overlapWithDemo->isNotEmpty() || $overlapWithAdmin->isNotEmpty()) {
                echo "REFUSING TO RUN — safety check failed, nothing was touched.\n";
                echo "Overlap with DemoSeeder handles: " . $overlapWithDemo->implode(', ') . "\n";
                echo "Overlap with admin user ids: " . $overlapWithAdmin->implode(', ') . "\n";
                break;
            }

            if ($candidates->isEmpty()) {
                echo "No original-seed shops found (none match nida_image/licence_file both under seed/). Nothing to do.\n";
                break;
            }

            $sellerIds = $candidates->pluck('id');
            $productIds = App\Models\Product::whereIn('seller_id', $sellerIds)->pluck('id');

            echo "Found {$candidates->count()} original-seed shop(s):\n\n";
            foreach ($candidates as $c) {
                $productCount = App\Models\Product::where('seller_id', $c->id)->count();
                $orderCount = App\Models\Order::where('seller_id', $c->id)->count();
                $reviewCount = App\Models\Review::where('seller_id', $c->id)->count();
                echo "  #{$c->id}  @{$c->handle}  \"{$c->shop_name}\"  ({$c->status})  -- {$productCount} products, {$orderCount} orders, {$reviewCount} reviews\n";
            }

            $mediaCount = App\Models\ProductMedia::whereIn('product_id', $productIds)->count();
            $commentCount = App\Models\Comment::whereIn('product_id', $productIds)->count();
            $orderTotal = App\Models\Order::whereIn('seller_id', $sellerIds)->count();
            $reviewTotal = App\Models\Review::whereIn('seller_id', $sellerIds)->count();

            echo "\nTotals: {$candidates->count()} shops, {$productIds->count()} products, "
                . "{$mediaCount} media items, {$orderTotal} orders, {$reviewTotal} reviews, {$commentCount} comments.\n";

            if (($_GET['confirm'] ?? '') !== '1') {
                echo "\nDRY RUN ONLY -- nothing was deleted.\n";
                echo "Re-run with &confirm=1 to actually delete everything listed above.\n";
                break;
            }

            $userIds = $candidates->pluck('user_id');
            $deleted = DB::transaction(fn () => App\Models\User::whereIn('id', $userIds)->delete());

            echo "\nDeleted {$deleted} seller account(s) and everything that cascaded from them.\n";
            break;

        case 'rewrite-media-host':
            // PUBLIC_UPLOADS_URL recently changed from the beta host to the
            // root domain (tester feedback B1) — but every media path is
            // stored as a FULL absolute URL baked in at upload time, not
            // computed dynamically at read time, so every row uploaded
            // before the change keeps the OLD host forever regardless of
            // what .env says now. This is a one-time data fix, not a code
            // fix: a plain string replace across every column that stores
            // an absolute media URL. Requires explicit &from=&to= (both
            // URL-encoded) rather than a hardcoded guess — a wrong string
            // replace across production media URLs is exactly the kind of
            // mistake that's expensive to get wrong silently.
            $from = $_GET['from'] ?? '';
            $to = $_GET['to'] ?? '';

            if ($from === '' || $to === '') {
                echo "Provide &from=<old host, e.g. https%3A%2F%2Fbeta.sokoni.co.tz%2Fuploads>";
                echo " and &to=<new host, e.g. https%3A%2F%2Fsokoni.co.tz%2Fuploads> (both URL-encoded).\n";
                break;
            }

            $targets = [
                ['table' => 'product_media', 'columns' => ['path', 'thumb_path', 'card_path']],
                ['table' => 'seller_profiles', 'columns' => ['logo']],
                ['table' => 'users', 'columns' => ['avatar']],
                ['table' => 'updates', 'columns' => ['media_path', 'thumb_path']],
                ['table' => 'showcases', 'columns' => ['video_path', 'thumb_path']],
                ['table' => 'messages', 'columns' => ['attachment']],
            ];

            $confirm = ($_GET['confirm'] ?? '') === '1';
            $totalMatched = 0;

            foreach ($targets as $target) {
                foreach ($target['columns'] as $column) {
                    $matched = DB::table($target['table'])->where($column, 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $from).'%')->count();
                    $totalMatched += $matched;

                    if ($matched === 0) {
                        continue;
                    }

                    echo "{$target['table']}.{$column}: {$matched} row(s) contain the old host.\n";

                    if ($confirm) {
                        $updated = DB::table($target['table'])
                            ->where($column, 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $from).'%')
                            ->update([$column => DB::raw("REPLACE({$column}, ".DB::connection()->getPdo()->quote($from).', '.DB::connection()->getPdo()->quote($to).')')]);
                        echo "  -> rewrote {$updated} row(s).\n";
                    }
                }
            }

            if ($totalMatched === 0) {
                echo "No rows anywhere contain \"{$from}\". Nothing to do.\n";
            } elseif (! $confirm) {
                echo "\nDRY RUN ONLY -- {$totalMatched} row(s) total would be rewritten. Add &confirm=1 to actually rewrite them.\n";
            } else {
                echo "\nDone. {$totalMatched} row(s) matched and were rewritten.\n";
            }
            break;

        case 'diagnose-assets':
            // Part 1 (client feedback, urgent): "the site renders
            // unstyled for first-time visitors" — Laravel's @vite
            // directive builds asset URLs from manifest.json under
            // public_path('build') (the APP ROOT's own public/ folder,
            // wherever this PHP process actually boots Laravel from),
            // but the browser fetches those URLs from wherever THIS
            // file physically sits — the real docroot, a separate
            // physical folder on this shared host. If a deploy ever
            // updates one copy of build/ without the other, the manifest
            // keeps generating a filename that simply doesn't exist
            // where the browser looks. This is that exact check, one
            // request instead of a guessing game — and __DIR__ below is
            // used rather than a hardcoded docroot path specifically
            // because that path has already moved once (beta.sokoni.co.tz
            // -> public_html); this keeps working regardless of where
            // setup.php itself is uploaded.
            $manifestPath = public_path('build/manifest.json');

            if (! is_file($manifestPath)) {
                echo "!! manifest.json NOT FOUND at {$manifestPath}\n";
                echo "The app root's own public/build/ is missing or was never deployed there.\n";
                break;
            }

            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (! is_array($manifest)) {
                echo "!! manifest.json exists but failed to parse as JSON: {$manifestPath}\n";
                break;
            }

            echo "App root manifest:                {$manifestPath}\n";
            echo "Docroot (this file's own folder):  " . __DIR__ . "\n\n";

            $referenced = [];
            foreach ($manifest as $entry) {
                if (isset($entry['file'])) {
                    $referenced[] = $entry['file'];
                }
                foreach (($entry['css'] ?? []) as $cssFile) {
                    $referenced[] = $cssFile;
                }
            }
            $referenced = array_unique($referenced);
            sort($referenced);

            $mismatches = 0;
            foreach ($referenced as $file) {
                $appRootPath = public_path('build/' . $file);
                $docrootPath = __DIR__ . '/build/' . $file;
                $inAppRoot = is_file($appRootPath);
                $inDocroot = is_file($docrootPath);
                $ok = $inAppRoot && $inDocroot;
                if (! $ok) {
                    $mismatches++;
                }

                echo ($ok ? 'OK       ' : 'MISMATCH ') . $file . "\n";
                echo '  app root: ' . ($inAppRoot ? 'present' : 'MISSING') . "  -- {$appRootPath}\n";
                echo '  docroot:  ' . ($inDocroot ? 'present' : 'MISSING') . "  -- {$docrootPath}\n";
            }

            echo "\n--- What's actually sitting in each build/assets/ folder ---\n";
            echo "App root (" . public_path('build/assets') . "):\n";
            foreach (glob(public_path('build/assets') . '/*') ?: [] as $f) {
                echo '  ' . basename($f) . "\n";
            }
            echo "Docroot (" . __DIR__ . '/build/assets' . "):\n";
            foreach (glob(__DIR__ . '/build/assets/*') ?: [] as $f) {
                echo '  ' . basename($f) . "\n";
            }

            // Second named hypothesis: a malformed generated URL (a
            // recent bug had PUBLIC_UPLOADS_URL containing a duplicated
            // "https:" prefix -- "https:https://...").
            echo "\n--- URL sanity (APP_URL / ASSET_URL / PUBLIC_UPLOADS_URL) ---\n";
            $urlSettings = [
                'APP_URL' => config('app.url'),
                'ASSET_URL' => config('app.asset_url'),
                'PUBLIC_UPLOADS_URL (filesystems.disks.public.url)' => config('filesystems.disks.public.url'),
            ];
            foreach ($urlSettings as $name => $value) {
                $issues = [];
                if ($value === null || $value === '') {
                    // ASSET_URL is genuinely optional (Laravel falls back
                    // to APP_URL) — only flag emptiness as a real problem
                    // for the setting that actually has to be set.
                    if (str_starts_with($name, 'APP_URL')) {
                        $issues[] = 'empty';
                    }
                } else {
                    if (preg_match('#https?://.*https?://#', (string) $value)) {
                        $issues[] = 'DUPLICATED SCHEME';
                    }
                    if (str_ends_with((string) $value, '/')) {
                        $issues[] = 'trailing slash';
                    }
                    if ($value !== trim((string) $value)) {
                        $issues[] = 'leading/trailing whitespace';
                    }
                }
                $suffix = $issues === [] ? '  OK' : '  !! ' . implode(', ', $issues);
                if ($value === null && $name === 'ASSET_URL') {
                    $suffix = '  (not set — defaults to APP_URL, fine)';
                }
                echo "{$name}: " . var_export($value, true) . $suffix . "\n";
            }

            // A real, live-generated Vite URL, checked the same way —
            // catches a malformed ASSET_URL even if the raw config value
            // alone looks clean but the concatenation Vite performs
            // doesn't.
            $sampleUrl = (string) \Illuminate\Support\Facades\Vite::asset('resources/css/app.css');
            $sampleIssue = preg_match('#https?://.*https?://#', $sampleUrl) ? '  !! DUPLICATED SCHEME' : '  OK';
            echo "\nLive Vite::asset('resources/css/app.css') URL: {$sampleUrl}{$sampleIssue}\n";

            echo "\n" . ($mismatches === 0
                ? 'RESULT: every manifest-referenced asset is present in BOTH build/ folders. The unstyled-page symptom is not currently reproducible from disk state.'
                : "RESULT: {$mismatches} asset(s) referenced by the manifest are MISSING from at least one of the two build/ folders — this is exactly the unstyled-page symptom. Re-deploy this round's public-docroot zip (or the app-root zip) so both folders agree.") . "\n";
            break;

        case 'diagnose-banners':
            // Part 1 (client feedback, urgent): "an uploaded banner never
            // appears — still shows loading, 24+ hours later." One
            // request answering every question the task lists rather
            // than guessing: is the file on disk, is it a display
            // problem or an upload problem, is the banner actually just
            // scheduled/inactive without the admin UI saying so, and
            // does this host's own upload_max_filesize/post_max_size
            // leave any room for what the admin form's own maxSize()
            // allows through client-side.
            // PHP's own ini shorthand (e.g. "2M", "512K", "1G", or a bare
            // byte count) — parsed properly rather than guessed at, since
            // getting this wrong would make the one check that matters
            // here unreliable.
            $parseIniBytes = function (string $value): int {
                $value = trim($value);
                if ($value === '') {
                    return 0;
                }
                $unit = strtolower(substr($value, -1));
                $number = (int) $value;

                return match ($unit) {
                    'g' => $number * 1073741824,
                    'm' => $number * 1048576,
                    'k' => $number * 1024,
                    default => (int) $value,
                };
            };

            $uploadMax = ini_get('upload_max_filesize');
            $postMax = ini_get('post_max_size');
            $memoryLimit = ini_get('memory_limit');
            echo "PHP upload_max_filesize: {$uploadMax}\n";
            echo "PHP post_max_size:       {$postMax}\n";
            echo "PHP memory_limit:        {$memoryLimit}\n";
            echo "Filament form's own client-side cap (BannerForm::maxSize): 2048 KB (2MB)\n";

            $uploadMaxBytes = $parseIniBytes((string) $uploadMax);
            $postMaxBytes = $parseIniBytes((string) $postMax);
            $formCapBytes = 2048 * 1024;
            // <= , not just < — multipart encoding overhead (field
            // boundaries, the rest of the form's own fields in the same
            // POST body) means a file just under the form's own 2MB cap
            // can still push the total request over an ini limit set to
            // exactly the same number.
            if ($uploadMaxBytes > 0 && $uploadMaxBytes <= $formCapBytes) {
                echo "!! upload_max_filesize ({$uploadMax}) leaves little to no headroom over the form's own 2MB cap — multipart overhead alone can push a borderline upload over this limit, and PHP rejects it before Laravel ever sees the request. This alone would explain \"stuck on loading, nothing ever saves.\"\n";
            }
            if ($postMaxBytes > 0 && $postMaxBytes <= $formCapBytes) {
                echo "!! post_max_size ({$postMax}) leaves little to no headroom over the form's own 2MB cap — same failure mode as above, just the other ini setting.\n";
            }
            echo "\n";

            $disk = config('filesystems.disks.public.root');
            $diskUrl = rtrim((string) config('filesystems.disks.public.url'), '/');
            echo "PUBLIC_UPLOADS_ROOT: {$disk}\n";
            echo "PUBLIC_UPLOADS_URL:  {$diskUrl}\n\n";

            $banners = DB::table('banners')->orderByDesc('id')->get();
            if ($banners->isEmpty()) {
                echo "No banners exist at all.\n";
                break;
            }

            $now = now();
            foreach ($banners as $banner) {
                $relative = str_starts_with($banner->image_path, $diskUrl)
                    ? ltrim(substr($banner->image_path, strlen($diskUrl)), '/')
                    : null;
                $path = $relative ? rtrim((string) $disk, '/') . '/' . $relative : null;
                $exists = $path && is_file($path);
                $size = $exists ? filesize($path) : null;

                $scheduleNotes = [];
                if (! $banner->is_active) {
                    $scheduleNotes[] = 'is_active = false (hidden regardless of everything else)';
                }
                if ($banner->starts_at && \Illuminate\Support\Carbon::parse($banner->starts_at)->isFuture()) {
                    $scheduleNotes[] = "starts_at is in the future ({$banner->starts_at}) — not live yet";
                }
                if ($banner->ends_at && \Illuminate\Support\Carbon::parse($banner->ends_at)->isPast()) {
                    $scheduleNotes[] = "ends_at is in the past ({$banner->ends_at}) — expired";
                }
                $live = $scheduleNotes === [];

                echo "#{$banner->id}  \"{$banner->title}\"  position={$banner->position}\n";
                echo "  stored path:  {$banner->image_path}\n";
                echo '  resolved disk path: ' . ($path ?? '(could not resolve — stored URL does not start with the configured PUBLIC_UPLOADS_URL)') . "\n";
                echo '  file exists:  ' . ($exists ? "YES ({$size} bytes)" : 'NO — the upload never reached disk, or was moved/deleted since') . "\n";
                echo '  scheduling:   ' . ($live ? 'live now (is_active=true, within any start/end window)' : implode('; ', $scheduleNotes)) . "\n";
                echo "\n";
            }
            break;

        case 'diagnose-media':
            // B1 (tester feedback): "diagnose before changing code" — this
            // answers, for a real sample of product_media rows, the exact
            // question the URL-rewrite tool above can't: is the *file*
            // actually sitting where the *current* config says to look for
            // it, regardless of what the stored URL says? Two things can
            // independently be wrong (a stale URL baked into the row, and
            // the physical file living in the wrong of two known upload
            // folders on this account), and only checking the URL string
            // (rewrite-media-host) can't tell them apart. Deliberately
            // reads real file_exists() results against real paths on this
            // exact server, not a guess from config values alone.
            // Part A (client feedback): bumped the default sample and the
            // per-row report to also cover extension, size, and upload
            // date — "some images work and some don't" needs grouping by
            // those axes to say *which* rows fail and why, not just a
            // pass/fail count.
            $sample = max(1, min(200, (int) ($_GET['sample'] ?? 50)));

            $currentRoot = rtrim(config('filesystems.disks.public.root'), '/');
            $currentUrl = rtrim(config('filesystems.disks.public.url'), '/');
            echo "Current PUBLIC_UPLOADS_ROOT (filesystems.disks.public.root): {$currentRoot}\n";
            echo "Current PUBLIC_UPLOADS_URL  (filesystems.disks.public.url):  {$currentUrl}\n\n";

            // Both physical locations the client named, checked regardless
            // of what's currently configured — this is what actually
            // answers "is this a path problem or a code problem."
            $knownRoots = [
                'public_html/uploads' => '/home/sokoftsn/public_html/uploads',
                'beta.sokoni.co.tz/uploads' => '/home/sokoftsn/beta.sokoni.co.tz/uploads',
            ];

            $rows = DB::table('product_media')->orderByDesc('id')->limit($sample)->get(['id', 'product_id', 'path', 'created_at']);

            if ($rows->isEmpty()) {
                echo "No product_media rows exist at all.\n";
                break;
            }

            $tally = array_fill_keys(array_keys($knownRoots), 0);
            $tally['configured_root'] = 0;
            $tally['found_nowhere'] = 0;

            // Grouping buckets for the summary — keyed by the axis value,
            // each holding [total, missing] so a share-of-failures per
            // group is visible directly, not just a raw count.
            $byExtension = [];
            $byDate = [];
            $bySource = [];
            $byDirectory = [];

            $bump = function (array &$bucket, string $key, bool $missing): void {
                $bucket[$key] ??= ['total' => 0, 'missing' => 0];
                $bucket[$key]['total']++;
                if ($missing) {
                    $bucket[$key]['missing']++;
                }
            };

            foreach ($rows as $row) {
                // Every stored URL is {some host}/uploads/{relative path} —
                // the "/uploads/" segment is the one constant across every
                // host this value has ever been baked in under (root
                // domain, beta subdomain, local dev), so anchoring on it
                // (rather than assuming today's config's own host matches
                // what's actually stored) is what makes this work no
                // matter how stale the row's own URL is.
                if (! preg_match('#/uploads/(.+)$#', $row->path, $m)) {
                    echo "#{$row->id} (product {$row->product_id}): stored path has no /uploads/ segment at all -- {$row->path}\n";
                    continue;
                }
                $relative = $m[1];
                $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION)) ?: '(none)';
                // Real uploads always land under "products/{id}/..."
                // (ImageVariants::generate's own directory convention);
                // everything the demo seeder writes lives under "demo/..."
                // instead — that prefix alone tells seeded and
                // user-uploaded rows apart without guessing.
                $source = str_starts_with($relative, 'demo/') ? 'seeded (demo/)' : 'user-uploaded';
                $uploadDate = $row->created_at ? substr($row->created_at, 0, 10) : '(no timestamp)';

                echo "#{$row->id} (product {$row->product_id})\n";
                echo "  stored URL:    {$row->path}\n";
                echo "  extension:     {$extension}\n";
                echo "  source:        {$source}\n";
                echo "  uploaded:      {$uploadDate}\n";

                $configuredPath = $currentRoot . '/' . $relative;
                $configuredExists = is_file($configuredPath);
                $configuredSize = $configuredExists ? filesize($configuredPath) : null;
                echo '  configured disk path: ' . $configuredPath . ' -- '
                    . ($configuredExists ? "EXISTS ({$configuredSize} bytes)" : 'missing') . "\n";
                if ($configuredExists) {
                    $tally['configured_root']++;
                }

                $foundAnywhere = $configuredExists;
                $foundUnderLabel = $configuredExists ? 'configured_root' : null;
                foreach ($knownRoots as $label => $root) {
                    $path = $root . '/' . $relative;
                    $exists = is_file($path);
                    $size = $exists ? filesize($path) : null;
                    echo "  {$label}: {$path} -- " . ($exists ? "EXISTS ({$size} bytes)" : 'missing') . "\n";
                    if ($exists) {
                        $tally[$label]++;
                        $foundAnywhere = true;
                        $foundUnderLabel ??= $label;
                    }
                }
                $missing = ! $foundAnywhere;
                if ($missing) {
                    $tally['found_nowhere']++;
                    echo "  !! NOT FOUND in the configured root or either known upload folder.\n";
                }
                echo "\n";

                $bump($byExtension, $extension, $missing);
                $bump($byDate, $uploadDate, $missing);
                $bump($bySource, $source, $missing);
                $bump($byDirectory, $foundUnderLabel ?? 'nowhere', $missing);
            }

            echo "--- Summary over {$rows->count()} sampled row(s) ---\n";
            echo "Resolves via the currently configured root: {$tally['configured_root']}\n";
            foreach ($knownRoots as $label => $root) {
                echo "Physically present under {$label}: {$tally[$label]}\n";
            }
            echo "Found in neither known location: {$tally['found_nowhere']}\n";

            $printGroup = function (string $title, array $bucket): void {
                echo "\n--- By {$title} ---\n";
                ksort($bucket);
                foreach ($bucket as $key => $counts) {
                    echo "  {$key}: {$counts['missing']} missing / {$counts['total']} total\n";
                }
            };
            $printGroup('extension', $byExtension);
            $printGroup('upload date', $byDate);
            $printGroup('source', $bySource);
            $printGroup('directory (where it was actually found, if anywhere)', $byDirectory);
            break;

        case 'test-mail':
            // B7 (tester feedback): "SMTP failures are silent by default"
            // — SafeMail already catches and logs every real send failure
            // (see app/Support/SafeMail.php), but that only helps once
            // something has already tried to send through the app's own
            // flows. This sends one real message right now, on this
            // request, and prints the exact underlying exception if it
            // fails — connection refused, auth rejected, TLS handshake
            // failure, whatever it actually is — rather than a generic
            // "didn't arrive" with no further signal. Also prints the
            // resolved mail config so a wrong host/port/encryption value
            // is visible without needing shell access to read .env.
            $to = $_GET['to'] ?? '';
            if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
                echo "Provide &to=<a real email address you can check>.\n";
                break;
            }

            echo "MAIL_MAILER:     " . config('mail.default') . "\n";
            echo "MAIL_HOST:       " . config('mail.mailers.smtp.host') . "\n";
            echo "MAIL_PORT:       " . config('mail.mailers.smtp.port') . "\n";
            echo "MAIL_ENCRYPTION: " . (config('mail.mailers.smtp.scheme') ?: '(none)') . "\n";
            echo "MAIL_USERNAME:   " . (config('mail.mailers.smtp.username') ? '(set)' : '(EMPTY -- likely the actual problem)') . "\n";
            echo "MAIL_FROM:       " . config('mail.from.address') . "\n\n";

            if (config('mail.default') !== 'smtp') {
                echo "MAIL_MAILER is \"" . config('mail.default') . "\", not \"smtp\" -- no real email will ever leave this server while that's the case, regardless of whether the SMTP fields above are correct. This alone would explain \"nothing arrives, no error\" exactly.\n\n";
            }

            try {
                \Illuminate\Support\Facades\Mail::raw(
                    'This is a test message from setup.php?step=test-mail, sent at ' . now()->toDateTimeString() . '.',
                    fn ($message) => $message->to($to)->subject('Sokoni setup.php test email')
                );
                echo "SENT -- no exception was thrown. Check the inbox at {$to} (and spam folder). If it still never arrives despite this succeeding, the problem is downstream of this server (the receiving mail server rejecting/silently dropping it), not this app's own mail sending.\n";
            } catch (Throwable $e) {
                echo "FAILED -- " . get_class($e) . ": " . $e->getMessage() . "\n\n";
                echo "This is the exact reason no verification email (or any other transactional email) is reaching anyone right now.\n";
            }
            break;

        case 'admin':
            $email    = $_GET['email'] ?? '';
            $password = $_GET['password'] ?? '';
            $name     = $_GET['name'] ?? 'Admin';

            if ($email === '' || strlen($password) < 8) {
                echo "Provide &email= and &password= (at least 8 characters).\n";
                break;
            }

            $user = App\Models\User::firstOrNew(['email' => $email]);
            $user->forceFill([
                'name'              => $name,
                'password'          => Hash::make($password),
                'is_admin'          => true,
                'email_verified_at' => now(),
            ])->save();

            echo "Admin ready: {$email} (id {$user->id})\n";
            echo "Log in at https://api.sokoni.co.tz/admin\n";
            echo "\nNow DELETE setup.php from public/.\n";
            break;

        case 'tables':
            foreach (DB::select('show tables') as $row) {
                echo implode('', (array) $row) . "\n";
            }
            break;

        case 'cache':
            Artisan::call('config:cache');
            echo Artisan::output();
            Artisan::call('route:cache');
            echo Artisan::output();
            echo "\nCaches rebuilt.\n";
            break;

        case 'clear':
            Artisan::call('optimize:clear');
            echo Artisan::output();
            echo "\nCaches cleared.\n";
            break;

        default:
            echo "Unknown step. Use: check, fresh, migrate, seed, seed-categories, demo-seed, cleanup-original-seed, rewrite-media-host, diagnose-assets, diagnose-banners, diagnose-media, test-mail, admin, tables, cache, clear\n";
            echo "diagnose-assets compares manifest.json's referenced asset filenames against what's actually on disk in both the app root's and the docroot's own build/ folders, and checks APP_URL/ASSET_URL/PUBLIC_UPLOADS_URL for a duplicated scheme or other malformation.\n";
            echo "diagnose-banners lists every banner with its stored path, whether the file exists on disk and its size, its active/scheduled state, and this host's own upload_max_filesize/post_max_size against the admin form's 2MB client-side cap.\n";
            echo "demo-seed accepts &fresh=1 to clear previously seeded demo shops/buyers first, and &force-media=1 to regenerate every placeholder image file unconditionally (not just ones whose content has drifted).\n";
            echo "cleanup-original-seed is a dry run by default; add &confirm=1 to actually delete.\n";
            echo "rewrite-media-host needs &from=&to= (URL-encoded); dry run by default, add &confirm=1 to rewrite.\n";
            echo "diagnose-media accepts &sample=N (default 50, max 200) -- prints stored URL, resolved disk path, file size, extension, upload date and source (seeded vs user-uploaded) for each, against both known upload folders, then groups the results by each of those axes.\n";
            echo "test-mail needs &to=<email> -- sends one real email right now and prints the exact SMTP exception if it fails, plus the resolved mail config.\n";
    }
} catch (Throwable $e) {
    echo "ERROR on step '{$step}'\n\n";
    echo $e->getMessage() . "\n\n";
    echo $e->getTraceAsString();
}
