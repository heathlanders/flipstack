<?php

namespace App\Services\Tracking\Adapters;

use App\Models\Fulfillment\Shipment;
use App\Models\Fulfillment\ShipmentTrackingEvent;
use App\Models\Integration\IntegrationCredential;
use App\Services\Ebay\EbayOAuthService;
use App\Services\Tracking\Contracts\TrackingProvider;
use App\Services\Tracking\TrackingResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

/**
 * Fallback tracking provider that reads delivery confirmation from eBay's
 * Trading API GetOrders. Useful for sellers who do NOT have a carrier API
 * (USPS/EasyPost) wired up — eBay populates ActualDeliveryTime on its own
 * tracking record once the carrier reports delivery to eBay.
 *
 * Registered last in TrackingProviderRegistry so paid carrier APIs win when
 * configured.
 */
class EbayTrackingAdapter implements TrackingProvider
{
    protected int $compatibilityLevel = 967;

    protected int $siteId = 0;

    public function name(): string
    {
        return 'ebay';
    }

    public function supports(Shipment $shipment): bool
    {
        if (! $shipment->tracking_number) {
            return false;
        }

        $shipment->loadMissing('order');
        $order = $shipment->order;

        if (! $order || $order->marketplace !== 'ebay' || ! $order->marketplace_order_id) {
            return false;
        }

        return IntegrationCredential::where('organization_id', $order->organization_id)
            ->where('platform', 'ebay')
            ->where('is_active', true)
            ->exists();
    }

    public function fetch(Shipment $shipment): TrackingResult
    {
        $shipment->loadMissing('order');
        $order = $shipment->order;

        if (! $order) {
            return new TrackingResult;
        }

        $credential = IntegrationCredential::where('organization_id', $order->organization_id)
            ->where('platform', 'ebay')
            ->where('is_active', true)
            ->first();

        if (! $credential) {
            return new TrackingResult;
        }

        $cacheKey = $this->cacheKeyFor($order->marketplace_order_id);
        $body = Cache::get($cacheKey);

        if (! $body) {
            $body = $this->callTradingApi($credential, $order->marketplace_order_id);
            // Only cache responses that actually carry delivery / shipment data.
            // Caching an empty XML for 6h would lock us out of subsequent retries
            // until eBay populates ActualDeliveryTime — which is exactly the
            // signal we are trying to read.
            if ($body && $this->responseHasDeliveryData($body)) {
                Cache::put($cacheKey, $body, now()->addHours(6));
            }
        }

        if ($body) {
            $tradingResult = $this->parse($body, (string) $shipment->tracking_number);
            if (! $tradingResult->isEmpty()) {
                return $tradingResult;
            }
        }

        // Trading API has no ActualDeliveryTime for this shipment — eBay only
        // populates it for eBay-purchased labels. Fall back to the data eBay
        // already gave us at order import: if the order is FULFILLED and the
        // buyer-facing max estimated delivery date has passed, treat as
        // presumed delivered. This mirrors how eBay's seller dashboard treats
        // the order internally (FULFILLED + max date elapsed = effectively
        // complete pending the return window).
        return $this->presumedDeliveryFallback($shipment);
    }

    protected function presumedDeliveryFallback(Shipment $shipment): TrackingResult
    {
        $order = $shipment->order;
        if (! $order) {
            return new TrackingResult;
        }

        if (($order->marketplace_status ?? null) !== 'FULFILLED') {
            return new TrackingResult;
        }

        $maxDate = data_get($order->marketplace_data, 'fulfillmentStartInstructions.0.maxEstimatedDeliveryDate');
        if (! $maxDate) {
            return new TrackingResult;
        }

        try {
            $deliveredAt = Carbon::parse($maxDate);
        } catch (\Throwable) {
            return new TrackingResult;
        }

        if ($deliveredAt->isFuture()) {
            return new TrackingResult;
        }

        $tracking = (string) $shipment->tracking_number;
        $event = [
            'status' => ShipmentTrackingEvent::STATUS_DELIVERED,
            'description' => 'Delivered (presumed — eBay max estimated delivery date elapsed, FULFILLED)',
            'occurred_at' => $deliveredAt,
            'source_event_id' => substr(sha1('ebay:presumed:'.$tracking.'|'.$deliveredAt->toIso8601String()), 0, 32),
            'raw' => null,
        ];

        return new TrackingResult(
            events: [$event],
            latestStatus: ShipmentTrackingEvent::STATUS_DELIVERED,
            deliveredAt: $deliveredAt,
        );
    }

    public function cacheKeyFor(string $marketplaceOrderId): string
    {
        return 'ebay:tracking:'.$marketplaceOrderId.':'.now()->toDateString();
    }

    protected function responseHasDeliveryData(string $body): bool
    {
        return stripos($body, '<ActualDeliveryTime>') !== false
            || stripos($body, '<ShippedTime>') !== false;
    }

