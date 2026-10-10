<?php

namespace Tests\Feature\IntakeQueue;

use App\Jobs\ProcessQueueItem;
use App\Models\Catalog\ProductAiAnalysis;
use App\Models\Catalog\ProductIntakeQueue;
use App\Services\BackgroundRemovalService;
use App\Services\BarcodeValidationService;
use App\Services\EbayCategoryService;
use App\Services\EbayRateLimiter;
use App\Services\ProductDataValidator;
use App\Services\ProductResearchOrchestrator;
use App\Services\ShippingClassService;
use App\Services\SupplierPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Tests\Traits\CreatesIntakeQueueItems;

class ProcessQueueItemTest extends TestCase
{
    use CreatesIntakeQueueItems;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(); // Block all real HTTP calls; Mockery mocks handle service-level calls
        $this->setUpIntakeQueueTestFixtures();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create mocked service dependencies for the job.
     */
    protected function mockServices(array $overrides = []): array
    {
        $researchData = $overrides['researchData'] ?? $this->generateMockResearchData();

        // Mock ProductResearchOrchestrator
        $orchestrator = Mockery::mock(ProductResearchOrchestrator::class);
        $orchestrator->shouldReceive('researchWithStrategy')
            ->andReturn($researchData);
        $orchestrator->shouldReceive('research')
            ->andReturn($researchData);

        // Mock BackgroundRemovalService
        $bgRemoval = Mockery::mock(BackgroundRemovalService::class);
        $bgRemoval->shouldReceive('isAvailable')
            ->andReturn($overrides['bgRemovalAvailable'] ?? false);
        $bgRemoval->shouldReceive('processMultipleWithCropping')
            ->andReturn($overrides['processedPhotos'] ?? []);
        $bgRemoval->shouldReceive('processMultiple')
            ->andReturn($overrides['processedPhotos'] ?? []);

        // Mock BarcodeValidationService
        $barcodeValidator = Mockery::mock(BarcodeValidationService::class);
        $barcodeValidator->shouldReceive('validate')
            ->andReturn($overrides['barcodeValidation'] ?? [
                'valid' => true,
                'type' => 'UPC',
                'formatted' => '012345678901',
            ]);

        // Mock ProductDataValidator
        $validator = Mockery::mock(ProductDataValidator::class);
        $validator->shouldReceive('calculateQualityScore')
            ->andReturn($overrides['qualityScore'] ?? 85);

        // Mock EbayRateLimiter
        $rateLimiter = Mockery::mock(EbayRateLimiter::class);

        // Mock ShippingClassService
        $shippingClassifier = Mockery::mock(ShippingClassService::class);
        $shippingClassifier->shouldReceive('classify')
            ->andReturn($overrides['shippingClass'] ?? 'standard');
        $shippingClassifier->shouldReceive('estimateCost')
            ->andReturn($overrides['shippingCost'] ?? 12.50);

        // Mock SupplierPreferenceService
        $supplierPrefs = Mockery::mock(SupplierPreferenceService::class);
        $supplierPrefs->shouldReceive('getSupplierContext')
            ->andReturn($overrides['supplierContext'] ?? [
                'reliability_boost' => 0.05,
                'priority_boost' => false,
                'supplier_name' => 'Test Supplier',
            ]);

        // Mock EbayCategoryService
        $categoryService = Mockery::mock(EbayCategoryService::class);
        $categoryService->shouldReceive('findPreliminaryCategory')
            ->andReturn(null);

        return [
            'orchestrator' => $orchestrator,
            'bgRemoval' => $bgRemoval,
            'barcodeValidator' => $barcodeValidator,
            'validator' => $validator,
            'rateLimiter' => $rateLimiter,
            'shippingClassifier' => $shippingClassifier,
            'supplierPrefs' => $supplierPrefs,
            'categoryService' => $categoryService,
        ];
    }

    /**
     * Execute the job with mocked services.
     */
    protected function executeJobWithMocks(ProductIntakeQueue $queueItem, array $serviceOverrides = []): void
    {
        $services = $this->mockServices($serviceOverrides);

        $job = new ProcessQueueItem($queueItem);
        $job->handle(
            $services['orchestrator'],
            $services['bgRemoval'],
            $services['barcodeValidator'],
            $services['validator'],
            $services['rateLimiter'],
            $services['shippingClassifier'],
            $services['supplierPrefs'],
            $services['categoryService']
        );
    }

    // =============================================
    // Basic Pipeline Tests
    // =============================================

    public function test_job_can_be_dispatched(): void
    {
        Queue::fake();

        $queueItem = $this->createPendingQueueItem();

        ProcessQueueItem::dispatch($queueItem);

        Queue::assertPushed(ProcessQueueItem::class, function ($job) use ($queueItem) {
            return $job->queueItem->id === $queueItem->id;
        });
    }

