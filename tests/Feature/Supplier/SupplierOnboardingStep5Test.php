<?php

namespace Tests\Feature\Supplier;

use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use App\Models\Supplier\SupplierOnboarding;
use App\Models\Supplier\SupplierProductSubmission;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class SupplierOnboardingStep5Test extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $organization;

    protected Supplier $supplier;

    protected User $user;

    protected SupplierOnboarding $onboarding;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->supplier = Supplier::factory()->create([
            'organization_id' => $this->organization->id,
            'supplier_status' => 'pending',
            'onboarding_complete' => false,
        ]);
        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'is_supplier' => true,
            'supplier_id' => $this->supplier->id,
            'supplier_status' => 'active',
        ]);
        $this->onboarding = SupplierOnboarding::create([
            'supplier_id' => $this->supplier->id,
            'organization_id' => $this->organization->id,
            'status' => 'in_progress',
            'current_step' => 5,
            'started_at' => now(),
        ]);
    }

    public function test_acknowledge_completes_onboarding_without_creating_submission(): void
    {
        $this->actingAs($this->user);

        $this->post(route('supplier.onboarding.step5'), [
            'submission_type' => 'acknowledge',
        ])->assertRedirect(route('supplier.dashboard'));

        $this->assertSame(0, SupplierProductSubmission::where('supplier_id', $this->supplier->id)->count());

        $this->onboarding->refresh();
        $this->supplier->refresh();
        $this->assertSame('completed', $this->onboarding->status);
        $this->assertTrue((bool) $this->supplier->onboarding_complete);
    }

    public function test_sample_product_creates_real_submission_and_links_it(): void
    {
        $this->actingAs($this->user);

        $this->post(route('supplier.onboarding.step5'), [
            'submission_type' => 'sample_product',
            'product_data' => [
                'name' => 'Vintage Watch',
                'sku' => 'VW-001',
                'cost_price' => 49.99,
                'description' => 'A nice piece.',
            ],
        ])->assertRedirect(route('supplier.dashboard'));

        $submission = SupplierProductSubmission::where('supplier_id', $this->supplier->id)->first();
        $this->assertNotNull($submission);
        $this->assertSame('Vintage Watch', $submission->submitted_data['name']);
        $this->assertSame('VW-001', $submission->submitted_data['sku']);
        $this->assertSame(SupplierProductSubmission::STATUS_PENDING_REVIEW, $submission->status);

        $this->onboarding->refresh();
        $this->assertSame($submission->id, $this->onboarding->first_submission_id);
        $this->assertSame('completed', $this->onboarding->status);
    }

    public function test_sample_product_requires_name(): void
    {
        $this->actingAs($this->user);

        $this->post(route('supplier.onboarding.step5'), [
            'submission_type' => 'sample_product',
            'product_data' => ['sku' => 'NO-NAME'],
        ])->assertSessionHasErrors('product_data.name');

        $this->assertSame(0, SupplierProductSubmission::where('supplier_id', $this->supplier->id)->count());
    }
}
