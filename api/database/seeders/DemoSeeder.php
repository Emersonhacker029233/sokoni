<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\Review;
use App\Models\SellerProfile;
use App\Models\Showcase;
use App\Models\Update;
use App\Models\User;
use App\Support\InitialsLogo;
use App\Support\ProductPlaceholderImage;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Realistic Tanzanian demo content for client demos — separate from
 * DatabaseSeeder (which seeds the Faker-generated baseline this exists to
 * replace/supplement) and safe to run against a live database with real
 * accounts already in it.
 *
 * Identification, not a schema flag: every demo shop uses one of the 12
 * fixed handles in self::SHOPS, and every demo buyer one of the fixed
 * phone numbers in self::BUYERS — both hardcoded, deterministic, and never
 * matching a real account. `--fresh` (see Console\Commands\SeedDemoContent)
 * deletes exactly those rows by exact handle/phone match, which — every
 * relevant foreign key already being ON DELETE CASCADE (products, media,
 * offers, showcases, updates, reviews, comments, orders, order items) —
 * is enough to remove *only* demo content, real accounts and their data
 * completely untouched. Re-running without --fresh is still idempotent:
 * every shop/product upsert is keyed on its own unique handle/title+seller.
 */
class DemoSeeder extends Seeder
{
    /** @var array<int, array{handle: string, shop_name: string, category: string, district: string, lat: float, lng: float, address: string, whatsapp: string, bio_en: string, bio_sw: string, status: string, rejection_reason?: string, hours: array} */
    private const SHOPS = [
        [
            'handle' => 'kariakoo_mobile',
            'shop_name' => 'Kariakoo Mobile Center',
            'category' => 'Phones & Accessories',
            'district' => 'Kariakoo',
            'lat' => -6.8161, 'lng' => 39.2695,
            'address' => 'Mkunguni Street, near Kariakoo Market',
            'whatsapp' => '+255754112233',
            'bio_en' => 'Genuine phones and accessories at Kariakoo\'s best prices — iPhone, Samsung and Tecno, all with a 6-month warranty.',
            'bio_sw' => 'Simu halisi na vifaa vyake kwa bei nzuri za Kariakoo — iPhone, Samsung na Tecno, zote na dhamana ya miezi 6.',
            'status' => 'verified',
            'hours' => ['open' => '08:00', 'close' => '20:00', 'sunday' => null],
        ],
        [
            'handle' => 'mbezi_electronics',
            'shop_name' => 'Mbezi Beach Electronics',
            'category' => 'Electronics',
            'district' => 'Mbezi Beach',
            'lat' => -6.7247, 'lng' => 39.2148,
            'address' => 'Old Bagamoyo Road, opposite Mbezi Beach Shopping Centre',
            'whatsapp' => '+255715223344',
            'bio_en' => 'TVs, fridges and home appliances delivered anywhere in Dar es Salaam. Ask about our instalment plan.',
            'bio_sw' => 'Televisheni, friji na vifaa vya nyumbani, tunapeleka popote Dar es Salaam. Uliza kuhusu malipo ya awamu.',
            'status' => 'verified',
            'hours' => ['open' => '08:30', 'close' => '19:00', 'sunday' => ['open' => '10:00', 'close' => '16:00']],
        ],
        [
            'handle' => 'masaki_fashion',
            'shop_name' => 'Masaki Fashion House',
            'category' => 'Fashion',
            'district' => 'Masaki',
            'lat' => -6.7454, 'lng' => 39.2814,
            'address' => 'Chole Road, Masaki',
            'whatsapp' => '+255786334455',
            'bio_en' => 'Quality shoes and handbags for men and women, sourced from Dubai and Turkey.',
            'bio_sw' => 'Viatu na mikoba bora kwa wanaume na wanawake, tunatoa kutoka Dubai na Uturuki.',
            'status' => 'verified',
            'hours' => ['open' => '09:00', 'close' => '19:00', 'sunday' => null],
        ],
        [
            'handle' => 'sinza_grocers',
            'shop_name' => 'Sinza Fresh Grocers',
            'category' => 'Food & Groceries',
            'district' => 'Sinza',
            'lat' => -6.7735, 'lng' => 39.2478,
            'address' => 'Sinza Mori, near Shekilango Road',
            'whatsapp' => '+255767445566',
            'bio_en' => 'Rice, cooking oil, sugar and flour at fair prices — a Sinza family shop trusted for over ten years.',
            'bio_sw' => 'Mchele, mafuta ya kupikia, sukari na unga kwa bei nafuu — duka la familia la Sinza linaloaminika kwa zaidi ya miaka kumi.',
            'status' => 'verified',
            'hours' => ['open' => '07:00', 'close' => '21:00', 'sunday' => ['open' => '08:00', 'close' => '20:00']],
        ],
        [
            'handle' => 'ubungo_hardware',
            'shop_name' => 'Ubungo Hardware & Tools',
            'category' => 'Hardware',
            'district' => 'Ubungo',
            'lat' => -6.7789, 'lng' => 39.2094,
            'address' => 'Morogoro Road, near Ubungo Bus Terminal',
            'whatsapp' => '+255713556677',
            'bio_en' => 'Cement, paint, tools and fittings for contractors and homeowners. Bulk orders delivered on site.',
            'bio_sw' => 'Saruji, rangi, zana na vifaa vya ujenzi kwa wakandarasi na wamiliki wa nyumba. Oda za jumla tunapeleka eneo la kazi.',
            'status' => 'verified',
            'hours' => ['open' => '07:30', 'close' => '18:30', 'sunday' => null],
        ],
        [
            'handle' => 'mwenge_furniture',
            'shop_name' => 'Mwenge Furniture Workshop',
            'category' => 'Home & Furniture',
            'district' => 'Mwenge',
            'lat' => -6.7691, 'lng' => 39.2431,
            'address' => 'Sam Nujoma Road, Mwenge Furniture Market',
            'whatsapp' => '+255754667788',
            'bio_en' => 'Handmade sofas and dining tables built to order at the Mwenge Furniture Market — solid wood, real craftsmanship.',
            'bio_sw' => 'Sofa na meza za kulia zinazotengenezwa kwa mkono kwa oda katika Soko la Samani la Mwenge — mbao imara, ufundi halisi.',
            'status' => 'verified',
            'hours' => ['open' => '08:00', 'close' => '18:00', 'sunday' => null],
        ],
        [
            'handle' => 'kinondoni_beauty',
            'shop_name' => 'Kinondoni Beauty Supplies',
            'category' => 'Beauty & Health',
            'district' => 'Kinondoni',
            'lat' => -6.7900, 'lng' => 39.2600,
            'address' => 'Ali Hassan Mwinyi Road, Kinondoni',
            'whatsapp' => '+255765778899',
            'bio_en' => 'Skincare, haircare and grooming products for the whole family, at wholesale-friendly prices.',
            'bio_sw' => 'Bidhaa za ngozi, nywele na urembo kwa familia nzima, kwa bei rafiki za jumla.',
            'status' => 'verified',
            'hours' => ['open' => '08:00', 'close' => '20:00', 'sunday' => ['open' => '10:00', 'close' => '18:00']],
        ],
        [
            'handle' => 'ilala_autospares',
            'shop_name' => 'Ilala Auto Spares',
            'category' => 'Vehicles & Parts',
            'district' => 'Ilala',
            'lat' => -6.8235, 'lng' => 39.2917,
            'address' => 'Nyerere Road, Ilala',
            'whatsapp' => '+255786889900',
            'bio_en' => 'Genuine and quality-copy spare parts for cars, boda bodas and small trucks. We fit tyres and batteries on the spot.',
            'bio_sw' => 'Vipuri halisi na vya nakala bora kwa magari, bodaboda na malori madogo. Tunafunga matairi na betri papo hapo.',
            'status' => 'verified',
            'hours' => ['open' => '07:00', 'close' => '19:00', 'sunday' => null],
        ],
        [
            'handle' => 'temeke_agrovet',
            'shop_name' => 'Temeke Agrovet Center',
            'category' => 'Cereal & Legume',
            'district' => 'Temeke',
            'lat' => -6.8600, 'lng' => 39.2500,
            'address' => 'Sokoine Road, Temeke',
            'whatsapp' => '+255713990011',
            'bio_en' => 'Certified seeds, fertiliser and farm tools for small-scale farmers around Dar es Salaam. New shop, applying for verification.',
            'bio_sw' => 'Mbegu bora, mbolea na zana za shambani kwa wakulima wadogo karibu na Dar es Salaam. Duka jipya, tunasubiri uthibitisho.',
            'status' => 'pending',
            'hours' => ['open' => '07:00', 'close' => '18:00', 'sunday' => null],
        ],
        [
            'handle' => 'kigamboni_services',
            'shop_name' => 'Kigamboni Home Services',
            'category' => 'Services',
            'district' => 'Kigamboni',
            'lat' => -6.8300, 'lng' => 39.3100,
            'address' => 'Kigamboni Ferry Road',
            'whatsapp' => '+255754001122',
            'bio_en' => 'Plumbing, electrical and painting services across Kigamboni and Kigamboni Ferry area. Book a call-out any day.',
            'bio_sw' => 'Huduma za mabomba, umeme na upakaji rangi eneo lote la Kigamboni na Kivuko cha Kigamboni. Piga simu siku yoyote.',
            'status' => 'pending',
            'hours' => ['open' => '08:00', 'close' => '17:00', 'sunday' => null],
        ],
        [
            'handle' => 'tegeta_kitenge',
            'shop_name' => 'Tegeta Kitenge Collection',
            'category' => 'Fashion',
            'district' => 'Tegeta',
            'lat' => -6.6833, 'lng' => 39.2000,
            'address' => 'Tegeta Nyuki, Bagamoyo Road',
            'whatsapp' => '+255767112233',
            'bio_en' => 'Colourful kitenge fabric, ready-made ankara dresses and sandals — new shop awaiting verification.',
            'bio_sw' => 'Vitenge vya rangi mbalimbali, mavazi ya ankara yaliyoshonwa na viatu — duka jipya linalosubiri uthibitisho.',
            'status' => 'pending',
            'hours' => ['open' => '09:00', 'close' => '19:00', 'sunday' => null],
        ],
        [
            'handle' => 'mikocheni_comforts',
            'shop_name' => 'Mikocheni Home Comforts',
            'category' => 'Home & Furniture',
            'district' => 'Mikocheni',
            'lat' => -6.7600, 'lng' => 39.2500,
            'address' => 'Mwai Kibaki Road, Mikocheni',
            'whatsapp' => '+255715334455',
            'bio_en' => 'Mattresses and bedroom comfort items for every budget.',
            'bio_sw' => 'Magodoro na vifaa vya starehe vya chumba cha kulala kwa bajeti yoyote.',
            'status' => 'rejected',
            'rejection_reason' => 'Business licence photo was unreadable — please resubmit a clearer copy.',
            'hours' => ['open' => '08:00', 'close' => '19:00', 'sunday' => null],
        ],
    ];

