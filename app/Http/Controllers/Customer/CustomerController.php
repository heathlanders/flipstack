<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Services\Customer\CustomerMatchingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerMatchingService $matchingService
    ) {
        $this->middleware(['auth']);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $query = Customer::where('organization_id', $organizationId);

        if ($request->filled('search')) {
            $query->search($request->get('search'));
        }

        if ($request->filled('marketplace')) {
            $query->byMarketplace($request->get('marketplace'));
        }

        if ($request->boolean('flagged')) {
            $query->flagged();
        }

        if ($request->boolean('repeat')) {
            $query->repeatBuyers();
        }

        $sortBy = $request->get('sort', 'last_order_at');
        $sortDir = $request->get('direction', 'desc');
        $allowedSorts = ['name', 'email', 'total_orders', 'total_spent', 'last_order_at', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('last_order_at');
        }

        $customers = $query->paginate(20)->withQueryString();

        // Stats
        $base = Customer::withoutGlobalScopes()->where('organization_id', $organizationId);
        $stats = [
            'total' => (clone $base)->count(),
            'repeat_buyers' => (clone $base)->where('total_orders', '>', 1)->count(),
            'total_revenue' => (clone $base)->sum('total_spent'),
            'avg_ltv' => (clone $base)->avg('total_spent'),
            'flagged' => (clone $base)->where('is_flagged', true)->count(),
        ];

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'stats' => $stats,
            'filters' => $request->only(['search', 'marketplace', 'flagged', 'repeat', 'sort', 'direction']),
        ]);
    }

    public function show(Customer $customer): Response
    {
        $customer->load(['addresses', 'orders' => function ($q) {
            $q->orderByDesc('created_at')->limit(20);
        }]);

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
        ]);
    }

    public function edit(Customer $customer): Response
    {
        $customer->load('addresses');

        return Inertia::render('Customers/Edit', [
            'customer' => $customer,
        ]);
    }

    public function update(Request $request, Customer $customer): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_flagged' => ['nullable', 'boolean'],
            'flag_reason' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'array'],
        ]);

        $customer->update($validated);

        return redirect()->route('customers.show', $customer->id)->with('success', 'Customer updated.');
    }

    public function merge(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'primary_id' => ['required', 'uuid', 'exists:customers,id'],
            'secondary_id' => ['required', 'uuid', 'exists:customers,id', 'different:primary_id'],
        ]);

        $primary = Customer::findOrFail($validated['primary_id']);
        $secondary = Customer::findOrFail($validated['secondary_id']);

        $this->matchingService->mergeCustomers($primary, $secondary);

        return redirect()->route('customers.show', $primary->id)->with('success', 'Customers merged successfully.');
    }
}
