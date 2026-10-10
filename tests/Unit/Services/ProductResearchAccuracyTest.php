<?php

namespace Tests\Unit\Services;

use App\Models\Catalog\ProductIntakeQueue;
use App\Services\EbayBrowseService;
use App\Services\GeminiProductResearch;
use App\Services\ProductIdentificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Regression tests for the product research orchestrator accuracy fixes.
 *
 * Covers the failure that turned a Protech 62-24164-01 furnace ignition part into a
 * "PC power supply" with sandal images:
 *  - eBay query no longer truncates the part number or defaults the brand to NCR
 *  - images below the relevance floor are not attached
 *  - the generic identification prompt grounds on user notes + disambiguation + honesty
 */
class ProductResearchAccuracyTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(object $obj, string $method, array $args): mixed
    {
        $ref = new ReflectionMethod($obj, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($obj, $args);
    }

    public function test_smart_query_uses_full_part_number_and_real_brand(): void
    {
        $service = app(EbayBrowseService::class);

        $query = $this->invoke($service, 'buildSmartQuery', [[
            'brand' => 'Protech',
            'manufacturer_part_number' => '62-24164-01',
            'keywords' => ['DSI', 'ignition'],
        ]]);

        $this->assertStringContainsString('Protech', $query);
        // Full part number preserved (dashes normalized to spaces), NOT truncated to "6224".
        $this->assertStringContainsString('62 24164 01', $query);
        $this->assertStringNotContainsString('NCR', $query);
    }

    public function test_smart_query_prefers_prebuilt_query(): void
    {
        $service = app(EbayBrowseService::class);

        $query = $this->invoke($service, 'buildSmartQuery', [[
            'query' => 'Rheem furnace ignition assembly 62-24164-01',
            'manufacturer_part_number' => '62-24164-01',
        ]]);

        $this->assertSame('Rheem furnace ignition assembly 62-24164-01', $query);
    }

    public function test_extract_images_applies_relevance_floor(): void
    {
        $service = app(GeminiProductResearch::class);

        $listings = [
            ['title' => 'Protech 62-24164-01 Direct Spark Ignition', 'image' => 'relevant.jpg'],
            ['title' => 'Mens Leather Sandals Size 9 Brown', 'image' => 'sandal.jpg'],
        ];

        $images = $this->invoke($service, 'extractImages', [
            $listings,
            ['manufacturer_part_number' => '62-24164-01'],
            null,
        ]);

        $this->assertSame(['relevant.jpg'], $images);
    }

    public function test_extract_images_empty_when_nothing_relevant(): void
    {
        $service = app(GeminiProductResearch::class);

        $listings = [
            ['title' => 'Mens Leather Sandals Size 9 Brown', 'image' => 'sandal.jpg'],
            ['title' => 'Garden Hose 50ft', 'image' => 'hose.jpg'],
        ];

        $images = $this->invoke($service, 'extractImages', [
            $listings,
            ['manufacturer_part_number' => '62-24164-01'],
            null,
        ]);

        $this->assertSame([], $images);
    }

    public function test_generic_prompt_grounds_on_user_notes_and_disambiguation(): void
    {
        $service = new ProductIdentificationService;

        $item = new ProductIntakeQueue([
            'brand' => 'Protech',
            'manufacturer_part_number' => '62-24164-01',
            'product_category' => 'hvac_refrigeration',
            'user_notes' => 'Genuine OEM Rheem furnace Direct Spark Ignition assembly',
            'keywords' => ['DSI', 'furnace', 'ignition'],
        ]);

        $prompt = $this->invoke($service, 'buildExpertCategoryPrompt', [$item, 'hvac_refrigeration']);

        $this->assertStringContainsString('Rheem furnace Direct Spark Ignition', $prompt);
        $this->assertStringContainsString('AUTHORITATIVE', $prompt);
        $this->assertStringContainsString('BRAND DISAMBIGUATION', $prompt);
        $this->assertStringContainsString('ANTI-HALLUCINATION', $prompt);
    }
}
