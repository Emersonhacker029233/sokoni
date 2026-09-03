<?php

namespace App\Console\Commands;

use App\Models\SellerProfile;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

/**
 * `php artisan demo:seed` (optionally `--fresh`) — the only supported way
 * to run DemoSeeder with a clean slate. `db:seed` itself has no `--fresh`
 * option of its own (that belongs to `migrate:fresh`, a very different and
 * much more destructive operation), so this is a small dedicated command
 * rather than trying to bolt a custom flag onto the generic seeding
 * command.
 */
class SeedDemoContent extends Command
{
    protected $signature = 'demo:seed {--fresh : Remove any previously seeded demo shops and buyers first}';

    protected $description = 'Seed realistic Tanzanian demo content (shops, products, orders, reviews) for client demos.';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->clearPriorDemoData();
        }

        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        return self::SUCCESS;
    }

    /**
     * Deletes only the exact rows DemoSeeder itself created last time —
     * identified by the fixed, hardcoded handle/phone lists it exposes,
     * never a broader heuristic — so a real seller or buyer account can
     * never be caught by this, no matter how this command is invoked.
     * Every relevant foreign key cascades on delete (products, media,
     * offers, showcases, updates, reviews, comments, orders, order items),
     * so deleting the seller_profiles/users rows themselves is sufficient.
     */
    private function clearPriorDemoData(): void
    {
        $sellerUserIds = SellerProfile::whereIn('handle', DemoSeeder::demoHandles())->pluck('user_id');
        $removedShops = SellerProfile::whereIn('handle', DemoSeeder::demoHandles())->delete();
        User::whereIn('id', $sellerUserIds)->delete();

        $removedBuyers = User::whereIn('phone', DemoSeeder::demoBuyerPhones())->count();
        User::whereIn('phone', DemoSeeder::demoBuyerPhones())->delete();

        $this->info("Cleared {$removedShops} prior demo shop(s) and {$removedBuyers} prior demo buyer(s).");
    }
}
