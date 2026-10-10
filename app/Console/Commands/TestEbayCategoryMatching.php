<?php

namespace App\Console\Commands;

use App\Models\Catalog\ProductIntakeQueue;
use App\Models\Ebay\EbayCategory;
use App\Services\EbayCategoryService;
use Illuminate\Console\Command;

class TestEbayCategoryMatching extends Command
{
    protected $signature = 'ebay:test-categories 
                           {--product-id= : Test specific product intake queue ID}
                           {--search= : Search for categories by keyword}
                           {--category-id= : Test specific category ID}
                           {--brand= : Test category matching for brand}';

    protected $description = 'Test eBay category matching and search functionality';

    public function handle(EbayCategoryService $categoryService): int
    {
        $this->info('eBay Category Testing Tool');
        $this->newLine();

        // Test 1: Search categories
        if ($search = $this->option('search')) {
            $this->testCategorySearch($categoryService, $search);

            return 0;
        }

        // Test 2: Specific category details
        if ($categoryId = $this->option('category-id')) {
            $this->testCategoryDetails($categoryService, $categoryId);

            return 0;
        }

        // Test 3: Brand category suggestions
        if ($brand = $this->option('brand')) {
            $this->testBrandCategories($categoryService, $brand);

            return 0;
        }

        // Test 4: Product category matching
        if ($productId = $this->option('product-id')) {
            $this->testProductCategoryMatching($categoryService, $productId);

            return 0;
        }

        // Interactive mode - test all features
        $this->runInteractiveTests($categoryService);

        return 0;
    }

    protected function testCategorySearch(EbayCategoryService $categoryService, string $query): void
    {
        $this->info("Searching categories for: '{$query}'");
        $this->newLine();

        $results = $categoryService->searchCategories($query, 15);

        if (empty($results)) {
            $this->warn('No categories found for that search term.');

            return;
        }

        $this->table(
            ['Category ID', 'Name', 'Full Path', 'Items'],
            array_map(function ($cat) {
                return [
                    $cat['category_id'],
                    $cat['name'],
                    $cat['path'],
                    number_format($cat['listing_count'] ?? 0),
                ];
            }, $results)
        );
    }

    protected function testCategoryDetails(EbayCategoryService $categoryService, string $categoryId): void
    {
        $this->info("Category Details for ID: {$categoryId}");
        $this->newLine();

        $category = EbayCategory::where('category_id', $categoryId)->first();

        if (! $category) {
            $this->error("Category {$categoryId} not found. Run 'php artisan ebay:sync-categories' first.");

            return;
        }

        // Basic info
        $this->table(
            ['Property', 'Value'],
            [
                ['Category ID', $category->category_id],
                ['Name', $category->name],
                ['Full Path', $category->getFullPath()],
                ['Level', $category->level],
                ['Is Leaf', $category->is_leaf ? 'Yes (can list items)' : 'No (parent category)'],
                ['Item Count', number_format($category->item_count ?? 0)],
                ['Active', $category->is_active ? 'Yes' : 'No'],
            ]
        );

        // Required item specifics
        $this->newLine();
        $this->info('Required Item Specifics:');

        try {
            $specifics = $categoryService->getRequiredItemSpecifics($categoryId);

            if (empty($specifics)) {
                $this->line('No required specifics found (or API call failed).');
            } else {
                foreach ($specifics as $specific) {
                    $name = $specific['name'] ?? 'Unknown';
                    $required = $specific['cardinality'] === 'REQUIRED' ? 'REQUIRED' : 'OPTIONAL';
                    $this->line("  • {$name} ({$required})");
                }
            }
        } catch (\Exception $e) {
            $this->error('Failed to get specifics: '.$e->getMessage());
        }

        // Similar categories
        $similar = $category->getSimilarCategories(5);
        if (! empty($similar)) {
            $this->newLine();
            $this->info('Similar Categories:');
            foreach ($similar as $sim) {
                $this->line("  • {$sim['name']} ({$sim['category_id']})");
            }
        }
    }

    protected function testBrandCategories(EbayCategoryService $categoryService, string $brand): void
    {
        $this->info("Popular categories for brand: {$brand}");
        $this->newLine();

        $categories = EbayCategory::getPopularForBrand($brand, 10);

        if (empty($categories)) {
            $this->warn("No categories found for brand '{$brand}'");

            return;
        }

        $this->table(
            ['Category ID', 'Name', 'Full Path', 'Items'],
            array_map(function ($cat) {
                return [
                    $cat['category_id'],
                    $cat['name'],
                    $cat['path'],
                    number_format($cat['item_count'] ?? 0),
                ];
            }, $categories)
        );
    }

