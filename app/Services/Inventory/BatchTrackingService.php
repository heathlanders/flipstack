<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\InventoryBatchItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BatchTrackingService
{
    public function generateBatchNumber(string $organizationId): string
    {
        $count = InventoryBatch::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->count();

        return sprintf('BATCH-%06d', $count + 1);
    }

    public function create(array $data, string $organizationId): InventoryBatch
    {
        return DB::transaction(function () use ($data, $organizationId) {
            $batch = InventoryBatch::create([
                'organization_id' => $organizationId,
                'batch_number' => $this->generateBatchNumber($organizationId),
                'supplier_id' => $data['supplier_id'] ?? null,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'acquisition_date' => $data['acquisition_date'] ?? now()->toDateString(),
                'source_description' => $data['source_description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'open',
            ]);

            if (! empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $this->addItem($batch, $item);
                }
            }

            Log::info('Inventory batch created', ['batch_id' => $batch->id, 'batch_number' => $batch->batch_number]);

            return $batch->fresh(['items.product', 'supplier']);
        });
    }

    public function addItem(InventoryBatch $batch, array $itemData): InventoryBatchItem
    {
        return InventoryBatchItem::create([
            'batch_id' => $batch->id,
            'product_id' => $itemData['product_id'],
            'quantity' => $itemData['quantity'] ?? 1,
            'unit_cost' => $itemData['unit_cost'] ?? 0,
        ]);
    }

    public function close(InventoryBatch $batch): void
    {
        $batch->close();
        Log::info('Inventory batch closed', ['batch_id' => $batch->id]);
    }

    public function getStats(string $organizationId): array
    {
        $base = InventoryBatch::withoutGlobalScopes()->where('organization_id', $organizationId);

        return [
            'total' => (clone $base)->count(),
            'open' => (clone $base)->where('status', 'open')->count(),
            'closed' => (clone $base)->where('status', 'closed')->count(),
            'total_acquisition_cost' => round((clone $base)->sum('acquisition_cost'), 2),
            'total_items' => (clone $base)->sum('item_count'),
        ];
    }
}
