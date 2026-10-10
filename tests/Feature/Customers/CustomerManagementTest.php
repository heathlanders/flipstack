<?php

namespace Tests\Feature\Customers;

use App\Models\Customer\Customer;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

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
                'customers.view', 'customers.create', 'customers.update', 'customers.delete',
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

    public function test_can_view_customers_index(): void
    {
        Customer::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('customers.index'));

        $response->assertStatus(200);
    }

    public function test_can_view_customer_detail(): void
    {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('customers.show', $customer));

        $response->assertStatus(200);
    }

    public function test_can_create_customer(): void
    {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertDatabaseHas('customers', [
            'organization_id' => $this->organization->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    public function test_can_update_customer(): void
    {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $customer->update([
            'name' => 'Updated Customer Name',
            'email' => 'updated@example.com',
            'phone' => '555-1234',
            'notes' => 'Updated notes',
            'is_flagged' => true,
            'flag_reason' => 'Frequent returns',
        ]);

        $customer->refresh();
        $this->assertEquals('Updated Customer Name', $customer->name);
        $this->assertEquals('updated@example.com', $customer->email);
        $this->assertTrue($customer->is_flagged);
        $this->assertEquals('Frequent returns', $customer->flag_reason);
    }

    public function test_customer_scoped_to_organization(): void
    {
        $otherOrg = Organization::factory()->create();

        $myCustomer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $otherCustomer = Customer::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);

        $orgCustomers = Customer::withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->get();

        $this->assertTrue($orgCustomers->contains($myCustomer));
        $this->assertFalse($orgCustomers->contains($otherCustomer));
    }
}
