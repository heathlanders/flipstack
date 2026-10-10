<?php

namespace App\Services\Fulfillment;

use App\Jobs\Inventory\PushInventoryToMarketplaces;
use App\Models\Catalog\Product;
use App\Models\Fulfillment\Order;
use App\Models\Integration\IntegrationCredential;
use App\Models\Shopify\ShopifyListing;
use App\Services\Shopify\ShopifyFulfillmentService;
use App\Services\Shopify\ShopifyOrderService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShopifyOrderSyncService
{
    protected IntegrationCredential $credential;

    protected FulfillmentAuditService $auditService;

    protected ShopifyOrderService $orderService;

    protected ShopifyFulfillmentService $fulfillmentService;

    public function __construct(IntegrationCredential $credential)
    {
        $this->credential = $credential;
        $this->auditService = new FulfillmentAuditService;
        $this->orderService = new ShopifyOrderService($credential);
        $this->fulfillmentService = new ShopifyFulfillmentService($credential);
    }

    public function syncOrders(?Carbon $since = null): array
    {
        $since = $since ?? now()->subHours(2);
        $imported = 0;
        $updated = 0;
        $errors = [];

        try {
            $orders = $this->orderService->getOrders([
                'status' => 'any',
                'created_at_min' => $since->toIso8601String(),
                'financial_status' => 'paid',
            ]);

            foreach ($orders as $shopifyOrder) {
                try {
                    $result = $this->importOrder($shopifyOrder);
                    if ($result['created']) {
                        $imported++;
                    } else {
                        $updated++;
                    }
                } catch (Exception $e) {
                    $errors[] = [
                        'order_id' => $shopifyOrder['id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Failed to import Shopify order', [
                        'order_id' => $shopifyOrder['id'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (Exception $e) {
            Log::error('Shopify order sync API error', ['error' => $e->getMessage()]);
            $errors[] = ['api_error' => $e->getMessage()];
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
            'total_fetched' => $imported + $updated + count($errors),
        ];
    }

    public function importOrder(array $shopifyOrder): array
    {
        $marketplaceOrderId = (string) ($shopifyOrder['id'] ?? '');
        $isNew = false;

        $order = DB::transaction(function () use ($shopifyOrder, $marketplaceOrderId, &$isNew) {
            $existing = Order::withoutGlobalScopes()
                ->where('marketplace_order_id', $marketplaceOrderId)
                ->first();

            $orderData = $this->transformOrderData($shopifyOrder);

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

            $this->syncOrderItems($order, $shopifyOrder['line_items'] ?? []);

            $order->update([
                'items_count' => $order->items()->count(),
                'last_synced_at' => now(),
            ]);

            return $order;
        });

        if ($isNew) {
            $this->matchOrderItemsToProducts($order);
            $this->reserveInventoryForOrder($order);
        }

        return ['order' => $order, 'created' => $isNew];
    }

    public function matchOrderItemsToProducts(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->product_id) {
                continue;
            }

            // Match by Shopify variant ID
            if ($item->marketplace_item_id) {
                $listing = ShopifyListing::where('shopify_variant_id', $item->marketplace_item_id)
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

            // Match by SKU
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
                Log::error('Failed to reserve inventory for Shopify order item', [
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
            // Get fulfillment orders for this Shopify order
            $shopifyOrderId = (int) $order->marketplace_order_id;
            $fulfillmentOrders = $this->fulfillmentService->getFulfillmentOrders($shopifyOrderId);

            if (empty($fulfillmentOrders)) {
                Log::warning('No fulfillment orders found for Shopify order', [
                    'order_id' => $order->id,
                    'shopify_order_id' => $shopifyOrderId,
                ]);

                return false;
            }

            // Build line items for fulfillment
            $lineItemsByFulfillmentOrder = [];
            foreach ($fulfillmentOrders as $fo) {
                if (in_array($fo['status'] ?? '', ['closed', 'cancelled'])) {
                    continue;
                }
                foreach ($fo['line_items'] ?? [] as $lineItem) {
                    $lineItemsByFulfillmentOrder[] = [
                        'fulfillment_order_id' => $fo['id'],
                        'fulfillment_order_line_items' => [
                            [
                                'id' => $lineItem['id'],
                                'quantity' => $lineItem['quantity'],
                            ],
                        ],
                    ];
                }
            }

            $result = $this->fulfillmentService->createFulfillment([
                'line_items_by_fulfillment_order' => $lineItemsByFulfillmentOrder,
                'tracking_info' => [
                    'number' => $shipment->tracking_number,
                    'company' => $this->mapCarrierName($shipment->carrier),
                ],
                'notify_customer' => true,
            ]);

            if (! empty($result)) {
                $shipment->update([
                    'tracking_uploaded_to_marketplace' => true,
                    'tracking_uploaded_at' => now(),
                ]);

                return true;
            }

            return false;
        } catch (Exception $e) {
            Log::error('Failed to upload tracking to Shopify', [
                'order_id' => $order->id,
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function handleOrderCancellation(Order $order): void
    {
        $order->update([
            'status' => Order::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $this->restoreInventoryForOrder($order);
    }

    protected function restoreInventoryForOrder(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            if (! $item->product_id || ! $item->quantity) {
                continue;
            }

            $product = Product::find($item->product_id);

            if (! $product) {
                continue;
            }

            $product->incrementAvailableQuantity((int) $item->quantity);

            PushInventoryToMarketplaces::dispatch($product->id);

            Log::info('Inventory restored on Shopify order cancellation', [
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity_restored' => $item->quantity,
            ]);
        }
    }

    public function handleOrderUpdate(Order $order, array $shopifyOrder): void
    {
        $shippingAddress = $shopifyOrder['shipping_address'] ?? [];

        $updateData = array_filter([
            'shipping_address_name' => trim(($shippingAddress['first_name'] ?? '').' '.($shippingAddress['last_name'] ?? '')) ?: null,
            'shipping_address_line1' => $shippingAddress['address1'] ?? null,
            'shipping_address_line2' => $shippingAddress['address2'] ?? null,
            'shipping_city' => $shippingAddress['city'] ?? null,
            'shipping_state' => $shippingAddress['province_code'] ?? null,
            'shipping_postal_code' => $shippingAddress['zip'] ?? null,
            'shipping_country' => $shippingAddress['country_code'] ?? null,
            'buyer_phone' => $shippingAddress['phone'] ?? null,
        ], fn ($v) => $v !== null);

        if (! empty($updateData)) {
            $updateData['last_synced_at'] = now();
            $updateData['marketplace_data'] = $shopifyOrder;
            $order->update($updateData);
        }
    }

    protected function transformOrderData(array $shopifyOrder): array
    {
        $shippingAddress = $shopifyOrder['shipping_address'] ?? [];
        $customer = $shopifyOrder['customer'] ?? [];
        $shippingLines = $shopifyOrder['shipping_lines'] ?? [];

        $shippingCost = 0;
        foreach ($shippingLines as $line) {
            $shippingCost += (float) ($line['price'] ?? 0);
        }

        $taxAmount = 0;
        foreach ($shopifyOrder['tax_lines'] ?? [] as $taxLine) {
            $taxAmount += (float) ($taxLine['price'] ?? 0);
        }

        return [
            'marketplace' => 'shopify',
            'marketplace_order_id' => (string) $shopifyOrder['id'],
            'marketplace_status' => $shopifyOrder['fulfillment_status'] ?? 'unfulfilled',
            'status' => $this->mapShopifyStatus($shopifyOrder),
            'buyer_username' => $customer['email'] ?? 'unknown',
            'buyer_name' => trim(($shippingAddress['first_name'] ?? $customer['first_name'] ?? '').' '.($shippingAddress['last_name'] ?? $customer['last_name'] ?? '')),
            'buyer_email' => $customer['email'] ?? $shopifyOrder['email'] ?? null,
            'buyer_phone' => $shippingAddress['phone'] ?? $customer['phone'] ?? null,
            'shipping_address_name' => $shippingAddress['name'] ?? '',
            'shipping_address_line1' => $shippingAddress['address1'] ?? '',
            'shipping_address_line2' => $shippingAddress['address2'] ?? null,
            'shipping_city' => $shippingAddress['city'] ?? '',
            'shipping_state' => $shippingAddress['province_code'] ?? '',
            'shipping_postal_code' => $shippingAddress['zip'] ?? '',
            'shipping_country' => $shippingAddress['country_code'] ?? 'US',
            'subtotal' => (float) ($shopifyOrder['subtotal_price'] ?? 0),
            'shipping_cost' => $shippingCost,
            'tax_amount' => $taxAmount,
            'total_amount' => (float) ($shopifyOrder['total_price'] ?? 0),
            'marketplace_fees' => 0,
            'currency' => $shopifyOrder['currency'] ?? 'USD',
            'paid_at' => ! empty($shopifyOrder['processed_at']) ? Carbon::parse($shopifyOrder['processed_at']) : null,
            'ship_by_date' => $this->calculateShipByDate($shopifyOrder),
            'marketplace_data' => $shopifyOrder,
        ];
    }

    protected function syncOrderItems(Order $order, array $lineItems): void
    {
        $existingItemIds = $order->items()->pluck('marketplace_item_id')->toArray();

        foreach ($lineItems as $lineItem) {
            $variantId = (string) ($lineItem['variant_id'] ?? null);

            $itemData = [
                'marketplace_item_id' => $variantId,
                'sku' => $lineItem['sku'] ?? null,
                'title' => $lineItem['title'] ?? 'Unknown Item',
                'quantity' => $lineItem['quantity'] ?? 1,
                'unit_price' => (float) ($lineItem['price'] ?? 0),
                'total_price' => (float) ($lineItem['price'] ?? 0) * ($lineItem['quantity'] ?? 1),
                'tax_amount' => $this->extractLineItemTax($lineItem),
                'shipping_cost' => 0,
                'condition' => null,
                'image_url' => null,
                'marketplace_data' => $lineItem,
            ];

            if ($variantId && in_array($variantId, $existingItemIds)) {
                $order->items()->where('marketplace_item_id', $variantId)->update($itemData);
            } else {
                $order->items()->create($itemData);
            }
        }
    }

    protected function mapShopifyStatus(array $shopifyOrder): string
    {
        $fulfillmentStatus = $shopifyOrder['fulfillment_status'] ?? null;
        $cancelledAt = $shopifyOrder['cancelled_at'] ?? null;

        if ($cancelledAt) {
            return Order::STATUS_CANCELLED;
        }

        return match ($fulfillmentStatus) {
            null, 'unfulfilled' => Order::STATUS_PENDING,
            'partial' => Order::STATUS_ASSIGNED,
            'fulfilled' => Order::STATUS_SHIPPED,
            default => Order::STATUS_PENDING,
        };
    }

    protected function mapCarrierName(string $carrier): string
    {
        return match (strtoupper($carrier)) {
            'USPS' => 'USPS',
            'UPS' => 'UPS',
            'FEDEX' => 'FedEx',
            'DHL', 'DHL_EXPRESS' => 'DHL Express',
            default => $carrier,
        };
    }

    protected function extractLineItemTax(array $lineItem): float
    {
        $tax = 0;
        foreach ($lineItem['tax_lines'] ?? [] as $taxLine) {
            $tax += (float) ($taxLine['price'] ?? 0);
        }

        return $tax;
    }

    protected function calculateShipByDate(array $shopifyOrder): ?Carbon
    {
        $createdAt = $shopifyOrder['created_at'] ?? null;
        if ($createdAt) {
            return Carbon::parse($createdAt)->addWeekdays(2);
        }

        return null;
    }
}
