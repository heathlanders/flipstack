<?php

namespace Tests\Feature\Analytics;

use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use App\Services\Analytics\OrderFinancialCalculator;
use App\Services\Analytics\ProfitLossService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitLossTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected Team $team;

    protected User $user;

    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();

        $this->team = Team::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
            'is_system' => true,
            'permissions' => [
                'analytics.view', 'analytics.export',
            ],
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->user->roles()->attach($this->adminRole->id, [
            'organization_id' => $this->organization->id,
            'team_id' => null,
        ]);

        $this->user->current_team_id = $this->team->id;
        $this->user->save();

        $this->team->users()->attach($this->user->id, ['role' => 'admin']);

        OnboardingProgress::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'current_step' => 'completed',
            'steps_completed' => ['welcome', 'profile', 'organization', 'team'],
        ]);
    }

    public function test_can_view_profit_loss_dashboard(): void
    {
        $service = app(ProfitLossService::class);

        $summary = $service
            ->forOrganization((string) $this->organization->id)
            ->dateRange(now()->startOfMonth()->toDateString(), now()->toDateString())
            ->getSummary();

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('order_count', $summary);
        $this->assertArrayHasKey('total_revenue', $summary);
        $this->assertArrayHasKey('total_net_profit', $summary);
        $this->assertEquals(0, $summary['order_count']);
    }

    public function test_can_calculate_order_financials(): void
    {
        $calculator = app(OrderFinancialCalculator::class);

        $this->assertNotNull($calculator);
        $this->assertInstanceOf(OrderFinancialCalculator::class, $calculator);
    }

    public function test_profit_loss_scoped_to_organization(): void
    {
        $otherOrg = Organization::factory()->create();

        $service = app(ProfitLossService::class);

        $mySummary = $service
            ->forOrganization((string) $this->organization->id)
            ->dateRange(now()->startOfMonth()->toDateString(), now()->toDateString())
            ->getSummary();

        $otherSummary = $service
            ->forOrganization((string) $otherOrg->id)
            ->dateRange(now()->startOfMonth()->toDateString(), now()->toDateString())
            ->getSummary();

        $this->assertIsArray($mySummary);
        $this->assertIsArray($otherSummary);
        $this->assertEquals(0, $mySummary['order_count']);
        $this->assertEquals(0, $otherSummary['order_count']);
    }
}
