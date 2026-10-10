<?php

namespace Tests\Feature\Audit;

use App\Models\Organization\Organization;
use App\Models\User\Role;
use App\Models\User\User;
use App\Models\User\UserActivityLog;
use App\Support\AuditContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class AuditHubControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $org;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        AuditContext::reset();

        $this->org = Organization::factory()->create();

        // Manager role -- needed to pass the AuditHubController::authorizeAdmin gate
        $role = Role::firstOrCreate(['slug' => 'organization_owner'], [
            'name' => 'Organization Owner',
            'description' => 'Owner of organization',
            'permissions' => [],
        ]);

        $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->admin->roles()->attach($role->id, ['organization_id' => $this->org->id]);
    }

    public function test_index_renders_with_paginated_rows(): void
    {
        $this->seedAuditRows(3);

        $response = $this->actingAs($this->admin)->get(route('admin.audit-hub.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/AuditHub/Index')
            ->where('rows.total', 3)
            ->has('facets.users')
            ->has('facets.severities')
        );
    }

    public function test_filter_by_action_returns_only_matching_rows(): void
    {
        $this->seedAuditRow('product.created');
        $this->seedAuditRow('order.shipped');
        $this->seedAuditRow('order.cancelled');

        $response = $this->actingAs($this->admin)->get(route('admin.audit-hub.index', ['action' => 'order.']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('rows.total', 2));
    }

    public function test_entity_timeline_returns_audit_rows_for_subject(): void
    {
        $this->seedAuditRow('product.created', metadata: [], modelType: 'App\\Models\\Catalog\\Product', modelId: 'product-uuid-1');
        $this->seedAuditRow('product.updated', metadata: ['changes' => ['name' => ['old' => 'A', 'new' => 'B']]], modelType: 'App\\Models\\Catalog\\Product', modelId: 'product-uuid-1');
        $this->seedAuditRow('order.shipped', metadata: [], modelType: 'App\\Models\\Fulfillment\\Order', modelId: 'order-1');

        $response = $this->actingAs($this->admin)->get(route('admin.audit-hub.entity', [
            'type' => 'App\\Models\\Catalog\\Product',
            'id' => 'product-uuid-1',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/AuditHub/EntityTimeline')
            ->where('modelType', 'App\\Models\\Catalog\\Product')
            ->where('modelId', 'product-uuid-1')
            ->has('rows', 2)
        );
    }

    public function test_security_feed_returns_only_security_category(): void
    {
        $this->seedAuditRow('auth.login', category: 'security', severity: 'info');
        $this->seedAuditRow('auth.failed_login', category: 'security', severity: 'warning');
        $this->seedAuditRow('order.shipped', category: 'fulfillment');

        $response = $this->actingAs($this->admin)->get(route('admin.audit-hub.security'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/AuditHub/Security')
            ->where('rows.total', 2)
            ->has('failed_by_day')
        );
    }

    public function test_export_streams_csv(): void
    {
        $this->seedAuditRow('product.created');

        $response = $this->actingAs($this->admin)->get(route('admin.audit-hub.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('product.created', $response->streamedContent());
    }

    public function test_non_admin_gets_403(): void
    {
        $user = User::factory()->create(['organization_id' => $this->org->id]);

        $this->actingAs($user)
            ->get(route('admin.audit-hub.index'))
            ->assertForbidden();
    }

    protected function seedAuditRows(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->seedAuditRow('test.row');
        }
    }

    protected function seedAuditRow(string $action, array $metadata = [], string $category = 'general', string $severity = 'info', ?string $modelType = null, ?string $modelId = null): void
    {
        UserActivityLog::create([
            'user_id' => $this->admin->id,
            'organization_id' => $this->org->id,
            'team_id' => null,
            'action' => $action,
            'description' => "Test {$action}",
            'metadata' => $metadata,
            'severity' => $severity,
            'category' => $category,
            'model_type' => $modelType,
            'model_id' => $modelId,
        ]);
    }
}
