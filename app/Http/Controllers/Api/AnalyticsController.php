<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\User\UserActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    use ResolvesOrganization;

    public function trackActivity(Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'properties' => 'nullable|array',
        ]);

        try {
            UserActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => $request->action,
                'description' => $request->description,
                'properties' => $request->properties ?? [],
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Activity tracked',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function teamPerformance(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        $teamStats = DB::table('teams')
            ->where('teams.organization_id', $organizationId)
            ->whereNull('teams.deleted_at')
            ->leftJoin('team_user', 'teams.id', '=', 'team_user.team_id')
            ->select([
                'teams.id',
                'teams.name',
                'teams.department',
                DB::raw('COUNT(DISTINCT team_user.user_id) as member_count'),
            ])
            ->groupBy('teams.id', 'teams.name', 'teams.department')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $teamStats->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'department' => $t->department,
                'member_count' => (int) $t->member_count,
            ]),
        ]);
    }

    public function userProductivity(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        $today = now()->startOfDay();
        $weekStart = now()->startOfWeek();

        $ordersToday = DB::table('orders')
            ->where('organization_id', $organizationId)
            ->where('assigned_picker_id', $user->id)
            ->where('updated_at', '>=', $today)
            ->whereIn('status', ['picked', 'packing', 'packed', 'shipped', 'delivered'])
            ->count();

        $ordersThisWeek = DB::table('orders')
            ->where('organization_id', $organizationId)
            ->where('assigned_picker_id', $user->id)
            ->where('updated_at', '>=', $weekStart)
            ->whereIn('status', ['picked', 'packing', 'packed', 'shipped', 'delivered'])
            ->count();

        $shipmentsToday = DB::table('shipments')
            ->where('organization_id', $organizationId)
            ->where('shipped_at', '>=', $today)
            ->count();

        $shipmentsThisWeek = DB::table('shipments')
            ->where('organization_id', $organizationId)
            ->where('shipped_at', '>=', $weekStart)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'orders_picked_today' => $ordersToday,
                'orders_picked_this_week' => $ordersThisWeek,
                'shipments_today' => $shipmentsToday,
                'shipments_this_week' => $shipmentsThisWeek,
            ],
        ]);
    }

    public function organizationOverview(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        $monthStart = now()->startOfMonth();

        $totalProducts = DB::table('products')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->count();

        $totalOrders = DB::table('orders')
            ->where('organization_id', $organizationId)
            ->count();

        $ordersThisMonth = DB::table('orders')
            ->where('organization_id', $organizationId)
            ->where('created_at', '>=', $monthStart)
            ->count();

        $revenueThisMonth = DB::table('orders')
            ->where('organization_id', $organizationId)
            ->where('created_at', '>=', $monthStart)
            ->whereNotIn('status', ['cancelled', 'returned'])
            ->sum('total_amount');

        $pendingOrders = DB::table('orders')
            ->where('organization_id', $organizationId)
            ->whereNotIn('status', ['delivered', 'cancelled', 'returned'])
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_products' => $totalProducts,
                'total_orders' => $totalOrders,
                'orders_this_month' => $ordersThisMonth,
                'revenue_this_month' => round((float) $revenueThisMonth, 2),
                'pending_orders' => $pendingOrders,
            ],
        ]);
    }
}