    /**
     * @var array<int, array{handle: string, title: string, price: int, condition: string, description_en: string, description_sw: string}>
     */
    private const PRODUCTS = [
        // Kariakoo Mobile Center — Phones & Accessories
        ['handle' => 'kariakoo_mobile', 'title' => 'iPhone 12 Pro 128GB', 'price' => 950000, 'condition' => 'used', 'description_en' => 'UK-used iPhone 12 Pro, 128GB, Face ID working, battery health above 85%. Comes with charger.', 'description_sw' => 'iPhone 12 Pro iliyotumika kutoka Uingereza, 128GB, Face ID inafanya kazi, afya ya betri zaidi ya 85%. Inakuja na chaja.'],
        ['handle' => 'kariakoo_mobile', 'title' => 'iPhone 12 Pro 256GB', 'price' => 1150000, 'condition' => 'used', 'description_en' => 'UK-used iPhone 12 Pro, 256GB storage, Pacific Blue, minor scratches on the back only.', 'description_sw' => 'iPhone 12 Pro kutoka Uingereza, hifadhi ya 256GB, rangi ya Pacific Blue, mikwaruzo midogo nyuma tu.'],
        ['handle' => 'kariakoo_mobile', 'title' => 'iPhone 12 Pro 128GB (Used)', 'price' => 700000, 'condition' => 'used', 'description_en' => 'Budget-friendly used iPhone 12 Pro, 128GB, screen has a small crack in the corner, fully functional.', 'description_sw' => 'iPhone 12 Pro ya bei nafuu iliyotumika, 128GB, skrini ina mpasuko mdogo pembeni, inafanya kazi vizuri.'],
        ['handle' => 'kariakoo_mobile', 'title' => 'Tecno Spark 10', 'price' => 210000, 'condition' => 'new', 'description_en' => 'Brand new Tecno Spark 10, 128GB storage, 6.6" display, dual SIM. Sealed box with 12-month warranty.', 'description_sw' => 'Tecno Spark 10 mpya, hifadhi ya 128GB, skrini ya inchi 6.6, laini mbili. Boksi limefungwa na dhamana ya mwaka mmoja.'],
        ['handle' => 'kariakoo_mobile', 'title' => 'Tecno Spark 10 Pro', 'price' => 280000, 'condition' => 'new', 'description_en' => 'Tecno Spark 10 Pro with 8GB RAM, 256GB storage and a 108MP camera. Brand new, sealed.', 'description_sw' => 'Tecno Spark 10 Pro yenye RAM 8GB, hifadhi 256GB na kamera ya MP 108. Mpya, imefungwa.'],
        ['handle' => 'kariakoo_mobile', 'title' => 'Samsung Galaxy A14', 'price' => 380000, 'condition' => 'new', 'description_en' => 'Samsung Galaxy A14, 4GB RAM, 128GB storage, long-lasting 5000mAh battery. Official Samsung Tanzania warranty.', 'description_sw' => 'Samsung Galaxy A14, RAM 4GB, hifadhi 128GB, betri ya 5000mAh ya kudumu. Dhamana rasmi ya Samsung Tanzania.'],
        ['handle' => 'kariakoo_mobile', 'title' => 'Samsung Galaxy A34', 'price' => 480000, 'condition' => 'new', 'description_en' => 'Samsung Galaxy A34 5G, 8GB RAM, 128GB storage, 48MP triple camera. Brand new, sealed box.', 'description_sw' => 'Samsung Galaxy A34 5G, RAM 8GB, hifadhi 128GB, kamera tatu za MP 48. Mpya, boksi limefungwa.'],
        ['handle' => 'kariakoo_mobile', 'title' => 'Samsung Galaxy A54', 'price' => 620000, 'condition' => 'new', 'description_en' => 'Samsung Galaxy A54, 8GB RAM, 256GB storage, AMOLED display. The most popular mid-range Samsung this year.', 'description_sw' => 'Samsung Galaxy A54, RAM 8GB, hifadhi 256GB, skrini ya AMOLED. Samsung maarufu zaidi mwaka huu kwa bei ya kati.'],
        ['handle' => 'kariakoo_mobile', 'title' => 'Fast Charger 20W (Type-C)', 'price' => 15000, 'condition' => 'new', 'description_en' => 'Original-quality 20W fast charger, Type-C cable included. Compatible with most Android phones.', 'description_sw' => 'Chaja ya haraka ya 20W ubora wa awali, inakuja na kebo ya Type-C. Inafaa simu nyingi za Android.'],
        ['handle' => 'kariakoo_mobile', 'title' => 'Tempered Glass Screen Protector', 'price' => 8000, 'condition' => 'new', 'description_en' => '9H hardness tempered glass, fitted free of charge in-store while you wait.', 'description_sw' => 'Kioo kigumu cha 9H, tunakifunga bure dukani ukisubiri.'],
        ['handle' => 'kariakoo_mobile', 'title' => 'Power Bank 10000mAh', 'price' => 35000, 'condition' => 'new', 'description_en' => '10000mAh power bank with dual USB output, charges two phones at once.', 'description_sw' => 'Power bank ya 10000mAh yenye milango miwili ya USB, inachaji simu mbili kwa wakati mmoja.'],

        // Mbezi Beach Electronics
        ['handle' => 'mbezi_electronics', 'title' => '43" Smart LED TV', 'price' => 650000, 'condition' => 'new', 'description_en' => '43-inch Smart LED TV, Full HD, built-in WiFi with YouTube and Netflix apps. Free wall mount on delivery.', 'description_sw' => 'TV ya inchi 43 ya Smart LED, Full HD, WiFi ya ndani ikiwa na YouTube na Netflix. Tunaweka ukutani bure tukileta.'],
        ['handle' => 'mbezi_electronics', 'title' => '55" Smart LED TV', 'price' => 1150000, 'condition' => 'new', 'description_en' => '55-inch 4K Smart LED TV with Android TV built in — perfect for a living room upgrade.', 'description_sw' => 'TV ya inchi 55 ya 4K Smart LED yenye Android TV ya ndani — nzuri kwa kuboresha sebule yako.'],
        ['handle' => 'mbezi_electronics', 'title' => 'Electric Blender', 'price' => 68000, 'condition' => 'new', 'description_en' => '1.5-litre electric blender, 3-speed settings, ideal for juice and smoothies. 1-year warranty.', 'description_sw' => 'Blenda ya umeme ya lita 1.5, mwendo mitatu, nzuri kwa juisi na smoothie. Dhamana ya mwaka mmoja.'],
        ['handle' => 'mbezi_electronics', 'title' => 'Double Door Fridge', 'price' => 950000, 'condition' => 'new', 'description_en' => '350-litre double door fridge with a separate freezer compartment, energy-saving inverter compressor.', 'description_sw' => 'Friji ya milango miwili ya lita 350 yenye sehemu tofauti ya friza, inatumia umeme kidogo.'],
        ['handle' => 'mbezi_electronics', 'title' => 'Single Door Fridge', 'price' => 480000, 'condition' => 'new', 'description_en' => 'Compact 150-litre single door fridge, perfect for a small kitchen or bedsitter.', 'description_sw' => 'Friji ndogo ya mlango mmoja ya lita 150, nzuri kwa jiko dogo au chumba cha kupanga.'],
        ['handle' => 'mbezi_electronics', 'title' => 'Microwave Oven', 'price' => 180000, 'condition' => 'new', 'description_en' => '23-litre microwave oven with grill function, digital timer, 1-year warranty.', 'description_sw' => 'Oveni ya microwave ya lita 23 yenye grill, saa ya kidijitali, dhamana ya mwaka mmoja.'],

        // Masaki Fashion House
        ['handle' => 'masaki_fashion', 'title' => "Men's Leather Shoes", 'price' => 65000, 'condition' => 'new', 'description_en' => 'Genuine leather formal shoes, available in black and brown, sizes 40-45.', 'description_sw' => 'Viatu vya ngozi halisi vya kazi, vinapatikana rangi nyeusi na kahawia, saizi 40-45.'],
        ['handle' => 'masaki_fashion', 'title' => "Women's Handbag", 'price' => 45000, 'condition' => 'new', 'description_en' => 'Stylish women\'s handbag, faux leather, available in five colours.', 'description_sw' => 'Mkoba wa kisasa wa wanawake, ngozi bandia, unapatikana rangi tano.'],

        // Sinza Fresh Grocers
        ['handle' => 'sinza_grocers', 'title' => 'Pishori Rice (1kg)', 'price' => 3500, 'condition' => 'new', 'description_en' => 'Premium Pishori rice, sold by the kilo or in bulk sacks — ask for a discount on 25kg+.', 'description_sw' => 'Mchele bora wa Pishori, unauzwa kwa kilo au magunia — uliza punguzo ukinunua kilo 25 na zaidi.'],
        ['handle' => 'sinza_grocers', 'title' => 'Sunflower Cooking Oil (5L)', 'price' => 32000, 'condition' => 'new', 'description_en' => '5-litre sunflower cooking oil, cholesterol-free, a trusted local brand.', 'description_sw' => 'Mafuta ya kupikia ya alizeti ya lita 5, hayana lehemu, chapa ya ndani inayoaminika.'],
        ['handle' => 'sinza_grocers', 'title' => 'White Sugar (1kg)', 'price' => 3200, 'condition' => 'new', 'description_en' => 'Locally refined white sugar, sold per kilo.', 'description_sw' => 'Sukari nyeupe iliyosafishwa nchini, inauzwa kwa kilo.'],
        ['handle' => 'sinza_grocers', 'title' => 'Maize Flour - Unga wa Sembe (25kg)', 'price' => 45000, 'condition' => 'new', 'description_en' => '25kg sack of finely milled maize flour (sembe), a household staple.', 'description_sw' => 'Gunia la unga wa sembe uliosagwa vizuri la kilo 25, chakula muhimu cha nyumbani.'],
        ['handle' => 'sinza_grocers', 'title' => 'Red Beans (1kg)', 'price' => 4000, 'condition' => 'new', 'description_en' => 'Clean, sorted red beans (maharage), sold per kilo.', 'description_sw' => 'Maharage mekundu safi yaliyopambanuliwa, yanauzwa kwa kilo.'],
        ['handle' => 'sinza_grocers', 'title' => 'Wheat Flour (2kg)', 'price' => 6500, 'condition' => 'new', 'description_en' => '2kg pack of wheat flour, good for chapati and mandazi.', 'description_sw' => 'Pakiti ya unga wa ngano ya kilo 2, nzuri kwa chapati na mandazi.'],

        // Ubungo Hardware & Tools
        ['handle' => 'ubungo_hardware', 'title' => 'Cement (50kg bag) - Simba Cement', 'price' => 20000, 'condition' => 'new', 'description_en' => '50kg bag of Simba Cement, ordinary Portland cement for general construction.', 'description_sw' => 'Mfuko wa saruji ya Simba wa kilo 50, saruji ya kawaida kwa ujenzi wa aina zote.'],
        ['handle' => 'ubungo_hardware', 'title' => 'Emulsion Paint (20L)', 'price' => 85000, 'condition' => 'new', 'description_en' => '20-litre emulsion wall paint, washable finish, several colours in stock.', 'description_sw' => 'Rangi ya ukuta ya emulsion ya lita 20, inaoshwa, rangi mbalimbali zipo dukani.'],
        ['handle' => 'ubungo_hardware', 'title' => 'Claw Hammer', 'price' => 12000, 'condition' => 'new', 'description_en' => 'Sturdy steel claw hammer with a fibreglass handle.', 'description_sw' => 'Nyundo imara ya chuma yenye mpini wa fibreglass.'],
        ['handle' => 'ubungo_hardware', 'title' => 'Assorted Nails (1kg)', 'price' => 6000, 'condition' => 'new', 'description_en' => '1kg mixed pack of construction nails, various sizes.', 'description_sw' => 'Pakiti ya misumari ya ujenzi ya kilo 1, saizi mbalimbali.'],
        ['handle' => 'ubungo_hardware', 'title' => 'PVC Pipe Fittings Set', 'price' => 15000, 'condition' => 'new', 'description_en' => 'Assorted PVC pipe fittings for plumbing repairs — elbows, tees and couplings.', 'description_sw' => 'Seti ya vifaa vya bomba la PVC kwa ukarabati wa mabomba — elbow, tee na coupling.'],

        // Mwenge Furniture Workshop
        ['handle' => 'mwenge_furniture', 'title' => '5-Seater Sofa Set', 'price' => 950000, 'condition' => 'new', 'description_en' => 'Handmade 5-seater sofa set, solid mvule wood frame, fabric of your choice. Built to order, 2-week delivery.', 'description_sw' => 'Seti ya sofa ya viti 5 iliyotengenezwa kwa mkono, fremu ya mbao ya mvule, kitambaa unachochagua. Inatengenezwa kwa oda, wiki 2 kukamilika.'],
        ['handle' => 'mwenge_furniture', 'title' => '6-Seater Dining Table Set', 'price' => 580000, 'condition' => 'new', 'description_en' => 'Solid wood 6-seater dining table with matching chairs, hand-finished.', 'description_sw' => 'Meza ya kulia ya mbao imara ya viti 6 na viti vinavyolingana, imemaliziwa kwa mkono.'],
        ['handle' => 'mwenge_furniture', 'title' => '3-Seater Sofa', 'price' => 550000, 'condition' => 'new', 'description_en' => 'Comfortable 3-seater sofa, high-density foam, durable fabric cover.', 'description_sw' => 'Sofa nzuri ya viti 3, povu imara, kitambaa cha kudumu.'],

        // Kinondoni Beauty Supplies
        ['handle' => 'kinondoni_beauty', 'title' => 'Shea Butter Body Lotion', 'price' => 12000, 'condition' => 'new', 'description_en' => 'Pure shea butter body lotion, 400ml, moisturises dry skin all day.', 'description_sw' => 'Losheni ya mwili ya shea butter safi, mililita 400, inalainisha ngozi kavu siku nzima.'],
        ['handle' => 'kinondoni_beauty', 'title' => 'Natural Hair Growth Oil', 'price' => 18000, 'condition' => 'new', 'description_en' => 'Blend of natural oils for healthy hair growth, 200ml bottle.', 'description_sw' => 'Mchanganyiko wa mafuta asili kwa ukuaji mzuri wa nywele, chupa ya mililita 200.'],
        ['handle' => 'kinondoni_beauty', 'title' => 'Skin Whitening Cream', 'price' => 25000, 'condition' => 'new', 'description_en' => 'Gentle brightening cream for even skin tone, dermatologist-tested formula.', 'description_sw' => 'Krimu laini ya kung\'arisha ngozi kwa rangi sawa, imepimwa na daktari wa ngozi.'],
        ['handle' => 'kinondoni_beauty', 'title' => 'Perfume (50ml)', 'price' => 45000, 'condition' => 'new', 'description_en' => 'Long-lasting unisex perfume, 50ml spray bottle.', 'description_sw' => 'Manukato ya kudumu kwa wanaume na wanawake, chupa ya kunyunyizia ya mililita 50.'],
        ['handle' => 'kinondoni_beauty', 'title' => 'Electric Hair Clipper', 'price' => 55000, 'condition' => 'new', 'description_en' => 'Rechargeable electric hair clipper set with multiple guard combs.', 'description_sw' => 'Mashine ya kunyoa ya umeme inayochajika, ina vichanuo vya ukubwa mbalimbali.'],

        // Ilala Auto Spares
        ['handle' => 'ilala_autospares', 'title' => 'Car Battery 12V', 'price' => 180000, 'condition' => 'new', 'description_en' => '12V 65Ah car battery, 18-month warranty, free fitting in-store.', 'description_sw' => 'Betri ya gari ya 12V 65Ah, dhamana ya miezi 18, tunafunga bure dukani.'],
        ['handle' => 'ilala_autospares', 'title' => 'Motorcycle Tyre', 'price' => 55000, 'condition' => 'new', 'description_en' => 'Durable motorcycle tyre for boda bodas, fits most 175cc-250cc bikes.', 'description_sw' => 'Tairi imara ya pikipiki kwa bodaboda, inafaa pikipiki nyingi za 175cc-250cc.'],
        ['handle' => 'ilala_autospares', 'title' => 'Car Tyre 175/70R13', 'price' => 120000, 'condition' => 'new', 'description_en' => 'New 175/70R13 car tyre, suitable for most small sedans.', 'description_sw' => 'Tairi mpya ya gari 175/70R13, inafaa magari mengi madogo.'],
        ['handle' => 'ilala_autospares', 'title' => 'Bajaj Boda Spare Parts Kit', 'price' => 45000, 'condition' => 'new', 'description_en' => 'Common spare parts kit for Bajaj Boxer motorcycles — brake pads, cables and filters.', 'description_sw' => 'Seti ya vipuri vya kawaida vya pikipiki za Bajaj Boxer — breki, nyaya na vichujio.'],
        ['handle' => 'ilala_autospares', 'title' => 'Engine Oil (4L)', 'price' => 35000, 'condition' => 'new', 'description_en' => '4-litre engine oil, suitable for both petrol and diesel engines.', 'description_sw' => 'Mafuta ya injini ya lita 4, yanafaa injini za petroli na dizeli.'],
        ['handle' => 'ilala_autospares', 'title' => 'Car Spare Tyre Rim', 'price' => 90000, 'condition' => 'used', 'description_en' => 'Used steel spare wheel rim, common 13-inch size, good condition.', 'description_sw' => 'Rim ya chuma ya spea iliyotumika, ukubwa wa kawaida wa inchi 13, hali nzuri.'],

        // Temeke Agrovet Center
        ['handle' => 'temeke_agrovet', 'title' => 'Maize Seeds (2kg pack)', 'price' => 15000, 'condition' => 'new', 'description_en' => 'Certified hybrid maize seeds, 2kg pack, high germination rate.', 'description_sw' => 'Mbegu za mahindi za mseto zilizothibitishwa, pakiti ya kilo 2, zinaota vizuri.'],
        ['handle' => 'temeke_agrovet', 'title' => 'NPK Fertiliser (50kg)', 'price' => 85000, 'condition' => 'new', 'description_en' => '50kg bag of NPK fertiliser, suitable for maize, vegetables and rice.', 'description_sw' => 'Mfuko wa mbolea ya NPK wa kilo 50, unafaa mahindi, mboga na mpunga.'],
        ['handle' => 'temeke_agrovet', 'title' => 'Jembe (Hand Hoe)', 'price' => 12000, 'condition' => 'new', 'description_en' => 'Sturdy steel hand hoe with a wooden handle, for general farm work.', 'description_sw' => 'Jembe imara la chuma lenye mpini wa mbao, kwa kazi za kawaida za shambani.'],
        ['handle' => 'temeke_agrovet', 'title' => 'Panga (Machete)', 'price' => 10000, 'condition' => 'new', 'description_en' => 'Sharp steel machete for clearing bush and harvesting.', 'description_sw' => 'Panga kali la chuma kwa kufyeka na kuvuna.'],
        ['handle' => 'temeke_agrovet', 'title' => 'Watering Can (10L)', 'price' => 18000, 'condition' => 'new', 'description_en' => '10-litre plastic watering can with a removable rose head.', 'description_sw' => 'Chombo cha kumwagilia cha plastiki cha lita 10, kina kichwa cha kutoa kinachoondolewa.'],

        // Kigamboni Home Services
        ['handle' => 'kigamboni_services', 'title' => 'Plumbing Repair Service (call-out)', 'price' => 25000, 'condition' => 'new', 'description_en' => 'Call-out fee for a plumbing repair visit within Kigamboni — leaking pipes, blocked drains, tap repairs. Parts charged separately.', 'description_sw' => 'Ada ya kufika kwa ukarabati wa mabomba ndani ya Kigamboni — uvujaji, michirizi iliyoziba, ukarabati wa mfereji. Vipuri vinalipiwa tofauti.'],
        ['handle' => 'kigamboni_services', 'title' => 'House Painting Service (per room)', 'price' => 80000, 'condition' => 'new', 'description_en' => 'Professional interior painting, priced per standard-size room, paint not included.', 'description_sw' => 'Upakaji rangi wa ndani wa kitaalamu, bei kwa chumba cha kawaida, rangi haijajumuishwa.'],
        ['handle' => 'kigamboni_services', 'title' => 'Electrical Wiring Service', 'price' => 120000, 'condition' => 'new', 'description_en' => 'Full electrical wiring inspection and repair for a standard 3-bedroom house.', 'description_sw' => 'Ukaguzi kamili wa mfumo wa umeme na ukarabati kwa nyumba ya kawaida ya vyumba 3.'],
        ['handle' => 'kigamboni_services', 'title' => 'Furniture Delivery Service', 'price' => 30000, 'condition' => 'new', 'description_en' => 'Delivery and careful handling of furniture within Kigamboni and Dar es Salaam.', 'description_sw' => 'Uwasilishaji na utunzaji makini wa samani ndani ya Kigamboni na Dar es Salaam.'],
        ['handle' => 'kigamboni_services', 'title' => 'Generator Repair Service', 'price' => 60000, 'condition' => 'new', 'description_en' => 'Diagnosis and repair for home and small business generators.', 'description_sw' => 'Uchunguzi na ukarabati wa jenereta za nyumbani na biashara ndogo.'],

        // Tegeta Kitenge Collection
        ['handle' => 'tegeta_kitenge', 'title' => 'Kitenge Fabric (6 yards)', 'price' => 28000, 'condition' => 'new', 'description_en' => 'Vibrant 6-yard kitenge fabric, wax print, many patterns to choose from.', 'description_sw' => 'Kitenge cha yadi 6 chenye rangi nzuri, cha wax print, mifumo mingi ya kuchagua.'],
        ['handle' => 'tegeta_kitenge', 'title' => 'Ankara Dress', 'price' => 38000, 'condition' => 'new', 'description_en' => 'Ready-made ankara print dress, available in sizes S-XL.', 'description_sw' => 'Vazi la ankara lililoshonwa tayari, linapatikana saizi S-XL.'],
        ['handle' => 'tegeta_kitenge', 'title' => "Men's Casual Sandals", 'price' => 30000, 'condition' => 'new', 'description_en' => 'Comfortable leather casual sandals for everyday wear.', 'description_sw' => 'Ndara za ngozi za kawaida, nzuri kwa matumizi ya kila siku.'],

        // Mikocheni Home Comforts
        ['handle' => 'mikocheni_comforts', 'title' => 'Queen Size Mattress', 'price' => 250000, 'condition' => 'new', 'description_en' => 'High-density foam queen size mattress, 6-inch thickness, 3-year warranty.', 'description_sw' => 'Godoro la povu imara la ukubwa wa queen, unene wa inchi 6, dhamana ya miaka 3.'],
        ['handle' => 'mikocheni_comforts', 'title' => 'King Size Mattress', 'price' => 380000, 'condition' => 'new', 'description_en' => 'Premium king size mattress with a quilted cover, orthopaedic support.', 'description_sw' => 'Godoro bora la ukubwa wa king lenye kifuniko cha quilt, msaada mzuri wa mgongo.'],
        ['handle' => 'mikocheni_comforts', 'title' => 'Recliner Armchair', 'price' => 320000, 'condition' => 'new', 'description_en' => 'Reclining armchair with a footrest, faux leather upholstery.', 'description_sw' => 'Kiti cha starehe chenye sehemu ya kuegemeza miguu, kimefunikwa ngozi bandia.'],
    ];

