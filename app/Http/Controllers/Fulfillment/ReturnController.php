<?php

namespace App\Http\Controllers\Fulfillment;

use App\Http\Controllers\Controller;
use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\ReturnRequest;
use App\Models\Inventory\InventoryLocation;
use App\Services\Fulfillment\ReturnService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReturnController extends Controller
{
    protected ReturnService $returnService;

    public function __construct(ReturnService $returnService)
    {
        $this->returnService = $returnService;
    }

    public function index(Request $request)
    {
        $query = ReturnRequest::with([
            'order:id,marketplace_order_id,buyer_name,total_amount',
            'orderItem:id,title,sku,quantity',
            'inspectedByUser:id,name',
            'receivedByUser:id,name',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('rma_number', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($oq) => $oq->where('marketplace_order_id', 'like', "%{$search}%"));
            });
        }

        $returns = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        $stats = [
            'open' => ReturnRequest::open()->count(),
            'awaiting_receipt' => ReturnRequest::whereIn('status', [ReturnRequest::STATUS_APPROVED, ReturnRequest::STATUS_SHIPPED_BACK])->count(),
            'awaiting_inspection' => ReturnRequest::where('status', ReturnRequest::STATUS_RECEIVED)->count(),
            'refunded_this_month' => ReturnRequest::where('status', ReturnRequest::STATUS_REFUNDED)
                ->whereMonth('refunded_at', now()->month)
                ->sum('refund_amount'),
        ];

        return Inertia::render('Fulfillment/Returns/Index', [
            'returns' => $returns,
            'stats' => $stats,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function show(ReturnRequest $returnRequest)
    {
        $returnRequest->load([
            'order.items',
            'order.shipments',
            'orderItem',
            'shipment',
            'inspectedByUser:id,name',
            'receivedByUser:id,name',
        ]);

        $locations = InventoryLocation::where('is_active', true)->get(['id', 'name', 'code']);

        return Inertia::render('Fulfillment/Returns/Show', [
            'returnRequest' => $returnRequest,
            'locations' => $locations,
        ]);
    }

    public function create(Request $request, Order $order)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
            'reason_details' => 'nullable|string|max:2000',
            'order_item_id' => 'nullable|exists:order_items,id',
            'quantity' => 'nullable|integer|min:1',
            'refund_amount' => 'nullable|numeric|min:0',
            'source' => 'nullable|in:buyer,internal,marketplace',
            'marketplace_case_id' => 'nullable|string|max:255',
        ]);

        $return = $this->returnService->createReturn($order, $request->all(), $request->user());

        return back()->with('success', 'Return initiated. RMA: '.$return->rma_number);
    }

    public function approve(Request $request, ReturnRequest $returnRequest)
    {
        $this->returnService->approveReturn($returnRequest, $request->user());

        return back()->with('success', 'Return approved. RMA: '.$returnRequest->rma_number);
    }

    public function receive(Request $request, ReturnRequest $returnRequest)
    {
        $this->returnService->receiveReturn($returnRequest, $request->user());

        return back()->with('success', 'Return received.');
    }

    public function inspect(Request $request, ReturnRequest $returnRequest)
    {
        $request->validate([
            'condition' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'restockable' => 'required|boolean',
        ]);

        $this->returnService->inspectReturn($returnRequest, $request->all(), $request->user());

        return back()->with('success', 'Return inspected.');
    }

    public function refund(Request $request, ReturnRequest $returnRequest)
    {
        $this->returnService->processRefund($returnRequest, $request->user());

        return back()->with('success', 'Refund processed for RMA '.$returnRequest->rma_number);
    }

    public function deny(Request $request, ReturnRequest $returnRequest)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $this->returnService->denyReturn($returnRequest, $request->reason, $request->user());

        return back()->with('success', 'Return denied.');
    }

    public function restock(Request $request, ReturnRequest $returnRequest)
    {
        $request->validate(['location_id' => 'required|exists:inventory_locations,id']);

        $location = InventoryLocation::findOrFail($request->location_id);
        $this->returnService->restockItem($returnRequest, $location, $request->user());

        return back()->with('success', 'Item restocked at '.$location->name);
    }
}
