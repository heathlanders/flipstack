<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\BulkOperationResource;
use App\Jobs\Inventory\ProcessProductExport;
use App\Jobs\Inventory\ProcessProductImport;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryLocation;
use App\Models\System\BulkOperationJob;
use App\Services\Import\ProductImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportExportController extends Controller
{
    public function __construct(protected ProductImportService $importService) {}

    public function importIndex(): Response
    {
        $user = auth()->user();
        $orgId = $user->organization_id ?? $user->current_organization_id ?? 1;
        $imports = BulkOperationJob::where('organization_id', $orgId)->where('operation_type', 'import')->orderBy('created_at', 'desc')->limit(10)->get();

        $locations = InventoryLocation::where('organization_id', $orgId)->where('is_active', true)->select('id', 'name', 'code')->orderBy('name')->get();

        return Inertia::render('Inventory/Import/Index', ['recentImports' => BulkOperationResource::collection($imports), 'supportedFormats' => ['csv', 'xlsx'], 'maxFileSize' => '10MB', 'templateFields' => $this->getTemplateFields(), 'locations' => $locations]);
    }

    public function downloadTemplate(Request $request): StreamedResponse
    {
        $headers = ['sku', 'name', 'description', 'category', 'brand', 'condition', 'cost_price', 'selling_price', 'stock_quantity', 'barcode', 'status'];
        $example = ['SKU-001', 'Example Product', 'Description', 'Electronics', 'Brand', 'new', '10.00', '19.99', '100', '1234567890123', 'active'];

        return response()->streamDownload(function () use ($headers, $example) {
            $h = fopen('php://output', 'w');
            fprintf($h, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($h, $headers);
            fputcsv($h, $example);
            fclose($h);
        }, 'product_import_template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function uploadFile(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240']);
        $file = $request->file('file');
        $path = $file->store('imports/'.date('Y/m'), 'local');
        $validation = $this->importService->validateFile(storage_path('app/'.$path));
        $suggested = $this->importService->suggestMapping($validation['columns']);

        return response()->json(['success' => true, 'file_path' => $path, 'file_name' => $file->getClientOriginalName(), 'row_count' => $validation['row_count'], 'preview' => $validation['preview'], 'detected_columns' => $validation['columns'], 'suggested_mapping' => $suggested, 'requires_mapping' => $validation['requires_mapping']]);
    }

    public function validateImport(Request $request): JsonResponse
    {
        $user = auth()->user();
        $orgId = $user->organization_id ?? $user->current_organization_id ?? 1;
        $v = $request->validate(['file_path' => 'required|string', 'column_mapping' => 'required|array']);
        $fullPath = storage_path('app/'.$v['file_path']);
        if (! file_exists($fullPath)) {
            return response()->json(['message' => 'File not found'], 404);
        }
        $result = $this->importService->validateWithMapping($fullPath, $v['column_mapping'], $orgId);

        return response()->json(['success' => $result['valid'], 'total_rows' => $result['total_rows'], 'valid_rows' => $result['valid_rows'], 'error_rows' => $result['error_rows'], 'errors' => $result['errors'], 'warnings' => $result['warnings'], 'new_products' => $result['new_count'], 'existing_products' => $result['existing_count']]);
    }

    public function processImport(Request $request): JsonResponse
    {
        $user = auth()->user();
        $orgId = $user->organization_id ?? $user->current_organization_id ?? 1;
        $v = $request->validate(['file_path' => 'required|string', 'column_mapping' => 'required|array', 'update_existing' => 'boolean', 'skip_errors' => 'boolean', 'default_values' => 'nullable|array', 'location_id' => 'nullable|uuid|exists:inventory_locations,id']);
        $job = BulkOperationJob::create(['organization_id' => $orgId, 'user_id' => $user->id, 'operation_type' => 'import', 'status' => 'pending', 'total_items' => 0, 'file_path' => $v['file_path'], 'parameters' => $v]);
        ProcessProductImport::dispatch($job->id, $v['file_path'], $v['column_mapping'], $v['update_existing'] ?? false, $v['skip_errors'] ?? true, $v['default_values'] ?? [], $orgId, $user->id, $v['location_id'] ?? null);

        return response()->json(['success' => true, 'message' => 'Import started', 'job_id' => $job->id]);
    }

    public function exportIndex(): Response
    {
        $user = auth()->user();
        $orgId = $user->organization_id ?? $user->current_organization_id ?? 1;
        $exports = BulkOperationJob::where('organization_id', $orgId)->where('operation_type', 'export')->orderBy('created_at', 'desc')->limit(10)->get();

        return Inertia::render('Inventory/Export/Index', ['recentExports' => BulkOperationResource::collection($exports), 'availableFields' => $this->getExportableFields(), 'exportFormats' => ['csv', 'xlsx'], 'filterOptions' => ['categories' => Category::where('organization_id', $orgId)->get(['id', 'name']), 'brands' => Brand::where('organization_id', $orgId)->get(['id', 'name'])]]);
    }

    public function generateExport(Request $request): JsonResponse
    {
        $user = auth()->user();
        $orgId = $user->organization_id ?? $user->current_organization_id ?? 1;
        $v = $request->validate(['format' => 'required|in:csv,xlsx', 'fields' => 'required|array|min:1', 'filters' => 'nullable|array', 'product_ids' => 'nullable|array']);

        $query = Product::where('organization_id', $orgId);
        if (! empty($v['product_ids'])) {
            $query->whereIn('id', $v['product_ids']);
        }
        if (! empty($v['filters']['category_id'])) {
            $query->where('category_id', $v['filters']['category_id']);
        }
        if (! empty($v['filters']['status'])) {
            $query->where('status', $v['filters']['status']);
        }
        $count = $query->count();
        if ($count === 0) {
            return response()->json(['success' => false, 'message' => 'No products match criteria'], 422);
        }

        $job = BulkOperationJob::create(['organization_id' => $orgId, 'user_id' => $user->id, 'operation_type' => 'export', 'status' => 'pending', 'total_items' => $count, 'parameters' => $v]);
        ProcessProductExport::dispatch($job->id, $v['fields'], $v['format'], $v['filters'] ?? [], $v['product_ids'] ?? [], $orgId, $user->id);

        return response()->json(['success' => true, 'message' => 'Export started', 'job_id' => $job->id, 'total_items' => $count]);
    }

    public function downloadExport(BulkOperationJob $job): StreamedResponse|JsonResponse
    {
        $user = auth()->user();
        if ($job->organization_id !== ($user->organization_id ?? $user->current_organization_id ?? 1)) {
            return response()->json(['message' => 'Not found'], 404);
        }
        if ($job->status !== 'completed' || ! $job->result_file_path) {
            return response()->json(['message' => 'Export not ready'], 422);
        }
        $path = storage_path('app/'.$job->result_file_path);
        if (! file_exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return response()->download($path);
    }

    protected function getTemplateFields(): array
    {
        return [['field' => 'sku', 'required' => true, 'description' => 'Unique identifier'], ['field' => 'name', 'required' => true, 'description' => 'Product name'], ['field' => 'selling_price', 'required' => true, 'description' => 'Selling price'], ['field' => 'description', 'required' => false, 'description' => 'Full description'], ['field' => 'category', 'required' => false, 'description' => 'Category name'], ['field' => 'brand', 'required' => false, 'description' => 'Brand name'], ['field' => 'stock_quantity', 'required' => false, 'description' => 'Stock quantity'], ['field' => 'cost_price', 'required' => false, 'description' => 'Cost price'], ['field' => 'status', 'required' => false, 'description' => 'draft, active, inactive']];
    }

    protected function getExportableFields(): array
    {
        return [['field' => 'sku', 'label' => 'SKU', 'default' => true], ['field' => 'name', 'label' => 'Name', 'default' => true], ['field' => 'category.name', 'label' => 'Category', 'default' => true], ['field' => 'brand.name', 'label' => 'Brand', 'default' => true], ['field' => 'cost_price', 'label' => 'Cost Price', 'default' => true], ['field' => 'selling_price', 'label' => 'Selling Price', 'default' => true], ['field' => 'stock_quantity', 'label' => 'Stock', 'default' => true], ['field' => 'status', 'label' => 'Status', 'default' => true], ['field' => 'description', 'label' => 'Description', 'default' => false], ['field' => 'barcode', 'label' => 'Barcode', 'default' => false]];
    }
}
