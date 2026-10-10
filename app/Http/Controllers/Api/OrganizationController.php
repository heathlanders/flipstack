<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Organization\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationController extends Controller
{
    use ResolvesOrganization;

    public function info(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        $org = Organization::find($organizationId);

        if (! $org) {
            return response()->json(['success' => false, 'message' => 'Organization not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $org->id,
                'name' => $org->name,
                'slug' => $org->slug,
                'email' => $org->email,
                'phone' => $org->phone,
                'website' => $org->website,
                'logo_url' => $org->logo_url,
                'timezone' => $org->timezone,
                'currency' => $org->currency,
                'subscription_status' => $org->subscription_status,
                'subscription_plan' => $org->subscription_plan,
                'sku_prefix' => $org->sku_prefix,
                'created_at' => $org->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function settings(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        $org = Organization::find($organizationId);

        if (! $org) {
            return response()->json(['success' => false, 'message' => 'Organization not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'settings' => $org->settings ?? [],
                'branding' => $org->branding ?? [],
            ],
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'nullable|array',
            'branding' => 'nullable|array',
        ]);

        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        $org = Organization::find($organizationId);

        if (! $org) {
            return response()->json(['success' => false, 'message' => 'Organization not found'], 404);
        }

        try {
            if ($request->has('settings')) {
                $org->settings = array_merge($org->settings ?? [], $request->settings);
            }

            if ($request->has('branding')) {
                $org->branding = array_merge($org->branding ?? [], $request->branding);
            }

            $org->save();

            return response()->json([
                'success' => true,
                'message' => 'Settings updated',
                'data' => [
                    'settings' => $org->settings,
                    'branding' => $org->branding,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        $userCount = DB::table('users')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->count();

        $teamCount = DB::table('teams')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->count();

        $productCount = DB::table('products')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->count();

        $orderCount = DB::table('orders')
            ->where('organization_id', $organizationId)
            ->count();

        $activeOrderCount = DB::table('orders')
            ->where('organization_id', $organizationId)
            ->whereNotIn('status', ['delivered', 'cancelled', 'returned'])
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'users' => $userCount,
                'teams' => $teamCount,
                'products' => $productCount,
                'orders' => $orderCount,
                'active_orders' => $activeOrderCount,
            ],
        ]);
    }
}
