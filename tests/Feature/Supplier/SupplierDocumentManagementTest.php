<?php

namespace Tests\Feature\Supplier;

use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use App\Models\Supplier\SupplierOnboarding;
use App\Models\User\User;
use App\Services\Supplier\SupplierDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupplierDocumentManagementTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $organization;

    protected Supplier $supplier;

    protected User $supplierUser;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        $this->organization = Organization::factory()->create();
        $this->supplier = Supplier::factory()->create([
            'organization_id' => $this->organization->id,
            'supplier_status' => 'pending',
        ]);

        $this->supplierUser = User::factory()->create([
            'organization_id' => $this->organization->id,
            'is_supplier' => true,
            'supplier_id' => $this->supplier->id,
            'supplier_status' => 'active',
        ]);

        $this->adminUser = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        SupplierOnboarding::create([
            'supplier_id' => $this->supplier->id,
            'organization_id' => $this->organization->id,
            'status' => 'in_progress',
            'current_step' => 2,
            'started_at' => now(),
        ]);
    }

    public function test_supplier_can_upload_replace_and_download_own_document(): void
    {
        $this->actingAs($this->supplierUser);

        $file = UploadedFile::fake()->create('license.pdf', 100, 'application/pdf');

        $this->post(route('supplier.documents.store'), [
            'type' => 'business_license',
            'file' => $file,
        ])->assertRedirect(route('supplier.documents.index'));

        $onboarding = SupplierOnboarding::where('supplier_id', $this->supplier->id)->first();
        $this->assertArrayHasKey('business_license', $onboarding->step2_data['documents']);
        $firstPath = $onboarding->step2_data['documents']['business_license']['path'];
        Storage::disk('private')->assertExists($firstPath);

        $this->get(route('supplier.documents.download', ['type' => 'business_license']))
            ->assertOk();

        $replacement = UploadedFile::fake()->create('license_v2.pdf', 100, 'application/pdf');
        $this->post(route('supplier.documents.store'), [
            'type' => 'business_license',
            'file' => $replacement,
        ])->assertRedirect();

        Storage::disk('private')->assertMissing($firstPath);
    }

    public function test_admin_verify_flips_flag_and_status(): void
    {
        app(SupplierDocumentService::class)->store(
            $this->supplier,
            'business_license',
            UploadedFile::fake()->create('license.pdf', 100, 'application/pdf'),
        );

        $this->actingAs($this->adminUser);

        $this->patch(route('admin.suppliers.documents.verify', [
            'supplier' => $this->supplier->id,
            'type' => 'business_license',
        ]))->assertRedirect();

        $onboarding = SupplierOnboarding::where('supplier_id', $this->supplier->id)->first();
        $this->assertTrue((bool) $onboarding->business_license_verified);
        $this->assertSame('verified', $onboarding->step2_data['documents']['business_license']['status']);
    }

    public function test_admin_reject_stores_reason_and_clears_verified(): void
    {
        app(SupplierDocumentService::class)->store(
            $this->supplier,
            'tax_documents',
            UploadedFile::fake()->create('w9.pdf', 100, 'application/pdf'),
        );

        $this->actingAs($this->adminUser);

        $this->patch(route('admin.suppliers.documents.reject', [
            'supplier' => $this->supplier->id,
            'type' => 'tax_documents',
        ]), ['reason' => 'Form is unsigned'])->assertRedirect();

        $onboarding = SupplierOnboarding::where('supplier_id', $this->supplier->id)->first();
        $this->assertFalse((bool) $onboarding->tax_documents_verified);
        $this->assertSame('rejected', $onboarding->step2_data['documents']['tax_documents']['status']);
        $this->assertSame('Form is unsigned', $onboarding->step2_data['documents']['tax_documents']['rejection_reason']);
    }

    public function test_admin_reject_requires_reason(): void
    {
        app(SupplierDocumentService::class)->store(
            $this->supplier,
            'identity',
            UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'),
        );

        $this->actingAs($this->adminUser);

        $this->patch(route('admin.suppliers.documents.reject', [
            'supplier' => $this->supplier->id,
            'type' => 'identity',
        ]), [])->assertSessionHasErrors('reason');
    }

    public function test_re_upload_resets_status_to_pending(): void
    {
        $service = app(SupplierDocumentService::class);

        $service->store($this->supplier, 'insurance', UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf'));
        $service->verify($this->supplier, 'insurance', $this->adminUser);

        $this->actingAs($this->supplierUser);

        $this->post(route('supplier.documents.store'), [
            'type' => 'insurance',
            'file' => UploadedFile::fake()->create('cert_v2.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $onboarding = SupplierOnboarding::where('supplier_id', $this->supplier->id)->first();
        $this->assertFalse((bool) $onboarding->insurance_verified);
        $this->assertSame('pending', $onboarding->step2_data['documents']['insurance']['status']);
    }

    public function test_supplier_cannot_download_document_for_other_supplier(): void
    {
        $other = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        app(SupplierDocumentService::class)->store(
            $other,
            'business_license',
            UploadedFile::fake()->create('other.pdf', 100, 'application/pdf'),
        );

        $this->actingAs($this->supplierUser);

        $this->get(route('supplier.documents.download', ['type' => 'business_license']))
            ->assertNotFound();
    }
}
