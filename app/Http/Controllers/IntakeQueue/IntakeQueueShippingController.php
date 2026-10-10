<?php

namespace App\Http\Controllers\IntakeQueue;

use App\Http\Controllers\Controller;
use App\Models\Catalog\ProductIntakeQueue;
use App\Models\Organization\OrganizationSettings;
use App\Services\Shipping\IntakeShippingRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntakeQueueShippingController extends Controller
{
    public function getRates(Request $request, ProductIntakeQueue $queueItem): JsonResponse
    {
        $user = $request->user();

        if ($queueItem->organization_id !== $user->organization_id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        try {
            $options = $this->extractShippingOptions($request);
            $service = new IntakeShippingRateService($user->organization);
            $rates = $service->getRatesForQueueItem($queueItem, $options);

            return response()->json([
                'success' => true,
                'rates' => $rates->map(fn ($rate) => $this->mapRateToResponse($rate)),
                'dimensions' => [
                    'weight_lbs' => $queueItem->weight_lbs,
                    'length_inches' => $queueItem->length_inches,
                    'width_inches' => $queueItem->width_inches,
                    'height_inches' => $queueItem->height_inches,
                ],
                'shipping_settings' => $this->getShippingSettings($user->organization_id),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getCachedRates(Request $request, ProductIntakeQueue $queueItem): JsonResponse
    {
        $user = $request->user();

        if ($queueItem->organization_id !== $user->organization_id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $service = new IntakeShippingRateService($user->organization);
        $rates = $service->getCachedRates($queueItem);

        return response()->json([
            'success' => true,
            'rates' => $rates->map(fn ($rate) => $this->mapRateToResponse($rate)),
            'has_valid_rates' => $rates->isNotEmpty(),
            'shipping_settings' => $this->getShippingSettings($user->organization_id),
        ]);
    }

    public function refreshRates(Request $request, ProductIntakeQueue $queueItem): JsonResponse
    {
        $user = $request->user();

        if ($queueItem->organization_id !== $user->organization_id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        try {
            $options = $this->extractShippingOptions($request);
            $service = new IntakeShippingRateService($user->organization);

            // Clear existing cache and fetch fresh rates
            $service->clearCachedRates($queueItem);
            $rates = $service->getRatesForQueueItem($queueItem, $options);

            return response()->json([
                'success' => true,
                'message' => 'Rates refreshed successfully.',
                'rates' => $rates->map(fn ($rate) => $this->mapRateToResponse($rate)),
                'shipping_settings' => $this->getShippingSettings($user->organization_id),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function extractShippingOptions(Request $request): array
    {
        $options = [];

        if ($request->has('confirmation') && $request->input('confirmation') !== 'none') {
            $options['confirmation'] = $request->input('confirmation');
        }

        if ($request->has('insurance_value') && $request->input('insurance_value') > 0) {
            $options['insurance_value'] = (float) $request->input('insurance_value');
        }

        return $options;
    }

    private function mapRateToResponse($rate): array
    {
        return [
            'id' => $rate->id,
            'carrier' => $rate->carrier,
            'service' => $rate->service,
            'service_code' => $rate->service_code,
            'rate' => $rate->rate,
            'formatted_rate' => $rate->formatted_rate,
            'total_rate' => $rate->total_rate,
            'formatted_total_rate' => $rate->formatted_total_rate,
            'currency' => $rate->currency,
            'estimated_days' => $rate->estimated_days,
            'estimated_delivery' => $rate->estimated_delivery,
            'is_guaranteed' => $rate->is_guaranteed,
            'display_name' => $rate->display_name,
            'provider' => $rate->provider,
            'is_estimate' => $rate->provider === 'estimate',
            'insurance_included' => $rate->insurance_included,
            'insurance_cost' => $rate->insurance_cost,
            'insured_value' => $rate->insured_value,
            'confirmation_type' => $rate->confirmation_type,
            'additional_item_cost' => $rate->additional_item_cost,
            'formatted_additional_item_cost' => $rate->formatted_additional_item_cost,
        ];
    }

    private function getShippingSettings(int $organizationId): array
    {
        $settings = OrganizationSettings::get($organizationId, 'fulfillment_shipping', []);

        return [
            'auto_insurance_enabled' => $settings['auto_insurance_enabled'] ?? false,
            'auto_insurance_threshold' => (float) ($settings['auto_insurance_threshold'] ?? 100),
            'signature_confirmation' => $settings['signature_confirmation'] ?? 'none',
            'signature_threshold' => (float) ($settings['signature_threshold'] ?? 250),
            'additional_item_cost_mode' => $settings['additional_item_cost_mode'] ?? 'percentage',
            'additional_item_cost_percent' => (float) ($settings['additional_item_cost_percent'] ?? 50),
            'additional_item_cost_fixed' => (float) ($settings['additional_item_cost_fixed'] ?? 3.00),
        ];
    }
}
