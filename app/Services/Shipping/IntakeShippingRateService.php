<?php

namespace App\Services\Shipping;

use App\Models\Catalog\ProductIntakeQueue;
use App\Models\Inventory\InventoryLocation;
use App\Models\Organization\Organization;
use App\Models\Organization\OrganizationSettings;
use App\Models\Shipping\ShippingRateQuote;
use App\Services\Fulfillment\EasyPostService;
use App\Services\Fulfillment\ShippingEasyService;
use App\Services\Fulfillment\ShipStationService;
use App\Services\ShippingClassService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class IntakeShippingRateService
{
    protected Organization $organization;

    protected ShippingClassService $shippingClassService;

    public function __construct(Organization $organization)
    {
        $this->organization = $organization;
        $this->shippingClassService = new ShippingClassService;
    }

    public function getRatesForQueueItem(ProductIntakeQueue $queueItem, array $options = []): Collection
    {
        // If options are specified (insurance/confirmation), always fetch fresh rates
        $hasOptions = ! empty($options['confirmation']) && $options['confirmation'] !== 'none'
            || ! empty($options['insurance_value']);

        if (! $hasOptions) {
            // Check for cached valid rates first
            $cachedRates = $this->getCachedRates($queueItem);
            if ($cachedRates->isNotEmpty()) {
                return $cachedRates;
            }
        }

        try {
            $rates = $this->fetchLiveRates($queueItem, $options);

            if ($rates->isEmpty()) {
                Log::info('No live rates returned, using estimates', ['queue_item_id' => $queueItem->id]);

                return $this->getEstimatedRates($queueItem);
            }

            return $rates;

        } catch (Exception $e) {
            Log::warning('Failed to fetch live shipping rates, using estimates', [
                'queue_item_id' => $queueItem->id,
                'error' => $e->getMessage(),
            ]);

            return $this->getEstimatedRates($queueItem);
        }
    }

    public function getCachedRates(ProductIntakeQueue $queueItem): Collection
    {
        return ShippingRateQuote::where('queue_item_id', $queueItem->id)
            ->valid()
            ->cheapest()
            ->get();
    }

    protected function fetchLiveRates(ProductIntakeQueue $queueItem, array $options = []): Collection
    {
        $shipFrom = $this->getShipFromLocation();

        if (! $shipFrom) {
            throw new Exception('No ship-from location configured for this organization.');
        }

        $dimensions = $this->getDimensionsFromQueueItem($queueItem);

        // Check if dimensions are valid for rate lookup
        if (! $this->hasValidDimensions($dimensions)) {
            throw new Exception('Invalid or missing package dimensions.');
        }

        $provider = OrganizationSettings::get(
            $this->organization->id,
            'fulfillment_shipping_provider',
            'shipstation'
        );

        $rates = match ($provider) {
            'easypost' => $this->fetchEasyPostRates($shipFrom, $dimensions, $options),
            'shippingeasy' => $this->fetchShippingEasyRates($shipFrom, $dimensions, $options),
            default => $this->fetchShipStationRates($shipFrom, $dimensions, $options),
        };

        // Store rates in cache
        $this->cacheRates($queueItem, $rates, $shipFrom, $dimensions, $provider, $options);

        return ShippingRateQuote::where('queue_item_id', $queueItem->id)
            ->valid()
            ->cheapest()
            ->get();
    }

    protected function getShipFromLocation(): ?InventoryLocation
    {
        return InventoryLocation::where('organization_id', $this->organization->id)
            ->where('is_ship_from', true)
            ->where('is_active', true)
            ->first();
    }

    protected function getDimensionsFromQueueItem(ProductIntakeQueue $queueItem): array
    {
        $enrichedData = $queueItem->enriched_data ?? [];

        return [
            'weight_lbs' => $queueItem->weight_lbs
                ?? $enrichedData['weight_lbs']
                ?? $enrichedData['physical_measurements']['weight_lbs']
                ?? null,
            'length' => $queueItem->length_inches
                ?? $enrichedData['length_inches']
                ?? $enrichedData['physical_measurements']['length_inches']
                ?? null,
            'width' => $queueItem->width_inches
                ?? $enrichedData['width_inches']
                ?? $enrichedData['physical_measurements']['width_inches']
                ?? null,
            'height' => $queueItem->height_inches
                ?? $enrichedData['height_inches']
                ?? $enrichedData['physical_measurements']['height_inches']
                ?? null,
        ];
    }

    protected function hasValidDimensions(array $dimensions): bool
    {
        return ! empty($dimensions['weight_lbs']) && $dimensions['weight_lbs'] > 0;
    }

    protected function fetchShipStationRates(InventoryLocation $shipFrom, array $dimensions, array $options = []): array
    {
        try {
            $service = new ShipStationService($this->organization);

            // ShipStation uses ounces
            $weightOz = ($dimensions['weight_lbs'] ?? 1) * 16;

            $rateRequest = [
                'carrierCode' => null, // Get rates from all carriers
                'fromPostalCode' => $shipFrom->postal_code,
                'toPostalCode' => '10001', // Default to NYC for estimate
                'toCountry' => 'US',
                'weight' => [
                    'value' => $weightOz,
                    'units' => 'ounces',
                ],
                'dimensions' => [
                    'length' => $dimensions['length'] ?? 12,
                    'width' => $dimensions['width'] ?? 9,
                    'height' => $dimensions['height'] ?? 4,
                    'units' => 'inches',
                ],
                'confirmation' => $options['confirmation'] ?? 'none',
                'insurance_value' => $options['insurance_value'] ?? null,
            ];

            return $service->getRatesForPackage($rateRequest);

        } catch (Exception $e) {
            Log::warning('ShipStation rate fetch failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    protected function fetchEasyPostRates(InventoryLocation $shipFrom, array $dimensions, array $options = []): array
    {
        try {
            $service = new EasyPostService($this->organization);

            // EasyPost uses ounces
            $weightOz = ($dimensions['weight_lbs'] ?? 1) * 16;

            return $service->getRatesForPackage([
                'from_postal_code' => $shipFrom->postal_code,
                'from_country' => $shipFrom->country ?? 'US',
                'to_postal_code' => '10001',
                'to_street1' => '123 Main St',
                'to_city' => 'New York',
                'to_state' => 'NY',
                'to_country' => 'US',
                'weight_oz' => $weightOz,
                'length' => $dimensions['length'] ?? 12,
                'width' => $dimensions['width'] ?? 9,
                'height' => $dimensions['height'] ?? 4,
                'confirmation' => $options['confirmation'] ?? 'none',
                'insurance_value' => $options['insurance_value'] ?? null,
            ]);

        } catch (Exception $e) {
            Log::warning('EasyPost rate fetch failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    protected function fetchShippingEasyRates(InventoryLocation $shipFrom, array $dimensions, array $options = []): array
    {
        try {
            $service = new ShippingEasyService($this->organization);

            $weightOz = ($dimensions['weight_lbs'] ?? 1) * 16;

            return $service->getRatesForPackage([
                'from_postal_code' => $shipFrom->postal_code,
                'to_postal_code' => '10001',
                'weight_oz' => $weightOz,
                'length' => $dimensions['length'] ?? 12,
                'width' => $dimensions['width'] ?? 9,
                'height' => $dimensions['height'] ?? 4,
                'confirmation' => $options['confirmation'] ?? 'none',
                'insurance_value' => $options['insurance_value'] ?? null,
            ]);

        } catch (Exception $e) {
            Log::warning('ShippingEasy rate fetch failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    protected function cacheRates(
        ProductIntakeQueue $queueItem,
        array $rates,
        InventoryLocation $shipFrom,
        array $dimensions,
        string $provider,
        array $options = []
    ): void {
        // Delete old rates for this queue item
        ShippingRateQuote::where('queue_item_id', $queueItem->id)->delete();

        // Load org settings for additional item cost calculation
        $shippingSettings = OrganizationSettings::get(
            $this->organization->id,
            'fulfillment_shipping',
            []
        );
        $additionalItemMode = $shippingSettings['additional_item_cost_mode'] ?? 'percentage';
        $additionalItemPercent = (float) ($shippingSettings['additional_item_cost_percent'] ?? 50);
        $additionalItemFixed = (float) ($shippingSettings['additional_item_cost_fixed'] ?? 3.00);

        $confirmation = $options['confirmation'] ?? 'none';
        $insuranceValue = $options['insurance_value'] ?? null;

        foreach ($rates as $rate) {
            $baseRate = (float) ($rate['rate'] ?? $rate['shipmentCost'] ?? $rate['cost'] ?? 0);
            $insuranceCost = $insuranceValue ? (float) ($rate['insurance_cost'] ?? 0) : null;

            // Compute additional item cost
            $additionalItemCost = $additionalItemMode === 'fixed'
                ? $additionalItemFixed
                : round($baseRate * ($additionalItemPercent / 100), 2);

            ShippingRateQuote::create([
                'organization_id' => $this->organization->id,
                'queue_item_id' => $queueItem->id,
                'carrier' => $rate['carrier'] ?? $rate['carrierCode'] ?? 'Unknown',
                'service' => $rate['service'] ?? $rate['serviceName'] ?? 'Standard',
                'service_code' => $rate['serviceCode'] ?? $rate['service_code'] ?? null,
                'rate' => $baseRate,
                'currency' => $rate['currency'] ?? 'USD',
                'estimated_days' => $rate['est_delivery_days'] ?? $rate['deliveryDays'] ?? null,
                'is_guaranteed' => $rate['delivery_date_guaranteed'] ?? false,
                'insurance_included' => $insuranceValue && $insuranceValue > 0,
                'insurance_cost' => $insuranceCost,
                'insured_value' => $insuranceValue,
                'confirmation_type' => $confirmation,
                'additional_item_cost' => $additionalItemCost,
                'weight_lbs' => $dimensions['weight_lbs'],
                'length_inches' => $dimensions['length'],
                'width_inches' => $dimensions['width'],
                'height_inches' => $dimensions['height'],
                'from_postal_code' => $shipFrom->postal_code,
                'to_postal_code' => '10001',
                'provider' => $provider,
                'expires_at' => now()->addHour(),
            ]);
        }
    }

    public function getEstimatedRates(ProductIntakeQueue $queueItem): Collection
    {
        $dimensions = $this->getDimensionsFromQueueItem($queueItem);
        $shipFrom = $this->getShipFromLocation();

        $analysis = $this->shippingClassService->analyzeShipping([
            'weight' => $dimensions['weight_lbs'] ?? 1,
            'length' => $dimensions['length'] ?? 12,
            'width' => $dimensions['width'] ?? 9,
            'height' => $dimensions['height'] ?? 4,
        ]);

        $shippingClass = $analysis['shipping_class'];
        $estimatedCost = $analysis['estimated_cost'];
        $costRange = $analysis['cost_range'];

        // Delete old estimates
        ShippingRateQuote::where('queue_item_id', $queueItem->id)->delete();

        // Create estimated rates based on shipping class
        $estimates = $this->buildEstimatedRates($shippingClass, $estimatedCost, $costRange);

        foreach ($estimates as $estimate) {
            ShippingRateQuote::create([
                'organization_id' => $this->organization->id,
                'queue_item_id' => $queueItem->id,
                'carrier' => $estimate['carrier'],
                'service' => $estimate['service'],
                'service_code' => $estimate['service_code'],
                'rate' => $estimate['rate'],
                'currency' => 'USD',
                'estimated_days' => $estimate['estimated_days'],
                'is_guaranteed' => false,
                'weight_lbs' => $dimensions['weight_lbs'],
                'length_inches' => $dimensions['length'],
                'width_inches' => $dimensions['width'],
                'height_inches' => $dimensions['height'],
                'from_postal_code' => $shipFrom?->postal_code,
                'to_postal_code' => '10001',
                'provider' => 'estimate',
                'expires_at' => now()->addHours(24),
            ]);
        }

        return ShippingRateQuote::where('queue_item_id', $queueItem->id)
            ->valid()
            ->cheapest()
            ->get();
    }

    protected function buildEstimatedRates(string $shippingClass, float $estimatedCost, array $costRange): array
    {
        return match ($shippingClass) {
            'small_letter', 'large_letter' => [
                [
                    'carrier' => 'USPS',
                    'service' => 'First Class Mail',
                    'service_code' => 'usps_first_class',
                    'rate' => $costRange['min'] ?? $estimatedCost * 0.8,
                    'estimated_days' => 5,
                ],
                [
                    'carrier' => 'USPS',
                    'service' => 'Priority Mail',
                    'service_code' => 'usps_priority',
                    'rate' => $costRange['typical'] ?? $estimatedCost,
                    'estimated_days' => 3,
                ],
            ],
            'small_package' => [
                [
                    'carrier' => 'USPS',
                    'service' => 'Priority Mail',
                    'service_code' => 'usps_priority',
                    'rate' => $costRange['min'] ?? $estimatedCost * 0.9,
                    'estimated_days' => 3,
                ],
                [
                    'carrier' => 'UPS',
                    'service' => 'Ground',
                    'service_code' => 'ups_ground',
                    'rate' => $costRange['typical'] ?? $estimatedCost,
                    'estimated_days' => 5,
                ],
                [
                    'carrier' => 'FedEx',
                    'service' => 'Ground',
                    'service_code' => 'fedex_ground',
                    'rate' => $costRange['typical'] ?? $estimatedCost,
                    'estimated_days' => 5,
                ],
            ],
            'medium_package', 'large_package' => [
                [
                    'carrier' => 'UPS',
                    'service' => 'Ground',
                    'service_code' => 'ups_ground',
                    'rate' => $costRange['min'] ?? $estimatedCost * 0.9,
                    'estimated_days' => 5,
                ],
                [
                    'carrier' => 'FedEx',
                    'service' => 'Ground',
                    'service_code' => 'fedex_ground',
                    'rate' => $costRange['typical'] ?? $estimatedCost,
                    'estimated_days' => 5,
                ],
                [
                    'carrier' => 'USPS',
                    'service' => 'Priority Mail',
                    'service_code' => 'usps_priority',
                    'rate' => $costRange['max'] ?? $estimatedCost * 1.1,
                    'estimated_days' => 3,
                ],
            ],
            'oversized' => [
                [
                    'carrier' => 'FedEx',
                    'service' => 'Freight',
                    'service_code' => 'fedex_freight',
                    'rate' => $costRange['min'] ?? $estimatedCost * 0.9,
                    'estimated_days' => 7,
                ],
                [
                    'carrier' => 'UPS',
                    'service' => 'Freight',
                    'service_code' => 'ups_freight',
                    'rate' => $costRange['typical'] ?? $estimatedCost,
                    'estimated_days' => 7,
                ],
            ],
            default => [
                [
                    'carrier' => 'USPS',
                    'service' => 'Priority Mail',
                    'service_code' => 'usps_priority',
                    'rate' => $estimatedCost,
                    'estimated_days' => 3,
                ],
            ],
        };
    }

    public function clearCachedRates(ProductIntakeQueue $queueItem): void
    {
        ShippingRateQuote::where('queue_item_id', $queueItem->id)->delete();
    }

    public static function cleanupExpiredQuotes(): int
    {
        return ShippingRateQuote::cleanupExpired();
    }
}
