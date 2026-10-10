<?php

namespace App\Services\Fulfillment;

use App\Jobs\Inventory\PushInventoryToMarketplaces;
use App\Models\Amazon\AmazonListing;
use App\Models\Catalog\Product;
use App\Models\Fulfillment\Order;
use App\Models\Integration\IntegrationCredential;
use App\Services\Amazon\AmazonFeedService;
use App\Services\Amazon\AmazonOrderService;
use App\Services\Customer\CustomerMatchingService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AmazonOrderSyncService
{
    protected IntegrationCredential $credential;

    protected FulfillmentAuditService $auditService;

    protected AmazonOrderService $orderService;

    protected AmazonFeedService $feedService;

    public function __construct(IntegrationCredential $credential)
    {
        $this->credential = $credential;
        $this->auditService = new FulfillmentAuditService;
        $this->orderService = new AmazonOrderService($credential);
        $this->feedService = new AmazonFeedService($credential);
    }

    public function syncOrders(?Carbon $since = null): array
    {
        $since = $since ?? now()->subHours(2);
        $imported = 0;
        $updated = 0;
        $errors = [];

        try {
            $orders = $this->orderService->getOrders(
                $since->toIso8601String(),
                ['Unshipped', 'PartiallyShipped']
            );

            foreach ($orders as $amazonOrder) {
                try {
                    $orderId = $amazonOrder['AmazonOrderId'] ?? null;
                    if (! $orderId) {
                        continue;
                    }

                    // Fetch order items
                    $orderItems = $this->orderService->getOrderItems($orderId);
                    $amazonOrder['OrderItems'] = $orderItems;

                    $result = $this->importOrder($amazonOrder);
                    if ($result['created']) {
                        $imported++;
                    } else {
                        $updated++;
                    }
                } catch (Exception $e) {
                    $errors[] = [
                        'order_id' => $amazonOrder['AmazonOrderId'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Failed to import Amazon order', [
                        'order_id' => $amazonOrder['AmazonOrderId'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (Exception $e) {
            Log::error('Amazon order sync API error', ['error' => $e->getMessage()]);
            $errors[] = ['api_error' => $e->getMessage()];
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
            'total_fetched' => $imported + $updated + count($errors),
        ];
    }

    public function importOrder(array $amazonOrder): array
    {
        $marketplaceOrderId = $amazonOrder['AmazonOrderId'];
        $isNew = false;

        $order = DB::transaction(function () use ($amazonOrder, $marketplaceOrderId, &$isNew) {
            $existing = Order::withoutGlobalScopes()
                ->where('marketplace_order_id', $marketplaceOrderId)
                ->first();

            $orderData = $this->transformOrderData($amazonOrder);

            if ($existing) {
                $existing->update($orderData);
                $order = $existing;
            } else {
                $isNew = true;
                $order = Order::create(array_merge($orderData, [
                    'organization_id' => $this->credential->organization_id,
                    'team_id' => $this->credential->team_id,
                    'credential_id' => $this->credential->id,
                ]));
            }

            $this->syncOrderItems($order, $amazonOrder['OrderItems'] ?? []);

            $order->update([
                'items_count' => $order->items()->count(),
                'last_synced_at' => now(),
            ]);

            return $order;
        });

        if ($isNew) {
            $this->matchOrderItemsToProducts($order);
            $this->reserveInventoryForOrder($order);
            $this->acknowledgeOrder($order);
            $this->matchCustomer($order);
        }

        return ['order' => $order, 'created' => $isNew];
    }

    protected function matchCustomer(Order $order): void
    {
        try {
            // Amazon BuyerEmail is anonymized (xxx@marketplace.amazon.com).
            // Each new order from the same buyer gets a different anon email.
            // CustomerMatchingService strategies fall through to name+postal,
            // which is what we want for repeat-buyer detection.
            app(CustomerMatchingService::class)->findOrCreateFromOrder($order);
        } catch (Exception $e) {
            Log::warning('AmazonOrderSyncService: customer match failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function matchOrderItemsToProducts(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->product_id) {
                continue;
            }

            // Match by ASIN via AmazonListing
            $asin = $item->marketplace_data['ASIN'] ?? null;
            if ($asin) {
                $listing = AmazonListing::where('asin', $asin)
                    ->where('integration_credential_id', $this->credential->id)
                    ->first();

                if ($listing && $listing->product_id) {
                    $item->update([
                        'product_id' => $listing->product_id,
                        'supplier_id' => $listing->product?->supplier_id ?? null,
                    ]);

                    continue;
                }
            }

            // Match by Amazon SKU via AmazonListing
            $sellerSku = $item->marketplace_data['SellerSKU'] ?? $item->sku;
            if ($sellerSku) {
                $listing = AmazonListing::where('amazon_sku', $sellerSku)
                    ->where('integration_credential_id', $this->credential->id)
                    ->first();

                if ($listing && $listing->product_id) {
                    $item->update([
                        'product_id' => $listing->product_id,
                        'supplier_id' => $listing->product?->supplier_id ?? null,
                    ]);

                    continue;
                }
            }

            // Match by SKU on Product
            if ($item->sku) {
                $product = Product::where('sku', $item->sku)
                    ->where('organization_id', $order->organization_id)
                    ->first();

                if ($product) {
                    $item->update([
                        'product_id' => $product->id,
                        'supplier_id' => $product->supplier_id ?? null,
                    ]);
                }
            }
        }
    }

    public function reserveInventoryForOrder(Order $order): void
    {
        $order->load('items');

        $affectedProductIds = [];

        foreach ($order->items as $item) {
            if (! $item->product_id || ! $item->quantity) {
                continue;
            }

            try {
                $product = Product::find($item->product_id);

                if (! $product) {
                    Log::warning('Cannot reserve inventory: product not found', [
                        'order_id' => $order->id,
                        'product_id' => $item->product_id,
                    ]);

                    continue;
                }

                $product->decrementAvailableQuantity((int) $item->quantity);

                $affectedProductIds[] = $product->id;
            } catch (Exception $e) {
                Log::error('Failed to reserve inventory for Amazon order item', [
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach (array_unique($affectedProductIds) as $productId) {
            PushInventoryToMarketplaces::dispatch($productId);
        }
    }

    public function uploadTracking(Order $order): bool
    {
        $shipment = $order->shipments()
            ->whereNotNull('tracking_number')
            ->where('tracking_uploaded_to_marketplace', false)
            ->first();

        if (! $shipment) {
            return false;
        }

        try {
            $feedData = [
                'messages' => [
                    [
                        'orderFulfillment' => [
                            'amazonOrderId' => $order->marketplace_order_id,
                            'fulfillmentDate' => now()->toIso8601String(),
                            'shippingMethod' => $this->mapCarrierCode($shipment->carrier),
                            'carrierName' => $shipment->carrier,
                            'trackingNumber' => $shipment->tracking_number,
                            'items' => $order->items->map(function ($item) {
                                return [
                                    'amazonOrderItemCode' => $item->marketplace_item_id,
                                    'quantity' => $item->quantity,
                                ];
                            })->filter(fn ($item) => $item['amazonOrderItemCode'])->values()->toArray(),
                        ],
                    ],
                ],
            ];

            $feed = $this->feedService->submitFeed(
                'POST_ORDER_FULFILLMENT_DATA',
                $feedData,
                $this->credential->team_id
            );

            if ($feed) {
                $shipment->update([
                    'tracking_uploaded_to_marketplace' => true,
                    'tracking_uploaded_at' => now(),
                ]);

                return true;
            }

            return false;
        } catch (Exception $e) {
            Log::error('Failed to upload tracking to Amazon', [
                'order_id' => $order->id,
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function acknowledgeOrder(Order $order): void
    {
        try {
            $this->orderService->acknowledgeOrder($order->marketplace_order_id);
        } catch (Exception $e) {
            Log::warning('Failed to acknowledge Amazon order', [
                'order_id' => $order->id,
                'amazon_order_id' => $order->marketplace_order_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function transformOrderData(array $amazonOrder): array
    {
        $shippingAddress = $amazonOrder['ShippingAddress'] ?? [];
        $orderTotal = $amazonOrder['OrderTotal'] ?? [];

        return [
            'marketplace' => 'amazon',
            'marketplace_order_id' => $amazonOrder['AmazonOrderId'],
            'marketplace_status' => $amazonOrder['OrderStatus'] ?? null,
            'status' => $this->mapAmazonStatus($amazonOrder['OrderStatus'] ?? ''),
            'buyer_username' => $amazonOrder['BuyerInfo']['BuyerEmail'] ?? 'unknown',
            'buyer_name' => $shippingAddress['Name'] ?? 'Unknown',
            'buyer_email' => $amazonOrder['BuyerInfo']['BuyerEmail'] ?? null,
            'buyer_phone' => $shippingAddress['Phone'] ?? null,
            'shipping_address_name' => $shippingAddress['Name'] ?? '',
            'shipping_address_line1' => $shippingAddress['AddressLine1'] ?? '',
            'shipping_address_line2' => $shippingAddress['AddressLine2'] ?? null,
            'shipping_city' => $shippingAddress['City'] ?? '',
            'shipping_state' => $shippingAddress['StateOrRegion'] ?? '',
            'shipping_postal_code' => $shippingAddress['PostalCode'] ?? '',
            'shipping_country' => $shippingAddress['CountryCode'] ?? 'US',
            'subtotal' => (float) ($orderTotal['Amount'] ?? 0),
            'shipping_cost' => 0,
            'tax_amount' => 0,
            'total_amount' => (float) ($orderTotal['Amount'] ?? 0),
            'marketplace_fees' => 0,
            'currency' => $orderTotal['CurrencyCode'] ?? 'USD',
            'paid_at' => ! empty($amazonOrder['PurchaseDate']) ? Carbon::parse($amazonOrder['PurchaseDate']) : null,
            'ship_by_date' => ! empty($amazonOrder['LatestShipDate']) ? Carbon::parse($amazonOrder['LatestShipDate']) : null,
            'marketplace_data' => $amazonOrder,
        ];
    }

    protected function syncOrderItems(Order $order, array $orderItems): void
    {
        $existingItemIds = $order->items()->pluck('marketplace_item_id')->toArray();

        foreach ($orderItems as $item) {
            $orderItemId = $item['OrderItemId'] ?? null;
            $itemPrice = $item['ItemPrice'] ?? [];
            $itemTax = $item['ItemTax'] ?? [];
            $shippingPrice = $item['ShippingPrice'] ?? [];

            $itemData = [
                'marketplace_item_id' => $orderItemId,
                'sku' => $item['SellerSKU'] ?? null,
                'title' => $item['Title'] ?? 'Unknown Item',
                'quantity' => (int) ($item['QuantityOrdered'] ?? 1),
                'unit_price' => (float) ($itemPrice['Amount'] ?? 0) / max((int) ($item['QuantityOrdered'] ?? 1), 1),
                'total_price' => (float) ($itemPrice['Amount'] ?? 0),
                'tax_amount' => (float) ($itemTax['Amount'] ?? 0),
                'shipping_cost' => (float) ($shippingPrice['Amount'] ?? 0),
                'condition' => $item['ConditionId'] ?? null,
                'image_url' => null,
                'marketplace_data' => $item,
            ];

            if ($orderItemId && in_array($orderItemId, $existingItemIds)) {
                $order->items()->where('marketplace_item_id', $orderItemId)->update($itemData);
            } else {
                $order->items()->create($itemData);
            }
        }
    }

    protected function mapAmazonStatus(string $amazonStatus): string
    {
        return match ($amazonStatus) {
            'Unshipped', 'Pending' => Order::STATUS_PENDING,
            'PartiallyShipped' => Order::STATUS_ASSIGNED,
            'Shipped' => Order::STATUS_SHIPPED,
            'Canceled', 'Cancelled' => Order::STATUS_CANCELLED,
            default => Order::STATUS_PENDING,
        };
    }

    protected function mapCarrierCode(string $carrier): string
    {
        return match (strtoupper($carrier)) {
            'USPS' => 'USPS',
            'UPS' => 'UPS',
            'FEDEX' => 'FedEx',
            'DHL', 'DHL_EXPRESS' => 'DHL',
            default => 'Other',
        };
    }
}
