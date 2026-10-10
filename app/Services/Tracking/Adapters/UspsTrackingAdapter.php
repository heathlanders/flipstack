<?php

namespace App\Services\Tracking\Adapters;

use App\Models\Fulfillment\Shipment;
use App\Models\Fulfillment\ShipmentTrackingEvent;
use App\Services\Tracking\Contracts\TrackingProvider;
use App\Services\Tracking\TrackingResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

/**
 * USPS Web Tools "TrackFieldRequestV2" — free with a registered USERID.
 * Endpoint: https://secure.shippingapis.com/ShippingAPI.dll
 */
class UspsTrackingAdapter implements TrackingProvider
{
    public function name(): string
    {
        return 'usps';
    }

    public function supports(Shipment $shipment): bool
    {
        if (! $shipment->tracking_number) {
            return false;
        }

        $carrier = strtoupper((string) $shipment->carrier);
        if ($carrier !== '' && $carrier !== 'USPS') {
            return false;
        }

        return (bool) config('services.usps.user_id');
    }

    public function fetch(Shipment $shipment): TrackingResult
    {
        $userId = config('services.usps.user_id');
        if (! $userId) {
            return new TrackingResult;
        }

        $xml = $this->buildRequestXml($shipment->tracking_number, $userId);

        $response = Http::asForm()
            ->timeout(10)
            ->get('https://secure.shippingapis.com/ShippingAPI.dll', [
                'API' => 'TrackV2',
                'XML' => $xml,
            ]);

        if (! $response->ok()) {
            Log::warning('usps tracking http error', [
                'shipment_id' => $shipment->id,
                'status' => $response->status(),
            ]);

            return new TrackingResult;
        }

        return $this->parse($response->body());
    }

    protected function buildRequestXml(string $trackingNumber, string $userId): string
    {
        $tracking = htmlspecialchars($trackingNumber, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $userIdAttr = htmlspecialchars($userId, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return <<<XML
<TrackFieldRequest USERID="{$userIdAttr}">
  <Revision>1</Revision>
  <ClientIp>127.0.0.1</ClientIp>
  <SourceId>FlipStack</SourceId>
  <TrackID ID="{$tracking}"></TrackID>
</TrackFieldRequest>
XML;
    }

    protected function parse(string $xmlBody): TrackingResult
    {
        try {
            $xml = new SimpleXMLElement($xmlBody);
        } catch (\Throwable $e) {
            Log::warning('usps tracking parse error', ['error' => $e->getMessage()]);

            return new TrackingResult;
        }

        if (isset($xml->Number)) {
            // Top-level <Error>
            return new TrackingResult;
        }

        $info = $xml->TrackInfo ?? null;
        if (! $info) {
            return new TrackingResult;
        }

        if (isset($info->Error)) {
            return new TrackingResult;
        }

        $events = [];

        if (isset($info->TrackSummary)) {
            $events[] = $this->buildEvent($info->TrackSummary, isSummary: true);
        }

        foreach ($info->TrackDetail ?? [] as $detail) {
            $events[] = $this->buildEvent($detail);
        }

        $events = array_values(array_filter($events));
        usort($events, fn ($a, $b) => $b['occurred_at'] <=> $a['occurred_at']);

        $latest = $events[0] ?? null;
        $deliveredAt = null;
        if ($latest && $latest['status'] === ShipmentTrackingEvent::STATUS_DELIVERED) {
            $deliveredAt = $latest['occurred_at'];
        }

        $estimated = null;
        if (! empty($info->ExpectedDeliveryDate)) {
            try {
                $estimated = Carbon::parse((string) $info->ExpectedDeliveryDate);
            } catch (\Throwable) {
            }
        }

        return new TrackingResult(
            events: $events,
            latestStatus: $latest['status'] ?? null,
            estimatedDeliveryAt: $estimated,
            deliveredAt: $deliveredAt,
        );
    }

    protected function buildEvent(SimpleXMLElement $node, bool $isSummary = false): ?array
    {
        $description = trim((string) ($node->Event ?? ''));
        $date = trim((string) ($node->EventDate ?? ''));
        $time = trim((string) ($node->EventTime ?? ''));

        if (! $date) {
            return null;
        }

        try {
            $occurredAt = Carbon::parse(trim($date.' '.$time));
        } catch (\Throwable) {
            return null;
        }

        return [
            'status' => $this->mapStatus($description),
            'description' => $description ?: null,
            'city' => trim((string) ($node->EventCity ?? '')) ?: null,
            'state' => trim((string) ($node->EventState ?? '')) ?: null,
            'postal_code' => trim((string) ($node->EventZIPCode ?? '')) ?: null,
            'country' => trim((string) ($node->EventCountry ?? '')) ?: 'US',
            'source_event_id' => substr(sha1(($isSummary ? 'summary:' : 'detail:').$description.'|'.$occurredAt->toIso8601String()), 0, 32),
            'occurred_at' => $occurredAt,
            'raw' => json_decode(json_encode($node), true),
        ];
    }

    protected function mapStatus(string $description): string
    {
        $d = strtolower($description);

        return match (true) {
            str_contains($d, 'delivered') => ShipmentTrackingEvent::STATUS_DELIVERED,
            str_contains($d, 'out for delivery') => ShipmentTrackingEvent::STATUS_OUT_FOR_DELIVERY,
            str_contains($d, 'return to sender') || str_contains($d, 'returned') => ShipmentTrackingEvent::STATUS_RETURNED,
            str_contains($d, 'pre-shipment') || str_contains($d, 'shipping label created') => ShipmentTrackingEvent::STATUS_PRE_TRANSIT,
            str_contains($d, 'failure') || str_contains($d, 'undeliverable') => ShipmentTrackingEvent::STATUS_FAILURE,
            $d === '' => ShipmentTrackingEvent::STATUS_UNKNOWN,
            default => ShipmentTrackingEvent::STATUS_IN_TRANSIT,
        };
    }
}
