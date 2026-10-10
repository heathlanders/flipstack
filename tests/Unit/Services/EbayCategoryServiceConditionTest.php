<?php

namespace Tests\Unit\Services;

use App\Services\EbayCategoryService;
use App\Services\EbayRateLimiter;
use Tests\TestCase;

class EbayCategoryServiceConditionTest extends TestCase
{
    private EbayCategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EbayCategoryService(app(EbayRateLimiter::class));
    }

    public function test_fallback_used_good_to_used_very_good(): void
    {
        $result = $this->service->findClosestValidCondition('USED_GOOD', ['USED_VERY_GOOD', 'NEW']);
        $this->assertEquals('USED_VERY_GOOD', $result);
    }

    public function test_fallback_returns_null_when_no_family_match(): void
    {
        $result = $this->service->findClosestValidCondition('USED_GOOD', ['NEW', 'FOR_PARTS_OR_NOT_WORKING']);
        $this->assertNull($result);
    }

    public function test_no_cross_family_mapping_used_to_refurbished(): void
    {
        $result = $this->service->findClosestValidCondition('USED_GOOD', ['GOOD_REFURBISHED', 'NEW']);
        $this->assertNull($result);
    }

    public function test_fallback_manufacturer_refurbished_to_certified(): void
    {
        $result = $this->service->findClosestValidCondition('MANUFACTURER_REFURBISHED', ['CERTIFIED_REFURBISHED', 'NEW']);
        $this->assertEquals('CERTIFIED_REFURBISHED', $result);
    }

    public function test_fallback_parts_returns_null(): void
    {
        $result = $this->service->findClosestValidCondition('FOR_PARTS_OR_NOT_WORKING', ['USED_GOOD', 'NEW']);
        $this->assertNull($result);
    }

    public function test_fallback_used_excellent_to_used_very_good(): void
    {
        $result = $this->service->findClosestValidCondition('USED_EXCELLENT', ['USED_VERY_GOOD', 'NEW']);
        $this->assertEquals('USED_VERY_GOOD', $result);
    }

    public function test_no_cross_family_used_excellent_to_excellent_refurbished(): void
    {
        $result = $this->service->findClosestValidCondition('USED_EXCELLENT', ['EXCELLENT_REFURBISHED', 'NEW']);
        $this->assertNull($result);
    }

    public function test_get_condition_family_used(): void
    {
        $this->assertEquals('used', $this->service->getConditionFamily('USED_GOOD'));
        $this->assertEquals('used', $this->service->getConditionFamily('USED_EXCELLENT'));
        $this->assertEquals('used', $this->service->getConditionFamily('USED_VERY_GOOD'));
        $this->assertEquals('used', $this->service->getConditionFamily('USED_ACCEPTABLE'));
    }

    public function test_get_condition_family_new(): void
    {
        $this->assertEquals('new', $this->service->getConditionFamily('NEW'));
        $this->assertEquals('new', $this->service->getConditionFamily('NEW_OTHER'));
        $this->assertEquals('new', $this->service->getConditionFamily('NEW_WITH_DEFECTS'));
    }

    public function test_get_condition_family_refurbished(): void
    {
        $this->assertEquals('refurbished', $this->service->getConditionFamily('CERTIFIED_REFURBISHED'));
        $this->assertEquals('refurbished', $this->service->getConditionFamily('MANUFACTURER_REFURBISHED'));
        $this->assertEquals('refurbished', $this->service->getConditionFamily('SELLER_REFURBISHED'));
    }

    public function test_get_condition_family_parts(): void
    {
        $this->assertEquals('parts', $this->service->getConditionFamily('FOR_PARTS_OR_NOT_WORKING'));
    }

    public function test_get_condition_family_unknown(): void
    {
        $this->assertEquals('unknown', $this->service->getConditionFamily('SOMETHING_WEIRD'));
    }
}
