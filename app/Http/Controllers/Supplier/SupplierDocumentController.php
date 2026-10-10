<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Supplier;
use App\Services\Supplier\SupplierDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SupplierDocumentController extends Controller
{
    public function __construct(protected SupplierDocumentService $documentService) {}

    public function index(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        if (! $supplier) {
            return redirect()->route('dashboard')->with('error', 'Supplier account not found.');
        }

        return Inertia::render('Supplier/Documents', [
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
            ],
            'documents' => $this->documentService->list($supplier),
            'document_types' => SupplierDocumentService::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|string|in:'.implode(',', array_keys(SupplierDocumentService::TYPES)),
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png',
        ]);

        $supplier = $this->getAuthenticatedSupplier();

        if (! $supplier) {
            return redirect()->route('dashboard')->with('error', 'Supplier account not found.');
        }

        $this->documentService->store($supplier, $request->input('type'), $request->file('file'));

        return redirect()->route('supplier.documents.index')
            ->with('success', SupplierDocumentService::TYPES[$request->input('type')].' uploaded.');
    }

    public function download(Request $request, string $type)
    {
        $supplier = $this->getAuthenticatedSupplier();

        if (! $supplier) {
            abort(403);
        }

        $resolved = $this->documentService->pathFor($supplier, $type);

        if (! $resolved) {
            abort(404, 'Document not found.');
        }

        return Storage::disk(SupplierDocumentService::DISK)
            ->download($resolved['path'], $resolved['original_name']);
    }

    protected function getAuthenticatedSupplier(): ?Supplier
    {
        $user = Auth::user();

        if ($user?->supplier_id) {
            return Supplier::find($user->supplier_id);
        }

        return Supplier::where('email', $user?->email)
            ->orWhere('user_id', $user?->id)
            ->first();
    }
}
