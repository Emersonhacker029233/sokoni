<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Top-level categories per CLAUDE.md feature 9, updated per client
     * request (2026-09-03): "Agriculture" renamed to "Cereal & Legume",
     * "Construction & Hardware" shortened to "Hardware" (moved here from
     * DemoSeeder's own one-off creation, now that it's a permanent
     * category rather than demo-only), "Real Estate" added third, "Kids"
     * added. "Other" stays last regardless of insertion order here — see
     * the rename migration for how existing rows/product FKs survive
     * this without a fresh seed.
     */
    private const CATEGORIES = [
        ['name_en' => 'Electronics', 'name_sw' => 'Elektroniki', 'icon' => 'devices'],
        ['name_en' => 'Fashion', 'name_sw' => 'Mitindo', 'icon' => 'checkroom'],
        ['name_en' => 'Real Estate', 'name_sw' => 'Nyumba na Viwanja', 'icon' => 'home_work'],
        ['name_en' => 'Kids', 'name_sw' => 'Watoto', 'icon' => 'child_care'],
        ['name_en' => 'Food & Groceries', 'name_sw' => 'Chakula na Vyakula', 'icon' => 'restaurant'],
        ['name_en' => 'Home & Furniture', 'name_sw' => 'Nyumbani na Samani', 'icon' => 'chair'],
        ['name_en' => 'Beauty & Health', 'name_sw' => 'Urembo na Afya', 'icon' => 'spa'],
        ['name_en' => 'Phones & Accessories', 'name_sw' => 'Simu na Vifaa', 'icon' => 'smartphone'],
        ['name_en' => 'Vehicles & Parts', 'name_sw' => 'Magari na Vipuri', 'icon' => 'directions_car'],
        ['name_en' => 'Cereal & Legume', 'name_sw' => 'Nafaka na Mikunde', 'icon' => 'agriculture'],
        ['name_en' => 'Hardware', 'name_sw' => 'Vifaa vya Ujenzi', 'icon' => 'hardware'],
        ['name_en' => 'Services', 'name_sw' => 'Huduma', 'icon' => 'handyman'],
        ['name_en' => 'Other', 'name_sw' => 'Nyingine', 'icon' => 'more_horiz'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $index => $category) {
            Category::updateOrCreate(
                ['name_en' => $category['name_en']],
                [...$category, 'sort_order' => $index, 'is_active' => true]
            );
        }
    }
}