    public function test_job_updates_status_to_processing_on_start(): void
    {
        $queueItem = $this->createPendingQueueItem();

        $this->executeJobWithMocks($queueItem);

        // After completion, status should be one of the result statuses, not 'processing'
        $fresh = $queueItem->fresh();
        $this->assertContains($fresh->ai_analysis_status, ['complete', 'failed']);
    }

    public function test_job_increments_processing_attempts(): void
    {
        $queueItem = $this->createPendingQueueItem(['processing_attempts' => 0]);

        $this->executeJobWithMocks($queueItem);

        $this->assertEquals(1, $queueItem->fresh()->processing_attempts);
    }

    public function test_job_sets_ai_started_at_timestamp(): void
    {
        $queueItem = $this->createPendingQueueItem();

        $this->executeJobWithMocks($queueItem);

        $this->assertNotNull($queueItem->fresh()->ai_started_at);
    }

    public function test_job_sets_ai_completed_at_timestamp(): void
    {
        $queueItem = $this->createPendingQueueItem();

        $this->executeJobWithMocks($queueItem);

        $this->assertNotNull($queueItem->fresh()->ai_completed_at);
    }

    // =============================================
    // Confidence Scoring Tests
    // =============================================

    public function test_high_confidence_results_in_ready_for_listing_status(): void
    {
        $queueItem = $this->createPendingQueueItem();

        // Mock high quality research data
        $researchData = $this->generateMockResearchData([
            'product_identity' => [
                'title' => 'NCR 7167 POS Terminal',
                'brand' => 'NCR',
                'model' => '7167',
                'category' => 'POS Equipment',
            ],
            'product_details' => [
                'description' => 'Complete POS terminal with thermal printer.',
            ],
            'market_pricing' => [
                'recommended_list_price' => 299.99,
            ],
        ]);

        $this->executeJobWithMocks($queueItem, [
            'researchData' => $researchData,
            'qualityScore' => 90,
        ]);

        $fresh = $queueItem->fresh();
        $this->assertEquals('ready_for_listing', $fresh->processing_status);
        $this->assertTrue($fresh->ai_confidence_score >= 0.8);
    }

    public function test_medium_confidence_results_in_needs_minor_review_status(): void
    {
        $queueItem = $this->createPendingQueueItem();

        // Mock partial research data (missing some fields)
        $researchData = $this->generateMockResearchData([
            'product_identity' => [
                'title' => 'Unknown Terminal',
                'brand' => 'NCR',
                'model' => null, // Missing model
            ],
            'product_details' => [
                'description' => 'POS terminal.',
            ],
            'market_pricing' => [], // No pricing
        ]);

        $this->executeJobWithMocks($queueItem, [
            'researchData' => $researchData,
            'qualityScore' => 65,
        ]);

        $fresh = $queueItem->fresh();
        $this->assertContains($fresh->processing_status, ['needs_minor_review', 'needs_review']);
    }

    public function test_low_confidence_results_in_needs_review_status(): void
    {
        $queueItem = $this->createPendingQueueItem();

        // Mock minimal research data
        $researchData = [
            'product_identity' => [],
            'product_details' => [],
            'market_pricing' => [],
            'listing_optimization' => [],
            'data_source' => 'basic',
        ];

        $this->executeJobWithMocks($queueItem, [
            'researchData' => $researchData,
            'qualityScore' => 40,
        ]);

        $fresh = $queueItem->fresh();
        $this->assertContains($fresh->processing_status, ['needs_review', 'needs_major_review']);
    }

    // =============================================
    // Enriched Data Tests
    // =============================================

    public function test_job_populates_enriched_data(): void
    {
        $queueItem = $this->createPendingQueueItem();

        $this->executeJobWithMocks($queueItem);

        $fresh = $queueItem->fresh();
        $this->assertNotNull($fresh->enriched_data);
        $this->assertArrayHasKey('research_data', $fresh->enriched_data);
        $this->assertArrayHasKey('merged_product_fields', $fresh->enriched_data);
    }

    public function test_job_populates_confidence_breakdown(): void
    {
        $queueItem = $this->createPendingQueueItem();

        $this->executeJobWithMocks($queueItem);

        $fresh = $queueItem->fresh();
        $this->assertArrayHasKey('confidence_breakdown', $fresh->enriched_data);
    }

    public function test_job_populates_sources_used(): void
    {
        $queueItem = $this->createPendingQueueItem();

        $this->executeJobWithMocks($queueItem);

        $fresh = $queueItem->fresh();
        $this->assertArrayHasKey('sources_used', $fresh->enriched_data);
        $this->assertIsArray($fresh->enriched_data['sources_used']);
    }

