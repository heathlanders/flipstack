<?php

namespace App\Services\Fulfillment;

use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\Shipment;
use App\Models\Fulfillment\ShippingEasySyncLog;
use App\Models\Fulfillment\ShippingRate;
use App\Models\Integration\IntegrationCredential;
use App\Models\Inventory\InventoryLocation;
use App\Models\Organization\Organization;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShippingEasyService
{
    protected Organization $organization;

    protected string $apiKey = '';

    protected string $apiSecret = '';

    protected ?string $storeApiKey = null;

    protected ?IntegrationCredential $credential = null;

    protected bool $available = false;

    protected string $baseUrl = 'https://app.shippingeasy.com/api';

    protected int $maxRetries = 3;

    protected array $retryDelays = [1, 2, 4];

    public function __construct(Organization $organization)
    {
        $this->organization = $organization;

        try {
            $this->loadCredentials();
        } catch (\Throwable $e) {
            Log::warning('ShippingEasy service unavailable', [
                'org_id' => $organization->id,
                'error' => $e->getMessage(),
            ]);
            $this->available = false;
        }
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    protected function ensureAvailable(): void
    {
        if (! $this->available) {
            throw new Exception('ShippingEasy integration not configured for this organization.');
        }
    }

    protected function loadCredentials(): void
    {
        $credential = IntegrationCredential::where('organization_id', $this->organization->id)
            ->where('platform', 'shippingeasy')
            ->where('is_active', true)
            ->first();

        if (! $credential) {
            throw new Exception('ShippingEasy integration not configured.');
        }

        $this->credential = $credential;
        $this->apiKey = $credential->decrypted_client_id;
        $this->apiSecret = $credential->decrypted_client_secret;
        $this->storeApiKey = $credential->config['store_api_key'] ?? null;
        $this->available = true;
    }

    /**
     * NOTE: POST /rates is not in the official SE API docs. Verify with real credentials.
     * If unavailable, rates must be obtained through SE's UI or order workflow.
     */
    public function getRates(Order $order, InventoryLocation $fromLocation): Collection
    {
        $this->ensureAvailable();

        $totalWeightOz = $order->items->sum('weight_oz') ?: ($fromLocation->default_weight_oz ?: 16);

        $response = $this->request('POST', '/rates', [
            'shipment' => [
                'from_address' => [
                    'postal_code' => $fromLocation->postal_code,
                    'country' => $fromLocation->country ?? 'US',
                ],
                'to_address' => [
                    'state' => $order->shipping_state,
                    'country' => $order->shipping_country ?? 'US',
                    'postal_code' => $order->shipping_postal_code,
                    'city' => $order->shipping_city,
                ],
                'weight' => [
                    'value' => $totalWeightOz,
                    'units' => 'ounces',
                ],
                'dimensions' => [
                    'length' => $fromLocation->default_length ?: 12,
                    'width' => $fromLocation->default_width ?: 9,
                    'height' => $fromLocation->default_height ?: 4,
                    'units' => 'inches',
                ],
                'confirmation' => 'none',
                'residential' => true,
            ],
        ], ShippingEasySyncLog::OP_GET_RATES);

        // Delete old rates for this order
        ShippingRate::where('order_id', $order->id)->delete();

        $rates = collect($response['rates'] ?? [])->map(function ($rate) use ($order) {
            return ShippingRate::create([
                'organization_id' => $order->organization_id,
                'order_id' => $order->id,
                'easypost_rate_id' => $rate['service_code'] ?? null,
                'carrier' => $rate['carrier'] ?? 'Unknown',
                'service' => $rate['service_name'] ?? $rate['service_code'] ?? 'Unknown',
                'rate' => $rate['total_cost'] ?? 0,
                'currency' => 'USD',
                'estimated_days' => $rate['estimated_days'] ?? null,
                'delivery_date' => null,
                'delivery_guarantee' => false,
                'expires_at' => now()->addHours(1),
                'metadata' => [
                    'provider' => 'shippingeasy',
                    'carrier_code' => $rate['carrier'] ?? null,
                    'service_code' => $rate['service_code'] ?? null,
                ],
            ]);
        });

        return $rates;
    }

    /**
     * Get rates for a package without requiring an Order model.
     * Used by IntakeShippingRateService for quote-time rate lookups.
     */
    public function getRatesForPackage(array $params): array
    {
        $this->ensureAvailable();

        $confirmation = $params['confirmation'] ?? 'none';
        $insuranceValue = $params['insurance_value'] ?? null;

        $shipmentPayload = [
            'from_address' => [
                'postal_code' => $params['from_postal_code'],
                'country' => $params['from_country'] ?? 'US',
            ],
            'to_address' => [
                'postal_code' => $params['to_postal_code'],
                'country' => $params['to_country'] ?? 'US',
            ],
            'weight' => [
                'value' => $params['weight_oz'],
                'units' => 'ounces',
            ],
            'dimensions' => [
                'length' => $params['length'] ?? 12,
                'width' => $params['width'] ?? 9,
                'height' => $params['height'] ?? 4,
                'units' => 'inches',
            ],
            'confirmation' => $confirmation,
            'residential' => true,
        ];

        if ($insuranceValue && $insuranceValue > 0) {
            $shipmentPayload['insurance'] = [
                'amount' => $insuranceValue,
                'currency' => 'USD',
            ];
        }

        $response = $this->request('POST', '/rates', [
            'shipment' => $shipmentPayload,
        ], ShippingEasySyncLog::OP_GET_RATES);

        return collect($response['rates'] ?? [])->map(function ($rate) use ($insuranceValue) {
            return [
                'carrier' => $rate['carrier'] ?? 'Unknown',
                'service' => $rate['service_name'] ?? $rate['service_code'] ?? 'Unknown',
                'service_code' => $rate['service_code'] ?? null,
                'rate' => (float) ($rate['total_cost'] ?? 0),
                'est_delivery_days' => $rate['estimated_days'] ?? null,
                'delivery_date_guaranteed' => false,
                'insurance_cost' => $insuranceValue ? (float) ($rate['insurance_cost'] ?? 0) : null,
                'currency' => 'USD',
            ];
        })->all();
    }

    /**
     * Create an order in ShippingEasy (without purchasing a label).
     */
    public function createOrder(Order $order, array $packageDimensions = [], ?ShippingRate $rate = null): array
    {
        $this->ensureAvailable();
        $this->ensureStoreApiKey();

        $order->loadMissing('items.product');

        $lineItems = $order->items->map(function ($item) {
            return [
                'product_name' => $item->title ?? $item->product?->title ?? 'Item',
                'sku' => $item->sku ?? $item->product?->sku ?? '',
                'quantity' => $item->quantity,
                'unit_price' => (float) ($item->unit_price ?? 0),
                'weight_in_ounces' => (float) ($item->weight_oz ?? 0),
            ];
        })->all();

        $orderPayload = [
            'order' => [
                'external_order_identifier' => $order->order_number ?? $order->id,
                'ordered_at' => $order->created_at->toIso8601String(),
                'recipients' => [
                    [
                        'first_name' => $order->shipping_address_name,
                        'address' => $order->shipping_address_line1,
                        'address2' => $order->shipping_address_line2,
                        'city' => $order->shipping_city,
                        'state' => $order->shipping_state,
                        'postal_code' => $order->shipping_postal_code,
                        'country' => $order->shipping_country ?? 'US',
                        'shipping_method' => $rate?->metadata['service_code'] ?? 'standard',
                        'line_items_attributes' => $lineItems,
                    ],
                ],
                'total_including_tax' => (string) ($order->total ?? 0),
                'total_excluding_tax' => (string) ($order->subtotal ?? $order->total ?? 0),
                'discount_amount' => '0.00',
                'total_shipping_cost' => (string) ($order->shipping_cost ?? 0),
            ],
        ];

        if ($rate) {
            $orderPayload['order']['carrier_key'] = $rate->metadata['carrier_code'] ?? null;
            $orderPayload['order']['service_key'] = $rate->metadata['service_code'] ?? null;
        }

        $response = $this->request('POST', "/stores/{$this->storeApiKey}/orders", $orderPayload, ShippingEasySyncLog::OP_CREATE_ORDER);

        $seOrderId = $response['order']['id'] ?? null;

        if (! $seOrderId) {
            throw new Exception('Failed to create ShippingEasy order.');
        }

        return $response;
    }

    /**
     * NOTE: POST /orders/{id}/labels is not in the official SE API docs. Verify with real credentials.
     */
    public function purchaseLabel(Order $order, string $rateId, array $packageDimensions = []): array
    {
        $this->ensureAvailable();
        $this->ensureStoreApiKey();

        $rate = ShippingRate::where('order_id', $order->id)
            ->where('easypost_rate_id', $rateId)
            ->firstOrFail();

        // Create order in ShippingEasy with line items
        $seOrderResponse = $this->createOrder($order, $packageDimensions, $rate);
        $seOrderId = $seOrderResponse['order']['id'];

        // Purchase label for the order
        $label = $this->request('POST', "/orders/{$seOrderId}/labels", [
            'label' => [
                'carrier_key' => $rate->metadata['carrier_code'] ?? null,
                'service_key' => $rate->metadata['service_code'] ?? $rateId,
                'ship_date' => now()->toDateString(),
                'weight' => [
                    'value' => $packageDimensions['weight_oz'] ?? 16,
                    'units' => 'ounces',
                ],
                'dimensions' => [
                    'length' => $packageDimensions['length'] ?? 12,
                    'width' => $packageDimensions['width'] ?? 9,
                    'height' => $packageDimensions['height'] ?? 4,
                    'units' => 'inches',
                ],
            ],
        ], ShippingEasySyncLog::OP_PURCHASE_LABEL);

        $rate->update(['selected' => true]);

        return [
            'tracking_number' => $label['tracking_number'] ?? null,
            'tracking_url' => $label['tracking_url'] ?? null,
            'label_url' => $label['label_url'] ?? null,
            'label_format' => 'PDF',
            'carrier' => $label['carrier'] ?? $rate->carrier,
            'service' => $rate->service,
            'label_cost' => $label['cost'] ?? $rate->rate,
            'shippingeasy_shipment_id' => $label['shipment_id'] ?? null,
            'shippingeasy_order_id' => (string) $seOrderId,
            'estimated_delivery_date' => $label['estimated_delivery_date'] ?? null,
        ];
    }

    /**
     * NOTE: POST /shipments/{id}/void is not in the official SE API docs. Verify with real credentials.
     */
    public function voidLabel(Shipment $shipment): bool
    {
        $this->ensureAvailable();

        if (! $shipment->shippingeasy_shipment_id) {
            throw new Exception('No ShippingEasy shipment ID found.');
        }

        try {
            $response = $this->request('POST', "/shipments/{$shipment->shippingeasy_shipment_id}/void", [], ShippingEasySyncLog::OP_VOID_LABEL);

            return ($response['success'] ?? false) === true;
        } catch (Exception $e) {
            Log::error('ShippingEasy label void failed', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get orders from ShippingEasy with optional filters.
     * Returns a single page of results with pagination metadata.
     */
    public function getOrders(array $filters = [], int $page = 1): array
    {
        $this->ensureAvailable();

        $params = array_merge($filters, [
            'page' => $page,
            'per_page' => 200,
        ]);

        return $this->request('GET', '/orders', $params, ShippingEasySyncLog::OP_GET_ORDERS);
    }

    /**
     * Get all orders across all pages.
     * Use with caution — respects rate limits via retry logic.
     */
    public function getAllOrders(array $filters = []): array
    {
        $this->ensureAvailable();

        $allOrders = [];
        $page = 1;

        do {
            $response = $this->getOrders($filters, $page);
            $orders = $response['orders'] ?? [];
            $allOrders = array_merge($allOrders, $orders);

            $totalPages = $response['meta']['total_pages']
                ?? $response['total_pages']
                ?? 1;
            $page++;
        } while ($page <= $totalPages);

        return $allOrders;
    }

    /**
     * Get a single order from ShippingEasy.
     */
    public function getOrder(string $seOrderId): array
    {
        $this->ensureAvailable();

        return $this->request('GET', "/orders/{$seOrderId}", [], ShippingEasySyncLog::OP_GET_ORDER);
    }

    /**
     * Cancel an order in ShippingEasy.
     * Uses the external_order_identifier (our order number), not the SE order ID.
     */
    public function cancelOrder(string $externalOrderIdentifier): bool
    {
        $this->ensureAvailable();
        $this->ensureStoreApiKey();

        try {
            $this->request('POST', "/stores/{$this->storeApiKey}/orders/{$externalOrderIdentifier}/cancellations", [], ShippingEasySyncLog::OP_CANCEL_ORDER);

            return true;
        } catch (Exception $e) {
            Log::error('ShippingEasy order cancel failed', [
                'external_order_identifier' => $externalOrderIdentifier,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * List all stores in the ShippingEasy account.
     */
    public function getStores(): array
    {
        $this->ensureAvailable();

        return $this->request('GET', '/stores', [], ShippingEasySyncLog::OP_GET_CARRIERS);
    }

    /**
     * Find an order by our external order identifier.
     */
    public function findOrderByExternalId(string $externalOrderIdentifier): array
    {
        $this->ensureAvailable();
        $this->ensureStoreApiKey();

        return $this->request('GET', "/stores/{$this->storeApiKey}/orders/{$externalOrderIdentifier}", [], ShippingEasySyncLog::OP_GET_ORDER);
    }

    /**
     * Update an order's status in ShippingEasy.
     */
    public function updateOrderStatus(string $externalOrderIdentifier, string $status): bool
    {
        $this->ensureAvailable();
        $this->ensureStoreApiKey();

        try {
            $this->request('PUT', "/stores/{$this->storeApiKey}/orders/{$externalOrderIdentifier}/status", [
                'order' => ['order_status' => $status],
            ], ShippingEasySyncLog::OP_CANCEL_ORDER);

            return true;
        } catch (Exception $e) {
            Log::error('ShippingEasy order status update failed', [
                'external_order_identifier' => $externalOrderIdentifier,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function testConnection(): bool
    {
        $this->ensureAvailable();

        try {
            $this->request('GET', '/carriers', [], ShippingEasySyncLog::OP_TEST_CONNECTION);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getCarriers(): array
    {
        $this->ensureAvailable();

        return $this->request('GET', '/carriers', [], ShippingEasySyncLog::OP_GET_CARRIERS);
    }

    /**
     * Verify a webhook signature from ShippingEasy.
     * Matches the official SE Authenticator: params are ksort'd and http_build_query'd.
     *
     * @param  array  $params  All query params INCLUDING api_key and api_timestamp, EXCLUDING api_signature
     */
    public static function verifyWebhookSignature(
        string $apiSecret,
        string $method,
        string $path,
        array $params,
        string $body,
        string $providedSignature
    ): bool {
        $expectedSignature = self::computeSignature($apiSecret, $method, $path, $params, $body);

        return hash_equals($expectedSignature, $providedSignature);
    }

    /**
     * Compute HMAC-SHA256 signature matching the official SE PHP client algorithm.
     *
     * Plaintext: METHOD & PATH [& http_build_query(ksorted_params)] [& normalized_body]
     */
    protected static function computeSignature(string $apiSecret, string $method, string $path, array $params, string $body = ''): string
    {
        ksort($params);

        $parts = [strtoupper($method), $path];

        if (! empty($params)) {
            $parts[] = http_build_query($params);
        }

        if ($body !== '' && $body !== 'null') {
            $parts[] = str_replace('\\/', '/', $body);
        }

        return hash_hmac('sha256', implode('&', $parts), $apiSecret);
    }

    protected function ensureStoreApiKey(): void
    {
        if (! $this->storeApiKey) {
            throw new Exception('ShippingEasy Store API Key not configured. Add it in Fulfillment Settings.');
        }
    }

    /**
     * Generate signature for an outbound API request.
     * Adds api_key and api_timestamp to params, then signs using official SE algorithm.
     */
    protected function generateSignature(string $method, string $path, array $params, string $body = ''): string
    {
        return self::computeSignature($this->apiSecret, $method, $path, $params, $body);
    }

    protected function request(string $method, string $endpoint, array $data = [], ?string $operation = null): array
    {
        $url = $this->baseUrl.$endpoint;

        // Start sync log
        $syncLog = null;
        if ($operation) {
            try {
                $syncLog = ShippingEasySyncLog::startOperation(
                    $this->organization->id,
                    $operation,
                    ['integration_credential_id' => $this->credential?->id]
                );
                $syncLog->setRequest($endpoint, $method, $data);
            } catch (\Throwable $e) {
                Log::warning('Failed to create ShippingEasy sync log', ['error' => $e->getMessage()]);
            }
        }

        $lastException = null;

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $timestamp = (string) time();
                $jsonBody = ! empty($data) && strtoupper($method) !== 'GET' ? json_encode($data) : '';

                // Build auth params (SE requires these as query params, not headers)
                $authParams = [
                    'api_key' => $this->apiKey,
                    'api_timestamp' => $timestamp,
                ];

                // For GET requests, merge data params with auth params for signing
                $signParams = strtoupper($method) === 'GET'
                    ? array_merge($data, $authParams)
                    : $authParams;

                $signature = $this->generateSignature($method, $endpoint, $signParams, $jsonBody);
                $authParams['api_signature'] = $signature;

                // Build URL with auth query params
                $signedUrl = $url.'?'.http_build_query($authParams);

                $request = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->timeout(30);

                $response = match (strtoupper($method)) {
                    'GET' => $request->get($signedUrl, $data),
                    'POST' => $request->post($signedUrl, $data),
                    'PUT' => $request->put($signedUrl, $data),
                    'DELETE' => $request->delete($signedUrl, $data),
                    default => throw new Exception("Unsupported HTTP method: {$method}"),
                };

                if ($response->status() === 429) {
                    if ($attempt < $this->maxRetries) {
                        $delay = $this->retryDelays[$attempt] ?? 4;
                        Log::warning('ShippingEasy rate limited, retrying', [
                            'endpoint' => $endpoint,
                            'attempt' => $attempt + 1,
                            'delay_seconds' => $delay,
                            'org_id' => $this->organization->id,
                        ]);
                        sleep($delay);

                        continue;
                    }

                    // All retries exhausted
                    $syncLog?->completeError('Rate limit exceeded after retries', null, [], 429, true);

                    throw new Exception('ShippingEasy API rate limit exceeded after '.$this->maxRetries.' retries');
                }

                if ($response->failed()) {
                    $error = $response->json('error') ?? $response->json('message') ?? $response->body();
                    Log::error('ShippingEasy API error', [
                        'method' => $method,
                        'endpoint' => $endpoint,
                        'status' => $response->status(),
                        'error' => $error,
                        'org_id' => $this->organization->id,
                    ]);

                    $syncLog?->completeError(
                        is_string($error) ? $error : json_encode($error),
                        null,
                        is_array($error) ? $error : [],
                        $response->status(),
                        $response->status() >= 500 || $response->status() === 429
                    );

                    throw new Exception('ShippingEasy API error: '.(is_string($error) ? $error : json_encode($error)));
                }

                $syncLog?->completeSuccess($response->json() ?? [], $response->status());

                return $response->json() ?? [];
            } catch (Exception $e) {
                $lastException = $e;

                // If sync log is still pending, mark it as error
                if ($syncLog && $syncLog->status === ShippingEasySyncLog::STATUS_PENDING) {
                    $syncLog->completeError($e->getMessage());
                }

                throw $e;
            }
        }

        // Should not be reachable, but just in case
        throw new Exception('ShippingEasy API request failed unexpectedly');
    }
}