    /** Fixed, deterministic demo buyers — identified for --fresh cleanup by this exact phone list, never a real number. */
    private const BUYERS = [
        ['name' => 'Amina Juma', 'phone' => '+255755010001'],
        ['name' => 'Baraka Mushi', 'phone' => '+255755010002'],
        ['name' => 'Catherine Mwakalinga', 'phone' => '+255755010003'],
        ['name' => 'Daudi Chacha', 'phone' => '+255755010004'],
        ['name' => 'Elizabeth Kimaro', 'phone' => '+255755010005'],
        ['name' => 'Frank Mbwana', 'phone' => '+255755010006'],
        ['name' => 'Grace Ndosi', 'phone' => '+255755010007'],
        ['name' => 'Hamisi Rashidi', 'phone' => '+255755010008'],
        ['name' => 'Irene Massawe', 'phone' => '+255755010009'],
        ['name' => 'John Mgaya', 'phone' => '+255755010010'],
        ['name' => 'Khadija Salum', 'phone' => '+255755010011'],
        ['name' => 'Lucas Temba', 'phone' => '+255755010012'],
        ['name' => 'Mwajuma Iddi', 'phone' => '+255755010013'],
        ['name' => 'Neema Shirima', 'phone' => '+255755010014'],
        ['name' => 'Omary Kassim', 'phone' => '+255755010015'],
    ];

