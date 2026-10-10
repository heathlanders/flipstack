<?php

namespace App\Http\Controllers\Ebay;

use App\Http\Controllers\Controller;
use App\Models\Ebay\EbayPolicy;
use App\Models\Ebay\EbayPolicyRule;
use App\Models\Integration\IntegrationCredential;
use App\Services\Ebay\EbayPolicyRulesEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EbayPolicyRuleController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential) {
            return Inertia::render('Marketplaces/Ebay/PolicyRules', [
                'error' => 'No eBay credential configured.',
                'rules' => [],
                'policies' => [],
            ]);
        }

        $rules = EbayPolicyRule::where('integration_credential_id', $credential->id)
            ->with(['fulfillmentPolicy', 'paymentPolicy', 'returnPolicy'])
            ->orderByDesc('priority')
            ->get();

        $policies = EbayPolicy::where('integration_credential_id', $credential->id)
            ->where('is_active', true)
            ->orderBy('policy_type')
            ->orderBy('name')
            ->get()
            ->groupBy('policy_type');

        return Inertia::render('Marketplaces/Ebay/PolicyRules', [
            'rules' => $rules,
            'policies' => [
                'fulfillment' => $policies->get('fulfillment', collect())->values(),
                'payment' => $policies->get('payment', collect())->values(),
                'return' => $policies->get('return', collect())->values(),
            ],
            'availableFields' => EbayPolicyRulesEngine::getAvailableFields(),
            'operators' => EbayPolicyRulesEngine::getOperators(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential) {
            return response()->json(['error' => 'No eBay credential configured.'], 400);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|integer|min:0',
            'conditions' => 'required|array|min:1',
            'conditions.*.field' => 'required|string',
            'conditions.*.operator' => 'required|string',
            'conditions.*.value' => 'required',
            'condition_logic' => 'required|in:AND,OR',
            'fulfillment_policy_id' => 'nullable|exists:ebay_policies,id',
            'payment_policy_id' => 'nullable|exists:ebay_policies,id',
            'return_policy_id' => 'nullable|exists:ebay_policies,id',
            'is_active' => 'boolean',
        ]);

        $rule = EbayPolicyRule::create([
            'organization_id' => $user->organization_id,
            'integration_credential_id' => $credential->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'],
            'conditions' => $validated['conditions'],
            'condition_logic' => $validated['condition_logic'],
            'fulfillment_policy_id' => $validated['fulfillment_policy_id'] ?? null,
            'payment_policy_id' => $validated['payment_policy_id'] ?? null,
            'return_policy_id' => $validated['return_policy_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $rule->load(['fulfillmentPolicy', 'paymentPolicy', 'returnPolicy']);

        return response()->json([
            'success' => true,
            'rule' => $rule,
        ]);
    }

    public function update(Request $request, EbayPolicyRule $rule): JsonResponse
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential || $rule->integration_credential_id !== $credential->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|integer|min:0',
            'conditions' => 'required|array|min:1',
            'conditions.*.field' => 'required|string',
            'conditions.*.operator' => 'required|string',
            'conditions.*.value' => 'required',
            'condition_logic' => 'required|in:AND,OR',
            'fulfillment_policy_id' => 'nullable|exists:ebay_policies,id',
            'payment_policy_id' => 'nullable|exists:ebay_policies,id',
            'return_policy_id' => 'nullable|exists:ebay_policies,id',
            'is_active' => 'boolean',
        ]);

        $rule->update($validated);
        $rule->load(['fulfillmentPolicy', 'paymentPolicy', 'returnPolicy']);

        return response()->json([
            'success' => true,
            'rule' => $rule,
        ]);
    }

    public function destroy(Request $request, EbayPolicyRule $rule): JsonResponse
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential || $rule->integration_credential_id !== $credential->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $rule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rule deleted successfully.',
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential) {
            return response()->json(['error' => 'No eBay credential configured.'], 400);
        }

        $validated = $request->validate([
            'rules' => 'required|array',
            'rules.*.id' => 'required|exists:ebay_policy_rules,id',
            'rules.*.priority' => 'required|integer|min:0',
        ]);

        foreach ($validated['rules'] as $ruleData) {
            EbayPolicyRule::where('id', $ruleData['id'])
                ->where('integration_credential_id', $credential->id)
                ->update(['priority' => $ruleData['priority']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rule priorities updated.',
        ]);
    }

    public function test(Request $request): JsonResponse
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential) {
            return response()->json(['error' => 'No eBay credential configured.'], 400);
        }

        $validated = $request->validate([
            'rule_id' => 'nullable|exists:ebay_policy_rules,id',
            'product_data' => 'required|array',
            'product_data.price' => 'nullable|numeric',
            'product_data.weight_lbs' => 'nullable|numeric',
            'product_data.shipping_class' => 'nullable|string',
            'product_data.condition' => 'nullable|string',
            'product_data.category' => 'nullable|string',
            'product_data.estimated_shipping_cost' => 'nullable|numeric',
        ]);

        $rulesEngine = new EbayPolicyRulesEngine($credential);

        if (! empty($validated['rule_id'])) {
            // Test specific rule
            $rule = EbayPolicyRule::find($validated['rule_id']);
            if ($rule->integration_credential_id !== $credential->id) {
                return response()->json(['error' => 'Unauthorized.'], 403);
            }

            $result = $rulesEngine->testRule($rule, $validated['product_data']);
        } else {
            // Test against all rules
            $result = $rulesEngine->getApplicablePolicies($validated['product_data']);
        }

        return response()->json([
            'success' => true,
            'result' => $result,
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
