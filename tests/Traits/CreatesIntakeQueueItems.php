<?php

namespace Tests\Traits;

use App\Models\Catalog\ProductAiAnalysis;
use App\Models\Catalog\ProductIntakeQueue;
use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;

/**
 * Trait providing helper methods for creating ProductIntakeQueue test fixtures.
 *
 * Use this trait in test classes that need to test the intake queue system.
 */
trait CreatesIntakeQueueItems
{
    protected Organization $testOrganization;

    protected Team $testTeam;

    protected User $testUser;

    protected Role $testRole;

    /**
     * Set up the organization, team, user, and role for testing.
     */
    protected function setUpIntakeQueueTestFixtures(): void
    {
        $this->testOrganization = Organization::factory()->create([
            'sku_prefix' => 'TEST',
            'sku_prefix_locked' => true,
        ]);

        $this->testTeam = Team::factory()->create([
            'organization_id' => $this->testOrganization->id,
        ]);

        $this->testRole = Role::factory()->create([
            'name' => 'Queue Manager',
            'slug' => 'queue-manager',
            'is_system' => true,
            'permissions' => [
                'intake_queue.view',
                'intake_queue.create',
                'intake_queue.update',
                'intake_queue.delete',
                'products.create',
                'products.edit',
            ],
        ]);

        $this->testUser = User::factory()->create([
            'organization_id' => $this->testOrganization->id,
        ]);

        $this->testUser->roles()->attach($this->testRole->id, [
            'organization_id' => $this->testOrganization->id,
            'team_id' => $this->testTeam->id,
        ]);

        // Set current team and add user to team (Jetstream pattern)
        $this->testUser->current_team_id = $this->testTeam->id;
        $this->testUser->save();

        // Attach user to team (required for currentTeam relationship)
        $this->testTeam->users()->attach($this->testUser->id, ['role' => 'admin']);

        // Complete onboarding for the test user (required for EnsureOnboardingComplete middleware)
        OnboardingProgress::create([
            'user_id' => $this->testUser->id,
            'organization_id' => $this->testOrganization->id,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'current_step' => 'completed',
            'steps_completed' => ['welcome', 'profile', 'organization', 'team'],
        ]);
    }

    /**
     * Create a basic queue item for the test organization/team.
     */
    protected function createQueueItem(array $attributes = []): ProductIntakeQueue
    {
        return ProductIntakeQueue::factory()->create(array_merge([
            'organization_id' => $this->testOrganization->id,
            'team_id' => $this->testTeam->id,
            'captured_by' => $this->testUser->id,
        ], $attributes));
    }

    /**
     * Create a pending queue item.
     */
    protected function createPendingQueueItem(array $attributes = []): ProductIntakeQueue
    {
        return ProductIntakeQueue::factory()
            ->pending()
            ->create(array_merge([
                'organization_id' => $this->testOrganization->id,
                'team_id' => $this->testTeam->id,
                'captured_by' => $this->testUser->id,
            ], $attributes));
    }

    /**
     * Create a queue item ready for listing.
     */
    protected function createReadyQueueItem(array $attributes = []): ProductIntakeQueue
    {
        return ProductIntakeQueue::factory()
            ->ready()
            ->withPhotos()
            ->create(array_merge([
                'organization_id' => $this->testOrganization->id,
                'team_id' => $this->testTeam->id,
                'captured_by' => $this->testUser->id,
            ], $attributes));
    }

    /**
     * Create a queue item that needs review.
     */
    protected function createNeedsReviewQueueItem(array $attributes = []): ProductIntakeQueue
    {
        return ProductIntakeQueue::factory()
            ->needsReview()
            ->withPhotos()
            ->create(array_merge([
                'organization_id' => $this->testOrganization->id,
                'team_id' => $this->testTeam->id,
                'captured_by' => $this->testUser->id,
            ], $attributes));
    }

    /**
     * Create a failed queue item.
     */
    protected function createFailedQueueItem(array $attributes = []): ProductIntakeQueue
    {
        return ProductIntakeQueue::factory()
            ->failed()
            ->create(array_merge([
                'organization_id' => $this->testOrganization->id,
                'team_id' => $this->testTeam->id,
                'captured_by' => $this->testUser->id,
            ], $attributes));
    }

