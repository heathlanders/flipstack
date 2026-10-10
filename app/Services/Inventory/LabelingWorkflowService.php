<?php

namespace App\Services\Inventory;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductIntakeQueue;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\LabelingWorkOrder;
use App\Models\Inventory\LabelingWorkOrderItem;
use App\Models\Inventory\PartOutWorkOrder;
use App\Models\Inventory\WarehouseBin;
use App\Models\Organization\OrganizationSettings;
use App\Models\Procurement\PurchaseOrderReceipt;
use App\Models\System\BulkOperationJob;
use App\Notifications\Inventory\LabelingWorkOrderCreated;
use App\Services\LabelPrintingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class LabelingWorkflowService
{
    public function __construct(
        protected LabelPrintingService $labelPrintingService,
    ) {}

    // ---------------------------------------------------------------
    // Work Order Creation
    // ---------------------------------------------------------------

    public function createFromPoReceipt(PurchaseOrderReceipt $receipt): ?LabelingWorkOrder
    {
        $orgId = $receipt->purchaseOrder->organization_id;

        if (! $this->isAutoLabelingEnabled($orgId)) {
            return null;
        }

        $receipt->loadMissing('items');

        $productItems = $receipt->items
            ->filter(fn ($item) => $item->product_id)
            ->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity_received,
            ])
            ->values()
            ->toArray();

        if (empty($productItems)) {
            return null;
        }

        $wo = $this->createWorkOrder(
            organizationId: $orgId,
            locationId: $receipt->location_id,
            sourceType: LabelingWorkOrder::SOURCE_PO_RECEIPT,
            sourceId: $receipt->id,
            items: $productItems,
            createdBy: $receipt->received_by,
        );

        $this->notifyWorkOrderCreated($wo);

        return $wo;
    }

    public function createFromIntakeComplete(Product $product, ProductIntakeQueue $queueItem): ?LabelingWorkOrder
    {
        if (! $this->isAutoLabelingEnabled($product->organization_id)) {
            return null;
        }

        $locationId = $queueItem->quick_location ?? null;

        // Batch into existing open WO if one exists for same org + location within 30 min
        $existingWo = LabelingWorkOrder::withoutGlobalScopes()
            ->where('organization_id', $product->organization_id)
            ->where('source_type', LabelingWorkOrder::SOURCE_INTAKE_QUEUE)
            ->where('status', LabelingWorkOrder::STATUS_OPEN)
            ->where('location_id', $locationId)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->first();

        if ($existingWo) {
            $this->addItemsToWorkOrder($existingWo, [
                ['product_id' => $product->id, 'quantity' => 1],
            ]);

            return $existingWo;
        }

        $wo = $this->createWorkOrder(
            organizationId: $product->organization_id,
            locationId: $locationId,
            sourceType: LabelingWorkOrder::SOURCE_INTAKE_QUEUE,
            sourceId: $queueItem->id,
            items: [['product_id' => $product->id, 'quantity' => 1]],
            createdBy: auth()->id(),
        );

        $this->notifyWorkOrderCreated($wo);

        return $wo;
    }

    public function createFromImportJob(
        BulkOperationJob $job,
        array $productIds,
        ?string $locationId,
        string $sourceType = LabelingWorkOrder::SOURCE_CSV_IMPORT,
    ): ?LabelingWorkOrder {
        if (empty($productIds)) {
            return null;
        }

        $orgId = $job->organization_id;

        if (! $this->isAutoLabelingEnabled($orgId)) {
            return null;
        }

        $items = collect($productIds)->map(fn ($id) => [
            'product_id' => $id,
            'quantity' => 1,
        ])->toArray();

        $wo = $this->createWorkOrder(
            organizationId: $orgId,
            locationId: $locationId,
            sourceType: $sourceType,
            sourceId: $job->id,
            items: $items,
            createdBy: $job->user_id ?? auth()->id(),
        );

        $this->notifyWorkOrderCreated($wo);

        return $wo;
    }

    public function createFromPartOut(PartOutWorkOrder $partOutWo, array $componentProductIds): ?LabelingWorkOrder
    {
        if (empty($componentProductIds)) {
            return null;
        }

        if (! $this->isAutoLabelingEnabled($partOutWo->organization_id)) {
            return null;
        }

        $items = collect($componentProductIds)->map(fn ($id) => [
            'product_id' => $id,
            'quantity' => 1,
        ])->toArray();

        $wo = $this->createWorkOrder(
            organizationId: $partOutWo->organization_id,
            locationId: $partOutWo->location_id,
            sourceType: LabelingWorkOrder::SOURCE_PART_OUT,
            sourceId: $partOutWo->id,
            items: $items,
            createdBy: $partOutWo->completed_by ?? auth()->id(),
        );

        $this->notifyWorkOrderCreated($wo);

        return $wo;
    }

    public function createManual(array $productIds, string $organizationId, ?string $locationId, int $userId): LabelingWorkOrder
    {
        $items = collect($productIds)->map(fn ($id) => [
            'product_id' => $id,
            'quantity' => 1,
        ])->toArray();

        return $this->createWorkOrder(
            organizationId: $organizationId,
            locationId: $locationId,
            sourceType: LabelingWorkOrder::SOURCE_MANUAL,
            sourceId: null,
            items: $items,
            createdBy: $userId,
        );
    }

    // ---------------------------------------------------------------
    // Operations
    // ---------------------------------------------------------------

    public function startWorkOrder(LabelingWorkOrder $wo, int $userId): void
    {
        if (! $wo->canStart()) {
            throw new \RuntimeException('Work order cannot be started.');
        }

        $wo->markInProgress($userId);
    }

    public function markItemLabeled(LabelingWorkOrderItem $item, int $userId): void
    {
        if (! $item->canTransitionTo(LabelingWorkOrderItem::STATUS_LABEL_PRINTED)) {
            throw new \RuntimeException('Item cannot be marked as labeled.');
        }

        $item->markLabelPrinted($userId);

        $this->stampProductLabeled($item->product, $userId);
    }

    public function markAllLabeled(LabelingWorkOrder $wo, int $userId): void
    {
        $pendingItems = $wo->items()->where('status', LabelingWorkOrderItem::STATUS_PENDING)->get();

        foreach ($pendingItems as $item) {
            $item->markLabelPrinted($userId);
            $this->stampProductLabeled($item->product, $userId);
        }
    }

    public function printLabelsForWorkOrder(LabelingWorkOrder $wo, ?int $templateId = null, ?int $printerId = null): array
    {
        $pendingItems = $wo->items()
            ->where('status', LabelingWorkOrderItem::STATUS_PENDING)
            ->with('product')
            ->get();

        $productIds = $pendingItems->pluck('product.id')->filter()->values()->toArray();

        $results = [];
        if (! empty($productIds)) {
            try {
                $results = $this->labelPrintingService->printBatch($productIds, 1, $templateId, $printerId);
            } catch (\Throwable $e) {
                Log::warning('Label printing failed, marking items as labeled manually', [
                    'work_order_id' => $wo->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Mark items as labeled regardless of print success
        $userId = auth()->id();
        foreach ($pendingItems as $item) {
            $item->markLabelPrinted($userId);
            $this->stampProductLabeled($item->product, $userId);
        }

        return $results;
    }

    public function printLabelForItem(LabelingWorkOrderItem $item, ?int $templateId = null, ?int $printerId = null): bool
    {
        $result = false;

        if ($item->product) {
            try {
                $result = $this->labelPrintingService->printOnDemand($item->product, $item->quantity, $templateId, $printerId);
            } catch (\Throwable $e) {
                Log::warning('Single label print failed', [
                    'item_id' => $item->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->markItemLabeled($item, auth()->id());

        return $result;
    }

    public function assignBin(LabelingWorkOrderItem $item, string $binId, int $userId, bool $verified = false): void
    {
        $bin = WarehouseBin::findOrFail($binId);

        if (! $bin->is_active) {
            throw new \RuntimeException('Bin is not active.');
        }

        // Validate bin belongs to the WO's location via zone → location chain
        $wo = $item->workOrder;
        if ($wo->location_id && $bin->zone?->location_id !== $wo->location_id) {
            throw new \RuntimeException('Bin does not belong to the work order location.');
        }

        // Check capacity with lock to prevent races
        DB::transaction(function () use ($bin, $item, $userId, $verified) {
            $bin = WarehouseBin::lockForUpdate()->find($bin->id);

            if ($bin->isAtCapacity()) {
                throw new \RuntimeException('Bin is at capacity. Choose a different bin.');
            }

            // Update inventory level to point to this bin
            $level = InventoryLevel::where('product_id', $item->product_id)
                ->where('location_id', $item->workOrder->location_id)
                ->first();

            if ($level) {
                $level->update(['bin_id' => $bin->id]);
            }

            // Increment bin count
            $bin->increment('current_count');

            // Mark item shelved
            $item->markShelved($bin->id, $userId, $verified);

            // Update product labeling status
            $item->product?->update(['labeling_status' => 'shelved']);
        });
    }

    public function assignBinByBarcode(LabelingWorkOrderItem $item, string $barcode, int $userId): void
    {
        $bin = WarehouseBin::where('barcode', $barcode)->first();

        if (! $bin) {
            throw new \RuntimeException('No bin found with barcode: '.$barcode);
        }

        $this->assignBin($item, $bin->id, $userId, true);
    }

    public function skipItem(LabelingWorkOrderItem $item, string $reason): void
    {
        if (! $item->canTransitionTo(LabelingWorkOrderItem::STATUS_SKIPPED)) {
            throw new \RuntimeException('Item cannot be skipped.');
        }

        $item->markSkipped($reason);
    }

    public function completeWorkOrder(LabelingWorkOrder $wo, int $userId): void
    {
        if (! $wo->canComplete()) {
            throw new \RuntimeException('Work order cannot be completed.');
        }

        $wo->recalculateCounts();
        $wo->refresh();

        $pendingCount = $wo->items()->where('status', LabelingWorkOrderItem::STATUS_PENDING)->count();
        $labeledOnly = $wo->items()->where('status', LabelingWorkOrderItem::STATUS_LABEL_PRINTED)->count();

        if ($pendingCount > 0 || $labeledOnly > 0) {
            $wo->markPartiallyCompleted();
        } else {
            $wo->markCompleted();
        }
    }

    public function suggestBin(Product $product, ?string $locationId): ?WarehouseBin
    {
        $query = WarehouseBin::query()
            ->active()
            ->where(function ($q) {
                $q->whereNull('max_capacity')
                    ->orWhereColumn('current_count', '<', 'max_capacity');
            });

        if ($locationId) {
            $query->whereHas('zone', fn ($q) => $q->where('location_id', $locationId)->where('is_active', true));
        }

        // Prefer bins in same zone as other products in same category
        if ($product->category_id) {
            $existingBinIds = InventoryLevel::whereHas('product', fn ($q) => $q->where('category_id', $product->category_id))
                ->whereNotNull('bin_id')
                ->pluck('bin_id')
                ->unique();

            $zoneBin = $query->clone()
                ->whereIn('zone_id', function ($q) use ($existingBinIds) {
                    $q->select('zone_id')->from('warehouse_bins')->whereIn('id', $existingBinIds);
                })
                ->orderBy('current_count')
                ->first();

            if ($zoneBin) {
                return $zoneBin;
            }
        }

        // Fallback: any available bin with least utilization
        return $query->orderBy('current_count')->first();
    }

    // ---------------------------------------------------------------
    // Queries
    // ---------------------------------------------------------------

    public function getActionableWorkOrders(string $organizationId): Collection
    {
        return LabelingWorkOrder::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->actionable()
            ->with(['location', 'assignedTo'])
            ->orderByRaw("CASE WHEN priority = 'urgent' THEN 0 WHEN priority = 'high' THEN 1 ELSE 2 END")
            ->orderBy('created_at')
            ->get();
    }

    public function getStats(string $organizationId): array
    {
        $base = LabelingWorkOrder::withoutGlobalScopes()
            ->where('organization_id', $organizationId);

        return [
            'open' => (clone $base)->where('status', LabelingWorkOrder::STATUS_OPEN)->count(),
            'in_progress' => (clone $base)->where('status', LabelingWorkOrder::STATUS_IN_PROGRESS)->count(),
            'completed_today' => (clone $base)
                ->whereIn('status', [LabelingWorkOrder::STATUS_COMPLETED, LabelingWorkOrder::STATUS_PARTIALLY_COMPLETED])
                ->whereDate('completed_at', today())
                ->count(),
            'items_pending_label' => LabelingWorkOrderItem::query()
                ->whereHas('workOrder', fn ($q) => $q->withoutGlobalScopes()
                    ->where('organization_id', $organizationId)
                    ->actionable()
                )->where('status', LabelingWorkOrderItem::STATUS_PENDING)
                ->count(),
        ];
    }

    // ---------------------------------------------------------------
    // Internal helpers
    // ---------------------------------------------------------------

    protected function createWorkOrder(
        string $organizationId,
        ?string $locationId,
        string $sourceType,
        ?string $sourceId,
        array $items,
        ?int $createdBy,
    ): LabelingWorkOrder {
        return DB::transaction(function () use ($organizationId, $locationId, $sourceType, $sourceId, $items, $createdBy) {
            $wo = LabelingWorkOrder::create([
                'organization_id' => $organizationId,
                'work_order_number' => $this->generateWorkOrderNumber($organizationId),
                'source_type' => $sourceType,
                'source_id' => $sourceId !== null ? (string) $sourceId : null,
                'location_id' => $locationId,
                'status' => LabelingWorkOrder::STATUS_OPEN,
                'priority' => LabelingWorkOrder::PRIORITY_STANDARD,
                'total_items' => count($items),
                'created_by' => $createdBy,
            ]);

            foreach ($items as $idx => $item) {
                LabelingWorkOrderItem::create([
                    'work_order_id' => $wo->id,
                    'product_id' => $item['product_id'],
                    'product_variation_id' => $item['product_variation_id'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                    'status' => LabelingWorkOrderItem::STATUS_PENDING,
                    'sort_order' => $idx,
                ]);

                // Mark product as pending label
                Product::where('id', $item['product_id'])->update(['labeling_status' => 'pending_label']);
            }

            Log::info('Labeling work order created', [
                'work_order_id' => $wo->id,
                'work_order_number' => $wo->work_order_number,
                'source_type' => $sourceType,
                'items_count' => count($items),
            ]);

            return $wo;
        });
    }

    public function addItemsToWorkOrder(LabelingWorkOrder $wo, array $items): void
    {
        $currentMax = $wo->items()->max('sort_order') ?? -1;

        foreach ($items as $idx => $item) {
            LabelingWorkOrderItem::create([
                'work_order_id' => $wo->id,
                'product_id' => $item['product_id'],
                'product_variation_id' => $item['product_variation_id'] ?? null,
                'quantity' => $item['quantity'] ?? 1,
                'status' => LabelingWorkOrderItem::STATUS_PENDING,
                'sort_order' => $currentMax + $idx + 1,
            ]);

            Product::where('id', $item['product_id'])->update(['labeling_status' => 'pending_label']);
        }

        $wo->recalculateCounts();
    }

    public function generateWorkOrderNumber(string $organizationId): string
    {
        $count = LabelingWorkOrder::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->count();

        return sprintf('LWO-%s-%04d', now()->format('Ymd'), $count + 1);
    }

    protected function stampProductLabeled(?Product $product, ?int $userId): void
    {
        if (! $product) {
            return;
        }

        $product->update([
            'labeling_status' => 'labeled',
            'last_label_printed_at' => now(),
            'last_label_printed_by' => $userId,
        ]);
    }

    protected function isAutoLabelingEnabled(string $organizationId): bool
    {
        return (bool) OrganizationSettings::get((int) $organizationId, 'auto_labeling_enabled', false);
    }

    protected function notifyWorkOrderCreated(LabelingWorkOrder $wo): void
    {
        try {
            $users = \App\Models\User\User::whereHas('teams', function ($q) use ($wo) {
                $q->where('teams.organization_id', $wo->organization_id);
            })->get();

            if ($users->isNotEmpty()) {
                Notification::send($users, new LabelingWorkOrderCreated($wo));
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to send labeling WO notification', [
                'work_order_id' => $wo->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