    protected function callTradingApi(IntegrationCredential $credential, string $marketplaceOrderId): ?string
    {
        try {
            $token = $this->resolveToken($credential);
            if (! $token) {
                return null;
            }

            $url = $credential->environment === 'sandbox'
                ? 'https://api.sandbox.ebay.com/ws/api.dll'
                : 'https://api.ebay.com/ws/api.dll';

            $xml = $this->buildGetOrdersXml($marketplaceOrderId);

            $response = Http::withHeaders([
                'X-EBAY-API-IAF-TOKEN' => $token,
                'X-EBAY-API-SITEID' => (string) $this->siteId,
                'X-EBAY-API-COMPATIBILITY-LEVEL' => (string) $this->compatibilityLevel,
                'X-EBAY-API-CALL-NAME' => 'GetOrders',
                'Content-Type' => 'text/xml',
            ])->withBody($xml, 'text/xml')
                ->timeout(20)
                ->post($url);

            if (! $response->successful()) {
                Log::warning('ebay tracking trading api http error', [
                    'order_id' => $marketplaceOrderId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->body();
        } catch (\Throwable $e) {
            Log::warning('ebay tracking trading api request failed', [
                'order_id' => $marketplaceOrderId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function resolveToken(IntegrationCredential $credential): ?string
    {
        try {
            $token = $credential->getActiveEbayToken();
            if (! $token) {
                return null;
            }

            if ($token->needsRefresh()) {
                $oauth = new EbayOAuthService($credential);
                $token = $oauth->refreshToken($token);
            }

            return $token->decrypted_access_token ?: null;
        } catch (\Throwable $e) {
            Log::warning('ebay tracking adapter token resolution failed', [
                'credential_id' => $credential->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function buildGetOrdersXml(string $orderId): string
    {
        $orderId = htmlspecialchars($orderId, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<GetOrdersRequest xmlns="urn:ebay:apis:eBLBaseComponents">
  <OrderIDArray>
    <OrderID>{$orderId}</OrderID>
  </OrderIDArray>
  <DetailLevel>ReturnAll</DetailLevel>
</GetOrdersRequest>
XML;
    }

    protected function parse(string $xmlBody, string $trackingNumber): TrackingResult
    {
        try {
            $xml = new SimpleXMLElement($xmlBody);
        } catch (\Throwable $e) {
            Log::warning('ebay tracking adapter parse error', ['error' => $e->getMessage()]);

            return new TrackingResult;
        }

        $packages = [];
        foreach ($xml->OrderArray->Order ?? [] as $orderNode) {
            foreach ($orderNode->TransactionArray->Transaction ?? [] as $txn) {
                foreach ($txn->ShippingDetails->ShippingPackageInfo ?? [] as $pkg) {
                    $packages[] = $pkg;
                }
            }
            foreach ($orderNode->ShippingDetails->ShippingPackageInfo ?? [] as $pkg) {
                $packages[] = $pkg;
            }
        }

        if (empty($packages)) {
            return new TrackingResult;
        }

        $needle = strtoupper(trim($trackingNumber));
        $matched = null;
        foreach ($packages as $pkg) {
            $candidate = strtoupper(trim((string) ($pkg->ShipmentTrackingNumber ?? '')));
            if ($candidate !== '' && $candidate === $needle) {
                $matched = $pkg;
                break;
            }
        }
        $matched = $matched ?? $packages[0];

        $deliveredAt = $this->parseTime((string) ($matched->ActualDeliveryTime ?? ''));
        $shippedAt = $this->parseTime((string) ($matched->ShippedTime ?? ''));
        $estimatedAt = $this->parseTime((string) ($matched->EstimatedDeliveryTimeMax ?? $matched->EstimatedDeliveryTimeMin ?? ''));

        if ($deliveredAt) {
            $event = [
                'status' => ShipmentTrackingEvent::STATUS_DELIVERED,
                'description' => 'Delivered (per eBay)',
                'occurred_at' => $deliveredAt,
                'source_event_id' => substr(sha1('ebay:delivered:'.$needle.'|'.$deliveredAt->toIso8601String()), 0, 32),
                'raw' => json_decode(json_encode($matched), true),
            ];

            return new TrackingResult(
                events: [$event],
                latestStatus: ShipmentTrackingEvent::STATUS_DELIVERED,
                estimatedDeliveryAt: $estimatedAt,
                deliveredAt: $deliveredAt,
            );
        }

        if ($shippedAt) {
            $event = [
                'status' => ShipmentTrackingEvent::STATUS_IN_TRANSIT,
                'description' => 'Shipped (per eBay)',
                'occurred_at' => $shippedAt,
                'source_event_id' => substr(sha1('ebay:shipped:'.$needle.'|'.$shippedAt->toIso8601String()), 0, 32),
                'raw' => json_decode(json_encode($matched), true),
            ];

            return new TrackingResult(
                events: [$event],
                latestStatus: ShipmentTrackingEvent::STATUS_IN_TRANSIT,
                estimatedDeliveryAt: $estimatedAt,
                deliveredAt: null,
            );
        }

        return new TrackingResult;
    }

    protected function parseTime(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
