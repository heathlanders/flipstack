<?php

namespace Tests\Unit\Models;

use App\Models\Catalog\ProductAiAnalysis;
use App\Models\Catalog\ProductIntakeQueue;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesIntakeQueueItems;

class ProductIntakeQueueTest extends TestCase
{
    use CreatesIntakeQueueItems;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIntakeQueueTestFixtures();
    }

    // =============================================
    // Relationship Tests
    // =============================================

    public function test_belongs_to_organization(): void
    {
        $queueItem = $this->createQueueItem();

        $this->assertInstanceOf(Organization::class, $queueItem->organization);
        $this->assertEquals($this->testOrganization->id, $queueItem->organization->id);
    }

    public function test_belongs_to_team(): void
    {
        $queueItem = $this->createQueueItem();

        $this->assertInstanceOf(Team::class, $queueItem->team);
        $this->assertEquals($this->testTeam->id, $queueItem->team->id);
    }

    public function test_belongs_to_captured_by_user(): void
    {
        $queueItem = $this->createQueueItem();

        $this->assertInstanceOf(User::class, $queueItem->capturedBy);
        $this->assertEquals($this->testUser->id, $queueItem->capturedBy->id);
    }

    public function test_belongs_to_processed_by_user(): void
    {
        $processor = User::factory()->create(['organization_id' => $this->testOrganization->id]);
        $queueItem = $this->createQueueItem(['processed_by' => $processor->id]);

        $this->assertInstanceOf(User::class, $queueItem->processedBy);
        $this->assertEquals($processor->id, $queueItem->processedBy->id);
    }

    public function test_belongs_to_ai_analysis(): void
    {
        $queueItem = $this->createQueueItemWithAiAnalysis();

        $this->assertInstanceOf(ProductAiAnalysis::class, $queueItem->aiAnalysis);
        $this->assertEquals($queueItem->ai_analysis_id, $queueItem->aiAnalysis->id);
    }

    // =============================================
    // Scope Tests - Status Scopes
    // =============================================

    public function test_pending_scope(): void
    {
        $pending = $this->createPendingQueueItem();
        $ready = $this->createReadyQueueItem();

        $results = ProductIntakeQueue::pending()->get();

        $this->assertTrue($results->contains($pending));
        $this->assertFalse($results->contains($ready));
    }

    public function test_ready_scope(): void
    {
        $pending = $this->createPendingQueueItem();
        $ready = $this->createQueueItem(['processing_status' => 'ready']);

        $results = ProductIntakeQueue::ready()->get();

        $this->assertTrue($results->contains($ready));
        $this->assertFalse($results->contains($pending));
    }

    public function test_validated_scope(): void
    {
        $validated = $this->createQueueItem(['processing_status' => 'validated']);
        $pending = $this->createPendingQueueItem();

        $results = ProductIntakeQueue::validated()->get();

        $this->assertTrue($results->contains($validated));
        $this->assertFalse($results->contains($pending));
    }

    public function test_enriched_scope_includes_ready_and_validated(): void
    {
        $ready = $this->createQueueItem(['processing_status' => 'ready']);
        $validated = $this->createQueueItem(['processing_status' => 'validated']);
        $pending = $this->createPendingQueueItem();

        $results = ProductIntakeQueue::enriched()->get();

        $this->assertTrue($results->contains($ready));
        $this->assertTrue($results->contains($validated));
        $this->assertFalse($results->contains($pending));
    }

    public function test_in_progress_scope(): void
    {
        $inProgress = $this->createQueueItem(['processing_status' => 'in_progress']);
        $pending = $this->createPendingQueueItem();

        $results = ProductIntakeQueue::inProgress()->get();

        $this->assertTrue($results->contains($inProgress));
        $this->assertFalse($results->contains($pending));
    }

    public function test_completed_scope(): void
    {
        $completed = $this->createCompletedQueueItem();
        $pending = $this->createPendingQueueItem();

        $results = ProductIntakeQueue::completed()->get();

        $this->assertTrue($results->contains($completed));
        $this->assertFalse($results->contains($pending));
    }

    public function test_listed_on_ebay_scope(): void
    {
        $listed = $this->createQueueItem([
            'processing_status' => 'listed_on_ebay',
            'ebay_listing_id' => '123456789',
        ]);
        $pending = $this->createPendingQueueItem();

        $results = ProductIntakeQueue::listedOnEbay()->get();

        $this->assertTrue($results->contains($listed));
        $this->assertFalse($results->contains($pending));
    }

    public function test_listing_failed_scope_includes_failed_and_listing_failed(): void
    {
        $failed = $this->createFailedQueueItem();
        $listingFailed = $this->createQueueItem(['processing_status' => 'listing_failed']);
        $ready = $this->createReadyQueueItem();

        $results = ProductIntakeQueue::listingFailed()->get();

        $this->assertTrue($results->contains($failed));
        $this->assertTrue($results->contains($listingFailed));
        $this->assertFalse($results->contains($ready));
    }

    public function test_needs_review_scope(): void
    {
        $needsReview = $this->createNeedsReviewQueueItem();
        $ready = $this->createReadyQueueItem();

        $results = ProductIntakeQueue::needsReview()->get();

        $this->assertTrue($results->contains($needsReview));
        $this->assertFalse($results->contains($ready));
    }

    public function test_archived_scope(): void
    {
        $archived = $this->createQueueItem(['processing_status' => 'archived']);
        $pending = $this->createPendingQueueItem();

        $results = ProductIntakeQueue::archived()->get();

        $this->assertTrue($results->contains($archived));
        $this->assertFalse($results->contains($pending));
    }

    // =============================================
    // Scope Tests - Confidence Scopes
    // =============================================

    public function test_high_confidence_scope(): void
    {
        $high = $this->createQueueItem(['ai_confidence_score' => 0.90]);
        $low = $this->createQueueItem(['ai_confidence_score' => 0.50]);

        $results = ProductIntakeQueue::highConfidence()->get();

        $this->assertTrue($results->contains($high));
        $this->assertFalse($results->contains($low));
    }

    public function test_medium_confidence_scope(): void
    {
        $medium = $this->createQueueItem(['ai_confidence_score' => 0.70]);
        $high = $this->createQueueItem(['ai_confidence_score' => 0.90]);
        $low = $this->createQueueItem(['ai_confidence_score' => 0.40]);

        $results = ProductIntakeQueue::mediumConfidence()->get();

        $this->assertTrue($results->contains($medium));
        $this->assertFalse($results->contains($high));
        $this->assertFalse($results->contains($low));
    }

    public function test_low_confidence_scope(): void
    {
        $low = $this->createQueueItem(['ai_confidence_score' => 0.40]);
        $nullScore = $this->createQueueItem(['ai_confidence_score' => null]);
        $high = $this->createQueueItem(['ai_confidence_score' => 0.90]);

        $results = ProductIntakeQueue::lowConfidence()->get();

        $this->assertTrue($results->contains($low));
        $this->assertTrue($results->contains($nullScore));
        $this->assertFalse($results->contains($high));
    }

    // =============================================
    // Scope Tests - Organization/Team Scopes
    // =============================================

    public function test_for_team_scope(): void
    {
        $item = $this->createQueueItem();
        $otherTeam = Team::factory()->create(['organization_id' => $this->testOrganization->id]);
        $otherItem = $this->createQueueItem(['team_id' => $otherTeam->id]);

        $results = ProductIntakeQueue::forTeam($this->testTeam->id)->get();

        $this->assertTrue($results->contains($item));
        $this->assertFalse($results->contains($otherItem));
    }

    public function test_for_organization_scope(): void
    {
        $item = $this->createQueueItem();
        $otherOrg = Organization::factory()->create();
        $otherItem = ProductIntakeQueue::factory()->create(['organization_id' => $otherOrg->id]);

        $results = ProductIntakeQueue::forOrganization($this->testOrganization->id)->get();

        $this->assertTrue($results->contains($item));
        $this->assertFalse($results->contains($otherItem));
    }

    // =============================================
    // Scope Tests - Classification Scopes
    // =============================================

    public function test_category_scope(): void
    {
        $pos = $this->createPosEquipmentQueueItem();
        $other = $this->createQueueItem(['product_category' => 'consumer_electronics']);

        $results = ProductIntakeQueue::category('pos_equipment')->get();

        $this->assertTrue($results->contains($pos));
        $this->assertFalse($results->contains($other));
    }

    public function test_type_scope(): void
    {
        $completeUnit = $this->createQueueItem(['product_type' => 'complete_unit']);
        $part = $this->createQueueItem(['product_type' => 'replacement_part']);

        $results = ProductIntakeQueue::type('complete_unit')->get();

        $this->assertTrue($results->contains($completeUnit));
        $this->assertFalse($results->contains($part));
    }

    public function test_classification_scope(): void
    {
        $posUnit = $this->createQueueItem([
            'product_category' => 'pos_equipment',
            'product_type' => 'complete_unit',
        ]);
        $posPart = $this->createQueueItem([
            'product_category' => 'pos_equipment',
            'product_type' => 'replacement_part',
        ]);

        $results = ProductIntakeQueue::classification('pos_equipment', 'complete_unit')->get();

        $this->assertTrue($results->contains($posUnit));
        $this->assertFalse($results->contains($posPart));
    }

    public function test_classified_scope(): void
    {
        $classified = $this->createQueueItem([
            'product_category' => 'pos_equipment',
            'product_type' => 'complete_unit',
        ]);
        $unclassified = $this->createQueueItem([
            'product_category' => null,
            'product_type' => null,
        ]);

        $results = ProductIntakeQueue::classified()->get();

        $this->assertTrue($results->contains($classified));
        $this->assertFalse($results->contains($unclassified));
    }

    public function test_unclassified_scope(): void
    {
        $classified = $this->createQueueItem([
            'product_category' => 'pos_equipment',
            'product_type' => 'complete_unit',
        ]);
        $unclassified = $this->createQueueItem([
            'product_category' => null,
            'product_type' => null,
        ]);

        $results = ProductIntakeQueue::unclassified()->get();

        $this->assertTrue($results->contains($unclassified));
        $this->assertFalse($results->contains($classified));
    }

    public function test_order_by_priority_scope(): void
    {
        $lowPriority = $this->createQueueItem(['priority' => 1, 'created_at' => now()]);
        $highPriority = $this->createQueueItem(['priority' => 3, 'created_at' => now()]);
        $medPriority = $this->createQueueItem(['priority' => 2, 'created_at' => now()]);

        $results = ProductIntakeQueue::forTeam($this->testTeam->id)
            ->orderByPriority()
            ->get();

        $this->assertEquals($highPriority->id, $results->first()->id);
        $this->assertEquals($lowPriority->id, $results->last()->id);
    }

    // =============================================
    // Accessor Tests
    // =============================================

    public function test_condition_label_attribute(): void
    {
        $item = $this->createQueueItem(['condition' => 'NEW']);
        $this->assertEquals('Brand New', $item->condition_label);

        $item2 = $this->createQueueItem(['condition' => 'USED_GOOD']);
        $this->assertEquals('Used - Good', $item2->condition_label);

        $item3 = $this->createQueueItem(['condition' => 'FOR_PARTS_OR_NOT_WORKING']);
        $this->assertEquals('For Parts or Not Working', $item3->condition_label);
    }

    public function test_confidence_level_attribute(): void
    {
        $high = $this->createQueueItem(['ai_confidence_score' => 0.90]);
        $this->assertEquals('high', $high->confidence_level);

        $medium = $this->createQueueItem(['ai_confidence_score' => 0.70]);
        $this->assertEquals('medium', $medium->confidence_level);

        $low = $this->createQueueItem(['ai_confidence_score' => 0.40]);
        $this->assertEquals('low', $low->confidence_level);

        $unknown = $this->createQueueItem(['ai_confidence_score' => null]);
        $this->assertEquals('unknown', $unknown->confidence_level);
    }

    public function test_confidence_color_attribute(): void
    {
        $high = $this->createQueueItem(['ai_confidence_score' => 0.90]);
        $this->assertEquals('green', $high->confidence_color);

        $medium = $this->createQueueItem(['ai_confidence_score' => 0.70]);
        $this->assertEquals('yellow', $medium->confidence_color);

        $low = $this->createQueueItem(['ai_confidence_score' => 0.40]);
        $this->assertEquals('red', $low->confidence_color);

        $unknown = $this->createQueueItem(['ai_confidence_score' => null]);
        $this->assertEquals('gray', $unknown->confidence_color);
    }

    public function test_status_badge_attribute(): void
    {
        $statusBadges = [
            'ready' => 'Ready for Listing',
            'validated' => 'Validated',
            'needs_review' => 'Needs Review',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'listed_on_ebay' => 'Listed on eBay',
            'listing_failed' => 'Listing Failed',
            'failed' => 'Failed',
            'archived' => 'Archived',
        ];

        foreach ($statusBadges as $status => $expectedBadge) {
            $item = $this->createQueueItem(['processing_status' => $status]);
            $this->assertEquals($expectedBadge, $item->status_badge, "Failed for status: {$status}");
        }
    }

    public function test_primary_photo_attribute_returns_null_when_no_photos(): void
    {
        $item = $this->createQueueItem(['photos' => []]);
        $this->assertNull($item->primary_photo);
    }

    public function test_primary_photo_attribute_returns_first_photo(): void
    {
        $item = $this->createQueueItem(['photos' => ['queue-photos/photo1.jpg', 'queue-photos/photo2.jpg']]);
        $this->assertStringContainsString('photo1.jpg', $item->primary_photo);
    }

    public function test_photo_urls_attribute_returns_empty_array_when_no_photos(): void
    {
        $item = $this->createQueueItem(['photos' => []]);
        $this->assertEquals([], $item->photo_urls);
    }

    public function test_photo_urls_attribute_returns_all_photos(): void
    {
        $item = $this->createQueueItem(['photos' => ['queue-photos/photo1.jpg', 'queue-photos/photo2.jpg']]);
        $this->assertCount(2, $item->photo_urls);
    }

    public function test_category_label_attribute(): void
    {
        $item = $this->createQueueItem(['product_category' => 'pos_equipment']);
        $this->assertEquals('POS Equipment', $item->category_label);
    }

    public function test_type_label_attribute(): void
    {
        $item = $this->createQueueItem(['product_type' => 'complete_unit']);
        $this->assertEquals('Complete Unit/System', $item->type_label);
    }

    public function test_classification_context_attribute(): void
    {
        $item = $this->createQueueItem([
            'product_category' => 'pos_equipment',
            'product_type' => 'complete_unit',
        ]);

        $context = $item->classification_context;

        $this->assertEquals('pos_equipment', $context['category']);
        $this->assertEquals('POS Equipment', $context['category_label']);
        $this->assertEquals('complete_unit', $context['type']);
        $this->assertEquals('Complete Unit/System', $context['type_label']);
        $this->assertTrue($context['classification_available']);
        $this->assertNotEmpty($context['ai_research_hint']);
    }

    // =============================================
    // Helper Method Tests
    // =============================================

    public function test_has_photos_returns_true_when_photos_exist(): void
    {
        $item = $this->createQueueItem(['photos' => ['queue-photos/photo1.jpg']]);
        $this->assertTrue($item->hasPhotos());
    }

    public function test_has_photos_returns_false_when_no_photos(): void
    {
        $item = $this->createQueueItem(['photos' => []]);
        $this->assertFalse($item->hasPhotos());

        $itemNull = $this->createQueueItem(['photos' => null]);
        $this->assertFalse($itemNull->hasPhotos());
    }

    public function test_has_barcode_returns_true_when_barcode_exists(): void
    {
        $item = $this->createQueueItem(['barcode' => '012345678901']);
        $this->assertTrue($item->hasBarcode());
    }

    public function test_has_barcode_returns_false_when_no_barcode(): void
    {
        $item = $this->createQueueItem(['barcode' => null]);
        $this->assertFalse($item->hasBarcode());
    }

    public function test_has_ai_analysis_returns_true_when_complete(): void
    {
        $item = $this->createQueueItemWithAiAnalysis();
        $item->update(['ai_analysis_status' => 'complete']);
        $this->assertTrue($item->hasAiAnalysis());
    }

    public function test_has_ai_analysis_returns_false_when_not_complete(): void
    {
        $item = $this->createQueueItem([
            'ai_analysis_status' => 'pending',
            'ai_analysis_id' => null,
        ]);
        $this->assertFalse($item->hasAiAnalysis());
    }

    public function test_is_ready_for_processing(): void
    {
        $ready = $this->createQueueItem([
            'processing_status' => 'pending',
            'ai_analysis_status' => 'complete',
        ]);
        $this->assertTrue($ready->isReadyForProcessing());

        $notReady = $this->createQueueItem([
            'processing_status' => 'in_progress',
            'ai_analysis_status' => 'complete',
        ]);
        $this->assertFalse($notReady->isReadyForProcessing());
    }

    public function test_is_ready_for_listing(): void
    {
        $ready = $this->createQueueItem(['processing_status' => 'ready']);
        $this->assertTrue($ready->isReadyForListing());

        $validated = $this->createQueueItem(['processing_status' => 'validated']);
        $this->assertTrue($validated->isReadyForListing());

        $pending = $this->createPendingQueueItem();
        $this->assertFalse($pending->isReadyForListing());
    }

    public function test_mark_as_in_progress(): void
    {
        $item = $this->createPendingQueueItem();

        $item->markAsInProgress($this->testUser);

        $this->assertEquals('in_progress', $item->fresh()->processing_status);
        $this->assertEquals($this->testUser->id, $item->fresh()->processed_by);
    }

    public function test_mark_as_completed(): void
    {
        $item = $this->createReadyQueueItem();
        $product = \App\Models\Catalog\Product::factory()->create([
            'organization_id' => $this->testOrganization->id,
        ]);

        $item->markAsCompleted($product, $this->testUser);

        $fresh = $item->fresh();
        $this->assertEquals('completed', $fresh->processing_status);
        $this->assertEquals($product->id, $fresh->product_id);
        $this->assertEquals($this->testUser->id, $fresh->processed_by);
        $this->assertNotNull($fresh->processed_at);
    }

    public function test_archive(): void
    {
        $item = $this->createCompletedQueueItem();

        $item->archive();

        $this->assertEquals('archived', $item->fresh()->processing_status);
    }

    public function test_get_ai_research_hint_with_classification(): void
    {
        $item = $this->createQueueItem([
            'product_category' => 'pos_equipment',
            'product_type' => 'complete_unit',
        ]);

        $hint = $item->getAiResearchHint();

        $this->assertStringContainsString('POS', $hint);
    }

    public function test_get_ai_research_hint_without_classification(): void
    {
        $item = $this->createQueueItem([
            'product_category' => null,
            'product_type' => null,
        ]);

        $hint = $item->getAiResearchHint();

        $this->assertEquals('Use general product research approach', $hint);
    }

    // =============================================
    // Constants Tests
    // =============================================

    public function test_product_categories_constant_contains_expected_keys(): void
    {
        $categories = ProductIntakeQueue::PRODUCT_CATEGORIES;

        $this->assertArrayHasKey('pos_equipment', $categories);
        $this->assertArrayHasKey('network_equipment', $categories);
        $this->assertArrayHasKey('computer_hardware', $categories);
        $this->assertArrayHasKey('consumer_electronics', $categories);
        $this->assertArrayHasKey('other', $categories);
    }

    public function test_product_types_constant_contains_expected_keys(): void
    {
        $types = ProductIntakeQueue::PRODUCT_TYPES;

        $this->assertArrayHasKey('complete_unit', $types);
        $this->assertArrayHasKey('replacement_part', $types);
        $this->assertArrayHasKey('accessory', $types);
        $this->assertArrayHasKey('component', $types);
        $this->assertArrayHasKey('consumable', $types);
    }
}
