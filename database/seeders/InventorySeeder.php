<?php

namespace Database\Seeders;

use App\Models\Catalog\Category;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use App\Services\Inventory\CategoryService;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\ProductService;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::where('name', 'Demo Organization')->first();

        if (! $organization) {
            $this->command->error('Demo Organization not found. Please run OrganizationSeeder first.');

            return;
        }

        $this->seedCategories($organization);
        $this->seedLocations($organization);
        $this->seedSuppliers($organization);
        $this->seedProducts($organization);
    }

    private function seedCategories(Organization $organization): void
    {
        $categoryService = app(CategoryService::class);

        // Root categories
        $electronics = $categoryService->createCategory([
            'organization_id' => $organization->id,
            'name' => 'Electronics',
            'slug' => 'electronics',
            'description' => 'Electronic devices and accessories',
            'is_active' => true,
            'created_by' => $organization->users()->first()->id,
        ]);

        $clothing = $categoryService->createCategory([
            'organization_id' => $organization->id,
            'name' => 'Clothing',
            'slug' => 'clothing',
            'description' => 'Apparel and fashion items',
            'is_active' => true,
            'created_by' => $organization->users()->first()->id,
        ]);

        // Subcategories for Electronics
        $categoryService->createCategory([
            'organization_id' => $organization->id,
            'name' => 'Smartphones',
            'slug' => 'smartphones',
            'description' => 'Mobile phones and accessories',
            'parent_id' => $electronics->id,
            'is_active' => true,
            'created_by' => $organization->users()->first()->id,
        ]);

        $categoryService->createCategory([
            'organization_id' => $organization->id,
            'name' => 'Laptops',
            'slug' => 'laptops',
            'description' => 'Laptop computers and accessories',
            'parent_id' => $electronics->id,
            'is_active' => true,
            'created_by' => $organization->users()->first()->id,
        ]);

        // Subcategories for Clothing
        $categoryService->createCategory([
            'organization_id' => $organization->id,
            'name' => 'Men\'s Clothing',
            'slug' => 'mens-clothing',
            'description' => 'Clothing for men',
            'parent_id' => $clothing->id,
            'is_active' => true,
            'created_by' => $organization->users()->first()->id,
        ]);

        $categoryService->createCategory([
            'organization_id' => $organization->id,
            'name' => 'Women\'s Clothing',
            'slug' => 'womens-clothing',
            'description' => 'Clothing for women',
            'parent_id' => $clothing->id,
            'is_active' => true,
            'created_by' => $organization->users()->first()->id,
        ]);
    }

    private function seedLocations(Organization $organization): void
    {
        InventoryLocation::create([
            'organization_id' => $organization->id,
            'name' => 'Main Warehouse',
            'code' => 'WH001',
            'type' => 'warehouse',
            'address_line1' => '1234 Industrial Blvd',
            'city' => 'Distribution City',
            'state' => 'CA',
            'postal_code' => '90210',
            'country' => 'United States',
            'contact_person' => 'Warehouse Manager',
            'email' => 'warehouse@demo.com',
            'phone' => '+1-555-0123',
            'is_active' => true,
            'is_default' => true,
        ]);

        InventoryLocation::create([
            'organization_id' => $organization->id,
            'name' => 'Retail Store Downtown',
            'code' => 'ST001',
            'type' => 'store',
            'address_line1' => '567 Main Street',
            'city' => 'Downtown',
            'state' => 'CA',
            'postal_code' => '90211',
            'country' => 'United States',
            'contact_person' => 'Store Manager',
            'email' => 'store@demo.com',
            'phone' => '+1-555-0124',
            'is_active' => true,
            'is_default' => false,
        ]);

        InventoryLocation::create([
            'organization_id' => $organization->id,
            'name' => 'Online Fulfillment Center',
            'code' => 'FC001',
            'type' => 'warehouse',
            'address_line1' => '789 Logistics Way',
            'city' => 'Fulfillment City',
            'state' => 'NV',
            'postal_code' => '89101',
            'country' => 'United States',
            'contact_person' => 'FC Manager',
            'email' => 'fulfillment@demo.com',
            'phone' => '+1-555-0125',
            'is_active' => true,
            'is_default' => false,
        ]);
    }

    private function seedSuppliers(Organization $organization): void
    {
        Supplier::create([
            'organization_id' => $organization->id,
            'name' => 'TechSource Electronics',
            'code' => 'SUP001',
            'email' => 'orders@techsource.com',
            'phone' => '+1-555-2000',
            'website' => 'https://techsource.com',
            'address_line1' => '100 Technology Drive',
            'city' => 'Tech Valley',
            'state' => 'CA',
            'postal_code' => '95110',
            'country' => 'United States',
            'tax_id' => '12-3456789',
            'payment_terms' => 30,
            'currency' => 'USD',
            'minimum_order_amount' => 500.00,
            'lead_time_days' => 7,
            'performance_rating' => 4.5,
            'is_active' => true,
            'is_preferred' => true,
            'created_by' => $organization->users()->first()->id,
        ]);

        Supplier::create([
            'organization_id' => $organization->id,
            'name' => 'Fashion Forward Inc',
            'code' => 'SUP002',
            'email' => 'wholesale@fashionforward.com',
            'phone' => '+1-555-3000',
            'website' => 'https://fashionforward.com',
            'address_line1' => '200 Fashion Avenue',
            'city' => 'Style City',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'United States',
            'tax_id' => '98-7654321',
            'payment_terms' => 45,
            'currency' => 'USD',
            'minimum_order_amount' => 1000.00,
            'lead_time_days' => 14,
            'performance_rating' => 4.2,
            'is_active' => true,
            'is_preferred' => false,
            'created_by' => $organization->users()->first()->id,
        ]);

        Supplier::create([
            'organization_id' => $organization->id,
            'name' => 'Global Gadgets Ltd',
            'code' => 'SUP003',
            'email' => 'sales@globalgadgets.com',
            'phone' => '+44-20-7123-4567',
            'website' => 'https://globalgadgets.com',
            'address_line1' => '50 Innovation Street',
            'city' => 'London',
            'postal_code' => 'EC1A 1BB',
            'country' => 'United Kingdom',
            'payment_terms' => 30,
            'currency' => 'GBP',
            'minimum_order_amount' => 750.00,
            'lead_time_days' => 10,
            'performance_rating' => 4.0,
            'is_active' => true,
            'is_preferred' => false,
            'created_by' => $organization->users()->first()->id,
        ]);
    }

    private function seedProducts(Organization $organization): void
    {
        $productService = app(ProductService::class);
        $inventoryService = app(InventoryService::class);

        $categories = Category::where('organization_id', $organization->id)->get();
        $locations = InventoryLocation::where('organization_id', $organization->id)->get();
        $mainWarehouse = $locations->where('is_default', true)->first();
        $userId = $organization->users()->first()->id;

        // Electronics Products
        $smartphone = $productService->createProduct([
            'organization_id' => $organization->id,
            'category_id' => $categories->where('slug', 'smartphones')->first()?->id,
            'name' => 'Premium Smartphone X1',
            'short_description' => 'Latest flagship smartphone with advanced features',
            'description' => 'A cutting-edge smartphone featuring a 6.7-inch OLED display, 128GB storage, triple camera system, and 5G connectivity.',
            'sku' => 'PHONE-X1-001',
            'type' => 'variable',
            'status' => 'active',
            'cost_price' => 450.00,
            'selling_price' => 799.99,
            'msrp' => 899.99,
            'currency' => 'USD',
            'weight' => 0.175,
            'length' => 15.8,
            'width' => 7.4,
            'height' => 0.8,
            'track_inventory' => true,
            'manage_stock' => true,
            'low_stock_threshold' => 10,
            'requires_shipping' => true,
            'attributes' => [
                'brand' => 'TechPro',
                'model' => 'X1',
                'storage' => '128GB',
                'color_options' => ['Black', 'White', 'Blue'],
                'warranty' => '1 Year',
            ],
            'search_keywords' => 'smartphone, mobile, phone, 5G, camera',
            'created_by' => $userId,
            'published_at' => now(),
        ]);

        // Add initial inventory
        $inventoryService->adjustStock(
            $smartphone->id,
            $mainWarehouse->id,
            50,
            'Initial stock',
            null,
            450.00
        );

        $laptop = $productService->createProduct([
            'organization_id' => $organization->id,
            'category_id' => $categories->where('slug', 'laptops')->first()?->id,
            'name' => 'Professional Laptop Pro 15',
            'short_description' => 'High-performance laptop for professionals',
            'description' => 'A powerful 15-inch laptop with Intel i7 processor, 16GB RAM, 512GB SSD, and dedicated graphics card. Perfect for work and creativity.',
            'sku' => 'LAPTOP-PRO15-001',
            'type' => 'simple',
            'status' => 'active',
            'cost_price' => 850.00,
            'selling_price' => 1299.99,
            'msrp' => 1499.99,
            'currency' => 'USD',
            'weight' => 2.1,
            'length' => 35.8,
            'width' => 24.7,
            'height' => 2.0,
            'track_inventory' => true,
            'manage_stock' => true,
            'low_stock_threshold' => 5,
            'requires_shipping' => true,
            'attributes' => [
                'brand' => 'CompuTech',
                'processor' => 'Intel i7',
                'ram' => '16GB',
                'storage' => '512GB SSD',
                'screen_size' => '15.6"',
                'warranty' => '3 Years',
            ],
            'search_keywords' => 'laptop, computer, professional, intel, SSD',
            'created_by' => $userId,
            'published_at' => now(),
        ]);

        $inventoryService->adjustStock(
            $laptop->id,
            $mainWarehouse->id,
            25,
            'Initial stock',
            null,
            850.00
        );

        // Clothing Products
        $mensShirt = $productService->createProduct([
            'organization_id' => $organization->id,
            'category_id' => $categories->where('slug', 'mens-clothing')->first()?->id,
            'name' => 'Classic Cotton Dress Shirt',
            'short_description' => 'Premium cotton dress shirt for men',
            'description' => 'A timeless dress shirt made from 100% premium cotton. Features a classic fit, button-down collar, and wrinkle-resistant finish.',
            'sku' => 'SHIRT-M-001',
            'type' => 'variable',
            'status' => 'active',
            'cost_price' => 25.00,
            'selling_price' => 59.99,
            'msrp' => 79.99,
            'currency' => 'USD',
            'weight' => 0.3,
            'track_inventory' => true,
            'manage_stock' => true,
            'low_stock_threshold' => 20,
            'requires_shipping' => true,
            'attributes' => [
                'brand' => 'StyleCorp',
                'material' => '100% Cotton',
                'fit' => 'Classic',
                'care' => 'Machine Washable',
                'colors' => ['White', 'Blue', 'Light Blue', 'Grey'],
                'sizes' => ['S', 'M', 'L', 'XL', 'XXL'],
            ],
            'search_keywords' => 'shirt, dress shirt, cotton, mens, formal',
            'created_by' => $userId,
            'published_at' => now(),
        ]);

        $inventoryService->adjustStock(
            $mensShirt->id,
            $mainWarehouse->id,
            100,
            'Initial stock',
            null,
            25.00
        );

        $this->command->info('Inventory data seeded successfully!');
        $this->command->info("- Created {$categories->count()} categories");
        $this->command->info("- Created {$locations->count()} locations");
        $this->command->info('- Created 3 suppliers');
        $this->command->info('- Created 3 products with initial inventory');
    }
}
