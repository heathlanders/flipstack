<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Supplier;
use App\Models\Supplier\CommissionStructure;
use App\Models\Supplier\SupplierOnboarding;
use App\Models\Supplier\SupplierTier;
use App\Services\Supplier\SupplierUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SupplierOnboardingController extends Controller
{
    public function __construct(protected SupplierUploadService $uploadService) {}

    /**
     * Display the onboarding wizard
     */
    public function index(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        if (! $supplier) {
            return redirect()->route('dashboard')
                ->with('error', 'Supplier account not found.');
        }

        // Get or create onboarding record
        $onboarding = SupplierOnboarding::firstOrCreate(
            ['supplier_id' => $supplier->id],
            [
                'organization_id' => $supplier->organization_id,
                'status' => 'not_started',
                'current_step' => 1,
                'completion_percentage' => 0,
            ]
        );

        // If onboarding is complete, redirect to dashboard
        if ($onboarding->status === 'completed') {
            return redirect()->route('supplier.dashboard')
                ->with('success', 'Onboarding already completed.');
        }

        // Start onboarding if not started
        if ($onboarding->status === 'not_started') {
            $onboarding->start();
        }

        // Get available commission structures for step 3
        $commissionStructures = CommissionStructure::forOrganization($supplier->organization_id)
            ->active()
            ->get(['id', 'name', 'type', 'base_percentage', 'description']);

        // Get tiers for reference
        $tiers = SupplierTier::where('organization_id', $supplier->organization_id)
            ->active()
            ->ordered()
            ->get(['id', 'tier_name', 'tier_code', 'min_performance_score', 'max_performance_score', 'benefits']);

        return Inertia::render('Supplier/Onboarding', [
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'email' => $supplier->email,
                'company_name' => $supplier->company_name,
                'phone' => $supplier->phone,
            ],
            'onboarding' => [
                'id' => $onboarding->id,
                'status' => $onboarding->status,
                'current_step' => $onboarding->current_step,
                'completion_percentage' => $onboarding->completion_percentage,
                'steps' => $onboarding->progressSteps,
                'step_data' => [
                    'step1' => $onboarding->step1_data,
                    'step2' => $onboarding->step2_data,
                    'step3' => $onboarding->step3_data,
                    'step4' => $onboarding->step4_data,
                    'step5' => $onboarding->step5_data,
                ],
                'documents' => [
                    'business_license_verified' => $onboarding->business_license_verified,
                    'tax_documents_verified' => $onboarding->tax_documents_verified,
                    'insurance_verified' => $onboarding->insurance_verified,
                    'identity_verified' => $onboarding->identity_verified,
                ],
            ],
            'commissionStructures' => $commissionStructures,
            'tiers' => $tiers,
        ]);
    }

    /**
     * Complete Step 1: Application
     */
    public function completeStep1(Request $request)
    {
        $request->validate([
            'business_name' => 'required|string|max:255',
            'business_type' => 'required|string|in:individual,llc,corporation,partnership',
            'tax_id' => 'nullable|string|max:50',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'zip_code' => 'required|string|max:20',
            'country' => 'required|string|max:100',
            'website' => 'nullable|url|max:255',
            'product_categories' => 'nullable|array',
            'average_monthly_items' => 'nullable|integer|min:0',
            'agree_to_terms' => 'required|accepted',
        ]);

        $supplier = $this->getAuthenticatedSupplier();
        $onboarding = SupplierOnboarding::where('supplier_id', $supplier->id)->firstOrFail();

        // Update supplier info
        $supplier->update([
            'company_name' => $request->business_name,
            'business_type' => $request->business_type,
            'tax_id' => $request->tax_id,
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'zip_code' => $request->zip_code,
            'country' => $request->country,
            'website' => $request->website,
        ]);

        // Complete step
        $onboarding->completeStep(1, [
            'business_name' => $request->business_name,
            'business_type' => $request->business_type,
            'product_categories' => $request->product_categories,
            'average_monthly_items' => $request->average_monthly_items,
            'terms_agreed_at' => now()->toIso8601String(),
        ]);

        return redirect()->route('supplier.onboarding.index')
            ->with('success', 'Application information saved successfully.');
    }

    /**
     * Complete Step 2: Documentation
     */
    public function completeStep2(Request $request)
    {
        $request->validate([
            'documents' => 'required|array',
            'documents.*.type' => 'required|string|in:business_license,tax_documents,insurance,identity',
            'documents.*.file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png',
        ]);

        $supplier = $this->getAuthenticatedSupplier();
        $onboarding = SupplierOnboarding::where('supplier_id', $supplier->id)->firstOrFail();

        $uploadedDocs = [];

        foreach ($request->file('documents') as $index => $docData) {
            $type = $request->input("documents.{$index}.type");
            $file = $docData['file'];

            // Store the file
            $path = $file->store("suppliers/{$supplier->id}/documents", 'private');

            $uploadedDocs[$type] = [
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'uploaded_at' => now()->toIso8601String(),
            ];
        }

        // Update step data
        $onboarding->update([
            'step2_data' => array_merge($onboarding->step2_data ?? [], ['documents' => $uploadedDocs]),
        ]);

        // Mark documents as uploaded (verification will be done by admin)
        $verificationFields = [];
        foreach ($uploadedDocs as $type => $doc) {
            $fieldName = "{$type}_verified";
            // For now, auto-verify (in production, this would be admin-reviewed)
            $verificationFields[$fieldName] = false;
        }
        $onboarding->update($verificationFields);

        // If all 4 documents are uploaded, complete step
        $requiredDocs = ['business_license', 'tax_documents', 'insurance', 'identity'];
        $allUploaded = count(array_intersect_key($uploadedDocs, array_flip($requiredDocs))) >= 2; // At least 2 docs

        if ($allUploaded) {
            $onboarding->update([
                'step2_documentation_complete' => true,
                'step2_completed_at' => now(),
            ]);
            $onboarding->calculateProgress();
            $onboarding->advanceToNextStep();
        }

        return redirect()->route('supplier.onboarding.index')
            ->with('success', 'Documents uploaded successfully. Verification is pending.');
    }

    /**
     * Complete Step 3: Commission Setup
     */
    public function completeStep3(Request $request)
    {
        $request->validate([
            'commission_structure_id' => 'required|uuid|exists:commission_structures,id',
            'settlement_preference' => 'required|string|in:weekly,bi_weekly,monthly',
            'payment_method' => 'required|string|in:bank_transfer,paypal,check',
            'bank_details' => 'required_if:payment_method,bank_transfer|array',
            'bank_details.account_name' => 'required_if:payment_method,bank_transfer|string|max:255',
            'bank_details.routing_number' => 'required_if:payment_method,bank_transfer|string|max:50',
            'bank_details.account_number' => 'required_if:payment_method,bank_transfer|string|max:50',
            'paypal_email' => 'required_if:payment_method,paypal|email',
        ]);

        $supplier = $this->getAuthenticatedSupplier();
        $onboarding = SupplierOnboarding::where('supplier_id', $supplier->id)->firstOrFail();

        // Verify commission structure belongs to organization
        $commissionStructure = CommissionStructure::where('id', $request->commission_structure_id)
            ->where('organization_id', $supplier->organization_id)
            ->firstOrFail();

        // Update supplier settings
        $supplier->update([
            'commission_structure_id' => $commissionStructure->id,
            'settlement_schedule' => $request->settlement_preference,
            'payment_method' => $request->payment_method,
        ]);

        // Store commission/payment data
        $stepData = [
            'commission_structure_id' => $commissionStructure->id,
            'commission_structure_name' => $commissionStructure->name,
            'settlement_preference' => $request->settlement_preference,
            'payment_method' => $request->payment_method,
        ];

        // Store payment details securely (in production, use encryption)
        if ($request->payment_method === 'bank_transfer') {
            $stepData['bank_details_provided'] = true;
            // In production, store encrypted bank details
        } elseif ($request->payment_method === 'paypal') {
            $stepData['paypal_email'] = $request->paypal_email;
        }

        $onboarding->update([
            'assigned_commission_structure_id' => $commissionStructure->id,
            'settlement_preference' => $request->settlement_preference,
        ]);

        $onboarding->completeStep(3, $stepData);

        return redirect()->route('supplier.onboarding.index')
            ->with('success', 'Commission setup completed successfully.');
    }

    /**
     * Complete Step 4: Portal Access
     */
    public function completeStep4(Request $request)
    {
        $request->validate([
            'notification_preferences' => 'required|array',
            'notification_preferences.email' => 'boolean',
            'notification_preferences.sms' => 'boolean',
            'notification_preferences.push' => 'boolean',
        ]);

        $supplier = $this->getAuthenticatedSupplier();
        $onboarding = SupplierOnboarding::where('supplier_id', $supplier->id)->firstOrFail();

        $onboarding->update([
            'portal_credentials_sent' => true,
            'tutorial_completed' => true,
        ]);

        $onboarding->completeStep(4, [
            'notification_preferences' => $request->notification_preferences,
            'portal_accessed_at' => now()->toIso8601String(),
        ]);

        return redirect()->route('supplier.onboarding.index')
            ->with('success', 'Portal setup completed successfully.');
    }

    /**
     * Complete Step 5: First Submission
     */
    public function completeStep5(Request $request)
    {
        $request->validate([
            'submission_type' => 'required|string|in:sample_product,acknowledge',
            'product_data' => 'required_if:submission_type,sample_product|array',
            'product_data.name' => 'required_if:submission_type,sample_product|string|max:255',
            'product_data.sku' => 'nullable|string|max:100',
            'product_data.barcode' => 'nullable|string|max:50',
            'product_data.cost_price' => 'nullable|numeric|min:0|max:999999.99',
            'product_data.description' => 'nullable|string|max:5000',
        ]);

        $supplier = $this->getAuthenticatedSupplier();
        $onboarding = SupplierOnboarding::where('supplier_id', $supplier->id)->firstOrFail();

        $stepData = [
            'submission_type' => $request->submission_type,
            'completed_at' => now()->toIso8601String(),
        ];

        if ($request->submission_type === 'sample_product' && $request->product_data) {
            $submission = $this->uploadService->submit(
                array_merge($request->product_data, ['images' => []]),
                $supplier->id,
                $supplier->organization_id,
            );

            $stepData['sample_product_submission_id'] = $submission->id;
            $onboarding->update(['first_submission_id' => $submission->id]);
        }

        $onboarding->completeStep(5, $stepData);
        $onboarding->complete();

        return redirect()->route('supplier.dashboard')
            ->with('success', 'Congratulations! Your onboarding is complete. Welcome to the platform!');
    }

    /**
     * Skip documentation verification (for demo purposes)
     */
    public function skipDocumentation(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();
        $onboarding = SupplierOnboarding::where('supplier_id', $supplier->id)->firstOrFail();

        // Mark step 2 as complete without full verification
        $onboarding->update([
            'step2_documentation_complete' => true,
            'step2_completed_at' => now(),
            'step2_data' => array_merge($onboarding->step2_data ?? [], [
                'skipped' => true,
                'skipped_at' => now()->toIso8601String(),
            ]),
        ]);

        $onboarding->calculateProgress();
        $onboarding->advanceToNextStep();

        return redirect()->route('supplier.onboarding.index')
            ->with('success', 'Documentation step skipped. You can upload documents later.');
    }

    /**
     * Get current onboarding status
     */
    public function status(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        if (! $supplier) {
            return response()->json(['error' => 'Supplier not found'], 404);
        }

        $onboarding = SupplierOnboarding::where('supplier_id', $supplier->id)->first();

        if (! $onboarding) {
            return response()->json([
                'status' => 'not_started',
                'current_step' => 1,
                'completion_percentage' => 0,
            ]);
        }

        return response()->json([
            'status' => $onboarding->status,
            'current_step' => $onboarding->current_step,
            'completion_percentage' => $onboarding->completion_percentage,
            'steps' => $onboarding->progressSteps,
        ]);
    }

    /**
     * Get the authenticated supplier
     */
    protected function getAuthenticatedSupplier(): ?Supplier
    {
        $user = Auth::user();

        if ($user->supplier_id) {
            return Supplier::find($user->supplier_id);
        }

        return Supplier::where('email', $user->email)
            ->orWhere('user_id', $user->id)
            ->first();
    }
}
