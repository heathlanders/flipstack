<?php

namespace Database\Seeders;

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Organization\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * 📦 PRODUCT SEEDER - Creates actual products from processed queue items
     *
     * This seeder creates realistic products that would result from successful
     * ProcessQueueItem → CreateEbayListingJob workflow
     */
    public function run(): void
    {
        $organizations = Organization::all();

        if ($organizations->isEmpty()) {
            $this->command->error('❌ No organizations found. Run OrganizationSeeder first.');

            return;
        }

        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($organizations as $organization) {
            $counts = $this->createProductsForOrganization($organization);
            $totalCreated += $counts['created'];
            $totalSkipped += $counts['skipped'];
        }

        $this->command->info("✅ Created {$totalCreated} products from processed queue items");
        if ($totalSkipped > 0) {
            $this->command->info("   ↻ Skipped {$totalSkipped} existing products");
        }
    }

    private function createProductsForOrganization(Organization $organization): array
    {
        // Get processed queue items for this organization that should have products
        $processedQueueItems = DB::table('product_intake_queue')
            ->where('organization_id', $organization->id)
            ->whereIn('processing_status', ['ready', 'listed_on_ebay'])
            ->whereNotNull('ai_confidence_score')
            ->where('ai_confidence_score', '>=', 0.7)
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($processedQueueItems as $queueItem) {
            // Check if product already exists (idempotent)
            if ($queueItem->product_id) {
                $skipped++;

                continue;
            }

            $this->createProductFromQueueItem($organization, $queueItem);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    private function createProductFromQueueItem(Organization $organization, $queueItem): void
    {
        // Get enriched data
        $enrichedData = json_decode($queueItem->enriched_data ?? '{}', true);
        $mergedFields = $enrichedData['merged_product_fields'] ?? [];
        $productIdentity = $mergedFields['product_identity'] ?? [];
        $productDetails = $mergedFields['product_details'] ?? [];
        $marketPricing = $mergedFields['market_pricing'] ?? [];
        $listingOptimization = $mergedFields['listing_optimization'] ?? [];

        // Find or create category and brand
        $category = $this->findOrCreateCategory($organization, $productIdentity['category'] ?? null);
        $brand = $this->findOrCreateBrand($organization, $queueItem->brand ?? $productIdentity['brand'] ?? null);

        // Generate unique SKU
        $sku = $this->generateUniqueSku($queueItem);
        $slug = $this->generateUniqueSlug($productIdentity['title'] ?? 'product');

        // Create the product
        $productData = [
            'id' => Str::uuid(),
            'organization_id' => $organization->id,
            'category_id' => $category?->id,
            'brand_id' => $brand?->id,
            'name' => $productIdentity['title'] ?? $this->generateProductName($queueItem),
            'slug' => $slug,
            'short_description' => $this->generateShortDescription($queueItem, $productDetails),
            'description' => $productDetails['description'] ?? $queueItem->user_notes ?? 'Product description not available.',
            'sku' => $sku,
            'barcode' => $queueItem->barcode,
            'type' => $this->determineProductType($queueItem),
            'status' => $queueItem->processing_status === 'listed_on_ebay' ? 'active' : 'draft',
            'cost_price' => $this->calculateCostPrice($marketPricing['recommended_list_price'] ?? 0),
            'selling_price' => $marketPricing['recommended_list_price'] ?? 0,
            'msrp' => $this->calculateMsrp($marketPricing['recommended_list_price'] ?? 0),
            'currency' => 'USD',
            'weight' => $this->estimateWeight($queueItem),
            'length' => $this->estimateDimension($queueItem, 'length'),
            'width' => $this->estimateDimension($queueItem, 'width'),
            'height' => $this->estimateDimension($queueItem, 'height'),
            'track_inventory' => true,
            'manage_stock' => true,
            'stock_quantity' => $queueItem->quick_quantity ?? 1,
            'low_stock_threshold' => 1,
            'allow_backorders' => false,
            'requires_shipping' => true,
            'shipping_class' => $this->determineShippingClass($queueItem),
            'seo_title' => $listingOptimization['suggested_title'] ?? null,
            'seo_description' => $this->generateSeoDescription($queueItem, $productDetails),
            'search_keywords' => implode(',', $listingOptimization['primary_keywords'] ?? []),
            'attributes' => json_encode($this->generateProductAttributes($queueItem, $mergedFields)),
            'featured_image' => $this->getFeaturedImage($queueItem),
            'gallery_images' => json_encode($this->getGalleryImages($queueItem)), // JSON encode!
            'published_at' => $queueItem->processing_status === 'listed_on_ebay' ? $queueItem->updated_at : null,
            'created_by' => $queueItem->captured_by,
            'created_at' => $queueItem->updated_at,
            'updated_at' => now(),
            'version' => 1,

            // Product identifiers
            'upc' => $productIdentity['upc'] ?? $queueItem->barcode,
            'ean' => $productIdentity['ean'] ?? null,
            'isbn' => null,
            'mpn' => $productIdentity['manufacturer_part_number'] ?? $queueItem->manufacturer_part_number ?? $queueItem->product_number,
            'asin' => null,
            'epid' => null,
            'gtin_array' => json_encode(array_filter([ // JSON encode!
                'upc' => $productIdentity['upc'] ?? $queueItem->barcode,
                'ean' => $productIdentity['ean'] ?? null,
            ])),
            'model_number' => $this->extractModelNumber($queueItem, $productIdentity),
            'series' => $this->extractSeries($queueItem),

            // Package dimensions (estimated)
            'weight_lbs' => $this->estimateWeight($queueItem, 'lbs'),
            'package_weight_lbs' => $this->estimateWeight($queueItem, 'lbs', true),
            'package_length' => $this->estimateDimension($queueItem, 'length', true),
            'package_width' => $this->estimateDimension($queueItem, 'width', true),
            'package_height' => $this->estimateDimension($queueItem, 'height', true),

            // Product features
            'features_bullets' => json_encode($listingOptimization['bullet_points'] ?? $this->generateFeatureBullets($queueItem)), // JSON encode!
            'whats_in_box' => $this->generateWhatsInBox($queueItem),
            'specifications_table' => json_encode($productDetails['specifications'] ?? []), // JSON encode!

            // Condition and compliance
            'item_condition' => $this->mapCondition($queueItem->condition ?? 'USED_GOOD'),
            'condition_details' => $queueItem->condition_description,
            'warranty_type' => $this->determineWarrantyType($queueItem),
            'warranty_duration' => $this->determineWarrantyDuration($queueItem),
            'warranty_details' => $this->generateWarrantyDetails($queueItem),
            'requires_age_verification' => $this->requiresAgeVerification($queueItem),
            'compliance_certifications' => json_encode($this->getComplianceCertifications($queueItem)), // JSON encode!
            'safety_warnings' => json_encode($this->getSafetyWarnings($queueItem)), // JSON encode!

            // Multi-pack and listing readiness
            'is_multipack' => ($queueItem->quick_quantity ?? 1) > 1,
            'multipack_quantity' => max(1, $queueItem->quick_quantity ?? 1),
            'ready_for_listing' => $queueItem->processing_status === 'ready',
            'listing_checklist' => json_encode($this->generateListingChecklist($queueItem, $enrichedData)), // JSON encode!

            // NCR-specific fields
            'manufacturer_part_number' => $queueItem->manufacturer_part_number,
            'product_number' => $queueItem->product_number,

            // Inventory management
            'quantity' => $queueItem->quick_quantity ?? 1,
            'reserved_quantity' => 0,
            'available_quantity' => $queueItem->quick_quantity ?? 1,
            'minimum_quantity' => 1,
            'maximum_quantity' => ($queueItem->quick_quantity ?? 1) * 10,
            'reorder_point' => 1,
            'reorder_quantity' => 5,
            'cost_per_unit' => $this->calculateCostPrice($marketPricing['recommended_list_price'] ?? 0),
            'last_stock_update' => now(),
            'stock_status' => 'in_stock',
            'condition' => $this->mapCondition($queueItem->condition ?? 'USED_GOOD'),
            'condition_description' => $queueItem->condition_description,
        ];

        // Insert the product
        DB::table('products')->insert($productData);

        // Update the queue item to link to the created product
        DB::table('product_intake_queue')
            ->where('id', $queueItem->id)
            ->update(['product_id' => $productData['id']]);

        // Create product variations if applicable
        $this->createProductVariations($productData['id'], $queueItem);

        // Create product identifiers
        $this->createProductIdentifiers($productData['id'], $queueItem, $productIdentity);
    }

    private function findOrCreateCategory(Organization $organization, ?string $categoryName): ?object
    {
        if (! $categoryName) {
            return null;
        }

        return DB::table('categories')
            ->where('organization_id', $organization->id)
            ->where('name', 'LIKE', "%{$categoryName}%")
            ->first();
    }

    private function findOrCreateBrand(Organization $organization, ?string $brandName): ?object
    {
        if (! $brandName) {
            return null;
        }

        return DB::table('brands')
            ->where('organization_id', $organization->id)
            ->where('name', $brandName)
            ->first();
    }

    private function generateUniqueSku($queueItem): string
    {
        $base = $queueItem->manufacturer_part_number ?? $queueItem->product_number ?? 'ITEM';
        $base = preg_replace('/[^a-zA-Z0-9\-]/', '', $base);
        $base = strtoupper($base);

        if (strlen($base) < 3) {
            $base = 'ITEM-'.$base;
        }

        $counter = 0;
        do {
            $sku = $counter > 0 ? "{$base}-{$counter}" : $base;
            $counter++;
            $exists = DB::table('products')->where('sku', $sku)->exists();
        } while ($exists && $counter < 100);

        return $sku;
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);

        $counter = 0;
        do {
            $slug = $counter > 0 ? "{$baseSlug}-{$counter}" : $baseSlug;
            $counter++;
            $exists = DB::table('products')->where('slug', $slug)->exists();
        } while ($exists && $counter < 100);

        return $slug;
    }

    private function generateProductName($queueItem): string
    {
        $brand = $queueItem->brand ?? '';
        $notes = $queueItem->user_notes ?? '';

        // Try to extract a meaningful name from user notes
        if (preg_match('/^([^.]{10,50})/', $notes, $matches)) {
            $name = trim($matches[1]);
            if ($brand && ! str_contains($name, $brand)) {
                return "{$brand} {$name}";
            }

            return $name;
        }

        // Fallback to NCR ID or product number
        if ($queueItem->manufacturer_part_number) {
            return ($brand ? "{$brand} " : '')."Part {$queueItem->manufacturer_part_number}";
        }

        if ($queueItem->product_number) {
            return ($brand ? "{$brand} " : '')."Model {$queueItem->product_number}";
        }

        return ($brand ? "{$brand} " : '').'Electronic Equipment';
    }

    private function generateShortDescription($queueItem, array $productDetails): string
    {
        $condition = $this->mapCondition($queueItem->condition ?? 'USED_GOOD');
        $brand = $queueItem->brand ?? '';

        if (! empty($productDetails['key_features'][0])) {
            return $productDetails['key_features'][0]." - {$condition} condition.";
        }

        $category = $this->determineProductCategory($queueItem);

        return "{$brand} {$category} in {$condition} condition. Professional grade equipment.";
    }

    private function determineProductType($queueItem): string
    {
        $notes = strtolower($queueItem->user_notes ?? '');
        $quantity = $queueItem->quick_quantity ?? 1;

        // Check if it's a multi-pack (bundle)
        if ($quantity > 1) {
            return 'bundle';
        }

        // Check if it's digital
        if (str_contains($notes, 'software') || str_contains($notes, 'digital') || str_contains($notes, 'license')) {
            return 'digital';
        }

        // Most physical items are simple products
        return 'simple';
    }

    private function calculateCostPrice(float $sellingPrice): float
    {
        if ($sellingPrice <= 0) {
            return 0;
        }

        // Assume 25% markup (cost = selling / 1.25)
        return round($sellingPrice / 1.25, 2);
    }

    private function calculateMsrp(float $sellingPrice): float
    {
        if ($sellingPrice <= 0) {
            return 0;
        }

        // Assume MSRP is 20% higher than selling price
        return round($sellingPrice * 1.2, 2);
    }

    private function estimateWeight($queueItem, string $unit = 'kg', bool $withPackaging = false): float
    {
        $notes = strtolower($queueItem->user_notes ?? '');
        $baseWeight = 1.0; // Default 1kg

        // Estimate based on product type
        if (str_contains($notes, 'terminal') || str_contains($notes, 'pos')) {
            $baseWeight = 8.5; // Terminals are heavy
        } elseif (str_contains($notes, 'laptop')) {
            $baseWeight = 2.2;
        } elseif (str_contains($notes, 'phone') || str_contains($notes, 'mobile')) {
            $baseWeight = 0.2;
        } elseif (str_contains($notes, 'tablet')) {
            $baseWeight = 0.6;
        } elseif (str_contains($notes, 'scanner')) {
            $baseWeight = 0.8;
        } elseif (str_contains($notes, 'printer')) {
            $baseWeight = 3.5;
        } elseif (str_contains($notes, 'keyboard')) {
            $baseWeight = 0.8;
        } elseif (str_contains($notes, 'cable')) {
            $baseWeight = 0.1;
        }

        if ($withPackaging) {
            $baseWeight *= 1.15; // Add 15% for packaging
        }

        // Convert to pounds if requested
        if ($unit === 'lbs') {
            $baseWeight *= 2.20462;
        }

        return round($baseWeight, 2);
    }

    private function estimateDimension($queueItem, string $dimension, bool $withPackaging = false): float
    {
        $notes = strtolower($queueItem->user_notes ?? '');

        // Base dimensions in inches [length, width, height]
        $dimensions = [12, 8, 4]; // Default small box

        if (str_contains($notes, 'terminal') || str_contains($notes, 'pos')) {
            $dimensions = [16, 12, 8]; // Terminal size
        } elseif (str_contains($notes, 'laptop')) {
            $dimensions = [14, 10, 1.5];
        } elseif (str_contains($notes, 'phone')) {
            $dimensions = [6, 3, 0.5];
        } elseif (str_contains($notes, 'tablet')) {
            $dimensions = [10, 7, 0.5];
        } elseif (str_contains($notes, 'printer')) {
            $dimensions = [18, 14, 10];
        }

        $dimensionMap = [
            'length' => 0,
            'width' => 1,
            'height' => 2,
        ];

        $value = $dimensions[$dimensionMap[$dimension]] ?? 8;

        if ($withPackaging) {
            $value += 2; // Add 2 inches for packaging
        }

        return round($value, 1);
    }

    private function determineShippingClass($queueItem): string
    {
        $weight = $this->estimateWeight($queueItem, 'lbs');
        $notes = strtolower($queueItem->user_notes ?? '');

        if ($weight > 30 || str_contains($notes, 'terminal')) {
            return 'heavy_equipment';
        }

        if ($weight > 10) {
            return 'standard_heavy';
        }

        if (str_contains($notes, 'phone') || str_contains($notes, 'small')) {
            return 'small_package';
        }

        return 'standard';
    }

    private function generateProductAttributes($queueItem, array $mergedFields): array
    {
        $attributes = [
            'source' => 'product_intake_queue',
            'capture_method' => 'processed_automatically',
            'ai_confidence' => (string) ($queueItem->ai_confidence_score ?? 0.8),
            'quality_score' => $queueItem->quality_score ?? 80,
        ];

        // Add identity attributes
        if (! empty($mergedFields['product_identity'])) {
            $identity = $mergedFields['product_identity'];
            $attributes = array_merge($attributes, [
                'brand' => $identity['brand'] ?? null,
                'model' => $identity['model'] ?? null,
                'category' => $identity['category'] ?? null,
            ]);
        }

        // Add specification attributes
        if (! empty($mergedFields['product_details']['specifications'])) {
            $attributes['specifications'] = $mergedFields['product_details']['specifications'];
        }

        return $attributes;
    }

    private function getFeaturedImage($queueItem): ?string
    {
        $photos = json_decode($queueItem->photos ?? '[]', true);

        return $photos[0] ?? null;
    }

    private function getGalleryImages($queueItem): array
    {
        $photos = json_decode($queueItem->photos ?? '[]', true);

        return $photos;
    }

    private function mapCondition(string $condition): string
    {
        $conditionMap = [
            'NEW' => 'NEW',
            'USED_EXCELLENT' => 'USED_EXCELLENT',
            'USED_VERY_GOOD' => 'USED_VERY_GOOD',
            'USED_GOOD' => 'USED_GOOD',
            'USED_ACCEPTABLE' => 'USED_ACCEPTABLE',
            'FOR_PARTS_OR_NOT_WORKING' => 'FOR_PARTS_OR_NOT_WORKING',
        ];

        return $conditionMap[$condition] ?? 'USED_GOOD';
    }

    private function generateSeoDescription($queueItem, array $productDetails): string
    {
        $brand = $queueItem->brand ?? '';
        $condition = $this->mapCondition($queueItem->condition ?? 'USED_GOOD');
        $category = $this->determineProductCategory($queueItem);

        return "Buy {$brand} {$category} in {$condition} condition. Professional grade equipment with fast shipping and 30-day return policy.";
    }

    private function determineProductCategory($queueItem): string
    {
        $notes = strtolower($queueItem->user_notes ?? '');

        if (str_contains($notes, 'terminal')) {
            return 'Terminal';
        }
        if (str_contains($notes, 'pos')) {
            return 'POS System';
        }
        if (str_contains($notes, 'phone')) {
            return 'Mobile Phone';
        }
        if (str_contains($notes, 'laptop')) {
            return 'Laptop';
        }
        if (str_contains($notes, 'tablet')) {
            return 'Tablet';
        }
        if (str_contains($notes, 'scanner')) {
            return 'Scanner';
        }
        if (str_contains($notes, 'printer')) {
            return 'Printer';
        }

        return 'Electronic Equipment';
    }

    private function extractModelNumber($queueItem, array $productIdentity): ?string
    {
        // Try from enriched data first
        if (! empty($productIdentity['model'])) {
            return $productIdentity['model'];
        }

        // Extract from NCR ID or notes
        $text = ($queueItem->manufacturer_part_number ?? '').' '.($queueItem->user_notes ?? '');

        if (preg_match('/(\d{4}[-\s]?\d{4})/', $text, $matches)) {
            return $matches[1];
        }

        if (preg_match('/(iPhone \d+[^,\s]*)/i', $text, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function extractSeries($queueItem): ?string
    {
        $notes = $queueItem->user_notes ?? '';

        if (preg_match('/NCR[- ]?(\d{4})/', $notes, $matches)) {
            return "NCR {$matches[1]} Series";
        }

        return null;
    }

    private function generateFeatureBullets($queueItem): array
    {
        $bullets = ['Tested and verified working condition', 'Professional grade equipment', 'Fast shipping with tracking', '30-day return policy included'];

        $notes = strtolower($queueItem->user_notes ?? '');

        if (str_contains($notes, 'terminal') || str_contains($notes, 'pos')) {
            $bullets[] = 'Commercial point-of-sale system';
            $bullets[] = 'Suitable for retail and restaurant use';
        } elseif (str_contains($notes, 'phone')) {
            $bullets[] = 'Unlocked for all major carriers';
            $bullets[] = 'Ready to use out of the box';
        } elseif (str_contains($notes, 'laptop')) {
            $bullets[] = 'Business grade laptop computer';
            $bullets[] = 'Includes power adapter';
        }

        $condition = $queueItem->condition ?? 'USED_GOOD';
        if ($condition === 'NEW') {
            $bullets[] = 'Brand new in original packaging';
        }

        return array_slice($bullets, 0, 8); // Max 8 bullets
    }

    private function generateWhatsInBox($queueItem): string
    {
        $items = [];

        $category = $this->determineProductCategory($queueItem);
        $items[] = "1x {$category}";

        $notes = strtolower($queueItem->user_notes ?? '');

        if (str_contains($notes, 'keyboard')) {
            $items[] = '1x Keyboard';
        }
        if (str_contains($notes, 'cable') || str_contains($notes, 'power')) {
            $items[] = '1x Power Cable';
        }
        if (str_contains($notes, 'manual')) {
            $items[] = '1x User Manual';
        }

        return implode("\n", $items);
    }

    private function determineWarrantyType($queueItem): ?string
    {
        $condition = $queueItem->condition ?? 'USED_GOOD';

        if ($condition === 'NEW') {
            return 'manufacturer';
        } elseif (in_array($condition, ['USED_EXCELLENT', 'USED_VERY_GOOD'])) {
            return 'seller';
        }

        return null;
    }

    private function determineWarrantyDuration($queueItem): ?string
    {
        $warrantyType = $this->determineWarrantyType($queueItem);

        if ($warrantyType === 'manufacturer') {
            return '1_year';
        } elseif ($warrantyType === 'seller') {
            return '30_days';
        }

        return null;
    }

    private function generateWarrantyDetails($queueItem): ?string
    {
        $warrantyType = $this->determineWarrantyType($queueItem);

        if ($warrantyType === 'manufacturer') {
            return 'Full manufacturer warranty included. Registration may be required.';
        } elseif ($warrantyType === 'seller') {
            return '30-day seller warranty covers functionality and defects.';
        }

        return null;
    }

    private function requiresAgeVerification($queueItem): bool
    {
        // Most electronics don't require age verification
        return false;
    }

    private function getComplianceCertifications($queueItem): array
    {
        $certs = [];

        if ($queueItem->brand === 'NCR') {
            $certs[] = 'FCC Part 15';
            $certs[] = 'CE Marking';
        }

        return $certs;
    }

    private function getSafetyWarnings($queueItem): array
    {
        return [
            'Contains electronic components',
            'Keep away from water and moisture',
            'Use only specified power adapters',
        ];
    }

    private function generateListingChecklist($queueItem, array $enrichedData): array
    {
        $qualityScore = $queueItem->quality_score ?? 80;

        return [
            'title' => ['completed' => ! empty($enrichedData['merged_product_fields']['product_identity']['title']), 'required' => true],
            'description' => ['completed' => ! empty($queueItem->user_notes), 'required' => true],
            'photos' => ['completed' => ! empty($queueItem->photos), 'required' => true],
            'price' => ['completed' => ! empty($enrichedData['merged_product_fields']['market_pricing']['recommended_list_price']), 'required' => true],
            'category' => ['completed' => ! empty($enrichedData['merged_product_fields']['product_identity']['category']), 'required' => true],
            'condition' => ['completed' => ! empty($queueItem->condition), 'required' => true],
            'shipping' => ['completed' => true, 'required' => true],
            'ready_score' => $qualityScore,
        ];
    }

    private function createProductVariations(string $productId, $queueItem): void
    {
        // Only create variations for multi-pack items
        $quantity = $queueItem->quick_quantity ?? 1;

        if ($quantity > 1) {
            // Get enriched data for pricing
            $enrichedData = json_decode($queueItem->enriched_data ?? '{}', true);
            $marketPricing = $enrichedData['merged_product_fields']['market_pricing'] ?? [];
            $basePrice = $marketPricing['recommended_list_price'] ?? 0;

            DB::table('product_variations')->insert([
                'id' => Str::uuid(),
                'parent_product_id' => $productId,
                'sku' => $this->generateUniqueSku($queueItem).'-QTY'.$quantity,
                'variation_attributes' => json_encode([
                    'name' => 'Quantity',
                    'value' => (string) $quantity,
                    'type' => 'multipack',
                ]),
                'cost_price' => $this->calculateCostPrice($basePrice),
                'selling_price' => $basePrice,
                'msrp' => $this->calculateMsrp($basePrice),
                'weight' => $this->estimateWeight($queueItem),
                'length' => $this->estimateDimension($queueItem, 'length'),
                'width' => $this->estimateDimension($queueItem, 'width'),
                'height' => $this->estimateDimension($queueItem, 'height'),
                'stock_quantity' => 1,
                'low_stock_threshold' => 1,
                'featured_image' => $this->getFeaturedImage($queueItem),
                'gallery_images' => json_encode($this->getGalleryImages($queueItem)),
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
                'version' => 1,
            ]);
        }
    }

    private function createProductIdentifiers(string $productId, $queueItem, array $productIdentity): void
    {
        $identifiers = [];

        if ($queueItem->barcode) {
            $identifiers[] = [
                'id' => Str::uuid(),
                'product_id' => $productId,
                'type' => 'upc',
                'value' => $queueItem->barcode,
                'is_primary' => true,
            ];
        }

        if ($queueItem->manufacturer_part_number) {
            $identifiers[] = [
                'id' => Str::uuid(),
                'product_id' => $productId,
                'type' => 'part_number',
                'value' => $queueItem->manufacturer_part_number,
                'is_primary' => ! $queueItem->barcode, // Primary if no UPC
            ];
        }

        if ($queueItem->product_number) {
            $identifiers[] = [
                'id' => Str::uuid(),
                'product_id' => $productId,
                'type' => 'model_number',
                'value' => $queueItem->product_number,
                'is_primary' => false,
            ];
        }

        foreach ($identifiers as $identifier) {
            $identifier['created_at'] = now();
            $identifier['updated_at'] = now();
            DB::table('product_identifiers')->insert($identifier);
        }
    }
}
