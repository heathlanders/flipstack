<?php

namespace App\Services\Fulfillment;

use App\Jobs\Inventory\PushInventoryToMarketplaces;
use App\Models\Catalog\Product;
use App\Models\Ebay\EbayListing;
use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\Shipment;
use App\Models\Fulfillment\ShipmentItem;
use App\Models\Integration\IntegrationCredential;
use App\Models\Integration\OAuthToken;
use App\Models\Inventory\InventoryLocation;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrderSyncService
{
    protected IntegrationCredential $credential;

    protected ?OAuthToken $token = null;

    protected string $baseUrl;

    protected FulfillmentAuditService $auditService;

    public function __construct(IntegrationCredential $credential)
    {
        $this->credential = $credential;
        $this->baseUrl = $credential->environment === 'sandbox'
            ? 'https://api.sandbox.ebay.com'
            : 'https://api.ebay.com';
        $this->auditService = new FulfillmentAuditService;
    }

    public function syncOrders(?Carbon $since = null): array
    {
        $since = $since ?? now()->subHours(2);
        $imported = 0;
        $updated = 0;
        $errors = [];
        $offset = 0;
        $limit = 50;

        do {
            try {
                $response = $this->getEbayOrders($since, $offset, $limit);
                $orders = $response['orders'] ?? [];
                $total = $response['total'] ?? 0;

                foreach ($orders as $ebayOrder) {
                    try {
                        $result = $this->importOrder($ebayOrder);
                        if ($result['created']) {
                            $imported++;
                        } else {
                            $updated++;
                        }
                    } catch (Exception $e) {
                        $errors[] = [
                            'order_id' => $ebayOrder['orderId'] ?? 'unknown',
                            'error' => $e->getMessage(),
                        ];
                        Log::error('Failed to import eBay order', [
                            'order_id' => $ebayOrder['orderId'] ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $offset += $limit;
            } catch (Exception $e) {
                Log::error('eBay order sync API error', ['error' => $e->getMessage()]);
                $errors[] = ['api_error' => $e->getMessage()];
                break;
            }
        } while ($offset < ($total ?? 0));

        return [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
            'total_fetched' => $offset,
        ];
    }

    public function syncSingleOrder(string $orderId): Order
    {
        $response = $this->request('GET', "/sell/fulfillment/v1/order/{$orderId}");
        $result = $this->importOrder($response);

        return $result['order'];
    }

    public function importOrder(array $ebayOrder): array
    {
        $marketplaceOrderId = $ebayOrder['orderId'];
        $isNew = false;

        $order = DB::transaction(function () use ($ebayOrder, $marketplaceOrderId, &$isNew) {
            $existing = Order::withoutGlobalScopes()
                ->where('marketplace_order_id', $marketplaceOrderId)
                ->first();

            $orderData = $this->transformOrderData($ebayOrder);

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

            $this->syncOrderItems($order, $ebayOrder['lineItems'] ?? []);

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

            // Match by eBay listing (scoped to the same eBay credential as the order)
            if ($item->marketplace_item_id) {
                $listing = EbayListing::where('ebay_listing_id', $item->marketplace_item_id)
                    ->where('integration_credential_id', $order->credential_id)
                    ->first();

                if ($listing) {
                    $item->update([
                        'product_id' => $listing->product_id,
                        'ebay_listing_id' => $listing->id,
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
                Log::error('Failed to reserve inventory for order item', [
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'error' => $e->getMessage(),
                ]);
                // Never fail the order import due to reservation error
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
            $response = $this->request('POST', "/sell/fulfillment/v1/order/{$order->marketplace_order_id}/shipping_fulfillment", [
                'lineItems' => $order->items->map(function ($item) {
                    return [
                        'lineItemId' => $item->marketplace_item_id,
                        'quantity' => $item->quantity,
                    ];
                })->filter(fn ($item) => $item['lineItemId'])->values()->toArray(),
                'shippingCarrierCode' => $this->mapCarrierCode($shipment->carrier),
                'trackingNumber' => $shipment->tracking_number,
            ]);

            $shipment->update([
                'tracking_uploaded_to_marketplace' => true,
                'tracking_uploaded_at' => now(),
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to upload tracking to eBay', [
                'order_id' => $order->id,
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Pull tracking that the seller created on eBay (e.g. when they bought a label
     * directly through eBay). For each fulfillment eBay reports that we don't have
     * locally, create a Shipment + ShipmentItems with tracking_uploaded_to_marketplace=true
     * and advance the Order to shipped if every item is covered.
     */
    public function pullEbayTracking(Order $order): bool
    {
        try {
            $response = $this->request('GET', "/sell/fulfillment/v1/order/{$order->marketplace_order_id}/shipping_fulfillment");
        } catch (Exception $e) {
            Log::warning('Failed to pull eBay shipping fulfillments', [
                'order_id' => $order->id,
                'marketplace_order_id' => $order->marketplace_order_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        $fulfillments = $response['fulfillments'] ?? [];

        if (empty($fulfillments)) {
            return false;
        }

        $order->loadMissing(['items', 'shipments']);

        $existingTracking = $order->shipments
            ->pluck('tracking_number')
            ->filter()
            ->map(fn ($t) => strtoupper(trim($t)))
            ->all();

        $created = false;

        foreach ($fulfillments as $fulfillment) {
            $tracking = $fulfillment['shipmentTrackingNumber'] ?? null;

            if (! $tracking) {
                continue;
            }

            if (in_array(strtoupper(trim($tracking)), $existingTracking, true)) {
                continue;
            }

            $shipment = $this->createShipmentFromEbayFulfillment($order, $fulfillment);

            if ($shipment) {
                $created = true;
                $existingTracking[] = strtoupper(trim($shipment->tracking_number));
            }
        }

        if ($created) {
            $this->maybeMarkOrderShipped($order);
        }

        return $created;
    }

    protected function createShipmentFromEbayFulfillment(Order $order, array $fulfillment): ?Shipment
    {
        $location = $this->resolveShipFromLocation($order);

        if (! $location) {
            Log::warning('Cannot import eBay tracking: no inventory location available', [
                'order_id' => $order->id,
            ]);

            return null;
        }

        $shippedAt = ! empty($fulfillment['shippedDate']) ? Carbon::parse($fulfillment['shippedDate']) : now();

        $shipment = Shipment::create([
            'organization_id' => $order->organization_id,
            'order_id' => $order->id,
            'location_id' => $location->id,
            'shipment_number' => Shipment::generateShipmentNumber(),
            'status' => Shipment::STATUS_SHIPPED,
            'shipping_provider' => 'ebay',
            'carrier' => $this->mapCarrierCode($fulfillment['shippingCarrierCode'] ?? ''),
            'tracking_number' => $fulfillment['shipmentTrackingNumber'],
            'ship_from_name' => $location->name ?: ($order->organization->name ?? 'Sender'),
            'ship_from_address_line1' => $location->address_line1 ?: 'N/A',
            'ship_from_city' => $location->city ?: 'N/A',
            'ship_from_state' => $location->state ?: 'N/A',
            'ship_from_postal_code' => $location->postal_code ?: '00000',
            'ship_from_country' => $location->country ?: 'US',
            'tracking_uploaded_to_marketplace' => true,
            'tracking_uploaded_at' => now(),
            'shipped_at' => $shippedAt,
            'marketplace_data' => $fulfillment,
        ]);

        $this->createShipmentItemsFromEbayFulfillment($order, $shipment, $fulfillment);

        Log::info('Imported tracking from eBay-purchased label', [
            'order_id' => $order->id,
            'shipment_id' => $shipment->id,
            'tracking_number' => $shipment->tracking_number,
            'carrier' => $shipment->carrier,
        ]);

        return $shipment;
    }

    protected function createShipmentItemsFromEbayFulfillment(Order $order, Shipment $shipment, array $fulfillment): void
    {
        $reportedLineItems = collect($fulfillment['lineItems'] ?? []);

        if ($reportedLineItems->isEmpty()) {
            // No line-item detail from eBay — assume the shipment covers every item on the order.
            foreach ($order->items as $item) {
                ShipmentItem::create([
                    'shipment_id' => $shipment->id,
                    'order_item_id' => $item->id,
                    'quantity' => $item->quantity,
                ]);
            }

            return;
        }

        foreach ($reportedLineItems as $reported) {
            $lineItemId = $reported['lineItemId'] ?? null;

            if (! $lineItemId) {
                continue;
            }

            $orderItem = $order->items->firstWhere('marketplace_item_id', $lineItemId);

            if (! $orderItem) {
                continue;
            }

            ShipmentItem::create([
                'shipment_id' => $shipment->id,
                'order_item_id' => $orderItem->id,
                'quantity' => (int) ($reported['quantity'] ?? $orderItem->quantity),
            ]);
        }
    }

    protected function resolveShipFromLocation(Order $order): ?InventoryLocation
    {
        if ($order->location_id) {
            $location = InventoryLocation::find($order->location_id);
            if ($location) {
                return $location;
            }
        }

        return InventoryLocation::where('organization_id', $order->organization_id)
            ->where('is_active', true)
            ->orderBy('created_at')
            ->first();
    }

    protected function maybeMarkOrderShipped(Order $order): void
    {
        $order->load(['items', 'shipments.shipmentItems']);

        $shippedQuantityByItem = [];
        foreach ($order->shipments as $shipment) {
            foreach ($shipment->shipmentItems as $shipItem) {
                $shippedQuantityByItem[$shipItem->order_item_id] =
                    ($shippedQuantityByItem[$shipItem->order_item_id] ?? 0) + (int) $shipItem->quantity;
            }
        }

        $allCovered = $order->items->every(function ($item) use ($shippedQuantityByItem) {
            return ($shippedQuantityByItem[$item->id] ?? 0) >= (int) $item->quantity;
        });

        if (! $allCovered) {
            return;
        }

        if (in_array($order->status, [Order::STATUS_SHIPPED, Order::STATUS_IN_TRANSIT, Order::STATUS_DELIVERED, Order::STATUS_CANCELLED], true)) {
            return;
        }

        $order->update([
            'status' => Order::STATUS_SHIPPED,
            'shipped_at' => $order->shipped_at ?? now(),
        ]);
    }

    public function issueRefund(Order $order, ?float $amount = null): array
    {
        $refundAmount = $amount ?? (float) $order->total_amount;

        try {
            return $this->request('POST', "/sell/fulfillment/v1/order/{$order->marketplace_order_id}/issue_refund", [
                'reasonForRefund' => 'ITEM_RETURNED',
                'orderLevelRefundAmount' => [
                    'value' => number_format($refundAmount, 2, '.', ''),
                    'currency' => $order->currency ?? 'USD',
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Failed to issue eBay refund', [
                'order_id' => $order->id,
                'amount' => $refundAmount,
                'error' => $e->getMessage(),
            ]);

            throw $e;
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

            Log::info('Inventory restored on order cancellation', [
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity_restored' => $item->quantity,
            ]);
        }
    }

    protected function getEbayOrders(Carbon $since, int $offset, int $limit): array
    {
        $filter = "creationdate:[{$since->toISOString()}..],orderfulfillmentstatus:{NOT_STARTED|IN_PROGRESS}";

        return $this->request('GET', '/sell/fulfillment/v1/order', [
            'filter' => $filter,
            'offset' => $offset,
            'limit' => $limit,
        ]);
    }

    protected function transformOrderData(array $ebayOrder): array
    {
        $pricingSummary = $ebayOrder['pricingSummary'] ?? [];
        $shippingAddress = $ebayOrder['fulfillmentStartInstructions'][0]['shippingStep']['shipTo'] ?? [];
        $contactAddress = $shippingAddress['contactAddress'] ?? [];
        $buyer = $ebayOrder['buyer'] ?? [];
        $paymentSummary = $ebayOrder['paymentSummary'] ?? [];
        $payments = $paymentSummary['payments'] ?? [];

        $paidAt = null;
        if (! empty($payments)) {
            $paidAt = $payments[0]['paymentDate'] ?? null;
        }

        return [
            'marketplace' => 'ebay',
            'marketplace_order_id' => $ebayOrder['orderId'],
            'marketplace_status' => $ebayOrder['orderFulfillmentStatus'] ?? null,
            'status' => $this->mapEbayStatus($ebayOrder['orderFulfillmentStatus'] ?? ''),
            'buyer_username' => $buyer['username'] ?? 'unknown',
            'buyer_name' => ($shippingAddress['fullName'] ?? $buyer['username'] ?? 'Unknown'),
            'buyer_email' => $buyer['buyerRegistrationAddress']['email'] ?? null,
            'buyer_phone' => $shippingAddress['primaryPhone']['phoneNumber'] ?? null,
            'shipping_address_name' => $shippingAddress['fullName'] ?? '',
            'shipping_address_line1' => $contactAddress['addressLine1'] ?? '',
            'shipping_address_line2' => $contactAddress['addressLine2'] ?? null,
            'shipping_city' => $contactAddress['city'] ?? '',
            'shipping_state' => $contactAddress['stateOrProvince'] ?? '',
            'shipping_postal_code' => $contactAddress['postalCode'] ?? '',
            'shipping_country' => $contactAddress['countryCode'] ?? 'US',
            'subtotal' => $this->extractAmount($pricingSummary['priceSubtotal'] ?? null),
            'shipping_cost' => $this->extractAmount($pricingSummary['deliveryCost'] ?? null),
            'tax_amount' => $this->extractAmount($pricingSummary['tax'] ?? null),
            'total_amount' => $this->extractAmount($pricingSummary['total'] ?? null),
            'marketplace_fees' => $this->extractAmount($ebayOrder['totalFeeBasisAmount'] ?? null),
            'currency' => $pricingSummary['total']['currency'] ?? 'USD',
            'paid_at' => $paidAt ? Carbon::parse($paidAt) : null,
            'ship_by_date' => $this->calculateShipByDate($ebayOrder),
            'marketplace_data' => $ebayOrder,
        ];
    }

    protected function syncOrderItems(Order $order, array $lineItems): void
    {
        $existingItemIds = $order->items()->pluck('marketplace_item_id')->toArray();

        foreach ($lineItems as $lineItem) {
            $lineItemId = $lineItem['lineItemId'] ?? null;

            $itemData = [
                'marketplace_item_id' => $lineItemId,
                'sku' => $lineItem['sku'] ?? null,
                'title' => $lineItem['title'] ?? 'Unknown Item',
                'quantity' => $lineItem['quantity'] ?? 1,
                'unit_price' => $this->extractAmount($lineItem['lineItemCost'] ?? null),
                'total_price' => $this->extractAmount($lineItem['total'] ?? $lineItem['lineItemCost'] ?? null),
                'tax_amount' => $this->extractAmount($lineItem['tax'] ?? null),
                'shipping_cost' => $this->extractAmount($lineItem['deliveryCost']['shippingCost'] ?? null),
                'condition' => $lineItem['properties']['itemCondition'] ?? null,
                'image_url' => $lineItem['legacyItemId'] ? null : null,
                'marketplace_data' => $lineItem,
            ];

            if ($lineItemId && in_array($lineItemId, $existingItemIds)) {
                $order->items()->where('marketplace_item_id', $lineItemId)->update($itemData);
            } else {
                $order->items()->create($itemData);
            }
        }
    }

    protected function mapEbayStatus(string $ebayStatus): string
    {
        return match ($ebayStatus) {
            'NOT_STARTED' => Order::STATUS_PENDING,
            'IN_PROGRESS' => Order::STATUS_ASSIGNED,
            'FULFILLED' => Order::STATUS_SHIPPED,
            default => Order::STATUS_PENDING,
        };
    }

    protected function mapCarrierCode(string $carrier): string
    {
        return match (strtoupper($carrier)) {
            'USPS' => 'USPS',
            'UPS' => 'UPS',
            'FEDEX' => 'FedEx',
            'DHL' => 'DHL',
            default => $carrier,
        };
    }

    protected function extractAmount(?array $amount): float
    {
        return (float) ($amount['value'] ?? 0);
    }

    protected function calculateShipByDate(array $ebayOrder): ?Carbon
    {
        $fulfillmentInstructions = $ebayOrder['fulfillmentStartInstructions'] ?? [];
        if (! empty($fulfillmentInstructions[0]['maxEstimatedDeliveryDate'])) {
            return Carbon::parse($fulfillmentInstructions[0]['maxEstimatedDeliveryDate'])->subDays(3);
        }

        $creationDate = $ebayOrder['creationDate'] ?? null;
        if ($creationDate) {
            return Carbon::parse($creationDate)->addBusinessDays(1);
        }

        return null;
    }

    protected function getToken(): OAuthToken
    {
        if ($this->token) {
            return $this->token;
        }

        $this->token = OAuthToken::where('integration_credential_id', $this->credential->id)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $this->token) {
            throw new Exception('No valid OAuth token found for credential: '.$this->credential->id);
        }

        return $this->token;
    }

    protected function request(string $method, string $endpoint, array $data = []): array
    {
        $token = $this->getToken();
        $url = $this->baseUrl.$endpoint;

        $request = Http::withToken($token->decrypted_access_token)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-EBAY-C-MARKETPLACE-ID' => $this->credential->marketplace_id ?? 'EBAY_US',
            ])
            ->timeout(30);

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url, $data),
            'POST' => $request->post($url, $data),
            default => throw new Exception("Unsupported HTTP method: {$method}"),
        };

        if ($response->failed()) {
            $error = $response->json('errors.0.message') ?? $response->body();
            throw new Exception("eBay Fulfillment API error ({$response->status()}): {$error}");
        }

        return $response->json() ?? [];
    }
}