    /**
     * Create a completed queue item.
     */
    protected function createCompletedQueueItem(array $attributes = []): ProductIntakeQueue
    {
        return ProductIntakeQueue::factory()
            ->completed()
            ->withPhotos()
            ->create(array_merge([
                'organization_id' => $this->testOrganization->id,
                'team_id' => $this->testTeam->id,
                'captured_by' => $this->testUser->id,
                'processed_by' => $this->testUser->id,
            ], $attributes));
    }

    /**
     * Create a high confidence queue item.
     */
    protected function createHighConfidenceQueueItem(array $attributes = []): ProductIntakeQueue
    {
        return ProductIntakeQueue::factory()
            ->ready()
            ->highConfidence()
            ->withPhotos()
            ->withEnrichedData()
            ->create(array_merge([
                'organization_id' => $this->testOrganization->id,
                'team_id' => $this->testTeam->id,
                'captured_by' => $this->testUser->id,
            ], $attributes));
    }

    /**
     * Create a queue item with AI analysis.
     */
    protected function createQueueItemWithAiAnalysis(array $attributes = []): ProductIntakeQueue
    {
        $queueItem = ProductIntakeQueue::factory()
            ->ready()
            ->withPhotos()
            ->withEnrichedData()
            ->create(array_merge([
                'organization_id' => $this->testOrganization->id,
                'team_id' => $this->testTeam->id,
                'captured_by' => $this->testUser->id,
            ], $attributes));

        $aiAnalysis = ProductAiAnalysis::create([
            'queue_item_id' => $queueItem->id,
            'analysis_type' => 'enhanced_research',
            'ai_provider' => 'enhanced_pipeline',
            'model_version' => 'simplified_enhanced_v1',
            'detected_category' => 'Electronics',
            'detected_brand' => $queueItem->brand ?? 'Test Brand',
            'detected_model' => $queueItem->model_number ?? 'Test Model',
            'ai_description' => 'Test product description from AI.',
            'confidence_score' => $queueItem->ai_confidence_score ?? 0.85,
            'verification_status' => 'pending',
            'raw_response' => ['test' => 'data'],
        ]);

        $queueItem->update(['ai_analysis_id' => $aiAnalysis->id]);

        return $queueItem->fresh(['aiAnalysis']);
    }

    /**
     * Create a queue item with physical measurements.
     */
    protected function createQueueItemWithMeasurements(array $attributes = []): ProductIntakeQueue
    {
        return ProductIntakeQueue::factory()
            ->withMeasurements()
            ->create(array_merge([
                'organization_id' => $this->testOrganization->id,
                'team_id' => $this->testTeam->id,
                'captured_by' => $this->testUser->id,
            ], $attributes));
    }

    /**
     * Create a POS equipment queue item.
     */
    protected function createPosEquipmentQueueItem(array $attributes = []): ProductIntakeQueue
    {
        return ProductIntakeQueue::factory()
            ->posEquipment()
            ->completeUnit()
            ->create(array_merge([
                'organization_id' => $this->testOrganization->id,
                'team_id' => $this->testTeam->id,
                'captured_by' => $this->testUser->id,
            ], $attributes));
    }

    /**
     * Create multiple queue items with varying statuses.
     *
     * @return array<string, ProductIntakeQueue>
     */
    protected function createQueueItemsWithVariousStatuses(): array
    {
        return [
            'pending' => $this->createPendingQueueItem(),
            'ready' => $this->createReadyQueueItem(),
            'needs_review' => $this->createNeedsReviewQueueItem(),
            'failed' => $this->createFailedQueueItem(),
            'completed' => $this->createCompletedQueueItem(),
        ];
    }

