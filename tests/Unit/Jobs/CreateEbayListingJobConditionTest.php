<?php

namespace Tests\Unit\Jobs;

use App\Jobs\CreateEbayListingJob;
use App\Models\Catalog\ProductIntakeQueue;
use Tests\TestCase;

class CreateEbayListingJobConditionTest extends TestCase
{
    private CreateEbayListingJobTestable $job;

    protected function setUp(): void
    {
        parent::setUp();
        $queueItem = new ProductIntakeQueue;
        $this->job = new CreateEbayListingJobTestable($queueItem);
    }

    public function test_full_ebay_enum_used_good_maps_correctly(): void
    {
        $this->assertEquals('USED_GOOD', $this->job->publicMapCondition('USED_GOOD'));
    }

    public function test_full_ebay_enum_used_very_good_maps_correctly(): void
    {
        $this->assertEquals('USED_VERY_GOOD', $this->job->publicMapCondition('USED_VERY_GOOD'));
    }

    public function test_full_ebay_enum_used_excellent_maps_correctly(): void
    {
        $this->assertEquals('USED_EXCELLENT', $this->job->publicMapCondition('USED_EXCELLENT'));
    }

    public function test_full_ebay_enum_used_acceptable_maps_correctly(): void
    {
        $this->assertEquals('USED_ACCEPTABLE', $this->job->publicMapCondition('USED_ACCEPTABLE'));
    }

    public function test_full_ebay_enum_new_maps_correctly(): void
    {
        $this->assertEquals('NEW', $this->job->publicMapCondition('NEW'));
    }

    public function test_full_ebay_enum_new_other_maps_correctly(): void
    {
        $this->assertEquals('NEW_OTHER', $this->job->publicMapCondition('NEW_OTHER'));
    }

    public function test_full_ebay_enum_for_parts_maps_correctly(): void
    {
        $this->assertEquals('FOR_PARTS_OR_NOT_WORKING', $this->job->publicMapCondition('FOR_PARTS_OR_NOT_WORKING'));
    }

    public function test_full_ebay_enum_certified_refurbished_maps_correctly(): void
    {
        $this->assertEquals('CERTIFIED_REFURBISHED', $this->job->publicMapCondition('CERTIFIED_REFURBISHED'));
    }

    public function test_legacy_shorthand_good_maps_correctly(): void
    {
        $this->assertEquals('USED_GOOD', $this->job->publicMapCondition('good'));
    }

    public function test_legacy_shorthand_refurbished_maps_correctly(): void
    {
        $this->assertEquals('CERTIFIED_REFURBISHED', $this->job->publicMapCondition('refurbished'));
    }

    public function test_legacy_shorthand_parts_maps_correctly(): void
    {
        $this->assertEquals('FOR_PARTS_OR_NOT_WORKING', $this->job->publicMapCondition('parts'));
    }

    public function test_null_defaults_to_new(): void
    {
        $this->assertEquals('NEW', $this->job->publicMapCondition(null));
    }

    public function test_unknown_value_defaults_to_new(): void
    {
        $this->assertEquals('NEW', $this->job->publicMapCondition('completely_unknown'));
    }

    // findClosestValidCondition — within-family fallbacks

    public function test_fallback_used_good_falls_back_to_used_very_good_within_family(): void
    {
        $result = $this->job->publicFindClosestValidCondition('USED_GOOD', ['USED_VERY_GOOD', 'NEW']);
        $this->assertEquals('USED_VERY_GOOD', $result);
    }

    public function test_fallback_used_good_falls_back_to_used_acceptable_within_family(): void
    {
        $result = $this->job->publicFindClosestValidCondition('USED_GOOD', ['USED_ACCEPTABLE', 'NEW']);
        $this->assertEquals('USED_ACCEPTABLE', $result);
    }

    public function test_fallback_used_good_returns_null_when_category_only_has_refurbished(): void
    {
        // Must NOT cross into refurbished family — misrepresents the item
        $result = $this->job->publicFindClosestValidCondition('USED_GOOD', ['GOOD_REFURBISHED', 'NEW']);
        $this->assertNull($result);
    }

    public function test_fallback_used_good_returns_null_when_no_used_conditions_available(): void
    {
        $result = $this->job->publicFindClosestValidCondition('USED_GOOD', ['NEW', 'FOR_PARTS_OR_NOT_WORKING']);
        $this->assertNull($result);
    }

    public function test_fallback_used_excellent_falls_back_to_used_very_good(): void
    {
        $result = $this->job->publicFindClosestValidCondition('USED_EXCELLENT', ['USED_VERY_GOOD', 'NEW']);
        $this->assertEquals('USED_VERY_GOOD', $result);
    }

    public function test_fallback_used_excellent_does_not_map_to_excellent_refurbished(): void
    {
        // Cross-family: must return null, not EXCELLENT_REFURBISHED
        $result = $this->job->publicFindClosestValidCondition('USED_EXCELLENT', ['EXCELLENT_REFURBISHED', 'NEW']);
        $this->assertNull($result);
    }

    public function test_fallback_manufacturer_refurbished_maps_to_certified_refurbished(): void
    {
        $result = $this->job->publicFindClosestValidCondition('MANUFACTURER_REFURBISHED', ['CERTIFIED_REFURBISHED', 'NEW']);
        $this->assertEquals('CERTIFIED_REFURBISHED', $result);
    }

    public function test_fallback_excellent_refurbished_falls_back_to_very_good_refurbished(): void
    {
        $result = $this->job->publicFindClosestValidCondition('EXCELLENT_REFURBISHED', ['VERY_GOOD_REFURBISHED', 'USED_GOOD']);
        $this->assertEquals('VERY_GOOD_REFURBISHED', $result);
    }

    public function test_fallback_parts_returns_null_no_family_adjacents(): void
    {
        $result = $this->job->publicFindClosestValidCondition('FOR_PARTS_OR_NOT_WORKING', ['USED_ACCEPTABLE', 'USED_GOOD']);
        $this->assertNull($result);
    }
}

/**
 * Testable subclass that exposes the protected methods.
 */
class CreateEbayListingJobTestable extends CreateEbayListingJob
{
    public function publicMapCondition(?string $condition): string
    {
        return $this->mapConditionToEbay($condition);
    }

    public function publicFindClosestValidCondition(string $requested, array $validConditions): ?string
    {
        return $this->findClosestValidCondition($requested, $validConditions);
    }
}
