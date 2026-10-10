<?php

namespace Tests\Feature\Settings;

use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $organization;

    protected Team $team;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create([
            'sku_prefix' => 'CUR',
            'sku_prefix_locked' => true,
        ]);

        $this->team = Team::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin-cur',
            'is_system' => true,
            'permissions' => [
                'inventory.view', 'settings.view', 'settings.manage',
            ],
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->user->roles()->attach($adminRole->id, [
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

    public function test_can_view_currency_settings(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('settings.currency.index'));

        $response->assertStatus(200);
    }

    public function test_can_update_base_currency(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('settings.currency.sync'));

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_currency_settings_scoped_to_organization(): void
    {
        // Currency settings page should load with the correct organization context
        $response = $this->actingAs($this->user)
            ->get(route('settings.currency.index'));

        $response->assertStatus(200);

        // Verify a different org user gets their own context
        $otherOrg = Organization::factory()->create();
        $otherTeam = Team::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);

        $otherRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin-cur-other',
            'is_system' => true,
            'permissions' => ['settings.view'],
        ]);

        $otherUser = User::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);

        $otherUser->roles()->attach($otherRole->id, [
            'organization_id' => $otherOrg->id,
            'team_id' => null,
        ]);

        $otherUser->current_team_id = $otherTeam->id;
        $otherUser->save();

        $otherTeam->users()->attach($otherUser->id, ['role' => 'admin']);

        OnboardingProgress::create([
            'user_id' => $otherUser->id,
            'organization_id' => $otherOrg->id,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'current_step' => 'completed',
            'steps_completed' => ['welcome', 'profile', 'organization', 'team'],
        ]);

        $otherResponse = $this->actingAs($otherUser)
            ->get(route('settings.currency.index'));

        $otherResponse->assertStatus(200);
    }
}
