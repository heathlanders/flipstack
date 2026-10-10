<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Main dashboard route - redirects to role-specific dashboard
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Redirect supplier users to the supplier portal
        if ($user->is_supplier && $user->supplier_status === \App\Enums\Supplier\SupplierStatus::ACTIVE) {
            return redirect()->route('supplier.dashboard');
        }

        $dashboardType = $user->getDashboardType();

        // Debug output - remove this after testing
        \Log::info('Dashboard Debug:', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'organization_id' => $user->organization_id,
            'current_team_id' => $user->current_team_id,
            'primary_role' => $user->getPrimaryRole(),
            'dashboard_type' => $dashboardType,
            'roles_count' => $user->roles()->count(),
        ]);

        // Route to appropriate dashboard based on user role
        return match ($dashboardType) {
            'owner' => app(MainDashboardController::class)->show($request),
            'buyer' => app(BuyerDashboardController::class)->show($request),
            'lister' => app(ListerDashboardController::class)->show($request),
            'fulfillment' => app(FulfillmentDashboardController::class)->show($request),
            'support' => app(SupportDashboardController::class)->show($request),
            default => $this->basicDashboard($request)
        };
    }

    /**
     * Fallback dashboard for users without specific roles
     */
    protected function basicDashboard(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Dashboard/Basic', [
            'dashboardType' => 'basic',
            'user' => $user,
            'permissions' => $user->getEffectivePermissions(),
            'quickActions' => $user->getQuickActions(),
            'availableFeatures' => [
                'inventory' => $user->can('inventory.view'),
                'orders' => $user->can('orders.view'),
                'analytics' => $user->can('analytics.view'),
                'settings' => $user->can('settings.view'),
            ],
        ]);
    }
}
