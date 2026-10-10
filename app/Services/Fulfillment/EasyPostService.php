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

class EasyPostService
{
    protected Organization $organization;

    protected string $apiKey;

    protected string $baseUrl;

    public function __construct(Organization $organization)
    {
        $this->organization = $organization;
        $this->loadCredentials();
    }

    protected function loadCredentials(): void
    {
        $credential = IntegrationCredential::where('organization_id', $this->organization->id)
            ->where('platform', 'easypost')
            ->where('is_active', true)
            ->first();

        if (! $credential) {
            throw new Exception('EasyPost integration not configured for this organization.');
        }

        $this->apiKey = $credential->decrypted_client_secret;
        $mode = $credential->environment ?? 'test';
        $this->baseUrl = 'https://api.easypost.com/v2';
    }

    public function createAddress(array $address): array
    {
        $response = $this->request('POST', '/addresses', [
            'address' => [
                'name' => $address['name'] ?? null,
                'street1' => $address['street1'] ?? $address['address_line1'] ?? null,
                'street2' => $address['street2'] ?? $address['address_line2'] ?? null,
                'city' => $address['city'] ?? null,
                'state' => $address['state'] ?? null,
                'zip' => $address['zip'] ?? $address['postal_code'] ?? null,
                'country' => $address['country'] ?? 'US',
                'phone' => $address['phone'] ?? null,
                'email' => $address['email'] ?? null,
                'company' => $address['company'] ?? null,
            ],
            'verify' => ['delivery'],
        ]);

        return $response;
    }

    public function verifyAddress(InventoryLocation $location): array
    {
        $result = $this->createAddress([
            'name' => $location->contact_person ?? $location->name,
            'address_line1' => $location->address_line1,
            'address_line2' => $location->address_line2,
            'city' => $location->city,
            'state' => $location->state,
            'postal_code' => $location->postal_code,
            'country' => $location->country ?? 'US',
            'phone' => $location->phone,
            'email' => $location->email,
        ]);

        if (! empty($result['id']) && empty($result['verifications']['delivery']['errors'])) {
            $location->update([
                'easypost_address_id' => $result['id'],
                'ship_from_verified' => true,
            ]);
        }

        return $result;
    }