    protected function testProductCategoryMatching(EbayCategoryService $categoryService, int $productId): void
    {
        $this->info("Testing category matching for Product Intake Queue #{$productId}");
        $this->newLine();

        $queueItem = ProductIntakeQueue::find($productId);

        if (! $queueItem) {
            $this->error("Product intake queue item #{$productId} not found.");

            return;
        }

        // Show product data
        $enrichedData = $queueItem->enriched_data;
        $mergedFields = $enrichedData['merged_product_fields'] ?? [];

        $this->table(
            ['Field', 'Value'],
            [
                ['NCR ID', $queueItem->manufacturer_part_number ?? 'N/A'],
                ['Product Number', $queueItem->product_number ?? 'N/A'],
                ['Brand', $queueItem->brand ?? $mergedFields['product_identity']['brand'] ?? 'N/A'],
                ['Title', $mergedFields['listing_optimization']['suggested_title'] ?? $mergedFields['product_identity']['title'] ?? 'N/A'],
                ['User Notes', $queueItem->user_notes ?? 'N/A'],
            ]
        );

        // Test category matching
        $this->newLine();
        $this->info('Finding best category match...');

        try {
            $productData = [
                'title' => $mergedFields['listing_optimization']['suggested_title'] ?? '',
                'description' => $mergedFields['product_details']['description'] ?? '',
                'brand' => $mergedFields['product_identity']['brand'] ?? $queueItem->brand ?? '',
                'model' => $mergedFields['product_identity']['model'] ?? '',
                'category' => $mergedFields['product_identity']['category'] ?? '',
                'keywords' => $mergedFields['listing_optimization']['primary_keywords'] ?? [],
            ];

            $result = $categoryService->findBestCategory($productData);

            $this->table(
                ['Property', 'Value'],
                [
                    ['Best Match', $result['name'] ?? 'N/A'],
                    ['Category ID', $result['category_id'] ?? 'N/A'],
                    ['Full Path', $result['path'] ?? 'N/A'],
                    ['Confidence', $result['confidence'].'%'],
                    ['Can List Items', $result['is_leaf'] ? 'Yes' : 'No'],
                    ['Item Count', number_format($result['item_count'] ?? 0)],
                ]
            );

            // Validate the choice
            if ($result['category_id']) {
                $this->newLine();
                $this->info('Validating category choice...');

                $validation = $categoryService->validateCategoryChoice($result['category_id'], $productData);

                if ($validation['valid']) {
                    $this->info('Category choice is valid for eBay listing!');
                } else {
                    $this->warn('Category validation issues:');
                    $this->line('  Error: '.$validation['error']);

                    if (! empty($validation['missing_specifics'])) {
                        $this->line('  Missing specifics: '.implode(', ', $validation['missing_specifics']));
                    }
                }

                if (! empty($validation['warnings'])) {
                    $this->warn('Warnings:');
                    foreach ($validation['warnings'] as $warning) {
                        $this->line("  • {$warning}");
                    }
                }
            }

        } catch (\Exception $e) {
            $this->error('Category matching failed: '.$e->getMessage());
        }
    }

    protected function runInteractiveTests(EbayCategoryService $categoryService): void
    {
        $this->info('Interactive eBay Category Testing');
        $this->newLine();

        while (true) {
            $choice = $this->choice(
                'What would you like to test?',
                [
                    'search' => 'Search categories',
                    'brand' => 'Test brand categories',
                    'product' => 'Test product matching',
                    'category' => 'Get category details',
                    'stats' => 'Show category statistics',
                    'quit' => 'Quit',
                ],
                'search'
            );

            switch ($choice) {
                case 'search':
                    $query = $this->ask('Enter search term:');
                    if ($query) {
                        $this->testCategorySearch($categoryService, $query);
                    }
                    break;

                case 'brand':
                    $brand = $this->ask('Enter brand name (e.g., NCR, IBM, HP):');
                    if ($brand) {
                        $this->testBrandCategories($categoryService, $brand);
                    }
                    break;

                case 'product':
                    $productId = $this->ask('Enter Product Intake Queue ID:');
                    if ($productId && is_numeric($productId)) {
                        $this->testProductCategoryMatching($categoryService, (int) $productId);
                    }
                    break;

                case 'category':
                    $categoryId = $this->ask('Enter eBay Category ID:');
                    if ($categoryId) {
                        $this->testCategoryDetails($categoryService, $categoryId);
                    }
                    break;

                case 'stats':
                    $this->showCategoryStats();
                    break;

                case 'quit':
                    $this->info('Goodbye!');

                    return;
            }

            $this->newLine(2);
        }
    }

    protected function showCategoryStats(): void
    {
        $this->info('eBay Category Statistics');
        $this->newLine();

        $totalCategories = EbayCategory::count();
        $leafCategories = EbayCategory::where('is_leaf', true)->count();
        $activeCategories = EbayCategory::where('is_active', true)->count();
        $lastSync = EbayCategory::max('last_synced_at');

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Categories', number_format($totalCategories)],
                ['Leaf Categories (can list)', number_format($leafCategories)],
                ['Active Categories', number_format($activeCategories)],
                ['Last Sync', $lastSync ? $lastSync->format('Y-m-d H:i:s') : 'Never'],
            ]
        );

        // Top level categories
        $this->newLine();
        $this->info('Top Level Categories:');

        $topCategories = EbayCategory::whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(10)
            ->get(['category_id', 'name']);

        foreach ($topCategories as $cat) {
            $childCount = $cat->children()->count();
            $leafCount = $cat->children()->where('is_leaf', true)->count();
            $this->line("  • {$cat->name} ({$cat->category_id}) - {$childCount} subcategories, {$leafCount} listing categories");
        }

        // Check if sync needed
        if (EbayCategory::needsSync()) {
            $this->newLine();
            $this->warn('Categories are outdated. Run: php artisan ebay:sync-categories');
        }
    }
}
