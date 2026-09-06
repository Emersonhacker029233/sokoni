<?php
/**
 * Sokoni one-time setup runner (v2).
 *
 * Upload to: /home/sokoftsn/sokoni-api/public/setup.php  (replace the old one)
 * DELETE THIS FILE as soon as setup is finished.
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

            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DemoSeeder', '--force' => true]);
            echo Artisan::output();
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
            echo "Unknown step. Use: check, fresh, migrate, seed, seed-categories, demo-seed, cleanup-original-seed, rewrite-media-host, admin, tables, cache, clear\n";
            echo "demo-seed accepts &fresh=1 to clear previously seeded demo shops/buyers first.\n";
            echo "cleanup-original-seed is a dry run by default; add &confirm=1 to actually delete.\n";
            echo "rewrite-media-host needs &from=&to= (URL-encoded); dry run by default, add &confirm=1 to rewrite.\n";
    }
} catch (Throwable $e) {
    echo "ERROR on step '{$step}'\n\n";
    echo $e->getMessage() . "\n\n";
    echo $e->getTraceAsString();
}