    public function getRates(Order $order, InventoryLocation $fromLocation): Collection
    {
        $fromAddress = $this->getLocationAddress($fromLocation);
        $toAddress = [
            'name' => $order->shipping_address_name,
            'street1' => $order->shipping_address_line1,
            'street2' => $order->shipping_address_line2,
            'city' => $order->shipping_city,
            'state' => $order->shipping_state,
            'zip' => $order->shipping_postal_code,
            'country' => $order->shipping_country,
        ];

        $totalWeightOz = $order->items->sum('weight_oz') ?: ($fromLocation->default_weight_oz ?: 16);
        $parcel = [
            'weight' => $totalWeightOz,
            'length' => $fromLocation->default_length ?: 12,
            'width' => $fromLocation->default_width ?: 9,
            'height' => $fromLocation->default_height ?: 4,
        ];

        $response = $this->request('POST', '/shipments', [
            'shipment' => [
                'from_address' => $fromAddress,
                'to_address' => $toAddress,
                'parcel' => $parcel,
            ],
        ]);

        // Delete old rates for this order
        ShippingRate::where('order_id', $order->id)->delete();

        $rates = collect($response['rates'] ?? [])->map(function ($rate) use ($order) {
            return ShippingRate::create([
                'organization_id' => $order->organization_id,
                'order_id' => $order->id,
                'easypost_rate_id' => $rate['id'],
                'carrier' => $rate['carrier'],
                'service' => $rate['service'],
                'rate' => $rate['rate'],
                'currency' => $rate['currency'] ?? 'USD',
                'estimated_days' => $rate['est_delivery_days'] ?? null,
                'delivery_date' => $rate['delivery_date'] ?? null,
                'delivery_guarantee' => $rate['delivery_date_guaranteed'] ?? false,
                'expires_at' => now()->addHours(1),
                'metadata' => [
                    'shipment_id' => $response['id'],
                    'carrier_account_id' => $rate['carrier_account_id'] ?? null,
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

        $shipmentPayload = [
            'shipment' => [
                'from_address' => [
                    'zip' => $params['from_postal_code'],
                    'country' => $params['from_country'] ?? 'US',
                ],
                'to_address' => [
                    'street1' => $params['to_street1'] ?? '123 Main St',
                    'city' => $params['to_city'] ?? 'New York',
                    'state' => $params['to_state'] ?? 'NY',
                    'zip' => $params['to_postal_code'],
                    'country' => $params['to_country'] ?? 'US',
                ],
                'parcel' => [
                    'weight' => $params['weight_oz'] ?? 16,
                    'length' => $params['length'] ?? 12,
                    'width' => $params['width'] ?? 9,
                    'height' => $params['height'] ?? 4,
                ],
                'options' => [
                    'delivery_confirmation' => $confirmation === 'none' ? 'NO_SIGNATURE' : strtoupper($confirmation),
                ],
            ],
        ];

        if ($insuranceValue && $insuranceValue > 0) {
            $shipmentPayload['shipment']['options']['insurance'] = $insuranceValue;
        }

        $response = $this->request('POST', '/shipments', $shipmentPayload);

        return collect($response['rates'] ?? [])->map(function ($rate) use ($insuranceValue) {
            return [
                'carrier' => $rate['carrier'] ?? 'Unknown',
                'service' => $rate['service'] ?? 'Unknown',
                'service_code' => $rate['service'] ?? null,
                'rate' => (float) ($rate['rate'] ?? 0),
                'est_delivery_days' => $rate['est_delivery_days'] ?? null,
                'delivery_date_guaranteed' => $rate['delivery_date_guaranteed'] ?? false,
                'insurance_cost' => $insuranceValue ? (float) ($rate['insurance'] ?? 0) : null,
                'currency' => $rate['currency'] ?? 'USD',
            ];
        })->all();
    }

    public function purchaseLabel(Order $order, string $rateId, array $packageDimensions = []): array
    {
        $rate = ShippingRate::where('order_id', $order->id)
            ->where('easypost_rate_id', $rateId)
            ->firstOrFail();

        $shipmentId = $rate->metadata['shipment_id'] ?? null;

        if (! $shipmentId) {
            throw new Exception('No EasyPost shipment found for this rate.');
        }

        $response = $this->request('POST', "/shipments/{$shipmentId}/buy", [
            'rate' => ['id' => $rateId],
        ]);

        $rate->update(['selected' => true]);

        return [
            'tracking_number' => $response['tracking_code'] ?? null,
            'tracking_url' => $response['tracker']['public_url'] ?? null,
            'label_url' => $response['postage_label']['label_url'] ?? null,
            'label_format' => $response['postage_label']['label_file_type'] ?? 'PDF',
            'carrier' => $response['selected_rate']['carrier'] ?? $rate->carrier,
            'service' => $response['selected_rate']['service'] ?? $rate->service,
            'label_cost' => $response['selected_rate']['rate'] ?? $rate->rate,
            'easypost_shipment_id' => $response['id'],
            'easypost_tracker_id' => $response['tracker']['id'] ?? null,
            'estimated_delivery_date' => $response['selected_rate']['delivery_date'] ?? null,
        ];
    }

    public function voidLabel(Shipment $shipment): bool
    {
        if (! $shipment->easypost_shipment_id) {
            throw new Exception('No EasyPost shipment ID found.');
        }

        try {
            $response = $this->request('POST', "/shipments/{$shipment->easypost_shipment_id}/refund");

            return ($response['status'] ?? '') === 'submitted';
        } catch (Exception $e) {
            Log::error('EasyPost label void failed', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function createTracker(string $trackingNumber, string $carrier): array
    {
        return $this->request('POST', '/trackers', [
            'tracker' => [
                'tracking_code' => $trackingNumber,
                'carrier' => $carrier,
            ],
        ]);
    }

    public function processWebhook(array $payload): array
    {
        $eventType = $payload['description'] ?? '';
        $result = $payload['result'] ?? [];

        return [
            'event_type' => $eventType,
            'tracking_code' => $result['tracking_code'] ?? null,
            'status' => $result['status'] ?? null,
            'status_detail' => $result['status_detail'] ?? null,
            'est_delivery_date' => $result['est_delivery_date'] ?? null,
            'carrier' => $result['carrier'] ?? null,
            'tracking_details' => $result['tracking_details'] ?? [],
            'shipment_id' => $result['shipment_id'] ?? null,
        ];
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $credential = IntegrationCredential::where('organization_id', $this->organization->id)
            ->where('platform', 'easypost')
            ->where('is_active', true)
            ->first();

        $webhookSecret = $credential?->config['webhook_secret'] ?? null;

        if (! $webhookSecret) {
            Log::warning('EasyPost webhook secret not configured', ['org' => $this->organization->id]);

            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }

    public function getCarrierAccounts(): Collection
    {
        $response = $this->request('GET', '/carrier_accounts');

        return collect($response ?? []);
    }

    public function testConnection(): bool
    {
        try {
            $this->request('GET', '/addresses');

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    protected function getLocationAddress(InventoryLocation $location): array
    {
        if ($location->easypost_address_id) {
            return ['id' => $location->easypost_address_id];
        }

        return [
            'name' => $location->contact_person ?? $location->name,
            'street1' => $location->address_line1,
            'street2' => $location->address_line2,
            'city' => $location->city,
            'state' => $location->state,
            'zip' => $location->postal_code,
            'country' => $location->country ?? 'US',
            'phone' => $location->phone,
        ];
    }

    protected function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl.$endpoint;

        $request = Http::withBasicAuth($this->apiKey, '')
            ->timeout(30)
            ->retry(2, 1000);

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url, $data),
            'POST' => $request->post($url, $data),
            'PUT' => $request->put($url, $data),
            'PATCH' => $request->patch($url, $data),
            'DELETE' => $request->delete($url, $data),
            default => throw new Exception("Unsupported HTTP method: {$method}"),
        };

        if ($response->failed()) {
            $error = $response->json('error.message') ?? $response->body();
            Log::error('EasyPost API error', [
                'method' => $method,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'error' => $error,
                'org_id' => $this->organization->id,
            ]);
            throw new Exception("EasyPost API error: {$error}");
        }

        return $response->json();
    }
}
