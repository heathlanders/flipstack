<?php

namespace Tests\Feature\Api;

use App\Models\Organization\Organization;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\AssertsAudits;
use Tests\TestCase;

/**
 * Covers the mobile API authz middlewares (api.permission / api.role) in both
 * soft (audit-only) and enforce (403) modes, plus owner/super_admin bypass.
 *
 * Permission-backed target: POST /api/inventory/adjust (api.permission:inventory.manage_stock)
 * Role-backed target:       GET  /api/fulfillment/pick  (api.role:fulfillment_manager,...)
 */
class MobileAuthzTest extends TestCase
{
    use AssertsAudits;
    use RefreshDatabase;

    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
    }

    private function userWithRole(string $slug, array $permissions = []): User
    {
        $role = Role::create([
            'name' => ucfirst(str_replace('_', ' ', $slug)),
            'slug' => $slug,
            'scope' => 'organization',
            'permissions' => $permissions,
            'organization_id' => $this->organization->id,
            'is_active' => true,
            'level' => 100,
        ]);

        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $user->roles()->attach($role->id, [
            'organization_id' => $this->organization->id,
            'team_id' => null,
        ]);

        return $user->fresh();
    }

    // ---- permission-backed route -------------------------------------------

    public function test_permission_soft_mode_allows_but_audits(): void
    {
        config(['mobile.authz_enforce' => false]);
        $user = $this->userWithRole('support_agent', []); // lacks inventory.manage_stock
        Sanctum::actingAs($user, ['*']);

        // Note: the controller's FormRequest may 403 on its own; we assert the
        // middleware's soft-mode behaviour (audit written, no enforce 403 of OUR doing).
        $this->postJson('/api/inventory/adjust', []);

        $this->assertAudited('security.mobile.authz_denied', $user, [
            'gate_type' => 'permission',
            'mode' => 'soft',
            'required' => 'inventory.manage_stock',
        ]);
    }

    public function test_permission_enforce_mode_returns_403(): void
    {
        config(['mobile.authz_enforce' => true]);
        $user = $this->userWithRole('support_agent', []);
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/inventory/adjust', [])
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->assertAudited('security.mobile.authz_denied', $user, ['mode' => 'enforce']);
    }

    public function test_permission_granted_passes_middleware(): void
    {
        config(['mobile.authz_enforce' => true]);
        $user = $this->userWithRole('buyer', ['inventory.manage_stock']);
        Sanctum::actingAs($user, ['*']);

        // Permission holder passes OUR middleware -> no deny audit. (The controller's
        // own FormRequest may still reject the empty body, which is not our concern.)
        $this->postJson('/api/inventory/adjust', []);

        $this->assertNotAudited('security.mobile.authz_denied', $user);
    }

    // ---- role-backed route -------------------------------------------------

    public function test_role_soft_mode_allows_but_audits(): void
    {
        config(['mobile.authz_enforce' => false]);
        $user = $this->userWithRole('employee', []); // not a fulfillment role
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/fulfillment/pick');

        $this->assertNotEquals(403, $response->status(), 'Soft mode must not 403');
        $this->assertAudited('security.mobile.authz_denied', $user, [
            'gate_type' => 'role',
            'mode' => 'soft',
        ]);
    }

    public function test_role_enforce_mode_returns_403(): void
    {
        config(['mobile.authz_enforce' => true]);
        $user = $this->userWithRole('employee', []);
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/fulfillment/pick')->assertStatus(403);
        $this->assertAudited('security.mobile.authz_denied', $user, ['mode' => 'enforce']);
    }

    public function test_role_granted_passes_middleware(): void
    {
        config(['mobile.authz_enforce' => true]);
        $user = $this->userWithRole('fulfillment_manager', []);
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/fulfillment/pick');

        $this->assertNotEquals(403, $response->status(), 'Fulfillment role must pass');
        $this->assertNotAudited('security.mobile.authz_denied', $user);
    }

    // ---- bypass ------------------------------------------------------------

    public function test_organization_owner_bypasses_both_gates(): void
    {
        config(['mobile.authz_enforce' => true]);
        $owner = $this->userWithRole('organization_owner', []);
        Sanctum::actingAs($owner, ['*']);

        $this->assertNotEquals(403, $this->postJson('/api/inventory/adjust', [])->status());
        $this->assertNotEquals(403, $this->getJson('/api/fulfillment/pick')->status());
        $this->assertNotAudited('security.mobile.authz_denied', $owner);
    }

    public function test_super_admin_bypasses_both_gates(): void
    {
        config(['mobile.authz_enforce' => true]);
        $admin = $this->userWithRole('super_admin', []);
        Sanctum::actingAs($admin, ['*']);

        $this->assertNotEquals(403, $this->postJson('/api/inventory/adjust', [])->status());
        $this->assertNotEquals(403, $this->getJson('/api/fulfillment/pick')->status());
        $this->assertNotAudited('security.mobile.authz_denied', $admin);
    }
}
