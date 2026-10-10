<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderItem;
use App\Services\Procurement\ReceivingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileReceivingController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        protected ReceivingService $receivingService
    ) {}

    /**
     * GET /api/receiving
     * Open purchase orders (submitted / partially received) ready to receive.
     */
    public function index(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $pos = PurchaseOrder::with(['supplier:id,name', 'location:id,name,code'])
            ->withCount('items')
            ->where('organization_id', $organizationId)
            ->whereIn('status', [
                PurchaseOrder::STATUS_SUBMITTED,
                PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            ])
            ->orderByRaw('expected_delivery_date IS NULL, expected_delivery_date')
            ->get();

        $stats = [
            'total_orders' => $pos->count(),
            'submitted' => $pos->where('status', PurchaseOrder::STATUS_SUBMITTED)->count(),
            'partially_received' => $pos->where('status', PurchaseOrder::STATUS_PARTIALLY_RECEIVED)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $pos->map(fn ($po) => $this->formatPoSummary($po)),
            'stats' => $stats,
        ]);
    }

    /**
     * GET /api/receiving/{purchaseOrder}
     * Purchase order detail with per-line ordered/received/remaining quantities.
     */
    public function show(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->organization_id !== $this->organizationId($request)) {
            return response()->json(['success' => false, 'message' => 'Purchase order not found'], 404);
        }

        $purchaseOrder->load([
            'items.product:id,name,sku,internal_sku,barcode',
            'supplier:id,name',
            'location:id,name,code',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatPoDetail($purchaseOrder),
        ]);
    }

    /**
     * POST /api/receiving/{purchaseOrder}/items/{item}/scan-verify
     * Verify a scanned barcode matches a PO line without receiving it.
     */
    public function scanVerify(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderItem $item): JsonResponse
    {
        if ($purchaseOrder->organization_id !== $this->organizationId($request)) {
            return response()->json(['success' => false, 'message' => 'Purchase order not found'], 404);
        }
        if ($item->purchase_order_id !== $purchaseOrder->id) {
            return response()->json(['success' => false, 'message' => 'Item not on this purchase order'], 404);
        }

        $request->validate(['barcode' => 'required|string|max:100']);

        $scanned = $request->barcode;
        $matched = false;
        $matchType = null;
        $product = $item->product;

        if ($product) {
            if ($product->barcode === $scanned) {
                $matched = true;
                $matchType = 'barcode';
            } elseif ($product->sku === $scanned) {
                $matched = true;
                $matchType = 'sku';
            } elseif ($product->internal_sku === $scanned) {
                $matched = true;
                $matchType = 'internal_sku';
            } elseif ($product->identifiers()->where('value', $scanned)->exists()) {
                $matched = true;
                $matchType = 'identifier';
            }
        } elseif ($item->sku === $scanned) {
            $matched = true;
            $matchType = 'sku';
        }

        return response()->json([
            'success' => true,
            'matched' => $matched,
            'match_type' => $matchType,
            'scanned_barcode' => $scanned,
            'expected_sku' => $item->sku ?? $product?->sku,
            'item' => $this->formatItem($item),
        ]);
    }

    /**
     * POST /api/receiving/{purchaseOrder}/items/{item}/receive
     * Receive a quantity against a single PO line. Supports barcode verification.
     */
    public function receiveItem(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderItem $item): JsonResponse
    {
        if ($purchaseOrder->organization_id !== $this->organizationId($request)) {
            return response()->json(['success' => false, 'message' => 'Purchase order not found'], 404);
        }
        if ($item->purchase_order_id !== $purchaseOrder->id) {
            return response()->json(['success' => false, 'message' => 'Item not on this purchase order'], 404);
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'barcode' => 'nullable|string|max:100',
            'location_id' => 'nullable|uuid|exists:inventory_locations,id',
            'condition' => 'nullable|string|in:good,damaged,defective',
            'condition_notes' => 'nullable|string|max:500',
        ]);

        // Barcode verification: if scanned, it must match this line's product.
        if ($request->filled('barcode') && $item->product) {
            $product = $item->product;
            $scanned = $request->barcode;
            $matched = $product->barcode === $scanned
                || $product->sku === $scanned
                || $product->internal_sku === $scanned
                || $product->identifiers()->where('value', $scanned)->exists();

            if (! $matched) {
                return response()->json([
                    'success' => false,
                    'message' => 'Barcode does not match this item. Expected SKU: '.($item->sku ?? $product->sku),
                    'expected_sku' => $item->sku ?? $product->sku,
                    'scanned_barcode' => $scanned,
                ], 422);
            }
        }

        return $this->doReceive($request, $purchaseOrder, [
            'location_id' => $validated['location_id'] ?? null,
            'items' => [[
                'purchase_order_item_id' => $item->id,
                'quantity_received' => $validated['quantity'],
                'condition' => $validated['condition'] ?? 'good',
                'condition_notes' => $validated['condition_notes'] ?? null,
            ]],
        ]);
    }

    /**
     * POST /api/receiving/{purchaseOrder}/complete
     * Receive all remaining quantity across every line in good condition.
     */
    public function complete(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->organization_id !== $this->organizationId($request)) {
            return response()->json(['success' => false, 'message' => 'Purchase order not found'], 404);
        }

        $validated = $request->validate([
            'location_id' => 'nullable|uuid|exists:inventory_locations,id',
            'notes' => 'nullable|string|max:2000',
        ]);

        $remaining = $purchaseOrder->items
            ->filter(fn ($i) => $i->remaining_quantity > 0)
            ->map(fn ($i) => [
                'purchase_order_item_id' => $i->id,
                'quantity_received' => $i->remaining_quantity,
                'condition' => 'good',
            ])->values()->all();

        if (empty($remaining)) {
            return response()->json([
                'success' => false,
                'message' => 'All items have already been fully received.',
            ], 422);
        }

        return $this->doReceive($request, $purchaseOrder, [
            'location_id' => $validated['location_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'items' => $remaining,
        ]);
    }

    /**
     * Shared receive path: call the service, emit a mobile-sourced audit row,
     * return the refreshed PO detail.
     */
    private function doReceive(Request $request, PurchaseOrder $purchaseOrder, array $data): JsonResponse
    {
        try {
            $receipt = $this->receivingService->receive($purchaseOrder, $data, (int) $request->user()->id);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $purchaseOrder->refresh()->load(['items.product:id,name,sku,internal_sku,barcode', 'supplier:id,name', 'location:id,name,code']);

        audit_event(
            action: 'po.received',
            description: "PO {$purchaseOrder->po_number} received via mobile",
            subject: $purchaseOrder,
            metadata: [
                'po_number' => $purchaseOrder->po_number,
                'receipt_id' => (string) $receipt->id,
                'items_received' => $receipt->items->count(),
                'quantity_received' => (int) $receipt->items->sum('quantity_received'),
                'po_status' => $purchaseOrder->status,
                'source' => 'mobile',
            ],
            severity: 'info',
            category: 'procurement',
        );

        return response()->json([
            'success' => true,
            'message' => "Received against {$purchaseOrder->po_number}",
            'data' => $this->formatPoDetail($purchaseOrder),
            'receipt' => [
                'id' => (string) $receipt->id,
                'items_received' => $receipt->items->count(),
                'quantity_received' => (int) $receipt->items->sum('quantity_received'),
            ],
        ]);
    }

    private function organizationId(Request $request): int
    {
        return $this->resolveOrganizationId($request->user())
            ?? abort(response()->json([
                'success' => false,
                'message' => 'No organization associated with this account.',
            ], 422));
    }

    private function formatPoSummary(PurchaseOrder $po): array
    {
        return [
            'id' => $po->id,
            'po_number' => $po->po_number,
            'status' => $po->status,
            'supplier_name' => $po->supplier?->name,
            'expected_delivery_date' => $po->expected_delivery_date?->toIso8601String(),
            'items_count' => $po->items_count,
            'total_quantity_ordered' => $po->total_quantity_ordered,
            'total_quantity_received' => $po->total_quantity_received,
            'receiving_progress' => round((float) $po->receiving_progress, 1),
            'location' => $po->location ? [
                'id' => $po->location->id,
                'name' => $po->location->name,
                'code' => $po->location->code,
            ] : null,
        ];
    }

    private function formatPoDetail(PurchaseOrder $po): array
    {
        return array_merge($this->formatPoSummary($po), [
            'can_receive' => $po->canReceiveItems(),
            'notes' => $po->notes,
            'items' => $po->items->map(fn ($item) => $this->formatItem($item))->values(),
        ]);
    }

    private function formatItem(PurchaseOrderItem $item): array
    {
        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'description' => $item->description,
            'sku' => $item->sku ?? $item->product?->sku,
            'barcode' => $item->product?->barcode,
            'quantity_ordered' => $item->quantity_ordered,
            'quantity_received' => $item->quantity_received,
            'remaining_quantity' => $item->remaining_quantity,
            'is_fully_received' => $item->isFullyReceived(),
            'unit_cost' => (float) $item->unit_cost,
            'product' => $item->product ? [
                'id' => $item->product->id,
                'name' => $item->product->name,
                'sku' => $item->product->sku,
                'internal_sku' => $item->product->internal_sku,
                'barcode' => $item->product->barcode,
            ] : null,
        ];
    }
}