    /** @var array<int, array{rating: int, comment: string, reply?: string}> */
    private const REVIEW_TEMPLATES = [
        ['rating' => 5, 'comment' => 'Bidhaa nzuri sana, imefika kwa wakati. Nitanunua tena.', 'reply' => 'Asante sana kwa kutuamini, tunafurahi umeridhika!'],
        ['rating' => 5, 'comment' => 'Excellent service, exactly as described. Highly recommend this shop.'],
        ['rating' => 4, 'comment' => 'Bidhaa ni nzuri lakini ilichelewa kidogo kufika.', 'reply' => 'Pole kwa ucheleweshaji, tutaboresha huduma ya usafirishaji.'],
        ['rating' => 5, 'comment' => 'Mzigo umefika salama, bei nzuri kuliko sokoni.'],
        ['rating' => 3, 'comment' => 'It was okay, packaging could be better next time.'],
        ['rating' => 5, 'comment' => 'Muuzaji ni mkweli, bidhaa kama ilivyoelezwa kwenye picha.'],
        ['rating' => 4, 'comment' => 'Good quality for the price. Would buy again.'],
        ['rating' => 2, 'comment' => 'Bidhaa haikuwa sawa na maelezo, nilitegemea kitu bora zaidi.', 'reply' => 'Samahani kwa hali hii, tafadhali wasiliana nasi tukusaidie.'],
        ['rating' => 5, 'comment' => 'Very fast delivery and the seller was easy to reach on WhatsApp.'],
        ['rating' => 4, 'comment' => 'Nzuri, ila bei ingeweza kuwa nafuu kidogo.'],
        ['rating' => 5, 'comment' => 'Duka zuri, mmiliki ni mwaminifu na mchangamfu.'],
        ['rating' => 3, 'comment' => 'Average experience, delivery took longer than expected.'],
        ['rating' => 5, 'comment' => 'Highly recommend! Second time ordering and still great quality.'],
        ['rating' => 4, 'comment' => 'Bidhaa nzuri, huduma kwa wateja ni nzuri pia.'],
        ['rating' => 5, 'comment' => 'Perfect transaction, no issues at all.'],
    ];

