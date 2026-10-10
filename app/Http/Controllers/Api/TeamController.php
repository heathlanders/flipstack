<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Organization\Team;
use App\Models\User\UserActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    use ResolvesOrganization;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'department' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        $query = Team::with(['teamLead:id,name', 'users:id,name'])
            ->where('organization_id', $organizationId)
            ->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'ilike', "%{$request->search}%");
        }

        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        $perPage = min($request->input('per_page', 20), 100);
        $teams = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $teams->map(fn ($t) => $this->formatTeam($t)),
            'meta' => [
                'total' => $teams->total(),
                'per_page' => $teams->perPage(),
                'current_page' => $teams->currentPage(),
                'last_page' => $teams->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'department' => 'nullable|string|max:255',
            'team_lead_id' => 'nullable|exists:users,id',
            'max_members' => 'nullable|integer|min:1',
        ]);

        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        try {
            $team = Team::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
                'department' => $request->department,
                'organization_id' => $organizationId,
                'user_id' => $user->id,
                'team_lead_id' => $request->team_lead_id,
                'max_members' => $request->max_members,
                'created_by' => $user->id,
                'is_active' => true,
            ]);

            $team->load(['teamLead:id,name', 'users:id,name']);

            return response()->json([
                'success' => true,
                'message' => 'Team created',
                'data' => $this->formatTeam($team),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(Request $request, Team $team): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($team->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Team not found'], 404);
        }

        $team->load(['teamLead:id,name,email', 'users:id,name,email']);

        return response()->json([
            'success' => true,
            'data' => $this->formatTeam($team),
        ]);
    }

    public function update(Request $request, Team $team): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'department' => 'nullable|string|max:255',
            'team_lead_id' => 'nullable|exists:users,id',
            'is_active' => 'nullable|boolean',
            'max_members' => 'nullable|integer|min:1',
        ]);

        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($team->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Team not found'], 404);
        }

        try {
            $team->update($request->only(['name', 'description', 'department', 'team_lead_id', 'is_active', 'max_members']));
            $team->load(['teamLead:id,name', 'users:id,name']);

            return response()->json([
                'success' => true,
                'message' => 'Team updated',
                'data' => $this->formatTeam($team),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroy(Request $request, Team $team): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($team->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Team not found'], 404);
        }

        if ($team->personal_team) {
            return response()->json(['success' => false, 'message' => 'Cannot delete personal team'], 422);
        }

        try {
            $team->delete();

            return response()->json([
                'success' => true,
                'message' => 'Team deleted',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function join(Request $request, Team $team): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($team->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Team not found'], 404);
        }

        if ($team->users()->where('user_id', $user->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Already a member'], 422);
        }

        if ($team->max_members && $team->users()->count() >= $team->max_members) {
            return response()->json(['success' => false, 'message' => 'Team is at capacity'], 422);
        }

        try {
            $team->users()->attach($user->id, ['role' => 'member']);

            return response()->json([
                'success' => true,
                'message' => 'Joined team '.$team->name,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function leave(Request $request, Team $team): JsonResponse
    {
        $user = $request->user();

        if (! $team->users()->where('user_id', $user->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Not a member'], 422);
        }

        if ($team->team_lead_id === $user->id) {
            return response()->json(['success' => false, 'message' => 'Team lead cannot leave. Transfer leadership first.'], 422);
        }

        try {
            $team->users()->detach($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Left team '.$team->name,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function members(Request $request, Team $team): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($team->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Team not found'], 404);
        }

        $members = $team->users()->get();

        return response()->json([
            'success' => true,
            'data' => $members->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'email' => $m->email,
                'role' => $m->pivot->role ?? 'member',
                'profile_photo_url' => $m->profile_photo_url,
            ])->values(),
        ]);
    }

    public function activity(Request $request, Team $team): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($team->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Team not found'], 404);
        }

        $memberIds = $team->users()->pluck('users.id');

        $logs = UserActivityLog::whereIn('user_id', $memberIds)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $logs->map(fn ($log) => [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'action' => $log->action,
                'description' => $log->description,
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

    private function formatTeam(Team $team): array
    {
        return [
            'id' => $team->id,
            'name' => $team->name,
            'slug' => $team->slug,
            'description' => $team->description,
            'department' => $team->department,
            'is_active' => $team->is_active,
            'max_members' => $team->max_members,
            'member_count' => $team->relationLoaded('users') ? $team->users->count() : null,
            'team_lead' => $team->teamLead ? [
                'id' => $team->teamLead->id,
                'name' => $team->teamLead->name,
            ] : null,
            'members' => $team->relationLoaded('users')
                ? $team->users->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'role' => $u->pivot->role ?? 'member',
                ])->values()
                : [],
            'created_at' => $team->created_at?->toIso8601String(),
        ];
    }
}
