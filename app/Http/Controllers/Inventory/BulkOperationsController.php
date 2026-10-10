<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\BulkOperationResource;
use App\Jobs\Inventory\BulkCategoryUpdate;
use App\Jobs\Inventory\BulkLocationTransfer;
use App\Jobs\Inventory\BulkProductUpdate;
use App\Jobs\Inventory\BulkStockAdjustment;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\System\BulkOperationJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class BulkOperationsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = auth()->user();
        $orgId = $user->organization_id ?? $user->current_organization_id ?? 1;

        $jobs = BulkOperationJob::where('organization_id', $orgId)
            ->where('user_id', $user->id)->orderBy('created_at', 'desc')->limit(10)->get();

        return Inertia::render('Inventory/BulkOperations/Index', [
            'recentJobs' => BulkOperationResource::collection($jobs),
            'filterOptions' => [
                'categories' => Category::where('organization_id', $orgId)->orderBy('name')->get(['id', 'name']),
                'brands' => Brand::where('organization_id', $orgId)->orderBy('name')->get(['id', 'name']),
                'statuses' => [['value' => 'draft', 'label' => 'Draft'], ['value' => 'active', 'label' => 'Active'], ['value' => 'inactive', 'label' => 'Inactive'], ['value' => 'archived', 'label' => 'Archived']],
            ],
            'permissions' => [
                'can_delete' => ! method_exists($user, 'hasPermission') || $user->hasPermission('inventory.delete'),
                'can_import' => ! method_exists($user, 'hasPermission') || $user->hasPermission('inventory.import'),
                'can_export' => ! method_exists($user, 'hasPermission') || $user->hasPermission('inventory.export'),
            ],
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $user = auth()->user();
        $orgId = $user->organization_id ?? $user->current_organization_id ?? 1;
        $v = $request->validate(['product_ids' => 'required|array|min:1|max:1000', 'operation' => 'required|string', 'data' => 'required|array']);

        $products = Product::whereIn('id', $v['product_ids'])->where('organization_id', $orgId)->with(['category:id,name', 'brand:id,name'])->get();
        if ($products->count() !== count($v['product_ids'])) {
            return response()->json(['success' => false, 'message' => 'Some products not found'], 403);
        }

        $preview = ['operation' => $v['operation'], 'affected_products' => []];
        foreach ($products->take(10) as $p) {
            $changes = match ($v['operation']) {
                'update_status' => ['status' => ['from' => $p->status, 'to' => $v['data']['status'] ?? '']],
                'update_category' => ['category' => ['from' => $p->category?->name ?? 'None', 'to' => Category::find($v['data']['category_id'] ?? 0)?->name ?? '']],
                'adjust_stock' => ['stock' => ['from' => $p->stock_quantity, 'to' => $p->stock_quantity + (int) ($v['data']['adjustment'] ?? 0)]],
                'delete' => ['action' => 'Will be deleted'],
                default => [],
            };
            $preview['affected_products'][] = ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'changes' => $changes];
        }

        return response()->json(['success' => true, 'preview' => $preview, 'affected_count' => $products->count()]);
    }

    public function execute(Request $request): JsonResponse
    {
        $user = auth()->user();
        $orgId = $user->organization_id ?? $user->current_organization_id ?? 1;
        $v = $request->validate(['product_ids' => 'required|array|min:1', 'operation' => 'required|string', 'data' => 'required|array', 'batch_size' => 'nullable|integer|min:10|max:500']);

        if ($v['operation'] === 'delete' && method_exists($user, 'hasPermission') && ! $user->hasPermission('inventory.delete')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validIds = Product::whereIn('id', $v['product_ids'])->where('organization_id', $orgId)->pluck('id')->toArray();
        if (count($validIds) !== count($v['product_ids'])) {
            return response()->json(['success' => false, 'message' => 'Some products not found'], 403);
        }

        $opType = match ($v['operation']) {
            'adjust_stock' => 'stock_adjustment', 'delete' => 'bulk_delete', default => 'bulk_update'
        };
        $job = BulkOperationJob::create(['organization_id' => $orgId, 'user_id' => $user->id, 'operation_type' => $opType, 'status' => 'pending', 'total_items' => count($validIds), 'parameters' => ['product_ids' => $validIds, 'operation' => $v['operation'], 'data' => $v['data'], 'batch_size' => $v['batch_size'] ?? 100]]);

        $jobClass = match ($v['operation']) {
            'adjust_stock' => BulkStockAdjustment::class, 'update_category' => BulkCategoryUpdate::class, 'transfer_location' => BulkLocationTransfer::class, default => BulkProductUpdate::class
        };
        $jobClass::dispatch($job->id, $validIds, $v['data'], $orgId, $user->id);

        return response()->json(['success' => true, 'message' => 'Bulk operation started', 'job_id' => $job->id, 'total_items' => count($validIds)]);
    }

    public function getJobProgress(BulkOperationJob $job): JsonResponse
    {
        $user = auth()->user();
        $orgId = $user->organization_id ?? $user->current_organization_id ?? 1;
        if ($job->organization_id !== $orgId) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json(['id' => $job->id, 'status' => $job->status, 'total_items' => $job->total_items, 'processed_items' => $job->processed_items, 'failed_items' => $job->failed_items, 'progress_percent' => $job->progress_percent, 'is_cancellable' => $job->isCancellable()]);
    }

    public function getJobErrors(BulkOperationJob $job): JsonResponse
    {
        $user = auth()->user();
        if ($job->organization_id !== ($user->organization_id ?? $user->current_organization_id ?? 1)) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json(['errors' => $job->errors ?? [], 'failed_count' => $job->failed_items]);
    }

    public function cancelJob(BulkOperationJob $job): JsonResponse
    {
        $user = auth()->user();
        if ($job->organization_id !== ($user->organization_id ?? $user->current_organization_id ?? 1)) {
            return response()->json(['message' => 'Not found'], 404);
        }
        if (! $job->isCancellable()) {
            return response()->json(['message' => 'Cannot cancel'], 422);
        }
        Cache::put("bulk_job_cancelled_{$job->id}", true, 3600);

        return response()->json(['success' => true, 'message' => 'Cancellation requested']);
    }

    public function retryJob(BulkOperationJob $job): JsonResponse
    {
        $user = auth()->user();
        $orgId = $user->organization_id ?? $user->current_organization_id ?? 1;
        if ($job->organization_id !== $orgId) {
            return response()->json(['message' => 'Not found'], 404);
        }
        if (! $job->isRetryable()) {
            return response()->json(['message' => 'Cannot retry'], 422);
        }

        $failedIds = collect($job->errors ?? [])->pluck('product_id')->filter()->unique()->values()->toArray();
        if (empty($failedIds)) {
            return response()->json(['message' => 'No failed items'], 422);
        }

        $params = $job->parameters;
        $retryJob = BulkOperationJob::create(['organization_id' => $orgId, 'user_id' => $user->id, 'operation_type' => $job->operation_type, 'status' => 'pending', 'total_items' => count($failedIds), 'parameters' => array_merge($params, ['product_ids' => $failedIds, 'is_retry' => true])]);

        $jobClass = match ($params['operation'] ?? '') {
            'adjust_stock' => BulkStockAdjustment::class, 'update_category' => BulkCategoryUpdate::class, default => BulkProductUpdate::class
        };
        $jobClass::dispatch($retryJob->id, $failedIds, $params['data'] ?? [], $orgId, $user->id);

        return response()->json(['success' => true, 'job_id' => $retryJob->id, 'retry_count' => count($failedIds)]);
    }

    public function jobs(Request $request): Response|JsonResponse
    {
        $user = auth()->user();
        $orgId = $user->organization_id ?? $user->current_organization_id ?? 1;
        $jobs = BulkOperationJob::where('organization_id', $orgId)->when($request->get('user_only', true), fn ($q) => $q->where('user_id', $user->id))->when($request->get('status'), fn ($q, $s) => $q->where('status', $s))->orderBy('created_at', 'desc')->paginate(20);

        return $request->wantsJson() ? response()->json(BulkOperationResource::collection($jobs)) : Inertia::render('Inventory/BulkOperations/Jobs', ['jobs' => BulkOperationResource::collection($jobs)]);
    }

    public function showJob(BulkOperationJob $job): Response|JsonResponse
    {
        $user = auth()->user();
        if ($job->organization_id !== ($user->organization_id ?? $user->current_organization_id ?? 1)) {
            abort(404);
        }

        return request()->wantsJson() ? response()->json(new BulkOperationResource($job)) : Inertia::render('Inventory/BulkOperations/Show', ['job' => new BulkOperationResource($job)]);
    }
}
