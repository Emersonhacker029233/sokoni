<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /** The ten top-level categories from CLAUDE.md feature 9, EN + SW. */
    private const CATEGORIES = [
        ['name_en' => 'Electronics', 'name_sw' => 'Elektroniki', 'icon' => 'devices'],
        ['name_en' => 'Fashion', 'name_sw' => 'Mitindo', 'icon' => 'checkroom'],
        ['name_en' => 'Food & Groceries', 'name_sw' => 'Chakula na Vyakula', 'icon' => 'restaurant'],
        ['name_en' => 'Home & Furniture', 'name_sw' => 'Nyumbani na Samani', 'icon' => 'chair'],
        ['name_en' => 'Beauty & Health', 'name_sw' => 'Urembo na Afya', 'icon' => 'spa'],
        ['name_en' => 'Phones & Accessories', 'name_sw' => 'Simu na Vifaa', 'icon' => 'smartphone'],
        ['name_en' => 'Vehicles & Parts', 'name_sw' => 'Magari na Vipuri', 'icon' => 'directions_car'],
        ['name_en' => 'Agriculture', 'name_sw' => 'Kilimo', 'icon' => 'agriculture'],
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