    /** @var array<int, string> */
    private const COMMENT_TEMPLATES = [
        'Bei hii ni ya mwisho au kuna nafasi ya punguzo?',
        'Is this still in stock? I need it urgently.',
        'Mnauza jumla pia au ni rejareja tu?',
        'Do you deliver outside Dar es Salaam?',
        'Ubora wa hii bidhaa ukoje kwa matumizi ya muda mrefu?',
    ];

    /** @var array<int, string> */
    private const COMMENT_REPLY_TEMPLATES = [
        'Bei hiyo ni ya mwisho, lakini tukiwa na oda kubwa tunaweza kuongea.',
        'Yes, still available — message us on WhatsApp to confirm before you come.',
        'Tunauza jumla na rejareja, karibu dukani.',
    ];

    /** The exact set of handles --fresh cleanup deletes by — see Console\Commands\SeedDemoContent. */
    public static function demoHandles(): array
    {
        return array_column(self::SHOPS, 'handle');
    }

    /** The exact set of buyer phone numbers --fresh cleanup deletes by. */
    public static function demoBuyerPhones(): array
    {
        return array_column(self::BUYERS, 'phone');
    }

    public function run(): void
    {
        $this->assertHandlesAreValid();

        $sellersByHandle = $this->seedShops();
        $productsByHandle = $this->seedProducts($sellersByHandle);
        $buyers = $this->seedBuyers();

        $this->seedOffers($productsByHandle);
        $this->seedUpdatesAndShowcases($sellersByHandle, $productsByHandle);
        $this->seedOrdersReviewsAndComments($sellersByHandle, $productsByHandle, $buyers);

        $this->command?->info('Demo content seeded: '.count(self::SHOPS).' shops, '.count(self::PRODUCTS).' products.');
    }

