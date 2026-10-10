<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsImageUrls;
use App\Http\Controllers\Api\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductIntakeQueue;
use App\Models\Fulfillment\Order;
use App\Models\Inventory\LabelingWorkOrder;
use App\Models\Inventory\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    use FormatsImageUrls;
    use ResolvesOrganization;

    /**
     * Omni-search across products, suppliers, and orders.
     */
    public function omniSearch(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:255',
            'type' => 'nullable|string|in:product,supplier,order,all',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $user = Auth::user();
        $organizationId = $this->resolveOrganizationId($user);
        $query = $request->input('q');
        $type = $request->input('type', 'all');
        $limit = $request->input('limit', 10);

        $results = [
            'products' => [],
            'suppliers' => [],
            'orders' => [],
        ];

        // Search products
        if ($type === 'all' || $type === 'product') {
            $results['products'] = $this->searchProducts($organizationId, $query, $limit);
        }

        // Search suppliers
        if ($type === 'all' || $type === 'supplier') {
            $results['suppliers'] = $this->searchSuppliers($organizationId, $query, $limit);
        }

        // Search orders
        if ($type === 'all' || $type === 'order') {
            $results['orders'] = $this->searchOrders($organizationId, $query, $limit);
        }

        return response()->json([
            'success' => true,
            'query' => $query,
            'type' => $type,
            'results' => $results,
            'products' => $results['products'],
            'suppliers' => $results['suppliers'],
            'orders' => $results['orders'],
        ]);
    }

    /**
     * Search products.
     */
    protected function searchProducts(int $organizationId, string $query, int $limit): array
    {
        try {
            $products = Product::forOrganization($organizationId)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'ilike', "%{$query}%")
                        ->orWhere('sku', 'ilike', "%{$query}%")
                        ->orWhere('barcode', 'ilike', "%{$query}%")
                        ->orWhere('description', 'ilike', "%{$query}%");
                })
                ->with(['category:id,name', 'images'])
                ->select(['id', 'name', 'sku', 'barcode', 'selling_price', 'category_id', 'status', 'featured_image'])
                ->limit($limit)
                ->get();

            return $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'type' => 'product',
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'price' => number_format((float) $product->selling_price, 2),
                    'category' => $product->category?->name,
                    'image' => $this->imageUrl($product->images?->first()?->url ?? $product->featured_image),
                    'status' => $product->status,
                ];
            })->toArray();
        } catch (\Exception $e) {
            report($e);

            return [];
        }
    }

    /**
     * Search suppliers.
     */
    protected function searchSuppliers(int $organizationId, string $query, int $limit): array
    {
        try {
            $suppliers = Supplier::where('organization_id', $organizationId)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'ilike', "%{$query}%")
                        ->orWhere('email', 'ilike', "%{$query}%")
                        ->orWhere('company_name', 'ilike', "%{$query}%")
                        ->orWhere('phone', 'ilike', "%{$query}%");
                })
                ->select(['id', 'name', 'email', 'company_name', 'phone', 'status'])
                ->limit($limit)
                ->get();

            return $suppliers->map(function ($supplier) {
                return [
                    'id' => $supplier->id,
                    'type' => 'supplier',
                    'name' => $supplier->name ?? $supplier->company_name,
                    'email' => $supplier->email,
                    'company' => $supplier->company_name,
                    'phone' => $supplier->phone,
                    'status' => $supplier->status,
                ];
            })->toArray();
        } catch (\Exception $e) {
            report($e);

            return [];
        }
    }

    /**
     * Search orders by marketplace order ID, buyer name, or email.
     */
    protected function searchOrders(int $organizationId, string $query, int $limit): array
    {
        try {
            $orders = Order::where('organization_id', $organizationId)
                ->where(function ($q) use ($query) {
                    $q->where('marketplace_order_id', 'ilike', "%{$query}%")
                        ->orWhere('buyer_name', 'ilike', "%{$query}%")
                        ->orWhere('buyer_email', 'ilike', "%{$query}%")
                        ->orWhere('buyer_username', 'ilike', "%{$query}%");
                })
                ->select(['id', 'marketplace_order_id', 'marketplace', 'buyer_name', 'status', 'total_amount', 'created_at'])
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();

            return $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'type' => 'order',
                    'marketplace_order_id' => $order->marketplace_order_id,
                    'marketplace' => $order->marketplace,
                    'buyer_name' => $order->buyer_name,
                    'total_amount' => number_format((float) $order->total_amount, 2),
                    'status' => $order->status,
                    'created_at' => $order->created_at?->toIso8601String(),
                ];
            })->toArray();
        } catch (\Exception $e) {
            report($e);

            return [];
        }
    }

    /**
     * Get workflow counts for the WorkflowIndicators component.
     */
    public function workflowCounts(Request $request): JsonResponse
    {
        $user = Auth::user();
        $organizationId = $this->resolveOrganizationId($user);
        $teamId = $user->currentTeam?->id;

        $counts = [
            'intake' => 0,
            'to_list' => 0,
            'to_ship' => 0,
            'settlements' => 0,
            'labeling' => 0,
        ];

        try {
            // Count intake queue items
            $counts['intake'] = ProductIntakeQueue::where('team_id', $teamId)
                ->whereIn('processing_status', ['pending', 'queued', 'processing'])
                ->count();

            // Count products ready to list
            $counts['to_list'] = Product::forOrganization($organizationId)
                ->where('status', 'ready_to_list')
                ->count();

            // Count pending shipments (stubbed - depends on Orders model)
            // $counts['to_ship'] = Order::where('organization_id', $organizationId)
            //     ->where('status', 'pending_shipment')
            //     ->count();

            // Count pending settlements (suppliers with unsettled balances)
            $counts['settlements'] = Supplier::where('organization_id', $organizationId)
                ->where('pending_balance', '>', 0)
                ->count();

            // Count actionable labeling work orders
            $counts['labeling'] = LabelingWorkOrder::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->whereIn('status', ['open', 'in_progress'])
                ->count();
        } catch (\Exception $e) {
            report($e);
        }

        return response()->json($counts);
    }

    /**
     * Quick add to intake queue from barcode scan.
     */
    public function quickIntakeAdd(Request $request): JsonResponse
    {
        $request->validate([
            'barcode' => 'required|string|max:255',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'location_id' => 'nullable|integer|exists:inventory_locations,id',
            'default_price' => 'nullable|numeric|min:0',
        ]);

        $user = Auth::user();
        $teamId = $user->currentTeam?->id;

        if (! $teamId) {
            return response()->json([
                'success' => false,
                'message' => 'No team selected',
            ], 400);
        }

        $organizationId = $this->resolveOrganizationId($user);

        if (! $organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization associated with this account.',
            ], 422);
        }

        try {
            // Check if barcode already exists in queue
            $existing = ProductIntakeQueue::where('team_id', $teamId)
                ->where('barcode', $request->barcode)
                ->whereIn('processing_status', ['pending', 'queued', 'processing'])
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Barcode already in queue',
                    'existing_item' => [
                        'id' => $existing->id,
                        'status' => $existing->processing_status,
                    ],
                ], 409);
            }

            // Check if product already exists
            $existingProduct = Product::forOrganization($organizationId)
                ->where('barcode', $request->barcode)
                ->first();

            if ($existingProduct) {
                return response()->json([
                    'success' => true,
                    'message' => 'Product found in inventory',
                    'product' => [
                        'id' => $existingProduct->id,
                        'name' => $existingProduct->name,
                        'sku' => $existingProduct->sku,
                    ],
                    'action' => 'existing',
                ]);
            }

            // Create new intake queue item
            $queueItem = ProductIntakeQueue::create([
                'team_id' => $teamId,
                'captured_by_user_id' => $user->id,
                'barcode' => $request->barcode,
                'processing_status' => 'pending',
                'supplier_id' => $request->supplier_id,
                'location_id' => $request->location_id,
                'metadata' => [
                    'default_price' => $request->default_price,
                    'source' => 'quick_intake',
                    'scanned_at' => now()->toIso8601String(),
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Added to intake queue',
                'queue_item' => [
                    'id' => $queueItem->id,
                    'barcode' => $queueItem->barcode,
                    'status' => $queueItem->processing_status,
                ],
                'action' => 'queued',
            ]);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process barcode',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
