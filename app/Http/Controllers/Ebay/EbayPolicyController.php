<?php

namespace App\Http\Controllers\Ebay;

use App\Http\Controllers\Controller;
use App\Models\Ebay\EbayPolicy;
use App\Models\Integration\IntegrationCredential;
use App\Services\Ebay\EbayPolicyRulesEngine;
use App\Services\Ebay\EbayPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EbayPolicyController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'No eBay credential configured.',
                    'policies' => [],
                ]);
            }

            return Inertia::render('Marketplaces/Ebay/Policies', [
                'error' => 'No eBay credential configured.',
                'policies' => [],
            ]);
        }

        $policies = EbayPolicy::where('integration_credential_id', $credential->id)
            ->where('is_active', true)
            ->orderBy('policy_type')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->groupBy('policy_type');

        $policiesData = [
            'fulfillment' => $policies->get('fulfillment', collect())->values(),
            'payment' => $policies->get('payment', collect())->values(),
            'return' => $policies->get('return', collect())->values(),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'credential' => [
                    'id' => $credential->id,
                    'fulfillment_policy_id' => $credential->fulfillment_policy_id,
                    'payment_policy_id' => $credential->payment_policy_id,
                    'return_policy_id' => $credential->return_policy_id,
                ],
                'policies' => $policiesData,
            ]);
        }

        return Inertia::render('Marketplaces/Ebay/Policies', [
            'credential' => [
                'id' => $credential->id,
                'fulfillment_policy_id' => $credential->fulfillment_policy_id,
                'payment_policy_id' => $credential->payment_policy_id,
                'return_policy_id' => $credential->return_policy_id,
            ],
            'policies' => $policiesData,
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential) {
            return response()->json(['error' => 'No eBay credential configured.'], 400);
        }

        try {
            $service = new EbayPolicyService($credential);
            $result = $service->syncAllPolicies();

            return response()->json([
                'success' => true,
                'message' => "Synced {$result['total']} policies from eBay.",
                'counts' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential) {
            return response()->json(['error' => 'No eBay credential configured.'], 400);
        }

        $validated = $request->validate([
            'policy_type' => 'required|in:fulfillment,payment,return',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            // Fulfillment specific
            'handling_time' => 'nullable|integer|min:0|max:30',
            'domestic_shipping' => 'nullable|array',
            // Payment specific
            'immediate_pay' => 'nullable|boolean',
            'payment_methods' => 'nullable|array',
            // Return specific
            'returns_accepted' => 'nullable|boolean',
            'return_period_days' => 'nullable|integer|in:14,30,60',
            'return_shipping_cost_payer' => 'nullable|in:BUYER,SELLER',
            'refund_method' => 'nullable|in:MONEY_BACK,MERCHANDISE_CREDIT',
        ]);

        try {
            $service = new EbayPolicyService($credential);

            $policy = match ($validated['policy_type']) {
                'fulfillment' => $service->createFulfillmentPolicy($validated),
                'payment' => $service->createPaymentPolicy($validated),
                'return' => $service->createReturnPolicy($validated),
            };

            return response()->json([
                'success' => true,
                'policy' => $policy,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, EbayPolicy $policy): JsonResponse
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential || $policy->integration_credential_id !== $credential->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            // Fulfillment specific
            'handling_time' => 'nullable|integer|min:0|max:30',
            'domestic_shipping' => 'nullable|array',
            // Payment specific
            'immediate_pay' => 'nullable|boolean',
            'payment_methods' => 'nullable|array',
            // Return specific
            'returns_accepted' => 'nullable|boolean',
            'return_period_days' => 'nullable|integer|in:14,30,60',
            'return_shipping_cost_payer' => 'nullable|in:BUYER,SELLER',
            'refund_method' => 'nullable|in:MONEY_BACK,MERCHANDISE_CREDIT',
        ]);

        try {
            $service = new EbayPolicyService($credential);

            $updatedPolicy = match ($policy->policy_type) {
                'fulfillment' => $service->updateFulfillmentPolicy($policy, $validated),
                'payment' => $service->updatePaymentPolicy($policy, $validated),
                'return' => $service->updateReturnPolicy($policy, $validated),
            };

            return response()->json([
                'success' => true,
                'policy' => $updatedPolicy,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, EbayPolicy $policy): JsonResponse
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential || $policy->integration_credential_id !== $credential->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        try {
            $service = new EbayPolicyService($credential);

            match ($policy->policy_type) {
                'fulfillment' => $service->deleteFulfillmentPolicy($policy),
                'payment' => $service->deletePaymentPolicy($policy),
                'return' => $service->deleteReturnPolicy($policy),
            };

            return response()->json([
                'success' => true,
                'message' => 'Policy deleted successfully.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function setDefault(Request $request, EbayPolicy $policy): JsonResponse
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential || $policy->integration_credential_id !== $credential->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $policy->markAsDefault();

        // Also update the credential's policy ID for backward compatibility
        $field = match ($policy->policy_type) {
            'fulfillment' => 'fulfillment_policy_id',
            'payment' => 'payment_policy_id',
            'return' => 'return_policy_id',
        };

        $credential->update([$field => $policy->ebay_policy_id]);

        return response()->json([
            'success' => true,
            'message' => "{$policy->name} set as default.",
        ]);
    }

    public function evaluateForProduct(Request $request): JsonResponse
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential) {
            return response()->json(['error' => 'No eBay credential configured.'], 400);
        }

        $productData = $request->validate([
            'price' => 'nullable|numeric',
            'weight_lbs' => 'nullable|numeric',
            'shipping_class' => 'nullable|string',
            'condition' => 'nullable|string',
            'category' => 'nullable|string',
            'estimated_shipping_cost' => 'nullable|numeric',
        ]);

        $rulesEngine = new EbayPolicyRulesEngine($credential);
        $result = $rulesEngine->getApplicablePolicies($productData);

        // Fetch full policy details
        $policies = [];
        foreach (['fulfillment', 'payment', 'return'] as $type) {
            if (! empty($result[$type])) {
                $policy = EbayPolicy::where('integration_credential_id', $credential->id)
                    ->where('ebay_policy_id', $result[$type])
                    ->first();
                $policies[$type] = $policy ? [
                    'id' => $policy->id,
                    'ebay_policy_id' => $policy->ebay_policy_id,
                    'name' => $policy->name,
                ] : null;
            }
        }

        return response()->json([
            'success' => true,
            'policy_ids' => [
                'fulfillment' => $result['fulfillment'],
                'payment' => $result['payment'],
                'return' => $result['return'],
            ],
            'policies' => $policies,
            'matched_rule' => $result['matched_rule'],
        ]);
    }

    protected function getCredential(int $organizationId): ?IntegrationCredential
    {
        return IntegrationCredential::where('organization_id', $organizationId)
            ->where('platform', 'ebay')
            ->where('is_active', true)
            ->first();
    }
}
