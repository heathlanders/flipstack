<?php

namespace App\Services\Tracking\Adapters;

use App\Models\Fulfillment\Shipment;
use App\Models\Fulfillment\ShipmentTrackingEvent;
use App\Models\Integration\IntegrationCredential;
use App\Services\Tracking\Contracts\TrackingProvider;
use App\Services\Tracking\TrackingResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * EasyPost Tracker API. Universal carrier coverage; per-tracker fee.
 * Uses the org's existing easypost IntegrationCredential.
 */
class EasyPostTrackingAdapter implements TrackingProvider
{
    public function name(): string
    {
        return 'easypost';
    }

    public function supports(Shipment $shipment): bool
    {
        if (! $shipment->tracking_number) {
            return false;
        }

        $credential = $this->credentialFor($shipment);

        return (bool) $credential;
    }

    public function fetch(Shipment $shipment): TrackingResult
    {
        $credential = $this->credentialFor($shipment);
        if (! $credential) {
            return new TrackingResult;
        }

        $apiKey = $credential->decrypted_client_secret ?? null;
        if (! $apiKey) {
            return new TrackingResult;
        }

        if ($shipment->easypost_tracker_id) {
            $response = Http::withBasicAuth($apiKey, '')
                ->timeout(10)
                ->get("https://api.easypost.com/v2/trackers/{$shipment->easypost_tracker_id}");
        } else {
            $response = Http::withBasicAuth($apiKey, '')
                ->timeout(10)
                ->post('https://api.easypost.com/v2/trackers', [
                    'tracker' => [
                        'tracking_code' => $shipment->tracking_number,
                        'carrier' => $shipment->carrier ?: null,
                    ],
                ]);
        }

        if (! $response->ok()) {
            Log::warning('easypost tracking http error', [
                'shipment_id' => $shipment->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return new TrackingResult;
        }

        $data = $response->json();
        if (! is_array($data)) {
            return new TrackingResult;
        }

        if (! $shipment->easypost_tracker_id && ! empty($data['id'])) {
            $shipment->forceFill(['easypost_tracker_id' => $data['id']])->save();
        }

        return $this->parse($data);
    }

    protected function credentialFor(Shipment $shipment): ?IntegrationCredential
    {
        return IntegrationCredential::where('organization_id', $shipment->organization_id)
            ->where('platform', 'easypost')
            ->where('is_active', true)
            ->first();
    }

    protected function parse(array $data): TrackingResult
    {
        $events = [];

        foreach ($data['tracking_details'] ?? [] as $detail) {
            $occurredAt = $this->parseDate($detail['datetime'] ?? null);
            if (! $occurredAt) {
                continue;
            }

            $loc = $detail['tracking_location'] ?? [];

            $events[] = [
                'status' => $this->mapStatus($detail['status'] ?? ''),
                'description' => $detail['message'] ?? null,
                'city' => $loc['city'] ?? null,
                'state' => $loc['state'] ?? null,
                'postal_code' => $loc['zip'] ?? null,
                'country' => $loc['country'] ?? null,
                'source_event_id' => substr(sha1(($detail['status'] ?? '').'|'.($detail['message'] ?? '').'|'.$occurredAt->toIso8601String()), 0, 32),
                'occurred_at' => $occurredAt,
                'raw' => $detail,
            ];
        }

        usort($events, fn ($a, $b) => $b['occurred_at'] <=> $a['occurred_at']);

        $latestStatus = $this->mapStatus($data['status'] ?? '');
        $deliveredAt = null;
        if ($latestStatus === ShipmentTrackingEvent::STATUS_DELIVERED) {
            $deliveredAt = $events[0]['occurred_at'] ?? null;
        }

        return new TrackingResult(
            events: $events,
            latestStatus: $latestStatus !== ShipmentTrackingEvent::STATUS_UNKNOWN ? $latestStatus : null,
            estimatedDeliveryAt: $this->parseDate($data['est_delivery_date'] ?? null),
            deliveredAt: $deliveredAt,
        );
    }

    protected function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function mapStatus(string $status): string
    {
        return match ($status) {
            'pre_transit' => ShipmentTrackingEvent::STATUS_PRE_TRANSIT,
            'in_transit' => ShipmentTrackingEvent::STATUS_IN_TRANSIT,
            'out_for_delivery' => ShipmentTrackingEvent::STATUS_OUT_FOR_DELIVERY,
            'delivered' => ShipmentTrackingEvent::STATUS_DELIVERED,
            'return_to_sender', 'returned' => ShipmentTrackingEvent::STATUS_RETURNED,
            'failure', 'cancelled', 'error' => ShipmentTrackingEvent::STATUS_FAILURE,
            default => ShipmentTrackingEvent::STATUS_UNKNOWN,
        };
    }
}
