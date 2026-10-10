<?php

namespace App\Http\Controllers\Pricing;

use App\Http\Controllers\Controller;
use App\Models\Pricing\RepricingLog;
use App\Models\Pricing\RepricingRule;
use App\Services\Pricing\RepricingEngine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RepricingController extends Controller
{
    public function __construct(
        protected RepricingEngine $repricingEngine
    ) {
        $this->middleware(['auth']);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $rules = RepricingRule::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->withCount('logs')
            ->orderBy('priority')
            ->get();

        $stats = [
            'total_rules' => $rules->count(),
            'active_rules' => $rules->where('is_active', true)->count(),
            'recent_changes' => RepricingLog::whereHas('rule', fn ($q) => $q->withoutGlobalScopes()->where('organization_id', $organizationId))
                ->where('applied_at', '>=', now()->subDay())
                ->count(),
        ];

        return Inertia::render('Pricing/Repricing/Index', [
            'rules' => $rules,
            'stats' => $stats,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Pricing/Repricing/Create', [
            'strategies' => $this->getStrategyOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'marketplace' => 'required|string|in:ebay,shopify,amazon,*',
            'scope_type' => 'required|string|in:all,category,product',
            'scope_ids' => 'nullable|array',
            'strategy' => 'required|string|in:match_lowest,beat_by_amount,beat_by_percent,cost_plus,map_floor',
            'parameters' => 'nullable|array',
            'parameters.min_price' => 'nullable|numeric|min:0',
            'parameters.max_price' => 'nullable|numeric|min:0',
            'parameters.beat_amount' => 'nullable|numeric|min:0',
            'parameters.beat_percent' => 'nullable|numeric|min:0|max:100',
            'parameters.cost_plus_margin' => 'nullable|numeric|min:0',
            'priority' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $maxPriority = RepricingRule::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->max('priority') ?? 0;

        RepricingRule::create([
            'organization_id' => $organizationId,
            'name' => $validated['name'],
            'marketplace' => $validated['marketplace'],
            'scope_type' => $validated['scope_type'],
            'scope_ids' => $validated['scope_ids'] ?? null,
            'strategy' => $validated['strategy'],
            'parameters' => $validated['parameters'] ?? [],
            'priority' => $validated['priority'] ?? ($maxPriority + 1),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('pricing.repricing.index')
            ->with('success', 'Repricing rule created.');
    }

    public function edit(Request $request, RepricingRule $rule): Response
    {
        return Inertia::render('Pricing/Repricing/Edit', [
            'rule' => $rule,
            'strategies' => $this->getStrategyOptions(),
        ]);
    }

    public function update(Request $request, RepricingRule $rule)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'marketplace' => 'required|string|in:ebay,shopify,amazon,*',
            'scope_type' => 'required|string|in:all,category,product',
            'scope_ids' => 'nullable|array',
            'strategy' => 'required|string|in:match_lowest,beat_by_amount,beat_by_percent,cost_plus,map_floor',
            'parameters' => 'nullable|array',
            'priority' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $rule->update($validated);

        return redirect()->route('pricing.repricing.index')
            ->with('success', 'Repricing rule updated.');
    }

    public function destroy(RepricingRule $rule)
    {
        $rule->delete();

        return redirect()->route('pricing.repricing.index')
            ->with('success', 'Repricing rule deleted.');
    }

    public function toggle(RepricingRule $rule)
    {
        $rule->update(['is_active' => ! $rule->is_active]);

        return redirect()->back()
            ->with('success', $rule->is_active ? 'Rule activated.' : 'Rule paused.');
    }

    public function logs(Request $request): Response
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $query = RepricingLog::whereHas('rule', fn ($q) => $q->withoutGlobalScopes()->where('organization_id', $organizationId))
            ->with(['rule:id,name,strategy', 'product:id,name,sku'])
            ->orderByDesc('applied_at');

        if ($request->filled('rule_id')) {
            $query->where('repricing_rule_id', $request->get('rule_id'));
        }

        $logs = $query->paginate(50)->withQueryString();

        $rules = RepricingRule::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->select('id', 'name')
            ->get();

        return Inertia::render('Pricing/Repricing/Logs', [
            'logs' => $logs,
            'rules' => $rules,
            'filters' => $request->only('rule_id'),
        ]);
    }

    public function run(Request $request)
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $results = $this->repricingEngine->runForOrganization($organizationId);

        return redirect()->back()
            ->with('success', "Repricing complete: {$results['processed']} products checked, {$results['changed']} prices updated.");
    }

    protected function getStrategyOptions(): array
    {
        return [
            ['value' => 'cost_plus', 'label' => 'Cost Plus Margin', 'description' => 'Set price as cost + margin percentage'],
            ['value' => 'beat_by_amount', 'label' => 'Beat by Amount', 'description' => 'Reduce current price by a fixed dollar amount'],
            ['value' => 'beat_by_percent', 'label' => 'Beat by Percent', 'description' => 'Reduce current price by a percentage'],
            ['value' => 'match_lowest', 'label' => 'Match Lowest', 'description' => 'Match the lowest competitor price'],
            ['value' => 'map_floor', 'label' => 'MAP Floor', 'description' => 'Set price to MSRP (minimum advertised price)'],
        ];
    }
}