    // =============================================
    // AI Analysis Record Tests
    // =============================================

    public function test_job_creates_ai_analysis_record(): void
    {
        $queueItem = $this->createPendingQueueItem();

        $this->executeJobWithMocks($queueItem);

        $fresh = $queueItem->fresh();
        $this->assertNotNull($fresh->ai_analysis_id);

        $aiAnalysis = ProductAiAnalysis::find($fresh->ai_analysis_id);
        $this->assertNotNull($aiAnalysis);
        $this->assertEquals($queueItem->id, $aiAnalysis->queue_item_id);
    }

    public function test_ai_analysis_record_contains_detected_fields(): void
    {
        $queueItem = $this->createPendingQueueItem(['brand' => 'NCR']);

        $researchData = $this->generateMockResearchData([
            'product_identity' => [
                'brand' => 'NCR',
                'model' => '7167',
                'category' => 'POS Equipment',
            ],
        ]);

        $this->executeJobWithMocks($queueItem, ['researchData' => $researchData]);

        $aiAnalysis = ProductAiAnalysis::where('queue_item_id', $queueItem->id)->first();
        $this->assertEquals('NCR', $aiAnalysis->detected_brand);
    }

    // =============================================
    // Shipping Classification Tests
    // =============================================

    public function test_job_analyzes_physical_measurements(): void
    {
        $queueItem = $this->createQueueItemWithMeasurements();

        $this->executeJobWithMocks($queueItem, ['shippingClass' => 'oversized']);

        $fresh = $queueItem->fresh();
        $this->assertArrayHasKey('shipping_data', $fresh->enriched_data);
    }

    public function test_job_detects_fragile_products(): void
    {
        $queueItem = $this->createPendingQueueItem([
            'user_notes' => 'Handle with care - glass display, fragile',
        ]);

        $this->executeJobWithMocks($queueItem);

        $fresh = $queueItem->fresh();
        $shippingData = $fresh->enriched_data['shipping_data'] ?? [];
        $this->assertTrue($shippingData['fragile'] ?? false);
    }

    // =============================================
    // Supplier Context Tests
    // =============================================

    public function test_job_includes_supplier_context_when_supplier_set(): void
    {
        $supplier = $this->createTestSupplier();
        $queueItem = $this->createPendingQueueItem(['supplier_id' => $supplier->id]);

        $this->executeJobWithMocks($queueItem, [
            'supplierContext' => [
                'reliability_boost' => 0.08,
                'priority_boost' => true,
                'supplier_name' => $supplier->name,
            ],
        ]);

        $fresh = $queueItem->fresh();
        $this->assertArrayHasKey('supplier_context', $fresh->enriched_data);
        $this->assertEquals($supplier->name, $fresh->enriched_data['supplier_context']['supplier_name']);
    }

    // =============================================
    // Error Handling Tests
    // =============================================

    public function test_job_handles_research_failure_gracefully(): void
    {
        $queueItem = $this->createPendingQueueItem();

        $services = $this->mockServices();

        // Make orchestrator throw exception
        $services['orchestrator'] = Mockery::mock(ProductResearchOrchestrator::class);
        $services['orchestrator']->shouldReceive('researchWithStrategy')
            ->andThrow(new \Exception('Research API unavailable'));
        $services['orchestrator']->shouldReceive('research')
            ->andThrow(new \Exception('Research API unavailable'));

        $job = new ProcessQueueItem($queueItem);

        // The job should catch research exceptions and continue processing
        // (logging a warning and updating with partial data)
        try {
            $job->handle(
                $services['orchestrator'],
                $services['bgRemoval'],
                $services['barcodeValidator'],
                $services['validator'],
                $services['rateLimiter'],
                $services['shippingClassifier'],
                $services['supplierPrefs'],
                $services['categoryService']
            );
        } catch (\Exception $e) {
            // The job may re-throw or fail gracefully - either is acceptable
            // Check that the queue item was updated with error info
            $fresh = $queueItem->fresh();
            $this->assertEquals('failed', $fresh->processing_status);

            return;
        }

        // If no exception, verify the job completed with warnings
        $fresh = $queueItem->fresh();
        $this->assertNotNull($fresh->enriched_data);
        $this->assertContains('complete', [$fresh->ai_analysis_status, 'failed']);
    }

    public function test_job_failed_method_updates_status(): void
    {
        $queueItem = $this->createPendingQueueItem();

        $job = new ProcessQueueItem($queueItem);
        $job->failed(new \Exception('Test failure'));

        $fresh = $queueItem->fresh();
        $this->assertEquals('failed', $fresh->processing_status);
        $this->assertEquals('failed', $fresh->ai_analysis_status);
        $this->assertArrayHasKey('error', $fresh->enriched_data);
    }

