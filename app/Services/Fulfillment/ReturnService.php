<?php

namespace App\Services\Fulfillment;

use App\Jobs\Fulfillment\SyncRefundToMarketplace;
use App\Jobs\Inventory\PushInventoryToMarketplaces;
use App\Models\Catalog\Product;
use App\Models\Fulfillment\FulfillmentLog;
use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\ReturnRequest;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\InventoryLocation;
use App\Models\User\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReturnService
{
    protected FulfillmentAuditService $auditService;

    public function __construct(FulfillmentAuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function createReturn(Order $order, array $data, User $actor): ReturnRequest
    {
        $return = ReturnRequest::create([
            'organization_id' => $order->organization_id,
            'order_id' => $order->id,
            'order_item_id' => $data['order_item_id'] ?? null,
            'shipment_id' => $data['shipment_id'] ?? null,
            'rma_number' => ReturnRequest::generateRmaNumber(),
            'status' => ReturnRequest::STATUS_REQUESTED,
            'reason' => $data['reason'],
            'reason_details' => $data['reason_details'] ?? null,
            'source' => $data['source'] ?? ReturnRequest::SOURCE_BUYER,
            'marketplace_case_id' => $data['marketplace_case_id'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
            'refund_amount' => $data['refund_amount'] ?? null,
            'restockable' => $data['restockable'] ?? true,
        ]);

        $order->update(['status' => Order::STATUS_RETURN_INITIATED]);

        $this->auditService->log(
            FulfillmentLog::ACTION_RETURN_REQUESTED,
            $return,
            $actor,
            ['reason' => $data['reason'], 'rma_number' => $return->rma_number],
            $order->id
        );

        return $return;
    }

    public function approveReturn(ReturnRequest $return, User $actor): void
    {
        if ($return->status !== ReturnRequest::STATUS_REQUESTED) {
            throw new Exception('Return can only be approved from requested status.');
        }

        $return->update(['status' => ReturnRequest::STATUS_APPROVED]);

        $this->auditService->log(
            FulfillmentLog::ACTION_RETURN_REQUESTED,
            $return,
            $actor,
            ['action' => 'approved'],
            $return->order_id
        );

        $order = $return->order;
        $orderNumber = $order?->marketplace_order_id ?? (string) ($order?->id ?? '');
        $notificationService = app(\App\Services\NotificationService::class);

        $notificationService->dispatchEvent(
            eventKey: 'fulfillment.return.approved',
            organizationId: (int) $return->organization_id,
            args: [(string) $return->id, (string) $return->rma_number, $orderNumber ?: null],
            relatedId: (string) $return->id,
        );

        if ($order?->buyer_email) {
            $notificationService->dispatchEvent(
                eventKey: 'fulfillment.return.approved_to_buyer',
                organizationId: (int) $return->organization_id,
                args: [
                    (int) $return->organization_id,
                    (string) $return->rma_number,
                    $orderNumber ?: null,
                    $order->marketplace,
                    null,
                ],
                context: ['order' => ['buyer_email' => $order->buyer_email]],
                relatedId: 'buyer:'.$return->id,
            );
        }
    }

    public function receiveReturn(ReturnRequest $return, User $actor): void
    {
        if (! in_array($return->status, [ReturnRequest::STATUS_APPROVED, ReturnRequest::STATUS_SHIPPED_BACK])) {
            throw new Exception('Return must be approved or shipped back before receiving.');
        }

        $return->update([
            'status' => ReturnRequest::STATUS_RECEIVED,
            'received_at' => now(),
            'received_by' => $actor->id,
        ]);

        $this->auditService->log(
            FulfillmentLog::ACTION_RETURN_RECEIVED,
            $return,
            $actor,
            ['rma_number' => $return->rma_number],
            $return->order_id
        );

        $orderNumber = $return->order?->marketplace_order_id ?? (string) ($return->order_id ?? '');

        app(\App\Services\NotificationService::class)->dispatchEvent(
            eventKey: 'fulfillment.return.received',
            organizationId: (int) $return->organization_id,
            args: [(string) $return->id, (string) $return->rma_number, $orderNumber ?: null],
            relatedId: (string) $return->id,
        );
    }

    public function inspectReturn(ReturnRequest $return, array $inspection, User $actor): void
    {
        if ($return->status !== ReturnRequest::STATUS_RECEIVED) {
            throw new Exception('Return must be received before inspection.');
        }

        $return->update([
            'status' => ReturnRequest::STATUS_INSPECTED,
            'condition_on_return' => $inspection['condition'] ?? null,
            'inspection_notes' => $inspection['notes'] ?? null,
            'restockable' => $inspection['restockable'] ?? $return->restockable,
            'inspected_by' => $actor->id,
            'inspected_at' => now(),
        ]);

        $this->auditService->log(
            FulfillmentLog::ACTION_RETURN_INSPECTED,
            $return,
            $actor,
            [
                'condition' => $inspection['condition'] ?? null,
                'restockable' => $inspection['restockable'] ?? null,
            ],
            $return->order_id
        );
    }

    public function processRefund(ReturnRequest $return, User $actor): void
    {
        if ($return->status !== ReturnRequest::STATUS_INSPECTED) {
            throw new Exception('Return must be inspected before refund.');
        }

        $return->update([
            'status' => ReturnRequest::STATUS_REFUNDED,
            'refunded_at' => now(),
            'refund_method' => $return->refund_method ?? 'original_payment',
        ]);

        $return->order->update(['status' => Order::STATUS_RETURNED]);

        $this->auditService->log(
            FulfillmentLog::ACTION_REFUND_PROCESSED,
            $return,
            $actor,
            [
                'refund_amount' => $return->refund_amount,
                'refund_method' => $return->refund_method,
            ],
            $return->order_id
        );

        // Dispatch marketplace refund sync
        $order = $return->order;
        if ($order->credential_id && in_array($order->marketplace, ['ebay', 'shopify', 'amazon'])) {
            try {
                SyncRefundToMarketplace::dispatch($return);
            } catch (\Exception $e) {
                Log::warning('Failed to dispatch marketplace refund sync', [
                    'return_id' => $return->id,
                    'order_id' => $order->id,
                    'marketplace' => $order->marketplace,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $orderNumber = $order?->marketplace_order_id ?? (string) ($order?->id ?? '');
        $refundAmount = $return->refund_amount !== null ? (float) $return->refund_amount : null;
        $notificationService = app(\App\Services\NotificationService::class);

        $notificationService->dispatchEvent(
            eventKey: 'fulfillment.refund.processed',
            organizationId: (int) $return->organization_id,
            args: [(string) $return->id, (string) $return->rma_number, $orderNumber ?: null, $refundAmount],
            relatedId: (string) $return->id,
        );

        if ($order?->buyer_email) {
            $notificationService->dispatchEvent(
                eventKey: 'fulfillment.refund.processed_to_buyer',
                organizationId: (int) $return->organization_id,
                args: [
                    (int) $return->organization_id,
                    (string) $return->rma_number,
                    $orderNumber ?: null,
                    $refundAmount,
                    $order->marketplace,
                ],
                context: ['order' => ['buyer_email' => $order->buyer_email]],
                relatedId: 'buyer:'.$return->id,
            );
        }
    }

    public function denyReturn(ReturnRequest $return, string $reason, User $actor): void
    {
        $return->update([
            'status' => ReturnRequest::STATUS_DENIED,
            'inspection_notes' => trim(($return->inspection_notes ?? '')."\n[DENIED] {$reason}"),
        ]);

        $this->auditService->log(
            FulfillmentLog::ACTION_RETURN_INSPECTED,
            $return,
            $actor,
            ['action' => 'denied', 'reason' => $reason],
            $return->order_id
        );
    }

    public function restockItem(ReturnRequest $return, InventoryLocation $location, User $actor): void
    {
        if (! $return->restockable) {
            throw new Exception('This return item is not marked as restockable.');
        }

        $orderItem = $return->orderItem;
        if (! $orderItem || ! $orderItem->product_id) {
            throw new Exception('No product associated with this return item.');
        }

        DB::transaction(function () use ($return, $location, $orderItem, $actor) {
            $inventoryLevel = InventoryLevel::firstOrCreate(
                [
                    'product_id' => $orderItem->product_id,
                    'location_id' => $location->id,
                ],
                ['quantity_on_hand' => 0, 'quantity_reserved' => 0]
            );

            $inventoryLevel->increment('quantity_on_hand', $return->quantity);

            // Restore available quantity and push to marketplaces
            $product = Product::find($orderItem->product_id);

            if ($product) {
                $product->incrementAvailableQuantity((int) $return->quantity);

                PushInventoryToMarketplaces::dispatch($product->id);

                Log::info('Inventory restored on return restock', [
                    'product_id' => $product->id,
                    'return_id' => $return->id,
                    'quantity_restored' => $return->quantity,
                ]);
            }

            $this->auditService->log(
                FulfillmentLog::ACTION_ITEM_RESTOCKED,
                $return,
                $actor,
                [
                    'product_id' => $orderItem->product_id,
                    'location_id' => $location->id,
                    'quantity' => $return->quantity,
                ],
                $return->order_id
            );
        });
    }
}
