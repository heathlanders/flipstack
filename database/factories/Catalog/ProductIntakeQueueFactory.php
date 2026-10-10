<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\ProductAiAnalysis;
use App\Models\Catalog\ProductIntakeQueue;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductIntakeQueueFactory extends Factory
{
    protected $model = ProductIntakeQueue::class;

    public function definition(): array
    {
        $organization = Organization::factory();

        return [
            'organization_id' => $organization,
            'team_id' => Team::factory()->state(function (array $attributes, $parent) use ($organization) {
                return ['organization_id' => $parent['organization_id'] ?? $organization];
            }),
            'captured_by' => User::factory()->state(function (array $attributes, $parent) use ($organization) {
                return ['organization_id' => $parent['organization_id'] ?? $organization];
            }),
            'captured_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'capture_method' => $this->faker->randomElement([
                'mobile_app',
                'web_interface',
                'barcode_and_photos',
                'manufacturer_part_number_and_photos',
                'manual_entry',
            ]),
            'barcode' => $this->faker->optional(0.5)->ean13(),
            'manufacturer_part_number' => $this->faker->optional(0.6)->numerify('####-####-####'),
            'product_number' => $this->faker->optional(0.4)->bothify('??##-####'),
            'model_number' => $this->faker->optional(0.5)->bothify('??-####'),
            'brand' => $this->faker->optional(0.7)->company(),
            'photos' => [],
            'user_notes' => $this->faker->optional(0.8)->sentence(),
            'quick_quantity' => $this->faker->numberBetween(1, 10),
            'quick_location' => $this->faker->optional(0.6)->randomElement(['Shelf A1', 'Bin B2', 'Warehouse C', 'Staging']),
            'processing_status' => 'pending',
            'ai_analysis_status' => 'pending',
            'priority' => $this->faker->numberBetween(1, 3),
            'product_category' => $this->faker->randomElement(array_keys(ProductIntakeQueue::PRODUCT_CATEGORIES)),
            'product_type' => $this->faker->randomElement(array_keys(ProductIntakeQueue::PRODUCT_TYPES)),
            'condition' => $this->faker->randomElement([
                'NEW',
                'NEW_OTHER',
                'USED_EXCELLENT',
                'USED_VERY_GOOD',
                'USED_GOOD',
                'FOR_PARTS_OR_NOT_WORKING',
            ]),
        ];
    }

    /**
     * Item in pending state (default)
     */
    public function pending(): static
    {
        return $this->state([
            'processing_status' => 'pending',
            'ai_analysis_status' => 'pending',
            'ai_confidence_score' => null,
            'enriched_data' => null,
        ]);
    }

    /**
     * Item currently being processed by AI
     */
    public function processing(): static
    {
        return $this->state([
            'processing_status' => 'pending',
            'ai_analysis_status' => 'processing',
            'ai_started_at' => now(),
            'processing_attempts' => 1,
        ]);
    }

    /**
     * Item ready for listing (high confidence)
     */
    public function ready(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'processing_status' => 'ready_for_listing',
                'ai_analysis_status' => 'complete',
                'ai_confidence_score' => $this->faker->randomFloat(2, 0.85, 0.98),
                'quality_score' => $this->faker->numberBetween(80, 100),
                'ai_started_at' => now()->subMinutes(5),
                'ai_completed_at' => now()->subMinutes(3),
                'last_processed_at' => now()->subMinutes(3),
            ];
        })->withEnrichedData();
    }

    /**
     * Alias for ready state
     */
    public function readyForListing(): static
    {
        return $this->ready();
    }

    /**
     * Item needs minor review (medium confidence)
     */
    public function needsMinorReview(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'processing_status' => 'needs_minor_review',
                'ai_analysis_status' => 'complete',
                'ai_confidence_score' => $this->faker->randomFloat(2, 0.60, 0.79),
                'quality_score' => $this->faker->numberBetween(60, 79),
                'ai_started_at' => now()->subMinutes(5),
                'ai_completed_at' => now()->subMinutes(3),
            ];
        })->withEnrichedData();
    }

    /**
     * Item needs review (low-medium confidence)
     */
    public function needsReview(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'processing_status' => 'needs_review',
                'ai_analysis_status' => 'complete',
                'ai_confidence_score' => $this->faker->randomFloat(2, 0.40, 0.59),
                'quality_score' => $this->faker->numberBetween(40, 59),
                'ai_started_at' => now()->subMinutes(5),
                'ai_completed_at' => now()->subMinutes(3),
            ];
        })->withEnrichedData();
    }

    /**
     * Item needs major review (very low confidence)
     */
    public function needsMajorReview(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'processing_status' => 'needs_major_review',
                'ai_analysis_status' => 'complete',
                'ai_confidence_score' => $this->faker->randomFloat(2, 0.10, 0.39),
                'quality_score' => $this->faker->numberBetween(10, 39),
                'ai_started_at' => now()->subMinutes(5),
                'ai_completed_at' => now()->subMinutes(3),
            ];
        })->withEnrichedData();
    }

    /**
     * Item in progress (user has opened it)
     */
    public function inProgress(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'processing_status' => 'in_progress',
                'ai_analysis_status' => 'complete',
                'ai_confidence_score' => $this->faker->randomFloat(2, 0.70, 0.95),
                'processed_by' => User::factory()->state(function () use ($attributes) {
                    return ['organization_id' => $attributes['organization_id']];
                }),
            ];
        })->withEnrichedData();
    }

    /**
     * Item completed (product created)
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'processing_status' => 'completed',
                'ai_analysis_status' => 'complete',
                'ai_confidence_score' => $this->faker->randomFloat(2, 0.80, 0.98),
                'quality_score' => $this->faker->numberBetween(75, 100),
                'processed_at' => now(),
                'processed_by' => User::factory()->state(function () use ($attributes) {
                    return ['organization_id' => $attributes['organization_id']];
                }),
            ];
        })->withEnrichedData();
    }

    /**
     * Item listed on eBay
     */
    public function listedOnEbay(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'processing_status' => 'listed_on_ebay',
                'ai_analysis_status' => 'complete',
                'ai_confidence_score' => $this->faker->randomFloat(2, 0.85, 0.98),
                'quality_score' => $this->faker->numberBetween(80, 100),
                'ebay_listing_id' => $this->faker->numerify('############'),
                'processed_at' => now(),
            ];
        })->withEnrichedData();
    }

    /**
     * Item failed to process
     */
    public function failed(): static
    {
        return $this->state([
            'processing_status' => 'failed',
            'ai_analysis_status' => 'failed',
            'ai_started_at' => now()->subMinutes(5),
            'processing_attempts' => 3,
            'enriched_data' => [
                'error' => 'Processing failed after maximum retry attempts',
                'failed_permanently_at' => now()->toIso8601String(),
                'total_attempts' => 3,
            ],
        ]);
    }

    /**
     * Item listing failed
     */
    public function listingFailed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'processing_status' => 'listing_failed',
                'ai_analysis_status' => 'complete',
                'ai_confidence_score' => $this->faker->randomFloat(2, 0.80, 0.98),
                'enriched_data' => [
                    'listing_error' => 'eBay API error: Invalid item specifics',
                    'listing_failed_at' => now()->toIso8601String(),
                ],
            ];
        });
    }

    /**
     * Item archived
     */
    public function archived(): static
    {
        return $this->state([
            'processing_status' => 'archived',
            'ai_analysis_status' => 'complete',
        ])->withEnrichedData();
    }

    /**
     * Item blocked due to missing SKU prefix
     */
    public function blockedSkuPrefix(): static
    {
        return $this->state([
            'processing_status' => 'blocked_sku_prefix',
            'ai_analysis_status' => 'pending',
            'validation_errors' => ['Organization requires SKU prefix setup before processing products'],
        ]);
    }

    /**
     * Add enriched data from AI processing
     */
    public function withEnrichedData(): static
    {
        return $this->state(function (array $attributes) {
            $brand = $attributes['brand'] ?? $this->faker->company();
            $model = $attributes['model_number'] ?? $this->faker->bothify('Model-####');

            return [
                'enriched_data' => [
                    'research_data' => [
                        'product_identity' => [
                            'title' => "{$brand} {$model}",
                            'brand' => $brand,
                            'model' => $model,
                            'category' => 'Electronics',
                        ],
                        'product_details' => [
                            'description' => $this->faker->paragraph(),
                            'key_features' => $this->faker->sentences(3),
                            'specifications' => [
                                'Weight' => $this->faker->randomFloat(1, 0.5, 10).' lbs',
                                'Dimensions' => $this->faker->numerify('##x##x## inches'),
                            ],
                        ],
                        'market_pricing' => [
                            'recommended_list_price' => $this->faker->randomFloat(2, 50, 500),
                            'ebay_sold_listings' => [],
                            'ebay_sold_summary' => [
                                'average_sold' => $this->faker->randomFloat(2, 40, 450),
                                'median_sold' => $this->faker->randomFloat(2, 45, 400),
                            ],
                        ],
                        'listing_optimization' => [
                            'suggested_title' => "{$brand} {$model} - Tested Working",
                            'primary_keywords' => [$brand, $model, 'electronics'],
                            'bullet_points' => $this->faker->sentences(4),
                        ],
                    ],
                    'merged_product_fields' => [
                        'product_identity' => [
                            'title' => "{$brand} {$model}",
                            'brand' => $brand,
                            'model' => $model,
                            'manufacturer_part_number' => $attributes['manufacturer_part_number'] ?? null,
                            'upc' => $attributes['barcode'] ?? null,
                        ],
                        'product_details' => [
                            'description' => $this->faker->paragraph(),
                            'key_features' => $this->faker->sentences(3),
                        ],
                        'listing_optimization' => [
                            'suggested_title' => "{$brand} {$model} - Tested Working",
                            'primary_keywords' => [$brand, $model],
                            'bullet_points' => $this->faker->sentences(4),
                        ],
                        'market_pricing' => [
                            'recommended_list_price' => $this->faker->randomFloat(2, 50, 500),
                        ],
                    ],
                    'supplier_context' => [
                        'reliability_boost' => 0.05,
                        'priority_boost' => false,
                        'supplier_name' => $this->faker->company(),
                    ],
                    'shipping_data' => [
                        'shipping_class' => $this->faker->randomElement(['standard', 'lightweight', 'oversized']),
                        'estimated_cost' => $this->faker->randomFloat(2, 5, 25),
                        'fragile' => $this->faker->boolean(20),
                    ],
                    'sources_used' => ['barcode_validation', 'research', 'background_removal'],
                    'confidence_breakdown' => [
                        'base_confidence' => 0.7,
                        'completeness_boost' => 0.05,
                        'supplier_boost' => 0.05,
                        'final_confidence' => 0.8,
                    ],
                ],
            ];
        });
    }

    /**
     * Add photo paths
     */
    public function withPhotos(int $count = 3): static
    {
        return $this->state(function () use ($count) {
            $photos = [];
            for ($i = 0; $i < $count; $i++) {
                $photos[] = 'queue-photos/'.fake()->uuid().'.jpg';
            }

            return ['photos' => $photos];
        });
    }

    /**
     * Add physical measurements
     */
    public function withMeasurements(): static
    {
        return $this->state([
            'weight_lbs' => $this->faker->randomFloat(2, 0.5, 50),
            'length_inches' => $this->faker->randomFloat(2, 2, 36),
            'width_inches' => $this->faker->randomFloat(2, 2, 24),
            'height_inches' => $this->faker->randomFloat(2, 1, 18),
        ]);
    }

    /**
     * Add completeness data
     */
    public function withCompletenessData(float $score = 85.0): static
    {
        return $this->state([
            'completeness_data' => [
                'completeness_score' => $score,
                'has_power_cable' => true,
                'has_manual' => false,
                'has_original_box' => false,
                'has_accessories' => true,
            ],
            'completeness_score' => $score,
        ]);
    }

    /**
     * Add AI analysis relationship
     */
    public function withAiAnalysis(): static
    {
        return $this->afterCreating(function (ProductIntakeQueue $queueItem) {
            $aiAnalysis = ProductAiAnalysis::create([
                'queue_item_id' => $queueItem->id,
                'analysis_type' => 'enhanced_research',
                'ai_provider' => 'enhanced_pipeline',
                'model_version' => 'simplified_enhanced_v1',
                'detected_category' => 'Electronics',
                'detected_brand' => $queueItem->brand,
                'detected_model' => $queueItem->model_number,
                'ai_description' => fake()->paragraph(),
                'confidence_score' => $queueItem->ai_confidence_score ?? 0.85,
                'verification_status' => 'pending',
                'raw_response' => [],
            ]);

            $queueItem->update(['ai_analysis_id' => $aiAnalysis->id]);
        });
    }

    /**
     * High confidence item (>= 0.85)
     */
    public function highConfidence(): static
    {
        return $this->state([
            'ai_confidence_score' => $this->faker->randomFloat(2, 0.85, 0.98),
            'ai_analysis_status' => 'complete',
        ]);
    }

    /**
     * Medium confidence item (0.60 - 0.84)
     */
    public function mediumConfidence(): static
    {
        return $this->state([
            'ai_confidence_score' => $this->faker->randomFloat(2, 0.60, 0.84),
            'ai_analysis_status' => 'complete',
        ]);
    }

    /**
     * Low confidence item (< 0.60)
     */
    public function lowConfidence(): static
    {
        return $this->state([
            'ai_confidence_score' => $this->faker->randomFloat(2, 0.20, 0.59),
            'ai_analysis_status' => 'complete',
        ]);
    }

    /**
     * POS equipment category
     */
    public function posEquipment(): static
    {
        return $this->state([
            'product_category' => 'pos_equipment',
            'manufacturer_part_number' => $this->faker->numerify('7167-####'),
            'brand' => $this->faker->randomElement(['NCR', 'IBM', 'Epson', 'Verifone']),
        ]);
    }

    /**
     * Complete unit product type
     */
    public function completeUnit(): static
    {
        return $this->state([
            'product_type' => 'complete_unit',
        ]);
    }

    /**
     * Replacement part product type
     */
    public function replacementPart(): static
    {
        return $this->state([
            'product_type' => 'replacement_part',
        ]);
    }

    /**
     * For a specific team
     */
    public function forTeam(Team $team): static
    {
        return $this->state([
            'team_id' => $team->id,
            'organization_id' => $team->organization_id,
        ]);
    }

    /**
     * For a specific organization
     */
    public function forOrganization(Organization $organization): static
    {
        return $this->state([
            'organization_id' => $organization->id,
        ]);
    }

    /**
     * Captured by a specific user
     */
    public function capturedBy(User $user): static
    {
        return $this->state([
            'captured_by' => $user->id,
            'organization_id' => $user->organization_id,
        ]);
    }
}
