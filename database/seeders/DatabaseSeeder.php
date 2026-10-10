<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseSeeder extends Seeder
{
    /**
     * 🚀 COMPREHENSIVE E-COMMERCE SEEDER SYSTEM
     *
     * This seeder creates complete test data for the entire eBay automation pipeline:
     * - Multi-tenant organizations with teams and users
     * - Product categories with eBay mappings
     * - Realistic product intake queue with AI analysis
     * - Complete products with eBay listings
     * - Inventory management and supplier data
     * - Error scenarios and edge cases
     *
     * Result: Full end-to-end testing data from capture → eBay listing
     */
    public function run(): void
    {
        Log::info('🚀 Starting comprehensive e-commerce seeder system...');

        // Disable foreign key checks for clean seeding
        DB::statement("SET session_replication_role = 'replica';");

        $startTime = microtime(true);

        try {
            Log::info('📋 PHASE 1: Foundation - Organizations, Teams & Users');
            $this->call([
                OrganizationSeeder::class,
                UserSeeder::class,
                RoleSeeder::class,
                TeamSeeder::class,
                OnboardingProgressSeeder::class,
            ]);

            // PHASE 2: PRODUCT STRUCTURE (Categories & Brands)
            Log::info('🏷️ PHASE 2: Product Structure - Categories & Brands');
            $this->call([
                CategorySeeder::class,          // Hierarchical categories
                BrandSeeder::class,             // NCR + major brands
                EbayCategorySeeder::class,      // Real eBay category mappings
                SupplierSeeder::class,          // Product suppliers
                AiCategoryPromptSeeder::class,  // AI prompts for category analysis
            ]);

            // PHASE 3: INVENTORY SYSTEM
            Log::info('📦 PHASE 3: Inventory System');
            $this->call([
                InventoryLocationSeeder::class, // Warehouses/locations
            ]);

            // PHASE 4: PRODUCT INTAKE PIPELINE
            Log::info('🤖 PHASE 4: Product Intake Pipeline (THE BIG ONE!)');
            $this->call([
                ProductIntakeQueueSeeder::class,    // 200+ realistic queue items
                ProductAiAnalysisSeeder::class,     // AI analysis records
                ProductSeeder::class,               // Processed products
                EbayListingSeederFinal::class,          // eBay integration
            ]);

            // PHASE 5: SUPPORTING DATA
            Log::info('⚙️ PHASE 5: Supporting Systems');
            $this->call([
                InventoryLevelSeeder::class,        // Stock levels
                ProductImageSeeder::class,          // Product images
                SupplierProductSeeder::class,       // Supplier relationships
                SupplierFinancialSeeder::class,     // Supplier tiers, commissions, settlements, performance
            ]);

            // PHASE 6: EDGE CASES & TEST SCENARIOS
            Log::info('🧪 PHASE 6: Edge Cases & Test Scenarios');
            $this->call([
                // EdgeCaseSeeder::class, // Disabled - schema mismatch              // Error conditions
                // WorkflowTestSeeder::class, // Disabled - schema mismatch          // End-to-end scenarios
            ]);

            $endTime = microtime(true);
            $totalTime = round($endTime - $startTime, 2);

            Log::info('✅ SEEDING COMPLETE!', [
                'total_time_seconds' => $totalTime,
                'organizations' => DB::table('organizations')->count(),
                'users' => DB::table('users')->count(),
                'categories' => DB::table('categories')->count(),
                'brands' => DB::table('brands')->count(),
                'queue_items' => DB::table('product_intake_queue')->count(),
                'products' => DB::table('products')->count(),
                'ai_analyses' => DB::table('product_ai_analyses')->count(),
                'ebay_listings' => DB::table('ebay_listings')->count(),
                'ai_prompts' => DB::table('ai_category_prompts')->count(),
            ]);

            $this->command->info('🎉 COMPREHENSIVE E-COMMERCE DATA SEEDED SUCCESSFULLY!');
            $this->command->info("⏱️  Total time: {$totalTime}s");
            $this->command->info('🚀 Ready for full eBay automation pipeline testing!');

        } catch (\Exception $e) {
            Log::error('❌ Seeding failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } finally {
            // Re-enable foreign key checks
            DB::statement("SET session_replication_role = 'origin';");
        }
    }
}
