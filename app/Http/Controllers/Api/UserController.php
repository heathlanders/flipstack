<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\User\User;
use App\Models\User\UserActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use ResolvesOrganization;

    private function organizationId(Request $request): int
    {
        return $this->resolveOrganizationId($request->user())
            ?? abort(response()->json([
                'success' => false,
                'message' => 'No organization associated with this account.',
            ], 422));
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'role' => 'nullable|string',
            'status' => 'nullable|string|in:active,inactive',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        $query = User::with(['roles', 'userTeams'])
            ->where('organization_id', $organizationId)
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('slug', $request->role));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $perPage = min($request->input('per_page', 20), 100);
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users->map(fn ($u) => $this->formatUser($u)),
            'meta' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role_id' => 'nullable|exists:roles,id',
        ]);

        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        try {
            $newUser = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'organization_id' => $organizationId,
            ]);

            if ($request->filled('role_id')) {
                $newUser->roles()->attach($request->role_id, [
                    'organization_id' => $organizationId,
                ]);
            }

            $newUser->load(['roles', 'userTeams']);

            return response()->json([
                'success' => true,
                'message' => 'User created',
                'data' => $this->formatUser($newUser),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();
        $organizationId = $this->organizationId($request);

        if ($user->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $user->load(['roles', 'userTeams.team']);

        return response()->json([
            'success' => true,
            'data' => $this->formatUser($user),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,'.$user->id,
            'is_active' => 'nullable|boolean',
            'role_id' => 'nullable|exists:roles,id',
        ]);

        $currentUser = $request->user();
        $organizationId = $this->organizationId($request);

        if ($user->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        try {
            $user->update($request->only(['name', 'email', 'is_active']));

            if ($request->filled('role_id')) {
                $user->roles()->syncWithPivotValues([$request->role_id], [
                    'organization_id' => $organizationId,
                ]);
            }

            $user->load(['roles', 'userTeams']);

            return response()->json([
                'success' => true,
                'message' => 'User updated',
                'data' => $this->formatUser($user),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();
        $organizationId = $this->organizationId($request);

        if ($user->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        if ($user->id === $currentUser->id) {
            return response()->json(['success' => false, 'message' => 'Cannot delete yourself'], 422);
        }

        try {
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function activity(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();
        $organizationId = $this->organizationId($request);

        if ($user->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $logs = UserActivityLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $logs->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'properties' => $log->properties,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at->toIso8601String(),
            ]),
            'meta' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    public function updateAvatar(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|max:2048',
        ]);

        $currentUser = $request->user();
        $organizationId = $this->organizationId($request);

        if ($user->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        try {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->update(['profile_photo_path' => $path]);

            return response()->json([
                'success' => true,
                'message' => 'Avatar updated',
                'data' => ['avatar_url' => asset('storage/'.$path)],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $request->validate([
            'preferences' => 'required|array',
        ]);

        try {
            $user = $request->user();
            $current = $user->preferences ?? [];
            $user->update([
                'preferences' => array_merge($current, $request->preferences),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Preferences updated',
                'data' => $user->preferences,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active ?? true,
            'profile_photo_url' => $user->profile_photo_url,
            'roles' => $user->relationLoaded('roles')
                ? $user->roles->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'slug' => $r->slug,
                ])->values()
                : [],
            'teams' => $user->relationLoaded('userTeams')
                ? $user->userTeams->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                ])->values()
                : [],
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