    /**
     * Create queue items with varying confidence levels.
     *
     * @return array<string, ProductIntakeQueue>
     */
    protected function createQueueItemsWithVariousConfidence(): array
    {
        return [
            'high' => ProductIntakeQueue::factory()
                ->highConfidence()
                ->withEnrichedData()
                ->create([
                    'organization_id' => $this->testOrganization->id,
                    'team_id' => $this->testTeam->id,
                    'captured_by' => $this->testUser->id,
                ]),
            'medium' => ProductIntakeQueue::factory()
                ->mediumConfidence()
                ->withEnrichedData()
                ->create([
                    'organization_id' => $this->testOrganization->id,
                    'team_id' => $this->testTeam->id,
                    'captured_by' => $this->testUser->id,
                ]),
            'low' => ProductIntakeQueue::factory()
                ->lowConfidence()
                ->withEnrichedData()
                ->create([
                    'organization_id' => $this->testOrganization->id,
                    'team_id' => $this->testTeam->id,
                    'captured_by' => $this->testUser->id,
                ]),
        ];
    }

    /**
     * Create a supplier for the test organization.
     */
    protected function createTestSupplier(array $attributes = []): Supplier
    {
        return Supplier::factory()->create(array_merge([
            'organization_id' => $this->testOrganization->id,
            'is_active' => true,
        ], $attributes));
    }

    /**
     * Generate mock enriched data structure.
     */
    protected function generateMockEnrichedData(array $overrides = []): array
    {
        $brand = $overrides['brand'] ?? 'Test Brand';
        $model = $overrides['model'] ?? 'Test Model';

        return array_merge([
            'research_data' => [
                'product_identity' => [
                    'title' => "{$brand} {$model}",
                    'brand' => $brand,
                    'model' => $model,
                    'category' => 'Electronics',
                ],
                'product_details' => [
                    'description' => 'Test product description.',
                    'key_features' => ['Feature 1', 'Feature 2', 'Feature 3'],
                    'specifications' => [
                        'Weight' => '2.5 lbs',
                        'Dimensions' => '10x8x4 inches',
                    ],
                ],
                'market_pricing' => [
                    'recommended_list_price' => 199.99,
                    'ebay_sold_listings' => [],
                    'ebay_sold_summary' => [
                        'average_sold' => 175.00,
                        'median_sold' => 180.00,
                    ],
                ],
                'listing_optimization' => [
                    'suggested_title' => "{$brand} {$model} - Tested Working",
                    'primary_keywords' => [$brand, $model],
                    'bullet_points' => ['Point 1', 'Point 2', 'Point 3'],
                ],
            ],
            'merged_product_fields' => [
                'product_identity' => [
                    'title' => "{$brand} {$model}",
                    'brand' => $brand,
                    'model' => $model,
                ],
                'product_details' => [
                    'description' => 'Test product description.',
                ],
                'listing_optimization' => [
                    'suggested_title' => "{$brand} {$model} - Tested Working",
                ],
                'market_pricing' => [
                    'recommended_list_price' => 199.99,
                ],
            ],
            'sources_used' => ['research', 'barcode_validation'],
            'confidence_breakdown' => [
                'base_confidence' => 0.7,
                'completeness_boost' => 0.05,
                'supplier_boost' => 0.05,
                'final_confidence' => 0.8,
            ],
        ], $overrides);
    }

    /**
     * Generate mock research data for job testing.
     */
    protected function generateMockResearchData(array $overrides = []): array
    {
        return array_merge([
            'product_identity' => [
                'title' => 'Test Product Title',
                'brand' => 'Test Brand',
                'model' => 'Test Model',
                'category' => 'Electronics',
                'mpn' => '1234-5678',
                'upc' => '012345678901',
            ],
            'product_details' => [
                'description' => 'A detailed product description.',
                'key_features' => ['Feature 1', 'Feature 2'],
                'specifications' => ['Weight' => '1 lb'],
            ],
            'market_pricing' => [
                'recommended_list_price' => 99.99,
                'ebay_sold_listings' => [
                    ['price' => 95.00, 'sold_date' => now()->subDays(5)->toDateString()],
                ],
            ],
            'listing_optimization' => [
                'suggested_title' => 'Test Product - Great Condition',
                'primary_keywords' => ['test', 'product'],
                'bullet_points' => ['Bullet 1', 'Bullet 2'],
            ],
            'data_source' => 'enhanced_research',
            'search_method' => 'comprehensive_ebay',
        ], $overrides);
    }
}
