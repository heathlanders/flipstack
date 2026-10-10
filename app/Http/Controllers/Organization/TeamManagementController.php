<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TeamManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /**
     * Display teams index
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        $this->authorize('viewTeams', $organization);

        $query = Team::with(['users', 'teamLead'])
            ->forOrganization($organization->id);

        // Filters
        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('description', 'like', '%'.$request->search.'%');
            });
        }

        $teams = $query->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        // Transform teams data
        $teams->through(function ($team) use ($user) {
            return [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'description' => $team->description,
                'department' => $team->department,
                'department_color' => $team->getDepartmentColor(),
                'is_active' => $team->is_active,
                'max_members' => $team->max_members,
                'member_count' => $team->users()->count(),
                'team_lead' => $team->teamLead ? [
                    'id' => $team->teamLead->id,
                    'name' => $team->teamLead->name,
                    'email' => $team->teamLead->email,
                    'avatar' => $team->teamLead->profile_photo_url,
                ] : null,
                'created_at' => $team->created_at->diffForHumans(),
                'updated_at' => $team->updated_at->diffForHumans(),
                'can_edit' => $user->hasPermission('teams.edit'),
                'can_delete' => $user->hasPermission('teams.delete') && $team->users()->count() === 0,
                'can_manage_members' => $user->hasPermission('teams.manage_members'),
                'is_member' => $user->isMemberOfTeam($team),
                'is_team_lead' => $team->team_lead_id === $user->id,
            ];
        });

        // Summary statistics
        $stats = [
            'total_teams' => Team::forOrganization($organization->id)->count(),
            'active_teams' => Team::forOrganization($organization->id)->active()->count(),
            'departments' => Team::forOrganization($organization->id)
                ->select('department', DB::raw('count(*) as count'))
                ->groupBy('department')
                ->get()
                ->pluck('count', 'department'),
            'average_team_size' => $this->getAverageTeamSize($organization),
            'teams_at_capacity' => Team::forOrganization($organization->id)
                ->whereRaw('(SELECT COUNT(*) FROM user_teams WHERE team_id = teams.id AND is_active = true) >= max_members')
                ->count(),
            'teams_without_lead' => Team::forOrganization($organization->id)
                ->whereNull('team_lead_id')
                ->count(),
        ];

        return Inertia::render('Teams/Index', [
            'teams' => $teams,
            'stats' => $stats,
            'filters' => $request->only(['department', 'status', 'search']),
            'departments' => $this->getDepartmentOptions(),
            'can_create_team' => $user->hasPermission('teams.create'),
            'can_bulk_actions' => $user->hasPermission('teams.bulk_actions'),
        ]);
    }

    /**
     * Show team creation form
     */
    public function create()
    {
        $this->authorize('createTeam', auth()->user()->organization);

        $users = User::inOrganization(auth()->user()->organization_id)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'employee_id']);

        return Inertia::render('Teams/Create', [
            'departments' => $this->getDepartmentOptions(),
            'available_users' => $users,
            'available_roles' => Role::active()->get(['id', 'name', 'slug', 'description']),
        ]);
    }

    /**
     * Store new team
     */
    public function store(Request $request)
    {
        $this->authorize('createTeam', $request->user()->organization);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'department' => ['required', 'string'],
            'team_lead_id' => ['nullable', 'exists:users,id'],
            'max_members' => ['nullable', 'integer', 'min:1', 'max:100'],
            'goals' => ['array'],
            'settings' => ['array'],
            'initial_members' => ['array'],
            'initial_members.*' => ['exists:users,id'],
        ]);

        try {
            DB::transaction(function () use ($request) {
                $creator = $request->user();

                $team = Team::create([
                    'name' => $request->name,
                    'slug' => Str::slug($request->name),
                    'description' => $request->description,
                    'department' => $request->department,
                    'team_lead_id' => $request->team_lead_id,
                    'max_members' => $request->max_members ?? 20,
                    'organization_id' => $creator->organization_id,
                    'goals' => $request->goals ?? [],
                    'settings' => $request->settings ?? [],
                    'is_active' => true,
                    'created_by' => $creator->id,
                ]);

                // Add team lead as member with lead role if specified
                if ($request->team_lead_id) {
                    $leadRole = Role::where('slug', 'team_lead')->first() ??
                               Role::where('is_default', true)->first();

                    $team->addUser(User::find($request->team_lead_id), $leadRole);
                }

                // Add initial members
                if ($request->filled('initial_members')) {
                    $memberRole = Role::where('slug', 'team_member')->first() ??
                                 Role::where('is_default', true)->first();

                    foreach ($request->initial_members as $userId) {
                        if ($userId !== $request->team_lead_id) {
                            $team->addUser(User::find($userId), $memberRole);
                        }
                    }
                }

                // Log activity
                $creator->logActivity('team_created', "Created team: {$team->name}", [
                    'team_id' => $team->id,
                    'department' => $team->department,
                    'initial_member_count' => count($request->initial_members ?? []) + ($request->team_lead_id ? 1 : 0),
                ]);
            });

            return redirect()->route('teams.index')
                ->with('success', 'Team created successfully!');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to create team. Please try again.']);
        }
    }

    /**
     * Show team details
     */
    public function show(Team $team)
    {
        $this->authorize('viewTeam', $team);

        $team->load(['users.roles', 'teamLead', 'organization']);

        // Team members with roles
        $members = $team->users->map(function ($user) use ($team) {
            $userTeam = $user->userTeams()->where('team_id', $team->id)->first();
            $role = $user->getTeamRole($team);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'employee_id' => $user->employee_id,
                'avatar' => $user->profile_photo_url,
                'is_active' => $user->is_active && ($userTeam ? $userTeam->pivot->is_active : false),
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ],
                'joined_at' => $userTeam ? $userTeam->pivot->created_at->diffForHumans() : null,
                'is_team_lead' => $team->team_lead_id === $user->id,
            ];
        })->sortBy([
            ['is_team_lead', 'desc'],
            ['name', 'asc'],
        ])->values();

        return Inertia::render('Teams/Show', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'description' => $team->description,
                'department' => $team->department,
                'is_active' => $team->is_active,
                'max_members' => $team->max_members,
                'member_count' => $members->count(),
                'goals' => $team->goals ?? [],
                'settings' => $team->settings ?? [],
                'team_lead' => $team->teamLead ? [
                    'id' => $team->teamLead->id,
                    'name' => $team->teamLead->name,
                    'email' => $team->teamLead->email,
                    'avatar' => $team->teamLead->profile_photo_url,
                ] : null,
                'created_at' => $team->created_at->format('M j, Y'),
                'updated_at' => $team->updated_at->diffForHumans(),
            ],
            'members' => $members,
            'permissions' => [
                'can_edit' => auth()->user()->hasPermission('teams.edit'),
                'can_delete' => auth()->user()->hasPermission('teams.delete'),
                'can_manage_members' => auth()->user()->hasPermission('teams.manage_members'),
                'can_invite' => auth()->user()->hasPermission('teams.invite'),
            ],
            'available_users' => User::inOrganization($team->organization_id)
                ->active()
                ->whereNotIn('id', $members->pluck('id'))
                ->get(['id', 'name', 'email', 'employee_id']),
            'available_roles' => Role::active()->get(['id', 'name', 'slug', 'description']),
        ]);
    }

    /**
     * Show team edit form
     */
    public function edit(Team $team)
    {
        $this->authorize('update', $team);

        return Inertia::render('Teams/Edit', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'department' => $team->department,
                'team_lead_id' => $team->team_lead_id,
                'max_members' => $team->max_members,
                'goals' => $team->goals ?? [],
                'settings' => $team->settings ?? [],
                'is_active' => $team->is_active,
            ],
            'departments' => $this->getDepartmentOptions(),
            'available_users' => User::inOrganization($team->organization_id)
                ->active()
                ->get(['id', 'name', 'email', 'employee_id']),
            'current_members' => $team->users()->count(),
        ]);
    }

    /**
     * Update team
     */
    public function update(Request $request, Team $team)
    {
        $this->authorize('update', $team);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'department' => ['required', 'string'],
            'team_lead_id' => ['nullable', 'exists:users,id'],
            'max_members' => ['nullable', 'integer', 'min:1', 'max:100'],
            'goals' => ['array'],
            'settings' => ['array'],
            'is_active' => ['boolean'],
        ]);

        try {
            $team->update([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
                'department' => $request->department,
                'team_lead_id' => $request->team_lead_id,
                'max_members' => $request->max_members ?? $team->max_members,
                'goals' => $request->goals ?? [],
                'settings' => $request->settings ?? [],
                'is_active' => $request->is_active ?? true,
            ]);

            // Log activity
            $request->user()->logActivity('team_updated', "Updated team: {$team->name}", [
                'team_id' => $team->id,
                'changes' => $request->only(['name', 'department', 'team_lead_id', 'max_members']),
            ]);

            return redirect()->route('teams.show', $team)
                ->with('success', 'Team updated successfully!');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update team. Please try again.']);
        }
    }

    /**
     * Delete team
     */
    public function destroy(Request $request, Team $team)
    {
        $this->authorize('delete', $team);

        try {
            // Check if team has active members
            $memberCount = $team->users()->count();

            if ($memberCount > 0) {
                return back()->withErrors(['error' => 'Cannot delete team with active members. Please remove all members first.']);
            }

            $teamName = $team->name;
            $team->delete();

            // Log activity
            $request->user()->logActivity('team_deleted', "Deleted team: {$teamName}", [
                'team_id' => $team->id,
            ]);

            return redirect()->route('teams.index')
                ->with('success', 'Team deleted successfully.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete team.']);
        }
    }

    /**
     * Allow user to join a team
     */
    public function join(Request $request, Team $team)
    {
        $this->authorize('join', $team);

        $user = $request->user();

        try {
            // Check if user is already a member
            if ($user->isMemberOfTeam($team)) {
                if ($request->expectsJson()) {
                    return response()->json(['error' => 'You are already a member of this team.'], 422);
                }

                return back()->withErrors(['error' => 'You are already a member of this team.']);
            }

            // Verify user belongs to same organization
            if ($user->organization_id !== $team->organization_id) {
                if ($request->expectsJson()) {
                    return response()->json(['error' => 'You cannot join teams from other organizations.'], 422);
                }

                return back()->withErrors(['error' => 'You cannot join teams from other organizations.']);
            }

            // Check if team is at capacity
            if ($team->max_members && $team->users()->count() >= $team->max_members) {
                return back()->withErrors(['error' => 'This team is at maximum capacity.']);
            }

            // Get default member role
            $memberRole = Role::where('slug', 'team_member')->first() ??
                         Role::where('is_default', true)->first();

            if (! $memberRole) {
                if ($request->expectsJson()) {
                    return response()->json(['error' => 'Default member role not found.'], 500);
                }

                return back()->withErrors(['error' => 'Default member role not found.']);
            }

            // Add user to team
            $team->addUser($user, $memberRole);

            // Set as current team if user doesn't have one
            if (! $user->currentTeam) {
                $user->update(['current_team_id' => $team->id]);
                // Reload user to get fresh current team data
                $user->refresh();
            }

            // Log activity
            $user->logActivity('team_joined', "Joined team: {$team->name}", [
                'team_id' => $team->id,
                'role_id' => $memberRole->id,
            ]);

            // Handle AJAX vs regular requests
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "You have successfully joined {$team->name}!",
                    'team' => [
                        'id' => $team->id,
                        'name' => $team->name,
                        'is_member' => true,
                        'member_count' => $team->users()->count(),
                    ],
                ]);
            }

            return redirect()->route('teams.show', $team)
                ->with('success', "You have successfully joined {$team->name}!");

        } catch (\Exception $e) {
            \Log::error('Team join failed', [
                'user_id' => $user->id,
                'team_id' => $team->id,
                'error' => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json(['error' => 'Failed to join team. Please try again.'], 500);
            }

            return back()->withErrors(['error' => 'Failed to join team. Please try again.']);
        }
    }

    /**
     * Allow user to leave a team
     */
    public function leave(Request $request, Team $team)
    {
        $user = $request->user();

        try {
            // Check if user is a member
            if (! $user->isMemberOfTeam($team)) {
                return back()->withErrors(['error' => 'You are not a member of this team.']);
            }

            // Prevent team lead from leaving (they need to transfer leadership first)
            if ($team->team_lead_id === $user->id) {
                return back()->withErrors(['error' => 'Team leads cannot leave. Please transfer leadership first.']);
            }

            // Remove user from team
            $team->removeUser($user);

            // If this was their current team, find another team or set to null
            if ($user->current_team_id === $team->id) {
                $nextTeam = $user->teams()->where('teams.id', '!=', $team->id)->first();
                $user->update(['current_team_id' => $nextTeam ? $nextTeam->id : null]);
            }

            // Log activity
            $user->logActivity('team_left', "Left team: {$team->name}", [
                'team_id' => $team->id,
            ]);

            return redirect()->route('teams.index')
                ->with('success', "You have left {$team->name}.");

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to leave team. Please try again.']);
        }
    }

    /**
     * Add user to team
     */
    public function addMember(Request $request, Team $team)
    {
        $this->authorize('manageMembers', $team);

        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        try {
            $user = User::findOrFail($request->user_id);
            $role = Role::findOrFail($request->role_id);

            // Check if user is already a member
            if ($user->isMemberOfTeam($team)) {
                return back()->withErrors(['error' => 'User is already a member of this team.']);
            }

            // Verify user belongs to same organization
            if ($user->organization_id !== $team->organization_id) {
                return back()->withErrors(['error' => 'User does not belong to the same organization.']);
            }

            $team->addUser($user, $role);

            // Log activity
            $request->user()->logActivity('team_member_added', "Added {$user->name} to team: {$team->name}", [
                'team_id' => $team->id,
                'user_id' => $user->id,
                'role_id' => $role->id,
            ]);

            return back()->with('success', "Added {$user->name} to {$team->name}!");

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to add team member.']);
        }
    }

    /**
     * Remove user from team
     */
    public function removeMember(Request $request, Team $team, User $user)
    {
        $this->authorize('manageMembers', $team);

        try {
            if (! $user->isMemberOfTeam($team)) {
                return back()->withErrors(['error' => 'User is not a member of this team.']);
            }

            $team->removeUser($user);

            // Log activity
            $request->user()->logActivity('team_member_removed', "Removed {$user->name} from team: {$team->name}", [
                'team_id' => $team->id,
                'user_id' => $user->id,
            ]);

            return back()->with('success', "Removed {$user->name} from {$team->name}.");

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to remove team member.']);
        }
    }

    /**
     * Update a team member's role
     */
    public function updateMemberRole(Request $request, Team $team, User $user)
    {
        $this->authorize('manageMembers', $team);

        $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        if (! $user->isMemberOfTeam($team)) {
            return back()->withErrors(['error' => 'User is not a member of this team.']);
        }

        try {
            $role = Role::findOrFail($request->role_id);
            $user->changeTeamRole($team, $role);

            $request->user()->logActivity('team_member_role_updated', "Updated {$user->name}'s role in {$team->name} to {$role->name}", [
                'team_id' => $team->id,
                'user_id' => $user->id,
                'role_id' => $role->id,
            ]);

            return back()->with('success', "Updated {$user->name}'s role to {$role->name}.");

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update team member role.']);
        }
    }

    /**
     * Helper Methods
     */
    private function getDepartmentOptions()
    {
        return [
            ['value' => 'sourcing', 'label' => 'Sourcing & Buying', 'color' => '#3B82F6'],
            ['value' => 'listing', 'label' => 'Product Listing', 'color' => '#10B981'],
            ['value' => 'fulfillment', 'label' => 'Order Fulfillment', 'color' => '#F59E0B'],
            ['value' => 'management', 'label' => 'Management', 'color' => '#8B5CF6'],
            ['value' => 'support', 'label' => 'Customer Support', 'color' => '#EF4444'],
            ['value' => 'operations', 'label' => 'Operations', 'color' => '#6B7280'],
            ['value' => 'general', 'label' => 'General', 'color' => '#374151'],
        ];
    }

    private function getAverageTeamSize(Organization $organization)
    {
        $teams = Team::forOrganization($organization->id)->withCount('users')->get();

        return $teams->count() > 0 ? round($teams->avg('users_count'), 1) : 0;
    }
}
