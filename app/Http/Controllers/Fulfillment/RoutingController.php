<?php

namespace App\Http\Controllers\Fulfillment;

use App\Http\Controllers\Controller;
use App\Models\Fulfillment\FulfillmentRoutingRule;
use App\Models\Fulfillment\Order;
use App\Models\Inventory\InventoryLocation;
use App\Services\Fulfillment\FulfillmentRouter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoutingController extends Controller
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

        $rules = FulfillmentRoutingRule::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->orderBy('priority', 'asc')
            ->get()
            ->map(function ($rule) {
                return [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'rule_type' => $rule->rule_type,
                    'type_label' => FulfillmentRoutingRule::typeLabel($rule->rule_type),
                    'priority' => $rule->priority,
                    'is_active' => $rule->is_active,
                    'parameters' => $rule->parameters,
                    'created_at' => $rule->created_at->toDateTimeString(),
                ];
            });

        return Inertia::render('Fulfillment/Routing/Index', [
            'rules' => $rules,
        ]);
    }

    public function create(Request $request)
    {
        $orgId = $this->getOrganizationId();

        $locations = InventoryLocation::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->select('id', 'name', 'code', 'city', 'state', 'postal_code')
            ->orderBy('name')
            ->get();

        return Inertia::render('Fulfillment/Routing/Create', [
            'locations' => $locations,
            'ruleTypes' => FulfillmentRoutingRule::RULE_TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rule_type' => 'required|string|in:'.implode(',', FulfillmentRoutingRule::RULE_TYPES),
            'priority' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'parameters' => 'nullable|array',
            'parameters.location_id' => 'nullable|string',
        ]);

        $orgId = $this->getOrganizationId();

        FulfillmentRoutingRule::create([
            'organization_id' => $orgId,
            'name' => $validated['name'],
            'rule_type' => $validated['rule_type'],
            'priority' => $validated['priority'],
            'is_active' => $validated['is_active'] ?? true,
            'parameters' => $validated['parameters'] ?? [],
        ]);

        return redirect()->route('fulfillment.routing.index')
            ->with('success', 'Routing rule created successfully.');
    }

    public function edit(FulfillmentRoutingRule $rule)
    {
        $orgId = $this->getOrganizationId();

        $locations = InventoryLocation::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->select('id', 'name', 'code', 'city', 'state', 'postal_code')
            ->orderBy('name')
            ->get();

        return Inertia::render('Fulfillment/Routing/Edit', [
            'rule' => [
                'id' => $rule->id,
                'name' => $rule->name,
                'rule_type' => $rule->rule_type,
                'priority' => $rule->priority,
                'is_active' => $rule->is_active,
                'parameters' => $rule->parameters,
            ],
            'locations' => $locations,
            'ruleTypes' => FulfillmentRoutingRule::RULE_TYPES,
        ]);
    }

    public function update(Request $request, FulfillmentRoutingRule $rule)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rule_type' => 'required|string|in:'.implode(',', FulfillmentRoutingRule::RULE_TYPES),
            'priority' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'parameters' => 'nullable|array',
            'parameters.location_id' => 'nullable|string',
        ]);

        $rule->update([
            'name' => $validated['name'],
            'rule_type' => $validated['rule_type'],
            'priority' => $validated['priority'],
            'is_active' => $validated['is_active'] ?? true,
            'parameters' => $validated['parameters'] ?? [],
        ]);

        return redirect()->route('fulfillment.routing.index')
            ->with('success', 'Routing rule updated successfully.');
    }

    public function destroy(FulfillmentRoutingRule $rule)
    {
        $rule->delete();

        return redirect()->route('fulfillment.routing.index')
            ->with('success', 'Routing rule deleted.');
    }

    public function toggle(FulfillmentRoutingRule $rule)
    {
        $rule->update(['is_active' => ! $rule->is_active]);

        return redirect()->route('fulfillment.routing.index')
            ->with('success', 'Routing rule '.($rule->is_active ? 'activated' : 'deactivated').'.');
    }

    public function test(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|string|exists:orders,id',
        ]);

        $order = Order::with('items')->findOrFail($validated['order_id']);

        $router = new FulfillmentRouter;
        $location = $router->selectLocation($order);

        return response()->json([
            'order_id' => $order->id,
            'selected_location' => $location ? [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
                'city' => $location->city,
                'state' => $location->state,
                'postal_code' => $location->postal_code,
            ] : null,
            'message' => $location
                ? "Order would be routed to {$location->name}."
                : 'No matching location found for this order.',
        ]);
    }
}
