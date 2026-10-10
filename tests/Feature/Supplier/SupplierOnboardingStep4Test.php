<?php

namespace Tests\Feature\Supplier;

use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use App\Models\Supplier\SupplierOnboarding;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class SupplierOnboardingStep4Test extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected User $user;

    protected SupplierOnboarding $onboarding;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::factory()->create();
        $supplier = Supplier::factory()->create([
            'organization_id' => $org->id,
            'supplier_status' => 'pending',
        ]);
        $this->user = User::factory()->create([
            'organization_id' => $org->id,
            'is_supplier' => true,
            'supplier_id' => $supplier->id,
            'supplier_status' => 'active',
        ]);
        $this->onboarding = SupplierOnboarding::create([
            'supplier_id' => $supplier->id,
            'organization_id' => $org->id,
            'status' => 'in_progress',
            'current_step' => 4,
            'started_at' => now(),
        ]);
    }

    public function test_step4_completes_without_tutorial_checkbox(): void
    {
        $this->actingAs($this->user);

        $this->post(route('supplier.onboarding.step4'), [
            'notification_preferences' => ['email' => true, 'sms' => false, 'push' => true],
        ])->assertRedirect(route('supplier.onboarding.index'));

        $this->onboarding->refresh();
        $this->assertTrue((bool) $this->onboarding->step4_portal_complete);
        $this->assertTrue((bool) $this->onboarding->tutorial_completed);
        $this->assertTrue((bool) $this->onboarding->portal_credentials_sent);
    }

    public function test_step4_still_requires_notification_preferences(): void
    {
        $this->actingAs($this->user);

        $this->post(route('supplier.onboarding.step4'), [])
            ->assertSessionHasErrors('notification_preferences');
    }
}
