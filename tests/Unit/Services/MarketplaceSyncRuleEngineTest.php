<?php

namespace Tests\Unit\Services;

use App\Models\Catalog\Product;
use App\Models\MarketplaceSyncRule;
use App\Models\Organization\Organization;
use App\Services\MarketplaceSyncRuleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceSyncRuleEngineTest extends TestCase
{
    use RefreshDatabase;

    protected MarketplaceSyncRuleEngine $engine;

    protected Organization $org;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new MarketplaceSyncRuleEngine;
        $this->org = Organization::factory()->create();

        $this->product = Product::factory()->create([
            'organization_id' => $this->org->id,
            'selling_price' => 50.00,
            'condition' => 'NEW',
            'marketplace_overrides' => null,
        ]);
    }

    // -------------------------------------------------------
    // Per-product override tests
    // -------------------------------------------------------

    public function test_manual_include_override_returns_true(): void
    {
        $this->product->marketplace_overrides = ['ebay' => 'include'];
        $this->assertTrue($this->engine->shouldSync($this->product, 'ebay'));
    }

    public function test_manual_exclude_override_returns_false(): void
    {
        $this->product->marketplace_overrides = ['ebay' => 'exclude'];
        $this->assertFalse($this->engine->shouldSync($this->product, 'ebay'));
    }

    public function test_override_only_applies_to_its_platform(): void
    {
        $this->product->marketplace_overrides = ['shopify' => 'exclude'];
        // 'ebay' has no override, no rules → default true
        $this->assertTrue($this->engine->shouldSync($this->product, 'ebay'));
        $this->assertFalse($this->engine->shouldSync($this->product, 'shopify'));
    }

    // -------------------------------------------------------
    // Default (no rules, no override) tests
    // -------------------------------------------------------

    public function test_default_is_true_when_no_rules_exist(): void
    {
        $this->assertTrue($this->engine->shouldSync($this->product, 'ebay'));
        $this->assertTrue($this->engine->shouldSync($this->product, 'shopify'));
    }

    // -------------------------------------------------------
    // Rule matching tests
    // -------------------------------------------------------

    public function test_matching_include_rule_returns_true(): void
    {
        MarketplaceSyncRule::create([
            'organization_id' => $this->org->id,
            'name' => 'Include new items',
            'marketplace' => 'ebay',
            'action' => 'include',
            'priority' => 10,
            'conditions' => [
                ['field' => 'condition', 'operator' => 'in', 'value' => ['NEW', 'NEW_OTHER']],
            ],
            'is_active' => true,
        ]);

        $this->assertTrue($this->engine->shouldSync($this->product, 'ebay'));
    }

    public function test_matching_exclude_rule_returns_false(): void
    {
        MarketplaceSyncRule::create([
            'organization_id' => $this->org->id,
            'name' => 'Exclude new items',
            'marketplace' => 'ebay',
            'action' => 'exclude',
            'priority' => 10,
            'conditions' => [
                ['field' => 'condition', 'operator' => 'in', 'value' => ['NEW']],
            ],
            'is_active' => true,
        ]);

        $this->assertFalse($this->engine->shouldSync($this->product, 'ebay'));
    }

    public function test_non_matching_rule_falls_through_to_default(): void
    {
        MarketplaceSyncRule::create([
            'organization_id' => $this->org->id,
            'name' => 'Exclude used items',
            'marketplace' => 'ebay',
            'action' => 'exclude',
            'priority' => 10,
            'conditions' => [
                ['field' => 'condition', 'operator' => 'in', 'value' => ['USED_GOOD']],
            ],
            'is_active' => true,
        ]);

        // product condition is 'NEW' — rule doesn't match, default = true
        $this->assertTrue($this->engine->shouldSync($this->product, 'ebay'));
    }

    public function test_priority_order_first_match_wins(): void
    {
        // Priority 5 (evaluated first): exclude NEW
        MarketplaceSyncRule::create([
            'organization_id' => $this->org->id,
            'name' => 'Exclude new',
            'marketplace' => 'ebay',
            'action' => 'exclude',
            'priority' => 5,
            'conditions' => [
                ['field' => 'condition', 'operator' => 'in', 'value' => ['NEW']],
            ],
            'is_active' => true,
        ]);

        // Priority 20 (evaluated second): include NEW
        MarketplaceSyncRule::create([
            'organization_id' => $this->org->id,
            'name' => 'Include new',
            'marketplace' => 'ebay',
            'action' => 'include',
            'priority' => 20,
            'conditions' => [
                ['field' => 'condition', 'operator' => 'in', 'value' => ['NEW']],
            ],
            'is_active' => true,
        ]);

        // First match (priority 5, exclude) should win
        $this->assertFalse($this->engine->shouldSync($this->product, 'ebay'));
    }

    public function test_and_logic_all_conditions_must_match(): void
    {
        MarketplaceSyncRule::create([
            'organization_id' => $this->org->id,
            'name' => 'Exclude cheap new items',
            'marketplace' => 'ebay',
            'action' => 'exclude',
            'priority' => 10,
            'conditions' => [
                ['field' => 'condition', 'operator' => 'in', 'value' => ['NEW']],
                ['field' => 'selling_price', 'operator' => 'less_than', 'value' => 25.00], // 50 is NOT < 25
            ],
            'is_active' => true,
        ]);

        // Second condition fails (50 is not < 25), so rule doesn't match, default = true
        $this->assertTrue($this->engine->shouldSync($this->product, 'ebay'));
    }

    public function test_between_operator(): void
    {
        MarketplaceSyncRule::create([
            'organization_id' => $this->org->id,
            'name' => 'Exclude mid-range',
            'marketplace' => 'ebay',
            'action' => 'exclude',
            'priority' => 10,
            'conditions' => [
                ['field' => 'selling_price', 'operator' => 'between', 'value' => [40.00, 60.00]], // 50 is in range
            ],
            'is_active' => true,
        ]);

        $this->assertFalse($this->engine->shouldSync($this->product, 'ebay'));
    }

    public function test_inactive_rule_is_ignored(): void
    {
        MarketplaceSyncRule::create([
            'organization_id' => $this->org->id,
            'name' => 'Inactive exclude',
            'marketplace' => 'ebay',
            'action' => 'exclude',
            'priority' => 10,
            'conditions' => [
                ['field' => 'condition', 'operator' => 'in', 'value' => ['NEW']],
            ],
            'is_active' => false,
        ]);

        // Rule is inactive → default = true
        $this->assertTrue($this->engine->shouldSync($this->product, 'ebay'));
    }

    public function test_wildcard_platform_matches_any_marketplace(): void
    {
        MarketplaceSyncRule::create([
            'organization_id' => $this->org->id,
            'name' => 'Exclude all',
            'marketplace' => '*',
            'action' => 'exclude',
            'priority' => 10,
            'conditions' => [
                ['field' => 'condition', 'operator' => 'in', 'value' => ['NEW']],
            ],
            'is_active' => true,
        ]);

        $this->assertFalse($this->engine->shouldSync($this->product, 'ebay'));
        $this->assertFalse($this->engine->shouldSync($this->product, 'shopify'));
    }

    public function test_rule_from_different_org_not_applied(): void
    {
        $otherOrg = Organization::factory()->create();

        MarketplaceSyncRule::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other org rule',
            'marketplace' => 'ebay',
            'action' => 'exclude',
            'priority' => 10,
            'conditions' => [
                ['field' => 'condition', 'operator' => 'in', 'value' => ['NEW']],
            ],
            'is_active' => true,
        ]);

        // Rule belongs to a different org → default = true
        $this->assertTrue($this->engine->shouldSync($this->product, 'ebay'));
    }

    // -------------------------------------------------------
    // Override beats rule
    // -------------------------------------------------------

    public function test_manual_exclude_override_beats_include_rule(): void
    {
        $this->product->marketplace_overrides = ['ebay' => 'exclude'];

        MarketplaceSyncRule::create([
            'organization_id' => $this->org->id,
            'name' => 'Include all',
            'marketplace' => 'ebay',
            'action' => 'include',
            'priority' => 0,
            'conditions' => [],
            'is_active' => true,
        ]);

        // Override wins
        $this->assertFalse($this->engine->shouldSync($this->product, 'ebay'));
    }

    // -------------------------------------------------------
    // getDetailedSyncStatus tests
    // -------------------------------------------------------

    public function test_detailed_status_returns_auto_label_by_default(): void
    {
        $status = $this->engine->getDetailedSyncStatus($this->product, ['ebay', 'shopify']);

        $this->assertArrayHasKey('ebay', $status);
        $this->assertEquals('default', $status['ebay']['reason']);
        $this->assertTrue($status['ebay']['syncs']);
    }

    public function test_detailed_status_returns_manual_include_label(): void
    {
        $this->product->marketplace_overrides = ['ebay' => 'include'];
        $status = $this->engine->getDetailedSyncStatus($this->product, ['ebay']);

        $this->assertEquals('manual_include', $status['ebay']['reason']);
    }

    public function test_detailed_status_shows_rule_name_when_matched(): void
    {
        MarketplaceSyncRule::create([
            'organization_id' => $this->org->id,
            'name' => 'My Exclude Rule',
            'marketplace' => 'ebay',
            'action' => 'exclude',
            'priority' => 10,
            'conditions' => [
                ['field' => 'condition', 'operator' => 'in', 'value' => ['NEW']],
            ],
            'is_active' => true,
        ]);

        $status = $this->engine->getDetailedSyncStatus($this->product, ['ebay']);

        $this->assertEquals('rule_exclude', $status['ebay']['reason']);
        $this->assertEquals('My Exclude Rule', $status['ebay']['rule']['name']);
    }
}