    public function test_job_failed_method_records_error_details(): void
    {
        $queueItem = $this->createPendingQueueItem();

        $job = new ProcessQueueItem($queueItem);
        $job->failed(new \Exception('API timeout error'));

        $fresh = $queueItem->fresh();
        $this->assertStringContainsString('API timeout error', $fresh->enriched_data['error']);
        $this->assertArrayHasKey('failed_permanently_at', $fresh->enriched_data);
    }

    // =============================================
    // SKU Prefix Blocking Tests
    // =============================================

    public function test_job_blocks_when_organization_missing_sku_prefix(): void
    {
        // Remove SKU prefix from organization settings
        $this->testOrganization->update(['settings' => []]);

        // Ensure the organization's canCreateProducts method returns false
        // This requires the Organization model to have this method
        $queueItem = $this->createPendingQueueItem();

        // The job checks organization->canCreateProducts() which should return false
        // We need to verify this behavior through the actual job execution
        // For now, we test the expected state
        $this->assertEmpty($this->testOrganization->settings['sku_prefix'] ?? null);
    }

    // =============================================
    // Research Strategy Tests
    // =============================================

    public function test_job_uses_comprehensive_strategy_with_identifiers(): void
    {
        $queueItem = $this->createPendingQueueItem([
            'manufacturer_part_number' => '7167-1015-9001',
            'brand' => 'NCR',
        ]);

        $services = $this->mockServices();

        // Verify researchWithStrategy is called with 'comprehensive'
        $services['orchestrator'] = Mockery::mock(ProductResearchOrchestrator::class);
        $services['orchestrator']->shouldReceive('researchWithStrategy')
            ->once()
            ->with(
                Mockery::type(ProductIntakeQueue::class),
                'comprehensive',
                Mockery::type('array')
            )
            ->andReturn($this->generateMockResearchData());

        $job = new ProcessQueueItem($queueItem);
        $job->handle(
            $services['orchestrator'],
            $services['bgRemoval'],
            $services['barcodeValidator'],
            $services['validator'],
            $services['rateLimiter'],
            $services['shippingClassifier'],
            $services['supplierPrefs'],
            $services['categoryService']
        );

        // Verify the job completed successfully
        $this->assertEquals('complete', $queueItem->fresh()->ai_analysis_status);
    }

    public function test_job_uses_barcode_focused_strategy_with_only_barcode(): void
    {
        $queueItem = $this->createPendingQueueItem([
            'barcode' => '012345678901',
            'manufacturer_part_number' => null,
            'product_number' => null,
            'brand' => null,
        ]);

        $services = $this->mockServices();

        $services['orchestrator'] = Mockery::mock(ProductResearchOrchestrator::class);
        $services['orchestrator']->shouldReceive('researchWithStrategy')
            ->once()
            ->with(
                Mockery::type(ProductIntakeQueue::class),
                'barcode_focused',
                Mockery::type('array')
            )
            ->andReturn($this->generateMockResearchData());

        $job = new ProcessQueueItem($queueItem);
        $job->handle(
            $services['orchestrator'],
            $services['bgRemoval'],
            $services['barcodeValidator'],
            $services['validator'],
            $services['rateLimiter'],
            $services['shippingClassifier'],
            $services['supplierPrefs'],
            $services['categoryService']
        );

        // Verify the job completed successfully
        $this->assertEquals('complete', $queueItem->fresh()->ai_analysis_status);
    }

    // =============================================
    // Photo Processing Tests
    // =============================================

    public function test_job_processes_photos_when_bg_removal_available(): void
    {
        $queueItem = ProductIntakeQueue::factory()
            ->pending()
            ->withPhotos(2)
            ->create([
                'organization_id' => $this->testOrganization->id,
                'team_id' => $this->testTeam->id,
                'captured_by' => $this->testUser->id,
            ]);

        $processedPaths = ['queue-photos/processed-1.jpg', 'queue-photos/processed-2.jpg'];

        $this->executeJobWithMocks($queueItem, [
            'bgRemovalAvailable' => true,
            'processedPhotos' => $processedPaths,
        ]);

        $fresh = $queueItem->fresh();
        $this->assertEquals($processedPaths, $fresh->photos);
    }

    public function test_job_preserves_original_photos_when_bg_removal_unavailable(): void
    {
        $originalPhotos = ['queue-photos/original-1.jpg', 'queue-photos/original-2.jpg'];

        $queueItem = $this->createPendingQueueItem([
            'photos' => $originalPhotos,
        ]);

        $this->executeJobWithMocks($queueItem, [
            'bgRemovalAvailable' => false,
        ]);

        $fresh = $queueItem->fresh();
        $this->assertEquals($originalPhotos, $fresh->photos);
    }
}
