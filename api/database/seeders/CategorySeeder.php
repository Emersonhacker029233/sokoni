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
        // Part 2 (client feedback): C1's "Food & Groceries" -> "Restaurant"
        // rename was reverted — Restaurant is a subcategory of Food &
        // Groceries below, not a replacement for it. Icon unchanged.
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

    /**
     * Subcategories per parent (keyed by the parent's own name_en above),
     * 4-8 each, covering every parent except "Other" — a catch-all bucket
     * has nothing sensible to subdivide into. Roughly noon.com/Jumia-style
     * granularity for the Tanzanian market. Slugs are computed the same
     * way as top-level ones (Str::slug(name_en)), never stored.
     */
    private const SUBCATEGORIES = [
        'Electronics' => [
            ['name_en' => 'TVs', 'name_sw' => 'Televisheni'],
            ['name_en' => 'Fridges & Freezers', 'name_sw' => 'Friji na Sanduku la Barafu'],
            ['name_en' => 'Cookers', 'name_sw' => 'Majiko'],
            ['name_en' => 'Washing Machines', 'name_sw' => 'Mashine za Kufulia'],
            ['name_en' => 'Kitchen Appliances', 'name_sw' => 'Vifaa vya Jikoni'],
            ['name_en' => 'Audio', 'name_sw' => 'Vifaa vya Sauti'],
            ['name_en' => 'Fans & Cooling', 'name_sw' => 'Mashabiki na Upoezaji'],
        ],
        'Fashion' => [
            ['name_en' => "Men's Clothing", 'name_sw' => 'Nguo za Wanaume'],
            ['name_en' => "Women's Clothing", 'name_sw' => 'Nguo za Wanawake'],
            ['name_en' => 'Shoes', 'name_sw' => 'Viatu'],
            ['name_en' => 'Bags', 'name_sw' => 'Mikoba'],
            ['name_en' => 'Kitenge & Fabric', 'name_sw' => 'Kitenge na Vitambaa'],
            ['name_en' => 'Watches', 'name_sw' => 'Saa'],
            ['name_en' => 'Jewellery', 'name_sw' => 'Vito'],
        ],
        'Real Estate' => [
            ['name_en' => 'Houses for Sale', 'name_sw' => 'Nyumba za Kuuza'],
            ['name_en' => 'Houses for Rent', 'name_sw' => 'Nyumba za Kupanga'],
            ['name_en' => 'Apartments', 'name_sw' => 'Ghorofa'],
            ['name_en' => 'Plots & Land', 'name_sw' => 'Viwanja na Ardhi'],
            ['name_en' => 'Commercial', 'name_sw' => 'Majengo ya Biashara'],
            ['name_en' => 'Short Stay', 'name_sw' => 'Kukaa Muda Mfupi'],
        ],
        'Kids' => [
            ['name_en' => 'Baby Clothing', 'name_sw' => 'Nguo za Watoto Wachanga'],
            ['name_en' => 'Kids Clothing', 'name_sw' => 'Nguo za Watoto'],
            ['name_en' => 'Toys', 'name_sw' => 'Vitu vya Kuchezea'],
            ['name_en' => 'School Supplies', 'name_sw' => 'Vifaa vya Shule'],
            ['name_en' => 'Baby Gear', 'name_sw' => 'Vifaa vya Watoto Wachanga'],
            ['name_en' => 'Kids Shoes', 'name_sw' => 'Viatu vya Watoto'],
        ],
        'Food & Groceries' => [
            ['name_en' => 'Fresh Produce', 'name_sw' => 'Mazao Mabichi'],
            ['name_en' => 'Rice & Grains', 'name_sw' => 'Mchele na Nafaka'],
            ['name_en' => 'Cooking Oil', 'name_sw' => 'Mafuta ya Kupikia'],
            ['name_en' => 'Beverages', 'name_sw' => 'Vinywaji'],
            ['name_en' => 'Snacks', 'name_sw' => 'Vitafunio'],
            ['name_en' => 'Spices', 'name_sw' => 'Viungo'],
            ['name_en' => 'Bakery', 'name_sw' => 'Mikate na Keki'],
            // Part 2 (client feedback): Restaurant belongs here as a
            // subcategory, not as a replacement for the parent itself.
            ['name_en' => 'Restaurant', 'name_sw' => 'Mkahawa'],
        ],
        'Home & Furniture' => [
            ['name_en' => 'Sofas & Seating', 'name_sw' => 'Sofa na Viti'],
            ['name_en' => 'Beds & Mattresses', 'name_sw' => 'Vitanda na Magodoro'],
            ['name_en' => 'Tables & Chairs', 'name_sw' => 'Meza na Viti'],
            ['name_en' => 'Storage & Shelving', 'name_sw' => 'Uhifadhi na Rafu'],
            ['name_en' => 'Home Decor', 'name_sw' => 'Mapambo ya Nyumbani'],
            ['name_en' => 'Curtains & Rugs', 'name_sw' => 'Mapazia na Mazulia'],
        ],
        'Beauty & Health' => [
            ['name_en' => 'Skincare', 'name_sw' => 'Utunzaji wa Ngozi'],
            ['name_en' => 'Haircare', 'name_sw' => 'Utunzaji wa Nywele'],
            ['name_en' => 'Makeup', 'name_sw' => 'Vipodozi'],
            ['name_en' => 'Fragrances', 'name_sw' => 'Manukato'],
            ['name_en' => 'Personal Care', 'name_sw' => 'Usafi Binafsi'],
            ['name_en' => 'Health & Wellness', 'name_sw' => 'Afya na Ustawi'],
        ],
        'Phones & Accessories' => [
            ['name_en' => 'Smartphones', 'name_sw' => 'Simu Janja'],
            ['name_en' => 'Feature Phones', 'name_sw' => 'Simu za Kawaida'],
            ['name_en' => 'Chargers & Cables', 'name_sw' => 'Chaja na Nyaya'],
            ['name_en' => 'Cases & Covers', 'name_sw' => 'Vifuniko vya Simu'],
            ['name_en' => 'Power Banks', 'name_sw' => 'Power Bank'],
            ['name_en' => 'Screen Protectors', 'name_sw' => 'Kinga za Skrini'],
            ['name_en' => 'Earphones', 'name_sw' => 'Vipokea Sauti vya Masikioni'],
        ],
        'Vehicles & Parts' => [
            ['name_en' => 'Cars', 'name_sw' => 'Magari'],
            ['name_en' => 'Motorcycles', 'name_sw' => 'Pikipiki'],
            ['name_en' => 'Tyres', 'name_sw' => 'Matairi'],
            ['name_en' => 'Batteries', 'name_sw' => 'Betri'],
            ['name_en' => 'Spare Parts', 'name_sw' => 'Vipuri'],
            ['name_en' => 'Car Audio', 'name_sw' => 'Sauti za Gari'],
        ],
        'Cereal & Legume' => [
            ['name_en' => 'Maize', 'name_sw' => 'Mahindi'],
            ['name_en' => 'Rice', 'name_sw' => 'Mchele'],
            ['name_en' => 'Beans', 'name_sw' => 'Maharage'],
            ['name_en' => 'Sorghum & Millet', 'name_sw' => 'Mtama na Ulezi'],
            ['name_en' => 'Groundnuts', 'name_sw' => 'Karanga'],
            ['name_en' => 'Seeds & Seedlings', 'name_sw' => 'Mbegu na Miche'],
        ],
        'Hardware' => [
            ['name_en' => 'Cement & Building Materials', 'name_sw' => 'Saruji na Vifaa vya Ujenzi'],
            ['name_en' => 'Paint', 'name_sw' => 'Rangi'],
            ['name_en' => 'Tools', 'name_sw' => 'Zana'],
            ['name_en' => 'Plumbing', 'name_sw' => 'Mabomba'],
            ['name_en' => 'Electrical', 'name_sw' => 'Umeme'],
            ['name_en' => 'Roofing', 'name_sw' => 'Vifaa vya Paa'],
        ],
        'Services' => [
            ['name_en' => 'Home Services', 'name_sw' => 'Huduma za Nyumbani'],
            ['name_en' => 'Repairs & Maintenance', 'name_sw' => 'Ukarabati na Matengenezo'],
            ['name_en' => 'Beauty Services', 'name_sw' => 'Huduma za Urembo'],
            ['name_en' => 'Events', 'name_sw' => 'Matukio'],
            ['name_en' => 'Transport & Logistics', 'name_sw' => 'Usafiri na Usafirishaji'],
            ['name_en' => 'Professional Services', 'name_sw' => 'Huduma za Kitaalamu'],
        ],
    ];

    public function run(): void
    {
        $parentIds = [];

        foreach (self::CATEGORIES as $index => $category) {
            $parent = Category::updateOrCreate(
                ['name_en' => $category['name_en'], 'parent_id' => null],
                [...$category, 'sort_order' => $index, 'is_active' => true]
            );
            $parentIds[$category['name_en']] = $parent->id;
        }

        foreach (self::SUBCATEGORIES as $parentName => $children) {
            $parentId = $parentIds[$parentName];

            foreach ($children as $index => $child) {
                Category::updateOrCreate(
                    ['parent_id' => $parentId, 'name_en' => $child['name_en']],
                    [...$child, 'sort_order' => $index, 'is_active' => true]
                );
            }
        }
    }
}