    /**
     * A production run of this exact seeder once inserted a 22-character
     * handle (`mbezibeach_electronics`) straight past `handle`'s VARCHAR(20)
     * column with a hard SQL error, because SQLite — this project's local/CI
     * test driver — doesn't enforce declared VARCHAR lengths, so the same
     * bug sailed through the full test suite unnoticed. Checked here against
     * the app's own SellerProfile::HANDLE_PATTERN/RESERVED_HANDLES (the same
     * rule SellerOnboardBusinessRequest enforces for a real seller signing
     * up), so a future edit to self::SHOPS fails loudly in every
     * environment — dev, CI, or production — the moment it's run, not
     * silently on whichever environment happens to enforce column limits.
     */
    private function assertHandlesAreValid(): void
    {
        foreach (self::demoHandles() as $handle) {
            if (! preg_match(SellerProfile::HANDLE_PATTERN, $handle)) {
                throw new \InvalidArgumentException("DemoSeeder handle '{$handle}' fails SellerProfile::HANDLE_PATTERN (".SellerProfile::HANDLE_PATTERN.").");
            }

            if (in_array($handle, SellerProfile::RESERVED_HANDLES, true)) {
                throw new \InvalidArgumentException("DemoSeeder handle '{$handle}' is a reserved handle.");
            }
        }

        if (count(self::demoHandles()) !== count(array_unique(self::demoHandles()))) {
            throw new \InvalidArgumentException('DemoSeeder::SHOPS contains a duplicate handle.');
        }
    }

