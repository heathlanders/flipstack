<?php

namespace Tests\Feature\Audit;

use App\Jobs\Analytics\AggregateAuditDerivedKPIs;
use App\Models\Analytics\UserProductivityDaily;
use App\Models\Organization\Organization;
use App\Models\User\User;
use App\Services\Analytics\UserProductivityService;
use App\Support\AuditContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AggregateAuditDerivedKPIsTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected User $userA;

    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();
        AuditContext::reset();

        $this->org = Organization::factory()->create();
        $this->userA = User::factory()->create(['organization_id' => $this->org->id]);
        $this->userB = User::factory()->create(['organization_id' => $this->org->id]);
    }

    public function test_aggregator_counts_action_prefixes_per_user(): void
    {
        $date = now()->subDay()->toDateString();

        // User A: 3 product creates + 1 PO created + 2 messages answered + 1 inventory adjust
        $this->seedRow('product.created', $this->userA, $date);
        $this->seedRow('product.created', $this->userA, $date);
        $this->seedRow('product.created', $this->userA, $date);
        $this->seedRow('po.created', $this->userA, $date);
        $this->seedRow('message.replied', $this->userA, $date);
        $this->seedRow('message.replied', $this->userA, $date);
        $this->seedRow('inventory.adjusted', $this->userA, $date);

        // User B: 1 ebay publish + 2 customer updates
        $this->seedRow('listing.ebay.published', $this->userB, $date);
        $this->seedRow('customer.updated', $this->userB, $date);
        $this->seedRow('customer.updated', $this->userB, $date);

        // Other day — should NOT be counted
        $this->seedRow('product.created', $this->userA, now()->subDays(3)->toDateString());

        (new AggregateAuditDerivedKPIs((int) $this->org->id, $date))->handle();

        $rowA = UserProductivityDaily::where('user_id', $this->userA->id)->first();
        $rowB = UserProductivityDaily::where('user_id', $this->userB->id)->first();

        $this->assertNotNull($rowA);
        $this->assertSame(3, $rowA->products_listed_count);
        $this->assertSame(1, $rowA->pos_created_count);
        $this->assertSame(2, $rowA->messages_answered_count);
        $this->assertSame(1, $rowA->inventory_moves_count);
        $this->assertSame(0, $rowA->listings_published_ebay);

        $this->assertNotNull($rowB);
        $this->assertSame(1, $rowB->listings_published_ebay);
        $this->assertSame(2, $rowB->customer_records_touched);
    }

    public function test_aggregator_is_idempotent_on_upsert(): void
    {
        $date = now()->subDay()->toDateString();
        $this->seedRow('product.created', $this->userA, $date);

        (new AggregateAuditDerivedKPIs((int) $this->org->id, $date))->handle();
        (new AggregateAuditDerivedKPIs((int) $this->org->id, $date))->handle();

        $this->assertSame(1, UserProductivityDaily::where('user_id', $this->userA->id)->count());
        $this->assertSame(1, UserProductivityDaily::where('user_id', $this->userA->id)->first()->products_listed_count);
    }

    public function test_user_productivity_service_returns_per_user_totals(): void
    {
        $date = now()->subDay()->toDateString();
        UserProductivityDaily::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->userA->id,
            'performance_date' => $date,
            'products_listed_count' => 5,
            'pos_created_count' => 2,
        ]);

        $totals = (new UserProductivityService)
            ->forOrganization((int) $this->org->id)
            ->dateRange(now()->subDays(7)->toDateString(), now()->toDateString())
            ->perUserTotals();

        $this->assertCount(1, $totals);
        $this->assertSame(5, (int) $totals[0]['products_listed']);
        $this->assertSame(2, (int) $totals[0]['pos_created']);
    }

    public function test_user_productivity_service_leaderboard(): void
    {
        $date = now()->subDay()->toDateString();
        UserProductivityDaily::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->userA->id,
            'performance_date' => $date,
            'products_listed_count' => 5,
        ]);
        UserProductivityDaily::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->userB->id,
            'performance_date' => $date,
            'products_listed_count' => 9,
        ]);

        $board = (new UserProductivityService)
            ->forOrganization((int) $this->org->id)
            ->dateRange(now()->subDays(7)->toDateString(), now()->toDateString())
            ->leaderboard('products_listed_count');

        $this->assertSame($this->userB->id, $board[0]['user_id']);
        $this->assertSame(9, $board[0]['value']);
        $this->assertSame($this->userA->id, $board[1]['user_id']);
    }

    protected function seedRow(string $action, User $user, string $date): void
    {
        // Insert via DB facade so we can pin created_at to the target date.
        // Eloquent's $timestamps auto-fill would otherwise stamp now() and the aggregator
        // (which filters by whereDate('created_at', $date)) would miss the row.
        \DB::table('user_activity_logs')->insert([
            'user_id' => $user->id,
            'organization_id' => $this->org->id,
            'action' => $action,
            'description' => $action,
            'metadata' => '[]',
            'severity' => 'info',
            'category' => 'general',
            'created_at' => $date.' 12:00:00',
            'updated_at' => $date.' 12:00:00',
        ]);
    }
}
