<?php

namespace Tests\Feature\Supplier;

use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use App\Models\Supplier\SupplierOnboarding;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupplierOnboardingDocumentUploadTest extends TestCase
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
            'current_step' => 2,
            'started_at' => now(),
        ]);

        Storage::fake('private');
    }

    public function test_step2_uploads_documents_and_persists_paths(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('supplier.onboarding.step2'), [
            'documents' => [
                ['type' => 'business_license', 'file' => UploadedFile::fake()->create('license.pdf', 100, 'application/pdf')],
                ['type' => 'tax_documents', 'file' => UploadedFile::fake()->create('w9.pdf', 100, 'application/pdf')],
            ],
        ]);

        $response->assertRedirect(route('supplier.onboarding.index'));

        $this->onboarding->refresh();

        $this->assertNotEmpty($this->onboarding->step2_data['documents'] ?? []);
        $this->assertArrayHasKey('business_license', $this->onboarding->step2_data['documents']);
        $this->assertArrayHasKey('tax_documents', $this->onboarding->step2_data['documents']);

        $licensePath = $this->onboarding->step2_data['documents']['business_license']['path'];
        Storage::disk('private')->assertExists($licensePath);

        $this->assertTrue($this->onboarding->step2_documentation_complete);
        $this->assertNotNull($this->onboarding->step2_completed_at);
    }

    public function test_step2_with_single_document_does_not_advance_step(): void
    {
        $this->actingAs($this->user);

        $this->post(route('supplier.onboarding.step2'), [
            'documents' => [
                ['type' => 'business_license', 'file' => UploadedFile::fake()->create('license.pdf', 100, 'application/pdf')],
            ],
        ])->assertRedirect();

        $this->onboarding->refresh();

        $this->assertFalse((bool) $this->onboarding->step2_documentation_complete);
        $this->assertSame(2, $this->onboarding->current_step);
    }

    public function test_step2_rejects_invalid_file_type(): void
    {
        $this->actingAs($this->user);

        $this->post(route('supplier.onboarding.step2'), [
            'documents' => [
                ['type' => 'business_license', 'file' => UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream')],
            ],
        ])->assertSessionHasErrors('documents.0.file');
    }

    public function test_skip_docs_advances_step_without_uploads(): void
    {
        $this->actingAs($this->user);

        $this->post(route('supplier.onboarding.skip-docs'))->assertRedirect();

        $this->onboarding->refresh();

        $this->assertGreaterThan(2, $this->onboarding->current_step);
    }
}