    /** @return array<string, SellerProfile> keyed by handle */
    private function seedShops(): array
    {
        // DemoSeeder must be runnable entirely on its own (per instruction),
        // not assume DatabaseSeeder/CategorySeeder already ran — CategorySeeder's
        // own updateOrCreate() keyed on name_en makes this a safe no-op if they did.
        $this->call(CategorySeeder::class);

        // "Hardware" (renamed from "Construction & Hardware") is now a
        // permanent part of CategorySeeder::CATEGORIES itself, not created
        // ad hoc here — the call above already provisions it.
        $categoryIds = Category::pluck('id', 'name_en')->all();

        $sellers = [];

        foreach (self::SHOPS as $shop) {
            $user = User::firstOrCreate(
                ['phone' => $shop['whatsapp']],
                ['name' => $shop['shop_name'].' Owner', 'provider' => 'phone', 'provider_id' => $shop['whatsapp'], 'account_intent' => 'sell'],
            );

            $logoPath = "demo/logos/{$shop['handle']}.svg";
            Storage::disk('public')->put($logoPath, InitialsLogo::svg($shop['shop_name']));

            $attributes = [
                'user_id' => $user->id,
                'shop_name' => $shop['shop_name'],
                'category_id' => $categoryIds[$shop['category']] ?? null,
                'bio' => $shop['bio_en'],
                'bio_sw' => $shop['bio_sw'],
                'whatsapp' => $shop['whatsapp'],
                'show_whatsapp' => true,
                'lat' => $shop['lat'],
                'lng' => $shop['lng'],
                'address' => $shop['address'],
                'region' => 'Dar es Salaam',
                'district' => $shop['district'],
                'nida_number' => str_pad((string) crc32($shop['handle']), 20, '0', STR_PAD_LEFT),
                'nida_image' => 'demo/nida/'.$shop['handle'].'.jpg',
                'licence_file' => 'demo/licences/'.$shop['handle'].'.pdf',
                'status' => $shop['status'],
                'rejection_reason' => $shop['rejection_reason'] ?? null,
                'verified_at' => $shop['status'] === 'verified' ? now() : null,
                'logo' => Storage::disk('public')->url($logoPath),
                'opening_hours' => $this->openingHoursFor($shop['hours']),
            ];

            $seller = SellerProfile::updateOrCreate(['handle' => $shop['handle']], $attributes);

            $sellers[$shop['handle']] = $seller;
        }

        return $sellers;
    }

    /** @param array{open: string, close: string, sunday: array|null} $hours */
    private function openingHoursFor(array $hours): array
    {
        $weekday = ['open' => $hours['open'], 'close' => $hours['close']];

        return [
            'monday' => $weekday, 'tuesday' => $weekday, 'wednesday' => $weekday,
            'thursday' => $weekday, 'friday' => $weekday, 'saturday' => $weekday,
            'sunday' => $hours['sunday'],
        ];
    }

    /**
     * @param array<string, SellerProfile> $sellersByHandle
     * @return array<int, Product>
     */
    private function seedProducts(array $sellersByHandle): array
    {
        $categoryIcons = Category::pluck('icon', 'id')->all();
        $products = [];

        foreach (self::PRODUCTS as $data) {
            $seller = $sellersByHandle[$data['handle']];

            $product = Product::updateOrCreate(
                ['seller_id' => $seller->id, 'title' => $data['title']],
                [
                    'category_id' => $seller->category_id,
                    'description' => $data['description_en'],
                    'description_sw' => $data['description_sw'],
                    'price' => $data['price'],
                    'currency' => 'TZS',
                    'stock' => random_int(3, 40),
                    'condition' => $data['condition'],
                    'is_active' => true,
                    'is_hidden' => false,
                    'views' => random_int(5, 800),
                ],
            );

            // Self-healing, not just idempotent: the path is deterministic
            // from the product's own handle+title, so it's recomputed and
            // checked against the *disk* every run, independent of whether
            // a ProductMedia row already exists. A row surviving while its
            // file doesn't — a storage move/reset, or a --fresh that only
            // cleared shops/buyers, not media — previously left the file
            // never rewritten, since the old guard only checked "does a
            // row exist" (see DECISIONS.md).
            $icon = $categoryIcons[$product->category_id] ?? 'more_horiz';
            $label = $product->category?->name_en ?? 'Sokoni';
            $path = 'demo/products/'.str($data['handle'].'-'.$data['title'])->slug().'.svg';
            $this->ensureFileExists($path, fn () => ProductPlaceholderImage::svg($icon, $label));

            if ($product->media()->count() === 0) {
                $url = Storage::disk('public')->url($path);

                ProductMedia::create([
                    'product_id' => $product->id,
                    'type' => 'image',
                    'path' => $url,
                    'thumb_path' => $url,
                    'card_path' => $url,
                    'sort' => 0,
                ]);
            }

            $products[] = $product;
        }

        return $products;
    }

