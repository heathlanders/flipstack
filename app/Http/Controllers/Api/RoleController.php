<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\User\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    use ResolvesOrganization;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        $roles = Role::where(function ($q) use ($organizationId) {
            $q->where('organization_id', $organizationId)
                ->orWhere('is_system', true);
        })
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $roles->map(fn ($r) => $this->formatRole($r)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
            'color' => 'nullable|string|max:7',
            'level' => 'nullable|integer|min:0',
        ]);

        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        try {
            $role = Role::create([
                'name' => $request->name,
                'description' => $request->description,
                'permissions' => $request->permissions ?? [],
                'organization_id' => $organizationId,
                'is_system' => false,
                'is_active' => true,
                'color' => $request->color,
                'level' => $request->level ?? 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Role created',
                'data' => $this->formatRole($role),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(Request $request, Role $role): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if (! $role->is_system && $role->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Role not found'], 404);
        }

        $role->loadCount('users');

        return response()->json([
            'success' => true,
            'data' => array_merge($this->formatRole($role), [
                'users_count' => $role->users_count,
            ]),
        ]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        if ($role->is_system) {
            return response()->json(['success' => false, 'message' => 'Cannot edit system roles'], 422);
        }

        $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
            'color' => 'nullable|string|max:7',
            'level' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($role->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Role not found'], 404);
        }

        try {
            $role->update($request->only(['name', 'description', 'permissions', 'color', 'level', 'is_active']));

            return response()->json([
                'success' => true,
                'message' => 'Role updated',
                'data' => $this->formatRole($role),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        if ($role->is_system) {
            return response()->json(['success' => false, 'message' => 'Cannot delete system roles'], 422);
        }

        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($role->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Role not found'], 404);
        }

        try {
            $role->delete();

            return response()->json([
                'success' => true,
                'message' => 'Role deleted',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function availablePermissions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'inventory' => ['inventory.view', 'inventory.create', 'inventory.edit', 'inventory.delete'],
                'orders' => ['orders.view', 'orders.create', 'orders.edit', 'orders.delete'],
                'fulfillment' => ['fulfillment.view', 'fulfillment.pick', 'fulfillment.pack', 'fulfillment.ship'],
                'analytics' => ['analytics.view', 'analytics.export'],
                'users' => ['users.view', 'users.create', 'users.edit', 'users.delete'],
                'teams' => ['teams.view', 'teams.create', 'teams.edit', 'teams.delete'],
                'settings' => ['settings.view', 'settings.edit'],
                'marketplace' => ['marketplace.view', 'marketplace.sync', 'marketplace.configure'],
            ],
        ]);
    }

    public function updatePermissions(Request $request, Role $role): JsonResponse
    {
        if ($role->is_system) {
            return response()->json(['success' => false, 'message' => 'Cannot modify system role permissions'], 422);
        }

        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string',
        ]);

        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($role->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Role not found'], 404);
        }

        try {
            $role->update(['permissions' => $request->permissions]);

            return response()->json([
                'success' => true,
                'message' => 'Permissions updated',
                'data' => $this->formatRole($role),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function formatRole(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'permissions' => $role->permissions ?? [],
            'is_system' => $role->is_system,
            'is_active' => $role->is_active,
            'is_default' => $role->is_default,
            'color' => $role->color,
            'level' => $role->level,
            'created_at' => $role->created_at?->toIso8601String(),
        ];
    }
}
