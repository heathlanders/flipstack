<?php

namespace App\Jobs\Fulfillment;

use App\Models\Fulfillment\ReturnRequest;
use App\Services\Amazon\AmazonOrderService;
use App\Services\Fulfillment\OrderSyncService;
use App\Services\Shopify\ShopifyOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncRefundToMarketplace implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public int $backoff = 120;

    public function __construct(protected ReturnRequest $returnRequest)
    {
        $this->onQueue('fulfillment');
    }

    public function handle(): void
    {
        $order = $this->returnRequest->order;

        if (! $order || ! $order->credential_id) {
            return;
        }

        $result = match ($order->marketplace) {
            'ebay' => $this->syncToEbay($order),
            'shopify' => $this->syncToShopify($order),
            'amazon' => $this->syncToAmazon($order),
            default => false,
        };

        if (! $result) {
            Log::warning('Failed to sync refund to marketplace', [
                'return_id' => $this->returnRequest->id,
                'order_id' => $order->id,
                'marketplace' => $order->marketplace,
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release(300);
            }
        }
    }

    protected function syncToEbay($order): bool
    {
        try {
            $syncService = new OrderSyncService($order->credential);

            $response = $syncService->issueRefund($order, $this->returnRequest->refund_amount);

            return ! empty($response);
        } catch (\Exception $e) {
            Log::error('Failed to sync refund to eBay', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function syncToShopify($order): bool
    {
        try {
            $orderService = new ShopifyOrderService($order->credential);
            $shopifyOrderId = (int) $order->marketplace_order_id;

            // Get transactions to find the parent transaction
            $transactions = $orderService->getTransactions($shopifyOrderId);
            $parentTransactionId = null;

            foreach ($transactions as $transaction) {
                if ($transaction['kind'] === 'sale' && $transaction['status'] === 'success') {
                    $parentTransactionId = $transaction['id'];
                    break;
                }
            }

            $refundData = [
                'notify' => true,
                'note' => 'Refund for return RMA: '.$this->returnRequest->rma_number,
            ];

            if ($parentTransactionId && $this->returnRequest->refund_amount) {
                $refundData['transactions'] = [
                    [
                        'parent_id' => $parentTransactionId,
                        'amount' => $this->returnRequest->refund_amount,
                        'kind' => 'refund',
                    ],
                ];
            }

            $result = $orderService->createRefund($shopifyOrderId, $refundData);

            return ! empty($result);
        } catch (\Exception $e) {
            Log::error('Failed to sync refund to Shopify', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function syncToAmazon($order): bool
    {
        try {
            $orderService = new AmazonOrderService($order->credential);

            $result = $orderService->authorizeReturn($order->marketplace_order_id, [
                'returnAuthorizationStatus' => 'APPROVED',
                'returnItem' => [
                    'returnReasonCode' => $this->returnRequest->reason ?? 'CUSTOMER_RETURN',
                    'returnComment' => $this->returnRequest->reason_details ?? '',
                ],
            ]);

            return ! empty($result);
        } catch (\Exception $e) {
            Log::error('Failed to sync refund to Amazon', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
