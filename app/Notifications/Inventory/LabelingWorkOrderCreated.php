<?php

namespace App\Notifications\Inventory;

use App\Models\Inventory\LabelingWorkOrder;
use App\Notifications\BaseNotification;

class LabelingWorkOrderCreated extends BaseNotification
{
    public function __construct(
        protected LabelingWorkOrder $workOrder,
    ) {
        $this->onQueue('notifications');
    }

    public function getPriority(): string
    {
        return match ($this->workOrder->priority) {
            LabelingWorkOrder::PRIORITY_URGENT => self::PRIORITY_HIGH,
            LabelingWorkOrder::PRIORITY_HIGH => self::PRIORITY_MEDIUM,
            default => self::PRIORITY_LOW,
        };
    }

    public function getCategory(): string
    {
        return self::CATEGORY_INVENTORY;
    }

    public function getTitle(): string
    {
        return 'New Labeling Work Order: '.$this->workOrder->work_order_number;
    }

    public function getMessage(): string
    {
        $locationName = $this->workOrder->location?->name ?? 'Unassigned';

        return $this->workOrder->total_items.' items ready for labeling at '.$locationName;
    }

    public function getActionUrl(): ?string
    {
        return route('inventory.labeling.show', $this->workOrder->id);
    }

    public function getActionText(): string
    {
        return 'View Work Order';
    }

    protected function getData(): array
    {
        return [
            'work_order_id' => $this->workOrder->id,
            'work_order_number' => $this->workOrder->work_order_number,
            'source_type' => $this->workOrder->source_type,
            'total_items' => $this->workOrder->total_items,
            'location_id' => $this->workOrder->location_id,
        ];
    }
}
