<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\Report;
use App\Models\Review;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed demo data per CLAUDE.md Phase 2: 10 categories, 20 sellers,
     * 120 products with placeholder media, and 60 reviews.
     *
     * Order count is 70, not the spec's literal 40: 60 reviews require 60
     * *unique* completed orders (reviews.order_id is unique — the schema's
     * entire defence against fake reviews, per CLAUDE.md feature 2), which
     * 40 total orders can't supply alongside a realistic status mix. See
     * DECISIONS.md.
     */
    public function run(): void
    {
        $this->call(CategorySeeder::class);

        User::factory()->admin()->create([
            'name' => 'Sokoni Admin',
            'email' => 'admin@sokoni.co.tz',
        ]);

        $categoryIds = Category::pluck('id')->all();

        // 20 sellers: 14 verified, 4 pending, 2 rejected.
        $sellerCategory = fn () => ['category_id' => fake()->randomElement($categoryIds)];
        $sellers = collect()
            ->concat(SellerProfile::factory(14)->verified()->state($sellerCategory)->create())
            ->concat(SellerProfile::factory(4)->state($sellerCategory)->create())
            ->concat(SellerProfile::factory(2)->rejected()->state($sellerCategory)->create());

        // 120 products spread across the 20 sellers, each with 1-8 media items.
        $products = collect();
        foreach ($sellers as $seller) {
            $count = (int) floor(120 / $sellers->count());
            $products = $products->concat(
                Product::factory($count)
                    ->state(fn () => ['category_id' => fake()->randomElement($categoryIds)])
                    ->create(['seller_id' => $seller->id])
            );
        }
        // Top up to exactly 120 if the even split left a remainder.
        while ($products->count() < 120) {
            $seller = $sellers->random();
            $products->push(
                Product::factory()
                    ->state(['category_id' => fake()->randomElement($categoryIds)])
                    ->create(['seller_id' => $seller->id])
            );
        }

        foreach ($products as $product) {
            $mediaCount = fake()->numberBetween(1, 8);
            $hasVideo = fake()->boolean(20);
            for ($i = 0; $i < $mediaCount; $i++) {
                $isLastAndVideo = $hasVideo && $i === $mediaCount - 1;
                ProductMedia::factory()
                    ->when($isLastAndVideo, fn ($f) => $f->video())
                    ->create(['product_id' => $product->id, 'sort' => $i]);
            }
        }

        $buyers = User::factory(40)->create();

        // 70 orders: 60 completed (feeding the 60 reviews below) + 10 spread
        // across the other statuses for a realistic dashboard/timeline demo.
        $statusFactories = [
            ...array_fill(0, 60, 'completed'),
            ...array_fill(0, 4, 'pending'),
            ...array_fill(0, 2, 'accepted'),
            ...array_fill(0, 2, 'ready'),
            ...array_fill(0, 2, 'cancelled'),
        ];

        $completedOrders = collect();

        foreach ($statusFactories as $status) {
            $seller = $sellers->random();
            $sellerProducts = $products->where('seller_id', $seller->id);
            if ($sellerProducts->isEmpty()) {
                continue;
            }

            $factory = $status === 'pending' ? Order::factory() : Order::factory()->{$status}();
            $order = $factory->create([
                'buyer_id' => $buyers->random()->id,
                'seller_id' => $seller->id,
            ]);

            $items = $sellerProducts->random(min(fake()->numberBetween(1, 3), $sellerProducts->count()));
            $subtotal = 0;
            foreach ($items as $item) {
                $qty = fake()->numberBetween(1, 3);
                OrderItem::factory()->create([
                    'order_id' => $order->id,
                    'product_id' => $item->id,
                    'title_snapshot' => $item->title,
                    'price_snapshot' => $item->price,
                    'qty' => $qty,
                ]);
                $subtotal += $item->price * $qty;
            }
            $order->update([
                'subtotal' => $subtotal,
                'total' => $subtotal + $order->delivery_fee,
            ]);

            if ($status === 'completed') {
                $completedOrders->push($order);
            }
        }

        // 60 reviews, one per completed order, ~50% with a seller reply.
        foreach ($completedOrders as $order) {
            Review::factory()
                ->when(fake()->boolean(50), fn ($f) => $f->withReply())
                ->create([
                    'order_id' => $order->id,
                    'seller_id' => $order->seller_id,
                    'buyer_id' => $order->buyer_id,
                ]);
        }

        // A handful of reports so the Filament moderation queue (Phase 9)
        // isn't empty on a fresh seed: 5 pending (the actual queue), 2
        // upheld against the same product (below the 3-strike auto-hide
        // threshold — see ReportObserver — so it stays a realistic "being
        // watched" case rather than already-resolved), 1 dismissed.
        $reportTargets = [
            ...$products->random(6)->map(fn ($p) => ['type' => Product::class, 'id' => $p->id]),
            ...$sellers->random(2)->map(fn ($s) => ['type' => SellerProfile::class, 'id' => $s->id]),
        ];
        foreach (array_slice($reportTargets, 0, 5) as $target) {
            Report::factory()->create([
                'reporter_id' => $buyers->random()->id,
                'reportable_type' => $target['type'],
                'reportable_id' => $target['id'],
            ]);
        }
        $watchedProduct = $products->random();
        for ($i = 0; $i < 2; $i++) {
            Report::factory()->create([
                'reporter_id' => $buyers->random()->id,
                'reportable_type' => Product::class,
                'reportable_id' => $watchedProduct->id,
                'status' => 'upheld',
                'resolved_by' => User::where('is_admin', true)->value('id'),
                'resolution' => 'Upheld by admin review.',
            ]);
        }
        Report::factory()->create([
            'reporter_id' => $buyers->random()->id,
            'reportable_type' => Product::class,
            'reportable_id' => $products->random()->id,
            'status' => 'dismissed',
            'resolved_by' => User::where('is_admin', true)->value('id'),
            'resolution' => 'Dismissed — no action taken.',
        ]);
    }
}
