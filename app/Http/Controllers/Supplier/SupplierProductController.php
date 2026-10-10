<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Supplier;
use App\Models\Supplier\SupplierProductSubmission;
use App\Services\Supplier\SupplierUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SupplierProductController extends Controller
{
    public function __construct(protected SupplierUploadService $uploadService)
    {
        $this->middleware(['auth']);
    }

    /**
     * Show the product submission form.
     */
    public function submit(Request $request)
    {
        return Inertia::render('Supplier/Products/Submit');
    }

    /**
     * Store a new product submission.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'barcode' => 'nullable|string|max:50',
            'cost_price' => 'nullable|numeric|min:0|max:999999.99',
            'description' => 'nullable|string|max:5000',
            'images' => 'nullable|array|max:5',
            'images.*' => 'file|image|max:5120',
        ]);

        $supplier = $this->getAuthenticatedSupplier();

        if (! $supplier) {
            return redirect()->route('dashboard')
                ->with('error', 'Supplier account not found.');
        }

        $organizationId = $supplier->organization_id;

        // Handle image uploads
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imagePaths[] = $image->store('supplier-uploads/'.$supplier->id, 'public');
            }
        }

        $submittedData = [
            'name' => $validated['name'],
            'sku' => $validated['sku'] ?? null,
            'barcode' => $validated['barcode'] ?? null,
            'cost_price' => $validated['cost_price'] ?? null,
            'description' => $validated['description'] ?? null,
            'images' => $imagePaths,
        ];

        $this->uploadService->submit($submittedData, $supplier->id, $organizationId);

        return redirect()->route('supplier.products.submissions')
            ->with('success', 'Product submitted for review.');
    }

    /**
     * List the supplier's own submissions.
     */
    public function submissions(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        if (! $supplier) {
            return redirect()->route('dashboard')
                ->with('error', 'Supplier account not found.');
        }

        $query = SupplierProductSubmission::where('supplier_id', $supplier->id)
            ->orderByDesc('created_at');

        if ($request->filled('status') && in_array($request->input('status'), ['pending_review', 'approved', 'rejected'])) {
            $query->where('status', $request->input('status'));
        }

        $submissions = $query->paginate(20)->withQueryString();

        return Inertia::render('Supplier/Products/Submissions', [
            'submissions' => $submissions,
            'filters' => [
                'status' => $request->input('status', ''),
            ],
        ]);
    }

    /**
     * Get the authenticated supplier.
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
