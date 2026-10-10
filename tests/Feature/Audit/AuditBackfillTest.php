<?php

namespace Tests\Feature\Audit;

use App\Models\Organization\Organization;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderItem;
use App\Models\User\User;
use App\Services\Procurement\PurchaseOrderService;
use App\Support\AuditContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\Support\AssertsAudits;
use Tests\TestCase;

class AuditBackfillTest extends TestCase
{
    use AssertsAudits;
    use RefreshDatabase;
    use WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();
        AuditContext::reset();
    }

    public function test_purchase_order_submit_emits_po_submitted_audit(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);
        $this->actingAs($user);

        $po = PurchaseOrder::factory()->create([
            'organization_id' => $org->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $po->id]);

        app(PurchaseOrderService::class)->submit($po);

        $this->assertAudited('po.submitted', $user, [
            'po_number' => $po->po_number,
            'source' => 'web',
        ]);
    }

    public function test_login_emits_auth_login_security_audit(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $org->id,
            'password' => bcrypt('secret-pass-123'),
            'is_active' => true,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-pass-123',
        ])->assertRedirect();

        $row = $this->assertAudited('auth.login', $user);
        $this->assertSame('security', $row->category);
    }

    public function test_failed_login_emits_failed_login_security_audit(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $org->id,
            'password' => bcrypt('right-password'),
            'is_active' => true,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $row = \App\Models\User\UserActivityLog::where('action', 'auth.failed_login')->latest('id')->first();
        $this->assertNotNull($row, 'Expected auth.failed_login row');
        $this->assertSame('security', $row->category);
        $this->assertSame('warning', $row->severity);
        $this->assertSame($user->email, $row->metadata['email']);
    }

    public function test_product_create_via_trait_emits_audit_via_observer(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);
        $this->actingAs($user);

        \App\Models\Catalog\Product::create([
            'organization_id' => $org->id,
            'name' => 'Trait Smoke Product',
            'sku' => 'TRAIT-SMOKE-1',
            'status' => 'active',
        ]);

        $this->assertAudited('product.created', $user);
    }
}