    /** @param array<int, Product> $products */
    private function seedOffers(array $products): void
    {
        // A handful of live offers with real countdowns — every verified
        // seller's cheaper, everyday items are good discount candidates.
        $candidates = collect($products)->filter(fn (Product $p) => $p->seller->isVerified() && $p->price <= 200000)->values();

        foreach ($candidates->take(6) as $index => $product) {
            Offer::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'seller_id' => $product->seller_id,
                    'discount_type' => 'percent',
                    'discount_value' => [10, 15, 20, 25, 30, 15][$index % 6],
                    'price_snapshot' => $product->price,
                    'starts_at' => now()->subHours(random_int(1, 12)),
                    'ends_at' => now()->addDays(random_int(1, 5))->addHours(random_int(0, 12)),
                ],
            );
        }
    }

    /**
     * @param array<string, SellerProfile> $sellersByHandle
     * @param array<int, Product> $products
     */
    private function seedUpdatesAndShowcases(array $sellersByHandle, array $products): void
    {
        $verifiedSellers = collect($sellersByHandle)->filter(fn (SellerProfile $s) => $s->isVerified())->values();
        $captionsEn = ['New stock just arrived!', 'Limited quantity available this week.', 'Ask us about delivery in your area.'];
        $captionsSw = ['Bidhaa mpya zimefika!', 'Kiasi kidogo tu kwa wiki hii.', 'Tuulize kuhusu usafirishaji eneo lako.'];

        foreach ($verifiedSellers->take(6) as $index => $seller) {
            // .svg, not the old .jpg — the placeholder generator produces
            // SVG, and a mismatched extension gets served with the wrong
            // Content-Type by Apache's default MIME mapping (image/jpeg
            // for a .jpg path regardless of the actual bytes inside it),
            // which browsers then refuse to render as an image at all.
            // Same self-healing check as products: recomputed and checked
            // against the disk every run, independent of whether the
            // Update row already exists.
            $path = "demo/updates/{$seller->handle}.svg";
            $this->ensureFileExists($path, fn () => ProductPlaceholderImage::svg('more_horiz', $seller->shop_name));
            // The real Api\UpdateController::store() always wraps a stored
            // path in Storage::disk('public')->url() before saving it —
            // UpdateResource/ShowcaseResource pass media_path/thumb_path/
            // video_path straight through with no prefixing of their own,
            // so a bare relative path here was never a loadable URL for
            // any client at all (tester feedback B1's "media not
            // displaying" — a second, distinct bug from the SVG one).
            $url = Storage::disk('public')->url($path);

            Update::updateOrCreate(
                ['seller_id' => $seller->id, 'caption' => $captionsEn[$index % 3]],
                [
                    'product_id' => null,
                    'type' => 'image',
                    'media_path' => $url,
                    'thumb_path' => $url,
                    'caption' => ($index % 2 === 0 ? $captionsEn : $captionsSw)[$index % 3],
                    'expires_at' => now()->addHours(random_int(6, 20)),
                ],
            );
        }

        $productsBySeller = collect($products)->groupBy('seller_id');
        foreach ($verifiedSellers->take(4) as $seller) {
            $sellerProducts = $productsBySeller->get($seller->id, collect());
            if ($sellerProducts->isEmpty()) {
                continue;
            }
            $product = $sellerProducts->first();

            // Same self-healing treatment for the poster image, same fix
            // to its extension (.svg, not .jpg, for the same Content-Type
            // reason as Updates above). `video_path` is deliberately left
            // alone: there is no video-generation capability anywhere in
            // this codebase — no ffmpeg, no video library, a standing
            // constraint of this project's shared-hosting target (see
            // DECISIONS.md) — to produce a genuine placeholder clip, and
            // writing arbitrary bytes at a .mp4 path would pass an
            // exists() check while still being an unplayable file, which
            // is a more deceptive failure than a missing one.
            $thumbPath = "demo/showcases/{$seller->handle}_thumb.svg";
            $this->ensureFileExists($thumbPath, fn () => ProductPlaceholderImage::svg('more_horiz', $product->title));

            Showcase::updateOrCreate(
                ['seller_id' => $seller->id, 'product_id' => $product->id],
                [
                    // Wrapped for consistency with every real path this seeder
                    // and the real API both write — the file behind it still
                    // doesn't exist (see the no-video-generation note above),
                    // so this remains a 404, just a well-formed absolute one
                    // instead of a relative string no client can even attempt.
                    'video_path' => Storage::disk('public')->url("demo/showcases/{$seller->handle}.mp4"),
                    'thumb_path' => Storage::disk('public')->url($thumbPath),
                    'caption' => "See {$product->title} up close",
                    'duration' => random_int(10, 40),
                    'views' => random_int(20, 3000),
                ],
            );
        }
    }

    /**
     * Writes $content (lazily built — the closure never runs unless
     * actually needed) to $path only if nothing real is already there on
     * the public disk. This is what makes the seeder self-healing rather
     * than merely idempotent: a database row surviving while the file it
     * points to doesn't — a storage move/reset, or (before this fix) a
     * guard that checked "does a media row exist" instead of "does the
     * file it points to actually exist" — previously left the file never
     * rewritten. Every path passed in here is deterministic (derived from
     * the seller/product's own stable identity), so it's safe to recompute
     * and check on every run regardless of whether the owning row is new.
     */
    private function ensureFileExists(string $path, Closure $content): void
    {
        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put($path, $content());
        }
    }

    /** @return array<int, User> */
    private function seedBuyers(): array
    {
        return array_map(
            fn (array $buyer) => User::firstOrCreate(
                ['phone' => $buyer['phone']],
                ['name' => $buyer['name'], 'provider' => 'phone', 'provider_id' => $buyer['phone'], 'account_intent' => 'buy'],
            ),
            self::BUYERS,
        );
    }

    /**
     * @param array<string, SellerProfile> $sellersByHandle
     * @param array<int, Product> $products
     * @param array<int, User> $buyers
     */
    private function seedOrdersReviewsAndComments(array $sellersByHandle, array $products, array $buyers): void
    {
        $verifiedSellers = collect($sellersByHandle)->filter(fn (SellerProfile $s) => $s->isVerified())->values();
        $productsBySeller = collect($products)->groupBy('seller_id');

        // Orders across every status — enough completed ones to carry the
        // review set below, plus a realistic spread of everything else so
        // the admin dashboard and buyer/seller order timelines have
        // something real to show.
        $statusPlan = [
            ...array_fill(0, 15, 'completed'),
            ...array_fill(0, 4, 'pending'),
            ...array_fill(0, 3, 'accepted'),
            ...array_fill(0, 3, 'ready'),
            ...array_fill(0, 3, 'cancelled'),
        ];

        $completedOrders = [];
        $reviewIndex = 0;

        foreach ($statusPlan as $i => $status) {
            $seller = $verifiedSellers[$i % $verifiedSellers->count()];
            $sellerProducts = $productsBySeller->get($seller->id, collect());
            if ($sellerProducts->isEmpty()) {
                continue;
            }
            $buyer = $buyers[$i % count($buyers)];

            // A deterministic code (Order::code otherwise auto-generates a
            // random one via a creating() hook) is what makes this whole
            // order → review → comment chain idempotent: re-running without
            // --fresh must reuse the *same* order row, not mint a new one
            // with a new id every time, which would make the review below
            // (correctly keyed on order_id) create a genuine duplicate too.
            $code = 'DEMO-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $existing = Order::where('code', $code)->first();

            $factory = $status === 'pending' ? Order::factory() : Order::factory()->{$status}();
            $order = $existing ?? $factory->create(['code' => $code, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id]);

            if (! $existing) {
                $items = $sellerProducts->random(min(random_int(1, 2), $sellerProducts->count()));
                $subtotal = 0;
                foreach ($items as $item) {
                    $qty = random_int(1, 2);
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item->id,
                        'title_snapshot' => $item->title,
                        'price_snapshot' => $item->price,
                        'qty' => $qty,
                    ]);
                    $subtotal += $item->price * $qty;
                }
                $order->update(['subtotal' => $subtotal, 'total' => $subtotal + $order->delivery_fee]);
            }

            if ($status === 'completed') {
                $completedOrders[] = $order;
            }
        }

        // Reviews — mixed English/Kiswahili, mixed ratings, some with a
        // seller reply — one per completed demo order.
        foreach ($completedOrders as $order) {
            $template = self::REVIEW_TEMPLATES[$reviewIndex % count(self::REVIEW_TEMPLATES)];
            $reviewIndex++;

            Review::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'seller_id' => $order->seller_id,
                    'buyer_id' => $order->buyer_id,
                    'rating' => $template['rating'],
                    'comment' => $template['comment'],
                    'reply' => $template['reply'] ?? null,
                    'replied_at' => isset($template['reply']) ? now() : null,
                ],
            );
        }

        // Comment threads on a handful of products, with a seller reply on some.
        $commentedProducts = collect($products)->filter(fn (Product $p) => $p->seller->isVerified())->take(5)->values();
        foreach ($commentedProducts as $index => $product) {
            $buyer = $buyers[$index % count($buyers)];
            $comment = Comment::updateOrCreate(
                ['product_id' => $product->id, 'user_id' => $buyer->id, 'parent_id' => null],
                ['body' => self::COMMENT_TEMPLATES[$index % count(self::COMMENT_TEMPLATES)], 'is_hidden' => false],
            );

            if ($index % 2 === 0) {
                Comment::updateOrCreate(
                    ['product_id' => $product->id, 'user_id' => $product->seller->user_id, 'parent_id' => $comment->id],
                    ['body' => self::COMMENT_REPLY_TEMPLATES[$index % count(self::COMMENT_REPLY_TEMPLATES)], 'is_hidden' => false],
                );
            }
        }
    }
}
