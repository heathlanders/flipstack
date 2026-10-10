<?php

namespace App\Jobs\Fulfillment;

use App\Models\Fulfillment\Shipment;
use App\Models\Integration\IntegrationCredential;
use App\Services\Fulfillment\TrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessShipStationWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        protected string $resourceType,
        protected string $resourceUrl
    ) {
        $this->onQueue('fulfillment');
    }

    public function handle(TrackingService $trackingService): void
    {
        try {
            // Find any active ShipStation credential to authenticate the resource URL fetch
            $credential = IntegrationCredential::where('platform', 'shipstation')
                ->where('is_active', true)
                ->first();

            if (! $credential) {
                Log::warning('No active ShipStation credentials found for webhook processing');

                return;
            }

            $response = Http::withBasicAuth($credential->client_id, $credential->decrypted_client_secret)
                ->timeout(30)
                ->get($this->resourceUrl);

            if ($response->failed()) {
                Log::error('Failed to fetch ShipStation webhook resource', [
                    'resource_url' => $this->resourceUrl,
                    'status' => $response->status(),
                ]);
                throw new \Exception('Failed to fetch ShipStation resource data');
            }

            $data = $response->json();

            if ($this->resourceType === 'SHIP_NOTIFY') {
                $this->processShipNotify($data, $trackingService);
            }
        } catch (\Exception $e) {
            Log::error('Failed to process ShipStation webhook', [
                'error' => $e->getMessage(),
                'resource_type' => $this->resourceType,
                'resource_url' => $this->resourceUrl,
            ]);
            throw $e;
        }
    }

    protected function processShipNotify(array $data, TrackingService $trackingService): void
    {
        $shipments = isset($data['shipments']) ? $data['shipments'] : [$data];

        foreach ($shipments as $shipmentData) {
            $trackingNumber = $shipmentData['trackingNumber'] ?? null;
            $orderId = (string) ($shipmentData['orderId'] ?? '');

            $shipment = Shipment::query()
                ->when($orderId, fn ($q) => $q->where('shipstation_order_id', $orderId))
                ->when(! $orderId && $trackingNumber, fn ($q) => $q->where('tracking_number', $trackingNumber))
                ->first();

            if (! $shipment) {
                Log::info('ShipStation webhook for unknown shipment', [
                    'tracking_number' => $trackingNumber,
                    'shipstation_order_id' => $orderId,
                ]);

                continue;
            }

            $trackingService->processShipStationUpdate([
                'tracking_code' => $trackingNumber,
                'status' => $shipmentData['shipmentStatus'] ?? null,
                'carrier' => $shipmentData['carrierCode'] ?? null,
                'shipment' => $shipment,
            ]);
        }
    }
}
