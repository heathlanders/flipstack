<?php

namespace App\Services\Fulfillment;

use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\Shipment;
use App\Models\Fulfillment\ShippingRate;
use App\Models\Integration\IntegrationCredential;
use App\Models\Inventory\InventoryLocation;
use App\Models\Organization\Organization;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShipStationService
{
    protected Organization $organization;

    protected string $apiKey;

    protected string $apiSecret;

    protected string $baseUrl = 'https://ssapi.shipstation.com';

    public function __construct(Organization $organization)
    {
        $this->organization = $organization;
        $this->loadCredentials();
    }

    protected function loadCredentials(): void
    {
        $credential = IntegrationCredential::where('organization_id', $this->organization->id)
            ->where('platform', 'shipstation')
            ->where('is_active', true)
            ->first();

        if (! $credential) {
            throw new Exception('ShipStation integration not configured for this organization.');
        }

        $this->apiKey = $credential->client_id;
        $this->apiSecret = $credential->decrypted_client_secret;
    }

    public function getRates(Order $order, InventoryLocation $fromLocation): Collection
    {
        $totalWeightOz = $order->items->sum('weight_oz') ?: ($fromLocation->default_weight_oz ?: 16);

        $response = $this->request('POST', '/shipments/getrates', [
            'carrierCode' => null,
            'fromPostalCode' => $fromLocation->postal_code,
            'toState' => $order->shipping_state,
            'toCountry' => $order->shipping_country ?? 'US',
            'toPostalCode' => $order->shipping_postal_code,
            'toCity' => $order->shipping_city,
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
        ]);

        // Delete old rates for this order
        ShippingRate::where('order_id', $order->id)->delete();

        $rates = collect($response ?? [])->map(function ($rate) use ($order) {
            return ShippingRate::create([
                'organization_id' => $order->organization_id,
                'order_id' => $order->id,
                'easypost_rate_id' => $rate['serviceCode'] ?? null,
                'carrier' => $rate['carrierCode'] ?? 'Unknown',
                'service' => $rate['serviceName'] ?? $rate['serviceCode'] ?? 'Unknown',
                'rate' => $rate['shipmentCost'] + ($rate['otherCost'] ?? 0),
                'currency' => 'USD',
                'estimated_days' => null,
                'delivery_date' => null,
                'delivery_guarantee' => false,
                'expires_at' => now()->addHours(1),
                'metadata' => [
                    'provider' => 'shipstation',
                    'carrier_code' => $rate['carrierCode'] ?? null,
                    'service_code' => $rate['serviceCode'] ?? null,
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
        $confirmation = $params['confirmation'] ?? 'none';
        $insuranceValue = $params['insurance_value'] ?? null;

        $requestPayload = [
            'carrierCode' => $params['carrier_code'] ?? null,
            'fromPostalCode' => $params['from_postal_code'] ?? $params['fromPostalCode'] ?? null,
            'toPostalCode' => $params['to_postal_code'] ?? $params['toPostalCode'] ?? null,
            'toCountry' => $params['to_country'] ?? $params['toCountry'] ?? 'US',
            'weight' => $params['weight'] ?? [
                'value' => $params['weight_oz'] ?? 16,
                'units' => 'ounces',
            ],
            'dimensions' => $params['dimensions'] ?? [
                'length' => $params['length'] ?? 12,
                'width' => $params['width'] ?? 9,
                'height' => $params['height'] ?? 4,
                'units' => 'inches',
            ],
            'confirmation' => $confirmation,
            'residential' => true,
        ];

        if ($insuranceValue && $insuranceValue > 0) {
            $requestPayload['insuranceOptions'] = [
                'provider' => 'carrier',
                'insureShipment' => true,
                'insuredValue' => $insuranceValue,
            ];
        }

        $response = $this->request('POST', '/shipments/getrates', $requestPayload);

        return collect($response ?? [])->map(function ($rate) use ($insuranceValue) {
            $shipmentCost = (float) ($rate['shipmentCost'] ?? 0);
            $otherCost = (float) ($rate['otherCost'] ?? 0);

            return [
                'carrier' => $rate['carrierCode'] ?? 'Unknown',
                'service' => $rate['serviceName'] ?? $rate['serviceCode'] ?? 'Unknown',
                'service_code' => $rate['serviceCode'] ?? null,
                'rate' => $shipmentCost + $otherCost,
                'est_delivery_days' => null,
                'delivery_date_guaranteed' => false,
                'insurance_cost' => $insuranceValue ? (float) ($rate['insuranceCost'] ?? 0) : null,
                'currency' => 'USD',
            ];
        })->all();
    }

    public function purchaseLabel(Order $order, string $rateId, array $packageDimensions = []): array
    {
        $rate = ShippingRate::where('order_id', $order->id)
            ->where('easypost_rate_id', $rateId)
            ->firstOrFail();

        $fromLocation = $order->shipments()->latest()->first()?->location
            ?? $order->location;

        // Create order in ShipStation
        $ssOrder = $this->request('POST', '/orders/createorder', [
            'orderNumber' => $order->order_number ?? $order->id,
            'orderDate' => $order->created_at->toIso8601String(),
            'orderStatus' => 'awaiting_shipment',
            'billTo' => [
                'name' => $order->shipping_address_name,
                'street1' => $order->shipping_address_line1,
                'street2' => $order->shipping_address_line2,
                'city' => $order->shipping_city,
                'state' => $order->shipping_state,
                'postalCode' => $order->shipping_postal_code,
                'country' => $order->shipping_country ?? 'US',
            ],
            'shipTo' => [
                'name' => $order->shipping_address_name,
                'street1' => $order->shipping_address_line1,
                'street2' => $order->shipping_address_line2,
                'city' => $order->shipping_city,
                'state' => $order->shipping_state,
                'postalCode' => $order->shipping_postal_code,
                'country' => $order->shipping_country ?? 'US',
            ],
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
            'carrierCode' => $rate->metadata['carrier_code'] ?? null,
            'serviceCode' => $rate->metadata['service_code'] ?? $rateId,
        ]);

        $ssOrderId = $ssOrder['orderId'] ?? null;

        if (! $ssOrderId) {
            throw new Exception('Failed to create ShipStation order.');
        }

        // Create label for the order
        $label = $this->request('POST', '/orders/createlabelfororder', [
            'orderId' => $ssOrderId,
            'carrierCode' => $rate->metadata['carrier_code'] ?? null,
            'serviceCode' => $rate->metadata['service_code'] ?? $rateId,
            'confirmation' => 'none',
            'shipDate' => now()->toDateString(),
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
            'testLabel' => false,
        ]);

        $rate->update(['selected' => true]);

        return [
            'tracking_number' => $label['trackingNumber'] ?? null,
            'tracking_url' => null,
            'label_url' => $label['labelData'] ?? null,
            'label_format' => 'PDF',
            'carrier' => $label['carrierCode'] ?? $rate->carrier,
            'service' => $rate->service,
            'label_cost' => $label['shipmentCost'] ?? $rate->rate,
            'shipstation_shipment_id' => $label['shipmentId'] ?? null,
            'shipstation_order_id' => (string) $ssOrderId,
            'estimated_delivery_date' => null,
        ];
    }

    public function voidLabel(Shipment $shipment): bool
    {
        if (! $shipment->shipstation_shipment_id) {
            throw new Exception('No ShipStation shipment ID found.');
        }

        try {
            $response = $this->request('POST', '/shipments/voidlabel', [
                'shipmentId' => $shipment->shipstation_shipment_id,
            ]);

            return ($response['approved'] ?? false) === true;
        } catch (Exception $e) {
            Log::error('ShipStation label void failed', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function testConnection(): bool
    {
        try {
            $this->request('GET', '/carriers');

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getCarriers(): array
    {
        return $this->request('GET', '/carriers');
    }

    protected function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl.$endpoint;

        $request = Http::withBasicAuth($this->apiKey, $this->apiSecret)
            ->timeout(30)
            ->retry(2, 1000);

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url, $data),
            'POST' => $request->post($url, $data),
            'PUT' => $request->put($url, $data),
            'DELETE' => $request->delete($url, $data),
            default => throw new Exception("Unsupported HTTP method: {$method}"),
        };

        if ($response->failed()) {
            $error = $response->json('Message') ?? $response->json('ExceptionMessage') ?? $response->body();
            Log::error('ShipStation API error', [
                'method' => $method,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'error' => $error,
                'org_id' => $this->organization->id,
            ]);
            throw new Exception("ShipStation API error: {$error}");
        }

        return $response->json() ?? [];
    }
}
