<?php

namespace App\Services\Fulfillment;

use App\Models\Fulfillment\FulfillmentLog;
use App\Models\Fulfillment\Order;
use App\Models\Inventory\InventoryLocation;
use App\Models\Organization\Organization;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Request;

class FulfillmentAuditService
{
    public function log(string $action, Model $loggable, User $user, ?array $metadata = null, ?string $orderId = null): FulfillmentLog
    {
        $resolvedOrderId = $orderId;
        if (! $resolvedOrderId && $loggable instanceof Order) {
            $resolvedOrderId = $loggable->id;
        } elseif (! $resolvedOrderId && method_exists($loggable, 'order')) {
            $resolvedOrderId = $loggable->order_id ?? $loggable->order?->id;
        }

        return FulfillmentLog::create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'action' => $action,
            'loggable_type' => get_class($loggable),
            'loggable_id' => $loggable->getKey(),
            'order_id' => $resolvedOrderId,
            'metadata' => $metadata,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }

    public function getOrderTimeline(Order $order): Collection
    {
        return FulfillmentLog::where('order_id', $order->id)
            ->with('user:id,name')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function (FulfillmentLog $log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'action_label' => $this->getActionLabel($log->action),
                    'user' => $log->user?->name ?? 'System',
                    'metadata' => $log->metadata,
                    'created_at' => $log->created_at,
                    'relative_time' => $log->created_at->diffForHumans(),
                ];
            });
    }

    public function getUserActivity(User $user, ?Carbon $since = null): Collection
    {
        $query = FulfillmentLog::where('user_id', $user->id)
            ->with('order:id,marketplace_order_id,status')
            ->orderBy('created_at', 'desc');

        if ($since) {
            $query->where('created_at', '>=', $since);
        }

        return $query->limit(100)->get();
    }

    public function getLocationActivity(InventoryLocation $location): Collection
    {
        return FulfillmentLog::whereHas('order', function ($q) use ($location) {
            $q->where('location_id', $location->id);
        })
            ->with(['user:id,name', 'order:id,marketplace_order_id,status'])
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();
    }

    public function getActionSummary(Organization $org, Carbon $from, Carbon $to): array
    {
        $logs = FulfillmentLog::where('organization_id', $org->id)
            ->whereBetween('created_at', [$from, $to])
            ->get();

        return [
            'total_actions' => $logs->count(),
            'orders_imported' => $logs->where('action', FulfillmentLog::ACTION_ORDER_IMPORTED)->count(),
            'orders_shipped' => $logs->where('action', FulfillmentLog::ACTION_TRACKING_UPLOADED)->count(),
            'labels_purchased' => $logs->where('action', FulfillmentLog::ACTION_LABEL_PURCHASED)->count(),
            'labels_voided' => $logs->where('action', FulfillmentLog::ACTION_LABEL_VOIDED)->count(),
            'returns_processed' => $logs->where('action', FulfillmentLog::ACTION_REFUND_PROCESSED)->count(),
            'items_picked' => $logs->where('action', FulfillmentLog::ACTION_ITEM_PICKED)->count(),
            'items_shorted' => $logs->where('action', FulfillmentLog::ACTION_PICK_SHORTED)->count(),
            'by_user' => $logs->groupBy('user_id')->map->count()->toArray(),
            'by_action' => $logs->groupBy('action')->map->count()->toArray(),
        ];
    }

    protected function getActionLabel(string $action): string
    {
        $labels = [
            FulfillmentLog::ACTION_ORDER_IMPORTED => 'Order imported',
            FulfillmentLog::ACTION_ORDER_ASSIGNED => 'Order assigned',
            FulfillmentLog::ACTION_ORDER_PRIORITY_CHANGED => 'Priority changed',
            FulfillmentLog::ACTION_ORDER_HELD => 'Order put on hold',
            FulfillmentLog::ACTION_ORDER_RELEASED => 'Order released',
            FulfillmentLog::ACTION_ORDER_CANCELLED => 'Order cancelled',
            FulfillmentLog::ACTION_PICK_STARTED => 'Picking started',
            FulfillmentLog::ACTION_ITEM_PICKED => 'Item picked',
            FulfillmentLog::ACTION_PICK_COMPLETED => 'Picking completed',
            FulfillmentLog::ACTION_PICK_SHORTED => 'Item shorted',
            FulfillmentLog::ACTION_PACK_STARTED => 'Packing started',
            FulfillmentLog::ACTION_RATE_SHOPPED => 'Rates fetched',
            FulfillmentLog::ACTION_LABEL_PURCHASED => 'Label purchased',
            FulfillmentLog::ACTION_LABEL_VOIDED => 'Label voided',
            FulfillmentLog::ACTION_ITEM_PACKED => 'Item packed',
            FulfillmentLog::ACTION_PACK_COMPLETED => 'Packing completed',
            FulfillmentLog::ACTION_SHIPMENT_CREATED => 'Shipment created',
            FulfillmentLog::ACTION_TRACKING_UPLOADED => 'Tracking uploaded',
            FulfillmentLog::ACTION_TRACKING_UPDATED => 'Tracking updated',
            FulfillmentLog::ACTION_DELIVERY_CONFIRMED => 'Delivery confirmed',
            FulfillmentLog::ACTION_RETURN_REQUESTED => 'Return requested',
            FulfillmentLog::ACTION_RETURN_RECEIVED => 'Return received',
            FulfillmentLog::ACTION_RETURN_INSPECTED => 'Return inspected',
            FulfillmentLog::ACTION_REFUND_PROCESSED => 'Refund processed',
            FulfillmentLog::ACTION_ITEM_RESTOCKED => 'Item restocked',
            FulfillmentLog::ACTION_DROPSHIP_ROUTED => 'Routed to supplier',
            FulfillmentLog::ACTION_DROPSHIP_ACCEPTED => 'Supplier accepted',
            FulfillmentLog::ACTION_DROPSHIP_REJECTED => 'Supplier rejected',
            FulfillmentLog::ACTION_DROPSHIP_ESCALATED => 'Dropship escalated',
            FulfillmentLog::ACTION_LABEL_PRINTED => 'Label printed',
            FulfillmentLog::ACTION_PACKING_SLIP_PRINTED => 'Packing slip printed',
            FulfillmentLog::ACTION_PICK_LIST_PRINTED => 'Pick list printed',
        ];

        return $labels[$action] ?? str_replace('_', ' ', ucfirst($action));
    }
}
