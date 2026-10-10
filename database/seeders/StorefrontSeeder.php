<?php

namespace Database\Seeders;

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Organization\Organization;
use Illuminate\Database\Seeder;

class StorefrontSeeder extends Seeder
{
    public function run(): void
    {
        // Get first organization (or create one for testing)
        $org = Organization::first();

        if (! $org) {
            echo "No organization found. Please create an organization first.\n";

            return;
        }

        echo "Seeding data for organization: {$org->name}\n";

        // Create Categories with nested set values
        $categories = [
            [
                'name' => 'Electronics',
                'slug' => 'electronics',
                'description' => 'Electronic devices and accessories',
            ],
            [
                'name' => 'Clothing',
                'slug' => 'clothing',
                'description' => 'Apparel and fashion items',
            ],
            [
                'name' => 'Home & Garden',
                'slug' => 'home-garden',
                'description' => 'Home decor and garden supplies',
            ],
            [
                'name' => 'Sports & Outdoors',
                'slug' => 'sports-outdoors',
                'description' => 'Sports equipment and outdoor gear',
            ],
        ];

        $createdCategories = [];
        $lftCounter = 1;

        foreach ($categories as $index => $categoryData) {
            $lft = $lftCounter;
            $rgt = $lftCounter + 1;
            $lftCounter += 2;

            $category = Category::firstOrCreate(
                [
                    'organization_id' => $org->id,
                    'slug' => $categoryData['slug'],
                ],
                [
                    'name' => $categoryData['name'],
                    'description' => $categoryData['description'],
                    'path' => $categoryData['slug'], // ltree path
                    'lft' => $lft,
                    'rgt' => $rgt,
                    'depth' => 0,
                    'is_active' => true,
                    'sort_order' => $index,
                ]
            );
            $createdCategories[$categoryData['slug']] = $category;
            echo "Created category: {$category->name}\n";
        }

        // Create Brands
        $brands = [
            ['name' => 'TechPro', 'slug' => 'techpro'],
            ['name' => 'StyleWear', 'slug' => 'stylewear'],
            ['name' => 'HomeComfort', 'slug' => 'homecomfort'],
            ['name' => 'ActiveLife', 'slug' => 'activelife'],
        ];

        $createdBrands = [];
        foreach ($brands as $brandData) {
            $brand = Brand::firstOrCreate(
                [
                    'organization_id' => $org->id,
                    'slug' => $brandData['slug'],
                ],
                [
                    'name' => $brandData['name'],
                    'is_active' => true,
                    'sort_order' => 0,
                ]
            );
            $createdBrands[$brandData['slug']] = $brand;
            echo "Created brand: {$brand->name}\n";
        }

        // Create Products
        $products = [
            // Electronics
            [
                'category' => 'electronics',
                'brand' => 'techpro',
                'name' => 'Wireless Bluetooth Headphones',
                'short_description' => 'Premium noise-cancelling headphones',
                'description' => 'Experience superior sound quality with our premium wireless headphones featuring active noise cancellation and 30-hour battery life.',
                'sku' => 'ELEC-HP-001',
                'selling_price' => 129.99,
                'msrp' => 179.99,
                'stock_quantity' => 50,
                'type' => 'simple',
            ],
            [
                'category' => 'electronics',
                'brand' => 'techpro',
                'name' => 'Smart Watch Pro',
                'short_description' => 'Fitness tracking smartwatch',
                'description' => 'Track your fitness goals with GPS, heart rate monitor, and 7-day battery life.',
                'sku' => 'ELEC-SW-002',
                'selling_price' => 249.99,
                'msrp' => 299.99,
                'stock_quantity' => 30,
                'type' => 'simple',
            ],
            [
                'category' => 'electronics',
                'brand' => 'techpro',
                'name' => 'USB-C Fast Charger',
                'short_description' => '65W fast charging adapter',
                'description' => 'Charge your devices at lightning speed with our compact 65W USB-C charger.',
                'sku' => 'ELEC-CH-003',
                'selling_price' => 39.99,
                'msrp' => null,
                'stock_quantity' => 100,
                'type' => 'simple',
            ],

            // Clothing
            [
                'category' => 'clothing',
                'brand' => 'stylewear',
                'name' => 'Classic Cotton T-Shirt',
                'short_description' => 'Comfortable everyday tee',
                'description' => '100% organic cotton t-shirt available in multiple colors. Perfect for casual wear.',
                'sku' => 'CLO-TS-001',
                'selling_price' => 24.99,
                'msrp' => 34.99,
                'stock_quantity' => 200,
                'type' => 'variable',
            ],
            [
                'category' => 'clothing',
                'brand' => 'stylewear',
                'name' => 'Slim Fit Jeans',
                'short_description' => 'Modern slim fit denim',
                'description' => 'Premium denim jeans with stretch comfort and modern slim fit.',
                'sku' => 'CLO-JE-002',
                'selling_price' => 79.99,
                'msrp' => 99.99,
                'stock_quantity' => 80,
                'type' => 'variable',
            ],

            // Home & Garden
            [
                'category' => 'home-garden',
                'brand' => 'homecomfort',
                'name' => 'Ceramic Plant Pot Set',
                'short_description' => 'Set of 3 decorative planters',
                'description' => 'Beautiful ceramic planters in modern design. Perfect for indoor plants.',
                'sku' => 'HOME-PP-001',
                'selling_price' => 44.99,
                'msrp' => 59.99,
                'stock_quantity' => 40,
                'type' => 'bundle',
            ],
            [
                'category' => 'home-garden',
                'brand' => 'homecomfort',
                'name' => 'Memory Foam Pillow',
                'short_description' => 'Ergonomic sleep pillow',
                'description' => 'Premium memory foam pillow with cooling gel technology for better sleep.',
                'sku' => 'HOME-PI-002',
                'selling_price' => 49.99,
                'msrp' => null,
                'stock_quantity' => 60,
                'type' => 'simple',
            ],

            // Sports & Outdoors
            [
                'category' => 'sports-outdoors',
                'brand' => 'activelife',
                'name' => 'Yoga Mat Premium',
                'short_description' => 'Non-slip exercise mat',
                'description' => 'Extra thick yoga mat with excellent grip and cushioning for all exercises.',
                'sku' => 'SPO-YM-001',
                'selling_price' => 34.99,
                'msrp' => 49.99,
                'stock_quantity' => 75,
                'type' => 'simple',
            ],
            [
                'category' => 'sports-outdoors',
                'brand' => 'activelife',
                'name' => 'Water Bottle Insulated',
                'short_description' => '32oz stainless steel bottle',
                'description' => 'Keep drinks cold for 24 hours or hot for 12 hours with double-wall insulation.',
                'sku' => 'SPO-WB-002',
                'selling_price' => 29.99,
                'msrp' => 39.99,
                'stock_quantity' => 120,
                'type' => 'simple',
            ],
        ];

        foreach ($products as $productData) {
            $product = Product::create([
                'organization_id' => $org->id,
                'category_id' => $createdCategories[$productData['category']]->id,
                'brand_id' => $createdBrands[$productData['brand']]->id,
                'name' => $productData['name'],
                'slug' => \Str::slug($productData['name']),
                'short_description' => $productData['short_description'],
                'description' => $productData['description'],
                'sku' => $productData['sku'],
                'type' => $productData['type'],
                'status' => 'active',
                'selling_price' => $productData['selling_price'],
                'msrp' => $productData['msrp'],
                'currency' => 'USD',
                'stock_quantity' => $productData['stock_quantity'],
                'manage_stock' => true,
                'track_inventory' => true,
                'allow_backorders' => false,
                'requires_shipping' => true,
                'published_at' => now(),
            ]);

            // Add a placeholder image
            ProductImage::create([
                'product_id' => $product->id,
                'url' => 'https://via.placeholder.com/600x600/4F46E5/FFFFFF?text='.urlencode($product->name),
                'thumbnail_url' => 'https://via.placeholder.com/200x200/4F46E5/FFFFFF?text='.urlencode($product->name),
                'alt_text' => $product->name,
                'sort_order' => 0,
                'is_primary' => true,
            ]);

            echo "Created product: {$product->name}\n";
        }

        echo "\n✅ Seeding complete!\n";
        echo "Created:\n";
        echo '- '.count($categories)." categories\n";
        echo '- '.count($brands)." brands\n";
        echo '- '.count($products)." products\n";
    }
}
