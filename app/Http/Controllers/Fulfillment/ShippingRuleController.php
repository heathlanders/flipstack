<?php

namespace App\Http\Controllers\Fulfillment;

use App\Http\Controllers\Controller;
use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\PackagePreset;
use App\Models\Fulfillment\ShippingRule;
use App\Services\Fulfillment\ShippingRuleEngine;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShippingRuleController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    protected function getOrganizationId(): int
    {
        return auth()->user()->currentTeam?->organization_id
            ?? auth()->user()->current_organization_id
            ?? auth()->user()->organization_id;
    }

    public function index(Request $request)
    {
        $orgId = $this->getOrganizationId();

        $rules = ShippingRule::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->ordered()
            ->with('packagePreset')
            ->get();

        $orders = Order::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->whereIn('status', [
                Order::STATUS_PENDING,
                Order::STATUS_ASSIGNED,
                Order::STATUS_PICKING,
                Order::STATUS_PICKED,
                Order::STATUS_PACKING,
                Order::STATUS_PACKED,
            ])
            ->select('id', 'marketplace', 'total_amount', 'shipping_state', 'shipping_country', 'items_count')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return Inertia::render('Fulfillment/ShippingRules/Index', [
            'rules' => $rules,
            'orders' => $orders,
        ]);
    }

    public function create(Request $request)
    {
        $orgId = $this->getOrganizationId();

        $packagePresets = PackagePreset::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->active()
            ->ordered()
            ->get();

        return Inertia::render('Fulfillment/ShippingRules/Create', [
            'packagePresets' => $packagePresets,
            'carriers' => ShippingRule::carriers(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'priority' => 'required|integer|min:0',
            'conditions' => 'nullable|array',
            'conditions.weight_min' => 'nullable|numeric|min:0',
            'conditions.weight_max' => 'nullable|numeric|min:0',
            'conditions.value_min' => 'nullable|numeric|min:0',
            'conditions.value_max' => 'nullable|numeric|min:0',
            'conditions.destination_states' => 'nullable|array',
            'conditions.destination_states.*' => 'string|max:10',
            'conditions.destination_countries' => 'nullable|array',
            'conditions.destination_countries.*' => 'string|max:10',
            'conditions.marketplace' => 'nullable|string|max:50',
            'conditions.item_count_min' => 'nullable|integer|min:0',
            'conditions.item_count_max' => 'nullable|integer|min:0',
            'action_carrier' => 'required|string|in:USPS,UPS,FedEx,DHL',
            'action_service' => 'required|string|max:100',
            'action_package_preset_id' => 'nullable|uuid|exists:package_presets,id',
            'is_active' => 'boolean',
        ]);

        $validated['organization_id'] = $this->getOrganizationId();
        $validated['conditions'] = $this->cleanConditions($validated['conditions'] ?? []);

        ShippingRule::create($validated);

        return redirect()->route('fulfillment.shipping-rules.index')
            ->with('success', 'Shipping rule created.');
    }

    public function edit(ShippingRule $rule)
    {
        $orgId = $this->getOrganizationId();

        $packagePresets = PackagePreset::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->active()
            ->ordered()
            ->get();

        $rule->load('packagePreset');

        return Inertia::render('Fulfillment/ShippingRules/Edit', [
            'rule' => $rule,
            'packagePresets' => $packagePresets,
            'carriers' => ShippingRule::carriers(),
        ]);
    }

    public function update(Request $request, ShippingRule $rule)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'priority' => 'required|integer|min:0',
            'conditions' => 'nullable|array',
            'conditions.weight_min' => 'nullable|numeric|min:0',
            'conditions.weight_max' => 'nullable|numeric|min:0',
            'conditions.value_min' => 'nullable|numeric|min:0',
            'conditions.value_max' => 'nullable|numeric|min:0',
            'conditions.destination_states' => 'nullable|array',
            'conditions.destination_states.*' => 'string|max:10',
            'conditions.destination_countries' => 'nullable|array',
            'conditions.destination_countries.*' => 'string|max:10',
            'conditions.marketplace' => 'nullable|string|max:50',
            'conditions.item_count_min' => 'nullable|integer|min:0',
            'conditions.item_count_max' => 'nullable|integer|min:0',
            'action_carrier' => 'required|string|in:USPS,UPS,FedEx,DHL',
            'action_service' => 'required|string|max:100',
            'action_package_preset_id' => 'nullable|uuid|exists:package_presets,id',
            'is_active' => 'boolean',
        ]);

        $validated['conditions'] = $this->cleanConditions($validated['conditions'] ?? []);

        $rule->update($validated);

        return redirect()->route('fulfillment.shipping-rules.index')
            ->with('success', 'Shipping rule updated.');
    }

    public function destroy(ShippingRule $rule)
    {
        $rule->delete();

        return redirect()->route('fulfillment.shipping-rules.index')
            ->with('success', 'Shipping rule deleted.');
    }

    public function toggle(ShippingRule $rule)
    {
        $rule->update(['is_active' => ! $rule->is_active]);

        return back()->with('success', 'Shipping rule '.($rule->is_active ? 'enabled' : 'disabled').'.');
    }

    public function test(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|uuid|exists:orders,id',
        ]);

        $order = Order::withoutGlobalScope('organization')
            ->where('organization_id', $this->getOrganizationId())
            ->with('items')
            ->findOrFail($validated['order_id']);

        $engine = new ShippingRuleEngine;
        $result = $engine->testOrder($order);

        return response()->json($result);
    }

    /**
     * Remove empty/null values from conditions to keep the JSON clean.
     */
    protected function cleanConditions(array $conditions): array
    {
        $cleaned = [];

        foreach ($conditions as $key => $value) {
            if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                continue;
            }
            $cleaned[$key] = $value;
        }

        return $cleaned;
    }
}
